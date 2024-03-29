<?php

/**
 *
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Random;

use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Random\Display
 */
class Display extends AbstractModule
{
	/** @var bool */
	protected static $includeUnapproved = false;

	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		$return = [];

		if (!empty($modSettings['enableFollowup']))
		{
			$return = [
				['topicinfo', [Display::class, 'topicinfo'], ['topicinfo', 'topic', 'includeUnapproved']],
				['prepare_context', [Display::class, 'prepare_context'], []]
			];

			add_integration_function('integrate_topic_query', '\\ElkArte\\Modules\\Random\\Display::followup_topic_query', '', false);
			add_integration_function('integrate_display_message_list', '\\ElkArte\\Modules\\Random\\Display::followup_message_list', '', false);
		}

		if (!empty($modSettings['likes_enabled']))
		{
			add_integration_function('integrate_display_message_list', '\\ElkArte\\Modules\\Random\\Display::load_likes', '', false);
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
	 * Show topics originated from the messages.  Called from integrate_display_message_list
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
	 * Show likes.  Called from integrate_display_message_list
	 *
	 * @param int[] $messages
	 */
	public static function load_likes($messages)
	{
		global $context;

		if (!empty($messages))
		{
			require_once(SUBSDIR . '/Likes.subs.php');
			$context['likes'] = loadLikes($messages, true);
			theme()->getLayers()->addBefore('load_likes_button', 'body');
		}
	}

	/**
	 * Prepares the data for the droppy with "child topics".
	 *
	 * @param array $topicinfo
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
			{
				$context['links']['derived_from'] = $scripturl . '?msg=' . $context['topic_derived_from']['derived_from'];
			}
		}
	}

	/**
	 * Can we show the button?
	 */
	public function prepare_context()
	{
		global $context;

		$context['can_follow_up'] = boardsAllowedTo('post_new') !== [];
	}
}
