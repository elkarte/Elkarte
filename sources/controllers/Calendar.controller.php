<?php

/**
 * This file has only one real task, showing the calendar.
 * Original module by Aaron O'Neil - aaron@mud-master.com
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
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Calendar_Controller class, displays the calendar for the site and
 * provides for its navigation
 */
class Calendar_Controller extends Action_Controller
{
	/**
	 * Default action handler for requests on the calendar
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// when you don't know what you're doing... we know! :P
		$this->action_calendar();
	}

	/**
	 * Show the calendar.
	 * It loads the specified month's events, holidays, and birthdays.
	 * It requires the calendar_view permission.
	 * It depends on the cal_enabled setting, and many of the other cal_ settings.
	 * It uses the calendar_start_day theme option. (Monday/Sunday)
	 * It uses the main sub template in the Calendar template.
	 * It goes to the month and year passed in 'month' and 'year' by get or post.
	 * It is accessed through ?action=calendar.
	 */
	public function action_calendar()
	{
		global $txt, $context, $modSettings, $scripturl, $options;

		// Permissions, permissions, permissions.
		isAllowedTo('calendar_view');

		// This is gonna be needed...
		loadTemplate('Calendar');

		// You can't do anything if the calendar is off.
		if (empty($modSettings['cal_enabled']))
			fatal_lang_error('calendar_off', false);

		// Set the page title to mention the calendar ;).
		$context['page_title'] = $txt['calendar'];
		$context['sub_template'] = 'show_calendar';

		// Is this a week view?
		$context['view_week'] = isset($_GET['viewweek']);

		// Don't let search engines index weekly calendar pages.
		if ($context['view_week'])
			$context['robot_no_index'] = true;

		// Get the current day of month...
		require_once(SUBSDIR . '/Calendar.subs.php');
		$today = getTodayInfo();

		// If the month and year are not passed in, use today's date as a starting point.
		$curPage = array(
			'day' => isset($_REQUEST['day']) ? (int) $_REQUEST['day'] : $today['day'],
			'month' => isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : $today['month'],
			'year' => isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : $today['year']
		);

		// Make sure the year and month are in valid ranges.
		if ($curPage['month'] < 1 || $curPage['month'] > 12)
			fatal_lang_error('invalid_month', false);

		if ($curPage['year'] < $modSettings['cal_minyear'] || $curPage['year'] > $modSettings['cal_maxyear'])
			fatal_lang_error('invalid_year', false);

		// If we have a day clean that too.
		if ($context['view_week'])
		{
			if ($curPage['day'] > 31 || !mktime(0, 0, 0, $curPage['month'], $curPage['day'], $curPage['year']))
				fatal_lang_error('invalid_day', false);
		}

		// Load all the context information needed to show the calendar grid.
		$calendarOptions = array(
			'start_day' => !empty($options['calendar_start_day']) ? $options['calendar_start_day'] : 0,
			'show_birthdays' => in_array($modSettings['cal_showbdays'], array(1, 2)),
			'show_events' => in_array($modSettings['cal_showevents'], array(1, 2)),
			'show_holidays' => in_array($modSettings['cal_showholidays'], array(1, 2)),
			'show_week_num' => true,
			'short_day_titles' => false,
			'show_next_prev' => true,
			'show_week_links' => true,
			'size' => 'large',
		);

		// Load up the main view.
		if ($context['view_week'])
			$context['calendar_grid_main'] = getCalendarWeek($curPage['month'], $curPage['year'], $curPage['day'], $calendarOptions);
		else
			$context['calendar_grid_main'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

		// Load up the previous and next months.
		$calendarOptions['show_birthdays'] = $calendarOptions['show_events'] = $calendarOptions['show_holidays'] = false;
		$calendarOptions['short_day_titles'] = true;
		$calendarOptions['show_next_prev'] = false;
		$calendarOptions['show_week_links'] = false;
		$calendarOptions['size'] = 'small';
		$context['calendar_grid_current'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

		// Only show previous month if it isn't pre-January of the min-year
		if ($context['calendar_grid_current']['previous_calendar']['year'] > $modSettings['cal_minyear'] || $curPage['month'] != 1)
			$context['calendar_grid_prev'] = getCalendarGrid($context['calendar_grid_current']['previous_calendar']['month'], $context['calendar_grid_current']['previous_calendar']['year'], $calendarOptions);

		// Only show next month if it isn't post-December of the max-year
		if ($context['calendar_grid_current']['next_calendar']['year'] < $modSettings['cal_maxyear'] || $curPage['month'] != 12)
			$context['calendar_grid_next'] = getCalendarGrid($context['calendar_grid_current']['next_calendar']['month'], $context['calendar_grid_current']['next_calendar']['year'], $calendarOptions);

		// Basic template stuff.
		$context['can_post'] = allowedTo('calendar_post');
		$context['current_day'] = $curPage['day'];
		$context['current_month'] = $curPage['month'];
		$context['current_year'] = $curPage['year'];
		$context['show_all_birthdays'] = isset($_GET['showbd']);

		// Set the page title to mention the month or week, too
		$context['page_title'] .= ' - ' . ($context['view_week'] ? sprintf($txt['calendar_week_title'], $context['calendar_grid_main']['week_number'], ($context['calendar_grid_main']['week_number'] == 53 ? $context['current_year'] - 1 : $context['current_year'])) : $txt['months'][$context['current_month']] . ' ' . $context['current_year']);

		// Load up the linktree!
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=calendar',
			'name' => $txt['calendar']
		);

		// Add the current month to the linktree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=calendar;year=' . $context['current_year'] . ';month=' . $context['current_month'],
			'name' => $txt['months'][$context['current_month']] . ' ' . $context['current_year']
		);

		// If applicable, add the current week to the linktree.
		if ($context['view_week'])
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=calendar;viewweek;year=' . $context['current_year'] . ';month=' . $context['current_month'] . ';day=' . $context['current_day'],
				'name' => $txt['calendar_week'] . ' ' . $context['calendar_grid_main']['week_number']
			);

