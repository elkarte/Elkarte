<?php

/**
 * This contains functions for handling tar.gz and .zip files
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.7
 *
 */

/**
 * Reads a .tar.gz file, filename, in and extracts file(s) from it.
 * essentially just a shortcut for read_tgz_data().
 *
 * @package Packages
 * @param string $gzfilename
 * @param string $destination
 * @param bool $single_file = false
 * @param bool $overwrite = false
 * @param string[]|null $files_to_extract = null
 * @return array|false
 */
function read_tgz_file($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	// From a web site
	if (substr($gzfilename, 0, 7) === 'http://' || substr($gzfilename, 0, 8) === 'https://')
	{
		$data = fetch_web_data($gzfilename);

		if ($data === false)
			return false;
	}
	// Or a file on the system
	else
	{
		$data = @file_get_contents($gzfilename);

		if ($data === false)
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
 * @package Packages
 * @param string $data
 * @param string $destination
 * @param bool $single_file = false,
 * @param bool $overwrite = false,
 * @param string[]|null $files_to_extract = null
 * @return array|false
 */
function read_tgz_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	require_once(SUBSDIR . '/UnTgz.class.php');
	$untgz = new UnTgz($data, $destination, $single_file, $overwrite, $files_to_extract);

	// Choose the right method for the file
	if ($untgz->check_valid_tgz())
		return $untgz->read_tgz_data();
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
 * @package Packages
 * @param string $data
 * @param string $destination
 * @param bool $single_file
 * @param bool $overwrite
 * @param string[]|null $files_to_extract
 * @return array|false
 */
function read_zip_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	require_once(SUBSDIR . '/UnZip.class.php');
	$unzip = new UnZip($data, $destination, $single_file, $overwrite, $files_to_extract);

	return $unzip->read_zip_data();
}

/**
 * Checks the existence of a remote file since file_exists() does not do remote.
 * will return false if the file is "moved permanently" or similar.
 *
 * @package Packages
 * @param string $url
 * @return boolean true if the remote url exists.
 */
function url_exists($url)
{
	$a_url = parse_url($url);

	if (!isset($a_url['scheme']))
		return false;

	// Attempt to connect...
	$temp = '';
	$fid = fsockopen($a_url['host'], !isset($a_url['port']) ? 80 : $a_url['port'], $temp, $temp, 8);

	// Can't make a connection
	if (!$fid)
		return false;

	// See if the file is where its supposed to be
	fputs($fid, 'HEAD ' . $a_url['path'] . ' HTTP/1.0' . "\r\n" . 'Host: ' . $a_url['host'] . "\r\n\r\n");
	$head = fread($fid, 1024);
	fclose($fid);

	// Check for a return code that shows the file was there
	return preg_match('~^HTTP/.+\s+(20[01]|30[127])~i', $head) == 1;
}

/**
 * Loads and returns an array of installed packages.
 *
 * - Gets this information from packages/installed.list.
 * - Returns the array of data.
 * - Default sort order is package_installed time
 *
 * @package Packages
 * @return array
 */
function loadInstalledPackages()
{
	$db = database();

	// First, check that the database is valid, installed.list is still king.
	$install_file = implode('', file(BOARDDIR . '/packages/installed.list'));
	if (trim($install_file) == '')
	{
		$db->query('', '
			UPDATE {db_prefix}log_packages
			SET install_state = {int:not_installed}',
			array(
				'not_installed' => 0,
			)
		);

		// Don't have anything left, so send an empty array.
		return array();
	}

	// Load the packages from the database - note this is ordered by install time to ensure latest package uninstalled first.
	$request = $db->query('', '
		SELECT id_install, package_id, filename, name, version
		FROM {db_prefix}log_packages
		WHERE install_state != {int:not_installed}
		ORDER BY time_installed DESC',
		array(
			'not_installed' => 0,
		)
	);
	$installed = array();
	$found = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Already found this? If so don't add it twice!
		if (in_array($row['package_id'], $found))
			continue;

		$found[] = $row['package_id'];

		$installed[] = array(
			'id' => $row['id_install'],
			'name' => $row['name'],
			'filename' => $row['filename'],
			'package_id' => $row['package_id'],
			'version' => $row['version'],
		);
	}
	$db->free_result($request);

	return $installed;
}

/**
 * Loads a package's information and returns a representative array.
 *
 * - Expects the file to be a package in packages/.
 * - Returns a error string if the package-info is invalid.
 * - Otherwise returns a basic array of id, version, filename, and similar information.
 * - An Xml_Array is available in 'xml'.
 *
 * @package Packages
 * @param string $gzfilename
 *
 * @return array|string error string on error array on success
 */
function getPackageInfo($gzfilename)
{
	$gzfilename = trim($gzfilename);

	// Extract package-info.xml from downloaded file. (*/ is used because it could be in any directory.)
	if (preg_match('~^https?://~i', $gzfilename) === 1)
		$packageInfo = read_tgz_data(fetch_web_data($gzfilename, '', true), '*/package-info.xml', true);
	else
	{
		// It must be in the package directory then
		if (!file_exists(BOARDDIR . '/packages/' . $gzfilename))
			return 'package_get_error_not_found';

		// Make sure an package.xml file is available
		if (is_file(BOARDDIR . '/packages/' . $gzfilename))
			$packageInfo = read_tgz_file(BOARDDIR . '/packages/' . $gzfilename, '*/package-info.xml', true);
		elseif (file_exists(BOARDDIR . '/packages/' . $gzfilename . '/package-info.xml'))
			$packageInfo = file_get_contents(BOARDDIR . '/packages/' . $gzfilename . '/package-info.xml');
		else
			return 'package_get_error_missing_xml';
	}

	// Nothing?
	if (empty($packageInfo))
	{
		// Perhaps they are trying to install a theme, lets tell them nicely this is the wrong function
		$packageInfo = read_tgz_file(BOARDDIR . '/packages/' . $gzfilename, '*/theme_info.xml', true);
		if (!empty($packageInfo))
			return 'package_get_error_is_theme';
		else
			return 'package_get_error_is_zero';
	}

	// Parse package-info.xml into an Xml_Array.
	$packageInfo = new Xml_Array($packageInfo);

	// @todo Error message of some sort?
	if (!$packageInfo->exists('package-info[0]'))
		return 'package_get_error_packageinfo_corrupt';

	$packageInfo = $packageInfo->path('package-info[0]');

	// Convert packageInfo to an array for use
	$package = htmlspecialchars__recursive($packageInfo->to_array());
	$package['xml'] = $packageInfo;
	$package['filename'] = $gzfilename;

	// Set a default type if none was supplied in the package
	if (!isset($package['type']))
		$package['type'] = 'modification';

	return $package;
}

/**
 * Create a chmod control for chmoding files.
 *
 * @package Packages
 * @param string[] $chmodFiles
 * @param mixed[] $chmodOptions
 * @param boolean $restore_write_status
 * @return array|boolean
 * @throws Elk_Exception
 */
