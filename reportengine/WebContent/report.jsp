<%@page import="com.ericsson.nms.ddp.report.db.DBHandle" %>
<%@page import="com.ericsson.nms.ddp.report.data.Report" %>
<%@page import="java.util.Enumeration" %>

<%
Report report = null;

if (request.getParameter("id") != null) {
    DBHandle.setDDPServer(request.getParameter("ddp"));
    DBHandle hdl = new DBHandle();;
    try {
        int tplId = Integer.parseInt(request.getParameter("id"));
        report = hdl.getReport(tplId, request.getParameterMap());
    } catch (NumberFormatException e) {
        report = new Report("Invalid template ID: " + request.getParameter("id"),
                "Please verify the data you provided and regenerate this report", request.getParameterMap());
    }
    
}
if (report == null)
    report = new Report("Could not generate report for ID " + request.getParameter("id"),
        "Please check supplied parameters and try again", request.getParameterMap());
String pageTitle = "DDP: " + report.getName();
%>
<%@include file="include/top.jsp" %>
<h1><%= report.getName() %></h1>
<table border=0 width=600>
<%
for (Iterator<String> i = report.getOverview().keySet().iterator() ; i.hasNext() ; ) {
    String key = i.next();
    String lbl = key;
    if (key.equals("id") || key.equals("submithtml") || key.equals("format")) continue;
    if (key.equals("start_time")) lbl = "Start Time";
    if (key.equals("end_time")) lbl = "End Time";
%>
<tr><td width=120><b><%= lbl %>&nbsp;</b></td><td class=val><%= report.getOverview().get(key) %></td></tr>
            
<%
}
%>
</table>
<%
for (Iterator<String> i = report.getTimeSeriesMap().keySet().iterator() ; i.hasNext() ; ) {
    String n = i.next();
    //response.getWriter().println(report.getTimeSeriesMap().get(n).getSql());
    Enumeration<String> en = request.getParameterNames();
    String qStr = "";
    while (en.hasMoreElements()) {
        String pName = en.nextElement();
        if (! qStr.equals("")) qStr += "&";
        if (pName.equals("format")) qStr += "format=png";
        else qStr += pName + "=" + request.getParameter(pName);
    }
%>
<br/><img src='rg?<%= qStr %>&ts=<%= n %>' /><br />
<%
}

%>
<%@include file="include/footer.jsp" %>