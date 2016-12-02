<?php

/**
 * The admin screen to change the search settings.  Allows for the creation \
 * of search indexes and search weights
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
 * ManageSearch controller admin class.
 *
 * @package Search
 */
class ManageSearch_Controller extends Action_Controller
{

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
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

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
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_search_settings');

			if (empty($this->_req->post->search_results_per_page))
				$this->_req->post->search_results_per_page = !empty($modSettings['search_results_per_page']) ? $modSettings['search_results_per_page'] : $modSettings['defaultMaxMessages'];

			$new_engines = array();
			foreach ($this->_req->post->engine_name as $id => $searchengine)
			{
				$url = trim(str_replace(array('"', '<', '>'), array('&quot;', '&lt;', '&gt;'), $this->_req->post->engine_url[$id]));
				// If no url, forget it
				if (!empty($searchengine) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL))
				{
					$new_engines[] = array(
						'name' => trim(Util::htmlspecialchars($searchengine, ENT_COMPAT)),
						'url' => $url,
						'separator' => trim(Util::htmlspecialchars(!empty($this->_req->post->engine_separator[$id]) ? $this->_req->post->engine_separator[$id] : '+', ENT_COMPAT)),
					);
				}
			}
			updateSettings(array(
				'additional_search_engines' => !empty($new_engines) ? serialize($new_engines) : ''
			));

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Prep the template!
		$context['post_url'] = $scripturl . '?action=admin;area=managesearch;save;sa=settings';
		$context['settings_title'] = $txt['search_settings_title'];

		$settingsForm->prepare();
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

		// Perhaps the search method wants to add some settings?
		$search = new \ElkArte\Search\Search();
		$searchAPI = $search->findSearchAPI();

		if (is_callable(array($searchAPI, 'searchSettings')))
			call_user_func_array($searchAPI->searchSettings);

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
			'search_weight_likes',
		);

		call_integration_hook('integrate_modify_search_weights', array(&$factors));

		// A form was submitted.
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-msw');

			call_integration_hook('integrate_save_search_weights');

			$changes = array();
			foreach ($factors as $factor)
				$changes[$factor] = (int) $this->_req->post->{$factor};

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

		// Creating index, removing or simply changing the one in use?
		if ($this->_req->getQuery('sa', 'trim', '') === 'createfulltext')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			$context['fulltext_index'] = 'body';
			alterFullTextIndex('{db_prefix}messages', $context['fulltext_index'], true);
		}
		elseif ($this->_req->getQuery('sa', 'trim', '') === 'removefulltext' && !empty($context['fulltext_index']))
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
		elseif ($this->_req->getQuery('sa', 'trim', '') === 'removecustom')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			drop_log_search_words();

			updateSettings(array(
				'search_custom_index_config' => '',
				'search_custom_index_resume' => '',
			));

			// Go back to the default search method.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'custom')
				updateSettings(array(
					'search_index' => '',
				));
		}
		elseif (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-msmpost');

			updateSettings(array(
				'search_index' => empty($this->_req->post->search_index) || (!in_array($this->_req->post->search_index, array('fulltext', 'custom')) && !isset($context['search_apis'][$this->_req->post->search_index])) ? '' : $this->_req->post->search_index,
				'search_force_index' => isset($this->_req->post->search_force_index) ? '1' : '0',
				'search_match_words' => isset($this->_req->post->search_match_words) ? '1' : '0',
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

		// Format the data and index length in human readable form.
		foreach ($context['table_info'] as $type => $size)
		{
			// If it's not numeric then just break.  This database engine doesn't support size.
			if (!is_numeric($size))
				break;

			$context['table_info'][$type] = byte_format($context['table_info'][$type]);
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
		detectServer()->setTimeLimit(600);

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
		if (isset($this->_req->query->resume) && !empty($modSettings['search_custom_index_resume']))
		{
			$context['index_settings'] = Util::unserialize($modSettings['search_custom_index_resume']);
			$context['start'] = (int) $context['index_settings']['resume_at'];
			unset($context['index_settings']['resume_at']);
			$context['step'] = 1;
		}
		else
		{
			$context['index_settings'] = array(
				'bytes_per_word' => isset($this->_req->post->bytes_per_word) && isset($index_properties[$this->_req->post->bytes_per_word]) ? (int) $this->_req->post->bytes_per_word : 2,
			);
			$context['start'] = isset($this->_req->post->start) ? (int) $this->_req->post->start : 0;
			$context['step'] = isset($this->_req->post->step) ? (int) $this->_req->post->step : 0;

			// Admin timeouts are painful when building these long indexes
			if ($_SESSION['admin_time'] + 3300 < time() && $context['step'] >= 1)
				$_SESSION['admin_time'] = time();
		}

		if ($context['step'] !== 0)
			checkSession('request');

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
		if ($context['step'] === 2)
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
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-mssphinx');

			updateSettings(array(
				'sphinx_data_path' => rtrim($this->_req->post->sphinx_data_path, '/'),
				'sphinx_log_path' => rtrim($this->_req->post->sphinx_log_path, '/'),
				'sphinx_stopword_path' => $this->_req->post->sphinx_stopword_path,
				'sphinx_indexer_mem' => (int) $this->_req->post->sphinx_indexer_mem,
				'sphinx_searchd_server' => $this->_req->post->sphinx_searchd_server,
				'sphinx_searchd_port' => (int) $this->_req->post->sphinx_searchd_port,
				'sphinxql_searchd_port' => (int) $this->_req->post->sphinxql_searchd_port,
				'sphinx_max_results' => (int) $this->_req->post->sphinx_max_results,
			));
		}
		// Checking if we can connect?
		elseif (isset($this->_req->post->checkconnect))
		{
			checkSession();
			validateToken('admin-mssphinx');

			// If they have not picked sphinx yet, let them know, but we can still check connections
			if (empty($modSettings['search_index']) || stripos($modSettings['search_index'], 'sphinx') === false)
			{
				$context['settings_message'][] = $txt['sphinx_test_not_selected'];
				$context['error_type'] = 'notice';
			}

			// Try to connect via Sphinx API?
			if (!empty($modSettings['search_index']) && (stripos($modSettings['search_index'], 'sphinx_') === 0 || empty($modSettings['search_index'])))
			{
				if (@file_exists(SOURCEDIR . '/sphinxapi.php'))
				{
					include_once(SOURCEDIR . '/sphinxapi.php');
					$mySphinx = new SphinxClient();
					$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
					$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results']);
					$mySphinx->SetMatchMode(SPH_MATCH_BOOLEAN);
					$mySphinx->SetSortMode(SPH_SORT_ATTR_ASC, 'id_topic');

					$request = $mySphinx->Query('test', 'elkarte_index');
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
			if (!empty($modSettings['search_index']) && (stripos($modSettings['search_index'], 'sphinxql_') === 0 || empty($modSettings['search_index'])))
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
		elseif (isset($this->_req->post->createconfig))
		{
			checkSession();
			validateToken('admin-mssphinx');
			require_once(SUBSDIR . '/ManageSearch.subs.php');

			createSphinxConfig();
		}

		// Setup for the template
		$context[$context['admin_menu_name']]['current_subsection'] = 'managesphinx';
		$context['page_title'] = $txt['search_sphinx'];
		$context['page_description'] = $txt['sphinx_description'];
		$context['sub_template'] = 'manage_sphinx';
		createToken('admin-mssphinx');
	}

	/**
	 * Get the installed Search API implementations.
	 *
	 * - This function checks for patterns in comments on top of the Search-API files!
	 * - It loads the search API classes if identified.
	 * - This function is used by action_edit to list all installed API implementations.
	 */
	private function loadSearchAPIs()
	{
		global $txt, $scripturl;

		$apis = array();
		$search = new \ElkArte\Search\Search();

		try
		{
			$files = new GlobIterator(SUBSDIR . '/Search/API/*.php', FilesystemIterator::SKIP_DOTS);
			foreach ($files as $file)
			{
				if ($file->isFile())
				{
					$index_name = $file->getBasename('.php');
					$common_name = strtolower($index_name);

					if ($common_name == 'searchapi')
						continue;

					$searchAPI = $search->findSearchAPI($common_name);

					$apis[$index_name] = array(
						'filename' => $file->getFilename(),
						'setting_index' => $index_name,
						'has_template' => in_array($common_name, array('custom', 'fulltext', 'standard')),
						'label' => $index_name && isset($txt['search_index_' . $common_name]) ? str_replace('{managesearch_url}', $scripturl . '?action=admin;area=managesearch;sa=manage' . $common_name, $txt['search_index_' . $common_name]) : '',
						'desc' => $index_name && isset($txt['search_index_' . $common_name . '_desc']) ? str_replace('{managesearch_url}', $scripturl . '?action=admin;area=managesearch;sa=manage' . $common_name, $txt['search_index_' . $common_name . '_desc']) : '',
					);
				}
			}
		}
		catch (UnexpectedValueException $e)
		{
			// @todo for now just passthrough
		}

		return $apis;
	}
}
