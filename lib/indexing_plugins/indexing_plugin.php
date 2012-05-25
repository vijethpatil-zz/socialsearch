<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2011 - 2012 Priya Gangaraju priya.gangaraju@gmail.com, 
 *                            Chris Pollett
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com, Chris Pollett
 * @package seek_quarry
 * @subpackage indexing_plugin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2011 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
 
/** Some models might interface with a DBMS so load the DBMS manager*/
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/**
 * Base indexing plugin Class. An indexing plugin allows a developer
 * to do additional processing on web pages during a crawl, then after
 * the web crawl is over do post processing on the additional data
 * that was collected. For example, during a crawl one might by analysing
 * web pages mark pages that have recipes on them with the meta word
 * recipe:all, then after the crawl is over do post processing such
 * as clustering the recipe's found and add additional meta words to
 * retrieve recipe's by principle ingredient. Subclasses of IndexingPlugin
 * do in crawl processing by overriding the pageProcessing method, they
 * do post crawl processing by overriding the postProcessing method. In 
 * addition a subclass should override the static functions
 * getProcessors() to say what PageProcessor's the plugin should be
 * associated with as well as getAdditionalMetaWords() to say what
 * additional meta words the plugin injects into the index.
 *
 * @author Priya Gangaraju, Chris Pollett
 * @package seek_quarry
 * @subpackage indexing_plugin
 */ 
abstract class IndexingPlugin
{

    /**
     * Array of the PageProcessor classes used by this IndexingPlugin 
     * (contructor loads these)
     * @var array
     */
    var $processors = array();
    /**
     * Array of the model classes used by this IndexingPlugin 
     * (contructor loads these)
     * @var array
     */
    var $models = array();
    /**
     * The IndexArchiveBundle object that this indexing plugin might
     * make changes to in its postProcessing method
     * @var object
     */
    var $index_archive;

    /**
     * Reference to a database object that might be used by models on this 
     * plugin
     * @var object
     */
    var $db;

    /**
     * Builds an IndexingPlugin object. Loads in the appropriate
     * models for the given plugin object
     */
    function __construct() 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();
        
        require_once BASE_DIR."/models/model.php";

        foreach($this->models as $model) {
            require_once BASE_DIR."/models/".$model."_model.php";
             
            $model_name = ucfirst($model)."Model";
            $model_instance_name = lcfirst($model_name);

            $this->$model_instance_name = new $model_name();
        }
        
    }

    /**
     * This method is called by a PageProcessor in its handle() method
     * just after it has processed a web page. This method allows
     * an indexing plugin to do additional processing on the page
     * such as adding sub-documents, before the page summary is
     * handed back to the fetcher.
     *
     *  @param string $page web-page contents
     *  @param string $url the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return array consisting of a sequence of subdoc arrays found
     *      on the given page. Each subdoc array has a self::TITLE and
     *      a self::DESCRIPTION
     */
    abstract function pageProcessing($page, $url);

    /**
     * This method is called by the queue_server with the name of
     * a completed index. This allows the indexing plugin to 
     * perform searches on the index and using the results, inject
     * new page/index data into the index before it becomes available
     * for end use.
     *
     * @param string $index_name the name/timestamp of an IndexArchiveBundle
     *      to do post processing for
     */
    abstract function postProcessing($index_name);

    /**
     * @return array string names of page processors that this plugin
     *      associates with
     */
    static function getProcessors() {return NULL;}

    /**
     *  Returns an associative array of meta words => description length
     *  for each meta word injected by this plugin into an index. The
     *  description length is used to say how the maximum length of
     *  the web snippet show in search results for this meta owrd should be
     *  
     *  @return array meta words => description length pairs
     */
    static function getAdditionalMetaWords() {return array();}

}
?>
