<?php

/**
 * The admin screen to change the search settings.  Aloows for the creation \
 * of search indexes and search weights
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.10
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManageSearch controller admin class.
 *
 * @package Search
 */
class ManageSearch_Controller extends Action_Controller
{
	/**
	 * Search settings form
	 * @var Settings_Form
	 */
	protected $_searchSettings;

	/**
	 * Main entry point for the admin search settings screen.
	 *
	 * What it does:
	 * - It checks permissions, and it forwards to the appropriate function based on
	 * the given sub-action.
	 * - Defaults to sub-action 'settings'.
	 * - Called by ?action=admin;area=managesearch.
	 * - Requires the admin_forum permission.
	 *
	 * @uses ManageSearch template.
	 * @uses Search language file.
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		loadLanguage('Search');
		loadTemplate('ManageSearch');

		$subActions = array(
			'settings' => array($this, 'action_searchSettings_display', 'permission' => 'admin_forum'),
			'weights' => array($this, 'action_weight', 'permission' => 'admin_forum'),
			'method' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'createfulltext' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'removecustom' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'removefulltext' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'createmsgindex' => array($this, 'action_create', 'permission' => 'admin_forum'),
			'managesphinx' => array($this, 'action_managesphinx', 'permission' => 'admin_forum'),
			'managesphinxql' => array($this, 'action_managesphinx', 'permission' => 'admin_forum'),
		);

		// Control for actions
		$action = new Action('manage_search');

		// Create the tabs for the template.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['manage_search'],
			'help' => 'search',
			'description' => $txt['search_settings_desc'],
			'tabs' => array(
				'method' => array(
					'description' => $txt['search_method_desc'],
				),
				'weights' => array(
					'description' => $txt['search_weights_desc'],
				),
				'settings' => array(
					'description' => $txt['search_settings_desc'],
				),
			),
		);

		// Default the sub-action to 'edit search method'.  Call integrate_sa_manage_search
		$subAction = $action->initialize($subActions, 'method');

		// Final bits
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['search_settings_title'];

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Edit some general settings related to the search function.
	 *
	 * - Called by ?action=admin;area=managesearch;sa=settings.
	 * - Requires the admin_forum permission.
	 *
	 * @uses ManageSearch template, 'modify_settings' sub-template.
	 */
	public function action_searchSettings_display()
	{
		global $txt, $context, $scripturl, $modSettings;

		// Initialize the form
		$this->_initSearchSettingsForm();

		$config_vars = $this->_searchSettings->settings();

		// Perhaps the search method wants to add some settings?
		require_once(SUBSDIR . '/Search.subs.php');
		$searchAPI = findSearchAPI();
		if (is_callable(array($searchAPI, 'searchSettings')))
			call_user_func_array($searchAPI->searchSettings, array(&$config_vars));

		$context['page_title'] = $txt['search_settings_title'];
		$context['sub_template'] = 'show_settings';

		$context['search_engines'] = array();
		if (!empty($modSettings['additional_search_engines']))
			$context['search_engines'] = Util::unserialize($modSettings['additional_search_engines']);

		for ($count = 0; $count < 3; $count++)
			$context['search_engines'][] = array(
				'name' => '',
				'url' => '',
				'separator' => '',
			);

		// A form was submitted.
		if (isset($_REQUEST['save']))
		{
			checkSession();

			call_integration_hook('integrate_save_search_settings');

			if (empty($_POST['search_results_per_page']))
				$_POST['search_results_per_page'] = !empty($modSettings['search_results_per_page']) ? $modSettings['search_results_per_page'] : $modSettings['defaultMaxMessages'];

			$new_engines = array();
			foreach ($_POST['engine_name'] as $id => $searchengine)
			{
				// If no url, forget it
				if (!empty($_POST['engine_url'][$id]))
				{
					$new_engines[] = array(
						'name' => trim(Util::htmlspecialchars($searchengine, ENT_COMPAT)),
						'url' => trim(Util::htmlspecialchars($_POST['engine_url'][$id], ENT_COMPAT)),
						'separator' => trim(Util::htmlspecialchars(!empty($_POST['engine_separator'][$id]) ? $_POST['engine_separator'][$id] : '+', ENT_COMPAT)),
					);
				}
			}
			updateSettings(array(
				'additional_search_engines' => !empty($new_engines) ? serialize($new_engines) : ''
			));

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Prep the template!
		$context['post_url'] = $scripturl . '?action=admin;area=managesearch;save;sa=settings';
		$context['settings_title'] = $txt['search_settings_title'];

		// We need this for the in-line permissions
		createToken('admin-mp');

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize admin searchSettings form with the existing forum settings
	 * for search.
	 */
	private function _initSearchSettingsForm()
	{
		// This is really quite wanting.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_searchSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_searchSettings->settings($config_vars);
	}

	/**
	 * Retrieve admin search settings
	 */
	private function _settings()
	{
		global $txt;

		// What are we editing anyway?
		$config_vars = array(
				// Permission...
				array('permissions', 'search_posts'),
				// Some simple settings.
				array('check', 'search_dropdown'),
				array('int', 'search_results_per_page'),
				array('int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']),
			'',
				// Some limitations.
				array('int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc'], 6, 'postinput' => $txt['seconds']),
				array('title', 'additional_search_engines'),
				array('callback', 'external_search_engines'),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_search_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * Edit the relative weight of the search factors.
	 *
	 * - Called by ?action=admin;area=managesearch;sa=weights.
	 * - Requires the admin_forum permission.
	 *
	 * @uses ManageSearch template, 'modify_weights' sub-template.
	 */
	public function action_weight()
	{
		global $txt, $context, $modSettings;

		$context['page_title'] = $txt['search_weights_title'];
		$context['sub_template'] = 'modify_weights';

		$factors = array(
			'search_weight_frequency',
			'search_weight_age',
			'search_weight_length',
			'search_weight_subject',
			'search_weight_first_message',
			'search_weight_sticky',
		);

		call_integration_hook('integrate_modify_search_weights', array(&$factors));

		// A form was submitted.
		if (isset($_POST['save']))
		{
			checkSession();
			validateToken('admin-msw');

			call_integration_hook('integrate_save_search_weights');

			$changes = array();
			foreach ($factors as $factor)
				$changes[$factor] = (int) $_POST[$factor];
			updateSettings($changes);
		}

		$context['relative_weights'] = array('total' => 0);
		foreach ($factors as $factor)
			$context['relative_weights']['total'] += isset($modSettings[$factor]) ? $modSettings[$factor] : 0;

		foreach ($factors as $factor)
			$context['relative_weights'][$factor] = round(100 * (isset($modSettings[$factor]) ? $modSettings[$factor] : 0) / $context['relative_weights']['total'], 1);

		createToken('admin-msw');
	}

	/**
	 * Edit the search method and search index used.
	 *
	 * What it does:
	 * - Calculates the size of the current search indexes in use.
	 * - Allows to create and delete a fulltext index on the messages table.
	 * - Allows to delete a custom index (that action_create() created).
	 * - Called by ?action=admin;area=managesearch;sa=method.
	 * - Requires the admin_forum permission.
	 *
	 * @uses ManageSearch template, 'select_search_method' sub-template.
	 */
	public function action_edit()
	{
		global $txt, $context, $modSettings;

		// Need to work with some db search stuffs
		$db_search = db_search();
		require_once(SUBSDIR . '/ManageSearch.subs.php');

		$context[$context['admin_menu_name']]['current_subsection'] = 'method';
		$context['page_title'] = $txt['search_method_title'];
		$context['sub_template'] = 'select_search_method';
		$context['supports_fulltext'] = $db_search->search_support('fulltext');

		// Load any apis.
		$context['search_apis'] = $this->loadSearchAPIs();

		// Detect whether a fulltext index is set.
		if ($context['supports_fulltext'])
			detectFulltextIndex();

		if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'createfulltext')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			$context['fulltext_index'] = 'body';
			alterFullTextIndex('{db_prefix}messages', $context['fulltext_index'], true);
		}
		elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removefulltext' && !empty($context['fulltext_index']))
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			alterFullTextIndex('{db_prefix}messages', $context['fulltext_index']);

			$context['fulltext_index'] = '';

			// Go back to the default search method.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext')
				updateSettings(array(
					'search_index' => '',
				));
		}
		elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removecustom')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			drop_log_search_words();

