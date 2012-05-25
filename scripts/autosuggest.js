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
 * @author Sandhya Vissapragada
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012s
 * @filesource
 */

/**
 * Steps to follow every time a key is up from the user end
 * Handles up/dowm arrow keys
 *
 * @param Event e current event 
 * @return String text_field Current value from the search box
 *
 */
function askeyup(e,text_field) {
    var keynum;
    var term_array;
    var inputTerm = text_field.value;
    var results_dropdown = document.getElementById("aslist");
    var scrollpos = 0;
    var astobj = document.getElementById("search-name"); 
    concat_term = "";
    if(window.event) { // IE8 and earlier
        keynum = e.keyCode;
    }
    else if(e.which) { // IE9/Firefox/Chrome/Opera/Safari
        keynum = e.which;
    }
    term_array = inputTerm.split(" ");
    concat_array = inputTerm.split(" ",term_array.length-1);          
    if (inputTerm != "") {
        for(var i=0; i < concat_array.length ; i++) {
            concat_term += concat_array[i] + " ";
        }
        concat_term = concat_term.trim();
    }    
    inputTerm = term_array[term_array.length-1];  

    if (keynum != 40 && keynum != 38) {
        search_list="";  
        autosuggest(dictionary, inputTerm);
        document.getElementById("asdropdown").style.visibility = "visible";
        document.getElementById("asdropdown").scrollTop =  0;             
        results_dropdown.innerHTML = search_list;
        cursor_pos=-1; 
        if (search_list != "") {
            results_dropdown.style.visibility = "visible";
        } 
        else {  
            results_dropdown.style.visibility = "hidden";        
        }
        items = results_dropdown.children.length;
        if (items == 0) {
            document.getElementById("asdropdown").className = "";
        } else {
            document.getElementById("asdropdown").className = "dropdown";
            if (items < 6) {
                document.getElementById("asdropdown").style.height = "" + items * 0.25 + "in";
            } else {
                document.getElementById("asdropdown").style.height = "1.5in";
            } 
        }
    }
    if (results_dropdown.style.visibility == "visible") {
        if(keynum == 40) { 
            if (cursor_pos == -1) { 
                cursor_pos = 0;                
                setDisplay(cursor_pos,"selected"); 
            }
            else {  
                setDisplay(cursor_pos,"unselected");                                
                cursor_pos++;
                if (cursor_pos == items) {            
                    cursor_pos=0;
                }
                setDisplay(cursor_pos,"selected");                                                       
            }            
            scrollpos = (cursor_pos > 2) ? (cursor_pos - 2) : 0;
            document.getElementById("asdropdown").scrollTop = scrollpos * 26;
        } else if(keynum == 38) {         
            if (cursor_pos == -1)
            {    
                cursor_pos = items-1;
                setDisplay(cursor_pos,"selected");                          
            }
            else {   
                setDisplay(cursor_pos,"unselected");                    
                cursor_pos--;                                      
                if (cursor_pos == -1) {            
                    cursor_pos=items-1;
                }
                setDisplay(cursor_pos,"selected");                                                         
            }
            scrollpos = (cursor_pos > 2) ? (cursor_pos - 2) : 0;
            document.getElementById("asdropdown").scrollTop = scrollpos * 26;
        }

    }  
}

/**
 * To select an autosuggest value while up/down arrow keys are being used
 * and place in the search box
 */
function setDisplay(cursor_pos, class_name)  {
    var astobj = document.getElementById("search-name");     
    astobj.value = document.getElementById(cursor_pos).innerHTML;
    document.getElementById(cursor_pos).className = class_name;
}

/**
 * To select a value onclick from the dropdownlist and place in the search box 
 */
function aslitem_click(liObj) {
    var results_dropdown = document.getElementById("aslist");
    var astobj = document.getElementById("search-name");
    astobj.value = liObj.innerHTML;
    results_dropdown.innerHTML = "";
    document.getElementById("asdropdown").style.visibility = "hidden";        
}

/**
 * To hanble the cursor hover
 */
function hover_display(liObj) {
    if (cursor_pos > -1) { 
        document.getElementById(cursor_pos).className = "unselected";
    }
    cursor_pos = liObj.id;                       
    liObj.className = "selected";
}
    
/**
 * Fetch words from the Trie and add to seachList with <li> </li> tags
 */
