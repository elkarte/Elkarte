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

use ElkArte\Cache\Cache;
use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * This class's task is to show the upcoming events in the BoardIndex.
 */
class BoardIndex extends AbstractModule
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(EventManager $eventsManager)
	{
		// Load the calendar?
		if (allowedTo('calendar_view'))
		{
			return array(
				array('pre_load', array('\\ElkArte\\Modules\\Calendar\\BoardIndex', 'pre_load'), array()),
				array('post_load', array('\\ElkArte\\Modules\\Calendar\\BoardIndex', 'post_load'), array()),
			);
		}

		return false;
	}

	/**
	 * Pre-load hooks as part of board index
	 */
	public function pre_load()
	{
		global $modSettings, $context;

		// Retrieve the calendar data (events, birthdays, holidays).
		$eventOptions = array(
			'include_holidays' => $modSettings['cal_showholidays'] > 1,
			'include_birthdays' => $modSettings['cal_showbdays'] > 1,
			'include_events' => $modSettings['cal_showevents'] > 1,
			'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
		);

		$context += Cache::instance()->quick_get('calendar_index_offset_' . ($this->user->time_offset + $modSettings['time_offset']), 'subs/Calendar.subs.php', 'cache_getRecentEvents', array($eventOptions));

		// Whether one or multiple days are shown on the board index.
		$context['calendar_only_today'] = $modSettings['cal_days_for_index'] == 1;

		// This is used to show the "how-do-I-edit" help.
		$context['calendar_can_edit'] = allowedTo('calendar_edit_any');
	}

	/**
	 * post load functions, load calendar events for the board index as part of BoardIndex
	 *
	 * @param array $callbacks
	 */
	public function post_load(&$callbacks)
	{
		global $context;

		if (empty($context['calendar_holidays']) && empty($context['calendar_birthdays']) && empty($context['calendar_events']))
		{
			return;
		}

		$callbacks = elk_array_insert($callbacks, 'recent_posts', array('show_events'), 'after', false);
	}
}
