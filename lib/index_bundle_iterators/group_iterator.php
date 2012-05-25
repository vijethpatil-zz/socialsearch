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
 * This iterator is used to group together documents or document parts
 * which share the same url. For instance, a link document item and 
 * the document that it links to will both be stored in the IndexArchiveBundle
 * by the QueueServer. This iterator would combine both these items into
 * a single document result with a sum of their score, and a summary, if 
 * returned, containing text from both sources. The iterator's purpose is
 * vaguely analagous to a SQL GROUP BY clause
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class GroupIterator extends IndexBundleIterator
{
    /**
     * The iterator we are using to get documents from
     * @var string
     */
    var $index_bundle_iterator;

    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    var $count_block_unfiltered;
    /**
     * The number of documents in the current block after filtering
     * by restricted words
     * @var int
     */
    var $count_block;

    /**
     * hashes of document web pages seen in results returned from the
     * most recent call to findDocsWithWord
     * @var array
     */
    var $current_block_hashes;

    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    var $seen_docs_unfiltered;

    /**
     * hashed url keys used to keep track of track of groups seen so far
     * @var array
     */
    var $grouped_keys;

    /**
     * hashed of document web pages used to keep track of track of 
     *  groups seen so far
     * @var array
     */
    var $grouped_hashes;

    /**
     * Used to keep track and to weight pages based on the number of other
     * pages from the same domain
     * @var array
     */
    var $domain_factors;

    /**
     * Flag used to tell group iterator whether to do a usual grouping
     * or to only look-up parent pages for links for which a parent page
     * hasn't been seen
     * @var bool
     */
    var $only_lookup;

    /**
     * When true, tells any parent iterator not to try to call getIndex,
     * currentGenDocOffsetWithWord, or computeRelevance
     *
     * @var bool
     */
    var $no_lookup;

    /**
     * the minimum number of pages to group from a block;
     * this trumps $this->index_bundle_iterator->results_per_block
     */
    const MIN_FIND_RESULTS_PER_BLOCK = MIN_RESULTS_TO_GROUP;

    /**
     * the minimum length of a description before we stop appending
     * additional link doc summaries
     */
    const MIN_DESCRIPTION_LENGTH = 10;
    /**
     * Creates a group iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over
     * @param int $num_iterators
     * @param bool $only_lookup
     */
    function __construct($index_bundle_iterator, $num_iterators = 1, 
        $only_lookup = false)
    {
        $this->index_bundle_iterator = $index_bundle_iterator;
        $this->num_docs = $this->index_bundle_iterator->num_docs;
        if($only_lookup) {
            $this->results_per_block = 
                $this->index_bundle_iterator->results_per_block;
        } else {
            $this->results_per_block = max(
                $this->index_bundle_iterator->results_per_block,
                self::MIN_FIND_RESULTS_PER_BLOCK);
            $this->results_per_block /=  ceil($num_iterators/2);
        }
        $this->only_lookup = $only_lookup;
        $this->no_lookup = 
            (isset( $this->index_bundle_iterator->no_lookup)) ?
             $this->index_bundle_iterator->no_lookup : false;

        $this->reset();
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        $this->index_bundle_iterator->reset();
        $this->grouped_keys = array();
         $this->grouped_hashes = array();
            // -1 == never save, so file name not used using time to be safer
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
        return $this->index_bundle_iterator->computeRelevance($generation,
            $posting_offset);
    }

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        // first get a block of documents on which grouping can be done

        $pages =  $this->getPagesToGroup();

        $this->count_block_unfiltered = count($pages);
        if(!is_array($pages)) {
            return $pages;
        }
        $this->current_block_hashes = array();
        $this->current_seen_hashes = array();
        if($this->count_block_unfiltered > 0 ) {
            if($this->only_lookup && !$this->no_lookup) {
                $pages = $this->insertUnseenDocs($pages);

                $this->count_block = count($pages);
            } else {
                /* next we group like documents by url and remember 
                   which urls we've seen this block
                */

                $pre_out_pages = $this->groupByHashUrl($pages);

               /*get doc page for groups of link data if exists and don't have
                 also aggregate by hash
               */
               $this->groupByHashAndAggregate($pre_out_pages);
               $this->count_block = count($pre_out_pages);
                /*
                    Calculate aggregate values for each field of the groups we 
                    found
                 */
                $pages = $this->computeOutPages($pre_out_pages);
            }
        }
        $this->pages = $pages;

        return $pages;
    }

    /**
     * Gets a sample of a few hundred pages on which to do grouping by URL
     * 
     * @return array of pages of document key --> meta data arrays
     */
    function getPagesToGroup()
    {
        $pages = array();
        $count = 0;
        $done = false;

        do {
            $new_pages = $this->index_bundle_iterator->currentDocsWithWord();
            if(!is_array($new_pages)) {
                $done = true;
                if(count($pages) == 0) {
                    $pages = -1;
                }
            } else {
                $pages += $new_pages;
                $count = count($pages);
            }
            if($count < $this->results_per_block && !$done) {
                $this->index_bundle_iterator->advance();
            } else {
                $done = true;
            }
        } while($done != true);

        return $pages;
    }

    /**
     * Groups documents as well as mini-pages based on links to documents by
     * url to produce an array of arrays of documents with same url. Since
     * this is called in an iterator, documents which were already returned by
     * a previous call to currentDocsWithWord() followed by an advance() will 
     * have been remembered in grouped_keys and will be ignored in the return 
     * result of this function.
     *
     * @param array &$pages pages to group
     * @return array $pre_out_pages pages after grouping
     */
    function groupByHashUrl(&$pages)
    {
        $pre_out_pages = array();
        foreach($pages as $doc_key => $doc_info) {
            if(!is_array($doc_info) || $doc_info[self::SUMMARY_OFFSET] == 
                self::NEEDS_OFFSET_FLAG) { continue;}
            $hash_url = substr($doc_key, 0, IndexShard::DOC_KEY_LEN);
            // initial aggregate domain score vector for given domain
            if($doc_info[self::IS_DOC]) { 
                if(!isset($pre_out_pages[$hash_url])) {
                    $pre_out_pages[$hash_url] = array();
                }
                array_unshift($pre_out_pages[$hash_url], $doc_info);
            } else {
                $pre_out_pages[$hash_url][] = $doc_info;
            }

            if(!isset($this->grouped_keys[$hash_url])) {
               /*
                    new urls found in this block
                */
                $this->current_block_hashes[] = $hash_url;
            } else {
                unset($pre_out_pages[$hash_url]);
            }
        }

        return $pre_out_pages;
    }

    /**
     * For documents which had been previously grouped by the hash of their
     * url, groups these groups further by the hash of their pages contents.
     * For each group of groups with the same hash summary, this function
     * then selects the subgroup of with the highest aggregate score for
     * that group as its representative. The function then modifies the
     * supplied argument array to make it an array of group representatives.
     *
     * @param array &$pre_out_pages documents previously grouped by hash of url
     */
    function groupByHashAndAggregate(&$pre_out_pages)
    {
        foreach($pre_out_pages as $hash_url => $data) {
            $hash = $pre_out_pages[$hash_url][0][self::HASH];
            $is_location = (crawlHash($hash_url. "LOCATION", true) == $hash);
            if(!$this->no_lookup && (!$data[0][self::IS_DOC] || $is_location)) {
                $item = $this->lookupDoc($data[0]['KEY'], 
                    $is_location, 3); 
                if($item != false) {
                    array_unshift($pre_out_pages[$hash_url], $item);
                }
            }

            $this->aggregateScores($hash_url, $pre_out_pages[$hash_url]);

            if(isset($pre_out_pages[$hash_url][0][self::HASH])) {
                $hash = $pre_out_pages[$hash_url][0][self::HASH];
                if(isset($this->grouped_hashes[$hash])) {
                    unset($pre_out_pages[$hash_url]);
                } else {
                    if(!isset($this->current_seen_hashes[$hash])) {
                        $this->current_seen_hashes[$hash] = array();
                    }
                    if(!isset($this->current_seen_hashes[$hash][$hash_url])) {
                        $this->current_seen_hashes[$hash][$hash_url] = 0;
                    }
                    $this->current_seen_hashes[$hash][$hash_url] += 
                        $pre_out_pages[$hash_url][0][self::HASH_SUM_SCORE];
                }
            }
        }
        foreach($this->current_seen_hashes as $hash => $url_data) {
            arsort($url_data);
            array_shift($url_data);
            foreach($url_data as $hash_url => $value) {
                unset($pre_out_pages[$hash_url]);
            }
        }
    }

    /**
     * Looks up a doc for a link doc_key, so can get its summary info
     *
     * @param string $doc_key key to look up doc of
     * @param bool $is_location we are doing look up because doc had a refresh
     * @param int $depth max recursion depth to carry out lookup to if need
     *      to follow location redirects
     * 
     * @return array consisting of info about the doc
     */
     function lookupDoc($doc_key, $is_location = false, $depth = 3)
     {
        $hash_url = substr($doc_key, 0, IndexShard::DOC_KEY_LEN);
        $prefix = ($is_location) ? "location:" : "info:";
        $hash_info_url=
            crawlHash($prefix.base64Hash($hash_url), true);
        $index = $this->getIndex($doc_key);
        $word_iterator =
             new WordIterator($hash_info_url,
                $index, true);
        $count = 1;
        if(isset($word_iterator->dictionary_info)) {
            $count = count($word_iterator->dictionary_info);
        }
        if($count > 1) { 
            /* if a page is recrawled it gets a second info page,
               this is to ensure we look up the most recent
            */
            $gen_off = array();
            list($gen_off[0], $gen_off[1], , ) =
                 $word_iterator->dictionary_info[
                 $word_iterator->num_generations - 1];
            $word_iterator->advance($gen_off);
        }
        $doc_array = $word_iterator->currentDocsWithWord();
        $item = false;
        if(is_array($doc_array) && count($doc_array) == 1) {
            $relevance =  $this->computeRelevance(
                $word_iterator->current_generation,
                $word_iterator->current_offset);
            $keys = array_keys($doc_array);
            $key = $keys[0];
            $item = $doc_array[$key];
            $hash = substr($key, IndexShard::DOC_KEY_LEN, 
                IndexShard::DOC_KEY_LEN);
            $is2_location = (crawlHash($hash_url. "LOCATION", true) == $hash);
            if($depth > 0) {
                if($is2_location) {
                    return $this->lookupDoc($key, $is2_location, $depth - 1);
                } else if(!isset($item[self::IS_DOC]) || !$item[self::IS_DOC]) {
                    return $this->lookupDoc($key, false, $depth - 1);
                }
            }
            $item[self::RELEVANCE] = $relevance;
            $item[self::SCORE] = $item[self::DOC_RANK]*pow(1.1, $relevance);
            $item['KEY'] = $key;
            $item['INDEX'] = $word_iterator->index;
            $item[self::HASH] = $hash;
            $item[self::INLINKS] = substr($key,
                2*IndexShard::DOC_KEY_LEN, IndexShard::DOC_KEY_LEN);
        }
        return $item;
     }

    /**
     *  This function is called if $raw mode 1 was requested. In this
     *  mode no grouping is done, but if a link does not correspond to
     *  a doc file already listed, then an attempt to look up the doc is
     *  done
     *
     *  @param array $pages an array of links or docs returned by the
     *     iterator that had been fed into this group iterator
     *
     *  @return array new pages where docs have been added if possible
     */
     function insertUnseenDocs($pages)
     {
        $new_pages = array();
        $doc_keys = array_keys($pages);
        $need_docs = array();
        foreach($doc_keys as $key) {
           $hash_url = substr($key, 0, IndexShard::DOC_KEY_LEN);
           $need_docs[$hash_url] = $key;
        }
        $need_docs = array_diff_key($need_docs, $this->grouped_keys);
        foreach($pages as $doc_key => $doc_info) {
            $new_pages[$doc_key] = $doc_info;
            if($doc_info[self::IS_DOC]) {
                if(isset($need_docs[$hash_url])) {
                    unset($need_docs[$hash_url]);
                }
            }
            if(!isset($this->grouped_keys[$hash_url])) {
                /*
                    new url found in this block
                */
                $this->current_block_hashes[] = $hash_url;
            }
        }

        $item_pages = array();
        if(is_array($need_docs)) {
            $need_docs = array_unique($need_docs);
            foreach($need_docs as $hash_url => $doc_key) {
                $item = $this->lookupDoc($doc_key);
                if($item != false) {
                    $item_pages[$hash_url] = $item;
                }
            }
        }

        $new_pages = array_merge($new_pages, $item_pages);
        
        foreach($new_pages as $doc_key => $doc_info) {
            $new_pages[$doc_key][self::SUMMARY_OFFSET] = array();
            $new_pages[$doc_key][self::SUMMARY_OFFSET][] = 
                array($doc_info["KEY"], $doc_info[self::GENERATION],
                        $doc_info[self::SUMMARY_OFFSET]);
        }

        return $new_pages;
     }

    /**
     * For a collection of grouped pages generates a grouped summary for each
     * group and returns an array of out pages consisting 
     * of single summarized documents for each group. These single summarized 
     * documents have aggregated scores. 
     *
     * @param array &$pre_out_pages array of groups of pages for which out pages
     *      are to be generated.
     * @return array $out_pages array of single summarized documents
     */
    function computeOutPages(&$pre_out_pages)
    {
        $out_pages = array();

        foreach($pre_out_pages as $hash_url => $group_infos) {
            $out_pages[$hash_url] = $pre_out_pages[$hash_url][0];
            $out_pages[$hash_url][self::SUMMARY_OFFSET] = array();
            unset($out_pages[$hash_url][self::GENERATION]);

            $hash_count = $out_pages[$hash_url][self::HASH_URL_COUNT];
            for($i = 0; $i < $hash_count; $i++) {
                $doc_info = $group_infos[$i];
                if(isset($doc_info[self::GENERATION])) {
                    $out_pages[$hash_url][self::SUMMARY_OFFSET][] = 
                        array($doc_info["KEY"], $doc_info[self::GENERATION],
                            $doc_info[self::SUMMARY_OFFSET]);
                }
            }
            $out_pages[$hash_url][self::SCORE] = 
                $out_pages[$hash_url][self::HASH_SUM_SCORE]; 
        }
        return $out_pages;
    }

    /**
     * For a collection of pages each with the same url, computes the page
     * with the min score, max score, as well as the sum of the score,
     * sum of the ranks, sum of the relevance score, and count. Stores this
     * information in the first element of the array of pages.
     *
     *  @param array &$pre_hash_page pages to compute scores for
     */
    function aggregateScores($hash_url, &$pre_hash_page)
    {
        $sum_score = 0;
        $sum_rank = 0;
        $sum_relevance = 0;
        $sum_proximity = 0;
        $min = 1000000; //no score will be this big
        $max = -1;
        $domain_weights = array();
        foreach($pre_hash_page as $hash_page) {
            if(isset($hash_page[self::SCORE])) {
                $current_rank = $hash_page[self::DOC_RANK];
                $hash_host = $hash_page[self::INLINKS];
                if(!isset($domain_weights[$hash_host])) {
                    $domain_weights[$hash_host] = 1;
                }
                $relevance_boost = 1;
                if(substr($hash_url, 1) == substr($hash_host, 1)) {
                    $relevance_boost = 2;
                }
                $min = ($current_rank < $min ) ? $current_rank : $min;
                $max = ($max < $current_rank ) ? $current_rank : $max;
                $alpha = $relevance_boost * $domain_weights[$hash_host];
                $sum_score += $hash_page[self::DOC_RANK] 
                    * $alpha * pow(1.1,$hash_page[self::RELEVANCE]) *
                    $hash_page[self::PROXIMITY];
                $sum_rank += $alpha * $hash_page[self::DOC_RANK];
                $sum_relevance += $alpha * $hash_page[self::RELEVANCE];
                $sum_proximity += $alpha * $hash_page[self::PROXIMITY];
                $domain_weights[$hash_host] *=  0.5;
            }
        }
        
        $pre_hash_page[0][self::MIN] = $min;
        $pre_hash_page[0][self::MAX] = $max;
        $pre_hash_page[0][self::HASH_SUM_SCORE] = $sum_score;

        $pre_hash_page[0][self::DOC_RANK] = $sum_rank;
        $pre_hash_page[0][self::HASH_URL_COUNT] = count($pre_hash_page);
        $pre_hash_page[0][self::RELEVANCE] = $sum_relevance;
        $pre_hash_page[0][self::PROXIMITY] = $sum_proximity;
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
            $result = $this->currentDocsWithWord();
            if(!is_array($result) || $this->no_lookup) {
                return $result;
            }
        }
        if(!is_array($this->pages)) {
            return $this->pages;
        }
        if($keys == NULL) {
            $keys = array_keys($this->pages);
        }
        $out_pages = array();
        foreach($keys as $doc_key) {
            if(!isset($this->pages[$doc_key])) {
                continue;
            } else {
                $doc_info = $this->pages[$doc_key];
            }

            if(isset($doc_info[self::SUMMARY_OFFSET]) && 
                is_array($doc_info[self::SUMMARY_OFFSET])) {
                $out_pages[$doc_key] = $doc_info;
                foreach($doc_info[self::SUMMARY_OFFSET] as $offset_array) {
                    list($key, $generation, $summary_offset) = $offset_array;
                    if(isset($doc_info['INDEX'])) {
                        $index = $doc_info['INDEX'];
                    } else {
                        $index = $this->getIndex($key);
                    }
                    $index->setCurrentShard($generation, true);
                    $page = @$index->getPage($summary_offset);
                    if(!$page || $page == array()) {continue;}
                    $ellipsis_used = false;
                    if(!isset($out_pages[$doc_key][self::SUMMARY])) {
                        $out_pages[$doc_key][self::SUMMARY] = $page;
                    } else if (isset($page[self::DESCRIPTION])) {
                        if(!isset($out_pages[$doc_key][
                            self::SUMMARY][self::DESCRIPTION])) {
                            $out_pages[$doc_key][self::SUMMARY][
                                self::DESCRIPTION] = "";
                        }
                        $out_pages[$doc_key][self::SUMMARY][self::DESCRIPTION].=
                            " .. ".$page[self::DESCRIPTION];
                        $ellipsis_used = true;
                    }
                    if($ellipsis_used && strlen($out_pages[$doc_key][
                        self::SUMMARY][self::DESCRIPTION]) > 
                        self::MIN_DESCRIPTION_LENGTH) {
                        /* want at least one ellipsis in case terms only appear
                           in links
                         */
                        break;
                    }
                }
            }
        }

        return $out_pages;

    }



    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *      the must be of greater than or equal generation, and if equal the
     *      next block must all have $doc_offsets larger than or equal to 
     *      this value
     */
    function advance($gen_doc_offset = NULL) 
    {
        $this->advanceSeenDocs();

        $this->seen_docs_unfiltered += $this->count_block_unfiltered;

        if($this->seen_docs_unfiltered > 0) {
            if($this->count_block_unfiltered < $this->results_per_block) {
                $this->num_docs = $this->seen_docs;
            } else {
                $this->num_docs = 
                    floor(
                    ($this->seen_docs*$this->index_bundle_iterator->num_docs)/
                    $this->seen_docs_unfiltered);
            }
        } else {
            $this->num_docs = 0;
        }
        
        
        foreach($this->current_block_hashes as $hash_url) {
            $this->grouped_keys[$hash_url] = true;
        }

        foreach($this->current_seen_hashes as $hash => $url_data) {
            $this->grouped_hashes[$hash] = true;
        }

        $this->index_bundle_iterator->advance($gen_doc_offset);

    }

    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        $this->index_bundle_iterator->currentGenDocOffsetWithWord();
    }


    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
    {
        return $this->index_bundle_iterator->getIndex($key);
    }
}
?>
