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
 * A library of string, log, hash, time, and conversion functions
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
 * Copies from $source string beginning at position $start, $length many
 * bytes to destination string
 *
 * @param string $source  string to copy from
 * @param string &$destination string to copy to
 * @param int $start starting offset
 * @param int $length number of bytes to copy
 */
function charCopy($source, &$destination, $start, $length) 
{
    $endk = $length - 1;
    $end = $start + $endk;
    for($j = $end, $k = $endk; $j >= $start; $j--, $k--) {
        $destination[$j] = $source[$k]; 
    }
}

/**
 *  Encodes an integer using variable byte coding.
 *
 *  @param int $pos_int integer to encode
 *  @return string a string of 1-5 chars depending on how bit $pos_int was
 */
function vByteEncode($pos_int) 
{
    $result = chr($pos_int & 127);
    $pos_int >>= 7;
    while($pos_int > 0){
        $result .= chr(128 | ($pos_int & 127));
        $pos_int >>= 7;
    }
    return $result;
}

/**
 *  Decodes from a string using variable byte coding an integer.
 *
 *  @param string &$str string to use for decoding
 *  @param int $offset byte offset into string when var int stored
 *  @return int the decoded integer
 */
function vByteDecode(&$str, &$offset) 
{
    $pos_int = ord($str[$offset] & 127) ;
    $shift = 7;
    while (ord($str[$offset++]) & 128 > 0) {
        $pos_int += (ord($str[$offset] & 127) << $shift);
        $shift += 7;
    }

    return $pos_int;
}

/**
 * Computes the difference of a list of integers.
 * i.e., (a1, a2, a3, a4) becomes (a1, a2-a1, a3-a2, a4-a3) 
 *
 * @param array $list a nondecreasing list of integers
 * @return array the corresponding list of differences of adjacent
 *      integers
 */
function deltaList($list)
{
    $last = 0;
    $delta_list = array();
    foreach($list as $elt) {
        $delta_list[] = $elt - $last;
        $last = $elt;
    }
    return $delta_list;
}

/**
 * Given an array of differences of integers reconstructs the
 * original list. This computes the inverse of the deltaList function
 *
 * @see deltaList
 * @param array $delta_list a list of nonegative integers
 * @return array a nondecreasing list of integers
 */
function deDeltaList($delta_list)
{
    $last = 0;
    $list = array();
    foreach($delta_list as $delta) {
        $last += $delta;
        $list[] = $last;
    }
    return $list;
}

/**
 * Encodes a sequence of integers x, such that 1 <= x <= 2<<28-1
 * as a string.
 *
 * The encoded string is a sequence of 4 byte words (packed int's).
 * The high order 2 bits of a given word indicate whether or not
 * to look at the next word. The codes are as follows:
 * 11 start of encoded string, 10 continue four more bytes, 01 end of
 * encoded, and 00 indicates whole sequence encoded in one word.
 *
 * After the high order 2 bits, the next most significant bits indicate
 * the format of the current word. There are nine possibilities:
 * 00 - 1 28 bit number, 01 - 2 14 bit numbers, 10 - 3 9 bit numbers,
 * 1100 - 4 6 bit numbers, 1101 - 5 5 bit numbers, 1110 6 4 bit numbers,
 * 11110 - 7 3 bit numbers, 111110 - 12 2 bit numbers, 111111 - 24 1 bit 
 * numbers.
 *
 * @param array $list a list of positive integers satsfying above
 * @return string encoded string
 */
function encodeModified9($list)
{
    global $MOD9_PACK_POSSIBILITIES;
    $cnt = 0;
    $cur_size = 1;
    $cur_len = 1;
    $pack_list = array();
    $list_string = "";
    $continue_bits = 3;
    foreach($list as $elt) {
        $old_len = $cur_len;
        while( $elt > $cur_size )
        {
            $cur_len++;
            $cur_size = (1 << $cur_len) - 1;

        }

        if( $cnt < $MOD9_PACK_POSSIBILITIES[$cur_len] ) {
            $pack_list[] = $elt;
            $cnt++;
        } else {
            $list_string .= packListModified9($continue_bits, 
                $MOD9_PACK_POSSIBILITIES[$old_len], $pack_list);
            $continue_bits = 2;
            $pack_list = array($elt);
            $cur_size = 1;
            $cur_len = 1;
            $cnt = 1;
            while( $elt > $cur_size )
            {
                $cur_size = (1 << $cur_len) - 1;
                $cur_len++;
            }
        }
    }
    $continue_bits = ($continue_bits == 3) ? 0 : 1;
    $list_string .= packListModified9($continue_bits, 
        $MOD9_PACK_POSSIBILITIES[$cur_len], $pack_list);

    return $list_string;
}

