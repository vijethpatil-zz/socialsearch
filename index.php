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
 * Main web interface entry point for Yioop!
 * search site. Used to both get and display
 * search results. Also used for inter-machine
 * communication during crawling
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

/** Calculate base directory of script
 *  @ignore
 */
$pathinfo = pathinfo($_SERVER['SCRIPT_FILENAME']);
define("BASE_DIR", $pathinfo["dirname"].'/');

/**
 * Load the configuration file
 */
require_once(BASE_DIR.'configs/config.php');
ini_set("memory_limit","500M");
header("X-FRAME-OPTIONS: DENY"); //prevent click-jacking
session_name(SESSION_NAME);
session_start();
/**
 * Sets up DB to be used
 */
require_once(BASE_DIR."/models/datasources/".DBMS."_manager.php");
/**
 * Load global functions related to localization
 */
require_once BASE_DIR."/lib/locale_functions.php";

/**
 * Load global functions related to checking Yioop! version
 */
require_once BASE_DIR."/lib/upgrade_functions.php";
 
/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");

if(USE_MEMCACHE) {
    $CACHE = new Memcache();
    foreach($MEMCACHES as $mc) {
        $CACHE->addServer($mc['host'], $mc['port']);
    }
    unset($mc);
    define("USE_CACHE", true);
} else if (USE_FILECACHE) {
    $CACHE = new FileCache(WORK_DIRECTORY."/cache/queries");
    define("USE_CACHE", true);
} else {
    define("USE_CACHE", false);
}

mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

if ( false === function_exists('lcfirst') ) {
    /**
     *  Lower cases the first letter in a string
     *
     *  This function is only defined if the PHP version is before 5.3
     *  @param string $str  string to be lower cased
     *  @return string the lower cased string
     */
    function lcfirst( $str )
    { return (string)(strtolower(substr($str,0,1)).substr($str,1));}
}

$available_controllers = array( "admin", "archive",  "cache", "crawl",
    "fetch",  "machine", "resource", "search", "settings", "statistics",
    "static",);
if(!WEB_ACCESS) {
$available_controllers = array("admin", "archive", "cache", "crawl", "fetch",
     "machine");
}

//the request variable c is used to determine the controller
if(!isset($_REQUEST['c'])) {
    $controller_name = "search";
} else {
   $controller_name = $_REQUEST['c'];
}

if(!checkAllowedController($controller_name))
{
    if(WEB_ACCESS) {
        $controller_name = "search";
    } else {
        $controller_name = "admin";
    }
}

// if no profile exists we force the page to be the configuration page
if(!PROFILE ) {
    $controller_name = "admin";
}

//check if mobile css and formatting should be used or not
$agent = $_SERVER['HTTP_USER_AGENT'];
$is_admin = strcmp($controller_name, "admin") == 0;
if((stristr($agent, "mobile") || stristr($agent, "fennec")) && 
    !stristr($agent, "ipad") ) {
    define("MOBILE", true);
} else {
    define("MOBILE", false);
}

$locale_tag = guessLocale();

if(upgradeDatabaseWorkDirectoryCheck()) {
    upgradeDatabaseWorkDirectory();
}

if(upgradeLocaleCheck()) {
    upgradeLocale();
}

$locale = NULL;
setLocaleObject($locale_tag);

if(file_exists(APP_DIR."/index.php")) {
    require_once(APP_DIR."/index.php");
}

/**
 * Loads controller responsible for calculating
 * the data needed to render the scene
 *
 */
if(file_exists(APP_DIR."/controllers/".$controller_name."_controller.php")) {
    require_once(APP_DIR."/controllers/".$controller_name."_controller.php");
} else {
    require_once(BASE_DIR."/controllers/".$controller_name."_controller.php");
}
$controller_class = ucfirst($controller_name)."Controller";
$controller = new $controller_class($INDEXING_PLUGINS);

$controller->processRequest();

/**
 * Verifies that the supplied controller string is a controller for the
 * SeekQuarry app
 *
 * @param string $controller_name  name of controller
 *      (this usually come from the query string)
 * @return bool  whether it is a valid controller
 */
function checkAllowedController($controller_name)
{
    global $available_controllers;

    return in_array($controller_name, $available_controllers) ;
}

/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}

?>
