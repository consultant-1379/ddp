<?php

require_once 'HTML/QuickForm2.php';
require_once 'HTML/QuickForm2/Element/Select.php';
require_once 'HTML/QuickForm2/Element/InputFile.php';


class HTML_QuickForm { // NOSONAR
    private $form;
    private $fieldset;

    const CONTENT = 'content';
    const RADIO = 'radio';

    public function __construct($formName='', $method='post', $action='') {
        global $debug;

        $attributes = null;
        if ( strlen($action) > 0 ) {
            $attributes = array('action' => $action);
        }
        debugMsg("QFAdaptor attributes:", $attributes);
        $this->form = new HTML_QuickForm2($formName, $method, $attributes); // NOSONAR
        $this->fieldset = $this->form->addElement('fieldset');

        if ( $debug ) {
            $element = $this->form->addElement('hidden', 'debug');
            $element->setValue($debug);
        }
    }

    public function addElement() {
        $args = func_get_args();
        debugMsg("HTML_QuickForm::addElement args", $args);
        if ( gettype($args[0]) === 'string' ) {
            if ( $args[0] === 'header' ) {
                $this->fieldset->setLabel($args[1]);
            } else {
                $element = call_user_func_array("HTML_QuickForm::createElement", $args);
                return $this->fieldset->addElement($element);
            }
        } else {
            return call_user_func_array(array($this->fieldset,'addElement'), $args);
        }
    }

    public function setConstants($constantValues) {
        $datasources = $this->form->getDataSources();
        array_unshift($datasources, new HTML_QuickForm2_DataSource_Array($constantValues));
        $this->form->setDataSources($datasources);
    }

    public function setDefaults($defaultValues) {
        $this->form->addDataSource(new HTML_QuickForm2_DataSource_Array($defaultValues));
    }

    public function applyFilter($name, $function) {
        $element = $this->form->getElementsByName($name)[0];
        $element->addFilter($function);
    }

    public function addRule($name, $message, $type, $format = null, $validation = 'server') { // NOSONAR
        $element = $this->form->getElementsByName($name)[0];
        if ( $type === 'nopunctuation' ) {
            $element->addRule(
                'regex',
                $message,
                '/^[^().\/\*\^\?#!@$%+=,\"\'><~\[\]{}]+$/',
                HTML_QuickForm2_Rule::SERVER
            );
        } elseif ( $type === 'numeric' ) {
            $element->addRule(
                'regex',
                $message,
                '/(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/',
                HTML_QuickForm2_Rule::SERVER
            );
        } else {
            $element->addRule($type, $message, $format, HTML_QuickForm2_Rule::SERVER);
        }
    }

    public function addGroup($elements, $grpName, $grpLabel, $seperator = null) {
        // For Radio groups, we need to keep the group name empty
        // and set each element's name to the group name
        $isRadioGroup = true;
        foreach ($elements as $element) {
            if ( $element->getType() !== self::RADIO ) {
                $isRadioGroup = false;
            }
        }

        $grpFieldSet = $this->fieldset->addFieldset($grpName)->setLabel($grpLabel);
        if ( $isRadioGroup ) {
            $group = $grpFieldSet->addGroup();
        } else {
            $group = $grpFieldSet->addGroup($grpName);
        }

        if ( ! is_null($seperator) ) {
            $group->setSeparator($seperator);
        }
        foreach ($elements as $element) {
            if ( $isRadioGroup ) {
                $element->setName($grpName);
            }
            $group->addElement($element);
        }
    }

    public function getElement($name) {
        return $this->form->getElementsByName($name)[0];
    }

    public function getElementValue($name) {
        return $this->form->getElementsByName($name)[0]->getValue();
    }


    public function display() {
        echo $this->form;
    }

    public function validate() {
        return $this->form->validate();
    }

    public function freeze() {
        return true;
    }

    public function exportValues() {
        return $this->form->getValue();
    }

    public function process($function, $mergeFiles = true) { // NOSONAR
        return call_user_func($function, $this->form->getValue());
    }

