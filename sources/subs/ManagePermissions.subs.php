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
 * Set the permission level for a specific profile, group, or group for a profile.
 * @internal
 *
 * @param string $level
 * @param int $group
 * @param mixed $profile = null, int expected
 */
function setPermissionLevel($level, $group, $profile = 'null')
{
	global $smcFunc, $context;

	// we'll need to init illegal permissions.
	require_once(SUBSDIR . '/Permission.subs.php');

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Levels by group... restrict, standard, moderator, maintenance.
	$groupLevels = array(
		'board' => array('inherit' => array()),
		'group' => array('inherit' => array())
	);
	// Levels by board... standard, publish, free.
	$boardLevels = array('inherit' => array());

	// Restrictive - ie. guests.
	$groupLevels['global']['restrict'] = array(
		'search_posts',
		'calendar_view',
		'view_stats',
		'who_view',
		'profile_view_own',
		'profile_identity_own',
	);
	$groupLevels['board']['restrict'] = array(
		'poll_view',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'mark_any_notify',
		'mark_notify',
		'report_any',
		'send_topic',
	);

	// Standard - ie. members.  They can do anything Restrictive can.
	$groupLevels['global']['standard'] = array_merge($groupLevels['global']['restrict'], array(
		'view_mlist',
		'karma_edit',
		'pm_read',
		'pm_send',
		'send_email_to_members',
		'profile_view_any',
		'profile_extra_own',
		'profile_server_avatar',
		'profile_upload_avatar',
		'profile_remote_avatar',
		'profile_remove_own',
	));
	$groupLevels['board']['standard'] = array_merge($groupLevels['board']['restrict'], array(
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'post_attachment',
		'lock_own',
		'remove_own',
		'view_attachments',
	));

	// Moderator - ie. moderators :P.  They can do what standard can, and more.
	$groupLevels['global']['moderator'] = array_merge($groupLevels['global']['standard'], array(
		'calendar_post',
		'calendar_edit_own',
		'access_mod_center',
		'issue_warning',
	));
	$groupLevels['board']['moderator'] = array_merge($groupLevels['board']['standard'], array(
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
	));

	// Maintenance - wannabe admins.  They can do almost everything.
	$groupLevels['global']['maintenance'] = array_merge($groupLevels['global']['moderator'], array(
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
	));
	$groupLevels['board']['maintenance'] = array_merge($groupLevels['board']['moderator'], array(
	));

	// Standard - nothing above the group permissions. (this SHOULD be empty.)
	$boardLevels['standard'] = array(
	);

	// Locked - just that, you can't post here.
	$boardLevels['locked'] = array(
		'poll_view',
		'mark_notify',
		'report_any',
		'send_topic',
		'view_attachments',
	);

	// Publisher - just a little more...
	$boardLevels['publish'] = array_merge($boardLevels['locked'], array(
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
	));

	// Free for All - Scary.  Just scary.
	$boardLevels['free'] = array_merge($boardLevels['publish'], array(
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
	));

	// Make sure we're not granting someone too many permissions!
	foreach ($groupLevels['global'][$level] as $k => $permission)
	{
		if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
			unset($groupLevels['global'][$level][$k]);

		if ($group == -1 && in_array($permission, $context['non_guest_permissions']))
			unset($groupLevels['global'][$level][$k]);
	}
	if ($group == -1)
		foreach ($groupLevels['board'][$level] as $k => $permission)
			if (in_array($permission, $context['non_guest_permissions']))
				unset($groupLevels['board'][$level][$k]);

	// Reset all cached permissions.
	updateSettings(array('settings_updated' => time()));

	// Setting group permissions.
	if ($profile === 'null' && $group !== 'null')
	{
		$group = (int) $group;

		if (empty($groupLevels['global'][$level]))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $group,
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:default_profile}',
			array(
				'current_group' => $group,
				'default_profile' => 1,
			)
		);

		$groupInserts = array();
		foreach ($groupLevels['global'][$level] as $permission)
			$groupInserts[] = array($group, $permission);

		$smcFunc['db_insert']('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string'),
			$groupInserts,
			array('id_group')
		);

		$boardInserts = array();
		foreach ($groupLevels['board'][$level] as $permission)
			$boardInserts[] = array(1, $group, $permission);

		$smcFunc['db_insert']('insert',
			'{db_prefix}board_permissions',
			array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
			$boardInserts,
			array('id_profile', 'id_group')
		);
	}
	// Setting profile permissions for a specific group.
	elseif ($profile !== 'null' && $group !== 'null' && ($profile == 1 || $profile > 4))
	{
		$group = (int) $group;
		$profile = (int) $profile;

		if (!empty($groupLevels['global'][$level]))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group = {int:current_group}
					AND id_profile = {int:current_profile}',
				array(
					'current_group' => $group,
					'current_profile' => $profile,
				)
			);
		}

		if (!empty($groupLevels['board'][$level]))
		{
			$boardInserts = array();
			foreach ($groupLevels['board'][$level] as $permission)
				$boardInserts[] = array($profile, $group, $permission);

			$smcFunc['db_insert']('insert',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
		}
	}
	// Setting profile permissions for all groups.
	elseif ($profile !== 'null' && $group === 'null' && ($profile == 1 || $profile > 4))
	{
		$profile = (int) $profile;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}',
			array(
				'current_profile' => $profile,
			)
		);

		if (empty($boardLevels[$level]))
			return;

		// Get all the groups...
		$query = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group > {int:moderator_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'newbie_group' => 4,
			)
		);
		while ($row = $smcFunc['db_fetch_row']($query))
		{
			$group = $row[0];

			$boardInserts = array();
			foreach ($boardLevels[$level] as $permission)
				$boardInserts[] = array($profile, $group, $permission);

			$smcFunc['db_insert']('insert',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
		}
		$smcFunc['db_free_result']($query);

		// Add permissions for ungrouped members.
		$boardInserts = array();
		foreach ($boardLevels[$level] as $permission)
			$boardInserts[] = array($profile, 0, $permission);

		$smcFunc['db_insert']('insert',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
	}
	// $profile and $group are both null!
	else
		fatal_lang_error('no_access', false);
}


