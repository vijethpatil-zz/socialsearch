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
 * Load base class, if needed
 */
require_once BASE_DIR."/lib/processors/text_processor.php";


/**
 * Used to create crawl summary information 
 * for PPT files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class PptProcessor extends TextProcessor
{
    const PPT_IGNORING = 0;
    const ZEROONE_IGNORING = 1;
    const ZEROTWO_IGNORING = 2;
    const FIRST_CHAR_TEXT_SEG = 3;
    const READ_LEN_TEXT_SEG = 4;
    const SCAN_TEXT_SEG = 5;
    const ALWAYS_IGNORE = 6;

    /**
     * Computes a summary based on a string of a binary Powerpoint document
     * (as opposed to the modern xml powerpoint format).
     *
     * Text is extracted from the Powerpoint document using a crude finite
     * state machine that was developed by looking at a few Powerpoint
     * documents in a Hex editor. Then the TextProcessor:: process() method
     * is used to make a summary
     *
     * @param string $page string of a Powerpoint document
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
        $text = "";
        if(is_string($page)) {

            $text_objects = array();
            $cur_id = 0;

            $state = self::PPT_IGNORING;

            $cur_char_pos = 0;
            $len = strlen($page);

            while ($cur_char_pos < $len) {
                $ascii = ord($page[$cur_char_pos]);
                switch($state)
                {
                    case self::PPT_IGNORING:
                        if($ascii == 0) {
                            $state = self::ZEROONE_IGNORING;
                        }
                    break;

                    case self::ZEROONE_IGNORING:
                        if($ascii == 0) {
                            $state = self::ZEROTWO_IGNORING;
                        } else {
                             $state = self::PPT_IGNORING;
                        }
                    break;

                    case self::ZEROTWO_IGNORING:
                        if($ascii == 168) {
                            $state = self::FIRST_CHAR_TEXT_SEG;
                        } else if ($ascii != 0) {
                            $state = self::PPT_IGNORING;
                        }
                    break;

                    case self::FIRST_CHAR_TEXT_SEG:
                        if($ascii == 15) {
                            $state = self::READ_LEN_TEXT_SEG;
                            $text_len = 0;
                            $text_len_pos = 0;
                        } else {
                            $state = self::PPT_IGNORING;
                        }
                    break;

                    case self::READ_LEN_TEXT_SEG:
                        if($text_len_pos < 4) {
                            $text_len += ($ascii << ($text_len_pos * 8)); 
                            $text_len_pos++;
                        } else {
                            $state = self::SCAN_TEXT_SEG;
                            $scan_text_pos = 0;
                            $out_text = chr($ascii);
                        }
                    break;
                     
                    case self::SCAN_TEXT_SEG:
                        if(strpos($out_text, 
                            "lick to edit Master title style") > 0) {
                            $state = self::ALWAYS_IGNORE;
                        } else if($scan_text_pos < $text_len) {
                            if(($ascii >= 32 &&  $ascii <= 126) ||
                                $ascii == 10) {
                                $out_text .= chr($ascii);
                                $scan_text_pos++;
                             }
                        } else {
                            $text_objects[$cur_id] = $out_text;
                            $cur_id++;
                            $state = self::PPT_IGNORING; 
                        }
                    break;

                    case self::ALWAYS_IGNORE:
                    break;

                }

                $cur_char_pos++;
            }

            $text = implode("\n", $text_objects);


        }

        if($text == "") {
            $text = $url;
        }

        $summary = parent::process($text, $url);

        return $summary;
    }

}

?>
