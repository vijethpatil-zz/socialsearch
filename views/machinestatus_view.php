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
 * This view is used to display information about
 * the on/off state of the queue_servers and fetchers managed by
 * this instance of Yioop.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */

class MachinestatusView extends View
{
    /** 
     * Names of helper objects that the view uses to help draw itself
     * toggle is used to draw an On/Off switch
     * @var array
     */
    var $helpers = array('toggle');

    /**
     * Draws the ManagestatusView to the output buffer
     *
     * @param array $data  contains on/off status info for each of the machines
     *      managed by this Yioop instance.
     */
    public function renderView($data) 
    {
        $base_url = "?c=admin&a=manageMachines&YIOOP_TOKEN=".
            $data['YIOOP_TOKEN']."&arg=";
        if(count($data['MACHINES']) == 0) {
            e(tl('machinestatus_view_no_monitored'));
        } else {
        foreach($data['MACHINES'] as $m) { ?>
            <div class="box">
            <h3 class="nomargin"><?php e($m['NAME']);?></h3>
            <p><?php e($m['URL']);
                $on_queue_server = $base_url . "update&name={$m['NAME']}".
                    "&action=start";
                $off_queue_server = $base_url . "update&name={$m['NAME']}".
                    "&action=stop";
                $on_mirror = $base_url . "update&name={$m['NAME']}".
                    "&action=mirror_start";
                $off_mirror = $base_url . "update&name={$m['NAME']}".
                    "&action=mirror_stop";
            ?></p>
            <?php if($m['PARENT'] != "") {
                $log_url = $base_url . "log&mirror_name={$m['NAME']}"
            ?>
                <table class="machinetable"><tr>
                <th><?php e(tl('machinestatus_view_mirrors', $m['PARENT'])); ?>
                    </th>
                <td>[<a href="<?php e($log_url);?>"><?php 
                    e(tl('machinestatus_view_log'));?>]</td><td><?php 
                    $this->toggleHelper->render(
                        isset($m['STATUSES']["mirror"]) , 
                        $on_mirror, $off_mirror);
                ?></td>
                </table>
                </div><br /><?php
                continue;
            } ?>
            <?php if($m['HAS_QUEUE_SERVER'] == "1") {
                $log_url = $base_url . "log&name={$m['NAME']}";
            ?>
                <table class="machinetable">
                <tr><th><?php e(tl('machinestatus_view_queue_server'));?>
                </th><td>[<a href="<?php e($log_url);?>"><?php 
                    e(tl('machinestatus_view_log'));?>]</a>
                    </td><td><?php 
                    $this->toggleHelper->render(
                        isset($m['STATUSES']["queue_server"]) , 
                        $on_queue_server, $off_queue_server);
                ?></td>
                </tr>
                </table>
            <?php } else {
                e("<h3>".tl('machinestatus_view_no_queue_server')."</h3>");
            }?><?php
            if($m['NUM_FETCHERS'] == 0) {
                e("<h3>".tl('machinestatus_view_no_fetchers')."</h3>");
            } else {
            for($i = 0; $i < $m['NUM_FETCHERS']; $i++) {
                $on_fetcher = $base_url . "update&name={$m['NAME']}".
                    "&action=start&fetcher_num=$i";
                $off_fetcher = $base_url . "update&name={$m['NAME']}".
                    "&action=stop&fetcher_num=$i";
                if($i  == 0) { ?>
                    <table class="machinetable">
                    <th colspan="<?php e(min($m['NUM_FETCHERS'] - $i, 4)); ?>"
                    ><?php e(tl('machinestatus_view_fetchers'));?></th></tr>
                    <tr>
                <?php } else if($i % 4 == 0) {?>
                    <table class="machinetable"><tr>
                <?php } ?>
                <td><table><tr><td>#<?php 
                $log_url = $base_url . "log&name={$m['NAME']}&fetcher_num=$i";
                if($i <10){e("0");} e($i); 
                ?>[<a href="<?php e($log_url);?>"><?php 
                    e(tl('machinestatus_view_log'));?></a>]</td>
                </tr><tr><td><?php 
                $this->toggleHelper->render(
                    isset($m['STATUSES']["fetcher"][$i]), 
                    $on_fetcher, $off_fetcher);?></td>
                </tr>
                </table>
                <?php if($i % 4  == 3) { ?>
                    </tr>
                    </table></td>
                <?php } ?>
        <?php }
            ?></tr></table><?php
        }
        ?></div><br /><?php
        }
    }
    }
}
?>
