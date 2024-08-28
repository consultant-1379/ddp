function setupMenu(myDataTable,menuItems,menuHandler) {
    YAHOO.log("myDataTable=" + myDataTable.getContainerEl().id, "info", "setupMenu");    

    myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow); 
    myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow); 
    myDataTable.subscribe("rowClickEvent", myDataTable.onEventSelectRow);

    var ctxMenu = new YAHOO.widget.ContextMenu(myDataTable.getContainerEl().id + "_ctxMenu",
                                               {
                                                   trigger: myDataTable.getTbodyEl(),
                                                   itemdata: menuItems
                                               });
    ctxMenu.render(myDataTable.getContainerEl());
    ctxMenu.clickEvent.subscribe(menuHandler, myDataTable);
}

function getSelected(p_myDataTable,column) {
    var selectedItems = Array();

    var selRows = p_myDataTable.getSelectedRows();
    if ( selRows.length > 0 ) {
        for ( var i = 0; i < selRows.length; i++ ) {
            var oRecord = p_myDataTable.getRecord(selRows[i]);
            selectedItems.push(oRecord.getData(column));
        }
    }

    return selectedItems;
}

function member_ctxMenuHdlr(p_sType, p_aArgs, p_myDataTable) {
    YAHOO.log("entered", "info", "member_ctxMenuHdlr");

    var task = p_aArgs[1];
    if( ! task ) 
        return;
    YAHOO.log("task.groupIndex=" + task.groupIndex  + ", task.index=" + task.index, "info", "member_ctxMenuHdlr");    

    selectedItems = getSelected(p_myDataTable,"server");
    if ( selectedItems.length == 0 ) {
        return;
    }

    url = selfURL + "&plots=members&servers=" + selectedItems.join();
    YAHOO.log("url=" + url, "info", "member_ctxMenuHdlr");    
    window.open (url,"_self",false);
}

function member_setupMenu(myDataTable) {
    YAHOO.log("myDataTable=" + myDataTable.getContainerEl().id, "info", "member_setupMenu");    

    setupMenu(myDataTable,[ "Plot" ], member_ctxMenuHdlr);
}
