<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains nosy functions.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

function viewers($id, $session, $type = 'topic')
{
	global $smcFunc;

	if (!in_array($type, array('topic', 'board')))
	{
		// Dunno what you want!
		$type = 'topic';
	}

	$viewers = array();
	$request = $smcFunc['db_query']('', '
		SELECT
			lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
			mg.online_color, mg.id_group, mg.group_name
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_member_group} THEN mem.id_post_group ELSE mem.id_group END)
		WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
		array(
			'reg_member_group' => 0,
			'in_url_string' => 's:5:"' . $type . '";i:' . $id . ';',
			'session' => $session
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$viewers[] = $row;
	}
	$smcFunc['db_free_result']($request);

	return $viewers;
}

/**
 * Format viewers list for display, for a topic or board.
 *
 * @param int $id id of the element (topic or board) we're watching
 * @param string $type = 'topic, 'topic' or 'board'
 */
function formatViewers($id, $type)
{
	global $user_info, $context, $scripturl;

	// Lets say there's no one around. (what? could happen!)
	$context['view_members'] = array();
	$context['view_members_list'] = array();
	$context['view_num_hidden'] = 0;
	$context['view_num_guests'] = 0;

	$viewers = viewers($id, $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id(), $type);

	foreach ($viewers as $viewer)
	{
		// is this a guest?
		if (empty($viewer['id_member']))
		{
			$context['view_num_guests']++;
			continue;
		}

		// it's a member. We format them with links 'n stuff.
		if (!empty($viewer['online_color']))
			$link = '<a href="' . $scripturl . '?action=profile;u=' . $viewer['id_member'] . '" style="color: ' . $viewer['online_color'] . ';">' . $viewer['real_name'] . '</a>';
		else
			$link = '<a href="' . $scripturl . '?action=profile;u=' . $viewer['id_member'] . '">' . $viewer['real_name'] . '</a>';

		$is_buddy = in_array($viewer['id_member'], $user_info['buddies']);
		if ($is_buddy)
			$link = '<strong>' . $link . '</strong>';

		// fill the summary list
		if (!empty($viewer['show_online']) || allowedTo('moderate_forum'))
			$context['view_members_list'][$viewer['log_time'] . $viewer['member_name']] = empty($viewer['show_online']) ? '<em>' . $link . '</em>' : $link;

		// fill the detailed list
		$context['view_members'][$viewer['log_time'] . $viewer['member_name']] = array(
			'id' => $viewer['id_member'],
			'username' => $viewer['member_name'],
			'name' => $viewer['real_name'],
			'group' => $viewer['id_group'],
			'href' => $scripturl . '?action=profile;u=' . $viewer['id_member'],
			'link' => $link,
			'is_buddy' => $is_buddy,
			'hidden' => empty($viewer['show_online']),
		);

		// add the hidden members to the count (and don't show them in the template)
		if (empty($viewer['show_online']))
			$context['view_num_hidden']++;
	}

	// Sort them out.
	krsort($context['view_members_list']);
	krsort($context['view_members']);
}