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

/** Load search engine-wide configuration file */
require_once BASE_DIR.'/configs/config.php';

/**   Loads the WebArchive class we are going to test */
require_once BASE_DIR."/lib/web_archive.php"; 

/**
 *  A compressor determines how objects are written into a web_archive.
 *  This require loads a gzip-based compressor.
 */
require_once BASE_DIR."/lib/compressors/gzip_compressor.php"; 

/**
 * UnitTest for the WebArchive class. A web archive is used to store 
 * array-based objects persistently to a file. This class tests storing and 
 * retreiving from such an archive.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage test
 */
class WebArchiveTest extends UnitTest
{
    /**
     *  Creates a new web archive object that we can add objects to
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] = 
            new WebArchive("ar1.sqwa", new GzipCompressor());
    }

    /**
     * Delete any files associated with out test web archive
     */
    public function tearDown()
    {
        @unlink("ar1.sqwa");
    }

    /**
     * Inserts three objects into a web archive. To look up an object in a web 
     * archive we need to know its byte offset into the archive file. This test 
     * looks that after the inserts we get back an array of byte offsets and 
     * that the byte offsets are of increasing size
     */
    public function addObjectTestCase()
    {
        $items = 
            array(array("hello"), array("how are you"), array("good thanks"));
        $objects = $this->test_objects['FILE1']->addObjects("offset",  $items);
        $this->assertTrue(
            isset($objects[0]['offset']), "First insert got offset into file");
        $this->assertTrue(
            isset($objects[1]['offset']), "Second insert got offset into file");
        $this->assertTrue(
            isset($objects[2]['offset']), "Third insert got offset into file");
        $offset_flag = $objects[0]['offset'] === 0 && 
            $objects[0]['offset'] < $objects[1]['offset'] &&
            $objects[1]['offset'] < $objects[2]['offset'];
        $this->assertTrue($offset_flag, 
            "First offset into archive is zero and ".
            "later ones are strictly increasing");

    }

    /**
     * Does two addObjects of three objects each. Then does a getObjects to get 
     * six object using offset 0 into the web archive. This should return the 
     * six objects just inserted
     */
    public function getObjectTestCase()
    {
        $items = 
            array(array("hello"), array("how are you"), array("good thanks"));
        $more_items = 
            array(array("he3llo"),array("how4 are you"), array("good5 thanks"));

        $objects = $this->test_objects['FILE1']->addObjects("offset",  $items);
        $new_objects = 
            $this->test_objects['FILE1']->addObjects("offset", $more_items);

        $all_items = array_merge($items, $more_items);
        $retrieved_items = $this->test_objects['FILE1']->getObjects(0,6);

        $retrieved_count = count($retrieved_items);
        $this->assertEqual(
            $retrieved_count, 6, "number of items retrieved is what asked for");

        for($i = 0; $i < $retrieved_count; $i++) {
            $this->assertEqual(
                $retrieved_items[$i][1][0], $all_items[$i][0], 
                "object $i retrieved correctly");
        }


    }

    /**
     * If the file associated with a web archive already exists when the 
     * constructor is called, then the constructor will load the existing web 
     * archive. This test case checks this functionality by adding six items to 
     * a web archive, then constructing a new WebArchive object using the same
     * file name and seeing if we can read the objects that were just inserted.
     *
     */
    public function reloadArchiveTestCase()
    {
        $items = 
            array(array("hello"), array("how are you"), array("good thanks"));
        $more_items = 
            array(array("he3llo"),array("how4 are you"), array("good5 thanks"));
        $objects = $this->test_objects['FILE1']->addObjects("offset",  $items);
        $new_objects = 
            $this->test_objects['FILE1']->addObjects("offset", $more_items);

        $this->test_objects['REF_FILE1'] = 
            new WebArchive("ar1.sqwa", new GzipCompressor());
        $this->assertEqual(
            $this->test_objects['REF_FILE1']->count, 6, 
            "Archive count is equal to number of items inserted");
    }
}
?>
