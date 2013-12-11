<?php
namespace TweePdfCrowd\Client;
use InvalidArgumentException;

class PdfCrowd
{
    const API_PREFIX 'http://pdfcrowd.com/api/';

    // constants for setPageMode()
    const NONE_VISIBLE = 1;
    const THUMBNAILS_VISIBLE = 2;
    const FULLSCREEN = 3;

    // constants for setPageLayout()
    const SINGLE_PAGE = 1;
    const CONTINUOUS = 2;
    const CONTINUOUS_FACING = 3;

    // constants for setInitialPdfZoomType()
    const FIT_WIDTH = 1;
    const FIT_HEIGHT = 2;
    const FIT_PAGE = 3;

    private $options = array(
        'pdf_scaling_factor' => 1,
        'html_zoom'          => 200,
        // 'width'
        // 'height'
        // 'margin_right'
        // 'margin_left'
        // 'margin_top'
        // 'margin_bottom'
        // 'author'
        // 'encrypted'
        // 'user_pwd'
        // 'owner_pwd',
        // 'no_print',
        // 'no_modify',
        // 'no_copy'
        // 'page_layout',
        // 'page_mode',
        // 'page_background_color',
        // 'page_numbering_offset',
        // 'max_pages',
        // 'footer_text',
        // 'footer_text',
        // 'footer_url',
        // 'header_html',
        // 'header_url',
        // 'header_footer_page_exclude_list',
        // 'transparent_background',
        // 'no_images',
        // 'no_backgrounds',
        // 'no_javascript',
        // 'no_hyperlinks',
        // 'html_zoom',
        // 'text_encoding',
        // 'use_print_media',
        // 'initial_pdf_zoom_type',
        // 'initial_pdf_zoom',
        // 'pdf_scaling_factor',
        // 'watermark_url',
        // 'watermark_rotation',
        // 'watermark_offset_x',
        // 'watermark_offset_y',
        // 'watermark_in_background',
    );

    /**
     * Constructor
     *
     * @param  array $options Sample array('username' => 'xxx', 'apikey' => 'yyy')
     */
    public function __construct(array $options)
    {
        $this->options = array(
            'username' => $username,
            'key' => $apikey,

        );
    }

    /**
     * Set options
     *
     * @param  array $options Sample array('username' => 'xxx', 'apikey' => 'yyy')
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Converts an in-memory html document
     *
     * @param string $src String containing a html document
     * @param stream|null $outstream Output stream, if null then the return value is a string containing the PDF
     */
    public function convertHtml($src, $outstream=null)
    {
        if (!$src) {
            throw new InvalidArgumentException("convertHTML(): the src parameter must not be empty");
        }

        $this->options['src'] = $src;
        $uri = self::API_PREFIX . "/pdf/convert/html/";
        $postfields = http_build_query($this->options, '', '&');

        return $this->request($uri, $postfields, $outstream);
    }

    /**
     * Converts an html file
     *
     * @param string $src Path to an html file
     * @param stream|null $outstream Output stream, if null then the return value is a string containing the PDF
     */
    public function convertFile($src, $outstream = null)
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

        $this->options['src'] = '@' . $src;
        $uri = self::API_PREFIX . "/pdf/convert/html/";

        return $this->request($uri, $this->options, $outstream);
    }

    /**
     * Converts a web page
     *
     * @param string $src Web page URL
     * @param stream|null $outstream Output stream, if null then the return value is a string containing the PDF
     */
    public function convertURI($src, $outstream=null)
    {
        $src = trim($src);
        if (!preg_match("/^https?:\/\/.*/i", $src)) {
            throw new InvalidArgumentException("convertURI(): the URL must start with http:// or https:// (got '$src')");
        }

        $this->options['src'] = $src;
        $uri = self::API_PREFIX . "/pdf/convert/uri/";
        $postfields = http_build_query($this->options, '', '&');

        return $this->request($uri, $postfields, $outstream);
    }

    /**
     * Returns the number of available conversion tokens.
     */
    public function numTokens()
    {
        $username = $this->options['username'];
        $uri = self::API_PREFIX . "/user/{$username}/tokens/";
        $arr = array('username' => $this->options['username'],
                     'key' => $this->options['key']);
        $postfields = http_build_query($arr, '', '&');
        $ntokens = $this->request($uri, $postfields, NULL);

        return (int) $ntokens;
    }

    protected function request($url, $postfields, $outstream)
    {
        if (!function_exists("curl_init")) {
            throw new InvalidArgumentException('pdfcrowd.php requires cURL which is not installed on your system');
        }

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_HEADER, false);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postfields);
        if ($outstream) {
            curl_setopt($c, CURLOPT_WRITEFUNCTION, function($curlHandle, $data) use ($outstream) {
                static $length = 0;
                $written = fwrite($outstream, $data);
                $length += $written;
                return $length;
            });
        }

        $response = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $errorString = curl_error($c);
        $errorNumber = curl_errno($c);
        curl_close($c);

        if ($errorNumber != 0) {
            throw new InvalidArgumentException($errorString, $errorNumber);
        }
        if ($code != 200) {
            throw new InvalidArgumentException($response, $code);
        }
        return $response;
    }
}
