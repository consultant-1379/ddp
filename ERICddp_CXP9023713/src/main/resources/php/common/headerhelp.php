<?php

const RET_CNT_AS_STR = "ReturnContentAsString";

const HEADER_1 = 1;
const HEADER_2 = 2;
const HEADER_3 = 3;
const HEADER_4 = 4;

// In some cases where the functions.php has been directly included
// php_root is not set. In these cases, we don't need the
// HelpBubbles
if ( isset($php_root) ) {
    require_once "$php_root/classes/HelpBubbles.php";
}

/**
  * Outputs the HTML for a header.
  *
  * Example usage
  *  drawHeader("Full Syncs", HEADER_2, "fullsyncs");
  *
  * @see getHeader For how the args are used
  */
function drawHeader($headerText, $headerLevel, $id) {
    echo getHeader($headerText, $headerLevel, $id);
}

/**
  * Returns the HTML for a header.
  *
  *
  * Returns string containing the HTML to display a header. If there is a matching
  * help id (DDP_Bubble.<pagename>.<id>), the HTML will include the help bubble.
  *
  *
  * @param string $headerText  The content of the header
  * @param int    $headerLevel The level of the header, use one of the constants HEADER_1, etc
  * @param string $id          The id to be used for the header (for anchor links and help id)
  */
function getHeader($headerText, $headerLevel, $id) {
    $helpText = HelpBubbles::getInstance()->getHelp($id);
    if ( (!is_null($helpText)) && strpos($helpText, "ddp_instr_model=[") !== false ) {
        $helpText = _processModelTag($helpText);
    }

    return _getHeader($headerText, $headerLevel, $id, $helpText);
}

/**
 * Output or return HTML for a header
 *
 *
 * @deprecated Should not be called by new code
 */
function drawHeaderWithHelp(
    $headerText,
    $headerLevel,
    $label,
    $helpTextOrID = "",
    $returnAsStr = "",
    $extraLink = ""
) {
    if ( $helpTextOrID === "" ) {
        $headerWithHelp = getHeader($headerText, $headerLevel, $label);
    } else {
        if ( preg_match('/^DDP_Bubble/', $helpTextOrID) ) {
            $helpText = getHelpTextFromDB($helpTextOrID);
        } else {
            $helpText = $helpTextOrID;
        }
        if ( $extraLink === "" ) {
            $extraLink = null;
        }
        $headerWithHelp = _getHeader($headerText, $headerLevel, $label, $helpText, $extraLink);
    }

    if ( $returnAsStr === RET_CNT_AS_STR ) {
        return $headerWithHelp;
    } else {
        echo $headerWithHelp;
    }
}

/**
 * Output or return help link title.
 *
 *
 * @deprecated Should not be called by new code
 */
function drawHelpTitle($title, $returnAsStr = "") {
    $helpTitle = _getHelpTitleDiv($title);
    if ( $returnAsStr === RET_CNT_AS_STR ) {
        return $helpTitle;
    } else {
        echo $helpTitle;
    }
}

/**
 * Output or return help link.
 *
 *
 * @deprecated Should not be called by new code
 */
function drawHelpLink($targetDiv, $returnAsStr = "") {
    $helpLink = _getHelpLink($targetDiv);
    if ( $returnAsStr === RET_CNT_AS_STR ) {
        return $helpLink;
    } else {
        echo $helpLink;
    }
}

/**
 * Output or return the help content div
 *
 * @deprecated Should not be called by new code
 */
function drawHelp($targetDiv, $title, $text, $returnAsStr = "") {
    $help  = "<div id=\"div_" . $targetDiv . "\" class=\"helpbox\" helpClicked=false>\n";
    $help .= drawHelpTitle($title, RET_CNT_AS_STR);
    $help .= "<div class=helpbody>$text</div></div>\n";

    if ( $returnAsStr === RET_CNT_AS_STR ) {
        return $help;
    } else {
        echo $help;
    }
}

/**
 * Get the help text for the given id.
 *
 *
 * @deprecated Should not be called by new code
 */
function getHelpTextFromDB($textID) {
    $statsDB = new StatsDB();
    $sqlquery = "SELECT ddpadmin.help_bubble_texts.content FROM ddpadmin.help_bubble_texts WHERE
                 ddpadmin.help_bubble_texts.help_id = '$textID';";
    $row = $statsDB->queryRow($sqlquery);
    if ( $row[0] == "" ) {
        $row[0] = "Help description not found";
    }
    return $row[0];
}

//
// Internal functions only below this point, i.e. not to be used outside this file
//

/**
 * Returns the HTML for a header
 *
 *
 * This function is for internal use only and should not be called from
 * outside this file.
 */
function _getHeader($headerText, $headerLevel, $id, $helpText, $extraLink = null) { // NOSONAR
    $content = sprintf("<H%d id=\"%s\">%s", $headerLevel, $id, $headerText);
    if ( ! is_null($helpText) ) {
        $content .= _getHelpLink($id);
    }
    if ( ! is_null($extraLink) ) {
       $content .= '<span class = "header-extra-link">' . $extraLink . '</span>';
    }
    $content .= sprintf("</H%d>\n", $headerLevel);

    if ( ! is_null($helpText) ) {
        $content .= _getHelpContentDiv($headerText, $id, $helpText);
    }

    return $content;
}

/**
  * Returns the HTML for a help link bubble.
  *
  *
  * This function is for internal use only and should not be called from
  * outside this file.
  *
  *
  * @param string $id          The id to be used for the the anchor
  */
function _getHelpLink($id) { // NOSONAR
    global $php_webroot;

    $anchorName = $id . "_anchor";
    $targetDivId = "div_" . $id;
    return <<<EOF
<a name="$anchorName"></a>
<a href="#$anchorName" class=helplink
    onmouseover="return showHelp('$targetDivId', event);"
    onmouseout="return showHelp('$targetDivId', event);"
    onclick="return showHelp('$targetDivId', event);">
    <img border=0 src="$php_webroot/common/images/help.gif"/>
</a>
EOF;
}

/**
 * Returns the HTML for the help content div.
 *
 *
 * This function is for internal use only and should not be called from
 * outside this file.
 */
function _getHelpContentDiv($title, $id, $text) { // NOSONAR
    $helpTitle = _getHelpTitleDiv($title);
    return <<<EOT
<div id="div_$id" class="helpbox" helpClicked=false>
$helpTitle
 <div class=helpbody>
$text
 </div>
</div>
EOT;
}

/**
 * Returns the HTML for the help content div title.
 *
 *
 * This function is for internal use only and should not be called from
 * outside this file.
 */
function _getHelpTitleDiv($title) { // NOSONAR
    return <<<EOT
 <div class=helptitle>
  <div class=title>
   <b>$title</b>
  </div>
 </div>
EOT;
}

/**
 * Enrich the helpText using the model.
 *
 *
 * This function is for internal use only and should not be called from
 * outside this file.
 *
 * Replace the ddp_instr_model=[$model] tag with a link to the raw data.
 *
 * @param string $helpText The help text.
 */
function _processModelTag($helpText) { // NOSONAR
    global $datadir;

    $matches = array();
    if ( preg_match("/ddp_instr_model=\[([^\[]+)\]/", $helpText, $matches) ) {
        $model = $matches[1];
        $torServersDir = $datadir . "/tor_servers";
        if ( file_exists($torServersDir) ) {
            $rawLink = makeLink("/TOR/system/modelled_instr.php", "Raw Data", array('model' => $model));
        } else {
            $rawLink = "";
        }
        $helpText = str_replace("ddp_instr_model=[$model]", $rawLink, $helpText);
    }

    return $helpText;
}
