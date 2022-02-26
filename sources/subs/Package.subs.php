<?php

/**
 * This contains functions for handling tar.gz and .zip files
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\FileFunctions;
use ElkArte\Http\CurlFetchWebdata;
use ElkArte\Http\FsockFetchWebdata;
use ElkArte\Http\FtpConnection;
use ElkArte\Http\StreamFetchWebdata;
use ElkArte\Packages\PackageChmod;
use ElkArte\Packages\PackageParser;
use ElkArte\UnTgz;
use ElkArte\UnZip;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\XmlArray;

/**
 * Reads a .tar.gz file, filename, in and extracts file(s) from it.
 * essentially just a shortcut for read_tgz_data().
 *
 * @param string $gzfilename
 * @param string $destination
 * @param bool $single_file = false
 * @param bool $overwrite = false
 * @param string[]|null $files_to_extract = null
 * @return array|bool
 * @package Packages
 */
function read_tgz_file($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	// From a web site
	if (substr($gzfilename, 0, 7) === 'http://' || substr($gzfilename, 0, 8) === 'https://')
	{
		$data = fetch_web_data($gzfilename);
	}
	// Or a file on the system
	else
	{
		$data = @file_get_contents($gzfilename);
	}

	if ($data === false)
	{
		return false;
	}

	return read_tgz_data($data, $destination, $single_file, $overwrite, $files_to_extract);
}

/**
 * Extracts a file or files from the .tar.gz contained in data.
 *
 * - Detects if the file is really a .zip file, and if so returns the result of read_zip_data
 *
 * if destination is null
 * - returns a list of files in the archive.
 *
 * if single_file is true
 * - returns the contents of the file specified by destination, if it exists, or false.
 * - destination can start with * and / to signify that the file may come from any directory.
 * - destination should not begin with a / if single_file is true.
 *
 * - existing files with newer modification times if and only if overwrite is true.
 * - creates the destination directory if it doesn't exist, and is is specified.
 * - requires zlib support be built into PHP.
 * - returns an array of the files extracted on success
 * - if files_to_extract is not equal to null only extracts the files within this array.
 *
 * @param string $data
 * @param string $destination
 * @param bool $single_file = false,
 * @param bool $overwrite = false,
 * @param string[]|null $files_to_extract = null
 * @return array|bool
 * @package Packages
 */
function read_tgz_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	$untgz = new UnTgz($data, $destination, $single_file, $overwrite, $files_to_extract);

	// Choose the right method for the file
	if ($untgz->check_valid_tgz())
	{
		return $untgz->read_tgz_data();
	}
	else
	{
		unset($untgz);

		return read_zip_data($data, $destination, $single_file, $overwrite, $files_to_extract);
	}
}

/**
 * Extract zip data.
 *
 * - If destination is null, return a listing.
 *
 * @param string $data
 * @param string $destination
 * @param bool $single_file
 * @param bool $overwrite
 * @param string[]|null $files_to_extract
 * @return array|bool
 * @package Packages
 */
function read_zip_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	$unzip = new UnZip($data, $destination, $single_file, $overwrite, $files_to_extract);

	return $unzip->read_zip_data();
}

/**
 * Loads and returns an array of installed packages.
 *
 * - Gets this information from packages/installed.list.
 * - Returns the array of data.
 * - Default sort order is package_installed time
 *
 * @return array
 * @package Packages
 */
function loadInstalledPackages()
{
	$db = database();

	// First, check that the database is valid, installed.list is still king.
	$install_file = @file_get_contents(BOARDDIR . '/packages/installed.list');
	if (trim($install_file) === '')
	{
		$db->query('', '
			UPDATE {db_prefix}log_packages
			SET 
				install_state = {int:not_installed}',
			array(
				'not_installed' => 0,
			)
		);

		// Don't have anything left, so send an empty array.
		return [];
	}

	// Load the packages from the database - note this is ordered by installation time to ensure
	// latest package uninstalled first.
	$installed = array();
	$found = array();
	$db->fetchQuery('
		SELECT 
			id_install, package_id, filename, name, version
		FROM {db_prefix}log_packages
		WHERE install_state != {int:not_installed}
		ORDER BY time_installed DESC',
		array(
			'not_installed' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$found, &$installed) {
			// Already found this? If so don't add it twice!
			if (in_array($row['package_id'], $found))
			{
				return;
			}

			$found[] = $row['package_id'];

			$installed[] = array(
				'id' => $row['id_install'],
				'name' => $row['name'],
				'filename' => $row['filename'],
				'package_id' => $row['package_id'],
				'version' => $row['version'],
			);
		}
	);

	return $installed;
}

/**
 * Loads a package's information and returns a representative array.
 *
 * - Expects the file to be a package in packages/.
 * - Returns a error string if the package-info is invalid.
 * - Otherwise returns a basic array of id, version, filename, and similar information.
 * - An \ElkArte\XmlArray is available in 'xml'.
 *
 * @param string $gzfilename
 *
 * @return array|string error string on error array on success
 * @package Packages
 */
function getPackageInfo($gzfilename)
{
	$gzfilename = trim($gzfilename);
	$fileFunc = FileFunctions::instance();

	// Extract package-info.xml from downloaded file. (*/ is used because it could be in any directory.)
	if (preg_match('~^https?://~i', $gzfilename) === 1)
	{
		$packageInfo = read_tgz_data(fetch_web_data($gzfilename, '', true), '*/package-info.xml', true);
	}
	else
	{
		// It must be in the package directory then
		if (!$fileFunc->fileExists(BOARDDIR . '/packages/' . $gzfilename))
		{
			return 'package_get_error_not_found';
		}

		// Make sure a package.xml file is available
		if ($fileFunc->fileExists(BOARDDIR . '/packages/' . $gzfilename))
		{
			$packageInfo = read_tgz_file(BOARDDIR . '/packages/' . $gzfilename, '*/package-info.xml', true);
		}
		elseif ($fileFunc->fileExists(BOARDDIR . '/packages/' . $gzfilename . '/package-info.xml'))
		{
			$packageInfo = file_get_contents(BOARDDIR . '/packages/' . $gzfilename . '/package-info.xml');
		}
		else
		{
			return 'package_get_error_missing_xml';
		}
	}

	// Nothing?
	if (empty($packageInfo))
	{
		// Perhaps they are trying to install a theme, lets tell them nicely this is the wrong function
		$packageInfo = read_tgz_file(BOARDDIR . '/packages/' . $gzfilename, '*/theme_info.xml', true);
		if (!empty($packageInfo))
		{
			return 'package_get_error_is_theme';
		}

		return 'package_get_error_is_zero';
	}

	// Parse package-info.xml into an \ElkArte\XmlArray.
	$packageInfo = new XmlArray($packageInfo);

	if (!$packageInfo->exists('package-info[0]'))
	{
		return 'package_get_error_packageinfo_corrupt';
	}

	$packageInfo = $packageInfo->path('package-info[0]');

	// Convert packageInfo to an array for use
	$package = Util::htmlspecialchars__recursive($packageInfo->to_array());
	$package['xml'] = $packageInfo;
	$package['filename'] = $gzfilename;

	// Set a default type if none was supplied in the package
	if (!isset($package['type']))
	{
		$package['type'] = 'modification';
	}

	return $package;
}

