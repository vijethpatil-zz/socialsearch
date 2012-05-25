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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Marker interface used to say that a class has supports a join() 
 * callback method. IndexArchiveBundle has methods which take objects
 * that implement Join. For activities which may take a long time
 * such as index saving index tier merging IndexArchiveBundle will
 * periodically call the Join objects join method so that it can continue
 * processing rather than blocking entirely until the long running method
 * completes
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @see WebQueueBundle
 */ 
 
interface Join
{
    /**
     * A callback function which will be invoked periodically by a method
     * of another object that runs a long time.
     */
    function join();
} 
?>
