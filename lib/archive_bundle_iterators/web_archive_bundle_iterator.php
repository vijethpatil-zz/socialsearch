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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/archive_bundle_iterator.php';

/** 
 * Class used to model iterating documents indexed in 
 * an WebArchiveBundle. This would typically be for the purpose
 * of re-indexing these documents.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class WebArchiveBundleIterator extends ArchiveBundleIterator 
    implements CrawlConstants
{

    /**
     * Number of web archive objects in this web archive bundle
     * @var int
     */
    var $num_partitions;
    /**
     * The current web archive in the bundle that is being iterated over
     * @var int
     */
    var $partition;
    /**
     * The item within the current partition to be returned next
     * @var int
     */
    var $partition_index;
    /**
     * Index of web archive in the web archive bundle that the iterator is
     * currently getting results from
     * @var int
     */
    var $current_partition_num;
    /**
     * Index between 0 and $this->count of where the iterator is at
     * @var int
     */
    var $overall_index;
    /**
     * Number of documents in the web archive bundle being iterated over
     * @var int
     */
    var $count;
    /**
     * The web archive bundle being iterated over
     * @var object
     */
    var $archive;
    /**
     * The fetcher prefix associated with this archive.
     * @var string
     */
    var $fetcher_prefix;

    /**
     * Returns the path to an archive given its timestamp.
     *
     * @param string $timestamp the archive timestamp
     * @return string the path to the archive, based off of the fetcher prefix 
     *     used when this iterator was constructed
     */
    function get_archive_name($timestamp)
    {
        return CRAWL_DIR.'/cache/'.$this->fetcher_prefix.
            self::archive_base_name.$timestamp;
    }

    /**
     * Creates a web archive iterator with the given parameters.
     *
     * @param string $prefix fetcher number this bundle is associated with
     * @param string $iterate_timestamp timestamp of the web archive bundle to 
     *      iterate over the pages of
     * @param string $result_timestamp timestamp of the web archive bundle
     *      results are being stored in
     */
    function __construct($prefix, $iterate_timestamp, $result_timestamp)
    {
        $this->fetcher_prefix = $prefix;
        $this->iterate_timestamp = $iterate_timestamp;
        $this->result_timestamp = $result_timestamp;
        $archive_name = $this->get_archive_name($iterate_timestamp);
        $this->archive = new WebArchiveBundle($archive_name);
        $archive_name = $this->get_archive_name($result_timestamp);
        if(file_exists("$archive_name/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }

    /**
     * Saves the current state so that a new instantiation can pick up just 
     * after the last batch of pages extracted.
     */
    function saveCheckpoint($info = array())
    {
        $info['overall_index'] = $this->overall_index;
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['partition_index'] = $this->partition_index;
        $info['current_partition_num'] = $this->current_partition_num;
        $info['iterator_pos'] = $this->partition->iterator_pos;
        $archive_name = $this->get_archive_name($this->result_timestamp);
        file_put_contents("$archive_name/iterate_status.txt",
            serialize($info));
    }

    /**
     * Restores state from a previous instantiation, after the last batch of 
     * pages extracted.
     */
    function restoreCheckpoint()
    {
        $info = unserialize(file_get_contents(
            "$archive_name/iterate_status.txt"));
        $this->count = $this->archive->count;
        $this->num_partitions = $this->archive->write_partition+1;
        $this->overall_index = $info['overall_index'];
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->partition_index = $info['partition_index'];
        $this->current_partition_num = $info['current_partition_num'];
        $this->partition =  $this->archive->getPartition(
                $this->current_partition_num, false);
        $this->partition->iterator_pos = $info['iterator_pos'];
        return $info;
    }

    /**
     * Estimates the importance of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return bool false we assume files were crawled roughly according to 
     *      page importance so we use default estimate of doc rank
     */
    function weight(&$site) 
    {
        return false;
    }

    /**
     * Gets the next $num many docs from the iterator
     *
     * @param int $num number of docs to get
     * @return array associative arrays for $num pages
     */
    function nextPages($num)
    {
        if($num + $this->overall_index >= $this->count) {
            $num = max($this->count - $this->overall_index, 0);
        }
        $num_to_get = 1;
        $objects = array();
        for($i = 0; $i < $num; $i += $num_to_get) {
            $num_to_get = min($num, $this->partition->count - 
                $this->partition_index);
            $pre_new_objects = $this->partition->nextObjects($num_to_get);
            foreach($pre_new_objects as $object) {
                $objects[] = $object[1];
            }

            $this->overall_index += $num_to_get;
            $this->partition_index += $num_to_get;
            if($num_to_get <= 0) {
                $this->current_partition_num++;
                $this->partition = $this->archive->getPartition(
                    $this->current_partition_num, false);
                $this->partition_index = 0;
            }
            if($this->current_partition_num > $this->num_partitions) break;
        }
        $this->end_of_iterator = ($this->overall_index >= $this->count ) ?
            true : false;

        $this->saveCheckpoint();
        return $objects;
    }

    /**
     * Resets the iterator to the start of the archive bundle
     */
    function reset()
    {
        $this->count = $this->archive->count;
        $this->num_partitions = $this->archive->write_partition + 1;
        $this->overall_index = 0;
        $this->end_of_iterator = ($this->overall_index >= $this->count) ?
            true : false;
        $this->partition_index = 0;
        $this->current_partition_num = 0;
        $this->partition = $this->archive->getPartition(
            $this->current_partition_num, false);
        $this->partition->reset();
        $archive_name = $this->get_archive_name($this->result_timestamp);
        @unlink("$archive_name/iterate_status.txt");
    }
}
?>
