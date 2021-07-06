<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
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

use ElkArte\Cache\Cache;
use ElkArte\Censor;
use ElkArte\ConstructPageIndex;
use ElkArte\Debug;
use ElkArte\GenericList;
use ElkArte\Hooks;
use ElkArte\Http\Headers;
use ElkArte\Notifications;
use ElkArte\Search\Search;
use ElkArte\UrlGenerator\UrlGenerator;
use ElkArte\User;
use ElkArte\Util;

/**
 * Updates the settings table as well as $modSettings... only does one at a time if $update is true.
 *
 * What it does:
 *
 * - Updates both the settings table and $modSettings array.
 * - All of changeArray's indexes and values are assumed to have escaped apostrophes (')!
 * - If a variable is already set to what you want to change it to, that
 *   Variable will be skipped over; it would be unnecessary to reset.
 * - When update is true, UPDATEs will be used instead of REPLACE.
 * - When update is true, the value can be true or false to increment
 *  or decrement it, respectively.
 *
 * @param mixed[] $changeArray An associative array of what we're changing in 'setting' => 'value' format
 * @param bool $update Use an UPDATE query instead of a REPLACE query
 */
function updateSettings($changeArray, $update = false)
{
	global $modSettings;

	$db = database();
	$cache = Cache::instance();

	if (empty($changeArray) || !is_array($changeArray))
	{
		return;
	}

	// In some cases, this may be better and faster, but for large sets we don't want so many UPDATEs.
	if ($update)
	{
		foreach ($changeArray as $variable => $value)
		{
			$db->query('', '
				UPDATE {db_prefix}settings
				SET value = {' . ($value === false || $value === true ? 'raw' : 'string') . ':value}
				WHERE variable = {string:variable}',
				array(
					'value' => $value === true ? 'value + 1' : ($value === false ? 'value - 1' : $value),
					'variable' => $variable,
				)
			);

			$modSettings[$variable] = $value === true ? $modSettings[$variable] + 1 : ($value === false ? $modSettings[$variable] - 1 : $value);
		}

		// Clean out the cache and make sure the cobwebs are gone too.
		$cache->remove('modSettings');

		return;
	}

	$replaceArray = array();
	foreach ($changeArray as $variable => $value)
	{
		// Don't bother if it's already like that ;).
		if (isset($modSettings[$variable]) && $modSettings[$variable] === $value)
		{
			continue;
		}

		// If the variable isn't set, but would only be set to nothing'ness, then don't bother setting it.
		if (!isset($modSettings[$variable]) && empty($value))
		{
			continue;
		}

		$replaceArray[] = array($variable, $value);

		$modSettings[$variable] = $value;
	}

	if (empty($replaceArray))
	{
		return;
	}

	$db->replace(
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-65534'),
		$replaceArray,
		array('variable')
	);

	// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
	$cache->remove('modSettings');
}

/**
 * Deletes one setting from the settings table and takes care of $modSettings as well
 *
 * @param string|string[] $toRemove the setting or the settings to be removed
 */
function removeSettings($toRemove)
{
	global $modSettings;

	$db = database();

	if (empty($toRemove))
	{
		return;
	}

	if (!is_array($toRemove))
	{
		$toRemove = array($toRemove);
	}

	// Remove the setting from the db
	$db->query('', '
		DELETE FROM {db_prefix}settings
		WHERE variable IN ({array_string:setting_name})',
		array(
			'setting_name' => $toRemove,
		)
	);

	// Remove it from $modSettings now so it does not persist
	foreach ($toRemove as $setting)
	{
		if (isset($modSettings[$setting]))
		{
			unset($modSettings[$setting]);
		}
	}

	// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
	Cache::instance()->remove('modSettings');
}

/**
 * Constructs a page list.
 *
 * @depreciated since 2.0
 *
 */
function constructPageIndex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show = [])
{
	$pageindex = new ConstructPageIndex($base_url, $start, $max_value, $num_per_page, $flexible_start, $show);
	return $pageindex->getPageIndex();
}

/**
 * Formats a number.
 *
 * What it does:
 *
 * - Uses the format of number_format to decide how to format the number.
 *   for example, it might display "1 234,50".
 * - Caches the formatting data from the setting for optimization.
 *
 * @param float $number The float value to apply comma formatting
 * @param int|bool $override_decimal_count = false or number of decimals
 *
 * @return string
 */
function comma_format($number, $override_decimal_count = false)
{
	global $txt;
	static $thousands_separator = null, $decimal_separator = null, $decimal_count = null;

	// Cache these values...
	if ($decimal_separator === null)
	{
		// Not set for whatever reason?
		if (empty($txt['number_format']) || preg_match('~^1([^\d]*)?234([^\d]*)(0*?)$~', $txt['number_format'], $matches) != 1)
		{
			return $number;
		}

		// Cache these each load...
		$thousands_separator = $matches[1];
		$decimal_separator = $matches[2];
		$decimal_count = strlen($matches[3]);
	}

	// Format the string with our friend, number_format.
	return number_format($number, (float) $number === $number ? ($override_decimal_count === false ? $decimal_count : $override_decimal_count) : 0, $decimal_separator, $thousands_separator);
}

/**
 * Formats a number to a multiple of thousands x, x k, x M, x G, x T
 *
 * @param float $number The value to format
 * @param int|bool $override_decimal_count = false or number of decimals
 *
 * @return string
 */
function thousands_format($number, $override_decimal_count = false)
{
	foreach (array('', ' k', ' M', ' G', ' T') as $kb)
	{
		if ($number < 1000)
		{
			break;
		}

		$number /= 1000;
	}

	return comma_format($number, $override_decimal_count) . $kb;
}

/**
 * Formats a number to a computer byte size value xB, xKB, xMB, xGB
 *
 * @param int $number
 *
 * @return string
 */
function byte_format($number)
{
	global $txt;

	$kb = '';
	foreach (array('byte', 'kilobyte', 'megabyte', 'gigabyte') as $kb)
	{
		if ($number < 1024)
		{
			break;
		}

		$number /= 1024;
	}

	return comma_format($number) . ' ' . $txt[$kb];
}

