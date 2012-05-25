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
 * This file contains global functions connected to localization that
 * are used throughout the web site part of Yioop!
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */
 
/**
 * Used to contain information about the current language and regional settings
 */
require_once BASE_DIR."/models/locale_model.php";


/**
 *  Attempts to guess the user's locale based on the request, session,
 *  and user-agent data
 *
 * @return string IANA language tag of the guessed locale
 */
function guessLocale()
{
    /* the request variable l and the browser's HTTP_ACCEPT_LANGUAGE
       are used to determine the locale */
    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $l_parts = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if(count($l_parts) > 0) {
            $guess_l = $l_parts[0];
        }
        $guess_map = array(
            "cn" => "zh-CN",
            "en" => "en-US",
            "en-us" => "en-US",
            "en-US" => "en-US",
            "fr" => "fr-FR",
            "ko" => "ko",
            "in" => "in-ID",
            "ja" => "ja",
            "vi" => "vi-VN",
            "vi-vn" => "vi-VN",
            "vi-VN" => "vi-VN",
            "zh" => "zh-CN",
            "zh-CN" => "zh-CN",
            "zh-cn" => "zh-CN",
        );
        if(isset($guess_map[$guess_l])) {
            $guess_l = $guess_map[$guess_l];
        }

    }

    if(isset($_SESSION['l']) || isset($_REQUEST['l']) || isset($guess_l)) {
        $l = (isset($_REQUEST['l'])) ? $_REQUEST['l'] : 
            ((isset($_SESSION['l'])) ? $_SESSION['l'] : $guess_l);
        if(strlen($l) < 10) { 
            $l= addslashes($l);
            if(is_dir(LOCALE_DIR."/$l")) {
                $locale_tag = $l;
            }
        }
    }

    if(!isset($locale_tag)) {
        $locale_tag = DEFAULT_LOCALE;
    }

    return $locale_tag;
}


/**
 *  Attempts to guess the user's locale based on a string sample
 *
 * @param string $phrase_string used to make guess
 * @param string $locale_tag language tag to use if can't guess -- if not
 *      provided uses current locale's value
 * @param int threshold number of chars to guess a particular encoding
 * @return string IANA language tag of the guessed locale

 */
function guessLocaleFromString($phrase_string, $locale_tag = NULL, $factor = 2)
{
    $locale_tag = ($locale_tag == NULL) ? getLocaleTag() : $locale_tag;
    $phrase_string = mb_convert_encoding($phrase_string, "UTF-32", "UTF-8");
    $len = strlen($phrase_string);
    $guess['zh-CN'] = 0;
    $guess['ru'] = 0;
    $guess['he'] = 0;
    $guess['ar'] = 0;
    $guess['th'] = 0;
    $guess['ja'] = 0;
    $guess['ko'] = 0;
    for($i = 0; $i < $len; $i += 4) {
        $start = ord($phrase_string[$i+2]);
        $next = ord($phrase_string[$i+3]);
        if($start >= 78 && $start <= 159) {
            $guess['zh-CN']++;
        } else if ($start == 4 || ($start == 5 && $next < 48)) {
            $guess['ru']++;
        } else if ($start == 5 && $next >= 144) {
            $guess['he']++;
        } else if ($start >= 6 && $start <= 7) {
            $guess['ar']++;
        } else if ($start == 14 && $next < 128) {
            $guess['th']++;
        } else if ($start >= 48 && $start <= 49) {
            $guess['ja']++;
        } else if ($start == 17 || $start >= 172 && $start < 215) {
            $guess['ko']++;
        }
    }
    $num_points = $len/4 - 2; //there will be a lead and tail space
    if($num_points > 0 ) {
        foreach($guess as $tag => $cnt) {
            if($factor*$cnt >= $num_points) {
                $locale_tag = $tag;
                break;
            }
        }
    }
    return $locale_tag;
}
/**
 * Tries to guess at a language tag based on the name of a character
 * encoding
 *
 *  @param string $encoding a character encoding name
 *
 *  @return string guessed language tag
 */
function guessLangEncoding($encoding)
{
    $lang = array("EUC-JP", "Shift_JIS", "JIS", "ISO-2022-JP");
    if(in_array($encoding, $lang)) {
        return "ja";
    }
    $lang = array("EUC-CN", "GBK", "GB2312", "EUC-TW", "HZ", "CP936", 
        "BIG-5", "CP950");
    if(in_array($encoding, $lang)) {
        return "zh-CN";
    }
    $lang = array("EUC-KR", "UHC", "CP949", "ISO-2022-KR");
    if(in_array($encoding, $lang)) {
        return "ko";
    }
    $lang = array("Windows-1251", "CP1251", "CP866", "IBM866", "KOI8-R");
    if(in_array($encoding, $lang)) {
        return "ru";
    }

    return 'en';
}


/**
 * Translate the supplied arguments into the current locale.
 * This function takes a variable number of arguments. The first
 * being an identifier to translate. Additional arguments
 * are used to interpolate values in for %s's in the translation.
 *
 * @param string string_identifier  identifier to be translated
 * @param mixed additional_args  used for interpolation in translated string
 * @return string  translated string
 */
function tl()
{
    global $locale;

    $args = func_get_args();

    $translation = $locale->translate($args);
    if($translation == "") {
        $translation = $args[0];
    }
    return $translation;
}

/**
 * Sets the language to be used for locale settings
 *
 * @param string $locale_tag the tag of the language to use to determine
 *      locale settings
 */
function setLocaleObject($locale_tag)
{
    global $locale;
    $locale = new LocaleModel();
    $locale->initialize($locale_tag);
}

/**
 * Gets the language tag (for instance, en_US for American English) of the
 * locale that is currently being used.
 *
 * @return string  the tag of the language currently being used for locale
 *      settings
 */
if(!function_exists("getLocaleTag")) {
function getLocaleTag()
{
    global $locale;
    return $locale->getLocaleTag();
}
}

/**
 * Returns the current language directions.
 *
 * @return string ltr or rtl depending on if the language is left-to-right
 * or right-to-left
 */
function getLocaleDirection()
{
    global $locale;
    return $locale->getLocaleDirection();
}

/**
 * Returns the query statistics info for the current llocalt.
 *
 * @return array consisting of queries and elapses times for locale computations
 */
function getLocaleQueryStatistics()
{
    global $locale;
    $query_info = array();
    $query_info['QUERY_LOG'] = $locale->db->query_log;
    $query_info['TOTAL_ELAPSED_TIME'] = $locale->db->total_time;
    return $query_info;
}


/**
 * Returns the current locales method of writing blocks (things like divs or
 * paragraphs).A language like English puts blocks one after another from the
 * top of the page to the bottom. Other languages like classical Chinese list
 * them from right to left.
 *
 *  @return string  tb lr rl depending on the current locales block progression
 */
function getBlockProgression()
{
    global $locale;
    return $locale->getBlockProgression();

}

/**
 * Returns the writing mode of the current locale. This is a combination of the
 * locale direction and the block progression. For instance, for English the
 * writing mode is lr-tb (left-to-right top-to-bottom).
 *
 *  @return string   the locales writing mode
 */
function getWritingMode()
{
    global $locale;
    return $locale->getWritingMode();

}
?>
