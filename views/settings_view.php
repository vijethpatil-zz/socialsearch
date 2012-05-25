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
 *
 * Draws the view on which people can control
 * their search settings such as num links per screen
 * and the language settings
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class SettingsView extends View
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";
    /** 
     * Names of element objects that the view uses to display itself 
     * @var array
     */
    var $elements = array("language");
    /** 
     * Names of helper objects that the view uses to help draw itself
     * @var array
     */
    var $helpers = array('options');

    /**
     * sDraws the web page on which users can control their search settings.
     *
     * @param array $data   contains anti CSRF token YIOOP_TOKEN, as well
     *      the language info and the current and possible per page settings
     */
    public function renderView($data) {
    $logo = "resources/yioop.png";
    if(MOBILE) {
        $logo = "resources/m-yioop.png";
    }
?>
<div class="landing non-search">
<h1 class="logo"><a href="./?YIOOP_TOKEN=<?php 
    e($data['YIOOP_TOKEN'])?>&amp;its=<?php 
    e($data['its'])?>"><img 
    src="<?php e($logo); ?>" alt="Yioop!" /></a><span> - <?php 
    e(tl('settings_view_settings')); ?></span></h1>
<div class="settings">
<form class="user_settings" method="get" action=".">
<table>
<tr>
<td class="table-label"><label for="per-page"><b><?php 
    e(tl('settings_view_results_per_page')); ?></b></label></td><td 
    class="table-input"><?php $this->optionsHelper->render(
    "per-page", "perpage", $data['PER_PAGE'], $data['PER_PAGE_SELECTED']); ?>
</td></tr>
<tr><td class="table-label"><label for="locale"><b><?php 
    e(tl('settings_view_language_label')); ?></b></label></td><td 
    class="table-input"><?php $this->languageElement->render($data); ?>
</td></tr>
<tr>
<td class="table-label"><label for="index-ts"><b><?php 
    e(tl('settings_view_search_index')); ?></b></label></td><td 
    class="table-input"><?php $this->optionsHelper->render(
    "index-ts", "index_ts", $data['CRAWLS'], $data['its']); ?>
</td></tr>
<tr><td><input type="hidden" name="YIOOP_TOKEN" value="<?php 
    e($data['YIOOP_TOKEN']); ?>" /><input type="hidden" 
    name="its" value="<?php e($data['its']); ?>" /><button 
    class="topmargin" type="submit" name="c" value="search"><?php 
    e(tl('settings_view_return_yioop')); 
    ?></button></td><td class="table-input">
<button class="topmargin" type="submit" name="c" value="settings"><?php 
    e(tl('settings_view_save')); ?></button>
</td></tr>
</table>
</form>
</div>
<div class="setting-footer"><a 
            href="javascript:window.external.AddSearchProvider('<?php 
            e(NAME_SERVER."yioopbar.xml");?>')"><?php 
    e(tl('setting_install_search_plugin'));
?></a>.</div>
</div>
<div class='landing-spacer'></div>
<?php
    }
}
?>
