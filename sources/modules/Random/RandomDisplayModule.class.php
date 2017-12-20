<?php

/**
 *
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * Class Random_Display_Module
 */
class Random_Display_Module extends ElkArte\sources\modules\Abstract_Module
{
	/**
	 * @var bool
	 */
	protected static $includeUnapproved = false;

	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $modSettings;

		$return = array();

		if (!empty($modSettings['enableFollowup']))
		{
			$return = array(
				array('topicinfo', array('Random_Display_Module', 'topicinfo'), array('topicinfo', 'topic', 'includeUnapproved')),
				array('prepare_context', array('Random_Display_Module', 'prepare_context'), array())
			);

			add_integration_function('integrate_topic_query', 'Random_Display_Module::followup_topic_query', '', false);
			add_integration_function('integrate_display_message_list', 'Random_Display_Module::followup_message_list', '', false);
		}

		return $return;
	}

	/**
	 * Adds to the display query to fetch the id of the original topic.
	 *
	 * @param string[] $topic_selects
	 * @param string[] $topic_tables
	 */
	public static function followup_topic_query(&$topic_selects, &$topic_tables)
	{
		$topic_selects[] = 'fu.derived_from';
		$topic_tables[] = 'LEFT JOIN {db_prefix}follow_ups AS fu ON (fu.follow_up = t.id_topic)';
	}

	/**
	 * Show topics originated from the messages.
	 *
	 * @param int[] $messages
	 */
	public static function followup_message_list($messages)
	{
		global $context;

		require_once(SUBSDIR . '/FollowUps.subs.php');
		$context['follow_ups'] = followupTopics($messages, self::$includeUnapproved);
	}

	/**
	 * Prepares the data for the droppy with "child topics".
	 *
	 * @param mixed[] $topicinfo
	 * @param int $topic
	 * @param bool $includeUnapproved
	 */
	public function topicinfo($topicinfo, $topic, $includeUnapproved)
	{
		global $context, $scripturl;

		self::$includeUnapproved = $includeUnapproved;

		// If this topic was derived from another, set the followup details
		if (!empty($topicinfo['derived_from']))
		{
			require_once(SUBSDIR . '/FollowUps.subs.php');
			$context['topic_derived_from'] = topicStartedHere($topic, $includeUnapproved);

			// Derived from, set the link back
			if (!empty($context['topic_derived_from']))
				$context['links']['derived_from'] = $scripturl . '?msg=' . $context['topic_derived_from']['derived_from'];
		}
	}

	/**
	 * Can we show the button?
	 */
	public function prepare_context()
	{
		global $context;

		$context['can_follow_up'] = boardsAllowedTo('post_new') !== array();
	}
}
