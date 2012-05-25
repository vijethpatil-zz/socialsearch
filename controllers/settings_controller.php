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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base controller class, if needed
 */
require_once BASE_DIR."/controllers/controller.php";

/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class SettingsController extends Controller
{
    /**
     * Load the SettingsView
     * @var array
     */
    var $views = array("settings");
    /**
     * LocaleModel used to get the available languages/locales, CrawlModel
     * is used to get a list of available crawls
     * @var array
     */
    var $models = array("locale", "crawl", "user", "machine");

    /**
     *  Sets up the available perpage language options.
     *  If handling data sent from a  form, it stores cleaned versions of
     *  the number of results per page and language options into a sesssion
     *
     */
    function processRequest() 
    {
        $data = array();
        $view = "settings";
        $changed_settings_flag = false;

        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR']; 
        }

        $data['YIOOP_TOKEN'] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken('YIOOP_TOKEN', $user);

        $languages = $this->localeModel->getLocaleList();
        foreach($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] = 
                $language['LOCALE_NAME'];
        }

        if($token_okay && isset($_REQUEST['lang']) && 
            in_array($_REQUEST['lang'], array_keys($data['LANGUAGES']))) {
            $_SESSION['l'] = $_REQUEST['lang'];
            setLocaleObject( $_SESSION['l']);
            $changed_settings_flag = true;
        }
        $data['LOCALE_TAG'] = getLocaleTag();

        $n = NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] = 
            array($n => $n, 2*$n => 2*$n, 5*$n=> 5*$n, 10*$n=>10*$n);
        if($token_okay && isset($_REQUEST['perpage']) && 
            in_array($_REQUEST['perpage'], array_keys($data['PER_PAGE']))) {
            $_SESSION['MAX_PAGES_TO_SHOW'] = $_REQUEST['perpage'];
            $changed_settings_flag = true;
        }

        if(isset($_SESSION['MAX_PAGES_TO_SHOW'])){
            $data['PER_PAGE_SELECTED'] = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $data['PER_PAGE_SELECTED'] = NUM_RESULTS_PER_PAGE;
        }

        $machine_urls = $this->machineModel->getQueueServerUrls();

        $crawls = $this->crawlModel->getCrawlList(false, true, $machine_urls,
            true);
        $data['CRAWLS'] = array();
        foreach($crawls as $crawl) {
            $data['CRAWLS'][$crawl['CRAWL_TIME']] = $crawl['DESCRIPTION'].
                " ... ".$crawl['COUNT']." urls";
        }
        $mixes = $this->crawlModel->getMixList();
        foreach($mixes as $mix) {
            $data['CRAWLS'][$mix['MIX_TIMESTAMP']] = $mix['MIX_NAME'].
                " ... ".tl('settings_controller_crawl_mix');
        }
        $crawl_stamps = array_keys($data['CRAWLS']);
        if($token_okay && isset($_REQUEST['index_ts']) &&
            in_array($_REQUEST['index_ts'], $crawl_stamps)) {
            $_SESSION['its'] = $_REQUEST['index_ts'];
            $data['its'] = $_REQUEST['index_ts'];
            $changed_settings_flag = true;
        } else if(isset($_REQUEST['its']) && 
            in_array($_REQUEST['its'],$crawl_stamps)){
            $data['its'] = $_REQUEST['its'];
        }else {
            $data['its'] = $this->crawlModel->getCurrentIndexDatabaseName();
        }
        
        if($changed_settings_flag) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('settings_controller_settings_saved')."</h1>')";
            if($user != $_SERVER['REMOTE_ADDR']) {
                $this->userModel->setUserSession($user, $_SESSION);
            }
        }

        $this->displayView($view, $data);
    }

}

?>
