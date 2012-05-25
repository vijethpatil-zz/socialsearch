<?php

function main()
{
    global $argv;
    $path = $argv[1];
    $prefix = isset($argv[2]) ? $argv[2] : 'rec';
    $itr = new BZip2BlockIterator($path);
    $i = 1;
    while(($block = $itr->next_block($raw=true)) !== NULL) {
        $rec_name = sprintf("%s%05d.bz2", $prefix, $i);
        file_put_contents($rec_name, $block);
        echo "Recoverd block {$i}\n";
        $i++;
    }
}

class BZip2BlockIterator
{
    const MAGIC           = 'BZh';
    const BLOCK_HEADER    = "\x31\x41\x59\x26\x53\x59";
    const BLOCK_ENDMARK   = "\x17\x72\x45\x38\x50\x90";

    // Blocks are NOT byte-aligned, so the block header (and endmark) may show 
    // up shifted right by 0-8 bits in various places throughout the file. This 
    // regular expression matches any of the possible shifts for both the block 
    // header and the block endmark.
    const BLOCK_LEADER_RE = '
        /
         \x41\x59\x26\x53\x59 | \xa0\xac\x93\x29\xac | \x50\x56\x49\x94\xd6
        |\x28\x2b\x24\xca\x6b | \x14\x15\x92\x65\x35 | \x8a\x0a\xc9\x32\x9a
        |\xc5\x05\x64\x99\x4d | \x62\x82\xb2\x4c\xa6

        |\x72\x45\x38\x50\x90 | \xb9\x22\x9c\x28\x48 | \xdc\x91\x4e\x14\x24
        |\xee\x48\xa7\x0a\x12 | \x77\x24\x53\x85\x09 | \xbb\x92\x29\xc2\x84
        |\x5d\xc9\x14\xe1\x42 | \x2e\xe4\x8a\x70\xa1
        /x';

    static $header_info = array(
        "\x41" => array(0,  true), "\xa0" => array(1,  true),
        "\x50" => array(2,  true), "\x28" => array(3,  true),
        "\x14" => array(4,  true), "\x8a" => array(5,  true),
        "\xc5" => array(6,  true), "\x62" => array(7,  true),

        "\x72" => array(0, false), "\xb9" => array(1, false),
        "\xdc" => array(2, false), "\xee" => array(3, false),
        "\x77" => array(4, false), "\xbb" => array(5, false),
        "\x5d" => array(6, false), "\x2e" => array(7, false)
    );

    var $fd = null;
    var $file_offset = 0;
    var $buffer = '';
    var $block = '';
    var $bits = 0;
    var $num_extra_bits = 0;
    var $shift = 0;

    function __construct($path)
    {
        $this->path = $path;
        $this->fd = fopen($this->path, 'rb');
        $this->header = fread($this->fd, 4);
        if(substr($this->header, 0, 3) != self::MAGIC) {
            throw new Exception('Bad bz2 magic number. Not a bz2 file?');
        }
        $this->block = fread($this->fd, 6);
        if($this->block != self::BLOCK_HEADER) {
            throw new Exception('Bad bz2 block header');
        }
        $this->file_offset = 10;
    }

    function __wakeup()
    {
        $this->fd = fopen($this->path, 'rb');
        fseek($this->fd, $this->file_offset);
    }

    function is_eof()
    {
        return feof($this->fd);
    }

    function close()
    {
        return fclose($this->fd);
    }

