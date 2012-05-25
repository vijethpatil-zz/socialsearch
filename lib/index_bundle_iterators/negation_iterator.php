<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012 Chris Pollett chris@pollett.org
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
 * Used to iterate over the documents which dont' occur in a set of
 * iterator results
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class NegationIterator extends IndexBundleIterator
{
    /**
     * An array of iterators whose interection we  get documents from
     * @var array
     */
    var $index_bundle_iterators;
    /**
     * Number of elements in $this->index_bundle_iterators
     * @var int
     */
    var $num_iterators;
    /**
     * Index of the iterator amongst those we are intersecting to advance
     * next
     * @var int
     */
    var $to_advance_index;

    /**
     * Creates a negation iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over
     */
    function __construct($index_bundle_iterator)
    {
        $this->index_bundle_iterators[0] = new WordIterator(
            crawlHash("site:all", true), 
            $index_bundle_iterator->index, true,
            $index_bundle_iterator->filter);
        $this->index_bundle_iterators[1] = $index_bundle_iterator;

        $this->num_iterators = 2;
        $this->num_docs = 0;
        $this->results_per_block = 1;

        $this->num_docs = $this->index_bundle_iterators[0]->num_docs;

        $this->reset();
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        for($i = 0; $i < $this->num_iterators; $i++) {
            $this->index_bundle_iterators[$i]->setResultsPerBlock(1);
            $this->index_bundle_iterators[$i]->reset();
        }

        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;

    }

    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation
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
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and rank if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {

        $status = $this->syncGenDocOffsetsAmongstIterators();

        if($status == -1) {
            return -1;
        }
        //next we finish computing BM25F
        $docs = $this->index_bundle_iterators[0]->currentDocsWithWord();

        if(is_array($docs) && count($docs) == 1) {
            //we get intersect docs one at a time so should be only one
            $keys = array_keys($docs);
            $key = $keys[0];

            $docs[$key][self::RELEVANCE] = 1;
            $docs[$key][self::PROXIMITY] = 1;

            $docs[$key][self::SCORE] = $docs[$key][self::DOC_RANK] *
                 $docs[$key][self::RELEVANCE] * $docs[$key][self::PROXIMITY];
        }
        $this->count_block = count($docs); 
        $this->pages = $docs;
        return $docs;
    }


    /**
     * Finds the next generation and doc offset amongst the all docs iterator
     * and the term to be negated iterator such that the all iterator is
     * strictly less than the term iterator.
     */
    function syncGenDocOffsetsAmongstIterators()
    {
        $changed_term = false;
        $changed_all = false;
        do {
            $gen_offset_all = $this->index_bundle_iterators[
                0]->currentGenDocOffsetWithWord();
            if($gen_offset_all == -1 || ($changed_all &&
                $this->genDocOffsetCmp($gen_offset_all, 
                $old_gen_offset_all) == 0)) {
                return -1;
            }
            $gen_offset_term = 
                $this->index_bundle_iterators[
                    1]->currentGenDocOffsetWithWord();
            if($gen_offset_term == -1 || ($changed_term &&
                $this->genDocOffsetCmp($gen_offset_term, 
                $old_gen_offset_term) == 0)) {
                return -1;
            }
            $gen_doc_cmp = $this->genDocOffsetCmp($gen_offset_all, 
                $gen_offset_term);
            if($gen_doc_cmp > 0) {
                $this->index_bundle_iterators[1]->advance($gen_offset_all);
                $old_gen_offset_term = $gen_offset_term;
                $changed_term = true;
                $changed_all = false;
            } else if($gen_doc_cmp == 0) {
                $this->index_bundle_iterators[0]->advance($gen_offset_term);
                $old_gen_offset_all = $gen_offset_all;
                $changed_term = false;
                $changed_all = true;
            }
        } while($gen_doc_cmp >= 0);

        return 1;
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

        $this->index_bundle_iterators[0]->advance($gen_doc_offset);

    }

    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        $this->syncGenDocOffsetsAmongstIterators();
        return $this->index_bundle_iterators[0]->currentGenDocOffsetWithWord();
    }

    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
    {
        return $this->index_bundle_iterators[0]->getIndex($key = NULL);
    }

    /**
     * This method is supposed to set
     * the value of the result_per_block field. This field controls
     * the maximum number of results that can be returned in one go by
     * currentDocsWithWord(). This method cannot be consistently
     * implemented for this iterator and expect it to behave nicely
     * it this iterator is used together with union_iterator. So
     * to prevent a user for doing this, calling this method results
     * in a user defined error
     *
     * @param int $num the maximum number of results that can be returned by
     *      a block
     */
     function setResultsPerBlock($num) {
        if($num != 1) {
            trigger_error("Cannot set the results per block of
                a negation iterator", E_USER_ERROR);
        }
     }
}
?>
