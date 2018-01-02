<?php

/**
 * This file is holds low-level database work used by the Stats.
 * Some functions/queries (or all :P) might be duplicate, along Elk.
 * They'll be here to avoid including many files in action_stats, and
 * perhaps for use of addons in a similar way they were using some
 * SSI functions.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Return the number of currently online members.
 *
 * @return double
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
 * Gets totals for posts, topics, most online, new users, page views, emails
 *
 * - Can be used (and is) with days up value to generate averages.
 *
 * @return array
 */
function getAverages()
{
	$db = database();

	$result = $db->query('', '
		SELECT
			SUM(posts) AS posts, SUM(topics) AS topics, SUM(registers) AS registers,
			SUM(most_on) AS most_on, MIN(date) AS date, SUM(hits) AS hits, SUM(email) AS email
		FROM {db_prefix}log_activity',
		array(
		)
	);
	$row = $db->fetch_assoc($result);
	$db->free_result($result);

	return $row;
}

/**
 * Get the count of categories
 *
 * @return int
 */
function numCategories()
{
	$db = database();

	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}categories',
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

	return (int) $online;
}

/**
 * Loads a list of top x posters
 *
 * - x is configurable via $modSettings['stats_limit'].
 *
 * @param int|null $limit if empty defaults to 10
 * @return array
 */
