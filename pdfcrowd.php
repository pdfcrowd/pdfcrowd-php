<?php

// Copyright (C) 2009-2018 pdfcrowd.com
//
// Permission is hereby granted, free of charge, to any person
// obtaining a copy of this software and associated documentation
// files (the "Software"), to deal in the Software without
// restriction, including without limitation the rights to use,
// copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following
// conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
// OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
// FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
// OTHER DEALINGS IN THE SOFTWARE.

namespace {

//
// Thrown when an error occurs.
//
class PdfcrowdException extends Exception {
    // custom string representation of object
    public function __toString() {
        if ($this->code) {
            return "[{$this->code}] {$this->message}\n";
        } else {
            return "{$this->message}\n";
        }
    }
}

// ======================================
// === PDFCrowd legacy version client ===
// ======================================

//
// PDFCrowd API client.
//
class PdfCrowd {
    //
    // PDFCrowd constructor.
    //
    // $username - your username at PDFCrowd
    // $apikey  - your API key
    // $hostname - API hostname, defaults to pdfcrowd.com
    //
    function __construct($username, $apikey, $hostname=null){
        if ($hostname)
            $this->hostname = $hostname;
        else
            $this->hostname = self::$api_host;
        $this->useSSL(false);
        $this->fields = array(
            'username' => $username,
            'key' => $apikey,
            'pdf_scaling_factor' => 1,
            'html_zoom' => 200);
        $this->proxy_name = null;
        $this->proxy_port = null;
        $this->proxy_username = "";
        $this->proxy_password = "";

        $this->user_agent = "pdfcrowd_php_client_".self::$client_version."_(http://pdfcrowd.com)";
    }

    //
    // Converts an in-memory html document.
    //
    // $src       - a string containing a html document
    // $outstream - output stream, if null then the return value is a string
    //              containing the PDF
    //
    function convertHtml($src, $outstream=null){
        if (!$src) {
            throw new PdfcrowdException("convertHTML(): the src parameter must not be empty");
        }

        $this->fields['src'] = $src;
        $uri = $this->api_prefix . "/pdf/convert/html/";
        $postfields = http_build_query($this->fields, '', '&');
        return $this->http_post($uri, $postfields, $outstream);
    }

    //
    // Converts an html file.
    //
    // $src       - a path to an html file
    // $outstream - output stream, if null then the return value is a string
    //              containing the PDF
    //
    function convertFile($src, $outstream=null) {
        $src = trim($src);

        if (!file_exists($src)) {
            $cwd = getcwd();
            throw new PdfcrowdException("convertFile(): '{$src}' not found
Possible reasons:
 1. The file is missing.
 2. You misspelled the file name.
 3. You use a relative file path (e.g. 'index.html') but the current working
    directory is somewhere else than you expect: '{$cwd}'
    Generally, it is safer to use an absolute file path instead of a relative one.
");
        }

        if (is_dir($src)) {
            throw new PdfcrowdException("convertFile(): '{$src}' must be file, not a directory");
        }

        if (!is_readable($src)) {
            throw new PdfcrowdException("convertFile(): cannot read '{$src}', please check if the process has sufficient permissions");
        }

        if (!filesize($src)) {
            throw new PdfcrowdException("convertFile(): '{$src}' must not be empty");
        }

        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            $this->fields['src'] = new CurlFile($src);
        } else {
            $this->fields['src'] = '@' . $src;
        }

        $uri = $this->api_prefix . "/pdf/convert/html/";
        return $this->http_post($uri, $this->fields, $outstream);
    }

    //
    // Converts a web page.
    //
    // $src       - a web page URL
    // $outstream - output stream, if null then the return value is a string
    //              containing the PDF
    //
    function convertURI($src, $outstream=null){
        $src = trim($src);
        if (!preg_match("/^https?:\/\/.*/i", $src)) {
            throw new PdfcrowdException("convertURI(): the URL must start with http:// or https:// (got '$src')");
        }

        $this->fields['src'] = $src;
        $uri = $this->api_prefix . "/pdf/convert/uri/";
        $postfields = http_build_query($this->fields, '', '&');
        return $this->http_post($uri, $postfields, $outstream);
    }

    //
    // Returns the number of available conversion tokens.
    //
    function numTokens() {
        $username = $this->fields['username'];
        $uri = $this->api_prefix . "/user/{$username}/tokens/";
        $arr = array('username' => $this->fields['username'],
                     'key' => $this->fields['key']);
        $postfields = http_build_query($arr, '', '&');
        $ntokens = $this->http_post($uri, $postfields, NULL);
        return (int)$ntokens;
    }

    function useSSL($use_ssl) {
        if($use_ssl) {
            $this->port = self::$https_port;
            $this->scheme = 'https';
        }
        else {
            $this->port = self::$http_port;
            $this->scheme = 'http';
        }

        $this->api_prefix = "{$this->scheme}://{$this->hostname}/api";
    }

    function setPageWidth($value) {
        $this->fields['width'] = $value;
    }

    function setPageHeight($value) {
        $this->fields['height'] = $value;
    }

    function setHorizontalMargin($value) {
        $this->fields['margin_right'] = $this->fields['margin_left'] = $value;
    }

    function setVerticalMargin($value) {
        $this->fields['margin_top'] = $this->fields['margin_bottom'] = $value;
    }

    function setPageMargins($top, $right, $bottom, $left) {
      $this->fields['margin_top'] = $top;
      $this->fields['margin_right'] = $right;
      $this->fields['margin_bottom'] = $bottom;
      $this->fields['margin_left'] = $left;
    }

    function setEncrypted($val=True) {
        $this->set_or_unset($val, 'encrypted');
    }

    function setUserPassword($pwd) {
        $this->set_or_unset($pwd, 'user_pwd');
    }

    function setOwnerPassword($pwd) {
        $this->set_or_unset($pwd, 'owner_pwd');
    }

    function setNoPrint($val=True) {
        $this->set_or_unset($val, 'no_print');
    }

    function setNoModify($val=True) {
        $this->set_or_unset($val, 'no_modify');
    }

    function setNoCopy($val=True) {
        $this->set_or_unset($val, 'no_copy');
    }

    // constants for setPageLayout()
    const SINGLE_PAGE = 1;
    const CONTINUOUS = 2;
    const CONTINUOUS_FACING = 3;

    function setPageLayout($value) {
        assert($value > 0 && $value <= 3);
        $this->fields['page_layout'] = $value;
    }

    // constants for setPageMode()
    const NONE_VISIBLE = 1;
    const THUMBNAILS_VISIBLE = 2;
    const FULLSCREEN = 3;

    function setPageMode($value) {
        assert($value > 0 && $value <= 3);
        $this->fields['page_mode'] = $value;
    }

    function setFooterText($value) {
        $this->set_or_unset($value, 'footer_text');
    }

    function enableImages($value=True) {
        $this->set_or_unset(!$value, 'no_images');
    }

    function enableBackgrounds($value=True) {
        $this->set_or_unset(!$value, 'no_backgrounds');
    }

    function setHtmlZoom($value) {
        $this->set_or_unset($value, 'html_zoom');
    }

    function enableJavaScript($value=True) {
        $this->set_or_unset(!$value, 'no_javascript');
    }

    function enableHyperlinks($value=True) {
        $this->set_or_unset(!$value, 'no_hyperlinks');
    }

    function setDefaultTextEncoding($value) {
        $this->set_or_unset($value, 'text_encoding');
    }

    function usePrintMedia($value=True) {
        $this->set_or_unset($value, 'use_print_media');
    }

    function setMaxPages($value) {
        $this->fields['max_pages'] = $value;
    }

    function enablePdfcrowdLogo($value=True) {
        $this->set_or_unset($value, 'pdfcrowd_logo');
    }

    // constants for setInitialPdfZoomType()
    const FIT_WIDTH = 1;
    const FIT_HEIGHT = 2;
    const FIT_PAGE = 3;

    function setInitialPdfZoomType($value) {
        assert($value>0 && $value<=3);
        $this->fields['initial_pdf_zoom_type'] = $value;
    }

    function setInitialPdfExactZoom($value) {
        $this->fields['initial_pdf_zoom_type'] = 4;
        $this->fields['initial_pdf_zoom'] = $value;
    }

    function setPdfScalingFactor($value) {
        $this->fields['pdf_scaling_factor'] = $value;
    }

    function setAuthor($value) {
        $this->fields['author'] = $value;
    }

    function setFailOnNon200($value) {
        $this->fields['fail_on_non200'] = $value;
    }

    function setFooterHtml($value) {
        $this->fields['footer_html'] = $value;
    }

    function setFooterUrl($value) {
        $this->fields['footer_url'] = $value;
    }

    function setHeaderHtml($value) {
        $this->fields['header_html'] = $value;
    }

    function setHeaderUrl($value) {
        $this->fields['header_url'] = $value;
    }

    function setPageBackgroundColor($value) {
        $this->fields['page_background_color'] = $value;
    }

    function setTransparentBackground($value=True) {
        $this->set_or_unset($value, 'transparent_background');
    }

    function setPageNumberingOffset($value) {
        $this->fields['page_numbering_offset'] = $value;
    }

    function setHeaderFooterPageExcludeList($value) {
        $this->fields['header_footer_page_exclude_list'] = $value;
    }

    function setWatermark($url, $offset_x=0, $offset_y=0) {
        $this->fields["watermark_url"] = $url;
        $this->fields["watermark_offset_x"] = $offset_x;
        $this->fields["watermark_offset_y"] = $offset_y;
    }

    function setWatermarkRotation($angle) {
        $this->fields["watermark_rotation"] = $angle;
    }

    function setWatermarkInBackground($val=True) {
        $this->set_or_unset($val, "watermark_in_background");
    }

    function setProxy($proxyname, $port, $username="", $password="") {
        $this->proxy_name = $proxyname;
        $this->proxy_port = $port;
        $this->proxy_username = $username;
        $this->proxy_password = $password;
    }

    function setUserAgent($user_agent) {
        $this->user_agent = $user_agent;
    }

    function setTimeout($timeout) {
        if (is_int($timeout) && $timeout > 0) {
            $this->curlopt_timeout = $timeout;
        }
    }




    // ----------------------------------------------------------------------
    //
    //                        Private stuff
    //

    private $fields, $scheme, $port, $api_prefix, $curlopt_timeout;
    private $hostname;
    private $proxy_name;
    private $proxy_port;
    private $proxy_username;
    private $proxy_password;
    private $user_agent;
    private $http_code;
    private $error;
    private $outstream;

    public static $client_version = "6.5.4";
    public static $http_port = 80;
    public static $https_port = 443;
    public static $api_host = 'pdfcrowd.com';

    private static $missing_curl = 'pdfcrowd.php requires cURL which is not installed on your system.

How to install:
  Windows: uncomment/add the "extension=php_curl.dll" line in php.ini
  Linux:   should be a part of the distribution,
           e.g. on Debian/Ubuntu run "sudo apt-get install php5-curl"

You need to restart your web server after installation.

Links:
 Installing the PHP/cURL binding:  <http://curl.haxx.se/libcurl/php/install.html>
 PHP/cURL documentation:           <http://cz.php.net/manual/en/book.curl.php>';


    private function http_post($url, $postfields, $outstream) {
        if (!function_exists("curl_init")) {
            throw new PdfcrowdException(self::$missing_curl);
        }

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL,$url);
        curl_setopt($c, CURLOPT_HEADER, false);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_PORT, $this->port);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postfields);
        if (!PHP_ZTS) {
            // don't disable CURLOPT_DNS_USE_GLOBAL_CACHE in ZTS mode
            // it's disabled by default and
            // calling this method produces a warning always
            curl_setopt($c, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        }
        curl_setopt($c, CURLOPT_USERAGENT, $this->user_agent);
        if (isset($this->curlopt_timeout)) {
            curl_setopt($c, CURLOPT_TIMEOUT, $this->curlopt_timeout);
        }
        if ($outstream) {
            $this->outstream = $outstream;
            curl_setopt($c, CURLOPT_WRITEFUNCTION, array($this, 'receive_to_stream'));
        }

        if ($this->scheme == 'https' && self::$api_host == 'pdfcrowd.com') {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($this->proxy_name) {
            curl_setopt($c, CURLOPT_PROXY, $this->proxy_name . ":" . $this->proxy_port);
            if ($this->proxy_username) {
                curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($c, CURLOPT_PROXYUSERPWD, $this->proxy_username . ":" . $this->proxy_password);
            }
        }

        $this->http_code = 0;
        $this->error = "";

        $response = curl_exec($c);
        $this->http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $error_str = curl_error($c);
        $error_nr = curl_errno($c);
        curl_close($c);

        if ($error_nr != 0) {
            throw new PdfcrowdException($error_str, $error_nr);
        }
        else if ($this->http_code == 200) {
            if ($outstream == NULL) {
                return $response;
            }
        } else {
            throw new PdfcrowdException($this->error ? $this->error : $response, $this->http_code);
        }
    }

    private function receive_to_stream($curl, $data) {
        if ($this->http_code == 0) {
            $this->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        }

        if ($this->http_code >= 400) {
            $this->error = $this->error . $data;
            return strlen($data);
        }

        $written = fwrite($this->outstream, $data);
        if ($written != strlen($data)) {
            if (get_magic_quotes_runtime()) {
                throw new PdfcrowdException("Cannot write the PDF file because the 'magic_quotes_runtime' setting is enabled.
Please disable it either in your php.ini file, or in your code by calling 'set_magic_quotes_runtime(false)'.");
            } else {
                throw new PdfcrowdException('Writing the PDF file failed. The disk may be full.');
            }
        }
        return $written;
    }

    private function set_or_unset($val, $field) {
        if ($val)
            $this->fields[$field] = $val;
        else
            unset($this->fields[$field]);
    }
}

}

// =====================================
// === PDFCrowd cloud version client ===
// =====================================

namespace Pdfcrowd {

class Error extends \Exception {
    protected $error;
    protected $reasonCode;
    protected $docLink;

    public function __construct($error, $statusCode = null)
    {
        parent::__construct($error, $statusCode);

        $this->error = $error;
        $this->reasonCode = -1;
        $this->docLink = '';

        $pattern = '/^(\d+)\.(\d+)\s+-\s+(.*?)(?:\s+Documentation link:\s+(.*))?$/s';
        if (preg_match($pattern, $error, $matches)) {
            $this->code = $matches[1];
            $this->reasonCode = $matches[2];
            $this->message = $matches[3];
            $this->docLink = isset($matches[4]) ? $matches[4] : '';
        } else {
            $this->message = $error;
            if ($this->code) {
                $this->error = "{$this->code} - {$this->message}";
            }
        }
    }

    public function __toString()
    {
        return $this->reasonCode ? $this->error : $this->message;
    }

    public function getStatusCode()
    {
        return $this->code;
    }

    public function getReasonCode()
    {
        return $this->reasonCode;
    }

