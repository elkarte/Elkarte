<?php

/**
 * @package ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Beta 1
 *
 */

$output_file_name = 'detailed-version.js';
if (empty($argv[1]) && empty($_GET['b']))
{
	echo "Please specify a branch to compare against master, (for example: b=patch_1-1-9)\n";
	die();
}

$new_release = $argv[1] ?? $_GET['b'];

if (empty($argv[2]) && empty($_GET['v']))
{
	echo "Please specify a version to check (for example v=1.1.9)\n";
	die();
}
$new_version = $argv[2] ?? $_GET['v'];

// Some constants and $settings needed to let getFileVersions do it's magic
DEFINE('ELK', '1');
DEFINE('BOARDDIR', __DIR__);
DEFINE('LANGUAGEDIR', BOARDDIR . '/sources/ElkArte/Languages');
DEFINE('SOURCEDIR', BOARDDIR . '/sources');
DEFINE('ADMINDIR', SOURCEDIR . '/ElkArte/AdminController');
DEFINE('EXTDIR', SOURCEDIR . '/ext');
DEFINE('CONTROLLERDIR', SOURCEDIR . '/Controllers');
DEFINE('SUBSDIR', SOURCEDIR . '/subs');
DEFINE('ADDONSDIR', BOARDDIR . '/Addons');
DEFINE('ELKARTEDIR', SOURCEDIR . '/ElkArte');

global $settings;
$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
$settings['theme_dir'] = BOARDDIR . '/themes/default';
$settings['theme_id'] = 1;

// Call the function that'll get all the version info we need.
require_once(SUBSDIR . '/Admin.subs.php');
$versionOptions = [
	'include_ssi' => true,
	'include_subscriptions' => true,
	'sort_results' => true,
];

// Use our function to read all file headers and get the stated version
$version_info = getFileVersions($versionOptions);

// Use git to get our list of file changed by commit
echo 'Getting changed files from branch ' . $new_release . ' compared to master branch<br>';
$changed_files_list = getFilesChanged('master', $new_release);
$update_files = [];

// Now we need to grab the current version of the forum from index.php
$index = file_get_contents(BOARDDIR . '/bootstrap.php');
$index_lines = explode("\n", $index);
foreach ($index_lines as $line)
{
	if (str_contains($line, "define('FORUM_VERSION"))
	{
		preg_match('~\'ElkArte (.*)\'\);$~', $line, $matches);
		$forum_version = $matches[1];
		break;
	}
}

echo 'Checking Changed File headers match expected ' . $forum_version . '<br>';

$handle = fopen($output_file_name, 'wb');

// Start with the common thing
fwrite($handle, 'window.ourVersions = {');
fwrite($handle, "\n\t'Version': '{$forum_version}',\n");

foreach (['admin', 'controllers', 'database', 'subs'] as $type)
{
	foreach ($version_info['file_versions_' . $type] as $file => $ver)
	{
		if ($new_version === $ver)
		{
			$update_files[] = str_replace('subssubs', 'subs', $type . $file);
		}

		fwrite($handle, "\t'{$type}{$file}': '{$ver}',\n");
	}
}

foreach ($version_info['file_versions_modules'] as $file => $ver)
{
	if ($new_version === $ver)
	{
		$update_files[] = 'sources' . $file;
	}

	fwrite($handle, "\t'{$type}{$file}': '{$ver}',\n");
}

foreach ($version_info['file_versions'] as $file => $ver)
{
	if ($new_version === $ver)
	{
		$update_files[] = 'sources' . $file;
	}

	fwrite($handle, "\t'sources{$file}': '{$ver}',\n");
}

