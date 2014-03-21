<?php

/**
 * This file contains a couple of functions for the latests posts on forum.
 *
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
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Get the latest posts of a forum.
 *
 * @param mixed[] $latestPostOptions
 * @return array
 */
function getLastPosts($latestPostOptions)
{
	global $scripturl, $modSettings;

	$db = database();

	// Find all the posts. Newer ones will have higher IDs. (assuming the last 20 * number are accessable...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $db->query('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_board, b.name AS board_name,
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
	while ($row = $db->fetch_assoc($request))
	{
		// Censor the subject and post for the preview ;).
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		$row['body'] = shorten_text($row['body'], !empty($modSettings['lastpost_preview_characters']) ? $modSettings['lastpost_preview_characters'] : 128, true);

		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_text($row['subject'], !empty($modSettings['subject_length']) ? $modSettings['subject_length'] : 24),
			'preview' => $row['body'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>'
		);
	}
	$db->free_result($request);

	return $posts;
}

/**
 * Callback-function for the cache for getLastPosts().
 *
 * @param mixed[] $latestPostOptions
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
 * For a supplied list of message id's, loads the posting details for each.
 *  - Intended to get all the most recent posts.
 *  - Tracks the posts made by this user (from the supplied message list) and
 *    loads the id's in to the 'own' or 'any' array.
 *    Reminder The controller needs to check permissions
 *  - Returns two arrays, one of the posts one of any/own
 *
 * @param int[] $messages
 * @param int $start
 */
