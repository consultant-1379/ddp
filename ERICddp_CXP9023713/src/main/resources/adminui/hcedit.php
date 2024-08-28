<?php
$pageTitle = "Custom Health Check Editor";

$NOREDIR = true; // prevents redirection due to lack of site

const EDIT_REPORT_FORM = 'editreport';
const BTN_EXPORT= 'btnExport';

$UI = true;
if ( $_SERVER["REQUEST_METHOD"] === "POST" && //NOSONAR
     $_REQUEST["form"] === EDIT_REPORT_FORM &&
     isset($_REQUEST[BTN_EXPORT]) ) {
    $UI = false;
}

include "init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/common/rules.php";

const EDIT_RULE_INST_FORM = 'editruleinst';
const MAX_LENGTH = 'maxlength';
const ACCESS = 'access';
const NON_BREAKING_SPACE = '&nbsp;';
const LABEL = 'label';
const FILTER = 'filter';
const OPERATION = 'operation';
const TITLE = 'title';
const ACTIVE_ID = 'activeid';
const DISABLED_ID = 'disabledid';
const SELECT_ELEMENT = 'select';
const STYLE = 'style';
const REPORT_IDS = 'reportids';
const RULE_NAME = 'rulename';
const REP_NAME = 'reportname';
const CLIENT = 'client';

$EMPTY_REPORT = "<?xml version='1.0'?><report/>";
$RULES_DIR = dirname($php_root) . "/rules";

function getFilterableColumns($ruleDef) {
    $column_list = array();
    foreach ( $ruleDef->getElementsByTagName("columns") as $columns ) {
        foreach ( $columns->getElementsByTagName("column") as $column ) {
            if ( $column->hasAttribute("filterable") && $column->getAttribute("filterable") === "true" ) {
                $column_list[$column->getAttribute("name")] = $column->getAttribute(LABEL);
            }
        }
    }

    return $column_list;
}

function getChildValue( $parent, $tag ) {
    $child = null;
    if ( !is_null($parent) ) {
        $child = $parent->getElementsByTagName($tag)->item(0);
    }
    if ( ! is_null($child) ) {
        return $child->nodeValue;
    } else {
        return NULL;
    }
}

function getRuleDef( $ruleDefXml, $ruleid ) {
    foreach($ruleDefXml->getElementsByTagName("rule") as $ruleDef) {
        if ( getChildValue($ruleDef,"name") ==  $ruleid ) {
            return $ruleDef;
        }
    }

    return NULL;
}

function getHcReport($site_type) {
    global $RULES_DIR;

    $hc_file = $RULES_DIR . "/hc_" . strtolower($site_type) . ".xml";
    $hcXmlStr = file_get_contents($hc_file);
    return loadRuleInst($hcXmlStr);
}

function getInstValue( $ruleInst, $type, $name ) {
    if ( $type == "parameter" ) {
        foreach ( $ruleInstXml->ruleinst[$instIndex]->parameter as $param ) {
            if ( (string)$param['name'] == $name ) {
                return (string)$param['value'];
            }
        }
    } else if ( $type == "threshold" ) {
        foreach ( $ruleInst->getElementsByTagName("threshold") as $thresInst ) {
            if ( $thresInst->getAttribute('name') === $name ) {
                return $thresInst->getAttribute('value');
            }
        }
    }

    return NULL;
}

function loadRuleInst($reportContent) {
    global $debug;

    if ( $debug > 2 ) { echo "<pre>loadRuleInst: reportContent="; print_r($reportContent); echo "</pre>\n"; }

    $xmlDoc = new DOMDocument();
    $xmlDoc->loadXML($reportContent);
    return $xmlDoc;
}

function saveRuleInst($editDB,$reportId,$customReportDoc) {
    global $debug, $RULES_DIR;

    if ( ! $customReportDoc->schemaValidate($RULES_DIR . "/report.xsd") ) {
        echo "<b>ERROR: Save failed as report is invalid</b>";
        exit(1);
    }

    $customReportDoc->formatOutput = true;

    $reportContent = $editDB->escape($customReportDoc->saveXML());
    if ( $debug > 1 ) { echo "<pre>saveRuleInst: reportId=$reportId reportContent=$reportContent</pre>\n"; }

    $updateSql = sprintf("UPDATE ddp_custom_reports SET content = '%s' WHERE id = %d",
                         $reportContent, $reportId );

    $editDB->exec($updateSql);
}

function loadRuleDef() {
    global $debug, $php_root, $RULES_DIR;

    $rulesFile = $RULES_DIR . '/rules.xml';
    if ( $debug ) { echo "<pre>loadRuleDef: rulesFile=$rulesFile</pre>\n"; }
    $xmlDoc = new DOMDocument();
    $xmlDoc->load($rulesFile);

    if ( $debug > 2 ) { echo "<pre>loadRuleDef: xmlDoc"; print_r($xmlDoc); echo "</pre>\n"; }
    return $xmlDoc;
}

function getDisplayThresholds($ruleInst, $ruleDef) {
    $displayThresholds = array();
    foreach ( $ruleInst->getElementsByTagName("threshold") as $thres ) {
        $compareType = "<";
        foreach ( $ruleDef->getElementsByTagName("threshold") as $thresDef ) {
            if ( $thresDef->getAttribute('name') == $thres->getAttribute('name') ) {
                if ( $thresDef->getAttribute('type') == 'greater' ) {
                    $compareType = ">";
                }
                $displayThresholds[] = $thres->getAttribute('name') . $compareType . $thres->getAttribute('value');
            }
        }
    }

    return $displayThresholds;
}