/**
 * Packs the contents of a single word of a sequence being encoded
 * using Modified9.
 *
 * @param int $continue_bits the high order 2 bits of the word
 * @param int $cnt the number of element that will be packed in this word
 * @param array $list a list of positive integers to pack into word
 * @return string encoded 4 byte string
 * @see encodeModified9
 */
function packListModified9($continue_bits, $cnt, $pack_list)
{
    global $MOD9_NUM_ELTS_CODES, $MOD9_NUM_BITS_CODES;

    $out_int = 0;
    $code = $MOD9_NUM_ELTS_CODES[$cnt];
    $num_bits = $MOD9_NUM_BITS_CODES[$code];
    foreach($pack_list as $elt) {
        $out_int <<= $num_bits;
        $out_int += $elt;
    }
    $out_string = packInt($out_int);

    $out_string[0] = chr(($continue_bits << 6) + $code + ord($out_string[0]));
    return $out_string;
}

/**
 * Decoded a sequence of positive integers from a string that has been
 * encoded using Modified 9
 *
 * @param string $int_string string to decode from
 * @param int &$offset where to string in the string, after decode
 *      points to where one was after decoding.
 * @return array sequence of positive integers that were decoded
 * @see encodeModified9
 */
function decodeModified9($input_string, &$offset)
{
    $flag_mask = 192;
    $continue_threshold = 128;
    $first_time = true;
    $decode_list = array();
    if(($len = strlen($input_string) ) < 4) return array();
    do {
        $int_string = substr($input_string, $offset, 4);
        $ord_first = ord($int_string[0]);
        $flag_bits = ($ord_first & $flag_mask);
        if($first_time) {
            if($flag_bits != 0 && $flag_bits != $flag_mask) {
                return false;
            }
            $first_time = false;
        }
        $int_string[0] = chr($ord_first - $flag_bits);
        $decode_list = array_merge($decode_list, 
            unpackListModified9($int_string));
        $offset += 4;
        $len -= 4;
    } while($flag_bits >= $continue_threshold);

   return $decode_list;
}

/**
 * Decoded a single word with high two bits off according to modified 9
 *
 * @param string $int_string 4 byte string to decode
 * @return array sequence of integers that results from the decoding.
 */
function unpackListModified9($int_string) 
{
    global $MOD9_NUM_BITS_CODES, $MOD9_NUM_ELTS_DECODES;

    $first_char = ord($int_string[0]);
    foreach($MOD9_NUM_BITS_CODES as $code => $num_bits) {
        if(($first_char & $code) == $code) break;
    }
    $mask = (2 << ($num_bits - 1)) - 1;
    $num_elts = $MOD9_NUM_ELTS_DECODES[$code];
    $int_string[0] = chr($first_char - $code);

    $encoded_list = unpackInt($int_string);
    $decoded_list = array();
    for($i = 0; $i < $num_elts; $i++) {
        if(($pre_elt = $encoded_list & $mask) == 0) break;
        array_unshift($decoded_list, $pre_elt);
        $encoded_list >>= $num_bits;
    }

    return $decoded_list;
}


/**
 * Unpacks an int from a 4 char string
 *
 * @param string $str where to extract int from
 * @return int extracted integer
 */
function unpackInt($str)
{
    if(!is_string($str)) return false;
    $tmp = unpack("N", $str);
    return $tmp[1];
}

/**
 * Packs an int into a 4 char string
 *
 * @param int $my_int the integer to pack
 * @return string the packed string
 */
function packInt($my_int)
{
    return pack("N", $my_int);
}

/**
 * Unpacks a float from a 4 char string
 *
 * @param string $str where to extract int from
 * @return float extracted float
 */
function unpackFloat($str)
{
    if(!is_string($str)) return false;
    $tmp = unpack("f", $str);
    return $tmp[1];
}

