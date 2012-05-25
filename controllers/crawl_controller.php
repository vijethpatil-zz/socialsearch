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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Load base controller class if needed */
require_once BASE_DIR."/controllers/controller.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/** Need getHost to partition urls to different queue_servers*/
require_once BASE_DIR."/lib/url_parser.php";


/**
 * Controller used to manage networked installations of Yioop where
 * there might be mutliple queue_servers and a name_server. Command
 * sent to the nameserver web page are mapped out to queue_servers
 * using this controller. Each method of the controller essentially
 * mimics one method of CrawlModel and is used to proxy that information
 * through a result web page back to the name_server.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class CrawlController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array("crawl");
    /**
     * Only outputs serialized php data so don't need view
     * @var array
     */
    var $views = array();
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("sendStartCrawlMessage", "sendStopCrawlMessage", 
        "crawlStalled", "crawlStatus", "deleteCrawl", "injectUrlsCurrentCrawl",
        "getCrawlList", "combinedCrawlInfo", "getInfoTimestamp",
        "getCrawlSeedInfo", "setCrawlSeedInfo", "getCrawlItem", "countWords");

    /**
     * Checks that the request seems to be coming from a legitimate fetcher then
     * determines which activity the fetcher is requesting and calls that
     * activity for processing.
     *
     */
    function processRequest() 
    {
        $data = array();

        /* do a quick test to see if this is a request seems like 
           from a legitimate machine
         */
        if(!$this->checkRequest()) {return; }

        $activity = $_REQUEST['a'];
        if(in_array($activity, $this->activities)) {$this->$activity();}
    }

    /**
     * Handles a request for whether or not the crawl is stalled on the
     * given local server (which means no fetcher has spoken to it in a while)
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */

    function crawlStalled()
    {
        echo webencode(serialize($this->crawlModel->crawlStalled()));
    }

    /**
     * Handles a request for the crawl status (memory use, recent fetchers
     * crawl rate, etc) data from a remote name server
     * and retrieves that the statistic about this that are held by the 
     * local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function crawlStatus()
    {
        echo webencode(serialize($this->crawlModel->crawlStatus()));
    }

    /**
     * Handles a request for the starting parameters of a crawl of a given
     * timestamp and retrieves that information from the bundle held by the 
     * local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function getCrawlSeedInfo()
    {
        $timestamp = 0;
        if(isset($_REQUEST["arg"]) ) {
            $timestamp = unserialize(webdecode($_REQUEST["arg"]));
        }
        echo webencode(serialize($this->crawlModel->getCrawlSeedInfo(
            $timestamp)));
    }


    /**
     * Handles a request to change the parameters of a crawl of a given
     * timestamp on the local machine (does nothing if crawl doesn't exist)
     */
    function setCrawlSeedInfo()
    {
        if(isset($_REQUEST["arg"]) ) {
            list($timestamp, $info) = unserialize(webdecode($_REQUEST["arg"]));
            if($timestamp && $info) {
                $this->crawlModel->getCrawlSeedInfo($timestamp, $info);
            }
        }
    }

    /**
     * Handles a request for information about a crawl with a given timestamp
     * from a remote name server and retrieves statistics about this crawl
     * that are held by the local queue server (number of pages, name, etc)
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function getInfoTimestamp()
    {
        $timestamp = 0;
        if(isset($_REQUEST["arg"]) ) {
            $timestamp = unserialize(webdecode($_REQUEST["arg"]));
        }
        echo webencode(serialize($this->crawlModel->getInfoTimestamp(
            $timestamp)));
    }

    /**
     * Handles a request for the crawl list (what crawl are stored on the 
     * machine) data from a remote name server and retrieves the 
     * statistic about this that are held by the local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function getCrawlList()
    {
        $return_arc_bundles = false;
        $return_recrawls = false;
        if(isset($_REQUEST["arg"]) ) {
            $arg = $this->clean($_REQUEST["arg"], "int");
            if($arg == 3 || $arg == 1) {$return_arc_bundles = true; }
            if($arg == 3 || $arg == 2) {$return_recrawls = true; }
        }
        echo webencode(serialize($this->crawlModel->getCrawlList(
            $return_arc_bundles, $return_recrawls)));
    }

    /**
     * Handles a request for the combined crawl list, stalled, and status
     * data from a remote name server and retrieves that the statistic about
     * this that are held by the local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function combinedCrawlInfo()
    {
        $combined =  $this->crawlModel->combinedCrawlInfo();
        echo webencode(serialize($combined));
    }

    /**
     * Receives a request to delete a crawl from a remote name server
     * and then deletes crawl on the local queue server
     */
    function deleteCrawl()
    {
        if(!isset($_REQUEST["arg"]) ) {
            return;
        }
        $timestamp = unserialize(webdecode($_REQUEST["arg"]));
        $this->crawlModel->deleteCrawl($timestamp);
    }

    /**
     * Receives a request to inject new urls into the active 
     * crawl from a remote name server and then does this for
     * the local queue server
     */
    function injectUrlsCurrentCrawl()
    {
        if(!isset($_REQUEST["arg"]) || !isset($_REQUEST["num"])
            || !isset($_REQUEST["i"])) {
            return;
        }
        $num = $this->clean($_REQUEST["num"], "int");
        $i = $this->clean($_REQUEST["i"], "int");
        list($timestamp, $inject_urls) = 
            unserialize(webdecode($_REQUEST["arg"]));
        $inject_urls = partitionByHash($inject_urls,
            NULL, $num, $i, "UrlParser::getHost");
        $this->crawlModel->injectUrlsCurrentCrawl($timestamp, 
            $inject_urls, NULL);
    }

    /**
     * Receives a request to get crawl summary data for a url
     * from a remote name server and then looks it up on the local queue server
     */
     function getCrawlItem()
     {
        if(!isset($_REQUEST["arg"]) ) {
            return;
        }
        list($url, $index_name) = unserialize(webdecode($_REQUEST["arg"]));
        $this->crawlModel->index_name = $index_name;
        echo webencode(serialize(
            $this->crawlModel->getCrawlItem($url)));
     }

    /**
     * Receives a request to get counts of the number of occurrences of an
     * array of words a remote name server and then 
     * determines and outputs these counts for the local queue server
     */
     function countWords()
     {
        if(!isset($_REQUEST["arg"]) ) {
            return;
        }
        list($words, $index_name) = unserialize(webdecode($_REQUEST["arg"]));
        $this->crawlModel->index_name = $index_name;
        echo webencode(serialize(
            $this->crawlModel->countWords($words)));
     }

    /**
     * Receives a request to stop a crawl from a remote name server
     * and then stop the current crawl on the local queue server
     */
    function sendStopCrawlMessage()
    {
        $this->crawlModel->sendStopCrawlMessage();
    }


    /**
     * Receives a request to start a crawl from a remote name server
     * and then starts the crawl process on the local queue server
     */
    function sendStartCrawlMessage()
    {
        if(!isset($_REQUEST["arg"]) || !isset($_REQUEST["num"])
            || !isset($_REQUEST["i"])) {
            return;
        }
        $num = $this->clean($_REQUEST["num"], "int");
        $i = $this->clean($_REQUEST["i"], "int");
        list($crawl_params, 
            $seed_info) = unserialize(webdecode($_REQUEST["arg"]));
        $seed_info['seed_sites']['url'] = 
            partitionByHash($seed_info['seed_sites']['url'],
            NULL, $num, $i, "UrlParser::getHost");
        $this->crawlModel->sendStartCrawlMessage($crawl_params, $seed_info, 
            NULL);
    }


}
?>
