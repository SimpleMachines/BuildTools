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

// Directories we need to work with.  Should be releative fo the translation export.
$directory21 = 'SMF_2-1';
$directory30 = 'SMF_3-0_NEXT';

// Get the listing of languages.
$dir = getcwd();
$langs21 = glob($dir . '/' . $directory21 . '/*');
$langs30 = glob($dir . '/' . $directory30 . '/Languages/*');

// Locales and language mapping between 2.1 and 3.0
$languageToLocalMap = [
	'albanian' => 'sq_AL',
	// 001 is the region code the whole world, so this means modern standard Arabic.
	'arabic' => 'ar_001',
	'bulgarian' => 'bg_BG',
	'cambodian' => 'km_KH',
	'catalan' => 'ca_ES',
	'chinese-simplified' => 'zh_Hans',
	'chinese-traditional' => 'zh_Hant',
	'croatian' => 'hr_HR',
	'czech' => 'cs_CZ',
	// Since 'informal' is not a locale, we just map this to the 'root' locale.
	'czech_informal' => 'cs',
	'danish' => 'da_DK',
	'dutch' => 'nl_NL',
	'english' => 'en_US',
	'english_british' => 'en_GB',
	// english_pirate isn't a real language, so we use the _x_ to mark it as a 'private language'.
	'english_pirate' => 'en_x_pirate',
	'esperanto' => 'eo',
	'finnish' => 'fi_FI',
	'french' => 'fr_FR',
	'galician' => 'gl_ES',
	'german' => 'de_DE',
	// Since 'informal' is not a locale, we just map this to the 'root' locale.
	'german_informal' => 'de',
	'greek' => 'el_GR',
	'hebrew' => 'he_IL',
	'hungarian' => 'hu_HU',
	'indonesian' => 'id_ID',
	'italian' => 'it_IT',
	'japanese' => 'ja_JP',
	'lithuanian' => 'lt_LT',
	'macedonian' => 'mk_MK',
	'malay' => 'ms_MY',
	'norwegian' => 'nb_NO',
	'persian' => 'fa_IR',
	'polish' => 'pl_PL',
	'portuguese_brazilian' => 'pt_BR',
	'portuguese_pt' => 'pt_PT',
	'romanian' => 'ro_RO',
	'russian' => 'ru_RU',
	// Cyrl indicates Cyrillic script.
	'serbian_cyrillic' => 'sr_Cyrl',
	// Latn indicates Latin script.
	'serbian_latin' => 'sr_Latn',
	'slovak' => 'sk_SK',
	'slovenian' => 'sl_SI',
	'spanish_es' => 'es_ES',
	// 419 is the region code for Latin America.
	'spanish_latin' => 'es_419',
	'swedish' => 'sv_SE',
	'thai' => 'th_TH',
	'turkish' => 'tr_TR',
	'ukrainian' => 'uk_UA',
	'urdu' => 'ur_PK',
	'vietnamese' => 'vi_VN',
];
$localeToLanguageMap = array_flip($languageToLocalMap);

// The file maping between 2.1 and 3.0
$fileMap = [
	'Admin' => 'Admin',
	'Alerts' => 'Alerts',
	'Agreement' => 'Agreement',
	'Drafts' => 'Drafts',
	'Editor' => 'Editor',
	'EmailTemplates' => 'EmailTemplates',
	'Errors' => 'Errors',
	'Help' => 'Help',
	'Login' => 'Login',
	'index' => 'General',
	'Install' => 'Install',
	'ManageBoards' => 'ManageBoards',
	'ManageCalendar' => 'ManageCalendar',
	'ManageMail' => 'ManageMail',
	'ManageMaintenance' => 'ManageMaintenance',
	'ManageMembers' => 'ManageMembers',
	'ManagePaid' => 'ManagePaid',
	'ManagePermissions' => 'ManagePermissions',
	'ManageScheduledTasks' => 'ManageScheduledTasks',
	'ManageSettings' => 'ManageSettings',
	'ManageSmileys' => 'ManageSmileys',
	'Manual' => 'Manual',
	'ModerationCenter' => 'ModerationCenter',
	'Modifications' => 'Modifications',
	'Modlog' => 'Modlog',
	'Post' => 'Post',
	'Profile' => 'Profile',
	'PersonalMessage' => 'PersonalMessage',
	'Packages' => 'Packages',
	'Reports' => 'Reports',
	'Search' => 'Search',
	'Stats' => 'Stats',
	'Timezones' => 'Timezones',
	'Themes' => 'Themes',
	'Who' => 'Who',
];

