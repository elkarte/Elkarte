<?php

/**
 * Random module is a collection of small stuff not worth it's own module.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Random;

/**
 * Class \ElkArte\Modules\Random\Post
 *
 * Collection of small items not requiring a separate module
 */
class Post extends \ElkArte\Modules\AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager)
	{
		global $modSettings;

		$return = array();

		if (!empty($modSettings['enableFollowup']))
		{
			$return[] = array('prepare_context', array('\\ElkArte\\Modules\\Random\\Post', 'prepare_context_followup'), array());

			add_integration_function('integrate_create_topic', '\\ElkArte\\Modules\\Random\\Post::followup_create_topic', '', false);
		}

		return $return;
	}

	/**
	 * Create a followup.
	 *
	 * @param mixed[] $msgOptions
	 * @param mixed[] $topicOptions
	 * @param mixed[] $posterOptions
	 */
	public static function followup_create_topic($msgOptions, $topicOptions, $posterOptions)
	{
		if (!empty($_REQUEST['followup']))
			$original_post = (int) $_REQUEST['followup'];

		require_once(SUBSDIR . '/FollowUps.subs.php');

		// Time to update the original message with a pointer to the new one
		if (!empty($original_post) && canAccessMessage($original_post))
			linkMessages($original_post, $topicOptions['id']);
	}

	/**
	 * Show followups.
	 */
	public function prepare_context_followup()
	{
		global $context, $board;

		// Are we moving a discussion to its own topic?
		if (!empty($_REQUEST['followup']))
		{
			$context['original_post'] = isset($_REQUEST['quote']) ? (int) $_REQUEST['quote'] : (int) $_REQUEST['followup'];
			$context['show_boards_dropdown'] = true;

			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList(array('not_redirection' => true, 'allowed_to' => array('post_new', 'post_unapproved_topics')));
			$context['boards_current_disabled'] = false;

			if (!empty($context['categories']))
			{
				foreach ($context['categories'] as $id => $values)
				{
					if (isset($values['boards'][$board]))
					{
						$context['categories'][$id]['boards'][$board]['selected'] = true;
						break;
					}
				}
			}
		}
	}
}
