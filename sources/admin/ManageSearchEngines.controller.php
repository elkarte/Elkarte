<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file contains all the screens that relate to search engines.
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManageSearchEngines admin controller.
 * This class handles all search engines pages in admin panel,
 *  forwards to display and allows to change options.
 */
class ManageSearchEngines_Controller extends Action_Controller
{
	/**
	 * Search engines settings form
	 * @var Settings_Form
	 */
	protected $_engineSettings;

	/**
	* Entry point for this section.
	*
	* @see Action_Controller::action_index()
	*/
	function action_index()
	{
		global $context, $txt;

		isAllowedTo('admin_forum');

		loadLanguage('Search');
		loadTemplate('ManageSearch');

		$subActions = array(
			'editspiders' => array($this, 'action_editspiders'),
			'logs' => array($this, 'action_logs'),
			'settings' => array($this, 'action_engineSettings_display'),
			'spiders' => array($this, 'action_spiders'),
			'stats' => array($this, 'action_stats'),
		);

		call_integration_hook('integrate_manage_search_engines', array(&$subActions));

		// Ensure we have a valid subaction.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'stats';

		// Set up action/subaction stuff.
		$action = new Action();
		$action->initialize($subActions);

		// Some contextual data for the template.
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['search_engines'];

		// Some more tab data.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['search_engines'],
			'description' => $txt['search_engines_description'],
		);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * This is the admin settings page for search engines.
	 */
	function action_engineSettings_display()
	{
		global $context, $txt, $scripturl;

		// initialize the form
		$this->_initEngineSettingsForm();

		$config_vars = $this->_engineSettings->settings();

		// Set up a message.
		$context['settings_message'] = sprintf($txt['spider_settings_desc'], $scripturl . '?action=admin;area=logs;sa=pruning;' . $context['session_var'] . '=' . $context['session_id']);

		// Do some javascript.
		$javascript_function = '
			function disableFields()
			{
				disabledState = document.getElementById(\'spider_mode\').value == 0;';

		foreach ($config_vars as $variable)
			if ($variable[1] != 'spider_mode')
				$javascript_function .= '
				if (document.getElementById(\'' . $variable[1] . '\'))
					document.getElementById(\'' . $variable[1] . '\').disabled = disabledState;';

		$javascript_function .= '
			}
			disableFields();';

		// notify the integration that we're preparing to mess up with search engine settings...
		call_integration_hook('integrate_modify_search_engine_settings', array(&$config_vars));

		require_once(SUBSDIR . '/SearchEngines.subs.php');
		require_once(SUBSDIR . '/Membergroups.subs.php');

		$groups = getBasicMembergroupData(array('globalmod', 'postgroups', 'protected', 'member'));
		foreach ($groups as $row)
			$config_vars['spider_group'][2][$row['id']] = $row['name'];

		// Make sure it's valid - note that regular members are given id_group = 1 which is reversed in Load.php - no admins here!
		if (isset($_POST['spider_group']) && !isset($config_vars['spider_group'][2][$_POST['spider_group']]))
			$_POST['spider_group'] = 0;

		// We'll want this for our easy save.
		require_once(SUBSDIR . '/Settings.class.php');

		// Setup the template.
		$context['page_title'] = $txt['settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($_GET['save']))
		{
			// security checks
			checkSession();

			// notify the interested add-ons or integrations
			call_integration_hook('integrate_save_search_engine_settings', array(&$config_vars));

			// save the results!
			Settings_Form::save_db($config_vars);

			// make sure to rebuild the cache with updated results
			recacheSpiderNames();

			// We're done with this.
			redirectexit('action=admin;area=sengines;sa=settings');
		}

		// Set up some details for the template.
		$context['post_url'] = $scripturl . '?action=admin;area=sengines;save;sa=settings';
		$context['settings_title'] = $txt['settings'];
		addInlineJavascript($javascript_function, true);

		// Prepare the settings...
		Settings_Form::prepare_db($config_vars);
	}

/**
	 * Initialize the form with configuration settings for search engines
	 *
	 * @return array
	 */
	function _initEngineSettingsForm()
	{
		global $txt;

		// This is really quite wanting.
		require_once(SUBSDIR . '/Settings.class.php');

		// Instantiate the form
		$this->_engineSettings = new Settings_Form();

		$config_vars = array(
			// How much detail?
			array('select', 'spider_mode', 'subtext' => $txt['spider_mode_note'], array($txt['spider_mode_off'], $txt['spider_mode_standard'], $txt['spider_mode_high'], $txt['spider_mode_vhigh']), 'onchange' => 'disableFields();'),
			'spider_group' => array('select', 'spider_group', 'subtext' => $txt['spider_group_note'], array($txt['spider_group_none'], $txt['membergroups_members'])),
			array('select', 'show_spider_online', array($txt['show_spider_online_no'], $txt['show_spider_online_summary'], $txt['show_spider_online_detail'], $txt['show_spider_online_detail_admin'])),
		);

		return $this->_engineSettings->settings($config_vars);
	}

	/**
	 * Return configuration settings for search engines
	 * Used by admin search.
	 *
	 * @return array
	 */
	function settings()
	{
		global $txt;

		$config_vars = array(
			// How much detail?
			array('select', 'spider_mode', 'subtext' => $txt['spider_mode_note'], array($txt['spider_mode_off'], $txt['spider_mode_standard'], $txt['spider_mode_high'], $txt['spider_mode_vhigh']), 'onchange' => 'disableFields();'),
			'spider_group' => array('select', 'spider_group', 'subtext' => $txt['spider_group_note'], array($txt['spider_group_none'], $txt['membergroups_members'])),
			array('select', 'show_spider_online', array($txt['show_spider_online_no'], $txt['show_spider_online_summary'], $txt['show_spider_online_detail'], $txt['show_spider_online_detail_admin'])),
		);

		return $config_vars;
	}

	/**
	 * View a list of all the spiders we know about.
	 */
	function action_spiders()
	{
		global $context, $txt, $scripturl;

		$db = database();

		// We'll need to do hard work here.
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
		{
			consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we adding a new one?
		if (!empty($_POST['addSpider']))
			return $this->action_editspiders();
		// User pressed the 'remove selection button'.
		elseif (!empty($_POST['removeSpiders']) && !empty($_POST['remove']) && is_array($_POST['remove']))
		{
			checkSession();
			validateToken('admin-ser');

			// Make sure every entry is a proper integer.
			foreach ($_POST['remove'] as $index => $spider_id)
				$_POST['remove'][(int) $index] = (int) $spider_id;

			// Delete them all!
			$db->query('', '
				DELETE FROM {db_prefix}spiders
				WHERE id_spider IN ({array_int:remove_list})',
				array(
					'remove_list' => $_POST['remove'],
				)
			);
			$db->query('', '
				DELETE FROM {db_prefix}log_spider_hits
				WHERE id_spider IN ({array_int:remove_list})',
				array(
					'remove_list' => $_POST['remove'],
				)
			);
			$db->query('', '
				DELETE FROM {db_prefix}log_spider_stats
				WHERE id_spider IN ({array_int:remove_list})',
				array(
					'remove_list' => $_POST['remove'],
				)
			);

			cache_put_data('spider_search', null, 300);
			recacheSpiderNames();
		}

		// Get the last seens.
		$request = $db->query('', '
			SELECT id_spider, MAX(last_seen) AS last_seen_time
			FROM {db_prefix}log_spider_stats
			GROUP BY id_spider',
			array(
			)
		);

		$context['spider_last_seen'] = array();
		while ($row = $db->fetch_assoc($request))
			$context['spider_last_seen'][$row['id_spider']] = $row['last_seen_time'];
		$db->free_result($request);

		createToken('admin-ser');
		$listOptions = array(
			'id' => 'spider_list',
			'title' => $txt['spiders'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=sengines;sa=spiders',
			'default_sort_col' => 'name',
			'get_items' => array(
				'function' => array($this, 'list_getSpiders'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getNumSpiders'),
			),
			'no_items_label' => $txt['spiders_no_entries'],
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['spider_name'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl;

							return sprintf(\'<a href="%1$s?action=admin;area=sengines;sa=editspiders;sid=%2$d">%3$s</a>\', $scripturl, $rowData[\'id_spider\'], htmlspecialchars($rowData[\'spider_name\']));
						'),
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
						'function' => create_function('$rowData', '
							global $context, $txt;

							return isset($context[\'spider_last_seen\'][$rowData[\'id_spider\']]) ? standardTime($context[\'spider_last_seen\'][$rowData[\'id_spider\']]) : $txt[\'spider_last_never\'];
						'),
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
					'position' => 'bottom_of_list',
					'value' => '
						<input type="submit" name="removeSpiders" value="' . $txt['spiders_remove_selected'] . '" onclick="return confirm(\'' . $txt['spider_remove_selected_confirm'] . '\');" class="right_submit" />
						<input type="submit" name="addSpider" value="' . $txt['spiders_add'] . '" class="right_submit" />
					',
				),
			),
		);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'spider_list';
	}

	/**
	 * Here we can add, and edit, spider info!
	 */
	function action_editspiders()
	{
		global $context, $txt;

		$db = database();

		// Some standard stuff.
		$context['id_spider'] = !empty($_GET['sid']) ? (int) $_GET['sid'] : 0;
		$context['page_title'] = $context['id_spider'] ? $txt['spiders_edit'] : $txt['spiders_add'];
		$context['sub_template'] = 'spider_edit';

		// Are we saving?
		if (!empty($_POST['save']))
		{
			checkSession();
			validateToken('admin-ses');

			$ips = array();
			// Check the IP range is valid.
			$ip_sets = explode(',', $_POST['spider_ip']);
			foreach ($ip_sets as $set)
			{
				$test = ip2range(trim($set));
				if (!empty($test))
					$ips[] = $set;
			}
			$ips = implode(',', $ips);

			// Goes in as it is...
			if ($context['id_spider'])
				$db->query('', '
					UPDATE {db_prefix}spiders
					SET spider_name = {string:spider_name}, user_agent = {string:spider_agent},
						ip_info = {string:ip_info}
					WHERE id_spider = {int:current_spider}',
					array(
						'current_spider' => $context['id_spider'],
						'spider_name' => $_POST['spider_name'],
						'spider_agent' => $_POST['spider_agent'],
						'ip_info' => $ips,
					)
				);
			else
				$db->insert('insert',
					'{db_prefix}spiders',
					array(
						'spider_name' => 'string', 'user_agent' => 'string', 'ip_info' => 'string',
					),
					array(
						$_POST['spider_name'], $_POST['spider_agent'], $ips,
					),
					array('id_spider')
				);

			// Order by user agent length.
			require_once(SUBSDIR . '/SearchEngines.subs.php');
			sortSpiderTable();

			cache_put_data('spider_search', null, 300);
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
		{
			$request = $db->query('', '
				SELECT id_spider, spider_name, user_agent, ip_info
				FROM {db_prefix}spiders
				WHERE id_spider = {int:current_spider}',
				array(
					'current_spider' => $context['id_spider'],
				)
			);
			if ($row = $db->fetch_assoc($request))
				$context['spider'] = array(
					'id' => $row['id_spider'],
					'name' => $row['spider_name'],
					'agent' => $row['user_agent'],
					'ip_info' => $row['ip_info'],
				);
			$db->free_result($request);
		}

		createToken('admin-ses');
	}

	/**
	 * See what spiders have been up to.
	 */
	function action_logs()
	{
		global $context, $txt, $scripturl, $modSettings;

		$db = database();

		// Load the template and language just incase.
		loadLanguage('Search');
		loadTemplate('ManageSearch');

		// Did they want to delete some entries?
		if (!empty($_POST['delete_entries']) && isset($_POST['older']))
		{
			checkSession();
			validateToken('admin-sl');

			$deleteTime = time() - (((int) $_POST['older']) * 24 * 60 * 60);

			// Delete the entires.
			$db->query('', '
				DELETE FROM {db_prefix}log_spider_hits
				WHERE log_time < {int:delete_period}',
				array(
					'delete_period' => $deleteTime,
				)
			);
		}

		$listOptions = array(
			'id' => 'spider_logs',
			'items_per_page' => 20,
			'title' => $txt['spider_logs'],
			'no_items_label' => $txt['spider_logs_empty'],
			'base_href' => $context['admin_area'] == 'sengines' ? $scripturl . '?action=admin;area=sengines;sa=logs' : $scripturl . '?action=admin;area=logs;sa=spiderlog',
			'default_sort_col' => 'log_time',
			'get_items' => array(
				'function' => array($this, 'list_getSpiderLogs'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getNumSpiderLogs'),
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
						'function' => create_function('$rowData', '
							return standardTime($rowData[\'log_time\']);
						'),
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
					'class' => 'windowbg2',
				),
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="removeAll" value="' . $txt['spider_log_empty_log'] . '" onclick="return confirm(\'' . $txt['spider_log_empty_log_confirm'] . '\');" class="right_submit" />',
				),
			),
		);

		createToken('admin-sl');

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		// Now determine the actions of the URLs.
		if (!empty($context['spider_logs']['rows']))
		{
			$urls = array();
			// Grab the current /url.
			foreach ($context['spider_logs']['rows'] as $k => $row)
			{
				// Feature disabled?
				if (empty($row['viewing']['value']) && isset($modSettings['spider_mode']) && $modSettings['spider_mode'] < 3)
					$context['spider_logs']['rows'][$k]['viewing']['value'] = '<em>' . $txt['spider_disabled'] . '</em>';
				else
					$urls[$k] = array($row['viewing']['value'], -1);
			}

			// Now stick in the new URLs.
			require_once(SUBSDIR . '/Who.subs.php');
			$urls = determineActions($urls, 'whospider_');
			foreach ($urls as $k => $new_url)
			{
				$context['spider_logs']['rows'][$k]['viewing']['value'] = $new_url;
			}
		}

		$context['page_title'] = $txt['spider_logs'];
		$context['sub_template'] = 'show_spider_logs';
	}

	/**
	 * Show the spider statistics.
	 */
	function action_stats()
	{
		global $context, $txt, $scripturl;

		$db = database();

		// We'll need to do hard work here.
		require_once(SUBSDIR . '/SearchEngines.subs.php');

		// Force an update of the stats every 60 seconds.
		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60)
		{
			consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we cleaning up some old stats?
		if (!empty($_POST['delete_entries']) && isset($_POST['older']))
		{
			checkSession();
			validateToken('admin-ss');

			$deleteTime = time() - (((int) $_POST['older']) * 24 * 60 * 60);

			// Delete the entires.
			$db->query('', '
				DELETE FROM {db_prefix}log_spider_stats
				WHERE last_seen < {int:delete_period}',
				array(
					'delete_period' => $deleteTime,
				)
			);
		}

		// Get the earliest and latest dates.
		$request = $db->query('', '
			SELECT MIN(stat_date) AS first_date, MAX(stat_date) AS last_date
			FROM {db_prefix}log_spider_stats',
			array(
			)
		);

		list ($min_date, $max_date) = $db->fetch_row($request);
		$db->free_result($request);

		$min_year = (int) substr($min_date, 0, 4);
		$max_year = (int) substr($max_date, 0, 4);
		$min_month = (int) substr($min_date, 5, 2);
		$max_month = (int) substr($max_date, 5, 2);

		// Prepare the dates for the drop down.
		$date_choices = array();
		for ($y = $min_year; $y <= $max_year; $y++)
			for ($m = 1; $m <= 12; $m++)
			{
				// This doesn't count?
				if ($y == $min_year && $m < $min_month)
					continue;
				if ($y == $max_year && $m > $max_month)
					break;

				$date_choices[$y . $m] = $txt['months_short'][$m] . ' ' . $y;
			}

		// What are we currently viewing?
		$current_date = isset($_REQUEST['new_date']) && isset($date_choices[$_REQUEST['new_date']]) ? $_REQUEST['new_date'] : $max_date;

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
		if (isset($_REQUEST['new_date']))
		{
			$date_query = sprintf('%04d-%02d-01', substr($current_date, 0, 4), substr($current_date, 4));

			$request = $db->query('', '
				SELECT COUNT(*) AS offset
				FROM {db_prefix}log_spider_stats
				WHERE stat_date < {date:date_being_viewed}',
				array(
					'date_being_viewed' => $date_query,
				)
			);
			list ($_REQUEST['start']) = $db->fetch_row($request);
			$db->free_result($request);
		}

		$listOptions = array(
			'id' => 'spider_stat_list',
			'title' => $txt['spider'] . ' ' . $txt['spider_stats'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=sengines;sa=stats',
			'default_sort_col' => 'stat_date',
			'get_items' => array(
				'function' => array($this, 'list_getSpiderStats'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getNumSpiderStats'),
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

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		$context['sub_template'] = 'show_spider_stats';
	}

	/**
	 * Callback function for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string sort
	 * @return array
	 */
	function list_getSpiders($start, $items_per_page, $sort)
	{
		// retrieve spiders within these limits
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		return getSpiders($start, $items_per_page, $sort);
	}

	/**
	 * Callback function for createList()
	 *
	 * @return int
	 */
	function list_getNumSpiders()
	{
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		return getNumSpiders();
	}

	/**
	 * Callback function for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @return array
	 */
	function list_getSpiderLogs($start, $items_per_page, $sort)
	{
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		return getSpiderLogs($start, $items_per_page, $sort);
	}

	/**
	 * Callback function for createList()
	 *
	 * @return int
	 */
	function list_getNumSpiderLogs()
	{
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		return getNumSpiderLogs();
	}

	/**
	 * Callback function for createList()
	 *
	 * @param type $start
	 * @param type $items_per_page
	 * @param type $sort
	 * @return array
	 */
	function list_getSpiderStats($start, $items_per_page, $sort)
	{
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		return getSpiderStats($start, $items_per_page, $sort);
	}

	/**
	 * Callback function for createList()
	 *
	 * @return int
	 */
	function list_getNumSpiderStats()
	{
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		return getNumSpiderStats();
	}
}
