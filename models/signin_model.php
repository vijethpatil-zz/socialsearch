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

/** For the base model class */
require_once BASE_DIR."/models/model.php";
/** For the crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/**
 * This is class is used to handle
 * db results needed for a user to login
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class SigninModel extends Model 
{


    /**
     * {@inheritdoc}
     */
    function __construct() 
    {
        parent::__construct();
    }


    /**
     * Checks that a username password pair is valid
     *
     * @param string $username the username to check
     * @param string $password the password to check
     * @return bool  where the password is that of the given user 
     *      (or at least hashes to the same thing) 
     */
    function checkValidSignin($username, $password)
    {
        $this->db->selectDB(DB_NAME);

        $username = $this->db->escapeString($username);
        $password = $this->db->escapeString($password);

        $sql = "SELECT USER_NAME, PASSWORD FROM USER ".
            "WHERE USER_NAME = '$username' LIMIT 1";

        $result = $this->db->execute($sql);
        if(!$result) {
            return false;
        }
        $row = $this->db->fetchArray($result);

        return ($username == $row['USER_NAME'] && 
            crawlCrypt($password, $row['PASSWORD']) == $row['PASSWORD']) ;

    }


    /**
     *  Get the user_id associated with a given username
     *
     *  @param string $username the username to look up
     *  @return string the corresponding userid
     */
    function getUserId($username)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT USER_ID FROM USER WHERE USER_NAME = '$username' LIMIT 1";
        $result = $this->db->execute($sql);
        if(!$result) return false;
        $row = $this->db->fetchArray($result);
        $user_id = $row['USER_ID'];
        return $user_id;
    }


    /**
     *  Get the user_name associated with a given userid
     *
     *  @param string $user_id the userid to look up
     *  @return string the corresponding username
     */
   function getUserName($user_id)
   {
        $this->db->selectDB(DB_NAME);

        $user_id = $this->db->escapeString($user_id);

        $sql = "SELECT USER_NAME FROM USER WHERE USER_ID = '$user_id' LIMIT 1"; 
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $username = $row['USER_NAME'];
        return $username;
   }


    /**
     *  Changes the password of a given user
     *
     *  @param string $username username of user to change password of
     *  @param string $password new password for user
     *  @return boob update successful or not.
     */
    function changePassword($username, $password)
    {
        $this->db->selectDB(DB_NAME);

        $username = $this->db->escapeString($username);
        $password = $this->db->escapeString($password);

        $sql = "UPDATE USER SET PASSWORD='".
            crawlCrypt($password)."' WHERE USER_NAME = '$username' "; 

        $result = $this->db->execute($sql);
        return $result != false;
    }

}

?>
