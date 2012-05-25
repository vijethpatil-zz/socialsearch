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
 * pagination of search results
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */
class PaginationHelper extends Helper
{
    /**
     * The maximum numbered links to pages to show besides the next and 
     * previous links
     *  @var int
     */
    const MAX_PAGES_TO_SHOW = 11;

    /**
     * Draws a strip of links which begins with a previous
     * link (if their are previous pages of links) followed by up to
     * ten links to more search result page (if available) followed
     * by a next set of pages link.
     *
     * @param string $base_url the url together with base query that the 
     *      search was done on
     * @param int $limit the number of the first link to display in the 
     *      set of search results.
     * @param int $results_per_page   how many links are displayed on a given 
     *      page of search results
     * @param int $total_results the total number of search results for the 
     *      current search term
     */
    public function render($base_url, $limit, $results_per_page, $total_results)
    {
        $num_earlier_pages = ceil($limit/$results_per_page);
        $total_pages = ceil($total_results/$results_per_page);

        if($num_earlier_pages < floor(self::MAX_PAGES_TO_SHOW/2)) {
            $first_page = 0;
        } else {
            $first_page = $num_earlier_pages - floor(self::MAX_PAGES_TO_SHOW/2);
        }

        if($first_page + self::MAX_PAGES_TO_SHOW > $total_pages) {
            $last_page = $total_pages;
        } else {
            $last_page = $first_page + self::MAX_PAGES_TO_SHOW;
        }

        echo "<div class='pagination'><ul>";
        if(0 < $num_earlier_pages) {
            $prev_limit = ($num_earlier_pages - 1)*$results_per_page;
            echo "<li><span class='end'>&laquo;".
                "<a href='$base_url&amp;limit=$prev_limit' rel='nofollow'>".
                tl('pagination_helper_previous')."</a></span></li>";
        }
        if(MOBILE) {
            if(0 < $num_earlier_pages && $num_earlier_pages < $total_pages - 1){
                e("<li><span class='end'>--</span></li>");
            }
        } else {
            for($i=$first_page; $i < $last_page; $i++) {
                 if($i == $num_earlier_pages) {
                    echo "<li><span class='item'>$i</span></li>";
                 } else {
                    $cur_limit = $i * $results_per_page;
                    echo "<li><a class='item' href='$base_url".
                        "&amp;limit=$cur_limit' rel='nofollow'>$i</a></li>";
                 }
            }
        }
        if($num_earlier_pages < $total_pages - 1) {
            $next_limit = ($num_earlier_pages + 1)*$results_per_page;
            echo "<li><span class='end'><a href='$base_url".
                "&amp;limit=$next_limit' rel='nofollow'>".
                tl('pagination_helper_next')."</a>&raquo;</span></li>";
        }

        echo "</ul></div>";
    }

}
?>
