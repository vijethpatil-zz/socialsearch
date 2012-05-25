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
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";

/**
 * This is a helper class is used to draw
 * an On-Off switch in a web page
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */
 
class ToggleHelper extends Helper
{

    /**
     *  Draws an On Off switch in HTML where to toggle state one
     *  clicks a link
     *
     *  @parm bool $state whether the switch is on or off
     *  @param string $on_url - url that is sent when one clicks on
     *  @param string $off_url - url that is sent when one clicks off
     */
    public function render($state, $on_url, $off_url)
    { 
        if($state) {?>
            <table class="toggletable"><tr><td 
            class="backgreen"><b><?php e(tl('toggle_helper_on'));?></b></td>
            <td><a href="<?php e($off_url);?>"
            ><?php e(tl('toggle_helper_off'));?></a></td></tr></table>
        <?php } else {?>
            <table class="toggletable"><tr><td><a href="<?php e($on_url);?>"
            ><?php e(tl('toggle_helper_on'));?></a></td>
            <td  class="backred"><b><?php e(tl('toggle_helper_off'));?></b></td>
            </tr></table>
        <?php }
    }

}
?>
