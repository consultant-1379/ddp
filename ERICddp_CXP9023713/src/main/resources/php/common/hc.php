<?php
$pageTitle = "Health Status";

include_once "./init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once "./rules.php";

const NO_BORDER = 'border=0';
const RULEDEF_KEY = '_ruleDef';
const DISPLAYID = 'displayId';

$RULES_DIR = dirname($php_root) . "/rules";
$ALERT_OFF = -1;
function getRuleDescrition($ruleInst,$ruleDef) {
    if ( property_exists($ruleInst,"desc") ) {
        $ruleDescription = (string)$ruleInst->desc;
    } else {
        $ruleDescription = (string)$ruleDef->desc;
    }
    return $ruleDescription;
}

function getDefaultRuleInstFile($oss) {
    $reportInstFile = "hc_" . $oss . ".xml";
    return $reportInstFile;
}

function getParamStr($param,$resultValueByName) {
    global $debug;
    if ( $debug > 4 ) { echo "<pre>getParamStr: param "; print_r($param); echo "</pre>\n"; }

    $str = (string)$param['name'] . "=";
    if ( isset($param['source']) ) {
        $sourceName = (string)$param['source'];
        $sourceValue = $resultValueByName[$sourceName];
        if ( $debug > 3 ) { echo "<pre>getParamStr: got $sourceValue for $sourceName</pre>\n"; }
        $str .= $sourceValue;
    } else {
        $str .= $param['value'];
    }
    return $str;
}

function makeHCLink($linkDef, $resultData) {
    global $php_webroot,$webargs,$debug;
    $url = $php_webroot . "/" . (string)$linkDef->page . "?" . $webargs;

    if ( property_exists($linkDef,"param") ) {
        $resultValueByName = array();
        foreach ( $resultData as $result) {
            $resultValueByName[$result['name']] = $result['value'];
        }
        if ( $debug > 3 ) { echo "<pre>makeHCLink linkDef "; print_r($linkDef); echo "</pre>\n"; }
        if ( $debug > 3 ) { echo "<pre>makeHCLink linkDef->param "; print_r($linkDef->param); echo "</pre>\n"; }

        foreach ($linkDef->param as $param) {
            $url .= "&" . getParamStr($param,$resultValueByName);
        }
    }

    if ( property_exists($linkDef,"anchor") ) {
        $url .= "#" . (string)$linkDef->anchor;
    }

    if ( $debug > 3 ) { echo "<pre>makeHCLink url=$url</pre>\n"; }
    return $url;
}