function getDisplayFilters($filter) {
    $operation = " " . $filter->getAttribute(OPERATION) . " ";
    $conditions = array();
    foreach ( $filter->getElementsByTagName("condition") as $condition ) {
        $conditions[] = $condition->getAttribute("name") . " " . $condition->getAttribute("type") .
                      " " . $condition->getAttribute("value");
    }
    return " [Filters: " . implode($operation, $conditions) . "]";
}

function getRuleDisplayText($ruleInst, $ruleDefXml) {
    global $debug;

    $ruleDefName = getChildValue($ruleInst,"ruledef");
    if ( is_null($ruleDefName) ) {
       $ruleDefName = getChildValue($ruleInst, RULE_NAME);
    }
    if ( $debug ) { echo "<pre>getRuleDisplayText: rulename=" . getChildValue($ruleInst, RULE_NAME) . ", ruledef=$ruleDefName</pre>\n"; }
    $ruleDef = getRuleDef( $ruleDefXml, $ruleDefName );

    $ruleText = getChildValue($ruleInst,"desc");
    if ( is_null($ruleText) ) {
       $ruleText = getChildValue($ruleDef,"desc");
    }
    if ( is_null($ruleText) ) {
        $ruleText = getChildValue($ruleInst, RULE_NAME);
    }

    $title = "";
    $displayThresholds = getDisplayThresholds($ruleInst, $ruleDef);
    if ( count($displayThresholds) > 0 ) {
        $title .= " [Thresholds: " . implode(", ", $displayThresholds) . "]";
    }

    $filter = $ruleInst->getElementsByTagName(FILTER)->item(0);
    if ( ! is_null($filter) ) {
        $title .= getDisplayFilters($filter);
    }

    return array(
        LABEL => $ruleText,
        TITLE => $title
    );
}

function makeForm($name,$method,$title) {
    global $debug;

    $form = new HTML_QuickForm($name, $method);
    $form->addElement('header', null, $title);

    $form->addElement('hidden', 'form', $name);
    $form->addElement('hidden', 'debug', $debug );

    $form->setConstants(array('form' => $name, 'debug' => $debug));

    return $form;
}

function makeEditReportForm() {
    global $debug, $AdminDB;

    $reportId = $_REQUEST['reportid'];

    $statsDB = new StatsDB();
    $row = $statsDB->queryNamedRow("
SELECT
 site_type, reportname, access, content
FROM
 $AdminDB.ddp_custom_reports
WHERE
 id = $reportId");
    $site_type = $row['site_type'];
    $reportName = $row[REP_NAME];
    $reportContent = $row['content'];
    $access = $row[ACCESS];

    $ruleDefXml = loadRuleDef();
    $customReport = loadRuleInst($reportContent);
    $hcReport = getHcReport($site_type);

    $disabledRuleNames = array();
    foreach ( $customReport->getElementsByTagName("disabledrule") as $disabledrule ) {
        $disabledRuleNames[$disabledrule->nodeValue] = 1;
    }
    $customRulesByName = array();
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        $customRulesByName[getChildValue($ruleInst, RULE_NAME)] = $ruleInst;
    }
    if ( $debug ) {
        echo "<pre>makeEditReportForm: customRulesByName keys: ";
        print_r(array_keys($customRulesByName));
        echo "</pre>\n";
    }

    $activeRulesSelectList = array();
    $disabledRulesSelectList = array();
    foreach ($hcReport->getElementsByTagName("ruleinst") as $ruleInst) {
        $ruleName = getChildValue($ruleInst, RULE_NAME);
        if ( array_key_exists($ruleName,$disabledRuleNames) ) {
            $disabledRulesSelectList[$ruleName] = getRuleDisplayText($ruleInst,$ruleDefXml);
        } else if ( array_key_exists( $ruleName, $customRulesByName) ) {
             $activeRulesSelectList[$ruleName] = getRuleDisplayText($customRulesByName[$ruleName],$ruleDefXml);
             unset($customRulesByName[$ruleName]);
        } else {
             $activeRulesSelectList[$ruleName] = getRuleDisplayText($ruleInst,$ruleDefXml);
        }
    }
    # Add any custom ruleinst that aren't in the standard report
    foreach ($customRulesByName as $ruleName => $ruleInst) {
        $activeRulesSelectList[$ruleName] = getRuleDisplayText($ruleInst,$ruleDefXml);
    }

    asort($activeRulesSelectList);
    asort($disabledRulesSelectList);

    $form = makeForm(EDIT_REPORT_FORM, 'POST', $reportName);
    $form->addElement('hidden','reportid',$reportId);

    $radio = array();
    $radio[] = HTML_QuickForm::createElement('radio', null, null, 'Private', 'PRIVATE');
    $radio[] = HTML_QuickForm::createElement('radio', null, null, 'Public', 'PUBLIC');
    $form->addGroup($radio, ACCESS, 'Access:', NON_BREAKING_SPACE);
    $form->setDefaults(array(ACCESS => $access));
    $reportButtons = array();
    $reportButtons[] = HTML_QuickForm::createElement(SUBMIT, 'btnUpdate', 'Update');
    $reportButtons[] = HTML_QuickForm::createElement(SUBMIT, BTN_EXPORT, 'Export');
    $reportButtons[] = HTML_QuickForm::createElement(SUBMIT, 'btnImport', 'Import');
    $form->addGroup($reportButtons, null, null, NON_BREAKING_SPACE);

    $activeRuleSelect = $form->addElement(
        SELECT_ELEMENT,
        ACTIVE_ID,
        'Active Rules:',
        null,
        array( STYLE => "width: 80em" )
    );
    foreach ( $activeRulesSelectList as $ruleName => $ruleDisplay ) {
        $attributes = null;
        if ( $ruleDisplay[TITLE] !== '' ) {
            $attributes = array( TITLE => $ruleDisplay[TITLE] );
        }
        $activeRuleSelect->addOption($ruleDisplay[LABEL], $ruleName, $attributes);
    }

    $activeRuleSelect->setMultiple(true);
    $activeRuleSelect->setSize(10);
    $activeButtons[] = HTML_QuickForm::createElement(SUBMIT, 'btnActiveDisable', 'Disable');
    $activeButtons[] = HTML_QuickForm::createElement(SUBMIT, 'btnActiveEdit', 'Edit');
    $activeButtons[] = HTML_QuickForm::createElement(SUBMIT, 'btnActiveNew', 'New');
    $form->addGroup($activeButtons, null, null, NON_BREAKING_SPACE);

    $disabledRuleSelect = $form->addElement(
        SELECT_ELEMENT,
        DISABLED_ID,
        'Disabled Rules:',
        null,
        array( STYLE => "width: 80em" )
    );
    foreach ( $disabledRulesSelectList as $ruleName => $ruleDisplay ) {
        if ( $ruleDisplay[TITLE] !== '' ) {
            $attributes = array( TITLE => $ruleDisplay[TITLE] );
        }
        $disabledRuleSelect->addOption($ruleDisplay[LABEL], $ruleName, $attributes);
    }
    $disabledRuleSelect->setMultiple(true);
    $disabledRuleSelect->setSize(5);
    $disabledButtons = array();
    $disabledButtons[] = HTML_QuickForm::createElement(SUBMIT, 'btnDisabledEnable', 'Enable');
    $form->addGroup($disabledButtons, null, null, NON_BREAKING_SPACE);

    return $form;
}