function getRecentPosts($messages, $start)
{
	global $user_info, $scripturl, $modSettings;

	$db = database();

	// Get all the most recent posts.
	$request = $db->query('', '
		SELECT
			m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic, t.id_board, b.id_cat,
			b.name AS bname, c.name AS cname, t.num_replies, m.id_member, m2.id_member AS first_id_member,
			IFNULL(mem2.real_name, m2.poster_name) AS first_display_name, t.id_first_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
		ORDER BY m.id_msg DESC
		LIMIT ' . count($messages),
		array(
			'message_list' => $messages,
		)
	);
	$counter = $start + 1;
	$posts = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $db->fetch_assoc($request))
	{
		// Censor everything.
		censorText($row['body']);
		censorText($row['subject']);

		// BBC-atize the message.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And build the array.
		$posts[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cname'],
				'href' => $scripturl . '#c' . $row['id_cat'],
				'link' => '<a href="' . $scripturl . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'first_poster' => array(
				'id' => $row['first_id_member'],
				'name' => $row['first_display_name'],
				'href' => empty($row['first_id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['first_id_member'],
				'link' => empty($row['first_id_member']) ? $row['first_display_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '">' . $row['first_display_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
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
	$db->free_result($request);

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
 * Creates a temporary table for logging of unread topics
 *
 * @param mixed[] $query_parameters - 
 * @param int $earliest_msg - id of the earliest message to consider
 * @return bool - if the table has been created ot not
 */
function recent_log_topics_unread_tempTable($query_parameters, $earliest_msg)
{
	global $modSettings, $user_info;

	$db = database();

	$db->query('', '
		DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
		array(
		)
	);

	// Let's copy things out of the log_topics table, to reduce searching.
	return $db->query('', '
		CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
			PRIMARY KEY (id_topic)
		)
		SELECT lt.id_topic, lt.id_msg, lt.unwatched
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
		WHERE lt.id_member = {int:current_member}
			AND t.id_board IN ({array_int:boards})' . (empty($earliest_msg) ? '' : '
			AND t.id_last_msg > {int:earliest_msg}') . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_unwatch'] ? '
			AND lt.unwatched != 1' : ''),
		array_merge($query_parameters, array(
			'current_member' => $user_info['id'],
			'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
			'is_approved' => 1,
			'db_error_skip' => true,
		))
	) !== false;
}

/**
 * Counts unread topics, used in *all* unread replies with temp table and
 * new posts since last visit
 *
 * @param mixed[] $query_parameters - 
 * @param bool $showing_all_topics - Is the user looking at all the unread
 *             replies, or the recent topics?
 * @param bool $have_temp_table - if use the temp table or not
 * @param bool $is_first_login - if the member has already logged in at least
 *             once, then there is an $id_msg_last_visit
 * @param int $earliest_msg - id of the earliest message to consider
 * @param int $id_msg_last_visit - highest id_msg found during the last visit
 */
function countRecentTopics($query_parameters, $showing_all_topics, $have_temp_table, $is_first_login, $earliest_msg, $id_msg_last_visit = 0)
{
	global $modSettings, $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*), MIN(t.id_last_msg)
		FROM {db_prefix}topics AS t' . (!empty($have_temp_table) ? '
			LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
		WHERE t.id_board IN ({array_int:boards})' . ($showing_all_topics && !empty($earliest_msg) ? '
			AND t.id_last_msg > {int:earliest_msg}' : (!$showing_all_topics && $is_first_login ? '
			AND t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
			AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' .
			($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') .
			($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : ''),
		array_merge($query_parameters, array(
			'current_member' => $user_info['id'],
			'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
			'id_msg_last_visit' => $id_msg_last_visit,
			'is_approved' => 1,
		))
	);
	list ($num_topics, $min_message) = $db->fetch_row($request);
	$db->free_result($request);

	return array($num_topics, $min_message);
}

/**
 * Retrieves unread topics, used in *all* unread replies with temp table and
 * new posts since last visit
 *
 * @param mixed[] $query_parameters - 
 * @param bool|int|string $preview_bodies - if retrieve also a preview of the last
 *                        message body. It can assume the following values:
 *                         - 'all' to retrieve the entire body,
 *                         - a number representing the number of chars the
 *                           to include in the preview,
 *                         - 0/false no body retrieved
 * @param string $join - kind of "JOIN" to execute. If 'topic' JOINs boards on
 *                       the topics table, otherwise ('message') the JOIN is on
 *                       the messages table
 * @param bool $have_temp_table - if use the temp table or not
 * @param int $min_message - id of the earliest message to consider
 * @param string $sort - sorting order (query)
 * @param bool $ascending - if the sorting is ascending or descending
 * @param int $start - position to start the query
 * @param int $limit - number of entries to grab
 * @param bool $include_avatars - if avatars should be retrieved as well
 * @return processRecentTopicList
 */
function getUnreadTopics($query_parameters, $preview_bodies, $join, $have_temp_table, $min_message, $sort, $ascending, $start, $limit, $include_avatars = false)
{
	global $modSettings, $user_info;

	$db = database();

	if ($preview_bodies == 'all')
		$body_query = 'ml.body AS last_body, ms.body AS first_body,';
	else
	{
		$preview_bodies = (int) $preview_bodies;

		// If empty, no preview at all
		if (empty($preview_bodies))
			$body_query = '';
		// Default: a SUBSTRING
		else
			$body_query = 'SUBSTRING(ml.body, 1, ' . ($preview_bodies + 256) . ') AS last_body, SUBSTRING(ms.body, 1, ' . ($preview_bodies + 256) . ') AS first_body,';
	}

	$request = $db->query('substring', '
		SELECT
			ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
			t.num_replies, t.num_views, t.num_likes, ms.id_member AS first_id_member, ml.id_member AS last_id_member,
			ml.poster_time AS last_poster_time, IFNULL(mems.real_name, ms.poster_name) AS first_display_name,
			IFNULL(meml.real_name, ml.poster_name) AS last_display_name, ml.subject AS last_subject,
			ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from,
			' . $body_query . '
			' . ($include_avatars ? ' meml.avatar ,IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, meml.email_address, ' : '') . '
			ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg
		FROM {db_prefix}messages AS ms
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' . ($join == 'topics' ? '
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' : '
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)') . '
			LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($have_temp_table) ? '
			LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . ($include_avatars ? '
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)' : '') . '
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
		WHERE t.id_board IN ({array_int:boards})
			AND t.id_last_msg >= {int:min_message}
			AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < ml.id_msg' .
			($modSettings['postmod_active'] ? ' AND ms.approved = {int:is_approved}' : '') .
			($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : '') . '
		ORDER BY {raw:order}
		LIMIT {int:offset}, {int:limit}',
		array_merge($query_parameters, array(
			'current_member' => $user_info['id'],
			'min_message' => $min_message,
			'is_approved' => 1,
			'order' => $sort . ($ascending ? '' : ' DESC'),
			'offset' => $start,
			'limit' => $limit,
		))
	);

	$topics = array();
	while ($row = $db->fetch_assoc($request))
		$topics[] = $row;
	$db->free_result($request);

	return processRecentTopicList($topics, true);
}

/**
 * Formats the data obtained by the database in a way that is template-friendly
 *
 * Currently used by getUnreadTopics and getUnreadReplies
 *
 * @param mixed[] $topics_info - data coming from a query,
 *                for example generated by getUnreadTopics
 * @param bool $topicseen - if use the temp table or not
 * @return mixed[] - array of data related to topics
 */
function processRecentTopicList($topics_info, $topicseen = false)
{
	global $modSettings, $options, $scripturl, $context, $txt, $settings;

	$topics = array();
	$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
	$topicseen = $topicseen ? ';topicseen' : '';

	foreach ($topics_info as $row)
	{
		// is message previews enabled?
		if (!empty($modSettings['message_index_preview']))
		{
			// Limit them to $modSettings['preview_characters'] characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br />' => "\n", '&nbsp;' => ' ')));
			$row['first_body'] = shorten_text($row['first_body'], !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128, true);

			$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br />' => "\n", '&nbsp;' => ' ')));
			$row['last_body'] = shorten_text($row['last_body'], !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128, true);

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		if ($topic_length > $messages_per_page)
		{
			// We can't pass start by reference.
			$start = -1;
			$pages = constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d' . $topicseen, $start, $topic_length, $messages_per_page, true, array('prev_next' => false));

			// If we can use all, show it.
			if (!empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
		}
		else
			$pages = '';

		// We need to check the topic icons exist... you can never be too sure!
		if (!empty($modSettings['messageIconChecks_enable']))
		{
			// First icon first... as you'd expect.
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';

			// Last icon... last... duh.
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		}
		else
		{
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = 'images_url';

			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = 'images_url';
		}

		// And build the array.
		$topics[$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'name' => $row['first_display_name'],
					'id' => $row['first_id_member'],
					'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
					'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
				),
				'time' => standardTime($row['first_poster_time']),
				'html_time' => htmlTime($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'preview' => trim($row['first_body']),
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0' . $topicseen,
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0' . $topicseen . '">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'name' => $row['last_display_name'],
					'id' => $row['last_id_member'],
					'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
					'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
				),
				'time' => standardTime($row['last_poster_time']),
				'html_time' => htmlTime($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'preview' => trim($row['last_body']),
				'icon' => $row['last_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . $topicseen . '#msg' . $row['id_last_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . $topicseen . '#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
			),
			'default_preview' => trim($row[!empty($modSettings['message_index_preview']) && $modSettings['message_index_preview'] == 2 ? 'last_body' : 'first_body']),
			'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'is_poll' => !empty($modSettings['pollMode']) && $row['id_poll'] > 0,
			'is_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicPosts'] : $row['num_replies'] >= $modSettings['hotTopicPosts'],
			'is_very_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicVeryPosts'] : $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
			'is_posted_in' => false,
			'icon' => $row['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
			'subject' => $row['first_subject'],
			'new' => !empty($row['id_msg_modified']) && $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'newtime' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . $topicseen . '#new',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . $topicseen . ($row['num_replies'] == 0 ? '' : 'new'),
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . $topicseen . '#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views']),
			'likes' => comma_format($row['num_likes']),
		);

		if (!empty($row['id_board']))
			$topics[$row['id_topic']]['board'] = array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			);

		if (!empty($settings['avatars_on_indexes']))
			$topics[$row['id_topic']]['last_post']['member']['avatar'] = determineAvatar($row);

		determineTopicClass($topics[$row['id_topic']]);
	}

	return $topics;
}