function getRuleHelp( $ruleResult, $ruleInst ) {
    global $debug;

    $ruleName = $ruleResult['rulename'];
    $ruleDefName = $ruleResult['ruledefname'];

    $helpText = getRuleHelpTextFromDB($ruleName, $ruleDefName);

    if ( count($ruleResult['thresholds']) > 0 ) {
        $helpText .= "<br>";
        $thresholdTable = new HTML_Table(NO_BORDER);
        $thresholdTable->addRow(array("Name","Type","Warning","Critical"),null,'th');
        foreach ($ruleResult['thresholds'] as $threshold ) {
            $thresholdName = $threshold['name'];
            $warnValue = array_key_exists('warn', $threshold) ? $threshold['warn'] : '';
            $criticalValue = array_key_exists('value', $threshold) ? $threshold['value'] : '';
            $thresholdTable->addRow(array($thresholdName,$threshold['type'],$warnValue,$criticalValue));

            # Substitute the placeholders in the help text with the threshold values.
            $strSubstitutions = array("%$thresholdName%" => $criticalValue, "%$thresholdName-warn%" => $warnValue);
            $helpText = strtr($helpText, $strSubstitutions);
        }
        $helpText .= "<H4>Healthy Thresholds</H4>\n";
        $helpText .= "<p>Health check fails and appears in the table if any results are outside the following defined 'Healthy Thresholds'.</p>";
        $helpText .= $thresholdTable->toHTML();
    }
    if ( count($ruleResult['parameters']) > 0 ) {
        $helpText .= "<br>";
        $parameterTable = new HTML_Table(NO_BORDER);
        $parameterTable->addRow(array("Name","Value"),null,'th');
        foreach ($ruleResult['parameters'] as $parameter ) {
            $parameterName = $parameter['name'];
            $parameterTable->addRow(array($parameterName,$parameter['value']));

            # Substitute the placeholders in the help text with the parameter values.
            $strSubstitutions = array("%$parameterName%" => $parameter['value']);
            $helpText = strtr($helpText, $strSubstitutions);
        }
        $helpText .= "<H4>Parameters</H4>\n";
        $helpText .= $parameterTable->toHTML();
    }

    if ( property_exists($ruleInst, "filter") ) {
        $helpText .= "<br>";
        $filterTable = new HTML_Table(NO_BORDER);
        $filterTable->addRow(array("Name", "Type", "Value"), null, 'th');
        foreach ( $ruleInst->filter->children() as $condition ) {
            $row = array(
                (string)$condition['name'],
                (string)$condition['type'],
                (string)$condition['value']
            );
            $filterTable->addRow($row);
        }
        $helpText .= "<H4>Filters</H4>\n";
        $operation = " " . (string)$ruleInst->filter['operation'] . " ";
        $operationUC = strtoupper($operation);
        $helpText .= "<p>The conditions below are '$operationUC-ed' together to filter the rows checked</p>";
        $helpText .= $filterTable->toHTML();
    }


    return $helpText;
}

function getRuleHelpString( $ruleResult, $ruleInst ) {
    $returnHelp = '';

    $helpText = getRuleHelp($ruleResult, $ruleInst);
    $ruleName = $ruleResult['rulename'];

    if ( strlen($helpText) > 0 ) {
        $returnHelp = drawHelpLink($ruleName . "_standalone_help", "ReturnContentAsString");
        $returnHelp .= drawHelp($ruleName . "_standalone_help", $ruleResult["_desc"], $helpText, "ReturnContentAsString");
    }

    return $returnHelp;
}

function drawRuleHeaderWithHelp( $ruleResult, $ruleInst, $ruleDef ) {
    $helpText = getRuleHelp($ruleResult, $ruleInst, $ruleDef);
    $ruleName = $ruleResult['rulename'];

    echo '<H2 id="' . $ruleName . '_anchor">' . $ruleResult["_desc"];
    if ( strlen($helpText) > 0 ) {
        drawHelpLink($ruleName . "_help");
    }
    echo "</H2>\n";
    if ( strlen($helpText) > 0 ) {
        drawHelp($ruleName . "_help", $ruleResult["_desc"], $helpText);
    }
}

function execRules( $oss, $site, $date, &$outText = NULL ) {
    global $debug;

    $rootDir = dirname(PHP_ROOT);
    $ruleDir = $rootDir . "/rules";
    $cmdStr = $ruleDir . "/executeRules";
    $cmdStr .= " --ruledefs " . $ruleDir . "/rules.xml";
    $cmdStr .= " --ruleinsts " . $ruleDir . "/hc_" . $oss . ".xml";
    $cmdStr .= " --site $site --date $date";
    $cmdStr .= " --noemail --format json";

    if ( $debug ) { echo "<pre>execRules: $cmdStr</pre>\n"; }

    $descriptorspec = array(
        0 => array("file", "/dev/null", "r"),
        1 => array("pipe", "w"),
        2 => array("file", "/tmp/err", "w")
    );
    $commonDir = dirname($ruleDir) . "/analysis/common";
    $env = array('PERL5OPT' => '-I' . $commonDir);

    $process = proc_open("$cmdStr", $descriptorspec, $pipes, NULL, $env);
    //echo "<pre>"; print_r($pipes); echo "</pre>\n";
    if ( is_resource($process) ) {
        while ($buffer = fgets($pipes[1], 1024) ) {
            if ( ! is_null($outText) ) {
                $outText .= $buffer;
            }
            $buffer = rtrim($buffer);
        }
        fclose($pipes[1]);
        return proc_close($process);
    } else {
        $error = error_get_last();
        if ( ! is_null($outText) ) {
            $outText .= $error;
        }
        return -1;
    }
}

