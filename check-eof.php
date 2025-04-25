<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

// Stuff we will ignore.
$ignoreFiles = [
	'\./cache/',
	'\./other/',
	'\./tests/',
	'\./vendor/',

	// Minify Stuff.
	'\./Sources/minify/',

	// random_compat().
	'\./Sources/random_compat/',

	// ReCaptcha Stuff.
	'\./Sources/ReCaptcha/',

	// We will ignore Settings.php if this is a live dev site.
	'\./Settings\.php',
	'\./Settings_bak\.php',
	'\./db_last_error\.php',
];

try {
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', FilesystemIterator::UNIX_PATHS)) as $currentFile => $fileInfo) {
		// Starts with a dot, skip.  Also gets Mac OS X resource files.
		if ($currentFile[0] == '.') {
			continue;
		}

		if ($fileInfo->getExtension() == 'php') {
			foreach ($ignoreFiles as $if) {
				if (preg_match('~' . $if . '~i', $currentFile)) {
					continue 2;
				}
			}

			if (($file = fopen($currentFile, 'r')) !== false) {
				// Seek the end minus some bytes.
				fseek($file, -100, SEEK_END);
				$contents = fread($file, 100);

				// We don't want closing PHP tags in SMF 3.0+.
				if (preg_match('~\s*\?>\s*$~', $contents, $matches)) {
					throw new Exception('Closing PHP tag found in ' . $currentFile . '. Please remove it.');
				}

				// Make sure we end with exactly one newline.
				if (!preg_match('~\S\n$~', $contents, $matches)) {
					throw new Exception('Incorrect number of newlines at EOF in ' . $currentFile);
				}
			} else {
				throw new Exception('Unable to open file ' . $currentFile);
			}
		}
	}
}
catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());
	exit(1);
}