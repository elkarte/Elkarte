<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
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

if (!defined('DIALOGO'))
	die('Hacking attempt...');

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
function MoveTopic()
{
	global $txt, $board, $topic, $user_info, $context, $language, $scripturl, $settings, $smcFunc, $sourcedir, $modSettings;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, ms.subject, t.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($id_member_started, $context['subject'], $context['is_approved']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Can they see it - if not approved?
	if ($modSettings['postmod_active'] && !$context['is_approved'])
		isAllowedTo('approve_posts');

	// Permission check!
	// @todo
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == $user_info['id'])
		{
			isAllowedTo('move_own');
			//$boards = array_merge(boardsAllowedTo('move_own'), boardsAllowedTo('move_any'));
		}
		else
			isAllowedTo('move_any');
	}
	//else
		//$boards = boardsAllowedTo('move_any');

	loadTemplate('MoveTopic');

	// Get a list of boards this moderator can move to.
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND b.redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
			'current_board' => $board,
		)
	);
	$number_of_boards = $smcFunc['db_num_rows']($request);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array (
				'name' => strip_tags($row['cat_name']),
				'boards' => array(),
			);

		$context['categories'][$row['id_cat']]['boards'][] = array(
			'id' => $row['id_board'],
			'name' => strip_tags($row['name']),
			'category' => strip_tags($row['cat_name']),
			'child_level' => $row['child_level'],
			'selected' => !empty($_SESSION['move_to_topic']) && $_SESSION['move_to_topic'] == $row['id_board'] && $row['id_board'] != $board,
		);
	}
	$smcFunc['db_free_result']($request);

	if (empty($context['categories']) || (!empty($number_of_boards) && $number_of_boards == 1))
		fatal_lang_error('moveto_noboards', false);

	$context['page_title'] = $txt['move_topic'];

	$context['linktree'][] = array(
		'url' => $scripturl . '?topic=' . $topic . '.0',
		'name' => $context['subject'],
	);

	$context['linktree'][] = array(
		'name' => $txt['move_topic'],
	);

	$context['back_to_topic'] = isset($_REQUEST['goback']);

	if ($user_info['language'] != $language)
	{
		loadLanguage('index', $language);
		$temp = $txt['movetopic_default'];
		loadLanguage('index');

		$txt['movetopic_default'] = $temp;
	}

	// We will need this
	require_once($sourcedir . '/Subs-Topic.php');
	moveTopicConcurrence();

	// Register this form and get a sequence number in $context.
	checkSubmitOnce('register');
}

/**
 * Execute the move of a topic.
 * It is called on the submit of MoveTopic.
 * This function logs that topics have been moved in the moderation log.
 * If the member is the topic starter requires the move_own permission,
 * otherwise requires the move_any permission.
 * Upon successful completion redirects to message index.
 * Accessed via ?action=movetopic2.
 *
 * @uses Subs-Post.php.
 */
