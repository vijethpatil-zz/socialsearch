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
 * Web page used to display information about the crawler component of
 * the SeekQuarry/Yioop Search engine
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage static
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

/** Calculate base directory of script
 *  @ignore
 */
$pathinfo = pathinfo($_SERVER['SCRIPT_FILENAME']);
if(!defined('BASE_DIR')) {
    define("BASE_DIR", $pathinfo["dirname"].'/');
}
/**
 * Load the configuration file
 */
require_once(BASE_DIR.'configs/config.php');
/** 
 * Used to set-up static error pages
 */
require_once(BASE_DIR."/controllers/static_controller.php");
/**
 * Load global functions related to localization
 */
require_once BASE_DIR."/lib/locale_functions.php";

mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

$locale_tag = guessLocale();

$locale = NULL;
setLocaleObject($locale_tag);

if(!isset($_REQUEST['p']) ||
    !in_array($_REQUEST['p'], array("404", "409"))) {
    $_REQUEST['p'] = "404";
}
switch($_REQUEST['p'])
{
    case "404":
        header("HTTP/1.0 404 Not Found");
    break;
    case "409":
        header("HTTP/1.0 409 Conflict");
    break;
}

$controller = new StaticController();

$controller->processRequest();

/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
if(!function_exists("e")) {
    function e($text)
    {
        echo $text;
    }
}
?>
