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
 * Test to see for big strings which how long various string concatenation
 * operations take.
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

ini_set("memory_limit","300M");
echo "Time to pack null strings of various lengths with pack('xLEN')\n";

for($i = 10; $i< 100000000; $i *= 10) {
    $start = microtime();
    $big_string = pack("x$i");
    echo "Len = $i Time =".changeInMicroTime($start)."secs \n";
}

echo 'Concatenation $str2 = $str1.$str1 where $str1 of various lengths'."\n";
for($i = 10; $i< 100000000; $i *= 10) {
    $big_string = pack("x$i");
    $start = microtime();
    $str2 = $big_string.$big_string;
    echo "Len = $i Time =".changeInMicroTime($start)."secs \n";
}

unset($str2);

echo 'Concatenation $str1 .= $str1 where $str1 of various lengths'."\n";
for($i = 10; $i< 100000000; $i *= 10) {
    $big_string = pack("x$i");
    $start = microtime();
    $big_string .= $big_string;
    echo "Len = $i Time =".changeInMicroTime($start)."secs \n";
}

echo 'Time to concatenate "hello" on to a string of various lengths'."\n";
for($i = 10; $i< 100000000; $i *= 10) {
    $big_string = pack("x$i");
    $start = microtime();
    $big_string .= "hello";
    echo "Len = $i Time =".changeInMicroTime($start)."secs \n";
}

echo "Concatenate hello various numbers of times to a string of length 10^7\n";

for($i = 10; $i< 10000000; $i *= 10) {
    $big_string = pack("x100000000");
    $start = microtime();
    for($j = 0; $j < $i; $j++) {
        $big_string .= "hello";
    }
    echo "Num Hello Cats = $i Time =".changeInMicroTime($start)."secs \n";
}

/**
 * Measures the change in time in seconds between two timestamps to microsecond
 * precision
 *
 * @param string $start starting time with microseconds
 * @param string $end ending time with microseconds
 * @return float time difference in seconds
 * @see SigninModel::changePassword()
 * @see SigninModel::checkValidSignin()
 * @ignore
 */
function changeInMicrotime( $start, $end=NULL )
{
    if( !$end ) {
            $end= microtime();
    }
    list($start_microseconds, $start_seconds) = explode(" ", $start);
    list($end_microseconds, $end_seconds) = explode(" ", $end);

    $change_in_seconds = intval($end_seconds) - intval($start_seconds);
    $change_in_microseconds =
        floatval($end_microseconds) - floatval($start_microseconds);

    return floatval( $change_in_seconds ) + $change_in_microseconds;
}

?>
