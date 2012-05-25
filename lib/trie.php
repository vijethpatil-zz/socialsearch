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
 * @author Sandhya Vissapragada, Chris Pollett (separated out this
 *      code into a separate file and cleaned up)
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

/**
 * Implements a trie data structure which can be used to store terms read
 * from a dictionary in a succinct way
 *
 * @author Sandhya Vissapragada, Chris Pollett (rewrite +
 *      documentation, multi-byte support)
 *
 * @package seek_quarry
 * @subpackage library
 */
class Trie 
{
    /**
     * A nested array used to represent the trie
     * @var array
     */
    var $trie_array;
    /**
     * The marker used to represent the end of an entry in a trie
     * @var string
     */
    var $end_marker;

    /**
     * Creates and returnes an empty trie. Sets the end of term character
     * 
     * @param string $end_marker end of term marker
     */
    function __construct($end_marker = " ")
    {
        $this->trie_array = array();
        $this->end_marker = $end_marker;
    }

    /**
     * Adds a term to the Trie
     *
     * @param string $term the term to be inserted
     * @return array $trie_array benath last letter of term inserted
     */
    function add($term) 
    {
        $trie_array = & $this->trie_array;
        for($i = 0; $i < mb_strlen($term); $i++) {
            $character = mb_substr($term, $i, 1);
            // If letter doesnt exist then create one by
            // assigning new array
            $enc_char = urlencode($character);
            if(!isset($trie_array[$enc_char])) {
                $trie_array[$enc_char] = array();
            }
            $trie_array = & $trie_array[$enc_char];
        }
        // Set end of term marker
        $trie_array[$this->end_marker] = $this->end_marker; 
        return $trie_array;
    }

    /**
     * Returns the sub trie_array under $term in
     * $this->trie_array. If $term does not exist in $trie->trie_array
     * returns false
     *
     * @param string $term term to look up
     * @return array $trie_array subtrie under term
     */
    function exists($term)
    {
        $trie_array = & $this->trie_array;
        $len = mb_strlen($term);
        for($i = 0; $i < $len; $i++) {
            if($trie_array == null){
                return false;
            }
            if ($trie_array != $this->end_marker) {
                $character = mb_substr($term, $i, 1);
                $enc_char = urlencode($character);
                if(!isset($trie_array[$enc_char])) {
                    return false;
                }
                if($trie_array[$enc_char] != $this->end_marker) { 
                    $trie_array = & $trie_array[$enc_char];
                }
            }
            else {
                return false;
            }
        } 
        return $trie_array;
    }

    /**
     * Returns all the terms in the trie beneath the provided term prefix
     *
     * @param string $prefix of term to look up
     * @return array $terms under $prefix
     */
    function getValues($prefix, $max_results)
    {
        $trie_array = $this->exists($prefix);
        if(!$trie_array) return false;

        return $this->getValuesTrieArray($trie_array, $prefix, $max_results);
    }

    /**
     * Computes the suffixes $count,...$max_results-$count in the trie_array 
     * beneath the provided $find_more is true. Prepends $prefix to each
     * and returns the array of the result.
     *
     * @param array $trie_array a nested array representing a trie to look
     *      up suffixes in
     * @param string $prefix to prepend to each found suffix
     * @param int $max_results maximum number of strings to return
     * @param int $count which suffix in trie_array to start with
     * @param bool $find_more whether to try to look up or not (stops recursion)
     * @return array $terms a list of ($prefix.suffix1, $prefix, $suffix2,...)
     */
    private function getValuesTrieArray($trie_array, $prefix, $max_results, 
        &$count = 0, &$find_more = true)
    {
        $end_marker = $this->end_marker;
        $terms = array();
        if ($trie_array != NULL && $find_more) {
            foreach ($trie_array as $character => $subtrie) {
                if ($character != $end_marker) {
                    $new_terms =
                        $this->getValuesTrieArray($subtrie, 
                            $prefix . urldecode($character), 
                            $max_results, $count, $find_more);
                    $terms = array_merge($terms, $new_terms);
                } else {
                    $count++;
                    if($count > $max_results) {
                        $find_more = false;
                    }
                    $terms[] = $prefix;
                }
            }
        }
        return $terms;
    }
}
?>
