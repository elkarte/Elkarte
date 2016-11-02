<?php

/**
 * This file is the main Package Manager.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * This class is the administration package manager controller.
 * Its main job is to install/uninstall, allow to browse, packages.
 * In fact, just about everything related to addon packages, including FTP connections when necessary.
 *
 * @package Packages
 */
class Packages_Controller extends Action_Controller
{
	/**
	 * listing of files in a packages
	 * @var array|boolean
	 */
	private $_extracted_files;

	/**
	 * Filename of the package
	 * @var string
	 */
	private $_filename;

	/**
	 * Base path of the package
	 * @var string
	 */
	private $_base_path;

	/**
	 * If this is an un-install pass or not
	 * @var boolean
	 */
	private $_uninstalling;

	/**
	 * If the package is installed, previously or not
	 * @var boolean
	 */
	private $_is_installed;

	/**
	 * The id from the DB or an installed package
	 * @var int
	 */
	public $install_id;

	/**
	 * Array of installed theme paths
	 * @var string[]
	 */
	public $theme_paths;

	/**
	 * Array of files / directories that require permissions
	 * @var array
	 */
	public $chmod_files;

	/**
	 * Pre Dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// Generic subs for this controller
		require_once(SUBSDIR . '/Package.subs.php');
	}

	/**
	 * Entry point, the default method of this controller.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// Admins-only!
		isAllowedTo('admin_forum');

		// Load all the basic stuff.
		loadLanguage('Packages');
		loadTemplate('Packages');
		loadCSSFile('admin.css');
		$context['page_title'] = $txt['package'];

		// Delegation makes the world... that is, the package manager go 'round.
		$subActions = array(
			'browse' => array($this, 'action_browse'),
			'remove' => array($this, 'action_remove'),
			'list' => array($this, 'action_list'),
			'ftptest' => array($this, 'action_ftptest'),
			'install' => array($this, 'action_install'),
			'install2' => array($this, 'action_install2'),
			'uninstall' => array($this, 'action_install'),
			'uninstall2' => array($this, 'action_install2'),
			'installed' => array($this, 'action_browse'),
			'options' => array($this, 'action_options'),
			'perms' => array($this, 'action_perms'),
			'flush' => array($this, 'action_flush'),
			'examine' => array($this, 'action_examine'),
			'showoperations' => array($this, 'action_showoperations'),
			// The following two belong to PackageServers,
			// for UI's sake moved here at least temporarily
			'servers' => array(
				'controller' => 'PackageServers_Controller',
				'function' => 'action_list'),
			'upload' => array(
				'controller' => 'PackageServers_Controller',
				'function' => 'action_upload'),
		);

		// Set up action/subaction stuff.
		$action = new Action('packages');

		// Set up some tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['package_manager'],
			'description' => $txt['package_manager_desc'],
			'tabs' => array(
				'browse' => array(
				),
				'installed' => array(
					'description' => $txt['installed_packages_desc'],
				),
				'perms' => array(
					'description' => $txt['package_file_perms_desc'],
				),
				// The following two belong to PackageServers,
				// for UI's sake moved here at least temporarily
				'servers' => array(
					'description' => $txt['download_packages_desc'],
				),
				'upload' => array(
					'description' => $txt['upload_packages_desc'],
				),
				'options' => array(
					'description' => $txt['package_install_options_desc'],
				),
			),
		);

		// Work out exactly who it is we are calling. call integrate_sa_packages
		$subAction = $action->initialize($subActions, 'browse');

		// Set up for the template
		$context['sub_action'] = $subAction;

		// Lets just do it!
		$action->dispatch($subAction);
	}

	/**
	 * Test install a package.
	 */
	public function action_install()
	{
		global $txt, $context, $scripturl;

		// You have to specify a file!!
		$file = $this->_req->getQuery('package', 'trim');
		if (empty($file))
			redirectexit('action=admin;area=packages');

		// What are we trying to do
		$this->_filename = (string) preg_replace('~[\.]+~', '.', $file);
		$this->_uninstalling = $this->_req->query->sa === 'uninstall';

		// If we can't find the file, our install ends here
		if (!file_exists(BOARDDIR . '/packages/' . $this->_filename))
			Errors::instance()->fatal_lang_error('package_no_file', false);

		// Do we have an existing id, for uninstalls and the like.
		$this->install_id = $this->_req->getQuery('pid', 'intval', 0);

		// This will be needed
		require_once(SUBSDIR . '/Themes.subs.php');

		// Load up the package FTP information?
		create_chmod_control();

		// Make sure our temp directory exists and is empty.
		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp', false);
		else
			$this->_create_temp_dir();

		// Extract the files in to the temp so we can get things like the readme, etc.
		$this->_extract_files_temp();

		// Load up any custom themes we may want to install into...
		$this->theme_paths = getThemesPathbyID();

		// Get the package info...
		$packageInfo = getPackageInfo($this->_filename);
		if (!is_array($packageInfo))
			Errors::instance()->fatal_lang_error($packageInfo);

		$packageInfo['filename'] = $this->_filename;

		// The addon isn't installed.... unless proven otherwise.
		$this->_is_installed = false;

		// See if it is installed?
		$package_installed = isPackageInstalled($packageInfo['id'], $this->install_id);

		$context['database_changes'] = array();
		if (isset($packageInfo['uninstall']['database']))
			$context['database_changes'][] = $txt['execute_database_changes'] . ' - ' . $packageInfo['uninstall']['database'];
		elseif (!empty($package_installed['db_changes']))
		{
			foreach ($package_installed['db_changes'] as $change)
			{
				if (isset($change[2]) && isset($txt['package_db_' . $change[0]]))
					$context['database_changes'][] = sprintf($txt['package_db_' . $change[0]], $change[1], $change[2]);
				elseif (isset($txt['package_db_' . $change[0]]))
					$context['database_changes'][] = sprintf($txt['package_db_' . $change[0]], $change[1]);
				else
					$context['database_changes'][] = $change[0] . '-' . $change[1] . (isset($change[2]) ? '-' . $change[2] : '');
			}
		}

		$actions = $this->_get_package_actions($package_installed, $packageInfo);

		$context['actions'] = array();
		$context['ftp_needed'] = false;

		// No actions found, return so we can display an error
		if (empty($actions))
			return;

		// Now prepare things for the template using the package actions class
		$pka = new Package_Actions();
		$pka->test_init($actions, $this->_uninstalling, $this->_base_path, $this->theme_paths);

		$context['has_failure'] = $pka->has_failure;
		$context['failure_details'] = $pka->failure_details;
		$context['actions'] = $pka->ourActions;

		// Change our last link tree item for more information on this Packages area.
		$context['linktree'][count($context['linktree']) - 1] = array(
			'url' => $scripturl . '?action=admin;area=packages;sa=browse',
			'name' => $this->_uninstalling ? $txt['package_uninstall_actions'] : $txt['install_actions']
		);

		// All things to make the template go round
		$context['page_title'] .= ' - ' . ($this->_uninstalling ? $txt['package_uninstall_actions'] : $txt['install_actions']);
		$context['sub_template'] = 'view_package';
		$context['filename'] = $this->_filename;
		$context['package_name'] = isset($packageInfo['name']) ? $packageInfo['name'] : $this->_filename;
		$context['is_installed'] = $this->_is_installed;
		$context['uninstalling'] = $this->_uninstalling;
		$context['extract_type'] = isset($packageInfo['type']) ? $packageInfo['type'] : 'modification';

		// Have we got some things which we might want to do "multi-theme"?
		$this->_multi_theme($pka->themeFinds['candidates']);

		// Trash the cache... which will also check permissions for us!
		package_flush_cache(true);

		// Clear the temp directory
		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp');

		// Will we need chmod permissions to pull this off
		$this->chmod_files = !empty($pka->chmod_files) ? $pka->chmod_files : array();
		if (!empty($this->chmod_files))
		{
			$ftp_status = create_chmod_control($this->chmod_files);
			$context['ftp_needed'] = !empty($ftp_status['files']['notwritable']) && !empty($context['package_ftp']);
		}

		$context['post_url'] = $scripturl . '?action=admin;area=packages;sa=' . ($this->_uninstalling ? 'uninstall' : 'install') . ($context['ftp_needed'] ? '' : '2') . ';package=' . $this->_filename . ';pid=' . $this->install_id;
		checkSubmitOnce('register');
	}

