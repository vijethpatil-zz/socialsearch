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
 *  Load the EpubProcessor class we are going to test
 */
require_once BASE_DIR."/lib/processors/epub_processor.php"; 

/**
 *  Load the base unit test class
 */
require_once BASE_DIR."/lib/unit_test.php";

/**
 * Load the Crawl constants required for the summary
 */
 require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * UnitTest for the EpubProcessor class. An EpubProcessor is used to process 
 * a .epub (ebook publishing standard) file and extract summary from it. This 
 * class tests the processing of an .epub file format by EpubProcessor.
 * 
 *
 * @author Vijeth Patil
 * @package seek_quarry
 * @subpackage test
 */
 
class EpubProcessorTest extends UnitTest implements CrawlConstants
{
    /**
     * Creates a new EpubProcessor object so that
     * we can process an .epub format file.
     */
     
    public function setUp()
    {
        $epub_object = new EpubProcessor;
        $url = "http://www.vijethpatil.com/TestandTextbookforyioop.epub";
        $filename= BASE_DIR."/tests/test_files/TestandTextbookforyioop.epub";
        $page = file_get_contents($filename);
        $summary=$epub_object->process($page,$url);
        $this->test_objects['summary'] = $summary;
        $this->testEpubTitleTestCase();
        $this->testEpubLangTestCase();
        $this->testEpubDescriptionTestCase();
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
    public function testEpubTitleTestCase()
    {
        $m = $this->test_objects['summary'];
        $x = $m[self::TITLE];
        $correct_title = "The Test and Textbook for yioop";
        $description = "Test Passed with correct title";
        $this->assertEqual($x,$correct_title,$description);
    }
    
    /**
     * Test case to check whether the language of the document is 
     * retrieved correctly.
     */
    public function testEpubLangTestCase()
    {
        $m = $this->test_objects['summary'] ;
        $x = $m[self::LANG];
        $correct_language = "en";
        $description = "Test Passed with correct Language";
        $this->assertEqual($x,$correct_language,$description);
    }
    
    /**
     * Test case to check whether the description of the document is 
     * not empty.
     */
    public function testEpubDescriptionTestCase()
    {
        $m = $this->test_objects['summary'] ;
        $x = $m[self::DESCRIPTION];
        $description = "Test Passed with Description information not empty";
        $this->assertTrue($x,$description);
    }
}
