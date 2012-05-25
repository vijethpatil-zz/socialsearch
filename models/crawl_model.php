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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}


/** 
 * Loads common constants for web crawling, used for index_data_base_name and 
 * schedule_data_base_name 
 */
require_once BASE_DIR."/lib/crawl_constants.php";
/** 
 * Crawl data is stored in an IndexArchiveBundle, 
 * so load the definition of this class
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/** 
 * Needed to be able to send data via http to remote queue_servers
 */
require_once BASE_DIR.'/lib/fetch_url.php';

/** used to prevent cache page requests from being logged*/
define("NO_LOGGING", true);

/**
 * This is class is used to handle
 * db results for a given phrase search
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class CrawlModel extends Model implements CrawlConstants
{
    /**
     * Stores the name of the current index archive to use to get search 
     * results from
     * @var string
     */
    var $index_name;


    /**
     *  {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
    }


    /**
     * Get a summary of a document by the generation it is in
     * and its offset into the corresponding WebArchive.
     *
     * @param int $summary_offset offset in $generation WebArchive
     * @param int $generation the index of the WebArchive in the 
     *      IndexArchiveBundle to find the item in
     * @param string $url of summary we are trying to look-up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array summary data of the matching document
     */
    function getCrawlItem($url, $machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $num_machines = count($machine_urls);
            $index = calculatePartition($url, $num_machines, 
                "UrlParser::getHost");
            $machines = array($machine_urls[$index]);
            $a_list = $this->execMachines("getCrawlItem", 
                $machines, serialize(array($url, $this->index_name) ) );
            if(is_array($a_list)) {
                $elt = $a_list[0];
                $summary = unserialize(webdecode(
                    $elt[self::PAGE]));
            }
            return $summary;
        }
        list($summary_offset, $generation, $cache_partition) = 
            $this->lookupSummaryOffsetGeneration($url);
        $index_archive_name = self::index_data_base_name . $this->index_name;

        $index_archive = 
            new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);
        $summary = $index_archive->getPage($summary_offset, $generation);
        $summary[self::CACHE_PAGE_PARTITION] = $cache_partition;

        return $summary;
    }

    /**
     * Determines the offset into the summaries WebArchiveBundle of the
     * provided url so that the info:url summary can be retrieved.
     * This assumes of course that  the info:url meta word has been stored.
     *
     * @return array (offset, generation) into the web archive bundle
     */
    function lookupSummaryOffsetGeneration($url)
    {
        $index_archive_name = self::index_data_base_name . $this->index_name;
        $index_archive = new IndexArchiveBundle(
            CRAWL_DIR.'/cache/'.$index_archive_name);
        $num_retrieved = 0;
        $pages = array();
        $summary_offset = NULL;
        $num_generations = $index_archive->generation_info['ACTIVE'];
        $word_iterator =
            new WordIterator(crawlHash("info:$url"), $index_archive);
        if(is_array($next_docs = $word_iterator->nextDocsWithWord())) {
             foreach($next_docs as $doc_key => $doc_info) {
                 $summary_offset =
                    $doc_info[CrawlConstants::SUMMARY_OFFSET];
                 $generation = $doc_info[CrawlConstants::GENERATION];
                 $cache_partition = $doc_info[CrawlConstants::SUMMARY][
                    CrawlConstants::CACHE_PAGE_PARTITION];
                 $num_retrieved++;
                 if($num_retrieved >=  1) {
                     break;
                 }
             }
             if($num_retrieved == 0) {
                return false;
             }
        } else {
            return false;
        }
        return array($summary_offset, $generation, $cache_partition);
    }

    /**
     * Gets the cached version of a web page from the machine on which it was 
     * fetched.
     *
     * Complete cached versions of web pages typically only live on a fetcher 
     * machine. The queue server machine typically only maintains summaries. 
     * This method makes a REST request of a fetcher machine for a cached page 
     * and get the results back.
     *
     * @param string $machine the ip address of domain name of the machine the 
     *      cached page lives on
     * @param string $machine_uri the path from document root on $machine where 
     *      the yioop scripts live
     * @param int $partition the partition in the WebArchiveBundle the page is
     *       in
     * @param int $offset the offset in bytes into the WebArchive partition in 
     *      the WebArchiveBundle at which the cached page lives.
     * @param string $crawl_time the timestamp of the crawl the cache page is 
     *      from
     * @param int $instance_num which fetcher instance for the particular 
     *      fetcher crawled the page (if more than one), false otherwise
     * @return array page data of the cached page
     */
    function getCacheFile($machine, $machine_uri, $partition, 
        $offset, $crawl_time, $instance_num = false) 
    {
        $time = time();
        $session = md5($time . AUTH_KEY);
        if($machine == '::1') { //IPv6 :(
            $machine = "[::1]/"; 
            //used if the fetching and queue serving were on the same machine
        }
        $request = "http://$machine$machine_uri?c=archive&a=cache&time=$time".
            "&session=$session&partition=$partition&offset=$offset".
            "&crawl_time=$crawl_time";
        if($instance_num !== false) {
            $request .= "&instance_num=$instance_num";
        }
        $tmp = FetchUrl::getPage($request);

        $page = @unserialize(base64_decode($tmp));
        $page['REQUEST'] = $request;

        return $page;
    }


    /**
     * Gets the name (aka timestamp) of the current index archive to be used to 
     * handle search queries
     *
     * @return string the timestamp of the archive
     */
    function getCurrentIndexDatabaseName()
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT CRAWL_TIME FROM CURRENT_WEB_INDEX";
        $result = $this->db->execute($sql);

        $row =  $this->db->fetchArray($result);
        
        return $row['CRAWL_TIME'];
    }


    /**
     * Sets the IndexArchive that will be used for search results
     *
     * @param $timestamp  the timestamp of the index archive. The timestamp is 
     * when the crawl was started. Currently, the timestamp appears as substring
     * of the index archives directory name
     */
    function setCurrentIndexDatabaseName($timestamp)
    {
        $this->db->selectDB(DB_NAME);
        $this->db->execute("DELETE FROM CURRENT_WEB_INDEX");
        $sql = "INSERT INTO CURRENT_WEB_INDEX VALUES ('".$timestamp."')";
        $this->db->execute($sql);

    }

    /**
     * Returns all the files in $dir or its subdirectories with modfied times
     * more recent than timestamp. The file which have
     * in their path or name a string in the $excludes array will be exclude
     *
     * @param string a directory to traverse
     * @param int $timestamp used to check modified times against
     * @param array $excludes an array of path substrings tot exclude
     * @return array of file structs consisting of name, modified time and
     *      size.
     */
    function getDeltaFileInfo($dir, $timestamp = 0, $excludes)
    {
        $dir_path_len = strlen($dir) + 1;
        $files = $this->db->fileInfoRecursive($dir, true);
        $names = array();
        $results = array();
        foreach ($files as $file) {
            $file["name"] = substr($file["name"], $dir_path_len);
            if($file["modified"] > $timestamp && $file["name"] !="") {
                $flag = true;
                foreach($excludes as $exclude) {
                    if(stristr($file["name"], $exclude)) {
                        $flag = false;
                        break;
                    }
                }
                if($flag) {
                    $results[$file["name"]] = $file;
                }
            }
        }
        $results = array_values($results);
        return $results;
    }



    /**
     * Gets a list of all mixes of available crawls
     *
     * @param bool $components if false then don't load the factors
     *      that make up the crawl mix, just load the name of the mixes
     *      and their timestamps; otherwise, if true loads everything
     * @return array list of available crawls
     */
    function getMixList($components = false)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES";
        $result = $this->db->execute($sql);

        $rows = array();
        while($row = $this->db->fetchArray($result)) {
            if($components) {
                $mix = $this->getCrawlMix($row['MIX_TIMESTAMP'], true);
                $row['GROUPS'] = $mix['GROUPS'];
            }
            $rows[] = $row;
        }
        return $rows;
    }


    /**
     * Retrieves the weighting component of the requested crawl mix
     *
     * @param string $timestamp of the requested crawl mix
     * @param bool $just_components says whether to find the mix name or
     *      just the components array.
     * @return array the crawls and their weights that make up the
     *      requested crawl mix.
     */
    function getCrawlMix($timestamp, $just_components = false)
    {
        $this->db->selectDB(DB_NAME);
        if(!$just_components) {
            $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
                " MIX_TIMESTAMP='$timestamp'";
            $result = $this->db->execute($sql);
            $mix =  $this->db->fetchArray($result);
        } else {
            $mix = array();
        }
        $sql = "SELECT GROUP_ID, RESULT_BOUND".
            " FROM MIX_GROUPS WHERE ".
            " MIX_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        $mix['GROUPS'] = array();
        while($row = $this->db->fetchArray($result)) {
            $mix['GROUPS'][$row['GROUP_ID']]['RESULT_BOUND'] = 
                $row['RESULT_BOUND'];
        }
        foreach($mix['GROUPS'] as $group_id => $data) {
            $sql = "SELECT CRAWL_TIMESTAMP, WEIGHT, KEYWORDS ".
                " FROM MIX_COMPONENTS WHERE ".
                " MIX_TIMESTAMP='$timestamp' AND GROUP_ID='$group_id'";
            $result = $this->db->execute($sql);

            $mix['COMPONENTS'] = array();
            $count = 0;
            while($row =  $this->db->fetchArray($result)) {
                $mix['GROUPS'][$group_id]['COMPONENTS'][$count] =$row;
                $count++;
            }
        }
        return $mix;
    }

    /**
     * Returns the timestamp associated with a mix name;
     *
     * @param string $mix_name name to lookup
     * @return mixed timestamp associated with name if exists false otherwise
     */
    function getCrawlMixTimestamp($mix_name)
    {
        $this->db->selectDB(DB_NAME);
            $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
                " MIX_NAME='$mix_name'";
            $result = $this->db->execute($sql);
            $mix =  $this->db->fetchArray($result);
        if(isset($mix["MIX_TIMESTAMP"])) {
            return $mix["MIX_TIMESTAMP"];
        }
        return false;
    }


    /**
     * Returns whether the supplied timestamp corresponds to a crawl mix
     *
     * @param string timestamp of the requested crawl mix
     *
     * @return bool true if it does; false otherwise
     */
    function isCrawlMix($timestamp)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
            " MIX_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        if($result) {
            if($mix =  $this->db->fetchArray($result)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Stores in DB the supplied crawl mix object
     *
     * @param array $mix an associative array repreenting the crawl mix object
     */
    function setCrawlMix($mix)
    {
        $this->db->selectDB(DB_NAME);
        //although maybe slower, we first get rid of any old data
        $timestamp = $mix['MIX_TIMESTAMP'];

        $this->deleteCrawlMix($timestamp);

        //next we store the new data
        $sql = "INSERT INTO CRAWL_MIXES VALUES ('$timestamp', '".
            $mix['MIX_NAME']."')";
        $this->db->execute($sql);

        $gid = 0;
        foreach($mix['GROUPS'] as $group_id => $group_data) {

            $sql = "INSERT INTO MIX_GROUPS VALUES ('$timestamp', '$gid', ".
                "'".$group_data['RESULT_BOUND']."')";
            $this->db->execute($sql);
            foreach($group_data['COMPONENTS'] as $component) {
                $sql = "INSERT INTO MIX_COMPONENTS VALUES ('$timestamp', '".
                    $gid."', '".$component['CRAWL_TIMESTAMP']."', '".
                    $component['WEIGHT']."', '" .
                    $component['KEYWORDS']."')";
                $this->db->execute($sql);
            }
            $gid++;
        }
    }

    /**
     * Stores in DB the supplied crawl mix object
     *
     * @param array $mix an associative array repreenting the crawl mix object
     */
    function deleteCrawlMix($timestamp)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM CRAWL_MIXES WHERE MIX_TIMESTAMP='$timestamp'";
        $this->db->execute($sql);
        $sql = "DELETE FROM MIX_GROUPS WHERE MIX_TIMESTAMP='$timestamp'";
        $this->db->execute($sql);
        $sql = "DELETE FROM MIX_COMPONENTS WHERE MIX_TIMESTAMP='$timestamp'";
        $this->db->execute($sql);

    }


    /**
     *  Returns the initial sites that a new crawl will start with along with
     *  crawl parameters such as crawl order, allowed and disallowed crawl sites
     *  @param bool $use_default whether or not to use the Yioop! default
     *      crawl.ini file rather than the one created by the user.
     *  @return array  the first sites to crawl during the next crawl
     *      restrict_by_url, allowed, disallowed_sites
     */
    function getSeedInfo($use_default = false)
    {
        if(file_exists(WORK_DIRECTORY."/crawl.ini") && !$use_default) {
            $info = parse_ini_file (WORK_DIRECTORY."/crawl.ini", true);
        } else {
            $info =
                parse_ini_file (BASE_DIR."/configs/default_crawl.ini", true);
        }

        return $info;
    }

    /**
     * Writes a crawl.ini file with the provided data to the user's 
     * WORK_DIRECTORY
     *
     * @param array $info an array containing information about the crawl
     * such as crawl_order, whether restricted_by_url, seed_sites, 
     * allowed_sites and disallowed_sites
     */
    function setSeedInfo($info)
    {
        if(!isset($info['general']['crawl_index'])) {
            $info['general']['crawl_index']='12345678';
        }
        if(!isset($info["general"]["arc_dir"])) {
            $info["general"]["arc_dir"] = "";
        }
        if(!isset($info["general"]["arc_type"])) {
            $info["general"]["arc_type"] = "";
        }
        $n = array();
        $n[] = <<<EOT
; ***** BEGIN LICENSE BLOCK ***** 
;  SeekQuarry/Yioop Open Source Pure PHP Search Engine, Crawler, and Indexer
;  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
;
;  This program is free software: you can redistribute it and/or modify
;  it under the terms of the GNU General Public License as published by
;  the Free Software Foundation, either version 3 of the License, or
;  (at your option) any later version.
;
;  This program is distributed in the hope that it will be useful,
;  but WITHOUT ANY WARRANTY; without even the implied warranty of
;  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;  GNU General Public License for more details.
;
;  You should have received a copy of the GNU General Public License
;  along with this program.  If not, see <http://www.gnu.org/licenses/>.
;  ***** END LICENSE BLOCK ***** 
;
; crawl.ini 
;
; crawl configuration file
;
EOT;
        if(!isset($info['general']['page_range_request'])) {
            $info['general']['page_range_request'] = PAGE_RANGE_REQUEST;
        }
        if(!isset($info['general']['page_recrawl_frequency'])) {
            $info['general']['page_recrawl_frequency'] = PAGE_RECRAWL_FREQUENCY;
        }
        $n[] = '[general]';
        $n[] = "crawl_order = '".$info['general']['crawl_order']."';";
        $n[] = "crawl_type = '".$info['general']['crawl_type']."';";
        $n[] = "crawl_index = '".$info['general']['crawl_index']."';";
        $n[] = 'arc_dir = "'.$info["general"]["arc_dir"].'";';
        $n[] = 'arc_type = "'.$info["general"]["arc_type"].'";';
        $n[] = "page_recrawl_frequency = '".
            $info['general']['page_recrawl_frequency']."';";
        $n[] = "page_range_request = '".
            $info['general']['page_range_request']."';";

        $bool_string = 
            ($info['general']['restrict_sites_by_url']) ? "true" : "false";
        $n[] = "restrict_sites_by_url = $bool_string;";
        $n[] = "";

        $n[] = "[indexed_file_types]";
        if(isset($info["indexed_file_types"]['extensions'])) {
            foreach($info["indexed_file_types"]['extensions'] as $extension) {
                $n[] = "extensions[] = '$extension';";
            }
        }
        $n[] = "";

        $site_types = array('allowed_sites', 'disallowed_sites', 'seed_sites');
        foreach($site_types as $type) {
            $n[] = "[$type]";
            foreach($info[$type]['url'] as $url) {
                $n[] = "url[] = '$url';";
            }
            $n[]="";
        }
        $n[] = "[meta_words]";
        if(isset($info["meta_words"])) {
            foreach($info["meta_words"] as $word_pattern => $url_pattern) {
                $n[] = "$word_pattern = '$url_pattern';";
            }
            $n[]="";
        }
        $n[] = "[indexing_plugins]";
        if(isset($info["indexing_plugins"]['plugins'])) {
            foreach($info["indexing_plugins"]['plugins'] as $plugin) {
                $n[] = "plugins[] = '$plugin';";
            }
        }

        $out = implode("\n", $n);
        file_put_contents(WORK_DIRECTORY."/crawl.ini", $out);
    }


    /**
     * Returns the crawl parameters that were used during a given crawl
     *
     * @param string $timestamp timestamp of the crawl to load the crawl
     *      parameters of
     * @return array  the first sites to crawl during the next crawl
     *      restrict_by_url, allowed, disallowed_sites
     * @param array $machine_urls an array of urls of yioop queue servers
     *
     */
    function getCrawlSeedInfo($timestamp,  $machine_urls = NULL)
    {
        if($machine_urls != NULL && 
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            /* seed info should be same amongst all queue_servers that have it--
               only start schedule differs -- however, not all queue_servers
               necessarily have the same crawls. THus, we still query all
               machines in case only one has it.
             */
            $a_list = $this->execMachines("getCrawlSeedInfo", 
                $machine_urls, serialize($timestamp));
            if(is_array($a_list)) {
                foreach($a_list as $elt) {
                    $seed_info = unserialize(webdecode(
                        $elt[self::PAGE]));
                    if(isset($seed_info['general'])) {
                        break;
                    }
                }
            }
            return $seed_info;
        }
        $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
        $seed_info = NULL;
        if(file_exists($dir)) {
            $info = IndexArchiveBundle::getArchiveInfo($dir);
            $index_info = unserialize($info['DESCRIPTION']);
            $seed_info['general']["restrict_sites_by_url"] = 
                $index_info[self::RESTRICT_SITES_BY_URL];
            $seed_info['general']["crawl_type"] = 
                (isset($index_info[self::CRAWL_TYPE])) ?
                $index_info[self::CRAWL_TYPE] : self::WEB_CRAWL;
            $seed_info['general']["crawl_index"] = 
                (isset($index_info[self::CRAWL_INDEX])) ?
                $index_info[self::CRAWL_INDEX] : '';
            $seed_info['general']["crawl_order"] = 
                $index_info[self::CRAWL_ORDER];
            $site_types = array(
                "allowed_sites" => self::ALLOWED_SITES,
                "disallowed_sites" => self::DISALLOWED_SITES,
                "seed_sites" => self::TO_CRAWL
            );
            foreach($site_types as $type => $code) {
                if(isset($index_info[$code])) {
                    $tmp = & $index_info[$code];
                } else {
                    $tmp = array();
                }
                $seed_info[$type]['url'] =  $tmp;
            }
            $seed_info['meta_words'] = array();
            if(isset($index_info[self::META_WORDS]) ) {
                $seed_info['meta_words'] = $index_info[self::META_WORDS];
            }
            if(isset($index_info[self::INDEXING_PLUGINS])) {
                $seed_info['indexing_plugins']['plugins'] = 
                    $index_info[self::INDEXING_PLUGINS];
            }
        }
        return $seed_info;
    }

    /**
     * Changes the crawl parameters of an existing crawl
     *
     * @param string $timestamp timestamp of the crawl to change
     * @param array $new_info the new parameters
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    function setCrawlSeedInfo($timestamp, $new_info,  $machine_urls = NULL)
    {
        if($machine_urls != NULL && 
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            $params = array($timestamp, $new_info);
            $this->execMachines("setCrawlSeedInfo", 
                $machine_urls, serialize($params));
        }
        $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
        if(file_exists($dir)) {
            $info = IndexArchiveBundle::getArchiveInfo($dir);
            $index_info = unserialize($info['DESCRIPTION']);
            if(isset($new_info['general']["restrict_sites_by_url"])) {
                $index_info[self::RESTRICT_SITES_BY_URL] = 
                    $new_info['general']["restrict_sites_by_url"];
            }
            $updatable_site_info = array(
                "allowed_sites" => self::ALLOWED_SITES,
                "disallowed_sites" => self::DISALLOWED_SITES
            );
            foreach($updatable_site_info as $type => $code) {
                if(isset($new_info[$type])) {
                    $index_info[$code] = $new_info[$type]['url'];
                }
            }
            if(isset($new_info['meta_words']) ) {
                $index_info[self::META_WORDS] = $new_info['meta_words'];
            }
            if(isset($new_info['indexing_plugins']) ) {
                $index_info[self::INDEXING_PLUGINS] = 
                    $new_info['indexing_plugins']['plugins'];
            }
            $info['DESCRIPTION'] = serialize($index_info);
            IndexArchiveBundle::setArchiveInfo($dir, $info);
        }
    }



    /**
     * Get a description associated with a Web Crawl or Crawl Mix
     *
     * @param int $timestamp of crawl or mix in question
     * @param array $machine_urls an array of urls of yioop queue servers
     *
     * @return array associative array containing item DESCRIPTION
     */
    function getInfoTimestamp($timestamp, $machine_urls = NULL)
    {
        $is_mix = $this->isCrawlMix($timestamp);
        $info = array();
        if($is_mix) {
            $this->db->selectDB(DB_NAME);
            $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
                " MIX_TIMESTAMP='$timestamp'";
            $result = $this->db->execute($sql);
            $mix =  $this->db->fetchArray($result);
            $info['TIMESTAMP'] = $timestamp;
            $info['DESCRIPTION'] = $mix['MIX_NAME'];
            $info['IS_MIX'] = true;
        } else {
            if($machine_urls != NULL && 
                !$this->isSingleLocalhost($machine_urls, $timestamp)) {
                $cache_file = CRAWL_DIR."/cache/".self::network_base_name.
                    $timestamp.".txt";
                if(file_exists($cache_file) && filemtime($cache_file) 
                    + 300 > time() ) {
                    return unserialize(file_get_contents($cache_file));
                }
                $info_lists = $this->execMachines("getInfoTimestamp", 
                    $machine_urls, serialize($timestamp));

                $info = array();
                $info['DESCRIPTION'] = "";
                $info["COUNT"] = 0;
                $info['VISITED_URLS_COUNT'] = 0;
                foreach($info_lists as $info_list) {
                    $a_info = unserialize(webdecode(
                        $info_list[self::PAGE]));
                    if(isset($a_info['DESCRIPTION'])) {
                        $info['DESCRIPTION'] = $a_info['DESCRIPTION'];
                    }
                    if(isset($a_info['VISITED_URLS_COUNT'])) {
                        $info['VISITED_URLS_COUNT'] += 
                            $a_info['VISITED_URLS_COUNT'];
                    }
                    if(isset($a_info['COUNT'])) {
                        $info['COUNT'] += 
                            $a_info['COUNT'];
                    }
                }
                file_put_contents($cache_file, serialize($info));
                return $info;
            }
            $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
            if(file_exists($dir)) {
                $info = IndexArchiveBundle::getArchiveInfo($dir);
                if(isset($info['DESCRIPTION'])) {
                    $tmp = unserialize($info['DESCRIPTION']);
                    $info['DESCRIPTION'] = $tmp['DESCRIPTION'];
                }
            }
        }

        return $info;
    }

    /**
     * Deletes the crawl with the supplied timestamp if it exists. Also
     * deletes any crawl mixes making use of this crawl
     *
     * @param string $timestamp a Unix timestamp
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    function deleteCrawl($timestamp, $machine_urls = NULL)
    {
        if($machine_urls != NULL && 
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            //get rid of cache info on Name machine
            $mask = CRAWL_DIR."/cache/".self::network_crawllist_base_name.
                "*.txt";
            array_map( "unlink", glob( $mask ) );
            @unlink(CRAWL_DIR."/cache/".self::network_base_name.
                "$timestamp.txt");
            @unlink(CRAWL_DIR."/cache/".self::statistics_base_name.
                "$timestamp.txt");
            if(!in_array(NAME_SERVER, $machine_urls)) {
                array_unshift($machine_urls, NAME_SERVER);
            }
            //now get rid of files on queue_servers
            $this->execMachines("deleteCrawl", 
                $machine_urls, serialize($timestamp));
            return;
        }

        $this->db->unlinkRecursive(
            CRAWL_DIR.'/cache/'.self::index_data_base_name . $timestamp, true);
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/schedules/'.self::index_data_base_name .
            $timestamp, true);
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/schedules/' . self::schedule_data_base_name.$timestamp,
            true);
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/schedules/'.self::robot_data_base_name.
            $timestamp, true);

        $this->db->selectDB(DB_NAME);
        $sql = "SELECT DISTINCT MIX_TIMESTAMP FROM MIX_COMPONENTS WHERE ".
            " CRAWL_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        $rows = array();
        while($rows[] =  $this->db->fetchArray($result)) ;

        foreach($rows as $row) {
            $this->deleteCrawlMix($row['MIX_TIMESTAMP']);
        }
        $current_timestamp = $this->getCurrentIndexDatabaseName();
        if($current_timestamp == $timestamp) {
            $this->db->execute("DELETE FROM CURRENT_WEB_INDEX");
        }
    }

    /**
     * Used to send a message to the queue_servers to start a crawl
     *
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    function sendStartCrawlMessage($crawl_params, $seed_info = NULL, 
        $machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $params = array($crawl_params, $seed_info);
            $timestamp = $crawl_params[self::CRAWL_TIME];
            file_put_contents(CRAWL_DIR."/schedules/network_status.txt",
                serialize($timestamp));
            $this->execMachines("sendStartCrawlMessage", 
                $machine_urls, serialize($params));
            return;
        }

        $info_string = serialize($crawl_params);
        file_put_contents(
            CRAWL_DIR."/schedules/queue_server_messages.txt", 
            $info_string);
        chmod(CRAWL_DIR."/schedules/queue_server_messages.txt",
            0777);
        if($seed_info != NULL) {
            $scheduler_info[self::HASH_SEEN_URLS] = array();

            foreach ($seed_info['seed_sites']['url'] as $site) {
                $scheduler_info[self::TO_CRAWL][] = array($site, 1.0);
            }
            $scheduler_string = "\n".webencode(
                gzcompress(serialize($scheduler_info)));
            file_put_contents(
                CRAWL_DIR."/schedules/".self::schedule_start_name,
                $scheduler_string);
        }
    }

    /**
     * Used to send a message to the queue_servers to stop a crawl
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    function sendStopCrawlMessage($machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            @unlink(CRAWL_DIR."/schedules/network_status.txt");
            $this->execMachines("sendStopCrawlMessage", $machine_urls);
            return;
        }

        $info = array();
        $info[self::STATUS] = "STOP_CRAWL";
        $info_string = serialize($info);
        file_put_contents(
            CRAWL_DIR."/schedules/queue_server_messages.txt", 
            $info_string);
    }

    /**
     * Gets a list of all index archives of crawls that have been conducted
     * 
     * @param bool $return_arc_bundles whether index bundles used for indexing
     *      arc or other archive bundles should be included in the lsit
     * @param bool $return_recrawls whether index archive bundles generated as
     *      a result of recrawling should be included in the result
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param bool $cache whether to try to get/set the data to a cache file
     *
     * @return array Available IndexArchiveBundle directories and 
     *      their meta information this meta information includes the time of 
     *      the crawl, its description, the number of pages downloaded, and the 
     *      number of partitions used in storing the inverted index
     */
    function getCrawlList($return_arc_bundles = false, $return_recrawls = false,
        $machine_urls = NULL, $cache = false)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $pre_arg = ($return_arc_bundles && $return_recrawls) ? 3 :
                ($return_recrawls) ? 2 : ($return_arc_bundles) ? 1 : 0;
            $cache_file = CRAWL_DIR."/cache/".self::network_crawllist_base_name.
                "$pre_arg.txt";
            if($cache && file_exists($cache_file) && filemtime($cache_file) 
                + 300 > time() ) {
                return unserialize(file_get_contents($cache_file));
            }
            $arg = "arg=$pre_arg";
            $list_strings = $this->execMachines("getCrawlList", 
                $machine_urls, $arg);
            $list = $this->aggregateCrawlList($list_strings);
            if($cache) {
                file_put_contents($cache_file, serialize($list));
            }
            return $list;
        }

        $list = array();
        $dirs = glob(CRAWL_DIR.'/cache/'.self::index_data_base_name.
            '*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            $crawl = array();
            $pre_timestamp = strstr($dir, self::index_data_base_name);
            $crawl['CRAWL_TIME'] = 
                substr($pre_timestamp, strlen(self::index_data_base_name));
            $info = IndexArchiveBundle::getArchiveInfo($dir);
            $index_info = @unserialize($info['DESCRIPTION']);
            $crawl['DESCRIPTION'] = "";
            if(!$return_recrawls && 
                isset($index_info[self::CRAWL_TYPE]) && 
                $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                continue;
            } else if($return_recrawls  && 
                isset($index_info[self::CRAWL_TYPE]) && 
                $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                $crawl['DESCRIPTION'] = "RECRAWL::";
            }
            $schedules = glob(CRAWL_DIR.'/schedules/'.
                self::schedule_data_base_name.$crawl['CRAWL_TIME'].
                '/*/At*.txt');
            $crawl['RESUMABLE'] = (count($schedules) > 0) ? true: false;
            if(isset($index_info['DESCRIPTION'])) {
                $crawl['DESCRIPTION'] .= $index_info['DESCRIPTION'];
            }
            $crawl['VISITED_URLS_COUNT'] = 
                isset($info['VISITED_URLS_COUNT']) ?
                $info['VISITED_URLS_COUNT'] : 0;
            $crawl['COUNT'] = (isset($info['COUNT'])) ? $info['COUNT'] :0;
            $crawl['NUM_DOCS_PER_PARTITION'] = 
                (isset($info['NUM_DOCS_PER_PARTITION'])) ?
                $info['NUM_DOCS_PER_PARTITION'] : 0;
            $crawl['WRITE_PARTITION'] = 
                (isset($info['WRITE_PARTITION'])) ?
                $info['WRITE_PARTITION'] : 0;
            $list[] = $crawl;
        }

        if($return_arc_bundles) {
            $dirs = glob(CRAWL_DIR.'/cache/archives/*', GLOB_ONLYDIR);
            foreach($dirs as $dir) {
                $crawl = array();
                $crawl['CRAWL_TIME'] = strval(filectime($dir));
                $crawl['DESCRIPTION'] = "ARCFILE::";
                $crawl['ARC_DIR'] = $dir;
                $ini_file = "$dir/arc_description.ini";
                if(!file_exists($ini_file)) {
                    continue;
                } else {
                    $ini = parse_ini_file($ini_file);
                    $crawl['ARC_TYPE'] = $ini['arc_type'];
                    $crawl['DESCRIPTION'] .= $ini['description'];
                }
                $crawl['VISITED_URLS_COUNT'] = 0;
                $crawl['COUNT'] = 0;
                $crawl['NUM_DOCS_PER_PARTITION'] = 0;
                $crawl['WRITE_PARTITION'] = 0;
                $list[] = $crawl;
            }
        }

        return $list;
    }

    /**
     * When @see getCrawlList() is used in a multi-queue_server this method
     * used to integrate the crawl lists received by the different machines
     *
     * @param array $list_strings serialized crawl list data from different
     *  queue_servers
     * @param string $data_field field of $list_strings to use for data
     * @return array list of crawls and their meta data
     */
    function aggregateCrawlList($list_strings, $data_field = NULL)
    {
        $pre_list = array();
        foreach($list_strings as $list_string) {
            $a_list = unserialize(webdecode(
                $list_string[self::PAGE]));
            if($data_field != NULL) {
                $a_list = $a_list[$data_field];
            }
            if(is_array($a_list)) {
                foreach($a_list as $elt) {
                    $timestamp = $elt['CRAWL_TIME'];
                    if(!isset($pre_list[$timestamp])) {
                        $pre_list[$timestamp] = $elt;
                    } else {
                        if(isset($elt["DESCRIPTION"]) && 
                            $elt["DESCRIPTION"] != "") {
                            $pre_list[$timestamp]["DESCRIPTION"] =
                                $elt["DESCRIPTION"];
                        }
                        $pre_list[$timestamp]["VISITED_URLS_COUNT"] +=
                            $elt["VISITED_URLS_COUNT"];
                        $pre_list[$timestamp]["COUNT"] +=
                            $elt["COUNT"];
                        $pre_list[$timestamp]['RESUMABLE'] |= $elt['RESUMABLE'];
                    }
                }
            }
        }
        $list = array_values($pre_list);
        return $list;
    }

    /**
     * Determines if the length of time since any of the fetchers has spoken
     * with any of the queue_servers has exceeded CRAWL_TIME_OUT. If so,
     * typically the caller of this method would do something such as officially
     * stop the crawl.
     *
     * @param array $list_strings serialized crawl list data from different
     *   queue_servers
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return bool whether the current crawl is stalled or not
     */
    function crawlStalled($machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $outputs = $this->execMachines("crawlStalled", $machine_urls);
            return $this->aggregateStalled($outputs);
        }

        if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
            /* assume if status not updated for CRAWL_TIME_OUT
               crawl not active (do check for both scheduler and indexer) */
            if(filemtime(
                CRAWL_DIR."/schedules/crawl_status.txt") + 
                    CRAWL_TIME_OUT < time() ) { 
                return true;
            }
            $schedule_status_exists = 
                file_exists(CRAWL_DIR."/schedules/schedule_status.txt");
            if($schedule_status_exists &&
                filemtime(CRAWL_DIR."/schedules/schedule_status.txt") + 
                    CRAWL_TIME_OUT < time() ) { 
                return true;
            }
        }
        return false;
    }

    /**
     * When @see crawlStalled() is used in a multi-queue_server this method
     * used to integrate the stalled information received by the different 
     * machines
     *
     * @param array $stall_statuses contains web encoded serialized data one
     *  one field of which has the boolean data concerning stalled statis
     *
     * @param string $data_field field of $stall_statuses to use for data
     *      if NULL then each element of $stall_statuses is a wen encoded
     *      serialized boolean
     * @return bool true if at list one queue_server has heard from one
     *      fetcher within the time out period
     */
    function aggregateStalled($stall_statuses, $data_field = NULL)
    {
        foreach($stall_statuses as $status) {
            $stall_status = unserialize(webdecode($status[self::PAGE]));
            if($data_field != NULL) {
                $stall_status = $stall_status[$data_field];
            }
            if($stall_status === true) {
                return true;
            }
        }
        return false;
    }

    /**
     *  Returns data about current crawl such as DESCRIPTION, TIMESTAMP,
     *  peak memory of various processes, most recent fetcher, most recent
     *  urls, urls seen, urls visited, etc.
     *
     *  @param array $machine_urls an array of urls of yioop queue servers
     *      on which the crawl is being conducted
     *  @return array associative array of the said data
     */
    function crawlStatus($machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $status_strings = $this->execMachines("crawlStatus", $machine_urls);
            return $this->aggregateStatuses($status_strings);
        }

        $data = array();
        $crawl_status_exists = 
            file_exists(CRAWL_DIR."/schedules/crawl_status.txt");
        if($crawl_status_exists) {
            $crawl_status = 
                @unserialize(file_get_contents(
                    CRAWL_DIR."/schedules/crawl_status.txt"));
        }
        $schedule_status_exists = 
            file_exists(CRAWL_DIR."/schedules/schedule_status.txt");
        if($schedule_status_exists) {
            $schedule_status = 
                @unserialize(file_get_contents(
                    CRAWL_DIR."/schedules/schedule_status.txt"));
            if(isset($schedule_status[self::TYPE]) &&
                $schedule_status[self::TYPE] == self::SCHEDULER) {
                $data['SCHEDULER_PEAK_MEMORY'] = 
                    isset($schedule_status[self::MEMORY_USAGE]) ?
                    $schedule_status[self::MEMORY_USAGE] : 0;
            } 
        }

        $data = (isset($crawl_status) && is_array($crawl_status)) ? 
            array_merge($data, $crawl_status) : $data;

        if(isset($data['VISITED_COUNT_HISTORY']) && 
            count($data['VISITED_COUNT_HISTORY']) > 1) {
            $recent = array_shift($data['VISITED_COUNT_HISTORY']);
            $data["MOST_RECENT_TIMESTAMP"] = $recent[0];
            $oldest = array_pop($data['VISITED_COUNT_HISTORY']);
            unset($data['VISITED_COUNT_HISTORY']);
            $change_in_time_hours = floatval(time() - $oldest[0])/3600.;
            $change_in_urls = $recent[1] - $oldest[1];
            $data['VISITED_URLS_COUNT_PER_HOUR'] = ($change_in_time_hours > 0) ?
                $change_in_urls/$change_in_time_hours : 0;
        } else {
            $data['VISITED_URLS_COUNT_PER_HOUR'] = 0;
        }

        return $data;
    }

    /**
     * When @see crawlStatus() is used in a multi-queue_server this method
     * used to integrate the status information received by the different 
     * machines
     *
     * @param array $status_strings
     * @param string $data_field field of $status_strings to use for data
     * @return array associative array of DESCRIPTION, TIMESTAMP,
     *  peak memory of various processes, most recent fetcher, most recent
     *  urls, urls seen, urls visited, etc.
     */
    function aggregateStatuses($status_strings, $data_field = NULL)
    {
        $status['WEBAPP_PEAK_MEMORY'] = 0;
        $status['FETCHER_PEAK_MEMORY'] = 0;
        $status['QUEUE_PEAK_MEMORY'] = 0;
        $status["SCHEDULER_PEAK_MEMORY"] = 0;
        $status["COUNT"] = 0;
        $status["VISITED_URLS_COUNT"] = 0;
        $status["VISITED_URLS_COUNT_PER_HOUR"] = 0;
        $status["MOST_RECENT_TIMESTAMP"] = 0;
        $status["DESCRIPTION"] = "";
        $status['MOST_RECENT_FETCHER'] = "";
        $status['MOST_RECENT_URLS_SEEN'] = array();
        $status['CRAWL_TIME'] = 0;

        foreach($status_strings as $status_string) {
            $a_status = unserialize(webdecode(
                    $status_string[self::PAGE]));
            if($data_field != NULL) {
                $a_status = $a_status[$data_field];
            }
            $count_fields = array("COUNT", "VISITED_URLS_COUNT_PER_HOUR",
                "VISITED_URLS_COUNT");
            foreach($count_fields as $field) {
                if(isset($a_status[$field])) {
                    $status[$field] += $a_status[$field];
                }
            }
            if(isset($a_status["CRAWL_TIME"]) && $a_status["CRAWL_TIME"] >=
                $status['CRAWL_TIME']) {
                $status['CRAWL_TIME'] = $a_status["CRAWL_TIME"];
                $text_fields = array("DESCRIPTION", "MOST_RECENT_FETCHER");
                foreach($text_fields as $field) {
                    if(isset($a_status[$field])) {
                        if($status[$field] == "" ||
                            in_array($status[$field], array("BEGIN_CRAWL",
                                "RESUME_CRAWL") )) {
                            $status[$field] = $a_status[$field];
                        }
                    }
                }
            }
            if(isset($a_status["MOST_RECENT_TIMESTAMP"]) &&
                $status["MOST_RECENT_TIMESTAMP"] <= 
                    $a_status["MOST_RECENT_TIMESTAMP"]) {
                $status["MOST_RECENT_TIMESTAMP"] = 
                    $a_status["MOST_RECENT_TIMESTAMP"];
                if(isset($a_status['MOST_RECENT_URLS_SEEN'])) {
                    $status['MOST_RECENT_URLS_SEEN'] =
                        $a_status['MOST_RECENT_URLS_SEEN'];
                }
            }
            $memory_fields = array("WEBAPP_PEAK_MEMORY", 
                "FETCHER_PEAK_MEMORY", "QUEUE_PEAK_MEMORY", 
                "SCHEDULER_PEAK_MEMORY");
            foreach($memory_fields as $field) {
                $status[$field] = (!isset($a_status[$field])) ? 0 :
                        max($status[$field], $a_status[$field]);
            }
        }
        return $status;
    }

    /**
     *  This method is used to reduce the number of network requests
     *  needed by the crawlStatus method of admin_controller. It returns
     *  an array containing the results of the @see crawlStalled
     *  @see crawlStatus and @see getCrawlList methods
     *
     *  @param array $machine_urls an array of urls of yioop queue servers
     *  @return array containing three components one for each of the three
     *      kinds of results listed above
     */
    function combinedCrawlInfo($machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $combined_strings = 
                $this->execMachines("combinedCrawlInfo", $machine_urls);
            $combined = array();
            $combined[] = $this->aggregateStalled($combined_strings, 
                0);
            $combined[] = $this->aggregateStatuses($combined_strings, 
                1);
            $combined[] = $this->aggregateCrawlList($combined_strings, 
                2);
            return $combined;
        }

        $combined = array();
        $combined[] = $this->crawlStalled();
        $combined[] = $this->crawlStatus();
        $combined[] = $this->getCrawlList(false, true);
        return $combined;
    }

    /**
     *  Add the provided urls to the schedule directory of URLs that will
     *  be crawled
     *
     *  @param string $timestamp Unix timestamp of crawl to add to schedule of
     *  @param array $inject_urls urls to be added to the schedule of
     *      the active crawl
     *  @param array $machine_urls an array of urls of yioop queue servers
     */
    function injectUrlsCurrentCrawl($timestamp, $inject_urls, 
        $machine_urls = NULL)
    {
        if($machine_urls != NULL && 
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            $this->execMachines("injectUrlsCurrentCrawl", $machine_urls,
                serialize(array($timestamp, $inject_urls)));
            return;
        }

        $dir = CRAWL_DIR."/schedules/".
            self::schedule_data_base_name. $timestamp;
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $day = floor($timestamp/86400) - 1; 
            /* want before all other schedules, 
               execute next */
        $dir .= "/$day";
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $count = count($inject_urls);
        if($count > 0 ) {
            $now = time();
            $schedule_data = array();
            $schedule_data[self::SCHEDULE_TIME] = 
                $timestamp;
            $schedule_data[self::TO_CRAWL] = array();
            for($i = 0; $i < $count; $i++) {
                $url = $inject_urls[$i];
                $hash = crawlHash($now.$url);
                $schedule_data[self::TO_CRAWL][] = 
                    array($url, 1, $hash);
            }
            $data_string = webencode(
                gzcompress(serialize($schedule_data)));
            $data_hash = crawlHash($data_string);
            file_put_contents($dir."/At1From127-0-0-1".
                "WithHash$data_hash.txt", $data_string);
            return true;
        }
        return false;
    }

    /**
     *  Computes for each word in an array of words a count of the total number
     *  of times it occurs in this crawl model's default index.
     *
     *  @param array $words words to find the counts for
     *  @param array $machine_urls machines to invoke this command on
     *  @return array associative array of word => counts
     */
     function countWords($words, $machine_urls = NULL)
     {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $count_strings = $this->execMachines("countWords", $machine_urls,
                serialize(array($words, $this->index_name)));
            $word_counts = array();
            foreach($count_strings as $count_string) {
                $a_word_counts = unserialize(webdecode(
                        $count_string[self::PAGE]));
                if(is_array($a_word_counts)) {
                    foreach($a_word_counts as $word => $count) {
                        $word_counts[$word] = (isset($word_counts[$word])) ?
                            $word_counts[$word] + $count : $count;
                    }
                }
            }
            return $word_counts;
        }

        $index_archive_name = self::index_data_base_name . $this->index_name;

        $index_archive =
            new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);

        $hashes = array();
        $lookup = array();
        foreach($words as $word) {
            $tmp = crawlHash($word);
            $hashes[] = $tmp;
            $lookup[$tmp] = $word;
        }

        $word_key_counts =
            $index_archive->countWordKeys($hashes);
        $phrases = array();

        $word_counts = array();
        if(is_array($word_key_counts ) && count($word_key_counts ) > 0) {
            foreach($word_key_counts as $word_key =>$count) {
                $word_counts[$lookup[$word_key]] = $count;
            }
        }
        return $word_counts;
     }

    /**
     *  This method is invoked by other CrawlModel (for example, CrawlModel) 
     *  methods when they want to have their method performed 
     *  on an array of other  Yioop instances. The results returned can then 
     *  be aggregated.  The invocation sequence is 
     *  crawlModelMethodA invokes execMachine with a list of 
     *  urls of other Yioop instances. execMachine makes REST requests of
     *  those instances of the given command and optional arguments
     *  This request would be handled by a CrawlController which in turn
     *  calls crawlModelMethodA on the given Yioop instance, serializes the
     *  result and gives it back to execMachine and then back to the originally
     *  calling function.
     *
     *  @param string $command the CrawlModel method to invoke on the remote 
     *      Yioop instances
     *  @param array $machine_urls machines to invoke this command on
     *  @param string additional arguments to be passed to the remote machine
     *  @return array a list of outputs from each machine that was called.
     */
    function execMachines($command, $machine_urls, $arg = NULL)
    {
        $num_machines = count($machine_urls);
        $time = time();
        $session = md5($time . AUTH_KEY);
        $query = "c=crawl&a=$command&time=$time&session=$session" . 
            "&num=$num_machines";
        if($arg != NULL) {
            $arg = webencode($arg);
            $query .= "&arg=$arg";
        }

        $sites = array();
        $post_data = array();
        $i = 0;
        foreach($machine_urls as $machine_url) {
            $sites[$i][CrawlConstants::URL] =  $machine_url;
            $post_data[$i] = $query."&i=$i";
            $i++;
        }

        $outputs = array();
        if(count($sites) > 0) {
            $outputs = FetchUrl::getPages($sites, false, 0, NULL, self::URL,
                self::PAGE, true, $post_data);
        }

        return $outputs;
    }

}
?>
