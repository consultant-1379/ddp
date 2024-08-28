var lunTableCol = Array();
var rgLunTableCol = Array();

var rgTableCol = Array();

var lunMainData = {};
var lunIoSizeData = Array();

var poolRgData = {};

function fmtLunName(elCell, oRecord, oColumn, oData) {
    var fullName = oRecord.getData("name");
    YAHOO.log("fullName=" + fullName);
    var indexOfBracket = fullName.indexOf("[")
    if ( indexOfBracket > -1 ) {
        var shortName = fullName.substr( 0, fullName.indexOf("[") - 1);
        YAHOO.log("shortName=" + shortName);
        elCell.innerHTML = shortName;
    } else {
        elCell.innerHTML = fullName;
    }
}

function fmtRgName(elCell, oRecord, oColumn, oData) {
    var fullName = oRecord.getData("name");
    YAHOO.log("fullName=" + fullName);
    var shortName = fullName.substr( fullName.lastIndexOf(" ") + 1 );
    YAHOO.log("shortName=" + shortName);
    elCell.innerHTML = shortName;
}

function fmtRg(elCell, oRecord, oColumn, oData) {
    var rgIdStr = oRecord.getData("rgid");
    YAHOO.log("rgIdStr=" + rgIdStr, "info", "fmtRg");    

    if ( rgIdStr.length > 0 ) {
	var rgData = rgIdStr.split(",");
	elCell.innerHTML = "<a href=\"" + selfURL + 
	    "&lunid=" + oRecord.getData("lunid") + 
	    "&rgid=" + rgData[0] + "&rgnum=" + rgData[1] +
	    "\">" + rgData[1] + "</a>";
    } else {
	elCell.innerHTML = "";
    }
}

function lunMainCtxMenuHdlr(p_sType, p_aArgs, p_myDataTable) {
    YAHOO.log("entered", "info", "lunMainCtxMenuHdlr");

    var task = p_aArgs[1];

    if( ! task )
        return;

    YAHOO.log("task.groupIndex=" + task.groupIndex  + ", task.index=" + task.index, "info", "lunMainCtxMenuHdlr");    
    var selRows = p_myDataTable.getSelectedRows();
    if ( selRows.length < 1 ) {
	YAHOO.log("invalid selected count for stat" + selRows.length, "warn", "lunMainCtxMenuHdlr");    
        return;
    }

    var selectedLunIds = Array();
    for ( var i = 0; i < selRows.length; i++ ) {
        var oRecord = p_myDataTable.getRecord(selRows[i]);  
        selectedLunIds.push( oRecord.getData('lunid') );
    }

    var url = selfURL;
    if ( task.groupIndex == 0 ) {
        var stat = lunTableCol[task.index].key;
	url = url +  "&plotluns=" + stat + "&lunids=" + selectedLunIds.join();
    } else {
	url = url +  "&showluns=" + selectedLunIds.join();
    }

    YAHOO.log("url=" + url, "info", "lunMainCtxMenuHdlr");
    window.open (url,'_self',false);
}

function rgCtxMenuHdlr(p_sType, p_aArgs, p_myDataTable) {
    YAHOO.log("entered", "info", "rgCtxMenuHdlr");

    var task = p_aArgs[1];

    if( ! task ) 
        return;

    YAHOO.log("task.groupIndex=" + task.groupIndex  + ", task.index=" + task.index, "info", "rgCtxMenuHdlr");    
    var selRows = p_myDataTable.getSelectedRows();
    if ( selRows.length < 1 ) {
	YAHOO.log("invalid selected count for stat" + selRows.length, "warn", "rgCtxMenuHdlr");    
        return;
    }

    var selectedRgIds = Array();
    for ( var i = 0; i < selRows.length; i++ ) {
        var oRecord = p_myDataTable.getRecord(selRows[i]);  
        selectedRgIds.push( oRecord.getData('rgid') );
    }

    var url = selfURL;
    if ( task.groupIndex == 0 ) {
        var stat = rgTableCol[task.index].key;
	url = url +  "&plotrgs=" + stat + "&rgids=" + selectedRgIds.join();
    } else {
	url = url +  "&showrgs=" + selectedRgIds.join();
    }

    YAHOO.log("url=" + url, "info", "rgCtxMenuHdlr");    
    window.open (url,'_self',false);
}

