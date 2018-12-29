<?php

/**
 * Functions to help with managing the site news and newsletters
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
 * Prepares an array of the forum news items
 *
 * @package News
 * @return array
 */
function getNews()
{
	global $modSettings;

	$admin_current_news = array();

	$bbc_parser = \BBC\ParserWrapper::instance();

	// Ready the current news.
	foreach (explode("\n", $modSettings['news']) as $id => $line)
		$admin_current_news[$id] = array(
			'id' => $id,
			'unparsed' => un_preparsecode($line),
			'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', $bbc_parser->parseNews($line)),
		);

	$admin_current_news['last'] = array(
		'id' => 'last',
		'unparsed' => '',
		'parsed' => '<div id="moreNewsItems_preview"></div>',
	);

	return $admin_current_news;
}

/**
 * Get a list of all full banned users.
 *
 * - Use their Username and email to find them.
 * - Only get the ones that can't login to turn off notification.
 *
 * @package News
 * @return array
 */
function excludeBannedMembers()
{
	$db = database();

	$excludes = array();

	$request = $db->query('', '
		SELECT DISTINCT mem.id_member
		FROM {db_prefix}ban_groups AS bg
			INNER JOIN {db_prefix}ban_items AS bi ON (bg.id_ban_group = bi.id_ban_group)
			INNER JOIN {db_prefix}members AS mem ON (bi.id_member = mem.id_member)
		WHERE (bg.cannot_access = {int:cannot_access} OR bg.cannot_login = {int:cannot_login})
			AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})',
		array(
			'cannot_access' => 1,
			'cannot_login' => 1,
			'current_time' => time(),
		)
	);
	while ($row = $db->fetch_assoc($request))
		$excludes[] = $row['id_member'];
	$db->free_result($request);

	$request = $db->query('', '
		SELECT DISTINCT bi.email_address
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE (bg.cannot_access = {int:cannot_access} OR bg.cannot_login = {int:cannot_login})
			AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
			AND bi.email_address != {string:blank_string}',
		array(
			'cannot_access' => 1,
			'cannot_login' => 1,
			'current_time' => time(),
			'blank_string' => '',
		)
	);
	$condition_array = array();
	$condition_array_params = array();
	$count = 0;
	while ($row = $db->fetch_assoc($request))
	{
		$condition_array[] = '{string:email_' . $count . '}';
		$condition_array_params['email_' . ($count++)] = $row['email_address'];
	}
	$db->free_result($request);

	if (!empty($condition_array))
	{
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address IN(' . implode(', ', $condition_array) . ')',
			$condition_array_params
		);
		while ($row = $db->fetch_assoc($request))
			$excludes[] = $row['id_member'];
		$db->free_result($request);
	}

	return $excludes;
}

/**
 * Get a list of our local board moderators.
 *
 * @package News
 * @return array
 */
function getModerators()
{
	$db = database();

	$mods = array();

	$request = $db->query('', '
		SELECT DISTINCT mem.id_member AS identifier
		FROM {db_prefix}members AS mem
			INNER JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member)
		WHERE mem.is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$mods[] = $row['identifier'];
	$db->free_result($request);

	return $mods;
}

/**
 * Lists our newsletter recipients, step by step.
 *
 * @package News
 * @param string $sendQuery
 * @param mixed[] $sendParams
 * @param int $start
 * @param int $increment
 * @param int $counter
 * @return array
 */
function getNewsletterRecipients($sendQuery, $sendParams, $start, $increment, $counter)
{
	$db = database();

	$recipients = array();

	$result = $db->query('', '
		SELECT mem.id_member, mem.email_address, mem.real_name, mem.id_group, mem.additional_groups, mem.id_post_group
		FROM {db_prefix}members AS mem
		WHERE mem.id_member > {int:min_id_member}
			AND mem.id_member < {int:max_id_member}
			AND ' . $sendQuery . '
			AND mem.is_activated = {int:is_activated}
		ORDER BY mem.id_member ASC
		LIMIT {int:atonce}',
		array_merge($sendParams, array(
			'min_id_member' => $start,
			'max_id_member' => $start + $increment - $counter,
			'atonce' => $increment - $counter,
			'regular_group' => 0,
			'notify_announcements' => 1,
			'is_activated' => 1,
		))
	);
	while ($row = $db->fetch_assoc($result))
		$recipients[] = $row;
	$db->free_result($result);

	return $recipients;
}

/**
 * Find the latest posts that:
 * - are the first post in their topic.
 * - are on an any board OR in a specified board.
 * - can be seen by this user.
 * - are actually the latest posts.
 *
 * @package News
 *
 * @param string $query_this_board passed to query, assumed raw and inserted as such
 * @param int $board
 * @param int $limit
 *
 * @return array
 */
function getXMLNews($query_this_board, $board, $limit)
{
	global $modSettings, $board, $context;

	$db = database();

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $db->query('', '
			SELECT
				m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.modified_time,
				m.icon, t.id_topic, t.id_board, t.num_replies,
				b.name AS bname,
				mem.hide_email, COALESCE(mem.id_member, 0) AS id_member,
				COALESCE(mem.email_address, m.poster_email) AS poster_email,
				COALESCE(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE ' .  $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND t.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_first_msg DESC
			LIMIT {int:limit}',
			array(
				'current_board' => $board,
				'is_approved' => 1,
				'limit' => $limit,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $limit results, we try again with an unoptimized version covering all rows.
		if ($loops < 2 && $db->num_rows($request) < $limit)
		{
			$db->free_result($request);

			if (empty($_REQUEST['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = 'm.id_msg >= t.id_first_msg';

			$context['optimize_msg']['highest'] = 'm.id_msg <= t.id_last_msg';
			$loops++;
		}
		else
			$done = true;
	}
	$data = array();
	while ($row = $db->fetch_assoc($request))
		$data[] = $row;

	$db->free_result($request);

	return $data;
}

/**
 * Get the recent topics to display.
 *
 * @package News
 *
 * @param string $query_this_board passed to query, assumed raw and inserted as such
 * @param int $board
 * @param int $limit
 *
 * @return array
 */
function getXMLRecent($query_this_board, $board, $limit)
{
	global $modSettings, $board, $context;

	$db = database();

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $db->query('', '
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE ' . $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND m.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $limit,
				'current_board' => $board,
				'is_approved' => 1,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $limit results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $db->num_rows($request) < $limit)
		{
			$db->free_result($request);

			if (empty($_REQUEST['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';

			$loops++;
		}
		else
			$done = true;
	}
	$messages = array();
	while ($row = $db->fetch_assoc($request))
		$messages[] = $row['id_msg'];
	$db->free_result($request);

	// No messages found, then return nothing
	if (empty($messages))
		return array();

	// Find the most recent posts from our message list that this user can see.
	$request = $db->query('', '
		SELECT
			m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic, t.id_board,
			b.name AS bname, t.num_replies, m.id_member, m.icon, mf.id_member AS id_first_member,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, mf.subject AS first_subject,
			COALESCE(memf.real_name, mf.poster_name) AS first_poster_name, mem.hide_email,
			COALESCE(mem.email_address, m.poster_email) AS poster_email, m.modified_time
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			' . (empty($board) ? '' : 'AND t.id_board = {int:current_board}') . '
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'limit' => $limit,
			'current_board' => $board,
			'message_list' => $messages,
		)
	);
	$data = array();
	while ($row = $db->fetch_assoc($request))
		$data[] = $row;

	$db->free_result($request);

	return $data;
}
