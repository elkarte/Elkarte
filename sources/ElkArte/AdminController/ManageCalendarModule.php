<?php

/**
 * This file allows you to manage the calendar.
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Util;

/**
 * This class controls execution for actions in the manage calendar area
 * of the admin panel.
 *
 * @package Calendar
 */
class ManageCalendarModule extends AbstractController
{
	/**
	 * Used to add the Calendar entry to the Core Features list.
	 *
	 * @param mixed[] $core_features The core features array
	 */
	public static function addCoreFeature(&$core_features)
	{
		$core_features['cd'] = array(
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'managecalendar', '{session_data}']),
			'settings' => array(
				'cal_enabled' => 1,
			),
			'setting_callback' => function ($value) {
				if ($value)
				{
					enableModules('calendar', array('admin', 'post', 'boardindex', 'display'));
				}
				else
				{
					disableModules('calendar', array('admin', 'post', 'boardindex', 'display'));
				}
			},
		);
	}

	/**
	 * The main controlling function doesn't have much to do... yet.
	 * Just check permissions and delegate to the rest.
	 *
	 * @uses ManageCalendar language file.
	 */
	public function action_index()
	{
		global $context, $txt;

		// Everything's gonna need this.
		theme()->getTemplates()->loadLanguageFile('ManageCalendar');

		// Default text.
		$context['explain_text'] = $txt['calendar_desc'];

		// Little short on the ground of functions here... but things can and maybe will change...
		$subActions = array(
			'editholiday' => array($this, 'action_editholiday', 'permission' => 'admin_forum'),
			'holidays' => array($this, 'action_holidays', 'permission' => 'admin_forum'),
			'settings' => array($this, 'action_calendarSettings_display', 'permission' => 'admin_forum')
		);

		// Action control
		$action = new Action('manage_calendar');

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

		// Set up the default subaction, call integrate_sa_manage_calendar
		$subAction = $action->initialize($subActions, 'settings');
		$context['sub_action'] = $subAction;

		// Off we go
		$action->dispatch($subAction);
	}

	/**
	 * The function that handles adding, and deleting holiday data
	 */
	public function action_holidays()
	{
		global $txt, $context;

		// Submitting something...
		if (isset($this->_req->post->delete) && !empty($this->_req->post->holiday))
		{
			checkSession();
			validateToken('admin-mc');

			$to_remove = array_map('intval', array_keys($this->_req->post->holiday));

			// Now the IDs are "safe" do the delete...
			require_once(SUBSDIR . '/Calendar.subs.php');
			removeHolidays($to_remove);
		}

		createToken('admin-mc');
		$listOptions = array(
			'id' => 'holiday_list',
			'title' => $txt['current_holidays'],
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'managecalendar', 'sa' => 'holidays']),
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
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'managecalendar', 'sa' => 'editholiday', 'holiday' => '%1$d']) . '">%2$s</a>',
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
						'function' => function ($rowData) {
							global $txt;

							// Recurring every year or just a single year?
							$year = $rowData['year'] == '0004' ? sprintf('(%1$s)', $txt['every_year']) : $rowData['year'];

							// Construct the date.
							return sprintf('%1$d %2$s %3$s', $rowData['day'], $txt['months'][(int) $rowData['month']], $year);
						},
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
						'class' => 'centertext'
					),
				),
			),
			'form' => array(
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'managecalendar', 'sa' => 'holidays']),
				'token' => 'admin-mc',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '<a class="linkbutton_right" href="' . $scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . $txt['holidays_add'] . '</a>
					<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['holidays_delete_confirm'] . '\');" />',
				),
			),
		);

		createList($listOptions);

		$context['page_title'] = $txt['manage_holidays'];
	}

	/**
	 * This function is used for adding/editing a specific holiday
	 *
	 * @uses ManageCalendar template, edit_holiday sub template
	 */
	public function action_editholiday()
	{
		global $txt, $context, $modSettings;

		//We need this, really..
		require_once(SUBSDIR . '/Calendar.subs.php');

		theme()->getTemplates()->load('ManageCalendar');

		$context['is_new'] = !isset($this->_req->query->holiday);
		$context['cal_minyear'] = $modSettings['cal_minyear'];
		$context['cal_maxyear'] = date('Y') + $modSettings['cal_limityear'];
		$context['page_title'] = $context['is_new'] ? $txt['holidays_add'] : $txt['holidays_edit'];
		$context['sub_template'] = 'edit_holiday';

		// Cast this for safety...
		$this->_req->query->holiday = $this->_req->getQuery('holiday', 'intval');

		// Submitting?
		if (isset($this->_req->post->delete) || ($this->_req->getPost('title', 'trim', '') !== ''))
		{
			checkSession();

			// Not too long good sir?
			$this->_req->post->title = Util::substr($this->_req->post->title, 0, 60);
			$this->_req->post->holiday = $this->_req->getPost('holiday', 'intval', 0);

			if (isset($this->_req->post->delete))
			{
				removeHolidays($this->_req->post->holiday);
			}
			else
			{
				$date = strftime('%Y-%m-%d', mktime(0, 0, 0, $this->_req->post->month, $this->_req->post->day, $this->_req->post->year));
				if (isset($this->_req->post->edit))
				{
					editHoliday($this->_req->post->holiday, $date, $this->_req->post->title);
				}
				else
				{
					insertHoliday($date, $this->_req->post->title);
				}
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
		{
			$context['holiday'] = getHoliday($this->_req->query->holiday);
		}

		// Last day for the drop down?
		$context['holiday']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['holiday']['month'] == 12
			? 1
			: $context['holiday']['month'] + 1, 0, $context['holiday']['month'] == 12
				? $context['holiday']['year'] + 1
				: $context['holiday']['year']));
	}

	/**
	 * Show and allow to modify calendar settings.
	 *
	 * @event integrate_save_calendar_settings
	 * - The method uses a \ElkArte\SettingsForm\SettingsForm to do the work.
	 */
	public function action_calendarSettings_display()
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Some important context stuff
		$context['page_title'] = $txt['calendar_settings'];
		$context['sub_template'] = 'show_settings';

		// Lets start off with the permission blocks collapsed
		theme()->addInlineJavascript('var legend = $(\'legend\');
			legend.siblings().slideToggle("fast");
			legend.parent().toggleClass("collapsed")', true);

		// Get the final touches in place.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'managecalendar', 'sa' => 'settings']);
		$context[$context['admin_menu_name']]['current_subsection'] = 'settings';

		// Saving the settings?
		if (isset($this->_req->query->save))
		{
			checkSession();
			call_integration_hook('integrate_save_calendar_settings');
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// Update the stats in case.
			updateSettings(array(
				'calendar_updated' => time(),
			));

			redirectexit('action=admin;area=managecalendar;sa=settings');
		}

		// Prepare the settings...
		$settingsForm->prepare();
	}

	/**
	 * Retrieve and return all admin settings for the calendar.
	 *
	 * @event integrate_modify_calendar_settings Used to add new calendar settings
	 */
	private function _settings()
	{
		global $txt;

		// Load the boards list.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_list = getBoardList(array('override_permissions' => true, 'not_redirection' => true), true);
		$boards = array('');
		foreach ($boards_list as $board)
		{
			$boards[$board['id_board']] = $board['cat_name'] . ' - ' . $board['board_name'];
		}

		// Look, all the calendar settings - of which there are many!
		$config_vars = array(
			array('title', 'calendar_settings'),
			// All the permissions:
			array('permissions', 'calendar_view'),
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
			'',
			// Calendar spanning...
			array('check', 'cal_allowspan'),
			array('int', 'cal_maxspan', 6, 'postinput' => $txt['days_word']),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_calendar_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