/**
 * Create a chmod control for chmoding files.
 *
 * @param string[] $chmodFiles
 * @param array $chmodOptions
 * @param bool $restore_write_status
 * @return array|bool
 * @package Packages
 * @deprecated since 2.0, use PackageChmod class
 */
function create_chmod_control($chmodFiles = array(), $chmodOptions = array(), $restore_write_status = false)
{
	$create_chmod_control = new PackageChmod();

	return $create_chmod_control->createChmodControl($chmodFiles, $chmodOptions, $restore_write_status);
}

/**
 * Get a listing of files that will need to be set back to the original state
 *
 * @param string $dummy1
 * @param string $dummy2
 * @param string $dummy3
 * @param bool $do_change
 *
 * @return array
 */
function list_restoreFiles($dummy1, $dummy2, $dummy3, $do_change)
{
	global $txt, $package_ftp;

	$restore_files = [];
	$fileFunc = FileFunctions::instance();

	foreach ($_SESSION['ftp_connection']['original_perms'] as $file => $perms)
	{
		// Check the file still exists, and the permissions were indeed different than now.
		$file_permissions = $fileFunc->filePerms($file);
		if (!$fileFunc->fileExists($file) || $file_permissions === $perms)
		{
			unset($_SESSION['ftp_connection']['original_perms'][$file]);
			continue;
		}

		// Are we wanting to change the permission?
		if ($do_change && isset($_POST['restore_files']) && in_array($file, $_POST['restore_files']))
		{
			// Use FTP if we have it.
			if (!empty($package_ftp))
			{
				$ftp_file = strtr($file, array($_SESSION['ftp_connection']['root'] => ''));
				$package_ftp->chmod($ftp_file, $perms);
			}
			else
			{
				$fileFunc->elk_chmod($file, $perms);
			}

			$new_permissions = $fileFunc->filePerms($file);
			$result = $new_permissions === $perms ? 'success' : 'failure';
			unset($_SESSION['ftp_connection']['original_perms'][$file]);
		}
		elseif ($do_change)
		{
			$new_permissions = '';
			$result = 'skipped';
			unset($_SESSION['ftp_connection']['original_perms'][$file]);
		}

		// Record the results!
		$restore_files[] = array(
			'path' => $file,
			'old_perms_raw' => $perms,
			'old_perms' => substr(sprintf('%o', $perms), -4),
			'cur_perms' => substr(sprintf('%o', $file_permissions), -4),
			'new_perms' => isset($new_permissions) ? substr(sprintf('%o', $new_permissions), -4) : '',
			'result' => $result ?? '',
			'writable_message' => '<span class="' . (@is_writable($file) ? 'success' : 'alert') . '">' . ($fileFunc->isWritable($file) ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']) . '</span>',
		);
	}

	return $restore_files;
}

/**
 * Parses the actions in package-info.xml file from packages.
 *
 * @param \ElkArte\XmlArray $packageXML
 * @param bool $testing_only = true
 * @param string $method = 'install' ('install', 'upgrade', or 'uninstall')
 * @param string $previous_version = ''
 * @return array an array of those changes made.
 * @package Packages
 * @deprecated since 2.0 use parsePackageInfo class
 */
function parsePackageInfo(&$packageXML, $testing_only = true, $method = 'install', $previous_version = '')
{
	$parser = new PackageParser();
	return $parser->parsePackageInfo($packageXML, $testing_only, $method, $previous_version);
}

/**
 * Checks if version matches any of the versions in versions.
 *
 * - Supports comma separated version numbers, with or without whitespace.
 * - Supports lower and upper bounds. (1.0-1.2)
 * - Returns true if the version matched.
 *
 * @param string $versions
 * @param bool $reset
 * @param string $the_version
 * @return string|bool highest install value string or false
 * @package Packages
 */
function matchHighestPackageVersion($versions, $the_version, $reset = false)
{
	static $near_version = 0;

	if ($reset)
	{
		$near_version = 0;
	}

	// Normalize the $versions
	$versions = explode(',', str_replace(' ', '', strtolower($versions)));

	// If it is not ElkArte, let's just give up
	list ($the_brand,) = explode(' ', FORUM_VERSION, 2);
	if ($the_brand !== 'ElkArte')
	{
		return false;
	}

	// Loop through each version, save the highest we can find
	foreach ($versions as $for)
	{
		// Adjust for those wild cards
		if (strpos($for, '*') !== false)
		{
			$for = str_replace('*', '0', $for) . '-' . str_replace('*', '999', $for);
		}

		// If we have a range, grab the lower value, done this way so it looks normal-er to the user e.g. 1.0 vs 1.0.99
		if (strpos($for, '-') !== false)
		{
			list ($for,) = explode('-', $for);
		}

		// Do the compare, if the for is greater, than what we have but not greater than what we are running .....
		if (compareVersions($near_version, $for) === -1 && compareVersions($for, $the_version) !== 1)
		{
			$near_version = $for;
		}
	}

	return !empty($near_version) ? $near_version : false;
}

/**
 * Checks if the forum version matches any of the available versions from the package install xml.
 *
 * - Supports comma separated version numbers, with or without whitespace.
 * - Supports lower and upper bounds. (1.0-1.2)
 * - Returns true if the version matched.
 *
 * @param string $version
 * @param string $versions
 * @return bool
 * @package Packages
 */
function matchPackageVersion($version, $versions)
{
	// Make sure everything is lowercase and clean of spaces.
	$version = str_replace(' ', '', strtolower($version));
	$versions = explode(',', str_replace(' ', '', strtolower($versions)));

	// Perhaps we do accept anything?
	if (in_array('all', $versions))
	{
		return true;
	}

	// Loop through each version.
	foreach ($versions as $for)
	{
		// Wild card spotted?
		if (strpos($for, '*') !== false)
		{
			$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);
		}

		// Do we have a range?
		if (strpos($for, '-') !== false)
		{
			list ($lower, $upper) = explode('-', $for);

			// Compare the version against lower and upper bounds.
			if (compareVersions($version, $lower) > -1 && compareVersions($version, $upper) < 1)
			{
				return true;
			}
		}
		// Otherwise check if they are equal...
		elseif (compareVersions($version, $for) === 0)
		{
			return true;
		}
	}

	return false;
}

