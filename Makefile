# Makefile for building the project

OWNCLOUD_PATH=$(CURDIR)/../..
OCC=$(OWNCLOUD_PATH)/occ

app_name=customgroups
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build
doc_files=README.md
src_files=
src_dirs=appinfo lib
all_src=$(src_files) $(src_dirs) $(doc_files)
market_dir=$(build_dir)/market

.PHONY: all
all: market

.PHONE: market
market: dist
	cd $(build_dir); tar cvzf $(app_name).tar.gz $(app_name)
	rm -Rf $(market_dir); mkdir -p $(market_dir)
	mv $(build_dir)/$(app_name).tar.gz $(market_dir)

$(build_dir)/$(app_name):
	rm -Rf $@; mkdir -p $@
	cp -R $(all_src) $@

.PHONY: dist
dist: $(build_dir)/$(app_name)

.PHONY: distclean
distclean: clean

.PHONY: clean
clean: clean-test
	rm -rf $(build_dir)

.PHONY: clean-test
clean-test:
	rm tests/unit/clover.xml
	rm tests/unit/*.phar

.PHONY: test-syntax
test-syntax:
	for F in $(shell find . -name \*.php); do \
		php -l "$$F" || exit $?; \
	done

.PHONY: test-codecheck
test-codecheck: test-syntax
	$(OCC) app:check-code $(app_name) -c private -c strong-comparison

.PHONY: test-codecheck-deprecations
test-codecheck-deprecations:
	$(OCC) app:check-code $(app_name) -c deprecation

.PHONY: test-php
test-php: test-syntax
	$(OCC) app:enable $(app_name)
	cd tests/unit && phpunit --configuration phpunit.xml

.PHONY: test-upload-coverage
test-upload-coverage:
	cd tests/unit && wget https://scrutinizer-ci.com/ocular.phar; php ocular.phar code-coverage:upload --format=php-clover clover.xml

.PHONY: test-js
test-js:
	@echo No JS unit tests currently
	#cd tests/js && npm install --deps; node_modules/karma/bin/karma start karma.config.js --single-run;

.PHONY: test
test: test-codecheck test-codecheck-deprecations test-php test-js