function topPosters($limit = null)
{
	global $scripturl, $modSettings;

	$db = database();

	// If there is a default setting, let's not retrieve something bigger
	if (isset($modSettings['stats_limit']))
		$limit = empty($limit) ? $modSettings['stats_limit'] : ($limit < $modSettings['stats_limit'] ? $limit : $modSettings['stats_limit']);
	// Otherwise, fingers crossed and let's grab what is asked
	else
		$limit = empty($limit) ? 10 : $limit;

	// Make the query to the the x number of top posters
	$members_result = $db->query('', '
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		WHERE posts > {int:no_posts}
		ORDER BY posts DESC
		LIMIT {int:limit_posts}',
		array(
			'no_posts' => 0,
			'limit_posts' => $limit,
		)
	);
	$top_posters = array();
	$max_num_posts = 1;
	while ($row_members = $db->fetch_assoc($members_result))
	{
		// Build general member information for each top poster
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

	// Determine the percents and then format the num_posts
	foreach ($top_posters as $i => $poster)
	{
		$top_posters[$i]['post_percent'] = round(($poster['num_posts'] * 100) / $max_num_posts);
		$top_posters[$i]['num_posts'] = comma_format($top_posters[$i]['num_posts']);
	}

	return $top_posters;
}

/**
 * Loads a list of top x boards with number of board posts and board topics
 *
 * - x is configurable via $modSettings['stats_limit'].
 *
 * @param int|null $limit if not supplied, defaults to 10
 * @param boolean $read_status
 * @return array
 */
function topBoards($limit = null, $read_status = false)
{
	global $modSettings, $scripturl, $user_info;

	$db = database();

	// If there is a default setting, let's not retrieve something bigger
	if (isset($modSettings['stats_limit']))
		$limit = empty($limit) ? $modSettings['stats_limit'] : ($limit < $modSettings['stats_limit'] ? $limit : $modSettings['stats_limit']);
	// Otherwise, fingers crossed and let's grab what is asked
	else
		$limit = empty($limit) ? 10 : $limit;

	$boards_result = $db->query('', '
		SELECT b.id_board, b.name, b.num_posts, b.num_topics' . ($read_status ? ',' . (!$user_info['is_guest'] ? ' 1 AS is_read' : '
			(COALESCE(lb.id_msg, 0) >= b.id_last_msg) AS is_read') : '') . '
		FROM {db_prefix}boards AS b' . ($read_status ? '
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})' : '') . '
		WHERE {query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND b.redirect = {string:blank_redirect}
		ORDER BY num_posts DESC
		LIMIT {int:limit_boards}',
		array(
			'recycle_board' => $modSettings['recycle_board'],
			'blank_redirect' => '',
			'limit_boards' => $limit,
			'current_member' => $user_info['id'],
		)
	);
	$top_boards = array();
	$max_num_posts = 1;
	while ($row_board = $db->fetch_assoc($boards_result))
	{
		// Load the boards info, number of posts, topics etc
		$top_boards[$row_board['id_board']] = array(
			'id' => $row_board['id_board'],
			'name' => $row_board['name'],
			'num_posts' => $row_board['num_posts'],
			'num_topics' => $row_board['num_topics'],
			'href' => $scripturl . '?board=' . $row_board['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['name'] . '</a>'
		);
		if ($read_status)
			$top_boards[$row_board['id_board']]['is_read'] = !empty($row_board['is_read']);

		if ($max_num_posts < $row_board['num_posts'])
			$max_num_posts = $row_board['num_posts'];
	}
	$db->free_result($boards_result);

	// Determine the post percentages for the boards, then format the numbers
	foreach ($top_boards as $i => $board)
	{
		$top_boards[$i]['post_percent'] = round(($board['num_posts'] * 100) / $max_num_posts);
		$top_boards[$i]['num_posts'] = comma_format($top_boards[$i]['num_posts']);
		$top_boards[$i]['num_topics'] = comma_format($top_boards[$i]['num_topics']);
	}

	return $top_boards;
}

/**
 * Loads a list of top x topics by replies
 *
 * - x is configurable via $modSettings['stats_limit'].
 *
 * @param int|null $limit if not supplied, defaults to 10
 * @return array
 */
function topTopicReplies($limit = null)
{
	global $modSettings, $scripturl;

	$db = database();

	// If there is a default setting, let's not retrieve something bigger
	if (isset($modSettings['stats_limit']))
		$limit = empty($limit) ? $modSettings['stats_limit'] : ($limit < $modSettings['stats_limit'] ? $limit : $modSettings['stats_limit']);
	// Otherwise, fingers crossed and let's grab what is asked
	else
		$limit = empty($limit) ? 10 : $limit;

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

	// Find the top x topics by number of replys
	$topic_reply_result = $db->query('', '
		SELECT m.subject, t.num_replies, t.num_views, t.id_board, t.id_topic, b.name
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
			'topic_replies' => $limit,
		)
	);
	$top_topics_replies = array();
	$max_num_replies = 1;
	while ($row_topic_reply = $db->fetch_assoc($topic_reply_result))
	{
		// Build out this topics details for controller use
		$row_topic_reply['subject'] = censor($row_topic_reply['subject']);
		$top_topics_replies[$row_topic_reply['id_topic']] = array(
			'id' => $row_topic_reply['id_topic'],
			'board' => array(
				'id' => $row_topic_reply['id_board'],
				'name' => $row_topic_reply['name'],
				'href' => $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_reply['id_board'] . '.0">' . $row_topic_reply['name'] . '</a>'
			),
			'subject' => $row_topic_reply['subject'],
			'num_replies' => $row_topic_reply['num_replies'],
			'num_views' => $row_topic_reply['num_views'],
			'href' => $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0">' . $row_topic_reply['subject'] . '</a>'
		);

		if ($max_num_replies < $row_topic_reply['num_replies'])
			$max_num_replies = $row_topic_reply['num_replies'];
	}
	$db->free_result($topic_reply_result);

	// Calculate the percentages and final formatting of the number
	foreach ($top_topics_replies as $i => $topic)
	{
		$top_topics_replies[$i]['post_percent'] = round(($topic['num_replies'] * 100) / $max_num_replies);
		$top_topics_replies[$i]['num_replies'] = comma_format($top_topics_replies[$i]['num_replies']);
	}

	return $top_topics_replies;
}

/**
 * Loads a list of top x topics by number of views
 *
 * - x is configurable via $modSettings['stats_limit'].
 *
 * @param int|null $limit if not supplied, defaults to 10
 * @return array
 */
