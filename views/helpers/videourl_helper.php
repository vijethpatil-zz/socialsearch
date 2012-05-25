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
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";

/**
 * Helper used to draw thumbnails for video sites
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */
 
class VideourlHelper extends Helper
{

    /**
     *  Used to check if a url is the url of a video site and if so
     *  draw a link with a thumbnail from the video.
     *  @param string $url to check if of a video site
     */
    public function render($url)
    {
        if(substr($url, 0, 3) == "url") {
            $link_url_parts = explode("|", $url);
            if(count($link_url_parts) > 1) {
                $url = $link_url_parts[1];
            }
        }
        if(stripos($url, "http://www.youtube.com/watch?v=") !== false) {
            $id = substr($url, 31, 11);
            ?><a class="video-link" href="<?php e($url); ?>"><img 
                src="http://img.youtube.com/vi/<?php e($id); ?>/2.jpg"
                alt="Thumbnail for <?php e($id); ?>" />
                <img class="video-play" src="resources/play.png" alt="" />
              </a>
            <?php 
        } else if(stripos($url, "http://www.metacafe.com/watch/") !== false) {
            $pre_id = substr($url, 30);
            $parts = explode("/", $pre_id);
            $id = $parts[0];
            ?><a class="video-link" href="<?php e($url); ?>"><img class="thumb"
                src="http://www.metacafe.com/thumb/<?php e($id); ?>.jpg"
                alt="Thumbnail for <?php e($id); ?>" />
                <img class="video-play" src="resources/play.png" alt="" />
              </a>
            <?php 
        } else if(stripos($url, "http://www.dailymotion.com/video/") !== false){
            $rest = substr($url, 26);
            $thumb_url = "http://www.dailymotion.com/thumbnail".$rest;
            ?><a class="video-link" href="<?php e($url); ?>"><img class="thumb"
                src="<?php e($thumb_url); ?>"
                alt="Thumbnail for DailyMotion" />
                <img class="video-play" src="resources/play.png" alt="" />
              </a>
            <?php 
        }
    }

}
?>
