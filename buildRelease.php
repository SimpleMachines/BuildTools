<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 1.0
 */

 /*
  * This tool is designed to run via command line.
  * To use this tool: php ./other/buildTools/buildRelease.php -s=repos/smf3.0/ -o=/tmp/
  *         Will use the repos/smf3.0/ to build release archives and output them into /tmp/
  * This tool is designed to be standalone and relies on no dependencies.
  */
declare(strict_types=1);

// Ensure that we exit with a failure if an error occurs.
try {
	buildRelease::run();
}
catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());
	exit(1);
}

class buildRelease
{
	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * Which version of SMF we are working with.
	 * This allows this tool to handle multiple SMF versions.
	 *
	 * @var string
	 */
	protected static string $target_version = '30';

	/**
	 * SMF root folder.
	 * @var string
	 */
	protected static string $smf_root = '';

	/**
	 * The directory where archives will be saved and additionally the temp files are handled here.
	 * @var string
	 */
	protected static string $output_dir = '';

	/**
	 * When true, shows the CLI help output.
	 * @var bool
	 */
	protected static bool $help = false;

	/**
	 * When true, shows the CLI debug output.
	 * @var bool
	 */
	protected static bool $debug = false;

	/**
	 * List of files we will exclude. Grouping is
	 *      all: All archives (install and upgrade)
	 *      install: Files to ignore just for install
	 *      upgrade: Files to ignore for upgrades
	 * @var array
	 */
	protected static array $ignoreFiles = [
		'all' => [
			// Git files.
			'.git',
			'.git*',

			// System folders.
			'.*',
			'*/.DS_Store',
			'*/._*',

			// SMF files.
			'favicon.ico',
			'other/*',
			'cache/data*',
			'cache/db_last_error.php',
			'custom_avatar/avatar*',
			'db_last_error.php',

			// Development files.
			'.editorconfig',
			'DCO.txt',
			'*.md',
			'error_log',
			'changelog.txt',
			'composer.*',
			// If we ever include this, make sure we don't include developer files.
			'vendor/*',
		],
		'install' => [],
		'upgrade' => [
			'agreement.txt',
		],
	];

	/**
	 * List of files we will pull in from Other into our root archive.
	 * @var array
	 */
	protected static array $otherFiles = [
		'install' => [
			'readme.html',
			'install.php',
			'Settings.php',
			'Settings_bak.php',
			// SMF 2.1 or below, SMF 3.0 does not make use of this.
			'install*.sql',
		],
		'upgrade' => [
			'upgrade.php',
			// SMF 2.1 or below, SMF 3.0 just uses upgrade.php
			'upgrade*.php',
			'upgrade*.sql',
		],
	];

	/**
	 * A list of archives we will build.
	 * Currently this is:
	 *      ZIP
	 *      Tar.gz
	 *      Tar.bz2
	 * @var array
	 */
	protected static array $archives = [
		[Phar::ZIP, Phar::NONE],
		[Phar::TAR, Phar::GZ],
		[Phar::TAR, Phar::BZ2],
	];

	/**
	 * Map of CLI parameters to variables in this class.
	 *
	 * @var array
	 */
	protected static array $cli_param_map = [
		's' => 'smf_root',
		'v' => 'target_version',
		'o' => 'output_dir',
		'h' => 'help',
		'd' => 'debug',
		'help' => 'help',
		'debug' => 'debug'
	];

	/***********************
	 * Public static methods
	 ***********************/

