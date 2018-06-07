<?php

/**
 * General exception handler. Has support for throwing errors that
 * are specifically targeted at users, and even placing multiple messages
 * in one exception.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.4
 *
 */

use ElkArte\Errors\Errors as E;

/**
 * Class Elk_Exception
 */
class Elk_Exception extends Exception
{
	/**
	 * The log under which the error should be displayed.
	 *
	 * @var bool|string
	 */
	protected $log = '';

	/**
	 * Values to use in vsprintf.
	 *
	 * @var string[]
	 */
	protected $sprintf = array();

	/**
	 * The $txt index of the error message.
	 *
	 * @var string
	 */
	protected $index_message = '';

	/**
	 * Elk_Exception constructor.
	 * Extended exception rules because we need more stuff
	 *
	 * @param string|string[] $message index of $txt or message
	 *  - If an array is used, then it can specify a custom language template to load.
	 * @param bool|string $log type of error, defines under which "log" is it shown.
	 *  - If false is used, the error is not logged.
	 * @param string[] $sprintf optional array of values to use in vsprintf with the $txt
	 * @param int $code
	 * @param Exception|null $previous
	 */
	public function __construct($message, $log = 'general', $sprintf = array(), $code = 0, Exception $previous = null)
	{
		global $txt;

		$this->log = $log;
		$this->sprintf = $sprintf;

		$this->index_message = $this->loadMessage($message);

		if (isset($txt[$this->index_message]))
		{
			$real_message = $txt[$this->index_message];
		}
		else
		{
			$real_message = $this->index_message;
		}

		// Make sure everything is assigned properly
		parent::__construct($real_message, $code, $previous);
	}

	/**
	 * Cleans up the message param passed to the constructor.
	 *
	 * @param string|string[] $message Can be several different thing:
	 * - The index of $txt string
	 * - A plain text message
	 * - An array with the following structure:
	 *		array(
	 *			0 => language to load (use loadLanguage)
	 *			1 => index of $txt
	 *		)
	 * - A namespaced index in the form:
	 * 	 - language.index
	 *   - a "language" followed by a "dot" followed by the "index"
	 *   - "language" can be any character matched by \w
	 *   - "index" can be anything
	 * - "language" is loaded by loadLanguage.
	 *
	 * @return string[]
	 */
	protected function parseMessage($message)
	{
		// Load message with language support
		if (is_array($message))
		{
			$language = $message[0];
			$msg = $message[1];
		}
		else
		{
			// language.messageIndex
			if (preg_match('/^(\w+)\.(.+)$/', $message, $matches) !== 0)
			{
				$language = $matches[1];
				$msg = $matches[2];
			}
			// Simple Error message
			else
			{
				$language = 'Errors';
				$msg = $message;
			}
		}

		loadLanguage($language);

		return array($msg, $language);
	}

	/**
	 * Loads the language file specified in Elk_Exception::parseMessage()
	 * and replaces the index received in the constructor.
	 *
	 * @param string|string[] $message
	 *
	 * @return string The index or the message.
	 */
	protected function loadMessage($message)
	{
		global $txt;

		try
		{
			list ($msg, $lang) = $this->parseMessage($message);
			$this->logMessage($message, $lang);
			loadLanguage($lang);
		}
		catch (\Exception $e)
		{
			E::instance()->display_minimal_error($message);
		}

		$msg = !isset($txt[$msg]) ? $msg : (empty($this->sprintf) ? $txt[$msg] : vsprintf($txt[$msg], $this->sprintf));

		return $msg;
	}

	/**
	 * Loads the language file specified in Elk_Exception::parseMessage()
	 * and replaces the index received in the constructor.  Logs the message if needed.
	 *
	 * @param string $msg
	 * @param string $lang
	 */
	protected function logMessage($msg, $lang)
	{
		global $user_info, $language, $txt;

		// Don't need to reload the language file if both the user and
		// the forum share the same language.
		if ($language !== $user_info['language'])
			loadLanguage($lang, $language);

		if ($this->log !== false)
		{
			$msg = !isset($txt[$msg]) ? $msg : (empty($this->sprintf) ? $txt[$msg] : vsprintf($txt[$msg], $this->sprintf));
			E::instance()->log_error($msg, $this->log, $this->getFile(), $this->getLine());
		}
	}

	/**
	 * Calls fatal_lang_error and ends the execution of the script.
	 */
	public function fatalLangError()
	{
		E::instance()->fatal_lang_error($this->index_message, $this->log, $this->sprintf);
	}
}
