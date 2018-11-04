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

/**
 * Wrapper for VerificationControls_Integrate::create_control_verification
 *
 * @deprecated since 2.0; use VerificationControls_Integrate
 *
 * @param mixed[] $verificationOptions
 * @param bool    $do_test = false If we are validating the input to a verification control
 *
 * @return array|bool
 * @throws Elk_Exception no_access
 */
function create_control_verification(&$verificationOptions, $do_test = false)
{
	Errors::instance()->log_deprecated('create_control_verification()', 'VerificationControls_Integrate::create()');
	return VerificationControls_Integrate::create($verificationOptions, $do_test);
}
