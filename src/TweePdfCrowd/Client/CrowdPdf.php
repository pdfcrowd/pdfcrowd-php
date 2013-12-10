<?php
namespace TweePdfCrowd\Client;
use InvalidArgumentException;

class CrowdPdf
{
    // constants for setPageMode()
    const NONE_VISIBLE = 1;
    const THUMBNAILS_VISIBLE = 2;
    const FULLSCREEN = 3;

    // constants for setPageLayout()
    const SINGLE_PAGE = 1;
    const CONTINUOUS = 2;
    const CONTINUOUS_FACING = 3;

    private $fields, $scheme, $port, $api_prefix;

    public static $http_port  = 80;
    public static $https_port = 443;
    public static $api_host   = 'pdfcrowd.com';


    //
    // Pdfcrowd constructor.
    //
    // $username - your username at Pdfcrowd
    // $apikey  - your API key
    // $hostname - API hostname, defaults to pdfcrowd.com
    //
    public function __construct($username, $apikey, $hostname=null)
    {
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
    public function convertHtml($src, $outstream=null)
    {
        if (!$src) {
            throw new InvalidArgumentException("convertHTML(): the src parameter must not be empty");
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
    public function convertFile($src, $outstream=null)
    {
        $src = trim($src);

        if (!file_exists($src)) {
            $cwd = getcwd();
            throw new InvalidArgumentException("convertFile(): '{$src}' not found");
        }

        if (is_dir($src)) {
            throw new InvalidArgumentException("convertFile(): '{$src}' must be file, not a directory");
        }

        if (!is_readable($src)) {
            throw new InvalidArgumentException("convertFile(): cannot read '{$src}', please check if the process has sufficient permissions");
        }

        if (!filesize($src)) {
            throw new InvalidArgumentException("convertFile(): '{$src}' must not be empty");
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
    public function convertURI($src, $outstream=null)
    {
        $src = trim($src);
        if (!preg_match("/^https?:\/\/.*/i", $src)) {
            throw new InvalidArgumentException("convertURI(): the URL must start with http:// or https:// (got '$src')");
        }

        $this->fields['src'] = $src;
        $uri = $this->api_prefix . "/pdf/convert/uri/";
        $postfields = http_build_query($this->fields, '', '&');

        return $this->http_post($uri, $postfields, $outstream);
    }

    //
    // Returns the number of available conversion tokens.
    //
    public function numTokens()
    {
        $username = $this->fields['username'];
        $uri = $this->api_prefix . "/user/{$username}/tokens/";
        $arr = array('username' => $this->fields['username'],
                     'key' => $this->fields['key']);
        $postfields = http_build_query($arr, '', '&');
        $ntokens = $this->http_post($uri, $postfields, NULL);

        return (int) $ntokens;
    }

    public function useSSL($use_ssl)
    {
        if ($use_ssl) {
            $this->port = self::$https_port;
            $this->scheme = 'https';
        } else {
            $this->port = self::$http_port;
            $this->scheme = 'http';
        }

        $this->api_prefix = "{$this->scheme}://{$this->hostname}/api";
    }

    public function setPageWidth($value)
    {
        $this->fields['width'] = $value;
    }

    public function setPageHeight($value)
    {
        $this->fields['height'] = $value;
    }

    public function setHorizontalMargin($value)
    {
        $this->fields['margin_right'] = $this->fields['margin_left'] = $value;
    }

    public function setVerticalMargin($value)
    {
        $this->fields['margin_top'] = $this->fields['margin_bottom'] = $value;
    }

    public function setPageMargins($top, $right, $bottom, $left)
    {
      $this->fields['margin_top'] = $top;
      $this->fields['margin_right'] = $right;
      $this->fields['margin_bottom'] = $bottom;
      $this->fields['margin_left'] = $left;
    }

    public function setEncrypted($val=true)
    {
        $this->set_or_unset($val, 'encrypted');
    }

    public function setUserPassword($pwd)
    {
        $this->set_or_unset($pwd, 'user_pwd');
    }

    public function setOwnerPassword($pwd)
    {
        $this->set_or_unset($pwd, 'owner_pwd');
    }

    public function setNoPrint($val=true)
    {
        $this->set_or_unset($val, 'no_print');
    }

    public function setNoModify($val=true)
    {
        $this->set_or_unset($val, 'no_modify');
    }

    public function setNoCopy($val=true)
    {
        $this->set_or_unset($val, 'no_copy');
    }

    public function setPageLayout($value)
    {
        assert($value > 0 && $value <= 3);
        $this->fields['page_layout'] = $value;
    }

    public function setPageMode($value)
    {
        assert($value > 0 && $value <= 3);
        $this->fields['page_mode'] = $value;
    }

    public function setFooterText($value)
    {
        $this->set_or_unset($value, 'footer_text');
    }

    public function enableImages($value=true)
    {
        $this->set_or_unset(!$value, 'no_images');
    }

    public function enableBackgrounds($value=true)
    {
        $this->set_or_unset(!$value, 'no_backgrounds');
    }

    public function setHtmlZoom($value)
    {
        $this->set_or_unset($value, 'html_zoom');
    }

    public function enableJavaScript($value=true)
    {
        $this->set_or_unset(!$value, 'no_javascript');
    }

    public function enableHyperlinks($value=true)
    {
        $this->set_or_unset(!$value, 'no_hyperlinks');
    }

    public function setDefaultTextEncoding($value)
    {
        $this->set_or_unset($value, 'text_encoding');
    }

    public function usePrintMedia($value=true)
    {
        $this->set_or_unset($value, 'use_print_media');
    }

    public function setMaxPages($value)
    {
        $this->fields['max_pages'] = $value;
    }

    public function enablePdfcrowdLogo($value=true)
    {
        $this->set_or_unset($value, 'pdfcrowd_logo');
    }

    // constants for setInitialPdfZoomType()
    const FIT_WIDTH = 1;
    const FIT_HEIGHT = 2;
    const FIT_PAGE = 3;

    public function setInitialPdfZoomType($value)
    {
        assert($value>0 && $value<=3);
        $this->fields['initial_pdf_zoom_type'] = $value;
    }

    public function setInitialPdfExactZoom($value)
    {
        $this->fields['initial_pdf_zoom_type'] = 4;
        $this->fields['initial_pdf_zoom'] = $value;
    }

    public function setPdfScalingFactor($value)
    {
        $this->fields['pdf_scaling_factor'] = $value;
    }

    public function setAuthor($value)
    {
        $this->fields['author'] = $value;
    }

    public function setFailOnNon200($value)
    {
        $this->fields['fail_on_non200'] = $value;
    }

    public function setFooterHtml($value)
    {
        $this->fields['footer_html'] = $value;
    }

    public function setFooterUrl($value)
    {
        $this->fields['footer_url'] = $value;
    }

    public function setHeaderHtml($value)
    {
        $this->fields['header_html'] = $value;
    }

    public function setHeaderUrl($value)
    {
        $this->fields['header_url'] = $value;
    }

    public function setPageBackgroundColor($value)
    {
        $this->fields['page_background_color'] = $value;
    }

    public function setTransparentBackground($value=true)
    {
        $this->set_or_unset($value, 'transparent_background');
    }

    public function setPageNumberingOffset($value)
    {
        $this->fields['page_numbering_offset'] = $value;
    }

    public function setHeaderFooterPageExcludeList($value)
    {
        $this->fields['header_footer_page_exclude_list'] = $value;
    }

    public function setWatermark($url, $offset_x=0, $offset_y=0)
    {
        $this->fields["watermark_url"] = $url;
        $this->fields["watermark_offset_x"] = $offset_x;
        $this->fields["watermark_offset_y"] = $offset_y;
    }

    public function setWatermarkRotation($angle)
    {
        $this->fields["watermark_rotation"] = $angle;
    }

    public function setWatermarkInBackground($val=true)
    {
        $this->set_or_unset($val, "watermark_in_background");
    }

    // ----------------------------------------------------------------------
    //
    //                        Private stuff
    //


    private function http_post($url, $postfields, $outstream)
    {
        if (!function_exists("curl_init")) {
            throw new InvalidArgumentException('pdfcrowd.php requires cURL which is not installed on your system');
        }

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL,$url);
        curl_setopt($c, CURLOPT_HEADER, false);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_PORT, $this->port);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($c, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        //curl_setopt($c, CURLOPT_USERAGENT, $this->user_agent);
        if ($outstream) {
            $this->outstream = $outstream;
            curl_setopt($c, CURLOPT_WRITEFUNCTION, array($this, 'receive_to_stream'));
        }

        if ($this->scheme == 'https' && self::$api_host == 'pdfcrowd.com') {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        }

        $this->http_code = 0;
        $this->error = "";

        $response = curl_exec($c);
        $this->http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $error_str = curl_error($c);
        $error_nr = curl_errno($c);
        curl_close($c);

        if ($error_nr != 0) {
            throw new InvalidArgumentException($error_str, $error_nr);
        } elseif ($this->http_code == 200) {
            if ($outstream == NULL) {
                return $response;
            }
        } else {
            throw new InvalidArgumentException($this->error ? $this->error : $response, $this->http_code);
        }
    }

    private function receive_to_stream($curl, $data)
    {
        if ($this->http_code == 0) {
            $this->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        }

        if ($this->http_code >= 400) {
            $this->error = $this->error . $data;

            return strlen($data);
        }

        $written = fwrite($this->outstream, $data);
        if ($written != strlen($data)) {
                throw new InvalidArgumentException('Writing the PDF file failed. The disk may be full.');
            }
        }

        return $written;
    }

    private function set_or_unset($val, $field)
    {
        if ($val)
            $this->fields[$field] = $val;
        else
            unset($this->fields[$field]);
    }
}