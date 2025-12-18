<?php

/**
 * Support functions and db interface functions for permissions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Helper\Util;
use ElkArte\PermissionManager;
use ElkArte\Permissions;

/**
 * Set the permission level for a specific profile, group, or group for a profile.
 *
 * @param string $level The level ('restrict', 'standard', etc.)
 * @param int|null $group The group to set the permission for
 * @param int|null $profile = null, int id of the permissions group or 'null' if we're setting it for a group
 *
 * @throws \ElkArte\Exceptions\Exception no_access
 * @package Permissions
 *
 */
function setPermissionLevel($level, $group = null, $profile = null)
{
	$db = database();

	// We'll need to init illegal permissions.
	$permissionsObject = new Permissions();
	$illegal_permissions = $permissionsObject->getIllegalPermissions();
	$illegal_guest_permissions = $permissionsObject->getIllegalGuestPermissions();

	// Levels by group... restrict, standard, moderator, maintenance.
	$groupLevels = [
		'board' => ['inherit' => []],
		'group' => ['inherit' => []]
	];

	// Levels by board... standard, publish, free.
	$boardLevels = ['inherit' => []];

	// Restrictive - ie. guests.
	$groupLevels['global']['restrict'] = [
		'search_posts',
		'calendar_view',
		'view_stats',
		'who_view',
		'profile_view_own',
		'profile_identity_own',
	];

	$groupLevels['board']['restrict'] = [
		'poll_view',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'mark_any_notify',
		'mark_notify',
		'report_any',
	];

	// Standard - i.e., members.  They can do anything Restrictive can.
	$groupLevels['global']['standard'] = array_merge($groupLevels['global']['restrict'], [
		'view_mlist',
		'karma_edit',
		'like_posts',
		'like_posts_stats',
		'pm_read',
		'pm_send',
		'send_email_to_members',
		'profile_view_any',
		'profile_extra_own',
		'profile_set_avatar',
		'profile_remove_own',
	]);

	$groupLevels['board']['standard'] = array_merge($groupLevels['board']['restrict'], [
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'post_attachment',
		'lock_own',
		'remove_own',
		'view_attachments',
	]);

	// Moderator - i.e., moderators :P. They can do what standard can, and more.
	$groupLevels['global']['moderator'] = array_merge($groupLevels['global']['standard'], [
		'calendar_post',
		'calendar_edit_own',
		'access_mod_center',
		'issue_warning',
	]);

	$groupLevels['board']['moderator'] = array_merge($groupLevels['board']['standard'], [
		'make_sticky',
		'poll_edit_any',
		'delete_any',
		'modify_any',
		'lock_any',
		'remove_any',
		'move_any',
		'merge_any',
		'split_any',
		'poll_lock_any',
		'poll_remove_any',
		'poll_add_any',
		'approve_posts',
		'like_posts',
	]);

	// Maintenance - wannabe admins.  They can do almost everything.
	$groupLevels['global']['maintenance'] = array_merge($groupLevels['global']['moderator'], [
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'moderate_forum',
		'manage_membergroups',
		'manage_bans',
		'admin_forum',
		'manage_permissions',
		'edit_news',
		'calendar_edit_any',
		'profile_identity_any',
		'profile_extra_any',
		'profile_title_any',
	]);
	$groupLevels['board']['maintenance'] = array_merge($groupLevels['board']['moderator'], []);

	// Standard - nothing above the group permissions. (this SHOULD be empty.)
	$boardLevels['standard'] = [];

	// Locked - just that, you can't post here.
	$boardLevels['locked'] = [
		'poll_view',
		'mark_notify',
		'report_any',
		'view_attachments',
	];

	// Publisher - just a little more...
	$boardLevels['publish'] = array_merge($boardLevels['locked'], [
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'mark_any_notify',
		'delete_replies',
		'modify_replies',
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'poll_remove_own',
		'post_attachment',
		'lock_own',
		'remove_own',
	]);

	// Free for All - Scary.  Just scary.
	$boardLevels['free'] = array_merge($boardLevels['publish'], [
		'poll_lock_any',
		'poll_edit_any',
		'poll_add_any',
		'poll_remove_any',
		'make_sticky',
		'lock_any',
		'remove_any',
		'delete_any',
		'split_any',
		'merge_any',
		'modify_any',
		'approve_posts',
	]);

	// Make sure we're not granting someone too many permissions!
	foreach ($groupLevels['global'][$level] as $k => $permission)
	{
		if (!empty($illegal_permissions) && in_array($permission, $illegal_permissions, true))
		{
			unset($groupLevels['global'][$level][$k]);
		}

		if ($group === -1 && in_array($permission, $illegal_guest_permissions, true))
		{
			unset($groupLevels['global'][$level][$k]);
		}
	}

	if ($group === -1)
	{
		foreach ($groupLevels['board'][$level] as $k => $permission)
		{
			if (in_array($permission, $illegal_guest_permissions, true))
			{
				unset($groupLevels['board'][$level][$k]);
			}
		}
	}

	// Reset all cached permissions.
	updateSettings(['settings_updated' => time()]);

	// Setting group permissions.
	if ($profile === null && $group !== null)
	{
		$group = (int) $group;

		if (empty($groupLevels['global'][$level]))
		{
			return;
		}

		$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			[
				'current_group' => $group,
				'illegal_permissions' => $illegal_permissions,
			]
		);

		$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:default_profile}',
			[
				'current_group' => $group,
				'default_profile' => 1,
			]
		);

		$groupInserts = [];
		foreach ($groupLevels['global'][$level] as $permission)
		{
			$groupInserts[] = [$group, $permission];
		}

		$db->insert('insert',
			'{db_prefix}permissions',
			['id_group' => 'int', 'permission' => 'string'],
			$groupInserts,
			['id_group']
		);

		$boardInserts = [];
		foreach ($groupLevels['board'][$level] as $permission)
		{
			$boardInserts[] = [1, $group, $permission];
		}

		$db->insert('insert',
			'{db_prefix}board_permissions',
			['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
			$boardInserts,
			['id_profile', 'id_group']
		);
	}
	// Setting profile permissions for a specific group.
	elseif ($profile !== null && $group !== null && ($profile === 1 || $profile > 4))
	{
		$group = (int) $group;
		$profile = (int) $profile;

		if (!empty($groupLevels['global'][$level]))
		{
			$db->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group = {int:current_group}
					AND id_profile = {int:current_profile}',
				[
					'current_group' => $group,
					'current_profile' => $profile,
				]
			);
		}

		if (!empty($groupLevels['board'][$level]))
		{
			$boardInserts = [];
			foreach ($groupLevels['board'][$level] as $permission)
			{
				$boardInserts[] = [$profile, $group, $permission];
			}

			$db->insert('insert',
				'{db_prefix}board_permissions',
				['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
				$boardInserts,
				['id_profile', 'id_group']
			);
		}
	}
	// Setting profile permissions for all groups.
	elseif ($profile !== null && $group === null && ($profile === 1 || $profile > 4))
	{
		$profile = (int) $profile;

		$db->query('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}',
			[
				'current_profile' => $profile,
			]
		);

		if (empty($boardLevels[$level]))
		{
			return;
		}

		// Get all the groups...
		$db->fetchQuery('
			SELECT 
				id_group
			FROM {db_prefix}membergroups
			WHERE id_group > {int:moderator_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			[
				'moderator_group' => 3,
				'newbie_group' => 4,
			]
		)->fetch_callback(
			function ($row) use ($db, $boardLevels, $profile, $level) {
				$group = $row['id_group'];

				$boardInserts = [];
				foreach ($boardLevels[$level] as $permission)
				{
					$boardInserts[] = [$profile, $group, $permission];
				}

				$db->insert('insert',
					'{db_prefix}board_permissions',
					['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
					$boardInserts,
					['id_profile', 'id_group']
				);
			}
		);

		// Add permissions for ungrouped members.
		$boardInserts = [];
		foreach ($boardLevels[$level] as $permission)
		{
			$boardInserts[] = [$profile, 0, $permission];
		}

		$db->insert('insert',
			'{db_prefix}board_permissions',
			['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'],
			$boardInserts,
			['id_profile', 'id_group']
		);
	}
	// $profile and $group are both null!
	else
	{
		throw new \ElkArte\Exceptions\Exception('no_access', false);
	}
}

/**
 * Load permissions profiles.
 *
 * @package Permissions
 */
function loadPermissionProfiles()
{
	global $context, $txt;

	$db = database();

	$context['profiles'] = [];
	$db->fetchQuery('
		SELECT 
			id_profile, profile_name
		FROM {db_prefix}permission_profiles
		ORDER BY id_profile',
		[]
	)->fetch_callback(
		function ($row) use ($txt) {
			global $context;

			// Format the label nicely.
			$name = $txt['permissions_profile_' . $row['profile_name']] ?? $row['profile_name'];

			$context['profiles'][$row['id_profile']] = [
				'id' => $row['id_profile'],
				'name' => $name,
				'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
				'unformatted_name' => $row['profile_name'],
			];
		}
	);
}

/**
 * Load permissions into $context['permissions'].
 *
 * @package Permissions
 */
function loadAllPermissions()
{
	global $context, $txt, $modSettings;

	$permissionManager = new PermissionManager($context, $txt, $modSettings);
	$permissionManager->loadAllPermissions();
}

/**
 * Counts membergroup permissions.
 *
 * @param int[] $groups the group ids to return permission counts
 * @param string[]|null $hidden_permissions array of permission names to skip in the count totals
 *
 * @return int[] [id_group][num_permissions][denied] = count, [id_group][num_permissions][allowed] = count
 * @package Permissions
 */
function countPermissions($groups, $hidden_permissions = null)
{
	$db = database();

	$db->fetchQuery('
		SELECT 
			id_group, COUNT(*) AS num_permissions, add_deny
		FROM {db_prefix}permissions'
		. (isset($hidden_permissions) ? '' : 'WHERE permission NOT IN ({array_string:hidden_permissions})') . '
		GROUP BY id_group, add_deny',
		[
			'hidden_permissions' => !isset($hidden_permissions) ? $hidden_permissions : [],
		]
	)->fetch_callback(
		function ($row) use (&$groups) {
			if (isset($groups[(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
			{
				$groups[$row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
			}
		}
	);

	return $groups;
}

/**
 * Counts board permissions.
 *
 * @param int[] $groups
 * @param string[]|null $hidden_permissions
 * @param int|null $profile_id
 *
 * @return int[]
 * @package Permissions
 */
function countBoardPermissions($groups, $hidden_permissions = null, $profile_id = null)
{
	$db = database();

	$db->fetchQuery('
		SELECT 
			' . (isset($profile_id) ? 'id_profile, ' : '') . 'id_group, add_deny, COUNT(*) AS num_permissions
		FROM {db_prefix}board_permissions
		WHERE 1 = 1'
		. (isset($profile_id) ? ' AND id_profile = {int:current_profile}' : '')
		. (empty($hidden_permissions) ? '' : ' AND permission NOT IN ({array_string:hidden_permissions})') . '
		GROUP BY ' . (isset($profile_id) ? 'id_profile, ' : '') . 'id_group, add_deny',
		[
			'hidden_permissions' => !empty($hidden_permissions) ? $hidden_permissions : [],
			'current_profile' => $profile_id,
		]
	)->fetch_callback(
		function ($row) use (&$groups) {
			if (isset($groups[(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
			{
				$groups[$row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
			}
		}
	);

	return $groups;
}

/**
 * Used to assign a permission profile to a board.
 *
 * @param int $profile
 * @param int $board
 * @package Permissions
 */
function assignPermissionProfileToBoard($profile, $board)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}boards
		SET 
			id_profile = {int:current_profile}
		WHERE id_board IN ({array_int:board_list})',
		[
			'board_list' => $board,
			'current_profile' => $profile,
		]
	);
}

/**
 * Copy a set of permissions from one group to another.
 *
 * @param int $copy_from
 * @param int[] $groups
 * @param string[] $illegal_permissions
 * @param string[] $non_guest_permissions
 * @todo another function with the same name in Membergroups.subs.php
 * @package Permissions
 */
function copyPermission($copy_from, $groups, $illegal_permissions, $non_guest_permissions = [])
{
	$db = database();

	// Retrieve current permissions of a group.
	$target_perm = [];
	$db->fetchQuery('
		SELECT 
			permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group = {int:copy_from}',
		[
			'copy_from' => $copy_from,
		]
	)->fetch_callback(
		function ($row) use (&$target_perm) {
			$target_perm[$row['permission']] = $row['add_deny'];
		}
	);

	$inserts = [];
	foreach ($groups as $group_id)
	{
		foreach ($target_perm as $perm => $add_deny)
		{
			// No dodgy permissions, please!
			if (!empty($illegal_permissions) && in_array($perm, $illegal_permissions))
			{
				continue;
			}

			if ($group_id === -1 && in_array($perm, $non_guest_permissions))
			{
				continue;
			}

			if ($group_id !== 1 && $group_id !== 3)
			{
				$inserts[] = ['permission' => $perm, 'id_group' => $group_id, 'add_deny' => $add_deny];
			}
		}
	}

	// Delete the previous permissions...
	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})
			' . (empty($illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		[
			'group_list' => $groups,
			'illegal_permissions' => $illegal_permissions,
		]
	);

	if (!empty($inserts))
	{
		// ...and insert the new ones.
		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		replacePermission($inserts);
	}
}

/**
 * Copy a set of board permissions from one group to another.
 *
 * @param int $copy_from
 * @param int[] $groups The target groups
 * @param int $profile_id
 * @param string[] $non_guest_permissions
 * @package Permissions
 */
function copyBoardPermission($copy_from, $groups, $profile_id, $non_guest_permissions)
{
	$db = database();

	// Now do the same for the board permissions.
	$target_perm = [];
	$db->fetchQuery('
		SELECT 
			permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:copy_from}
			AND id_profile = {int:current_profile}',
		[
			'copy_from' => $copy_from,
			'current_profile' => $profile_id,
		]
	)->fetch_callback(
		function ($row) use (&$target_perm) {
			$target_perm[$row['permission']] = $row['add_deny'];
		}
	);

	$inserts = [];
	foreach ($groups as $group_id)
	{
		foreach ($target_perm as $perm => $add_deny)
		{
			// Are these for guests?
			if ($group_id === -1 && in_array($perm, $non_guest_permissions))
			{
				continue;
			}

			$inserts[] = [$perm, $group_id, $add_deny, $profile_id];
		}
	}

	// Delete the previous global board permissions...
	deleteAllBoardPermissions($groups, $profile_id);

	// And insert the copied permissions.
	if (!empty($inserts))
	{
		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		replaceBoardPermission($inserts);
	}
}

/**
 * Deletes membergroup permissions.
 *
 * @param int[] $groups
 * @param string $permission
 * @param string[] $illegal_permissions
 * @package Permissions
 */
function deletePermission($groups, $permission, $illegal_permissions)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND permission = {string:current_permission}
			' . (empty($illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		[
			'current_group_list' => $groups,
			'current_permission' => $permission,
			'illegal_permissions' => $illegal_permissions,
		]
	);
}

