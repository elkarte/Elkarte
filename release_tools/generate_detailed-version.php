<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

$output_file_name = 'detailed-version.js';

if (empty($argv[1]))
{
	echo "Please specify a branch to compare against master\n";
	die();
}
else
{
	$new_release = $argv[1];
}

if (empty($argv[2]))
{
	echo "Please specify a version\n";
	die();
}
else
{
	$new_version = $argv[2];
}

// Some constants and $settings needed to let getFileVersions do it's magic
DEFINE('ELK', '1');
DEFINE('BOARDDIR', dirname(__FILE__));
DEFINE('LANGUAGEDIR', BOARDDIR . '/themes/default/languages');
DEFINE('SOURCEDIR', BOARDDIR . '/sources');
DEFINE('ADMINDIR', SOURCEDIR . '/admin');
DEFINE('EXTDIR', SOURCEDIR . '/ext');
DEFINE('CONTROLLERDIR', SOURCEDIR . '/controllers');
DEFINE('SUBSDIR', SOURCEDIR . '/subs');

global $settings;
$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
$settings['theme_dir'] = BOARDDIR . '/themes/default';
$settings['theme_id'] = 1;

// Call the function that'll get all the version info we need.
require_once(SUBSDIR . '/Admin.subs.php');
$versionOptions = array(
	'include_ssi' => true,
	'include_subscriptions' => true,
	'sort_results' => true,
);
$version_info = getFileVersions($versionOptions);
$changed_files_list = getFilesChanged('master', $new_release);
$update_files = array();

// Now we need to grab the current version of the script from index.php
$index = file_get_contents(BOARDDIR . '/index.php');
$index_lines = explode("\n", $index);
foreach ($index_lines as $line)
{
	if (strpos($line, 'const FORUM_VERSION') !== false)
	{
		preg_match('~\'(ElkArte .*)\';$~', $line, $matches);
		$forum_version = $matches[1];
		break;
	}
}

$handle = fopen($output_file_name, 'w');

// Start with the common thing
fwrite($handle, 'window.ourVersions = {');
fwrite($handle, "\n\t'Version': '{$forum_version}',\n");

foreach (array('admin', 'controllers', 'database', 'subs') as $type)
{
	foreach ($version_info['file_versions_' . $type] as $file => $ver)
	{
		if ($new_version == $ver)
		{
			$update_files[] = $type . $file;
		}
		fwrite($handle, "\t'{$type}{$file}': '{$ver}',\n");
	}
}

foreach ($version_info['file_versions'] as $file => $ver)
{
	if ($new_version == $ver)
	{
		$update_files[] = 'sources' . $file;
	}
	fwrite($handle, "\t'sources{$file}': '{$ver}',\n");
}

foreach ($version_info['default_template_versions'] as $file => $ver)
{
	if ($new_version == $ver)
	{
		$update_files[] = 'default' . $file;
	}
	fwrite($handle, "\t'default{$file}': '{$ver}',\n");
}

// Let's close the "core" files and start the language files
fwrite($handle, '};');
fwrite($handle, "\n\nourLanguageVersions = {\n");

foreach ($version_info['default_language_versions'] as $lang => $files)
{
	if ($lang == 'english')
	{
		foreach ($files as $file => $ver)
		{
			fwrite($handle, "\t'{$file}': '{$ver}',\n");
		}
		break;
	}
}

// And that's all folks!
fwrite($handle, '};');

fclose($handle);
if (count(array_diff($update_files, $changed_files_list)) !== 0)
{
	echo "Something is wrong: at least one of the files updated is not in the list of those changed since the lastest version.\nThis is a list of the files affected by the problem:\n";
	print_r(array_diff($update_files, $changed_files_list));
}

if (count(array_diff($changed_files_list, $update_files)) !== 0)
{
	echo "Something is wrong: at least one of the files changed since the last released version has not been updated in the repository.\nThis is a list of the files affected by the problem:\n";
	print_r(array_diff($changed_files_list, $update_files));
}

/**
 * Get the listing of changed files between two releases
 *
 * @param string $from
 * @param string $to
 *
 * @return array
 */
function getFilesChanged($from, $to)
{
	global $settings;

	$output = shell_exec('git log --name-only --pretty=oneline --full-index ' . $from . '..' . $to . ' | grep -vE \'^[0-9a-f]{40} \' | sort | uniq');

	$dirs = array(
		str_replace(BOARDDIR . '/', '', SOURCEDIR . '/database/') => 'database',
		str_replace(BOARDDIR . '/', '', SUBSDIR . '/') => 'subs',
		str_replace(BOARDDIR . '/', '', CONTROLLERDIR . '/') => 'controllers',
		str_replace(BOARDDIR . '/', '', SOURCEDIR . '/') => 'sources',
		str_replace(BOARDDIR . '/', '', ADMINDIR . '/') => 'admin',
		str_replace(BOARDDIR . '/', '', ADMINDIR . '/') => 'admin',
		str_replace(BOARDDIR . '/', '', $settings['theme_dir'] . '/') => 'default',
	);

	$files = array_filter(explode("\n", $output));
	$list = array();
	foreach ($files as $file)
	{
		if (strpos($file, 'install') !== false)
		{
			continue;
		}

		if (strpos($file, '/ext') !== false)
		{
			continue;
		}

		if (strpos($file, 'tests') !== false)
		{
			continue;
		}

		if (strpos($file, 'fonts') !== false)
		{
			continue;
		}

		if (strpos($file, '/scripts') !== false)
		{
			continue;
		}

		if (strpos($file, '/images') !== false)
		{
			continue;
		}

		if (strpos($file, '/css') !== false)
		{
			continue;
		}

		if (strpos($file, '/languages') !== false)
		{
			continue;
		}

		if ($file === 'index.php')
		{
			continue;
		}

		if ($file === 'SSI.php')
		{
			$list[] = 'sourcesSSI.php';
			continue;
		}

		if ($file === 'subscriptions.php')
		{
			$list[] = 'sourcessubscriptions.php';
			continue;
		}

		$list[] = strtr($file, $dirs);
	}

	return $list;
}