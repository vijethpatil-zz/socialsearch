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
 * Load system-wide defines
 */
require_once BASE_DIR."/configs/config.php";
/**
 * Load the crawlLog function
 */
require_once BASE_DIR."/lib/utility.php"; 
/**
 *  Load common constants for crawling
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Used to run scripts as a daemon on *nix systems
 * To use CrawlDaemon need to declare ticks first in a scope that
 * won't go away after CrawlDaemon:init is called
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */
class CrawlDaemon implements CrawlConstants
{

    /**
     * Name prefix to be used on files associated with this daemon
     * (such as lock like and messages)
     * @var string
     * @static
     */
    static $name;

    /**
     *  Subname of the name prefix used on files associated with this daemon
     *  For example, the name might be fetcher, the subname might 2 to indicate
     *  which fetcher daemon instance.
     *
     * @var string
     * @static
     */
     static $subname;

    /**
     * Used by processHandler to decide when to update the lock file
     * @var int
     * @static
     */
    static $time;

    /**
     * Tick callback function used to update the timestamp in this processes
     * lock. If lock_file does not exist it stops the process
     */
    static function processHandler()
    {
        $now = time();
        if($now - self::$time < 30) return;

        self::$time = $now;
        $lock_file = CrawlDaemon::getLockFileName(self::$name, self::$subname);
        if(!file_exists($lock_file)) {
            $name_string = CrawlDaemon::getNameString(self::$name, 
                self::$subname);
            crawlLog("Stopping $name_string ...");
            exit();
        }

        file_put_contents($lock_file, $now);
    }

    /**
     * Used to send a message the given daemon or run the program in the
     * foreground.
     *
     * @param array $argv an array of command line arguments. The argument
     *      start will check if the process control functions exists if these
     *      do they will fork and detach a child process to act as a daemon.
     *      a lock file will be created to prevent additional daemons from
     *      running. If the message is stop then a message file is written to 
     *      tell the daemon to stop. If the argument is terminal then the
     *      program won't be run as a daemon.
     * @param string $name the prefix to use for lock and message files
     */
    static function init($argv, $name, $exit = true)
    {
        self::$name = $name;

        if(isset($argv[2]) && $argv[2] != "none") {
            self::$subname = $argv[2];
        } else {
            self::$subname = "";
        }
        //don't let our script be run from apache
        if(isset($_SERVER['DOCUMENT_ROOT']) && 
            strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
            echo "BAD REQUEST";
            exit();
        }
        if(!isset($argv[1])) {
            echo "$name needs to be run with a command-line argument.\n";
            echo "For example,\n";
            echo "php $name.php start //starts the $name as a daemon\n";
            echo "php $name.php stop //stops the $name daemon\n";
            echo "php $name.php terminal //runs $name within the current ".
                "process, not as a daemon, output going to the terminal\n";
            exit();
        }

        $messages_file = self::getMesssageFileName(self::$name, self::$subname);

        switch($argv[1])
        {
            case "start":
                $options = "";
                for($i = 3; $i < count($argv); $i++) {
                    $options .= " ".$argv[$i];
                }
                $subname = ($argv[2] == 'none') ? 'none' :self::$subname;
                $name_prefix = (isset($argv[3])) ? $argv[3] : self::$subname;;
                $name_string = CrawlDaemon::getNameString($name,$name_prefix);
                echo "Starting $name_string...\n";
                CrawlDaemon::start($name, $subname, $options, $exit);
            break;

            case "stop":
                CrawlDaemon::stop($name, self::$subname);
            break;

            case "terminal":
                $info = array();
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents($messages_file, serialize($info));
                define("LOG_TO_FILES", false);
            break;

            case "child":
                register_tick_function('CrawlDaemon::processHandler');

                self::$time = time();
                $info = array();
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents($messages_file, serialize($info));

                define("LOG_TO_FILES", true); 
                    // if false log messages are sent to the console
            break;

            default:
                exit();
            break;
        }

    }