/**
 * Delete board permissions.
 *
 * @param int[] $group
 * @param int $profile_id
 * @param string $permission
 * @package Permissions
 */
function deleteBoardPermission($group, $profile_id, $permission)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND id_profile = {int:current_profile}
			AND permission = {string:current_permission}',
		[
			'current_group_list' => $group,
			'current_profile' => $profile_id,
			'current_permission' => $permission,
		]
	);
}

/**
 * Replaces existing membergroup permissions with the given ones.
 *
 * @param array $permChange associative array permission, id_group, add_deny
 * @package Permissions
 */
function replacePermission($permChange)
{
	$db = database();

	if (!empty($permChange))
	{
		$db->replace(
			'{db_prefix}permissions',
			['permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'],
			$permChange,
			['permission', 'id_group']
		);
	}
}

/**
 * Replaces existing board permissions with the given ones.
 *
 * @param array $permChange associative array of 'permission', 'id_group', 'add_deny', 'id_profile'
 * @package Permissions
 */
function replaceBoardPermission($permChange)
{
	$db = database();

	$db->replace(
		'{db_prefix}board_permissions',
		['permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int', 'id_profile' => 'int'],
		$permChange,
		['permission', 'id_group', 'id_profile']
	);
}

/**
 * Removes the moderator's permissions.
 *
 * @package Permissions
 */
