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
 * Handle splitting of topics
 *
 * Original module by Mach8 - We'll never forget you.
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Splits a topic into two topics.
 * delegates to the other functions (based on the URL parameter 'sa').
 * loads the SplitTopics template.
 * requires the split_any permission.
 * is accessed with ?action=splittopics.
 */
function action_splittopics()
{
	global $topic;

	// And... which topic were you splitting, again?
	if (empty($topic))
		fatal_lang_error('numbers_one_to_nine', false);

	// Are you allowed to split topics?
	isAllowedTo('split_any');

	// Load up the "dependencies" - the template, getMsgMemberID(), and sendNotifications().
	if (!isset($_REQUEST['xml']))
		loadTemplate('SplitTopics');
	require_once(SUBSDIR . '/Boards.subs.php');
	require_once(SUBSDIR . '/Post.subs.php');

	$subActions = array(
		'selectTopics' => 'action_splitSelectTopics',
		'execute' => 'action_splitExecute',
		'index' => 'action_splitIndex',
		'splitSelection' => 'action_splitSelection',
	);

	// ?action=splittopics;sa=LETSBREAKIT won't work, sorry.
	if (empty($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		action_splitIndex();
	else
		$subActions[$_REQUEST['sa']]();
}

/**
 * Screen shown before the actual split.
 * is accessed with ?action=splittopics;sa=index.
 * default sub action for ?action=splittopics.
 * uses 'ask' sub template of the SplitTopics template.
 * redirects to action_splitSelectTopics if the message given turns out to be
 * the first message of a topic.
 * shows the user three ways to split the current topic.
 */
function action_splitIndex()
{
	global $txt, $topic, $context, $smcFunc, $modSettings;

	// Validate "at".
	if (empty($_GET['at']))
		fatal_lang_error('numbers_one_to_nine', false);
	$splitAt = (int) $_GET['at'];

	// We deal with topics here.
	require_once(SUBSDIR . '/Topic.subs.php');

	// Retrieve message info for the message at the split point.
	$messageInfo = messageInfo($topic, $splitAt);

	// If not approved validate they can see it.
	if ($modSettings['postmod_active'] && !$messageInfo['approved'])
		isAllowedTo('approve_posts');

	// If this topic has unapproved posts, we need to count them too...
	if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
		$messageInfo['num_replies'] += $messageInfo['unapproved_posts'] - ($messageInfo['approved'] ? 0 : 1);

	// Check if there is more than one message in the topic.  (there should be.)
	if ($messageInfo['num_replies'] < 1)
		fatal_lang_error('topic_one_post', false);

	// Check if this is the first message in the topic (if so, the first and second option won't be available)
	if ($messageInfo['id_first_msg'] == $splitAt)
		return action_splitSelectTopics();

	// Basic template information....
	$context['message'] = array(
		'id' => $splitAt,
		'subject' => $messageInfo['subject']
	);
	$context['sub_template'] = 'ask';
	$context['page_title'] = $txt['split'];
}

/**
 * Do the actual split.
 * is accessed with ?action=splittopics;sa=execute.
 * uses the main SplitTopics template.
 * supports three ways of splitting:
 * (1) only one message is split off.
 * (2) all messages after and including a given message are split off.
 * (3) select topics to split (redirects to action_splitSelectTopics()).
 * uses splitTopic function to do the actual splitting.
 */
function action_splitExecute()
{
	global $txt, $board, $topic, $context, $user_info, $smcFunc, $modSettings;

	// Check the session to make sure they meant to do this.
	checkSession();

	// Clean up the subject.
	if (!isset($_POST['subname']) || $_POST['subname'] == '')
		$_POST['subname'] = $txt['new_topic'];

	// Redirect to the selector if they chose selective.
	if ($_POST['step2'] == 'selective')
	{
		$_REQUEST['subname'] = $_POST['subname'];
		return action_splitSelectTopics();
	}

	// We work with them topics.
	require_once(SUBSDIR . '/Topic.subs.php');

	$splitAt = (int) $_POST['at'];
	$messagesToBeSplit = array();

	// Fetch the message IDs of the topic that are at or after the message.
	if ($_POST['step2'] == 'afterthis')
		$messagesToBeSplit = messagesAfter($topic, $splitAt);
	// Only the selected message has to be split. That should be easy.
	elseif ($_POST['step2'] == 'onlythis')
		$messagesToBeSplit[] = $splitAt;
	// There's another action?!
	else
		fatal_lang_error('no_access', false);

	$context['old_topic'] = $topic;
	$context['new_topic'] = splitTopic($topic, $messagesToBeSplit, $_POST['subname']);
	$context['page_title'] = $txt['split'];
}

/**
 * Allows the user to select the messages to be split.
 * is accessed with ?action=splittopics;sa=selectTopics.
 * uses 'select' sub template of the SplitTopics template or (for
 * XMLhttp) the 'split' sub template of the Xml template.
 * supports XMLhttp for adding/removing a message to the selection.
 * uses a session variable to store the selected topics.
 * shows two independent page indexes for both the selected and
 * not-selected messages (;topic=1.x;start2=y).
 */
function action_splitSelectTopics()
{
	global $txt, $scripturl, $topic, $context, $modSettings, $original_msgs, $options;
	global $smcFunc;

	$context['page_title'] = $txt['split'] . ' - ' . $txt['select_split_posts'];

	// Haven't selected anything have we?
	$_SESSION['split_selection'][$topic] = empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic];

	// This is a special case for split topics from quick-moderation checkboxes
	if (isset($_REQUEST['subname_enc']))
		$_REQUEST['subname'] = urldecode($_REQUEST['subname_enc']);

	require_once(SUBSDIR . '/Topic.subs.php');

	$context['not_selected'] = array(
		'num_messages' => 0,
		'start' => empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'],
		'messages' => array(),
	);

	$context['selected'] = array(
		'num_messages' => 0,
		'start' => empty($_REQUEST['start2']) ? 0 : (int) $_REQUEST['start2'],
		'messages' => array(),
	);

	$context['topic'] = array(
		'id' => $topic,
		'subject' => urlencode($_REQUEST['subname']),
	);

	// Some stuff for our favorite template.
	$context['new_subject'] = $_REQUEST['subname'];

	// Using the "select" sub template.
	$context['sub_template'] = isset($_REQUEST['xml']) ? 'split' : 'select';

	// Are we using a custom messages per page?
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	// Get the message ID's from before the move.
	if (isset($_REQUEST['xml']))
	{
		$original_msgs = array(
			'not_selected' => array(),
			'selected' => array(),
		);
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][$topic]) ? '' : '
				AND id_msg NOT IN ({array_int:no_split_msgs})') . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . '
			ORDER BY id_msg DESC
			LIMIT {int:start}, {int:messages_per_page}',
			array(
				'current_topic' => $topic,
				'no_split_msgs' => empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
				'start' => $context['not_selected']['start'],
				'messages_per_page' => $context['messages_per_page'],
			)
		);
		// You can't split the last message off.
		if (empty($context['not_selected']['start']) && $smcFunc['db_num_rows']($request) <= 1 && $_REQUEST['move'] == 'down')
			$_REQUEST['move'] = '';
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$original_msgs['not_selected'][] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
		if (!empty($_SESSION['split_selection'][$topic]))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg IN ({array_int:split_msgs})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}') . '
				ORDER BY id_msg DESC
				LIMIT {int:start}, {int:messages_per_page}',
				array(
					'current_topic' => $topic,
					'split_msgs' => $_SESSION['split_selection'][$topic],
					'is_approved' => 1,
					'start' => $context['selected']['start'],
					'messages_per_page' => $context['messages_per_page'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$original_msgs['selected'][] = $row['id_msg'];
			$smcFunc['db_free_result']($request);
		}
	}

	// (De)select a message..
	if (!empty($_REQUEST['move']))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		if ($_REQUEST['move'] == 'reset')
			$_SESSION['split_selection'][$topic] = array();
		elseif ($_REQUEST['move'] == 'up')
			$_SESSION['split_selection'][$topic] = array_diff($_SESSION['split_selection'][$topic], array($_REQUEST['msg']));
		else
			$_SESSION['split_selection'][$topic][] = $_REQUEST['msg'];
	}

	// Make sure the selection is still accurate.
	if (!empty($_SESSION['split_selection'][$topic]))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg IN ({array_int:split_msgs})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}'),
			array(
				'current_topic' => $topic,
				'split_msgs' => $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
			)
		);
		$_SESSION['split_selection'][$topic] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$_SESSION['split_selection'][$topic][] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
	}

	// Get the number of messages (not) selected to be split.
	$request = $smcFunc['db_query']('', '
		SELECT ' . (empty($_SESSION['split_selection'][$topic]) ? '0' : 'm.id_msg IN ({array_int:split_msgs})') . ' AS is_selected, COUNT(*) AS num_messages
		FROM {db_prefix}messages AS m
		WHERE m.id_topic = {int:current_topic}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND approved = {int:is_approved}') . (empty($_SESSION['split_selection'][$topic]) ? '' : '
		GROUP BY is_selected'),
		array(
			'current_topic' => $topic,
			'split_msgs' => !empty($_SESSION['split_selection'][$topic]) ? $_SESSION['split_selection'][$topic] : array(),
			'is_approved' => 1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context[empty($row['is_selected']) || $row['is_selected'] == 'f' ? 'not_selected' : 'selected']['num_messages'] = $row['num_messages'];
	$smcFunc['db_free_result']($request);

	// Fix an oversized starting page (to make sure both pageindexes are properly set).
	if ($context['selected']['start'] >= $context['selected']['num_messages'])
		$context['selected']['start'] = $context['selected']['num_messages'] <= $context['messages_per_page'] ? 0 : ($context['selected']['num_messages'] - (($context['selected']['num_messages'] % $context['messages_per_page']) == 0 ? $context['messages_per_page'] : ($context['selected']['num_messages'] % $context['messages_per_page'])));

	// Build a page list of the not-selected topics...
	$context['not_selected']['page_index'] = constructPageIndex($scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), array('%' => '%%')) . ';topic=' . $topic . '.%1$d;start2=' . $context['selected']['start'], $context['not_selected']['start'], $context['not_selected']['num_messages'], $context['messages_per_page'], true);
	// ...and one of the selected topics.
	$context['selected']['page_index'] = constructPageIndex($scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), array('%' => '%%')) . ';topic=' . $topic . '.' . $context['not_selected']['start'] . ';start2=%1$d', $context['selected']['start'], $context['selected']['num_messages'], $context['messages_per_page'], true);

	// Retrieve the unselected messages.
	$context['not_selected']['messages'] = selectMessages($topic, $context['not_selected']['start'], $context['messages_per_page'], empty($_SESSION['split_selection'][$topic]) ? array() : array('excluded' => $_SESSION['split_selection'][$topic]), $modSettings['postmod_active'] && !allowedTo('approve_posts'));

	// Now retrieve the selected messages.
	if (!empty($_SESSION['split_selection'][$topic]))
		$context['selected']['messages'] = selectMessages($topic, $context['selected']['start'], $context['messages_per_page'], array('included' => $_SESSION['split_selection'][$topic]), $modSettings['postmod_active'] && !allowedTo('approve_posts'));

	// The XMLhttp method only needs the stuff that changed, so let's compare.
	if (isset($_REQUEST['xml']))
	{
		$changes = array(
			'remove' => array(
				'not_selected' => array_diff($original_msgs['not_selected'], array_keys($context['not_selected']['messages'])),
				'selected' => array_diff($original_msgs['selected'], array_keys($context['selected']['messages'])),
			),
			'insert' => array(
				'not_selected' => array_diff(array_keys($context['not_selected']['messages']), $original_msgs['not_selected']),
				'selected' => array_diff(array_keys($context['selected']['messages']), $original_msgs['selected']),
			),
		);

		$context['changes'] = array();
		foreach ($changes as $change_type => $change_array)
			foreach ($change_array as $section => $msg_array)
			{
				if (empty($msg_array))
					continue;

				foreach ($msg_array as $id_msg)
				{
					$context['changes'][$change_type . $id_msg] = array(
						'id' => $id_msg,
						'type' => $change_type,
						'section' => $section,
					);
					if ($change_type == 'insert')
						$context['changes']['insert' . $id_msg]['insert_value'] = $context[$section]['messages'][$id_msg];
				}
			}
	}
}

