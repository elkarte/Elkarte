<?php

/**
 * This file contains all the screens that relate to search engines.
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
use ElkArte\Cache\Cache;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * ManageSearchEngines admin controller. This class handles all search engines
 * pages in admin panel, forwards to display and allows to change options.
 *
 * @package SearchEngines
 */
class ManageSearchEngines extends AbstractController
{
	/**
	 * Entry point for this section.
	 *
	 * @event integrate_sa_manage_search_engines add additonal search engine actions
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		Txt::load('Search');
		theme()->getTemplates()->load('ManageSearch');

		$subActions = [
			'editspiders' => [$this, 'action_editspiders', 'permission' => 'admin_forum'],
			'logs' => [$this, 'action_logs', 'permission' => 'admin_forum'],
			'settings' => [$this, 'action_engineSettings_display', 'permission' => 'admin_forum'],
			'spiders' => [$this, 'action_spiders', 'permission' => 'admin_forum'],
			'stats' => [$this, 'action_stats', 'permission' => 'admin_forum'],
		];

		// Control
		$action = new Action('manage_search_engines');

		// Ensure we have a valid subaction. call integrate_sa_manage_search_engines
		$subAction = $action->initialize($subActions, 'stats');

		// Some contextual data for the template.
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['search_engines'];

		// Some more tab data.
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'search_engines',
			'description' => 'search_engines_description',
		]);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * This is the admin settings page for search engines.
	 *
	 * @event integrate_save_search_engine_settings
	 */
	public function action_engineSettings_display(): void
	{
		global $context, $txt;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_settings();
		$settingsForm->setConfigVars($config_vars);

		// Set up a message.
		$context['settings_message'] = sprintf($txt['spider_settings_desc'], getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'pruning', '{session_data}']));

		// Validate posted spider_group against allowed values (don't mutate the request object)
		$posted_spider_group = $this->_req->getPost('spider_group', 'intval', null);
		if ($posted_spider_group !== null && !isset($config_vars['spider_group'][2][$posted_spider_group]))
		{
			// Force to 0 by passing a local value into the form processing later
			$context['__override_spider_group'] = 0;
		}

		// Setup the template.
		$context['page_title'] = $txt['settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if ($this->_req->hasQuery('save'))
		{
			// security checks
			checkSession();

			// notify the interested addons or integrations
			call_integration_hook('integrate_save_search_engine_settings');

			// save the results!
			$values = (array) $this->_req->post;
			if (isset($context['__override_spider_group']))
			{
				$values['spider_group'] = $context['__override_spider_group'];
			}
			$settingsForm->setConfigValues($values);
			$settingsForm->save();

			// make sure to rebuild the cache with updated results
			recacheSpiderNames();

			// We're done with this.
			redirectexit('action=admin;area=sengines;sa=settings');
		}

		// Set up some details for the template.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'save', 'sa' => 'settings']);
		$context['settings_title'] = $txt['settings'];

		// Do some javascript.
		$javascript_function = '
			function disableFields()
			{
				disabledState = document.getElementById(\'spider_mode\').value == 0;';

		foreach ($config_vars as $variable)
		{
			if ($variable[1] !== 'spider_mode')
			{
				$javascript_function .= '
				if (document.getElementById(\'' . $variable[1] . '\'))
					document.getElementById(\'' . $variable[1] . "').disabled = disabledState;";
			}
		}

		$javascript_function .= '
			}
			disableFields();';

		theme()->addInlineJavascript($javascript_function, true);

		// Prepare the settings...
		$settingsForm->prepare();
	}

	/**
	 * Return configuration settings for search engines
	 *
	 * @event integrate_modify_search_engine_settings
	 */
	private function _settings()
	{
		global $txt;

		$config_vars = [
			// How much detail?
			['select', 'spider_mode', 'subtext' => $txt['spider_mode_note'], [$txt['spider_mode_off'], $txt['spider_mode_standard'], $txt['spider_mode_high'], $txt['spider_mode_vhigh']], 'onchange' => 'disableFields();'],
			'spider_group' => ['select', 'spider_group', 'subtext' => $txt['spider_group_note'], [$txt['spider_group_none']]],
			['check', 'spider_no_guest', 'subtext' => $txt['spider_no_guest_note']],
			['select', 'show_spider_online', [$txt['show_spider_online_no'], $txt['show_spider_online_summary'], $txt['show_spider_online_detail'], $txt['show_spider_online_detail_admin']]],
		];

		require_once(SUBSDIR . '/SearchEngines.subs.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');

		$groups = getBasicMembergroupData(['globalmod', 'postgroups', 'protected', 'member']);
		foreach ($groups as $row)
		{
			// Unfortunately, regular members have to be 1 because 0 is for disabled.
			if ($row['id'] == 0)
			{
				$config_vars['spider_group'][2][1] = $row['name'];
			}
			else
			{
				$config_vars['spider_group'][2][$row['id']] = $row['name'];
			}
		}

		// Notify the integration that we're preparing to mess up with search engine settings...
		call_integration_hook('integrate_modify_search_engine_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Return the search engine settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * View a list of all the spiders we know about.
	 *
	 * @event integrate_list_spider_list
	 */
	public function action_spiders(): void
	{
		global $context, $txt;

		// We'll need to do hard work here.
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
		{
			consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we adding a new one?
		if ($this->_req->hasPost('addSpider'))
		{
			$this->action_editspiders();
			return;
		}

		// User pressed the 'remove selection button'.
		$removeSpiders = $this->_req->hasPost('removeSpiders');
		$toRemovePost = $this->_req->getPost('remove', null, []);
		if ($removeSpiders && !empty($toRemovePost) && is_array($toRemovePost))
		{
			checkSession();
			validateToken('admin-ser');

			// Make sure every entry is a proper integer.
			$toRemove = array_map('intval', $toRemovePost);

			// Delete them all!
			removeSpiders($toRemove);
			Cache::instance()->remove('spider_search');
			recacheSpiderNames();
		}

		// Get the last seen's.
		$context['spider_last_seen'] = spidersLastSeen();

		// Token for the ride
		createToken('admin-ser');

		// Build the list
		$listOptions = [
			'id' => 'spider_list',
			'title' => $txt['spiders'],
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'spiders']),
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => 'getSpiders',
			],
			'get_count' => [
				'function' => 'getNumSpiders',
				'file' => SUBSDIR . '/SearchEngines.subs.php',
			],
			'no_items_label' => $txt['spiders_no_entries'],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['spider_name'],
					],
					'data' => [
						'function' => static fn($rowData) => sprintf('<a href=' . getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'editspiders', 'sid' => $rowData['id_spider']]) . '">%1$s</a>', htmlspecialchars($rowData['spider_name'], ENT_COMPAT, 'UTF-8')),
					],
					'sort' => [
						'default' => 'spider_name',
						'reverse' => 'spider_name DESC',
					],
				],
				'last_seen' => [
					'header' => [
						'value' => $txt['spider_last_seen'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $context, $txt;

							return isset($context['spider_last_seen'][$rowData['id_spider']]) ? standardTime($context['spider_last_seen'][$rowData['id_spider']]) : $txt['spider_last_never'];
						},
					],
				],
				'user_agent' => [
					'header' => [
						'value' => $txt['spider_agent'],
					],
					'data' => [
						'db_htmlsafe' => 'user_agent',
					],
					'sort' => [
						'default' => 'user_agent',
						'reverse' => 'user_agent DESC',
					],
				],
				'ip_info' => [
					'header' => [
						'value' => $txt['spider_ip_info'],
					],
					'data' => [
						'db_htmlsafe' => 'ip_info',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'ip_info',
						'reverse' => 'ip_info DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
							'params' => [
								'id_spider' => false,
							],
						],
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'spiders']),
				'token' => 'admin-ser',
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton',
					'position' => 'bottom_of_list',
					'value' => '
						<input type="submit" name="removeSpiders" value="' . $txt['spiders_remove_selected'] . '" onclick="return confirm(\'' . $txt['spider_remove_selected_confirm'] . '\');" />
						<input type="submit" name="addSpider" value="' . $txt['spiders_add'] . '" class="right_submit" />
					',
				],
			],
		];

		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'spider_list';
	}

	/**
	 * Here we can add, and edit, spider info!
	 */
	public function action_editspiders(): void
	{
		global $context, $txt;

		// Some standard stuff.
		$context['id_spider'] = $this->_req->getQuery('sid', 'intval', 0);
		$context['page_title'] = $context['id_spider'] ? $txt['spiders_edit'] : $txt['spiders_add'];
		$context['sub_template'] = 'spider_edit';
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		// Are we saving?
		if ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-ses');

			// Check the IP range is valid.
			$ips = [];
			$ip_sets = explode(',', $this->_req->getPost('spider_ip', 'trim', ''));
			foreach ($ip_sets as $set)
			{
				$test = ip2range(trim($set));
				if (!empty($test))
				{
					$ips[] = $set;
				}
			}

			$ips = implode(',', $ips);

			// Goes in as it is...
			$spider_name = $this->_req->getPost('spider_name', 'trim|strval', '');
			$spider_agent = $this->_req->getPost('spider_agent', 'trim|strval', '');
			updateSpider($context['id_spider'], $spider_name, $spider_agent, $ips);

			Cache::instance()->remove('spider_search');
			recacheSpiderNames();

			redirectexit('action=admin;area=sengines;sa=spiders');
		}

		// The default is new.
		$context['spider'] = [
			'id' => 0,
			'name' => '',
			'agent' => '',
			'ip_info' => '',
		];

		// An edit?
		if ($context['id_spider'])
		{
			$context['spider'] = getSpiderDetails($context['id_spider']);
		}

		createToken('admin-ses');
	}

	/**
	 * See what spiders have been up to.
	 *
	 * @event integrate_list_spider_logs
	 */
	public function action_logs(): void
	{
		global $context, $txt, $modSettings;

		// Load the template and language just incase.
		Txt::load('Search');
		theme()->getTemplates()->load('ManageSearch');

		// Did they want to delete some or all entries?
		$deleteEntries = $this->_req->hasPost('delete_entries');
		$removeAll = $this->_req->hasPost('removeAll');
		$since = $this->_req->getPost('older', 'intval', 0);
		if (($deleteEntries && $since) || $removeAll)
		{
			checkSession();
			validateToken('admin-sl');
			$deleteTime = time() - ($since * 24 * 60 * 60);

			// Delete the entries.
			require_once(SUBSDIR . '/SearchEngines.subs.php');
			removeSpiderOldLogs($deleteTime);
		}

		// Build out the spider log list
		$listOptions = [
			'id' => 'spider_logs',
			'items_per_page' => 20,
			'title' => $txt['spider_logs'],
			'no_items_label' => $txt['spider_logs_empty'],
			'base_href' => $context['admin_area'] === 'sengines' ? getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'logs']) : getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'spiderlog']),
			'default_sort_col' => 'log_time',
			'get_items' => [
				'function' => 'getSpiderLogs',
			],
			'get_count' => [
				'function' => 'getNumSpiderLogs',
				'file' => SUBSDIR . '/SearchEngines.subs.php',
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['spider'],
					],
					'data' => [
						'db' => 'spider_name',
					],
					'sort' => [
						'default' => 's.spider_name',
						'reverse' => 's.spider_name DESC',
					],
				],
				'log_time' => [
					'header' => [
						'value' => $txt['spider_time'],
					],
					'data' => [
						'function' => static fn($rowData) => standardTime($rowData['log_time']),
					],
					'sort' => [
						'default' => 'sl.id_hit DESC',
						'reverse' => 'sl.id_hit',
					],
				],
				'viewing' => [
					'header' => [
						'value' => $txt['spider_viewing'],
					],
					'data' => [
						'db' => 'url',
					],
				],
			],
			'form' => [
				'token' => 'admin-sl',
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'logs']),
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => $txt['spider_logs_info'],
				],
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="removeAll" value="' . $txt['spider_log_empty_log'] . '" onclick="return confirm(\'' . $txt['spider_log_empty_log_confirm'] . '\');" class="right_submit" />',
				],
			],
		];

		createToken('admin-sl');
		createList($listOptions);

		// Now determine the actions of the URLs.
		if (!empty($context['spider_logs']['rows']))
		{
			$urls = [];

			// Grab the current /url.
			foreach ($context['spider_logs']['rows'] as $k => $row)
			{
				// Feature disabled?
				if (empty($row['data']['viewing']['value']) && isset($modSettings['spider_mode']) && $modSettings['spider_mode'] < 3)
				{
					$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = '<em>' . $txt['spider_disabled'] . '</em>';
				}
				else
				{
					$urls[$k] = [$row['data']['viewing']['value'], -1];
				}
			}

			// Now stick in the new URLs.
			require_once(SUBSDIR . '/Who.subs.php');
			$urls = determineActions($urls, 'whospider_');
			foreach ($urls as $k => $new_url)
			{
				$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = $new_url;
			}
		}

		$context['page_title'] = $txt['spider_logs'];
		$context['sub_template'] = 'show_spider_logs';
	}

	/**
	 * Show the spider statistics.
	 *
	 * @event integrate_list_spider_stat_list
	 */
	public function action_stats(): void
	{
		global $context, $txt;

		// We'll need to do hard work here.
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		// Force an update of the stats every 60 seconds.
		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
		{
			consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we cleaning up some old stats?
		if ($this->_req->hasPost('delete_entries') && $this->_req->hasPost('older'))
		{
			checkSession();
			validateToken('admin-ss');
			$older = $this->_req->getPost('older', 'intval', 0);
			$deleteTime = time() - ($older * 24 * 60 * 60);

			// Delete the entries.
			removeSpiderOldStats($deleteTime);
		}

		// Prepare the dates for the drop down.
		$date_choices = spidersStatsDates();
		$max_date = array_key_last($date_choices);

		// What are we currently viewing?
		$posted_date = $this->_req->getPost('new_date', 'trim|strval', null);
		$current_date = ($posted_date !== null && isset($date_choices[$posted_date])) ? $posted_date : $max_date;

		// Prepare the HTML.
		$date_select = '
			' . $txt['spider_stats_select_month'] . ':
			<select name="new_date" onchange="document.spider_stat_list.submit();">';

		if (empty($date_choices))
		{
			$date_select .= '
				<option></option>';
		}
		else
		{
			foreach ($date_choices as $id => $text)
			{
				$date_select .= '
				<option value="' . $id . '"' . ($current_date == $id ? ' selected="selected"' : '') . '>' . $text . '</option>';
			}
		}

		$date_select .= '
			</select>
			<noscript>
				<input type="submit" name="go" value="' . $txt['go'] . '" class="right_submit" />
			</noscript>';

		// If we manually jumped to a date work out the offset.
		if ($this->_req->hasPost('new_date'))
		{
			$date_query = sprintf('%04d-%02d-01', substr($current_date, 0, 4), substr($current_date, 4));

			$_REQUEST['start'] = getNumSpiderStats($date_query);
		}

		$listOptions = [
			'id' => 'spider_stat_list',
			'title' => $txt['spider'] . ' ' . $txt['spider_stats'],
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'stats']),
			'default_sort_col' => 'stat_date',
			'get_items' => [
				'function' => 'getSpiderStats',
			],
			'get_count' => [
				'function' => 'getNumSpiderStats',
				'file' => SUBSDIR . '/SearchEngines.subs.php',
			],
			'no_items_label' => $txt['spider_stats_no_entries'],
			'columns' => [
				'stat_date' => [
					'header' => [
						'value' => $txt['date'],
					],
					'data' => [
						'db' => 'stat_date',
					],
					'sort' => [
						'default' => 'stat_date',
						'reverse' => 'stat_date DESC',
					],
				],
				'name' => [
					'header' => [
						'value' => $txt['spider_name'],
					],
					'data' => [
						'db' => 'spider_name',
					],
					'sort' => [
						'default' => 's.spider_name',
						'reverse' => 's.spider_name DESC',
					],
				],
				'page_hits' => [
					'header' => [
						'value' => $txt['spider_stats_page_hits'],
					],
					'data' => [
						'db' => 'page_hits',
					],
					'sort' => [
						'default' => 'ss.page_hits',
						'reverse' => 'ss.page_hits DESC',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'sengines', 'sa' => 'stats']),
				'name' => 'spider_stat_list',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => $date_select,
					'style' => 'text-align: right;',
				],
			],
		];

		createToken('admin-ss');

		createList($listOptions);

		$context['sub_template'] = 'show_spider_stats';
	}
}