    /**
     * Used to start a daemon running in the background
     *
     * @param string $name the main name of this daemon such as queue_server
     *      or fetcher.
     * @param string $subname the instance name if it is possible for more
     *      than one copy of the daemon to be running at the same time
     * @param string $options a string of additional command line options
     */
    static function start($name, $subname = "", $options = "", $exit = true)
    {
        $tmp_subname = ($subname == 'none') ? '' : $subname;
        $lock_file = CrawlDaemon::getLockFileName($name, $tmp_subname);

        if(file_exists($lock_file)) {
            $time = intval(file_get_contents($lock_file));
            if(time() - $time < 60) {
                echo "$name appears to be already running...\n";
                echo "Try stopping it first, then running start.";
                exit();
            }
        }
        if(strstr(PHP_OS, "WIN")) {
            $base_dir = str_replace("/", "\\", BASE_DIR);
            $script = "psexec -accepteula -d php ".
                $base_dir."\\bin\\$name.php child %s";
        } else {
            $script = "echo \"php ".
                BASE_DIR."/bin/$name.php child %s\" | at now ";
        }
        $total_options = "$subname $options";
        $at_job = sprintf($script, $total_options);
        exec($at_job);

        if($exit) {
            file_put_contents($lock_file,  time());
            exit();
        }
    }

    /**
     * Used to stop a daemon that is running in the background
     *
     * @param string $name the main name of this daemon such as queue_server
     *      or fetcher.
     * @param string $subname the instance name if it is possible for more
     *      than one copy of the daemon to be running at the same time
     */
    static function stop($name, $subname = "")
    {
        $name_string = CrawlDaemon::getNameString($name, $subname);
        $lock_file = CrawlDaemon::getLockFileName($name, $subname);
        if(file_exists($lock_file)) {
            unlink($lock_file);
            crawlLog("Sending stop signal to $name_string...");
        } else {
            crawlLog("$name_string does not appear to running...");
        }
        exit();
    }

    /**
     * Used to return the string name of the messages file used to pass
     * messages to a daemon running in the background
     *
     * @param string $name the main name of this daemon such as queue_server
     *      or fetcher.
     * @param string $subname the instance name if it is possible for more
     *      than one copy of the daemon to be running at the same time
     *
     * @return string the name of the message file for the daemon with
     *      the given name and subname
     */
    static function getMesssageFileName($name, $subname = "")
    {
        return CRAWL_DIR."/schedules/".self::getNameString($name, $subname)
            . "_messages.txt";
    }

    /**
     * Used to return the string name of the lock file used to pass
     * by a daemon
     *
     * @param string $name the main name of this daemon such as queue_server
     *      or fetcher.
     * @param string $subname the instance name if it is possible for more
     *      than one copy of the daemon to be running at the same time
     *
     * @return string the name of the lock file for the daemon with
     *      the given name and subname
     */
    static function getLockFileName($name, $subname = "")
    {
        return CRAWL_DIR."/schedules/".self::getNameString($name, $subname)
            . "_lock.txt";
    }

    /**
     * Used to return a string name for a given daemon instance
     *
     * @param string $name the main name of this daemon such as queue_server
     *      or fetcher.
     * @param string $subname the instance name if it is possible for more
     *      than one copy of the daemon to be running at the same time
     *
     * @return string a single name that combines the name and subname
     */
    static function getNameString($name, $subname)
    {
            return ($subname == "") ? $name : $subname."-".$name;
    }

    /**
     * Returns the statuses of the running daemons 
     * 
     * @return array 2d array active_daemons[name][instance] = true
     */
    static function statuses()
    {
        $prefix = CRAWL_DIR."/schedules/";
        $prefix_len = strlen($prefix);
        $suffix = "_lock.txt";
        $suffix_len = strlen($suffix);
        $lock_files = "$prefix*$suffix";
        clearstatcache();
        $time = time();
        $active_daemons = array();
        foreach (glob($lock_files) as $file) {
            if($time - filemtime($file)  < 120) {
                $len = strlen($file) - $suffix_len - $prefix_len;
                $pre_name = substr($file, $prefix_len, $len);
                $pre_name_parts = explode("-", $pre_name);
                if(count($pre_name_parts) == 1) {
                    $active_daemons[$pre_name][-1] = true;
                } else { 
                    $first = array_shift($pre_name_parts);
                    $rest = implode("-", $pre_name_parts);
                    $active_daemons[$rest][$first] = true;
                }
            }
        }
        return $active_daemons;
    }

}
 ?>
