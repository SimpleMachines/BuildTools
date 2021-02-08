#!/bin/bash

find . -type f -name "*.php" -print0 \
    -o -path "./Sources/minify" -prune \
    -o -path "./Sources/random_compat" -prune \
    -o -path "./Sources/ReCaptcha" -prune \
  | xargs -0 -n1 -P4 php -l \
  | (! grep -v "No syntax errors detected" )

php check-signed-off.php
php check-eof.php
php check-smf-license.php
php check-smf-languages.php
php check-version.php