/**
 * Format a time to make it look purdy.
 *
 * What it does:
 *
 * - Returns a pretty formatted version of time based on the user's format in User::$info->time_format.
 * - Applies all necessary time offsets to the timestamp, unless offset_type is set.
 * - If todayMod is set and show_today was not not specified or true, an
 *   alternate format string is used to show the date with something to show it is "today" or "yesterday".
 * - Performs localization (more than just strftime would do alone.)
 *
 * @param int $log_time A unix timestamp
 * @param string|bool $show_today = true show "Today"/"Yesterday",
 *   false shows the date, a string can force a date format to use %b %d, %Y
 * @param string|bool $offset_type = false If false, uses both user time offset and forum offset.
 *   If 'forum', uses only the forum offset. Otherwise no offset is applied.
 *
 * @return string
 */
function standardTime($log_time, $show_today = true, $offset_type = false)
{
	global $txt, $modSettings;
	static $non_twelve_hour, $is_win = null;

	if ($is_win === null)
	{
		$is_win = detectServer()->is('windows');
	}

	// Offset the time.
	if (!$offset_type)
	{
		$time = $log_time + (User::$info->time_offset + $modSettings['time_offset']) * 3600;
	}
	// Just the forum offset?
	elseif ($offset_type === 'forum')
	{
		$time = $log_time + $modSettings['time_offset'] * 3600;
	}
	else
	{
		$time = $log_time;
	}

	// We can't have a negative date (on Windows, at least.)
	if ($log_time < 0)
	{
		$log_time = 0;
	}

	// Today and Yesterday?
	if ($modSettings['todayMod'] >= 1 && $show_today === true)
	{
		// Get the current time.
		$nowtime = forum_time();

		$then = @getdate($time);
		$now = @getdate($nowtime);

		// Try to make something of a time format string...
		$s = strpos(User::$info->time_format, '%S') === false ? '' : ':%S';
		if (strpos(User::$info->time_format, '%H') === false && strpos(User::$info->time_format, '%T') === false)
		{
			$h = strpos(User::$info->time_format, '%l') === false ? '%I' : '%l';
			$today_fmt = $h . ':%M' . $s . ' %p';
		}
		else
		{
			$today_fmt = '%H:%M' . $s;
		}

		// Same day of the year, same year.... Today!
		if ($then['yday'] == $now['yday'] && $then['year'] == $now['year'])
		{
			return sprintf($txt['today'], standardTime($log_time, $today_fmt, $offset_type));
		}

		// Day-of-year is one less and same year, or it's the first of the year and that's the last of the year...
		if ($modSettings['todayMod'] == '2' && (($then['yday'] == $now['yday'] - 1 && $then['year'] == $now['year']) || ($now['yday'] == 0 && $then['year'] == $now['year'] - 1) && $then['mon'] == 12 && $then['mday'] == 31))
		{
			return sprintf($txt['yesterday'], standardTime($log_time, $today_fmt, $offset_type));
		}
	}

	$str = !is_bool($show_today) ? $show_today : User::$info->time_format;

	// Windows requires a slightly different language code identifier (LCID).
	// https://msdn.microsoft.com/en-us/library/cc233982.aspx
	if ($is_win)
	{
		$txt['lang_locale'] = strtr($txt['lang_locale'], '_', '-');
	}

	if (setlocale(LC_TIME, $txt['lang_locale']))
	{
		if (!isset($non_twelve_hour))
		{
			$non_twelve_hour = trim(strftime('%p')) === '';
		}
		if ($non_twelve_hour && strpos($str, '%p') !== false)
		{
			$str = str_replace('%p', (strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm']), $str);
		}

		foreach (array('%a', '%A', '%b', '%B') as $token)
		{
			if (strpos($str, $token) !== false)
			{
				$str = str_replace($token, !empty($txt['lang_capitalize_dates']) ? ElkArte\Util::ucwords(strftime($token, $time)) : strftime($token, $time), $str);
			}
		}
	}
	else
	{
		// Do-it-yourself time localization.  Fun.
		foreach (array('%a' => 'days_short', '%A' => 'days', '%b' => 'months_short', '%B' => 'months') as $token => $text_label)
		{
			if (strpos($str, $token) !== false)
			{
				$str = str_replace($token, $txt[$text_label][(int) strftime($token === '%a' || $token === '%A' ? '%w' : '%m', $time)], $str);
			}
		}

		if (strpos($str, '%p') !== false)
		{
			$str = str_replace('%p', (strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm']), $str);
		}
	}

	// Windows doesn't support %e; on some versions, strftime fails altogether if used, so let's prevent that.
	if ($is_win && strpos($str, '%e') !== false)
	{
		$str = str_replace('%e', ltrim(strftime('%d', $time), '0'), $str);
	}

	// Format any other characters..
	return strftime($str, $time);
}

/**
 * Used to render a timestamp to html5 <time> tag format.
 *
 * @param int $timestamp A unix timestamp
 *
 * @return string
 */
function htmlTime($timestamp)
{
	global $txt, $context;

	if (empty($timestamp))
	{
		return '';
	}

	$timestamp = forum_time(true, $timestamp);
	$time = date('Y-m-d H:i', $timestamp);
	$stdtime = standardTime($timestamp, true, true);

	// @todo maybe htmlspecialchars on the title attribute?
	return '<time title="' . (!empty($context['using_relative_time']) ? $stdtime : $txt['last_post']) . '" datetime="' . $time . '" data-timestamp="' . $timestamp . '">' . $stdtime . '</time>';
}

/**
 * Gets the current time with offset.
 *
 * What it does:
 *
 * - Always applies the offset in the time_offset setting.
 *
 * @param bool $use_user_offset = true if use_user_offset is true, applies the user's offset as well
 * @param int|null $timestamp = null A unix timestamp (null to use current time)
 *
 * @return int seconds since the unix epoch
 */
function forum_time($use_user_offset = true, $timestamp = null)
{
	global $modSettings;

	if ($timestamp === null)
	{
		$timestamp = time();
	}
	elseif ($timestamp === 0)
	{
		return 0;
	}

	return $timestamp + ($modSettings['time_offset'] + ($use_user_offset ? User::$info->time_offset : 0)) * 3600;
}

/**
 * Removes special entities from strings.  Compatibility...
 *
 * - Faster than html_entity_decode
 * - Removes the base entities ( &amp; &quot; &#039; &lt; and &gt;. ) from text with htmlspecialchars_decode
 * - Additionally converts &nbsp with str_replace
 *
 * @param string $string The string to apply htmlspecialchars_decode
 *
 * @return string string without entities
 */
function un_htmlspecialchars($string)
{
	$string = htmlspecialchars_decode($string, ENT_QUOTES);

	return str_replace('&nbsp;', ' ', $string);
}

/**
 * Lexicographic permutation function.
 *
 * This is a special type of permutation which involves the order of the set. The next
 * lexicographic permutation of '32541' is '34125'. Numerically, it is simply the smallest
 * set larger than the current one.
 *
 * The benefit of this over a recursive solution is that the whole list does NOT need
 * to be held in memory. So it's actually possible to run 30! permutations without
 * causing a memory overflow.
 *
 * Source: O'Reilly PHP Cookbook
 *
 * @param mixed[] $p The array keys to apply permutation
 * @param int $size The size of our permutation array
 *
 * @return mixed[]|bool the next permutation of the passed array $p
 */
function pc_next_permutation($p, $size)
{
	// Slide down the array looking for where we're smaller than the next guy
	for ($i = $size - 1; isset($p[$i]) && $p[$i] >= $p[$i + 1]; --$i)
	{
	}

	// If this doesn't occur, we've finished our permutations
	// the array is reversed: (1, 2, 3, 4) => (4, 3, 2, 1)
	if ($i === -1)
	{
		return false;
	}

	// Slide down the array looking for a bigger number than what we found before
	for ($j = $size; $p[$j] <= $p[$i]; --$j)
	{
	}

	// Swap them
	$tmp = $p[$i];
	$p[$i] = $p[$j];
	$p[$j] = $tmp;

	// Now reverse the elements in between by swapping the ends
	for (++$i, $j = $size; $i < $j; ++$i, --$j)
	{
		$tmp = $p[$i];
		$p[$i] = $p[$j];
		$p[$j] = $tmp;
	}

	return $p;
}

/**
 * Ends execution and redirects the user to a new location
 *
 * What it does:
 *
 * - Makes sure the browser doesn't come back and repost the form data.
 * - Should be used whenever anything is posted.
 * - Diverts final execution to obExit() which means a end to processing and sending of final output
 *
 * @event integrate_redirect called before headers are sent
 * @param string $setLocation = '' The URL to redirect to
 */
function redirectexit($setLocation = '')
{
	global $db_show_debug;

	// Allow a way for phpunit to run controller methods, pretty? no but allows us to return to the test
	if (defined('PHPUNITBOOTSTRAP') && defined('STDIN'))
	{
		return;
	}

	// Send headers, call integration, do maintance
	Headers::instance()
		->removeHeader('all')
		->redirect($setLocation)
		->send();

	// Debugging.
	if ($db_show_debug === true)
	{
		$_SESSION['debug_redirect'] = Debug::instance()->get_db();
	}

	obExit(false);
}

/**
 * Ends execution.
 *
 * What it does:
 *
 * - Takes care of template loading and remembering the previous URL.
 * - Calls ob_start() with ob_sessrewrite to fix URLs if necessary.
 *
 * @event integrate_invalid_old_url allows adding to "from" urls we don't save
 * @event integrate_exit inform portal, etc. that we're integrated with to exit
 * @param bool|null $header = null Output the header
 * @param bool|null $do_footer = null Output the footer
 * @param bool $from_index = false If we're coming from index.php
 * @param bool $from_fatal_error = false If we are exiting due to a fatal error
 * @throws \ElkArte\Exceptions\Exception
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $txt, $db_show_debug;

	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
	{
		exit;
	}

	if ($from_fatal_error)
	{
		$has_fatal_error = true;
	}

	$do_header = $header ?? !$header_done;
	$do_footer = $do_footer ?? $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		handleMaintance();

		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
		{
			$context['page_title_html_safe'] = ElkArte\Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');
		}

		// Start up the session URL fixer.
		ob_start('ob_sessrewrite');

		call_integration_buffer();

		// Display the screen in the logical order.
		template_header();
		$header_done = true;
	}

	if ($do_footer)
	{
		// Show the footer.
		theme()->getTemplates()->loadSubTemplate($context['sub_template'] ?? 'main');

		// Just so we don't get caught in an endless loop of errors from the footer...
		if (!$footer_done)
		{
			$footer_done = true;
			template_footer();

			// Add $db_show_debug = true; to Settings.php if you want to show the debugging information.
			// (since this is just debugging... it's okay that it's after </html>.)
			if ($db_show_debug === true)
			{
				if (!isset($_REQUEST['api']) && ((!isset($_GET['action']) || $_GET['action'] !== 'viewquery') && !isset($_GET['api'])))
				{
					Debug::instance()->display();
				}
			}
		}
	}

	// Need user agent
	$req = request();

	setOldUrl();

	// For session check verification.... don't switch browsers...
	$_SESSION['USER_AGENT'] = $req->user_agent();

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index)
	{
		exit;
	}
}

/**
 * Takes care of a few dynamic maintenance items
 */
function handleMaintance()
{
	global $context;

	// Clear out the stat cache.
	trackStats();

	// Send off any notifications accumulated
	Notifications::instance()->send();

	// Queue any mail that needs to be sent
	if (!empty($context['flush_mail']))
	{
		// @todo this relies on 'flush_mail' being only set in AddMailQueue itself... :\
		AddMailQueue(true);
	}
}

/**
 * @param string $index
 */
function setOldUrl($index = 'old_url')
{
	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	$invalid_old_url = array(
		'action=dlattach',
		'action=jsoption',
		';api=xml',
	);
	call_integration_hook('integrate_invalid_old_url', array(&$invalid_old_url));
	$make_old = true;
	foreach ($invalid_old_url as $url)
	{
		if (strpos($_SERVER['REQUEST_URL'], $url) !== false)
		{
			$make_old = false;
			break;
		}
	}

	if ($make_old)
	{
		$_SESSION[$index] = $_SERVER['REQUEST_URL'];
	}
}

/**
 * Sets the class of the current topic based on is_very_hot, veryhot, hot, etc
 *
 * @param mixed[] $topic_context array of topic information
 */
function determineTopicClass(&$topic_context)
{
	// Set topic class depending on locked status and number of replies.
	if ($topic_context['is_very_hot'])
	{
		$topic_context['class'] = 'veryhot';
	}
	elseif ($topic_context['is_hot'])
	{
		$topic_context['class'] = 'hot';
	}
	else
	{
		$topic_context['class'] = 'normal';
	}

	$topic_context['class'] .= !empty($topic_context['is_poll']) ? '_poll' : '_post';

	if ($topic_context['is_locked'])
	{
		$topic_context['class'] .= '_locked';
	}

	if ($topic_context['is_sticky'])
	{
		$topic_context['class'] .= '_sticky';
	}
}

/**
 * Sets up the basic theme context stuff.
 *
 * @param bool $forceload = false
 *
 * @return
 */
function setupThemeContext($forceload = false)
{
	return theme()->setupThemeContext($forceload);
}

/**
 * Helper function to convert memory string settings to bytes
 *
 * @param string $val The byte string, like 256M or 1G
 *
 * @return int The string converted to a proper integer in bytes
 */
function memoryReturnBytes($val)
{
	if (is_integer($val))
	{
		return $val;
	}

	// Separate the number from the designator
	$val = trim($val);
	$num = intval(substr($val, 0, strlen($val) - 1));
	$last = strtolower(substr($val, -1));

	// Convert to bytes
	switch ($last)
	{
		// fall through select g = 1024*1024*1024
		case 'g':
			$num *= 1024;
		// fall through select m = 1024*1024
		case 'm':
			$num *= 1024;
		// fall through select k = 1024
		case 'k':
			$num *= 1024;
	}

	return $num;
}

/**
 * This is the only template included in the sources.
 */
function template_rawdata()
{
	return theme()->template_rawdata();
}

/**
 * The header template
 */
function template_header()
{
	return theme()->template_header();
}

/**
 * Show the copyright.
 */
function theme_copyright()
{
	return theme()->theme_copyright();
}

/**
 * The template footer
 */
function template_footer()
{
	return theme()->template_footer();
}

/**
 * Output the Javascript files
 *
 * What it does:
 *
 * - tabbing in this function is to make the HTML source look proper
 * - outputs jQuery/jQueryUI from the proper source (local/CDN)
 * - if deferred is set function will output all JS (source & inline) set to load at page end
 * - if the admin option to combine files is set, will use Combiner.class
 *
 * @param bool $do_deferred = false
 */
function template_javascript($do_deferred = false)
{
	theme()->template_javascript($do_deferred);

	return;
}

/**
 * Output the CSS files
 *
 * What it does:
 *  - If the admin option to combine files is set, will use Combiner.class
 */
function template_css()
{
	theme()->template_css();

	return;
}

/**
 * Calls on template_show_error from index.template.php to show warnings
 * and security errors for admins
 */
function template_admin_warning_above()
{
	theme()->template_admin_warning_above();

	return;
}

/**
 * Convert a single IP to a ranged IP.
 *
 * - Internal function used to convert a user-readable format to a format suitable for the database.
 *
 * @param string $fullip A full dot notation IP address
 *
 * @return array|string 'unknown' if the ip in the input was '255.255.255.255'
 */
function ip2range($fullip)
{
	// If its IPv6, validate it first.
	if (isValidIPv6($fullip))
	{
		$ip_parts = explode(':', expandIPv6($fullip, false));
		$ip_array = array();

		if (count($ip_parts) !== 8)
		{
			return array();
		}

		for ($i = 0; $i < 8; $i++)
		{
			if ($ip_parts[$i] === '*')
			{
				$ip_array[$i] = array('low' => '0', 'high' => hexdec('ffff'));
			}
			elseif (preg_match('/^([0-9A-Fa-f]{1,4})\-([0-9A-Fa-f]{1,4})$/', $ip_parts[$i], $range) == 1)
			{
				$ip_array[$i] = array('low' => hexdec($range[1]), 'high' => hexdec($range[2]));
			}
			elseif (is_numeric(hexdec($ip_parts[$i])))
			{
				$ip_array[$i] = array('low' => hexdec($ip_parts[$i]), 'high' => hexdec($ip_parts[$i]));
			}
		}

		return $ip_array;
	}

	// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
	if ($fullip === 'unknown')
	{
		$fullip = '255.255.255.255';
	}

	$ip_parts = explode('.', $fullip);
	$ip_array = array();

	if (count($ip_parts) !== 4)
	{
		return array();
	}

	for ($i = 0; $i < 4; $i++)
	{
		if ($ip_parts[$i] === '*')
		{
			$ip_array[$i] = array('low' => '0', 'high' => '255');
		}
		elseif (preg_match('/^(\d{1,3})\-(\d{1,3})$/', $ip_parts[$i], $range) == 1)
		{
			$ip_array[$i] = array('low' => $range[1], 'high' => $range[2]);
		}
		elseif (is_numeric($ip_parts[$i]))
		{
			$ip_array[$i] = array('low' => $ip_parts[$i], 'high' => $ip_parts[$i]);
		}
	}

	// Makes it simpler to work with.
	$ip_array[4] = array('low' => 0, 'high' => 0);
	$ip_array[5] = array('low' => 0, 'high' => 0);
	$ip_array[6] = array('low' => 0, 'high' => 0);
	$ip_array[7] = array('low' => 0, 'high' => 0);

	return $ip_array;
}

/**
 * Lookup an IP; try shell_exec first because we can do a timeout on it.
 *
 * @param string $ip A full dot notation IP address
 *
 * @return string
 * @throws \ElkArte\Exceptions\Exception
 */
function host_from_ip($ip)
{
	global $modSettings;

	$cache = Cache::instance();

	$host = '';
	if ($cache->getVar($host, 'hostlookup-' . $ip, 600) || empty($ip))
	{
		return $host;
	}

	$t = microtime(true);

	// Try the Linux host command, perhaps?
	if ((stripos(PHP_OS, 'win') === false || stripos(PHP_OS, 'darwin') !== false) && mt_rand(0, 1) === 1)
	{
		if (!isset($modSettings['host_to_dis']))
		{
			$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
		}
		else
		{
			$test = @shell_exec('host ' . @escapeshellarg($ip));
		}

		// Did host say it didn't find anything?
		if (strpos($test, 'not found') !== false)
		{
			$host = '';
		}
		// Invalid server option?
		elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset($modSettings['host_to_dis']))
		{
			updateSettings(array('host_to_dis' => 1));
		}
		// Maybe it found something, after all?
		elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
		{
			$host = $match[1];
		}
	}

	// This is nslookup; usually only Windows, but possibly some Unix?
	if (empty($host) && stripos(PHP_OS, 'win') !== false && stripos(PHP_OS, 'darwin') === false && mt_rand(0, 1) === 1)
	{
		$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));

		if (strpos($test, 'Non-existent domain') !== false)
		{
			$host = '';
		}
		elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
		{
			$host = $match[1];
		}
	}

	// This is the last try :/.
	if (!isset($host) || $host === false)
	{
		$host = @gethostbyaddr($ip);
	}

	// It took a long time, so let's cache it!
	if (microtime(true) - $t > 0.5)
	{
		$cache->put('hostlookup-' . $ip, $host, 600);
	}

	return $host;
}

/**
 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
 *
 * @param string $text The string to process
 *     - if encrypt = true this is the maximum number of bytes to use in integer hashes (for searching)
 *     - if encrypt = false this is the maximum number of letters in each word
 * @param bool $encrypt = false Used for custom search indexes to return an int[] array representing the words
 *
 * @return array
 */
function text2words($text, $encrypt = false)
{
	// Step 0: prepare numbers so they are good for search & index 1000.45 -> 1000_45
	$words = preg_replace('~([\d]+)[.-/]+(?=[\d])~u', '$1_', $text);

	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', strtr($words, array('<br />' => ' ')));

	// Step 2: Entities we left to letters, where applicable, lowercase.
	$words = un_htmlspecialchars(ElkArte\Util::strtolower($words));

	// Step 3: Ready to split apart and index!
	$words = explode(' ', $words);

	if ($encrypt)
	{
		$blocklist = getBlocklist();
		$returned_ints = [];

		// Only index unique words
		$words = array_unique($words);
		foreach ($words as $word)
		{
			$word = trim($word, '-_\'');
			if ($word !== '' && !in_array($word, $blocklist) && Util::strlen($word) > 2)
			{
				// Get a hex representation of this word using a database indexing hash
				// designed to be fast while maintaining a very low collision rate
				$encrypted = hash('FNV1A32', $word);

				// Create an integer representation, the hash is an 8 char hex
				// so the largest int will be 4294967295 which fits in db int(10)
				$returned_ints[$word] = hexdec($encrypted);
			}
		}

		return $returned_ints;
	}
	else
	{
		// Trim characters before and after and add slashes for database insertion.
		$returned_words = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				$returned_words[] = substr($word, 0, 20);
			}
		}

		// Filter out all words that occur more than once.
		return array_unique($returned_words);
	}
}

