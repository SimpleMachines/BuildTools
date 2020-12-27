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
	// Index files.
	'\./attachments/index\.php',
	'\./avatars/index\.php',
	'\./avatars/[A-Za-z0-9]+/index\.php',
	'\./cache/index\.php',
	'\./custom_avatar/index\.php',
	'\./Packages/index\.php',
	'\./Packages/backups/index\.php',
	'\./Smileys/[A-Za-z0-9]+/index\.php',
	'\./Smileys/index\.php',
	'\./Sources/index\.php',
	'\./Sources/tasks/index\.php',
	'\./Themes/default/css/index\.php',
	'\./Themes/default/fonts/index\.php',
	'\./Themes/default/fonts/sound/index\.php',
	'\./Themes/default/images/[A-Za-z0-9]+/index\.php',
	'\./Themes/default/images/index\.php',
	'\./Themes/default/index\.php',
	'\./Themes/default/languages/index\.php',
	'\./Themes/default/scripts/index\.php',
	'\./Themes/index\.php',

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

	// Language Files are ignored as they don't use the License format.
	'./Themes/default/languages/[A-Za-z0-9]+\.english\.php',

	// Cache and miscellaneous.
	'\./cache/data_[A-Za-z0-9-_]+\.php',
	'\./other/db_last_error.php',
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

	$result = trim(shell_exec('php other/buildTools/check-smf-license.php ' . $currentFile . ' 2>&1'));

	if (!preg_match('~Error:([^$]+)~', $result))
		continue;

	$foundBad = true;
	fwrite(STDERR, $result . "\n");
}

if (!empty($foundBad))
	exit(1);