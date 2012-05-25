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
 * 
 * A BloomFilterBundle is a directory of BloomFilterFile.
 * The filter bundle, like a Bloom filter, also acts as a set,
 * but once the active filter in it fills up a new filter is 
 * added to the bundle so that more data can be stored.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @see BloomFilterFile
 */ 
class BloomFilterBundle
{

    /**
     * Reference to the filter which will be used to store new data
     * @var object
     */
    var $current_filter;
    /**
     * Total number of filter that this filter bundle currently has
     * @var int
     */
    var $num_filters;
    /**
     * The number of items which have been stored in the current filter
     * @var int
     */
    var $current_filter_count;
    /**
     * The maximum capacity of a filter in this filter bundle
     * @var int
     */
    var $filter_size;
    /**
     * The folder name of this filter bundle
     * @var string
     */
    var $dir_name;
    /**
     * The default maximum size of a filter in a filter bundle
     */
    const default_filter_size = 10000000;

    /**
     * Creates or loads if already exists the directory structure and 
     * BloomFilterFiles used by this bundle
     *
     * @param $dir_name directory when this bundles data is stored
     * @param $filter_size the size of an individual filter in this bundle
     *      once a filter is filled a new one is added to the directory
     */
    function __construct($dir_name, 
        $filter_size = self::default_filter_size ) 
    {
        $this->dir_name = $dir_name;
        if(!is_dir($dir_name)) {
            mkdir($dir_name);
        }
        
        $this->loadMetaData();
        
        if($this->num_filters == 0) {
            $this->current_filter = 
                new BloomFilterFile($dir_name."/filter_0.ftr", $filter_size);
            $this->num_filters++;
            $this->filter_size = $filter_size;
            $this->current_filter->save();
            $this->saveMetaData();
        } else {
            $last_filter = $this->num_filters - 1;
            $this->current_filter = 
                BloomFilterFile::load($dir_name."/filter_$last_filter.ftr");
        }


    }

    /**
     * Inserts a $value into the BloomFilterBundle
     *
     * This involves inserting into the current filter, if the filter
     * is full, a new filter is added before the value is added
     *
     * @param string $value a item to add to the filter bundle
     */
    function add($value)
    {
        if($this->current_filter_count >= $this->filter_size) {
            $this->current_filter->save();
            $this->current_filter = NULL;
            gc_collect_cycles();
            $last_filter = $this->num_filters;
            $this->current_filter = 
                new BloomFilterFile($this->dir_name."/filter_$last_filter.ftr", 
                    $this->filter_size);
            $this->current_filter_count = 0;
            $this->num_filters++;
            $this->saveMetaData();
        }

        $this->current_filter->add($value);

        $this->current_filter_count++;

    }

    /**
     * Removes from the passed array those elements $elt who either are in
     * the filter bundle or whose $elt[$field_name] is in the bundle.
     *
     * @param array &$arr the array to remove elements from
     * @param array $field_names if not NULL an array of field names of $arr 
     *      to use to do filtering
     */
    function differenceFilter(&$arr, $field_names = NULL)
    {

        $num_filters = $this->num_filters;
        $count = count($arr);
        for($i = 0; $i < $num_filters; $i++) {
            if($i == $num_filters - 1) {
                $tmp_filter = $this->current_filter;
            } else {
                $tmp_filter = 
                    BloomFilterFile::load($this->dir_name."/filter_$i.ftr");
            }

            for($j = 0; $j < $count; $j++) {
                if($field_names === NULL) {
                    $tmp = & $arr[$j];
                    if($tmp !== false && $tmp_filter->contains($tmp)) {
                    /* 
                        We deliberately don't try to add anything that has
                        the hash field set to false. This is our cue to 
                        skip an element such as a link document which we
                        know will almost always be unique and so be unnecessary
                        to de-duplicate
                     */
                        unset($arr[$j]);
                    }
                } else { //now do the same strategy for the array of fields case
                    foreach($field_names as $field_name) {
                        $tmp = & $arr[$j][$field_name];
                        if($tmp !== false && $tmp_filter->contains($tmp)) {
                            unset($arr[$j]);
                            break;
                        }
                    }
                }
            }
        }

    }

    /**
     * Loads from the filter bundles' meta.txt the meta data associated with
     * this filter bundle and stores this data into field variables
     */
    function loadMetaData()
    {
        if(file_exists($this->dir_name.'/meta.txt')) {
            $meta = unserialize(
                file_get_contents($this->dir_name.'/meta.txt') );
            $this->num_filters = $meta['NUM_FILTERS'];
            $this->current_filter_count = $meta['CURRENT_FILTER_COUNT'];
            $this->filter_size = $meta['FILTER_SIZE'];
        } else {
            $this->num_filters = 0;
            $this->current_filter_count = 0;
            $this->filter_size = self::default_filter_size;
        }
    }

    /**
     * Saves the meta data (number of filter, number of items stored, and size)
     * of the bundle
     */
    function saveMetaData()
    {
        $meta = array();
        $meta['NUM_FILTERS'] = $this->num_filters;
        $meta['CURRENT_FILTER_COUNT' ]= $this->current_filter_count;
        $meta['FILTER_SIZE'] = $this->filter_size;

        file_put_contents($this->dir_name.'/meta.txt', serialize($meta));
    }

    /**
     *  Empties the contents of the bloom filter bundle and resets
     *  it to start storing new data.
     */
    function reset()
    {
        for($i = 0; $i < $this->num_filters; $i++) {
            @unlink($this->dir_name."/filter_$i.ftr");
        }
        $this->num_filters = 0;
        $this->current_filter_count = 0;
        $this->current_filter = 
            new BloomFilterFile($this->dir_name."/filter_0.ftr", 
            $this->filter_size);
        $this->num_filters++;
        $this->current_filter->save();
        $this->saveMetaData();
    }

    /**
     * Used to save to disk all the file data associated with this bundle
     */
    function forceSave()
    {
        $this->saveMetaData();
        $this->current_filter->save();
    }

}
?>
