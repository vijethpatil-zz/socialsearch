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
 *  Used to serve resources, css, or scripts such as images from APP_DIR
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class ResourceController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array("crawl");
    /**
     * Only outputs JSON data so don't need view
     * @var array
     */
    var $views = array();
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("get", "syncList", "syncNotify", "suggest");


    /**
     * Checks that the request seems to be coming from a legitimate fetcher
     * or mirror server then determines which activity  is being requested 
     * and calls the method for that activity.
     *
     */
    function processRequest() 
    {
        if(!isset($_REQUEST['a']) ||
            (!in_array($_REQUEST['a'], array("get", "suggest"))
            && !$this->checkRequest())) {return; }
        $activity = $_REQUEST['a'];

        if(in_array($activity, $this->activities)) {$this->$activity();}
    }

    /**
     * Gets the resource $_REQUEST['n'] from APP_DIR/$_REQUEST['f'] or
     * CRAWL_DIR/$_REQUEST['f']  after cleaning
     */
    function get()
    {
        if(!isset($_REQUEST['n']) || !isset($_REQUEST['f'])) return;
        $name = $this->clean($_REQUEST['n'], "string");
        if(in_array($_REQUEST['f'], array("css", "scripts", "resources"))) {
            /* notice in this case we don't check if request come from a
               legitimate source but we do try to restrict it to being
               a file (not a folder) in the above array
            */
            $folder = $_REQUEST['f'];
            $base_dir = APP_DIR."/$folder";
            $type = UrlParser::getDocumentType($name);
            $name = UrlParser::getDocumentFilename($name);
            $name = ($type != "") ? "$name.$type" :$name;
        } else if(in_array(
            $_REQUEST['f'], array("cache"))) {
            /*  perform check since these request should come from a known
                machine
            */
            if(!$this->checkRequest()) {
                return;
            }
            $folder = $_REQUEST['f'];
            $base_dir = CRAWL_DIR."/$folder";
        } else {
            return;
        }
        if(isset($_REQUEST['o']) && isset($_REQUEST['l'])) {
            $offset = $this->clean($_REQUEST['o'], "int");
            $limit = $this->clean($_REQUEST['l'], "int");
        }

        $path = "$base_dir/$name";
        $finfo = new finfo(FILEINFO_MIME);
        $mime_type = $finfo->file($path);
        if(file_exists($path)) {
            header("Content-type:$mime_type");
            if(isset($offset) && isset($limit)) {
                echo file_get_contents($path, false, NULL, $offset, $limit);
            } else {
                readfile($path);
            }
        }
    }

    /**
     * Used to get a keyword suggest trie. This sends additional
     * header so will be decompressed on the fly
     */
    function suggest()
    {
        if(!isset($_REQUEST["locale"])){return;}
        $locale = $_REQUEST["locale"];
        $count = preg_match("/^[a-zA-z]{2}(-[a-zA-z]{2})?$/", $locale);
        if($count != 1) {return;}
        $path = LOCALE_DIR."/$locale/resources/suggest_trie.txt.gz";
        if(file_exists($path)) {
            header("Content-Type: application/json");
            header("Content-Encoding: gzip");
            header("Content-Length: ".filesize($path));
            readfile($path);
        }
    }

    /**
     *  Used to notify a machine that another machine acting as a mirror
     *  is still alive. Data is stored in a txt file self::mirror_table_name
     */
    function syncNotify()
    {
        if(isset($_REQUEST['last_sync']) && $_REQUEST['last_sync'] > 0 ) {
            $mirror_table_name = CRAWL_DIR."/".self::mirror_table_name;
            $mirror_table = array();
            $time = time();
            if(file_exists($mirror_table_name) ) {
                $mirror_table = unserialize(
                    file_get_contents($mirror_table_name));
                if(isset($mirror_table['time']) && 
                    $mirror_table['time'] - $time > MIRROR_SYNC_FREQUENCY) {
                    $mirror_table = array();
                    // truncate table periodically to get rid of stale entries
                }
            }
            if(isset($_REQUEST['robot_instance'])) {
                $mirror_table['time'] = $time;
                $mirror_table['machines'][
                    $this->clean($_REQUEST['robot_instance'], "string")] = 
                    array($_SERVER['REMOTE_ADDR'], $_REQUEST['machine_uri'],
                    $time, 
                    $this->clean($_REQUEST['last_sync'], "int"));
                file_put_contents($mirror_table_name, serialize($mirror_table));
            }
        }
    }

    /**
     * Returns a list of syncable files and the modification times
     */
    function syncList()
    {
        $this->syncNotify();
        $info = array();
        if(isset($_REQUEST["last_sync"])) {
            $last_sync = $this->clean($_REQUEST["last_sync"], "int");
        } else {
            $last_sync = 0;
        }
        // substrings to exclude from our list
        $excludes = array(".DS", "__MACOSX", "queries", "QueueBundle", "tmp",
            "thumb");
        $sync_files = $this->crawlModel->getDeltaFileInfo(CRAWL_DIR."/cache", 
            $last_sync, $excludes);

        if (count($sync_files) > 0 ) {
            $info[self::STATUS] = self::CONTINUE_STATE;
            $info[self::DATA] = $sync_files;
        } else {
            $info[self::STATUS] = self::NO_DATA_STATE;
        }
        echo base64_encode(gzcompress(serialize($info)));
    }
}
?>
