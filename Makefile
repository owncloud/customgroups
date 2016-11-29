# Makefile for building the project

app_name=customgroups
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build
doc_files=README.md
src_files=
src_dirs=appinfo controller css img js l10n lib templates
all_src=$(src_files) $(src_dirs) $(doc_files)
market_dir=$(build_dir)/market

.PHONY: all
all: dist market

market: dist
	cd $(build_dir); tar cvzf $(app_name).tar.gz $(app_name)
	rm -Rf $(market_dir); mkdir -p $(market_dir)
	mv $(build_dir)/$(app_name).tar.gz $(market_dir)


$(build_dir)/$(app_name):
	rm -Rf $@; mkdir -p $@
	cp -R $(all_src) $@

.PHONY: dist
dist: $(build_dir)/$(app_name)

.PHONY: distclean
distclean: clean

.PHONY: clean
clean:
	rm -rf $(build_dir)

