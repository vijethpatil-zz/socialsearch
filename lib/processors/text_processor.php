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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load the base class
 */
require_once BASE_DIR."/lib/processors/page_processor.php";

/**
 * So can extract parts of the URL if need to guess lang
 */
require_once BASE_DIR."/lib/url_parser.php";


/**
 * Parent class common to all processors used to create crawl summary 
 * information  that involves basically text data
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class TextProcessor extends PageProcessor
{
    /**
     * Max number of chars to extract for description
     */
    const MAX_DESCRIPTION_LEN = 2000;

    /**
     * Computes a summary based on a text string of a document
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by 
     *      TextProcessor at this point. Some of its subclasses override
     *      this method and use url to produce complete links for
     *      relative links within a document
     *
     * @return array a summary of (title, description,links, and content) of 
     *      the information in $page
     */
    function process($page, $url)
    {
        $summary = NULL;

        if(is_string($page)) {
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = mb_substr($page, 0, 
                self::MAX_DESCRIPTION_LEN);
            $summary[self::LANG] = self::calculateLang(
                $summary[self::DESCRIPTION]);
            $summary[self::LINKS] = self::extractHttpHttpsUrls($page);
            $summary[self::PAGE] = "<html><body><pre>".
                strip_tags($page)."</pre></body></html>";
        }
        return $summary;
    }


    /**
     *  Tries to determine the language of the document by looking at the
     *  $sample_text and $url provided
     *  the language
     *  @param string $sample_text sample text to try guess the language from
     *  @param string $url url of web-page as a fallback look at the country
     *      to figure out language
     *
     *  @return string language tag for guessed language
     */
    static function calculateLang($sample_text = NULL, $url = NULL)
    {
        if($url != NULL) {
            $lang = UrlParser::getLang($url);
            if($lang != NULL) return $lang;
        }
        if($sample_text != NULL){
            $words = mb_split("[[:space:]]|".PUNCT, $sample_text);
            $num_words = count($words);
            $ascii_count = 0;
            foreach($words as $word) {
                if(strlen($word) == mb_strlen($word)) {
                    $ascii_count++;
                }
            }
            // crude, but let's guess ASCII == english
            if($ascii_count/$num_words > EN_RATIO) {
                $lang = 'en';
            } else {
                $lang = NULL;
            }
        } else {
            $lang = NULL;
        }


        return $lang;
    }

    /**
     * Gets the text between two tags in a document starting at the current
     * position.
     *
     * @param string $string document to extract text from
     * @param int $cur_pos current location to look if can extract text
     * @param string $start_tag starting tag that we want to extract after
     * @param string $end_tag ending tag that we want to extract until
     * @return array pair consisting of when in the document we are after
     *      the end tag, together with the data between the two tags
     */
    static function getBetweenTags($string, $cur_pos, $start_tag, $end_tag) 
    {
        $len = strlen($string);
        if(($between_start = strpos($string, $start_tag, $cur_pos)) === 
            false ) {
            return array($len, "");
        }

        $between_start  += strlen($start_tag);
        if(($between_end = strpos($string, $end_tag, $between_start)) === 
            false ) {
            $between_end = $len;
        }

        $cur_pos = $between_end + strlen($end_tag);

        $between_string = substr($string, $between_start, 
            $between_end - $between_start);
        return array($cur_pos, $between_string);

    }

    /**
     * Tries to extract http or https links from a string of text.
     * Does this by a very approximate regular expression.
     *
     * @param string $page text string of a document
     * @return array a set of http or https links that were extracted from
     *      the document
     */
    static function extractHttpHttpsUrls($page)
    {
        $pattern = 
            '@((http|https)://([^ \t\r\n\v\f\'\"\;\,<>\{\}])*)@i';
        $sites = array();
        preg_match_all($pattern, $page, $matches);
        $i = 0;
        foreach($matches[0] as $url) {
            if(!isset($sites[$url]) && strlen($url) < MAX_URL_LENGTH) {
                $sites[$url] = strip_tags($url);
                $i++;
                if($i >= MAX_LINKS_PER_PAGE) {break;}
            }
        }
        return $sites;
    }

    /**
     * If an end of file is reached before closed tags are seen, this methods
     * closes these tags in the correct order.
     *
     * @param string &$page a reference to an xml or html document
     */
    static function closeDanglingTags(&$page)
    {
        $l_pos = strrpos($page, "<");
        $g_pos = strrpos($page, ">");
        if($g_pos && $l_pos > $g_pos) {
            $page = substr($page, 0, $l_pos);
        }
        // put all opened tags into an array
        preg_match_all("#<([a-z]+)( .*)?(?!/)>#iU", $page, $result);
        $openedtags = $result[1];

        // put all closed tags into an array
        preg_match_all("#</([a-z]+)>#iU", $page, $result);
        $closedtags=$result[1];
        $len_opened = count($openedtags);
        // all tags are closed
        if(count($closedtags) == $len_opened){
            return;
        }

        $openedtags = array_reverse($openedtags);
        // close tags
        for($i=0;$i < $len_opened;$i++) {
            if (!in_array($openedtags[$i],$closedtags)){
              $page .= '</'.$openedtags[$i].'>';
            } else {
              unset($closedtags[array_search($openedtags[$i],$closedtags)]);
            }
        }
    }
}

?>
