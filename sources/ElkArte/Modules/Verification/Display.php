<?php

/**
 *
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

namespace ElkArte\Modules\Verification;

use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;
use ElkArte\User;
use ElkArte\VerificationControls\VerificationControlsIntegrate;

/**
 * Class \ElkArte\Modules\Verification\Display
 *
 * Adding Visual Verification event to Quick Reply area (display.controller)
 */
class Display extends AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (User::$info->is_admin === false && User::$info->is_moderator === false && !empty($modSettings['posts_require_captcha']) && (User::$info->posts < $modSettings['posts_require_captcha'] || (User::$info->is_guest && $modSettings['posts_require_captcha'] == -1)))
		{
			return array(
				array('topicinfo', array('\\ElkArte\\Modules\\Verification\\Display', 'topicinfo'), array()),
			);
		}

		return array();
	}

	/**
	 * Prepare $context for the quick reply.
	 */
	public function topicinfo()
	{
		global $context;

		// Do we need to show the visual verification image?
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
}
