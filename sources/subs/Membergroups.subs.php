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
 * This file contains functions regarding manipulation of and information about membergroups.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Delete one of more membergroups.
 * Requires the manage_membergroups permission.
 * Returns true on success or false on failure.
 * Has protection against deletion of protected membergroups.
 * Deletes the permissions linked to the membergroup.
 * Takes members out of the deleted membergroups.
 *
 * @param array $groups
 *
 * @return boolean
 */
function deleteMembergroups($groups)
{
	global $modSettings;

	$db = database();

	// Make sure it's an array.
	if (!is_array($groups))
		$groups = array((int) $groups);
	else
	{
		$groups = array_unique($groups);

		// Make sure all groups are integer.
		foreach ($groups as $key => $value)
			$groups[$key] = (int) $value;
	}

	// Some groups are protected (guests, administrators, moderators, newbies).
	$protected_groups = array(-1, 0, 1, 3, 4);

	// There maybe some others as well.
	if (!allowedTo('admin_forum'))
	{
		$request = $db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		$db->free_result($request);
	}

	// Make sure they don't delete protected groups!
	$groups = array_diff($groups, array_unique($protected_groups));
	if (empty($groups))
		return false;

	// Log the deletion.
	$groups_to_log = membergroupsById($groups, 0);
	foreach ($groups_to_log as $key => $row)
		logAction('delete_group', array('group' => $row['group_name']), 'admin');

	call_integration_hook('integrate_delete_membergroups', array($groups));

	// Remove the membergroups themselves.
	$db->query('', '
		DELETE FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Remove the permissions of the membergroups.
	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Delete any outstanding requests.
	$db->query('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);

	// Update the primary groups of members.
	$db->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_group}
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'regular_group' => 0,
		)
	);

	// Update any inherited groups (Lose inheritance).
	$db->query('', '
		UPDATE {db_prefix}membergroups
		SET id_parent = {int:uninherited}
		WHERE id_parent IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
			'uninherited' => -2,
		)
	);

	// Update the additional groups of members.
	$request = $db->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:additional_groups_explode}, additional_groups) != 0',
		array(
			'additional_groups_explode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);

	// Update each member information.
	$updates = array();
	while ($row = $db->fetch_assoc($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	$db->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups))));

	// No boards can provide access to these membergroups anymore.
	$request = $db->query('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards
		WHERE FIND_IN_SET({raw:member_groups_explode}, member_groups) != 0',
		array(
			'member_groups_explode' => implode(', member_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = $db->fetch_assoc($request))
		$updates[$row['member_groups']][] = $row['id_board'];
	$db->free_result($request);

	foreach ($updates as $member_groups => $boardArray)
		$db->query('', '
			UPDATE {db_prefix}boards
			SET member_groups = {string:member_groups}
			WHERE id_board IN ({array_int:board_lists})',
			array(
				'board_lists' => $boardArray,
				'member_groups' => implode(',', array_diff(explode(',', $member_groups), $groups)),
			)
		);

	// Recalculate the post groups, as they likely changed.
	updateStats('postgroups');

	// Make a note of the fact that the cache may be wrong.
	$settings_update = array('settings_updated' => time());
	// Have we deleted the spider group?
	if (isset($modSettings['spider_group']) && in_array($modSettings['spider_group'], $groups))
		$settings_update['spider_group'] = 0;

	updateSettings($settings_update);

	// It was a success.
	return true;
}

/**
 * Remove one or more members from one or more membergroups.
 * Requires the manage_membergroups permission.
 * Function includes a protection against removing from implicit groups.
 * Non-admins are not able to remove members from the admin group.
 *
 * @param array $members
 * @param array $groups = null if groups is null, the specified members are stripped from all their membergroups.
 * @param bool $permissionCheckDone = false
 *
 * @return boolean
 */
function removeMembersFromGroups($members, $groups = null, $permissionCheckDone = false)
{
	global $modSettings;

	$db = database();

	// You're getting nowhere without this permission, unless of course you are the group's moderator.
	if (!$permissionCheckDone)
		isAllowedTo('manage_membergroups');

	// Assume something will happen.
	updateSettings(array('settings_updated' => time()));

	// Cleaning the input.
	if (!is_array($members))
		$members = array((int) $members);
	else
	{
		$members = array_unique($members);

		// Cast the members to integer.
		foreach ($members as $key => $value)
			$members[$key] = (int) $value;
	}

	// Before we get started, let's check we won't leave the admin group empty!
	if ($groups === null || $groups == 1 || (is_array($groups) && in_array(1, $groups)))
	{
		$admins = array();
		listMembergroupMembers_Href($admins, 1);

		// Remove any admins if there are too many.
		$non_changing_admins = array_diff(array_keys($admins), $members);

		if (empty($non_changing_admins))
			$members = array_diff($members, array_keys($admins));
	}

	// Just in case.
	if (empty($members))
		return false;

	// Wanna remove all groups from these members? That's easy.
	if ($groups === null)
	{
		$db->query('', '
			UPDATE {db_prefix}members
			SET
				id_group = {int:regular_member},
				additional_groups = {string:blank_string}
			WHERE id_member IN ({array_int:member_list})' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND FIND_IN_SET({int:admin_group}, additional_groups) = 0'),
			array(
				'member_list' => $members,
				'regular_member' => 0,
				'admin_group' => 1,
				'blank_string' => '',
			)
		);

		updateStats('postgroups', $members);

		// Log what just happened.
		foreach ($members as $member)
			logAction('removed_all_groups', array('member' => $member), 'admin');

		return true;
	}
	elseif (!is_array($groups))
		$groups = array((int) $groups);
	else
	{
		$groups = array_unique($groups);

		// Make sure all groups are integer.
		foreach ($groups as $key => $value)
			$groups[$key] = (int) $value;
	}

	// Fetch a list of groups members cannot be assigned to explicitely, and the group names of the ones we want.
	$implicitGroups = array(-1, 0, 3);
	$group_names = array();
	$group_details = membergroupsById($groups, 0, true);
	foreach ($group_details as $key => $row)
	{
		if ($row['min_posts'] != -1)
			$implicitGroups[] = $row['id_group'];
		else
			$group_names[$row['id_group']] = $row['group_name'];
	}

	// Now get rid of those groups.
	$groups = array_diff($groups, $implicitGroups);

	// Don't forget the protected groups.
	if (!allowedTo('admin_forum'))
	{
		$request = $db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		$protected_groups = array(1);
		while ($row = $db->fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		$db->free_result($request);

		// If you're not an admin yourself, you can't touch protected groups!
		$groups = array_diff($groups, array_unique($protected_groups));
	}

	// Only continue if there are still groups and members left.
	if (empty($groups) || empty($members))
		return false;

	// First, reset those who have this as their primary group - this is the easy one.
	$log_inserts = array();
	$request = $db->query('', '
		SELECT id_member, id_group
		FROM {db_prefix}members AS members
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		array(
			'group_list' => $groups,
			'member_list' => $members,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$log_inserts[] = array('group' => $group_names[$row['id_group']], 'member' => $row['id_member']);
	$db->free_result($request);

	$db->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group IN ({array_int:group_list})
			AND id_member IN ({array_int:member_list})',
		array(
			'group_list' => $groups,
			'member_list' => $members,
			'regular_member' => 0,
		)
	);

	// Those who have it as part of their additional group must be updated the long way... sadly.
	$request = $db->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE (FIND_IN_SET({raw:additional_groups_implode}, additional_groups) != 0)
			AND id_member IN ({array_int:member_list})
		LIMIT ' . count($members),
		array(
			'member_list' => $members,
			'additional_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$updates = array();
	while ($row = $db->fetch_assoc($request))
	{
		// What log entries must we make for this one, eh?
		foreach (explode(',', $row['additional_groups']) as $group)
			if (in_array($group, $groups))
				$log_inserts[] = array('group' => $group_names[$group], 'member' => $row['id_member']);

		$updates[$row['additional_groups']][] = $row['id_member'];
	}
	$db->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		$db->query('', '
			UPDATE {db_prefix}members
			SET additional_groups = {string:additional_groups}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $memberArray,
				'additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups)),
			)
		);

	// Their post groups may have changed now...
	updateStats('postgroups', $members);

	// Do the log.
	if (!empty($log_inserts) && !empty($modSettings['modlog_enabled']))
		foreach ($log_inserts as $extra)
			logAction('removed_from_group', $extra, 'admin');

	// Mission successful.
	return true;
}

/**
 * Add one or more members to a membergroup.
 *
 * Requires the manage_membergroups permission.
 * Function has protection against adding members to implicit groups.
 * Non-admins cannot add members to the admin group, or protected groups.
 *
 * @param string|array $members
 * @param int $group
 * @param string $type = 'auto' specifies whether the group is added as primary or as additional group.
 * Supported types:
 * 	- only_primary      - Assigns a membergroup as primary membergroup, but only
 * 						  if a member has not yet a primary membergroup assigned,
 * 						  unless the member is already part of the membergroup.
 * 	- only_additional   - Assigns a membergroup to the additional membergroups,
 * 						  unless the member is already part of the membergroup.
 * 	- force_primary     - Assigns a membergroup as primary membergroup no matter
 * 						  what the previous primary membergroup was.
 * 	- auto              - Assigns a membergroup to the primary group if it's still
 * 						  available. If not, assign it to the additional group.
 * @param bool $permissionCheckDone = false if true, it checks permission of the current user to add groups ('manage_membergroups')
 *
 * @return boolean success or failure
 */
function addMembersToGroup($members, $group, $type = 'auto', $permissionCheckDone = false)
{
	global $modSettings;

	$db = database();

	// Show your licence, but only if it hasn't been done yet.
	if (!$permissionCheckDone)
		isAllowedTo('manage_membergroups');

	// Make sure we don't keep old stuff cached.
	updateSettings(array('settings_updated' => time()));

	if (!is_array($members))
		$members = array((int) $members);
	else
	{
		$members = array_unique($members);

		// Make sure all members are integer.
		foreach ($members as $key => $value)
			$members[$key] = (int) $value;
	}
	$group = (int) $group;

	// Some groups just don't like explicitly having members.
	$implicitGroups = array(-1, 0, 3);
	$group_names = array();
	$group_details = membergroupsById($group, 1, true);
	if ($group_details['min_posts'] != -1)
		$implicitGroups[] = $group_details['id_group'];
	else
		$group_names[$group_details['id_group']] = $group_details['group_name'];

	// Sorry, you can't join an implicit group.
	if (in_array($group, $implicitGroups) || empty($members))
		return false;

	// Only admins can add admins...
	if (!allowedTo('admin_forum') && $group == 1)
		return false;
	// ... and assign protected groups!
	elseif (!allowedTo('admin_forum'))
	{
		$is_protected = membergroupsById($group, 1, false, false, true);

		// Is it protected?
		if ($is_protected['group_type'] == 1)
			return false;
	}

	// Do the actual updates.
	if ($type == 'only_additional')
		$db->query('', '
			UPDATE {db_prefix}members
			SET additional_groups = CASE WHEN additional_groups = {string:blank_string} THEN {string:id_group_string} ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'id_group' => $group,
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
				'blank_string' => '',
			)
		);
	elseif ($type == 'only_primary' || $type == 'force_primary')
		$db->query('', '
			UPDATE {db_prefix}members
			SET id_group = {int:id_group}
			WHERE id_member IN ({array_int:member_list})' . ($type == 'force_primary' ? '' : '
				AND id_group = {int:regular_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0'),
			array(
				'member_list' => $members,
				'id_group' => $group,
				'regular_group' => 0,
			)
		);
	elseif ($type == 'auto')
		$db->query('', '
			UPDATE {db_prefix}members
			SET
				id_group = CASE WHEN id_group = {int:regular_group} THEN {int:id_group} ELSE id_group END,
				additional_groups = CASE WHEN id_group = {int:id_group} THEN additional_groups
					WHEN additional_groups = {string:blank_string} THEN {string:id_group_string}
					ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'regular_group' => 0,
				'id_group' => $group,
				'blank_string' => '',
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
			)
		);
	// Ack!!?  What happened?
	else
		trigger_error('addMembersToGroup(): Unknown type \'' . $type . '\'', E_USER_WARNING);

	call_integration_hook('integrate_add_members_to_group', array($members, $group_details, &$group_names));

	// Update their postgroup statistics.
	updateStats('postgroups', $members);

	require_once(SOURCEDIR . '/Logging.php');
	foreach ($members as $member)
		logAction('added_to_group', array('group' => $group_names[$group], 'member' => $member), 'admin');

	return true;
}

/**
 * Gets the members of a supplied membergroup.
 * Returns them as a link for display.
 *
 * @param array &$members
 * @param int $membergroup
 * @param int $limit = null
 *
 * @return boolean
 */
function listMembergroupMembers_Href(&$members, $membergroup, $limit = null)
{
	global $scripturl;

	$db = database();

	$request = $db->query('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_group = {int:id_group} OR FIND_IN_SET({int:id_group}, additional_groups) != 0' . ($limit === null ? '' : '
		LIMIT ' . ($limit + 1)),
		array(
			'id_group' => $membergroup,
		)
	);
	$members = array();
	while ($row = $db->fetch_assoc($request))
		$members[$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
	$db->free_result($request);

	// If there are more than $limit members, add a 'more' link.
	if ($limit !== null && count($members) > $limit)
	{
		array_pop($members);
		return true;
	}
	else
		return false;
}

/**
 * Retrieve a list of (visible) membergroups used by the cache.
 *
 * @global type $scripturl
 *
 * @return type
 */
function cache_getMembergroupList()
{
	global $scripturl;

	$db = database();

	$request = $db->query('', '
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups
		WHERE min_posts = {int:min_posts}
			AND hidden = {int:not_hidden}
			AND id_group != {int:mod_group}
			AND online_color != {string:blank_string}
		ORDER BY group_name',
		array(
			'min_posts' => -1,
			'not_hidden' => 0,
			'mod_group' => 3,
			'blank_string' => '',
		)
	);
	$groupCache = array();
	while ($row = $db->fetch_assoc($request))
		$groupCache[] = '<a href="' . $scripturl . '?action=groups;sa=members;group=' . $row['id_group'] . '" ' . ($row['online_color'] ? 'style="color: ' . $row['online_color'] . '"' : '') . '>' . $row['group_name'] . '</a>';
	$db->free_result($request);

	return array(
		'data' => $groupCache,
		'expires' => time() + 3600,
		'refresh_eval' => 'return $GLOBALS[\'modSettings\'][\'settings_updated\'] > ' . time() . ';',
	);
}

/**
 * Helper function to generate a list of membergroups for display.
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $membergroup_type
 * @param int $user_id
 * @param bool $include_hidden
 * @param bool $include_all
 */
function list_getMembergroups($start, $items_per_page, $sort, $membergroup_type, $user_id, $include_hidden, $include_all = false)
{
	global $scripturl;

	$db = database();

	$groups = array();

	$request = $db->query('', '
		SELECT mg.id_group, mg.group_name, mg.min_posts, mg.description, mg.group_type, mg.online_color, mg.hidden,
			mg.icons, IFNULL(gm.id_member, 0) AS can_moderate, 0 AS num_members
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts {raw:min_posts}' . ($include_all ? '' : '
			AND mg.id_group != {int:mod_group}
			AND mg.group_type != {int:is_protected}') . '
		ORDER BY {raw:sort}',
		array(
			'current_member' => $user_id,
			'min_posts' => ($membergroup_type === 'post_count' ? '!= ' : '= ') . -1,
			'mod_group' => 3,
			'is_protected' => 1,
			'sort' => $sort,
		)
	);

	// Start collecting the data.
	$groups = array();
	$group_ids = array();
	while ($row = $db->fetch_assoc($request))
	{
		// We only list the groups they can see.
		if ($row['hidden'] && !$row['can_moderate'] && !$include_hidden)
			continue;

		$row['icons'] = explode('#', $row['icons']);

		$groups[$row['id_group']] = array(
			'id_group' => $row['id_group'],
			'group_name' => $row['group_name'],
			'min_posts' => $row['min_posts'],
			'desc' => $row['description'],
			'online_color' => $row['online_color'],
			'type' => $row['group_type'],
			'num_members' => $row['num_members'],
			'moderators' => array(),
			'icons' => $row['icons'],
		);

		$include_hidden |= $row['can_moderate'];
		$group_ids[] = $row['id_group'];
	}
	$db->free_result($request);

	// If we found any membergroups, get the amount of members in them.
	if (!empty($group_ids))
	{
		if ($membergroup_type === 'post_count')
			$groups_count = membersInGroups($group_ids);
		else
			$groups_count = membersInGroups(array(), $group_ids, $include_hidden);

		// @todo not sure why += wouldn't = be enough?
		foreach ($groups_count as $group_id => $num_members)
			$groups[$group_id]['num_members'] += $num_members;

		$query = $db->query('', '
			SELECT mods.id_group, mods.id_member, mem.member_name, mem.real_name
			FROM {db_prefix}group_moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE mods.id_group IN ({array_int:group_list})',
			array(
				'group_list' => $group_ids,
			)
		);
		while ($row = $db->fetch_assoc($query))
			$groups[$row['id_group']]['moderators'][] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		$db->free_result($query);
	}

	// Apply manual sorting if the 'number of members' column is selected.
	if (substr($sort, 0, 1) == '1' || strpos($sort, ', 1') !== false)
	{
		$sort_ascending = strpos($sort, 'DESC') === false;

		foreach ($groups as $group)
			$sort_array[] = $group['id_group'] != 3 ? (int) $group['num_members'] : -1;

		array_multisort($sort_array, $sort_ascending ? SORT_ASC : SORT_DESC, SORT_REGULAR, $groups);
	}

	return $groups;
}

/**
 * Count the number of members in specific groups
 *
 * @param array $postGroups an array of post-based groups id.
 * @param array $normalGroups = array() an array of normal groups id.
 * @param bool $include_hidden = false if true, it includes hidden groups in the count (default false).
 * @param bool $include_moderators = false if true, it includes board moderators too (default false).
 *
 * @return array
 */
function membersInGroups($postGroups, $normalGroups = array(), $include_hidden = false, $include_moderators = false)
{
	$db = database();

	$groups = array();

	// If we have post groups, let's count the number of members...
	if (!empty($postGroups))
	{
		$query = $db->query('', '
			SELECT id_post_group AS id_group, COUNT(*) AS member_count
			FROM {db_prefix}members
			WHERE id_post_group IN ({array_int:post_group_list})
			GROUP BY id_post_group',
			array(
				'post_group_list' => $postGroups,
			)
		);
		while ($row = $db->fetch_assoc($query))
			$groups[$row['id_group']] = $row['member_count'];
		$db->free_result($query);
	}

	if (!empty($normalGroups))
	{
		// Find people who are members of this group...
		$query = $db->query('', '
			SELECT id_group, COUNT(*) AS member_count
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			array(
				'normal_group_list' => $normalGroups,
			)
		);
		while ($row = $db->fetch_assoc($query))
			$groups[$row['id_group']] = $row['member_count'];
		$db->free_result($query);

		// Only do additional groups if we can moderate...
		if ($include_hidden)
		{
			// Also do those who have it as an additional membergroup - this ones more yucky...
			$query = $db->query('', '
				SELECT mg.id_group, COUNT(*) AS member_count
				FROM {db_prefix}membergroups AS mg
					INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
						AND mem.id_group != mg.id_group
						AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
				WHERE mg.id_group IN ({array_int:normal_group_list})
				GROUP BY mg.id_group',
				array(
					'normal_group_list' => $normalGroups,
					'blank_string' => '',
				)
			);
			while ($row = $db->fetch_assoc($query))
			{
				if (isset($groups[$row['id_group']]))
					$groups[$row['id_group']] += $row['member_count'];
				else
					$groups[$row['id_group']] = $row['member_count'];
			}
			$db->free_result($query);
		}
	}

	if ($include_moderators)
	{
		// Any moderators?
		$request = $db->query('', '
			SELECT COUNT(DISTINCT id_member) AS num_distinct_mods
			FROM {db_prefix}moderators
			LIMIT 1',
			array(
			)
		);
		list ($groups[3]) = $db->fetch_row($request);
		$db->free_result($request);
	}

	return $groups;
}

/**
 * Returns details of membergroups based on the id
 *
 * @param array/int $group_ids the IDs of the groups.
 * @param integer $limit = 1 the number of results returned (default 1, if null/false/0 returns all).
 * @param bool $detailed = false if true then it returns more fields (default false).
 *  false returns: id_group, group_name, group_type.
 *  true adds to above: description, min_posts, online_color, max_messages, icons, hidden, id_parent.
 * @param bool $assignable = false determine if the group is assignable or not and return that information.
 * @param bool $protected = false if true, it includes protected groups in the result.
 *
 * @return array|false
 */
function membergroupsById($group_ids, $limit = 1, $detailed = false, $assignable = false, $protected = false)
{
	$db = database();

	if (empty($group_ids))
		return false;

	if (!is_array($group_ids))
		$group_ids = array($group_ids);

	$groups = array();
	$group_ids = array_map('intval', $group_ids);

	$request = $db->query('', '
		SELECT id_group, group_name, group_type' . (!$detailed ? '' : ',
			description, min_posts, online_color, max_messages, icons, hidden, id_parent') . (!$assignable ? '' : ',
			CASE WHEN min_posts = {int:min_posts} THEN 1 ELSE 0 END AS assignable,
			CASE WHEN min_posts != {int:min_posts} THEN 1 ELSE 0 END AS is_post_group') . '
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_ids})' . ($protected ? '' : '
			AND group_type != {int:is_protected}') . (empty($limit) ? '' : '
		LIMIT {int:limit}'),
		array(
			'min_posts' => -1,
			'group_ids' => $group_ids,
			'limit' => $limit,
			'is_protected' => 1,
		)
	);

	if ($db->num_rows($request) == 0)
		return $groups;

	while ($row = $db->fetch_assoc($request))
		$groups[$row['id_group']] = $row;
	$db->free_result($request);

	if (is_array($group_id))
		return $groups;
	else
		return $groups[$group_id];
}

/**
 * Gets basic membergroup data
 *
 * the $includes and $excludes array is used for granular filtering the output. We need to exclude
 * groups sometimes because they are special ones.
 * Example: getBasicMembergroupData(array('admin', 'mod', 'globalmod'));
 * $includes parameters:
 * - 'admin' includes the admin: id_group = 1
 * - 'mod' includes the local moderator: id_group = 3
 * - 'globalmod' includes the global moderators: id_group = 2
 * - 'member' includes the ungrouped users from id_group = 0
 * - 'postgroups' includes the post based membergroups
 * - 'protected' includes protected groups
 * - 'all' lists all groups
 * $excludes parameters:
 * - 'newbie' excludes the newbie group id_group 4
 * - 'custom' lists only the system based groups (id 0, 1, 2, 3)
 * - 'membergroups' excludes permission groups, lists the post based membergroups
 * - 'hidden' excludes hidden groups
 *
 * @param array $includes
 * @param array $excludes
 * @param string $sort_order
 * @param bool $split, splits postgroups and membergroups
 *
 * @return array
 */
function getBasicMembergroupData($includes = array(), $excludes = array(), $sort_order = null, $split = null)
{
	global $txt, $modSettings;

	$db = database();

	//No $includes parameters given? Let's set some default values
	if(empty($includes))
		$includes = array('globalmod', 'member', 'postgroups');

	$groups = array();

	$where = '';
	$sort_order = isset($sort_order) ? $sort_order : 'min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name';

	// Do we need the post based membergroups?
	$where .= !empty($modSettings['permission_enable_postgroups']) || in_array('postgroups', $includes) ? '' : 'AND min_posts = {int:min_posts}';
	// Include protected groups?
	$where .= allowedTo('admin_forum') || in_array('protected', $includes) ? '' : ' AND group_type != {int:is_protected}';
	// Include the global moderators?
	$where .= in_array('globalmod', $includes) ? '' : ' AND id_group != {int:global_mod_group}';
	// Include the admins?
	$where .= in_array('admin', $includes) ? '' : ' AND id_group != {int:admin_group}';
	// Local Moderators?
	$where .= in_array('mod', $includes) ? '' : ' AND id_group != {int:moderator_group}';
	// Ignore the first post based group?
	$where .= in_array('newbie', $excludes) ? '' : ' AND id_group != {int:newbie_group}';
	// Exclude custom groups?
	$where .= !in_array('custom', $excludes) ? '' : ' AND id_group < {int:newbie_group}';
	// Exclude hidden?
	$where .= !in_array('hidden', $excludes) ? '' : ' AND hidden != {int:hidden_group}';
	// Only the post based membergroups? We can safely overwrite the $where.
	if (in_array('membergroups', $excludes))
		$where = ' AND min_posts != {int:min_posts}';
	// Simply all of them?
	if (in_array('all', $includes))
			$where = '';

	$request = $db->query('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE 1 = 1
			' . $where . '
		ORDER BY ' . $sort_order,
		array(
			'admin_group' => 1,
			'moderator_group' => 3,
			'global_mod_group' => 2,
			'min_posts' => -1,
			'is_protected' => 1,
			'newbie_group' => 4,
			'hidden_group' => 2,
		)
	);

	// Include the default membergroup? the ones with id_member = 0
	if(in_array('member', $includes) && !isset($split))
		$groups[] = array(
			'id' => 0,
			'name' => $txt['membergroups_members']
		);

	if (isset($split))
	{
		if (empty($modSettings['permission_enable_postgroups']))
		{
			$groups['groups'][0] = array(
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'can_be_additional' => false,
				'member_count' => 0,
			);
			$groups['membergroups'][0] = array(
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'can_be_additional' => false,
				'member_count' => 0,
			);
		}
		while ($row = $db->fetch_assoc($request))
		{
			$groups['groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'member_count' => 0,
			);

			if ($row['min_posts'] == -1)
				$groups['membergroups'][] =  array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'can_be_additional' => true,
				);
			else
				$groups['postgroups'][] =  array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
				);
		}
	}

	else
		while ($row = $db->fetch_assoc($request))
		{
			$groups[] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name']
			);
		}
	$db->free_result($request);

	return $groups;
}

