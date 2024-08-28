// A custom 'formatter' function to present 'YYYY-MM-DD hh:mm:ss' timestamps as
//  'hh:mm:ss' under YUI datatables
// Note: Formats the timestamps presented as hyperlinks as well
function ddpFormatTime(elCell, oRecord, oColumn, oData) {
    // If a value hasn't been provided for the column, display a blank
    if ( oData == null ) {
        elCell.innerHTML = "";
        return;
    }

    var timestampPatt = /\d{4,4}-\d{2,2}-\d{2,2} \d{2,2}:\d{2,2}:\d{2,2}/g;
    if ( timestampPatt.test(oData) ) {
        var hyperLinkPatt = /^(<a\s*href.*>)\s*(.*)\s*(<\/a>)/;
        if ( match = hyperLinkPatt.exec(oData) ) {
            elCell.innerHTML = match[1] + match[2].split(' ')[1] + match[3];
        } else {
            elCell.innerHTML = oData.split(' ')[1];
        }
    }
    else {
        elCell.innerHTML = oData;
    }
}

// A custom 'formatter' function to present a number as  hh:mm:ss.sss'
//  under YUI datatables
function ddpFormatMSec(elCell, oRecord, oColumn, oData) {
    // If a value hasn't been provided for the column, display a blank
    if ( oData == null ) {
        elCell.innerHTML = "";
        return;
    }

    var date = new Date(oData);
    elCell.innerHTML = date.toISOString().substring(11,23);

    var msPerDay = 24 * 60 * 60 * 1000;
    if ( oData > msPerDay ) {
        var days = Math.floor(oData/msPerDay)
        elCell.innerHTML = days + "d " + elCell.innerHTML
    }
}

// A custom 'formatter' function to separate thousand positions in a given number with
//  commas under YUI datatables
// Note: Formats the numbers presented as hyperlinks as well
function ddpFormatNumber(elCell, oRecord, oColumn, oData) {
    // If a value hasn't been provided for the column, display a blank
    if ( oData == null ) {
        elCell.innerHTML = "";
        return;
    }

    // Extract the value if the element is a hyperlink
    var isThisHyperlink = false;
    var hyperLinkPatt = /^(<a\s*href.*>)(.*)(<\/a>)/;
    var match = "";
    if ( match = hyperLinkPatt.exec(oData) ) {
        if ( isNaN(match[2]) ) {
            oData = match[2];
        }
        else {
            oData = Number(match[2]);
        }
        isThisHyperlink = true;
    }

    // Enter this block and format 'oData' only if it is a valid number
    if ( ! isNaN(oData) ) {
        var negNum = false;
        if ( oData < 0 ) {
            negNum = true;
        }
        var num = oData.toString();
        var decimalPos = num.lastIndexOf('.');
        if ( decimalPos < 0 ) {
            decimalPos = num.length;
        }
        // For a given num like '452953743.1740' start adding commas like ",743.1740",
        //  ",953,743.1740" & "452,953,743.1740"
        formattedNum = num.substring(decimalPos);
        var charCount = 0;
        for ( var i = decimalPos; i > 0; i-- ) {
            if ( (charCount%3) == 0 && i != decimalPos ) {
                if ( !negNum || ( negNum && i != 1 ) ) {
                    formattedNum = ',' + formattedNum;
                }
            }
            formattedNum = num.charAt(i-1) + formattedNum;
            charCount++;
        }
        oData = formattedNum;
    }

    if ( isThisHyperlink ) {
        elCell.innerHTML = match[1] + oData + match[3];
    }
    else {
        elCell.innerHTML = oData;
    }
}

function ddpFormatRollup(elCell, oRecord, oColumn, oData) {
    if ( oData == null ) {
        elCell.innerHTML = "Totals";
    } else {
        elCell.innerHTML = oData;
    }
}

function ddpFormatRollupTotals(elCell, oRecord, oColumn, oData) {
    if ( oRecord.getData("rollupcol") == null ) {
        elCell.innerHTML = "<b>Totals</b>";
    } else {
        elCell.innerHTML = oData;
    }
}

function ddpFormatRollupOther(elCell, oRecord, oColumn, oData) {
    if ( oRecord.getData("rollupcol") == null ) {
        elCell.innerHTML = "";
    } else {
        elCell.innerHTML = oData;
    }
}

