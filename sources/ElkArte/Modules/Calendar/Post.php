<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
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

namespace ElkArte\Modules\Calendar;

use ElkArte\CalendarEvent;
use ElkArte\Controller\Calendar;
use ElkArte\Errors\ErrorContext;
use ElkArte\EventManager;
use ElkArte\Exceptions\ControllerRedirectException;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\Util;
use ElkArte\Modules\AbstractModule;

/**
 * This class's task is to bind the posting of a topic to a calendar event.
 * Used when from the calendar controller the poster is redirected to the post page.
 *
 * @package Calendar
 */
class Post extends AbstractModule
{
	/** @var bool If we are making a topic event */
	protected static $_make_event = false;

	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $context, $modSettings;

		// Posting an event?
		self::$_make_event = isset($_REQUEST['calendar']);

		$modSettings['cal_limityear'] = empty($modSettings['cal_limityear']) ? 20 : (int) $modSettings['cal_limityear'];

		$context['make_event'] = self::$_make_event;
		$context['cal_minyear'] = $modSettings['cal_minyear'];
		$context['cal_maxyear'] = (int) date('Y') + $modSettings['cal_limityear'];

		if (self::$_make_event)
		{
			return [
				['prepare_post', [Post::class, 'prepare_post'], []],
				['prepare_context', [Post::class, 'prepare_context'], ['id_member_poster']],
				['before_save_post', [Post::class, 'before_save_post'], ['post_errors']],
				['after_save_post', [Post::class, 'after_save_post'], []],
			];
		}

		return [];
	}

	/**
	 * Prepare post event, add the make event template layer
	 */
	public function prepare_post()
	{
		theme()->getLayers()->addAfter('make_event', 'postarea');
	}

	/**
	 * before_save_post event, checks the event title is set
	 *
	 * @param ErrorContext $post_errors
	 */
	public function before_save_post($post_errors)
	{
		if (isset($_REQUEST['deleteevent']))
		{
			return;
		}

		if (Util::htmltrim($_POST['evtitle']) !== '')
		{
			return;
		}

		$post_errors->addError('no_event');
	}

	/**
	 * after_save_post event, creates/edits/removes the linked event in the calendar
	 *
	 * @throws \Exception
	 */
	public function after_save_post()
	{
		global $modSettings, $board, $topic;

		$req = HttpReq::instance();
		$eventid = $req->getPost('eventid', 'intval', -1);

		$event = new CalendarEvent($eventid, $modSettings);

		try
		{
			$save_data = $event->validate($_POST);
			$save_data['id_board'] = $board;
			$save_data['id_topic'] = $topic;
		}
		catch (\Exception $exception)
		{
			throw $exception;
		}

		// Editing or posting an event?
		if ($event->isNew())
		{
			// Make sure they can link an event to this post.
			canLinkEvent();

			// Insert the event.
			$event->insert($save_data, $this->user->id);
		}
		else
		{
			// If you're not allowed to edit any events, you have to be the poster.
			if (!allowedTo('calendar_edit_any'))
			{
				$event_poster = getEventPoster($eventid);

				// Silly hacker, Trix are for kids. ...probably trademarked somewhere, this is FAIR USE! (parody...)
				isAllowedTo('calendar_edit_' . ($event_poster == $this->user->id ? 'own' : 'any'));
			}

			// Delete it?
			if (isset($_REQUEST['deleteevent']))
			{
				$event->remove();
			}
			// ... or just update it?
			else
			{
				$event->update($save_data);
			}
		}
	}

	/**
	 * Verifies the user can edit an event and makes calls to load context related to the event
	 *
	 * @param int $id_member_poster
	 *
	 * @throws ControllerRedirectException
	 * @throws Exception
	 */
	public function prepare_context($id_member_poster)
	{
		global $txt, $context;

		$event_id = $this->_req->getRequest('eventid', 'intval', -1);
		$id_member_poster = (int) $id_member_poster;

		// Editing an event?  (but NOT previewing!?)
		// If the user doesn't have permission to edit the post in this topic, redirect them.
		if ($event_id !== -1 && !isset($_REQUEST['subject'])
			&& (empty($id_member_poster) || $id_member_poster !== $this->user->id || !allowedTo('modify_own')) && !allowedTo('modify_any'))
		{
			throw new ControllerRedirectException(Calendar::class, 'action_post');
		}

		$this->_prepareEventContext($event_id);

		$context['page_title'] = $event_id === -1 ? $txt['calendar_post_event'] : $txt['calendar_edit'];
	}

	/**
	 * Loads in context stuff related to the event
	 *
	 * @param int $event_id The id of the event
	 *
	 * @throws Exception cannot_post_new, invalid_year, invalid_month
	 */
	private function _prepareEventContext($event_id)
	{
		global $context, $modSettings, $board;

		// They might want to pick a board.
		$context['current_board'] = $context['current_board'] ?? 0;

		// Start loading up the event info.
		$context['event'] = [];
		$context['event']['title'] = isset($_REQUEST['evtitle']) ? htmlspecialchars(stripslashes($_REQUEST['evtitle']), ENT_COMPAT, 'UTF-8') : '';
		$context['event']['id'] = (int) $event_id;
		$context['event']['new'] = $context['event']['id'] === -1;

		// Permissions check!
		isAllowedTo('calendar_post');

		// Editing an event?  (but NOT previewing!?)
		if (empty($context['event']['new']) && !isset($_REQUEST['subject']))
		{
			// Get the current event information.
			require_once(SUBSDIR . '/Calendar.subs.php');
			$event_info = getEventProperties($context['event']['id']);

			// Make sure the user is allowed to edit this event.
			if ($event_info['member'] !== $this->user->id)
			{
				isAllowedTo('calendar_edit_any');
			}
			elseif (!allowedTo('calendar_edit_any'))
			{
				isAllowedTo('calendar_edit_own');
			}

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

			$context['event']['month'] = isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : $today['mon'];
			$context['event']['year'] = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : $today['year'];

			if (isset($_REQUEST['day']))
			{
				$context['event']['day'] = (int) $_REQUEST['day'];
			}
			else
			{
				$context['event']['day'] = $context['event']['month'] === $today['mon'] ? $today['mday'] : 0;
			}

			$context['event']['span'] = $this->_req->getRequest('span', 'intval', 1);

			// Make sure the year and month are in the valid range.
			if ($context['event']['month'] < 1 || $context['event']['month'] > 12)
			{
				throw new Exception('invalid_month', false);
			}

			if ($context['event']['year'] < $modSettings['cal_minyear'] || $context['event']['year'] > (int) date('Y') + $modSettings['cal_limityear'])
			{
				throw new Exception('invalid_year', false);
			}

			// Get a list of boards they can post in.
			require_once(SUBSDIR . '/Boards.subs.php');

			$boards = boardsAllowedTo('post_new');
			if (empty($boards))
			{
				throw new Exception('cannot_post_new', 'user');
			}

			// Load a list of boards for this event in the context.
			$boardListOptions = [
				'included_boards' => in_array(0, $boards) ? null : $boards,
				'not_redirection' => true,
				'selected_board' => empty($context['current_board']) ? $modSettings['cal_defaultboard'] : $context['current_board'],
			];
			$context += getBoardList($boardListOptions);
		}

		// Find the last day of the month.
		$context['event']['last_day'] = (int) Util::strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

		$context['event']['board'] = empty($board) ? $modSettings['cal_defaultboard'] : $board;
	}
}
