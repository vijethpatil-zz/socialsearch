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
 * Used to set the configuration settings of the SeekQuarry project.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage configs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}


/** Don't display any query info*/
define('NO_DEBUG_INFO', 0);
/** bit of DEBUG_LEVEL used to indicate test cases should be displayable*/
define('TEST_INFO', 1);
/** bit of DEBUG_LEVEL used to indicate query statistics should be displayed*/
define('QUERY_INFO', 2);
/** bit of DEBUG_LEVEL used to indicate php messages should be displayed*/
define('ERROR_INFO', 4);
date_default_timezone_set('America/Los_Angeles');

/** Maintenance mode restricts access to local machine*/
define("MAINTENANCE_MODE", false);

if(file_exists(BASE_DIR."/configs/local_config.php")) {
    /** Include any locally specified defines (could use as an alternative
        way to set work directory) */
    require_once(BASE_DIR."/configs/local_config.php");
}

if(MAINTENANCE_MODE && $_SERVER["SERVER_ADDR"] != $_SERVER["REMOTE_ADDR"]) {
    echo "This Yioop! installation is undergoing maintenance, please come ".
        "back later!";
    exit();
}

if(!defined('WORK_DIRECTORY')) {
/*+++ The next block of code is machine edited, change at 
your own risk, please use configure web page instead +++*/
define('WORK_DIRECTORY', 'C:/xampp/htdocs/additionalyioopdirs/work');
/*++++++*/
}

define('APP_DIR', WORK_DIRECTORY."/app");
define('PREP_DIR', WORK_DIRECTORY."/prepare");

define('FALLBACK_LOCALE_DIR', BASE_DIR."/locale");