function create_chmod_control($chmodFiles = array(), $chmodOptions = array(), $restore_write_status = false)
{
	global $context, $modSettings, $package_ftp, $txt, $scripturl;

	// If we're restoring the status of existing files prepare the data.
	if ($restore_write_status && isset($_SESSION['pack_ftp']) && !empty($_SESSION['pack_ftp']['original_perms']))
	{
		$listOptions = array(
			'id' => 'restore_file_permissions',
			'title' => $txt['package_restore_permissions'],
			'get_items' => array(
				'function' => 'list_restoreFiles',
				'params' => array(
					!empty($_POST['restore_perms']),
				),
			),
			'columns' => array(
				'path' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_filename'],
					),
					'data' => array(
						'db' => 'path',
						'class' => 'smalltext',
					),
				),
				'old_perms' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_orig_status'],
					),
					'data' => array(
						'db' => 'old_perms',
						'class' => 'smalltext',
					),
				),
				'cur_perms' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_cur_status'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							$formatTxt = $rowData['result'] == '' || $rowData['result'] == 'skipped' ? $txt['package_restore_permissions_pre_change'] : $txt['package_restore_permissions_post_change'];
							return sprintf($formatTxt, $rowData['cur_perms'], $rowData['new_perms'], $rowData['writable_message']);
						},
						'class' => 'smalltext',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="restore_files[]" value="%1$s" class="input_check" />',
							'params' => array(
								'path' => false,
							),
						),
						'class' => 'centertext',
					),
				),
				'result' => array(
					'header' => array(
						'value' => $txt['package_restore_permissions_result'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return $txt['package_restore_permissions_action_' . $rowData['result']];
						},
						'class' => 'smalltext',
					),
				),
			),
			'form' => array(
				'href' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : $scripturl . '?action=admin;area=packages;sa=perms;restore;' . $context['session_var'] . '=' . $context['session_id'],
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="restore_perms" value="' . $txt['package_restore_permissions_restore'] . '" class="right_submit" />',
					'class' => 'category_header',
				),
				array(
					'position' => 'after_title',
					'value' => '<span class="smalltext">' . $txt['package_restore_permissions_desc'] . '</span>',
				),
			),
		);

		// Work out what columns and the like to show.
		if (!empty($_POST['restore_perms']))
		{
			$listOptions['additional_rows'][1]['value'] = sprintf($txt['package_restore_permissions_action_done'], $scripturl . '?action=admin;area=packages;sa=perms;' . $context['session_var'] . '=' . $context['session_id']);
			unset($listOptions['columns']['check'], $listOptions['form'], $listOptions['additional_rows'][0]);

			$context['sub_template'] = 'show_list';
			$context['default_list'] = 'restore_file_permissions';
		}
		else
		{
			unset($listOptions['columns']['result']);
		}

		// Create the list for display.
		createList($listOptions);

		// If we just restored permissions then wherever we are, we are now done and dusted.
		if (!empty($_POST['restore_perms']))
			obExit();
	}
	// Otherwise, it's entirely irrelevant?
	elseif ($restore_write_status)
		return true;

	// This is where we report what we got up to.
	$return_data = array(
		'files' => array(
			'writable' => array(),
			'notwritable' => array(),
		),
	);

	// If we have some FTP information already, then let's assume it was required and try to get ourselves connected.
	if (!empty($_SESSION['pack_ftp']['connected']))
	{
		$package_ftp = new Ftp_Connection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], package_crypt($_SESSION['pack_ftp']['password']));

		// Check for a valid connection
		if ($package_ftp->error !== false)
			unset($package_ftp, $_SESSION['pack_ftp']);
	}

	// Just got a submission did we?
	if ((empty($package_ftp) || ($package_ftp->error !== false)) && isset($_POST['ftp_username']))
	{
		$ftp = new Ftp_Connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

		// We're connected, jolly good!
		if ($ftp->error === false)
		{
			// Common mistake, so let's try to remedy it...
			if (!$ftp->chdir($_POST['ftp_path']))
			{
				$ftp_error = $ftp->last_message;
				$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
			}

			if (!in_array($_POST['ftp_path'], array('', '/')))
			{
				$ftp_root = strtr(BOARDDIR, array($_POST['ftp_path'] => ''));
				if (substr($ftp_root, -1) == '/' && ($_POST['ftp_path'] == '' || substr($_POST['ftp_path'], 0, 1) == '/'))
					$ftp_root = substr($ftp_root, 0, -1);
			}
			else
				$ftp_root = BOARDDIR;

			$_SESSION['pack_ftp'] = array(
				'server' => $_POST['ftp_server'],
				'port' => $_POST['ftp_port'],
				'username' => $_POST['ftp_username'],
				'password' => package_crypt($_POST['ftp_password']),
				'path' => $_POST['ftp_path'],
				'root' => $ftp_root,
				'connected' => true,
			);

			if (!isset($modSettings['package_path']) || $modSettings['package_path'] != $_POST['ftp_path'])
				updateSettings(array('package_path' => $_POST['ftp_path']));

			// This is now the primary connection.
			$package_ftp = $ftp;
		}
	}

	// Now try to simply make the files writable, with whatever we might have.
	if (!empty($chmodFiles))
	{
		foreach ($chmodFiles as $k => $file)
		{
			// Sometimes this can somehow happen maybe?
			if (empty($file))
				unset($chmodFiles[$k]);
			// Already writable?
			elseif (@is_writable($file))
				$return_data['files']['writable'][] = $file;
			else
			{
				// Now try to change that.
				$return_data['files'][package_chmod($file, 'writable', true) ? 'writable' : 'notwritable'][] = $file;
			}
		}
	}

	// Have we still got nasty files which ain't writable? Dear me we need more FTP good sir.
	if (empty($package_ftp) && (!empty($return_data['files']['notwritable']) || !empty($chmodOptions['force_find_error'])))
	{
		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
			{
				$ftp = new Ftp_Connection(null);
			}
			elseif ($ftp->error !== false && !isset($ftp_error))
				$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;

			list ($username, $detect_path, $found_path) = $ftp->detect_path(BOARDDIR);

			if ($found_path)
				$_POST['ftp_path'] = $detect_path;
			elseif (!isset($_POST['ftp_path']))
				$_POST['ftp_path'] = isset($modSettings['package_path']) ? $modSettings['package_path'] : $detect_path;

			if (!isset($_POST['ftp_username']))
				$_POST['ftp_username'] = $username;
		}

		$context['package_ftp'] = array(
			'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'),
			'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'),
			'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''),
			'path' => $_POST['ftp_path'],
			'error' => empty($ftp_error) ? null : $ftp_error,
			'destination' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : '',
		);

		// Which files failed?
		if (!isset($context['notwritable_files']))
			$context['notwritable_files'] = array();
		$context['notwritable_files'] = array_merge($context['notwritable_files'], $return_data['files']['notwritable']);

		// Sent here to die?
		if (!empty($chmodOptions['crash_on_error']))
		{
			$context['page_title'] = $txt['package_ftp_necessary'];
			$context['sub_template'] = 'ftp_required';
			obExit();
		}
	}

	return $return_data;
}

/**
 * Get a listing of files that will need to be set back to the original state
 *
 * @param string $dummy1
 * @param string $dummy2
 * @param string $dummy3
 * @param boolean $do_change
 */