/**
 * Retrieves unread replies since last visit
 *
 * @param mixed[] $query_parameters - 
 * @param bool|int|string $preview_bodies - if retrieve also a preview of the last
 *                        message body. It can assume the following values:
 *                         - 'all' to retrieve the entire body,
 *                         - a number representing the number of chars the
 *                           to include in the preview,
 *                         - 0/false no body retrieved
 * @param bool $have_temp_table - if use the temp table or not
 * @param int $min_message - id of the earliest message to consider
 * @param string $sort - sorting order (query)
 * @param bool $ascending - if the sorting is ascending or descending
 * @param int $start - position to start the query
 * @param int $limit - number of entries to grab
 * @param bool $include_avatars - if avatars should be retrieved as well
 * @return processRecentTopicList
 */
function getUnreadReplies($query_parameters, $preview_bodies, $have_temp_table, $min_message, $sort, $ascending, $start, $limit, $include_avatars = false)
{
	global $modSettings, $user_info;

	$db = database();

	if (!empty($have_temp_table))
	{
		$request = $db->query('', '
			SELECT t.id_topic
			FROM {db_prefix}topics_posted_in AS t
				LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
			WHERE t.id_board IN ({array_int:boards})
				AND IFNULL(lt.id_msg, t.id_msg) < t.id_last_msg
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'order' => (in_array($sort, array('t.id_last_msg', 't.id_topic')) ? $sort : 't.sort_key') . ($ascending ? '' : ' DESC'),
				'offset' => $start,
				'limit' => $limit,
			))
		);
	}
	else
	{
		$request = $db->query('unread_replies', '
			SELECT DISTINCT t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($sort, 'ms.') === false ? '' : '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($sort, 'mems.') === false ? '' : '
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_board IN ({array_int:boards})
				AND t.id_last_msg >= {int:min_message}
				AND (IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0))) < t.id_last_msg' .
				($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') .
				($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : '') . '
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'min_message' => (int) $min_message,
				'is_approved' => 1,
				'order' => $sort . ($ascending ? '' : ' DESC'),
				'offset' => $start,
				'limit' => $limit,
			))
		);
	}

	$topics = array();
	while ($row = $db->fetch_assoc($request))
		$topics[] = $row['id_topic'];
	$db->free_result($request);

	// Sanity... where have you gone?
	if (empty($topics))
		return false;

	if ($preview_bodies == 'all')
		$body_query = 'ml.body AS last_body, ms.body AS first_body,';
	else
	{
		$preview_bodies = (int) $preview_bodies;

		// If empty, no preview at all
		if (empty($preview_bodies))
			$body_query = '';
		// Default: a SUBSTRING
		else
			$body_query = 'SUBSTRING(ml.body, 1, ' . ($preview_bodies + 256) . ') AS last_body, SUBSTRING(ms.body, 1, ' . ($preview_bodies + 256) . ') AS first_body,';
	}

	$request = $db->query('substring', '
		SELECT
			ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
			t.num_replies, t.num_views, t.num_likes, ms.id_member AS first_id_member, ml.id_member AS last_id_member,
			ml.poster_time AS last_poster_time, IFNULL(mems.real_name, ms.poster_name) AS first_display_name,
			IFNULL(meml.real_name, ml.poster_name) AS last_display_name, ml.subject AS last_subject,
			ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from,
			' . $body_query . '
			' . ($include_avatars ? ' meml.avatar, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, meml.email_address, ' : '') . '
			ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . ($include_avatars ? '
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)' : '') . '
		WHERE t.id_topic IN ({array_int:topic_list})
		ORDER BY {raw:order}
		LIMIT {int:limit}',
		array(
			'current_member' => $user_info['id'],
			'order' => $sort . ($ascending ? '' : ' DESC'),
			'topic_list' => $topics,
			'limit' => count($topics),
		)
	);

	$return = array();
	while ($row = $db->fetch_assoc($request))
		$return[] = $row;
	$db->free_result($request);

	return processRecentTopicList($return, true);
}