if(file_exists(WORK_DIRECTORY."/profile.php")) {
    require_once(WORK_DIRECTORY."/profile.php");
    define('PROFILE', true);
    define('CRAWL_DIR', WORK_DIRECTORY);
    if(is_dir(WORK_DIRECTORY."/locale")) {
        define('LOCALE_DIR', WORK_DIRECTORY."/locale");
    } else {
        /** @ignore */
        define('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    }
    define('LOG_DIR', WORK_DIRECTORY."/log");
    if(defined('DB_URL') && !defined('DB_HOST')) {
        define('DB_HOST', DB_URL); //for backward compatibility
    }
    if(defined('QUEUE_SERVER') && !defined('NAME_SERVER')) {
        define('NAME_SERVER', QUEUE_SERVER); //for backward compatibility
    }
} else {
    if((!isset( $_SERVER['SERVER_NAME'])||$_SERVER['SERVER_NAME']!=='localhost')
        && !defined("NO_LOCAL_CHECK")) {
        echo "SERVICE AVAILABLE ONLY VIA LOCALHOST UNTIL CONFIGURED"; 
        exit();
    }
    error_reporting(-1);
    /** @ignore */
    define('PROFILE', false);
    define('DBMS', 'sqlite3');
    define('DEBUG_LEVEL', NO_DEBUG_INFO);
    define('USE_FILECACHE', false);
    define('WEB_ACCESS', true);
    define('RSS_ACCESS', true);
    define('API_ACCESS', true);
    define('DB_NAME', "default");
    define('DB_USER', '');
    define('DB_PASSWORD', '');
    define('DB_HOST', '');
    /** @ignore */
    define('CRAWL_DIR', BASE_DIR);
    /** @ignore */
    define('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    /** @ignore */
    define('LOG_DIR', BASE_DIR."/log");
    define('NAME_SERVER', "http://localhost/");
    define('USER_AGENT_SHORT', "NeedsNameBot");
    /** @ignore */
    define('SESSION_NAME', "yioopbiscuit");
    define('DEFAULT_LOCALE', "en-US");
    define('AUTH_KEY', 0);
    define('USE_MEMCACHE', false);
    define('WORD_SUGGEST', true);
    define('CACHE_LINK', true);
    define('SIMILAR_LINK', true);
    define('IN_LINK', true);
    define('IP_LINK', true);
    define('SIGNIN_LINK', true);
    /** BM25F weight for title text */
    define ('TITLE_WEIGHT', 4);
    /** BM25F weight for other text within doc*/
    define ('DESCRIPTION_WEIGHT', 1);
    /** BM25F weight for other text within links to a doc*/
    define ('LINK_WEIGHT', 2);
    /** If that many exist, the minimum number of results to get
        and group before trying to compute the top x (say 10) results
     */
    define ('MIN_RESULTS_TO_GROUP', 200);
    /** For a given number of search results total to return (total_num)
        server_alpha*total_num/num_servers will be returned any a given
        queue server machine*/
    define ('SERVER_ALPHA', 1.6);
    $INDEXING_PLUGINS = array();

}

if((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    error_reporting(-1);
} else {
    error_reporting(0);
}

/** if true tests are diplayable*/
define('DISPLAY_TESTS', ((DEBUG_LEVEL & TEST_INFO) == TEST_INFO));

/** if true query statistics are diplayed */
define('QUERY_STATISTICS', ((DEBUG_LEVEL & QUERY_INFO) == QUERY_INFO));

if(!PROFILE) {
    return;
}
/*+++ End machine generated code, feel free to edit the below as desired +++*/

/** this is the User-Agent names the crawler provides 
 * a web-server it is crawling
 */
define('USER_AGENT', 
    'Mozilla/5.0 (compatible; '.USER_AGENT_SHORT.'; +'.NAME_SERVER.'bot.php)');

/** name of the cookie used to manage the session 
 * (store language and perpage settings)
 */
define ('SESSION_NAME', "yioopbiscuit"); 

/**
 * @global array addresses of memcached servers to use assuming memcached is
 * available
 */
if(USE_MEMCACHE) {
    $memcache_hosts = explode("|Z|", MEMCACHE_SERVERS);
    foreach($memcache_hosts as $host)
    $MEMCACHES[] = array("host" => $host, "port" => "11211"
    );
    unset($memcache_hosts);
    unset($host);
}

/** maximum size of a log file before it is rotated */
define("MAX_LOG_FILE_SIZE", 5000000); 

/** number of log files to rotate amongst */
define("NUMBER_OF_LOG_FILES", 5); 

/**
 * how long in seconds to keep a cache of a robot.txt 
 * file before re-requesting it
 */
define('CACHE_ROBOT_TXT_TIME', 86400); 

/**
 * if the robots.txt has a Crawl-delay larger than this 
 * value don't crawl the site.
 * maximum value for this is 255
 */
define('MAXIMUM_CRAWL_DELAY', 64);

/** maximum number of active crawl-delayed hosts */
define('MAX_WAITING_HOSTS', 250); 

 
/** 
 * bloom filters are used to keep track of which urls are visited, 
 * this parameter determines up to how many
 * urls will be stored in a single filter. Additional filters are 
 * read to and from disk.
 */
define('URL_FILTER_SIZE', 20000000);

/**
 * maximum number of urls that will be held in ram
 * (as opposed to in files) in the priority queue
 */
define('NUM_URLS_QUEUE_RAM', 300000); 

/** Minimum weight in priority queue before rebuilt*/
define('MIN_QUEUE_WEIGHT', 1/100000);

/**  largest sized object allowedin a web archive (used to sanity check
     reading data out of a web archive)
*/
define('MAX_ARCHIVE_OBJECT_SIZE', 100000000);

/** number of documents before next gen */
define('NUM_DOCS_PER_GENERATION', 50000);

/** precision to round floating points document scores */
define('PRECISION', 10); 

/** maximum number of links to consider on any given page */
define('MAX_LINKS_PER_PAGE', 50); 

/** maximum number of links to consider from a sitemap page */
define('MAX_LINKS_PER_SITEMAP', 200); 

/**  maximum number of words from links to consider on any given page */
define('MAX_LINKS_WORD_TEXT', 100);

/**  maximum length of urls to try to queue, this is important for
 *   memory when creating schedule, since the amount of memory is
 *   going to be greater than the product MAX_URL_LENGTH*MAX_FETCH_SIZE
 *   text_processors need to promise to implement this check or rely
 *   on the base class which does implement it in extractHttpHttpsUrls
 */
define('MAX_URL_LENGTH', 512); 

/** request this many bytes out of a page -- this is the default value to
    use if the user doesn't set this value in the page options GUI 
 */
define('PAGE_RANGE_REQUEST', 50000);

/** 
    Allow pages to be recrawled after this many days -- this is the 
    default value to use if the user doesn't set this value in the page options 
    GUI. What this controls is how often the page url filter is deleted.
    A nonpositive value means the filter will never be deleted.
 */
define('PAGE_RECRAWL_FREQUENCY', -1);

/** maximum length +1 exact phrase matches */
define('MAX_PHRASE_LEN', 2); 

/** number of multi curl page requests in one go */
define('NUM_MULTI_CURL_PAGES', 100); 

/** number of pages to extract from an archive in one go */
define('ARCHIVE_BATCH_SIZE', 100);

/**
 *  Time in seconds to wait to acquire an exclusive lock before we're no longer
 *  allowed to extract the next batch of pages for an archive crawl. This is
 *  intended to prevent a fetcher from waiting to acquire the lock, then
 *  getting it just before cURL gives up and times out the request.
 */
define('ARCHIVE_LOCK_TIMEOUT', 8);

/** time in seconds before we give up on a page */
define('PAGE_TIMEOUT', 30);

/**
 *  Number of error page 400 or greater seen from a host before crawl-delay
 *  host and dump remainder from current schedule
 */
define('DOWNLOAD_ERROR_THRESHOLD', 10);

/** Crawl-delay to set in the event that DOWNLOAD_ERROR_THRESHOLD exceeded*/
define('ERROR_CRAWL_DELAY', 20);


/** how often should we make in OPIC the sum of weights totals MAX_URLS */
define('NORMALIZE_FREQUENCY', 10000); 

/**
 * @global array file extensions which can be handled by the search engine, 
 * other extensions will be ignored
 */
$INDEXED_FILE_TYPES =
    array(  
            "asp",
            "aspx",
            "cgi",
            "cfm",
            "cfml",
            "csv",
            "doc",
            "epub",
            "gif",
            "html",
            "htm",
            "jsp",
            "jpg",
            "jpeg",
            "pdf",
            "php",
            "pl",
            "py",
            "ppt",
            "pptx",
            "png",
            "rtf",
            "rss",
            "shtml",
            "svg",
            "tab",
            "tsv",
            "txt",
            "xlsx",
            "xml");

/**
 * @global array filetypes which should be considered images
 */
$IMAGE_TYPES = array("gif","jpg", "bmp", "png", "jpeg", "svg");

/**
 * @global array associates mimetypes that can be processed by the search 
 * engine with the processor class that can process them
 */
$PAGE_PROCESSORS = array(   "text/html" => "HtmlProcessor", 
                            "text/asp" => "HtmlProcessor",
                            "text/xml" => "XmlProcessor",
                            "text/robot" => "RobotProcessor",

                            "application/xml" => "XmlProcessor",
                            "application/xhtml+xml" => "HtmlProcessor",

                            "application/rss+xml" => "RssProcessor",
                            
                            "application/pdf" => "PdfProcessor",

                            "application/msword" => "DocProcessor",
                            "application/vnd.ms-powerpoint" => "PptProcessor",
                            "application/vnd.openxmlformats-officedocument.
                                presentationml.presentation"=> "PptxProcessor",
                            "application/epub+zip" => "EpubProcessor",
                            "application/vnd.openxmlformats-officedocument.
                                spreadsheetml.sheet" => "XlsxProcessor",

                            "text/rtf" => "RtfProcessor",
                            "text/plain" => "TextProcessor", 
                            "text/csv" => "TextProcessor",
                            "text/tab-separated-values" => "TextProcessor",
                            "image/jpeg" => "JpgProcessor",
                            "image/gif" => "GifProcessor", 
                            "image/png" => "PngProcessor",
                            "image/bmp" => "BmpProcessor",
                            "image/svg+xml"=> "SvgProcessor"
);

if(defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50300) {
    $INDEXING_PLUGINS = array("recipe");
} else {
    $INDEXING_PLUGINS = array();
}

$MOD9_PACK_POSSIBILITIES = array(
    0, 24, 12, 7, 6, 5, 4, 3, 3, 3, 2, 2, 2, 2,
    2,  1,  1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
    1, 1, 1, 1);

$MOD9_NUM_ELTS_CODES = array( 
    24 => 63, 12 => 62, 7 => 60, 6 => 56, 5 => 52, 4 => 48, 3 => 32,
    2 => 16, 1 => 0);

$MOD9_NUM_BITS_CODES = array( 63 => 1, 62 => 2, 60 => 3, 56 => 4, 52 => 5,
    48 => 6, 32 => 9, 16 => 14, 0 => 28);

$MOD9_NUM_ELTS_DECODES = array( 
    63 => 24, 62 => 12, 60=> 7, 56 => 6, 52 => 5, 48 => 4, 32 => 3,
    16 => 2, 0 => 1);


/** Characters we view as not part of words, not same as POSIX [:punct:]*/
define ('PUNCT', "\.|\,|\:|\;|\"|\'|\[|\/|\%".
    "\]|\{|\}|\(|\)|\!|\||\&|\`|\’|\‘|©|®|™|℠|… |- |\/ |\>|，|\=");

/** Percentage ASCII text before guess we dealing with english*/
define ('EN_RATIO', 0.9);

/** Number of total description deemed title */
define ('AD_HOC_TITLE_LENGTH', 10);

/** Used to say number of bytes in histogram bar for file download sizes*/
define('DOWNLOAD_SIZE_INTERVAL', 5000);

/** Used to say number of secs in histogram bar for file download times*/
define('DOWNLOAD_TIME_INTERVAL', 0.5);

/**
 * How many non robot urls the fetcher successfully downloads before
 * between times data sent back to queue server
 */
define ('SEEN_URLS_BEFORE_UPDATE_SCHEDULER', 500);

/** maximum number of urls to schedule to a given fetcher in one go */
define ('MAX_FETCH_SIZE', 5000);

/** fetcher must wait at least this long between multi-curl requests */
define ('MINIMUM_FETCH_LOOP_TIME', 5); 

/** an idling fetcher sleeps this long between queue_server pings*/
define ('FETCH_SLEEP_TIME', 15); 

/** an a queue_server minimum loop idle time*/
define ('QUEUE_SLEEP_TIME', 5);

/** How often mirror script tries to synchronize with machine it is mirroring*/
define ('MIRROR_SYNC_FREQUENCY', 3600);
/** How often mirror script tries to notify machine it is mirroring that it
is still alive*/
define ('MIRROR_NOTIFY_FREQUENCY', 60);

/** Max time before dirty index (queue_server) and 
    filters (fetcher) will be force saved in seconds*/
define('FORCE_SAVE_TIME', 3600);

    /** Number of seconds of no fetcher contact before crawl is deemed dead*/
define("CRAWL_TIME_OUT", 1800);

/** maximum number of terms allowed in a conjunctive search query */
define ('MAX_QUERY_TERMS', 10); 

/** default number of search results to display per page */
define ('NUM_RESULTS_PER_PAGE', 10); 

/** Number of recently crawled urls to display on admin screen */
define ('NUM_RECENT_URLS_TO_DISPLAY', 10); 
?>
