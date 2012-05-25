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
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';

/** 
 * Needed to be able to get pages from remote queue_servers
 */
require_once BASE_DIR.'/lib/fetch_url.php';


/**
 * This iterator is used to handle querying a network of queue_servers
 * with regard to a query 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 */
class NetworkIterator extends IndexBundleIterator
{

    /**
     * Part of query without limit and num to be processed by all queue_server
     * machines
     *
     * @var string
     */
    var $base_query;

    /**
     * When true, tells any parent iterator not to try to call getIndex,
     * currentGenDocOffsetWithWord, or computeRelevance
     *
     * @var bool
     */
    var $no_lookup;

    /**
     * Current limit number to be added to base query
     *
     * @var string
     */
    var $limit;

    /**
     * An array of servers to ask a query to
     *
     * @var string
     */
    var $queue_servers;

    /**
     * Flags for each server saying if there are more results for that server
     * or not
     *
     * @var array
     */
    var $more_results;

    /**
     * Keeps track of whether the word_iterator list is empty becuase the
     * word does not appear in the index shard
     * @var int
     */
    var $filter;

    /**
     * the minimum number of pages to group from a block;
     * this trumps $this->index_bundle_iterator->results_per_block
     */
    const MIN_FIND_RESULTS_PER_BLOCK = 200;

    /** Host Key position + 1 (first char says doc, inlink or eternal link)*/
    const HOST_KEY_POS = 17;

    /** Length of a doc key*/
    const KEY_LEN = 8;

    /**
     * Creates a network iterator with the given parameters.
     *
     * @param string $query the query that was supplied by the end user
     *      that we are trying to get search results for
     * @param array $queue_servers urls of yioop instances on which documents
     *  indexes live
     * @param string $timestamp the timestamp of the particular current index
     *      archive bundles that we look in for results
     * @param array $filter an array of hashes of domains to filter from
     *      results
     */
    function __construct($query, $queue_servers, $timestamp, &$filter = NULL)
    {
        $this->no_lookup = true;
        $this->results_per_block = ceil(self::MIN_FIND_RESULTS_PER_BLOCK);
        $this->base_query = "q=".urlencode($query).
            "&f=serial&network=&raw=1&its=$timestamp";
        $this->queue_servers = $queue_servers;
        $this->limit = 0;
        $count = count($this->queue_servers);
        for($i = 0; $i < $count; $i++) {
            $this->more_flags[$i] = true;
        }

        if($filter != NULL) {
            $this->filter = & $filter;
        } else {
            $this->filter = NULL;
        }
    }

    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation As this is not easily determined
     * for a network iterator, this method always returns 1 for this
     * iterator
     *
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *      relevance of
     * @return float a relevancy score based on BM25F.
     */
    function computeRelevance($generation, $posting_offset)
     {
        return 1;
     }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
     {
        $this->limit = 0;
        $count = count($this->queue_servers);
        for($i = 0; $i < $count; $i++) {
            $this->more_flags[$i] = true;
        }
     }

    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *      the must be of greater than or equal generation, and if equal the
     *      next block must all have $doc_offsets larger than or equal to 
     *      this value
     */
    function advance($gen_doc_offset = null)
     {
        $this->advanceSeenDocs();
        $this->limit += $this->results_per_block;
     }

    /**
     * Returns the index associated with this iterator. As this is not easily 
     * determined for a network iterator, this method always returns NULL for 
     * this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
     {
        return NULL;
     }

    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator. As this is not easily determined
     * for a network iterator, this method always returns -1 for this
     * iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord()
    {
        return -1;
    }

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
     function findDocsWithWord()
     {
        $query = $this->base_query .
            "&num={$this->results_per_block}&limit={$this->limit}";

        $sites = array();
        $lookup = array();
        $i = 0;
        $j = 0;
        foreach($this->queue_servers as $server) {
            if($this->more_flags[$i]) {
                $sites[$j][CrawlConstants::URL] = $server ."?". $query;
                $lookup[$j] = $i;
                $j++;
            }
            $i++;
        }

        $downloads = array();
        if(count($sites) > 0) {
            $downloads = FetchUrl::getPages($sites, false, 0, NULL, self::URL,
                self::PAGE, true);
        }
        $results = array();
        $count = count($downloads);
        $this->num_docs = 0;
        for($j = 0 ; $j < $count; $j++) {
            $download = & $downloads[$j];
            if(isset($download[self::PAGE])) {
                $pre_result = @unserialize(webdecode($download[self::PAGE]));
                if(!isset($pre_result["TOTAL_ROWS"]) || 
                    $pre_result["TOTAL_ROWS"] < $this->results_per_block) {
                    $this->more_flags[$lookup[$j]] = false;
                }
                if(isset($pre_result["TOTAL_ROWS"])) {
                    $this->num_docs += $pre_result["TOTAL_ROWS"];
                }
                if(isset($pre_result["PAGES"])) {
                    foreach($pre_result["PAGES"] as $page_data) {
                        if(isset($page_data["KEY"])) {
                            $results[$page_data["KEY"]] = 
                                $page_data;
                        }
                    }
                }
            }
        }
        if($results == array()) {
            $results = -1;
        }
        if($results != -1) {
            if($this->filter != NULL) {
                foreach($results as $keys => $data) {
                    $host_key = 
                        substr($keys, self::HOST_KEY_POS, self::KEY_LEN);
                    if(in_array($host_key, $this->filter) ) {
                        unset($results[$keys]);
                    }
                }
            }
        }
        $this->count_block = count($results);
        $this->pages = $results;

        return $results;
     }

    /**
     * Gets the summaries associated with the keys provided the keys
     * can be found in the current block of docs returned by this iterator
     * @param array $keys keys to try to find in the current block of returned
     *      results
     * @return array doc summaries that match provided keys
     */
    function getSummariesFromCurrentDocs($keys = NULL) 
    {
        if($this->current_block_fresh == false) {
            $pages = $this->currentDocsWithWord();
            if(!is_array($pages)) {
                return $pages;
            }
        } else {
            $pages = & $this->pages;
        }

        return $pages;
    }

}
 ?>
