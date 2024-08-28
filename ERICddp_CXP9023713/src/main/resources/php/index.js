
function menuTreeEnhance() {
    var treeElement = document.getElementById("menutree");
    if ( treeElement != null ) {
        var menuTree = new YAHOO.widget.TreeView("menutree");
        menuTree.render();
        menuTree.subscribe('dblClickEvent',menuTree.onEventEditNode);
    }
}
