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
 * Element responsible for displaying options about how a crawl will be
 * performed. For instance, what are the seed sites for the crawl, what
 * sites are allowed to be crawl what sites must not be crawled, etc.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */

class CrawloptionsElement extends Element
{

    /**
     * Draws configurable options about how a web crawl should be conducted
     *
     * @param array $data keys are generally the different setting that can 
     *      be set in the crawl.ini file
     */
    public function render($data) 
    { 
    ?>
        <div class="currentactivity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=manageCrawls&amp;YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN']) ?>"
        ><?php e(tl('crawloptions_element_back_to_manage'))?></a>
        </div>
        <?php if(isset($data['ts'])) { ?>
        <h2><?php e(tl('crawloptions_element_modify_active_crawl')); ?></h2>
        <?php } else { ?>
        <h2><?php e(tl('crawloptions_element_edit_crawl_options')); ?></h2>
        <?php } ?>
        <form id="crawloptionsForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageCrawls" />
        <input type="hidden" name="arg" value="options" />
        <input type="hidden" name="posted" value="posted" />
        <input type="hidden" id='crawl-type' name="crawl_type" value="<?php 
            e($data['crawl_type'])?>" />
        <?php if(isset($data['ts'])) { ?>
            <input type="hidden" name="ts" value="<?php 
                e($data['ts'])?>" />
        <?php } ?>
        <ul class='tabmenu-list'>
        <?php if(!isset($data['ts']) || 
            $data['crawl_type'] == CrawlConstants::WEB_CRAWL) { ?>
        <li><a  <?php if(!isset($data['ts'])) { ?>
            href="javascript:switchTab('webcrawltab', 'archivetab');"
            <?php } ?>
            id='webcrawltabitem'
            class="<?php e($data['web_crawl_active']); ?>"><?php 
            e(tl('crawloptions_element_web_crawl'))?></a></li>
        <?php
        }
        if(!isset($data['ts']) || 
            $data['crawl_type'] == CrawlConstants::ARCHIVE_CRAWL) { ?>
        <li><a <?php if(!isset($data['ts'])) { ?>
            href="javascript:switchTab('archivetab', 'webcrawltab');"
            <?php } ?>
            id='archivetabitem'
            class="<?php e($data['archive_crawl_active']); ?>"><?php 
            e(tl('crawloptions_element_archive_crawl'))?></a></li>
        <?php } ?>
        </ul>
        <div class='tabmenu-content'>
        <div id='webcrawltab'>
        <?php if(!isset($data['ts'])) { ?>
        <div class="topmargin"><label for="load-options"><b><?php 
            e(tl('crawloptions_element_load_options'))?></b></label><?php
            $this->view->optionsHelper->render("load-options", "load_option", 
                $data['available_options'], $data['options_default']);
        ?></div>
        <div class="topmargin"><label for="crawl-order"><b><?php 
            e(tl('crawloptions_element_crawl_order'))?></b></label><?php
            $this->view->optionsHelper->render("crawl-order", "crawl_order", 
                $data['available_crawl_orders'], $data['crawl_order']);
        ?>
        </div>
        <?php } ?>
        <div class="topmargin"><label for="restrict-sites-by-url"><b><?php 
            e(tl('crawloptions_element_restrict_by_url'))?></b></label>
                <input type="checkbox" id="restrict-sites-by-url" 
                    name="restrict_sites_by_url" value="true" 
                    onclick="setDisplay('toggle', this.checked)" <?php 
                    e($data['TOGGLE_STATE']); ?> /></div>
        <div id="toggle">
            <div class="topmargin"><label for="allowed-sites"><b><?php 
            e(tl('crawloptions_element_allowed_to_crawl'))?></b></label></div>
        <textarea class="shorttextarea" id="allowed-sites" 
            name="allowed_sites"><?php e($data['allowed_sites']);
        ?></textarea></div>
        <div class="topmargin"><label for="disallowed-sites"><b><?php 
            e(tl('crawloptions_element_disallowed_and_quota_sites')); 
                ?></b></label></div>
        <textarea class="shorttextarea" id="disallowed-sites" 
            name="disallowed_sites" ><?php e($data['disallowed_sites']);
        ?></textarea>
        <?php if(!isset($data['ts'])) { ?>
        <div class="topmargin"><label for="seed-sites"><b><?php 
            e(tl('crawloptions_element_seed_sites'))?></b></label></div>
        <textarea class="talltextarea" id="seed-sites" name="seed_sites" ><?php 
            e($data['seed_sites']);
        ?></textarea>
        <?php } else { ?>
        <div class="topmargin"><label for="inject-sites"><b><?php 
            e(tl('crawloptions_element_inject_sites'))?></b></label></div>
        <textarea class="shorttextarea" id="inject-sites" 
            name="inject_sites" ></textarea>
        <?php } ?>
        </div>
        <div id='archivetab'>
        <?php if(!isset($data['ts'])) { ?>
        <div class="topmargin"><label for="load-options"><b><?php 
            e(tl('crawloptions_element_reindex_crawl'))?></b></label><?php
            $this->view->optionsHelper->render("crawl-indexes", "crawl_indexes",
                $data['available_crawl_indexes'], $data['crawl_index']);
        ?></div>
        </div>
        <?php } ?>
        </div>
        <div class="topmargin"><b><?php 
            e(tl('crawloptions_element_meta_words'))?></b></div>
        <table class="metawordstable">
            <tr><th><?php e(tl('crawloptions_element_word'));?></th>
                <th><?php 
                e(tl('crawloptions_element_url_pattern')); ?></th></tr>
            <?php
            $i = 0;
            foreach($data['META_WORDS'] as $word => $url) {
            ?>
                <tr><td class="input-word" ><input
                    title="<?php e(tl('crawloptions_element_word')); ?>"
                    name="META_WORDS[<?php e($i); ?>]['WORD']" 
                    value="<?php e($word); ?>" 
                    />
                </td>
                <td class="input-url"><input
                    title="<?php e(tl('crawloptions_element_url_pattern')); ?>"
                    name="META_WORDS[<?php e($i); ?>]['URL_PATTERN']" 
                    value="<?php e($url); ?>" 
                     />
                </td>
                </tr>
            <?php
                $i++;
            }
            ?>
            <tr><td class="input-word"><input type="text"  title="New Word" 
                name="META_WORDS[<?php e($i); ?>]['WORD']"
                />
                </td>
                <td class="input-url"><input type="text"  title="New URL Pattern" 
                   name="META_WORDS[<?php e($i); ?>]['URL_PATTERN']" /></td>
            </tr>
            </table>

            <?php if(isset($data['INDEXING_PLUGINS'])) {
            ?>
                <div class="topmargin"><b><?php 
                    e(tl("crawloptions_element_indexing_plugins"));?></b></div>
                <table class="indexingplugintable">
                    <tr><th><?php e(tl('crawloptions_element_plugin'));
                                      ?></th>
                    <th><?php 
                        e(tl('crawloptions_element_plugin_include'));
                            ?></th></tr>
                    <?php
                    $k = 0;
                    foreach($data['INDEXING_PLUGINS'] as 
                        $plugin => $toggleState) {
                    ?>
                    <tr><td><?php e($plugin. "Plugin"); ?></td>
                    <td class="check"><input type="checkbox" 
                        name="INDEXING_PLUGINS[<?php e($k); ?>]" 
                        value = "<?php e($plugin) ?>"
                        <?php e($toggleState); ?>
                        /></td></tr>
                <?php 
                    $k++;
                }
                ?>
                </table>
            <?php
            }
            ?> 
            
            
        <div class="center slightpad"><button class="buttonbox" 
            type="submit"><?php e(tl('crawloptions_element_save_options')); 
            ?></button></div>
        </form>
        </div>
        <script type="text/javascript">

        function switchTab(newtab, oldtab)
        {
            setDisplay(newtab, true);
            setDisplay(oldtab, false);
            ntab = elt(newtab+"item");
            if(ntab) {
                ntab.className = 'active';
            }
            otab = elt(oldtab+"item");
            if(otab) {
                otab.className = '';
            }
            ctype = elt('crawl-type');
            if(ctype) {
            ctype.value = (newtab == 'webcrawltab') 
                ? '<?php e(CrawlConstants::WEB_CRAWL); ?>' : 
                '<?php e(CrawlConstants::ARCHIVE_CRAWL); ?>';
            }
        }
        </script>
    <?php
    }
}
?>
