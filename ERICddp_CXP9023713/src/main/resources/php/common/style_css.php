<?php

function matchValue($name, $value) {
    return (isset($_COOKIE[$name]) && $_COOKIE[$name] === $value) || // NOSONAR
        (isset($_GET[$name]) && $_GET[$name] === $value); // NOSONAR
}

if ( matchValue("menu", "hidden") ) {
    $content_marginleft = "10px";
    $cal_display = "none";
} else {
    $content_marginleft = "200px";
    $cal_display = "block";
}

if ( matchValue("notice", "hidden") ) {
    $notice_display = "none";
} else {
    $notice_display = "block";
}

header("content-type: text/css");

echo <<<EOT
:root {
  --notice-display: $notice_display;
  --cal-display: $cal_display;
  --content-marginleft: $content_marginleft;
}


EOT;

readfile('stylesheet.css'); // NOSONAR
