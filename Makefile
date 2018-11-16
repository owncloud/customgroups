# Makefile

OWNCLOUD_PATH=$(CURDIR)/../..
OCC=$(OWNCLOUD_PATH)/occ

app_name=customgroups
app_namespace=CustomGroups

build_dir=$(CURDIR)/build

# these can be extended by included files
# to add for example generated files
doc_files=README.md CHANGELOG.md
src_dirs=appinfo lib l10n js css img templates
all_src=$(src_files) $(src_dirs) $(doc_files)

# rules to be extended by included files
build_rules=
test_rules=
clean_rules=
js_rules=
help_rules=help-base

tools_path=$(shell pwd)/tools

# bin file definitions
PHPUNIT=php -d zend.enable_gc=0  vendor/bin/phpunit
PHPUNITDBG=phpdbg -qrr -d memory_limit=4096M -d zend.enable_gc=0 "./vendor/bin/phpunit"
PHP_CS_FIXER=php -d zend.enable_gc=0 vendor-bin/owncloud-codestyle/vendor/bin/php-cs-fixer

.DEFAULT_GOAL := help

# start with displaying help
help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

.PHONY: all
all: help-hint dist

include rules/deps.mk
include rules/sign.mk
include rules/dist.mk
include rules/tests.mk
include rules/frontend.mk

.PHONY: help-base
help-base:
	@echo "Please use 'make <target>' where <target> is one of"
	@echo

.PHONY: help-hint
help-hint:
	@echo "Building $(app_name) app"
	@echo
	@echo "Note: You can type 'make help' for more targets"
	@echo

.PHONY: help
help: $(help_rules)

.PHONY: clean
clean: $(clean_rules)

.PHONY: test
test: $(test_rules)


##
## Tests
##--------------------------------------

.PHONY: test-php-unit
test-php-unit:             ## Run php unit tests
test-php-unit: vendor/bin/phpunit
	$(PHPUNIT) --configuration ./tests/unit/phpunit.xml --testsuite unit

.PHONY: test-php-unit-dbg
test-php-unit-dbg:         ## Run php unit tests using phpdbg
test-php-unit-dbg: vendor/bin/phpunit
	$(PHPUNITDBG) --configuration ./tests/unit/phpunit.xml --testsuite unit

.PHONY: test-php-style
test-php-style:            ## Run php-cs-fixer and check owncloud code-style
test-php-style: vendor-bin/owncloud-codestyle/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes --dry-run

.PHONY: test-php-style-fix
test-php-style-fix:        ## Run php-cs-fixer and fix code style issues
test-php-style-fix: vendor-bin/owncloud-codestyle/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes

.PHONY: test-acceptance-api
test-acceptance-api:       ## Run API acceptance tests
test-acceptance-api: vendor/bin/phpunit
	../../tests/acceptance/run.sh --remote --type api

#
# Dependency management
#--------------------------------------

composer.lock: composer.json
	@echo composer.lock is not up to date.

vendor: composer.lock
	composer install --no-dev

vendor/bin/phpunit: composer.lock
	composer install

vendor/bamarni/composer-bin-plugin: composer.lock
	composer install


vendor-bin/owncloud-codestyle/composer.lock: vendor-bin/owncloud-codestyle/composer.json
	@echo owncloud-codestyle composer.lock is not up to date.