function removeModeratorPermissions()
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group = {int:moderator_group}',
		[
			'moderator_group' => 3,
		]
	);
}

/**
 * Fetches membergroup permissions from the given group.
 *
 * @param int $id_group
 * @return array
 * @package Permissions
 */
function fetchPermissions($id_group)
{
	$db = database();

	$permissions = [
		'allowed' => [],
		'denied' => [],
	];

	$db->fetchQuery('
		SELECT 
			permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group = {int:current_group}',
		[
			'current_group' => $id_group,
		]
	)->fetch_callback(
		function ($row) use (&$permissions) {
			$permissions[empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		}
	);

	return $permissions;
}

/**
 * Fetches board permissions from the given group.
 *
 * @param int $id_group
 * @param string $permission_type
 * @param int $profile_id
 *
 * @return array
 * @package Permissions
 *
 */
function fetchBoardPermissions($id_group, $permission_type, $profile_id)
{
	$db = database();

	$permissions = [
		'allowed' => [],
		'denied' => [],
	];

	$db->fetchQuery('
		SELECT 
			permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		[
			'current_group' => $id_group,
			'current_profile' => $permission_type === 'membergroup' ? 1 : $profile_id,
		]
	)->fetch_callback(
		function ($row) use (&$permissions) {
			$permissions[empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		}
	);

	return $permissions;
}

/**
 * Deletes invalid permissions for the given group.
 *
 * @param int $id_group
 * @param string[] $illegal_permissions
 * @package Permissions
 */
function deleteInvalidPermissions($id_group, $illegal_permissions)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group = {int:current_group}
		' . (empty($illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		[
			'current_group' => $id_group,
			'illegal_permissions' => $illegal_permissions,
		]
	);
}

