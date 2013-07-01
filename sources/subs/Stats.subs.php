<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file is holds low-level database work used by the Stats.
 * Some functions/queries (or all :P) might be duplicate, along Elk.
 * They'll be here to avoid including many files in action_stats, and
 * perhaps for use of add-ons in a similar way they were using some
 * SSI functions.
 * The purpose of this file is experimental and might be deprecated in
 * favor of a better solution.
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 *
 * Return the number of currently online members.
 */
function onlineCount()
{
	$db = database();

	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_online',
		array(
		)
	);
	list ($users_online) = $db->fetch_row($result);
	$db->free_result($result);

	return $users_online;
}

/**
 * Gets avarages for posts, topics, most online and new users.
 *
 * @return array
 */
function getAverages()
{
	$db = database();

	$result = $db->query('', '
		SELECT
			SUM(posts) AS posts, SUM(topics) AS topics, SUM(registers) AS registers,
			SUM(most_on) AS most_on, MIN(date) AS date, SUM(hits) AS hits
		FROM {db_prefix}log_activity',
		array(
		)
	);
	$row = $db->fetch_assoc($result);
	$db->free_result($result);

	return $row;
}

/**
 * Get the amount of boards.
 *
 * @return int
 */
function numBoards()
{
	$db = database();

	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}boards AS b
		WHERE b.redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
		)
	);
	list ($num_boards) = $db->fetch_row($result);
	$db->free_result($result);

	return $num_boards;
}

/**
 * Get the amount of categories
 *
 * @return int
 */
function numCategories()
{
	$db = database();

	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}categories AS c',
		array(
		)
	);
	list ($num_categories) = $db->fetch_row($result);
	$db->free_result($result);

	return $num_categories;
}

/**
 * Gets most online members for a specific date
 *
 * @param int $date
 * @return int
 */
function mostOnline($date)
{
	$db = database();

	$result = $db->query('', '
		SELECT most_on
		FROM {db_prefix}log_activity
		WHERE date = {date:given_date}
		LIMIT 1',
		array(
			'given_date' => $date,
		)
	);
	list ($online) = $db->fetch_row($result);
	$db->free_result($result);

	return $online;
}

/**
 * Determines the male vs. female ratio
 *
 * @return array
 */
function genderRatio()
{
	$db = database();
	$gender = array();

	$result = $db->query('', '
		SELECT COUNT(*) AS total_members, gender
		FROM {db_prefix}members
		GROUP BY gender',
		array(
		)
	);

	while ($row = $db->fetch_assoc($result))
	{
		// Assuming we're telling... male or female?
		if (!empty($row['gender']))
			$gender[$row['gender'] == 2 ? 'females' : 'males'] = $row['total_members'];
	}
	$db->free_result($result);

	return $gender;
}

/**
 * Loads a list of top x posters, x is configurable via $modSettings['stats_limit'].
 *
 * @return array
 */
function topPosters()
{
	global $scripturl, $modSettings;

	$db = database();

	$top_posters = array();

	$members_result = $db->query('', '
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		WHERE posts > {int:no_posts}
		ORDER BY posts DESC
		LIMIT {int:limit_posts}',
		array(
			'no_posts' => 0,
			'limit_posts' => isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 10,
		)
	);
	
	$max_num_posts = 1;
	while ($row_members = $db->fetch_assoc($members_result))
	{
		$top_posters[] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num_posts' => $row_members['posts'],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);
		if ($max_num_posts < $row_members['posts'])
			$max_num_posts = $row_members['posts'];
	}
	$db->free_result($members_result);

	return $top_posters;
}

/**
 * Loads a list of top x boards, x is configurable via $modSettings['stats_limit'].
 *
 * @return array
 */
