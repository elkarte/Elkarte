<?php

/**
 * Adds Visual Verification controls to the PM page for those that need it.
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

use ElkArte\Errors\ErrorContext;
use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;
use ElkArte\User;
use ElkArte\VerificationControls\VerificationControlsIntegrate;

/**
 * Class \ElkArte\Modules\Verification\PersonalMessage
 *
 * Adds Visual Verification controls to the PM page for those that need it.
 */
class PersonalMessage extends AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		// Are controls required?
		if (User::$info->is_admin === false && !empty($modSettings['pm_posts_verification']) && User::$info->posts < $modSettings['pm_posts_verification'])
		{
			// Add the events to call for the verification
			return [
				['prepare_send_context', [PersonalMessage::class, 'prepare_send_context'], []],
				['before_sending', [PersonalMessage::class, 'before_sending'], ['post_errors']],
			];
		}

		return [];
	}

	/**
	 * Prepare $context for the PM page.
	 */
	public function prepare_send_context()
	{
		global $context;

		// Verification control needed for this PM?
		$context['require_verification'] = true;

		$verificationOptions = [
			'id' => 'pm',
		];
		$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	/**
	 * Checks the user passed the verifications on the PM page.
	 *
	 * @param ErrorContext $post_errors
	 */
	public function before_sending($post_errors)
	{
		global $context;

		if (isset($_REQUEST['xml']))
		{
			return;
		}

		// Wrong verification code?
		$verificationOptions = [
			'id' => 'pm',
		];
		$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions, true);

		if (is_array($context['require_verification']))
		{
			foreach ($context['require_verification'] as $error)
			{
				$post_errors->addError($error);
			}
		}
	}
}
