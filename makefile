VERSION = 1.6

all:

dist: dist/pdfcrowd-$(VERSION)-php.zip

dist/pdfcrowd-$(VERSION)-php.zip:
	mkdir -p dist
	zip dist/pdfcrowd-$(VERSION)-php.zip pdfcrowd.php

test:
	php test.php $(API_USERNAME) $(API_TOKEN) $(API_HOSTNAME) $(API_HTTP_PORT) $(API_HTTPS_PORT)

.PHONY: clean
clean:
	rm -rf dist/* 0