function getValues(trie_array, parent_word, max_display) {
    var list_string; 
    // Default to display top 6 words
    max_display = (typeof max_display == 'undefined') ?
        6 : max_display;
    // Default end_marker is ' '
    if (trie_array != null) {
        for (key in trie_array) {
            if (key != end_marker ) {
                getValues(trie_array[key], parent_word + key);
            } else {
                list_string = concat_term.trim() + " " + decode(parent_word);
                list_string = list_string.trim();
                search_list += "<li><span id=" + count + " class='unselected' onmouseover=hover_display(this) "; 
                search_list += "onclick=aslitem_click(this)>" + list_string + "</span></li>";
                count++;
            }
        }
    }
}

/**
 * Returns the sub trie_array under term in
 * trie_array. If term does not exist in the trie_array
 * returns false
 *
 * @param String term  what to look up
 * @return Array trie_array sub trie_array under term
 */
function exist(trie_array, term) {
    for(var i = 1; i< term.length; i++) {
        if(trie_array == null){
            return false;
        }
        if (trie_array != 'null') {
            tmp = getUnicodeCharAndNextOffset(term, i);
            if(tmp == false) return false;
            [next_char, i] = tmp;
            enc_char = encode(next_char);
            if(trie_array[enc_char] != 'null') {
                trie_array = trie_array[enc_char];
            }
        }
        else {
            return false;
        }
    }
    return trie_array;
}

/**
 * Entry point to find words for autosuggestion. Finds the portion of trie_aray
 * beneath term. Then using this subtrie get the first six entries. 
 * Six is specified in get values.
 *
 * @param Array trie_array - a nested array represent a trie
 * @param String term - what to look up suggestions for
 * @sideeffect global Array search_list has list of first six entries
 */
function autosuggest(trie_array, term) {
    //default end_marker is space
    end_marker = (typeof end_marker == 'undefined') ? " " : end_marker;
    last_word = false;
    count = 0;
    search_list = "";
    term = term.toLowerCase();
    var tmp;
    if(trie_array == null) {
        return false;
    }
    if((term.length) > 1) {
        tmp = getUnicodeCharAndNextOffset(term, 0);
        if(tmp == false) return false;
        [start_char, ] = tmp;
        enc_chr = encode(start_char);
        trie_array = exist(trie_array[enc_chr], term);

    } else {
        trie_array = trie_array[term];
    }
    getValues(trie_array, term);
}

/* wrappers to save typing */
function decode(str) {
    str = str.replace(/\+/g, '%20');
    return decodeURIComponent(str);
}
/* wrappers to save typing */
function encode(str)
{
    return encodeURIComponent(str);
}

/**
 * Extract next Unicode Char beginning at offset i in str returns Array
 * with this character and the next offset
 *
 * This is based on code found at:
 * https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects
 * /String/charAt
 * 
 * @param String str what to get the next character out of
 * @param int i current offset into str
 * @return Array pair of Unicode character beginning at i and the next offset
 */
function getUnicodeCharAndNextOffset(str, i) {
    var code = str.charCodeAt(i);
    if (isNaN(code)) {
        return '';
    }
    if (code < 0xD800 || code > 0xDFFF) {
        return [str.charAt(i), i];
    }
    if (0xD800 <= code && code <= 0xDBFF) {
        if (str.length <= i + 1) {
            return false;
        }  
        var next = str.charCodeAt(i + 1);
        if (0xDC00 > next || next > 0xDFFF) {
            return false;
        }
        return [str.charAt(i) + str.charAt(i + 1), i + 1];
    }
    if (i === 0) {
        return false;
    }
    var prev = str.charCodeAt(i-1);
    if (0xD800 > prev || prev > 0xDBFF) {
        return false;
    }
    return [str.charAt(i + 1), i + 1];
}

/**
 * Load the Trie(compressed with .gz extension) during the launch of website
 * Trie's are represented using nested arrays.
 */
function loadTrie() {
    var xmlhttp;
    if (window.XMLHttpRequest) {
        // code for IE7i+, Firefox, Chrome, Opera, Safari
        xmlhttp=new XMLHttpRequest();
    } else {
        // code for IE6, IE5
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            trie = JSON.parse(xmlhttp.responseText);
            dictionary = trie["trie_array"]
            end_marker = trie["end_marker"]
        }
    }
    locale = document.documentElement.lang
    if(locale) {
        trie_loc = "./?c=resource&a=suggest&locale="+locale;
        xmlhttp.open("GET", trie_loc, true);
        xmlhttp.send();
    }
}
document.getElementsByTagName("body")[0].onload = loadTrie;
