<?php

if ( (!isset($USE_QF2)) || $USE_QF2 ) {
    require_once "QForm.php";
} else {
    require_once "HTML/QuickForm.php";
}