foreach ($version_info['default_template_versions'] as $file => $ver)
{
	if ($new_version === $ver)
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
	if ($lang === 'english')
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
if (array_diff($update_files, $changed_files_list) !== [])
{
	if (empty($_GET))
	{
		echo "Something is wrong: at least one of the files updated is not in the list of those changed since the lastest version.\nThis is a list of the files affected by the problem:\n";
		print_r(array_diff($update_files, $changed_files_list));
	}
	else
	{
		echo "Something is wrong:<br>At least one of the files updated is not in the list of those changed since the lastest version.<br>This is a list of the files affected by the problem:<br>";
		echo implode('<br>', array_diff($update_files, $changed_files_list));
	}
}

if (array_diff($changed_files_list, $update_files) !== [])
{
	if (empty($_GET))
	{
		echo "Something is wrong: at least one of the files changed since the last released version has not been updated in the repository.\nThis is a list of the files affected by the problem:\n";
		print_r(array_diff($changed_files_list, $update_files));
	}
	else
	{
		echo "Something is wrong:<br>At least one of the files changed since the last released version has not been updated in the repository.<br>This is a list of the files affected by the problem:<br>";
		echo implode('<br>', array_diff($changed_files_list, $update_files));
	}
}
else
{
	echo 'Successfully created detailed-version.js!';
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

	echo 'Running Command: git diff --name-only --pretty=oneline --full-index ElkArte/' . $from . '..ElkArte/' . $to . ' | sort | uniq<br>';

	$output = shell_exec('git diff --name-only --pretty=oneline --full-index ElkArte/' . $from . '..ElkArte/' . $to . ' | sort | uniq');
	if (empty($output))
	{
		echo "The git command failed to return any results\n";
		//die;
	}

	$dirs = [
		str_replace(BOARDDIR . '/', '', SOURCEDIR . '/database/') => 'database',
		str_replace(BOARDDIR . '/', '', SUBSDIR . '/') => 'subs',
		str_replace(BOARDDIR . '/', '', CONTROLLERDIR . '/') => 'controllers',
		str_replace(BOARDDIR . '/', '', SOURCEDIR . '/') => 'sources',
		str_replace(BOARDDIR . '/', '', ADMINDIR . '/') => 'admin',
		str_replace(BOARDDIR . '/', '', ADDONSDIR . '/') => 'addons',
		str_replace(BOARDDIR . '/', '', $settings['theme_dir'] . '/') => 'default',
	];

	$files = array_filter(explode("\n", $output));
	$list = [];
	foreach ($files as $file)
	{
		if ($file[0] === '.')
		{
			continue;
		}

		if (str_contains($file, 'README'))
		{
			continue;
		}

		if (str_contains($file, 'install'))
		{
			continue;
		}

		if (str_contains($file, 'release_tools'))
		{
			continue;
		}

		if (str_contains($file, '/ext'))
		{
			continue;
		}

		if (str_contains($file, 'tests'))
		{
			continue;
		}

		if (str_contains($file, 'fonts'))
		{
			continue;
		}

		if (str_contains($file, '/scripts'))
		{
			continue;
		}

		if (str_contains($file, 'docs/'))
		{
			continue;
		}

		if (str_contains($file, '/images'))
		{
			continue;
		}

		if (str_contains($file, '/css'))
		{
			continue;
		}

		if (str_contains($file, '/languages'))
		{
			continue;
		}

		if (str_contains($file, 'packages'))
		{
			continue;
		}

		if (str_contains($file, '.txt') || str_contains($file, '.json'))
		{
			continue;
		}

		if ($file === 'index.php')
		{
			continue;
		}

		if ($file === 'ssi_examples.php')
		{
			continue;
		}

		if ($file === 'ssi_examples.shtml')
		{
			continue;
		}

		if ($file === 'elkServiceWorker.min.js')
		{
			continue;
		}

		if ($file === 'SSI.php')
		{
			$list[] = 'sourcesSSI.php';
			continue;
		}

		if ($file === 'subscriptions.php' || $file === 'bootstrap.php' || $file === 'email_imap_cron.php' || $file === 'emailpost.php' || $file === 'emailtopic.php')
		{
			$list[] = 'sources' . $file;
			continue;
		}

		$list[] = strtr($file, $dirs);
	}

	echo 'Found ' . count($list) . ' Changed Files<br>';

	return $list;
}

/**
 * Search through source, theme and language files to determine their version.
 * Get detailed version information about the physical Elk files on the server.
 *
 * What it does:
 *
 * - the input parameter allows to set whether to include SSI.php and whether
 *   the results should be sorted.
 * - returns an array containing information on source files, templates and
 *   language files found in the default theme directory (grouped by language).
 * - options include include_ssi, include_subscriptions, sort_results
 *
 * @param array $versionOptions associative array of options
 * @return array
 * @package Admin
 */
function getFileVersions(&$versionOptions)
{
	global $settings;

	// Default place to find the languages is now /sources/ElkArte/Languages.
	$lang_dir = SOURCEDIR . '/ElkArte/Languages';

	$version_info = [
		'file_versions' => [],
		'file_versions_admin' => [],
		'file_versions_controllers' => [],
		'file_versions_database' => [],
		'file_versions_subs' => [],
		'default_template_versions' => [],
		'template_versions' => [],
		'default_language_versions' => [],
	];

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists(BOARDDIR . '/SSI.php'))
	{
		readFileVersions($version_info, ['file_versions' => BOARDDIR], 'SSI.php');
	}

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']))
	{
		foreach ([
			         'subscriptions.php',
			         'bootstrap.php',
			         'email_imap_cron.php',
			         'emailpost.php',
			         'emailtopic.php'] as $file)
		{
			if (file_exists(BOARDDIR . '/' . $file))
			{
				readFileVersions($version_info, ['file_versions' => BOARDDIR], $file);
			}
		}
	}

	// Load all the files in the sources and its sub directories
	$directories = [
		'file_versions' => SOURCEDIR,
		'file_versions_admin' => ADMINDIR,
		'file_versions_controllers' => CONTROLLERDIR,
		'file_versions_database' => SOURCEDIR . '/database',
		'file_versions_lib' => EXTDIR
	];
	readFileVersions($version_info, $directories, '.php');
	$directories = [
		'file_versions_subs' => SUBSDIR,
		'file_versions_modules' => SOURCEDIR . '/modules',
	];
	$tmp_version_info = array_combine(array_keys($directories), array_fill(0, count($directories), []));
	readFileVersions($tmp_version_info, $directories, '.php', true);

	foreach ($tmp_version_info['file_versions_subs'] as $key => $val)
	{
		$version_info['file_versions_subs'][str_replace($directories['file_versions_subs'] . DIRECTORY_SEPARATOR, 'subs', $key)] = $val;
	}
	foreach ($tmp_version_info['file_versions_modules'] as $key => $val)
	{
		$version_info['file_versions_modules'][str_replace($directories['file_versions_modules'], 'modules', $key)] = $val;
	}
	// Load all the files in the default template directory - and the current theme if applicable.
	$directories = ['default_template_versions' => $settings['default_theme_dir']];
	if ((int) $settings['theme_id'] !== 1)
	{
		$directories += ['template_versions' => $settings['theme_dir']];
	}
	readFileVersions($version_info, $directories, 'template.php');
	readFileVersions($version_info, $directories, 'Theme.php');

	// Load up all the files in the default language directory and sort by language.
	// @todo merge this loop into readFileVersions
	$this_dir = dir($lang_dir);
	while ($path = $this_dir->read())
	{
		if ($path === '.' || $path === '..')
		{
			continue;
		}

		if (is_dir($lang_dir . '/' . $path))
		{
			$language = $path;
			$this_lang_path = $lang_dir . '/' . $language;
			$this_lang = dir($this_lang_path);
			while ($entry = $this_lang->read())
			{
				if (str_ends_with($entry, '.php') && $entry !== 'index.php' && !is_dir($this_lang_path . '/' . $entry))
				{
					if (!is_writable($this_lang_path . '/' . $entry))
					{
						continue;
					}
					// Read the first 768 bytes from the file.... enough for the header.
					$header = file_get_contents($this_lang_path . '/' . $entry, false, null, 0, 768);

					// Split the file name off into useful bits.
					list ($name, $language) = explode('.', $entry);

					// Look for the version comment in the file header.
					if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
					{
						$version_info['default_language_versions'][$language][$name] = $match[1];
					}
					// It wasn't found, but the file was... show a '??'.
					else
					{
						$version_info['default_language_versions'][$language][$name] = '??';
					}
				}
			}
		}
	}
	$this_dir->close();

	// Sort the file versions by filename.
	if (!empty($versionOptions['sort_results']))
	{
		ksort($version_info['file_versions']);
		ksort($version_info['file_versions_admin']);
		ksort($version_info['file_versions_controllers']);
		ksort($version_info['file_versions_database']);
		ksort($version_info['file_versions_subs']);
		ksort($version_info['default_template_versions']);
		ksort($version_info['template_versions']);
		ksort($version_info['default_language_versions']);

		// For languages sort each language too.
		foreach ($version_info['default_language_versions'] as $language => $dummy)
		{
			ksort($version_info['default_language_versions'][$language]);
		}
	}

	return $version_info;
}

