##
## Tests
##--------------------------------------

# bin file definitions
KARMA=$(NODE_PREFIX)/node_modules/.bin/karma
JSHINT=$(NODE_PREFIX)/node_modules/.bin/jshint
PHPUNIT=php -d zend.enable_gc=0  "$(PWD)/../../lib/composer/bin/phpunit"
PHPUNITDBG=phpdbg -qrr -d memory_limit=4096M -d zend.enable_gc=0 "$(PWD)/../../lib/composer/bin/phpunit"
PHP_CS_FIXER=php -d zend.enable_gc=0 vendor-bin/owncloud-codestyle/vendor/bin/php-cs-fixer
PHP_CODESNIFFER=vendor-bin/php_codesniffer/vendor/bin/phpcs
BEHAT_BIN=vendor-bin/behat/vendor/bin/behat

test_rules+=test-php-style test-codecheck test-codecheck-deprecations test-js test-php-unit test-acceptance-api
clean_rules+=clean-deps

.PHONY: test-syntax
test-syntax: ## Run syntax checks
test-syntax: test-syntax-js

.PHONY: test-syntax-js
test-syntax-js: ## Run JS syntax checks
test-syntax-js: $(JSHINT)
	-$(JSHINT) --config .jshintrc --exclude-path .gitignore js tests/js

.PHONY: test-codecheck
test-codecheck: ## Run the app code checker
test-codecheck:
	$(OCC) app:check-code $(app_name) -c private -c strong-comparison

.PHONY: test-codecheck-deprecations
test-codecheck-deprecations: ## Run the app code deprecation checker
test-codecheck-deprecations:
	$(OCC) app:check-code $(app_name) -c deprecation

.PHONY: test-js
test-js: ## run JS test suites (single run)
test-js: $(js_deps) $(KARMA) js-templates test-syntax-js
	$(KARMA) start tests/js/karma.config.js --single-run

test-js-debug: run JS test suites and watch for changes
test-js-debug: $(js_deps) $(KARMA) js-templates test-syntax-js
	$(KARMA) start tests/js/karma.config.js

$(KARMA): $(nodejs_deps)
$(JSHINT): $(nodejs_deps)

.PHONY: test-php-unit
test-php-unit: ## Run php unit tests
test-php-unit:
	$(PHPUNIT) --configuration ./phpunit.xml --testsuite unit

.PHONY: test-php-unit-dbg
test-php-unit-dbg: ## Run php unit tests using phpdbg
test-php-unit-dbg:
	$(PHPUNITDBG) --configuration ./phpunit.xml --testsuite unit

.PHONY: test-php-style
test-php-style: ## Run php-cs-fixer and check owncloud code-style
test-php-style: vendor-bin/owncloud-codestyle/vendor vendor-bin/php_codesniffer/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes --dry-run
	$(PHP_CODESNIFFER) --runtime-set ignore_warnings_on_exit --standard=phpcs.xml tests/acceptance

.PHONY: test-php-style-fix
test-php-style-fix: ## Run php-cs-fixer and fix code style issues
test-php-style-fix: vendor-bin/owncloud-codestyle/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes

.PHONY: test-acceptance-api
test-acceptance-api: ## Run API acceptance tests
test-acceptance-api: $(acceptance_test_deps)
	BEHAT_BIN=$(BEHAT_BIN) ../../tests/acceptance/run.sh --remote --type api

#
# Dependency management
#--------------------------------------

vendor-bin/owncloud-codestyle/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/owncloud-codestyle/composer.lock
	composer bin owncloud-codestyle install --no-progress

vendor-bin/owncloud-codestyle/composer.lock: vendor-bin/owncloud-codestyle/composer.json
	@echo owncloud-codestyle composer.lock is not up to date.

vendor-bin/php_codesniffer/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/php_codesniffer/composer.lock
	composer bin php_codesniffer install --no-progress

vendor-bin/php_codesniffer/composer.lock: vendor-bin/php_codesniffer/composer.json
	@echo php_codesniffer composer.lock is not up to date.

vendor-bin/behat/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/behat/composer.lock
	composer bin behat install --no-progress

vendor-bin/behat/composer.lock: vendor-bin/behat/composer.json
	@echo behat composer.lock is not up to date.