/**
 * Get the block list from the search controller.
 *
 * @return array
 */
function getBlocklist()
{
	static $blocklist;

	if (!isset($blocklist))
	{
		$search = new Search();
		$blocklist = $search->getBlockListedWords();
		unset($search);
	}

	return $blocklist;
}

/**
 * Sets up all of the top menu buttons
 *
 * What it does:
 *
 * - Defines every master item in the menu, as well as any sub-items
 * - Ensures the chosen action is set so the menu is highlighted
 * - Saves them in the cache if it is available and on
 * - Places the results in $context
 */
function setupMenuContext()
{
	return theme()->setupMenuContext();
}

/**
 * Process functions of an integration hook.
 *
 * What it does:
 *
 * - Calls all functions of the given hook.
 * - Supports static class method calls.
 *
 * @param string $hook The name of the hook to call
 * @param mixed[] $parameters = array() Parameters to pass to the hook
 *
 * @return mixed[] the results of the functions
 */
function call_integration_hook($hook, $parameters = array())
{
	return Hooks::instance()->hook($hook, $parameters);
}

/**
 * Includes files for hooks that only do that (i.e. integrate_pre_include)
 *
 * @param string $hook The name to include
 */
function call_integration_include_hook($hook)
{
	Hooks::instance()->include_hook($hook);
}

