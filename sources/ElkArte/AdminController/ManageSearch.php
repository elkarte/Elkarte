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
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

/**
 * ManageSearch controller admin class.
 *
 * @package Search
 */
class ManageSearch extends \ElkArte\AbstractController
{
	/**
	 * Main entry point for the admin search settings screen.
	 *
	 * What it does:
	 *
	 * - It checks permissions, and it forwards to the appropriate function based on
	 * the given sub-action.
	 * - Defaults to sub-action 'settings'.
	 * - Called by ?action=admin;area=managesearch.
	 * - Requires the admin_forum permission.
	 *
	 * @event integrate_sa_manage_search add new search actions
	 * @uses ManageSearch template.
	 * @uses Search language file.
	 * @see  \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		theme()->getTemplates()->loadLanguageFile('Search');
		theme()->getTemplates()->load('ManageSearch');

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
	 * @event integrate_save_search_settings
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
		{
			$context['search_engines'] = \ElkArte\Util::unserialize($modSettings['additional_search_engines']);
		}

		for ($count = 0; $count < 3; $count++)
		{
			$context['search_engines'][] = array(
				'name' => '',
				'url' => '',
				'separator' => '',
			);
		}

		// A form was submitted.
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_search_settings');

			if (empty($this->_req->post->search_results_per_page))
			{
				$this->_req->post->search_results_per_page = !empty($modSettings['search_results_per_page']) ? $modSettings['search_results_per_page'] : $modSettings['defaultMaxMessages'];
			}

			$new_engines = array();
			foreach ($this->_req->post->engine_name as $id => $searchengine)
			{
				$url = trim(str_replace(array('"', '<', '>'), array('&quot;', '&lt;', '&gt;'), $this->_req->post->engine_url[$id]));
				// If no url, forget it
				if (!empty($searchengine) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL))
				{
					$new_engines[] = array(
						'name' => trim(\ElkArte\Util::htmlspecialchars($searchengine, ENT_COMPAT)),
						'url' => $url,
						'separator' => trim(\ElkArte\Util::htmlspecialchars(!empty($this->_req->post->engine_separator[$id]) ? $this->_req->post->engine_separator[$id] : '+', ENT_COMPAT)),
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
	 *
	 * @event integrate_modify_search_settings
	 */
	private function _settings()
	{
		global $txt, $modSettings;

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
		$searchAPI = new \ElkArte\Search\SearchApiWrapper(!empty($modSettings['search_index']) ? $modSettings['search_index'] : '');
		$searchAPI->searchSettings($config_vars);

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
	 * @event integrate_modify_search_weights
	 * @event integrate_save_search_weights
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
			{
				$changes[$factor] = (int) $this->_req->post->{$factor};
			}

			updateSettings($changes);
		}

		$context['relative_weights'] = array('total' => 0);
		foreach ($factors as $factor)
		{
			$context['relative_weights']['total'] += isset($modSettings[$factor]) ? $modSettings[$factor] : 0;
		}

		foreach ($factors as $factor)
		{
			$context['relative_weights'][$factor] = round(100 * (isset($modSettings[$factor]) ? $modSettings[$factor] : 0) / $context['relative_weights']['total'], 1);
		}

