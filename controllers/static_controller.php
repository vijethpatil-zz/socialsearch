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

/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/controller.php";

/**
 * This controller is  used by the Yioop web site to display static pages. 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class StaticController extends Controller
{
    /**
     * Says which models to load for this controller.
     * @var array
     */
    var $models = array();
    /**
     * Says which views to load for this controller.
     * @var array
     */
    var $views = array("static");
    /**
     * Says which activities (roughly methods invoke from the web) 
     * this controller will respond to
     * @var array
     */
    var $activities = array("show_page");

    /**
     *  This is the main entry point for handling people arriving to the 
     * SeekQuarry site.
     */
    function processRequest() 
    {
        $data = array();
        $view = "static";
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR']; 
        }
        if(isset($_REQUEST['a'])) {
            if(in_array($_REQUEST['a'], $this->activities)) {
                $activity = $_REQUEST['a'];
            } else {
                $activity = "show_page";
            }
        } else {
            $activity = "show_page";
        }
        $data['VIEW'] = $view;
        $data = array_merge($data, $this->$activity());

        $data['YIOOP_TOKEN'] = $this->generateCSRFToken($user);

        $this->displayView($view, $data);

    }


    /**
     * This activity is used to display one of a set of static pages used
     * by the Yioop Web Site
     *
     * @return array $data has which static page to display
     */
    function show_page() 
    {
        $data = array();
        if(isset($_REQUEST['p']) && 
            in_array($_REQUEST['p'], $this->staticView->pages)) {
            $data['page'] = $_REQUEST['p'];
        } else {
            $data['page'] = "blog";
        }
        if((isset($this->staticView->head_objects[$data['page']]['title']))) {
            $data["subtitle"]=" - ".
            $this->staticView->head_objects[$data['page']]['title'];
            $this->staticView->head_objects[$data['page']]['title'] = "Yioop!".
                $data["subtitle"];
        } else {
            $data["subtitle"] = "";
        }
        return $data;
    }
}
?>
