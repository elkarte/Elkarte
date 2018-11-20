<?php

/**
 * Handles the moving of topics from board to board
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Move Topic Controller
 */
class MoveTopic extends \ElkArte\AbstractController
{
	/**
	 * The id of the topic being manipulated
	 * @var int
	 */
	private $_topic;

	/**
	 * Information about the topic being moved
	 * @var array
	 */
	private $_topic_info;

	/**
	 * Information about the board where the topic resides
	 * @var array
	 */
	private $_board_info;

	/**
	 * Board that will receive the topic
	 * @var int
	 */
	private $_toboard;

	/**
	 * Pre Dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		global $topic;

		// Set the topic from the global, yes yes
		$this->_topic = $topic;
	}

	/**
	 * Forwards to the action method to handle the action.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// move a topic, what else?!
		// $this->action_movetopic();
	}

	/**
	 * This function allows to move a topic
	 *
	 * What it does:
	 *
	 * - It must be called with a topic specified. (that is, global $topic must
	 * be set... @todo fix this thing.)
	 * - Validates access
	 * - Accessed via ?action=movetopic.
	 *
	 * @uses template_move_topic() sub-template in MoveTopic.template.php
	 */
	public function action_movetopic()
	{
		global $context;

		// Lets make sure they can access the topic being moved and have permissions to move it
		$this->_check_access();

		// Get a list of boards this moderator can move to.
		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList(array('not_redirection' => true));

		// No boards?
		if (empty($context['categories']) || $context['num_boards'] == 1)
			throw new \ElkArte\Exceptions\Exception('moveto_noboards', false);

		// Already used the function, let's set the selected board back to the last
		$last_moved_to = isset($_SESSION['move_to_topic']['move_to']) && $_SESSION['move_to_topic']['move_to'] != $context['current_board'] ? (int) $_SESSION['move_to_topic']['move_to'] : 0;
		if (!empty($last_moved_to))
		{
			foreach ($context['categories'] as $id => $values)
			{
				if (isset($values['boards'][$last_moved_to]))
				{
					$context['categories'][$id]['boards'][$last_moved_to]['selected'] = true;
					break;
				}
			}
		}

		// Set up for the template
		theme()->getTemplates()->load('MoveTopic');
		$this->_prep_template();
	}

	/**
	 * Executes the actual move of a topic.
	 *
	 * What it does:
	 *
	 * - It is called on the submit of action_movetopic.
	 * - This function logs that topics have been moved in the moderation log.
	 * - Upon successful completion redirects to message index.
	 * - Accessed via ?action=movetopic2.
	 *
	 * @uses subs/Post.subs.php.
	 */
	public function action_movetopic2()
	{
		global $board, $user_info;

		$this->_check_access_2();

		checkSession();
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// The destination board must be numeric.
		$this->_toboard = (int) $this->_req->post->toboard;

		// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
		$this->_board_info = boardInfo($this->_toboard, $this->_topic);
		if (empty($this->_board_info))
			throw new \ElkArte\Exceptions\Exception('no_board');

		// Remember this for later.
		$_SESSION['move_to_topic'] = array(
			'move_to' => $this->_toboard
		);

		// Rename the topic if needed
		$this->_rename_topic();

		// Create a link to this in the old board.
		$this->_post_redirect();

		// Account for boards that count posts and those that don't
		$this->_count_update();

		// Do the move (includes statistics update needed for the redirect topic).
		moveTopics($this->_topic, $this->_toboard);

		// Log that they moved this topic.
		if (!allowedTo('move_own') || $this->_topic_info['id_member_started'] != $user_info['id'])
			logAction('move', array('topic' => $this->_topic, 'board_from' => $board, 'board_to' => $this->_toboard));

		// Notify people that this topic has been moved?
		require_once(SUBSDIR . '/Notification.subs.php');
		sendNotifications($this->_topic, 'move');

		// Why not go back to the original board in case they want to keep moving?
		if (!isset($this->_req->post->goback))
			redirectexit('board=' . $board . '.0');
		else
			redirectexit('topic=' . $this->_topic . '.0');
	}

