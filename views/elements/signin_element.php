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
 * Element responsible for drawing links to settings and login panels 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class SigninElement extends Element
{

    /**
     *  Method responsible for drawing links to settings and login panels 
     *
     *  @param array $data makes use of the YIOOP_TOKEN for anti CSRF attacks
     */
    public function render($data)
    {
    ?>
        <div class="userNav" >
        <ul>
        <?php
        if(WEB_ACCESS) { ?>
        <li><a href="?c=settings&amp;YIOOP_TOKEN=<?php 
                e($data['YIOOP_TOKEN'])?>&amp;l=<?php
                e(getLocaleTag()); 
                e((isset($data['its'])) ? '&amp;its='.$data['its'] : '');
                ?>"><?php 
            e(tl('signin_element_settings')); ?></a></li>
        <?php }
        if(SIGNIN_LINK) {
            if(!isset($_SESSION['USER_ID'])) { ?>
                <li><a href="?c=admin"><?php 
                    e(tl('signin_element_signin')); ?></a></li>
            <?php 
            } else {
            ?>
                <li><a href="?c=admin&amp;YIOOP_TOKEN=<?php 
                    e($data['YIOOP_TOKEN'])?>"><?php 
                        e(tl('signin_element_admin')); ?></a></li>  
                <li><a href="?c=search&amp;a=signout"><?php 
                    e(tl('signin_element_signout')); ?></a></li>
            <?php 
            }
        } 
        ?>
        </ul>
        </div>
    <?php
    }
}
?>
