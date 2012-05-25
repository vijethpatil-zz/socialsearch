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
 * @author Vijeth Patil vijeth.patil@gmail.com
 * @package seek_quarry
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load base class, if needed.
 */
require_once BASE_DIR."/lib/processors/text_processor.php";

/**
 * If XML turns out to be XHTML ...
 */
require_once BASE_DIR."/lib/processors/html_processor.php";

/**
 * Load so can parse urls
 */
require_once BASE_DIR."/lib/url_parser.php";


/**
 *  The maximum length of description
 */
const MAX_DESCRIPTION_LEN = 2000;
    
/**
 * The constant represents the number of
 * child levels at which the data is present in
 * the content.opf file.
 */
const MAX_DOM_LEVEL = 15;

 /**
 * Used to create crawl summary information
 * for XML files (those served as application/epub+zip)
 *
 * @author Vijeth Patil
 * @package seek_quarry
 * @subpackage processor
 */
 
 

class EpubProcessor extends TextProcessor
{
    /**
     *  The name of the tag element in an xml document
     *
     *  @var string name
     */
    var $name;

    /**
     *  The attribute of the tag element in an xml document
     *
     *  @var string attributes
     */
    var $attributes;

    /**
     *  The content of the tag element or attribute, used to extract
     *  the fields like title, creator, language of the document
     *
     *  @var string content
     */
    var $content;

    /**
     *  The child tag element of a tag element.
     *
     *  @var string children
     */
    var $children;

    /**
     *  Used to extract the title, description and links from
     *  a string consisting of ebook publication data.
     *
     *  @param string $page epub contents
     *  @param string $url the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return array  a summary of the contents of the page
     *
     */
    function process($page, $url)
    {
        $summary = NULL;
        $opf_pattern = "/.opf$/i";
        $html_pattern  = "/.html$/i";
        $xhtml_pattern = "/.xhtml$/i";
        $temp_filename = CRAWL_DIR ."/temp/epub.zip";
        $epub_url[0] = '';
        $epub_language = '';
        $epub_title = '';
        $epub_unique_identifier = '';
        $epub_author = '';
        $epub_publisher = '';
        $epub_date = '';
        $epub_subject = '';
        $desc = '';
        $htmlcontent = '';
        file_put_contents($temp_filename,$page);
        $zip = new ZipArchive;
        if($zip->open($temp_filename)) {
            for($i = 0; $i < $zip->numFiles; $i++) {
                // get the content file names of .epub document
                $filename[$i] = $zip->getNameIndex($i) ;
                if(preg_match($opf_pattern,$filename[$i])) {
                    // Get the file data from zipped folder
                    $opf_data = $zip->getFromName($filename[$i]);
                    $opf_summary = $this->xmlToObject($opf_data);
                    for($m = 0;$m <= MAX_DOM_LEVEL; $m++) {
                        for($n = 0;$n <= MAX_DOM_LEVEL; $n++) {
                            if(isset($opf_summary->children[$m]->children[$n])){
                                $child = $opf_summary->children[$m]->
                                    children[$n];
                                if( isset($child->name) &&
                                    $child->name == "dc:language") {
                                    $epub_language =
                                        $opf_summary->children[$m]->
                                            children[$n]->content ;
                                }
                                if( ($opf_summary->children[$m]->children[$n]->
                                    name) == "dc:title") {
                                    $epub_title = $opf_summary->children[$m]->
                                        children[$n]->content;
                                }
                                if( ($opf_summary->children[$m]->children[$n]->
                                    name) == "dc:creator") {
                                    $epub_author = $opf_summary->children[$m]->
                                        children[$n]->content ;
                                }
                                if( ($opf_summary->children[$m]->children[$n]->
                                    name) == "dc:identifier") {
                                    $epub_unique_identifier = $opf_summary->
                                        children[$m]->children[$n]->content ;
                                }
                            }
                        }
                    }
                }else if((preg_match($html_pattern,$filename[$i])) ||
                    (preg_match($xhtml_pattern,$filename[$i]))) {
                    $html = new HtmlProcessor;
                    $html_data = $zip->getFromName($filename[$i]);
                    $description[$i] = $html->process($html_data,$url);
                    $htmlcontent.= $description[$i]['t'];
                }
            }
        }
        if($epub_title != '')
        {
            $desc= " $epub_title .";
        }
        if($epub_author != '')
        {
            $desc = $desc." $epub_author ";
        }
        if($epub_language != '')
        {  
            $desc = $desc." $epub_language ";
        }
        if($epub_unique_identifier != '')
        {
            $desc = $desc." URN-".
            $epub_unique_identifier.".";
        }
        if($epub_publisher != '')
        {  
            $desc = $desc." $epub_publisher ";
        }
        if($epub_date != '')
        {  
            $desc = $desc." $epub_date ";
        }
        if($epub_subject != '')
        {  
            $desc = $desc." $epub_subject ";
        }
        $desc= $desc.$htmlcontent;
        //restrict the length of the description to maximum description length
        if(strlen($desc) > MAX_DESCRIPTION_LEN)  
        {
            $desc= substr($desc, 0, MAX_DESCRIPTION_LEN);
        }
        $summary[self::TITLE] = $epub_title;
        $summary[self::DESCRIPTION] = $desc;
        $summary[self::LANG] = $epub_language;
        $summary[self::LINKS] = $epub_url;
        $summary[self::PAGE] = $page;
        if($zip) {
            $zip->close(); 
        }
        unlink($temp_filename);
        return $summary;
    }

    /**
     *  Used to extract the DOM tree containing the information
     *  about the epub file such as title, author, language, unique
     *  identifier of the book from a string consisting of ebook publication
     *  content OPF file.
     *
     *  @param string $page xml contents
     *
     *  @return array  an information about the contents of the page
     *
     */
    function xmlToObject($xml)
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $xml, $tags);
        xml_parser_free($parser);

        $elements = array();  // the currently filling [child] XmlElement array
        $stack = array();
        foreach ($tags as $tag) {
            $index = count($elements);
            if ($tag['type'] == "complete" || $tag['type'] == "open") {
                $elements[$index] = new EpubProcessor;
                $elements[$index]->name = $tag['tag'];
                if(isset($tag['attributes'])) {
                    $elements[$index]->attributes = $tag['attributes'];
                }
                if(isset($tag['value'])) {
                    $elements[$index]->content = $tag['value'];
                }
                if ($tag['type'] == "open") {  // push
                    $elements[$index]->children = array();
                    $stack[count($stack)] = &$elements;
                    $elements = &$elements[$index]->children;
                }
            }
            if ($tag['type'] == "close") {  // pop
                $elements = &$stack[count($stack) - 1];
                unset($stack[count($stack) - 1]);
            }
        }
        return $elements[0];  // the single top-level element
    }
}
?>
