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

use ElkArte\sources\subs\SessionIndex;
use ElkArte\sources\subs\VerificationControls;

/**
 * Create a anti-bot verification control?
 *
 * @param mixed[] $verificationOptions
 * @param bool    $do_test = false If we are validating the input to a verification control
 *
 * @return array|bool
 * @throws Elk_Exception no_access
 */
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $context, $modSettings;

	// We need to remember this because when failing the page is reloaded and the
	// code must remain the same (unless it has to change)
	static $all_instances = array();

	// Always have an ID.
	assert(isset($verificationOptions['id']));

	$sessionVal = new SessionIndex($verificationOptions['id'] . '_vv');

	$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);
	$max_errors = isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3;
	$force_refresh = ((!empty($sessionVal['did_pass']) || empty($sessionVal['count']) || $sessionVal['count'] > $max_errors) && empty($verificationOptions['dont_refresh']));

	if (!isset($all_instances[$verificationOptions['id']]))
	{
		$all_instances[$verificationOptions['id']] = new VerificationControls($sessionVal, $modSettings, $verificationOptions, $isNew, $force_refresh);
	}

	$instances = &$all_instances[$verificationOptions['id']];

	// Is there actually going to be anything?
	if ($instances->hasControls() === false)
	{
		return false;
	}
	elseif (!$isNew && !$do_test)
	{
		return true;
	}

	$verification_errors = ElkArte\Errors\ErrorContext::context($verificationOptions['id']);

	// Start with any testing.
	if ($do_test)
	{
		$force_refresh = $instances->test($verification_errors);
	}

	// Are we refreshing then?
	if ($force_refresh)
	{
		// Assume nothing went before.
		$sessionVal['count'] = 0;
		$sessionVal['errors'] = 0;
		$sessionVal['did_pass'] = false;
	}

	$context['controls']['verification'][$verificationOptions['id']] = $instances->create($force_refresh);

	$sessionVal['count'] = empty($sessionVal['count']) ? 1 : $sessionVal['count'] + 1;

	// Return errors if we have them.
	if ($verification_errors->hasErrors())
	{
		// @todo temporary until the error class is implemented in register
		$error_codes = array();
		foreach ($verification_errors->getErrors() as $errors)
		{
			foreach ($errors as $error)
			{
				$error_codes[] = $error;
			}
		}

		return $error_codes;
	}
	// If we had a test that one, make a note.
	elseif ($do_test)
	{
		$sessionVal['did_pass'] = true;
	}

	// Say that everything went well chaps.
	return true;
}