/**
 * Retrieve groups and their number of members.
 *
 * @param array $groupList
 *
 * @return array with ('id', 'name', 'member_count')
 */
function getGroups($groupList)
{
	$db = database();

	$groups = array();
	if (in_array(0, $groups))
	{
		$groups[0] = array(
			'id' => 0,
			'name' => $txt['announce_regular_members'],
			'member_count' => 'n/a',
		);
	}

	// Get all membergroups that have access to the board the announcement was made on.
	$request = $db->query('', '
		SELECT mg.id_group, COUNT(mem.id_member) AS num_members
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_group = mg.id_group OR FIND_IN_SET(mg.id_group, mem.additional_groups) != 0 OR mg.id_group = mem.id_post_group)
		WHERE mg.id_group IN ({array_int:group_list})
		GROUP BY mg.id_group',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$groups[$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => '',
			'member_count' => $row['num_members'],
		);
	}
	$db->free_result($request);

	return $groups;
}

/**
 * Gets the last assigned group id.
 *
 * @return int $id_group
 */
function getMaxGroupID()
{
	$db = database();

    $request = $db->query('', '
		SELECT MAX(id_group)
		FROM {db_prefix}membergroups',
		array(
		)
	);
	list ($id_group) = $db->fetch_row($request);

	return $id_group;
}

