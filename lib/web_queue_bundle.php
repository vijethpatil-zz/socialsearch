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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * We use a variety of bloom filters for handling robots.txt data
 */
require_once 'bloom_filter_file.php';
/**
 * Data on which urls we've already crawled is stored in a bloom filter bundle
 */
require_once 'bloom_filter_bundle.php';
/**
 * Priority queue is used to store a 8 byte ids of urls to crawl next
 */
require_once 'priority_queue.php';
/**
 * Hash table is used to store for each id in the priority queue an offset into
 * a web archive for that urls id actual complete url
 */
require_once 'hash_table.php';
/**
 * Urls are stored in a web archive using a filter that does no compression
 */
require_once BASE_DIR.'/lib/compressors/non_compressor.php';
/**
 *  Used to store to crawl urls
 */
require_once 'web_archive.php';
/**
 *  Used for getHost function
 */
require_once 'url_parser.php';
/**
 *  Used for the crawlHash function
 */
require_once 'utility.php';
/**
 *  Needed for robot stuff
 */
require_once 'crawl_constants.php';

/**
 * Encapsulates the data structures needed to have a queue of to crawl urls
 * 
 * <pre>
 * (hash of url, weights) are stored in a PriorityQueue, 
 * (hash of url, index in PriorityQueue, offset of url in WebArchive) is stored
 * in a HashTable
 * urls are stored in a WebArchive in an uncompressed format
 * </pre>
 * 
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
class WebQueueBundle implements Notifier
{

    /**
     * The folder name of this WebQueueBundle
     * @var string
     */
    var $dir_name;
    /**
     * Number items that can be stored in a partition of the page exists filter
     * bundle 
     * @var int
     */
    var $filter_size;
    /**
     * number of entries the priority queue used by this web queue bundle
     * can store
     * @var int
     */
    var $num_urls_ram;
    /**
     * whether polling the first element of the priority queue returns the
     * smallest or largest weighted element. This is set to a constant specified
     * in PriorityQueue
     * @var int
     */
    var $min_or_max; 
    /**
     * the PriorityQueue used by this WebQueueBundle
     * @var object
     */
    var $to_crawl_queue;
    /**
     * the HashTable used by this WebQueueBundle
     * @var object
     */
    var $to_crawl_table;
    /**
     * Current count of the number of non-read operation performed on the
     * WebQueueBundles's hash table since the last time it was rebuilt.
     * @var int
     */
    var $hash_rebuild_count;
    /**
     * Number of non-read operations on the hash table before it needs to be
     * rebuilt.
     * @var int
     */
    var $max_hash_ops_before_rebuild;
    /**
     * WebArchive used to store urls that are to be crawled
     * @var object
     */
    var $to_crawl_archive;

    /**
     * BloomFilter used to keep track of which urls we've already seen
     * @var object
     */
    var $url_exists_filter_bundle;
    /**
     * BloomFilter used to store which hosts whose robots.txt file we 
     * have already download
     * @var object
     */
    var $got_robottxt_filter;
    /**
     * host-ip table used for dns look-up, comes from robot.txt data and
     * deleted with same frequency
     * @var object
     */
    var $dns_table;
    /**
     * HashTable used to store offsets into WebArchive that stores robot paths
     * @var object
     */
    var $robot_table;
    /**
     * WebArchive used to store paths coming from robots.txt files
     * @var object
     */
    var $robot_archive;
    /**
     * BloomFilter used to keep track of crawl delay in seconds for a given 
     * host
     * @var object
     */
    var $crawl_delay_filter;

    /**
     * The largest offset for the url WebArchive before we rebuild it.
     * Entries are never deleted from the url WebArchive even if they are
     * deleted from the priority queue. So when we pass this value we
     * make a new WebArchive containing only those urls which are still in
     * the queue.
     */
    const max_url_archive_offset = 200000000;

    /**
     * Number of bytes in for hash table key
     */
    const HASH_KEY_SIZE = 8;

    /**
     * 4 bytes offset,  4 bytes index, 4 bytes flags
     */
    const HASH_VALUE_SIZE = 12;

    /**
     * Length of an IPv6 ip address (IPv4 address are padded)
     */
    const IP_SIZE = 16;

    /**
     * Url type flag
     */
     const NO_FLAGS = 0;

    /**
     * Url type flag
     */
     const ROBOT = 1;

    /**
     * Url type flag
     */
     const SCHEDULABLE = 2;

    /** Size of int
     **/
    const INT_SIZE = 4;

    /**
     * Makes a WebQueueBundle with the provided parameters
     *
     * @param string $dir_name folder name used by this WebQueueBundle
     * @param int $filter_size size of each partition in the page exists
     *      BloomFilterBundle
     * @param int $num_urls_ram number of entries in ram for the priority queue
     * @param string $min_or_max when the priority queue maintain the heap
     *      property with respect to the least or the largest weight
     */
    function __construct($dir_name, 
        $filter_size, $num_urls_ram, $min_or_max) 
    {
        $this->dir_name = $dir_name;
        $this->filter_size = $filter_size;
        $this->num_urls_ram = $num_urls_ram;
        $this->min_or_max = $min_or_max;

        if(!file_exists($this->dir_name)) {
            mkdir($this->dir_name);
        }

        /*
            if we are resuming a crawl we discard the old priority queue and 
            associated hash table and archive new queue data will be read in 
            from any existing schedule
        */
        // set up the priority queue... stores (hash(url), weight) pairs.
        $this->to_crawl_queue = new PriorityQueue($dir_name."/queue.dat", 
            $num_urls_ram, self::HASH_KEY_SIZE, $min_or_max, $this, 0);

        /* set up the hash table... stores (hash(url), offset into url archive, 
          index in priority queue) triples.
         */

        /*to ensure we can always insert into table, because of how deletions 
          work we will periodically want to
          rebuild our table we will also want to give a little more than the 
          usual twice the number we want to insert slack
        */
        $this->to_crawl_table = $this->constructHashTable(
            $dir_name."/hash_table.dat", 4*$num_urls_ram);

        /* set up url archive, used to store the full text of the urls which 
           are on the priority queue
         */
        $url_archive_name = $dir_name."/url_archive". 
            NonCompressor::fileExtension();
        if(file_exists($url_archive_name)) {
            unlink($url_archive_name);
        }
        $this->to_crawl_archive = new WebArchive(
            $url_archive_name, new NonCompressor(), false, true);

        //timestamp for url filters (so can delete if get too old)
        if(!file_exists($dir_name."/url_timestamp.txt")) {
            file_put_contents($dir_name."/url_timestamp.txt", time());
        }

        //filter bundle to check if we have already visited a URL
        $this->url_exists_filter_bundle = new BloomFilterBundle(
            $dir_name."/UrlExistsFilterBundle", $filter_size);

        //timestamp for robot filters (so can delete if get too old)
        if(!file_exists($dir_name."/robot_timestamp.txt")) {
            file_put_contents($dir_name."/robot_timestamp.txt", time());
        }

        //filter to check if we have already have a copy of a robot.txt file
        if(file_exists($dir_name."/got_robottxt.ftr")) {
            $this->got_robottxt_filter = BloomFilterFile::load(
                $dir_name."/got_robottxt.ftr");

        } else {
            $this->got_robottxt_filter = new BloomFilterFile(
                $dir_name."/got_robottxt.ftr", $filter_size);
        }
        /* Hash table containing DNS cache this is cleared whenever robot 
           filters cleared
         */
        if(file_exists($dir_name."/dns_table.dat")) {
            $this->dns_table = HashTable::load($dir_name."/dns_table.dat");
        } else {
            $this->dns_table = new HashTable($dir_name."/dns_table.dat", 
                4*$num_urls_ram, self::HASH_KEY_SIZE, self::IP_SIZE);
        }
        //set up storage for robots.txt info
        $robot_archive_name = $dir_name."/robot_archive". 
            NonCompressor::fileExtension();

        $this->robot_archive = new WebArchive(
            $robot_archive_name, new NonCompressor(), false, true);

        if(file_exists($dir_name."/robot.dat")) {
            $this->robot_table = 
                HashTable::load($dir_name."/robot.dat");
        } else {
            $this->robot_table =  new HashTable($dir_name.
                "/robot.dat", 16*$num_urls_ram, 
                 self::HASH_KEY_SIZE, self::INT_SIZE);
        }


        //filter to check for and determine crawl delay
        if(file_exists($dir_name."/crawl_delay.ftr")) {
            $this->crawl_delay_filter = 
                BloomFilterFile::load($dir_name."/crawl_delay.ftr");

        } else {
            $this->crawl_delay_filter = 
                new BloomFilterFile($dir_name."/crawl_delay.ftr", $filter_size);
        }
    }

    /**
     * Adds an array of (url, weight) pairs to the WebQueueBundle.
     *
     * @param array $url_pairs a list of pairs to add
     */
    function addUrlsQueue(&$url_pairs)
    {
        $add_urls = array();
        $count = count($url_pairs);
        if( $count < 1) return;
        for($i = 0; $i < $count; $i++) {
            $add_urls[$i][0] = & $url_pairs[$i][0];
        }
        
        $objects = $this->to_crawl_archive->addObjects("offset", $add_urls);

        for($i = 0; $i < $count; $i++) {
            $url = & $url_pairs[$i][0];
            $weight = $url_pairs[$i][1];
            if(isset($objects[$i]['offset'])) {
                $offset = $objects[$i]['offset'];

                $data = packInt($offset).packInt(0).packInt(self::NO_FLAGS);

                if($this->insertHashTable(crawlHash($url, true), $data)) {
                    /* 
                       we will change 0 to priority queue index in the 
                       notify callback
                     */
                    $loc = $this->to_crawl_queue->insert(
                        crawlHash($url, true), $weight);
                } else {
                    crawlLog("Error inserting $url into hash table !!");
                }
            } else {
                crawlLog("Error inserting $url into web archive !!");
            }
        }
        
        if(isset($offset) && $offset > self::max_url_archive_offset) {
             $this->rebuildUrlTable();
        }
    }

    /**
     * Check is the url queue already contains the given url
     * @param string $url what to check
     * @return bool whether it is contained in the queue yet or not
     */
    function containsUrlQueue(&$url)
    {
        $hash_url = crawlHash($url, true);
        $lookup_url = $this->lookupHashTable($hash_url);
        return ($lookup_url == false) ? false : true;
    }

    /**
     * Adjusts the weight of the given url in the priority queue by amount delta
     *
     * In a page importance crawl. a given web page casts its votes on who
     * to crawl next by splitting its crawl money amongst its child links.
     * This entails a mechanism for adusting weights of elements in the 
     * priority queue periodically is necessary. This function is used to
     * solve this problem.
     *
     * @param string $url url whose weight in queue we want to adjust
     * @param float $delta change in weight (usually positive).
     */
    function adjustQueueWeight(&$url, $delta)
    {
        $hash_url = crawlHash($url, true);
        $data = $this->lookupHashTable($hash_url);
        if($data !== false)
        {
            $queue_index = unpackInt(substr($data, 4 , 4));

            $this->to_crawl_queue->adjustWeight($queue_index, $delta);
        } else {
          crawlLog("Can't adjust weight. Not in queue $url");
        }
    }

    /**
     * Sets the flag which provides additional information about the
     * kind of url, for a url already stored in the queue. For instance,
     * might say if it is a robots.txt url, or if the url has already 
     * passed the robots.txt test, or if it has a crawl-delay
     *
     * @param string $url url whose weight in queue we want to adjust
     * @param int $flag should be one of self::ROBOT, self::NO_FLAGS, 
     *      self::SCHEDULABLE or self::SCHEDULABLE + crawl_delay
     */
    function setQueueFlag(&$url, $flag)
    {
        $hash_url = crawlHash($url, true);
        $both = 
            $this->lookupHashTable($hash_url, HashTable::RETURN_BOTH);
        if($both !== false)
        {
            list($probe, $data) = $both;
            $non_flag = substr($data, 0 , 8);
            $new_data = $non_flag . packInt($flag);

            $this->insertHashTable($hash_url, $new_data, $probe);
        } else {
          crawlLog("Can't set flag. Not in queue $url");
        }
    }

    /**
     * Removes a url from the priority queue.
     *
     * This method would typical be called during a crawl after the given
     * url is scheduled to be crawled. It only deletes the item from
     * the bundles priority queue and hash table -- not from the web archive.
     *
     * @param string $url the url or hash of url to delete
     * @param bool $isHash flag to say whether or not is the hash of a url
     */
    function removeQueue($url, $isHash = false)
    {
        if($isHash == true) {
            $hash_url = $url;
        } else {
            $hash_url = crawlHash($url, true);
        }
        $both = 
            $this->lookupHashTable($hash_url, HashTable::RETURN_BOTH);

        if(!$both) {
            crawlLog("Not in queue $url");
            return;
        }
        list($probe, $data) = $both;

        $queue_index = unpackInt(substr($data, 4 , 4));

        $this->to_crawl_queue->poll($queue_index);

        $this->deleteHashTable($hash_url, $probe);

    }

    /**
     * Gets the url and weight of the ith entry in the priority queue
     * @param int $i entry to look up
     * @param resource $fh a file handle to the WebArchive for urls
     * @return mixed false on error, otherwise the ordered 4-tuple in an array
     */
    function peekQueue($i = 1, $fh = NULL)
    {
        $tmp = $this->to_crawl_queue->peek($i);
        if(!$tmp) {
            crawlLog("web queue peek error on index $i");
            return false;
        }

        list($hash_url, $weight) = $tmp;

        $both = $this->lookupHashTable($hash_url, HashTable::RETURN_BOTH);
        if($both === false ) {
            crawlLog("web queue hash lookup error $hash_url");
            return false;
        }
        list($probe, $data) =  $both;

        $offset = unpackInt(substr($data, 0 , 4));
        $flag = unpackInt(substr($data, 8 , 4));

        $url_obj = $this->to_crawl_archive->getObjects($offset, 1, true, $fh);


        if(isset($url_obj[0][1][0])) {
            $url = $url_obj[0][1][0];
        } else {
            $url = "LOOKUP ERROR";
        }
        return array($url, $weight, $flag, $probe);
    }

    /**
     * Pretty prints the contents of the queue bundle in order
     */
    function printContents()
    {
        $count = $this->to_crawl_queue->count;

        for($i = 1; $i <= $count; $i++) {
            list($url, $weight, $flag, $probe) = $this->peekQueue($i);
            print "$i URL: $url WEIGHT:$weight FLAG: $flag PROBE: $probe\n";
        }
    }

    /**
     * Gets the contents of the queue bundle as an array of ordered 
     * url,weight, flag triples
     * @return array a list of ordered url, weight, falg triples
     */
    function getContents()
    {
        $count = $this->to_crawl_queue->count;
        $contents = array();
        for($i = 1; $i <= $count; $i++) {
            $contents[] = $this->peekQueue($i);
        }
        return $contents;
    }

    /**
     * Makes the weight sum of the to-crawl priority queue sum to $new_total
     * @param int $new_total amount weights should sum to. All weights will be
     *      scaled by the same factor.
     */
    function normalize($new_total = NUM_URLS_QUEUE_RAM)
    {
        $this->to_crawl_queue->normalize();
    }

    //Filter and Filter Bundle Methods   

    /**
     * Opens the url WebArchive associated with this queue bundle in the
     * given read/write mode
     * @param string $mode the read/write mode to open the archive with
     * @return resource a file handle to the WebArchive file
     */
    function openUrlArchive($mode = "r")
    {
        return $this->to_crawl_archive->open($mode);
    }

    /**
     * Closes a file handle to the url WebArchive
     * @param resource $fh a valid handle to the url WebArchive file
     */
    function closeUrlArchive($fh)
    {
        $this->to_crawl_archive->close($fh);
    }

    /**
     * Adds the supplied url to the url_exists_filter_bundle
     * @param string $url url to add
     */
    function addSeenUrlFilter($url)
    {
        $this->url_exists_filter_bundle->add($url);
    }

    /**
     * Removes all url objects from $url_array which have been seen
     * @param array &$url_array objects to check if have been seen
     * @param array $field_names an array of components of a url_array element 
     * which 
     *      contains a url to check if seen
     */
    function differenceSeenUrls(&$url_array, $field_names = NULL)
    {
        $this->url_exists_filter_bundle->differenceFilter(
            $url_array, $field_names);
    }

    /**
     * Adds the supplied $host to the got_robottxt_filter
     * @param string $host url to add
     */
    function addGotRobotTxtFilter($host)
    {
        $this->got_robottxt_filter->add($host);
    }

    /**
     * Checks if we have a fresh copy of robots.txt info for $host
     * @param string $host url to check
     * @return bool whether we do or not
     */
    function containsGotRobotTxt($host)
    {
        return $this->got_robottxt_filter->contains($host);
    }

    /**
     * Adds all the paths for a host to the Robots Web Archive.
     * @param string $host name that the paths are to be added for.
     * @param array an array with two keys ALLOW and DISALLOW. For each key
     *      one has an array of paths
     */
    function addRobotPaths($host, $paths)
    {
        $paths_container = array($paths);
        $objects = $this->robot_archive->addObjects("offset", $paths_container);
        if(isset($objects[0]['offset'])) {
            $offset = $objects[0]['offset'];
            $data = packInt($offset);
            $this->robot_table->insert(crawlHash($host, true), $data);
        }
    }

    /**
     * Checks if the given $url is allowed to be crawled based on stored
     * robots.txt info.
     * @param string $url to check
     * @return bool whether it was allowed or not
     */
    function checkRobotOkay($url)
    {
        // local cache of recent robot.txt stuff
        static $robot_cache = array();
        $cache_size = 2000;
        $host = UrlParser::getHost($url);
        $path = UrlParser::getPath($url);
        $path = urldecode($path);
        $key = crawlHash($host, true);
        if(isset($robot_cache[$key])) {
            $robot_object = $robot_cache[$key];
        } else {
            $data = $this->robot_table->lookup($key);
            $offset = unpackInt($data);
            $robot_object = $this->robot_archive->getObjects($offset, 1);
            $robot_cache[$key] = $robot_object;
            if(count($robot_cache) > $cache_size) {
                array_shift($robot_cache);
            }
        }
        $robot_paths = (isset($robot_object[0][1])) ? $robot_object[0][1]
            : array(); //these should have been urldecoded in RobotProcessor
        $robots_okay = true;
        $robots_not_okay = false;
        if(isset($robot_paths[CrawlConstants::DISALLOWED_SITES])) {
            $robots_not_okay = UrlParser::isPathMemberRegexPaths($path, 
                $robot_paths[CrawlConstants::DISALLOWED_SITES]);
            $robots_okay = !$robots_not_okay;
        }
        if(isset($robot_paths[CrawlConstants::ALLOWED_SITES])) {
            $robots_okay = UrlParser::isPathMemberRegexPaths($path, 
                $robot_paths[CrawlConstants::ALLOWED_SITES]);
        }
        return $robots_okay || !$robots_not_okay;
    }

    /**
     * Gets the timestamp of the oldest robot data still stored in
     * the queue bundle
     * @return int a Unix timestamp
     */
    function getRobotTxtAge()
    {

        $creation_time = intval(
            file_get_contents($this->dir_name."/robot_timestamp.txt"));

        return (time() - $creation_time);
    }

    /**
     * Add an entry to the web_queue_bundles DNS cache
     *
     * @param string $host hostname to add to DNS Lookup table
     * @param string $ip_address in presentation format (not as int) to add
     *      to table
     */
    function addDNSCache($host, $ip_address)
    {
        $pad = "000000000000";
        $hash_host = crawlHash($host, true);
        $packed_ip = inet_pton($ip_address);
        if(strlen($packed_ip) == 4) {
            $packed_ip .= $pad;
        }
        $this->dns_table->insert($hash_host, $packed_ip);
    }

    /**
     * Add an entry to the web_queue_bundles DNS cache
     *
     * @param string $host hostname to add to DNS Lookup table
     * @return value
     */
    function dnsLookup($host)
    {
        $pad = "000000000000";
        $hash_host = crawlHash($host, true);
        $packed_ip = $this->dns_table->lookup($hash_host);
        if(!$packed_ip) return false;
        $maybe_pad = substr($packed_ip, 4);
        $maybe_ip4 = substr($packed_ip, 0, 4);
        if(strcmp($maybe_pad, $pad) == 0) {
            $ip_address = inet_ntop($maybe_ip4);
        } else {
            $ip_address = inet_ntop($packed_ip);
        }
        if(strcmp($ip_address, "0.0.0.0") == 0) {
            return false;
        }
        return $ip_address;
    }

    /**
     * Gets the timestamp of the oldest url filter data still stored in
     * the queue bundle
     * @return int a Unix timestamp
     */
    function getUrlFilterAge()
    {

        $creation_time = intval(
            file_get_contents($this->dir_name."/url_timestamp.txt"));

        return (time() - $creation_time);
    }

    /**
     * Sets the Crawl-delay of $host to passes $value in seconds
     *
     * @param string $host a host to set the Crawl-delay for
     * @param int $value a delay in seconds up to 255
     */
    function setCrawlDelay($host, $value)
    {
        $this->crawl_delay_filter->add("-1".$host); 
            //used to say a crawl delay has been set

        for($i = 0; $i < 8; $i++) {
            if(($value & 1) == 1) {
                $this->crawl_delay_filter->add("$i".$host);
            }
            $value = $value >> 1;
        }
    }

    /**
     * Gets the Crawl-delay of $host from the crawl delay bloom filter
     *
     * @param string $host site to check for a Crawl-delay
     * @return int the crawl-delay in seconds or -1 if $host has no delay
     */
    function getCrawlDelay($host)
    {
        if(!$this->crawl_delay_filter->contains("-1".$host)) {
            return -1;
        }

        $value = 0;
        for($i = 0; $i < 8; $i++) {
            if($this->crawl_delay_filter->contains("$i".$host)) {
                $value += (1 << $i);
            }
        }

        return $value;
    }

    /**
     * Mainly, a Factory style wrapper around the HashTable's constructor.
     * However, this function also sets up a rebuild frequency. It is used
     * as part of the process of keeping the to crawl table from having too
     * many entries
     *
     * @param string $name filename to store the hash table persistently
     * @param int $num_values size of HashTable's arraya
     * @return object the newly built hash table
     * @see rebuildHashTable()
     */
    function constructHashTable($name, $num_values)
    {
        $this->hash_rebuild_count = 0;
        $this->max_hash_ops_before_rebuild = floor($num_values/4);
        return new HashTable($name, $num_values, 
            self::HASH_KEY_SIZE, self::HASH_VALUE_SIZE);
    }

    /**
     * Looks up $key in the to-crawl hash table
     *
     * @param string $key the things to look up
     * @param int $return_probe_value one of self::ALWAYS_RETURN_PROBE, 
     *      self::RETURN_PROBE_ON_KEY_FOUND, self::RETURN_VALUE, or self::BOTH. 
     *      Here value means the value associated with the key and probe is
     *      either the location in the array where the key was found or
     *      the first location in the array where it was determined the
     *      key could not be found.
     * @return mixed would be string if the value is being returned, 
     *      otherwise, false if the key is not found 
     */
    function lookupHashTable($key, $return_probe_value = 
        HashTable::RETURN_VALUE)
    {
        return $this->to_crawl_table->lookup($key, $return_probe_value);
    }

    /**
     * Removes an entries from the to crawl hash table
     * @param int $probe if the location in the hash table is already known
     *      to be $probe then this variable can be used to save a lookup
     * @param string $key usually a hash of a url
     */
    function deleteHashTable($key, $probe = false)
    {
        $this->to_crawl_table->delete($key, $probe);
        $this->hash_rebuild_count++;
        if($this->hash_rebuild_count > $this->max_hash_ops_before_rebuild) {
            $this->rebuildHashTable();
        }
    }

    /**
     * Inserts the $key, $value pair into this web queue's to crawl table
     *
     * @param string $key intended to be a hash of a url
     * @param string $value intended to be offset into a webarchive for urls
     *      together with an index into the priority queue
     * @param int $probe if the location in the hash table is already known
     *      to be $probe then this variable can be used to save a lookup
     * @return bool whether the insert was a success or not
     */
    function insertHashTable($key, $value, $probe = false)
    {
        $this->hash_rebuild_count++;
        if($this->hash_rebuild_count > $this->max_hash_ops_before_rebuild) {
            $this->rebuildHashTable();
        }
        return $this->to_crawl_table->insert($key, $value, $probe);
    }

    /**
     * Makes a new HashTable without deleted rows
     *
     * The hash table in Yioop is implemented using open addressing. i.e.,
     * We store key value pair in the table itself and if there is a collision
     * we look for the next available slot. Two codes are use to indicate
     * space available in the table. One to indicate empty never used, the
     * other used to indicate empty but previously used and deleted. The reason
     * you need two codes is to ensure that if somebody inserted an item B,
     * it hashes to the same value as A and we move to the next empty slot,
     * to store B, then if we delete A we should still be able to lookup B.
     * The problem is as the table gets reused a lot, it tends to fill up
     * with a lot of deleted entries making lookup times get more and more
     * linear in the hash table size. By rebuilding the table we mitigate
     * against this problem. By choosing the rebuild frequency appropriately,
     * the amortized cost of this operation is only O(1).
     */
    function rebuildHashTable()
    {
        crawlLog("Rebuilding Hash table");
        $num_values = $this->to_crawl_table->num_values;
        $tmp_table = $this->constructHashTable(
            $this->dir_name."/tmp_table.dat", $num_values);
        $null = $this->to_crawl_table->null;
        $deleted = $this->to_crawl_table->deleted;
        
        for($i = 0; $i < $num_values; $i++) {
            list($key, $value) = $this->to_crawl_table->getEntry($i);
            if(strcmp($key, $null) != 0 
                && strcmp($key, $deleted) != 0) {
                $tmp_table->insert($key, $value);
            }
        }
        
        $this->to_crawl_table = NULL;
        gc_collect_cycles();
        if(file_exists($this->dir_name."/hash_table.dat")) {
            unlink($this->dir_name."/hash_table.dat");
            if(file_exists($this->dir_name."/tmp_table.dat")) {
                rename($this->dir_name."/tmp_table.dat", 
                    $this->dir_name."/hash_table.dat");
            }
        }
        $tmp_table->filename = $this->dir_name."/hash_table.dat";
        $this->to_crawl_table = $tmp_table; 
    }
    
   /**
    * Since offsets are integers, even if the queue is kept relatively small, 
    * periodically we will need to rebuild the archive for storing urls.
    */
    function rebuildUrlTable()
    {
        crawlLog("Rebuilding URL table");
        $dir_name = $this->dir_name;

        $count = $this->to_crawl_queue->count;
        $tmp_archive_name = $dir_name."/tmp_archive" .
            NonCompressor::fileExtension();
        $url_archive_name = $dir_name."/url_archive" .
            NonCompressor::fileExtension();
        $tmp_archive = 
            new WebArchive($tmp_archive_name, new NonCompressor(), false, true);
        
        for($i = 1; $i <= $count; $i++) {

            list($url, $weight, $flag, $probe) = $this->peekQueue($i);
            $url_container = array(array($url));
            $objects = $tmp_archive->addObjects("offset", $url_container);
            if(isset($objects[0]['offset'])) {
                $offset = $objects[0]['offset'];
            } else {
                crawlLog("Error inserting $url into rebuild url archive file");
                continue;
            }
            
            $hash_url = crawlHash($url, true);
            $data = packInt($offset).packInt($i).packInt($flag);

            $this->insertHashTable($hash_url, $data, $probe);
        }
        
        $this->to_crawl_archive = NULL;
        gc_collect_cycles();
 
        $tmp_archive->filename = $url_archive_name;
        $this->to_crawl_archive =  $tmp_archive; 

    }

    /**
     * Delete the Bloom filters used to store robots.txt file info.
     * Then construct empty new ones.
     * This is called roughly once a day so that robots files will be
     * reloaded and so the policies used won't be too old.
     */
    function emptyRobotData()
    {
        $robot_archive_name = $this->dir_name."/robot_archive". 
            NonCompressor::fileExtension();
        unlink($this->dir_name."/got_robottxt.ftr");  
        unlink($this->dir_name."/crawl_delay.ftr");
        unlink($this->dir_name."/robot.dat");
        unlink($robot_archive_name);
        $this->crawl_delay_table = array();

        file_put_contents($this->dir_name."/robot_timestamp.txt", time());

        $this->got_robottxt_filter = NULL;
        $this->crawl_delay_filter = NULL;
        $this->robot_archive = NULL;
        $this->robot_table = NULL;
        gc_collect_cycles();

        $this->got_robottxt_filter = 
            new BloomFilterFile(
                $this->dir_name."/got_robottxt.ftr", $this->filter_size);
        $this->crawl_delay_filter = 
            new BloomFilterFile(
                $this->dir_name."/crawl_delay.ftr", $this->filter_size);
        $this->robot_archive = new WebArchive(
            $robot_archive_name, new NonCompressor(), false, true);
        $this->robot_table =  new HashTable($this->dir_name.
            "/robot.dat", 8*$this->num_urls_ram, 
             self::HASH_KEY_SIZE, self::HASH_VALUE_SIZE);
    }

    /**
     * Delete the Hash table used to store DNS lookup info.
     * Then construct an empty new one.
     * This is called roughly once a day at the same time as 
     * @see emptyRobotFilters()
     */
    function emptyDNSCache()
    {
        $num_values = $this->dns_table->num_values;
        unlink($this->dir_name."/dns_table.dat");
        $this->dns_table = NULL;
        gc_collect_cycles();
        $this->dns_table = new HashTable($this->dir_name."/dns_table.dat", 
            $num_values, self::HASH_KEY_SIZE, self::IP_SIZE);
    }

    /**
     * Empty the crawled url filter for this web queue bundle; resets the
     * the timestamp of the last time this filter was emptied.
     */
    function emptyUrlFilter()
    {
        file_put_contents($this->dir_name."/url_timestamp.txt", time());

        $this->url_exists_filter_bundle->reset();
    }

    /**
     * Callback which is called when an item in the priority queue changes 
     * position. The position is updated in the hash table.
     * The priority queue stores (hash of url, weight). The hash table
     * stores (hash of url, web_archive offset to url, index priority queue).
     *
     * @param int $index new index in priority queue
     * @param array $data (hash url, weight)
     */
    function notify($index, $data)
    {
        $hash_url = $data[0];
        $both = $this->lookupHashTable($hash_url, HashTable::RETURN_BOTH);
        if($both !== false) {
            list($probe, $value) = $both;
            $packed_offset = substr($value, 0 , 4);
            $packed_flag = substr($value, 8 , 4);
            $new_data = $packed_offset.packInt($index).$packed_flag;

            $this->insertHashTable($hash_url, $new_data, $probe);
        } else {
            crawlLog("NOTIFY LOOKUP FAILED. INDEX WAS $index. DATA WAS ".
                bin2hex($data[0]));
          
        }
    }

}
?>
