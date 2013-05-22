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
	$db = database();

	if (!in_array($type, array('topic', 'board')))
	{
		// Dunno what you want!
		$type = 'topic';
	}

	$viewers = array();
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		$viewers[] = $row;
	}
	$db->free_result($request);

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

/**
 * This function reads from the database the add-ons credits,
 * and returns them in an array for display in credits section of the site.
 * The add-ons copyright, license, title informations are those saved from <license>
 * and <credits> tags in package.xml.
 *
 * @return array
 */
function addonsCredits()
{
	$db = database();

	if (($credits = cache_get_data('addons_credits', 86400)) === null)
	{
		$credits = array();
		$request = $db->query('substring', '
			SELECT version, name, credits
			FROM {db_prefix}log_packages
			WHERE install_state = {int:installed_mods}
				AND credits != {string:empty}
				AND SUBSTRING(filename, 1, 9) != {string:old_patch_name}
				AND SUBSTRING(filename, 1, 9) != {string:patch_name}',
			array(
				'installed_mods' => 1,
				'old_patch_name' => 'smf_patch',
				'patch_name' => 'elk_patch',
				'empty' => '',
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			$credit_info = unserialize($row['credits']);

			$copyright = empty($credit_info['copyright']) ? '' : $txt['credits_copyright'] . ' &copy; ' . Util::htmlspecialchars($credit_info['copyright']);
			$license = empty($credit_info['license']) ? '' : $txt['credits_license'] . ': ' . Util::htmlspecialchars($credit_info['license']);
			$version = $txt['credits_version'] . '' . $row['version'];
			$title = (empty($credit_info['title']) ? $row['name'] : Util::htmlspecialchars($credit_info['title'])) . ': ' . $version;

			// build this one out and stash it away
			$name = empty($credit_info['url']) ? $title : '<a href="' . $credit_info['url'] . '">' . $title . '</a>';
			$credits[] = $name . (!empty($license) ? ' | ' . $license  : '') . (!empty($copyright) ? ' | ' . $copyright  : '');
		}
		cache_put_data('addons_credits', $credits, 86400);
	}

	return $credits;
}