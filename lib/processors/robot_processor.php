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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load the base class
 */
require_once BASE_DIR."/lib/processors/page_processor.php";

/**
 * So can extract parts of the URL if need to guess lang
 */
require_once BASE_DIR."/lib/url_parser.php";


/**
 * Processor class used to extract information from robots.txt files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class RobotProcessor extends PageProcessor
{

    /**
     * Parses the contents of a robots.txt page extracting allowed,
     * disallowed paths, crawl-delay, and sitemaps. We also extract a
     * list of all user agent strings seen.
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by 
     *      TextProcessor at this point. Some of its subclasses override
     *      this method and use url to produce complete links for
     *      relative links within a document
     *
     * @return array a summary of (title, description, links, and content) of 
     *      the information in $page
     */
    function process($page, $url)
    {
        $summary = NULL;

        $summary[self::TITLE] = "";
        $summary[self::DESCRIPTION] = "";
        $summary[self::LANG] = NULL;
        $summary[self::ROBOT_PATHS] = array();
        $summary[self::AGENT_LIST] = array();
        $summary[self::LINKS] = array();

        $host_url = UrlParser::getHost($url);
        $lines = explode("\n", $page);

        $add_rule_state = false;
        $rule_added_flag = false;
        $delay_flag = false;
        $delay = 0;

        foreach($lines as $pre_line) {
            $pre_line_parts = explode("#", $pre_line);
            $line = $pre_line_parts[0];
            $line_parts = explode(":", $line);
            if(!isset($line_parts[1])) continue;
            $field = array_shift($line_parts);
            $value = implode(":", $line_parts);
            //notice we lower case field, so switch below is case insensitive
            $field = strtolower(trim($field));
            $value = trim($value);
            $specificness = 0;
            if(strlen($value) == 0) continue;
            switch($field)
            {
                case "user-agent":
                    //we allow * in user agent string
                    $summary[self::AGENT_LIST][] = $value;
                    $current_specificness = 
                        (strcmp($value, USER_AGENT_SHORT) == 0) ? 1 : 0;
                    if($current_specificness < $specificness) break;
                    if($specificness < $current_specificness) {
                        //Give precedence to exact match on agent string
                        $specificness = $current_specificness;
                        $add_rule_state = true;
                        $summary[self::ROBOT_PATHS] = array();
                        break;
                    }
                    $agent_parts = explode("*", $value);
                    $offset = 0;
                    $add_rule_state = true;
                    foreach($agent_parts as $part) {
                        if($part == "") continue;
                        $new_offset = stripos(USER_AGENT_SHORT, $part, $offset);
                        if($new_offset === false) {
                            $add_rule_state = false;
                            break;
                        }
                        $offset = $new_offset;
                    }
                break;

                case "sitemap":
                    $tmp_url = UrlParser::canonicalLink($value, $host_url);
                    if(!UrlParser::checkRecursiveUrl($tmp_url)
                        && strlen($tmp_url) < MAX_URL_LENGTH) {
                        $summary[self::LINKS][] = $tmp_url;
                    }
                break;

                case "allow":
                    if($add_rule_state) {
                        $rule_added_flag = true;
                        $summary[self::ROBOT_PATHS][self::ALLOWED_SITES][] = 
                            $this->makeCanonicalRobotPath($value); 
                    }
                break;

                case "disallow":
                    if($add_rule_state) {
                        $rule_added_flag = true;
                        $summary[self::ROBOT_PATHS][self::DISALLOWED_SITES][] = 
                            $this->makeCanonicalRobotPath($value); 
                    }
                break;

                case "crawl-delay":
                    if($add_rule_state) {
                        $delay_flag = true;
                        $delay = max($delay, intval($value));
                    }
                break;
            }

        }

        if($delay_flag) {
            if($delay > MAXIMUM_CRAWL_DELAY)  {
                $summary[self::ROBOT_PATHS][] = "/";
            } else {
                $summary[self::CRAWL_DELAY] = $delay;
            }
        }

        $summary[self::PAGE] = "<html><body><pre>".
                strip_tags($page)."</pre></body></html>";

        return $summary;
    }

    /**
     * For robot paths 
     *     foo
     * is treated the same as
     *     /foo
     * Path might contain urlencoded characters. These are all decoded
     * except for %2F which corresponds to a / (this is as per
     * http://www.robotstxt.org/norobots-rfc.txt)
     */
    function makeCanonicalRobotPath($path)
    {
        if($path[0] != "/") {
            $path = "/$path";
        }
        return urldecode(preg_replace("/\%2F/i", "%252F", $path));
    }
}
?>
