<?php

/**
 * This file contains our custom error handlers for PHP, hence its final status.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Errors;

use ElkArte\Exceptions\Exception;
use ErrorException;
use Throwable;

/**
 * Class to handle our custom error handlers for PHP, hence its final status.
 *
 * @internal
 */
final class ErrorHandler extends Errors
{
	/** @var bool Set this to TRUE to let PHP handle errors/warnings/notices. */
	public const USE_DEFAULT = false;

	/** @var int Mask for errors that are fatal and will halt */
	protected $fatalErrors = E_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_CORE_ERROR | E_PARSE;

	/** @var string The error string from $e->getMessage() */
	private $error_string;

	/** @var int The level of error from $e->getCode */
	private $error_level;

	/** @var string Common name for the error: Error, Waning, Notice */
	private $error_name;

	/**
	 * Good ol' constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Register the class handlers to the PHP handler functions
		set_error_handler(fn($error_level, $error_string, $file, $line) => $this->error_handler($error_level, $error_string, $file, $line));
		set_exception_handler(function (Throwable $e) {
			$this->exception_handler($e);
		});
	}

	/**
	 * Handler for standard error messages, standard PHP error handler replacement.
	 *
	 * - Checks first if self::USE_DEFAULT is set to true and returns accordingly. Useful
	 * if you have specified your own custom error handler.
	 * - Converts notices, warnings, and other errors into exceptions.
	 * - Only does so if $error_level matches with error_reporting.
	 * - Dies if $error_level is a known fatal error.
	 *
	 * @param int $error_level
	 * @param string $error_string
	 * @param string $file
	 * @param int $line
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function error_handler($error_level, $error_string, $file, $line)
	{
		// Not using our custom error handler?
		if (self::USE_DEFAULT)
		{
			return false;
		}

		// Ignore errors if we're ignoring them or if the error code is not included in error_reporting.
		if (($error_level & error_reporting()) === 0)
		{
			return true;
		}

		// Send it as an ErrorException so that our exception handler deals with it.
		$this->exception_handler(
			new ErrorException($error_string, $error_level, $error_level, $file, $line)
		);

		// Don't execute PHP internal error handler.
		return true;
	}

	/**
	 * Handler for exceptions and standard PHP errors
	 *
	 * - Only does so if the error_level matches with error_reporting.
	 *
	 * @param Throwable $e
	 */
	public function exception_handler(Throwable $e)
	{
		// Prepare the error details for the log
		$isException = !$e instanceof ErrorException;
		$this->error_string = $e->getMessage();
		$this->error_level = $e->getCode();
		$this->error_name = $this->set_error_name($this->error_level, $isException);

		// Showing the errors? Format them to look decent
		$message = $this->_prepareErrorDisplay($e);

		// Let's give integrations a chance to output a bit differently
		call_integration_hook('integrate_output_error', [$e]);

		// \ElkArte\Exceptions\Exception handles its own logging.
		if (!$e instanceof Exception)
		{
			\ElkArte\Errors::instance()->log_error(
				$this->error_name . ': ' . $this->error_string,
				stripos($this->error_string, 'undefined') !== false ? 'undefined_vars' : 'general',
				$e->getFile(),
				$e->getLine()
			);
		}

		// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die.  Violently so.
		if ($this->error_level & $this->fatalErrors || $this->error_level % 255 === E_WARNING || $isException)
		{
			$this->_setup_fatal_ErrorContext($message, $this->error_level);
		}
		else
		{
			// Display debug information?
			$this->_displayDebug($message);

			return;
		}

		// We should NEVER get to this point.  Any fatal error MUST quit, or very bad things can happen.
		if (($this->error_level & $this->fatalErrors) !== 0)
		{
			$this->terminate('Hacking attempt...');
		}
	}

