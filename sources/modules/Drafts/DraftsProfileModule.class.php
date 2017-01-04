<?php

/**
 * Integration system for drafts into Profile controller
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
 * Class Drafts_Profile_Module
 *
 * Handles adding the show drafts area to the user profile
 */
class Drafts_Profile_Module extends ElkArte\sources\modules\Abstract_Module
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			add_integration_function('integrate_profile_areas', 'Drafts_Profile_Module::integrate_profile_areas', '', false);
			return array(
				array('pre_load', array('Drafts_Profile_Module', 'pre_load'), array('post_errors')),
			);
		}
		else
			return array();
	}

	/**
	 * If drafts are enabled, provides an interface to display them for the user
	 *
	 * @param array $profile_areas
	 */
	public static function integrate_profile_areas(&$profile_areas)
	{
		global $txt, $context;

		$profile_areas['info']['areas'] = elk_array_insert($profile_areas['info']['areas'], 'showposts', array(
			'showdrafts' => array(
				'label' => $txt['drafts_show'],
				'controller' => 'Draft_Controller',
				'function' => 'action_showProfileDrafts',
				'enabled' => $context['user']['is_owner'],
				'permission' => array(
					'own' => 'profile_view_own',
					'any' => array(),
				),
			)), 'after');
	}

	/**
	 * Load in the draft language stings when needed.
	 *
	 * @param array $post_errors
	 */
	public function pre_load($post_errors)
	{
		if (empty($post_errors))
			loadLanguage('Drafts');
	}
}