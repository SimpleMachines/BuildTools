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

$dirs = ['./vendor'];

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator(
		'./vendor',
		RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
	),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
	if ($item->isDir()) {
		$dirs[] = $item->getPathname();
	}
}

foreach ($dirs as $key => $dir) {
	if (!file_exists($dir . '/index.php')) {
		copy('./Sources/index.php', $dir . '/index.php');
	}
}