/**
 * Compares two versions and determines if one is newer, older or the same, returns
 *
 * - (-1) if version1 is lower than version2
 * - (0) if version1 is equal to version2
 * - (1) if version1 is higher than version2
 *
 * @param string $version1
 * @param string $version2
 * @return int (-1, 0, 1)
 * @package Packages
 */
function compareVersions($version1, $version2)
{
	static $categories;

	$versions = array();
	foreach (array(1 => $version1, $version2) as $id => $version)
	{
		// Clean the version and extract the version parts.
		$clean = str_replace(' ', '', strtolower($version));
		preg_match('~(\d+)(?:\.(\d+|))?(?:\.)?(\d+|)(?:(alpha|beta|rc)(\d+|)(?:\.)?(\d+|))?(?:\s(dev))?(\d+|)~', $clean, $parts);

		// Build an array of parts.
		$versions[$id] = array(
			'major' => !empty($parts[1]) ? (int) $parts[1] : 0,
			'minor' => !empty($parts[2]) ? (int) $parts[2] : 0,
			'patch' => !empty($parts[3]) ? (int) $parts[3] : 0,
			'type' => empty($parts[4]) && empty($parts[7]) ? 'stable' : (!empty($parts[7]) ? 'alpha' : $parts[4]),
			'type_major' => !empty($parts[6]) ? (int) $parts[5] : 0,
			'type_minor' => !empty($parts[6]) ? (int) $parts[6] : 0,
			'dev' => !empty($parts[7]),
		);
	}

	// Are they the same, perhaps?
	if ($versions[1] === $versions[2])
	{
		return 0;
	}

	// Get version numbering categories...
	if (!isset($categories))
	{
		$categories = array_keys($versions[1]);
	}

	// Loop through each category.
	foreach ($categories as $category)
	{
		// Is there something for us to calculate?
		if ($versions[1][$category] !== $versions[2][$category])
		{
			// Dev builds are a problematic exception.
			// (stable) dev < (stable) but (unstable) dev = (unstable)
			if ($category === 'type')
			{
				return $versions[1][$category] > $versions[2][$category] ? ($versions[1]['dev'] ? -1 : 1) : ($versions[2]['dev'] ? 1 : -1);
			}
			elseif ($category === 'dev')
			{
				return $versions[1]['dev'] ? ($versions[2]['type'] === 'stable' ? -1 : 0) : ($versions[1]['type'] === 'stable' ? 1 : 0);
			}
			// Otherwise a simple comparison.
			else
			{
				return $versions[1][$category] > $versions[2][$category] ? 1 : -1;
			}
		}
	}

	// They are the same!
	return 0;
}

/**
 * Parses special identifiers out of the specified path.
 *
 * @param string $path
 * @return string The parsed path
 * @package Packages
 */
function parse_path($path)
{
	global $modSettings, $settings, $temp_path;

	if (empty($path))
	{
		return '';
	}

	$dirs = array(
		'\\' => '/',
		'BOARDDIR' => BOARDDIR,
		'SOURCEDIR' => SOURCEDIR,
		'SUBSDIR' => SUBSDIR,
		'ADMINDIR' => ADMINDIR,
		'CONTROLLERDIR' => CONTROLLERDIR,
		'EXTDIR' => EXTDIR,
		'ADDONSDIR' => ADDONSDIR,
		'AVATARSDIR' => $modSettings['avatar_directory'],
		'THEMEDIR' => $settings['default_theme_dir'],
		'IMAGESDIR' => $settings['default_theme_dir'] . '/' . basename($settings['default_images_url']),
		'LANGUAGEDIR' => SOURCEDIR . '/ElkArte/Languages',
		'SMILEYDIR' => $modSettings['smileys_dir'],
	);

	// Do we parse in a package directory?
	if (!empty($temp_path))
	{
		$dirs['PACKAGE'] = $temp_path;
	}

	// Check if they are using some old software install paths
	if (strpos($path, '$') === 0 && isset($dirs[strtoupper(substr($path, 1))]))
	{
		$path = strtoupper(substr($path, 1));
	}

	return strtr($path, $dirs);
}

/**
 * Deletes all the files in a directory, and all the files in sub directories inside it.
 *
 * What it does:
 *
 * - Requires access to delete these files.
 * - Recursively goes in to all sub directories looking for files to delete
 * - Optionally removes the directory as well, otherwise will leave an empty tree behind
 *
 * @param string $dir
 * @param bool $delete_dir = true
 * @package Packages
 */
function deltree($dir, $delete_dir = true)
{
	global $package_ftp;

	$fileFunc = FileFunctions::instance();

	if (!$fileFunc->isDir($dir))
	{
		return;
	}

	// Read all the files and directories in the parent directory
	$iterator = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
	$entrynames = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

	/** @var \SplFileInfo $entryname */
	foreach ($entrynames as $entryname)
	{
		if ($entryname->isDir() && $delete_dir)
		{
			if (isset($package_ftp))
			{
				$ftp_file = strtr($entryname->getRealPath(), array($_SESSION['ftp_connection']['root'] => ''));

				if (!$fileFunc->isWritable($ftp_file . '/'))
				{
					$package_ftp->chmod($ftp_file, 0777);
				}

				$package_ftp->unlink($ftp_file);
			}
			else
			{
				if (!$fileFunc->isWritable($entryname))
				{
					$fileFunc->chmod($entryname->getRealPath());
				}

				@rmdir($entryname->getRealPath());
			}
		}
		// A file, delete it by any means necessary
		else
		{
			if (isset($package_ftp))
			{
				// Here, 755 doesn't really matter since we're deleting it anyway.
				$ftp_file = strtr($entryname->getPathname(), array($_SESSION['ftp_connection']['root'] => ''));

				if (!$fileFunc->isWritable($ftp_file))
				{
					$package_ftp->chmod($ftp_file, 0777);
				}

				$package_ftp->unlink($ftp_file);
			}
			else
			{
				if (!$entryname->isWritable())
				{
					$fileFunc->chmod($entryname->getRealPath());
				}

				$fileFunc->delete($entryname->getRealPath());
			}
		}
	}

	// Finish off with the directory itself
	if ($delete_dir)
	{
		if (isset($package_ftp))
		{
			$ftp_file = strtr(realpath($dir), array($_SESSION['ftp_connection']['root'] => ''));
			$package_ftp->unlink($ftp_file);
		}
		else
		{
			@rmdir(realpath($dir));
		}
	}
}

