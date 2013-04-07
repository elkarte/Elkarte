<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
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
 * This file contains some useful functions for members and membergroups.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Delete one or more members.
 * Requires profile_remove_own or profile_remove_any permission for
 * respectively removing your own account or any account.
 * Non-admins cannot delete admins.
 * The function:
 *   - changes author of messages, topics and polls to guest authors.
 *   - removes all log entries concerning the deleted members, except the
 * error logs, ban logs and moderation logs.
 *   - removes these members' personal messages (only the inbox), avatars,
 * ban entries, theme settings, moderator positions, poll votes, and
 * karma votes.
 *   - updates member statistics afterwards.
 *
 * @param array $users
 * @param bool $check_not_admin = false
 */
function deleteMembers($users, $check_not_admin = false)
{
	global $modSettings, $user_info, $smcFunc;

	// Try give us a while to sort this out...
	@set_time_limit(600);

	// Try to get some more memory.
	setMemoryLimit('128M');

	// If it's not an array, make it so!
	if (!is_array($users))
		$users = array($users);
	else
		$users = array_unique($users);

	// Make sure there's no void user in here.
	$users = array_diff($users, array(0));

	// How many are they deleting?
	if (empty($users))
		return;
	elseif (count($users) == 1)
	{
		list ($user) = $users;

		if ($user == $user_info['id'])
			isAllowedTo('profile_remove_own');
		else
			isAllowedTo('profile_remove_any');
	}
	else
	{
		foreach ($users as $k => $v)
			$users[$k] = (int) $v;

		// Deleting more than one?  You can't have more than one account...
		isAllowedTo('profile_remove_any');
	}

	// Get their names for logging purposes.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:user_list})
		LIMIT ' . count($users),
		array(
			'user_list' => $users,
			'admin_group' => 1,
		)
	);
	$admins = array();
	$user_log_details = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['is_admin'])
			$admins[] = $row['id_member'];
		$user_log_details[$row['id_member']] = array($row['id_member'], $row['member_name']);
	}
	$smcFunc['db_free_result']($request);

	if (empty($user_log_details))
		return;

	// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themself.
	if (!empty($admins) && ($check_not_admin || (!allowedTo('admin_forum') && (count($users) != 1 || $users[0] != $user_info['id']))))
	{
		$users = array_diff($users, $admins);
		foreach ($admins as $id)
			unset($user_log_details[$id]);
	}

	// No one left?
	if (empty($users))
		return;

	// Log the action - regardless of who is deleting it.
	$log_changes = array();
	foreach ($user_log_details as $user)
	{
		$log_changes[] = array(
			'action' => 'delete_member',
			'log_type' => 'admin',
			'extra' => array(
				'member' => $user[0],
				'name' => $user[1],
				'member_acted' => $user_info['name'],
			),
		);

		// Remove any cached data if enabled.
		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('user_settings-' . $user[0], null, 60);
	}

	// Make these peoples' posts guest posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:guest_id}' . (!empty($modSettings['deleteMembersRemovesEmail']) ? ',
		poster_email = {string:blank_email}' : '') . '
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'blank_email' => '',
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}polls
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Make these peoples' posts guest first posts and last posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:guest_id}
		WHERE id_member_started IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:guest_id}
		WHERE id_member_updated IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_actions
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_banned
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_errors
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Delete the member.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}members
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete any drafts...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}user_drafts
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete the logs...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member IN ({array_int:users})',
		array(
			'log_type' => 2,
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_boards
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_comments
		WHERE id_recipient IN ({array_int:users})
			AND comment_type = {string:warntpl}',
		array(
			'users' => $users,
			'warntpl' => 'warntpl',
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma
		WHERE id_target IN ({array_int:users})
			OR id_executor IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_subscribed
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collapsed_categories
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Make their votes appear as guest votes - at least it keeps the totals right.
	// @todo Consider adding back in cookie protection.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_polls
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Delete personal messages.
	require_once(SUBSDIR . '/PersonalMessage.subs.php');
	deleteMessages(null, null, $users);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:guest_id}
		WHERE id_member_from IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// They no longer exist, so we don't know who it was sent to.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}pm_recipients
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete avatar.
	require_once(SUBSDIR . '/Attachments.subs.php');
	removeAttachments(array('id_member' => $users));

	// It's over, no more moderation for you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}moderators
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// If you don't exist we can't ban you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ban_items
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Remove individual theme settings.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// These users are nobody's buddy nomore.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, pm_ignore_list, buddy_list
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:pm_ignore_list}, pm_ignore_list) != 0 OR FIND_IN_SET({raw:buddy_list}, buddy_list) != 0',
		array(
			'pm_ignore_list' => implode(', pm_ignore_list) != 0 OR FIND_IN_SET(', $users),
			'buddy_list' => implode(', buddy_list) != 0 OR FIND_IN_SET(', $users),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				pm_ignore_list = {string:pm_ignore_list},
				buddy_list = {string:buddy_list}
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $row['id_member'],
				'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), $users)),
				'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), $users)),
			)
		);
	$smcFunc['db_free_result']($request);

	// Make sure no member's birthday is still sticking in the calendar...
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// Integration rocks!
	call_integration_hook('integrate_delete_members', array($users));

	updateStats('member');

	require_once(SOURCEDIR . '/Logging.php');
	logActions($log_changes);
}

