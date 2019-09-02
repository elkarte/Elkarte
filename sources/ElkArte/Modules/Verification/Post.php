<?php

/**
 *
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Verification;

use ElkArte\User;

/**
 * Class \ElkArte\Modules\Verification\Post
 *
 * Adds Visual Verification controls to the Post page for those that need it.
 */
class Post extends \ElkArte\Modules\AbstractModule
{
	/**
	 * {@inheritDoc}
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager)
	{
		global $modSettings;

		// Using controls and this users is the lucky recipient of them?
		if (User::$info->is_admin === false && User::$info->is_moderator === false && !empty($modSettings['posts_require_captcha']) && (User::$info->posts < $modSettings['posts_require_captcha'] || (User::$info->is_guest && $modSettings['posts_require_captcha'] == -1)))
		{
			return array(
				array('post_errors', array('\\ElkArte\\Modules\\Verification\\Post', 'post_errors'), array('_post_errors')),
				array('prepare_save_post', array('\\ElkArte\\Modules\\Verification\\Post', 'prepare_save_post'), array('_post_errors')),
			);
		}
		else
			return array();
	}

	/**
	 * Prepare $context for the post page.
	 *
	 * @param \ElkArte\Errors\ErrorContext $_post_errors
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
	 * @param \ElkArte\Errors\ErrorContext $_post_errors
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
