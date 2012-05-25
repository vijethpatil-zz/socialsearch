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
 * A Compressor is used to apply a filter to objects before they are stored 
 * into a WebArchive. The filter is assumed to be invertible, and the typical 
 * intention is the filter carries out some kind of string compression.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */ 
 
interface Compressor
{
    /**
     * Applies the Compressor compress filter to a string before it is 
     * inserted into a WebArchive.
     *
     * @param string $str  string to apply filter to
     * @return string  the result of applying the filter
     */
    function compress($str);
    
    /**
     * Used to unapply the compress filter as when data is read out of a 
     * WebArchive.
     *
     * @param string $str  data read from a string archive
     * @return string result of uncompressing
     */
    function uncompress($str);

    /**
     * Used to compress an int as a fixed length string in the format of
     * the compression algorithm underlying the compressor.
     *
     * @param int $my_int the integer to compress as a fixed length string
     * @return string the fixed length string containing the packed int
     */
    function compressInt($my_int);

    /**
     * Used to uncompress an int from a fixed length string in the format of
     * the compression algorithm underlying the compressor.
     *
     * @param string $my_compressed_int the fixed length string containing 
     *      the packed int to extract
     * @return int the integer contained in that string
     */
    function uncompressInt($my_compressed_int);

    /**
     * Computes the length of an int when packed using the underlying
     * compression algorithm as a fixed length string
     * @return int length of int as a fixed length compressed string
     */
    function compressedIntLen();

    /**
     * File extension that should be associated with this compressor
     * @return string name of dos file extension
     */
    static function fileExtension();
} 
?>
