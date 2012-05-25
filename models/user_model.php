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

/** Loads the base class */
require_once BASE_DIR."/models/model.php";

/** Used for the crawlHash function */
require_once BASE_DIR."/lib/utility.php"; 

/**
 * This class is used to handle
 * database statements related to User Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class UserModel extends Model 
{


    /**
     * Just calls the parent class constructor
     */
    function __construct() 
    {
        parent::__construct();
    }


    /**
     *  Get a list of admin activities that a user is allowed to perform.
     *  This includes their name and their associated method.
     *  
     *  @param string $user_id  id of user to get activities fors
     */
    function getUserActivities($user_id)
    {
        $this->db->selectDB(DB_NAME);

        $user_id = $this->db->escapeString($user_id);

        $activities = array();
        $locale_tag = getLocaleTag();

        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];

        $sql = "SELECT UR.ROLE_ID AS ROLE_ID, RA.ACTIVITY_ID AS ACTIVITY_ID, ".
            "T.TRANSLATION_ID AS TRANSLATION_ID, A.METHOD_NAME AS METHOD_NAME,".
            " T.IDENTIFIER_STRING AS IDENTIFIER_STRING FROM ACTIVITY A, ".
            " USER_ROLE UR, ROLE_ACTIVITY RA, TRANSLATION T ".
            "WHERE UR.USER_ID = '$user_id' ".
            "AND UR.ROLE_ID=RA.ROLE_ID AND T.TRANSLATION_ID=A.TRANSLATION_ID ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID"; 

        $result = $this->db->execute($sql);
        $i = 0;
        while($activities[$i] = $this->db->fetchArray($result)) {

            $id = $activities[$i]['TRANSLATION_ID'];

            $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
                "FROM TRANSLATION_LOCALE ".
                "WHERE TRANSLATION_ID=$id AND ".
                "LOCALE_ID=$locale_id LIMIT 1"; 
                // maybe do left join at some point

            $result_sub =  $this->db->execute($sub_sql);
            $translate = $this->db->fetchArray($result_sub);

            if($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            } else {
                $activities[$i]['ACTIVITY_NAME'] = 
                    $activities[$i]['IDENTIFIER_STRING'];
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null

        return $activities;

    }

    /**
     * Returns $_SESSION variable of given user from the last time
     * logged in.
     *
     * @param int $user_id id of user to get session for
     * @return array user's session data
     */
    function getUserSession($user_id)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT SESSION FROM USER_SESSION ".
            "WHERE USER_ID = '$user_id' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        if(isset($row["SESSION"])) {
            return unserialize($row["SESSION"]);
        }
        return NULL;
    }

    /**
     * Stores into DB the $session associative array of given user
     *
     * @param int $user_id id of user to store session for
     * @param array $session session data for the given user
     */
    function setUserSession($user_id, $session)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "DELETE FROM USER_SESSION ".
            "WHERE USER_ID = '$user_id'";
        $this->db->execute($sql);
        $session_string = serialize($session);
        $sql = "INSERT INTO USER_SESSION ".
            "VALUES ('$user_id', '$session_string')";
        $this->db->execute($sql);
    }

    /**
     * Gets all the roles associated with a user id
     *
     * @param string $user_id  the user_id to get roles of
     * @return array of role_ids and their names
     */
    function getUserRoles($user_id)
    {
        $this->db->selectDB(DB_NAME);

        $user_id = $this->db->escapeString($user_id);

        $roles = array();
        $locale_tag = getLocaleTag();

        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        
        
        $sql = "SELECT UR.ROLE_ID AS ROLE_ID, R.NAME AS ROLE_NAME ".
            " FROM  USER_ROLE UR, ROLE R WHERE UR.USER_ID = '$user_id' ".
            " AND R.ROLE_ID = UR.ROLE_ID";

        $result = $this->db->execute($sql);
        $i = 0;
        while($roles[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($roles[$i]); //last one will be null
        
        return $roles;

    }


    /**
     *  Returns an array of all user_names
     *
     *  @return array a list of usernames
     */
    function getUserList()
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT USER_NAME FROM USER ORDER BY USER_NAME ASC"; 
        $result = $this->db->execute($sql);
        $usernames = array();
        while($row = $this->db->fetchArray($result)) {
            $usernames[] = $row['USER_NAME'];
        }
        return $usernames;
    }


    /**
     * Add a user with a given username and password to the list of users 
     * that can login to the admin panel
     *
     * @param string $username  the username of the user to be added
     * @param string $password  the password of the user to be added
     */
    function addUser($username, $password)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO USER(USER_NAME, PASSWORD) VALUES ('".
            $this->db->escapeString($username)."', '".
            crawlCrypt($this->db->escapeString($password))."' ) ";
        $result = $this->db->execute($sql);
    }


    /**
     * Deletes a user by username from the list of users that can login to 
     * the admin panel
     *
     * @param string $username  the login name of the user to delete
     */
    function deleteUser($username)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM USER WHERE USER_NAME='".
            $this->db->escapeString($username)."'";
        $result = $this->db->execute($sql);
    }


    /**
     * Adds a role to a given user
     *
     * @param string $userid  the id of the user to add the role to
     * @param string $roleid  the id of the role to add
     */
    function addUserRole($userid, $roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO USER_ROLE  VALUES ('".
            $this->db->escapeString($userid)."', '".
            $this->db->escapeString($roleid)."' ) ";
        $result = $this->db->execute($sql);
    }


    /**
     * Deletes a role from a given user
     *
     * @param string $userid  the id of the user to delete the role from
     * @param string $roleid  the id of the role to delete
     */
    function deleteUserRole($userid, $roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM USER_ROLE WHERE USER_ID='".
            $this->db->escapeString($userid)."' AND  ROLE_ID='".
            $this->db->escapeString($roleid)."'";
        $result = $this->db->execute($sql);
    }
}

 ?>