/**
 * Deletes a membergroup's board permissions from a specified permission profile.
 *
 * @param int[] $groups
 * @param int $id_profile
 * @package Permissions
 */
function deleteAllBoardPermissions(array $groups, $id_profile)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND id_profile = {int:current_profile}',
		[
			'current_group_list' => $groups,
			'current_profile' => $id_profile,
		]
	);
}

/**
 * Deny permissions disabled? We need to clean the permission tables.
 *
 * @package Permissions
 */
function clearDenyPermissions()
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE add_deny = {int:denied}',
		[
			'denied' => 0,
		]
	);
	$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE add_deny = {int:denied}',
		[
			'denied' => 0,
		]
	);
}

/**
 * Permissions for post-based groups disabled? We need to clean the permission
 * tables, too.
 *
 * @package Permissions
 */
function clearPostgroupPermissions()
{
	$db = database();

	$post_groups = $db->fetchQuery('
		SELECT 
			id_group
		FROM {db_prefix}membergroups
		WHERE min_posts != {int:min_posts}',
		[
			'min_posts' => -1,
		]
	)->fetch_callback(
		function ($row) {
			return $row['id_group'];
		}
	);

	// Remove'em.
	$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:post_group_list})',
		[
			'post_group_list' => $post_groups,
		]
	);
	$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:post_group_list})',
		[
			'post_group_list' => $post_groups,
		]
	);
	$db->query('', '
		UPDATE {db_prefix}membergroups
		SET 
			id_parent = {int:not_inherited}
		WHERE id_parent IN ({array_int:post_group_list})',
		[
			'post_group_list' => $post_groups,
			'not_inherited' => -2,
		]
	);
}