function processEditReportForm($values) {
    global $debug, $AdminDB;

    if ( $debug ) { echo "<pre>processEditReportForm values="; print_r($values); echo "</pre>"; }

    $reportId = $values['reportid'];

    $editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $editDB->exec("use $AdminDB");

    $row = $editDB->queryNamedRow("SELECT site_type, reportname, content FROM $AdminDB.ddp_custom_reports WHERE id = $reportId");
    $customReportDoc = loadRuleInst($row['content']);
    $customReport = $customReportDoc->documentElement;
    $hcReport = getHcReport($row['site_type']);

    $form = null;

    if ( isset($values['btnActiveEdit']) ) {
        if ( array_key_exists(ACTIVE_ID, $values) ) {
            # If the selected rule is not a customed rule, then we need to
            # add it to the report
            $customRuleNames = array();
            foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
                $customRuleNames[getChildValue($ruleInst, RULE_NAME)] = 1;
            }
            $selectedRuleName = $values[ACTIVE_ID][0];
            if ( ! array_key_exists($selectedRuleName, $customRuleNames) ) {
                foreach ($hcReport->getElementsByTagName("ruleinst") as $ruleInst) {
                    $ruleName = getChildValue($ruleInst, RULE_NAME);
                    if ( $ruleName === $selectedRuleName ) {
                        $customReport->appendChild($customReportDoc->importNode($ruleInst,TRUE));
                        saveRuleInst($editDB,$reportId,$customReportDoc);
                    }
                }
            }
            $_REQUEST[RULE_NAME] = $selectedRuleName;
            $form = makeEditRuleInstForm();
        } else {
            $form = makeEditReportForm();
        }
    } else if ( isset($values['btnActiveDisable']) ) {
        if ( array_key_exists(ACTIVE_ID, $values) ) {
            # Check if these are custom rules and if so remove them
            $customRulesToRemove = array();
            foreach ( $values[ACTIVE_ID] as $ruleName ) {
                foreach ( $customReport->getElementsByTagName("ruleinst") as $ruleInst ) {
                    if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
                        $customRulesToRemove[$ruleName] = $ruleInst;
                    }
                }
            }
            if ( $debug ) {
                echo "<pre>processEditReportForm: customRulesToRemove";
                print_r(array_keys($customRulesToRemove));
                echo "</pre>\n";
            }
            foreach ($customRulesToRemove as $customRuleToRemove) {
                $customReport->removeChild($customRuleToRemove);
            }

            $firstRuleInst = $customReport->getElementsByTagName("ruleinst")->item(0);
            foreach ( $values[ACTIVE_ID] as $ruleName ) {
                # If the rule exists in the standard hc report we need to add
                # a disabledrule element
                $isHcRuleInst = FALSE;
                foreach ($hcReport->getElementsByTagName("ruleinst") as $ruleInst) {
                    if (  getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
                        $isHcRuleInst = TRUE;
                        break;
                    }
                }

                if ( $isHcRuleInst ) {
                    $disabledNode = $customReportDoc->createElement("disabledrule",$ruleName);
                    if ( is_null($firstRuleInst) ) {
                        $customReport->appendChild($disabledNode);
                    } else {
                        $customReport->insertBefore($disabledNode, $firstRuleInst);
                    }
                }
            }

            saveRuleInst($editDB, $reportId, $customReportDoc);
        }
        $form = makeEditReportForm();
    } else if ( isset($values['btnActiveNew']) ) {
        $form = makeNewRuleInstForm();
    } else if ( isset($values['btnDisabledEnable']) ) {
        if ( array_key_exists(DISABLED_ID, $values) ) {
            $nodesToRemove = array();
            foreach ($customReport->getElementsByTagName("disabledrule") as $disabledrule) {
                if ( in_array($disabledrule->nodeValue, $values[DISABLED_ID]) ) {
                   $nodesToRemove[] = $disabledrule;
                }
            }
            foreach ( $nodesToRemove as $node ) {
                if ( $debug ) { echo "<pre>processMain: removing $node->nodeValue from disabledrule</pre>\n"; }
                $customReport->removeChild($node);
            }
            saveRuleInst($editDB,$reportId,$customReportDoc);
        }
        $form = makeEditReportForm();
    } elseif ( isset($values['btnUpdate']) ) {
        $updateSql = sprintf(
            "UPDATE ddp_custom_reports SET access = '%s' WHERE id = %d",
            $values[ACCESS],
            $reportId
        );
        $editDB->exec($updateSql);
        $form = makeEditReportForm();
    } elseif ( isset($values[BTN_EXPORT]) ) {
        header( "content-type: text/xml" );
        header("Cache-Control: private, must-revalidate");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Content-Disposition: attachment; filename=\"customhc.xml\"");

        $customReportDoc->formatOutput = true;
        echo $customReportDoc->saveXML();
    } elseif ( isset($values['btnImport']) ) {
        $form = makeEditImportForm();
    }

    if ( ! is_null($form) ) {
        displayForm($form);
    }
}

