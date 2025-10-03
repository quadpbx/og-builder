#!/bin/bash

# Framework Dir
FDIR=opt/quadpbx/modules/framework/current

# Our tarball
TARBALL=$(dirname $THISSCRIPT)/5000-composer.tgz
if [ ! -e $TARBALL ]; then
    echo "ERROR: $TARBALL does not exist"
    exit 9
fi

# Nuke FDIR/amp_conf/htdocs/admin/libraries/Composer
LDIR=$FDIR/amp_conf/htdocs/admin/libraries

if [ ! -d $LDIR/Composer ]; then
    echo "ERROR: $LDIR/Composer is not a directory"
    exit 9
fi

# NOTE WHEN UPDATING:
# This is a pile of crap. Things are manually patched in this tarball:
#   Composer/vendor/mtdowling/cron-expression/src/Cron/CronExpression.php - Fix not explict nullable on line 69

rm -rf $LDIR/Composer
echo "Replacing $LDIR/Composer"
tar -C $LDIR -xf $TARBALL
echo "Done"



