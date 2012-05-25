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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Web page used to present search results
 * It is also contains the search box for
 * people to types searches into
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class RssView extends View implements CrawlConstants
{
    /** Names of helper objects that the view uses to help draw itself 
     *  @var array
     */
    var $helpers = array("pagination", "filetype", "displayresults");

    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "rss";

    /**
     *  Draws the main landing pages as well as search result pages
     *
     *  @param array $data  PAGES contains all the summaries of web pages
     *  returned by the current query, $data also contains information
     *  about how the the query took to process and the total number
     *  of results, how to fetch the next results, etc.
     *
     */
    public function renderView($data) 
    {
        if(isset($data['PAGES'])) {
        ?>

            <?php
            foreach($data['PAGES'] as $page) {?>
                <item>
                <title><?php  e(strip_tags($page[self::TITLE]));
                    if(isset($page[self::TYPE])) {
                        $this->filetypeHelper->render($page[self::TYPE]);
                    }?></title>

                <link><?php if(isset($page[self::TYPE]) 
                    && $page[self::TYPE] != "link") {
                        e($page[self::URL]); 
                    } else {
                        e(strip_tags($page[self::TITLE]));
                    } ?></link>
                <description><?php 
                e(strip_tags($page[self::DESCRIPTION]));
                if(isset($page[self::THUMB]) 
                    && $page[self::THUMB] != 'NULL') { 
                    $img = "<img src='{$page[self::THUMB]}' ".
                        "alt='Image' />";
                    e(htmlentities($img));
                }
                ?></description>
                </item>

            <?php 
            } //end foreach
        }
    }
}
?>
