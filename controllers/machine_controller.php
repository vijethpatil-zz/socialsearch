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
/** Loads crawl_daemon to get status on running daemons*/
require_once BASE_DIR."/lib/crawl_daemon.php";

/**
 * This class handles requests from a computer that is managing several
 * fetchers and queue_servers. This controller might be used to start, stop
 * fetchers/queue_server as well as get status on the active fetchers
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class MachineController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array();
    /**
     * Only outputs JSON data so don't need view
     * @var array
     */
    var $views = array();
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("statuses", "update", "log");

    /**
     * Number of characters from end of most recent log file to return
     * on a log request
     */
    const LOG_LISTING_LEN = 100000;
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
     * Checks the running/non-running status of the 
     * fetchers and queue_servers of the current Yioop instance
     */
    function statuses()
    {
        echo json_encode(CrawlDaemon::statuses());
    }

    /**
     * Used to start/stop a queue_server/fetcher of the current Yioop instance
     * based on the queue_server and fetcher fields of the current $_REQUEST
     */
    function update()
    {
        $statuses = CrawlDaemon::statuses();

        if(isset($_REQUEST['queue_server'])) {
            if($_REQUEST['queue_server'] == "true" && 
                !isset($statuses["queue_server"][-1])) {
                CrawlDaemon::start("queue_server", 'none', self::INDEXER,false);
                CrawlDaemon::start("queue_server", 'none', self::SCHEDULER);
            } else if($_REQUEST['queue_server'] == "false" && 
                isset($statuses["queue_server"][-1]) ) {
                CrawlDaemon::stop("queue_server");
            }
        }

        if(isset($_REQUEST['mirror'])) {
            if($_REQUEST['mirror'] == "true" && 
                !isset($statuses["mirror"][-1])) {
                CrawlDaemon::start("mirror");
            } else if($_REQUEST['mirror'] == "false" && 
                isset($statuses["mirror"][-1]) ) {
                CrawlDaemon::stop("mirror");
            }
        }

        if(isset($_REQUEST['fetcher']) && is_array($_REQUEST['fetcher'])) {
            foreach($_REQUEST['fetcher'] as $index => $value) {
                if($value == "true" && !isset($statuses["fetcher"][$index]) ) {
                    CrawlDaemon::start("fetcher", "$index");
                } else if($value == "false" && 
                    isset($statuses["fetcher"][$index]) ) {
                    CrawlDaemon::stop("fetcher", "$index");
                }
            }
        }
    }

    /**
     *  Used to retrieve a fetcher/queue_server logfile for the the current
     *  Yioop instance
     */
    function log()
    {
        $log_data = "";
        if(isset($_REQUEST["fetcher_num"])) {
            $fetcher_num = $this->clean($_REQUEST["fetcher_num"], "int");
            $log_file_name = LOG_DIR . "/{$fetcher_num}-fetcher.log";
        }  else if(isset($_REQUEST["mirror"])) {
            $log_file_name = LOG_DIR . "/mirror.log";
        } else {
            $log_file_name = LOG_DIR . "/queue_server.log";
        }
        if(file_exists($log_file_name)) {
            $size = filesize($log_file_name);
            $len = min(self::LOG_LISTING_LEN, $size);
            $fh = fopen($log_file_name, "r");
            if($fh) {
                fseek($fh, $size - $len);
                $log_data = fread($fh, $len);
                fclose($fh);
            }
        }
        echo json_encode(urlencode($log_data));
    }


}
?>