// A custom 'sortFunction' to force sort the values under given YUI datatable's column
//  as numbers rather than as strings
// Note: Force sorts the numbers presented as hyperlinks as well
function forceSortAsNums(a, b, desc, field) {
    // Handle empty values
    if ( !YAHOO.lang.isValue(a) || a.getData(field) === "" ) {
        if ( !YAHOO.lang.isValue(b) || b.getData(field) === "" ) {
            return 0;
        } else {
            return (desc) ? 1 : -1;
        }
    }
    else if ( !YAHOO.lang.isValue(b) || b.getData(field) === "" ) {
        return (desc) ? -1 : 1;
    }

    // Implement custom numerical sort for the columns containing both numbers and strings
    var aValue = a.getData(field);
    var bValue = b.getData(field);

    // Extract the value if the element is a hyperlink
    var hyperLinkPatt = /^<a\s*href.*>(.*)<\/a>/;
    var match = "";
    if ( match = hyperLinkPatt.exec(aValue) ) {
        aValue = match[1];
    }
    if ( match = hyperLinkPatt.exec(bValue) ) {
        bValue = match[1];
    }

    aValue = Number(aValue);
    bValue = Number(bValue);

    if (isNaN(aValue) && isNaN(bValue)) {
        aValue = a.getData(field).toLowerCase();
        bValue = b.getData(field).toLowerCase();
    }
    else if (isNaN(aValue)) {
        return (desc) ? 1 : -1;
    }
    else if (isNaN(bValue)) {
        return (desc) ? -1 : 1;
    }

    if (aValue < bValue) {
        return (desc) ? 1 : -1;
    }
    else if (aValue > bValue) {
        return (desc) ? -1 : 1;
    }
    else {
        return 0;
    }
}

// A custom 'formatRow' to selectively color the first column of all rows in a given YUI
//  datatable either in 'Red' or 'Green' or 'Amber'
function formatRowWithColors(elTr, oRecord) {
    if ( /yui-span-green/.test( oRecord.getData('status') ) ) {
        YAHOO.util.Dom.addClass(elTr, 'health-in-green');
    }
    else if ( /yui-span-amber/.test( oRecord.getData('status') ) ) {
        YAHOO.util.Dom.addClass(elTr, 'health-in-amber');
    }
    else if ( /yui-span-red/.test( oRecord.getData('status') ) ) {
        YAHOO.util.Dom.addClass(elTr, 'health-in-red');
    }
    return true;
}