		createToken('admin-msw');
	}

	/**
	 * Edit the search method and search index used.
	 *
	 * What it does:
	 *
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
		$context['fulltext_index'] = false;

		// Load any apis.
		$context['search_apis'] = $this->loadSearchAPIs();

		// Detect whether a fulltext index is set.
		if ($context['supports_fulltext'])
		{
			$fulltext_index = detectFulltextIndex();
		}

		// Creating index, removing or simply changing the one in use?
		if ($this->_req->getQuery('sa', 'trim', '') === 'createfulltext')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			$context['fulltext_index'] = true;
			alterFullTextIndex('{db_prefix}messages', 'body', true);
		}
		elseif ($this->_req->getQuery('sa', 'trim', '') === 'removefulltext' && !empty($fulltext_index))
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			alterFullTextIndex('{db_prefix}messages', $fulltext_index);

			// Go back to the default search method.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'fulltext')
			{
				updateSettings(array(
					'search_index' => '',
				));
			}
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
			{
				updateSettings(array(
					'search_index' => '',
				));
			}
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
		{
			$context['table_info'] = array_merge($table_info_defaults, $db_search->membersTableInfo());
		}
		else
		{
			// Here may be wolves.
			$context['table_info'] = array(
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			);
		}

		// Format the data and index length in human readable form.
		foreach ($context['table_info'] as $type => $size)
		{
			// If it's not numeric then just break.  This database engine doesn't support size.
			if (!is_numeric($size))
			{
				break;
			}

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
	 *
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
			$context['index_settings'] = \ElkArte\Util::unserialize($modSettings['search_custom_index_resume']);
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
		}

		if ($context['step'] !== 0)
		{
			checkSession('request');

			// Admin timeouts are painful when building these long indexes
			$_SESSION['admin_time'] = time();
		}

		// Step 0: let the user determine how they like their index.
		if ($context['step'] === 0)
		{
			$context['sub_template'] = 'create_index';
		}

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
			{
				$context['step'] = 3;
			}
			else
			{
				list ($context['start'], $complete) = removeCommonWordsFromIndex($context['start'], $index_properties[$context['index_settings']['bytes_per_word']]);
				if ($complete)
				{
					$context['step'] = 3;
				}

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
				'sphinx_index_prefix' => rtrim($this->_req->post->sphinx_index_prefix, '/'),
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
			if (empty($modSettings['search_index']) || $modSettings['search_index'] === 'Sphinx')
			{
				// This is included with sphinx
				if (file_exists(SOURCEDIR . '/sphinxapi.php'))
				{
					include_once(SOURCEDIR . '/sphinxapi.php');
					$server = !empty($modSettings['sphinx_searchd_server']) ? $modSettings['sphinx_searchd_server'] : 'localhost';
					$port = !empty($modSettings['sphinx_searchd_port']) ? $modSettings['sphinxql_searchd_port'] : '9312';

					$mySphinx = new SphinxClient();
					$mySphinx->SetServer($server, (int) $port);
					$mySphinx->SetLimits(0, 25);
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
					{
						updateSettings(array('sphinx_searchd_server' => $server, 'sphinx_searchd_port' => $port));
						$context['settings_message'][] = $txt['sphinx_test_passed'];
					}
				}
				else
				{
					$context['settings_message'][] = $txt['sphinx_test_api_missing'];
					$context['error_type'] = 'serious';
				}
			}

			// Try to connect via SphinxQL
			if (empty($modSettings['search_index']) || $modSettings['search_index'] === 'Sphinxql')
			{
				$server = !empty($modSettings['sphinx_searchd_server']) ? $modSettings['sphinx_searchd_server'] : 'localhost';
				$server = $server === 'localhost' ? '127.0.0.1' : $server;
				$port = !empty($modSettings['sphinxql_searchd_port']) ? $modSettings['sphinxql_searchd_port'] : '9306';

				$result = @mysqli_connect($server, '', '', '', (int) $port);
				if ($result === false)
				{
					$context['settings_message'][] = $txt['sphinxql_test_connect_failed'];
					$context['error_type'] = 'serious';
				}
				else
				{
					updateSettings(array('sphinx_searchd_server' => $server, 'sphinxql_searchd_port' => $port));
					$context['settings_message'][] = $txt['sphinxql_test_passed'];
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
		try
		{
			$files = new GlobIterator(SUBSDIR . '/Search/API/*.php', FilesystemIterator::SKIP_DOTS);
			foreach ($files as $file)
			{
				if ($file->isFile())
				{
					$index_name = $file->getBasename('.php');
					$common_name = strtolower($index_name);

					if ($common_name === 'searchapi')
					{
						continue;
					}

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
