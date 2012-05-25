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
 * Library of functions used to manipulate and to extract components from urls 
 *
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
  
class UrlParser 
{

    /**
     * Checks if the url scheme is either http or https.
     *
     * @param string $url  the url to check
     * @return bool returns true if it is either http or https and false 
     *      otherwise
     */
    static function isSchemeHttpOrHttps($url) 
    {
        $url_parts = @parse_url($url);

        if(isset($url_parts['scheme']) && $url_parts['scheme'] != "http" && 
            $url_parts['scheme'] != "https") {
            return false;
        }

        return true; 

    }

    /**
     * Checks if the url has a host part.
     *
     * @param string $url  the url to check
     * @return bool true if it does; false otherwise
     */
    static function hasHostUrl($url) 
    {
       $url_parts = @parse_url($url);

       return isset($url_parts['host']);
    }

    /**
     * Get the host name portion of a url if present; if not return false
     *
     * @param string $url the url to parse
     * @param bool $with_login whether to include user,password,port if present
     * @return the host portion of the url if present; false otherwise
     */
    static function getHost($url, $with_login_and_port = true) 
    {
        $url_parts = @parse_url($url);

        if(!isset($url_parts['scheme']) ) {return false;}
        $host_url = $url_parts['scheme'].'://';

        //handles common typo http:/yahoo.com rather than http://yahoo.com
        if(!isset($url_parts['host'])) {
            if(isset($url_parts['path'])) {
                $url_parts = @parse_url($url_parts['scheme'].":/".
                    $url_parts['path']);
                if(!isset($url_parts['host'])) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if($with_login_and_port &&
            isset($url_parts['user']) && isset($url_parts['pass'])) {
            $host_url .= $url_parts['user'].":".$url_parts['pass']."@";
        }

        if(strlen($url_parts['host']) <= 0) { return false; }

        $host_url .= $url_parts['host'];

        if($with_login_and_port && isset($url_parts['port'])) {
            $host_url .= ":".$url_parts['port'];
        }

        return $host_url;
    }

    /**
     *  Attempts to guess the language tag based on url
     *
     *  @param string $url the url to parse
     *  @return the top level domain if present; false otherwise
     */
    static function getLang($url) 
    {
        $LANG = array(
            "com" => 'en',
            "edu" => 'en',
            "gov" => 'en',
            "mil" => 'en',
            "org" => 'en',
            "net" => 'en',
            'us' => 'en',
            "uk" => 'en',
            "ca" => 'en',
            "au" => 'en',
            "bz" => 'en',
            "ie" => 'en',
            "jm" => 'en',
            "nz" => 'en',
            "za" => 'en',
            "zw" => 'en',
            "tt" => 'en',
            "eg" => 'ar',
            "dz" => 'ar',
            "bh" => 'ar',
            "jo" => 'ar',
            "kw" => 'ar',
            "lb" => 'ar',
            "iq" => 'ar',
            "ma" => 'ar',
            "om" => 'ar',
            "qa" => 'ar',
            "sa" => 'ar',
            "sy" => 'ar',
            "tn" => 'ar',
            "ae" => 'ar',
            "ye" => 'ar',
            "de" => 'de',
            "at" => "de",
            "es" => 'es',
            "ar" => 'es',
            "bo" => 'es',
            "cl" => 'es',
            "co" => 'es',
            "cr" => 'es',
            "dr" => 'es',
            "ec" => 'es',
            "sv" => 'es',
            "gt" => 'es',
            "hn" => 'es',
            "mx" => 'es',
            "ni" => 'es',
            "pa" => 'es',
            "py" => 'es',
            "pe" => 'es',
            "pr" => 'es',
            "uy" => 'es',
            "ve" => 'es',
            "fr" => 'fr-FR',
            "be" => 'fr-FR',
            "lu" => 'fr-FR',
            "hk" => 'zh-CN',
            "id" => 'in-ID',
            "il" => 'he',
            "it" => 'it',
            "jp" => 'ja',
            "kp" => 'ko',
            "kr" => 'ko',
            "pl" => 'pl',
            "br" => 'pt',
            "pt" => 'pt',
            "qc" => 'fr',
            "ru" => 'ru',
            "sg" => 'zh-CN',
            "th" => 'th',
            "tr" => 'tr',
            "tw" => 'zh-CN',
            "vi" => 'vi-VN',
            "cn" => 'zh-CN',
        );

        $host = self::getHost($url, false);
        if(!$host) return false;
        
        $host_parts = explode(".", $host);
        $count = count($host_parts);
        if($count > 0) {
            $tld = $host_parts[$count - 1];
            if($tld == 'ca' && isset($host_parts[$count - 2]) &&
                $host_parts[$count - 2] == 'qc') {
                $tld = 'qc';
            }
            if(isset($LANG[$tld])) {
                return $LANG[$tld];
            }
        }

        return NULL;
    }
  


    /**
     *  Get the path portion of a url if present; if not return NULL
     *
     *  @param string $url the url to parse
     *  @return the host portion of the url if present; NULL otherwise
     */
    static function getPath($url) 
    {
        $url_parts = @parse_url($url);
        if(!isset($url_parts['path'])) {
            return NULL;
        }
        return $url_parts['path'];
    }

    /**
     * Gets an array of prefix urls from a given url. Each prefix contains at 
     * least the the hostname of the the start url
     *
     * http://host.com/b/c/ would yield http://host.com/ , http://host.com/b, 
     * http://host.com/b/, http://host.com/b/c, http://host.com/b/c/
     *
     * @param string $url the url to extract prefixes from
     * @return array the array of url prefixes
     */
    static function getHostPaths($url) 
    {
        $host_paths = array($url);

        $host = self::getHost($url);
        if(!$host) {return $host_paths;}

        $host_paths[] = $host;

        $path = self::getPath($url);

        $path_parts = explode("/", $path);

        $url = $host;
        foreach($path_parts as $part) {
         if($part != "") {
            $url .="/$part";
            $host_paths[] = $url;
            }
            $host_paths[] = $url."/";
        }

        $host_paths = array_unique($host_paths);

        return $host_paths;

    }

    /**
     * Gets the subdomains of the host portion of a url. So
     *
     * http://a.b.c/d/f/
     * will return a.b.c, .a.b.c, b.c, .b.c, c, .c
     *
     * @param string $url the url to extract prefixes from
     * @return array the array of url prefixes
     */
    static function getHostSubdomains($url)
    {
        $subdomains = array();
        $url_parts = @parse_url($url);
        if(!isset($url_parts['host']) || strlen($url_parts['host']) <= 0) { 
            return $subdomains; 
        }
        $host = $url_parts['host'];
        $host_parts = explode(".", $host);
        $num_parts = count($host_parts);
        $domain = "";
        for($i = $num_parts - 1; $i >= 0 ; $i--) {
            $domain = $host_parts[$i].$domain;
            $subdomains[] = $domain;
            $domain = ".$domain";
            $subdomains[] = $domain;
        }

        return $subdomains;
    }

    /**
     * Checks if $path matches against any of the Robots.txt style regex
     * paths in $paths
     *
     * @param string $path a path component of a url
     * @param array $robot_paths in format of robots.txt regex paths
     * @return bool whether it is a member or not
     */
    static function isPathMemberRegexPaths($path, $robot_paths) 
    {
        $is_member = false;
        $len = strlen($path);
        foreach($robot_paths as $robot_path) {
            $rlen = strlen($robot_path);
            if($rlen == 0) continue;
            $end_match = false;
            $end = ($robot_path[$rlen - 1] == "$") ? 1 : 0;
            $path_string = substr($robot_path, 0, $rlen - $end);
            $path_parts = explode("*", $path_string);
            $offset = -1;
            $old_part_len = 0;
            $is_match = true;
            foreach($path_parts as $part) {
                $offset += 1 + $old_part_len;
                $old_part_len = strlen($part);
                if($part == "") {
                    continue;
                }
                else if($offset >= $len) {
                    $is_match = false;
                    break;
                }
                $new_offset = stripos($path, $part, $offset);
                if($new_offset === false ||($offset == 0 && $new_offset !=0)) {
                    $is_match = false;
                    break;
                }
                $offset = $new_offset;
            }
            if($is_match) {
                if($end == 0 || strlen($part) + $offset == $len) {
                    $is_member = true;
                } 
            }
        }
        return $is_member;
    }


    /**
     * Given a url, extracts the words in the host part of the url
     * provided the url does not have a path part more than / .
     * Ignores a leading www and also ignore tld.
     *
     * For example, "http://www.yahoo.com/" returns " yahoo "
     *
     * @param string $url a url to figure out the file type for
     *
     * @return string space separated words extracted.
     *
     */
    static function getWordsIfHostUrl($url) 
    {
        $words = array();
        $url_parts = @parse_url($url);
        if(!isset($url_parts['host']) || strlen($url_parts['host']) <= 0
            || (isset($url_parts['path']) && $url_parts['path'] != "/")|| 
            isset($url_parts['query'])
            || isset($url_parts['fragment'])) {
            // if no host or has a query string bail
            return ""; 
        }
        $host = $url_parts['host'];
        $host_parts = explode(".", $host);
        if(count($host_parts) <= 1) {
            return "";
        }
        array_pop($host_parts); // get rid of tld
        if(stristr($host_parts[0],"www")) {
            array_shift($host_parts);
        }
        $words = array_merge($words, $host_parts);
        $word_string = " ".implode(" ", $words). " ";
        return $word_string;
    }

    /**
     * Given a url, extracts the words in the last path part of the url
     * For example, 
     * http://us3.php.net/manual/en/function.array-filter.php
     * yields " function array filter "
     *
     * @param string $url a url to figure out the file type for
     *
     * @return string space separated words extracted.
     *
     */
    static function getWordsLastPathPartUrl($url) 
    {
        $words = array();
        $url_parts = @parse_url($url);
        $path_info = @pathinfo($url_parts['path']);
        $path = "";
        if(isset($path_info['dirname'])) {
            $path .= $path_info['dirname']."/";
        }
        if(isset($path_info['filename'])) {
            $path .= $path_info['filename'];
        }
        $pre_path_parts = explode("/", $path);
        $count = count($pre_path_parts);
        if($count < 1 ) {
            return "";
        }
        $last_path = $pre_path_parts[$count - 1];
        $path_parts = preg_split("/(_|-|\ |\+|\.)/", $last_path);
        foreach($path_parts as $part) {
            if(strlen($part) > 0 ) {
                $words[] = $part;
            }
        }
        $word_string = " ".implode(" ", $words). " ";
        return $word_string;
    }

    /**
     * Given a url, makes a guess at the file type of the file it points to
     *
     * @param string $url a url to figure out the file type for
     *
     * @return string the guessed file type.
     *
     */
    static function getDocumentType($url) 
    {

        $url_parts = @parse_url($url); 

        if(!isset($url_parts['path'])) {
            return "html"; //we default to html
        } else if ($url[strlen($url) - 1] == "/"||$url[strlen($url) - 1]=="\\"){
            return "html";
        } else {
            $path_parts = pathinfo($url_parts['path']);

            if(!isset($path_parts["extension"]) ) {
             return "html"; //we default to html
            }

            return $path_parts["extension"];
        }

    }

    /**
     * Gets the filename portion of a url if present; 
     * otherwise returns "Some File"
     *
     * @param string $url a url to parse
     * @return string the filename portion of this url
     */
    static function getDocumentFilename($url)
    {

        $url_parts = @parse_url($url); 

        if(!isset($url_parts['path'])) {
            return "html"; //we default to html
        } else {
            $path_parts = pathinfo($url_parts['path']);

            if(!isset($path_parts["filename"]) ) {
                return "Some File";
            }

            return $path_parts["filename"];
        }

    }

    /**
     * Get the query string component of a url
     *
     * @param string $url  a url to get the query string out of
     * @return string the query string if present; NULL otherwise
     */
    static function getQuery($url) 
    {
        $url_parts = @parse_url($url);
        if(isset($url_parts['query'])) {
            $out = $url_parts['query'];
        } else {
            $out = NULL;
        }

        return $out;
    }

    /**
     * Get the url fragment string component of a url
     *
     * @param string $url  a url to get the url fragment string out of
     * @return string the url fragment string if present; NULL otherwise
     */
    static function getFragment($url) 
    {
        $url_parts = @parse_url($url);
        if(isset($url_parts['fragment'])) {
            $out = $url_parts['fragment'];
        } else {
            $out = NULL;
        }

        return $out;
    }

    /**
     * Given a $link that was obtained from a website $site, returns 
     * a complete URL for that link.
     * For example, the $link
     * some_dir/test.html
     * on the $site
     * http://www.somewhere.com/bob
     * would yield the complete url
     * http://www.somewhere.com/bob/some_dir/test.html
     * 
     * @param string $link  a relative or complete url
     * @param string $site  a base url
     * @param string $no_fragment if false then if the url had a fragment
     *      (#link_within_page) then the fragement will be included
     * 
     * @return string a complete url based on these two pieces of information
     * 
     */
    public static function canonicalLink($link, $site, $no_fragment = true) 
    {

        if(!self::isSchemeHttpOrHttps($link)) {return NULL;}

        if(isset($link[0]) && 
            $link[0] == "/" && isset($link[1]) && $link[1] == "/") {
            $http = ($site[4] == 's') ? "https:" : "http:";
            $link = $http . $link;
        }

        if(self::hasHostUrl($link)) {
            $host = self::getHost($link);
            $path = self::getPath($link);
            $query = self::getQuery($link);
            $fragment = self::getFragment($link);
        } else {

            $host = self::getHost($site);

            if($link !=NULL && $link[0] =="/") {
                $path = $link;
            } else {
                $site_path = self::getPath($site);
                $site_path_parts = pathinfo($site_path);

                if(isset($site_path_parts['dirname'])) {
                    $pre_path = $site_path_parts['dirname'];
                } else {
                    $pre_path = "";
                }
                if(isset($site_path_parts['basename']) && 
                    !isset($site_path_parts['extension'])) {
                    $pre_path .="/".$site_path_parts['basename'];
                }

                if(strlen($link) > 0) {
                     $pre_path = ($link[0] !="#") ? $pre_path."/".$link :
                        $pre_path . $link;
                }
                $path = self::getPath($pre_path);
                $so_far_link = $host . $pre_path;
                $query = self::getQuery($so_far_link);
                $fragment = self::getFragment($so_far_link);
            }
        }


        // take a stab at paths containing ..
        $path = preg_replace('/(\/\w+\/\.\.\/)+/', "/", $path);

            
        // if still has .. give up
        if(stristr($path, "../"))
        {
            return NULL;
        }

        // handle paths with dot in it 
        $path = preg_replace('/(\.\/)+/', "", $path);
        $path = str_replace(" ", "%20", $path);


        $link_path_parts = pathinfo($path);

        $path2 = $path;
        do {
            $path = $path2;
            $path2 = str_replace("//","/", $path);
        } while($path != $path2);

        $path = str_replace("/./","/", $path);
        if($path == "." || substr($path, -2) == "/.") {
            $path = "/";
        }
        if($path == "" && !(isset($fragment) && $fragment !== "")) {
            $path = "/";
        }

        $url = $host.$path;

        if(isset($query) && $query !== "") {
            $url .= "?".$query;
        }

        if(isset($fragment) && $fragment !== "" && !$no_fragment) {
            $url .= "#".$fragment;
        }
        return $url;
    }

    /**
     * Checks if a url has a repeated set of subdirectories, and if the number 
     * of repeats occurs more than some threshold number of times
     *
     *  A pattern like bob/.../bob counts as own reptition. 
     * bob/.../alice/.../bob/.../alice would count as two (... should be read 
     * as ellipsis, not a directory name).If the threshold is three and there 
     * are at least three repeated mathes this function return true; it returns
     * false otherwise.
     *
     * @param string $url the url to check
     * @param int $repeat_threshold the number of repeats of a subdir name to 
     *      trigger a true response
     * @return bool whether a repeated subdirectory name with more matches than
     *      the threshold was found
     *
     */
    static function checkRecursiveUrl($url, $repeat_threshold = 3) 
    {
        $url_parts = mb_split("/", $url);

        $count= count($url_parts);
        $flag = 0;
        for($i = 0; $i < $count; $i++) {
            for($j = 0; $j < $i; $j++) {
                if($url_parts[$j] == $url_parts[$i]) {
                    $flag++;
                }
            }
        }

        if($flag > $repeat_threshold) {
            return true;
        }

        return false;

    }

    /**
     * Checks if a $url is on localhost
     *
     * @param string $url the url to check
     * @return bool whether or not it is on localhost
     */
    static function isLocalhostUrl($url)
    {
        $host = UrlParser::getHost($url, false);
        
        $localhosts = array("localhost", "127.0.0.1", "::1");
        if(isset($_SERVER["SERVER_NAME"])) {
            $localhosts[] = $_SERVER["SERVER_NAME"];
            $localhosts[] = gethostbyname($_SERVER["SERVER_NAME"]);
        }
        if(isset($_SERVER["SERVER_ADDR"])) {
            $localhosts[] = $_SERVER["SERVER_ADDR"];
        }
        foreach($localhosts as $localhost) {
            if(stristr($host, $localhost)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a URL corresponds to a known playback page of a video
     * sharing site
     *
     * @param string $url the url to check
     * @return bool whether or not corresponds to video playback page of a known
     *      video site
     */
    static function isVideoUrl(&$url)
    {
        static $video_prefixes = array("http://www.youtube.com/watch?v=",
            "http://www.metacafe.com/watch/", 
            "http://screen.yahoo.com/",
            "http://player.vimeo.com/video/", 
            "http://archive.org/movies/thumbnails.php?identifier=",
            "http://www.dailymotion.com/video/",
            "http://v.youku.com/v_playlist/",
            "http://www.break.com/index/");
        static $patterns = array();

        if(strlen($url) <= 0 ) {
            return false;
        }
        if($patterns == array()) {
            foreach($video_prefixes as $prefix) {
                $quoted = preg_quote($prefix, "/");
                $patterns[] = "/$quoted/";
            }
        }

        foreach($patterns as $pattern) {
            if(preg_match($pattern, $url) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     *  Used to delete links from array of links $links based on whether
     *  they are the same as the site they came from (or otherwise judged
     *  irrelevant)
     *
     *  @param array $links pairs of the form $link =>$text
     *  @param string $parent_url a site that the links were found on
     *  @return array just those links which pass the relevancy test
     */
    static function cleanRedundantLinks($links, $parent_url)
    {
        $out_links = array();
        foreach($links as $url => $text) {
            //ignore links back to oneself (too easy to spam)
            if(strcmp($parent_url, $url) != 0) {
                $out_links[$url] = $text;
            }
        }
        return $out_links;
    }
}

?>
