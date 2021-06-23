# Makefile

COMPOSER_BIN := $(shell command -v composer 2> /dev/null)
ifndef COMPOSER_BIN
    $(error composer is not available on your system, please install composer)
endif

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

acceptance_test_deps=vendor-bin/behat/vendor

.DEFAULT_GOAL := help

# start with displaying help
help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | sed -e 's/  */ /' | column -t -s :

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
clean: clean-deps $(clean_rules)

.PHONY: test
test: $(test_rules)

#
# Dependency management
#--------------------------------------

composer.lock: composer.json
	@echo composer.lock is not up to date.

vendor: composer.lock
	composer install --no-dev

vendor/bamarni/composer-bin-plugin: composer.lock
	composer install
