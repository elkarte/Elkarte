<?php

/**
 * Functions to support the permissions controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 1
 */

class Permissions
{
	/**
	 * Load a few illegal permissions in context.
	 */
	public static function loadIllegal()
	{
		global $context;

		$context['illegal_permissions'] = array();
		if (!allowedTo('admin_forum'))
			$context['illegal_permissions'][] = 'admin_forum';
		if (!allowedTo('manage_membergroups'))
			$context['illegal_permissions'][] = 'manage_membergroups';
		if (!allowedTo('manage_permissions'))
			$context['illegal_permissions'][] = 'manage_permissions';

		call_integration_hook('integrate_load_illegal_permissions');
	}

	/**
	 * Loads those permissions guests cannot have, into context.
	 */
	public static function loadIllegalGuest()
	{
		global $context;

		$context['non_guest_permissions'] = array(
			'delete_replies',
			'karma_edit',
			'poll_add_own',
			'pm_read',
			'pm_send',
			'profile_identity',
			'profile_extra',
			'profile_title',
			'profile_remove',
			'profile_set_avatar',
			'profile_view_own',
			'mark_any_notify',
			'mark_notify',
			'admin_forum',
			'manage_boards',
			'manage_attachments',
			'manage_smileys',
			'edit_news',
			'access_mod_center',
			'moderate_forum',
			'issue_warning',
			'manage_membergroups',
			'manage_permissions',
			'manage_bans',
			'move_own',
			'modify_replies',
			'send_mail',
			'approve_posts',
			'postby_email',
			'approve_emails',
			'like_posts',
		);

		call_integration_hook('integrate_load_illegal_guest_permissions');
	}

	/**
	 * This function updates the permissions of any groups based on the given groups.
	 *
	 * @param mixed[]|int $parents (array or int) group or groups whose children are to be updated
	 * @param int|null $profile = null an int or null for the customized profile, if any
	 */
	public static function updateChild($parents, $profile = null)
	{
		$db = database();

		// All the parent groups to sort out.
		if (!is_array($parents))
			$parents = array($parents);

		// Find all the children of this group.
		$request = $db->query('', '
			SELECT id_parent, id_group
			FROM {db_prefix}membergroups
			WHERE id_parent != {int:not_inherited}
				' . (empty($parents) ? '' : 'AND id_parent IN ({array_int:parent_list})'),
			array(
				'parent_list' => $parents,
				'not_inherited' => -2,
			)
		);
		$children = array();
		$parents = array();
		$child_groups = array();
		while ($row = $db->fetch_assoc($request))
		{
			$children[$row['id_parent']][] = $row['id_group'];
			$child_groups[] = $row['id_group'];
			$parents[] = $row['id_parent'];
		}
		$db->free_result($request);

		$parents = array_unique($parents);

		// Not a sausage, or a child?
		if (empty($children))
			return false;

		// First off, are we doing general permissions?
		if ($profile < 1 || $profile === null)
		{
			// Fetch all the parent permissions.
			$request = $db->query('', '
				SELECT id_group, permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:parent_list})',
				array(
					'parent_list' => $parents,
				)
			);
			$permissions = array();
			while ($row = $db->fetch_assoc($request))
			{
				foreach ($children[$row['id_group']] as $child)
				{
					$permissions[] = array('id_group' => (int) $child, 'permission' => $row['permission'], 'add_deny' => $row['add_deny']);
				}
			}
			$db->free_result($request);

			$db->query('', '
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:child_groups})',
				array(
					'child_groups' => $child_groups,
				)
			);

			// Finally insert.
			if (!empty($permissions))
			{
				require_once(SUBSDIR . '/ManagePermissions.subs.php');
				replacePermission($permissions);
			}
		}

		// Then, what about board profiles?
		if ($profile != -1)
		{
			$profileQuery = $profile === null ? '' : ' AND id_profile = {int:current_profile}';

			// Again, get all the parent permissions.
			$request = $db->query('', '
				SELECT id_profile, id_group, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_group IN ({array_int:parent_groups})
					' . $profileQuery,
				array(
					'parent_groups' => $parents,
					'current_profile' => $profile !== null && $profile ? $profile : 1,
				)
			);
			$permissions = array();
			while ($row = $db->fetch_assoc($request))
				foreach ($children[$row['id_group']] as $child)
					$permissions[] = array($child, $row['id_profile'], $row['permission'], $row['add_deny']);
			$db->free_result($request);

			$db->query('', '
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group IN ({array_int:child_groups})
					' . $profileQuery,
				array(
					'child_groups' => $child_groups,
					'current_profile' => $profile !== null && $profile ? $profile : 1,
				)
			);

			// Do the insert.
			if (!empty($permissions))
			{
				$db->insert('insert',
					'{db_prefix}board_permissions',
					array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$permissions,
					array('id_group', 'id_profile', 'permission')
				);
			}
		}
	}
}