/**
 * Handle creation of temporary tables to track unread replies.
 * The main benefit of this temporary table is not that it's faster;
 * it's that it avoids locks later.
 *
 * @param int $board_id - id of a board if looking at the unread of a single
 *            board, 0 if looking at the unread of the entire forum
 * @param string $sort - sorting query clause
 * @return bool - if the table has been created ot not
 */
function unreadreplies_tempTable($board_id, $sort)
{
	global $modSettings, $user_info;

	$db = database();

	$db->query('', '
		DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
		array(
		)
	);

	$db->query('', '
		DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
		array(
		)
	);

	$sortKey_joins = array(
		'ms.subject' => '
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
		'IFNULL(mems.real_name, ms.poster_name)' => '
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
	);

	$have_temp_table = $db->query('', '
		CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
			id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
			id_board smallint(5) unsigned NOT NULL default {string:string_zero},
			id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
			id_msg int(10) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_topic)
		)
		SELECT t.id_topic, t.id_board, t.id_last_msg, IFNULL(lmr.id_msg, 0) AS id_msg' . (!in_array($sort, array('t.id_last_msg', 't.id_topic')) ? ', ' . $sort . ' AS sort_key' : '') . '
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' . ($modSettings['enable_unwatch'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (t.id_topic = lt.id_topic AND lt.id_member = {int:current_member})' : '') . '
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$sort]) ? $sortKey_joins[$sort] : '') . '
		WHERE m.id_member = {int:current_member}' . (!empty($board_id) ? '
			AND t.id_board = {int:current_board}' : '') . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_unwatch'] ? '
			AND IFNULL(lt.unwatched, 0) != 1' : '') . '
		GROUP BY m.id_topic',
		array(
			'current_board' => $board_id,
			'current_member' => $user_info['id'],
			'is_approved' => 1,
			'string_zero' => '0',
			'db_error_skip' => true,
		)
	) !== false;

	// If that worked, create a sample of the log_topics table too.
	if ($have_temp_table)
		$have_temp_table = $db->query('', '
			CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg
			FROM {db_prefix}log_topics AS lt
				INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
			WHERE lt.id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'db_error_skip' => true,
			)
		) !== false;

	return $have_temp_table;
}

