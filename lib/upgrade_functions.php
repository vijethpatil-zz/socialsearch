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
 * This file contains global functions connected to upgrading the database
 * and locales between different versions of Yioop!
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

 /**
 * Checks to see if the locale data of Yioop! in the work dir is older than the
 * currently running Yioop!
 */
function upgradeLocaleCheck()
{
    global $locale_tag;
    $config_name = LOCALE_DIR."/$locale_tag/";
    $fallback_config_name = 
        FALLBACK_LOCALE_DIR."/$locale_tag/";
    if(filemtime($fallback_config_name) > filemtime($config_name)) {
        return "locale";
    }
    return false;
}

/**
 * If the locale data of Yioop! in the work directory is older than the
 * currently running Yioop! then this function is called to at least
 * try to copy the new strings into the old profile.
 */
function upgradeLocale()
{
    global $locale;
    $locale = new LocaleModel();
    $locale->extractMergeLocales();
}

/**
 * Checks to see if the database data or work_dir folder of Yioop! is from an 
 * older version of Yioop! than the currently running Yioop!
 */
function upgradeDatabaseWorkDirectoryCheck()
{
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";

    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if($row['ID'] == 7) {
            return false;
        }
    }
    return true;
}

/**
 * If the database data of Yioop!  is older than the version of the
 * currently running Yioop! then this function is called to try
 * upgrade the database to the new version
 */
function upgradeDatabaseWorkDirectory()
{
    $versions = array(0, 1, 2, 3, 4, 5, 6, 7);
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";
    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if(isset($row['ID']) && in_array($row['ID'], $versions)) {
            $current_version = $row['ID'];
        } else {
            $current_version = 0;
        }
    } else {
        $current_version = 0;
    }
    $key = array_search($current_version, $versions);
    $versions = array_slice($versions, $current_version + 1);
    foreach($versions as $version) {
        $upgradeDB = "upgradeDatabaseVersion$version";
        $upgradeDB($model->db);
    }
}

/**
 * Upgrades a Version 0 version of the Yioop! database to a Version 1 version
 * @param object $db datasource to use to upgrade 
 */
function upgradeDatabaseVersion1(&$db)
{
    $db->execute("CREATE TABLE VERSION (ID INTEGER PRIMARY KEY)");
    $db->execute("INSERT INTO VERSION VALUES (1)");
    $db->execute("CREATE TABLE USER_SESSION( USER_ID INTEGER PRIMARY KEY, ".
        "SESSION VARCHAR(4096))");
}

/**
 * Upgrades a Version 1 version of the Yioop! database to a Version 2 version
 * @param object $db datasource to use to upgrade 
 */
function upgradeDatabaseVersion2(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID=1");
    $db->execute("INSERT INTO VERSION VALUES (2)");
    $db->execute("ALTER TABLE USER ADD UNIQUE ( USER_NAME )" );
    $db->execute("INSERT INTO LOCALE VALUES (17, 'kn', 'ಕನ್ನಡ', 'lr-tb')");
    $db->execute("INSERT INTO LOCALE VALUES (18, 'hi', 'हिन्दी', 'lr-tb')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 5, 
        'Modifier les rôles')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 5, 
        'Modifier les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 5, 
        'Mélanger les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5, 
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5, 
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5, 
        'Configurer')");

}

/**
 * Upgrades a Version 2 version of the Yioop! database to a Version 3 version
 * @param object $db datasource to use to upgrade 
 */
function upgradeDatabaseVersion3(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID=2");
    $db->execute("INSERT INTO VERSION VALUES (3)");
    $db->execute("INSERT INTO LOCALE VALUES (19, 'tr', 'Türkçe', 'lr-tb')");

    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 10)");

    $db->execute("CREATE TABLE MACHINE (
        NAME VARCHAR(16) PRIMARY KEY, URL VARCHAR(256) UNIQUE,
        HAS_QUEUE_SERVER BOOLEAN, NUM_FETCHERS INT(4))");

    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID>5 AND ACTIVITY_ID<11");
    $db->execute(
        "DELETE FROM TRANSLATION WHERE TRANSLATION_ID>5 AND TRANSLATION_ID<11");
    $db->execute("DELETE FROM TRANSLATION_LOCALE ".
        "WHERE TRANSLATION_ID>5 AND TRANSLATION_ID<11");

    $db->execute("INSERT INTO ACTIVITY VALUES (6, 6, 'pageOptions')");
    $db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'searchFilters')");
    $db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'manageMachines')");
    $db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageLocales')");
    $db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'configure')");

    $db->execute(
        "INSERT INTO TRANSLATION VALUES (6, 'db_activity_file_options')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (7,'db_activity_search_filters')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES(8,'db_activity_manage_machines')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (9,'db_activity_manage_locales')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (10, 'db_activity_configure')");

    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (6, 1, 'Page Options')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Search Filters')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (8, 1, 'Manage Machines')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (9, 1, 'Manage Locales')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (10, 1, 'Configure')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5, 
        'Options de fichier')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5, 
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5, 
        'Modifier les ordinateurs')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5, 
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 5, 
        'Configurer')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 9, 'ローケル管理')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 9, '設定')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 10, '로케일 관리')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 10, '구성')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 15, 
        'Quản lý miền địa phương')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 15, 
        'Sắp xếp hoạt động dựa theo hoạch định')");

}
/**
 * Upgrades a Version 3 version of the Yioop! database to a Version 4 version
 * @param object $db datasource to use to upgrade 
 */
function upgradeDatabaseVersion4(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID=3");
    $db->execute("INSERT INTO VERSION VALUES (4)");
    $db->execute("ALTER TABLE MACHINE ADD COLUMN PARENT VARCHAR(16)");
}

/**
 * Upgrades a Version 4 version of the Yioop! database to a Version 5 version
 * @param object $db datasource to use to upgrade 
 */
function upgradeDatabaseVersion5(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID=4");
    $db->execute("INSERT INTO VERSION VALUES (5)");

    $static_page_path = LOCALE_DIR."/".DEFAULT_LOCALE."/pages";
    if(!file_exists($static_page_path)) {
        mkdir($static_page_path);
    }
    $default_bot_txt_path = "$static_page_path/bot.thtml";
    $old_bot_txt_path = WORK_DIRECTORY."/bot.txt";
    if(file_exists($old_bot_txt_path) && !file_exists($default_bot_txt_path)){
        rename($old_bot_txt_path, $default_bot_txt_path);
    }
    $db->setWorldPermissionsRecursive($static_page_path);
}

/**
 * Upgrades a Version 5 version of the Yioop! database to a Version 6 version
 * @param object $db datasource to use to upgrade 
 */
function upgradeDatabaseVersion6(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID=5");
    $db->execute("INSERT INTO VERSION VALUES (6)");

    if(!file_exists(PREP_DIR)) {
        mkdir(PREP_DIR);
    }
    $db->setWorldPermissionsRecursive(PREP_DIR);
}

function upgradeDatabaseVersion7(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID=6");
    $db->execute("INSERT INTO VERSION VALUES (7)");
    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID=7");
    $db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'resultsEditor')");
    $db->execute("DELETE FROM TRANSLATION WHERE TRANSLATION_ID=7");
    $db->execute("DELETE FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID=7");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (7,'db_activity_results_editor')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Results Editor')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5, 
        'Éditeur de résultats')");
}
?>
