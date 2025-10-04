SHELL=/bin/bash

BUILDROOT=/usr/local/data/quadpbx-deb
REPOTOOLS=/usr/local/repo/repo-tools
DEBDEST=$(REPOTOOLS)/incoming

PHPVER=8.4

QBUILDVER=2509.07-02

PREPVER=$(shell awk '/Version: / { print $$2 }' packages/quadpbx-og-prep/control)
PREPDEB=quadpbx-og-prep_$(PREPVER)_all.deb
PREPDEBDEST=$(DEBDEST)/$(PREPDEB)
PREPSRC=$(BUILDROOT)/quadpbx-og-prep

export QBUILDVER PREPVER BUILDROOT

.PHONY: build prep push
build:
	@echo Hi
	./build.php

push: $(PREPDEBDEST)
	cd $(REPOTOOLS); make repo

prep $(PREPDEBDEST): $(PREPSRC)/DEBIAN/control
	@echo Building $(PREPDEBDEST)
	dpkg -b $(PREPSRC) $(DEBDEST)

$(PREPSRC)/DEBIAN/control: packages/quadpbx-og-prep/control
	@mkdir -p $(@D)
	sed -e 's/__PHPVER__/$(PHPVER)/g' < $< > $@