/**
 * Packs an float into a 4 char string
 *
 * @param float $my_floatt the float to pack
 * @return string the packed string
 */
function packFloat($my_float)
{
    return pack("f", $my_float);
}

/**
 * Converts a string to string where each char has been replaced by its 
 * hexadecimal equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the hexified string
 */
function toHexString($str)
{
    $out = "";
    for($i = 0; $i < strlen($str); $i++) {
        $out .= dechex(ord($str[$i]))." ";
    }
    return $out;
}

/**
 * Converts a string to string where each char has been replaced by its 
 * binary equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the binary string
 */
function toBinString($str)
{
    $out = "";
    for($i = 0; $i < strlen($str); $i++) {
        $out .= substr(decbin(256+ord($str[$i])), 1)." ";
    }
    return $out;
}

/**
 *  Logs a message to a logfile or the screen
 *
 *  @param string $msg message to log
 *  @param string $lname name of log file in the LOG_DIR directory, rotated logs
 *      will also use this as their basename followed by a number followed by 
 *      bz2 (since they are bzipped).
 */

function crawlLog($msg, $lname = NULL)
{
    static $logname;

    if(defined("NO_LOGGING")) {
        return;
    }

    if($lname != NULL)
    {
        $logname = $lname;
    } else if(!isset($logname)) {
        $logname = "message";
    }

    $time_string = date("r", time());
    $out_msg = "[$time_string] $msg";
    if(defined("LOG_TO_FILES") && LOG_TO_FILES) {
        $logfile = LOG_DIR."/$logname.log";

        clearstatcache(); //hopefully, this doesn't slow things too much

        if(file_exists($logfile) && filesize($logfile) > MAX_LOG_FILE_SIZE) {
            if(file_exists("$logfile.".NUMBER_OF_LOG_FILES.".bz2")) {
                unlink("$logfile.".NUMBER_OF_LOG_FILES.".bz2");
            }
            for($i = NUMBER_OF_LOG_FILES; $i > 0; $i--) {
                if(file_exists("$logfile.".($i-1).".bz2")) {
                    rename("$logfile.".($i-1).".bz2", "$logfile.$i.bz2");
                }
            }
            file_put_contents("$logfile.0.bz2", 
                bzcompress(file_get_contents($logfile)));
            unlink($logfile);
        }
        error_log($out_msg."\n", 3, $logfile);
    } else {
        error_log($out_msg);
    }
}

/**
 *  Computes an 8 byte hash of a string for use in storing documents.
 *
 *  An eight byte hash was chosen so that the odds of collision even for
 *  a few billion documents via the birthday problem are still reasonable.
 *  If the raw flag is set to false then an 11 byte base64 encoding of the
 *  8 byte hash is returned. The hash is calculated as the xor of the
 *  two halves of the 16 byte md5 of the string. (8 bytes takes less storage
 *  which is useful for keeping more doc info in memory)
 *
 *  @param string $string the string to hash
 *  @param bool $raw whether to leave raw or base 64 encode
 *  @return string the hash of $string
 */
function crawlHash($string, $raw = false) 
{
    $pre_hash = md5($string, true);

    $left = substr($pre_hash,0, 8) ;
    $right = substr($pre_hash,8, 8) ;

    $combine = $right ^ $left;

    if(!$raw) {
        $hash = base64Hash($combine);
            // common variant of base64 safe for urls and paths
    } else {
        $hash = $combine;
    }

    return $hash; 
}

/**
 * Converts a crawl hash number to something closer to base64 coded but
 * so doesn't get confused in urls or DBs
 *
 *  @param string $string a hash to base64 encode
 *  @return string the encoded hash 
 */
function base64Hash($string)
{
    $hash = rtrim(base64_encode($string), "=");
    $hash = str_replace("/", "_", $hash);
    $hash = str_replace("+", "-" , $hash); 

    return $hash;
}


/**
 * Decodes a crawl hash number from base64 to raw ASCII
 *
 *  @param string $base64 a hash to decode
 *  @return string the decoded hash 
 */
function unbase64Hash($base64)
{
    //get rid of out modified base64 encoding
    $hash = str_replace("_", "/", $base64);
    $hash = str_replace("-", "+" , $hash);
    $hash .= "=";
    $raw = base64_decode($hash);

    return $raw;
}

