<?php

/**
 * This file contains several functions for retrieving and manipulating calendar events, birthdays and holidays.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Class Drafts_Admin_Module
 *
 * Events and functions for post based drafts
 */
class Drafts_Admin_Module extends ElkArte\sources\modules\Abstract_Module
{
	/**
	 * {@inheritdoc}
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		return array(
			array('addMenu', array('Drafts_Admin_Module', 'addMenu'), array()),
			array('addSearch', array('Drafts_Admin_Module', 'addSearch'), array()),
		);
	}

	/**
	 * Used to add the Drafts entry to the admin menu.
	 *
	 * @param mixed[] $admin_areas The admin menu array
	 */
	public function addMenu(&$admin_areas)
	{
		global $txt, $context;

		$admin_areas['layout']['areas']['managedrafts'] = array(
			'label' => $txt['manage_drafts'],
			'controller' => 'ManageDraftsModule_Controller',
			'function' => 'action_index',
			'icon' => 'transparent.png',
			'class' => 'admin_img_logs',
			'permission' => array('admin_forum'),
			'enabled' => in_array('dr', $context['admin_features']),
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
		$settings_search[] = array('settings_search', 'area=managedrafts', 'ManageDraftsModule_Controller');
	}
}
