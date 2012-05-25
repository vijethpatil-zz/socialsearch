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
 * @author Shawn Tice sctice@gmail.com
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

/** Calculate base directory of script @ignore */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

/** Some pages are huge, and the page hash function can run out of memory 
 * stripping script, noscript, and style tags. */
ini_set("memory_limit","500M");

/** This tool does not need logging */
define("LOG_TO_FILES", false);

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** NO_CACHE means don't try to use memcache */
define("NO_CACHE", true);

/** USE_CACHE false rules out file cache as well */
define("USE_CACHE", false);

/** Load the iterator classes */
foreach(glob(BASE_DIR."/lib/archive_bundle_iterators/*_iterator.php")
    as $filename) {
    require_once $filename;
}

/** Load FetchUrl, used by the MediaWiki archive iterator */
require_once BASE_DIR."/lib/fetch_url.php";

/** Load FetchUrl, used by the MediaWiki archive iterator */
require_once BASE_DIR."/lib/utility.php";

/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 */
class ArcExtractor implements CrawlConstants
{

    const DEFAULT_EXTRACT_NUM = 50;

    const MAX_BUFFER_PAGES = 200;

    /**
     * Runs the ArcExtractor on the supplied command line arguments
     */
    function start()
    {
        global $argv;

        $num_to_extract = self::DEFAULT_EXTRACT_NUM;

        if(count($argv) < 2) {
            $this->usageMessageAndExit();
        }

        $archive_name = $argv[1];
        if(!file_exists($archive_name)) {
            $archive_name = CRAWL_DIR."/cache/".$archive_name;
            if(!file_exists($archive_name)) {
                echo "{$archive_name} doesn't exist";
                exit;
            }
        }

        if(isset($argv[2])) {
            $num_to_extract = max(1, intval($argv[2]));
        }

        $this->outputShowPages($archive_name, $num_to_extract);
    }

    /**
     * Used to list out the pages/summaries stored in a bundle
     * $archive_name. It lists to stdout $num many documents starting from 
     * either the beginning or wherever the last run left off.
     *
     * @param string $archive_name name of bundle to list documents for
     * @param int $num number of documents to list
     */
    function outputShowPages($archive_name, $total)
    {
        $fields_to_print = array(
            self::URL => "URL",
            self::IP_ADDRESSES => "IP ADDRESSES",
            self::TIMESTAMP => "DATE",
            self::HTTP_CODE => "HTTP RESPONSE CODE",
            self::TYPE => "MIMETYPE",
            self::ENCODING => "CHARACTER ENCODING",
            self::DESCRIPTION => "DESCRIPTION",
            self::PAGE => "PAGE DATA");
        $archive_kind = $this->getArchiveKind($archive_name);
        if($archive_kind === false) {
            $this->badFormatMessageAndExit($archive_name);
        }
        $iterator = $this->instantiateIterator($archive_name, $archive_kind);
        if($iterator === false) {
            $this->badFormatMessageAndExit($archive_name);
        }
        $seen = 0;
        while(!$iterator->end_of_iterator && $seen < $total) {
            $num_to_get = min(self::MAX_BUFFER_PAGES, $total - $seen);
            $objects = $iterator->nextPages($num_to_get);
            $seen += count($objects);
            foreach($objects as $object) {
                $out = "";
                if(isset($object[self::TIMESTAMP])) {
                    $object[self::TIMESTAMP] = 
                        date("r", $object[self::TIMESTAMP]);
                }
                foreach($fields_to_print as $key => $name) {
                    if(isset($object[$key])) {
                        $out .= "[$name]\n";
                        if($key != self::IP_ADDRESSES) {
                            $out .= $object[$key]."\n";
                        } else {
                            foreach($object[$key] as $address) {
                                $out .= $address."\n";
                            }
                        }
                    }
                }
                $out .= "==========\n\n";
                echo "BEGIN ITEM, LENGTH:".strlen($out)."\n";
                echo $out;
            }
        }
    }

    /**
     * Given a folder name, determines the kind of bundle (if any) it holds.
     * It does this based on the expected location of the arc_description.ini 
     * file.
     *
     * @param string $archive_name the name of folder
     * @return string the archive bundle type or false if no arc_type is found
     */
    function getArchiveKind($archive_name)
    {
        $desc_path = "$archive_name/arc_description.ini";
        if(file_exists($desc_path)) {
            $desc = parse_ini_file($desc_path);
            if(!isset($desc['arc_type'])) {
                return false;
            }
            return $desc['arc_type'];
        }
        return false;
    }

    function instantiateIterator($archive_name, $iterator_type)
    {
        $iterate_timestamp = filectime($archive_name);
        $result_timestamp = strval(time());
        /*
            Create the result dir under the current directory, and name it after 
           the iterate timestamp so that running the tool twice on the same 
           archive will result in the second run picking up where the first one 
           left off.
        */
        $this->result_name = 'ArchiveExtract'.$iterate_timestamp;
        if(!file_exists($this->result_name)) {
            mkdir($this->result_name);
        }
        $iterator_class = "{$iterator_type}Iterator";
        $iterator = new $iterator_class($iterate_timestamp, $archive_name,
            $result_timestamp, $this->result_name);
        return $iterator;
    }

    /**
     * Outputs the "hey, this isn't a known bundle message" and then exit()'s.
     */
    function badFormatMessageAndExit($archive_name) 
    {
        echo "{$archive_name} does not appear to be a valid archive type\n";
        exit();
    }

    /**
     * Outputs the "how to use this tool message" and then exit()'s.
     */
    function usageMessageAndExit() 
    {
        echo "\nDescription coming soon.\n";
        exit();
    }
}

$arc_extractor = new ArcExtractor();
$arc_extractor->start();

?>
