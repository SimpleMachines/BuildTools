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
  * To use this tool: php ./other/buildPatch.php -s=repos/smf3.0/ -o=/tmp -f='v2.1.5' -t='v2.1.6'
  *         Will use the repos/smf3.0/ to build generate a upgrade file from 2.1.5 to 2.1.6 and output it to /tmp
  * This tool is designed to be standalone and relies on no dependencies.
  */
declare(strict_types=1);

// Ensure that we exit with a failure if an error occurs.
try {
	buildPatch::run();
}
catch (Exception $e) {
	fwrite(STDERR, $e->getMessage());
	exit(1);
}

class buildPatch
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
     * ID of the tag we are going to.
     * @var string
     */
	protected static string $to_tag = '';

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
     * What type of patching we are doing.  Either xml or diff
     * @var
     */
    protected static ?string $patch_type = null;

	/**
	 * A list of archives we will build.
	 * Currently this is:
	 *      ZIP
	 *      Tar.gz
	 *      Tar.bz2
	 * @var array
	 */
	protected static array $archives = [
		[Phar::TAR, Phar::GZ],
	];

	/**
	 * Map of CLI parameters to variables in this class.
	 *
	 * @var array
	 */
	protected static array $cli_param_map = [
		's' => 'smf_root',
		'f' => 'from_tag',
		't' => 'to_tag',
		'o' => 'output_dir',
        'p' => 'patch_type',
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

        // Setting up our from version.
        $from_tag_exists = trim(shell_exec('if [ $(git tag -l ' . escapeshellarg(self::$from_tag) . ') ]; then echo "true"; else echo ""; fi') ?? '');
        if (empty($from_tag_exists)) {
            throw new Exception('Unable to tag for ' . self::$from_tag);
        }
        // Get the version information.
        $from_index = trim(shell_exec('git show ' . escapeshellarg(self::$from_tag . ':index.php')) ?? '');

        // Validation of from version.
		if (!preg_match('/define\(\'SMF_VERSION\', \'([^\']+)\'\);/i', $from_index, $version)) {
			throw new Exception('Error: Could not locate SMF_VERSION in ' . self::$from_tag);
		}
        if (!preg_match('/(\d+)\.(\d+)\.(\d+)/i', $version[1])) {
            throw new Exception('Error: Version is not stable in ' . self::$from_tag);
        }
		$from_smf_version = $version[1];

        // Setting up our to version
        $to_tag_exists = trim(shell_exec('if [ $(git tag -l ' . escapeshellarg(self::$to_tag) . ') ]; then echo "true"; else echo ""; fi') ?? '');
        if (empty($from_tag_exists)) {
            throw new Exception('Unable to tag for ' . self::$to_tag);
        }
        // Get the version information.
        $to_index = trim(shell_exec('git show ' . escapeshellarg(self::$to_tag . ':index.php')) ?? '');

        // Validation of from version.
		if (!preg_match('/define\(\'SMF_VERSION\', \'([^\']+)\'\);/i', $to_index, $version)) {
			throw new Exception('Error: Could not locate SMF_VERSION in ' . self::$to_tag);
		}
        if (!preg_match('/(\d+)\.(\d+)\.(\d+)/i', $version[1])) {
            throw new Exception('Error: Version is not stable in ' . self::$to_tag);
        }
		$to_smf_version = $version[1];

        // Additional variables we need.
		$to_file_prefix = self::getFileNamePrefix($to_smf_version);
        $to_php_version = self::getPhpMinimumVersion(self::$to_tag);

        // Ensure we have a sane patch type.
        if (self::$patch_type === null || !in_array(self::$patch_type, ['xml', 'diff'])) {
            self::$patch_type = version_compare($to_smf_version, '3.0.0-alpha1', '<') ? 'xml' : 'diff';
        }

		self::writeDebug("[patch] Creating working folder");
  		$tmp_dir = self::$output_dir . '/' . $to_file_prefix . 'patch' . DIRECTORY_SEPARATOR;
        if (empty($tmp_dir)) {
            throw new Exception("Temp directory name missing");
        }

        // Cleanup any previous runs.
        @array_map('unlink', glob($tmp_dir . '/*'));
        @rmdir($tmp_dir);
        @mkdir($tmp_dir);

		// When we generate the patch, we may end up needing to do some special operations.
		$info_operations = [];

		self::writeDebug("[patch] Generating diff");
        shell_exec('git diff -p -M -C -C -B --default-prefix --no-relative ' . escapeshellarg(self::$from_tag) . '...' . escapeshellarg(self::$to_tag) . ' > ' . escapeshellcmd($tmp_dir . $to_file_prefix . 'patch.diff'));

        // Running something below 3.0
        if (self::$patch_type === 'xml') {
		    self::writeDebug("[patch] Converting to xml");
            self::convertDiffToPatch($tmp_dir . $to_file_prefix . 'patch.diff', $to_smf_version, $tmp_dir, $to_file_prefix, $info_operations);

    		self::writeDebug(msg: "[patch] Cleaning up diff");
            unlink($tmp_dir . $to_file_prefix . 'patch.diff');
        }

        //template for our package info file.
		self::writeDebug("[patch] Building info file");
        $infoFileContents = self::packageInfoTemplate($to_smf_version, $to_file_prefix, $from_smf_version, $to_php_version, self::$patch_type, $info_operations);

		self::writeDebug(msg: "[patch] Writing info file");
        file_put_contents($tmp_dir . 'package-info.xml', $infoFileContents);

		// Sort the output so its more organized.
		self::sortPatchFile($tmp_dir . $to_file_prefix . 'patch.xml');

		// Apply some qualify of life fixes.
		self::cleanupPatchFile($tmp_dir . $to_file_prefix . 'patch.xml');

		$tmp_file = self::$output_dir . '/' . $to_file_prefix;
        $build = 'patch';

        // Ensure we run a clean setup for the build.
        @array_map('unlink', glob($tmp_file . $build . '.*'));

        foreach (self::$archives as $a) {
            $extension = $a[0] === Phar::ZIP ? 'zip' : ($a[1] === Phar::GZ ? 'tar.gz' : 'tar.bz2');

            self::writeDebug("[patch] [$extension] Creating empty archive");

            $pd = new PharData(
                $tmp_file . $build . '.tmp',
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
                null,
                $a[0],
            );

            // Quickly now, use a iterator to build the main archive.
            self::writeDebug("[$build] [$extension] Adding initial files");
            $pd->buildFromIterator(new GlobIterator(pattern: $tmp_dir . DIRECTORY_SEPARATOR . '*'), $tmp_dir . DIRECTORY_SEPARATOR);

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

        // Cleanup.
        @array_map('unlink', glob($tmp_dir . '/*'));
        @array_map('unlink', glob($tmp_dir . '/.*'));
        @rmdir($tmp_dir);
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
				. '$ php ' . basename(__FILE__) . " -s=path/to/smf/ -o=/tmp -f=3.0.1 -t=3.0.2  \n"
				. '--s=/path/to/smf     Where SMF has its files' . "\n"
				. '--o=/path/to/out     Where to store the generated files' . "\n"
				. '--f=tag_id        	Tag in git for our source version.' . "\n"
				. '--t=tag_id        	Tag in git for our target version.' . "\n"
				. '-p=xml               The of patch file (xml or diff)' . "\n"
				. '-h, --help           This help file.' . "\n"
				. '-d, --debug          Prints out more debug info.' . "\n"
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

	/**
	 * Generates the package-info.xml File
	 *
	 * @param string $version SMF version we are going to.
	 * @param string $file_version SMF version file prefix.
	 * @param string $previous_version The previous SMF version (friendly)
	 * @param string $min_php_version Minimum version of PHP supported for the version we are going to.
	 * @param array $info_operations Additional operations to perform.
	 * @return string XML data for package-info.xml
	 */
    protected static function packageInfoTemplate(string $version, string $file_version, string $previous_version, string $min_php_version, string $extension, array $info_operations) {
        $template = <<<END
<?xml version="1.0"?>
<!DOCTYPE package-info SYSTEM "http://www.simplemachines.org/xml/package-info">
<package-info xmlns="http://www.simplemachines.org/xml/package-info" xmlns:smf="http://www.simplemachines.org/">
	<id>smf:smf-{$version}</id>
	<name>SMF {$version} Update</name>
	<version>{$version}</version>
	<type>modification</type>

	<install for="{$previous_version}">
		<readme type="inline" parsebbc="true">This will update your forum to SMF {$version}.</readme>
		<code type="inline"><![CDATA[<?php
			define('REQUIRED_PHP_VERSION', '{$min_php_version}');
			if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
				fatal_error('This update requires a minimum of PHP ' . REQUIRED_PHP_VERSION . ' in order to function. (You are currently running PHP ' . PHP_VERSION . ')');
			}

			// Update smfVersion.
			updateSettings(array('smfVersion' => '{$version}'));
		?>]]></code>
		<modification format="{$extension}">{$file_version}patch.{$extension}</modification>
END;

		foreach ($info_operations['remove-file'] ?? [] as $rm) {
			$template .= '
		<remove-file name="' . $rm . '" />';
		}

		$template .= <<<END

	</install>
	<uninstall for="{$version}">
		<readme type="inline" parsebbc="true">This will remove the changes introduced by SMF {$version}. [b]This is generally not a good idea.[/b]</readme>
		<modification format="diff" reverse="true">{$file_version}patch.diff</modification>
		<code type="inline"><![CDATA[<?php updateSettings(array('smfVersion' => '{$previous_version}'));]]></code>
END;

		foreach ($info_operations['remove-file'] ?? [] as $rm) {
			$file_base = basename($rm);
			$file_path = dirname($rm);

			$template .= '
		<require-file name="' . $file_base . '" destination="' . $file_path . '" />';
		}

		$template .= '
	</uninstall>
</package-info>';

        return $template;
    }

	/**
	 * Given a git tag, find the minimum version of PHP it supports.
	 * This will search in locations for SMF 3.0 (Sources/Maintenance/Maintenance.php) and 2.x (other/install.php)
	 *
	 * @param string $tag (git tag -l)
	 * @throws \Exception
	 * @return string Minimum PHP version supported
	 */
    protected static function getPhpMinimumVersion(string $tag): string
    {
        // SMF 3.0 way.
        $maintenance_file = trim(shell_exec('git show ' . escapeshellarg($tag . ':Sources/Maintenance/Maintenance.php') . ' 2> /dev/null || echo ""') ?? '');

        if (!empty($maintenance_file)) {
            if (!preg_match('/public\s*const\s*PHP_MIN_VERSION\s*=\s*\'([^\']+)\';/i', $maintenance_file, $version)) {
                throw new Exception('Error: Unable to parse PHP version from installer in ' . $tag);
            }

            return $version[1];
        }

        // SMF 2.1 and below.
        $install_file = trim(shell_exec('git show ' . escapeshellarg($tag . ':other/install.php') . ' 2> /dev/null || echo ""') ?? '');

        if (empty($install_file)) {
			throw new Exception('Error: Unable to read contents of installer in ' . $tag);
        }

		if (!preg_match('/\$GLOBALS\[\'required_php_version\'\]\s*=\s*\'([^\']+)\';/i', $install_file, $version)) {
			throw new Exception('Error: Unable to parse PHP version from installer in ' . $tag);
		}

        return $version[1];
    }

    /**
     * Given the contents of a diff file, attempt to parse our a valid XML data file.
     *
     * @param string $diff_file File path to the diff file.
     * @param string $version SMF Version we are going to.
	 * @param string $working_dir Working directory.
	 * @param string $to_file_prefix File prefix for this patch.
	 * @param array &$info_operations Operations passed onto our package-info.xml
     * @return void
     */
    protected static function convertDiffToPatch(string $diff_file, string $version, string $working_dir, string $to_file_prefix, array &$info_operations): void
    {
        $content = file($diff_file);
        $file_operations = [];
        $operations = [];
        $counter = 0;
        $opCounter = 0;
		$infoOperation = null;

        // First walk each line to figure out what we are doing.
        for ($i = 0; $i < count($content); $i++)
        {
            if (str_starts_with($content[$i], '--- a/'))
            {
                $directory = substr($content[$i], 6, strpos($content[$i], '/', 7) - 6);
                if ($directory == 'Sources')
                    $dir = '$source'. 'dir';
                elseif (strpos($content[$i], 'languages') !== false)
                    $dir = '$language'. 'dir';
                elseif (strpos($content[$i], 'images') !== false)
                    $dir = '$images'. 'dir';
                elseif (strpos($content[$i], 'default/scripts') !== false)
                    $dir = '$theme'. 'dir/scripts';
                elseif ($directory == 'Themes')
                    $dir = '$theme'. 'dir';
                else
                    $dir = '$board'. 'dir';

                $operations[$counter]['path'] = $dir . '/' . basename($content[$i]);

				// Is this a file deletion?
            	if (str_starts_with($content[$i + 1], '+++ /dev/null')) {
					$file_operations['replace'] = [''];
					$infoOperation = basename(trim($content[$i]));
					$info_operations['remove-file'][] = $dir . '/' . $infoOperation;
				}

				while (!str_starts_with($content[$i], '@@')) {
                    $i++;
				}
                continue;
            }

            // Appearing to start a new section, tie things off.
            /**
             * When we end a block of code, tie it off and add it as a operation
             * We do this when we detect:
             *      A new block (@@)
             *      A new file (diff --git)
             *      No more operations / EOF
             *
             * @author emanuele
             * @copyright 2012 emanuele, Simple Machines
             * @license http://www.simplemachines.org/about/smf/license.php BSD
             */
            if (
                (str_starts_with($content[$i], '@@')
                || str_starts_with($content[$i], 'diff --git')
                || !isset($content[$i + 1])
                ) && !empty($file_operations))
            {
				// If this was a special info operation, don't do this.
				if ($infoOperation !== null) {
					self::writeDebug("[patch] Writing file {$infoOperation}");

					file_put_contents($working_dir . DIRECTORY_SEPARATOR . $infoOperation, $file_operations['search']);
					unset($operations[$counter]);
					$infoOperation = null;
				}
				else {
					$operations[$counter]['operations'][$opCounter]['search'] = str_replace(['<![CDATA[', ']]>'], ['<![CDA\' . \'TA[', ']\' . \']>'], implode("", $file_operations['search']));
					$operations[$counter]['operations'][$opCounter]['replace'] = str_replace(['<![CDATA[', ']]>'], ['<![CDA\' . \'TA[', ']\' . \']>'], implode("", $file_operations['replace']));

					$opCounter++;
					if (str_starts_with($content[$i], 'diff --git'))
					{
						$dir = '';
						$counter++;
					}
				}

                $file_operations = [];
                continue;
            }
            if (!empty($dir))
            {
                if (str_starts_with($content[$i], ' '))
                {
                    $file_operations['replace'][] = $file_operations['search'][] = substr($content[$i], 1);
                }
                if (str_starts_with($content[$i], '-'))
                    $file_operations['search'][] = substr($content[$i], 1);
                elseif (str_starts_with($content[$i], '+'))
                    $file_operations['replace'][] = substr($content[$i], 1);
            }
        }

        // Build the data.
	    $ret = '<?xml version="1.0"?>
<!DOCTYPE modification SYSTEM "http://www.simplemachines.org/xml/modification">
<modification xmlns="http://www.simplemachines.org/xml/modification" xmlns:smf="http://www.simplemachines.org/">

	<id>smf:' . $version . '</id>
	<version>' . $version . '</version>';

		foreach ($operations as $file)
		{
			$ret .= '
	<file name="' . $file['path'] . '">';

			foreach ($file['operations'] as $file_operations)
				$ret .= '
		<operation>
			<search position="replace"><![CDATA[' .
				$file_operations['search'] . ']]></search>
			<add><![CDATA[' .
				$file_operations['replace'] . ']]></add>
		</operation>';

			$ret .= '
	</file>';
		}

		$ret .= '
</modification>';

		self::writeDebug("[patch] Writing patch.xml");
		file_put_contents($working_dir . DIRECTORY_SEPARATOR . $to_file_prefix . 'patch.xml', $ret);
    }

	/**
	 * Given a patch file, apply our XLS template to automatically sort the contents.
	 * @param string $output_file
	 * @return void
	 */
	protected static function sortPatchFile(string $output_file): void
	{
		$xml1 = new DOMDocument();
		$xml1->load($output_file);

		$xslt = new DOMDocument();
		$xslt->loadXML(self::xlsTemplate());

		$proc = new XSLTProcessor();
		$proc->importStylesheet($xslt);
		$proc->transformToURI($xml1, 'file://' . $output_file);
	}

    /**
     * A template for sorting our XML output.
     * Not required just makes things easier to read.
     *
     * @return string XML Template
     */
	protected static function xlsTemplate(): string
	{
		return <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
				xmlns:smf="http://www.simplemachines.org/"
				xmlns:mod="http://www.simplemachines.org/xml/modification"
				exclude-result-prefixes="smf mod">

	<xsl:output method="xml" indent="yes" cdata-section-elements="mod:search mod:add" doctype-system="http://www.simplemachines.org/xml/modification"/>

	<xsl:template match="@* | node()">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="mod:modification">
		<xsl:copy>
			<xsl:text>&#x0A;&#x09;</xsl:text>
			<xsl:apply-templates select="mod:id"/>
			<xsl:text>&#x0A;&#x09;</xsl:text>
			<xsl:apply-templates select="mod:version"/>
			<xsl:apply-templates select="mod:file">
				<xsl:sort select="@name"/>
			</xsl:apply-templates>
			<xsl:text>&#x0A;</xsl:text>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="mod:file">
		<xsl:text disable-output-escaping="yes">&#x0A;&#x0A;&#x09;&lt;!-- 2.1.6 updates for </xsl:text>
		<xsl:value-of select="@name"/>
		<xsl:text disable-output-escaping="yes"> --&gt;&#x0A;&#x09;</xsl:text>
		<xsl:copy>
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>
</xsl:stylesheet>
EOF;
	}

	/**
	 * Perform some cleanup operations that just make things easier.
	 * 
	 * @param string $output_file
	 * @return void
	 */
	protected static function cleanupPatchFile(string $output_file): void
	{
		$contents = file_get_contents($output_file);

		// Be more precise with changes to license blocks.
		$contents = preg_replace(
	'~		<operation>
			<search position="replace"><!\[CDATA\[( \* @copyright \d{4} Simple Machines and individual contributors)
 \* @license https://www\.simplemachines\.org/about/smf/license\.php BSD
 \*
( \* @version \d\.\d\.\d)
\]\]></search>
			<add><!\[CDATA\[( \* @copyright \d{4} Simple Machines and individual contributors)
 \* @license https://www\.simplemachines\.org/about/smf/license\.php BSD
 \*
( \* @version \d\.\d\.\d)
\]\]></add>
		</operation>~',
	'		<operation>
			<search position="replace"><![CDATA[$1]]></search>
			<add><![CDATA[$3]]></add>
		</operation>
		<operation>
			<search position="replace"><![CDATA[$2]]></search>
			<add><![CDATA[$4]]></add>
		</operation>',
	$contents,
		);

		// Additional version fixing.
		$contents = preg_replace(
	'~		<operation>
			<search position="replace"><!\[CDATA\[(define\(\'SMF_VERSION\', \'\d\.\d\.\d\'\);)
define\(\'SMF_FULL_VERSION\', \'SMF \' . SMF_VERSION\);
(define\(\'SMF_SOFTWARE_YEAR\', \'\d{4}\'\);)
\]\]></search>
			<add><!\[CDATA\[(define\(\'SMF_VERSION\', \'\d\.\d\.\d\'\);)
define\(\'SMF_FULL_VERSION\', \'SMF \' . SMF_VERSION\);
(define\(\'SMF_SOFTWARE_YEAR\', \'\d{4}\'\);)
\]\]></add>
		</operation>~',
	'		<operation>
			<search position="replace"><![CDATA[$1]]></search>
			<add><![CDATA[$3]]></add>
		</operation>
		<operation>
			<search position="replace"><![CDATA[$2]]></search>
			<add><![CDATA[$4]]></add>
		</operation>',
	$contents,
		);

		// Would you guess we are doing more cleaning of the version updates?
		$contents = preg_replace(
	'~		<operation>
			<search position="replace"><!\[CDATA\[( \* @copyright \d{4} Simple Machines and individual contributors)
 \* @license https://www\.simplemachines\.org/about/smf/license\.php BSD
 \*
( \* @version \d\.\d\.\d)
(\X*?)(define\(\'SMF_VERSION\', \'\d\.\d\.\d\'\);)
(\X*?)(define\(\'SMF_SOFTWARE_YEAR\', \'\d{4}\'\);)
\]\]></search>
			<add><!\[CDATA\[( \* @copyright \d{4} Simple Machines and individual contributors)
 \* @license https://www\.simplemachines\.org/about/smf/license\.php BSD
 \*
( \* @version \d\.\d\.\d)
\3(define\(\'SMF_VERSION\', \'\d\.\d\.\d\'\);)
\5(define\(\'SMF_SOFTWARE_YEAR\', \'\d{4}\'\);)
\]\]></add>
		</operation>~',
	'		<operation>
			<search position="replace"><![CDATA[$1]]></search>
			<add><![CDATA[$7]]></add>
		</operation>
		<operation>
			<search position="replace"><![CDATA[$2]]></search>
			<add><![CDATA[$8]]></add>
		</operation>
		<operation>
			<search position="replace"><![CDATA[$4]]></search>
			<add><![CDATA[$9]]></add>
		</operation>
		<operation>
			<search position="replace"><![CDATA[$6]]></search>
			<add><![CDATA[$10]]></add>
		</operation>',
	$contents,
		);

		// Get rid of useless ending newlines in replace statements.
		$contents = preg_replace('~(<search position="replace"><!\[CDATA\[)((?:\X(?!\]\]>))*)\n(\]\]></search>\s+<add><!\[CDATA\[)((?:\X(?!\]\]>))*)\n(\]\]></add>)~', '$1$2$3$4$5', $contents);

		// Move ending newlines to start in before statements.
		$contents = preg_replace('~(<search position="before"><!\[CDATA\[)((?:\X(?!\]\]>))*)\n(\]\]></search>\s+<add><!\[CDATA\[)((?:\X(?!\]\]>))*)\n(\]\]></add>)~', '$1' . "\n" . '$2$3' . "\n" . '$4$5', $contents);

		file_put_contents($output_file, $contents);
	}
}