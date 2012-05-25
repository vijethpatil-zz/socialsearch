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
 * This View is responsible for drawing the login
 * screen for the admin panel of the Seek Quarry app
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class SigninView extends View
{

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
    public function renderView($data) {
    $logo = "resources/yioop.png";
    if(MOBILE) {
        $logo = "resources/m-yioop.png";
    }
?>
<div class="landing non-search">
<h1 class="logo"><a href="./?YIOOP_TOKEN=<?php 
    e($data['YIOOP_TOKEN'])?>"><img src="<?php e($logo); ?>" alt="Yioop!" 
        /></a><span> - <?php e(tl('signin_view_signin')); ?></span></h1>
<form class="user_settings" method="post" action="#">
<div class="login">
    <table>
    <tr>
    <td class="table-label" ><b><label for="username"><?php 
        e(tl('signin_view_username')); ?></label>:</b></td><td 
            class="table-input"><input id="username" type="text" 
            class="narrowfield" maxlength="80" name="u"/>
    </td><td></td></tr>
    <tr>
    <td class="table-label" ><b><label for="password"><?php 
        e(tl('signin_view_password')); ?></label>:</b></td><td 
        class="table-input"><input id="password" type="password" 
        class="narrowfield" maxlength="80" name="p" /></td>
    <td><input type="hidden" name="YIOOP_TOKEN" value="<?php 
        e($data['YIOOP_TOKEN']); ?>" />
    </td>
    </tr>
    <tr><td>&nbsp;</td><td class="center">
    <button  type="submit" name="c" value="admin"><?php 
        e(tl('signin_view_login')); ?></button>
    </td><td>&nbsp;</td></tr>
    </table>
</div>
</form>

<div class="signin-exit"><a href="."><?php 
    e(tl('signin_view_return_yioop')); ?></a></div>
</div>

<div class='landing-spacer'></div>
<?php
    }
}
?>