/**
 * Load permissions profiles.
 */
function loadPermissionProfiles()
{
	global $context, $txt, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_profile, profile_name
		FROM {db_prefix}permission_profiles
		ORDER BY id_profile',
		array(
		)
	);
	$context['profiles'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Format the label nicely.
		if (isset($txt['permissions_profile_' . $row['profile_name']]))
			$name = $txt['permissions_profile_' . $row['profile_name']];
		else
			$name = $row['profile_name'];

		$context['profiles'][$row['id_profile']] = array(
			'id' => $row['id_profile'],
			'name' => $name,
			'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
			'unformatted_name' => $row['profile_name'],
		);
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Load permissions into $context['permissions'].
 * @internal
 *
 * @param string $loadType options: 'classic' or 'simple'
 */
function loadAllPermissions($loadType = 'classic')
{
	global $context, $txt, $modSettings;

	// List of all the groups dependant on the currently selected view - for the order so it looks pretty, yea?
	// Note to Mod authors - you don't need to stick your permission group here if you don't mind having it as the last group of the page.
	$permissionGroups = array(
		'membergroup' => array(
			'simple' => array(
				'view_basic_info',
				'disable_censor',
				'use_pm_system',
				'post_calendar',
				'edit_profile',
				'delete_account',
				'use_avatar',
				'moderate_general',
				'administrate',
			),
			'classic' => array(
				'general',
				'pm',
				'calendar',
				'maintenance',
				'member_admin',
				'profile',
			),
		),
		'board' => array(
			'simple' => array(
				'make_posts',
				'make_unapproved_posts',
				'post_polls',
				'participate',
				'modify',
				'notification',
				'attach',
				'moderate',
			),
			'classic' => array(
				'general_board',
				'topic',
				'post',
				'poll',
				'notification',
				'attachment',
			),
		),
	);

	/*   The format of this list is as follows:
		'membergroup' => array(
			'permissions_inside' => array(has_multiple_options, classic_view_group, simple_view_group(_own)*, simple_view_group_any*),
		),
		'board' => array(
			'permissions_inside' => array(has_multiple_options, classic_view_group, simple_view_group(_own)*, simple_view_group_any*),
		);
	*/
	$permissionList = array(
		'membergroup' => array(
			'view_stats' => array(false, 'general', 'view_basic_info'),
			'view_mlist' => array(false, 'general', 'view_basic_info'),
			'who_view' => array(false, 'general', 'view_basic_info'),
			'search_posts' => array(false, 'general', 'view_basic_info'),
			'karma_edit' => array(false, 'general', 'moderate_general'),
			'disable_censor' => array(false, 'general', 'disable_censor'),
			'pm_read' => array(false, 'pm', 'use_pm_system'),
			'pm_send' => array(false, 'pm', 'use_pm_system'),
			'pm_draft' => array(false, 'pm', 'use_pm_system'),
			'pm_autosave_draft' => array(false, 'pm', 'use_pm_system'),
			'send_email_to_members' => array(false, 'pm', 'use_pm_system'),
			'calendar_view' => array(false, 'calendar', 'view_basic_info'),
			'calendar_post' => array(false, 'calendar', 'post_calendar'),
			'calendar_edit' => array(true, 'calendar', 'post_calendar', 'moderate_general'),
			'admin_forum' => array(false, 'maintenance', 'administrate'),
			'manage_boards' => array(false, 'maintenance', 'administrate'),
			'manage_attachments' => array(false, 'maintenance', 'administrate'),
			'manage_smileys' => array(false, 'maintenance', 'administrate'),
			'edit_news' => array(false, 'maintenance', 'administrate'),
			'access_mod_center' => array(false, 'maintenance', 'moderate_general'),
			'moderate_forum' => array(false, 'member_admin', 'moderate_general'),
			'manage_membergroups' => array(false, 'member_admin', 'administrate'),
			'manage_permissions' => array(false, 'member_admin', 'administrate'),
			'manage_bans' => array(false, 'member_admin', 'administrate'),
			'send_mail' => array(false, 'member_admin', 'administrate'),
			'issue_warning' => array(false, 'member_admin', 'moderate_general'),
			'profile_view' => array(true, 'profile', 'view_basic_info', 'view_basic_info'),
			'profile_identity' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_extra' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_title' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_remove' => array(true, 'profile', 'delete_account', 'moderate_general'),
			'profile_server_avatar' => array(false, 'profile', 'use_avatar'),
			'profile_upload_avatar' => array(false, 'profile', 'use_avatar'),
			'profile_remote_avatar' => array(false, 'profile', 'use_avatar'),
		),
		'board' => array(
			'moderate_board' => array(false, 'general_board', 'moderate'),
			'approve_posts' => array(false, 'general_board', 'moderate'),
			'post_new' => array(false, 'topic', 'make_posts'),
			'post_draft' => array(false, 'topic', 'make_posts'),
			'post_autosave_draft' => array(false, 'topic', 'make_posts'),
			'post_unapproved_topics' => array(false, 'topic', 'make_unapproved_posts'),
			'post_unapproved_replies' => array(true, 'topic', 'make_unapproved_posts', 'make_unapproved_posts'),
			'post_reply' => array(true, 'topic', 'make_posts', 'make_posts'),
			'merge_any' => array(false, 'topic', 'moderate'),
			'split_any' => array(false, 'topic', 'moderate'),
			'send_topic' => array(false, 'topic', 'moderate'),
			'make_sticky' => array(false, 'topic', 'moderate'),
			'move' => array(true, 'topic', 'moderate', 'moderate'),
			'lock' => array(true, 'topic', 'moderate', 'moderate'),
			'remove' => array(true, 'topic', 'modify', 'moderate'),
			'modify_replies' => array(false, 'topic', 'moderate'),
			'delete_replies' => array(false, 'topic', 'moderate'),
			'announce_topic' => array(false, 'topic', 'moderate'),
			'delete' => array(true, 'post', 'modify', 'moderate'),
			'modify' => array(true, 'post', 'modify', 'moderate'),
			'report_any' => array(false, 'post', 'participate'),
			'poll_view' => array(false, 'poll', 'participate'),
			'poll_vote' => array(false, 'poll', 'participate'),
			'poll_post' => array(false, 'poll', 'post_polls'),
			'poll_add' => array(true, 'poll', 'post_polls', 'moderate'),
			'poll_edit' => array(true, 'poll', 'modify', 'moderate'),
			'poll_lock' => array(true, 'poll', 'moderate', 'moderate'),
			'poll_remove' => array(true, 'poll', 'modify', 'moderate'),
			'mark_any_notify' => array(false, 'notification', 'notification'),
			'mark_notify' => array(false, 'notification', 'notification'),
			'view_attachments' => array(false, 'attachment', 'participate'),
			'post_unapproved_attachments' => array(false, 'attachment', 'make_unapproved_posts'),
			'post_attachment' => array(false, 'attachment', 'attach'),
		),
	);

	// All permission groups that will be shown in the left column on classic view.
	$leftPermissionGroups = array(
		'general',
		'calendar',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	);

	// we'll need to init illegal permissions.
	require_once(SUBSDIR . '/Permission.subs.php');

	// We need to know what permissions we can't give to guests.
	loadIllegalGuestPermissions();

	// Some permissions are hidden if features are off.
	$hiddenPermissions = array();
	$relabelPermissions = array(); // Permissions to apply a different label to.

	if (!in_array('cd', $context['admin_features']))
	{
		$hiddenPermissions[] = 'calendar_view';
		$hiddenPermissions[] = 'calendar_post';
		$hiddenPermissions[] = 'calendar_edit';
	}
	if (!in_array('w', $context['admin_features']))
		$hiddenPermissions[] = 'issue_warning';
	if (!in_array('k', $context['admin_features']))
		$hiddenPermissions[] = 'karma_edit';
	if (!in_array('dr', $context['admin_features']))
	{
		$hiddenPermissions[] = 'post_draft';
		$hiddenPermissions[] = 'pm_draft';
		$hiddenPermissions[] = 'post_autosave_draft';
		$hiddenPermissions[] = 'pm_autosave_draft';
	}

	// Post moderation?
	if (!$modSettings['postmod_active'])
	{
		$hiddenPermissions[] = 'approve_posts';
		$hiddenPermissions[] = 'post_unapproved_topics';
		$hiddenPermissions[] = 'post_unapproved_replies';
		$hiddenPermissions[] = 'post_unapproved_attachments';
	}
	// If we show them on classic view we change the name.
	else
	{
		// Relabel the topics permissions
		$relabelPermissions['post_new'] = 'auto_approve_topics';

		// Relabel the reply permissions
		$relabelPermissions['post_reply'] = 'auto_approve_replies';

		// Relabel the attachment permissions
		$relabelPermissions['post_attachment'] = 'auto_approve_attachments';
	}

	// Are attachments enabled?
	if (empty($modSettings['attachmentEnable']))
	{
		$hiddenPermissions[] = 'manage_attachments';
		$hiddenPermissions[] = 'view_attachments';
		$hiddenPermissions[] = 'post_unapproved_attachments';
		$hiddenPermissions[] = 'post_attachment';
	}

	// Provide a practical way to modify permissions.
	call_integration_hook('integrate_load_permissions', array(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions));

	$context['permissions'] = array();
	$context['hidden_permissions'] = array();
	foreach ($permissionList as $permissionType => $permissionList)
	{
		$context['permissions'][$permissionType] = array(
			'id' => $permissionType,
			'view' => $loadType,
			'columns' => array()
		);
		foreach ($permissionList as $permission => $permissionArray)
		{
			// If this is a guest permission we don't do it if it's the guest group.
			if (isset($context['group']['id']) && $context['group']['id'] == -1 && in_array($permission, $context['non_guest_permissions']))
				continue;

			// What groups will this permission be in?
			$own_group = $permissionArray[($loadType == 'classic' ? 1 : 2)];
			$any_group = $loadType == 'simple' && !empty($permissionArray[3]) ? $permissionArray[3] : ($loadType == 'simple' && $permissionArray[0] ? $permissionArray[2] : '');

			// First, Do these groups actually exist - if not add them.
			if (!isset($permissionGroups[$permissionType][$loadType][$own_group]))
				$permissionGroups[$permissionType][$loadType][$own_group] = true;
			if (!empty($any_group) && !isset($permissionGroups[$permissionType][$loadType][$any_group]))
				$permissionGroups[$permissionType][$loadType][$any_group] = true;

			// What column should this be located into?
			$position = $loadType == 'classic' && !in_array($own_group, $leftPermissionGroups) ? 1 : 0;

			// If the groups have not yet been created be sure to create them.
			$bothGroups = array('own' => $own_group);
			$bothGroups = array();

			// For guests, just reset the array.
			if (!isset($context['group']['id']) || !($context['group']['id'] == -1 && $any_group))
				$bothGroups['own'] = $own_group;

			if ($any_group)
			{
				$bothGroups['any'] = $any_group;

			}

			foreach ($bothGroups as $group)
				if (!isset($context['permissions'][$permissionType]['columns'][$position][$group]))
					$context['permissions'][$permissionType]['columns'][$position][$group] = array(
						'type' => $permissionType,
						'id' => $group,
						'name' => $loadType == 'simple' ? (isset($txt['permissiongroup_simple_' . $group]) ? $txt['permissiongroup_simple_' . $group] : '') : $txt['permissiongroup_' . $group],
						'icon' => isset($txt['permissionicon_' . $group]) ? $txt['permissionicon_' . $group] : $txt['permissionicon'],
						'help' => isset($txt['permissionhelp_' . $group]) ? $txt['permissionhelp_' . $group] : '',
						'hidden' => false,
						'permissions' => array()
					);

			// This is where we set up the permission dependant on the view.
			if ($loadType == 'classic')
			{
				$context['permissions'][$permissionType]['columns'][$position][$own_group]['permissions'][$permission] = array(
					'id' => $permission,
					'name' => !isset($relabelPermissions[$permission]) ? $txt['permissionname_' . $permission] : $txt[$relabelPermissions[$permission]],
					'show_help' => isset($txt['permissionhelp_' . $permission]),
					'note' => isset($txt['permissionnote_' . $permission]) ? $txt['permissionnote_' . $permission] : '',
					'has_own_any' => $permissionArray[0],
					'own' => array(
						'id' => $permission . '_own',
						'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_own'] : ''
					),
					'any' => array(
						'id' => $permission . '_any',
						'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_any'] : ''
					),
					'hidden' => in_array($permission, $hiddenPermissions),
				);
			}
			else
			{
				foreach ($bothGroups as $group_type => $group)
				{
					$context['permissions'][$permissionType]['columns'][$position][$group]['permissions'][$permission . ($permissionArray[0] ? '_' . $group_type : '')] = array(
						'id' => $permission . ($permissionArray[0] ? '_' . $group_type : ''),
						'name' => isset($txt['permissionname_simple_' . $permission . ($permissionArray[0] ? '_' . $group_type : '')]) ? $txt['permissionname_simple_' . $permission . ($permissionArray[0] ? '_' . $group_type : '')] : $txt['permissionname_' . $permission],
						'help_index' => isset($txt['permissionhelp_' . $permission]) ? 'permissionhelp_' . $permission : '',
						'hidden' => in_array($permission, $hiddenPermissions),
					);
				}
			}

			if (in_array($permission, $hiddenPermissions))
			{
				if ($permissionArray[0])
				{
					$context['hidden_permissions'][] = $permission . '_own';
					$context['hidden_permissions'][] = $permission . '_any';
				}
				else
					$context['hidden_permissions'][] = $permission;
			}
		}
		ksort($context['permissions'][$permissionType]['columns']);

		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach ($context['permissions'][$permissionType]['columns'] as $column => $groups)
			foreach ($groups as $id => $group)
			{
				if (empty($group['permissions']))
					unset($context['permissions'][$permissionType]['columns'][$column][$id]);
				else
				{
					$foundNonHidden = false;
					foreach ($group['permissions'] as $permission)
						if (empty($permission['hidden']))
							$foundNonHidden = true;
					if (!$foundNonHidden)
						$context['permissions'][$permissionType]['columns'][$column][$id]['hidden'] = true;
				}
			}
	}
}

