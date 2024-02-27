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

use ElkArte\Controller\Draft;
use ElkArte\EventManager;
use ElkArte\Languages\Txt;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Drafts\Profile
 *
 * Handles adding the show drafts area to the user profile
 */
class Profile extends AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			add_integration_function('integrate_profile_areas', '\\ElkArte\\Modules\\Drafts\\Profile::integrate_profile_areas', '', false);

			return [
				['pre_load', [Profile::class, 'pre_load'], ['post_errors']],
			];
		}

		return [];
	}

	/**
	 * If drafts are enabled, provides an interface to display them for the user
	 *
	 * @param \ElkArte\Menu\Menu $profile_areas
	 */
	public static function integrate_profile_areas($profile_areas)
	{
		global $txt, $context;

		$new_areas['info'] = [
			'showdrafts' => [
				'label' => $txt['drafts_show'],
				'controller' => Draft::class,
				'function' => 'action_showProfileDrafts',
				'enabled' => $context['user']['is_owner'],
				'permission' => [
					'own' => 'profile_view_own',
					'any' => [],
				],
			]
		];

		return $profile_areas->insertSection($new_areas, 'showposts');
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
			Txt::load('Drafts');
		}
	}
}