/**
 * Creates the specified tree structure with a mode that permits write access.
 *
 * - Creates every directory in path until it finds one that already exists.
 *
 * @param string $strPath
 * @param bool $mode true attempts to make a writable tree
 * @return bool true if successful, false otherwise
 * @package Packages
 */
function mktree($strPath, $mode = true)
{
	global $package_ftp;

	$fileFunc = FileFunctions::instance();

	// If already a directory
	if ($fileFunc->isDir($strPath))
	{
		// Not writable, try to make it so with FTP or not
		if (!$fileFunc->isWritable($strPath) && $mode !== false)
		{
			if (isset($package_ftp))
			{
				$package_ftp->ftp_chmod(strtr($strPath, array($_SESSION['ftp_connection']['root'] => '')), [0755, 0775, 0777]);
			}
			else
			{
				$fileFunc->chmod($strPath);
			}
		}

		// See if we can open it for access, return the result
		return test_access($strPath);
	}

	// Is this an invalid path and/or we can't make the directory?
	if ($strPath === dirname($strPath) || !mktree(dirname($strPath), $mode))
	{
		return false;
	}

	// Is the dir writable and do we have permission to attempt to make it so
	if (!$fileFunc->isWritable(dirname($strPath)) && $mode !== false)
	{
		if (isset($package_ftp))
		{
			$package_ftp->ftp_chmod(dirname(strtr($strPath, array($_SESSION['ftp_connection']['root'] => ''))), [0755, 0775, 0777]);
		}
		else
		{
			$fileFunc->chmod(dirname($strPath));
		}
	}

	// Can't change the mode so just return the current availability
	if ($mode === false)
	{
		return test_access($strPath);
	}
	// Let FTP take care of this directory creation
	if (isset($package_ftp, $_SESSION['ftp_connection']))
	{
		return $package_ftp->create_dir(strtr($strPath, array($_SESSION['ftp_connection']['root'] => '')));
	}
	// Only one choice left and that is to try and make a directory with PHP
	else
	{
		try
		{
			return $fileFunc->createDirectory($strPath, false);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}
}

/**
 * Determines if a directory is writable
 *
 * @param string $strPath
 * @return bool
 */
function dirTest($strPath)
{
	$fileFunc = FileFunctions::instance();

	// If it is already a directory
	if ($fileFunc->isDir($strPath))
	{
		// See if we can open it for access, return the result
		return test_access($strPath);
	}

	// Is this an invalid path ?
	if ($strPath === dirname($strPath) || !dirTest(dirname($strPath)))
	{
		return false;
	}

	// Return the current availability
	return test_access(dirname($strPath));
}

/**
 * Copies one directory structure over to another.
 *
 * - Requires the destination to be writable.
 *
 * @param string $source
 * @param string $destination
 * @package Packages
 */
function copytree($source, $destination)
{
	global $package_ftp;

	$fileFunc = FileFunctions::instance();

	// The destination must exist and be writable
	if (!$fileFunc->isDIr($destination) || !$fileFunc->isWritable($destination))
	{
		try
		{
			$fileFunc->createDirectory($destination, false);
		}
		catch (\Exception $e)
		{
			return;
		}
	}

	$current_dir = opendir($source);
	if ($current_dir === false)
	{
		return;
	}

	// Copy the files over by whatever means we have enabled
	while (($entryname = readdir($current_dir)))
	{
		if (in_array($entryname, array('.', '..')))
		{
			continue;
		}

		if (isset($package_ftp))
		{
			$ftp_file = strtr($destination . '/' . $entryname, array($_SESSION['ftp_connection']['root'] => ''));
		}

		if (!$fileFunc->isDir($source . '/' . $entryname))
		{
			if (isset($package_ftp) && !$fileFunc->fileExists($destination . '/' . $entryname))
			{
				$package_ftp->create_file($ftp_file);
			}
			elseif (!$fileFunc->fileExists($destination . '/' . $entryname))
			{
				@touch($destination . '/' . $entryname);
			}
		}

		$packageChmod = new PackageChmod();
		$packageChmod->pkgChmod($destination . '/' . $entryname);

		if ($fileFunc->isDir($source . '/' . $entryname))
		{
			copytree($source . '/' . $entryname, $destination . '/' . $entryname);
		}
		elseif ($fileFunc->fileExists($destination . '/' . $entryname))
		{
			package_put_contents($destination . '/' . $entryname, package_get_contents($source . '/' . $entryname));
		}
		else
		{
			copy($source . '/' . $entryname, $destination . '/' . $entryname);
		}
	}

	closedir($current_dir);
}

/**
 * Parses a xml-style modification file (file).
 *
 * @param string $file
 * @param bool $testing = true means modifications shouldn't actually be saved.
 * @param bool $undo = false specifies that the modifications the file requests should be undone; this doesn't work with everything (regular expressions.)
 * @param array $theme_paths = array()
 * @return array an array of those changes made.
 * @package Packages
 */
function parseModification($file, $testing = true, $undo = false, $theme_paths = array())
{
	global $txt, $modSettings;

	detectServer()->setTimeLimit(600);

	$xml = new XmlArray(strtr($file, array("\r" => '')));
	$actions = array();
	$everything_found = true;

	if (!$xml->exists('modification') || !$xml->exists('modification/file'))
	{
		$actions[] = array(
			'type' => 'error',
			'filename' => '-',
			'debug' => $txt['package_modification_malformed']
		);

		return $actions;
	}

	// Get the XML data.
	$files = $xml->set('modification/file');

	// Use this for holding all the template changes in this mod.
	$template_changes = array();

	// This is needed to hold the long paths, as they can vary...
	$long_changes = array();

	// First, we need to build the list of all the files likely to get changed.
	foreach ($files as $file)
	{
		// What is the filename we're currently on?
		$filename = parse_path(trim($file->fetch('@name')));

		// Now, we need to work out whether this is even a template file...
		foreach ($theme_paths as $id => $theme)
		{
			// If this filename is relative, if so take a guess at what it should be.
			$real_filename = $filename;
			if (strpos($filename, 'themes') === 0)
			{
				$real_filename = BOARDDIR . '/' . $filename;
			}

			if (strpos($real_filename, $theme['theme_dir']) === 0)
			{
				$template_changes[$id][] = substr($real_filename, strlen($theme['theme_dir']) + 1);
				$long_changes[$id][] = $filename;
			}
		}
	}

	// Custom themes to add.
	$custom_themes_add = array();

	// If we have some template changes, we need to build a master link of what new ones are required for the custom themes.
	if (!empty($template_changes[1]))
	{
		foreach ($theme_paths as $id => $theme)
		{
			// Default is getting done anyway, so no need for involvement here.
			if ($id == 1)
			{
				continue;
			}

			// For every template, do we want it? Yea, no, maybe?
			foreach ($template_changes[1] as $index => $template_file)
			{
				// What, it exists and we haven't already got it?! Lordy, get it in!
				if (file_exists($theme['theme_dir'] . '/' . $template_file) && (!isset($template_changes[$id]) || !in_array($template_file, $template_changes[$id])))
				{
					// Now let's add it to the "todo" list.
					$custom_themes_add[$long_changes[1][$index]][$id] = $theme['theme_dir'] . '/' . $template_file;
				}
			}
		}
	}

	foreach ($files as $file)
	{
		// This is the actual file referred to in the XML document...
		$files_to_change = array(
			1 => parse_path(trim($file->fetch('@name'))),
		);

		// Sometimes though, we have some additional files for other themes, if we have add them to the mix.
		if (isset($custom_themes_add[$files_to_change[1]]))
		{
			$files_to_change += $custom_themes_add[$files_to_change[1]];
		}

		// Now, loop through all the files we're changing, and, well, change them ;)
		foreach ($files_to_change as $theme => $working_file)
		{
			if ($working_file[0] !== '/' && $working_file[1] !== ':')
			{
				trigger_error('parseModification(): The filename \'' . $working_file . '\' is not a full path!', E_USER_WARNING);

				$working_file = BOARDDIR . '/' . $working_file;
			}

			// Doesn't exist - give an error or what?
			if (!file_exists($working_file) && (!$file->exists('@error') || !in_array(trim($file->fetch('@error')), array('ignore', 'skip'))))
			{
				$actions[] = array(
					'type' => 'missing',
					'filename' => $working_file,
					'debug' => $txt['package_modification_missing']
				);

				$everything_found = false;
				continue;
			}
			// Skip the file if it doesn't exist.
			elseif (!file_exists($working_file) && $file->exists('@error') && trim($file->fetch('@error')) === 'skip')
			{
				$actions[] = array(
					'type' => 'skipping',
					'filename' => $working_file,
				);
				continue;
			}
			// Okay, we're creating this file then...?
			elseif (!file_exists($working_file))
			{
				$working_data = '';
			}
			// Phew, it exists!  Load 'er up!
			else
			{
				$working_data = str_replace("\r", '', package_get_contents($working_file));
			}

			$actions[] = array(
				'type' => 'opened',
				'filename' => $working_file
			);

			$operations = $file->exists('operation') ? $file->set('operation') : array();
			foreach ($operations as $operation)
			{
				// Convert operation to an array.
				$actual_operation = array(
					'searches' => array(),
					'error' => $operation->exists('@error') && in_array(trim($operation->fetch('@error')), array('ignore', 'fatal', 'required')) ? trim($operation->fetch('@error')) : 'fatal',
				);

				// The 'add' parameter is used for all searches in this operation.
				$add = $operation->exists('add') ? $operation->fetch('add') : '';

				// Grab all search items of this operation (in most cases just 1).
				$searches = $operation->set('search');
				foreach ($searches as $i => $search)
				{
					$actual_operation['searches'][] = array(
						'position' => $search->exists('@position') && in_array(trim($search->fetch('@position')), array('before', 'after', 'replace', 'end')) ? trim($search->fetch('@position')) : 'replace',
						'is_reg_exp' => $search->exists('@regexp') && trim($search->fetch('@regexp')) === 'true',
						'loose_whitespace' => $search->exists('@whitespace') && trim($search->fetch('@whitespace')) === 'loose',
						'search' => $search->fetch('.'),
						'add' => $add,
						'preg_search' => '',
						'preg_replace' => '',
					);
				}

				// At least one search should be defined.
				if (empty($actual_operation['searches']))
				{
					$actions[] = array(
						'type' => 'failure',
						'filename' => $working_file,
						'search' => $search['search'],
						'is_custom' => $theme > 1 ? $theme : 0,
					);

					// Skip to the next operation.
					continue;
				}

				// Reverse the operations in case of undoing stuff.
				if ($undo)
				{
					foreach ($actual_operation['searches'] as $i => $search)
					{
						// Reverse modification of regular expressions are not allowed.
						if ($search['is_reg_exp'])
						{
							if ($actual_operation['error'] === 'fatal')
							{
								$actions[] = array(
									'type' => 'failure',
									'filename' => $working_file,
									'search' => $search['search'],
									'is_custom' => $theme > 1 ? $theme : 0,
								);
							}

							// Continue to the next operation.
							continue 2;
						}

						// The replacement is now the search subject...
						if ($search['position'] === 'replace' || $search['position'] === 'end')
						{
							$actual_operation['searches'][$i]['search'] = $search['add'];
						}
						else
						{
							// Reversing a before/after modification becomes a replacement.
							$actual_operation['searches'][$i]['position'] = 'replace';

							if ($search['position'] === 'before')
							{
								$actual_operation['searches'][$i]['search'] .= $search['add'];
							}
							elseif ($search['position'] === 'after')
							{
								$actual_operation['searches'][$i]['search'] = $search['add'] . $search['search'];
							}
						}

						// ...and the search subject is now the replacement.
						$actual_operation['searches'][$i]['add'] = $search['search'];
					}
				}

				// Sort the search list so the replaces come before the add before/after's.
				if (count($actual_operation['searches']) !== 1)
				{
					$replacements = array();

					foreach ($actual_operation['searches'] as $i => $search)
					{
						if ($search['position'] === 'replace')
						{
							$replacements[] = $search;
							unset($actual_operation['searches'][$i]);
						}
					}
					$actual_operation['searches'] = array_merge($replacements, $actual_operation['searches']);
				}

				// Create regular expression replacements from each search.
				foreach ($actual_operation['searches'] as $i => $search)
				{
					// Not much needed if the search subject is already a regexp.
					if ($search['is_reg_exp'])
					{
						$actual_operation['searches'][$i]['preg_search'] = $search['search'];
					}
					else
					{
						// Make the search subject fit into a regular expression.
						$actual_operation['searches'][$i]['preg_search'] = preg_quote($search['search'], '~');

						// Using 'loose', a random amount of tabs and spaces may be used.
						if ($search['loose_whitespace'])
						{
							$actual_operation['searches'][$i]['preg_search'] = preg_replace('~[ \t]+~', '[ \t]+', $actual_operation['searches'][$i]['preg_search']);
						}
					}

					// Shuzzup.  This is done so we can safely use a regular expression. ($0 is bad!!)
					$actual_operation['searches'][$i]['preg_replace'] = strtr($search['add'], array('$' => '[$PACKAGE1$]', '\\' => '[$PACKAGE2$]'));

					// Before, so the replacement comes after the search subject :P
					if ($search['position'] === 'before')
					{
						$actual_operation['searches'][$i]['preg_search'] = '(' . $actual_operation['searches'][$i]['preg_search'] . ')';
						$actual_operation['searches'][$i]['preg_replace'] = '$1' . $actual_operation['searches'][$i]['preg_replace'];
					}

					// After, after what?
					elseif ($search['position'] === 'after')
					{
						$actual_operation['searches'][$i]['preg_search'] = '(' . $actual_operation['searches'][$i]['preg_search'] . ')';
						$actual_operation['searches'][$i]['preg_replace'] .= '$1';
					}

					// Position the replacement at the end of the file (or just before the closing PHP tags).
					elseif ($search['position'] === 'end')
					{
						if ($undo)
						{
							$actual_operation['searches'][$i]['preg_replace'] = '';
						}
						else
						{
							$actual_operation['searches'][$i]['preg_search'] = '(\\n\\?\\>)?$';
							$actual_operation['searches'][$i]['preg_replace'] .= '$1';
						}
					}

					// Testing 1, 2, 3...
					$failed = preg_match('~' . $actual_operation['searches'][$i]['preg_search'] . '~s', $working_data) === 0;

					// Nope, search pattern not found.
					if ($failed && $actual_operation['error'] === 'fatal')
					{
						$actions[] = array(
							'type' => 'failure',
							'filename' => $working_file,
							'search' => $actual_operation['searches'][$i]['preg_search'],
							'search_original' => $actual_operation['searches'][$i]['search'],
							'replace_original' => $actual_operation['searches'][$i]['add'],
							'position' => $search['position'],
							'is_custom' => $theme > 1 ? $theme : 0,
							'failed' => $failed,
						);

						$everything_found = false;
						continue;
					}

					// Found, but in this case, that means failure!
					elseif (!$failed && $actual_operation['error'] === 'required')
					{
						$actions[] = array(
							'type' => 'failure',
							'filename' => $working_file,
							'search' => $actual_operation['searches'][$i]['preg_search'],
							'search_original' => $actual_operation['searches'][$i]['search'],
							'replace_original' => $actual_operation['searches'][$i]['add'],
							'position' => $search['position'],
							'is_custom' => $theme > 1 ? $theme : 0,
							'failed' => $failed,
						);

						$everything_found = false;
						continue;
					}

					// Replace it into nothing? That's not an option...unless it's an undoing end.
					if ($search['add'] === '' && ($search['position'] !== 'end' || !$undo))
					{
						continue;
					}

					// Finally, we're doing some replacements.
					$working_data = preg_replace('~' . $actual_operation['searches'][$i]['preg_search'] . '~s', $actual_operation['searches'][$i]['preg_replace'], $working_data, 1);

					$actions[] = array(
						'type' => 'replace',
						'filename' => $working_file,
						'search' => $actual_operation['searches'][$i]['preg_search'],
						'replace' => $actual_operation['searches'][$i]['preg_replace'],
						'search_original' => $actual_operation['searches'][$i]['search'],
						'replace_original' => $actual_operation['searches'][$i]['add'],
						'position' => $search['position'],
						'failed' => $failed,
						'ignore_failure' => $failed && $actual_operation['error'] === 'ignore',
						'is_custom' => $theme > 1 ? $theme : 0,
					);
				}
			}

			// Fix any little helper symbols ;).
			$working_data = strtr($working_data, array('[$PACKAGE1$]' => '$', '[$PACKAGE2$]' => '\\'));

			$packageChmod = new PackageChmod();
			$packageChmod->pkgChmod($working_file);

			if ((file_exists($working_file) && !is_writable($working_file)) || (!file_exists($working_file) && !is_writable(dirname($working_file))))
			{
				$actions[] = array(
					'type' => 'chmod',
					'filename' => $working_file
				);
			}

			if (basename($working_file) === 'Settings_bak.php')
			{
				continue;
			}

			if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
			{
				// No, no, not Settings.php!
				if (basename($working_file) === 'Settings.php')
				{
					@copy($working_file, dirname($working_file) . '/Settings_bak.php');
				}
				else
				{
					@copy($working_file, $working_file . '~');
				}
			}

			// Always call this, even if in testing, because it won't really be written in testing mode.
			package_put_contents($working_file, $working_data, $testing);

			$actions[] = array(
				'type' => 'saved',
				'filename' => $working_file,
				'is_custom' => $theme > 1 ? $theme : 0,
			);
		}
	}

	$actions[] = array(
		'type' => 'result',
		'status' => $everything_found
	);

	return $actions;
}

/**
 * Get the physical contents of a packages file
 *
 * @param string $filename
 * @return string
 * @package Packages
 */
function package_get_contents($filename)
{
	global $package_cache, $modSettings;

	if (!isset($package_cache))
	{
		$mem_check = detectServer()->setMemoryLimit('128M');

		// Windows doesn't seem to care about the memory_limit.
		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
		{
			$package_cache = array();
		}
		else
		{
			$package_cache = false;
		}
	}

	if (strpos($filename, 'packages/') !== false || $package_cache === false || !isset($package_cache[$filename]))
	{
		return file_get_contents($filename);
	}
	else
	{
		return $package_cache[$filename];
	}
}

/**
 * Writes data to a file, almost exactly like the file_put_contents() function.
 *
 * - uses FTP to create/chmod the file when necessary and available.
 * - uses text mode for text mode file extensions.
 * - returns the number of bytes written.
 *
 * @param string $filename
 * @param string $data
 * @param bool $testing
 * @return int|bool
 * @package Packages
 */
function package_put_contents($filename, $data, $testing = false)
{
	global $package_ftp, $package_cache, $modSettings;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (!isset($package_cache))
	{
		// Try to increase the memory limit - we don't want to run out of ram!
		$mem_check = detectServer()->setMemoryLimit('128M');

		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
		{
			$package_cache = array();
		}
		else
		{
			$package_cache = false;
		}
	}

	if (isset($package_ftp, $_SESSION['ftp_connection']))
	{
		$ftp_file = strtr($filename, array($_SESSION['ftp_connection']['root'] => ''));
	}

	if (!file_exists($filename) && isset($package_ftp))
	{
		$package_ftp->create_file($ftp_file);
	}
	elseif (!file_exists($filename))
	{
		@touch($filename);
	}

	$packageChmod = new PackageChmod();
	$packageChmod->pkgChmod($filename);

	if (!$testing && (strpos($filename, 'packages/') !== false || $package_cache === false))
	{
		$fp = @fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');

		// We should show an error message or attempt a rollback, no?
		if (!$fp)
		{
			return false;
		}

		fwrite($fp, $data);
		fclose($fp);
	}
	elseif (strpos($filename, 'packages/') !== false || $package_cache === false)
	{
		return strlen($data);
	}
	else
	{
		$package_cache[$filename] = $data;

		// Permission denied, eh?
		$fp = @fopen($filename, 'r+');
		if (!$fp)
		{
			return false;
		}
		fclose($fp);
	}

	return strlen($data);
}

/**
 * Clears (removes the files) the current package cache (temp directory)
 *
 * @param bool $trash
 * @package Packages
 */
function package_flush_cache($trash = false)
{
	global $package_ftp, $package_cache;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (empty($package_cache))
	{
		return;
	}

	$fileFunc = FileFunctions::instance();

	// First, let's check permissions!
	foreach ($package_cache as $filename => $data)
	{
		if (isset($package_ftp))
		{
			$ftp_file = strtr($filename, array($_SESSION['ftp_connection']['root'] => ''));
		}

		if (!$fileFunc->fileExists($filename) && isset($package_ftp))
		{
			$package_ftp->create_file($ftp_file);
		}
		elseif (!$fileFunc->fileExists($filename))
		{
			@touch($filename);
		}

		$packageChmod = new PackageChmod();
		$result = $packageChmod->pkgChmod($filename);

		// If we are not doing our test pass, then lets do a full write check
		if (!$trash && !$fileFunc->isDir($filename))
		{
			// Acid test, can we really open this file for writing?
			$fp = ($result) ? fopen($filename, 'r+') : $result;
			if (!$fp)
			{
				// We should have package_chmod()'d them before, no?!
				trigger_error('package_flush_cache(): some files are still not writable', E_USER_WARNING);

				return;
			}
			fclose($fp);
		}
	}

	if ($trash)
	{
		$package_cache = array();

		return;
	}

	foreach ($package_cache as $filename => $data)
	{
		if (!$fileFunc->isDir($filename))
		{
			$fp = fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');
			fwrite($fp, $data);
			fclose($fp);
		}
	}

	$package_cache = array();
}

/**
 * Try to make a file writable.
 *
 * @param string $filename
 * @param bool $track_change = false
 * @return bool True if it worked, false if it didn't
 * @package Packages
 * @deprecated since 2.0 use PackageChmod class
 */
function package_chmod($filename, $track_change = false)
{
	$packageChmod = new PackageChmod();
	return $packageChmod->pkgChmod($filename, $track_change);
}

/**
 * Creates a site backup before installing a package just in case things don't go
 * as planned.
 *
 * @param string $id
 *
 * @return bool
 * @package Packages
 *
 */
function package_create_backup($id = 'backup')
{
	$db = database();
	$files = new ArrayIterator();
	$use_relative_paths = empty($_REQUEST['use_full_paths']);
	$fileFunc = FileFunctions::instance();

	// The files that reside outside of sources, in the base, we add manually
	$base_files = array('index.php', 'SSI.php', 'subscriptions.php',
						'email_imap_cron.php', 'emailpost.php', 'emailtopic.php');
	foreach ($base_files as $file)
	{
		if ($fileFunc->fileExists(BOARDDIR . '/' . $file))
		{
			$files[$use_relative_paths ? $file : realpath(BOARDDIR . '/' . $file)] = BOARDDIR . '/' . $file;
		}
	}

	// Root directory where most of our files reside
	$dirs = array(
		SOURCEDIR => $use_relative_paths ? 'sources/' : strtr(SOURCEDIR . '/', '\\', '/')
	);

	// Find all installed theme directories
	$db->fetchQuery('
		SELECT 
			value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:theme_dir}',
		array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
		)
	)->fetch_callback(
		function ($row) use (&$dirs, $use_relative_paths) {
			$dirs[$row['value']] = $use_relative_paths ? 'themes/' . basename($row['value']) . '/' : strtr($row['value'] . '/', '\\', '/');
		}
	);

	try
	{
		foreach ($dirs as $dir => $dest)
		{
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
			);

			foreach ($iter as $entry => $dir)
			{
				if ($dir->isDir())
				{
					continue;
				}

				if (preg_match('~^(\.{1,2}|CVS|backup.*|help|images|.*\~)$~', $entry) != 0)
				{
					continue;
				}

				$files[$use_relative_paths ? str_replace(realpath(BOARDDIR), '', $entry) : $entry] = $entry;
			}
		}

		// Make sure we have a backup directory and its writable
		if (!$fileFunc->fileExists(BOARDDIR . '/packages/backups'))
		{
			$fileFunc->createDirectory(BOARDDIR . '/packages/backups');
		}

		if (!$fileFunc->isWritable(BOARDDIR . '/packages/backups'))
		{
			$packageChmod = new PackageChmod();
			$packageChmod->pkgChmod(BOARDDIR . '/packages/backups');
		}

		// Name the output file, yyyy-mm-dd_before_package_name.tar.gz
		$output_file = BOARDDIR . '/packages/backups/' . Util::strftime('%Y-%m-%d_') . preg_replace('~[$\\\\/:<>|?*"\']~', '', $id);
		$output_ext = '.tar';

		if ($fileFunc->fileExists($output_file . $output_ext . '.gz'))
		{
			$i = 2;
			while ($fileFunc->fileExists($output_file . '_' . $i . $output_ext . '.gz'))
			{
				$i++;
			}
			$output_file = $output_file . '_' . $i . $output_ext;
		}
		else
		{
			$output_file .= $output_ext;
		}

		// Buy some more time, so we have enough to create this archive
		detectServer()->setTimeLimit(300);

		$phar = new PharData($output_file);
		$phar->buildFromIterator($files);
		$phar->compress(Phar::GZ);

		/*
		 * Destroying the local var tells PharData to close its internal
		 * file pointer, enabling us to delete the uncompressed tarball.
		 */
		unset($phar);
		unlink($output_file);
	}
	catch (Exception $e)
	{
		\ElkArte\Errors\Errors::instance()->log_error($e->getMessage(), 'backup');

		return false;
	}

	return true;
}

