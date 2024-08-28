<?php
$pageTitle = "Graphs";

$height = "600";
if ( isset($_GET["full"]) ) {
  $height = "100%";
  $UI = false;
}
include "common/init.php";

$dir=$_GET["dir"];
$file=$_GET["file"];

$target="data";
if ( isset($_GET["target"]) ) {
  $target = $_GET["target"];
}
$plotdata = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER["HTTP_HOST"] . "/" . $oss . "/" . $site . "/" . $target . "/" . $dir . "/" . $file;


$plotDir = $php_webroot . "/../plot";
?>

<html>
<body>

<?php

if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') ) {
?>
<OBJECT 
  classid="clsid:8AD9C840-044E-11D1-B3E9-00805F499D93"
  width="100%" height="<?=$height?>">
  <PARAM name="code" value="Plot">
  <PARAM name="codebase" value="<?=$plotDir?>">
  <PARAM name="archive" value="jfreechart-1.0.13.jar,jcommon-1.0.16.jar,Plot.jar">
  <PARAM name="data" value="<?=$plotdata?>">
</OBJECT>
<?php
    } 
 else {
?>

<applet code="Plot" 
		codebase="<?=$plotDir?>"
		archive="jfreechart-1.0.13.jar,jcommon-1.0.16.jar,Plot.jar"
        width="100%" height="<?=$height?>">
    <param name="data" value="<?=$plotdata?>">

Your browser is completely ignoring the &lt;APPLET&gt; tag!
</applet>
<?php
							 }
?>

</body>
</html>
