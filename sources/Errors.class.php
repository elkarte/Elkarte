<?php

/**
 * The purpose of this file is... errors. (hard to guess, I guess?)  It takes
 * care of logging, error messages, error handling, database errors, and
 * error log administration.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Class to handle all forum errors and exceptions
 */
class Errors
{
	/**
	 * The prepared error string from getTrace to display when debug is enabled
	 * @var string
	 */
	private $error_text = '';

	/**
	 * Sole private Errors instance
	 * @var Errors
	 */
	private static $_errors = null;

	/**
	 * Mask for errors that are fatal and will halt
	 * @var int
	 */
	protected $fatalErrors;

	/**
	 * The error string from $e->getMessage()
	 * @var string
	 */
	private $error_string;

	/**
	 * The level of error from $e->getCode
	 * @var int
	 */
	private $error_level;

	/**
	 * Common name for the error , Error, Waning, Notice
	 * @var string
	 */
	private $error_name;

	/**
	 * Good old constructor
	 */
	public function __construct()
	{
		// Build the bitwise mask
		$this->fatalErrors = E_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_CORE_ERROR | E_PARSE;
	}

	/**
	 * Registers the class handlers to the PHP handler functions
	 */
	public function register_handlers()
	{
		set_error_handler(array($this, 'error_handler'));
		set_exception_handler(array($this, 'exception_handler'));
	}

	/**
	 * Halts execution, optionally displays an error message
	 *
	 * @param string|integer $error
	 */
	protected function terminate($error = '')
	{
		die(htmlspecialchars($error));
	}