/**
 * Adds a new group to the membergroups table.
 *
 * @param int $id_group
 * @param string $groupname
 * @param int $minposts
 * @param string $type
 */
function addMembergroup($id_group, $groupname, $minposts, $type)
{
	$db = database();

	$db->insert('',
		'{db_prefix}membergroups',
		array(
			'id_group' => 'int', 'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
			'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int',
		),
		array(
			$id_group, '', Util::htmlspecialchars($groupname, ENT_QUOTES), $minposts,
			'1#icon.png', '', $type,
		),
		array('id_group')
	);
}

/**
 * Copies permissions from a given membergroup.
 *
 * @param int $id_group
 * @param int $copy_id
 * @param array $illegal_permissions
 */
function copyPermissions($id_group, $copy_from, $illegal_permissions)
{
	$db = database();

	$inserts = array();

	$request = $db->query('', '
		SELECT permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group = {int:copy_from}',
		array(
			'copy_from' => $copy_from,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		if (empty($illegal_permissions) || !in_array($row['permission'], $illegal_permissions))
			$inserts[] = array($id_group, $row['permission'], $row['add_deny']);
	}
	$db->free_result($request);

	if (!empty($inserts))
		$db->insert('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'permission')
		);
}

/**
 * Copies the board permissions from a given membergroup.
 *
 * @param int $id_group
 * @param int $copy_id
 * @param array $illegal_permissions
 */
function copyBoardPermissions($id_group, $copy_from)
{
	$db = database();

	$inserts = array();

	$request = $db->query('', '
		SELECT id_profile, permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:copy_from}',
		array(
			'copy_from' => $copy_from,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$inserts[] = array($id_group, $row['id_profile'], $row['permission'], $row['add_deny']);
	$db->free_result($request);

	if (!empty($inserts))
		$db->insert('insert',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'id_profile', 'permission')
		);
}

/**
 * Updates the properties of a copied membergroup.
 *
 * @param int $id_group
 * @param int $copy_from
 */
function updateCopiedGroup($id_group, $copy_from)
{
	$db = database();

	require_once(SUBSDIR . '/Membergroups.subs.php');
	$group_info = membergroupsById($copy_from, 1, true);

	// update the new membergroup
	$db->query('', '
		UPDATE {db_prefix}membergroups
		SET
			online_color = {string:online_color},
			max_messages = {int:max_messages},
			icons = {string:icons}
			WHERE id_group = {int:current_group}',
			array(
				'max_messages' => $group_info['max_messages'],
				'current_group' => $id_group,
				'online_color' => $group_info['online_color'],
				'icons' => $group_info['icons'],
			)
	);
}

/**
 * Updates the properties of a inherited membergroup.
 *
 * @param int $id_group
 * @param int $copy_from
 */
function updateInheritedGroup($id_group, $copy_id)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}membergroups
		SET id_parent = {int:copy_from}
		WHERE id_group = {int:current_group}',
		array(
			'copy_from' => $copy_id,
			'current_group' => $id_group,
		)
	);
}

