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
 * Used by subclasses, so have succinct access (i.e., can use self:: rather 
 * than CrawlConstants::) to constants like:
 * CrawlConstants::TITLE, CrawlConstants::DESCRIPTION, etc.
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Base class common to all processors of web page data
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
abstract class PageProcessor implements CrawlConstants
{
    /**
     * indexing_plugins which might be used with the current processor
     *
     * @var array
     */
    var $indexing_plugins;

    /**
     *  Set-ups the any indexing plugins associated with this page
     *  processor
     *
     *  @param array $plugins an array of indexing plugins which might
     *      do further processing on the data handles by this page
     *      processor
     */
    function __construct($plugins = array()){
        $this->indexing_plugins = $plugins;
        foreach($plugins as $plugin) {
            $plugin_name = ucfirst($plugin);
            $plugin_instance_name = lcfirst($plugin);
            $this->$plugin_instance_name = new $plugin_name();
        }
    }

    /**
     *  Method used to handle processing data for a web page. It makes
     *  a summary for the page (via the process() function which should
     *  be subclassed) as well as runs any plugins that are associated with
     *  the processors to create sub-documents
     *
     * @param string $page string of a web document
     * @param string $url location the document came from
     *
     * @return array a summary of (title, description,links, and content) of 
     *      the information in $page also has a subdocs array containing any
     *      subdocuments returned from a plugin. A subdocumenst might be 
     *      things like recipes that appeared in a page or tweets, etc.
     */
    function handle($page, $url)
    {
        $summary = $this->process($page, $url);
        if($summary != NULL && isset($this->indexing_plugins) &&
            is_array($this->indexing_plugins) ) {
            $summary[self::SUBDOCS] = array();
            foreach($this->indexing_plugins as $plugin) {
                $subdoc = NULL;
                $plugin_instance_name = 
                    lcfirst($plugin);
                $subdocs_description = 
                    $this->$plugin_instance_name->pageProcessing($page, $url);
                if(is_array($subdocs_description) 
                    && count($subdocs_description) != 0) {
                    foreach($subdocs_description as $subdoc_description) {
                        $subdoc[self::TITLE] = $subdoc_description[self::TITLE];
                        $subdoc[self::DESCRIPTION] = 
                            $subdoc_description[self::DESCRIPTION];
                        $subdoc[self::LANG] = $summary[self::LANG];
                        $subdoc[self::LINKS] = $summary[self::LINKS];
                        $subdoc[self::PAGE] = $page;
                        $subdoc[self::SUBDOCTYPE] = lcfirst(
                            substr($plugin, 0, -strlen("Plugin")));
                        $summary[self::SUBDOCS][] = $subdoc;
                    }
                }
            }
        }
        return $summary;
    }

    /**
     * Should be implemented to compute a summary based on a 
     * text string of a document. This method is called from 
     * @see handle($page, $url)
     *
     * @param string $page string of a document
     * @param string $url location the document came from
     *
     * @return array a summary of (title, description,links, and content) of 
     *      the information in $page
     */
    abstract function process($page, $url);
}

?>
