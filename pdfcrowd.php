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
// Pdfcrowd API client.
//
class PdfCrowd {
    //
    // Pdfcrowd constructor.
    //
    // $username - your username at Pdfcrowd
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
    directory is somewhere else than you expect: '${cwd}'
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

    public static $client_version = "4.4.2";
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
    // custom string representation of object
    public function __toString() {
        if ($this->code) {
            return "{$this->code} - {$this->message}";
        }
        return "{$this->message}";
    }
}

define('Pdfcrowd\HOST', getenv('PDFCROWD_HOST') ?: 'api.pdfcrowd.com');

const CLIENT_VERSION = '4.4.2';
const MULTIPART_BOUNDARY = '----------ThIs_Is_tHe_bOUnDary_$';

function create_invalid_value_message($value, $field, $converter, $hint, $id) {
    $message = "Invalid value '$value' for a field '$field'.";
    if($hint != null) {
        $message = $message . " " . $hint;
    }
    return $message . " " . "Details: https://www.pdfcrowd.com/doc/api/$converter/php/#$id";
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
        $this->user_name = $user_name;
        $this->api_key = $api_key;

        $this->reset_response_data();
        $this->setProxy(null, null, null, null);
        $this->setUseHttp(false);
        $this->setUserAgent('pdfcrowd_php_client/4.4.2 (http://pdfcrowd.com)');

        $this->retry_count = 1;

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

    private $proxy_host;
    private $proxy_port;
    private $proxy_user_name;
    private $proxy_password;

    private $retry_count;
    private $retry;
    private $error_message;

    private $use_curl;

    private static $SSL_ERRORS = array(35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91);

    private function add_file_field($name, $file_name, $data, &$body) {
        $body .= "--" . MULTIPART_BOUNDARY . "\r\n";
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
        $this->output_size = 0;
        $this->retry = 0;
    }

    private function build_body($fields, $files, $raw_data) {
        $body = '';

        foreach ($fields as $name => $content) {
            $body .= "--" . MULTIPART_BOUNDARY . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $body .= $content . "\r\n";
        }

        foreach ($files as $name => $file_name) {
            $this->add_file_field($name, $file_name, file_get_contents($file_name), $body);
        }

        foreach ($raw_data as $name => $data) {
            $this->add_file_field($name, $name, $data, $body);
        }

        return $body . "--" . MULTIPART_BOUNDARY . "--\r\n";
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
        if (($code == 502 || getenv('PDFCROWD_UNIT_TEST_MODE'))
            && $this->retry_count > $this->retry) {
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
        curl_setopt($c, CURLOPT_URL, $this->url);
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

        if ($this->scheme == 'https' && HOST == 'api.pdfcrowd.com') {
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
                'Content-Type: multipart/form-data; boundary=' . MULTIPART_BOUNDARY,
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
                throw new Error("There was a problem connecting to Pdfcrowd servers over HTTPS:\n" .
                                "{$error_str} ({$error_nr})" .
                                "\nYou can still use the API over HTTP, you just need to add the following line right after Pdfcrowd client initialization:\n\$client->setUseHttp(true);",
                                481);
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
            'Content-Type: multipart/form-data; boundary=' . MULTIPART_BOUNDARY,
            'Content-Length: ' . strlen($body),
            'Authorization: Basic ' . $auth,
            'User-Agent: ' . $this->user_agent
        );

        $context_options = array(
            'http' => array(
                'method' => 'POST',
                'content' => $body,
                'ignore_errors' => true
            )
        );

        if (HOST != 'api.pdfcrowd.com') {
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
        $response = $this->exec_request_no_curl($this->url, $context);

        return $this->output_body($response['code'], $response['body'], $out_stream);
    }

    private function parse_response_headers($headers) {
        $code = 555;
        foreach ($headers as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)\s*.*/', $header, $matches)) {
                $code = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Job-Id:\s+(.*)/', $header, $matches)) {
                $this->job_id = $matches[1];
            } else if(preg_match('/X-Pdfcrowd-Pages:\s+(.*)/', $header, $matches)) {
                $this->page_count = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Output-Size:\s+(.*)/', $header, $matches)) {
                $this->output_size = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Remaining-Credits:\s+(.*)/', $header, $matches)) {
                $this->credits = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Consumed-Credits:\s+(.*)/', $header, $matches)) {
                $this->consumed_credits = intval($matches[1]);
            } else if(preg_match('/X-Pdfcrowd-Debug-Log:\s+(.*)/', $header, $matches)) {
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
                throw new Error($this->error_message);
            }
            throw new Error("There was a problem connecting to Pdfcrowd servers over HTTPS:\n" .
                            $this->error_message .
                            "\nYou can still use the API over HTTP, you just need to add the following line right after Pdfcrowd client initialization:\n\$client->setUseHttp(true);",
                            481);
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
        $this->url = "{$this->scheme}://".HOST.'/convert/';
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

    function getOutputSize() {
        return $this->output_size;
    }

    function setUseCurl($use_curl) {
        $this->use_curl = $use_curl;
    }
}

// generated code

/**
* Conversion from HTML to PDF.
*/
class HtmlToPdfClient {
    private $helper;
    private $fields;

    /**
    * Constructor for the Pdfcrowd API client.
    *
    * @param user_name Your username at Pdfcrowd.
    * @param api_key Your API key.
    */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'html', 'output_format'=>'pdf');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
    * Convert a web page.
    *
    * @param url The address of the web page to convert. The supported protocols are http:// and https://.
    * @return Byte array containing the conversion output.
    */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "html-to-pdf", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a web page and write the result to an output stream.
    *
    * @param url The address of the web page to convert. The supported protocols are http:// and https://.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "html-to-pdf", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a web page and write the result to a local file.
    *
    * @param url The address of the web page to convert. The supported protocols are http:// and https://.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "html-to-pdf", "The string must not be empty.", "convert_url_to_file"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-pdf", "The file must exist and not be empty.", "convert_file"), 470);
        
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-pdf", "The file name must have a valid extension.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a local file and write the result to an output stream.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-pdf", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-pdf", "The file name must have a valid extension.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a local file and write the result to a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "html-to-pdf", "The string must not be empty.", "convert_file_to_file"), 470);
        
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
    * Convert a string.
    *
    * @param text The string content to convert. The string must not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertString($text) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "text", "html-to-pdf", "The string must not be empty.", "convert_string"), 470);
        
        $this->fields['text'] = $text;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a string and write the output to an output stream.
    *
    * @param text The string content to convert. The string must not be empty.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertStringToStream($text, $out_stream) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "text", "html-to-pdf", "The string must not be empty.", "convert_string_to_stream"), 470);
        
        $this->fields['text'] = $text;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a string and write the output to a file.
    *
    * @param text The string content to convert. The string must not be empty.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertStringToFile($text, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "html-to-pdf", "The string must not be empty.", "convert_string_to_file"), 470);
        
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
    * Set the output page size.
    *
    * @param page_size Allowed values are A2, A3, A4, A5, A6, Letter.
    * @return The converter object.
    */
    function setPageSize($page_size) {
        if (!preg_match("/(?i)^(A2|A3|A4|A5|A6|Letter)$/", $page_size))
            throw new Error(create_invalid_value_message($page_size, "page_size", "html-to-pdf", "Allowed values are A2, A3, A4, A5, A6, Letter.", "set_page_size"), 470);
        
        $this->fields['page_size'] = $page_size;
        return $this;
    }

    /**
    * Set the output page width. The safe maximum is <span class='field-value'>200in</span> otherwise some PDF viewers may be unable to open the PDF.
    *
    * @param page_width Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setPageWidth($page_width) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $page_width))
            throw new Error(create_invalid_value_message($page_width, "page_width", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_page_width"), 470);
        
        $this->fields['page_width'] = $page_width;
        return $this;
    }

    /**
    * Set the output page height. Use <span class='field-value'>-1</span> for a single page PDF. The safe maximum is <span class='field-value'>200in</span> otherwise some PDF viewers may be unable to open the PDF.
    *
    * @param page_height Can be -1 or specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setPageHeight($page_height) {
        if (!preg_match("/(?i)^\-1$|^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $page_height))
            throw new Error(create_invalid_value_message($page_height, "page_height", "html-to-pdf", "Can be -1 or specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_page_height"), 470);
        
        $this->fields['page_height'] = $page_height;
        return $this;
    }

    /**
    * Set the output page dimensions.
    *
    * @param width Set the output page width. The safe maximum is <span class='field-value'>200in</span> otherwise some PDF viewers may be unable to open the PDF. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @param height Set the output page height. Use <span class='field-value'>-1</span> for a single page PDF. The safe maximum is <span class='field-value'>200in</span> otherwise some PDF viewers may be unable to open the PDF. Can be -1 or specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setPageDimensions($width, $height) {
        $this->setPageWidth($width);
        $this->setPageHeight($height);
        return $this;
    }

    /**
    * Set the output page orientation.
    *
    * @param orientation Allowed values are landscape, portrait.
    * @return The converter object.
    */
    function setOrientation($orientation) {
        if (!preg_match("/(?i)^(landscape|portrait)$/", $orientation))
            throw new Error(create_invalid_value_message($orientation, "orientation", "html-to-pdf", "Allowed values are landscape, portrait.", "set_orientation"), 470);
        
        $this->fields['orientation'] = $orientation;
        return $this;
    }

    /**
    * Set the output page top margin.
    *
    * @param margin_top Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginTop($margin_top) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $margin_top))
            throw new Error(create_invalid_value_message($margin_top, "margin_top", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_top"), 470);
        
        $this->fields['margin_top'] = $margin_top;
        return $this;
    }

    /**
    * Set the output page right margin.
    *
    * @param margin_right Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginRight($margin_right) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $margin_right))
            throw new Error(create_invalid_value_message($margin_right, "margin_right", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_right"), 470);
        
        $this->fields['margin_right'] = $margin_right;
        return $this;
    }

    /**
    * Set the output page bottom margin.
    *
    * @param margin_bottom Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginBottom($margin_bottom) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $margin_bottom))
            throw new Error(create_invalid_value_message($margin_bottom, "margin_bottom", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_bottom"), 470);
        
        $this->fields['margin_bottom'] = $margin_bottom;
        return $this;
    }

    /**
    * Set the output page left margin.
    *
    * @param margin_left Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginLeft($margin_left) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $margin_left))
            throw new Error(create_invalid_value_message($margin_left, "margin_left", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_left"), 470);
        
        $this->fields['margin_left'] = $margin_left;
        return $this;
    }

    /**
    * Disable margins.
    *
    * @param no_margins Set to <span class='field-value'>true</span> to disable margins.
    * @return The converter object.
    */
    function setNoMargins($no_margins) {
        $this->fields['no_margins'] = $no_margins;
        return $this;
    }

    /**
    * Set the output page margins.
    *
    * @param top Set the output page top margin. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @param right Set the output page right margin. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @param bottom Set the output page bottom margin. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @param left Set the output page left margin. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setPageMargins($top, $right, $bottom, $left) {
        $this->setMarginTop($top);
        $this->setMarginRight($right);
        $this->setMarginBottom($bottom);
        $this->setMarginLeft($left);
        return $this;
    }

    /**
    * Load an HTML code from the specified URL and use it as the page header. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values</li> <li>Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt;</li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul>
</li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param header_url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setHeaderUrl($header_url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $header_url))
            throw new Error(create_invalid_value_message($header_url, "header_url", "html-to-pdf", "The supported protocols are http:// and https://.", "set_header_url"), 470);
        
        $this->fields['header_url'] = $header_url;
        return $this;
    }

    /**
    * Use the specified HTML code as the page header. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values</li> <li>Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt;</li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul>
</li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param header_html The string must not be empty.
    * @return The converter object.
    */
    function setHeaderHtml($header_html) {
        if (!($header_html != null && $header_html !== ''))
            throw new Error(create_invalid_value_message($header_html, "header_html", "html-to-pdf", "The string must not be empty.", "set_header_html"), 470);
        
        $this->fields['header_html'] = $header_html;
        return $this;
    }

    /**
    * Set the header height.
    *
    * @param header_height Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setHeaderHeight($header_height) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $header_height))
            throw new Error(create_invalid_value_message($header_height, "header_height", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_header_height"), 470);
        
        $this->fields['header_height'] = $header_height;
        return $this;
    }

    /**
    * Load an HTML code from the specified URL and use it as the page footer. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values</li> <li>Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt;</li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul>
</li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param footer_url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setFooterUrl($footer_url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $footer_url))
            throw new Error(create_invalid_value_message($footer_url, "footer_url", "html-to-pdf", "The supported protocols are http:// and https://.", "set_footer_url"), 470);
        
        $this->fields['footer_url'] = $footer_url;
        return $this;
    }

    /**
    * Use the specified HTML as the page footer. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values</li> <li>Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt;</li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul>
</li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param footer_html The string must not be empty.
    * @return The converter object.
    */
    function setFooterHtml($footer_html) {
        if (!($footer_html != null && $footer_html !== ''))
            throw new Error(create_invalid_value_message($footer_html, "footer_html", "html-to-pdf", "The string must not be empty.", "set_footer_html"), 470);
        
        $this->fields['footer_html'] = $footer_html;
        return $this;
    }

    /**
    * Set the footer height.
    *
    * @param footer_height Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setFooterHeight($footer_height) {
        if (!preg_match("/(?i)^[0-9]*(\.[0-9]+)?(pt|px|mm|cm|in)$/", $footer_height))
            throw new Error(create_invalid_value_message($footer_height, "footer_height", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_footer_height"), 470);
        
        $this->fields['footer_height'] = $footer_height;
        return $this;
    }

    /**
    * Set the page range to print.
    *
    * @param pages A comma seperated list of page numbers or ranges.
    * @return The converter object.
    */
    function setPrintPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "pages", "html-to-pdf", "A comma seperated list of page numbers or ranges.", "set_print_page_range"), 470);
        
        $this->fields['print_page_range'] = $pages;
        return $this;
    }

    /**
    * The page background color in RGB or RGBA hexadecimal format. The color fills the entire page regardless of the margins.
    *
    * @param page_background_color The value must be in RRGGBB or RRGGBBAA hexadecimal format.
    * @return The converter object.
    */
    function setPageBackgroundColor($page_background_color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $page_background_color))
            throw new Error(create_invalid_value_message($page_background_color, "page_background_color", "html-to-pdf", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_page_background_color"), 470);
        
        $this->fields['page_background_color'] = $page_background_color;
        return $this;
    }

    /**
    * Apply the first page of the watermark PDF to every page of the output PDF.
    *
    * @param page_watermark The file path to a local watermark PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setPageWatermark($page_watermark) {
        if (!(filesize($page_watermark) > 0))
            throw new Error(create_invalid_value_message($page_watermark, "page_watermark", "html-to-pdf", "The file must exist and not be empty.", "set_page_watermark"), 470);
        
        $this->files['page_watermark'] = $page_watermark;
        return $this;
    }

    /**
    * Apply each page of the specified watermark PDF to the corresponding page of the output PDF.
    *
    * @param multipage_watermark The file path to a local watermark PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setMultipageWatermark($multipage_watermark) {
        if (!(filesize($multipage_watermark) > 0))
            throw new Error(create_invalid_value_message($multipage_watermark, "multipage_watermark", "html-to-pdf", "The file must exist and not be empty.", "set_multipage_watermark"), 470);
        
        $this->files['multipage_watermark'] = $multipage_watermark;
        return $this;
    }

    /**
    * Apply the first page of the specified PDF to the background of every page of the output PDF.
    *
    * @param page_background The file path to a local background PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setPageBackground($page_background) {
        if (!(filesize($page_background) > 0))
            throw new Error(create_invalid_value_message($page_background, "page_background", "html-to-pdf", "The file must exist and not be empty.", "set_page_background"), 470);
        
        $this->files['page_background'] = $page_background;
        return $this;
    }

    /**
    * Apply each page of the specified PDF to the background of the corresponding page of the output PDF.
    *
    * @param multipage_background The file path to a local background PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setMultipageBackground($multipage_background) {
        if (!(filesize($multipage_background) > 0))
            throw new Error(create_invalid_value_message($multipage_background, "multipage_background", "html-to-pdf", "The file must exist and not be empty.", "set_multipage_background"), 470);
        
        $this->files['multipage_background'] = $multipage_background;
        return $this;
    }

    /**
    * The page header is not printed on the specified pages.
    *
    * @param pages List of physical page numbers. Negative numbers count backwards from the last page: -1 is the last page, -2 is the last but one page, and so on. A comma seperated list of page numbers.
    * @return The converter object.
    */
    function setExcludeHeaderOnPages($pages) {
        if (!preg_match("/^(?:\s*\-?\d+\s*,)*\s*\-?\d+\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "pages", "html-to-pdf", "A comma seperated list of page numbers.", "set_exclude_header_on_pages"), 470);
        
        $this->fields['exclude_header_on_pages'] = $pages;
        return $this;
    }

    /**
    * The page footer is not printed on the specified pages.
    *
    * @param pages List of physical page numbers. Negative numbers count backwards from the last page: -1 is the last page, -2 is the last but one page, and so on. A comma seperated list of page numbers.
    * @return The converter object.
    */
    function setExcludeFooterOnPages($pages) {
        if (!preg_match("/^(?:\s*\-?\d+\s*,)*\s*\-?\d+\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "pages", "html-to-pdf", "A comma seperated list of page numbers.", "set_exclude_footer_on_pages"), 470);
        
        $this->fields['exclude_footer_on_pages'] = $pages;
        return $this;
    }

    /**
    * Set an offset between physical and logical page numbers.
    *
    * @param offset Integer specifying page offset.
    * @return The converter object.
    */
    function setPageNumberingOffset($offset) {
        $this->fields['page_numbering_offset'] = $offset;
        return $this;
    }

    /**
    * Do not print the background graphics.
    *
    * @param no_background Set to <span class='field-value'>true</span> to disable the background graphics.
    * @return The converter object.
    */
    function setNoBackground($no_background) {
        $this->fields['no_background'] = $no_background;
        return $this;
    }

    /**
    * Do not execute JavaScript.
    *
    * @param disable_javascript Set to <span class='field-value'>true</span> to disable JavaScript in web pages.
    * @return The converter object.
    */
    function setDisableJavascript($disable_javascript) {
        $this->fields['disable_javascript'] = $disable_javascript;
        return $this;
    }

    /**
    * Do not load images.
    *
    * @param disable_image_loading Set to <span class='field-value'>true</span> to disable loading of images.
    * @return The converter object.
    */
    function setDisableImageLoading($disable_image_loading) {
        $this->fields['disable_image_loading'] = $disable_image_loading;
        return $this;
    }

    /**
    * Disable loading fonts from remote sources.
    *
    * @param disable_remote_fonts Set to <span class='field-value'>true</span> disable loading remote fonts.
    * @return The converter object.
    */
    function setDisableRemoteFonts($disable_remote_fonts) {
        $this->fields['disable_remote_fonts'] = $disable_remote_fonts;
        return $this;
    }

    /**
    * Try to block ads. Enabling this option can produce smaller output and speed up the conversion.
    *
    * @param block_ads Set to <span class='field-value'>true</span> to block ads in web pages.
    * @return The converter object.
    */
    function setBlockAds($block_ads) {
        $this->fields['block_ads'] = $block_ads;
        return $this;
    }

    /**
    * Set the default HTML content text encoding.
    *
    * @param default_encoding The text encoding of the HTML content.
    * @return The converter object.
    */
    function setDefaultEncoding($default_encoding) {
        $this->fields['default_encoding'] = $default_encoding;
        return $this;
    }

    /**
    * Set the HTTP authentication user name.
    *
    * @param user_name The user name.
    * @return The converter object.
    */
    function setHttpAuthUserName($user_name) {
        $this->fields['http_auth_user_name'] = $user_name;
        return $this;
    }

    /**
    * Set the HTTP authentication password.
    *
    * @param password The password.
    * @return The converter object.
    */
    function setHttpAuthPassword($password) {
        $this->fields['http_auth_password'] = $password;
        return $this;
    }

    /**
    * Set credentials to access HTTP base authentication protected websites.
    *
    * @param user_name Set the HTTP authentication user name.
    * @param password Set the HTTP authentication password.
    * @return The converter object.
    */
    function setHttpAuth($user_name, $password) {
        $this->setHttpAuthUserName($user_name);
        $this->setHttpAuthPassword($password);
        return $this;
    }

    /**
    * Use the print version of the page if available (@media print).
    *
    * @param use_print_media Set to <span class='field-value'>true</span> to use the print version of the page.
    * @return The converter object.
    */
    function setUsePrintMedia($use_print_media) {
        $this->fields['use_print_media'] = $use_print_media;
        return $this;
    }

    /**
    * Do not send the X-Pdfcrowd HTTP header in Pdfcrowd HTTP requests.
    *
    * @param no_xpdfcrowd_header Set to <span class='field-value'>true</span> to disable sending X-Pdfcrowd HTTP header.
    * @return The converter object.
    */
    function setNoXpdfcrowdHeader($no_xpdfcrowd_header) {
        $this->fields['no_xpdfcrowd_header'] = $no_xpdfcrowd_header;
        return $this;
    }

    /**
    * Set cookies that are sent in Pdfcrowd HTTP requests.
    *
    * @param cookies The cookie string.
    * @return The converter object.
    */
    function setCookies($cookies) {
        $this->fields['cookies'] = $cookies;
        return $this;
    }

    /**
    * Do not allow insecure HTTPS connections.
    *
    * @param verify_ssl_certificates Set to <span class='field-value'>true</span> to enable SSL certificate verification.
    * @return The converter object.
    */
    function setVerifySslCertificates($verify_ssl_certificates) {
        $this->fields['verify_ssl_certificates'] = $verify_ssl_certificates;
        return $this;
    }

    /**
    * Abort the conversion if the main URL HTTP status code is greater than or equal to 400.
    *
    * @param fail_on_error Set to <span class='field-value'>true</span> to abort the conversion.
    * @return The converter object.
    */
    function setFailOnMainUrlError($fail_on_error) {
        $this->fields['fail_on_main_url_error'] = $fail_on_error;
        return $this;
    }

    /**
    * Abort the conversion if any of the sub-request HTTP status code is greater than or equal to 400 or if some sub-requests are still pending. See details in a debug log.
    *
    * @param fail_on_error Set to <span class='field-value'>true</span> to abort the conversion.
    * @return The converter object.
    */
    function setFailOnAnyUrlError($fail_on_error) {
        $this->fields['fail_on_any_url_error'] = $fail_on_error;
        return $this;
    }

    /**
    * Run a custom JavaScript after the document is loaded. The script is intended for post-load DOM manipulation (add/remove elements, update CSS, ...).
    *
    * @param custom_javascript String containing a JavaScript code. The string must not be empty.
    * @return The converter object.
    */
    function setCustomJavascript($custom_javascript) {
        if (!($custom_javascript != null && $custom_javascript !== ''))
            throw new Error(create_invalid_value_message($custom_javascript, "custom_javascript", "html-to-pdf", "The string must not be empty.", "set_custom_javascript"), 470);
        
        $this->fields['custom_javascript'] = $custom_javascript;
        return $this;
    }

    /**
    * Set a custom HTTP header that is sent in Pdfcrowd HTTP requests.
    *
    * @param custom_http_header A string containing the header name and value separated by a colon.
    * @return The converter object.
    */
    function setCustomHttpHeader($custom_http_header) {
        if (!preg_match("/^.+:.+$/", $custom_http_header))
            throw new Error(create_invalid_value_message($custom_http_header, "custom_http_header", "html-to-pdf", "A string containing the header name and value separated by a colon.", "set_custom_http_header"), 470);
        
        $this->fields['custom_http_header'] = $custom_http_header;
        return $this;
    }

    /**
    * Wait the specified number of milliseconds to finish all JavaScript after the document is loaded. The maximum value is determined by your API license.
    *
    * @param javascript_delay The number of milliseconds to wait. Must be a positive integer number or 0.
    * @return The converter object.
    */
    function setJavascriptDelay($javascript_delay) {
        if (!(intval($javascript_delay) >= 0))
            throw new Error(create_invalid_value_message($javascript_delay, "javascript_delay", "html-to-pdf", "Must be a positive integer number or 0.", "set_javascript_delay"), 470);
        
        $this->fields['javascript_delay'] = $javascript_delay;
        return $this;
    }

    /**
    * Convert only the specified element from the main document and its children. The element is specified by one or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a>. If the element is not found, the conversion fails. If multiple elements are found, the first one is used.
    *
    * @param selectors One or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a> separated by commas. The string must not be empty.
    * @return The converter object.
    */
    function setElementToConvert($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "selectors", "html-to-pdf", "The string must not be empty.", "set_element_to_convert"), 470);
        
        $this->fields['element_to_convert'] = $selectors;
        return $this;
    }

    /**
    * Specify the DOM handling when only a part of the document is converted.
    *
    * @param mode Allowed values are cut-out, remove-siblings, hide-siblings.
    * @return The converter object.
    */
    function setElementToConvertMode($mode) {
        if (!preg_match("/(?i)^(cut-out|remove-siblings|hide-siblings)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "mode", "html-to-pdf", "Allowed values are cut-out, remove-siblings, hide-siblings.", "set_element_to_convert_mode"), 470);
        
        $this->fields['element_to_convert_mode'] = $mode;
        return $this;
    }

    /**
    * Wait for the specified element in a source document. The element is specified by one or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a>. The element is searched for in the main document and all iframes. If the element is not found, the conversion fails. Your API license defines the maximum wait time by "Max Delay" parameter.
    *
    * @param selectors One or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a> separated by commas. The string must not be empty.
    * @return The converter object.
    */
    function setWaitForElement($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "selectors", "html-to-pdf", "The string must not be empty.", "set_wait_for_element"), 470);
        
        $this->fields['wait_for_element'] = $selectors;
        return $this;
    }

    /**
    * Set the viewport width in pixels. The viewport is the user's visible area of the page.
    *
    * @param viewport_width The value must be in the range 96-7680.
    * @return The converter object.
    */
    function setViewportWidth($viewport_width) {
        if (!(intval($viewport_width) >= 96 && intval($viewport_width) <= 7680))
            throw new Error(create_invalid_value_message($viewport_width, "viewport_width", "html-to-pdf", "The value must be in the range 96-7680.", "set_viewport_width"), 470);
        
        $this->fields['viewport_width'] = $viewport_width;
        return $this;
    }

    /**
    * Set the viewport height in pixels. The viewport is the user's visible area of the page.
    *
    * @param viewport_height Must be a positive integer number.
    * @return The converter object.
    */
    function setViewportHeight($viewport_height) {
        if (!(intval($viewport_height) > 0))
            throw new Error(create_invalid_value_message($viewport_height, "viewport_height", "html-to-pdf", "Must be a positive integer number.", "set_viewport_height"), 470);
        
        $this->fields['viewport_height'] = $viewport_height;
        return $this;
    }

    /**
    * Set the viewport size. The viewport is the user's visible area of the page.
    *
    * @param width Set the viewport width in pixels. The viewport is the user's visible area of the page. The value must be in the range 96-7680.
    * @param height Set the viewport height in pixels. The viewport is the user's visible area of the page. Must be a positive integer number.
    * @return The converter object.
    */
    function setViewport($width, $height) {
        $this->setViewportWidth($width);
        $this->setViewportHeight($height);
        return $this;
    }

    /**
    * Set the rendering mode.
    *
    * @param rendering_mode The rendering mode. Allowed values are default, viewport.
    * @return The converter object.
    */
    function setRenderingMode($rendering_mode) {
        if (!preg_match("/(?i)^(default|viewport)$/", $rendering_mode))
            throw new Error(create_invalid_value_message($rendering_mode, "rendering_mode", "html-to-pdf", "Allowed values are default, viewport.", "set_rendering_mode"), 470);
        
        $this->fields['rendering_mode'] = $rendering_mode;
        return $this;
    }

    /**
    * Set the scaling factor (zoom) for the main page area.
    *
    * @param scale_factor The percentage value. The value must be in the range 10-500.
    * @return The converter object.
    */
    function setScaleFactor($scale_factor) {
        if (!(intval($scale_factor) >= 10 && intval($scale_factor) <= 500))
            throw new Error(create_invalid_value_message($scale_factor, "scale_factor", "html-to-pdf", "The value must be in the range 10-500.", "set_scale_factor"), 470);
        
        $this->fields['scale_factor'] = $scale_factor;
        return $this;
    }

    /**
    * Set the scaling factor (zoom) for the header and footer.
    *
    * @param header_footer_scale_factor The percentage value. The value must be in the range 10-500.
    * @return The converter object.
    */
    function setHeaderFooterScaleFactor($header_footer_scale_factor) {
        if (!(intval($header_footer_scale_factor) >= 10 && intval($header_footer_scale_factor) <= 500))
            throw new Error(create_invalid_value_message($header_footer_scale_factor, "header_footer_scale_factor", "html-to-pdf", "The value must be in the range 10-500.", "set_header_footer_scale_factor"), 470);
        
        $this->fields['header_footer_scale_factor'] = $header_footer_scale_factor;
        return $this;
    }

    /**
    * Disable the intelligent shrinking strategy that tries to optimally fit the HTML contents to a PDF page.
    *
    * @param disable_smart_shrinking Set to <span class='field-value'>true</span> to disable the intelligent shrinking strategy.
    * @return The converter object.
    */
    function setDisableSmartShrinking($disable_smart_shrinking) {
        $this->fields['disable_smart_shrinking'] = $disable_smart_shrinking;
        return $this;
    }

    /**
    * Set the quality of embedded JPEG images. Lower quality results in smaller PDF file. Lower quality affects printing or zooming in a PDF viewer.
    *
    * @param jpeg_quality The percentage value. The value must be in the range 1-100.
    * @return The converter object.
    */
    function setJpegQuality($jpeg_quality) {
        if (!(intval($jpeg_quality) >= 1 && intval($jpeg_quality) <= 100))
            throw new Error(create_invalid_value_message($jpeg_quality, "jpeg_quality", "html-to-pdf", "The value must be in the range 1-100.", "set_jpeg_quality"), 470);
        
        $this->fields['jpeg_quality'] = $jpeg_quality;
        return $this;
    }

    /**
    * Set image categories to be converted into embedded JPEG images. The conversion into JPEG may result in smaller PDF file.
    *
    * @param convert_images_to_jpeg The image category. Allowed values are none, opaque, all.
    * @return The converter object.
    */
    function setConvertImagesToJpeg($convert_images_to_jpeg) {
        if (!preg_match("/(?i)^(none|opaque|all)$/", $convert_images_to_jpeg))
            throw new Error(create_invalid_value_message($convert_images_to_jpeg, "convert_images_to_jpeg", "html-to-pdf", "Allowed values are none, opaque, all.", "set_convert_images_to_jpeg"), 470);
        
        $this->fields['convert_images_to_jpeg'] = $convert_images_to_jpeg;
        return $this;
    }

    /**
    * Set the DPI when embedded image is scaled down. Lower DPI may result in smaller PDF file. Lower DPI affects printing or zooming in a PDF viewer. Use <span class='field-value'>0</span> for no scaling down.
    *
    * @param image_dpi The DPI value. Must be a positive integer number or 0.
    * @return The converter object.
    */
    function setImageDpi($image_dpi) {
        if (!(intval($image_dpi) >= 0))
            throw new Error(create_invalid_value_message($image_dpi, "image_dpi", "html-to-pdf", "Must be a positive integer number or 0.", "set_image_dpi"), 470);
        
        $this->fields['image_dpi'] = $image_dpi;
        return $this;
    }

    /**
    * Create linearized PDF. This is also known as Fast Web View.
    *
    * @param linearize Set to <span class='field-value'>true</span> to create linearized PDF.
    * @return The converter object.
    */
    function setLinearize($linearize) {
        $this->fields['linearize'] = $linearize;
        return $this;
    }

    /**
    * Encrypt the PDF. This prevents search engines from indexing the contents.
    *
    * @param encrypt Set to <span class='field-value'>true</span> to enable PDF encryption.
    * @return The converter object.
    */
    function setEncrypt($encrypt) {
        $this->fields['encrypt'] = $encrypt;
        return $this;
    }

    /**
    * Protect the PDF with a user password. When a PDF has a user password, it must be supplied in order to view the document and to perform operations allowed by the access permissions.
    *
    * @param user_password The user password.
    * @return The converter object.
    */
    function setUserPassword($user_password) {
        $this->fields['user_password'] = $user_password;
        return $this;
    }

    /**
    * Protect the PDF with an owner password.  Supplying an owner password grants unlimited access to the PDF including changing the passwords and access permissions.
    *
    * @param owner_password The owner password.
    * @return The converter object.
    */
    function setOwnerPassword($owner_password) {
        $this->fields['owner_password'] = $owner_password;
        return $this;
    }

    /**
    * Disallow printing of the output PDF.
    *
    * @param no_print Set to <span class='field-value'>true</span> to set the no-print flag in the output PDF.
    * @return The converter object.
    */
    function setNoPrint($no_print) {
        $this->fields['no_print'] = $no_print;
        return $this;
    }

    /**
    * Disallow modification of the ouput PDF.
    *
    * @param no_modify Set to <span class='field-value'>true</span> to set the read-only only flag in the output PDF.
    * @return The converter object.
    */
    function setNoModify($no_modify) {
        $this->fields['no_modify'] = $no_modify;
        return $this;
    }

    /**
    * Disallow text and graphics extraction from the output PDF.
    *
    * @param no_copy Set to <span class='field-value'>true</span> to set the no-copy flag in the output PDF.
    * @return The converter object.
    */
    function setNoCopy($no_copy) {
        $this->fields['no_copy'] = $no_copy;
        return $this;
    }

    /**
    * Set the title of the PDF.
    *
    * @param title The title.
    * @return The converter object.
    */
    function setTitle($title) {
        $this->fields['title'] = $title;
        return $this;
    }

    /**
    * Set the subject of the PDF.
    *
    * @param subject The subject.
    * @return The converter object.
    */
    function setSubject($subject) {
        $this->fields['subject'] = $subject;
        return $this;
    }

    /**
    * Set the author of the PDF.
    *
    * @param author The author.
    * @return The converter object.
    */
    function setAuthor($author) {
        $this->fields['author'] = $author;
        return $this;
    }

    /**
    * Associate keywords with the document.
    *
    * @param keywords The string with the keywords.
    * @return The converter object.
    */
    function setKeywords($keywords) {
        $this->fields['keywords'] = $keywords;
        return $this;
    }

    /**
    * Specify the page layout to be used when the document is opened.
    *
    * @param page_layout Allowed values are single-page, one-column, two-column-left, two-column-right.
    * @return The converter object.
    */
    function setPageLayout($page_layout) {
        if (!preg_match("/(?i)^(single-page|one-column|two-column-left|two-column-right)$/", $page_layout))
            throw new Error(create_invalid_value_message($page_layout, "page_layout", "html-to-pdf", "Allowed values are single-page, one-column, two-column-left, two-column-right.", "set_page_layout"), 470);
        
        $this->fields['page_layout'] = $page_layout;
        return $this;
    }

    /**
    * Specify how the document should be displayed when opened.
    *
    * @param page_mode Allowed values are full-screen, thumbnails, outlines.
    * @return The converter object.
    */
    function setPageMode($page_mode) {
        if (!preg_match("/(?i)^(full-screen|thumbnails|outlines)$/", $page_mode))
            throw new Error(create_invalid_value_message($page_mode, "page_mode", "html-to-pdf", "Allowed values are full-screen, thumbnails, outlines.", "set_page_mode"), 470);
        
        $this->fields['page_mode'] = $page_mode;
        return $this;
    }

    /**
    * Specify how the page should be displayed when opened.
    *
    * @param initial_zoom_type Allowed values are fit-width, fit-height, fit-page.
    * @return The converter object.
    */
    function setInitialZoomType($initial_zoom_type) {
        if (!preg_match("/(?i)^(fit-width|fit-height|fit-page)$/", $initial_zoom_type))
            throw new Error(create_invalid_value_message($initial_zoom_type, "initial_zoom_type", "html-to-pdf", "Allowed values are fit-width, fit-height, fit-page.", "set_initial_zoom_type"), 470);
        
        $this->fields['initial_zoom_type'] = $initial_zoom_type;
        return $this;
    }

    /**
    * Display the specified page when the document is opened.
    *
    * @param initial_page Must be a positive integer number.
    * @return The converter object.
    */
    function setInitialPage($initial_page) {
        if (!(intval($initial_page) > 0))
            throw new Error(create_invalid_value_message($initial_page, "initial_page", "html-to-pdf", "Must be a positive integer number.", "set_initial_page"), 470);
        
        $this->fields['initial_page'] = $initial_page;
        return $this;
    }

    /**
    * Specify the initial page zoom in percents when the document is opened.
    *
    * @param initial_zoom Must be a positive integer number.
    * @return The converter object.
    */
    function setInitialZoom($initial_zoom) {
        if (!(intval($initial_zoom) > 0))
            throw new Error(create_invalid_value_message($initial_zoom, "initial_zoom", "html-to-pdf", "Must be a positive integer number.", "set_initial_zoom"), 470);
        
        $this->fields['initial_zoom'] = $initial_zoom;
        return $this;
    }

    /**
    * Specify whether to hide the viewer application's tool bars when the document is active.
    *
    * @param hide_toolbar Set to <span class='field-value'>true</span> to hide tool bars.
    * @return The converter object.
    */
    function setHideToolbar($hide_toolbar) {
        $this->fields['hide_toolbar'] = $hide_toolbar;
        return $this;
    }

    /**
    * Specify whether to hide the viewer application's menu bar when the document is active.
    *
    * @param hide_menubar Set to <span class='field-value'>true</span> to hide the menu bar.
    * @return The converter object.
    */
    function setHideMenubar($hide_menubar) {
        $this->fields['hide_menubar'] = $hide_menubar;
        return $this;
    }

    /**
    * Specify whether to hide user interface elements in the document's window (such as scroll bars and navigation controls), leaving only the document's contents displayed.
    *
    * @param hide_window_ui Set to <span class='field-value'>true</span> to hide ui elements.
    * @return The converter object.
    */
    function setHideWindowUi($hide_window_ui) {
        $this->fields['hide_window_ui'] = $hide_window_ui;
        return $this;
    }

    /**
    * Specify whether to resize the document's window to fit the size of the first displayed page.
    *
    * @param fit_window Set to <span class='field-value'>true</span> to resize the window.
    * @return The converter object.
    */
    function setFitWindow($fit_window) {
        $this->fields['fit_window'] = $fit_window;
        return $this;
    }

    /**
    * Specify whether to position the document's window in the center of the screen.
    *
    * @param center_window Set to <span class='field-value'>true</span> to center the window.
    * @return The converter object.
    */
    function setCenterWindow($center_window) {
        $this->fields['center_window'] = $center_window;
        return $this;
    }

    /**
    * Specify whether the window's title bar should display the document title. If false , the title bar should instead display the name of the PDF file containing the document.
    *
    * @param display_title Set to <span class='field-value'>true</span> to display the title.
    * @return The converter object.
    */
    function setDisplayTitle($display_title) {
        $this->fields['display_title'] = $display_title;
        return $this;
    }

    /**
    * Set the predominant reading order for text to right-to-left. This option has no direct effect on the document's contents or page numbering but can be used to determine the relative positioning of pages when displayed side by side or printed n-up
    *
    * @param right_to_left Set to <span class='field-value'>true</span> to set right-to-left reading order.
    * @return The converter object.
    */
    function setRightToLeft($right_to_left) {
        $this->fields['right_to_left'] = $right_to_left;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param debug_log Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($debug_log) {
        $this->fields['debug_log'] = $debug_log;
        return $this;
    }

    /**
    * Get the URL of the debug log for the last conversion.
    * @return The link to the debug log.
    */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
    * Get the number of conversion credits available in your <a href='/user/account/'>account</a>.
    * The number is available after calling the conversion. So use the method after convertXYZ method.
    * The returned value can differ from the actual count if you run parallel conversions.
    * The special value <span class='field-value'>999999</span> is returned if the information is not available.
    * @return The number of credits.
    */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
    * Get the number of credits consumed by the last conversion.
    * @return The number of credits.
    */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
    * Get the job id.
    * @return The unique job identifier.
    */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
    * Get the total number of pages in the output document.
    * @return The page count.
    */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
    * Get the size of the output in bytes.
    * @return The count of bytes.
    */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
    * Tag the conversion with a custom value. The tag is used in <a href='/user/account/log/conversion/'>conversion statistics</a>. A value longer than 32 characters is cut off.
    *
    * @param tag A string with the custom tag.
    * @return The converter object.
    */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTP scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param http_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($http_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $http_proxy))
            throw new Error(create_invalid_value_message($http_proxy, "http_proxy", "html-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $http_proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param https_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($https_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $https_proxy))
            throw new Error(create_invalid_value_message($https_proxy, "https_proxy", "html-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $https_proxy;
        return $this;
    }

    /**
    * A client certificate to authenticate Pdfcrowd converter on your web server. The certificate is used for two-way SSL/TLS authentication and adds extra security.
    *
    * @param client_certificate The file must be in PKCS12 format. The file must exist and not be empty.
    * @return The converter object.
    */
    function setClientCertificate($client_certificate) {
        if (!(filesize($client_certificate) > 0))
            throw new Error(create_invalid_value_message($client_certificate, "client_certificate", "html-to-pdf", "The file must exist and not be empty.", "set_client_certificate"), 470);
        
        $this->files['client_certificate'] = $client_certificate;
        return $this;
    }

    /**
    * A password for PKCS12 file with a client certificate if it's needed.
    *
    * @param client_certificate_password
    * @return The converter object.
    */
    function setClientCertificatePassword($client_certificate_password) {
        $this->fields['client_certificate_password'] = $client_certificate_password;
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    *
    * @param use_http Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($use_http) {
        $this->helper->setUseHttp($use_http);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be usefull if you are behind some proxy or firewall.
    *
    * @param user_agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($user_agent) {
        $this->helper->setUserAgent($user_agent);
        return $this;
    }

    /**
    * Specifies an HTTP proxy that the API client library will use to connect to the internet.
    *
    * @param host The proxy hostname.
    * @param port The proxy port.
    * @param user_name The username.
    * @param password The password.
    * @return The converter object.
    */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param retry_count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($retry_count) {
        $this->helper->setRetryCount($retry_count);
        return $this;
    }

    function setUseCurl($use_curl) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }
}

/**
* Conversion from HTML to image.
*/
class HtmlToImageClient {
    private $helper;
    private $fields;

    /**
    * Constructor for the Pdfcrowd API client.
    *
    * @param user_name Your username at Pdfcrowd.
    * @param api_key Your API key.
    */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'html', 'output_format'=>'png');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
    * The format of the output file.
    *
    * @param output_format Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.
    * @return The converter object.
    */
    function setOutputFormat($output_format) {
        if (!preg_match("/(?i)^(png|jpg|gif|tiff|bmp|ico|ppm|pgm|pbm|pnm|psb|pct|ras|tga|sgi|sun|webp)$/", $output_format))
            throw new Error(create_invalid_value_message($output_format, "output_format", "html-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
        $this->fields['output_format'] = $output_format;
        return $this;
    }

    /**
    * Convert a web page.
    *
    * @param url The address of the web page to convert. The supported protocols are http:// and https://.
    * @return Byte array containing the conversion output.
    */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "html-to-image", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a web page and write the result to an output stream.
    *
    * @param url The address of the web page to convert. The supported protocols are http:// and https://.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "html-to-image", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a web page and write the result to a local file.
    *
    * @param url The address of the web page to convert. The supported protocols are http:// and https://.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "html-to-image", "The string must not be empty.", "convert_url_to_file"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-image", "The file name must have a valid extension.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a local file and write the result to an output stream.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "html-to-image", "The file name must have a valid extension.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a local file and write the result to a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "html-to-image", "The string must not be empty.", "convert_file_to_file"), 470);
        
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
    * Convert a string.
    *
    * @param text The string content to convert. The string must not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertString($text) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "text", "html-to-image", "The string must not be empty.", "convert_string"), 470);
        
        $this->fields['text'] = $text;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a string and write the output to an output stream.
    *
    * @param text The string content to convert. The string must not be empty.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertStringToStream($text, $out_stream) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "text", "html-to-image", "The string must not be empty.", "convert_string_to_stream"), 470);
        
        $this->fields['text'] = $text;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a string and write the output to a file.
    *
    * @param text The string content to convert. The string must not be empty.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertStringToFile($text, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "html-to-image", "The string must not be empty.", "convert_string_to_file"), 470);
        
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
    * Do not print the background graphics.
    *
    * @param no_background Set to <span class='field-value'>true</span> to disable the background graphics.
    * @return The converter object.
    */
    function setNoBackground($no_background) {
        $this->fields['no_background'] = $no_background;
        return $this;
    }

    /**
    * Do not execute JavaScript.
    *
    * @param disable_javascript Set to <span class='field-value'>true</span> to disable JavaScript in web pages.
    * @return The converter object.
    */
    function setDisableJavascript($disable_javascript) {
        $this->fields['disable_javascript'] = $disable_javascript;
        return $this;
    }

    /**
    * Do not load images.
    *
    * @param disable_image_loading Set to <span class='field-value'>true</span> to disable loading of images.
    * @return The converter object.
    */
    function setDisableImageLoading($disable_image_loading) {
        $this->fields['disable_image_loading'] = $disable_image_loading;
        return $this;
    }

    /**
    * Disable loading fonts from remote sources.
    *
    * @param disable_remote_fonts Set to <span class='field-value'>true</span> disable loading remote fonts.
    * @return The converter object.
    */
    function setDisableRemoteFonts($disable_remote_fonts) {
        $this->fields['disable_remote_fonts'] = $disable_remote_fonts;
        return $this;
    }

    /**
    * Try to block ads. Enabling this option can produce smaller output and speed up the conversion.
    *
    * @param block_ads Set to <span class='field-value'>true</span> to block ads in web pages.
    * @return The converter object.
    */
    function setBlockAds($block_ads) {
        $this->fields['block_ads'] = $block_ads;
        return $this;
    }

    /**
    * Set the default HTML content text encoding.
    *
    * @param default_encoding The text encoding of the HTML content.
    * @return The converter object.
    */
    function setDefaultEncoding($default_encoding) {
        $this->fields['default_encoding'] = $default_encoding;
        return $this;
    }

    /**
    * Set the HTTP authentication user name.
    *
    * @param user_name The user name.
    * @return The converter object.
    */
    function setHttpAuthUserName($user_name) {
        $this->fields['http_auth_user_name'] = $user_name;
        return $this;
    }

    /**
    * Set the HTTP authentication password.
    *
    * @param password The password.
    * @return The converter object.
    */
    function setHttpAuthPassword($password) {
        $this->fields['http_auth_password'] = $password;
        return $this;
    }

    /**
    * Set credentials to access HTTP base authentication protected websites.
    *
    * @param user_name Set the HTTP authentication user name.
    * @param password Set the HTTP authentication password.
    * @return The converter object.
    */
    function setHttpAuth($user_name, $password) {
        $this->setHttpAuthUserName($user_name);
        $this->setHttpAuthPassword($password);
        return $this;
    }

    /**
    * Use the print version of the page if available (@media print).
    *
    * @param use_print_media Set to <span class='field-value'>true</span> to use the print version of the page.
    * @return The converter object.
    */
    function setUsePrintMedia($use_print_media) {
        $this->fields['use_print_media'] = $use_print_media;
        return $this;
    }

    /**
    * Do not send the X-Pdfcrowd HTTP header in Pdfcrowd HTTP requests.
    *
    * @param no_xpdfcrowd_header Set to <span class='field-value'>true</span> to disable sending X-Pdfcrowd HTTP header.
    * @return The converter object.
    */
    function setNoXpdfcrowdHeader($no_xpdfcrowd_header) {
        $this->fields['no_xpdfcrowd_header'] = $no_xpdfcrowd_header;
        return $this;
    }

    /**
    * Set cookies that are sent in Pdfcrowd HTTP requests.
    *
    * @param cookies The cookie string.
    * @return The converter object.
    */
    function setCookies($cookies) {
        $this->fields['cookies'] = $cookies;
        return $this;
    }

    /**
    * Do not allow insecure HTTPS connections.
    *
    * @param verify_ssl_certificates Set to <span class='field-value'>true</span> to enable SSL certificate verification.
    * @return The converter object.
    */
    function setVerifySslCertificates($verify_ssl_certificates) {
        $this->fields['verify_ssl_certificates'] = $verify_ssl_certificates;
        return $this;
    }

    /**
    * Abort the conversion if the main URL HTTP status code is greater than or equal to 400.
    *
    * @param fail_on_error Set to <span class='field-value'>true</span> to abort the conversion.
    * @return The converter object.
    */
    function setFailOnMainUrlError($fail_on_error) {
        $this->fields['fail_on_main_url_error'] = $fail_on_error;
        return $this;
    }

    /**
    * Abort the conversion if any of the sub-request HTTP status code is greater than or equal to 400 or if some sub-requests are still pending. See details in a debug log.
    *
    * @param fail_on_error Set to <span class='field-value'>true</span> to abort the conversion.
    * @return The converter object.
    */
    function setFailOnAnyUrlError($fail_on_error) {
        $this->fields['fail_on_any_url_error'] = $fail_on_error;
        return $this;
    }

    /**
    * Run a custom JavaScript after the document is loaded. The script is intended for post-load DOM manipulation (add/remove elements, update CSS, ...).
    *
    * @param custom_javascript String containing a JavaScript code. The string must not be empty.
    * @return The converter object.
    */
    function setCustomJavascript($custom_javascript) {
        if (!($custom_javascript != null && $custom_javascript !== ''))
            throw new Error(create_invalid_value_message($custom_javascript, "custom_javascript", "html-to-image", "The string must not be empty.", "set_custom_javascript"), 470);
        
        $this->fields['custom_javascript'] = $custom_javascript;
        return $this;
    }

    /**
    * Set a custom HTTP header that is sent in Pdfcrowd HTTP requests.
    *
    * @param custom_http_header A string containing the header name and value separated by a colon.
    * @return The converter object.
    */
    function setCustomHttpHeader($custom_http_header) {
        if (!preg_match("/^.+:.+$/", $custom_http_header))
            throw new Error(create_invalid_value_message($custom_http_header, "custom_http_header", "html-to-image", "A string containing the header name and value separated by a colon.", "set_custom_http_header"), 470);
        
        $this->fields['custom_http_header'] = $custom_http_header;
        return $this;
    }

    /**
    * Wait the specified number of milliseconds to finish all JavaScript after the document is loaded. The maximum value is determined by your API license.
    *
    * @param javascript_delay The number of milliseconds to wait. Must be a positive integer number or 0.
    * @return The converter object.
    */
    function setJavascriptDelay($javascript_delay) {
        if (!(intval($javascript_delay) >= 0))
            throw new Error(create_invalid_value_message($javascript_delay, "javascript_delay", "html-to-image", "Must be a positive integer number or 0.", "set_javascript_delay"), 470);
        
        $this->fields['javascript_delay'] = $javascript_delay;
        return $this;
    }

    /**
    * Convert only the specified element from the main document and its children. The element is specified by one or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a>. If the element is not found, the conversion fails. If multiple elements are found, the first one is used.
    *
    * @param selectors One or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a> separated by commas. The string must not be empty.
    * @return The converter object.
    */
    function setElementToConvert($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "selectors", "html-to-image", "The string must not be empty.", "set_element_to_convert"), 470);
        
        $this->fields['element_to_convert'] = $selectors;
        return $this;
    }

    /**
    * Specify the DOM handling when only a part of the document is converted.
    *
    * @param mode Allowed values are cut-out, remove-siblings, hide-siblings.
    * @return The converter object.
    */
    function setElementToConvertMode($mode) {
        if (!preg_match("/(?i)^(cut-out|remove-siblings|hide-siblings)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "mode", "html-to-image", "Allowed values are cut-out, remove-siblings, hide-siblings.", "set_element_to_convert_mode"), 470);
        
        $this->fields['element_to_convert_mode'] = $mode;
        return $this;
    }

    /**
    * Wait for the specified element in a source document. The element is specified by one or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a>. The element is searched for in the main document and all iframes. If the element is not found, the conversion fails. Your API license defines the maximum wait time by "Max Delay" parameter.
    *
    * @param selectors One or more <a href='https://developer.mozilla.org/en-US/docs/Learn/CSS/Introduction_to_CSS/Selectors'>CSS selectors</a> separated by commas. The string must not be empty.
    * @return The converter object.
    */
    function setWaitForElement($selectors) {
        if (!($selectors != null && $selectors !== ''))
            throw new Error(create_invalid_value_message($selectors, "selectors", "html-to-image", "The string must not be empty.", "set_wait_for_element"), 470);
        
        $this->fields['wait_for_element'] = $selectors;
        return $this;
    }

    /**
    * Set the output image width in pixels.
    *
    * @param screenshot_width The value must be in the range 96-7680.
    * @return The converter object.
    */
    function setScreenshotWidth($screenshot_width) {
        if (!(intval($screenshot_width) >= 96 && intval($screenshot_width) <= 7680))
            throw new Error(create_invalid_value_message($screenshot_width, "screenshot_width", "html-to-image", "The value must be in the range 96-7680.", "set_screenshot_width"), 470);
        
        $this->fields['screenshot_width'] = $screenshot_width;
        return $this;
    }

    /**
    * Set the output image height in pixels. If it's not specified, actual document height is used.
    *
    * @param screenshot_height Must be a positive integer number.
    * @return The converter object.
    */
    function setScreenshotHeight($screenshot_height) {
        if (!(intval($screenshot_height) > 0))
            throw new Error(create_invalid_value_message($screenshot_height, "screenshot_height", "html-to-image", "Must be a positive integer number.", "set_screenshot_height"), 470);
        
        $this->fields['screenshot_height'] = $screenshot_height;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param debug_log Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($debug_log) {
        $this->fields['debug_log'] = $debug_log;
        return $this;
    }

    /**
    * Get the URL of the debug log for the last conversion.
    * @return The link to the debug log.
    */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
    * Get the number of conversion credits available in your <a href='/user/account/'>account</a>.
    * The number is available after calling the conversion. So use the method after convertXYZ method.
    * The returned value can differ from the actual count if you run parallel conversions.
    * The special value <span class='field-value'>999999</span> is returned if the information is not available.
    * @return The number of credits.
    */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
    * Get the number of credits consumed by the last conversion.
    * @return The number of credits.
    */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
    * Get the job id.
    * @return The unique job identifier.
    */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
    * Get the size of the output in bytes.
    * @return The count of bytes.
    */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
    * Tag the conversion with a custom value. The tag is used in <a href='/user/account/log/conversion/'>conversion statistics</a>. A value longer than 32 characters is cut off.
    *
    * @param tag A string with the custom tag.
    * @return The converter object.
    */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTP scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param http_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($http_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $http_proxy))
            throw new Error(create_invalid_value_message($http_proxy, "http_proxy", "html-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $http_proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param https_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($https_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $https_proxy))
            throw new Error(create_invalid_value_message($https_proxy, "https_proxy", "html-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $https_proxy;
        return $this;
    }

    /**
    * A client certificate to authenticate Pdfcrowd converter on your web server. The certificate is used for two-way SSL/TLS authentication and adds extra security.
    *
    * @param client_certificate The file must be in PKCS12 format. The file must exist and not be empty.
    * @return The converter object.
    */
    function setClientCertificate($client_certificate) {
        if (!(filesize($client_certificate) > 0))
            throw new Error(create_invalid_value_message($client_certificate, "client_certificate", "html-to-image", "The file must exist and not be empty.", "set_client_certificate"), 470);
        
        $this->files['client_certificate'] = $client_certificate;
        return $this;
    }

    /**
    * A password for PKCS12 file with a client certificate if it's needed.
    *
    * @param client_certificate_password
    * @return The converter object.
    */
    function setClientCertificatePassword($client_certificate_password) {
        $this->fields['client_certificate_password'] = $client_certificate_password;
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    *
    * @param use_http Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($use_http) {
        $this->helper->setUseHttp($use_http);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be usefull if you are behind some proxy or firewall.
    *
    * @param user_agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($user_agent) {
        $this->helper->setUserAgent($user_agent);
        return $this;
    }

    /**
    * Specifies an HTTP proxy that the API client library will use to connect to the internet.
    *
    * @param host The proxy hostname.
    * @param port The proxy port.
    * @param user_name The username.
    * @param password The password.
    * @return The converter object.
    */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param retry_count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($retry_count) {
        $this->helper->setRetryCount($retry_count);
        return $this;
    }

    function setUseCurl($use_curl) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }
}

/**
* Conversion from one image format to another image format.
*/
class ImageToImageClient {
    private $helper;
    private $fields;

    /**
    * Constructor for the Pdfcrowd API client.
    *
    * @param user_name Your username at Pdfcrowd.
    * @param api_key Your API key.
    */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'image', 'output_format'=>'png');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
    * Convert an image.
    *
    * @param url The address of the image to convert. The supported protocols are http:// and https://.
    * @return Byte array containing the conversion output.
    */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "image-to-image", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert an image and write the result to an output stream.
    *
    * @param url The address of the image to convert. The supported protocols are http:// and https://.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "image-to-image", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert an image and write the result to a local file.
    *
    * @param url The address of the image to convert. The supported protocols are http:// and https://.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "image-to-image", "The string must not be empty.", "convert_url_to_file"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip). The file must exist and not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "image-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a local file and write the result to an output stream.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip). The file must exist and not be empty.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "image-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a local file and write the result to a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip). The file must exist and not be empty.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "image-to-image", "The string must not be empty.", "convert_file_to_file"), 470);
        
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
    * Convert raw data.
    *
    * @param data The raw content to be converted.
    * @return Byte array with the output.
    */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert raw data and write the result to an output stream.
    *
    * @param data The raw content to be converted.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert raw data to a file.
    *
    * @param data The raw content to be converted.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "image-to-image", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
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
    * The format of the output file.
    *
    * @param output_format Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.
    * @return The converter object.
    */
    function setOutputFormat($output_format) {
        if (!preg_match("/(?i)^(png|jpg|gif|tiff|bmp|ico|ppm|pgm|pbm|pnm|psb|pct|ras|tga|sgi|sun|webp)$/", $output_format))
            throw new Error(create_invalid_value_message($output_format, "output_format", "image-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
        $this->fields['output_format'] = $output_format;
        return $this;
    }

    /**
    * Resize the image.
    *
    * @param resize The resize percentage or new image dimensions.
    * @return The converter object.
    */
    function setResize($resize) {
        $this->fields['resize'] = $resize;
        return $this;
    }

    /**
    * Rotate the image.
    *
    * @param rotate The rotation specified in degrees.
    * @return The converter object.
    */
    function setRotate($rotate) {
        $this->fields['rotate'] = $rotate;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param debug_log Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($debug_log) {
        $this->fields['debug_log'] = $debug_log;
        return $this;
    }

    /**
    * Get the URL of the debug log for the last conversion.
    * @return The link to the debug log.
    */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
    * Get the number of conversion credits available in your <a href='/user/account/'>account</a>.
    * The number is available after calling the conversion. So use the method after convertXYZ method.
    * The returned value can differ from the actual count if you run parallel conversions.
    * The special value <span class='field-value'>999999</span> is returned if the information is not available.
    * @return The number of credits.
    */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
    * Get the number of credits consumed by the last conversion.
    * @return The number of credits.
    */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
    * Get the job id.
    * @return The unique job identifier.
    */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
    * Get the size of the output in bytes.
    * @return The count of bytes.
    */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
    * Tag the conversion with a custom value. The tag is used in <a href='/user/account/log/conversion/'>conversion statistics</a>. A value longer than 32 characters is cut off.
    *
    * @param tag A string with the custom tag.
    * @return The converter object.
    */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTP scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param http_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($http_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $http_proxy))
            throw new Error(create_invalid_value_message($http_proxy, "http_proxy", "image-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $http_proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param https_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($https_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $https_proxy))
            throw new Error(create_invalid_value_message($https_proxy, "https_proxy", "image-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $https_proxy;
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    *
    * @param use_http Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($use_http) {
        $this->helper->setUseHttp($use_http);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be usefull if you are behind some proxy or firewall.
    *
    * @param user_agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($user_agent) {
        $this->helper->setUserAgent($user_agent);
        return $this;
    }

    /**
    * Specifies an HTTP proxy that the API client library will use to connect to the internet.
    *
    * @param host The proxy hostname.
    * @param port The proxy port.
    * @param user_name The username.
    * @param password The password.
    * @return The converter object.
    */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param retry_count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($retry_count) {
        $this->helper->setRetryCount($retry_count);
        return $this;
    }

    function setUseCurl($use_curl) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }
}

/**
* Conversion from PDF to PDF.
*/
class PdfToPdfClient {
    private $helper;
    private $fields;

    /**
    * Constructor for the Pdfcrowd API client.
    *
    * @param user_name Your username at Pdfcrowd.
    * @param api_key Your API key.
    */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'pdf', 'output_format'=>'pdf');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
    * Specifies the action to be performed on the input PDFs.
    *
    * @param action Allowed values are join, shuffle.
    * @return The converter object.
    */
    function setAction($action) {
        if (!preg_match("/(?i)^(join|shuffle)$/", $action))
            throw new Error(create_invalid_value_message($action, "action", "pdf-to-pdf", "Allowed values are join, shuffle.", "set_action"), 470);
        
        $this->fields['action'] = $action;
        return $this;
    }

    /**
    * Perform an action on the input files.
    * @return Byte array containing the output PDF.
    */
    function convert() {
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Perform an action on the input files and write the output PDF to an output stream.
    *
    * @param out_stream The output stream that will contain the output PDF.
    */
    function convertToStream($out_stream) {
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Perform an action on the input files and write the output PDF to a file.
    *
    * @param file_path The output file path. The string must not be empty.
    */
    function convertToFile($file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "pdf-to-pdf", "The string must not be empty.", "convert_to_file"), 470);
        
        $output_file = fopen($file_path, "wb");
        $this->convertToStream($output_file);
        fclose($output_file);
    }

    /**
    * Add a PDF file to the list of the input PDFs.
    *
    * @param file_path The file path to a local PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function addPdfFile($file_path) {
        if (!(filesize($file_path) > 0))
            throw new Error(create_invalid_value_message($file_path, "file_path", "pdf-to-pdf", "The file must exist and not be empty.", "add_pdf_file"), 470);
        
        $this->files['f_' . $this->file_id] = $file_path;
        $this->file_id++;
        return $this;
    }

    /**
    * Add in-memory raw PDF data to the list of the input PDFs.<br>Typical usage is for adding PDF created by another Pdfcrowd converter.<br><br> Example in PHP:<br> <b>$clientPdf2Pdf</b>-&gt;addPdfRawData(<b>$clientHtml2Pdf</b>-&gt;convertUrl('http://www.example.com'));
    *
    * @param pdf_raw_data The raw PDF data. The input data must be PDF content.
    * @return The converter object.
    */
    function addPdfRawData($pdf_raw_data) {
        if (!($pdf_raw_data != null && strlen($pdf_raw_data) > 300 && substr($pdf_raw_data, 0, 4) == '%PDF'))
            throw new Error(create_invalid_value_message("raw PDF data", "pdf_raw_data", "pdf-to-pdf", "The input data must be PDF content.", "add_pdf_raw_data"), 470);
        
        $this->raw_data['f_' . $this->file_id] = $pdf_raw_data;
        $this->file_id++;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param debug_log Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($debug_log) {
        $this->fields['debug_log'] = $debug_log;
        return $this;
    }

    /**
    * Get the URL of the debug log for the last conversion.
    * @return The link to the debug log.
    */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
    * Get the number of conversion credits available in your <a href='/user/account/'>account</a>.
    * The number is available after calling the conversion. So use the method after convertXYZ method.
    * The returned value can differ from the actual count if you run parallel conversions.
    * The special value <span class='field-value'>999999</span> is returned if the information is not available.
    * @return The number of credits.
    */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
    * Get the number of credits consumed by the last conversion.
    * @return The number of credits.
    */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
    * Get the job id.
    * @return The unique job identifier.
    */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
    * Get the total number of pages in the output document.
    * @return The page count.
    */
    function getPageCount() {
        return $this->helper->getPageCount();
    }

    /**
    * Get the size of the output in bytes.
    * @return The count of bytes.
    */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
    * Tag the conversion with a custom value. The tag is used in <a href='/user/account/log/conversion/'>conversion statistics</a>. A value longer than 32 characters is cut off.
    *
    * @param tag A string with the custom tag.
    * @return The converter object.
    */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    *
    * @param use_http Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($use_http) {
        $this->helper->setUseHttp($use_http);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be usefull if you are behind some proxy or firewall.
    *
    * @param user_agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($user_agent) {
        $this->helper->setUserAgent($user_agent);
        return $this;
    }

    /**
    * Specifies an HTTP proxy that the API client library will use to connect to the internet.
    *
    * @param host The proxy hostname.
    * @param port The proxy port.
    * @param user_name The username.
    * @param password The password.
    * @return The converter object.
    */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param retry_count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($retry_count) {
        $this->helper->setRetryCount($retry_count);
        return $this;
    }

    function setUseCurl($use_curl) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }
}

/**
* Conversion from an image to PDF.
*/
class ImageToPdfClient {
    private $helper;
    private $fields;

    /**
    * Constructor for the Pdfcrowd API client.
    *
    * @param user_name Your username at Pdfcrowd.
    * @param api_key Your API key.
    */
    function __construct($user_name, $api_key) {
        $this->helper = new ConnectionHelper($user_name, $api_key);
        $this->fields = array('input_format'=>'image', 'output_format'=>'pdf');
        $this->file_id = 1;
        $this->files = array();
        $this->raw_data = array();
    }

    /**
    * Convert an image.
    *
    * @param url The address of the image to convert. The supported protocols are http:// and https://.
    * @return Byte array containing the conversion output.
    */
    function convertUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "image-to-pdf", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
        $this->fields['url'] = $url;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert an image and write the result to an output stream.
    *
    * @param url The address of the image to convert. The supported protocols are http:// and https://.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertUrlToStream($url, $out_stream) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "url", "image-to-pdf", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
        $this->fields['url'] = $url;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert an image and write the result to a local file.
    *
    * @param url The address of the image to convert. The supported protocols are http:// and https://.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertUrlToFile($url, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "image-to-pdf", "The string must not be empty.", "convert_url_to_file"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip). The file must exist and not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "image-to-pdf", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a local file and write the result to an output stream.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip). The file must exist and not be empty.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "file", "image-to-pdf", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a local file and write the result to a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip). The file must exist and not be empty.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertFileToFile($file, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "image-to-pdf", "The string must not be empty.", "convert_file_to_file"), 470);
        
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
    * Convert raw data.
    *
    * @param data The raw content to be converted.
    * @return Byte array with the output.
    */
    function convertRawData($data) {
        $this->raw_data['file'] = $data;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert raw data and write the result to an output stream.
    *
    * @param data The raw content to be converted.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertRawDataToStream($data, $out_stream) {
        $this->raw_data['file'] = $data;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert raw data to a file.
    *
    * @param data The raw content to be converted.
    * @param file_path The output file path. The string must not be empty.
    */
    function convertRawDataToFile($data, $file_path) {
        if (!($file_path != null && $file_path !== ''))
            throw new Error(create_invalid_value_message($file_path, "file_path", "image-to-pdf", "The string must not be empty.", "convert_raw_data_to_file"), 470);
        
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
    * Resize the image.
    *
    * @param resize The resize percentage or new image dimensions.
    * @return The converter object.
    */
    function setResize($resize) {
        $this->fields['resize'] = $resize;
        return $this;
    }

    /**
    * Rotate the image.
    *
    * @param rotate The rotation specified in degrees.
    * @return The converter object.
    */
    function setRotate($rotate) {
        $this->fields['rotate'] = $rotate;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param debug_log Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($debug_log) {
        $this->fields['debug_log'] = $debug_log;
        return $this;
    }

    /**
    * Get the URL of the debug log for the last conversion.
    * @return The link to the debug log.
    */
    function getDebugLogUrl() {
        return $this->helper->getDebugLogUrl();
    }

    /**
    * Get the number of conversion credits available in your <a href='/user/account/'>account</a>.
    * The number is available after calling the conversion. So use the method after convertXYZ method.
    * The returned value can differ from the actual count if you run parallel conversions.
    * The special value <span class='field-value'>999999</span> is returned if the information is not available.
    * @return The number of credits.
    */
    function getRemainingCreditCount() {
        return $this->helper->getRemainingCreditCount();
    }

    /**
    * Get the number of credits consumed by the last conversion.
    * @return The number of credits.
    */
    function getConsumedCreditCount() {
        return $this->helper->getConsumedCreditCount();
    }

    /**
    * Get the job id.
    * @return The unique job identifier.
    */
    function getJobId() {
        return $this->helper->getJobId();
    }

    /**
    * Get the size of the output in bytes.
    * @return The count of bytes.
    */
    function getOutputSize() {
        return $this->helper->getOutputSize();
    }

    /**
    * Tag the conversion with a custom value. The tag is used in <a href='/user/account/log/conversion/'>conversion statistics</a>. A value longer than 32 characters is cut off.
    *
    * @param tag A string with the custom tag.
    * @return The converter object.
    */
    function setTag($tag) {
        $this->fields['tag'] = $tag;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTP scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param http_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($http_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $http_proxy))
            throw new Error(create_invalid_value_message($http_proxy, "http_proxy", "image-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $http_proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param https_proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($https_proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $https_proxy))
            throw new Error(create_invalid_value_message($https_proxy, "https_proxy", "image-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $https_proxy;
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    *
    * @param use_http Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($use_http) {
        $this->helper->setUseHttp($use_http);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be usefull if you are behind some proxy or firewall.
    *
    * @param user_agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($user_agent) {
        $this->helper->setUserAgent($user_agent);
        return $this;
    }

    /**
    * Specifies an HTTP proxy that the API client library will use to connect to the internet.
    *
    * @param host The proxy hostname.
    * @param port The proxy port.
    * @param user_name The username.
    * @param password The password.
    * @return The converter object.
    */
    function setProxy($host, $port, $user_name, $password) {
        $this->helper->setProxy($host, $port, $user_name, $password);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param retry_count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($retry_count) {
        $this->helper->setRetryCount($retry_count);
        return $this;
    }

    function setUseCurl($use_curl) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }
}


}

?>
