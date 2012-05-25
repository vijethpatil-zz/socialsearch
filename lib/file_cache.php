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

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/** Used to manage for file i/o functions */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/**
 * Library of functions used to implement a simple file cache
 * This might be used on systems that don't have memcache
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class FileCache
{
    /**
     * Folder name to use for this FileCache
     * @var string
     */
    var $dir_name;

    /**
     * How many seconds a bin is vulnerable to be deleted as expired
     */
    const SECONDS_IN_A_BIN = 3600;

    /**
     * Total number of bins to cycle between
     */
    const NUMBER_OF_BINS = 24;

    /**
     * Creates the directory for the file cache, sets how frequently
     * all items in the cache expire
     *
     * @param string $dir_name folder name of where to put the file cache
     */
    function __construct($dir_name)
    {
        $this->dir_name = $dir_name;

        if(!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
            $db_class = ucfirst(DBMS)."Manager";
            $db = new $db_class();
            $db->setWorldPermissionsRecursive($this->dir_name, true);
        }
    }

    /**
     * Retrieve data associated with a key that has been put in the cache
     *
     * @param string $key the key to look up
     * @return mixed the data associated with the key if it exists, false
     *      otherwise
     */
    function get($key)
    {
        $checksum_block = $this->checksum($key);
        $cache_file = $this->dir_name."/$checksum_block/".webencode($key);
        if(file_exists($cache_file)) {
            return unserialize(file_get_contents($cache_file));
        }
        return false;
    }

    /**
     * Stores in the cache a key-value pair
     *
     * @param string $key to associate with value
     * @param mixed $value to store
     */
    function set($key, $value)
    {
        $expire_block = floor(time() / self::SECONDS_IN_A_BIN) 
            % self::NUMBER_OF_BINS;
        $checksum_block = $this->checksum($key);
        $checksum_dir = $this->dir_name."/$checksum_block";
        if($expire_block == $checksum_block ) {
            $last_expired = 
                unserialize(
                    file_get_contents("$checksum_dir/last_expired.txt"));
            if(time() - $last_expired > self::SECONDS_IN_A_BIN) {
                $db_class = ucfirst(DBMS)."Manager";
                $db = new $db_class();
                $db->unlinkRecursive($checksum_dir);
            }
        }
        if(!file_exists($checksum_dir)) {
            mkdir($checksum_dir);
            $last_expired = time();
            file_put_contents("$checksum_dir/last_expired.txt", 
                serialize($last_expired));
        }
        $cache_file = "$checksum_dir/".webencode($key);
        file_put_contents($cache_file, serialize($value));
    }

    /**
     * Makes a 0 - self::NUMBER_OF_BINS value out of the provided key
     *
     * @param string $key to convert to a random value between 
     *      0 - self::NUMBER_OF_BINS
     * @return int value between 0 and self::NUMBER_OF_BINS
     */
    function checksum($key)
    {
        $len = strlen($key);
        $value = 0;
        for($i = 0; $i < $len; $i++) {
            $value += ord($key[$i]);
        }
        return ($value % self::NUMBER_OF_BINS);
    }
}
?>
