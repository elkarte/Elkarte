<?php

/**
 * A class to handle the basics of calendar events.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * A class to handle the basics of calendar events.
 * Namely a certain kind of validation, inserting a new one, updating existing,
 * deleting, etc.
 */
class CalendarEvent
{
	/**
	 * The id of the event.
	 *
	 * @var null|int
	 */
	protected $_event_id = null;

	/**
	 * The general settings (in fact a copy of $modSettings).
	 *
	 * @var mixed[]
	 */
	protected $_settings = array();

	/**
	 * Construct the object requires the id of the event and the settings
	 *
	 * @param null|int $event_id Obviously the id of the event.
	 *                  If null or -1 the event is considered new
	 *                  @see \ElkArte\CalendarEvent::isNew
	 * @param mixed[] $settings An array of settings ($modSettings is the current one)
	 */
	public function __construct($event_id, $settings = array())
	{
		$this->_settings = $settings;
		$this->_event_id = $event_id;

		// We need this for all kinds of useful functions.
		require_once(SUBSDIR . '/Calendar.subs.php');
	}

	/**
	 * Makes sure the calendar data are valid depending on settings
	 * and permissions.
	 *
	 * @param array $event The options may come from a form
	 *
	 * @return array
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function validate($event)
	{
		// Make sure they're allowed to post...
		isAllowedTo('calendar_post');

		if (isset($event['span']))
		{
			// Make sure it's turned on and not some fool trying to trick it.
			if (empty($this->_settings['cal_allowspan']))
				throw new Exceptions\Exception('no_span', false);
			if ($event['span'] < 1 || $event['span'] > $this->_settings['cal_maxspan'])
				throw new Exceptions\Exception('invalid_days_numb', false);
		}


		// There is no need to validate the following values if we are just deleting the event.
		if (!isset($event['deleteevent']))
		{
			// No month?  No year?
			if (!isset($event['month']))
				throw new Exceptions\Exception('event_month_missing', false);
			if (!isset($event['year']))
				throw new Exceptions\Exception('event_year_missing', false);

			// Check the month and year...
			if ($event['month'] < 1 || $event['month'] > 12)
				throw new Exceptions\Exception('invalid_month', false);
			if ($event['year'] < $this->_settings['cal_minyear'] || $event['year'] > date('Y') + $this->_settings['cal_limityear'])
				throw new Exceptions\Exception('invalid_year', false);

			// No day?
			if (!isset($event['day']))
				throw new Exceptions\Exception('event_day_missing', false);

			if (!isset($event['evtitle']) && !isset($event['subject']))
				throw new Exceptions\Exception('event_title_missing', false);
			elseif (!isset($event['evtitle']))
				$event['evtitle'] = $event['subject'];

			// Bad day?
			if (!checkdate($event['month'], $event['day'], $event['year']))
				throw new Exceptions\Exception('invalid_date', false);

			// No title?
			if (Util::htmltrim($event['evtitle']) === '')
				throw new Exceptions\Exception('no_event_title', false);
			if (Util::strlen($event['evtitle']) > 100)
				$event['evtitle'] = Util::substr($event['evtitle'], 0, 100);
			$event['evtitle'] = str_replace(';', '', $event['evtitle']);
		}

		return $event;
	}

	/**
	 * Does the save of an event.
	 *
	 * @param array $options - An array of options for the event.
	 * @param int $member_id - the id of the member saving the event.
	 */
	public function insert($options, $member_id)
	{
		$eventOptions = array(
			'id_board' => isset($options['id_board']) ? $options['id_board'] : 0,
			'id_topic' => isset($options['id_topic']) ? $options['id_topic'] : 0,
			'title' => Util::substr($options['evtitle'], 0, 100),
			'member' => $member_id,
			'start_date' => sprintf('%04d-%02d-%02d', $options['year'], $options['month'], $options['day']),
			'span' => isset($options['span']) && $options['span'] > 0 ? min((int) $this->_settings['cal_maxspan'], (int) $options['span'] - 1) : 0,
		);
		insertEvent($eventOptions);
	}

