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
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";

/**
 * This is a helper class is used to handle
 * draw select options form elements
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */
 
class OptionsHelper extends Helper
{

    /**
     *  Draws an HTML select tag according to the supplied parameters
     *
     *  @param string $id   the id attribute the select tag should have
     *  @param string $name   the name this form element should use
     *  @param array $options   an array of key value pairs for the options
     *  tags of this select element
     *  @param string $selected   which option (note singular -- no support 
     *  for selecting more than one) should be set as selected 
     *  in the select tag
     */
    public function render($id, $name, $options, $selected)
    { 
    ?>
        <select id="<?php e($id);?>" name="<?php e($name);?>" >
        <?php
        foreach($options as $value => $text) {
        ?>
            <option value="<?php e($value); ?>" <?php 
                if($value== $selected) { e('selected="selected"'); } 
             ?>><?php e($text); ?></option>
        <?php
        }
        ?>
        </select>
        <?php 
    }

}
?>
