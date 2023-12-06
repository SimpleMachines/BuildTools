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

if (php_sapi_name() !== 'cli')
	die('This tool is to be ran via CLI');
prepareCLIhandler();

// All the stuff we need to build and their versions.
$buildFiles = [
	'index.php' => 'SMF',

	'cron.php' => 'Root',
	'subscriptions.php' => 'Root',
	'proxy.php' => 'Root',
	'SSI.php' => 'Root',

	// Sources
	'Sources/*' => 'Sources',

	// Tasks.
	'Sources/Tasks/*' => 'Tasks',

	// Themes.
	'Themes/default/*.php' => 'Default',
	'Themes/*/*.php' => 'Template',

	// Languages (keep this last in the list!)
	'Themes/default/languages/*.english.php' => 'Languages',
];

$skipSourceFiles = [
	'minify/*',
	'ReCaptcha/*',
	'Tasks/*'
];

// Read lengths
$readLengths = [
	'SMF' => 4096,
	'Root' => 4096,
	'Sources' => 4096,
	'Tasks' => 4096,
	'Default' => 768,
	'Template' => 768,
	'Languages' => 768
];

// Search strings.
$searchStrings = [
	'SMF' => '~\*\s@version\s+(.+)[\s]{2}~i',
	'Root' => '~\*\s@version\s+(.+)[\s]{2}~i',
	'Sources' => '~\*\s@version\s+(.+)[\s]{2}~i',
	'Tasks' => '~\*\s@version\s+(.+)[\s]{2}~i',
	'Default' => '~\*\s@version\s+(.+)[\s]{2}~i',
	'Template' => '~\*\s@version\s+(.+)[\s]{2}~i',
	'Languages' => '~(?://|/\*)\s*Version:\s+(.+?);\s*~i'
];

// Ignorables.
$ignoreFiles = [
	'|\./*.php~|i',
	'|\./*.txt|i',
];

// Skipping languages?
if (!isset($cliparams['include-languages']))
	unset($buildFiles['Themes/default/languages/*.english.php']);

// No file? Thats bad.
if (!isset($_SERVER['argv'], $_SERVER['argv'][1]))
	die('Error: No SMF root specified' . "\n");

// The file has to exist.
$smfRoot = $_SERVER['argv'][1];
if (!file_exists($smfRoot))
	die('Error: SMF Root does not exist' . "\n");

// Cleanup the slashes.
$smfRoot = realpath(rtrim($smfRoot, '/')) . '/';

// Loop all the data.
$version_info = array();
$count = 0;
foreach ($buildFiles as $globPath => $location)
{
	// Lets be specail here.
	if ($globPath == 'Sources/*') {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				'Sources' . DIRECTORY_SEPARATOR,
				RecursiveDirectoryIterator::SKIP_DOTS
			)
		);

		// Someday we could simplify this?
		foreach ($files as $shortname => $file)
		{
			if ($file->getFilename() === 'index.php' || $file->getFilename()[0] === '.' || $file->getExtension() !== 'php')
				continue;

			$basename = basename($file);
			foreach ($ignoreFiles as $if)
				if (preg_match($if, $basename))
					continue 2;

			foreach ($skipSourceFiles as $if)
				if (preg_match('~' . $if . '~i', $shortname))
					continue 2;

			// Count this.
			++$count;

			// Open the file, read it and close it.
			$fp = fopen($file, 'r');
			$header = fread($fp, $readLengths[$location]);
			fclose($fp);

			$filename = str_replace('Sources/', '', $shortname);
			if (preg_match($searchStrings[$location], $header, $match) == 1)
				$version_info[$location][$filename] = $match[1];
			else
				$version_info[$location][$filename] = '???';
		}
	}
	else {
		// Get a list of files.
		$files = glob($smfRoot . $globPath);

		if (!isset($version_info[$location]))
			$version_info[$location] = array();

		foreach ($files as $file)
		{
			$basename = basename($file);

			// Skip index files.
			if ($basename == 'index.php' && $location != 'SMF')
				continue;
			// Skip these files.
			foreach ($ignoreFiles as $if)
				if (preg_match($if, $basename))
					continue 2;

			// Count this.
			++$count;

			// Open the file, read it and close it.
			$fp = fopen($file, 'r');
			$header = fread($fp, $readLengths[$location]);
			fclose($fp);

			if (preg_match($searchStrings[$location], $header, $match) == 1)
				$version_info[$location][$basename] = $match[1];
			else
				$version_info[$location][$basename] = '???';
		}
	}
}

// Sort it.
foreach ($version_info as $location => $files)
	ksort($version_info[$location]);

// Output styles.
if (isset($cliparams['output']) && $cliparams['output'] == 'raw')
	var_dump($version_info);
else
{
	foreach ($version_info as $location => $files)
	{
		if ($location === 'SMF')
			echo "window.smfVersions = {\n";
		elseif ($location === 'Languages')
			echo "};\n\nwindow.smfLanguageVersions = {\n";

		$i = 0;
		foreach ($files as $file => $version)
		{
			++$i;
			$thislocation = $location === 'SMF' ? 'SMF' : ($location === 'Languages' ? str_replace('.english.php', '', $file) : $location . $file);

			if ($thislocation === 'SMF')
				$version = 'SMF ' . $version;

			// 'SMF': 'SMF 2.1 RC1'
			echo "\t'", $thislocation, "': '" . $version . "'";

			// Add in the comma.
			if ($count != $i)
				echo ',';

			// Add the return.
			echo "\n";
		}

		if ($location === 'Languages' || (!isset($cliparams['include-languages']) && $location === 'Template'))
			echo "};\n";
	}
}

function prepareCLIhandler()
{
	global $cliparams;

	// Read the params into a place we can handle this.
	$params = $_SERVER['argv'];
	array_shift($params);
	$cliparams = array();
	foreach($params AS $param)
	{
		if (strpos($param, '=') !== false)
		{
			list ($var, $val) = explode('=', $param);
			$cliparams[ltrim($var, '-')] = $val;
		}
		else
			$cliparams[ltrim($param, '-')] = true;
	}
	unset($params);

	// Need help, hopefully not.
	if (empty($cliparams) || isset($cliparams['help']) || isset($cliparams['h']))
	{
		echo "SMF Generate Detailed Versions Tool". "\n"
			. '$ php ' . basename(__FILE__) . " path/to/smf/ [--output=raw] [--include-languages] [--smf=[30]] \n"
			. "--include-languages   Include Languages Versions.". "\n"
			. "--smf=[30]         What Version of SMF.  This defaults to SMF 30.". "\n"
			. "-h, --help            This help file.". "\n"
			. "--output=raw          Raw output.". "\n"
			. "\n";
		die;
	}

	// Default SMF version.
	if (!isset($cliparams['smf']))
		$cliparams['smf'] = '30';
}