	/**
	 * Deletes an event.
	 * No permission checks.
	 */
	public function remove()
	{
		removeEvent($this->_event_id);
	}

	/**
	 * Updates an existing event.
	 * Some options are validated to be sure the data inserted into the
	 * database are correct.
	 *
	 * @param array $options The options may come from a form
	 */
	public function update($options)
	{
		// There could be already a topic you are not allowed to modify
		if (!allowedTo('post_new') && empty($this->_settings['disableNoPostingCalendarEdits']))
			$eventProperties = getEventProperties($this->_event_id, true);

		if (empty($this->_settings['cal_allowspan']))
			$span = 0;
		elseif (empty($options['span']) || $options['span'] == 1)
			$span = 0;
		elseif (empty($this->_settings['cal_maxspan']) || $options['span'] > $this->_settings['cal_maxspan'])
			$span = 0;
		else
			$span = min((int) $this->_settings['cal_maxspan'], (int) $options['span'] - 1);

		$eventOptions = array(
			'title' => Util::substr($options['evtitle'], 0, 100),
			'span' => $span,
			'start_date' => strftime('%Y-%m-%d', mktime(0, 0, 0, (int) $options['month'], (int) $options['day'], (int) $options['year'])),
			'id_board' => isset($eventProperties['id_board']) ? (int) $eventProperties['id_board'] : 0,
			'id_topic' => isset($eventProperties['id_topic']) ? (int) $eventProperties['id_topic'] : 0,
		);

		modifyEvent($this->_event_id, $eventOptions);
	}

	/**
	 * Loads up the data of an event for the template.
	 * If new the default values are loaded.
	 *
	 * @param array $options The options may come from a form. Used to set
	 *              some of the defaults in case of new events.
	 * @param int   $member_id - the id of the member saving the event
	 *
	 * @return mixed[] The event structure.
	 * @throws \ElkArte\Exceptions\Exception no_access
	 */
	public function load($options, $member_id)
	{
		global $topic;

		// New?
		if ($this->isNew())
		{
			$today = getdate();

			$event = array(
				'boards' => array(),
				'board' => 0,
				'new' => 1,
				'eventid' => -1,
				'year' => isset($options['year']) ? $options['year'] : $today['year'],
				'month' => isset($options['month']) ? $options['month'] : $today['mon'],
				'day' => isset($options['day']) ? $options['day'] : $today['mday'],
				'title' => '',
				'span' => 1,
			);
			$event['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $event['month'] == 12 ? 1 : $event['month'] + 1, 0, $event['month'] == 12 ? $event['year'] + 1 : $event['year']));
		}
		else
		{
			// Reload the event after making changes
			$event = getEventProperties($this->_event_id);

			if ($event === false)
				throw new Exceptions\Exception('no_access', false);

			// If it has a board, then they should be editing it within the topic.
			if (!empty($event['topic']['id']) && !empty($event['topic']['first_msg']))
			{
				// We load the board up, for a check on the board access rights...
				$topic = $event['topic']['id'];
				loadBoard();
			}

			// Make sure the user is allowed to edit this event.
			if ($event['member'] != $member_id)
				isAllowedTo('calendar_edit_any');
			elseif (!allowedTo('calendar_edit_any'))
				isAllowedTo('calendar_edit_own');
		}

		return $event;
	}

	/**
	 * Determines if the current calendar event is new or not.
	 * @return bool
	 */
	public function isNew()
	{
		return !isset($this->_event_id) || $this->_event_id === -1;
	}

	/**
	 * Determines if the passed member is the one that originally posted the event.
	 *
	 * @param int $member_id
	 * @return bool
	 */
	public function isStarter($member_id)
	{
		return !empty($member_id) && getEventPoster($this->_event_id) == $member_id;
	}
}