/**
 * Get the contents of a URL, irrespective of allow_url_fopen.
 *
 * - reads the contents of http or ftp addresses and returns the page in a string
 * - will accept up to (3) redirections (redirection_level in the function call is private)
 * - if post_data is supplied, the value and length is posted to the given url as form data
 * - URL must be supplied in lowercase
 *
 * @param string $url
 * @param string $post_data = ''
 * @param bool $keep_alive = false
 * @param int $redirection_level = 3
 * @return false|string
 * @package Packages
 */
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 3)
{
	global $webmaster_email;

	preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', $url, $match);
	$data = '';

	// An FTP url. We should try connecting and RETRieving it...
	if (empty($match[1]))
	{
		return false;
	}

	if ($match[1] === 'ftp')
	{
		// Establish a connection and attempt to enable passive mode.
		$ftp = new FtpConnection(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? 21 : $match[5], 'anonymous', $webmaster_email);
		if ($ftp->error !== false || !$ftp->passive())
		{
			return false;
		}

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $match[6] . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
		{
			return false;
		}

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		while (!feof($fp))
		{
			$data .= fread($fp, 4096);
		}
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}
	// More likely a standard HTTP URL, first try to use cURL if available
	elseif ($match[1] === 'http')
	{
		// Choose the fastest and most robust way
		if (function_exists('curl_init'))
		{
			$fetch_data = new CurlFetchWebdata(array(), $redirection_level);
		}
		elseif (empty(ini_get('allow_url_fopen')))
		{
			$fetch_data = new StreamFetchWebdata(array(), $redirection_level, $keep_alive);
		}
		else
		{
			$fetch_data = new FsockFetchWebdata(array(), $redirection_level, $keep_alive);
		}

		// no errors and a 200 result, then we have a good dataset, well we at least have data ;)
		$fetch_data->get_url_data($url, $post_data);
		if ($fetch_data->result('code') == 200 && !$fetch_data->result('error'))
		{
			return $fetch_data->result('body');
		}

		return false;
	}

	return $data;
}

