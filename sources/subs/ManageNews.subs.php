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

function getExtraGroups()
{
	global $smcFunc, $modSettings, $txt;

	$groups = array(
		'groups' => array(),
		'membergroups' => array(),
		'postgroups' => array(),
	);
	// If we have post groups disabled then we need to give a "ungrouped members" option.
	if (empty($modSettings['permission_enable_postgroups']))
	{
		$groups['groups'][0] = array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'member_count' => 0,
		);
		$groups['membergroups'][0] = 0;
	}

	// Get all the extra groups as well as Administrator and Global Moderator.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups' . (empty($modSettings['permission_enable_postgroups']) ? '
		WHERE min_posts = {int:min_posts}' : '') . '
		GROUP BY id_group, min_posts, group_name
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'min_posts' => -1,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$groups['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'member_count' => 0,
		);

		if ($row['min_posts'] == -1)
			$groups['membergroups'][$row['id_group']] = $row['id_group'];
		else
			$groups['postgroups'][$row['id_group']] = $row['id_group'];
	}
	$smcFunc['db_free_result']($request);

	return $groups;
}


function excludeBannedMembers()
{
	global $smcFunc;

	$excludes = array();

	// Get a list of all full banned users.  Use their Username and email to find them.
	// Only get the ones that can't login to turn off notification.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$excludes[] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$condition_array[] = '{string:email_' . $count . '}';
		$condition_array_params['email_' . $count++] = $row['email_address'];
	}
	$smcFunc['db_free_result']($request);

	if (!empty($condition_array))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address IN(' . implode(', ', $condition_array) .')',
			$condition_array_params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$excludes['exclude_members'][] = $row['id_member'];
		$smcFunc['db_free_result']($request);
	}

	return $excludes;
}

function getModerators()
{
	global $smcFunc;

	$mods = array();

	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT mem.id_member AS identifier
		FROM {db_prefix}members AS mem
			INNER JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member)
		WHERE mem.is_activated = {int:is_activated}',
		array(
			'is_activated' => 1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$mods[] = $row['identifier'];
	$smcFunc['db_free_result']($request);

	return $mods;
}


function getNewsletterRecipients($sendQuery, $sendParams, $start, $increment, $counter)
{
	global $smcFunc;

	$recipients = array();

	$result = $smcFunc['db_query']('', '
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

	while ($row = $smcFunc['db_fetch_assoc']($result))
		$recipients[] = $row;
	$smcFunc['db_free_result']($result);

	return $recipients;
}