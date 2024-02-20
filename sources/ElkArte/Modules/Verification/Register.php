<?php

/**
 * Adds Visual Verification controls to the Registration page
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
use ElkArte\VerificationControls\VerificationControlsIntegrate;

/**
 * Class \ElkArte\Modules\Verification\Register
 *
 * Adds Visual Verification controls to the Registration page.
 */
class Register extends AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['reg_verification']))
		{
			return [
				['prepare_context', [Register::class, 'prepare_context'], ['current_step']],
				['before_complete_register', [Register::class, 'before_complete_register'], ['reg_errors']],
				['verify_contact', [Register::class, 'verify_contact'], []],
				['setup_contact', [Register::class, 'setup_contact'], []],
			];
		}

		return [];
	}

	/**
	 * Prepare $context for the registration form.
	 *
	 * @param int $current_step current step of the registration process
	 */
	public function prepare_context($current_step)
	{
		global $context;

		// Generate a visual verification code to make sure the user is no bot.
		if ($current_step > 1)
		{
			$verificationOptions = [
				'id' => 'register',
			];
			$context['visual_verification'] = VerificationControlsIntegrate::create($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}
		// Otherwise we have nothing to show.
		else
		{
			$context['visual_verification'] = false;
		}
	}

	/**
	 * Checks the user passed the verifications on the registration form.
	 *
	 * @param ErrorContext $reg_errors Errors object from the registration controller
	 */
	public function before_complete_register($reg_errors)
	{
		global $context;

		// Check whether the visual verification code was entered correctly.
		$verificationOptions = [
			'id' => 'register',
		];
		$context['visual_verification'] = VerificationControlsIntegrate::create($verificationOptions, true);

		if (is_array($context['visual_verification']))
		{
			foreach ($context['visual_verification'] as $error)
			{
				$reg_errors->addError($error);
			}
		}
	}

	/**
	 * Checks the user passed the verifications on the contact page.
	 */
	public function verify_contact()
	{
		global $context, $txt;

		// How about any verification errors
		$verificationOptions = [
			'id' => 'contactform',
		];
		$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions, true);

		if (is_array($context['require_verification']))
		{
			foreach ($context['require_verification'] as $error)
			{
				$context['errors'][] = $txt['error_' . $error];
			}
		}
	}

	/**
	 * Prepare $context for the contact page.
	 */
	public function setup_contact()
	{
		global $context;

		$verificationOptions = [
			'id' => 'contactform',
		];
		$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
}
