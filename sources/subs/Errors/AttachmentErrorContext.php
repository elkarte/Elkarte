<?php

/**
 * This does the job of handling attachment related errors
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

namespace ElkArte\Errors;

/**
 * Class Error context for attachments
 *
 * @todo Can this be simplified and extend ErrorContext?
 */
class AttachmentErrorContext
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
	 * @var ErrorContext|null
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
				'error' => ErrorContext::context($id, 1),
			);

		$this->activate($id);

		return true;
	}

	/**
	 * Sets the active attach (errors are "attached" to that)
	 *
	 * @param string|null $id A valid attachment, if invalid it defaults to 'generic'
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
				$this->_generic_error = ErrorContext::context('attach_generic_error', 1);

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
	 * If this error context has a particular error code.
	 *
	 * @param string $error_code the code of the error
	 * @param string|null $attachID
	 */
	public function hasError($error_code, $attachID = null)
	{
		if ($this->_generic_error !== null)
		{
			if ($this->_generic_error->hasError($error_code))
			{
				return true;
			}
		}

		if (!empty($this->_attachs))
		{
			if ($attachID !== null)
			{
				return isset($this->_attachs[$attachID]) && $this->_attachs[$attachID]['error']->hasError($error_code);
			}
			else
			{
				foreach ($this->_attachs as $attach)
				{
					if ($attach['error']->hasError($error_code))
						return true;
				}
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
		{
			foreach ($this->_attachs as $attachID => $error)
			{
				$returns[$attachID] = array(
					'errors' => $error['error']->prepareErrors($severity),
					'type' => $this->getErrorType(),
					'title' => sprintf($txt['attach_warning'], $error['name']),
				);
			}
		}

		return $returns;
	}

	public function getName()
	{
		return 'attach_error_title';
	}

	/**
	 * Return the type of the error
	 */
	public function getErrorType()
	{
		return 1;
	}

	/**
	 * Find and return Attachment_ErrorContext instance if it exists,
	 * or create it if it doesn't exist
	 */
	public static function context()
	{
		if (self::$_context === null)
			self::$_context = new self();

		return self::$_context;
	}
}