function topTopicViews($limit = null)
{
	global $modSettings, $scripturl;

	$db = database();

	// If there is a default setting, let's not retrieve something bigger
	if (isset($modSettings['stats_limit']))
		$limit = empty($limit) ? $modSettings['stats_limit'] : ($limit < $modSettings['stats_limit'] ? $limit : $modSettings['stats_limit']);
	// Otherwise, fingers crossed and let's grab what is asked
	else
		$limit = empty($limit) ? 10 : $limit;

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
		SELECT m.subject, t.num_views, t.num_replies, t.id_board, t.id_topic, b.name
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
			'topic_views' => $limit,
		)
	);
	$top_topics_views = array();
	$max_num_views = 1;
	while ($row_topic_views = $db->fetch_assoc($topic_view_result))
	{
		// Build the topic result array
		$row_topic_views['subject'] = censor($row_topic_views['subject']);
		$top_topics_views[$row_topic_views['id_topic']] = array(
			'id' => $row_topic_views['id_topic'],
			'board' => array(
				'id' => $row_topic_views['id_board'],
				'name' => $row_topic_views['name'],
				'href' => $scripturl . '?board=' . $row_topic_views['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row_topic_views['id_board'] . '.0">' . $row_topic_views['name'] . '</a>'
			),
			'subject' => $row_topic_views['subject'],
			'num_replies' => $row_topic_views['num_replies'],
			'num_views' => $row_topic_views['num_views'],
			'href' => $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0">' . $row_topic_views['subject'] . '</a>'
		);

		if ($max_num_views < $row_topic_views['num_views'])
			$max_num_views = $row_topic_views['num_views'];
	}
	$db->free_result($topic_view_result);

	// Percentages and final formatting
	foreach ($top_topics_views as $i => $topic)
	{
		$top_topics_views[$i]['post_percent'] = round(($topic['num_views'] * 100) / $max_num_views);
		$top_topics_views[$i]['num_views'] = comma_format($top_topics_views[$i]['num_views']);
	}

	return $top_topics_views;
}

/**
 * Loads a list of top x topic starters
 *
 * - x is configurable via $modSettings['stats_limit'].
 *
 * @return array
 */
function topTopicStarter()
{
	global $modSettings, $scripturl;

	$db = database();
	$members = array();
	$top_starters = array();
	$max_num_topics = 1;

	// Try to cache this when possible, because it's a little unavoidably slow.
	if (!Cache::instance()->getVar($members, 'stats_top_starters', 360) || empty($members))
	{
		$request = $db->query('', '
			SELECT id_member_started, COUNT(*) AS hits
			FROM {db_prefix}topics' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			WHERE id_board != {int:recycle_board}' : '') . '
			GROUP BY id_member_started',
			array(
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		$members = array();
		while ($row = $db->fetch_row($request))
			$members[$row[0]] = $row[1];
		$db->free_result($request);
		arsort($members);
		$members = array_slice($members, 0, $modSettings['stats_limit'] ?? 10, true);

		Cache::instance()->put('stats_top_starters', $members, 360);
	}
	$max_num_topics = max($members);

	if (empty($members))
		$members = array(0 => 0);

	// Find the top starters of topics
	$members_result = $db->query('7', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})',
		array(
			'member_list' => array_keys($members),
		)
	);
	while ($row_members = $db->fetch_assoc($members_result))
	{
		// Our array of spammers, er topic starters !
		$top_starters[$row_members['id_member']] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num_topics' => comma_format($members[$row_members['id_member']]),
			'post_percent' => round(($members[$row_members['id_member']] * 100) / $max_num_topics),
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

	}
	$db->free_result($members_result);

	// Even spammers must be orderly.
	uksort($top_starters, function ($a, $b) use ($members) {
		return $members[$b] <=> $members[$a];
	});

	return $top_starters;
}

/**
 * Loads a list of top users by online time
 *
 * - x is configurable via $modSettings['stats_limit'], defaults to 10
 *
 * @return array
 */
