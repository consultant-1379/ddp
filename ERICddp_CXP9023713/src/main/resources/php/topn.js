var procTableCols = [ {
    key : "cpu",
    label : "CPU Time"
}, {
    key : "cpurate",
    label : "Avg. CPU/Sec"
}, {
    key : "mem",
    label : "Memory (MB)"
}, {
    key : "rss",
    label : "RSS (MB)"
}, {
    key : "thr",
    label : "Threads"
}, {
    key : "fd",
    label : "File Descriptors"
} ];

var procTableEnmCols = [ {
    key : "cpu",
    label : "CPU Time"
}, {
    key : "cpurate",
    label : "Avg. CPU/Sec"
}, {
    key : "rss",
    label : "RSS (MB)"
}, {
    key : "thr",
    label : "Threads"
}, {
    key : "fd",
    label : "File Descriptors"
} ];

YAHOO.log("ie=" + YAHOO.env.ua.ie);

function procTableCtxMenuHdlr(p_sType, p_aArgs, p_myDataTable) {
    YAHOO.log("entered", "info", "procTableCtxMenuHdlr");

    var task = p_aArgs[1];

    if (!task)
        return;

    YAHOO.log("task.groupIndex=" + task.groupIndex + ", task.index=" + task.index, "info", "procTableCtxMenuHdlr");

    var selRows = p_myDataTable.getSelectedRows();
    if (selRows.length < 1) {
        YAHOO.log("invalid selected count for stat" + selRows.length, "warn", "procTableCtxMenuHdlr");
        return;
    }

    var selectedLunIds = Array();
    for (var i = 0; i < selRows.length; i++) {
        var oRecord = p_myDataTable.getRecord(selRows[i]);
        selectedLunIds.push(oRecord.getData('procid'));
    }

    var url = selfURL;
    if (task.groupIndex == 0) {
        var stat = procTableColumns[task.index].key;
        url = url + "&plot=procs&stat=" + stat + "&procids=" + selectedLunIds.join();
    } else {
        url = url + "&plotallprocs=" + selectedLunIds.join();
    }

    YAHOO.log("url=" + url, "info", "procTableCtxMenuHdlr");
    window.open(url, '_self', false);
}

function procTableOnDataReturn(oArgs) {
    YAHOO.log("oArgs=" + YAHOO.lang.dump(oArgs), "info", "procTableOnDataReturn");

    var procType = oArgs.response.meta.procType;
    for (var i = 0; i < oArgs.response.results.length; i++) {
        var id = oArgs.response.results[i]["procid"];
        var name = procsByType[procType][id];
        YAHOO.log("id=" + id + ", name=" + name, "info", "procTableOnDataReturn");
        oArgs.response.results[i]["name"] = name;
    }
}

function procTableCtxMenuHdlr(p_sType, p_aArgs, p_myDataTable) {
    YAHOO.log("entered", "info", "procTableCtxMenuHdlr");

    var task = p_aArgs[1];

    if (!task)
        return;

    YAHOO.log("task.groupIndex=" + task.groupIndex + ", task.index=" + task.index, "info", "procTableCtxMenuHdlr");

    var selRows = p_myDataTable.getSelectedRows();
    if (selRows.length < 1) {
        YAHOO.log("invalid selected count for stat" + selRows.length, "warn", "procTableCtxMenuHdlr");
        return;
    }

    var selectedIds = Array();
    for (var i = 0; i < selRows.length; i++) {
        var oRecord = p_myDataTable.getRecord(selRows[i]);
        selectedIds.push(oRecord.getData('procid'));
    }

    var url = selfURL + "&procids=" + selectedIds.join();
    if (task.groupIndex == 0) {
        var stat = procTableCols[task.index].key;
        url = url + "&plot=" + stat;
    } else {
        url = url + "&plotall=1"
    }

    YAHOO.log("url=" + url, "info", "procTableCtxMenuHdlr");
    window.open(url, '_self', false);
}

function makeProcTable() {
    var responseFields = [ {
        key : "procid",
        parser : "number"
    }, {
        key : "procname"
    } ];

    if ( ossTypeVar == "tor" ) {
        procTableCols = procTableEnmCols;
    }

    for (var i = 0; i < procTableCols.length; i++) {
        responseFields.push({
            key : procTableCols[i].key,
            parser : "number"
        });
    }
    var ds = new YAHOO.util.DataSource(selfURL + "&getdata=procs");
    ds.responseType = YAHOO.util.DataSource.TYPE_JSON;
    ds.responseSchema = {
        resultsList : "result",
        fields : responseFields
    };

    var myColumnDefs = [ {
        key : "procname",
        label : "Process Name",
        sortable : true
    } ];
    for (var i = 0; i < procTableCols.length; i++) {
        myColumnDefs.push({
            key : procTableCols[i].key,
            label : procTableCols[i].label,
            sortable : true
        });
    }

    var oConfigs = {
        paginator : new YAHOO.widget.Paginator({
            rowsPerPage : 25,
            template : YAHOO.widget.Paginator.TEMPLATE_ROWS_PER_PAGE,
            rowsPerPageOptions : [ 25, 1000, 5000 ]
        }),
        sortedBy : {
            key : 'cpu',
            dir : 'desc'
        }
    };
    var myDataTable = new YAHOO.widget.DataTable("procdiv", myColumnDefs, ds, oConfigs);

    myDataTable.subscribe("rowMouseoverEvent", myDataTable.onEventHighlightRow);
    myDataTable.subscribe("rowMouseoutEvent", myDataTable.onEventUnhighlightRow);
    myDataTable.subscribe("rowClickEvent", myDataTable.onEventSelectRow);

    var statsItems = Array();
    for (var i = 0; i < procTableCols.length; i++) {
        statsItems.push(procTableCols[i].label);
    }
    var ctxMenu = new YAHOO.widget.ContextMenu("procCtxMenu", {
        trigger : myDataTable.getTbodyEl()
    });
    ctxMenu.addItems([ statsItems, [ {
        text : "All"
    } ] ]);
    ctxMenu.render("procdiv");
    ctxMenu.clickEvent.subscribe(procTableCtxMenuHdlr, myDataTable);
}

