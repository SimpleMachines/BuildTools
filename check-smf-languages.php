<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

// Stuff we will ignore.
global $ignoreFiles, $currentVersion;

$ignoreFiles = [
	'index\.php',
	'\.(?!php)[^.]*$',
];

try {
	if (($indexFile = fopen('./index.php', 'r')) === false) {
		throw new Exception('Unable to open file ./index.php');
	}

	$indexContents = fread($indexFile, 1250);

	if (!preg_match('~define\(\'SMF_VERSION\', \'([^\']+)\'\);~i', $indexContents, $versionResults)) {
		throw new Exception('Error: Could not locate SMF_VERSION');
	}

	$currentVersion = $versionResults[1];
	$ignoreFiles[] = '^(?!General\.)';

	checkLanguageDirectory('./Languages/en_US', '~([A-Za-z]+)\.php~i');
} catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());
	exit(1);
}

function checkLanguageDirectory($language_dir, $search)
{
	global $ignoreFiles, $currentVersion;

	foreach (new DirectoryIterator($language_dir) as $fileInfo) {
		// Starts with a dot, skip.  Also gets Mac OS X resource files.
		if ($fileInfo->getFilename()[0] == '.') {
			continue;
		}

		if ($fileInfo->getExtension() == 'php') {
			foreach ($ignoreFiles as $if) {
				if (preg_match('~' . $if . '~i', $fileInfo->getFilename())) {
					continue 2;
				}
			}

			if (($file = fopen($fileInfo->getPathname(), 'r')) !== false) {
				$contents = fread($file, 500);

				// Just see if the basic match is there.
				$match = '// Version: ' . $currentVersion;
				if (!preg_match('~' . $match . '~i', $contents)) {
					throw new Exception('Error: The version is missing or incorrect in ' . $fileInfo->getPathname());
				}

				// Get the file prefix.
				preg_match($search, $fileInfo->getFilename(), $fileMatch);
				if (empty($fileMatch)) {
					throw new Exception('Error: Could not locate the file name in ' . $fileInfo->getPathname());
				}

				// Now match that prefix in a more strict mode.
				$match = '// Version: ' . $currentVersion . '; ' . $fileMatch[1];
				if (!preg_match('~' . $match . '~i', $contents)) {
					throw new Exception('Error: The version with file name is missing or incorrect in ' . $fileInfo->getPathname());
				}
			} else {
				throw new Exception('Unable to open file ' . $fileInfo->getPathname());
			}
		}
	}
}
