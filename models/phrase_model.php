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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * logging is done during crawl not through web,
 * so it will not be used in the phrase model
 */
if(!defined("POST_PROCESSING")) {
    define("LOG_TO_FILES", false);
}
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/** For extractPhrasesAndCount function */
require_once BASE_DIR."/lib/phrase_parser.php";


/**
 * Used to look up words and phrases in the inverted index
 * associated with a given crawl
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");

/**
 * Load iterators to get docs out of index archive
 */
foreach(glob(BASE_DIR."/lib/index_bundle_iterators/*_iterator.php")
    as $filename) {
    require_once $filename;
}

/**
 *
 * This is class is used to handle
 * results for a given phrase search
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class PhraseModel extends Model
{

    /** used to hold the name of index archive to look summaries up in
     *  @var string
     */
    var $index_name;

    /** an associative array of additional meta words and
     * the max description length of results if such a meta word is used
     * this array is typically set in index.php
     *
     *  @var array
     */
    var $additional_meta_words;

    /**
     * Used to hold query statistics about the current query
     * @var array
     */
    var $query_info;

    /**
     * A list of meta words that might be extracted from a query
     * @var array
     */
    var $meta_words_list = array('link:', 'site:', 'version:', 'modified:',
            'filetype:', 'info:', '\-', 'os:', 'server:', 'date:', "numlinks:",
            'index:', 'i:', 'ip:', 'weight:', 'w:', 'u:', 'time:', 'code:',
            'lang:', 'media:', 'elink:', 'location:', 'size:', 'host:', 'dns:',
            'path:', 'robot:', 'safe:');

    /**
     * Number of pages to cache in one go in memcache or filecache
     * Size chosen based on 1MB max object size for memcache or filecache
     */
     const NUM_CACHE_PAGES = 10;
    /**
     * {@inheritdoc}
     */
    function __construct($db_name = DB_NAME)
    {
        parent::__construct($db_name);
    }

    /**
     * Returns whether there is a index with the provide timestamp
     *
     * @param int $index_timestamp timestamp of the index to check if in cache
     * @return bool whether it exists or not
     */
    function indexExists($index_time_stamp)
    {
        return file_exists(CRAWL_DIR.'/cache/IndexData'.$index_time_stamp);
    }

    /**
     * Rewrites a mix query so that it maps directly to a query about crawls
     *
     * @param string $query the original before a rewrite
     * @param object $mix a mix object saying how the mix is built out of crawls
     *
     * @return string a rewritten query in terms of crawls
     */
    function rewriteMixQuery($query, $mix)
    {
        $disjunct_phrases = explode("|", $query);
        $rewrite = "";
        if(isset($mix['GROUPS'])) {
            foreach($mix['GROUPS'] as $group) {
                $pipe = "";
                foreach($disjunct_phrases as $disjunct) {
                    $rewrite .= $pipe;
                    $pipe = ' | ';
                    $disjunct_string = $disjunct;
                    $base_weight = 1;
                    $pattern = "/(\s)(index:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if(isset($matches[2][0])) {
                        $rewrite .= $disjunct;
                        continue;
                    }
                    $pattern = "/(\s)(i:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if(isset($matches[2][0])) {
                        $rewrite .= $disjunct;
                        continue;
                    }
                    $pattern = "/(\s)(weight:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if(isset($matches[2][0])) {
                        $base_weight = substr($matches[2][0],strlen("weight:"));
                        $disjunct_string =
                            preg_replace($pattern,"", $disjunct_string);
                    }
                    $pattern = "/(\s)(w:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if(isset($matches[2][0])) {
                        $base_weight = substr($matches[2][0],strlen("w:"));
                        $disjunct_string =
                            preg_replace($pattern,"", $disjunct_string);
                    }
                    $pipe2 = "";
                    if(isset($group['COMPONENTS'])) {
                        $start_disjunct_string = $disjunct_string;
                        foreach($group['COMPONENTS'] as $component) {
                            $disjunct_string = $start_disjunct_string;
                            if(isset($component['KEYWORDS'])) {
                                $disjunct_string .= " ".$component['KEYWORDS'];
                            }
                            $rewrite .= $pipe2.$disjunct_string." w:".
                                ($component['WEIGHT']*$base_weight)." i:".
                                $component['CRAWL_TIMESTAMP'];
                            $pipe2 = ' | ';
                        }
                    }

                }
                $num_results = (isset($group['RESULT_BOUND']) &&
                    $group['RESULT_BOUND'] > 1) ?
                    $group['RESULT_BOUND'] : 1;
                $rewrite .= " #$num_results# ";
            }
        }
        return $rewrite;
    }

    /**
     * Given a query phrase, returns formatted document summaries of the
     * documents that match the phrase.
     *
     * @param string $phrase  the phrase to try to match
     * @param int $low  return results beginning with the $low document
     * @param int $results_per_page  how many results to return
     * @param bool $format  whether to highlight in the returned summaries the
     *      matched text
     * @param array $filter an array of hashes of domains to filter from
     *      results
     * @param bool $use_cache_if_allowed if true and USE_CACHE is true then
     *      an attempt will be made to look up the results in either
     *      the file cache or memcache. Otherwise, items will be recomputed
     *      and then potentially restored in cache
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *      no grouping but page look-up for links, ($raw == 2)
     *      no grouping done on data'
     * @param array $queue_servers a list of urls of yioop machines which might
     *      be used during lookup
     * @return array an array of summary data
     */
    function getPhrasePageResults(
        $input_phrase, $low = 0, $results_per_page = NUM_RESULTS_PER_PAGE,
        $format = true, $filter = NULL, $use_cache_if_allowed = true,
        $raw = 0, $queue_servers = array())
    {
        if(QUERY_STATISTICS) {
            $indent= "&nbsp;&nbsp;";
            $in2 = $indent . $indent;
            $in3 = $in2 . $indent;
            $prs_cnt = 0;
            $dis_cnt = 0;
            $this->query_info = array();
            $this->query_info['QUERY'] =
                "<b>PHRASE QUERY</b>: ".$input_phrase."<br />";
            $start_time = microtime();
        }
        $results = NULL;
        $word_structs = array();

        /*
            this is a quick and dirty parsing and will usually work,
            exceptions would be # or | in quotes or if someone tried
            to escape |.

            First we split into presentation elements then we split by
            disjuncts
        */
        $presentation_parts = preg_split('/#(\d)+#/',
            $input_phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
        $count = 0;

        $presentation_parts = array_chunk($presentation_parts, 2);

        $num_parts = count($presentation_parts);

        $query_parts = array();
        $last_part = NULL;
        for($i = 0;  $i < $num_parts ; $i++) {
           if(isset($presentation_parts[$i][0])  &&
                ($trimmed = trim($presentation_parts[$i][0])) != "" ) {
                $to_return = (isset($presentation_parts[$i][1])) ?
                    $presentation_parts[$i][1]: 1;
                $query_parts[$trimmed][] =
                    array($count, $to_return);
                $last_part = $trimmed;
                if(isset($presentation_parts[$i][1])) {
                    $count += $presentation_parts[$i][1];
                } else {
                    $count ++;
                }
           }
        }

        $results_high = $low + $results_per_page;
        $num_phrases = count($query_parts);
        if($num_phrases > 0 ) {
            $num_last_parts = count($query_parts[$last_part]);
            if($query_parts[$last_part][$num_last_parts - 1][0] +
                $query_parts[$last_part][$num_last_parts - 1][1] < $low) {
                $query_parts[$last_part][$num_last_parts - 1][1] = 
                    $results_high;
            }
        }

        foreach($query_parts as $phrase => $pre_result_bounds) {

            $phrase_high = $pre_result_bounds[0][1];
            $result_bounds = array();
            $start_flag = false;
            $num_bounds = 0;

            foreach($pre_result_bounds as $bound) {
                if($bound[0] > $results_high) break;
                //rest of presentation after what we'll return so break
                $phrase_high =  $bound[0] + $bound[1];

                if($phrase_high < $low) continue;
                // this part of presentation is before what we'll return so skip
                $result_bounds[] = $bound;
                $num_bounds++;
            }
            if($num_bounds == 0) continue;
            if($phrase == $last_part &&
                $result_bounds[$num_bounds - 1][0] +
                $result_bounds[$num_bounds - 1][1] < $results_high) {
                $result_bounds[$num_bounds - 1][1] = $results_high -
                    $result_bounds[$num_bounds - 1][0];
            }

            $phrase_num = max(min($phrase_high, $results_high), $results_high) -
                $low;
            $disjunct_phrases = explode("|", $phrase);
            $word_structs = array();
            if(QUERY_STATISTICS) {
                $this->query_info['QUERY'] .= $indent .
                    "<b>Presentation $prs_cnt:</b><br />";
                $this->query_info['QUERY'] .= "$in2<i>Low</i>:".
                    $result_bounds[0][0]."<br />";
                $this->query_info['QUERY'] .= $in2 .
                    "<i>High</i>: ".$result_bounds[0][1]."<br />";
                $prs_cnt++;
            }

            foreach($disjunct_phrases as $disjunct) {
                if(QUERY_STATISTICS) {

                    $this->query_info['QUERY'] .= "$in2<b>Disjunct $dis_cnt:"
                        . "</b><br />";
                    $dis_cnt++;
                }
                list($word_struct, $format_words) =
                    $this->parseWordStructConjunctiveQuery($disjunct,
                        $queue_servers);
                if($word_struct != NULL) {
                    $word_structs[] = $word_struct;
                }
            }
            if(QUERY_STATISTICS) {
                $this->query_info['QUERY'] .=
                    "$in2<b>Presentation Parse time</b>: " .
                    changeInMicrotime($start_time)."<br />";
                $summaries_time = microtime();
            }

            $out_results = $this->getSummariesByHash($word_structs,
                $low, $phrase_num, $filter, $use_cache_if_allowed, $raw, 
                $queue_servers, $phrase);

            if(isset($out_results['PAGES']) &&
                count($out_results['PAGES']) != 0) {
                $out_count = 0;
                foreach($result_bounds as $bound) {
                    for($i = $bound[0];
                        $i < min($bound[0] + $bound[1], $results_high);
                        $i++) {
                         if(isset($out_results['PAGES'][$out_count])) {
                            $results['PAGES'][$i] =
                                $out_results['PAGES'][$out_count];
                            $out_count++;
                         }
                    }
                }
                if($phrase == $last_part && isset($out_results['TOTAL_ROWS'])){
                    $total_rows = $out_results['TOTAL_ROWS'];
                }
            }
            if(QUERY_STATISTICS) {
                $this->query_info['QUERY'] .= "$in2<b>Get Summaries time</b>: ".
                    changeInMicrotime($summaries_time)."<br />";
                $format_time = microtime();
            }
        }

        if(isset($results['PAGES'])){
            ksort($results['PAGES']);
            $results["PAGES"] = array_values($results["PAGES"]);
        }
        if(count($results) == 0) {
            $results = NULL;
        }
        if($results == NULL) {
            $total_rows = 0;
            $results['TOTAL_ROWS'] = 0;
        }
        if(isset($total_rows)) {
            $results['TOTAL_ROWS'] = $total_rows;
        } else {
            $results['TOTAL_ROWS'] = count($results['PAGES']);
        }

        if($format) {
            if(count($format_words) == 0 ){
                $format_words = NULL;
            }
        } else {
            $format_words = NULL;
        }

        $description_length = self::DEFAULT_DESCRIPTION_LENGTH;
        if(isset($this->additional_meta_words) &&
            is_array($this->additional_meta_words)) {
            foreach($this->additional_meta_words as $meta_word => $length){
                $pattern = "/$meta_word/";
                if(preg_match($pattern, $input_phrase)) {
                    $description_length = $length;
                    break; // only match the first found
                }
            }
        }
        if($raw == 0) {
            $output = $this->formatPageResults($results, $format_words,
                $description_length);
        } else {
            $output = $results;
        }

        if(QUERY_STATISTICS) {
            $this->query_info['QUERY'] .= "<b>Format time</b>: ".
                changeInMicrotime($format_time)."<br />";
            $this->query_info['ELAPSED_TIME'] = changeInMicrotime($start_time);
            $this->db->total_time += $this->query_info['ELAPSED_TIME'];
            $this->db->query_log[] = $this->query_info;
        }
        return $output;

    }

    /**
     *  Parses from a string phrase representing a conjunctive query, a struct
     *  consisting of the words keys searched for, the allowed and disallowed
     *  phrases, the weight that should be put on these query results, and
     *  which archive to use.
     *
     * @param string $phrase string to extract struct from
     * @param array $queue_servers a list of urls of yioop machines which might
     *      be used during lookup
     * @return array struct representing the conjunctive query
     */
    function parseWordStructConjunctiveQuery($phrase, $queue_servers = array())
    {
        $indent= "&nbsp;&nbsp;";
        $in2 = $indent . $indent;
        $in3 = $in2 . $indent;
        $in4 = $in2. $in2;
        $phrase = " ".$phrase;
        $phrase = $this->guessSemantics($phrase);
        $phrase = $this->parseIfConditions($phrase);
        $phrase_string = $phrase;
        $phrase_string = str_replace("&", "&amp;", $phrase_string);
        $meta_words = $this->meta_words_list;
        if(isset($this->additional_meta_words)) {
            $meta_words = array_merge($meta_words, array_keys(
                $this->additional_meta_words));
        }
        $index_name = $this->index_name;
        $weight = 1;
        $found_metas = array();
        $disallow_phrases = array();
        foreach($meta_words as $meta_word) {
            $pattern = "/(\s)($meta_word(\S)+)/";
            preg_match_all($pattern, $phrase, $matches);
            if(!in_array($meta_word, array('i:', 'index:', 'w:',
            'weight:', '\-') )) {
                $matches = $matches[2];
                $found_metas = array_merge($found_metas, $matches);
            } else if($meta_word == '\-') {
                if(count($matches[0]) > 0) {
                    $disallow_phrases =
                        array_merge($disallow_phrases,
                            array(substr($matches[2][0],1)));
                }
            } else if ($meta_word == 'i:' || $meta_word == 'index:') {
                if(isset($matches[2][0])) {
                    $index_name = substr($matches[2][0],strlen($meta_word));
                }
            } else if ($meta_word == 'w:' || $meta_word == 'weight:') {

                if(isset($matches[2][0])) {
                    $weight = substr($matches[2][0],strlen($meta_word));
                }
            }
            $phrase_string = preg_replace($pattern, "", $phrase_string);
        }
        $index_archive_name = self::index_data_base_name . $index_name;

        /* if network query then don't want to create an IndexArchive
           so create a mock object that suffices till the NetworkIterator
           is constructed
         */
        $index_dummy_flag = false;
        if($queue_servers != array() &&
            !$this->isSingleLocalhost($queue_servers)) {
            $tmp["dir_name"] = CRAWL_DIR.'/cache/'.$index_archive_name;
            $index_archive = (object) $tmp;
            $index_dummy_flag = true;
        } else {
            $index_archive = new IndexArchiveBundle(
                CRAWL_DIR.'/cache/'.$index_archive_name);
        }

        $phrase_string = mb_ereg_replace("&amp;", "_and_", $phrase_string);
        
        $query_string = mb_ereg_replace(PUNCT, " ", $phrase_string);
        $query_string = preg_replace("/(\s)+/", " ", $query_string);
        $query_string = mb_ereg_replace('_and_', '&', $query_string);
        $phrase_string = mb_ereg_replace('_and_', '&', $phrase_string);

        /*
            we search using the stemmed/char-grammed words, but we format
            snippets in the results by bolding either
         */
        $query_words = explode(" ", $query_string); //not stemmed

        $locale_tag = guessLocaleFromString($query_string);
        $base_words = //still use original phrase string here to handle acronyms
            //abbreviations and the like that use periods
            PhraseParser::extractPhrases($phrase_string, $locale_tag); 
                //stemmed, if have stemmer
        $words = array_merge($base_words, $found_metas);
        if(QUERY_STATISTICS) {
            $this->query_info['QUERY'] .= "$in3<i>Index</i>: ".
                $index_archive_name."<br />";
            $this->query_info['QUERY'] .= "$in3<i>LocaleTag</i>: ".
                $locale_tag."<br />";
            $this->query_info['QUERY'] .=
                "$in3<i>Stemmed/Char-grammed Words</i>:<br />";
            foreach($base_words as $word){
                $this->query_info['QUERY'] .= "$in4$word<br />";
            }
            $this->query_info['QUERY'] .= "$in3<i>Meta Words</i>:<br />";
            foreach($found_metas as $word){
                $this->query_info['QUERY'] .= "$in4$word<br />";
            }
        }
        if(isset($words) && count($words) == 1 &&
            count($disallow_phrases) < 1) {
            $phrase_string = $words[0];
            $phrase_hash = crawlHash($phrase_string);
            $word_struct = array("KEYS" => array($phrase_hash),
                "RESTRICT_PHRASES" => NULL, "DISALLOW_KEYS" => array(),
                "WEIGHT" => $weight, "INDEX_ARCHIVE" => $index_archive
            );
        } else {
            /*
                handle strings in quotes
                (we want an exact match on such quoted strings)
            */
            $quoteds =array();
            $hash_quoteds = array();
            $num_quotes =
                preg_match_all('/\"((?:[^\"\\\]|\\\\.)*)\"/', $phrase,$quoteds);
            if(isset($quoteds[1])) {
                $quoteds = $quoteds[1];
            }

            //get a raw list of words and their hashes

            $hashes = array();
            $i = 0;
            foreach($words as $word) {
                $hashes[] = crawlHash($word);
            }

            $restrict_phrases = $quoteds;

            if(count($hashes) > 0) {
                $word_keys = array_slice($hashes, 0, MAX_QUERY_TERMS);
            } else {
                $word_keys = NULL;
                $word_struct = NULL;
            }
            $restrict_phrases = array_unique($restrict_phrases);
            $restrict_phrases = array_filter($restrict_phrases);
            if(!$index_dummy_flag) {
                $index_archive->setCurrentShard(0, true);
            }
            $disallow_keys = array();
            $num_disallow_keys = min(MAX_QUERY_TERMS, count($disallow_phrases));
            for($i = 0; $i < $num_disallow_keys; $i++) {
                $disallow_stem=array_keys(PhraseParser::extractPhrasesAndCount(
                    $disallow_phrases[$i], 2, getLocaleTag()));
                        //stemmed
                $disallow_keys[] = crawlHash($disallow_stem[0]);
            }

            if($word_keys !== NULL) {
                $word_struct = array("KEYS" => $word_keys,
                    "RESTRICT_PHRASES" => $restrict_phrases,
                    "DISALLOW_KEYS" => $disallow_keys,
                    "WEIGHT" => $weight,
                    "INDEX_ARCHIVE" => $index_archive
                );
            }
        }
        $format_words = array_merge($query_words, $base_words);

        return array($word_struct, $format_words);
    }



    /**
     * The plan is code toguess from the query what the user is 
     * looking for will be called from here. For now, we are just guessing
     * when a query term is a url and rewriting it to the appropriate meta
     * meta word.
     *
     *  @param string $phrase input query to guess semantics of
     *  @return string a phrase that more closely matches the intentions of the
     *      query.
     */
    function guessSemantics($phrase)
    {
        $domain_suffixes = array(".com", ".net", ".edu", ".org", ".gov", 
            ".mil", ".ca", ".uk", ".fr", ".ly");
        foreach($domain_suffixes as $suffix) {
            $phrase = $this->endMatch($phrase, $suffix, "site:", "", ":");
        }

        $phrase = $this->beginMatch($phrase, "www.", "site:www.");

        $phrase = $this->beginMatch($phrase, "http:", "site:http:");

        $phrase = $this->beginMatch($phrase, "info:", "info:http://", "/",
            "/");

        $phrase = $this->beginMatch($phrase, "info:", "info:http://", "",
            "http://");

        return $phrase;
    }

    /**
     *  Matches terms (non white-char strings) in the language $lang_tag in 
     *  $phrase that begin with  $start_with and don't contain  $not_contain, 
     *  replaces $start_with with $new_prefix and adds $suffix to the end 
     *
     *  @param string $phrase string to look for terms in
     *  @param string $start_with what we're looking to see if term begins with
     *  @param string $new_prefix what to change $start_with to
     *  @param string $suffix what to tack on to the end of the term if there is
     *      a match
     *  @param string $lang_tag what language the phrase must be in for the rule
     *      to apply
     *
     *  @return string $phrase after modifications have been made
     */
    function beginMatch($phrase, $start_with, $new_prefix, $suffix = "",
        $not_contain="", $lang_tag = "en-US")
    {
        $phrase .= " ";
        $quote_start_with = preg_quote($start_with, "/");
        $pattern = "/(\s)($quote_start_with(\S)+)/";
        $start_pos = strlen($start_with);
        preg_match_all($pattern, $phrase, $matches);
        $matches = $matches[2];
        $result_phrase = preg_replace($pattern, "", $phrase);
        foreach($matches as $match) {
            $tag = guessLocaleFromString($match, $lang_tag, 10);
            if($tag == $lang_tag && ($not_contain == "" || 
                !strstr($match, $not_contain))) {
                $body = substr($match, $start_pos);
                $result_phrase .= " ".$new_prefix.$body.$suffix;
            } else {
                $result_phrase .= " ".$match;
            }
        }
        return $result_phrase;
    }

    /**
     *  Matches terms (non white-char strings) in the language $lang_tag in 
     *  $phrase that end with $end_with and don't contain  $not_contain, 
     *  replaces $end_with with $new_suffix (if not empty) and adds $prefix to 
     *  the beginning 
     *
     *  @param string $phrase string to look for terms in
     *  @param string $end_with what we're looking to see if term ends with
     *  @param string $prefix what to tack on to the start if there is
     *      a match
     *  @param string $suffix what to change $end_with to
     *  @param string $lang_tag what language the phrase must be in for the rule
     *      to apply
     *
     *  @return string $phrase after modifications have been made
     */
    function endMatch($phrase, $end_with, $prefix, $new_suffix = "",
        $not_contain="",
        $lang_tag = "en-US")
    {
        $phrase .= " ";
        $quote_end_with = preg_quote($end_with, "/");
        $pattern = "/(\s)((\S)+$quote_end_with)(\s)/";
        $end_len = strlen($end_with);
        preg_match_all($pattern, $phrase, $matches);
        $matches = $matches[2];
        $result_phrase = preg_replace($pattern, " ", $phrase);
        foreach($matches as $match) {
            $tag = guessLocaleFromString($match, $lang_tag, 10);
            if($tag == $lang_tag && ($not_contain = "" || 
                !strstr($match, $not_contain))) {
                if($new_suffix == "") {
                    $body = $match;
                } else {
                    $body = substr($match, 0, -$end_len);
                }
                $result_phrase .= " $prefix".$body.$new_suffix;
            } else {
                $result_phrase .= " ".$match;
            }
        }
        return $result_phrase;
    }

    /**
     * Evaluates any if: conditional meta-words in the query string to
     * caluclate a new query string.
     *
     * @param string $phrase original query string
     * @return string query string after if: meta words have been evaluated
     */
    function parseIfConditions($phrase)
    {
        $cond_token = "if:";
        $pattern = "/(\s)($cond_token(\S)+)/";
        preg_match_all($pattern, $phrase, $matches);
        $matches = $matches[2];
        $result_phrase = preg_replace($pattern, "", $phrase);
        foreach($matches as $match) {
            $match = substr($match, strlen($cond_token));
            $match_parts = explode("!", $match);
            if(count($match_parts) < 2) continue;
            if(stristr($result_phrase, $match_parts[0]) !== false) {
                $result_phrase .= " ".str_replace("+", " ", $match_parts[1]);
            } else if(isset($match_parts[2])) {
                $result_phrase .= " ".str_replace("+", " ", $match_parts[2]);
            }
        }
        return $result_phrase;
    }

    /**
     * Gets doc summaries of documents containing given words and meeting the
     * additional provided criteria
     * @param array $word_structs an array of word_structs. Here a word_struct
     *      is an associative array with at least the following fields
     *      KEYS -- an array of word keys
     *      RESTRICT_PHRASES -- an array of phrases the document must contain
     *      DISALLOW_PHRASES -- an array of words the document must not contain
     *      WEIGHT -- a weight to multiple scores returned from this iterator by
     *      INDEX_ARCHIVE -- an index_archive object to get results from
     * @param int $limit number of first document in order to return
     * @param int $num number of documents to return summaries of
     * @param array &$filter an array of hashes of domains to filter from
     *      results
     * @param bool $use_cache_if_allowed if true and USE_CACHE is true then
     *      an attempt will be made to look up the results in either
     *      the file cache or memcache. Otherwise, items will be recomputed
     *      and then potentially restored in cache
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *      no grouping but page look-up for links, ($raw == 2)
     *      no grouping done on data. If $raw > 0 no caching is done as will
     *      likely come from a network query
     * @param array $queue_servers a list of urls of yioop machines which might
     *      be used during lookup
     * @param string $original_query if set, the original query that corresponds
     *      to $word_structs
     *
     * @return array document summaries
     */
    function getSummariesByHash($word_structs, $limit, $num, &$filter,
        $use_cache_if_allowed = true, $raw = 0, $queue_servers = array(),
        $original_query = "")
    {
        global $CACHE;

        $pages = array();
        $generation = 0;
        $to_retrieve = ceil(($limit+$num)/self::NUM_CACHE_PAGES) *
            self::NUM_CACHE_PAGES;
        $start_slice = floor(($limit)/self::NUM_CACHE_PAGES) *
            self::NUM_CACHE_PAGES;
        if(USE_CACHE && $raw == 0) {
            $mem_tmp = "";
            foreach($word_structs as $word_struct) {
                $mem_tmp .= serialize($word_struct["KEYS"]).
                    serialize($word_struct["RESTRICT_PHRASES"]) .
                    serialize($word_struct["DISALLOW_KEYS"]) .
                    $word_struct["WEIGHT"] .
                    $word_struct["INDEX_ARCHIVE"]->dir_name;
            }
            if($use_cache_if_allowed) {
                $cache_success = true;
                $results = array();
                $results['PAGES'] = array();
                for($i=$start_slice; $i<$to_retrieve;$i+=self::NUM_CACHE_PAGES){
                    $summary_hash = crawlHash($mem_tmp.":".$i);
                    $slice = $CACHE->get($summary_hash);
                    if($slice === false) {
                        $cache_success = false;
                        break;
                    }
                    $results['PAGES'] = array_merge($results['PAGES'],
                        $slice['PAGES']);
                    $results['TOTAL_ROWS'] = $slice['TOTAL_ROWS'];
                }
                if($cache_success) {
                    $results['PAGES'] =
                        array_slice($results['PAGES'],
                            $limit - $start_slice, $num);
                    return $results;
                }
            }
        }
        $query_iterator = $this->getQueryIterator($word_structs, $filter, $raw,
             $queue_servers, $original_query);

        $num_retrieved = 0;
        $pages = array();

        $isLocal = ($queue_servers == array()) ||
            $this->isSingleLocalhost($queue_servers);

        while($num_retrieved < $to_retrieve && is_object($query_iterator) &&
            is_array($next_docs = $query_iterator->nextDocsWithWord()) ) {
            foreach($next_docs as $doc_key => $doc_info) {
                if($isLocal) {
                    $summary = & $doc_info[CrawlConstants::SUMMARY];

                    $tmp = unserialize($query_iterator->getIndex(
                        $doc_key)->description);

                    $doc_info[self::CRAWL_TIME] = $tmp[self::CRAWL_TIME];
                    unset($doc_info[CrawlConstants::SUMMARY]);
                    if(is_array($summary)) {
                        $pre_page = array_merge($doc_info, $summary);
                        $robots_okay = true;
                        if(isset($pre_page[CrawlConstants::ROBOT_METAS])) {
                            if(in_array("NOINDEX", $pre_page[self::ROBOT_METAS])
                                 || 
                                in_array("NONE", $pre_page[self::ROBOT_METAS])){
                                $robots_okay = false;
                            }
                        }
                        if($robots_okay) {
                            $pages[] = $pre_page;
                            $num_retrieved++;
                        }
                    }
                } else {
                    $pages[] = $doc_info;
                    $num_retrieved++;
                }
            }
        }

        $result_count = count($pages);
        // initialize scores
        for($i = 0; $i < $result_count; $i++) {
            $pages[$i]["OUT_SCORE"] = 0;
        }
        $subscore_fields = array(self::DOC_RANK, self::RELEVANCE,
            self::PROXIMITY, self::SCORE);
        $num_fields = count($subscore_fields);
        // Compute Reciprocal Rank Fusion Score
        $alpha = 600/$num_fields;
        if(isset($pages[0])) {
            foreach($subscore_fields as $field) {
                orderCallback($pages[0], $pages[0], $field);
                usort($pages, "orderCallback");
                $score = 0;
                for($i = 0; $i < $result_count; $i++) {
                    if($i > 0) {
                        if($pages[$i - 1][$field] != $pages[$i][$field]) {
                            $score++;
                        }
                    }
                    $pages[$i]["OUT_SCORE"] += $alpha/(60 + $score);
                }
            }
            orderCallback($pages[0], $pages[0], "OUT_SCORE");
        }
        usort($pages, "orderCallback");
        for($i = 0; $i < $result_count; $i++) {
           $pages[$i][self::SCORE] = $pages[$i]["OUT_SCORE"];
        }

        if($num_retrieved < $to_retrieve) {
            $results['TOTAL_ROWS'] = $num_retrieved;
        } else {
            $results['TOTAL_ROWS'] =  $query_iterator->num_docs;
            //this is only an approximation
        }


        if(USE_CACHE && $raw  == 0) {
            for($i = 0; $i < $result_count; $i++){
                unset($pages[$i][self::LINKS]);
            }
            for($i = 0;$i < $to_retrieve;$i+=self::NUM_CACHE_PAGES){
                $summary_hash = crawlHash($mem_tmp.":".$i);
                $slice['PAGES'] = array_slice($pages, $i,
                    self::NUM_CACHE_PAGES);
                $slice['TOTAL_ROWS'] = $results['TOTAL_ROWS'];
                $CACHE->set($summary_hash, $slice);
            }

        }
        $results['PAGES'] = & $pages;
        $results['PAGES'] = array_slice($results['PAGES'], $start_slice);
        $results['PAGES'] = array_slice($results['PAGES'], $limit -
            $start_slice, $num);

        return $results;
    }


    /**
     * Using the supplied $word_structs, contructs an iterator for getting
     * results to a query
     *
     * @param array $word_structs an array of word_structs. Here a word_struct
     *      is an associative array with at least the following fields
     *      KEYS -- an array of word keys
     *      RESTRICT_PHRASES -- an array of phrases the document must contain
     *      DISALLOW_PHRASES -- an array of words the document must not contain
     *      WEIGHT -- a weight to multiple scores returned from this iterator by
     *      INDEX_ARCHIVE -- an index_archive object to get results from
     * @param array &$filter an array of hashes of domains to filter from
     *      results
     *      and then potentially restored in cache
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *      no grouping but page look-up for links, ($raw == 2)
     *      no grouping done on data
     * @param array $queue_servers a list of urls of yioop machines which might
     *      be used during lookup
     * @param string $original_query if set, the orginal query that corresponds
     *      to $word_structs
     *
     * @return &object an iterator for iterating through results to the
     *  query
     */
    function getQueryIterator($word_structs, &$filter, $raw = 0, 
        $queue_servers = array(), $original_query = "")
    {
        $iterators = array();
        $total_iterators = 0;
        $network_flag = false;
        if($queue_servers != array() &&
            !$this->isSingleLocalhost($queue_servers)) {
                $network_flag = true;
                $total_iterators = 1;
                $num_servers = count($queue_servers);
                $iterators[0] = new NetworkIterator($original_query, 
                    $queue_servers, $this->index_name, $filter);
        }
        if(!$network_flag) {
            foreach($word_structs as $word_struct) {
                if(!is_array($word_struct)) { continue;}
                $word_keys = $word_struct["KEYS"];
                $distinct_word_keys = array_unique($word_keys);
                $restrict_phrases = $word_struct["RESTRICT_PHRASES"];
                $disallow_keys = $word_struct["DISALLOW_KEYS"];
                $index_archive = $word_struct["INDEX_ARCHIVE"];
                $weight = $word_struct["WEIGHT"];
                $num_word_keys = count($word_keys);
                $total_iterators = count($distinct_word_keys);
                $word_iterators = array();
                $word_iterator_map = array();
                if($num_word_keys < 1) {continue;}

                for($i = 0; $i < $total_iterators; $i++) {
                    $word_iterators[$i] =
                        new WordIterator($distinct_word_keys[$i], 
                            $index_archive, false, $filter);
                    foreach ($word_keys as $index => $key) {
                        if(isset($distinct_word_keys[$i]) && 
                            $key == $distinct_word_keys[$i]){
                            $word_iterator_map[$index] = $i;
                        }
                    }
                }
                $num_disallow_keys = count($disallow_keys);
                if($num_disallow_keys > 0) {
                for($i = 0; $i < $num_disallow_keys; $i++) {
                        $disallow_iterator =
                            new WordIterator($disallow_keys[$i], $index_archive,
                                false, $filter);
                        $word_iterators[$num_word_keys + $i] =
                            new NegationIterator($disallow_iterator);
                    }
                }
                $num_word_keys += $num_disallow_keys;

                if($num_word_keys == 1) {
                    $base_iterator = $word_iterators[0];
                } else {
                    $base_iterator = new IntersectIterator(
                        $word_iterators,$word_iterator_map);
                }
                if($restrict_phrases == NULL && $disallow_keys == array() &&
                    $weight == 1) {
                    $iterators[] = $base_iterator;
                } else {
                    $iterators[] = new PhraseFilterIterator($base_iterator,
                        $restrict_phrases, $weight);
                }

            }
        }
        $num_iterators = count($iterators);

        if( $num_iterators < 1) {
            return NULL;
        } else if($num_iterators == 1) {
            $union_iterator = $iterators[0];
        } else {
            $union_iterator = new UnionIterator($iterators);
        }

        $raw = intval($raw);

        if ($raw == 2) {
            $group_iterator = $union_iterator;
        } else if ($raw == 1) {
            $group_iterator =
                new GroupIterator($union_iterator, $total_iterators, true);
        } else {
            $group_iterator =
                new GroupIterator($union_iterator, $total_iterators);
        }

        if($network_flag) {
            $union_iterator->results_per_block = 
                ceil(SERVER_ALPHA * 
                    $group_iterator->results_per_block/$num_servers);
        }

        return $group_iterator;
    }

}

?>