	/**
	 * Log an error to the error log if the error logging is enabled.
	 *
	 * - filename and line should be __FILE__ and __LINE__, respectively.
	 *
	 * Example use:
	 *   - die(Errors::instance()->log_error($msg));
	 *
	 * @param string $error_message
	 * @param string|boolean $error_type = 'general'
	 * @param string|null $file = null
	 * @param int|null $line = null
	 */
	public function log_error($error_message, $error_type = 'general', $file = null, $line = null)
	{
		global $modSettings, $user_info, $scripturl, $last_error;

		$db = database();
		static $tried_hook = false;

		// Check if error logging is actually on.
		if (empty($modSettings['enableErrorLogging']))
			return $error_message;

		// Basically, htmlspecialchars it minus &. (for entities!)
		$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
		$error_message = strtr($error_message, array('&lt;br /&gt;' => '<br />', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br />'));

		// Add a file and line to the error message?
		// Don't use the actual txt entries for file and line but instead use %1$s for file and %2$s for line
		// Window style slashes don't play well, lets convert them to the unix style.
		$file = ($file === null) ? $file = '' : str_replace('\\', '/', $file);
		$line = ($line === null) ? $line = 0 : (int) $line;

		// Just in case there's no id_member or IP set yet.
		if (empty($user_info['id']))
			$user_info['id'] = 0;
		if (empty($user_info['ip']))
			$user_info['ip'] = '';

		// Find the best query string we can...
		$query_string = empty($_SERVER['QUERY_STRING']) ? (empty($_SERVER['REQUEST_URL']) ? '' : str_replace($scripturl, '', $_SERVER['REQUEST_URL'])) : $_SERVER['QUERY_STRING'];

		// Don't log the session hash in the url twice, it's a waste.
		$query_string = htmlspecialchars((ELK === 'SSI' ? '' : '?') . preg_replace(array('~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'), array(';sesc', ''), $query_string), ENT_COMPAT, 'UTF-8');

		// Just so we know what board error messages are from.
		if (isset($_POST['board']) && !isset($_GET['board']))
			$query_string .= ($query_string == '' ? 'board=' : ';board=') . $_POST['board'];

		// What types of categories do we have?$other_error_types = array();
		$known_error_types = array(
			'general',
			'critical',
			'database',
			'undefined_vars',
			'user',
			'template',
			'debug',
		);

		// Perhaps integration wants to add specific error types for the log
		$other_error_types = array();
		if (empty($tried_hook))
		{
			// This prevents us from infinite looping if the hook or call produces an error.
			$tried_hook = true;
			call_integration_hook('integrate_error_types', array(&$other_error_types));
			$known_error_types += $other_error_types;
		}

		// Make sure the category that was specified is a valid one
		$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

		// Don't log the same error countless times, as we can get in a cycle of depression...
		$error_info = array($user_info['id'], time(), $user_info['ip'], $query_string, $error_message, (string) $_SESSION['session_value'], $error_type, $file, $line);
		if (empty($last_error) || $last_error != $error_info)
		{
			// Insert the error into the database.
			$db->insert('',
				'{db_prefix}log_errors',
				array('id_member' => 'int', 'log_time' => 'int', 'ip' => 'string-16', 'url' => 'string-65534', 'message' => 'string-65534', 'session' => 'string', 'error_type' => 'string', 'file' => 'string-255', 'line' => 'int'),
				$error_info,
				array('id_error')
			);
			$last_error = $error_info;
		}

		// Return the message to make things simpler.
		return $error_message;
	}

	/**
	 * Similar to log_error, it accepts a language index as the error.
	 *
	 * - Takes care of loading the forum default language
	 * - Logs the error (forwarding to log_error)
	 *
	 * @param string $error
	 * @param string $error_type = 'general'
	 * @param string|mixed[] $sprintf = array()
	 * @param string|null $file = null
	 * @param int|null $line = null
	 */
	public function log_lang_error($error, $error_type = 'general', $sprintf = array(), $file = null, $line = null)
	{
		global $user_info, $language, $txt;

		loadLanguage('Errors', $language);

		$reload_lang_file = $language != $user_info['language'];

		$error_message = !isset($txt[$error]) ? $error : (empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf));
		$this->log_error($error_message, $error_type, $file, $line);

		// Load the language file, only if it needs to be reloaded
		if ($reload_lang_file)
			loadLanguage('Errors');
	}

	/**
	 * An irrecoverable error.
	 *
	 * - This function stops execution and displays an error message.
	 * - It logs the error message if $log is specified.
	 *
	 * @param string $error
	 * @param string|boolean $log defaults to 'general', use false to skip _setup_fatal_error_context
	 */
	public function fatal_error($error = '', $log = 'general')
	{
		global $txt;

		// We don't have $txt yet, but that's okay...
		if (empty($txt))
			$this->terminate($error);

		if (class_exists('Template_Layers'))
			Template_Layers::getInstance()->isError();

		$this->_setup_fatal_error_context($log ? $this->log_error($error, $log) : $error, $error);
	}

	/**
	 * Shows a fatal error with a message stored in the language file.
	 *
	 * What it does:
	 * - This function stops execution and displays an error message by key.
	 * - uses the string with the error_message_key key.
	 * - logs the error in the forum's default language while displaying the error
	 * message in the user's language.
	 * - uses Errors language file and applies the $sprintf information if specified.
	 * - the information is logged if log is specified.
	 *
	 * @param string $error
	 * @param string|boolean $log defaults to 'general' false will skip logging, true will use general
	 * @param string[] $sprintf defaults to empty array()
	 */
	public function fatal_lang_error($error, $log = 'general', $sprintf = array())
	{
		global $txt, $language, $user_info, $context;
		static $fatal_error_called = false;

		$error_message = '';

		// Try to load a theme if we don't have one.
		if (empty($context['theme_loaded']) && empty($fatal_error_called))
		{
			$fatal_error_called = true;
			loadTheme();
		}

		// If we have no theme stuff we can't have the language file...
		if (empty($context['theme_loaded']))
			$this->terminate($error);

		if (class_exists('Template_Layers'))
			Template_Layers::getInstance()->isError();

		$reload_lang_file = true;

		// Log the error in the forum's language, but don't waste the time if we aren't logging
		if ($log)
		{
			loadLanguage('Errors', $language);
			$reload_lang_file = $language != $user_info['language'];
			$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
			$this->log_error($error_message, $log);
		}

		// Load the language file, only if it needs to be reloaded
		if ($reload_lang_file)
		{
			loadLanguage('Errors');
			$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
		}

		$this->_setup_fatal_error_context($error_message, $error);
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
	 */
	public function error_handler($error_level, $error_string, $file, $line)
	{
		// Ignore errors if we're ignoring them or if the error code is not included in error_reporting
		if (!($error_level & error_reporting()))
			return true;

		// Throw it as an exception so our exception_handler deals with it
		$this->exception_handler(new Exception($error_string, $error_level), $file, $line);

		return false;
	}

	/**
	 * Handler for exceptions and standard PHP errors
	 *
	 * - It dies with fatal_error() if the error_level matches with error_reporting.
	 *
	 * @param Exception|Throwable $e The error. Since the code shall work with php 5 and 7
	 *                               we cannot type-hint the function parameter.
	 * @param string|null $err_file
	 * @param int|null $err_line
	 */
	public function exception_handler($e, $err_file = null, $err_line = null)
	{
		$this->error_text = '';

		// Showing the errors? Format them to look decent
		$this->_prepareErrorDisplay($e, $err_file, $err_line);

		// Prepare the error details for the log
		$exception = !isset($err_file, $err_line);
		$this->error_string = $e->getMessage();
		$this->error_level = $e->getCode();
		$this->error_name = $this->error_level % 255 === E_ERROR ? 'Error' : ($this->error_level % 255 === E_WARNING ? 'Warning' : 'Notice');
		$error_type = stripos($this->error_string, 'undefined') !== false ? 'undefined_vars' : 'general';
		$err_file = htmlspecialchars(isset($err_file) ? $err_file : $e->getFile());
		$err_line = isset($err_line) ? $err_line : $e->getLine();

		// Logged !
		$message = $this->log_error($this->error_name . ': ' . $this->error_string, $error_type, $err_file, $err_line);

		// Display debug information?
		$this->_displayDebug();

		// Let's give integrations a chance to output a bit differently
		call_integration_hook('integrate_output_error', array($message, $error_type, $this->error_level, $err_file, $err_line));

		// Dying on these errors only causes MORE problems (blank pages!)
		if ($err_file === 'Unknown')
			return;

		// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
		if ($this->error_level & $this->fatalErrors || $exception)
			obExit(false);
		else
			return;

		// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die.  Violently so.
		if ($this->error_level & $this->fatalErrors || $this->error_level % 255 === E_WARNING)
			$this->fatal_error(allowedTo('admin_forum') ? $message : $this->error_string, false);

		// We should NEVER get to this point.  Any fatal error MUST quit, or very bad things can happen.
		if ($this->error_level & $this->fatalErrors)
			$this->terminate('Hacking attempt...');
	}

	/**
	 * Display debug information, shows exceptions / errors similar to standard
	 * PHP error output.
	 */
	private function _displayDebug()
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
			echo '<br /><strong>', $this->error_name, '</strong>: ' . $this->error_string . '<ol>' . $this->error_text . '</ol>';
		}
	}