/**
 * Do the actual split of a selection of topics.
 * is accessed with ?action=splittopics;sa=splitSelection.
 * uses the main SplitTopics template.
 * uses splitTopic function to do the actual splitting.

 */
function action_splitSelection()
{
	global $txt, $board, $topic, $context, $user_info;

	// Make sure the session id was passed with post.
	checkSession();

	// Default the subject in case it's blank.
	if (!isset($_POST['subname']) || $_POST['subname'] == '')
		$_POST['subname'] = $txt['new_topic'];

	// You must've selected some messages!  Can't split out none!
	if (empty($_SESSION['split_selection'][$topic]))
		fatal_lang_error('no_posts_selected', false);

	$context['old_topic'] = $topic;
	$context['new_topic'] = splitTopic($topic, $_SESSION['split_selection'][$topic], $_POST['subname']);
	$context['page_title'] = $txt['split'];
}

/**
 * General function to split off a topic.
 * creates a new topic and moves the messages with the IDs in
 * array messagesToBeSplit to the new topic.
 * the subject of the newly created topic is set to 'newSubject'.
 * marks the newly created message as read for the user splitting it.
 * updates the statistics to reflect a newly created topic.
 * logs the action in the moderation log.
 * a notification is sent to all users monitoring this topic.
 * @param int $split1_ID_TOPIC
 * @param array $splitMessages
 * @param string $new_subject
 * @return int the topic ID of the new split topic.
 */
