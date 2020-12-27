<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

// Stuff we will ignore.
$ignoreFiles = array(
	// Build tools
	'./other/buildtools/[A-Za-z0-9-_]+.php',

	// Cache and miscellaneous.
	'\./cache/data_[A-Za-z0-9-_]\.php',
	'\./other/db_last_error.php',

	// Installer and ugprade are not a worry.
	'\./other/install.php',
	'\./other/upgrade.php',
	'\./other/upgrade-helper.php',

	// Minify Stuff.
	'\./Sources/minify/[A-Za-z0-9/-]+\.php',

	// random_compat().
	'\./Sources/random_compat/\w+\.php',

	// ReCaptcha Stuff.
	'\./Sources/ReCaptcha/[A-Za-z0-9]+\.php',
	'\./Sources/ReCaptcha/RequestMethod/[A-Za-z0-9]+\.php',

	// Punycode Stuff.
	'\./Sources/punycode/[A-Za-z0-9]+\.php',
	'\./Sources/punycode/Exception/[A-Za-z0-9]+\.php',

	// We will ignore Settings.php if this is a live dev site.
	'\./Settings.php',
	'\./Settings_bak.php',
	'\./db_last_error.php',
);

$curDir = '.';
if (isset($_SERVER['argv'], $_SERVER['argv'][1]))
	$curDir = $_SERVER['argv'][1];

$foundBad = false;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($curDir, FilesystemIterator::UNIX_PATHS)) as $currentFile => $fileInfo)
{
	// Only check PHP
	if ($fileInfo->getExtension() !== 'php')
		continue;

	foreach ($ignoreFiles as $if)
		if (preg_match('~' . $if . '~i', $currentFile))
			continue 2;

	$result = trim(shell_exec('php other/buildTools/check-eof.php ' . $currentFile . ' 2>&1'));

	if (!preg_match('~Error:([^$]+)~', $result))
		continue;

	$foundBad = true;
	fwrite(STDERR, $result . "\n");
}

if (!empty($foundBad))
	exit(1);