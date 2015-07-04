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

class Verification_Display_Module
{
	public static function hooks()
	{
		return array(
			array('topicinfo', array('Verification_Display_Module', 'topicinfo'), array()),
		);
	}

	public function topicinfo($_post_errors)
	{
		global $context;

		// Do we need to show the visual verification image?
		$context['require_verification'] = $this->_userNeedVerification();
		if ($context['require_verification'])
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
			$verificationOptions = array(
				'id' => 'post',
			);
			$context['require_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}
	}

	protected function _userNeedVerification()
	{
		global $user_info, $modSettings;

		return !$user_info['is_admin'] && !$user_info['is_mod'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
	}
}