function list_restoreFiles($dummy1, $dummy2, $dummy3, $do_change)
{
	global $txt, $package_ftp;

	$restore_files = array();

	foreach ($_SESSION['pack_ftp']['original_perms'] as $file => $perms)
	{
		// Check the file still exists, and the permissions were indeed different than now.
		$file_permissions = @fileperms($file);
		if (!file_exists($file) || $file_permissions == $perms)
		{
			unset($_SESSION['pack_ftp']['original_perms'][$file]);
			continue;
		}

		// Are we wanting to change the permission?
		if ($do_change && isset($_POST['restore_files']) && in_array($file, $_POST['restore_files']))
		{
			// Use FTP if we have it.
			if (!empty($package_ftp))
			{
				$ftp_file = strtr($file, array($_SESSION['pack_ftp']['root'] => ''));
				$package_ftp->chmod($ftp_file, $perms);
			}
			else
				elk_chmod($file, $perms);

			$new_permissions = @fileperms($file);
			$result = $new_permissions == $perms ? 'success' : 'failure';
			unset($_SESSION['pack_ftp']['original_perms'][$file]);
		}
		elseif ($do_change)
		{
			$new_permissions = '';
			$result = 'skipped';
			unset($_SESSION['pack_ftp']['original_perms'][$file]);
		}

		// Record the results!
		$restore_files[] = array(
			'path' => $file,
			'old_perms_raw' => $perms,
			'old_perms' => substr(sprintf('%o', $perms), -4),
			'cur_perms' => substr(sprintf('%o', $file_permissions), -4),
			'new_perms' => isset($new_permissions) ? substr(sprintf('%o', $new_permissions), -4) : '',
			'result' => isset($result) ? $result : '',
			'writable_message' => '<span class="' . (@is_writable($file) ? 'success' : 'alert') . '">' . (@is_writable($file) ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']) . '</span>',
		);
	}

	return $restore_files;
}

/**
 * Use FTP functions to work with a package download/install
 *
 * @package Packages
 * @param string $destination_url
 * @param string[]|null $files = none
 * @param bool $return = false
 * @throws Elk_Exception
 */
function packageRequireFTP($destination_url, $files = null, $return = false)
{
	global $context, $modSettings, $package_ftp, $txt;

	// Try to make them writable the manual way.
	if ($files !== null)
	{
		foreach ($files as $k => $file)
		{
			// If this file doesn't exist, then we actually want to look at the directory, no?
			if (!file_exists($file))
				$file = dirname($file);

			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!@is_writable($file))
				elk_chmod($file, 0755);
			if (!@is_writable($file))
				elk_chmod($file, 0777);
			if (!@is_writable(dirname($file)))
				elk_chmod($file, 0755);
			if (!@is_writable(dirname($file)))
				elk_chmod($file, 0777);

			$fp = is_dir($file) ? @opendir($file) : @fopen($file, 'rb');
			if (@is_writable($file) && $fp)
			{
				unset($files[$k]);
				if (!is_dir($file))
					fclose($fp);
				else
					closedir($fp);
			}
		}

		// No FTP required!
		if (empty($files))
			return array();
	}

	// They've opted to not use FTP, and try anyway.
	if (isset($_SESSION['pack_ftp']) && $_SESSION['pack_ftp'] === false)
	{
		if ($files === null)
			return array();

		foreach ($files as $k => $file)
		{
			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!file_exists($file))
			{
				mktree(dirname($file), 0755);
				@touch($file);
				elk_chmod($file, 0755);
			}
			if (!@is_writable($file))
				elk_chmod($file, 0777);
			if (!@is_writable(dirname($file)))
				elk_chmod(dirname($file), 0777);

			if (@is_writable($file))
				unset($files[$k]);
		}

		return $files;
	}
	elseif (isset($_SESSION['pack_ftp']))
	{
		$package_ftp = new Ftp_Connection($_SESSION['pack_ftp']['server'], $_SESSION['pack_ftp']['port'], $_SESSION['pack_ftp']['username'], package_crypt($_SESSION['pack_ftp']['password']));

		if ($files === null)
			return array();

		foreach ($files as $k => $file)
		{
			$ftp_file = strtr($file, array($_SESSION['pack_ftp']['root'] => ''));

			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!file_exists($file))
			{
				mktree(dirname($file), 0755);
				$package_ftp->create_file($ftp_file);
				$package_ftp->chmod($ftp_file, 0755);
			}

			// Still not writable, true full permissions
			if (!@is_writable($file))
				$package_ftp->chmod($ftp_file, 0777);

			// Directory not writable, try to chmod to 777 then
			if (!@is_writable(dirname($file)))
				$package_ftp->chmod(dirname($ftp_file), 0777);

			if (@is_writable($file))
				unset($files[$k]);
		}

		return $files;
	}

	if (isset($_POST['ftp_none']))
	{
		$_SESSION['pack_ftp'] = false;

		$files = packageRequireFTP($destination_url, $files, $return);
		return $files;
	}
	elseif (isset($_POST['ftp_username']))
	{
		// Attempt to make a new FTP connection
		$ftp = new Ftp_Connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

		if ($ftp->error === false)
		{
			// Common mistake, so let's try to remedy it...
			if (!$ftp->chdir($_POST['ftp_path']))
			{
				$ftp_error = $ftp->last_message;
				$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
			}
		}
	}

	if (!isset($ftp) || $ftp->error !== false)
	{
		if (!isset($ftp))
		{
			$ftp = new Ftp_Connection(null);
		}
		elseif ($ftp->error !== false && !isset($ftp_error))
			$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;

		list ($username, $detect_path, $found_path) = $ftp->detect_path(BOARDDIR);

		if ($found_path)
			$_POST['ftp_path'] = $detect_path;
		elseif (!isset($_POST['ftp_path']))
			$_POST['ftp_path'] = isset($modSettings['package_path']) ? $modSettings['package_path'] : $detect_path;

		if (!isset($_POST['ftp_username']))
			$_POST['ftp_username'] = $username;

		$context['package_ftp'] = array(
			'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'),
			'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'),
			'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''),
			'path' => $_POST['ftp_path'],
			'error' => empty($ftp_error) ? null : $ftp_error,
			'destination' => $destination_url,
		);

		// If we're returning dump out here.
		if ($return)
			return $files;

		$context['page_title'] = $txt['package_ftp_necessary'];
		$context['sub_template'] = 'ftp_required';
		obExit();
	}
	else
	{
		if (!in_array($_POST['ftp_path'], array('', '/')))
		{
			$ftp_root = strtr(BOARDDIR, array($_POST['ftp_path'] => ''));
			if (substr($ftp_root, -1) == '/' && ($_POST['ftp_path'] == '' || $_POST['ftp_path'][0] == '/'))
				$ftp_root = substr($ftp_root, 0, -1);
		}
		else
			$ftp_root = BOARDDIR;

		$_SESSION['pack_ftp'] = array(
			'server' => $_POST['ftp_server'],
			'port' => $_POST['ftp_port'],
			'username' => $_POST['ftp_username'],
			'password' => package_crypt($_POST['ftp_password']),
			'path' => $_POST['ftp_path'],
			'root' => $ftp_root,
		);

		if (!isset($modSettings['package_path']) || $modSettings['package_path'] != $_POST['ftp_path'])
			updateSettings(array('package_path' => $_POST['ftp_path']));

		$files = packageRequireFTP($destination_url, $files, $return);
	}

	return $files;
}

/**
 * Parses the actions in package-info.xml file from packages.
 *
 * What it does:
 *
 * - Package should be an Xml_Array with package-info as its base.
 * - Testing_only should be true if the package should not actually be applied.
 * - Method can be upgrade, install, or uninstall.  Its default is install.
 * - Previous_version should be set to the previous installed version of this package, if any.
 * - Does not handle failure terribly well; testing first is always better.
 *
 * @package Packages
 * @param Xml_Array $packageXML
 * @param bool $testing_only = true
 * @param string $method = 'install' ('install', 'upgrade', or 'uninstall')
 * @param string $previous_version = ''
 * @return array an array of those changes made.
 */
