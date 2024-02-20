<?php

/**
 * This file is the main Package Action Manager
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
use ElkArte\FileFunctions;
use ElkArte\HttpReq;
use ElkArte\Util;

/**
 * Coordinates the processing for all known package actions
 *
 * @package Packages
 */
class PackageActions extends AbstractController
{
	/** @var array Passed and updated array of themes to install in */
	public $themes_installed = [];

	/** @var array Current action step of the passed actions */
	public $thisAction = [];

	/** @var array Holds the files that will need to be chmod'ed for the package to install */
	public $chmod_files = [];

	/** @var array The actions that must be completed to install a package */
	public $ourActions = [];

	/** @var bool If any of the steps will fail to complete */
	public $has_failure = false;

	/** @var string Details of what the failure entails */
	public $failure_details;

	/** @var array Available during the install phase, holds what when wrong and where */
	public $failed_steps = [];

	/** @var array Other themes found that this addon can be installed in */
	public $themeFinds;

	/** @var array Created during install for use in addPackageLog */
	public $credits_tag = [];

	/** @var array Passed actions from parsePackageInfo */
	protected $_passed_actions;

	/** @var string Passed base path value for the package location within the temp directory */
	protected $_base_path;

	/** @var bool Passed value to indicate if this is an install or uninstall pass */
	protected $_uninstalling;

	/** @var array Passed array of theme paths */
	protected $_theme_paths;

	/** @var \ElkArte\FileFunctions */
	protected $fileFunc;

	/** @var int Failed counter */
	private $_failed_count = 0;

	/** @var array Current action step of the passed actions */
	private $_action;

	/** @var bool Current check for a modification add/replace failure for the file being changed */
	private $_failure;

	/** @var string Holds the last section of the file name */
	private $_actual_filename;

	/**
	 * Start the Package Actions, here for the abstract only
	 */
	public function action_index()
	{
		// Empty but needed for abstract compliance
	}

	/**
	 * Called from packages controller as part of the "test" phase
	 *
	 * @param array $actions set of actions as defined by parsePackageInfo
	 * @param bool $uninstalling Yea or Nay
	 * @param string $base_path base path for the package within the temp directory
	 * @param array $theme_paths
	 */
	public function test_init($actions, $uninstalling, $base_path, $theme_paths)
	{
		// This will hold data about anything that can be installed in other themes.
		$this->themeFinds = [
			'candidates' => [],
			'other_themes' => [],
		];

		$this->fileFunc = FileFunctions::instance();

		// Pass the vars
		$this->_passed_actions = $actions;
		$this->_uninstalling = $uninstalling;
		$this->_base_path = $base_path;
		$this->_theme_paths = $theme_paths;

		// Run the test install, looking for problems
		$this->action_test();

		// Cleanup the chmod array
		$this->chmod_files = array_unique($this->chmod_files);
		$this->chmod_files = array_values(array_filter($this->chmod_files));
	}

	/**
	 * "controller" for the test installation actions
	 */
	public function action_test()
	{
		// Admins-only!
		isAllowedTo('admin_forum');

		// Generic subs for this controller
		require_once(SUBSDIR . '/Package.subs.php');

		// Oh my
		$subActions = [
			'chmod' => [$this, 'action_chmod'],
			'license' => [$this, 'action_readme'],
			'readme' => [$this, 'action_readme'],
			'redirect' => [$this, 'action_redirect'],
			'error' => [$this, 'action_error'],
			'modification' => [$this, 'action_modification'],
			'code' => [$this, 'action_code'],
			'database' => [$this, 'action_database'],
			'create-dir' => [$this, 'action_create_dir_file'],
			'create-file' => [$this, 'action_create_dir_file'],
			'hook' => [$this, 'action_hook'],
			'credits' => [$this, 'action_credits'],
			'requires' => [$this, 'action_requires'],
			'require-dir' => [$this, 'action_require_dir_file'],
			'require-file' => [$this, 'action_require_dir_file'],
			'move-dir' => [$this, 'action_move_dir_file'],
			'move-file' => [$this, 'action_move_dir_file'],
			'remove-dir' => [$this, 'action_remove_dir_file'],
			'remove-file' => [$this, 'action_remove_dir_file'],
			'skip' => [$this, 'action_skip'],
		];

		// Set up action/subaction stuff.
		$action = new Action('package_actions_test');
		$this->fileFunc = FileFunctions::instance();

		foreach ($this->_passed_actions as $this->_action)
		{
			// Not failed until proven otherwise.
			$this->_failure = false;
			$this->thisAction = [];

			// Work out exactly which test function we are calling
			if (!isset($this->_action['type']) || !array_key_exists($this->_action['type'], $subActions))
			{
				continue;
			}

			$subAction = $action->initialize($subActions, $this->_action['type'], '');

			// Lets just do it!
			$action->dispatch($subAction);

			// Loop collector
			$this->_action_our_actions();
		}
	}

