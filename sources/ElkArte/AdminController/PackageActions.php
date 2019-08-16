<?php

/**
 * This file is the main Package Action Manager
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

/**
 * Coordinates the processing for all known package actions
 *
 * @package Packages
 */
class PackageActions extends \ElkArte\AbstractController
{
	/**
	 * Passed actions from parsePackageInfo
	 * @var array
	 */
	protected $_passed_actions;

	/**
	 * Passed base path value for the package location within the temp directory
	 * @var string
	 */
	protected $_base_path;

	/**
	 * Passed value to indicate if this is an install or uninstall pass
	 * @var boolean
	 */
	protected $_uninstalling;

	/**
	 * Passed array of theme paths
	 * @var array
	 */
	protected $_theme_paths;

	/**
	 * Passed and updated array of themes to install in
	 * @var array
	 */
	public $themes_installed = array();

	/**
	 * Current action step of the passed actions
	 * @var array
	 */
	public $thisAction = array();

	/**
	 * Holds the files that will need to be chmod'ed for the package to install
	 * @var array
	 */
	public $chmod_files = array();

	/**
	 * The actions that must be completed to install a package
	 * @var array
	 */
	public $ourActions = array();

	/**
	 * If any of the steps will fail to complete
	 * @var boolean
	 */
	public $has_failure = false;

	/**
	 * Details of what the failure entails
	 * @var string
	 */
	public $failure_details;

	/**
	 * Available during the install phase, holds what when wrong and where
	 * @var array
	 */
	public $failed_steps = array();

	/**
	 * Other themes found that this addon can be installed in
	 * @var array
	 */
	public $themeFinds;

	/**
	 * Created during install for use in addPackageLog
	 * @var array
	 */
	public $credits_tag = array();

	/**
	 * Failed counter
	 * @var int
	 */
	private $_failed_count = 0;

	/**
	 * Current action step of the passed actions
	 * @var array
	 */
	private $_action;

	/**
	 * Current check for a modification add/replace failure for the file being changed
	 * @var boolean
	 */
	private $_failure;

	/**
	 * Holds the last section of the file name
	 * @var string
	 */
	private $_actual_filename;

	/**
	 * Start the Package Actions, here for the abstract only
	 */
	public function action_index()
	{
	}