/**
 * Special hook call executed during obExit
 */
function call_integration_buffer()
{
	Hooks::instance()->buffer_hook();
}

/**
 * Add a function for integration hook.
 *
 * - Does nothing if the function is already added.
 *
 * @param string $hook The name of the hook to add
 * @param string $function The function associated with the hook
 * @param string $file The file that contains the function
 * @param bool $permanent = true if true, updates the value in settings table
 */
function add_integration_function($hook, $function, $file = '', $permanent = true)
{
	Hooks::instance()->add($hook, $function, $file, $permanent);
}

/**
 * Remove an integration hook function.
 *
 * What it does:
 *
 * - Removes the given function from the given hook.
 * - Does nothing if the function is not available.
 *
 * @param string $hook The name of the hook to remove
 * @param string $function The name of the function
 * @param string $file The file its located in
 */
function remove_integration_function($hook, $function, $file = '')
{
	Hooks::instance()->remove($hook, $function, $file);
}

/**
 * Decode numeric html entities to their UTF8 equivalent character.
 *
 * What it does:
 *
 * - Callback function for preg_replace_callback in subs-members
 * - Uses capture group 2 in the supplied array
 * - Does basic scan to ensure characters are inside a valid range
 *
 * @param mixed[] $matches matches from a preg_match_all
 *
 * @return string $string
 */
