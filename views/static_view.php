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
 * This View is responsible for drawing the landing page
 * of the Seek Quarry app
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class StaticView extends View
{

    /** This view is makes use of the localized static page overview.thtml
     *  @var array
     */
    var $pages = array('privacy', 'blog', 'bot', "404", "409");

    /** Names of element objects that the view uses to display itself 
     *  @var array
     */
    var $elements = array("footer");

    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     *  Draws the login web page.
     *
     *  @param array $data  contains the anti CSRF token YIOOP_TOKEN
     *  the view
     */
    function renderView($data) {
    $logo = "resources/yioop.png";
    if(MOBILE) {
        $logo = "resources/m-yioop.png";
    }
?>
<div class="non-search center">
<h1 class="logo"><a href="."><img src="<?php e($logo); ?>" 
    alt="<?php e(tl('static_view_title')); ?>" /></a><span><?php 
    e($data['subtitle']);?></span></h1>
</div>
<div class="content">
<?php e($this->page_objects[$data['page']]); ?>
</div>
<div class="landing-footer">
<?php  $this->footerElement->render($data);?>
</div>
<?php
    }
}
?>
