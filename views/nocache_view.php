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
 * This view is drawn when someone clicks
 * on the cached link of a web-page for which
 * no cache is available
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class NocacheView extends View
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";
    /** Names of element objects that the view uses to display itself 
     *  @var array
     */
    var $elements = array("language");

    /**
     *  Draws a simple message saying no cache available of
     *  the requested page
     *
     *  @param array $data   at this point this view does not make
     *  use of the $data info passed to it.
     */
    public function renderView($data) {
        ?>
        <h1><?php e(tl('nocache_view_no_cache')); ?></h1>
        <?php if(isset($data["SUMMARY_STRING"])) {?>
           <p><?php e(tl('nocache_view_summary_contents')); ?></p>
           <pre>
           <?php e($data["SUMMARY_STRING"]); ?>
           </pre>
        <?php
        }
    }
}
?>
