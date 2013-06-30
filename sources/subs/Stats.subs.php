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