<?php

/**
 * The purpose of this file is... errors. (hard to guess, I guess?)  It takes
 * care of logging, error messages, error handling, database errors, and
 * error log administration.
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

use ElkArte\AbstractModel;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;

/**
 * Class to handle all forum errors and exceptions
 */
class Errors extends AbstractModel
{
	/** @var Errors Sole private Errors instance */
	private static $_errors = null;

	/** @var string[] The types of categories we have */
	private $errorTypes = array(
		'general',
		'critical',
		'database',
		'undefined_vars',
		'user',
		'template',
		'debug',
		'deprecated',
	);

	/**
	 * In case of maintenance of very early errors, the database may not be available,
	 * this __construct will feed AbstractModel with a value just to stop it
	 * from trying to initialize the database connection.
	 *
	 * @param \ElkArte\Database\QueryInterface|null $db
	 * @param \ElkArte\UserInfo|null $user
	 */
	public function __construct($db = null, $user = null)
	{
		parent::__construct($db, $user);
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return \ElkArte\Errors\Errors
	 * @throws \Exception
	 */
	public static function instance()
	{
		if (self::$_errors === null)
		{
			self::$_errors = function_exists('database') ? new self(database(), User::$info) : new self(1, null);
		}

		return self::$_errors;
	}

	/**
	 * @param string $errorType
	 */
	public function addErrorTypes($errorType)
	{
		$this->errorTypes[] = $errorType;
	}

	/**
	 * Similar to log_error, it accepts a language index as the error.
	 *
	 * What it does:
	 *
	 * - Takes care of loading the forum default language
	 * - Logs the error (forwarding to log_error)
	 *
	 * @param string $error
	 * @param string $error_type = 'general'
	 * @param string|mixed[] $sprintf = array()
	 * @param string $file = ''
	 * @param int $line = 0
	 *
	 * @return string
	 */
	public function log_lang_error($error, $error_type = 'general', $sprintf = array(), $file = '', $line = 0)
	{
		global $language, $txt;

		theme()->getTemplates()->loadLanguageFile('Errors', $language);

		$reload_lang_file = $language !== $this->user->language;

		$error_message = !isset($txt[$error]) ? $error : (empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf));
		$this->log_error($error_message, $error_type, $file, $line);

		// Load the language file, only if it needs to be reloaded
		if ($reload_lang_file)
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
		}

