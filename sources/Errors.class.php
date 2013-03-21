<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
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

	const MINOR = 0;
	const SERIOUS = 1;

	/**
	 * Create and initialize an instance of the class
	 *
	 * @param string error identifier
	 * @param int the default error severity level
	 */
	private function __construct ($id = 'default', $default_severity = null)
	{
		if (!empty($id))
			$this->_name = $id;

		// initialize severity levels... waiting for details!
		$this->_severity_levels = array(error_context::MINOR, error_context::SERIOUS);

		// initialize default severity (not sure this is needed)
		if ($default_severity === null || !in_array($default_severity, $this->_severity_levels))
			$this->_default_severity = error_context::MINOR;
		else
			$this->_default_severity = $default_severity;

		$this->_errors = array();
	}

	/**
	 * Add an error to the list
	 *
	 * @param string error code
	 * @param mixed error severity
	 * @param string lang_file
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
	 * Remove an error from the list
	 *
	 * @param string error code
	 */
	public function removeError($error)
	{
		if (!empty($error))
		{
			if (is_array($error))
				$error = $error[0];
			foreach ($this->_errors as $severity => $errors)
				if (in_array($error, $errors))
					unset($this->_errors[$severity][$error]);
		}
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
	 * @param string the error
	 * @return bool
	 */
	public function hasError($errors)
	{
		if (empty($errors))
			return false;
		else
		{
			$errors = is_array($errors) ? $errors : array($errors);
			foreach ($errors as $error)
				foreach ($this->_errors as $current_errors)
					if (isset($current_errors[$error]))
						return true;
		}
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

		if (empty($this->_errors))
			return array();

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
			// @todo: take in consideration also $txt[$error_val]?
			if (is_array($error_val))
				$returns[$error_val[0]] = vsprintf(isset($txt['error_' . $error_val[0]]) ? $txt['error_' . $error_val[0]] : $error_val[0], $error_val[1]);
			else
				$returns[$error_val] = isset($txt['error_' . $error_val]) ? $txt['error_' . $error_val] : $error_val;
		}

		return $returns;
	}

	/**
	 * Find and return error_context instance if it exists,
	 * or create a new instance for $id if it didn't already exist.
	 *
	 * @param string $id
	 * @param int $default_severity
	 */
	public static function context($id = 'default', $default_severity = null)
	{
		if (self::$_contexts === null)
			self::$_contexts = array();
		if (!array_key_exists($id, self::$_contexts))
			self::$_contexts[$id] = new error_context($id, $default_severity);

		return self::$_contexts[$id];
	}
}

/**
 * Error context for attachments
 *
 */
class attachment_error_context
{
	private static $_context = null;
	private $_attachs = null;
	private $_generic_error = null;

	/**
	 * Add attachment
	 *
	 * @param string $id
	 * @param string $name
	 */
	public function addAttach($id, $name)
	{
		if (empty($id) || empty($name))
			return false;

		if (!isset($this->_attachs[$id]))
			$this->_attachs[$id] = array(
				'name' => $name,
				'error' => error_context::context($id, 1),
			);
		return true;

	}

	/**
	 * Add an error
	 *
	 * @param string $error error code
	 * @param string $attachID = 'generic'
	 * @param string $lang_file = null
	 */
	public function addError($error, $attachID = 'generic', $lang_file = null)
	{
		if (empty($error))
			return;

		if ($attachID == 'generic')
		{
			if (!isset($this->_attachs[$attachID]))
				$this->_generic_error = error_context::context('attach_generic_error', 1);
			$this->_generic_error->addError($error, null, $lang_file);
			return;
		}

		$this->_attachs[$attachID]['error']->addError($error, null, $lang_file);
	}

	/**
	 * If this error context has errors stored.
	 *
	 * @param string $attachID
	 * @param int $severity the severity level
	 */
	public function hasErrors($attachID = null, $severity = null)
	{
		if ($this->_generic_error !== null)
			if ($this->_generic_error->hasErrors($severity))
				return true;

		if (!empty($this->_attachs))
		{
			if ($attachID !== null)
			{
				if (isset($this->_attachs[$attachID]))
					return $this->_attachs[$attachID]['error']->hasErrors($severity);
			}
			else
			{
				foreach ($this->_attachs as $attach)
					if ($attach['error']->hasErrors($severity))
						return true;
			}
		}
		return false;
	}

	/**
	 * Prepare the errors for display.
	 * Return an array containing the error strings
	 * If severity is null the function returns all the errors
	 *
	 * @param int = null the severity level wanted
	 * @return array
	 */
	public function prepareErrors($severity = null)
	{
		global $txt;

		$returns = array();

		if ($this->_generic_error !== null)
			$returns['attach_generic'] = array(
				'errors' => $this->_generic_error->prepareErrors($severity),
				'type' => $this->getErrorType(),
				'title' => $txt['attach_error_title'],
			);

		if (!empty($this->_attachs))
			foreach ($this->_attachs as $attachID => $error)
				$returns[$attachID] = array(
					'errors' => $error['error']->prepareErrors($severity),
					'type' => $this->getErrorType(),
					'title' => sprintf($txt['attach_warning'], $error['name']),
				);

		return $returns;
	}

	/**
	 * Return the type of the error
	 *
	 * @return int
	 */
	public function getErrorType()
	{
		return 1;
	}

	/**
	 * Find and return attachment_error_context instance if it exists,
	 * or create it if it doesn't exist
	 *
	 * @return attachment_error_context
	 */
	public static function context()
	{
		if (self::$_context === null)
			self::$_context = new attachment_error_context();

		return self::$_context;
	}

}