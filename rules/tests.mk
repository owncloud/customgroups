##
## Tests
##--------------------------------------

KARMA=$(NODE_PREFIX)/node_modules/.bin/karma
JSHINT=$(NODE_PREFIX)/node_modules/.bin/jshint

test_rules+=test-codecheck test-codecheck-deprecations test-js
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

