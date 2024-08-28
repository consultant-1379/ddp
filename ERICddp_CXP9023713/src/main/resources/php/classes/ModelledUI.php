<?php

class ModelledUI {
    protected static function sendRequest($modelType, $modelName, $contentArray) {
        global $modelledUiEndpoint;

        // Get the endpoint for the ModelledUI app
        // Normally should be setting in the apache.conf so
        // we pick it up as a _SERVER env variable
        // Can be overridden by setting modelleduiEndpoint
        $endPoint = null;
        if (isset($modelledUiEndpoint)) {
            $endPoint = $modelledUiEndpoint;
        } else {
            $endPoint = fromServer('MODELLED_UI_ENDPOINT');
        }
        if (is_null($endPoint)) {
            die("ERROR: No setting for Modelled UI endpoint");
        }
        $url = sprintf("%s/%s/%s", $endPoint, $modelType, $modelName);
        debugMsg("ModelledUI::sendRequest url", $url);
        debugMsg("ModelledUI::sendRequest contentArray", $contentArray);

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode($contentArray)
            )
        );
        $context  = stream_context_create($opts);
        $replyContent = file_get_contents($url, false, $context);
        $reply = json_decode($replyContent, true);
        if (is_null($reply)) {
            /*
            0 = JSON_ERROR_NONE
            1 = JSON_ERROR_DEPTH
            2 = JSON_ERROR_STATE_MISMATCH
            3 = JSON_ERROR_CTRL_CHAR
            4 = JSON_ERROR_SYNTAX
            5 = JSON_ERROR_UTF8
            */
            die("Failed to json_decode: " . json_last_error());
        } else {
            debugMsg("ModelledUI.sendRequst: reply", $reply);
            if (array_key_exists('error', $reply)) {
                echo "<pre>" . $reply['error'] . "</pre>\n";
                die('ModelledUI request failed');
            }
            return $reply;
        }
    }
}
