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

// Get paths from the the composer.lock file.
$json = json_decode(file_get_contents('composer.lock'), true);

// Add index.php to any directories that will be included in our distribution packages.
$dist_dirs = ['./vendor'];

foreach ($json['packages'] as $package) {
	$dist_dirs[] = './vendor/' . strstr($package['name'], '/', true);

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(
			'./vendor/' . strstr($package['name'], '/', true),
			RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
		),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item) {
		if ($item->isDir()) {
			$dist_dirs[] = $item->getPathname();
		}
	}
}

foreach ($dist_dirs as $key => $dir) {
	if (!file_exists($dir . '/index.php')) {
		copy('./Sources/index.php', $dir . '/index.php');
	}
}

// Never add index.php to any other vendor directories.
$dev_dirs = [];

foreach ($json['packages-dev'] as $package) {
	$dev_dirs[] = './vendor/' . strstr($package['name'], '/', true);

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(
			'./vendor/' . strstr($package['name'], '/', true),
			RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
		),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item) {
		if ($item->isDir()) {
			$dev_dirs[] = $item->getPathname();
		}
	}
}

foreach ($dev_dirs as $key => $dir) {
	if (
		file_exists($dir . '/index.php')
		&& md5_file('./Sources/index.php') === md5_file($dir . '/index.php')
	) {
		unlink($dir . '/index.php');
	}
}
