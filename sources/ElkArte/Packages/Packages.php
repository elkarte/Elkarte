<?php

/**
 * This file is the main Package Manager.
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

namespace ElkArte\Packages;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\Util;

/**
 * This class is the administration package manager controller.
 * Its main job is to install/uninstall, allow to browse, packages.
 * In fact, just about everything related to addon packages, including FTP connections when necessary.
 *
 * @package Packages
 */
class Packages extends AbstractController
{
	/** @var int The id from the DB or an installed package */
	public $install_id;

	/** @var string[] Array of installed theme paths */
	public $theme_paths;

	/** @var array Array of files / directories that require permissions */
	public $chmod_files;

	/** @var string Filename of the package */
	private $_filename;

	/** @var string Base path of the package */
	private $_base_path;

	/** @var bool If this is an un-install pass or not */
	private $_uninstalling;

	/** @var bool If the package is installed, previously or not */
	private $_is_installed;

	/** @var \ElkArte\FileFunctions */
	private $fileFunc;

	/**
	 * Pre Dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// Generic subs for this controller
		require_once(SUBSDIR . '/Package.subs.php');

		$this->fileFunc = FileFunctions::instance();
	}

	/**
	 * Entry point, the default method of this controller.
	 *
	 * @event integrate_sa_packages
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// Admins-only!
		isAllowedTo('admin_forum');

		// Load all the basic stuff.
		Txt::load('Packages');
		theme()->getTemplates()->load('Packages');
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
			'flush' => array($this, 'action_flush'),
			'examine' => array($this, 'action_examine'),
			'showoperations' => array($this, 'action_showoperations'),
			// The following two belong to PackageServers,
			// for UI's sake moved here at least temporarily
			'servers' => array(
				'controller' => '\\ElkArte\\Packages\\PackageServers',
				'function' => 'action_list'),
			'upload' => array(
				'controller' => '\\ElkArte\\Packages\\PackageServers',
				'function' => 'action_upload'),
		);

		// Set up action/subaction stuff.
		$action = new Action('packages');

		// Set up some tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['package_manager'],
			'description' => $txt['package_manager_desc'],
			'tabs' => array(
				'browse' => array(),
				'installed' => array(
					'description' => $txt['installed_packages_desc'],
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
	 * Test install/uninstall a package.
	 */
	public function action_install()
	{
		global $txt, $context;

		// You have to specify a file!!
		$file = $this->_req->getQuery('package', 'trim');
		if (empty($file))
		{
			redirectexit('action=admin;area=packages');
		}

		// What are we trying to do
		$this->_filename = (string) preg_replace('~[\.]+~', '.', $file);
		$this->_uninstalling = $this->_req->query->sa === 'uninstall';

		// If we can't find the file, our installation ends here
		if (!file_exists(BOARDDIR . '/packages/' . $this->_filename))
		{
			throw new Exception('package_no_file', false);
		}

		// Do we have an existing id, for uninstalls and the like.
		$this->install_id = $this->_req->getQuery('pid', 'intval', 0);

		// This will be needed
		require_once(SUBSDIR . '/Themes.subs.php');

		// Load up the package FTP information?
		$create_chmod_control = new PackageChmod();
		$create_chmod_control->createChmodControl();

		// Prevent browsers from auto completing the FTP password
		theme()->addInlineJavascript('disableAutoComplete();', true);

		// Make sure our temp directory exists and is empty.
		if (file_exists(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp', false);
		}
		else
		{
			$this->_create_temp_dir();
		}

		// Extract the files in to the temp so we can get things like the readme, etc.
		$this->_extract_files_temp();

		// Load up any custom themes we may want to install into...
		$this->theme_paths = getThemesPathbyID();

		// Get the package info...
		$packageInfo = getPackageInfo($this->_filename);
		if (!is_array($packageInfo))
		{
			throw new Exception($packageInfo);
		}

		$packageInfo['filename'] = $this->_filename;

		// The addon isn't installed.... unless proven otherwise.
		$this->_is_installed = false;

		// See if it is installed?
		$package_installed = isPackageInstalled($packageInfo['id'], $this->install_id);

		$context['database_changes'] = array();
		if (isset($packageInfo['uninstall']['database']))
		{
			$context['database_changes'][] = $txt['execute_database_changes'] . ' - ' . $packageInfo['uninstall']['database'];
		}
		elseif (!empty($package_installed['db_changes']))
		{
			foreach ($package_installed['db_changes'] as $change)
			{
				if (isset($change[2], $txt['package_db_' . $change[0]]))
				{
					$context['database_changes'][] = sprintf($txt['package_db_' . $change[0]], $change[1], $change[2]);
				}
				elseif (isset($txt['package_db_' . $change[0]]))
				{
					$context['database_changes'][] = sprintf($txt['package_db_' . $change[0]], $change[1]);
				}
				else
				{
					$context['database_changes'][] = $change[0] . '-' . $change[1] . (isset($change[2]) ? '-' . $change[2] : '');
				}
			}
		}

		$actions = $this->_get_package_actions($package_installed, $packageInfo);

		$context['actions'] = array();
		$context['ftp_needed'] = false;

		// No actions found, return so we can display an error
		if (empty($actions))
		{
			redirectexit('action=admin;area=packages');
		}

		// Now prepare things for the template using the package actions class
		$pka = new PackageActions(new EventManager());
		$pka->setUser(User::$info);
		$pka->test_init($actions, $this->_uninstalling, $this->_base_path, $this->theme_paths);

		$context['has_failure'] = $pka->has_failure;
		$context['failure_details'] = $pka->failure_details;
		$context['actions'] = $pka->ourActions;

		// Change our last link tree item for more information on this Packages area.
		$context['linktree'][count($context['linktree']) - 1] = array(
			'url' =>getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'browse']),
			'name' => $this->_uninstalling ? $txt['package_uninstall_actions'] : $txt['install_actions']
		);

