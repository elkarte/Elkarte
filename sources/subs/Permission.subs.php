<?php

/**
 * Functions to support the permissions controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 */

/**
 * Load a few illegal permissions in context.
 * @deprecated since 1.1
 */
function loadIllegalPermissions()
{
	Permissions::loadIllegal();
}

/**
 * Loads those permissions guests cannot have, into context.
 * @deprecated since 1.1
 */
function loadIllegalGuestPermissions()
{
	Permissions::loadIllegalGuest();
}

/**
 * This function updates the permissions of any groups based on the given groups.
 * @deprecated since 1.1
 *
 * @param mixed[]|int $parents (array or int) group or groups whose children are to be updated
 * @param int|null $profile = null an int or null for the customized profile, if any
 */
function updateChildPermissions($parents, $profile = null)
{
	Permissions::updateChild($parents, $profile);
}

/**
 * Dummy class for compatibility sake
 * @deprecated since 1.1
 */
class InlinePermissions_Form extends Inline_Permissions_Form
{
	/**
	 * Save the permissions of a form containing inline permissions.
	 *
	 * @param string[] $permissions
	 */
	public static function save_inline_permissions($permissions)
	{
		return self::save($permissions);
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variables for each permission.
	 * This function is used by several settings screens to set specific permissions.
	 *
	 * @param string[] $permissions
	 * @param int[] $excluded_groups = array()
	 *
	 * @uses ManagePermissions language
	 * @uses ManagePermissions template.
	 */
	public static function init_inline_permissions($permissions, $excluded_groups = array())
	{
		return self::init($permissions, $excluded_groups);
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
