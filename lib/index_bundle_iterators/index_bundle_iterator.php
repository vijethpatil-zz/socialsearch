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
 * Abstract classed used to model iterating documents indexed in 
 * an IndexArchiveBundle or set of such bundles. 
 *
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
abstract class IndexBundleIterator implements CrawlConstants
{

    /**
     * Estimate of the number of documents that this iterator can return
     * @var int
     */
    var $num_docs;

    /**
     * The number of documents already iterated over
     * @var int
     */
    var $seen_docs;

    /**
     * The number of documents in the current block
     * @var int
     */
    var $count_block;

    /**
     * Cache of what currentDocsWithWord returns
     * @var array
     */
    var $pages;

    /**
     * Says whether the value in $this->count_block is up to date
     * @var bool
     */
    var $current_block_fresh;

    /**
     * Number of documents returned for each block (at most)
     * @var int
     */
    var $results_per_block = self::RESULTS_PER_BLOCK;

    /**
     *  Default number of documents returned for each block (at most)
     * @var int
     */
    const RESULTS_PER_BLOCK = 100;

    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *      relevance of
     * @return float a relevancy score based on BM25F.
     */
    abstract function computeRelevance($generation, $posting_offset);

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    abstract function reset();

    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *      the must be of greater than or equal generation, and if equal the
     *      next block must all have $doc_offsets larger than or equal to 
     *      this value
     */
    abstract function advance($gen_doc_offset = null);

    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    abstract function getIndex($key = NULL);

    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    abstract function currentGenDocOffsetWithWord();

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
     abstract function findDocsWithWord();

    /**
     *
     */
     function genDocOffsetCmp($gen_doc1, $gen_doc2) 
     {
        //less generation
        if($gen_doc1[0] < $gen_doc2[0]) {
            return -1;
        }
        //equal generation
        if($gen_doc1[0] == $gen_doc2[0]) {
            if($gen_doc1[1] == $gen_doc2[1]) {
                return 0; //equal offset
            } else if ($gen_doc1[1] < $gen_doc2[1]) {
                return -1; // less offset
            }
        }
        return 1;
     }


    /**
     * Gets the current block of doc ids and score associated with the
     * this iterators word
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function currentDocsWithWord()
    {
        if($this->current_block_fresh == true) {
            return $this->pages;
        }
        $this->current_block_fresh = true;
        return $this->findDocsWithWord();
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
        $index = $this->getIndex(); //objects assgined by ref

        if($this->current_block_fresh == false) {
            $pages = $this->currentDocsWithWord();
            if(!is_array($pages)) {
                return $pages;
            }
        } else {
            $pages = & $this->pages;
        }
        if($keys == NULL) {
            if(is_array($pages)) {
                $keys = array_keys($pages);
            } else {
                return NULL;
            }
        }
        $out_pages = array();

        foreach($keys as $doc_key) {
            if(!isset($pages[$doc_key])) {
                continue;
            } else {
                $doc_info = $pages[$doc_key];
            }
            if(isset($doc_info[self::SUMMARY_OFFSET]) &&
                isset($doc_info[self::GENERATION])) {
                $index->setCurrentShard($doc_info[self::GENERATION], true);
                $page = $index->getPage($doc_info[self::SUMMARY_OFFSET]);
                $out_pages[$doc_key] = $doc_info;
                $out_pages[$doc_key][self::SUMMARY] = $page;
            }
        }
        return $out_pages;
    }

    /**
     * Get the current block of doc summaries for the word iterator and advances
     * the current pointer to the next blockof documents. If a doc index is
     * the next block must be of docs after this doc_index
     *
     * @param $doc_offset if set the next block must all have $doc_offsets 
     *      equal to or larger than this value
     * @return array doc summaries matching the $this->restrict_phrases
     */
    function nextDocsWithWord($doc_offset = null)
    {
        $doc_block = $this->getSummariesFromCurrentDocs();
        
        if($doc_block == -1 || !is_array($doc_block) ) {
            return NULL;
        }

        $this->advance($doc_offset);
        
        return $doc_block;

    }

    /**
     * Updates the seen_docs count during an advance() call
     */
    function advanceSeenDocs()
    {
        if($this->current_block_fresh != true) {
            $doc_block = $this->currentDocsWithWord();
            if($doc_block == -1 || !is_array($doc_block) ) {
                return;
            }
        }
        $this->current_block_fresh = false;
        $this->seen_docs += $this->count_block;
    }

    /**
     * Sets the value of the result_per_block field. This field controls
     * the maximum number of results that can be returned in one go by
     * currentDocsWithWord()
     *
     * @param int $num the maximum number of results that can be returned by
     *      a block
     */
     function setResultsPerBlock($num) {
        $this->results_per_block = $num;
     }
}
?>
