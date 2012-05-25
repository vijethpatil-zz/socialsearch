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
 * Test to see how many sockets system can open. On *nix systems, by doing 
 * ulimit -n 
 * you can find this out,
 * but the number doesn't exactly agree.
 *
 * On Macs you can change this value by editing /etc/launchd.conf
 * and having lines like:
 *
 * <pre>
 * limit maxproc 1024 2048
 * limit maxfiles 2048 2048 
 * </pre>
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

ini_set("memory_limit","100M");

$begin_mem = memory_get_usage();
for($i = 0; $i < 10000; $i++)
{
    $socket[$i] =socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    $address = "127.0.0.1";
    $port = 80;


    $val = socket_connect($socket[$i], $address, $port);
    if($val) {
        echo "connection established\n";

        echo "Memory used $i:".( memory_get_usage() - $begin_mem)."\n";

    }
}

for($j = 0; $j <= $i; $j++) {
    socket_close($socket[$j]);
}
?>
