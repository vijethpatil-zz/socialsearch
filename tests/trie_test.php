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
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load the Trie class we'll be testing
 */
require_once BASE_DIR."/lib/trie.php"; 
/**
 *  Load the crawlHash function
 */
require_once BASE_DIR.'/lib/utility.php'; 

/**
 *  Used to test that the Trie class properly stores words that
 *  could be used for an autosuggest dictionary
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class TrieTest extends UnitTest
{
    /**
     * We'll set up one Trie for testing purpose
     */
    public function setUp()
    {
        $this->test_objects["TRIE"] = new Trie();
    }

    /**
     * Since a Trie is not a PersistentStructure we don't need to do
     * anything to tear it down
     */
    public function tearDown()
    {

    }

    /**
     * Check if we add something into our Trie, add returns the correct
     * sub-tree or false if does not exists
     */
    public function addTestCase()
    {
        $this->assertNotEqual(
            $this->test_objects['TRIE']->add(
                "hello"), false, "Successful add should not return false");
        $this->assertEqual(serialize($this->test_objects['TRIE']->add(
                "hell")), serialize(array("o" => array(" " => " "), 
                    " " => " ")), 
                "Add subsequence of an existing string should return subtree");
    }

    /**
     * Check if we look up something in our Trie, that correct subtree
     * is returned or false if does not exists
     */
    public function existsTestCase()
    {
        $this->test_objects['TRIE']->add("hello");
        $this->test_objects['TRIE']->add("hell");
        $this->assertEqual(serialize($this->test_objects['TRIE']->exists(
                "hell")), serialize(array("o" => array(" " => " "), 
                    " " => " ")), 
                "Exists should return correct subtree");
        $this->assertFalse($this->test_objects['TRIE']->exists(
                "helmut"), "If not in Trie should get false");
    }

    /**
     * Check that if we can get all the terms from a trie that begin
     * with a given prefix
     */
    public function getValuesTestCase()
    {
        $this->test_objects['TRIE']->add("hello");
        $this->test_objects['TRIE']->add("hell");
        $this->test_objects['TRIE']->add("helmut");
        $this->test_objects['TRIE']->add("handsome");
        $this->test_objects['TRIE']->add("hen");
        $this->assertEqual($this->test_objects['TRIE']->getValues("h", 5),
            array("hello", "hell", "helmut", "hen", "handsome"), 
                "Returns first all in subtree");
    }
}
?>
