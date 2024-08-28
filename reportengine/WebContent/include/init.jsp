<%@page import="com.ericsson.nms.ddp.report.ui.Config"%>
<%@page import="com.ericsson.nms.ddp.report.ui.Auth" %>
<%@page import="java.util.Iterator" %>
<%@page import="java.util.List" %>

<%
Auth auth = new Auth(request, response);
auth.checkAuth();
request.getSession().setAttribute("authuser", auth.getUserName());
String pageName = request.getParameter("pg");
%>