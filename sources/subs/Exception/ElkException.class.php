<?php

/**
 * All of the helper functions for use by the maillist controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

class Elk_Exception extends Exception
{
	protected $log = '';
	protected $sprintf = array();

	/**
	 * Redefined exception because we need more stuff
	 *
	 * @param string|string[] $message index of $txt or message
	 *                         If an array is used, then it can specify a
	 *                         custom language template to load.
	 * @param bool|string $log type of error, defines under which "log" is it shown.
	 *                      if false is used, the error is not logged.
	 * @param string[] $sprintf optional array of values to use in vsprintf with the $txt
	 */
	public function __construct($message, $log = 'general', $sprintf = array(), $code = 0, Exception $previous = null)
	{
		global $txt;

		$this->log = $log;
		$this->sprintf = $sprintf;

		$index_message = $this->_cleanMessage($message);

		if (isset($txt[$index_message]))
			$real_message = $txt[$index_message];
		else
			$real_message = $index_message;

		// make sure everything is assigned properly
		parent::__construct($real_message, $code, $previous);
	}

	/**
	 * Cleans up the message param passed to the constructor.
	 *
	 * @param string|string[] $message Can be several different thing:
	 *                         - the index of $txt
	 *                         - a plain message
	 *                         - an array with the following structure:
	 *                            array(
	 *                              0 => language to load (use loadLanguage)
	 *                              1 => index of $txt
	 *                            )
	 *                         - a namespaced index in the form:
	 *                            language.index
	 *                           a "language" followed by a "dot" followed by the "index"
	 *                           "language" can be any character matched by \w
	 *                           "index" can be anything
	 *                           "language" is loaded by loadLanguage.
	 * @return string The index or the message.
	 */
	protected function _cleanMessage($message)
	{
		if (is_array($message))
		{
			$language = $message[0];
			$msg = $message[1];
		}
		else
		{
			if (preg_match('/^(\w+)\.(.+)$/', $message, $matches) !== 0)
			{
				$language = $matches[1];
				$msg = $matches[2];
			}
			else
			{
				$language = 'Errors';
				$msg = $message;
			}
		}

		loadLanguage($language);

		return $msg;
	}

	/**
	 * Calls fatal_lang_error and end the execution of the script.
	 */
	public function fatalLangError()
	{
		fatal_lang_error($this->message, $this->log, $this->sprintf);
	}
}