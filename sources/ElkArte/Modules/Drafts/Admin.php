<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Drafts;

/**
 * Class \ElkArte\Modules\Drafts\Admin
 *
 * Events and functions for post based drafts
 */
class Admin extends \ElkArte\Modules\AbstractModule
{
	/**
	 * {@inheritdoc}
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager)
	{
		return array(
			array('addMenu', array('\\ElkArte\\Modules\\Drafts\\Admin', 'addMenu'), array()),
			array('addSearch', array('\\ElkArte\\Modules\\Drafts\\Admin', 'addSearch'), array()),
		);
	}

	/**
	 * Used to add the Drafts entry to the admin menu.
	 *
	 * @param mixed[] $admin_areas The admin menu array
	 */
	public function addMenu(&$admin_areas)
	{
		global $txt;

		$admin_areas['layout']['areas']['managedrafts'] = array(
			'label' => $txt['manage_drafts'],
			'controller' => '\\ElkArte\\AdminController\\ManageDraftsModule',
			'function' => 'action_index',
			'icon' => 'transparent.png',
			'class' => 'admin_img_logs',
			'permission' => array('admin_forum'),
			'enabled' => featureEnabled('dr'),
		);
	}

	/**
	 * Used to add the Drafts entry to the admin search.
	 *
	 * @param string[] $language_files
	 * @param string[] $include_files
	 * @param mixed[] $settings_search
	 */
	public function addSearch(&$language_files, &$include_files, &$settings_search)
	{
		$language_files[] = 'Drafts';
		$include_files[] = 'ManageDraftsModule.controller';
		$settings_search[] = array('settings_search', 'area=managedrafts', '\\ElkArte\\AdminController\\ManageDraftsModule');
	}
}
