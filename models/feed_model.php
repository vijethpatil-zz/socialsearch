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
 * @author Vijeth Patil vijeth.patil@gmail.com
 * @package seek_quarry
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * logging is done during crawl not through web,
 * so it will not be used in the phrase model
 */

/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/** For extractPhrasesAndCount function */
require_once BASE_DIR."/lib/phrase_parser.php";

/**
 * Used to look up words and phrases in the inverted index
 * associated with a given crawl
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");

/**
 * Load iterators to get docs out of index archive
 */
foreach(glob(BASE_DIR."/lib/index_bundle_iterators/*_iterator.php")
    as $filename) {
    require_once $filename;
}
/** 
 *Load the base class 
*/
require_once BASE_DIR."/models/model.php";

/**
*   This class gets the search results from the Twitter and RSS feed results
*/

class FeedModel extends Model
{
    /**
     *       Constructor creates a connection to db
     */
    function __construct($db_name = DB_NAME)
    {
        parent::__construct($db_name);
    }
     
    /**
     * Used to get the results from twitter database
     */    
    function feedResults($query,$user)
    {
        $this->db->selectDB(DB_NAME);
        $query = $this->db->escapeString($query);
        $stmt = "SELECT * FROM FEED AS  F INNER JOIN USER_FEED AS U ON  F.FEED_ID = U.FEED_ID  AND U.USER_ID ='$user' WHERE F.FEEDTEXT like '%$query%' ORDER BY F.FEEDTIME DESC LIMIT 0,100";
        $result = $this->db->execute($stmt);  
        $i=0;
         while ($row = $this->db->fetchArray($result)) {
            $output['TITLE'][$i] = $row['FEEDER'];
            if (!$row['REFEEDCOUNT']) 
            {
                $row['REFEEDCOUNT'] = 0;
            }
            $output['PAGES'][$i] = $row['FEEDTEXT'].". Tweet Link :<a href='http://twitter.com/#!/".$row['FEEDER']."/status/".$row['FEED_ID']."' target=\"_blank\">GoTo  Tweet</a> . Tweeted using : ".$row['FEEDSOURCE'].". Retweet count : ".$row['REFEEDCOUNT'];
            $output['TWEETERPIC'][$i] = $row['FEEDERPIC'];
            $output['TWEETLINK'][$i] = "<a href='http://twitter.com/#!/".$row['FEEDER']."/status/".$row['FEED_ID']."'target=\"_blank\">";
            $rankinfo['REFEEDCOUNT'][$i] = $row['REFEEDCOUNT'];
            $rankinfo['FEEDLENGTH'][$i] = strlen($row['FEEDTEXT']);
            $rankinfo['FEEDTIME'][$i] = $row['FEEDTIME'];
            $rankinfo['FOLLOWERSCOUNT'][$i] = $row['FOLLOWERS_COUNT'];
            $rankinfo['FRIENDSCOUNT'][$i] = $row['FRIENDS_COUNT'];
            $rankinfo['VERIFIED'][$i] = $row['VERIFIED'];
            $i++;
        }
        $description_length = 300;
        $format_words = 0;
        $output['TOTAL_ROWS'] = $i;
        $results = array();
        $pages = array();
        for($j=0;$j<$i;$j++)
        {
         $page[self::TITLE] =  $output['TITLE'] [$j];
         $page[self::DESCRIPTION] = $output['PAGES'][$j];
         $page[self::SCORE] = $this->getReciprocalRankFusionScore($rankinfo,$j);
         $page[self::TWEETLINK] =  $output['TWEETLINK'][$j];
         $page[self::TWEETERPIC] = $output['TWEETERPIC'][$j];
         $pages[$j] = $page;
        }
        if($pages)
        {
            foreach($pages as $page)
            {
                $score[] = $page[self::SCORE];
            }
            array_multisort($score,SORT_ASC,$pages );
            $results['TOTAL_ROWS'] = $output['TOTAL_ROWS'];
            $results['PAGES'] = $pages;
        }    
        return $results;
    }  
    
    /**
     *  To get the ranking of each of the feeds according to reciprocal rank fusion ranking method 
     */
    function getReciprocalRankFusionScore($rankinfo,$j)
    {
        $k=60;
        $score['refeedcount'] =  1/($k + $rankinfo['REFEEDCOUNT'][$j]);
        $score['feedlength']  = 1/($k + $rankinfo['FEEDLENGTH'][$j]);
        $score['feedtime'] = 1/($k + $rankinfo['FEEDTIME'][$j]);
        $score['followerscount'] = 1/($k + $rankinfo['FOLLOWERSCOUNT'][$j]);
        $score['friendscount'] = 1/($k + $rankinfo['FRIENDSCOUNT'][$j]);
        $score['verified'] = 1/($k + $rankinfo['VERIFIED'][$j]);
         
        $rrfscore = $score['refeedcount'] + $score['feedlength'] + $score['feedtime'] + $score['followerscount'] + $score['friendscount'] + $score['verified'] ;
        return $rrfscore;
    }
    /**
     *  To get the feed id
     */
    function getFeedId($usertoken)
    {
        $this->db->selectDB(DB_NAME);
        if(isset($_SESSION['USER_ID'])){
            $user = $_SESSION['USER_ID'];
        } else{
            $user = $_SERVER['REMOTE_ADDR']; 
        }
        $stmt = "SELECT * FROM USER_KEYS WHERE USER_ID = '$user'";
        $result = $this->db->execute($stmt); 
        $row = $this->db->fetchArray($result);
        if($row){
            return $row;
        }
        else
            return 0;
    }
    
