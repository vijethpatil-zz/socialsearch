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

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();} 
/*
define("BASE_DIR", dirname(realpath('.')));
*/
/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
/** Loads base model class if necessary*/
require_once BASE_DIR."/models/model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";
/** Loads base model class if necessary*/
require_once BASE_DIR."/models/feed_model.php";
require_once BASE_DIR."/feeds/tmhOAuth.php";
require_once BASE_DIR."/feeds/tmhUtilities.php";

/**
 * This class is used to crawl
 * twitter feeds related to registered users
 *
 * @author vijeth patil
 * @package seek_quarry
 * @subpackage feeds
 */
class FeedCrawler implements CrawlConstants
{
    /**
     *  variable for database connection
     */
    var $db;
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
     * Main function of feed crawler. This function will be invoked from feed server.
     * Responsible for the managing crawl of all users tweets
     */
    function getUserTokens()
    {   
        $stmt = "SELECT * FROM USER_KEYS ";
        $userkeysresult = $this->db->execute($stmt);
        while($row = $this->db->fetchArray($userkeysresult)) {
            $user_id = $row['USER_ID'];
            $consumer_key = $row['CONSUMER_KEY'];
            $consumer_secret = $row['CONSUMER_SECRET'];
            $user_token = $row['USER_KEY'];
            $user_secret = $row['USER_SECRET'];
            $user_screenname = $row['USER_SCREENNAME'];
            $since_id = $row['CURRENT_SINCEID'];
            $this->fetchTweets($user_id,$consumer_key,$consumer_secret,$user_token,$user_secret,$user_screenname,$since_id);
        }
        $data['USER_ID'] = $user_id;
        $data['USER_KEY'] = $user_token;
        $data['USER_SECRET'] = $user_secret;
        $this->db->disconnect();
        return $data;
    }

     
    /**
     *  function responsible for creating API requests and retrieving the JSON response
     */
    function fetchTweets($user_id,$consumer_key,$consumer_secret,$user_token,$user_secret,$user_screenname,$since_id)
    {   
        $tmhOAuth = new tmhOAuth(array(
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
            'user_token'      => $user_token,
            'user_secret'     => $user_secret,
        ));
                
        $time_start = microtime(true);
        if($since_id == 12345){
            $code = $tmhOAuth->request('GET', $tmhOAuth->url('1/statuses/home_timeline'), array(
            'include_entities' => '1',
            'include_rts' => '1',
            'screen_name' => '$user_screenname',
            'count' => 200,
            ));
        }
        else {
        $code = $tmhOAuth->request('GET', $tmhOAuth->url('1/statuses/home_timeline'), array(
            'include_entities' => '1',
            'include_rts' => '1',
            'screen_name' => '$user_screenname',
            'count' => 200,
            'since_id' => $since_id
        ));
        }
        if ($code == 200) {
            $timeline = json_decode($tmhOAuth->response['response'], true);
            $this->crawlTweets($timeline,$user_id);
            
        }
    }
     
	/**
     * Parses the JSON data and retrieves tweets and inserts into database
     */
    function crawlTweets($timeline,$user_id)
    {
        foreach ($timeline as $tweet) :
            $entified_tweet = tmhUtilities::entify($tweet);
            if(isset($tweet['retweet_count'])) {
                $is_retweet = $tweet['retweet_count'] ;
            }
            else {
                 $is_retweet = 0;
            }
            $tweettime  =  $tweet['created_at'];
            $tweeter = $tweet['user']['screen_name'];
            $tweetid = $tweet['id_str'];
            $tweettext = $tweet['text'];
            $tweetsource = $tweet['source'];
            $tweeterpic = $tweet['user']['profile_image_url'];
            $tweettime = strtotime($tweettime);
            $tweetfollowers = $tweet['user']['followers_count'];
            $tweetfriends = $tweet['user']['friends_count'];
            $tweetverified = $tweet['user']['verified'];
            
            $tweetid = $this->db->escapeString("$tweetid");
            $tweeter = $this->db->escapeString("$tweeter");
            $tweettext = $this->db->escapeString("$tweettext");
            $is_retweet = $this->db->escapeString("$is_retweet");
            $tweettime = $this->db->escapeString("$tweettime");
            $tweetsource = $this->db->escapeString("$tweetsource");
            $tweeterpic = $this->db->escapeString("$tweeterpic");
            $tweetfollowers = $this->db->escapeString("$tweetfollowers");
            $tweetfriends = $this->db->escapeString("$tweetfriends");
            $tweetverified = $this->db->escapeString("$tweetverified");
            
            if(!$tweetverified)
            {
                $tweetverified = 0;
            }
            if(!$is_retweet)
            {
                $is_retweet = 0;
            }
            $stmt = "SELECT * FROM FEED WHERE FEED_ID ='$tweetid' LIMIT 1";
            $result = $this->db->execute($stmt);
            if($row = $this->db->fetchArray($result)){
                $stmt = " SELECT * FROM USER_FEED WHERE FEED_ID = $tweetid AND USER_ID = $user_id LIMIT 1";
                $alreadyinserted = $this->db->execute($stmt);
                if($row = $this->db->fetchArray($alreadyinserted)){
                }
                else{
                    $stmt = " INSERT INTO USER_FEED VALUES ('$user_id','$tweetid','twitter') ";
                    $this->db->execute($stmt);
                }
            }
            else{
                $sqlstmt = "INSERT INTO FEED VALUES ($tweetid,'$tweeter','$tweettext',$is_retweet,'$tweettime','$tweetsource','$tweeterpic','$tweetfollowers', '$tweetfriends', '$tweetverified')";
                $this->db->execute($sqlstmt);
                $sqlstmt = " INSERT INTO USER_FEED VALUES ('$user_id','$tweetid','twitter') ";
                $this->db->execute($sqlstmt);
                $sqlstmt = " UPDATE USER_KEYS SET CURRENT_SINCEID = '$tweetid' WHERE USER_ID = '$user_id'";
                $this->db->execute($sqlstmt);
            }
        endforeach;
    }
}

?>