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


/** Calculate base directory of script 
 * @ignore 
 */

define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

ini_set("memory_limit","1400M"); //so have enough memory to crawl big pages

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance ".
        "by visiting its web interface on localhost.\n";
    exit();
}

/** NO_CACHE means don't try to use memcache 
 * @ignore
 */
define("NO_CACHE", true);

/** Get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Load the class that maintains our URL queue */
require_once BASE_DIR."/lib/web_queue_bundle.php";

/** Load word->{array of docs with word} index class */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/** Used for manipulating urls*/
require_once BASE_DIR."/lib/url_parser.php";

/**  For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/** For crawlDaemon function  */
require_once BASE_DIR."/lib/crawl_daemon.php"; 
/**  */
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/** */
require_once BASE_DIR."/lib/phrase_parser.php";

/** Include marker interface to say we support join() method*/
require_once BASE_DIR."/lib/join.php";

/** Include Feed Crawler */
require_once BASE_DIR."/feeds/feed_crawler.php";

/** Include RSSFeed Crawler */
require_once BASE_DIR."/feeds/rssfeed_crawler.php";

/** Include Feed Model */
require_once BASE_DIR."/models/feed_model.php";

/** get any indexing plugins */
foreach(glob(BASE_DIR."/lib/indexing_plugins/*_plugin.php") as $filename) { 
    require_once $filename;
}

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/*
 * If Memcache is available queueserver can be used to load 
 * index dictionaries and shards into memcache. Note in only
 * this situation is NO_CACHE ignored
 */
if(USE_MEMCACHE) {
    $MEMCACHE = new Memcache();
    foreach($MEMCACHES as $mc) {
        $MEMCACHE->addServer($mc['host'], $mc['port']);
    }
    unset($mc);
}

/**
 * Command line program responsible for managing Yioop crawls.
 *
 * It maintains a queue of urls that are going to be scheduled to be seen.
 * It also keeps track of what has been seen and robots.txt info.
 * Its last responsibility is to create and populate the IndexArchiveBundle
 * that is used by the search front end.
 *
 * @author Chris Pollett
 * @package seek_quarry
 */