    /**
     *  To add twitter usertoken and secret to the database
     */
    function addFeed($usertoken,$usersecret,$userscreenname)
    {
        $this->db->selectDB(DB_NAME);
        
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR']; 
        }
        $consumer_key = 'tNjvFxZsTH6jXt7VjO6tQ';
        $consumer_secret = 'xoc6OpG0cpufsqKsE8sZANpjft5o81R46BYWV0dcOc';
        $feedname = "twitter";
        $sinceid = '12345';
        $stmt = "INSERT INTO USER_KEYS VALUES ('$user','$feedname','$consumer_key','$consumer_secret','$usertoken','$usersecret','$userscreenname','$sinceid')";
        $result = $this->db->execute($stmt);
    }
    
    /**
     *  To add RSS Feed URL to the database
     */
    function getRSSFeedId($url)
    {
        return;
    }
    /**
     *  To add RSS Feed URL to the database
     */
    function addRSSFeed($url)
    {
        $this->db->selectDB(DB_NAME);
        
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR']; 
        }
        
        $checkstmt = "SELECT FEEDURL_ID FROM RSSFEED WHERE FEEDURL = '$url' LIMIT 1";
        $result = $this->db->execute($checkstmt);
        $row = $this->db->fetchArray($result);
        $feedurl_id = $row['FEEDURL_ID'];
        
        if(isset($feedurl_id)){
            $checkdupstmt = "SELECT * FROM USER_RSSFEED WHERE USER_ID = '$user' AND FEEDURL_ID = '$feedurl_id' LIMIT 1";
            $result = $this->db->execute($checkdupstmt);
            $row = $this->db->fetchArray($result);
            $user_id = $row['USER_ID'];
            if(isset($user_id)){
                return;
            }
            else{
                $adduserrssfeedstmt = "INSERT INTO USER_RSSFEED VALUES ('$user','$feedurl_id')";
                $this->db->execute($adduserrssfeedstmt);
                return;
            }
        }
        else{
            $lastrowstmt = "SELECT FEEDURL_ID FROM RSSFEED  ORDER BY FEEDURL_ID DESC LIMIT 1";
            $result = $this->db->execute($lastrowstmt);
            $row = $this->db->fetchArray($result);
            if(isset($row)){
                $feedurl_id = $row['FEEDURL_ID'] + 1;
            }
            else {
                $feedurl_id = 1;
            }
                
            $addrssfeedstmt = "INSERT INTO RSSFEED VALUES ('$feedurl_id','$url')";
            $this->db->execute($addrssfeedstmt);
            $adduserrssfeedstmt = "INSERT INTO USER_RSSFEED VALUES ('$user','$feedurl_id')";
            $this->db->execute($adduserrssfeedstmt);
            return;
        }
        
    }
    
    /**
     *  Update Since ID after each crawl
     */
    function updateSinceID($newsinceid,$user_screenname)
    {
        $this->db->selectDB(DB_NAME);  
        $stmt = " UPDATE USER_KEYS SET CURRENT_SINCEID ='$newsinceid' WHERE USER_SCREENNAME = '$user_screenname'";
        $this->db->execute($stmt);
        return;
    }
    
    /**
     *  Fetches all the feeds subscribed by a user
     */
    function getFeedList()
    {
        $this->db->selectDB(DB_NAME);
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR']; 
        }
        if(in_array(DBMS, array('sqlite', 'sqlite3'))) {
            $stmt = " SELECT R.FEEDURL AS FEEDURL,R.FEEDURL_ID AS FEEDURL_ID FROM RSSFEED R INNER JOIN USER_RSSFEED U ON R.FEEDURL_ID = U.FEEDURL_ID AND U.USER_ID = $user ";
        }
        else {
            $stmt = " SELECT R.FEEDURL AS FEEDURL,R.FEEDURL_ID AS FEEDURL_ID FROM RSSFEED R INNER JOIN USER_RSSFEED U ON R.FEEDURL_ID = U.FEEDURL_ID AND U.USER_ID = $user ";
        }
        $result = $this->db->execute($stmt);
        $i=0;
        $feeds = array();
        while($feeds[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($feeds[$i]);   
        return $feeds;
    }
    
    /**
     *  Delete a  Feed
     */
    function deleteFeed($feedname) 
    {
        $this->db->selectDB(DB_NAME);
        if(isset($_SESSION['USER_ID'])){
            $user = $_SESSION['USER_ID'];
        } else{
            $user = $_SERVER['REMOTE_ADDR']; 
        }
        $stmt = "DELETE FROM USER_RSSFEED WHERE FEEDURL_ID=$feedname AND USER_ID = $user";
        $this->db->execute($stmt);
    } 
     
     
     
     
     
     
     
     
     
}
