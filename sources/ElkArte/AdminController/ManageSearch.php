<?php

/**
 * The admin screen to change the search settings.  Allows for the creation \
 * of search indexes and search weights
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\Search\SearchApiWrapper;
use ElkArte\SettingsForm\SettingsForm;
use Exception;
use FilesystemIterator;
use GlobIterator;
use UnexpectedValueException;

/**
 * ManageSearch controller admin class.
 *
 * @package Search
 */
class ManageSearch extends AbstractController
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
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		Txt::load('Search');
		theme()->getTemplates()->load('ManageSearch');

		$subActions = [
			'settings' => [$this, 'action_searchSettings_display', 'permission' => 'admin_forum'],
			'weights' => [$this, 'action_weight', 'permission' => 'admin_forum'],
			'method' => [$this, 'action_edit', 'permission' => 'admin_forum'],
			'createfulltext' => [$this, 'action_edit', 'permission' => 'admin_forum'],
			'removecustom' => [$this, 'action_edit', 'permission' => 'admin_forum'],
			'removefulltext' => [$this, 'action_edit', 'permission' => 'admin_forum'],
			'createmsgindex' => [$this, 'action_create', 'permission' => 'admin_forum'],
			'managemanticore' => [$this, 'action_managemanticore', 'permission' => 'admin_forum'],
			'managesphinxql' => [$this, 'action_managesphinx', 'permission' => 'admin_forum'],
		];

		// Control for actions
		$action = new Action('manage_search');

		// Default the sub-action to 'edit search method'.  Call integrate_sa_manage_search
		$subAction = $action->initialize($subActions, 'method');

		// Final bits
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['search_settings_title'];

		// Create the tabs
		$context[$context['admin_menu_name']]['object']->prepareTabData([
				'title' => 'manage_search',
				'description' => 'search_settings_desc',
				'prefix' => 'search',
				'help' => 'search']
		);

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
	public function action_searchSettings_display(): void
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		$context['page_title'] = $txt['search_settings_title'];
		$context['sub_template'] = 'show_settings';

		$context['search_engines'] = [];
		if (!empty($modSettings['additional_search_engines']))
		{
			$context['search_engines'] = Util::unserialize($modSettings['additional_search_engines']);
			// Ensure we always have an array here; serialized empty string or other types are normalized.
			if ($context['search_engines'] === false || !is_array($context['search_engines']))
			{
				$context['search_engines'] = [];
			}
		}

		// Ensure it is an array before we append placeholders (avoid [] on strings)
		$context['search_engines'] = (array) $context['search_engines'];
		for ($count = 0; $count < 3; $count++)
		{
			$context['search_engines'][] = [
				'name' => '',
				'url' => '',
				'separator' => '',
			];
		}

		// A form was submitted.
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_search_settings');

			// Build a local copy of values to avoid mutating the request object.
			$values = (array) $this->_req->post;
			if (empty($values['search_results_per_page']))
			{
				$values['search_results_per_page'] = empty($modSettings['search_results_per_page']) ? ($modSettings['defaultMaxMessages'] ?? 0) : $modSettings['search_results_per_page'];
			}

			$new_engines = [];
			$engine_names = $this->_req->getPost('engine_name', null, []);
			$engine_urls = $this->_req->getPost('engine_url', null, []);
			$engine_separators = $this->_req->getPost('engine_separator', null, []);
			foreach ($engine_names as $id => $searchengine)
			{
				$url_raw = $engine_urls[$id] ?? '';
				$url = trim(str_replace(['"', '<', '>'], ['&quot;', '&lt;', '&gt;'], $url_raw));
				// If no url, forget it
				if (empty($searchengine))
				{
					continue;
				}

				if (empty($url))
				{
					continue;
				}

				if (!filter_var($url, FILTER_VALIDATE_URL))
				{
					continue;
				}

				$new_engines[] = [
					'name' => trim(Util::htmlspecialchars($searchengine)),
					'url' => $url,
					'separator' => trim(Util::htmlspecialchars(empty($engine_separators[$id]) ? '+' : $engine_separators[$id])),
				];
			}

			updateSettings([
				'additional_search_engines' => $new_engines === [] ? '' : serialize($new_engines)
			]);

			$settingsForm->setConfigValues($values);
			$settingsForm->save();
			redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Prep the template!
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'managesearch', 'save', 'sa' => 'settings']);
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
		$config_vars = [
			// Permission...
			['permissions', 'search_posts', 'collapsed' => 'true'],
			// Some simple settings.
			['check', 'search_dropdown'],
			['int', 'search_results_per_page'],
			['int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']],
			// Some limitations.
			['int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc'], 6, 'postinput' => $txt['seconds']],
			['title', 'additional_search_engines'],
			['callback', 'external_search_engines'],
		];

		// Perhaps the search method wants to add some settings?
		$searchAPI = new SearchApiWrapper(empty($modSettings['search_index']) ? '' : $modSettings['search_index']);
		$searchAPI->searchSettings($config_vars);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_search_settings', [&$config_vars]);

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
	public function action_weight(): void
	{
		global $txt, $context, $modSettings;

		$context['page_title'] = $txt['search_weights_title'];
		$context['sub_template'] = 'modify_weights';

		$factors = [
			'search_weight_frequency',
			'search_weight_age',
			'search_weight_length',
			'search_weight_subject',
			'search_weight_first_message',
			'search_weight_sticky',
			'search_weight_likes',
		];

		call_integration_hook('integrate_modify_search_weights', [&$factors]);

		// A form was submitted.
		if ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-msw');

			call_integration_hook('integrate_save_search_weights');

			$changes = [];
			foreach ($factors as $factor)
			{
				$changes[$factor] = $this->_req->getPost($factor, 'intval', 0);
			}

			updateSettings($changes);
		}

		$context['relative_weights'] = ['total' => 0];
		foreach ($factors as $factor)
		{
			$context['relative_weights']['total'] += $modSettings[$factor] ?? 0;
		}

		foreach ($factors as $factor)
		{
			$context['relative_weights'][$factor] = round(100 * ($modSettings[$factor] ?? 0) / $context['relative_weights']['total'], 1);
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
	public function action_edit(): void
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
		$sa = $this->_req->getQuery('sa', 'trim', '');
		if ($sa === 'createfulltext')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			alterFullTextIndex('{db_prefix}messages', ['body', 'subject', 'body,subject'], true);
			$fulltext_index = true;
		}
		elseif ($sa === 'removefulltext' && !empty($fulltext_index))
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			alterFullTextIndex('{db_prefix}messages', $fulltext_index);
			$fulltext_index = false;

			// Go back to the default search method.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'fulltext')
			{
				updateSettings([
					'search_index' => '',
				]);
			}
		}
		elseif ($sa === 'removecustom')
		{
			checkSession('get');
			validateToken('admin-msm', 'get');

			drop_log_search_words();

			updateSettings([
				'search_custom_index_config' => '',
				'search_custom_index_resume' => '',
			]);

			// Go back to the default search method.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] === 'custom')
			{
				updateSettings([
					'search_index' => '',
				]);
			}
		}
		elseif ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-msmpost');

			$search_index = $this->_req->getPost('search_index', 'trim|strval', '');
			$allowed_indexes = ['fulltext', 'custom'];
			$valid_index = in_array($search_index, $allowed_indexes, true) || isset($context['search_apis'][$search_index]);

			updateSettings([
				'search_index' => $valid_index ? $search_index : '',
				'search_force_index' => $this->_req->hasPost('search_force_index') ? '1' : '0',
				'search_match_words' => $this->_req->hasPost('search_match_words') ? '1' : '0',
			]);
		}

		$table_info_defaults = [
			'data_length' => 0,
			'index_length' => 0,
			'fulltext_length' => 0,
			'custom_index_length' => 0,
		];

		// Get some info about the messages table, to show its size and index size.
		if (method_exists($db_search, 'membersTableInfo'))
		{
			$context['table_info'] = array_merge($table_info_defaults, $db_search->membersTableInfo());
		}
		else
		{
			// Here may be wolves.
			$context['table_info'] = [
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			];
		}

		// Format the data and index length in human-readable form.
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
		$context['fulltext_index'] = !empty($fulltext_index);

		createToken('admin-msmpost');
		createToken('admin-msm', 'get');
	}

	/**
	 * Get the installed Search API implementations.
	 *
	 * - This function checks for patterns in comments on top of the Search-API files!
	 * - It loads the search API classes if identified.
	 * - This function is used by action_edit to list all installed API implementations.
	 */
	private function loadSearchAPIs(): array
	{
		global $txt;

		$apis = [];
		try
		{
			$files = new GlobIterator(SOURCEDIR . '/ElkArte/Search/API/*.php', FilesystemIterator::SKIP_DOTS);
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

					$apis[$index_name] = [
						'filename' => $file->getFilename(),
						'setting_index' => $index_name,
						'has_template' => in_array($common_name, ['custom', 'fulltext', 'standard']),
						'label' => $index_name && isset($txt['search_index_' . $common_name]) ? str_replace('{managesearch_url}', getUrl('admin', ['action' => 'admin', 'area' => 'managesearch', 'sa' => 'manage' . $common_name]), $txt['search_index_' . $common_name]) : '',
						'desc' => $index_name && isset($txt['search_index_' . $common_name . '_desc']) ? str_replace('{managesearch_url}', getUrl('admin', ['action' => 'admin', 'area' => 'managesearch', 'sa' => 'manage' . $common_name]), $txt['search_index_' . $common_name . '_desc']) : '',
					];
				}
			}
		}
		catch (UnexpectedValueException)
		{
			// @todo for now just passthrough
		}

		return $apis;
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
	 * @uses ManageSearch template, 'create_index_progress', and 'create_index_done'
	 * sub-templates.
	 */
	public function action_create(): void
	{
		global $modSettings, $context, $txt, $db_show_debug;

		// Scotty, we need more time...
		detectServer()->setTimeLimit(600);

		$context[$context['admin_menu_name']]['current_subsection'] = 'method';
		$context['page_title'] = $txt['search_index_custom'];
		$context['sub_template'] = 'create_index_progress';

		$messages_per_batch = 75;

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
			$context['index_settings'] = [];
			$context['start'] = $this->_req->getPost('start', 'intval', 0);
			$context['step'] = $this->_req->getPost('step', 'intval', 1);
		}

		checkSession('request');

		// Admin timeouts are painful when building these long indexes
		$_SESSION['admin_time'] = time();

		require_once(SUBSDIR . '/ManageSearch.subs.php');

		// Logging may cause session issues with many queries
		$old_db_show_debug = $db_show_debug;
		$db_show_debug = false;

		// Step 1: insert all the words.
		if ($context['step'] === 1)
		{
			$context['sub_template'] = 'create_index_progress';
			[$context['start'], $context['step'], $context['percentage']] = createSearchIndex($context['start'], $messages_per_batch);
		}

		// Step 2: removing the words that occur too often and are of no use.
		if ($context['step'] === 2)
		{
			[$context['start'], $complete, $context['percentage']] = removeCommonWordsFromIndex($context['start']);
			if ($complete)
			{
				$context['step'] = 3;
			}

			$context['sub_template'] = 'create_index_progress';
		}

		// Restore previous debug state
		$db_show_debug = $old_db_show_debug;

		// Step 3: everything done.
		if ($context['step'] === 3)
		{
			$context['sub_template'] = 'create_index_done';

			updateSettings(['search_index' => 'custom', 'search_custom_index_config' => serialize($context['index_settings'])]);
			removeSettings('search_custom_index_resume');
		}
	}

	/**
	 * Edit settings related to the sphinxQL search function.
	 *
	 * - Called by ?action=admin;area=managesearch;sa=sphinx.
	 * - Checks if connection to search daemon is possible
	 */
	public function action_managesphinx(): void
	{
		global $txt, $context, $modSettings;

		// Saving the settings
		if ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-mssphinx');
			$this->_saveSphinxConfig();
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

			// Try to connect via SphinxQL
			if (empty($modSettings['search_index']) || $modSettings['search_index'] === 'Sphinxql')
			{
				$this->connectSphinxQL();
			}
		}
		elseif (isset($this->_req->post->createconfig))
		{
			checkSession();
			validateToken('admin-mssphinx');
			$this->_saveSphinxConfig();

			require_once(SUBSDIR . '/ManageSearch.subs.php');

			createSphinxConfig();
		}

		// Setup for the template
		$context[$context['admin_menu_name']]['current_subsection'] = 'managesphinx';
		$context['page_title'] = $txt['search_sphinx'];
		$context['page_description'] = $txt['sphinx_create_config'];
		$context['sub_template'] = 'manage_sphinx';
		createToken('admin-mssphinx');
	}

	/**
	 * Save the form values in modsettings
	 */
	private function _saveSphinxConfig(): void
	{
		updateSettings([
			'sphinx_index_prefix' => rtrim($this->_req->post->sphinx_index_prefix, '/'),
			'sphinx_data_path' => rtrim($this->_req->post->sphinx_data_path, '/'),
			'sphinx_log_path' => rtrim($this->_req->post->sphinx_log_path, '/'),
			'sphinx_stopword_path' => $this->_req->getPost('sphinx_stopword_path', 'trim', ''),
			'sphinx_indexer_mem' => $this->_req->getPost('sphinx_indexer_mem', 'intval', 128),
			'sphinx_searchd_server' => $this->_req->getPost('sphinx_searchd_server', 'trim', 'localhost'),
			'sphinxql_searchd_port' => $this->_req->getPost('sphinxql_searchd_port', 'intval', 0),
			'sphinx_max_results' => $this->_req->getPost('sphinx_max_results', 'intval', 0)
		]);
	}

	/**
	 * Attempt to connect to Sphinx using the preferred QL way
	 */
	public function connectSphinxQL(): void
	{
		global $txt, $modSettings, $context;

		$server = empty($modSettings['sphinx_searchd_server']) ? 'localhost' : $modSettings['sphinx_searchd_server'];
		$server = $server === 'localhost' ? '127.0.0.1' : $server;
		$port = empty($modSettings['sphinxql_searchd_port']) ? '9306' : $modSettings['sphinxql_searchd_port'];

		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$result = mysqli_connect($server, '', '', '', (int) $port);
		}
		catch (Exception)
		{
			$result = false;
		}
		finally
		{
			restore_error_handler();
		}

		if ($result === false)
		{
			$context['settings_message'][] = $txt['sphinxql_test_connect_failed'];
			$context['error_type'] = 'serious';

			return;
		}

		updateSettings(['sphinx_searchd_server' => $server, 'sphinxql_searchd_port' => $port]);
		$context['settings_message'][] = $txt['sphinxql_test_passed'];
	}

	/**
	 * Edit settings related to the Manticore search function.
	 *
	 * - Called by ?action=admin;area=managesearch;sa=manticore.
	 * - Checks if connection to search daemon is possible
	 */
	public function action_managemanticore(): void
	{
		global $txt, $context, $modSettings;

		// Saving the settings
		if ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-msmanticore');
			$this->_saveManticoreConfig();
		}
		// Checking if we can connect?
		elseif (isset($this->_req->post->checkconnect))
		{
			checkSession();
			validateToken('admin-msmanticore');

			// If they have not picked manticore yet, let them know, but we can still check connections
			if (empty($modSettings['search_index']) || stripos($modSettings['search_index'], 'manticore') === false)
			{
				$context['settings_message'][] = $txt['manticore_test_not_selected'];
				$context['error_type'] = 'notice';
			}

			// Try to connect
			if (empty($modSettings['search_index']) || $modSettings['search_index'] === 'Manticore')
			{
				$this->connectManticore();
			}
		}
		elseif (isset($this->_req->post->createconfig))
		{
			checkSession();
			validateToken('admin-msmanticore');
			$this->_saveManticoreConfig();

			require_once(SUBSDIR . '/ManageSearch.subs.php');

			createManticoreConfig();
		}

		// Setup for the template
		$context[$context['admin_menu_name']]['current_subsection'] = 'managemanticore';
		$context['page_title'] = $txt['search_manticore'];
		$context['page_description'] = $txt['manticore_create_config'];
		$context['sub_template'] = 'manage_manticore';
		createToken('admin-msmanticore');
	}

	/**
	 * Save the form values
	 */
	private function _saveManticoreConfig(): void
	{
		updateSettings([
			'manticore_index_prefix' => rtrim($this->_req->post->manticore_index_prefix, '/'),
			'manticore_data_path' => rtrim($this->_req->post->manticore_data_path, '/'),
			'manticore_log_path' => rtrim($this->_req->post->manticore_log_path, '/'),
			'manticore_stopword_path' => $this->_req->getPost('manticore_stopword_path', 'trim', ''),
			'manticore_wordform_path' => $this->_req->getPost('manticore_wordform_path', 'trim', ''),
			'manticore_indexer_mem' => $this->_req->getPost('manticore_indexer_mem', 'intval', 128),
			'manticore_searchd_server' => $this->_req->getPost('manticore_searchd_server', 'trim', 'localhost'),
			'manticore_searchd_port' => $this->_req->getPost('manticore_searchd_port', 'intval', 0),
			'manticore_max_results' => $this->_req->getPost('manticore_max_results', 'intval', 0)
		]);
	}

	/**
	 * Attempt to connect to Manticore Daemon
	 */
	public function connectManticore(): void
	{
		global $txt, $modSettings, $context;

		$server = empty($modSettings['manticore_searchd_server']) ? 'localhost' : $modSettings['manticore_searchd_server'];
		$server = $server === 'localhost' ? '127.0.0.1' : $server;
		$port = empty($modSettings['manticore_searchd_port']) ? '9306' : $modSettings['manticore_searchd_port'];

		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$result = mysqli_connect($server, '', '', '', (int) $port);
		}
		catch (Exception)
		{
			$result = false;
		}
		finally
		{
			restore_error_handler();
		}

		if ($result === false)
		{
			$context['settings_message'][] = $txt['manticore_test_connect_failed'];
			$context['error_type'] = 'serious';

			return;
		}

		updateSettings(['manticore_searchd_server' => $server, 'manticore_searchd_port' => $port]);
		$context['settings_message'][] = $txt['manticore_test_passed'];
	}
}
