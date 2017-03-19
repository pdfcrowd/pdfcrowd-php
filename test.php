#!/usr/bin/php
<?php

// $argv .. array
// $argc

require 'pdfcrowd.php';

if ($argc < 2) {
    echo "usage: test.php username api_key [apihost [http-port https-port]]\n";
    exit(1);
}

if ($argv > 3) {
    Pdfcrowd::$api_host = $argv[3];
}

if ($argv == 6) {
    Pdfcrowd::$http_port = $argv[4];
    Pdfcrowd::$https_port = $argv[5];
}

$api_host = Pdfcrowd::$api_host;
$http_port = Pdfcrowd::$http_port;
$https_port = Pdfcrowd::$https_port;
echo "using {$api_host} ports {$http_port} {$https_port}\n";


chdir(dirname($argv[0]));
$test_dir = './test_files';

function out_stream($name, $use_ssl)
{
    $fname = "./test_files/out/php_client_{$name}";
    if ($use_ssl)
        $fname .= "_ssl";
    return fopen($fname . '.pdf', 'wb');
}

$html = "<html><body>Uploaded content!</body></html>";
$client = new Pdfcrowd($argv[1], $argv[2]);
foreach(array(False, True) as $i => $use_ssl) {
    $client->useSSL($use_ssl);
    try
    {
        $ntokens = $client->numTokens();
        $client->convertURI('https://storage.googleapis.com/pdfcrowd-legacy-tests/tests/webtopdfcom.html', out_stream('uri', $use_ssl));
        $client->convertHtml($html, out_stream('content', $use_ssl));
        $client->convertFile($test_dir . '/in/simple.html', out_stream('upload', $use_ssl));
        $client->convertFile($test_dir . '/in/archive.tar.gz', out_stream('archive', $use_ssl));
        $after_tokens = $client->numTokens();
        echo "remaining tokens: {$after_tokens}\n";
        if ($ntokens - 4 != $after_tokens) {
            throw new Exception("Mismatch in the number of tokens.");
        }
    }
    catch(PdfcrowdException $e)
    {
        echo "EXCEPTION: " . $e->getMessage();
        exit(1);
    }
}


$tests = array(
    'setPageWidth' => 500,
    'setPageHeight' => -1,
    'setHorizontalMargin' => 0,
    'setVerticalMargin' => 72,
    'setEncrypted' => True,
    'setUserPassword' => 'userpwd',
    'setOwnerPassword' => 'ownerpwd',
    'setNoPrint' => True,
    'setNoModify' => True,
    'setNoCopy' => True,
    'setPageLayout' => Pdfcrowd::CONTINUOUS,
    'setPageMode' => Pdfcrowd::FULLSCREEN,
    'setFooterText' => '%p/%n | source %u',
    'enableImages' => False,
    'enableBackgrounds' => False,
    'setHtmlZoom' => 300,
    'enableJavaScript' => False,
    'enableHyperlinks' => False,
    'setDefaultTextEncoding' => 'iso-8859-1',
    'usePrintMedia' => True,
    'setMaxPages' => 1,
    'enablePdfcrowdLogo' => True,
    'setInitialPdfZoomType' => Pdfcrowd::FIT_PAGE,
    'setInitialPdfExactZoom' => 113,
    'setPdfScalingFactor' => 0.5,
    'setFooterHtml' => '<b>bold</b> and <i>italic</i> <img src="http://s3.pdfcrowd.com/test-resources/logo175x30.png" />',
    'setFooterUrl' => 'http://s3.pdfcrowd.com/test-resources/footer.html',
    'setHeaderHtml' => 'page %p out of %n',
    'setHeaderUrl' => 'http://s3.pdfcrowd.com/test-resources/header.html',
    'setAuthor' => 'Custom Author',
    'setPageBackgroundColor' => 'ee82EE',
    'setTransparentBackground' => True,
    'setUserAgent' => "test user agent"
    );

try
{
    foreach($tests as $method => $arg)
    {
        $client = new Pdfcrowd($argv[1], $argv[2]);
        $client->$method($arg);
        $client->setVerticalMargin('1in');
        $client->convertFile($test_dir . '/in/simple.html', out_stream(strtolower($method), False));
    }
}
catch(PdfcrowdException $e)
{
    echo "EXCEPTION: " . $e->getMessage();
    exit(1);
}

// margins
$client = new Pdfcrowd($argv[1], $argv[2]);
$client->setPageMargins('0.25in', '0.5in', '0.75in', '1.0in');
$client->convertHtml('<div style="background-color:red;height:100%">4 margins</div>', out_stream('4margins', False));


// expected failures
$failures = array(
    array("convertHtml", "", "must not be empty"),
    array("convertFile", "does-not-exist.html", "not found"),
    array("convertFile", "/", "not a directory"),
    array("convertFile", $test_dir."/in/empty.html", "must not be empty"),
    array("convertURI", "domain.com", "must start with"),    
    array("convertURI", "HtTp://s3.pdfcrowd.com/this/url/does/not/exist/", "Received a non-2xx response")
    );
$client = new Pdfcrowd($argv[1], $argv[2]);
$client->setFailOnNon200(True);
foreach($failures as $failure) {
    try {
        $client->$failure[0]($failure[1]);
        echo "FAILED expected an exception: ${failure}\n";
        exit(1);
    } catch(PdfcrowdException $e) {
        if (!strstr($e->getMessage(), $failure[2])) {
            echo "error message [". $e->getMessage() ."] is expected to contain [".$failure[2]."]\n";
            exit(1);
        }
    }
}
    


?>