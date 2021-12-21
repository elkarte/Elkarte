<?php

/**
 * Integration system for drafts into Profile controller
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

use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;
use ElkArte\Themes\ThemeLoader;

/**
 * Class \ElkArte\Modules\Drafts\Profile
 *
 * Handles adding the show drafts area to the user profile
 */
class Profile extends AbstractModule
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			add_integration_function('integrate_profile_areas', '\\ElkArte\\Modules\\Drafts\\Profile::integrate_profile_areas', '', false);

			return array(
				array('pre_load', array('\\ElkArte\\Modules\\Drafts\\Profile', 'pre_load'), array('post_errors')),
			);
		}
		else
		{
			return array();
		}
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
				'controller' => '\\ElkArte\\Controller\\Draft',
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
		{
			ThemeLoader::loadLanguageFile('Drafts');
		}
	}
}
