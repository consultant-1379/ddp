function setSubmitDisabled(disabled) {
    var getSubmit = YAHOO.util.Dom.get("getfile");
    getSubmit.disabled = disabled;
    var linkSubmit = YAHOO.util.Dom.get("getlink");
    linkSubmit.disabled = disabled;
}

function itemSelected(sType, aArgs) {
    // aArgs[0] reference back to the AC instance
    // aArgs[1]; // reference to the selected LI element
    var oData = aArgs[2]; // object literal of selected item's result data

    var relpathField = YAHOO.util.Dom.get("relpath");
    relpathField.value = oData[0];

    var typeField = YAHOO.util.Dom.get("pathtype");
    typeField.value = oData[1];

    setSubmitDisabled(false);
}

function filterResults(sQuery, oFullResponse, oParsedResponse, oCallback) {
    // Disable Get File button (will be enabled when user selects result)
    setSubmitDisabled(true);

    console.debug("filterResults: sQuery=" + sQuery);
    var regex = RegExp(unescape(sQuery), 'i');


    var filteredResults = [];
    for(var i=0; i<oParsedResponse.results.length; i++) {
        if ( regex.test(oParsedResponse.results[i].relpath) ) {
            filteredResults.push(oParsedResponse.results[i]);
        }
    }
    oParsedResponse.results = filteredResults;

    return oParsedResponse;
}

function dataReturned(sType, aArgs) {
    var oAutoComp = aArgs[0];
    var aResults = aArgs[2];

    if(aResults.length == 0) {
        oAutoComp.setBody("<div id=\"matchContainer\">No matching results</div>");
    }
}

function setupAutoComplete(localMode) {
    var ds = null;
    if ( localMode ) {
	var flatArray = [];
	for(var i=0; i<filelist.length; i++) {
	    for (var j=0; j<filelist[i].relpaths.length; j++) {
		flatArray.push([filelist[i].relpaths[j], filelist[i].type]);
	    }
	}
        ds = new YAHOO.util.LocalDataSource(flatArray);
        ds.responseSchema = {fields : ["relpath", "type"]};
    } else {
        ds = new YAHOO.util.DataSource("$baseURL&");
        ds.responseType = YAHOO.util.DataSource.TYPE_JSON;
        ds.responseSchema = {
            resultsList: "results"
        };
    }

    var myAutoComp = new YAHOO.widget.AutoComplete("file","matchContainer", ds);
    myAutoComp.minQueryLength = 2;
    myAutoComp.queryDelay = 0;
    myAutoComp.maxResultsDisplayed = 30;
    myAutoComp.itemSelectEvent.subscribe(itemSelected);
    myAutoComp.dataReturnEvent.subscribe(dataReturned);

    if ( localMode ) {
        myAutoComp.filterResults = filterResults;
    } else {
        myAutoComp.queryQuestionMark = false;
        myAutoComp.queryMatchContains = true;
    }
}