    public static function createElement() { // NOSONAR
        global $debug;

        $args = func_get_args();

        debugMsg("HTML_QuickForm::createElement args", $args);

        $type = $args[0];
        if ( $type === 'select' ) {
            $selectData = array('label' => $args[2]);
            if ( ! is_null($args[3]) ) {
                $selectData['options'] = $args[3];
            }
            $attributes = null;
            if ( count($args) >= 5  ) {
                $attributes = $args[4];
            }
            $element = new SelectorAdaptor($args[1], $attributes, $selectData);
        } elseif ( $type === 'submit' ) {
            $element = HTML_QuickForm2_Factory::createElement($type, $args[1], array('value' => $args[2]));
        } elseif ( $type === 'text' || $type === 'password' ) {
            $element = self::createTextInput($args);
        } elseif ( $type === 'header' ) {
            $element = null;
        } elseif ( $type === 'static' ) {
            $data = array( self::CONTENT => $args[2]);
            $element = HTML_QuickForm2_Factory::createElement($type, null, null, $data);
        } elseif ( $type === 'hidden' ) {
            $element = HTML_QuickForm2_Factory::createElement($type, $args[1]);
            $element->setValue($args[2]);
        } elseif ( $type === 'checkbox' || $type === self::RADIO) {
            $attributes = array();
            if ( count($args) >= 5 ) {
                if ( $type === 'checkbox' && $args[4] === 'checked=true' ) {
                    $attributes['checked'] = 'checked';
                } elseif ( $type === self::RADIO ) {
                    $attributes['value'] = $args[4];
                }
            }

            $data = array();
            if ( ! is_null($args[2]) ) {
                $data[self::CONTENT] = $args[2];
            } else {
                $data[self::CONTENT] = $args[3];
            }

            $element = HTML_QuickForm2_Factory::createElement($type, $args[1], $attributes, $data);
        } elseif ( $type === 'file' ) {
            $element = new FileAdaptor($args[1]);
            $element->setLabel($args[2]);
        } else {
            $element = call_user_func_array('HTML_QuickForm2_Factory::createElement', $args);
        }

        return $element;
    }

    private static function createTextInput($args) { // NOSONAR
        global $debug;

        $type = $args[0];
        $name = $args[1];
        $label = $args[2];

        $attributes = array();
        $value = null;
        if ( count($args) >= 4 ) {
            if ( is_array($args[3]) ) {
                $attributes = $args[3];
            } else {
                $attribStr = $args[3];
                debugMsg("createTextInput: attribStr", $attribStr);
                if ( preg_match('/^value="([^"]*)"\s*(.*)/', $attribStr, $matches) === 1) {
                    debugMsg("createTextInput: matches", $matches);
                    $value = $matches[1];
                    $attribStr = $matches[2];
                }
                foreach ( explode(" ", $attribStr) as $part ) {
                    $nameValue = explode("=", $part);
                    if ( count($nameValue) == 1 ) {
                        $attributes[$nameValue[0]] = null;
                    } else {
                        $attributes[$nameValue[0]] = $nameValue[1];
                    }
                }
            }
        }

        debugMsg("createTextInput: label=$label value=$value attributes", $attributes);
        $element =  HTML_QuickForm2_Factory::createElement($type, $name, $attributes);
        $element->setLabel($label);
        if ( ! is_null($value) ) {
            $element->setValue($value);
        }

        return $element;
    }
}


class SelectorAdaptor extends HTML_QuickForm2_Element_Select {
    public function setSelected($value) {
        $this->setValue($value);
    }

    public function setMultiple($multiple) {
        if ( $multiple ) {
            $this->setAttribute('multiple', 'multiple');
        }
    }

    public function setSize($value) {
        $this->setAttribute('size', (string)$value);
    }
}

class FileAdaptor extends HTML_QuickForm2_Element_InputFile {
    public function isUploadedFile() {
        return $this->validate();
    }

    public function moveUploadedFile($dest, $fileName = '') {
        global $debug;

        $value = $this->getValue();
        debugMsg("moveUploadedFile: value", $value);

        if ($dest != ''  && substr($dest, -1) != '/') {
            $dest .= '/';
        }
        $fileName = ($fileName != '') ? $fileName : basename($value['name']);
        return move_uploaded_file($value['tmp_name'], $dest . $fileName); // NOSONAR
    }
}