function makeEditRuleInstForm() {
    global $AdminDB, $debug;

    $reportId = $_REQUEST['reportid'];
    $ruleName = $_REQUEST[RULE_NAME];

    $statsDB = new StatsDB();
    $row = $statsDB->queryNamedRow("SELECT site_type, reportname, content FROM $AdminDB.ddp_custom_reports WHERE id = $reportId");
    $reportContent = $row['content'];

    $ruleDefDoc = loadRuleDef();
    $customReportDoc = loadRuleInst($reportContent);
    $customReport = $customReportDoc->documentElement;

    $ruleInstToEdit = NULL;
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
            $ruleInstToEdit = $ruleInst;
        }
    }
    if ( is_null($ruleInstToEdit) ) {
        echo "<H1>ERROR</H1>\n<pre>Could not find ruleinst with rulename $ruleName</pre>\n";
        return NULL;
    }

    $ruleDefName = getChildValue($ruleInstToEdit,"ruledef");
    if ( is_null($ruleDefName) ) {
       $ruleDefName = getChildValue($ruleInstToEdit, RULE_NAME);
    }

    if ( $debug ) { echo "<pre>makeEditRuleInstForm: ruleDefName=$ruleDefName</pre>\n"; }

    $ruleDef = getRuleDef( $ruleDefDoc, $ruleDefName );
    if ( $debug > 1) { echo "<pre>makeEditRuleInstForm: ruleDef</pre>"; echo $ruleDefDoc->saveHTML($ruleDef);  }

    $defaults = array();
    foreach ( $ruleDef->getElementsByTagName("threshold") as $thresDef ) {
        $thresDefName = $thresDef->getAttribute('name');
        foreach ( $ruleInstToEdit->getElementsByTagName("threshold") as $thresInst ) {
            if ( $thresInst->getAttribute('name') === $thresDefName ) {
                $defaults['threshold_' . urlencode($thresDefName)] = $thresInst->getAttribute("value");
                if ( $thresInst->hasAttribute("warn") ) {
                    $defaults['warn_' . urlencode($thresDefName)] = $thresInst->getAttribute('warn');
                }
            }
        }
    }

    # If the ruledef has filterable columns
    $filter_column_list = getFilterableColumns($ruleDef);
    if ( count($filter_column_list) > 0 ) {
        $conditions = array();
        $operation = 'and';
        foreach ( $ruleInstToEdit->getElementsByTagName(FILTER) as $filter ) {
            $condition_index = 0;
            foreach ( $filter->getElementsByTagName("condition") as $condition ) {
                $name = $condition->getAttribute("name");
                $conditions[$condition_index] = $name . " " . $condition->getAttribute("type") . " " . $condition->getAttribute("value");
                $condition_index++;
            }
            $operation = $filter->getAttribute(OPERATION);
        }
        $defaults['filter_type'] = $operation;
    }
    if ($debug > 1) { echo "<pre>makeEditRuleInstForm: defaults="; print_r($defaults); echo "</pre>"; }

    $form = makeForm(EDIT_RULE_INST_FORM, 'POST', 'Edit Rule');
    $form->setDefaults($defaults);

    $form->addElement('hidden', 'reportid', $reportId );
    $form->addElement('hidden', RULE_NAME, $ruleName );
    $form->addElement('hidden', 'ruledefname', $ruleDefName );

    $form->addElement('static', null, 'Thresholds');

    foreach ( $ruleDef->getElementsByTagName("threshold") as $thresDef ) {
        $thresDefName = $thresDef->getAttribute('name');
        foreach ( $ruleInstToEdit->getElementsByTagName("threshold") as $thresInst ) {
            if ( $thresInst->getAttribute('name') === $thresDefName ) {
                $elementName = 'threshold_' . urlencode($thresDefName);
                $form->addElement(
                    'text',
                    $elementName,
                    $thresDefName,
                    array('size' => 20, MAX_LENGTH => 255)
                );
                $form->addRule($elementName, 'The field is required', 'required', null, CLIENT);
                $form->addRule($elementName, 'Should be numeric', 'numeric', null, CLIENT);

                if ( $thresInst->hasAttribute("warn") ) {
                    $elementName = 'warn_' . urlencode($thresDefName);
                    $form->addElement(
                        'text',
                        $elementName,
                        $thresDefName . " Warn",
                        array('size' => 20, MAX_LENGTH => 255)
                    );
                    $warnValue = $thresInst->getAttribute("warn");
                    $form->addRule($elementName, 'The field is required', 'required', null, CLIENT);
                    $form->addRule($elementName, 'Should be numeric', 'numeric', null, CLIENT);
                }
            }
        }
    }

    $buttons = array();
    $buttons[] = &HTML_QuickForm::createElement(SUBMIT, 'btnEditSave', 'Save');
    $buttons[] = &HTML_QuickForm::createElement(SUBMIT, 'btnEditCancel', 'Canel');
    $form->addGroup($buttons, null, null, NON_BREAKING_SPACE);

    if ( count($filter_column_list) > 0 ) {
        $form->addElement('static', null, FILTER);

        $conditionSelect = $form->addElement(SELECT_ELEMENT, 'filter_condition', 'Conditions', $conditions);
        $conditionSelect->setSize(5);

        $filterButtons = array();
        $filterButtons[] = &HTML_QuickForm::createElement(SUBMIT, 'btnConditionAdd', 'Add');
        $filterButtons[] = &HTML_QuickForm::createElement(SUBMIT, 'btnConditionRemove', 'Remove');
        $form->addGroup($filterButtons, null, null, NON_BREAKING_SPACE);
    }

    return $form;
}