	/**
	 * Test install loop collector
	 */
	private function _action_our_actions()
	{
		global $txt;

		// Now prepare things for the template.
		if (empty($this->thisAction))
		{
			return;
		}

		if (isset($this->_action['filename']))
		{
			$hasFailure = false;

			if ($this->_uninstalling)
			{
				$file = in_array($this->_action['type'], ['remove-dir', 'remove-file']) ? $this->_action['filename'] : BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename'];
			}
			else
			{
				$file = BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename'];
			}

			// Creating, so there must not be an existing one in the package
			if (in_array($this->thisAction['type'], ['Create Tree', 'Create File'])
				&& $this->fileFunc->fileExists($file))
			{
				$hasFailure = true;
			}

			// Move/Extract there must be an existing directory in the package
			if (in_array($this->thisAction['type'], ['Move Tree', 'Extract Tree'])
				&& !$this->fileFunc->isDir($file))
			{
				$hasFailure = true;
			}

			if ($hasFailure)
			{
				$this->has_failure = true;

				$this->thisAction += [
					'description' => $txt['package_action_error'],
					'failed' => true,
				];
			}
		}

		// Over-write existing description if we have an error
		if (empty($this->thisAction['description']))
		{
			$this->thisAction['description'] = $this->_action['description'] ?? '';
		}

		$this->ourActions[] = $this->thisAction;
	}

	/**
	 * Called from the packages.controller as part of the "install" phase
	 *
	 * @param array $actions set of actions as defined by parsePackageInfo
	 * @param bool $uninstalling Yea or Nay
	 * @param string $base_path base path for the package within the temp directory
	 * @param array $theme_paths
	 * @param array $themes_installed
	 */
	public function install_init($actions, $uninstalling, $base_path, $theme_paths, $themes_installed)
	{
		// Pass the vars
		$this->_passed_actions = $actions;
		$this->_uninstalling = $uninstalling;
		$this->_base_path = $base_path;
		$this->_theme_paths = $theme_paths;
		$this->themes_installed = $themes_installed;

		// Give installation a chance
		$this->action_install();
	}

	/**
	 * Called when we are actually installing an addon
	 */
	public function action_install()
	{
		// Admins-only!
		isAllowedTo('admin_forum');

		// Generic subs for this controller
		require_once(SUBSDIR . '/Package.subs.php');

		// Here is what we need to do!
		$subActions = [
			'redirect' => [$this, 'action_redirect2'],
			'modification' => [$this, 'action_modification2'],
			'code' => [$this, 'action_code2'],
			'database' => [$this, 'action_database2'],
			'hook' => [$this, 'action_hook2'],
			'credits' => [$this, 'action_credits2'],
			'skip' => [$this, 'action_skip'],
		];

		// No failures yet
		$this->_failed_count = 0;
		$this->failed_steps = [];

		// Set up action/subaction stuff.
		$action = new Action('package_actions_install');

		foreach ($this->_passed_actions as $this->_action)
		{
			$this->_failed_count++;

			// Work out exactly who it is we are calling. call integrate_sa_packages
			if (!array_key_exists($this->_action['type'], $subActions))
			{
				continue;
			}

			$subAction = $action->initialize($subActions, $this->_action['type'], '');

			// Lets just do it!
			$action->dispatch($subAction);
		}
	}

	/**
	 * Chmod action requested, add it to the list
	 */
	public function action_chmod()
	{
		$this->chmod_files[] = $this->_action['filename'];
	}

