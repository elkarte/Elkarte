<?php

/**
 * This file contains a couple of functions for the latest posts on forum.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * Get the latest posts of a forum.
 *
 * @param mixed[] $latestPostOptions
 * @return array
 */
function getLastPosts($latestPostOptions)
{
	global $modSettings;

	$db = database();

	// Find all the posts. Newer ones will have higher IDs. (assuming the last 20 * number are accessible...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $db->query('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, t.id_board, b.name AS board_name,
			SUBSTRING(m.body, 1, 385) AS body, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg >= {int:likely_max_msg}' .
			(!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}' : '') . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $latestPostOptions['number_posts'],
		array(
			'likely_max_msg' => max(0, $modSettings['maxMsgID'] - 50 * $latestPostOptions['number_posts']),
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);

	$posts = array();
	$bbc_parser = \BBC\ParserWrapper::instance();

	while ($row = $db->fetch_assoc($request))
	{
		// Censor the subject and post for the preview ;).
		$row['subject'] = censor($row['subject']);
		$row['body'] = censor($row['body']);

		$row['body'] = strip_tags(strtr($bbc_parser->parseMessage($row['body'], $row['smileys_enabled']), array('<br />' => '&#10;')));
		$row['body'] = \ElkArte\Util::shorten_text($row['body'], !empty($modSettings['lastpost_preview_characters']) ? $modSettings['lastpost_preview_characters'] : 128, true);

		$board_href = getUrl('board', ['board' => $row['id_board'], 'start' => '0', 'name' => $row['board_name']]);
		$poster_href = getUrl('profile', ['action' => 'profile', 'u' => $row['id_member'], 'name' => $row['poster_name']]);
		$topic_href = getUrl('topic', ['topic' => $row['id_topic'], 'start' => 'msg' . $row['id_msg'], 'subject' => $row['subject'], 'topicseen']);
		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $board_href,
				'link' => '<a href="' . $board_href . '">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $poster_href,
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $poster_href . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => \ElkArte\Util::shorten_text($row['subject'], $modSettings['subject_length']),
			'preview' => $row['body'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $topic_href . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $topic_href . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>'
		);
	}
	$db->free_result($request);

	return $posts;
}

/**
 * Callback-function for the cache for getLastPosts().
 *
 * @param mixed[] $latestPostOptions
 *
 * @return array
 */
function cache_getLastPosts($latestPostOptions)
{
	return array(
		'data' => getLastPosts($latestPostOptions),
		'expires' => time() + 60,
		'post_retri_eval' => '
			foreach ($cache_block[\'data\'] as $k => $post)
			{
				$cache_block[\'data\'][$k] += array(
					\'time\' => standardTime($post[\'raw_timestamp\']),
					\'html_time\' => htmlTime($post[\'raw_timestamp\']),
					\'timestamp\' => $post[\'raw_timestamp\'],
				);
			}',
	);
}

/**
 * Formats data supplied into a form that can be used in the template
 *
 * @param mixed[] $messages
 * @param int $start
 *
 * @return array
 */
function prepareRecentPosts($messages, $start)
{
	global $user_info, $modSettings;

	$counter = $start + 1;
	$posts = array();
	$board_ids = array('own' => array(), 'any' => array());
	$bbc_parser = \BBC\ParserWrapper::instance();
	foreach ($messages as $row)
	{
		// Censor everything.
		$row['body'] = censor($row['body']);
		$row['subject'] = censor($row['subject']);

		// BBC-atize the message.
		$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

		$board_href = getUrl('board', ['board' => $row['id_board'], 'start' => '0', 'name' => $row['bname']]);
		$topic_href = getUrl('topic', ['topic' => $row['id_topic'], 'start' => 'msg' . $row['id_msg'], 'subject' => $row['subject']]);
		$first_poster_href = getUrl('profile', ['action' => 'profile', 'u' => $row['first_id_member'], 'name' => $row['first_display_name']]);
		$poster_href = getUrl('profile', ['action' => 'profile', 'u' => $row['id_member'], 'name' => $row['poster_name']]);
		// And build the array.
		$posts[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cname'],
				'href' => getUrl('action', $modSettings['default_forum_action']) . '#c' . $row['id_cat'],
				'link' => '<a href="' . getUrl('action', $modSettings['default_forum_action']) . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $board_href,
				'link' => '<a href="' . $board_href . '">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => $topic_href . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $topic_href . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'first_poster' => array(
				'id' => $row['first_id_member'],
				'name' => $row['first_display_name'],
				'href' => empty($row['first_id_member']) ? '' : $first_poster_href,
				'link' => empty($row['first_id_member']) ? $row['first_display_name'] : '<a href="' . $first_poster_href . '">' . $row['first_display_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $poster_href,
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $poster_href . '">' . $row['poster_name'] . '</a>'
			),
			'body' => $row['body'],
			'message' => $row['body'],
			'tests' => array(
				'can_reply' => false,
				'can_mark_notify' => false,
				'can_delete' => false,
			),
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
		);

		if ($user_info['id'] == $row['first_id_member'])
			$board_ids['own'][$row['id_board']][] = $row['id_msg'];
		$board_ids['any'][$row['id_board']][] = $row['id_msg'];
	}

	return array($posts, $board_ids);
}

/**
 * Return the earliest message a user can...see?
 */
function earliest_msg()
{
	global $board, $user_info;

	$db = database();

	if (!empty($board))
	{
		$request = $db->query('', '
			SELECT MIN(id_msg)
			FROM {db_prefix}log_mark_read
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
			)
		);
		list ($earliest_msg) = $db->fetch_row($request);
		$db->free_result($request);
	}
	else
	{
		$request = $db->query('', '
			SELECT MIN(lmr.id_msg)
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
			WHERE {query_see_board}',
			array(
				'current_member' => $user_info['id'],
			)
		);
		list ($earliest_msg) = $db->fetch_row($request);
		$db->free_result($request);
	}

	// This is needed in case of topics marked unread.
	if (empty($earliest_msg))
		$earliest_msg = 0;
	else
	{
		// Using caching, when possible, to ignore the below slow query.
		if (isset($_SESSION['cached_log_time']) && $_SESSION['cached_log_time'][0] + 45 > time())
			$earliest_msg2 = $_SESSION['cached_log_time'][1];
		else
		{
			// This query is pretty slow, but it's needed to ensure nothing crucial is ignored.
			$request = $db->query('', '
				SELECT MIN(id_msg)
				FROM {db_prefix}log_topics
				WHERE id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
				)
			);
			list ($earliest_msg2) = $db->fetch_row($request);
			$db->free_result($request);

			// In theory this could be zero, if the first ever post is unread, so fudge it ;)
			if ($earliest_msg2 == 0)
				$earliest_msg2 = -1;

			$_SESSION['cached_log_time'] = array(time(), $earliest_msg2);
		}

		$earliest_msg = min($earliest_msg2, $earliest_msg);
	}

	return $earliest_msg;
}

