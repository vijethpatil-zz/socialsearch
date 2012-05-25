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
 * Read in base class, if necessary
 */
require_once "persistent_structure.php";

/**
 * Load charCopy
 */
require_once "utility.php";

/**
 * Memory efficient implementation of persistent arrays
 *
 * The standard array objects in php and even spl have a large amount of
 * overhead. The point of this class is to have the size as close to the
 * optimal as possible
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */  
class StringArray extends PersistentStructure 
{

    /**
     * Number of items to be stored in the StringArray
     * @var int
     */
    var $num_values;
    /**
     * Size of each item in bytes to be stored
     * @var int
     */
    var $data_size;
    /**
     * Number of bytes of storage need by the string array
     * @var int
     */
    var $string_array_size;
    /**
     * Character string used to store the packed data of the StringArray
     * @var string
     */
    var $string_array;


    /**
     * Initiliazes the fields of the StringArray and its parent class
     * PersistentStructure. Creates a null filled string array of size 
     * $this->string_array_size to stored data in.
     *
     * @param string $fname the name of the file to store data persistently in
     * @param int $num_values the number of items the StringArray will store
     * @param int $data_size the size in bytes of a single item
     * @param int $save_frequency how often the StringArray should be stored to
     *      disk
     */
    public function __construct($fname, $num_values, $data_size, 
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY) 
    {
        $this->num_values = $num_values;
        $this->data_size = $data_size;

        $this->string_array_size = $num_values*($data_size);

        $this->string_array = pack("x". $this->string_array_size);

        parent::__construct($fname, $save_frequency);

    }

    /**
     *  Load a StringArray from a file
     *
     *  @param string the name of the file to load the StringArray from
     *  @return object the PersistentStructure loaded
     */
    public static function load($fname)
    {
        $fh = fopen($fname, "rb");
        $array_size = unpackInt(fread($fh, 4));
        $array = fread($fh, $array_size);
        $object = unserialize(fread($fh, 
            filesize($fname) -4 - $array_size));
        $object->string_array = & $array;
        fclose($fh);
        return $object;
    }

    /**
     *  Save the StringArray to its filename 
     */
    public function save()
    {
        $fh = fopen($this->filename, "wb");
        $tmp = & $this->string_array;
        fwrite($fh, packInt($this->string_array_size));
        fwrite($fh, $this->string_array);
        unset($this->string_array);
        fwrite($fh, serialize($this));
        $this->string_array = & $tmp;
        fclose($fh);
    }

    /**
     *  Looks up the ith item in the StringArray
     *
     *  @param int $i array index of item to look up
     *  @return string the looked-up item of length $this->data_size
     */
    public function get($i)
    {
        $data_size = $this->data_size;

        return substr($this->string_array, $i*$data_size, $data_size);
    }

    /**
     * Puts data into the ith item of the StringArray
     *
     * @param int $i array index of where to store data
     * @param string $data at least $this->data_size many bytes of data to 
     *      store
     */
    public function put($i, $data)
    {
        $data_size = $this->data_size;

        $start = $i * $data_size;

        charCopy($data, $this->string_array, $start, $data_size);

    }

}
?>