/**
 * Registers a member to the forum.
 * Allows two types of interface: 'guest' and 'admin'. The first
 * includes hammering protection, the latter can perform the
 * registration silently.
 * The strings used in the options array are assumed to be escaped.
 * Allows to perform several checks on the input, e.g. reserved names.
 * The function will adjust member statistics.
 * If an error is detected will fatal error on all errors unless return_errors is true.
 *
 * @param array $regOptions
 * @param bool $return_errors - specify whether to return the errors
 * @return int, the ID of the newly created member
 */
function registerMember(&$regOptions, $return_errors = false)
{
	global $scripturl, $txt, $modSettings, $context;
	global $user_info, $options, $settings, $smcFunc;

	loadLanguage('Login');

	// We'll need some external functions.
	require_once(SUBSDIR . '/Auth.subs.php');
	require_once(SUBSDIR . '/Mail.subs.php');

	// Put any errors in here.
	$reg_errors = array();

	// Registration from the admin center, let them sweat a little more.
	if ($regOptions['interface'] == 'admin')
	{
		is_not_guest();
		isAllowedTo('moderate_forum');
	}
	// If you're an admin, you're special ;).
	elseif ($regOptions['interface'] == 'guest')
	{
		// You cannot register twice...
		if (empty($user_info['is_guest']))
			redirectexit();

		// Make sure they didn't just register with this session.
		if (!empty($_SESSION['just_registered']) && empty($modSettings['disableRegisterCheck']))
			fatal_lang_error('register_only_once', false);
	}

	// What method of authorization are we going to use?
	if (empty($regOptions['auth_method']) || !in_array($regOptions['auth_method'], array('password', 'openid')))
	{
		if (!empty($regOptions['openid']))
			$regOptions['auth_method'] = 'openid';
		else
			$regOptions['auth_method'] = 'password';
	}

	// Spaces and other odd characters are evil...
	$regOptions['username'] = preg_replace('~[\t\n\r\x0B\0\x{A0}]+~u', ' ', $regOptions['username']);

	// @todo Separate the sprintf?
	if (empty($regOptions['email']) || preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $regOptions['email']) === 0 || strlen($regOptions['email']) > 255)
		$reg_errors[] = array('done', sprintf($txt['valid_email_needed'], $smcFunc['htmlspecialchars']($regOptions['username'])));

	$username_validation_errors = validateUsername(0, $regOptions['username'], true, !empty($regOptions['check_reserved_name']));
	if (!empty($username_validation_errors))
		$reg_errors = array_merge($reg_errors, $username_validation_errors);

	// Generate a validation code if it's supposed to be emailed.
	$validation_code = '';
	if ($regOptions['require'] == 'activation')
		$validation_code = generateValidationCode();

	// If you haven't put in a password generate one.
	if ($regOptions['interface'] == 'admin' && $regOptions['password'] == '' && $regOptions['auth_method'] == 'password')
	{
		mt_srand(time() + 1277);
		$regOptions['password'] = generateValidationCode();
		$regOptions['password_check'] = $regOptions['password'];
	}
	// Does the first password match the second?
	elseif ($regOptions['password'] != $regOptions['password_check'] && $regOptions['auth_method'] == 'password')
		$reg_errors[] = array('lang', 'passwords_dont_match');

	// That's kind of easy to guess...
	if ($regOptions['password'] == '')
	{
		if ($regOptions['auth_method'] == 'password')
			$reg_errors[] = array('lang', 'no_password');
		else
			$regOptions['password'] = sha1(mt_rand());
	}

	// Now perform hard password validation as required.
	if (!empty($regOptions['check_password_strength']))
	{
		$passwordError = validatePassword($regOptions['password'], $regOptions['username'], array($regOptions['email']));

		// Password isn't legal?
		if ($passwordError != null)
			$reg_errors[] = array('lang', 'profile_error_password_' . $passwordError);
	}

	// If they are using an OpenID that hasn't been verified yet error out.
	// @todo Change this so they can register without having to attempt a login first
	if ($regOptions['auth_method'] == 'openid' && (empty($_SESSION['openid']['verified']) || $_SESSION['openid']['openid_uri'] != $regOptions['openid']))
		$reg_errors[] = array('lang', 'openid_not_verified');

	// You may not be allowed to register this email.
	if (!empty($regOptions['check_email_ban']))
		isBannedEmail($regOptions['email'], 'cannot_register', $txt['ban_register_prohibited']);

	// Check if the email address is in use.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE email_address = {string:email_address}
			OR email_address = {string:username}
		LIMIT 1',
		array(
			'email_address' => $regOptions['email'],
			'username' => $regOptions['username'],
		)
	);
	// @todo Separate the sprintf?
	if ($smcFunc['db_num_rows']($request) != 0)
		$reg_errors[] = array('lang', 'email_in_use', false, array(htmlspecialchars($regOptions['email'])));
	$smcFunc['db_free_result']($request);

	// If we found any errors we need to do something about it right away!
	foreach ($reg_errors as $key => $error)
	{
		/* Note for each error:
			0 = 'lang' if it's an index, 'done' if it's clear text.
			1 = The text/index.
			2 = Whether to log.
			3 = sprintf data if necessary. */
		if ($error[0] == 'lang')
			loadLanguage('Errors');
		$message = $error[0] == 'lang' ? (empty($error[3]) ? $txt[$error[1]] : vsprintf($txt[$error[1]], $error[3])) : $error[1];

		// What to do, what to do, what to do.
		if ($return_errors)
		{
			if (!empty($error[2]))
				log_error($message, $error[2]);
			$reg_errors[$key] = $message;
		}
		else
			fatal_error($message, empty($error[2]) ? false : $error[2]);
	}

	// If there's any errors left return them at once!
	if (!empty($reg_errors))
		return $reg_errors;

	$reservedVars = array(
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_layers',
		'theme_templates',
		'theme_url',
	);

	// Can't change reserved vars.
	if (isset($regOptions['theme_vars']) && count(array_intersect(array_keys($regOptions['theme_vars']), $reservedVars)) != 0)
		fatal_lang_error('no_theme');

	// Some of these might be overwritten. (the lower ones that are in the arrays below.)
	$regOptions['register_vars'] = array(
		'member_name' => $regOptions['username'],
		'email_address' => $regOptions['email'],
		'passwd' => sha1(strtolower($regOptions['username']) . $regOptions['password']),
		'password_salt' => substr(md5(mt_rand()), 0, 4) ,
		'posts' => 0,
		'date_registered' => time(),
		'member_ip' => $regOptions['interface'] == 'admin' ? '127.0.0.1' : $user_info['ip'],
		'member_ip2' => $regOptions['interface'] == 'admin' ? '127.0.0.1' : $_SERVER['BAN_CHECK_IP'],
		'validation_code' => $validation_code,
		'real_name' => $regOptions['username'],
		'personal_text' => $modSettings['default_personal_text'],
		'pm_email_notify' => 1,
		'id_theme' => 0,
		'id_post_group' => 4,
		'lngfile' => '',
		'buddy_list' => '',
		'pm_ignore_list' => '',
		'message_labels' => '',
		'website_title' => '',
		'website_url' => '',
		'location' => '',
		'time_format' => '',
		'signature' => '',
		'avatar' => '',
		'usertitle' => '',
		'secret_question' => '',
		'secret_answer' => '',
		'additional_groups' => '',
		'ignore_boards' => '',
		'smiley_set' => '',
		'openid_uri' => (!empty($regOptions['openid']) ? $regOptions['openid'] : ''),
	);

	// Setup the activation status on this new account so it is correct - firstly is it an under age account?
	if ($regOptions['require'] == 'coppa')
	{
		$regOptions['register_vars']['is_activated'] = 5;
		// @todo This should be changed.  To what should be it be changed??
		$regOptions['register_vars']['validation_code'] = '';
	}
	// Maybe it can be activated right away?
	elseif ($regOptions['require'] == 'nothing')
		$regOptions['register_vars']['is_activated'] = 1;
	// Maybe it must be activated by email?
	elseif ($regOptions['require'] == 'activation')
		$regOptions['register_vars']['is_activated'] = 0;
	// Otherwise it must be awaiting approval!
	else
		$regOptions['register_vars']['is_activated'] = 3;

	if (isset($regOptions['memberGroup']))
	{
		// Make sure the id_group will be valid, if this is an administator.
		$regOptions['register_vars']['id_group'] = $regOptions['memberGroup'] == 1 && !allowedTo('admin_forum') ? 0 : $regOptions['memberGroup'];

		// Check if this group is assignable.
		$unassignableGroups = array(-1, 3);
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE min_posts != {int:min_posts}' . (allowedTo('admin_forum') ? '' : '
				OR group_type = {int:is_protected}'),
			array(
				'min_posts' => -1,
				'is_protected' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$unassignableGroups[] = $row['id_group'];
		$smcFunc['db_free_result']($request);

		if (in_array($regOptions['register_vars']['id_group'], $unassignableGroups))
			$regOptions['register_vars']['id_group'] = 0;
	}

	// Integrate optional member settings to be set.
	if (!empty($regOptions['extra_register_vars']))
		foreach ($regOptions['extra_register_vars'] as $var => $value)
			$regOptions['register_vars'][$var] = $value;

	// Integrate optional user theme options to be set.
	$theme_vars = array();
	if (!empty($regOptions['theme_vars']))
		foreach ($regOptions['theme_vars'] as $var => $value)
			$theme_vars[$var] = $value;

	// Right, now let's prepare for insertion.
	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
		'new_pm', 'pm_prefs', 'gender', 'hide_email', 'show_online', 'pm_email_notify', 'karma_good', 'karma_bad',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
	);
	$knownFloats = array(
		'time_offset',
	);

	// Call an optional function to validate the users' input.
	call_integration_hook('integrate_register', array(&$regOptions, &$theme_vars, &$knownInts, &$knownFloats));

	$column_names = array();
	$values = array();
	foreach ($regOptions['register_vars'] as $var => $val)
	{
		$type = 'string';
		if (in_array($var, $knownInts))
			$type = 'int';
		elseif (in_array($var, $knownFloats))
			$type = 'float';
		elseif ($var == 'birthdate')
			$type = 'date';

		$column_names[$var] = $type;
		$values[$var] = $val;
	}

	// Register them into the database.
	$smcFunc['db_insert']('',
		'{db_prefix}members',
		$column_names,
		$values,
		array('id_member')
	);
	$memberID = $smcFunc['db_insert_id']('{db_prefix}members', 'id_member');

	// Update the number of members and latest member's info - and pass the name, but remove the 's.
	if ($regOptions['register_vars']['is_activated'] == 1)
		updateStats('member', $memberID, $regOptions['register_vars']['real_name']);
	else
		updateStats('member');

	// Theme variables too?
	if (!empty($theme_vars))
	{
		$inserts = array();
		foreach ($theme_vars as $var => $val)
			$inserts[] = array($memberID, $var, $val);
		$smcFunc['db_insert']('insert',
			'{db_prefix}themes',
			array('id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			$inserts,
			array('id_member', 'variable')
		);
	}

	// If it's enabled, increase the registrations for today.
	trackStats(array('registers' => '+'));

	// Administrative registrations are a bit different...
	if ($regOptions['interface'] == 'admin')
	{
		if ($regOptions['require'] == 'activation')
			$email_message = 'admin_register_activate';
		elseif (!empty($regOptions['send_welcome_email']))
			$email_message = 'admin_register_immediate';

		if (isset($email_message))
		{
			$replacements = array(
				'REALNAME' => $regOptions['register_vars']['real_name'],
				'USERNAME' => $regOptions['username'],
				'PASSWORD' => $regOptions['password'],
				'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $memberID . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $memberID,
				'ACTIVATIONCODE' => $validation_code,
			);

			$emaildata = loadEmailTemplate($email_message, $replacements);

			sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}

		// All admins are finished here.
		return $memberID;
	}

	// Can post straight away - welcome them to your fantastic community...
	if ($regOptions['require'] == 'nothing')
	{
		if (!empty($regOptions['send_welcome_email']))
		{
			$replacements = array(
				'REALNAME' => $regOptions['register_vars']['real_name'],
				'USERNAME' => $regOptions['username'],
				'PASSWORD' => $regOptions['password'],
				'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
				'OPENID' => !empty($regOptions['openid']) ? $regOptions['openid'] : '',
			);
			$emaildata = loadEmailTemplate('register_' . ($regOptions['auth_method'] == 'openid' ? 'openid_' : '') . 'immediate', $replacements);
			sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}

		// Send admin their notification.
		require_once(SUBSDIR . '/Post.subs.php');
		adminNotify('standard', $memberID, $regOptions['username']);
	}
	// Need to activate their account - or fall under COPPA.
	elseif ($regOptions['require'] == 'activation' || $regOptions['require'] == 'coppa')
	{
		$replacements = array(
			'REALNAME' => $regOptions['register_vars']['real_name'],
			'USERNAME' => $regOptions['username'],
			'PASSWORD' => $regOptions['password'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
			'OPENID' => !empty($regOptions['openid']) ? $regOptions['openid'] : '',
		);

		if ($regOptions['require'] == 'activation')
			$replacements += array(
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $memberID . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $memberID,
				'ACTIVATIONCODE' => $validation_code,
			);
		else
			$replacements += array(
				'COPPALINK' => $scripturl . '?action=coppa;u=' . $memberID,
			);

		$emaildata = loadEmailTemplate('register_' . ($regOptions['auth_method'] == 'openid' ? 'openid_' : '') . ($regOptions['require'] == 'activation' ? 'activate' : 'coppa'), $replacements);

		sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
	}
	// Must be awaiting approval.
	else
	{
		$replacements = array(
			'REALNAME' => $regOptions['register_vars']['real_name'],
			'USERNAME' => $regOptions['username'],
			'PASSWORD' => $regOptions['password'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
			'OPENID' => !empty($regOptions['openid']) ? $regOptions['openid'] : '',
		);

		$emaildata = loadEmailTemplate('register_' . ($regOptions['auth_method'] == 'openid' ? 'openid_' : '') . 'pending', $replacements);

		sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

		// Admin gets informed here...
		require_once(SUBSDIR . '/Post.subs.php');
		adminNotify('approval', $memberID, $regOptions['username']);
	}

	// Okay, they're for sure registered... make sure the session is aware of this for security. (Just married :P!)
	$_SESSION['just_registered'] = 1;

	return $memberID;
}

/**
 * Check if a name is in the reserved words list.
 * (name, current member id, name/username?.)
 * - checks if name is a reserved name or username.
 * - if is_name is false, the name is assumed to be a username.
 * - the id_member variable is used to ignore duplicate matches with the
 * current member.
 *
 * @param string $name
 * @param int $current_ID_MEMBER
 * @param bool $is_name
 * @param bool $fatal
 */
function isReservedName($name, $current_ID_MEMBER = 0, $is_name = true, $fatal = true)
{
	global $user_info, $modSettings, $smcFunc, $context;

	$name = preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $name);
	$checkName = $smcFunc['strtolower']($name);

	// Administrators are never restricted ;).
	if (!allowedTo('moderate_forum') && ((!empty($modSettings['reserveName']) && $is_name) || !empty($modSettings['reserveUser']) && !$is_name))
	{
		$reservedNames = explode("\n", $modSettings['reserveNames']);
		// Case sensitive check?
		$checkMe = empty($modSettings['reserveCase']) ? $checkName : $name;

		// Check each name in the list...
		foreach ($reservedNames as $reserved)
		{
			if ($reserved == '')
				continue;

			// The admin might've used entities too, level the playing field.
			$reservedCheck = preg_replace('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $reserved);

			// Case sensitive name?
			if (empty($modSettings['reserveCase']))
				$reservedCheck = $smcFunc['strtolower']($reservedCheck);

			// If it's not just entire word, check for it in there somewhere...
			if ($checkMe == $reservedCheck || ($smcFunc['strpos']($checkMe, $reservedCheck) !== false && empty($modSettings['reserveWord'])))
				if ($fatal)
					fatal_lang_error('username_reserved', 'password', array($reserved));
				else
					return true;
		}

		$censor_name = $name;
		if (censorText($censor_name) != $name)
			if ($fatal)
				fatal_lang_error('name_censored', 'password', array($name));
			else
				return true;
	}

	// Characters we just shouldn't allow, regardless.
	foreach (array('*') as $char)
		if (strpos($checkName, $char) !== false)
			if ($fatal)
				fatal_lang_error('username_reserved', 'password', array($char));
			else
				return true;

	// Get rid of any SQL parts of the reserved name...
	$checkName = strtr($name, array('_' => '\\_', '%' => '\\%'));

	// Make sure they don't want someone else's name.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . (empty($current_ID_MEMBER) ? '' : 'id_member != {int:current_member}
			AND ') . '(real_name LIKE {string:check_name} OR member_name LIKE {string:check_name})
		LIMIT 1',
		array(
			'current_member' => $current_ID_MEMBER,
			'check_name' => $checkName,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$smcFunc['db_free_result']($request);
		return true;
	}

	// Does name case insensitive match a member group name?
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE group_name LIKE {string:check_name}
		LIMIT 1',
		array(
			'check_name' => $checkName,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$smcFunc['db_free_result']($request);
		return true;
	}

	// Okay, they passed.
	return false;
}

// Get a list of groups that have a given permission (on a given board).
/**
 * Retrieves a list of membergroups that are allowed to do the given
 * permission. (on the given board)
 * If board_id is not null, a board permission is assumed.
 * The function takes different permission settings into account.
 *
 * @param string $permission
 * @param int $board_id = null
 * @return an array containing an array for the allowed membergroup ID's
 * and an array for the denied membergroup ID's.
 */
function groupsAllowedTo($permission, $board_id = null)
{
	global $modSettings, $board_info, $smcFunc;

	// Admins are allowed to do anything.
	$member_groups = array(
		'allowed' => array(1),
		'denied' => array(),
	);

	// Assume we're dealing with regular permissions (like profile_view_own).
	if ($board_id === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}',
			array(
				'permission' => $permission,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		$smcFunc['db_free_result']($request);
	}

	// Otherwise it's time to look at the board.
	else
	{
		// First get the profile of the given board.
		if (isset($board_info['id']) && $board_info['id'] == $board_id)
			$profile_id = $board_info['profile'];
		elseif ($board_id !== 0)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_profile
				FROM {db_prefix}boards
				WHERE id_board = {int:id_board}
				LIMIT 1',
				array(
					'id_board' => $board_id,
				)
			);
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('no_board');
			list ($profile_id) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
			$profile_id = 1;

		$request = $smcFunc['db_query']('', '
			SELECT bp.id_group, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
			WHERE bp.permission = {string:permission}
				AND bp.id_profile = {int:profile_id}',
			array(
				'profile_id' => $profile_id,
				'permission' => $permission,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		$smcFunc['db_free_result']($request);
	}

	// Denied is never allowed.
	$member_groups['allowed'] = array_diff($member_groups['allowed'], $member_groups['denied']);

	return $member_groups;
}

/**
 * Retrieves a list of members that have a given permission
 * (on a given board).
 * If board_id is not null, a board permission is assumed.
 * Takes different permission settings into account.
 * Takes possible moderators (on board 'board_id') into account.
 *
 * @param string $permission
 * @param int $board_id = null
 * @return an array containing member ID's.
 */
function membersAllowedTo($permission, $board_id = null)
{
	global $smcFunc;

	$member_groups = groupsAllowedTo($permission, $board_id);

	$include_moderators = in_array(3, $member_groups['allowed']) && $board_id !== null;
	$member_groups['allowed'] = array_diff($member_groups['allowed'], array(3));

	$exclude_moderators = in_array(3, $member_groups['denied']) && $board_id !== null;
	$member_groups['denied'] = array_diff($member_groups['denied'], array(3));

	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem' . ($include_moderators || $exclude_moderators ? '
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member AND mods.id_board = {int:board_id})' : '') . '
		WHERE (' . ($include_moderators ? 'mods.id_member IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_allowed}) OR FIND_IN_SET({raw:member_group_allowed_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_allowed}))' . (empty($member_groups['denied']) ? '' : '
			AND NOT (' . ($exclude_moderators ? 'mods.id_member IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_denied}) OR FIND_IN_SET({raw:member_group_denied_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_denied}))'),
		array(
			'member_groups_allowed' => $member_groups['allowed'],
			'member_groups_denied' => $member_groups['denied'],
			'board_id' => $board_id,
			'member_group_allowed_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['allowed']),
			'member_group_denied_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['denied']),
		)
	);
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	return $members;
}