// Posts data by appending a form to the page and posting data via it.
function postByAppend(path, params) {
    var form = document.createElement("form");
    form.setAttribute("method", "post");
    form.setAttribute("action", path);

    for(var key in params) {
        if(params.hasOwnProperty(key)) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", params[key]);

            form.appendChild(hiddenField);
         }
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Posts table data to the specified page (currently exportTable.php) with the expectation of generating an excel.
function downloadExcel(e, params) {
    e.preventDefault();
    downloadURL = params.downloadURL;

    tableParamsJSON = JSON.stringify( params );
    postByAppend(downloadURL, {'tableParams' :tableParamsJSON});
}

// The master JS function that plugs-in all the table params coming from 'DDPTable.php'
//  into an YUI datatable object
function ddpShowTable(element,tableParam) {

    var tableColumnDefs = Array();
    var fieldsDefs = Array();
    for (var i = 0; i < tableParam.columns.length; i++ ) {
        if ( tableParam.columns[i].visible ) {
            var colDef = { key: tableParam.columns[i].key, label: tableParam.columns[i].label, sortable: true };
            if ( 'formatter' in tableParam.columns[i] ) {
                colDef.formatter = window[tableParam.columns[i].formatter];
            }
            if ( tableParam.columns[i].type == "int" || tableParam.columns[i].type == "float" || tableParam.columns[i].type == "real" ) {
                colDef.className = 'ddp_numeric';
            }
            if ( 'sortOptions' in tableParam.columns[i] ) {
                colDef.sortOptions = {};
                if ( 'defaultDir' in tableParam.columns[i].sortOptions ) {
                    colDef.sortOptions.defaultDir = YAHOO.widget.DataTable.CLASS_ASC;
                    if ( tableParam.columns[i].sortOptions.defaultDir.toUpperCase() == "DESC" ) {
                        colDef.sortOptions.defaultDir = YAHOO.widget.DataTable.CLASS_DESC;
                    }
                }
                if ( 'sortFunction' in tableParam.columns[i].sortOptions ) {
                    colDef.sortOptions.sortFunction = window[tableParam.columns[i].sortOptions.sortFunction];
                }
            }
            tableColumnDefs.push( colDef );
        }

        var fieldsDef = { key: tableParam.columns[i].key };
        if ( tableParam.columns[i].type == "int" || tableParam.columns[i].type == "float" || tableParam.columns[i].type == "real" ) {
            fieldsDef.parser = "number";
        }
        fieldsDefs.push( fieldsDef );
    }

    var config = {};

    if ( 'order' in tableParam ) {
        var sortDir = YAHOO.widget.DataTable.CLASS_ASC;
        if ( tableParam.order.dir == "desc" ) {
            sortDir = YAHOO.widget.DataTable.CLASS_DESC;
        }

        config.sortedBy = { key: tableParam.order.by, dir: sortDir };
    }

    if ( 'rowsPerPage' in tableParam ) {
        if ( 'rowsPerPageOptions' in tableParam ) {
            config.paginator = new YAHOO.widget.Paginator({
                rowsPerPage : tableParam.rowsPerPage,
                template: YAHOO.widget.Paginator.TEMPLATE_ROWS_PER_PAGE,
                rowsPerPageOptions: tableParam.rowsPerPageOptions
            });
        } else {
            config.paginator = new YAHOO.widget.Paginator({rowsPerPage : tableParam.rowsPerPage});
        }
    }

    var ds;
    if ( 'query' in tableParam ) {
        ds = new YAHOO.util.DataSource(tableParam.query.url + "?");
        ds.responseType = YAHOO.util.DataSource.TYPE_JSON;
        ds.responseSchema = {
            resultsList: "rows",
            fields: fieldsDefs,
            metaFields: {
                totalRecords: "totalRecords",
                startIndex: "startIndex"
            }
        };

        config.dynamicData = true;
        var myRequestArgsBuilder = function(oState, oSelf) {
            var args = [ "qid=" + tableParam.query.id ];
            var keyToBeSortedBy = "";

            if ( oState ) {
                if ( oState.pagination ) {
                    args.push("startIndex=" + oState.pagination.recordOffset);
                    args.push("results=" + oState.pagination.rowsPerPage);
                }

                if ( oState.sortedBy ) {
                    args.push("sort=" + oState.sortedBy.key);
                    if ( oState.sortedBy.dir === YAHOO.widget.DataTable.CLASS_DESC ) {
                        args.push("dir=DESC");
                    } else {
                        args.push("dir=ASC");
                    }
                    keyToBeSortedBy = oState.sortedBy.key;
                }
            } else {
                if ( 'rowsPerPage' in tableParam ) {
                    args.push("startIndex=0");
                    args.push("results=" + tableParam.rowsPerPage);
                }
                if ( 'order' in tableParam ) {
                    args.push("sort=" + tableParam.order.by);
                    args.push("dir=" + tableParam.order.dir);
                    keyToBeSortedBy = tableParam.order.by;
                }
            }

            if ( keyToBeSortedBy != "" ) {
                for (var i = 0; i < tableParam.columns.length; i++ ) {
                    if ( tableParam.columns[i].key == keyToBeSortedBy ) {
                        if ( 'sortOptions' in tableParam.columns[i] ) {
                            if ( 'sortFunction' in tableParam.columns[i].sortOptions ) {
                                args.push("sortfunction=" + tableParam.columns[i].sortOptions.sortFunction);
                            }
                        }
                        break;
                    }
                }
            }

            return args.join("&");
        };
        config.initialRequest = myRequestArgsBuilder();
        config.generateRequest = myRequestArgsBuilder;
    } else {
        ds = new YAHOO.util.DataSource(tableParam.data);
        ds.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
        ds.responseSchema = {
            fields: fieldsDefs
        };
    }

    if ( 'ctxMenu' in tableParam ) {
        if ( tableParam.ctxMenu.multi == false ) {
            config.selectionMode = "single";
        }
    }


    var dt = new YAHOO.widget.DataTable("tablediv_" + tableParam.name,
                                        tableColumnDefs,
                                        ds,
                                        config
                                       );

    if ( 'query' in tableParam ) {
        dt.handleDataReturnPayload = function (oRequest, oResponse, oPayload) {
            oPayload.totalRecords = tableParam.query.totalRows;
            return oPayload;
        }
    }

    if ( 'ctxMenu' in tableParam ) {
        dt.subscribe("rowMouseoverEvent", dt.onEventHighlightRow);
        dt.subscribe("rowMouseoutEvent", dt.onEventUnhighlightRow);
        dt.subscribe("rowClickEvent", dt.onEventSelectRow);

        var ctxMenu = new YAHOO.widget.ContextMenu(dt.getContainerEl().id + "_ctxMenu",
                                                   {trigger:dt.getTbodyEl()});

        function onMenuItemClick(p_sType, p_aArgs, p_oValue) {
            var ctxMenu = tableParam['ctxMenu']

            var selRows = dt.getSelectedRows();
            if ( selRows.length < 1 ) {
                YAHOO.log("invalid selected count for stat" + selRows.length, "warn", "onMenuItemClick");
                return;
            }

            var selectedItems = Array();
            var type = "";
            for ( var i = 0; i < selRows.length; i++ ) {
                var oRecord = dt.getRecord(selRows[i]);
                selectedItems.push(oRecord.getData(ctxMenu['col']));
            }

            var url = ctxMenu['url'] + "&" + ctxMenu['key'] + "=" + p_oValue + "&selected=" + selectedItems.join();
            YAHOO.log("url=" + url, "info", "onMenuItemClick");
            window.open (url,"_self",false);
        }

        for (var prop in tableParam['ctxMenu']['menu']) {
            ctxMenu.addItem({ text: tableParam['ctxMenu']['menu'][prop], onclick: { fn: onMenuItemClick, obj: prop } });
        }

        ctxMenu.render(dt.getContainerEl());
    }

    if ( 'downloadURL' in tableParam ) {
        function onExcelDownloadClick(p_sType, p_aArgs, p_oValue) {
            downloadExcel(p_aArgs[0], p_oValue);
        }

        var downloadText = "Download as Excel";
        if ( tableParam.hasOwnProperty('query') ) {
            downloadText = "Download as CSV";
        }

        var ctxMenu = new YAHOO.widget.ContextMenu(dt.getContainerEl().id + "_ctxMenuHead", {trigger:dt.getTheadEl()});
        ctxMenu.addItem( {text : downloadText, onclick: { fn: onExcelDownloadClick, obj : tableParam } });
        ctxMenu.render(dt.getContainerEl());
    }
}