function countPermissions($groups, $hidden_permissions = null)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_group, COUNT(*) AS num_permissions, add_deny
		FROM {db_prefix}permissions'
		. (isset($hidden_permissions) ? '' : 'WHERE permission NOT IN ({array_string:hidden_permissions})') . '
		GROUP BY id_group, add_deny',
		array(
			'hidden_permissions' => !isset($hidden_permissions) ? $hidden_permissions : array(),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		if (isset($groups[(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
			$groups[$row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
	$smcFunc['db_free_result']($request);

	return $groups;
}

function countBoardPermissions($groups, $hidden_permissions = null , $profile_id = null)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
		FROM {db_prefix}board_permissions
		WHERE 1 = 1'
			. (isset($profile_id) ? ' AND id_profile = {int:current_profile}'  : '' )
			. (isset($hidden_permissions) ? '' : ' AND permission NOT IN ({array_string:hidden_permissions})') . '
		GROUP BY ' . (isset($profile_id) ? 'id_profile, ' : '') . 'id_group, add_deny',
		array(
			'hidden_permissions' => !isset($hidden_permissions) ? $hidden_permissions : array(),
			'current_profile' => $profile_id,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		if (isset($groups[(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
			$groups[$row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
	$smcFunc['db_free_result']($request);

	return $groups;
}

function assignPermissionProfileToBoard($profile, $board)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET id_profile = {int:current_profile}
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $board,
			'current_profile' => $profile,
		)
	);
}

function copyPermission($copy_from, $groups, $illgeal_permissions, $non_guest_permissions = array())
{
	global $smcFunc;

	// Retrieve current permissions of group.
	$request = $smcFunc['db_query']('', '
		SELECT permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group = {int:copy_from}',
		array(
			'copy_from' => $copy_from,
		)
	);
	$target_perm = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$target_perm[$row['permission']] = $row['add_deny'];
	$smcFunc['db_free_result']($request);

	$inserts = array();
	foreach ($groups as $group_id)
		foreach ($target_perm as $perm => $add_deny)
		{
			// No dodgy permissions please!
			if (!empty($illgeal_permissions) && in_array($perm, $illgeal_permissions))
				continue;
			if ($group_id == -1 && in_array($perm, $non_guest_permissions))
				continue;

			if ($group_id != 1 && $group_id != 3)
				$inserts[] = array($perm, $group_id, $add_deny);
		}

	// Delete the previous permissions...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:group_list})
			' . (empty($illgeal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		array(
			'group_list' => $groups,
			'illegal_permissions' => !empty($illgeal_permissions) ? $illgeal_permissions : array(),
		)
	);

	if (!empty($inserts))
	{
		// ..and insert the new ones.
		$smcFunc['db_insert']('',
			'{db_prefix}permissions',
			array(
				'permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int',
			),
			$inserts,
			array('permission', 'id_group')
		);
	}
}

function copyBoardPermission($copy_from, $groups, $profile_id, $non_guest_permissions)
{
	global $smcFunc;

	// Now do the same for the board permissions.
	$request = $smcFunc['db_query']('', '
		SELECT permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:copy_from}
			AND id_profile = {int:current_profile}',
		array(
			'copy_from' => $copy_from,
			'current_profile' => $profile_id,
		)
	);
	$target_perm = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$target_perm[$row['permission']] = $row['add_deny'];
	$smcFunc['db_free_result']($request);

	$inserts = array();
	foreach ($_POST['group'] as $group_id)
		foreach ($target_perm as $perm => $add_deny)
		{
			// Are these for guests?
			if ($group_id == -1 && in_array($perm, $non_guest_permissions))
				continue;

			$inserts[] = array($perm, $group_id, $profile_id, $add_deny);
		}

	// Delete the previous global board permissions...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND id_profile = {int:current_profile}',
		array(
			'current_group_list' => $groups,
			'current_profile' => $profile_id,
		)
	);

	// And insert the copied permissions.
	if (!empty($inserts))
	{
		// ..and insert the new ones.
		$smcFunc['db_insert']('',
			'{db_prefix}board_permissions',
			array('permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'),
			$inserts,
			array('permission', 'id_group', 'id_profile')
		);
	}
}

function deletePermission($groups, $permission, $illegal_permissions)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND permission = {string:current_permission}
			' . (empty($illegal_permissions) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		array(
			'current_group_list' => $groups,
			'current_permission' => $permission,
			'illegal_permissions' => !empty($illegal_permissions) ? $illegal_permissions : array(),
		)
	);
}