/**
 * Read a directory searching for files with a certain pattern in the name
 *
 * @param array $version_info -
 * @param string[] $directories - an array of directories to loop
 * @param string $pattern - how the name of the files should end
 * @param bool $recursive - if scan recursively the directories
 */
function readFileVersions(&$version_info, $directories, $pattern, $recursive = false)
{
	// The comment looks roughly like... that.
	$version_regex = '~\*\s@version\s+(.+)[\s]{2}~i';
	$unknown_version = '??';

	$ext_offset = -strlen($pattern);

	foreach ($directories as $type => $dirname)
	{
		if ($recursive === true)
		{
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
			);
		}
		else
		{
			$iter = new IteratorIterator(new FilesystemIterator($dirname));
		}

		foreach ($iter as $dir)
		{
			if ($dir->isDir())
			{
				continue;
			}
			$entry = $dir->getFilename();

			if (substr($entry, $ext_offset) == $pattern)
			{
				if ($dir->isWritable() === false)
				{
					continue;
				}
				// Read the first 768 bytes from the file.... enough for the header.
				$header = file_get_contents($dir->getPathname(), false, null, 0, 768);

				if ($recursive === true)
				{
					$entry_key = $dir->getPathname();
				}
				else
				{
					$entry_key = $entry;
				}

				// Look for the version comment in the file header.
				if (preg_match($version_regex, $header, $match) === 1)
				{
					$version_info[$type][$entry_key] = $match[1];
				}
				// It wasn't found, but the file was... show a $unknown_version.
				else
				{
					$version_info[$type][$entry_key] = $unknown_version;
				}
			}
		}
	}
}
