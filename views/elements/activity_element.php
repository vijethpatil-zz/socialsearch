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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */
 
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This element is used to display the list of available activities
 * in the AdminView
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class ActivityElement extends Element
{

    /**
     * Displays a list of admin activities
     *
     * @param array $data  available activities and CSRF token
     */
    public function render($data) 
    {
    ?>
        <div class="frame activitymenu">
        <h2><?php e(tl('activity_element_activities')); ?></h2>
        <ul>
        <?php
        if(isset($data['ACTIVITIES'])) {
            $count = count($data['ACTIVITIES']);
            $activities = $data['ACTIVITIES'];
            if(MOBILE) {
                $out_activities = array();
                $base_url = "?c=admin&amp;YIOOP_TOKEN=".$data['YIOOP_TOKEN'].
                    "&amp;a=";
                $current = "";
                foreach($activities as $activity) {
                    $out_activities[$base_url .
                        $activity['METHOD_NAME'] ]= $activity['ACTIVITY_NAME'];
                    if(strcmp($activity['ACTIVITY_NAME'], 
                        $data['CURRENT_ACTIVITY']) == 0) {
                        $current = $base_url .$activity['METHOD_NAME'];
                    }
                }

                $this->view->optionsHelper->render(
                    "activity", "a", $out_activities,  $current);
                ?>
                <script type="text/javascript">
                activity_select = document.getElementById('activity');
                function activityChange() {
                    document.location = activity_select.value;
                }

                activity_select.onchange = activityChange;
                </script>
                <?php
            } else {
                for($i =0 ; $i < $count; $i++) {
                    if($i < $count - 1) {
                        $class="class='bottom_border'";
                    } else {
                        $class="";
                    }
                    e("<li $class><a href='?c=admin&amp;YIOOP_TOKEN=".
                        $data['YIOOP_TOKEN']."&amp;a=".
                        $activities[$i]['METHOD_NAME']."'>".
                        $activities[$i]['ACTIVITY_NAME']."</a></li>");
                }
            }
        }
        ?>
        </ul>
        </div>
    <?php
    }
}
?>
