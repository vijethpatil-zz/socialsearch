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
 * This element is used to render the Page Options admin activity
 * This activity lets a usercontrol the amount of web pages downloaded,
 * the recrawl frequency, the file types, etc of the pages crawled
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class PageOptionsElement extends Element
{

    /**
     * Draws the page options element to the output buffer
     *
     * @param array $data used to keep track of page range, recrawl frequency,
     *  and file types of the page
     */
    public function render($data) 
    {
        global $INDEXED_FILE_TYPES;
    ?>
        <div class="currentactivity">
        <form id="pageoptionsForm" method="get" action='?'>
        <h2><?php e(tl('pageoptions_element_crawl_time'))?></h2>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="pageOptions" />
        <div class="topmargin"><b><label for="page-range-request"><?php 
            e(tl('pageoptions_element_page_range'))?></label></b>
            <?php $this->view->optionsHelper->render("page-range-request", 
            "page_range_request", $data['SIZE_VALUES'], $data['PAGE_SIZE']); 
            ?></div>
        <div class="topmargin"><b><label for="allow-recrawl"><?php 
            e(tl('pageoptions_element_allow_recrawl'))?></label></b>
            <?php $this->view->optionsHelper->render("page-recrawl-frequency", 
            "page_recrawl_frequency", $data['RECRAWL_FREQS'], 
                $data['PAGE_RECRAWL_FREQUENCY']); 
            ?></div>
        <div class="topmargin"><b><?php 
            e(tl('pageoptions_element_file_types'))?></b>
       </div>
       <table class="ftypesall"><tr>
       <?php $cnt = 0;
             foreach ($data['INDEXED_FILE_TYPES'] as $filetype => $checked) { 
                 if($cnt % 10 == 0) {
                    ?><td><table class="filetypestable" ><?php
                 }
       ?>
            <tr><td><label for="<?php e($filetype); ?>-id"><?php 
                e($filetype); ?>
            </label></td><td><input type="checkbox" <?php e($checked) ?>
                name="filetype[<?php  e($filetype); ?>]" value="true" /></td>
            </tr>
       <?php 
                $cnt++;
                if($cnt % 10 == 0) {
                    ?></table></td><?php
                }
            }?>
        <?php
            if($cnt % 10 != 0) {
                ?></table></td><?php
            }
        ?>
        </tr></table>
        <h2><?php e(tl('pageoptions_element_page_scoring'))?></h2>
        <table class="weightstable" >
        <tr><th><label for="title-weight"><?php 
            e(tl('pageoptions_element_title_weight'))?></label></th><td>
            <input type="text" id="title-weight" size="3" maxlength="6"
                name="TITLE_WEIGHT" 
                value="<?php  e($data['TITLE_WEIGHT']); ?>" /></td></tr>
        <tr><th><label for="description-weight"><?php 
            e(tl('pageoptions_element_description_weight'))?></label></th><td>
            <input type="text" id="description-weight" size="3" maxlength="6"
                name="DESCRIPTION_WEIGHT" 
                value="<?php  e($data['DESCRIPTION_WEIGHT']); ?>" /></td></tr>
        <tr><th><label for="link-weight"><?php 
            e(tl('pageoptions_element_link_weight'))?></label></th><td>
            <input type="text" id="link-weight" size="3" maxlength="6"
                name="LINK_WEIGHT" 
                value="<?php  e($data['LINK_WEIGHT']); ?>" /></td></tr>
        </table>
        <h2><?php e(tl('pageoptions_element_results_grouping_options'))?></h2>
        <table class="weightstable" >
        <tr><th><label for="min-results-to-group"><?php 
            e(tl('pageoptions_element_min_results_to_group'))?></label></th><td>
            <input type="text" id="min-results-to-group" size="3" maxlength="6"
                name="MIN_RESULTS_TO_GROUP" 
                value="<?php  e($data['MIN_RESULTS_TO_GROUP']); ?>" /></td></tr>
        <tr><th><label for="server-alpha"><?php 
            e(tl('pageoptions_element_server_alpha'))?></label></th><td>
            <input type="text" id="server-alpha" size="3" maxlength="6"
                name="SERVER_ALPHA" 
                value="<?php e($data['SERVER_ALPHA']); ?>" /></td></tr>
        </table>
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php e(tl('pageoptions_element_save_options')); 
            ?></button></div>
        </form>
        </div>

    <?php
    }
}
?>