    public function getDocumentationLink()
    {
        return $this->docLink;
    }
}

function create_invalid_value_message($value, $field, $converter, $hint, $id) {
    $message = "400.311 - Invalid value '$value' for the '$field' option.";
    if($hint != null) {
        $message = $message . " " . $hint;
    }
    return $message . " " . "Documentation link: https://www.pdfcrowd.com/api/$converter-php/ref/#$id";
}

class ConnectionHelper
{
    private static $REQ_NOT_AVAILABLE = 'pdfcrowd.php can not post HTTP request.
Solution 1: Edit your php.ini file and enable:
    allow_url_fopen = On

Solution 2: Install cURL for PHP:
    Windows: uncomment/add the "extension=php_curl.dll" line in your php.ini
    Linux: should be a part of the distribution,
           e.g. on Debian/Ubuntu run "sudo apt-get install php-curl"

You need to restart your web server after installation.';

    function __construct($user_name, $api_key){
        $this->host = getenv('PDFCROWD_HOST') ?: 'api.pdfcrowd.com';
        $this->user_name = $user_name;
        $this->api_key = $api_key;

        $this->reset_response_data();
        $this->setProxy(null, null, null, null);
        $this->setUseHttp(false);
        $this->setUserAgent('pdfcrowd_php_client/6.5.4 (https://pdfcrowd.com)');

        $this->retry_count = 1;
        $this->converter_version = '24.04';

        // find available method for POST request
        if(!ini_get('allow_url_fopen')) {
            if(!function_exists("curl_init")) {
                throw new Error(self::$REQ_NOT_AVAILABLE);
            }
            $this->use_curl = true;
        }
        else
        {
            $this->use_curl = false;
        }
    }

    private $host;
    private $user_name;
    private $api_key;
    private $port;
    private $use_http;
    private $scheme;
    private $url;
    private $debug_log_url;
    private $credits;
    private $consumed_credits;
    private $job_id;
    private $page_count;
    private $total_page_count;
    private $output_size;
    private $user_agent;

    private $proxy_host;
    private $proxy_port;
    private $proxy_user_name;
    private $proxy_password;

    private $retry_count;
    private $retry;
    private $converter_version;
    private $error_message;

    private $use_curl;

    private static $SSL_ERRORS = array(35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91);

    const CLIENT_VERSION = '6.5.4';
    public static $MULTIPART_BOUNDARY = '----------ThIs_Is_tHe_bOUnDary_$';

    private function add_file_field($name, $file_name, $data, &$body) {
        $body .= "--" . self::$MULTIPART_BOUNDARY . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . $name . '";' . ' filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: application/octet-stream' . "\r\n";
        $body .= "\r\n";
        $body .= $data . "\r\n";
    }

    private function reset_response_data() {
        $this->debug_log_url = null;
        $this->credits = 999999;
        $this->consumed_credits = 0;
        $this->job_id = '';
        $this->page_count = 0;
        $this->total_page_count = 0;
        $this->output_size = 0;
        $this->retry = 0;
    }

    private function build_body($fields, $files, $raw_data) {
        $body = '';

        foreach ($fields as $name => $content) {
            $body .= "--" . self::$MULTIPART_BOUNDARY . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $body .= $content . "\r\n";
        }

        foreach ($files as $name => $file_name) {
            $this->add_file_field($name, $file_name, file_get_contents($file_name), $body);
        }

        foreach ($raw_data as $name => $data) {
            $this->add_file_field($name, $name, $data, $body);
        }

        return $body . "--" . self::$MULTIPART_BOUNDARY . "--\r\n";
    }

    private function output_body($http_code, $body, $out_stream) {
        if ($http_code >= 300)
            throw new Error($body, $http_code);

        if ($out_stream == null)
            return $body;

        $written = fwrite($out_stream, $body);
        if ($written != strlen($body)) {
            if (get_magic_quotes_runtime()) {
                throw new Error("Cannot write the PDF file because the 'magic_quotes_runtime' setting is enabled. Please disable it either in your php.ini file, or in your code by calling 'set_magic_quotes_runtime(false)'.");
            }
            throw new Error('Writing the PDF file failed. The disk may be full.');
        }
    }

    private function should_retry($code) {
        if (($code == 502 || $code == 503) && $this->retry_count > $this->retry) {
            // http error 502 occures sometimes due to network problems
            // so retry request
            $this->retry++;

            // wait a while before retry
            usleep($this->retry * 100000);
            return true;
        }
        return false;
    }

    public function post($fields, $files, $raw_data, $out_stream = null) {
        if ($this->proxy_host && !$this->use_http)
            throw new Error('HTTPS over a proxy is not supported.');

        $this->reset_response_data();

        if (!($this->use_curl || getenv('PDFCROWD_UNIT_TEST_MODE'))) {
            if (!$this->use_http && !extension_loaded('openssl')) {
                throw new Error('The Open SSL PHP extension is not enabled. Check your php.ini file.');
            }

            // use implementation without curl
            return $this->post_no_curl($fields, $files, $raw_data, $out_stream);
        }

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $this->url . $this->converter_version . '/');
        curl_setopt($c, CURLOPT_PORT, $this->port);
        curl_setopt($c, CURLOPT_HEADER, true);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POST, true);
        if (!PHP_ZTS) {
            // don't disable CURLOPT_DNS_USE_GLOBAL_CACHE in ZTS mode
            // it's disabled by default and
            // calling this method produces a warning always
            curl_setopt($c, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        }
        curl_setopt($c, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($c, CURLOPT_USERPWD, "{$this->user_name}:{$this->api_key}");

        if ($this->scheme == 'https' && $this->host == 'api.pdfcrowd.com') {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($this->proxy_host) {
            curl_setopt($c, CURLOPT_PROXY, $this->proxy_host . ':' . $this->proxy_port);
            if ($this->proxy_user_name) {
                curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($c, CURLOPT_PROXYUSERPWD, $this->proxy_user_name . ':' . $this->proxy_password);
            }
        }

        if ($files === null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $fields);
        } else {
            $body = $this->build_body($fields, $files, $raw_data);

            curl_setopt($c, CURLOPT_HTTPHEADER , array(
                'Content-Type: multipart/form-data; boundary=' . self::$MULTIPART_BOUNDARY,
                'Content-Length: ' . strlen($body)));
            curl_setopt($c, CURLOPT_POSTFIELDS, $body);
        }

        $response = $this->exec_request($c);
        $http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $error_str = curl_error($c);
        $error_nr = curl_errno($c);
        $header_size = curl_getinfo($c, CURLINFO_HEADER_SIZE);
        $headers = array_map("rtrim", explode("\n", substr($response, 0, $header_size)));
        $body = substr($response, $header_size);
        $this->parse_response_headers($headers);
        curl_close($c);

        if ($error_nr != 0) {
            if (in_array($error_nr, self::$SSL_ERRORS)) {
                throw new Error("400.356 - There was a problem connecting to PDFCrowd servers over HTTPS:\n" .
                                "{$error_str} ({$error_nr})" .
                                "\nYou can still use the API over HTTP, you just need to add the following line right after PDFCrowd client initialization:\n\$client->setUseHttp(true);",
                                0);
            }
            throw new Error($error_str, $error_nr);
        }

        return $this->output_body($http_code, $body, $out_stream);
    }

    private function exec_request($c) {
        $response = curl_exec($c);
        $http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        if ($this->should_retry($http_code)) {
            return $this->exec_request($c);
        }
        return $response;
    }

    private function post_no_curl($fields, $files, $raw_data, $out_stream) {
        $body = $this->build_body($fields, $files, $raw_data);
        $auth = base64_encode("{$this->user_name}:{$this->api_key}");
        $headers = array(
            'Content-Type: multipart/form-data; boundary=' . self::$MULTIPART_BOUNDARY,
            'Content-Length: ' . strlen($body),
            'Authorization: Basic ' . $auth,
            'User-Agent: ' . $this->user_agent
        );

        $context_options = array(
            'http' => array(
                'method' => 'POST',
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 300
            )
        );

        if ($this->host != 'api.pdfcrowd.com') {
            $context_options['ssl'] = array(
                'verify_peer_name' => false
            );
        }

        if ($this->proxy_host) {
            $context_options['http']['proxy'] = $this->proxy_host . ':' . $this->proxy_port;
            $context_options['http']['request_fulluri'] = true;
            if ($this->proxy_user_name) {
                $auth = base64_encode("{$this->proxy_user_name}:{$this->proxy_password}");
                $headers[] = "Proxy-Authorization: Basic $auth";
            }
        }

        $context_options['http']['header'] = $headers;

        $context = stream_context_create($context_options);
        $response = $this->exec_request_no_curl(
            $this->url . $this->converter_version . '/', $context);

        return $this->output_body($response['code'], $response['body'], $out_stream);
    }

    private function parse_response_headers($headers) {
        $code = 555;
        foreach ($headers as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)\s*.*/i', $header, $matches)) {
                $code = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Job-Id:\s+(.*)/i', $header, $matches)) {
                $this->job_id = $matches[1];
            } else if(preg_match('/X-Pdfcrowd-Pages:\s+(.*)/i', $header, $matches)) {
                $this->page_count = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Total-Pages:\s+(.*)/i', $header, $matches)) {
                $this->total_page_count = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Output-Size:\s+(.*)/i', $header, $matches)) {
                $this->output_size = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Remaining-Credits:\s+(.*)/i', $header, $matches)) {
                $this->credits = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Consumed-Credits:\s+(.*)/i', $header, $matches)) {
                $this->consumed_credits = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Debug-Log:\s+(.*)/i', $header, $matches)) {
                $this->debug_log_url = $matches[1];
            }
        }
        return $code;
    }

    function custom_error_handler($severity, $message, $file, $line) {
        $this->error_message .= $message . "\n";
    }

    private function exec_request_no_curl($url, $context) {
        $this->error_message = '';
        set_error_handler(array($this, 'custom_error_handler'));
        $body = file_get_contents($url, false, $context);
        restore_error_handler();

        if($body === false) {
            if(strpos($this->error_message, "SSL") === false) {
                if(strpos($this->error_message, "allow_url_fopen") !== false) {
                    throw new Error($this->error_message .
                                    self::$REQ_NOT_AVAILABLE);
                }
                throw new Error($this->error_message);
            }
            throw new Error("400.356 - There was a problem connecting to PDFCrowd servers over HTTPS:\n" .
                            $this->error_message .
                            "\nYou can still use the API over HTTP, you just need to add the following line right after PDFCrowd client initialization:\n\$client->setUseHttp(true);",
                            0);
        }

        $code = $this->parse_response_headers($http_response_header);
        if ($this->should_retry($code)) {
            return $this->exec_request_no_curl($url, $context);
        }
        return array(
            'code' => $code,
            'body' => $body
        );
    }

    function setUseHttp($use_http) {
        if($use_http) {
            $this->port = 80;
            $this->scheme = 'http';
        }
        else {
            $this->port = 443;
            $this->scheme = 'https';
        }

        $this->use_http = $use_http;
        $this->url = "{$this->scheme}://{$this->host}/convert/";
    }

    function setProxy($host, $port, $user_name, $password) {
        $this->proxy_host = $host;
        $this->proxy_port = $port;
        $this->proxy_user_name = $user_name;
        $this->proxy_password = $password;
    }

    function setUserAgent($user_agent) {
        $this->user_agent = $user_agent;
    }

    function setRetryCount($retry_count) {
        $this->retry_count = $retry_count;
    }

    function setConverterVersion($converter_version) {
        $this->converter_version = $converter_version;
    }

    function getDebugLogUrl() {
        return $this->debug_log_url;
    }

    function getRemainingCreditCount() {
        return $this->credits;
    }

    function getConsumedCreditCount() {
        return $this->consumed_credits;
    }

    function getJobId() {
        return $this->job_id;
    }

    function getPageCount() {
        return $this->page_count;
    }

    function getTotalPageCount() {
        return $this->total_page_count;
    }

    function getOutputSize() {
        return $this->output_size;
    }

    function getConverterVersion() {
        return $this->converter_version;
    }

    function setUseCurl($use_curl) {
        $this->use_curl = $use_curl;
    }
}

// generated code

/**
 * Conversion from HTML to PDF.
 *
 * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/">https://pdfcrowd.com/api/html-to-pdf-php/</a>
 */
class HtmlToPdfClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#__construct">https://pdfcrowd.com/api/html-to-pdf-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'html', 'output_format'=>'pdf');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "html-to-pdf", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "html-to-pdf", "The string must not be empty.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "html-to-pdf", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "html-to-pdf", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "html-to-pdf", "The string must not be empty.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_string">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_string</a>
     */
    function convertString($text) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "convertString", "html-to-pdf", "The string must not be empty.", "convert_string"), 470);
        
        $this->fields['text'] = $text;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_string_to_stream">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_string_to_stream</a>
     */
    function convertStringToStream($text, $out_stream) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "convertStringToStream::text", "html-to-pdf", "The string must not be empty.", "convert_string_to_stream"), 470);
        
        $this->fields['text'] = $text;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_string_to_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_string_to_file</a>
     */
    function convertStringToFile($text, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStringToFile::file_path", "html-to-pdf", "The string must not be empty.", "convert_string_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStringToStream($text, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_stream">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "html-to-pdf", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_zip_main_filename">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_zip_main_filename</a>
     */
    function setZipMainFilename($filename) {
        $this->fields['zip_main_filename'] = $filename;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_size">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_size</a>
     */
    function setPageSize($size) {
        if (!preg_match("/(?i)^(A0|A1|A2|A3|A4|A5|A6|Letter)$/", $size))
            throw new Error(create_invalid_value_message($size, "setPageSize", "html-to-pdf", "Allowed values are A0, A1, A2, A3, A4, A5, A6, Letter.", "set_page_size"), 470);
        
        $this->fields['page_size'] = $size;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_width">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_width</a>
     */
    function setPageWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setPageWidth", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_page_width"), 470);
        
        $this->fields['page_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_height">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_height</a>
     */
    function setPageHeight($height) {
        if (!preg_match("/(?i)^0$|^\-1$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setPageHeight", "html-to-pdf", "The value must be -1 or specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_page_height"), 470);
        
        $this->fields['page_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_dimensions">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_dimensions</a>
     */
    function setPageDimensions($width, $height) {
        $this->setPageWidth($width);
        $this->setPageHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_orientation">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_orientation</a>
     */
    function setOrientation($orientation) {
        if (!preg_match("/(?i)^(landscape|portrait)$/", $orientation))
            throw new Error(create_invalid_value_message($orientation, "setOrientation", "html-to-pdf", "Allowed values are landscape, portrait.", "set_orientation"), 470);
        
        $this->fields['orientation'] = $orientation;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_top">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_top</a>
     */
    function setMarginTop($top) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $top))
            throw new Error(create_invalid_value_message($top, "setMarginTop", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_top"), 470);
        
        $this->fields['margin_top'] = $top;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_right">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_right</a>
     */
    function setMarginRight($right) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $right))
            throw new Error(create_invalid_value_message($right, "setMarginRight", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_right"), 470);
        
        $this->fields['margin_right'] = $right;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_bottom">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_bottom</a>
     */
    function setMarginBottom($bottom) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $bottom))
            throw new Error(create_invalid_value_message($bottom, "setMarginBottom", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_bottom"), 470);
        
        $this->fields['margin_bottom'] = $bottom;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_left">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_margin_left</a>
     */
    function setMarginLeft($left) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $left))
            throw new Error(create_invalid_value_message($left, "setMarginLeft", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_left"), 470);
        
        $this->fields['margin_left'] = $left;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_margins">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_margins</a>
     */
    function setNoMargins($value) {
        $this->fields['no_margins'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_margins">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_margins</a>
     */
    function setPageMargins($top, $right, $bottom, $left) {
        $this->setMarginTop($top);
        $this->setMarginRight($right);
        $this->setMarginBottom($bottom);
        $this->setMarginLeft($left);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_print_page_range">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_print_page_range</a>
     */
    function setPrintPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*)|odd|even|last)\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*)|odd|even|last)\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setPrintPageRange", "html-to-pdf", "A comma separated list of page numbers or ranges. Special strings may be used, such as 'odd', 'even' and 'last'.", "set_print_page_range"), 470);
        
        $this->fields['print_page_range'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_viewport_width">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_viewport_width</a>
     */
    function setContentViewportWidth($width) {
        if (!preg_match("/(?i)^(balanced|small|medium|large|extra-large|[0-9]+(px)?)$/", $width))
            throw new Error(create_invalid_value_message($width, "setContentViewportWidth", "html-to-pdf", "The value must be 'balanced', 'small', 'medium', 'large', 'extra-large', or a number in the range 96-65000px.", "set_content_viewport_width"), 470);
        
        $this->fields['content_viewport_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_viewport_height">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_viewport_height</a>
     */
    function setContentViewportHeight($height) {
        if (!preg_match("/(?i)^(auto|large|[0-9]+(px)?)$/", $height))
            throw new Error(create_invalid_value_message($height, "setContentViewportHeight", "html-to-pdf", "The value must be 'auto', 'large', or a number.", "set_content_viewport_height"), 470);
        
        $this->fields['content_viewport_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_fit_mode">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_fit_mode</a>
     */
    function setContentFitMode($mode) {
        if (!preg_match("/(?i)^(auto|smart-scaling|no-scaling|viewport-width|content-width|single-page|single-page-ratio)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setContentFitMode", "html-to-pdf", "Allowed values are auto, smart-scaling, no-scaling, viewport-width, content-width, single-page, single-page-ratio.", "set_content_fit_mode"), 470);
        
        $this->fields['content_fit_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_remove_blank_pages">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_remove_blank_pages</a>
     */
    function setRemoveBlankPages($pages) {
        if (!preg_match("/(?i)^(trailing|all|none)$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setRemoveBlankPages", "html-to-pdf", "Allowed values are trailing, all, none.", "set_remove_blank_pages"), 470);
        
        $this->fields['remove_blank_pages'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_url</a>
     */
    function setHeaderUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setHeaderUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "set_header_url"), 470);
        
        $this->fields['header_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_html">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_html</a>
     */
    function setHeaderHtml($html) {
        if (!($html != null && $html !== ''))
            throw new Error(create_invalid_value_message($html, "setHeaderHtml", "html-to-pdf", "The string must not be empty.", "set_header_html"), 470);
        
        $this->fields['header_html'] = $html;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_height">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_height</a>
     */
    function setHeaderHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setHeaderHeight", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_header_height"), 470);
        
        $this->fields['header_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_zip_header_filename">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_zip_header_filename</a>
     */
    function setZipHeaderFilename($filename) {
        $this->fields['zip_header_filename'] = $filename;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_url</a>
     */
    function setFooterUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setFooterUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "set_footer_url"), 470);
        
        $this->fields['footer_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_html">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_html</a>
     */
    function setFooterHtml($html) {
        if (!($html != null && $html !== ''))
            throw new Error(create_invalid_value_message($html, "setFooterHtml", "html-to-pdf", "The string must not be empty.", "set_footer_html"), 470);
        
        $this->fields['footer_html'] = $html;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_height">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_height</a>
     */
    function setFooterHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setFooterHeight", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_footer_height"), 470);
        
        $this->fields['footer_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_zip_footer_filename">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_zip_footer_filename</a>
     */
    function setZipFooterFilename($filename) {
        $this->fields['zip_footer_filename'] = $filename;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_header_footer_horizontal_margins">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_header_footer_horizontal_margins</a>
     */
    function setNoHeaderFooterHorizontalMargins($value) {
        $this->fields['no_header_footer_horizontal_margins'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_exclude_header_on_pages">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_exclude_header_on_pages</a>
     */
    function setExcludeHeaderOnPages($pages) {
        if (!preg_match("/^(?:\s*\-?\d+\s*,)*\s*\-?\d+\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setExcludeHeaderOnPages", "html-to-pdf", "A comma separated list of page numbers.", "set_exclude_header_on_pages"), 470);
        
        $this->fields['exclude_header_on_pages'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_exclude_footer_on_pages">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_exclude_footer_on_pages</a>
     */
    function setExcludeFooterOnPages($pages) {
        if (!preg_match("/^(?:\s*\-?\d+\s*,)*\s*\-?\d+\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setExcludeFooterOnPages", "html-to-pdf", "A comma separated list of page numbers.", "set_exclude_footer_on_pages"), 470);
        
        $this->fields['exclude_footer_on_pages'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_footer_scale_factor">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_footer_scale_factor</a>
     */
    function setHeaderFooterScaleFactor($factor) {
        if (!(intval($factor) >= 10 && intval($factor) <= 500))
            throw new Error(create_invalid_value_message($factor, "setHeaderFooterScaleFactor", "html-to-pdf", "The accepted range is 10-500.", "set_header_footer_scale_factor"), 470);
        
        $this->fields['header_footer_scale_factor'] = $factor;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_numbering_offset">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_numbering_offset</a>
     */
    function setPageNumberingOffset($offset) {
        $this->fields['page_numbering_offset'] = $offset;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_watermark">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_watermark</a>
     */
    function setPageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setPageWatermark", "html-to-pdf", "The file must exist and not be empty.", "set_page_watermark"), 470);
        
        $this->files['page_watermark'] = $watermark;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_watermark_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_watermark_url</a>
     */
    function setPageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageWatermarkUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "set_page_watermark_url"), 470);
        
        $this->fields['page_watermark_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_watermark">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_watermark</a>
     */
    function setMultipageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setMultipageWatermark", "html-to-pdf", "The file must exist and not be empty.", "set_multipage_watermark"), 470);
        
        $this->files['multipage_watermark'] = $watermark;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_watermark_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_watermark_url</a>
     */
    function setMultipageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageWatermarkUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "set_multipage_watermark_url"), 470);
        
        $this->fields['multipage_watermark_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_background">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_background</a>
     */
    function setPageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setPageBackground", "html-to-pdf", "The file must exist and not be empty.", "set_page_background"), 470);
        
        $this->files['page_background'] = $background;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_background_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_background_url</a>
     */
    function setPageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageBackgroundUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "set_page_background_url"), 470);
        
        $this->fields['page_background_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_background">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_background</a>
     */
    function setMultipageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setMultipageBackground", "html-to-pdf", "The file must exist and not be empty.", "set_multipage_background"), 470);
        
        $this->files['multipage_background'] = $background;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_background_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_multipage_background_url</a>
     */
    function setMultipageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageBackgroundUrl", "html-to-pdf", "Supported protocols are http:// and https://.", "set_multipage_background_url"), 470);
        
        $this->fields['multipage_background_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_background_color">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_background_color</a>
     */
    function setPageBackgroundColor($color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $color))
            throw new Error(create_invalid_value_message($color, "setPageBackgroundColor", "html-to-pdf", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_page_background_color"), 470);
        
        $this->fields['page_background_color'] = $color;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_print_media">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_print_media</a>
     */
    function setUsePrintMedia($value) {
        $this->fields['use_print_media'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_background">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_background</a>
     */
    function setNoBackground($value) {
        $this->fields['no_background'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_javascript">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_javascript</a>
     */
    function setDisableJavascript($value) {
        $this->fields['disable_javascript'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_image_loading">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_image_loading</a>
     */
    function setDisableImageLoading($value) {
        $this->fields['disable_image_loading'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_remote_fonts">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_remote_fonts</a>
     */
    function setDisableRemoteFonts($value) {
        $this->fields['disable_remote_fonts'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_mobile_user_agent">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_mobile_user_agent</a>
     */
    function setUseMobileUserAgent($value) {
        $this->fields['use_mobile_user_agent'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_load_iframes">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_load_iframes</a>
     */
    function setLoadIframes($iframes) {
        if (!preg_match("/(?i)^(all|same-origin|none)$/", $iframes))
            throw new Error(create_invalid_value_message($iframes, "setLoadIframes", "html-to-pdf", "Allowed values are all, same-origin, none.", "set_load_iframes"), 470);
        
        $this->fields['load_iframes'] = $iframes;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_block_ads">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_block_ads</a>
     */
    function setBlockAds($value) {
        $this->fields['block_ads'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_default_encoding">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_default_encoding</a>
     */
    function setDefaultEncoding($encoding) {
        $this->fields['default_encoding'] = $encoding;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_locale">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_locale</a>
     */
    function setLocale($locale) {
        $this->fields['locale'] = $locale;
        return $this;
    }


    function setHttpAuthUserName($user_name) {
        $this->fields['http_auth_user_name'] = $user_name;
        return $this;
    }


    function setHttpAuthPassword($password) {
        $this->fields['http_auth_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_http_auth">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_http_auth</a>
     */
    function setHttpAuth($user_name, $password) {
        $this->setHttpAuthUserName($user_name);
        $this->setHttpAuthPassword($password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_cookies">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_cookies</a>
     */
    function setCookies($cookies) {
        $this->fields['cookies'] = $cookies;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_verify_ssl_certificates">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_verify_ssl_certificates</a>
     */
    function setVerifySslCertificates($value) {
        $this->fields['verify_ssl_certificates'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_fail_on_main_url_error">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_fail_on_main_url_error</a>
     */
    function setFailOnMainUrlError($fail_on_error) {
        $this->fields['fail_on_main_url_error'] = $fail_on_error;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_fail_on_any_url_error">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_fail_on_any_url_error</a>
     */
    function setFailOnAnyUrlError($fail_on_error) {
        $this->fields['fail_on_any_url_error'] = $fail_on_error;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_xpdfcrowd_header">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_xpdfcrowd_header</a>
     */
    function setNoXpdfcrowdHeader($value) {
        $this->fields['no_xpdfcrowd_header'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_css_page_rule_mode">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_css_page_rule_mode</a>
     */
    function setCssPageRuleMode($mode) {
        if (!preg_match("/(?i)^(default|mode1|mode2)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setCssPageRuleMode", "html-to-pdf", "Allowed values are default, mode1, mode2.", "set_css_page_rule_mode"), 470);
        
        $this->fields['css_page_rule_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_custom_css">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_custom_css</a>
     */
    function setCustomCss($css) {
        if (!($css != null && $css !== ''))
            throw new Error(create_invalid_value_message($css, "setCustomCss", "html-to-pdf", "The string must not be empty.", "set_custom_css"), 470);
        
        $this->fields['custom_css'] = $css;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_custom_javascript">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_custom_javascript</a>
     */
    function setCustomJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setCustomJavascript", "html-to-pdf", "The string must not be empty.", "set_custom_javascript"), 470);
        
        $this->fields['custom_javascript'] = $javascript;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_on_load_javascript">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_on_load_javascript</a>
     */
    function setOnLoadJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setOnLoadJavascript", "html-to-pdf", "The string must not be empty.", "set_on_load_javascript"), 470);
        
        $this->fields['on_load_javascript'] = $javascript;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_custom_http_header">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_custom_http_header</a>
     */
    function setCustomHttpHeader($header) {
        if (!preg_match("/^.+:.+$/", $header))
            throw new Error(create_invalid_value_message($header, "setCustomHttpHeader", "html-to-pdf", "A string containing the header name and value separated by a colon.", "set_custom_http_header"), 470);
        
        $this->fields['custom_http_header'] = $header;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_javascript_delay">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_javascript_delay</a>
     */
    function setJavascriptDelay($delay) {
        if (!(intval($delay) >= 0))
            throw new Error(create_invalid_value_message($delay, "setJavascriptDelay", "html-to-pdf", "Must be a positive integer or 0.", "set_javascript_delay"), 470);
        
        $this->fields['javascript_delay'] = $delay;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_element_to_convert">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_element_to_convert</a>
     */
    function setElementToConvert($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "setElementToConvert", "html-to-pdf", "The string must not be empty.", "set_element_to_convert"), 470);
        
        $this->fields['element_to_convert'] = $selectors;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_element_to_convert_mode">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_element_to_convert_mode</a>
     */
    function setElementToConvertMode($mode) {
        if (!preg_match("/(?i)^(cut-out|remove-siblings|hide-siblings)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setElementToConvertMode", "html-to-pdf", "Allowed values are cut-out, remove-siblings, hide-siblings.", "set_element_to_convert_mode"), 470);
        
        $this->fields['element_to_convert_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_wait_for_element">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_wait_for_element</a>
     */
    function setWaitForElement($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "setWaitForElement", "html-to-pdf", "The string must not be empty.", "set_wait_for_element"), 470);
        
        $this->fields['wait_for_element'] = $selectors;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_auto_detect_element_to_convert">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_auto_detect_element_to_convert</a>
     */
    function setAutoDetectElementToConvert($value) {
        $this->fields['auto_detect_element_to_convert'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_readability_enhancements">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_readability_enhancements</a>
     */
    function setReadabilityEnhancements($enhancements) {
        if (!preg_match("/(?i)^(none|readability-v1|readability-v2|readability-v3|readability-v4)$/", $enhancements))
            throw new Error(create_invalid_value_message($enhancements, "setReadabilityEnhancements", "html-to-pdf", "Allowed values are none, readability-v1, readability-v2, readability-v3, readability-v4.", "set_readability_enhancements"), 470);
        
        $this->fields['readability_enhancements'] = $enhancements;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_viewport_width">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_viewport_width</a>
     */
    function setViewportWidth($width) {
        if (!(intval($width) >= 96 && intval($width) <= 65000))
            throw new Error(create_invalid_value_message($width, "setViewportWidth", "html-to-pdf", "The accepted range is 96-65000.", "set_viewport_width"), 470);
        
        $this->fields['viewport_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_viewport_height">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_viewport_height</a>
     */
    function setViewportHeight($height) {
        if (!(intval($height) > 0))
            throw new Error(create_invalid_value_message($height, "setViewportHeight", "html-to-pdf", "Must be a positive integer.", "set_viewport_height"), 470);
        
        $this->fields['viewport_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_viewport">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_viewport</a>
     */
    function setViewport($width, $height) {
        $this->setViewportWidth($width);
        $this->setViewportHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_rendering_mode">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_rendering_mode</a>
     */
    function setRenderingMode($mode) {
        if (!preg_match("/(?i)^(default|viewport)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setRenderingMode", "html-to-pdf", "Allowed values are default, viewport.", "set_rendering_mode"), 470);
        
        $this->fields['rendering_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_smart_scaling_mode">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_smart_scaling_mode</a>
     */
    function setSmartScalingMode($mode) {
        if (!preg_match("/(?i)^(default|disabled|viewport-fit|content-fit|single-page-fit|single-page-fit-ex|mode1)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setSmartScalingMode", "html-to-pdf", "Allowed values are default, disabled, viewport-fit, content-fit, single-page-fit, single-page-fit-ex, mode1.", "set_smart_scaling_mode"), 470);
        
        $this->fields['smart_scaling_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_scale_factor">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_scale_factor</a>
     */
    function setScaleFactor($factor) {
        if (!(intval($factor) >= 10 && intval($factor) <= 500))
            throw new Error(create_invalid_value_message($factor, "setScaleFactor", "html-to-pdf", "The accepted range is 10-500.", "set_scale_factor"), 470);
        
        $this->fields['scale_factor'] = $factor;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_jpeg_quality">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_jpeg_quality</a>
     */
    function setJpegQuality($quality) {
        if (!(intval($quality) >= 1 && intval($quality) <= 100))
            throw new Error(create_invalid_value_message($quality, "setJpegQuality", "html-to-pdf", "The accepted range is 1-100.", "set_jpeg_quality"), 470);
        
        $this->fields['jpeg_quality'] = $quality;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_convert_images_to_jpeg">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_convert_images_to_jpeg</a>
     */
    function setConvertImagesToJpeg($images) {
        if (!preg_match("/(?i)^(none|opaque|all)$/", $images))
            throw new Error(create_invalid_value_message($images, "setConvertImagesToJpeg", "html-to-pdf", "Allowed values are none, opaque, all.", "set_convert_images_to_jpeg"), 470);
        
        $this->fields['convert_images_to_jpeg'] = $images;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_image_dpi">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_image_dpi</a>
     */
    function setImageDpi($dpi) {
        if (!(intval($dpi) >= 0))
            throw new Error(create_invalid_value_message($dpi, "setImageDpi", "html-to-pdf", "Must be a positive integer or 0.", "set_image_dpi"), 470);
        
        $this->fields['image_dpi'] = $dpi;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_enable_pdf_forms">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_enable_pdf_forms</a>
     */
    function setEnablePdfForms($value) {
        $this->fields['enable_pdf_forms'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_linearize">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_linearize</a>
     */
    function setLinearize($value) {
        $this->fields['linearize'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_encrypt">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_encrypt</a>
     */
    function setEncrypt($value) {
        $this->fields['encrypt'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_user_password">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_user_password</a>
     */
    function setUserPassword($password) {
        $this->fields['user_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_owner_password">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_owner_password</a>
     */
    function setOwnerPassword($password) {
        $this->fields['owner_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_print">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_print</a>
     */
    function setNoPrint($value) {
        $this->fields['no_print'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_modify">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_modify</a>
     */
    function setNoModify($value) {
        $this->fields['no_modify'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_copy">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_no_copy</a>
     */
    function setNoCopy($value) {
        $this->fields['no_copy'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_title">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_title</a>
     */
    function setTitle($title) {
        $this->fields['title'] = $title;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_subject">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_subject</a>
     */
    function setSubject($subject) {
        $this->fields['subject'] = $subject;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_author">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_author</a>
     */
    function setAuthor($author) {
        $this->fields['author'] = $author;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_keywords">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_keywords</a>
     */
    function setKeywords($keywords) {
        $this->fields['keywords'] = $keywords;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_extract_meta_tags">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_extract_meta_tags</a>
     */
    function setExtractMetaTags($value) {
        $this->fields['extract_meta_tags'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_layout">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_layout</a>
     */
    function setPageLayout($layout) {
        if (!preg_match("/(?i)^(single-page|one-column|two-column-left|two-column-right)$/", $layout))
            throw new Error(create_invalid_value_message($layout, "setPageLayout", "html-to-pdf", "Allowed values are single-page, one-column, two-column-left, two-column-right.", "set_page_layout"), 470);
        
        $this->fields['page_layout'] = $layout;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_mode">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_page_mode</a>
     */
    function setPageMode($mode) {
        if (!preg_match("/(?i)^(full-screen|thumbnails|outlines)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPageMode", "html-to-pdf", "Allowed values are full-screen, thumbnails, outlines.", "set_page_mode"), 470);
        
        $this->fields['page_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_initial_zoom_type">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_initial_zoom_type</a>
     */
    function setInitialZoomType($zoom_type) {
        if (!preg_match("/(?i)^(fit-width|fit-height|fit-page)$/", $zoom_type))
            throw new Error(create_invalid_value_message($zoom_type, "setInitialZoomType", "html-to-pdf", "Allowed values are fit-width, fit-height, fit-page.", "set_initial_zoom_type"), 470);
        
        $this->fields['initial_zoom_type'] = $zoom_type;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_initial_page">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_initial_page</a>
     */
    function setInitialPage($page) {
        if (!(intval($page) > 0))
            throw new Error(create_invalid_value_message($page, "setInitialPage", "html-to-pdf", "Must be a positive integer.", "set_initial_page"), 470);
        
        $this->fields['initial_page'] = $page;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_initial_zoom">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_initial_zoom</a>
     */
    function setInitialZoom($zoom) {
        if (!(intval($zoom) > 0))
            throw new Error(create_invalid_value_message($zoom, "setInitialZoom", "html-to-pdf", "Must be a positive integer.", "set_initial_zoom"), 470);
        
        $this->fields['initial_zoom'] = $zoom;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_hide_toolbar">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_hide_toolbar</a>
     */
    function setHideToolbar($value) {
        $this->fields['hide_toolbar'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_hide_menubar">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_hide_menubar</a>
     */
    function setHideMenubar($value) {
        $this->fields['hide_menubar'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_hide_window_ui">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_hide_window_ui</a>
     */
    function setHideWindowUi($value) {
        $this->fields['hide_window_ui'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_fit_window">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_fit_window</a>
     */
    function setFitWindow($value) {
        $this->fields['fit_window'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_center_window">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_center_window</a>
     */
    function setCenterWindow($value) {
        $this->fields['center_window'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_display_title">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_display_title</a>
     */
    function setDisplayTitle($value) {
        $this->fields['display_title'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_right_to_left">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_right_to_left</a>
     */
    function setRightToLeft($value) {
        $this->fields['right_to_left'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_string">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_string</a>
     */
    function setDataString($data_string) {
        $this->fields['data_string'] = $data_string;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_file</a>
     */
    function setDataFile($data_file) {
        $this->files['data_file'] = $data_file;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_format">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_format</a>
     */
    function setDataFormat($data_format) {
        if (!preg_match("/(?i)^(auto|json|xml|yaml|csv)$/", $data_format))
            throw new Error(create_invalid_value_message($data_format, "setDataFormat", "html-to-pdf", "Allowed values are auto, json, xml, yaml, csv.", "set_data_format"), 470);
        
        $this->fields['data_format'] = $data_format;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_encoding">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_encoding</a>
     */
    function setDataEncoding($encoding) {
        $this->fields['data_encoding'] = $encoding;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_ignore_undefined">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_ignore_undefined</a>
     */
    function setDataIgnoreUndefined($value) {
        $this->fields['data_ignore_undefined'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_auto_escape">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_auto_escape</a>
     */
    function setDataAutoEscape($value) {
        $this->fields['data_auto_escape'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_trim_blocks">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_trim_blocks</a>
     */
    function setDataTrimBlocks($value) {
        $this->fields['data_trim_blocks'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_options">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_data_options</a>
     */
    function setDataOptions($options) {
        $this->fields['data_options'] = $options;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_debug_log">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_job_id">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_page_count">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_page_count</a>
     */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_total_page_count">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_total_page_count</a>
     */
    function getTotalPageCount() {
        return $this->helper->getTotalPageCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_output_size">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_version">https://pdfcrowd.com/api/html-to-pdf-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_tag">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_http_proxy">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "html-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_https_proxy">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "html-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_client_certificate">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_client_certificate</a>
     */
    function setClientCertificate($certificate) {
        if (!(filesize($certificate) > 0))
            throw new Error(create_invalid_value_message($certificate, "setClientCertificate", "html-to-pdf", "The file must exist and not be empty.", "set_client_certificate"), 470);
        
        $this->files['client_certificate'] = $certificate;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_client_certificate_password">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_client_certificate_password</a>
     */
    function setClientCertificatePassword($password) {
        $this->fields['client_certificate_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_layout_dpi">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_layout_dpi</a>
     */
    function setLayoutDpi($dpi) {
        if (!(intval($dpi) >= 72 && intval($dpi) <= 600))
            throw new Error(create_invalid_value_message($dpi, "setLayoutDpi", "html-to-pdf", "The accepted range is 72-600.", "set_layout_dpi"), 470);
        
        $this->fields['layout_dpi'] = $dpi;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_x">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_x</a>
     */
    function setContentAreaX($x) {
        if (!preg_match("/(?i)^0$|^\-?[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $x))
            throw new Error(create_invalid_value_message($x, "setContentAreaX", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'. It may contain a negative value.", "set_content_area_x"), 470);
        
        $this->fields['content_area_x'] = $x;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_y">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_y</a>
     */
    function setContentAreaY($y) {
        if (!preg_match("/(?i)^0$|^\-?[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $y))
            throw new Error(create_invalid_value_message($y, "setContentAreaY", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'. It may contain a negative value.", "set_content_area_y"), 470);
        
        $this->fields['content_area_y'] = $y;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_width">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_width</a>
     */
    function setContentAreaWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setContentAreaWidth", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_content_area_width"), 470);
        
        $this->fields['content_area_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_height">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area_height</a>
     */
    function setContentAreaHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setContentAreaHeight", "html-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_content_area_height"), 470);
        
        $this->fields['content_area_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_content_area</a>
     */
    function setContentArea($x, $y, $width, $height) {
        $this->setContentAreaX($x);
        $this->setContentAreaY($y);
        $this->setContentAreaWidth($width);
        $this->setContentAreaHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_contents_matrix">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_contents_matrix</a>
     */
    function setContentsMatrix($matrix) {
        $this->fields['contents_matrix'] = $matrix;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_matrix">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_matrix</a>
     */
    function setHeaderMatrix($matrix) {
        $this->fields['header_matrix'] = $matrix;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_matrix">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_footer_matrix</a>
     */
    function setFooterMatrix($matrix) {
        $this->fields['footer_matrix'] = $matrix;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_page_height_optimization">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_disable_page_height_optimization</a>
     */
    function setDisablePageHeightOptimization($value) {
        $this->fields['disable_page_height_optimization'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_main_document_css_annotation">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_main_document_css_annotation</a>
     */
    function setMainDocumentCssAnnotation($value) {
        $this->fields['main_document_css_annotation'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_footer_css_annotation">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_header_footer_css_annotation</a>
     */
    function setHeaderFooterCssAnnotation($value) {
        $this->fields['header_footer_css_annotation'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_max_loading_time">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_max_loading_time</a>
     */
    function setMaxLoadingTime($max_time) {
        if (!(intval($max_time) >= 10 && intval($max_time) <= 30))
            throw new Error(create_invalid_value_message($max_time, "setMaxLoadingTime", "html-to-pdf", "The accepted range is 10-30.", "set_max_loading_time"), 470);
        
        $this->fields['max_loading_time'] = $max_time;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_conversion_config">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_conversion_config</a>
     */
    function setConversionConfig($json_string) {
        $this->fields['conversion_config'] = $json_string;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_conversion_config_file">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_conversion_config_file</a>
     */
    function setConversionConfigFile($filepath) {
        if (!(filesize($filepath) > 0))
            throw new Error(create_invalid_value_message($filepath, "setConversionConfigFile", "html-to-pdf", "The file must exist and not be empty.", "set_conversion_config_file"), 470);
        
        $this->files['conversion_config_file'] = $filepath;
        return $this;
    }


    function setSubprocessReferrer($referrer) {
        $this->fields['subprocess_referrer'] = $referrer;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_converter_user_agent">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_converter_user_agent</a>
     */
    function setConverterUserAgent($agent) {
        $this->fields['converter_user_agent'] = $agent;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_converter_version">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_converter_version</a>
     */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(24.04|20.10|18.10|latest)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "html-to-pdf", "Allowed values are 24.04, 20.10, 18.10, latest.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_http">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_user_agent">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_proxy">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_curl">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_retry_count">https://pdfcrowd.com/api/html-to-pdf-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}

/**
 * Conversion from HTML to image.
 *
 * @see <a href="https://pdfcrowd.com/api/html-to-image-php/">https://pdfcrowd.com/api/html-to-image-php/</a>
 */
class HtmlToImageClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#__construct">https://pdfcrowd.com/api/html-to-image-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'html', 'output_format'=>'png');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_output_format">https://pdfcrowd.com/api/html-to-image-php/ref/#set_output_format</a>
     */
    function setOutputFormat($output_format) {
        if (!preg_match("/(?i)^(png|jpg|gif|tiff|bmp|ico|ppm|pgm|pbm|pnm|psb|pct|ras|tga|sgi|sun|webp)$/", $output_format))
            throw new Error(create_invalid_value_message($output_format, "setOutputFormat", "html-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
        $this->fields['output_format'] = $output_format;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_url">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "html-to-image", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "html-to-image", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "html-to-image", "The string must not be empty.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_file">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "html-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "html-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "html-to-image", "The string must not be empty.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_string">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_string</a>
     */
    function convertString($text) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "convertString", "html-to-image", "The string must not be empty.", "convert_string"), 470);
        
        $this->fields['text'] = $text;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_string_to_stream">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_string_to_stream</a>
     */
    function convertStringToStream($text, $out_stream) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "convertStringToStream::text", "html-to-image", "The string must not be empty.", "convert_string_to_stream"), 470);
        
        $this->fields['text'] = $text;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_string_to_file">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_string_to_file</a>
     */
    function convertStringToFile($text, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStringToFile::file_path", "html-to-image", "The string must not be empty.", "convert_string_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStringToStream($text, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_stream">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/html-to-image-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "html-to-image", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_zip_main_filename">https://pdfcrowd.com/api/html-to-image-php/ref/#set_zip_main_filename</a>
     */
    function setZipMainFilename($filename) {
        $this->fields['zip_main_filename'] = $filename;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_screenshot_width">https://pdfcrowd.com/api/html-to-image-php/ref/#set_screenshot_width</a>
     */
    function setScreenshotWidth($width) {
        if (!(intval($width) >= 96 && intval($width) <= 65000))
            throw new Error(create_invalid_value_message($width, "setScreenshotWidth", "html-to-image", "The accepted range is 96-65000.", "set_screenshot_width"), 470);
        
        $this->fields['screenshot_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_screenshot_height">https://pdfcrowd.com/api/html-to-image-php/ref/#set_screenshot_height</a>
     */
    function setScreenshotHeight($height) {
        if (!(intval($height) > 0))
            throw new Error(create_invalid_value_message($height, "setScreenshotHeight", "html-to-image", "Must be a positive integer.", "set_screenshot_height"), 470);
        
        $this->fields['screenshot_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_scale_factor">https://pdfcrowd.com/api/html-to-image-php/ref/#set_scale_factor</a>
     */
    function setScaleFactor($factor) {
        if (!(intval($factor) > 0))
            throw new Error(create_invalid_value_message($factor, "setScaleFactor", "html-to-image", "Must be a positive integer.", "set_scale_factor"), 470);
        
        $this->fields['scale_factor'] = $factor;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_background_color">https://pdfcrowd.com/api/html-to-image-php/ref/#set_background_color</a>
     */
    function setBackgroundColor($color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $color))
            throw new Error(create_invalid_value_message($color, "setBackgroundColor", "html-to-image", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_background_color"), 470);
        
        $this->fields['background_color'] = $color;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_print_media">https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_print_media</a>
     */
    function setUsePrintMedia($value) {
        $this->fields['use_print_media'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_no_background">https://pdfcrowd.com/api/html-to-image-php/ref/#set_no_background</a>
     */
    function setNoBackground($value) {
        $this->fields['no_background'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_disable_javascript">https://pdfcrowd.com/api/html-to-image-php/ref/#set_disable_javascript</a>
     */
    function setDisableJavascript($value) {
        $this->fields['disable_javascript'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_disable_image_loading">https://pdfcrowd.com/api/html-to-image-php/ref/#set_disable_image_loading</a>
     */
    function setDisableImageLoading($value) {
        $this->fields['disable_image_loading'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_disable_remote_fonts">https://pdfcrowd.com/api/html-to-image-php/ref/#set_disable_remote_fonts</a>
     */
    function setDisableRemoteFonts($value) {
        $this->fields['disable_remote_fonts'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_mobile_user_agent">https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_mobile_user_agent</a>
     */
    function setUseMobileUserAgent($value) {
        $this->fields['use_mobile_user_agent'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_load_iframes">https://pdfcrowd.com/api/html-to-image-php/ref/#set_load_iframes</a>
     */
    function setLoadIframes($iframes) {
        if (!preg_match("/(?i)^(all|same-origin|none)$/", $iframes))
            throw new Error(create_invalid_value_message($iframes, "setLoadIframes", "html-to-image", "Allowed values are all, same-origin, none.", "set_load_iframes"), 470);
        
        $this->fields['load_iframes'] = $iframes;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_block_ads">https://pdfcrowd.com/api/html-to-image-php/ref/#set_block_ads</a>
     */
    function setBlockAds($value) {
        $this->fields['block_ads'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_default_encoding">https://pdfcrowd.com/api/html-to-image-php/ref/#set_default_encoding</a>
     */
    function setDefaultEncoding($encoding) {
        $this->fields['default_encoding'] = $encoding;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_locale">https://pdfcrowd.com/api/html-to-image-php/ref/#set_locale</a>
     */
    function setLocale($locale) {
        $this->fields['locale'] = $locale;
        return $this;
    }


    function setHttpAuthUserName($user_name) {
        $this->fields['http_auth_user_name'] = $user_name;
        return $this;
    }


    function setHttpAuthPassword($password) {
        $this->fields['http_auth_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_http_auth">https://pdfcrowd.com/api/html-to-image-php/ref/#set_http_auth</a>
     */
    function setHttpAuth($user_name, $password) {
        $this->setHttpAuthUserName($user_name);
        $this->setHttpAuthPassword($password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_cookies">https://pdfcrowd.com/api/html-to-image-php/ref/#set_cookies</a>
     */
    function setCookies($cookies) {
        $this->fields['cookies'] = $cookies;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_verify_ssl_certificates">https://pdfcrowd.com/api/html-to-image-php/ref/#set_verify_ssl_certificates</a>
     */
    function setVerifySslCertificates($value) {
        $this->fields['verify_ssl_certificates'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_fail_on_main_url_error">https://pdfcrowd.com/api/html-to-image-php/ref/#set_fail_on_main_url_error</a>
     */
    function setFailOnMainUrlError($fail_on_error) {
        $this->fields['fail_on_main_url_error'] = $fail_on_error;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_fail_on_any_url_error">https://pdfcrowd.com/api/html-to-image-php/ref/#set_fail_on_any_url_error</a>
     */
    function setFailOnAnyUrlError($fail_on_error) {
        $this->fields['fail_on_any_url_error'] = $fail_on_error;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_no_xpdfcrowd_header">https://pdfcrowd.com/api/html-to-image-php/ref/#set_no_xpdfcrowd_header</a>
     */
    function setNoXpdfcrowdHeader($value) {
        $this->fields['no_xpdfcrowd_header'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_custom_css">https://pdfcrowd.com/api/html-to-image-php/ref/#set_custom_css</a>
     */
    function setCustomCss($css) {
        if (!($css != null && $css !== ''))
            throw new Error(create_invalid_value_message($css, "setCustomCss", "html-to-image", "The string must not be empty.", "set_custom_css"), 470);
        
        $this->fields['custom_css'] = $css;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_custom_javascript">https://pdfcrowd.com/api/html-to-image-php/ref/#set_custom_javascript</a>
     */
    function setCustomJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setCustomJavascript", "html-to-image", "The string must not be empty.", "set_custom_javascript"), 470);
        
        $this->fields['custom_javascript'] = $javascript;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_on_load_javascript">https://pdfcrowd.com/api/html-to-image-php/ref/#set_on_load_javascript</a>
     */
    function setOnLoadJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setOnLoadJavascript", "html-to-image", "The string must not be empty.", "set_on_load_javascript"), 470);
        
        $this->fields['on_load_javascript'] = $javascript;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_custom_http_header">https://pdfcrowd.com/api/html-to-image-php/ref/#set_custom_http_header</a>
     */
    function setCustomHttpHeader($header) {
        if (!preg_match("/^.+:.+$/", $header))
            throw new Error(create_invalid_value_message($header, "setCustomHttpHeader", "html-to-image", "A string containing the header name and value separated by a colon.", "set_custom_http_header"), 470);
        
        $this->fields['custom_http_header'] = $header;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_javascript_delay">https://pdfcrowd.com/api/html-to-image-php/ref/#set_javascript_delay</a>
     */
    function setJavascriptDelay($delay) {
        if (!(intval($delay) >= 0))
            throw new Error(create_invalid_value_message($delay, "setJavascriptDelay", "html-to-image", "Must be a positive integer or 0.", "set_javascript_delay"), 470);
        
        $this->fields['javascript_delay'] = $delay;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_element_to_convert">https://pdfcrowd.com/api/html-to-image-php/ref/#set_element_to_convert</a>
     */
    function setElementToConvert($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "setElementToConvert", "html-to-image", "The string must not be empty.", "set_element_to_convert"), 470);
        
        $this->fields['element_to_convert'] = $selectors;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_element_to_convert_mode">https://pdfcrowd.com/api/html-to-image-php/ref/#set_element_to_convert_mode</a>
     */
    function setElementToConvertMode($mode) {
        if (!preg_match("/(?i)^(cut-out|remove-siblings|hide-siblings)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setElementToConvertMode", "html-to-image", "Allowed values are cut-out, remove-siblings, hide-siblings.", "set_element_to_convert_mode"), 470);
        
        $this->fields['element_to_convert_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_wait_for_element">https://pdfcrowd.com/api/html-to-image-php/ref/#set_wait_for_element</a>
     */
    function setWaitForElement($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "setWaitForElement", "html-to-image", "The string must not be empty.", "set_wait_for_element"), 470);
        
        $this->fields['wait_for_element'] = $selectors;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_auto_detect_element_to_convert">https://pdfcrowd.com/api/html-to-image-php/ref/#set_auto_detect_element_to_convert</a>
     */
    function setAutoDetectElementToConvert($value) {
        $this->fields['auto_detect_element_to_convert'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_readability_enhancements">https://pdfcrowd.com/api/html-to-image-php/ref/#set_readability_enhancements</a>
     */
    function setReadabilityEnhancements($enhancements) {
        if (!preg_match("/(?i)^(none|readability-v1|readability-v2|readability-v3|readability-v4)$/", $enhancements))
            throw new Error(create_invalid_value_message($enhancements, "setReadabilityEnhancements", "html-to-image", "Allowed values are none, readability-v1, readability-v2, readability-v3, readability-v4.", "set_readability_enhancements"), 470);
        
        $this->fields['readability_enhancements'] = $enhancements;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_string">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_string</a>
     */
    function setDataString($data_string) {
        $this->fields['data_string'] = $data_string;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_file">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_file</a>
     */
    function setDataFile($data_file) {
        $this->files['data_file'] = $data_file;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_format">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_format</a>
     */
    function setDataFormat($data_format) {
        if (!preg_match("/(?i)^(auto|json|xml|yaml|csv)$/", $data_format))
            throw new Error(create_invalid_value_message($data_format, "setDataFormat", "html-to-image", "Allowed values are auto, json, xml, yaml, csv.", "set_data_format"), 470);
        
        $this->fields['data_format'] = $data_format;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_encoding">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_encoding</a>
     */
    function setDataEncoding($encoding) {
        $this->fields['data_encoding'] = $encoding;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_ignore_undefined">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_ignore_undefined</a>
     */
    function setDataIgnoreUndefined($value) {
        $this->fields['data_ignore_undefined'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_auto_escape">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_auto_escape</a>
     */
    function setDataAutoEscape($value) {
        $this->fields['data_auto_escape'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_trim_blocks">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_trim_blocks</a>
     */
    function setDataTrimBlocks($value) {
        $this->fields['data_trim_blocks'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_options">https://pdfcrowd.com/api/html-to-image-php/ref/#set_data_options</a>
     */
    function setDataOptions($options) {
        $this->fields['data_options'] = $options;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_debug_log">https://pdfcrowd.com/api/html-to-image-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/html-to-image-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/html-to-image-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/html-to-image-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#get_job_id">https://pdfcrowd.com/api/html-to-image-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#get_output_size">https://pdfcrowd.com/api/html-to-image-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#get_version">https://pdfcrowd.com/api/html-to-image-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_tag">https://pdfcrowd.com/api/html-to-image-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_http_proxy">https://pdfcrowd.com/api/html-to-image-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "html-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_https_proxy">https://pdfcrowd.com/api/html-to-image-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "html-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_client_certificate">https://pdfcrowd.com/api/html-to-image-php/ref/#set_client_certificate</a>
     */
    function setClientCertificate($certificate) {
        if (!(filesize($certificate) > 0))
            throw new Error(create_invalid_value_message($certificate, "setClientCertificate", "html-to-image", "The file must exist and not be empty.", "set_client_certificate"), 470);
        
        $this->files['client_certificate'] = $certificate;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_client_certificate_password">https://pdfcrowd.com/api/html-to-image-php/ref/#set_client_certificate_password</a>
     */
    function setClientCertificatePassword($password) {
        $this->fields['client_certificate_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_max_loading_time">https://pdfcrowd.com/api/html-to-image-php/ref/#set_max_loading_time</a>
     */
    function setMaxLoadingTime($max_time) {
        if (!(intval($max_time) >= 10 && intval($max_time) <= 30))
            throw new Error(create_invalid_value_message($max_time, "setMaxLoadingTime", "html-to-image", "The accepted range is 10-30.", "set_max_loading_time"), 470);
        
        $this->fields['max_loading_time'] = $max_time;
        return $this;
    }


    function setSubprocessReferrer($referrer) {
        $this->fields['subprocess_referrer'] = $referrer;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_converter_user_agent">https://pdfcrowd.com/api/html-to-image-php/ref/#set_converter_user_agent</a>
     */
    function setConverterUserAgent($agent) {
        $this->fields['converter_user_agent'] = $agent;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_converter_version">https://pdfcrowd.com/api/html-to-image-php/ref/#set_converter_version</a>
     */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(24.04|20.10|18.10|latest)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "html-to-image", "Allowed values are 24.04, 20.10, 18.10, latest.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_http">https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/html-to-image-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_user_agent">https://pdfcrowd.com/api/html-to-image-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_proxy">https://pdfcrowd.com/api/html-to-image-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_curl">https://pdfcrowd.com/api/html-to-image-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/html-to-image-php/ref/#set_retry_count">https://pdfcrowd.com/api/html-to-image-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}

/**
 * Conversion from one image format to another image format.
 *
 * @see <a href="https://pdfcrowd.com/api/image-to-image-php/">https://pdfcrowd.com/api/image-to-image-php/</a>
 */
class ImageToImageClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#__construct">https://pdfcrowd.com/api/image-to-image-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'image', 'output_format'=>'png');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_url">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "image-to-image", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "image-to-image", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "image-to-image", "The string must not be empty.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_file">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "image-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "image-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "image-to-image", "The string must not be empty.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_raw_data">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_raw_data</a>
     */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_raw_data_to_stream">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_raw_data_to_stream</a>
     */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_raw_data_to_file">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_raw_data_to_file</a>
     */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertRawDataToFile::file_path", "image-to-image", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertRawDataToStream($data, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_stream">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/image-to-image-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "image-to-image", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_output_format">https://pdfcrowd.com/api/image-to-image-php/ref/#set_output_format</a>
     */
    function setOutputFormat($output_format) {
        if (!preg_match("/(?i)^(png|jpg|gif|tiff|bmp|ico|ppm|pgm|pbm|pnm|psb|pct|ras|tga|sgi|sun|webp)$/", $output_format))
            throw new Error(create_invalid_value_message($output_format, "setOutputFormat", "image-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
        $this->fields['output_format'] = $output_format;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_resize">https://pdfcrowd.com/api/image-to-image-php/ref/#set_resize</a>
     */
    function setResize($resize) {
        $this->fields['resize'] = $resize;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_rotate">https://pdfcrowd.com/api/image-to-image-php/ref/#set_rotate</a>
     */
    function setRotate($rotate) {
        $this->fields['rotate'] = $rotate;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_x">https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_x</a>
     */
    function setCropAreaX($x) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $x))
            throw new Error(create_invalid_value_message($x, "setCropAreaX", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_x"), 470);
        
        $this->fields['crop_area_x'] = $x;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_y">https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_y</a>
     */
    function setCropAreaY($y) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $y))
            throw new Error(create_invalid_value_message($y, "setCropAreaY", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_y"), 470);
        
        $this->fields['crop_area_y'] = $y;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_width">https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_width</a>
     */
    function setCropAreaWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setCropAreaWidth", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_width"), 470);
        
        $this->fields['crop_area_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_height">https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area_height</a>
     */
    function setCropAreaHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setCropAreaHeight", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_height"), 470);
        
        $this->fields['crop_area_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area">https://pdfcrowd.com/api/image-to-image-php/ref/#set_crop_area</a>
     */
    function setCropArea($x, $y, $width, $height) {
        $this->setCropAreaX($x);
        $this->setCropAreaY($y);
        $this->setCropAreaWidth($width);
        $this->setCropAreaHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_remove_borders">https://pdfcrowd.com/api/image-to-image-php/ref/#set_remove_borders</a>
     */
    function setRemoveBorders($value) {
        $this->fields['remove_borders'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_size">https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_size</a>
     */
    function setCanvasSize($size) {
        if (!preg_match("/(?i)^(A0|A1|A2|A3|A4|A5|A6|Letter)$/", $size))
            throw new Error(create_invalid_value_message($size, "setCanvasSize", "image-to-image", "Allowed values are A0, A1, A2, A3, A4, A5, A6, Letter.", "set_canvas_size"), 470);
        
        $this->fields['canvas_size'] = $size;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_width">https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_width</a>
     */
    function setCanvasWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setCanvasWidth", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_canvas_width"), 470);
        
        $this->fields['canvas_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_height">https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_height</a>
     */
    function setCanvasHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setCanvasHeight", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_canvas_height"), 470);
        
        $this->fields['canvas_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_dimensions">https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_dimensions</a>
     */
    function setCanvasDimensions($width, $height) {
        $this->setCanvasWidth($width);
        $this->setCanvasHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_orientation">https://pdfcrowd.com/api/image-to-image-php/ref/#set_orientation</a>
     */
    function setOrientation($orientation) {
        if (!preg_match("/(?i)^(landscape|portrait)$/", $orientation))
            throw new Error(create_invalid_value_message($orientation, "setOrientation", "image-to-image", "Allowed values are landscape, portrait.", "set_orientation"), 470);
        
        $this->fields['orientation'] = $orientation;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_position">https://pdfcrowd.com/api/image-to-image-php/ref/#set_position</a>
     */
    function setPosition($position) {
        if (!preg_match("/(?i)^(center|top|bottom|left|right|top-left|top-right|bottom-left|bottom-right)$/", $position))
            throw new Error(create_invalid_value_message($position, "setPosition", "image-to-image", "Allowed values are center, top, bottom, left, right, top-left, top-right, bottom-left, bottom-right.", "set_position"), 470);
        
        $this->fields['position'] = $position;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_print_canvas_mode">https://pdfcrowd.com/api/image-to-image-php/ref/#set_print_canvas_mode</a>
     */
    function setPrintCanvasMode($mode) {
        if (!preg_match("/(?i)^(default|fit|stretch)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPrintCanvasMode", "image-to-image", "Allowed values are default, fit, stretch.", "set_print_canvas_mode"), 470);
        
        $this->fields['print_canvas_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_top">https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_top</a>
     */
    function setMarginTop($top) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $top))
            throw new Error(create_invalid_value_message($top, "setMarginTop", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_top"), 470);
        
        $this->fields['margin_top'] = $top;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_right">https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_right</a>
     */
    function setMarginRight($right) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $right))
            throw new Error(create_invalid_value_message($right, "setMarginRight", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_right"), 470);
        
        $this->fields['margin_right'] = $right;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_bottom">https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_bottom</a>
     */
    function setMarginBottom($bottom) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $bottom))
            throw new Error(create_invalid_value_message($bottom, "setMarginBottom", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_bottom"), 470);
        
        $this->fields['margin_bottom'] = $bottom;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_left">https://pdfcrowd.com/api/image-to-image-php/ref/#set_margin_left</a>
     */
    function setMarginLeft($left) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $left))
            throw new Error(create_invalid_value_message($left, "setMarginLeft", "image-to-image", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_left"), 470);
        
        $this->fields['margin_left'] = $left;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_margins">https://pdfcrowd.com/api/image-to-image-php/ref/#set_margins</a>
     */
    function setMargins($top, $right, $bottom, $left) {
        $this->setMarginTop($top);
        $this->setMarginRight($right);
        $this->setMarginBottom($bottom);
        $this->setMarginLeft($left);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_background_color">https://pdfcrowd.com/api/image-to-image-php/ref/#set_canvas_background_color</a>
     */
    function setCanvasBackgroundColor($color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $color))
            throw new Error(create_invalid_value_message($color, "setCanvasBackgroundColor", "image-to-image", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_canvas_background_color"), 470);
        
        $this->fields['canvas_background_color'] = $color;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_dpi">https://pdfcrowd.com/api/image-to-image-php/ref/#set_dpi</a>
     */
    function setDpi($dpi) {
        $this->fields['dpi'] = $dpi;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_debug_log">https://pdfcrowd.com/api/image-to-image-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/image-to-image-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/image-to-image-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/image-to-image-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#get_job_id">https://pdfcrowd.com/api/image-to-image-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#get_output_size">https://pdfcrowd.com/api/image-to-image-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#get_version">https://pdfcrowd.com/api/image-to-image-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_tag">https://pdfcrowd.com/api/image-to-image-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_http_proxy">https://pdfcrowd.com/api/image-to-image-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "image-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_https_proxy">https://pdfcrowd.com/api/image-to-image-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "image-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_converter_version">https://pdfcrowd.com/api/image-to-image-php/ref/#set_converter_version</a>
     */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(24.04|20.10|18.10|latest)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "image-to-image", "Allowed values are 24.04, 20.10, 18.10, latest.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_use_http">https://pdfcrowd.com/api/image-to-image-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/image-to-image-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_user_agent">https://pdfcrowd.com/api/image-to-image-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_proxy">https://pdfcrowd.com/api/image-to-image-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_use_curl">https://pdfcrowd.com/api/image-to-image-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-image-php/ref/#set_retry_count">https://pdfcrowd.com/api/image-to-image-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}

/**
 * Conversion from PDF to PDF.
 *
 * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/">https://pdfcrowd.com/api/pdf-to-pdf-php/</a>
 */
class PdfToPdfClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#__construct">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'pdf', 'output_format'=>'pdf');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_action">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_action</a>
     */
    function setAction($action) {
        if (!preg_match("/(?i)^(join|shuffle|extract|delete)$/", $action))
            throw new Error(create_invalid_value_message($action, "setAction", "pdf-to-pdf", "Allowed values are join, shuffle, extract, delete.", "set_action"), 470);
        
        $this->fields['action'] = $action;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#convert">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#convert</a>
     */
    function convert() {
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#convert_to_stream">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#convert_to_stream</a>
     */
    function convertToStream($out_stream) {
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#convert_to_file">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#convert_to_file</a>
     */
    function convertToFile($file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertToFile", "pdf-to-pdf", "The string must not be empty.", "convert_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        $this->convertToStream($output_file);
        fclose($output_file);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#add_pdf_file">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#add_pdf_file</a>
     */
    function addPdfFile($file_path) {
        if (!(filesize($file_path) > 0))
            throw new Error(create_invalid_value_message($file_path, "addPdfFile", "pdf-to-pdf", "The file must exist and not be empty.", "add_pdf_file"), 470);
        
        $this->files['f_' . $this->file_id] = $file_path;
        $this->file_id++;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#add_pdf_raw_data">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#add_pdf_raw_data</a>
     */
    function addPdfRawData($data) {
        if (!($data != null && strlen($data) > 300 && substr($data, 0, 4) == '%PDF'))
            throw new Error(create_invalid_value_message("raw PDF data", "addPdfRawData", "pdf-to-pdf", "The input data must be PDF content.", "add_pdf_raw_data"), 470);
        
        $this->raw_data['f_' . $this->file_id] = $data;
        $this->file_id++;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_input_pdf_password">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_input_pdf_password</a>
     */
    function setInputPdfPassword($password) {
        $this->fields['input_pdf_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_range">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_range</a>
     */
    function setPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setPageRange", "pdf-to-pdf", "A comma separated list of page numbers or ranges.", "set_page_range"), 470);
        
        $this->fields['page_range'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_watermark">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_watermark</a>
     */
    function setPageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setPageWatermark", "pdf-to-pdf", "The file must exist and not be empty.", "set_page_watermark"), 470);
        
        $this->files['page_watermark'] = $watermark;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_watermark_url">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_watermark_url</a>
     */
    function setPageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageWatermarkUrl", "pdf-to-pdf", "Supported protocols are http:// and https://.", "set_page_watermark_url"), 470);
        
        $this->fields['page_watermark_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_watermark">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_watermark</a>
     */
    function setMultipageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setMultipageWatermark", "pdf-to-pdf", "The file must exist and not be empty.", "set_multipage_watermark"), 470);
        
        $this->files['multipage_watermark'] = $watermark;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_watermark_url">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_watermark_url</a>
     */
    function setMultipageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageWatermarkUrl", "pdf-to-pdf", "Supported protocols are http:// and https://.", "set_multipage_watermark_url"), 470);
        
        $this->fields['multipage_watermark_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_background">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_background</a>
     */
    function setPageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setPageBackground", "pdf-to-pdf", "The file must exist and not be empty.", "set_page_background"), 470);
        
        $this->files['page_background'] = $background;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_background_url">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_background_url</a>
     */
    function setPageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageBackgroundUrl", "pdf-to-pdf", "Supported protocols are http:// and https://.", "set_page_background_url"), 470);
        
        $this->fields['page_background_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_background">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_background</a>
     */
    function setMultipageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setMultipageBackground", "pdf-to-pdf", "The file must exist and not be empty.", "set_multipage_background"), 470);
        
        $this->files['multipage_background'] = $background;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_background_url">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_multipage_background_url</a>
     */
    function setMultipageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageBackgroundUrl", "pdf-to-pdf", "Supported protocols are http:// and https://.", "set_multipage_background_url"), 470);
        
        $this->fields['multipage_background_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_linearize">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_linearize</a>
     */
    function setLinearize($value) {
        $this->fields['linearize'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_encrypt">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_encrypt</a>
     */
    function setEncrypt($value) {
        $this->fields['encrypt'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_user_password">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_user_password</a>
     */
    function setUserPassword($password) {
        $this->fields['user_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_owner_password">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_owner_password</a>
     */
    function setOwnerPassword($password) {
        $this->fields['owner_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_no_print">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_no_print</a>
     */
    function setNoPrint($value) {
        $this->fields['no_print'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_no_modify">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_no_modify</a>
     */
    function setNoModify($value) {
        $this->fields['no_modify'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_no_copy">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_no_copy</a>
     */
    function setNoCopy($value) {
        $this->fields['no_copy'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_title">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_title</a>
     */
    function setTitle($title) {
        $this->fields['title'] = $title;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_subject">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_subject</a>
     */
    function setSubject($subject) {
        $this->fields['subject'] = $subject;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_author">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_author</a>
     */
    function setAuthor($author) {
        $this->fields['author'] = $author;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_keywords">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_keywords</a>
     */
    function setKeywords($keywords) {
        $this->fields['keywords'] = $keywords;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_use_metadata_from">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_use_metadata_from</a>
     */
    function setUseMetadataFrom($index) {
        if (!(intval($index) >= 0))
            throw new Error(create_invalid_value_message($index, "setUseMetadataFrom", "pdf-to-pdf", "Must be a positive integer or 0.", "set_use_metadata_from"), 470);
        
        $this->fields['use_metadata_from'] = $index;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_layout">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_layout</a>
     */
    function setPageLayout($layout) {
        if (!preg_match("/(?i)^(single-page|one-column|two-column-left|two-column-right)$/", $layout))
            throw new Error(create_invalid_value_message($layout, "setPageLayout", "pdf-to-pdf", "Allowed values are single-page, one-column, two-column-left, two-column-right.", "set_page_layout"), 470);
        
        $this->fields['page_layout'] = $layout;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_mode">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_page_mode</a>
     */
    function setPageMode($mode) {
        if (!preg_match("/(?i)^(full-screen|thumbnails|outlines)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPageMode", "pdf-to-pdf", "Allowed values are full-screen, thumbnails, outlines.", "set_page_mode"), 470);
        
        $this->fields['page_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_initial_zoom_type">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_initial_zoom_type</a>
     */
    function setInitialZoomType($zoom_type) {
        if (!preg_match("/(?i)^(fit-width|fit-height|fit-page)$/", $zoom_type))
            throw new Error(create_invalid_value_message($zoom_type, "setInitialZoomType", "pdf-to-pdf", "Allowed values are fit-width, fit-height, fit-page.", "set_initial_zoom_type"), 470);
        
        $this->fields['initial_zoom_type'] = $zoom_type;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_initial_page">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_initial_page</a>
     */
    function setInitialPage($page) {
        if (!(intval($page) > 0))
            throw new Error(create_invalid_value_message($page, "setInitialPage", "pdf-to-pdf", "Must be a positive integer.", "set_initial_page"), 470);
        
        $this->fields['initial_page'] = $page;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_initial_zoom">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_initial_zoom</a>
     */
    function setInitialZoom($zoom) {
        if (!(intval($zoom) > 0))
            throw new Error(create_invalid_value_message($zoom, "setInitialZoom", "pdf-to-pdf", "Must be a positive integer.", "set_initial_zoom"), 470);
        
        $this->fields['initial_zoom'] = $zoom;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_hide_toolbar">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_hide_toolbar</a>
     */
    function setHideToolbar($value) {
        $this->fields['hide_toolbar'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_hide_menubar">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_hide_menubar</a>
     */
    function setHideMenubar($value) {
        $this->fields['hide_menubar'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_hide_window_ui">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_hide_window_ui</a>
     */
    function setHideWindowUi($value) {
        $this->fields['hide_window_ui'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_fit_window">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_fit_window</a>
     */
    function setFitWindow($value) {
        $this->fields['fit_window'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_center_window">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_center_window</a>
     */
    function setCenterWindow($value) {
        $this->fields['center_window'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_display_title">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_display_title</a>
     */
    function setDisplayTitle($value) {
        $this->fields['display_title'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_right_to_left">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_right_to_left</a>
     */
    function setRightToLeft($value) {
        $this->fields['right_to_left'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_debug_log">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_job_id">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_page_count">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_page_count</a>
     */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_output_size">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_version">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_tag">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_converter_version">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_converter_version</a>
     */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(24.04|20.10|18.10|latest)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "pdf-to-pdf", "Allowed values are 24.04, 20.10, 18.10, latest.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_use_http">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_user_agent">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_proxy">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_use_curl">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_retry_count">https://pdfcrowd.com/api/pdf-to-pdf-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}

/**
 * Conversion from an image to PDF.
 *
 * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/">https://pdfcrowd.com/api/image-to-pdf-php/</a>
 */
class ImageToPdfClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#__construct">https://pdfcrowd.com/api/image-to-pdf-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'image', 'output_format'=>'pdf');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_url">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "image-to-pdf", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "image-to-pdf", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "image-to-pdf", "The string must not be empty.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_file">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "image-to-pdf", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "image-to-pdf", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "image-to-pdf", "The string must not be empty.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_raw_data">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_raw_data</a>
     */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_raw_data_to_stream">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_raw_data_to_stream</a>
     */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_raw_data_to_file">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_raw_data_to_file</a>
     */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertRawDataToFile::file_path", "image-to-pdf", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertRawDataToStream($data, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_stream">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/image-to-pdf-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "image-to-pdf", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_resize">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_resize</a>
     */
    function setResize($resize) {
        $this->fields['resize'] = $resize;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_rotate">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_rotate</a>
     */
    function setRotate($rotate) {
        $this->fields['rotate'] = $rotate;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_x">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_x</a>
     */
    function setCropAreaX($x) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $x))
            throw new Error(create_invalid_value_message($x, "setCropAreaX", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_x"), 470);
        
        $this->fields['crop_area_x'] = $x;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_y">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_y</a>
     */
    function setCropAreaY($y) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $y))
            throw new Error(create_invalid_value_message($y, "setCropAreaY", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_y"), 470);
        
        $this->fields['crop_area_y'] = $y;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_width">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_width</a>
     */
    function setCropAreaWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setCropAreaWidth", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_width"), 470);
        
        $this->fields['crop_area_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_height">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area_height</a>
     */
    function setCropAreaHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setCropAreaHeight", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_crop_area_height"), 470);
        
        $this->fields['crop_area_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_crop_area</a>
     */
    function setCropArea($x, $y, $width, $height) {
        $this->setCropAreaX($x);
        $this->setCropAreaY($y);
        $this->setCropAreaWidth($width);
        $this->setCropAreaHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_remove_borders">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_remove_borders</a>
     */
    function setRemoveBorders($value) {
        $this->fields['remove_borders'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_size">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_size</a>
     */
    function setPageSize($size) {
        if (!preg_match("/(?i)^(A0|A1|A2|A3|A4|A5|A6|Letter)$/", $size))
            throw new Error(create_invalid_value_message($size, "setPageSize", "image-to-pdf", "Allowed values are A0, A1, A2, A3, A4, A5, A6, Letter.", "set_page_size"), 470);
        
        $this->fields['page_size'] = $size;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_width">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_width</a>
     */
    function setPageWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setPageWidth", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_page_width"), 470);
        
        $this->fields['page_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_height">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_height</a>
     */
    function setPageHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setPageHeight", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_page_height"), 470);
        
        $this->fields['page_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_dimensions">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_dimensions</a>
     */
    function setPageDimensions($width, $height) {
        $this->setPageWidth($width);
        $this->setPageHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_orientation">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_orientation</a>
     */
    function setOrientation($orientation) {
        if (!preg_match("/(?i)^(landscape|portrait)$/", $orientation))
            throw new Error(create_invalid_value_message($orientation, "setOrientation", "image-to-pdf", "Allowed values are landscape, portrait.", "set_orientation"), 470);
        
        $this->fields['orientation'] = $orientation;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_position">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_position</a>
     */
    function setPosition($position) {
        if (!preg_match("/(?i)^(center|top|bottom|left|right|top-left|top-right|bottom-left|bottom-right)$/", $position))
            throw new Error(create_invalid_value_message($position, "setPosition", "image-to-pdf", "Allowed values are center, top, bottom, left, right, top-left, top-right, bottom-left, bottom-right.", "set_position"), 470);
        
        $this->fields['position'] = $position;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_print_page_mode">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_print_page_mode</a>
     */
    function setPrintPageMode($mode) {
        if (!preg_match("/(?i)^(default|fit|stretch)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPrintPageMode", "image-to-pdf", "Allowed values are default, fit, stretch.", "set_print_page_mode"), 470);
        
        $this->fields['print_page_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_top">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_top</a>
     */
    function setMarginTop($top) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $top))
            throw new Error(create_invalid_value_message($top, "setMarginTop", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_top"), 470);
        
        $this->fields['margin_top'] = $top;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_right">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_right</a>
     */
    function setMarginRight($right) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $right))
            throw new Error(create_invalid_value_message($right, "setMarginRight", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_right"), 470);
        
        $this->fields['margin_right'] = $right;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_bottom">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_bottom</a>
     */
    function setMarginBottom($bottom) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $bottom))
            throw new Error(create_invalid_value_message($bottom, "setMarginBottom", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_bottom"), 470);
        
        $this->fields['margin_bottom'] = $bottom;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_left">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_margin_left</a>
     */
    function setMarginLeft($left) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $left))
            throw new Error(create_invalid_value_message($left, "setMarginLeft", "image-to-pdf", "The value must be specified in inches 'in', millimeters 'mm', centimeters 'cm', pixels 'px', or points 'pt'.", "set_margin_left"), 470);
        
        $this->fields['margin_left'] = $left;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_margins">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_margins</a>
     */
    function setPageMargins($top, $right, $bottom, $left) {
        $this->setMarginTop($top);
        $this->setMarginRight($right);
        $this->setMarginBottom($bottom);
        $this->setMarginLeft($left);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_background_color">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_background_color</a>
     */
    function setPageBackgroundColor($color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $color))
            throw new Error(create_invalid_value_message($color, "setPageBackgroundColor", "image-to-pdf", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_page_background_color"), 470);
        
        $this->fields['page_background_color'] = $color;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_dpi">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_dpi</a>
     */
    function setDpi($dpi) {
        $this->fields['dpi'] = $dpi;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_watermark">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_watermark</a>
     */
    function setPageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setPageWatermark", "image-to-pdf", "The file must exist and not be empty.", "set_page_watermark"), 470);
        
        $this->files['page_watermark'] = $watermark;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_watermark_url">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_watermark_url</a>
     */
    function setPageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageWatermarkUrl", "image-to-pdf", "Supported protocols are http:// and https://.", "set_page_watermark_url"), 470);
        
        $this->fields['page_watermark_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_watermark">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_watermark</a>
     */
    function setMultipageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setMultipageWatermark", "image-to-pdf", "The file must exist and not be empty.", "set_multipage_watermark"), 470);
        
        $this->files['multipage_watermark'] = $watermark;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_watermark_url">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_watermark_url</a>
     */
    function setMultipageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageWatermarkUrl", "image-to-pdf", "Supported protocols are http:// and https://.", "set_multipage_watermark_url"), 470);
        
        $this->fields['multipage_watermark_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_background">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_background</a>
     */
    function setPageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setPageBackground", "image-to-pdf", "The file must exist and not be empty.", "set_page_background"), 470);
        
        $this->files['page_background'] = $background;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_background_url">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_background_url</a>
     */
    function setPageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageBackgroundUrl", "image-to-pdf", "Supported protocols are http:// and https://.", "set_page_background_url"), 470);
        
        $this->fields['page_background_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_background">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_background</a>
     */
    function setMultipageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setMultipageBackground", "image-to-pdf", "The file must exist and not be empty.", "set_multipage_background"), 470);
        
        $this->files['multipage_background'] = $background;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_background_url">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_multipage_background_url</a>
     */
    function setMultipageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageBackgroundUrl", "image-to-pdf", "Supported protocols are http:// and https://.", "set_multipage_background_url"), 470);
        
        $this->fields['multipage_background_url'] = $url;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_linearize">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_linearize</a>
     */
    function setLinearize($value) {
        $this->fields['linearize'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_encrypt">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_encrypt</a>
     */
    function setEncrypt($value) {
        $this->fields['encrypt'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_user_password">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_user_password</a>
     */
    function setUserPassword($password) {
        $this->fields['user_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_owner_password">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_owner_password</a>
     */
    function setOwnerPassword($password) {
        $this->fields['owner_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_no_print">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_no_print</a>
     */
    function setNoPrint($value) {
        $this->fields['no_print'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_no_modify">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_no_modify</a>
     */
    function setNoModify($value) {
        $this->fields['no_modify'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_no_copy">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_no_copy</a>
     */
    function setNoCopy($value) {
        $this->fields['no_copy'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_title">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_title</a>
     */
    function setTitle($title) {
        $this->fields['title'] = $title;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_subject">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_subject</a>
     */
    function setSubject($subject) {
        $this->fields['subject'] = $subject;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_author">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_author</a>
     */
    function setAuthor($author) {
        $this->fields['author'] = $author;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_keywords">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_keywords</a>
     */
    function setKeywords($keywords) {
        $this->fields['keywords'] = $keywords;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_layout">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_layout</a>
     */
    function setPageLayout($layout) {
        if (!preg_match("/(?i)^(single-page|one-column|two-column-left|two-column-right)$/", $layout))
            throw new Error(create_invalid_value_message($layout, "setPageLayout", "image-to-pdf", "Allowed values are single-page, one-column, two-column-left, two-column-right.", "set_page_layout"), 470);
        
        $this->fields['page_layout'] = $layout;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_mode">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_page_mode</a>
     */
    function setPageMode($mode) {
        if (!preg_match("/(?i)^(full-screen|thumbnails|outlines)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPageMode", "image-to-pdf", "Allowed values are full-screen, thumbnails, outlines.", "set_page_mode"), 470);
        
        $this->fields['page_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_initial_zoom_type">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_initial_zoom_type</a>
     */
    function setInitialZoomType($zoom_type) {
        if (!preg_match("/(?i)^(fit-width|fit-height|fit-page)$/", $zoom_type))
            throw new Error(create_invalid_value_message($zoom_type, "setInitialZoomType", "image-to-pdf", "Allowed values are fit-width, fit-height, fit-page.", "set_initial_zoom_type"), 470);
        
        $this->fields['initial_zoom_type'] = $zoom_type;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_initial_page">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_initial_page</a>
     */
    function setInitialPage($page) {
        if (!(intval($page) > 0))
            throw new Error(create_invalid_value_message($page, "setInitialPage", "image-to-pdf", "Must be a positive integer.", "set_initial_page"), 470);
        
        $this->fields['initial_page'] = $page;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_initial_zoom">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_initial_zoom</a>
     */
    function setInitialZoom($zoom) {
        if (!(intval($zoom) > 0))
            throw new Error(create_invalid_value_message($zoom, "setInitialZoom", "image-to-pdf", "Must be a positive integer.", "set_initial_zoom"), 470);
        
        $this->fields['initial_zoom'] = $zoom;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_hide_toolbar">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_hide_toolbar</a>
     */
    function setHideToolbar($value) {
        $this->fields['hide_toolbar'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_hide_menubar">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_hide_menubar</a>
     */
    function setHideMenubar($value) {
        $this->fields['hide_menubar'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_hide_window_ui">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_hide_window_ui</a>
     */
    function setHideWindowUi($value) {
        $this->fields['hide_window_ui'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_fit_window">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_fit_window</a>
     */
    function setFitWindow($value) {
        $this->fields['fit_window'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_center_window">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_center_window</a>
     */
    function setCenterWindow($value) {
        $this->fields['center_window'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_display_title">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_display_title</a>
     */
    function setDisplayTitle($value) {
        $this->fields['display_title'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_debug_log">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_job_id">https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_output_size">https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_version">https://pdfcrowd.com/api/image-to-pdf-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_tag">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_http_proxy">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "image-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_https_proxy">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "image-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_converter_version">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_converter_version</a>
     */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(24.04|20.10|18.10|latest)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "image-to-pdf", "Allowed values are 24.04, 20.10, 18.10, latest.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_use_http">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_user_agent">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_proxy">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_use_curl">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_retry_count">https://pdfcrowd.com/api/image-to-pdf-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}

/**
 * Conversion from PDF to HTML.
 *
 * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/">https://pdfcrowd.com/api/pdf-to-html-php/</a>
 */
class PdfToHtmlClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#__construct">https://pdfcrowd.com/api/pdf-to-html-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'pdf', 'output_format'=>'html');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_url">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "pdf-to-html", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "pdf-to-html", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "pdf-to-html", "The string must not be empty.", "convert_url_to_file"), 470);
        
        if (!($this->isOutputTypeValid($file_path)))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "pdf-to-html", "The converter generates an HTML or ZIP file. If ZIP file is generated, the file path must have a ZIP or zip extension.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_file">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "pdf-to-html", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "pdf-to-html", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "pdf-to-html", "The string must not be empty.", "convert_file_to_file"), 470);
        
        if (!($this->isOutputTypeValid($file_path)))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "pdf-to-html", "The converter generates an HTML or ZIP file. If ZIP file is generated, the file path must have a ZIP or zip extension.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_raw_data">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_raw_data</a>
     */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_raw_data_to_stream">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_raw_data_to_stream</a>
     */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_raw_data_to_file">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_raw_data_to_file</a>
     */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertRawDataToFile::file_path", "pdf-to-html", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
        if (!($this->isOutputTypeValid($file_path)))
            throw new Error(create_invalid_value_message($file_path, "convertRawDataToFile::file_path", "pdf-to-html", "The converter generates an HTML or ZIP file. If ZIP file is generated, the file path must have a ZIP or zip extension.", "convert_raw_data_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertRawDataToStream($data, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_stream">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/pdf-to-html-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "pdf-to-html", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        if (!($this->isOutputTypeValid($file_path)))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "pdf-to-html", "The converter generates an HTML or ZIP file. If ZIP file is generated, the file path must have a ZIP or zip extension.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_pdf_password">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_pdf_password</a>
     */
    function setPdfPassword($password) {
        $this->fields['pdf_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_scale_factor">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_scale_factor</a>
     */
    function setScaleFactor($factor) {
        if (!(intval($factor) > 0))
            throw new Error(create_invalid_value_message($factor, "setScaleFactor", "pdf-to-html", "Must be a positive integer.", "set_scale_factor"), 470);
        
        $this->fields['scale_factor'] = $factor;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_print_page_range">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_print_page_range</a>
     */
    function setPrintPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setPrintPageRange", "pdf-to-html", "A comma separated list of page numbers or ranges.", "set_print_page_range"), 470);
        
        $this->fields['print_page_range'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_dpi">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_dpi</a>
     */
    function setDpi($dpi) {
        $this->fields['dpi'] = $dpi;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_image_mode">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_image_mode</a>
     */
    function setImageMode($mode) {
        if (!preg_match("/(?i)^(embed|separate|none)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setImageMode", "pdf-to-html", "Allowed values are embed, separate, none.", "set_image_mode"), 470);
        
        $this->fields['image_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_image_format">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_image_format</a>
     */
    function setImageFormat($image_format) {
        if (!preg_match("/(?i)^(png|jpg|svg)$/", $image_format))
            throw new Error(create_invalid_value_message($image_format, "setImageFormat", "pdf-to-html", "Allowed values are png, jpg, svg.", "set_image_format"), 470);
        
        $this->fields['image_format'] = $image_format;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_css_mode">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_css_mode</a>
     */
    function setCssMode($mode) {
        if (!preg_match("/(?i)^(embed|separate)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setCssMode", "pdf-to-html", "Allowed values are embed, separate.", "set_css_mode"), 470);
        
        $this->fields['css_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_font_mode">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_font_mode</a>
     */
    function setFontMode($mode) {
        if (!preg_match("/(?i)^(embed|separate)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setFontMode", "pdf-to-html", "Allowed values are embed, separate.", "set_font_mode"), 470);
        
        $this->fields['font_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_type3_mode">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_type3_mode</a>
     */
    function setType3Mode($mode) {
        if (!preg_match("/(?i)^(raster|convert)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setType3Mode", "pdf-to-html", "Allowed values are raster, convert.", "set_type3_mode"), 470);
        
        $this->fields['type3_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_split_ligatures">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_split_ligatures</a>
     */
    function setSplitLigatures($value) {
        $this->fields['split_ligatures'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_custom_css">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_custom_css</a>
     */
    function setCustomCss($css) {
        if (!($css != null && $css !== ''))
            throw new Error(create_invalid_value_message($css, "setCustomCss", "pdf-to-html", "The string must not be empty.", "set_custom_css"), 470);
        
        $this->fields['custom_css'] = $css;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_html_namespace">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_html_namespace</a>
     */
    function setHtmlNamespace($prefix) {
        if (!preg_match("/(?i)^[a-z_][a-z0-9_:-]*$/", $prefix))
            throw new Error(create_invalid_value_message($prefix, "setHtmlNamespace", "pdf-to-html", "Start with a letter or underscore, and use only letters, numbers, hyphens, underscores, or colons.", "set_html_namespace"), 470);
        
        $this->fields['html_namespace'] = $prefix;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#is_zipped_output">https://pdfcrowd.com/api/pdf-to-html-php/ref/#is_zipped_output</a>
     */
    function isZippedOutput() {
        return (isset($this->fields['image_mode']) && $this->fields['image_mode'] == 'separate') || (isset($this->fields['css_mode']) && $this->fields['css_mode'] == 'separate') || (isset($this->fields['font_mode']) && $this->fields['font_mode'] == 'separate') || (isset($this->fields['force_zip']) && $this->fields['force_zip'] == 'true');
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_force_zip">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_force_zip</a>
     */
    function setForceZip($value) {
        $this->fields['force_zip'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_title">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_title</a>
     */
    function setTitle($title) {
        $this->fields['title'] = $title;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_subject">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_subject</a>
     */
    function setSubject($subject) {
        $this->fields['subject'] = $subject;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_author">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_author</a>
     */
    function setAuthor($author) {
        $this->fields['author'] = $author;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_keywords">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_keywords</a>
     */
    function setKeywords($keywords) {
        $this->fields['keywords'] = $keywords;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_debug_log">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_job_id">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_page_count">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_page_count</a>
     */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_output_size">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_version">https://pdfcrowd.com/api/pdf-to-html-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_tag">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_http_proxy">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "pdf-to-html", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_https_proxy">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "pdf-to-html", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_converter_version">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_converter_version</a>
     */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(24.04|20.10|18.10|latest)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "pdf-to-html", "Allowed values are 24.04, 20.10, 18.10, latest.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_use_http">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_user_agent">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_proxy">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_use_curl">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_retry_count">https://pdfcrowd.com/api/pdf-to-html-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

    private function isOutputTypeValid($file_path) {
        $extension = pathinfo($file_path)['extension'];
        return ($extension === "zip") === $this->isZippedOutput();
    }
}

/**
 * Conversion from PDF to text.
 *
 * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/">https://pdfcrowd.com/api/pdf-to-text-php/</a>
 */
class PdfToTextClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#__construct">https://pdfcrowd.com/api/pdf-to-text-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'pdf', 'output_format'=>'txt');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_url">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "pdf-to-text", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "pdf-to-text", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "pdf-to-text", "The string must not be empty.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_file">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "pdf-to-text", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "pdf-to-text", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "pdf-to-text", "The string must not be empty.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_raw_data">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_raw_data</a>
     */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_raw_data_to_stream">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_raw_data_to_stream</a>
     */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_raw_data_to_file">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_raw_data_to_file</a>
     */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertRawDataToFile::file_path", "pdf-to-text", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertRawDataToStream($data, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_stream">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/pdf-to-text-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "pdf-to-text", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_pdf_password">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_pdf_password</a>
     */
    function setPdfPassword($password) {
        $this->fields['pdf_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_print_page_range">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_print_page_range</a>
     */
    function setPrintPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setPrintPageRange", "pdf-to-text", "A comma separated list of page numbers or ranges.", "set_print_page_range"), 470);
        
        $this->fields['print_page_range'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_no_layout">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_no_layout</a>
     */
    function setNoLayout($value) {
        $this->fields['no_layout'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_eol">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_eol</a>
     */
    function setEol($eol) {
        if (!preg_match("/(?i)^(unix|dos|mac)$/", $eol))
            throw new Error(create_invalid_value_message($eol, "setEol", "pdf-to-text", "Allowed values are unix, dos, mac.", "set_eol"), 470);
        
        $this->fields['eol'] = $eol;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_page_break_mode">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_page_break_mode</a>
     */
    function setPageBreakMode($mode) {
        if (!preg_match("/(?i)^(none|default|custom)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPageBreakMode", "pdf-to-text", "Allowed values are none, default, custom.", "set_page_break_mode"), 470);
        
        $this->fields['page_break_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_custom_page_break">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_custom_page_break</a>
     */
    function setCustomPageBreak($page_break) {
        $this->fields['custom_page_break'] = $page_break;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_paragraph_mode">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_paragraph_mode</a>
     */
    function setParagraphMode($mode) {
        if (!preg_match("/(?i)^(none|bounding-box|characters)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setParagraphMode", "pdf-to-text", "Allowed values are none, bounding-box, characters.", "set_paragraph_mode"), 470);
        
        $this->fields['paragraph_mode'] = $mode;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_line_spacing_threshold">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_line_spacing_threshold</a>
     */
    function setLineSpacingThreshold($threshold) {
        if (!preg_match("/(?i)^0$|^[0-9]+%$/", $threshold))
            throw new Error(create_invalid_value_message($threshold, "setLineSpacingThreshold", "pdf-to-text", "The value must be a positive integer percentage.", "set_line_spacing_threshold"), 470);
        
        $this->fields['line_spacing_threshold'] = $threshold;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_remove_hyphenation">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_remove_hyphenation</a>
     */
    function setRemoveHyphenation($value) {
        $this->fields['remove_hyphenation'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_remove_empty_lines">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_remove_empty_lines</a>
     */
    function setRemoveEmptyLines($value) {
        $this->fields['remove_empty_lines'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_x">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_x</a>
     */
    function setCropAreaX($x) {
        if (!(intval($x) >= 0))
            throw new Error(create_invalid_value_message($x, "setCropAreaX", "pdf-to-text", "Must be a positive integer or 0.", "set_crop_area_x"), 470);
        
        $this->fields['crop_area_x'] = $x;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_y">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_y</a>
     */
    function setCropAreaY($y) {
        if (!(intval($y) >= 0))
            throw new Error(create_invalid_value_message($y, "setCropAreaY", "pdf-to-text", "Must be a positive integer or 0.", "set_crop_area_y"), 470);
        
        $this->fields['crop_area_y'] = $y;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_width">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_width</a>
     */
    function setCropAreaWidth($width) {
        if (!(intval($width) >= 0))
            throw new Error(create_invalid_value_message($width, "setCropAreaWidth", "pdf-to-text", "Must be a positive integer or 0.", "set_crop_area_width"), 470);
        
        $this->fields['crop_area_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_height">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area_height</a>
     */
    function setCropAreaHeight($height) {
        if (!(intval($height) >= 0))
            throw new Error(create_invalid_value_message($height, "setCropAreaHeight", "pdf-to-text", "Must be a positive integer or 0.", "set_crop_area_height"), 470);
        
        $this->fields['crop_area_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_crop_area</a>
     */
    function setCropArea($x, $y, $width, $height) {
        $this->setCropAreaX($x);
        $this->setCropAreaY($y);
        $this->setCropAreaWidth($width);
        $this->setCropAreaHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_debug_log">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_job_id">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_page_count">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_page_count</a>
     */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_output_size">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_version">https://pdfcrowd.com/api/pdf-to-text-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_tag">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_http_proxy">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "pdf-to-text", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_https_proxy">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "pdf-to-text", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_use_http">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_user_agent">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_proxy">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_use_curl">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_retry_count">https://pdfcrowd.com/api/pdf-to-text-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}

/**
 * Conversion from PDF to image.
 *
 * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/">https://pdfcrowd.com/api/pdf-to-image-php/</a>
 */
class PdfToImageClient {
    private $helper;
    private $fields;
    private $file_id;
    private $files;
    private $raw_data;

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#__construct">https://pdfcrowd.com/api/pdf-to-image-php/ref/#__construct</a>
     */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'pdf', 'output_format'=>'png');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_url">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_url</a>
     */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrl", "pdf-to-image", "Supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_url_to_stream">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_url_to_stream</a>
     */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "pdf-to-image", "Supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_url_to_file">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_url_to_file</a>
     */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertUrlToFile::file_path", "pdf-to-image", "The string must not be empty.", "convert_url_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertUrlToStream($url, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_file">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_file</a>
     */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "pdf-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_file_to_stream">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_file_to_stream</a>
     */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "pdf-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_file_to_file">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_file_to_file</a>
     */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertFileToFile::file_path", "pdf-to-image", "The string must not be empty.", "convert_file_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertFileToStream($file, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_raw_data">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_raw_data</a>
     */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_raw_data_to_stream">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_raw_data_to_stream</a>
     */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_raw_data_to_file">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_raw_data_to_file</a>
     */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertRawDataToFile::file_path", "pdf-to-image", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertRawDataToStream($data, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_stream">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_stream</a>
     */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_stream_to_stream">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_stream_to_stream</a>
     */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_stream_to_file">https://pdfcrowd.com/api/pdf-to-image-php/ref/#convert_stream_to_file</a>
     */
    function convertStreamToFile($in_stream, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "convertStreamToFile::file_path", "pdf-to-image", "The string must not be empty.", "convert_stream_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        if (!$output_file) {
            $error = error_get_last();
            throw new \Exception($error['message']);
        }
        try {
            $this->convertStreamToStream($in_stream, $output_file);
            fclose($output_file);
        }
        catch(Error $why) {
            fclose($output_file);
            unlink($file_path);
            throw $why;
        }
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_output_format">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_output_format</a>
     */
    function setOutputFormat($output_format) {
        if (!preg_match("/(?i)^(png|jpg|gif|tiff|bmp|ico|ppm|pgm|pbm|pnm|psb|pct|ras|tga|sgi|sun|webp)$/", $output_format))
            throw new Error(create_invalid_value_message($output_format, "setOutputFormat", "pdf-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
        $this->fields['output_format'] = $output_format;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_pdf_password">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_pdf_password</a>
     */
    function setPdfPassword($password) {
        $this->fields['pdf_password'] = $password;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_print_page_range">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_print_page_range</a>
     */
    function setPrintPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setPrintPageRange", "pdf-to-image", "A comma separated list of page numbers or ranges.", "set_print_page_range"), 470);
        
        $this->fields['print_page_range'] = $pages;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_dpi">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_dpi</a>
     */
    function setDpi($dpi) {
        $this->fields['dpi'] = $dpi;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#is_zipped_output">https://pdfcrowd.com/api/pdf-to-image-php/ref/#is_zipped_output</a>
     */
    function isZippedOutput() {
        return (isset($this->fields['force_zip']) && $this->fields['force_zip'] == 'true') || $this->getPageCount() > 1;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_force_zip">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_force_zip</a>
     */
    function setForceZip($value) {
        $this->fields['force_zip'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_cropbox">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_cropbox</a>
     */
    function setUseCropbox($value) {
        $this->fields['use_cropbox'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_x">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_x</a>
     */
    function setCropAreaX($x) {
        if (!(intval($x) >= 0))
            throw new Error(create_invalid_value_message($x, "setCropAreaX", "pdf-to-image", "Must be a positive integer or 0.", "set_crop_area_x"), 470);
        
        $this->fields['crop_area_x'] = $x;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_y">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_y</a>
     */
    function setCropAreaY($y) {
        if (!(intval($y) >= 0))
            throw new Error(create_invalid_value_message($y, "setCropAreaY", "pdf-to-image", "Must be a positive integer or 0.", "set_crop_area_y"), 470);
        
        $this->fields['crop_area_y'] = $y;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_width">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_width</a>
     */
    function setCropAreaWidth($width) {
        if (!(intval($width) >= 0))
            throw new Error(create_invalid_value_message($width, "setCropAreaWidth", "pdf-to-image", "Must be a positive integer or 0.", "set_crop_area_width"), 470);
        
        $this->fields['crop_area_width'] = $width;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_height">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area_height</a>
     */
    function setCropAreaHeight($height) {
        if (!(intval($height) >= 0))
            throw new Error(create_invalid_value_message($height, "setCropAreaHeight", "pdf-to-image", "Must be a positive integer or 0.", "set_crop_area_height"), 470);
        
        $this->fields['crop_area_height'] = $height;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_crop_area</a>
     */
    function setCropArea($x, $y, $width, $height) {
        $this->setCropAreaX($x);
        $this->setCropAreaY($y);
        $this->setCropAreaWidth($width);
        $this->setCropAreaHeight($height);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_grayscale">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_grayscale</a>
     */
    function setUseGrayscale($value) {
        $this->fields['use_grayscale'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_debug_log">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_debug_log</a>
     */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_debug_log_url">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_debug_log_url</a>
     */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_remaining_credit_count">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_remaining_credit_count</a>
     */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_consumed_credit_count">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_consumed_credit_count</a>
     */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_job_id">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_job_id</a>
     */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_page_count">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_page_count</a>
     */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_output_size">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_output_size</a>
     */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_version">https://pdfcrowd.com/api/pdf-to-image-php/ref/#get_version</a>
     */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_tag">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_tag</a>
     */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_http_proxy">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_http_proxy</a>
     */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "pdf-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_https_proxy">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_https_proxy</a>
     */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "pdf-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_http">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_http</a>
     */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_client_user_agent">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_client_user_agent</a>
     */
    function setClientUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_user_agent">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_user_agent</a>
     */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_proxy">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_proxy</a>
     */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_curl">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_use_curl</a>
     */
    function setUseCurl($value) {
        $this->helper->setUseCurl($value);
        return $this;
    }

    /**
     * @see <a href="https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_retry_count">https://pdfcrowd.com/api/pdf-to-image-php/ref/#set_retry_count</a>
     */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}


}

?>
