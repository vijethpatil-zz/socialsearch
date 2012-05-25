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
 * @author vijeth patil  vijeth.patil@gmail.com
 * @package seek_quarry
 * @subpackage feeds
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */
 
/**
 * This class is used to crawl
 * twitter feeds related to registered users
 *
 * @author vijeth patil
 * @package seek_quarry
 * @subpackage feeds
 */
class RssFeed{

    /**
     *  constructor initializes the db connection
     */
    function __construct()
    {  
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();
        $this->db->connect(); 
        $this->db->selectDB(DB_NAME);
    }
    
    /**
     *  Main function of the class responsible for managing the RSS feed crawling.
     *  Fetches all the subscibed URIs from the database and initializes the retrieval
     */
    function get_url_links()
    {       
        $stmt = "SELECT FEEDURL_ID FROM USER_RSSFEED";
        $result = $this->db->execute($stmt);
        while($row = $this->db->fetchArray($result))
        {
            if($row){
                $feedurl_id = $row['FEEDURL_ID'];
                $stmt = "SELECT FEEDURL FROM RSSFEED WHERE FEEDURL_ID = $feedurl_id LIMIT 1";
                $urlresult = $this->db->execute($stmt);
                if($urlresult){
                    $urls = $this->db->fetchArray($urlresult);
                    $url = $urls['FEEDURL'];
                    $this->get_rss_feeds($url,$feedurl_id);
                }
            }
        }      
        $this->db->disconnect();  
    }
    
    /**
     *  This function is responsible for calling the Curl function to download pages
     *  and also for the insertion of rss feeds into database
     */
    function get_rss_feeds($url,$feedurl_id)
    {
        $feedpage = FetchUrl::getPage($url);  // call to Yioop!'s lib Curl function to download pages
        $arrayfeeds = $this->get_each_feed($feedpage);
        if($feedpage)
        {
            $arrayfeeds = $this->get_each_feed($feedpage);
            if($arrayfeeds){
                foreach($arrayfeeds as $rssfeed){
                    if($rssfeed['desc']){
                        $rssfeed['desc'] = strip_tags($rssfeed['desc']);
                        if(strlen($rssfeed['desc']) < 300){
                            $description = $rssfeed['desc'];
                        }
                        else{
                            $description = substr($rssfeed['desc'],0,299);
                        }
                        $feedid = base_convert(crc32($description), 16, 10);
                        $stmt = "SELECT * FROM FEED WHERE FEED_ID='$feedid' LIMIT 1";
                        $result = $this->db->execute($stmt);
                        $row = $this->db->fetchArray($result);
                        if($row){
                        }
                        else{
                            $feedid = base_convert(crc32($description), 16, 10);
                            $feeder = $rssfeed['title'];
                            if(strlen($feeder) > 50){
                                $feeder = substr($feeder,0,49);
                            }
                            $feeder = $this->db->escapeString($feeder);
                            $feedtext = $this->db->escapeString($description);
                            $isrefeed = 0;                        
                            $feedtime = $rssfeed['date'];
                            $feedsource = $rssfeed['link'];
                            if(strlen($feedsource) > 230){
                                $feedsource = substr($feedsource,0,49);
                            }
                            $feederpic = 'http://upload.wikimedia.org/wikipedia/en/4/43/Feed-icon.svg';
                            $feedfollowers = 1;
                            $feedfriends = 1;
                            $feedverified = 1;
                            $stmt = "INSERT INTO FEED VALUES ($feedid,'$feeder','$feedtext',$isrefeed,'$feedtime','$feedsource','$feederpic','$feedfollowers', '$feedfriends', '$feedverified')";
                            if($this->db->execute($stmt)){
                                $stmt = " SELECT USER_ID FROM USER_RSSFEED WHERE FEEDURL_ID = $feedurl_id ";
                                $feedsubscribers = $this->db->execute($stmt);
                                while($subscriber = $this->db->fetchArray($feedsubscribers))
                                {
                                    $user_id = $subscriber['USER_ID'];
                                    $stmt = " INSERT INTO USER_FEED VALUES ('$user_id','$feedid','rss')";
                                    $this->db->execute($stmt);
                                }
                            }                      
                            
                        }
                    }
                }
            }
        }
        return;
    }
    
    /**
     *  This function parses the XML files
     *  and returns the array back.
     */
    function get_each_feed($feedpage)
    {
  
        $doc = new DOMDocument();
        if($feedpage)
        {
            $doc->loadXML($feedpage);
            $arrFeeds = array();
            $title = "unknown";
            $description = "missing";
            $link = "missing";
            if($title = $doc->getElementsByTagName('title')->item(0)->nodeValue)
            if($description = $doc->getElementsByTagName('description')->item(0)->nodeValue)
            if($link = $doc->getElementsByTagName('link')->item(0)->nodeValue)
            if($pubdate = $doc->getElementsByTagName('pubDate')->item(0)->nodeValue){
            }
            foreach ($doc->getElementsByTagName('item') as $node) {
            $itemRSS = array ( 
              'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
              'desc' => $node->getElementsByTagName('description')->item(0)->nodeValue,
              'link' => $node->getElementsByTagName('link')->item(0)->nodeValue,
              'date' => $node->getElementsByTagName('pubDate')->item(0)->nodeValue
              );
              $itemRSS['date'] = strtotime($itemRSS['date']);
              array_push($arrFeeds, $itemRSS);
            }
            return $arrFeeds;
        }  
    }
}




?>