function processEditRuleInstForm($values) {
    global $AdminDB, $debug;

    if ( $debug ) { echo "<pre>processEditRuleInst _REQUEST="; print_r($_REQUEST); echo "</pre>"; }

    $reportId = $values['reportid'];
    $ruleName = $values[RULE_NAME];

    if ( $debug ) { echo "<pre>processEditRuleInst values="; print_r($values); echo "</pre>"; }

    $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $statsDB->exec("use $AdminDB");

    $report_props = $statsDB->queryNamedRow("SELECT site_type, reportname, content FROM ddp_custom_reports WHERE id = $reportId");

    $customReportDoc = loadRuleInst($report_props['content']);
    $customReport = $customReportDoc->documentElement;

    $ruleInstToEdit = NULL;
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
            $ruleInstToEdit = $ruleInst;
        }
    }
    if ( is_null($ruleInstToEdit) ) {
        echo "<H1>ERROR</H1>\n<pre>Could not find ruleinst with rulename $ruleName</pre>\n";
        return NULL;
    }

    if ( isset($values['btnEditSave']) ) {
        foreach ( $ruleInstToEdit->getElementsByTagName("threshold") as $thresInst ) {
            $thresInstName = urlencode($thresInst->getAttribute('name'));
            $thresValue = $values['threshold_' . $thresInstName];
            if ( $debug ) { echo "<pre>updated value to $thresValue $thresInstName for </pre>\n"; }
            $thresInst->setAttribute("value", $thresValue);

            if ( $thresInst->hasAttribute("warn") ) {
                $warnValue = $values['warn_' . $thresInstName];
                if ( $debug ) { echo "<pre>updated warn to $warnValue for $thresInstName</pre>\n"; }
                $thresInst->setAttribute("warn",$warnValue);
            }
        }

        saveRuleInst($statsDB, $reportId, $customReportDoc);
        $form = makeEditReportForm();
    } else if ( isset($values['btnConditionAdd']) ) {
        $form = makeAddConditionForm();
    } else if ( isset($values['btnConditionRemove']) ) {
        $condition_index = $values['filter_condition'];
        $filter = $ruleInstToEdit->getElementsByTagName(FILTER)->item(0);
        $condition = $filter->getElementsByTagName("condition")->item($condition_index);
        $filter->removeChild($condition);

        # Now check if there are any conditions left, and if not remove the filter
        if ( is_null($filter->getElementsByTagName("condition")->item(0)) ) {
            $ruleInstToEdit->removeChild($filter);
        }

        saveRuleInst($statsDB, $reportId, $customReportDoc);
        $form = makeEditRuleInstForm();
    } else {
        $form = makeEditReportForm();
    }

    displayForm($form);
}

function makeNewRuleInstForm() {
    global $debug;

    $reportId = $_REQUEST['reportid'];

    $ruledefs = array();
    $ruleDefXml = loadRuleDef();
    foreach($ruleDefXml->getElementsByTagName("rule") as $ruleDef) {
        $ruleDefName = getChildValue($ruleDef,"name");
        $ruleDefDesc = getChildValue($ruleDef,"desc");
        if ( is_null($ruleDefDesc) ) {
            $ruleDefDesc = $ruleDefName;
        }
        $ruledefs[$ruleDefName] = $ruleDefDesc;
    }
    asort($ruledefs);

    $form = makeForm('newruleinst','POST','Add Rule');

    $form->addElement('hidden', 'reportid', $reportId );

    $form->addElement('text', RULE_NAME, 'Name', array('size' => 20, MAX_LENGTH => 32));
    $form->addRule(RULE_NAME, 'The field is required', 'required', null, CLIENT);
    $message = 'Characters in rule name must match /^[a-zA-z\d_]+$/';
    $form->addRule(RULE_NAME, $message, 'regex', '/^[a-zA-z\d_]+$/', CLIENT);
    $form->addRule(RULE_NAME, 'Rule name must be unique', 'callback', 'checkRuleNameUnique');

    $form->addElement('text', 'desc', 'Description', array('size' => 20, MAX_LENGTH => 128));
    $form->addRule('desc', 'The field is required', 'required', null, CLIENT);

    $form->addElement(SELECT_ELEMENT, 'ruledef', 'Rule Type:', $ruledefs);
    $form->addRule('desc', 'The field is required', 'required', null, CLIENT);

    $form->addElement(SUBMIT, null, 'Create');

    return $form;
}

