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
 * @copyright 2009 - 2012s
 * @filesource
 */

/**
 * Contains javascripts used to edit Crawl Mixes
 * A crawl mix consists of a sequence of groups. Each group
 * represents a number of search results to be presented. The
 * sources of these search results is the contents of the group.
 * These sources are a weighted sum of individual crawls and the
 * edit crawl mix page allows you to create both groups and select
 * which individuals crawls they contain. 
 */
/*
 * Used to draw all of the list of groups of crawl results for the
 * current crawl mix
 */
function drawGroups() 
{
    var gcnt = 0;
    for(key in groups) {
        var group = groups[key];
        drawGroup(gcnt, group['num_results']);
        var rcnt = 0;
        for(var ckey in group['components']) {
            var comp = group['components'][ckey];
            drawCrawl(gcnt, rcnt, comp[0], comp[1], comp[2], comp[3]);
            rcnt++;
        }
        gcnt++;
    }
}

/*
 * Used to erase the current rendering of crawl grouls and then draw it again
 */
function redrawGroups()
{
    var mts = elt("mix-tables");
    mts.innerHTML = "";
    drawGroups();
}
/*
 * Adds a crawl group to the end of the list of crawl groups.
 *
 * @param int num_results the number of results the crawl group should be used 
 *      for
 */
function addGroup(num_results) 
{

    num_groups = groups.length;
    groups[num_groups] ={};
    groups[num_groups]['num_results'] = num_results;
    groups[num_groups]['components'] = [];
    drawGroup(num_groups, num_results)
}

/*
 * Draws a single crawl group within the crawl mix
 *
 * @param int group_num the index of group to draw
 * @param int num_results the number of results to this crawl group
 */
function drawGroup(group_num, num_results) 
{
    var mts = elt("mix-tables");
    var tbl = document.createElement("table");
    tbl.id = "mix-table-"+group_num;
    tbl.className = "mixestable topmargin";
    makeBlankMixTable(tbl, group_num, num_results);
    mts.appendChild(tbl);
    addCrawlHandler(group_num);
}

/*
 * Draw a blank crawl mix group, without the Javascript functions attached to it
 *
 * @param Object tbl the table object to store blank mix table in
 * @param int num_groups which group this table will be
 * @param int num_results number of results this crawl group will be used for 
 */
function makeBlankMixTable(tbl, num_groups, num_results)
{
    var tdata = "<tr><td colspan=\"2\"><label for=\"add-crawls-"+num_groups+
        "\">"+tl['editmix_element_add_crawls']+"</label>"+ 
        drawCrawlSelect(num_groups)+"</td><td><label for=\"num-results-"+
        num_groups+"\">"+tl['editmix_element_num_results']+"</label>"+
        drawNumResultSelect(num_groups, num_results)+
            "<td><a href=\"javascript:removeGroup("+num_groups+")\">"+
            tl['editmix_element_del_grp']+'</a></td></tr>'+
            "<tr><th>"+tl['editmix_element_weight']+'</th>'+
            "<th>"+tl['editmix_element_name']+'</th>'+
            "<th>"+tl['editmix_add_keywords']+'</th>'+
            "<th>"+tl['editmix_element_actions']+"</th></tr>";
    tbl.innerHTML = tdata;
}

/*
 * Removes the ith group from the current crawl mix and redraws the screen
 *
 * @param int i index of group to delete 
 */
function removeGroup(i) 
{
    num_groups = groups.length;
    for(j = i+1; j < num_groups; j++) {
        groups[j - 1] = groups[j];
    }
    delete groups[num_groups - 1];
    groups.length--;
    redrawGroups();
}


/*
 * Adds the javascript needed to handle adding a crawl when the crawl
 * selection done
 * 
 * @param int i the group to add the Javascript handler for
 */
function addCrawlHandler(i)
{
    elt("add-crawls-"+i).onchange = 
        function () { 
            var  ac = elt("add-crawls-"+i);
            var sel = ac.selectedIndex;
            var name = ac.options[sel].text;
            var ts = ac.options[sel].value;
            ac.selectedIndex = 0;
            addCrawl(i, ts, name, 1, "");
        }
}

/*
 * Adds a crawl to the given crawl group with the listed parameters
 *
 * @param int i crawl group to add to 
 * @param int ts timestamp of crawl that is being added
 * @param String name name of crawl
 * @param float weight the crawl should ahve within group
 * @param String keywords  words to add to search when using this crawl
 */