function MoveTopic2()
{
	global $txt, $board, $topic, $scripturl, $sourcedir, $modSettings, $context;
	global $board, $language, $user_info, $smcFunc;

	if (empty($topic))
		fatal_lang_error('no_access', false);

	// You can't choose to have a redirection topic and use an empty reason.
	if (isset($_POST['postRedirect']) && (!isset($_POST['reason']) || trim($_POST['reason']) == ''))
		fatal_lang_error('movetopic_no_reason', false);

	// We will need this
	require_once($sourcedir . '/Subs-Topic.php');
	moveTopicConcurrence();

	// Make sure this form hasn't been submitted before.
	checkSubmitOnce('check');

	$request = $smcFunc['db_query']('', '
		SELECT id_member_started, id_first_msg, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($id_member_started, $id_first_msg, $context['is_approved']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Can they see it?
	if (!$context['is_approved'])
		isAllowedTo('approve_posts');

	// Can they move topics on this board?
	if (!allowedTo('move_any'))
	{
		if ($id_member_started == $user_info['id'])
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
		$can_approve = boardsAllowedTo('approve_posts');
		$boards = array_intersect($boards, $can_approve);
	}

	checkSession();
	require_once($sourcedir . '/Subs-Post.php');

	// The destination board must be numeric.
	$_POST['toboard'] = (int) $_POST['toboard'];

	// Make sure they can see the board they are trying to move to (and get whether posts count in the target board).
	$request = $smcFunc['db_query']('', '
		SELECT b.count_posts, b.name, m.subject
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE {query_see_board}
			AND b.id_board = {int:to_board}
			AND b.redirect = {string:blank_redirect}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'to_board' => $_POST['toboard'],
			'blank_redirect' => '',
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board');
	list ($pcounter, $board_name, $subject) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Remember this for later.
	$_SESSION['move_to_topic'] = $_POST['toboard'];

	// Rename the topic...
	if (isset($_POST['reset_subject'], $_POST['custom_subject']) && $_POST['custom_subject'] != '')
	{
		$_POST['custom_subject'] = strtr($smcFunc['htmltrim']($smcFunc['htmlspecialchars']($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));
		// Keep checking the length.
		if ($smcFunc['strlen']($_POST['custom_subject']) > 100)
			$_POST['custom_subject'] = $smcFunc['substr']($_POST['custom_subject'], 0, 100);

		// If it's still valid move onwards and upwards.
		if ($_POST['custom_subject'] != '')
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

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET subject = {string:subject}
					WHERE id_topic = {int:current_topic}',
					array(
						'current_topic' => $topic,
						'subject' => $context['response_prefix'] . $_POST['custom_subject'],
					)
				);
			}

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET subject = {string:custom_subject}
				WHERE id_msg = {int:id_first_msg}',
				array(
					'id_first_msg' => $id_first_msg,
					'custom_subject' => $_POST['custom_subject'],
				)
			);

			// Fix the subject cache.
			updateStats('subject', $topic, $_POST['custom_subject']);
		}
	}

	// Create a link to this in the old board.
	// @todo Does this make sense if the topic was unapproved before? I'd just about say so.
	if (isset($_POST['postRedirect']))
	{
		// Should be in the boardwide language.
		if ($user_info['language'] != $language)
			loadLanguage('index', $language);

		$_POST['reason'] = $smcFunc['htmlspecialchars']($_POST['reason'], ENT_QUOTES);
		preparsecode($_POST['reason']);

		// Add a URL onto the message.
		$_POST['reason'] = strtr($_POST['reason'], array(
			$txt['movetopic_auto_board'] => '[url=' . $scripturl . '?board=' . $_POST['toboard'] . '.0]' . $board_name . '[/url]',
			$txt['movetopic_auto_topic'] => '[iurl]' . $scripturl . '?topic=' . $topic . '.0[/iurl]'
		));

		// auto remove this MOVED redirection topic in the future?
		$redirect_expires = !empty($_POST['redirect_expires']) ? ((int) ($_POST['redirect_expires'] * 60) + time()) : 0;

		// redirect to the MOVED topic from topic list?
		$redirect_topic = isset($_POST['redirect_topic']) ? $topic : 0;

		$msgOptions = array(
			'subject' => $txt['moved'] . ': ' . $subject,
			'body' => $_POST['reason'],
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
			'update_post_count' => empty($pcounter),
		);
		createPost($msgOptions, $topicOptions, $posterOptions);
	}

	$request = $smcFunc['db_query']('', '
		SELECT count_posts
		FROM {db_prefix}boards
		WHERE id_board = {int:current_board}
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);
	list ($pcounter_from) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($pcounter_from != $pcounter)
	{
		$request = $smcFunc['db_query']('', '
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
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($posters[$row['id_member']]))
				$posters[$row['id_member']] = 0;

			$posters[$row['id_member']]++;
		}
		$smcFunc['db_free_result']($request);

		foreach ($posters as $id_member => $posts)
		{
			// The board we're moving from counted posts, but not to.
			if (empty($pcounter_from))
				updateMemberData($id_member, array('posts' => 'posts - ' . $posts));
			// The reverse: from didn't, to did.
			else
				updateMemberData($id_member, array('posts' => 'posts + ' . $posts));
		}
	}

	// Do the move (includes statistics update needed for the redirect topic).
	moveTopics($topic, $_POST['toboard']);

	// Log that they moved this topic.
	if (!allowedTo('move_own') || $id_member_started != $user_info['id'])
		logAction('move', array('topic' => $topic, 'board_from' => $board, 'board_to' => $_POST['toboard']));
	// Notify people that this topic has been moved?
	sendNotifications($topic, 'move');

	// Why not go back to the original board in case they want to keep moving?
	if (!isset($_REQUEST['goback']))
		redirectexit('board=' . $board . '.0');
	else
		redirectexit('topic=' . $topic . '.0');
}
