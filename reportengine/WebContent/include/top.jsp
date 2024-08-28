<%@include file="init.jsp"%>
<%@ page language="java" contentType="text/html; charset=ISO-8859-1"
	pageEncoding="ISO-8859-1"%>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<link rel="stylesheet" type="text/css" href="include/style.css" />

<script type="text/javascript"
	src="<%=Config.getYuiUrl()%>/yahoo-dom-event/yahoo-dom-event.js"></script>
<script type="text/javascript"
	src="<%=Config.getYuiUrl()%>/container/container_core.js"></script>
<script type="text/javascript"
	src="<%=Config.getYuiUrl()%>/menu/menu.js"></script>
	
<script type="text/javascript" src="<%=Config.getYuiUrl()%>/dragdrop/dragdrop-min.js"></script>
<script type="text/javascript" src="<%=Config.getYuiUrl()%>/container/container-min.js"></script>

<title><%= pageTitle %></title>
</head>
<body>
    <!--
	<div id=header>
		<div id="topmenu" class="ddpmenu">
			<div id="navigation-menu" class="ddpmenubar ddpmenubarnav">
				<div class="bd">
					<ul class="first-of-type">
						<li class="ddpmenubaritem first-of-type"><a
							class="ddpmenubaritemlabel" href="#">Templates</a>
							<div id="menu" class="ddpmenu">
								<div class="bd">
									<ul>
										<li class="ddpmenuitem"><a class="ddpmenuitemlabel"
											href="?pg=editTemplates">Edit Templates</a></li>
										<li class="ddpmenuitem"><a class="ddpmenuitemlabel"
											href="#" onclick="YAHOO.ddp.containers.selecttemplate.show(YAHOO.ddp.containers.selecttemplate, true)">Select a Template</a></li>
										<li class="ddpmenuitem"><a class="ddpmenuitemlabel"
											href="#">Upload a new template</a></li>
									</ul>
								</div>
							</div></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
    -->

	<script type="text/javascript">
		/*
		 Initialize and render the MenuBar when its elements are ready 
		 to be scripted.
		 */

		YAHOO.util.Event.onContentReady("navigation-menu", function() {

			/*
			Instantiate a MenuBar:  The first argument passed to the constructor
			is the id for the Menu element to be created, the second is an 
			object literal of configuration properties.
			 */

			var oMenuBar = new YAHOO.widget.MenuBar("navigation-menu", {
				autosubmenudisplay : true,
				hidedelay : 750,
				lazyload : true
			});

			/*
			Call the "render" method with no arguments since the 
			markup for this MenuBar instance is already exists in 
			the page.
			 */

			oMenuBar.render();
		});
	</script>
	
	<div id="content">