/**
 * This function is used to reassociate members with relevant posts.
 * Reattribute guest posts to a specified member.
 * Does not check for any permissions.
 * If add_to_post_count is set, the member's post count is increased.
 *
 * @param int $memID
 * @param string $email = false
 * @param string $membername = false
 * @param bool $post_count = false
 * @return nothing
 */
function reattributePosts($memID, $email = false, $membername = false, $post_count = false)
{
	global $smcFunc;

	// Firstly, if email and username aren't passed find out the members email address and name.
	if ($email === false && $membername === false)
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$result = getBasicMemberData($memID);
		$email = $result['email_address'];
		$membername = $result['member_name'];
	}

	// If they want the post count restored then we need to do some research.
	if ($post_count)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND b.count_posts = {int:count_posts})
			WHERE m.id_member = {int:guest_id}
				AND m.approved = {int:is_approved}
				AND m.icon != {string:recycled_icon}' . (empty($email) ? '' : '
				AND m.poster_email = {string:email_address}') . (empty($membername) ? '' : '
				AND m.poster_name = {string:member_name}'),
			array(
				'count_posts' => 0,
				'guest_id' => 0,
				'email_address' => $email,
				'member_name' => $membername,
				'is_approved' => 1,
				'recycled_icon' => 'recycled',
			)
		);
		list ($messageCount) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		updateMemberData($memID, array('posts' => 'posts + ' . $messageCount));
	}

	$query_parts = array();
	if (!empty($email))
		$query_parts[] = 'poster_email = {string:email_address}';
	if (!empty($membername))
		$query_parts[] = 'poster_name = {string:member_name}';
	$query = implode(' AND ', $query_parts);

	// Finally, update the posts themselves!
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:memID}
		WHERE ' . $query,
		array(
			'memID' => $memID,
			'email_address' => $email,
			'member_name' => $membername,
		)
	);

	// ...and the topics too!
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics as t, {db_prefix}messages as m
		SET t.id_member_started = {int:memID}
		WHERE m.id_member = {int:memID}
			AND t.id_first_msg = m.id_msg',
		array(
			'memID' => $memID,
		)
	);

	// Allow mods with their own post tables to reattribute posts as well :)
 	call_integration_hook('integrate_reattribute_posts', array($memID, $email, $membername, $post_count));
}

