var menuTreeSystemView; var menuTreePlatform; var menuTreeCmServ; var menuTreeCmMed; var menuTreePm; var menuTreeShm;
var menuTreeSmrs; var menuTreeFm; var menuTreeSecurity; var menuTreeDatabases; var menuTreeHw; var menuTreeRVInfo;
var menuTreeNCM;

function torMenuTreeEnhance() {
    var torTreeElement = document.getElementById("torMenuTree");
    var divList = torTreeElement.getElementsByTagName("div");
    for (var i = 0; i < divList.length; i++) {
        var divId = divList[i].id;
        if ( divId.startsWith("menuTree") ) {
            var menutree = new YAHOO.widget.TreeView(divId);
            menutree.render();
            menutree.subscribe('dblClickEvent', menutree.onEventEditNode);
        }
    }

    var cookieSplit = document.cookie.split("; ");
    console.log(cookieSplit);

    var menutreeNamesObj = {"menuTreeSystemView"   : "collapseMenuTreeSystemView",
                            "menuTreeCmServ"       : "collapseMenuTreeCmServ",
                            "menuTreeCmMed"        : "collapseMenuTreeCmMed",
                            "menuTreeNCM"          : "collapseMenuTreeNCM",
                            "menuTreePm"           : "collapseMenuTreePm",
                            "menuTreeShm"          : "collapseMenuTreeShm",
                            "menuTreeSmrs"         : "collapseMenuTreeSmrs",
                            "menuTreeFm"           : "collapseMenuTreeFm",
                            "menuTreeSecurity"     : "collapseMenuTreeSecurity",
                            "menuTreePlatform"     : "collapseMenuTreePlatform",
                            "menuTreeDatabases"    : "collapseMenuTreeDatabases",
                            "menuTreeHw"           : "collapseMenuTreeHw",
                            "menuTreeRVInfo"       : "collapseMenuTreeRVInfo"
                            };

    for (var ci = 0; ci < cookieSplit.length; ci++) {
        var matches = cookieSplit[ci].match(/^\s*(.*)\s*=\s*(.*)\s*$/);
        if (matches != null) {
            var menutreeName = matches[1];
            if (menutreeName in menutreeNamesObj && document.getElementById(menutreeName) != null) {
                document.getElementById(menutreeName).style.display = "block";
                document.getElementById(menutreeNamesObj[menutreeName]).innerHTML = matches[2];
            }
        }
    }
}

// Function collapse(obj,id) takes the html object, and the heading id's of the menu.
// If the menu is collapsed close it and change the symbol to a '-', delete the cookie
// If the menu is closed collapse it and change the symbol to a '+', create a cookie that saves the menu choice.

// Function getCookie() returns all cookies, if one of those cookies match the id's of the menu collapse the appropriate menu header.
function torMenuTreeCollaspe(obj,id) {
    var element = document.getElementById(id);
    var object = document.getElementById(obj);

    if (element.style.display != "none"){
        element.style.display = "none";
        object.innerHTML="&#43;"
        document.cookie = id + "=; expires=Thu, 01 Jan 1970 00:00:01 GMT;";
    }
    else {
        var cookieExpiryDate = new Date();
        //add 4 years
        cookieExpiryDate.setYear(cookieExpiryDate.getFullYear() + 4);
        element.style.display = "block";
        object.innerHTML="&#8722;"
        document.cookie = id + "=&#8722; expires=" + cookieExpiryDate + ";";
    }
}

