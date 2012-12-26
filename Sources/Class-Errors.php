<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('DIALOGO'))
	die('Hacking attempt...');

/**
 *  This class is an experiment for the job of handling errors.
 */
class error_context
{
	/**
	 * Holds the unique identifier of the error (a name).
	 *
	 * @var string
	 */
	private $_name = null;

	/**
	 * An array that holds all the errors occurred separated by severity.
	 *
	 * @var array
	 */
	private $_errors = null;

	/**
	 * The default severity code.
	 *
	 * @var mixed
	 */
	private $_default_severity = 0;

	/**
	 * A list of all severity code from the less important to the most serious.
	 *
	 * @var array/mixed
	 */
	private $_severity_levels = array(0);

	/**
	 * Certain errors may need some specific language file...
	 *
	 * @var array
	 */
	private $_language_files = array();

	/**
	 * Multipleton. This is an array of instances of error_context.
	 * All callers use an error context ('post', 'attach', or 'default' if none chosen).
	 *
	 * @var array of error_context
	 */
	private static $_contexts = null;

	/**
	 * Initialize the class
	 *
	 * @param string error identifier
	 * @param array/mixed a list of all severity code from the less important to the most serious
	 * @param mixed the default error severity code
	 */
	private function __construct ($id = 'default')
	{
		if (!empty($id))
			$this->_name = $id;

		// initialize severity levels... waiting for details!
		$this->_severity_levels = array('minor', 'serious');

		// initialize default severity (not sure this is needed)
		$this->_default_severity = 'minor';
	}

	/**
	 * Add an error to the list
	 *
	 * @param string error code
	 * @param mixed error severity
	 */
	public function addError($error, $severity = null, $lang_file = null)
	{
		$severity = $severity !== null && in_array($severity, $this->_severity_levels) ? $severity : $this->_default_severity;

		if (!empty($error))
		{
			if (is_array($error))
				$this->_errors[$severity][$error[0]] = $error;
			else
				$this->_errors[$severity][$error] = $error;
		}
		if (!empty($lang_file))
			$this->_language_files[] = $lang_file;
	}

	/**
	 * Return an array of errors of a certain severity.
	 * @todo is it needed at all?
	 *
	 * @param string the severity level wanted. If null returns all the errors
	 */
	public function getErrors($severity = null)
	{
		if ($severity !== null && in_array($severity, $this->_severity_levels) && !empty($this->_errors[$severity]))
			return $this->_errors[$severity];
		elseif ($severity === null && !empty($this->_errors))
			return $this->_errors;
		else
			return false;
	}

	/**
	 * Returns if there are errors or not.
	 *
	 * @param string the severity level wanted. If null returns all the errors
	 */
	public function hasErrors($severity = null)
	{
		if ($severity !== null && in_array($severity, $this->_severity_levels))
			return !empty($this->_errors[$severity]);
		elseif ($severity === null)
			return !empty($this->_errors);
		else
			return false;
	}

	/**
	 * Check if a particular error exists.
	 *
	 * @param string the error
	 */
	public function hasError($error)
	{
		if (empty($error))
			return false;
		else
			foreach ($this->_errors as $errors)
				if (isset($errors[$error]))
					return true;
		return false;
	}

	/**
	 * Return the code of the highest error level encountered
	 *
	 * @return int
	 */
	public function getErrorType()
	{
		$levels = array_reverse($this->_severity_levels);
		foreach ($levels as $level)
			if (!empty($this->_errors[$level]))
				return $level;

		return $level;
	}

	/**
	 * Return an array containing the error strings
	 * If severity is null the function returns all the errors
	 *
	 * @param string the severity level wanted
	 */
	public function prepareErrors($severity = null)
	{
		global $txt;

		// Load the default error language and any other language file needed
		// @todo: we could load these languages only if really necessary...it just needs a coupld of changes
		loadLanguage('Errors');
		if (!empty($this->_language_files))
			foreach ($this->_language_files as $language)
				loadLanguage($language);

		call_integration_hook('integrate_' . $this->_name . 'errors', array($this->_errors, $this->_severity_levels));

		$errors = array();
		$returns = array();
		if ($severity === null)
		{
			foreach ($this->_errors as $err)
				$errors = array_merge($errors, $err);
		}
		elseif (in_array($severity, $this->_severity_levels) && !empty($this->_errors[$severity]))
			$errors = $this->_errors[$severity];

		foreach ($errors as $error_val)
		{
			if (is_array($error_val))
				$returns[$error_val[0]] = vsprintf(isset($txt['error_' . $error_val[0]]) ? $txt['error_' . $error_val[0]] : $error_val[0], $error_val[1]);
			else
				$returns[$error_val] = isset($txt['error_' . $error_val]) ? $txt['error_' . $error_val] : $error_val;
		}

		return $returns;
	}

	public static function context($id = 'default')
	{
		if (self::$_contexts === null)
			self::$_contexts = array();
		if (!array_key_exists($id, self::$_contexts))
			self::$_contexts[$id] = new error_context($id);

		return self::$_contexts[$id];
	}
}