/**
 * Callback for createList().
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 * @param $where
 * @param $where_params
 * @param $get_duplicates
 */
function list_getMembers($start, $items_per_page, $sort, $where, $where_params = array(), $get_duplicates = false)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.member_ip, mem.member_ip2, mem.last_login,
			mem.posts, mem.is_activated, mem.date_registered, mem.id_group, mem.additional_groups, mg.group_name
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE ' . ($where == '1' ? '1=1' : $where) . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array_merge($where_params, array(
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		))
	);

	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = $row;
	$smcFunc['db_free_result']($request);

	// If we want duplicates pass the members array off.
	if ($get_duplicates)
		populateDuplicateMembers($members);

	return $members;
}

/**
 * Callback for createList().
 *
 * @param $where
 * @param $where_params
 */
function list_getNumMembers($where, $where_params = array())
{
	global $smcFunc, $modSettings;

	// We know how many members there are in total.
	if (empty($where) || $where == '1=1')
		$num_members = $modSettings['totalMembers'];

	// The database knows the amount when there are extra conditions.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}members AS mem
			WHERE ' . $where,
			array_merge($where_params, array(
			))
		);
		list ($num_members) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	return $num_members;
}

/**
 * Find potential duplicate registation members based on the same IP address
 *
 * @param $members
 */
