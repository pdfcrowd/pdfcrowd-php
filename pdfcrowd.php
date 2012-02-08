<?php

// Copyright (C) 2010 pdfcrowd.com
//
// Inspired by code written by Jawaad Mahmood
//  <http://www.tokyomuslim.com/2010/04/php-class-to-run-pdfcrowd-com/>
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



//
// Thrown when an error occurs.
// 
class PdfcrowdException extends Exception {}


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
    }

    //
    // Converts an in-memory html document.
    //
    // $src       - a string containing a html document
    // $outstream - output stream, if null then the return value is a string
    //              containing the PDF
    // 
    function convertHtml($src, $outstream=null){
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
        if (!file_exists($src)){
            throw new Exception("Cannot access {$src}.");
        }
        
        $this->fields['src'] = '@' . $src;
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
        $this->fields['hmargin'] = $value;
    }
    
    function setVerticalMargin($value) {
        $this->fields['vmargin'] = $value;
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
    
    


    // ----------------------------------------------------------------------
    //
    //                        Private stuff
    //

    private $fields, $scheme, $port, $api_prefix;

    public static $http_port = 80;
    public static $https_port = 443;
    public static $api_host = 'pdfcrowd.com';

    private function http_post($url, $postfields, $outstream) {
        if (!function_exists("curl_init")) {
            throw new PdfcrowdException("pdfcrowd.php requires curl but it is not installed on your system. Please, see: http://cz.php.net/manual/en/book.curl.php\n", 0);
        }
        
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL,$url);
        curl_setopt($c, CURLOPT_HEADER, false);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_PORT, $this->port);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postfields);
        if ($outstream) {
            $this->outstream = $outstream;
            curl_setopt($c, CURLOPT_WRITEFUNCTION, array($this, 'receive_to_stream'));
        }

        if ($this->scheme == 'https' && self::$api_host == 'pdfcrowd.com') {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($c);
        $response_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $error_str = curl_error($c);
        $error_nr = curl_errno($c);
        curl_close($c);

        if ($error_nr != 0) {
            throw new PdfcrowdException($error_str, $error_nr);            
        }
        else if ($response_code == 200) {
            if ($outstream == NULL) {
                return $response;
            }
        } else {
            throw new PdfcrowdException($response, $response_code);
        }
    }

    private function receive_to_stream($curl, $data) {
        return fwrite($this->outstream, $data);
    }

    private function set_or_unset($val, $field) {
        if ($val)
            $this->fields[$field] = $val;
        else
            unset($this->fields[$field]);
    }

    
}

?>