function checkRuleNameUnique($ruleName) {
    global $AdminDB;

    $reportId = $_REQUEST['reportid'];

    $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $statsDB->exec("use $AdminDB");
    $query = "SELECT site_type, reportname, content FROM ddp_custom_reports WHERE id = $reportId";
    $report_props = $statsDB->queryNamedRow( $query );

    $customReportDoc = loadRuleInst($report_props['content']);
    $customReport = $customReportDoc->documentElement;
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
            return FALSE;
        }
    }

    $hcReportDoc = getHcReport($report_props['site_type']);
    $hcReport = $hcReportDoc->documentElement;
    foreach ($hcReport->getElementsByTagName("ruleinst") as $ruleInst) {
        if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
            return FALSE;
        }
    }

    return TRUE;
}

function processNewRuleInstForm($values) {
    global $debug, $AdminDB;

    if ( $debug ) { echo "<pre>processNewRuleInstForm values="; print_r($values); echo "</pre>"; }

    $reportId = $values['reportid'];
    $ruleName = $values[RULE_NAME];

    $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $statsDB->exec("use $AdminDB");
    $report_props = $statsDB->queryNamedRow("SELECT site_type, reportname, content FROM ddp_custom_reports WHERE id = $reportId");

    $ruleDefDoc = loadRuleDef();
    $customReportDoc = loadRuleInst($report_props['content']);
    $customReport = $customReportDoc->documentElement;

    $ruleInst = $customReportDoc->createElement("ruleinst");
    $ruleInst->appendChild($customReportDoc->createElement(RULE_NAME,$ruleName));
    $ruleInst->appendChild($customReportDoc->createElement("ruledef",$values['ruledef']));
    $ruleInst->appendChild($customReportDoc->createElement("desc",$values['desc']));

    $ruleDef = getRuleDef( $ruleDefDoc, $values['ruledef'] );
    foreach ( $ruleDef->getElementsByTagName("threshold") as $thresDef ) {
        $thresDefName = $thresDef->getAttribute('name');
        $threshold = $customReportDoc->createElement("threshold");
        $threshold->setAttribute("name", $thresDefName);
        $threshold->setAttribute("value", 0);
        $ruleInst->appendChild($threshold);
    }

    $customReport->appendChild($ruleInst);

    saveRuleInst($statsDB, $reportId, $customReportDoc);

    $form = makeEditRuleInstForm();
    displayForm($form);
}

function makeAddConditionForm() {
    global $AdminDB;

    $reportId = $_REQUEST['reportid'];
    $ruleName = $_REQUEST[RULE_NAME];

    $statsDB = new StatsDB();
    $row = $statsDB->queryNamedRow("SELECT site_type, reportname, content FROM $AdminDB.ddp_custom_reports WHERE id = $reportId");
    $reportContent = $row['content'];

    $ruleDefDoc = loadRuleDef();
    $customReportDoc = loadRuleInst($reportContent);
    $customReport = $customReportDoc->documentElement;

    $ruleInstToEdit = NULL;
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
            $ruleInstToEdit = $ruleInst;
        }
    }
    if ( is_null($ruleInstToEdit) ) {
        echo "<H1>ERROR</H1>\n<pre>Could not find ruleinst with rulename $ruleName</pre>\n";
        return NULL;
    }

    $ruleDefName = getChildValue($ruleInstToEdit,"ruledef");
    if ( is_null($ruleDefName) ) {
       $ruleDefName = getChildValue($ruleInstToEdit, RULE_NAME);
    }
    $ruleDef = getRuleDef( $ruleDefDoc, $ruleDefName );

    $column_list = getFilterableColumns($ruleDef);

    $form = makeForm('addcondition','POST','Add Condition');
    $form->addElement('hidden', 'reportid', $reportId );
    $form->addElement('hidden', RULE_NAME, $ruleName );

    $form->addElement(SELECT_ELEMENT, 'column', 'Column', $column_list);
    $form->addElement(SELECT_ELEMENT, 'type', OPERATION, array( 'like' => 'Like', 'notlike' => 'Not Like'));
    $form->addElement('text', 'value', 'Value', array('size' => 20, MAX_LENGTH => 255));
    $form->addRule('value', 'The field is required', 'required', null, CLIENT);

    $buttons = array();
    $buttons[] = &HTML_QuickForm::createElement(SUBMIT, 'btnConditionSave', 'Save');
    $buttons[] = &HTML_QuickForm::createElement(SUBMIT, 'btnConditionCancel', 'Canel');
    $form->addGroup($buttons, null, null, NON_BREAKING_SPACE);

    return $form;
}