	/**
	 * Called from the packages.controller as part of the "test" phase
	 *
	 * @param array $actions set of actions as defined by parsePackageInfo
	 * @param boolean $uninstalling Yea or Nay
	 * @param string $base_path base path for the package within the temp directory
	 * @param array $theme_paths
	 */
	public function test_init($actions, $uninstalling, $base_path, $theme_paths)
	{
		// This will hold data about anything that can be installed in other themes.
		$this->themeFinds = array(
			'candidates' => array(),
			'other_themes' => array(),
		);

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
	 * Called from the packages.controller as part of the "install" phase
	 *
	 * @param array $actions set of actions as defined by parsePackageInfo
	 * @param boolean $uninstalling Yea or Nay
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
	 * "controller" for the test install actions
	 */
	public function action_test()
	{
		// Admins-only!
		isAllowedTo('admin_forum');

		// Generic subs for this controller
		require_once(SUBSDIR . '/Package.subs.php');

		// Oh my
		$subActions = array(
			'chmod' => array($this, 'action_chmod'),
			'license' => array($this, 'action_readme'),
			'readme' => array($this, 'action_readme'),
			'redirect' => array($this, 'action_redirect'),
			'error' => array($this, 'action_error'),
			'modification' => array($this, 'action_modification'),
			'code' => array($this, 'action_code'),
			'database' => array($this, 'action_database'),
			'create-dir' => array($this, 'action_create_dir_file'),
			'create-file' => array($this, 'action_create_dir_file'),
			'hook' => array($this, 'action_hook'),
			'credits' => array($this, 'action_credits'),
			'requires' => array($this, 'action_requires'),
			'require-dir' => array($this, 'action_require_dir_file'),
			'require-file' => array($this, 'action_require_dir_file'),
			'move-dir' => array($this, 'action_move_dir_file'),
			'move-file' => array($this, 'action_move_dir_file'),
			'remove-dir' => array($this, 'action_remove_dir_file'),
			'remove-file' => array($this, 'action_remove_dir_file'),
			'skip' => array($this, 'action_skip'),
		);

		// Set up action/subaction stuff.
		$action = new \ElkArte\Action('package_actions_test');

		foreach ($this->_passed_actions as $this->_action)
		{
			// Not failed until proven otherwise.
			$this->_failure = false;
			$this->thisAction = array();

			// Work out exactly which test function we are calling
			if (!array_key_exists($this->_action['type'], $subActions))
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

		if (file_exists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
			$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');
		elseif (file_exists($this->_action['filename']))
			$context[$type] = htmlspecialchars(trim(file_get_contents($this->_action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');
		elseif (file_exists(BOARDDIR . '/packages/temp/' . $this->_action['filename']))
			$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $this->_action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');

		// Fancy or plain
		if (!empty($this->_action['parse_bbc']))
		{
			$bbc_parser = \BBC\ParserWrapper::instance();

			require_once(SUBSDIR . '/Post.subs.php');
			preparsecode($context[$type]);

			$context[$type] = $bbc_parser->parsePackage($context[$type]);
		}
		else
			$context[$type] = nl2br($context[$type]);
	}

	/**
	 * Noted for test, handled in the real install
	 */
	public function action_redirect()
	{
		return;
	}

	/**
	 * Don't know this one or handled outside.
	 */
	public function action_skip()
	{
		return;
	}

	/**
	 * Set the warning message that there is a problem
	 */
	public function action_error()
	{
		global $txt;

		$this->has_failure = true;

		if (isset($this->_action['error_msg']) && isset($this->_action['error_var']))
			$this->failure_details = sprintf($txt['package_will_fail_' . $this->_action['error_msg']], $this->_action['error_var']);
		elseif (isset($this->_action['error_msg']))
			$this->failure_details = isset($txt['package_will_fail_' . $this->_action['error_msg']]) ? $txt['package_will_fail_' . $this->_action['error_msg']] : $this->_action['error_msg'];
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
		if (!file_exists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
		{
			$this->has_failure = true;
			$this->ourActions[] = array(
				'type' => $txt['execute_modification'],
				'action' => \ElkArte\Util::htmlspecialchars(strtr($this->_action['filename'], array(BOARDDIR => '.'))),
				'description' => $txt['package_action_error'],
				'failed' => true,
			);
		}
		else
		{
			$mod_actions = parseModification(@file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']), true, $this->_action['reverse'], $this->_theme_paths);

			if (count($mod_actions) === 1 && isset($mod_actions[0]) && $mod_actions[0]['type'] === 'error' && $mod_actions[0]['filename'] === '-')
				$mod_actions[0]['filename'] = $this->_action['filename'];

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
				if (!in_array($mod_action['type'], array('error', 'result', 'opened', 'saved', 'end', 'missing', 'skipping', 'chmod')))
				{
					$summary = array(
						'type' => $txt['execute_modification'],
						'action' => \ElkArte\Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
						'description' => $mod_action['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'],
						'position' => $mod_action['position'],
						'operation_key' => $operation_key,
						'filename' => $this->_action['filename'],
						'failed' => $mod_action['failed'],
						'ignore_failure' => !empty($mod_action['ignore_failure'])
					);

					if (empty($mod_action['is_custom']))
						$this->ourActions[$this->_actual_filename]['operations'][] = $summary;

					// Themes are under the saved type.
					if (isset($mod_action['is_custom']) && isset($context['theme_actions'][$mod_action['is_custom']]))
						$context['theme_actions'][$mod_action['is_custom']]['actions'][$this->_actual_filename]['operations'][] = $summary;
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
			$this->_actual_filename = strtolower(substr(strrchr($mod_action['filename'], '/'), 1) . '||' . $this->_action['filename']);
		elseif (isset($mod_action['filename']) && preg_match('~([\w]*)/([\w]*)\.template\.php$~', $mod_action['filename'], $matches))
			$this->_actual_filename = strtolower($matches[1] . '/' . $matches[2] . '.template.php||' . $this->_action['filename']);
		else
			$this->_actual_filename = $key;
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
					$this->has_failure = true;

				$this->_failure = true;
				break;
			case 'chmod':
				$this->chmod_files[] = $mod_action['filename'];
				break;
			case 'saved':
				if (!empty($mod_action['is_custom']))
				{
					if (!isset($context['theme_actions'][$mod_action['is_custom']]))
						$context['theme_actions'][$mod_action['is_custom']] = array(
							'name' => $this->_theme_paths[$mod_action['is_custom']]['name'],
							'actions' => array(),
							'has_failure' => $this->_failure,
						);
					else
						$context['theme_actions'][$mod_action['is_custom']]['has_failure'] |= $this->_failure;

					$context['theme_actions'][$mod_action['is_custom']]['actions'][$this->_actual_filename] = array(
						'type' => $txt['execute_modification'],
						'action' => \ElkArte\Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
						'description' => $this->_failure ? $txt['package_action_failure'] : $txt['package_action_success'],
						'failed' => $this->_failure,
					);
				}
				elseif (!isset($this->ourActions[$this->_actual_filename]))
				{
					$this->ourActions[$this->_actual_filename] = array(
						'type' => $txt['execute_modification'],
						'action' => \ElkArte\Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
						'description' => $this->_failure ? $txt['package_action_failure'] : $txt['package_action_success'],
						'failed' => $this->_failure,
					);
				}
				else
				{
					$this->ourActions[$this->_actual_filename]['failed'] |= $this->_failure;
					$this->ourActions[$this->_actual_filename]['description'] = $this->ourActions[$this->_actual_filename]['failed'] ? $txt['package_action_failure'] : $txt['package_action_success'];
				}
				break;
			case 'skipping':
				$this->ourActions[$this->_actual_filename] = array(
					'type' => $txt['execute_modification'],
					'action' => \ElkArte\Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
					'description' => $txt['package_action_skipping']
				);
				break;
			case 'missing':
				if (empty($mod_action['is_custom']))
				{
					$this->has_failure = true;
					$this->ourActions[$this->_actual_filename] = array(
						'type' => $txt['execute_modification'],
						'action' => \ElkArte\Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
						'description' => $txt['package_action_missing'],
						'failed' => true,
					);
				}
				break;
			case 'error':
				$this->ourActions[$this->_actual_filename] = array(
					'type' => $txt['execute_modification'],
					'action' => \ElkArte\Util::htmlspecialchars(strtr($mod_action['filename'], array(BOARDDIR => '.'))),
					'description' => $txt['package_action_error'],
					'failed' => true,
				);
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

		$this->thisAction = array(
			'type' => $txt['execute_code'],
			'action' => \ElkArte\Util::htmlspecialchars($this->_action['filename']),
		);
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

		$this->thisAction = array(
			'type' => $txt['execute_database_changes'],
			'action' => \ElkArte\Util::htmlspecialchars($this->_action['filename']),
		);
	}

	/**
	 * An empty directory or blank file will need to be created
	 * <create-dir /> or <create-file />
	 */
	public function action_create_dir_file()
	{
		global $txt;

		$this->thisAction = array(
			'type' => $txt['package_create'] . ' ' . ($this->_action['type'] === 'create-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => \ElkArte\Util::htmlspecialchars(strtr($this->_action['destination'], array(BOARDDIR => '.')))
		);
	}

	/**
	 * Hooks to add during the install
	 */
	public function action_hook()
	{
		global $txt;

		$this->_action['description'] = !isset($this->_action['hook'], $this->_action['function']) ? $txt['package_action_failure'] : $txt['package_action_success'];

		if (!isset($this->_action['hook'], $this->_action['function']))
			$this->has_failure = true;

		$this->thisAction = array(
			'type' => $this->_action['reverse'] ? $txt['execute_hook_remove'] : $txt['execute_hook_add'],
			'action' => sprintf($txt['execute_hook_action'], \ElkArte\Util::htmlspecialchars($this->_action['hook'])),
		);
	}

	/**
	 * Credits that will be added to the about area
	 */
	public function action_credits()
	{
		global $txt;

		$this->thisAction = array(
			'type' => $txt['execute_credits_add'],
			'action' => sprintf($txt['execute_credits_action'], \ElkArte\Util::htmlspecialchars($this->_action['title'])),
		);
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
			$this->has_failure = true;
		else
		{
			// See if this dependency is installed
			$installed_version = checkPackageDependency($this->_action['id']);

			// Do a version level check (if requested) in the most basic way
			$version_check = (isset($this->_action['version']) ? $installed_version == $this->_action['version'] : true);
		}

		// Set success or failure information
		$this->_action['description'] = ($installed_version && $version_check) ? $txt['package_action_success'] : $txt['package_action_failure'];
		$this->has_failure = !($installed_version && $version_check);
		$this->thisAction = array(
			'type' => $txt['package_requires'],
			'action' => $txt['package_check_for'] . ' ' . $this->_action['id'] . (isset($this->_action['version']) ? (' / ' . ($version_check ? $this->_action['version'] : '<span class="error">' . $this->_action['version'] . '</span>')) : ''),
		);
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
		$this->thisAction = array(
			'type' => $txt['package_extract'] . ' ' . ($this->_action['type'] === 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => \ElkArte\Util::htmlspecialchars(strtr($this->_action['destination'], array(BOARDDIR => '.')))
		);

		// Could this be theme related?
		if (!empty($this->_action['unparsed_destination']))
			$this->_check_theme_actions($this->_action['unparsed_destination']);
	}

	/**
	 * Move an entire directory or a single file.
	 * <move-dir />
	 * <move-file />
	 */
	public function action_move_dir_file()
	{
		global $txt;

		$this->thisAction = array(
			'type' => $txt['package_move'] . ' ' . ($this->_action['type'] === 'move-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => \ElkArte\Util::htmlspecialchars(strtr($this->_action['source'], array(BOARDDIR => '.'))) . ' => ' . \ElkArte\Util::htmlspecialchars(strtr($this->_action['destination'], array(BOARDDIR => '.')))
		);
	}

	/**
	 * Remove a directory and all its file or remove a single file
	 * <remove-dir />
	 * <remove-file />
	 */
	public function action_remove_dir_file()
	{
		global $txt;

		$this->thisAction = array(
			'type' => $txt['package_delete'] . ' ' . ($this->_action['type'] === 'remove-dir' ? $txt['package_tree'] : $txt['package_file']),
			'action' => \ElkArte\Util::htmlspecialchars(strtr($this->_action['filename'], array(BOARDDIR => '.')))
		);

		// Could this be theme related?
		if (!empty($this->_action['unparsed_filename']))
			$this->_check_theme_actions($this->_action['unparsed_filename'], true);
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
			$theme_action = !empty($this->_action['theme_action']) && in_array($this->_action['theme_action'], array('no', 'yes', 'auto')) ? $this->_action['theme_action'] : 'auto';

			// Need to set it?
			if ($set_destination)
				$this->_action['unparsed_destination'] = $this->_action['unparsed_filename'];

			// If it's not auto do we think we have something we can act upon?
			if ($theme_action !== 'auto' && !in_array($matches[1], array('languagedir', 'languages_dir', 'imagesdir', 'themedir')))
				$theme_action = '';
			// ... or if it's auto do we even want to do anything?
			elseif ($theme_action === 'auto' && $matches[1] !== 'imagesdir')
				$theme_action = '';

			// So, we still want to do something?
			if ($theme_action != '')
				$this->themeFinds['candidates'][] = $this->_action;
			// Otherwise is this is going into another theme record it.
			elseif ($matches[1] === 'themes_dir')
				$this->themeFinds['other_themes'][] = strtolower(strtr(parse_path($destination), array('\\' => '/')) . '/' . basename($this->_action['filename']));
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
			return;

		if (isset($this->_action['filename']))
		{
			if ($this->_uninstalling)
				$file = in_array($this->_action['type'], array('remove-dir', 'remove-file')) ? $this->_action['filename'] : BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename'];
			else
				$file = BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename'];

			if (!file_exists($file) && ($this->thisAction['type'] !== 'Create Tree' && $this->thisAction['type'] !== 'Create File'))
			{
				$this->has_failure = true;

				$this->thisAction += array(
					'description' => $txt['package_action_error'],
					'failed' => true,
				);
			}
		}

		// @todo None given?
		if (empty($this->thisAction['description']))
			$this->thisAction['description'] = isset($this->_action['description']) ? $this->_action['description'] : '';

		$this->ourActions[] = $this->thisAction;
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
		$subActions = array(
			'redirect' => array($this, 'action_redirect2'),
			'modification' => array($this, 'action_modification2'),
			'code' => array($this, 'action_code2'),
			'database' => array($this, 'action_database2'),
			'hook' => array($this, 'action_hook2'),
			'credits' => array($this, 'action_credits2'),
			'skip' => array($this, 'action_skip'),
		);

		// No failures yet
		$this->_failed_count = 0;
		$this->failed_steps = array();

		// Set up action/subaction stuff.
		$action = new \ElkArte\Action('package_actions_install');

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
					$this->failed_steps[] = array(
						'file' => $action['filename'],
						'large_step' => $this->_failed_count,
						'sub_step' => $key,
						'theme' => 1,
					);

				// Gather the themes we installed into.
				if (!empty($this->_action['is_custom']))
					$this->themes_installed[] = $this->_action['is_custom'];
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

			// Now include the file and be done with it ;).
			if (file_exists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
				require(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']);
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
			$this->credits_tag = array(
				'url' => $this->_action['url'],
				'license' => $this->_action['license'],
				'copyright' => $this->_action['copyright'],
				'title' => $this->_action['title'],
			);
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
				remove_integration_function($this->_action['hook'], $this->_action['function'], $this->_action['include_file']);
			else
				add_integration_function($this->_action['hook'], $this->_action['function'], $this->_action['include_file']);
		}
	}

	/**
	 * Updates the database as defined by the addon db files
	 */
	public function action_database2()
	{
		// Only do the database changes on uninstall if requested.
		if (!empty($this->_action['filename']) && (!$this->_uninstalling || !empty(\ElkArte\HttpReq::instance()->post->do_db_changes)))
		{
			// These can also be there for database changes.
			global $context;

			// Let the file work its magic ;)
			if (file_exists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']))
				require(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename']);
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
			$context['redirect_text'] = !empty($this->_action['filename']) && file_exists(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename'])
				? file_get_contents(BOARDDIR . '/packages/temp/' . $this->_base_path . $this->_action['filename'])
				: ($this->_uninstalling ? $txt['package_uninstall_done'] : $txt['package_installed_done']);
			$context['redirect_timeout'] = $this->_action['redirect_timeout'];

			// Parse out a couple of common urls.
			$urls = array(
				'$boardurl' => $boardurl,
				'$scripturl' => $scripturl,
				'$session_var' => $context['session_var'],
				'$session_id' => $context['session_id'],
			);

			$context['redirect_url'] = strtr($context['redirect_url'], $urls);
		}
	}
}
