<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012 Chris Pollett chris@pollett.org
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
 * Element responsible for drawing the screen used to set up the search engine
 *
 * This element has form fields to set up the work directory for crawls, 
 * the default language, the debug settings, the database, and the robot 
 * identifier information.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class EditstaticElement extends Element
{

    /**
     * Draws the forms used to edit static pages.
     *
     * @param array $data holds data on the static page that is beign edited
     *      and for which locale
     */
    public function render($data)
    {
    ?>
        <div class="currentactivity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=manageLocales&amp;YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN']) ?>&selectlocale=<?php 
            e($data['CURRENT_LOCALE_TAG']) ?>&amp;arg=editlocale"
        ><?php e(tl('editlocales_element_back_to_manage'))?></a>
        </div>
        <form id="editstaticForm" method="post" action=''>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="YIOOP_TOKEN" value="<?php 
                e($data['YIOOP_TOKEN']); ?>" />
            <input type="hidden" name="a" value="manageLocales" />
            <input type="hidden" name="arg" value="editlocale" />
            <input type="hidden" name="selectlocale" value="<?php 
                e($data['CURRENT_LOCALE_TAG']); ?>" />
            <input type="hidden" name="static_page" value="<?php 
                e($data['STATIC_PAGE']); ?>" />
            <div class="topmargin">
                <b><?php 
                e(tl('editstatic_element_locale_name', 
                    $data['CURRENT_LOCALE_NAME'])); 
                ?></b><br />
                <label for="page-data"><b><?php 
                e(tl('editstatic_element_page', $data['PAGE_NAME'])); 
                ?></b></label></div>
            <textarea class="talltextarea"  name="PAGE_DATA" ><?php 
                e($data['PAGE_DATA']);
            ?></textarea>
            <div class="topmargin center">
            <button class="buttonbox" type="submit"><?php 
                e(tl('editstatic_element_savebutton')); ?></button>
            </div>
            </div>

        </form>
        </div>

    <?php
    }
}
?>