	/**
	 * Sets the error name based on the error level and whether it is an exception or not.
	 *
	 * @param int $error_level The error level
	 * @param bool $isException Whether it is an exception or not
	 * @return string The error name
	 */
	private function set_error_name(int $error_level, bool $isException): string
	{
		$type = match ($error_level)
		{
			E_USER_ERROR => 'Fatal Error',
			E_USER_WARNING, E_WARNING => 'Warning',
			E_USER_NOTICE, E_NOTICE, E_STRICT => 'Notice',
			E_RECOVERABLE_ERROR => 'Catchable',
			default => 'Unknown Error',
		};

		if ($isException)
		{
			return 'Exception';
		}

		return $type;
	}

	/**
	 * Builds the error text for display.
	 *
	 * Shows the stack trace if in debug mode and user is admin.
	 *
	 * @param Throwable $exception
	 *
	 * @return string The fully parsed error message
	 */
	private function _prepareErrorDisplay(Throwable $exception): string
	{
		global $db_show_debug;

		// Showing the errors, lets make it look decent
		if ($db_show_debug === true && allowedTo('admin_forum'))
		{
			$msg =
				<<<'MSG'
PHP Fatal error:  Uncaught exception '%s' with message '%s' in %s:%s<br />
Stack trace:<br />%s<br />  thrown in %s on line %s
MSG;

			// write trace lines into main template
			return sprintf(
				$msg,
				$exception::class,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine(),
				implode('<br />', $this->parseTrace($exception)),
				$exception->getFile(),
				$exception->getLine()
			);
		}

		return $this->error_string;
	}

	/**
	 * Builds the stack trace for display.
	 *
	 * @param Throwable $exception
	 *
	 * @return array The fully parsed stack trace
	 */
	private function parseTrace(Throwable $exception): array
	{
		$result = [];
		$key = 0;
		foreach ($exception->getTrace() as $key => $stackPoint)
		{
			$result[] = strtr(
				sprintf(
					'#%d. %s(%s): %s(%s)',
					$key,
					$stackPoint['file'] ?? '',
					$stackPoint['line'] ?? '',
					(isset($stackPoint['class']) ? $stackPoint['class'] . $stackPoint['type'] : '') . $stackPoint['function'] ?? '[internal function]',
					implode(', ', $this->getTraceArgs($stackPoint))
				),
				['(): ' => '']
			);
		}

		// trace always ends with {main}
		$result[] = '#' . (++$key) . ' {main}';

		return $result;
	}

	/**
	 * Get the arguments from a stack point
	 *
	 *  Advanced gettype().
	 *
	 *  - Shows the full class name if argument is an object.
	 *  - Shows the resource type if argument is a resource.
	 *  - Uses gettype() for all other types.
	 *
	 * @param array $stackPoint The stack point containing the arguments
	 * @return array The array of argument types
	 */
	private function getTraceArgs(array $stackPoint): array
	{
		$args = [];
		if (isset($stackPoint['args']))
		{
			foreach ($stackPoint['args'] as $arg)
			{
				if (is_object($arg))
				{
					$args[] = $arg::class;
				}
				elseif (is_resource($arg))
				{
					$args[] = get_resource_type($arg);
				}
				else
				{
					$args[] = gettype($arg);
				}
			}
		}

		return $args;
	}

	/**
	 * Display debug information, shows exceptions / errors similar to standard
	 * PHP error output.
	 *
	 * @param string $message
	 */
	private function _displayDebug(string $message): void
	{
		global $db_show_debug;

		if ($db_show_debug === true)
		{
			// Commonly, undefined indexes will occur inside attributes; try to show them anyway!
			if ($this->error_level % 255 !== E_ERROR)
			{
				$temporary = ob_get_contents();
				if (substr($temporary, -2) === '="')
				{
					echo '"';
				}
			}

			// Debugging!  This should look like a PHP error message.
			echo '<br /><strong>', $this->error_name, '</strong>: ' . $this->error_string . '<ol>' . $message . '</ol>';
		}
	}
}
