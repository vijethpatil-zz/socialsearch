<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Loads crawlLog functions if needed
 */
require_once "utility.php";

/**
 * 
 * Code used to manage web archive files
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class WebArchive
{

    /**
     * Filename used to store the web archive.
     * @var string
     */
    var $filename;
    /**
     *
     * Current offset into the web archive the iterator for the archive is at
     * (at most one iterator / archive -- oh well)
     * @var int
     */
    var $iterator_pos;
    /**
     * Filter object used to compress/uncompress objects stored in archive
     * @var object
     */
    var $compressor;
    /**
     * number of item in archive
     * @var int
     */
    var $count;

    /**
     * version number of the current archive
     * @var float
     */
    var $version;

    /**
     * Says whether the archive is a string archive
     * @var bool
     */
    var $is_string;

    /**
     * If archive is stored as a string rather than persistently to disk
     * then $storage is used to hold the string
     * @var string
     */
    var $storage;

    /**
     * Version number to use in the WebArchive header if constructing a new
     * archive
     */
    const WEB_ARCHIVE_VERSION = 1.0;

    /**
     * Makes or initializes a WebArchive object using the supplied parameters
     *
     * @param string $fname filename to use to store archive to disk
     * @param string $compressor what kind of Compressor object should be
     *      used to read and write objects in the archive
     * @param bool $fast_construct do we read the info block of the web
     *      archive as part of the constructing process
     * @param bool $is_string says whether the archive stores to string
     *      rather than a file
     */
    function __construct($fname, $compressor, $fast_construct = false, 
        $is_string = false) 
    {
        $this->filename = $fname;
        $this->compressor = $compressor;
        $this->is_string = $is_string;
        if($this->is_string) {
            $this->storage = "";
            $this->iterator_pos = 0;
            $this->count = 0;
            return;
        }
        if(file_exists($fname)) {
            if(!$fast_construct) {
                $this->readInfoBlock();
            }
            $this->iterator_pos = 0;
        } else {
            $this->iterator_pos = 0;
            $this->count = 0;
            $fh =  fopen($this->filename, "w");
            $this->writeInfoBlock($fh);
            fclose($fh);
        }
    }

    /**
     * Read the info block associated with this web archive.
     * The info block is meta data for the archive stored at the end of
     * the WebArchive file. The particular meta is up to who is using
     * the web archive.
     * @return array the contents of the info block
     */
    function readInfoBlock()
    {
        if($this->is_string) return NULL;
        $fh =  fopen($this->filename, "r");
        $len = $this->seekEndObjects($fh);
        $info_string = fread($fh, $len);
        $info_block = unserialize($this->compressor->uncompress($info_string));
        $this->count = $info_block["count"];
        $this->version = $info_block["version"];
        if(isset($info_block["data"])) {
            return $info_block["data"];
        } else {
            return NULL;
        }
    }

    /**
     * Serializes and applies the compressor to an info block and write it at
     * the end of the web archive
     * The info block is meta data for the archive stored at the end of
     * the WebArchive file. The particular meta is up to who is using
     * the web archive; however, count and archive version number are always
     * stored
     *
     * @param resource $fh resource for the web archive file. If null
     *      the web archive is open first and close when the data is written
     * @param array &$data data to write into the info block of the archive
     */
    function writeInfoBlock($fh = NULL, &$data = NULL)
    {
        if($this->is_string) return;
        $compressed_int_len = $this->compressor->compressedIntLen();
        $open_flag = false;
        if($fh == NULL) {
            $open_flag = true;
            $fh =  fopen($this->filename, "r+");
            $this->seekEndObjects($fh);
        }
        $info_block = array();
        $info_block["version"] = self::WEB_ARCHIVE_VERSION;
        $info_block["count"] = $this->count;
        if($data != NULL) {
            $info_block['data'] = & $data;
        }
        $info_string = 
            $this->compressor->compress(serialize($info_block));
        $len = strlen($info_string) + $compressed_int_len;

        $offset = ftell($fh);
        ftruncate($fh, $offset);

        $out = $info_string.$this->compressor->compressInt($len);
        fwrite($fh, $out, $len);

        if($open_flag) {
            fclose($fh);
        }
    }

    /**
     * Seeks in the WebArchive file to the end of the last Object.
     *
     * The last $compressed_int_len bytes of a WebArchive say the length 
     * of an info block in bytes
     *
     * @param resource $fh resource for the WebArchive file
     * @return int offset length of info block
     */
    function seekEndObjects($fh)
    {
        if($this->is_string) {
            return strlen($this->storage);
        }
        $compressed_int_len = $this->compressor->compressedIntLen();
        fseek($fh, - $compressed_int_len, SEEK_END);
        $len_block = $this->compressor->uncompressInt(
            fread($fh, $compressed_int_len));
        fseek($fh, - ($len_block), SEEK_END);

        return $len_block - $compressed_int_len;
    }

    /**
     * Adds objects to the WebArchive
     *
     * @param string $offset_field field in objects to return the byte offset
     *      at which they were stored
     * @param array &$objects references to objects that will be stored
     *      the offset field in these references will be adjusted if 
     * @param array $data data to write in the WebArchive's info block
     * @param string $callback name of a callback 
     *      $callback($data, $new_objects, $offset_field)
     *      used to modify $data before it is written
     *      to the info block. For instance, we can add offset info to data.
     * @param bool $return_flag if true rather than adjust the offsets by
     *      reference, create copy objects and adjust their offsets anf return
     * @return mixed adjusted objects or void
     */
    function addObjects($offset_field, &$objects, 
        $data = NULL, $callback = NULL, $return_flag = true)
    {

        $is_string = $this->is_string;
        if(!$is_string) {
            $fh =  fopen($this->filename, "r+");

            $this->seekEndObjects($fh);

            $offset = ftell($fh);
            ftruncate($fh, $offset);
        } else {
            $offset = strlen($this->storage);
        }

        $out = "";

        if($return_flag) {
            $new_objects = $objects;
        } else {
            $new_objects = & $objects;
        }
        $num_objects = count($new_objects);
        $compressed_int_len = $this->compressor->compressedIntLen();
        for($i = 0; $i < $num_objects; $i++) {
            $new_objects[$i][$offset_field] = $offset;

            $file = serialize($new_objects[$i]);
            $compressed_file = $this->compressor->compress($file);
            $len = strlen($compressed_file);
            $out .= $this->compressor->compressInt($len).$compressed_file;
            $offset += $len + $compressed_int_len;
        }
        
        $this->count += $num_objects;

        if($is_string) {
            $this->storage .= $out;
        } else {
            fwrite($fh, $out, strlen($out));
        }

        if($data != NULL && $callback != NULL) {
            $data = $callback($data, $new_objects, $offset_field);
        }

        if(!$is_string) {
            $this->writeInfoBlock($fh, $data);
            fclose($fh);
        }

        if($return_flag) {
            return $new_objects;
        } else {
            return;
        }

    }

    /**
     * Open the web archive file associated with this WebArchive object.
     *
     * @param string $mode read/write mode to open file with
     * @return resource a file resource for the web archive
     */
    function open($mode = "r")
    {
        if($this->is_string) {
            return "is_string";
        }
        $fh = fopen($this->filename, $mode);
        return $fh;
    }

    /**
     * Closes a file handle (which should be of a web archive)
     */
    function close($fh)
    {
        if($this->is_string) return;
        fclose($fh);
    }

    /**
     * Gets $num many objects out of the web archive starting at byte $offset
     *
     * If the $next_flag is true the archive iterator is advance and if $fh
     * is not NULL then it is assumed to be an open resource pointing to the
     * archive (saving the time to open it).
     *
     * @param int $offset a valid byte offset into a web archive
     * @param int $num number of objects to return
     * @param bool $next_flag whether to advance the archive iterator
     * @param resource $fh either NULL or a file resource to the archive
     * @return array the $num objects beginning at $offset
     */
    function getObjects($offset, $num, $next_flag = true, $fh = NULL)
    {

        $open_flag = false;
        if($fh == NULL) {
            $fh =  $this->open();
            $open_flag = true;
        }
        $is_string = $this->is_string;

        $objects = array();
        $compressed_int_len = $this->compressor->compressedIntLen();
        if($is_string) {
            $storage_len = strlen($this->storage);
        }
        if((!$is_string &&fseek($fh, $offset) == 0 ) || ($is_string
            && $offset < $storage_len)) {

            for($i = 0; $i < $num; $i++) {
                if(!$is_string && feof($fh)) {break; }
                if($is_string && $offset >= $storage_len) {break; }

                $object = NULL;
                $compressed_len = ($is_string) 
                    ? substr($this->storage, $offset, $compressed_int_len)
                    : fread($fh, $compressed_int_len);

                $len = $this->compressor->uncompressInt($compressed_len);

                if($len > 0 && $len < MAX_ARCHIVE_OBJECT_SIZE) {
                    $compressed_file = ($is_string) 
                        ? substr($this->storage, $offset + $compressed_int_len,
                            $len)
                        : fread($fh, $len);
                    $file = $this->compressor->uncompress($compressed_file);
                    $object = @unserialize($file);

                    $offset += $compressed_int_len + $len;
                    $objects[] = array($offset, $object);
                } else {
                    crawlLog("Web archive saw blank line ".
                        "when looked for offset $offset");
                }
            }

            if($next_flag) {
                $this->iterator_pos = $offset;
            }
        }

        if($open_flag) {
            $this->close($fh);
        }
        
        return $objects;
    }

    /**
     * Returns $num many objects from the web archive starting at the current 
     * iterator position, leaving the iterator position unchanged
     *
     * @param int $num number of objects to return
     * @return array an array of objects from the web archive
     */
    function currentObjects($num)
    {
        return $this->getObjects($this->iterator_pos, $num, false);
    }

    /**
     * Returns $num many objects from the web archive starting at the 
     * current iterator position. The iterator is advance to the object 
     * after the last one returned
     *
     * @param int $num number of objects to return
     * @return array an array of objects from the web archive
     */
    function nextObjects($num)
    {
        return $this->getObjects($this->iterator_pos, $num);
    }

    /**
     * Resets the iterator for this web archive to the first object 
     * in the archive
     */
    function reset() 
    {
        $this->iterator_pos = 0;
    }

}
?>
