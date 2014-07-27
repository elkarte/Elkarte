<?php

/**
 * This contains functions for handling "packages" (that includes compressed
 * archives, ElkArte addons, language packs, themes, smiley packs, etc.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

class Package
{
	protected $package_installed = null;
	protected $actions = null;
	protected $action_pointer = 0;
	protected $themeFinds = null;
	protected $theme_paths = null;
	protected $theme_actions = null;
	protected $base_path = '';
	protected $chmod_files = array();

	public function __construct($destination_url = '', $filename)
	{
		// Load up the package FTP information?
		create_chmod_control();

		// Make sure temp directory exists and is empty.
		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp', false);

		// Attempt to create the temp directory
		if (!mktree(BOARDDIR . '/packages/temp', 0755))
		{
			deltree(BOARDDIR . '/packages/temp', false);
			if (!mktree(BOARDDIR . '/packages/temp', 0777))
			{
				deltree(BOARDDIR . '/packages/temp', false);
				create_chmod_control(array(BOARDDIR . '/packages/temp/delme.tmp'), array('destination_url' => $destination_url, 'crash_on_error' => true));

				deltree(BOARDDIR . '/packages/temp', false);
				if (!mktree(BOARDDIR . '/packages/temp', 0777))
					throw new Elk_Exception('package_cant_download', false);
			}
		}

		if (!file_exists(BOARDDIR . '/packages/' . $filename))
		{
			deltree(BOARDDIR . '/packages/temp');
			throw new Elk_Exception('package_no_file', false);
		}

		$this->extractFiles($filename);
	}

	protected function extractFiles($filename)
	{
		// Extract the files so we can get things like the readme, etc.
		if (is_file(BOARDDIR . '/packages/' . $filename))
		{
			$extracted_files = read_tgz_file(BOARDDIR . '/packages/' . $filename, BOARDDIR . '/packages/temp');
			if ($extracted_files && !file_exists(BOARDDIR . '/packages/temp/package-info.xml'))
			{
				foreach ($extracted_files as $file)
				{
					if (basename($file['filename']) == 'package-info.xml')
					{
						$this->base_path = dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset($this->base_path))
				$this->base_path = '';
		}
		elseif (is_dir(BOARDDIR . '/packages/' . $filename))
		{
			copytree(BOARDDIR . '/packages/' . $filename, BOARDDIR . '/packages/temp');
			$extracted_files = listtree(BOARDDIR . '/packages/temp');
			$this->base_path = '';
		}
		else
			throw new Elk_Exception('no_access', false);
	}

	public function cleanup()
	{
		// Trash the cache... which will also check permissions for us!
		package_flush_cache(true);

		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp');

		if (!empty($this->chmod_files))
		{
			$ftp_status = create_chmod_control($this->chmod_files);
			return !empty($ftp_status['files']['notwritable']);
		}

		return false;
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
	 */
	function getPackageInfo($gzfilename)
	{
		// Extract package-info.xml from downloaded file. (*/ is used because it could be in any directory.)
		if (preg_match('~^https?://~i', $gzfilename) === 1)
			$packageInfo = read_tgz_file($gzfilename, '*/package-info.xml', true);
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
	 * Parses the actions in package-info.xml file from packages.
	 *
	 * What it does:
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
		global $forum_version, $context, $temp_path, $language;

		// Mayday!  That action doesn't exist!!
		if (empty($packageXML) || !$packageXML->exists($method))
			return array();

		// We haven't found the package script yet...
		$script = false;
		$the_version = strtr($forum_version, array('ElkArte ' => ''));

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
		$temp_path = BOARDDIR . '/packages/temp/' . (isset($this->base_path) ? $this->base_path : '');

		$context['readmes'] = array();
		$context['licences'] = array();

		// This is the testing phase... nothing shall be done yet.
		foreach ($actions as $action)
		{
			$actionType = $action->name();

			if (in_array($actionType, array('readme', 'code', 'database', 'modification', 'redirect', 'license')))
			{
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
					'boardmod' => $action->exists('@format') && $action->fetch('@format') == 'boardmod',
					'redirect_url' => $action->exists('@url') ? $action->fetch('@url') : '',
					'redirect_timeout' => $action->exists('@timeout') ? (int) $action->fetch('@timeout') : '',
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
				if (strlen(trim($url)) > 0 && substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://')
				{
					$url = 'http://' . $url;
					if (strlen($url) < 8 || (substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://'))
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
						$this_action['source'] = parse_path($action->fetch('@from'));
					else
						$this_action['source'] = $temp_path . $this_action['filename'];
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
	 * @return highest install value string or false
	 */
	function matchHighestPackageVersion($versions, $reset = false, $the_version)
	{
		global $forum_version;
		static $near_version = 0;

		if ($reset)
			$near_version = 0;

		// Normalize the $versions
		$versions = explode(',', str_replace(' ', '', strtolower($versions)));

		// If it is not ElkArte, let's just give up
		list ($the_brand,) = explode(' ', $forum_version, 2);
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

		@set_time_limit(600);

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
	 * Parses a boardmod-style modification file (file).
	 *
	 * @package Packages
	 * @param string $file
	 * @param bool $testing = true tells it the modifications shouldn't actually be saved.
	 * @param bool $undo = false specifies that the modifications the file requests should be undone.
	 * @param mixed[] $theme_paths = array()
	 * @return array an array of those changes made.
	 */
	function parseBoardMod($file, $testing = true, $undo = false, $theme_paths = array())
	{
		global $settings, $modSettings;

		@set_time_limit(600);
		$file = strtr($file, array("\r" => ''));

		$working_file = null;
		$working_search = null;
		$working_data = '';
		$replace_with = null;
		$actions = array();
		$everything_found = true;

		// This holds all the template changes in the standard mod file.
		$template_changes = array();

		// This is just the temporary file.
		$temp_file = $file;

		// This holds the actual changes on a step counter basis.
		$temp_changes = array();
		$counter = 0;
		$step_counter = 0;

		// Before we do *anything*, let's build a list of what we're editing, as it's going to be used for other theme edits.
		while (preg_match('~<(edit file|file|search|search for|add|add after|replace|add before|add above|above|before)>\n(.*?)\n</\\1>~is', $temp_file, $code_match) != 0)
		{
			$counter++;

			// Get rid of the old stuff.
			$temp_file = substr_replace($temp_file, '', strpos($temp_file, $code_match[0]), strlen($code_match[0]));

			// No interest to us?
			if ($code_match[1] != 'edit file' && $code_match[1] != 'file')
			{
				// It's a step, let's add that to the current steps.
				if (isset($temp_changes[$step_counter]))
					$temp_changes[$step_counter]['changes'][] = $code_match[0];
				continue;
			}

			// We've found a new edit - let's make ourself heard, kind of.
			$step_counter = $counter;
			$temp_changes[$step_counter] = array(
				'title' => $code_match[0],
				'changes' => array(),
			);

			$filename = parse_path($code_match[2]);

			// Now, is this a template file, and if so, which?
			foreach ($theme_paths as $id => $theme)
			{
				// If this filename is relative, if so take a guess at what it should be.
				if (strpos($filename, 'themes') === 0)
					$filename = BOARDDIR . '/' . $filename;

				if (strpos($filename, $theme['theme_dir']) === 0)
					$template_changes[$id][$counter] = substr($filename, strlen($theme['theme_dir']) + 1);
			}
		}

		// Reference for what theme ID this action belongs to.
		$theme_id_ref = array();

		// Now we know what templates we need to touch, cycle through each theme and work out what we need to edit.
		if (!empty($template_changes[1]))
		{
			foreach ($theme_paths as $id => $theme)
			{
				// Don't do default, it means nothing to me.
				if ($id == 1)
					continue;

				// Now, for each file do we need to edit it?
				foreach ($template_changes[1] as $pos => $template_file)
				{
					// It does? Add it to the list darlin'.
					if (file_exists($theme['theme_dir'] . '/' . $template_file) && (!isset($template_changes[$id][$pos]) || !in_array($template_file, $template_changes[$id][$pos])))
					{
						// Actually add it to the mod file too, so we can see that it will work ;)
						if (!empty($temp_changes[$pos]['changes']))
						{
							$file .= "\n\n" . '<edit file>' . "\n" . $theme['theme_dir'] . '/' . $template_file . "\n" . '</edit file>' . "\n\n" . implode("\n\n", $temp_changes[$pos]['changes']);
							$theme_id_ref[$counter] = $id;
							$counter += 1 + count($temp_changes[$pos]['changes']);
						}
					}
				}
			}
		}

		$counter = 0;
		$is_custom = 0;
		while (preg_match('~<(edit file|file|search|search for|add|add after|replace|add before|add above|above|before)>\n(.*?)\n</\\1>~is', $file, $code_match) != 0)
		{
			// This is for working out what we should be editing.
			$counter++;

			// Edit a specific file.
			if ($code_match[1] == 'file' || $code_match[1] == 'edit file')
			{
				// Backup the old file.
				if ($working_file !== null)
				{
					package_chmod($working_file);

					// Don't even dare.
					if (basename($working_file) == 'Settings_bak.php')
						continue;

					if (!is_writable($working_file))
						$actions[] = array(
							'type' => 'chmod',
							'filename' => $working_file
						);

					if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
					{
						if (basename($working_file) == 'Settings.php')
							@copy($working_file, dirname($working_file) . '/Settings_bak.php');
						else
							@copy($working_file, $working_file . '~');
					}

					package_put_contents($working_file, $working_data, $testing);
				}

				if ($working_file !== null)
					$actions[] = array(
						'type' => 'saved',
						'filename' => $working_file,
						'is_custom' => $is_custom,
					);

				// Is this "now working on" file a theme specific one?
				$is_custom = isset($theme_id_ref[$counter - 1]) ? $theme_id_ref[$counter - 1] : 0;

				// Make sure the file exists!
				$working_file = parse_path($code_match[2]);

				if ($working_file[0] != '/' && $working_file[1] != ':')
				{
					trigger_error('parseBoardMod(): The filename \'' . $working_file . '\' is not a full path!', E_USER_WARNING);

					$working_file = BOARDDIR . '/' . $working_file;
				}

				if (!file_exists($working_file))
				{
					$places_to_check = array(BOARDDIR, SOURCEDIR, $settings['default_theme_dir'], $settings['default_theme_dir'] . '/languages');

					foreach ($places_to_check as $place)
						if (file_exists($place . '/' . $working_file))
						{
							$working_file = $place . '/' . $working_file;
							break;
						}
				}

				if (file_exists($working_file))
				{
					// Load the new file.
					$working_data = str_replace("\r", '', package_get_contents($working_file));

					$actions[] = array(
						'type' => 'opened',
						'filename' => $working_file
					);
				}
				else
				{
					$actions[] = array(
						'type' => 'missing',
						'filename' => $working_file
					);

					$working_file = null;
					$everything_found = false;
				}

				// Can't be searching for something...
				$working_search = null;
			}
			// Search for a specific string.
			elseif (($code_match[1] == 'search' || $code_match[1] == 'search for') && $working_file !== null)
			{
				if ($working_search !== null)
				{
					$actions[] = array(
						'type' => 'error',
						'filename' => $working_file
					);

					$everything_found = false;
				}

				$working_search = $code_match[2];
			}
			// Must've already loaded a search string.
			elseif ($working_search !== null)
			{
				// This is the base string....
				$replace_with = $code_match[2];

				// Add this afterward...
				if ($code_match[1] == 'add' || $code_match[1] == 'add after')
					$replace_with = $working_search . "\n" . $replace_with;
				// Add this beforehand.
				elseif ($code_match[1] == 'before' || $code_match[1] == 'add before' || $code_match[1] == 'above' || $code_match[1] == 'add above')
					$replace_with .= "\n" . $working_search;
				// Otherwise.. replace with $replace_with ;).
			}

			// If we have a search string, replace string, and open file..
			if ($working_search !== null && $replace_with !== null && $working_file !== null)
			{
				// Make sure it's somewhere in the string.
				if ($undo)
				{
					$temp = $replace_with;
					$replace_with = $working_search;
					$working_search = $temp;
				}

				if (strpos($working_data, $working_search) !== false)
				{
					$working_data = str_replace($working_search, $replace_with, $working_data);

					$actions[] = array(
						'type' => 'replace',
						'filename' => $working_file,
						'search' => $working_search,
						'replace' => $replace_with,
						'search_original' => $working_search,
						'replace_original' => $replace_with,
						'position' => $code_match[1] == 'replace' ? 'replace' : ($code_match[1] == 'add' || $code_match[1] == 'add after' ? 'before' : 'after'),
						'is_custom' => $is_custom,
						'failed' => false,
					);
				}
				// It wasn't found!
				else
				{
					$actions[] = array(
						'type' => 'failure',
						'filename' => $working_file,
						'search' => $working_search,
						'is_custom' => $is_custom,
						'search_original' => $working_search,
						'replace_original' => $replace_with,
						'position' => $code_match[1] == 'replace' ? 'replace' : ($code_match[1] == 'add' || $code_match[1] == 'add after' ? 'before' : 'after'),
						'is_custom' => $is_custom,
						'failed' => true,
					);

					$everything_found = false;
				}

				// These don't hold any meaning now.
				$working_search = null;
				$replace_with = null;
			}

			// Get rid of the old tag.
			$file = substr_replace($file, '', strpos($file, $code_match[0]), strlen($code_match[0]));
		}

		// Backup the old file.
		if ($working_file !== null)
		{
			package_chmod($working_file);

			if (!is_writable($working_file))
				$actions[] = array(
					'type' => 'chmod',
					'filename' => $working_file
				);

			if (!$testing && !empty($modSettings['package_make_backups']) && file_exists($working_file))
			{
				if (basename($working_file) == 'Settings.php')
					@copy($working_file, dirname($working_file) . '/Settings_bak.php');
				else
					@copy($working_file, $working_file . '~');
			}

			package_put_contents($working_file, $working_data, $testing);
		}

		if ($working_file !== null)
			$actions[] = array(
				'type' => 'saved',
				'filename' => $working_file,
				'is_custom' => $is_custom,
			);

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
			$mem_check = setMemoryLimit('128M');

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
			$mem_check = setMemoryLimit('128M');

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
			if (!$trash)
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
			$fp = fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');
			fwrite($fp, $data);
			fclose($fp);
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
						@chmod($chmod_file, 0755);
					}
					else
						$file_permissions = @fileperms($chmod_file);
				}

				// This looks odd, but it's another attempt to work around PHP suExec.
				if ($perm_state != 'writable')
					@chmod($chmod_file, $perm_state == 'execute' ? 0755 : 0644);
				else
				{
					if (!@is_writable($chmod_file))
						@chmod($chmod_file, 0755);
					if (!@is_writable($chmod_file))
						@chmod($chmod_file, 0777);
					if (!@is_writable(dirname($chmod_file)))
						@chmod($chmod_file, 0755);
					if (!@is_writable(dirname($chmod_file)))
						@chmod($chmod_file, 0777);
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
	 * Checks if a package is installed, and if so returns its version level
	 *
	 * @package Packages
	 * @param string $id
	 */
	function checkPackageDependency($id)
	{
		$db = database();

		$version = false;

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
		list ($version) = $db->fetch_row($request);
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
	 * Validates that the remote url is one of our known package servers
	 *
	 * @package Packages
	 * @param string $remote_url
	 */
	function isAuthorizedServer($remote_url)
	{
		global $modSettings;

		// Know addon servers
		$servers = @unserialize($modSettings['authorized_package_servers']);
		if (empty($servers))
			return false;

		foreach ($servers as $server)
			if (preg_match('~^' . preg_quote($server) . '~', $remote_url) == 0)
				return true;

		return false;
	}

	/**
	 * Checks if a package is installed or not
	 *
	 * - If installed returns an array of themes, db changes and versions associated with
	 * the package id
	 *
	 * @package Packages
	 * @param string $id of package to check
	 */
	function isPackageInstalled($id)
	{
		global $context;

		$db = database();

		$result = array(
			'package_id' => null,
			'install_state' => null,
			'old_themes' => null,
			'old_version' => null,
			'db_changes' => null
		);

		if (empty($id))
			return $result;

		// See if it is installed?
		$request = $db->query('', '
			SELECT version, themes_installed, db_changes, package_id, install_state
			FROM {db_prefix}log_packages
			WHERE package_id = {string:current_package}
				AND install_state != {int:not_installed}
				' . (!empty($context['install_id']) ? ' AND id_install = {int:install_id} ' : '') . '
			ORDER BY time_installed DESC
			LIMIT 1',
			array(
				'not_installed' => 0,
				'current_package' => $id,
				'install_id' => $context['install_id'],
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$result = array(
				'old_themes' => explode(',', $row['themes_installed']),
				'old_version' => $row['version'],
				'db_changes' => empty($row['db_changes']) ? array() : unserialize($row['db_changes']),
				'package_id' => $row['package_id'],
				'install_state' => $row['install_state'],
			);
		}
		$db->free_result($request);

		return $result;
	}

	public function installedPackage($packageInfo)
	{
		global $txt;

		// See if it is installed?
		$this->package_installed = $this->isPackageInstalled($packageInfo['id']);

		$database_changes = array();
		if (isset($packageInfo['uninstall']['database']))
			$database_changes[] = $txt['execute_database_changes'] . ' - ' . $packageInfo['uninstall']['database'];
		elseif (!empty($this->package_installed['db_changes']))
		{
			foreach ($this->package_installed['db_changes'] as $change)
			{
				if (isset($change[2]) && isset($txt['package_db_' . $change[0]]))
					$database_changes[] = sprintf($txt['package_db_' . $change[0]], $change[1], $change[2]);
				elseif (isset($txt['package_db_' . $change[0]]))
					$database_changes[] = sprintf($txt['package_db_' . $change[0]], $change[1]);
				else
					$database_changes[] = $change[0] . '-' . $change[1] . (isset($change[2]) ? '-' . $change[2] : '');
			}
		}

		$this->preapreActions($packageInfo);

		return $database_changes;
	}

	public function getAction()
	{
		global $context, $txt;

		// Not failed until proven otherwise.
		$failed = false;
		$thisAction = array();

		$action = $this->actions[$this->action_pointer];
		$this->action_pointer++;

		switch ($action['type'])
		{
			case 'chmod':
			{
				$this->chmod_files[] = $action['filename'];
				break;
			}
			case 'readme':
			case 'license':
			{
				$type = 'package_' . $action['type'];
				if (file_exists(BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename']))
					$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');
				elseif (file_exists($action['filename']))
					$context[$type] = htmlspecialchars(trim(file_get_contents($action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');

				if (!empty($action['parse_bbc']))
				{
					require_once(SUBSDIR . '/Post.subs.php');
					preparsecode($context[$type]);
					$context[$type] = parse_bbc($context[$type]);
				}
				else
					$context[$type] = nl2br($context[$type]);

				break;
			}
			// Don't show redirects.
			case 'redirect':
			{
				break;
			}
			case 'error':
			{
				$context['has_failure'] = true;
				if (isset($action['error_msg']) && isset($action['error_var']))
					$context['failure_details'] = sprintf($txt['package_will_fail_' . $action['error_msg']], $action['error_var']);
				elseif (isset($action['error_msg']))
					$context['failure_details'] = isset($txt['package_will_fail_' . $action['error_msg']]) ? $txt['package_will_fail_' . $action['error_msg']] : $action['error_msg'];

				break;
			}
			case 'modification':
			{
				if (!file_exists(BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename']))
				{
					$context['has_failure'] = true;
					$context['actions'][] = array(
						'type' => $txt['execute_modification'],
						'action' => Util::htmlspecialchars(strtr($action['filename'], array(BOARDDIR => '.'))),
						'description' => $txt['package_action_error'],
						'failed' => true,
					);
				}
				else
				{
					if ($action['boardmod'])
						$mod_actions = parseBoardMod(@file_get_contents(BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename']), true, $action['reverse'], $this->theme_paths);
					else
						$mod_actions = parseModification(@file_get_contents(BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename']), true, $action['reverse'], $this->theme_paths);

					if (count($mod_actions) == 1 && isset($mod_actions[0]) && $mod_actions[0]['type'] == 'error' && $mod_actions[0]['filename'] == '-')
						$mod_actions[0]['filename'] = $action['filename'];

					foreach ($mod_actions as $key => $mod_action)
					{
						// Lets get the last section of the file name.
						if (isset($mod_action['filename']) && substr($mod_action['filename'], -13) != '.template.php')
							$actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $action['filename']);
						elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches))
							$actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php||' . $action['filename']);
						else
							$actual_filename = $key;

						if ($mod_action['type'] == 'opened')
							$failed = false;
						elseif ($mod_action['type'] == 'failure')
						{
							if (empty($mod_action['is_custom']))
								$context['has_failure'] = true;
							$failed = true;
						}
						elseif ($mod_action['type'] == 'chmod')
							$this->chmod_files[] = $mod_action['filename'];
						elseif ($mod_action['type'] == 'saved')
						{
							if (!empty($mod_action['is_custom']))
							{
								if (!isset($this->theme_actions[$mod_action['is_custom']]))
									$this->theme_actions[$mod_action['is_custom']] = array(
										'name' => $this->theme_paths[$mod_action['is_custom']]['name'],
										'actions' => array(),
										'has_failure' => $failed,
									);
								else
									$this->theme_actions[$mod_action['is_custom']]['has_failure'] |= $failed;

								$this->theme_actions[$mod_action['is_custom']]['actions'][$actual_filename] = array(
									'type' => $txt['execute_modification'],
									'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
									'description' => $failed ? $txt['package_action_failure'] : $txt['package_action_success'],
									'failed' => $failed,
								);
							}
							elseif (!isset($context['actions'][$actual_filename]))
							{
								$context['actions'][$actual_filename] = array(
									'type' => $txt['execute_modification'],
									'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
									'description' => $failed ? $txt['package_action_failure'] : $txt['package_action_success'],
									'failed' => $failed,
								);
							}
							else
							{
								$context['actions'][$actual_filename]['failed'] |= $failed;
								$context['actions'][$actual_filename]['description'] = $context['actions'][$actual_filename]['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'];
							}
						}
						elseif ($mod_action['type'] == 'skipping')
						{
							$context['actions'][$actual_filename] = array(
								'type' => $txt['execute_modification'],
								'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
								'description' => $txt['package_action_skipping']
							);
						}
						elseif ($mod_action['type'] == 'missing' && empty($mod_action['is_custom']))
						{
							$context['has_failure'] = true;
							$context['actions'][$actual_filename] = array(
								'type' => $txt['execute_modification'],
								'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
								'description' => $txt['package_action_missing'],
								'failed' => true,
							);
						}
						elseif ($mod_action['type'] == 'error')
							$context['actions'][$actual_filename] = array(
								'type' => $txt['execute_modification'],
								'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
								'description' => $txt['package_action_error'],
								'failed' => true,
							);
					}

					// We need to loop again just to get the operations down correctly.
					foreach ($mod_actions as $operation_key => $mod_action)
					{
						// Lets get the last section of the file name.
						if (isset($mod_action['filename']) && substr($mod_action['filename'], -13) != '.template.php')
							$actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $action['filename']);
						elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches))
							$actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php||' . $action['filename']);
						else
							$actual_filename = $key;

						// We just need it for actual parse changes.
						if (!in_array($mod_action['type'], array('error', 'result', 'opened', 'saved', 'end', 'missing', 'skipping', 'chmod')))
						{
							if (empty($mod_action['is_custom']))
								$context['actions'][$actual_filename]['operations'][] = array(
									'type' => $txt['execute_modification'],
									'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
									'description' => $mod_action['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'],
									'position' => $mod_action['position'],
									'operation_key' => $operation_key,
									'filename' => $action['filename'],
									'is_boardmod' => $action['boardmod'],
									'failed' => $mod_action['failed'],
									'ignore_failure' => !empty($mod_action['ignore_failure']),
								);

							// Themes are under the saved type.
							if (isset($mod_action['is_custom']) && isset($this->theme_actions[$mod_action['is_custom']]))
								$this->theme_actions[$mod_action['is_custom']]['actions'][$actual_filename]['operations'][] = array(
									'type' => $txt['execute_modification'],
									'action' => Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
									'description' => $mod_action['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'],
									'position' => $mod_action['position'],
									'operation_key' => $operation_key,
									'filename' => $action['filename'],
									'is_boardmod' => $action['boardmod'],
									'failed' => $mod_action['failed'],
									'ignore_failure' => !empty($mod_action['ignore_failure']),
								);
						}
					}
				}

				break;
			}
			case 'code':
			{
				$thisAction = array(
					'type' => $txt['execute_code'],
					'action' => Util::htmlspecialchars($action['filename']),
				);

				break;
			}
			case 'database':
			{
				$thisAction = array(
					'type' => $txt['execute_database_changes'],
					'action' => Util::htmlspecialchars($action['filename']),
				);

				break;
			}
			case 'create-dir':
			case 'create-dir':
			{
				$thisAction = array(
					'type' => $txt['package_create'] . ' ' . ($action['type'] == 'create-dir' ? $txt['package_tree'] : $txt['package_file']),
					'action' => Util::htmlspecialchars(strtr($action['destination'], array(BOARDDIR => '.')))
				);

				break;
			}
			case 'hook':
			{
				$action['description'] = !isset($action['hook'], $action['function']) ? $txt['package_action_failure'] : $txt['package_action_success'];

				if (!isset($action['hook'], $action['function']))
					$context['has_failure'] = true;

				$thisAction = array(
					'type' => $action['reverse'] ? $txt['execute_hook_remove'] : $txt['execute_hook_add'],
					'action' => sprintf($txt['execute_hook_action'], Util::htmlspecialchars($action['hook'])),
				);

				break;
			}
			case 'credits':
			{
				$thisAction = array(
					'type' => $txt['execute_credits_add'],
					'action' => sprintf($txt['execute_credits_action'], Util::htmlspecialchars($action['title'])),
				);

				break;
			}
			case 'requires':
			{
				$installed_version = false;
				$version_check = true;

				// Package missing required values?
				if (!isset($action['id']))
					$context['has_failure'] = true;
				else
				{
					// See if this dependency is installed
					$installed_version = checkPackageDependency($action['id']);

					// Do a version level check (if requested) in the most basic way
					$version_check = (isset($action['version']) ? $installed_version == $action['version'] : true);
				}

				// Set success or failure information
				$action['description'] = ($installed_version && $version_check) ? $txt['package_action_success'] : $txt['package_action_failure'];
				$context['has_failure'] = !($installed_version && $version_check);
				$thisAction = array(
					'type' => $txt['package_requires'],
					'action' => $txt['package_check_for'] . ' ' . $action['id'] . (isset($action['version']) ? (' / ' . ($version_check ? $action['version'] : '<span class="error">' . $action['version'] . '</span>')) : ''),
				);

				break;
			}
			case 'require-dir':
			case 'require-dir':
			{
				// Do this one...
				$thisAction = array(
					'type' => $txt['package_extract'] . ' ' . ($action['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
					'action' => Util::htmlspecialchars(strtr($action['destination'], array(BOARDDIR => '.')))
				);

				// Could this be theme related?
				if (!empty($action['unparsed_destination']) && preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $action['unparsed_destination'], $matches))
				{
					// Is the action already stated?
					$theme_action = !empty($action['theme_action']) && in_array($action['theme_action'], array('no', 'yes', 'auto')) ? $action['theme_action'] : 'auto';

					// If it's not auto do we think we have something we can act upon?
					if ($theme_action != 'auto' && !in_array($matches[1], array('languagedir', 'languages_dir', 'imagesdir', 'themedir')))
						$theme_action = '';
					// ... or if it's auto do we even want to do anything?
					elseif ($theme_action == 'auto' && $matches[1] != 'imagesdir')
						$theme_action = '';

					// So, we still want to do something?
					if ($theme_action != '')
						$this->themeFinds['candidates'][] = $action;
					// Otherwise is this is going into another theme record it.
					elseif ($matches[1] == 'themes_dir')
						$this->themeFinds['other_themes'][] = strtolower(strtr(parse_path($action['unparsed_destination']), array('\\' => '/')) . '/' . basename($action['filename']));
				}

				break;
			}
			case 'move-dir':
			case 'move-dir':
			{
				$thisAction = array(
					'type' => $txt['package_move'] . ' ' . ($action['type'] == 'move-dir' ? $txt['package_tree'] : $txt['package_file']),
					'action' => Util::htmlspecialchars(strtr($action['source'], array(BOARDDIR => '.'))) . ' => ' . Util::htmlspecialchars(strtr($action['destination'], array(BOARDDIR => '.')))
				);

				break;
			}
			case 'remove-dir':
			case 'remove-dir':
			{
				$thisAction = array(
					'type' => $txt['package_delete'] . ' ' . ($action['type'] == 'remove-dir' ? $txt['package_tree'] : $txt['package_file']),
					'action' => Util::htmlspecialchars(strtr($action['filename'], array(BOARDDIR => '.')))
				);

				// Could this be theme related?
				if (!empty($action['unparsed_filename']) && preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $action['unparsed_filename'], $matches))
				{
					// Is the action already stated?
					$theme_action = !empty($action['theme_action']) && in_array($action['theme_action'], array('no', 'yes', 'auto')) ? $action['theme_action'] : 'auto';
					$action['unparsed_destination'] = $action['unparsed_filename'];

					// If it's not auto do we think we have something we can act upon?
					if ($theme_action != 'auto' && !in_array($matches[1], array('languagedir', 'languages_dir', 'imagesdir', 'themedir')))
						$theme_action = '';
					// ... or if it's auto do we even want to do anything?
					elseif ($theme_action == 'auto' && $matches[1] != 'imagesdir')
						$theme_action = '';

					// So, we still want to do something?
					if ($theme_action != '')
						$this->themeFinds['candidates'][] = $action;
					// Otherwise is this is going into another theme record it.
					elseif ($matches[1] == 'themes_dir')
						$this->themeFinds['other_themes'][] = strtolower(strtr(parse_path($action['unparsed_filename']), array('\\' => '/')) . '/' . basename($action['filename']));
				}

				break;
			}
		}

		if (empty($thisAction))
			return true;

		if (isset($action['filename']))
		{
			if ($context['uninstalling'])
				$file = in_array($action['type'], array('remove-dir', 'remove-file')) ? $action['filename'] : BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename'];
			else
				$file = BOARDDIR . '/packages/temp/' . $this->base_path . $action['filename'];

			if (!file_exists($file))
			{
				$context['has_failure'] = true;

				$thisAction += array(
					'description' => $txt['package_action_error'],
					'failed' => true,
				);
			}
		}

		// @todo None given?
		if (empty($thisAction['description']))
			$thisAction['description'] = isset($action['description']) ? $action['description'] : '';

		return $thisAction;
	}

	public function actionPointer($set = null)
	{
		$this->action_pointer = (int) $set;
	}

	public function hasActions()
	{
		return !empty($this->actions);
	}

	public function themeFinds()
	{
		global $settings, $context, $txt;

		if (!empty($this->themeFinds['candidates']))
		{
			foreach ($this->themeFinds['candidates'] as $action_data)
			{
				// Get the part of the file we'll be dealing with.
				preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir)(\\|/)*(.+)*~i', $action_data['unparsed_destination'], $matches);

				if ($matches[1] == 'imagesdir')
					$path = '/' . basename($settings['default_images_url']);
				elseif ($matches[1] == 'languagedir' || $matches[1] == 'languages_dir')
					$path = '/languages';
				else
					$path = '';

				if (!empty($matches[3]))
					$path .= $matches[3];

				if (!$context['uninstalling'])
					$path .= '/' . basename($action_data['filename']);

				// Loop through each custom theme to note it's candidacy!
				foreach ($this->theme_paths as $id => $theme_data)
				{
					if (isset($theme_data['theme_dir']) && $id != 1)
					{
						$real_path = $theme_data['theme_dir'] . $path;

						// Confirm that we don't already have this dealt with by another entry.
						if (!in_array(strtolower(strtr($real_path, array('\\' => '/'))), $this->themeFinds['other_themes']))
						{
							// Check if we will need to chmod this.
							if (!mktree(dirname($real_path), false))
							{
								$temp = dirname($real_path);
								while (!file_exists($temp) && strlen($temp) > 1)
									$temp = dirname($temp);
								$this->chmod_files[] = $temp;
							}

							if ($action_data['type'] == 'require-dir' && !is_writable($real_path) && (file_exists($real_path) || !is_writable(dirname($real_path))))
								$this->chmod_files[] = $real_path;

							if (!isset($this->theme_actions[$id]))
								$this->theme_actions[$id] = array(
									'name' => $theme_data['name'],
									'actions' => array(),
								);

							if ($context['uninstalling'])
								$this->theme_actions[$id]['actions'][] = array(
									'type' => $txt['package_delete'] . ' ' . ($action_data['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
									'action' => strtr($real_path, array('\\' => '/', BOARDDIR => '.')),
									'description' => '',
									'value' => base64_encode(serialize(array('type' => $action_data['type'], 'orig' => $action_data['filename'], 'future' => $real_path, 'id' => $id))),
									'not_mod' => true,
								);
							else
								$this->theme_actions[$id]['actions'][] = array(
									'type' => $txt['package_extract'] . ' ' . ($action_data['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
									'action' => strtr($real_path, array('\\' => '/', BOARDDIR => '.')),
									'description' => '',
									'value' => base64_encode(serialize(array('type' => $action_data['type'], 'orig' => $action_data['destination'], 'future' => $real_path, 'id' => $id))),
									'not_mod' => true,
								);
						}
					}
				}
			}
		}

		return $this->theme_actions;
	}

	protected function preapreActions($packageInfo)
	{
		global $context;

		// This will hold data about anything that can be installed in other themes.
		$this->themeFinds = array(
			'candidates' => array(),
			'other_themes' => array(),
		);

		// Load up any custom themes we may want to install into...
		$this->theme_paths = getThemesPathbyID();

		// Uninstalling?
		if ($context['uninstalling'])
		{
			// Wait, it's not installed yet!
			if (!isset($this->package_installed['old_version']) && $context['uninstalling'])
			{
				deltree(BOARDDIR . '/packages/temp');
				fatal_lang_error('package_cant_uninstall', false);
			}

			$this->actions = parsePackageInfo($packageInfo['xml'], true, 'uninstall');

			// Gadzooks!  There's no uninstaller at all!?
			if (empty($this->actions))
			{
				deltree(BOARDDIR . '/packages/temp');
				fatal_lang_error('package_uninstall_cannot', false);
			}

			// Can't edit the custom themes it's edited if you're unisntalling, they must be removed.
			$context['themes_locked'] = true;

			// Only let them uninstall themes it was installed into.
			foreach ($this->theme_paths as $id => $data)
			{
				if ($id != 1 && !in_array($id, $this->package_installed['old_themes']))
					unset($this->theme_paths[$id]);
			}
		}
		elseif (isset($this->package_installed['old_version']) && $this->package_installed['old_version'] != $packageInfo['version'])
		{
			// Look for an upgrade...
			$this->actions = parsePackageInfo($packageInfo['xml'], true, 'upgrade', $this->package_installed['old_version']);

			// There was no upgrade....
			if (empty($this->actions))
				$context['is_installed'] = true;
			else
			{
				// Otherwise they can only upgrade themes from the first time around.
				foreach ($this->theme_paths as $id => $data)
				{
					if ($id != 1 && !in_array($id, $this->package_installed['old_themes']))
						unset($this->theme_paths[$id]);
				}
			}
		}
		elseif (isset($this->package_installed['old_version']) && $this->package_installed['old_version'] == $packageInfo['version'])
			$context['is_installed'] = true;

		if (!isset($this->package_installed['old_version']) || $context['is_installed'])
			$this->actions = parsePackageInfo($packageInfo['xml'], true, 'install');
	}
}
