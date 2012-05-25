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
 *Loads common constants for web crawling
 */
require_once  BASE_DIR.'/lib/crawl_constants.php';

/**
 * Data structure used to store for entries of the form:
 * word id, index shard generation, posting list offset, and length of
 * posting list. It has entries for all words stored in a given
 * IndexArchiveBundle. There might be multiple entries for a given word_id
 * if it occurs in more than one index shard in the given IndexArchiveBundle.
 *
 * In terms of file structure, a dictionary is stored a folder consisting of
 * 256 subfolders. Each subfolder is used to store the word_ids beginning with
 * a particular character. Within a folder are files of various tier levels 
 * representing the data stored. As crawling proceeds words from a shard are
 * added to the dictionary in files of tier level 0 either with suffix A or B.
 * If it is detected that both an A and a B file of a given tier level exist,
 * then the results of these two files are merged to a new file at one tier 
 * level up . The old files are then deleted. This process is applied 
 * recursively until there is at most an A file on each level.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
 
class IndexDictionary implements CrawlConstants
{
    /**
     * Folder name to use for this IndexDictionary
     * @var string
     */
    var $dir_name;

    var $hash_name;
    /**
     * Array of file handle for files in the dictionary. Members are used
     * to read files to look up words.
     *
     * @var resource
     */
    var $fhs;

    /**
     * Array of file lengths for files in the dictionary. Use so don't try to
     * seek past end of files
     *
     * @var int
     */
    var $file_lens;

    /**
     * An cached array of disk blocks for an index dictionary that has not
     * been completely loaded into memory.
     * @var array
     */
    var $blocks;

    /**
     * The highest tiered index in the IndexDictionary
     * @var int
     */
    var $max_tier;

    /**
     * When merging two files on a given dictionary tier. This is the max number
     * of bytes to read in one go. (Must be divisible by WORD_ITEM_LEN)
     */
     const SEGMENT_SIZE = 20000000;

    /**
     * Size in bytes of one block in IndexDictionary
     */
    const DICT_BLOCK_SIZE = 4096;

    /**
     * Size of an item in the prefix index used to look up words. 
     * If the sub-dir was 65 (ASCII A), and the second char  was also
     * ASCII 65, then the corresonding prefix record would be the
     * offset to the first word_id beginning with AA, followed by the
     * number of such AA records.
     */
    const PREFIX_ITEM_SIZE = 8;
    /**
     * Number of possible prefix records (number of possible values for
     * second char of a word id)
     */
    const NUM_PREFIX_LETTERS = 256;
    /**
     * One dictionary file represents the words whose ids begin with a
     * fixed char. Amongst these id, the prefix index gives offsets for 
     * where id's with a given second char start. The total length of the 
     * records needed is PREFIX_ITEM_SIZE * NUM_PREFIX_LETTERS.
     */
    const PREFIX_HEADER_SIZE = 2048;

    /**
     * Makes an index dictionary with the given name
     *
     * @param string $dir_name the directory name to store the index dictionary
     *      in
     */
    function __construct($dir_name)
    {
        $this->dir_name = $dir_name;
        $this->hash_name = crawlHash($dir_name);
        if(!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
            IndexDictionary::makePrefixLetters($this->dir_name);
            $this->max_tier = 0;
        } else {
            $this->max_tier = unserialize(
                file_get_contents($this->dir_name."/max_tier.txt"));
        }
    }

    /**
     *
     */
    static function makePrefixLetters($dir_name)
    {
        for($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {
            mkdir($dir_name."/$i");
        }
        file_put_contents($dir_name."/max_tier.txt", 
            serialize(0));
    }

    /**
     * Adds the words in the provided IndexShard to the dictionary.
     * Merges tiers as needed.
     *
     * @param object $index_shard the shard to add the word to the dictionary
     *      with
     * @param object $callback object with join function to be 
     *      called if process is taking too  long
     */
    function addShardDictionary($index_shard, $callback = NULL) 
    {
        $out_slot = "A";
        if(file_exists($this->dir_name."/0/0A.dic")) {
            $out_slot ="B";
        }
        crawlLog("Adding shard data to dictionary files...");
        $header = $index_shard->getShardHeader();
        $base_offset = IndexShard::HEADER_LENGTH + $index_shard->prefixes_len;
        $prefix_string = $index_shard->getShardSubstring(
            IndexShard::HEADER_LENGTH, $index_shard->prefixes_len);
        $next_offset = $base_offset;
        $word_item_len = IndexShard::WORD_ITEM_LEN;
        for($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {

            $last_offset = $next_offset;
            // adjust prefix values 
            $first_offset_flag = true;
            $last_set = -1;
            for($j = 0; $j < self::NUM_PREFIX_LETTERS; $j++) {
                $prefix_info = $this->extractPrefixRecord($prefix_string, 
                        ($i << 8) + $j);
                if($prefix_info !== false) {
                    list($offset, $count) = $prefix_info;
                    if($first_offset_flag) {
                        $first_offset = $offset;
                        $first_offset_flag = false;
                    }
                    $offset -= $first_offset;
                    $out = packInt($offset) . packInt($count);
                    $last_set = $j;
                    $last_out = $prefix_info;
                    charCopy($out, $prefix_string, 
                        (($i << 8) + $j) * self::PREFIX_ITEM_SIZE,
                        self::PREFIX_ITEM_SIZE);
                }
            }
            // write prefixes
            $fh = fopen($this->dir_name."/$i/0".$out_slot.".dic", "wb");
            fwrite($fh, substr($prefix_string, 
                $i*self::PREFIX_HEADER_SIZE, self::PREFIX_HEADER_SIZE));
            $j = self::NUM_PREFIX_LETTERS;
            // write words
            if($last_set >= 0) {
                list($offset, $count) = $last_out;
                $next_offset = $base_offset + $offset + 
                    $count * IndexShard::WORD_ITEM_LEN;
                fwrite($fh, $index_shard->getShardSubstring($last_offset, 
                    $next_offset - $last_offset));
            } 
            fclose($fh);
        }
        unset($prefix_string);
        crawlLog("Incrementally Merging tiers of dictionary");
        // log merge tiers if needed
        $tier = 0;
        while($out_slot == "B") {
            if($callback != NULL) {
                $callback->join();
            }
            $out_slot = "A";
            if(file_exists($this->dir_name."/0/".($tier + 1)."A.dic")) {
                $out_slot ="B";
            }
            $this->mergeTier($tier, $out_slot);
            $tier++;
            if($tier > $this->max_tier) {
                $this->max_tier = $tier;
                file_put_contents($this->dir_name."/max_tier.txt", 
                    serialize($this->max_tier));
            }
        }
        crawlLog("...Done Incremental (Not Full) Merging of Dictionary Tiers");

    }

    /**
     * Merges for each first letter subdirectory, the $tier pair of files
     * of dictinary words. The output is stored in $out_slot.
     *
     * @param int $tier tier level to perform the merge of files at
     * @param string either "A" or "B", the suffix but not extension of the
     *      file one tier up to create with the merged results.
     */
    function mergeTier($tier, $out_slot)
    {
        for($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {
            $this-> mergeTierFiles($i, $tier, $out_slot);
        }
    }

    /**
     * For a fixed prefix directory merges the $tier pair of files
     * of dictinary words. The output is stored in $out_slot.
     *
     * @param int $prefix which prefix directory to perform the merge of files
     * @param int $tier tier level to perform the merge of files at
     * @param string either "A" or "B", the suffix but not extension of the
     *      file one tier up to create with the merged results.
     */
    function mergeTierFiles($prefix, $tier, $out_slot)
    {
        $file_a = $this->dir_name."/$prefix/$tier"."A.dic";
        $file_b = $this->dir_name."/$prefix/$tier"."B.dic";
        $size_a = filesize($file_a);
        $size_b = filesize($file_b);

        $fhA = fopen( $file_a, "rb");
        $fhB = fopen( $file_b, "rb");
        $fhOut = fopen( $this->dir_name."/$prefix/".($tier + 1).
            "$out_slot.dic", "wb");
        $prefix_string_a = fread($fhA, self::PREFIX_HEADER_SIZE);
        $prefix_string_b = fread($fhB, self::PREFIX_HEADER_SIZE);
        $prefix_string_out = "";
        $offset = 0;
        for($j = 0; $j < self::NUM_PREFIX_LETTERS; $j++) {
            $record_a = $this->extractPrefixRecord($prefix_string_a, $j);
            $record_b = $this->extractPrefixRecord($prefix_string_b, $j);
            if($record_a === false && $record_b === false) {
                $prefix_string_out .= IndexShard::BLANK;
            } else if($record_a === false){
                $prefix_string_out .= 
                    $this->makePrefixRecord($offset, $record_b[1]);
                $offset += $record_b[1] * IndexShard::WORD_ITEM_LEN;
            } else if($record_b === false){
                $prefix_string_out .= 
                    $this->makePrefixRecord($offset, $record_a[1]);
                $offset += $record_a[1] * IndexShard::WORD_ITEM_LEN;
            } else {
                $count = $record_a[1] + $record_b[1];
                $prefix_string_out .= 
                    $this->makePrefixRecord($offset, $count);
                $offset += $count * IndexShard::WORD_ITEM_LEN;
            }
        }
        fwrite($fhOut, $prefix_string_out);
        $remaining_a = $size_a - self::PREFIX_HEADER_SIZE;
        $remaining_b = $size_b - self::PREFIX_HEADER_SIZE;
        $done = false;
        $work_string_a = "";
        $read_size_a = 0;
        $offset_a = 0;
        $work_string_b = "";
        $read_size_b = 0;
        $offset_b = 0;
        $out = "";
        $out_len = 0;

        while($remaining_a > 0 || $remaining_b > 0 ||
            $offset_a < $read_size_a || $offset_b < $read_size_b) {
            if($offset_a >= $read_size_a && $remaining_a > 0) {
                $read_size_a = min($remaining_a, self::SEGMENT_SIZE);
                $work_string_a = fread($fhA, $read_size_a);
                $remaining_a -= $read_size_a;
                $offset_a = 0;
            }
            if($offset_b >= $read_size_b && $remaining_b > 0) {
                $read_size_b = min($remaining_b, self::SEGMENT_SIZE);
                $work_string_b = fread($fhB, $read_size_b);
                $remaining_b -= $read_size_b;
                $offset_b = 0;
            }
            if($offset_a < $read_size_a) {
                $record_a = substr($work_string_a, $offset_a, 
                    IndexShard::WORD_ITEM_LEN);
            }
            if($offset_b < $read_size_b) {
                $record_b = substr($work_string_b, $offset_b, 
                    IndexShard::WORD_ITEM_LEN);
            }
            if($offset_b >= $read_size_b) {
                $out .= $record_a;
                $offset_a += IndexShard::WORD_ITEM_LEN;
            } else if ($offset_a >= $read_size_a) {
                $out .= $record_b;
                $offset_b += IndexShard::WORD_ITEM_LEN;
            } else if ($this->recordCmp($record_a, $record_b) < 0){
                $out .= $record_a;
                $offset_a += IndexShard::WORD_ITEM_LEN;
            } else {
                $out .= $record_b;
                $offset_b += IndexShard::WORD_ITEM_LEN;
            }
            $out_len += IndexShard::WORD_ITEM_LEN;
            if($out_len >=  self::SEGMENT_SIZE) {
                fwrite($fhOut, $out);
                $out = "";
                $out_len = 0;
            }
        }
        fwrite($fhOut, $out);
        fclose($fhA);
        fclose($fhB);
        unlink($file_a);
        unlink($file_b);
        fclose($fhOut);
    }

    /**
     * Does a lexicographical comparison of the word_ids of two word records.
     *
     * @param string $record_a
     * @param string $record_b
     * @return int less than 0 if $record_a less than $record_b; 
     *      greater than 0 if $record_b is less than $record_a; 0 otherwise
     */
    function recordCmp($record_a, $record_b) 
    {
        return strcmp(substr($record_a, 0, IndexShard::WORD_KEY_LEN), 
            substr($record_b, 0,  IndexShard::WORD_KEY_LEN));
    }

    /**
     * Returns the $record_num'th prefix record from $prefix_string
     *
     * @param string $prefix_string string to get record from
     * @param int $record_num which record to extract
     * @return array $offset, $count  array
     */
    function extractPrefixRecord(&$prefix_string, $record_num)
    {

        $record = substr($prefix_string, self::PREFIX_ITEM_SIZE*$record_num,
             self::PREFIX_ITEM_SIZE);
        if($record == IndexShard::BLANK) {
            return false;
        }
        $offset = unpackInt(substr($record, 0, 4));
        $count = unpackInt(substr($record, 4, 4));
        return array($offset, $count);
    }

    /**
     * Makes a prefix record string out of an offset and count (packs and 
     * concatenates).
     *
     * @param int $offset byte offset into words for the prefix record
     * @param int $count number of word with that prefix
     * @return string the packed record
     */
    function makePrefixRecord($offset, $count)
    {
        return pack("N", $offset).pack("N", $count);
    }

    /**
     * Merges for each tier and for each first letter subdirectory, 
     * the $tier pair of (A and B) files  of dictionary words. If max_tier has 
     * not been reached but only one of the two tier files is present then that 
     * file is renamed with a name one tier higher. The output in all cases is 
     * stored in file ending with A or B one tier up. B is used if an A file is
     * already present.
     * @param object $callback object with join function to be 
     *      called if process is taking too long
     * @param int $max_tier the maximum tier to merge to merge till --
     *      if not set then $this->max_tier used. Otherwise, one would
     *      typically set to a value bigger than $this->max_tier
     */
    function mergeAllTiers($callback = NULL, $max_tier = -1)
    {
        $new_tier = false;

        crawlLog("Starting Full Merge of Dictionary Tiers");

        if($max_tier == -1) {
            $max_tier = $this->max_tier;
        }

        for($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {
            for($j = 0; $j <= $max_tier; $j++) {
                crawlLog("...Processing Prefix Number $i Tier $j Max Tier ".
                    $max_tier);
                if($callback != NULL) {
                    $callback->join();
                }
                $a_exists = file_exists($this->dir_name."/$i/".$j."A.dic");
                $b_exists = file_exists($this->dir_name."/$i/".$j."B.dic");
                $higher_a = file_exists($this->dir_name."/$i/".($j+1)."A.dic");
                if($a_exists && $b_exists) {
                    $out_slot = ($higher_a) ? "B" : "A";
                    $this->mergeTierFiles($i, $j, $out_slot);
                    if($j == $max_tier) {$new_tier = true;}
                } else if ($a_exists && $higher_a) {
                    rename($this->dir_name."/$i/".$j."A.dic", 
                        $this->dir_name."/$i/".($j + 1)."B.dic");
                } else if ($a_exists && $j < $max_tier) {
                    rename($this->dir_name."/$i/".$j."A.dic", 
                        $this->dir_name."/$i/".($j + 1)."A.dic");
                }
            }
        }
        if($new_tier) {
            $max_tier++;
            file_put_contents($this->dir_name."/max_tier.txt", 
                serialize($max_tier));
            $this->max_tier = $max_tier;
        }
        crawlLog("...End Full Merge of Dictionary Tiers");
    }

    /**
     * For each index shard generation a word occurred in, return as part of
     * array, an array entry of the form generation first offset, l
     * Last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word.
     *
     * @param string $word_id id of the word one wants to look up
     * @param bool $raw whether the id is our version of base64 encoded or not
     * @param bool $extract whether to extract an array of entries or to just
     *      return the word info as a string
     * @return mixed an array of entries of the form 
     *      generation, first offset, last offset, count or
     *      just a string of the word_info data if $extract is false 
     */
     function getWordInfo($word_id, $raw = false, $extract = true)
     {
        if(strlen($word_id) < IndexShard::WORD_KEY_LEN) {
            return false;
        }
        if($raw == false) {
            //get rid of out modified base64 encoding
            $word_id = unbase64Hash($word_id);
        }

        $word_item_len = IndexShard::WORD_ITEM_LEN;
        $word_data_len = IndexShard::WORD_ITEM_LEN - IndexShard::WORD_KEY_LEN;
        $file_num = ord($word_id[0]);

        $prefix = ord($word_id[1]);
        $prefix_info = $this->getDictSubstring($file_num,
            self::PREFIX_ITEM_SIZE*$prefix, self::PREFIX_ITEM_SIZE);
        if($prefix_info == IndexShard::BLANK) {
            return false;
        }

        $offset = unpackInt(substr($prefix_info, 0, 4));
        $high = unpackInt(substr($prefix_info, 4, 4)) - 1;

        $start = self::PREFIX_HEADER_SIZE  + $offset;
        $low = 0;
        $check_loc = (($low + $high) >> 1);
        $found = false;
        // find a record with word id
        do {
            $old_check_loc = $check_loc;

            $word_string = $this->getDictSubstring($file_num, $start + 
                $check_loc * $word_item_len, $word_item_len);

            if($word_string == false) {return false;}
            $id = substr($word_string, 0, IndexShard::WORD_KEY_LEN);
            $cmp = strcmp($word_id, $id);
            if($cmp === 0) {
                $found = true;
                break;
            } else if ($cmp < 0) {
                $high = $check_loc;
                $check_loc = (($low + $check_loc) >> 1);
            } else {
                if($check_loc + 1 == $high) {
                    $check_loc++;
                }
                $low = $check_loc;
                $check_loc = (($high + $check_loc) >> 1);
            }
        } while($old_check_loc != $check_loc);

        if(!$found) {
            return false;
        }
        //now extract the info
        $word_string = substr($word_string, IndexShard::WORD_KEY_LEN);
        if($extract) {
            $info = array();
            $info[0]=IndexShard::getWordInfoFromString($word_string, true);
        } else {
            $info = $word_string;
        }
        //up to first record with word id 
        $test_loc = $check_loc - 1;
        $start_loc = $check_loc;

        while ($test_loc >= $low) {
            $word_string = $this->getDictSubstring($file_num, $start + 
                $test_loc * $word_item_len, $word_item_len);
            if($word_string == "" ) break;
            $id = substr($word_string, 0, IndexShard::WORD_KEY_LEN);
            if(strcmp($word_id, $id) != 0 ) break;
            $start_loc = $test_loc;
            $test_loc--;
            $ws = substr($word_string, IndexShard::WORD_KEY_LEN);
            if($extract) {
                $tmp = IndexShard::getWordInfoFromString($ws, true);
                array_push($info, $tmp);
            } else {
                $info = $ws . $info;
            }
        }
        //until last record with word id 

        $test_loc = $check_loc + 1;

        while ($test_loc <= $high) {
            $word_string = $this->getDictSubstring($file_num, $start + 
                $test_loc * $word_item_len, $word_item_len);
            if($word_string == "" ) break;
            $id = substr($word_string, 0, IndexShard::WORD_KEY_LEN);
            if(strcmp($word_id, $id) != 0 ) break;
            $test_loc++;
            $ws = substr($word_string, IndexShard::WORD_KEY_LEN);
            if($extract) {
                $tmp = IndexShard::getWordInfoFromString($ws, true);
                array_unshift($info, $tmp);
            } else {
                $info .= $ws;
            }
        }
        return $info;
    }

    /**
     *  Given an array of $key => $word_id associations returns an array of
     *  $key => $num_docs of that $word_id
     *
     *  @param array &$key_words associative array of $key => $word_id's
     *  @return array $key => $num_docs associations
     */
     function getNumDocsArray(&$key_words)
     {
        $file_key_words = array();
        foreach($key_words as $key => $word_id) {
            $file_key_words[ord($word_id[0])][$key] = $word_id;
        }
        $num_docs_array = array();
        foreach($file_key_words as $file_num => $k_words) {
            foreach($k_words as $key => $word_id) {
                $info = $this->getWordInfo($word_id, true);
                $num_generations = count($info);
                $num_docs = 0;
                for($i = 0; $i < $num_generations; $i++) {
                    $num_docs += $info[$i][3];
                }
                $num_docs_array[$key] = $num_docs;
            }
        }
        return $num_docs_array;
     }

    /**
     *  Gets from disk $len many bytes beginning at $offset from the
     *  $file_num prefix file in the index dictionary
     *
     * @param int $file_num which prefix file to read from (always reads 
     *      a file at the max_tier level)
     * @param int $offset byte offset to start reading from
     * @param int $len number of bytes to read
     * @return string data from that location  in the shard
     */
    function getDictSubstring($file_num, $offset, $len)
    {
        $block_offset = (floor($offset/self::DICT_BLOCK_SIZE) *
            self::DICT_BLOCK_SIZE);
        $start_loc = $offset - $block_offset;
        $substring = "";
        do {
            $data = $this->readBlockDictAtOffset($file_num, $block_offset);
            if($data === false) {return $substring;}
            $block_offset += self::DICT_BLOCK_SIZE;
            $substring .= substr($data, $start_loc);
            $start_loc = 0;
        } while (strlen($substring) < $len);
        return substr($substring, 0, $len);
    }


    /**
     * Reads DICT_BLOCK_SIZE bytes from the prefix file $file_num beginning
     * at byte offset $bytes
     *
     * @param int $file_num which dictionary file (given by first letter prefix)
     *      to read from
     * @param int $bytes byte offset to start reading from
     * @return &string data fromIndexShard file
     */
    function &readBlockDictAtOffset($file_num, $bytes)
    {
        $false = false;
        if(isset($this->blocks[$file_num][$bytes])) {
            return $this->blocks[$file_num][$bytes];
        }
        if(!isset($this->fhs[$file_num]) || $this->fhs[$file_num] === NULL) {
            $file_name = $this->dir_name. "/$file_num/".$this->max_tier."A.dic";
            if(!file_exists($file_name)) return $false;
            $this->fhs[$file_num] = fopen($file_name, "rb");
            if($this->fhs[$file_num] === false) return $false;
            $this->file_lens[$file_num] = filesize($file_name);
        }
        if($bytes >= $this->file_lens[$file_num]) {
            
            return $false;
        }
        $seek = fseek($this->fhs[$file_num], $bytes, SEEK_SET);
        if($seek < 0) {
            return $false;
        }
        $this->blocks[$file_num][$bytes] = fread($this->fhs[$file_num], 
            self::DICT_BLOCK_SIZE);

        return $this->blocks[$file_num][$bytes];
    }


}
 ?>
