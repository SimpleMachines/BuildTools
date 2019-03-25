<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

$versions = array();
$years = array();
foreach (array('./index.php', './SSI.php', './cron.php', './proxy.php', './other/install.php', './other/upgrade.php') as $path)
{
	$contents = file_get_contents($path, false, null, 0, 1250);

	if (!preg_match('/define\(\'SMF_VERSION\', \'([^\']+)\'\);/i', $contents, $versionResults))
		die('Error: Could not locate SMF_VERSION in ' . $path . "\n");
	$versions[$versionResults[1]][] = $path;

	if (!preg_match('/define\(\'SMF_SOFTWARE_YEAR\', \'(\d{4})\'\);/i', $contents, $yearResults))
		die('Error: Could not locate SMF_SOFTWARE_YEAR in ' . $path . "\n");
	$years[$yearResults[1]][] = $path;
}

if (count($versions) != 1)
{
	$errmsg = 'Error: SMF_VERSION differs between files.';
	foreach ($versions as $version => $paths)
		$errmsg .= ' "' . $version . '" in ' . implode(', ', $paths) . '.';
	die($errmsg);
}

if (count($years) != 1)
{
	$errmsg = 'Error: SMF_SOFTWARE_YEAR differs between files.';
	foreach ($years as $year => $paths)
		$errmsg .= ' "' . $year . '" in ' . implode(', ', $paths) . '.';
	die($errmsg);
}

if (!preg_match('~^((\d+)\.(\d+)[. ]?((?:(?<= )(?>RC|Beta |Alpha ))?\d+)?)$~', key($versions)))
	die('Error: SMF_VERSION string is invalid: "' . key($versions) . '"');