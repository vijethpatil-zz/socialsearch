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
 * Loads the base class
 */
require_once "string_array.php";
/**
 * Needed for crawlHash
 */
require_once "utility.php";

/**
 * 
 * Code used to manage a memory efficient hash table
 * Weights for the queue must be flaots
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
class HashTable extends StringArray
{

    /**
     * The size in bytes for keys stored in the hash table
     *
     * @var int
     */
    var $key_size;
    /**
     * The size in bytes of values associated with keys
     *
     * @var int
     */
    var $value_size;
    /**
     * Holds an all \0 string used of length $this->key_size
     * @var string
     */
    var $null;
    /**
     * Holds \0\0 followed by an all \FF string of length $this->key_size -1
     * Used to indicate that a slot once held data but that data was deleted.
     * Such a slot tells a lookup to keep going, but on an insert can be 
     * overwritten in the inserted key is not already in the table
     * @var string
     */
    var $deleted;
    /**
     * Number of items currently in the hash table
     * @var int
     */
    var $count;

    /**
     * Flag for hash table lookup methods
     */
    const ALWAYS_RETURN_PROBE = 1;
    /**
     * Flag for hash table lookup methods
     */
    const RETURN_PROBE_ON_KEY_FOUND = 0;
    /**
     * Flag for hash table lookup methods
     */
    const RETURN_VALUE = -1;

    /**
     * Flag for hash table lookup methods
     */
    const RETURN_BOTH = -2;

    /**
     * Makes a persistently stored (i.e., on disk and ram)  hash table using the
     * supplied parameters
     *
     * @param string $fname filename to use when storing the hash table to disk
     * @param int $num_values number of key value pairs the table can hold
     * @param int $key_size number of bytes to store a hash table key
     * @param int $value_size number of bytes to store a hash table value
     * @param int $save_fequency how many non read operation before saving to
     *      disk
     */
    function __construct($fname, $num_values, $key_size, $value_size, 
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $this->key_size = $key_size;
        $this->value_size = $value_size;
        $this->null = pack("x". $this->key_size);
        $this->deleted = pack("H2x".($this->key_size - 1), "FF");

        $this->count = 0;

        parent::__construct($fname, $num_values, 
            $key_size + $value_size, $save_frequency);
    }

    /**
     * Inserts the provided $key - $value pair into the hash table
     *
     * @param string $key the key to use for the insert (will be needed for
     *      lookup)
     * @param string $value the value associated with $key
     * @param int $probe if the location in the hash table is already known
     *      to be $probe then this variable can be used to save a lookup
     * @return bool whether the insert was successful or not
     */
    function insert($key, $value, $probe = false)
    {
        $null = $this->null;
        $deleted = $this->deleted;

        if($probe === false) {
            $probe = $this->lookup($key, self::ALWAYS_RETURN_PROBE);
        }

        if($probe === false) {
            /* this is a little slow
               the idea is we can't use deleted slots until we are sure 
               $key isn't in the table
             */
            $probe = $this->lookupArray(
                $key, array($null, $deleted), self::ALWAYS_RETURN_PROBE);

            if($probe === false) {
                crawlLog("No space in hash table");
                return false;
            }
        }

        //there was a free slot so write entry...
        $data = pack("x". ($this->key_size + $this->value_size));

        //first the key

        for ($i = 0; $i < $this->key_size; $i++) {
            $data[$i] = $key[$i];
        }

        //then the value

        for ($i = 0; $i < $this->value_size; $i++) {
            $data[$i + $this->key_size] = $value[$i];
        }

        $this->put($probe, $data);
        $this->count++;
        $this->checkSave();

        return true;
    }


    /**
     * Tries to lookup the key in the hash table either return the
     * location where it was found or the value associated with the key.
     *
     * @param string $key key to look up in the hash table
     * @param int $return_probe_value one of self::ALWAYS_RETURN_PROBE, 
     *      self::RETURN_PROBE_ON_KEY_FOUND, self::RETURN_VALUE, or self::BOTH. 
     *      Here value means the value associated with the key and probe is
     *      either the location in the array where the key was found or
     *      the first location in the array where it was determined the
     *      key could not be found.
     * @return mixed would be string if the value is being returned, 
     *      an int if the probe is being returned, and false if the key
     *      is not found
     */
    function lookup($key, $return_probe_value = self::RETURN_VALUE)
    {
        return $this->lookupArray(
            $key, array($this->null), $return_probe_value);
    }

    /**
     * Tries to lookup the key in the hash table either return the
     * location where it was found or the value associated with the key.
     * If the key is not at the initial probe value, linear search in the
     * table is done. The values which cut-off the search are stored in 
     * $null_array. Using an array allows for flexibility since a deleted
     * entry needs to be handled different when doing a lookup then when
     * doing an insert.
     *
     * @param string $key key to look up in the hash table
     * @param array $null_array key values that would cut-off the search
     *      for key if the initial probe failed
     * @param int $return_probe_value one of self::ALWAYS_RETURN_PROBE, 
     *      self::RETURN_PROBE_ON_KEY_FOUND, or self::RETURN_VALUE. Here
     *      value means the value associated with the key and probe is
     *      either the location in the array where the key was found or
     *      the first location in the array where it was determined the
     *      key could not be found.
     * @return mixed would be string if the value is being returned, 
     *      an int if the probe is being returned, and false if the key
     *      is not found
     */
    function lookupArray($key, $null_array, 
        $return_probe_value = self::RETURN_VALUE)
    {
        $index = $this->hash($key);

        $num_values = $this->num_values;
        $probe_array = array(self::RETURN_PROBE_ON_KEY_FOUND, 
            self::ALWAYS_RETURN_PROBE);

        for($j = 0; $j < $num_values; $j++)  {
            $probe = ($index + $j) % $num_values;

            list($index_key, $index_value) = $this->getEntry($probe);

            if(in_array($index_key, $null_array)) {
                if($return_probe_value == self::ALWAYS_RETURN_PROBE) {
                    return $probe;
                } else {
                    return false;
                }
            }

            if(strcmp($key, $index_key) == 0) { break; }
        }

        if($j == $num_values) {return false;}

        $result = $index_value;
        if(in_array($return_probe_value, $probe_array)) {
            $result = $probe;
        }
        if($return_probe_value == self::RETURN_BOTH) {
            $result = array($probe, $index_value);
        }

        return $result; 

    }

    /**
     * Deletes the data associated with the provided key from the hash table
     *
     * @param string $key the key to delete the entry for
     * @param int $probe if the location in the hash table is already known
     *      to be $probe then this variable can be used to save a lookup
     * @return bool whether or not something was deleted
     */
    function delete($key, $probe = false)
    {
        $deleted = pack("H2x".($this->key_size + $this->value_size - 1), "FF");
            //deletes

        if($probe === false) {
            $probe = $this->lookup($key, self::RETURN_PROBE_ON_KEY_FOUND);
        }

        if($probe === false) { return false; }

        $this->put($probe, $deleted);

        $this->count--;
        $this->checkSave();

        return true;

    }

    /**
     * Get the ith entry of the array for the hash table (no hashing here)
     *
     * @param int $i an index of the hash table array
     * @return array the key value pair stored at this index
     */
    function getEntry($i)
    {
        $raw = $this->get($i);
        $key = substr($raw, 0, $this->key_size);
        $value = substr($raw, $this->key_size, $this->value_size);

        return array($key, $value);
    }

    /**
     * Hashes the provided key to an index in the array of the hash table
     *
     * @param string $key a key to hashed into the hash table
     * @return int an index in the array of the hash table
     */
    function hash($key)
    {
        $hash = substr(md5($key, true), 0, 4);
        $seed = unpackInt($hash);

        mt_srand($seed);
        $index = mt_rand(0, $this->num_values -1);

        return $index;
    }


}
?>