	/**
	 * The readme that addon authors always spend quality time producing
	 */
	public function action_readme()
	{
		global $context;

		$type = 'package_' . $this->_action['type'];

		if ($this->fileFunc->fileExists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
		{
			$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']), "\n\r"), ENT_COMPAT);
		}
		elseif ($this->fileFunc->fileExists($this->_action['filename']))
		{
			$context[$type] = htmlspecialchars(trim(file_get_contents($this->_action['filename']), "\n\r"), ENT_COMPAT);
		}
		elseif ($this->fileFunc->fileExists(BOARDDIR . '/packages/temp/' . $this->_action['filename']))
		{
			$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $this->_action['filename']), "\n\r"), ENT_COMPAT);
		}

		// Fancy or plain
		if (!empty($this->_action['parse_bbc']))
		{
			$bbc_parser = ParserWrapper::instance();

			require_once(SUBSDIR . '/Post.subs.php');
			preparsecode($context[$type]);

			$context[$type] = $bbc_parser->parsePackage($context[$type]);
		}
		else
		{
			$context[$type] = nl2br($context[$type]);
		}
	}

	/**
	 * Noted for test, handled in the real install
	 */
	public function action_redirect()
	{
	}

	/**
	 * Don't know this one or handled outside.
	 */
	public function action_skip()
	{
	}

	/**
	 * Set the warning message that there is a problem
	 */
	public function action_error()
	{
		global $txt;

		$this->has_failure = true;

		if (isset($this->_action['error_msg'], $this->_action['error_var']))
		{
			$this->failure_details = sprintf($txt['package_will_fail_' . $this->_action['error_msg']], $this->_action['error_var']);
		}
		elseif (isset($this->_action['error_msg']))
		{
			$this->failure_details = $txt['package_will_fail_' . $this->_action['error_msg']] ?? $this->_action['error_msg'];
		}
	}

	/**
	 * Validates that a file can be found and modified.
	 *
	 * <modification></modification> or <modification />
	 * <search>, <add>, <replace>, before, after, ignore attributes
	 */
	public function action_modification()
	{
		global $context, $txt;

		// Can't find the file, thats a failure !
		if (!$this->fileFunc->fileExists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
		{
			$this->has_failure = true;
			$this->ourActions[] = [
				'type' => $txt['execute_modification'],
				'action' => Util::htmlspecialchars(strtr($this->_action['filename'], [BOARDDIR => '.'])),
				'description' => $txt['package_action_error'],
				'failed' => true,
			];
		}
		else
		{
			$mod_actions = parseModification(@file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']), true, $this->_action['reverse'], $this->_theme_paths);

			if (count($mod_actions) === 1 && isset($mod_actions[0]) && $mod_actions[0]['type'] === 'error' && $mod_actions[0]['filename'] === '-')
			{
				$mod_actions[0]['filename'] = $this->_action['filename'];
			}

			foreach ($mod_actions as $key => $mod_action)
			{
				$this->_get_filename($mod_action, $key);
				$this->_check_modification($mod_action);
			}

			// We need to loop again just to get the operations down correctly.
			foreach ($mod_actions as $operation_key => $mod_action)
			{
				$this->_get_filename($mod_action, $operation_key);

				// We just need it for actual parse changes.
				if (!in_array($mod_action['type'], ['error', 'result', 'opened', 'saved', 'end', 'missing', 'skipping', 'chmod']))
				{
					$summary = [
						'type' => $txt['execute_modification'],
						'action' => Util::htmlspecialchars(strtr($mod_action['filename'], [BOARDDIR => '.'])),
						'description' => $mod_action['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'],
						'position' => $mod_action['position'],
						'operation_key' => $operation_key,
						'filename' => $this->_action['filename'],
						'failed' => $mod_action['failed'],
						'ignore_failure' => !empty($mod_action['ignore_failure'])
					];

					if (empty($mod_action['is_custom']))
					{
						$this->ourActions[$this->_actual_filename]['operations'][] = $summary;
					}

					// Themes are under the saved type.
					if (isset($mod_action['is_custom'], $context['theme_actions'][$mod_action['is_custom']]))
					{
						$context['theme_actions'][$mod_action['is_custom']]['actions'][$this->_actual_filename]['operations'][] = $summary;
					}
				}
			}
		}
	}

	/**
	 * Get the last section of the file name
	 *
	 * @param array $mod_action
	 * @param string $key
	 */
	private function _get_filename($mod_action, $key)
	{
		// Lets get the last section of the file name.
		if (isset($mod_action['filename']) && substr($mod_action['filename'], -13) !== '.template.php')
		{
			$this->_actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $this->_action['filename']);
		}
		elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches))
		{
			$this->_actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php||' . $this->_action['filename']);
		}
		else
		{
			$this->_actual_filename = $key;
		}
	}

	/**
	 * Helper function to parse the results of the parseModification test
	 *
	 * @param array $mod_action
	 */
	private function _check_modification($mod_action)
	{
		global $context, $txt;

		switch ($mod_action['type'])
		{
			case 'opened':
				$this->_failure = false;
				break;
			case 'failure':
				if (empty($mod_action['is_custom']))
				{
					$this->has_failure = true;
				}

				$this->_failure = true;
				break;
			case 'chmod':
				$this->chmod_files[] = $mod_action['filename'];
				break;
			case 'saved':
				if (!empty($mod_action['is_custom']))
				{
					if (!isset($context['theme_actions'][$mod_action['is_custom']]))
					{
						$context['theme_actions'][$mod_action['is_custom']] = [
							'name' => $this->_theme_paths[$mod_action['is_custom']]['name'],
							'actions' => [],
							'has_failure' => $this->_failure,
						];
					}
					else
					{
						$context['theme_actions'][$mod_action['is_custom']]['has_failure'] |= $this->_failure;
					}

					$context['theme_actions'][$mod_action['is_custom']]['actions'][$this->_actual_filename] = [
						'type' => $txt['execute_modification'],
						'action' => Util::htmlspecialchars(strtr($mod_action['filename'], [BOARDDIR => '.'])),
						'description' => $this->_failure ? $txt['package_action_failure'] : $txt['package_action_success'],
						'failed' => $this->_failure,
					];
				}
				elseif (!isset($this->ourActions[$this->_actual_filename]))
				{
					$this->ourActions[$this->_actual_filename] = [
						'type' => $txt['execute_modification'],
						'action' => Util::htmlspecialchars(strtr($mod_action['filename'], [BOARDDIR => '.'])),
						'description' => $this->_failure ? $txt['package_action_failure'] : $txt['package_action_success'],
						'failed' => $this->_failure,
					];
				}
				else
				{
					$this->ourActions[$this->_actual_filename]['failed'] |= $this->_failure;
					$this->ourActions[$this->_actual_filename]['description'] = $this->ourActions[$this->_actual_filename]['failed'] !== 0 ? $txt['package_action_failure'] : $txt['package_action_success'];
				}

				break;
			case 'skipping':
				$this->ourActions[$this->_actual_filename] = [
					'type' => $txt['execute_modification'],
					'action' => Util::htmlspecialchars(strtr($mod_action['filename'], [BOARDDIR => '.'])),
					'description' => $txt['package_action_skipping']
				];
				break;
			case 'missing':
				if (empty($mod_action['is_custom']))
				{
					$this->has_failure = true;
					$this->ourActions[$this->_actual_filename] = [
						'type' => $txt['execute_modification'],
						'action' => Util::htmlspecialchars(strtr($mod_action['filename'], [BOARDDIR => '.'])),
						'description' => $txt['package_action_missing'],
						'failed' => true,
					];
				}

				break;
			case 'error':
				$this->ourActions[$this->_actual_filename] = [
					'type' => $txt['execute_modification'],
					'action' => Util::htmlspecialchars(strtr($mod_action['filename'], [BOARDDIR => '.'])),
					'description' => $txt['package_action_error'],
					'failed' => true,
				];
				break;
		}
	}

	/**
	 * A code file that needs to be run during the install phase
	 *
	 * <code></code> or <code /> (for use with type="file" only)
	 * Filename of a php file to be required.
	 */
	public function action_code()
	{
		global $txt;

		$this->thisAction = [
			'type' => $txt['execute_code'],
			'action' => Util::htmlspecialchars($this->_action['filename']),
		];
	}

	/**
	 * Database actions that need to occur during the install phase
	 *
	 * <database></database> or <database /> (for use with type="file" only)
	 * Filename of a database code to be executed.
	 */
	public function action_database()
	{
		global $txt;

		$this->thisAction = [
			'type' => $txt['execute_database_changes'],
			'action' => Util::htmlspecialchars($this->_action['filename']),
		];
	}

	/**
	 * An empty directory or blank file will need to be created
	 * <create-dir /> or <create-file />
	 */
	public function action_create_dir_file()
	{
		global $txt;

		$this->thisAction = [
			'type' => $txt['package_create'] . ' ' . ($this->_action['type'] === 'create-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => Util::htmlspecialchars(strtr($this->_action['destination'], [BOARDDIR => '.']))
		];
	}

	/**
	 * Hooks to add during the installation
	 */
	public function action_hook()
	{
		global $txt;

		$this->_action['description'] = isset($this->_action['hook'], $this->_action['function']) ? $txt['package_action_success'] : $txt['package_action_failure'];

		if (!isset($this->_action['hook'], $this->_action['function']))
		{
			$this->has_failure = true;
		}

		$this->thisAction = [
			'type' => $this->_action['reverse'] ? $txt['execute_hook_remove'] : $txt['execute_hook_add'],
			'action' => sprintf($txt['execute_hook_action'], Util::htmlspecialchars($this->_action['hook'])),
		];
	}

	/**
	 * Credits that will be added to the about area
	 */
	public function action_credits()
	{
		global $txt;

		$this->thisAction = [
			'type' => $txt['execute_credits_add'],
			'action' => sprintf($txt['execute_credits_action'], Util::htmlspecialchars($this->_action['title'])),
		];
	}

	/**
	 * Checks if this addon relies on other addons to be installed
	 */
	public function action_requries()
	{
		global $txt;

		$installed_version = false;
		$version_check = true;

		// Package missing required values?
		if (!isset($this->_action['id']))
		{
			$this->has_failure = true;
		}
		else
		{
			// See if this dependency is installed
			$installed_version = checkPackageDependency($this->_action['id']);

			// Do a version level check (if requested) in the most basic way
			$version_check = (!isset($this->_action['version']) || $installed_version === $this->_action['version']);
		}

		// Set success or failure information
		$this->_action['description'] = ($installed_version && $version_check) ? $txt['package_action_success'] : $txt['package_action_failure'];
		$this->has_failure = !($installed_version && $version_check);
		$this->thisAction = [
			'type' => $txt['package_requires'],
			'action' => $txt['package_check_for'] . ' ' . $this->_action['id'] . (isset($this->_action['version']) ? (' / ' . ($version_check ? $this->_action['version'] : '<span class="error">' . $this->_action['version'] . '</span>')) : ''),
		];
	}

	/**
	 * Extract Tree or Extract File from the package to a location
	 * - <require-file /> require-file destination
	 * - <require-dir /> require-dir destination
	 */
	public function action_require_dir_file()
	{
		global $txt;

		// Do this one...
		$this->thisAction = [
			'type' => $txt['package_extract'] . ' ' . ($this->_action['type'] === 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => Util::htmlspecialchars(strtr($this->_action['destination'], [BOARDDIR => '.']))
		];

		// Could this be theme related?
		if (!empty($this->_action['unparsed_destination']))
		{
			$this->_check_theme_actions($this->_action['unparsed_destination']);
		}
	}

	/**
	 * Helper function for action_remove_dir_file and action_require_dir_file
	 *
	 * @param string $destination
	 * @param bool $set_destination
	 */
	private function _check_theme_actions($destination, $set_destination = false)
	{
		if (preg_match('~^\$(languagedir|languages_dir|imagesdir|themedir|themes_dir)~i', $destination, $matches))
		{
			// Is the action already stated?
			$theme_action = !empty($this->_action['theme_action']) && in_array($this->_action['theme_action'], ['no', 'yes', 'auto']) ? $this->_action['theme_action'] : 'auto';

			// Need to set it?
			if ($set_destination)
			{
				$this->_action['unparsed_destination'] = $this->_action['unparsed_filename'];
			}

			// If it's not auto do we think we have something we can act upon?
			if ($theme_action !== 'auto' && !in_array($matches[1], ['languagedir', 'languages_dir', 'imagesdir', 'themedir']))
			{
				$theme_action = '';
			}
			// ... or if it's auto do we even want to do anything?
			elseif ($theme_action === 'auto' && $matches[1] !== 'imagesdir')
			{
				$theme_action = '';
			}

			// So, we still want to do something?
			if ($theme_action !== '')
			{
				$this->themeFinds['candidates'][] = $this->_action;
			}
			// Otherwise is this is going into another theme record it.
			elseif ($matches[1] === 'themes_dir')
			{
				$this->themeFinds['other_themes'][] = strtolower(strtr(parse_path($destination), ['\\' => '/']) . '/' . basename($this->_action['filename']));
			}
		}
	}

	/**
	 * Move an entire directory or a single file.
	 * <move-dir />
	 * <move-file />
	 */
	public function action_move_dir_file()
	{
		global $txt;

		$this->thisAction = [
			'type' => $txt['package_move'] . ' ' . ($this->_action['type'] === 'move-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => Util::htmlspecialchars(strtr($this->_action['source'], [BOARDDIR => '.'])) . ' => ' . Util::htmlspecialchars(strtr($this->_action['destination'], [BOARDDIR => '.']))
		];
	}

	/**
	 * Remove a directory and all its file or remove a single file
	 * <remove-dir />
	 * <remove-file />
	 */
	public function action_remove_dir_file()
	{
		global $txt;

		$this->thisAction = [
			'type' => $txt['package_delete'] . ' ' . ($this->_action['type'] === 'remove-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => Util::htmlspecialchars(strtr($this->_action['filename'], [BOARDDIR => '.']))
		];

		// Could this be theme related?
		if (!empty($this->_action['unparsed_filename']))
		{
			$this->_check_theme_actions($this->_action['unparsed_filename'], true);
		}
	}

	/**
	 * Modify one of the core files!  Lets parseModification do the work and
	 * reports on errors
	 */
	public function action_modification2()
	{
		if (!empty($this->_action['filename']))
		{
			$mod_actions = parseModification(file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']), false, $this->_action['reverse'], $this->_theme_paths);

			// Any errors worth noting?
			foreach ($mod_actions as $key => $action)
			{
				if ($this->_action['type'] === 'failure')
				{
					$this->failed_steps[] = [
						'file' => $action['filename'],
						'large_step' => $this->_failed_count,
						'sub_step' => $key,
						'theme' => 1,
					];
				}

				// Gather the themes we installed into.
				if (!empty($this->_action['is_custom']))
				{
					$this->themes_installed[] = $this->_action['is_custom'];
				}
			}
		}
	}

	/**
	 * Runs a code file that was supplied with the addon
	 */
	public function action_code2()
	{
		if ($this->_action['type'] === 'code' && !empty($this->_action['filename']))
		{
			// This is just here as reference for what is available.
			global $context;

			if (FileFunctions::instance()->fileExists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
			{
				require(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']);
			}
		}
	}

	/**
	 * Sets up for installing addon credits to the forum
	 */
	public function action_credits2()
	{
		if ($this->_action['type'] === 'credits')
		{
			// Time to build the billboard
			$this->credits_tag = [
				'url' => $this->_action['url'],
				'license' => $this->_action['license'],
				'copyright' => $this->_action['copyright'],
				'title' => $this->_action['title'],
			];
		}
	}

	/**
	 * Do the actual add or removal of hooks
	 */
	public function action_hook2()
	{
		if (isset($this->_action['hook'], $this->_action['function']))
		{
			if ($this->_action['reverse'])
			{
				remove_integration_function($this->_action['hook'], $this->_action['function'], $this->_action['include_file']);
			}
			else
			{
				add_integration_function($this->_action['hook'], $this->_action['function'], $this->_action['include_file']);
			}
		}
	}

	/**
	 * Updates the database as defined by the addon db files
	 */
	public function action_database2()
	{
		// Only do the database changes on uninstall if requested.
		if (!empty($this->_action['filename']) && (!$this->_uninstalling || !empty(HttpReq::instance()->post->do_db_changes)))
		{
			// These can also be there for database changes.
			global $context;

			// Let the file work its magic ;)
			if (FileFunctions::instance()->fileExists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
			{
				require(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']);
			}
		}
	}

	/**
	 * Redirect to a page, generally the addon settings page but could be anywhere
	 */
	public function action_redirect2()
	{
		global $boardurl, $scripturl, $context, $txt;

		// Handle a redirect...
		if ($this->_action['type'] === 'redirect' && !empty($this->_action['redirect_url']))
		{
			$context['redirect_url'] = $this->_action['redirect_url'];
			if (!empty($this->_action['filename']) && FileFunctions::instance()->fileExists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
			{
				$context['redirect_text'] = file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']);
			}
			else
			{
				$context['redirect_text'] = $this->_uninstalling ? $txt['package_uninstall_done'] : $txt['package_installed_done'];
			}

			$context['redirect_timeout'] = $this->_action['redirect_timeout'];

			// Parse out a couple of common urls.
			$urls = [
				'$boardurl' => $boardurl,
				'$scripturl' => $scripturl,
				'$session_var' => $context['session_var'],
				'$session_id' => $context['session_id'],
			];

			$context['redirect_url'] = strtr($context['redirect_url'], $urls);
		}
	}
}
