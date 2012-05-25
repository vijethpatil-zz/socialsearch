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
 * @subpackage layout
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Base layout Class. Layouts are used to
 * render the headers and footer of the page
 * on which a View lives
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage layout
 */
class Layout
{
    /**
     * The view that is to be drawn on this layout
     * @var object
     */
    var $view;

    /**
     * The constructor sets the view that will be drawn inside the
     * Layout.
     *
     */
    public function __construct($v)
    {
       $this->view = $v;
    }

    /**
     * The render method of Layout and its subclasses is responsible for drawing
     * the header of the document, calling the renderView method of the
     * View that lives on the layout and then drawing the footer of 
     * the document.
     *
     * @param array $data   an array of data set up by the controller to be
     * be used in drawing the Layout and its View.
     */
    public function render($data) {
       $this->view->renderView($data);
    }
}
?>
