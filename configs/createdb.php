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
 * This script can be used to set up the database and filesystem for the
 * seekquarry database system. The SeekQuarry system is deployed with a
 * minimal sqlite database so this script is not strictly needed.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage configs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

/** Calculate base directory of script 
 * @ignore 
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/configs")));
require_once BASE_DIR.'/configs/config.php';
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php"; 
    //get the database library
require_once BASE_DIR."/lib/utility.php"; //for crawlHash function


$db_class = ucfirst(DBMS)."Manager";
$db = new $db_class();
$db->connect();

$auto_increment = "AUTOINCREMENT";
if(in_array(DBMS, array("mysql"))) {
    $auto_increment = "AUTO_INCREMENT";
}
if(in_array(DBMS, array("sqlite"))) {
    $auto_increment = ""; 
    /* in sqlite2 a primary key column will act 
       as auto_increment if don't give value
     */
}
if(!in_array(DBMS, array('sqlite', 'sqlite3'))) {
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
}
$db->selectDB(DB_NAME);

$db->execute("CREATE TABLE VERSION (ID INTEGER PRIMARY KEY)");
$db->execute("INSERT INTO VERSION VALUES (7)");

$db->execute("CREATE TABLE USER (USER_ID INTEGER PRIMARY KEY $auto_increment, ".
    "USER_NAME VARCHAR(16) UNIQUE,  PASSWORD VARCHAR(16))");

$db->execute("CREATE TABLE USER_SESSION( USER_ID INTEGER PRIMARY KEY, ".
    "SESSION VARCHAR(4096))");
    
$db->execute("CREATE TABLE USER_FEED ( USER_ID INTEGER, FEED_ID UNSIGNED BIG INT, FEED_TYPE VARCHAR(25), PRIMARY KEY (USER_ID, FEED_ID))");

$db->execute("CREATE TABLE USER_KEYS ( USER_ID INTEGER, FEED_TYPE VARCHAR(25), CONSUMER_KEY VARCHAR(100), CONSUMER_SECRET VARCHAR(100), USER_KEY VARCHAR(100), USER_SECRET VARCHAR(100), USER_SCREENNAME VARCHAR(100), CURRENT_SINCEID BIG INT, PRIMARY KEY (USER_ID, FEED_TYPE))");

$db->execute("CREATE TABLE FEED ( FEED_ID UNSIGNED BIG INT PRIMARY KEY, FEEDER VARCHAR(100), FEEDTEXT VARCHAR(500), REFEEDCOUNT INT, FEEDTIME VARCHAR(100), FEEDSOURCE VARCHAR(250), FEEDERPIC VARCHAR(500), FOLLOWERS_COUNT INT, FRIENDS_COUNT INT, VERIFIED INT)");    

$db->execute("CREATE TABLE RSSFEED ( FEEDURL_ID INTEGER PRIMARY KEY, FEEDURL TEXT)");

$db->execute("CREATE TABLE USER_RSSFEED ( USER_ID INTEGER, FEEDURL_ID INTEGER, PRIMARY KEY(USER_ID, FEEDURL_ID))");

//default account is root without a password
$sql ="INSERT INTO USER VALUES (1, 'root', '".crawlCrypt('')."' ) ";
$db->execute($sql);

$db->execute("CREATE TABLE TRANSLATION (TRANSLATION_ID INTEGER PRIMARY KEY ".
    "$auto_increment, IDENTIFIER_STRING VARCHAR(512) UNIQUE)");

$db->execute("CREATE TABLE LOCALE (LOCALE_ID INTEGER PRIMARY KEY ".
    "$auto_increment, LOCALE_TAG VARCHAR(16), LOCALE_NAME VARCHAR(256),".
    " WRITING_MODE CHAR(5))");
$db->execute("CREATE TABLE TRANSLATION_LOCALE (TRANSLATION_ID INTEGER, ".
    "LOCALE_ID INTEGER, TRANSLATION VARCHAR(4096) )");
/* we insert 1 by 1 rather than comma separate as sqlite 
   does not support comma separated inserts
 */
$db->execute("INSERT INTO LOCALE VALUES (1, 'en-US', 'English', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (2, 'ar', 'العربية', 'rl-tb')");
$db->execute("INSERT INTO LOCALE VALUES (3, 'de', 'Deutsch', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (4, 'es', 'Español', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (5, 'fr-FR', 'Français', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (6, 'he', 'עברית', 'rl-tb')");
$db->execute("INSERT INTO LOCALE VALUES (7, 'in-ID', 'Bahasa', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (8, 'it', 'Italiano', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (9, 'ja', '日本語', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (10, 'ko', '한국어', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (11, 'pl', 'Polski', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (12, 'pt', 'Português', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (13, 'ru', 'Русский', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (14, 'th', 'ไทย', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (15, 'vi-VN', 'Tiếng Việt', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (16, 'zh-CN', '中文', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (17, 'kn', 'ಕನ್ನಡ', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (18, 'hi', 'हिन्दी', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (19, 'tr', 'Türkçe', 'lr-tb')");

$db->execute("CREATE TABLE ROLE (ROLE_ID INTEGER PRIMARY KEY ".
    "$auto_increment, NAME VARCHAR(512))");
$sql ="INSERT INTO ROLE VALUES (1, 'Admin' ) ";
$db->execute($sql);