function populateDuplicateMembers(&$members)
{
	global $smcFunc;

	// This will hold all the ip addresses.
	$ips = array();
	foreach ($members as $key => $member)
	{
		// Create the duplicate_members element.
		$members[$key]['duplicate_members'] = array();

		// Store the IPs.
		if (!empty($member['member_ip']))
			$ips[] = $member['member_ip'];
		if (!empty($member['member_ip2']))
			$ips[] = $member['member_ip2'];
	}

	$ips = array_unique($ips);

	if (empty($ips))
		return false;

	// Fetch all members with this IP address, we'll filter out the current ones in a sec.
	$request = $smcFunc['db_query']('', '
		SELECT
			id_member, member_name, email_address, member_ip, member_ip2, is_activated
		FROM {db_prefix}members
		WHERE member_ip IN ({array_string:ips})
			OR member_ip2 IN ({array_string:ips})',
		array(
			'ips' => $ips,
		)
	);
	$duplicate_members = array();
	$duplicate_ids = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		//$duplicate_ids[] = $row['id_member'];

		$member_context = array(
			'id' => $row['id_member'],
			'name' => $row['member_name'],
			'email' => $row['email_address'],
			'is_banned' => $row['is_activated'] > 10,
			'ip' => $row['member_ip'],
			'ip2' => $row['member_ip2'],
		);

		if (in_array($row['member_ip'], $ips))
			$duplicate_members[$row['member_ip']][] = $member_context;
		if ($row['member_ip'] != $row['member_ip2'] && in_array($row['member_ip2'], $ips))
			$duplicate_members[$row['member_ip2']][] = $member_context;
	}
	$smcFunc['db_free_result']($request);

	// Also try to get a list of messages using these ips.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.poster_ip, mem.id_member, mem.member_name, mem.email_address, mem.is_activated
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_member != 0
			' . (!empty($duplicate_ids) ? 'AND m.id_member NOT IN ({array_int:duplicate_ids})' : '') . '
			AND m.poster_ip IN ({array_string:ips})',
		array(
			'duplicate_ids' => $duplicate_ids,
			'ips' => $ips,
		)
	);

	$had_ips = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't collect lots of the same.
		if (isset($had_ips[$row['poster_ip']]) && in_array($row['id_member'], $had_ips[$row['poster_ip']]))
			continue;
		$had_ips[$row['poster_ip']][] = $row['id_member'];

		$duplicate_members[$row['poster_ip']][] = array(
			'id' => $row['id_member'],
			'name' => $row['member_name'],
			'email' => $row['email_address'],
			'is_banned' => $row['is_activated'] > 10,
			'ip' => $row['poster_ip'],
			'ip2' => $row['poster_ip'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Now we have all the duplicate members, stick them with their respective member in the list.
	if (!empty($duplicate_members))
		foreach ($members as $key => $member)
		{
			if (isset($duplicate_members[$member['member_ip']]))
				$members[$key]['duplicate_members'] = $duplicate_members[$member['member_ip']];
			if ($member['member_ip'] != $member['member_ip2'] && isset($duplicate_members[$member['member_ip2']]))
				$members[$key]['duplicate_members'] = array_merge($member['duplicate_members'], $duplicate_members[$member['member_ip2']]);

			// Check we don't have lots of the same member.
			$member_track = array($member['id_member']);
			foreach ($members[$key]['duplicate_members'] as $duplicate_id_member => $duplicate_member)
			{
				if (in_array($duplicate_member['id'], $member_track))
				{
					unset($members[$key]['duplicate_members'][$duplicate_id_member]);
					continue;
				}

				$member_track[] = $duplicate_member['id'];
			}
		}
}

/**
 * Generate a random validation code.
 * @todo Err. Whatcha doin' here.
 *
 * @return type
 */
function generateValidationCode()
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('get_random_number', '
		SELECT RAND()',
		array(
		)
	);

	list ($dbRand) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return substr(preg_replace('/\W/', '', sha1(microtime() . mt_rand() . $dbRand . $modSettings['rand_seed'])), 0, 10);
}

