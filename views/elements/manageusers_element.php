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
 *  Element responsible for drawing the activity screen for User manipulation
 *  in the AdminView. 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManageusersElement extends Element
{

    /**
     * draws a screen in which an admin can add users, delete users,
     * and manipulate user roles.
     * 
     * @param array $data info about current users and current roles, CSRF token
     */
    public function render($data) 
    {
    ?>
        <div class="currentactivity">
        <h2><?php e(tl('manageusers_element_add_user'))?></h2>
        <form id="addUserForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="adduser" />

        <table class="nametable">
        <tr><td><label for="user-name"><?php 
            e(tl('manageusers_element_username'))?></label></td>
            <td><input type="text" id="user-name" 
                name="username"  maxlength="80" class="narrowfield"/></td></tr>
        <tr><td><label for="pass-word"><?php
             e(tl('manageusers_element_password'))?></label></td>
            <td><input type="password" id="pass-word" 
                name="password"  maxlength="80" class="narrowfield"/></td></tr>
            <td><label for="retype-password"><?php 
                e(tl('manageusers_element_retype_password'))?></label></td>
            <td><input type="password" id="retype-password" 
                name="retypepassword"  maxlength="80" 
                class="narrowfield"/></td></tr>
        <tr><td></td><td class="center"><button class="buttonbox" 
            type="submit"><?php e(tl('manageusers_element_submit')); 
            ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('manageusers_element_delete_user'))?></h2>
        <form id="deleteUserForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="deleteuser" />

        <table class="nametable">
        <tr><td><label for="delete-username"><?php 
            e(tl('manageusers_element_delete_username'))?></label></td>
            <td><?php $this->view->optionsHelper->render(
                "delete-username", "username", $data['USER_NAMES'], ""); 
                ?></td><td><button class="buttonbox" type="submit"><?php 
                e(tl('manageusers_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('manageusers_element_view_user_roles'))?></h2>
        <form id="viewUserRoleForm" method="get" action='' >
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="viewuserroles" />
        <table class="nametable">
        <tr><td><label for="select-user"><?php 
            e(tl('manageusers_element_select_user'))?></label></td>
            <td><?php $this->view->optionsHelper->render("select-user", 
                "selectuser", $data['USER_NAMES'], $data['SELECT_USER']); 
                ?></td></tr>
        </table>
        </form>
        <?php 
        if(isset($data['SELECT_ROLES'])) {
            if(count($data['AVAILABLE_ROLES']) > 0) {
            ?>
                <form id="addUserRoleForm" method="get" action='' >
                <input type="hidden" name="c" value="admin" /> 
                <input type="hidden" name="YIOOP_TOKEN" value="<?php 
                    e($data['YIOOP_TOKEN']); ?>" />
                <input type="hidden" name="a" value="manageUsers" /> 
                <input type="hidden" name="arg" value="adduserrole" />
                <input type="hidden" name="selectuser" value="<?php 
                    e($data['SELECT_USER']); ?>" />
                <table summary="organizes the fields and columns of the 
                    view user role form" cellpadding="5px">
                <tr><td><label for="add-role"><?php 
                    e(tl('manageusers_element_add_role'))?></label></td>
                <td><?php $this->view->optionsHelper->render("add-userrole", 
                    "selectrole", $data['AVAILABLE_ROLES'], 
                    $data['SELECT_ROLE']); ?></td>
                <td><button class="buttonbox" type="submit"><?php 
                    e(tl('manageusers_element_submit')); ?></button></td></tr>
                </table>
                </form>
            <?php
            }
            ?>
            <table class="roletable" ><?php
            foreach($data['SELECT_ROLES'] as $role_array) {
                echo "<tr><td>".$role_array['ROLE_NAME'].
                    "</td><td><a href='?c=admin&a=manageUsers".
                    "&arg=deleteuserrole&selectrole=".$role_array['ROLE_ID'];
                echo "&selectuser=".$data['SELECT_USER'].
                    "&YIOOP_TOKEN=".$data['YIOOP_TOKEN']."'>Delete</a></td>";
            }
            ?>
            </table>
        <?php
        }
        ?>
        <script type="text/javascript">
        function submitViewUserRole()
        {
            elt('viewUserRoleForm').submit();
        }
        </script>
        </div>
    <?php
    }
}
?>