/**
 * Encodes a string in a format suitable for post data
 * (mainly, base64, but str_replace data that might mess up post in result) 
 *
 * @param string $str string to encode
 * @return string encoded string
 */
function webencode($str)
{
    $str = base64_encode($str);
    $str = str_replace("/", "_", $str);
    $str = str_replace("+", ".", $str);
    $str = str_replace("=", "~", $str);
    return $str;
}

/**
 * Decodes a string encoded by webencode
 *
 * @param string $str string to encode
 * @return string encoded string
 */
function webdecode($str)
{
    $str = str_replace("_", "/", $str);
    $str = str_replace(".", "+", $str);
    $str = str_replace("~", "=", $str);
    return base64_decode($str);
}

/**
 * The search engine project's variation on the Unix crypt function using the 
 * crawlHash function instead of DES
 *
 * The crawlHash function is used to encrypt passwords stored in the database
 *
 * @param string $string the string to encrypt
 * @param int $salt salt value to be used (needed to verify if a password is 
 *      valid)
 * @return string the crypted string where crypting is done using crawlHash
 */
function crawlCrypt($string, $salt = NULL)
{
    if($salt == NULL) {
        $salt = rand(10000, 99999);
    } else {
        $len = strlen($salt);
        $salt = substr($salt, $len - 5, 5);
    }
    return crawlHash($string.$salt).$salt;
}

/**
 * Used by a controller to take a table and return those rows in the
 * table that a given queue_server would be responsible for handling
 *
 * @param array $table an array of rows of associative arrays which
 *      a queue_server might need to process
 * @param string $field column of $table whose values should be used
 *   for partitioning
 * @param int $num_partition number of queue_servers to choose between
 * @param int $instance the id of the particular server we are interested
 *  in
 * @param object $callbackfunction or static method that might be
 *      applied to input before deciding the responsible queue_server.
 *      For example, if input was a url we might want to get the host
 *      before deciding on the queue_server
 * @return array the reduced table that the $instance queue_server is 
 *      responsible for
 */
function partitionByHash($table, $field, $num_partition, $instance, 
    $callback = NULL)
{
    $out_table = array();
    foreach($table as $row) {
        $cell = ($field === NULL) ? $row : $row[$field];
        $hash_int = calculatePartition($cell, $num_partition, $callback);

        if($hash_int  == $instance) {
            $out_table[] = $row;
        }
    }
    return $out_table;
}

/**
 * Used by a controller to say which queue_server should receive
 * a given input
 * @param string $input can view as a key that might be processes by a
 *      queue_server. For example, in some cases input might be
 *      a url and we want to determine which queue_server should be
 *      responsible for queuing that url
 * @param int $num_partition number of queue_servers to choose between
 * @param object $callback function or static method that might be
 *      applied to input before deciding the responsible queue_server.
 *      For example, if input was a url we might want to get the host
 *      before deciding on the queue_server
 * @return int id of server responsible for input
 */
function calculatePartition($input, $num_partition, $callback = NULL)
{
    if($callback !== NULL) {
        $callback_parts = explode("::", $callback);
        if(count($callback_parts) == 1) {
            $input = $callback($input);
        } else {
            $class_name = $callback_parts[0];
            $method_name = $callback_parts[1];
            $tmp_class = new $class_name;
            $input = $tmp_class->$method_name($input);
        }
    }
    $hash_int =  abs(unpackInt(substr(crawlHash($input, true), 0, 4))) %
        $num_partition;

    return $hash_int;
}

/**
 * Measures the change in time in seconds between two timestamps to microsecond
 * precision
 *
 * @param string $start starting time with microseconds
 * @param string $end ending time with microseconds, if null use current time
 * @return float time difference in seconds
 */
function changeInMicrotime( $start, $end = NULL) 
{
    if( !$end ) {
        $end= microtime();
    }
    list($start_microseconds, $start_seconds) = explode(" ", $start);
    list($end_microseconds, $end_seconds) = explode(" ", $end);

    $change_in_seconds = intval($end_seconds) - intval($start_seconds);
    $change_in_microseconds = 
        floatval($end_microseconds) - floatval($start_microseconds);

    return floatval( $change_in_seconds ) + $change_in_microseconds;
} 


