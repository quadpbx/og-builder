#!/bin/bash

SRCDIR=$(dirname $THISSCRIPT)
DESTDIR=opt/quadpbx/modules/framework/current/installlib/SQL
for s in cdr.sql; do
	SRCFILE=$SRCDIR/4001-$s
	cp $SRCFILE $DESTDIR/$s
done

