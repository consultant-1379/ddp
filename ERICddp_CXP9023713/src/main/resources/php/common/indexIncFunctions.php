<?php
const VIEW_LIST = 'viewList';
const OTHER_ARGS = 'otherArgs';
const LABEL = 'label';
const QUERY = 'query';
const VIEW = 'view';
const PATH = 'path';
const START_TIME = 'startTime';
const SERVICE_GROUP = 'servicegroup';
const START = 'start';
const HTML = 'html';

function sortOnLabels($a, $b) {
    if ( isset($a[LABEL]) && isset($b[LABEL]) ) {
        return $a[LABEL]>$b[LABEL];
    }
}

//Takes an array of arrays. Each array contains the data needed to decide if a link is needed and
//the data to build the link.
//Can also take html code to be added directly to the list, using HTML as the key.
//Returns a list of html links.
function generateLinkList( $dataArray, $alphaSort = true ) {
    global $site, $date, $statsDB;
    if ( $alphaSort ) {
        usort($dataArray, "sortOnLabels");
    }
    foreach ($dataArray as $data) {
        $hasLink = false;
        if ( !empty( $data[HTML] ) ) {
            $list[] = $data[HTML];
        } elseif ( empty( $data[QUERY] ) ) {
            $hasLink = true;
        } else {
            foreach ( $data[QUERY] as $query ) {
                $row = $statsDB->queryRow($query);
                if ($row[0] > 0) {
                    $hasLink = true;
                    break;
                }
            }
        }
        generateLink( $hasLink, $data, $list );
    }

    if ( isset($list) ) {
        return $list;
    } else {
        return null;
    }
}

function generateLink( $hasLink, $data, &$list ) {
    if ( $hasLink ) {
        if ( isset( $data[OTHER_ARGS] ) ) {
            $list[] = makeLink( $data[PATH], $data[LABEL], $data[OTHER_ARGS] );
        } else {
            $list[] = makeLink( $data[PATH], $data[LABEL] );
        }
    }
}

//Code to generate each view.
//Is passed a list containing all the required links for the page and the data to generate the collapsable headings.
//It is also passed a reference to the content array which it adds the generated views to.
//Assumes there is a /php/$oss/index_inc.js file
function getViewHtml( $viewData, &$content ) {
    global $oss;
    $content[] = "\n\n<!--Start of View Tabs-->\n";
    foreach ( $viewData as $data ) {
        if ( !empty($data[VIEW_LIST]) ) {
            $html = <<<EOF
<!--{$data[VIEW]} Tab-->
<div style='margin-bottom:15px;margin-top:15px;'>
    <a id='collapseMenuTree{$data[VIEW]}' class='collapsableList'
    onclick='{$oss}MenuTreeCollaspe("collapseMenuTree{$data[VIEW]}","menuTree{$data[VIEW]}");'>&#43;</a>
    <b style='cursor: pointer;' onclick='{$oss}MenuTreeCollaspe("collapseMenuTree{$data[VIEW]}",
    "menuTree{$data[VIEW]}");'>{$data[LABEL]}</b>
</div>
<div style='display: none' id='menuTree{$data[VIEW]}'> \n
EOF;

            $content[] = $html;
            $content[] = makeHTMLList($data[VIEW_LIST]);
            $content[] = "\n</div>\n\n";
        }
    }
    $content[] = "<!--End of View Tabs-->\n\n\n";
}

// Get the link (if any) to the Grafana server
//
// If grafanaURL is set and this site is pushing data to the external store
// then return a link.
// The returned link depends on whether we're using OAuth2
// If we're using it we return a link to common/grafana.php which in turn will
// redirect to the Grafana server, passing the JWT
// If we're not using it, then just return the value of grafanaURL
function getGrafanaLink() {
    global $statsDB, $grafanaURL, $AdminDB, $useOAuth2, $php_webroot, $datadir, $webargs;

    if ( ! isset($grafanaURL) ) {
        return null;
    }


    if ( (! $statsDB->hasData("$AdminDB.external_store", 'date', true)) && (!is_dir($datadir . "/remote_writer")) ) {
        return null;
    }

    if ( isset($useOAuth2) && $useOAuth2 ) {
        $grafanaLink = $php_webroot . "/common/grafana.php?" . $webargs;
    } else {
        $grafanaLink = $grafanaURL;
    }

    return $grafanaLink;
}
