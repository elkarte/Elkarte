<?php

/**
 *
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Class Verification_Post_Module
 *
 * Adds Visual Verification controls to the Post page for those that need it.
 */
class Verification_Post_Module extends ElkArte\sources\modules\Abstract_Module
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager)
	{
		global $user_info, $modSettings;

		// Using controls and this users is the lucky recipient of them?
		if (!$user_info['is_admin'] && !$user_info['is_moderator'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1)))
		{
			return array(
				array('post_errors', array('Verification_Post_Module', 'post_errors'), array('_post_errors')),
				array('prepare_save_post', array('Verification_Post_Module', 'prepare_save_post'), array('_post_errors')),
			);
		}
		else
			return array();
	}

	/**
	 * Prepare $context for the post page.
	 *
	 * @param \ErrorContext $_post_errors
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function post_errors($_post_errors)
	{
		global $context;

		// Do we need to show the visual verification image?
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = \ElkArte\VerificationControls\VerificationControlsIntegrate::create($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];

		// If they came from quick reply, and have to enter verification details, give them some notice.
		if (!empty($_REQUEST['from_qr']) && $context['require_verification'] !== false)
			$_post_errors->addError('need_qr_verification');
	}

	/**
	 * Checks the user passed the verifications on the post page.
	 *
	 * @param \ErrorContext $_post_errors
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function prepare_save_post($_post_errors)
	{
		global $context;

		// Wrong verification code?
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = \ElkArte\VerificationControls\VerificationControlsIntegrate::create($verificationOptions, true);

		if (is_array($context['require_verification']))
		{
			foreach ($context['require_verification'] as $verification_error)
				$_post_errors->addError($verification_error);
		}
	}
}
