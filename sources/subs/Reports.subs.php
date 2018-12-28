<?php

/**
 * This file is used by Reports.controller.php mainly to retrieve data from the database
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Retrieve a list of all boards plus more details
 * @todo merge with some function in Boards.subs.php
 */
function reportsBoardsList()
{
	global $txt;

	$db = database();

	// Go through each board!
	$request = $db->query('', '
		SELECT b.id_board, b.name, b.num_posts, b.num_topics, b.count_posts, b.member_groups, b.override_theme, b.id_profile, b.deny_member_groups,
			c.name AS cat_name, COALESCE(par.name, {string:text_none}) AS parent_name, COALESCE(th.value, {string:text_none}) AS theme_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			LEFT JOIN {db_prefix}boards AS par ON (par.id_board = b.id_parent)
			LEFT JOIN {db_prefix}themes AS th ON (th.id_theme = b.id_theme AND th.variable = {string:name})
		ORDER BY b.board_order',
		array(
			'name' => 'name',
			'text_none' => $txt['none'],
		)
	);
	$boards = array();
	while ($row = $db->fetch_assoc($request))
		$boards[] = $row;

	return $boards;
}

/**
 * Fetch membergroups names and ids
 *
 * @param string $group_clause a string used as WHERE clause in the query
 * @param int[] $query_groups an array of group ids
 *
 * @return array
 */
function allMembergroups($group_clause, $query_groups = array())
{
	global $modSettings;

	$db = database();

	$group_clause = !empty($group_clause) ? $group_clause : '1=1';

	// Get all the possible membergroups, except admin!
	$request = $db->query('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE ' . $group_clause . '
			AND id_group != {int:admin_group}' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
			'newbie_group' => 4,
			'groups' => $query_groups,
			'moderator_group' => 3,
		)
	);
	$member_groups = array();
	while ($row = $db->fetch_assoc($request))
		$member_groups[$row['id_group']] = $row['group_name'];
	$db->free_result($request);

	return $member_groups;
}

/**
 * Boards profiles and related permissions
 *
 * @param int[] $profiles a list of board profile ids
 * @param string $group_clause a string used as WHERE clause in the query
 * @param int[] $query_groups an array of group ids
 *
 * @return array
 */
function boardPermissions($profiles, $group_clause, $query_groups)
{
	global $modSettings;

	$db = database();

	// Permissions, last!
	$board_permissions = array();
	$request = $db->query('', '
		SELECT id_profile, id_group, add_deny, permission
		FROM {db_prefix}board_permissions
		WHERE id_profile IN ({array_int:profile_list})
			AND ' . $group_clause . (empty($modSettings['permission_enable_deny']) ? '
			AND add_deny = {int:not_deny}' : '') . '
		ORDER BY id_profile, permission',
		array(
			'profile_list' => $profiles,
			'not_deny' => 1,
			'groups' => $query_groups,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$board_permissions[] = $row;

	$db->free_result($request);

	return $board_permissions;
}

/**
 * Retrieve all membergroups and their board access
 */
function allMembergroupsBoardAccess()
{
	global $txt;

	$db = database();

	$request = $db->query('', '
		SELECT mg.id_group, mg.group_name, mg.online_color, mg.min_posts, mg.max_messages, mg.icons,
			CASE WHEN bp.permission IS NOT NULL OR mg.id_group = {int:admin_group} THEN 1 ELSE 0 END AS can_moderate
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}board_permissions AS bp ON (bp.id_group = mg.id_group AND bp.id_profile = {int:default_profile} AND bp.permission = {string:moderate_board})
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'admin_group' => 1,
			'default_profile' => 1,
			'newbie_group' => 4,
			'moderate_board' => 'moderate_board',
		)
	);

	// Cache them so we get regular members too.
	$rows = array(
		array(
			'id_group' => -1,
			'group_name' => $txt['membergroups_guests'],
			'online_color' => '',
			'min_posts' => -1,
			'max_messages' => null,
			'icons' => ''
		),
		array(
			'id_group' => 0,
			'group_name' => $txt['membergroups_members'],
			'online_color' => '',
			'min_posts' => -1,
			'max_messages' => null,
			'icons' => ''
		),
	);
	while ($row = $db->fetch_assoc($request))
		$rows[] = $row;
	$db->free_result($request);

	return $rows;
}

/**
 * Boards profiles and related permissions based on groups
 *
 * @param string $group_clause a string used as WHERE clause in the query
 * @param int[] $query_groups an array of group ids
 *
 * @return array
 */
function boardPermissionsByGroup($group_clause, $query_groups)
{
	global $modSettings;

	$db = database();

	// Now the big permission fetch!
	$request = $db->query('', '
		SELECT id_group, add_deny, permission
		FROM {db_prefix}permissions
		WHERE ' . $group_clause . (empty($modSettings['permission_enable_deny']) ? '
			AND add_deny = {int:not_denied}' : '') . '
		ORDER BY permission',
		array(
			'not_denied' => 1,
			'moderator_group' => 3,
			'groups' => $query_groups,
		)
	);

	$perms = array();
	while ($row = $db->fetch_assoc($request))
		$perms[] = $row;
	$db->free_result($request);

	return $perms;
}