function replaceEntities__callback($matches)
{
	if (!isset($matches[2]))
	{
		return '';
	}

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// remove left to right / right to left overrides
	if ($num === 0x202D || $num === 0x202E)
	{
		return '';
	}

	// Quote, Ampersand, Apostrophe, Less/Greater Than get html replaced
	if (in_array($num, array(0x22, 0x26, 0x27, 0x3C, 0x3E)))
	{
		return '&#' . $num . ';';
	}

	// <0x20 are control characters, 0x20 is a space, > 0x10FFFF is past the end of the utf8 character set
	// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text)
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF))
	{
		return '';
	}

	// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and punctuation
	if ($num < 0x80)
	{
		return chr($num);
	}

	// <0x800 (2048)
	if ($num < 0x800)
	{
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	}

	// < 0x10000 (65536)
	if ($num < 0x10000)
	{
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	}

	// <= 0x10FFFF (1114111)
	return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
}

/**
 * Converts html entities to utf8 equivalents
 *
 * What it does:
 *
 * - Callback function for preg_replace_callback
 * - Uses capture group 1 in the supplied array
 * - Does basic checks to keep characters inside a viewable range.
 *
 * @param mixed[] $matches array of matches as output from preg_match_all
 *
 * @return string $string
 */
function fixchar__callback($matches)
{
	if (!isset($matches[1]))
	{
		return '';
	}

	$num = $matches[1][0] === 'x' ? hexdec(substr($matches[1], 1)) : (int) $matches[1];

	// <0x20 are control characters, > 0x10FFFF is past the end of the utf8 character set
	// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text), 0x202D-E are left to right overrides
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202D || $num === 0x202E)
	{
		return '';
	}

	// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and punctuation
	if ($num < 0x80)
	{
		return chr($num);
	}

	// <0x800 (2048)
	if ($num < 0x800)
	{
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	}

	// < 0x10000 (65536)
	if ($num < 0x10000)
	{
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	}

	// <= 0x10FFFF (1114111)
	return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
}

