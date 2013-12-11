# Pdfcrowd HTML to PDF API client

The Pdfcrowd API lets you easily create PDF from web pages or raw HTML
code in your PHP applications.

To use the API, you need an account on
[http://pdfcrowd.com](https://pdfcrowd.com), if you don't have one you
can sign up [here](https://pdfcrowd.com/pricing/api/). This will give
you a username and an API key.

## Installation

1. Add this to your composer.json:

```json
"require": {
    "necromant2005/pdfcrowd-php": "@dev",
}
```

2. Now tell composer to download TweeGearmanStat by running the command:

```bash
$ php composer.phar update
```

## Example

Server side PDF generation. This code converts a web page and sends
the generated PDF to the browser (don't forget to use your `"username"`
and `"apikey"`):

    use TweePdfCrowd\Client\CrowdPdf;
    use Exception;
    
    try
    {   
        // create an API client instance
        $client = new Pdfcrowd("username", "apikey");
    
        // convert a web page and store the generated PDF into a $pdf variable
        $pdf = $client->convertURI('http://example.com/');
    
        // set HTTP response headers
        header("Content-Type: application/pdf");
        header("Cache-Control: no-cache");
        header("Accept-Ranges: none");
        header("Content-Disposition: attachment; filename=\"created.pdf\"");
    
        // send the generated PDF 
        echo $pdf;
    }
    catch(Exception $e)
    {
        echo "Pdfcrowd Error: " . $e->getMessage();
    }


Other basic operations:

    // convert an HTML string
    $html = "<html><body>In-memory HTML.</body></html>";
    $pdf = $client->convertHtml($html);

    // convert an HTML file
    $pdf = $client->convertFile('/path/to/local/file.html');