	/**
	 * Determines the availability / validity of installing a package in any of the installed themes
	 * @param array $themeFinds
	 */
	private function _multi_theme($themeFinds)
	{
		global $settings, $txt, $context;

		if (!empty($themeFinds['candidates']))
		{
			foreach ($themeFinds['candidates'] as $action_data)
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

				if (!$this->_uninstalling)
					$path .= '/' . basename($action_data['filename']);

				// Loop through each custom theme to note it's candidacy!
				foreach ($this->theme_paths as $id => $theme_data)
				{
					if (isset($theme_data['theme_dir']) && $id != 1)
					{
						$real_path = $theme_data['theme_dir'] . $path;

						// Confirm that we don't already have this dealt with by another entry.
						if (!in_array(strtolower(strtr($real_path, array('\\' => '/'))), $themeFinds['other_themes']))
						{
							// Check if we will need to chmod this.
							if (!mktree(dirname($real_path), false))
							{
								$temp = dirname($real_path);
								while (!file_exists($temp) && strlen($temp) > 1)
									$temp = dirname($temp);

								$this->chmod_files[] = $temp;
							}

							if ($action_data['type'] === 'require-dir' && !is_writable($real_path) && (file_exists($real_path) || !is_writable(dirname($real_path))))
								$this->chmod_files[] = $real_path;

							if (!isset($context['theme_actions'][$id]))
								$context['theme_actions'][$id] = array(
									'name' => $theme_data['name'],
									'actions' => array(),
								);

							if ($this->_uninstalling)
								$context['theme_actions'][$id]['actions'][] = array(
									'type' => $txt['package_delete'] . ' ' . ($action_data['type'] === 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
									'action' => strtr($real_path, array('\\' => '/', BOARDDIR => '.')),
									'description' => '',
									'value' => base64_encode(json_encode(array('type' => $action_data['type'], 'orig' => $action_data['filename'], 'future' => $real_path, 'id' => $id))),
									'not_mod' => true,
								);
							else
								$context['theme_actions'][$id]['actions'][] = array(
									'type' => $txt['package_extract'] . ' ' . ($action_data['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
									'action' => strtr($real_path, array('\\' => '/', BOARDDIR => '.')),
									'description' => '',
									'value' => base64_encode(json_encode(array('type' => $action_data['type'], 'orig' => $action_data['destination'], 'future' => $real_path, 'id' => $id))),
									'not_mod' => true,
								);
						}
					}
				}
			}
		}
	}

	/**
	 * Extracts a package file in the packages/temp directory
	 *
	 * - Sets the base path as needed
	 * - Loads $this->_extracted_files with the package file listing
	 */
	private function _extract_files_temp()
	{
		// Is it a file in the package directory
		if (is_file(BOARDDIR . '/packages/' . $this->_filename))
		{
			// Unpack the files in to the packages/temp directory
			$this->_extracted_files = read_tgz_file(BOARDDIR . '/packages/' . $this->_filename, BOARDDIR . '/packages/temp');

			// Determine the base path for the package
			if ($this->_extracted_files && !file_exists(BOARDDIR . '/packages/temp/package-info.xml'))
			{
				foreach ($this->_extracted_files as $file)
				{
					if (basename($file['filename']) === 'package-info.xml')
					{
						$this->_base_path = dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset($this->_base_path))
				$this->_base_path = '';
		}
		// Perhaps its a directory then, assumed to be extracted
		elseif (!empty($this->_filename) && is_dir(BOARDDIR . '/packages/' . $this->_filename))
		{
			// Copy the directory to the temp directory
			copytree(BOARDDIR . '/packages/' . $this->_filename, BOARDDIR . '/packages/temp');

			// Get the file listing
			$this->_extracted_files = listtree(BOARDDIR . '/packages/temp');
			$this->_base_path = '';
		}
		// Well we don't know what it is then, so we stop
		else
			Errors::instance()->fatal_lang_error('no_access', false);
	}

	/**
	 * Returns the actions that are required to install / uninstall / upgrade a package.
	 * Actions are defined by parsePackageInfo
	 * Sets the is_installed flag
	 *
	 * @param array $package_installed
	 * @param array $packageInfo Details for the package being tested/installed, set by getPackageInfo
	 * @param boolean $testing passed to parsePackageInfo, true for test install, false for real install
	 */
	private function _get_package_actions($package_installed, $packageInfo, $testing = true)
	{
		global $context;

		$actions = array();

		// Uninstalling?
		if ($this->_uninstalling)
		{
			// Wait, it's not installed yet!
			if (!isset($package_installed['old_version']))
			{
				deltree(BOARDDIR . '/packages/temp');
				Errors::instance()->fatal_lang_error('package_cant_uninstall', false);
			}

			$actions = parsePackageInfo($packageInfo['xml'], $testing, 'uninstall');

			// Gadzooks!  There's no uninstaller at all!?
			if (empty($actions))
			{
				deltree(BOARDDIR . '/packages/temp');
				Errors::instance()->fatal_lang_error('package_uninstall_cannot', false);
			}

			// Can't edit the custom themes it's edited if you're uninstalling, they must be removed.
			$context['themes_locked'] = true;

			// Only let them uninstall themes it was installed into.
			foreach ($this->theme_paths as $id => $data)
			{
				if ($id != 1 && !in_array($id, $package_installed['old_themes']))
					unset($this->theme_paths[$id]);
			}
		}
		// Or is it already installed and you want to upgrade
		elseif (isset($package_installed['old_version']) && $package_installed['old_version'] != $packageInfo['version'])
		{
			// Look for an upgrade...
			$actions = parsePackageInfo($packageInfo['xml'], $testing, 'upgrade', $package_installed['old_version']);

			// There was no upgrade....
			if (empty($actions))
				$this->_is_installed = true;
			else
			{
				// Otherwise they can only upgrade themes from the first time around.
				foreach ($this->theme_paths as $id => $data)
				{
					if ($id != 1 && !in_array($id, $package_installed['old_themes']))
						unset($this->theme_paths[$id]);
				}
			}
		}
		// Simply already installed
		elseif (isset($package_installed['old_version']) && $package_installed['old_version'] == $packageInfo['version'])
			$this->_is_installed = true;

		if (!isset($package_installed['old_version']) || $this->_is_installed)
			$actions = parsePackageInfo($packageInfo['xml'], $testing, 'install');

		return $actions;
	}

	/**
	 * Creates the packages temp directory
	 *
	 * - First trys as 755, failing moves to 777
	 * - Will try with FTP permissions for cases where the web server credentials
	 * do not have create directory permissions
	 */
	private function _create_temp_dir()
	{
		global $context, $scripturl;

		// Make the temp directory
		if (!mktree(BOARDDIR . '/packages/temp', 0755))
		{
			// 755 did not work, try 777?
			deltree(BOARDDIR . '/packages/temp', false);
			if (!mktree(BOARDDIR . '/packages/temp', 0777))
			{
				// That did not work either, we need additional permissions
				deltree(BOARDDIR . '/packages/temp', false);
				create_chmod_control(array(BOARDDIR . '/packages/temp/delme.tmp'), array('destination_url' => $scripturl . '?action=admin;area=packages;sa=' . $this->_req->query->sa . ';package=' . $context['filename'], 'crash_on_error' => true));

				// No temp directory was able to be made, that's fatal
				deltree(BOARDDIR . '/packages/temp', false);
				if (!mktree(BOARDDIR . '/packages/temp', 0777))
					Errors::instance()->fatal_lang_error('package_cant_download', false);
			}
		}
	}

	/**
	 * Actually installs a package
	 */
	public function action_install2()
	{
		global $txt, $context, $scripturl, $modSettings;

		// Make sure we don't install this addon twice.
		checkSubmitOnce('check');
		checkSession();

		// If there's no package file, what are we installing?
		$this->_filename = $this->_req->getQuery('package', 'trim');
		if (empty($this->_filename))
			redirectexit('action=admin;area=packages');

		// And if the file does not exist there is a problem
		if (!file_exists(BOARDDIR . '/packages/' . $this->_filename))
			Errors::instance()->fatal_lang_error('package_no_file', false);

		// If this is an uninstall, we'll have an id.
		$this->install_id = $this->_req->getQuery('pid', 'intval', 0);

		// Installing in themes will require some help
		require_once(SUBSDIR . '/Themes.subs.php');

		// @todo Perhaps do it in steps, if necessary?
		$this->_uninstalling = $this->_req->query->sa === 'uninstall2';

		// Load up the package FTP information?
		create_chmod_control(array(), array('destination_url' => $scripturl . '?action=admin;area=packages;sa=' . $this->_req->query->sa . ';package=' . $this->_req->query->package));

		// Make sure temp directory exists and is empty!
		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp', false);
		else
			$this->_create_temp_dir();

		// Let the unpacker do the work.
		$this->_extract_files_temp();

		// Are we installing this into any custom themes?
		$custom_themes = array(1);
		$known_themes = explode(',', $modSettings['knownThemes']);
		if (!empty($this->_req->post->custom_theme))
		{
			foreach ($this->_req->post->custom_theme as $tid)
				if (in_array($tid, $known_themes))
					$custom_themes[] = (int) $tid;
		}

		// Now load up the paths of the themes that we need to know about.
		$this->theme_paths = getThemesPathbyID($custom_themes);
		$themes_installed = array(1);

		// Are there any theme copying that we want to take place?
		$context['theme_copies'] = array(
			'require-file' => array(),
			'require-dir' => array(),
		);

		if (!empty($this->_req->post->theme_changes))
		{
			foreach ($this->_req->post->theme_changes as $change)
			{
				if (empty($change))
					continue;

				$theme_data = json_decode(base64_decode($change), true);
				if (empty($theme_data['type']))
					continue;

				$themes_installed[] = $theme_data['id'];
				$context['theme_copies'][$theme_data['type']][$theme_data['orig']][] = $theme_data['future'];
			}
		}

		// Get the package info...
		$packageInfo = getPackageInfo($this->_filename);
		if (!is_array($packageInfo))
			Errors::instance()->fatal_lang_error($packageInfo);

		$packageInfo['filename'] = $this->_filename;

		// Create a backup file to roll back to! (but if they do this more than once, don't run it a zillion times.)
		if (!empty($modSettings['package_make_full_backups']) && (!isset($_SESSION['last_backup_for']) || $_SESSION['last_backup_for'] != $this->_filename . ($this->_uninstalling ? '$$' : '$')))
		{
			$_SESSION['last_backup_for'] = $this->_filename . ($this->_uninstalling ? '$$' : '$');

			// @todo Internationalize this?
			package_create_backup(($this->_uninstalling ? 'backup_' : 'before_') . strtok($this->_filename, '.'));
		}

		// The addon isn't installed.... unless proven otherwise.
		$this->_is_installed = false;

		// Is it actually installed?
		$package_installed = isPackageInstalled($packageInfo['id'], $this->install_id);

		// Fetch the install status and action log
		$install_log = $this->_get_package_actions($package_installed, $packageInfo, false);

		// Set up the details for the sub template, linktree, etc
		$context['linktree'][count($context['linktree']) - 1] = array(
			'url' => $scripturl . '?action=admin;area=packages;sa=browse',
			'name' => $this->_uninstalling ? $txt['uninstall'] : $txt['extracting']
		);
		$context['page_title'] .= ' - ' . ($this->_uninstalling ? $txt['uninstall'] : $txt['extracting']);
		$context['sub_template'] = 'extract_package';
		$context['filename'] = $this->_filename;
		$context['install_finished'] = false;
		$context['is_installed'] = $this->_is_installed;
		$context['uninstalling'] = $this->_uninstalling;
		$context['extract_type'] = isset($packageInfo['type']) ? $packageInfo['type'] : 'modification';

		// We're gonna be needing the table db functions! ...Sometimes.
		$table_installer = db_table();

		// @todo Make a log of any errors that occurred and output them?
		if (!empty($install_log))
		{
			// @todo Make a log of any errors that occurred and output them?
			$pka = new Package_Actions();
			$pka->install_init($install_log, $this->_uninstalling, $this->_base_path, $this->theme_paths, $themes_installed);
			$failed_steps = $pka->failed_steps;
			$themes_installed = $pka->themes_installed;

			package_flush_cache();

			// First, ensure this change doesn't get removed by putting a stake in the ground (So to speak).
			package_put_contents(BOARDDIR . '/packages/installed.list', time());

			// See if this is already installed
			$is_upgrade = false;
			$old_db_changes = array();
			$package_check = isPackageInstalled($packageInfo['id']);

			// Change the installed state as required.
			if (!empty($package_check['install_state']))
			{
				if ($this->_uninstalling)
					setPackageState($package_check['package_id'], $this->install_id);
				else
				{
					// not uninstalling so must be an upgrade
					$is_upgrade = true;
					$old_db_changes = empty($package_check['db_changes']) ? array() : $package_check['db_changes'];
				}
			}

			// Assuming we're not uninstalling, add the entry.
			if (!$this->_uninstalling)
			{
				// Any db changes from older version?
				$table_log = $table_installer->package_log();

				if (!empty($old_db_changes))
					$db_package_log = empty($table_log) ? $old_db_changes : array_merge($old_db_changes, $table_log);
				else
					$db_package_log = $table_log;

				// If there are some database changes we might want to remove then filter them out.
				if (!empty($db_package_log))
				{
					// We're really just checking for entries which are create table AND add columns (etc).
					$tables = array();
					usort($db_package_log, array($this, '_sort_table_first'));
					foreach ($db_package_log as $k => $log)
					{
						if ($log[0] == 'remove_table')
							$tables[] = $log[1];
						elseif (in_array($log[1], $tables))
							unset($db_package_log[$k]);
					}

					$package_installed['db_changes'] = serialize($db_package_log);
				}
				else
					$package_installed['db_changes'] = '';

				// What themes did we actually install?
				$themes_installed = array_unique($themes_installed);
				$themes_installed = implode(',', $themes_installed);

				// What failed steps?
				$failed_step_insert = serialize($failed_steps);

				// Credits tag?
				$credits_tag = (empty($pka->credits_tag)) ? '' : serialize($pka->credits_tag);

				// Add to the log packages
				addPackageLog($packageInfo, $failed_step_insert, $themes_installed, $package_installed['db_changes'], $is_upgrade, $credits_tag);
			}

			$context['install_finished'] = true;
		}

		// If there's database changes - and they want them removed - let's do it last!
		if (!empty($package_installed['db_changes']) && !empty($this->_req->post->do_db_changes))
		{
			foreach ($package_installed['db_changes'] as $change)
			{
				if ($change[0] == 'remove_table' && isset($change[1]))
					$table_installer->db_drop_table($change[1]);
				elseif ($change[0] == 'remove_column' && isset($change[2]))
					$table_installer->db_remove_column($change[1], $change[2]);
				elseif ($change[0] == 'remove_index' && isset($change[2]))
					$table_installer->db_remove_index($change[1], $change[2]);
			}
		}

		// Clean house... get rid of the evidence ;).
		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp');

		// Log what we just did.
		logAction($this->_uninstalling ? 'uninstall_package' : (!empty($is_upgrade) ? 'upgrade_package' : 'install_package'), array('package' => Util::htmlspecialchars($packageInfo['name']), 'version' => Util::htmlspecialchars($packageInfo['version'])), 'admin');

		// Just in case, let's clear the whole cache to avoid anything going up the swanny.
		clean_cache();

		// Restore file permissions?
		create_chmod_control(array(), array(), true);
	}

	/**
	 * Table sorting function used in usort
	 *
	 * @param string[] $a
	 * @param string[] $b
	 */
	private function _sort_table_first($a, $b)
	{
		if ($a[0] == $b[0])
			return 0;

		return $a[0] == 'remove_table' ? -1 : 1;
	}

	/**
	 * List the files in a package.
	 */
	public function action_list()
	{
		global $txt, $scripturl, $context;

		// No package?  Show him or her the door.
		if (!isset($this->_req->query->package) || $this->_req->query->package == '')
			redirectexit('action=admin;area=packages');

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=packages;sa=list;package=' . $this->_req->query->package,
			'name' => $txt['list_file']
		);
		$context['page_title'] .= ' - ' . $txt['list_file'];
		$context['sub_template'] = 'list';

		// The filename...
		$context['filename'] = $this->_req->query->package;

		// Let the unpacker do the work.
		if (is_file(BOARDDIR . '/packages/' . $context['filename']))
			$context['files'] = read_tgz_file(BOARDDIR . '/packages/' . $context['filename'], null);
		elseif (is_dir(BOARDDIR . '/packages/' . $context['filename']))
			$context['files'] = listtree(BOARDDIR . '/packages/' . $context['filename']);
	}

	/**
	 * Display one of the files in a package.
	 */
	public function action_examine()
	{
		global $txt, $scripturl, $context;

		// No package?  Show him or her the door.
		if (!isset($this->_req->query->package) || $this->_req->query->package == '')
			redirectexit('action=admin;area=packages');

		// No file?  Show him or her the door.
		if (!isset($this->_req->query->file) || $this->_req->query->file == '')
			redirectexit('action=admin;area=packages');

		$this->_req->query->package = preg_replace('~[\.]+~', '.', strtr($this->_req->query->package, array('/' => '_', '\\' => '_')));
		$this->_req->query->file = preg_replace('~[\.]+~', '.', $this->_req->query->file);

		if (isset($this->_req->query->raw))
		{
			if (is_file(BOARDDIR . '/packages/' . $this->_req->query->package))
				echo read_tgz_file(BOARDDIR . '/packages/' . $this->_req->query->package, $this->_req->query->file, true);
			elseif (is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
				echo file_get_contents(BOARDDIR . '/packages/' . $this->_req->query->package . '/' . $this->_req->query->file);

			obExit(false);
		}

		$context['linktree'][count($context['linktree']) - 1] = array(
			'url' => $scripturl . '?action=admin;area=packages;sa=list;package=' . $this->_req->query->package,
			'name' => $txt['package_examine_file']
		);
		$context['page_title'] .= ' - ' . $txt['package_examine_file'];
		$context['sub_template'] = 'examine';

		// The filename...
		$context['package'] = $this->_req->query->package;
		$context['filename'] = $this->_req->query->file;

		// Let the unpacker do the work.... but make sure we handle images properly.
		if (in_array(strtolower(strrchr($this->_req->query->file, '.')), array('.bmp', '.gif', '.jpeg', '.jpg', '.png')))
			$context['filedata'] = '<img src="' . $scripturl . '?action=admin;area=packages;sa=examine;package=' . $this->_req->query->package . ';file=' . $this->_req->query->file . ';raw" alt="' . $this->_req->query->file . '" />';
		else
		{
			if (is_file(BOARDDIR . '/packages/' . $this->_req->query->package))
				$context['filedata'] = htmlspecialchars(read_tgz_file(BOARDDIR . '/packages/' . $this->_req->query->package, $this->_req->query->file, true));
			elseif (is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
				$context['filedata'] = htmlspecialchars(file_get_contents(BOARDDIR . '/packages/' . $this->_req->query->package . '/' . $this->_req->query->file));

			if (strtolower(strrchr($this->_req->query->file, '.')) == '.php')
				$context['filedata'] = highlight_php_code($context['filedata']);
		}
	}

	/**
	 * Empty out the installed list.
	 */
	public function action_flush()
	{
		// Always check the session.
		checkSession('get');

		include_once(SUBSDIR . '/Package.subs.php');

		// Record when we last did this.
		package_put_contents(BOARDDIR . '/packages/installed.list', time());

		// Set everything as uninstalled.
		setPackagesAsUninstalled();

		redirectexit('action=admin;area=packages;sa=installed');
	}

	/**
	 * Delete a package.
	 */
	public function action_remove()
	{
		global $scripturl;

		// Check it.
		checkSession('get');

		// Ack, don't allow deletion of arbitrary files here, could become a security hole somehow!
		if (!isset($this->_req->query->package) || $this->_req->query->package == 'index.php' || $this->_req->query->package == 'installed.list' || $this->_req->query->package == 'backups')
			redirectexit('action=admin;area=packages;sa=browse');
		$this->_req->query->package = preg_replace('~[\.]+~', '.', strtr($this->_req->query->package, array('/' => '_', '\\' => '_')));

		// Can't delete what's not there.
		if (file_exists(BOARDDIR . '/packages/' . $this->_req->query->package)
			&& (substr($this->_req->query->package, -4) == '.zip' || substr($this->_req->query->package, -4) == '.tgz' || substr($this->_req->query->package, -7) == '.tar.gz' || is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
			&& $this->_req->query->package != 'backups' && substr($this->_req->query->package, 0, 1) != '.')
		{
			create_chmod_control(array(BOARDDIR . '/packages/' . $this->_req->query->package), array('destination_url' => $scripturl . '?action=admin;area=packages;sa=remove;package=' . $this->_req->query->package, 'crash_on_error' => true));

			if (is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
				deltree(BOARDDIR . '/packages/' . $this->_req->query->package);
			else
			{
				@chmod(BOARDDIR . '/packages/' . $this->_req->query->package, 0777);
				unlink(BOARDDIR . '/packages/' . $this->_req->query->package);
			}
		}

		redirectexit('action=admin;area=packages;sa=browse');
	}

	/**
	 * Browse a list of installed packages.
	 */
	public function action_browse()
	{
		global $txt, $scripturl, $context, $settings;

		$context['page_title'] .= ' - ' . $txt['browse_packages'];
		$context['forum_version'] = FORUM_VERSION;
		$installed = $context['sub_action'] == 'installed' ? true : false;
		$context['package_types'] = $installed ? array('modification') : array('modification', 'avatar', 'language', 'smiley', 'unknown');

		foreach ($context['package_types'] as $type)
		{
			// Use the standard templates for showing this.
			$listOptions = array(
				'id' => 'packages_lists_' . $type,
				'title' => $installed ? $txt['view_and_remove'] : $txt[$type . '_package'],
				'no_items_label' => $txt['no_packages'],
				'get_items' => array(
					'function' => array($this, 'list_packages'),
					'params' => array('type' => $type, 'installed' => $installed),
				),
				'base_href' => $scripturl . '?action=admin;area=packages;sa=' . $context['sub_action'] . ';type=' . $type,
				'default_sort_col' => 'mod_name' . $type,
				'columns' => array(
					'mod_name' . $type => array(
						'header' => array(
							'value' => $txt['mod_name'],
							'style' => 'width: 25%;',
						),
						'data' => array(
							'function' => function ($package_md5) use ($type)  {
								global $context;

								if (isset($context['available_' . $type . ''][$package_md5]))
									return $context['available_' . $type . ''][$package_md5]['name'];

								return '';
							},
						),
						'sort' => array(
							'default' => 'name',
							'reverse' => 'name',
						),
					),
					'version' . $type => array(
						'header' => array(
							'value' => $txt['mod_version'],
							'style' => 'width: 25%;',
						),
						'data' => array(
							'function' => function ($package_md5) use ($type)  {
								global $context;

								if (isset($context['available_' . $type . ''][$package_md5]))
									return $context['available_' . $type . ''][$package_md5]['version'];

								return '';
							},
						),
						'sort' => array(
							'default' => 'version',
							'reverse' => 'version',
						),
					),
					'operations' . $type => array(
						'header' => array(
							'value' => '',
						),
						'data' => array(
							'function' => function ($package_md5) use ($type) {
								global $context, $scripturl, $txt;

								if (!isset($context['available_' . $type . ''][$package_md5]))
									return '';

								// Rewrite shortcut
								$package = $context['available_' . $type . ''][$package_md5];
								$return = '';

								if ($package['can_uninstall'])
									$return = '
										<a href="' . $scripturl . '?action=admin;area=packages;sa=uninstall;package=' . $package['filename'] . ';pid=' . $package['installed_id'] . '">[ ' . $txt['uninstall'] . ' ]</a>';
								elseif ($package['can_emulate_uninstall'])
									$return = '
										<a href="' . $scripturl . '?action=admin;area=packages;sa=uninstall;ve=' . $package['can_emulate_uninstall'] . ';package=' . $package['filename'] . ';pid=' . $package['installed_id'] . '">[ ' . $txt['package_emulate_uninstall'] . ' ' . $package['can_emulate_uninstall'] . ' ]</a>';
								elseif ($package['can_upgrade'])
									$return = '
										<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $package['filename'] . '">[ ' . $txt['package_upgrade'] . ' ]</a>';
								elseif ($package['can_install'])
									$return = '
										<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $package['filename'] . '">[ ' . $txt['install_mod'] . ' ]</a>';
								elseif ($package['can_emulate_install'])
									$return = '
										<a href="' . $scripturl . '?action=admin;area=packages;sa=install;ve=' . $package['can_emulate_install'] . ';package=' . $package['filename'] . '">[ ' . $txt['package_emulate_install'] . ' ' . $package['can_emulate_install'] . ' ]</a>';

								return $return . '
										<a href="' . $scripturl . '?action=admin;area=packages;sa=list;package=' . $package['filename'] . '">[ ' . $txt['list_files'] . ' ]</a>
										<a href="' . $scripturl . '?action=admin;area=packages;sa=remove;package=' . $package['filename'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"' . ($package['is_installed'] && $package['is_current']
											? ' onclick="return confirm(\'' . $txt['package_delete_bad'] . '\');"'
											: '') . '>[ ' . $txt['package_delete'] . ' ]</a>';
							},
							'class' => 'righttext',
						),
					),
				),
				'additional_rows' => array(
					array(
						'position' => 'bottom_of_list',
						'class' => 'submitbutton',
						'value' => ($context['sub_action'] == 'browse'
							? '<div class="smalltext">' . $txt['package_installed_key'] . '<i class="icon icon-small i-green-dot"></i>' . $txt['package_installed_current'] . '<i class="icon icon-small i-red-dot"></i>' . $txt['package_installed_old'] . '</div>'
							: '<a class="linkbutton" href="' . $scripturl . '?action=admin;area=packages;sa=flush;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['package_delete_list_warning'] . '\');">' . $txt['delete_list'] . '</a>'),
					),
				),
			);

			createList($listOptions);
		}

		$context['sub_template'] = 'browse';
		$context['default_list'] = 'packages_lists';
	}

	/**
	 * Test an FTP connection.
	 *
	 * @uses Xml Template, generic_xml sub template
	 */
	public function action_ftptest()
	{
		global $context, $txt, $package_ftp;

		checkSession('get');

		// Try to make the FTP connection.
		create_chmod_control(array(), array('force_find_error' => true));

		// Deal with the template stuff.
		loadTemplate('Xml');
		$context['sub_template'] = 'generic_xml';
		Template_Layers::getInstance()->removeAll();

		// Define the return data, this is simple.
		$context['xml_data'] = array(
			'results' => array(
				'identifier' => 'result',
				'children' => array(
					array(
						'attributes' => array(
							'success' => !empty($package_ftp) ? 1 : 0,
						),
						'value' => !empty($package_ftp) ? $txt['package_ftp_test_success'] : (isset($context['package_ftp'], $context['package_ftp']['error']) ? $context['package_ftp']['error'] : $txt['package_ftp_test_failed']),
					),
				),
			),
		);
	}

	/**
	 * Used when a temp FTP access is needed to package functions
	 */
	public function action_options()
	{
		global $txt, $context, $modSettings;

		if (isset($this->_req->post->save))
		{
			checkSession('post');

			updateSettings(array(
				'package_server' => $this->_req->getPost('pack_server', 'trim|Util::htmlspecialchars'),
				'package_port' => $this->_req->getPost('pack_port', 'trim|Util::htmlspecialchars'),
				'package_username' => $this->_req->getPost('pack_user', 'trim|Util::htmlspecialchars'),
				'package_make_backups' => !empty($this->_req->post->package_make_backups),
				'package_make_full_backups' => !empty($this->_req->post->package_make_full_backups)
			));

			redirectexit('action=admin;area=packages;sa=options');
		}

		if (preg_match('~^/home\d*/([^/]+?)/public_html~', $this->_req->server->DOCUMENT_ROOT, $match))
			$default_username = $match[1];
		else
			$default_username = '';

		$context['page_title'] = $txt['package_settings'];
		$context['sub_template'] = 'install_options';
		$context['package_ftp_server'] = isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost';
		$context['package_ftp_port'] = isset($modSettings['package_port']) ? $modSettings['package_port'] : '21';
		$context['package_ftp_username'] = isset($modSettings['package_username']) ? $modSettings['package_username'] : $default_username;
		$context['package_make_backups'] = !empty($modSettings['package_make_backups']);
		$context['package_make_full_backups'] = !empty($modSettings['package_make_full_backups']);
	}

	/**
	 * List operations
	 */
	public function action_showoperations()
	{
		global $context, $txt;

		// Can't be in here buddy.
		isAllowedTo('admin_forum');

		// We need to know the operation key for the search and replace?
		if (!isset($this->_req->query->operation_key, $this->_req->query->filename) && !is_numeric($this->_req->query->operation_key))
			Errors::instance()->fatal_lang_error('operation_invalid', 'general');

		// Load the required file.
		require_once(SUBSDIR . '/Themes.subs.php');

		// Uninstalling the mod?
		$reverse = isset($this->_req->query->reverse) ? true : false;

		// Get the base name.
		$context['filename'] = preg_replace('~[\.]+~', '.', $this->_req->query->package);

		// We need to extract this again.
		if (is_file(BOARDDIR . '/packages/' . $context['filename']))
		{
			$context['extracted_files'] = read_tgz_file(BOARDDIR . '/packages/' . $context['filename'], BOARDDIR . '/packages/temp');
			if ($context['extracted_files'] && !file_exists(BOARDDIR . '/packages/temp/package-info.xml'))
			{
				foreach ($context['extracted_files'] as $file)
					if (basename($file['filename']) === 'package-info.xml')
					{
						$context['base_path'] = dirname($file['filename']) . '/';
						break;
					}
			}

			if (!isset($context['base_path']))
				$context['base_path'] = '';
		}
		elseif (is_dir(BOARDDIR . '/packages/' . $context['filename']))
		{
			copytree(BOARDDIR . '/packages/' . $context['filename'], BOARDDIR . '/packages/temp');
			$context['extracted_files'] = listtree(BOARDDIR . '/packages/temp');
			$context['base_path'] = '';
		}

		// Load up any custom themes we may want to install into...
		$theme_paths = getThemesPathbyID();

		// For uninstall operations we only consider the themes in which the package is installed.
		if ($reverse && !empty($this->_req->query->install_id))
		{
			$install_id = (int) $this->_req->query->install_id;
			if ($install_id > 0)
			{
				$old_themes = loadThemesAffected($install_id);
				foreach ($theme_paths as $id => $data)
				{
					if ($id != 1 && !in_array($id, $old_themes))
						unset($theme_paths[$id]);
				}
			}
		}

		$mod_actions = parseModification(@file_get_contents(BOARDDIR . '/packages/temp/' . $context['base_path'] . $this->_req->query->filename), true, $reverse, $theme_paths);

		// Ok lets get the content of the file.
		$context['operations'] = array(
			'search' => strtr(htmlspecialchars($mod_actions[$this->_req->query->operation_key]['search_original'], ENT_COMPAT, 'UTF-8'), array('[' => '&#91;', ']' => '&#93;')),
			'replace' => strtr(htmlspecialchars($mod_actions[$this->_req->query->operation_key]['replace_original'], ENT_COMPAT, 'UTF-8'), array('[' => '&#91;', ']' => '&#93;')),
			'position' => $mod_actions[$this->_req->query->operation_key]['position'],
		);

		// Let's do some formatting...
		$operation_text = $context['operations']['position'] == 'replace' ? 'operation_replace' : ($context['operations']['position'] == 'before' ? 'operation_after' : 'operation_before');
		$bbc_parser = \BBC\ParserWrapper::getInstance();
		$context['operations']['search'] = $bbc_parser->parsePackage('[code=' . $txt['operation_find'] . ']' . ($context['operations']['position'] == 'end' ? '?&gt;' : $context['operations']['search']) . '[/code]');
		$context['operations']['replace'] = $bbc_parser->parsePackage('[code=' . $txt[$operation_text] . ']' . $context['operations']['replace'] . '[/code]');

		// No layers
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'view_operations';
	}

	/**
	 * Allow the admin to reset permissions on files.
	 */
	public function action_perms()
	{
		global $context, $txt, $modSettings, $package_ftp;

		// Let's try and be good, yes?
		checkSession('get');

		// If we're restoring permissions this is just a pass through really.
		if (isset($this->_req->query->restore))
		{
			create_chmod_control(array(), array(), true);
			Errors::instance()->fatal_lang_error('no_access', false);
		}

		// This is a time and memory eating ...
		detectServer()->setMemoryLimit('128M');
		detectServer()->setTimeLimit(600);

		// Load up some FTP stuff.
		create_chmod_control();

		if (empty($package_ftp) && !isset($this->_req->post->skip_ftp))
		{
			$ftp = new Ftp_Connection(null);
			list ($username, $detect_path, $found_path) = $ftp->detect_path(BOARDDIR);

			$context['package_ftp'] = array(
				'server' => isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost',
				'port' => isset($modSettings['package_port']) ? $modSettings['package_port'] : '21',
				'username' => empty($username) ? (isset($modSettings['package_username']) ? $modSettings['package_username'] : '') : $username,
				'path' => $detect_path,
				'form_elements_only' => true,
			);
		}
		else
			$context['ftp_connected'] = true;

		// Define the template.
		$context['page_title'] = $txt['package_file_perms'];
		$context['sub_template'] = 'file_permissions';

		// Define what files we're interested in, as a tree.
		$context['file_tree'] = array(
			strtr(BOARDDIR, array('\\' => '/')) => array(
				'type' => 'dir',
				'contents' => array(
					'agreement.txt' => array(
						'type' => 'file',
						'writable_on' => 'standard',
					),
					'Settings.php' => array(
						'type' => 'file',
						'writable_on' => 'restrictive',
					),
					'Settings_bak.php' => array(
						'type' => 'file',
						'writable_on' => 'restrictive',
					),
					'attachments' => array(
						'type' => 'dir',
						'writable_on' => 'restrictive',
					),
					'avatars' => array(
						'type' => 'dir',
						'writable_on' => 'standard',
					),
					'cache' => array(
						'type' => 'dir',
						'writable_on' => 'restrictive',
					),
					'custom_avatar_dir' => array(
						'type' => 'dir',
						'writable_on' => 'restrictive',
					),
					'smileys' => array(
						'type' => 'dir_recursive',
						'writable_on' => 'standard',
					),
					'sources' => array(
						'type' => 'dir',
						'list_contents' => true,
						'writable_on' => 'standard',
					),
					'themes' => array(
						'type' => 'dir_recursive',
						'writable_on' => 'standard',
						'contents' => array(
							'default' => array(
								'type' => 'dir_recursive',
								'list_contents' => true,
								'contents' => array(
									'languages' => array(
										'type' => 'dir',
										'list_contents' => true,
									),
								),
							),
						),
					),
					'packages' => array(
						'type' => 'dir',
						'writable_on' => 'standard',
						'contents' => array(
							'temp' => array(
								'type' => 'dir',
							),
							'backup' => array(
								'type' => 'dir',
							),
							'installed.list' => array(
								'type' => 'file',
								'writable_on' => 'standard',
							),
						),
					),
				),
			),
		);

		// Directories that can move.
		if (substr(SOURCEDIR, 0, strlen(BOARDDIR)) != BOARDDIR)
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['sources']);
			$context['file_tree'][strtr(SOURCEDIR, array('\\' => '/'))] = array(
				'type' => 'dir',
				'list_contents' => true,
				'writable_on' => 'standard',
			);
		}

		// Moved the cache?
		if (substr(CACHEDIR, 0, strlen(BOARDDIR)) != BOARDDIR)
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['cache']);
			$context['file_tree'][strtr(CACHEDIR, array('\\' => '/'))] = array(
				'type' => 'dir',
				'list_contents' => false,
				'writable_on' => 'restrictive',
			);
		}

		// Are we using multiple attachment directories?
		if (!empty($modSettings['currentAttachmentUploadDir']))
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['attachments']);

			if (!is_array($modSettings['attachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = Util::unserialize($modSettings['attachmentUploadDir']);

			// @todo Should we suggest non-current directories be read only?
			foreach ($modSettings['attachmentUploadDir'] as $dir)
				$context['file_tree'][strtr($dir, array('\\' => '/'))] = array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				);
		}
		elseif (substr($modSettings['attachmentUploadDir'], 0, strlen(BOARDDIR)) != BOARDDIR)
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['attachments']);
			$context['file_tree'][strtr($modSettings['attachmentUploadDir'], array('\\' => '/'))] = array(
				'type' => 'dir',
				'writable_on' => 'restrictive',
			);
		}

		if (substr($modSettings['smileys_dir'], 0, strlen(BOARDDIR)) != BOARDDIR)
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['smileys']);
			$context['file_tree'][strtr($modSettings['smileys_dir'], array('\\' => '/'))] = array(
				'type' => 'dir_recursive',
				'writable_on' => 'standard',
			);
		}

		if (substr($modSettings['avatar_directory'], 0, strlen(BOARDDIR)) != BOARDDIR)
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['avatars']);
			$context['file_tree'][strtr($modSettings['avatar_directory'], array('\\' => '/'))] = array(
				'type' => 'dir',
				'writable_on' => 'standard',
			);
		}

		if (isset($modSettings['custom_avatar_dir']) && substr($modSettings['custom_avatar_dir'], 0, strlen(BOARDDIR)) != BOARDDIR)
		{
			unset($context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['custom_avatar_dir']);
			$context['file_tree'][strtr($modSettings['custom_avatar_dir'], array('\\' => '/'))] = array(
				'type' => 'dir',
				'writable_on' => 'restrictive',
			);
		}

		// Load up any custom themes.
		require_once(SUBSDIR . '/Themes.subs.php');
		$themes = getCustomThemes();
		foreach ($themes as $id => $theme)
		{
			// Skip the default
			if ($id == 1)
				continue;

			if (substr(strtolower(strtr($theme['theme_dir'], array('\\' => '/'))), 0, strlen(BOARDDIR) + 7) === strtolower(strtr(BOARDDIR, array('\\' => '/')) . '/themes'))
			{
				$context['file_tree'][strtr(BOARDDIR, array('\\' => '/'))]['contents']['themes']['contents'][substr($theme['theme_dir'], strlen(BOARDDIR) + 8)] = array(
					'type' => 'dir_recursive',
					'list_contents' => true,
					'contents' => array(
						'languages' => array(
							'type' => 'dir',
							'list_contents' => true,
						),
					),
				);
			}
			else
			{
				$context['file_tree'][strtr($theme['theme_dir'], array('\\' => '/'))] = array(
					'type' => 'dir_recursive',
					'list_contents' => true,
					'contents' => array(
						'languages' => array(
							'type' => 'dir',
							'list_contents' => true,
						),
					),
				);
			}
		}

		// If we're submitting then let's move on to another function to keep things cleaner..
		if (isset($this->_req->post->action_changes))
			return $this->action_perms_save();

		$context['look_for'] = array();

		// Are we looking for a particular tree - normally an expansion?
		if (!empty($this->_req->query->find))
			$context['look_for'][] = base64_decode($this->_req->query->find);

		// Only that tree?
		$context['only_find'] = isset($this->_req->query->xml) && !empty($this->_req->query->onlyfind) ? $this->_req->query->onlyfind : '';
		if ($context['only_find'])
			$context['look_for'][] = $context['only_find'];

		// Have we got a load of back-catalogue trees to expand from a submit etc?
		if (!empty($this->_req->query->back_look))
		{
			$potententialTrees = json_decode(base64_decode($this->_req->query->back_look), true);
			foreach ($potententialTrees as $tree)
				$context['look_for'][] = $tree;
		}

		// ... maybe posted?
		if (!empty($this->_req->post->back_look))
			$context['only_find'] = array_merge($context['only_find'], $this->_req->post->back_look);

		$context['back_look_data'] = base64_encode(json_encode(array_slice($context['look_for'], 0, 15)));

		// Are we finding more files than first thought?
		$context['file_offset'] = !empty($this->_req->query->fileoffset) ? (int) $this->_req->query->fileoffset : 0;

		// Don't list more than this many files in a directory.
		$context['file_limit'] = 150;

		// How many levels shall we show?
		$context['default_level'] = empty($context['only_find']) ? 2 : 25;

		// This will be used if we end up catching XML data.
		$context['xml_data'] = array(
			'roots' => array(
				'identifier' => 'root',
				'children' => array(
					array(
						'value' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
					),
				),
			),
			'folders' => array(
				'identifier' => 'folder',
				'children' => array(),
			),
		);

		foreach ($context['file_tree'] as $path => $data)
		{
			// Run this directory.
			if (file_exists($path) && (empty($context['only_find']) || substr($context['only_find'], 0, strlen($path)) == $path))
			{
				// Get the first level down only.
				fetchPerms__recursive($path, $context['file_tree'][$path], 1);
				$context['file_tree'][$path]['perms'] = array(
					'chmod' => @is_writable($path),
					'perms' => @fileperms($path),
				);
			}
			else
				unset($context['file_tree'][$path]);
		}

		// Is this actually xml?
		if (isset($this->_req->query->xml))
		{
			loadTemplate('Xml');
			$context['sub_template'] = 'generic_xml';
			Template_Layers::getInstance()->removeAll();
		}
	}

	/**
	 * Actually action the permission changes they want.
	 */
	public function action_perms_save()
	{
		global $context, $txt, $time_start, $package_ftp;

		umask(0);

		$timeout_limit = 5;
		$context['method'] = $this->_req->post->method === 'individual' ? 'individual' : 'predefined';
		$context['back_look_data'] = isset($this->_req->post->back_look) ? $this->_req->post->back_look : array();

		// Skipping use of FTP?
		if (empty($package_ftp))
			$context['skip_ftp'] = true;

		// We'll start off in a good place, security. Make sure that if we're dealing with individual files that they seem in the right place.
		if ($context['method'] === 'individual')
		{
			// Only these path roots are legal.
			$legal_roots = array_keys($context['file_tree']);
			$context['custom_value'] = (int) $this->_req->post->custom_value;

			// Continuing?
			if (isset($this->_req->post->toProcess))
				$this->_req->post->permStatus = json_decode(base64_decode($this->_req->post->toProcess), true);

			if (isset($this->_req->post->permStatus))
			{
				$context['to_process'] = array();
				$validate_custom = false;
				foreach ($this->_req->post->permStatus as $path => $status)
				{
					// Nothing to see here?
					if ($status === 'no_change')
						continue;

					$legal = false;
					foreach ($legal_roots as $root)
						if (substr($path, 0, strlen($root)) == $root)
							$legal = true;

					if (!$legal)
						continue;

					// Check it exists.
					if (!file_exists($path))
						continue;

					if ($status === 'custom')
						$validate_custom = true;

					// Now add it.
					$context['to_process'][$path] = $status;
				}
				$context['total_items'] = isset($this->_req->post->totalItems) ? (int) $this->_req->post->totalItems : count($context['to_process']);

				// Make sure the chmod status is valid?
				if ($validate_custom)
				{
					if (!preg_match('~^[4567][4567][4567]$~', $context['custom_value']))
						Errors::instance()->fatal_error($txt['chmod_value_invalid']);
				}

				// Nothing to do?
				if (empty($context['to_process']))
					redirectexit('action=admin;area=packages;sa=perms' . (!empty($context['back_look_data']) ? ';back_look=' . base64_encode(json_encode($context['back_look_data'])) : '') . ';' . $context['session_var'] . '=' . $context['session_id']);
			}
			// Should never get here,
			else
				Errors::instance()->fatal_lang_error('no_access', false);

			// Setup the custom value.
			$custom_value = octdec('0' . $context['custom_value']);

			// Start processing items.
			foreach ($context['to_process'] as $path => $status)
			{
				if (in_array($status, array('execute', 'writable', 'read')))
					package_chmod($path, $status);
				elseif ($status == 'custom' && !empty($custom_value))
				{
					// Use FTP if we have it.
					if (!empty($package_ftp) && !empty($_SESSION['pack_ftp']))
					{
						$ftp_file = strtr($path, array($_SESSION['pack_ftp']['root'] => ''));
						$package_ftp->chmod($ftp_file, $custom_value);
					}
					else
						@chmod($path, $custom_value);
				}

				// This fish is fried...
				unset($context['to_process'][$path]);

				// See if we're out of time?
				if (time() - array_sum(explode(' ', $time_start)) > $timeout_limit)
					pausePermsSave();
			}
		}
		// If predefined this is a little different.
		else
		{
			$context['predefined_type'] = $this->_req->getPost('predefined', 'trim|strval', 'restricted');
			$context['total_items'] = $this->_req->getPost('totalItems', 'intval', 0);
			$context['directory_list'] = isset($this->_req->post->dirList) ? json_decode(base64_decode($this->_req->post->dirList), true) : array();
			$context['file_offset'] = $this->_req->getPost('fileOffset', 'intval', 0);

			// Haven't counted the items yet?
			if (empty($context['total_items']))
			{
				foreach ($context['file_tree'] as $path => $data)
				{
					if (is_dir($path))
					{
						$context['directory_list'][$path] = 1;
						$context['total_items'] += $this->count_directories__recursive($path);
						$context['total_items']++;
					}
				}
			}

			// Have we built up our list of special files?
			if (!isset($this->_req->post->specialFiles) && $context['predefined_type'] != 'free')
			{
				$context['special_files'] = array();

				foreach ($context['file_tree'] as $path => $data)
					$this->build_special_files__recursive($path, $data);
			}
			// Free doesn't need special files.
			elseif ($context['predefined_type'] === 'free')
				$context['special_files'] = array();
			else
				$context['special_files'] = json_decode(base64_decode($this->_req->post->specialFiles), true);

			// Now we definitely know where we are, we need to go through again doing the chmod!
			foreach ($context['directory_list'] as $path => $dummy)
			{
				// Do the contents of the directory first.
				try
				{
					$file_count = 0;
					$dont_chmod = false;

					$entrys = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
					foreach ($entrys as $entry)
					{
						$file_count++;

						// Actually process this file?
						if (!$dont_chmod && !$entry->isDir() && (empty($context['file_offset']) || $context['file_offset'] < $file_count))
						{
							$status = $context['predefined_type'] === 'free' || isset($context['special_files'][$entry->getPathname()]) ? 'writable' : 'execute';
							package_chmod($entry->getPathname(), $status);
						}

						// See if we're out of time?
						if (!$dont_chmod && time() - array_sum(explode(' ', $time_start)) > $timeout_limit)
						{
							$dont_chmod = true;

							// Make note of how far we have come so we restart at the right point
							$context['file_offset'] = isset($file_count) ? $file_count : 0;
							break;
						}
					}
				}
				catch (UnexpectedValueException $e)
				{
					// @todo for now do nothing...
				}

				// If this is set it means we timed out half way through.
				if (!empty($dont_chmod))
				{
					$context['total_files'] = isset($file_count) ? $file_count : 0;
					pausePermsSave();
				}

				// Do the actual directory.
				$status = $context['predefined_type'] === 'free' || isset($context['special_files'][$path]) ? 'writable' : 'execute';
				package_chmod($path, $status);

				// We've finished the directory so no file offset, and no record.
				$context['file_offset'] = 0;
				unset($context['directory_list'][$path]);

				// See if we're out of time?
				if (time() - array_sum(explode(' ', $time_start)) > $timeout_limit)
					pausePermsSave();
			}
		}

		// If we're here we are done!
		redirectexit('action=admin;area=packages;sa=perms' . (!empty($context['back_look_data']) ? ';back_look=' . base64_encode(json_decode($context['back_look_data'], true)) : '') . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Builds a list of special files recursively for a given path
	 *
	 * @param string $path
	 * @param mixed[] $data
	 */
	public function build_special_files__recursive($path, &$data)
	{
		global $context;

		if (!empty($data['writable_on']))
			if ($context['predefined_type'] === 'standard' || $data['writable_on'] === 'restrictive')
				$context['special_files'][$path] = 1;

		if (!empty($data['contents']))
			foreach ($data['contents'] as $name => $contents)
				$this->build_special_files__recursive($path . '/' . $name, $contents);
	}

	/**
	 * Recursive counts all the directory's under a given path
	 *
	 * @param string $dir
	 */
	public function count_directories__recursive($dir)
	{
		global $context;

		$count = 0;

		try
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ($iterator as $path => $file)
			{
				if ($file->isDir())
				{
					$context['directory_list'][$path] = 1;
					$count++;
				}
			}
		}
		catch (UnexpectedValueException $e)
		{
			// @todo
		}

		return $count;
	}

	/**
	 * Get a listing of all the packages
	 *
	 * - Determines if the package is a mod, avatar, language package
	 * - Determines if the package has been installed or not
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page  The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $params 'type' type of package
	 * @param bool $installed
	 */
	public function list_packages($start, $items_per_page, $sort, $params, $installed)
	{
		global $scripturl, $context;
		static $instadds, $packages;

		// Start things up
		if (!isset($packages[$params]))
			$packages[$params] = array();

		// We need the packages directory to be writable for this.
		if (!@is_writable(BOARDDIR . '/packages'))
			create_chmod_control(array(BOARDDIR . '/packages'), array('destination_url' => $scripturl . '?action=admin;area=packages', 'crash_on_error' => true));

		list ($the_brand, $the_version) = explode(' ', FORUM_VERSION, 2);

		// Here we have a little code to help those who class themselves as something of gods, version emulation ;)
		if (isset($this->_req->query->version_emulate) && strtr($this->_req->query->version_emulate, array($the_brand => '')) == $the_version)
			unset($_SESSION['version_emulate']);
		elseif (isset($this->_req->query->version_emulate))
		{
			if (($this->_req->query->version_emulate === 0 || $this->_req->query->version_emulate === FORUM_VERSION) && isset($this->_req->session->version_emulate))
				unset($_SESSION['version_emulate']);
			elseif ($this->_req->query->version_emulate !== 0)
				$_SESSION['version_emulate'] = strtr($this->_req->query->version_emulate, array('-' => ' ', '+' => ' ', $the_brand . ' ' => ''));
		}

		if (!empty($_SESSION['version_emulate']))
		{
			$context['forum_version'] = $the_brand . ' ' . $_SESSION['version_emulate'];
			$the_version = $_SESSION['version_emulate'];
		}

		if (isset($_SESSION['single_version_emulate']))
			unset($_SESSION['single_version_emulate']);

		if (empty($instadds))
		{
			$instadds = loadInstalledPackages();
			$installed_adds = array();

			// Look through the list of installed mods...
			foreach ($instadds as $installed_add)
				$installed_adds[$installed_add['package_id']] = array(
					'id' => $installed_add['id'],
					'version' => $installed_add['version'],
				);

			// Get a list of all the ids installed, so the latest packages won't include already installed ones.
			$context['installed_adds'] = array_keys($installed_adds);
		}

		if ($installed)
		{
			$sort_id = 1;
			foreach ($instadds as $installed_add)
			{
				$context['available_modification'][$installed_add['package_id']] = array(
					'sort_id' => $sort_id++,
					'can_uninstall' => true,
					'name' => $installed_add['name'],
					'filename' => $installed_add['filename'],
					'installed_id' => $installed_add['id'],
					'version' => $installed_add['version'],
					'is_installed' => true,
					'is_current' => true,
				);
			}
		}

		if (empty($packages))
			foreach ($context['package_types'] as $type)
				$packages[$type] = array();

		try
		{
			$dir = new FilesystemIterator(BOARDDIR . '/packages', FilesystemIterator::SKIP_DOTS);

			$dirs = array();
			$sort_id = array(
				'mod' => 1,
				'modification' => 1,
				'addon' => 1,
				'avatar' => 1,
				'language' => 1,
				'smiley' => 1,
				'unknown' => 1,
			);
			foreach ($dir as $package)
			{
				if ($package->getFilename() == 'temp'
					|| (!($package->isDir() && file_exists($package->getPathname() . '/package-info.xml'))
						&& substr(strtolower($package->getFilename()), -7) !== '.tar.gz'
						&& strtolower($package->getExtension()) !== 'tgz'
						&& strtolower($package->getExtension()) !== 'zip'))
					continue;

				foreach ($context['package_types'] as $type)
					if (isset($context['available_' . $type][md5($package->getFilename())]))
						continue 2;

				// Skip directories or files that are named the same.
				if ($package->isDir())
				{
					if (in_array($package, $dirs))
						continue;
					$dirs[] = $package;
				}
				elseif (substr(strtolower($package->getFilename()), -7) === '.tar.gz')
				{
					if (in_array(substr($package, 0, -7), $dirs))
						continue;
					$dirs[] = substr($package, 0, -7);
				}
				elseif (strtolower($package->getExtension()) === 'zip' || strtolower($package->getExtension()) === 'tgz')
				{
					if (in_array(substr($package->getBasename(), 0, -4), $dirs))
						continue;
					$dirs[] = substr($package->getBasename(), 0, -4);
				}

				$packageInfo = getPackageInfo($package->getFilename());
				if (!is_array($packageInfo))
					continue;

				if (!empty($packageInfo))
				{
					$packageInfo['installed_id'] = isset($installed_adds[$packageInfo['id']]) ? $installed_adds[$packageInfo['id']]['id'] : 0;
					$packageInfo['sort_id'] = isset($sort_id[$packageInfo['type']]) ? $sort_id[$packageInfo['type']] : $sort_id['unknown'];
					$packageInfo['is_installed'] = isset($installed_adds[$packageInfo['id']]);
					$packageInfo['is_current'] = $packageInfo['is_installed'] && isset($installed_adds[$packageInfo['id']]) && ($installed_adds[$packageInfo['id']]['version'] == $packageInfo['version']);
					$packageInfo['is_newer'] = $packageInfo['is_installed'] && isset($installed_adds[$packageInfo['id']]) && ($installed_adds[$packageInfo['id']]['version'] > $packageInfo['version']);
					$packageInfo['can_install'] = false;
					$packageInfo['can_uninstall'] = false;
					$packageInfo['can_upgrade'] = false;
					$packageInfo['can_emulate_install'] = false;
					$packageInfo['can_emulate_uninstall'] = false;

					// This package is currently NOT installed.  Check if it can be.
					if (!$packageInfo['is_installed'] && $packageInfo['xml']->exists('install'))
					{
						// Check if there's an install for *THIS* version
						$installs = $packageInfo['xml']->set('install');
						foreach ($installs as $install)
						{
							if (!$install->exists('@for') || matchPackageVersion($the_version, $install->fetch('@for')))
							{
								// Okay, this one is good to go.
								$packageInfo['can_install'] = true;
								break;
							}
						}

						// no install found for our version, lets see if one exists for another
						if ($packageInfo['can_install'] === false && $install->exists('@for') && empty($_SESSION['version_emulate']))
						{
							$reset = true;

							// Get the highest install version that is available from the package
							foreach ($installs as $install)
							{
								$packageInfo['can_emulate_install'] = matchHighestPackageVersion($install->fetch('@for'), $reset, $the_version);
								$reset = false;
							}
						}
					}
					// An already installed, but old, package.  Can we upgrade it?
					elseif ($packageInfo['is_installed'] && !$packageInfo['is_current'] && $packageInfo['xml']->exists('upgrade'))
					{
						$upgrades = $packageInfo['xml']->set('upgrade');

						// First go through, and check against the current version of ElkArte.
						foreach ($upgrades as $upgrade)
						{
							// Even if it is for this ElkArte, is it for the installed version of the mod?
							if (!$upgrade->exists('@for') || matchPackageVersion($the_version, $upgrade->fetch('@for')))
								if (!$upgrade->exists('@from') || matchPackageVersion($installed_adds[$packageInfo['id']]['version'], $upgrade->fetch('@from')))
								{
									$packageInfo['can_upgrade'] = true;
									break;
								}
						}
					}
					// Note that it has to be the current version to be uninstallable.  Shucks.
					elseif ($packageInfo['is_installed'] && $packageInfo['is_current'] && $packageInfo['xml']->exists('uninstall'))
					{
						$uninstalls = $packageInfo['xml']->set('uninstall');

						// Can we find any uninstallation methods that work for this ElkArte version?
						foreach ($uninstalls as $uninstall)
						{
							if (!$uninstall->exists('@for') || matchPackageVersion($the_version, $uninstall->fetch('@for')))
							{
								$packageInfo['can_uninstall'] = true;
								break;
							}
						}

						// No uninstall found for this version, lets see if one exists for another
						if ($packageInfo['can_uninstall'] === false && $uninstall->exists('@for') && empty($_SESSION['version_emulate']))
						{
							$reset = true;

							// Get the highest install version that is available from the package
							foreach ($uninstalls as $uninstall)
							{
								$packageInfo['can_emulate_uninstall'] = matchHighestPackageVersion($uninstall->fetch('@for'), $reset, $the_version);
								$reset = false;
							}
						}
					}

					// Add-on / Modification
					if ($packageInfo['type'] == 'addon' || $packageInfo['type'] == 'modification' || $packageInfo['type'] == 'mod')
					{
						$sort_id['modification']++;
						$sort_id['mod']++;
						$sort_id['addon']++;
						if ($installed)
						{
							if (!empty($context['available_modification'][$packageInfo['id']]))
							{
								$packages['modification'][strtolower($packageInfo[$sort]) . '_' . $sort_id['mod']] = $packageInfo['id'];
								$context['available_modification'][$packageInfo['id']] = array_merge($context['available_modification'][$packageInfo['id']], $packageInfo);
							}
						}
						else
						{
							$packages['modification'][strtolower($packageInfo[$sort]) . '_' . $sort_id['mod']] = md5($package->getFilename());
							$context['available_modification'][md5($package->getFilename())] = $packageInfo;
						}
					}
					// Avatar package.
					elseif ($packageInfo['type'] == 'avatar')
					{
						$sort_id[$packageInfo['type']]++;
						$packages['avatar'][strtolower($packageInfo[$sort])] = md5($package->getFilename());
						$context['available_avatar'][md5($package->getFilename())] = $packageInfo;
					}
					// Smiley package.
					elseif ($packageInfo['type'] == 'smiley')
					{
						$sort_id[$packageInfo['type']]++;
						$packages['smiley'][strtolower($packageInfo[$sort])] = md5($package->getFilename());
						$context['available_smiley'][md5($package->getFilename())] = $packageInfo;
					}
					// Language package.
					elseif ($packageInfo['type'] == 'language')
					{
						$sort_id[$packageInfo['type']]++;
						$packages['language'][strtolower($packageInfo[$sort])] = md5($package->getFilename());
						$context['available_language'][md5($package->getFilename())] = $packageInfo;
					}
					// Other stuff.
					else
					{
						$sort_id['unknown']++;
						$packages['unknown'][strtolower($packageInfo[$sort])] = md5($package->getFilename());
						$context['available_unknown'][md5($package->getFilename())] = $packageInfo;
					}
				}
			}
		}
		catch (UnexpectedValueException $e)
		{
			// @todo for now do nothing...
		}

		if (isset($this->_req->query->desc))
			krsort($packages[$params]);
		else
			ksort($packages[$params]);

		return $packages[$params];
	}
}