function parsePackageInfo(&$packageXML, $testing_only = true, $method = 'install', $previous_version = '')
{
	global $context, $temp_path, $language;

	// Mayday!  That action doesn't exist!!
	if (empty($packageXML) || !$packageXML->exists($method))
		return array();

	// We haven't found the package script yet...
	$script = false;
	$the_version = strtr(FORUM_VERSION, array('ElkArte ' => ''));

	// Emulation support...
	if (!empty($_SESSION['version_emulate']))
		$the_version = $_SESSION['version_emulate'];

	// Single package emulation
	if (!empty($_REQUEST['ve']) && !empty($_REQUEST['package']))
	{
		$the_version = $_REQUEST['ve'];
		$_SESSION['single_version_emulate'][$_REQUEST['package']] = $the_version;
	}
	if (!empty($_REQUEST['package']) && (!empty($_SESSION['single_version_emulate'][$_REQUEST['package']])))
		$the_version = $_SESSION['single_version_emulate'][$_REQUEST['package']];

	// Get all the versions of this method and find the right one.
	$these_methods = $packageXML->set($method);
	foreach ($these_methods as $this_method)
	{
		// They specified certain versions this part is for.
		if ($this_method->exists('@for'))
		{
			// Don't keep going if this won't work for this version.
			if (!matchPackageVersion($the_version, $this_method->fetch('@for')))
				continue;
		}

		// Upgrades may go from a certain old version of the mod.
		if ($method == 'upgrade' && $this_method->exists('@from'))
		{
			// Well, this is for the wrong old version...
			if (!matchPackageVersion($previous_version, $this_method->fetch('@from')))
				continue;
		}

		// We've found it!
		$script = $this_method;
		break;
	}

	// Bad news, a matching script wasn't found!
	if ($script === false)
		return array();

	// Find all the actions in this method - in theory, these should only be allowed actions. (* means all.)
	$actions = $script->set('*');
	$return = array();

	$temp_auto = 0;
	$temp_path = BOARDDIR . '/packages/temp/' . (isset($context['base_path']) ? $context['base_path'] : '');

	$context['readmes'] = array();
	$context['licences'] = array();
	$has_redirect = false;

	// This is the testing phase... nothing shall be done yet.
	foreach ($actions as $action)
	{
		$actionType = $action->name();

		if (in_array($actionType, array('readme', 'code', 'database', 'modification', 'redirect', 'license')))
		{
			if ($actionType == 'redirect')
			{
				$has_redirect = true;
			}

			// Allow for translated readme and license files.
			if ($actionType == 'readme' || $actionType == 'license')
			{
				$type = $actionType . 's';
				if ($action->exists('@lang'))
				{
					// Auto-select the language based on either request variable or current language.
					if ((isset($_REQUEST['readme']) && $action->fetch('@lang') == $_REQUEST['readme']) || (isset($_REQUEST['license']) && $action->fetch('@lang') == $_REQUEST['license']) || (!isset($_REQUEST['readme']) && $action->fetch('@lang') == $language) || (!isset($_REQUEST['license']) && $action->fetch('@lang') == $language))
					{
						// In case the user put the blocks in the wrong order.
						if (isset($context[$type]['selected']) && $context[$type]['selected'] == 'default')
							$context[$type][] = 'default';

						$context[$type]['selected'] = htmlspecialchars($action->fetch('@lang'), ENT_COMPAT, 'UTF-8');
					}
					else
					{
						// We don't want this now, but we'll allow the user to select to read it.
						$context[$type][] = htmlspecialchars($action->fetch('@lang'), ENT_COMPAT, 'UTF-8');
						continue;
					}
				}
				// Fallback when we have no lang parameter.
				else
				{
					// Already selected one for use?
					if (isset($context[$type]['selected']))
					{
						$context[$type][] = 'default';
						continue;
					}
					else
						$context[$type]['selected'] = 'default';
				}
			}

			// @todo Make sure the file actually exists?  Might not work when testing?
			if ($action->exists('@type') && $action->fetch('@type') == 'inline')
			{
				$filename = $temp_path . '$auto_' . $temp_auto++ . (in_array($actionType, array('readme', 'redirect', 'license')) ? '.txt' : ($actionType == 'code' || $actionType == 'database' ? '.php' : '.mod'));
				package_put_contents($filename, $action->fetch('.'));
				$filename = strtr($filename, array($temp_path => ''));
			}
			else
				$filename = $action->fetch('.');

			$return[] = array(
				'type' => $actionType,
				'filename' => $filename,
				'description' => '',
				'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true',
				'redirect_url' => $action->exists('@url') ? $action->fetch('@url') : '',
				'redirect_timeout' => $action->exists('@timeout') ? (int) $action->fetch('@timeout') : 5000,
				'parse_bbc' => $action->exists('@parsebbc') && $action->fetch('@parsebbc') == 'true',
				'language' => (($actionType == 'readme' || $actionType == 'license') && $action->exists('@lang') && $action->fetch('@lang') == $language) ? $language : '',
			);

			continue;
		}
		elseif ($actionType == 'hook')
		{
			$return[] = array(
				'type' => $actionType,
				'function' => $action->exists('@function') ? $action->fetch('@function') : '',
				'hook' => $action->exists('@hook') ? $action->fetch('@hook') : $action->fetch('.'),
				'include_file' => $action->exists('@file') ? $action->fetch('@file') : '',
				'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true' ? true : false,
				'description' => '',
			);
			continue;
		}
		elseif ($actionType == 'credits')
		{
			// quick check of any supplied url
			$url = $action->exists('@url') ? $action->fetch('@url') : '';
			if (strlen(trim($url)) > 0)
			{
				$url = addProtocol($url, array('http://', 'https://'));

				if (strlen($url) < 8)
					$url = '';
			}

			$return[] = array(
				'type' => $actionType,
				'url' => $url,
				'license' => $action->exists('@license') ? $action->fetch('@license') : '',
				'copyright' => $action->exists('@copyright') ? $action->fetch('@copyright') : '',
				'title' => $action->fetch('.'),
			);
			continue;
		}
		elseif ($actionType == 'requires')
		{
			$return[] = array(
				'type' => $actionType,
				'id' => $action->exists('@id') ? $action->fetch('@id') : '',
				'version' => $action->exists('@version') ? $action->fetch('@version') : $action->fetch('.'),
				'description' => '',
			);
			continue;
		}
		elseif ($actionType == 'error')
		{
			$return[] = array(
				'type' => 'error',
			);
		}
		elseif (in_array($actionType, array('require-file', 'remove-file', 'require-dir', 'remove-dir', 'move-file', 'move-dir', 'create-file', 'create-dir')))
		{
			$this_action = &$return[];
			$this_action = array(
				'type' => $actionType,
				'filename' => $action->fetch('@name'),
				'description' => $action->fetch('.')
			);

			// If there is a destination, make sure it makes sense.
			if (substr($actionType, 0, 6) != 'remove')
			{
				$this_action['unparsed_destination'] = $action->fetch('@destination');
				$this_action['destination'] = parse_path($action->fetch('@destination')) . '/' . basename($this_action['filename']);
			}
			else
			{
				$this_action['unparsed_filename'] = $this_action['filename'];
				$this_action['filename'] = parse_path($this_action['filename']);
			}

			// If we're moving or requiring (copying) a file.
			if (substr($actionType, 0, 4) == 'move' || substr($actionType, 0, 7) == 'require')
			{
				if ($action->exists('@from'))
				{
					$this_action['source'] = parse_path($action->fetch('@from'));
				}
				else
				{
					$this_action['source'] = $temp_path . $this_action['filename'];
				}
			}

			// Check if these things can be done. (chmod's etc.)
			if ($actionType == 'create-dir')
			{
				// Try to create a directory
				if (!mktree($this_action['destination'], false))
				{
					$temp = $this_action['destination'];
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}
			}
			elseif ($actionType == 'create-file')
			{
				// Try to create a file in a known location
				if (!mktree(dirname($this_action['destination']), false))
				{
					$temp = dirname($this_action['destination']);
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}

				if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination']))))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['destination']
					);
			}
			elseif ($actionType == 'require-dir')
			{
				if (!mktree($this_action['destination'], false))
				{
					$temp = $this_action['destination'];
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}
			}
			elseif ($actionType == 'require-file')
			{
				if ($action->exists('@theme'))
					$this_action['theme_action'] = $action->fetch('@theme');

				if (!mktree(dirname($this_action['destination']), false))
				{
					$temp = dirname($this_action['destination']);
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}

				if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination']))))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['destination']
					);
			}
			elseif ($actionType == 'move-dir' || $actionType == 'move-file')
			{
				if (!mktree(dirname($this_action['destination']), false))
				{
					$temp = dirname($this_action['destination']);
					while (!file_exists($temp) && strlen($temp) > 1)
						$temp = dirname($temp);

					$return[] = array(
						'type' => 'chmod',
						'filename' => $temp
					);
				}

				if (!is_writable($this_action['destination']) && (file_exists($this_action['destination']) || !is_writable(dirname($this_action['destination']))))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['destination']
					);
			}
			elseif ($actionType == 'remove-dir')
			{
				if (!is_writable($this_action['filename']) && file_exists($this_action['filename']))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['filename']
					);
			}
			elseif ($actionType == 'remove-file')
			{
				if (!is_writable($this_action['filename']) && file_exists($this_action['filename']))
					$return[] = array(
						'type' => 'chmod',
						'filename' => $this_action['filename']
					);
			}
		}
		else
		{
			$return[] = array(
				'type' => 'error',
				'error_msg' => 'unknown_action',
				'error_var' => $actionType
			);
		}
	}

	if (!$has_redirect)
	{
		$return[] = array(
			'type' => 'redirect',
			'filename' => '',
			'description' => '',
			'reverse' => false,
			'redirect_url' => '$scripturl?action=admin;area=packages',
			'redirect_timeout' => 5000,
			'parse_bbc' => false,
			'language' => '',
		);
	}

	// Only testing - just return a list of things to be done.
	if ($testing_only)
		return $return;

	umask(0);

	$failure = false;
	$not_done = array(array('type' => '!'));
	foreach ($return as $action)
	{
		if (in_array($action['type'], array('modification', 'code', 'database', 'redirect', 'hook', 'credits')))
			$not_done[] = $action;

		if ($action['type'] == 'create-dir')
		{
			if (!mktree($action['destination'], 0755) || !is_writable($action['destination']))
				$failure |= !mktree($action['destination'], 0777);
		}
		elseif ($action['type'] == 'create-file')
		{
			if (!mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination'])))
				$failure |= !mktree(dirname($action['destination']), 0777);

			// Create an empty file.
			package_put_contents($action['destination'], package_get_contents($action['source']), $testing_only);

			if (!file_exists($action['destination']))
				$failure = true;
		}
		elseif ($action['type'] == 'require-dir')
		{
			copytree($action['source'], $action['destination']);
			// Any other theme folders?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['destination']]))
				foreach ($context['theme_copies'][$action['type']][$action['destination']] as $theme_destination)
					copytree($action['source'], $theme_destination);
		}
		elseif ($action['type'] == 'require-file')
		{
			if (!mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination'])))
				$failure |= !mktree(dirname($action['destination']), 0777);

			package_put_contents($action['destination'], package_get_contents($action['source']), $testing_only);

			$failure |= !copy($action['source'], $action['destination']);

			// Any other theme files?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['destination']]))
				foreach ($context['theme_copies'][$action['type']][$action['destination']] as $theme_destination)
				{
					if (!mktree(dirname($theme_destination), 0755) || !is_writable(dirname($theme_destination)))
						$failure |= !mktree(dirname($theme_destination), 0777);

					package_put_contents($theme_destination, package_get_contents($action['source']), $testing_only);

					$failure |= !copy($action['source'], $theme_destination);
				}
		}
		elseif ($action['type'] == 'move-file')
		{
			if (!mktree(dirname($action['destination']), 0755) || !is_writable(dirname($action['destination'])))
				$failure |= !mktree(dirname($action['destination']), 0777);

			$failure |= !rename($action['source'], $action['destination']);
		}
		elseif ($action['type'] == 'move-dir')
		{
			if (!mktree($action['destination'], 0755) || !is_writable($action['destination']))
				$failure |= !mktree($action['destination'], 0777);

			$failure |= !rename($action['source'], $action['destination']);
		}
		elseif ($action['type'] == 'remove-dir')
		{
			deltree($action['filename']);

			// Any other theme folders?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['filename']]))
				foreach ($context['theme_copies'][$action['type']][$action['filename']] as $theme_destination)
					deltree($theme_destination);
		}
		elseif ($action['type'] == 'remove-file')
		{
			// Make sure the file exists before deleting it.
			if (file_exists($action['filename']))
			{
				package_chmod($action['filename']);
				$failure |= !unlink($action['filename']);
			}
			// The file that was supposed to be deleted couldn't be found.
			else
				$failure = true;

			// Any other theme folders?
			if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['filename']]))
				foreach ($context['theme_copies'][$action['type']][$action['filename']] as $theme_destination)
					if (file_exists($theme_destination))
						$failure |= !unlink($theme_destination);
					else
						$failure = true;
		}
	}

	return $not_done;
}

