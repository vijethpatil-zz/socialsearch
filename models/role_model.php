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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads base model class if necessary*/
require_once BASE_DIR."/models/model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/**
 * This is class is used to handle
 * db results related to Role Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class RoleModel extends Model 
{


    /**
     *  {@inheritdoc}
     */
    function __construct() 
    {
        parent::__construct();
    }


    /**
     *  Get the activities  (name, method, id) that a given role can perform
     *
     *  @param string $role_id  the rolid_id to get activities for
     */
    function getRoleActivities($role_id)
    {
        $this->db->selectDB(DB_NAME);

        $role_id = $this->db->escapeString($role_id);

        $activities = array();
        $locale_tag = getLocaleTag();

        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];

        $sql = "SELECT R.ROLE_ID AS ROLE_ID, RA.ACTIVITY_ID AS ACTIVITY_ID, ".
            "A.METHOD_NAME AS METHOD_NAME, ".
            "T.IDENTIFIER_STRING AS IDENTIFIER_STRING, ".
            "T.TRANSLATION_ID AS TRANSLATION_ID FROM ".
            "ROLE R, ROLE_ACTIVITY RA, ACTIVITY A, TRANSLATION T ".
            "WHERE  R.ROLE_ID = '$role_id'  AND ".
            "R.ROLE_ID = RA.ROLE_ID AND T.TRANSLATION_ID = A.TRANSLATION_ID ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID";

        $result = $this->db->execute($sql);

        $i = 0;
        while($activities[$i] = $this->db->fetchArray($result)) {
            $id = $activities[$i]['TRANSLATION_ID'];

            $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
                "FROM TRANSLATION_LOCALE ".
                "WHERE TRANSLATION_ID=$id AND LOCALE_ID=$locale_id LIMIT 1"; 
                // maybe do left join at some point

            $result_sub =  $this->db->execute($sub_sql);
            $translate = $this->db->fetchArray($result_sub);

            if($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            } else {
                $activities[$i]['ACTIVITY_NAME'] = 
                    $activities['IDENTIFIER_STRING'];
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null

        return $activities;

    }


    /**
     *  Get a list of all roles. Role names are not localized since these are
     *  created by end user admins of the search engine
     *
     *  @return array an array of role_id, role_name pairs 
     */
    function getRoleList()
    {
        $this->db->selectDB(DB_NAME);

        $roles = array();

        $sql = "SELECT R.ROLE_ID AS ROLE_ID, R.NAME AS ROLE_NAME ".
            " FROM ROLE R"; 

        $result = $this->db->execute($sql);
        $i = 0;

        while($roles[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($roles[$i]); //last one will be null
        

        return $roles;

    }


    /**
     * Get role id associated with rolename (so rolenames better be unique)
     *
     * @param string $rolename to use to look up a role_id
     * @return string  role_id corresponding to the rolename.
     */
    function getRoleId($rolename)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT R.ROLE_ID AS ROLE_ID FROM ".
            " ROLE R WHERE R.NAME = '".$this->db->escapeString($rolename)."' "; 
        $result = $this->db->execute($sql);
        if(!$row = $this->db->fetchArray($result) ) {
            return -1;
        }

        return $row['ROLE_ID'];
    }


    /**
     *  Add a rolename to the database using provided string
     *
     *  @param string $rolename  the rolename to be added
     */
    function addRole($rolename)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO ROLE (NAME) VALUES ('".
            $this->db->escapeString($rolename)."')";

        $this->db->execute($sql);
    }


    /**
     *  Add an allowed activity to an existing role
     *
     *  @param string $roleid  the role id of the role to add the activity to
     *  @param string $activityid the id of the acitivity to add
     */
    function addActivityRole($roleid, $activityid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO ROLE_ACTIVITY VALUES ('".
            $this->db->escapeString($roleid)."', '".
            $this->db->escapeString($activityid)."')";

        $this->db->execute($sql);
    }


    /**
     *  Delete a role by its roleid
     *
     *  @param string $roleid - the roleid of the role to delete 
     */
    function deleteRole($roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM ROLE_ACTIVITY WHERE ROLE_ID='$roleid'";

        $this->db->execute($sql);
        $sql = "DELETE FROM ROLE WHERE ROLE_ID='".
            $this->db->escapeString($roleid)."'";
        $this->db->execute($sql);
    }


    /**
     *  Remove an allowed activity from a role
     *
     *  @param string $roleid  the roleid of the role to be modified
     *  @param string $activityid  the activityid of the activity to remove
     */
    function deleteActivityRole($roleid, $activityid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM ROLE_ACTIVITY WHERE ROLE_ID='".
            $this->db->escapeString($roleid)."' AND ACTIVITY_ID='".
            $this->db->escapeString($activityid)."'";

        $this->db->execute($sql);
    }
}
 
 
 
 ?>
