<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Update some basic statistics.
 *
 * 'member' statistic updates the latest member, the total member
 *  count, and the number of unapproved members.
 * 'member' also only counts approved members when approval is on, but
 *  is much more efficient with it off.
 *
 * 'message' changes the total number of messages, and the
 *  highest message id by id_msg - which can be parameters 1 and 2,
 *  respectively.
 *
 * 'topic' updates the total number of topics, or if parameter1 is true
 *  simply increments them.
 *
 * 'subject' updateds the log_search_subjects in the event of a topic being
 *  moved, removed or split.  parameter1 is the topicid, parameter2 is the new subject
 *
 * 'postgroups' case updates those members who match condition's
 *  post-based membergroups in the database (restricted by parameter1).
 *
 * @param string $type Stat type - can be 'member', 'message', 'topic', 'subject' or 'postgroups'
 * @param int|string|false|mixed[]|null $parameter1 pass through value
 * @param int|string|false|mixed[]|null $parameter2 pass through value
 */
function updateStats($type, $parameter1 = null, $parameter2 = null)
{
	switch ($type)
	{
		case 'member':
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberStats($parameter1, $parameter2);
			break;
		case 'message':
			require_once(SUBSDIR . '/Messages.subs.php');
			updateMessageStats($parameter1, $parameter2);
			break;
		case 'subject':
			require_once(SUBSDIR . '/Messages.subs.php');
			updateSubjectStats($parameter1, $parameter2);
			break;
		case 'topic':
			require_once(SUBSDIR . '/Topic.subs.php');
			updateTopicStats($parameter1);
			break;
		case 'postgroups':
			require_once(SUBSDIR . '/Membergroups.subs.php');
			updatePostGroupStats($parameter1, $parameter2);
			break;
		default:
			trigger_error('updateStats(): Invalid statistic type \'' . $type . '\'', E_USER_NOTICE);
	}
}

/**
 * Updates the columns in the members table.
 * Assumes the data has been htmlspecialchar'd, no sanitization is performed on the data.
 * this function should be used whenever member data needs to be updated in place of an UPDATE query.
 *
 * $data is an associative array of the columns to be updated and their respective values.
 * any string values updated should be quoted and slashed.
 *
 * The value of any column can be '+' or '-', which mean 'increment' and decrement, respectively.
 *
 * If the member's post number is updated, updates their post groups.
 *
 * @param int[]|int $members An array of member ids
 * @param mixed[] $data An associative array of the columns to be updated and their respective values.
 */
function updateMemberData($members, $data)
{
	global $modSettings, $user_info;

	$db = database();

	$parameters = array();
	if (is_array($members))
	{
		$condition = 'id_member IN ({array_int:members})';
		$parameters['members'] = $members;
	}
	elseif ($members === null)
		$condition = '1=1';
	else
	{
		$condition = 'id_member = {int:member}';
		$parameters['member'] = $members;
	}

	// Everything is assumed to be a string unless it's in the below.
	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'personal_messages', 'unread_messages', 'mentions',
		'new_pm', 'pm_prefs', 'gender', 'hide_email', 'show_online', 'pm_email_notify', 'receive_from', 'karma_good', 'karma_bad',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning', 'likes_given', 'likes_received',
	);
	$knownFloats = array(
		'time_offset',
	);

	if (!empty($modSettings['integrate_change_member_data']))
	{
		// Only a few member variables are really interesting for integration.
		$integration_vars = array(
			'member_name',
			'real_name',
			'email_address',
			'id_group',
			'gender',
			'birthdate',
			'website_title',
			'website_url',
			'location',
			'hide_email',
			'time_format',
			'time_offset',
			'avatar',
			'lngfile',
		);
		$vars_to_integrate = array_intersect($integration_vars, array_keys($data));

		// Only proceed if there are any variables left to call the integration function.
		if (count($vars_to_integrate) != 0)
		{
			// Fetch a list of member_names if necessary
			if ((!is_array($members) && $members === $user_info['id']) || (is_array($members) && count($members) == 1 && in_array($user_info['id'], $members)))
				$member_names = array($user_info['username']);
			else
			{
				$member_names = array();
				$request = $db->query('', '
					SELECT member_name
					FROM {db_prefix}members
					WHERE ' . $condition,
					$parameters
				);
				while ($row = $db->fetch_assoc($request))
					$member_names[] = $row['member_name'];
				$db->free_result($request);
			}

			if (!empty($member_names))
				foreach ($vars_to_integrate as $var)
					call_integration_hook('integrate_change_member_data', array($member_names, &$var, &$data[$var], &$knownInts, &$knownFloats));
		}
	}

	$setString = '';
	foreach ($data as $var => $val)
	{
		$type = 'string';

		if (in_array($var, $knownInts))
			$type = 'int';
		elseif (in_array($var, $knownFloats))
			$type = 'float';
		elseif ($var == 'birthdate')
			$type = 'date';

		// Doing an increment?
		if ($type == 'int' && ($val === '+' || $val === '-'))
		{
			$val = $var . ' ' . $val . ' 1';
			$type = 'raw';
		}

		// Ensure posts, personal_messages, and unread_messages don't overflow or underflow.
		if (in_array($var, array('posts', 'personal_messages', 'unread_messages')))
		{
			if (preg_match('~^' . $var . ' (\+ |- |\+ -)([\d]+)~', $val, $match))
			{
				if ($match[1] != '+ ')
					$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
				$type = 'raw';
			}
		}

		$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
		$parameters['p_' . $var] = $val;
	}

	$db->query('', '
		UPDATE {db_prefix}members
		SET' . substr($setString, 0, -1) . '
		WHERE ' . $condition,
		$parameters
	);

	updateStats('postgroups', $members, array_keys($data));

	// Clear any caching?
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && !empty($members))
	{
		if (!is_array($members))
			$members = array($members);

		foreach ($members as $member)
		{
			if ($modSettings['cache_enable'] >= 3)
			{
				cache_put_data('member_data-profile-' . $member, null, 120);
				cache_put_data('member_data-normal-' . $member, null, 120);
				cache_put_data('member_data-minimal-' . $member, null, 120);
			}

			cache_put_data('user_settings-' . $member, null, 60);
		}
	}
}

/**
 * Updates the settings table as well as $modSettings... only does one at a time if $update is true.
 *
 * - updates both the settings table and $modSettings array.
 * - all of changeArray's indexes and values are assumed to have escaped apostrophes (')!
 * - if a variable is already set to what you want to change it to, that
 *   variable will be skipped over; it would be unnecessary to reset.
 * - When update is true, UPDATEs will be used instead of REPLACE.
 * - when update is true, the value can be true or false to increment
 *  or decrement it, respectively.
 *
 * @param mixed[] $changeArray associative array of variable => value
 * @param bool $update = false
 * @param bool $debug = false
 * @todo: add debugging features, $debug isn't used
 */
function updateSettings($changeArray, $update = false, $debug = false)
{
	global $modSettings;

	$db = database();

	if (empty($changeArray) || !is_array($changeArray))
		return;

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
		cache_put_data('modSettings', null, 90);

		return;
	}

	$replaceArray = array();
	foreach ($changeArray as $variable => $value)
	{
		// Don't bother if it's already like that ;).
		if (isset($modSettings[$variable]) && $modSettings[$variable] == $value)
			continue;
		// If the variable isn't set, but would only be set to nothing'ness, then don't bother setting it.
		elseif (!isset($modSettings[$variable]) && empty($value))
			continue;

		$replaceArray[] = array($variable, $value);

		$modSettings[$variable] = $value;
	}

	if (empty($replaceArray))
		return;

	$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-65534'),
		$replaceArray,
		array('variable')
	);

	// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
	cache_put_data('modSettings', null, 90);
}

/**
 * Deletes one setting from the settings table and takes care of $modSettings as well
 *
 * @param string $toRemove the setting or the settings to be removed
 */
function removeSettings($toRemove)
{
	global $modSettings;

	$db = database();

	if (empty($toRemove))
		return;

	if (!is_array($toRemove))
		$toRemove = array($toRemove);

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
		if (isset($modSettings[$setting]))
			unset($modSettings[$setting]);

	// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
	cache_put_data('modSettings', null, 90);
}

/**
 * Constructs a page list.
 *
 * - builds the page list, e.g. 1 ... 6 7 [8] 9 10 ... 15.
 * - flexible_start causes it to use "url.page" instead of "url;start=page".
 * - very importantly, cleans up the start value passed, and forces it to
 *   be a multiple of num_per_page.
 * - checks that start is not more than max_value.
 * - base_url should be the URL without any start parameter on it.
 * - uses the compactTopicPagesEnable and compactTopicPagesContiguous
 *   settings to decide how to display the menu.
 *
 * an example is available near the function definition.
 * $pageindex = constructPageIndex($scripturl . '?board=' . $board, $_REQUEST['start'], $num_messages, $maxindex, true);
 *
 * @param string $base_url
 * @param int $start
 * @param int $max_value
 * @param int $num_per_page
 * @param bool $flexible_start = false
 * @param mixed[] $show associative array of option => boolean
 */
