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
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * 
 * Code used to manage HTTP requests from one or more URLS
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class FetchUrl implements CrawlConstants
{

    /**
     * Make multi_curl requests for an array of sites with urls
     *
     * @param array $sites  an array containing urls of pages to request
     * @param bool $timer  flag, true means print timing statistics to log
     * @param int $page_range_request maximum number of bytes to download/page
     *      0 means download all
     * @param string $key  the component of $sites[$i] that has the value of 
     *      a url to get defaults to URL
     * @param string $value component of $sites[$i] in which to store the 
     *      page that was gotten
     *  @param array $post_data for each site data to be POST'd to that site
     *
     *  @return array an updated array with the contents of those pages
     */ 

    public static function getPages($sites, $timer = false,
        $page_range_request = PAGE_RANGE_REQUEST, $temp_dir = NULL,
        $key=CrawlConstants::URL, $value = CrawlConstants::PAGE, $minimal=false,
        $post_data = NULL)
    {
        $agent_handler = curl_multi_init(); 

        $active = NULL;

        $start_time = microtime();

        if(!$minimal && $temp_dir == NULL) {
            $temp_dir = CRAWL_DIR."/temp";
            if(!file_exists($temp_dir)) {
                mkdir($temp_dir);
            }
        }

        //Set-up requests
        for($i = 0; $i < count($sites); $i++) {
            if(isset($sites[$i][$key])) {
                list($sites[$i][$key], $url, $headers) = 
                    self::prepareUrlHeaders($sites[$i][$key], $minimal);
                $sites[$i][0] = curl_init();
                if(!$minimal) {
                    $ip_holder[$i] = fopen("$temp_dir/tmp$i.txt", 'w+');
                    curl_setopt($sites[$i][0], CURLOPT_STDERR, $ip_holder[$i]);
                    curl_setopt($sites[$i][0], CURLOPT_VERBOSE, true);
                }
                curl_setopt($sites[$i][0], CURLOPT_USERAGENT, USER_AGENT);
                curl_setopt($sites[$i][0], CURLOPT_URL, $url);
                $follow = false;
                if(strcmp(substr($url,-10), "robots.txt") == 0 ) {
                    $follow = true; //wikipedia redirects their robot page. grr
                }
                curl_setopt($sites[$i][0], CURLOPT_FOLLOWLOCATION, $follow);
                curl_setopt($sites[$i][0], CURLOPT_AUTOREFERER, true);
                curl_setopt($sites[$i][0], CURLOPT_RETURNTRANSFER, true);
                curl_setopt($sites[$i][0], CURLOPT_CONNECTTIMEOUT,PAGE_TIMEOUT);
                curl_setopt($sites[$i][0], CURLOPT_TIMEOUT, PAGE_TIMEOUT);
                if(!$minimal) {
                    curl_setopt($sites[$i][0], CURLOPT_HEADER, true);
                }
                //make lighttpd happier
                curl_setopt($sites[$i][0], CURLOPT_HTTPHEADER, 
                    $headers);
                curl_setopt($sites[$i][0], CURLOPT_ENCODING, "");
                   // ^ need to set for sites like att that use gzip
                if($page_range_request > 0) {
                    curl_setopt($sites[$i][0], CURLOPT_RANGE, "0-".
                        $page_range_request);
                }
                if($post_data != NULL) {
                    curl_setopt($sites[$i][0], CURLOPT_POST, true);
                    curl_setopt($sites[$i][0], CURLOPT_POSTFIELDS, 
                        $post_data[$i]);
                }
                curl_multi_add_handle($agent_handler, $sites[$i][0]);
            }
        }

        if($timer) {
            crawlLog("  Init Get Pages ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime();
        $start = time();

        //Wait for responses
        $running=null;
        do {
            $mrc = curl_multi_exec($agent_handler, $running);
            $ready=curl_multi_select($agent_handler, 0.02);
        } while (time() - $start < PAGE_TIMEOUT &&  $running > 0 && $ready!=-1);

        if(time() - $start > PAGE_TIMEOUT) {crawlLog("  TIMED OUT!!!");}

        if($timer) {

            crawlLog("  Page Request time ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime();

        //Process returned pages
        for($i = 0; $i < count($sites); $i++) {
            if(!$minimal && isset($ip_holder[$i]) ) {
                rewind($ip_holder[$i]);
                $header = fread($ip_holder[$i], 8192);
                $ip_addresses = self::getCurlIp($header);
                fclose($ip_holder[$i]);
            }
            if(isset($sites[$i][0]) && $sites[$i][0]) { 
                // Get Data and Message Code
                $content = @curl_multi_getcontent($sites[$i][0]);
                /* 
                    If the Transfer-encoding was chunked then the Range header 
                    we sent was ignored. So we manually truncate the data
                    here
                 */
                if($page_range_request > 0) {
                    $content = substr($content, 0, $page_range_request);
                }
                if(isset($content) && !$minimal) {
                    $site = self::parseHeaderPage($content, $value);
                    $sites[$i] = array_merge($sites[$i], $site);
                    if(isset($header)) {
                        $header = substr($header, 0 ,
                            strpos($header, "\x0D\x0A\x0D\x0A") + 4);
                    } else {
                        $header = "";
                    }
                    $sites[$i][CrawlConstants::HEADER] = 
                        $header . $sites[$i][CrawlConstants::HEADER];
                    unset($header);
                } else {
                    $sites[$i][$value] = $content;
                }
                if(!$minimal) {
                    $sites[$i][self::SIZE] = @curl_getinfo($sites[$i][0],
                        CURLINFO_SIZE_DOWNLOAD);
                    $sites[$i][self::DNS_TIME] = @curl_getinfo($sites[$i][0],
                        CURLINFO_NAMELOOKUP_TIME);
                    $sites[$i][self::TOTAL_TIME] = @curl_getinfo($sites[$i][0],
                        CURLINFO_TOTAL_TIME);
                    $sites[$i][self::HTTP_CODE] = 
                        curl_getinfo($sites[$i][0], CURLINFO_HTTP_CODE);
                    if(!$sites[$i][self::HTTP_CODE]) {
                        $sites[$i][self::HTTP_CODE] = curl_error($sites[$i][0]);
                    }
                    if($ip_addresses) {
                        $sites[$i][self::IP_ADDRESSES] = $ip_addresses;
                    } else {
                        $sites[$i][self::IP_ADDRESSES] = array("0.0.0.0");
                    }

                    //Get Time, Mime type and Character encoding
                    $sites[$i][self::TIMESTAMP] = time();

                    $type_parts = 
                        explode(";", curl_getinfo($sites[$i][0], 
                            CURLINFO_CONTENT_TYPE));

                    $sites[$i][self::TYPE] = strtolower(trim($type_parts[0]));
                }

                curl_multi_remove_handle($agent_handler, $sites[$i][0]);
                // curl_close($sites[$i][0]);
            } //end big if

        } //end for

        if($timer) {
            crawlLog("  Get Page Content time ".
                (changeInMicrotime($start_time)));
        }
        curl_multi_close($agent_handler);

        return $sites;
    }

    /**
     *
     * @param string $url
     * @param bool $minimal
     */
    static function prepareUrlHeaders($url, $minimal = false)
    {
        $url = str_replace("&amp;", "&", $url);
        /* in queue_server we added the ip (if available)
          after the url followed by ###
         */
        $headers = array();
        if(!$minimal) {
            $url_ip_parts = explode("###", $url);
            if(count($url_ip_parts) > 1) {
                $ip_address = urldecode(array_pop($url_ip_parts));
                $len = strlen(inet_pton($ip_address));
                if($len == 4 || $len == 16) {
                    if($len == 16) {
                        $ip_address= "[$ip_address]";
                    }
                    if(count($url_ip_parts) > 1) {
                        $url = implode("###", $url_ip_parts);
                    } else {
                        $url = $url_ip_parts[0];
                    }
                    $url_parts = @parse_url($url);
                    if(isset($url_parts['host'])) {
                        $cnt = 1;
                        $url_with_ip_if_possible = 
                            str_replace($url_parts['host'], $ip_address ,$url,
                                 $cnt);
                        if($cnt != 1) {
                            $url_with_ip_if_possible = $url;
                        } else {
                            $headers[] = "Host:".$url_parts['host'];
                        }
                    }
                } else {
                    $url_with_ip_if_possible = $url;
                }
            } else {
                $url_with_ip_if_possible = $url;
            }
        } else {
            $url_with_ip_if_possible = $url;
        }
        $headers[] = 'Expect:';
        $results = array($url, $url_with_ip_if_possible, $headers);
        return $results;
    }

    /**
     * Computes a hash of a string containing page data for use in
     * deduplication of pages with similar content
     *
     *  @param string &$page  web page data
     *  @return string 8 byte hash to identify page contents
     */
    public static function computePageHash(&$page)
    {
        /* to do dedup we strip script, noscript, and style tags 
           as well as their content, then we strip tags, get rid 
           of whitespace and hash
         */
        $strip_array = 
            array('@<script[^>]*?>.*?</script>@si', 
                '@<noscript[^>]*?>.*?</noscript>@si', 
                '@<style[^>]*?>.*?</style>@si');
        $dedup_string = preg_replace(
            $strip_array, '', $page);
        $dedup_string_old = preg_replace(
            '/\W+/', '', $dedup_string);
        $dedup_string = strip_tags($dedup_string_old);
        if($dedup_string == "") {
            $dedup_string = $dedup_string_old;
        }
        $dedup_string = preg_replace(
            '/\W+/', '', $dedup_string);

        return crawlHash($dedup_string, true);
    }

    /**
     *  Splits an http response document into the http headers sent
     *  and the web page returned. Parses out useful information from
     *  the header and return an array of these two parts and the useful info.
     *
     *  @param string &$header_and_page reference to string of downloaded data
     *  @param string $value field to store the page protion of page
     *  @return array info array consisting of a header, page for an http
     *      response, as well as parsed from the header the server, server
     *      version, operating system, encoding, and date information.
     */
    public static function parseHeaderPage(&$header_and_page, 
        $value=CrawlConstants::PAGE)
    { 
        $new_offset = 0;
        // header will include all redirect headers
        $site = array();
        $site[CrawlConstants::LOCATION] = array();
        do {
            $continue = false;
            $CRLFCRLF = strpos($header_and_page, "\x0D\x0A\x0D\x0A", 
                $new_offset);
            $LFLF = strpos($header_and_page, "\x0A\x0A", $new_offset);
            //either two CRLF (what spec says) or two LF's to be safe
            $old_offset = $new_offset;
            $header_offset = ($CRLFCRLF > 0) ? $CRLFCRLF : $LFLF;
            $new_offset = ($CRLFCRLF > 0) ? $header_offset + 4 
                : $header_offset + 2;
            $redirect_pos = stripos($header_and_page, 'Location:', $old_offset);
            $redirect_str = "Location:";
            if($redirect_pos === false) {
                $redirect_pos = 
                    stripos($header_and_page, 'Refresh:', $old_offset);
                $redirect_str = "Refresh:";
            }
            if(isset($header_and_page[$redirect_pos - 1]) &&
                ord($header_and_page[$redirect_pos - 1]) > 32) {
                $redirect_pos = $new_offset; //ignore X-XRDS-Location header
            } else if($redirect_pos !== false && $redirect_pos < $new_offset){
                $redirect_pos += strlen($redirect_str);
                $pre_line = substr($header_and_page, $redirect_pos,
                    strpos($header_and_page, "\n", $redirect_pos) - 
                    $redirect_pos);
                $loc = @trim($pre_line);
                if(strlen($loc) > 0) {
                    $site[CrawlConstants::LOCATION][] = @$loc;
                }
                $continue = true;
            }
        } while($continue);


        $site[CrawlConstants::HEADER] = 
            substr($header_and_page, 0, $header_offset);
        $site[$value] = ltrim(substr($header_and_page, $header_offset));

        $lines = explode("\n", $site[CrawlConstants::HEADER]);
        $first_line = array_shift($lines);
        $response = preg_split("/(\s+)/", $first_line);
        $site[CrawlConstants::HTTP_CODE] = @trim($response[1]);
        $site[CrawlConstants::ROBOT_METAS] = array();
        foreach($lines as $line) {
            $line = trim($line);
            if(stristr($line, 'Server:')) {
                $server_parts = preg_split("/Server\:/i", $line);
                $server_name_parts = @explode("/", $server_parts[1]);
                $site[CrawlConstants::SERVER] = @trim($server_name_parts[0]);
                if(isset($server_name_parts[1])) {
                    $version_parts = explode("(", $server_name_parts[1]);
                    $site[CrawlConstants::SERVER_VERSION] = 
                        @trim($version_parts[0]);
                    if(isset($version_parts[1])) {
                        $os_parts = explode(")", $version_parts[1]);
                        $site[CrawlConstants::OPERATING_SYSTEM] =
                            @trim($os_parts[0]);
                    }
                }
            }
            if(stristr($line, 'charset=')) {
                $line_parts = preg_split("/charset\=/i", $line);
                $site[CrawlConstants::ENCODING] = 
                    strtoupper(@trim($line_parts[1]));
            }
            if(stristr($line, 'Last-Modified:')) {
                $line_parts = preg_split("/Last\-Modified\:/i", $line);
                $site[CrawlConstants::MODIFIED] = 
                    strtotime(@trim($line_parts[1]));
            }
            if(stristr($line, 'X-Robots-Tag:')) {
                $line_parts = preg_split("/X\-Robots\-Tag\:/i", $line);
                $robot_metas = explode(",", $line_parts[1]);
                foreach($robot_metas as $robot_meta) {
                    $site[CrawlConstants::ROBOT_METAS][] = strtoupper(
                        trim($robot_meta));
                }
            }
        }
        /*
           If the doc is HTML and it uses a http-equiv to set the encoding
           then we override what the server says (if anything). As we
           are going to convert to UTF-8 we remove the charset info
           from the meta tag so cached pages will display correctly and
           redirects without char encoding won't be given a different hash.
         */
        $end_head = stripos($site[$value], "</head");
        if($end_head) {
            $reg = "charset(\s*)=(\s*)(\'|\")?((\w|\-)+)(\'|\")?";
            mb_regex_encoding("UTF-8");
            mb_ereg_search_init($site[$value]);
            mb_ereg_search($reg);
            $match = mb_ereg_search_getregs();
            if(isset($match[0])) {
                $len_c = mb_strlen($match[0]);
                if(($match[6] == "'" || $match[6] == '"') &&
                   $match[3] != $match[6]) {
                    $len_c--;
                }
                $start_charset = strpos($site[$value], $match[0]);
                if($start_charset + $len_c < $end_head) {
                    if(isset($match[4])) {
                        $site[CrawlConstants::ENCODING] = strtoupper(
                            $match[4]);
                        $site[$value] = substr_replace(
                            $site[$value], "", $start_charset, 
                            $len_c);
                    }
                }
            }
        }
        if(!isset($site[CrawlConstants::ENCODING])) {
            //else  fallback to auto-detect
            $site[CrawlConstants::ENCODING] =
                mb_detect_encoding($site[$value], 'auto');
        }

        if(!isset($site[CrawlConstants::SERVER]) ) {
            $site[CrawlConstants::SERVER] = "unknown";
        }
        return $site;
    }

    /**
     * Computes the IP address from http get-responser header
     *
     * @param string contains complete transcript of HTTP get/response
     * @return string IPv4 address as a string of dot separated quads.
     */
    static function getCurlIp($header) 
    {
        if (preg_match_all('/Trying\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', 
            $header, $matches)) {
            return array_unique($matches[1]);
        } else {
            return false;
        }
    }


    /**
     *  Make a curl request for the provide url
     *
     *  @param string $site  url of page to request
     *  @param string $post_data  any data to be POST'd to the URL
     * 
     *  @return string the contents of what the curl request fetched
     */
    public static function getPage($site, $post_data = NULL) 
    {
        static $agents = array();
        $MAX_SIZE = 50;
        $host = @parse_url($site,PHP_URL_HOST);
        if($host !== false) {
            if(count($agents) > $MAX_SIZE) {
                array_shift($agents);
            }
            if(!isset($agents[$host])) {
                $agents[$host] = curl_init();
            }
        }
        crawlLog("Init curl request");
        curl_setopt($agents[$host], CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($agents[$host], CURLOPT_URL, $site);

        curl_setopt($agents[$host], CURLOPT_AUTOREFERER, true);
        curl_setopt($agents[$host], CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($agents[$host], CURLOPT_NOSIGNAL, true);
        curl_setopt($agents[$host], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($agents[$host], CURLOPT_FAILONERROR, true);
        curl_setopt($agents[$host], CURLOPT_TIMEOUT, PAGE_TIMEOUT);
        curl_setopt($agents[$host], CURLOPT_CONNECTTIMEOUT, PAGE_TIMEOUT);
        //make lighttpd happier
        curl_setopt($agents[$host], CURLOPT_HTTPHEADER, array('Expect:'));
        if($post_data != NULL) {
            curl_setopt($agents[$host], CURLOPT_POST, true);
            curl_setopt($agents[$host], CURLOPT_POSTFIELDS, $post_data);
        } else {
            // since we are caching agents, need to do this so doesn't get stuck
            // as post and so query string ignored for get's
            curl_setopt($agents[$host], CURLOPT_HTTPGET, true);
        }
        crawlLog("Set curl options");
        $response = curl_exec($agents[$host]);
        curl_setopt($agents[$host], CURLOPT_POSTFIELDS, "");
        crawlLog("Done curl exec");
        return $response;
    }
}
?>
