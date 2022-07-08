<?php

/**
 * This file has only one real task, showing the calendar.
 * Original module by Aaron O'Neil - aaron@mud-master.com
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

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\CalendarEvent;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Http\Headers;
use ElkArte\User;
use ElkArte\Util;

/**
 * Displays the calendar for the site and provides for its navigation
 */
class Calendar extends AbstractController
{
	/**
	 * Default action handler for requests on the calendar
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// when you don't know what you're doing... we know! :P
		$this->action_calendar();
	}

	/**
	 * Show the calendar.
	 *
	 * - It loads the specified month's events, holidays, and birthdays.
	 * - It requires the calendar_view permission.
	 * - It depends on the cal_enabled setting, and many of the other cal_ settings.
	 * - It uses the calendar_start_day theme option. (Monday/Sunday)
	 * - It goes to the month and year passed in 'month' and 'year' by get or post.
	 * - It is accessed through ?action=calendar.
	 *
	 * @uses the main sub template in the Calendar template.
	 */
	public function action_calendar()
	{
		global $txt, $context, $modSettings, $options;

		// Permissions, permissions, permissions.
		isAllowedTo('calendar_view');

		// This is gonna be needed...
		theme()->getTemplates()->load('Calendar');

		// You can't do anything if the calendar is off.
		if (empty($modSettings['cal_enabled']))
		{
			throw new Exception('calendar_off', false);
		}

		if (empty($modSettings['cal_limityear']))
		{
			$modSettings['cal_limityear'] = 20;
		}

		// Set the page title to mention the calendar ;).
		$context['page_title'] = $txt['calendar'];
		$context['sub_template'] = 'show_calendar';

		// Is this a week view?
		$context['view_week'] = isset($_GET['viewweek']);
		$context['cal_minyear'] = $modSettings['cal_minyear'];
		$context['cal_maxyear'] = (int) date('Y') + (int) $modSettings['cal_limityear'];

		// Don't let search engines index weekly calendar pages.
		if ($context['view_week'])
		{
			$context['robot_no_index'] = true;
		}

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
		{
			throw new Exception('invalid_month', false);
		}

		if ($curPage['year'] < $context['cal_minyear'] || $curPage['year'] > $context['cal_maxyear'])
		{
			throw new Exception('invalid_year', false);
		}

		// If we have a day clean that too.
		if ($context['view_week'])
		{
			if ($curPage['day'] > 31 || !mktime(0, 0, 0, $curPage['month'], $curPage['day'], $curPage['year']))
			{
				throw new Exception('invalid_day', false);
			}
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
		{
			$context['calendar_grid_main'] = getCalendarWeek($curPage['month'], $curPage['year'], $curPage['day'], $calendarOptions);
		}
		else
		{
			$context['calendar_grid_main'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);
		}

		// Load up the previous and next months.
		$calendarOptions['show_birthdays'] = $calendarOptions['show_events'] = $calendarOptions['show_holidays'] = false;
		$calendarOptions['short_day_titles'] = true;
		$calendarOptions['show_next_prev'] = false;
		$calendarOptions['show_week_links'] = false;
		$calendarOptions['size'] = 'small';
		$context['calendar_grid_current'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

		// Only show previous month if it isn't pre-January of the min-year
		if ($context['calendar_grid_current']['previous_calendar']['year'] > $context['cal_minyear'] || $curPage['month'] != 1)
		{
			$context['calendar_grid_prev'] = getCalendarGrid($context['calendar_grid_current']['previous_calendar']['month'], $context['calendar_grid_current']['previous_calendar']['year'], $calendarOptions);
		}

		// Only show next month if it isn't post-December of the max-year
		if ($context['calendar_grid_current']['next_calendar']['year'] < $context['cal_maxyear'] || $curPage['month'] != 12)
		{
			$context['calendar_grid_next'] = getCalendarGrid($context['calendar_grid_current']['next_calendar']['month'], $context['calendar_grid_current']['next_calendar']['year'], $calendarOptions);
		}

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
			'url' => getUrl('action', ['action' => 'calendar']),
			'name' => $txt['calendar']
		);

		// Add the current month to the linktree.
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'calendar', 'year' => $context['current_year'], 'month' => $context['current_month']]),
			'name' => $txt['months'][$context['current_month']] . ' ' . $context['current_year']
		);

		// If applicable, add the current week to the linktree.
		if ($context['view_week'])
		{
			$context['linktree'][] = array(
				'url' => getUrl('action', ['action' => 'calendar', 'year' => $context['current_year'], 'month' => $context['current_month'], 'day' => $context['current_day'], 'viewweek']),
				'name' => $txt['calendar_week'] . ' ' . $context['calendar_grid_main']['week_number']
			);
		}

		// Build the calendar button array.
		$context['calendar_buttons'] = array(
			'post_event' => array(
				'test' => 'can_post',
				'text' => 'calendar_post_event',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'calendar', 'sa' => 'post', 'year' => $context['current_year'], 'month' => $context['current_month'], '{session_data}'])
			),
		);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_calendar_buttons');
	}

	/**
	 * This function processes posting/editing/deleting a calendar event.
	 *
	 *  - Calls action_post() function if event is linked to a post.
	 *  - Calls insertEvent() to insert the event if not linked to post.
	 *  - It requires the calendar_post permission to use.
	 *  - It is accessed with ?action=calendar;sa=post.
	 *
	 * @uses the event_post sub template in the Calendar template.
	 */
	public function action_post()
	{
		global $context, $txt, $modSettings;

		// You need to view what you're doing :P
		isAllowedTo('calendar_view');

		// Well - can they post?
		isAllowedTo('calendar_post');

		// Cast this for safety...
		$event_id = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : null;

		// Submitting?
		if (isset($_POST[$context['session_var']], $event_id))
		{
			return $this->action_save();
		}

		// If we are not enabled... we are not enabled.
		if (empty($modSettings['cal_allow_unlinked']) && empty($event_id))
		{
			$_REQUEST['calendar'] = 1;

			return $this->_returnToPost();
		}

		$event = new CalendarEvent($event_id, $modSettings);

		$context['event'] = $event->load($_REQUEST, $this->user->id);

		if ($event->isNew())
		{
			// Get list of boards that can be posted in.
			$boards = boardsAllowedTo('post_new');
			if (empty($boards))
			{
				throw new Exception('cannot_post_new', 'permission');
			}

			// Load the list of boards and categories in the context.
			require_once(SUBSDIR . '/Boards.subs.php');
			$boardListOptions = array(
				'included_boards' => in_array(0, $boards) ? null : $boards,
				'not_redirection' => true,
				'selected_board' => $modSettings['cal_defaultboard'],
			);
			$context += getBoardList($boardListOptions);
		}

		// Template, sub template, etc.
		theme()->getTemplates()->load('Calendar');
		$context['sub_template'] = 'unlinked_event_post';

		$context['cal_minyear'] = $modSettings['cal_minyear'];
		$context['cal_maxyear'] = (int) date('Y') + (int) $modSettings['cal_limityear'];
		$context['page_title'] = $event->isNew() ? $txt['calendar_edit'] : $txt['calendar_post_event'];
		$context['linktree'][] = array(
			'name' => $context['page_title'],
		);
	}

	/**
	 * Takes care of the saving process.
	 */
	public function action_save()
	{
		global $modSettings;

		checkSession();

		// Cast this for safety...
		$event_id = $this->_req->get('eventid', 'intval');

		$event = new CalendarEvent($event_id, $modSettings);

		// Validate the post...
		$save_data = array();
		if (!isset($_POST['link_to_board']))
		{
			try
			{
				$save_data = $event->validate($_POST);
			}
			catch (Exception $e)
			{
				// @todo This should really integrate into $post_errors.
				$e->fatalLangError();
			}
		}

		// If you're not allowed to edit any events, you have to be the poster.
		if (!$event->isNew() && !allowedTo('calendar_edit_any'))
		{
			isAllowedTo('calendar_edit_' . ($event->isStarter($this->user->id) ? 'own' : 'any'));
		}

		// New - and directing?
		if ($event->isNew() && isset($_POST['link_to_board']))
		{
			$_REQUEST['calendar'] = 1;

			return $this->_returnToPost();
		}

		// New...
		if ($event->isNew())
		{
			$event->insert($save_data, $this->user->id);
		}
		elseif (isset($_REQUEST['deleteevent']))
		{
			$event->remove();
		}
		else
		{
			$event->update($save_data);
		}

		// No point hanging around here now...
		redirectexit('action=calendar;month=' . $this->_req->get('month', 'intval') . ';year=' . $this->_req->get('year', 'intval'));
	}

	/**
	 *
	 * What it does:
	 *  - require_once modules of the controller (not addons because these are
	 *    always all require'd by the dispatcher),
	 *  - Creates the event manager and registers addons and modules,
	 *  - Instantiate the controller
	 *  - Runs pre_dispatch
	 */
	protected function _returnToPost()
	{
		$controller = new Post(new EventManager());
		$controller->setUser(User::$info);
		$hook = $controller->getHook();
		$controller->pre_dispatch();
		$function_name = 'action_post';

		call_integration_hook('integrate_action_' . $hook . '_before', array($function_name));

		$controller->{$function_name}();

		call_integration_hook('integrate_action_' . $hook . '_after', array($function_name));
	}

	/**
	 * This function offers up a download of an event in iCal 2.0 format.
	 *
	 * What it does:
	 *
	 * - Follows the conventions in RFC5546 http://tools.ietf.org/html/rfc5546
	 * - Sets events as all day events since we don't have hourly events
	 * - Will honor and set multi day events
	 * - Sets a sequence number if the event has been modified.
	 * - Accessed by action=calendar;sa=ical
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
		{
			throw new Exception('calendar_export_off', false);
		}

		// Goes without saying that this is required.
		if (!isset($_REQUEST['eventid']))
		{
			throw new Exception('no_access', false);
		}

		// This is kinda wanted.
		require_once(SUBSDIR . '/Calendar.subs.php');

		// Load up the event in question and check it exists.
		$event = getEventProperties($_REQUEST['eventid']);

		if ($event === false)
		{
			throw new Exception('no_access', false);
		}

		$filecontents = build_ical_content($event);

		// Send some standard headers.
		obStart(!empty($modSettings['enableCompressedOutput']));

		// Send the file headers
		$headers = Headers::instance();
		$headers
			->header('Pragma', 'no-cache')
			->header('Cache-Control', 'no-cache')
			->header('Expires', gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT')
			->header('Last-Modified', gmdate('D, d M Y H:i:s', time()) . 'GMT')
			->header('Accept-Ranges', 'bytes')
			->header('Connection', 'close')
			->header('Content-Disposition', 'attachment; filename="' . $event['title'] . '.ics"');
		if (empty($modSettings['enableCompressedOutput']))
		{
			$headers->header('Content-Length', Util::strlen($filecontents));
		}

		// This is a calendar item!

		$headers
			->contentType('text/calendar', 'UTF-8')
			->sendHeaders();

		// Chuck out the card.
		echo $filecontents;

		// Off we pop - lovely!
		obExit(false);
	}
}
