<?php

/**
 * Functions to support the permissions controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 */

/**
 * Load a few illegal permissions in context.
 * @deprecated since 1.1
 */
function loadIllegalPermissions()
{
	$permissionsObject = new Permissions;

	return $permissionsObject->getIllegalPermissions();
}

/**
 * Loads those permissions guests cannot have, into context.
 * @deprecated since 1.1
 */
function loadIllegalGuestPermissions()
{
	$permissionsObject = new Permissions;

	return $permissionsObject->getIllegalGuestPermissions();
}

/**
 * This function updates the permissions of any groups based on the given groups.
 * @deprecated since 1.1
 *
 * @param mixed[]|int $parents (array or int) group or groups whose children are to be updated
 * @param int|null    $profile = null an int or null for the customized profile, if any
 */
function updateChildPermissions($parents, $profile = null)
{
	$permissionsObject = new Permissions;

	return $permissionsObject->updateChild($parents, $profile);
}

/**
 * Show a collapsible box to set a specific permission.
 * The function is called by templates to show a list of permissions settings.
 * Calls the template function template_inline_permissions().
 *
 * @param string $permission
 * @deprecated since 1.1
 */
function theme_inline_permissions($permission)
{
	template_inline_permissions($permission);
}
