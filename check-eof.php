<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

// Stuff we will ignore.
$ignoreFiles = [
	'./cache/',
	'./other/',
	'./tests/',
	'./vendor/',
	'./.git',

	// We will ignore Settings.php if this is a live dev site.
	'./Settings.php',
	'./Settings_bak.php',
	'./db_last_error.php',
];

try {
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath('.'), FilesystemIterator::UNIX_PATHS)) as $currentFile => $fileInfo) {
		// Starts with a dot, skip.  Also gets Mac OS X resource files.
		if (str_starts_with($fileInfo->getBasename(), '.')) {
			continue;
		}

		if ($fileInfo->getExtension() == 'php') {
			foreach ($ignoreFiles as $if) {
				if (file_exists($if) && preg_match('~' . preg_quote(realpath($if), '~') . '~i', $currentFile)) {
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
				if (strlen($contents) > 0 && !preg_match('~\S\n$~', $contents, $matches)) {
					throw new Exception('Incorrect number of newlines at EOF in ' . $currentFile);
				}
			} else {
				throw new Exception('Unable to open file ' . $currentFile);
			}
		}
	}
} catch (Exception $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}