function topBoards()
{
	global $modSettings, $scripturl;

	$db = database();

	$boards_result = $db->query('', '
		SELECT id_board, name, num_posts
		FROM {db_prefix}boards AS b
		WHERE {query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND b.redirect = {string:blank_redirect}
		ORDER BY num_posts DESC
		LIMIT {int:limit_boards}',
		array(
			'recycle_board' => $modSettings['recycle_board'],
			'blank_redirect' => '',
			'limit_boards' => isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 10,
		)
	);
	$top_boards = array();
	$max_num_posts = 1;
	while ($row_board = $db->fetch_assoc($boards_result))
	{
		$top_boards[] = array(
			'id' => $row_board['id_board'],
			'name' => $row_board['name'],
			'num_posts' => $row_board['num_posts'],
			'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['name'] . '</a>'
		);

		if ($max_num_posts < $row_board['num_posts'])
			$max_num_posts = $row_board['num_posts'];
	}
	$db->free_result($boards_result);

	return $top_boards;
}

/**
 * Loads a list of top x topic replies, x is configurable via $modSettings['stats_limit'].
 *
 * @return array
 */
function topTopicReplies()
{
	global $modSettings, $scripturl;

	$db = database();

	// Are you on a larger forum?  If so, let's try to limit the number of topics we search through.
	if ($modSettings['totalMessages'] > 100000)
	{
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_replies != {int:no_replies}' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved}' : '') . '
			ORDER BY num_replies DESC
			LIMIT 100',
			array(
				'no_replies' => 0,
				'is_approved' => 1,
			)
		);
		$topic_ids = array();
		while ($row = $db->fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		$db->free_result($request);
	}
	else
		$topic_ids = array();

	$topic_reply_result = $db->query('', '
		SELECT m.subject, t.num_replies, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '')) . '
		ORDER BY t.num_replies DESC
		LIMIT {int:topic_replies}',
		array(
			'topic_list' => $topic_ids,
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
			'topic_replies' => isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 10,
		)
	);
	$top_topics_replies = array();
	$max_num_replies = 1;
	while ($row_topic_reply = $db->fetch_assoc($topic_reply_result))
	{
		censorText($row_topic_reply['subject']);
		$top_topics_replies[] = array(
			'id' => $row_topic_reply['id_topic'],
			'board' => array(
				'id' => $row_topic_reply['id_board'],
				'name' => $row_topic_reply['name'],
				'href' => $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0">' . $row_topic_reply['name'] . '</a>'
			),
			'subject' => $row_topic_reply['subject'],
			'num_replies' => $row_topic_reply['num_replies'],
			'href' => $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0">' . $row_topic_reply['subject'] . '</a>'
		);

		if ($max_num_replies < $row_topic_reply['num_replies'])
			$max_num_replies = $row_topic_reply['num_replies'];
	}
	$db->free_result($topic_reply_result);

	return $top_topics_replies;
}

/**
 * Loads a list of top x topic views, x is configurable via $modSettings['stats_limit'].
 *
 * @return array
 */
function topTopicViews()
{
	global $modSettings, $scripturl;

	$db = database();

	// Large forums may need a bit more prodding...
	if ($modSettings['totalMessages'] > 100000)
	{
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_views != {int:no_views}
			ORDER BY num_views DESC
			LIMIT 100',
			array(
				'no_views' => 0,
			)
		);
		$topic_ids = array();
		while ($row = $db->fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		$db->free_result($request);
	}
	else
		$topic_ids = array();

		$topic_view_result = $db->query('', '
		SELECT m.subject, t.num_views, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '')) . '
		ORDER BY t.num_views DESC
		LIMIT {int:topic_views}',
		array(
			'topic_list' => $topic_ids,
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
			'topic_views' => isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 10,
		)
	);
	$top_topics_views = array();
	$max_num_views = 1;
	while ($row_topic_views = $db->fetch_assoc($topic_view_result))
	{
		censorText($row_topic_views['subject']);

		$top_topics_views[] = array(
			'id' => $row_topic_views['id_topic'],
			'board' => array(
				'id' => $row_topic_views['id_board'],
				'name' => $row_topic_views['name'],
				'href' => $scripturl . '?board=' . $row_topic_views['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_views['id_board'] . '.0">' . $row_topic_views['name'] . '</a>'
			),
			'subject' => $row_topic_views['subject'],
			'num_views' => $row_topic_views['num_views'],
			'href' => $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0">' . $row_topic_views['subject'] . '</a>'
		);

		if ($max_num_views < $row_topic_views['num_views'])
			$max_num_views = $row_topic_views['num_views'];
	}
	$db->free_result($topic_view_result);

	return $top_topics_views;
}

function topTopicStarter()
{
	global $modSettings, $scripturl;

	$db = database();

	// Try to cache this when possible, because it's a little unavoidably slow.
	if (($members = cache_get_data('stats_top_starters', 360)) == null)
	{
		$request = $db->query('', '
			SELECT id_member_started, COUNT(*) AS hits
			FROM {db_prefix}topics' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			WHERE id_board != {int:recycle_board}' : '') . '
			GROUP BY id_member_started
			ORDER BY hits DESC
			LIMIT 20',
			array(
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		$members = array();
		while ($row = $db->fetch_assoc($request))
			$members[$row['id_member_started']] = $row['hits'];
		$db->free_result($request);

		cache_put_data('stats_top_starters', $members, 360);
	}

	if (empty($members))
		$members = array(0 => 0);

	$members_result = $db->query('top_topic_starters', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})
		ORDER BY FIND_IN_SET(id_member, {string:top_topic_posters})
		LIMIT {int:topic_starter}',
		array(
			'member_list' => array_keys($members),
			'top_topic_posters' => implode(',', array_keys($members)),
			'topic_starter' => isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 10,
		)
	);
	$top_starters = array();
	$max_num_topics = 1;
	while ($row_members = $db->fetch_assoc($members_result))
	{
		$top_starters[] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num_topics' => $members[$row_members['id_member']],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		if ($max_num_topics < $members[$row_members['id_member']])
			$max_num_topics = $members[$row_members['id_member']];
	}

	foreach ($top_starters as $i => $topic)
		{
			$top_starters[$i]['post_percent'] = round(($topic['num_topics'] * 100) / $max_num_topics);
			$top_starters[$i]['num_topics'] = comma_format($context['top_starters'][$i]['num_topics']);
		}
	$db->free_result($members_result);

	return $top_starters;
}