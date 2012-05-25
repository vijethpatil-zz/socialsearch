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
 *Loads common constants for web crawling
 */
require_once  BASE_DIR.'/lib/crawl_constants.php';

/**
 * Data structure used to store one generation worth of the word document
 * index (inverted index).
 * This data structure consists of three main components a word entries,
 * word_doc entries, and document entries.
 *
 * Word entries are described in the documentation for the words field.
 *
 * Word-doc entries are described in the documentation for the word_docs field
 *
 * Document entries are described in the documentation for the doc_infos field
 * 
 * IndexShards also have two access modes a $read_only_from_disk mode and 
 * a loaded in memory mode. Loaded in memory mode is mainly for writing new
 * data to the shard. When in memory, data in the shard can also be in one of 
 * two states packed or unpacked. Roughly, when it is in a packed state it is 
 * ready to be serialized to disk; when it is an unpacked state it methods 
 * for adding data can be used.
 *
 * Serialized on disk, a shard has a header with document statistics followed
 * by the a prefix index into the words component, followed by the word
 * component itself, then the word-docs component, and finally the document
 * component.
 * 
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
 
class IndexShard extends PersistentStructure implements 
    CrawlConstants
{
    /**
     * Stores document id's and links to documents id's together with
     * summary offset information, and number of words in the doc/link
     * The format for a record is 4 byte offset, followed by
     * 3 bytes for the document length, followed by 1 byte containing
     * the number of 8 byte doc key strings that make up the doc id (2 for
     * a doc, 3 for a link), followed by the doc key strings themselves.
     * In the case of a document the first doc key string has a hash of the
     * url, the second a hash a tag stripped version of the document. 
     * In the case of a link, the keys are a unique identifier for the link 
     * context, followed by  8 bytes for
     * the hash of the url being pointed to by the link, followed by 8
     * bytes for the hash of "info:url_pointed_to_by_link".
     * @var string
     */
    var $doc_infos;
    /**
     *  Length of $doc_infos as a string
     *  @var int
     */
    var $docids_len;

    /**
     * This string is non-empty when shard is loaded and in its packed state.
     * It consists of a sequence of posting records. Each posting
     * consists of a offset into the document entries structure
     * for a document containing the word this is the posting for,
     * as well as the number of occurrences of that word in that document.
     * @var string
     */
    var $word_docs;
    /**
     *  Length of $word_docs as a string
     *  @var int
     */
    var $word_docs_len;

    /**
     * Stores the array of word entries for this shard
     * In the packed state, word entries consist of the word id, 
     * a generation number, an offset into the word_docs structure 
     * where the posting list for that word begins,
     * and a length of this posting list. In the unpacked state
     * each entry is a string of all the posting items for that word
     * Periodically data in this words array is flattened to the word_postings
     * string which is a more memory efficient was of storing data in PHP
     * @var array
     */
    var $words;

    /**
     * Stores length of the words array in the shard on disk. Only set if
     * we're in $read_only_from_disk mode
     *
     * @var int
     */
     var $words_len;

    /**
     * An array representing offsets into the words dictionary of the index of 
     * the first occurrence of a two byte prefix of a word_id. 
     *
     * @var array
     */
    var $prefixes;

    /**
     * Length of the prefix index into the dictionary of the shard
     *
     * @var int
     */
    var $prefixes_len;

    /**
     * This is supposed to hold the number of earlier shards, prior to the 
     * current shard.
     * @var int
     */
    var $generation;

    /**
     * This is supposed to hold the number of documents that a given shard can
     * hold.
     * @var int
     */
    var $num_docs_per_generation;

    /**
     * Number of documents (not links) stored in this shard
     * @var int
     */
    var $num_docs;
    /**
     * Number of links (not documents) stored in this shard
     * @var int
     */
    var $num_link_docs;
    /**
     * Number of words stored in total in all documents in this shard
     * @var int
     */
    var $len_all_docs;
    /**
     * Number of words stored in total in all links in this shard
     * @var int
     */
    var $len_all_link_docs;

    /**
     * File handle for a shard if we are going to use it in read mode
     * and not completely load it.
     *
     * @var resource
     */
    var $fh;

    /**
     * An cached array of disk blocks for an index shard that has not
     * been completely loaded into memory.
     * @var array
     */
    var $blocks;

    /**
     * Flag used to determined if this shard is going to be largely kept on
     * disk and to be in read only mode. Otherwise, shard will assume to
     * be completely held in memory and be read/writable.
     * @var bool
     */
    var $read_only_from_disk;

    /**
     * Keeps track of the packed/unpacked state of the word_docs list
     *
     * @var bool
     */
    var $word_docs_packed;

    /**
     * Keeps track of the length of the shard as a file
     *
     * @var int
     */
    var $file_len;

    /**
     * Number of document inserts since the last time word data was flattened
     * to the word_postings string.
     */
     var $last_flattened_words_count;

    /**
     * Used to hold word_id, posting_len, posting triples as a memory efficient
     * string
     * @var string
     */
    var $word_postings;
     
    /**
     * Fraction of NUM_DOCS_PER_GENERATION document inserts before data
     * from the words array is flattened to word_postings. (It will
     * also be flattened during periodic index saves)
     */
    const FLATTEN_FREQUENCY = 10000;

    /**
     * Bytes of tmp string allowed during flattenings
     */
     const WORD_POSTING_COPY_LEN = 2000000;

    /**
     * Used to keep track of whether a record in document infos is for a
     * document or for a link
     */
    const LINK_FLAG =  0x800000;

    /**
     * Size in bytes of one block in IndexShard
     */
    const SHARD_BLOCK_SIZE = 4096;

    /**
     * Header Length of an IndexShard (sum of its non-variable length fields)
     */
    const HEADER_LENGTH = 40;

    /**
     * Length of a Word entry in bytes in the shard
     */
    const WORD_ITEM_LEN = 20;

    /**
     * Length of a word entry's key in bytes
     */
    const WORD_KEY_LEN = 8;

    /**
     * Length of a key in a DOC ID.
     */
    const DOC_KEY_LEN = 8;

    /**
     * Length of one posting ( a doc offset occurrence pair) in a posting list
     */
    const POSTING_LEN = 4;

    /**
     *  Represents an empty prefix item
     */
    const BLANK = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";

    /**
     * Flag used to indicate that a word item should not be packed or unpacked
     */
    const HALF_BLANK = "\xFF\xFF\xFF\xFF";

    /**
     *  Represents an empty prefix item
     */
    const STORE_FLAG = "\x80";

    /**
     * Makes an index shard with the given file name and generation offset
     *
     * @param string $fname filename to store the index shard with
     * @param int $generation when returning documents from the shard
     *      pretend there ar ethis many earlier documents
     * @param bool $read_only_from_disk used to determined if this shard is 
     *      going to be largely kept on disk and to be in read only mode. 
     *      Otherwise, shard will assume to be completely held in memory and be 
     *      read/writable.
     */
    function __construct($fname, $generation = 0, 
        $num_docs_per_generation = NUM_DOCS_PER_GENERATION,
        $read_only_from_disk = false)
    {
        parent::__construct($fname, -1);
        $this->hash_name = crawlHash($fname);
        $this->generation = $generation;
        $this->num_docs_per_generation = $num_docs_per_generation;
        $this->word_docs = "";
        $this->word_postings = "";
        $this->words_len = 0;
        $this->word_docs_len = 0;
        $this->last_flattened_words_count = 0;
        $this->words = array();
        $this->docids_len = 0;
        $this->doc_infos = "";
        $this->num_docs = 0;
        $this->num_link_docs = 0;
        $this->len_all_docs = 0;
        $this->len_all_link_docs = 0;
        $this->blocks = array();
        $this->fh = NULL;
        $this->read_only_from_disk = $read_only_from_disk;
        $this->word_docs_packed = false;
    }

    /**
     * Add a new document to the index shard with the given summary offset.
     * Associate with this document the supplied list of words and word counts.
     * Finally, associate the given meta words with this document.
     *
     * @param string $doc_keys a string of concatenated keys for a document 
     *      to insert. Each key is assumed to be a string of DOC_KEY_LEN many 
     *      bytes. This whole set of keys is viewed as fixing one document.
     * @param int $summary_offset its offset into the word archive the
     *      document's data is stored in
     * @param array $word_lists (word => array of word positions in doc)
     * @param array $meta_ids meta words to be associated with the document
     *      an example meta word would be filetype:pdf for a PDF document.
     * @param bool $is_doc flag used to indicate if what is being sored is
     *      a document or a link to a document
     * @param mixed $rank either false if not used, or a 4 bit estimate of the
     *      rank of this document item
     * @return bool success or failure of performing the add
     */
    function addDocumentWords($doc_keys, $summary_offset, $word_lists,
        $meta_ids = array(), $is_doc = false, $rank = false)
    {
        if($this->word_docs_packed == true) {
            $this->words = array();
            $this->word_docs = "";
            $this->word_docs_packed = false;
        }

        $doc_len = 0;
        $link_doc_len = 0;
        $len_key = strlen($doc_keys);
        $num_keys = floor($len_key/self::DOC_KEY_LEN);

        if($num_keys * self::DOC_KEY_LEN != $len_key) return false;

        if($num_keys % 2 == 0 ) {
            $doc_keys .= self::BLANK; //want to keep docids_len divisible by 16
        }

        $summary_offset_string = packInt($summary_offset);
        $added_len = strlen($summary_offset_string);
        $this->doc_infos .= $summary_offset_string;

        if($is_doc) { 
            $this->num_docs++;
        } else { //link item
            $this->num_link_docs++;
        }
        foreach($meta_ids as $meta_id) {
            $word_lists[$meta_id] = array();
        }

        //using $this->docids_len divisible by 16
        $doc_offset = $this->docids_len >> 4;
        foreach($word_lists as $word => $position_list) {
            $occurrences = count($position_list);
            $word_id = crawlHash($word, true);
            $store = $this->packPosting($doc_offset, $position_list);

            if(!isset($this->words[$word_id])) {
                $this->words[$word_id] = $store;
            } else {
                $this->words[$word_id] .= $store;
            }
            if($occurrences > 0) {
                if($is_doc == true) {
                    $doc_len += $occurrences;
                } else {
                    $link_doc_len += $occurrences;
                }
            }
            $this->word_docs_len += strlen($store);
        }

        $this->len_all_docs += $doc_len;
        $this->len_all_link_docs += $link_doc_len;
        $flags = ($is_doc) ? 0 : self::LINK_FLAG;
        if($rank !== false) {
            $rank &= 0x0f;
            $rank <<= 19;
            $flags += $rank;
        }
        $item_len = ($is_doc) ? $doc_len: $link_doc_len;
        $len_num_keys = $this->packDoclenNum(($flags + $item_len), $num_keys);

        $this->doc_infos .=  $len_num_keys;
        $added_len += strlen($len_num_keys);
        $this->doc_infos .= $doc_keys;
        $added_len += strlen($doc_keys);
        $this->docids_len += $added_len;

        return true;
    }

    /**
     * Returns the first offset, last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word.
     *
     * @param string $word_id id of the word one wants to look up
     * @param bool $raw whether the id is our version of base64 encoded or not
     * @return array first offset, last offset, count
     */
    function getWordInfo($word_id, $raw = false)
    {
        if($raw == false) {
            //get rid of out modfied base64 encoding
            $word_id = unbase64Hash($word_id);
        }

        $is_disk = $this->read_only_from_disk;
        $word_item_len = self::WORD_ITEM_LEN;

        if($is_disk) {
            $this->getShardHeader();

            $prefix = (ord($word_id[0]) << 8) + ord($word_id[1]);
            $prefix_info = $this->getShardSubstring(
                self::HEADER_LENGTH + 8*$prefix, 8);
            if($prefix_info == self::BLANK) {
                return false;
            }
            $offset = unpackInt(substr($prefix_info, 0, 4));

            $high = unpackInt(substr($prefix_info, 4, 4)) - 1;

            $start = self::HEADER_LENGTH + $this->prefixes_len  + $offset;
        } else {
            if($this->word_docs_packed == false) {
                $this->mergeWordPostingsToString();
                $this->packWords(NULL);
                $this->outputPostingLists();
            }
            $start = 0;
            $high = (strlen($this->words) - $word_item_len)/$word_item_len;
        }
        $low = 0;
        $check_loc = (($low + $high) >> 1);
        do {
            $old_check_loc = $check_loc;
            if($is_disk) {
                $word_string = $this->getShardSubstring($start + 
                    $check_loc * $word_item_len, $word_item_len);
            } else {
                $word_string = substr($this->words, $start + 
                    $check_loc * $word_item_len, $word_item_len);
            }
            if($word_string == false) {return false;}
            $id = substr($word_string, 0, self::WORD_KEY_LEN);
            $cmp = strcmp($word_id, $id);
            if($cmp === 0) {
                return $this->getWordInfoFromString(
                    substr($word_string, self::WORD_KEY_LEN));
            } else if ($cmp < 0) {
                $high = $check_loc;
                $check_loc = (($low + $check_loc) >> 1);
            } else {
                if($check_loc + 1 == $high) {
                    $check_loc++;
                }
                $low = $check_loc;
                $check_loc = (($high + $check_loc) >> 1);
            }
        } while($old_check_loc != $check_loc);

        return false;

    }

    /**
     * Returns documents using the word_docs string (either as stored
     * on disk or completely read in) of records starting
     * at the given offset and using its link-list of records. Traversal of
     * the list stops if an offset larger than $last_offset is seen or
     * $len many doc's have been returned. Since $next_offset is passed by
     * reference the value of $next_offset will point to the next record in
     * the list (if it exists) after the function is called.
     *
     * @param int $start_offset of the current posting list for query term
     *      used in calculating BM25F.
     * @param int &$next_offset where to start in word docs
     * @param int $last_offset offset at which to stop by
     * @param int $len number of documents desired
     * @return array desired list of doc's and their info
     */
    function getPostingsSlice($start_offset, &$next_offset, $last_offset, $len)
    {
        if(!$this->read_only_from_disk && !$this->word_docs_packed) {
            $this->mergeWordPostingsToString();
            $this->packWords(NULL);
            $this->outputPostingLists();
        }

        $num_docs_so_far = 0;
        $results = array();
        /* wd_len is a kludgy fix because word_docs_len can get out of sync
           when things are file-based and am still tracking down why
        */
        $wd_len = (isset($this->file_len )) ? 
            $this->file_len - $this->docids_len : $this->word_docs_len;
        $end = min($wd_len, $last_offset);

        $num_docs_or_links =  
            self::numDocsOrLinks($start_offset, $last_offset);

        do {
            if($next_offset > $end) {break;}
            $old_next_offset = $next_offset;

            $doc_id = 
                $this->makeItem( // this changes next offst
                    $item, $next_offset, $num_docs_or_links);
            $results[$doc_id] = $item;
            $num_docs_so_far += ($next_offset - $old_next_offset)
                / self::POSTING_LEN;
        } while ($next_offset<= $last_offset && $num_docs_so_far < $len
            && $next_offset > $old_next_offset);

        return $results;
    }

    /**
     *  An upper bound on the number of docs or links represented by
     *  the start and ending integer offsets into a posting list.
     *
     *  @param int $start_offset starting location in posting list
     *  @param int $last_offset ending location in posting list
     *  @return int number of docs or links
     */
    static function numDocsOrLinks($start_offset, $last_offset)
    {
        return floor(($last_offset - $start_offset) / self::POSTING_LEN);
    }

    /**
     * Stores in the supplied item document statistics (suumary offset, 
     * relevance, doc rank, and score) for the the document
     * pointed to by $current_offset, based on the the posting lists 
     * num docs with word, and the number of occurrences of the word in the doc.
     * Returns the doc_id of the document
     *
     * @param array &$item a reference to an array to store statistic in
     * @param int $current_offset offset into word_docs for the document to
     *      calculate statistics for
     * @param int $num_doc_or_links number of documents or links doc appears in
     * @param int $occurs number of occurrences of the current word in 
     *   the document
     *
     * @return string $doc_id of document pointed to by $current_offset
     */
    function makeItem(&$item, &$current_offset, $num_doc_or_links,
        $occurs = 0)
    {
        $current = ($current_offset/self::POSTING_LEN );
        $posting_start = $current;
        $posting_end = $current;
        $posting = $this->getPostingAtOffset(
                $current, $posting_start, $posting_end);
        $current_offset = ($posting_end + 1)* self::POSTING_LEN;
        $offset = 0;
        list($doc_index, $item[self::POSITION_LIST]) = 
            $this->unpackPosting($posting, $offset);

        $doc_depth = log(10*(($doc_index +1) + 
            $this->num_docs_per_generation*$this->generation), 10);
        $item[self::DOC_RANK] = number_format(11 - 
            $doc_depth, PRECISION);

        $doc_loc = $doc_index << 4;
        $doc_info_string = $this->getDocInfoSubstring($doc_loc, 
            self::DOC_KEY_LEN); 
        $item[self::SUMMARY_OFFSET] = unpackInt(
            substr($doc_info_string, 0, 4));
        list($doc_len, $num_keys) = 
            $this->unpackDoclenNum(substr($doc_info_string, 4));

        $item[self::GENERATION] = $this->generation;

        $is_doc = (($doc_len & self::LINK_FLAG) == 0) ? true : false;
        if(!$is_doc) {
            $doc_len &= (self::LINK_FLAG - 1);
        }
        $item[self::IS_DOC] = $is_doc;

        $item[self::PROXIMITY] = 
            $this->computeProximity($item[self::POSITION_LIST],$is_doc);
        $occurrences = $this->weightedCount($item[self::POSITION_LIST],$is_doc);

        if($occurs != 0) {
            $occurences = array(
                self::TITLE => 0,
                self::DESCRIPTION => 0,
                self::LINKS => 0);
            if($is_doc) {
                $occurrences[self::DESCRIPTION] = $occurs;
            } else {
                $occurences[self::LINKS] = $occurs;
            }
        }
        /* 
           for archive crawls we store rank as the 4 bits after the high order 
           bit
        */
        $rank_mask = (0x0f) << 19;
        $pre_rank = ($doc_len & $rank_mask);
        if( $pre_rank > 0) {
            $item[self::DOC_RANK] = $pre_rank >> 19;
            $doc_len &= (2 << 19 - 1);
        }

        $skip_stats = false;

        if($item[self::SUMMARY_OFFSET] == self::NEEDS_OFFSET_FLAG) {
            $skip_stats = true;
            $item[self::RELEVANCE] = 1;
            $item[self::SCORE] = $item[self::DOC_RANK];
        } else if($is_doc) {
            $average_doc_len = $this->len_all_docs/$this->num_docs;
            $num_docs = $this->num_docs;
            $type_weight = 1;
        } else {
            $average_doc_len = ($this->num_link_docs != 0) ? 
                $this->len_all_link_docs/$this->num_link_docs : 0;
            $num_docs = $this->num_link_docs;
            $type_weight = floatval(LINK_WEIGHT);
        }
        if(!isset($item['KEY'])) {
            $doc_id = $this->getDocInfoSubstring(
                $doc_loc + self::DOC_KEY_LEN, $num_keys * self::DOC_KEY_LEN);
        } else {
            $doc_id = $item['KEY'];
        }
        if(!$skip_stats) {
            $item[self::RELEVANCE] = 0;
            if($occurrences[self::TITLE] > 0) {
                self::docStats($item, $occurrences[self::TITLE], 
                    AD_HOC_TITLE_LENGTH, 
                    $num_doc_or_links, AD_HOC_TITLE_LENGTH, $num_docs, 
                    $this->num_docs + $this->num_link_docs, 
                    floatval(TITLE_WEIGHT));
            }
            if($occurrences[self::DESCRIPTION] > 0) {
                $average_doc_len = 
                    max($average_doc_len - AD_HOC_TITLE_LENGTH, 1);
                $doc_len = max($doc_len - AD_HOC_TITLE_LENGTH, 1);
                self::docStats($item, $occurrences[self::DESCRIPTION], 
                    $doc_len, $num_doc_or_links, $average_doc_len , $num_docs, 
                    $this->num_docs + $this->num_link_docs, 
                    floatval(DESCRIPTION_WEIGHT));
            }
            if($occurrences[self::LINKS] > 0) {
                self::docStats($item, $occurrences[self::LINKS], 
                    $doc_len, $num_doc_or_links, $average_doc_len , $num_docs,
                    $this->num_docs + $this->num_link_docs, 
                    floatval(LINK_WEIGHT));
            }
            $item[self::SCORE] = $item[self::DOC_RANK]
                * $item[self::RELEVANCE];
        }

        return $doc_id;

    }
    /**
     * Used to sum over the occurences in a position list counting with
     * weight based on term location in the document
     *
     * @param array $position_list positions of term in item
     * @param bool $is_doc whether the item is a document or a link
     * @return array asscoiative array of document_part => weight count 
     *  of occurrences of term in 
     *
     */
    function weightedCount($position_list, $is_doc) {
        $count = array(
            self::TITLE => 0,
            self::DESCRIPTION => 0,
            self::LINKS => 0);
        foreach($position_list as $position) {
            if($is_doc) {
                if($position < AD_HOC_TITLE_LENGTH) {
                    $count[self::TITLE] ++;
                } else {
                    $count[self::DESCRIPTION]++;
                }
            } else {
                $count[self::LINKS]++;
            }
        }
        return $count;
    }

    /**
     * Returns a proximity score for a single term based on its location in
     * doc.
     *
     * @param array $position_list locations of term within item
     * @param bool $is_doc whether the item is a document or not
     * @return int a score for proximity
     */
    function computeProximity($position_list, $is_doc) {
        return (!$is_doc) ? floatval(LINK_WEIGHT): (isset($position_list[0]) && 
            $position_list[0] < AD_HOC_TITLE_LENGTH) ?
            floatval(TITLE_WEIGHT) : floatval(DESCRIPTION_WEIGHT);
    }

    /**
     *  Computes BM25F relevance and a score for the supplied item based
     *  on the supplied parameters.
     *
     *  @param array &$item doc summary to compute a relevance and score for.
     *      Pass-by-ref so self::RELEVANCE and self::SCORE fields can be changed
     *  @param int $occurrences - number of occurences of the term in the item
     *  @param int $doc_len number of words in doc item represents
     *  @param int $num_doc_or_link number of links or docs containing the term
     *  @param float $average_doc_len average length of items in corpus
     *  @param int $num_docs either number of links or number of docs depending
     *      if item represents a link or a doc.
     *  @param int $total_docs_or_links number of docs or links in corpus
     *  @param float BM25F weight for this component (doc or link) of score
     */
    static function docStats(&$item, $occurrences, $doc_len, $num_doc_or_links, 
        $average_doc_len, $num_docs, $total_docs_or_links, $type_weight)
    {

        $doc_ratio = ($average_doc_len > 0) ?
            $doc_len/$average_doc_len : 0;
        $pre_relevance = number_format(
                3 * $occurrences/
                ($occurrences + .5 + 1.5* $doc_ratio), 
                PRECISION);

        $num_term_occurrences = $num_doc_or_links *
            $num_docs/($total_docs_or_links);

        $IDF = log(($num_docs - $num_term_occurrences + 0.5) /
            ($num_term_occurrences + 0.5));

        $item[self::RELEVANCE] += 0.5 * $IDF * $pre_relevance * $type_weight;

    }

    /**
     *  Gets the posting closest to index $current in the word_docs string
     *  modifies the passed-by-ref variables $posting_start and 
     *  $posting_end so they are the index of the the start and end of the
     *  posting
     *
     *  @param int $current an index into the word_docs strings
     *      corresponds to a start search loc of $current * self::POSTING_LEN
     *  @param int &$posting_start after function call will be
     *      index of start of nearest posting to current
     *  @param int &$posting_end after function call will be
     *      index of end of nearest posting to current
     *
     *  @return string the substring of word_docs corresponding to the posting
     */
    function getPostingAtOffset($current, &$posting_start, &$posting_end, 
        $just_start = false)
    {
            $posting = $this->getWordDocsSubstring($current * self::POSTING_LEN,
                self::POSTING_LEN);
            $posting_start = $current;
            $posting_end = $current;
            if($posting == "") return false;
            $end_word_start = 0;
            $chr = (ord($posting[0]) & 192);
            $first_time = ( $chr == 64);
            while ($chr == 128 || $first_time ){
                $first_time = false;
                $posting_start--;
                $posting = $this->getWordDocsSubstring(
                    $posting_start * self::POSTING_LEN, self::POSTING_LEN) . 
                    $posting;
                $chr = (ord($posting[0]) & 192);
                $end_word_start += self::POSTING_LEN;
            }
            if($just_start) {
                return $posting;
            }
            $chr = ord($posting[$end_word_start]) & 192;
            while($chr > 64) {
                $posting_end++;
                $posting .= $this->getWordDocsSubstring(
                    $posting_end*self::POSTING_LEN, self::POSTING_LEN);
                $end_word_start += self::POSTING_LEN;
                $chr = ord($posting[$end_word_start]) & 192;
            }

            return $posting;
    }

    /**
     * Finds the first posting offset between $start_offset and $end_offset
     * of a posting that has a doc_offset bigger than or equal to $doc_offset
     * This is implemented using a galloping search (double offset till
     * get larger than binary search).
     *
     *  @param int $start_offset first posting to consider
     *  @param int $end_offset last posting before give up
     *  @param int $doc_offset document offset we want to be greater than or 
     *      equal to
     *
     *  @return int offset to next posting
     */
     function nextPostingOffsetDocOffset($start_offset, $end_offset,
        $doc_offset) {

        $doc_index = $doc_offset >> 4;
        $current = floor($start_offset/self::POSTING_LEN);
        $end = floor($end_offset/self::POSTING_LEN);
        $low = $current;
        $high = $end;
        $posting_start = $current;
        $posting_end = $current;
        $stride = 32;
        $gallop_phase = true;
        do {
            $offset = 0;
            $posting = $this->getPostingAtOffset(
                $current, $posting_start, $posting_end, true);
            $post_doc_index = $this->getDocIndexPosting($posting);
            if($doc_index > $post_doc_index) {
                $low = $current;
                if($gallop_phase) {
                    $current += $stride;
                    $stride <<= 1;
                    if($current > $end ) {
                        $current = $end;
                        $gallop_phase = false;
                    }
                } else if($current >= $end) {
                    return false;
                } else {
                    if($current + 1 == $high) {
                        $current++;
                        $low = $current;
                    }
                    $current = (($low + $high) >> 1);
                }
            } else if($doc_index < $post_doc_index) {
                if($low == $current) {
                    return $posting_start * self::POSTING_LEN;
                } else if($gallop_phase) {
                    $gallop_phase = false;
                }
                $high = $current;
                $current = (($low + $high) >> 1);
            } else  {
                return $posting_start * self::POSTING_LEN;
            }

        } while($current <= $end);

        return false;
     }

    /**
     * Given an offset of a posting into the word_docs string, looks up
     * the posting there and computes the doc_offset stored in it.
     *
     *  @param int $offset byte/char offset into the word_docs string
     *  @return int a document byte/char offset into the doc_infos string
     */
    function docOffsetFromPostingOffset($offset) {
        $current = $offset / self::POSTING_LEN;
        $posting = $this->getPostingAtOffset(
            $current, $posting_start, $posting_end, true);
        $doc_index = $this->getDocIndexPosting($posting);

        return ($doc_index << 4);
    }

    /**
     * Returns $len many documents which contained the word corresponding to
     * $word_id (only wordk for loaded shards)
     *
     * @param string $word_id key to look up documents for
     * @param int number of documents desired back (from start of word linked
     *      list).
     * @return array desired list of doc's and their info
     */
    function getPostingsSliceById($word_id, $len)
    {
        $results = array();
        $info = $this->getWordInfo($word_id, true);
        if($info !== false) {
            list($first_offset, $last_offset,
                $num_docs_or_links) = $info;
            $results = $this->getPostingsSlice($first_offset, 
                $first_offset, $last_offset, $len);
        }
        return $results;
    }

    /**
     * Adds the contents of the supplied $index_shard to the current index
     * shard
     *
     * @param object $index_shard the shard to append to the current shard
     */
    function appendIndexShard($index_shard)
    {
        if($this->word_docs_packed == true) {
            $this->words = array();
            $this->word_docs = "";
            $this->word_docs_packed = false;
        }
        if($index_shard->word_docs_packed == true) {
            $index_shard->unpackWordDocs();
        }

        $this->doc_infos .= $index_shard->doc_infos;

        $two_doc_len = 2 * self::DOC_KEY_LEN;
        foreach($index_shard->words as $word_id => $postings) {
            $postings_len = strlen($postings);
            // update doc offsets for newly added docs
            $add_len_flag = false;
            if($postings_len !=  $two_doc_len || 
                substr($postings, 0, self::POSTING_LEN) != self::HALF_BLANK) {
                $offset = 0;
                $new_postings = "";
                $index_shard_len = ($this->docids_len >> 4);
                while($offset < $postings_len) {
                    list($doc_index, $posting_list) = // this changes $offset
                        $this->unpackPosting($postings, $offset, false);
                    $doc_index += $index_shard_len;
                    $new_postings .=
                        $this->packPosting($doc_index, $posting_list, false);
                }
                $add_len_flag = true;
            } else {
                $new_postings = $postings;
            }
            $new_postings_len = strlen($new_postings);
            if(!isset($this->words[$word_id])) {
                $this->words[$word_id] = $new_postings;
            } else  {
                $this->words[$word_id] .= $new_postings;
            }
            if($add_len_flag) {
                $this->word_docs_len += $new_postings_len;
            }
        }
        $this->docids_len += $index_shard->docids_len;
        $this->num_docs += $index_shard->num_docs;
        $this->num_link_docs += $index_shard->num_link_docs;
        $this->len_all_docs += $index_shard->len_all_docs;
        $this->len_all_link_docs += $index_shard->len_all_link_docs;
        crawlLog("Finishing append...mem:".memory_get_usage());
        if($this->num_docs - $this->last_flattened_words_count >
            self::FLATTEN_FREQUENCY) {
            $this->mergeWordPostingsToString();
            crawlLog("...Flattened Word Postings mem:".memory_get_usage());
        }
    }

    /**
     * Used to flatten the words associative array to a more memory 
     * efficient word_postings string.
     */
    function mergeWordPostingsToString()
    {
        if($this->word_docs_packed) {
            return;
        }
        ksort($this->words, SORT_STRING);
        $tmp_string = "";
        $offset = 0;
        $write_offset = 0;
        $len = strlen($this->word_postings);
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $item_len = $key_len + $posting_len;
        foreach($this->words as $word_id => $postings) {
            $cmp = -1;
            while($cmp < 0 && $offset + $item_len <= $len) {
                $key = substr($this->word_postings, $offset, $key_len);
                $key_posts_len = unpackInt(substr(
                    $this->word_postings, $offset + $key_len, $posting_len));
                $key_postings = substr($this->word_postings, 
                    $offset + $item_len, $key_posts_len);
                $word_id_posts_len = strlen($postings);
                $cmp = strcmp($key, $word_id);
                if($cmp == 0) {
                    $tmp_string .= $key . 
                        packInt($key_posts_len + $word_id_posts_len) .
                        $key_postings . $postings;
                    $offset += $item_len + $key_posts_len;
                } else if ($cmp < 0) {
                    $tmp_string .= $key .packInt($key_posts_len). $key_postings;
                    $offset += $item_len + $key_posts_len;
                } else {
                    $tmp_string .= $word_id . 
                        packInt($word_id_posts_len). $postings;
                }
                $tmp_len = strlen($tmp_string);
                $copy_data_len = min(self::WORD_POSTING_COPY_LEN, $tmp_len);
                $copy_to_len = min($offset - $write_offset, 
                    $len - $write_offset);
                if($copy_to_len > $copy_data_len) {
                    charCopy($tmp_string, $this->word_postings, $write_offset,
                        $copy_data_len);
                    $write_offset += $copy_data_len;
                    $tmp_string = substr($tmp_string, $copy_data_len);
                }
           }
           if($offset + $item_len > $len) {
                $word_id_posts_len = strlen($postings);
                if($write_offset < $len) {
                    $tmp_len = strlen($tmp_string);
                    $copy_data_len = $len - $write_offset;
                    if($tmp_len < $copy_data_len) { // this case shouldn't occur
                        $this->word_postings = 
                            substr($this->word_postings, 0, $write_offset);
                        $this->word_postings .= $tmp_string;
                    } else {
                        charCopy($tmp_string, $this->word_postings, 
                            $write_offset, $copy_data_len);
                        $this->word_postings .=
                             substr($tmp_string, $copy_data_len);
                        $tmp_string = "";
                    }
                    $tmp_string = "";
                    $write_offset = $len;
                }
                $this->word_postings .= 
                    $word_id . packInt($word_id_posts_len). $postings;
            }
        }
        if($tmp_string != "") {
            $tmp_len = strlen($tmp_string);
            $copy_data_len = $offset - $write_offset;
            $pad_len = $tmp_len - $copy_data_len;
            $pad = str_pad("", $pad_len, "@");
            $this->word_postings .= $pad;
            for($j = $len + $pad_len - 1, 
                $k = $len - 1; $k >= $offset; $j--, $k--) {
                $this->word_postings[$j] = "" . $this->word_postings[$k];
                    /*way slower if directly
                    assign!!! PHP is crazy*/
            }
            charCopy($tmp_string, $this->word_postings, 
                $write_offset, $tmp_len);
        }

        $this->words = array();
        $this->last_flattened_words_count = $this->num_docs;
    }

    /**
     * Changes the summary offsets associated with a set of doc_ids to new 
     * values. This is needed because the fetcher puts documents in a 
     * shard before sending them to a queue_server. It is on the queue_server
     * however where documents are stored in the IndexArchiveBundle and
     * summary offsets are obtained. Thus, the shard needs to be updated at
     * that point. This function should be called when shard unpacked 
     * (we check and unpack to be on the safe side).
     *
     * @param array $docid_offsets a set of doc_id  associated with a
     *      new_doc_offset.
     */
    function changeDocumentOffsets($docid_offsets)
    {
        if($this->word_docs_packed == true) {
            $this->words = array();
            $this->word_docs = "";
            $this->word_docs_packed = false;
        }
        $docids_len = $this->docids_len;

        for($i = 0 ; $i < $docids_len; $i += $row_len) {
            $doc_info_string = $this->getDocInfoSubstring($i, 
                self::DOC_KEY_LEN);
            $offset = unpackInt(
                substr($doc_info_string, 0, self::POSTING_LEN));
            $doc_len_info = substr($doc_info_string, 
                    self::POSTING_LEN, self::POSTING_LEN);
            list($doc_len, $num_keys) = 
                $this->unpackDoclenNum($doc_len_info);
            $key_count = ($num_keys % 2 == 0) ? $num_keys + 2: $num_keys + 1;
            $row_len = self::DOC_KEY_LEN * ($key_count);

            $id = substr($this->doc_infos, $i + self::DOC_KEY_LEN, 
                $num_keys * self::DOC_KEY_LEN);

            $new_offset = (isset($docid_offsets[$id])) ? 
                packInt($docid_offsets[$id]) : 
                packInt($offset);

            charCopy($new_offset, $this->doc_infos, $i, self::POSTING_LEN);

        }
    }


    /**
     *  Save the IndexShard to its filename
     * 
     *  @param bool $to_string whether output should be written to a string
     *      rather than the default file location
     *  @param bool $with_logging whether log messages should be written
     *      as the shard save progresses
     *  @return string serialized shard if output was to string else empty 
     *      string
     */
    public function save($to_string = false, $with_logging = false)
    {
        $out = "";
        $this->mergeWordPostingsToString();
        if($with_logging) {
            crawlLog("Saving index shard .. done merge postings to string");
        }
        $this->prepareWordsAndPrefixes();
        if($with_logging) {
            crawlLog("Saving index shard .. make prefixes");
        }
        $header =  pack("N", $this->prefixes_len) .
            pack("N", $this->words_len) .
            pack("N", $this->word_docs_len) .
            pack("N", $this->docids_len) . 
            pack("N", $this->generation) .
            pack("N", $this->num_docs_per_generation) .
            pack("N", $this->num_docs) .
            pack("N", $this->num_link_docs) .
            pack("N", $this->len_all_docs) .
            pack("N", $this->len_all_link_docs);
        if($with_logging) {
            crawlLog("Saving index shard .. packed header");
        }
        if($to_string) {
            $out = $header;
            $this->packWords(NULL);
            $out .= $this->words;
            $this->outputPostingLists(NULL);
            $out .= $this->word_docs;
            $out .= $this->doc_infos;
        } else {
            $fh = fopen($this->filename, "wb");
            fwrite($fh, $header);
            fwrite($fh, $this->prefixes);
            $this->packWords($fh);
            if($with_logging) {
                crawlLog("Saving index shard .. wrote dictionary");
            }
            $this->outputPostingLists($fh);
            fwrite($fh, $this->doc_infos);
            fclose($fh);
        }
        if($with_logging) {
            crawlLog("Saving index shard .. done");
        }
        // clean up by returning to state where could add more docs
        $this->words = array();
        $this->word_docs = "";
        $this->prefixes = "";
        $this->word_docs_packed = false;
        return $out;
    }

    /**
     * Computes the prefix string index for the current words array.
     * This index gives offsets of the first occurrences of the lead two char's
     * of a word_id in the words array. This method assumes that the word
     * data is already in >word_postings
     */
    function prepareWordsAndPrefixes()
    {
        $word_item_len = IndexShard::WORD_ITEM_LEN;
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $this->words_len = 0;
        $word_postings_len = strlen($this->word_postings);
        $pos = 0;
        $tmp = array();
        $offset = 0;
        $num_words = 0;
        $old_prefix = false;
        while($pos < $word_postings_len) {
            $this->words_len += $word_item_len;
            $first = substr($this->word_postings, $pos, $key_len);
            $post_len = unpackInt(substr($this->word_postings, 
                $pos + $key_len, $posting_len));
            $pos += $key_len + $posting_len + $post_len;
            $prefix = (ord($first[0]) << 8) + ord($first[1]);
            if($old_prefix === $prefix) {
                $num_words++;
            } else {
                if($old_prefix !== false) {
                    $tmp[$old_prefix] = packInt($offset) .
                        pack("N", $num_words);
                    $offset += $num_words * $word_item_len;
                }
                $old_prefix = $prefix;
                $num_words = 1;
            }
        }

        $tmp[$old_prefix] = packInt($offset) . packInt($num_words);
        $num_prefixes = 2 << 16;
        $this->prefixes = "";
        for($i = 0; $i < $num_prefixes; $i++) {
            if(isset($tmp[$i])) {
                $this->prefixes .= $tmp[$i];
            } else {
                $this->prefixes .= self::BLANK;
            }
        }
        $this->prefixes_len = strlen($this->prefixes);
    }

    /**
     * Posting lists are initially stored associated with a word as a key
     * value pair. The merge operation then merges them these to a string
     * help by word_postings. packWords separates words from postings.
     * After being applied words is a string consisting of 
     * triples (as concatenated strings) word_id, start_offset, end_offset.
     * The offsets refer to integers offsets into a string $this->word_docs
     * Finally, if a file handle is given its write the word dictionary out 
     * to the file as a long string. This function assumes 
     * mergeWordPostingsToString has just been called.
     *
     * @param resource $fh a file handle to write the dictionary to, if desired
     * @param bool $to_string whether to return a string containing the packed 
     *      data

     */
    function packWords($fh = NULL)
    {
        if($this->word_docs_packed) {
            return;
        }
        $word_item_len = IndexShard::WORD_ITEM_LEN;
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $this->word_docs_len = 0;
        $this->words = "";
        $total_out = "";
        $word_postings_len = strlen($this->word_postings);
        $pos = 0;
        $two_doc_len = 2 * self::DOC_KEY_LEN;
        while($pos < $word_postings_len) {
            $word_id = substr($this->word_postings, $pos, $key_len);
            $len = unpackInt(substr($this->word_postings, 
                $pos + $key_len, $posting_len));
            $postings = substr($this->word_postings, 
                $pos + $key_len + $posting_len, $len);
            $pos += $key_len + $posting_len + $len;
            /* 
                we pack generation info to make it easier to build the global
                dictionary
            */
            if($len != $two_doc_len || 
                substr($postings, 0, self::POSTING_LEN) != self::HALF_BLANK) {
                $out = packInt($this->generation)
                    . packInt($this->word_docs_len)
                    . packInt($len);
                $this->word_docs_len += $len;
                $this->words .= $word_id . $out;
            } else {
                $out = substr($postings, 
                    self::POSTING_LEN, self::WORD_ITEM_LEN);
                $out[0] = chr((0x80 | ord($out[0])));
                $this->words .= $word_id . $out;
            }
        }
        if($fh != null) {
            fwrite($fh, $this->words);
        }
        $this->words_len = strlen($this->words);
        $this->word_docs_packed = true;
    }

    /**
     * Used to convert the word_postings string into a word_docs string
     * or if a file handle is provided write out the word_docs sequence
     * of postings to the provided file handle.
     *
     * @param resource $fh a filehandle to write to
     */
    function outputPostingLists($fh = NULL)
    {
        $word_item_len = IndexShard::WORD_ITEM_LEN;
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $this->word_docs = "";
        $total_out = "";
        $word_postings_len = strlen($this->word_postings);
        $pos = 0;
        $tmp_string = "";
        $tmp_len = 0;
        $two_doc_len = 2 * self::DOC_KEY_LEN;
        while($pos < $word_postings_len) {
            $word_id = substr($this->word_postings, $pos, $key_len);
            $len = unpackInt(substr($this->word_postings, 
                $pos + $key_len, $posting_len));
            $postings = substr($this->word_postings, 
                $pos + $key_len + $posting_len, $len);
            $pos += $key_len + $posting_len + $len;

            if($len != $two_doc_len || 
                substr($postings, 0, self::POSTING_LEN) != self::HALF_BLANK) {
                if($fh != NULL) {
                    if($tmp_len < self::SHARD_BLOCK_SIZE) {
                        $tmp_string .= $postings;
                        $tmp_len += $len;
                    } else {
                        fwrite($fh, $tmp_string);
                        $tmp_string = $postings;
                        $tmp_len = $len;
                    }
                } else {
                    $this->word_docs .= $postings;
                }
           }
        }
        if($tmp_len > 0) {
            if($fh == NULL ) {
                $this->word_docs .= $tmp_string;
            } else {
                fwrite($fh, $tmp_string);
            }
        }
    }

    /**
     * Takes the word docs string and splits it into posting lists which are
     * assigned to particular words in the words dictionary array.
     * This method is memory expensive as it briefly has essentially 
     * two copies of what's in word_docs.
     */
    function unpackWordDocs()
    {
        if(!$this->word_docs_packed) {
            return;
        }
        foreach($this->words as $word_id => $postings_info) {
            /* we are ignoring the first four bytes which contains 
               generation info
             */
            if((ord($postings_info[0]) & 0x80) > 0 ) {
                $postings_info[0] = chr(ord($postings_info[0]) - 0x80);
                $postings_info = self::HALF_BLANK . $postings_info;
                $this->words[$word_id] = $postings_info;
            } else {
                $offset = unpackInt(substr($postings_info, 4, 4));
                $len = unpackInt(substr($postings_info, 8, 4));
                $postings = substr($this->word_docs, $offset, $len);
                $this->words[$word_id] = $postings;
            }
        }
        unset($this->word_docs);
        $this->word_docs_packed = false;
    }


    /**
     * Used to store the length of a document as well as the number of
     * key components in its doc_id as a packed int (4 byte string)
     *
     * @param int $doc_len number of words in the document
     * @param int $num_keys number of keys that are used to make up its doc_id
     * @return string packed int string representing these two values
     */
    static function packDoclenNum($doc_len, $num_keys)
    {
        return packInt(($doc_len << 8) + $num_keys);
    }

    /**
     * Used to extract from a 4 byte string representing a packed int,
     * a pair which represents the length of a document together with the
     * number of keys in its doc_id
     *
     * @param string $doc_len_string string to unpack
     * @return array pair (number of words in the document,
     *      number of keys that are used to make up its doc_id)
     */
    static function unpackDoclenNum($doc_len_string)
    {
        $doc_int = unpackInt($doc_len_string);
        $num_keys = $doc_int & 255;
        $doc_len = ($doc_int >> 8);
        return array($doc_len, $num_keys);
    }

    /**
     * Makes an packed integer string from a docindex and the number of
     * occurrences of a word in the document with that docindex.
     *
     * @param int $doc_index index (i.e., a count of which document it
     *      is rather than a byte offset) of a document in the document string
     * @param array integer positions word occurred in that doc
     * @param bool $delta if true then stores the position_list as a sequence of
     *      differences (a delta list)
     * @return string a modified9 (our compression scheme) packed 
     *      string containing this info.
     */
    static function packPosting($doc_index, $position_list, $delta = true)
    {
        if($delta) {
            $delta_list = deltaList($position_list);
        } else {
            $delta_list = $position_list;
        }
        if(isset($delta_list[0])){
            $delta_list[0]++;
        }

        if( $doc_index >= (2 << 14) && isset($delta_list[0]) 
            && $delta_list[0] < (2 << 9)  && $doc_index < (2 << 17)) {
            $delta_list[0] += (((2 << 17) + $doc_index) << 9);
        } else {
            // we add 1 to doc_index to make sure not 0 (modified9 needs > 0)
            array_unshift($delta_list, ($doc_index + 1));
        }
        $encoded_list = encodeModified9($delta_list);
        return $encoded_list;
    }

    /**
     * Given a packed integer string, uses the top three bytes to calculate
     * a doc_index of a document in the shard, and uses the low order byte
     * to computer a number of occurences of a word in that document.
     *
     * @param string $posting a string containing 
     *      a doc index position list pair coded encoded using modified9
     * @param int &offset a offset into the string where the modified9 posting
     *      is encoded
     * @param bool $dedelta if true then assumes the list is a sequence of 
     *      differences (a delta list) and undoes the difference to get 
     *      the original sequence
     * @return array consisting of integer doc_index and a subarray consisting
     *      of integer positions of word in doc.
     */
    static function unpackPosting($posting, &$offset, $dedelta = true)
    {
        $delta_list = decodeModified9($posting, $offset);
        $doc_index = array_shift($delta_list);

        if(($doc_index & (2 << 26)) > 0) {
            $delta0 = ($doc_index & ((2 << 9) - 1));
            array_unshift($delta_list, $delta0);
            $doc_index -= $delta0;
            $doc_index -= (2 << 26);
            $doc_index >>= 9;
        } else {
            $doc_index--;
        }
        if(isset($delta_list[0])){
            $delta_list[0]--;
        }

        if($dedelta) {
            $position_list = deDeltaList($delta_list);
        } else {
            $position_list = $delta_list;
        }

        return array($doc_index, $position_list);
    }

    static function getDocIndexPosting($posting)
    {
        $delta_list = unpackListModified9(substr($posting, 0, 4));
        $doc_index = array_shift($delta_list);

        if(($doc_index & (2 << 26)) > 0) {
            $delta0 = ($doc_index & ((2 << 9) - 1));
            array_unshift($delta_list, $delta0);
            $doc_index -= $delta0;
            $doc_index -= (2 << 26);
            $doc_index >>= 9;
        } else {
            $doc_index--;
        }
        return $doc_index;
    }

    /**
     * Converts $str into 3 ints for a first offset into word_docs,
     * a last offset into word_docs, and a count of number of docs
     * with that word.
     *
     * @param string $str 
     * @param bool $include_generation 
     * @return array of these three or four int's
     */
    static function getWordInfoFromString($str, $include_generation = false)
    {
        $generation = unpackInt(substr($str, 0, 4));
        $first_offset = unpackInt(substr($str, 4, 4));
        $len = unpackInt(substr($str, 8, 4));
        $last_offset = $first_offset + $len - self::POSTING_LEN;
        $count = floor($len / self::POSTING_LEN);
        if( $include_generation) {
            return array($generation, $first_offset, $last_offset, $count);
        }
        return array($first_offset, $last_offset, $count);
    }

    /**
     * From disk gets $len many bytes starting from $offset in the word_docs
     * strings 
     *
     * @param $offset byte offset to begin getting data out of disk-based
     *      word_docs
     * @param $len number of bytes to get
     * @return desired string
     */
    function getWordDocsSubstring($offset, $len)
    {
        if($this->read_only_from_disk) {
            $base_offset = self::HEADER_LENGTH + 
                $this->prefixes_len + $this->words_len;
            return $this->getShardSubstring($base_offset + $offset, $len);
        }
        return substr($this->word_docs, $offset, $len);
    }

    /**
     * From disk gets $len many bytes starting from $offset in the doc_infos
     * strings 
     *
     * @param $offset byte offset to begin getting data out of disk-based
     *      doc_infos
     * @param $len number of bytes to get
     * @return desired string
     */
    function getDocInfoSubstring($offset, $len)
    {
        if($this->read_only_from_disk) {
            $base_offset = $this->file_len - $this->docids_len;
            return $this->getShardSubstring($base_offset + $offset, $len);
        }
        return substr($this->doc_infos, $offset, $len);
    }

    /**
     *  Gets from Disk Data $len many bytes beginning at $offset from the
     *  current IndexShard
     *
     * @param int $offset byte offset to start reading from
     * @param int $len number of bytes to read
     * @return string data fromthat location  in the shard
     */
    function getShardSubstring($offset, $len)
    {
        $block_offset = (floor($offset/self::SHARD_BLOCK_SIZE) *
            self::SHARD_BLOCK_SIZE);
        $start_loc = $offset - $block_offset;
        $substring = "";
        do {
            $data = $this->readBlockShardAtOffset($block_offset);
            if($data === false) {return $substring;}
            $block_offset += self::SHARD_BLOCK_SIZE;
            $substring .= substr($data, $start_loc);
            $start_loc = 0;
        } while (strlen($substring) < $len);
        return substr($substring, 0, $len);
    }

    /**
     * Reads SHARD_BLOCK_SIZE from the current IndexShard's file beginning
     * at byte offset $bytes
     *
     * @param int $bytes byte offset to start reading from
     * @return &string data fromIndexShard file
     */
    function &readBlockShardAtOffset($bytes)
    {
        $false = false;
        if(isset($this->blocks[$bytes])) {
            return $this->blocks[$bytes];
        } 
        if($this->fh === NULL) {
            $this->fh = fopen($this->filename, "rb");
            if($this->fh === false) return false;
            $this->file_len = filesize($this->filename);
        }
        if($bytes >= $this->file_len) {
            return $false;
        }
        $seek = fseek($this->fh, $bytes, SEEK_SET);
        if($seek < 0) {
            return $false;
        }
        $this->blocks[$bytes] = fread($this->fh, self::SHARD_BLOCK_SIZE);

        return $this->blocks[$bytes];
    }

    /**
     * If not already loaded, reads in from disk the fixed-length'd field 
     * variables of this IndexShard ($this->words_len, etc)
     */
    function getShardHeader()
    {
        if(isset($this->num_docs) && $this->num_docs > 0) {
            return; // if $this->num_docs > 0 assume have read in
        }
        $info_block = & $this->readBlockShardAtOffset(0);
        $header = substr($info_block, 0, self::HEADER_LENGTH);
        self::headerToShardFields($header, $this);
    }



    /**
     *  Load an IndexShard from a file or string
     *
     *  @param string $fname the name of the file to the IndexShard from/to
     *  @param string &$data stringified shard data to load shard from. If NULL
     *      then the data is loaded from the $fname if possible
     *  @return object the IndexShard loaded
     */
    static function load($fname, &$data = NULL)
    {
        $shard = new IndexShard($fname);
        if($data === NULL) {
            $fh = fopen($fname, "rb");
            $shard->file_len = filesize($fname);
            $header = fread($fh, self::HEADER_LENGTH);
        } else {
            $shard->file_len = strlen($data);
            $header = substr($data, 0, self::HEADER_LENGTH);
            $pos = self::HEADER_LENGTH;
        }
        self::headerToShardFields($header, $shard);

        if($data === NULL) {
            fread($fh, $shard->prefixes_len );
            $words = fread($fh, $shard->words_len);
            $shard->word_docs = fread($fh, $shard->word_docs_len);
            $shard->doc_infos = fread($fh, $shard->docids_len);
            fclose($fh);
        } else {
            $words = substr($data, $pos, $shard->words_len);
            $pos += $shard->words_len;
            $shard->word_docs = substr($data, $pos, $shard->word_docs_len);
            $pos += $shard->word_docs_len;
            $shard->doc_infos = substr($data, $pos, $shard->docids_len);
        }

        $pre_words_array = str_split($words, self::WORD_ITEM_LEN);
        unset($words);
        array_walk($pre_words_array, 'IndexShard::makeWords', $shard);
        $shard->word_docs_packed = true;
        $shard->unpackWordDocs();
        return $shard;
    }


    /**
     *  Split a header string into a shards field variable
     *
     *  @param string $header a string with packed shard header data
     *  @param object shard IndexShard to put data into
     */
    static function headerToShardFields($header, $shard)
    {
        $header_array = str_split($header, 4);
        $header_data = array_map('unpackInt', $header_array);
        $shard->prefixes_len = $header_data[0];
        $shard->words_len = $header_data[1];
        $shard->word_docs_len = $header_data[2];
        $shard->docids_len = $header_data[3];
        $shard->generation = $header_data[4];
        $shard->num_docs_per_generation = $header_data[5];
        $shard->num_docs = $header_data[6];
        $shard->num_link_docs = $header_data[7];
        $shard->len_all_docs = $header_data[8];
        $shard->len_all_link_docs = $header_data[9];
    }

    /**
     * Callback function for load method. splits a word_key . word_info string
     * into an entry in the passed shard $shard->words[word_key] = $word_info.
     *
     * @param string &value  the word_key . word_info string
     * @param int $key index in array - we don't use
     * @param object $shard IndexShard to add the entry to word table for
     */
    static function makeWords(&$value, $key, $shard)
    {
        $shard->words[substr($value, 0, self::WORD_KEY_LEN)] = 
            substr($value, self::WORD_KEY_LEN, 
                self::WORD_ITEM_LEN - self::WORD_KEY_LEN);
    }

}
