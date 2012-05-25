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
 * @author Tarun Ramaswamy tarun.pepira@gmail.com
 * @package seek_quarry
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load in the xlsx_processor class we are testing
 *
 */
require_once BASE_DIR."/lib/processors/xlsx_processor.php";

/**
 * Used to test that the XlsxProcessor class provides the basic functionality 
 * of getting the tile, description, languages and links
 *
 * @author Tarun Ramaswamy
 * @package seek_quarry
 * @subpackage test
 */
class XlsxProcessorTest extends UnitTest implements CrawlConstants
{
    /**
     * sets up the initial content for the testcase by extracting
     * it from the xlsx file
     */
    public function setUp()
    {
        $file_name="test_files/test.xlsx";
        $page=file_get_contents($file_name);
        $xlsx_processor = new XlsxProcessor();
        $summary = NULL;
        $url = "http://localhost:80/";
        $summary = $xlsx_processor->process($page,$url);
        $this->test_objects['title'] = $summary[self::TITLE];
        $this->test_objects['description'] = $summary[self::DESCRIPTION];
        $this->test_objects['language'] = $summary[self::LANG];
        $this->test_objects['links'] = $summary[self::LINKS];
    }

    /** 
     * Can be used for clenup activity
     */
    public function tearDown()
    {
        
    }

    /**
     * Tests that the title is correct 
     */
    public function titleTestCase()
    {

        $title = "SampleTitle";
        
        $this->assertEqual($this->test_objects['title'], $title,
            "check for title");
    }

    /**
     * Tests that the description is correct 
     */
    public function descriptionTestCase()
    {

        $description = "This is a sample descriptionlink1link2";
        
        $this->assertEqual($this->test_objects['description'], $description,
            "check for description");
    }
    
    /**
     * Tests that the language is correct 
     */
    public function languageTestCase()
    {

        $language = "en";
        
        $this->assertEqual($this->test_objects['language'], $language,
            "check for language");
    }
    
    /**
     * Tests that the links are correct 
     */
    public function linksTestCase()
    {

        $sites = array();
        $sites[0] = "http://www.yahoo.com/";
        $sites[1] = "http://www.google.com/";
        $i = 0;
        foreach($this->test_objects['links'] as $link) {
            $this->assertEqual($link, $sites[$i], "check for Links");
            $i++;
        }
    }
}
?>
