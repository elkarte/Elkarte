<?php

/**
 * A class to handle the basic drafts integrations.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Integration trick.
 */
class DraftsIntegrate
{
	/**
	 * Registers hooks as needed for the drafts function to work
	 *
	 * @return array
	 */
	public static function register(): array
	{
		// $hook, $function, $file
		return [];
	}

	/**
	 * Returns the config settings form the drafts module
	 *
	 * @return array
	 */
	public static function settingsRegister(): array
	{
		// $hook, $function, $file
		return [
			['integrate_load_permissions', '\\ElkArte\\AdminController\\ManageDraftsModule::integrate_load_permissions'],
			['integrate_topics_maintenance', '\\ElkArte\\AdminController\\ManageDraftsModule::integrate_topics_maintenance'],
			['integrate_sa_manage_maintenance', '\\ElkArte\\AdminController\\ManageDraftsModule::integrate_sa_manage_maintenance'],
			['integrate_delete_members', '\\ElkArte\\AdminController\\ManageDraftsModule::integrate_delete_members'],
			['integrate_load_illegal_guest_permissions', '\\ElkArte\\AdminController\\ManageDraftsModule::integrate_load_illegal_guest_permissions'],
		];
	}
}