		// Return the message to make things simpler.
		return $error_message;
	}

	/**
	 * Log an error to the error log if the error logging is enabled.
	 *
	 * Available error types:
	 * - general
	 * - critical
	 * - database
	 * - undefined_vars
	 * - template
	 * - user
	 * - deprecated
	 *
	 * - filename and line should be __FILE__ and __LINE__, respectively.
	 *
	 * Example use:
	 *   - die(Errors::instance()->log_error($msg));
	 *
	 * @param string $error_message
	 * @param string|bool $error_type = 'general'
	 * @param string $file = ''
	 * @param int $line = 0
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function log_error($error_message, $error_type = 'general', $file = '', $line = 00)
	{
		// Check if error logging is actually on.
		if (empty($this->_modSettings['enableErrorLogging']))
		{
			return $error_message;
		}

		// Basically, htmlspecialchars it minus &. (for entities!)
		$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
		$error_message = strtr($error_message, array('&lt;br /&gt;' => '<br />', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br />'));

		// Add a file and line to the error message?
		// Don't use the actual txt entries for file and line but instead use %1$s for file and %2$s for line
		// Windows-style slashes don't play well, lets convert them to the unix style.
		$file = str_replace('\\', '/', $file);
		$line = (int) $line;

		// Find the best query string we can...
		$query_string = $this->parseQueryString();

		// Make sure the category that was specified is a valid one
		$error_type = in_array($error_type, $this->getErrorTypes()) && $error_type !== true ? $error_type : 'general';

		// Insert the error into the database.
		$this->insertLog($query_string, $error_message, $error_type, $file, $line);

		// Return the message to make things simpler.
		return $error_message;
	}

	/**
	 * @return string
	 */
	private function parseQueryString()
	{
		global $scripturl;

		$query_string = empty($_SERVER['QUERY_STRING']) ? (empty($_SERVER['REQUEST_URL']) ? '' : str_replace($scripturl, '', $_SERVER['REQUEST_URL'])) : $_SERVER['QUERY_STRING'];

		// Don't log the session hash in the url twice, it's a waste.
		$query_string = htmlspecialchars((ELK === 'SSI' ? '' : '?') . preg_replace(array('~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'), array(';sesc', ''), $query_string), ENT_COMPAT, 'UTF-8');

		// Just so we know what board error messages are from.
		if (isset($_POST['board']) && !isset($_GET['board']))
		{
			$query_string .= ($query_string === '' ? 'board=' : ';board=') . $_POST['board'];
		}

		return $query_string;
	}

	/**
	 * @return string[]
	 */
	protected function getErrorTypes()
	{
		static $tried_hook = false;

		// Perhaps integration wants to add specific error types for the log
		$errorTypes = array();
		if (empty($tried_hook))
		{
			// This prevents us from infinite looping if the hook or call produces an error.
			$tried_hook = true;
			call_integration_hook('integrate_error_types', array(&$errorTypes));
			$this->errorTypes += $errorTypes;
		}

		return $this->errorTypes;
	}

	/**
	 * Insert an error entry in to the log_errors table
	 *
	 * @param string $query_string
	 * @param string $error_message
	 * @param string|bool $error_type
	 * @param string $file
	 * @param int $line
	 * @throws \Exception
	 */
	private function insertLog($query_string, $error_message, $error_type, $file, $line)
	{
		global $last_error;

		$this->_db = database();

		// Just in case there's no id_member or IP set yet.
		$user_id = $this->user->id ?? 0;
		$user_ip = $this->user->ip ?? '';

		// Don't log the same error countless times, as we can get in a cycle of depression...
		$error_info = array($user_id, time(), $user_ip, $query_string, $error_message, isset($_SESSION['session_value']) ? (string) $_SESSION['session_value'] : 'no_session_data', $error_type, $file, $line);
		if (empty($last_error) || $last_error != $error_info)
		{
			// Insert the error into the database.
			$this->_db->insert(
				'',
				'{db_prefix}log_errors',
				array('id_member' => 'int', 'log_time' => 'int', 'ip' => 'string-16', 'url' => 'string-65534', 'message' => 'string-65534', 'session' => 'string', 'error_type' => 'string', 'file' => 'string-255', 'line' => 'int'),
				$error_info,
				array('id_error')
			);
			$last_error = $error_info;
		}
	}

	/**
	 * An irrecoverable error.
	 *
	 * What it does:
	 *
	 * - This function stops execution and displays an error message.
	 * - It logs the error message if $log is specified.
	 *
	 * @param string $error
	 * @param string|bool $log defaults to 'general' false will skip logging, true will use general
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function fatal_error($error = '', $log = 'general')
	{
		throw new Exception($error, $log);
	}

	/**
	 * Shows a fatal error with a message stored in the language file.
	 *
	 * What it does:
	 *
	 * - This function stops execution and displays an error message by key.
	 * - uses the string with the error_message_key key.
	 * - logs the error in the forum's default language while displaying the error
	 * message in the user's language.
	 * - uses Errors language file and applies the $sprintf information if specified.
	 * - the information is logged if log is specified.
	 *
	 * @param string $error
	 * @param string|bool $log defaults to 'general' false will skip logging, true will use general
	 * @param string[] $sprintf defaults to empty array()
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function fatal_lang_error($error, $log = 'general', $sprintf = array())
	{
		throw new Exception($error, $log, $sprintf);
	}

	/**
	 * Show a message for the (full block) maintenance mode.
	 *
	 * What it does:
	 *
	 * - It shows a complete page independent of language files or themes.
	 * - It is used only if $maintenance = 2 in Settings.php.
	 * - It stops further execution of the script.
	 */
	public function display_maintenance_message()
	{
		global $maintenance, $mtitle, $mmessage;

		$this->_set_fatal_error_headers();

		if (!empty($maintenance))
		{
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
		}

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
	 * Halts execution, optionally displays an error message
	 *
	 * @param string|int $error
	 */
	protected function terminate($error = '')
	{
		die(htmlspecialchars($error));
	}

	/**
	 * Show a message for the (full block) maintenance mode.
	 *
	 * What it does:
	 *
	 * - It shows a complete page independent of language files or themes.
	 * - It is used only if $maintenance = 2 in Settings.php.
	 * - It stops further execution of the script.
	 */
	public function display_minimal_error($message)
	{
		if (!headers_sent())
		{
			$this->_set_fatal_error_headers();
		}

		echo '<!DOCTYPE html>
	<html>
		<head>
			<meta name="robots" content="noindex" />
			<title>Unknown Error</title>
		</head>
		<body>
			<h3>Unknown Error</h3>
			', $message, '
		</body>
	</html>';

		$this->terminate();
	}

	/**
	 * Show an error message for the connection problems.
	 *
	 * What it does:
	 *
	 * - It shows a complete page independent of language files or themes.
	 * - It is used only if there's no way to connect to the database.
	 * - It stops further execution of the script.
	 */
	public function display_db_error($additional_msg = '')
	{
		global $mbname, $maintenance, $webmaster_email, $db_error_send;

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
		{
			$db_last_error = max($db_last_error, $temp);
		}

		// Perhaps we want to notify by mail that there was a this->_db error
		if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
		{
			// Try using shared memory if possible.
			$cache->put('db_last_error', time(), 600);
			if (!$cache->getVar($temp, 'db_last_error', 600))
			{
				logLastDatabaseError();
			}

			// Language files aren't loaded yet :'(
			$db_error = $this->_db->last_error();
			@mail($webmaster_email, $mbname . ': Database Error!', 'There has been a problem with the database!' . ($db_error === '' ? '' : "\n" . $this->_db->title() . ' reported:' . "\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that the system could not connect to the database, contact your host if this continues.');
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
			Sorry, we were unable to connect to the database.  This may be caused by the server being busy.  Please try again later.<br>
			' . $additional_msg . '
		</body>
	</html>';

		$this->terminate();
	}

	/**
	 * Show an error message for load average blocking problems.
	 *
	 * What it does:
	 *
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
	 * Small utility function for simplify logging of deprecated functions
	 * in the development phase of 2.0.
	 *
	 * @param $function
	 * @param $replacement
	 */
	public function log_deprecated($function, $replacement)
	{
		$debug = '<br><br>';
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] as $var => $val)
		{
			$debug .= $var . ': ' . $val . '<br>';
		}
		$this->log_error(
			sprintf(
				'%s is deprecated, use %s instead.%s',
				$function,
				$replacement,
				$debug
			),
			'deprecated'
		);
	}

	/**
	 * It is called by Errors::fatal_error() and Errors::fatal_lang_error().
	 *
	 * @param string $error_message
	 * @param string $error_code string or int code
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 * @uses Errors template, fatal_error sub template
	 *
	 */
	final protected function _setup_fatal_ErrorContext($error_message, $error_code)
	{
		global $context, $txt, $ssi_on_error_method;
		static $level = 0;

		// Attempt to prevent a recursive loop.
		++$level;
		if ($level > 1)
		{
			return false;
		}

		// Maybe they came from dlattach or similar?
		if (ELK !== 'SSI' && empty($context['theme_loaded']))
		{
			global $modSettings;

			// Who knew dying took this much effort
			$context['linktree'] = isset($context['linktree']) ? $context['linktree'] : array();
			User::load(true);

			$_SESSION['session_var'] = '';
			$_SESSION['session_value'] = '';
			new ThemeLoader();

			// Here lies elkarte, dead from a program error. Just a cryptic message, no output could be better.
			$context['user']['can_mod'] = false;
			$modSettings['default_forum_action'] = '';
		}

		// Don't bother indexing errors mate...
		$context['robot_no_index'] = true;

		// A little something for the template
		$context['error_title'] = isset($context['error_title']) ? $context['error_title'] : $txt['error_occurred'];
		$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;
		$context['error_code'] = isset($error_code) ? 'id="' . htmlspecialchars($error_code) . '" ' : '';
		$context['page_title'] = empty($context['page_title']) ? $context['error_title'] : $context['page_title'];

		// Load the template and set the sub template.
		theme()->getTemplates()->load('Errors');
		$context['sub_template'] = 'fatal_error';
		theme()->getLayers()->isError();

		// If this is SSI, what do they want us to do?
		if (ELK === 'SSI')
		{
			if (!empty($ssi_on_error_method) && $ssi_on_error_method !== true && is_callable($ssi_on_error_method))
			{
				call_user_func($ssi_on_error_method);
			}
			elseif (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			{
				theme()->getTemplates()->loadSubTemplate('fatal_error');
			}

			// No layers?
			if (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			{
				$this->terminate();
			}
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
}
