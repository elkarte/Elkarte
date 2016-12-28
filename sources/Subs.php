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
 * @version 1.0.8
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
 * @param int|string|boolean|mixed[]|null $parameter1 pass through value
 * @param int|string|boolean|mixed[]|null $parameter2 pass through value
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
 * What it does:
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
 * What it does:
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
			if (!empty($show['all_selected']))
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
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
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
			if (!empty($show['all_selected']))
				$pageindex .= sprintf($settings['page_index_template']['current_page'], $txt['all']);
			else
				$pageindex .= sprintf(str_replace('.%1$d', '.%1$s', $base_link), '0;all', str_replace('{all_txt}', $txt['all'], $settings['page_index_template']['all']));
		}
	}

	return $pageindex;
}

/**
 * Formats a number.
 *
 * What it does:
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
 * What it does:
 * - returns a pretty formated version of time based on the user's format in $user_info['time_format'].
 * - applies all necessary time offsets to the timestamp, unless offset_type is set.
 * - if todayMod is set and show_today was not not specified or true, an
 *   alternate format string is used to show the date with something to show it is "today" or "yesterday".
 * - performs localization (more than just strftime would do alone.)
 *
 * @param int $log_time
 * @param string|bool $show_today = true
 * @param string|bool $offset_type = false
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
			return sprintf($txt['today'], standardTime($log_time, $today_fmt, $offset_type));

		// Day-of-year is one less and same year, or it's the first of the year and that's the last of the year...
		if ($modSettings['todayMod'] == '2' && (($then['yday'] == $now['yday'] - 1 && $then['year'] == $now['year']) || ($now['yday'] == 0 && $then['year'] == $now['year'] - 1) && $then['mon'] == 12 && $then['mday'] == 31))
			return sprintf($txt['yesterday'], standardTime($log_time, $today_fmt, $offset_type));
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
	global $txt, $context;

	if (empty($timestamp))
		return '';

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
 *
 * - Faster than html_entity_decode
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
 *
 * What it does:
 * - Caution: should not be called on arrays bigger than 8 elements as this function is memory hungry
 * - returns an array containing each permutation.
 * - e.g. (1,2,3) returns (1,2,3), (1,3,2), (2,1,3), (2,3,1), (3,1,2), and (3,2,1)
 * - A combinations without repetition N! function so 3! = 6 and 10! = 3,628,800 combinations
 * - Used by parse_bbc to allow bbc tag parameters to be in any order and still be
 * parsed properly
 *
 * @deprecated since 1.0.5
 * @param mixed[] $array index array of values
 * @return mixed[] array representing all permutations of the supplied array
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
			$p[$i] = $i;

		$orders[] = $array;
	}

	return $orders;
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
 * @param mixed[] $p
 * @param int $size
 *
 * @return mixed[] the next permutation of the passed array $p
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
 * Parse bulletin board code in a string, as well as smileys optionally.
 *
 * What it does:
 * - only parses bbc tags which are not disabled in disabledBBC.
 * - handles basic HTML, if enablePostHTML is on.
 * - caches the from/to replace regular expressions so as not to reload them every time a string is parsed.
 * - only parses smileys if smileys is true.
 * - does nothing if the enableBBC setting is off.
 * - uses the cache_id as a unique identifier to facilitate any caching it may do.
 * - returns the modified message.
 *
 * @param string|false $message if false return list of enabled bbc codes
 * @param bool|string $smileys = true
 * @param string $cache_id = ''
 * @param string[]|null $parse_tags array of tags to parse, null for all
 * @return string
 */
