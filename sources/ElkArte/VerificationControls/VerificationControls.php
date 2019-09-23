<?php

/**
 * This file contains those functions specific to the various verification controls
 * used to challenge users, and hopefully robots as well.
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

namespace ElkArte\VerificationControls;

/**
 * Class VerificationControls
 *
 * - Takes care of create the verification controls, do the tests, etc.
 * - Assumes the controls are available under /sources/ElkArte/VerificationControls/VerificationControl
 *   and implement \ElkArte\VerificationControl\ControlInterface
 * - It also provides a static method to load the available verifications (admin)
 *
 * @package ElkArte
 */
class VerificationControls
{
	protected $_known_verifications = array();
	protected $_verification_options = array();
	protected $_verification_instances = array();
	protected $_sessionVal = null;

	/**
	 * Obviously the entry point of verification
	 *
	 * @param \ElkArte\Sessions\SessionIndex $sessionVal
	 * @param mixed[] $settings Basically $modSettings
	 * @param mixed[] $verificationOptions
	 * @param bool $isNew If the control was initialized before
	 * @param bool $force_refresh If the controls should be re-initialized
	 */
	public function __construct(\ElkArte\Sessions\SessionIndex $sessionVal, $settings = array(), $verificationOptions = array(), $isNew = false, $force_refresh = false)
	{
		if (empty($settings['known_verifications']))
		{
			$settings['known_verifications'] = self::discoverControls();
		}

		$this->_known_verifications = json_decode($settings['known_verifications'], true);
		$this->_verification_options = $verificationOptions;
		$this->_verification_options['render'] = false;
		$this->_sessionVal = $sessionVal;

		foreach ($this->_known_verifications as $verification)
		{
			$class_name = '\\ElkArte\\VerificationControls\\VerificationControl\\' . $verification;
			$current_instance = new $class_name($verificationOptions);

			// If there is anything to show, otherwise forget it
			if ($current_instance->showVerification($this->_sessionVal, $isNew, $force_refresh))
			{
				$this->_verification_instances[$verification] = $current_instance;
			}
		}
	}

	/**
	 * This method returns the verification controls found in the file system
	 *
	 * @param mixed[] $config_vars
	 * @return false|string
	 */
	public static function discoverControls(&$config_vars = null)
	{
		$known_verifications = self::loadFSControls();
		$working_verifications = array();

		foreach ($known_verifications as $verification)
		{
			$class = '\\ElkArte\\VerificationControls\\VerificationControl\\' . $verification;

			try
			{
				$obj = new $class(array());
				if ($obj instanceof VerificationControl\ControlInterface)
				{
					$new_settings = $obj->settings();
					$working_verifications[] = $verification;

					if ($config_vars !== null && !empty($new_settings) && is_array($new_settings))
					{
						foreach ($new_settings as $new_setting)
						{
							$config_vars[] = $new_setting;
						}
					}
				}
			}
			catch (\Error $e)
			{
				// track the error?
			}
		}
		$to_update = json_encode($working_verifications);
		updateSettings(array('known_verifications' => $to_update));

		return $to_update;
	}

	/**
	 * Simple function that find and returns all the verification controls known to Elk
	 */
	protected static function loadFSControls()
	{
		$glob = new \GlobIterator(SOURCEDIR . '/ElkArte/VerificationControls/VerificationControl/*.php', \FilesystemIterator::SKIP_DOTS);
		$foundControls = array();

		foreach ($glob as $file)
		{
			if (strpos($file->getBasename('.php'), 'Interface') === false)
			{
				$foundControls[] = $file->getBasename('.php');
			}
		}

		// Need GD for CAPTCHA images
		if (!in_array('gd', get_loaded_extensions()))
		{
			array_unshift($foundControls, 'captcha');
		}

		// Let integration add some more controls
		// @deprecated since 2.0 dev - remove before final
		call_integration_hook('integrate_control_verification', array(&$foundControls));

		return $foundControls;
	}

	/**
	 * Runs the tests and populates the errors (if any)
	 *
	 * @param \ElkArte\Errors\ErrorContext $verification_errors
	 * @param int $max_errors
	 * @return bool
	 */
	public function test($verification_errors, $max_errors)
	{
		$increase_error_count = false;
		$force_refresh = false;

		// This cannot happen!
		if (!isset($this->_sessionVal['count']))
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		foreach ($this->_verification_instances as $instance)
		{
			$outcome = $instance->doTest($this->_sessionVal);
			if ($outcome !== true)
			{
				$increase_error_count = true;
				$verification_errors->addError($outcome);
			}
		}

		// Any errors means we refresh potentially.
		if ($increase_error_count)
		{
			if (empty($this->_sessionVal['errors']))
			{
				$this->_sessionVal['errors'] = 0;
			}
			// Too many errors?
			elseif ($this->_sessionVal['errors'] > $max_errors)
			{
				$force_refresh = true;
			}

			// Keep a track of these.
			$this->_sessionVal['errors']++;
		}

		return $force_refresh;
	}

	/**
	 * Instantiate the verification controls
	 *
	 * @param bool $force_refresh If the controls should be re-initialized
	 * @return mixed[]
	 */
	public function create($force_refresh = false)
	{

		foreach ($this->_verification_instances as $test => $instance)
		{
			$instance->createTest($this->_sessionVal, $force_refresh);
			$this->_verification_options['test'][$test] = $instance->prepareContext($this->_sessionVal);

			if ($instance->hasVisibleTemplate())
			{
				$this->_verification_options['render'] = true;
			}
		}

		return array(
			'id' => $this->_verification_options['id'],
			'max_errors' => isset($this->_verification_options['max_errors']) ? $this->_verification_options['max_errors'] : 3,
			'render' => $this->_verification_options['render'],
			'test' => $this->_verification_options['test']
		);
	}

	/**
	 * Is there any control to show?
	 */
	public function hasControls()
	{
		return count($this->_verification_instances) !== 0;
	}
}