function topTimeOnline()
{
	global $modSettings, $scripturl, $txt;

	$db = database();

	$max_members = isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 10;

	// Do we have something cached that will help speed this up?
	$temp = Cache::instance()->get('stats_total_time_members', 600);

	// Get the member data, sorted by total time logged in
	$members_result = $db->query('', '
		SELECT id_member, real_name, total_time_logged_in
		FROM {db_prefix}members' . (!empty($temp) ? '
		WHERE id_member IN ({array_int:member_list_cached})' : '') . '
		ORDER BY total_time_logged_in DESC
		LIMIT {int:top_online}',
		array(
			'member_list_cached' => $temp,
			'top_online' => isset($modSettings['stats_limit']) ? $modSettings['stats_limit'] : 20,
		)
	);
	$top_time_online = array();
	$temp2 = array();
	$max_time_online = 1;
	while ($row_members = $db->fetch_assoc($members_result))
	{
		$temp2[] = (int) $row_members['id_member'];
		if (count($top_time_online) >= $max_members)
			continue;

		// Figure out the days, hours and minutes.
		$timeDays = floor($row_members['total_time_logged_in'] / 86400);
		$timeHours = floor(($row_members['total_time_logged_in'] % 86400) / 3600);

		// Figure out which things to show... (days, hours, minutes, etc.)
		$timelogged = '';
		if ($timeDays > 0)
			$timelogged .= $timeDays . $txt['totalTimeLogged5'];

		if ($timeHours > 0)
			$timelogged .= $timeHours . $txt['totalTimeLogged6'];

		$timelogged .= floor(($row_members['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged7'];

		// Finally add it to the stats array
		$top_time_online[] = array(
			'id' => $row_members['id_member'],
			'name' => $row_members['real_name'],
			'time_online' => $timelogged,
			'seconds_online' => $row_members['total_time_logged_in'],
			'href' => $scripturl . '?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		if ($max_time_online < $row_members['total_time_logged_in'])
			$max_time_online = $row_members['total_time_logged_in'];
	}
	$db->free_result($members_result);

	// As always percentages are next
	foreach ($top_time_online as $i => $member)
		$top_time_online[$i]['time_percent'] = round(($member['seconds_online'] * 100) / $max_time_online);

	// Cache the ones we found for a bit, just so we don't have to look again.
	if ($temp !== $temp2)
		Cache::instance()->put('stats_total_time_members', $temp2, 600);

	return $top_time_online;
}

/**
 * Loads the monthly statistics and returns them in $context
 *
 * - page views, new registrations, topics posts, most on etc
 *
 */
function monthlyActivity()
{
	global $context, $scripturl, $txt;

	$db = database();

	$months_result = $db->query('', '
		SELECT
			YEAR(date) AS stats_year, MONTH(date) AS stats_month, SUM(hits) AS hits, SUM(registers) AS registers, SUM(topics) AS topics, SUM(posts) AS posts, MAX(most_on) AS most_on, COUNT(*) AS num_days
		FROM {db_prefix}log_activity
		GROUP BY stats_year, stats_month',
		array()
	);
	while ($row_months = $db->fetch_assoc($months_result))
	{
		$id_month = $row_months['stats_year'] . sprintf('%02d', $row_months['stats_month']);
		$expanded = !empty($_SESSION['expanded_stats'][$row_months['stats_year']]) && in_array($row_months['stats_month'], $_SESSION['expanded_stats'][$row_months['stats_year']]);

		if (!isset($context['yearly'][$row_months['stats_year']]))
			$context['yearly'][$row_months['stats_year']] = array(
				'year' => $row_months['stats_year'],
				'new_topics' => 0,
				'new_posts' => 0,
				'new_members' => 0,
				'most_members_online' => 0,
				'hits' => 0,
				'num_months' => 0,
				'months' => array(),
				'expanded' => false,
				'current_year' => $row_months['stats_year'] == date('Y'),
			);

		$context['yearly'][$row_months['stats_year']]['months'][(int) $row_months['stats_month']] = array(
			'id' => $id_month,
			'date' => array(
				'month' => sprintf('%02d', $row_months['stats_month']),
				'year' => $row_months['stats_year']
			),
			'href' => $scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $id_month . '#m' . $id_month,
			'link' => '<a href="' . $scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $id_month . '#m' . $id_month . '">' . $txt['months'][(int) $row_months['stats_month']] . ' ' . $row_months['stats_year'] . '</a>',
			'month' => $txt['months'][(int) $row_months['stats_month']],
			'year' => $row_months['stats_year'],
			'new_topics' => comma_format($row_months['topics']),
			'new_posts' => comma_format($row_months['posts']),
			'new_members' => comma_format($row_months['registers']),
			'most_members_online' => comma_format($row_months['most_on']),
			'hits' => comma_format($row_months['hits']),
			'num_days' => $row_months['num_days'],
			'days' => array(),
			'expanded' => $expanded
		);

		$context['yearly'][$row_months['stats_year']]['new_topics'] += $row_months['topics'];
		$context['yearly'][$row_months['stats_year']]['new_posts'] += $row_months['posts'];
		$context['yearly'][$row_months['stats_year']]['new_members'] += $row_months['registers'];
		$context['yearly'][$row_months['stats_year']]['hits'] += $row_months['hits'];
		$context['yearly'][$row_months['stats_year']]['num_months']++;
		$context['yearly'][$row_months['stats_year']]['expanded'] |= $expanded;
		$context['yearly'][$row_months['stats_year']]['most_members_online'] = max($context['yearly'][$row_months['stats_year']]['most_members_online'], $row_months['most_on']);
	}

	krsort($context['yearly']);
}

/**
 * Loads the statistics on a daily basis in $context.
 *
 * - called by action_stats().
 *
 * @param string $condition_string
 * @param mixed[] $condition_parameters = array()
 */
function getDailyStats($condition_string, $condition_parameters = array())
{
	global $context;

	$db = database();

	// Activity by day.
	$days_result = $db->query('', '
		SELECT YEAR(date) AS stats_year, MONTH(date) AS stats_month, DAYOFMONTH(date) AS stats_day, topics, posts, registers, most_on, hits
		FROM {db_prefix}log_activity
		WHERE ' . $condition_string . '
		ORDER BY stats_day DESC',
		$condition_parameters
	);
	while ($row_days = $db->fetch_assoc($days_result))
		$context['yearly'][$row_days['stats_year']]['months'][(int) $row_days['stats_month']]['days'][] = array(
			'day' => sprintf('%02d', $row_days['stats_day']),
			'month' => sprintf('%02d', $row_days['stats_month']),
			'year' => $row_days['stats_year'],
			'new_topics' => comma_format($row_days['topics']),
			'new_posts' => comma_format($row_days['posts']),
			'new_members' => comma_format($row_days['registers']),
			'most_members_online' => comma_format($row_days['most_on']),
			'hits' => comma_format($row_days['hits'])
		);
	$db->free_result($days_result);
}

/**
 * Returns the number of topics a user has started, including ones on boards
 * they may no longer have access on.
 *
 * - Does not count topics that are in the recycle board
 *
 * @param int $memID
 */
function UserStatsTopicsStarted($memID)
{
	global $modSettings;

	$db = database();

	// Number of topics started.
	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : ''),
		array(
			'current_member' => $memID,
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	list ($num_topics) = $db->fetch_row($result);
	$db->free_result($result);

	return $num_topics;
}

/**
 * Returns the number of polls a user has started, including ones on boards
 * they may no longer have access on.
 *
 * - Does not count topics that are in the recycle board
 *
 * @param int $memID
 */
function UserStatsPollsStarted($memID)
{
	global $modSettings;

	$db = database();

	// Number polls started.
	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : '') . '
			AND id_poll != {int:no_poll}',
		array(
			'current_member' => $memID,
			'recycle_board' => $modSettings['recycle_board'],
			'no_poll' => 0,
		)
	);
	list ($num_polls) = $db->fetch_row($result);
	$db->free_result($result);

	return $num_polls;
}

/**
 * Returns the number of polls a user has voted in, including ones on boards
 * they may no longer have access on.
 *
 * @param int $memID
 */
function UserStatsPollsVoted($memID)
{
	$db = database();

	// Number polls voted in.
	$result = $db->query('distinct_poll_votes', '
		SELECT COUNT(DISTINCT id_poll)
		FROM {db_prefix}log_polls
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $memID,
		)
	);
	list ($num_votes) = $db->fetch_row($result);
	$db->free_result($result);

	return $num_votes;
}

