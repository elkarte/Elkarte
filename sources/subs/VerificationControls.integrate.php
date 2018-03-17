<?php

/**
 * This file contains those functions specific to the various verification controls
 * used to challenge users, and hopefully robots as well.
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

use ElkArte\sources\subs\VerificationControls;

class VerificationControls_Integrate
{
	protected $_known_verifications = array();
	protected $_verification_options = array();
	protected $_verification_instances = array();

	/**
	 * Register ACP config hooks for setting values
	 *
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return array(
			array('integrate_spam_settings', 'VerificationControls_Integrate::integrate_spam_settings'),
		);
	}

	public static function integrate_spam_settings(&$config_vars)
	{
		VerificationControls::discoverControls($config_vars);
	}
}