function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array())
{
	global $txt, $scripturl, $context, $modSettings, $user_info;

	static $bbc_codes = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $disabled, $default_disabled, $parse_tag_cache;

	// Don't waste cycles
	if ($message === '')
		return '';

	// Clean up any cut/paste issues we may have
	$message = sanitizeMSCutPaste($message);

	// If the load average is too high, don't parse the BBC.
	if (!empty($modSettings['bbc']) && $modSettings['current_load'] >= $modSettings['bbc'])
	{
		$context['disabled_parse_bbc'] = true;
		return $message;
	}

	if ($smileys !== null && ($smileys == '1' || $smileys == '0'))
		$smileys = (bool) $smileys;

	if (empty($modSettings['enableBBC']) && $message !== false)
	{
		if ($smileys === true)
			parsesmileys($message);

		return $message;
	}

	// Allow addons access before entering the main parse_bbc loop
	call_integration_hook('integrate_pre_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

	// Sift out the bbc for a performance improvement.
	if (empty($bbc_codes) || $message === false)
	{
		if (!empty($modSettings['disabledBBC']))
		{
			$temp = explode(',', strtolower($modSettings['disabledBBC']));

			foreach ($temp as $tag)
				$disabled[trim($tag)] = true;
		}

		/* The following bbc are formatted as an array, with keys as follows:

			tag: the tag's name - should be lowercase!

			type: one of...
				- (missing): [tag]parsed content[/tag]
				- unparsed_equals: [tag=xyz]parsed content[/tag]
				- parsed_equals: [tag=parsed data]parsed content[/tag]
				- unparsed_content: [tag]unparsed content[/tag]
				- closed: [tag], [tag/], [tag /]
				- unparsed_commas: [tag=1,2,3]parsed content[/tag]
				- unparsed_commas_content: [tag=1,2,3]unparsed content[/tag]
				- unparsed_equals_content: [tag=...]unparsed content[/tag]

			parameters: an optional array of parameters, for the form
				[tag abc=123]content[/tag].  The array is an associative array
				where the keys are the parameter names, and the values are an
				array which may contain the following:
					- match: a regular expression to validate and match the value.
					- quoted: true if the value should be quoted.
					- validate: callback to evaluate on the data, which is $data.
					- value: a string in which to replace $1 with the data.
					  either it or validate may be used, not both.
					- optional: true if the parameter is optional.

			test: a regular expression to test immediately after the tag's
				'=', ' ' or ']'.  Typically, should have a \] at the end.
				Optional.

			content: only available for unparsed_content, closed,
				unparsed_commas_content, and unparsed_equals_content.
				$1 is replaced with the content of the tag.  Parameters
				are replaced in the form {param}.  For unparsed_commas_content,
				$2, $3, ..., $n are replaced.

			before: only when content is not used, to go before any
				content.  For unparsed_equals, $1 is replaced with the value.
				For unparsed_commas, $1, $2, ..., $n are replaced.

			after: similar to before in every way, except that it is used
				when the tag is closed.

			disabled_content: used in place of content when the tag is
				disabled.  For closed, default is '', otherwise it is '$1' if
				block_level is false, '<div>$1</div>' elsewise.

			disabled_before: used in place of before when disabled.  Defaults
				to '<div>' if block_level, '' if not.

			disabled_after: used in place of after when disabled.  Defaults
				to '</div>' if block_level, '' if not.

			block_level: set to true the tag is a "block level" tag, similar
				to HTML.  Block level tags cannot be nested inside tags that are
				not block level, and will not be implicitly closed as easily.
				One break following a block level tag may also be removed.

			trim: if set, and 'inside' whitespace after the begin tag will be
				removed.  If set to 'outside', whitespace after the end tag will
				meet the same fate.

			validate: except when type is missing or 'closed', a callback to
				validate the data as $data.  Depending on the tag's type, $data
				may be a string or an array of strings (corresponding to the
				replacement.)

			quoted: when type is 'unparsed_equals' or 'parsed_equals' only,
				may be not set, 'optional', or 'required' corresponding to if
				the content may be quoted.  This allows the parser to read
				[tag="abc]def[esdf]"] properly.

			require_parents: an array of tag names, or not set.  If set, the
				enclosing tag *must* be one of the listed tags, or parsing won't
				occur.

			require_children: similar to require_parents, if set children
				won't be parsed if they are not in the list.

			disallow_children: similar to, but very different from,
				require_children, if it is set the listed tags will not be
				parsed inside the tag.

			disallow_parents: similar to, but very different from,
				require_parents, if it is set the listed tags will not be
				parsed inside the tag.

			parsed_tags_allowed: an array restricting what BBC can be in the
				parsed_equals parameter, if desired.
		*/

		$codes = array(
			array(
				'tag' => 'abbr',
				'type' => 'unparsed_equals',
				'before' => '<abbr title="$1">',
				'after' => '</abbr>',
				'quoted' => 'optional',
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'anchor',
				'type' => 'unparsed_equals',
				'test' => '[#]?([A-Za-z][A-Za-z0-9_\-]*)\]',
				'before' => '<span id="post_$1">',
				'after' => '</span>',
			),
			array(
				'tag' => 'b',
				'before' => '<strong class="bbc_strong">',
				'after' => '</strong>',
			),
			array(
				'tag' => 'br',
				'type' => 'closed',
				'content' => '<br />',
			),
			array(
				'tag' => 'center',
				'before' => '<span style="display:block" class="centertext">',
				'after' => '</span>',
				'block_level' => false,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_content',
				'content' => '<div class="codeheader">' . $txt['code'] . ': <a href="javascript:void(0);" onclick="return elkSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></div><pre class="bbc_code prettyprint">$1</pre>',
				'validate' => isset($disabled['code']) ? null : create_function('&$tag, &$data, $disabled', '
					global $context;

					if (!isset($disabled[\'code\']))
						$data = str_replace("\t", "<span class=\"tab\">\t</span>", $data);
					'),
				'block_level' => true,
			),
			array(
				'tag' => 'code',
				'type' => 'unparsed_equals_content',
				'content' => '<div class="codeheader">' . $txt['code'] . ': ($2) <a href="#" onclick="return elkSelectText(this);" class="codeoperation">' . $txt['code_select'] . '</a></div><pre class="bbc_code prettyprint">$1</pre>',
				'validate' => isset($disabled['code']) ? null : create_function('&$tag, &$data, $disabled', '
					global $context;

					if (!isset($disabled[\'code\']))
						$data[0] = str_replace("\t", "<span class=\"tab\">\t</span>", $data[0]);
					'),
				'block_level' => true,
			),
			array(
				'tag' => 'color',
				'type' => 'unparsed_equals',
				'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\s?,\s?){2}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\))\]',
				'before' => '<span style="color: $1;" class="bbc_color">',
				'after' => '</span>',
			),
			array(
				'tag' => 'email',
				'type' => 'unparsed_content',
				'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '$data = strtr($data, array(\'<br />\' => \'\'));'),
			),
			array(
				'tag' => 'email',
				'type' => 'unparsed_equals',
				'before' => '<a href="mailto:$1" class="bbc_email">',
				'after' => '</a>',
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'footnote',
				'before' => '<sup class="bbc_footnotes">%fn%',
				'after' => '%fn%</sup>',
				'disallow_parents' => array('footnote', 'code', 'anchor', 'url', 'iurl'),
				'disallow_before' => '',
				'disallow_after' => '',
				'block_level' => true,
			),
			array(
				'tag' => 'font',
				'type' => 'unparsed_equals',
				'test' => '[A-Za-z0-9_,\-\s]+?\]',
				'before' => '<span style="font-family: $1;" class="bbc_font">',
				'after' => '</span>',
			),
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_ftp new_win" target="_blank">$1</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'ftp://\') !== 0 && strpos($data, \'ftps://\') !== 0)
						$data = \'ftp://\' . $data;
				'),
			),
			array(
				'tag' => 'ftp',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_ftp new_win" target="_blank">',
				'after' => '</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (strpos($data, \'ftp://\') !== 0 && strpos($data, \'ftps://\') !== 0)
						$data = \'ftp://\' . $data;
				'),
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'hr',
				'type' => 'closed',
				'content' => '<hr />',
				'block_level' => true,
			),
			array(
				'tag' => 'i',
				'before' => '<em>',
				'after' => '</em>',
			),
			array(
				'tag' => 'img',
				'type' => 'unparsed_content',
				'parameters' => array(
					'alt' => array('optional' => true),
					'width' => array('optional' => true, 'value' => 'width:100%;max-width:$1px;', 'match' => '(\d+)'),
					'height' => array('optional' => true, 'value' => 'max-height:$1px;', 'match' => '(\d+)'),
				),
				'content' => '<img src="$1" alt="{alt}" style="{width}{height}" class="bbc_img resized" />',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'img',
				'type' => 'unparsed_content',
				'content' => '<img src="$1" alt="" class="bbc_img" />',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
				'disabled_content' => '($1)',
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link">$1</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
			),
			array(
				'tag' => 'iurl',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link">',
				'after' => '</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (substr($data, 0, 1) == \'#\')
						$data = \'#post_\' . substr($data, 1);
					elseif (strpos($data, \'http://\') !== 0 && strpos($data, \'https://\') !== 0)
						$data = \'http://\' . $data;
				'),
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
			array(
				'tag' => 'left',
				'before' => '<div style="text-align: left;">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 'li',
				'before' => '<li>',
				'after' => '</li>',
				'trim' => 'outside',
				'require_parents' => array('list'),
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '<br />',
			),
			array(
				'tag' => 'list',
				'before' => '<ul class="bbc_list">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => array('li', 'list'),
				'block_level' => true,
			),
			array(
				'tag' => 'list',
				'parameters' => array(
					'type' => array('match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)'),
				),
				'before' => '<ul class="bbc_list" style="list-style-type: {type};">',
				'after' => '</ul>',
				'trim' => 'inside',
				'require_children' => array('li'),
				'block_level' => true,
			),
			array(
				'tag' => 'me',
				'type' => 'unparsed_equals',
				'before' => '<div class="meaction">&nbsp;$1 ',
				'after' => '</div>',
				'quoted' => 'optional',
				'block_level' => true,
				'disabled_before' => '/me ',
				'disabled_after' => '<br />',
			),
			array(
				'tag' => 'member',
				'type' => 'unparsed_equals',
				'test' => '[\d*]',
				'before' => '<span class="bbc_mention"><a href="' . $scripturl . '?action=profile;u=$1">@',
				'after' => '</a></span>',
				'disabled_before' => '@',
				'disabled_after' => '',
			),
			array(
				'tag' => 'nobbc',
				'type' => 'unparsed_content',
				'content' => '$1',
			),
			array(
				'tag' => 'pre',
				'before' => '<pre class="bbc_pre">',
				'after' => '</pre>',
			),
			array(
				'tag' => 'quote',
				'before' => '<div class="quoteheader">' . $txt['quote'] . '</div><blockquote>',
				'after' => '</blockquote>',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?)', 'quoted' => true),
				),
				'before' => '<div class="quoteheader">' . $txt['quote_from'] . ': {author}</div><blockquote>',
				'after' => '</blockquote>',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'type' => 'parsed_equals',
				'before' => '<div class="quoteheader">' . $txt['quote_from'] . ': $1</div><blockquote>',
				'after' => '</blockquote>',
				'quoted' => 'optional',
				// Don't allow everything to be embedded with the author name.
				'parsed_tags_allowed' => array('url', 'iurl', 'ftp'),
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '([^<>]{1,192}?)'),
					'link' => array('match' => '(?:board=\d+;)?((?:topic|threadid)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|msg=\d{1,40}|action=profile;u=\d+)'),
					'date' => array('match' => '(\d+)', 'validate' => 'htmlTime'),
				),
				'before' => '<div class="quoteheader"><a href="' . $scripturl . '?{link}">' . $txt['quote_from'] . ': {author} ' . ($modSettings['todayMod'] == 3 ? ' - ' : $txt['search_on']) . ' {date}</a></div><blockquote>',
				'after' => '</blockquote>',
				'block_level' => true,
			),
			array(
				'tag' => 'quote',
				'parameters' => array(
					'author' => array('match' => '(.{1,192}?\]?)'),
				),
				'before' => '<div class="quoteheader">' . $txt['quote_from'] . ': {author}</div><blockquote>',
				'after' => '</blockquote>',
				'block_level' => true,
			),
			array(
				'tag' => 'right',
				'before' => '<div style="text-align: right;">',
				'after' => '</div>',
				'block_level' => true,
			),
			array(
				'tag' => 's',
				'before' => '<del>',
				'after' => '</del>',
			),
			array(
				'tag' => 'size',
				'type' => 'unparsed_equals',
				'test' => '([1-9][\d]?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\.[1-9]|[1-9](\.[\d][\d]?)?)?em)\]',
				'before' => '<span style="font-size: $1;" class="bbc_size">',
				'after' => '</span>',
				'disallow_parents' => array('size'),
				'disallow_before' => '<span>',
				'disallow_after' => '</span>',
			),
			array(
				'tag' => 'size',
				'type' => 'unparsed_equals',
				'test' => '[1-7]\]',
				'before' => '<span style="font-size: $1;" class="bbc_size">',
				'after' => '</span>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$sizes = array(1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95);
					$data = $sizes[$data] . \'em\';'
				),
				'disallow_parents' => array('size'),
				'disallow_before' => '<span>',
				'disallow_after' => '</span>',
			),
			array(
				'tag' => 'spoiler',
				'before' => '<span class="spoilerheader">' . $txt['spoiler'] . '</span><div class="spoiler"><div class="bbc_spoiler" style="display: none;">',
				'after' => '</div></div>',
				'block_level' => true,
			),
			array(
				'tag' => 'sub',
				'before' => '<sub>',
				'after' => '</sub>',
			),
			array(
				'tag' => 'sup',
				'before' => '<sup>',
				'after' => '</sup>',
			),
			array(
				'tag' => 'table',
				'before' => '<div class="bbc_table_container"><table class="bbc_table">',
				'after' => '</table></div>',
				'trim' => 'inside',
				'require_children' => array('tr'),
				'block_level' => true,
			),
			array(
				'tag' => 'td',
				'before' => '<td>',
				'after' => '</td>',
				'require_parents' => array('tr'),
				'trim' => 'outside',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			),
			array(
				'tag' => 'th',
				'before' => '<th>',
				'after' => '</th>',
				'require_parents' => array('tr'),
				'trim' => 'outside',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			),
			array(
				'tag' => 'tr',
				'before' => '<tr>',
				'after' => '</tr>',
				'require_parents' => array('table'),
				'require_children' => array('td', 'th'),
				'trim' => 'both',
				'block_level' => true,
				'disabled_before' => '',
				'disabled_after' => '',
			),
			array(
				'tag' => 'tt',
				'before' => '<span class="bbc_tt">',
				'after' => '</span>',
			),
			array(
				'tag' => 'u',
				'before' => '<span class="bbc_u">',
				'after' => '</span>',
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_content',
				'content' => '<a href="$1" class="bbc_link" target="_blank">$1</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					$data = strtr($data, array(\'<br />\' => \'\'));
					if (preg_match("~^https?://~i", $data) !== 1)
						$data = \'http://\' . $data;
				'),
			),
			array(
				'tag' => 'url',
				'type' => 'unparsed_equals',
				'before' => '<a href="$1" class="bbc_link" target="_blank">',
				'after' => '</a>',
				'validate' => create_function('&$tag, &$data, $disabled', '
					if (preg_match("~^https?://~i", $data) !== 1)
						$data = \'http://\' . $data;
				'),
				'disallow_children' => array('email', 'ftp', 'url', 'iurl'),
				'disabled_after' => ' ($1)',
			),
		);

		// Inside these tags autolink is not recommendable.
		$no_autolink_tags = array(
			'url',
			'iurl',
			'ftp',
			'email',
		);
		// So the parser won't skip them.
		$itemcodes = array(
			'*' => 'disc',
			'@' => 'disc',
			'+' => 'square',
			'x' => 'square',
			'#' => 'decimal',
			'0' => 'decimal',
			'o' => 'circle',
			'O' => 'circle',
		);

		// Let addons add new BBC without hassle.
		call_integration_hook('integrate_bbc_codes', array(&$codes, &$no_autolink_tags, &$itemcodes));

		// This is mainly for the bbc manager, so it's easy to add tags above.  Custom BBC should be added above this line.
		if ($message === false)
		{
			if (isset($temp_bbc))
				$bbc_codes = $temp_bbc;
			return $codes;
		}

		if (!isset($disabled['li']) && !isset($disabled['list']))
		{
			foreach ($itemcodes as $c => $dummy)
				$bbc_codes[$c] = array();
		}

		foreach ($codes as $code)
			$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
	}

	// If we are not doing every enabled tag then create a cache for this parsing group.
	if ($parse_tags !== array() && is_array($parse_tags))
	{
		$temp_bbc = $bbc_codes;
		$tags_cache_id = implode(',', $parse_tags);

		if (!isset($default_disabled))
			$default_disabled = isset($disabled) ? $disabled : array();

		// Already cached, use it, otherwise create it
		if (isset($parse_tag_cache[$tags_cache_id]))
			list ($bbc_codes, $disabled) = $parse_tag_cache[$tags_cache_id];
		else
		{
			foreach ($bbc_codes as $key_bbc => $bbc)
			{
				foreach ($bbc as $key_code => $code)
				{
					if (!in_array($code['tag'], $parse_tags))
					{
						$disabled[$code['tag']] = true;
						unset($bbc_codes[$key_bbc][$key_code]);
					}
				}
			}

			$parse_tag_cache[$tags_cache_id] = array($bbc_codes, $disabled);
		}
	}
	elseif (isset($default_disabled))
		$disabled = $default_disabled;

	// Shall we take the time to cache this?
	if ($cache_id != '' && !empty($modSettings['cache_enable']) && (($modSettings['cache_enable'] >= 2 && isset($message[1000])) || isset($message[2400])) && empty($parse_tags))
	{
		// It's likely this will change if the message is modified.
		$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled))) . serialize($context['browser']) . $txt['lang_locale'] . $user_info['time_offset'] . $user_info['time_format']);

		if (($temp = cache_get_data($cache_key, 240)) != null)
			return $temp;

		$cache_t = microtime(true);
	}

	if ($smileys === 'print')
	{
		// Colors can't well be displayed... supposed to be black and white.
		$disabled['color'] = true;
		$disabled['me'] = true;

		// Links are useless on paper... just show the link.
		$disabled['url'] = true;
		$disabled['iurl'] = true;
		$disabled['email'] = true;

		// @todo Change maybe?
		if (!isset($_GET['images']))
			$disabled['img'] = true;

		// @todo Interface/setting to add more?
	}

	$open_tags = array();
	$message = strtr($message, array("\n" => '<br />'));

	// The non-breaking-space looks a bit different each time.
	$non_breaking_space = '\x{A0}';

	$pos = -1;
	while ($pos !== false)
	{
		$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
		$pos = strpos($message, '[', $pos + 1);

		// Failsafe.
		if ($pos === false || $last_pos > $pos)
			$pos = strlen($message) + 1;

		// Can't have a one letter smiley, URL, or email! (sorry.)
		if ($last_pos < $pos - 1)
		{
			// Make sure the $last_pos is not negative.
			$last_pos = max($last_pos, 0);

			// Pick a block of data to do some raw fixing on.
			$data = substr($message, $last_pos, $pos - $last_pos);

			// Take care of some HTML!
			if (!empty($modSettings['enablePostHTML']) && strpos($data, '&lt;') !== false)
			{
				$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:)\S+?)\\1&gt;~i', '[url=$2]', $data);
				$data = preg_replace('~&lt;/a&gt;~i', '[/url]', $data);

				// <br /> should be empty.
				$empty_tags = array('br', 'hr');
				foreach ($empty_tags as $tag)
					$data = str_replace(array('&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'), '[' . $tag . ' /]', $data);

				// b, u, i, s, pre... basic tags.
				$closable_tags = array('b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote');
				foreach ($closable_tags as $tag)
				{
					$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
					$data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

					if ($diff > 0)
						$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
				}

				// Do <img ... /> - with security... action= -> action-.
				preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);
				if (!empty($matches[0]))
				{
					$replaces = array();
					foreach ($matches[2] as $match => $imgtag)
					{
						$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

						// Remove action= from the URL - no funny business, now.
						if (preg_match('~action(=|%3d)(?!dlattach)~i', $imgtag) != 0)
							$imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);

						// Check if the image is larger than allowed.
						// @todo - We should seriously look at deprecating some of this in favour of CSS resizing.
						if (!empty($modSettings['max_image_width']) && !empty($modSettings['max_image_height']))
						{
							// For images, we'll want this.
							require_once(SUBSDIR . '/Attachments.subs.php');
							list ($width, $height) = url_image_size($imgtag);

							if (!empty($modSettings['max_image_width']) && $width > $modSettings['max_image_width'])
							{
								$height = (int) (($modSettings['max_image_width'] * $height) / $width);
								$width = $modSettings['max_image_width'];
							}

							if (!empty($modSettings['max_image_height']) && $height > $modSettings['max_image_height'])
							{
								$width = (int) (($modSettings['max_image_height'] * $width) / $height);
								$height = $modSettings['max_image_height'];
							}

							// Set the new image tag.
							$replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
						}
						else
							$replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
					}

					$data = strtr($data, $replaces);
				}
			}

			if (!empty($modSettings['autoLinkUrls']))
			{
				// Are we inside tags that should be auto linked?
				$no_autolink_area = false;
				if (!empty($open_tags))
				{
					foreach ($open_tags as $open_tag)
						if (in_array($open_tag['tag'], $no_autolink_tags))
							$no_autolink_area = true;
				}

				// Don't go backwards.
				// @todo Don't think is the real solution....
				$lastAutoPos = isset($lastAutoPos) ? $lastAutoPos : 0;
				if ($pos < $lastAutoPos)
					$no_autolink_area = true;
				$lastAutoPos = $pos;

				if (!$no_autolink_area)
				{
					// Parse any URLs.... have to get rid of the @ problems some things cause... stupid email addresses.
					if (!isset($disabled['url']) && (strpos($data, '://') !== false || strpos($data, 'www.') !== false) && strpos($data, '[url') === false)
					{
						// Switch out quotes really quick because they can cause problems.
						$data = strtr($data, array('&#039;' => '\'', '&nbsp;' => "\xC2\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));

						// Check for links with special () checking to allow links with ) in them
						if (is_string($result = preg_replace_callback('~(?<=([\s>\.(;\'"])|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\p{L}\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\]?)~ui', 'parse_autolink', $data)));
							$data = $result;

						// Only do this if the preg survives.
						if (is_string($result = preg_replace(array(
							'~(?<=[\s>\.(;\'"]|^)((?:ftp|ftps)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\w\-_\~%\.@,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\]?)~i',
							'~(?<=[\s>(\'<]|^)(www(?:\.[\w\-_]+)+(?::\d+)?(?:/[\p{L}\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\])~ui'
						), array(
							'[ftp]$1[/ftp]',
							'[url=http://$1]$1[/url]'
						), $data)))
							$data = $result;

						$data = strtr($data, array('\'' => '&#039;', "\xC2\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
					}

					// Next, emails...
					if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false)
					{
						$data = preg_replace('~(?<=[\?\s' . $non_breaking_space . '\[\]()*\\\;>]|^)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?,\s' . $non_breaking_space . '\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br />))~u', '[email]$1[/email]', $data);
						$data = preg_replace('~(?<=<br />)([\w\-\.]{1,80}@[\w\-]+\.[\w\-\.]+[\w\-])(?=[?\.,;\s' . $non_breaking_space . '\[\]()*\\\]|$|<br />|&nbsp;|&gt;|&lt;|&quot;|&#039;)~u', '[email]$1[/email]', $data);
					}
				}
			}

			$data = strtr($data, array("\t" => '&nbsp;&nbsp;&nbsp;'));

			// If it wasn't changed, no copying or other boring stuff has to happen!
			if ($data != substr($message, $last_pos, $pos - $last_pos))
			{
				$message = substr($message, 0, $last_pos) . $data . substr($message, $pos);

				// Since we changed it, look again in case we added or removed a tag.  But we don't want to skip any.
				$old_pos = strlen($data) + $last_pos;
				$pos = strpos($message, '[', $last_pos);
				$pos = $pos === false ? $old_pos : min($pos, $old_pos);
			}
		}

		// Are we there yet?  Are we there yet?
		if ($pos >= strlen($message) - 1)
			break;

		$tags = strtolower($message[$pos + 1]);

		if ($tags == '/' && !empty($open_tags))
		{
			$pos2 = strpos($message, ']', $pos + 1);
			if ($pos2 == $pos + 2)
				continue;

			$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

			$to_close = array();
			$block_level = null;

			do
			{
				$tag = array_pop($open_tags);
				if (!$tag)
					break;

				if (!empty($tag['block_level']))
				{
					// Only find out if we need to.
					if ($block_level === false)
					{
						array_push($open_tags, $tag);
						break;
					}

					// The idea is, if we are LOOKING for a block level tag, we can close them on the way.
					if (strlen($look_for) > 0 && isset($bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] == $look_for)
							{
								$block_level = !empty($temp['block_level']);
								break;
							}
					}

					if ($block_level !== true)
					{
						$block_level = false;
						array_push($open_tags, $tag);
						break;
					}
				}

				$to_close[] = $tag;
			}
			while ($tag['tag'] != $look_for);

			// Did we just eat through everything and not find it?
			if ((empty($open_tags) && (empty($tag) || $tag['tag'] != $look_for)))
			{
				$open_tags = $to_close;
				continue;
			}
			elseif (!empty($to_close) && $tag['tag'] != $look_for)
			{
				if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
				{
					foreach ($bbc_codes[$look_for[0]] as $temp)
						if ($temp['tag'] == $look_for)
						{
							$block_level = !empty($temp['block_level']);
							break;
						}
				}

				// We're not looking for a block level tag (or maybe even a tag that exists...)
				if (!$block_level)
				{
					foreach ($to_close as $tag)
						array_push($open_tags, $tag);
					continue;
				}
			}

			foreach ($to_close as $tag)
			{
				$message = substr($message, 0, $pos) . "\n" . $tag['after'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['after']) + 2;
				$pos2 = $pos - 1;

				// See the comment at the end of the big loop - just eating whitespace ;).
				if (!empty($tag['block_level']) && substr($message, $pos, 6) == '<br />')
					$message = substr($message, 0, $pos) . substr($message, $pos + 6);
				if (!empty($tag['trim']) && $tag['trim'] != 'inside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));
			}

			if (!empty($to_close))
			{
				$to_close = array();
				$pos--;
			}

			continue;
		}

		// No tags for this character, so just keep going (fastest possible course.)
		if (!isset($bbc_codes[$tags]))
			continue;

		$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
		$tag = null;
		foreach ($bbc_codes[$tags] as $possible)
		{
			$pt_strlen = strlen($possible['tag']);

			// Not a match?
			if (strtolower(substr($message, $pos + 1, $pt_strlen)) != $possible['tag'])
				continue;

			$next_c = isset($message[$pos + 1 + $pt_strlen]) ? $message[$pos + 1 + $pt_strlen] : '';

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + $pt_strlen + 1)) === 0)
				continue;
			// Do we want parameters?
			elseif (!empty($possible['parameters']))
			{
				if ($next_c != ' ')
					continue;
			}
			elseif (isset($possible['type']))
			{
				// Do we need an equal sign?
				if (in_array($possible['type'], array('unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals')) && $next_c != '=')
					continue;
				// Maybe we just want a /...
				if ($possible['type'] == 'closed' && $next_c != ']' && substr($message, $pos + 1 + $pt_strlen, 2) != '/]' && substr($message, $pos + 1 + $pt_strlen, 3) != ' /]')
					continue;
				// An immediate ]?
				if ($possible['type'] == 'unparsed_content' && $next_c != ']')
					continue;
			}
			// No type means 'parsed_content', which demands an immediate ] without parameters!
			elseif ($next_c != ']')
				continue;

			// Check allowed tree?
			if (isset($possible['require_parents']) && ($inside === null || !in_array($inside['tag'], $possible['require_parents'])))
				continue;
			elseif (isset($inside['require_children']) && !in_array($possible['tag'], $inside['require_children']))
				continue;
			// If this is in the list of disallowed child tags, don't parse it.
			elseif (isset($inside['disallow_children']) && in_array($possible['tag'], $inside['disallow_children']))
				continue;
			// Not allowed in this parent, replace the tags or show it like regular text
			elseif (isset($possible['disallow_parents']) && ($inside !== null && in_array($inside['tag'], $possible['disallow_parents'])))
			{
				if (!isset($possible['disallow_before'], $possible['disallow_after']))
					continue;
				$possible['before'] = isset($possible['disallow_before']) ? $possible['disallow_before'] : $possible['before'];
				$possible['after'] = isset($possible['disallow_after']) ? $possible['disallow_after'] : $possible['after'];
			}

			$pos1 = $pos + 1 + $pt_strlen + 1;

			// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
			if ($possible['tag'] == 'quote')
			{
				// Start with standard
				$quote_alt = false;
				foreach ($open_tags as $open_quote)
				{
					// Every parent quote this quote has flips the styling
					if ($open_quote['tag'] == 'quote')
						$quote_alt = !$quote_alt;
				}
				// Add a class to the quote to style alternating blockquotes
				// @todo - Frankly it makes little sense to allow alternate blockquote
				// styling without also catering for alternate quoteheader styling.
				// I do remember coding that some time back, but it seems to have gotten
				// lost somewhere in the Elk processes.
				// Come to think of it, it may be better to append a second class rather
				// than alter the standard one.
				//  - Example: class="bbc_quote" and class="bbc_quote alt_quote".
				// This would mean simpler CSS for themes (like default) which do not use the alternate styling,
				// but would still allow it for themes that want it.
				$possible['before'] = strtr($possible['before'], array('<blockquote>' => '<blockquote class="bbc_' . ($quote_alt ? 'alternate' : 'standard') . '_quote">'));
			}

			// This is long, but it makes things much easier and cleaner.
			if (!empty($possible['parameters']))
			{
				// Build a regular expression for each parameter for the current tag
				$preg = array();
				foreach ($possible['parameters'] as $p => $info)
					$preg[] = '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . ')' . (empty($info['optional']) ? '' : '?');

				// Okay, this may look ugly and it is, but it's not going to happen much and it is the best way
				// of allowing any order of parameters but still parsing them right.
				$param_size = count($preg) - 1;
				$preg_keys = range(0, $param_size);
				$message_stub = substr($message, $pos1 - 1);

				// If an addon adds many parameters we can exceed max_execution time, lets prevent that
				// 5040 = 7, 40,320 = 8, (N!) etc
				$max_iterations = 5040;

				// Step, one by one, through all possible permutations of the parameters until we have a match
				do {
					$match_preg = '~^';
					foreach ($preg_keys as $key)
						$match_preg .= $preg[$key];
					$match_preg .= '\]~i';

					// Check if this combination of parameters matches the user input
					$match = preg_match($match_preg, $message_stub, $matches) !== 0;
				} while (!$match && --$max_iterations && ($preg_keys = pc_next_permutation($preg_keys, $param_size)));

				// Didn't match our parameter list, try the next possible.
				if (!$match)
					continue;

				$params = array();
				for ($i = 1, $n = count($matches); $i < $n; $i += 2)
				{
					$key = strtok(ltrim($matches[$i]), '=');
					if (isset($possible['parameters'][$key]['value']))
						$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], array('$1' => $matches[$i + 1]));
					elseif (isset($possible['parameters'][$key]['validate']))
						$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
					else
						$params['{' . $key . '}'] = $matches[$i + 1];

					// Just to make sure: replace any $ or { so they can't interpolate wrongly.
					$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], array('$' => '&#036;', '{' => '&#123;'));
				}

				foreach ($possible['parameters'] as $p => $info)
				{
					if (!isset($params['{' . $p . '}']))
						$params['{' . $p . '}'] = '';
				}

				$tag = $possible;

				// Put the parameters into the string.
				if (isset($tag['before']))
					$tag['before'] = strtr($tag['before'], $params);
				if (isset($tag['after']))
					$tag['after'] = strtr($tag['after'], $params);
				if (isset($tag['content']))
					$tag['content'] = strtr($tag['content'], $params);

				$pos1 += strlen($matches[0]) - 1;
			}
			else
				$tag = $possible;
			break;
		}

		// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
		if ($smileys !== false && $tag === null && isset($message[$pos + 2]) && isset($itemcodes[$message[$pos + 1]]) && $message[$pos + 2] === ']' && !isset($disabled['list']) && !isset($disabled['li']))
		{
			if ($message[$pos + 1] == '0' && !in_array($message[$pos - 1], array(';', ' ', "\t", "\n", '>')))
				continue;

			$tag = $itemcodes[$message[$pos + 1]];

			// First let's set up the tree: it needs to be in a list, or after an li.
			if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
			{
				$open_tags[] = array(
					'tag' => 'list',
					'after' => '</ul>',
					'block_level' => true,
					'require_children' => array('li'),
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
				);
				$code = '<ul' . ($tag == '' ? '' : ' style="list-style-type: ' . $tag . '"') . ' class="bbc_list">';
			}
			// We're in a list item already: another itemcode?  Close it first.
			elseif ($inside['tag'] == 'li')
			{
				array_pop($open_tags);
				$code = '</li>';
			}
			else
				$code = '';

			// Now we open a new tag.
			$open_tags[] = array(
				'tag' => 'li',
				'after' => '</li>',
				'trim' => 'outside',
				'block_level' => true,
				'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
			);

			// First, open the tag...
			$code .= '<li>';
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
			$pos += strlen($code) - 1 + 2;

			// Next, find the next break (if any.)  If there's more itemcode after it, keep it going - otherwise close!
			$pos2 = strpos($message, '<br />', $pos);
			$pos3 = strpos($message, '[/', $pos);
			if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
			{
				preg_match('~^(<br />|&nbsp;|\s|\[)+~', substr($message, $pos2 + 6), $matches);
				$message = substr($message, 0, $pos2) . (!empty($matches[0]) && substr($matches[0], -1) == '[' ? '[/li]' : '[/li][/list]') . substr($message, $pos2);

				$open_tags[count($open_tags) - 2]['after'] = '</ul>';
			}
			// Tell the [list] that it needs to close specially.
			else
			{
				// Move the li over, because we're not sure what we'll hit.
				$open_tags[count($open_tags) - 1]['after'] = '';
				$open_tags[count($open_tags) - 2]['after'] = '</li></ul>';
			}

			continue;
		}

		// Implicitly close lists and tables if something other than what's required is in them.  This is needed for itemcode.
		if ($tag === null && $inside !== null && !empty($inside['require_children']))
		{
			array_pop($open_tags);

			$message = substr($message, 0, $pos) . "\n" . $inside['after'] . "\n" . substr($message, $pos);
			$pos += strlen($inside['after']) - 1 + 2;
		}

		// No tag?  Keep looking, then.  Silly people using brackets without actual tags.
		if ($tag === null)
			continue;

		// Propagate the list to the child (so wrapping the disallowed tag won't work either.)
		if (isset($inside['disallow_children']))
			$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $inside['disallow_children'])) : $inside['disallow_children'];

		// Is this tag disabled?
		if (isset($disabled[$tag['tag']]))
		{
			if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content']))
			{
				$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
				$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
				$tag['content'] = isset($tag['type']) && $tag['type'] == 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
			}
			elseif (isset($tag['disabled_before']) || isset($tag['disabled_after']))
			{
				$tag['before'] = isset($tag['disabled_before']) ? $tag['disabled_before'] : (!empty($tag['block_level']) ? '<div>' : '');
				$tag['after'] = isset($tag['disabled_after']) ? $tag['disabled_after'] : (!empty($tag['block_level']) ? '</div>' : '');
			}
			else
				$tag['content'] = $tag['disabled_content'];
		}

		// We use this alot
		$tag_strlen = strlen($tag['tag']);

		// The only special case is 'html', which doesn't need to close things.
		if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($inside['block_level']))
		{
			$n = count($open_tags) - 1;
			while (empty($open_tags[$n]['block_level']) && $n >= 0)
				$n--;

			// Close all the non block level tags so this tag isn't surrounded by them.
			for ($i = count($open_tags) - 1; $i > $n; $i--)
			{
				$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
				$ot_strlen = strlen($open_tags[$i]['after']);
				$pos += $ot_strlen + 2;
				$pos1 += $ot_strlen + 2;

				// Trim or eat trailing stuff... see comment at the end of the big loop.
				if (!empty($open_tags[$i]['block_level']) && substr($message, $pos, 6) == '<br />')
					$message = substr($message, 0, $pos) . substr($message, $pos + 6);
				if (!empty($open_tags[$i]['trim']) && $tag['trim'] != 'inside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

				array_pop($open_tags);
			}
		}

		// No type means 'parsed_content'.
		if (!isset($tag['type']))
		{
			// @todo Check for end tag first, so people can say "I like that [i] tag"?
			$open_tags[] = $tag;
			$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
			$pos += strlen($tag['before']) - 1 + 2;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] === 'unparsed_content')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 6) === '<br />')
				$data = substr($data, 6);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + $tag_strlen);

			$pos += strlen($code) - 1 + 2;
			$last_pos = $pos + 1;

		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] === 'unparsed_equals_content')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) == '&quot;';
				if ($tag['quoted'] !== 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
			if ($pos3 === false)
				continue;

			$data = array(
				substr($message, $pos2 + ($quoted == false ? 1 : 7), $pos3 - ($pos2 + ($quoted == false ? 1 : 7))),
				substr($message, $pos1, $pos2 - $pos1)
			);

			if (!empty($tag['block_level']) && substr($data[0], 0, 6) === '<br />')
				$data[0] = substr($data[0], 6);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
			$pos += strlen($code) - 1 + 2;
		}
		// A closed tag, with no content or value.
		elseif ($tag['type'] === 'closed')
		{
			$pos2 = strpos($message, ']', $pos);
			$message = substr($message, 0, $pos) . "\n" . $tag['content'] . "\n" . substr($message, $pos2 + 1);
			$pos += strlen($tag['content']) - 1 + 2;
		}
		// This one is sorta ugly... :/
		elseif ($tag['type'] === 'unparsed_commas_content')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag_strlen) . ']', $pos2);
			if ($pos3 === false)
				continue;

			// We want $1 to be the content, and the rest to be csv.
			$data = explode(',', ',' . substr($message, $pos1, $pos2 - $pos1));
			$data[0] = substr($message, $pos2 + 1, $pos3 - $pos2 - 1);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = $tag['content'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag_strlen);
			$pos += strlen($code) - 1 + 2;
		}
		// This has parsed content, and a csv value which is unparsed.
		elseif ($tag['type'] === 'unparsed_commas')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = explode(',', substr($message, $pos1, $pos2 - $pos1));

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// Fix after, for disabled code mainly.
			foreach ($data as $k => $d)
				$tag['after'] = strtr($tag['after'], array('$' . ($k + 1) => trim($d)));

			$open_tags[] = $tag;

			// Replace them out, $1, $2, $3, $4, etc.
			$code = $tag['before'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 1);
			$pos += strlen($code) - 1 + 2;
		}
		// A tag set to a value, parsed or not.
		elseif ($tag['type'] === 'unparsed_equals' || $tag['type'] === 'parsed_equals')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) == '&quot;';
				if ($tag['quoted'] !== 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// For parsed content, we must recurse to avoid security problems.
			if ($tag['type'] !== 'unparsed_equals')
			{
				$data = parse_bbc($data, !empty($tag['parsed_tags_allowed']) ? false : true, '', !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : array());

				// Unfortunately after we recurse, we must manually reset the static disabled tags to what they were
				parse_bbc('dummy');
			}

			$tag['after'] = strtr($tag['after'], array('$1' => $data));

			$open_tags[] = $tag;

			$code = strtr($tag['before'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted == false ? 1 : 7));
			$pos += strlen($code) - 1 + 2;
		}

		// If this is block level, eat any breaks after it.
		if (!empty($tag['block_level']) && substr($message, $pos + 1, 6) === '<br />')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 7);

		// Are we trimming outside this tag?
		if (!empty($tag['trim']) && $tag['trim'] !== 'outside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($message, $pos + 1), $matches) != 0)
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 1 + strlen($matches[0]));
	}

	// Close any remaining tags.
	while ($tag = array_pop($open_tags))
		$message .= "\n" . $tag['after'] . "\n";

	// Parse the smileys within the parts where it can be done safely.
	if ($smileys === true)
	{
		$message_parts = explode("\n", $message);
		for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			parsesmileys($message_parts[$i]);

		$message = implode('', $message_parts);
	}

	// No smileys, just get rid of the markers.
	else
		$message = strtr($message, array("\n" => ''));

	if (isset($message[0]) && $message[0] === ' ')
		$message = '&nbsp;' . substr($message, 1);

	// Cleanup whitespace.
	$message = strtr($message, array('  ' => '&nbsp; ', "\r" => '', "\n" => '<br />', '<br /> ' => '<br />&nbsp;', '&#13;' => "\n"));

	// Finish footnotes if we have any.
	if (strpos($message, '<sup class="bbc_footnotes">') !== false)
	{
		global $fn_num, $fn_content, $fn_count;
		static $fn_total;

		// @todo temporary until we have nesting
		$message = str_replace(array('[footnote]', '[/footnote]'), '', $message);

		$fn_num = 0;
		$fn_content = array();
		$fn_count = isset($fn_total) ? $fn_total : 0;

		// Replace our footnote text with a [1] link, save the text for use at the end of the message
		$message = preg_replace_callback('~(%fn%(.*?)%fn%)~is', 'footnote_callback', $message);
		$fn_total += $fn_num;

		// If we have footnotes, add them in at the end of the message
		if (!empty($fn_num))
			$message .= '<div class="bbc_footnotes">' . implode('', $fn_content) . '</div>';
	}

	// Allow addons access to what parse_bbc created
	call_integration_hook('integrate_post_parsebbc', array(&$message, &$smileys, &$cache_id, &$parse_tags));

	// Cache the output if it took some time...
	if (isset($cache_key, $cache_t) && microtime(true) - $cache_t > 0.05)
		cache_put_data($cache_key, $message, 240);

	// If this was a force parse revert if needed.
	if (!empty($parse_tags))
	{
		if (empty($temp_bbc))
			$bbc_codes = array();
		else
		{
			$bbc_codes = $temp_bbc;
			unset($temp_bbc);
		}
	}

	return $message;
}