/**
 * Checks if a package is installed or not
 *
 * - If installed returns an array of themes, db changes and versions associated with
 * the package id
 *
 * @param string $id of package to check
 * @param string|null $install_id to check
 *
 * @return array
 * @package Packages
 */
function isPackageInstalled($id, $install_id = null)
{
	$db = database();

	$result = array(
		'package_id' => null,
		'install_state' => null,
		'old_themes' => null,
		'old_version' => null,
		'db_changes' => array()
	);

	if (empty($id))
	{
		return $result;
	}

	// See if it is installed?
	$db->fetchQuery('
		SELECT 
			version, themes_installed, db_changes, package_id, install_state
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
			' . (!empty($install_id) ? ' AND id_install = {int:install_id} ' : '') . '
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => $id,
			'install_id' => $install_id,
		)
	)->fetch_callback(
		function ($row) use (&$result) {
			$result = array(
				'old_themes' => explode(',', $row['themes_installed']),
				'old_version' => $row['version'],
				'db_changes' => empty($row['db_changes']) ? array() : Util::unserialize($row['db_changes']),
				'package_id' => $row['package_id'],
				'install_state' => $row['install_state'],
			);
		}
	);

	return $result;
}

/**
 * For uninstalling action, updates the log_packages install_state state to 0 (uninstalled)
 *
 * @param string $id package_id to update
 * @param string $install_id install id of the package
 * @package Packages
 */
