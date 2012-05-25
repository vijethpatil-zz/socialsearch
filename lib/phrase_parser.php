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

foreach(glob(LOCALE_DIR."/*/resources/tokenizer.php")
    as $filename) {
    require_once $filename;
}
$GLOBALS["CHARGRAMS"] = $CHARGRAMS;

/**
 * Load the n word grams File
 */
require_once BASE_DIR."/lib/nword_grams.php";

/**
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Library of functions used to manipulate words and phrases
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class PhraseParser
{
    /**
     * Converts a summary of a web page into a string of space separated words
     *
     * @param array $page associative array of page summary data. Contains
     *      title, description, and links fields
     * @return string the concatenated words extracted from the page summary
     */
    static function extractWordStringPageSummary($page)
    {
        if(isset($page[CrawlConstants::TITLE])) {
            $title_phrase_string = mb_ereg_replace(PUNCT, " ",
                $page[CrawlConstants::TITLE]);
        } else {
            $title_phrase_string = "";
        }
        if(isset($page[CrawlConstants::DESCRIPTION])) {
            $description_phrase_string = mb_ereg_replace(PUNCT, " ",
                $page[CrawlConstants::DESCRIPTION]);
        } else {
            $description_phrase_string = "";
        }
        $page_string = $title_phrase_string . " " . $description_phrase_string;
        $page_string = preg_replace("/(\s)+/", " ", $page_string);

        return $page_string;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Array key indicates position
     * of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array of phrases
     */
    static function extractPhrases($string,$lang = NULL,$orig_and_grams = false)
    {
        self::canonicalizePunctuatedTerms($string, $lang);
        $phrase_pos = self::extractPhrasesInLists($string, $lang, 
            $orig_and_grams, false);
        $phrases = array_keys($phrase_pos);
        return $phrases;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Returns an associative array
     * of phrase => number of occurrences of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array pairs of the form (phrase, number of occurrences)
     */
    static function extractPhrasesAndCount($string, $lang = NULL, 
        $orig_and_grams = false)
    {

        self::canonicalizePunctuatedTerms($string, $lang);

        $phrases = self::extractPhrasesInLists($string, $lang, $orig_and_grams, 
            false);
        $phrase_counts = array();
        foreach($phrases as $term => $positions) {
            $phrase_counts[$term] = count($positions);
        }

        return $phrase_counts;
    }

    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * extract terms within those phrase. 
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array word => list of positions at which the word occurred in
     *      the document
     */
    static function extractPhrasesInLists($string,
        $lang = NULL, $orig_and_grams = false, $phrases_and_terms = true)
    {
        $phrase_lists = array();
        self::canonicalizePunctuatedTerms($string, $lang);
        $pre_phrases = 
            self::extractTermsAndFilterPhrases($string, $lang, $orig_and_grams);
        $phrases = array();
        $j = 0;
        foreach($pre_phrases as $pre_phrase) {
            $len = count($pre_phrase);
            if($len == 1) {
                $phrases[$pre_phrase[0]][] = $j++;
            } else {
                $phrases[implode(" ", $pre_phrase)][] = $j;
                if($phrases_and_terms) {
                    foreach($pre_phrase as $term) {
                        $phrases[$term][] = $j++;
                    }
                }
            }
        }
        return $phrases;
    }

    /**
     * This functions tries to convert acronyms, e-mail, urls, etc into
     * a format that does not involved punctuation that will be stripped
     * as we extract phrases.
     *
     * @param &$string a string of words, etc which might involve such terms
     * @param $lang a language tag to use as part of the canonicalization 
     *      process not used right now
     */
    static function canonicalizePunctuatedTerms(&$string, $lang = NULL)
    {
        //these obscure statics is because php 5.2 does not garbage collect
        //create_function's
        static $replace_function0, $replace_function1, $replace_function2;
        $acronym_pattern = "/[A-Za-z]\.(\s*[A-Za-z]\.)+/";
        if(!isset($replace_function0)) {
            $replace_function0 = create_function('$matches', '
                $result = "_".mb_strtolower(
                    mb_ereg_replace("\.", "", $matches[0]));
                return $result;');
        }
        $string = preg_replace_callback($acronym_pattern, 
            $replace_function0, $string);

        $ampersand_pattern = "/[A-Za-z]+(\s*(\s(\'n|\'N)\s|\&)\s*[A-Za-z])+/";
        if(!isset($replace_function1)) {
            $replace_function1 = create_function('$matches', '
                $result = mb_strtolower(
                    mb_ereg_replace("\s*(\'n|\'N|\&)\s*", "_and_",$matches[0]));
                return $result;
            ');
        }
        $string = preg_replace_callback($ampersand_pattern,$replace_function1,
            $string);

        $url_or_email_pattern = 
            '@((http|https)://([^ \t\r\n\v\f\'\"\;\,<>])*)|'.
            '([A-Z0-9._%-]+\@[A-Z0-9.-]+\.[A-Z]{2,4})@i';
        if(!isset($replace_function2)) {
            $replace_function2 = create_function('$matches', '
                $result =  mb_ereg_replace("\.", "_d_",$matches[0]);
                $result =  mb_ereg_replace("\:", "_c_",$result);
                $result =  mb_ereg_replace("\/", "_s_",$result);
                $result =  mb_ereg_replace("\@", "_a_",$result);
                $result =  mb_ereg_replace("\[", "_bo_",$result);
                $result =  mb_ereg_replace("\]", "_bc_",$result);
                $result =  mb_ereg_replace("\(", "_po_",$result);
                $result =  mb_ereg_replace("\)", "_pc_",$result);
                $result =  mb_ereg_replace("\?", "_q_",$result);
                $result =  mb_ereg_replace("\=", "_e_",$result);
                $result =  mb_ereg_replace("\&", "_a_",$result);
                $result = mb_strtolower($result);
                return $result;
            ');
        }
        $string = preg_replace_callback($url_or_email_pattern, 
            $replace_function2, $string);
    }

    /**
     * Splits string according to punctuation and white space then
     * extracts (stems/char grams) of terms and n word grams from the string
     *
     * @param string $string to extract terms from
     * @param string $lang IANA tag to look up stemmer under
     * @param bool $orig_and_grams if char-gramming is done whether to keep
     *      the original term as well in what's returned
     * @return array of terms and n word grams in the order they appeared in
     *      string
     */
    static function extractTermsAndFilterPhrases($string,
        $lang = NULL, $orig_and_grams = false)
    {
        global $CHARGRAMS;

        mb_internal_encoding("UTF-8");
        //split first on punctuation as n word grams shouldn't cross punctuation
        $fragments = mb_split(PUNCT, $string);

        $final_terms = array();
        $stem_obj = self::getStemmer($lang);

        foreach($fragments as $fragment) {
            $pre_terms = mb_split("[[:space:]]", $fragment);
            if($pre_terms == array()) continue;
            $terms = array();
            if(isset($CHARGRAMS[$lang])) {
                foreach($pre_terms as $pre_term) {
                    if($pre_term == "") continue;
                    $ngrams = self::getCharGramsTerm(array($pre_term), $lang);
                    if($orig_and_grams) {
                        $terms[]  = $pre_term;
                        $terms = array_merge($terms, $ngrams);
                    } else if(count($ngrams) > 0) {
                        $terms = array_merge($terms, $ngrams);
                    }
                }
            } else {
                $terms = $pre_terms;
            }
            $stems = array();
            if($stem_obj != NULL) { 
                foreach($terms as $term) {
                    $pre_stem = mb_strtolower($term);
                    $stems[] = $stem_obj->stem($pre_stem);
                }
            } else {
                foreach($terms as $term) {
                    $stems[] = mb_strtolower($term);
                }
            }
            $accumulators = array();
            $phrases = array();
            $i = 0;
            $num_stems = count($stems);
            while($i < $num_stems) {
                $tmp = $stems[$i];
                if($stems[$i] == "") {
                    $i++;
                    continue;
                }
                $j = $i + 1;
                $max_j = $i;
                $cont_ngram = true;
                while($cont_ngram && $j < $num_stems) {
                    $tmp .= " " . $stems[$j];
                    $isngram = NWordGrams::ngramsContains($tmp, $lang, "all");
                    if($isngram) {
                        $max_j = $j;
                    }
                    $cont_ngram = NWordGrams::ngramsContains($tmp."*", $lang,
                        "all");
                    $j++;
                }
                $phrases[] = array_slice($stems, $i, $max_j - $i + 1);
                $i = $max_j + 1;
            }

            $phrases = array_values($phrases);
            $final_terms = array_merge($final_terms, $phrases);
        }
        return $final_terms;
    }

    /**
     * Returns the characters n-grams for the given terms where n is the length
     * Yioop uses for the language in question. If a stemmer is used for
     * language then n-gramming is no done and this just returns an empty array
     *
     * @param array $terms the terms to make n-grams for
     * @param string $lang locale tag to determine n to be used for n-gramming
     *
     * @return array the n-grams for the terms in question
     */
    static function getCharGramsTerm($terms, $lang)
    {
        global $CHARGRAMS;

        mb_internal_encoding("UTF-8");
        if(isset($CHARGRAMS[$lang])) {
            $n = $CHARGRAMS[$lang];
        } else {
            return array();
        }

        $ngrams = array();

        foreach($terms as $term) {
            $pre_gram = $term;
            $last_pos = mb_strlen($pre_gram) - $n;
            if($last_pos < 0) {
                $ngrams[] = $pre_gram;
            } else {
                for($i = 0; $i <= $last_pos; $i++) {
                    $tmp = mb_substr($pre_gram, $i, $n);
                    if($tmp != "") {
                        $ngrams[] = $tmp;
                    }
                }
            }
        }
        return $ngrams;
    }

    /**
     * Splits supplied string based on white space, then stems each
     * terms according to the stemmer for $lanf if exists
     *
     * @param string $string to extract stemmed terms from
     * @param string $lang IANA tag to look up stemmer under
     * @return array stemmed terms if stemmer; terms otherwise
     */
    static function stemTerms($string, $lang)
    {
        $terms = mb_split("[[:space:]]", $string);
        $stem_obj = self::getStemmer($lang);
        $stems = array();
        if($stem_obj != NULL) {
            foreach($terms as $term) {
                $pre_stem = mb_strtolower($term);
                $stems[] = $stem_obj->stem($pre_stem);
            }
        } else {
            foreach($terms as $term) {
                $stems[] = mb_strtolower($term);
            }
        }

        return $stems;
    }

    /**
     * Loads and instantiates a stemmer object for a language if exists
     *
     * @param string $lang IANA tag to look up stemmer under
     * @return object stemmer object
     */
    static function getStemmer($lang)
    {
        mb_regex_encoding('UTF-8');
        mb_internal_encoding("UTF-8");
        $lower_lang = strtolower($lang); //try to avoid case sensitivity issues
        $lang_parts = explode("-", $lang);
        if(isset($lang_parts[1])) {
            $stem_class_name = ucfirst($lang_parts[0]).ucfirst($lang_parts[1]) .
                "Stemmer";
            if(!class_exists($stem_class_name)) {
                $stem_class_name = ucfirst($lang_parts[0])."Stemmer";
            }
        } else {
            $stem_class_name = ucfirst($lang)."Stemmer";
        }
        if(class_exists($stem_class_name)) {
            $stem_obj = new $stem_class_name(); //for php 5.2 compatibility
        } else {
            $stem_obj = NULL;
        }
        return $stem_obj;
    }

    /**
     *  Scores documents according to the lack or nonlack of sexually explicit
     *  terms. Tries to work for several languages.
     *
     *  @param array $word_lists word => pos_list tuples
     *  @param int $len length of text being examined in characters
     *  @return int $score of how explicit document is
     */
    static function computeSafeSearchScore(&$word_lists, $len)
    {
        static $unsafe_phrase = "
XXX sex slut nymphomaniac MILF lolita lesbian sadomasochism 
bondage fisting erotic vagina Tribadism penis facial hermaphrodite
transsexual tranny bestiality snuff boob fondle tit
blowjob lap cock dick hardcore pr0n fuck pussy penetration ass
cunt bisexual prostitution screw ass masturbation clitoris clit suck whore bitch
bellaco cachar chingar shimar chinquechar chichar clavar coger culear hundir
joder mámalo singar cojon carajo caray bicho concha chucha chocha
chuchamadre coño panocha almeja culo fundillo fundío puta puto teta
connorito cul pute putain sexe pénis vulve foutre baiser sein nicher nichons
puta sapatão foder ferro punheta vadia buceta bucetinha bunda caralho
mentula cunnus verpa sōpiō pipinna cōleī cunnilingus futuō copulate cēveō crīsō
scortor meretrīx futatrix minchia coglione cornuto culo inocchio frocio puttana
vaffanculo fok hoer kut lul やりまん 打っ掛け  二形 ふたなりゴックン ゴックン
ショタコン 全裸 受け 裏本 пизда́ хуй еба́ть блядь елда́ гондо́н хер манда́ му́ди мудя
пидора́с залу́па жо́па за́дница буфер 
雞巴 鷄巴 雞雞 鷄鷄 阴茎 陰莖 胯下物
屌 吊 小鳥 龟头 龜頭 屄 鸡白 雞白 傻屄 老二 那话儿 那話兒 屄 鸡白 雞白 阴道 陰道
阴户 陰戶 大姨妈 淫蟲 老嫖 妓女 臭婊子 卖豆腐 賣豆腐 咪咪 大豆腐 爆乳 肏操
炒饭 炒飯 cặc lồn kaltak orospu siktir sıçmak amcık";
        static $unsafe_terms = array();

        if(count($word_lists) == 0) {
            return 0;
        }

        if($unsafe_terms == array()) {
            $unsafe_lists = PhraseParser::extractPhrasesInLists($unsafe_phrase,
                "en-US", true);
            $unsafe_terms = array_keys($unsafe_lists);
        }

        $num_unsafe_terms = 0;
        $unsafe_count = 0;
        $words = array_keys($word_lists);

        $unsafe_found = array_intersect($words, $unsafe_terms);

        foreach($unsafe_found as $term) {
            $count = count($word_lists[$term]);
            if($count > 0 ) {
                $unsafe_count += $count;
                $num_unsafe_terms++;
            }
        }

        $score = $num_unsafe_terms * $unsafe_count/($len + 1);
        return $score;
    }
}