/**
 * Call back function for footnotes, builds the unique id and to/for link
 * for each footnote in a message and page
 *
 * @param mixed[] $matches
 * @return string
 */
function footnote_callback($matches)
{
	global $fn_num, $fn_content, $fn_count;

	$fn_num++;
	$fn_content[] = '<div class="target" id="fn' . $fn_num . '_' . $fn_count . '"><sup>' . $fn_num . '&nbsp;</sup>' . $matches[2] . '<a class="footnote_return" href="#ref' . $fn_num . '_' . $fn_count . '">&crarr;</a></div>';

	return '<a class="target" href="#fn' . $fn_num . '_' . $fn_count . '" id="ref' . $fn_num . '_' . $fn_count . '">[' . $fn_num . ']</a>';
}

/**
 * Callback function for autolinking.
 * - If the look behind contains ( then it will trim any trailing ) from the link
 * this to allow (link/path) where the path contains ) characters as allowed by RFC
 *
 * @param mixed[] $matches
 * @return string
 */
function parse_autolink($matches)
{
 	if ($matches[1] === '(' && substr($matches[2], -1) === ')')
		return '[url]' . rtrim($matches[2], ')') . '[/url])';
	else
		return '[url]' . $matches[2] . '[/url]';
}

/**
 * Parse smileys in the passed message.
 *
 * What it does:
 * - The smiley parsing function which makes pretty faces appear :).
 * - If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
 * - These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
 * - Caches the smileys from the database or array in memory.
 * - Doesn't return anything, but rather modifies message directly.
 *
 * @param string $message
 */
