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
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */


if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

/** 
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

ini_set("memory_limit","850M"); //so have enough memory to crawl big pages

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** CRAWLING means don't try to use memcache 
 * @ignore
 */
define("NO_CACHE", true);

/** get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php"; 

/** for crawlHash and crawlLog */
require_once BASE_DIR."/lib/utility.php"; 
/** for crawlDaemon function */
require_once BASE_DIR."/lib/crawl_daemon.php"; 
/** Used to fetches web pages and info from queue server*/
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 * This class is responsible for syncing crawl archives between machines using
 *  the SeekQuarry/Yioop search engine
 *
 * Mirror periodically queries the queue server asking for a list of files that
 * have changed in its parent since the last sync time. It then proceeds to
 * download them.
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @see buildMiniInvertedIndex()
 */
class Mirror implements CrawlConstants
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    var $db;
    /**
     * Url or IP address of the name_server to get sites to crawl from
     * @var string
     */
    var $name_server;

    /**
     * Last time a sync list was obtained from master machines
     * @var string
     */
    var $last_sync;

    /**
     * Last time the machine being mirrored was notified mirror.php is still
     * running
     * @var string
     */
    var $last_notify;

    /**
     * File name where last sync time is written
     * @var string
     */
    var $last_sync_file;
 
    /**
     * Time of start of current sync
     * @var string
     */
    var $start_sync;

    /**
     * Files to download for current sync
     * @var string
     */
    var $sync_schedule;

    /**
     * Directory to sync
     * @var string
     */
    var $sync_dir;

    /**
     * Maximum number of bytes from a file to download in one go
     */
    const DOWNLOAD_RANGE = 50000000;

    /**
     * Sets up the field variables so that syncing can begin
     *
     * @param string $name_server URL or IP address of the name server
     */
    function __construct($name_server) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();

        $this->name_server = $name_server;
        $this->last_sync_file = CRAWL_DIR."/schedules/last_sync.txt";
        if(file_exists($this->last_sync_file)) {
            $this->last_sync = unserialize(
                file_get_contents($this->last_sync_file));
        } else {
            $this->last_sync = 0;
        }
        $this->start_sync = $this->last_sync;
        $this->last_notify = $this->last_sync;
        $this->sync_schedule = array();
        $this->sync_dir = CRAWL_DIR."/cache";
    }

    /**
     *  This is the function that should be called to get the mirror to start 
     *  syncing. Calls init to handle the command line arguments then enters 
     *  the syncer's main loop
     */
    function start()
    {
        global $argv;

        // To use CrawlDaemon need to declare ticks first
        declare(ticks=200);
        CrawlDaemon::init($argv, "mirror");
        crawlLog("\n\nInitialize logger..", "mirror");
        $this->loop();
    }

    /**
     * Main loop for the mirror script.
     *
     */
    function loop()
    {
        crawlLog("In Sync Loop");

        $info[self::STATUS] = self::CONTINUE_STATE;
        
        while ($info[self::STATUS] != self::STOP_STATE) {
            $syncer_message_file = CRAWL_DIR.
                "/schedules/mirror_messages.txt";
            if(file_exists($syncer_message_file)) {
                $info = unserialize(file_get_contents($syncer_message_file));
                unlink($syncer_message_file);
                if(isset($info[self::STATUS]) && 
                    $info[self::STATUS] == self::STOP_STATE) {continue;}
            }

            $info = $this->checkScheduler();

            if($info === false) {
                crawlLog("Cannot connect to queue server...".
                    " will try again in ".MIRROR_NOTIFY_FREQUENCY." seconds.");
                sleep(MIRROR_NOTIFY_FREQUENCY);
                continue;
            }

            if($info[self::STATUS] == self::NO_DATA_STATE) {
                crawlLog("No data from queue server. Sleeping...");
                sleep(MIRROR_SYNC_FREQUENCY);
                continue;
            }

            $this->copyNextSyncFile();

        } //end while

        crawlLog("Mirror shutting down!!");
    }

    /**
     * Gets status and, if done processing all other mirroring activities, 
     * gets a new list of files that have changed since the last synchronization
     * from the web app of the machine we are mirroring with.
     *
     * @return mixed array or bool. Returns false if weren't succesful in 
     *      contacting web app, otherwise, returns an array with a status
     *      and potentially a list of files ot sync
     */
    function checkScheduler() 
    {

        $info = array();

        $name_server = $this->name_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);
        $request =  
            $name_server.
            "?c=resource&time=$time&session=$session".
            "&robot_instance=".ROBOT_INSTANCE."&machine_uri=".WEB_URI.
            "&last_sync=".$this->last_sync;
        if($this->start_sync <= $this->last_sync) {
            $request .= "&a=syncList";
            $info_string = FetchUrl::getPage($request);
            if($info_string === false) {
                return false;
            }
            $this->last_notify = $time;
            $info_string = trim($info_string);

            $info = unserialize(gzuncompress(base64_decode($info_string)));
            if(isset($info[self::STATUS]) && 
                $info[self::STATUS] == self::CONTINUE_STATE) {
                $this->start_sync = time();
                $this->sync_schedule = $info[self::DATA];
                unset($info[self::DATA]);
            }
        } else {
            $info[self::STATUS] = self::CONTINUE_STATE;
            if($time - $this->last_notify > MIRROR_NOTIFY_FREQUENCY) {
                $request .= "&a=syncNotify";
                FetchUrl::getPage($request);
                $this->last_notify = $time;
                CrawlLog("Notifying master that mirror is alive..");
            }
        }
        if(count($this->sync_schedule) == 0) {
            $this->last_sync = $this->start_sync;
            $this->db->setWorldPermissionsRecursive($this->sync_dir, true);
            file_put_contents($this->last_sync_file, 
                serialize($this->last_sync));
        }

        crawlLog("  Time to check Scheduler ".(changeInMicrotime($start_time)));

        return $info; 
    }

    /**
     *  Downloads the next file from the schedule of files to download received
     *  from the web app.
     */
    function copyNextSyncFile()
    {
        $dir = $this->sync_dir;
        $name_server = $this->name_server;
        $time = time();
        $session = md5($time . AUTH_KEY);
        if(count($this->sync_schedule) <= 0) return;
        $file = array_pop($this->sync_schedule);
        crawlLog("Start syncing {$file['name']}..");
        if($file['is_dir'] ) {
            if(!file_exists("$dir/{$file['name']}")) {
                mkdir("$dir/{$file['name']}");
                crawlLog(".. {$file['name']} directory created.");
            } else {
                crawlLog(".. {$file['name']} directory exists.");
            }
        } else {
            $request = 
                "$name_server?c=resource&a=get&time=$time&session=$session".
                "&robot_instance=".ROBOT_INSTANCE."&machine_uri=".WEB_URI.
                "&last_sync=".$this->last_sync."&f=cache&n=".
                urlencode($file["name"]);
            if($file["size"] < self::DOWNLOAD_RANGE) {
                $data = FetchUrl::getPage($request);
                if($file["size"] != strlen($data)) {
                    array_push($this->sync_schedule, $file);
                    crawlLog(".. {$file['name']} error downloading, retrying.");
                    return;
                }
                file_put_contents("$dir/{$file['name']}", $data);
                crawlLog(".. {$file['name']} file copied.");
            } else {
                $offset = 0;
                $fh = fopen("$dir/{$file['name']}", "wb");
                $request .= "&l=".self::DOWNLOAD_RANGE;
                while($offset < $file['size']) {
                    $data = FetchUrl::getPage($request."&o=$offset");
                    $old_offset = $offset;
                    $offset += self::DOWNLOAD_RANGE;
                    $end_point = min($offset, $file["size"]);
                    //crude check if we need to redownload segment
                    if(strlen($data) != ($end_point - $old_offset)) {
                        $offset = $old_offset;
                        crawlLog(".. Download error re-requesting segment");
                        continue;
                    }
                    fwrite($fh, $data);
                    crawlLog(".. {$file['name']} downloaded bytes $old_offset ".
                        "to $end_point..");
                }
                crawlLog(".. {$file['name']} file copied.");
                fclose($fh);
            }
        }

    }
}
/*
 *  Instantiate and runs the Fetcher
 */
$syncer =  new Mirror(NAME_SERVER);
$syncer->start();

?>
