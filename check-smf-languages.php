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
	'./Themes/default/languages/index\.php',
);

// No file? Thats bad.
if (!isset($_SERVER['argv'], $_SERVER['argv'][1]))
	die('Error: No File specified' . "\n");

// The file has to exist.
$currentFile = $_SERVER['argv'][1];
if (!file_exists($currentFile))
	die('Error: File does not exist' . "\n");

// Is this ignored?
foreach ($ignoreFiles as $if)
	if (preg_match('~' . $if . '~i', $currentFile))
		die;

// Lets get the version.
$upgradeFile = fopen('./other/upgrade.php', 'r');

// Error?
if ($upgradeFile === false)
	die("Error: Unable to open file ./other/upgrade.php\n");

$upgradeContents = fread($upgradeFile, 1250);

if (!preg_match('~define\(\'SMF_LANG_VERSION\', \'([^\']+)\'\);~i', $upgradeContents, $versionResults))
	die('Error: Could not locate SMF_LANG_VERSION' . "\n");
$currentVersion = $versionResults[1];

$file = fopen($currentFile, 'r');

// Error?
if ($file === false)
	die('Error: Unable to open file ' . $currentFile . "\n");

$contents = fread($file, 500);

// Just see if the basic match is there.
$match = '// Version: ' . $currentVersion;
if (!preg_match('~' . $match . '~i', $contents))
	die('Error: The version is missing or incorrect in ' . $currentFile . "\n");

// Get the file prefix.
preg_match('~([A-Za-z]+)\.english\.php~i', $currentFile, $fileMatch);
if (empty($fileMatch))
	die('Error: Could not locate locate the file name in ' . $currentFile . "\n");

// Now match that prefix in a more strict mode.
$match = '// Version: ' . $currentVersion . '; ' . $fileMatch[1];
if (!preg_match('~' . $match . '~i', $contents))
	die('Error: The version with file name is missing or incorrect in ' . $currentFile . "\n");