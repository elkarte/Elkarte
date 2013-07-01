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