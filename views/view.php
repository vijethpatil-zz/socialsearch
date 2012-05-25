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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Base View Class. A View is used to display
 * the output of controller activity
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
abstract class View
{
    /** 
     * Names of element objects that the view uses to display itself
     * @var array
     */
    var $elements = array();

    /** 
     * Names of helper objects that the view uses to help draw itself
     * @var array
     */
    var $helpers = array();

    /** 
     * Localized static page elements used by this view
     * @var array
     */
    var $pages = array();


    /** The name of the type of layout object that the view is drawn on 
     *  @var string
     */
    var $layout = "";

    /** The reference to the layout object that the view is drawn on 
     *  @var object
     */
    var $layout_object;

    /**
     *  The constructor reads in any Element and Helper subclasses which are
     *  needed to draw the view. It also reads in the Layout subclass on which
     *  the View will be drawn.
     *
     */
    function __construct() 
    {
        //read in and instantiate Element's needed for this View
        require_once BASE_DIR."/views/elements/element.php";
        foreach($this->elements as $element) {
            if(file_exists(
                APP_DIR."/views/elements/".$element."_element.php")) {
                require_once 
                    APP_DIR."/views/elements/".$element."_element.php";
            } else {
                require_once 
                    BASE_DIR."/views/elements/".$element."_element.php";
            }
            $element_name = ucfirst($element)."Element";
            $element_instance_name = lcfirst($element_name);

            $this->$element_instance_name = new $element_name($this);
        }

        //read in and instantiate Helper's needed for this View
        require_once BASE_DIR."/views/helpers/helper.php";

        foreach($this->helpers as $helper) {
            if(file_exists(
                APP_DIR."/views/helpers/".$helper."_helper.php")) {
                require_once 
                    APP_DIR."/views/helpers/".$helper."_helper.php";
            } else {
                require_once BASE_DIR."/views/helpers/".$helper."_helper.php";
            }

            $helper_name = ucfirst($helper)."Helper";
            $helper_instance_name = lcfirst($helper_name);

            $this->$helper_instance_name = new $helper_name();
        }

        //read in localized static page elements
        foreach($this->pages as $page) {
            $page_file = LOCALE_DIR."/".getLocaleTag()."/pages/".$page.".thtml";
            $fallback = LOCALE_DIR."/".DEFAULT_LOCALE."/pages/".$page.".thtml";

            if(file_exists($page_file)) {
                $page_string = file_get_contents($page_file);
            } else if (file_exists($fallback)) {
                $page_string = file_get_contents($fallback);
            } else {
                $page_string = "";
            }
            $page_parts = explode("END_HEAD_VARS", $page_string);
            $this->head_objects[$page] = array();
            if(count($page_parts) > 1) { 
                $this->page_objects[$page]  = $page_parts[1];
                $head_lines = explode("\n", $page_parts[0]);
                foreach($head_lines as $line) {
                    $semi_pos =  (strpos($line, ";")) ? strpos($line, ";") :
                        strlen($line);
                    $line = substr($line, 0, $semi_pos);
                    $line_parts = explode("=",$line);
                    if(count($line_parts) == 2) {
                        $this->head_objects[$page][
                             trim(addslashes($line_parts[0]))] =
                                addslashes(trim($line_parts[1]));
                    }
                }
            } else {
                $this->page_objects[$page] = $page_parts[0];
            }
        }

        //read in and instantiate the Layout on which the View will be drawn
        require_once BASE_DIR."/views/layouts/layout.php";

        $layout_name = ucfirst($this->layout)."Layout";
        if($this->layout != "") {
            if(file_exists(
                APP_DIR."/views/layouts/".$this->layout."_layout.php")) {
                require_once 
                    APP_DIR."/views/layouts/".$this->layout."_layout.php";
            } else {
                require_once 
                    BASE_DIR."/views/layouts/".$this->layout."_layout.php";
            }
        }

        $this->layout_object = new $layout_name($this);
    }

    /**
     * This method is responsible for drawing both the layout and the view. It 
     * should not be modified to change the display of then view. Instead, 
     * implement renderView.
     *
     * @param array $data  an array of values set up by a controller to be used 
     *      in rendering the view
     */
    function render($data) {
        $this->layout_object->render($data);
    }

    /**
     * This abstract method is implemented in sub classes with code which 
     * actually draws the view. The current layouts render method calls this 
     * function.
     *
     *  @param array $data  an array of values set up by a controller to be used 
     *      in rendering the view
     */
    abstract function renderView($data);
}

?>
