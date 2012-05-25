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
 * Element responsible for displaying info to allow a user to create
 * a crawl mix or edit an existing one
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class MixcrawlsElement extends Element
{

    /**
     * Draw form to start a new crawl, has div place holder and ajax code to 
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    public function render($data) 
    {
        $base_url = "?c=admin&a=mixCrawls&YIOOP_TOKEN=".
            $data['YIOOP_TOKEN']."&arg=";
        ?>
        <div class="currentactivity">
        <h2><?php e(tl('mixcrawls_element_make_mix'))?></h2>
        <form id="mixForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="createmix" />
        <?php if(isset($data['available_mixes'])) { ?>
        <?php } ?>
        <div class="topmargin"><label for="mix-name"><?php 
            e(tl('mixcrawls_element_mix_name')); ?></label> 
            <input type="text" id="mix-name" name="MIX_NAME" 
                value="" maxlength="80" 
                    class="widefield"/>
           <button class="buttonbox"  type="submit"><?php 
                e(tl('mixcrawls_element_create_button')); ?></button></div>
        </form>
        <?php if(isset($data['available_mixes']) && 
            count($data['available_mixes']) > 0) { ?>
        <h2><?php e(tl('mixcrawls_element_available_mixes'))?></h2>
        <table class="mixestable">
        <tr><th><?php e(tl('mixcrawls_view_name'));?></th>
        <th><?php e(tl('mixcrawls_view_definition'));?></th>
        <th colspan="3"><?php e(tl('mixcrawls_view_actions'));?></th></tr>
        <?php
        foreach($data['available_mixes'] as $mix) {
        ?>
            <tr><td><b><?php e($mix['MIX_NAME']); ?></b><br />
                <?php e($mix['MIX_TIMESTAMP']); ?><br /><?php
                e("<small>".date("d M Y H:i:s", $mix['MIX_TIMESTAMP']).
                    "</small>"); ?></td>
            <td><?php
                if(isset($mix['GROUPS']) && count($mix['GROUPS'])  > 0){
                    foreach($mix['GROUPS'] as $group_id => $group_data) {
                        if(!isset($group_data['RESULT_BOUND']) ||
                           !isset($group_data['COMPONENTS']) ||
                           count($group_data['COMPONENTS']) == 0) continue;
                        e(" #".$group_data['RESULT_BOUND']."[");
                        $plus = "";
                        foreach($group_data['COMPONENTS'] as $component){
                            $crawl_timestamp = $component['CRAWL_TIMESTAMP'];
                            e($plus.$component['WEIGHT']." * (".
                                $data['available_crawls'][
                                $crawl_timestamp]." + K:".
                                $component['KEYWORDS'].")");
                            $plus = "<br /> + ";
                        }
                        e("]<br />");
                    }
                } else {
                    e(tl('mixcrawls_view_no_components'));
                }
            ?></td>
            <td><a href="<?php e($base_url); ?>editmix&timestamp=<?php 
                e($mix['MIX_TIMESTAMP']); ?>"><?php 
                e(tl('mixcrawls_view_edit'));?></a></td>
            <td>
            <?php 
            if( $mix['MIX_TIMESTAMP'] != $data['CURRENT_INDEX']) { ?>
                <a href="<?php e($base_url); ?>index&timestamp=<?php 
                    e($mix['MIX_TIMESTAMP']); ?>"><?php 
                    e(tl('mixcrawls_set_index')); ?></a>
            <?php 
            } else { ?>
                <?php e(tl('mixcrawl_search_index')); ?>
            <?php
            }
            ?>
            </td>
            <td><a href="<?php e($base_url); ?>deletemix&timestamp=<?php 
                e($mix['MIX_TIMESTAMP']); ?>"><?php 
                e(tl('mixcrawls_view_delete'));?></a></td>

            </tr>
        <?php
        }
        ?></table>
        <?php } ?>
        </div>
    <?php 
    }
}
?>
