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

require_once BASE_DIR.'/lib/bzip2_block_iterator.php';

/**
 * Used to iterate through a collection of .xml.bz2  media wiki files 
 * stored in a WebArchiveBundle folder. Here these media wiki files contain the
 * kinds of documents used by wikipedia. Iteration would be 
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class MediaWikiArchiveBundleIterator extends ArchiveBundleIterator 
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
     * The number of arc files in this arc archive bundle
     *  @var int
     */
    var $num_partitions;
    /**
     *  Counting in glob order for this arc archive bundle directory, the 
     *  current active file number of the arc file being processed.
     *  @var int
     */
    var $current_partition_num;
    /**
     *  current number of wiki pages into the Media Wiki xml.bz2 file
     *  @var int
     */
    var $current_page_num;
    /**
     *  Array of filenames of arc files in this directory (glob order)
     *  @var array
     */
    var $partitions;
    /**
     *  Used to buffer data from the currently opened media wiki file
     *  @var string
     */
    var $buffer;
    /**
     * Used to hold data that was in the buffer but before a siteinfo or a page
     * when that data gets parsed out.
     *  @var string
     */
    var $remainder;
    /**
     *  Associative array containing global properties like base url of the
     *  current open wiki media file
     *  @var array
     */
    var $header;
    /**
     *  Wrapper for a bzip2 file that decompresses incrementally and can be 
     *  serialized and restored while maintaining its position.
     *  @var MicroBzip2
     */
    var $bz2_iterator;

    /**
     * Start state of FSA for lexing media wiki docs
     */
    const START = 0;
    /**
     * Reading an open link state of FSA for lexing media wiki docs
     */
    const OPEN_LINK = 1;
    /**
     * Reading a close link state of FSA for lexing media wiki docs
     */
    const CLOSE_LINK = 2;
    /**
     * Reading a string of chars state of FSA for lexing media wiki docs
     */
    const CHARS = 3;
    /**
     * Might be reading a heading state of FSA for lexing media wiki docs
     */
    const PRE_HEADING = 4;
    /**
     * Reading a heading state of FSA for lexing media wiki docs
     */
    const HEADING = 5;
    /**
     * Escape char state of FSA for lexing media wiki docs
     */
    const ESCAPE = 6;
    /**
     * How many bytes to read into buffer from bz2 stream in one go
     */
    const BLOCK_SIZE = 8192;

    /**
     * Creates a media wiki archive iterator with the given parameters.
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
        foreach(glob("{$this->iterate_dir}/*.xml*.bz2") as $filename) {
            $this->partitions[] = $filename;
        }
        $this->num_partitions = count($this->partitions);

        if(file_exists("{$this->result_dir}/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }

    /**
     * Saves the current state so that a new instantiation can pick up just 
     * after the last batch of pages extracted.
     */
    function saveCheckpoint($info = array())
    {
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['current_partition_num'] = $this->current_partition_num;
        $info['current_page_num'] = $this->current_page_num;
        $info['buffer'] = $this->buffer;
        $info['remainder'] = $this->remainder;
        $info['header'] = $this->header;
        $info['bz2_iterator'] = $this->bz2_iterator;
        file_put_contents("{$this->result_dir}/iterate_status.txt",
            serialize($info));
    }

    /**
     * Restores state from a previous instantiation, after the last batch of 
     * pages extracted.
     */
    function restoreCheckpoint()
    {
        $info = unserialize(file_get_contents(
            "{$this->result_dir}/iterate_status.txt"));
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->current_partition_num = $info['current_partition_num'];
        $this->current_page_num = $info['current_page_num'];
        $this->buffer = $info['buffer'];
        $this->remainder = $info['remainder'];
        $this->header = $info['header'];
        $this->bz2_iterator = $info['bz2_iterator'];
        return $info;
    }

    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return int a 4-bit number based on the log_2 size - 10 of the wiki
     *      entry (@see readPage).
     */
    function weight(&$site)
    {
        return min($site[self::WEIGHT], 15);
    }

    /**
     * Reads the siteinfo tag of the mediawiki xml file and extract data that
     * will be used in constructing page summaries.
     */
    function readMediaWikiHeader()
    {
        $this->header = array();
        $site_info = $this->getNextTagData("siteinfo");
        $found_lang = 
            preg_match('/lang\=\"(.*)\"/', $this->remainder, $matches);
        if($found_lang) {
            $this->header['lang'] = $matches[1];
        }
        if($site_info === false) return false;
        $dom = new DOMDocument();
        @$dom->loadXML($site_info);
        $this->header['sitename'] = $this->getTextContent($dom,
            "/siteinfo/sitename");
        $pre_host_name = 
            $this->getTextContent($dom, "/siteinfo/base");
        $this->header['base_address'] = substr($pre_host_name, 0, 
            strrpos($pre_host_name, "/") + 1);
        $url_parts = @parse_url($this->header['base_address']);
        $this->header['ip_address'] = gethostbyname($url_parts['host']);
        return true;
    }

    /**
     * Used to extract data between two tags such as siteinfo or page from
     * an media wiki file. Stores contents in $this->buffer from before
     * open tag into $this->remainder. After operation $this->buffer has
     * contents after the close tag.
     *
     * @param string $tag tagname to extract between
     * 
     * @return string data start tag contents close tag
     */
    function getNextTagData($tag)
    {
        while(stripos($this->buffer, "</$tag") === false) {
            if(is_null($this->bz2_iterator) || $this->bz2_iterator->is_eof()) {
                return false;
            }
            // Get the next block; the block iterator can very occasionally 
            // return a bad block if a block header pattern happens to show up 
            // in compressed data, in which case decompression will fail. We 
            // want to skip over these false blocks and get back to real 
            // blocks.
            while(!is_string($block = $this->bz2_iterator->next_block())) {
                if($this->bz2_iterator->is_eof())
                    return false;
            }
            $this->buffer .= $block;
        } 
        $start_info = strpos($this->buffer, "<$tag");
        $this->remainder = substr($this->buffer, 0, $start_info);
        $pre_end_info = strpos($this->buffer, "</$tag", $start_info);
        $end_info = strpos($this->buffer, ">", $pre_end_info) + 1;
        $tag_info = substr($this->buffer, $start_info, 
            $end_info - $start_info);
        $this->buffer = substr($this->buffer, $end_info);
        return $tag_info;
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
        if($objects  && is_object($objects) && $objects->item(0) != NULL ) {
            return $objects->item(0)->textContent;
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
        $this->current_offset = 0;
        $this->bz2_iterator = NULL;
        $this->buffer = "";
        @unlink("{$this->result_dir}/iterate_status.txt");
    }

    /**
     * Gets the next $num many wiki pages from the iterator
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
        while($page_count < $num) {
            $page = $this->readPage($return_pages);
            if(!$page) {
                if(!is_null($this->bz2_iterator)) {
                    $this->bz2_iterator->close();
                }
                $this->current_partition_num++;
                if($this->current_partition_num >= $this->num_partitions) {
                    $this->end_of_iterator = true;
                    break;
                }
                $this->bz2_iterator = new BZip2BlockIterator(
                    $this->partitions[$this->current_partition_num]);
                $result = $this->readMediaWikiHeader();
                if(!$result) {
                    $this->bz2_iterator = NULL;
                    break;
                }
            } else {
                if($return_pages) {
                    $pages[] = $page;
                }
                $page_count++;
            }
        }
        if(!is_null($this->bz2_iterator)) {
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
        if(is_null($this->bz2_iterator)) {
            return NULL;
        }
        $page_info = $this->getNextTagData("page");
        if(!$return_page) {
            return true;
        }
        $dom = new DOMDocument();
        @$dom->loadXML($page_info);
        $site = array();

        $pre_url = $this->getTextContent($dom, "/page/title");
        $pre_url = str_replace(" ", "_", $pre_url);
        $site[self::URL] = $this->header['base_address'].$pre_url;
        $site[self::IP_ADDRESSES] = array($this->header['ip_address']);
        $pre_timestamp = $this->getTextContent($dom, 
            "/page/revision/timestamp");
        $site[self::MODIFIED] = date("U", strtotime($pre_timestamp));
        $site[self::TIMESTAMP] = time();
        $site[self::TYPE] = "text/html";
        $site[self::HEADER] = "mediawiki_bundle_iterator extractor";
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = "UTF-8";
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $site[self::PAGE] = "<html lang='".$this->header['lang']."' >\n".
            "<head><title>$pre_url</title></head>\n".
            "<body><h1>$pre_url</h1>\n";
        $pre_page = $this->getTextContent($dom, "/page/revision/text");
        $divisions = explode("\n\n", $pre_page);
        foreach($divisions as $division) {
            $site[self::PAGE] .= $this->makeHtmlDivision($division);
        }
        $site[self::PAGE] .= "\n</body>\n</html>";
 
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = ceil(max(
            log(strlen($site[self::PAGE]) + 1, 2) - 10, 1));
        return $site;
    }

    /**
     *  Convert the MediaWiki section to an HTML Division (crudely for now)
     * 
     *  @param string $division a media wiki section as a string
     *  @return the result of converting this to HTML as a string
     */
    function makeHtmlDivision($division)
    {
        $out = "\n<div>\n";
        $len = strlen($division);
        $pos = 0;

        while($pos < $len) {
            list($token, $type, $pos) =$this->getNextWikiToken(
                $division, $pos, $len);
            switch($type)
            {
                case self::HEADING:
                    list($result, $pos) = $this->makeHeadingWiki(
                        $division, $token, $pos, $len);
                    $out .= $result;
                break;
                case self::OPEN_LINK:
                    list($result, $pos) = $this->makeLinkWiki(
                        $division, $token, $pos, $len);
                    $out .= $result;
                break;
                case self::CHARS:
                default:
                    $out .= $token;
                break;
            }
        }
        $out .= "\n</div>\n";
        return $out;
    }

    /**
     * Convert a media wiki section heading into the appropriate html heading
     * tags.
     *
     * @param string $division content of media wiki section
     * @param string $token the last token read, an open section heading of
     *      some type (==, ===, ====, etc.)
     * @param int $pos position inf $division we are at.
     * @param int $len length of $division (save an strlen?)
     * @return array pair consisting of html heading string and a new position
     *      after parsing the media wiki heading in $division
     */
    function makeHeadingWiki($division, $token, $pos, $len)
    {
        $token_len = strlen($token);
        $out = "\n<h$token_len>";
        $continue = true;
        do {
            list($token, $type, $pos) =$this->getNextWikiToken(
                $division, $pos, $len);
            switch($type)
            {
                case self::CHARS:
                    $out .= $token;
                break;

                case self::HEADING:
                    $out .= "</h$token_len>\n";
                    $continue = false;
                break;
                case self::OPEN_LINK:
                    list($result, $pos) = $this->makeLinkWiki(
                        $division, $token, $pos, $len);
                    $out .= $result;
                break;
            }
        } while ($pos < $len);
        //close heading if reached end of input
        if($type != self::HEADING) {
            $out .= "</h$token_len>\n";
        }
        return array($out, $pos);
    }

    /**
     * Parses a media wiki link into a canonical html link.
     *
     * @param string $division media wiki section containing the link to convert
     * @param string $open_token the media wiki open tag for the link (either
     *      [ or [[)
     * @param int $pos position in $division we are currently parsing from
     * @param int $len length of $division (save an strlen?)
     * @return array a pair containing a string an html link corresponding 
     *      to the media wiki link and a position in $division one is at after
     *      parsing
     */
    function makeLinkWiki($division, $open_token, $pos, $len)
    {
        $open_len = strlen($open_token);
        if($open_len > 2) { //hmm, not a wiki link
            return array($open_token, $pos);
        }

        $out = "<a href='";

        list($link_data, $type, $pos) =$this->getNextWikiToken(
            $division, $pos, $len);
        if($type != self::CHARS) { //that's weird, so bail
            $out = $open_token . $link_data;
            return array($out, $pos);
        }

        list($close_token, $type, $pos) =$this->getNextWikiToken(
            $division, $pos, $len);

        if($type != self::CLOSE_LINK || strlen($close_token)
            != $open_len) { //that's weird, so bail
            $out = $open_token . $link_data .$close_token;
            return array($out, $pos);
        }

        if($open_len == 1) { //external link
            $link_parts = explode(' ', $link_data);
            $out .= $link_parts[0]."'>";
            if(isset($link_parts[1])) {
                $out .= $link_parts[1]."</a>";
            } else {
                $out .= $link_parts[0]."</a>";
            }
        }

        if($open_len == 2) { //internal link
            $link_parts = explode('|', $link_data);
            $anchor = preg_replace("/\s/", "_", $link_parts[0]);
            $out .= $this->header['base_address'].$anchor."'>";
            if(isset($link_parts[1])) {
                $out .= $link_parts[1]."</a>";
            } else {
                $out .= $link_parts[0]."</a>";
            }
        }
        return array($out, $pos);
    }

    /**
     * Parse the next media wiki token from the supplied media wiki section
     * 
     * @param string $division a media wiki section
     * @param int $pos an integer position to parse from
     * @param int $len length of $division (save an strlen?)
     * @return array a triple containing a string media wiki token, the type of
     *      token that was found, and a position in $division one is at after
     *      lexing
     */
    function getNextWikiToken($division, $pos, $len)
    {
        $token = "";
        $state = self::START;
        $continue = true;
        if($pos >= $len) {
            return array($token, self::CHARS, $pos);
        }
        do {
            switch($division[$pos])
            {
                case "=":
                    if($state == self::START) {
                        $state= self::PRE_HEADING;
                    } else if ($state == self::ESCAPE) {
                        $state = self::CHARS;
                    } else if ($state == self::PRE_HEADING) {
                        $state = self::HEADING;
                    } else if ($state != self::HEADING){
                        $continue = false;
                    }
                break;

                case "\\":
                    if($state == self::ESCAPE) {
                        $state = self::CHARS;
                    } else {
                        $state = self::ESCAPE;
                    }
                break;

                case "[";
                    if($state == self::START) {
                        $state= self::OPEN_LINK;
                    } else if ($state == self::ESCAPE) {
                        $state = self::CHARS;
                    } else if ($state != self::OPEN_LINK){
                        $continue = false;
                    }
                break;

                case "]";
                    if($state == self::START) {
                        $state= self::CLOSE_LINK;
                    } else if ($state == self::ESCAPE) {
                        $state = self::CHARS;
                    } else if ($state != self::CLOSE_LINK){
                        $continue = false;
                    }
                break;

                default:
                    if($state == self::START) {
                        $state= self::CHARS;
                    } else if ($state == self::ESCAPE || 
                            $state == self::PRE_HEADING
                        ) {
                        $state = self::CHARS;
                    } else if ($state != self::CHARS){
                        $continue = false;
                    }
            }
            if($continue) {
                $token .= $division[$pos];
                $pos++;
            }
        } while($continue && $pos < $len);
        return array($token, $state, $pos);
    }

}
?>
