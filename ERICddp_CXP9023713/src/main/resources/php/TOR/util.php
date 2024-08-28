<?php

// Formats the product ver stored in tor_ver_names
// $input is the version string
// $incProdNum: If true, append the product number/rev
function formatTorVersion($input, $incProdNum=false) {
    $verString = $input;
    $verParts = explode(" ", $verString);
    if ( $verParts[0] != "NA" && count($verParts) == 6 ) {
        $verString = sprintf(
            "%s (%s)",
            $verParts[0],
            $verParts[1]
        );

        if ( $incProdNum ) {
            $verString = sprintf(
                "%s %s %s %s %s",
                $verString,
                $verParts[2],
                $verParts[3],
                $verParts[4],
                $verParts[5]
            );
        }
    }

    return  $verString;
}
