<?php

/**
 * This file contains all the screens that relate to search engines.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * ManageSearchEngines admin controller. This class handles all search engines
 * pages in admin panel, forwards to display and allows to change options.
 *
 * @package SearchEngines
 */
class ManageSearchEngines_Controller extends Action_Controller
{
	/**
	 * Entry point for this section.
	 *
	 * @event integrate_sa_manage_search_engines add additonal search engine actions
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		loadLanguage('Search');
		loadTemplate('ManageSearch');

		$subActions = array(
			'editspiders' => array($this, 'action_editspiders', 'permission' => 'admin_forum'),
			'logs' => array($this, 'action_logs', 'permission' => 'admin_forum'),
			'settings' => array($this, 'action_engineSettings_display', 'permission' => 'admin_forum'),
			'spiders' => array($this, 'action_spiders', 'permission' => 'admin_forum'),
			'stats' => array($this, 'action_stats', 'permission' => 'admin_forum'),
		);

		// Control
		$action = new Action('manage_search_engines');

		// Some more tab data.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['search_engines'],
			'description' => $txt['search_engines_description'],
		);

		// Ensure we have a valid subaction. call integrate_sa_manage_search_engines
		$subAction = $action->initialize($subActions, 'stats');

		// Some contextual data for the template.
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['search_engines'];

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * This is the admin settings page for search engines.
	 * 
	 * @event integrate_save_search_engine_settings
	 */
	public function action_engineSettings_display()
	{
		global $context, $txt, $scripturl;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_settings();
		$settingsForm->setConfigVars($config_vars);

		// Set up a message.
		$context['settings_message'] = sprintf($txt['spider_settings_desc'], $scripturl . '?action=admin;area=logs;sa=pruning;' . $context['session_var'] . '=' . $context['session_id']);

		// Make sure it's valid - note that regular members are given id_group = 1 which is reversed in Load.php - no admins here!
		if (isset($this->_req->post->spider_group) && !isset($config_vars['spider_group'][2][$this->_req->post->spider_group]))
			$this->_req->post->spider_group = 0;

		// Setup the template.
		$context['page_title'] = $txt['settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($this->_req->query->save))
		{
			// security checks
			checkSession();

			// notify the interested addons or integrations
			call_integration_hook('integrate_save_search_engine_settings');

			// save the results!
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// make sure to rebuild the cache with updated results
			recacheSpiderNames();

			// We're done with this.
			redirectexit('action=admin;area=sengines;sa=settings');
		}

		// Set up some details for the template.
		$context['post_url'] = $scripturl . '?action=admin;area=sengines;save;sa=settings';
		$context['settings_title'] = $txt['settings'];

		// Do some javascript.
		$javascript_function = '
			function disableFields()
			{
				disabledState = document.getElementById(\'spider_mode\').value == 0;';

		foreach ($config_vars as $variable)
		{
			if ($variable[1] != 'spider_mode')
				$javascript_function .= '
				if (document.getElementById(\'' . $variable[1] . '\'))
					document.getElementById(\'' . $variable[1] . '\').disabled = disabledState;';
		}

		$javascript_function .= '
			}
			disableFields();';
		addInlineJavascript($javascript_function, true);

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

		$config_vars = array(
			// How much detail?
			array('select', 'spider_mode', 'subtext' => $txt['spider_mode_note'], array($txt['spider_mode_off'], $txt['spider_mode_standard'], $txt['spider_mode_high'], $txt['spider_mode_vhigh']), 'onchange' => 'disableFields();'),
			'spider_group' => array('select', 'spider_group', 'subtext' => $txt['spider_group_note'], array($txt['spider_group_none'])),
			array('select', 'show_spider_online', array($txt['show_spider_online_no'], $txt['show_spider_online_summary'], $txt['show_spider_online_detail'], $txt['show_spider_online_detail_admin'])),
		);

		require_once(SUBSDIR . '/SearchEngines.subs.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');

		$groups = getBasicMembergroupData(array('globalmod', 'postgroups', 'protected', 'member'));
		foreach ($groups as $row)
		{
			// Unfortunately, regular members have to be 1 because 0 is for disabled.
			if ($row['id'] == 0)
				$config_vars['spider_group'][2][1] = $row['name'];
			else
				$config_vars['spider_group'][2][$row['id']] = $row['name'];
		}

		// Notify the integration that we're preparing to mess up with search engine settings...
		call_integration_hook('integrate_modify_search_engine_settings', array(&$config_vars));

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
	public function action_spiders()
	{
		global $context, $txt, $scripturl;

		// We'll need to do hard work here.
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
		{
			consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we adding a new one?
		if (!empty($this->_req->post->addSpider))
			return $this->action_editspiders();
		// User pressed the 'remove selection button'.
		elseif (!empty($this->_req->post->removeSpiders) && !empty($this->_req->post->remove) && is_array($this->_req->post->remove))
		{
			checkSession();
			validateToken('admin-ser');

			// Make sure every entry is a proper integer.
			$toRemove = array_map('intval', $this->_req->post->remove);

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
		$listOptions = array(
			'id' => 'spider_list',
			'title' => $txt['spiders'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=sengines;sa=spiders',
			'default_sort_col' => 'name',
			'get_items' => array(
				'function' => 'getSpiders',
			),
			'get_count' => array(
				'function' => 'getNumSpiders',
				'file' => SUBSDIR . '/SearchEngines.subs.php',
			),
			'no_items_label' => $txt['spiders_no_entries'],
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['spider_name'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $scripturl;

							return sprintf('<a href="%1$s?action=admin;area=sengines;sa=editspiders;sid=%2$d">%3$s</a>', $scripturl, $rowData['id_spider'], htmlspecialchars($rowData['spider_name'], ENT_COMPAT, 'UTF-8'));
						},
					),
					'sort' => array(
						'default' => 'spider_name',
						'reverse' => 'spider_name DESC',
					),
				),
				'last_seen' => array(
					'header' => array(
						'value' => $txt['spider_last_seen'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $context, $txt;

							return isset($context['spider_last_seen'][$rowData['id_spider']]) ? standardTime($context['spider_last_seen'][$rowData['id_spider']]) : $txt['spider_last_never'];
						},
					),
				),
				'user_agent' => array(
					'header' => array(
						'value' => $txt['spider_agent'],
					),
					'data' => array(
						'db_htmlsafe' => 'user_agent',
					),
					'sort' => array(
						'default' => 'user_agent',
						'reverse' => 'user_agent DESC',
					),
				),
				'ip_info' => array(
					'header' => array(
						'value' => $txt['spider_ip_info'],
					),
					'data' => array(
						'db_htmlsafe' => 'ip_info',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'ip_info',
						'reverse' => 'ip_info DESC',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id_spider' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=sengines;sa=spiders',
				'token' => 'admin-ser',
			),
			'additional_rows' => array(
				array(
					'class' => 'submitbutton',
					'position' => 'bottom_of_list',
					'value' => '
						<input type="submit" name="removeSpiders" value="' . $txt['spiders_remove_selected'] . '" onclick="return confirm(\'' . $txt['spider_remove_selected_confirm'] . '\');" />
						<input type="submit" name="addSpider" value="' . $txt['spiders_add'] . '" class="right_submit" />
					',
				),
			),
		);

		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'spider_list';
	}

	/**
	 * Here we can add, and edit, spider info!
	 */
	public function action_editspiders()
	{
		global $context, $txt;

		// Some standard stuff.
		$context['id_spider'] = $this->_req->getQuery('sid', 'intval', 0);
		$context['page_title'] = $context['id_spider'] ? $txt['spiders_edit'] : $txt['spiders_add'];
		$context['sub_template'] = 'spider_edit';
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		// Are we saving?
		if (!empty($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-ses');

			// Check the IP range is valid.
			$ips = array();
			$ip_sets = explode(',', $this->_req->post->spider_ip);
			foreach ($ip_sets as $set)
			{
				$test = ip2range(trim($set));
				if (!empty($test))
					$ips[] = $set;
			}
			$ips = implode(',', $ips);

			// Goes in as it is...
			updateSpider($context['id_spider'], $this->_req->post->spider_name, $this->_req->post->spider_agent, $ips);

			// Order by user agent length.
			sortSpiderTable();

			Cache::instance()->remove('spider_search');
			recacheSpiderNames();

			redirectexit('action=admin;area=sengines;sa=spiders');
		}

		// The default is new.
		$context['spider'] = array(
			'id' => 0,
			'name' => '',
			'agent' => '',
			'ip_info' => '',
		);

		// An edit?
		if ($context['id_spider'])
			$context['spider'] = getSpiderDetails($context['id_spider']);

		createToken('admin-ses');
	}

	/**
	 * See what spiders have been up to.
	 * 
	 * @event integrate_list_spider_logs
	 */
	public function action_logs()
	{
		global $context, $txt, $scripturl, $modSettings;

		// Load the template and language just incase.
		loadLanguage('Search');
		loadTemplate('ManageSearch');

		// Did they want to delete some or all entries?
		if ((!empty($this->_req->post->delete_entries) && isset($this->_req->post->older)) || !empty($this->_req->post->removeAll))
		{
			checkSession();
			validateToken('admin-sl');

			$since = $this->_req->getPost('older', 'intval', 0);
			$deleteTime = time() - ($since * 24 * 60 * 60);

			// Delete the entries.
			require_once(SUBSDIR . '/SearchEngines.subs.php');
			removeSpiderOldLogs($deleteTime);
		}

		// Build out the spider log list
		$listOptions = array(
			'id' => 'spider_logs',
			'items_per_page' => 20,
			'title' => $txt['spider_logs'],
			'no_items_label' => $txt['spider_logs_empty'],
			'base_href' => $context['admin_area'] == 'sengines' ? $scripturl . '?action=admin;area=sengines;sa=logs' : $scripturl . '?action=admin;area=logs;sa=spiderlog',
			'default_sort_col' => 'log_time',
			'get_items' => array(
				'function' => 'getSpiderLogs',
			),
			'get_count' => array(
				'function' => 'getNumSpiderLogs',
				'file' => SUBSDIR . '/SearchEngines.subs.php',
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['spider'],
					),
					'data' => array(
						'db' => 'spider_name',
					),
					'sort' => array(
						'default' => 's.spider_name',
						'reverse' => 's.spider_name DESC',
					),
				),
				'log_time' => array(
					'header' => array(
						'value' => $txt['spider_time'],
					),
					'data' => array(
						'function' => function ($rowData) {
							return standardTime($rowData['log_time']);
						},
					),
					'sort' => array(
						'default' => 'sl.id_hit DESC',
						'reverse' => 'sl.id_hit',
					),
				),
				'viewing' => array(
					'header' => array(
						'value' => $txt['spider_viewing'],
					),
					'data' => array(
						'db' => 'url',
					),
				),
			),
			'form' => array(
				'token' => 'admin-sl',
				'href' => $scripturl . '?action=admin;area=sengines;sa=logs',
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['spider_logs_info'],
				),
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="removeAll" value="' . $txt['spider_log_empty_log'] . '" onclick="return confirm(\'' . $txt['spider_log_empty_log_confirm'] . '\');" class="right_submit" />',
				),
			),
		);

		createToken('admin-sl');

		createList($listOptions);

		// Now determine the actions of the URLs.
		if (!empty($context['spider_logs']['rows']))
		{
			$urls = array();
			// Grab the current /url.
			foreach ($context['spider_logs']['rows'] as $k => $row)
			{
				// Feature disabled?
				if (empty($row['data']['viewing']['value']) && isset($modSettings['spider_mode']) && $modSettings['spider_mode'] < 3)
					$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = '<em>' . $txt['spider_disabled'] . '</em>';
				else
					$urls[$k] = array($row['data']['viewing']['value'], -1);
			}

			// Now stick in the new URLs.
			require_once(SUBSDIR . '/Who.subs.php');
			$urls = determineActions($urls, 'whospider_');
			foreach ($urls as $k => $new_url)
				$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = $new_url;
		}

		$context['page_title'] = $txt['spider_logs'];
		$context['sub_template'] = 'show_spider_logs';
	}

	/**
	 * Show the spider statistics.
	 * 
	 * @event integrate_list_spider_stat_list
	 */
	public function action_stats()
	{
		global $context, $txt, $scripturl;

		// We'll need to do hard work here.
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		// Force an update of the stats every 60 seconds.
		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
		{
			consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we cleaning up some old stats?
		if (!empty($this->_req->post->delete_entries) && isset($this->_req->post->older))
		{
			checkSession();
			validateToken('admin-ss');

			$deleteTime = time() - (((int) $this->_req->post->older) * 24 * 60 * 60);

			// Delete the entries.
			removeSpiderOldStats($deleteTime);
		}

		// Prepare the dates for the drop down.
		$date_choices = spidersStatsDates();
		end($date_choices);
		$max_date = key($date_choices);

		// What are we currently viewing?
		$current_date = isset($this->_req->post->new_date) && isset($date_choices[$this->_req->post->new_date]) ? $this->_req->post->new_date : $max_date;

		// Prepare the HTML.
		$date_select = '
			' . $txt['spider_stats_select_month'] . ':
			<select name="new_date" onchange="document.spider_stat_list.submit();">';

		if (empty($date_choices))
			$date_select .= '
				<option></option>';
		else
			foreach ($date_choices as $id => $text)
				$date_select .= '
				<option value="' . $id . '"' . ($current_date == $id ? ' selected="selected"' : '') . '>' . $text . '</option>';

		$date_select .= '
			</select>
			<noscript>
				<input type="submit" name="go" value="' . $txt['go'] . '" class="right_submit" />
			</noscript>';

		// If we manually jumped to a date work out the offset.
		if (isset($this->_req->post->new_date))
		{
			$date_query = sprintf('%04d-%02d-01', substr($current_date, 0, 4), substr($current_date, 4));

			$_REQUEST['start'] = getNumSpiderStats($date_query);
		}

		$listOptions = array(
			'id' => 'spider_stat_list',
			'title' => $txt['spider'] . ' ' . $txt['spider_stats'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=sengines;sa=stats',
			'default_sort_col' => 'stat_date',
			'get_items' => array(
				'function' => 'getSpiderStats',
			),
			'get_count' => array(
				'function' => 'getNumSpiderStats',
				'file' => SUBSDIR . '/SearchEngines.subs.php',
			),
			'no_items_label' => $txt['spider_stats_no_entries'],
			'columns' => array(
				'stat_date' => array(
					'header' => array(
						'value' => $txt['date'],
					),
					'data' => array(
						'db' => 'stat_date',
					),
					'sort' => array(
						'default' => 'stat_date',
						'reverse' => 'stat_date DESC',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['spider_name'],
					),
					'data' => array(
						'db' => 'spider_name',
					),
					'sort' => array(
						'default' => 's.spider_name',
						'reverse' => 's.spider_name DESC',
					),
				),
				'page_hits' => array(
					'header' => array(
						'value' => $txt['spider_stats_page_hits'],
					),
					'data' => array(
						'db' => 'page_hits',
					),
					'sort' => array(
						'default' => 'ss.page_hits',
						'reverse' => 'ss.page_hits DESC',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=sengines;sa=stats',
				'name' => 'spider_stat_list',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => $date_select,
					'style' => 'text-align: right;',
				),
			),
		);

		createToken('admin-ss');

		createList($listOptions);

		$context['sub_template'] = 'show_spider_stats';
	}
}
