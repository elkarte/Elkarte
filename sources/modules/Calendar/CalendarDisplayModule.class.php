<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * We like to show events associated to the topics.
 *
 * @package Calendar
 */
class Calendar_Display_Module extends ElkArte\sources\modules\Abstract_Module
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $context, $modSettings;

		$context['calendar_post'] = allowedTo('calendar_post');

		add_integration_function('integrate_mod_buttons', 'Calendar_Display_Module::integrate_mod_buttons', '', false);

		if (!empty($modSettings['cal_showInTopic']))
			return array(
				array('topicinfo', array('Calendar_Display_Module', 'topicinfo'), array('topicinfo', 'topic')),
			);
		else
			return array();
	}

	/**
	 * Add the calendar buttons
	 */
	public static function integrate_mod_buttons()
	{
		global $context, $scripturl;

		$context['mod_buttons']['calendar'] = array('test' => 'calendar_post', 'text' => 'calendar_link', 'image' => 'linktocal.png', 'lang' => true, 'url' => $scripturl . '?action=post;calendar;msg=' . $context['topic_first_message'] . ';topic=' . $context['current_topic'] . '.0');

		$context['calendar_post'] &= allowedTo('modify_any') || ($context['user']['started'] && allowedTo('modify_own'));
	}

	/**
	 * Fetches the topic information for linked calendar events
	 *
	 * @param array $topicinfo
	 * @param int $topic
	 */
	public function topicinfo(&$topicinfo, $topic)
	{
		global $context, $user_info, $scripturl;

		// If we want to show event information in the topic, prepare the data.
		if (allowedTo('calendar_view'))
		{
			// We need events details and all that jazz
			require_once(SUBSDIR . '/Calendar.subs.php');

			// First, try create a better time format, ignoring the "time" elements.
			if (preg_match('~%[AaBbCcDdeGghjmuYy](?:[^%]*%[AaBbCcDdeGghjmuYy])*~', $user_info['time_format'], $matches) == 0 || empty($matches[0]))
				$date_string = $user_info['time_format'];
			else
				$date_string = $matches[0];

			// Get event information for this topic.
			$events = eventInfoForTopic($topic);

			$context['linked_calendar_events'] = array();
			foreach ($events as $event)
			{
				// Prepare the dates for being formatted.
				$start_date = sscanf($event['start_date'], '%04d-%02d-%02d');
				$start_date = mktime(12, 0, 0, $start_date[1], $start_date[2], $start_date[0]);
				$end_date = sscanf($event['end_date'], '%04d-%02d-%02d');
				$end_date = mktime(12, 0, 0, $end_date[1], $end_date[2], $end_date[0]);

				$context['linked_calendar_events'][] = array(
					'id' => $event['id_event'],
					'title' => $event['title'],
					'can_edit' => allowedTo('calendar_edit_any') || ($event['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
					'modify_href' => $scripturl . '?action=post;msg=' . $topicinfo['id_first_msg'] . ';topic=' . $topic . '.0;calendar;eventid=' . $event['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
					'can_export' => allowedTo('calendar_edit_any') || ($event['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
					'export_href' => $scripturl . '?action=calendar;sa=ical;eventid=' . $event['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'start_date' => standardTime($start_date, $date_string, 'none'),
					'start_timestamp' => $start_date,
				'end_date' => standardTime($end_date, $date_string, 'none'),
					'end_timestamp' => $end_date,
					'is_last' => false
				);
			}

			if (!empty($context['linked_calendar_events']))
			{
				$context['linked_calendar_events'][count($context['linked_calendar_events']) - 1]['is_last'] = true;
				Template_Layers::instance()->add('display_calendar');
			}
		}
	}
}