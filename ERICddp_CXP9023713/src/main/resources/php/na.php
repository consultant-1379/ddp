<?php
$agent = $_GET['agent'];
if ($agent == "int")
    $pageTitle = "Internal NA";
else if ($agent == "ext")
    $pageTitle = "External NA";
else
    $pageTitle = "Notification Agent";
include "common/init.php";

$page = $rootdir . "/" . $agent . "_na.html";
if ($debug != 0) echo "PAGE: " . $page . "<br />\n";

// Strip "<html>, <body> and </html>, </body> tags

if (file_exists($page)) {
    $info = file_get_contents($page);

    // Strip tags
    $unwantedTags = array(
        "<body>",
        "<html>",
        "</html>",
        "</body>"
    );

    foreach ($unwantedTags as $tag) {
        $info = str_ireplace($tag, "", $info);
    }
    echo $info;
}
include "common/finalise.php";
?>
