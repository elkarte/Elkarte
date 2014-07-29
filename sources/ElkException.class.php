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

	// Redefine the exception so message isn't optional
	public function __construct($message, $log = 'general', $sprintf = array(), $code = 0, Exception $previous = null)
	{
		global $txt;

		$this->log = $log;
		$this->sprintf = $sprintf;

		if (is_array($message))
		{
			loadLanguage($message[0]);
			$real_message = $txt[$message[1]];
		}
		elseif (isset($txt[$message]))
			$real_message = $txt[$message];
		else
			$real_message = $message;

		// make sure everything is assigned properly
		parent::__construct($real_message, $code, $previous);
	}

	public function fatalLangError()
	{
		fatal_lang_error($this->message, $this->log, $this->sprintf);
	}
}