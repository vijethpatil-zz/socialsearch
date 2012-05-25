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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Base Element Class.
 * Elements are classes are used to render portions of
 * a web page which might be common to several views
 * like a view there is supposed to minimal php code
 * in an element
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

abstract class Element
{
    /**
     * The View on which this Element is drawn
     * @var object
     */
    var $view;

    /**
     *  constructor stores a reference to the view this element will reside on
     *
     *  @param object $view   object this element will reside on
     */
    public function __construct($view = NULL) 
    {
        $this->view = $view;
    }

    /**
     *  This method is responsible for actually drawing the view.
     *  It should be implemented in subclasses.
     *
     *  @param $data - contains all external data from the controller
     *  that should be used in drawing the view
     */
    public abstract function render($data);
}
?>