		// Build the calendar button array.
		$context['calendar_buttons'] = array(
			'post_event' => array('test' => 'can_post', 'text' => 'calendar_post_event', 'image' => 'calendarpe.png', 'lang' => true, 'url' => $scripturl . '?action=calendar;sa=post;month=' . $context['current_month'] . ';year=' . $context['current_year'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_calendar_buttons');
	}

	/**
	 * This function processes posting/editing/deleting a calendar event.
	 *
	 *  - calls action_post() function if event is linked to a post.
	 *  - calls insertEvent() to insert the event if not linked to post.
	 *
	 * It requires the calendar_post permission to use.
	 * It uses the event_post sub template in the Calendar template.
	 * It is accessed with ?action=calendar;sa=post.
	 */
	public function action_post()
	{
		global $context, $txt, $user_info, $scripturl, $modSettings, $topic;

		// You need to view what you're doing :P
		isAllowedTo('calendar_view');

		// Well - can they post?
		isAllowedTo('calendar_post');

		// We need this for all kinds of useful functions.
		require_once(SUBSDIR . '/Calendar.subs.php');

		// Cast this for safety...
		$event_id = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : null;

		// Submitting?
		if (isset($_POST[$context['session_var']], $event_id))
		{
			checkSession();

			// Validate the post...
			if (!isset($_POST['link_to_board']))
				$this->validateEventPost();

			// If you're not allowed to edit any events, you have to be the poster.
			if ($event_id > 0 && !allowedTo('calendar_edit_any'))
				isAllowedTo('calendar_edit_' . (!empty($user_info['id']) && getEventPoster($event_id) == $user_info['id'] ? 'own' : 'any'));

			// New - and directing?
			if ($event_id == -1 && isset($_POST['link_to_board']))
			{
				$_REQUEST['calendar'] = 1;
				require_once(CONTROLLERDIR . '/Post.controller.php');
				$controller = new Post_Controller();
				return $controller->action_post();
			}

			$this->_action_save($event_id);

			// No point hanging around here now...
			redirectexit($scripturl . '?action=calendar;month=' . $_POST['month'] . ';year=' . $_POST['year']);
		}

		// If we are not enabled... we are not enabled.
		if (empty($modSettings['cal_allow_unlinked']) && empty($event_id))
		{
			$_REQUEST['calendar'] = 1;
			require_once(CONTROLLERDIR . '/Post.controller.php');
			$controller = new Post_Controller();
			return $controller->action_post();
		}

		// New?
		if (!isset($event_id))
		{
			$today = getdate();

			$context['event'] = array(
				'boards' => array(),
				'board' => 0,
				'new' => 1,
				'eventid' => -1,
				'year' => isset($_REQUEST['year']) ? $_REQUEST['year'] : $today['year'],
				'month' => isset($_REQUEST['month']) ? $_REQUEST['month'] : $today['mon'],
				'day' => isset($_REQUEST['day']) ? $_REQUEST['day'] : $today['mday'],
				'title' => '',
				'span' => 1,
			);
			$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

			// Get list of boards that can be posted in.
			$boards = boardsAllowedTo('post_new');
			if (empty($boards))
				fatal_lang_error('cannot_post_new', 'permission');

			// Load the list of boards and categories in the context.
			require_once(SUBSDIR . '/Boards.subs.php');
			$boardListOptions = array(
				'included_boards' => in_array(0, $boards) ? null : $boards,
				'not_redirection' => true,
				'selected_board' => $modSettings['cal_defaultboard'],
			);
			$context += getBoardList($boardListOptions);
		}
		else
		{
			// Reload the event after making changes
			$context['event'] = getEventProperties($event_id);

			if ($context['event'] === false)
				fatal_lang_error('no_access', false);

			// If it has a board, then they should be editing it within the topic.
			if (!empty($context['event']['topic']['id']) && !empty($context['event']['topic']['first_msg']))
			{
				// We load the board up, for a check on the board access rights...
				$topic = $context['event']['topic']['id'];
				loadBoard();
			}

			// Make sure the user is allowed to edit this event.
			if ($context['event']['member'] != $user_info['id'])
				isAllowedTo('calendar_edit_any');
			elseif (!allowedTo('calendar_edit_any'))
				isAllowedTo('calendar_edit_own');
		}

		// Template, sub template, etc.
		loadTemplate('Calendar');
		$context['sub_template'] = 'unlinked_event_post';

		$context['page_title'] = isset($event_id) ? $txt['calendar_edit'] : $txt['calendar_post_event'];
		$context['linktree'][] = array(
			'name' => $context['page_title'],
		);
	}

	/**
	 * This function offers up a download of an event in iCal 2.0 format.
	 *
	 * follows the conventions in RFC5546 http://tools.ietf.org/html/rfc5546
	 * sets events as all day events since we don't have hourly events
	 * will honor and set multi day events
	 * sets a sequence number if the event has been modified.
	 * Accessed by action=calendar;sa=ical
	 *
	 * @todo .... allow for week or month export files as well?
	 */
	public function action_ical()
	{
		global $modSettings;

		// What do you think you export?
		isAllowedTo('calendar_view');

		// You can't export if the calendar export feature is off.
		if (empty($modSettings['cal_export']))
			fatal_lang_error('calendar_export_off', false);

		// Goes without saying that this is required.
		if (!isset($_REQUEST['eventid']))
			fatal_lang_error('no_access', false);

		// This is kinda wanted.
		require_once(SUBSDIR . '/Calendar.subs.php');

		// Load up the event in question and check it exists.
		$event = getEventProperties($_REQUEST['eventid']);

		if ($event === false)
			fatal_lang_error('no_access', false);

		$filecontents = build_ical_content($event);

		// Send some standard headers.
		ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		// Send the file headers
		header('Pragma: ');
		header('Cache-Control: no-cache');
		if (!isBrowser('gecko'))
			header('Content-Transfer-Encoding: binary');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . 'GMT');
		header('Accept-Ranges: bytes');
		header('Connection: close');
		header('Content-Disposition: attachment; filename="' . $event['title'] . '.ics"');
		if (empty($modSettings['enableCompressedOutput']))
			header('Content-Length: ' . Util::strlen($filecontents));

		// This is a calendar item!
		header('Content-Type: text/calendar');

		// Chuck out the card.
		echo $filecontents;

		// Off we pop - lovely!
		obExit(false);
	}

	/**
	 * Makes sure the calendar post is valid.
	 */
	public function validateEventPost()
	{
		global $modSettings;

		// Make sure they're allowed to post...
		isAllowedTo('calendar_post');

		if (isset($_POST['span']))
		{
			// Make sure it's turned on and not some fool trying to trick it.
			if (empty($modSettings['cal_allowspan']))
				fatal_lang_error('no_span', false);
			if ($_POST['span'] < 1 || $_POST['span'] > $modSettings['cal_maxspan'])
				fatal_lang_error('invalid_days_numb', false);
		}

		// There is no need to validate the following values if we are just deleting the event.
		if (!isset($_POST['deleteevent']))
		{
			// No month?  No year?
			if (!isset($_POST['month']))
				fatal_lang_error('event_month_missing', false);
			if (!isset($_POST['year']))
				fatal_lang_error('event_year_missing', false);

			// Check the month and year...
			if ($_POST['month'] < 1 || $_POST['month'] > 12)
				fatal_lang_error('invalid_month', false);
			if ($_POST['year'] < $modSettings['cal_minyear'] || $_POST['year'] > $modSettings['cal_maxyear'])
				fatal_lang_error('invalid_year', false);

			// No day?
			if (!isset($_POST['day']))
				fatal_lang_error('event_day_missing', false);
			if (!isset($_POST['evtitle']) && !isset($_POST['subject']))
				fatal_lang_error('event_title_missing', false);
			elseif (!isset($_POST['evtitle']))
				$_POST['evtitle'] = $_POST['subject'];

			// Bad day?
			if (!checkdate($_POST['month'], $_POST['day'], $_POST['year']))
				fatal_lang_error('invalid_date', false);

			// No title?
			if (Util::htmltrim($_POST['evtitle']) === '')
				fatal_lang_error('no_event_title', false);
			if (Util::strlen($_POST['evtitle']) > 100)
				$_POST['evtitle'] = Util::substr($_POST['evtitle'], 0, 100);
			$_POST['evtitle'] = str_replace(';', '', $_POST['evtitle']);
		}
	}

	/**
	 * Does the save of an event
	 *
	 * @param int $event_id - the id of the event being saved (if -1 is a new event)
	 */
	private function _action_save($event_id)
	{
		global $user_info, $modSettings;

		// New...
		if ($event_id == -1)
		{
			$eventOptions = array(
				'id_board' => 0,
				'id_topic' => 0,
				'title' => Util::substr($_REQUEST['evtitle'], 0, 100),
				'member' => $user_info['id'],
				'start_date' => sprintf('%04d-%02d-%02d', $_POST['year'], $_POST['month'], $_POST['day']),
				'span' => isset($_POST['span']) && $_POST['span'] > 0 ? min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1) : 0,
			);
			insertEvent($eventOptions);
		}
		// Deleting...
		elseif (isset($_REQUEST['deleteevent']))
			removeEvent($event_id);
		// ... or just update it?
		else
		{
			// There could be already a topic you are not allowed to modify
			if (!allowedTo('post_new') && empty($modSettings['disableNoPostingCalendarEdits']))
				$eventProperties = getEventProperties($event_id, true);

			$eventOptions = array(
				'title' => Util::substr($_REQUEST['evtitle'], 0, 100),
				'span' => empty($modSettings['cal_allowspan']) || empty($_POST['span']) || $_POST['span'] == 1 || empty($modSettings['cal_maxspan']) || $_POST['span'] > $modSettings['cal_maxspan'] ? 0 : min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1),
				'start_date' => strftime('%Y-%m-%d', mktime(0, 0, 0, (int) $_REQUEST['month'], (int) $_REQUEST['day'], (int) $_REQUEST['year'])),
				'id_board' => isset($eventProperties['id_board']) ? (int) $eventProperties['id_board'] : 0,
				'id_topic' => isset($eventProperties['id_topic']) ? (int) $eventProperties['id_topic'] : 0,
			);

			modifyEvent($event_id, $eventOptions);
		}
	}
}