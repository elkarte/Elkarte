<?php

/**
 * This does the job of handling user errors in their many forms
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

namespace ElkArte\Errors;

/**
 *  This class is an experiment for the job of handling errors.
 */
final class ErrorContext
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
	 * @var array|mixed
	 */
	private $_severity_levels = array(0);

	/**
	 * Certain errors may need some specific language file...
	 *
	 * @var array
	 */
	private $_language_files = array();

	/**
	 * Multiton. This is an array of instances of ErrorContext.
	 * All callers use an error context ('post', 'attach', or 'default' if none chosen).
	 *
	 * @var array of ErrorContext
	 */
	private static $_contexts = null;

	const MINOR = 0;
	const SERIOUS = 1;

	/**
	 * Create and initialize an instance of the class
	 *
	 * @param string $id the error identifier
	 * @param int|null $default_severity the default error severity level
	 */
	private function __construct($id = 'default', $default_severity = null)
	{
		if (!empty($id))
			$this->_name = $id;

		// Initialize severity levels... waiting for details!
		$this->_severity_levels = array(self::MINOR, self::SERIOUS);

		// Initialize default severity (not sure this is needed)
		if ($default_severity === null || !in_array($default_severity, $this->_severity_levels))
			$this->_default_severity = self::MINOR;
		else
			$this->_default_severity = $default_severity;

		$this->_errors = array();
	}

	/**
	 * Add an error to the list
	 *
	 * @param mixed[]|mixed $error error code
	 * @param string|int|null $severity error severity
	 * @param string|null $lang_file lang_file
	 */
	public function addError($error, $severity = null, $lang_file = null)
	{
		$severity = $severity !== null && in_array($severity, $this->_severity_levels) ? $severity : $this->_default_severity;

		if (!empty($error))
		{
			$name = $this->getErrorName($error);
			$this->_errors[$severity][$name] = $error;
		}

		if (!empty($lang_file) && !isset($this->_language_files[$lang_file]))
			$this->_language_files[$lang_file] = false;
	}

	/**
	 * Remove an error from the list
	 *
	 * @param mixed[]|mixed $error error code
	 */
	public function removeError($error)
	{
		if (!empty($error))
		{
			$name = $this->getErrorName($error);

			foreach ($this->_errors as $severity => $errors)
			{
				if (array_key_exists($name, $errors))
					unset($this->_errors[$severity][$name]);
				if (empty($this->_errors[$severity]))
					unset($this->_errors[$severity]);
			}
		}
	}

	/**
	 * Finds the "name" of the error (either the string, the first element
	 * of the array, or the result of getName)
	 *
	 * @param mixed|mixed[] $error error code
	 */
	protected function getErrorName($error)
	{
		if (is_array($error))
			return $error[0];
		elseif (is_object($error))
			return $error->getName();
		else
			return $error;
	}

	/**
	 * Return an array of errors of a certain severity.
	 *
	 * @todo is it needed at all?
	 * @param string|int|null $severity the severity level wanted. If null returns all the errors
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
	 * Return an error based on the id of the error set when adding the error itself.
	 *
	 * @param mixed|mixed[] $error error code
	 * @return null|mixed whatever the error is (string, object, array), noll if not found
	 */
	public function getError($error = null)
	{
		$name = $this->getErrorName($error);
		if (isset($this->_errors[$name]))
			return $this->_errors[$name];
		else
			return null;
	}

	/**
	 * Returns if there are errors or not.
	 *
	 * @param string|null $severity the severity level wanted. If null returns all the errors
	 * @return bool
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
	 * @param string $errors the error
	 */
	public function hasError($errors)
	{
		if (!empty($errors))
		{
			$errors = (array) $errors;
			foreach ($errors as $error)
			{
				$name = $this->getErrorName($error);
				foreach ($this->_errors as $current_errors)
				{
					if (isset($current_errors[$name]))
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Return the code of the highest error level encountered
	 */
	public function getErrorType()
	{
		$levels = array_reverse($this->_severity_levels);
		$level = null;

		foreach ($levels as $level)
		{
			if (!empty($this->_errors[$level]))
				return $level;
		}

		return $level;
	}

	/**
	 * Return an array containing the error strings
	 *
	 * - If severity is null the function returns all the errors
	 *
	 * @param string|null $severity the severity level wanted
	 */
	public function prepareErrors($severity = null)
	{
		global $txt;

		if (empty($this->_errors))
			return array();

		$this->_loadLang();

		call_integration_hook('integrate_' . $this->_name . '_errors', array(&$this->_errors, &$this->_severity_levels));

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
				$returns[$error_val[0]] = vsprintf(isset($txt['error_' . $error_val[0]]) ? $txt['error_' . $error_val[0]] : (isset($txt[$error_val[0]]) ? $txt[$error_val[0]] : $error_val[0]), $error_val[1]);
			elseif (is_object($error_val))
				continue;
			else
				$returns[$error_val] = isset($txt['error_' . $error_val]) ? $txt['error_' . $error_val] : (isset($txt[$error_val]) ? $txt[$error_val] : $error_val);
		}

		return $returns;
	}

	/**
	 * Load the default error language and any other language file needed
	 */
	private function _loadLang()
	{
		// Errors is always needed
		loadLanguage('Errors');

		// Any custom one?
		if (!empty($this->_language_files))
			foreach ($this->_language_files as $language => $loaded)
				if (!$loaded)
				{
					loadLanguage($language);

					// Remember this file has been loaded already
					$this->_language_files[$language] = true;
				}
	}

	/**
	 * Find and return ErrorContext instance if it exists,
	 * or create a new instance for $id if it didn't already exist.
	 *
	 * @param string $id
	 * @param int|null $default_severity
	 * @return ErrorContext
	 */
	public static function context($id = 'default', $default_severity = null)
	{
		if (self::$_contexts === null)
			self::$_contexts = array();

		if (!array_key_exists($id, self::$_contexts))
			self::$_contexts[$id] = new self($id, $default_severity);

		return self::$_contexts[$id];
	}
}
