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

/**
 * View responsible for drawing the admin pages of the
 * SeekQuarry search engine site
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class AdminView extends View
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";
    /** Names of element objects that the view uses to display itself 
     *  @var array
     */
    var $elements = array("language", "activity", "signin", 
        "managecrawls", "manageaccount", "manageusers", "manageroles",
        "mixcrawls", "managelocales", "editlocales", "crawloptions",
        "editmix", "pageoptions", "resultseditor", 
        "managemachines", "machinelog", "editstatic", "configure", "managefeeds");

    /** Names of helper objects that the view uses to help draw itself 
     *  @var array
     */
    var $helpers = array('options');

    /**
     * Renders the list of admin activities and draws the current activity
     * Renders the Javascript to autologout after an hour
     *
     * @param array $data  what is contained in this array depend on the current
     * admin activity. The $data['ELEMENT'] says which activity to render
     */
    public function renderView($data) {
        $logo = "resources/yioop.png";
        if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        if(PROFILE) {
        ?>
        <div class="topbar"><?php
            $this->signinElement->render($data);
        ?>
        </div><?php
        }

        ?>

        <h1 class="admin-heading logo"><a href="./?YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN'])?>"><img 
            src="<?php e($logo); ?>" alt="Yioop!" /></a><span> - <?php 
        e(tl('admin_view_admin')); 
        if(!MOBILE) {e(' ['.$data['CURRENT_ACTIVITY'].']');}?></span></h1>

        <?php
        $this->activityElement->render($data);
        if(isset($data['ELEMENT'])) { 
            $element = $data['ELEMENT'];

            $this->$element->render($data);
        }
        if(PROFILE) {
        ?>
        <script type="text/javascript">
        /*
            Used to warn that user is about to be logged out
         */
        function logoutWarn()
        {
            doMessage(
                "<h2 class='red'><?php 
                    e(tl('adminview_auto_logout_one_minute'))?></h2>");
        }
        /*
            Javscript to perform autologout
         */
        function autoLogout()
        {
            document.location='?c=search&amp;a=signout';
        }

        //schedule logout warnings
        var sec = 1000;
        var minute = 60*sec;
        setTimeout("logoutWarn()", 59*minute);
        setTimeout("autoLogout()", 60*minute);
        
        </script>
        <?php
        }
    }
}
?>
