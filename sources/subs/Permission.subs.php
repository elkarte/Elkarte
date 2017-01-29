<?php

/**
 * Functions to support the permissions controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
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
 * Dummy class for compatibility sake
 */
class InlinePermissions_Form extends ElkArte\sources\subs\SettingsFormAdapter\InlinePermissions
{
	/**
	 * Save the permissions of a form containing inline permissions.
	 *
	 * @param string[] $permissions
	 * @deprecated since 1.1
	 */
	public static function save_inline_permissions($permissions)
	{
		$permissionsForm = new self;
		$permissionsForm->setPermissions($permissions);

		$permissionsForm->save();

		return null;
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variables for each permission.
	 * This function is used by several settings screens
	 * to set specific permissions.
	 *
	 * @param string[] $permissions
	 * @param int[]    $excluded_groups = array()
	 * @deprecated since 1.1
	 *
	 * @uses ManagePermissions template.
	 */
	public static function init_inline_permissions($permissions, $excluded_groups = array())
	{
		$permissionsForm = new self;
		$permissionsForm->setExcludedGroups($excluded_groups);
		$permissionsForm->setPermissions($permissions);

		$permissionsForm->prepare();

		return null;
	}
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