function processAddConditionForm() {
    global $AdminDB;

    $reportId = $_REQUEST['reportid'];
    $ruleName = $_REQUEST[RULE_NAME];

    $form = makeAddConditionForm( $reportId, $ruleName );
    $values = $form->exportValues();

    $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $statsDB->exec("use $AdminDB");

    $report_props = $statsDB->queryNamedRow("SELECT site_type, reportname, content FROM ddp_custom_reports WHERE id = $reportId");

    $customReportDoc = loadRuleInst($report_props['content']);
    $customReport = $customReportDoc->documentElement;

    $ruleInstToEdit = NULL;
    foreach ($customReport->getElementsByTagName("ruleinst") as $ruleInst) {
        if ( getChildValue($ruleInst, RULE_NAME) === $ruleName ) {
            $ruleInstToEdit = $ruleInst;
        }
    }
    if ( is_null($ruleInstToEdit) ) {
        echo "<H1>ERROR</H1>\n<pre>Could not find ruleinst with rulename $ruleName</pre>\n";
        return NULL;
    }

    if ( isset($values['btnConditionSave']) ) {
        $filter = $ruleInstToEdit->getElementsByTagName(FILTER)->item(0);
        if ( is_null($filter) ) {
            $filter = $customReportDoc->createElement(FILTER);
            $filter->setAttribute(OPERATION, "and");
            foreach ( array("desc", "ruledef", RULE_NAME) as $prevElementName ) {
                $prevElement = $ruleInstToEdit->getElementsByTagName($prevElementName)->item(0);
                if ( ! is_null($prevElement)) {
                    if ( is_null($prevElement->nextSibling) ) {
                        $ruleInstToEdit->appendChild($filter);
                    } else {
                        $ruleInstToEdit->insertBefore($filter,$prevElement->nextSibling);
                    }
                    break;
                }
            }
        }
        $condition = $customReportDoc->createElement("condition");
        $condition->setAttribute("name", $values['column']);
        $condition->setAttribute("type", $values['type']);
        $condition->setAttribute("value", $values['value']);
        $filter->appendChild($condition);

        saveRuleInst($statsDB, $reportId, $customReportDoc);
    }

    $form = makeEditRuleInstForm($reportId,$ruleName);
    displayForm($form);
}

function makeSelectReportForm() {
    global $AdminDB, $auth_user, $debug;

    $statsDB = new StatsDB();
    $statsDB->query("SELECT id,reportname FROM $AdminDB.ddp_custom_reports WHERE signum = '$auth_user' ORDER BY reportname");
    $reports = array();
    while ($row = $statsDB->getNextNamedRow()) {
        $reports[$row['id']] = $row[REP_NAME];
    }

    $form = makeForm("selectreport", 'POST', 'Custom Reports');

    $reportSelect = $form->addElement(
        SELECT_ELEMENT,
        REPORT_IDS,
        'Report:',
        $reports,
        array( STYLE => "width: 60em" )
    );
    $reportSelect->setMultiple(true);
    $reportSelect->setSize(5);

    $buttons[] = HTML_QuickForm::createElement(SUBMIT, 'btnAdd', 'Add');
    $buttons[] = HTML_QuickForm::createElement(SUBMIT, 'btnRemove', 'Remove');
    $buttons[] = HTML_QuickForm::createElement(SUBMIT, 'btnEdit', 'Edit');
    $form->addGroup($buttons, null, null, NON_BREAKING_SPACE);

    return $form;
}

function sendEmail( $subscriptions, $statsDB ) {
    global $ddp_dir, $AdminDB;
    $mails = array();
    $ddpServer = getDDPServerName();
    $ddpServer = substr($ddpServer, 0, strpos($ddpServer, '.'));
    $subject = "DDP Custom Report Subscription Update on $ddpServer";

    foreach ($subscriptions as $sub) {
        $signum = $sub['signum'];
        $siteId = $sub['siteid'];
        $reportId = $sub['reportid'];

        if ( array_key_exists($signum, $mails) ) {
            $list = $mails[$signum];
            $list[$siteId] = $reportId;
            $mails[$signum] = $list;
        } else {
            $mails[$signum] = array( $siteId => $reportId );
        }
    }


    foreach ( $mails as $key => $val ) {
        $sql = "SELECT email FROM ddp_alert_subscriber_emails WHERE signum = '$key'";
        $row = $statsDB->queryRow($sql);
        $email = $row[0];

        $body = "Hi $key, <br><br> The below Custom Reports have been deleted and you have been unsubscribed: <br><br>";

        foreach ( $val as $siteId => $reportId ) {
            $sql = "SELECT reportname FROM ddp_custom_reports WHERE ddp_custom_reports.id = '$reportId'";
            $reportName = $statsDB->queryRow($sql)[0];

            $statsDB->exec("use statsdb");
            $siteName = $statsDB->queryRow("SELECT name from sites WHERE id = '$siteId'")[0];
            $statsDB->exec("use $AdminDB");

            $body .= "<li><b>Report Name:</b> $reportName   <b>Site Name:</b> $siteName</li><br>";
        }
        $body .= "<br><br>";

        $cmd = "export PERL5LIB=$ddp_dir/analysis/common/; ";
        $cmd .= "$ddp_dir/server_setup/sendEmail.pl --subject '$subject' ";
        $cmd .= "--body '$body' --emails '$email' 2>&1";

        exec($cmd, $output); //NOSONAR

        foreach ( $output as $item) {
            echo "sendEmail: " . $item . addLineBreak();
        }
    }
}

function processSelectReportForm($values) {
    global $debug, $AdminDB;

    if ( $debug ) { echo "<pre>processReportSelectorForm: values="; print_r($values); echo "</pre>\n"; }

    if ( isset($values['btnAdd']) ) {
        $form = makeNewReportForm();
    } elseif ( (!array_key_exists(REPORT_IDS, $values)) || count($values[REPORT_IDS]) == 0 ) {
        # Nothing selected, so just show the selector form again
        $form = makeSelectReportForm();
    } elseif ( isset($values['btnEdit']) ) {
        $_REQUEST['reportid'] = (int)$values[REPORT_IDS][0];
        $form = makeEditReportForm();
    } elseif ( isset($values['btnRemove']) ) {
        $idsStr = implode(",", array_values($values[REPORT_IDS]));
        $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
        $statsDB->exec("use $AdminDB");

        $statsDB->query("SELECT * FROM ddp_alert_subscriptions WHERE reportid IN ( $idsStr )");
        $subscriptions = array();
        while ($row = $statsDB->getNextNamedRow()) {
            $subscriptions[] = $row;
        }

        if ( $subscriptions ) {
            sendEmail($subscriptions, $statsDB);

            $sql = "DELETE FROM ddp_alert_subscriptions WHERE reportid IN ( $idsStr )";
            $statsDB->exec($sql);
        }

        $sql = "DELETE FROM ddp_custom_reports WHERE id IN ( $idsStr )";
        $statsDB->exec($sql);

        $form = makeSelectReportForm();
    }

    displayForm($form);
}