function makeRgMain(key) {
    var myDataSource = new YAHOO.util.DataSource(poolRgData[key]);
    myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
    var myColumnDefs = [ { key:"name", label:"Name", sortable:true, formatter:fmtRgName} ];
    for (var i = 0; i < rgTableCol.length; i++ ) {
        YAHOO.log("Adding " + rgTableCol[i].label);
        myColumnDefs.push( { key:rgTableCol[i].key,label:rgTableCol[i].label,sortable:true} );
    }

    var divName = "div_" + key + "_rg"
    var myDataTable = 
        new YAHOO.widget.DataTable(divName, 
                                   myColumnDefs, 
                                   myDataSource,
                                   { caption:"RAID Group Stats" }
                                  );
    myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow); 
    myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow); 
    myDataTable.subscribe("rowClickEvent", myDataTable.onEventSelectRow);

    var ctxMenu = new YAHOO.widget.ContextMenu(key + "_rgCtxMenu", {trigger:myDataTable.getTbodyEl()});
    
    var statsItems = Array();
    for (var i = 0; i < rgTableCol.length; i++ ) {
	statsItems.push( { text: rgTableCol[i].label } );
    }
    ctxMenu.addItems( [ statsItems, [ { text: "All" } ] ] );

    ctxMenu.render(divName);
    ctxMenu.clickEvent.subscribe(rgCtxMenuHdlr, myDataTable);    
}

function makeLunMain(key,hasRG) {
    var myDataSource = new YAHOO.util.DataSource(lunMainData[key]);
    myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
    
    var myColumnDefs = [ { key:"name", label:"Name", sortable:true, formatter:fmtLunName} ];
    if ( hasRG ) {
	myColumnDefs.push( { key:"rgid", label:"RAID Group", sortable:true, formatter:fmtRg } );
    }
    
    for (var i = 0; i < lunTableCol.length; i++ ) {
        YAHOO.log("Adding " + lunTableCol[i].label);
        myColumnDefs.push( { key:lunTableCol[i].key,label:lunTableCol[i].label,sortable:true} );
    }
    if ( hasRG ) {
	for (var i = 0; i < rgLunTableCol.length; i++ ) {
            YAHOO.log("Adding " + rgLunTableCol[i].label);
            myColumnDefs.push( { key:rgLunTableCol[i].key,label:rgLunTableCol[i].label,sortable:true} );
	}
    }
	
    

    var divName = "div_" + key + "_lunmain"
    var myDataTable = 
        new YAHOO.widget.DataTable(divName, 
                                   myColumnDefs, 
                                   myDataSource,
                                   { caption:"LUN Stats" }
                                  );
    myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow); 
    myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow); 
    myDataTable.subscribe("rowClickEvent", myDataTable.onEventSelectRow);   

    var ctxMenu = new YAHOO.widget.ContextMenu(key + "_lunMainCtxMenu", {trigger:myDataTable.getTbodyEl()});
    
    var statsItems = Array();
    for (var i = 0; i < lunTableCol.length; i++ ) {
	statsItems.push( { text: lunTableCol[i].label } );
    }
    if ( hasRG ) {
	for (var i = 0; i < rgLunTableCol.length; i++ ) {
	    statsItems.push( { text: rgLunTableCol[i].label } );
	}
    }
	
    ctxMenu.addItems( [ statsItems, [ { text: "All" } ] ] );

    ctxMenu.render(divName);
    ctxMenu.clickEvent.subscribe(lunMainCtxMenuHdlr, myDataTable);
}

function makeLunIoSize() {
    var readWrite = [ "Read", "Write" ];
    for ( var dataIndex = 0; dataIndex < lunIoSizeData.length; dataIndex++ ) {
	for ( var rwi = 0; rwi < readWrite.length; rwi++ ) {
	    var keyPrefix = readWrite[rwi].toLowerCase();
	    var values = lunIoSizeData[dataIndex][keyPrefix].split(",");
	    for ( var si = 0; si < lunIoSizes.length; si++ ) {
		lunIoSizeData[dataIndex][keyPrefix + "_" + lunIoSizes[si]] = values[si];
	    }
	}
    }
    YAHOO.log("lunIoSizeData" + YAHOO.lang.dump(lunIoSizeData),"info", "makeLunIoSize");


    var myDataSource = new YAHOO.util.DataSource(lunIoSizeData);
    myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
    
    var myColumnDefs = [ { key:"name", label:"Name", sortable:true, formatter:fmtLunName} ];
    for ( var rwi = 0; rwi < readWrite.length; rwi++ ) {
	var keyPrefix = readWrite[rwi].toLowerCase();
	var statCols = Array();
	for ( var si = 0; si < lunIoSizes.length; si++ ) {	    
	    var colKey = keyPrefix + "_" + lunIoSizes[si];
	    statCols.push( { key:colKey, label:lunIoSizes[si] + "+"} );
	}
	var colGrp = { label:readWrite[rwi], children:statCols };
	myColumnDefs.push(colGrp);
    }
    
    var myDataTable = 
        new YAHOO.widget.DataTable("luniosizediv", 
                                   myColumnDefs, 
                                   myDataSource,
                                   { caption:"LUN IO Size Distribution" }
                                  );
}

