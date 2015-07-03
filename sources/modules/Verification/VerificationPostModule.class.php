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

class Verification_Post_Module
{
	public static function hooks()
	{
		return array_merge($return, array(
			array('post_errors', array('Verification_Post_Module', 'post_errors'), array('_post_errors')),
		));
	}

	public function post_errors($_post_errors)
	{
		global $context, $user_info, $modSettings;

		// Do we need to show the visual verification image?
		$context['require_verification'] = !$user_info['is_mod'] && !$user_info['is_admin'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
		if ($context['require_verification'])
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
			$verificationOptions = array(
				'id' => 'post',
			);
			$context['require_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];

			// If they came from quick reply, and have to enter verification details, give them some notice.
			if (!empty($_REQUEST['from_qr']) && $context['require_verification'] !== false)
				$_post_errors->addError('need_qr_verification');
		}
	}
}