/**
 * Checks if version matches any of the versions in versions.
 *
 * - Supports comma separated version numbers, with or without whitespace.
 * - Supports lower and upper bounds. (1.0-1.2)
 * - Returns true if the version matched.
 *
 * @package Packages
 * @param string $versions
 * @param boolean $reset
 * @param string $the_version
 * @return string|boolean highest install value string or false
 */
function matchHighestPackageVersion($versions, $the_version, $reset = false)
{
	static $near_version = 0;

	if ($reset)
		$near_version = 0;

	// Normalize the $versions
	$versions = explode(',', str_replace(' ', '', strtolower($versions)));

	// If it is not ElkArte, let's just give up
	list ($the_brand,) = explode(' ', FORUM_VERSION, 2);
	if ($the_brand != 'ElkArte')
		return false;

	// Loop through each version, save the highest we can find
	foreach ($versions as $for)
	{
		// Adjust for those wild cards
		if (strpos($for, '*') !== false)
			$for = str_replace('*', '0', $for) . '-' . str_replace('*', '999', $for);

		// If we have a range, grab the lower value, done this way so it looks normal-er to the user e.g. 1.0 vs 1.0.99
		if (strpos($for, '-') !== false)
			list ($for,) = explode('-', $for);

		// Do the compare, if the for is greater, than what we have but not greater than what we are running .....
		if (compareVersions($near_version, $for) === -1 && compareVersions($for, $the_version) !== 1)
			$near_version = $for;
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
 * @package Packages
 * @param string $version
 * @param string $versions
 * @return boolean
 */
function matchPackageVersion($version, $versions)
{
	// Make sure everything is lowercase and clean of spaces.
	$version = str_replace(' ', '', strtolower($version));
	$versions = explode(',', str_replace(' ', '', strtolower($versions)));

	// Perhaps we do accept anything?
	if (in_array('all', $versions))
		return true;

	// Loop through each version.
	foreach ($versions as $for)
	{
		// Wild card spotted?
		if (strpos($for, '*') !== false)
			$for = str_replace('*', '0dev0', $for) . '-' . str_replace('*', '999', $for);

		// Do we have a range?
		if (strpos($for, '-') !== false)
		{
			list ($lower, $upper) = explode('-', $for);

			// Compare the version against lower and upper bounds.
			if (compareVersions($version, $lower) > -1 && compareVersions($version, $upper) < 1)
				return true;
		}
		// Otherwise check if they are equal...
		elseif (compareVersions($version, $for) === 0)
			return true;
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
 * @package Packages
 * @param string $version1
 * @param string $version2
 * @return int (-1, 0, 1)
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
		return 0;

	// Get version numbering categories...
	if (!isset($categories))
		$categories = array_keys($versions[1]);

	// Loop through each category.
	foreach ($categories as $category)
	{
		// Is there something for us to calculate?
		if ($versions[1][$category] !== $versions[2][$category])
		{
			// Dev builds are a problematic exception.
			// (stable) dev < (stable) but (unstable) dev = (unstable)
			if ($category == 'type')
				return $versions[1][$category] > $versions[2][$category] ? ($versions[1]['dev'] ? -1 : 1) : ($versions[2]['dev'] ? 1 : -1);
			elseif ($category == 'dev')
				return $versions[1]['dev'] ? ($versions[2]['type'] == 'stable' ? -1 : 0) : ($versions[1]['type'] == 'stable' ? 1 : 0);
			// Otherwise a simple comparison.
			else
				return $versions[1][$category] > $versions[2][$category] ? 1 : -1;
		}
	}

	// They are the same!
	return 0;
}

/**
 * Parses special identifiers out of the specified path.
 *
 * @package Packages
 * @param string $path
 * @return string The parsed path
 */
function parse_path($path)
{
	global $modSettings, $settings, $temp_path;

	if (empty($path))
		return '';

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
		'LANGUAGEDIR' => $settings['default_theme_dir'] . '/languages',
		'SMILEYDIR' => $modSettings['smileys_dir'],
	);

	// Do we parse in a package directory?
	if (!empty($temp_path))
		$dirs['PACKAGE'] = $temp_path;

	if (strlen($path) == 0)
		trigger_error('parse_path(): There should never be an empty filename', E_USER_ERROR);

	// Check if they are using some old software install paths
	if (strpos($path, '$') === 0 && isset($dirs[strtoupper(substr($path, 1))]))
		$path = strtoupper(substr($path, 1));

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
 * @package Packages
 * @param string $dir
 * @param bool $delete_dir = true
 */
function deltree($dir, $delete_dir = true)
{
	global $package_ftp;

	if (!file_exists($dir))
		return;

	// Read all the files in the directory
	try
	{
		$entrynames = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
		foreach ($entrynames as $entryname)
		{
			// Recursively dive in to each directory looking for files to delete
			if ($entryname->isDir())
				deltree($entryname->getPathname());
			// A file, delete it by any means necessary
			else
			{
				// Here, 755 doesn't really matter since we're deleting it anyway.
				if (isset($package_ftp))
				{
					$ftp_file = strtr($entryname->getPathname(), array($_SESSION['pack_ftp']['root'] => ''));

					if (!$entryname->isWritable())
						$package_ftp->chmod($ftp_file, 0777);

					$package_ftp->unlink($ftp_file);
				}
				else
				{
					if (!$entryname->isWritable())
						elk_chmod($entryname->getPathname(), 0777);

					@unlink($entryname->getPathname());
				}
			}
		}
	}
	catch (UnexpectedValueException $e)
	{
		// Can't open the directory for reading, try FTP to remove it before quiting
		if ($delete_dir && isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));
			if (!is_writable($dir . '/'))
				$package_ftp->chmod($ftp_file, 0777);
			$package_ftp->unlink($ftp_file);
		}

		return;
	}

	// Remove the directory entry as well?
	if ($delete_dir)
	{
		if (isset($package_ftp))
		{
			$ftp_file = strtr($dir, array($_SESSION['pack_ftp']['root'] => ''));

			if (!is_writable($dir . '/'))
				$package_ftp->chmod($ftp_file, 0777);

			$package_ftp->unlink($ftp_file);
		}
		else
		{
			if (!is_writable($dir))
				elk_chmod($dir, 0777);

			@rmdir($dir);
		}
	}
}

/**
 * Creates the specified tree structure with the mode specified.
 *
 * - Creates every directory in path until it finds one that already exists.
 *
 * @package Packages
 * @param string $strPath
 * @param int|false $mode
 * @return boolean true if successful, false otherwise
 */
function mktree($strPath, $mode)
{
	global $package_ftp;

	// If its already a directory
	if (is_dir($strPath))
	{
		// Not writable, try to make it so with FTP or not
		if (!is_writable($strPath) && $mode !== false)
		{
			if (isset($package_ftp))
				$package_ftp->chmod(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')), $mode);
			else
				elk_chmod($strPath, $mode);
		}

		// See if we can open it for access, return the result
		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return is_writable($strPath);
		}
		else
			return false;
	}

	// Is this an invalid path and/or we can't make the directory?
	if ($strPath == dirname($strPath) || !mktree(dirname($strPath), $mode))
		return false;

	// Is the dir writable and do we have permission to attempt to make it so
	if (!is_writable(dirname($strPath)) && $mode !== false)
	{
		if (isset($package_ftp))
			$package_ftp->chmod(dirname(strtr($strPath, array($_SESSION['pack_ftp']['root'] => ''))), $mode);
		else
			elk_chmod(dirname($strPath), $mode);
	}

	// Return an ftp control if using FTP
	if ($mode !== false && isset($package_ftp))
		return $package_ftp->create_dir(strtr($strPath, array($_SESSION['pack_ftp']['root'] => '')));
	// Can't change the mode so just return the current availability
	elseif ($mode === false)
	{
		$test = @opendir(dirname($strPath));
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
	// Only one choice left and thats to try and make a directory
	else
	{
		@mkdir($strPath, $mode);

		// Check and return if we were successful
		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
}

/**
 * Copies one directory structure over to another.
 *
 * - Requires the destination to be writable.
 *
 * @package Packages
 * @param string $source
 * @param string $destination
 */
function copytree($source, $destination)
{
	global $package_ftp;

	if (!file_exists($destination) || !is_writable($destination))
		mktree($destination, 0755);

	if (!is_writable($destination))
		mktree($destination, 0777);

	$current_dir = opendir($source);
	if ($current_dir === false)
		return;

	while ($entryname = readdir($current_dir))
	{
		if (in_array($entryname, array('.', '..')))
			continue;

		if (isset($package_ftp))
			$ftp_file = strtr($destination . '/' . $entryname, array($_SESSION['pack_ftp']['root'] => ''));

		if (is_file($source . '/' . $entryname))
		{
			if (isset($package_ftp) && !file_exists($destination . '/' . $entryname))
				$package_ftp->create_file($ftp_file);
			elseif (!file_exists($destination . '/' . $entryname))
				@touch($destination . '/' . $entryname);
		}

		package_chmod($destination . '/' . $entryname);

		if (is_dir($source . '/' . $entryname))
			copytree($source . '/' . $entryname, $destination . '/' . $entryname);
		elseif (file_exists($destination . '/' . $entryname))
			package_put_contents($destination . '/' . $entryname, package_get_contents($source . '/' . $entryname));
		else
			copy($source . '/' . $entryname, $destination . '/' . $entryname);
	}

	closedir($current_dir);
}

/**
 * Create a tree listing for a given directory path
 *
 * @package Packages
 * @param string $path
 * @param string $sub_path = ''
 * @return array
 */
function listtree($path, $sub_path = '')
{
	$data = array();

	$dir = @dir($path . $sub_path);
	if (!$dir)
		return array();

	while ($entry = $dir->read())
	{
		if ($entry == '.' || $entry == '..')
			continue;

		if (is_dir($path . $sub_path . '/' . $entry))
			$data = array_merge($data, listtree($path, $sub_path . '/' . $entry));
		else
			$data[] = array(
				'filename' => $sub_path == '' ? $entry : $sub_path . '/' . $entry,
				'size' => filesize($path . $sub_path . '/' . $entry),
				'skipped' => false,
			);
	}
	$dir->close();

	return $data;
}

/**
 * Parses a xml-style modification file (file).
 *
 * @package Packages
 * @param string $file
 * @param bool $testing = true tells it the modifications shouldn't actually be saved.
 * @param bool $undo = false specifies that the modifications the file requests should be undone; this doesn't work with everything (regular expressions.)
 * @param mixed[] $theme_paths = array()
 * @return array an array of those changes made.
 */
function parseModification($file, $testing = true, $undo = false, $theme_paths = array())
{
	global $txt, $modSettings;

	detectServer()->setTimeLimit(600);

	$xml = new Xml_Array(strtr($file, array("\r" => '')));
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
				$real_filename = BOARDDIR . '/' . $filename;

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
				continue;

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
			$files_to_change += $custom_themes_add[$files_to_change[1]];

		// Now, loop through all the files we're changing, and, well, change them ;)
		foreach ($files_to_change as $theme => $working_file)
		{
			if ($working_file[0] != '/' && $working_file[1] != ':')
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
			elseif (!file_exists($working_file) && $file->exists('@error') && trim($file->fetch('@error')) == 'skip')
			{
				$actions[] = array(
					'type' => 'skipping',
					'filename' => $working_file,
				);
				continue;
			}
			// Okay, we're creating this file then...?
			elseif (!file_exists($working_file))
				$working_data = '';
			// Phew, it exists!  Load 'er up!
			else
				$working_data = str_replace("\r", '', package_get_contents($working_file));

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
					$actual_operation['searches'][] = array(
						'position' => $search->exists('@position') && in_array(trim($search->fetch('@position')), array('before', 'after', 'replace', 'end')) ? trim($search->fetch('@position')) : 'replace',
						'is_reg_exp' => $search->exists('@regexp') && trim($search->fetch('@regexp')) === 'true',
						'loose_whitespace' => $search->exists('@whitespace') && trim($search->fetch('@whitespace')) === 'loose',
						'search' => $search->fetch('.'),
						'add' => $add,
						'preg_search' => '',
						'preg_replace' => '',
					);

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
								$actions[] = array(
									'type' => 'failure',
									'filename' => $working_file,
									'search' => $search['search'],
									'is_custom' => $theme > 1 ? $theme : 0,
								);

							// Continue to the next operation.
							continue 2;
						}

						// The replacement is now the search subject...
						if ($search['position'] === 'replace' || $search['position'] === 'end')
							$actual_operation['searches'][$i]['search'] = $search['add'];
						else
						{
							// Reversing a before/after modification becomes a replacement.
							$actual_operation['searches'][$i]['position'] = 'replace';

							if ($search['position'] === 'before')
								$actual_operation['searches'][$i]['search'] .= $search['add'];
							elseif ($search['position'] === 'after')
								$actual_operation['searches'][$i]['search'] = $search['add'] . $search['search'];
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
						$actual_operation['searches'][$i]['preg_search'] = $search['search'];
					else
					{
						// Make the search subject fit into a regular expression.
						$actual_operation['searches'][$i]['preg_search'] = preg_quote($search['search'], '~');

						// Using 'loose', a random amount of tabs and spaces may be used.
						if ($search['loose_whitespace'])
							$actual_operation['searches'][$i]['preg_search'] = preg_replace('~[ \t]+~', '[ \t]+', $actual_operation['searches'][$i]['preg_search']);
					}

					// Shuzzup.  This is done so we can safely use a regular expression. ($0 is bad!!)
					$actual_operation['searches'][$i]['preg_replace'] = strtr($search['add'], array('$' => '[$PACK' . 'AGE1$]', '\\' => '[$PACK' . 'AGE2$]'));

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
						continue;

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
			$working_data = strtr($working_data, array('[$PACK' . 'AGE1$]' => '$', '[$PACK' . 'AGE2$]' => '\\'));

			package_chmod($working_file);

			if ((file_exists($working_file) && !is_writable($working_file)) || (!file_exists($working_file) && !is_writable(dirname($working_file))))
				$actions[] = array(
					'type' => 'chmod',
					'filename' => $working_file
				);

			if (basename($working_file) == 'Settings_bak.php')
				continue;

			if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
			{
				// No, no, not Settings.php!
				if (basename($working_file) == 'Settings.php')
					@copy($working_file, dirname($working_file) . '/Settings_bak.php');
				else
					@copy($working_file, $working_file . '~');
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
 * @package Packages
 * @param string $filename
 * @return string
 */
function package_get_contents($filename)
{
	global $package_cache, $modSettings;

	if (!isset($package_cache))
	{
		$mem_check = detectServer()->setMemoryLimit('128M');

		// Windows doesn't seem to care about the memory_limit.
		if (!empty($modSettings['package_disable_cache']) || $mem_check || stripos(PHP_OS, 'win') !== false)
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (strpos($filename, 'packages/') !== false || $package_cache === false || !isset($package_cache[$filename]))
		return file_get_contents($filename);
	else
		return $package_cache[$filename];
}

/**
 * Writes data to a file, almost exactly like the file_put_contents() function.
 *
 * - uses FTP to create/chmod the file when necessary and available.
 * - uses text mode for text mode file extensions.
 * - returns the number of bytes written.
 *
 * @package Packages
 * @param string $filename
 * @param string $data
 * @param bool $testing
 * @return int
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
			$package_cache = array();
		else
			$package_cache = false;
	}

	if (isset($package_ftp))
		$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

	if (!file_exists($filename) && isset($package_ftp))
		$package_ftp->create_file($ftp_file);
	elseif (!file_exists($filename))
		@touch($filename);

	package_chmod($filename);

	if (!$testing && (strpos($filename, 'packages/') !== false || $package_cache === false))
	{
		$fp = @fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');

		// We should show an error message or attempt a rollback, no?
		if (!$fp)
			return false;

		fwrite($fp, $data);
		fclose($fp);
	}
	elseif (strpos($filename, 'packages/') !== false || $package_cache === false)
		return strlen($data);
	else
	{
		$package_cache[$filename] = $data;

		// Permission denied, eh?
		$fp = @fopen($filename, 'r+');
		if (!$fp)
			return false;
		fclose($fp);
	}

	return strlen($data);
}

/**
 * Clears (removes the files) the current package cache (temp directory)
 *
 * @package Packages
 * @param boolean $trash
 */
function package_flush_cache($trash = false)
{
	global $package_ftp, $package_cache;
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'vbs', 'tml', 'htm');

	if (empty($package_cache))
		return;

	// First, let's check permissions!
	foreach ($package_cache as $filename => $data)
	{
		if (isset($package_ftp))
			$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

		if (!file_exists($filename) && isset($package_ftp))
			$package_ftp->create_file($ftp_file);
		elseif (!file_exists($filename))
			@touch($filename);

		$result = package_chmod($filename);

		// If we are not doing our test pass, then lets do a full write check
		if (!$trash && !is_dir($filename))
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
		if (!is_dir($filename))
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
 * @package Packages
 * @param string $filename
 * @param string $perm_state = 'writable'
 * @param bool $track_change = false
 * @return boolean True if it worked, false if it didn't
 */
function package_chmod($filename, $perm_state = 'writable', $track_change = false)
{
	global $package_ftp;

	if (file_exists($filename) && is_writable($filename) && $perm_state == 'writable')
		return true;

	// Start off checking without FTP.
	if (!isset($package_ftp) || $package_ftp === false)
	{
		for ($i = 0; $i < 2; $i++)
		{
			$chmod_file = $filename;

			// Start off with a less aggressive test.
			if ($i == 0)
			{
				// If this file doesn't exist, then we actually want to look at whatever parent directory does.
				$subTraverseLimit = 2;
				while (!file_exists($chmod_file) && $subTraverseLimit)
				{
					$chmod_file = dirname($chmod_file);
					$subTraverseLimit--;
				}

				// Keep track of the writable status here.
				$file_permissions = @fileperms($chmod_file);
			}
			else
			{
				// This looks odd, but it's an attempt to work around PHP suExec.
				if (!file_exists($chmod_file) && $perm_state == 'writable')
				{
					$file_permissions = @fileperms(dirname($chmod_file));

					mktree(dirname($chmod_file), 0755);
					@touch($chmod_file);
					elk_chmod($chmod_file, 0755);
				}
				else
					$file_permissions = @fileperms($chmod_file);
			}

			// This looks odd, but it's another attempt to work around PHP suExec.
			if ($perm_state != 'writable')
				elk_chmod($chmod_file, $perm_state == 'execute' ? 0755 : 0644);
			else
			{
				if (!@is_writable($chmod_file))
					elk_chmod($chmod_file, 0755);
				if (!@is_writable($chmod_file))
					elk_chmod($chmod_file, 0777);
				if (!@is_writable(dirname($chmod_file)))
					elk_chmod($chmod_file, 0755);
				if (!@is_writable(dirname($chmod_file)))
					elk_chmod($chmod_file, 0777);
			}

			// The ultimate writable test.
			if ($perm_state == 'writable')
			{
				$fp = is_dir($chmod_file) ? @opendir($chmod_file) : @fopen($chmod_file, 'rb');
				if (@is_writable($chmod_file) && $fp)
				{
					if (!is_dir($chmod_file))
						fclose($fp);
					else
						closedir($fp);

					// It worked!
					if ($track_change)
						$_SESSION['pack_ftp']['original_perms'][$chmod_file] = $file_permissions;

					return true;
				}
			}
			elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$chmod_file]))
				unset($_SESSION['pack_ftp']['original_perms'][$chmod_file]);
		}

		// If we're here we're a failure.
		return false;
	}
	// Otherwise we do have FTP?
	elseif ($package_ftp !== false && !empty($_SESSION['pack_ftp']))
	{
		$ftp_file = strtr($filename, array($_SESSION['pack_ftp']['root'] => ''));

		// This looks odd, but it's an attempt to work around PHP suExec.
		if (!file_exists($filename) && $perm_state == 'writable')
		{
			$file_permissions = @fileperms(dirname($filename));

			mktree(dirname($filename), 0755);
			$package_ftp->create_file($ftp_file);
			$package_ftp->chmod($ftp_file, 0755);
		}
		else
			$file_permissions = @fileperms($filename);

		if ($perm_state != 'writable')
		{
			$package_ftp->chmod($ftp_file, $perm_state == 'execute' ? 0755 : 0644);
		}
		else
		{
			if (!@is_writable($filename))
				$package_ftp->chmod($ftp_file, 0777);
			if (!@is_writable(dirname($filename)))
				$package_ftp->chmod(dirname($ftp_file), 0777);
		}

		if (@is_writable($filename))
		{
			if ($track_change)
				$_SESSION['pack_ftp']['original_perms'][$filename] = $file_permissions;

			return true;
		}
		elseif ($perm_state != 'writable' && isset($_SESSION['pack_ftp']['original_perms'][$filename]))
			unset($_SESSION['pack_ftp']['original_perms'][$filename]);
	}

	// Oh dear, we failed if we get here.
	return false;
}

/**
 * Used to crypt the supplied ftp password in this session
 *
 * @package Packages
 * @param string $pass
 * @return string The encrypted password
 */
function package_crypt($pass)
{
	$n = strlen($pass);

	$salt = session_id();
	while (strlen($salt) < $n)
		$salt .= session_id();

	for ($i = 0; $i < $n; $i++)
		$pass[$i] = chr(ord($pass[$i]) ^ (ord($salt[$i]) - 32));

	return $pass;
}

/**
 * Creates a site backup before installing a package just in case things don't go
 * as planned.
 *
 * @package Packages
 * @param string $id
 */
function package_create_backup($id = 'backup')
{
	$db = database();
	$files = new ArrayIterator();
	$use_relative_paths = empty($_REQUEST['use_full_paths']);

	// The files that reside outside of sources, in the base, we add manually
	$base_files = array('index.php', 'SSI.php', 'agreement.txt', 'subscriptions.php',
	'email_imap_cron.php', 'emailpost.php', 'emailtopic.php');
	foreach ($base_files as $file)
	{
		if (file_exists(BOARDDIR . '/' . $file))
			$files[$use_relative_paths ? $file : realpath(BOARDDIR . '/' . $file)] = BOARDDIR . '/' . $file;
	}

	// Root directory where most of our files reside
	$dirs = array(
		SOURCEDIR => $use_relative_paths ? 'sources/' : strtr(SOURCEDIR . '/', '\\', '/')
	);

	// Find all installed theme directories
	$request = $db->query('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:theme_dir}',
		array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
		)
	);
	while ($row = $db->fetch_assoc($request))
		$dirs[$row['value']] = $use_relative_paths ? 'themes/' . basename($row['value']) . '/' : strtr($row['value'] . '/', '\\', '/');
	$db->free_result($request);

	try
	{
		foreach ($dirs as $dir => $dest)
		{
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
			);

			foreach ($iter as $entry => $dir)
			{
				if ($dir->isDir())
					continue;

				if (preg_match('~^(\.{1,2}|CVS|backup.*|help|images|.*\~)$~', $entry) != 0)
					continue;

				$files[$use_relative_paths ? str_replace(realpath(BOARDDIR), '', $entry) : $entry] = $entry;
			}
		}

		// Make sure we have a backup directory and its writable
		if (!file_exists(BOARDDIR . '/packages/backups'))
			mktree(BOARDDIR . '/packages/backups', 0777);

		if (!is_writable(BOARDDIR . '/packages/backups'))
			package_chmod(BOARDDIR . '/packages/backups');

		// Name the output file, yyyy-mm-dd_before_package_name.tar.gz
		$output_file = BOARDDIR . '/packages/backups/' . elk_strftime('%Y-%m-%d_') . preg_replace('~[$\\\\/:<>|?*"\']~', '', $id);
		$output_ext = '.tar';

		if (file_exists($output_file . $output_ext . '.gz'))
		{
			$i = 2;
			while (file_exists($output_file . '_' . $i . $output_ext . '.gz'))
				$i++;
			$output_file = $output_file . '_' . $i . $output_ext;
		}
		else
			$output_file .= $output_ext;

		// Buy some more time so we have enough to create this archive
		detectServer()->setTimeLimit(300);

		$a = new PharData($output_file);
		$a->buildFromIterator($files);
		$a->compress(Phar::GZ);

		/*
		 * Destroying the local var tells PharData to close its internal
		 * file pointer, enabling us to delete the uncompressed tarball.
		 */
		unset($a);
		unlink($output_file);
	}
	catch (Exception $e)
	{
		Errors::instance()->log_error($e->getMessage(), 'backup');

		return false;
	}

	return true;
}

/**
 * Get the contents of a URL, irrespective of allow_url_fopen.
 *
 * - reads the contents of an http or ftp address and returns the page in a string
 * - will accept up to 3 page redirections (redirection_level in the function call is private)
 * - if post_data is supplied, the value and length is posted to the given url as form data
 * - URL must be supplied in lowercase
 *
 * @package Packages
 * @param string $url
 * @param string $post_data = ''
 * @param bool $keep_alive = false
 * @param int $redirection_level = 3
 * @return string
 */
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 3)
{
	global $webmaster_email;
	static $keep_alive_dom = null, $keep_alive_fp = null;

	preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', $url, $match);

	// An FTP url. We should try connecting and RETRieving it...
	if (empty($match[1]))
		return false;
	elseif ($match[1] == 'ftp')
	{
		// Establish a connection and attempt to enable passive mode.
		$ftp = new Ftp_Connection(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? 21 : $match[5], 'anonymous', $webmaster_email);
		if ($ftp->error !== false || !$ftp->passive())
			return false;

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $match[6] . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
			return false;

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}
	// More likely a standard HTTP URL, first try to use cURL if available
	elseif (isset($match[1]) && $match[1] === 'http' && function_exists('curl_init'))
	{
		$fetch_data = new Curl_Fetch_Webdata(array(), $redirection_level);
		$fetch_data->get_url_data($url, $post_data);

		// no errors and a 200 result, then we have a good dataset, well we at least have data ;)
		if ($fetch_data->result('code') == 200 && !$fetch_data->result('error'))
			$data = $fetch_data->result('body');
		else
			return false;
	}
	// This is more likely; a standard HTTP URL.
	elseif (isset($match[1]) && $match[1] == 'http')
	{
		if ($keep_alive && $match[3] == $keep_alive_dom)
			$fp = $keep_alive_fp;
		if (empty($fp))
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? ($match[2] ? 443 : 80) : $match[5], $err, $err, 5);
			if (!$fp)
				return false;
		}

		if ($keep_alive)
		{
			$keep_alive_dom = $match[3];
			$keep_alive_fp = $fp;
		}

		// I want this, from there, and I'm not going to be bothering you for more (probably.)
		if (empty($post_data))
		{
			fwrite($fp, 'GET ' . ($match[6] !== '/' ? str_replace(' ', '%20', $match[6]) : '/') . ' HTTP/1.1' . "\r\n");
			fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
			fwrite($fp, 'User-Agent: PHP/ELK' . "\r\n");
			if ($keep_alive)
				fwrite($fp, 'Connection: Keep-Alive' . "\r\n\r\n");
			else
				fwrite($fp, 'Connection: close' . "\r\n\r\n");
		}
		else
		{
			fwrite($fp, 'POST ' . ($match[6] !== '/' ? $match[6] : '') . ' HTTP/1.1' . "\r\n");
			fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
			fwrite($fp, 'User-Agent: PHP/ELK' . "\r\n");
			if ($keep_alive)
				fwrite($fp, 'Connection: Keep-Alive' . "\r\n");
			else
				fwrite($fp, 'Connection: close' . "\r\n");
			fwrite($fp, 'Content-Type: application/x-www-form-urlencoded' . "\r\n");
			fwrite($fp, 'Content-Length: ' . strlen($post_data) . "\r\n\r\n");
			fwrite($fp, $post_data);
		}

		$response = fgets($fp, 768);

		// Redirect in case this location is permanently or temporarily moved.
		if ($redirection_level < 6 && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
		{
			$location = '';
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
				if (strpos($header, 'Location:') !== false)
					$location = trim(substr($header, strpos($header, ':') + 1));

			if (empty($location))
				return false;
			else
			{
				if (!$keep_alive)
					fclose($fp);
				return fetch_web_data($location, $post_data, $keep_alive, $redirection_level + 1);
			}
		}

		// Make sure we get a 200 OK.
		elseif (preg_match('~^HTTP/\S+\s+20[01]~i', $response) === 0)
			return false;

		// Skip the headers...
		while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
		{
			if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
				$content_length = $match[1];
			elseif (preg_match('~connection:\s*close~i', $header) != 0)
			{
				$keep_alive_dom = null;
				$keep_alive = false;
			}

			continue;
		}

		$data = '';
		if (isset($content_length))
		{
			while (!feof($fp) && strlen($data) < $content_length)
				$data .= fread($fp, $content_length - strlen($data));
		}
		else
		{
			while (!feof($fp))
				$data .= fread($fp, 4096);
		}

		if (!$keep_alive)
			fclose($fp);
	}
	else
	{
		// Umm, this shouldn't happen?
		trigger_error('fetch_web_data(): Bad URL', E_USER_NOTICE);
		$data = false;
	}

	return $data;
}

if (!function_exists('crc32_compat'))
{
	require_once(SUBSDIR . '/Compat.subs.php');
}

/**
 * Checks if a package is installed or not
 *
 * - If installed returns an array of themes, db changes and versions associated with
 * the package id
 *
 * @package Packages
 * @param string $id of package to check
 * @param string|null $install_id to check
 *
 * @return array
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
		return $result;

	// See if it is installed?
	$request = $db->query('', '
		SELECT version, themes_installed, db_changes, package_id, install_state
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
	);
	while ($row = $db->fetch_assoc($request))
	{
		$result = array(
			'old_themes' => explode(',', $row['themes_installed']),
			'old_version' => $row['version'],
			'db_changes' => empty($row['db_changes']) ? array() : Util::unserialize($row['db_changes']),
			'package_id' => $row['package_id'],
			'install_state' => $row['install_state'],
		);
	}
	$db->free_result($request);

	return $result;
}

/**
 * For uninstalling action, updates the log_packages install_state state to 0 (uninstalled)
 *
 * @package Packages
 * @param string $id package_id to update
 * @param string $install_id install id of the package
 */
function setPackageState($id, $install_id)
{
	global $user_info;

	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_packages
		SET install_state = {int:not_installed}, member_removed = {string:member_name}, id_member_removed = {int:current_member},
			time_removed = {int:current_time}
		WHERE package_id = {string:package_id}
			AND id_install = {int:install_id}',
		array(
			'current_member' => $user_info['id'],
			'not_installed' => 0,
			'current_time' => time(),
			'package_id' => $id,
			'member_name' => $user_info['name'],
			'install_id' => $install_id,
		)
	);
}