/**
 * Find out if there is another admin than the given user.
 *
 * @param int $memberID ID of the member, to compare with.
 */
function isAnotherAdmin($memberID)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
			AND id_member != {int:selected_member}
		LIMIT 1',
		array(
			'admin_group' => 1,
			'selected_member' => $memberID,
		)
	);
	list ($another) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $another;
}

/**
 * Retrieve administrators of the site.
 * The function returns basic information: name, language file.
 * It is used in personal messages reporting.
 *
 * @param int $id_admin = 0 if requested, only data about a specific admin is retrieved
 */
function admins($id_admin = 0)
{
	global $smcFunc;

	// Now let's get out and loop through the admins.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, lngfile
		FROM {db_prefix}members
		WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
			' . (empty($id_admin) ? '' : 'AND id_member = {int:specific_admin}') . '
		ORDER BY real_name, lngfile',
		array(
			'admin_group' => 1,
			'specific_admin' => isset($id_admin) ? (int) $id_admin : 0,
		)
	);

	$admins = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$admins[$row['id_member']] = array($row['real_name'], $row['lngfile']);
	$smcFunc['db_free_result']($request);

	return $admins;
}

/**
 * Load some basic member infos
 * 
 * @param mixed $member_ids an array of member IDs or a single ID
 * @param array $options an array of possible little alternatives, can be:
 *                - 'add_guest' (set or not) to add a guest user to the returned array
 *                - 'sort' (string) a column to sort the results
 *                - 'identification' (set or not) includes secret_answer/question and openid_uri
 * @return array
 */
function getBasicMemberData($member_ids, $options = array())
{
	global $smcFunc, $txt;

	$members = array();

	if (empty($member_ids))
		return false;

	if (!is_array($member_ids))
	{
		$single = true;
		$member_ids = array($member_ids);
	}

	if (!empty($options['add_guest']))
	{
		$single = false;
		// This is a guest...
		$members[0] = array(
			'id_member' => 0,
			'member_name' => '',
			'real_name' => $txt['guest_title'],
			'email_address' => '',
		);
	}

	// Get some additional member info...
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, member_ip, email_address, hide_email, id_group, lngfile,
			posts, last_login' . (isset($options['identification']) ? ', secret_answer, secret_question, openid_uri' : '') . '
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})
		LIMIT {int:limit}' . (isset($options['sort']) ? '
		ORDER BY {string:sort}' : ''),
		array(
			'member_list' => $member_ids,
			'limit' => count($member_ids),
			'sort' => isset($options['sort']) ? $options['sort'] : '',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[$row['id_member']] = $row;
	$smcFunc['db_free_result']($request);

	if (!empty($single))
		return $members[$row['id_member']];
	else
		return $members;
}