/**
 * This function updates the membergroup with the given information.
 * It's passed an associative array $properties, with 'current_group' holding
 * the group to update. The rest of the keys are details to update it with.
 *
 * @param array $properties
 */
function updateMembergroupProperties($properties)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}membergroups
		SET group_name = {string:group_name}, online_color = {string:online_color},
			max_messages = {int:max_messages}, min_posts = {int:min_posts}, icons = {string:icons},
			description = {string:group_desc}, group_type = {int:group_type}, hidden = {int:group_hidden},
			id_parent = {int:group_inherit}
		WHERE id_group = {int:current_group}',
		array(
			'max_messages' => $properties['max_messages'],
			'min_posts' => $properties['min_posts'],
			'group_type' => $properties['group_type'],
			'group_hidden' => $properties['group_hidden'],
			'group_inherit' => $properties['group_inherit'],
			'current_group' => $properties['current_group'],
			'group_name' => Util::htmlspecialchars($properties['group_name']),
			'online_color' => $properties['online_color'],
			'icons' => $properties['icons'],
			'group_desc' => $properties['group_desc'],
		)
	);
}

/**
 * Detaches a membergroup from the boards listed in $boards.
 *
 * @param int $id_group
 * @param array $boards
 * @param array $access_list
 */
function detachGroupFromBoards($id_group, $boards, $access_list)
{
	$db = database();

	// Find all boards in whose access list this group is in, but shouldn't be.
	$request = $db->query('', '
		SELECT id_board, {raw:column}
		FROM {db_prefix}boards
		WHERE FIND_IN_SET({string:current_group}, {raw:column}) != 0' . (empty($boards[$access_list]) ? '' : '
			AND id_board NOT IN ({array_int:board_access_list})'),
		array(
			'current_group' => $id_group,
			'board_access_list' => $boards[$access_list],
			'column' => $access_list == 'allow' ? 'member_groups' : 'deny_member_groups',
		)
	);
	while ($row = $db->fetch_assoc($request))
		$db->query('', '
			UPDATE {db_prefix}boards
			SET {raw:column} = {string:member_group_access}
			WHERE id_board = {int:current_board}',
			array(
				'current_board' => $row['id_board'],
				'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($id_group))),
				'column' =>$access_list == 'allow' ? 'member_groups' : 'deny_member_groups',
				)
		);
	$db->free_result($request);
}