function getReportResults($displayId) {
    global $site, $date, $AdminDB, $statsDB;

    $row = $statsDB->queryRow("
SELECT $AdminDB.healthcheck_results.data
FROM $AdminDB.healthcheck_results, sites
WHERE
  $AdminDB.healthcheck_results.siteid = sites.id AND sites.name = '$site' AND
  $AdminDB.healthcheck_results.date = '$date' AND
  $AdminDB.healthcheck_results.reportid = $displayId
");
    if ( is_null($row) ) {
        return null;
    } else {
        return json_decode($row[0], true);
    }
}

function getChildValue($parent,$tag) {
    $child = $parent->getElementsByTagName($tag)->item(0);
    if ( ! is_null($child) ) {
        return $child->nodeValue;
    } else {
        return NULL;
    }
}

function getCustomReport($defaultXmlStr, $customId) {
    global $AdminDB, $statsDB;

    $defaultDoc = new DOMDocument();
    $defaultDoc->loadXML($defaultXmlStr);
    $defaultReport = $defaultDoc->documentElement;

    $row = $statsDB->queryRow("SELECT content FROM $AdminDB.ddp_custom_reports WHERE id = $customId");
    $customDoc = new DOMDocument();
    $customDoc->loadXML($row[0]);
    $customReport = $customDoc->documentElement;

    $disabledRuleNames = array();
    foreach ( $customReport->getElementsByTagName("disabledrule") as $disabledrule ) {
        $disabledRuleNames[$disabledrule->nodeValue] = 1;
    }
    $customRulesByName = array();
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        $customRulesByName[getChildValue($ruleInst, "rulename")] = $ruleInst;
    }

    $ruleInstToRemove = array();
    $ruleInstToReplace = array();
    foreach ($defaultReport->getElementsByTagName("ruleinst") as $ruleInst) {
        $ruleName = getChildValue($ruleInst, "rulename");
        if ( array_key_exists($ruleName, $disabledRuleNames) ) {
            $ruleInstToRemove[$ruleName] = $ruleInst;
        } elseif ( array_key_exists( $ruleName, $customRulesByName) ) {
             $ruleInstToReplace[$ruleName] = $ruleInst;
        }
    }

    foreach ($ruleInstToRemove as $ruleInst) {
        $defaultReport->removeChild($ruleInst);
    }

    foreach ($ruleInstToReplace as $ruleInst) {
        $ruleName = getChildValue($ruleInst, "rulename");
        $importedRuleInst = $defaultDoc->importNode($customRulesByName[$ruleName], true);
        unset($customRulesByName[$ruleName]);
        $defaultReport->replaceChild($importedRuleInst, $ruleInstToReplace[$ruleName]);
    }

    // Add any custom rules that are not part of the standard healthcheck
    foreach ( $customRulesByName as $ruleInst ) {
        $defaultReport->appendChild($defaultDoc->importNode($ruleInst, true));
    }

    return $defaultDoc->saveXML();
}

