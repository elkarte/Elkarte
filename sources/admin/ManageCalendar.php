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
 * This file allows you to manage the calendar.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class ManageCalendar_Controller
{
	/**
	 * Calendar settings form
	 * @var Settings_Form
	 */
	protected $_calendarSettings;

	/**
	 * The main controlling function doesn't have much to do... yet.
	 * Just check permissions and delegate to the rest.
	 *
	 * uses ManageCalendar language file.
	 */
	public function action_index()
	{
		global $context, $txt;

		isAllowedTo('admin_forum');

		// Everything's gonna need this.
		loadLanguage('ManageCalendar');

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		// Default text.
		$context['explain_text'] = $txt['calendar_desc'];

		// Little short on the ground of functions here... but things can and maybe will change...
		$subActions = array(
			'editholiday' => array($this, 'action_editholiday'),
			'holidays' => array($this, 'action_holidays'),
			'settings' => array($this, 'action_calendarSettings_display')
		);

		call_integration_hook('integrate_manage_calendar', array(&$subActions));

		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'holidays';

		// Set up the two tabs here...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['manage_calendar'],
			'help' => 'calendar',
			'description' => $txt['calendar_settings_desc'],
			'tabs' => array(
				'holidays' => array(
					'description' => $txt['manage_holidays_desc'],
				),
				'settings' => array(
					'description' => $txt['calendar_settings_desc'],
				),
			),
		);

		$action = new Action();
		$action->initialize($subActions);
		$action->dispatch($_REQUEST['sa']);
	}

	/**
	 * The function that handles adding, and deleting holiday data
	 */
	public function action_holidays()
	{
		global $scripturl, $txt, $context;

		// Submitting something...
		if (isset($_REQUEST['delete']) && !empty($_REQUEST['holiday']))
		{
			checkSession();
			validateToken('admin-mc');

			foreach ($_REQUEST['holiday'] as $id => $value)
				$_REQUEST['holiday'][$id] = (int) $id;

			// Now the IDs are "safe" do the delete...
			require_once(SUBSDIR . '/Calendar.subs.php');
			removeHolidays($_REQUEST['holiday']);
		}

		createToken('admin-mc');
		$listOptions = array(
			'id' => 'holiday_list',
			'title' => $txt['current_holidays'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=managecalendar;sa=holidays',
			'default_sort_col' => 'name',
			'get_items' => array(
				'file' => SUBSDIR . '/Calendar.subs.php',
				'function' => 'list_getHolidays',
			),
			'get_count' => array(
				'file' => SUBSDIR . '/Calendar.subs.php',
				'function' => 'list_getNumHolidays',
			),
			'no_items_label' => $txt['holidays_no_entries'],
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['holidays_title'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=managecalendar;sa=editholiday;holiday=%1$d">%2$s</a>',
							'params' => array(
								'id_holiday' => false,
								'title' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'title',
						'reverse' => 'title DESC',
					)
				),
				'date' => array(
					'header' => array(
						'value' => $txt['date'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							// Recurring every year or just a single year?
							$year = $rowData[\'year\'] == \'0004\' ? sprintf(\'(%1$s)\', $txt[\'every_year\']) : $rowData[\'year\'];

							// Construct the date.
							return sprintf(\'%1$d %2$s %3$s\', $rowData[\'day\'], $txt[\'months\'][(int) $rowData[\'month\']], $year);
						'),
					),
					'sort' => array(
						'default' => 'event_date',
						'reverse' => 'event_date DESC',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="holiday[%1$d]" class="input_check" />',
							'params' => array(
								'id_holiday' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=managecalendar;sa=holidays',
				'token' => 'admin-mc',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" class="button_submit" />
					<a class="button_link" href="' . $scripturl . '?action=admin;area=managecalendar;sa=editholiday" style="margin: 0 1em">' . $txt['holidays_add'] . '</a>',
				),
			),
		);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		$context['page_title'] = $txt['manage_holidays'];
	}

	/**
	 * This function is used for adding/editing a specific holiday
	 */
	public function action_editholiday()
	{
		global $txt, $context;

		//We need this, really..
		require_once(SUBSDIR . '/Calendar.subs.php');

		loadTemplate('ManageCalendar');

		$context['is_new'] = !isset($_REQUEST['holiday']);
		$context['page_title'] = $context['is_new'] ? $txt['holidays_add'] : $txt['holidays_edit'];
		$context['sub_template'] = 'edit_holiday';

		// Cast this for safety...
		if (isset($_REQUEST['holiday']))
			$_REQUEST['holiday'] = (int) $_REQUEST['holiday'];

		// Submitting?
		if (isset($_POST[$context['session_var']]) && (isset($_REQUEST['delete']) || $_REQUEST['title'] != ''))
		{
			checkSession();

			// Not too long good sir?
			$_REQUEST['title'] =  Util::substr($_REQUEST['title'], 0, 60);
			$_REQUEST['holiday'] = isset($_REQUEST['holiday']) ? (int) $_REQUEST['holiday'] : 0;
		
			if (isset($_REQUEST['delete']))
				removeHolidays($_REQUEST['holiday']);
			else
			{
				$date = strftime($_REQUEST['year'] <= 4 ? '0004-%m-%d' : '%Y-%m-%d', mktime(0, 0, 0, $_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year']));
				if (isset($_REQUEST['edit']))
					editHoliday($_REQUEST['holiday'], $date, $_REQUEST['title']);
				else
					insertHoliday($date, $_REQUEST['title']);
			}

			redirectexit('action=admin;area=managecalendar;sa=holidays');
		}

		// Default states...
		if ($context['is_new'])
		{
			$context['holiday'] = array(
				'id' => 0,
				'day' => date('d'),
				'month' => date('m'),
				'year' => '0000',
				'title' => ''
			);
		}
		// If it's not new load the data.
		else
			$context['holiday'] = getHoliday($_REQUEST['holiday']);

		// Last day for the drop down?
		$context['holiday']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['holiday']['month'] == 12 ? 1 : $context['holiday']['month'] + 1, 0, $context['holiday']['month'] == 12 ? $context['holiday']['year'] + 1 : $context['holiday']['year']));
	}

	/**
	 * Show and allow to modify calendar settings.
	 * The method uses a Settings_Form to do the work.
	 */
	public function action_calendarSettings_display()
	{
		global $context, $txt, $scripturl;

		// initialize the form
		$this->_initCalendarSettingsForm();

		$config_vars = $this->_calendarSettings->settings();

		call_integration_hook('integrate_modify_calendar_settings', array(&$config_vars));

		// Get the settings template fired up.
		require_once(SUBSDIR . '/Settings.class.php');

		// Get the final touches in place.
		$context['post_url'] = $scripturl . '?action=admin;area=managecalendar;save;sa=settings';
		$context['settings_title'] = $txt['calendar_settings'];

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession();
			call_integration_hook('integrate_save_calendar_settings');
			Settings_Form::save_db($config_vars);

			// Update the stats in case.
			updateSettings(array(
				'calendar_updated' => time(),
			));

			redirectexit('action=admin;area=managecalendar;sa=settings');
		}

		// We need this for the in-line permissions
		createToken('admin-mp');

		// Prepare the settings...
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieve and return all admin settings for the calendar.
	 */
	private function _initCalendarSettingsForm()
	{
		global $txt, $context;

		// instantiate the form
		$this->_calendarSettings = new Settings_Form();

		// Load the boards list.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_list = getBoardList(array('not_redirection' => true), true);
		$boards = array('');
		foreach ($boards_list as $board)
			$boards[$board['id_board']] = $board['cat_name'] . ' - ' . $board['board_name'];

		// Look, all the calendar settings - of which there are many!
		$config_vars = array(
			// All the permissions:
			array('permissions', 'calendar_view', 'help' => 'cal_enabled'),
			array('permissions', 'calendar_post'),
			array('permissions', 'calendar_edit_own'),
			array('permissions', 'calendar_edit_any'),
			'',
			// How many days to show on board index, and where to display events etc?
			array('int', 'cal_days_for_index', 6, 'postinput' => $txt['days_word']),
			array('select', 'cal_showholidays', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('select', 'cal_showbdays', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('select', 'cal_showevents', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('check', 'cal_export'),
			'',
			// Linking events etc...
			array('select', 'cal_defaultboard', $boards),
			array('check', 'cal_daysaslink'),
			array('check', 'cal_allow_unlinked'),
			array('check', 'cal_showInTopic'),
			'',
			// Dates of calendar...
			array('int', 'cal_minyear'),
			array('int', 'cal_maxyear'),
			'',
			// Calendar spanning...
			array('check', 'cal_allowspan'),
			array('int', 'cal_maxspan', 6, 'postinput' => $txt['days_word']),
		);

		// Some important context stuff
		$context['page_title'] = $txt['calendar_settings'];
		$context['sub_template'] = 'show_settings';

		return $this->_calendarSettings->settings($config_vars);
	}

	/**
	 * Retrieve and return all admin settings for the calendar.
	 */
	public function settings()
	{
		global $txt;

		// Load the boards list.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_list = getBoardList(array('not_redirection' => true), true);
		$boards = array('');
		foreach ($boards_list as $board)
			$boards[$board['id_board']] = $board['cat_name'] . ' - ' . $board['board_name'];

		// Look, all the calendar settings - of which there are many!
		$config_vars = array(
			// All the permissions:
			array('permissions', 'calendar_view', 'help' => 'cal_enabled'),
			array('permissions', 'calendar_post'),
			array('permissions', 'calendar_edit_own'),
			array('permissions', 'calendar_edit_any'),
			'',
			// How many days to show on board index, and where to display events etc?
			array('int', 'cal_days_for_index', 6, 'postinput' => $txt['days_word']),
			array('select', 'cal_showholidays', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('select', 'cal_showbdays', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('select', 'cal_showevents', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('check', 'cal_export'),
			'',
			// Linking events etc...
			array('select', 'cal_defaultboard', $boards),
			array('check', 'cal_daysaslink'),
			array('check', 'cal_allow_unlinked'),
			array('check', 'cal_showInTopic'),
			'',
			// Dates of calendar...
			array('int', 'cal_minyear'),
			array('int', 'cal_maxyear'),
			'',
			// Calendar spanning...
			array('check', 'cal_allowspan'),
			array('int', 'cal_maxspan', 6, 'postinput' => $txt['days_word']),
		);

		return $config_vars;
	}
}