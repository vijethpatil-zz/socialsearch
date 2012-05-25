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
 * A Notifier is an object which will be notified by a priority queue
 * when the index in the queue viewed as array of some data item has been 
 * changed.
 *
 * A Notifier is notified when the index in the queue viewed as array of some 
 * data item has been changed, this gives the Notifier object the ability to 
 * update its value of the index for that data item. As an example, in the 
 * search engine, the WebQueueBundle class implements Notifier. Web queue 
 * bundles store url together with their weights and allow one to get out the 
 * url of highest weight. This is implemented by storing in a PriorityQueue 
 * keys consisting of hashes of urls (as fixed length) and values consisting of 
 * the weight. Then in a web archive the url and its index in the priority 
 * queue is stored. When the index in the queue changes, the WebQueueBundle's
 * notify method is called to adjust the index that is stored in the web 
 * archive.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @see WebQueueBundle
 */ 
 
interface Notifier
{
    /**
     * Handles the update of the index of a data item in a queue with respect 
     * to the Notifier object.
     *
     *  @param int $index  the index of a row in a heap-based priority queue
     *  @param mixed $data  the data that is stored at that index
     */
    function notify($index, $data);
} 
?>
