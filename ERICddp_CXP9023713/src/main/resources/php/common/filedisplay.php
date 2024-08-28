<?php
$UI = false;
$NOREDIR = true;

require_once "init.php";

// Used to display the contents of any file type in a html page
// Needed for 'Dynamic File Search' feature

function displayFileContents($fullPath, $maxAllowedSizeInMB) {
    $filePath = "";
    $fileContentStr = "";

    $filePath = $fullPath;
    if ( file_exists($filePath) ) {
        $downloadURL = getUrlForFile($filePath, true);
        $downloadLink = makeLinkForURL($downloadURL, "here");

        $fileName = basename($filePath);
        $sizeExceededAlert = "<b>Alert: Displaying only the first " . $maxAllowedSizeInMB .
                       "M size of the '" . $fileName . "' file due to its large size. Please click " .
                       $downloadLink . " to download the complete '" . $fileName .
                       "' file to your local system.</b><br><br>";
        if ( preg_match('/\.gz/', $filePath) ) {
            $gzFH = gzopen($filePath, "rb");
            $fileContentStr = gzread( $gzFH, ($maxAllowedSizeInMB * 1024 * 1024) );
            if ( ! gzeof($gzFH) ) {
                echo $sizeExceededAlert;
            }
            gzclose($gzFH);
        } else {
            $txtFH = fopen($filePath, "r"); // NOSONAR
            $fileContentStr = htmlentities( fread( $txtFH, ($maxAllowedSizeInMB * 1024 * 1024) ), ENT_SUBSTITUTE );
            if ( ! feof($txtFH) ) {
                echo $sizeExceededAlert;
            }
            fclose($txtFH);
        }
        echo "<pre>{$fileContentStr}</pre>";
    } else {
        echo "ERROR: File dose not exist";
    }
}

$fullPath = getPathFromArgs();
displayFileContents($fullPath, 8);
?>
