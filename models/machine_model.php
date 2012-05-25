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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads base model class if necessary*/
require_once BASE_DIR."/models/model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Used to fetches web pages to get statuses of individual machines*/
require_once BASE_DIR."/lib/fetch_url.php";

/**
 * This is class is used to handle
 * db results related to Machine Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class MachineModel extends Model 
{


    /**
     *  {@inheritdoc}
     */
    function __construct() 
    {
        parent::__construct();
    }

    /**
     *  Returns all the machine names stored in the DB
     *
     *  @return array machine names
     */
    function getMachineList()
    {
        $this->db->selectDB(DB_NAME);

        $machines = array();

        $sql = "SELECT * FROM MACHINE ORDER BY NAME DESC"; 

        $result = $this->db->execute($sql);
        $i = 0;

        while($machines[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($machines[$i]); //last one will be null
        

        return $machines;

    }

    /**
     *  Returns urls for all the queue_servers stored in the DB
     *
     *  @return array machine names
     */
    function getQueueServerUrls()
    {
        $this->db->selectDB(DB_NAME);

        $machines = array();

        $sql = "SELECT URL FROM MACHINE WHERE HAS_QUEUE_SERVER > 0 ".
            "ORDER BY NAME DESC"; 

        $result = $this->db->execute($sql);
        $i = 0;

        while($row = $this->db->fetchArray($result)) {
            $machines[$i] = $row["URL"];
            $i++;
        }
        unset($machines[$i]); //last one will be null

        return $machines;
    }

    /**
     *  Add a rolename to the database using provided string
     *
     *  @param string $name  the name of the machine to be added
     *  @param string $url the url of this machine
     *  @param boolean $has_queue_server - whether this machine is running a
     *      queue_server
     *  @param int $num_fetchers - how many managed fetchers are on this
     *      machine.
     *  @param string $parent - if this machine replicates some other machine
     *      then the name of the parent
     */
    function addMachine($name, $url, $has_queue_server, $num_fetchers, 
        $parent = "")
    {
        $this->db->selectDB(DB_NAME);
        if($has_queue_server == true) {
            $has_string = "1";
        } else {
            $has_string = "0";
        }
        $sql = "INSERT INTO MACHINE VALUES ('".
            $this->db->escapeString($name)."','".
            $this->db->escapeString($url)."',".$has_string.",'".
            $this->db->escapeString($num_fetchers)."','".
            $this->db->escapeString($parent)."')";

        $this->db->execute($sql);
    }

    /**
     *  Delete a machine by its name
     *
     *  @param string name - the name of the machine to delete 
     */
    function deleteMachine($machine_name)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM MACHINE WHERE NAME='$machine_name'";
        $this->db->execute($sql);

    }

    /**
     * Returns the statuses of machines in the machine table of their
     * fetchers and queue_server as well as the name and url's of these machines
     *
     * @return array  a list of machines, together with all their properties
     *  and the statuses of their fetchers and queue_servers
     */
    function getMachineStatuses()
    {
        $machines = $this->getMachineList();
        $num_machines = count($machines);
        $time = time();
        $session = md5($time . AUTH_KEY);
        for($i = 0; $i < $num_machines; $i++) {
            $machines[$i][CrawlConstants::URL] =
                $machines[$i]["URL"] ."?c=machine&a=statuses&time=$time".
                "&session=$session";
        }
        $statuses = FetchUrl::getPages($machines, true);
        for($i = 0; $i < $num_machines; $i++) {
            foreach($statuses as $status) {
                if($machines[$i][CrawlConstants::URL] == 
                    $status[CrawlConstants::URL]) {
                    $machines[$i]["STATUSES"] = 
                        json_decode($status[CrawlConstants::PAGE], true);
                }
            }
        }
        return $machines;
    }

    /**
     *  Get either a fetcher or queue_server log for a machine
     *
     *  @param string name  the name of the machine to get the log file for
     *  @param int $fetcher_num  if a fetcher, which instance on the machine
     *  @param bool whether the requested machine is a mirror of another machine
     *  @return string containing the last MachineController::LOG_LISTING_LEN
     *      bytes of the log record
     */
    function getLog($machine_name, $fetcher_num = NULL, $is_mirror = false)
    {
        $time = time();
        $session = md5($time . AUTH_KEY);
        $sql = "SELECT URL FROM MACHINE WHERE NAME='$machine_name'"; 

        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        if($row) {
            $url = $row["URL"]. "?c=machine&a=log&time=$time".
                "&session=$session";
            if($fetcher_num !== NULL) {
                $url .= "&fetcher_num=$fetcher_num";
            }
            if($is_mirror) {
                $url .= "&mirror=true";
            }
            $log_data = urldecode(json_decode(FetchUrl::getPage($url)));
        } else {
            $log_data = "";
        }
        return $log_data;
    }

    /**
     * Used to start or stop a queue_server, fetcher, mirror instance on
     * a machine managed by the current one
     *
     * @param string $machine_name name of machine
     * @param bool whether the requested machine is a mirror of another machine
     *
     */
    function update($machine_name, $action, $fetcher_num = NULL, 
        $is_mirror = false)
    {
        $value = ($action == "start") ? "true" : "false";
        $time = time();
        $session = md5($time . AUTH_KEY);
        $sql = "SELECT URL FROM MACHINE WHERE NAME='$machine_name'"; 

        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        if($row) {
            $url = $row["URL"]. "?c=machine&a=update&time=$time".
                "&session=$session";
            if($fetcher_num !== NULL) {
                $url .= "&fetcher[$fetcher_num]=$value";
            } else if($is_mirror) {
                $url .= "&mirror=$value";
            } else {
                $url .= "&queue_server=$value";
            }
            echo FetchUrl::getPage($url);
        }
    }
}

 ?>