function makeNewReportForm() {
    global $debug;

    $form = new HTML_QuickForm('newreport', 'POST');

    $form->addElement('hidden', 'debug', $debug );

    $hidden = $form->addElement('hidden','form','newreport');
    // Need to force the value of form as it takes it form
    // from the previous form
    $hidden->setValue('newreport');

    $form->addElement('text', REP_NAME, 'Name', array('size' => 20, MAX_LENGTH => 255));
    $message = 'Characters in report name must match /^[a-zA-z\d_]+$/';
    $form->addRule(REP_NAME, $message, 'regex', '/^[a-zA-z\d_]+$/', CLIENT);

    $typeArr = array(
        'NONE' => 'Select',
        'OSS' => 'OSS',
        'ENIQ' => 'ENIQ',
        'TOR' => 'ENM',
        'DDP' => 'DDP',
        'EO' => 'EO',
        'GENERIC' => 'GENERIC'
    );
    $typeSel =  HTML_QuickForm::createElement(SELECT_ELEMENT, 'site_type', 'Site Type:', $typeArr);
    $form->addElement($typeSel);

    $form->addElement(SUBMIT, null, 'Submit');

    return $form;
}

function processNewReportForm($values) {
    global $EMPTY_REPORT;
    global $AdminDB, $auth_user, $debug;

    $editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $editDB->exec("use $AdminDB");

    $reportContent = $editDB->escape($EMPTY_REPORT);
    $insertSql = sprintf("INSERT INTO ddp_custom_reports (site_type,signum,reportname,content) VALUES ('%s','%s','%s','%s')",
                             $values['site_type'], $auth_user, $values[REP_NAME], $reportContent );

    $editDB->exec($insertSql);
    $reportId = $editDB->lastInsertId();

    $_REQUEST['reportid'] = $reportId;
    $form = makeEditReportForm();
    displayForm($form);
}


function makeEditImportForm() {
    global $debug, $AdminDB;

    $reportId = $_REQUEST['reportid'];

    $statsDB = new StatsDB();
    $row = $statsDB->queryNamedRow("
SELECT
 reportname
FROM
 $AdminDB.ddp_custom_reports
WHERE
 id = $reportId");
    $reportName = $row[REP_NAME];

    $form = makeForm('editimport', 'POST', $reportName);
    $form->addElement('hidden', 'reportid', $reportId);
    $form->addElement('file', 'reportfile', "Report File");
    $form->addElement('submit', null, 'Import');

    return $form;
}

function processEditImportForm($values) {
    global $AdminDB, $form;

  $reportId = $values['reportid'];

  $file =& $form->getElement('reportfile');
  if ($file->isUploadedFile()) {
    $uploadParam = $file->getValue();
    debugMsg("processEditImportForm: uploadParam", $uploadParam);
    $customReportDoc = loadRuleInst(trim(file_get_contents($uploadParam['tmp_name'])));

    $editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);
    $editDB->exec("use $AdminDB");
    saveRuleInst($editDB, $reportId, $customReportDoc);
  } else {
      debugMsg("isUploadedFile returned false");
  }

  $form = makeEditReportForm();
  displayForm($form);
}

function displayForm($form) {
   if ( $form->getElementValue("form") === EDIT_RULE_INST_FORM ) {
      $ruleName = $form->getElementValue(RULE_NAME);
      $ruleDefName = $form->getElementValue('ruledefname');
      $helpElement = "<div>" . getHeader('Rule Description', 3, 'editruledesc') . "\n" .
          getRuleHelpTextFromDB($ruleName, $ruleDefName) . "\n</div>\n";
      echo $helpElement;
   }

  $form->display();
}

if ( $UI ) {
    echo <<<EOS
<style scoped>
    label { background-color: White; display: inline  }
</style>
EOS;
}

if ( $_SERVER["REQUEST_METHOD"] == "POST") {
    if ( $debug ) { echo "<pre>main: _REQUEST:"; print_r($_REQUEST); echo "</pre>\n"; }

    $formFunctions = array(
        'selectreport' => array( 'make' => 'makeSelectReportForm', 'process' => 'processSelectReportForm'),
        'newreport'    => array( 'make' => 'makeNewReportForm', 'process' => 'processNewReportForm'),
        EDIT_REPORT_FORM   => array( 'make' => 'makeEditReportForm', 'process' => 'processEditReportForm'),
        EDIT_RULE_INST_FORM => array( 'make' => 'makeEditRuleInstForm', 'process' => 'processEditRuleInstForm'),
        'newruleinst' => array( 'make' => 'makeNewRuleInstForm', 'process' => 'processNewRuleInstForm'),
        'addcondition' => array( 'make' => 'makeAddConditionForm', 'process' => 'processAddConditionForm'),
        'editimport' => array('make' => 'makeEditImportForm', 'process' => 'processEditImportForm')
    );

    $form = $formFunctions[$_REQUEST["form"]]['make']();
    if ( $form->validate() ) {
        $form->freeze();
        $form->process($formFunctions[$_REQUEST["form"]]['process']);
    } else {
        displayForm($form);
    }
} else {
    $form = makeSelectReportForm();
    displayForm($form);
}

include PHP_ROOT . "/common/finalise.php";