    function next_block($raw=false)
    {
        $recovered_block = NULL;
        while(!feof($this->fd)) {
            $next_chunk = fread($this->fd, 8192);
            $this->file_offset += strlen($next_chunk);
            $this->buffer .= $next_chunk;

            $match = preg_match(
                self::BLOCK_LEADER_RE,
                $this->buffer,
                $matches,
                PREG_OFFSET_CAPTURE);
            if($match) {
                // $pos is the position of the SECOND byte of the magic number
                // (plus some part of the first byte for a non-zero new_shift).
                $pos = $matches[0][1];

                // The new_shift is the number of bits by which the magic 
                // number for the next block has been shifted right.
                list($new_shift, $is_start) =
                    self::$header_info[$this->buffer[$pos]];

                // The new number of extra bits is what's left in a byte after 
                // the new shift. For example, if we have 10|001011 as the byte 
                // that begins the next block's header, where the vertical bar 
                // represents the beginning of the header bits, the new shift 
                // is 2, and after we byte-align the new header to the left 
                // there will always be 6 extra bits waiting for two bits to 
                // form a byte to be added to the next block.
                $new_num_extra_bits = $new_shift == 0 ? 0 : 8 - $new_shift;

                if($new_shift == 0) {
                    $tail_bits = $new_bits = 0;
                    $header_end = 5;
                    $new_header = substr($this->buffer, $pos - 1, 6);
                    $new_block = $new_header;
                } else {
                    $byte = ord($this->buffer[$pos-1]);
                    $tail_bits = $byte & (((0x1 << $new_shift) - 1) << 
                        (8 - $new_shift));
                    $new_bits = ($byte << $new_shift) & 0xff;
                    $header_end = 6;
                    $new_block = '';
                    $new_header = substr($this->buffer, $pos, 6);
                    self::pack_left($new_block, $new_bits, $new_header,
                        $new_num_extra_bits);
                }

                // Make sure all six header bytes match.
                if($is_start && $new_block != self::BLOCK_HEADER ||
                        !$is_start && $new_block != self::BLOCK_ENDMARK) {
                    $unmatched = substr($this->buffer, 0, $pos + 6);
                    $keep = substr($this->buffer, $pos + 6);
                    self::pack_left($this->block, $this->bits, $unmatched,
                        $this->num_extra_bits);
                    continue;
                }

                // Copy and shift the last chunk of bytes from the previous 
                // block before adding the block trailer.
                $block_tail = substr($this->buffer, 0, $pos - 1);
                self::pack_left($this->block, $this->bits, $block_tail,
                    $this->num_extra_bits);

                // We need to combine the non-header tail bits from the most 
                // significant end of the last byte before the next block's 
                // header with whatever extra bits are left over from shifting 
                // the body of the previous block.
                $bits_left = 8 - $this->num_extra_bits;
                if($new_shift >= $bits_left) {
                    $this->bits |= ($tail_bits >> $this->num_extra_bits);
                    $this->block .= chr($this->bits);
                    $this->bits = ($tail_bits << $bits_left) & 0xff;
                    $this->num_extra_bits = $new_shift - $bits_left;
                } else {
                    $this->bits |= ($tail_bits >> $this->num_extra_bits);
                    $this->num_extra_bits = $this->num_extra_bits + 
                        $new_shift;
                }

                // The last block is marked by a different header (sqrt(pi)), 
                // and a CRC for the entire "file", which is just the CRC for 
                // the first block, since there's only one block.
                $trailer = "\x17\x72\x45\x38\x50\x90".
                    substr($this->block, 6, 4);
                self::pack_left($this->block, $this->bits, $trailer,
                    $this->num_extra_bits);
                if($this->num_extra_bits != 0) {
                    $this->block .= chr($this->bits);
                }

                $recovered_block = $this->header.$this->block;
                $this->block = $new_block;

                // Keep everything after the end of the header for the next 
                // block in the buffer.
                $this->buffer = substr($this->buffer, $pos + $header_end);

                $this->bits = $new_bits;
                $this->shift = $new_shift;
                $this->num_extra_bits = $new_num_extra_bits;

                break;
            } else {
                // No match, but we may have just missed a header by a byte, so 
                // we need to keep the last six bytes in the buffer so that we 
                // have a chance to get the full header on the next round.
                $unmatched = substr($this->buffer, 0, -6);
                self::pack_left($this->block, $this->bits, $unmatched,
                    $this->num_extra_bits);
                $this->buffer = substr($this->buffer, -6);
            }
        }

        if(!$raw) {
            return bzdecompress($recovered_block);
        } else {
            return $recovered_block;
        }
    }

    static function pack_left(&$block, &$bits, $bytes, $num_extra_bits)
    {
        if($num_extra_bits == 0) {
            $block .= $bytes;
            return;
        }
        $num_bytes = strlen($bytes);
        for($i = 0; $i < $num_bytes; $i++) {
            $byte = ord($bytes[$i]);
            $bits |= ($byte >> $num_extra_bits);
            $block .= chr($bits);
            $bits = ($byte << (8 - $num_extra_bits)) & 0xff;
        }
    }

    static function hexdump($bytes)
    {
        $out = array();
        $bytes = str_split($bytes);
        foreach($bytes as $byte) {
            $out[] = sprintf("%02x", ord($byte));
        }
        return implode(' ', $out);
    }
}

// Only run main if this script is called directly from the command line.
if(isset($argv[0]) && realpath($argv[0]) == __FILE__) {
    main();
}
