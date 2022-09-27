<?php

/**
 * This file contains those functions specific to the empty field verification controls
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\VerificationControls\VerificationControl;

/**
 * This class shows an anti-spam bot box in the form
 * The proper response is to leave the field empty, bots however will see this
 * much like a session field and populate it with a value.
 *
 * Adding additional catch terms is recommended to keep bots from learning
 */
class EmptyField implements ControlInterface
{
	/** @var array Hold the options passed to the class */
	private $_options;

	/** @var bool If it is going to be used or not on a form */
	private $_empty_field;

	/** @var string Holds a randomly generated field name */
	private $_field_name;

	/** @var bool If the validation test has been run */
	private $_tested = false;

	/** @var string What the user entered */
	private $_user_value;

	/** @var string[] Array of terms used in building the field name */
	private $_terms = ['gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier', 'bb2'];

	/** @var string[] Secondary array used to build out the field name */
	private $_second_terms = ['hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value', 'screener'];

	/**
	 * Get things rolling
	 *
	 * @param array|null $verificationOptions no_empty_field,
	 */
	public function __construct($verificationOptions = null)
	{
		if (!empty($verificationOptions))
		{
			$this->_options = $verificationOptions;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function showVerification($sessionVal, $isNew, $force_refresh = true)
	{
		global $modSettings;

		$this->_tested = false;

		if ($isNew)
		{
			$this->_empty_field = !empty($this->_options['no_empty_field']) || (!empty($modSettings['enable_emptyfield']) && !isset($this->_options['no_empty_field']));
			$this->_user_value = '';
		}

		if ($isNew || $force_refresh)
		{
			$this->createTest($sessionVal, $force_refresh);
		}

		return $this->_empty_field;
	}

	/**
	 * {@inheritdoc}
	 */
	public function createTest($sessionVal, $refresh = true)
	{
		if (!$this->_empty_field)
		{
			return;
		}

		// Building a field with a believable name that will be inserted lives in the template.
		if ($refresh || !isset($sessionVal['empty_field']))
		{
			$start = mt_rand(0, 27);
			$_hash = substr(md5(time()), $start, 6);
			$this->_field_name = $this->_terms[array_rand($this->_terms)] . '-' . $this->_second_terms[array_rand($this->_second_terms)] . '-' . $_hash;
			$sessionVal['empty_field'] = $this->_field_name;
		}
		else
		{
			$this->_field_name = $sessionVal['empty_field'];
			$this->_user_value = !empty($_REQUEST[$sessionVal['empty_field']]) ? $_REQUEST[$sessionVal['empty_field']] : '';
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepareContext($sessionVal)
	{
		theme()->getTemplates()->load('VerificationControls');

		return [
			'template' => 'emptyfield',
			'values' => [
				'is_error' => $this->_tested && !$this->_verifyField($sessionVal),
				// Can be used in the template to show the normally hidden field to add some spice to things
				'show' => !empty($sessionVal['empty_field']) && (mt_rand(1, 100) > 60),
				'user_value' => $this->_user_value,
				'field_name' => $this->_field_name,
				// Can be used in the template to randomly add a value to the empty field that needs to be removed when show is on
				'clear' => (mt_rand(1, 100) > 60),
			]
		];
	}

	/**
	 * Test the field, easy, it is on, it is set, and it is empty
	 */
	private function _verifyField($sessionVal)
	{
		return $this->_empty_field && !empty($sessionVal['empty_field']) && empty($_REQUEST[$sessionVal['empty_field']]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function doTest($sessionVal)
	{
		$this->_tested = true;

		if (!$this->_verifyField($sessionVal))
		{
			return 'wrong_verification_answer';
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasVisibleTemplate()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function settings()
	{
		// Empty field verification.
		return [
			['title', 'configure_emptyfield'],
			['desc', 'configure_emptyfield_desc'],
			['check', 'enable_emptyfield'],
		];
	}
}
