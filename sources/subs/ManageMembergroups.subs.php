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
 * Gets the last assigned group id.
 *
 * @return int $id_group
 */
function getMaxGroupID()
{
	global $smcFunc;

    $request = $smcFunc['db_query']('', '
		SELECT MAX(id_group)
		FROM {db_prefix}membergroups',
		array(
		)
	);
	list ($id_group) = $smcFunc['db_fetch_row']($request);

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
	global $smcFunc;

	$smcFunc['db_insert']('',
		'{db_prefix}membergroups',
		array(
			'id_group' => 'int', 'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
			'icons' => 'string', 'online_color' => 'string', 'group_type' => 'int',
		),
		array(
			$id_group, '', $smcFunc['htmlspecialchars']($groupname, ENT_QUOTES), $minposts,
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
	global $smcFunc;

	$inserts = array();

	$request = $smcFunc['db_query']('', '
		SELECT permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group = {int:copy_from}',
		array(
			'copy_from' => $copy_from,
		)
	);
			
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($illegal_permissions) || !in_array($row['permission'], $illegal_permissions))
			$inserts[] = array($id_group, $row['permission'], $row['add_deny']);
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
		$smcFunc['db_insert']('insert',
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
	global $smcFunc;

	$inserts = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_profile, permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:copy_from}',
		array(
			'copy_from' => $copy_from,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$inserts[] = array($id_group, $row['id_profile'], $row['permission'], $row['add_deny']);
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
		$smcFunc['db_insert']('insert',
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
	global $smcFunc;

	require_once(SUBSDIR . '/Membergroups.subs.php');
	$group_info = membergroupsById($copy_from, 1, true);

	// update the new membergroup
	$smcFunc['db_query']('', '
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
	global $smcFunc;

	$smcFunc['db_query']('', '
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
 * Assigns a group to a list of boards.
 *
 * @param array $changed_boards
 * @param int $id_group
 * @param array $board_action
 */
function assignGroupsToBoards($changed_boards, $id_group, $board_action)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET {raw:column} = CASE WHEN {raw:column} = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT({raw:column}, {string:comma_group}) END
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $changed_boards[$board_action],
			'blank_string' => '',
			'group_id_string' => (string) $id_group,
			'comma_group' => ',' . $id_group,
			'column' => $board_action == 'allow' ? 'member_groups' : 'deny_member_groups',
		)
	);
}

/**
 * Get a list of our selfmade groups. Excludes the system groups such as admin, global mod, moderator...
 * @todo: merge with getMembergroups() from ManageMaintenance.subs.php
 * @return array
 */
function getCustomGroups()
{
	global $smcFunc, $modSettings;

	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE (id_group > {int:moderator_group} OR id_group = {int:global_mod_group})' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY min_posts, id_group != {int:global_mod_group}, group_name',
		array(
			'moderator_group' => 3,
			'global_mod_group' => 2,
			'min_posts' => -1,
			'is_protected' => 1,
		)
	);
	$groups = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$groups[] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	$smcFunc['db_free_result']($result);

	return $groups;
}

/**
 * Updates the membergroup with the given information.
 * @param array $properties
 */
function updateMembergroupProperties($properties)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
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
			'group_name' => $smcFunc['htmlspecialchars']($properties['group_name']),
			'online_color' => $properties['online_color'],
			'icons' => $properties['icons'],
			'group_desc' => $properties['group_desc'],
		)
	);
}

/**
 * Detaches a membergroup from the boards listed in $boards.
 * @param int $id_group
 * @param array $boards
 * @param array $access_list
 */
function detachGroupFromBoards($id_group, $boards, $access_list)
{
	global $smcFunc;

	// Find all board this group is in, but shouldn't be in.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET {raw:column} = {string:member_group_access}
			WHERE id_board = {int:current_board}',
			array(
				'current_board' => $row['id_board'],
				'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($id_group))),
				'column' =>$access_list == 'allow' ? 'member_groups' : 'deny_member_groups',
				)
		);
	$smcFunc['db_free_result']($request);
}

function assignGroupToBoards($id_group, $boards, $access_list)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
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
	global $smcFunc;

	$updates = array();

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group = {int:current_group}',
		array(
			'regular_member' => 0,
			'current_group' => $id_group,
		)
	);

	$request = $smcFunc['db_query']('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE FIND_IN_SET({string:current_group}, additional_groups) != 0',
		array(
			'current_group' => $id_group,
		)
	);
	
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), array($id_group)))));
	
}

/**
 * Make the given group hidden. Hidden groups are stored in the additional_groups 
 * @param int $id_group
 */
function setGroupToHidden($id_group)
{
	global $smcFunc;

	$updates = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_member, additional_groups
		FROM {db_prefix}members
		WHERE id_group = {int:current_group}
			AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
		array(
			'current_group' => $id_group,
		)
	);
		
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$updates[$row['additional_groups']][] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	foreach ($updates as $additional_groups => $memberArray)
		updateMemberData($memberArray, array('additional_groups' => implode(',', array_merge(explode(',', $additional_groups), array($id_group)))));

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:regular_member}
		WHERE id_group = {int:current_group}',
		array(
			'regular_member' => 0,
			'current_group' => $id_group,
		)
	);
}

function validateShowGroupMembership()
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}membergroups
		WHERE group_type > {int:non_joinable}',
		array(
			'non_joinable' => 1,
		)
	);
	list ($have_joinable) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Do we need to update the setting?
	if ((empty($modSettings['show_group_membership']) && $have_joinable) || (!empty($modSettings['show_group_membership']) && !$have_joinable))
		updateSettings(array('show_group_membership' => $have_joinable ? 1 : 0));
}

function deleteGroupModerators($id_group)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $id_group,
		)
	);
}

function getIDMemberFromGroupModerators($moderators)
{
	global $smcFunc;

	$group_moderators = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE member_name IN ({array_string:moderators}) OR real_name IN ({array_string:moderators})
		LIMIT ' . count($moderators),
		array(
			'moderators' => $moderators,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$group_moderators[] = $row['id_member'];
	$smcFunc['db_free_result']($request);
	
	return $group_moderators;

}

function assignGroupModerators($id_group, $group_moderators)
{
	global $smcFunc;

	$mod_insert = array();
		foreach ($group_moderators as $moderator)
			$mod_insert[] = array($id_group, $moderator);

		$smcFunc['db_insert']('insert',
			'{db_prefix}group_moderators',
			array('id_group' => 'int', 'id_member' => 'int'),
			$mod_insert,
			array('id_group', 'id_member')
		);
}

function getGroupModerators($id_group)
{
	global $smcFunc;

	$moderators = array();

	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}group_moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_group = {int:current_group}',
		array(
			'current_group' => $id_group,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$moderators[$row['id_member']] = $row['real_name'];
	$smcFunc['db_free_result']($request);

	return $moderators;	
}

function getInheritableGroups($id_group)
{
	global $smcFunc, $modSettings;

	$inheritable_groups = array();

	$request = $smcFunc['db_query']('', '
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

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$inheritable_groups[$row['id_group']] = $row['group_name'];
	$smcFunc['db_free_result']($request);

	return $inheritable_groups;
}