/**
 * Strips out invalid html entities, replaces others with html style &#123; codes
 *
 * What it does:
 *
 * - Callback function used of preg_replace_callback in various $ent_checks,
 * - For example strpos, strlen, substr etc
 *
 * @param mixed[] $matches array of matches for a preg_match_all
 *
 * @return string
 */
function entity_fix__callback($matches)
{
	if (!isset($matches[2]))
	{
		return '';
	}

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// We don't allow control characters, characters out of range, byte markers, etc
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
	{
		return '';
	}

	return '&#' . $num . ';';
}

/**
 * Retrieve additional search engines, if there are any, as an array.
 *
 * @return mixed[] array of engines
 */
function prepareSearchEngines()
{
	global $modSettings;

	$engines = array();
	if (!empty($modSettings['additional_search_engines']))
	{
		$search_engines = ElkArte\Util::unserialize($modSettings['additional_search_engines']);
		foreach ($search_engines as $engine)
		{
			$engines[strtolower(preg_replace('~[^A-Za-z0-9 ]~', '', $engine['name']))] = $engine;
		}
	}

	return $engines;
}

/**
 * This function receives a request handle and attempts to retrieve the next result.
 *
 * What it does:
 *
 * - It is used by the controller callbacks from the template, such as
 * posts in topic display page, posts search results page, or personal messages.
 *
 * @param resource $messages_request holds a query result
 * @param bool $reset
 *
 * @return int|bool
 * @throws \Exception
 */
function currentContext($messages_request, $reset = false)
{
	// Start from the beginning...
	if ($reset)
	{
		return $messages_request->data_seek(0);
	}

	// If the query has already returned false, get out of here
	if ($messages_request->hasResults())
	{
		return false;
	}

	// Attempt to get the next message.
	$message = $messages_request->fetch_assoc();
	if (!$message)
	{
		$messages_request->free_result();

		return false;
	}

	return $message;
}

/**
 * Helper function to insert an array in to an existing array
 *
 * What it does:
 *
 * - Intended for addon use to allow such things as
 * - Adding in a new menu item to an existing menu array
 *
 * @param mixed[] $input the array we will insert to
 * @param string $key the key in the array that we are looking to find for the insert action
 * @param mixed[] $insert the actual data to insert before or after the key
 * @param string $where adding before or after
 * @param bool $assoc if the array is a assoc array with named keys or a basic index array
 * @param bool $strict search for identical elements, this means it will also check the types of the needle.
 *
 * @return array|mixed[]
 */
function elk_array_insert($input, $key, $insert, $where = 'before', $assoc = true, $strict = false)
{
	$position = $assoc ? array_search($key, array_keys($input), $strict) : array_search($key, $input, $strict);

	// If the key is not found, just insert it at the end
	if ($position === false)
	{
		return array_merge($input, $insert);
	}

	if ($where === 'after')
	{
		$position++;
	}

	// Insert as first
	if (empty($position))
	{
		$input = array_merge($insert, $input);
	}
	else
	{
		$input = array_merge(array_slice($input, 0, $position), $insert, array_slice($input, $position));
	}

	return $input;
}

/**
 * Run a scheduled task now
 *
 * What it does:
 *
 * - From time to time it may be necessary to fire a scheduled task ASAP
 * - This function sets the scheduled task to be called before any other one
 *
 * @param string $task the name of a scheduled task
 * @throws \ElkArte\Exceptions\Exception
 */
function scheduleTaskImmediate($task)
{
	global $modSettings;

	if (!isset($modSettings['scheduleTaskImmediate']))
	{
		$scheduleTaskImmediate = array();
	}
	else
	{
		$scheduleTaskImmediate = Util::unserialize($modSettings['scheduleTaskImmediate']);
	}

	// If it has not been scheduled, the do so now
	if (!isset($scheduleTaskImmediate[$task]))
	{
		$scheduleTaskImmediate[$task] = 0;
		updateSettings(array('scheduleTaskImmediate' => serialize($scheduleTaskImmediate)));

		require_once(SUBSDIR . '/ScheduledTasks.subs.php');

		// Ensure the task is on
		toggleTaskStatusByName($task, true);

		// Before trying to run it **NOW** :P
		calculateNextTrigger($task, true);
	}
}

/**
 * For diligent people: remove scheduleTaskImmediate when done, otherwise
 * a maximum of 10 executions is allowed
 *
 * @param string $task the name of a scheduled task
 * @param bool $calculateNextTrigger if recalculate the next task to execute
 * @throws \ElkArte\Exceptions\Exception
 */
function removeScheduleTaskImmediate($task, $calculateNextTrigger = true)
{
	global $modSettings;

	// Not on, bail
	if (!isset($modSettings['scheduleTaskImmediate']))
	{
		return;
	}
	else
	{
		$scheduleTaskImmediate = Util::unserialize($modSettings['scheduleTaskImmediate']);
	}

	// Clear / remove the task if it was set
	if (isset($scheduleTaskImmediate[$task]))
	{
		unset($scheduleTaskImmediate[$task]);
		updateSettings(array('scheduleTaskImmediate' => serialize($scheduleTaskImmediate)));

		// Recalculate the next task to execute
		if ($calculateNextTrigger)
		{
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');
			calculateNextTrigger($task);
		}
	}
}

/**
 * Helper function to replace commonly used urls in text strings
 *
 * @event integrate_basic_url_replacement add additional place holder replacements
 * @param string $string the string to inject URLs into
 *
 * @return string the input string with the place-holders replaced with
 *           the correct URLs
 */
