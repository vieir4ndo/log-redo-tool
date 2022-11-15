<?php

require_once __DIR__ . '/vendor/autoload.php';

use Colors\Color;

function yellow($str, $eol = false) {
    $c = new Color();
    return $c($str)->yellow . ($eol ? PHP_EOL : '');
}

function magenta($str, $eol = false) {
    $c = new Color();
    return $c($str)->magenta . ($eol ? PHP_EOL : '');
}

function green($str, $eol = false) {
    $c = new Color();
    return $c($str)->green . ($eol ? PHP_EOL : '');
}

function white($str, $eol = false) {
    $c = new Color();
    return $c($str)->white . ($eol ? PHP_EOL : '');
}