/**
 * Checks the permissions of all the areas that will be affected by the package
 *
 * @package Packages
 * @param string $path
 * @param mixed[] $data
 * @param int $level
 */
function fetchPerms__recursive($path, &$data, $level)
{
	global $context;

	$isLikelyPath = false;
	foreach ($context['look_for'] as $possiblePath)
	{
		if (substr($possiblePath, 0, strlen($path)) == $path)
			$isLikelyPath = true;
	}

	// Is this where we stop?
	if (isset($_GET['xml']) && !empty($context['look_for']) && !$isLikelyPath)
		return;
	elseif ($level > $context['default_level'] && !$isLikelyPath)
		return;

	// Are we actually interested in saving this data?
	$save_data = empty($context['only_find']) || $context['only_find'] == $path;

	// @todo Shouldn't happen - but better error message?
	if (!is_dir($path))
		Errors::instance()->fatal_lang_error('no_access', false);

	// This is where we put stuff we've found for sorting.
	$foundData = array(
		'files' => array(),
		'folders' => array(),
	);

	try
	{
		$entrys = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
		foreach ($entrys as $entry)
		{
			// Some kind of file?
			if ($entry->isFile())
			{
				// Are we listing PHP files in this directory?
				if ($save_data && !empty($data['list_contents']) && $entry->getExtension() === 'php')
					$foundData['files'][$entry->getFilename()] = true;
				// A file we were looking for.
				elseif ($save_data && isset($data['contents'][$entry->getFilename()]))
					$foundData['files'][$entry->getFilename()] = true;
			}
			// It's a directory - we're interested one way or another, probably...
			elseif ($entry->isDir())
			{
				// Going further?
				if ((!empty($data['type']) && $data['type'] === 'dir_recursive')
					|| (isset($data['contents'][$entry->getFilename()])
						&& (!empty($data['contents'][$entry->getFilename()]['list_contents'])
							|| (!empty($data['contents'][$entry->getFilename()]['type'])
								&& $data['contents'][$entry->getFilename()]['type'] === 'dir_recursive'))))
				{
					if (!isset($data['contents'][$entry->getFilename()]))
						$foundData['folders'][$entry->getFilename()] = 'dir_recursive';
					else
						$foundData['folders'][$entry->getFilename()] = true;

					// If this wasn't expected inherit the recusiveness...
					if (!isset($data['contents'][$entry->getFilename()]))
						// We need to do this as we will be going all recursive.
						$data['contents'][$entry->getFilename()] = array(
							'type' => 'dir_recursive',
						);

					// Actually do the recursive stuff...
					fetchPerms__recursive($entry->getPathname(), $data['contents'][$entry->getFilename()], $level + 1);
				}
				// Maybe it is a folder we are not descending into.
				elseif (isset($data['contents'][$entry->getFilename()]))
					$foundData['folders'][$entry->getFilename()] = true;
				// Otherwise we stop here.
			}
		}
	}
	catch (UnexpectedValueException $e)
	{
		// @todo for now do nothing...
	}

	// Nothing to see here?
	if (!$save_data)
		return;

	// Now actually add the data, starting with the folders.
	ksort($foundData['folders']);
	foreach ($foundData['folders'] as $folder => $type)
	{
		$additional_data = array(
			'perms' => array(
				'chmod' => @is_writable($path . '/' . $folder),
				'perms' => @fileperms($path . '/' . $folder),
			),
		);
		if ($type !== true)
			$additional_data['type'] = $type;

		// If there's an offset ignore any folders in XML mode.
		if (isset($_GET['xml']) && $context['file_offset'] == 0)
		{
			$context['xml_data']['folders']['children'][] = array(
				'attributes' => array(
					'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
					'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
					'folder' => 1,
					'path' => $context['only_find'],
					'level' => $level,
					'more' => 0,
					'offset' => $context['file_offset'],
					'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find'] . '/' . $folder),
					'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
				'value' => $folder,
			);
		}
		elseif (!isset($_GET['xml']))
		{
			if (isset($data['contents'][$folder]))
				$data['contents'][$folder] = array_merge($data['contents'][$folder], $additional_data);
			else
				$data['contents'][$folder] = $additional_data;
		}
	}

	// Now we want to do a similar thing with files.
	ksort($foundData['files']);
	$counter = -1;
	foreach ($foundData['files'] as $file => $dummy)
	{
		$counter++;

		// Have we reached our offset?
		if ($context['file_offset'] > $counter)
			continue;

		// Gone too far?
		if ($counter > ($context['file_offset'] + $context['file_limit']))
			continue;

		$additional_data = array(
			'perms' => array(
				'chmod' => @is_writable($path . '/' . $file),
				'perms' => @fileperms($path . '/' . $file),
			),
		);

		// XML?
		if (isset($_GET['xml']))
		{
			$context['xml_data']['folders']['children'][] = array(
				'attributes' => array(
					'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
					'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
					'folder' => 0,
					'path' => $context['only_find'],
					'level' => $level,
					'more' => $counter == ($context['file_offset'] + $context['file_limit']) ? 1 : 0,
					'offset' => $context['file_offset'],
					'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find'] . '/' . $file),
					'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
				'value' => $file,
			);
		}
		elseif ($counter != ($context['file_offset'] + $context['file_limit']))
		{
			if (isset($data['contents'][$file]))
				$data['contents'][$file] = array_merge($data['contents'][$file], $additional_data);
			else
				$data['contents'][$file] = $additional_data;
		}
	}
}

