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

class SearchView extends View implements CrawlConstants
{
    /** Names of helper objects that the view uses to help draw itself 
     *  @var array
     */
    var $helpers = array("pagination", "filetype", "displayresults",
        "videourl", "images");
    /** Names of element objects that the view uses to display itself 
     *  @var array
     */
    var $elements = array("signin", "subsearch", "footer");

    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

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
        ?>
        <div class="topbar"><?php
        $this->subsearchElement->render($data);
        $this->signinElement->render($data);
        ?>
        </div><?php
        $logo = "resources/yioop.png";
        if(!isset($data['PHRASE']['PAGES'])) {
            e('<div class="landing">');
        } else if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        ?>
        <h1 class="logo"><a href="./?YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN'])?>"><img 
            src="<?php e($logo); ?>" alt="<?php e(tl('search_view_title')); ?>"
            /></a></h1>
        <?php
        if(isset($data['PHRASE']['PAGES'])) {
            e('<div class="serp">');
        }
        ?>
        <div class="searchbox">
        <form id="searchForm" method="get" action='?'>
        <p>
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="its" value="<?php e($data['PHRASE']['its']); ?>" />
        <input type="text" <?php if(WORD_SUGGEST) { ?>
            autocomplete="off"  onkeyup="askeyup(event,this)" 
            <?php } ?>
            title="<?php e(tl('search_view_input_label')); ?>"
            id="search-name" name="q" value="<?php if(isset($data['PHRASE']['QUERY'])) {
            e(urldecode($data['PHRASE']['QUERY']));} ?>" 
            placeholder="<?php e(tl('search_view_input_placeholder')); ?>" />
        <button class="buttonbox" type="submit"><?php if(MOBILE) {
                e('>');
            } else {
            e(tl('search_view_search')); } ?></button>
        </p>
        </form>
            </div>
        <div id="asdropdown"> 
            <ul id="aslist" class="autoresult">
            </ul>
        </div>
        <div class="feed-box-header"> 
            <h2> Results from your Social Network </h2>
        </div>
        <div class="feed-box" > 
        <?php
            if(isset($data['FEED']['PAGES'])) {
            foreach($data['FEED']['PAGES'] as $page)
            { ?> 
            <div class="feed-box-image"><img src="<?php echo $page[self::TWEETERPIC] ?>" />
            </div>
            <div class="feed-box-feed"><a href="http://www.twitter.com/<?php echo $page[self::TITLE]?>" target="_blank"><?php echo $page[self::TITLE] ?></a> says 
            <?php 
            echo $this->displayresultsHelper->
            render($page[self::DESCRIPTION]); ?>Rank:<?php echo $page[self::SCORE]; ?></div>
         <?php    
            }   }     
         ?>
        </div>
            
        <?php
        if(isset($data['PHRASE']['PAGES'])) {
            ?>
            </div>
            <div class="serp-results">
            <h2><?php 
                if(MOBILE) {
                } else {
                e(tl('search_view_query_results')); ?> (<?php 
                e(tl('search_view_calculated', $data['ELAPSED_TIME']));?> <?php
                e(tl('search_view_results', $data['PHRASE']['LIMIT'], 
                    min($data['PHRASE']['TOTAL_ROWS'], 
                    $data['PHRASE']['LIMIT'] + $data['PHRASE']['RESULTS_PER_PAGE']), 
                    $data['PHRASE']['TOTAL_ROWS'].")")); 
                }
            ?></h2>
            <?php
            foreach($data['PHRASE']['PAGES'] as $page) {?>
                <div class='result'>           
                <?php if(isset($page['IMAGES'])) {
                    $image_query = "?YIOOP_TOKEN={$data['YIOOP_TOKEN']}".
                            "&amp;c=search&amp;q={$data['PHRASE']['QUERY']}";
                    $this->imagesHelper->render($page['IMAGES'], $image_query);
                    continue;
                }?>

                <h2>
                <a href="<?php if(isset($page[self::TYPE]) 
                    && $page[self::TYPE] != "link") {
                        e($page[self::URL]); 
                    } else {
                        e(strip_tags($page[self::TITLE]));
                    } ?>" rel="nofollow"><?php
                 if(isset($page[self::THUMB]) && $page[self::THUMB] != 'NULL') {
                    ?><img src="<?php e($page[self::THUMB]); ?>" alt="<?php 
                        e($page[self::TITLE]); ?>"  /> <?php
                    $check_video = false;
                 } else {
                    echo $page[self::TITLE];
                    if(isset($page[self::TYPE])) {
                        $this->filetypeHelper->render($page[self::TYPE]);
                    }
                    $check_video = true;
                }
                ?></a></h2>
                <?php if($check_video) {
                    $this->videourlHelper->render($page[self::URL]);
                }
                ?>
                <p><?php if(!isset($page[self::ROBOT_METAS]) || 
                    !in_array("NOSNIPPET", $page[self::ROBOT_METAS])) {
                        echo $this->displayresultsHelper->
                            render($page[self::DESCRIPTION]); 
                    }?></p>
                <p class="echolink" ><?php if(isset($page[self::URL])){
                    e(substr($page[self::URL],0, 200)." ");}
                    e(tl('search_view_rank', 
                        number_format($page[self::DOC_RANK], 2)));
                    e(tl('search_view_relevancy',
                        number_format($page[self::RELEVANCE], 2) ));
                    e(tl('search_view_proximity',
                        number_format($page[self::PROXIMITY], 2) )." ");
                    e(tl('search_view_score', $page[self::SCORE]));
                if(isset($page[self::TYPE]) && $page[self::TYPE] != "link") {
                    if(CACHE_LINK && (!isset($page[self::ROBOT_METAS]) ||
                        !(in_array("NOARCHIVE", $page[self::ROBOT_METAS]) ||
                          in_array("NONE", $page[self::ROBOT_METAS])))) {
                    ?>
                        <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                            ?>&amp;c=search&amp;a=cache&amp;q=<?php 
                            e($data['PHRASE']['QUERY']); ?>&amp;arg=<?php 
                            e(urlencode($page[self::URL])); 
                            ?>&amp;its=<?php e($page[self::CRAWL_TIME]); ?>" 
                        rel='nofollow'>
                        <?php
                        if($page[self::TYPE] == "text/html" || 
                            stristr($page[self::TYPE], "image")) {
                            e(tl('search_view_cache'));

                        } else {
                            e(tl('search_view_as_text'));
                        }
                        ?></a>.
                    <?php 
                    }
                    if(SIMILAR_LINK) { 
                    ?> 
                    <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                        ?>&amp;c=search&amp;a=related&amp;arg=<?php 
                        e(urlencode($page[self::URL])); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>" 
                        rel='nofollow'><?php 
                        e(tl('search_view_similar')); 
                    ?></a>.
                    <?php 
                    }
                    if(IN_LINK) { 
                    ?>
                        <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                        ?>&amp;c=search&amp;q=<?php 
                        e(urlencode("link:".$page[self::URL])); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>" 
                        rel='nofollow'><?php 
                        e(tl('search_view_inlink')); 
                    ?></a>.
                    <?php 
                    }
                    if(IP_LINK) { 
                    ?>
                    <?php if(isset($page[self::IP_ADDRESSES])){
                          foreach($page[self::IP_ADDRESSES] as $address) {?> 
                            <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                                ?>&amp;c=search&amp;q=<?php
                                e(urlencode('ip:'.$address));?>&amp;<?php
                                ?>its=<?php e($data['PHRASE']['its']); ?>" 
                                rel='nofollow'>IP:<?php 
                                e("$address");?></a>. <?php 
                          } 
                        }?></p>
                <?php
                    }
                } ?>
                </div>
            <?php 
            } //end foreach
            
            $this->paginationHelper->render(
                $data['PHRASE']['PAGING_QUERY']."&amp;YIOOP_TOKEN=".$data['YIOOP_TOKEN'], 
                $data['PHRASE']['LIMIT'], $data['PHRASE']['RESULTS_PER_PAGE'], $data['PHRASE']['TOTAL_ROWS']);     
        }
        ?>
        <div class="landing-footer">
            <div><b><?php e($data['INDEX_INFO']);?></b> <?php
            if(isset($data["HAS_STATISTICS"]) && $data["HAS_STATISTICS"]) { ?>
            [<a href="index.php?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                ?>&amp;c=statistics&amp;its=<?php e($data['PHRASE']['its']);?>"><?php 
                e(tl('search_view_more_statistics')); ?></a>]
            <?php }?>
            </div>
            <?php  $this->footerElement->render($data);?>
        </div><?php
        if(!isset($data['PHRASE']['PAGES'])) {
            e("</div><div class='landing-spacer'></div>");
        }

    }
}
?>