function addCrawl(i, ts, name, weight, keywords) 
{
    var grp = groups[i]['components'];
    var j = grp.length;
    groups[i]['components'][j] = [ts, name, weight, keywords];
    drawCrawl(i, j, ts, name, weight, keywords)
}

/*
 * Draws a single crawl within a crawl group according to the passed parameters
 *
 * @param int i crawl group to draw to
 * @param int j index of crawl that is being added
 * @param int ts timestamp of crawl that is being drawn
 * @param String name name of crawl
 * @param float weight the crawl should ahve within group
 * 
 */
function drawCrawl(i, j, ts, name, weight, keywords) 
{
    var tr =document.createElement("tr");
    tr.id = i+"-"+j;
    elt("mix-table-"+i).appendChild(tr);
    tr.innerHTML += 
        "<td>"+drawWeightSelect(i, j, weight)+"</td><td>"+name+
        "</td><td><input type='hidden' name= \"mix[GROUPS]["+i+
        "][COMPONENTS]["+j+"][CRAWL_TIMESTAMP]\"' value=\""+ts+"\" />"+
        "<input title=\""+tl['editmix_add_query']+"\" "+
        "name=\"mix[GROUPS]["+i+"][COMPONENTS]["+j+"][KEYWORDS]\" "+
        "value=\""+ keywords+"\" onchange=\"updateKeywords("+i+","+j+
        ", this.value)\""+
        "class=\"widefield\"/></td><td><a href=\""+
        "javascript:removeCrawl("+i+", "+j+");\">"+
        tl['editmix_element_delete']+"</a></td>";
}

/*
 * Used to update the keywords of a crawl in the groups array whenever it is
 * changed in the form.
 *
 * @param int i group to update keywords in
 * @param int j crawl within group to update
 * @param String keywords the new keywords
 */
function updateKeywords(i, j, keywords)
{
    groups[i]['components'][j][3] = keywords;

}

/*
 * Deletes the jth crawl from the ith group in the current crawl mix
 *
 * @param int i group to delete crawl from
 * @param int j index of the crawl within the group to delete
 */
function removeCrawl(i, j) 
{

    var grp = groups[i]['components'];
    var len = grp.length;
    for( k = j + 1; k < len; k++) {
        grp[k-1] = grp[k];
    }
    delete grp[len - 1];

    redrawGroups();
}


/*
 * Used to draw the select drop down to allow users to select a weighting of
 * a given crawl within a crawl group
 *
 * @param int i which crawl group the crawl belongs to
 * @param int j which crawl index within the group to draw this weight select 
 *      for
 * @param int selected_weight the originally selected weight value
 */
function drawWeightSelect(i, j, selected_weight) {
    var weights = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1,
        2, 3, 4, 5, 6, 7, 8, 9, 10];
    var select = 
        "<select name=\'mix[GROUPS]["+i+"][COMPONENTS]["+j+"][WEIGHT]\'>";
    for ( wt in weights) {
        if(weights[wt] == selected_weight) {
            val = weights[wt] + "\' selected=\'selected";
        } else {
            val = weights[wt];
        }
        select += "<option value=\'"+val+"\'>" +
            weights[wt]+"</option>";
    }
    select += "</select>";
    return select;
}

/*
 * Used to draw the select drop down to allow users to select a crawl to be
 * added to a crawl group
 *
 * @param int i which crawl group to draw this for
 */
function drawCrawlSelect(i) {
    select = "<select id=\'add-crawls-"+i+"\' name=\'add_crawls_"+i+"\'>";
    for ( var crawl in c) {
        val = c[crawl];
        if(crawl == 0) {
            val = "0\' selected=\'selected";
        }
        select += "<option value=\'"+crawl+"\'>" + c[crawl] + "</option>";
    }
    select += "</select>";
    return select;
}

/*
 * Used to draw the select drop down to allow users to select the number
 * results a crawl group will be used for
 *
 * @param int i which crawl group this selection drop down is for
 * @param int selected_num what number of results should be initially selected
 */
function drawNumResultSelect(i, selected_num) {
    var num_results = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 30, 40, 50, 100];

    var select = "<select id=\'num-results-"+i+ 
        "\' name=\'mix[GROUPS]["+i+"][RESULT_BOUND]\'>";
    for ( nr in num_results) {
        if(num_results[nr] == selected_num) {
            val = num_results[nr] + "\' selected=\'selected";
        } else {
            val = num_results[nr];
        }
        select += "<option value=\'"+val+"\'>" +
            num_results[nr]+"</option>";
    }
    select += "</select>";
    return select;
}