function deleteBoardPermission($group, $profile_id, $permission)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group IN ({array_int:current_group_list})
			AND id_profile = {int:current_profile}
			AND permission = {string:current_permission}',
		array(
			'current_group_list' => $group,
			'current_profile' => $profile_id,
			'current_permission' => $permission,
		)
	);
}

function replacePermission($permChange)
{
	global $smcFunc;

	$smcFunc['db_insert']('replace',
		'{db_prefix}permissions',
			array('permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'),
			$permChange,
			array('permission', 'id_group')
		);
}

function replaceBoardPermission($permChange)
{
	global $smcFunc;

	$smcFunc['db_insert']('replace',
		'{db_prefix}board_permissions',
		array('permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'),
		$permChange,
		array('permission', 'id_group', 'id_profile')
	);
}

function removeModeratorPermissions()
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group = {int:moderator_group}',
		array(
			'moderator_group' => 3,
		)
	);
}

function fetchPermissions($id_group)
{
	global $smcFunc;

	$permissions = array();

	$result = $smcFunc['db_query']('', '
		SELECT permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group = {int:current_group}',
		array(
			'current_group' => $id_group,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$permissions[empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
	$smcFunc['db_free_result']($result);

	return $permissions;
}

function fetchBoardPermissions($id_group, $permission_type, $profile_id)
{
	global $smcFunc;

	$permissions = array();

	$result = $smcFunc['db_query']('', '
		SELECT permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		array(
			'current_group' => $id_group,
			'current_profile' => $permission_type == 'membergroup' ? 1 : $profile_id,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$permissions[empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
	$smcFunc['db_free_result']($result);

	return $permissions;
}