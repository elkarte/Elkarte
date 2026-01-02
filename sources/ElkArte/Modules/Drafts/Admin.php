<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events,
 * birthdays, and holidays.
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

namespace ElkArte\Modules\Drafts;

use ElkArte\AdminController\ManageDraftsModule;
use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Drafts\Admin
 *
 * Events and functions for post-based drafts
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
	 * It defines a subsection in the admin menu post and topic section for draft settings.
	 * DraftsIntegration has the hook to integrate_sa_manage_posts so that ManagePosts
	 * controller knows how to dispatch to the draft settings section.
	 *
	 * @param array $admin_areas The admin menu array
	 */
	public function addMenu(&$admin_areas): void
	{
		global $txt;

		$admin_areas['forum']['areas']['postsettings']['subsections']['drafts'] = [
			$txt['managedrafts_settings'],
			'enabled' => featureEnabled('dr'),
		];

		uksort($admin_areas['forum']['areas']['postsettings']['subsections'], 'strnatcasecmp');
	}

	/**
	 * Used to add the Drafts entry to the admin search.
	 *
	 * @param string[] $language_files
	 * @param string[] $include_files
	 * @param array $settings_search
	 */
	public function addSearch(&$language_files, &$include_files, &$settings_search): void
	{
		$language_files[] = 'Drafts';
		$settings_search[] = ['settings_search', 'area=managedrafts', ManageDraftsModule::class];
	}
}
