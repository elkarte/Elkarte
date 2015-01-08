<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
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
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class's task is to bind the posting of a topic to a calendar event.
 * Used when from the calendar controller the poster is redirected to the post page.
 */
class Calendar_Post_Module
{
	protected static $_make_event = false;

	public static function hooks()
	{
		global $context;

		// Posting an event?
		self::$_make_event = isset($_REQUEST['calendar']);

		$context['make_event'] = self::$_make_event;

		if (self::$_make_event)
			return array(
				array('prepare_post', array('Calendar_Post_Module', 'prepare_post'), array()),
				array('prepare_context', array('Calendar_Post_Module', 'prepare_context'), array()),
				array('before_save_post', array('Calendar_Post_Module', 'before_save_post'), array()),
				array('save_post', array('Calendar_Post_Module', 'save_post'), array()),
			);
		else
			return array();
	}

	public function prepare_post()
	{
		Template_Layers::getInstance()->addAfter('make_event', 'postarea');
	}

	public function before_save_post($post_errors)
	{
		if (!isset($_REQUEST['deleteevent']) && Util::htmltrim($_POST['evtitle']) === '')
			$post_errors->addError('no_event');
	}

	public function save_post($board, $topic)
	{
		global $user_info, $modSettings;

		// Editing or posting an event?
		if (!isset($_REQUEST['eventid']) || $_REQUEST['eventid'] == -1)
		{
			require_once(SUBSDIR . '/Calendar.subs.php');

			// Make sure they can link an event to this post.
			canLinkEvent();

			// Insert the event.
			$eventOptions = array(
				'id_board' => $board,
				'id_topic' => $topic,
				'title' => $_POST['evtitle'],
				'member' => $user_info['id'],
				'start_date' => sprintf('%04d-%02d-%02d', $_POST['year'], $_POST['month'], $_POST['day']),
				'span' => isset($_POST['span']) && $_POST['span'] > 0 ? min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1) : 0,
			);
			insertEvent($eventOptions);
		}
		else
		{
			$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

			// Validate the post...
			$loader = new Controller_Loader('Calendar');
			$calendarController = $loader->initDispatch();
			$calendarController->validateEventPost();

			// If you're not allowed to edit any events, you have to be the poster.
			if (!allowedTo('calendar_edit_any'))
			{
				$event_poster = getEventPoster($_REQUEST['eventid']);

				// Silly hacker, Trix are for kids. ...probably trademarked somewhere, this is FAIR USE! (parody...)
				isAllowedTo('calendar_edit_' . ($event_poster == $user_info['id'] ? 'own' : 'any'));
			}

			// Delete it?
			if (isset($_REQUEST['deleteevent']))
				removeEvent($_REQUEST['eventid']);
			// ... or just update it?
			else
			{
				$span = !empty($modSettings['cal_allowspan']) && !empty($_REQUEST['span']) ? min((int) $modSettings['cal_maxspan'], (int) $_REQUEST['span'] - 1) : 0;
				$start_time = mktime(0, 0, 0, (int) $_REQUEST['month'], (int) $_REQUEST['day'], (int) $_REQUEST['year']);

				$eventOptions = array(
					'start_date' => strftime('%Y-%m-%d', $start_time),
					'end_date' => strftime('%Y-%m-%d', $start_time + $span * 86400),
					'title' => $_REQUEST['evtitle'],
				);
				modifyEvent($_REQUEST['eventid'], $eventOptions);
			}
		}
	}

	public function prepare_context($id_member_poster)
	{
		global $user_info, $txt, $context;

		$event_id = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : -1;

		// Editing an event?  (but NOT previewing!?)
		if ($event_id != -1 && !isset($_REQUEST['subject']))
		{
			// If the user doesn't have permission to edit the post in this topic, redirect them.
			if ((empty($id_member_poster) || $id_member_poster != $user_info['id'] || !allowedTo('modify_own')) && !allowedTo('modify_any'))
			{
				throw new Controller_Redirect_Exception('calendar', 'action_post');
			}
		}

		$this->_prepareEventContext($event_id);

		$context['page_title'] = $context['event']['id'] == -1 ? $txt['calendar_post_event'] : $txt['calendar_edit'];
	}

	/**
	 * Loads in context stuff related to the envent
	 *
	 * @param int $event_id The id of the event
	 */
	private function _prepareEventContext($event_id)
	{
		global $context, $user_info, $modSettings, $board;

		// They might want to pick a board.
		if (!isset($context['current_board']))
			$context['current_board'] = 0;

		// Start loading up the event info.
		$context['event'] = array();
		$context['event']['title'] = isset($_REQUEST['evtitle']) ? htmlspecialchars(stripslashes($_REQUEST['evtitle']), ENT_COMPAT, 'UTF-8') : '';
		$context['event']['id'] = $event_id;
		$context['event']['new'] = $context['event']['id'] == -1;

		// Permissions check!
		isAllowedTo('calendar_post');

		// Editing an event?  (but NOT previewing!?)
		if (empty($context['event']['new']) && !isset($_REQUEST['subject']))
		{
			// Get the current event information.
			require_once(SUBSDIR . '/Calendar.subs.php');
			$event_info = getEventProperties($context['event']['id']);

			// Make sure the user is allowed to edit this event.
			if ($event_info['member'] != $user_info['id'])
				isAllowedTo('calendar_edit_any');
			elseif (!allowedTo('calendar_edit_any'))
				isAllowedTo('calendar_edit_own');

			$context['event']['month'] = $event_info['month'];
			$context['event']['day'] = $event_info['day'];
			$context['event']['year'] = $event_info['year'];
			$context['event']['title'] = $event_info['title'];
			$context['event']['span'] = $event_info['span'];
		}
		else
		{
			// Posting a new event? (or preview...)
			$today = getdate();

			// You must have a month and year specified!
			if (isset($_REQUEST['month']))
				$context['event']['month'] = (int) $_REQUEST['month'];
			else
				$_REQUEST['month'] = $today['mon'];

			if (isset($_REQUEST['year']))
				$context['event']['year'] = (int) $_REQUEST['year'];
			else
				$_REQUEST['year'] = $today['year'];

			if (isset($_REQUEST['day']))
				$context['event']['day'] = (int) $_REQUEST['day'];
			else
				$context['event']['day'] = $_REQUEST['month'] == $today['mon'] ? $today['mday'] : 0;

			$context['event']['span'] = isset($_REQUEST['span']) ? $_REQUEST['span'] : 1;

			// Make sure the year and month are in the valid range.
			if ($context['event']['month'] < 1 || $context['event']['month'] > 12)
				fatal_lang_error('invalid_month', false);

			if ($context['event']['year'] < $modSettings['cal_minyear'] || $context['event']['year'] > $modSettings['cal_maxyear'])
				fatal_lang_error('invalid_year', false);

			// Get a list of boards they can post in.
			require_once(SUBSDIR . '/Boards.subs.php');

			$boards = boardsAllowedTo('post_new');
			if (empty($boards))
				fatal_lang_error('cannot_post_new', 'user');

			// Load a list of boards for this event in the context.
			$boardListOptions = array(
				'included_boards' => in_array(0, $boards) ? null : $boards,
				'not_redirection' => true,
				'selected_board' => empty($context['current_board']) ? $modSettings['cal_defaultboard'] : $context['current_board'],
			);
			$context += getBoardList($boardListOptions);
		}

		// Find the last day of the month.
		$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

		$context['event']['board'] = !empty($board) ? $board : $modSettings['cal_defaultboard'];
	}
}
