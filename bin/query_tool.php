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

/** Calculate base directory of script @ignore*/
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");

/** NO_CACHE means don't try to use memcache*/
define("NO_CACHE", true);

/** USE_FILECACHE will let us use this tool to store long running
 *  queries into the filecache
 */
if(USE_FILECACHE) {
    $CACHE = new FileCache(WORK_DIRECTORY."/cache/queries");
    /** @ignore */
    define("USE_CACHE", true);
} else {
    /** @ignore */
    define("USE_CACHE", false);
}
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/locale_functions.php";


/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/search_controller.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 * Tool to provide a command line query interface to indexes stored in
 * Yioop! database. Running with no arguments gives a help message for
 * this tool.
 *
 * @author Chris Pollett
 * @package seek_quarry
 */
class QueryTool implements CrawlConstants
{
    /**
     * Initializes the QueryTool, for now does nothing
     */
    function __construct() 
    {

    }

    /**
     * Runs the QueryTool on the supplied command line arguments
     */
    function start()
    {
        global $argv, $INDEXING_PLUGINS;

        if(!isset($argv[1])) {
            $this->usageMessageAndExit();
        }

        $query = $argv[1];
        $results_per_page = (isset($argv[2])) ? $argv[2] : 10;
        $limit = (isset($argv[3])) ? $argv[3] : 0;
        setLocaleObject(getLocaleTag());

        $start_time = microtime();
        $controller = new SearchController($INDEXING_PLUGINS);
        $data = $controller->queryRequest($query, $results_per_page, $limit);

        if(!isset($data['PAGES'])) {
            $data['PAGES'] = array();
        }
        foreach($data['PAGES'] as $page) {
            echo "============\n";
            echo "TITLE: ". trim($page[self::TITLE]). "\n";
            echo "URL: ". trim($page[self::URL]). "\n";
            echo "IPs: ";
            foreach($page[self::IP_ADDRESSES] as $address) {
                echo $address." ";
            }
            echo "\n";
            echo "DESCRIPTION: ".wordwrap(trim($page[self::DESCRIPTION]))."\n";
            echo "Rank: ".$page[self::DOC_RANK]."\n";
            echo "Relevance: ".$page[self::RELEVANCE]."\n";
            echo "Proximity: ".$page[self::PROXIMITY]."\n";
            echo "Score: ".$page[self::SCORE]."\n";
            echo "============\n\n";
        }
        $data['ELAPSED_TIME'] = changeInMicrotime($start_time);
        echo "QUERY STATISTICS\n";

        echo "============\n";
        echo "ELAPSED TIME: ".$data['ELAPSED_TIME']."\n";
        if(isset($data['LIMIT'])) {
            echo "LOW: ".$data['LIMIT']."\n";
        }
        if(isset($data['HIGH'])) {
            echo "HIGH: ".min($data['TOTAL_ROWS'], 
                $data['LIMIT'] + $data['RESULTS_PER_PAGE'])."\n";
        }
        if(isset($data['TOTAL_ROWS'])) {
            echo "TOTAL ROWS: ".$data['TOTAL_ROWS']."\n";
        }
        if(isset($data['ERROR'])) {
            echo $data['ERROR']."\n";
        }
    }


    /**
     * Outputs the "how to use this tool message" and then exit()'s.
     */
    function usageMessageAndExit() 
    {
        echo "\nquery_tool.php is used to run Yioop!";
        echo " query from the command line.\n For example,\n";
        echo "  php query_tool.php 'chris pollett' \n returns results ".
            "from the default index of a search on 'chris pollett'.\n";
        echo "The general command format is:\n";
        echo "  php query_tool.php query num_results start_num lang_tag\n";
        exit();
    }
}

/**
 * Used within PhraseModel called from SearchController to do stemming
 * @return string IANA tag either default or from the command line.
 */
function getLocaleTag()
{
    global $argv;

    return  (isset($argv[4])) ? $argv[4] : DEFAULT_LOCALE;
}

$query_tool =  new QueryTool();
$query_tool->start();
?>
