<?php

/**
 * This file contains the integration functions that start the
 * verification controls.
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

/**
 * Class VerificationControls_Integrate
 *
 * - Injects the verification controls settings in the appropriate admin page.
 *
 * @package ElkArte
 */
class VerificationControls_Integrate
{
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

	/**
	 * Appends the configurations to $config_vars
	 */
	public static function integrate_spam_settings(&$config_vars)
	{
		VerificationControls::discoverControls($config_vars);
	}
}