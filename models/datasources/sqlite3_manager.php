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
 * SQLite3 DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager 
 * for the Sqlite3 DBMS (file format not compatible with versions less than 3). 
 * Method explanations are from the parent class. 
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage datasource_manager
 */
class Sqlite3Manager extends DatasourceManager
{
    /**
     *  Stores  the current Sqlite3 DB object
     *  @var object
     */
    var $dbhandle;
    /**
     *  Filename of the Sqlite3 Database
     *  @var string
     */
    var $dbname;

    /**
     *  Sqlite3 whether access to DB is through PDO object or SQLite3 object
     *  @var bool
     */
    var $pdo_flag;

    /** {@inheritdoc} */
    function __construct() 
    {
        parent::__construct();
        if(!file_exists(CRAWL_DIR."/data")) {
            mkdir(CRAWL_DIR."/data");
            chmod(CRAWL_DIR."/data", 0777);
        }
        if(class_exists("SQLite3")) {
            $this->pdo_flag = false;
        } else if (class_exists("PDO") && 
            in_array("sqlite", PDO::getAvailableDrivers())) {
            $this->pdo_flag = true;
        } else {
            echo "SQLite3 needs to be installed!";
            $this->pdo_flag = false;
        }
        $this->dbname = NULL;
    }

    /** 
     * For an Sqlite3 database no connection needs to be made so this 
     * method does nothing
     * {@inheritdoc}
     */
    function connect($db_HOST = DB_HOST, $db_user = DB_USER, 
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
        if(!$this->pdo_flag) {
            $this->dbhandle = new SQLite3(CRAWL_DIR."/data/$db_name.db", 
                SQLITE3_OPEN_READWRITE |SQLITE3_OPEN_CREATE);
        } else {
            $this->dbhandle = new PDO("sqlite:".
                CRAWL_DIR."/data/$db_name.db");
        }
        return $this->dbhandle;
    }

    /** {@inheritdoc} */
    function disconnect() 
    {
        if(!$this->pdo_flag) {
            $this->dbhandle->close();
        }
    }

    /** {@inheritdoc} */
    function exec($sql) 
    {
        $result = $this->dbhandle->query($sql);

        return $result;
    }

    /** {@inheritdoc} */
    function affectedRows() 
    {
        if(method_exists($this->dbhandle, "changes")) {
            return $this->dbhandle->changes();
        } else {
            echo "Affected rows not supported in PDO!";
        }
    }

    /** {@inheritdoc} */
    function insertID() 
    {
        return $this->dbhandle->lastInsertRowID();
    }

    /** {@inheritdoc} */
    function fetchArray($result) 
    {
        if(!$this->pdo_flag) {
            $row = $result->fetchArray(SQLITE3_ASSOC);
        } else {
            $row = $result->fetch(PDO::FETCH_ASSOC);
        }
        return $row;
    }

    /** {@inheritdoc} */
    function escapeString($str) 
    {
        if(method_exists($this->dbhandle, "escapeString")) {
            return $this->dbhandle->escapeString($str);
        } else {
            return addslashes($str);
        }
    }


}

?>
