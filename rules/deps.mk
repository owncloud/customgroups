# Deps

NODE_PREFIX=$(shell pwd)

NPM := $(shell command -v npm 2> /dev/null)
ifndef NPM
    $(error npm is not available on your system, please install npm)
endif

BOWER=$(NODE_PREFIX)/node_modules/bower/bin/bower
JSDOC=$(NODE_PREFIX)/node_modules/.bin/jsdoc
COMPOSER=$(tools_path)/composer.phar

composer_deps=lib/composer
composer_dev_deps=lib/composer/phpunit
nodejs_deps=node_modules
bower_deps=js/vendor
clean_rules+=clean-deps
help_rules+=help-deps

.PHONY: help-deps
help-deps:
	@echo -e "Dependencies:\n"
	@echo -e "deps\t\tto fetch all dependencies"
	@echo -e "update-composer\tto update composer.lock"
	@echo

.PHONY: clean-deps
clean-deps: clean-composer clean-bower clean-nodejs
	rm -Rf $(tools_path)/*.phar

.PHONY: clean-composer
clean-composer:
	rm -Rf $(composer_deps)/

.PHONY: clean-bower
clean-bower:
	rm -Rf $(bower_deps)/

.PHONY: clean-nodejs
clean-nodejs:
	rm -Rf $(nodejs_deps)/

$(COMPOSER):
	cd "$(tools_path)" && curl -ss https://getcomposer.org/installer | php
	chmod u+x $@

$(composer_deps): $(COMPOSER) composer.json composer.lock
	php $(COMPOSER) install --no-dev && touch $@

$(composer_dev_deps): $(COMPOSER) composer.json composer.lock
	php $(COMPOSER) install && touch $@

.PHONY: update-composer
update-composer:
	php $(COMPOSER) update

#
# Node JS dependencies for tools
#
$(nodejs_deps): package.json
	$(NPM) install --prefix $(NODE_PREFIX) && touch $@

$(BOWER): $(nodejs_deps)
$(JSDOC): $(nodejs_deps)

$(bower_deps): $(BOWER)
	$(BOWER) install && touch $@

.PHONY: deps
deps: $(composer_deps) $(bower_deps)

.PHONY: dev-deps
dev-deps: $(composer_dev_deps) $(bower_deps) $(nodejs_deps)