/**
 * Assigns the given group $id_group to the boards specified, for
 * the 'allow' or 'deny' list.
 *
 * @param int $id_group
 * @param array $boards
 * @param string $access_list ('allow', 'deny')
 */
function assignGroupToBoards($id_group, $boards, $access_list)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}boards
		SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
		WHERE id_board IN ({array_int:board_list})
			AND FIND_IN_SET({int:current_group}, {raw:column}) = 0',
		array(
			'board_list' => $boards[$access_list],
			'blank_string' => '',
			'current_group' => $id_group,
			'group_id_string' => (string) $id_group,
			'comma_group' => ',' . $id_group,
			'column' => $access_list == 'allow' ? 'member_groups' : 'deny_member_groups',
		)
	);
}

/**
 * Membergroup was deleted? We need to detach that group from our members, too...
 *
 * @param int $id_group
 */
function detachDeletedGroupFromMembers($id_group)
{
	$db = database();

	$updates = array();

	$db->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group = {int:current_group}',
		array(
			'regular_member' => 0,
			'current_group' => $id_group,
		)
	);

	$request = $db->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({string:current_group}, additional_groups) != 0',
		array(
			'current_group' => $id_group,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	$db->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), array($id_group)))));

}

/**
 * Make the given group hidden. Hidden groups are stored in the additional_groups.
 *
 * @param int $id_group
 */
