<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 */

$output_file_name = 'detailed-version.js';

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

// Now we need to grab the current version of the script from index.php
$index = file_get_contents(BOARDDIR . '/index.php');
$index_lines = explode("\n", $index);
foreach ($index_lines as $line)
{
	if (strpos($line, '$forum_version') !== false)
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
	foreach ($version_info['file_versions_' . $type] as $file => $ver)
		fwrite($handle, "\t'{$type}{$file}': '{$ver}',\n");

foreach ($version_info['file_versions'] as $file => $ver)
	fwrite($handle, "\t'sources{$file}': '{$ver}',\n");

foreach ($version_info['default_template_versions'] as $file => $ver)
	fwrite($handle, "\t'default{$file}': '{$ver}',\n");

foreach ($version_info['default_template_versions'] as $file => $ver)
	fwrite($handle, "\t'default{$file}': '{$ver}',\n");

// Let's close the "core" files and start the language files
fwrite($handle, '};');
fwrite($handle, "\n\nourLanguageVersions = {\n");

foreach ($version_info['default_language_versions'] as $lang => $files)
{
	if ($lang == 'english')
	{
		foreach ($files as $file => $ver)
			fwrite($handle, "\t'{$file}': '{$ver}',\n");
		break;
	}
}

// And that's all folks!
fwrite($handle, '};');

fclose($handle);