		// All things to make the template go round
		$context['page_title'] .= ' - ' . ($this->_uninstalling ? $txt['package_uninstall_actions'] : $txt['install_actions']);
		$context['sub_template'] = 'view_package';
		$context['filename'] = $this->_filename;
		$context['package_name'] = $packageInfo['name'] ?? $this->_filename;
		$context['is_installed'] = $this->_is_installed;
		$context['uninstalling'] = $this->_uninstalling;
		$context['extract_type'] = $packageInfo['type'] ?? 'modification';

		// Have we got some things which we might want to do "multi-theme"?
		$this->_multi_theme($pka->themeFinds['candidates']);

		// Trash the cache... which will also check permissions for us!
		package_flush_cache(true);

		// Clear the temp directory
		if (file_exists(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp');
		}

		// Will we need chmod permissions to pull this off
		$this->chmod_files = !empty($pka->chmod_files) ? $pka->chmod_files : array();
		if (!empty($this->chmod_files))
		{
			$chmod_control = new PackageChmod();
			$ftp_status = $chmod_control->createChmodControl($this->chmod_files);
			$context['ftp_needed'] = !empty($ftp_status['files']['notwritable']) && !empty($context['package_ftp']);
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => ($this->_uninstalling ? 'uninstall' : 'install') . ($context['ftp_needed'] ? '' : '2'), 'package' => $this->_filename, 'pid' => $this->install_id]);
		checkSubmitOnce('register');
	}

	/**
	 * Creates the packages temp directory
	 *
	 * - First try as 755, failing moves to 777
	 * - Will try with FTP permissions for cases where the web server credentials
	 * do not have "create" directory permissions
	 *
	 * @throws \ElkArte\Exceptions\Exception when no directory can be made
	 */
	private function _create_temp_dir()
	{
		global $context, $scripturl, $package_ftp;

		// Try to Make the temp directory
		if (!mktree(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp', false);
			$chmod_control = new PackageChmod();
			$chmod_control->createChmodControl(
				array(BOARDDIR . '/packages/temp/delme.tmp'),
				array(
					'destination_url' => $scripturl . '?action=admin;area=packages;sa=' . $this->_req->query->sa . ';package=' . ($context['filename'] ?? ''),
					'crash_on_error' => true
				)
			);

			// No temp directory was able to be made, that's fatal
			deltree(BOARDDIR . '/packages/temp', false);
			unset($package_ftp, $_SESSION['ftp_connection']);
			throw new Exception('package_cant_download', false);
		}
	}

	/**
	 * Extracts a package file in the packages/temp directory
	 *
	 * - Sets the base path as needed
	 * - Loads $extracted_files with the package file listing
	 */
	private function _extract_files_temp()
	{
		// Is it a file in the package directory
		if (is_file(BOARDDIR . '/packages/' . $this->_filename))
		{
			// Unpack the files in to the packages/temp directory
			$extracted_files = read_tgz_file(BOARDDIR . '/packages/' . $this->_filename, BOARDDIR . '/packages/temp');

			// Determine the base path for the package
			if ($extracted_files && !$this->fileFunc->fileExists(BOARDDIR . '/packages/temp/package-info.xml'))
			{
				foreach ($extracted_files as $file)
				{
					if (basename($file['filename']) === 'package-info.xml')
					{
						$this->_base_path = dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset($this->_base_path))
			{
				$this->_base_path = '';
			}
		}
		// Perhaps its a directory then, assumed to be extracted
		elseif (!empty($this->_filename) && $this->fileFunc->isDir(BOARDDIR . '/packages/' . $this->_filename))
		{
			// Copy the directory to the temp directory
			copytree(BOARDDIR . '/packages/' . $this->_filename, BOARDDIR . '/packages/temp');

			// Get the file listing
			$this->fileFunc->listtree(BOARDDIR . '/packages/temp');
			$this->_base_path = '';
		}
		// Well we don't know what it is then, so we stop
		else
		{
			throw new Exception('no_access', false);
		}
	}

	/**
	 * Returns the actions that are required to install / uninstall / upgrade a package.
	 * Actions are defined by parsePackageInfo
	 * Sets the is_installed flag
	 *
	 * @param array $package_installed
	 * @param array $packageInfo Details for the package being tested/installed, set by getPackageInfo
	 * @param bool $testing passed to parsePackageInfo, true for test install, false for real install
	 *
	 * @return array
	 * @throws \ElkArte\Exceptions\Exception package_cant_uninstall, package_uninstall_cannot
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
				throw new Exception('package_cant_uninstall', false);
			}

			$parser = new PackageParser();
			$actions = $parser->parsePackageInfo($packageInfo['xml'], $testing, 'uninstall');

			// Gadzooks!  There's no uninstaller at all!?
			if (empty($actions))
			{
				deltree(BOARDDIR . '/packages/temp');
				throw new Exception('package_uninstall_cannot', false);
			}

			// Can't edit the custom themes it's edited if you're uninstalling, they must be removed.
			$context['themes_locked'] = true;

			// Only let them uninstall themes it was installed into.
			foreach ($this->theme_paths as $id => $data)
			{
				if ($id != 1 && !in_array($id, $package_installed['old_themes']))
				{
					unset($this->theme_paths[$id]);
				}
			}
		}
		// Or is it already installed and you want to upgrade
		elseif (isset($package_installed['old_version']) && $package_installed['old_version'] != $packageInfo['version'])
		{
			// Look for an upgrade...
			$parser = new PackageParser();
			$actions = $parser->parsePackageInfo($packageInfo['xml'], $testing, 'upgrade', $package_installed['old_version']);

			// There was no upgrade....
			if (empty($actions))
			{
				$this->_is_installed = true;
			}
			else
			{
				// Otherwise they can only upgrade themes from the first time around.
				foreach ($this->theme_paths as $id => $data)
				{
					if ($id != 1 && !in_array($id, $package_installed['old_themes']))
					{
						unset($this->theme_paths[$id]);
					}
				}
			}
		}
		// Simply already installed
		elseif (isset($package_installed['old_version']) && $package_installed['old_version'] == $packageInfo['version'])
		{
			$this->_is_installed = true;
		}

		if (!isset($package_installed['old_version']) || $this->_is_installed)
		{
			$parser = new PackageParser();
			$actions = $parser->parsePackageInfo($packageInfo['xml'], $testing, 'install');
		}

		return $actions;
	}

	/**
	 * Determines the availability / validity of installing a package in any of the installed themes
	 *
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

				if ($matches[1] === 'imagesdir')
				{
					$path = '/' . basename($settings['default_images_url']);
				}
				elseif ($matches[1] === 'languagedir' || $matches[1] === 'languages_dir')
				{
					$path = '/ElkArte/Languages';
				}
				else
				{
					$path = '';
				}

				if (!empty($matches[3]))
				{
					$path .= $matches[3];
				}

				if (!$this->_uninstalling)
				{
					$path .= '/' . basename($action_data['filename']);
				}

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
							if (!dirTest(dirname($real_path)))
							{
								$temp = dirname($real_path);
								while (!file_exists($temp) && strlen($temp) > 1)
								{
									$temp = dirname($temp);
								}

								$this->chmod_files[] = $temp;
							}

							if ($action_data['type'] === 'require-dir' && !is_writable($real_path) && (file_exists($real_path) || !is_writable(dirname($real_path))))
							{
								$this->chmod_files[] = $real_path;
							}

							if (!isset($context['theme_actions'][$id]))
							{
								$context['theme_actions'][$id] = array(
									'name' => $theme_data['name'],
									'actions' => array(),
								);
							}

							if ($this->_uninstalling)
							{
								$context['theme_actions'][$id]['actions'][] = array(
									'type' => $txt['package_delete'] . ' ' . ($action_data['type'] === 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
									'action' => strtr($real_path, array('\\' => '/', BOARDDIR => '.')),
									'description' => '',
									'value' => base64_encode(json_encode(array('type' => $action_data['type'], 'orig' => $action_data['filename'], 'future' => $real_path, 'id' => $id))),
									'not_mod' => true,
								);
							}
							else
							{
								$context['theme_actions'][$id]['actions'][] = array(
									'type' => $txt['package_extract'] . ' ' . ($action_data['type'] === 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
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
	}

	/**
	 * Actually installs/uninstalls a package
	 */
	public function action_install2()
	{
		global $txt, $context, $modSettings;

		// Make sure we don't install this addon twice.
		checkSubmitOnce('check');
		checkSession();

		// If there's no package file, what are we installing?
		$this->_filename = $this->_req->getQuery('package', 'trim');
		if (empty($this->_filename))
		{
			redirectexit('action=admin;area=packages');
		}

		// And if the file does not exist there is a problem
		if (!file_exists(BOARDDIR . '/packages/' . $this->_filename))
		{
			throw new Exception('package_no_file', false);
		}

		// If this is an uninstallation, we'll have an id.
		$this->install_id = $this->_req->getQuery('pid', 'intval', 0);

		// Installing in themes will require some help
		require_once(SUBSDIR . '/Themes.subs.php');

		// @todo Perhaps do it in steps, if necessary?
		$this->_uninstalling = $this->_req->query->sa === 'uninstall2';

		// Load up the package FTP information?
		$chmod_control = new PackageChmod();
		$chmod_control->createChmodControl(
			array(),
			array(
				'destination_url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' =>  $this->_req->query->sa, 'package' => $this->_req->query->package])
			)
		);

		// Make sure temp directory exists and is empty!
		if (file_exists(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp', false);
		}
		else
		{
			$this->_create_temp_dir();
		}

		// Let the unpacker do the work.
		$this->_extract_files_temp();

		// Are we installing this into any custom themes?
		$custom_themes = array(1);
		$known_themes = explode(',', $modSettings['knownThemes']);
		if (!empty($this->_req->post->custom_theme))
		{
			foreach ($this->_req->post->custom_theme as $tid)
			{
				if (in_array($tid, $known_themes))
				{
					$custom_themes[] = (int) $tid;
				}
			}
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
				{
					continue;
				}

				$theme_data = json_decode(base64_decode($change), true);
				if (empty($theme_data['type']))
				{
					continue;
				}

				$themes_installed[] = $theme_data['id'];
				$context['theme_copies'][$theme_data['type']][$theme_data['orig']][] = $theme_data['future'];
			}
		}

		// Get the package info...
		$packageInfo = getPackageInfo($this->_filename);
		if (!is_array($packageInfo))
		{
			throw new Exception($packageInfo);
		}

		$packageInfo['filename'] = $this->_filename;

		$context['base_path'] = $this->_base_path;

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

		// Fetch the installation status and action log
		$install_log = $this->_get_package_actions($package_installed, $packageInfo, false);

		// Set up the details for the sub template, linktree, etc
		$context['linktree'][count($context['linktree']) - 1] = array(
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'browse']),
			'name' => $this->_uninstalling ? $txt['uninstall'] : $txt['extracting']
		);
		$context['page_title'] .= ' - ' . ($this->_uninstalling ? $txt['uninstall'] : $txt['extracting']);
		$context['sub_template'] = 'extract_package';
		$context['filename'] = $this->_filename;
		$context['install_finished'] = false;
		$context['is_installed'] = $this->_is_installed;
		$context['uninstalling'] = $this->_uninstalling;
		$context['extract_type'] = $packageInfo['type'] ?? 'modification';

		// We're gonna be needing the table db functions! ...Sometimes.
		$table_installer = db_table();

		// @todo Make a log of any errors that occurred and output them?
		if (!empty($install_log))
		{
			$pka = new PackageActions(new EventManager());
			$pka->setUser(User::$info);
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
				{
					setPackageState($package_check['package_id'], $this->install_id);
				}
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
				{
					$db_package_log = empty($table_log) ? $old_db_changes : array_merge($old_db_changes, $table_log);
				}
				else
				{
					$db_package_log = $table_log;
				}

				// If there are some database changes we might want to remove then filter them out.
				if (!empty($db_package_log))
				{
					// We're really just checking for entries which are create table AND add columns (etc).
					$tables = array();
					usort($db_package_log, array($this, '_sort_table_first'));
					foreach ($db_package_log as $k => $log)
					{
						if ($log[0] === 'remove_table')
						{
							$tables[] = $log[1];
						}
						elseif (in_array($log[1], $tables))
						{
							unset($db_package_log[$k]);
						}
					}

					$package_installed['db_changes'] = serialize($db_package_log);
				}
				else
				{
					$package_installed['db_changes'] = '';
				}

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
				if ($change[0] === 'remove_table' && isset($change[1]))
				{
					$table_installer->drop_table($change[1]);
				}
				elseif ($change[0] === 'remove_column' && isset($change[2]))
				{
					$table_installer->remove_column($change[1], $change[2]);
				}
				elseif ($change[0] === 'remove_index' && isset($change[2]))
				{
					$table_installer->remove_index($change[1], $change[2]);
				}
			}
		}

		// Clean house... get rid of the evidence ;).
		if (file_exists(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp');
		}

		// Log what we just did.
		logAction($this->_uninstalling ? 'uninstall_package' : (!empty($is_upgrade) ? 'upgrade_package' : 'install_package'), array('package' => Util::htmlspecialchars($packageInfo['name']), 'version' => Util::htmlspecialchars($packageInfo['version'])), 'admin');

		// Just in case, let's clear the whole cache to avoid anything going up the swanny.
		Cache::instance()->clean();

		// Restore file permissions?
		$chmod_control = new PackageChmod();
		$chmod_control->createChmodControl(array(), array(), true);
	}

	/**
	 * List the files in a package.
	 */
	public function action_list()
	{
		global $txt, $context;

		// No package?  Show him or her the door.
		$package = $this->_req->getQuery('package', 'trim', '');
		if (empty($package))
		{
			redirectexit('action=admin;area=packages');
		}

		$context['linktree'][] = array(
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'list', 'package' => $package]),
			'name' => $txt['list_file']
		);
		$context['page_title'] .= ' - ' . $txt['list_file'];
		$context['sub_template'] = 'list';

		// The filename...
		$context['filename'] = $package;

		// Let the unpacker do the work.
		if (is_file(BOARDDIR . '/packages/' . $context['filename']))
		{
			$context['files'] = read_tgz_file(BOARDDIR . '/packages/' . $context['filename'], null);
		}
		elseif ($this->fileFunc->isDir(BOARDDIR . '/packages/' . $context['filename']))
		{
			$context['files'] = $this->fileFunc->listtree(BOARDDIR . '/packages/' . $context['filename']);
		}
	}

	/**
	 * Display one of the files in a package.
	 */
	public function action_examine()
	{
		global $txt, $context;

		// No package?  Show him or her the door.
		if (!isset($this->_req->query->package) || $this->_req->query->package == '')
		{
			redirectexit('action=admin;area=packages');
		}

		// No file?  Show him or her the door.
		if (!isset($this->_req->query->file) || $this->_req->query->file == '')
		{
			redirectexit('action=admin;area=packages');
		}

		$this->_req->query->package = preg_replace('~[\.]+~', '.', strtr($this->_req->query->package, array('/' => '_', '\\' => '_')));
		$this->_req->query->file = preg_replace('~[\.]+~', '.', $this->_req->query->file);

		if (isset($this->_req->query->raw))
		{
			if (is_file(BOARDDIR . '/packages/' . $this->_req->query->package))
			{
				echo read_tgz_file(BOARDDIR . '/packages/' . $this->_req->query->package, $this->_req->query->file, true);
			}
			elseif (is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
			{
				echo file_get_contents(BOARDDIR . '/packages/' . $this->_req->query->package . '/' . $this->_req->query->file);
			}

			obExit(false);
		}

		$context['linktree'][count($context['linktree']) - 1] = array(
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'list', 'package' => $this->_req->query->package]),
			'name' => $txt['package_examine_file']
		);
		$context['page_title'] .= ' - ' . $txt['package_examine_file'];
		$context['sub_template'] = 'examine';

		// The filename...
		$context['package'] = $this->_req->query->package;
		$context['filename'] = $this->_req->query->file;

		// Let the unpacker do the work.... but make sure we handle images properly.
		if (in_array(strtolower(strrchr($this->_req->query->file, '.')), array('.bmp', '.gif', '.jpeg', '.jpg', '.png')))
		{
			$context['filedata'] = '<img src="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'examine', 'package' => $this->_req->query->package, 'file' => $this->_req->query->file, 'raw']) . '" alt="' . $this->_req->query->file . '" />';
		}
		elseif (is_file(BOARDDIR . '/packages/' . $this->_req->query->package))
		{
			$context['filedata'] = htmlspecialchars(read_tgz_file(BOARDDIR . '/packages/' . $this->_req->query->package, $this->_req->query->file, true));
		}
		elseif (is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
		{
			$context['filedata'] = htmlspecialchars(file_get_contents(BOARDDIR . '/packages/' . $this->_req->query->package . '/' . $this->_req->query->file));
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
		// Check it.
		checkSession('get');

		// Ack, don't allow deletion of arbitrary files here, could become a security hole somehow!
		if (!isset($this->_req->query->package) || $this->_req->query->package === 'index.php' || $this->_req->query->package === 'installed.list' || $this->_req->query->package === 'backups')
		{
			redirectexit('action=admin;area=packages;sa=browse');
		}
		$this->_req->query->package = preg_replace('~[\.]+~', '.', strtr($this->_req->query->package, array('/' => '_', '\\' => '_')));

		// Can't delete what's not there.
		if (file_exists(BOARDDIR . '/packages/' . $this->_req->query->package)
			&& (substr($this->_req->query->package, -4) === '.zip' || substr($this->_req->query->package, -4) === '.tgz' || substr($this->_req->query->package, -7) === '.tar.gz' || is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
			&& $this->_req->query->package !== 'backups' && substr($this->_req->query->package, 0, 1) !== '.')
		{
			$chmod_control = new PackageChmod();
			$chmod_control->createChmodControl(
				array(BOARDDIR . '/packages/' . $this->_req->query->package),
				array(
					'destination_url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'remove', 'package' => $this->_req->query->package]),
					'crash_on_error' => true
				)
			);

			if (is_dir(BOARDDIR . '/packages/' . $this->_req->query->package))
			{
				deltree(BOARDDIR . '/packages/' . $this->_req->query->package);
			}
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
		global $txt, $context;

		$context['page_title'] .= ' - ' . $txt['browse_packages'];
		$context['forum_version'] = FORUM_VERSION;
		$context['available_addon'] = array();
		$context['available_avatar'] = array();
		$context['available_smiley'] = array();
		$context['available_language'] = array();
		$context['available_unknown'] = array();

		$installed = $context['sub_action'] === 'installed';
		$context['package_types'] = $installed ? array('addon') : array('addon', 'avatar', 'language', 'smiley', 'unknown');

		foreach ($context['package_types'] as $type)
		{
			// Use the standard templates for showing this.
			$listOptions = array(
				'id' => 'packages_lists_' . $type,
				'title' => $installed ? $txt['view_and_remove'] : $txt[($type === 'addon' ? 'modification' : $type) . '_package'],
				'no_items_label' => $txt['no_packages'],
				'get_items' => array(
					'function' => array($this, 'list_packages'),
					'params' => array('params' => $type, 'installed' => $installed),
				),
				'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => $context['sub_action'], 'type' => $type]),
				'default_sort_col' => 'mod_name' . $type,
				'columns' => array(
					'mod_name' . $type => array(
						'header' => array(
							'value' => $txt['mod_name'],
							'style' => 'width: 25%;',
						),
						'data' => array(
							'function' => function ($package_md5) use ($type) {
								global $context;

								if (isset($context['available_' . $type][$package_md5]))
								{
									return $context['available_' . $type][$package_md5]['name'];
								}

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
							'function' => function ($package_md5) use ($type) {
								global $context;

								if (isset($context['available_' . $type][$package_md5]))
								{
									return $context['available_' . $type][$package_md5]['version'];
								}

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
								global $context, $txt;

								if (!isset($context['available_' . $type][$package_md5]))
								{
									return '';
								}

								// Rewrite shortcut
								$package = $context['available_' . $type][$package_md5];
								$return = '';

								if ($package['can_uninstall'])
								{
									$return = '
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'uninstall', 'package' => $package['filename'], 'pid' => $package['installed_id']]) . '">' . $txt['uninstall'] . '</a>';
								}
								elseif ($package['can_emulate_uninstall'])
								{
									$return = '
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'uninstall', 've' => $package['can_emulate_uninstall'], 'package' => $package['filename'], 'pid' => $package['installed_id']]) . '">' . $txt['package_emulate_uninstall'] . ' ' . $package['can_emulate_uninstall'] . '</a>';
								}
								elseif ($package['can_upgrade'])
								{
									$return = '
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'install', 'package' => $package['filename']]) . '">' . $txt['package_upgrade'] . '</a>';
								}
								elseif ($package['can_install'])
								{
									$return = '
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'install', 'package' => $package['filename']]) . '">' . $txt['install_mod'] . '</a>';
								}
								elseif ($package['can_emulate_install'])
								{
									$return = '
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'install', 've' => $package['can_emulate_install'], 'package' => $package['filename']]) . '">' . $txt['package_emulate_install'] . ' ' . $package['can_emulate_install'] . '</a>';
								}

								return $return . '
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'list', 'package' => $package['filename']]) . '">' . $txt['list_files'] . '</a>
										<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'remove', 'package' => $package['filename'], '{session_data}']) . '"' . ($package['is_installed'] && $package['is_current']
										? ' onclick="return confirm(\'' . $txt['package_delete_bad'] . '\');"'
										: '') . '>' . $txt['package_delete'] . '</a>';
							},
							'class' => 'righttext',
						),
					),
				),
				'additional_rows' => array(
					array(
						'position' => 'bottom_of_list',
						'class' => 'submitbutton',
						'value' => ($context['sub_action'] === 'browse'
							? ''
							: '<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'flush', '{session_data}']) . '" onclick="return confirm(\'' . $txt['package_delete_list_warning'] . '\');">' . $txt['delete_list'] . '</a>'),
					),
				),
			);

			createList($listOptions);
		}

		$context['sub_template'] = 'browse';
		$context['default_list'] = 'packages_lists';
	}

	/**
	 * Test an FTP connection via Ajax
	 *
	 * @uses Xml Template, generic_xml sub template
	 */
	public function action_ftptest()
	{
		global $context, $txt, $package_ftp;

		checkSession('get');

		// Try to make the FTP connection.
		$chmod_control = new PackageChmod();
		$chmod_control->createChmodControl(array(), array('force_find_error' => true));

		// Deal with the template stuff.
		theme()->getTemplates()->load('Xml');
		$context['sub_template'] = 'generic_xml';
		theme()->getLayers()->removeAll();

		// Define the return data, this is simple.
		$context['xml_data'] = array(
			'results' => array(
				'identifier' => 'result',
				'children' => array(
					array(
						'attributes' => array(
							'success' => !empty($package_ftp) ? 1 : 0,
						),
						'value' => !empty($package_ftp) ?
							$txt['package_ftp_test_success']
							: ($context['package_ftp']['error'] ?? $txt['package_ftp_test_failed']),
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
			checkSession();

			updateSettings(array(
				'package_server' => $this->_req->getPost('pack_server', 'trim|\\ElkArte\\Util::htmlspecialchars'),
				'package_port' => $this->_req->getPost('pack_port', 'trim|\\ElkArte\\Util::htmlspecialchars'),
				'package_username' => $this->_req->getPost('pack_user', 'trim|\\ElkArte\\Util::htmlspecialchars'),
				'package_make_backups' => !empty($this->_req->post->package_make_backups),
				'package_make_full_backups' => !empty($this->_req->post->package_make_full_backups)
			));

			redirectexit('action=admin;area=packages;sa=options');
		}

		if (preg_match('~^/home\d*/([^/]+?)/public_html~', $this->_req->server->DOCUMENT_ROOT, $match))
		{
			$default_username = $match[1];
		}
		else
		{
			$default_username = '';
		}

		$context['page_title'] = $txt['package_settings'];
		$context['sub_template'] = 'install_options';
		$context['package_ftp_server'] = $modSettings['package_server'] ?? 'localhost';
		$context['package_ftp_port'] = $modSettings['package_port'] ?? '21';
		$context['package_ftp_username'] = $modSettings['package_username'] ?? $default_username;
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

		$operation_key = $this->_req->getQuery('operation_key', 'trim');
		$filename = $this->_req->getQuery('filename', 'trim');
		$package = $this->_req->getQuery('package', 'trim');
		$install_id = $this->_req->getQuery('install_id', 'intval', 0);

		// We need to know the operation key for the search and replace?
		if (!isset($operation_key, $filename) && !is_numeric($operation_key))
		{
			throw new Exception('operation_invalid', 'general');
		}

		// Load the required file.
		require_once(SUBSDIR . '/Themes.subs.php');

		// Uninstalling the mod?
		$reverse = isset($this->_req->query->reverse);

		// Get the base name.
		$context['filename'] = preg_replace('~[\.]+~', '.', $package);

		// We need to extract this again.
		if (is_file(BOARDDIR . '/packages/' . $context['filename']))
		{
			$context['extracted_files'] = read_tgz_file(BOARDDIR . '/packages/' . $context['filename'], BOARDDIR . '/packages/temp');
			if ($context['extracted_files'] && !file_exists(BOARDDIR . '/packages/temp/package-info.xml'))
			{
				foreach ($context['extracted_files'] as $file)
				{
					if (basename($file['filename']) === 'package-info.xml')
					{
						$context['base_path'] = dirname($file['filename']) . '/';
						break;
					}
				}
			}

			if (!isset($context['base_path']))
			{
				$context['base_path'] = '';
			}
		}
		elseif ($this->fileFunc->isDir(BOARDDIR . '/packages/' . $context['filename']))
		{
			copytree(BOARDDIR . '/packages/' . $context['filename'], BOARDDIR . '/packages/temp');
			$context['extracted_files'] = $this->fileFunc->listtree(BOARDDIR . '/packages/temp');
			$context['base_path'] = '';
		}

		// Load up any custom themes we may want to install into...
		$theme_paths = getThemesPathbyID();

		// For uninstall operations we only consider the themes in which the package is installed.
		if ($reverse && !empty($install_id))
		{
			if ($install_id > 0)
			{
				$old_themes = loadThemesAffected($install_id);
				foreach ($theme_paths as $id => $data)
				{
					if ($id != 1 && !in_array($id, $old_themes))
					{
						unset($theme_paths[$id]);
					}
				}
			}
		}

		$mod_actions = parseModification(@file_get_contents(BOARDDIR . '/packages/temp/' . $context['base_path'] . $this->_req->query->filename), true, $reverse, $theme_paths);

		// Ok lets get the content of the file.
		$context['operations'] = array(
			'search' => strtr(htmlspecialchars($mod_actions[$operation_key]['search_original'], ENT_COMPAT), array('[' => '&#91;', ']' => '&#93;')),
			'replace' => strtr(htmlspecialchars($mod_actions[$operation_key]['replace_original'], ENT_COMPAT), array('[' => '&#91;', ']' => '&#93;')),
			'position' => $mod_actions[$operation_key]['position'],
		);

		// Let's do some formatting...
		$operation_text = $context['operations']['position'] === 'replace' ? 'operation_replace' : ($context['operations']['position'] === 'before' ? 'operation_after' : 'operation_before');
		$bbc_parser = ParserWrapper::instance();
		$context['operations']['search'] = $bbc_parser->parsePackage('[code=' . $txt['operation_find'] . ']' . ($context['operations']['position'] === 'end' ? '?&gt;' : $context['operations']['search']) . '[/code]');
		$context['operations']['replace'] = $bbc_parser->parsePackage('[code=' . $txt[$operation_text] . ']' . $context['operations']['replace'] . '[/code]');

		// No layers
		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'view_operations';
	}

	/**
	 * Get a listing of all the packages
	 *
	 * - Determines if the package is addon, smiley, avatar, language or unknown package
	 * - Determines if the package has been installed or not
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $params 'type' type of package
	 * @param bool $installed
	 *
	 * @return mixed
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function list_packages($start, $items_per_page, $sort, $params, $installed)
	{
		global $context;
		static $instadds, $packages;

		// Start things up
		if (!isset($packages[$params]))
		{
			$packages[$params] = array();
		}

		// We need the packages directory to be writable for this.
		if (!@is_writable(BOARDDIR . '/packages'))
		{
			$create_chmod_control = new PackageChmod();
			$create_chmod_control->createChmodControl(
				array(BOARDDIR . '/packages'),
				array(
					'destination_url' =>getUrl('admin', ['action' => 'admin', 'area' => 'packages']),
					'crash_on_error' => true
				)
			);
		}

		list ($the_brand, $the_version) = explode(' ', FORUM_VERSION, 2);

		// Here we have a little code to help those who class themselves as something of gods, version emulation ;)
		if (isset($this->_req->query->version_emulate) && strtr($this->_req->query->version_emulate, array($the_brand => '')) == $the_version)
		{
			unset($_SESSION['version_emulate']);
		}
		elseif (isset($this->_req->query->version_emulate))
		{
			if (($this->_req->query->version_emulate === 0 || $this->_req->query->version_emulate === FORUM_VERSION) && isset($this->_req->session->version_emulate))
			{
				unset($_SESSION['version_emulate']);
			}
			elseif ($this->_req->query->version_emulate !== 0)
			{
				$_SESSION['version_emulate'] = strtr($this->_req->query->version_emulate, array('-' => ' ', '+' => ' ', $the_brand . ' ' => ''));
			}
		}

		if (!empty($_SESSION['version_emulate']))
		{
			$context['forum_version'] = $the_brand . ' ' . $_SESSION['version_emulate'];
			$the_version = $_SESSION['version_emulate'];
		}

		if (isset($_SESSION['single_version_emulate']))
		{
			unset($_SESSION['single_version_emulate']);
		}

		if (empty($instadds))
		{
			$instadds = loadInstalledPackages();
			$installed_adds = array();

			// Look through the list of installed mods...
			foreach ($instadds as $installed_add)
			{
				$installed_adds[$installed_add['package_id']] = array(
					'id' => $installed_add['id'],
					'version' => $installed_add['version'],
				);
			}

			// Get a list of all the ids installed, so the latest packages won't include already installed ones.
			$context['installed_adds'] = array_keys($installed_adds);
		}

		if ($installed)
		{
			$sort_id = 1;
			foreach ($instadds as $installed_add)
			{
				$context['available_addon'][$installed_add['package_id']] = array(
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
		{
			foreach ($context['package_types'] as $type)
			{
				$packages[$type] = array();
			}
		}

		try
		{
			$dir = new \FilesystemIterator(BOARDDIR . '/packages', \FilesystemIterator::SKIP_DOTS);
			$filtered_dir = new PackagesFilterIterator($dir);

			$dirs = array();
			$sort_id = array(
				'addon' => 1,
				'avatar' => 1,
				'language' => 1,
				'smiley' => 1,
				'unknown' => 1,
			);
			foreach ($filtered_dir as $package)
			{
				foreach ($context['package_types'] as $type)
				{
					if (isset($context['available_' . $type][md5($package->getFilename())]))
					{
						continue 2;
					}
				}

				// Skip directories or files that are named the same.
				if ($package->isDir())
				{
					if (in_array($package, $dirs))
					{
						continue;
					}
					$dirs[] = $package;
				}
				elseif (substr(strtolower($package->getFilename()), -7) === '.tar.gz')
				{
					if (in_array(substr($package, 0, -7), $dirs))
					{
						continue;
					}
					$dirs[] = substr($package, 0, -7);
				}
				elseif (strtolower($package->getExtension()) === 'zip' || strtolower($package->getExtension()) === 'tgz')
				{
					if (in_array(substr($package->getBasename(), 0, -4), $dirs))
					{
						continue;
					}
					$dirs[] = substr($package->getBasename(), 0, -4);
				}

				$packageInfo = getPackageInfo($package->getFilename());
				if (!is_array($packageInfo))
				{
					continue;
				}

				if (!empty($packageInfo))
				{
					$packageInfo['installed_id'] = isset($installed_adds[$packageInfo['id']]) ? $installed_adds[$packageInfo['id']]['id'] : 0;
					$packageInfo['sort_id'] = $sort_id[$packageInfo['type']] ?? $sort_id['unknown'];
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
								$packageInfo['can_emulate_install'] = matchHighestPackageVersion($install->fetch('@for'), $the_version, $reset);
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
							{
								if (!$upgrade->exists('@from') || matchPackageVersion($installed_adds[$packageInfo['id']]['version'], $upgrade->fetch('@from')))
								{
									$packageInfo['can_upgrade'] = true;
									break;
								}
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
								$packageInfo['can_emulate_uninstall'] = matchHighestPackageVersion($uninstall->fetch('@for'), $the_version, $reset);
								$reset = false;
							}
						}
					}

					// Add-on / Modification
					if ($packageInfo['type'] === 'addon' || $packageInfo['type'] === 'modification' || $packageInfo['type'] === 'mod')
					{
						$sort_id['addon']++;
						if ($installed)
						{
							if (!empty($context['available_addon'][$packageInfo['id']]))
							{
								$packages['addon'][strtolower($packageInfo[$sort]) . '_' . $sort_id['addon']] = $packageInfo['id'];
								$context['available_addon'][$packageInfo['id']] = array_merge($context['available_addon'][$packageInfo['id']], $packageInfo);
							}
						}
						else
						{
							$packages['addon'][strtolower($packageInfo[$sort]) . '_' . $sort_id['addon']] = md5($package->getFilename());
							$context['available_addon'][md5($package->getFilename())] = $packageInfo;
						}
					}
					// Avatar package.
					elseif ($packageInfo['type'] === 'avatar')
					{
						$sort_id[$packageInfo['type']]++;
						$packages['avatar'][strtolower($packageInfo[$sort]) . '_' . $sort_id['avatar']] = md5($package->getFilename());
						$context['available_avatar'][md5($package->getFilename())] = $packageInfo;
					}
					// Smiley package.
					elseif ($packageInfo['type'] === 'smiley')
					{
						$sort_id[$packageInfo['type']]++;
						$packages['smiley'][strtolower($packageInfo[$sort]) . '_' . $sort_id['smiley']] = md5($package->getFilename());
						$context['available_smiley'][md5($package->getFilename())] = $packageInfo;
					}
					// Language package.
					elseif ($packageInfo['type'] === 'language')
					{
						$sort_id[$packageInfo['type']]++;
						$packages['language'][strtolower($packageInfo[$sort]) . '_' . $sort_id['language']] = md5($package->getFilename());
						$context['available_language'][md5($package->getFilename())] = $packageInfo;
					}
					// Other stuff.
					else
					{
						$sort_id['unknown']++;
						$packages['unknown'][strtolower($packageInfo[$sort]) . '_' . $sort_id['unknown']] = md5($package->getFilename());
						$context['available_unknown'][md5($package->getFilename())] = $packageInfo;
					}
				}
			}
		}
		catch (\UnexpectedValueException $e)
		{
			// @todo for now do nothing...
		}

		if (isset($this->_req->query->desc))
		{
			krsort($packages[$params]);
		}
		else
		{
			ksort($packages[$params]);
		}

		return $packages[$params];
	}

	/**
	 * Table sorting function used in usort
	 *
	 * @param string[] $a
	 * @param string[] $b
	 *
	 * @return int
	 */
	private function _sort_table_first($a, $b)
	{
		if ($a[0] === $b[0])
		{
			return 0;
		}

		return $a[0] === 'remove_table' ? -1 : 1;
	}
}
