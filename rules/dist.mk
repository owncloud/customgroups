##
## Build and dist rules
##--------------------------------------

clean_rules+=clean-build

.PHONY: clean-build
clean-build: ## Clean the build directory
clean-build:
	rm -Rf $(build_dir)

.PHONY: dist
dist: ## Build the distribution folder and tarball customgroups.tar.gz
dist: $(build_dir)/$(app_name).tar.gz

$(build_dir)/$(app_name).tar.gz: $(build_dir)/$(app_name) $(signature_file)
	cd $(build_dir); tar czf $@ $(app_name)
	@echo Tarball was built in $@

$(build_dir)/$(app_name).tar.bz2: $(build_dir)/$(app_name) $(signature_file)
	cd $(build_dir); tar cjf $@ $(app_name)
	@echo Tarball was built in $@

$(build_dir)/$(app_name): deps $(build_rules) $(all_src) js-templates
	mkdir -p $@
	cp -R $(all_src) $@
	@echo Removing unwanted files...
	rm -R $@/l10n/.tx
	find $@ \( \
		-name .gitkeep -o \
		-name .gitignore -o \
		-name no-php \
		\) -print | xargs rm -Rf
	find $@/{lib/composer/,js/vendor/} \( \
		-name bin -o \
		-name test -o \
		-name tests -o \
		-name examples -o \
		-name demo -o \
		-name demos -o \
		-name doc -o \
		-name travis -o \
		-name package.json -o \
		-name testem.json -o \
		-iname \*.sh -o \
		-iname \*.exe \
		\) -print | xargs rm -Rf
	find $@/js/templates -name \*.handlebars -delete
	touch $@

.PHONY: distclean
distclean: clean-build

.PHONY: clean-dist
clean-dist: clean-build