function setGroupToHidden($id_group)
{
	$db = database();

	$updates = array();

	$request = $db->query('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE id_group = {int:current_group}
			AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
		array(
			'current_group' => $id_group,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	$db->free_result($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_merge(explode(',', $additional_groups), array($id_group)))));

	$db->query('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group = {int:current_group}',
		array(
			'regular_member' => 0,
			'current_group' => $id_group,
		)
	);
}

/**
 * Make sure the setting to display membergroup key on the board index is valid.
 * It updates the setting if necessary.
 */
function validateShowGroupMembership()
{
	global $modSettings;

	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}membergroups
		WHERE group_type > {int:non_joinable}',
		array(
			'non_joinable' => 1,
		)
	);
	list ($have_joinable) = $db->fetch_row($request);
	$db->free_result($request);

	// Do we need to update the setting?
	if ((empty($modSettings['show_group_membership']) && $have_joinable) || (!empty($modSettings['show_group_membership']) && !$have_joinable))
		updateSettings(array('show_group_membership' => $have_joinable ? 1 : 0));
}

/**
 * Detaches group moderators from a deleted group.
 *
 * @param int $id_group
 */
function detachGroupModerators($id_group)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $id_group,
		)
	);
}

/**
 * Get the id_member from the membergroup moderators.
 *
 * @param array $moderators
 * @return array
 */
