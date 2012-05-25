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
 * Element used to control how urls are filtered out of search results
 * (if desired) after a crawl has already been performed.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class ResultsEditorElement extends Element
{

    /**
     * Draws the Screen for the Search Filter activity. This activity is
     * used to filter urls out of the search results
     *
     * @param array $data keys used to store disallowed_sites
     */
    public function render($data) 
    {
    ?>
        <div class="currentactivity">
        <h2><?php e(tl('resultseditor_element_edit_page'))?></h2>
        <form id="urlLookupForm" method="post" action=''>
        <div  class="topmargin"><b><label for="edited-result-pages"><?php 
            e(tl('resultseditor_element_edited_pages'))?></label>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="resultsEditor" /> 
        <input type="hidden" name="arg" value="load_url" />
        <?php $this->view->optionsHelper->render(
                "edited-result-pages", "LOAD_URL", 
                $data['URL_LIST'], 
                tl('resultseditor_element_url_list')); 
            ?><button class="buttonbox" type="submit" ><?php 
            e(tl('resultseditor_element_load_page')); 
            ?></button>
        </div>
        </form>

        <form id="urlUpdateForm" method="post" 
            action='?c=admin&amp;a=resultsEditor&amp;YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN']); ?>' >
        <div  class="topmargin">
        <input type="hidden" name="arg" value="save_page" />
        <b><label for="urlfield"><?php 
            e(tl('resultseditor_element_page_url'))?></label></b>
        <input type="text" id="urlfield" 
            name="URL"  class="extrawidefield" value='<?php 
                e($data["URL"]); ?>' />
        </div>
        <div  class="topmargin">
        <b><label for="titlefield"><?php 
            e(tl('resultseditor_element_page_title'))?></label></b>
        <input type="text" id="titlefield" 
            name="TITLE"  class="extrawidefield" value='<?php 
                e($data["TITLE"]); ?>' />
        </div>
        <div class="topmargin"><label for="descriptionfield"><b><?php 
            e(tl('resultseditor_element_description')); 
                ?></b></label></div>
        <textarea class="talltextarea" id="descriptionfield" 
            name="DESCRIPTION" ><?php e($data['DESCRIPTION']);
        ?></textarea>
        <div class="center slightpad"><button class="buttonbox" 
            type="reset"><?php e(tl('resultseditor_element_reset')); 
            ?></button> &nbsp;&nbsp; <button class="buttonbox" 
            type="submit" ><?php 
            e(tl('resultseditor_element_save_page')); 
            ?></button></div>
        </form>
        <h2><?php e(tl('resultseditor_element_filter_websites'))?></h2>
        <form id="searchfiltersForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="resultsEditor" />
        <input type="hidden" name="arg" value="urlfilter" />
        <input type="hidden" name="posted" value="posted" />

        <div class="topmargin"><label for="disallowed-sites"><b><?php 
            e(tl('resultseditor_element_sites_to_filter')); 
                ?></b></label></div>
        <textarea class="talltextarea" id="disallowed-sites" 
            name="disallowed_sites" ><?php e($data['disallowed_sites']);
        ?></textarea>

        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php e(tl('resultseditor_element_save_filter')); 
            ?></button></div>
        </form>
        </div>

    <?php
    }
}
?>
