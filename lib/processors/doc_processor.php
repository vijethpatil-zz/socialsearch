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
 * Load the parent class
 */
require_once BASE_DIR."/lib/processors/text_processor.php";

/**
 * Used to create crawl summary information 
 * for binary DOC files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class DocProcessor extends TextProcessor
{
    /**
     *  Used to extract the title, description and links from
     *  a string consisting of Word Doc data (2004 or earlier).
     *
     *  @param string $page  the web-page contents
     *  @param string $url  the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return array  a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $text = "";
        if(is_string($page)) {
            $text = self::extractASCIIText($page);
        }  
        if($text == "") {
            $text = $url;
        }

        $summary = parent::process($text, $url);

        return $summary;

    }
    
    /**
     * This is the main text from Word doc extractor
     * A Word Doc consists of a FIB, Piece Table, and
     * DocumentStream. The last contains the text.
     * The piece table is supposed to be used to reconstruct
     * the order of the text from the DocumentStream and the FIB, file 
     * information block,is supposed to tell us where the piece table is.
     * I am not using any of this for now. I am just brute
     * force looking for the text which I know has to be at a page (256 byte)
     * boundary. I then go until I no longer see ASCII. So the order
     * of text extracted might be screwed up right now.
     *
     * @param string $doc string data of a 2004 or earlier Word doc
     */
    static function extractASCIIText($doc) {
        $len = strlen($doc); 

        $text = "";
        $boundary_change = 0;
        $text_state = false;
        $start_text = 0;
        $end_text = 0;
        for($i = 0; $i < $len; $i+= 256) {
             if(self::checkPageForText($doc, $i)) {
                $start_text = $i;
                break;
             }
        }

        for($i = $start_text; $i < $len; $i += 8) {
            if(self::checkAllZeros($doc, $i)) {
                 $end_text =  $i; 
                 break;
            }

            $text .= self::cleanTextBlock($doc, $i);
        }

        return $text;
    }

    /**
     * Scans document starting at given position and looking forward eight
     * character to see if these are ASCII printable or not.
     *
     * @param string $doc document to scan
     * @param int $pos position to start scanning
     * @return whether the eight next characters were ASCII printable
     */
    static function checkPageForText($doc, $pos) {;
        $is_text = true;
        for($i = 0; $i < 8; $i++) {
            $ascii = ord($doc[$pos]);
            if (!((9 <= $ascii && $ascii <= 13) || 
                (32 <= $ascii && $ascii <= 126)) ){
                $is_text = false;
                break;
            }
            $pos++;
        }

        return $is_text;
    }

    /**
     * Scans document starting at given position and looking forward eight
     * character to see if these are all \0 or not.
     *
     * @param string $doc document to scan
     * @param int $pos position to start scanning
     * @return whether the eight next characters were \0
     */
    static function checkAllZeros($doc, $pos) {
        $is_zero = true;
        for($i = 0; $i < 8; $i++) {
            $ascii = ord($doc[$pos]);
            if ($ascii != 0 ){
                $is_zero = false;
                break;
            }
            $pos++; 
        } 

        return $is_zero;
    }

    /**
     * Scans document starting at given position forward eight
     * character returning those characters which are ASCII printable
     *
     * @param string $doc document to scan
     * @param int $pos position to start scanning
     * @return substring of ASCII printable characters
     */
    static function cleanTextBlock($doc, $pos) {
        $text = "";
        for($i = 0; $i < 8; $i++) {
            if(isset($doc[$pos])) {
                $ascii = ord($doc[$pos]);
                if((9<= $ascii && $ascii <= 13) ||
                    (32<= $ascii && $ascii <= 126) ) {
                    $text .= chr($ascii);
                }
            }
            $pos++;
        }

        return $text;
    }

}

?>
