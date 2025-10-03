#!/bin/bash

# Contact Manager
FDIR=opt/quadpbx/modules/contactmanager/*

# Our tarball
TARBALL=$(dirname $THISSCRIPT)/5001-contactmgr.tgz
if [ ! -e $TARBALL ]; then
    echo "ERROR: $TARBALL does not exist"
    exit 9
fi

# Nuke FDIR/vendor. The composer files will be overwritten
LDIR=$FDIR/vendor

if [ ! -d $LDIR ]; then
    echo "ERROR: $LDIR is not a directory"
    exit 9
fi

rm -rf $LDIR
tar -C $FDIR -xf $TARBALL

