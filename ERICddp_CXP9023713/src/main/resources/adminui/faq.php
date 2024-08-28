<?php
$pageTitle = "DDP FAQs";

include "init.php";
require_once 'HTML/Table.php';

function main() {
    $filename = "/data/ddp/current/help_content/FAQ.html";
    $faq = fopen($filename, "r") or die("Unable to open file!");
    echo stream_get_contents($faq);
    fclose($faq);
}

main();

include "../php/common/finalise.php";

