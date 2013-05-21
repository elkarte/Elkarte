<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Load a few illegal permissions in context.
 */
function loadIllegalPermissions()
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
function loadIllegalGuestPermissions()
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
		'profile_server_avatar',
		'profile_upload_avatar',
		'profile_remote_avatar',
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
		'post_draft',
		'post_autosave_draft',
		'postby_email',
		'approve_emails',
	);

	call_integration_hook('integrate_load_illegal_guest_permissions');
}

/**
 * This function updates the permissions of any groups based on the given groups.
 *
 * @param mixed $parents (array or int) group or groups whose children are to be updated
 * @param mixed $profile = null an int or null for the customized profile, if any
 */
function updateChildPermissions($parents, $profile = null)
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
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = array($child, $row['permission'], $row['add_deny']);
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
			$db->insert('insert',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$permissions,
				array('id_group', 'permission')
			);
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

class InlinePermissions_Form
{
	/**
	 * Save the permissions of a form containing inline permissions.
 	 *
	 * @param array $permissions
	 */
	static function save_inline_permissions($permissions)
	{
		global $context;

		$db = database();

		// No permissions? Not a great deal to do here.
		if (!allowedTo('manage_permissions'))
			return;

		// Almighty session check, verify our ways.
		checkSession();
		validateToken('admin-mp');

		// Make sure they can't do certain things,
		// unless they have the right permissions.
		loadIllegalPermissions();

		$insertRows = array();
		foreach ($permissions as $permission)
		{
			if (!isset($_POST[$permission]))
				continue;

			foreach ($_POST[$permission] as $id_group => $value)
			{
				if (in_array($value, array('on', 'deny')) && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
					$insertRows[] = array((int) $id_group, $permission, $value == 'on' ? 1 : 0);
			}
		}

		// Remove the old permissions...
		$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE permission IN ({array_string:permissions})
			' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
				'permissions' => $permissions,
			)
		);

		// ...and replace them with new ones.
		if (!empty($insertRows))
			$db->insert('insert',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$insertRows,
				array('id_group', 'permission')
			);

		// Do a full child update.
		updateChildPermissions(array(), -1);

		// Just in case we cached this.
		updateSettings(array('settings_updated' => time()));
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variables for each permission.
	 * This function is used by several settings screens to set specific permissions.
	 *
	 * @param array $permissions
	 * @param array $excluded_groups = array()
	 *
	 * @uses ManagePermissions language
	 * @uses ManagePermissions template.
	 */
	static function init_inline_permissions($permissions, $excluded_groups = array())
	{
		global $context, $txt, $modSettings;

		$db = database();

		loadLanguage('ManagePermissions');
		loadTemplate('ManagePermissions');
		$context['can_change_permissions'] = allowedTo('manage_permissions');

		// Nothing to initialize here.
		if (!$context['can_change_permissions'])
			return;

		// Load the permission settings for guests
		foreach ($permissions as $permission)
			$context[$permission] = array(
				-1 => array(
					'id' => -1,
					'name' => $txt['membergroups_guests'],
					'is_postgroup' => false,
					'status' => 'off',
				),
				0 => array(
					'id' => 0,
					'name' => $txt['membergroups_members'],
					'is_postgroup' => false,
					'status' => 'off',
				),
			);

		$request = $db->query('', '
			SELECT id_group, CASE WHEN add_deny = {int:denied} THEN {string:deny} ELSE {string:on} END AS status, permission
			FROM {db_prefix}permissions
			WHERE id_group IN (-1, 0)
				AND permission IN ({array_string:permissions})',
			array(
				'denied' => 0,
				'permissions' => $permissions,
				'deny' => 'deny',
				'on' => 'on',
			)
		);
		while ($row = $db->fetch_assoc($request))
			$context[$row['permission']][$row['id_group']]['status'] = $row['status'];
		$db->free_result($request);

		$request = $db->query('', '
			SELECT mg.id_group, mg.group_name, mg.min_posts, IFNULL(p.add_deny, -1) AS status, p.permission
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission IN ({array_string:permissions}))
			WHERE mg.id_group NOT IN (1, 3)
				AND mg.id_parent = {int:not_inherited}' . (empty($modSettings['permission_enable_postgroups']) ? '
				AND mg.min_posts = {int:min_posts}' : '') . '
			ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
			array(
				'not_inherited' => -2,
				'min_posts' => -1,
				'newbie_group' => 4,
				'permissions' => $permissions,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			// Initialize each permission as being 'off' until proven otherwise.
			foreach ($permissions as $permission)
				if (!isset($context[$permission][$row['id_group']]))
					$context[$permission][$row['id_group']] = array(
						'id' => $row['id_group'],
						'name' => $row['group_name'],
						'is_postgroup' => $row['min_posts'] != -1,
						'status' => 'off',
					);

			$context[$row['permission']][$row['id_group']]['status'] = empty($row['status']) ? 'deny' : ($row['status'] == 1 ? 'on' : 'off');
		}
		$db->free_result($request);

		// Some permissions cannot be given to certain groups. Remove the groups.
		foreach ($excluded_groups as $group)
		{
			foreach ($permissions as $permission)
			{
				if (isset($context[$permission][$group]))
					unset($context[$permission][$group]);
			}
		}
	}
}


/**
 * Show a collapsible box to set a specific permission.
 * The function is called by templates to show a list of permissions settings.
 * Calls the template function template_inline_permissions().
 *
 * @param string $permission
 */
function theme_inline_permissions($permission)
{
	global $context;

	$context['current_permission'] = $permission;
	$context['member_groups'] = $context[$permission];

	template_inline_permissions();
}