	/**
	 * Builds the error text stack trace for display when using debug options
	 *
	 * @param exception $e
	 * @param string|null $err_file
	 * @param int|null $err_line
	 */
	private function _prepareErrorDisplay($e, $err_file = null, $err_line = null)
	{
		global $db_show_debug;

		$current_directory = str_replace('\\', '/', getcwd());

		// Showing the errors, lets make it look decent
		if ($db_show_debug === true)
		{
			$error_trace = $e->getTrace();

			// Set the proper top
			$error_trace[0]['file'] = isset($err_file) ? $err_file : $e->getFile();
			$error_trace[0]['line'] = isset($err_line) ? $err_line : $e->getLine();

			// Where are we coming from
			$not_thrown = isset($err_file, $err_line);

			// Build the debug error trace html
			foreach ($error_trace as $key => $entry)
			{
				$function = $this->_debug_error_func($error_trace, $entry, $not_thrown, $key);
				$entry['file'] = isset($entry['file']) ? str_replace($current_directory, '', str_replace('\\', '/', $entry['file'])) : '';

				$this->error_text .= '<li><strong>' . htmlspecialchars($function) . '()</strong>' . (isset($entry['file'], $entry['line'])
						? ' in <strong>' . $entry['file'] . '</strong> at line <strong>' . $entry['line'] . '</strong>'
						: '') . "</li>\n";
			}
		}
	}

	/**
	 * Sets the function string for the debug backtrace
	 *
	 * @param array $error_trace
	 * @param string[] $entry
	 * @param boolean $not_thrown
	 * @param int $key
	 *
	 * @return string
	 */
	private function _debug_error_func($error_trace, $entry, $not_thrown, $key)
	{
		// If from the error_handler, the stack is out of sync
		if ($not_thrown)
			$function = (isset($error_trace[$key + 1]['class']) ? $error_trace[$key + 1]['class'] . $error_trace[$key + 1]['type'] : '') . isset($error_trace[$key + 1]['function']) ? $error_trace[$key + 1]['function'] : '';
		else
			$function = (isset($entry['class']) ? $entry['class'] . $entry['type'] : '') . $entry['function'];

		return $function;
	}