function constructPageIndex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show = array())
{
	global $modSettings, $context, $txt, $settings;

	// Save whether $start was less than 0 or not.
	$start = (int) $start;
	$start_invalid = $start < 0;
	$show_defaults = array(
		'prev_next' => true,
		'all' => false,
	);

	$show = array_merge($show_defaults, $show);

	// Make sure $start is a proper variable - not less than 0.
	if ($start_invalid)
		$start = 0;
	// Not greater than the upper bound.
	elseif ($start >= $max_value)
		$start = max(0, (int) $max_value - (((int) $max_value % (int) $num_per_page) == 0 ? $num_per_page : ((int) $max_value % (int) $num_per_page)));
	// And it has to be a multiple of $num_per_page!
	else
		$start = max(0, (int) $start - ((int) $start % (int) $num_per_page));

	$context['current_page'] = $start / $num_per_page;

	$base_link = str_replace('{base_link}', ($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d'), $settings['page_index_template']['base_link']);

	// Compact pages is off or on?
	if (empty($modSettings['compactTopicPagesEnable']))
	{
		// Show the left arrow.
		$pageindex = $start == 0 ? ' ' : sprintf($base_link, $start - $num_per_page, str_replace('{prev_txt}', $txt['prev'], $settings['page_index_template']['previous_page']));

		// Show all the pages.
		$display_page = 1;
		for ($counter = 0; $counter < $max_value; $counter += $num_per_page)
			$pageindex .= $start == $counter && !$start_invalid ? sprintf($settings['page_index_template']['current_page'], $display_page++) : sprintf($base_link, $counter, $display_page++);

		// Show the right arrow.
		$display_page = ($start + $num_per_page) > $max_value ? $max_value : ($start + $num_per_page);
		if ($start != $counter - $max_value && !$start_invalid)
			$pageindex .= $display_page > $counter - $num_per_page ? ' ' : sprintf($base_link, $display_page, str_replace('{next_txt}', $txt['next'], $settings['page_index_template']['next_page']));

		// The "all" button
		if ($show['all'])
		{
			if ($show['all_selected'])
				$pageindex .= sprintf($settings['page_index_template']['current_page'], $txt['all']);
			else
				$pageindex .= sprintf(str_replace('.%1$d', '.%1$s', $base_link), '0;all', str_replace('{all_txt}', $txt['all'], $settings['page_index_template']['all']));
		}
	}
	else
	{
		// If they didn't enter an odd value, pretend they did.
		$PageContiguous = (int) ($modSettings['compactTopicPagesContiguous'] - ($modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		// Show the "prev page" link. (>prev page< 1 ... 6 7 [8] 9 10 ... 15 next page)
		if (!empty($start) && $show['prev_next'])
			$pageindex = sprintf($base_link, $start - $num_per_page, str_replace('{prev_txt}', $txt['prev'], $settings['page_index_template']['previous_page']));
		else
			$pageindex = '';

		// Show the first page. (prev page >1< ... 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * $PageContiguous)
			$pageindex .= sprintf($base_link, 0, '1');

		// Show the ... after the first page.  (prev page 1 >...< 6 7 [8] 9 10 ... 15 next page)
		if ($start > $num_per_page * ($PageContiguous + 1))
			$pageindex .= str_replace('{custom}', 'data-baseurl="' . htmlspecialchars(JavaScriptEscape(($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d')), ENT_COMPAT, 'UTF-8') . '" data-perpage="' . $num_per_page . '" data-firstpage="' . $num_per_page . '" data-lastpage="' . ($start - $num_per_page * $PageContiguous) . '"', $settings['page_index_template']['expand_pages']);

		// Show the pages before the current one. (prev page 1 ... >6 7< [8] 9 10 ... 15 next page)
		for ($nCont = $PageContiguous; $nCont >= 1; $nCont--)
			if ($start >= $num_per_page * $nCont)
			{
				$tmpStart = $start - $num_per_page * $nCont;
				$pageindex.= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
		if (!$start_invalid)
			$pageindex .= sprintf($settings['page_index_template']['current_page'], ($start / $num_per_page + 1));
		else
			$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);

		// Show the pages after the current one... (prev page 1 ... 6 7 [8] >9 10< ... 15 next page)
		$tmpMaxPages = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
		for ($nCont = 1; $nCont <= $PageContiguous; $nCont++)
			if ($start + $num_per_page * $nCont <= $tmpMaxPages)
			{
				$tmpStart = $start + $num_per_page * $nCont;
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the '...' part near the end. (prev page 1 ... 6 7 [8] 9 10 >...< 15 next page)
		if ($start + $num_per_page * ($PageContiguous + 1) < $tmpMaxPages)
			$pageindex .= str_replace('{custom}', 'data-baseurl="' . htmlspecialchars(JavaScriptEscape(($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d')), ENT_COMPAT, 'UTF-8') . '" data-perpage="' . $num_per_page . '" data-firstpage="' . ($start + $num_per_page * ($PageContiguous + 1)) . '" data-lastpage="' . $tmpMaxPages . '"', $settings['page_index_template']['expand_pages']);

		// Show the last number in the list. (prev page 1 ... 6 7 [8] 9 10 ... >15<  next page)
		if ($start + $num_per_page * $PageContiguous < $tmpMaxPages)
			$pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);

		// Show the "next page" link. (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
		if ($start != $tmpMaxPages && $show['prev_next'])
			$pageindex .= sprintf($base_link, $start + $num_per_page, str_replace('{next_txt}', $txt['next'], $settings['page_index_template']['next_page']));

		// The "all" button
		if ($show['all'])
		{
			if ($show['all_selected'])
				$pageindex .= sprintf($settings['page_index_template']['current_page'], $txt['all']);
			else
				$pageindex .= sprintf(str_replace('.%1$d', '.%1$s', $base_link), '0;all', str_replace('{all_txt}', $txt['all'], $settings['page_index_template']['all']));
		}
	}

	return $pageindex;
}

/**
 * Formats a number.
 * - uses the format of number_format to decide how to format the number.
 *   for example, it might display "1 234,50".
 * - caches the formatting data from the setting for optimization.
 *
 * @param float $number
 * @param integer|false $override_decimal_count = false or number of decimals
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
			return $number;

		// Cache these each load...
		$thousands_separator = $matches[1];
		$decimal_separator = $matches[2];
		$decimal_count = strlen($matches[3]);
	}

	// Format the string with our friend, number_format.
	return number_format($number, (float) $number === $number ? ($override_decimal_count === false ? $decimal_count : $override_decimal_count) : 0, $decimal_separator, $thousands_separator);
}

/**
 * Format a time to make it look purdy.
 *
 * - returns a pretty formated version of time based on the user's format in $user_info['time_format'].
 * - applies all necessary time offsets to the timestamp, unless offset_type is set.
 * - if todayMod is set and show_today was not not specified or true, an
 *   alternate format string is used to show the date with something to show it is "today" or "yesterday".
 * - performs localization (more than just strftime would do alone.)
 *
 * @param int $log_time
 * @param string|bool $show_today = true
 * @param string|false $offset_type = false
 */
function standardTime($log_time, $show_today = true, $offset_type = false)
{
	global $context, $user_info, $txt, $modSettings;
	static $non_twelve_hour;

	// Offset the time.
	if (!$offset_type)
		$time = $log_time + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600;
	// Just the forum offset?
	elseif ($offset_type == 'forum')
		$time = $log_time + $modSettings['time_offset'] * 3600;
	else
		$time = $log_time;

	// We can't have a negative date (on Windows, at least.)
	if ($log_time < 0)
		$log_time = 0;

	// Today and Yesterday?
	if ($modSettings['todayMod'] >= 1 && $show_today === true)
	{
		// Get the current time.
		$nowtime = forum_time();

		$then = @getdate($time);
		$now = @getdate($nowtime);

		// Try to make something of a time format string...
		$s = strpos($user_info['time_format'], '%S') === false ? '' : ':%S';
		if (strpos($user_info['time_format'], '%H') === false && strpos($user_info['time_format'], '%T') === false)
		{
			$h = strpos($user_info['time_format'], '%l') === false ? '%I' : '%l';
			$today_fmt = $h . ':%M' . $s . ' %p';
		}
		else
			$today_fmt = '%H:%M' . $s;

		// Same day of the year, same year.... Today!
		if ($then['yday'] == $now['yday'] && $then['year'] == $now['year'])
			return $txt['today'] . standardTime($log_time, $today_fmt, $offset_type);

		// Day-of-year is one less and same year, or it's the first of the year and that's the last of the year...
		if ($modSettings['todayMod'] == '2' && (($then['yday'] == $now['yday'] - 1 && $then['year'] == $now['year']) || ($now['yday'] == 0 && $then['year'] == $now['year'] - 1) && $then['mon'] == 12 && $then['mday'] == 31))
			return $txt['yesterday'] . standardTime($log_time, $today_fmt, $offset_type);
	}

	$str = !is_bool($show_today) ? $show_today : $user_info['time_format'];

	if (setlocale(LC_TIME, $txt['lang_locale']))
	{
		if (!isset($non_twelve_hour))
			$non_twelve_hour = trim(strftime('%p')) === '';
		if ($non_twelve_hour && strpos($str, '%p') !== false)
			$str = str_replace('%p', (strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm']), $str);

		foreach (array('%a', '%A', '%b', '%B') as $token)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, !empty($txt['lang_capitalize_dates']) ? Util::ucwords(strftime($token, $time)) : strftime($token, $time), $str);
	}
	else
	{
		// Do-it-yourself time localization.  Fun.
		foreach (array('%a' => 'days_short', '%A' => 'days', '%b' => 'months_short', '%B' => 'months') as $token => $text_label)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, $txt[$text_label][(int) strftime($token === '%a' || $token === '%A' ? '%w' : '%m', $time)], $str);

		if (strpos($str, '%p') !== false)
			$str = str_replace('%p', (strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm']), $str);
	}

	// Windows doesn't support %e; on some versions, strftime fails altogether if used, so let's prevent that.
	if ($context['server']['is_windows'] && strpos($str, '%e') !== false)
		$str = str_replace('%e', ltrim(strftime('%d', $time), '0'), $str);

	// Format any other characters..
	return strftime($str, $time);
}

/**
 * Used to render a timestamp to html5 <time> tag format.
 *
 * @param int $timestamp
 * @return string
 */
function htmlTime($timestamp)
{
	if (empty($timestamp))
		return '';

	$timestamp = forum_time(true, $timestamp);
	$time = date('Y-m-d H:i', $timestamp);
	$stdtime = standardTime($timestamp, true, true);

	// @todo maybe htmlspecialchars on the title attribute?
	return '<time title="' . $stdtime . '" datetime="' . $time . '" data-timestamp="' . $timestamp . '">' . $stdtime . '</time>';
}

/**
 * Gets the current time with offset.
 *
 * - always applies the offset in the time_offset setting.
 *
 * @param bool $use_user_offset = true if use_user_offset is true, applies the user's offset as well
 * @param int|null $timestamp = null
 * @return int seconds since the unix epoch
 */
function forum_time($use_user_offset = true, $timestamp = null)
{
	global $user_info, $modSettings;

	if ($timestamp === null)
		$timestamp = time();
	elseif ($timestamp == 0)
		return 0;

	return $timestamp + ($modSettings['time_offset'] + ($use_user_offset ? $user_info['time_offset'] : 0)) * 3600;
}

/**
 * Removes special entities from strings.  Compatibility...
 * Faster than html_entity_decode
 *
 * - removes the base entities ( &amp; &quot; &#039; &lt; and &gt;. ) from text with htmlspecialchars_decode
 * - additionally converts &nbsp with str_replace
 *
 * @param string $string
 * @return string string without entities
 */
function un_htmlspecialchars($string)
{
	$string = htmlspecialchars_decode($string, ENT_QUOTES);
	$string = str_replace('&nbsp;', ' ', $string);

	return $string;
}

/**
 * Calculates all the possible permutations (orders) of an array.
 *	- should not be called on arrays bigger than 10 elements as this function is memory hungry
 *  - returns an array containing each permutation.
 *  - e.g. (1,2,3) returns (1,2,3), (1,3,2), (2,1,3), (2,3,1), (3,1,2), and (3,2,1)
 *  - really a combinations without repetition N! function so 3! = 6 and 10! = 4098 combinations
 *
 * Used by parse_bbc to allow bbc tag parameters to be in any order and still be
 * parsed properly
 *
 * @param mixed[] $array index array of values
 * @return mixed[] array representing all premutations of the supplied array
 */
function permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] == 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

/**
 * Ends execution and redirects the user to a new location
 * Makes sure the browser doesn't come back and repost the form data.
 * Should be used whenever anything is posted.
 * Calls AddMailQueue to process any mail queue items its can
 * Calls call_integration_hook integrate_redirect before headers are sent
 *
 * @param string $setLocation = ''
 * @param bool $refresh = false, enable to send a refresh header, default is a location header
 */
function redirectexit($setLocation = '', $refresh = false)
{
	global $scripturl, $context, $modSettings, $db_show_debug, $db_cache;

	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty($context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in AddMailQueue itself... :\
		AddMailQueue(true);

	$add = preg_match('~^(ftp|http)[s]?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

	if ($add)
		$setLocation = $scripturl . ($setLocation != '' ? '?' . $setLocation : '');

	// Put the session ID in.
	if (defined('SID') && SID != '')
		$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', $scripturl . '?' . SID . ';', $setLocation);
	// Keep that debug in their for template debugging!
	elseif (isset($_GET['debug']))
		$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\\??/', $scripturl . '?debug;', $setLocation);

	if (!empty($modSettings['queryless_urls']) && (empty($context['server']['is_cgi']) || ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && (!empty($context['server']['is_apache']) || !empty($context['server']['is_lighttpd']) || !empty($context['server']['is_litespeed'])))
	{
		if (defined('SID') && SID != '')
			$setLocation = preg_replace_callback('~^' . preg_quote($scripturl, '/') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~', 'redirectexit_callback', $setLocation);
		else
			$setLocation = preg_replace_callback('~^' . preg_quote($scripturl, '/') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~', 'redirectexit_callback', $setLocation);
	}

	// Maybe integrations want to change where we are heading?
	call_integration_hook('integrate_redirect', array(&$setLocation, &$refresh));

	// We send a Refresh header only in special cases because Location looks better. (and is quicker...)
	if ($refresh)
		header('Refresh: 0; URL=' . strtr($setLocation, array(' ' => '%20')));
	else
		header('Location: ' . str_replace(' ', '%20', $setLocation));

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$_SESSION['debug_redirect'] = $db_cache;

	obExit(false);
}

/**
 * URL fixer for redirect exit
 * Similar to the callback function used in ob_sessrewrite
 * Envoked by enabling queryless_urls for systems that support that function
 *
 * @param mixed[] $matches
 */
function redirectexit_callback($matches)
{
	global $scripturl;

	if (defined('SID') && SID != '')
		return $scripturl . '/' . strtr($matches[1], '&;=', '//,') . '.html?' . SID . (isset($matches[2]) ? $matches[2] : '');
	else
		return $scripturl . '/' . strtr($matches[1], '&;=', '//,') . '.html' . (isset($matches[2]) ? $matches[2] : '');
}

/**
 * Ends execution.
 * Takes care of template loading and remembering the previous URL.
 * Calls ob_start() with ob_sessrewrite to fix URLs if necessary.
 *
 * @param bool|null $header = null
 * @param bool|null $do_footer = null
 * @param bool $from_index = false
 * @param bool $from_fatal_error = false
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $settings, $modSettings, $txt;

	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;

	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	trackStats();

	// If we have mail to send, send it.
	if (!empty($context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in AddMailQueue itself... :\
		AddMailQueue(true);

	$do_header = $header === null ? !$header_done : $header;
	if ($do_footer === null)
		$do_footer = $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');

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
		loadSubTemplate(isset($context['sub_template']) ? $context['sub_template'] : 'main');

		// Just so we don't get caught in an endless loop of errors from the footer...
		if (!$footer_done)
		{
			$footer_done = true;
			template_footer();

			// (since this is just debugging... it's okay that it's after </html>.)
			if (!isset($_REQUEST['xml']))
				displayDebug();
		}
	}

	// Need user agent
	$req = request();

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (strpos($_SERVER['REQUEST_URL'], 'action=dlattach') === false && strpos($_SERVER['REQUEST_URL'], 'action=viewadminfile') === false)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... don't switch browsers...
	$_SESSION['USER_AGENT'] = $req->user_agent();

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index)
		exit;
}

/**
 * Sets the class of the current topic based on is_very_hot, veryhot, hot, etc
 *
 * @param mixed[] $topic_context
 */
function determineTopicClass(&$topic_context)
{
	// Set topic class depending on locked status and number of replies.
	if ($topic_context['is_very_hot'])
		$topic_context['class'] = 'veryhot';
	elseif ($topic_context['is_hot'])
		$topic_context['class'] = 'hot';
	else
		$topic_context['class'] = 'normal';

	$topic_context['class'] .= $topic_context['is_poll'] ? '_poll' : '_post';

	if ($topic_context['is_locked'])
		$topic_context['class'] .= '_locked';

	if ($topic_context['is_sticky'])
		$topic_context['class'] .= '_sticky';

	// This is so old themes will still work.
	// @deprecated since 1.0 do not rely on it
	$topic_context['extended_class'] = &$topic_context['class'];
}

/**
 * Sets up the basic theme context stuff.
 * @param bool $forceload = false
 */
function setupThemeContext($forceload = false)
{
	global $modSettings, $user_info, $scripturl, $context, $settings, $options, $txt;
	global $user_settings;

	static $loaded = false;

	// Under SSI this function can be called more then once.  That can cause some problems.
	//   So only run the function once unless we are forced to run it again.
	if ($loaded && !$forceload)
		return;

	$loaded = true;

	$context['current_time'] = standardTime(time(), false);
	$context['current_action'] = isset($_GET['action']) ? $_GET['action'] : '';
	$context['show_quick_login'] = !empty($modSettings['enableVBStyleLogin']) && $user_info['is_guest'];

	// Get some news...
	$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news'])))));
	for ($i = 0, $n = count($context['news_lines']); $i < $n; $i++)
	{
		if (trim($context['news_lines'][$i]) == '')
			continue;

		// Clean it up for presentation ;).
		$context['news_lines'][$i] = parse_bbc(stripslashes(trim($context['news_lines'][$i])), true, 'news' . $i);
	}

	if (!empty($context['news_lines']))
		$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];

	if (!empty($settings['enable_news']) && !empty($context['random_news_line']))
		loadJavascriptFile ('fader.js');

	if (!$user_info['is_guest'])
	{
		$context['user']['messages'] = &$user_info['messages'];
		$context['user']['unread_messages'] = &$user_info['unread_messages'];
		$context['user']['mentions'] = &$user_info['mentions'];

		// Personal message popup...
		if ($user_info['unread_messages'] > (isset($_SESSION['unread_messages']) ? $_SESSION['unread_messages'] : 0))
			$context['user']['popup_messages'] = true;
		else
			$context['user']['popup_messages'] = false;
		$_SESSION['unread_messages'] = $user_info['unread_messages'];

		$context['user']['avatar'] = array();

		// Figure out the avatar... uploaded?
		if ($user_info['avatar']['url'] == '' && !empty($user_info['avatar']['id_attach']))
			$context['user']['avatar']['href'] = $user_info['avatar']['custom_dir'] ? $modSettings['custom_avatar_url'] . '/' . $user_info['avatar']['filename'] : $scripturl . '?action=dlattach;attach=' . $user_info['avatar']['id_attach'] . ';type=avatar';
		// Full URL?
		elseif (substr($user_info['avatar']['url'], 0, 7) == 'http://' || substr($user_info['avatar']['url'], 0, 8) == 'https://')
		{
			$context['user']['avatar']['href'] = $user_info['avatar']['url'];

			if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
			{
				if (!empty($modSettings['avatar_max_width_external']))
					$context['user']['avatar']['width'] = $modSettings['avatar_max_width_external'];

				if (!empty($modSettings['avatar_max_height_external']))
					$context['user']['avatar']['height'] = $modSettings['avatar_max_height_external'];
			}
		}
		// Gravatars URL.
		elseif ($user_info['avatar']['url'] === 'gravatar')
			$context['user']['avatar']['href'] = '//www.gravatar.com/avatar/' . md5(strtolower($user_settings['email_address'])) . 'd=' . $modSettings['avatar_max_height_external'] . (!empty($modSettings['gravatar_rating']) ? ('&amp;r=' . $modSettings['gravatar_rating']) : '');
		// Otherwise we assume it's server stored?
		elseif ($user_info['avatar']['url'] !== '')
			$context['user']['avatar']['href'] = $modSettings['avatar_url'] . '/' . htmlspecialchars($user_info['avatar']['url']);

		if (!empty($context['user']['avatar']))
			$context['user']['avatar']['image'] = '<img src="' . $context['user']['avatar']['href'] . '" style="' . (isset($context['user']['avatar']['width']) ? 'width: ' . $context['user']['avatar']['width'] . 'px;' : '') . (isset($context['user']['avatar']['height']) ? 'height: ' . $context['user']['avatar']['height'] . 'px' : '') . '" alt="" class="avatar" />';

		// Figure out how long they've been logged in.
		$context['user']['total_time_logged_in'] = array(
			'days' => floor($user_info['total_time_logged_in'] / 86400),
			'hours' => floor(($user_info['total_time_logged_in'] % 86400) / 3600),
			'minutes' => floor(($user_info['total_time_logged_in'] % 3600) / 60)
		);
	}
	else
	{
		$context['user']['messages'] = 0;
		$context['user']['unread_messages'] = 0;
		$context['user']['mentions'] = 0;
		$context['user']['avatar'] = array();
		$context['user']['total_time_logged_in'] = array('days' => 0, 'hours' => 0, 'minutes' => 0);
		$context['user']['popup_messages'] = false;

		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1)
			$txt['welcome_guest'] .= $txt['welcome_guest_activate'];

		// If we've upgraded recently, go easy on the passwords.
		if (!empty($modSettings['enable_password_conversion']))
			$context['disable_login_hashing'] = true;
	}

	// Setup the main menu items.
	setupMenuContext();

	if (empty($settings['theme_version']))
		$context['show_vBlogin'] = $context['show_quick_login'];

	// This is here because old index templates might still use it.
	$context['show_news'] = !empty($settings['enable_news']);

	$context['additional_dropdown_search'] = prepareSearchEngines();

	// This is done to allow theme authors to customize it as they want.
	$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'pm');

	// Add the PM popup here instead. Theme authors can still override it simply by editing/removing the 'fPmPopup' in the array.
	if ($context['show_pm_popup'])
		addInlineJavascript('
		$(document).ready(function(){
			new smc_Popup({
				heading: ' . JavaScriptEscape($txt['show_personal_messages_heading']) . ',
				content: ' . JavaScriptEscape(sprintf($txt['show_personal_messages'], $context['user']['unread_messages'], $scripturl . '?action=pm')) . ',
				icon: elk_images_url + \'/im_sm_newmsg.png\'
			});
		});', true);

	// Resize avatars the fancy, but non-GD requiring way.
	if ($modSettings['avatar_action_too_large'] == 'option_js_resize' && (!empty($modSettings['avatar_max_width_external']) || !empty($modSettings['avatar_max_height_external'])))
	{
		// @todo Move this over to script.js?
		addInlineJavascript('
		var elk_avatarMaxWidth = ' . (int) $modSettings['avatar_max_width_external'] . ',
			elk_avatarMaxHeight = ' . (int) $modSettings['avatar_max_height_external'] . ';' . (!isBrowser('is_ie8') ? '
		window.addEventListener("load", elk_avatarResize, false);' : '
		window.attachEvent("load", elk_avatarResize);'), true);
	}

	// This looks weird, but it's because BoardIndex.controller.php references the variable.
	$context['common_stats']['latest_member'] = array(
		'id' => $modSettings['latestMember'],
		'name' => $modSettings['latestRealName'],
		'href' => $scripturl . '?action=profile;u=' . $modSettings['latestMember'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $modSettings['latestRealName'] . '</a>',
	);
	$context['common_stats'] = array(
		'total_posts' => comma_format($modSettings['totalMessages']),
		'total_topics' => comma_format($modSettings['totalTopics']),
		'total_members' => comma_format($modSettings['totalMembers']),
		'latest_member' => $context['common_stats']['latest_member'],
	);
	$context['common_stats']['boardindex_total_posts'] = sprintf($txt['boardindex_total_posts'], $context['common_stats']['total_posts'], $context['common_stats']['total_topics'], $context['common_stats']['total_members']);

	if (empty($settings['theme_version']))
		addJavascriptVar(array('elk_scripturl' => $scripturl));

	if (!isset($context['page_title']))
		$context['page_title'] = '';

	// Set some specific vars.
	$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');
	$context['meta_keywords'] = !empty($modSettings['meta_keywords']) ? Util::htmlspecialchars($modSettings['meta_keywords']) : '';
}

/**
 * Helper function to set the system memory to a needed value
 * - If the needed memory is greater than current, will attempt to get more
 * - if in_use is set to true, will also try to take the current memory usage in to account
 *
 * @param string $needed The amount of memory to request, if needed, like 256M
 * @param bool $in_use Set to true to account for current memory usage of the script
 * @return boolean true if we have at least the needed memory
 */
function setMemoryLimit($needed, $in_use = false)
{
	// Everything in bytes
	$memory_current = memoryReturnBytes(ini_get('memory_limit'));
	$memory_needed = memoryReturnBytes($needed);

	// Should we account for how much is currently being used?
	if ($in_use)
		$memory_needed += memory_get_usage();

	// If more is needed, request it
	if ($memory_current < $memory_needed)
	{
		@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
		$memory_current = memoryReturnBytes(ini_get('memory_limit'));
	}

	$memory_current = max($memory_current, memoryReturnBytes(get_cfg_var('memory_limit')));

	// Return success or not
	return (bool) ($memory_current >= $memory_needed);
}

/**
 * Helper function to convert memory string settings to bytes
 *
 * @param string $val The byte string, like 256M or 1G
 * @return integer The string converted to a proper integer in bytes
 */
function memoryReturnBytes($val)
{
	if (is_integer($val))
		return $val;

	// Separate the number from the designator
	$val = trim($val);
	$num = intval(substr($val, 0, strlen($val) - 1));
	$last = strtolower(substr($val, -1));

	// Convert to bytes
	switch ($last)
	{
		case 'g':
			$num *= 1024;
		case 'm':
			$num *= 1024;
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
	global $context;

	echo $context['raw_data'];
}

/**
 * The header template
 */
function template_header()
{
	global $context, $settings;

	doSecurityChecks();

	setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		// Are we debugging the template/html content?
		if ((!isset($_REQUEST['xml']) || !isset($_REQUEST['api'])) && isset($_GET['debug']) && !isBrowser('ie'))
			header('Content-Type: application/xhtml+xml');
		elseif (!isset($_REQUEST['xml']) || !isset($_REQUEST['api']))
			header('Content-Type: text/html; charset=UTF-8');
	}

	// Probably temporary ($_REQUEST['xml'] should be replaced by $_REQUEST['api'])
	if (isset($_REQUEST['api']) && $_REQUEST['api'] == 'json')
		header('Content-Type: application/json; charset=UTF-8');
	elseif (isset($_REQUEST['xml']) || isset($_REQUEST['api']))
		header('Content-Type: text/xml; charset=UTF-8');
	else
		header('Content-Type: text/html; charset=UTF-8');

	foreach (Template_Layers::getInstance()->prepareContext() as $layer)
		loadSubTemplate($layer . '_above', 'ignore');

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
}

/**
 * Show the copyright.
 */
function theme_copyright()
{
	global $forum_copyright, $forum_version;

	// Don't display copyright for things like SSI.
	if (!isset($forum_version))
		return;

	// Put in the version...
	// @todo - No necessity for inline CSS in the copyright, and better without it.
	$forum_copyright = sprintf($forum_copyright, ucfirst(strtolower($forum_version)));

	echo '
					<span class="smalltext" style="display: inline; visibility: visible; font-family: Verdana, Arial, sans-serif;">', $forum_copyright, '
					</span>';
}

/**
 * The template footer
 */
function template_footer()
{
	global $context, $settings, $modSettings, $time_start, $db_count;

	// Show the load time?  (only makes sense for the footer.)
	$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
	$context['load_time'] = round(microtime(true) - $time_start, 3);
	$context['load_queries'] = $db_count;

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $settings['actual_theme_url'];
		$settings['images_url'] = $settings['actual_images_url'];
		$settings['theme_dir'] = $settings['actual_theme_dir'];
	}

	foreach (Template_Layers::getInstance()->reverseLayers() as $layer)
		loadSubTemplate($layer . '_below', 'ignore');

}

/**
 * Output the Javascript files
 *  - tabbing in this function is to make the HTML source look proper
 *  - if defered is set function will output all JS (source & inline) set to load at page end
 *  - if the admin option to combine files is set, will use Combiner.class
 *
 * @param bool $do_defered = false
 */
function template_javascript($do_defered = false)
{
	global $context, $modSettings, $settings, $boardurl;

	// First up, load jQuery and jQuery UI
	if (isset($modSettings['jquery_source']) && !$do_defered)
	{
		// Using a specified version of jquery or what was shipped 1.10.2 and 1.10.3
		$jquery_version = (!empty($modSettings['jquery_default']) && !empty($modSettings['jquery_version'])) ? $modSettings['jquery_version'] : '1.10.2';
		$jqueryui_version = (!empty($modSettings['jqueryui_default']) && !empty($modSettings['jqueryui_version'])) ? $modSettings['jqueryui_version'] : '1.10.3';

		switch ($modSettings['jquery_source'])
		{
			// Only getting the files from the CDN?
			case 'cdn':
				echo '
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/' . $jquery_version . '/jquery.min.js" id="jquery"></script>',
	(!empty($modSettings['jquery_include_ui']) ? '
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/' . $jqueryui_version . '/jquery-ui.min.js" id="jqueryui"></script>' : '');
				break;
			// Just use the local file
			case 'local':
				echo '
	<script src="', $settings['default_theme_url'], '/scripts/jquery-' . $jquery_version . '.min.js" id="jquery"></script>',
	(!empty($modSettings['jquery_include_ui']) ? '
	<script src="' . $settings['default_theme_url'] . '/scripts/jqueryui-' . $jqueryui_version . '.min.js" id="jqueryui"></script>' : '');
				break;
			// CDN with local fallback
			case 'auto':
				echo '
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/' . $jquery_version . '/jquery.min.js" id="jquery"></script>',
	(!empty($modSettings['jquery_include_ui']) ? '
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/' . $jqueryui_version . '/jquery-ui.min.js" id="jqueryui"></script>' : '');
				echo '
	<script><!-- // --><![CDATA[
		window.jQuery || document.write(\'<script src="', $settings['default_theme_url'], '/scripts/jquery-' . $jquery_version . '.min.js"><\/script>\');',
		(!empty($modSettings['jquery_include_ui']) ? '
		window.jQuery.ui || document.write(\'<script src="' . $settings['default_theme_url'] . '/scripts/jqueryui-' . $jqueryui_version . '.min.js"><\/script>\')' : ''), '
	// ]]></script>';
				break;
		}
	}

	// Use this hook to work with Javascript files and vars pre output
	call_integration_hook('pre_javascript_output');

	// Combine and minify javascript source files to save bandwidth and requests
	if (!empty($context['javascript_files']))
	{
		if (!empty($modSettings['minify_css_js']))
		{
			require_once(SOURCEDIR . '/Combine.class.php');
			$combiner = new Site_Combiner(CACHEDIR, $boardurl . '/cache');
			$combine_name = $combiner->site_js_combine($context['javascript_files'], $do_defered);
		}

		if (!empty($combine_name))
			echo '
	<script src="', $combine_name, '" id="jscombined', $do_defered ? 'bottom' : 'top', '"></script>';
		else
		{
			// While we have Javascript files to place in the template
			foreach ($context['javascript_files'] as $id => $js_file)
			{
				if ((!$do_defered && empty($js_file['options']['defer'])) || ($do_defered && !empty($js_file['options']['defer'])))
					echo '
	<script src="', $js_file['filename'], '" id="', $id, '"', !empty($js_file['options']['async']) ? ' async="async"' : '', '></script>';
			}
		}
	}

	// Build the declared Javascript variables script
	$js_vars = array();
	if (!empty($context['javascript_vars']) && !$do_defered)
	{
		foreach ($context['javascript_vars'] as $var => $value)
			$js_vars[] = $var . ' = ' . $value;

		// nNewlines and tabs are here to make it look nice in the page source view, stripped if minimized though
		$context['javascript_inline']['standard'][] = 'var ' . implode(",\n\t\t\t", $js_vars) . ';';
	}

	// Inline JavaScript - Actually useful some times!
	if (!empty($context['javascript_inline']))
	{
		// Defered output waits until we are defering !
		if (!empty($context['javascript_inline']['defer']) && $do_defered)
		{
			// Combine them all in to one output
			$context['javascript_inline']['defer'] = array_map('trim', $context['javascript_inline']['defer']);
			$inline_defered_code = implode("\n\t\t", $context['javascript_inline']['defer']);

			// Output the defered script
			echo '
	<script><!-- // --><![CDATA[
		', $inline_defered_code, '
	// ]]></script>';
		}

		// Standard output, and our javascript vars, get output when we are not on a defered call
		if (!empty($context['javascript_inline']['standard']) && !$do_defered)
		{
			$context['javascript_inline']['standard'] = array_map('trim', $context['javascript_inline']['standard']);

			// And output the js vars and standard scripts to the page
			echo '
	<script><!-- // --><![CDATA[
		', implode("\n\t\t", $context['javascript_inline']['standard']), '
	// ]]></script>';
		}
	}
}

/**
 * Output the CSS files
 *  - if the admin option to combine files is set, will use Combiner.class
 */
function template_css()
{
	global $context, $modSettings, $boardurl;

	// Use this hook to work with CSS files pre output
	call_integration_hook('pre_css_output');

	// Combine and minify the CSS files to save bandwidth and requests?
	if (!empty($context['css_files']))
	{
		if (!empty($modSettings['minify_css_js']))
		{
			require_once(SOURCEDIR . '/Combine.class.php');
			$combiner = new Site_Combiner(CACHEDIR, $boardurl . '/cache');
			$combine_name = $combiner->site_css_combine($context['css_files']);
		}

		if (!empty($combine_name))
			echo '
	<link rel="stylesheet" href="', $combine_name, '" id="csscombined" />';
		else
		{
			foreach ($context['css_files'] as $id => $file)
				echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id,'" />';
		}
	}
}

/**
 * I know this is becoming annoying, though this template
 * *shall* be present for security reasons, so better it stays here
 *
 * @todo rework it and merge into some other kind of general warning-box (e.g. modtask at index.template)
 */
function template_admin_warning_above()
{
	global $context, $user_info, $scripturl, $txt;

	if (!empty($context['security_controls']))
	{
		foreach ($context['security_controls'] as $error)
		{
			echo '
	<div class="errorbox">
		<h3>', $error['title'], '</h3>
		<ul>';

			foreach ($error['messages'] as $text)
			{
				echo '
			<li class="listlevel1">', $text, '</li>';
			}

			echo '
		</ul>
	</div>';
		}
	}

	// Any special notices to remind the admin about?
	if (!empty($context['warning_controls']))
	{
		echo '
	<div class="warningbox">
		<ul>
			<li class="listlevel1">', implode('</li><li class="listlevel1">', $context['warning_controls']), '</li>
		</ul>
	</div>';
	}
}

/**
 * Get an attachment's encrypted filename. If $new is true, won't check for file existence.
 * @todo this currently returns the hash if new, and the full filename otherwise.
 * Something messy like that.
 * @todo and of course everything relies on this behavior and work around it. :P.
 * Converters included.
 *
 * @param string $filename
 * @param int $attachment_id
 * @param string|null $dir
 * @param bool $new
 * @param string $file_hash
 */
function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $modSettings;

	// Just make up a nice hash...
	if ($new)
		return sha1(md5($filename . time()) . mt_rand());

	// In case of files from the old system, do a legacy call.
	if (empty($file_hash))
	{
		require_once(SUBSDIR . '/Attachments.subs.php');
		return getLegacyAttachmentFilename($filename, $attachment_id, $dir, $new);
	}

	// Are we using multiple directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$path = isset($modSettings['attachmentUploadDir'][$dir]) ? $modSettings['attachmentUploadDir'][$dir] : $modSettings['basedirectory_for_attachments'];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash . '.elk';
}

/**
 * Convert a single IP to a ranged IP.
 * internal function used to convert a user-readable format to a format suitable for the database.
 *
 * @param string $fullip
 * @return array|string 'unknown' if the ip in the input was '255.255.255.255'
 */
function ip2range($fullip)
{
	// If its IPv6, validate it first.
	if (isValidIPv6($fullip) !== false)
	{
		$ip_parts = explode(':', expandIPv6($fullip, false));
		$ip_array = array();

		if (count($ip_parts) != 8)
			return array();

		for ($i = 0; $i < 8; $i++)
		{
			if ($ip_parts[$i] == '*')
				$ip_array[$i] = array('low' => '0', 'high' => hexdec('ffff'));
			elseif (preg_match('/^([0-9A-Fa-f]{1,4})\-([0-9A-Fa-f]{1,4})$/', $ip_parts[$i], $range) == 1)
				$ip_array[$i] = array('low' => hexdec($range[1]), 'high' => hexdec($range[2]));
			elseif (is_numeric(hexdec($ip_parts[$i])))
				$ip_array[$i] = array('low' => hexdec($ip_parts[$i]), 'high' => hexdec($ip_parts[$i]));
		}

		return $ip_array;
	}

	// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
	if ($fullip == 'unknown')
		$fullip = '255.255.255.255';

	$ip_parts = explode('.', $fullip);
	$ip_array = array();

	if (count($ip_parts) != 4)
		return array();

	for ($i = 0; $i < 4; $i++)
	{
		if ($ip_parts[$i] == '*')
			$ip_array[$i] = array('low' => '0', 'high' => '255');
		elseif (preg_match('/^(\d{1,3})\-(\d{1,3})$/', $ip_parts[$i], $range) == 1)
			$ip_array[$i] = array('low' => $range[1], 'high' => $range[2]);
		elseif (is_numeric($ip_parts[$i]))
			$ip_array[$i] = array('low' => $ip_parts[$i], 'high' => $ip_parts[$i]);
	}

	// Makes it simpiler to work with.
	$ip_array[4] = array('low' => 0, 'high' => 0);
	$ip_array[5] = array('low' => 0, 'high' => 0);
	$ip_array[6] = array('low' => 0, 'high' => 0);
	$ip_array[7] = array('low' => 0, 'high' => 0);

	return $ip_array;
}

/**
 * Lookup an IP; try shell_exec first because we can do a timeout on it.
 *
 * @param string $ip
 * @return string
 */
function host_from_ip($ip)
{
	global $modSettings;

	if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null)
		return $host;
	$t = microtime(true);

	// Try the Linux host command, perhaps?
	if (!isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
	{
		if (!isset($modSettings['host_to_dis']))
			$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
		else
			$test = @shell_exec('host ' . @escapeshellarg($ip));

		// Did host say it didn't find anything?
		if (strpos($test, 'not found') !== false)
			$host = '';
		// Invalid server option?
		elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset($modSettings['host_to_dis']))
			updateSettings(array('host_to_dis' => 1));
		// Maybe it found something, after all?
		elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is nslookup; usually only Windows, but possibly some Unix?
	if (!isset($host) && stripos(PHP_OS, 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
	{
		$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));

		if (strpos($test, 'Non-existent domain') !== false)
			$host = '';
		elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is the last try :/.
	if (!isset($host) || $host === false)
		$host = @gethostbyaddr($ip);

	// It took a long time, so let's cache it!
	if (microtime(true) - $t > 0.5)
		cache_put_data('hostlookup-' . $ip, $host, 600);

	return $host;
}

/**
 * Creates an image/text button
 *
 * @param string $name
 * @param string $alt
 * @param string $label = ''
 * @param string|boolean $custom = ''
 * @param boolean $force_use = false
 * @return string
 *
 * @deprecated since 1.0 this will be removed at some point, do not rely on this function
 */
function create_button($name, $alt, $label = '', $custom = '', $force_use = false)
{
	global $settings, $txt;

	// Does the current loaded theme have this and we are not forcing the usage of this function?
	if (function_exists('template_create_button') && !$force_use)
		return template_create_button($name, $alt, $label = '', $custom = '');

	if (!$settings['use_image_buttons'])
		return $txt[$alt];
	elseif (!empty($settings['use_buttons']))
		return '<img src="' . $settings['images_url'] . '/buttons/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . ' />' . ($label != '' ? '&nbsp;<strong>' . $txt[$label] . '</strong>' : '');
	else
		return '<img src="' . $settings['lang_images_url'] . '/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . ' />';
}

/**
 * Sets up all of the top menu buttons
 * Saves them in the cache if it is available and on
 * Places the results in $context
 */
function setupMenuContext()
{
	global $context, $modSettings, $user_info, $txt, $scripturl, $settings;

	// Set up the menu privileges.
	$context['allow_search'] = !empty($modSettings['allow_guestAccess']) ? allowedTo('search_posts') : (!$user_info['is_guest'] && allowedTo('search_posts'));
	$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));
	$context['allow_edit_profile'] = !$user_info['is_guest'] && allowedTo(array('profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any'));
	$context['allow_memberlist'] = allowedTo('view_mlist');
	$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
	$context['allow_moderation_center'] = $context['user']['can_mod'];
	$context['allow_pm'] = allowedTo('pm_read');

	$cacheTime = $modSettings['lastActive'] * 60;

	// Update the Moderation menu items with action item totals
	if ($context['allow_moderation_center'])
	{
		// Get the numbers for the menu ...
		require_once(SUBSDIR . '/Moderation.subs.php');
		$menu_count = loadModeratorMenuCounts();
	}

	$menu_count['unread_messages'] = $context['user']['unread_messages'];
	$menu_count['mentions'] = $context['user']['mentions'];

	// All the buttons we can possible want and then some, try pulling the final list of buttons from cache first.
	if (($menu_buttons = cache_get_data('menu_buttons-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $cacheTime)) === null || time() - $cacheTime <= $modSettings['settings_updated'])
	{
		// Start things up: this is what we know by default
		require_once(SUBSDIR . '/Menu.subs.php');
		$buttons = array(
			'home' => array(
				'title' => $txt['community'],
				'href' => $scripturl,
				'data-icon' => '&#xf015;',
				'show' => true,
				'sub_buttons' => array(
					'help' => array(
						'title' => $txt['help'],
						'href' => $scripturl . '?action=help',
						'show' => true,
					),
					'search' => array(
						'title' => $txt['search'],
						'href' => $scripturl . '?action=search',
						'show' => $context['allow_search'],
					),
					'calendar' => array(
						'title' => $txt['calendar'],
						'href' => $scripturl . '?action=calendar',
						'show' => $context['allow_calendar'],
					),
					'memberlist' => array(
						'title' => $txt['members_title'],
						'href' => $scripturl . '?action=memberlist',
						'show' => $context['allow_memberlist'],
					),
					'recent' => array(
						'title' => $txt['recent_posts'],
						'href' => $scripturl . '?action=recent',
						'show' => true,
					),
				),
			)
		);

		// Will change title correctly if user is either a mod or an admin.
		// Button highlighting works properly too (see current action stuffz).
		if ($context['allow_admin'])
		{
			$buttons['admin'] = array(
				'title' => $context['current_action'] !== 'moderate' ? $txt['admin'] : $txt['moderate'],
				'counter' => 'grand_total',
				'href' => $scripturl . '?action=admin',
				'data-icon' => '&#xf013;',
				'show' => true,
				'sub_buttons' => array(
					'admin_center' => array(
						'title' => $txt['admin_center'],
						'href' => $scripturl . '?action=admin',
						'show' => $context['allow_admin'],
					),
					'featuresettings' => array(
						'title' => $txt['modSettings_title'],
						'href' => $scripturl . '?action=admin;area=featuresettings',
						'show' => allowedTo('admin_forum'),
					),
					'packages' => array(
						'title' => $txt['package'],
						'href' => $scripturl . '?action=admin;area=packages',
						'show' => allowedTo('admin_forum'),
					),
					'permissions' => array(
						'title' => $txt['edit_permissions'],
						'href' => $scripturl . '?action=admin;area=permissions',
						'show' => allowedTo('manage_permissions'),
					),
					'errorlog' => array(
						'title' => $txt['errlog'],
						'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
						'show' => allowedTo('admin_forum') && !empty($modSettings['enableErrorLogging']),
					),
					'moderate_sub' => array(
						'title' => $txt['moderate'],
						'counter' => 'grand_total',
						'href' => $scripturl . '?action=moderate',
						'show' => $context['allow_moderation_center'],
						'sub_buttons' => array(
							'reports' => array(
								'title' => $txt['mc_reported_posts'],
								'counter' => 'reports',
								'href' => $scripturl . '?action=moderate;area=reports',
								'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
							),
							'modlog' => array(
								'title' => $txt['modlog_view'],
								'href' => $scripturl . '?action=moderate;area=modlog',
								'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
							),
							'attachments' => array(
								'title' => $txt['mc_unapproved_attachments'],
								'counter' => 'attachments',
								'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
								'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
							),
							'poststopics' => array(
								'title' => $txt['mc_unapproved_poststopics'],
								'counter' => 'postmod',
								'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
								'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
							),
							'postbyemail' => array(
								'title' => $txt['mc_emailerror'],
								'counter' => 'emailmod',
								'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
								'show' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
							),
						),
					),
				),
			);
		}
		else
		{
			$buttons['admin'] = array(
				'title' => $txt['moderate'],
				'counter' => 'grand_total',
				'href' => $scripturl . '?action=moderate',
				'data-icon' => '&#xf013;',
				'show' => $context['allow_moderation_center'],
				'sub_buttons' => array(
					'reports' => array(
						'title' => $txt['mc_reported_posts'],
						'counter' => 'reports',
						'href' => $scripturl . '?action=moderate;area=reports',
						'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
					),
					'modlog' => array(
						'title' => $txt['modlog_view'],
						'href' => $scripturl . '?action=moderate;area=modlog',
						'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
					),
					'attachments' => array(
						'title' => $txt['mc_unapproved_attachments'],
						'counter' => 'attachments',
						'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
						'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
					),
					'poststopics' => array(
						'title' => $txt['mc_unapproved_poststopics'],
						'counter' => 'postmod',
						'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
						'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
					),
					'postbyemail' => array(
						'title' => $txt['mc_emailerror'],
						'counter' => 'emailmod',
						'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
						'show' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
					),
				),
			);
		}

		$buttons += array(
			'profile' => array(
				'title' => (!empty($user_info['avatar']['image']) ? $user_info['avatar']['image'] . ' ' : '') . (!empty($modSettings['displayMemberNames']) ? $user_info['name'] : $txt['account_short']),
				'href' => $scripturl . '?action=profile',
				'data-icon' => '&#xf007;',
				'show' => $context['allow_edit_profile'],
				'sub_buttons' => array(
					'account' => array(
						'title' => $txt['account'],
						'href' => $scripturl . '?action=profile;area=account',
						'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
					),
					'profile' => array(
						'title' => $txt['forumprofile'],
						'href' => $scripturl . '?action=profile;area=forumprofile',
						'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
					),
					'theme' => array(
						'title' => $txt['theme'],
						'href' => $scripturl . '?action=profile;area=theme',
						'show' => allowedTo(array('profile_extra_any', 'profile_extra_own', 'profile_extra_any')),
					),
					// The old "logout" is meh. Not a real word. "Log out" is better.
					'logout' => array(
						'title' => $txt['logout'],
						'href' => $scripturl . '?action=logout',
						'show' => !$user_info['is_guest'],
					),
				),
			),
			// @todo - Will look at doing something here, to provide instant access to inbox when using click menus.
			// @todo - A small pop-up anchor seems like the obvious way to handle it. ;)
			'pm' => array(
				'title' => $txt['pm_short'],
				'counter' => 'unread_messages',
				'href' => $scripturl . '?action=pm',
				'data-icon' => '&#xf0e0;',
				'show' => $context['allow_pm'],
				'sub_buttons' => array(
					'pm_read' => array(
						'title' => $txt['pm_menu_read'],
						'href' => $scripturl . '?action=pm',
						'show' => allowedTo('pm_read'),
					),
					'pm_send' => array(
						'title' => $txt['pm_menu_send'],
						'href' => $scripturl . '?action=pm;sa=send',
						'show' => allowedTo('pm_send'),
					),
				),
			),

			'mention' => array(
				'title' => $txt['mention'],
				'counter' => 'mentions',
				'href' => $scripturl . '?action=mentions',
				'data-icon' => '&#xf0f3;',
				'show' => !$user_info['is_guest'] && !empty($modSettings['mentions_enabled']),
			),

			// The old language string made no sense, and was too long.
			// "New posts" is better, because there are probably a pile
			// of old unread posts, and they wont be reached from this button.
			'unread' => array(
				'title' => $txt['view_unread_category'],
				'href' => $scripturl . '?action=unread',
				'data-icon' => '&#xf086;',
				'show' => !$user_info['is_guest'],
			),

			// The old language string made no sense, and was too long.
			// "New replies" is better, because there are "updated topics"
			// that the user has never posted in and doesn't care about.
			'unreadreplies' => array(
				'title' => $txt['view_replies_category'],
				'href' => $scripturl . '?action=unreadreplies',
				'data-icon' => '&#xf0e6;',
				'show' => !$user_info['is_guest'],
			),

			// "Log out" would be better here.
			// "Login" is not a word, and sort of runs together into a bleh.
			'login' => array(
				'title' => $txt['login'],
				'href' => $scripturl . '?action=login',
				'data-icon' => '&#xf023;',
				'show' => $user_info['is_guest'],
			),

			'register' => array(
				'title' => $txt['register'],
				'href' => $scripturl . '?action=register',
				'data-icon' => '&#xf090;',
				'show' => $user_info['is_guest'] && $context['can_register'],
			),
		);

		// Allow editing menu buttons easily.
		call_integration_hook('integrate_menu_buttons', array(&$buttons, &$menu_count));

		// Now we put the buttons in the context so the theme can use them.
		$menu_buttons = array();
		foreach ($buttons as $act => $button)
		{
			if (!empty($button['show']))
			{
				$button['active_button'] = false;

				// This button needs some action.
				if (isset($button['action_hook']))
					$needs_action_hook = true;

				if (isset($button['counter']) && !empty($menu_count[$button['counter']]))
				{
					$button['alttitle'] = $button['title'] . ' [' . $menu_count[$button['counter']] . ']';
					if (!empty($settings['menu_numeric_notice'][0]))
					{
						$button['title'] .= sprintf($settings['menu_numeric_notice'][0], $menu_count[$button['counter']]);
						$button['indicator'] = true;
					}
				}

				// Go through the sub buttons if there are any.
				if (isset($button['sub_buttons']))
				{
					foreach ($button['sub_buttons'] as $key => $subbutton)
					{
						if (empty($subbutton['show']))
							unset($button['sub_buttons'][$key]);
						elseif (isset($subbutton['counter']) && !empty($menu_count[$subbutton['counter']]))
						{
							$button['sub_buttons'][$key]['alttitle'] = $subbutton['title'] . ' [' . $menu_count[$subbutton['counter']] . ']';
							if (!empty($settings['menu_numeric_notice'][1]))
								$button['sub_buttons'][$key]['title'] .= sprintf($settings['menu_numeric_notice'][1], $menu_count[$subbutton['counter']]);

							// 2nd level sub buttons next...
							if (isset($subbutton['sub_buttons']))
							{
								foreach ($subbutton['sub_buttons'] as $key2 => $subbutton2)
								{
									$button['sub_buttons'][$key]['sub_buttons'][$key2] = $subbutton2;
									if (empty($subbutton2['show']))
										unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
									elseif (isset($subbutton2['counter']) && !empty($menu_count[$subbutton2['counter']]))
									{
										$button['sub_buttons'][$key]['sub_buttons'][$key2]['alttitle'] = $subbutton2['title'] . ' [' . $menu_count[$subbutton2['counter']] . ']';
										if (!empty($settings['menu_numeric_notice'][2]))
											$button['sub_buttons'][$key]['sub_buttons'][$key2]['title'] .= sprintf($settings['menu_numeric_notice'][2], $menu_count[$subbutton2['counter']]);
										unset($menu_count[$subbutton2['counter']]);
									}
								}
							}
						}
					}
				}

				$menu_buttons[$act] = $button;
			}
		}

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('menu_buttons-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $menu_buttons, $cacheTime);
	}

	if (!empty($menu_buttons['profile']['sub_buttons']['logout']))
		$menu_buttons['profile']['sub_buttons']['logout']['href'] .= ';' . $context['session_var'] . '=' . $context['session_id'];

	$context['menu_buttons'] = $menu_buttons;

	// Figure out which action we are doing so we can set the active tab.
	// Default to home.
	$current_action = 'home';

	if (isset($context['menu_buttons'][$context['current_action']]))
		$current_action = $context['current_action'];
// 	elseif ($context['current_action'] == 'search2')
// 		$current_action = 'search';
	elseif ($context['current_action'] == 'profile')
		$current_action = 'pm';
	elseif ($context['current_action'] == 'theme')
		$current_action = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? 'profile' : 'admin';
	elseif ($context['current_action'] == 'register2')
		$current_action = 'register';
	elseif ($context['current_action'] == 'login2' || ($user_info['is_guest'] && $context['current_action'] == 'reminder'))
		$current_action = 'login';
	elseif ($context['current_action'] == 'groups' && $context['allow_moderation_center'])
		$current_action = 'moderate';
	elseif ($context['current_action'] == 'moderate' && $context['allow_admin'])
		$current_action = 'admin';

	// Not all actions are simple.
	if (!empty($needs_action_hook))
		call_integration_hook('integrate_current_action', array(&$current_action));

	if (isset($context['menu_buttons'][$current_action]))
		$context['menu_buttons'][$current_action]['active_button'] = true;
}

/**
 * Generate a random seed and ensure it's stored in settings.
 */
function elk_seed_generator()
{
	global $modSettings;

	// Change the seed.
	if (mt_rand(1, 250) == 69 || empty($modSettings['rand_seed']))
		updateSettings(array('rand_seed' => mt_rand()));
}

/**
 * Process functions of an integration hook.
 * calls all functions of the given hook.
 * supports static class method calls.
 *
 * @param string $hook
 * @param mixed[] $parameters = array()
 * @return mixed[] the results of the functions
 */
function call_integration_hook($hook, $parameters = array())
{
	global $modSettings, $settings, $db_show_debug, $context;

	static $path_replacements = array(
		'BOARDDIR' => BOARDDIR,
		'SOURCEDIR' => SOURCEDIR,
		'EXTDIR' => EXTDIR,
		'LANGUAGEDIR' => LANGUAGEDIR,
		'ADMINDIR' => ADMINDIR,
		'CONTROLLERDIR' => CONTROLLERDIR,
		'SUBSDIR' => SUBSDIR,
	);

	if ($db_show_debug === true)
		$context['debug']['hooks'][] = $hook;

	$results = array();
	if (empty($modSettings[$hook]))
		return $results;

	if (!empty($settings['theme_dir']))
		$path_replacements['$themedir'] = $settings['theme_dir'];

	// Loop through each function.
	$functions = explode(',', $modSettings[$hook]);
	foreach ($functions as $function)
	{
		$function = trim($function);

		// OOP static method
		if (strpos($function, '::') !== false)
		{
			$call = explode('::', $function);
			if (strpos($call[1], ':') !== false)
			{
				list ($func, $file) = explode(':', $call[1]);
				$call = array($call[0], $func);
			}
		}
		// Normal plain function
		else
		{
			$call = $function;
			if (strpos($function, ':') !== false)
			{
				list ($func, $file) = explode(':', $function);
				$call = $func;
			}
		}

		if (!empty($file))
		{
			$absPath = strtr(trim($file), $path_replacements);

			if (file_exists($absPath))
				require_once($absPath);
		}

		// Is it valid?
		if (is_callable($call))
			$results[$function] = call_user_func_array($call, $parameters);
	}

	return $results;
}

/**
 * Includes files for hooks that only do that (i.e. integrate_pre_include)
 *
 * @param string $hook
 */
function call_integration_include_hook($hook)
{
	global $modSettings, $settings, $db_show_debug, $context;

	static $path_replacements = array(
		'BOARDDIR' => BOARDDIR,
		'SOURCEDIR' => SOURCEDIR,
		'EXTDIR' => EXTDIR,
		'LANGUAGEDIR' => LANGUAGEDIR,
		'ADMINDIR' => ADMINDIR,
		'CONTROLLERDIR' => CONTROLLERDIR,
		'SUBSDIR' => SUBSDIR,
	);

	if ($db_show_debug === true)
		$context['debug']['hooks'][] = $hook;

	// Any file to include?
	if (!empty($modSettings[$hook]))
	{
		if (!empty($settings['theme_dir']))
			$path_replacements['$themedir'] = $settings['theme_dir'];

		$pre_includes = explode(',', $modSettings[$hook]);
		foreach ($pre_includes as $include)
		{
			$include = strtr(trim($include), $path_replacements);

			if (file_exists($include))
				require_once($include);
		}
	}
}

/**
 * Special hook call executed during obExit
 */
function call_integration_buffer()
{
	global $modSettings, $settings;

	static $path_replacements = array(
		'BOARDDIR' => BOARDDIR,
		'SOURCEDIR' => SOURCEDIR,
		'EXTDIR' => EXTDIR,
		'LANGUAGEDIR' => LANGUAGEDIR,
		'ADMINDIR' => ADMINDIR,
		'CONTROLLERDIR' => CONTROLLERDIR,
		'SUBSDIR' => SUBSDIR,
	);
	if (!empty($settings['theme_dir']))
		$path_replacements['$themedir'] = $settings['theme_dir'];

	if (isset($modSettings['integrate_buffer']))
		$buffers = explode(',', $modSettings['integrate_buffer']);

	if (empty($buffers))
		return;

	foreach ($buffers as $function)
	{
		$function = trim($function);

		// OOP static method
		if (strpos($function, '::') !== false)
		{
			$call = explode('::', $function);
			if (strpos($call[1], ':') !== false)
			{
				list ($func, $file) = explode(':', $call[1]);
				$call = array($call[0], $func);
			}
		}
		// Normal plain function
		else
		{
			$call = $function;
			if (strpos($function, ':') !== false)
			{
				list ($func, $file) = explode(':', $function);
				$call = $func;
			}
		}

		if (!empty($file))
		{
			$absPath = strtr(trim($file), $path_replacements);

			if (file_exists($absPath))
				require_once($absPath);
		}

		// Is it valid?
		if (is_callable($call))
			ob_start($call);
	}
}

/**
 * Add a function for integration hook.
 * does nothing if the function is already added.
 *
 * @param string $hook
 * @param string $function
 * @param string $file
 * @param bool $permanent = true if true, updates the value in settings table
 */
function add_integration_function($hook, $function, $file = '', $permanent = true)
{
	global $modSettings;

	$db = database();

	$integration_call = (!empty($file) && $file !== true) ? $function . ':' . $file : $function;

	// Is it going to be permanent?
	if ($permanent)
	{
		$request = $db->query('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			array(
				'variable' => $hook,
			)
		);
		list ($current_functions) = $db->fetch_row($request);
		$db->free_result($request);

		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);
			if (in_array($integration_call, $current_functions))
				return;

			$permanent_functions = array_merge($current_functions, array($integration_call));
		}
		else
			$permanent_functions = array($integration_call);

		updateSettings(array($hook => implode(',', $permanent_functions)));
	}

	// Make current function list usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// Do nothing, if it's already there.
	if (in_array($integration_call, $functions))
		return;

	$functions[] = $integration_call;
	$modSettings[$hook] = implode(',', $functions);
}

/**
 * Remove an integration hook function.
 * Removes the given function from the given hook.
 * Does nothing if the function is not available.
 *
 * @param string $hook
 * @param string $function
 * @param string $file
 */
function remove_integration_function($hook, $function, $file = '')
{
	global $modSettings;

	$db = database();

	$integration_call = (!empty($file)) ? $function . ':' . $file : $function;

	// Get the permanent functions.
	$request = $db->query('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:variable}',
		array(
			'variable' => $hook,
		)
	);
	list ($current_functions) = $db->fetch_row($request);
	$db->free_result($request);

	if (!empty($current_functions))
	{
		$current_functions = explode(',', $current_functions);

		if (in_array($integration_call, $current_functions))
			updateSettings(array($hook => implode(',', array_diff($current_functions, array($integration_call)))));
	}

	// Turn the function list into something usable.
	$functions = empty($modSettings[$hook]) ? array() : explode(',', $modSettings[$hook]);

	// You can only remove it if it's available.
	if (!in_array($integration_call, $functions))
		return;

	$functions = array_diff($functions, array($integration_call));
	$modSettings[$hook] = implode(',', $functions);
}

/**
 * Decode numeric html entities to their UTF8 equivalent character.
 *
 * Callback function for preg_replace_callback in subs-members
 * Uses capture group 2 in the supplied array
 * Does basic scan to ensure characters are inside a valid range
 *
 * @param mixed[] $matches matches from a preg_match_all
 * @return string $string
 */
function replaceEntities__callback($matches)
{
	if (!isset($matches[2]))
		return '';

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// remove left to right / right to left overrides
	if ($num === 0x202D || $num === 0x202E)
		return '';

	// Quote, Ampersand, Apostrophe, Less/Greater Than get html replaced
	if (in_array($num, array(0x22, 0x26, 0x27, 0x3C, 0x3E)))
		return '&#' . $num . ';';

	// <0x20 are control characters, 0x20 is a space, > 0x10FFFF is past the end of the utf8 character set
	// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text)
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF))
		return '';
	// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and puncuation
	elseif ($num < 0x80)
		return chr($num);
	// <0x800 (2048)
	elseif ($num < 0x800)
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	// < 0x10000 (65536)
	elseif ($num < 0x10000)
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	// <= 0x10FFFF (1114111)
	else
		return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
}

/**
 * Converts html entities to utf8 equivalents
 *
 * Callback function for preg_replace_callback
 * Uses capture group 1 in the supplied array
 * Does basic checks to keep characters inside a viewable range.
 *
 * @param mixed[] $matches array of matches as output from preg_match_all
 * @return string $string
 */
function fixchar__callback($matches)
{
	if (!isset($matches[1]))
		return '';

	$num = $matches[1][0] === 'x' ? hexdec(substr($matches[1], 1)) : (int) $matches[1];

	// <0x20 are control characters, > 0x10FFFF is past the end of the utf8 character set
	// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text), 0x202D-E are left to right overrides
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202D || $num === 0x202E)
		return '';
	// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and puncuation
	elseif ($num < 0x80)
		return chr($num);
	// <0x800 (2048)
	elseif ($num < 0x800)
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	// < 0x10000 (65536)
	elseif ($num < 0x10000)
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	// <= 0x10FFFF (1114111)
	else
		return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
}

/**
 * Strips out invalid html entities, replaces others with html style &#123; codes
 *
 * Callback function used of preg_replace_callback in smcFunc $ent_checks, for example
 * strpos, strlen, substr etc
 *
 * @param mixed[] $matches array of matches for a preg_match_all
 * @return string
 */
function entity_fix__callback($matches)
{
	if (!isset($matches[2]))
		return '';

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// We don't allow control characters, characters out of range, byte markers, etc
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
		return '';
	else
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
		$search_engines = unserialize($modSettings['additional_search_engines']);
		foreach ($search_engines as $engine)
			$engines[strtolower(preg_replace('~[^A-Za-z0-9 ]~', '', $engine['name']))] = $engine;
	}

	return $engines;
}

/**
 * This function receives a request handle and attempts to retrieve the next result.
 * It is used by the controller callbacks from the template, such as
 * posts in topic display page, posts search results page, or personal messages.
 *
 * @param resource $messages_request holds a query result
 * @param bool $reset
 * @return integer|null
 */
function currentContext($messages_request, $reset = false)
{
	// Can't work with a database without a database :P
	$db = database();

	// Start from the beginning...
	if ($reset)
		return $db->data_seek($messages_request, 0);

	// If the query has already returned false, get out of here
	if ($messages_request == false)
		return false;

	// Attempt to get the next message.
	$message = $db->fetch_assoc($messages_request);
	if (!$message)
	{
		$db->free_result($messages_request);
		return false;
	}

	return $message;
}

/**
 * Helper function to insert an array in to an existing array
 * Intended for addon use to allow such things as
 *  - adding in a new menu item to an existing menu array
 *
 * @param mixed[] $input the array we will insert to
 * @param string $key the key in the array that we are looking to find for the insert action
 * @param mixed[] $insert the actual data to insert before or after the key
 * @param string $where adding before or after
 * @param bool $assoc if the array is a assoc array with named keys or a basic index array
 * @param bool $strict search for identical elements, this means it will also check the types of the needle.
 */
function elk_array_insert($input, $key, $insert, $where = 'before', $assoc = true, $strict = false)
{
	// Search for key names or values
	if ($assoc)
		$position = array_search($key, array_keys($input), $strict);
	else
		$position = array_search($key, $input, $strict);

	// If the key is not found, just insert it at the end
	if ($position === false)
		return array_merge($input, $insert);

	if ($where === 'after')
		$position += 1;

	// Insert as first
	if ($position === 0)
		$input = array_merge($insert, $input);
	else
		$input = array_merge(array_slice($input, 0, $position), $insert, array_slice($input, $position));

	return $input;
}

/**
 * From time to time it may be necessary to fire a scheduled task ASAP
 * this function set the scheduled task to be called before any other one
 *
 * @param string $task the name of a scheduled task
 */
function scheduleTaskImmediate($task)
{
	global $modSettings;

	if (!isset($modSettings['scheduleTaskImmediate']))
		$scheduleTaskImmediate = array();
	else
		$scheduleTaskImmediate = unserialize($modSettings['scheduleTaskImmediate']);

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
 */
function removeScheduleTaskImmediate($task, $calculateNextTrigger = true)
{
	global $modSettings;

	if (!isset($modSettings['scheduleTaskImmediate']))
		return;
	else
		$scheduleTaskImmediate = unserialize($modSettings['scheduleTaskImmediate']);

	if (isset($scheduleTaskImmediate[$task]))
	{
		unset($scheduleTaskImmediate[$task]);
		updateSettings(array('scheduleTaskImmediate' => serialize($scheduleTaskImmediate)));

		if ($calculateNextTrigger)
		{
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');
			calculateNextTrigger($task);
		}
	}
}
