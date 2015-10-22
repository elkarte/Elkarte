<?php

/**
 *
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

class Verification_Register_Module implements ElkArte\sources\modules\Module_Interface
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['reg_verification']))
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');

			return array(
				array('prepare_context', array('Verification_Register_Module', 'prepare_context'), array('current_step')),
				array('before_complete_register', array('Verification_Register_Module', 'before_complete_register'), array('reg_errors')),
				array('verify_contact', array('Verification_Register_Module', 'verify_contact'), array()),
				array('setup_contact', array('Verification_Register_Module', 'setup_contact'), array()),
			);
		}
		else
			return array();
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
			$verificationOptions = array(
				'id' => 'register',
			);
			$context['visual_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}
		// Otherwise we have nothing to show.
		else
			$context['visual_verification'] = false;
	}

	/**
	 * Checks the user passed the verifications on the registration form.
	 *
	 * @param Error_Context $reg_errors Errors object from the registration controller
	 */
	public function before_complete_register($reg_errors)
	{
		// Check whether the visual verification code was entered correctly.
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['visual_verification']))
		{
			foreach ($context['visual_verification'] as $error)
				$reg_errors->addError($error);
		}
	}

	/**
	 * Checks the user passed the verifications on the contact page.
	 */
	public function verify_contact()
	{
		global $context, $txt;

		// How about any verification errors
		$verificationOptions = array(
			'id' => 'contactform',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['require_verification']))
		{
			foreach ($context['require_verification'] as $error)
				$context['errors'][] = $txt['error_' . $error];
		}
	}

	/**
	 * Prepare $context for the contact page.
	 */
	public function setup_contact()
	{
		global $context;

		$verificationOptions = array(
			'id' => 'contactform',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
}