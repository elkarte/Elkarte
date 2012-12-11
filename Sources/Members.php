<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file processes actions on members.
 *
 */

if (!defined('DIALOGO'))
	die('Hacking attempt...');

/**
 * This simple function adds/removes the passed user from the current users buddy list.
 * Requires profile_identity_own permission.
 * Called by ?action=buddy;u=x;session_id=y.
 * Subactions: sa=add and sa=remove. (@todo refactor subactions)
 * Redirects to ?action=profile;u=x.
 */
function action_buddy()
{
	global $user_info;

	checkSession('get');

	isAllowedTo('profile_identity_own');
	is_not_guest();

	if (empty($_REQUEST['u']))
		fatal_lang_error('no_access', false);
	$_REQUEST['u'] = (int) $_REQUEST['u'];

	// Remove if it's already there...
	if (in_array($_REQUEST['u'], $user_info['buddies']))
		$user_info['buddies'] = array_diff($user_info['buddies'], array($_REQUEST['u']));
	// ...or add if it's not and if it's not you.
	elseif ($user_info['id'] != $_REQUEST['u'])
		$user_info['buddies'][] = (int) $_REQUEST['u'];

	// Update the settings.
	updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

	// Redirect back to the profile
	redirectexit('action=profile;u=' . $_REQUEST['u']);
}

/**
 * Outputs each member name on its own line.
 * This function is used by javascript to find members matching the request.
 * Accessed by action=requestmembers.
 */
function action_requestmembers()
{
	global $user_info, $txt, $smcFunc;

	checkSession('get');

	$_REQUEST['search'] = $smcFunc['htmlspecialchars']($_REQUEST['search']) . '*';
	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search']));
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	if (function_exists('iconv'))
		header('Content-Type: text/plain; charset=UTF-8');

	$request = $smcFunc['db_query']('', '
		SELECT real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:search}' . (isset($_REQUEST['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'buddy_list' => $user_info['buddies'],
			'search' => $_REQUEST['search'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (function_exists('iconv'))
		{
			$utf8 = iconv($txt['lang_character_set'], 'UTF-8', $row['real_name']);
			if ($utf8)
				$row['real_name'] = $utf8;
		}

		$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		if (preg_match('~&#\d+;~', $row['real_name']) != 0)
			$row['real_name'] = preg_replace_callback('~&#(\d+);~', 'fixchar__callback', $row['real_name']);

		echo $row['real_name'], "\n";
	}
	$smcFunc['db_free_result']($request);

	obExit(false);
}