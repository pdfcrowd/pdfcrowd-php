#!/usr/bin/php
<?php

// $argv .. array
// $argc

require 'pdfcrowd.php';

if ($argc < 3) {
    echo "usage: apiserver-test.php username api_key apihost\n";
    exit(1);
}

$c = new Pdfcrowd($argv[1], $argv[2], $argv[3]);
$c->convertURI('https://storage.googleapis.com/pdfcrowd-legacy-tests/tests/webtopdfcom.html');
$c->convertHtml('raw html');
$c->convertFile('../test_files/in/simple.html');

