SHELL=/bin/bash

BUILDROOT=/usr/local/data/quadpbx-deb
REPOTOOLS=/usr/local/repo/repo-tools
DEBDEST=$(REPOTOOLS)/incoming

PHPVER=8.4

QBUILDVER=2025.07-07

PREPSRC=packages/quadpbx-og-prep
PREPVER=$(shell awk '/Version: / { print $$2 }' $(PREPSRC)/debdir/control)
PREPDEB=quadpbx-og-prep_$(PREPVER)_all.deb
PREPDEBDEST=$(DEBDEST)/$(PREPDEB)

PREPBUILDDIR=$(BUILDROOT)/quadpbx-og-prep

export QBUILDVER PREPVER BUILDROOT

.PHONY: build prep push
build:
	@echo Hi
	./build.php

push: $(PREPDEBDEST)
	cd $(REPOTOOLS); make repo

PREPSRCDEBFILES=$(wildcard $(PREPSRC)/debdir/*)
PREPDESTDEBFILES=$(subst $(PREPSRC)/debdir,$(PREPBUILDDIR)/DEBIAN,$(PREPSRCDEBFILES))
PREPDESTFILES=$(subst files/,$(PREPBUILDDIR)/,$(shell cd $(PREPSRC); find files/ -type f))

# Rewrite __PHPVER__ in all files (Currently, only control). This will probably
# change later, especially as PHP8.4 is hardcoded in a bunch of places.
$(PREPBUILDDIR)/DEBIAN/%: $(PREPSRC)/debdir/% | $(PREPBUILDDIR)/DEBIAN
	@echo "Updating $<"
	@sed -e 's/__PHPVER__/$(PHPVER)/g' < $< > $@

$(PREPBUILDDIR)/DEBIAN:
	@mkdir -p $@

prep $(PREPDEBDEST): $(PREPDESTDEBFILES) $(PREPDESTFILES)
	@echo Building $(PREPDEBDEST)
	@chmod 755 $(PREPBUILDDIR)/DEBIAN/*inst
	dpkg -b $(PREPBUILDDIR) $(DEBDEST)

$(PREPBUILDDIR)/%: $(PREPSRC)/files/%
	@mkdir -p $(@D)
	@cp $< $@

