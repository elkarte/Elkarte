<?php

/**
 * This does the job of handling user errors in their many forms
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 *  This class is an experiment for the job of handling errors.
 */
class Error_Context
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
	 * Multiton. This is an array of instances of error_context.
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
	 * @param string $id the error identifier
	 * @param int|null $default_severity the default error severity level
	 */
	private function __construct ($id = 'default', $default_severity = null)
	{
		if (!empty($id))
			$this->_name = $id;

		// Initialize severity levels... waiting for details!
		$this->_severity_levels = array(Error_Context::MINOR, Error_Context::SERIOUS);

		// Initialize default severity (not sure this is needed)
		if ($default_severity === null || !in_array($default_severity, $this->_severity_levels))
			$this->_default_severity = Error_Context::MINOR;
		else
			$this->_default_severity = $default_severity;

		$this->_errors = array();
	}

	/**
	 * Add an error to the list
	 *
	 * @param string[]|string $error error code
	 * @param string|int|null $severity error severity
	 * @param string|null $lang_file lang_file
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

		if (!empty($lang_file) && !isset($this->_language_files[$lang_file]))
			$this->_language_files[$lang_file] = false;
	}

	/**
	 * Remove an error from the list
	 *
	 * @param string $error error code
	 */
	public function removeError($error)
	{
		if (!empty($error))
		{
			if (is_array($error))
				$error = $error[0];

			foreach ($this->_errors as $severity => $errors)
			{
				if (array_key_exists($error, $errors))
					unset($this->_errors[$severity][$error]);
				if (empty($this->_errors[$severity]))
					unset($this->_errors[$severity]);
			}
		}
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
		if (empty($errors))
			return false;
		else
		{
			$errors = is_array($errors) ? $errors : array($errors);
			foreach ($errors as $error)
			{
				foreach ($this->_errors as $current_errors)
					if (isset($current_errors[$error]))
						return true;
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
		foreach ($levels as $level)
			if (!empty($this->_errors[$level]))
				return $level;

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
	 * Find and return error_context instance if it exists,
	 * or create a new instance for $id if it didn't already exist.
	 *
	 * @param string $id
	 * @param int|null $default_severity
	 * @return Error_Context
	 */
	public static function context($id = 'default', $default_severity = null)
	{
		if (self::$_contexts === null)
			self::$_contexts = array();

		if (!array_key_exists($id, self::$_contexts))
			self::$_contexts[$id] = new Error_Context($id, $default_severity);

		return self::$_contexts[$id];
	}
}

/**
 * Class Error context for attachments
 */
class Attachment_Error_Context
{
	/**
	 * Holds our static instance of the class
	 * @var object
	 */
	private static $_context = null;

	/**
	 * Holds all of the attachment ids
	 * @var array
	 */
	private $_attachs = null;

	/**
	 * Holds any errors found
	 * @var array
	 */
	private $_generic_error = null;

	/**
	 * Holds if the error is generic of specific to an attachment
	 * @var string
	 */
	private $_active_attach = null;

	/**
	 * Add attachment
	 *
	 * - Automatically activate the attachments added
	 *
	 * @param string $id
	 * @param string $name
	 */
	public function addAttach($id, $name)
	{
		if (empty($id) || empty($name))
		{
			$this->activate();
			return false;
		}

		if (!isset($this->_attachs[$id]))
			$this->_attachs[$id] = array(
				'name' => $name,
				'error' => Error_Context::context($id, 1),
			);

		$this->activate($id);

		return true;
	}

	/**
	 * Sets the active attach (errors are "attached" to that)
	 *
	 * @param int|null $id A valid attachment, if invalid it defaults to 'generic'
	 */
	public function activate($id = null)
	{
		if (empty($id) || !isset($this->_attachs[$id]))
			$this->_active_attach = 'generic';
		else
			$this->_active_attach = $id;

		return $this;
	}

	/**
	 * Add an error
	 *
	 * @param string $error error code
	 * @param string|null $lang_file = null
	 */
	public function addError($error, $lang_file = null)
	{
		if (empty($error))
			return;

		if ($this->_active_attach == 'generic')
		{
			if (!isset($this->_attachs[$this->_active_attach]))
				$this->_generic_error = Error_Context::context('attach_generic_error', 1);

			$this->_generic_error->addError($error, $lang_file);
			return;
		}

		$this->_attachs[$this->_active_attach]['error']->addError($error, $lang_file);
	}

	/**
	 * Removes an error
	 *
	 * @param string $error error code
	 */
	public function removeError($error)
	{
		if (empty($error))
			return;

		$this->_attachs[$this->_active_attach]['error']->removeError($error);
	}

	/**
	 * If this error context has errors stored.
	 *
	 * @param string|null $attachID
	 * @param int|null $severity the severity level
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
	 *
	 * - Return an array containing the error strings
	 * - If severity is null the function returns all the errors
	 *
	 * @param int|null $severity = null the severity level wanted
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
	 */
	public function getErrorType()
	{
		return 1;
	}

	/**
	 * Find and return Attachment_Error_Context instance if it exists,
	 * or create it if it doesn't exist
	 */
	public static function context()
	{
		if (self::$_context === null)
			self::$_context = new Attachment_Error_Context();

		return self::$_context;
	}
}