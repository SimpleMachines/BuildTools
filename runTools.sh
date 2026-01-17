#!/bin/bash
CURDIR=`dirname -- "$( readlink -f -- "$0"; )";`

find . -type f -name "*.php" -print0 \
    -o -path "./Sources/minify" -prune \
    -o -path "./Sources/random_compat" -prune \
    -o -path "./Sources/ReCaptcha" -prune \
  | xargs -0 -n1 -P4 php -l \
  | (! grep -v "No syntax errors detected" )

php $CURDIR/check-signed-off.php
php $CURDIR/check-eof.php
php $CURDIR/check-smf-license.php
php $CURDIR/check-smf-languages.php
php $CURDIR/check-version.php