/**
 * Counts unread replies
 *
 * @param mixed[] $query_parameters - 
 * @param bool $have_temp_table - if use the temp table or not
 */
function countUnreadReplies($query_parameters, $have_temp_table)
{
	global $modSettings, $user_info;

	$db = database();

	if (!empty($have_temp_table))
	{
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics_posted_in AS pi
				LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
			WHERE pi.id_board IN ({array_int:boards})
				AND IFNULL(lt.id_msg, pi.id_msg) < pi.id_last_msg',
			array_merge($query_parameters, array(
			))
		);
		list ($num_topics) = $db->fetch_row($request);
		$db->free_result($request);
		$min_message = 0;
	}
	else
	{
		$request = $db->query('unread_fetch_topic_count', '
			SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_board IN ({array_int:boards})
				AND m.id_member = {int:current_member}
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_unwatch'] ? '
				AND IFNULL(lt.unwatched, 0) != 1' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = $db->fetch_row($request);
		$db->free_result($request);
	}

	return array($num_topics, $min_message);
}

/**
 * Find the most recent messages in the forum.
 * Respects 
 *
 * @param mixed[] $query_parameters - 
 * @param string $query_this_board - query string holding the conditions
 *               for filtering the messages based on boards and other limits
 * @param bool $have_temp_table - if use the temp table or not
 */
function findRecentMessages($query_parameters, $query_this_board, $start, $limit = 10)
{
	global $modSettings, $user_info;

	$db = database();

	$key = 'recent-' . $user_info['id'] . '-' . md5(serialize(array_diff_key($query_parameters, array('max_id_msg' => 0)))) . '-' . $start . '-' . $limit;
	if (empty($modSettings['cache_enable']) || ($messages = cache_get_data($key, 120)) == null)
	{
		$done = false;
		while (!$done)
		{
			// Find the 10 most recent messages they can *view*.
			// @todo SLOW This query is really slow still, probably?
			$request = $db->query('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				WHERE ' . $query_this_board . '
					AND m.approved = {int:is_approved}
				ORDER BY m.id_msg DESC
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'is_approved' => 1,
					'offset' => $start,
					'limit' => $limit,
				))
			);
			// If we don't have 10 results, try again with an unoptimized version covering all rows, and cache the result.
			if (isset($query_parameters['max_id_msg']) && $db->num_rows($request) < $limit)
			{
				$db->free_result($request);
				$query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $query_this_board);
				$cache_results = true;
				unset($query_parameters['max_id_msg']);
			}
			else
				$done = true;
		}
		$messages = array();
		while ($row = $db->fetch_assoc($request))
			$messages[] = $row['id_msg'];
		$db->free_result($request);

		if (!empty($cache_results))
				cache_put_data($key, $messages, 120);
	}

	return $messages;
}