function parsesmileys(&$message)
{
	global $modSettings, $txt, $user_info;
	static $smileyPregSearch = null, $smileyPregReplacements = array(), $callback;

	$db = database();

	// No smiley set at all?!
	if ($user_info['smiley_set'] == 'none' || trim($message) == '')
		return;

	// If smileyPregSearch hasn't been set, do it now.
	if (empty($smileyPregSearch))
	{
		// Use the default smileys if it is disabled. (better for "portability" of smileys.)
		if (empty($modSettings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:)');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');
			$smileysdescs = array('', $txt['icon_cheesy'], $txt['icon_rolleyes'], $txt['icon_angry'], $txt['icon_laugh'], $txt['icon_smiley'], $txt['icon_wink'], $txt['icon_grin'], $txt['icon_sad'], $txt['icon_shocked'], $txt['icon_cool'], $txt['icon_tongue'], $txt['icon_huh'], $txt['icon_embarrassed'], $txt['icon_lips'], $txt['icon_kiss'], $txt['icon_cry'], $txt['icon_undecided'], '', '', '', $txt['icon_angel']);
		}
		else
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('parsing_smileys', 480)) == null)
			{
				$result = $db->query('', '
					SELECT code, filename, description
					FROM {db_prefix}smileys
					ORDER BY LENGTH(code) DESC',
					array(
					)
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdescs = array();
				while ($row = $db->fetch_assoc($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = htmlspecialchars($row['filename']);
					$smileysdescs[] = $row['description'];
				}
				$db->free_result($result);

				cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;
		}

		// The non-breaking-space is a complex thing...
		$non_breaking_space = '\x{A0}';

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$smileyPregReplacements = array();
		$searchParts = array();
		$smileys_path = htmlspecialchars($modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/');

		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$specialChars = htmlspecialchars($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<img src="' . $smileys_path . $smileysto[$i] . '" alt="' . strtr($specialChars, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley" />';

			$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;

			$searchParts[] = preg_quote($smileysfrom[$i], '~');
			if ($smileysfrom[$i] != $specialChars)
			{
				$smileyPregReplacements[$specialChars] = $smileyCode;
				$searchParts[] = preg_quote($specialChars, '~');
			}
		}

		$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~';
		$callback = new ParseSmileysReplacement;
		$callback->replacements = $smileyPregReplacements;
	}

	// Replace away!
	// @todo When support changes to PHP 5.3+, this can be changed this to "use" keyword and simpifly this.
	$message = preg_replace_callback($smileyPregSearch, array($callback, 'callback'), $message);
}

/**
 * Smiley Replacment Callback.
 *
 * This is needed until ELK supports PHP 5.3+ and we can change to "use"
 */
class ParseSmileysReplacement
{
	/**
	 * Our callback that does the actual smiley replacments.
	 *
	 * @param string[] $matches
	 */
	public function callback($matches)
	{
		if (isset($this->replacements[$matches[0]]))
			return $this->replacements[$matches[0]];
		else
			return '';
	}
}

/**
 * Highlight any code.
 *
 * What it does:
 * - Uses PHP's highlight_string() to highlight PHP syntax
 * - does special handling to keep the tabs in the code available.
 * - used to parse PHP code from inside [code] and [php] tags.
 *
 * @param string $code
 * @return string the code with highlighted HTML.
 */
function highlight_php_code($code)
{
	// Remove special characters.
	$code = un_htmlspecialchars(strtr($code, array('<br />' => "\n", "\t" => '___TAB();', '&#91;' => '[')));

	$buffer = str_replace(array("\n", "\r"), '', @highlight_string($code, true));

	// Yes, I know this is kludging it, but this is the best way to preserve tabs from PHP :P.
	$buffer = preg_replace('~___TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\\(\\);~', '<pre style="display: inline;">' . "\t" . '</pre>', $buffer);

	return strtr($buffer, array('\'' => '&#039;', '<code>' => '', '</code>' => ''));
}

/**
 * Ends execution and redirects the user to a new location
 *
 * What it does:
 * - Makes sure the browser doesn't come back and repost the form data.
 * - Should be used whenever anything is posted.
 * - Calls AddMailQueue to process any mail queue items its can
 * - Calls call_integration_hook integrate_redirect before headers are sent
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
			$setLocation = preg_replace_callback('~^' . preg_quote($scripturl, '~') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~', 'redirectexit_callback', $setLocation);
		else
			$setLocation = preg_replace_callback('~^' . preg_quote($scripturl, '~') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~', 'redirectexit_callback', $setLocation);
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
 *
 * What it does:
 * - Similar to the callback function used in ob_sessrewrite
 * - Envoked by enabling queryless_urls for systems that support that function
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
 *
 * What it does:
 * - Takes care of template loading and remembering the previous URL.
 * - Calls ob_start() with ob_sessrewrite to fix URLs if necessary.
 *
 * @param bool|null $header = null
 * @param bool|null $do_footer = null
 * @param bool $from_index = false
 * @param bool $from_fatal_error = false
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $txt;

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
	$invalid_old_url = array(
		'action=dlattach',
		'action=jsoption',
		'action=viewadminfile',
		';xml',
		';api',
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
	if ($make_old === true)
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
 *
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
	{
		$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];
		$context['upper_content_callbacks'][] = 'news_fader';
	}

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

		$context['user']['avatar'] = array(
			'href' => !empty($user_info['avatar']['href']) ? $user_info['avatar']['href'] : '',
			'image' => !empty($user_info['avatar']['image']) ? $user_info['avatar']['image'] : '',
		);

		// @deprecated since 1.0.2
		if (!empty($modSettings['avatar_max_width']))
			$context['user']['avatar']['width'] = $modSettings['avatar_max_width'];

		// @deprecated since 1.0.2
		if (!empty($modSettings['avatar_max_height']))
			$context['user']['avatar']['height'] = $modSettings['avatar_max_height'];

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
		$txt['welcome_guest'] = replaceBasicActionUrl($txt['welcome_guest']);

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
		addJavascriptVar(array('elk_scripturl' => '\'' . $scripturl . '\''));

	if (!isset($context['page_title']))
		$context['page_title'] = '';

	// Set some specific vars.
	$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');
	$context['meta_keywords'] = !empty($modSettings['meta_keywords']) ? Util::htmlspecialchars($modSettings['meta_keywords']) : '';

	// Load a custom CSS file?
	if (file_exists($settings['theme_dir'] . '/css/custom.css'))
		loadCSSFile('custom.css');
	if (!empty($context['theme_variant']) && file_exists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/custom' . $context['theme_variant'] . '.css'))
		loadCSSFile($context['theme_variant'] . '/custom' . $context['theme_variant'] . '.css');
}

/**
 * Helper function to set the system memory to a needed value
 *
 * What it does:
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
		// fall through select g = 1024*1024*1024
		case 'g':
			$num *= 1024;
		// fall through select m = 1024*1024
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
	$forum_copyright = replaceBasicActionUrl(sprintf($forum_copyright, $forum_version));

	echo '
					', $forum_copyright;
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
 *
 * What it does:
 * - tabbing in this function is to make the HTML source look proper
 * - outputs jQuery/jQueryUI from the proper source (local/CDN)
 * - if defered is set function will output all JS (source & inline) set to load at page end
 * - if the admin option to combine files is set, will use Combiner.class
 *
 * @param bool $do_defered = false
 */
function template_javascript($do_defered = false)
{
	global $context, $modSettings, $settings, $boardurl;

	// First up, load jQuery and jQuery UI
	if (isset($modSettings['jquery_source']) && !$do_defered)
	{
		// Using a specified version of jquery or what was shipped 1.11.1  / 1.10.4
		$jquery_version = (!empty($modSettings['jquery_default']) && !empty($modSettings['jquery_version'])) ? $modSettings['jquery_version'] : '1.11.1';
		$jqueryui_version = (!empty($modSettings['jqueryui_default']) && !empty($modSettings['jqueryui_version'])) ? $modSettings['jqueryui_version'] : '1.10.4';

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
	<script src="' . $settings['default_theme_url'] . '/scripts/jquery-ui-' . $jqueryui_version . '.min.js" id="jqueryui"></script>' : '');
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
		window.jQuery.ui || document.write(\'<script src="' . $settings['default_theme_url'] . '/scripts/jquery-ui-' . $jqueryui_version . '.min.js"><\/script>\')' : ''), '
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
			require_once(SOURCEDIR . '/SiteCombiner.class.php');
			$combiner = new Site_Combiner(CACHEDIR, $boardurl . '/cache');
			$combine_name = $combiner->site_js_combine($context['javascript_files'], $do_defered);

			call_integration_hook('post_javascript_combine', array(&$combine_name, $combiner));

			if (!empty($combine_name))
				echo '
	<script src="', $combine_name, '" id="jscombined', $do_defered ? 'bottom' : 'top', '"></script>';
			// While we have Javascript files to place in the template
			foreach ($combiner->getSpares() as $id => $js_file)
			{
				if ((!$do_defered && empty($js_file['options']['defer'])) || ($do_defered && !empty($js_file['options']['defer'])))
					echo '
	<script src="', $js_file['filename'], '" id="', $id, '"', !empty($js_file['options']['async']) ? ' async="async"' : '', '></script>';
			}
		}
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
 *
 * What it does:
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
			require_once(SOURCEDIR . '/SiteCombiner.class.php');
			$combiner = new Site_Combiner(CACHEDIR, $boardurl . '/cache');
			$combine_name = $combiner->site_css_combine($context['css_files']);

			call_integration_hook('post_css_combine', array(&$combine_name, $combiner));

			if (!empty($combine_name))
				echo '
	<link rel="stylesheet" href="', $combine_name, '" id="csscombined" />';

			foreach ($combiner->getSpares() as $id => $file)
				echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id,'" />';
		}
		else
		{
			foreach ($context['css_files'] as $id => $file)
				echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id,'" />';
		}
	}
}