function setPackageState($id, $install_id)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_packages
		SET 
			install_state = {int:not_installed}, member_removed = {string:member_name}, 
			id_member_removed = {int:current_member}, time_removed = {int:current_time}
		WHERE package_id = {string:package_id}
			AND id_install = {int:install_id}',
		array(
			'current_member' => User::$info->id,
			'not_installed' => 0,
			'current_time' => time(),
			'package_id' => $id,
			'member_name' => User::$info->name,
			'install_id' => $install_id,
		)
	);
}

/**
 * Checks if a package is installed, and if so returns its version level
 *
 * @param string $id
 *
 * @return string
 * @package Packages
 *
 */
function checkPackageDependency($id)
{
	$db = database();

	$version = '';
	$db->fetchQuery('
		SELECT 
			version
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => $id,
		)
	)->fetch_callback(
		function ($row) use (&$version) {
			$version = $row['version'];
		}
	);

	return $version;
}

/**
 * Adds a record to the log packages table
 *
 * @param array $packageInfo
 * @param string $failed_step_insert
 * @param string $themes_installed
 * @param string $db_changes
 * @param bool $is_upgrade
 * @param string $credits_tag
 * @package Packages
 */
function addPackageLog($packageInfo, $failed_step_insert, $themes_installed, $db_changes, $is_upgrade, $credits_tag)
{
	$db = database();

	$db->insert('', '{db_prefix}log_packages',
		array(
			'filename' => 'string', 'name' => 'string', 'package_id' => 'string', 'version' => 'string',
			'id_member_installed' => 'int', 'member_installed' => 'string', 'time_installed' => 'int',
			'install_state' => 'int', 'failed_steps' => 'string', 'themes_installed' => 'string',
			'member_removed' => 'int', 'db_changes' => 'string', 'credits' => 'string',
		),
		array(
			$packageInfo['filename'], $packageInfo['name'], $packageInfo['id'], $packageInfo['version'],
			User::$info->id, User::$info->name, time(),
			$is_upgrade ? 2 : 1, $failed_step_insert, $themes_installed,
			0, $db_changes, $credits_tag,
		),
		array('id_install')
	);
}

