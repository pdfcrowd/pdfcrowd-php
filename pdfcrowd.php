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

    public static $client_version = "5.1.1";
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

function create_invalid_value_message($value, $field, $converter, $hint, $id) {
    $message = "Invalid value '$value' for $field.";
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
        $this->host = getenv('PDFCROWD_HOST') ?: 'api.pdfcrowd.com';
        $this->user_name = $user_name;
        $this->api_key = $api_key;

        $this->reset_response_data();
        $this->setProxy(null, null, null, null);
        $this->setUseHttp(false);
        $this->setUserAgent('pdfcrowd_php_client/5.1.1 (https://pdfcrowd.com)');

        $this->retry_count = 1;
        $this->converter_version = '20.10';

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

    const CLIENT_VERSION = '5.1.1';
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
                if(strpos($this->error_message, "allow_url_fopen") !== false) {
                    throw new Error($this->error_message .
                                    self::$REQ_NOT_AVAILABLE);
                }
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
            throw new Error(create_invalid_value_message($url, "convertUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
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
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "html-to-pdf", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "html-to-pdf", "The file must exist and not be empty.", "convert_file"), 470);
        
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
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "html-to-pdf", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
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
    * Convert a string.
    *
    * @param text The string content to convert. The string must not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertString($text) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "convertString", "html-to-pdf", "The string must not be empty.", "convert_string"), 470);
        
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
            throw new Error(create_invalid_value_message($text, "convertStringToStream::text", "html-to-pdf", "The string must not be empty.", "convert_string_to_stream"), 470);
        
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
    * Convert the contents of an input stream.
    *
    * @param in_stream The input stream with source data.<br> The stream can contain either HTML code or an archive (.zip, .tar.gz, .tar.bz2).<br>The archive can contain HTML code and its external assets (images, style sheets, javascript).
    * @return Byte array containing the conversion output.
    */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert the contents of an input stream and write the result to an output stream.
    *
    * @param in_stream The input stream with source data.<br> The stream can contain either HTML code or an archive (.zip, .tar.gz, .tar.bz2).<br>The archive can contain HTML code and its external assets (images, style sheets, javascript).
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert the contents of an input stream and write the result to a local file.
    *
    * @param in_stream The input stream with source data.<br> The stream can contain either HTML code or an archive (.zip, .tar.gz, .tar.bz2).<br>The archive can contain HTML code and its external assets (images, style sheets, javascript).
    * @param file_path The output file path. The string must not be empty.
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
    * Set the output page size.
    *
    * @param size Allowed values are A0, A1, A2, A3, A4, A5, A6, Letter.
    * @return The converter object.
    */
    function setPageSize($size) {
        if (!preg_match("/(?i)^(A0|A1|A2|A3|A4|A5|A6|Letter)$/", $size))
            throw new Error(create_invalid_value_message($size, "setPageSize", "html-to-pdf", "Allowed values are A0, A1, A2, A3, A4, A5, A6, Letter.", "set_page_size"), 470);
        
        $this->fields['page_size'] = $size;
        return $this;
    }

    /**
    * Set the output page width. The safe maximum is <span class='field-value'>200in</span> otherwise some PDF viewers may be unable to open the PDF.
    *
    * @param width Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setPageWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setPageWidth", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_page_width"), 470);
        
        $this->fields['page_width'] = $width;
        return $this;
    }

    /**
    * Set the output page height. Use <span class='field-value'>-1</span> for a single page PDF. The safe maximum is <span class='field-value'>200in</span> otherwise some PDF viewers may be unable to open the PDF.
    *
    * @param height Can be -1 or specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setPageHeight($height) {
        if (!preg_match("/(?i)^0$|^\-1$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setPageHeight", "html-to-pdf", "Can be -1 or specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_page_height"), 470);
        
        $this->fields['page_height'] = $height;
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
            throw new Error(create_invalid_value_message($orientation, "setOrientation", "html-to-pdf", "Allowed values are landscape, portrait.", "set_orientation"), 470);
        
        $this->fields['orientation'] = $orientation;
        return $this;
    }

    /**
    * Set the output page top margin.
    *
    * @param top Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginTop($top) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $top))
            throw new Error(create_invalid_value_message($top, "setMarginTop", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_top"), 470);
        
        $this->fields['margin_top'] = $top;
        return $this;
    }

    /**
    * Set the output page right margin.
    *
    * @param right Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginRight($right) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $right))
            throw new Error(create_invalid_value_message($right, "setMarginRight", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_right"), 470);
        
        $this->fields['margin_right'] = $right;
        return $this;
    }

    /**
    * Set the output page bottom margin.
    *
    * @param bottom Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginBottom($bottom) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $bottom))
            throw new Error(create_invalid_value_message($bottom, "setMarginBottom", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_bottom"), 470);
        
        $this->fields['margin_bottom'] = $bottom;
        return $this;
    }

    /**
    * Set the output page left margin.
    *
    * @param left Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setMarginLeft($left) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $left))
            throw new Error(create_invalid_value_message($left, "setMarginLeft", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_margin_left"), 470);
        
        $this->fields['margin_left'] = $left;
        return $this;
    }

    /**
    * Disable page margins.
    *
    * @param value Set to <span class='field-value'>true</span> to disable margins.
    * @return The converter object.
    */
    function setNoMargins($value) {
        $this->fields['no_margins'] = $value;
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
    * Set the page range to print.
    *
    * @param pages A comma separated list of page numbers or ranges.
    * @return The converter object.
    */
    function setPrintPageRange($pages) {
        if (!preg_match("/^(?:\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*,\s*)*\s*(?:\d+|(?:\d*\s*\-\s*\d+)|(?:\d+\s*\-\s*\d*))\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setPrintPageRange", "html-to-pdf", "A comma separated list of page numbers or ranges.", "set_print_page_range"), 470);
        
        $this->fields['print_page_range'] = $pages;
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
    * Set the top left X coordinate of the content area. It is relative to the top left X coordinate of the print area.
    *
    * @param x Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt). It may contain a negative value.
    * @return The converter object.
    */
    function setContentAreaX($x) {
        if (!preg_match("/(?i)^0$|^\-?[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $x))
            throw new Error(create_invalid_value_message($x, "setContentAreaX", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt). It may contain a negative value.", "set_content_area_x"), 470);
        
        $this->fields['content_area_x'] = $x;
        return $this;
    }

    /**
    * Set the top left Y coordinate of the content area. It is relative to the top left Y coordinate of the print area.
    *
    * @param y Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt). It may contain a negative value.
    * @return The converter object.
    */
    function setContentAreaY($y) {
        if (!preg_match("/(?i)^0$|^\-?[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $y))
            throw new Error(create_invalid_value_message($y, "setContentAreaY", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt). It may contain a negative value.", "set_content_area_y"), 470);
        
        $this->fields['content_area_y'] = $y;
        return $this;
    }

    /**
    * Set the width of the content area. It should be at least 1 inch.
    *
    * @param width Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setContentAreaWidth($width) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $width))
            throw new Error(create_invalid_value_message($width, "setContentAreaWidth", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_content_area_width"), 470);
        
        $this->fields['content_area_width'] = $width;
        return $this;
    }

    /**
    * Set the height of the content area. It should be at least 1 inch.
    *
    * @param height Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setContentAreaHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setContentAreaHeight", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_content_area_height"), 470);
        
        $this->fields['content_area_height'] = $height;
        return $this;
    }

    /**
    * Set the content area position and size. The content area enables to specify a web page area to be converted.
    *
    * @param x Set the top left X coordinate of the content area. It is relative to the top left X coordinate of the print area. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt). It may contain a negative value.
    * @param y Set the top left Y coordinate of the content area. It is relative to the top left Y coordinate of the print area. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt). It may contain a negative value.
    * @param width Set the width of the content area. It should be at least 1 inch. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @param height Set the height of the content area. It should be at least 1 inch. Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setContentArea($x, $y, $width, $height) {
        $this->setContentAreaX($x);
        $this->setContentAreaY($y);
        $this->setContentAreaWidth($width);
        $this->setContentAreaHeight($height);
        return $this;
    }

    /**
    * Specifies behavior in presence of CSS @page rules. It may affect the page size, margins and orientation.
    *
    * @param mode The page rule mode. Allowed values are default, mode1, mode2.
    * @return The converter object.
    */
    function setCssPageRuleMode($mode) {
        if (!preg_match("/(?i)^(default|mode1|mode2)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setCssPageRuleMode", "html-to-pdf", "Allowed values are default, mode1, mode2.", "set_css_page_rule_mode"), 470);
        
        $this->fields['css_page_rule_mode'] = $mode;
        return $this;
    }

    /**
    * Load an HTML code from the specified URL and use it as the page header. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values <ul> <li> Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt; </li> </ul> </li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul> </li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setHeaderUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setHeaderUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "set_header_url"), 470);
        
        $this->fields['header_url'] = $url;
        return $this;
    }

    /**
    * Use the specified HTML code as the page header. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values <ul> <li> Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt; </li> </ul> </li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul> </li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param html The string must not be empty.
    * @return The converter object.
    */
    function setHeaderHtml($html) {
        if (!($html != null && $html !== ''))
            throw new Error(create_invalid_value_message($html, "setHeaderHtml", "html-to-pdf", "The string must not be empty.", "set_header_html"), 470);
        
        $this->fields['header_html'] = $html;
        return $this;
    }

    /**
    * Set the header height.
    *
    * @param height Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setHeaderHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setHeaderHeight", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_header_height"), 470);
        
        $this->fields['header_height'] = $height;
        return $this;
    }

    /**
    * Load an HTML code from the specified URL and use it as the page footer. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values <ul> <li> Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt; </li> </ul> </li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul> </li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setFooterUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setFooterUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "set_footer_url"), 470);
        
        $this->fields['footer_url'] = $url;
        return $this;
    }

    /**
    * Use the specified HTML as the page footer. The following classes can be used in the HTML. The content of the respective elements will be expanded as follows: <ul> <li><span class='field-value'>pdfcrowd-page-count</span> - the total page count of printed pages</li> <li><span class='field-value'>pdfcrowd-page-number</span> - the current page number</li> <li><span class='field-value'>pdfcrowd-source-url</span> - the source URL of a converted document</li> </ul> The following attributes can be used: <ul> <li><span class='field-value'>data-pdfcrowd-number-format</span> - specifies the type of the used numerals <ul> <li>Arabic numerals are used by default.</li> <li>Roman numerals can be generated by the <span class='field-value'>roman</span> and <span class='field-value'>roman-lowercase</span> values <ul> <li> Example: &lt;span class='pdfcrowd-page-number' data-pdfcrowd-number-format='roman'&gt;&lt;/span&gt; </li> </ul> </li> </ul> </li> <li><span class='field-value'>data-pdfcrowd-placement</span> - specifies where to place the source URL, allowed values: <ul> <li>The URL is inserted to the content <ul> <li> Example: &lt;span class='pdfcrowd-source-url'&gt;&lt;/span&gt;<br> will produce &lt;span&gt;http://example.com&lt;/span&gt; </li> </ul> </li> <li><span class='field-value'>href</span> - the URL is set to the href attribute <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href'&gt;Link to source&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;Link to source&lt;/a&gt; </li> </ul> </li> <li><span class='field-value'>href-and-content</span> - the URL is set to the href attribute and to the content <ul> <li> Example: &lt;a class='pdfcrowd-source-url' data-pdfcrowd-placement='href-and-content'&gt;&lt;/a&gt;<br> will produce &lt;a href='http://example.com'&gt;http://example.com&lt;/a&gt; </li> </ul> </li> </ul> </li> </ul>
    *
    * @param html The string must not be empty.
    * @return The converter object.
    */
    function setFooterHtml($html) {
        if (!($html != null && $html !== ''))
            throw new Error(create_invalid_value_message($html, "setFooterHtml", "html-to-pdf", "The string must not be empty.", "set_footer_html"), 470);
        
        $this->fields['footer_html'] = $html;
        return $this;
    }

    /**
    * Set the footer height.
    *
    * @param height Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).
    * @return The converter object.
    */
    function setFooterHeight($height) {
        if (!preg_match("/(?i)^0$|^[0-9]*\.?[0-9]+(pt|px|mm|cm|in)$/", $height))
            throw new Error(create_invalid_value_message($height, "setFooterHeight", "html-to-pdf", "Can be specified in inches (in), millimeters (mm), centimeters (cm), or points (pt).", "set_footer_height"), 470);
        
        $this->fields['footer_height'] = $height;
        return $this;
    }

    /**
    * Disable horizontal page margins for header and footer. The header/footer contents width will be equal to the physical page width.
    *
    * @param value Set to <span class='field-value'>true</span> to disable horizontal margins for header and footer.
    * @return The converter object.
    */
    function setNoHeaderFooterHorizontalMargins($value) {
        $this->fields['no_header_footer_horizontal_margins'] = $value;
        return $this;
    }

    /**
    * The page header is not printed on the specified pages.
    *
    * @param pages List of physical page numbers. Negative numbers count backwards from the last page: -1 is the last page, -2 is the last but one page, and so on. A comma separated list of page numbers.
    * @return The converter object.
    */
    function setExcludeHeaderOnPages($pages) {
        if (!preg_match("/^(?:\s*\-?\d+\s*,)*\s*\-?\d+\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setExcludeHeaderOnPages", "html-to-pdf", "A comma separated list of page numbers.", "set_exclude_header_on_pages"), 470);
        
        $this->fields['exclude_header_on_pages'] = $pages;
        return $this;
    }

    /**
    * The page footer is not printed on the specified pages.
    *
    * @param pages List of physical page numbers. Negative numbers count backwards from the last page: -1 is the last page, -2 is the last but one page, and so on. A comma separated list of page numbers.
    * @return The converter object.
    */
    function setExcludeFooterOnPages($pages) {
        if (!preg_match("/^(?:\s*\-?\d+\s*,)*\s*\-?\d+\s*$/", $pages))
            throw new Error(create_invalid_value_message($pages, "setExcludeFooterOnPages", "html-to-pdf", "A comma separated list of page numbers.", "set_exclude_footer_on_pages"), 470);
        
        $this->fields['exclude_footer_on_pages'] = $pages;
        return $this;
    }

    /**
    * Set the scaling factor (zoom) for the header and footer.
    *
    * @param factor The percentage value. The value must be in the range 10-500.
    * @return The converter object.
    */
    function setHeaderFooterScaleFactor($factor) {
        if (!(intval($factor) >= 10 && intval($factor) <= 500))
            throw new Error(create_invalid_value_message($factor, "setHeaderFooterScaleFactor", "html-to-pdf", "The value must be in the range 10-500.", "set_header_footer_scale_factor"), 470);
        
        $this->fields['header_footer_scale_factor'] = $factor;
        return $this;
    }

    /**
    * Apply the first page of the watermark PDF to every page of the output PDF.
    *
    * @param watermark The file path to a local watermark PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setPageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setPageWatermark", "html-to-pdf", "The file must exist and not be empty.", "set_page_watermark"), 470);
        
        $this->files['page_watermark'] = $watermark;
        return $this;
    }

    /**
    * Load a watermark PDF from the specified URL and apply the first page of the watermark PDF to every page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setPageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageWatermarkUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "set_page_watermark_url"), 470);
        
        $this->fields['page_watermark_url'] = $url;
        return $this;
    }

    /**
    * Apply each page of the specified watermark PDF to the corresponding page of the output PDF.
    *
    * @param watermark The file path to a local watermark PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setMultipageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setMultipageWatermark", "html-to-pdf", "The file must exist and not be empty.", "set_multipage_watermark"), 470);
        
        $this->files['multipage_watermark'] = $watermark;
        return $this;
    }

    /**
    * Load a watermark PDF from the specified URL and apply each page of the specified watermark PDF to the corresponding page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setMultipageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageWatermarkUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "set_multipage_watermark_url"), 470);
        
        $this->fields['multipage_watermark_url'] = $url;
        return $this;
    }

    /**
    * Apply the first page of the specified PDF to the background of every page of the output PDF.
    *
    * @param background The file path to a local background PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setPageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setPageBackground", "html-to-pdf", "The file must exist and not be empty.", "set_page_background"), 470);
        
        $this->files['page_background'] = $background;
        return $this;
    }

    /**
    * Load a background PDF from the specified URL and apply the first page of the background PDF to every page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setPageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageBackgroundUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "set_page_background_url"), 470);
        
        $this->fields['page_background_url'] = $url;
        return $this;
    }

    /**
    * Apply each page of the specified PDF to the background of the corresponding page of the output PDF.
    *
    * @param background The file path to a local background PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setMultipageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setMultipageBackground", "html-to-pdf", "The file must exist and not be empty.", "set_multipage_background"), 470);
        
        $this->files['multipage_background'] = $background;
        return $this;
    }

    /**
    * Load a background PDF from the specified URL and apply each page of the specified background PDF to the corresponding page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setMultipageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageBackgroundUrl", "html-to-pdf", "The supported protocols are http:// and https://.", "set_multipage_background_url"), 470);
        
        $this->fields['multipage_background_url'] = $url;
        return $this;
    }

    /**
    * The page background color in RGB or RGBA hexadecimal format. The color fills the entire page regardless of the margins.
    *
    * @param color The value must be in RRGGBB or RRGGBBAA hexadecimal format.
    * @return The converter object.
    */
    function setPageBackgroundColor($color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $color))
            throw new Error(create_invalid_value_message($color, "setPageBackgroundColor", "html-to-pdf", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_page_background_color"), 470);
        
        $this->fields['page_background_color'] = $color;
        return $this;
    }

    /**
    * Use the print version of the page if available (@media print).
    *
    * @param value Set to <span class='field-value'>true</span> to use the print version of the page.
    * @return The converter object.
    */
    function setUsePrintMedia($value) {
        $this->fields['use_print_media'] = $value;
        return $this;
    }

    /**
    * Do not print the background graphics.
    *
    * @param value Set to <span class='field-value'>true</span> to disable the background graphics.
    * @return The converter object.
    */
    function setNoBackground($value) {
        $this->fields['no_background'] = $value;
        return $this;
    }

    /**
    * Do not execute JavaScript.
    *
    * @param value Set to <span class='field-value'>true</span> to disable JavaScript in web pages.
    * @return The converter object.
    */
    function setDisableJavascript($value) {
        $this->fields['disable_javascript'] = $value;
        return $this;
    }

    /**
    * Do not load images.
    *
    * @param value Set to <span class='field-value'>true</span> to disable loading of images.
    * @return The converter object.
    */
    function setDisableImageLoading($value) {
        $this->fields['disable_image_loading'] = $value;
        return $this;
    }

    /**
    * Disable loading fonts from remote sources.
    *
    * @param value Set to <span class='field-value'>true</span> disable loading remote fonts.
    * @return The converter object.
    */
    function setDisableRemoteFonts($value) {
        $this->fields['disable_remote_fonts'] = $value;
        return $this;
    }

    /**
    * Specifies how iframes are handled.
    *
    * @param iframes Allowed values are all, same-origin, none.
    * @return The converter object.
    */
    function setLoadIframes($iframes) {
        if (!preg_match("/(?i)^(all|same-origin|none)$/", $iframes))
            throw new Error(create_invalid_value_message($iframes, "setLoadIframes", "html-to-pdf", "Allowed values are all, same-origin, none.", "set_load_iframes"), 470);
        
        $this->fields['load_iframes'] = $iframes;
        return $this;
    }

    /**
    * Try to block ads. Enabling this option can produce smaller output and speed up the conversion.
    *
    * @param value Set to <span class='field-value'>true</span> to block ads in web pages.
    * @return The converter object.
    */
    function setBlockAds($value) {
        $this->fields['block_ads'] = $value;
        return $this;
    }

    /**
    * Set the default HTML content text encoding.
    *
    * @param encoding The text encoding of the HTML content.
    * @return The converter object.
    */
    function setDefaultEncoding($encoding) {
        $this->fields['default_encoding'] = $encoding;
        return $this;
    }

    /**
    * Set the locale for the conversion. This may affect the output format of dates, times and numbers.
    *
    * @param locale The locale code according to ISO 639.
    * @return The converter object.
    */
    function setLocale($locale) {
        $this->fields['locale'] = $locale;
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
    * @param value Set to <span class='field-value'>true</span> to enable SSL certificate verification.
    * @return The converter object.
    */
    function setVerifySslCertificates($value) {
        $this->fields['verify_ssl_certificates'] = $value;
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
    * Do not send the X-Pdfcrowd HTTP header in Pdfcrowd HTTP requests.
    *
    * @param value Set to <span class='field-value'>true</span> to disable sending X-Pdfcrowd HTTP header.
    * @return The converter object.
    */
    function setNoXpdfcrowdHeader($value) {
        $this->fields['no_xpdfcrowd_header'] = $value;
        return $this;
    }

    /**
    * Run a custom JavaScript after the document is loaded and ready to print. The script is intended for post-load DOM manipulation (add/remove elements, update CSS, ...). In addition to the standard browser APIs, the custom JavaScript code can use helper functions from our <a href='/doc/api/libpdfcrowd/'>JavaScript library</a>.
    *
    * @param javascript A string containing a JavaScript code. The string must not be empty.
    * @return The converter object.
    */
    function setCustomJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setCustomJavascript", "html-to-pdf", "The string must not be empty.", "set_custom_javascript"), 470);
        
        $this->fields['custom_javascript'] = $javascript;
        return $this;
    }

    /**
    * Run a custom JavaScript right after the document is loaded. The script is intended for early DOM manipulation (add/remove elements, update CSS, ...). In addition to the standard browser APIs, the custom JavaScript code can use helper functions from our <a href='/doc/api/libpdfcrowd/'>JavaScript library</a>.
    *
    * @param javascript A string containing a JavaScript code. The string must not be empty.
    * @return The converter object.
    */
    function setOnLoadJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setOnLoadJavascript", "html-to-pdf", "The string must not be empty.", "set_on_load_javascript"), 470);
        
        $this->fields['on_load_javascript'] = $javascript;
        return $this;
    }

    /**
    * Set a custom HTTP header that is sent in Pdfcrowd HTTP requests.
    *
    * @param header A string containing the header name and value separated by a colon.
    * @return The converter object.
    */
    function setCustomHttpHeader($header) {
        if (!preg_match("/^.+:.+$/", $header))
            throw new Error(create_invalid_value_message($header, "setCustomHttpHeader", "html-to-pdf", "A string containing the header name and value separated by a colon.", "set_custom_http_header"), 470);
        
        $this->fields['custom_http_header'] = $header;
        return $this;
    }

    /**
    * Wait the specified number of milliseconds to finish all JavaScript after the document is loaded. Your API license defines the maximum wait time by "Max Delay" parameter.
    *
    * @param delay The number of milliseconds to wait. Must be a positive integer number or 0.
    * @return The converter object.
    */
    function setJavascriptDelay($delay) {
        if (!(intval($delay) >= 0))
            throw new Error(create_invalid_value_message($delay, "setJavascriptDelay", "html-to-pdf", "Must be a positive integer number or 0.", "set_javascript_delay"), 470);
        
        $this->fields['javascript_delay'] = $delay;
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
            throw new Error(create_invalid_value_message($selectors, "setElementToConvert", "html-to-pdf", "The string must not be empty.", "set_element_to_convert"), 470);
        
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
            throw new Error(create_invalid_value_message($mode, "setElementToConvertMode", "html-to-pdf", "Allowed values are cut-out, remove-siblings, hide-siblings.", "set_element_to_convert_mode"), 470);
        
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
            throw new Error(create_invalid_value_message($selectors, "setWaitForElement", "html-to-pdf", "The string must not be empty.", "set_wait_for_element"), 470);
        
        $this->fields['wait_for_element'] = $selectors;
        return $this;
    }

    /**
    * Set the viewport width in pixels. The viewport is the user's visible area of the page.
    *
    * @param width The value must be in the range 96-65000.
    * @return The converter object.
    */
    function setViewportWidth($width) {
        if (!(intval($width) >= 96 && intval($width) <= 65000))
            throw new Error(create_invalid_value_message($width, "setViewportWidth", "html-to-pdf", "The value must be in the range 96-65000.", "set_viewport_width"), 470);
        
        $this->fields['viewport_width'] = $width;
        return $this;
    }

    /**
    * Set the viewport height in pixels. The viewport is the user's visible area of the page.
    *
    * @param height Must be a positive integer number.
    * @return The converter object.
    */
    function setViewportHeight($height) {
        if (!(intval($height) > 0))
            throw new Error(create_invalid_value_message($height, "setViewportHeight", "html-to-pdf", "Must be a positive integer number.", "set_viewport_height"), 470);
        
        $this->fields['viewport_height'] = $height;
        return $this;
    }

    /**
    * Set the viewport size. The viewport is the user's visible area of the page.
    *
    * @param width Set the viewport width in pixels. The viewport is the user's visible area of the page. The value must be in the range 96-65000.
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
    * @param mode The rendering mode. Allowed values are default, viewport.
    * @return The converter object.
    */
    function setRenderingMode($mode) {
        if (!preg_match("/(?i)^(default|viewport)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setRenderingMode", "html-to-pdf", "Allowed values are default, viewport.", "set_rendering_mode"), 470);
        
        $this->fields['rendering_mode'] = $mode;
        return $this;
    }

    /**
    * Specifies the scaling mode used for fitting the HTML contents to the print area.
    *
    * @param mode The smart scaling mode. Allowed values are default, disabled, viewport-fit, content-fit, single-page-fit, mode1.
    * @return The converter object.
    */
    function setSmartScalingMode($mode) {
        if (!preg_match("/(?i)^(default|disabled|viewport-fit|content-fit|single-page-fit|mode1)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setSmartScalingMode", "html-to-pdf", "Allowed values are default, disabled, viewport-fit, content-fit, single-page-fit, mode1.", "set_smart_scaling_mode"), 470);
        
        $this->fields['smart_scaling_mode'] = $mode;
        return $this;
    }

    /**
    * Set the scaling factor (zoom) for the main page area.
    *
    * @param factor The percentage value. The value must be in the range 10-500.
    * @return The converter object.
    */
    function setScaleFactor($factor) {
        if (!(intval($factor) >= 10 && intval($factor) <= 500))
            throw new Error(create_invalid_value_message($factor, "setScaleFactor", "html-to-pdf", "The value must be in the range 10-500.", "set_scale_factor"), 470);
        
        $this->fields['scale_factor'] = $factor;
        return $this;
    }

    /**
    * Set the quality of embedded JPEG images. A lower quality results in a smaller PDF file but can lead to compression artifacts.
    *
    * @param quality The percentage value. The value must be in the range 1-100.
    * @return The converter object.
    */
    function setJpegQuality($quality) {
        if (!(intval($quality) >= 1 && intval($quality) <= 100))
            throw new Error(create_invalid_value_message($quality, "setJpegQuality", "html-to-pdf", "The value must be in the range 1-100.", "set_jpeg_quality"), 470);
        
        $this->fields['jpeg_quality'] = $quality;
        return $this;
    }

    /**
    * Specify which image types will be converted to JPEG. Converting lossless compression image formats (PNG, GIF, ...) to JPEG may result in a smaller PDF file.
    *
    * @param images The image category. Allowed values are none, opaque, all.
    * @return The converter object.
    */
    function setConvertImagesToJpeg($images) {
        if (!preg_match("/(?i)^(none|opaque|all)$/", $images))
            throw new Error(create_invalid_value_message($images, "setConvertImagesToJpeg", "html-to-pdf", "Allowed values are none, opaque, all.", "set_convert_images_to_jpeg"), 470);
        
        $this->fields['convert_images_to_jpeg'] = $images;
        return $this;
    }

    /**
    * Set the DPI of images in PDF. A lower DPI may result in a smaller PDF file.  If the specified DPI is higher than the actual image DPI, the original image DPI is retained (no upscaling is performed). Use <span class='field-value'>0</span> to leave the images unaltered.
    *
    * @param dpi The DPI value. Must be a positive integer number or 0.
    * @return The converter object.
    */
    function setImageDpi($dpi) {
        if (!(intval($dpi) >= 0))
            throw new Error(create_invalid_value_message($dpi, "setImageDpi", "html-to-pdf", "Must be a positive integer number or 0.", "set_image_dpi"), 470);
        
        $this->fields['image_dpi'] = $dpi;
        return $this;
    }

    /**
    * Create linearized PDF. This is also known as Fast Web View.
    *
    * @param value Set to <span class='field-value'>true</span> to create linearized PDF.
    * @return The converter object.
    */
    function setLinearize($value) {
        $this->fields['linearize'] = $value;
        return $this;
    }

    /**
    * Encrypt the PDF. This prevents search engines from indexing the contents.
    *
    * @param value Set to <span class='field-value'>true</span> to enable PDF encryption.
    * @return The converter object.
    */
    function setEncrypt($value) {
        $this->fields['encrypt'] = $value;
        return $this;
    }

    /**
    * Protect the PDF with a user password. When a PDF has a user password, it must be supplied in order to view the document and to perform operations allowed by the access permissions.
    *
    * @param password The user password.
    * @return The converter object.
    */
    function setUserPassword($password) {
        $this->fields['user_password'] = $password;
        return $this;
    }

    /**
    * Protect the PDF with an owner password.  Supplying an owner password grants unlimited access to the PDF including changing the passwords and access permissions.
    *
    * @param password The owner password.
    * @return The converter object.
    */
    function setOwnerPassword($password) {
        $this->fields['owner_password'] = $password;
        return $this;
    }

    /**
    * Disallow printing of the output PDF.
    *
    * @param value Set to <span class='field-value'>true</span> to set the no-print flag in the output PDF.
    * @return The converter object.
    */
    function setNoPrint($value) {
        $this->fields['no_print'] = $value;
        return $this;
    }

    /**
    * Disallow modification of the output PDF.
    *
    * @param value Set to <span class='field-value'>true</span> to set the read-only only flag in the output PDF.
    * @return The converter object.
    */
    function setNoModify($value) {
        $this->fields['no_modify'] = $value;
        return $this;
    }

    /**
    * Disallow text and graphics extraction from the output PDF.
    *
    * @param value Set to <span class='field-value'>true</span> to set the no-copy flag in the output PDF.
    * @return The converter object.
    */
    function setNoCopy($value) {
        $this->fields['no_copy'] = $value;
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
    * @param layout Allowed values are single-page, one-column, two-column-left, two-column-right.
    * @return The converter object.
    */
    function setPageLayout($layout) {
        if (!preg_match("/(?i)^(single-page|one-column|two-column-left|two-column-right)$/", $layout))
            throw new Error(create_invalid_value_message($layout, "setPageLayout", "html-to-pdf", "Allowed values are single-page, one-column, two-column-left, two-column-right.", "set_page_layout"), 470);
        
        $this->fields['page_layout'] = $layout;
        return $this;
    }

    /**
    * Specify how the document should be displayed when opened.
    *
    * @param mode Allowed values are full-screen, thumbnails, outlines.
    * @return The converter object.
    */
    function setPageMode($mode) {
        if (!preg_match("/(?i)^(full-screen|thumbnails|outlines)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPageMode", "html-to-pdf", "Allowed values are full-screen, thumbnails, outlines.", "set_page_mode"), 470);
        
        $this->fields['page_mode'] = $mode;
        return $this;
    }

    /**
    * Specify how the page should be displayed when opened.
    *
    * @param zoom_type Allowed values are fit-width, fit-height, fit-page.
    * @return The converter object.
    */
    function setInitialZoomType($zoom_type) {
        if (!preg_match("/(?i)^(fit-width|fit-height|fit-page)$/", $zoom_type))
            throw new Error(create_invalid_value_message($zoom_type, "setInitialZoomType", "html-to-pdf", "Allowed values are fit-width, fit-height, fit-page.", "set_initial_zoom_type"), 470);
        
        $this->fields['initial_zoom_type'] = $zoom_type;
        return $this;
    }

    /**
    * Display the specified page when the document is opened.
    *
    * @param page Must be a positive integer number.
    * @return The converter object.
    */
    function setInitialPage($page) {
        if (!(intval($page) > 0))
            throw new Error(create_invalid_value_message($page, "setInitialPage", "html-to-pdf", "Must be a positive integer number.", "set_initial_page"), 470);
        
        $this->fields['initial_page'] = $page;
        return $this;
    }

    /**
    * Specify the initial page zoom in percents when the document is opened.
    *
    * @param zoom Must be a positive integer number.
    * @return The converter object.
    */
    function setInitialZoom($zoom) {
        if (!(intval($zoom) > 0))
            throw new Error(create_invalid_value_message($zoom, "setInitialZoom", "html-to-pdf", "Must be a positive integer number.", "set_initial_zoom"), 470);
        
        $this->fields['initial_zoom'] = $zoom;
        return $this;
    }

    /**
    * Specify whether to hide the viewer application's tool bars when the document is active.
    *
    * @param value Set to <span class='field-value'>true</span> to hide tool bars.
    * @return The converter object.
    */
    function setHideToolbar($value) {
        $this->fields['hide_toolbar'] = $value;
        return $this;
    }

    /**
    * Specify whether to hide the viewer application's menu bar when the document is active.
    *
    * @param value Set to <span class='field-value'>true</span> to hide the menu bar.
    * @return The converter object.
    */
    function setHideMenubar($value) {
        $this->fields['hide_menubar'] = $value;
        return $this;
    }

    /**
    * Specify whether to hide user interface elements in the document's window (such as scroll bars and navigation controls), leaving only the document's contents displayed.
    *
    * @param value Set to <span class='field-value'>true</span> to hide ui elements.
    * @return The converter object.
    */
    function setHideWindowUi($value) {
        $this->fields['hide_window_ui'] = $value;
        return $this;
    }

    /**
    * Specify whether to resize the document's window to fit the size of the first displayed page.
    *
    * @param value Set to <span class='field-value'>true</span> to resize the window.
    * @return The converter object.
    */
    function setFitWindow($value) {
        $this->fields['fit_window'] = $value;
        return $this;
    }

    /**
    * Specify whether to position the document's window in the center of the screen.
    *
    * @param value Set to <span class='field-value'>true</span> to center the window.
    * @return The converter object.
    */
    function setCenterWindow($value) {
        $this->fields['center_window'] = $value;
        return $this;
    }

    /**
    * Specify whether the window's title bar should display the document title. If false , the title bar should instead display the name of the PDF file containing the document.
    *
    * @param value Set to <span class='field-value'>true</span> to display the title.
    * @return The converter object.
    */
    function setDisplayTitle($value) {
        $this->fields['display_title'] = $value;
        return $this;
    }

    /**
    * Set the predominant reading order for text to right-to-left. This option has no direct effect on the document's contents or page numbering but can be used to determine the relative positioning of pages when displayed side by side or printed n-up
    *
    * @param value Set to <span class='field-value'>true</span> to set right-to-left reading order.
    * @return The converter object.
    */
    function setRightToLeft($value) {
        $this->fields['right_to_left'] = $value;
        return $this;
    }

    /**
    * Set the input data for template rendering. The data format can be JSON, XML, YAML or CSV.
    *
    * @param data_string The input data string.
    * @return The converter object.
    */
    function setDataString($data_string) {
        $this->fields['data_string'] = $data_string;
        return $this;
    }

    /**
    * Load the input data for template rendering from the specified file. The data format can be JSON, XML, YAML or CSV.
    *
    * @param data_file The file path to a local file containing the input data.
    * @return The converter object.
    */
    function setDataFile($data_file) {
        $this->files['data_file'] = $data_file;
        return $this;
    }

    /**
    * Specify the input data format.
    *
    * @param data_format The data format. Allowed values are auto, json, xml, yaml, csv.
    * @return The converter object.
    */
    function setDataFormat($data_format) {
        if (!preg_match("/(?i)^(auto|json|xml|yaml|csv)$/", $data_format))
            throw new Error(create_invalid_value_message($data_format, "setDataFormat", "html-to-pdf", "Allowed values are auto, json, xml, yaml, csv.", "set_data_format"), 470);
        
        $this->fields['data_format'] = $data_format;
        return $this;
    }

    /**
    * Set the encoding of the data file set by <a href='#set_data_file'>setDataFile</a>.
    *
    * @param encoding The data file encoding.
    * @return The converter object.
    */
    function setDataEncoding($encoding) {
        $this->fields['data_encoding'] = $encoding;
        return $this;
    }

    /**
    * Ignore undefined variables in the HTML template. The default mode is strict so any undefined variable causes the conversion to fail. You can use <span class='field-value text-nowrap'>&#x007b;&#x0025; if variable is defined &#x0025;&#x007d;</span> to check if the variable is defined.
    *
    * @param value Set to <span class='field-value'>true</span> to ignore undefined variables.
    * @return The converter object.
    */
    function setDataIgnoreUndefined($value) {
        $this->fields['data_ignore_undefined'] = $value;
        return $this;
    }

    /**
    * Auto escape HTML symbols in the input data before placing them into the output.
    *
    * @param value Set to <span class='field-value'>true</span> to turn auto escaping on.
    * @return The converter object.
    */
    function setDataAutoEscape($value) {
        $this->fields['data_auto_escape'] = $value;
        return $this;
    }

    /**
    * Auto trim whitespace around each template command block.
    *
    * @param value Set to <span class='field-value'>true</span> to turn auto trimming on.
    * @return The converter object.
    */
    function setDataTrimBlocks($value) {
        $this->fields['data_trim_blocks'] = $value;
        return $this;
    }

    /**
    * Set the advanced data options:<ul><li><span class='field-value'>csv_delimiter</span> - The CSV data delimiter, the default is <span class='field-value'>,</span>.</li><li><span class='field-value'>xml_remove_root</span> - Remove the root XML element from the input data.</li><li><span class='field-value'>data_root</span> - The name of the root element inserted into the input data without a root node (e.g. CSV), the default is <span class='field-value'>data</span>.</li></ul>
    *
    * @param options Comma separated list of options.
    * @return The converter object.
    */
    function setDataOptions($options) {
        $this->fields['data_options'] = $options;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param value Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
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
    * This method can only be called after a call to one of the convertXYZ methods.
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
    * Get the version details.
    * @return API version, converter version, and client version.
    */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
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
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "html-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "html-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
    * A client certificate to authenticate Pdfcrowd converter on your web server. The certificate is used for two-way SSL/TLS authentication and adds extra security.
    *
    * @param certificate The file must be in PKCS12 format. The file must exist and not be empty.
    * @return The converter object.
    */
    function setClientCertificate($certificate) {
        if (!(filesize($certificate) > 0))
            throw new Error(create_invalid_value_message($certificate, "setClientCertificate", "html-to-pdf", "The file must exist and not be empty.", "set_client_certificate"), 470);
        
        $this->files['client_certificate'] = $certificate;
        return $this;
    }

    /**
    * A password for PKCS12 file with a client certificate if it is needed.
    *
    * @param password
    * @return The converter object.
    */
    function setClientCertificatePassword($password) {
        $this->fields['client_certificate_password'] = $password;
        return $this;
    }

    /**
    * Set the internal DPI resolution used for positioning of PDF contents. It can help in situations when there are small inaccuracies in the PDF. It is recommended to use values that are a multiple of 72, such as 288 or 360.
    *
    * @param dpi The DPI value. The value must be in the range of 72-600.
    * @return The converter object.
    */
    function setLayoutDpi($dpi) {
        if (!(intval($dpi) >= 72 && intval($dpi) <= 600))
            throw new Error(create_invalid_value_message($dpi, "setLayoutDpi", "html-to-pdf", "The value must be in the range of 72-600.", "set_layout_dpi"), 470);
        
        $this->fields['layout_dpi'] = $dpi;
        return $this;
    }

    /**
    * A 2D transformation matrix applied to the main contents on each page. The origin [0,0] is located at the top-left corner of the contents. The resolution is 72 dpi.
    *
    * @param matrix A comma separated string of matrix elements: "scaleX,skewX,transX,skewY,scaleY,transY"
    * @return The converter object.
    */
    function setContentsMatrix($matrix) {
        $this->fields['contents_matrix'] = $matrix;
        return $this;
    }

    /**
    * A 2D transformation matrix applied to the page header contents. The origin [0,0] is located at the top-left corner of the header. The resolution is 72 dpi.
    *
    * @param matrix A comma separated string of matrix elements: "scaleX,skewX,transX,skewY,scaleY,transY"
    * @return The converter object.
    */
    function setHeaderMatrix($matrix) {
        $this->fields['header_matrix'] = $matrix;
        return $this;
    }

    /**
    * A 2D transformation matrix applied to the page footer contents. The origin [0,0] is located at the top-left corner of the footer. The resolution is 72 dpi.
    *
    * @param matrix A comma separated string of matrix elements: "scaleX,skewX,transX,skewY,scaleY,transY"
    * @return The converter object.
    */
    function setFooterMatrix($matrix) {
        $this->fields['footer_matrix'] = $matrix;
        return $this;
    }

    /**
    * Disable automatic height adjustment that compensates for pixel to point rounding errors.
    *
    * @param value Set to <span class='field-value'>true</span> to disable automatic height scale.
    * @return The converter object.
    */
    function setDisablePageHeightOptimization($value) {
        $this->fields['disable_page_height_optimization'] = $value;
        return $this;
    }

    /**
    * Add special CSS classes to the main document's body element. This allows applying custom styling based on these classes:
  <ul>
    <li><span class='field-value'>pdfcrowd-page-X</span> - where X is the current page number</li>
    <li><span class='field-value'>pdfcrowd-page-odd</span> - odd page</li>
    <li><span class='field-value'>pdfcrowd-page-even</span> - even page</li>
  </ul>
    * Warning: If your custom styling affects the contents area size (e.g. by using different margins, padding, border width), the resulting PDF may contain duplicit contents or some contents may be missing.
    *
    * @param value Set to <span class='field-value'>true</span> to add the special CSS classes.
    * @return The converter object.
    */
    function setMainDocumentCssAnnotation($value) {
        $this->fields['main_document_css_annotation'] = $value;
        return $this;
    }

    /**
    * Add special CSS classes to the header/footer's body element. This allows applying custom styling based on these classes:
  <ul>
    <li><span class='field-value'>pdfcrowd-page-X</span> - where X is the current page number</li>
    <li><span class='field-value'>pdfcrowd-page-count-X</span> - where X is the total page count</li>
    <li><span class='field-value'>pdfcrowd-page-first</span> - the first page</li>
    <li><span class='field-value'>pdfcrowd-page-last</span> - the last page</li>
    <li><span class='field-value'>pdfcrowd-page-odd</span> - odd page</li>
    <li><span class='field-value'>pdfcrowd-page-even</span> - even page</li>
  </ul>
    *
    * @param value Set to <span class='field-value'>true</span> to add the special CSS classes.
    * @return The converter object.
    */
    function setHeaderFooterCssAnnotation($value) {
        $this->fields['header_footer_css_annotation'] = $value;
        return $this;
    }

    /**
    * Set the converter version. Different versions may produce different output. Choose which one provides the best output for your case.
    *
    * @param version The version identifier. Allowed values are latest, 20.10, 18.10.
    * @return The converter object.
    */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(latest|20.10|18.10)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "html-to-pdf", "Allowed values are latest, 20.10, 18.10.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    * Warning: Using HTTP is insecure as data sent over HTTP is not encrypted. Enable this option only if you know what you are doing.
    *
    * @param value Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be useful if you are behind some proxy or firewall.
    *
    * @param agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
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
    * Use cURL for the conversion request instead of the file_get_contents() PHP function.
    *
    * @param value Set to <span class='field-value'>true</span> to use PHP's cURL.
    * @return The converter object.
    */
    function setUseCurl($value) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
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
            throw new Error(create_invalid_value_message($output_format, "setOutputFormat", "html-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
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
            throw new Error(create_invalid_value_message($url, "convertUrl", "html-to-image", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
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
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "html-to-image", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br> The file can be either a single file or an archive (.tar.gz, .tar.bz2, or .zip).<br> If the HTML document refers to local external assets (images, style sheets, javascript), zip the document together with the assets. The file must exist and not be empty. The file name must have a valid extension.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "html-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
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
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "html-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
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
    * Convert a string.
    *
    * @param text The string content to convert. The string must not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertString($text) {
        if (!($text != null && $text !== ''))
            throw new Error(create_invalid_value_message($text, "convertString", "html-to-image", "The string must not be empty.", "convert_string"), 470);
        
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
            throw new Error(create_invalid_value_message($text, "convertStringToStream::text", "html-to-image", "The string must not be empty.", "convert_string_to_stream"), 470);
        
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
    * Convert the contents of an input stream.
    *
    * @param in_stream The input stream with source data.<br> The stream can contain either HTML code or an archive (.zip, .tar.gz, .tar.bz2).<br>The archive can contain HTML code and its external assets (images, style sheets, javascript).
    * @return Byte array containing the conversion output.
    */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert the contents of an input stream and write the result to an output stream.
    *
    * @param in_stream The input stream with source data.<br> The stream can contain either HTML code or an archive (.zip, .tar.gz, .tar.bz2).<br>The archive can contain HTML code and its external assets (images, style sheets, javascript).
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert the contents of an input stream and write the result to a local file.
    *
    * @param in_stream The input stream with source data.<br> The stream can contain either HTML code or an archive (.zip, .tar.gz, .tar.bz2).<br>The archive can contain HTML code and its external assets (images, style sheets, javascript).
    * @param file_path The output file path. The string must not be empty.
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
    * Use the print version of the page if available (@media print).
    *
    * @param value Set to <span class='field-value'>true</span> to use the print version of the page.
    * @return The converter object.
    */
    function setUsePrintMedia($value) {
        $this->fields['use_print_media'] = $value;
        return $this;
    }

    /**
    * Do not print the background graphics.
    *
    * @param value Set to <span class='field-value'>true</span> to disable the background graphics.
    * @return The converter object.
    */
    function setNoBackground($value) {
        $this->fields['no_background'] = $value;
        return $this;
    }

    /**
    * Do not execute JavaScript.
    *
    * @param value Set to <span class='field-value'>true</span> to disable JavaScript in web pages.
    * @return The converter object.
    */
    function setDisableJavascript($value) {
        $this->fields['disable_javascript'] = $value;
        return $this;
    }

    /**
    * Do not load images.
    *
    * @param value Set to <span class='field-value'>true</span> to disable loading of images.
    * @return The converter object.
    */
    function setDisableImageLoading($value) {
        $this->fields['disable_image_loading'] = $value;
        return $this;
    }

    /**
    * Disable loading fonts from remote sources.
    *
    * @param value Set to <span class='field-value'>true</span> disable loading remote fonts.
    * @return The converter object.
    */
    function setDisableRemoteFonts($value) {
        $this->fields['disable_remote_fonts'] = $value;
        return $this;
    }

    /**
    * Specifies how iframes are handled.
    *
    * @param iframes Allowed values are all, same-origin, none.
    * @return The converter object.
    */
    function setLoadIframes($iframes) {
        if (!preg_match("/(?i)^(all|same-origin|none)$/", $iframes))
            throw new Error(create_invalid_value_message($iframes, "setLoadIframes", "html-to-image", "Allowed values are all, same-origin, none.", "set_load_iframes"), 470);
        
        $this->fields['load_iframes'] = $iframes;
        return $this;
    }

    /**
    * Try to block ads. Enabling this option can produce smaller output and speed up the conversion.
    *
    * @param value Set to <span class='field-value'>true</span> to block ads in web pages.
    * @return The converter object.
    */
    function setBlockAds($value) {
        $this->fields['block_ads'] = $value;
        return $this;
    }

    /**
    * Set the default HTML content text encoding.
    *
    * @param encoding The text encoding of the HTML content.
    * @return The converter object.
    */
    function setDefaultEncoding($encoding) {
        $this->fields['default_encoding'] = $encoding;
        return $this;
    }

    /**
    * Set the locale for the conversion. This may affect the output format of dates, times and numbers.
    *
    * @param locale The locale code according to ISO 639.
    * @return The converter object.
    */
    function setLocale($locale) {
        $this->fields['locale'] = $locale;
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
    * @param value Set to <span class='field-value'>true</span> to enable SSL certificate verification.
    * @return The converter object.
    */
    function setVerifySslCertificates($value) {
        $this->fields['verify_ssl_certificates'] = $value;
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
    * Do not send the X-Pdfcrowd HTTP header in Pdfcrowd HTTP requests.
    *
    * @param value Set to <span class='field-value'>true</span> to disable sending X-Pdfcrowd HTTP header.
    * @return The converter object.
    */
    function setNoXpdfcrowdHeader($value) {
        $this->fields['no_xpdfcrowd_header'] = $value;
        return $this;
    }

    /**
    * Run a custom JavaScript after the document is loaded and ready to print. The script is intended for post-load DOM manipulation (add/remove elements, update CSS, ...). In addition to the standard browser APIs, the custom JavaScript code can use helper functions from our <a href='/doc/api/libpdfcrowd/'>JavaScript library</a>.
    *
    * @param javascript A string containing a JavaScript code. The string must not be empty.
    * @return The converter object.
    */
    function setCustomJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setCustomJavascript", "html-to-image", "The string must not be empty.", "set_custom_javascript"), 470);
        
        $this->fields['custom_javascript'] = $javascript;
        return $this;
    }

    /**
    * Run a custom JavaScript right after the document is loaded. The script is intended for early DOM manipulation (add/remove elements, update CSS, ...). In addition to the standard browser APIs, the custom JavaScript code can use helper functions from our <a href='/doc/api/libpdfcrowd/'>JavaScript library</a>.
    *
    * @param javascript A string containing a JavaScript code. The string must not be empty.
    * @return The converter object.
    */
    function setOnLoadJavascript($javascript) {
        if (!($javascript != null && $javascript !== ''))
            throw new Error(create_invalid_value_message($javascript, "setOnLoadJavascript", "html-to-image", "The string must not be empty.", "set_on_load_javascript"), 470);
        
        $this->fields['on_load_javascript'] = $javascript;
        return $this;
    }

    /**
    * Set a custom HTTP header that is sent in Pdfcrowd HTTP requests.
    *
    * @param header A string containing the header name and value separated by a colon.
    * @return The converter object.
    */
    function setCustomHttpHeader($header) {
        if (!preg_match("/^.+:.+$/", $header))
            throw new Error(create_invalid_value_message($header, "setCustomHttpHeader", "html-to-image", "A string containing the header name and value separated by a colon.", "set_custom_http_header"), 470);
        
        $this->fields['custom_http_header'] = $header;
        return $this;
    }

    /**
    * Wait the specified number of milliseconds to finish all JavaScript after the document is loaded. Your API license defines the maximum wait time by "Max Delay" parameter.
    *
    * @param delay The number of milliseconds to wait. Must be a positive integer number or 0.
    * @return The converter object.
    */
    function setJavascriptDelay($delay) {
        if (!(intval($delay) >= 0))
            throw new Error(create_invalid_value_message($delay, "setJavascriptDelay", "html-to-image", "Must be a positive integer number or 0.", "set_javascript_delay"), 470);
        
        $this->fields['javascript_delay'] = $delay;
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
            throw new Error(create_invalid_value_message($selectors, "setElementToConvert", "html-to-image", "The string must not be empty.", "set_element_to_convert"), 470);
        
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
            throw new Error(create_invalid_value_message($mode, "setElementToConvertMode", "html-to-image", "Allowed values are cut-out, remove-siblings, hide-siblings.", "set_element_to_convert_mode"), 470);
        
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
            throw new Error(create_invalid_value_message($selectors, "setWaitForElement", "html-to-image", "The string must not be empty.", "set_wait_for_element"), 470);
        
        $this->fields['wait_for_element'] = $selectors;
        return $this;
    }

    /**
    * Set the output image width in pixels.
    *
    * @param width The value must be in the range 96-65000.
    * @return The converter object.
    */
    function setScreenshotWidth($width) {
        if (!(intval($width) >= 96 && intval($width) <= 65000))
            throw new Error(create_invalid_value_message($width, "setScreenshotWidth", "html-to-image", "The value must be in the range 96-65000.", "set_screenshot_width"), 470);
        
        $this->fields['screenshot_width'] = $width;
        return $this;
    }

    /**
    * Set the output image height in pixels. If it is not specified, actual document height is used.
    *
    * @param height Must be a positive integer number.
    * @return The converter object.
    */
    function setScreenshotHeight($height) {
        if (!(intval($height) > 0))
            throw new Error(create_invalid_value_message($height, "setScreenshotHeight", "html-to-image", "Must be a positive integer number.", "set_screenshot_height"), 470);
        
        $this->fields['screenshot_height'] = $height;
        return $this;
    }

    /**
    * Set the scaling factor (zoom) for the output image.
    *
    * @param factor The percentage value. Must be a positive integer number.
    * @return The converter object.
    */
    function setScaleFactor($factor) {
        if (!(intval($factor) > 0))
            throw new Error(create_invalid_value_message($factor, "setScaleFactor", "html-to-image", "Must be a positive integer number.", "set_scale_factor"), 470);
        
        $this->fields['scale_factor'] = $factor;
        return $this;
    }

    /**
    * The output image background color.
    *
    * @param color The value must be in RRGGBB or RRGGBBAA hexadecimal format.
    * @return The converter object.
    */
    function setBackgroundColor($color) {
        if (!preg_match("/^[0-9a-fA-F]{6,8}$/", $color))
            throw new Error(create_invalid_value_message($color, "setBackgroundColor", "html-to-image", "The value must be in RRGGBB or RRGGBBAA hexadecimal format.", "set_background_color"), 470);
        
        $this->fields['background_color'] = $color;
        return $this;
    }

    /**
    * Set the input data for template rendering. The data format can be JSON, XML, YAML or CSV.
    *
    * @param data_string The input data string.
    * @return The converter object.
    */
    function setDataString($data_string) {
        $this->fields['data_string'] = $data_string;
        return $this;
    }

    /**
    * Load the input data for template rendering from the specified file. The data format can be JSON, XML, YAML or CSV.
    *
    * @param data_file The file path to a local file containing the input data.
    * @return The converter object.
    */
    function setDataFile($data_file) {
        $this->files['data_file'] = $data_file;
        return $this;
    }

    /**
    * Specify the input data format.
    *
    * @param data_format The data format. Allowed values are auto, json, xml, yaml, csv.
    * @return The converter object.
    */
    function setDataFormat($data_format) {
        if (!preg_match("/(?i)^(auto|json|xml|yaml|csv)$/", $data_format))
            throw new Error(create_invalid_value_message($data_format, "setDataFormat", "html-to-image", "Allowed values are auto, json, xml, yaml, csv.", "set_data_format"), 470);
        
        $this->fields['data_format'] = $data_format;
        return $this;
    }

    /**
    * Set the encoding of the data file set by <a href='#set_data_file'>setDataFile</a>.
    *
    * @param encoding The data file encoding.
    * @return The converter object.
    */
    function setDataEncoding($encoding) {
        $this->fields['data_encoding'] = $encoding;
        return $this;
    }

    /**
    * Ignore undefined variables in the HTML template. The default mode is strict so any undefined variable causes the conversion to fail. You can use <span class='field-value text-nowrap'>&#x007b;&#x0025; if variable is defined &#x0025;&#x007d;</span> to check if the variable is defined.
    *
    * @param value Set to <span class='field-value'>true</span> to ignore undefined variables.
    * @return The converter object.
    */
    function setDataIgnoreUndefined($value) {
        $this->fields['data_ignore_undefined'] = $value;
        return $this;
    }

    /**
    * Auto escape HTML symbols in the input data before placing them into the output.
    *
    * @param value Set to <span class='field-value'>true</span> to turn auto escaping on.
    * @return The converter object.
    */
    function setDataAutoEscape($value) {
        $this->fields['data_auto_escape'] = $value;
        return $this;
    }

    /**
    * Auto trim whitespace around each template command block.
    *
    * @param value Set to <span class='field-value'>true</span> to turn auto trimming on.
    * @return The converter object.
    */
    function setDataTrimBlocks($value) {
        $this->fields['data_trim_blocks'] = $value;
        return $this;
    }

    /**
    * Set the advanced data options:<ul><li><span class='field-value'>csv_delimiter</span> - The CSV data delimiter, the default is <span class='field-value'>,</span>.</li><li><span class='field-value'>xml_remove_root</span> - Remove the root XML element from the input data.</li><li><span class='field-value'>data_root</span> - The name of the root element inserted into the input data without a root node (e.g. CSV), the default is <span class='field-value'>data</span>.</li></ul>
    *
    * @param options Comma separated list of options.
    * @return The converter object.
    */
    function setDataOptions($options) {
        $this->fields['data_options'] = $options;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param value Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
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
    * This method can only be called after a call to one of the convertXYZ methods.
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
    * Get the version details.
    * @return API version, converter version, and client version.
    */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
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
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "html-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "html-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
    * A client certificate to authenticate Pdfcrowd converter on your web server. The certificate is used for two-way SSL/TLS authentication and adds extra security.
    *
    * @param certificate The file must be in PKCS12 format. The file must exist and not be empty.
    * @return The converter object.
    */
    function setClientCertificate($certificate) {
        if (!(filesize($certificate) > 0))
            throw new Error(create_invalid_value_message($certificate, "setClientCertificate", "html-to-image", "The file must exist and not be empty.", "set_client_certificate"), 470);
        
        $this->files['client_certificate'] = $certificate;
        return $this;
    }

    /**
    * A password for PKCS12 file with a client certificate if it is needed.
    *
    * @param password
    * @return The converter object.
    */
    function setClientCertificatePassword($password) {
        $this->fields['client_certificate_password'] = $password;
        return $this;
    }

    /**
    * Set the converter version. Different versions may produce different output. Choose which one provides the best output for your case.
    *
    * @param version The version identifier. Allowed values are latest, 20.10, 18.10.
    * @return The converter object.
    */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(latest|20.10|18.10)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "html-to-image", "Allowed values are latest, 20.10, 18.10.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    * Warning: Using HTTP is insecure as data sent over HTTP is not encrypted. Enable this option only if you know what you are doing.
    *
    * @param value Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be useful if you are behind some proxy or firewall.
    *
    * @param agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
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
    * Use cURL for the conversion request instead of the file_get_contents() PHP function.
    *
    * @param value Set to <span class='field-value'>true</span> to use PHP's cURL.
    * @return The converter object.
    */
    function setUseCurl($value) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
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
            throw new Error(create_invalid_value_message($url, "convertUrl", "image-to-image", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
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
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "image-to-image", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br>  The file must exist and not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "image-to-image", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a local file and write the result to an output stream.
    *
    * @param file The path to a local file to convert.<br>  The file must exist and not be empty.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "image-to-image", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a local file and write the result to a local file.
    *
    * @param file The path to a local file to convert.<br>  The file must exist and not be empty.
    * @param file_path The output file path. The string must not be empty.
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
    * Convert the contents of an input stream.
    *
    * @param in_stream The input stream with source data.<br>
    * @return Byte array containing the conversion output.
    */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert the contents of an input stream and write the result to an output stream.
    *
    * @param in_stream The input stream with source data.<br>
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert the contents of an input stream and write the result to a local file.
    *
    * @param in_stream The input stream with source data.<br>
    * @param file_path The output file path. The string must not be empty.
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
    * The format of the output file.
    *
    * @param output_format Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.
    * @return The converter object.
    */
    function setOutputFormat($output_format) {
        if (!preg_match("/(?i)^(png|jpg|gif|tiff|bmp|ico|ppm|pgm|pbm|pnm|psb|pct|ras|tga|sgi|sun|webp)$/", $output_format))
            throw new Error(create_invalid_value_message($output_format, "setOutputFormat", "image-to-image", "Allowed values are png, jpg, gif, tiff, bmp, ico, ppm, pgm, pbm, pnm, psb, pct, ras, tga, sgi, sun, webp.", "set_output_format"), 470);
        
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
    * @param value Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
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
    * This method can only be called after a call to one of the convertXYZ methods.
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
    * Get the version details.
    * @return API version, converter version, and client version.
    */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
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
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "image-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "image-to-image", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
    * Set the converter version. Different versions may produce different output. Choose which one provides the best output for your case.
    *
    * @param version The version identifier. Allowed values are latest, 20.10, 18.10.
    * @return The converter object.
    */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(latest|20.10|18.10)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "image-to-image", "Allowed values are latest, 20.10, 18.10.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    * Warning: Using HTTP is insecure as data sent over HTTP is not encrypted. Enable this option only if you know what you are doing.
    *
    * @param value Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be useful if you are behind some proxy or firewall.
    *
    * @param agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
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
    * Use cURL for the conversion request instead of the file_get_contents() PHP function.
    *
    * @param value Set to <span class='field-value'>true</span> to use PHP's cURL.
    * @return The converter object.
    */
    function setUseCurl($value) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
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
            throw new Error(create_invalid_value_message($action, "setAction", "pdf-to-pdf", "Allowed values are join, shuffle.", "set_action"), 470);
        
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
            throw new Error(create_invalid_value_message($file_path, "convertToFile", "pdf-to-pdf", "The string must not be empty.", "convert_to_file"), 470);
        
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
            throw new Error(create_invalid_value_message($file_path, "addPdfFile", "pdf-to-pdf", "The file must exist and not be empty.", "add_pdf_file"), 470);
        
        $this->files['f_' . $this->file_id] = $file_path;
        $this->file_id++;
        return $this;
    }

    /**
    * Add in-memory raw PDF data to the list of the input PDFs.<br>Typical usage is for adding PDF created by another Pdfcrowd converter.<br><br> Example in PHP:<br> <b>$clientPdf2Pdf</b>-&gt;addPdfRawData(<b>$clientHtml2Pdf</b>-&gt;convertUrl('http://www.example.com'));
    *
    * @param data The raw PDF data. The input data must be PDF content.
    * @return The converter object.
    */
    function addPdfRawData($data) {
        if (!($data != null && strlen($data) > 300 && substr($data, 0, 4) == '%PDF'))
            throw new Error(create_invalid_value_message("raw PDF data", "addPdfRawData", "pdf-to-pdf", "The input data must be PDF content.", "add_pdf_raw_data"), 470);
        
        $this->raw_data['f_' . $this->file_id] = $data;
        $this->file_id++;
        return $this;
    }

    /**
    * Apply the first page of the watermark PDF to every page of the output PDF.
    *
    * @param watermark The file path to a local watermark PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setPageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setPageWatermark", "pdf-to-pdf", "The file must exist and not be empty.", "set_page_watermark"), 470);
        
        $this->files['page_watermark'] = $watermark;
        return $this;
    }

    /**
    * Load a watermark PDF from the specified URL and apply the first page of the watermark PDF to every page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setPageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageWatermarkUrl", "pdf-to-pdf", "The supported protocols are http:// and https://.", "set_page_watermark_url"), 470);
        
        $this->fields['page_watermark_url'] = $url;
        return $this;
    }

    /**
    * Apply each page of the specified watermark PDF to the corresponding page of the output PDF.
    *
    * @param watermark The file path to a local watermark PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setMultipageWatermark($watermark) {
        if (!(filesize($watermark) > 0))
            throw new Error(create_invalid_value_message($watermark, "setMultipageWatermark", "pdf-to-pdf", "The file must exist and not be empty.", "set_multipage_watermark"), 470);
        
        $this->files['multipage_watermark'] = $watermark;
        return $this;
    }

    /**
    * Load a watermark PDF from the specified URL and apply each page of the specified watermark PDF to the corresponding page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setMultipageWatermarkUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageWatermarkUrl", "pdf-to-pdf", "The supported protocols are http:// and https://.", "set_multipage_watermark_url"), 470);
        
        $this->fields['multipage_watermark_url'] = $url;
        return $this;
    }

    /**
    * Apply the first page of the specified PDF to the background of every page of the output PDF.
    *
    * @param background The file path to a local background PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setPageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setPageBackground", "pdf-to-pdf", "The file must exist and not be empty.", "set_page_background"), 470);
        
        $this->files['page_background'] = $background;
        return $this;
    }

    /**
    * Load a background PDF from the specified URL and apply the first page of the background PDF to every page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setPageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setPageBackgroundUrl", "pdf-to-pdf", "The supported protocols are http:// and https://.", "set_page_background_url"), 470);
        
        $this->fields['page_background_url'] = $url;
        return $this;
    }

    /**
    * Apply each page of the specified PDF to the background of the corresponding page of the output PDF.
    *
    * @param background The file path to a local background PDF file. The file must exist and not be empty.
    * @return The converter object.
    */
    function setMultipageBackground($background) {
        if (!(filesize($background) > 0))
            throw new Error(create_invalid_value_message($background, "setMultipageBackground", "pdf-to-pdf", "The file must exist and not be empty.", "set_multipage_background"), 470);
        
        $this->files['multipage_background'] = $background;
        return $this;
    }

    /**
    * Load a background PDF from the specified URL and apply each page of the specified background PDF to the corresponding page of the output PDF.
    *
    * @param url The supported protocols are http:// and https://.
    * @return The converter object.
    */
    function setMultipageBackgroundUrl($url) {
        if (!preg_match("/(?i)^https?:\/\/.*$/", $url))
            throw new Error(create_invalid_value_message($url, "setMultipageBackgroundUrl", "pdf-to-pdf", "The supported protocols are http:// and https://.", "set_multipage_background_url"), 470);
        
        $this->fields['multipage_background_url'] = $url;
        return $this;
    }

    /**
    * Create linearized PDF. This is also known as Fast Web View.
    *
    * @param value Set to <span class='field-value'>true</span> to create linearized PDF.
    * @return The converter object.
    */
    function setLinearize($value) {
        $this->fields['linearize'] = $value;
        return $this;
    }

    /**
    * Encrypt the PDF. This prevents search engines from indexing the contents.
    *
    * @param value Set to <span class='field-value'>true</span> to enable PDF encryption.
    * @return The converter object.
    */
    function setEncrypt($value) {
        $this->fields['encrypt'] = $value;
        return $this;
    }

    /**
    * Protect the PDF with a user password. When a PDF has a user password, it must be supplied in order to view the document and to perform operations allowed by the access permissions.
    *
    * @param password The user password.
    * @return The converter object.
    */
    function setUserPassword($password) {
        $this->fields['user_password'] = $password;
        return $this;
    }

    /**
    * Protect the PDF with an owner password.  Supplying an owner password grants unlimited access to the PDF including changing the passwords and access permissions.
    *
    * @param password The owner password.
    * @return The converter object.
    */
    function setOwnerPassword($password) {
        $this->fields['owner_password'] = $password;
        return $this;
    }

    /**
    * Disallow printing of the output PDF.
    *
    * @param value Set to <span class='field-value'>true</span> to set the no-print flag in the output PDF.
    * @return The converter object.
    */
    function setNoPrint($value) {
        $this->fields['no_print'] = $value;
        return $this;
    }

    /**
    * Disallow modification of the output PDF.
    *
    * @param value Set to <span class='field-value'>true</span> to set the read-only only flag in the output PDF.
    * @return The converter object.
    */
    function setNoModify($value) {
        $this->fields['no_modify'] = $value;
        return $this;
    }

    /**
    * Disallow text and graphics extraction from the output PDF.
    *
    * @param value Set to <span class='field-value'>true</span> to set the no-copy flag in the output PDF.
    * @return The converter object.
    */
    function setNoCopy($value) {
        $this->fields['no_copy'] = $value;
        return $this;
    }

    /**
    * Specify the page layout to be used when the document is opened.
    *
    * @param layout Allowed values are single-page, one-column, two-column-left, two-column-right.
    * @return The converter object.
    */
    function setPageLayout($layout) {
        if (!preg_match("/(?i)^(single-page|one-column|two-column-left|two-column-right)$/", $layout))
            throw new Error(create_invalid_value_message($layout, "setPageLayout", "pdf-to-pdf", "Allowed values are single-page, one-column, two-column-left, two-column-right.", "set_page_layout"), 470);
        
        $this->fields['page_layout'] = $layout;
        return $this;
    }

    /**
    * Specify how the document should be displayed when opened.
    *
    * @param mode Allowed values are full-screen, thumbnails, outlines.
    * @return The converter object.
    */
    function setPageMode($mode) {
        if (!preg_match("/(?i)^(full-screen|thumbnails|outlines)$/", $mode))
            throw new Error(create_invalid_value_message($mode, "setPageMode", "pdf-to-pdf", "Allowed values are full-screen, thumbnails, outlines.", "set_page_mode"), 470);
        
        $this->fields['page_mode'] = $mode;
        return $this;
    }

    /**
    * Specify how the page should be displayed when opened.
    *
    * @param zoom_type Allowed values are fit-width, fit-height, fit-page.
    * @return The converter object.
    */
    function setInitialZoomType($zoom_type) {
        if (!preg_match("/(?i)^(fit-width|fit-height|fit-page)$/", $zoom_type))
            throw new Error(create_invalid_value_message($zoom_type, "setInitialZoomType", "pdf-to-pdf", "Allowed values are fit-width, fit-height, fit-page.", "set_initial_zoom_type"), 470);
        
        $this->fields['initial_zoom_type'] = $zoom_type;
        return $this;
    }

    /**
    * Display the specified page when the document is opened.
    *
    * @param page Must be a positive integer number.
    * @return The converter object.
    */
    function setInitialPage($page) {
        if (!(intval($page) > 0))
            throw new Error(create_invalid_value_message($page, "setInitialPage", "pdf-to-pdf", "Must be a positive integer number.", "set_initial_page"), 470);
        
        $this->fields['initial_page'] = $page;
        return $this;
    }

    /**
    * Specify the initial page zoom in percents when the document is opened.
    *
    * @param zoom Must be a positive integer number.
    * @return The converter object.
    */
    function setInitialZoom($zoom) {
        if (!(intval($zoom) > 0))
            throw new Error(create_invalid_value_message($zoom, "setInitialZoom", "pdf-to-pdf", "Must be a positive integer number.", "set_initial_zoom"), 470);
        
        $this->fields['initial_zoom'] = $zoom;
        return $this;
    }

    /**
    * Specify whether to hide the viewer application's tool bars when the document is active.
    *
    * @param value Set to <span class='field-value'>true</span> to hide tool bars.
    * @return The converter object.
    */
    function setHideToolbar($value) {
        $this->fields['hide_toolbar'] = $value;
        return $this;
    }

    /**
    * Specify whether to hide the viewer application's menu bar when the document is active.
    *
    * @param value Set to <span class='field-value'>true</span> to hide the menu bar.
    * @return The converter object.
    */
    function setHideMenubar($value) {
        $this->fields['hide_menubar'] = $value;
        return $this;
    }

    /**
    * Specify whether to hide user interface elements in the document's window (such as scroll bars and navigation controls), leaving only the document's contents displayed.
    *
    * @param value Set to <span class='field-value'>true</span> to hide ui elements.
    * @return The converter object.
    */
    function setHideWindowUi($value) {
        $this->fields['hide_window_ui'] = $value;
        return $this;
    }

    /**
    * Specify whether to resize the document's window to fit the size of the first displayed page.
    *
    * @param value Set to <span class='field-value'>true</span> to resize the window.
    * @return The converter object.
    */
    function setFitWindow($value) {
        $this->fields['fit_window'] = $value;
        return $this;
    }

    /**
    * Specify whether to position the document's window in the center of the screen.
    *
    * @param value Set to <span class='field-value'>true</span> to center the window.
    * @return The converter object.
    */
    function setCenterWindow($value) {
        $this->fields['center_window'] = $value;
        return $this;
    }

    /**
    * Specify whether the window's title bar should display the document title. If false , the title bar should instead display the name of the PDF file containing the document.
    *
    * @param value Set to <span class='field-value'>true</span> to display the title.
    * @return The converter object.
    */
    function setDisplayTitle($value) {
        $this->fields['display_title'] = $value;
        return $this;
    }

    /**
    * Set the predominant reading order for text to right-to-left. This option has no direct effect on the document's contents or page numbering but can be used to determine the relative positioning of pages when displayed side by side or printed n-up
    *
    * @param value Set to <span class='field-value'>true</span> to set right-to-left reading order.
    * @return The converter object.
    */
    function setRightToLeft($value) {
        $this->fields['right_to_left'] = $value;
        return $this;
    }

    /**
    * Turn on the debug logging. Details about the conversion are stored in the debug log. The URL of the log can be obtained from the <a href='#get_debug_log_url'>getDebugLogUrl</a> method or available in <a href='/user/account/log/conversion/'>conversion statistics</a>.
    *
    * @param value Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
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
    * This method can only be called after a call to one of the convertXYZ methods.
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
    * Get the version details.
    * @return API version, converter version, and client version.
    */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
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
    * Set the converter version. Different versions may produce different output. Choose which one provides the best output for your case.
    *
    * @param version The version identifier. Allowed values are latest, 20.10, 18.10.
    * @return The converter object.
    */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(latest|20.10|18.10)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "pdf-to-pdf", "Allowed values are latest, 20.10, 18.10.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    * Warning: Using HTTP is insecure as data sent over HTTP is not encrypted. Enable this option only if you know what you are doing.
    *
    * @param value Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be useful if you are behind some proxy or firewall.
    *
    * @param agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
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
    * Use cURL for the conversion request instead of the file_get_contents() PHP function.
    *
    * @param value Set to <span class='field-value'>true</span> to use PHP's cURL.
    * @return The converter object.
    */
    function setUseCurl($value) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
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
            throw new Error(create_invalid_value_message($url, "convertUrl", "image-to-pdf", "The supported protocols are http:// and https://.", "convert_url"), 470);
        
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
            throw new Error(create_invalid_value_message($url, "convertUrlToStream::url", "image-to-pdf", "The supported protocols are http:// and https://.", "convert_url_to_stream"), 470);
        
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
    * Convert a local file.
    *
    * @param file The path to a local file to convert.<br>  The file must exist and not be empty.
    * @return Byte array containing the conversion output.
    */
    function convertFile($file) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFile", "image-to-pdf", "The file must exist and not be empty.", "convert_file"), 470);
        
        $this->files['file'] = $file;
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert a local file and write the result to an output stream.
    *
    * @param file The path to a local file to convert.<br>  The file must exist and not be empty.
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertFileToStream($file, $out_stream) {
        if (!(filesize($file) > 0))
            throw new Error(create_invalid_value_message($file, "convertFileToStream::file", "image-to-pdf", "The file must exist and not be empty.", "convert_file_to_stream"), 470);
        
        $this->files['file'] = $file;
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert a local file and write the result to a local file.
    *
    * @param file The path to a local file to convert.<br>  The file must exist and not be empty.
    * @param file_path The output file path. The string must not be empty.
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
    * Convert the contents of an input stream.
    *
    * @param in_stream The input stream with source data.<br>
    * @return Byte array containing the conversion output.
    */
    function convertStream($in_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        return $this->helper->post($this->fields, $this->files, $this->raw_data);
    }

    /**
    * Convert the contents of an input stream and write the result to an output stream.
    *
    * @param in_stream The input stream with source data.<br>
    * @param out_stream The output stream that will contain the conversion output.
    */
    function convertStreamToStream($in_stream, $out_stream) {
        $this->raw_data['stream'] = stream_get_contents($in_stream);
        $this->helper->post($this->fields, $this->files, $this->raw_data, $out_stream);
    }

    /**
    * Convert the contents of an input stream and write the result to a local file.
    *
    * @param in_stream The input stream with source data.<br>
    * @param file_path The output file path. The string must not be empty.
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
    * @param value Set to <span class='field-value'>true</span> to enable the debug logging.
    * @return The converter object.
    */
    function setDebugLog($value) {
        $this->fields['debug_log'] = $value;
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
    * This method can only be called after a call to one of the convertXYZ methods.
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
    * Get the version details.
    * @return API version, converter version, and client version.
    */
    function getVersion() {
        return 'client '.ConnectionHelper::CLIENT_VERSION.', API v2, converter '.$this->helper->getConverterVersion();
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
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpProxy", "image-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_http_proxy"), 470);
        
        $this->fields['http_proxy'] = $proxy;
        return $this;
    }

    /**
    * A proxy server used by Pdfcrowd conversion process for accessing the source URLs with HTTPS scheme. It can help to circumvent regional restrictions or provide limited access to your intranet.
    *
    * @param proxy The value must have format DOMAIN_OR_IP_ADDRESS:PORT.
    * @return The converter object.
    */
    function setHttpsProxy($proxy) {
        if (!preg_match("/(?i)^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z0-9]{1,}:\d+$/", $proxy))
            throw new Error(create_invalid_value_message($proxy, "setHttpsProxy", "image-to-pdf", "The value must have format DOMAIN_OR_IP_ADDRESS:PORT.", "set_https_proxy"), 470);
        
        $this->fields['https_proxy'] = $proxy;
        return $this;
    }

    /**
    * Set the converter version. Different versions may produce different output. Choose which one provides the best output for your case.
    *
    * @param version The version identifier. Allowed values are latest, 20.10, 18.10.
    * @return The converter object.
    */
    function setConverterVersion($version) {
        if (!preg_match("/(?i)^(latest|20.10|18.10)$/", $version))
            throw new Error(create_invalid_value_message($version, "setConverterVersion", "image-to-pdf", "Allowed values are latest, 20.10, 18.10.", "set_converter_version"), 470);
        
        $this->helper->setConverterVersion($version);
        return $this;
    }

    /**
    * Specifies if the client communicates over HTTP or HTTPS with Pdfcrowd API.
    * Warning: Using HTTP is insecure as data sent over HTTP is not encrypted. Enable this option only if you know what you are doing.
    *
    * @param value Set to <span class='field-value'>true</span> to use HTTP.
    * @return The converter object.
    */
    function setUseHttp($value) {
        $this->helper->setUseHttp($value);
        return $this;
    }

    /**
    * Set a custom user agent HTTP header. It can be useful if you are behind some proxy or firewall.
    *
    * @param agent The user agent string.
    * @return The converter object.
    */
    function setUserAgent($agent) {
        $this->helper->setUserAgent($agent);
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
    * Use cURL for the conversion request instead of the file_get_contents() PHP function.
    *
    * @param value Set to <span class='field-value'>true</span> to use PHP's cURL.
    * @return The converter object.
    */
    function setUseCurl($value) {
        $this->helper->setUseCurl($use_curl);
        return $this;
    }

    /**
    * Specifies the number of retries when the 502 HTTP status code is received. The 502 status code indicates a temporary network issue. This feature can be disabled by setting to 0.
    *
    * @param count Number of retries wanted.
    * @return The converter object.
    */
    function setRetryCount($count) {
        $this->helper->setRetryCount($count);
        return $this;
    }

}


}

?>
