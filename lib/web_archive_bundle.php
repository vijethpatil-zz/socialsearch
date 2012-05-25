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
 * A WebArchiveBundle is a collection of WebArchive's, so load definition of
 * web archive
 */
require_once BASE_DIR.'/lib/web_archive.php';

/**
 * Used to compress data stored in WebArchiveBundle
 */
require_once BASE_DIR.'/lib/compressors/gzip_compressor.php';


 
/**
 * A web archive bundle is a collection of web archives which are managed 
 * together.It is useful to split data across several archive files rather than 
 * just store it in one, for both read efficiency and to keep filesizes from 
 * getting too big. In some places we are using 4 byte int's to store file 
 * offsets which restricts the size of the files we can use for wbe archives.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class WebArchiveBundle 
{

    /**
     * Folder name to use for this WebArchiveBundle
     * @var string
     */
    var $dir_name;
    /**
     * Used to contain the WebArchive paritions of the bundle
     * @var array
     */
    var $partition = array();
    /**
     * Total number of page objects stored by this WebArchiveBundle
     * @var int
     */
    var $count;
    /**
     * The index of the partition to which new documents will be added
     * @var int
     */
    var $write_partition;
    /**
     * A short text name for this WebArchiveBundle
     * @var string
     */
    var $description;
    /**
     * How Compressor object used to compress/uncompress data stored in
     * the bundle
     * @var object
     */
    var $compressor;
    /**
     * Controls whether the archive was opened in read only mode
     * @var bool
     */
    var $read_only_archive;
    /**
     * Makes or initializes an existing WebArchiveBundle with the given 
     * characteristics
     *
     * @param string $dir_name folder name of the bundle
     * @param int $num_docs_per_partition number of documents before the
     *      web archive is changed
     * @param string $description a short text name/description of this
     *      WebArchiveBundle
     * @param string $compressor the Compressor object used to 
     *      compress/uncompress data stored in the bundle
     */
    function __construct($dir_name, $read_only_archive = true,
        $num_docs_per_partition = NUM_DOCS_PER_GENERATION, $description = NULL, 
        $compressor = "GzipCompressor") 
    {
        $this->dir_name = $dir_name;
        $this->num_docs_per_partition = $num_docs_per_partition;
        $this->compressor = $compressor;
        $this->write_partition = 0;
        $this->read_only_archive = $read_only_archive;

        if(!is_dir($this->dir_name) && !$this->read_only_archive) {
            mkdir($this->dir_name);
        }

        //store/read archive description

        if(file_exists($dir_name."/description.txt")) {
            $info = unserialize(
                file_get_contents($this->dir_name."/description.txt"));
        }

        if(isset($info['NUM_DOCS_PER_PARTITION'])) {
            $this->num_docs_per_partition = $info['NUM_DOCS_PER_PARTITION'];
        }

        $this->count = 0;
        if(isset($info['COUNT'])) {
            $this->count = $info['COUNT'];
        }

        if(isset($info['WRITE_PARTITION'])) {
            $this->write_partition = $info['WRITE_PARTITION'];
        }
        if(isset($info['DESCRIPTION']) ) {
            $this->description = $info['DESCRIPTION'];
        } else {
            $this->description = $description;
            if($this->description == NULL) {
                $this->description = "Archive created without a description";
            }
        }

        $info['DESCRIPTION'] = $this->description;
        $info['NUM_DOCS_PER_PARTITION'] = $this->num_docs_per_partition;
        $info['COUNT'] = $this->count;
        $info['WRITE_PARTITION'] = $this->write_partition;
        if(!$read_only_archive) {
            file_put_contents(
                $this->dir_name."/description.txt", serialize($info));
        }

    }

    /**
     * Add the array of $pages to the WebArchiveBundle pages being stored in
     * the partition according to write partition and the field used to store
     * the resulting offsets given by $offset_field.
     *
     * @param string $offset_field field used to record offsets after storing
     * @param array &$pages data to store
     * @return int the write_partition the pages were stored in
     */
    function addPages($offset_field, &$pages)
    {

        $num_pages = count($pages);

        if($this->num_docs_per_partition > 0 && 
            $num_pages > $this->num_docs_per_partition) {
            crawlLog("ERROR! At most ".$this->num_docs_per_partition. 
                "many pages can be added in one go!");
            exit();
        }

        $partition = $this->getPartition($this->write_partition);
        $part_count = $partition->count;
        if($this->num_docs_per_partition > 0 && 
            $num_pages + $part_count > $this->num_docs_per_partition) {
            $this->setWritePartition($this->write_partition + 1);
            $partition = $this->getPartition($this->write_partition);
        }

        $this->addCount($num_pages); //only adds to count on disk
        $this->count += $num_pages;

        $partition->addObjects($offset_field, $pages, NULL, NULL, false);

        return $this->write_partition;
    }

    /**
     * Advances the index of the write partition by one and creates the 
     * corresponding web archive.
     */
    function setWritePartition($i)
    {
        $this->write_partition = $i;
        $this->getPartition($this->write_partition);
    }

    /**
     * Gets a page using in WebArchive $partition using the provided byte
     * $offset and using existing $file_handle if possible.
     *
     * @param int $offset byte offset of page data
     * @param int $partition which WebArchive to look in
     * @param resource $file_handle file handle resource of $partition archive
     * @return array desired page
     */
    function getPage($offset, $partition, $file_handle = NULL)
    {
        $page_array = 
            $this->getPartition($partition)->getObjects(
                $offset, 1, true, $file_handle);

        if(isset($page_array[0][1])) {
            return $page_array[0][1];
        } else {
            return array();
        }
    }

    /**
     * Gets an object encapsulating the $index the WebArchive partition in
     * this bundle.
     *
     * @param int $index the number of the partition within this bundle to
     *      return
     * @param bool $fast_construct should the constructor of the WebArchive
     *      avoid reading in its info block.
     * @return object the WebArchive file which was requested
     */
    function getPartition($index, $fast_construct = true)
    {
        if(!is_int($index)) {
            $index = 0;
        }
        if(!isset($this->partition[$index])) { 
            //this might not have been open yet
            $create_flag = false;
            $compressor = $this->compressor;
            $compressor = $this->compressor;
            $compressor_obj = new $compressor();
            $archive_name = $this->dir_name."/web_archive_".$index
                . $compressor_obj->fileExtension();
            if(!file_exists($archive_name)) {
                $create_flag = true;
            }
            $this->partition[$index] = 
                new WebArchive($archive_name, 
                    new $compressor(), $fast_construct);
            if($create_flag && file_exists($archive_name)) {
                chmod($archive_name, 0777);
            }
        }
        return $this->partition[$index];
    }

    /**
     * Creates a new counter to be maintained in the description.txt
     * file if the counter doesn't exist, leaves unchanged otherwise
     *
     * @param string $field field of info struct to add a counter for
     */
    function initCountIfNotExists($field = "COUNT")
    {
        $info = 
            unserialize(file_get_contents($this->dir_name."/description.txt"));
        if(!isset($info[$field])) {
            $info[$field] = 0;
        }
        if(!$this->read_only_archive) {
            file_put_contents($this->dir_name.
                "/description.txt", serialize($info));
        }
    }

    /**
     * Updates the description file with the current count for the number of
     * items in the WebArchiveBundle. If the $field item is used counts of
     * additional properties (visited urls say versus total urls) can be 
     * maintained.
     *
     * @param int $num number of items to add to current count
     * @param string $field field of info struct to add to the count of
     */
    function addCount($num, $field = "COUNT")
    {
        $info = 
            unserialize(file_get_contents($this->dir_name."/description.txt"));
        $info[$field] += $num;
        if(!$this->read_only_archive) {
            file_put_contents($this->dir_name."/description.txt",
                serialize($info));
        }
    }

    /**
     * Gets information about a WebArchiveBundle out of its description.txt 
     * file
     *
     * @param string $dir_name folder name of the WebArchiveBundle to get info
     *  for
     * @return array containing the name (description) of the WebArchiveBundle,
     *      the number of items stored in it, and the number of WebArchive
     *      file partitions it uses.
     */
    static function getArchiveInfo($dir_name)
    {
        if(!is_dir($dir_name) || !file_exists($dir_name."/description.txt")) {
            $info = array();
            $info['DESCRIPTION'] = 
                "Archive does not exist OR Archive description file not found";
            $info['COUNT'] = 0;
            $info['NUM_DOCS_PER_PARTITION'] = -1;
            return $info;
        }

        $info = unserialize(file_get_contents($dir_name."/description.txt"));

        return $info;

    }

    /**
     * Sets the archive info (DESCRIPTION, COUNT, 
     * NUM_DOCS_PER_PARTITION) for this web archive 
     *
     * @param string $dir_name folder with archive bundle 
     * @param array $info struct with above fields 
     */
    static function setArchiveInfo($dir_name, $info)
    {
        if(file_exists($dir_name."/description.txt") && ((isset($this) &&
            !$this->read_only_archive) || !isset($this))) {
            file_put_contents($dir_name."/description.txt", serialize($info));
        }
    }

    /**
     * Returns the mast time the archive info of the bundle was modified.
     *
     * @param string $dir_name folder with archive bundle
     */
    static function getParamModifiedTime($dir_name)
    {
        if(file_exists($dir_name."/description.txt")) {
            return filemtime($dir_name."/description.txt");
        }
        return false;
    }
}
?>
