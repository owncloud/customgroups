# Tests

PHPUNIT="$(shell pwd)"/lib/composer/phpunit/phpunit/phpunit
OCULAR=$(shell pwd)/lib/composer/scrutinizer/ocular/bin/ocular
KARMA=$(NODE_PREFIX)/node_modules/.bin/karma
JSHINT=$(NODE_PREFIX)/node_modules/.bin/jshint

test_rules+=test-codecheck test-codecheck-deprecations test-php test-js
clean_rules+=clean-deps
help_rules+=help-test

tests_unit_results=tests/unit/results
clover_xml=$(tests_unit_results)/clover.xml
phpunit_args=--log-junit $(tests_unit_results)/results.xml
ifndef NOCOVERAGE # env variable
phpunit_args+=--coverage-clover $(clover_xml) --coverage-html $(tests_unit_results)/coverage-html
endif

.PHONY: help-test
help-test:
	@echo -e "Testing:\n"
	@echo -e "test\t\t\tto run all test suites"
	@echo -e "test-syntax\t\tto run syntax checks"
	@echo -e "test-codecheck\t\tto run the code checker"
	@echo -e "test-php\t\tto run PHP test suites"
	@echo -e "test-integration\tto run integration tests"
	@echo -e "test-js\t\t\tto run JS test suites (single run)"
	@echo -e "test-js-debug\t\tto run JS test and watch for changes"
	@echo

.PHONY: clean-test
clean-test:
	rm -Rf $(tests_unit_results)

.PHONY: test-syntax
test-syntax: test-syntax-php test-syntax-js

.PHONY: test-syntax-php
test-syntax-php:
	for F in $(shell find . -name \*.php | grep -v -e 'lib/composer' -e 'vendor'); do \
		php -l "$$F" > /dev/null || exit $?; \
	done

.PHONY: test-syntax-js
test-syntax-js: $(JSHINT)
	-$(JSHINT) --config .jshintrc --exclude-path .gitignore js tests/js

.PHONY: test-codecheck
test-codecheck: test-syntax-php
	$(OCC) app:check-code $(app_name) -c private -c strong-comparison

.PHONY: test-codecheck-deprecations
test-codecheck-deprecations:
	$(OCC) app:check-code $(app_name) -c deprecation

.PHONY: test-php
test-php: $(PHPUNIT) test-syntax-php
	$(OCC) app:enable $(app_name)
	$(PHPUNIT) --configuration tests/unit/phpunit.xml $(phpunit_args)

$(clover_xml): test-php

.PHONY: test-upload-coverage
test-upload-coverage: $(OCULAR) $(clover_xml)
	$(OCULAR) code-coverage:upload --format=php-clover $(clover_xml)

.PHONY: test-integration
test-integration: test-syntax-php
	cd tests/integration && OCC="$(OCC)" ./run.sh

.PHONY: test-js
test-js: $(bower_deps) $(KARMA) js-templates test-syntax-js
	$(KARMA) start tests/js/karma.config.js --single-run

test-js-debug: $(bower_deps) $(KARMA) js-templates test-syntax-js
	$(KARMA) start tests/js/karma.config.js

$(PHPUNIT): $(composer_dev_deps)
$(OCULAR): $(composer_dev_deps)

$(KARMA): $(nodejs_deps)
$(JSHINT): $(nodejs_deps)

