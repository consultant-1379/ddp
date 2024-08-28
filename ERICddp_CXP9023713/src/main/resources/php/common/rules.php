<?php

function getRuleHelpTextFromDB($ruleName, $ruleDefName) {
    // First, lets set if we have help for the rulename
    $helpText = getHelpTextFromDB('DDP_Bubble.hc.' . $ruleName);
    if ( (is_null($helpText) || $helpText === 'Help description not found') &&
         $ruleDefName != $ruleName ) {
        // No help text for the rulename in DB, lets try the ruleDef
        $helpText = getHelpTextFromDB('DDP_Bubble.hc.' . $ruleDefName);
    }
    if ( is_null($helpText) || $helpText === 'Help description not found' ) {
        $helpText = "";
    }

    return $helpText;
}

