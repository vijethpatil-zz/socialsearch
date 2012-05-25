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
 * Load base class with methods for loading and saving this structure
 */
require_once "persistent_structure.php";

/**
 * Fot packInt/unpackInt
 */
require_once "utility.php";

/**
 * Code used to manage a bloom filter in-memory and in file.
 * A Bloom filter is used to store a set of objects.
 * It can support inserts into the set and it can also be
 * used to check membership in the set.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */  
class BloomFilterFile extends PersistentStructure
{

    /**
     * Number of bit positions in the Bloom filter used to say an item is
     * in the filter
     * @var int
     */
    var $num_keys;
    /**
     * Size in bits of the packed string array used to store the filter's
     * contents
     * @var int
     */
    var $filter_size;
    /**
     * Packed string used to store the Bloom filters
     * @var string
     */
    var $filter;

    /**
     * Initializes the fields of the BloomFilter and its base 
     * PersistentStructure.
     *
     * @param string $fname name of the file to store the BloomFilter data in
     * @param int $num_values the maximum number of values that will be stored
     *      in the BloomFilter. Filter will be sized so the odds of a false 
     *      positive are roughly one over this value
     * @param int $save_frequency how often to store the BloomFilter to disk
     */
    function __construct($fname, $num_values, 
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $log2 = log(2);
        $this->num_keys = ceil(log($num_values)/$log2);
        $this->filter_size = ($this->num_keys)*$num_values/$log2;

        $mem_before =  memory_get_usage(true);
        $this->filter = pack("x". ceil(.125*$this->filter_size)); 
            // 1/8 =.125 = num bits/bytes, want to make things floats
        $mem = memory_get_usage(true) - $mem_before;
        parent::__construct($fname, $save_frequency);

    }

    /**
     * Inserts the provided item into the Bloomfilter
     *
     * @param string $value item to add to filter
     */
    function add($value)
    {
        $num_keys = $this->num_keys;
        $pos_array = $this->getHashBitPositionArray($value, $num_keys);
        for($i = 0;  $i < $num_keys; $i++) {
            $this->setBit($pos_array[$i]);
        }

        $this->checkSave();
    }

    /**
     * Checks if the BloomFilter contains the provided $value
     *
     * @param string $value item to check if is in the BloomFilter
     * @return bool whether $value was in the filter or not
     */
    function contains($value)
    {
        $num_keys = $this->num_keys;
        $pos_array = $this->getHashBitPositionArray($value, $num_keys);
        for($i = 0;  $i < $num_keys; $i++) {
            if(!$this->getBit($pos_array[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hashes $value to a bit position in the BloomFilter
     *
     * @param string $value value to map to a bit position in the filter
     * @return int the bit position mapped to
     */
    function getHashBitPositionArray($value, $num_keys)
    {
        $md5 = md5($value, true);
        $seed = array();
        for($i = 0; $i < 16; $i += 4) {
            $hash = substr($md5, $i, 4);
            $seed[] = unpackInt($hash);
        }

        $pos_array = array();
        $offset = $num_keys >> 2;
        $size = $this->filter_size - 1;
        $index = 0;
        for($j = 0; $j < $num_keys; $j += $offset) {
            $high = $j + $offset;
            if($index < 4) {
                mt_srand($seed[$index++]);
            }
            for($i = $j; $i < $high; $i++) {
                $pos_array[$i] = mt_rand(0, $size);
            }
        }
        return $pos_array;
    }

    /**
     * Sets to true the ith bit position in the filter.
     *
     * @param int $i the position to set to true
     */
    function setBit($i)
    {
        $byte = ($i >> 3);

        $bit_in_byte = $i - ($byte << 3);

        $tmp = $this->filter[$byte];

        $this->filter[$byte] = $tmp | chr(1 << $bit_in_byte);

    }

    /**
     * Looks up the value of the ith bit position in the filter
     *
     * @param int $i the position to look up
     * @return bool the value of the looked up position
     */
    function getBit($i)
    {
        $byte = $i >> 3;
        $bit_in_byte = $i - ($byte << 3);

        return ($this->filter[$byte] & chr(1 << $bit_in_byte)) != chr(0);
    }
}
?>
