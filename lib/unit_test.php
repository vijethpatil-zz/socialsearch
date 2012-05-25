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
 * Base class for all the SeekQuarry/Yioop engine Unit tests
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage test
 */
abstract class UnitTest
{

    /**
     * Used to store the results for each test sub case
     */
    var $test_case_results;
    /**
     * Used to hold objects to be used in tests
     * @var array
     */
    var $test_objects;
    /**
     * The suffix that all TestCase methods need to have to be called by run()
     */
    const case_name = "TestCase";
    /**
     * Contructor should be overriden to do any set up that occurs before
     * and test cases
     */
    public function __construct()
    {
    }

    /**
     * Execute each of the test cases of this unit test and return the results
     * @return array test case results
     */
    public function run()
    {

        $test_results = array();
        $methods = get_class_methods(get_class($this));
        foreach ($methods as $method) {
            $this->test_objects = NULL;
            $this->setUp();
            $len = strlen($method);
            
            if(substr_compare(
                $method, self::case_name, $len - strlen(self::case_name)) == 0){
                $this->test_case_results = array();
                $this->$method();
                $test_results[$method] = $this->test_case_results;
            }
            $this->tearDown();
        }

        return $test_results;
    }

    /**
     * Checks that $x can coerced to true, the result of the
     * test is added to $this->test_case_results
     * 
     * @param mixed $x item to check
     * @param string $description information about this test subcase
     */
    public function assertTrue($x, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertTrue $description";
        if($x) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     * Checks that $x can coerced to false, the result of the
     * test is added to $this->test_case_results
     * 
     * @param mixed $x item to check
     * @param string $description information about this test subcase
     */
    public function assertFalse($x, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertFalse $description";
        if(!$x) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     * Checks that $x and $y are the same, the result of the
     * test is added to $this->test_case_results
     *
     * @param mixed $x a first item to compare
     * @param mixed $y a second item to compare
     * @param string $description information about this test subcase
     */
    public function assertEqual($x, $y, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertEqual $description";
        if($x == $y) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     * Checks that $x and $y are not the same, the result of the
     * test is added to $this->test_case_results
     *
     * @param mixed $x a first item to compare
     * @param mixed $y a second item to compare
     * @param string $description information about this test subcase
     */
    public function assertNotEqual($x, $y, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertNotEqual $description";
        if($x != $y) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     * This method is called before each test case is run to set up the
     * given test case
     */
    abstract public function setUp();

    /**
     * This method is called after each test case is run to clean up
     */
    abstract public function tearDown();

}
?>
