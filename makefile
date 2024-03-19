VERSION = 5.18.1
PHP ?= php
DIR_NAME := pdfcrowd-5.18.1

dist: dist/pdfcrowd-$(VERSION)-php.zip

dist/pdfcrowd-$(VERSION)-php.zip:
	@mkdir -p dist
	@cd dist && mkdir -p $(DIR_NAME) && cp ../pdfcrowd.php $(DIR_NAME) && zip pdfcrowd-$(VERSION)-php.zip $(DIR_NAME)/*

publish:
	curl -XPOST -H'content-type:application/json' "https://packagist.org/api/update-package?username=Pdfcrowd&apiToken=$(API_TOKEN)" -d'{"repository":{"url":"https://github.com/pdfcrowd/pdfcrowd-php"}}'

.PHONY: clean
clean:
	rm -rf dist/* ./test_files/out/php_*.pdf
