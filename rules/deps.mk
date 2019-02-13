# Deps

NODE_PREFIX=$(shell pwd)

YARN := $(shell command -v yarn 2> /dev/null)

JSDOC=$(NODE_PREFIX)/node_modules/.bin/jsdoc
COMPOSER=$(tools_path)/composer.phar

composer_deps=lib/composer
composer_dev_deps=lib/composer/phpunit
nodejs_deps=node_modules
js_deps=js/vendor
clean_rules+=clean-deps
help_rules+=help-deps

.PHONY: help-deps
help-deps:
	@echo -e "Dependencies:\n"
	@echo -e "deps\t\tto fetch all dependencies"
	@echo -e "update-composer\tto update composer.lock"
	@echo

.PHONY: clean-deps
clean-deps: clean-composer clean-js clean-nodejs
	rm -Rf $(tools_path)/*.phar

.PHONY: clean-composer
clean-composer:
	rm -Rf $(composer_deps)/
	rm -Rf vendor-bin/**/vendor vendor-bin/**/composer.lock

.PHONY: clean-js
clean-js:
	rm -Rf $(js_deps)/

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
$(nodejs_deps): package.json yarn.lock
	cd $(NODE_PREFIX) && $(YARN) install && touch $@

$(JSDOC): $(nodejs_deps)
$(js_deps): $(nodejs_deps)
.PHONY: deps
deps: $(composer_deps) $(js_deps)

.PHONY: dev-deps
dev-deps: $(composer_dev_deps) $(js_deps) $(nodejs_deps)

