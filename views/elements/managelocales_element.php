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
 * This Element is responsible for drawing screens in the Admin View related
 * to localization. Namely, the ability to create, delete, and text writing mode
 * for locales as well as the ability to modify translations within a locale.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagelocalesElement extends Element
{

    /**
     * Responsible for drawing the ceate, delete set writing mode screen for 
     * locales as well ass the screen for adding modifying translations
     *
     * @param array $data  contains info about the available locales and what 
     *      has been translated
     */
    public function render($data) 
    {
    ?>
        <div class="currentactivity">
        <h2><?php e(tl('managelocales_element_add_locale'))?></h2>
        <form id="addLocaleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="addlocale" />

        <table class="nametable">
            <tr><td><label for="locale-name"><?php 
                e(tl('managelocales_element_localenamelabel'))?></label></td>
                <td><input type="text" id="locale-name" 
                    name="localename" maxlength="80" class="narrowfield"/>
                </td><td></td>
            </tr>
            <tr><td><label for="locale-tag"><?php 
                e(tl('managelocales_element_localetaglabel'))?></label></td>
                <td><input type="text" id="locale-tag" 
                name="localetag"  maxlength="80" class="narrowfield"/></td>
            </tr>
            <tr><td><?php e(tl('managelocales_element_writingmodelabel'))?></td>
            <td><label for="locale-lr-tb">lr-tb</label><input type="radio" 
                id="locale-lr-tb" name="writingmode" 
                value="lr-tb" checked="checked" />
            <label for="locale-rl-tb">rl-tb</label><input type="radio" 
                id="locale-rl-tb" name="writingmode" value="rl-tb" />
            <label for="locale-tb-rl">tb-rl</label><input type="radio" 
                id="locale-tb-rl" name="writingmode" value="tb-rl" />
            <label for="locale-tb-lr">tb-lr</label><input type="radio" 
                id="locale-tb-lr" name="writingmode" value="tb-lr" />
            </td>
            </tr>
            <tr><td></td><td class="center"><button class="buttonbox" 
                type="submit"><?php e(tl('managelocales_element_submit')); 
                ?></button></td>
            </tr>
        </table>
        </form>
        
        <h2><?php e(tl('managelocales_element_delete_locale'))?></h2>
        <form id="deleteLocaleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="deletelocale" />

        <table class="nametable" >
        <tr><td><label for="delete-localename"><?php 
            e(tl('managelocales_element_delete_localelabel'))?></label></td>
            <td><?php $this->view->optionsHelper->render("delete-localename", 
                "selectlocale", $data['LOCALE_NAMES'], "-1"); ?></td>
            <td><button class="buttonbox" type="submit"><?php 
                e(tl('managelocales_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>
        
        <h2><?php e(tl('managelocales_element_locale_list'))?></h2>
        <table class="localetable">
            <tr>
            <th><?php e(tl('managelocales_element_localename')); ?></th>
            <th><?php e(tl('managelocales_element_localetag'));?></th>
            <th><?php e(tl('managelocales_element_writingmode'));
                ?></th>
            <th><?php  e(tl('managelocales_element_percenttranslated'));?></th>
            </tr>
        <?php
        foreach($data['LOCALES'] as $locale) {
            e("<tr><td><a href='?c=admin&amp;a=manageLocales".
                "&amp;arg=editlocale&amp;selectlocale=".$locale['LOCALE_TAG'].
                "&amp;YIOOP_TOKEN=".$data['YIOOP_TOKEN']."'>".
                $locale['LOCALE_NAME']."</a></td><td>".
                $locale['LOCALE_TAG']."</td>");
            e("<td>".$locale['WRITING_MODE']."</td><td class='alignRight' >".
                $locale['PERCENT_WITH_STRINGS']."</td></tr>");
        }
        ?>
        </table>
        </div>
    <?php
    }
}
?>