	/**
	 * Prepares the content for use in the move topic template
	 */
	private function _prep_template()
	{
		global $context, $txt, $scripturl, $user_info, $language, $board;

		$context['is_approved'] = $this->_topic_info['approved'];
		$context['subject'] = $this->_topic_info['subject'];
		$context['redirect_topic'] = isset($_SESSION['move_to_topic']['redirect_topic']) ? (int) $_SESSION['move_to_topic']['redirect_topic'] : 0;
		$context['redirect_expires'] = isset($_SESSION['move_to_topic']['redirect_expires']) ? (int) $_SESSION['move_to_topic']['redirect_expires'] : 0;
		$context['page_title'] = $txt['move_topic'];
		$context['sub_template'] = 'move_topic';

		// Breadcrumbs
		$context['linktree'][] = array(
			'url' => $scripturl . '?topic=' . $this->_topic . '.0',
			'name' => $context['subject'],
		);
		$context['linktree'][] = array(
			'url' => '#',
			'name' => $txt['move_topic'],
		);

		$context['back_to_topic'] = isset($this->_req->post->goback);

		// Ugly !
		if ($user_info['language'] != $language)
		{
			theme()->getTemplates()->loadLanguageFile('index', $language);
			$temp = $txt['movetopic_default'];
			theme()->getTemplates()->loadLanguageFile('index');
			$txt['movetopic_default'] = $temp;
		}

		// We will need this
		if (isset($this->_req->query->current_board))
		{
			moveTopicConcurrence((int) $this->_req->query->current_board, $board, $this->_topic);
		}

		// Register this form and get a sequence number in $context.
		checkSubmitOnce('register');
	}

	/**
	 * Validates that the member can access the topic
	 *
	 * What it does:
	 *
	 * - Checks that a topic is supplied
	 * - Validates the topic information can be loaded
	 * - If the topic is not approved yet, must have approve permissions to move it
	 * - If the member is the topic starter requires the move_own permission, otherwise the move_any permission.
	 */
	private function _check_access()
	{
		global $modSettings, $user_info;

		if (empty($this->_topic))
			throw new \ElkArte\Exceptions\Exception('no_access', false);

		// Retrieve the basic topic information for whats being moved
		require_once(SUBSDIR . '/Topic.subs.php');
		$this->_topic_info = getTopicInfo($this->_topic, 'message');

		if (empty($this->_topic_info))
			throw new \ElkArte\Exceptions\Exception('topic_gone', false);

		// Can they see it - if not approved?
		if ($modSettings['postmod_active'] && !$this->_topic_info['approved'])
			isAllowedTo('approve_posts');

		// Are they allowed to actually move any topics or even their own?
		if (!allowedTo('move_any') && ($this->_topic_info['id_member_started'] == $user_info['id'] && !allowedTo('move_own')))
			throw new \ElkArte\Exceptions\Exception('cannot_move_any', false);
	}

	/**
	 * Checks access and input validation before committing the move
	 *
	 * What it does:
	 *
	 * - Checks that a topic is supplied
	 * - Validates the move location
	 * - Checks redirection details if its a redirection is to be posted
	 * - If the member is the topic starter requires the move_own permission, otherwise the move_any permission.
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception no_access
	 */
	private function _check_access_2()
	{
		global $user_info, $board;

		if (empty($this->_topic))
			throw new \ElkArte\Exceptions\Exception('no_access', false);

		// You can't choose to have a redirection topic and not provide a reason.
		if (isset($this->_req->post->postRedirect) && $this->_req->getPost('reason', 'trim', '') === '')
			throw new \ElkArte\Exceptions\Exception('movetopic_no_reason', false);

		// You have to tell us were you are moving to
		if (!isset($this->_req->post->toboard))
			throw new \ElkArte\Exceptions\Exception('movetopic_no_board', false);

		// We will need this
		require_once(SUBSDIR . '/Topic.subs.php');
		if (isset($this->_req->query->current_board))
		{
			moveTopicConcurrence((int) $this->_req->query->current_board, $board, $this->_topic);
		}

		// Make sure this form hasn't been submitted before.
		checkSubmitOnce('check');

		// Get the basic details on this topic (again)
		$this->_topic_info = getTopicInfo($this->_topic);

		// Not approved then you need approval permissions to move it as well
		if (!$this->_topic_info['approved'])
			isAllowedTo('approve_posts');

		// Can they move topics on this board?
		if (!allowedTo('move_any'))
		{
			if ($this->_topic_info['id_member_started'] == $user_info['id'])
			{
				isAllowedTo('move_own');
			}
			else
			{
				isAllowedTo('move_any');
			}
		}

		return true;
	}