/**
 * Copies a permission profile.
 *
 * @param string $profile_name
 * @param int $copy_from
 * @package Permissions
 */
function copyPermissionProfile($profile_name, $copy_from)
{
	$db = database();

	$profile_name = Util::htmlspecialchars($profile_name);
	// Insert the profile itself.
	$db->insert('',
		'{db_prefix}permission_profiles',
		[
			'profile_name' => 'string',
		],
		[
			$profile_name,
		],
		['id_profile']
	);
	$profile_id = $db->insert_id('{db_prefix}permission_profiles');

	// Load the permissions from the one it's being copied from.
	$inserts = $db->fetchQuery('
		SELECT 
			id_group, permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_profile = {int:copy_from}',
		[
			'copy_from' => $copy_from,
		]
	)->fetch_callback(
		function ($row) use ($profile_id) {
			return [$profile_id, $row['id_group'], $row['permission'], $row['add_deny']];
		}
	);

	if (!empty($inserts))
	{
		$db->insert('insert',
			'{db_prefix}board_permissions',
			['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
			$inserts,
			['id_profile', 'id_group', 'permission']
		);
	}
}

/**
 * Rename a permission profile.
 *
 * @param int $id_profile
 * @param string $name
 * @package Permissions
 */
function renamePermissionProfile($id_profile, $name)
{
	$db = database();

	$name = Util::htmlspecialchars($name);

	$db->query('', '
		UPDATE {db_prefix}permission_profiles
		SET 
			profile_name = {string:profile_name}
		WHERE id_profile = {int:current_profile}',
		[
			'current_profile' => $id_profile,
			'profile_name' => $name,
		]
	);
}

/**
 * Delete a permission profile
 *
 * @param int[] $profiles
 *
 * @throws \ElkArte\Exceptions\Exception no_access
 * @package Permissions
 *
 */
function deletePermissionProfiles($profiles)
{
	$db = database();

	// Verify it's not in use...
	$request = $db->query('', '
		SELECT 
			id_board
		FROM {db_prefix}boards
		WHERE id_profile IN ({array_int:profile_list})
		LIMIT 1',
		[
			'profile_list' => $profiles,
		]
	);
	if ($request->num_rows() !== 0)
	{
		throw new \ElkArte\Exceptions\Exception('no_access', false);
	}
	$request->free_result();

	// Oh well, delete it.
	$db->query('', '
		DELETE FROM {db_prefix}permission_profiles
		WHERE id_profile IN ({array_int:profile_list})',
		[
			'profile_list' => $profiles,
		]
	);
}

/**
 * Checks if a permission profile is in use.
 *
 * @param int[] $profiles
 *
 * @return int[]
 * @package Permissions
 */
function permProfilesInUse($profiles)
{
	$db = database();

	$db->fetchQuery('
		SELECT 
			id_profile, COUNT(id_board) AS board_count
		FROM {db_prefix}boards
		GROUP BY id_profile',
		[]
	)->fetch_callback(
		function ($row) use (&$profiles) {
			global $txt;

			if (isset($profiles[$row['id_profile']]))
			{
				$profiles[$row['id_profile']]['in_use'] = true;
				$profiles[$row['id_profile']]['boards'] = $row['board_count'];
				$profiles[$row['id_profile']]['boards_text'] = $row['board_count'] > 1 ? sprintf($txt['permissions_profile_used_by_many'], $row['board_count']) : $txt['permissions_profile_used_by_' . ($row['board_count'] ? 'one' : 'none')];
			}
		}
	);

	return $profiles;
}

/**
 * Delete a board permission.
 *
 * @param array $groups array where the keys are the group id's
 * @param int[] $profile
 * @param string[] $permissions
 * @package Permissions
 */
function deleteBoardPermissions($groups, $profile, $permissions)
{
	$db = database();

	// Start by deleting all the permissions relevant.
	$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_profile = {int:current_profile}
			AND permission IN ({array_string:permissions})
			AND id_group IN ({array_int:profile_group_list})',
		[
			'profile_group_list' => array_keys($groups),
			'current_profile' => $profile,
			'permissions' => $permissions,
		]
	);
}

/**
 * Adds a new board permission to the board_permissions table.
 *
 * @param array $new_permissions
 * @package Permissions
 */
function insertBoardPermission($new_permissions)
{
	$db = database();

	$db->insert('',
		'{db_prefix}board_permissions',
		['id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'],
		$new_permissions,
		['id_profile', 'id_group', 'permission']
	);
}

/**
 * Lists the board permissions.
 *
 * @param int[] $group
 * @param int $profile
 * @param string[] $permissions
 * @return array
 * @package Permissions
 */
function getPermission($group, $profile, $permissions)
{
	$db = database();

	$groups = [];

	$db->fetchQuery('
		SELECT 
			id_group, permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_profile = {int:current_profile}
			AND permission IN ({array_string:permissions})
			AND id_group IN ({array_int:profile_group_list})',
		[
			'profile_group_list' => $group,
			'current_profile' => $profile,
			'permissions' => $permissions,
		]
	)->fetch_callback(
		function ($row) use (&$groups) {
			$groups[$row['id_group']][$row['add_deny'] ? 'add' : 'deny'][] = $row['permission'];
		}
	);

	return $groups;
}
