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
	public static function hooks(\Event_Manager $eventsManager)
	{
		return array(
			array('prepare_context', array('Verification_Register_Module', 'prepare_context'), array('current_step')),
			array('before_complete_register', array('Verification_Register_Module', 'before_complete_register'), array('reg_errors')),
			array('verify_contact', array('Verification_Register_Module', 'verify_contact'), array()),
			array('setup_contact', array('Verification_Register_Module', 'setup_contact'), array()),
		);
	}

	public function prepare_context($current_step)
	{
		global $context;

		// Generate a visual verification code to make sure the user is no bot.
		if ($this->_userNeedVerification($current_step))
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
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

	public function before_complete_register($reg_errors)
	{
		// Check whether the visual verification code was entered correctly.
		if ($this->_userNeedVerification(2))
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
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
	}

	protected function verify_contact()
	{
		global $context;

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

	public function setup_contact()
	{
		global $context;

		require_once(SUBSDIR . '/VerificationControls.class.php');
		$verificationOptions = array(
			'id' => 'contactform',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	protected function _userNeedVerification($current_step)
	{
		global $user_info, $modSettings;

		return !empty($modSettings['reg_verification']) && $current_step > 1;
	}
}