/**
 * Function called to briefly pause execution of directory/file chmod actions
 *
 * - Called by action_perms_save().
 *
 * @package Packages
 */
function pausePermsSave()
{
	global $context, $txt;

	// Try get more time...
	detectServer()->setTimeLimit(600);

	// Set up the items for the pause form
	$context['sub_template'] = 'pause_action_permissions';
	$context['page_title'] = $txt['package_file_perms_applying'];

	// And how are we progressing with our directories
	$context['remaining_items'] = count($context['method'] == 'individual' ? $context['to_process'] : $context['directory_list']);
	$context['progress_message'] = sprintf($context['method'] == 'individual' ? $txt['package_file_perms_items_done'] : $txt['package_file_perms_dirs_done'], $context['total_items'] - $context['remaining_items'], $context['total_items']);
	$context['progress_percent'] = round(($context['total_items'] - $context['remaining_items']) / $context['total_items'] * 100, 1);

	// Never more than 100%!
	$context['progress_percent'] = min($context['progress_percent'], 100);

	// And how are we progressing with files within a directory
	if ($context['method'] != 'individual' && !empty($context['total_files']))
	{
		$context['file_progress_message'] = sprintf($txt['package_file_perms_files_done'], $context['file_offset'], $context['total_files']);
		$context['file_progress_percent'] = round($context['file_offset'] / $context['total_files'] * 100, 1);

		// Never more than 100%!
		$context['file_progress_percent'] = min($context['file_progress_percent'], 100);
	}

	obExit();
}