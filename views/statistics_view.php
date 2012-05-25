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
 *
 * Draws a view displaying statistical information about a
 * web crawl such as number of hosts visited, distribution of
 * file sizes, distribution of file type, distribution of languages, etc
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class StatisticsView extends View
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

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
     * Draws the web page used to display statistics about the default crawl
     *
     * @param array $data   contains anti CSRF token YIOOP_TOKEN, as well
     *      statistics info about a web crawl
     */
    public function renderView($data) {
        $logo = "resources/yioop.png";
        if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        if(isset($data["UNFINISHED"])) {
            e('<div class="landing" style="clear:both">');
        } ?>
        <h1 class="stats logo"><a href="./?YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN'])?>"><img 
            src="<?php e($logo);?>" alt="Yioop!" /></a><span> - <?php 
            e(tl('statistics_view_statistics')); ?></span></h1>
        <div class="statistics">
        <?php
        $base_url = "?YIOOP_TOKEN=".$data["YIOOP_TOKEN"]."&its=".$data["its"];
        if(isset($data["UNFINISHED"])) {
            e("<h1 class='center'>".tl('statistics_view_calculating')."</h1>");

            e("<h2 class='red center' style='text-decoration:blink'>"
                .$data["stars"]."</h2>");
            ?>

            <script type="text/javascript">
                function continueCalculate()
                {
                    window.location = '<?php
                        e("$base_url&c=statistics&stars=".$data["stars"]); ?>';
                }
                setTimeout("continueCalculate()", 2000);
            </script>
        <?php } else {
            $headings = array(
                tl("statistics_view_error_codes") => "CODE",
                tl("statistics_view_sizes") => "SIZE",
                tl("statistics_view_links_per_page") => "NUMLINKS",
                tl("statistics_view_page_date") => "MODIFIED",
                tl("statistics_view_dns_time") => "DNS",
                tl("statistics_view_download_time") => "TIME",
                tl("statistics_view_top_level_domain") => "SITE",
                tl("statistics_view_file_extension") => "FILETYPE",
                tl("statistics_view_media_type") => "MEDIA",
                tl("statistics_view_language") => "LANG",
                tl("statistics_view_server") => "SERVER",
                tl("statistics_view_os") => "OS",

                );
        ?>
        <h2><?php e(tl("statistics_view_general_info")); ?></h2>
        <p><b><?php e(tl("statistics_view_description")); ?></b>:
        <?php e($data["DESCRIPTION"])?></p>
        <p><b><?php e(tl("statistics_view_timestamp")); ?></b>:
        <?php e($data["TIMESTAMP"])?></p>
        <p><b><?php e(tl("statistics_view_crawl_date")); ?></b>:
        <?php e(date("r",$data["TIMESTAMP"]))?></p>
        <p><b><?php e(tl("statistics_view_pages")); ?></b>:
        <?php e($data["VISITED_URLS_COUNT"])?></p>
        <p><b><?php e(tl("statistics_view_url")); ?></b>:
        <?php e($data["COUNT"])?></p>
        <?php if(isset($data["SEEN"]["HOST"]["all"])) { ?>
            <p><b><?php e(tl("statistics_view_number_hosts")); ?></b>:
            <?php e($data["SEEN"]["HOST"]["all"])?></p>
        <?php 
        }
            foreach($headings as $heading => $group_name) {
                if(isset($data[$group_name]["TOTAL"])) { ?>
                    <h2><?php e($heading); ?></h2>
                    <table summary= "$heading TABLE" class="box">
                        <?php 
                            $total = $data[$group_name]["TOTAL"];
                            $lower_name = strtolower($group_name);
                            foreach($data[$group_name]["DATA"] as 
                                $name => $value) {
                                $width = round(500*$value/(max($total,1)));
                                e("<tr><th><a href='".$base_url."&c=search".
                                    "&q=$lower_name:$name' rel='nofollow'>".
                                    "$name</a></th>".
                                    "<td><div style='background-color:green;".
                                        "width:{$width}px;' >$value</div>".
                                    " </td></tr>");
                            } ?>
                    </table>
                <?php
                }
            }
        }
    ?>
    </div>
    <?php
        if(isset($data["UNFINISHED"])) {
            e("</div><div class='landing-spacer'></div>");
        }
    }
}
?>
