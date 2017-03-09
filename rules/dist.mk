# Deps
# Build and dist rules

help_rules+=help-dist
clean_rules+=clean-build

.PHONY: help-market
help-dist:
	@echo -e "Building:\n"
	@echo -e "dist\t\tto build the distribution folder $(build_dir)/$(app_name)"
	@echo -e "market\t\tto build the market tarball in $(build_dir)/$(app_name).tar.gz"
	@echo -e "clean\t\tto clean everything"
	@echo

.PHONY: clean-build
clean-build:
	rm -Rf $(build_dir)

.PHONY: market
market: $(build_dir)/$(app_name).tar.gz

$(build_dir)/$(app_name).tar.gz: $(build_dir)/$(app_name)
	cd $(build_dir); tar czf $@ $(app_name)

$(build_dir)/$(app_name): deps $(all_src)
	mkdir -p $@
	cp -R $(all_src) $@
	@echo Removing unwanted files...
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
		-name .bower.json -o \
		-name bower.json -o \
		-name package.json -o \
		-name testem.json -o \
		-iname \*.sh -o \
		-iname \*.exe \
		\) -print | xargs rm -Rf
	touch $@

.PHONY: dist
dist: $(build_dir)/$(app_name)

.PHONY: distclean
distclean: clean