/**
 *  Converts a CSS unit string into its equivalent in pixels. This is
 *  used by @see SvgProcessor.
 *
 *  @param string $value  a number followed by a legal CSS unit
 *  @return int a number in pixels
 */
function convertPixels($value) 
{
    $len = strlen($value);
    if($len < 2) return intval($value);
    if($value[$len - 1] == "%") {
        $num = floatval(substr($value, 0, $len - 1));
        return ($num > 0) ? floor(8*min(100, $num)) : 0;
    }
    $num = floatval(substr($value, 0, $len - 2));
    $unit = substr($value, $len - 2);
    switch($unit)
    {

        case "cm":
        case "pt":
            return intval(28*$num);
        break;
        case "em":
        case "pc":
            return intval(6*$num);
        break;
        case "ex":
            return intval(12*$num);
        break;
        case "in":
            //assume screen 72 dpi as on mac
            return intval(72*$num);
        break;
        case "mm":
            return intval(2.8*$num);
        break;
        case "px":
            return intval($num);
        break;
        default:
            $num = $value;
    }
    return intval($num);
}


// callbacks for Model::traverseDirectory

/**
 * This is a callback function used in the process of recursively deleting a 
 * directory
 *
 * @param string $file_or_dir the filename or directory name to be deleted
 * @see DatasourceManager::unlinkRecursive()
 */
function deleteFileOrDir($file_or_dir)
{
    if(is_file($file_or_dir)) {
        unlink($file_or_dir);
    } else {
        rmdir($file_or_dir);
    }
}

/**
 * This is a callback function used in the process of recursively chmoding to 
 * 777 all files in a folder
 *
 * @param string $file the filename or directory name to be chmod
 * @see DatasourceManager::etWorldPermissionsRecursive()
 */
function setWorldPermissions($file)
{
    chmod($file, 0777);
}

/**
 * This is a callback function used in the process of recursively calculating
 * an array of file modification times and files sizes for a directorys
 *
 *  @param string a name of a file in the file system
 *  @return an array whose single element contain an associative array
 *      with the size and modification time of the file
 */
function fileInfo($file)
{
    $info["name"] = $file;
    $info["size"] = filesize($file);
    $info["is_dir"] = is_dir($file);
    $info["modified"] = filemtime($file);
    return array($info);
}

//ordering functions used in sorting

/**
 *  Callback function used to sort documents by a field
 *
 *  Should be initialized before using in usort with a call
 *  like: orderCallback($tmp, $tmp, "field_want");
 *
 *  @param string $word_doc_a doc id of first document to compare
 *  @param string $word_doc_b doc id of second document to compare
 *  @param string $field which field of these associative arrays to sort by
 *  @return int -1 if first doc bigger 1 otherwise
 */
function orderCallback($word_doc_a, $word_doc_b, $order_field = NULL)
{
    static $field = "a";
    if($order_field !== NULL) {
        $field = $order_field;
    }
    return ((float)$word_doc_a[$field] > 
        (float)$word_doc_b[$field]) ? -1 : 1;
}

/**
 *  Callback function used to sort documents by a field in reverse order
 *
 *  Should be initialized before using in usort with a call
 *  like: orderCallback($tmp, $tmp, "field_want");
 *
 *  @param string $word_doc_a doc id of first document to compare
 *  @param string $word_doc_b doc id of second document to compare
 *  @param string $field which field of these associative arrays to sort by
 *  @return int -1 if first doc bigger 1 otherwise
 */
function rorderCallback($word_doc_a, $word_doc_b, $order_field = NULL)
{
    static $field = "a";
    if($order_field !== NULL) {
        $field = $order_field;
    }
    return ((float)$word_doc_a[$field] > 
        (float)$word_doc_b[$field]) ? 1 : -1;
}

/**
 * Callback to check if $a is less than $b
 *
 * Used to help sort document results returned in PhraseModel called 
 * in IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is less than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getPhrasePageResults()
 */
function lessThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

/**
 *  Callback to check if $a is greater than $b
 *
 * Used to help sort document results returned in PhraseModel called in 
 * IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is greater than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getTopPhrases()
 */
function greaterThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a > $b) ? -1 : 1;
}

?>
