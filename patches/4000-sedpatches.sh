#!/bin/bash

# Temporary hacks to remove warnings and things

# dashboard/classes/phpsysinfo/includes/autoloader.inc.php
# This needs to be upgraded, but for the moment remove E_STRICT
F=opt/quadpbx/modules/dashboard/*/classes/phpsysinfo/includes/autoloader.inc.php
sed -i 's/| E_STRICT//' $F

# Null fix
L=opt/quadpbx/modules/certman/*/vendor/analogic/lescript/Lescript.php
sed -i 's/, ClientInterface $client = null/, ?ClientInterface $client = null/' $L

# There's a bunch in userman
P=$(egrep -l -R 'callable \$.+ = null' opt/quadpbx/modules/userman/*/vendor)
for f in $P; do
	echo Fixing null in $f
	sed -r -i 's/([\ (])callable \$([^\ ]+) = null/\1?callable \$\2 = null/g' $f
done

# API
sed -i 's/string \$className = null/?string $className = null/' opt/quadpbx/modules/api/*/vendor/php-di/php-di/src/functions.php


