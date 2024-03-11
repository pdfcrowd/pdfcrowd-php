Change Log
==========

5.18.0
------

- NEW: setSplitLigatures method for PdfToHtml API client

5.17.0
------

- NEW: setImageFormat method for PdfToHtml API client
- NEW: mode value 'none' for setImageMode method for PdfToHtml API client

5.16.0
------

- NEW: setDpi method for PdfToHtml API client

5.15.0
------

- NEW: setMaxLoadingTime method for HtmlToPdf and HtmlToImage API clients

5.14.0
------

- NEW: setCustomCss method for HtmlToPdf and HtmlToImage API clients

5.13.1
------

- FIX: resolve deprecation warnings for PHP 8.2.7

5.13.0
------

- NEW: setRemoveBlankPages method for HtmlToPdf API client

5.12.1
------

- FIX: replace deprecated string interpolation

5.12.1
------

- FIX: retry conversion on error 503

5.12.0
------

- NEW: setCropAreaX, setCropAreaY, setCropAreaWidth, setCropAreaHeight and setRemoveBorders methods for ImageToImage and ImageToPdf API client

5.11.0
------

- NEW: canvas setup options for ImageToImage API client
- NEW: page setup, watermark/background and output options for ImageToPdf API client
- FIX: rotation for ImageToPdf API client

5.10.0
------

- NEW: PdftoText API client for the converion from PDF to plain text
- NEW: ImageToPdf API client supports watermarks, backgrounds and PDF format options
- NEW: single-page-fit-ex mode for setSmartScalingMode API method

5.9.0
-----

- NEW: getTotalPageCount to get the total page count of the source document
- NEW: readability-v4 mode for setReadabilityEnhancements API method

5.8.0
-----

- images can be used as a watermark and a background in HTML to PDF and PDF to PDF converter

5.7.0
-----

- NEW: readability-v2 and readability-v3 modes for setReadabilityEnhancements API method

5.6.2
-----

- minor update of documentation links

5.6.1
-----

- minor update of the text of the error message

5.6.0
-----

- NEW: setEnablePdfForms to convert HTML forms to fillable PDF forms
- minor update of the text of the error message

5.5.0
-----

- NEW: setAutodetectElementToConvert to detect the main HTML element for conversion
- NEW: setReadabilityEnhancements to enhance the input HTML to improve the readability
- NEW: pdfcrowd-source-title CSS class available for header and footer HTML

5.4.0
-----

- NEW: PDF to HTML converter
- NEW: setInputPdfPassword for PDF to PDF converter

5.3.0
-----

- NEW: setUseMobileUserAgent for HTML to PDF and HTML to Image

5.2.2
-----

- FIX: the hyperlinks to the Pdfcrowd API documentation have been updated

5.2.1
-----

- FIX: methods for getting conversion info (e.g. getRemainingCreditCount)

5.2.0
-----

- NEW: setZipMainFilename
- NEW: setZipHeaderFilename
- NEW: setZipFooterFilename
- NEW: setExtractMetaTags
- NEW: setTitle for PDF to PDF
- NEW: setSubject for PDF to PDF
- NEW: setAuthor for PDF to PDF
- NEW: setKeywords for PDF to PDF

5.1.1
-----

- minor documentation updates

5.1.0
-----

- NEW: convertStream
- NEW: convertStreamToStream
- NEW: convertStreamToFile
- FIX: The main window scrollbar is not rendered in HTML to Image outputs.

5.0.0
-----

- NEW: converter version 20.10 supported
- NEW: setConverterVersion
- NEW: setLoadIframes
- NEW: setLocale
- NEW: setNoHeaderFooterHorizontalMargins
- NEW: setCssPageRuleMode
- NEW: setLayoutDpi
- NEW: setContentsMatrix
- NEW: setHeaderMatrix
- NEW: setFooterMatrix
- NEW: setDisablePageHeightOptimization
- NEW: setHeaderFooterCssAnnotation
- NEW: setMainDocumentCssAnnotation
- NEW: setBackgroundColor for HtmlToImage

Older versions
--------------

- Details on the [Pdfcrowd blog](https://pdfcrowd.com/blog/).