/**
 * Called from action_flush, used to flag all packages as uninstalled.
 *
 * @package Packages
 */
function setPackagesAsUninstalled()
{
	$db = database();

	// Set everything as uninstalled, just like that
	$db->query('', '
		UPDATE {db_prefix}log_packages
		SET install_state = {int:not_installed}',
		array(
			'not_installed' => 0,
		)
	);
}

/**
 * Validates that the remote url is one of our known package servers
 *
 * @param string $remote_url
 *
 * @return bool
 * @package Packages
 *
 */
function isAuthorizedServer($remote_url)
{
	global $modSettings;

	// Know addon servers
	$servers = Util::unserialize($modSettings['authorized_package_servers']);
	if (empty($servers))
	{
		return false;
	}

	foreach ($servers as $server)
	{
		if (preg_match('~^' . preg_quote($server) . '~', $remote_url) == 0)
		{
			return true;
		}
	}

	return false;
}

/**
 * The ultimate writable test.
 *
 * @param $item
 * @return bool
 */
function test_access($item)
{
	$fileFunc = FileFunctions::instance();

	$fp = $fileFunc->isDir($item) ? @opendir($item) : @fopen($item, 'rb');
	if ($fileFunc->isWritable($item) && $fp)
	{
		if (!$fileFunc->isDir($item))
		{
			fclose($fp);
		}
		else
		{
			closedir($fp);
		}

		return true;
	}

	return false;
}