/**
 * Callback-function for the cache for getLastTopics().
 *
 * @param mixed[] $latestTopicOptions
 *
 * @return array
 */
function cache_getLastTopics($latestTopicOptions)
{
	return array(
		'data' => getLastTopics($latestTopicOptions),
		'expires' => time() + 60,
		'post_retri_eval' => '
			foreach ($cache_block[\'data\'] as $k => $post)
			{
				$cache_block[\'data\'][$k] += array(
					\'time\' => standardTime($post[\'raw_timestamp\']),
					\'html_time\' => htmlTime($post[\'raw_timestamp\']),
					\'timestamp\' => $post[\'raw_timestamp\'],
				);
			}',
	);
}

/**
 * Get the latest posts of a forum.
 *
 * @param mixed[] $latestTopicOptions
 * @return array
 */
function getLastTopics($latestTopicOptions)
{
	global $modSettings, $txt;

	$db = database();

	// Find all the posts. Newer ones will have higher IDs. (assuming the last 20 * number are accessable...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $db->query('substring', '
		SELECT
			ml.poster_time, mf.subject, ml.id_topic, ml.id_member, ml.id_msg, t.id_first_msg, ml.id_msg_modified,
			' . ($latestTopicOptions['id_member'] == 0 ? '0' : 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1') . ' AS new_from,
			COALESCE(mem.real_name, ml.poster_name) AS poster_name, t.id_board, b.name AS board_name,
			SUBSTRING(ml.body, 1, 385) AS body, ml.smileys_enabled
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}messages AS mf ON (t.id_first_msg = mf.id_msg)
			LEFT JOIN {db_prefix}messages AS ml ON (t.id_last_msg = ml.id_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)' . ($latestTopicOptions['id_member'] == 0 ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})') . '
		WHERE ml.id_msg >= {int:likely_max_msg}' .
			(!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . '
		ORDER BY t.id_last_msg DESC
		LIMIT {int:num_msgs}',
		array(
			'likely_max_msg' => max(0, $modSettings['maxMsgID'] - 50 * $latestTopicOptions['number_posts']),
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
			'num_msgs' =>  $latestTopicOptions['number_posts'],
			'current_member' =>  $latestTopicOptions['id_member'],
		)
	);

	$posts = array();
	$bbc_parser = \BBC\ParserWrapper::instance();

	while ($row = $db->fetch_assoc($request))
	{
		// Censor the subject and post for the preview ;).
		$row['subject'] = censor($row['subject']);
		$row['body'] = censor($row['body']);

		$row['body'] = strip_tags(strtr($bbc_parser->parseMessage($row['body'], $row['smileys_enabled']), array('<br />' => '&#10;')));
		$row['body'] = \ElkArte\Util::shorten_text($row['body'], !empty($modSettings['lastpost_preview_characters']) ? $modSettings['lastpost_preview_characters'] : 128, true);

		$board_href = getUrl('board', ['board' => $row['id_board'], 'start' => '0', 'name' => $row['board_name']]);
		$poster_href = getUrl('profile', ['action' => 'profile', 'u' => $row['id_member'], 'name' => $row['poster_name']]);
		$topic_href = getUrl('topic', ['topic' => $row['id_topic'], 'start' => 'msg' . $row['id_msg'], 'subject' => $row['subject'], 'topicseen']);
		// Build the array.
		$post = array(
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $board_href,
				'link' => '<a href="' . $board_href . '">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $poster_href,
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $poster_href . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => \ElkArte\Util::shorten_text($row['subject'], $modSettings['subject_length']),
			'preview' => $row['body'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $topic_href . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $topic_href . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'newtime' => $row['new_from'],
			'new_href' => getUrl('topic', ['topic' => $row['id_topic'], 'start' => 'msg' . $row['new_from'], 'subject' => $row['subject']]) . '#new',
		);
		if ($post['new'])
		{
			$post['link'] .= '
							<a class="new_posts" href="' . $post['new_href'] . '" id="newicon' . $row['id_msg'] . '">' . $txt['new'] . '</a>';
		}

		$posts[] = $post;
	}
	$db->free_result($request);

	return $posts;
}
