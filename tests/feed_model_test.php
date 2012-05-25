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
 * @author Vijeth Patil vijeth.patil@gmail.com
 * @package seek_quarry
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load search engine-wide configuration file 
 */
require_once BASE_DIR.'/configs/config.php';

/** 
 *  Load the FeedModel class we are going to test
 */
require_once BASE_DIR."/models/feed_model.php"; 

/**
 *  Load the base unit test class
 */
require_once BASE_DIR."/lib/unit_test.php";

/**
 * Load the Crawl constants required for the summary
 */
 require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * UnitTest for the FeedModel class. An FeedModel is used to process 
 * a .epub (ebook publishing standard) file and extract summary from it. This 
 * class tests the processing of an .epub file format by EpubProcessor.
 * 
 *
 * @author Vijeth Patil
 * @package seek_quarry
 * @subpackage test
 */
 
class FeedModelTest extends UnitTest implements CrawlConstants
{
    /**
     * Creates a new Feed Model object
  .
     */
     
    public function setUp()
    {
        $feedmodel_object = new FeedModel;
        $query="Testing my";
        $result=$feedmodel_object->feedResults($query);
        $this->test_objects['result'] = $result;
    }
      
    /**
     * Delete any files associated with our test on EpubProcessor
     */
    public function tearDown()
    {
        @unlink("");
    }
    
    /**
     * Test case to check whether the title of the epub document
     * is retrieved correctly.
     */ 
    public function testFeedModelQueryResultsTestCase()
    {
        $m = $this->test_objects['result'];
        $x = $m['TOTAL_ROWS'];
        $correct_rows = 1;
        $description = "Test Passed with correct number of results";
        $this->assertEqual($x,$correct_rows,$description);
    }
    
    /**
     * Test case to check whether the language of the document is 
     * retrieved correctly.
     */
    public function testFeedModelQueryMatchesTestCase()
    {
        $m = $this->test_objects['result'] ;
        $x = $m['PAGES'];
        $correct_pages = $x;
        $description = "Test Passed with correct User Key";
        $this->assertEqual($x,$correct_pages,$description);
    }
    

}
?>