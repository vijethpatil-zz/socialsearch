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
 * Used to iterate through the documents associated with a word in
 * an IndexArchiveBundle. It also makes it easy to get the summaries
 * of these documents.
 *
 * A description of how words and the documents containing them are stored 
 * is given in the documentation of IndexArchiveBundle. 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class WordIterator extends IndexBundleIterator
{
    /**
     * hash of word that the iterator iterates over 
     * @var string
     */
    var $word_key;
    /**
     * The IndexArchiveBundle this index is associated with
     * @var object
     */
    var $index;

    /**
     * The next byte offset in the IndexShard
     * @var int
     */
    var $next_offset;

    /**
     * An array of shard generation and posting list offsets, lengths, and
     * numbers of documents
     * @var array
     */
    var $dictionary_info;

    /**
     * The total number of shards that have data for this word
     * @var int
     */
    var $num_generations;

    /**
     * Index into dictionary_info corresponding to the current shard
     * @var int
     */
    var $generation_pointer;

    /**
     * Numeric number of current shard
     * @var int
     */
    var $current_generation;

    /**
     * The current byte offset in the IndexShard
     * @var int
     */
    var $current_offset;

    /**
     * Starting Offset of word occurence in the IndexShard
     * @var int
     */
    var $start_offset;

    /**
     * Last Offset of word occurence in the IndexShard
     * @var int
     */
    var $last_offset;

    /**
     * Keeps track of whether the word_iterator list is empty becuase the
     * word does not appear in the index shard
     * @var int
     */
    var $empty;

    /**
     * Keeps track of whether the word_iterator list is empty becuase the
     * word does not appear in the index shard
     * @var int
     */
    var $filter;

    /** Host Key position + 1 (first char says doc, inlink or eternal link)*/
    const HOST_KEY_POS = 17;

    /** Length of a doc key*/
    const KEY_LEN = 8;

    static $start_time = 0;
    /**
     * Creates a word iterator with the given parameters.
     *
     * @param string $word_key hash of word or phrase to iterate docs of 
     * @param object $index the IndexArchiveBundle to use
     * @param int $limit the first element to return from the list of docs
     *      iterated over
     * @param bool $raw whether the $word_key is our variant of base64 encoded
     * @param array $filter an array of hashes of domains to filter from
     *      results
     */
    function __construct($word_key, $index, $raw = false, &$filter = NULL)
    {
        if($raw == false) {
            //get rid of out modfied base64 encoding
            $hash = str_replace("_", "/", $word_key);
            $hash = str_replace("-", "+" , $hash);
            $hash .= "=";
            $word_key = base64_decode($hash);

        }
        
        if($filter != NULL) {
            $this->filter = & $filter;
        } else {
            $this->filter = NULL;
        }

        $this->word_key = $word_key;

        $this->index =  $index;
        $this->current_block_fresh = false;
        $this->dictionary_info = 
            $index->dictionary->getWordInfo($word_key, true);
        if ($this->dictionary_info === false) {
            $this->empty = true;
        } else {
            $this->num_generations = count($this->dictionary_info);
            if($this->num_generations == 0) 
            {
                $this->empty = true;
            } else {
                $this->num_docs = 0;
                for($i = 0; $i < $this->num_generations; $i++) {
                    list(, , , $num_docs) =
                        $this->dictionary_info[$i];
                        $this->num_docs += $num_docs;
                }
                $this->empty = false;
                $this->reset();
            }
        }
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
        $item = array();
        $this->index->setCurrentShard($generation, true);
        $num_docs_or_links = 
            IndexShard::numDocsOrLinks($this->start_offset, $this->last_offset);
        $this->index->getCurrentShard()->makeItem($item, 
            $posting_offset, $num_docs_or_links, 1);
        return $item[self::RELEVANCE];
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     *
     */
    function reset()
    {
        if(!$this->empty) {// we shouldn't be called when empty - but to be safe
            list($this->current_generation, $this->start_offset, 
                $this->last_offset, ) 
                = $this->dictionary_info[0];
        } else {
            $this->start_offset = 0;
            $this->last_offset = -1;
            $this->num_generations = -1;
        }
        $this->current_offset = $this->start_offset;
        $this->generation_pointer = 0;
        $this->count_block = 0;
        $this->seen_docs = 0;

    }


    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        if($this->empty || ($this->generation_pointer >= $this->num_generations)
            || ($this->generation_pointer == $this->num_generations -1 &&
            $this->current_offset > $this->last_offset)) {
            return -1;
        }
        $this->next_offset = $this->current_offset;
        $this->index->setCurrentShard($this->current_generation, true);

        //the next call also updates next offset
        $shard = $this->index->getCurrentShard();
        $pre_results = $shard->getPostingsSlice(
            $this->start_offset,
            $this->next_offset, $this->last_offset, $this->results_per_block);
        $filter = ($this->filter == NULL) ? array() : $this->filter;
        foreach($pre_results as $keys => $data) {
            $host_key = substr($keys, self::HOST_KEY_POS, self::KEY_LEN);
            if(in_array($host_key, $filter) ) {
                continue;
            }
            $data['KEY'] = $keys;
            $hash_url = substr($keys, 0, IndexShard::DOC_KEY_LEN);
            $data[self::HASH] = substr($keys, 
                IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
            // inlinks is the domain of the inlink
            $data[self::INLINKS] = substr($keys, 
                2 * IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
            $results[$keys] = $data;
        }
        $this->count_block = count($results);
        $this->pages = $results;

        return $results;
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
        if($this->current_offset < $this->next_offset) {
            $this->current_offset = $this->next_offset;
        } else {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        
        if($this->current_offset > $this->last_offset) {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        if($gen_doc_offset !== null) {
            $last_current_generation = -1;
            if($this->current_generation < $gen_doc_offset[0]) {
                $this->advanceGeneration($gen_doc_offset[0]);
                $this->next_offset = $this->current_offset;
            }
            $this->index->setCurrentShard($this->current_generation, true);
            if($this->current_generation == $gen_doc_offset[0]) {
                $this->current_offset =
                    $this->index->getCurrentShard(
                        )->nextPostingOffsetDocOffset($this->next_offset,
                            $this->last_offset, $gen_doc_offset[1]);
                if($this->current_offset === false) {
                    $this->advanceGeneration();
                    $this->next_offset = $this->current_offset;
                }
            }
            $this->seen_docs = 
                ($this->current_offset - $this->start_offset)/
                    IndexShard::POSTING_LEN;
        }

    }

    /**
     * Switches which index shard is being used to return occurences of
     * the nord to the next shard containing the word
     *
     * @param int $generation generation to advance beyond
     */
    function advanceGeneration($generation = null)
    {
        if($generation === null) {
            $generation = $this->current_generation;
        }
        do {
            if($this->generation_pointer < $this->num_generations) {
                $this->generation_pointer++;
            }
            if($this->generation_pointer < $this->num_generations) {
                list($this->current_generation, $this->start_offset, 
                    $this->last_offset, ) 
                    = $this->dictionary_info[$this->generation_pointer];
                $this->current_offset = $this->start_offset;
            }
       } while($this->current_generation < $generation &&
            $this->generation_pointer < $this->num_generations);
    }


    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        if($this->current_offset > $this->last_offset ||
            $this->generation_pointer >= $this->num_generations) {
            return -1;
        }
        $this->index->setCurrentShard($this->current_generation, true);
        return array($this->current_generation, $this->index->getCurrentShard(
                        )->docOffsetFromPostingOffset($this->current_offset));
    }

    /**
     * Returns the index associated with this iterator
     * @return &object the index
     */
    function getIndex($key = NULL)
    {
        return $this->index;
    }
}
?>
