<?php

/**
 * This file contains our custom error handlers for PHP, hence its final status.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

namespace ElkArte\Errors;

use Elk_Exception;
use ErrorException;

/**
 * Class to handle our custom error handlers for PHP, hence its final status.
 *
 * @internal
 */
final class ErrorHandler extends Errors
{
	/** @var int Mask for errors that are fatal and will halt */
	protected $fatalErrors = 0;

	/** @var string The error string from $e->getMessage() */
	private $error_string;

	/** @var int The level of error from $e->getCode */
	private $error_level;

	/** @var string Common name for the error: Error, Waning, Notice */
	private $error_name;

	/** @var boolean Set this to TRUE to let PHP handle errors/warnings/notices. */
	const USE_DEFAULT = false;

	/**
	 * Good ol' constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Build the bitwise mask
		$this->fatalErrors = E_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_CORE_ERROR | E_PARSE;

		// Register the class handlers to the PHP handler functions
		set_error_handler(array($this, 'error_handler'));
		set_exception_handler(array($this, 'exception_handler'));
	}

	/**
	 * Determine the error name (or type) for display.
	 *
	 * @param int $error_level
	 * @param bool $isException
	 * @rerurn string
	 */
	private function set_error_name($error_level, $isException)
	{
		switch ($error_level)
		{
			case E_USER_ERROR:
				$type = 'Fatal Error';
			break;
			case E_USER_WARNING:
			case E_WARNING:
				$type = 'Warning';
			break;
			case E_USER_NOTICE:
			case E_NOTICE:
			case @E_STRICT:
				$type = 'Notice';
			break;
			case @E_RECOVERABLE_ERROR:
				$type = 'Catchable';
			break;
			default:
				$type = 'Unknown Error';
			break;
		}

		if ($isException)
		{
			$type = 'Exception';
		}

		return $type;
	}

	/**
	 * Handler for standard error messages, standard PHP error handler replacement.
	 *
	 * - Converts notices, warnings, and other errors into exceptions.
	 *
	 * @param int $error_level
	 * @param string $error_string
	 * @param string $file
	 * @param int $line
	 * @throws Elk_Exception
	 */
	public function error_handler($error_level, $error_string, $file, $line)
	{
		// Not using our custom error handler?
		if (self::USE_DEFAULT)
		{
			return false;
		}

		// Ignore errors if we're ignoring them or if the error code is not included in error_reporting.
		if (!($error_level & error_reporting()))
		{
			return true;
		}

		// Throw it as an ErrorException so that our exception handler deals with it.
		$this->exception_handler(new ErrorException($error_string, $error_level, $error_level, $file, $line));

		// Don't execute PHP internal error handler.
		return true;
	}

	/**
	 * Handler for exceptions and standard PHP errors
	 *
	 * - It dies with fatal_error() if the error_level matches with error_reporting.
	 *
	 * @param \Exception|\Throwable $e The error. Since the code shall work with php 5 and 7
	 *                                 we cannot type-hint the function parameter.
	 * @throws Elk_Exception
	 */
	public function exception_handler($e)
	{
		// Prepare the error details for the log
		$isException = !$e instanceof ErrorException;
		$this->error_string = $e->getMessage();
		$this->error_level = $e->getCode();
		$this->error_name = $this->set_error_name($this->error_level, $isException);
		$error_type = stripos($this->error_string, 'undefined') !== false ? 'undefined_vars' : 'general';
		$err_file = htmlspecialchars($e->getFile());
		$err_line = $e->getLine();

		// Showing the errors? Format them to look decent
		$message = $this->_prepareErrorDisplay($e);

		// Elk_Exception handles its own logging.
		if (!$e instanceof Elk_Exception)
			$this->log_error($this->error_name . ': ' . $this->error_string, $error_type, $err_file, $err_line);

		// Let's give integrations a chance to output a bit differently
		call_integration_hook('integrate_output_error', array($message, $error_type, $this->error_level, $err_file, $err_line));

		// Dying on these errors only causes MORE problems (blank pages!)
		if ($err_file === 'Unknown')
			return;

		// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die.  Violently so.
		if ($this->error_level & $this->fatalErrors || $this->error_level % 255 === E_WARNING || $isException)
			$this->_setup_fatal_ErrorContext($message, $this->error_level);
		else
		{
			// Display debug information?
			$this->_displayDebug($message);

			return;
		}

		// We should NEVER get to this point.  Any fatal error MUST quit, or very bad things can happen.
		if ($this->error_level & $this->fatalErrors)
			$this->terminate('Hacking attempt...');
	}

	/**
	 * Display debug information, shows exceptions / errors similar to standard
	 * PHP error output.
	 *
	 * @param string $message
	 */
	private function _displayDebug($message)
	{
		global $db_show_debug;

		if ($db_show_debug === true)
		{
			// Commonly, undefined indexes will occur inside attributes; try to show them anyway!
			if ($this->error_level % 255 !== E_ERROR)
			{
				$temporary = ob_get_contents();
				if (substr($temporary, -2) === '="')
					echo '"';
			}

			// Debugging!  This should look like a PHP error message.
			echo '<br /><strong>', $this->error_name, '</strong>: ' . $this->error_string . '<ol>' . $message . '</ol>';
		}
	}

	/**
	 * Builds the error text for display.
	 *
	 * Shows the stack trace if in debug mode and user is admin.
	 *
	 * @param \Exception|\Throwable $exception
	 *
	 * $return string The fully parsed error message
	 */
	private function _prepareErrorDisplay($exception)
	{
		global $db_show_debug;

		// Showing the errors, lets make it look decent
		if ($db_show_debug === true && allowedTo('admin_forum'))
		{
			$msg = 'PHP Fatal error:  Uncaught exception \'%s\' with message \'%s\' in %s:%s<br />Stack trace:<br />%s<br />  thrown in %s on line %s';

			// write tracelines into main template
			return sprintf(
				$msg,
				get_class($exception),
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine(),
				implode('<br />', $this->parseTrace($exception)),
				$exception->getFile(),
				$exception->getLine()
			);
		}
		else
			return $this->error_string;
	}

	/**
	 * Builds the stack trace for display.
	 *
	 * @param \Exception|\Throwable $exception
	 *
	 * $return string The fully parsed stack trace
	 */
	private function parseTrace($exception)
	{
		$traceline = '#%s %s(%s): %s(%s)';
		$trace = $exception->getTrace();
		foreach ($trace as $key => $stackPoint)
		{
			// convert arguments to their type
			$trace[$key]['args'] = array_map('gettype', isset($trace[$key]['args']) ? $trace[$key]['args'] : array());
		}

		$result = array();
		$key = 0;
		foreach ($trace as $key => $stackPoint)
		{
			$result[] = sprintf(
				$traceline,
				$key,
				!empty($stackPoint['file']) ? $stackPoint['file'] : '',
				!empty($stackPoint['line']) ? $stackPoint['line'] : '',
				!empty($stackPoint['function']) ? $stackPoint['function'] : '',
				implode(', ', $stackPoint['args'])
			);
		}
		// trace always ends with {main}
		$result[] = '#' . (++$key) . ' {main}';

		return $result;
	}
}
