<?php

class FindFile {
    const SITE_MODE = 1;
    const DDP_MODE = 2;

    public function processRequest($mode) {
        $relpath = requestValue(RELPATH_PARAM);
        if ( is_null($relpath) ) {
            $this->displaySearchUI($mode);
        } else {
            $fullPath = getPathFromArgs();
            if ( is_file($fullPath) ) {
                $url = getUrlForFile($fullPath);
                if ( is_null(requestValue(GETFILE_PARAM)) ) {
                    echo makeLinkForURL($url, "File Link");
                } else {
                    header("Location:" . $url);
                }
            }
        }
    }

    private function displaySearchUI($mode) {
        global $webargs, $dir, $site, $date, $oss, $yuiDir, $debug, $rootdir, $datadir, $php_webroot;
        debugMsg("displaySearchUI: mode=$mode");

        if ( $mode == self::DDP_MODE ) {
            $searchDirs = array("log" => "/data/ddp/log", "upgrade" => "/data/ddp/upgrade");
        } else {
            $searchDirs = array("data" => $datadir, "analysis" => $rootdir);
        }

        $results = array();
        foreach ( $searchDirs as $type => $searchDir ) {
            $files = array();
            $this->getAllFiles($searchDir, $files);
            $removePrefix = $searchDir . "/";
            $relPaths = array();
            foreach ( $files as $file ) {
                $relpath = str_replace($removePrefix, "", $file);
                $relPaths[] = $relpath;
            }
            $results[] = array('type' => $type, 'relpaths' => $relPaths);
        }
        $resultsTxt = json_encode($results, JSON_PRETTY_PRINT);

        $localMode = "true";
        $thisPage = fromServer(PHP_SELF);
        drawHeaderWithHelp("File Search", 2, "DynamicSearchHelp", "DDP_Bubble_108_ENM_Health_Status_Dynamic_Search");
        echo <<<EOS

<div id="myAutoComplete" class="yui-skin-sam">
 <form id="myForm" method="POST" action="$thisPage">
  <input type="hidden" name="site" value="$site">
  <input type="hidden" name="date" value="$date">
  <input type="hidden" name="dir" value="$dir">
  <input type="hidden" name="oss" value="$oss">

  <input type="hidden" name="relpath" id="relpath">
  <input type="hidden" name="pathtype" id="pathtype">

  <input type="text"   id="file"  style="width:50em">
  <input type="submit" name="getfile" id="getfile"
         value="Get" disabled style="position:absolute; left:70em; margin-left:1em;">
  <input type="submit" name="getlink" id="getlink"
         value="Link" disabled style="position:absolute; left:77em; margin-left:1em;">
 </form>
 <div id="matchContainer"></div>
</div>

<br>
<br>

<script type="text/javascript" src="$php_webroot/classes/findfile.js"></script>

<script type="text/javascript">
var filelist = $resultsTxt;
setupAutoComplete($localMode);
</script>

EOS;
    }

    private function getAllFiles($dir, &$matches) {
        debugMsg("getAllFiles: dir=$dir");
        if ( (!is_dir($dir)) || (!is_readable($dir)) ) {
            return;
        }
        $entries = array_diff(scandir($dir), array('..', '.'));
        foreach ($entries as $entry) {
            $path = $dir . "/" . $entry;
            if (is_file($path)) {
                $matches[] = $path;
            } else {
                $this->getAllFiles($path, $matches);
            }
        }
    }
}

