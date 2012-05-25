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
 * This view is displayed by the fetch_controller.php
 * to send information to a fetcher about things like
 * what to crawl next
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */

class FetchView extends View
{
    /** No layout is used for this view
     *  @var string
     */
    var $layout = "";

    /**
     *  Draws message to be used by a fetcher. It might for example
     *  contains a schedule of sites to crawl
     *
     *  @param array $data   message sent by fetch_controller.php
     */
    public function renderView($data) 
    {
        echo $data['MESSAGE'];
    }
}
?>