			updateSettings(array(
				'search_custom_index_config' => '',
				'search_custom_index_resume' => '',
			));

			// Go back to the default search method.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
				updateSettings(array(
					'search_index' => '',
				));
		}
		elseif (isset($_POST['save']))
		{
			checkSession();
			validateToken('admin-msmpost');

			updateSettings(array(
				'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], array('fulltext', 'custom')) && !isset($context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
				'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
				'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
			));
		}

		$table_info_defaults = array(
			'data_length' => 0,
			'index_length' => 0,
			'fulltext_length' => 0,
			'custom_index_length' => 0,
		);

		// Get some info about the messages table, to show its size and index size.
		if (method_exists($db_search, 'membersTableInfo'))
			$context['table_info'] = array_merge($table_info_defaults, $db_search->membersTableInfo());
		else
			// Here may be wolves.
			$context['table_info'] = array(
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			);

		// Format the data and index length in kilobytes.
		foreach ($context['table_info'] as $type => $size)
		{
			// If it's not numeric then just break.  This database engine doesn't support size.
			if (!is_numeric($size))
				break;

			$context['table_info'][$type] = comma_format($context['table_info'][$type] / 1024) . ' ' . $txt['search_method_kilobytes'];
		}

		$context['custom_index'] = !empty($modSettings['search_custom_index_config']);
		$context['partial_custom_index'] = !empty($modSettings['search_custom_index_resume']) && empty($modSettings['search_custom_index_config']);
		$context['double_index'] = !empty($context['fulltext_index']) && $context['custom_index'];

		createToken('admin-msmpost');
		createToken('admin-msm', 'get');
	}

	/**
	 * Create a custom search index for the messages table.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=managesearch;sa=createmsgindex.
	 * - Linked from the action_edit screen.
	 * - Requires the admin_forum permission.
	 * - Depending on the size of the message table, the process is divided in steps.
	 *
	 * @uses ManageSearch template, 'create_index', 'create_index_progress', and 'create_index_done'
	 * sub-templates.
	 */
	public function action_create()
	{
		global $modSettings, $context, $txt, $db_show_debug;

		// Scotty, we need more time...
		@set_time_limit(600);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		$context[$context['admin_menu_name']]['current_subsection'] = 'method';
		$context['page_title'] = $txt['search_index_custom'];

		$messages_per_batch = 50;

		$index_properties = array(
			2 => array(
				'column_definition' => 'small',
				'step_size' => 1000000,
			),
			4 => array(
				'column_definition' => 'medium',
				'step_size' => 1000000,
				'max_size' => 16777215,
			),
			5 => array(
				'column_definition' => 'large',
				'step_size' => 100000000,
				'max_size' => 2000000000,
			),
		);

		// Resume building an index that was not completed
		if (isset($_REQUEST['resume']) && !empty($modSettings['search_custom_index_resume']))
		{
			$context['index_settings'] = Util::unserialize($modSettings['search_custom_index_resume']);
			$context['start'] = (int) $context['index_settings']['resume_at'];
			unset($context['index_settings']['resume_at']);
			$context['step'] = 1;
		}
		else
		{
			$context['index_settings'] = array(
				'bytes_per_word' => isset($_REQUEST['bytes_per_word']) && isset($index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
			);
			$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
			$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;
		}

		if ($context['step'] !== 0)
		{
			checkSession('request');

			// Admin timeouts are painful when building these long indexes
			$_SESSION['admin_time'] = time();
		}

		// Step 0: let the user determine how they like their index.
		if ($context['step'] === 0)
			$context['sub_template'] = 'create_index';

		require_once(SUBSDIR . '/ManageSearch.subs.php');

		// Logging may cause session issues with many queries
		$old_db_show_debug = $db_show_debug;
		$db_show_debug = false;

		// Step 1: insert all the words.
		if ($context['step'] === 1)
		{
			$context['sub_template'] = 'create_index_progress';

			list ($context['start'], $context['step'], $context['percentage']) = createSearchIndex($context['start'], $messages_per_batch, $index_properties[$context['index_settings']['bytes_per_word']]['column_definition'], $context['index_settings']);
		}
		// Step 2: removing the words that occur too often and are of no use.
		elseif ($context['step'] === 2)
		{
			if ($context['index_settings']['bytes_per_word'] < 4)
				$context['step'] = 3;
			else
			{
				list ($context['start'], $complete) = removeCommonWordsFromIndex($context['start'], $index_properties[$context['index_settings']['bytes_per_word']]);
				if ($complete)
					$context['step'] = 3;

				$context['sub_template'] = 'create_index_progress';

				$context['percentage'] = 80 + round($context['start'] / $index_properties[$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
			}
		}

		// Restore previous debug state
		$db_show_debug = $old_db_show_debug;

		// Step 3: everything done.
		if ($context['step'] === 3)
		{
			$context['sub_template'] = 'create_index_done';

			updateSettings(array('search_index' => 'custom', 'search_custom_index_config' => serialize($context['index_settings'])));
			removeSettings('search_custom_index_resume');
		}
	}

	/**
	 * Edit settings related to the sphinx or sphinxQL search function.
	 *
	 * - Called by ?action=admin;area=managesearch;sa=sphinx.
	 * - Checks if connection to search daemon is possible
	 */
	public function action_managesphinx()
	{
		global $txt, $context, $modSettings;

		// Saving the settings
		if (isset($_POST['save']))
		{
			checkSession();
			validateToken('admin-mssphinx');

			updateSettings(array(
				'sphinx_index_prefix' => trim($_POST['sphinx_index_prefix']),
				'sphinx_data_path' => rtrim($_POST['sphinx_data_path'], '/'),
				'sphinx_log_path' => rtrim($_POST['sphinx_log_path'], '/'),
				'sphinx_stopword_path' => $_POST['sphinx_stopword_path'],
				'sphinx_indexer_mem' => (int) $_POST['sphinx_indexer_mem'],
				'sphinx_searchd_server' => $_POST['sphinx_searchd_server'],
				'sphinx_searchd_port' => (int) $_POST['sphinx_searchd_port'],
				'sphinxql_searchd_port' => (int) $_POST['sphinxql_searchd_port'],
				'sphinx_max_results' => (int) $_POST['sphinx_max_results'],
			));
		}
		// Checking if we can connect?
		elseif (isset($_POST['checkconnect']))
		{
			checkSession();
			validateToken('admin-mssphinx');

			// If they have not picked sphinx yet, let them know, but we can still check connections
			if (empty($modSettings['search_index']) || ($modSettings['search_index'] !== 'sphinx' && $modSettings['search_index'] !== 'sphinxql'))
			{
				$context['settings_message'][] = $txt['sphinx_test_not_selected'];
				$context['error_type'] = 'notice';
			}

			// Try to connect via Sphinx API?
			if (!empty($modSettings['search_index']) && ($modSettings['search_index'] === 'sphinx' || empty($modSettings['search_index'])))
			{
				if (@file_exists(SOURCEDIR . '/sphinxapi.php'))
				{
					include_once(SOURCEDIR . '/sphinxapi.php');
					$mySphinx = new SphinxClient();
					$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
					$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results']);
					$mySphinx->SetMatchMode(SPH_MATCH_BOOLEAN);
					$mySphinx->SetSortMode(SPH_SORT_ATTR_ASC, 'id_topic');

					$index = (!empty($modSettings['sphinx_index_prefix']) ? $modSettings['sphinx_index_prefix'] : 'elkarte') . '_index';
					$request = $mySphinx->Query('test', $index);
					if ($request === false)
					{
						$context['settings_message'][] = $txt['sphinx_test_connect_failed'];
						$context['error_type'] = 'serious';
					}
					else
						$context['settings_message'][] = $txt['sphinx_test_passed'];
				}
				else
				{
					$context['settings_message'][] = $txt['sphinx_test_api_missing'];
					$context['error_type'] = 'serious';
				}
			}

			// Try to connect via SphinxQL
			if (!empty($modSettings['search_index']) && ($modSettings['search_index'] === 'sphinxql' || empty($modSettings['search_index'])))
			{
				if (!empty($modSettings['sphinx_searchd_server']) && !empty($modSettings['sphinxql_searchd_port']))
				{
					$result = @mysqli_connect(($modSettings['sphinx_searchd_server'] === 'localhost' ? '127.0.0.1' : $modSettings['sphinx_searchd_server']), '', '', '', (int) $modSettings['sphinxql_searchd_port']);
					if ($result === false)
					{
						$context['settings_message'][] = $txt['sphinxql_test_connect_failed'];
						$context['error_type'] = 'serious';
					}
					else
						$context['settings_message'][] = $txt['sphinxql_test_passed'];
				}
				else
				{
					$context['settings_message'][] = $txt['sphinxql_test_connect_failed'];
					$context['error_type'] = 'serious';
				}
			}
		}
		elseif (isset($_POST['createconfig']))
		{
			checkSession();
			validateToken('admin-mssphinx');
			require_once(SUBSDIR . '/ManageSearch.subs.php');

			createSphinxConfig();
		}

		// Setup for the template
		$context['page_title'] = $txt['search_sphinx'];
		$context['page_description'] = $txt['sphinx_description'];
		$context['sub_template'] = 'manage_sphinx';
		createToken('admin-mssphinx');
	}

	/**
	 * Get the installed Search API implementations.
	 *
	 * - This function checks for patterns in comments on top of the Search-API files!
	 * - In addition to filenames pattern.
	 * - It loads the search API classes if identified.
	 * - This function is used by action_edit to list all installed API implementations.
	 */
	private function loadSearchAPIs()
	{
		global $txt, $scripturl;

		$apis = array();
		$dh = opendir(SUBSDIR);
		if ($dh)
		{
			while (($file = readdir($dh)) !== false)
			{
				if (is_file(SUBSDIR . '/' . $file) && preg_match('~^SearchAPI-([A-Za-z\d_]+)\.class\.php$~', $file, $matches))
				{
					// Check that this is definitely a valid API!
					$fp = fopen(SUBSDIR . '/' . $file, 'rb');
					$header = fread($fp, 4096);
					fclose($fp);

					if (strpos($header, '* SearchAPI-' . $matches[1] . '.class.php') !== false)
					{
						require_once(SUBSDIR . '/' . $file);

						$index_name = strtolower($matches[1]);
						$search_class_name = $index_name . '_search';
						$searchAPI = new $search_class_name();

						// No Support?  NEXT!
						if (!$searchAPI->is_supported)
							continue;

						$apis[$index_name] = array(
							'filename' => $file,
							'setting_index' => $index_name,
							'has_template' => in_array($index_name, array('custom', 'fulltext', 'standard')),
							'label' => $index_name && isset($txt['search_index_' . $index_name]) ? str_replace('{managesearch_url}', $scripturl . '?action=admin;area=managesearch;sa=manage' . $index_name, $txt['search_index_' . $index_name]) : '',
							'desc' => $index_name && isset($txt['search_index_' . $index_name . '_desc']) ? str_replace('{managesearch_url}', $scripturl . '?action=admin;area=managesearch;sa=manage' . $index_name, $txt['search_index_' . $index_name . '_desc']) : '',
						);
					}
				}
			}
		}
		closedir($dh);

		return $apis;
	}
}
