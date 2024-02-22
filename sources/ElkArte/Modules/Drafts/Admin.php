<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Drafts;

use ElkArte\AdminController\ManageDraftsModule;
use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Drafts\Admin
 *
 * Events and functions for post based drafts
 */
class Admin extends AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		return [
			['addMenu', [Admin::class, 'addMenu'], []],
			['addSearch', [Admin::class, 'addSearch'], []],
		];
	}

	/**
	 * Used to add the Drafts entry to the admin menu.
	 *
	 * @param array $admin_areas The admin menu array
	 */
	public function addMenu(&$admin_areas)
	{
		global $txt;

		$admin_areas['layout']['areas']['managedrafts'] = [
			'label' => $txt['manage_drafts'],
			'controller' => ManageDraftsModule::class,
			'function' => 'action_index',
			'class' => 'i-bookmark i-admin',
			'permission' => ['admin_forum'],
			'enabled' => featureEnabled('dr'),
		];
	}

	/**
	 * Used to add the Drafts entry to the admin search.
	 *
	 * @param string[] $language_files
	 * @param string[] $include_files
	 * @param array $settings_search
	 */
	public function addSearch(&$language_files, &$include_files, &$settings_search)
	{
		$language_files[] = 'Drafts';
		$settings_search[] = ['settings_search', 'area=managedrafts', ManageDraftsModule::class];
	}
}
