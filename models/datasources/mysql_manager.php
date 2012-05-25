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
 * @subpackage datasource_manager
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Loads base datasource class if necessary
 */
require_once "datasource_manager.php";


/**
 * Mysql DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager 
 * for the MySql DBMS. Method explanations
 * are from the parent class. 

 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage datasource_manager
 */
class MysqlManager extends DatasourceManager
{
    /** Used when to quote column names of db names that contain a 
     *  a keyword or special character
     *  @var string
     */
    var $special_quote = "`";

    /** {@inheritdoc} */
    function __construct() 
    {
        parent::__construct();
    }

    /** {@inheritdoc} */
    function connect($db_host = DB_HOST, $db_user = DB_USER, 
        $db_password = DB_PASSWORD) 
    {
        return mysql_connect($db_host, $db_user, $db_password);
    }

    /** {@inheritdoc} */
    function selectDB($db_name) 
    {
        return mysql_selectDB($db_name);
    }

    /** {@inheritdoc} */
    function disconnect() 
    {
        mysql_close();
    }

    /** {@inheritdoc} */
    function exec($sql) 
    {
        $result = mysql_query($sql);

        return $result;
    }

    /** {@inheritdoc} */
    function affectedRows() 
    {
        return mysql_affected_rows();
    }


    /** {@inheritdoc} */
    function insertID() 
    {
        return mysql_insert_id();
    }

    /** {@inheritdoc} */
    function fetchArray($result) 
    {
        $row = mysql_fetch_array($result, MYSQL_ASSOC);

        return $row;
    }


    /** {@inheritdoc} */
    public function escapeString($str) 
    {
        return mysql_real_escape_string($str);
    }


}

?>
