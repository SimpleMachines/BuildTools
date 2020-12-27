#!/bin/bash

git submodule foreach git pull origin master

if [ $# -eq 0 ]
then
	echo "Running Local Version"

	echo "Checking PHP syntax"
	php other/buildTools/check-php-syntax.php ./

	echo "Checking for Sign Off issues"
	php other/buildTools/check-signed-off.php ./

	echo "Checking for Licensing issues"
	php other/buildTools/check-license-master.php ./

	echo "Checking for End of File issues"
	php other/buildTools/check-eof-master.php ./
else
	echo "Running Travis Version"
	if find . -name "*.php" ! -path "./vendor/*" -exec php -l {} 2>&1 \; | grep "syntax error, unexpected"; then exit 1; fi
	if php other/buildTools/check-signed-off.php travis | grep "Error:"; then php buildTools/check-signed-off.php travis; exit 1; fi
	if find . -name "*.php" -exec php buildTools/check-license.php {} 2>&1 \; | grep "Error:"; then exit 1; fi
	if find . -name "*.php" -exec php buildTools/check-eof.php {} 2>&1 \; | grep "Error:"; then exit 1; fi
fi