foreach ($langs21 as $l) {
	$locale = str_replace('-', '_', basename($l));
	if (!isset($langs[$locale])) {
		$langs[$locale] = [
			'21' => $l,
			'30' => null,
			'merged' => null
		];
	} else {
		$langs[$locale]['30'] = $l;
	}
};

foreach ($langs30 as $l) {
	$locale = basename($l);
	if (!isset($langs[$locale])) {
		$langs[$locale] = [
			'21' => null,
			'30' => $l,
			'merged' => str_replace('SMF_3-0_NEXT', 'MERGED', $l)
		];
	} else {
		$langs[$locale]['30'] = $l;
		$langs[$locale]['merged'] = str_replace('SMF_3-0_NEXT', 'MERGED', $l);
	}
};

if (is_dir($dir. '/MERGED')) {
	rrmdir($dir. '/MERGED');
}
rcopy($dir . '/' . $directory30, $dir. '/MERGED');

foreach ($langs as $locale => $lang) {

	if (!isset($localeToLanguageMap[$locale]) ) {
		echo 'Error, no map for ' . $locale . "\n";
		continue;
	}
	else if ($lang['30'] === null || $lang['merged'] === null) {
		echo 'Missing 3.0 folder for ' . $locale . "\n";
		continue;
	}
	else if ($lang['21'] === null) {
		echo 'Missing 2.1 folder for ' . $locale . "\n";
		continue;
	}

	foreach ($fileMap as $basefile21 => $basefile30) {

		foreach (['txt', 'txtBirthdayEmails', 'tztxt', 'editortxt', 'helptxt'] as $var) {
			${$var} = [];
		}

		$file30 = $lang['30'] . '/' . $basefile30 . '.php';
		$newFile = $lang['merged'] . '/' . $basefile30 . '.php';
		$file21 = $lang['21'] . '/Themes/default/languages/' . $basefile21 . '.' . $localeToLanguageMap[$locale] . '.php';

		// Crowdin exported some strings wrong.  Try to fix it.
		$contents21 = file_get_contents($file21);
		try {
			$contents21 = preg_replace('~\\\\\\\\\'~', '\\\\\\\\\\\'', $contents21);
			$contents21 = strtr($contents21, [
				'<' . '?' . 'php' => '',
				'?' . '>' => ''
			]);

			eval($contents21);
		}
		catch (ParseError $e) {
			var_dump($file21, $e);
			print($contents21);
			die;
		}

		$contents = file_get_contents($file30);

		foreach (['txt', 'txtBirthdayEmails', 'tztxt', 'editortxt', 'helptxt'] as $var) {
			replaceContents($contents, ${$var});
		}

		file_put_contents($newFile, $contents);
	}
}

function replaceContents(&$contents, array $vars) {
	foreach ($vars as $key => $value) {
		if (is_array($value)) {
			replaceContents($contents, $value);
		} else {
			$contents = preg_replace(
				'~\[\'' . $key . '\']\s+=\s+\'([^\';]+)\';~i',
				'[\'' . $key . '\'] = \'' . $value . '\';',
				$contents
			);
		}
	}
}

function rrmdir($dir) {
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($files as $fileinfo) {
		$todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
		$todo($fileinfo->getRealPath());
	}

	rmdir($dir);
}

function rcopy($source, $dest) {
	mkdir($dest, 0755);
	foreach (
	 $iterator = new \RecursiveIteratorIterator(
	  new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
	  \RecursiveIteratorIterator::SELF_FIRST) as $item
	) {
	  if ($item->isDir()) {
		mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
	  } else {
		copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
	  }
	}
}