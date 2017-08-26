<?php

/**
 * Functions to support the permissions controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 */
class Permissions
{
	private $db;

	/**
	 * @var array
	 */
	protected $reserved_permissions = array(
		'admin_forum',
		'manage_membergroups',
		'manage_permissions',
	);

	/**
	 * @var string[]
	 */
	private $illegal_permissions = array();

	/**
	 * @var array
	 */
	protected $illegal_guest_permissions = array(
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

	/**
	 * Load a few illegal permissions into context.
	 *
	 * Calls hook: integrate_load_illegal_permissions
	 */
	public function __construct()
	{
		$this->db = database();
		$this->loadIllegal();
		$this->loadIllegalGuest();
	}

	/**
	 * @return string[]
	 */
	public function getIllegalPermissions()
	{
		return $this->illegal_permissions;
	}

	/**
	 * @return array
	 */
	public function getIllegalGuestPermissions()
	{
		return $this->illegal_guest_permissions;
	}

	/**
	 * @return array
	 */
	public function loadIllegal()
	{
		global $context;

		$illegal_permissions = array();
		foreach ($this->reserved_permissions as $illegal_permission)
		{
			if (!allowedTo($illegal_permission))
			{
				$illegal_permissions[] = $illegal_permission;
			}
		}
		$context['illegal_permissions'] = $illegal_permissions;
		call_integration_hook('integrate_load_illegal_permissions');

		$this->illegal_permissions = $illegal_permissions;
	}

	/**
	 * Loads those permissions guests cannot have, into context.
	 *
	 * @return array
	 */
	public function loadIllegalGuest()
	{
		global $context;

		$context['non_guest_permissions'] = $this->illegal_guest_permissions;
		call_integration_hook('integrate_load_illegal_guest_permissions');

		return $this->illegal_guest_permissions;
	}

	/**
	 * Deletes permissions.
	 *
	 * @param string[] $permissions
	 * @param string[] $where
	 * @param mixed[]  $where_parameters = array() or values used in the where statement
	 */
	public function deletePermissions($permissions, $where = array(), $where_parameters = array())
	{
		if (count($this->illegal_permissions) > 0)
		{
			$where[] = 'permission NOT IN ({array_string:illegal_permissions})';
			$where_parameters['illegal_permissions'] = $this->illegal_permissions;
		}
		$where[] = 'permission IN ({array_string:permissions})';
		$where_parameters['permissions'] = $permissions;

		$this->db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE ' . implode(' AND ', $where),
			$where_parameters
		);
	}

	/**
	 * This function updates the permissions of any groups based on the given groups.
	 *
	 * @param mixed[]|int $parents (array or int) group or groups whose children are to be updated
	 * @param int|null    $profile = null an int or null for the customized profile, if any
	 */
	public function updateChild($parents, $profile = null)
	{
		// All the parent groups to sort out.
		if (!is_array($parents))
		{
			$parents = array($parents);
		}

		// Find all the children of this group.
		$request = $this->db->query('', '
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
		while ($row = $this->db->fetch_assoc($request))
		{
			$children[$row['id_parent']][] = $row['id_group'];
			$child_groups[] = $row['id_group'];
			$parents[] = $row['id_parent'];
		}
		$this->db->free_result($request);

		$parents = array_unique($parents);

		// Not a sausage, or a child?
		if (empty($children))
		{
			return false;
		}

		// Need functions that modify permissions...
		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		// First off, are we doing general permissions?
		if ($profile < 1 || $profile === null)
		{
			// Fetch all the parent permissions.
			$request = $this->db->query('', '
				SELECT id_group, permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:parent_list})',
				array(
					'parent_list' => $parents,
				)
			);
			$permissions = array();
			while ($row = $this->db->fetch_assoc($request))
			{
				foreach ($children[$row['id_group']] as $child)
				{
					$permissions[] = array(
						'id_group' => (int) $child,
						'permission' => $row['permission'],
						'add_deny' => $row['add_deny'],
					);
				}
			}
			$this->db->free_result($request);

			$this->db->query('', '
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:child_groups})',
				array(
					'child_groups' => $child_groups,
				)
			);

			// Finally insert.
			if (!empty($permissions))
			{
				replacePermission($permissions);
			}
		}

		// Then, what about board profiles?
		if ($profile != -1)
		{
			$profileQuery = $profile === null ? '' : ' AND id_profile = {int:current_profile}';

			// Again, get all the parent permissions.
			$request = $this->db->query('', '
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
			while ($row = $this->db->fetch_assoc($request))
			{
				foreach ($children[$row['id_group']] as $child)
				{
					$permissions[] = array($row['permission'], $child, $row['add_deny'], $row['id_profile']);
				}
			}
			$this->db->free_result($request);

			$this->db->query('', '
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
				replaceBoardPermission($permissions);
			}
		}
	}
}
