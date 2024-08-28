<?php
include "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";


$form = new HTML_QuickForm('prototype', 'POST');

$file =& $form->addElement('file','modelfile',"Prototype Model file");
$form->addRule('modelfile', 'You must select a model file', 'required');
$form->addElement('submit', null, 'Submit');
$form->addElement('hidden', 'debug', $debug );

// Try to validate a form
if ($form->validate()) {
  $form->freeze();
  $form->process('process_data', false);
} else {
    $form->display();
}

function libxml_display_error($error)
{
    # NOSONAR To be removed in TORF-298046   
    $return = "<br/>\n"; //NOSONAR
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "<b>Warning $error->code</b>: ";
            break;
        case LIBXML_ERR_ERROR:
            $return .= "<b>Error $error->code</b>: ";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "<b>Fatal Error $error->code</b>: ";
            break;
    }
    $return .= trim($error->message);
    if ($error->file) {
        $return .=    " in <b>$error->file</b>";
    }

    return $return .= " on line <b>$error->line</b>\n";
}

function libxml_display_errors() {
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        print libxml_display_error($error);
    }
    libxml_clear_errors();
}

function process_data() {
    global $file, $ddp_dir, $auth_user, $debug;

    if ($file->isUploadedFile()) {
        # Move the upgrade file to /data/tmp
        $uploadParam = $file->getValue();
        $filename = $uploadParam['name'];
        $file->moveUploadedFile('/data/tmp');
        chmod( '/data/tmp/' . $filename  , 0666 );

        // Enable user error handling
        libxml_use_internal_errors(true);

        $xml = new DOMDocument();
        $xml->load('/data/tmp/' . $filename);

        if (!$xml->schemaValidate('/data/ddp/current/analysis/modelled/instr/models/modelledinstr.xsd')) {
            print '<b>Validation failed Errors!</b>';
            libxml_display_errors();
        } else {
            rename('/data/tmp/' . $filename, '/data/ddp/current/analysis/modelled/instr/models/prototype/' . $filename);
            echo "<p>Validation successful, model stored in prototype directory</p>\n";
        }
    }
}
include "../php/common/finalise.php";
?>