/**
 * Finds the 1-N list of boards that a user posts in most often
 *
 * - Returns array with some basic stats of post percent per board
 *
 * @param int $memID
 * @param int $limit
 */
function UserStatsMostPostedBoard($memID, $limit = 10)
{
	global $scripturl, $user_profile;

	$db = database();

	// Find the board this member spammed most often.
	$result = $db->query('', '
		SELECT
			b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND b.count_posts = {int:count_enabled}
			AND {query_see_board}
		GROUP BY b.id_board
		ORDER BY message_count DESC
		LIMIT {int:limit}',
		array(
			'current_member' => $memID,
			'count_enabled' => 0,
			'limit' => (int) $limit,
		)
	);
	$popular_boards = array();
	while ($row = $db->fetch_assoc($result))
	{
		// Build the board details that this member is responsible for
		$popular_boards[$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'posts_percent' => $user_profile[$memID]['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $user_profile[$memID]['posts'],
			'total_posts' => $row['num_posts'],
			'total_posts_member' => $user_profile[$memID]['posts'],
		);
	}
	$db->free_result($result);

	return $popular_boards;
}

/**
 * Finds the 1-N list of boards that a user participates in most often
 *
 * - Returns array with some basic stats of post percent per board as a percent of board activity
 *
 * @param int $memID
 * @param int $limit
 */
function UserStatsMostActiveBoard($memID, $limit = 10)
{
	global $scripturl;

	$db = database();

	// Find the board this member spammed most often.
	$result = $db->query('profile_board_stats', '
		SELECT
			b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
			CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND {query_see_board}
		GROUP BY b.id_board, b.num_posts
		ORDER BY percentage DESC
		LIMIT {int:limit}',
		array(
			'current_member' => $memID,
			'limit' => (int) $limit,
		)
	);
	$board_activity = array();
	while ($row = $db->fetch_assoc($result))
	{
		// What have they been doing in this board
		$board_activity[$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'percent' => comma_format((float) $row['percentage'], 2),
			'posts_percent' => (float) $row['percentage'],
			'total_posts' => $row['num_posts'],
		);
	}
	$db->free_result($result);

	return $board_activity;
}

/**
 * Finds the users posting activity by time of day
 *
 * - Returns array with some basic stats of post percent per hour
 *
 * @param int $memID
 */
function UserStatsPostingTime($memID)
{
	global $user_info, $modSettings;

	$db = database();

	// Find the times when the users posts
	$result = $db->query('user_activity_by_time', '
		SELECT
			HOUR(FROM_UNIXTIME(poster_time + {float:time_offset})) AS hour,
			COUNT(*) AS post_count
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}' . ($modSettings['totalMessages'] > 100000 ? '
			AND id_topic > {int:top_ten_thousand_topics}' : '') . '
		GROUP BY hour',
		array(
			'current_member' => $memID,
			'top_ten_thousand_topics' => $modSettings['totalTopics'] - 10000,
			'time_offset' => (($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
		)
	);
	$maxPosts = 0;
	$realPosts = 0;
	$posts_by_time = array();
	while ($row = $db->fetch_assoc($result))
	{
		// Cast as an integer to remove the leading 0.
		$row['hour'] = (int) $row['hour'];

		$maxPosts = max($row['post_count'], $maxPosts);
		$realPosts += $row['post_count'];

		// When they post, hour by hour
		$posts_by_time[$row['hour']] = array(
			'hour' => $row['hour'],
			'hour_format' => stripos($user_info['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
			'posts' => $row['post_count'],
			'posts_percent' => 0,
			'is_last' => $row['hour'] == 23,
		);
	}
	$db->free_result($result);

	// Clean it up some more
	if ($maxPosts > 0)
	{
		for ($hour = 0; $hour < 24; $hour++)
		{
			if (!isset($posts_by_time[$hour]))
				$posts_by_time[$hour] = array(
					'hour' => $hour,
					'hour_format' => stripos($user_info['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
					'posts' => 0,
					'posts_percent' => 0,
					'relative_percent' => 0,
					'is_last' => $hour == 23,
				);
			else
			{
				$posts_by_time[$hour]['posts_percent'] = round(($posts_by_time[$hour]['posts'] * 100) / $realPosts);
				$posts_by_time[$hour]['relative_percent'] = round(($posts_by_time[$hour]['posts'] * 100) / $maxPosts);
			}
		}
	}

	// Put it in the right order.
	ksort($posts_by_time);

	return $posts_by_time;
}