function splitTopic($split1_ID_TOPIC, $splitMessages, $new_subject)
{
	global $user_info, $topic, $board, $modSettings, $smcFunc, $txt;

	// Nothing to split?
	if (empty($splitMessages))
		fatal_lang_error('no_posts_selected', false);

	// Get some board info.
	$request = $smcFunc['db_query']('', '
		SELECT id_board, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:id_topic}
		LIMIT 1',
		array(
			'id_topic' => $split1_ID_TOPIC,
		)
	);
	list ($id_board, $split1_approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Find the new first and last not in the list. (old topic)
	$request = $smcFunc['db_query']('', '
		SELECT
			MIN(m.id_msg) AS myid_first_msg, MAX(m.id_msg) AS myid_last_msg, COUNT(*) AS message_count, m.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:id_topic})
		WHERE m.id_msg NOT IN ({array_int:no_msg_list})
			AND m.id_topic = {int:id_topic}
		GROUP BY m.approved
		ORDER BY m.approved DESC
		LIMIT 2',
		array(
			'id_topic' => $split1_ID_TOPIC,
			'no_msg_list' => $splitMessages,
		)
	);
	// You can't select ALL the messages!
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('selected_all_posts', false);

	$split1_first_msg = null;
	$split1_last_msg = null;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Get the right first and last message dependant on approved state...
		if (empty($split1_first_msg) || $row['myid_first_msg'] < $split1_first_msg)
			$split1_first_msg = $row['myid_first_msg'];
		if (empty($split1_last_msg) || $row['approved'])
			$split1_last_msg = $row['myid_last_msg'];

		// Get the counts correct...
		if ($row['approved'])
		{
			$split1_replies = $row['message_count'] - 1;
			$split1_unapprovedposts = 0;
		}
		else
		{
			if (!isset($split1_replies))
				$split1_replies = 0;
			// If the topic isn't approved then num replies must go up by one... as first post wouldn't be counted.
			elseif (!$split1_approved)
				$split1_replies++;

			$split1_unapprovedposts = $row['message_count'];
		}
	}
	$smcFunc['db_free_result']($request);
	$split1_firstMem = getMsgMemberID($split1_first_msg);
	$split1_lastMem = getMsgMemberID($split1_last_msg);

	// Find the first and last in the list. (new topic)
	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg) AS myid_first_msg, MAX(id_msg) AS myid_last_msg, COUNT(*) AS message_count, approved
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:msg_list})
			AND id_topic = {int:id_topic}
		GROUP BY id_topic, approved
		ORDER BY approved DESC
		LIMIT 2',
		array(
			'msg_list' => $splitMessages,
			'id_topic' => $split1_ID_TOPIC,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// As before get the right first and last message dependant on approved state...
		if (empty($split2_first_msg) || $row['myid_first_msg'] < $split2_first_msg)
			$split2_first_msg = $row['myid_first_msg'];
		if (empty($split2_last_msg) || $row['approved'])
			$split2_last_msg = $row['myid_last_msg'];

		// Then do the counts again...
		if ($row['approved'])
		{
			$split2_approved = true;
			$split2_replies = $row['message_count'] - 1;
			$split2_unapprovedposts = 0;
		}
		else
		{
			// Should this one be approved??
			if ($split2_first_msg == $row['myid_first_msg'])
				$split2_approved = false;

			if (!isset($split2_replies))
				$split2_replies = 0;
			// As before, fix number of replies.
			elseif (!$split2_approved)
				$split2_replies++;

			$split2_unapprovedposts = $row['message_count'];
		}
	}
	$smcFunc['db_free_result']($request);
	$split2_firstMem = getMsgMemberID($split2_first_msg);
	$split2_lastMem = getMsgMemberID($split2_last_msg);

	// No database changes yet, so let's double check to see if everything makes at least a little sense.
	if ($split1_first_msg <= 0 || $split1_last_msg <= 0 || $split2_first_msg <= 0 || $split2_last_msg <= 0 || $split1_replies < 0 || $split2_replies < 0 || $split1_unapprovedposts < 0 || $split2_unapprovedposts < 0 || !isset($split1_approved) || !isset($split2_approved))
		fatal_lang_error('cant_find_messages');

	// You cannot split off the first message of a topic.
	if ($split1_first_msg > $split2_first_msg)
		fatal_lang_error('split_first_post', false);

	// We're off to insert the new topic!  Use 0 for now to avoid UNIQUE errors.
	$smcFunc['db_insert']('',
			'{db_prefix}topics',
			array(
				'id_board' => 'int',
				'id_member_started' => 'int',
				'id_member_updated' => 'int',
				'id_first_msg' => 'int',
				'id_last_msg' => 'int',
				'num_replies' => 'int',
				'unapproved_posts' => 'int',
				'approved' => 'int',
				'is_sticky' => 'int',
			),
			array(
				(int) $id_board, $split2_firstMem, $split2_lastMem, 0,
				0, $split2_replies, $split2_unapprovedposts, (int) $split2_approved, 0,
			),
			array('id_topic')
		);
	$split2_ID_TOPIC = $smcFunc['db_insert_id']('{db_prefix}topics', 'id_topic');
	if ($split2_ID_TOPIC <= 0)
		fatal_lang_error('cant_insert_topic');

	// Move the messages over to the other topic.
	$new_subject = strtr($smcFunc['htmltrim']($smcFunc['htmlspecialchars']($new_subject)), array("\r" => '', "\n" => '', "\t" => ''));
	// Check the subject length.
	if ($smcFunc['strlen']($new_subject) > 100)
		$new_subject = $smcFunc['substr']($new_subject, 0, 100);
	// Valid subject?
	if ($new_subject != '')
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET
				id_topic = {int:id_topic},
				subject = CASE WHEN id_msg = {int:split_first_msg} THEN {string:new_subject} ELSE {string:new_subject_replies} END
			WHERE id_msg IN ({array_int:split_msgs})',
			array(
				'split_msgs' => $splitMessages,
				'id_topic' => $split2_ID_TOPIC,
				'new_subject' => $new_subject,
				'split_first_msg' => $split2_first_msg,
				'new_subject_replies' => $txt['response_prefix'] . $new_subject,
			)
		);

		// Cache the new topics subject... we can do it now as all the subjects are the same!
		updateStats('subject', $split2_ID_TOPIC, $new_subject);
	}

	// Any associated reported posts better follow...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET id_topic = {int:id_topic}
		WHERE id_msg IN ({array_int:split_msgs})',
		array(
			'split_msgs' => $splitMessages,
			'id_topic' => $split2_ID_TOPIC,
		)
	);

	// Mess with the old topic's first, last, and number of messages.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			num_replies = {int:num_replies},
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			id_member_started = {int:id_member_started},
			id_member_updated = {int:id_member_updated},
			unapproved_posts = {int:unapproved_posts}
		WHERE id_topic = {int:id_topic}',
		array(
			'num_replies' => $split1_replies,
			'id_first_msg' => $split1_first_msg,
			'id_last_msg' => $split1_last_msg,
			'id_member_started' => $split1_firstMem,
			'id_member_updated' => $split1_lastMem,
			'unapproved_posts' => $split1_unapprovedposts,
			'id_topic' => $split1_ID_TOPIC,
		)
	);

	// Now, put the first/last message back to what they should be.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_first_msg' => $split2_first_msg,
			'id_last_msg' => $split2_last_msg,
			'id_topic' => $split2_ID_TOPIC,
		)
	);

	// If the new topic isn't approved ensure the first message flags this just in case.
	if (!$split2_approved)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET approved = {int:approved}
			WHERE id_msg = {int:id_msg}
				AND id_topic = {int:id_topic}',
			array(
				'approved' => 0,
				'id_msg' => $split2_first_msg,
				'id_topic' => $split2_ID_TOPIC,
			)
		);

	// The board has more topics now (Or more unapproved ones!).
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET ' . ($split2_approved ? '
			num_topics = num_topics + 1' : '
			unapproved_topics = unapproved_topics + 1') . '
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $id_board,
		)
	);

	// Copy log topic entries.
	// @todo This should really be chunked.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_msg, disregarded
		FROM {db_prefix}log_topics
		WHERE id_topic = {int:id_topic}',
		array(
			'id_topic' => (int) $split1_ID_TOPIC,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$replaceEntries = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$replaceEntries[] = array($row['id_member'], $split2_ID_TOPIC, $row['id_msg'], $row['disregarded']);

		require_once(SUBSDIR . '/Topic.subs.php');
		markTopicsRead($replaceEntries, false);
		unset($replaceEntries);
	}
	$smcFunc['db_free_result']($request);

	// Housekeeping.
	updateStats('topic');
	updateLastMessages($id_board);

	logAction('split', array('topic' => $split1_ID_TOPIC, 'new_topic' => $split2_ID_TOPIC, 'board' => $id_board));

	// Notify people that this topic has been split?
	sendNotifications($split1_ID_TOPIC, 'split');

	// If there's a search index that needs updating, update it...
	require_once(SUBSDIR . '/Search.subs.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'topicSplit')))
		$searchAPI->topicSplit($split2_ID_TOPIC, $splitMessages);

	// Return the ID of the newly created topic.
	return $split2_ID_TOPIC;
}