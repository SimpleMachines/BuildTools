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
  * To use this tool: php ./other/buildTools/buildLanguages.php -s=repos/smf3.0/ -o=/tmp/
  *         Will use the repos/smf3.0/ to build release archives and output them into /tmp/
  * This tool is designed to be standalone and relies on no dependencies.
  */
declare(strict_types=1);

// Ensure that we exit with a failure if an error occurs.
try {
	buildLanguages::run();
}
catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());
	exit(1);
}

class buildLanguages
{
	/****************************
	 * Internal static properties
	 ****************************/

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

	protected static bool $skip_download = false;

	protected static string $crowdin_api_key = '';

	protected static array $crowdin_branch_map = [
		'SMF_2-1' => ['2.1.0-alpha1','2.1.99'],
		'SMF_3-0' => ['3.0.0-alpha1','3.0.99'],
	];

	/**
	 * Language map.  SMF 3.0 will not use these and will just use the locale.
	 * This does not match (yet) the list in 3.0, as it matches what Crowodin export gives us.
	 * @var array
	 */
	protected static array $language_map = [
		'af-ZA' => 'afrikaans',
		'sq-AL' => 'albanian',
		'ar-SA' => 'arabic',
		'bg-BG' => 'bulgarian',
		'ca-ES' => 'catalan',
		'zh-CN' => 'chinese_simplified',
		'zh-TW' => 'chinese_traditional',
		'hr-HR' => 'croatian',
		'cs' => 'czech_informal',
		'cs-CZ' => 'czech',
		'da-DK' => 'danish',
		'nl-NL' => 'dutch',
		'en-GB' => 'english_british',
		'eo-UY' => 'esperanto',
		'et-EE' => 'estonian',
		'fi-FI' => 'finnish',
		'fr-FR' => 'french',
		'gl-ES' => 'galician',
		'de' => 'german_informal',
		'de-DE' => 'german',
		'el-GR' => 'greek',
		'he-IL' => 'hebrew',
		'hu-HU' => 'hungarian',
		'id-ID' => 'indonesian',
		'it-IT' => 'italian',
		'ja-JP' => 'japanese',
		'kmr-TR' => 'kurdish_kurmanji',
		'lt-LT' => 'lithuanian',
		'mk-MK' => 'macedonian',
		'ms-MY' => 'malay',
		'no-NO' => 'norwegian',
		'fa-IR' => 'persian',
		'pl-PL' => 'polish',
		'pt-BR' => 'portuguese_brazilian',
		'pt-PT' => 'portuguese_pt',
		'ro-RO' => 'romanian',
		'ru-RU' => 'russian',
		'sr-SP' => 'serbian_cyrillic',
		'sr-CS' => 'serbian_latin',
		'sk-SK' => 'slovak',
		'sl-SI' => 'slovenian',
		'es-ES' => 'spanish_es',
		'es-MX' => 'spanish_latin',
		'sv-SE' => 'swedish',
		'th-TH' => 'thai',
		'tr-TR' => 'turkish',
		'uk-UA' => 'ukrainian',
		'ur-PK' => 'urdu',
		'vi-VN' => 'vietnamese',
		'eu' => 'basque',
		'bs-BA' => 'bosnian',
		'hi' => 'hindi',
		'ckb-IR' => 'kurdish_sorani',
		'lv-LV' => 'latvian',
		'ml-IN' => 'malayalam',
		'te' => 'telugu',
		'tk' => 'turkmen',
		'uz-UZ' => 'uzbek_latin',
		'az-AZ' => 'azerbaijani',
		'be-BY' => 'belarusian',
		'en-PT' => 'english_pirate',
		'ach' => 'acholi',
		'ug-CN' => 'uyghur'
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
		'o' => 'output_dir',
		'h' => 'help',
		'd' => 'debug',
		'help' => 'help',
		'debug' => 'debug',
		'key' => 'crowdin_api_key',
		'skip-download' => 'skip_download'
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

		if (empty(self::$crowdin_api_key)) {
			throw new Exception('Missing API Key');
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
		$tmp_file = self::$output_dir . '/' . $file_prefix . 'language_';

		// Find out which Crowdin branch we are building.
		// Pick our latest as the default.
		$current_crowdin_project = array_key_last(self::$crowdin_branch_map);
		foreach (self::$crowdin_branch_map as $k => $v) {
			if (version_compare($v[0], $smf_version, '<=') && version_compare($v[1], $smf_version, '>=')) {
				$current_crowdin_project = $k;
				break;
			}
		}

		// Startup a new API with the Crowdin PHP API client.
		if (!self::$skip_download) {
			self::writeDebug('Connecting to Crowdin API');
			require_once(__DIR__ . '/vendor/autoload.php');
			$api = new \CrowdinApiClient\Crowdin([
				'access_token' => self::$crowdin_api_key,
			]);
		}

		// We sometimes may wan to skip a download, such as if we are rerunning this locally.
		if (!self::$skip_download)
		{
			// Obtain project ID here..
			$project_id = $api->project->list()[0]->getId() ?? 0;
			$project_identifier = $api->project->list()[0]->getIdentifier() ?? '';
			if (empty($project_id)) {
				throw new Exception('Unable to obtain the project id');
			}

			/** @@todo Can we switch over to this? Simple Machines Download page should match these.
			 * Sample: ["es-ES"]=> array(1) { ["name"]=> string(10) "spanish_es" }
			*/
			//$lang_map = $api->project->list()[0]->getLanguageMapping();

			$branch_id = $api->branch->list($project_id)[0]->getId() ?? 0;
			if (empty($project_id)) {
				throw new Exception('Unable to obtain the branch id');
			}

			// Ensure the project is built.
			try
			{
				self::writeDebug('Starting Build');
				$results = $api->translation->buildProject(
					$project_id, //$projectId : int
					[
						'branchId' => $branch_id, //integer $params[branchId]
						//[],//array $params[targetLanguageIds]
						'skipUntranslatedStrings' => false, //bool $params[skipUntranslatedStrings] true value can't be used with skipUntranslatedFiles=true in same request
						'skipUntranslatedFiles' => false, //bool $params[skipUntranslatedFiles] true value can't be used with skipUntranslatedStrings=true in same request
						'exportApprovedOnly' => false, //bool $params[exportApprovedOnly]
						//false, //integer $params[exportWithMinApprovalsCount]
					]
				);
			}
			catch (CrowdinApiClient\Exceptions\ApiException $e)
			{
				self::writeDebug($e->getMessage());
				var_dump($e);
				throw $e;
			}
			catch (CrowdinApiClient\Exceptions\ApiValidationException $e)
			{
				$errs = $e->getErrors();
				foreach ($errs as $err)
					self::writeDebug($err['error']['errors']);
				throw $e;
			}

			// Obtain the build id.
			$buildID = $results->getId();

			// We need to wait for it to build.
			$done = false;
			while (!$done)
			{
				$status = $api->translation->getProjectBuildStatus(
					$project_id, //$projectId : int,
					$buildID
				);

				if ($status->getProgress() > 99)
				{
					$done = true;
					break;
				}

				self::writeDebug('Building [' . $status->getProgress() . '%]');

				sleep(10);
			}

			self::writeDebug('Downloading archive [' . $tmp_file . "all.zip" . ']');
			$results = $api->translation->downloadProjectBuild(
				$project_id, //$projectId : int,
				$buildID
			);
			$downloadURL = $results->getUrl();

			file_put_contents($tmp_file . "all.zip", fopen($downloadURL, 'r'));
		}

		if (!file_exists($tmp_file . "all.zip")) {
			throw new Exception('Unable to locate language bundle[' . $tmp_file . "all.zip]");
		}

		// Extract it.
		self::writeDebug('Extracting bundle');
		$zip = new ZipArchive;
		if ($zip->open($tmp_file . "all.zip") === TRUE) {
			$zip->extractTo($tmp_file . "tmp");
			$zip->close();
		} else {
			throw new Exception('Unable to extract ZIP');
		}

		foreach (self::$language_map as $locale => $naming) {
			self::writeDebug("[$locale] Building files");

			$language_directory = $tmp_file . "tmp" . DIRECTORY_SEPARATOR . $locale;

			// Ensure we run a clean setup for the build.
			array_map('unlink', glob($tmp_file . $locale . '*'));

			if (!file_exists($language_directory)) {
				self::writeDebug("[$locale] Not found, skipping [" . $language_directory . ']');
				continue;
			}

			$fileList = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator(
						$language_directory,
						RecursiveDirectoryIterator::SKIP_DOTS,
					),
					fn ($file, $key, $iterator) => strpos($file->getPathname(), $language_directory) === 0,
				),
			);

			foreach (self::$archives as $a) {
				$extension = $a[0] === Phar::ZIP ? 'zip' : ($a[1] === Phar::GZ ? 'tar.gz' : 'tar.bz2');

				self::writeDebug("[$locale] [$extension] Creating empty archive");

				$pd = new PharData(
					$tmp_file . $locale . '.tmp',
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
					null,
					$a[0],
				);

				// Quickly now, use a iterator to build the main archive.
				self::writeDebug("[$locale] [$extension] Adding initial files");
				$pd->buildFromIterator($fileList, $language_directory);

				// Convert the archive into the proper archive and compression.
				self::writeDebug("[$locale] [$extension] Writing file");
				$pd->convertToData($a[0], $a[1], $extension);

				// Zip needs to be compressed with DEFLATE, which phar doesn't do.
				if ($a[0] === Phar::ZIP) {
					self::writeDebug("[$locale] [$extension] Compressing");
					$zip = new ZipArchive;
					$zip->open($tmp_file . $locale . '.' . $extension);
					for ($i = 0; $i < $zip->numFiles; $i++) {
						$zip->setCompressionIndex($i, ZipArchive::CM_DEFLATE);
					}
					$zip->close();
				}

				// Tar files leave behind the .tmp file.
				if ($a[0] === Phar::TAR) {
					@unlink($tmp_file . $locale . '.tmp');
				}
			}
		}

		self::writeDebug('Cleaning up');
		@unlink($tmp_file . "all.zip");
		$tmp_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp_file . "tmp"), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($tmp_files as $file) {
        	$file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    	}
		@rmdir($tmp_file . "tmp");
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
				. '--key=....         	Crowdin API key, this defaults from the environment variable CROWDIN_API_TOKEN' . "\n"				
				. '-h, --help           This help file.' . "\n"
				. "\n";

			die;
		}
		unset($params);

		// Defaults.
		self::$smf_root = self::$smf_root === '' ? realpath($_SERVER['PWD']) : realpath(self::$smf_root);
		self::$output_dir = self::$output_dir === '' ? realpath($_SERVER['PWD'] . '/..') : realpath(self::$output_dir);
		self::$crowdin_api_key = self::$crowdin_api_key === '' ? getenv('CROWDIN_API_TOKEN') : self::$crowdin_api_key;
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