/**
 * Checks if a package is installed, and if so returns its version level
 *
 * @package Packages
 * @param string $id
 */
function checkPackageDependency($id)
{
	$db = database();

	$request = $db->query('', '
		SELECT version
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => $id,
		)
	);
	while ($row = $db->fetch_row($request))
		list ($version) = $row;
	$db->free_result($request);

	return $version;
}

/**
 * Adds a record to the log packages table
 *
 * @package Packages
 * @param mixed[] $packageInfo
 * @param string $failed_step_insert
 * @param string $themes_installed
 * @param string $db_changes
 * @param bool $is_upgrade
 * @param string $credits_tag
 */
function addPackageLog($packageInfo, $failed_step_insert, $themes_installed, $db_changes, $is_upgrade, $credits_tag)
{
	global $user_info;

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
			$user_info['id'], $user_info['name'], time(),
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
 * @package Packages
 * @param string $remote_url
 */
function isAuthorizedServer($remote_url)
{
	global $modSettings;

	// Know addon servers
	$servers = Util::unserialize($modSettings['authorized_package_servers']);
	if (empty($servers))
		return false;

	foreach ($servers as $server)
		if (preg_match('~^' . preg_quote($server) . '~', $remote_url) == 0)
			return true;

	return false;
}

/**
 * Simple wrapper around chmod
 *
 * - Checks proper value for mode is supplied
 * - Consolidates chmod error suppression to single function
 *
 * @param string $file
 * @param string|int|null $mode
 *
 * @return bool
 */
function elk_chmod($file, $mode = null)
{
	$result = false;

	$mode = trim($mode);
	if (empty($mode) || !is_numeric($mode))
	{
		if (is_dir($file))
		{
			$mode = 0755;
		}
		else
		{
			$mode = 0664;
		}
	}

	// Make sure we have a form of 0777 or '777' or '0777' so its safe for intval '8'
	if (($mode % 10) >= 8)
		$mode = decoct($mode);
	if ($mode == decoct(octdec($mode)))
		$result = @chmod($file, intval($mode, 8));

	return $result;
}
