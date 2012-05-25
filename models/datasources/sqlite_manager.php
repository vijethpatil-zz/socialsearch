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
 * SQLite DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager 
 * for the Sqlite 2.x DBMS . Method explanations
 * are from the parent class. 

 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage datasource_manager
 */
class SqliteManager extends DatasourceManager
{
    /**
     *  Stores the current Sqlite DB resource
     *  @var resource
     */
    var $dbhandle;
    /**
     *  Filename of the Sqlite Database
     *  @var string
     */
    var $dbname;
    /**
     *  Stores the result resource of the last DB exec
     *  @var resource
     */
    var $result;

    /** {@inheritdoc} */
    function __construct() 
    {
        parent::__construct();
        if(!file_exists(CRAWL_DIR."/data")) {
            mkdir(CRAWL_DIR."/data");
            chmod(CRAWL_DIR."/data", 0777);
        }
        $this->result = NULL; //will set later
        $this->dbname = NULL;
        $this->result = NULL;
    }

    /** 
     * For an Sqlite database no connection needs to be made so this 
     * method does nothing
     * {@inheritdoc}
     */
    function connect($db_host = DB_HOST, $db_user = DB_USER, 
        $db_password = DB_PASSWORD) 
    {
        return true;
    }

    /** {@inheritdoc} */
    function selectDB($db_name) 
    {
        if(strcmp($db_name, $this->dbname) == 0) {
            return $this->dbhandle;
        }

        $this->dbname = $db_name;
        $this->dbhandle = sqlite_open(CRAWL_DIR."/data/$db_name.db", 0666);
        return $this->dbhandle;
    }

    /** {@inheritdoc} */
    function disconnect() 
    {
        sqlite_close($this->dbhandle);
    }

    /** {@inheritdoc} */
    function exec($sql) 
    {
        $this->result = sqlite_query($this->dbhandle, $sql);

        return $this->result;
    }

    /** {@inheritdoc} */
    function affectedRows() 
    {
        return sqlite_changes($this->dbhandle);
    }

    /** {@inheritdoc} */
    function insertID() 
    {
        return sqlite_insert_id();
    }

    /** {@inheritdoc} */
    function fetchArray($result) 
    {
        $row = sqlite_fetch_array($result, SQLITE_ASSOC);

        return $row;
    }

    /** {@inheritdoc} */
    function escapeString($str) 
    {
        return sqlite_escape_string($str);
    }


}

?>
