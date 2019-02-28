##
## Frontend development
##--------------------------------------

HANDLEBARS=$(NODE_PREFIX)/node_modules/.bin/handlebars

template_src=$(wildcard js/templates/*.handlebars)
clean_rules+=clean-templates
build_rules+=js-templates
js_namespace=OCA.$(app_namespace)

$(HANDLEBARS): $(nodejs_deps)

%.handlebars.js: %.handlebars $(HANDLEBARS)
	$(HANDLEBARS) -n "$(js_namespace).Templates" $< > $@

.PHONY: js-templates
js-templates: ## manually build all templates
js-templates: $(addsuffix .js, $(template_src))

.PHONY: clean-templates
clean-templates: ## clean generated templates
clean-templates:
	rm -f $(addsuffix .js, $(template_src))

#
# Watch
#
.PHONY: watch
watch: ## watch for changes and rebuild all templates
watch: $(nodejs_deps)
	cd $(NODE_PREFIX) && $(YARN) run watch

