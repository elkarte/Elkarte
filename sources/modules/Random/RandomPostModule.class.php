<?php

/**
 * 
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

class Random_Post_Module
{
	public static function hooks()
	{
		global $modSettings;

		$return = array();

		if (!empty($modSettings['enableFollowup']))
		{
			$return[] = array('prepare_context', array('Random_Post_Module', 'prepare_context_followup'), array());
			add_integration_function('integrate_create_topic', 'Random_Post_Module::followup_create_topic', '', false);
		}

		return $return;
	}

	public static function followup_create_topic($msgOptions, $topicOptions, $posterOptions)
	{
		if (!empty($_REQUEST['followup']))
			$original_post = (int) $_REQUEST['followup'];

		require_once(SUBSDIR . '/FollowUps.subs.php');

		// Time to update the original message with a pointer to the new one
		if (!empty($original_post) && canAccessMessage($original_post))
			linkMessages($original_post, $topicOptions['id']);
	}

	public function prepare_context_followup()
	{
		global $context;

		// Are we moving a discussion to its own topic?
		if (!empty($_REQUEST['followup']))
		{
			$context['original_post'] = isset($_REQUEST['quote']) ? (int) $_REQUEST['quote'] : (int) $_REQUEST['followup'];
			$context['show_boards_dropdown'] = true;

			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList(array('not_redirection' => true, 'allowed_to' => 'post_new'));
			$context['boards_current_disabled'] = false;
			if (!empty($board))
			{
				foreach ($context['categories'] as $id => $values)
					if (isset($values['boards'][$board]))
					{
						$context['categories'][$id]['boards'][$board]['selected'] = true;
						break;
					}
			}
		}
	}
}