	/**
	 * Renames the topic during the move if requested
	 *
	 * What it does:
	 *
	 * - Renames the moved topic with a new topic subject
	 * - If enforce_subject is set, renames all posts withing the moved topic posts with a new subject
	 */
	private function _rename_topic()
	{
		global $context;

		// Rename the topic...
		if (isset($this->_req->post->reset_subject, $this->_req->post->custom_subject) && $this->_req->post->custom_subject != '')
		{
			$custom_subject = strtr(\ElkArte\Util::htmltrim(\ElkArte\Util::htmlspecialchars($this->_req->post->custom_subject)), array("\r" => '', "\n" => '', "\t" => ''));

			// Keep checking the length.
			if (\ElkArte\Util::strlen($custom_subject) > 100)
				$custom_subject = \ElkArte\Util::substr($custom_subject, 0, 100);

			// If it's still valid move onwards and upwards.
			if ($custom_subject != '')
			{
				$all_messages = isset($this->_req->post->enforce_subject);
				if ($all_messages)
				{
					// Get a response prefix, but in the forum's default language.
					$context['response_prefix'] = response_prefix();

					topicSubject($this->_topic_info, $custom_subject, $context['response_prefix'], $all_messages);
				}
				else
					topicSubject($this->_topic_info, $custom_subject);

				// Fix the subject cache.
				require_once(SUBSDIR . '/Messages.subs.php');
				updateSubjectStats($this->_topic, $custom_subject);
			}
		}
	}

	/**
	 * Posts a redirection topic in the original location of the moved topic
	 *
	 * What it does:
	 *
	 * - If leaving a moved "where did it go" topic, validates the needed inputs
	 * - Posts a new topic in the originating board of the topic to be moved.
	 */
	private function _post_redirect()
	{
		global $txt, $board, $scripturl, $language, $user_info;

		// @todo Does this make sense if the topic was unapproved before? I'd just about say so.
		if (isset($this->_req->post->postRedirect))
		{
			// Should be in the boardwide language.
			if ($user_info['language'] != $language)
				theme()->getTemplates()->loadLanguageFile('index', $language);

			$reason = \ElkArte\Util::htmlspecialchars($this->_req->post->reason, ENT_QUOTES);
			preparsecode($reason);

			// Add a URL onto the message.
			$reason = strtr($reason, array(
				$txt['movetopic_auto_board'] => '[url=' . $scripturl . '?board=' . $this->_toboard . '.0]' . $this->_board_info['name'] . '[/url]',
				$txt['movetopic_auto_topic'] => '[iurl]' . $scripturl . '?topic=' . $this->_topic . '.0[/iurl]'
			));

			// Auto remove this MOVED redirection topic in the future?
			$redirect_expires = !empty($this->_req->post->redirect_expires) ? (int) $this->_req->post->redirect_expires : 0;

			// Redirect to the MOVED topic from topic list?
			$redirect_topic = isset($this->_req->post->redirect_topic) ? $this->_topic : 0;

			// And remember the last expiry period too.
			$_SESSION['move_to_topic']['redirect_topic'] = $redirect_topic;
			$_SESSION['move_to_topic']['redirect_expires'] = $redirect_expires;

			$msgOptions = array(
				'subject' => $txt['moved'] . ': ' . $this->_board_info['subject'],
				'body' => $reason,
				'icon' => 'moved',
				'smileys_enabled' => 1,
			);

			$topicOptions = array(
				'board' => $board,
				'lock_mode' => 1,
				'mark_as_read' => true,
				'redirect_expires' => empty($redirect_expires) ? 0 : ($redirect_expires * 60) + time(),
				'redirect_topic' => $redirect_topic,
			);

			$posterOptions = array(
				'id' => $user_info['id'],
				'update_post_count' => empty($this->_board_info['count_posts']),
			);
			createPost($msgOptions, $topicOptions, $posterOptions);
		}
	}

	/**
	 * Accounts for board / user post counts when a topic is moved.
	 *
	 * What it does:
	 *
	 * - Checks if a topic is being moved to/from a board that does/does'nt count posts.
	 */
	private function _count_update()
	{
		global $board;

		$board_from = boardInfo($board);
		if ($board_from['count_posts'] != $this->_board_info['count_posts'])
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$posters = postersCount($this->_topic);

			foreach ($posters as $id_member => $posts)
			{
				// The board we're moving from counted posts, but not to.
				if (empty($board_from['count_posts']))
					updateMemberData($id_member, array('posts' => 'posts - ' . $posts));
				// The reverse: from didn't, to did.
				else
					updateMemberData($id_member, array('posts' => 'posts + ' . $posts));
			}
		}
	}
}
