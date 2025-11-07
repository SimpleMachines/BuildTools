#!/bin/bash

find . -type f -name "*.php" -print0 \
  | xargs -0 -n1 -P4 php -l \
  | (! grep -v "No syntax errors detected" )

php check-signed-off.php
php check-eof.php
php check-smf-license.php
php check-smf-languages.php
php check-version.php