$db->execute("CREATE TABLE ROLE_ACTIVITY (ROLE_ID INTEGER,ACTIVITY_ID INTEGER)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 1)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 2)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 3)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 4)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 5)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 6)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 7)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 8)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 9)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 10)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 11)");

$db->execute(
    "CREATE TABLE ACTIVITY (ACTIVITY_ID INTEGER PRIMARY KEY $auto_increment,".
    " TRANSLATION_ID INTEGER, METHOD_NAME VARCHAR(256))");
$db->execute("INSERT INTO ACTIVITY VALUES (1, 1, 'manageAccount')");
$db->execute("INSERT INTO ACTIVITY VALUES (2, 2, 'manageUsers')");
$db->execute("INSERT INTO ACTIVITY VALUES (3, 3, 'manageRoles')");
$db->execute("INSERT INTO ACTIVITY VALUES (4, 4, 'manageCrawls')");
$db->execute("INSERT INTO ACTIVITY VALUES (5, 5, 'mixCrawls')");
$db->execute("INSERT INTO ACTIVITY VALUES (6, 6, 'pageOptions')");
$db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'resultsEditor')");
$db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'manageMachines')");
$db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageLocales')");
$db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'manageFeeds')");
$db->execute("INSERT INTO ACTIVITY VALUES (11, 11, 'configure')");

$db->execute("INSERT INTO TRANSLATION VALUES (1,'db_activity_manage_account')");
$db->execute("INSERT INTO TRANSLATION VALUES (2, 'db_activity_manage_users')");
$db->execute("INSERT INTO TRANSLATION VALUES (3, 'db_activity_manage_roles')");
$db->execute("INSERT INTO TRANSLATION VALUES (4, 'db_activity_manage_crawl')");
$db->execute("INSERT INTO TRANSLATION VALUES (5, 'db_activity_mix_crawls')");
$db->execute("INSERT INTO TRANSLATION VALUES (6, 'db_activity_file_options')");
$db->execute("INSERT INTO TRANSLATION VALUES (7,'db_activity_results_editor')");
$db->execute("INSERT INTO TRANSLATION VALUES(8,'db_activity_manage_machines')");
$db->execute("INSERT INTO TRANSLATION VALUES (9,'db_activity_manage_locales')");
$db->execute("INSERT INTO TRANSLATION VALUES (10, 'db_activity_manage_feeds')");
$db->execute("INSERT INTO TRANSLATION VALUES (11, 'db_activity_configure')");


$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 1, 'Manage Account' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 1, 'Manage Users')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 1, 'Manage Roles')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 1, 'Manage Crawls')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 1, 'Mix Crawls')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 1, 'Page Options')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Results Editor')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 1, 'Manage Machines')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 1, 'Manage Locales')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 1, 'Manage Feeds')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 1, 'Configure')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 5, 
    'Modifier votre compte' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 5, 
    'Modifier les utilisateurs')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 5, 
    'Modifier les rôles')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 5, 
    'Modifier les indexes')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 5, 
    'Mélanger les indexes')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5, 
    'Options de fichier')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5, 
    'Éditeur de résultats')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5, 
    'Modifier les ordinateurs')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5, 
    'Modifier les lieux')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 5, 
    'Configurer')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 9, 'アカウント管理' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 9, 'ユーザー管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 9, '役割管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 9, '検索管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 9, 'ローケル管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 9, '設定')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 10, '사용자 계정 관리' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 10, '사용자 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 10, '사용자 권한 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 10, '크롤 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 10, '로케일 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 10, '구성')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 15, 
    'Quản lý tài khoản' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 15, 
    'Quản lý tên sử dụng')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 15, 
    'Quản lý chức vụ')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 15, 'Quản lý sự bò')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 15, 
    'Quản lý miền địa phương')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 15, 
    'Sắp xếp hoạt động dựa theo hoạch định')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 16, 
    '管理帳號')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 16, 
    '管理使用者')");


$db->execute("CREATE TABLE USER_ROLE (USER_ID INTEGER, ROLE_ID INTEGER)");
$sql ="INSERT INTO USER_ROLE VALUES (1, 1)";
$db->execute($sql);

$db->execute("CREATE TABLE CURRENT_WEB_INDEX (CRAWL_TIME INT(11) )");

$db->execute("CREATE TABLE CRAWL_MIXES (
    MIX_TIMESTAMP INT(11) PRIMARY KEY, MIX_NAME VARCHAR(16) UNIQUE)");

$db->execute("CREATE TABLE MIX_GROUPS (
    MIX_TIMESTAMP INT(11), GROUP_ID INT(4), RESULT_BOUND INT(4))");

$db->execute("CREATE TABLE MIX_COMPONENTS (
    MIX_TIMESTAMP INT(11), GROUP_ID INT(4), CRAWL_TIMESTAMP INT(11),
    WEIGHT REAL, KEYWORDS VARCHAR(256))");

$db->execute("CREATE TABLE MACHINE (
    NAME VARCHAR(16) PRIMARY KEY, URL VARCHAR(256) UNIQUE,
    HAS_QUEUE_SERVER INT, NUM_FETCHERS INT(4), PARENT VARCHAR(16) )");

$db->disconnect();
if(in_array(DBMS, array('sqlite','sqlite3' ))){
    chmod(CRAWL_DIR."/data/".DB_NAME.".db", 0666);
}
echo "Create DB succeeded\n";


?>
