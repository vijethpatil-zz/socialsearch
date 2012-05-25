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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Used to draw the admin screen on which admin users can create roles, delete 
 * roles and add and delete roles from users
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagefeedsElement extends Element
{

    /**
     * renders the screen in which roles can be created, deleted, and added or 
     * deleted from a user
     *
     * @param array $data  contains antiCSRF token, as well as data on 
     *      available roles or which user has what role
     */
    public function render($data) 
    {?>
        <div class="currentactivity">
        <h2><?php e(tl('managefeeds_element_add_feed'))?></h2>
        <form id="addFeedForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="manageFeeds" />
        <input type="hidden" name="arg" value="addfeed" />

        <table class="nametable">
        <tr><td><label for="feed-screenname"><?php 
            e(tl('managefeeds_element_feedscreen_name'))?></label></td>
            <td><input type="text" id="feed-screenname" name="feedscreenname" 
                maxlength="80" class="narrowfield" /></td>
        </tr>
        <tr><td><label for="feed-tokenname"><?php 
            e(tl('managefeeds_element_feedtoken_name'))?></label></td>
            <td><input type="text" id="feed-tokenname" name="feedtokenname" 
                maxlength="80" class="narrowfield" /></td>
        </tr>
        <tr><td><label for="feed-secretname"><?php 
            e(tl('managefeeds_element_feedsecret_name'))?></label></td>
            <td><input type="text" id="feed-secretname" name="feedsecretname" 
                maxlength="80" class="narrowfield" /></td>
        </tr>
        <tr><td class="center"><button class="buttonbox" type="submit"><?php 
                e(tl('managefeeds_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('managefeeds_element_add_rssfeed'))?></h2>
        <form id="addRSSFeedForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="manageFeeds" />
        <input type="hidden" name="arg" value="addRSSfeed" />

        <table class="nametable">
        <tr><td><label for="rssfeed-url"><?php 
            e(tl('managefeeds_element_rssfeed_url'))?></label></td>
            <td><input type="text" id="rssfeed-url" name="rssfeedurl" 
                maxlength="580" class="narrowfield" /></td>
        </tr>
        <tr><td class="center"><button class="buttonbox" type="submit"><?php 
                e(tl('managefeeds_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>
        <h2><?php e(tl('managefeeds_element_delete_feed'))?></h2>
        <form id="deleteFeedForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageFeeds" /> 
        <input type="hidden" name="arg" value="deletefeed" />
        <table class="nametable">
         <tr><th><label for="delete-feedname"><?php 
            e(tl('managefeeds_element_delete_feedname'))?></label></th>
            <td><?php $this->view->optionsHelper->render(
                "delete-feedname", "feedname", 
                $data['FEED_NAMES'], 
                tl('managefeeds_element_select_feed')); 
                ?></td><td><button class="buttonbox" type="submit"><?php 
                e(tl('managefeeds_element_submit')); ?></button></td>
         </tr>
        </table>
        </form>

        </div>
    <?php 
    }
}
?>