class FeedServer implements CrawlConstants, Join
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    var $db;
  
    var $page_recrawl_frequency;
    /**
     * Indicates the kind of crawl being performed: self::WEB_CRAWL indicates
     * a new crawl of the web; self::ARCHIVE_CRAWL indicates a crawl of an 
     * existing web archive
     * @var string
     */
    var $crawl_type;

    /**
     * If the crawl_type is self::ARCHIVE_CRAWL, then crawl_index is the 
     * timestamp of the existing archive to crawl
     * @var string
     */
    var $crawl_index;
    /**
     * Says whether the $allowed_sites array is being used or not
     * @var bool
     */
    var $restrict_sites_by_url;
    /**
     * List of file extensions supported for the crawl
     * @var array
     */
    var $indexed_file_types;
    /**
     * Holds an array of word -> url patterns which are used to 
     * add meta words to the words that are extracted from any given doc
     * @var array
     */
    var $meta_words;
    /**
     * Holds the WebQueueBundle for the crawl. This bundle encapsulates
     * the priority queue of urls that specifies what to crawl next
     * @var object
     */
    var $web_queue;
    /**
     * Holds the IndexArchiveBundle for the current crawl. This encapsulates
     * the inverted index word-->documents for the crawls as well as document
     * summaries of each document.
     * @var object
     */
    var $index_archive;
    /**
     * The timestamp of the current active crawl
     * @var int
     */
    var $crawl_time;
    /**
     * This is a list of hosts whose robots.txt file had a Crawl-delay directive
     * and which we have produced a schedule with urls for, but we have not
     * heard back from the fetcher who was processing those urls. Hosts on
     * this list will not be scheduled for more downloads until the fetcher
     * with earlier urls has gotten back to the queue_server.
     * @var array
     */
    var $waiting_hosts;
    /**
     * IP address as a string of the fetcher that most recently spoke with the
     * queue_server.
     * @var string
     */
    var $most_recent_fetcher;
    /**
     * Last time index was saved to disk
     * @var int
     */
    var $last_index_save_time;
    /**
     * flags for whether the index has data to be written to disk
     * @var int
     */
     var $index_dirty;

    /**
     * This keeps track of the time the current archive info was last modified
     * This way the queue_server knows if the user has changed the crawl
     * parameters during the crawl.
     * @var int
     */
    var $archive_modified_time;

    /**
     * This is a list of indexing_plugins which might do
     * post processing after the crawl. The plugins postProcessing function
     * is called if it is selected in the crawl options page.
     * @var array
     */
    var $indexing_plugins;

    /**
     * This is a list of hourly (timestamp, number_of_urls_crawled) data
     * @var array
     */
    var $hourly_crawl_data;

    /**
     *  Used to say what kind of queue_server this is (one of BOTH, INDEXER,
     *  SCHEDULER)
     *  @var int
     */
     var $servertype;

    /**
     * holds the post processors selected in the crawl options page
     */

    function __construct() 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class(); 
        $this->most_recent_fetcher = "No Fetcher has spoken with me";

        //the next values will be set for real in startCrawl
         
        $this->quota_clear_time = time();
        $this->last_index_save_time = 0;
        $this->index_dirty = false;
        $this->hourly_crawl_data = array();
        $this->archive_modified_time = 0;
        $this->crawl_time = 0;
        $this->page_recrawl_frequency = PAGE_RECRAWL_FREQUENCY;
        $this->page_range_request = PAGE_RANGE_REQUEST;
        $this->server_type = self::BOTH;
    }

    /**
     * This is the function that should be called to get the feedserver 
     * to start. Calls init to handle the command line arguments then enters 
     * the feed_server's main loop
     */
    function start()
    {
        global $argv;

        declare(ticks=200);
        if(isset($argv[1]) && $argv[1] == "start") {
            $argv[2] = "none";
            $argv[3] = self::INDEXER;
            CrawlDaemon::init($argv, "feed_server");
        } else {
            CrawlDaemon::init($argv, "feed_server");
        }

        crawlLog("\n\nInitialize logger..", "feed_server");
        if(isset($argv[3]) && $argv[1] == "child" && 
            in_array($argv[3], array(self::INDEXER))) {
            $this->server_type = $argv[3];
            crawlLog($argv[3]." logging started.");
        } 
        $remove = false;
        $old_message_names = array("feed_server_messages.txt");
        foreach($old_message_names as $name) {
            if(file_exists(CRAWL_DIR."/schedules/$name")) {
                @unlink(CRAWL_DIR."/schedules/$name");
                $remove = true;
            }
        }
        if($remove == true) {
            crawlLog("Remove old messages..", "feed_server");
        }
        $this->loop();

    }

    /**
     * Main runtime loop of the queue_server. 
     *
     * Loops until a stop message received, check for start, stop, resume
     * crawl messages, deletes any WebQueueBundle for which an 
     * IndexArchiveBundle does not exist. Processes
     */
    function loop()
    {
        crawlLog("In feed loop!! $server_name", "feed_server");
        
        crawlLog("Including base dir".BASE_DIR,"feed_server");
        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
        $server_name = ($this->server_type != self::BOTH) ? 
            $this->server_type : "Feed";
        
        
        while ($info[self::STATUS] != self::STOP_STATE) {
            $server_name = ($this->server_type != self::BOTH) ? 
                $this->server_type : "Feed server";
            crawlLog("$server_name peak memory usage so far".
                memory_get_peak_usage()."!!");
            
            //$info = $this->handleAdminMessages($info);
            /*
            if( $info[self::STATUS] == self::WAITING_START_MESSAGE_STATE) {
                crawlLog("Waiting for start message\n");
                sleep(QUEUE_SLEEP_TIME);
                continue;
            } */
            
            if($info[self::STATUS] == self::STOP_STATE) {
                continue;
            }
            crawlLog("Going to start feed crawler message\n");
            $start_loop_time = time();
            $twitfeed = new FeedCrawler();
            $twitfeed->getUserTokens();
            $rssfeed = new RssFeed();
            $rssfeed->get_url_links();
            crawlLog("End of for feed crawler message\n");
            $time_diff = time() - $start_loop_time;
            crawlLog("time diff is $time_diff , sleeping for ".(3600-$time_diff)." seconds");
            if( $time_diff < 3600) {
                crawlLog("Sleeping...");
                sleep(3600 - $time_diff);
            }
        }
        crawlLog("$server_name shutting down!!");
    }
    
    function join()
    {
    
    
  
    }
 
}

/*
 *  Instantiate and runs the QueueSever
 */
$feed_server =  new FeedServer();
$feed_server->start();


?>