function getWatchedReport($siteId, $username) {
    global $AdminDB, $statsDB;

    $row = $statsDB->queryRow("
SELECT reportid
FROM $AdminDB.ddp_alert_subscriptions
WHERE
  $AdminDB.ddp_alert_subscriptions.siteid= $siteId AND
  $AdminDB.ddp_alert_subscriptions.signum='$username'");
  if ( is_null($row) ) {
      return null;
  } else {
      return $row[0];
  }
}

function getDisplayReport($siteId, $username) {
    global $AdminDB, $statsDB;

    $row = $statsDB->queryRow("
SELECT reportid
FROM $AdminDB.ddp_report_display
WHERE
  $AdminDB.ddp_report_display.siteid= $siteId AND
  $AdminDB.ddp_report_display.signum='$username'");
  if ( is_null($row) ) {
      return null;
  } else {
      return $row[0];
  }
}

function getEmailFromLdap($ldap_conn,$user) {
    global $debug;

    $userInfo = getLdapUserInfo($ldap_conn,$user,array("mail"));
    if ( is_null($userInfo) ) {
        echo "<p>ERROR: No match found for ". $user . "</br>";
        return NULL;
    }
    $email = $userInfo['mail'];

    if ( $debug ) { echo "<pre>getEmailFromLdap: user=$user email=$email</pre>\n"; }

    return $email;
}

function connectResultsToRules($ruleResults,$ruleInstByName,$ruleDefByName) {
    global $debug;

    $updatedResults = array();

    foreach ( $ruleResults as $ruleResult ) {
        if ( $debug > 1 ) { echo "<pre>connectRulesToResults: ruleResult: "; print_r($ruleResult); echo "</pre>\n"; }

        # Get the corresponding ruleinst
        $ruleInstName = $ruleResult['rulename'];
        if ( $debug ) { echo "<pre>connectRulesToResults: ruleInstName=$ruleInstName\n"; }
        if ( ! array_key_exists($ruleInstName,$ruleInstByName) ) {
            if ( $debug ) { echo "<pre>WARN: connectRulesToResults: No ruleInst found for $ruleInstName</pre>\n"; }
            continue;
        }
        $ruleInst = $ruleInstByName[$ruleInstName];
        if ( $debug > 1 ) { echo "<pre>connectRulesToResults: ruleInst: "; print_r($ruleInst); echo "</pre>\n"; }

        # Get the corresponding ruledef
        if ( isset($ruleInst->ruledef) ) {
            $ruleDefName = (string)$ruleInst->ruledef;
        } else {
            $ruleDefName = $ruleInstName;
        }
        if ( $debug ) { echo "<pre>connectRulesToResults: ruleDefName="; print_r($ruleDefName); echo "</pre>\n"; }
        if ( ! array_key_exists( $ruleDefName, $ruleDefByName ) ) {
            if ( $debug ) { echo "<pre>WARN: connectRulesToResults: No ruleDef found for $ruleDefName</pre>\n"; }
            continue;
        }
        $ruleDef = $ruleDefByName[$ruleDefName];

        $ruleResult['_ruleInstDef'] = $ruleInst;
        $ruleResult['_ruleDefName'] = $ruleDefName;
        $ruleResult[RULEDEF_KEY] = $ruleDef;
        $ruleResult['_desc'] = getRuleDescrition($ruleInst,$ruleDef);
        $updatedResults[] = $ruleResult;
    }

    return $updatedResults;
}

function makeSummaryTable($ruleResults) {
    global $debug;

    $summaryTable = new HTML_Table(array("id" => "healthCheckTable"),'border=1');
    $summaryTable->addRow( array('Health Area'), array( 'class' => 'hcSummaryHeader' ), 'th' );
    $rowIndex = 1;
    foreach ( $ruleResults as $ruleResult ) {
        $ruleInstName = $ruleResult["rulename"];
        if ( $debug ) { echo "<pre>makeSummaryTable: ruleInstName=$ruleInstName</pre>\n"; }

        $ruleInst = $ruleResult["_ruleInstDef"];

        if ( $debug > 1) { echo "<pre>makeSummaryTable: ruleResult: "; print_r($ruleResult); echo "</pre>\n"; }

        if ( array_key_exists('failures', $ruleResult) ) {
            $cellContent = '<a href="#' . $ruleInstName . '_anchor">' . $ruleResult["_desc"] . '</a>';
            $colour = 'red';

            # Now check if the failures have severity indicators
            $isWarning = TRUE;
            foreach ( $ruleResult['failures'] as $failure ) {
                if ( $debug > 1 ) { echo "<pre>makeSummaryTable: checking severity for "; print_r($failure); echo "</pre>\n"; }
                if ( array_key_exists('threshold',$failure) ) {
                    foreach ( $failure['threshold'] as $threshold ) {
                        if ( $threshold['severity'] != 'warning' ) {
                            $isWarning = FALSE;
                        }
                    }
                } else {
                    $isWarning = FALSE;
                }
            }
            if ( $isWarning ) {
                $colour = 'orange';
            }
        } else {
            $cellContent = $ruleResult["_desc"];
            #$resultMsg = $noFaultsMsg;
            $colour = 'green';
        }
        $cellContent = '<span class="hcTitle">' . $cellContent . '</span>'  . getRuleHelpString($ruleResult, $ruleInst);
        $summaryTable->addRow( array($cellContent) );
        $summaryTable->setCellAttributes($rowIndex,0,array('bgcolor' => $colour, 'class' => 'hcSummaryRow'));
        $rowIndex++;
    }

    return $summaryTable;
}

function getHeaderDefFromColumns($ruleDef) {
    global $debug;

    $header = array();
    foreach ( $ruleDef->columns->column as $column ) {
        debugMsg("getHeaderDefFromColumns: column", $column);
        $headerDef = array( 'key' => (string)$column['name'], 'label' => (string)$column['label'] );
        if ( valueExists($column['type']) ) {
            $headerDef['type'] = (string)$column['type'];
        }
        if ( valueExists($column['formatter']) ) {
            $headerDef['formatter'] = (string)$column['formatter'];
        }
        if ( valueExists($column['defaultDir']) || valueExists($column['sortFunction']) ) {
            $headerDef['sortOptions'] = array();
            if ( valueExists($column['defaultDir']) ) {
                $headerDef['sortOptions']['defaultDir'] = (string)$column['defaultDir'];
            }
            if ( valueExists($column['sortFunction']) ) {
                $headerDef['sortOptions']['sortFunction'] = (string)$column['sortFunction'];
            }
        }
        $header[] = $headerDef;
    }

    return $header;
}

function getCreateLink($ruleDef) {
    global $debug;

    $createLink = array();

    foreach ( $ruleDef->columns->column as $column ) {
        debugMsg("getCreateLinks: column", $column);
        if ( property_exists($column,"link") ) {
            $columnName = (string)$column['name'];
            debugMsg("getCreateLinks: linkDef for $columnName", $column->link);
            $createLink[$columnName] = $column->link;
        }
    }

    return $createLink;
}

function outputRuleResult($ruleResult) {
    global $debug;

    if ( $debug ) { echo "<pre>outputRuleResult: ruleResult="; print_r($ruleResult); echo "</pre>\n"; }

    $ruleName = $ruleResult['rulename'];
    $ruleInst = $ruleResult["_ruleInstDef"];
    $ruleDef = $ruleResult[RULEDEF_KEY];
    if ( $debug > 1 ) { echo "<pre>outputRuleResult: ruleDef="; print_r($ruleDef); echo "</pre>\n"; }

    drawRuleHeaderWithHelp($ruleResult, $ruleInst, $ruleDef);

    # If the ruleDef has columns section then use that to
    # build the table header
    $header = NULL;
    $createLink = null;
    if ( property_exists($ruleDef,"columns") ) {
        $header = getHeaderDefFromColumns($ruleDef);
        $createLink = getCreateLink($ruleDef);
    }

    $issuesRows = array();
    $headerTypes = array();
    foreach ( $ruleResult['failures'] as $failure ) {
        if ( is_null($header) ) {
            $header = array();
            foreach ( $failure['resultdata'] as $result ) {
                $header[] = array( 'key' => $result['name'], 'label' => $result['name'] );
            }
        }
        $issueRow = array();
        foreach ( $failure['resultdata'] as $result ) {
            if ( array_key_exists($result['name'], $createLink) ) {
                $link = makeHCLink($createLink[$result['name']],$failure['resultdata']);
                $issueRow[$result['name']] = '<a href="' . $link . '">' . $result['value'] . "</a>";
            } else {
                $issueRow[$result['name']] = $result['value'];
            }

            # If the rules definition doesn't contain column/header info then get the datatype
            # of the column based on its values
            if ( ! array_key_exists($result['name'], $headerTypes) ) {
                $headerTypes[$result['name']] = array();
            }
            if ( is_numeric($result['value']) ) {
                $headerTypes[$result['name']]['number'] = 1;
            } else {
                $headerTypes[$result['name']]['string'] = 1;
            }
        }
        $issuesRows[] = $issueRow;
    }

    # Map the datatypes of the values to their columns in the absence of header info
    foreach ( $header as &$colDef ) {
        $haveTypeOrFormatter = array_key_exists('type', $colDef) || array_key_exists('formatter', $colDef);
        if ( isset($headerTypes[$colDef['key']]) ) {
            $formatCoumnAsNumber =
                                 array_key_exists('number', $headerTypes[$colDef['key']]) &&
                                 count($headerTypes[$colDef['key']]) === 1 &&
                                 (! array_key_exists($colDef['key'], $createLink));
        }
        if ( (!$haveTypeOrFormatter) && $formatCoumnAsNumber ) {
            $colDef['type'] = 'int';
            $colDef['formatter'] = 'ddpFormatNumber';
        }
    }

    $table = new DDPTable($ruleName . "_table",
                          $header,
                          array('data' => $issuesRows),
                          array('rowsPerPage' => 10, 'rowsPerPageOptions' => array(25, 50, 100))
    );
    echo $table->getTable();
}

function showAlertMeForm($siteId) {
    global $AdminDB, $auth_user, $oss, $ALERT_OFF, $site, $date, $statsDB;

    $availableReports = array('Off' => -1, 'Default' => 0);
    $displayReports = array('Default' => 0);
    $sitetype = strtoupper($oss);
    $statsDB->query("
SELECT
 reportname, id, signum
FROM
 $AdminDB.ddp_custom_reports
WHERE
 site_type = '$sitetype' AND
 (signum = '$auth_user' OR access = 'PUBLIC')
ORDER BY reportname");
    while ( $row = $statsDB->getNextRow()) {
        $label = $row[0] . ' [' . $row[2] . ']';
        $availableReports[$label] = $row[1];
    }

    $statsDB->query("
SELECT
 $AdminDB.ddp_custom_reports.reportname,
 $AdminDB.ddp_custom_reports.id,
 $AdminDB.ddp_custom_reports.signum
FROM
 $AdminDB.ddp_custom_reports,
 $AdminDB.healthcheck_results,
 sites
WHERE
 $AdminDB.ddp_custom_reports.site_type = '$sitetype' AND
 $AdminDB.healthcheck_results.reportid = $AdminDB.ddp_custom_reports.id AND
 $AdminDB.healthcheck_results.siteid = sites.id AND
 sites.name ='$site' AND
 $AdminDB.healthcheck_results.date = '$date' AND
 ($AdminDB.ddp_custom_reports.signum = '$auth_user' OR $AdminDB.ddp_custom_reports.access = 'PUBLIC')
ORDER BY $AdminDB.ddp_custom_reports.reportname");

    while ( $rowValue = $statsDB->getNextRow()) {
        $label = $rowValue[0] . ' [' . $rowValue[2] . ']';
        $displayReports[$label] = $rowValue[1];
    }

    $watchedReportId = getWatchedReport($siteId, $auth_user);
    if ( is_null($watchedReportId) ) {
        $watchedReportId = $ALERT_OFF;
    }

    $displayReportId = getDisplayReport($siteId, $auth_user);
    if ( is_null($displayReportId) ) {
        $displayReportId = 0;
    }

    //There are two inputs added here so when the alert me is checked a '1'
   //  will be passed and when unchecked a '0' will be passed.
    echo <<<EOT
<form name='saveAlert' method='POST'>
  <table style="border: none;">
   <tr>
    <td style="border: none;">
EOT;
    drawHeaderWithHelp('Alert Me', 3, 'AlertMeHelp');
    echo <<<EOT
    </td>
    <td style="border: none;">
     <select name="reportid" onchange="this.form.submit()">

EOT;
    foreach ($availableReports as $label => $id) {
        if ( $id == $watchedReportId ) {
            echo "   <option value=\"$id\" selected>$label</option>\n";
        } else {
            echo "   <option value=\"$id\">$label</option>\n";
        }
    }
    echo <<<EOT
    </select>
   </td>
  </tr>
  <tr>
   <td style="border: none;">
EOT;
   drawHeaderWithHelp('Report Display', 3, 'DisplayReportHelp');
   echo <<<EOT
   </td>
   <td style="border: none;">
    <select name="displayId" onchange="this.form.submit()">

EOT;
    foreach ($displayReports as $display => $did) {
        if ( $did == $displayReportId ) {
            assignRequestValue(DISPLAYID, $did);
            echo "   <option value=\"$did\" selected>$display</option>\n";
        } else {
            echo "   <option value=\"$did\">$display</option>\n";
        }
    }
    echo <<<EOT
    </select>
   </td>
  </tr>
 </table>
</form>
<br>
EOT;
}

function readXMLRules() {
    global $debug, $RULES_DIR;

    $rulesFile = $RULES_DIR . "/rules.xml";
    $xml = simplexml_load_file($rulesFile);
    if ( $debug > 3 ) {
       echo "<pre>mainFlow: rules xml\n";
       print_r($xml);
       echo "</pre>\n";
    }
    $ruleDefByNameValue = array();
    foreach ( $xml->rule as $ruleDef ) {
        $ruleDefByNameValue[(string)$ruleDef->name] = $ruleDef;
    }
    if ( $debug > 2 ) {
       echo "<pre>mainFlow: ruleDefByName";
       print_r($ruleDefByNameValue);
       echo "</pre>\n";
    }
    return $ruleDefByNameValue;

}

function readDefaultRule() {
    global $oss, $debug, $RULES_DIR;

    $report = $RULES_DIR . "/" . getDefaultRuleInstFile($oss);
    if ( $debug ) {
       echo "<pre>mainFlow: loading rule inst file \"$report\"</pre>\n";
    }
    $ruleInstContentValue = file_get_contents($report); //NOSONAR
    if ( $debug > 4) {
       echo "<pre>mainFlow: ruleInstContent\n";
       echo $ruleInstContentValue;
       echo "</pre>\n";
    }
    return $ruleInstContentValue;

}

function loadXMLParse($ruleInstContent, $ruleDefByName, $displayId) {
    global $debug;

    $xml = simplexml_load_string($ruleInstContent);

    if ( $debug > 3 ) {
       echo "<pre>mainFlow: report xml\n";
       print_r($xml);
       echo "</pre>\n";
    }
    $ruleInstByName = array();
    foreach ( $xml->ruleinst as $ruleInst ) {
        $ruleInstByName[(string)$ruleInst->rulename] = $ruleInst;
    }
    if ( $debug > 2 ) {
       echo "<pre>mainFlow: ruleInstByName";
       print_r($ruleInstByName);
       echo "</pre>\n";
    }

    $ruleResults = getReportResults($displayId);
    if ( $debug > 2 ) {
       echo "<pre>mainFlow: ruleResults\n";
       print_r($ruleResults);
       echo "</pre>\n";
    }

    if ( is_null($ruleResults) ) {
        echo "<H1>ERROR: No data found for this date</H1>\n";
        return;
    }

    $ruleResults = connectResultsToRules($ruleResults, $ruleInstByName, $ruleDefByName);
    $summaryTable = makeSummaryTable($ruleResults);
    echo $summaryTable->toHTML();

    foreach ( $ruleResults as $ruleResult ) {
        if ( ! array_key_exists('failures', $ruleResult) ) {
            continue;
        }

        outputRuleResult($ruleResult);
    }
}

function mainFlow($statsDB) {
    global $site;

    $siteId = getSiteId($statsDB, $site);

    drawHeaderWithHelp("Health Summary", 2, "HealthSummaryHelp");
    showAlertMeForm($siteId);

    $ruleDefByName = readXMLRules();
    $ruleInstContent = readDefaultRule();

    $displayId = 0;
    $isvalueset = requestValue(DISPLAYID);

    if ( isset($isvalueset) && $isvalueset >0 ) {
        $displayId = $isvalueset;
        $ruleInstContent = getCustomReport($ruleInstContent, $isvalueset);
    }
    loadXMLParse($ruleInstContent, $ruleDefByName, $displayId);
}

$editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
$siteId = getSiteId($editDB, $site);

$isreportid = requestValue('reportid');
if ( isset($isreportid) ) {
    $reportId = $isreportid;
    if ( $debug ) {
       echo "<pre>reportId=$reportId</pre>\n";
    }

    // Remove any existin$report subscription for the user for this site
    $editDB->exec("use $AdminDB");
    $deleteSiteWatcher = "DELETE FROM ddp_alert_subscriptions WHERE siteid = '$siteId' AND signum = '$auth_user'";
    $editDB->exec($deleteSiteWatcher);

    if ( $reportId != $ALERT_OFF ) {
        // Add the user as a watcher for this site
        $insertSiteWatcher = "INSERT INTO ddp_alert_subscriptions (siteid, signum, reportid)
                             VALUES ('$siteId', '$auth_user', '$reportId')";
        $editDB->exec($insertSiteWatcher);

        //If we already have an email for the signum dont add to db
        $hasEmail = $editDB->queryRow("SELECT COUNT(*) FROM ddp_alert_subscriber_emails where signum = '$auth_user'");
        if ( $hasEmail[0] == '0' ) {
            // Add the user's email
            $ldap_conn = getLdapConn();
            $email = getEmailFromLdap($ldap_conn, $auth_user);

            if ( ! is_null($email) ) {
                $addEmailSQL = "INSERT INTO ddp_alert_subscriber_emails (signum, email) VALUES('$auth_user', '$email')";
                $editDB->exec($addEmailSQL);
            } else {
                echo "ERROR: An error occurred subscription not added. Please Try Again!";
            }
        }
    } else {
        //If user unsubscribes from all alert mes then remove email from db
        $subCount = $editDB->queryRow("SELECT COUNT(*) FROM ddp_alert_subscriptions WHERE signum = '$auth_user'");
        if ( $subCount[0] == '0' ) {
            $deleteEmailSQL = "DELETE FROM ddp_alert_subscriber_emails WHERE signum = '$auth_user'";
            $editDB->exec($deleteEmailSQL);
        }
    }
}

$isdisplayId = requestValue(DISPLAYID);
if ( isset($isdisplayId ) ) {
    $displayId = $isdisplayId;
    if ( $debug ) {
       echo "<pre>displayId=$displayId</pre>\n";
    }

    $editDB->exec("use $AdminDB");
    $deletedispalyWatcher = "DELETE FROM ddp_report_display WHERE siteid = '$siteId' AND signum = '$auth_user'";
    $editDB->exec($deletedispalyWatcher);

    // Add the user as a watcher for this site
    $insertDisplayWatcher = "INSERT INTO ddp_report_display (siteid, signum, reportid)
                            VALUES ('$siteId', '$auth_user', '$displayId')";
    $editDB->exec($insertDisplayWatcher);
}

$statsDB = new StatsDB();
mainFlow($statsDB);

$URI = fromServer('REQUEST_URI');
if ( preg_match("/reportid=/", $URI )) {
    $uri_array = preg_split('/&reportid=/', $URI);
    echo "<script type='text/javascript'> window.location.href='$uri_array[0]'; </script>";
}

echo addLineBreak(10);
include_once PHP_ROOT . "/common/finalise.php";
