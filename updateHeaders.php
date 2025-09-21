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
  * To use this tool: php ./other/updateHeaders.php -s=repos/smf3.0/ -o=/tmp -f='v2.1.6' -name=2.1.7
  *         Will use the repos/smf3.0/ find all files changed between 2.1.5 to checked out release and update their headers.
  * This tool is designed to be standalone and relies on no dependencies.
  */
declare(strict_types=1);

// Ensure that we exit with a failure if an error occurs.
try {
	updateHeaders::run();
} catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());

	exit(1);
}

class updateHeaders
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
	 * ID of the tag we are starting from.
	 * @var string
	 */
	protected static string $from_tag = '';

	/**
	 * ID of the branch we are going to.
	 * @var string
	 */
	protected static string $to_branch = '';

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
	 * SMF Version we are going to.
	 *
	 * @var string
	 */
	protected static string $to_smf_version = '';

	/**
	 * Map of CLI parameters to variables in this class.
	 *
	 * @var array
	 */
	protected static array $cli_param_map = [
		's' => 'smf_root',
		'f' => 'from_tag',
		'h' => 'help',
		'd' => 'debug',
		'help' => 'help',
		'debug' => 'debug',
		'name' => 'to_smf_version',
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

		// Setting up our from version.
		$from_tag_exists = trim(shell_exec('if [ $(git tag -l ' . escapeshellarg(self::$from_tag) . ') ]; then echo "true"; else echo ""; fi') ?? '');

		if (empty($from_tag_exists)) {
			throw new Exception('Unable to tag for ' . self::$from_tag);
		}

		// Setting up our to version
		$to_branch_exists = trim(shell_exec('git rev-parse --abbrev-ref HEAD') ?? '');

		if (empty($to_branch_exists)) {
			throw new Exception('Unable to tag for ' . self::$to_smf_version);
		}

		// Get the version information.
		if (empty(self::$to_smf_version)) {
			throw new Exception('Error: Version is not stable in current branch');
		}

        $files = explode(PHP_EOL, shell_exec('git diff --name-only v2.1.6 release-2.1'));

        if (empty($files)) {
			throw new Exception('Error: Unable to find any new files');
        }

        // Ensure we force update these files.
        $files[] = 'index.php';
        $files[] = 'SSI.php';
        $files[] = 'proxy.php';
        $files[] = 'cron.php';

        // Get the current year.
        $current_year = date('Y', time());

        foreach ($files as $file) {
            if (empty($file) || !file_exists($file) || str_starts_with($file, 'other/')) {
                continue;
            }

            if (str_starts_with($file, 'Sources/') || str_starts_with($file, 'Themes/default')) {
                $length = 4000;

                $replacements = [
                    '~(\r?\n\s+)\* @copyright \d{4} Simple Machines and individual contributors(\s+)~' => '\1* @copyright ' . $current_year . ' Simple Machines and individual contributors\2',
                    '~(\r?\n\s+)\* @version \d\.\d(?:\.\d)?(\s+)~' => '\1* @version ' . self::$to_smf_version . '\2',
                ];
            } else if (str_starts_with($file, 'Themes/default/languages')) {
                $length = 300;

                $replacements = [
                    '~(\r?\n\s*)\/\/ Version: \d\.\d(?:\.\d)?;~' => '\1// Version: ' . self::$to_smf_version . ';'
                ];
            } else if (in_array($file, ['index.php', 'cron.php', 'proxy.php', 'SSI.php'])) {
                $length = 4000;

                $replacements = [
                    '~(\r?\n\s+)\* @copyright \d{4} Simple Machines and individual contributors(\s+)~' => '\1* @copyright ' . $current_year . ' Simple Machines and individual contributors\2',
                    '~(\r?\n\s+)\* @version \d\.\d(?:\.\d)?(\s+)~' => '\1* @version ' . self::$to_smf_version . '\2',
                    '~define\(\'SMF_VERSION\', \'([^\']+)\'\);~' => 'define(\'SMF_VERSION\', \'' . self::$to_smf_version . '\');'
                ];
            }
             else {
                self::writeDebug('[SKIP] Unknown file {$file}');
                continue;
            }

            // PHP doesn't offer a way to insert in the middle of a line.  So we use a temp file.
            self::writeDebug('[Updating] {$file}');
            $fr = fopen($file, 'r');
            $fw = fopen($file . '~', 'w+');

            if ($fr === false || $fw === false) {
                throw new Exception('Error: Unable to open file [' . $file . '] for read and write');
            }

            // The first read should have our header.
            $contents = fread($fr, $length);

            // Perform replacements.
            $contents = preg_replace(array_keys($replacements), array_values($replacements), $contents);
            fwrite($fw,$contents);

            // Write out rest of the file.
            while (!feof($fr)) {
                fwrite($fw, fread($fr, $length));
            }

            fclose($fr);
            fclose($fw);

            // Out with the old, in with the new.
            unlink($file);
            rename($file . '~', $file);
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

				if (!isset(self::$cli_param_map[ltrim($var, '-')])) {
					continue;
				}

				self::${self::$cli_param_map[ltrim($var, '-')]} = $val;
			} elseif (isset(self::$cli_param_map[ltrim($param, '-')])) {
				self::${self::$cli_param_map[ltrim($param, '-')]} = true;
			}
		}

		// Need help, hopefully not.
		if (empty($params) || self::$help) {
			echo 'SMF Update Headers tool' . "\n"
				. '$ php ' . basename(__FILE__) . " -s=path/to/smf/ -o=/tmp -f=3.0.1 -t=3.0.2  \n"
				. '-s=/path/to/smf     Where SMF has its files' . "\n"
				. '-f=tag_id        	Tag in git for our source version.' . "\n"
				. '--name=VERSION       SMF version to be named.' . "\n"
				. '-h, --help           This help file.' . "\n"
				. '-d, --debug          Prints out more debug info.' . "\n"

				. "\n";

			die;
		}
		unset($params);

		// Defaults.
		self::$smf_root = self::$smf_root === '' ? realpath($_SERVER['PWD']) : realpath(self::$smf_root);
	}

	/**
	 * Write a debug output.
	 *
	 * @param string $msg
	 */
	protected static function writeDebug(string $msg): void
	{
		if (self::$debug) {
			fwrite(STDOUT, $msg . "\n");
			flush();
		}
	}
}
