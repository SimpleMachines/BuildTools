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
	$versions[$path] = $versionResults[1];

	if (!preg_match('/define\(\'SMF_SOFTWARE_YEAR\', \'(\d{4})\'\);/i', $contents, $yearResults))
		die('Error: Could not locate SMF_SOFTWARE_YEAR in ' . $path . "\n");
	$years[$path] = (int) $yearResults[1];
}

$versions = array_unique($versions);
$years = array_unique($years);

if (count($versions) != 1)
	die('Error: SMF_VERSION differs between ' . implode(', ', array_keys($versions)));

if (count($years) != 1)
	die('Error: SMF_SOFTWARE_YEAR differs between ' . implode(', ', array_keys($versions)));

$version = reset($versions);
if (!preg_match('~^((\d+)\.(\d+)[. ]?((?:(?<= )(?>RC|Beta |Alpha ))?\d+)?)$~', $version))
	die('Error: SMF_VERSION string is invalid: "' . $version . '"');
