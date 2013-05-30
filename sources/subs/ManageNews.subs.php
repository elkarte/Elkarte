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

/**
 * Prepares an array of the forum news items for display in the template
 *
 * @return array
 */
function list_getNews()
{
	global $modSettings;

	$admin_current_news = array();

	// Ready the current news.
	foreach (explode("\n", $modSettings['news']) as $id => $line)
		$admin_current_news[$id] = array(
			'id' => $id,
			'unparsed' => un_preparsecode($line),
			'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', parse_bbc($line)),
		);

	$admin_current_news['last'] = array(
		'id' => 'last',
		'unparsed' => '<div id="moreNewsItems"></div>
		<noscript><textarea rows="3" cols="65" name="news[]" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';"></textarea></noscript>',
		'parsed' => '<div id="moreNewsItems_preview"></div>',
	);

	return $admin_current_news;
}

/**
 * Get a list of all full banned users.  Use their Username and email to find them.
 * Only get the ones that can't login to turn off notification.
 *
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
			AND (COALESCE(bg.expire_time, 1=1) OR bg.expire_time > {int:current_time})
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
		$condition_array_params['email_' . $count++] = $row['email_address'];
	}
	$db->free_result($request);

	if (!empty($condition_array))
	{
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address IN(' . implode(', ', $condition_array) .')',
			$condition_array_params
		);
		while ($row = $db->fetch_assoc($request))
			$excludes['exclude_members'][] = $row['id_member'];
		$db->free_result($request);
	}

	return $excludes;
}

/**
 * Get a list of our local board moderators.
 *
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
 * @param string $sendQuery
 * @param string $sendParams
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