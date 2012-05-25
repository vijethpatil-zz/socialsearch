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
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

/*
 *  Display a two second message in the message div at the top of the web page
 *
 *  @param String msg  string to display
 */
function doMessage(msg)
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = msg;
    msg_timer = setInterval("undoMessage()", 2000);
}

/*
 *  Undisplays the message display in the message div and clears associated 
 *  message display timer
 */
function undoMessage()
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = "";
    clearInterval(msg_timer);
}

/*
 *  Function to set up a request object even in  older IE's
 *
 *  @return Object the request object
 */
function makeRequest()
{
    try {
        request = new XMLHttpRequest();
    } catch(e) {
        try {
            request = new ActiveXObject('MSXML2.XMLHTTP');
        } catch(e) {
            try {
            request = new ActiveXObject('Microsoft.XMLHTTP');
            } catch(e) {
            return false;
            }
        }
    }
    return request;
}

/*
 *  Make an AJAX request for a url and put the results as inner HTML of a tag
 *
 *  @param Object tag  a DOM element to put the results of the AJAX request
 *  @param String url  web page to fetch using AJAX
 */
function getPage(tag, url)
{
    var request = makeRequest();
    if(request) {

        var self = this;
        request.onreadystatechange = function()
        {
            if(self.request.readyState == 4 ) {
                tag.innerHTML = self.request.responseText;
            }
        }
        request.open("GET", url, true);

        request.send();
    }
}

/*
 *  Shorthand for document.getElementById()
 *
 *  @param String id  the id of the DOM element one wants
 */
function elt(id) 
{
    return document.getElementById(id);
}

/*
 *  Sets whether an elt is styled as display:none or block
 *
 *  @param String id  the id of the DOM element one wants
 *  @param mixed value  true means display block; false display none;
 *      anything else will display that value
 */
function setDisplay(id, value) 
{
    obj = elt(id);
    if(value == true)  {
        value = "block";
    }
    if(value == false) {
        value = "none";
    }
    obj.style.display = value;
}

