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
 * Used to draw the admin screen on which admin users can create roles, delete 
 * roles and add and delete roles from users
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagerolesElement extends Element
{

    /**
     * renders the screen in which roles can be created, deleted, and added or 
     * deleted from a user
     *
     * @param array $data  contains antiCSRF token, as well as data on 
     *      available roles or which user has what role
     */
    public function render($data) 
    {?>
        <div class="currentactivity">
        <h2><?php e(tl('manageroles_element_add_role'))?></h2>
        <form id="addRoleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="manageRoles" />
        <input type="hidden" name="arg" value="addrole" />

        <table class="nametable">
        <tr><td><label for="role-name"><?php 
            e(tl('manageroles_element_rolename'))?></label></td>
            <td><input type="text" id="role-name" name="rolename" 
                maxlength="80" class="narrowfield" /></td><td 
                class="center"><button class="buttonbox" type="submit"><?php 
                e(tl('manageroles_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('manageroles_element_delete_role'))?></h2>
        <form id="deleteRoleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageRoles" /> 
        <input type="hidden" name="arg" value="deleterole" />

        <table class="nametable">
         <tr><td><label for="delete-rolename"><?php 
            e(tl('manageusers_element_delete_rolename'))?></label></td>
            <td><?php $this->view->optionsHelper->render(
                "delete-rolename", "selectrole", $data['ROLE_NAMES'], "-1"); 
                ?></td><td><button class="buttonbox" type="submit"><?php 
                e(tl('manageroles_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>
        <h2><?php e(tl('manageroles_element_view_role_activities'))?></h2>
        <form id="viewRoleActivityForm" method="get" action='' >
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="manageRoles" /> 
        <input type="hidden" name="arg" value="viewroleactivities" />
        <table class="nametable">
        <tr><td><label for="select-role"><?php 
            e(tl('manageusers_element_select_role'))?></label></td>
            <td><?php $this->view->optionsHelper->render("select-role", 
            "selectrole", $data['ROLE_NAMES'], $data['SELECT_ROLE']); 
            ?></td></tr>
        </table>
        </form>
        <?php
        if(isset($data['ROLE_ACTIVITIES'])) {
             if(count($data['AVAILABLE_ACTIVITIES']) > 0  && 
                $data['SELECT_ROLE'] != -1) { ?>
                <form id="addRoleActivityForm" method="get" action='' >
                <input type="hidden" name="c" value="admin" /> 
                <input type="hidden" name="YIOOP_TOKEN" value="<?php 
                    e($data['YIOOP_TOKEN']); ?>" />
                <input type="hidden" name="a" value="manageRoles" /> 
                <input type="hidden" name="arg" value="addactivity" />
                <input type="hidden" name="selectrole" value="<?php 
                    e($data['SELECT_ROLE']);?>" />
                <table class="nametable">
                 <tr><td><label for="add-activity"><?php 
                    e(tl('manageusers_element_add_activity'))?></label></td>
                    <td><?php $this->view->optionsHelper->render("add-activity", 
                        "selectactivity", $data['AVAILABLE_ACTIVITIES'], 
                        $data['SELECT_ACTIVITY']); ?></td>
                    <td><button class="buttonbox" type="submit"><?php 
                    e(tl('manageroles_element_submit')); ?></button></td></tr>
                 </table>
                 </form>
             <?php
             }
             ?>
             <table class="roletable"><?php
             foreach($data['ROLE_ACTIVITIES'] as $role_activity) {
                 e("<tr><td>".$role_activity['ACTIVITY_NAME'].
                    "</td><td><a href='?c=admin&amp;a=manageRoles".
                    "&amp;arg=deleteactivity&amp;selectrole=".
                    $role_activity['ROLE_ID'].
                    "&amp;selectactivity=".$role_activity['ACTIVITY_ID'].
                    "&amp;YIOOP_TOKEN=".$data['YIOOP_TOKEN'].
                    "'>Delete</a></td>");
             }
             ?>
             </table>
        <?php
        }
        ?>
        <script type="text/javascript">
        function submitViewRoleActivities()
        {
            elt('viewRoleActivityForm').submit();
        }
        </script>
        </div>
    <?php 
    }
}
?>