	public static function run()
	{
		if (php_sapi_name() !== 'cli') {
			throw new Exception('This tool is to be ran via CLI');
		}
		self::prepareCLIhandler();

		// The file has to exist.
		if (!file_exists(self::$smf_root)) {
			throw new Exception('Error: SMF Root does not exist');
		}

		// Cleanup the slashes.
		self::$smf_root = realpath(rtrim(self::$smf_root, '/')) . '/';

		// Find our version.
		$contents = file_get_contents(self::$smf_root . '/index.php', false, null, 0, 1500);

		if (!preg_match('/define\(\'SMF_VERSION\', \'([^\']+)\'\);/i', $contents, $version)) {
			throw new Exception('Error: Could not locate SMF_VERSION in ' . self::$smf_root);
		}

		$smf_version = $version[1];
		$file_prefix = self::getFileNamePrefix($smf_version);
		$tmp_file = self::$output_dir . '/' . $file_prefix;
	
		foreach (['install', 'upgrade'] as $build) {
			self::writeDebug("[$build] Building file list");

			// Ensure we run a clean setup for the build.
			array_map('unlink', glob($tmp_file . $build . '*'));

			// Builds our lists.
			$fileList = self::generateFileList(self::$smf_root, array_merge(self::$ignoreFiles['all'], self::$ignoreFiles[$build]));
			$otherList = self::generateOtherFilesList(self::$smf_root, self::$otherFiles[$build]);

			foreach (self::$archives as $a) {
				$extension = $a[0] === Phar::ZIP ? 'zip' : ($a[1] === Phar::GZ ? 'tar.gz' : 'tar.bz2');

				self::writeDebug("[$build] [$extension] Creating empty archive");

				$pd = new PharData(
					$tmp_file . $build . '.tmp',
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
					null,
					$a[0],
				);

				// Quickly now, use a iterator to build the main archive.
				self::writeDebug("[$build] [$extension] Adding initial files");
				$pd->buildFromIterator($fileList, self::$smf_root);

				// Add other into the root.
				self::writeDebug("[$build] [$extension] Adding other files");
				foreach ($otherList as $item) {
					$pd->addFile($item->getPathname(), $item->getFilename());
				}

				// Convert the archive into the proper archive and compression.
				self::writeDebug("[$build] [$extension] Writing file");
				$pd->convertToData($a[0], $a[1], $extension);

				// Zip needs to be compressed with DEFLATE, which phar doesn't do.
				if ($a[0] === Phar::ZIP) {
					self::writeDebug("[$build] [$extension] Compressing");
					$zip = new ZipArchive;
					$zip->open($tmp_file . $build . '.' . $extension);
					for ($i = 0; $i < $zip->numFiles; $i++) {
						$zip->setCompressionIndex($i, ZipArchive::CM_DEFLATE);
					}
					$zip->close();
				}

				// Tar files leave behind the .tmp file.
				if ($a[0] === Phar::TAR) {
					@unlink($tmp_file . $build . '.tmp');
				}
			}
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Reads the argv and parses them into variables we are passing into other parts of our code.
	 *
	 */
	protected static function prepareCLIhandler(): void
	{
		// Read the params into a place we can handle this.
		$params = $_SERVER['argv'];
		array_shift($params);

		foreach ($params as $param) {
			if (strpos($param, '=') !== false) {
				list($var, $val) = explode('=', $param);
				self::${self::$cli_param_map[ltrim($var, '-')]} = $val;
			} else {
			self::${self::$cli_param_map[ltrim($param, '-')]} = true;
			}
		}

		// Need help, hopefully not.
		if (empty($params) || self::$help) {
			echo 'SMF Build Release Tool' . "\n"
				. '$ php ' . basename(__FILE__) . " -s=path/to/smf/ -o=/tmp  \n"
				. '--s=/path/to/smf     Where SMF has its files' . "\n"
				. '--o=/path/to/out     Where to store the generated files' . "\n"
				. '--v=[30]         	What Version of SMF.  This defaults to SMF 30.' . "\n"
				. '-h, --help           This help file.' . "\n"
				. "\n";

			die;
		}
		unset($params);

		// Defaults.
		self::$smf_root = self::$smf_root === '' ? realpath($_SERVER['PWD']) : realpath(self::$smf_root);
		self::$output_dir = self::$output_dir === '' ? realpath($_SERVER['PWD'] . '/..') : realpath(self::$output_dir);
	}

	/**
	 * Taking the SMF version found in index.php, we figure out how we would name the files.
	 *
	 * @param string $version
	 * @return string
	 */
	protected static function getFileNamePrefix(string $version): string
	{
		preg_match('~v?([\d]+)[-._]?([\d]+)[-._\s]?(alpha|beta|rc)?\.?\s?([\d]?)~i', $version, $matches);

		// Sometimes we used beta.1 instead of beta-1
		if (isset($matches[3]) && $matches[3] == 'beta.') {
			$matches[3] = 'beta';
		}

		$prefix = 'smf_' . $matches[1] . '-' . $matches[2];

		// 4 part name "SMF 2.0 Alpha 3" will produce [2, 0, 'Alpha', 3]
		if (!empty($matches[4])) {
			$prefix .= '-' . strtolower($matches[3]) . $matches[4];
		} elseif (!empty($matches[3])) {
			$prefix .= '-' . $matches[3];
		}

		return $prefix . '_';
	}

	/**
	 * Generate a list of files that are to be included in the main archive using a exclusion list.
	 *
	 * @param string $path
	 * @param array $ignores List of files we will exclude, these are based on the SMF root forward.
	 * @return RecursiveIteratorIterator<RecursiveCallbackFilterIterator>
	 */
	protected static function generateFileList(string $path, array $ignores): RecursiveIteratorIterator
	{
		return new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator(
					$path,
					RecursiveDirectoryIterator::SKIP_DOTS,
				),
				function ($file, $key, $iterator) use ($ignores, $path) {
					// Simple is directory or exact matches.
					if ($iterator->hasChildren() && !in_array($file->getFilename(), $ignores)) {
						return true;
					}

					// Work out the SMF root.
					$filename = substr($file->getPathname(), 0, strlen($path)) === $path ? substr($file->getPathname(), strlen($path)) : $file->getPathname();

					foreach ($ignores as $e) {
						if (fnmatch($e, $filename)) {
							return false;
						}
					}

					// Otherwise, only include this if its a file.
					return $file->isFile();
				},
			),
		);
	}

	/**
	 * Generates a list of files that matches our filters to provide into the root of the archive from our other folder.
	 *
	 * @param string $path
	 * @param array $includes
	 * @return RecursiveIteratorIterator<RecursiveCallbackFilterIterator>
	 */
	protected static function generateOtherFilesList(string $path, array $includes): RecursiveIteratorIterator
	{
		return new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator(
					$path . 'other' . DIRECTORY_SEPARATOR,
					RecursiveDirectoryIterator::SKIP_DOTS,
				),
				function ($file, $key, $iterator) use ($includes) {
					if (in_array($file->getFilename(), $includes)) {
						return true;
					}

					foreach ($includes as $e) {
						if (fnmatch($e, $file->getFilename())) {
							return true;
						}
					}

					return false;
				},
			),
		);
	}

	/**
	 * Write a debug output.
	 * 
	 * @param string $msg
	 * @return void
	 */
	protected static function writeDebug(string $msg): void
	{
		if (self::$debug) {
			fwrite(STDOUT, $msg . "\n");
			flush();
		}
	}
}
