<%@page import="com.ericsson.nms.ddp.report.data.TemplateList" %>
<%@page import="java.util.Iterator" %>
<%@page import="java.util.List" %>
<%
TemplateList personal = new TemplateList(request.getParameterMap(), request.getSession().getAttribute("authuser").toString());
TemplateList global = new TemplateList(request.getParameterMap());

%>
<h1>My Report Templates</h1>
<table border=1>
<tr><th>Template Name</th><th>Description</th><th></th><th></th><th></th><th></th></tr>
<%
%>
</table>