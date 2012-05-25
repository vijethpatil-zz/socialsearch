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
/** Loads url_parser to clean resource name*/
require_once BASE_DIR."/lib/url_parser.php";

/**
 *  Responsible for handling requests about global crawl statistics for
 *  a web crawl. These statistics include: httpd code distribution,
 *  filetype distribution, num hosts, language distribution, 
 *  os distribution, server distribution, site distribution, file size
 *  distribution, download time distribution, etc
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class StatisticsController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array("crawl", "machine", "phrase", "user");
    /**
     * Only outputs JSON data so don't need view
     * @var array
     */
    var $views = array("statistics");
    /**
     * Machines (string urls) which may have portions of the web crawl 
     * statistics are being generated for
     * @var array
     */
    var $machine_urls = array();
    /**
     * Timestamp of crawl statistics are being generated for
     * @var string
     */
    var $index_time_stamp;
    /**
     * File name of file to cache generated statistic into
     * @var string
     */
    var $stats_file;

    /**
     * For size and time distrbutions the number of times the miminal
     * recorded interval (DOWNLOAD_SIZE_INTERVAL for size) to check for
     * pages with that size/download time
     */
    const NUM_TIMES_INTERVAL = 50;

    /**
     * While computing the statistics page, number of seconds until a
     * page refresh and save of progress so far occurs
     */
    const STATISTIC_REFRESH_RATE = 30;
    /**
     * Main handler for requests coming into this controller for web crawl
     * statistics. Checks for the presence of a statistics file, if not 
     * found performs the necessary queries to generate crawl statistics and
     * writes that file. Then creates a $data variable which it passes to
     * a StatisticsView to actually render the results
     *
     */
    function processRequest() 
    {
        $view = "statistics";
        $data = array();
        if(isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $token_okay = $this->checkCSRFToken('YIOOP_TOKEN', $user_id);
            if($token_okay === false) {
                unset($_SESSION['USER_ID']);
                $user = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            $user_id = $_SERVER['REMOTE_ADDR']; 
        }
        $this->machine_urls = $this->machineModel->getQueueServerUrls();
        if(isset($_REQUEST['its'])) {
            $this->index_time_stamp = $this->clean($_REQUEST['its'], "string");
            //validate timestamp against list 
            //(some crawlers replay deleted crawls)
            $crawls = $this->crawlModel->getCrawlList(false,true,
                $this->machine_urls, true);
            $found_crawl = false;
            foreach($crawls as $crawl) {
                if($this->index_time_stamp == $crawl['CRAWL_TIME']) {
                    $found_crawl = true;
                    break;
                }
            }
            if(!$found_crawl) {
                unset($_SESSION['its']);
                include(BASE_DIR."/error.php");
                exit(); //bail
            }
        }
        if(!isset($this->index_time_stamp) || $this->index_time_stamp == "") {
            $this->index_time_stamp = 
                $this->crawlModel->getCurrentIndexDatabaseName();
        }
        if($this->index_time_stamp == 0) {
            unset($_SESSION['its']);
            include(BASE_DIR."/error.php");
            exit(); //bail
        }

        $this->stats_file = CRAWL_DIR."/cache/".self::statistics_base_name.
                $this->index_time_stamp.".txt";
        $stats_file_exists = file_exists($this->stats_file);
        if($stats_file_exists) {
            $data = unserialize(file_get_contents($this->stats_file));
        }
        $computing = false;
        if((!$stats_file_exists || isset($data["UNFINISHED"])) &&
            $user_id != $_SERVER['REMOTE_ADDR']) {
            //check if user allowed to make statistics
            $activities = $this->userModel->getUserActivities($user_id);
            $allowed_to_make_statistics = false;
            foreach($activities as $activity) {
                if($activity['METHOD_NAME'] == "manageCrawls") {
                    $allowed_to_make_statistics = true;
                    break;
                }
            }
            // check that no one else is making statistics on the same index
            if(isset($data["UNFINISHED"])) {
                if(!isset($data['user_id']) ||$data['user_id'] != $user_id ||
                    !isset($data['REMOTE_ADDR']) ||
                    $data['REMOTE_ADDR'] != $_SERVER["REMOTE_ADDR"]) {
                    $_REQUEST['p'] = "409";
                    include(BASE_DIR."/error.php");
                    exit(); //bail
                }
            }
            $data['user_id'] = $user_id;
            $data['REMOTE_ADDR'] = $_SERVER["REMOTE_ADDR"];
            $this->computeStatistics($data);
            $computing = true;
        }
        if(!$stats_file_exists && !$computing) {
            unset($_SESSION['its']);
            include(BASE_DIR."/error.php");
            exit(); //bail
        }
        $data['YIOOP_TOKEN'] = $this->generateCSRFToken($user_id);
        $data["its"] = $this->index_time_stamp;
        $this->statisticsView->head_objects["robots"] = "NOINDEX, NOFOLLOW";
        $this->displayView($view, $data);
    }

    /**
     *  Runs the queries necessary to determine httpd code distribution,
     *  filetype distribution, num hosts, language distribution, 
     *  os distribution, server distribution, site distribution, file size
     *  distribution, download time distribution, etc for the web crawl
     *  set in $this->index_time_stamp. If these queries take to long it
     *  saves partial results and returns with the field $data["UNFINISHED"]
     *  set to true.
     *
     *  @param array &$data associative array which receive all the statistics
     *      data collected.
     */
    function computeStatistics(&$data)
    {
        global $INDEXED_FILE_TYPES;

        if(!isset($data["COUNT"])) {
            $tmp =  $this->crawlModel->getInfoTimestamp(
                $this->index_time_stamp, $this->machine_urls);
            $tmp["user_id"] = $data["user_id"];
            $tmp["REMOTE_ADDR"] = $data["REMOTE_ADDR"];
            $data = $tmp;
            $data["stars"] = "*";
            if(!isset($data["COUNT"])) {
                include(BASE_DIR."./error.php");
                exit();
            }
            $data["UNFINISHED"] = true;
            file_put_contents($this->stats_file, serialize($data));
            return $data;
        }
        $data["TIMESTAMP"] = $this->index_time_stamp;
        $queries = array(
            "CODE" => array(100, 101, 102, 103, 122, 200, 201, 202, 203, 204,
                205, 206, 207, 208, 226, 301, 302, 303, 304, 305, 306, 307,
                308, 400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410,
                411, 412, 413, 414, 415, 416, 417, 418, 420, 422, 423, 424,
                425, 426, 428, 429, 431, 444, 449, 450, 499, 500, 501, 502,
                503, 504, 505, 506, 507, 508, 509, 510, 511, 598, 599),
            "FILETYPE" => $INDEXED_FILE_TYPES,
            "HOST" => array("all"),
            "LANG" => array( 'aa', 'ab', 'ae', 'af', 'ak', 'am', 'an', 'ar', 
                'as', 'av', 'ay', 'az', 'ba', 'be', 'bg', 'bh', 'bi', 'bm', 
                'bn', 'bo', 'br', 'bs', 'ca', 'ce', 'ch', 'co', 'cr', 'cs',
                'cu', 'cv', 'cy', 'da', 'de', 'dv', 'dz', 'ee', 'el', 'en',
                'eo', 'es', 'et', 'eu', 'fa', 'ff', 'fi', 'fj', 'fo', 'fr',
                'fy', 'ga', 'gd', 'gl', 'gn', 'gu', 'gv', 'ha', 'he', 'hi',
                'ho', 'hr', 'ht', 'hu', 'hy', 'hz', 'ia', 'id', 'ie', 'ig',
                'ii', 'ik', 'in', 'io', 'is', 'it', 'iu', 'iw', 'ja', 'ji',
                'jv', 'jw', 'ka', 'kg', 'ki', 'kj', 'kk', 'kl', 'km', 'kn',
                'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky', 'la', 'lb', 'lg',
                'li', 'ln', 'lo', 'lt', 'lu', 'lv', 'mg', 'mh', 'mi', 'mk',
                'ml', 'mn', 'mo', 'mr', 'ms', 'mt', 'my', 'na', 'nb', 'nd',
                'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv', 'ny', 'oc', 'oj',
                'om', 'or', 'os', 'pa', 'pi', 'pl', 'ps', 'pt', 'qu', 'rm',
                'rn', 'ro', 'ru', 'rw', 'sa', 'sc', 'sd', 'se', 'sg', 'sh',
                'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st',
                'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'ti', 'tk', 'tl',
                'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug', 'uk', 'ur',
                'uz', 've', 'vi', 'vo', 'wa', 'wo', 'xh', 'yi', 'yo', 'za',
                'zh', 'zu'),
            "MEDIA" => array("image", "text", "video"),
            "OS" => array("asianux", "centos", "clearos", "debian", "fedora", 
                "freebsd", "gentoo", "linux", "netware", "solaris", "sunos",
                "ubuntu", "unix"),
            "SERVER" => array("aolserver", "apache", "bigip", "boa", "caudium",
                "cherokee", "gws", "goahead-webs", "httpd", "iis", 
                "ibm_http_server", "jetty", "lighttpd", "litespeed", 
                "microsoft-iis", "nginx", "resin", "server", "sun-java-system", 
                "thttpd", "tux", "virtuoso", "webrick", "yaws", "yts", 
                "zeus", "zope"),
            "SITE" => array(".aero", ".asia", ".biz", ".cat", ".com", ".coop",
                ".edu", ".gov", ".info", ".int", ".jobs", ".mil", ".mobi",
                ".museum", ".name", ".net", ".org", ".pro", ".tel", ".travel", 
                ".xxx", ".ac", ".ad", ".ae", ".af", ".ag", ".ai", ".al", ".am",
                ".ao", ".aq", ".ar", ".as", ".at", ".au", ".aw", ".ax", ".az",
                ".ba", ".bb", ".bd", ".be", ".bf", ".bg", ".bh", ".bi", ".bj",
                ".bm", ".bn", ".bo", ".br", ".bs", ".bt", ".bw", ".by", ".bz",
                ".ca", ".cc", ".cd", ".cf", ".cg", ".ch", ".ci", ".ck", ".cl",
                ".cm", ".cn", ".co", ".cr", ".cu", ".cv", ".cx", ".cy", ".cz",
                ".de", ".dj", ".dk", ".dm", ".do", ".dz", ".ec", ".ee", ".eg",
                ".er", ".es", ".et", ".eu", ".fi", ".fj", ".fk", ".fm", ".fo",
                ".fr", ".ga", ".gd", ".ge", ".gf", ".gg", ".gh", ".gi", ".gl",
                ".gm", ".gn", ".gp", ".gq", ".gr", ".gs", ".gt", ".gu", ".gw",
                ".gy", ".hk", ".hm", ".hn", ".hr", ".ht", ".hu", ".id", ".ie",
                ".il", ".im", ".in", ".io", ".iq", ".ir", ".is", ".it", ".je",
                ".jm", ".jo", ".jp", ".ke", ".kg", ".kh", ".ki", ".km", ".kn",
                ".kp", ".kr", ".kw", ".ky", ".kz", ".la", ".lb", ".lc", ".li",
                ".lk", ".lr", ".ls", ".lt", ".lu", ".lv", ".ly", ".ma", ".mc",
                ".md", ".me", ".mg", ".mh", ".mk", ".ml", ".mm", ".mn", ".mo",
                ".mp", ".mq", ".mr", ".ms", ".mt", ".mu", ".mv", ".mw", ".mx",
                ".my", ".mz", ".na", ".nc", ".ne", ".nf", ".ng", ".ni", ".nl",
                ".no", ".np", ".nr", ".nu", ".nz", ".om", ".pa", ".pe", ".pf",
                ".pg", ".ph", ".pk", ".pl", ".pm", ".pn", ".pr", ".ps", ".pt",
                ".pw", ".py", ".qa", ".re", ".ro", ".rs", ".ru", ".rw", ".sa",
                ".sb", ".sc", ".sd", ".se", ".sg", ".sh", ".si", ".sk", ".sl",
                ".sm", ".sn", ".so", ".sr", ".ss", ".st", ".sv", ".sy", ".sz",
                ".tc", ".td", ".tf", ".tg", ".th", ".tj", ".tk", ".tl", ".tm",
                ".tn", ".to", ".tr", ".tt", ".tv", ".tw", ".tz", ".ua", ".ug",
                ".uk", ".us", ".uy", ".uz", ".va", ".vc", ".ve", ".vg", ".vi",
                ".vn", ".vu", ".wf", ".ws", ".ye", ".za", ".zm", ".zw" ),

        );
        for($i = 0; $i <= self::NUM_TIMES_INTERVAL; $i++) {
            $queries["SIZE"][] = $i * DOWNLOAD_SIZE_INTERVAL;
        }
        for($i = 0; $i <= self::NUM_TIMES_INTERVAL; $i++) {
            $queries["TIME"][] = $i * DOWNLOAD_TIME_INTERVAL;
        }
        for($i = 0; $i <= self::NUM_TIMES_INTERVAL; $i++) {
            $queries["DNS"][] = $i * DOWNLOAD_TIME_INTERVAL;
        }
        for($i = 0; $i <= MAX_LINKS_PER_SITEMAP; $i++) {
            $queries["NUMLINKS"][] = $i;
        }
        $date = date("Y");
        for($i = 1969; $i <= $date; $i++) {
            $queries["MODIFIED"][] = $i;
        }
        $sort_fields = array("CODE", "FILETYPE", "LANG", "MEDIA", "OS",
            "SERVER", "SITE");
        $time = time();

        $data["stars"] = (isset($_REQUEST["stars"]) ) ?
            $this->clean($_REQUEST["stars"], "string") . "*" : "*";

        if(isset($data["UNFINISHED"])) {
            unset($data["UNFINISHED"]);
        }
        foreach($queries as $group_description => $query_group) {
            $total = 0;
            foreach($query_group as $query) {
                if(isset($data["SEEN"][$group_description][$query])) {
                    if(isset($data[$group_description]["DATA"][$query])) {
                        $total += $data[$group_description]["DATA"][$query];
                    }
                    continue;
                }
                $count = 
                    $this->countQuery(strtolower($group_description)
                        .":".$query);
                $data["SEEN"][$group_description][$query] = true;
                if($count >= 0) {
                    $data[$group_description]["DATA"][$query] = $count;
                    $total += $count;
                }
                if(time() - $time > self::STATISTIC_REFRESH_RATE) {
                    $data["UNFINISHED"] = true;
                    break 2;
                }
            }
            if(isset($data[$group_description]["DATA"])) {
                if(in_array($group_description, $sort_fields)) {
                    arsort($data[$group_description]["DATA"]);
                }
                $data[$group_description]["TOTAL"] = $total;
            }
        }
        $data["OS"]["DATA"]["windows"] = 0;
        if(isset($data["SERVER"]["DATA"]["iis"])) {
            $data["OS"]["DATA"]["windows"] = $data["SERVER"]["DATA"]["iis"];
        }
        if(isset($data["SERVER"]["DATA"]["microsoft-iis"])) {
            $data["OS"]["DATA"]["windows"] += 
                $data["SERVER"]["DATA"]["microsoft-iis"];
        }
        arsort($data["OS"]["DATA"]);
        file_put_contents($this->stats_file, serialize($data));
        return $data;
    }

    /**
     * Performs the provided $query of a web crawl (potentially distributed
     * across queue servers). Returns the count of the number of results that
     * would be returned by that query.
     *
     * @return int number of results that would be returned by the given query
     */
    function countQuery($query)
    {
        $results = $this->phraseModel->getPhrasePageResults(
            "$query i:{$this->index_time_stamp}", 0, 
            1, true, NULL, false, 0, $this->machine_urls);
        return (isset($results["TOTAL_ROWS"])) ? $results["TOTAL_ROWS"] : -1;
    }

}
?>