/**
 * Calls on template_show_error from index.template.php to show warnings
 * and security errors for admins
 */
function template_admin_warning_above()
{
	global $context, $txt;

	if (!empty($context['security_controls_files']))
	{
		$context['security_controls_files']['type'] = 'serious';
		template_show_error('security_controls_files');
	}

	if (!empty($context['security_controls_query']))
	{
		$context['security_controls_query']['type'] = 'serious';
		template_show_error('security_controls_query');
	}

	if (!empty($context['security_controls_ban']))
	{
		$context['security_controls_ban']['type'] = 'serious';
		template_show_error('security_controls_ban');
	}

	if (!empty($context['new_version_updates']))
	{
		template_show_error('new_version_updates');
	}

	// Any special notices to remind the admin about?
	if (!empty($context['warning_controls']))
	{
		$context['warning_controls']['errors'] = $context['warning_controls'];
		$context['warning_controls']['title'] = $txt['admin_warning_title'];
		$context['warning_controls']['type'] = 'warning';
		template_show_error('warning_controls');
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
			$modSettings['attachmentUploadDir'] = Util::unserialize($modSettings['attachmentUploadDir']);
		$path = isset($modSettings['attachmentUploadDir'][$dir]) ? $modSettings['attachmentUploadDir'][$dir] : $modSettings['basedirectory_for_attachments'];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash . '.elk';
}

/**
 * Convert a single IP to a ranged IP.
 *
 * - internal function used to convert a user-readable format to a format suitable for the database.
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

	if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null || empty($ip))
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
 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
 *
 * @param string $text
 * @param int|null $max_chars = 20
 *     - if encrypt = true this is the maximum number of bytes to use in integer hashes (for searching)
 *     - if encrypt = false this is the maximum number of letters in each word
 * @param bool $encrypt = false Used for custom search indexes to return an array of ints representing the words
 */
function text2words($text, $max_chars = 20, $encrypt = false)
{
	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', strtr($text, array('<br />' => ' ')));

	// Step 2: Entities we left to letters, where applicable, lowercase.
	$words = un_htmlspecialchars(Util::strtolower($words));

	// Step 3: Ready to split apart and index!
	$words = explode(' ', $words);

	if ($encrypt)
	{
		// Range of characters that crypt will produce (0-9, a-z, A-Z .)
		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));
		$returned_ints = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				// Get a crypt representation of this work
				$encrypted = substr(crypt($word, 'uk'), 2, $max_chars);
				$total = 0;

				// Create an integer reprsentation
				for ($i = 0; $i < $max_chars; $i++)
					$total += $possible_chars[ord($encrypted{$i})] * pow(63, $i);

				// Return the value
				$returned_ints[] = $max_chars == 4 ? min($total, 16777215) : $total;
			}
		}
		return array_unique($returned_ints);
	}
	else
	{
		// Trim characters before and after and add slashes for database insertion.
		$returned_words = array();
		foreach ($words as $word)
			if (($word = trim($word, '-_\'')) !== '')
				$returned_words[] = $max_chars === null ? $word : substr($word, 0, $max_chars);

		// Filter out all words that occur more than once.
		return array_unique($returned_words);
	}
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
 *
 * What it does:
 * - defines every master item in the menu, as well as any sub-items
 * - ensures the chosen action is set so the menu is highlighted
 * - Saves them in the cache if it is available and on
 * - Places the results in $context
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

	if ($context['allow_search'])
		$context['theme_header_callbacks'] = elk_array_insert($context['theme_header_callbacks'], 'login_bar', array('search_bar'), 'after');

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
				'title' => (!empty($user_info['avatar']['href']) ? '<img class="avatar" src="' . $user_info['avatar']['href'] . '" alt="" /> ' : '') . (!empty($modSettings['displayMemberNames']) ? $user_info['name'] : $txt['account_short']),
				'href' => $scripturl . '?action=profile',
				'data-icon' => '&#xf007;',
				'show' => $context['allow_edit_profile'],
				'sub_buttons' => array(
					'account' => array(
						'title' => $txt['account'],
						'href' => $scripturl . '?action=profile;area=account',
						'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
					),
					'forumprofile' => array(
						'title' => $txt['forumprofile'],
						'href' => $scripturl . '?action=profile;area=forumprofile',
						'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
					),
					'theme' => array(
						'title' => $txt['theme'],
						'href' => $scripturl . '?action=profile;area=theme',
						'show' => allowedTo(array('profile_extra_any', 'profile_extra_own', 'profile_extra_any')),
					),
					'logout' => array(
						'title' => $txt['logout'],
						'href' => $scripturl . '?action=logout',
						'show' => !$user_info['is_guest'],
					),
				),
			),
			// @todo Look at doing something here, to provide instant access to inbox when using click menus.
			// @todo A small pop-up anchor seems like the obvious way to handle it. ;)
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
			'mentions' => array(
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

			'contact' => array(
				'title' => $txt['contact'],
				'href' => $scripturl . '?action=contact',
				'data-icon' => '&#xf095;',
				'show' => $user_info['is_guest'] && !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] == 'menu',
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
 *
 * What it does:
 * - calls all functions of the given hook.
 * - supports static class method calls.
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
		if (strpos($function, '|') !== false)
			list ($call, $file) = explode('|', $function);
		else
			$call = $function;

		// OOP static method
		if (strpos($call, '::') !== false)
			$call = explode('::', $call);

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

		if (strpos($function, '|') !== false)
			list($call, $file) = explode('|', $function);
		else
			$call = $function;

		// OOP static method
		if (strpos($call, '::') !== false)
		{
			$call = explode('::', $call);
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
 *
 * - does nothing if the function is already added.
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

	$integration_call = (!empty($file) && $file !== true) ? $function . '|' . $file : $function;

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
 *
 * What it does:
 * - Removes the given function from the given hook.
 * - Does nothing if the function is not available.
 *
 * @param string $hook
 * @param string $function
 * @param string $file
 */
function remove_integration_function($hook, $function, $file = '')
{
	global $modSettings;

	$db = database();
	$integration_call = (!empty($file) && $file !== true) ? $function . '|' . $file : $function;

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

	// If we found entries for this hook
	if (!empty($current_functions))
	{
		$current_functions = explode(',', $current_functions);

		if (in_array($integration_call, $current_functions))
		{
			updateSettings(array($hook => implode(',', array_diff($current_functions, array($integration_call)))));
			if (empty($modSettings[$hook]))
				removeSettings($hook);
		}
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
 * Microsoft uses their own character set Code Page 1252 (CP1252), which is a
 * superset of ISO 8859-1, defining several characters between DEC 128 and 159
 * that are not normally displayable.  This converts the popular ones that
 * appear from a cut and paste from windows.
 *
 * @param string|false $string
 * @return string $string
 */
function sanitizeMSCutPaste($string)
{
	if (empty($string))
		return $string;

	// UTF-8 occurences of MS special characters
	$findchars_utf8 = array(
		"\xe2\x80\x9a", // single low-9 quotation mark
		"\xe2\x80\x9e", // double low-9 quotation mark
		"\xe2\x80\xa6", // horizontal ellipsis
		"\xe2\x80\x98", // left single curly quote
		"\xe2\x80\x99", // right single curly quote
		"\xe2\x80\x9c", // left double curly quote
		"\xe2\x80\x9d", // right double curly quote
		"\xe2\x80\x93", // en dash
		"\xe2\x80\x94", // em dash
	);

	// safe replacements
	$replacechars = array(
		',',   // &sbquo;
		',,',  // &bdquo;
		'...', // &hellip;
		"'",   // &lsquo;
		"'",   // &rsquo;
		'"',   // &ldquo;
		'"',   // &rdquo;
		'-',   // &ndash;
		'--',  // &mdash;
	);

	$string = str_replace($findchars_utf8, $replacechars, $string);

	return $string;
}

/**
 * Decode numeric html entities to their UTF8 equivalent character.
 *
 * What it does:
 * - Callback function for preg_replace_callback in subs-members
 * - Uses capture group 2 in the supplied array
 * - Does basic scan to ensure characters are inside a valid range
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
 * What it does:
 * - Callback function for preg_replace_callback
 * - Uses capture group 1 in the supplied array
 * - Does basic checks to keep characters inside a viewable range.
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
 * What it does:
 * - Callback function used of preg_replace_callback in various $ent_checks,
 * - for example strpos, strlen, substr etc
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
		$search_engines = Util::unserialize($modSettings['additional_search_engines']);
		foreach ($search_engines as $engine)
			$engines[strtolower(preg_replace('~[^A-Za-z0-9 ]~', '', $engine['name']))] = $engine;
	}

	return $engines;
}

/**
 * This function receives a request handle and attempts to retrieve the next result.
 *
 * What it does:
 * - It is used by the controller callbacks from the template, such as
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
 *
 * What it does:
 * - Intended for addon use to allow such things as
 * - adding in a new menu item to an existing menu array
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
 * Run a scheduled task now
 *
 * What it does:
 * - From time to time it may be necessary to fire a scheduled task ASAP
 * - This function sets the scheduled task to be called before any other one
 *
 * @param string $task the name of a scheduled task
 */
function scheduleTaskImmediate($task)
{
	global $modSettings;

	if (!isset($modSettings['scheduleTaskImmediate']))
		$scheduleTaskImmediate = array();
	else
		$scheduleTaskImmediate = Util::unserialize($modSettings['scheduleTaskImmediate']);

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
 */
function removeScheduleTaskImmediate($task, $calculateNextTrigger = true)
{
	global $modSettings;

	// Not on, bail
	if (!isset($modSettings['scheduleTaskImmediate']))
		return;
	else
		$scheduleTaskImmediate = Util::unserialize($modSettings['scheduleTaskImmediate']);

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
 * @param string $string the string to inject URLs into
 * @return string the input string with the place-holders replaced with
 *           the correct URLs
 */
function replaceBasicActionUrl($string)
{
	global $scripturl, $context, $boardurl;
	static $find = null, $replace = null;

	if ($find === null)
	{
		$find = array(
			'{forum_name}',
			'{forum_name_html_safe}',
			'{forum_name_html_unsafe}',
			'{script_url}',
			'{board_url}',
			'{login_url}',
			'{register_url}',
			'{activate_url}',
			'{help_url}',
			'{admin_url}',
			'{moderate_url}',
			'{recent_url}',
			'{search_url}',
			'{who_url}',
			'{credits_url}',
			'{calendar_url}',
			'{memberlist_url}',
			'{stats_url}',
		);
		$replace = array(
			$context['forum_name'],
			$context['forum_name_html_safe'],
			un_htmlspecialchars($context['forum_name_html_safe']),
			$scripturl,
			$boardurl,
			$scripturl . '?action=login',
			$scripturl . '?action=register',
			$scripturl . '?action=activate',
			$scripturl . '?action=help',
			$scripturl . '?action=admin',
			$scripturl . '?action=moderate',
			$scripturl . '?action=recent',
			$scripturl . '?action=search',
			$scripturl . '?action=who',
			$scripturl . '?action=who;sa=credits',
			$scripturl . '?action=calendar',
			$scripturl . '?action=memberlist',
			$scripturl . '?action=stats',
		);
		call_integration_hook('integrate_basic_url_replacement', array(&$find, &$replace));
	}

	return str_replace($find, $replace, $string);
}

/**
 * This function has the only task to retrieve the correct prefix to be used
 * in responses.
 *
 * @return string - The prefix in the default language of the forum
 */
function response_prefix()
{
	global $language, $user_info, $txt;
	static $response_prefix = null;

	if ($response_prefix === null && !($response_prefix = cache_get_data('response_prefix')))
	{
		if ($language === $user_info['language'])
			$response_prefix = $txt['response_prefix'];
		else
		{
			loadLanguage('index', $language, false);
			$response_prefix = $txt['response_prefix'];
			loadLanguage('index');
		}

		cache_put_data('response_prefix', $response_prefix, 600);
	}

	return $response_prefix;
}

/**
 * A very simple function to determine if an email address is "valid" for Elkarte.
 * A valid email for ElkArte is something that resebles an email (filter_var) and
 * is less than 255 characters (for database limits)
 *
 * @param string $value - The string to evaluate as valid email
 * @return bool|string - The email if valid, false if not a valid email
 */
function isValidEmail($value)
{
	$value = trim($value);
	if (filter_var($value, FILTER_VALIDATE_EMAIL) && Util::strlen($value) < 255)
		return $value;
	else
		return false;
}

/**
 * Helper function able to determine if the current member can see at least
 * one button of a button strip.
 *
 * @param mixed[] $button_strip
 * @return bool
 */
function can_see_button_strip($button_strip)
{
	global $context;

	foreach ($button_strip as $key => $value)
	{
		if (!isset($value['test']) || !empty($context[$value['test']]))
			return true;
	}

	return false;
}