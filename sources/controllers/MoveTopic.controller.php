<?php

/**
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
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class MoveTopic_Controller
{
	/**
	 * This function allows to move a topic, making sure to ask the moderator
	 * to give reason for topic move.
	 * It must be called with a topic specified. (that is, global $topic must
	 * be set... @todo fix this thing.)
	 * If the member is the topic starter requires the move_own permission,
	 * otherwise the move_any permission.
	 * Accessed via ?action=movetopic.
	 *
	 * @uses the MoveTopic template, main sub-template.
	 */
	function action_movetopic()
	{
		global $txt, $board, $topic, $user_info, $context, $language, $scripturl, $settings, $modSettings;

		$db = database();
		global $cat_tree, $boards, $boardList;

		if (empty($topic))
			fatal_lang_error('no_access', false);

		// Retrieve the basic topic information for whats being moved
		require_once(SUBSDIR . '/Topic.subs.php');
		$topic_info = getTopicInfo($topic, 'message');

		if (empty($topic_info))
			fatal_lang_error('topic_gone', false);

		$context['is_approved'] = $topic_info['approved'];
		$context['subject'] = $topic_info['subject'];

		// Can they see it - if not approved?
		if ($modSettings['postmod_active'] && !$context['is_approved'])
			isAllowedTo('approve_posts');

		// Are they allowed to actually move any topics or even their own?
		if (!allowedTo('move_any') && ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('move_own')))
			fatal_lang_error('cannot_move_any', false);

		loadTemplate('MoveTopic');

		// Get a list of boards this moderator can move to.
		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList(array('use_permissions' => true, 'not_redirection' => true));

		// No boards?
		if (empty($context['categories']) || $context['num_boards'] == 1)
			fatal_lang_error('moveto_noboards', false);

		$context['page_title'] = $txt['move_topic'];

		$context['linktree'][] = array(
			'url' => $scripturl . '?topic=' . $topic . '.0',
			'name' => $context['subject'],
		);
		$context['linktree'][] = array(
			'url' => '#',
			'name' => $txt['move_topic'],
		);

		$context['back_to_topic'] = isset($_REQUEST['goback']);

		// Ugly !
		if ($user_info['language'] != $language)
		{
			loadLanguage('index', $language);
			$temp = $txt['movetopic_default'];
			loadLanguage('index');
			$txt['movetopic_default'] = $temp;
		}

		// We will need this
		require_once(SUBSDIR . '/Topic.subs.php');
		moveTopicConcurrence();

		// Register this form and get a sequence number in $context.
		checkSubmitOnce('register');
	}

	/**
	 * Execute the move of a topic.
	 * It is called on the submit of action_movetopic.
	 * This function logs that topics have been moved in the moderation log.
	 * If the member is the topic starter requires the move_own permission,
	 * otherwise requires the move_any permission.
	 * Upon successful completion redirects to message index.
	 * Accessed via ?action=movetopic2.
	 *
	 * @uses subs/Post.subs.php.
	 */
	function action_movetopic2()
	{
		global $txt, $board, $topic, $scripturl, $modSettings, $context;
		global $board, $language, $user_info;

		$db = database();

		if (empty($topic))
			fatal_lang_error('no_access', false);

		// You can't choose to have a redirection topic and use an empty reason.
		if (isset($_POST['postRedirect']) && (!isset($_POST['reason']) || trim($_POST['reason']) == ''))
			fatal_lang_error('movetopic_no_reason', false);

		// You have to tell us were you are moving to
		if (!isset($_POST['toboard']))
			fatal_lang_error('movetopic_no_board', false);

		// We will need this
		require_once(SUBSDIR . '/Topic.subs.php');
		moveTopicConcurrence();

		// Make sure this form hasn't been submitted before.
		checkSubmitOnce('check');

		// Get the basic details on this topic
		$topic_info = getTopicInfo($topic);
		$context['is_approved'] = $topic_info['approved'];

		// Can they see it?
		if (!$context['is_approved'])
			isAllowedTo('approve_posts');

		// Can they move topics on this board?
		if (!allowedTo('move_any'))
		{
			if ($topic_info['id_member_started'] == $user_info['id'])
			{
				isAllowedTo('move_own');
				$boards = array_merge(boardsAllowedTo('move_own'), boardsAllowedTo('move_any'));
			}
			else
				isAllowedTo('move_any');
		}
		else
			$boards = boardsAllowedTo('move_any');

		// If this topic isn't approved don't let them move it if they can't approve it!
		if ($modSettings['postmod_active'] && !$context['is_approved'] && !allowedTo('approve_posts'))
		{
			// Only allow them to move it to other boards they can't approve it in.
			$can_approve = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');
			$boards = array_intersect($boards, $can_approve);
		}

		checkSession();
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// The destination board must be numeric.
		$toboard = (int) $_POST['toboard'];

		// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
		$board_info = boardInfo($toboard, $topic);
		if (empty($board_info))
			fatal_lang_error('no_board');

		// Remember this for later.
		$_SESSION['move_to_topic'] = $toboard;

		// Rename the topic...
		if (isset($_POST['reset_subject'], $_POST['custom_subject']) && $_POST['custom_subject'] != '')
		{
			$custom_subject = strtr(Util::htmltrim(Util::htmlspecialchars($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));

			// Keep checking the length.
			if (Util::strlen($custom_subject) > 100)
				$custom_subject = Util::substr($custom_subject, 0, 100);

			// If it's still valid move onwards and upwards.
			if ($custom_subject != '')
			{
				if (isset($_POST['enforce_subject']))
				{
					// Get a response prefix, but in the forum's default language.
					if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
					{
						if ($language === $user_info['language'])
							$context['response_prefix'] = $txt['response_prefix'];
						else
						{
							loadLanguage('index', $language, false);
							$context['response_prefix'] = $txt['response_prefix'];
							loadLanguage('index');
						}
						cache_put_data('response_prefix', $context['response_prefix'], 600);
					}

					$db->query('', '
						UPDATE {db_prefix}messages
						SET subject = {string:subject}
						WHERE id_topic = {int:current_topic}',
						array(
							'current_topic' => $topic,
							'subject' => $context['response_prefix'] . $custom_subject,
						)
					);
				}

				$db->query('', '
					UPDATE {db_prefix}messages
					SET subject = {string:custom_subject}
					WHERE id_msg = {int:id_first_msg}',
					array(
						'id_first_msg' => $topic_info['id_first_msg'],
						'custom_subject' => $custom_subject,
					)
				);

				// Fix the subject cache.
				updateStats('subject', $topic, $custom_subject);
			}
		}

		// Create a link to this in the old board.
		// @todo Does this make sense if the topic was unapproved before? I'd just about say so.
		if (isset($_POST['postRedirect']))
		{
			// Should be in the boardwide language.
			if ($user_info['language'] != $language)
				loadLanguage('index', $language);

			$reason = Util::htmlspecialchars($_POST['reason'], ENT_QUOTES);
			preparsecode($reason);

			// Add a URL onto the message.
			$reason = strtr($reason, array(
				$txt['movetopic_auto_board'] => '[url=' . $scripturl . '?board=' . $toboard . '.0]' . $board_info['name'] . '[/url]',
				$txt['movetopic_auto_topic'] => '[iurl]' . $scripturl . '?topic=' . $topic . '.0[/iurl]'
			));

			// auto remove this MOVED redirection topic in the future?
			$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

			// redirect to the MOVED topic from topic list?
			$redirect_topic = isset($_POST['redirect_topic']) ? $topic : 0;

			$msgOptions = array(
				'subject' => $txt['moved'] . ': ' . $board_info['subject'],
				'body' => $reason,
				'icon' => 'moved',
				'smileys_enabled' => 1,
			);
			$topicOptions = array(
				'board' => $board,
				'lock_mode' => 1,
				'mark_as_read' => true,
				'redirect_expires' => $redirect_expires,
				'redirect_topic' => $redirect_topic,
			);
			$posterOptions = array(
				'id' => $user_info['id'],
				'update_post_count' => empty($board_info['count_posts']),
			);
			createPost($msgOptions, $topicOptions, $posterOptions);
		}

		$board_from = boardInfo($board);

		if ($board_from['count_posts'] != $board_info['count_posts'])
		{
			$request = $db->query('', '
				SELECT id_member
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND approved = {int:is_approved}',
				array(
					'current_topic' => $topic,
					'is_approved' => 1,
				)
			);
			$posters = array();
			while ($row = $db->fetch_assoc($request))
			{
				if (!isset($posters[$row['id_member']]))
					$posters[$row['id_member']] = 0;

				$posters[$row['id_member']]++;
			}
			$db->free_result($request);

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

		// Do the move (includes statistics update needed for the redirect topic).
		moveTopics($topic, $toboard);

		// Log that they moved this topic.
		if (!allowedTo('move_own') || $topic_info['id_member_started'] != $user_info['id'])
			logAction('move', array('topic' => $topic, 'board_from' => $board, 'board_to' => $toboard));

		// Notify people that this topic has been moved?
		sendNotifications($topic, 'move');

		// Why not go back to the original board in case they want to keep moving?
		if (!isset($_REQUEST['goback']))
			redirectexit('board=' . $board . '.0');
		else
			redirectexit('topic=' . $topic . '.0');
	}
}