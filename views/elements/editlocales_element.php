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
 * Element responsible for displaying the form where users can input string
 * translations for a given locale
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class EditlocalesElement extends Element
{

    /**
     * Draws a form with strings to translate and a text field for the 
     * translation into
     * the given locale. Strings with no translations yet appear in red
     *
     * @param array $data  contains msgid and already translated msg_string info
     */
    public function render($data) 
    {
    ?>
        <div class="currentactivity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=manageLocales&amp;YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN']) ?>"
        ><?php e(tl('editlocales_element_back_to_manage'))?></a>
        </div>
        <h2><?php e(tl('editlocales_element_edit_locale', 
            $data['CURRENT_LOCALE_NAME']))?></h2>
        <?php if(count($data['STATIC_PAGES']) > 1) {?>
        <form id="staticPageForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="editlocale" />
        <input type="hidden" name="selectlocale" value="<?php 
            e($data['CURRENT_LOCALE_TAG']); ?>" />
        <div class="topmargin"><b><label for="static-pages"><?php 
            e(tl('editlocales_element_static_pages'))?></label></b>
            <?php $this->view->optionsHelper->render("static-pages", 
            "static_page", $data['STATIC_PAGES'], -1); 
            ?></div>
        </form>
        <?php }?>
        <form id="editLocaleForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="editlocale" />
        <input type="hidden" name="selectlocale" value="<?php 
            e($data['CURRENT_LOCALE_TAG']); ?>" />
        <table class="translatetable">
        <?php 
        foreach($data['STRINGS'] as $msg_id => $msg_string) {
            if(strlen($msg_string) > 0) {
                e("<tr><td><label for='$msg_id'>$msg_id</label>".
                    "</td><td><input type='text' title='".
                    @tl($msg_id,"%s", "%s", "%s").
                    "' id='$msg_id' name='STRINGS[$msg_id]' ".
                    "value='$msg_string' /></td></tr>");
            } else {
                e("<tr><td><label for='$msg_id'>$msg_id</label></td><td><input".
                    " class='highlight' type='text' title='".
                    @tl($msg_id,"%s", "%s", "%s")."' id='$msg_id' ".
                    "name='STRINGS[$msg_id]' value='$msg_string' /></td></tr>");
            }
        }
        ?>
        </table>
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php 
                e(tl('editlocales_element_submit')); ?></button></div>
        </form>
        </div>
        <script type="text/javascript">
        function submitStaticPageForm()
        {
            elt('staticPageForm').submit();
        }

        </script>
    <?php
    }
}
?>