	/**
	 * It is called by Errors::fatal_error() and Errors::fatal_lang_error().
	 *
	 * @uses Errors template, fatal_error sub template
	 * @param string $error_message
	 * @param string $error_code string or int code
	 */
	private function _setup_fatal_error_context($error_message, $error_code)
	{
		global $context, $txt, $ssi_on_error_method;
		static $level = 0;

		// Attempt to prevent a recursive loop.
		++$level;
		if ($level > 1)
			return false;

		// Maybe they came from dlattach or similar?
		if (ELK !== 'SSI' && empty($context['theme_loaded']))
			loadTheme();

		// Don't bother indexing errors mate...
		$context['robot_no_index'] = true;

		// A little something for the template
		$context['error_title'] = isset($context['error_title']) ? $context['error_title'] : $txt['error_occurred'];
		$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;
		$context['error_code'] = isset($error_code) ? 'id="' . htmlspecialchars($error_code) . '" ' : '';
		$context['page_title'] = empty($context['page_title']) ? $context['error_title'] : $context['page_title'] ;

		// Load the template and set the sub template.
		loadTemplate('Errors');
		$context['sub_template'] = 'fatal_error';

		// If this is SSI, what do they want us to do?
		if (ELK === 'SSI')
		{
			if (!empty($ssi_on_error_method) && $ssi_on_error_method !== true && is_callable($ssi_on_error_method))
				$ssi_on_error_method();
			elseif (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
				loadSubTemplate('fatal_error');

			// No layers?
			if (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
				$this->terminate();
		}

		// We want whatever for the header, and a footer. (footer includes sub template!)
		obExit(null, true, false, true);

		/* DO NOT IGNORE:
			If you are creating a bridge or modifying this function, you MUST
			make ABSOLUTELY SURE that this function quits and DOES NOT RETURN TO NORMAL
			PROGRAM FLOW.  Otherwise, security error messages will not be shown, and
			your forum will be in a very easily hackable state.
		*/
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}

	/**
	 * Show a message for the (full block) maintenance mode.
	 *
	 * What it does:
	 * - It shows a complete page independent of language files or themes.
	 * - It is used only if $maintenance = 2 in Settings.php.
	 * - It stops further execution of the script.
	 */
	public function display_maintenance_message()
	{
		global $maintenance, $mtitle, $mmessage;

		$this->_set_fatal_error_headers();

		if (!empty($maintenance))
			echo '<!DOCTYPE html>
	<html>
		<head>
			<meta name="robots" content="noindex" />
			<title>', $mtitle, '</title>
		</head>
		<body>
			<h3>', $mtitle, '</h3>
			', $mmessage, '
		</body>
	</html>';

		$this->terminate();
	}

	/**
	 * Show an error message for the connection problems.
	 *
	 * What it does:
	 * - It shows a complete page independent of language files or themes.
	 * - It is used only if there's no way to connect to the database.
	 * - It stops further execution of the script.
	 */
	public function display_db_error()
	{
		global $mbname, $maintenance, $webmaster_email, $db_error_send;

		$db = database();
		$cache = Cache::instance();

		// Just check we're not in any buffers, just in case.
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		// Set the output headers
		$this->_set_fatal_error_headers();

		$db_last_error = db_last_error();

		$temp = '';
		if ($cache->getVar($temp, 'db_last_error', 600))
			$db_last_error = max($db_last_error, $temp);

		// Perhaps we want to notify by mail that there was a db error
		if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
		{
			// Try using shared memory if possible.
			$cache->put('db_last_error', time(), 600);
			if (!$cache->getVar($temp, 'db_last_error', 600))
				logLastDatabaseError();

			// Language files aren't loaded yet :'(
			$db_error = $db->last_error($db->connection());
			@mail($webmaster_email, $mbname . ': Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\n" . $db->db_title() . ' reported:' . "\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that the system could not connect to the database, contact your host if this continues.');
		}

		// What to do?  Language files haven't and can't be loaded yet...
		echo '<!DOCTYPE html>
	<html>
		<head>
			<meta name="robots" content="noindex" />
			<title>Connection Problems</title>
		</head>
		<body>
			<h3>Connection Problems</h3>
			Sorry, we were unable to connect to the database.  This may be caused by the server being busy.  Please try again later.
		</body>
	</html>';

		$this->terminate();
	}

	/**
	 * Show an error message for load average blocking problems.
	 *
	 * What it does:
	 * - It shows a complete page independent of language files or themes.
	 * - It is used only if the load averages are too high to continue execution.
	 * - It stops further execution of the script.
	 */
	public function display_loadavg_error()
	{
		// If this is a load average problem, display an appropriate message (but we still don't have language files!)
		$this->_set_fatal_error_headers();

		echo '<!DOCTYPE html>
	<html>
		<head>
			<meta name="robots" content="noindex" />
			<title>Temporarily Unavailable</title>
		</head>
		<body>
			<h3>Temporarily Unavailable</h3>
			Due to high stress on the server the forum is temporarily unavailable.  Please try again later.
		</body>
	</html>';

		$this->terminate();
	}

	/**
	 * Small utility function for fatal error pages, sets the headers.
	 *
	 * - Used by display_db_error(), display_loadavg_error(),
	 * display_maintenance_message()
	 */
	private function _set_fatal_error_headers()
	{
		// Don't cache this page!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		// Send the right error codes.
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: 3600');
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return Errors
	 */
	public static function instance()
	{
		if (self::$_errors === null)
			self::$_errors = new Errors();

		return self::$_errors;
	}
}