function replaceBasicActionUrl($string)
{
	global $scripturl, $context, $boardurl;
	static $find_replace = null;

	if ($find_replace === null)
	{
		$find_replace = array(
			'{forum_name}' => $context['forum_name'],
			'{forum_name_html_safe}' => $context['forum_name_html_safe'],
			'{forum_name_html_unsafe}' => un_htmlspecialchars($context['forum_name_html_safe']),
			'{script_url}' => $scripturl,
			'{board_url}' => $boardurl,
			'{login_url}' => getUrl('action', ['action' => 'login']),
			'{register_url}' => getUrl('action', ['action' => 'register']),
			'{activate_url}' => getUrl('action', ['action' => 'register', 'sa' => 'activate']),
			'{help_url}' => getUrl('action', ['action' => 'help']),
			'{admin_url}' => getUrl('admin', ['action' => 'admin']),
			'{moderate_url}' => getUrl('moderate', ['action' => 'moderate']),
			'{recent_url}' => getUrl('action', ['action' => 'recent']),
			'{search_url}' => getUrl('action', ['action' => 'search']),
			'{who_url}' => getUrl('action', ['action' => 'who']),
			'{credits_url}' => getUrl('action', ['action' => 'who', 'sa' => 'credits']),
			'{calendar_url}' => getUrl('action', ['action' => 'calendar']),
			'{memberlist_url}' => getUrl('action', ['action' => 'memberlist']),
			'{stats_url}' => getUrl('action', ['action' => 'stats']),
		);
		call_integration_hook('integrate_basic_url_replacement', array(&$find_replace));
	}

	return str_replace(array_keys($find_replace), array_values($find_replace), $string);
}

/**
 * This function creates a new GenericList from all the passed options.
 *
 * What it does:
 *
 * - Calls integration hook integrate_list_"unique_list_id" to allow easy modifying
 *
 * @event integrate_list_$listID called before every createlist to allow access to its listoptions
 * @param mixed[] $listOptions associative array of option => value
 */
function createList($listOptions)
{
	call_integration_hook('integrate_list_' . $listOptions['id'], array(&$listOptions));

	$list = new GenericList($listOptions);

	$list->buildList();
}

/**
 * This handy function retrieves a Request instance and passes it on.
 *
 * What it does:
 *
 * - To get hold of a Request, you can use this function or directly Request::instance().
 * - This is for convenience, it simply delegates to Request::instance().
 */
function request()
{
	return ElkArte\Request::instance();
}

/**
 * Meant to replace any usage of $db_last_error.
 *
 * What it does:
 *
 * - Reads the file db_last_error.txt, if a time() is present returns it,
 * otherwise returns 0.
 */
function db_last_error()
{
	$time = trim(file_get_contents(BOARDDIR . '/db_last_error.txt'));

	if (preg_match('~^\d{10}$~', $time) === 1)
	{
		return $time;
	}

	return 0;
}

/**
 * This function has the only task to retrieve the correct prefix to be used
 * in responses.
 *
 * @return string - The prefix in the default language of the forum
 */
function response_prefix()
{
	global $language, $txt;
	static $response_prefix = null;

	$cache = Cache::instance();

	// Get a response prefix, but in the forum's default language.
	if ($response_prefix === null && (!$cache->getVar($response_prefix, 'response_prefix') || !$response_prefix))
	{
		if ($language === User::$info->language)
		{
			$response_prefix = $txt['response_prefix'];
		}
		else
		{
			\ElkArte\Themes\ThemeLoader::loadLanguageFile('index', $language, false);
			$response_prefix = $txt['response_prefix'];
			\ElkArte\Themes\ThemeLoader::loadLanguageFile('index');
		}

		$cache->put('response_prefix', $response_prefix, 600);
	}

	return $response_prefix;
}

/**
 * A very simple function to determine if an email address is "valid" for Elkarte.
 *
 * - A valid email for ElkArte is something that resembles an email (filter_var) and
 * is less than 255 characters (for database limits)
 *
 * @param string $value - The string to evaluate as valid email
 *
 * @return string|false - The email if valid, false if not a valid email
 */
function isValidEmail($value)
{
	$value = trim($value);
	if (filter_var($value, FILTER_VALIDATE_EMAIL) && ElkArte\Util::strlen($value) < 255)
	{
		return $value;
	}

	return false;
}

/**
 * Adds a protocol (http/s, ftp/mailto) to the beginning of an url if missing
 *
 * @param string $url - The url
 * @param string[] $protocols - A list of protocols to check, the first is
 *                 added if none is found (optional, default array('http://', 'https://'))
 *
 * @return string - The url with the protocol
 */
function addProtocol($url, $protocols = array())
{
	if (empty($protocols))
	{
		$pattern = '~^(http://|https://)~i';
		$protocols = array('http://');
	}
	else
	{
		$pattern = '~^(' . implode('|', array_map(function ($val) {
				return preg_quote($val, '~');
			}, $protocols)) . ')~i';
	}

	$found = false;
	$url = preg_replace_callback($pattern, function ($match) use (&$found) {
		$found = true;

		return strtolower($match[0]);
	}, $url);

	if ($found)
	{
		return $url;
	}

	return $protocols[0] . $url;
}

/**
 * Removes nested quotes from a text string.
 *
 * @param string $text - The body we want to remove nested quotes from
 *
 * @return string - The same body, just without nested quotes
 */
function removeNestedQuotes($text)
{
	global $modSettings;

	// Remove any nested quotes, if necessary.
	if (!empty($modSettings['removeNestedQuotes']))
	{
		return preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $text);
	}

	return $text;
}

/**
 * Change a \t to a span that will show a tab
 *
 * @param string $string
 *
 * @return string
 */
function tabToHtmlTab($string)
{
	return str_replace("\t", "<span class=\"tab\">\t</span>", $string);
}

/**
 * Remove <br />
 *
 * @param string $string
 *
 * @return string
 */
function removeBr($string)
{
	return str_replace('<br />', '', $string);
}

/**
 * Replace all vulgar words with respective proper words. (substring or whole words..)
 *
 * What it does:
 * - it censors the passed string.
 * - if the admin setting allow_no_censored is on it does not censor unless force is also set.
 * - if the admin setting allow_no_censored is off will censor words unless the user has set
 * it to not censor in their profile and force is off
 * - it caches the list of censored words to reduce parsing.
 * - Returns the censored text
 *
 * @param string $text
 * @param bool $force = false
 *
 * @return string
 */
function censor($text, $force = false)
{
	global $modSettings;
	static $censor = null;

	if ($censor === null)
	{
		$censor = new Censor(explode("\n", $modSettings['censor_vulgar']), explode("\n", $modSettings['censor_proper']), $modSettings);
	}

	return $censor->censor($text, $force);
}

/**
 * Helper function able to determine if the current member can see at least
 * one button of a button strip.
 *
 * @param mixed[] $button_strip
 *
 * @return bool
 */
