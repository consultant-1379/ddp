function showElement(el) {
    if (document.getElementById) {
        var elStyle = document.getElementById(el).style;
        if (elStyle.display == "none") {
            elStyle.display = "block";
        } else {
            elStyle.display = "none";
        }
        return false;
    } else {
        return true;
    }
}

function setCookie(c_name,value,expiredays)
{
    var exdate=new Date();
    exdate.setDate(exdate.getDate() + expiredays);
    document.cookie=c_name+ "=" + escape(value) + ";path=/" +
        ((expiredays == null) ? "" : ";expires=" + exdate.toGMTString());
}

function getCookie(c_name)
{
    if (document.cookie.length>0) {
        c_start=document.cookie.indexOf(c_name + "=");
        if (c_start!=-1) {
            c_start=c_start + c_name.length+1;
            c_end=document.cookie.indexOf(";",c_start);
            if (c_end==-1) c_end=document.cookie.length;
            return unescape(document.cookie.substring(c_start,c_end));
        }
    }
    return "";
}

function hideMenu() {
    if (document.getElementById) {
        var calStyle = document.getElementById("cal").style;
        var contentStyle = document.getElementById("content").style;
        if (calStyle.display == "none") {
            calStyle.display = "block";
            contentStyle.marginLeft = "200px";
            setCookie("menu","visible",365);
        } else {
            calStyle.display = "none";
            contentStyle.marginLeft = "10px";
            setCookie("menu","hidden",365);
        }
        return false;
    }
    return true;
}

function checkShowMenu() {
    var menuStatus = getCookie("menu");
    if (menuStatus == "hidden") {
        hideMenu();
    }
}
function showHelp(el, event) {
    //get mouse event for all browser type
    var event = window.event || event;
    if (document.getElementById) {
        var element = document.getElementById(el);
        if (element.style.display == "block" ) {
            //If mouse is clicked, check if - it is already clicked, and bubble is open then close - otherwise do nothing
            if(event.type == 'click'){
                if(element.helpClicked == "true"){
                    element.style.display = "none";
                    element.helpClicked="false";
                }else{
                    element.helpClicked="true";
                }
            }else if(element.helpClicked != "true" && (event.type == 'mouseout')){
               //It is not mouse click, it is mouse in or out. Hide only if mouse is going out not coming over.
               element.style.display = "none";
            }
        }
        else {
            //Show if mouse is not going out - Means mouse hover.
            if(event.type != 'mouseout'){
                if(event.type == 'click'){
                    element.helpClicked="true";
                }

            //Get scrollTop positon for all browsers types
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            element.style.top = (scrollTop + event.clientY + 20) + 'px';
            element.style.left = (event.clientX + 20) + 'px';
            element.style.display = "block";
            }
        }
        return false;
    } else {
        return true;
    }
}

function changeURL(form, baseUrl, prefix) {
    for (var i = 0 ; i < form.elements.length ; i++) {
        if (prefix != null) name = prefix + "_" + form.elements[i].name;
        else name = form.elements[i].name;
        baseUrl = baseUrl + "&" + name + "=" + form.elements[i].value;
    }
    window.location = baseUrl;
}

function popupWindow(url, name, width, height) {
    window.open(url, name,
            'height=' + height +
            ',width=' + width +
            ',scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes'
            );
}

/*
 * These functions use jQuery to draw JavaScript graphs.
 * $ is an alias for jQuery, and the jQuery string can be substituted
 * at will.
 */
function getOverview(data) {
    var d = data;
    var overview = $.plot($("#overview"), d, {
        series: {
            stack: true,
            lines: { show: true, lineWidth: 1, fill: true },
            shadowSize: 0
        },
        xaxis: { mode: "time" },
        yaxis: { ticks: [], min: 0, autoscaleMargin: 1 },
        selection: { mode: "x" }
    });
    return overview;
}

function getGraph (data, gName, stacked, overview) {
    var d = data;

    var options = {
        xaxis: { mode: "time" },
        yaxis: { min: 0, autoscaleMargin: 0.1 },
        selection: { mode: "x" },
        series: {
            stack: stacked,
            lines: { show: true, lineWidth: 1, fill: true },
            shadowSize: 2
        }
    };

    var plot = $.plot($("#" + gName), d, options);

    // handle zooming
    if (overview == null) {
        // just zoom ourselves
        $("#" + gName).bind("plotselected", function (event, ranges) {
            // do the zooming
            plot = $.plot($("#" + gName), d,
                              $.extend(true, {}, options, {
                              xaxis: { min: ranges.xaxis.from, max: ranges.xaxis.to }
                          }));
        });
    } else {
        // Attach to the overview to handle resizing if present
        $("#" + gName).bind("plotselected", function (event, ranges) {
            // do the zooming
            plot = $.plot($("#" + gName), d,
                              $.extend(true, {}, options, {
                              xaxis: { min: ranges.xaxis.from, max: ranges.xaxis.to }
                          }));
            // don't fire event on the overview to prevent eternal loop
            overview.setSelection(ranges, true);
        });

        $("#overview").bind("plotselected", function (event, ranges) {
            plot.setSelection(ranges);
        });
    }
}
