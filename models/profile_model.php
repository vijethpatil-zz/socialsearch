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

/** 
 * For getPath
 */
require_once(BASE_DIR.'/lib/url_parser.php');

/**
 * This is class is used to handle
 * getting and saving the profile.php of the current search engine instance
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class ProfileModel extends Model
{
    var $profile_fields = array('USER_AGENT_SHORT', 
            'DEFAULT_LOCALE', 'DEBUG_LEVEL', 'DBMS','DB_HOST', 
            'DB_NAME', 'DB_USER', 'DB_PASSWORD', 
            'NAME_SERVER', 'AUTH_KEY', "ROBOT_DESCRIPTION", 'WEB_URI',
            'USE_MEMCACHE', 'MEMCACHE_SERVERS', 'USE_FILECACHE', 
            'WORD_SUGGEST', 'CACHE_LINK', 'SIMILAR_LINK', 
            'IN_LINK', 'IP_LINK', 'SIGNIN_LINK',
            'ROBOT_INSTANCE', "WEB_ACCESS", "RSS_ACCESS", "API_ACCESS",
            'TITLE_WEIGHT','DESCRIPTION_WEIGHT','LINK_WEIGHT',
            'MIN_RESULTS_TO_GROUP','SERVER_ALPHA');
    /**
     *  {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
    }

    /**
     * Creates a folder to be used to maintain local information about this 
     * instance of the Yioop/SeekQuarry engin
     *
     * Creates the directory provides as well as subdirectories for crawls, 
     * locales, logging, and sqlite DBs.
     *
     *  @param string $directory parth and name of directory to create
     */
    function makeWorkDirectory($directory)
    {

        $to_make_dirs = array($directory, "$directory/locale",
            "$directory/cache", "$directory/schedules", 
            "$directory/log", "$directory/data", "$directory/app",
            "$directory/prepare");
        $dir_status = array();
        foreach($to_make_dirs as $dir) {
            $dir_status[$dir] = $this->createIfNecessaryDirectory($dir);
            if( $dir_status[$dir] < 0) {
                return false;
            }
        }
        if($dir_status["$directory/locale"] == 1) {
            $this->db->copyRecursive(BASE_DIR."/locale", "$directory/locale");
        }
        if($dir_status["$directory/data"] == 1) {
            $this->db->copyRecursive(BASE_DIR."/data", "$directory/data");
        }
        return true;
    }

    /**
     * Outputs a profile.php  file in the given directory containing profile 
     * data based on new and old data sources
     *
     * This function creates a profile.php file if it doesn't exist. A given 
     * field is output in the profile
     * according to the precedence that a new value is preferred to an old 
     * value is prefered to the value that comes from a currently defined 
     * constant. It might be the case that a new value for a given field 
     * doesn't exist, etc.
     *
     * @param string $directory the work directory to output the profile.php 
     *      file
     * @param array $new_profile_data fields and values containing at least 
     *      some profile information (only $this->profile_fields
     * fields of $new_profile_data will be considered).
     * @param array $old_profile_data fields and values that come from 
     *      presumably a previously existing profile
     */
    function updateProfile($directory, $new_profile_data, $old_profile_data)
    {
        $n = array();
        $n[] = <<<EOT
<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009-2012  Chris Pollett chris@pollett.org
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
 * Computer generated file giving the key defines of directory locations
 * as well as database settings used to run the SeekQuarry/Yioop search engine
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage config
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009-2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
EOT;

        foreach($this->profile_fields as $field) {
            if(isset($new_profile_data[$field])) {
                $profile[$field] = $new_profile_data[$field];
            } else if(isset($old_profile_data[$field])) {
                $profile[$field] = $old_profile_data[$field];
            } else if(defined($field)) {
                    $profile[$field] = constant($field);
            } else {
                    $profile[$field] = "";
            }
            if($field == "WEB_URI") {
                $profile[$field] = UrlParser::getPath($_SERVER['REQUEST_URI']);
            }
            if($field == "ROBOT_DESCRIPTION") continue;
            if($field != "DEBUG_LEVEL") {
                $profile[$field] = "\"{$profile[$field]}\"";
            }
            $n[] = "define('$field', {$profile[$field]});";
        }
        $out = implode("\n", $n);
        if(file_put_contents("$directory/profile.php", $out) !== false) {
            @chmod("$directory/profile.php", 0777);
            if(isset($new_profile_data['ROBOT_DESCRIPTION'])) {
                $robot_path = LOCALE_DIR."/".DEFAULT_LOCALE."/pages/bot.thtml";
                file_put_contents($robot_path, 
                    $new_profile_data['ROBOT_DESCRIPTION']);
                @chmod("$directory/bot.txt", 0777);
            }
            return true;
        }

        return false;
    }

    /**
     * Creates a  directory and sets it to world prermission if it doesn't 
     * aleady exist
     *
     * @param string $directory name of directory to create
     * @return int -1 on failure, 0 if already existed, 1 if created
     */
    function createIfNecessaryDirectory($directory)
    {
        if(file_exists($directory)) return 0;
        else {
            @mkdir($directory);
            @chmod($directory, 0777);
        }
        if(file_exists($directory)) {
            return 1;
        }
        return -1;
    }

    /**
     * Check if $dbinfo provided the connection details for a Yioop/SeekQuarry
     * database. If it does provide a valid db connection but no data then try 
     * to recreate the database from the default copy stored in /data dir.
     *
     * @param array $dbinfo has fields for DBMS, DB_USER, DB_PASSWORD, DB_HOST
     *      and DB_NAME
     * @return bool returns true if can connect to/create a valid database;
     *      returns false otherwise
     */
    function migrateDatabaseIfNecessary($dbinfo)
    {
        $test_dbm = $this->testDatabaseManager($dbinfo);
        if($test_dbm === false || $test_dbm === true) {return $test_dbm;}

        $auto_increment = "AUTOINCREMENT";
        if(in_array($dbinfo['DBMS'], array("mysql"))) {
            $auto_increment = "AUTO_INCREMENT";
        }
        if(in_array($dbinfo['DBMS'], array("sqlite"))) {
            $auto_increment = ""; 
                /* in sqlite2 a primary key column will act 
                   as auto_increment if don't give value
                 */
        }

        $tables = array("VERSION", "USER", "USER_SESSION", "TRANSLATION", 
            "LOCALE", "TRANSLATION_LOCALE", "ROLE", 
            "ROLE_ACTIVITY", "ACTIVITY", "USER_ROLE", "CURRENT_WEB_INDEX",
            "CRAWL_MIXES", "MIX_GROUPS", "MIX_COMPONENTS");
        $create_statements = array(
            "CREATE TABLE VERSION( ID INTEGER PRIMARY KEY)",
            "CREATE TABLE USER( USER_ID INTEGER PRIMARY KEY $auto_increment, ".
                "USER_NAME VARCHAR(16) UNIQUE,  PASSWORD VARCHAR(16))",
            "CREATE TABLE USER_SESSION( USER_ID INTEGER PRIMARY KEY, ".
                "SESSION VARCHAR(4096))",
            "CREATE TABLE TRANSLATION (TRANSLATION_ID INTEGER PRIMARY KEY ".
                "$auto_increment, IDENTIFIER_STRING VARCHAR(512) UNIQUE)",
            "CREATE TABLE LOCALE(LOCALE_ID INTEGER PRIMARY KEY ".
                "$auto_increment, LOCALE_TAG VARCHAR(16), ".
                "LOCALE_NAME VARCHAR(256)," .
                "WRITING_MODE CHAR(5))",
            "CREATE TABLE TRANSLATION_LOCALE (TRANSLATION_ID INTEGER, ".
                "LOCALE_ID INTEGER, TRANSLATION VARCHAR(4096) )",
            "CREATE TABLE ROLE (ROLE_ID INTEGER PRIMARY KEY $auto_increment, ".
                "NAME VARCHAR(512))",
            "CREATE TABLE ROLE_ACTIVITY (ROLE_ID INTEGER, ACTIVITY_ID INTEGER)",
            "CREATE TABLE ACTIVITY (ACTIVITY_ID INTEGER PRIMARY KEY ".
                "$auto_increment, TRANSLATION_ID INTEGER, ".
                "METHOD_NAME VARCHAR(256))",
            "CREATE TABLE USER_ROLE (USER_ID INTEGER, ROLE_ID INTEGER)",
            "CREATE TABLE CURRENT_WEB_INDEX (CRAWL_TIME INT(11) )",
            "CREATE TABLE CRAWL_MIXES (MIX_TIMESTAMP INT(11) PRIMARY KEY,".
                " MIX_NAME VARCHAR(16) UNIQUE)",
            "CREATE TABLE MIX_GROUPS (MIX_TIMESTAMP INT(11), GROUP_ID INT(4),".
                " RESULT_BOUND INT(4))",
            "CREATE TABLE MIX_COMPONENTS (MIX_TIMESTAMP INT(11),".
                "GROUP_ID INT(4), CRAWL_TIMESTAMP INT(11), WEIGHT REAL,".
                " KEYWORDS VARCHAR(256))"
            );
        foreach($create_statements as $statement) {
            if(!$test_dbm->execute($statement)) {return false;}
        }

        require_once(BASE_DIR."/models/datasources/sqlite3_manager.php");

        $default_dbm = new Sqlite3Manager();
        $default_dbm->dbhandle = new SQLite3(
            BASE_DIR."/data/default.db", SQLITE3_OPEN_READWRITE); 
            // a little bit hacky
        if(!$default_dbm->dbhandle) {return false;}
        foreach($tables as $table) {
            if(!$this->copyTable($table, $default_dbm, $test_dbm)) 
                {return false;}
        }
        return true;
    }

    /**
     * Checks if $dbinfo provides info to connect to an working instance of 
     * app db.
     *
     * @param array $dbinfo has field for DBMS, DB_USER, DB_PASSWORD, DB_HOST
     *      and DB_NAME
     * @return mixed returns true if can connect to DBMS with username and 
     *      password, can select the given database name and that database
     *      seems to be of Yioop/SeekQuarry type. If the connection works
     *      but database isn't there it attempts to create it. If the 
     *      database is there but no data, then it returns a resource for
     *      the database. Otherwise, it returns false.
     */
    function testDatabaseManager($dbinfo)
    {
        if(!isset($dbinfo['DBMS'])) {return false;}

        // check if can establish a connect to dbms
        require_once(
            BASE_DIR."/models/datasources/".$dbinfo['DBMS']."_manager.php");
        $dbms_manager = ucfirst($dbinfo['DBMS'])."Manager";
        $test_dbm = new $dbms_manager();
        if(isset($dbinfo['DB_HOST'])) {
            if(isset($dbinfo['DB_USER'])) {
                if(isset($dbinfo['DB_PASSWORD'])) {
                    $conn = @$test_dbm->connect(
                        $dbinfo['DB_HOST'], 
                        $dbinfo['DB_USER'], $dbinfo['DB_PASSWORD']);
                } else {
                    $conn = @$test_dbm->connect(
                        $dbinfo['DB_HOST'], $dbinfo['DB_USER']);
                }
            } else {
                $conn = @$test_dbm->connect($dbinfo['DB_HOST']);
            }
        } else {
            $conn = @$test_dbm->connect();
        }
        if($conn === false) {return false;}

        //check if can select db or if not create it
        if(!$test_dbm->selectDB($dbinfo['DB_NAME'])) {
            $q = "";
            if(isset($test_dbm->special_quote)) {
                $q = $test_dbm->special_quote;
            }
            @$test_dbm->execute("CREATE DATABASE $q".$dbinfo['DB_NAME']."$q");
            if(!$test_dbm->selectDB($dbinfo['DB_NAME'])) {
                return false;
            }
        }

        /* check if need to create db contents. 
           We check if any locale exists as proxy for contents being okay
         */

        $sql = "SELECT LOCALE_ID FROM LOCALE";
        $result = @$test_dbm->execute($sql);
        if($result !== false && $test_dbm->fetchArray($result) !== false) {
            return true;
        }
        
        return $test_dbm;
    }

    /**
     * Copies the contents of table in the first database into the same named
     * table in a second database. It assumes the table exists in both databases
     *
     * @param string $table name of the table to be copied
     * @param resource $from_dbm database resource for the from table
     * @param resource $to_dbm database resource for the to table
     */
    function copyTable($table, $from_dbm, $to_dbm)
    {
        $sql = "SELECT * FROM $table";
        if(($result = $from_dbm->execute($sql)) === false) {return false;}
        while($row = $from_dbm->fetchArray($result))
        {
            $statement = "INSERT INTO $table VALUES (";
            $comma ="";
            foreach($row as $col=> $value) {
                $statement .= $comma." '".$to_dbm->escapeString($value)."'";
                $comma = ",";
            }
            $statement .= ")";
            if(($to_dbm->execute($statement)) === false) {return false;}
        }

        return true;
    }

    /**
     * Modifies the config.php file so the WORK_DIRECTORY define points at 
     * $directory
     *
     * @param string $directory folder that WORK_DIRECTORY should be defined to
     */
    function setWorkDirectoryConfigFile($directory)
    {
        $config = file_get_contents(BASE_DIR."/configs/config.php");
        $start_machine_section = strpos($config,'/*+++ The next block of code');
        if($start_machine_section === false) return false;
        $end_machine_section = strpos($config, '/*++++++*/');
        if($end_machine_section === false) return false;
        $out = substr($config,  0, $start_machine_section);
        $out .= "/*+++ The next block of code is machine edited, change at \n".
            "your own risk, please use configure web page instead +++*/\n";
        $out .= "define('WORK_DIRECTORY', '$directory');\n";
        $out .= substr($config, $end_machine_section);
        if(file_put_contents(BASE_DIR."/configs/config.php", $out)) return true;
        return false;
    }

    /**
     * Reads a profile from a profile.php file in the provided directory
     *
     * @param string $work_directory directory to look for profile in
     * @return array associate array of the profile fields and their values
     */
    function getProfile($work_directory)
    {
        $profile = array();
        $profile_string = @file_get_contents($work_directory."/profile.php");

        foreach($this->profile_fields as $field) {
            if($field != 'ROBOT_DESCRIPTION') {
                $profile[$field] = $this->matchDefine($field, $profile_string);
            }
        }

        $robot_path = LOCALE_DIR."/".DEFAULT_LOCALE."/pages/bot.thtml";

        if(file_exists($robot_path)) {
            $profile['ROBOT_DESCRIPTION'] = 
                file_get_contents($robot_path);
        }

        return $profile;
    }

    /**
     * Finds the first occurrence of define('$defined', something) in $string 
     * and returns something 
     *
     * @param string $defined the constant being defined
     * @param string $string the haystack string to search in
     * @return string matched value of define if exists; empty string otherwise
     */
    function matchDefine($defined, $string)
    {
        preg_match("/define\((?:\"$defined\"|\'$defined\')\,([^\)]*)\)/", 
            $string, $match);
        $match = (isset($match[1])) ? trim($match[1]) : "";
        $len = strlen($match);
        if( $len >=2 && ($match[0] == '"' || $match[0] == "'")) {
            $match = substr($match, 1, strlen($match) - 2);
        }
        return $match;
    }

}
?>