function can_see_button_strip($button_strip)
{
	global $context;

	foreach ($button_strip as $key => $value)
	{
		if (!isset($value['test']) || !empty($context[$value['test']]))
		{
			return true;
		}
	}

	return false;
}

/**
 * @return \ElkArte\Themes\DefaultTheme\Theme
 */
function theme()
{
	return $GLOBALS['context']['theme_instance'];
}

/**
 * Stops the execution with a 1x1 gif file
 *
 * @param bool $expired Sends an expired header.
 */
function dieGif($expired = false)
{
	// The following is an attempt at stopping the behavior identified in #2391
	if (function_exists('fastcgi_finish_request'))
	{
		die();
	}

	$headers = Headers::instance();
	if ($expired)
	{
		$headers
			->header('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT')
			->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
	}

	$headers->contentType('image/gif')->sendHeaders();
	die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}

/**
 * Prepare ob_start with or without gzip compression
 *
 * @param bool $use_compression Starts compressed headers.
 */
function obStart($use_compression = false)
{
	// This is done to clear any output that was made before now.
	while (ob_get_level() > 0)
	{
		@ob_end_clean();
	}

	if ($use_compression)
	{
		ob_start('ob_gzhandler');
	}
	else
	{
		ob_start();
		Headers::instance()->header('Content-Encoding', 'none');
	}
}

/**
 * Returns an URL based on the parameters passed and the selected generator
 *
 * @param string $type The type of the URL (depending on the type, the
 *                     generator can act differently
 * @param mixed[] $params All the parameters of the URL
 *
 * @return string An URL
 */
function getUrl($type, $params)
{
	static $generator = null;

	if ($generator === null)
	{
		$generator = initUrlGenerator();
	}

	return $generator->get($type, $params);
}

/**
 * Returns the query part of an URL based on the parameters passed and the selected generator
 *
 * @param string $type The type of the URL (depending on the type, the
 *                     generator can act differently
 * @param mixed[] $params All the parameters of the URL
 *
 * @return string The query part of an URL
 */
function getUrlQuery($type, $params)
{
	static $generator = null;

	if ($generator === null)
	{
		$generator = initUrlGenerator();
	}

	return $generator->getQuery($type, $params);
}

/**
 * Initialize the URL generator
 *
 * @return object The URL generator object
 */
function initUrlGenerator()
{
	global $scripturl, $context, $url_format;

	$generator = new UrlGenerator([
		'generator' => ucfirst($url_format ?? 'standard'),
		'scripturl' => $scripturl,
		'replacements' => [
			'{session_data}' => isset($context['session_var']) ? $context['session_var'] . '=' . $context['session_id'] : ''
		]
	]);

	$generator->register('Topic');
	$generator->register('Board');
	$generator->register('Profile');

	return $generator;
}

/**
 * This function only checks if a certain feature (in core features)
 * is enabled or not.
 *
 * @param string $feature The abbreviated code of a core feature
 * @return bool true/false for enabled/disabled
 */
function featureEnabled($feature)
{
	global $modSettings, $context;
	static $features = null;

	if ($features === null)
	{
		// This allows us to change the way things look for the admin.
		$features = explode(',', $modSettings['admin_features'] ?? 'cd,cp,k,w,rg,ml,pm');

		// @deprecated since 2.0 - Next line is just for backward compatibility to remove before release
		$context['admin_features'] = $features;
	}

	return in_array($feature, $features);
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 *
 * What it does:
 *
 * - Removes invalid XML characters to assure the input string being
 * parsed properly.
 *
 * @param string $string The string to clean
 *
 * @return string The clean string
 */
function cleanXml($string)
{
	// http://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{FFFE}\x{FFFF}]~u', '', $string);
}

/**
 * Validates a IPv6 address. returns true if it is ipv6.
 *
 * @param string $ip ip address to be validated
 *
 * @return bool true|false
 */
function isValidIPv6($ip)
{
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}

/**
 * Converts IPv6s to numbers.  This makes ban checks much easier.
 *
 * @param string $ip ip address to be converted
 *
 * @return int[] array
 */
function convertIPv6toInts($ip)
{
	static $expanded = array();

	// Check if we have done this already.
	if (isset($expanded[$ip]))
	{
		return $expanded[$ip];
	}

	// Expand the IP out.
	$expanded_ip = explode(':', expandIPv6($ip));

	$new_ip = array();
	foreach ($expanded_ip as $int)
	{
		$new_ip[] = hexdec($int);
	}

	// Save this in case of repeated use.
	$expanded[$ip] = $new_ip;

	return $expanded[$ip];
}

/**
 * Expands a IPv6 address to its full form.
 *
 * @param string $addr ipv6 address string
 * @param bool $strict_check checks length to expanded address for compliance
 *
 * @return bool|string expanded ipv6 address.
 */
function expandIPv6($addr, $strict_check = true)
{
	static $converted = array();

	// Check if we have done this already.
	if (isset($converted[$addr]))
	{
		return $converted[$addr];
	}

	// Check if there are segments missing, insert if necessary.
	if (strpos($addr, '::') !== false)
	{
		$part = explode('::', $addr);
		$part[0] = explode(':', $part[0]);
		$part[1] = explode(':', $part[1]);
		$missing = array();

		// Looks like this is an IPv4 address
		if (isset($part[1][1]) && strpos($part[1][1], '.') !== false)
		{
			$ipoct = explode('.', $part[1][1]);
			$p1 = dechex($ipoct[0]) . dechex($ipoct[1]);
			$p2 = dechex($ipoct[2]) . dechex($ipoct[3]);

			$part[1] = array(
				$part[1][0],
				$p1,
				$p2
			);
		}

		$limit = count($part[0]) + count($part[1]);
		for ($i = 0; $i < (8 - $limit); $i++)
		{
			array_push($missing, '0000');
		}

		$part = array_merge($part[0], $missing, $part[1]);
	}
	else
	{
		$part = explode(':', $addr);
	}

	// Pad each segment until it has 4 digits.
	foreach ($part as &$p)
	{
		while (strlen($p) < 4)
		{
			$p = '0' . $p;
		}
	}

	unset($p);

	// Join segments.
	$result = implode(':', $part);

	// Save this in case of repeated use.
	$converted[$addr] = $result;

	// Quick check to make sure the length is as expected.
	if (!$strict_check || strlen($result) == 39)
	{
		return $result;
	}

	return false;
}
