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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/archive_bundle_iterator.php';

/**
 * Used to iterate through the records of a collection of one or more open 
 * directory RDF files stored in a WebArchiveBundle folder. Open Directory
 * file can be found at http://rdf.dmoz.org/ .  Iteration would be 
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class OdpRdfArchiveBundleIterator extends ArchiveBundleIterator 
    implements CrawlConstants
{
    /**
     * The path to the directory containing the archive partitions to be 
     * iterated over.
     * @var string
     */
    var $iterate_dir;
    /**
     * The path to the directory where the iteration status is stored.
     * @var string
     */
    var $result_dir;
    /**
     * The number of odp rdf files in this archive bundle
     *  @var int
     */
    var $num_partitions;
    /**
     *  Counting in glob order for this odp rdf archive bundle directory, the 
     *  current active file number of the file being processed.
     *  @var int
     */
    var $current_partition_num;
    /**
     *  current number of pages into the current odp rdf file
     *  @var int
     */
    var $current_page_num;
    /**
     *  Array of filenames of odp rdf files in this directory (glob order)
     *  @var array
     */
    var $partitions;
    /**
     *  Used to buffer data from the currently opened odp rdf file
     *  @var string
     */
    var $buffer;
    /**
     *  Associative array containing global properties like base url of the
     *  current open odp rdf file
     *  @var array
     */
    var $header;
    /**
     *  File handle for current odp rdf file
     *  @var resource
     */
    var $fh;
    /**
     *  Offset into the current odp rdf file
     *  @var int
     */
    var $current_offset;

    /**
     * How many bytes to read into buffer from gzip stream in one go
     * @var int
     */
    const BLOCK_SIZE = 1024;

    /**
     * Creates an open directory rdf archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the arc archive bundle to 
     *      iterate  over the pages of
     * @param string $result_timestamp timestamp of the arc archive bundle
     *      results are being stored in
     */
    function __construct($iterate_timestamp, $iterate_dir,
            $result_timestamp, $result_dir)
    {
        $this->iterate_timestamp = $iterate_timestamp;
        $this->iterate_dir = $iterate_dir;
        $this->result_timestamp = $result_timestamp;
        $this->result_dir = $result_dir;
        $this->partitions = array();
        foreach(glob("{$this->iterate_dir}/*.gz") as $filename) { 
            $this->partitions[] = $filename;
        }
        $this->num_partitions = count($this->partitions);
        $this->header['base_address'] = "http://www.dmoz.org/";
        $url_parts = @parse_url($this->header['base_address']);
        $this->header['ip_address'] = gethostbyname($url_parts['host']);

        if(file_exists("{$this->result_dir}/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }

    /**
     * Add the buffer contents to the standard gzip archive checkpoint.
     */
    function saveCheckpoint($info = array())
    {
        $info['buffer'] = $this->buffer;
        parent::saveCheckpoint($info);
    }

    /**
     * Restore the buffer from the checkpoint info.
     */
    function restoreCheckpoint()
    {
        $info = parent::restoreCheckpoint();
        $this->buffer = $info['buffer'];
    }

    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return int a 4-bit number based on the topic path of the odp entry
     *      (@see processTopic @see processExternalPage)
     */
    function weight(&$site)
    {
        return min($site[self::WEIGHT], 15);
    }

    /**
     * Used to extract data between two tags for the first tag found
     * amongst the array of tags $tags. After operation $this->buffer has
     * contents after the close tag.
     *
     * @param array $tags array of tagnames to look for
     * 
     * @return string data start tag contents close tag of first tag found
     */
    function getNextTagsData($tags)
    {
        $max_tag_len = 0;
        $regex = '@<('.implode('|', $tags).')[^>]*?>.*?</\1[^>]*?>@si';
        foreach($tags as $tag) {
            $max_tag_len = max(strlen($tag) + 2, $max_tag_len);
        }
        $done = false;
        $search_failed = false;
        $offset = 0;
        do {
            if($search_failed && (!$this->fh || feof($this->fh))) {
                return false;
            }
            $this->buffer .= gzread($this->fh, self::BLOCK_SIZE);
            if(preg_match($regex, $this->buffer, $matches,
                    PREG_OFFSET_CAPTURE, $offset)) {
                $done = true;
                $search_failed = false;
            } else {
                $search_failed = true;
            }
            $offset = max(0, strlen($this->buffer) - $max_tag_len);
        } while(!$done);
        $found_tag = $matches[1][0];
        $start = $matches[0][1];
        $length = strlen($matches[0][0]);
        $tag_info = substr($this->buffer, $start, $length);
        $this->buffer = substr($this->buffer, $start + $length);
        return array($tag_info, $found_tag);
    }

    /**
     * Gets the text content of the first dom node satisfying the
     * xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     *
     * @return string text content of the given node if it exists
     */
    function getTextContent($dom, $path)
    {
        $xpath = new DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if($objects  && is_object($objects) && $objects->item(0) != NULL) {
            return $objects->item(0)->textContent;
        }
        return "";
    }

    /**
     * Gets the value of the attribute $attribute for each dom node 
     * satisfying the xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     * @param string $attribute name of the attribute to get the values for
     *
     * @return array of values of the given attribute
     */
    function getAttributeValueAll($dom, $path, $attribute)
    {
        $values = array();
        $xpath = new DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if($objects  && is_object($objects)) {
            foreach($objects as $object) {
                $value = $object->getAttribute($attribute);
                if($value) {
                    $values[] = $value;
                }
            }
        }
        return $values;
    }

    /**
     * Gets the value of the attribute $attribute of the first dom node 
     * satisfying the xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     * @param string $attribute name of the attribute to get the value for
     *
     * @return string value of the given attribute
     */
    function getAttributeValue($dom, $path,  $attribute)
    {
        $xpath = new DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if($objects  && is_object($objects) && $objects->item(0) != NULL) {
            return $objects->item(0)->getAttribute($attribute);
        }
        return "";
    }

    /**
     * Resets the iterator to the start of the archive bundle
     */
    function reset()
    {
        $this->current_partition_num = -1;
        $this->end_of_iterator = false;
        $this->fh = NULL;
        $this->current_offset = 0;
        $this->buffer = "";
        @unlink("{$this->result_dir}/iterate_status.txt");
    }

    /**
     * Gets the next $num many Topic or ExternalPage pages from the iterator
     * @param int $num number of docs to get
     * @return array associative arrays of data for $num pages
     */
    function nextPages($num)
    {
        return $this->readPages($num, true);
    }

    /**
     * Reads the next at most $num many wiki pages from the iterator. It might 
     * return less than $num many documents if the partition changes or the end
     * of the bundle is reached.
     *
     * @param int $num number of pages to get
     * @param bool $return_pages whether to return all of the pages or
     *      not. If not, then doesn't bother storing them
     * @return array associative arrays for $num pages
     */
    function readPages($num, $return_pages)
    {
        $pages = array();
        $page_count = 0;
        for($i = 0; $i < $num; $i++) {
            $page = $this->readPage($return_pages);
            if(!$page) {
                if(is_resource($this->fh)) {
                    gzclose($this->fh);
                }
                $this->current_partition_num++;
                if($this->current_partition_num >= $this->num_partitions) {
                    $this->end_of_iterator = true;
                    break;
                }
                $this->fh = gzopen(
                    $this->partitions[$this->current_partition_num], "r");
                $this->current_offset = 0;
            } else {
                if($return_pages) {
                    $pages[] = $page;
                }
                $page_count++;
            }
        }
        if(is_resource($this->fh)) {
            $this->current_offset = gztell($this->fh);
            $this->current_page_num += $page_count;
        }

        $this->saveCheckpoint();
        return $pages;
    }

    
    /**
     * Gets the next doc from the iterator
     * @return array associative array for doc
     */
    function readPage($return_page)
    {
        if(!is_resource($this->fh)) return NULL;
        $tag_data = $this->getNextTagsData(
            array("Topic","ExternalPage"));
        if(!$tag_data) {
            return false;
        }
        list($page_info, $tag) = $tag_data;
        if(!$return_page) {
            return true;
        }
        $page_info = str_replace("r:id","id", $page_info);
        $page_info = str_replace("r:resource","resource", $page_info);
        $page_info = str_replace("d:Title","Title", $page_info);
        $page_info = str_replace("d:Description","Description", $page_info);
        $dom = new DOMDocument();
        $dom->loadXML($page_info);
        $processMethod = "process".$tag;
        $site[self::IP_ADDRESSES] = array($this->header['ip_address']);
        $site[self::MODIFIED] = time();
        $site[self::TIMESTAMP] = time();
        $site[self::TYPE] = "text/html";
        $site[self::HEADER] = "odp_rdf_bundle_iterator extractor";
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = "UTF-8";
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $this->$processMethod($dom, $site);
 
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);

        return $site;
    }

    /**
     *  Computes an HTML page for a Topic tag parsed from the ODP RDF 
     *  document
     *
     *  @param object $dom document object for one Topic tag tag
     *  @param array &$site a reference to an array of header and page info
     *      for an html page
     */
    function processTopic($dom, &$site)
    {
        $topic_path = $this->getAttributeValue($dom, "/Topic", "id");
        $site[self::URL] = $this->header['base_address'].$topic_path;

        $site[self::WEIGHT] = max(15 - substr_count($topic_path, "/"), 1);
        $title = str_replace("/", " ", $topic_path);
        $links = $this->computeTopicLinks($topic_path);

        $topic_link1 = $this->getAttributeValue($dom, "/Topic/link1", 
            "resource");
        if($topic_link1) {
            $links[$topic_link1] = $topic_link1." - ".$title;
        }

        $topic_links = $this->getAttributeValueAll($dom, "/Topic/link", 
            "resource");
        if($topic_links != NULL) {
            foreach($topic_links as $topic_link) {
                $links[$topic_link] = $topic_link." - ".$title;
            }
        }
        $site[self::PAGE] = "<html>\n".
            "<head><title>$title</title></head>\n"
            ."<body><h1>$title</h1>\n";
        $site[self::PAGE] .= $this->linksToHtml($links);
        $site[self::PAGE] .= "</body></html>";

    }

    /**
     *  Computes an HTML page for an ExternalPage tag parsed from the ODP RDF 
     *  document
     *
     *  @param object $dom document object for one Topic tag tag
     *  @param array &$site a reference to an array of header and page info
     *      for an html page
     */
    function processExternalPage($dom, &$site)
    {
        $site[self::URL] = $this->getAttributeValue($dom, 
            "/ExternalPage", "about");

        $topic_path = $this->getTextContent($dom, "/ExternalPage/topic");
        $site[self::WEIGHT] = max(14 - substr_count($topic_path, "/"), 1);

        $links = $this->computeTopicLinks($topic_path);
        $title = $this->getTextContent($dom, "/ExternalPage/Title");
        $title = "$title - ".str_replace("/", " ", $topic_path);
        $description = $this->getTextContent(
            $dom, "/ExternalPage/Description");

        $site[self::PAGE] = "<html>\n".
            "<head><title>$title</title></head>\n"
            ."<body><h1>$title</h1>\n";
        $site[self::PAGE] .= $this->linksToHtml($links);
        $site[self::PAGE] .= "<div>$description</div></body></html>";
    }

    /**
     *  Computes links for prefix topics of an ODP topic path
     *
     *  @param string $topic_path to compute links for
     *  @return array url => text pairs for each prefix of path
     */
    function computeTopicLinks($topic_path)
    {
        $links = array();
        $topic_parts = explode("/", $topic_path);
        $path = "";
        
        foreach($topic_parts as $part){
            $path .= "/$part";
            $links[$this->header['base_address'].$path] = $part;
        }
        return $links;
    }

    /**
     *  Makes an unordered HTML list out of an associative array of
     *  url => link_text pairs.
     *
     *  @param array $links url=>link_text pairs
     *  @return string containing html for unorderlisted list of links
     */
    function linksToHtml($links) 
    {
        $html = "";
        if(count($links) > 0) {
            $html .= "<ul>\n";
            foreach($links as $url => $text) {
                $html .= '<li><a href="'.
                    $url.'">'.$text.'</a></li>';
            }
            $html .= "</ul>\n";
        }
        return $html;
    }

}
?>
