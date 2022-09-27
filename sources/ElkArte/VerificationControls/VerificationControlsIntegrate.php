<?php

/**
 * This file contains the integration functions that start the verification controls.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\VerificationControls;

use ElkArte\Errors\ErrorContext;
use ElkArte\Sessions\SessionIndex;

/**
 * Class VerificationControlsIntegrate
 *
 * - Injects the verification controls settings in the appropriate admin page.
 *
 * @package ElkArte
 */
class VerificationControlsIntegrate
{
	/**
	 * Register ACP config hooks for setting values
	 *
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return [
			['integrate_spam_settings', '\\ElkArte\\VerificationControls\\VerificationControlsIntegrate::integrate_spam_settings'],
		];
	}

	/**
	 * Appends the configurations to $config_vars
	 */
	public static function integrate_spam_settings(&$config_vars)
	{
		VerificationControls::discoverControls($config_vars);
	}

	/**
	 * Create a anti-bot verification control
	 *
	 * @param array $verificationOptions
	 * @param bool $do_test = false If we are validating the input to a verification control
	 *
	 * @return array|bool
	 * @throws \ElkArte\Exceptions\Exception no_access
	 */
	public static function create(&$verificationOptions, $do_test = false)
	{
		global $context, $modSettings;

		// We need to remember this because when failing the page is reloaded and the
		// code must remain the same (unless it has to change)
		static $all_instances = [];

		// Always have an ID.
		assert(isset($verificationOptions['id']));

		$sessionVal = new SessionIndex($verificationOptions['id'] . '_vv');

		$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);
		$max_errors = $verificationOptions['max_errors'] ?? 3;
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

		if (!$isNew && !$do_test)
		{
			return true;
		}

		$verification_errors = ErrorContext::context($verificationOptions['id']);

		// Start with any testing.
		if ($do_test)
		{
			$force_refresh = $instances->test($verification_errors, $max_errors);
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
			$error_codes = [];
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
		if ($do_test)
		{
			$sessionVal['did_pass'] = true;
		}

		// Say that everything went well chaps.
		return true;
	}
}
