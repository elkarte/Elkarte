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

namespace ElkArte\sources\subs;

class VerificationControls
{
	protected $_known_verifications = array();
	protected $_verification_options = array();
	protected $_verification_instances = array();
	protected $_sessionVal = null;

	/**
	 * Simple function that find and returns all the verification controls known to Elk
	 */
	protected function loadFSControls()
	{
		$glob = new \GlobIterator(SUBSDIR . '/VerificationControl/*.php', \FilesystemIterator::SKIP_DOTS);
		$foundControls = array();

		foreach ($glob as $file)
		{
			if (strpos($file->getBasename('.php'), 'Interface') === false)
			{
				$foundControls[] = $file->getBasename('.php');
			}
		}

		// Let integration add some more controls
		// @deprecated since 2.0 dev - remove before final
		call_integration_hook('integrate_control_verification', array(&$foundControls));

		return $foundControls;
	}

	public function discoverControls(&$config_vars = null)
	{
		$known_verifications = $this->loadFSControls();
		$working_verifications = array();

		foreach ($known_verifications as $verification)
		{
			$class = 'ElkArte\\sources\\subs\\VerificationControl\\' . $verification;

			try
			{
				$obj = new $class(array());
				if ($obj instanceof \ElkArte\sources\subs\VerificationControl\ControlInterface)
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
			
			}
		}
		$to_update = json_encode($working_verifications);
		updateSettings(array('known_verifications' => $to_update));

		return $to_update;
	}

	public function __construct($sessionVal, $settings = array(), $verificationOptions = array(), $isNew = false, $force_refresh = false)
	{
		if (empty($settings['known_verifications']))
		{
			$settings['known_verifications'] = $this->discoverControls();
		}
		$this->_known_verifications = json_decode($settings['known_verifications']);
		$this->_verification_options = $verificationOptions;
		$this->_verification_options['render'] = false;
		$this->_sessionVal = $sessionVal;

		foreach ($this->_known_verifications as $verification)
		{
			$class_name = '\\ElkArte\\sources\\subs\\VerificationControl\\' . $verification;
			$current_instance = new $class_name($verificationOptions);

			// If there is anything to show, otherwise forget it
			if ($current_instance->showVerification($this->_sessionVal, $isNew, $force_refresh))
			{
				$this->_verification_instances[$verification] = $current_instance;
			}
		}
	}

	public function test($verification_errors)
	{
		$increase_error_count = false;

		// This cannot happen!
		if (!isset($this->_sessionVal['count']))
		{
			throw new \Elk_Exception('no_access', false);
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
			elseif ($this->_sessionVal['errors'] > $thisVerification['max_errors'])
			{
				$force_refresh = true;
			}

			// Keep a track of these.
			$this->_sessionVal['errors']++;
		}

		return $force_refresh;
	}

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

	public function hasControls()
	{
		return count($this->_verification_instances) !== 0;
	}
}