function getIDMemberFromGroupModerators($moderators)
{
	$db = database();

	$group_moderators = array();

	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE member_name IN ({array_string:moderators}) OR real_name IN ({array_string:moderators})
		LIMIT ' . count($moderators),
		array(
			'moderators' => $moderators,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$group_moderators[] = $row['id_member'];
	$db->free_result($request);

	return $group_moderators;

}

/**
 * Assign members to the membergroup moderators.
 *
 * @param int $id_group
 * @param array $group_moderators
 */
function assignGroupModerators($id_group, $group_moderators)
{
	$db = database();

	$mod_insert = array();
		foreach ($group_moderators as $moderator)
			$mod_insert[] = array($id_group, $moderator);

		$db->insert('insert',
			'{db_prefix}group_moderators',
			array('id_group' => 'int', 'id_member' => 'int'),
			$mod_insert,
			array('id_group', 'id_member')
		);
}

/**
 * List moderators from a given membergroup.
 *
 * @param int $id_group
 *
 * @return array moderators as array (id => name)
 */
function getGroupModerators($id_group)
{
	$db = database();

	$moderators = array();

	$request = $db->query('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}group_moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_group = {int:current_group}',
		array(
			'current_group' => $id_group,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$moderators[$row['id_member']] = $row['real_name'];
	$db->free_result($request);

	return $moderators;
}

/**
 * Lists all groups which inherit permission profiles from the given group.
 *
 * @param int $id_group
 *
 * @return array
 */
function getInheritableGroups($id_group)
{
	global $modSettings;

	$db = database();

	$inheritable_groups = array();

	$request = $db->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group != {int:current_group}' .
			(empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
			AND id_group NOT IN (1, 3)
			AND id_parent = {int:not_inherited}',
		array(
			'current_group' => $id_group,
			'min_posts' => -1,
			'not_inherited' => -2,
			'is_protected' => 1,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$inheritable_groups[$row['id_group']] = $row['group_name'];
	$db->free_result($request);

	return $inheritable_groups;
}

/**
 * Gets a list of membergroups, parent groups first.
 *
 * @todo: Merge with getBasicMembergroupData();
 * @return groups
 */
function getExtendedMembergroupData()
{
	global $modSettings;

	$db = database();

	$groups = array();

	// Query the database defined membergroups.
	$query = $db->query('', '
		SELECT id_group, id_parent, group_name, min_posts, online_color, icons
		FROM {db_prefix}membergroups' . (empty($modSettings['permission_enable_postgroups']) ? '
		WHERE min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent = {int:not_inherited} DESC, min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'min_posts' => -1,
			'not_inherited' => -2,
			'newbie_group' => 4,
		)
	);

	while ($row = $db->fetch_assoc($query))
	{
		$groups[$row['id_group']] = array(
			'id_group' => $row['id_group'],
			'id_parent' => $row['id_parent'],
			'group_name' => $row['group_name'],
			'min_posts' => $row['min_posts'],
			'online_color' => $row['online_color'],
			'icons' => $row['icons'],
		);
	}

	return $groups;
}

/**
 * List all membergroups and prepares them to assign permissions to..
 *
 * @return array
 */
function prepareMembergroupPermissions()
{
	global $modSettings;

	$db = database();

	$profile_groups = array();

	$request = $db->query('', '
		SELECT id_group, group_name, online_color, id_parent
		FROM {db_prefix}membergroups
		WHERE id_group != {int:admin_group}
			' . (empty($modSettings['permission_enable_postgroups']) ? ' AND min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent ASC',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		if ($row['id_parent'] == -2)
		{
			$profile_groups[$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'color' => $row['online_color'],
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'attachment' => 'disallow',
				'children' => array(),
			);
		}
		elseif (isset($profile_groups[$row['id_parent']]))
			$profile_groups[$row['id_parent']]['children'][] = $row['group_name'];
	}
	$db->free_result($request);

	return $profile_groups;
}