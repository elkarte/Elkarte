<?php

/**
 * This file contains some useful functions for members and membergroups.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.7
 *
 */

/**
 * Delete one or more members.
 *
 * What it does:
 *
 * - Requires profile_remove_own or profile_remove_any permission for
 * respectively removing your own account or any account.
 * - Non-admins cannot delete admins.
 *
 * What id does
 * - Changes author of messages, topics and polls to guest authors.
 * - Removes all log entries concerning the deleted members, except the
 * error logs, ban logs and moderation logs.
 * - Removes these members' personal messages (only the inbox)
 * - Removes avatars, ban entries, theme settings, moderator positions, poll votes,
 * likes, mentions, notifications
 * - Removes custom field data associated with them
 * - Updates member statistics afterwards.
 *
 * @package Members
 * @param int[]|int $users
 * @param bool $check_not_admin = false
 * @throws Elk_Exception
 */
function deleteMembers($users, $check_not_admin = false)
{
	global $modSettings, $user_info;

	$db = database();

	// Try give us a while to sort this out...
	detectServer()->setTimeLimit(600);

	// Try to get some more memory.
	detectServer()->setMemoryLimit('128M');

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
	$request = $db->query('', '
		SELECT id_member, member_name, email_address, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:user_list})
		LIMIT ' . count($users),
		array(
			'user_list' => $users,
			'admin_group' => 1,
		)
	);
	$admins = array();
	$emails = array();
	$user_log_details = array();
	while ($row = $db->fetch_assoc($request))
	{
		if ($row['is_admin'])
			$admins[] = $row['id_member'];
		$user_log_details[$row['id_member']] = array($row['id_member'], $row['member_name']);
		$emails[] = $row['email_address'];
	}
	$db->free_result($request);

	if (empty($user_log_details))
		return;

	// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themselves.
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
		Cache::instance()->remove('user_settings-' . $user[0]);
	}

	// Make these peoples' posts guest posts.
	$db->query('', '
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
	$db->query('', '
		UPDATE {db_prefix}polls
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Make these peoples' posts guest first posts and last posts.
	$db->query('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:guest_id}
		WHERE id_member_started IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);
	$db->query('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:guest_id}
		WHERE id_member_updated IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$db->query('', '
		UPDATE {db_prefix}log_actions
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$db->query('', '
		UPDATE {db_prefix}log_banned
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$db->query('', '
		UPDATE {db_prefix}log_errors
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Delete the member.
	$db->query('', '
		DELETE FROM {db_prefix}members
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete any likes...
	$db->query('', '
		DELETE FROM {db_prefix}message_likes
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete any custom field data...
	$db->query('', '
		DELETE FROM {db_prefix}custom_fields_data
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete any post by email keys...
	$db->query('', '
		DELETE FROM {db_prefix}postby_emails
		WHERE email_to IN ({array_string:emails})',
		array(
			'emails' => $emails,
		)
	);

	// Delete the logs...
	$db->query('', '
		DELETE FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member IN ({array_int:users})',
		array(
			'log_type' => 2,
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_boards
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_comments
		WHERE id_recipient IN ({array_int:users})
			AND comment_type = {string:warntpl}',
		array(
			'users' => $users,
			'warntpl' => 'warntpl',
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_karma
		WHERE id_target IN ({array_int:users})
			OR id_executor IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_subscribed
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}collapsed_categories
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Make their votes appear as guest votes - at least it keeps the totals right.
	// @todo Consider adding back in cookie protection.
	$db->query('', '
		UPDATE {db_prefix}log_polls
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Remove the mentions
	$db->query('', '
		DELETE FROM {db_prefix}log_mentions
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	// And null all those that were added by him
	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET id_member_from = {int:zero}
		WHERE id_member_from IN ({array_int:users})',
		array(
			'zero' => 0,
			'users' => $users,
		)
	);

	// Delete personal messages.
	require_once(SUBSDIR . '/PersonalMessage.subs.php');
	deleteMessages(null, null, $users);

	$db->query('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:guest_id}
		WHERE id_member_from IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// They no longer exist, so we don't know who it was sent to.
	$db->query('', '
		DELETE FROM {db_prefix}pm_recipients
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete avatar.
	require_once(SUBSDIR . '/ManageAttachments.subs.php');
	removeAttachments(array('id_member' => $users));

	// It's over, no more moderation for you.
	$db->query('', '
		DELETE FROM {db_prefix}moderators
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// If you don't exist we can't ban you.
	$db->query('', '
		DELETE FROM {db_prefix}ban_items
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Remove individual theme settings.
	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// These users are nobody's buddy nomore.
	$db->fetchQueryCallback('
		SELECT id_member, pm_ignore_list, buddy_list
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:pm_ignore_list}, pm_ignore_list) != 0 OR FIND_IN_SET({raw:buddy_list}, buddy_list) != 0',
		array(
			'pm_ignore_list' => implode(', pm_ignore_list) != 0 OR FIND_IN_SET(', $users),
			'buddy_list' => implode(', buddy_list) != 0 OR FIND_IN_SET(', $users),
		),
		function ($row) use ($users)
		{
			updateMemberData($row['id_member'], array(
				'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), $users)),
				'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), $users))
			));
		}
	);

	// Make sure no member's birthday is still sticking in the calendar...
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// Integration rocks!
	call_integration_hook('integrate_delete_members', array($users));

	updateMemberStats();

	logActions($log_changes);
}

/**
 * Registers a member to the forum.
 *
 * What it does:
 *
 * - Allows two types of interface: 'guest' and 'admin'. The first
 * - includes hammering protection, the latter can perform the registration silently.
 * - The strings used in the options array are assumed to be escaped.
 * - Allows to perform several checks on the input, e.g. reserved names.
 * - The function will adjust member statistics.
 * - If an error is detected will fatal error on all errors unless return_errors is true.
 *
 * @package Members
 * @uses Auth.subs.php
 * @uses Mail.subs.php
 *
 * @param mixed[] $regOptions
 * @param string  $ErrorContext
 *
 * @return int the ID of the newly created member
 * @throws Elk_Exception no_theme
 */
function registerMember(&$regOptions, $ErrorContext = 'register')
{
	global $scripturl, $txt;

	$db = database();

	loadLanguage('Login');

	// We'll need some external functions.
	require_once(SUBSDIR . '/Auth.subs.php');
	require_once(SUBSDIR . '/Mail.subs.php');

	// Put any errors in here.
	$reg_errors = ElkArte\Errors\ErrorContext::context($ErrorContext, 0);

	// What method of authorization are we going to use?
	if (empty($regOptions['auth_method']) || !in_array($regOptions['auth_method'], array('password', 'openid')))
	{
		if (!empty($regOptions['openid']))
			$regOptions['auth_method'] = 'openid';
		else
			$regOptions['auth_method'] = 'password';
	}

	// Spaces and other odd characters are evil...
	$regOptions['username'] = trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $regOptions['username']));

	// Valid emails only
	if (!Data_Validator::is_valid($regOptions, array('email' => 'valid_email|required|max_length[255]'), array('email' => 'trim')))
		$reg_errors->addError('bad_email');

	validateUsername(0, $regOptions['username'], $ErrorContext, !empty($regOptions['check_reserved_name']));

	// Generate a validation code if it's supposed to be emailed.
	$validation_code = generateValidationCode(14);

	// Does the first password match the second?
	if ($regOptions['password'] != $regOptions['password_check'] && $regOptions['auth_method'] == 'password')
		$reg_errors->addError('passwords_dont_match');

	// That's kind of easy to guess...
	if ($regOptions['password'] == '')
	{
		if ($regOptions['auth_method'] == 'password')
			$reg_errors->addError('no_password');
		else
			$regOptions['password'] = sha1(mt_rand());
	}

	// Now perform hard password validation as required.
	if (!empty($regOptions['check_password_strength']) && $regOptions['password'] != '')
	{
		$passwordError = validatePassword($regOptions['password'], $regOptions['username'], array($regOptions['email']));

		// Password isn't legal?
		if ($passwordError !== null)
			$reg_errors->addError('profile_error_password_' . $passwordError);
	}

	// @todo move to controller
	// You may not be allowed to register this email.
	if (!empty($regOptions['check_email_ban']))
		isBannedEmail($regOptions['email'], 'cannot_register', $txt['ban_register_prohibited']);

	// Check if the email address is in use.
	if (userByEmail($regOptions['email'], $regOptions['username']))
	{
		$reg_errors->addError(array('email_in_use', array(htmlspecialchars($regOptions['email'], ENT_COMPAT, 'UTF-8'))));
	}

	// Perhaps someone else wants to check this user
	call_integration_hook('integrate_register_check', array(&$regOptions, &$reg_errors));

	// If there's any errors left return them at once!
	if ($reg_errors->hasErrors())
		return false;

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
		throw new Elk_Exception('no_theme');

	$tokenizer = new Token_Hash();

	// @since 1.0.7 - This is necessary because validateLoginPassword
	// uses a pass-by-ref and would convert to hash $regOptions['password']
	// making it impossible to send the reminder email and even integrate
	// the registration
	$password = $regOptions['password'];

	// Some of these might be overwritten. (the lower ones that are in the arrays below.)
	$regOptions['register_vars'] = array(
		'member_name' => $regOptions['username'],
		'email_address' => $regOptions['email'],
		'passwd' => validateLoginPassword($password, '', $regOptions['username'], true),
		'password_salt' => $tokenizer->generate_hash(16),
		'posts' => 0,
		'date_registered' => !empty($regOptions['time']) ? $regOptions['time'] : time(),
		'member_ip' => $regOptions['interface'] == 'admin' ? '127.0.0.1' : $regOptions['ip'],
		'member_ip2' => $regOptions['interface'] == 'admin' ? '127.0.0.1' : $regOptions['ip2'],
		'validation_code' => substr(hash('sha256', $validation_code), 0, 10),
		'real_name' => !empty($regOptions['real_name']) ? $regOptions['real_name'] : $regOptions['username'],
		'pm_email_notify' => 1,
		'id_theme' => 0,
		'id_post_group' => 4,
		'lngfile' => '',
		'buddy_list' => '',
		'pm_ignore_list' => '',
		'message_labels' => '',
		'website_title' => '',
		'website_url' => '',
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
		'notify_announcements' => (!empty($regOptions['notify_announcements']) ? 1 : 0),
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
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// Make sure the id_group will be valid, if this is an administrator.
		$regOptions['register_vars']['id_group'] = $regOptions['memberGroup'] == 1 && !allowedTo('admin_forum') ? 0 : $regOptions['memberGroup'];

		// Check if this group is assignable.
		$unassignableGroups = getUnassignableGroups(allowedTo('admin_forum'));

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
		'date_registered', 'posts', 'id_group', 'last_login', 'personal_messages', 'unread_messages', 'notifications',
		'new_pm', 'pm_prefs', 'hide_email', 'show_online', 'pm_email_notify', 'karma_good', 'karma_bad',
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
	$db->insert('',
		'{db_prefix}members',
		$column_names,
		$values,
		array('id_member')
	);
	$memberID = $db->insert_id('{db_prefix}members', 'id_member');

	// Update the number of members and latest member's info - and pass the name, but remove the 's.
	if ($regOptions['register_vars']['is_activated'] == 1)
		updateMemberStats($memberID, $regOptions['register_vars']['real_name']);
	else
		updateMemberStats();

	// @todo there's got to be a method that does this
	// Theme variables too?
	if (!empty($theme_vars))
	{
		$inserts = array();
		foreach ($theme_vars as $var => $val)
			$inserts[] = array($memberID, $var, $val);
		$db->insert('insert',
			'{db_prefix}themes',
			array('id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			$inserts,
			array('id_member', 'variable')
		);
	}

	// If it's enabled, increase the registrations for today.
	trackStats(array('registers' => '+'));

	// @todo emails should be sent from the controller, with a new method.

	// Don't worry about what the emails might want to replace. Just give them everything and let them sort it out.
	$replacements = array(
		'REALNAME' => $regOptions['register_vars']['real_name'],
		'USERNAME' => $regOptions['username'],
		'PASSWORD' => $regOptions['password'],
		'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
		'ACTIVATIONLINK' => $scripturl . '?action=register;sa=activate;u=' . $memberID . ';code=' . $validation_code,
		'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=register;sa=activate;u=' . $memberID,
		'ACTIVATIONCODE' => $validation_code,
		'OPENID' => !empty($regOptions['openid']) ? $regOptions['openid'] : '',
		'COPPALINK' => $scripturl . '?action=register;sa=coppa;u=' . $memberID,
	);

	// Administrative registrations are a bit different...
	if ($regOptions['interface'] == 'admin')
	{
		if ($regOptions['require'] == 'activation')
			$email_message = 'admin_register_activate';
		elseif (!empty($regOptions['send_welcome_email']))
			$email_message = 'admin_register_immediate';

		if (isset($email_message))
		{
			$emaildata = loadEmailTemplate($email_message, $replacements);

			sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}
	}
	else
	{
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
				$mark_down = new Html_2_Md(str_replace("\n", '<br>', $emaildata['body']));
				$emaildata['body'] = $mark_down->get_markdown();

				sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
			}

			// Send admin their notification.
			require_once(SUBSDIR . '/Notification.subs.php');
			sendAdminNotifications('standard', $memberID, $regOptions['username']);
		}
		// Need to activate their account - or fall under COPPA.
		elseif ($regOptions['require'] == 'activation' || $regOptions['require'] == 'coppa')
		{

			$emaildata = loadEmailTemplate('register_' . ($regOptions['auth_method'] == 'openid' ? 'openid_' : '') . ($regOptions['require'] == 'activation' ? 'activate' : 'coppa'), $replacements);
			$mark_down = new Html_2_Md(str_replace("\n", '<br>', $emaildata['body']));
			$emaildata['body'] = $mark_down->get_markdown();

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
			$mark_down = new Html_2_Md(str_replace("\n", '<br>', $emaildata['body']));
			$emaildata['body'] = $mark_down->get_markdown();

			sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

			// Admin gets informed here...
			require_once(SUBSDIR . '/Notification.subs.php');
			sendAdminNotifications('approval', $memberID, $regOptions['username']);
		}

		// Okay, they're for sure registered... make sure the session is aware of this for security. (Just married :P!)
		$_SESSION['just_registered'] = 1;
	}

	// If they are for sure registered, let other people to know about it
	call_integration_hook('integrate_register_after', array($regOptions, $memberID));

	return $memberID;
}

/**
 * Check if a name is in the reserved words list. (name, current member id, name/username?.)
 *
 * - checks if name is a reserved name or username.
 * - if is_name is false, the name is assumed to be a username.
 * - the id_member variable is used to ignore duplicate matches with the current member.
 *
 * @package Members
 *
 * @param string $name
 * @param int    $current_ID_MEMBER
 * @param bool   $is_name
 * @param bool   $fatal
 *
 * @return bool
 * @throws Elk_Exception username_reserved, name_censored
 */
function isReservedName($name, $current_ID_MEMBER = 0, $is_name = true, $fatal = true)
{
	global $modSettings;

	$db = database();

	$name = preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $name);
	$checkName = Util::strtolower($name);

	// Administrators are never restricted ;).
	if (!allowedTo('admin_forum') && ((!empty($modSettings['reserveName']) && $is_name) || !empty($modSettings['reserveUser']) && !$is_name))
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
			$reservedCheck = preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $reserved);

			// Case sensitive name?
			if (empty($modSettings['reserveCase']))
				$reservedCheck = Util::strtolower($reservedCheck);

			// If it's not just entire word, check for it in there somewhere...
			if ($checkMe == $reservedCheck || (Util::strpos($checkMe, $reservedCheck) !== false && empty($modSettings['reserveWord'])))
				if ($fatal)
					throw new Elk_Exception('username_reserved', 'password', array($reserved));
				else
					return true;
		}

		$censor_name = $name;
		if (censor($censor_name) != $name)
			if ($fatal)
				throw new Elk_Exception('name_censored', 'password', array($name));
			else
				return true;
	}

	// Characters we just shouldn't allow, regardless.
	foreach (array('*') as $char)
		if (strpos($checkName, $char) !== false)
			if ($fatal)
				throw new Elk_Exception('username_reserved', 'password', array($char));
			else
				return true;

	// Get rid of any SQL parts of the reserved name...
	$checkName = strtr($name, array('_' => '\\_', '%' => '\\%'));

	// Make sure they don't want someone else's name.
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . (empty($current_ID_MEMBER) ? '' : 'id_member != {int:current_member}
			AND ') . '({raw:real_name} LIKE {string:check_name} OR {raw:member_name} LIKE {string:check_name})
		LIMIT 1',
		array(
			'real_name' => defined('DB_CASE_SENSITIVE') ? 'LOWER(real_name)' : 'real_name',
			'member_name' => defined('DB_CASE_SENSITIVE') ? 'LOWER(member_name)' : 'member_name',
			'current_member' => $current_ID_MEMBER,
			'check_name' => $checkName,
		)
	);
	if ($db->num_rows($request) > 0)
	{
		$db->free_result($request);
		return true;
	}

	// Does name case insensitive match a member group name?
	$request = $db->query('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE {raw:group_name} LIKE {string:check_name}
		LIMIT 1',
		array(
			'group_name' => defined('DB_CASE_SENSITIVE') ? 'LOWER(group_name)' : 'group_name',
			'check_name' => $checkName,
		)
	);
	if ($db->num_rows($request) > 0)
	{
		$db->free_result($request);
		return true;
	}

	// Okay, they passed.
	return false;
}

/**
 * Retrieves a list of membergroups that are allowed to do the given
 * permission. (on the given board)
 *
 * - If board_id is not null, a board permission is assumed.
 * - The function takes different permission settings into account.
 *
 * @package Members
 *
 * @param string       $permission
 * @param integer|null $board_id = null
 *
 * @return array containing an array for the allowed membergroup ID's
 * and an array for the denied membergroup ID's.
 * @throws Elk_Exception no_board
 */
function groupsAllowedTo($permission, $board_id = null)
{
	global $board_info;

	$db = database();

	// Admins are allowed to do anything.
	$member_groups = array(
		'allowed' => array(1),
		'denied' => array(),
	);

	// Assume we're dealing with regular permissions (like profile_view_own).
	if ($board_id === null)
	{
		$request = $db->query('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}',
			array(
				'permission' => $permission,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		$db->free_result($request);
	}

	// Otherwise it's time to look at the board.
	else
	{
		// First get the profile of the given board.
		if (isset($board_info['id']) && $board_info['id'] == $board_id)
			$profile_id = $board_info['profile'];
		elseif ($board_id !== 0)
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$board_data = fetchBoardsInfo(array('boards' => $board_id), array('selects' => 'permissions'));

			if (empty($board_data))
				throw new Elk_Exception('no_board');
			$profile_id = $board_data[$board_id]['id_profile'];
		}
		else
			$profile_id = 1;

		$request = $db->query('', '
			SELECT bp.id_group, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
			WHERE bp.permission = {string:permission}
				AND bp.id_profile = {int:profile_id}',
			array(
				'profile_id' => $profile_id,
				'permission' => $permission,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		$db->free_result($request);
	}

	// Denied is never allowed.
	$member_groups['allowed'] = array_diff($member_groups['allowed'], $member_groups['denied']);

	return $member_groups;
}

/**
 * Retrieves a list of members that have a given permission (on a given board).
 *
 * - If board_id is not null, a board permission is assumed.
 * - Takes different permission settings into account.
 * - Takes possible moderators (on board 'board_id') into account.
 *
 * @package Members
 * @param string $permission
 * @param integer|null $board_id = null
 *
 * @return int[] an array containing member ID's.
 * @throws Elk_Exception
 */
function membersAllowedTo($permission, $board_id = null)
{
	$db = database();

	$member_groups = groupsAllowedTo($permission, $board_id);

	$include_moderators = in_array(3, $member_groups['allowed']) && $board_id !== null;
	$member_groups['allowed'] = array_diff($member_groups['allowed'], array(3));

	$exclude_moderators = in_array(3, $member_groups['denied']) && $board_id !== null;
	$member_groups['denied'] = array_diff($member_groups['denied'], array(3));

	return $db->fetchQueryCallback('
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
		),
		function ($row)
		{
			return $row['id_member'];
		}
	);
}

/**
 * This function is used to re-associate members with relevant posts.
 *
 * - Re-attribute guest posts to a specified member.
 * - Does not check for any permissions.
 * - If add_to_post_count is set, the member's post count is increased.
 *
 * @package Members
 *
 * @param int $memID
 * @param bool|false|string $email = false
 * @param bool|false|string $membername = false
 * @param bool $post_count = false
 */
function reattributePosts($memID, $email = false, $membername = false, $post_count = false)
{
	$db = database();

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
		$request = $db->query('', '
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
		list ($messageCount) = $db->fetch_row($request);
		$db->free_result($request);

		updateMemberData($memID, array('posts' => 'posts + ' . $messageCount));
	}

	$query_parts = array();
	if (!empty($email))
		$query_parts[] = 'poster_email = {string:email_address}';
	if (!empty($membername))
		$query_parts[] = 'poster_name = {string:member_name}';
	$query = implode(' AND ', $query_parts);

	// Finally, update the posts themselves!
	$db->query('', '
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
	$db->query('', '
		UPDATE {db_prefix}topics as t, {db_prefix}messages as m
		SET t.id_member_started = {int:memID}
		WHERE m.id_member = {int:memID}
			AND t.id_first_msg = m.id_msg',
		array(
			'memID' => $memID,
		)
	);

	// Allow mods with their own post tables to re-attribute posts as well :)
	call_integration_hook('integrate_reattribute_posts', array($memID, $email, $membername, $post_count));
}

/**
 * Gets a listing of members, Callback for createList().
 *
 * @package Members
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $where
 * @param mixed[] $where_params
 * @param boolean $get_duplicates
 */
function list_getMembers($start, $items_per_page, $sort, $where, $where_params = array(), $get_duplicates = false)
{
	$db = database();

	$members = $db->fetchQuery('
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

	// If we want duplicates pass the members array off.
	if ($get_duplicates)
		populateDuplicateMembers($members);

	return $members;
}

/**
 * Gets the number of members, Callback for createList().
 *
 * @package Members
 * @param string $where
 * @param mixed[] $where_params
 */
function list_getNumMembers($where, $where_params = array())
{
	global $modSettings;

	$db = database();

	// We know how many members there are in total.
	if (empty($where) || $where == '1=1')
		$num_members = $modSettings['totalMembers'];

	// The database knows the amount when there are extra conditions.
	else
	{
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}members AS mem
			WHERE ' . $where,
			array_merge($where_params, array(
			))
		);
		list ($num_members) = $db->fetch_row($request);
		$db->free_result($request);
	}

	return $num_members;
}

/**
 * Find potential duplicate registration members based on the same IP address
 *
 * @package Members
 * @param mixed[] $members
 */
function populateDuplicateMembers(&$members)
{
	$db = database();

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
	$potential_dupes = membersByIP($ips, 'exact', true);

	$duplicate_members = array();
	$duplicate_ids = array();
	foreach ($potential_dupes as $row)
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

	// Also try to get a list of messages using these ips.
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
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
	$db->free_result($request);

	// Now we have all the duplicate members, stick them with their respective member in the list.
	if (!empty($duplicate_members))
	{
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
}

/**
 * Find members with a given IP (first, second, exact or "relaxed")
 *
 * @package Members
 * @param string|string[] $ip1 An IP or an array of IPs
 * @param string $match (optional, default 'exact') if the match should be exact
 *                of "relaxed" (using LIKE)
 * @param bool $ip2 (optional, default false) If the query should check IP2 as well
 */
function membersByIP($ip1, $match = 'exact', $ip2 = false)
{
	$db = database();

	$ip_params = array('ips' => array());
	$ip_query = array();
	foreach (array($ip1, $ip2) as $id => $ip)
	{
		if ($ip === false)
			continue;

		if ($match === 'exact')
			$ip_params['ips'] = array_merge($ip_params['ips'], (array) $ip);
		else
		{
			$ip = (array) $ip;
			foreach ($ip as $id_var => $ip_var)
			{
				$ip_var = str_replace('*', '%', $ip_var);
				$ip_query[] = strpos($ip_var, '%') === false ? '= {string:ip_address_' . $id . '_' . $id_var . '}' : 'LIKE {string:ip_address_' . $id . '_' . $id_var . '}';
				$ip_params['ip_address_' . $id . '_' . $id_var] = $ip_var;
			}
		}
	}

	if ($match === 'exact')
	{
		$where = 'member_ip IN ({array_string:ips})';
		if ($ip2 !== false)
			$where .= '
			OR member_ip2 IN ({array_string:ips})';
	}
	else
	{
		$where = 'member_ip ' . implode(' OR member_ip', $ip_query);
		if ($ip2 !== false)
			$where .= '
			OR member_ip2 ' . implode(' OR member_ip', $ip_query);
	}

	return $db->fetchQuery('
		SELECT
			id_member, member_name, email_address, member_ip, member_ip2, is_activated
		FROM {db_prefix}members
		WHERE ' . $where,
		$ip_params
	);
}

/**
 * Find out if there is another admin than the given user.
 *
 * @package Members
 * @param int $memberID ID of the member, to compare with.
 */
function isAnotherAdmin($memberID)
{
	$db = database();

	$request = $db->query('', '
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
	list ($another) = $db->fetch_row($request);
	$db->free_result($request);

	return $another;
}

/**
 * This function retrieves a list of member ids based on a set of conditions
 *
 * @package Members
 * @param mixed[]|string $query see prepareMembersByQuery
 * @param mixed[] $query_params see prepareMembersByQuery
 * @param bool $details if true returns additional member details (name, email, ip, etc.)
 *             false will only return an array of member id's that match the conditions
 * @param bool $only_active see prepareMembersByQuery
 */
function membersBy($query, $query_params, $details = false, $only_active = true)
{
	$db = database();

	$query_where = prepareMembersByQuery($query, $query_params, $only_active);

	// Lets see who we can find that meets the built up conditions
	$members = array();
	$request = $db->query('', '
		SELECT id_member' . ($details ? ', member_name, real_name, email_address, member_ip, date_registered, last_login,
				hide_email, posts, is_activated, real_name' : '') . '
		FROM {db_prefix}members
		WHERE ' . $query_where . (isset($query_params['start']) ? '
		LIMIT {int:start}, {int:limit}' : '') . (!empty($query_params['order']) ? '
		ORDER BY {raw:order}' : ''),
		$query_params
	);

	// Return all the details for each member found
	if ($details)
	{
		while ($row = $db->fetch_assoc($request))
			$members[$row['id_member']] = $row;
	}
	// Or just a int[] of found member id's
	else
	{
		while ($row = $db->fetch_assoc($request))
			$members[] = $row['id_member'];
	}
	$db->free_result($request);

	return $members;
}

/**
 * Counts the number of members based on conditions
 *
 * @package Members
 * @param string[]|string $query see prepareMembersByQuery
 * @param mixed[] $query_params see prepareMembersByQuery
 * @param boolean $only_active see prepareMembersByQuery
 */
function countMembersBy($query, $query_params, $only_active = true)
{
	$db = database();

	$query_where = prepareMembersByQuery($query, $query_params, $only_active);

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE ' . $query_where,
		$query_params
	);

	list ($num_members) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_members;
}

/**
 * Builds the WHERE clause for the functions countMembersBy and membersBy
 *
 * @package Members
 * @param mixed[]|string $query can be an array of "type" of conditions,
 *             or a string used as raw query
 *             or a string that represents one of the built-in conditions
 *             like member_names, not_in_group, etc
 * @param mixed[] $query_params is an array containing the parameters passed to the query
 *             'start' and 'limit' used in LIMIT
 *             'order' used raw in ORDER BY
 *             others passed as query params
 * @param bool $only_active only fetch active members
 */
function prepareMembersByQuery($query, &$query_params, $only_active = true)
{
	$allowed_conditions = array(
		'member_ids'   => 'id_member IN ({array_int:member_ids})',
		'member_names' => function (&$members)
		{
			$mem_query = array();

			foreach ($members['member_names'] as $key => $param)
			{
				$mem_query[] = (defined('DB_CASE_SENSITIVE') ? 'LOWER(real_name)' : 'real_name') . ' LIKE {string:member_names_' . $key . '}';
				$members['member_names_' . $key] = defined('DB_CASE_SENSITIVE') ? strtolower($param) : $param;
			}
			return implode("\n\t\t\tOR ", $mem_query);
		},
		'not_in_group'     => '(id_group != {int:not_in_group} AND FIND_IN_SET({int:not_in_group}, additional_groups) = 0)',
		'in_group'         => '(id_group = {int:in_group} OR FIND_IN_SET({int:in_group}, additional_groups) != 0)',
		'in_group_primary' => 'id_group = {int:in_group_primary}',
		'in_post_group'    => 'id_post_group = {int:in_post_group}',
		'in_group_no_add'  => '(id_group = {int:in_group_no_add} AND FIND_IN_SET({int:in_group_no_add}, additional_groups) = 0)',
	);

	// Are there multiple parts to this query
	if (is_array($query))
	{
		$query_parts = array('or' => array(), 'and' => array());
		foreach ($query as $type => $query_conditions)
		{
			if (is_array($query_conditions))
			{
				foreach ($query_conditions as $condition => $query_condition)
				{
					if ($query_condition == 'member_names')
						$query_parts[$condition === 'or' ? 'or' : 'and'][] = $allowed_conditions[$query_condition]($query_params);
					else
						$query_parts[$condition === 'or' ? 'or' : 'and'][] = isset($allowed_conditions[$query_condition]) ? $allowed_conditions[$query_condition] : $query_condition;
				}
			}
			elseif ($query_conditions == 'member_names')
			{
				$query_parts['and'][] = $allowed_conditions[$query_conditions]($query_params);
			}
			else
			{
				$query_parts['and'][] = isset($allowed_conditions[$query_conditions]) ? $allowed_conditions[$query_conditions] : $query_conditions;
			}
		}

		if (!empty($query_parts['or']))
			$query_parts['and'][] = implode("\n\t\t\tOR ", $query_parts['or']);

		$query_where = implode("\n\t\t\tAND ", $query_parts['and']);
	}
	// Is it one of our predefined querys like member_ids, member_names, etc
	elseif (isset($allowed_conditions[$query]))
	{
		if ($query == 'member_names')
			$query_where = $allowed_conditions[$query]($query_params);
		else
			$query_where = $allowed_conditions[$query];
	}
	// Something else, be careful ;)
	else
		$query_where = $query;

	// Lazy loading, our favorite
	if (empty($query_where))
		return false;

	// Only want active members
	if ($only_active)
	{
		$query_where .= '
			AND is_activated = {int:is_activated}';
		$query_params['is_activated'] = 1;
	}

	return $query_where;
}

/**
 * Retrieve administrators of the site.
 *
 * - The function returns basic information: name, language file.
 * - It is used in personal messages reporting.
 *
 * @package Members
 * @param int $id_admin = 0 if requested, only data about a specific admin is retrieved
 */
function admins($id_admin = 0)
{
	$db = database();

	// Now let's get out and loop through the admins.
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$admins[$row['id_member']] = array($row['real_name'], $row['lngfile']);
	$db->free_result($request);

	return $admins;
}

/**
 * Get the last known id_member
 * @return int
 */
function maxMemberID()
{
	$db = database();

	$request = $db->query('', '
		SELECT MAX(id_member)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($max_id) = $db->fetch_row($request);
	$db->free_result($request);

	return $max_id;
}

/**
 * Load some basic member information
 *
 * @package Members
 * @param int[]|int $member_ids an array of member IDs or a single ID
 * @param mixed[] $options an array of possible little alternatives, can be:
 * - 'add_guest' (bool) to add a guest user to the returned array
 * - 'limit' int if set overrides the default query limit
 * - 'sort' (string) a column to sort the results
 * - 'moderation' (bool) includes member_ip, id_group, additional_groups, last_login
 * - 'authentication' (bool) includes secret_answer, secret_question, openid_uri,
 *    is_activated, validation_code, passwd_flood, password_salt
 * - 'preferences' (bool) includes lngfile, mod_prefs, notify_types, signature
 * @return array
 */
function getBasicMemberData($member_ids, $options = array())
{
	global $txt, $language;

	$db = database();

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
	$request = $db->query('', '
		SELECT id_member, member_name, real_name, email_address, hide_email, posts, id_theme' . (!empty($options['moderation']) ? ',
		member_ip, id_group, additional_groups, last_login, id_post_group' : '') . (!empty($options['authentication']) ? ',
		secret_answer, secret_question, openid_uri, is_activated, validation_code, passwd_flood, password_salt' : '') . (!empty($options['preferences']) ? ',
		lngfile, mod_prefs, notify_types, signature' : '') . '
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})
		' . (isset($options['sort']) ? '
		ORDER BY {raw:sort}' : '') . '
		LIMIT {int:limit}',
		array(
			'member_list' => $member_ids,
			'limit' => isset($options['limit']) ? $options['limit'] : count($member_ids),
			'sort' => isset($options['sort']) ? $options['sort'] : '',
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		if (empty($row['lngfile']))
			$row['lngfile'] = $language;

		if (!empty($single))
			$members = $row;
		else
			$members[$row['id_member']] = $row;
	}
	$db->free_result($request);

	return $members;
}

/**
 * Counts all inactive members
 *
 * @package Members
 * @return array $inactive_members
 */
function countInactiveMembers()
{
	$db = database();

	$inactive_members = array();

	$request = $db->query('', '
		SELECT COUNT(*) AS total_members, is_activated
		FROM {db_prefix}members
		WHERE is_activated != {int:is_activated}
		GROUP BY is_activated',
		array(
			'is_activated' => 1,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$inactive_members[$row['is_activated']] = $row['total_members'];
	$db->free_result($request);

	return $inactive_members;
}

/**
 * Get the member's id and group
 *
 * @package Members
 * @param string $name
 * @param bool $flexible if true searches for both real_name and member_name (default false)
 * @return integer
 */
function getMemberByName($name, $flexible = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_member, id_group
		FROM {db_prefix}members
		WHERE {raw:real_name} LIKE {string:name}' . ($flexible ? '
			OR {raw:member_name} LIKE {string:name}' : '') . '
		LIMIT 1',
		array(
			'name' => Util::strtolower($name),
			'real_name' => defined('DB_CASE_SENSITIVE') ? 'LOWER(real_name)' : 'real_name',
			'member_name' => defined('DB_CASE_SENSITIVE') ? 'LOWER(member_name)' : 'member_name',
		)
	);
	if ($db->num_rows($request) == 0)
		return false;
	$member = $db->fetch_assoc($request);
	$db->free_result($request);

	return $member;
}

/**
 * Finds a member from the database using supplied string as real_name
 *
 * - Optionally will only search/find the member in a buddy list
 *
 * @package Members
 * @param string $search string to search real_name for like finds
 * @param int[]|null $buddies
 */
function getMember($search, $buddies = array())
{
	$db = database();

	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => array(),
		),
	);
	// Find the member.
	$xml_data['items']['children'] = $db->fetchQueryCallback('
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE {raw:real_name} LIKE {string:search}' . (!empty($buddies) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN ({array_int:activation_status})
		ORDER BY LENGTH(real_name), real_name
		LIMIT {int:limit}',
		array(
			'real_name' => defined('DB_CASE_SENSITIVE') ? 'LOWER(real_name)' : 'real_name',
			'buddy_list' => $buddies,
			'search' => Util::strtolower($search),
			'activation_status' => array(1, 12),
			'limit' => Util::strlen($search) <= 2 ? 100 : 200,
		),
		function ($row)
		{
			$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

			return array(
				'attributes' => array(
					'id' => $row['id_member'],
				),
				'value' => $row['real_name'],
			);
		}
	);

	return $xml_data;
}

/**
 * Retrieves MemberData based on conditions
 *
 * @package Members
 * @param mixed[] $conditions associative array holding the conditions for the WHERE clause of the query.
 * Possible keys:
 * - activated_status (boolean) must be present
 * - time_before (integer)
 * - members (array of integers)
 * - member_greater (integer) a member id, it will be used to filter only members with id_member greater than this
 * - group_list (array) array of group IDs
 * - notify_announcements (integer)
 * - order_by (string)
 * - limit (int)
 * @return array
 */
function retrieveMemberData($conditions)
{
	global $modSettings, $language;

	// We badly need this
	assert(isset($conditions['activated_status']));

	$db = database();

	$available_conditions = array(
		'time_before' => '
				AND date_registered < {int:time_before}',
		'members' => '
				AND id_member IN ({array_int:members})',
		'member_greater' => '
				AND id_member > {int:member_greater}',
		'member_greater_equal' => '
				AND id_member >= {int:member_greater_equal}',
		'member_lesser' => '
				AND id_member < {int:member_lesser}',
		'member_lesser_equal' => '
				AND id_member <= {int:member_lesser_equal}',
		'group_list' => '
				AND (id_group IN ({array_int:group_list}) OR id_post_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:additional_group_list}, additional_groups) != 0)',
		'notify_announcements' => '
				AND notify_announcements = {int:notify_announcements}'
	);

	$query_cond = array();
	foreach ($conditions as $key => $dummy)
		if (isset($available_conditions[$key]))
			$query_cond[] = $available_conditions[$key];

	if (isset($conditions['group_list']))
		$conditions['additional_group_list'] = implode(', additional_groups) != 0 OR FIND_IN_SET(', $conditions['group_list']);

	$data = array();

	if (!isset($conditions['order_by']))
		$conditions['order_by'] = 'lngfile';

	$limit = (isset($conditions['limit'])) ? '
		LIMIT {int:limit}' : '';

	// Get information on each of the members, things that are important to us, like email address...
	$request = $db->query('', '
		SELECT id_member, member_name, real_name, email_address, validation_code, lngfile
		FROM {db_prefix}members
		WHERE is_activated = {int:activated_status}' . implode('', $query_cond) . '
		ORDER BY {raw:order_by}' . $limit,
		$conditions
	);

	$data['member_count'] = $db->num_rows($request);

	if ($data['member_count'] == 0)
		return $data;

	// Fill the info array.
	while ($row = $db->fetch_assoc($request))
	{
		$data['members'][] = $row['id_member'];
		$data['member_info'][] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'email' => $row['email_address'],
			'language' => empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'],
			'code' => $row['validation_code']
		);
	}
	$db->free_result($request);

	return $data;
}

/**
 * Activate members
 *
 * @package Members
 * @param mixed[] $conditions associative array holding the conditions for the WHERE clause of the query.
 * Possible keys:
 * - activated_status (boolean) must be present
 * - time_before (integer)
 * - members (array of integers)
 */
function approveMembers($conditions)
{
	$db = database();

	// This shall be present
	assert(isset($conditions['activated_status']));

	$available_conditions = array(
		'time_before' => '
				AND date_registered < {int:time_before}',
		'members' => '
				AND id_member IN ({array_int:members})',
	);

	// @todo maybe an hook here?
	$query_cond = array();
	$query = false;
	foreach ($conditions as $key => $dummy)
	{
		if (isset($available_conditions[$key]))
		{
			if ($key === 'time_before')
				$query = true;
			$query_cond[] = $available_conditions[$key];
		}
	}

	if ($query)
	{
		$data = retrieveMemberData($conditions);
		$members_id = array();
		foreach ($data['member_info'] as $member)
			$members_id[] = $member['username'];
	}
	else
	{
		$members_id = $conditions['members'];
	}

	$conditions['is_activated'] = $conditions['activated_status'] >= 10 ? 11 : 1;
	$conditions['blank_string'] = '';

	// Approve/activate this member.
	$db->query('', '
		UPDATE {db_prefix}members
		SET validation_code = {string:blank_string}, is_activated = {int:is_activated}
		WHERE is_activated = {int:activated_status}' . implode('', $query_cond),
		$conditions
	);

	// Let the integration know that they've been activated!
	foreach ($members_id as $member_id)
		call_integration_hook('integrate_activate', array($member_id, $conditions['activated_status'], $conditions['is_activated']));

	return $conditions['is_activated'];
}

/**
 * Set these members for activation
 *
 * @package Members
 * @param mixed[] $conditions associative array holding the conditions for the  WHERE clause of the query.
 * Possible keys:
 * - selected_member (integer) must be present
 * - activated_status (boolean) must be present
 * - validation_code (string) must be present
 * - members (array of integers)
 * - time_before (integer)
 */
function enforceReactivation($conditions)
{
	$db = database();

	// We need all of these
	assert(isset($conditions['activated_status']));
	assert(isset($conditions['selected_member']));
	assert(isset($conditions['validation_code']));

	$conditions['validation_code'] = substr(hash('sha256', $conditions['validation_code']), 0, 10);

	$available_conditions = array(
		'time_before' => '
				AND date_registered < {int:time_before}',
		'members' => '
				AND id_member IN ({array_int:members})',
	);

	$query_cond = array();
	foreach ($conditions as $key => $dummy)
	{
		if (isset($available_conditions[$key]))
		{
			$query_cond[] = $available_conditions[$key];
		}
	}

	$conditions['not_activated'] = 0;

	$db->query('', '
		UPDATE {db_prefix}members
		SET validation_code = {string:validation_code}, is_activated = {int:not_activated}
		WHERE is_activated = {int:activated_status}
			' . implode('', $query_cond) . '
			AND id_member = {int:selected_member}',
		$conditions
	);
}

/**
 * Count members of a given group
 *
 * @package Members
 * @param int $id_group
 * @return int
 */
function countMembersInGroup($id_group = 0)
{
	$db = database();

	// Determine the number of ungrouped members.
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE id_group = {int:group}',
		array(
			'group' => $id_group,
		)
	);
	list ($num_members) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_members;
}

/**
 * Get the total amount of members online.
 *
 * @package Members
 * @param string[] $conditions
 * @return int
 */
function countMembersOnline($conditions)
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)' . (!empty($conditions) ? '
		WHERE ' . implode(' AND ', $conditions) : ''),
		array(
		)
	);
	list ($totalMembers) = $db->fetch_row($request);
	$db->free_result($request);

	return $totalMembers;
}

/**
 * Look for people online, provided they don't mind if you see they are.
 *
 * @package Members
 * @param string[] $conditions
 * @param string $sort_method
 * @param string $sort_direction
 * @param int $start
 * @return array
 */
function onlineMembers($conditions, $sort_method, $sort_direction, $start)
{
	global $modSettings;

	$db = database();

	return $db->fetchQuery('
		SELECT
			lo.log_time, lo.id_member, lo.url, lo.ip, mem.real_name,
			lo.session, mg.online_color, COALESCE(mem.show_online, 1) AS show_online,
			lo.id_spider
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_member} THEN mem.id_post_group ELSE mem.id_group END)' . (!empty($conditions) ? '
		WHERE ' . implode(' AND ', $conditions) : '') . '
		ORDER BY {raw:sort_method} {raw:sort_direction}
		LIMIT {int:offset}, {int:limit}',
		array(
			'regular_member' => 0,
			'sort_method' => $sort_method,
			'sort_direction' => $sort_direction == 'up' ? 'ASC' : 'DESC',
			'offset' => $start,
			'limit' => $modSettings['defaultMaxMembers'],
		)
	);
}

/**
 * Check if the OpenID URI is already registered for an existing member
 *
 * @package Members
 * @param string $url
 * @return array
 */
function memberExists($url)
{
	$db = database();

	$request = $db->query('', '
		SELECT mem.id_member, mem.member_name
		FROM {db_prefix}members AS mem
		WHERE mem.openid_uri = {string:openid_uri}',
		array(
			'openid_uri' => $url,
		)
	);
	$member = $db->fetch_assoc($request);
	$db->free_result($request);

	return $member;
}

/**
 * Find the most recent members
 *
 * @package Members
 * @param int $limit
 */
function recentMembers($limit)
{
	$db = database();

	// Find the most recent members.
	return $db->fetchQuery('
		SELECT id_member, member_name, real_name, date_registered, last_login
		FROM {db_prefix}members
		ORDER BY id_member DESC
		LIMIT {int:limit}',
		array(
			'limit' => $limit,
		)
	);
}

/**
 * Assign membergroups to members.
 *
 * @package Members
 * @param int $member
 * @param int $primary_group
 * @param int[] $additional_groups
 */
function assignGroupsToMember($member, $primary_group, $additional_groups)
{
	updateMemberData($member, array('id_group' => $primary_group, 'additional_groups' => implode(',', $additional_groups)));
}

/**
 * Get a list of members from a membergroups request.
 *
 * @package Members
 * @param int[] $groups
 * @param string $where
 * @param boolean $change_groups = false
 * @return mixed
 */
function getConcernedMembers($groups, $where, $change_groups = false)
{
	global $modSettings, $language;

	$db = database();

		// Get the details of all the members concerned...
	$request = $db->query('', '
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address, mem.id_group AS primary_group,
			mem.additional_groups AS additional_groups, mem.lngfile, mem.member_name, mem.notify_types,
			mg.hidden, mg.group_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . $where . '
			AND lgr.id_request IN ({array_int:request_list})
		ORDER BY mem.lngfile',
		array(
			'request_list' => $groups,
		)
	);

	$email_details = array();
	$group_changes = array();

	while ($row = $db->fetch_assoc($request))
	{
		$row['lngfile'] = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

		// If we are approving work out what their new group is.
		if ($change_groups)
		{
			// For people with more than one request at once.
			if (isset($group_changes[$row['id_member']]))
			{
				$row['additional_groups'] = $group_changes[$row['id_member']]['add'];
				$row['primary_group'] = $group_changes[$row['id_member']]['primary'];
			}
			else
				$row['additional_groups'] = explode(',', $row['additional_groups']);
				// Don't have it already?
			if ($row['primary_group'] == $row['id_group'] || in_array($row['id_group'], $row['additional_groups']))
				continue;
				// Should it become their primary?
			if ($row['primary_group'] == 0 && $row['hidden'] == 0)
				$row['primary_group'] = $row['id_group'];
			else
				$row['additional_groups'][] = $row['id_group'];

			// Add them to the group master list.
			$group_changes[$row['id_member']] = array(
				'primary' => $row['primary_group'],
				'add' => $row['additional_groups'],
			);
		}

		// Add required information to email them.
		if ($row['notify_types'] != 4)
			$email_details[] = array(
				'rid' => $row['id_request'],
				'member_id' => $row['id_member'],
				'member_name' => $row['member_name'],
				'group_id' => $row['id_group'],
				'group_name' => $row['group_name'],
				'email' => $row['email_address'],
				'language' => $row['lngfile'],
			);
	}
	$db->free_result($request);

	$output = array(
		'email_details' => $email_details,
		'group_changes' => $group_changes
	);

	return $output;
}

/**
 * Determine if the current user ($user_info) can contact another user ($who)
 *
 * @package Members
 * @param int $who The id of the user to contact
 */
function canContact($who)
{
	global $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT receive_from, buddy_list, pm_ignore_list
		FROM {db_prefix}members
		WHERE id_member = {int:member}',
		array(
			'member' => $who,
		)
	);
	list ($receive_from, $buddies, $ignore) = $db->fetch_row($request);
	$db->free_result($request);

	$buddy_list = array_map('intval', explode(',', $buddies));
	$ignore_list = array_map('intval', explode(',', $ignore));

	// 0 = all members
	if ($receive_from == 0)
		return true;
	// 1 = all except ignore
	elseif ($receive_from == 1)
		return !(!empty($ignore_list) && in_array($user_info['id'], $ignore_list));
	// 2 = buddies and admin
	elseif ($receive_from == 2)
		return ($user_info['is_admin'] || (!empty($buddy_list) && in_array($user_info['id'], $buddy_list)));
	// 3 = admin only
	else
		return (bool) $user_info['is_admin'];
}

/**
 * This function updates the latest member, the total membercount, and the
 * number of unapproved members.
 *
 * - It also only counts approved members when approval is on,
 * but is much more efficient with it off.
 *
 * @package Members
 * @param integer|null $id_member = null If not an integer reload from the database
 * @param string|null $real_name = null
 */
function updateMemberStats($id_member = null, $real_name = null)
{
	global $modSettings;

	$db = database();

	$changes = array(
		'memberlist_updated' => time(),
	);

	// #1 latest member ID, #2 the real name for a new registration.
	if (is_int($id_member))
	{
		$changes['latestMember'] = $id_member;
		$changes['latestRealName'] = $real_name;

		updateSettings(array('totalMembers' => true), true);
	}
	// We need to calculate the totals.
	else
	{
		// Update the latest activated member (highest id_member) and count.
		$request = $db->query('', '
			SELECT COUNT(*), MAX(id_member)
			FROM {db_prefix}members
			WHERE is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
			)
		);
		list ($changes['totalMembers'], $changes['latestMember']) = $db->fetch_row($request);
		$db->free_result($request);

		// Get the latest activated member's display name.
		$request = getBasicMemberData((int) $changes['latestMember']);
		$changes['latestRealName'] = $request['real_name'];

		// Are we using registration approval?
		if ((!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion']))
		{
			// Update the amount of members awaiting approval - ignoring COPPA accounts, as you can't approve them until you get permission.
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}members
				WHERE is_activated IN ({array_int:activation_status})',
				array(
					'activation_status' => array(3, 4),
				)
			);
			list ($changes['unapprovedMembers']) = $db->fetch_row($request);
			$db->free_result($request);
		}
	}

	updateSettings($changes);
}

/**
 * Builds the 'query_see_board' element for a certain member
 *
 * @package Members
 * @param integer $id_member a valid member id
 * @return string Query string
 */
function memberQuerySeeBoard($id_member)
{
	global $modSettings;

	$member = getBasicMemberData($id_member, array('moderation' => true));
	if (empty($member))
	{
		return '0=1';
	}

	if (empty($member['additional_groups']))
	{
		$groups = array($member['id_group'], $member['id_post_group']);
	}
	else
	{
		$groups = array_merge(
			array($member['id_group'], $member['id_post_group']),
			explode(',', $member['additional_groups'])
		);
	}

	foreach ($groups as $k => $v)
	{
		$groups[$k] = (int) $v;
	}
	$groups = array_unique($groups);

	if (in_array(1, $groups))
	{
		return '1=1';
	}
	else
	{
		require_once(SUBSDIR . '/Boards.subs.php');

		$boards_mod = boardsModerated($id_member);
		$mod_query = empty($boards_mod) ? '' : ' OR b.id_board IN (' . implode(',', $boards_mod) . ')';

		return '((FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $groups) . ', b.member_groups) != 0)' . (!empty($modSettings['deny_boards_access']) ? ' AND (FIND_IN_SET(' . implode(', b.deny_member_groups) = 0 AND FIND_IN_SET(', $groups) . ', b.deny_member_groups) = 0)' : '') . $mod_query . ')';
	}
}

/**
 * Updates the columns in the members table.
 *
 * What it does:
 *
 * - Assumes the data has been htmlspecialchar'd, no sanitation is performed on the data.
 * - This function should be used whenever member data needs to be updated in place of an UPDATE query.
 * - $data is an associative array of the columns to be updated and their respective values.
 * any string values updated should be quoted and slashed.
 * - The value of any column can be '+' or '-', which mean 'increment' and decrement, respectively.
 * - If the member's post number is updated, updates their post groups.
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
		'new_pm', 'pm_prefs', 'hide_email', 'show_online', 'pm_email_notify', 'receive_from', 'karma_good', 'karma_bad',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning', 'likes_given',
		'likes_received', 'enable_otp', 'otp_used'
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
			'birthdate',
			'website_title',
			'website_url',
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
				$member_names = $db->fetchQueryCallback('
					SELECT member_name
					FROM {db_prefix}members
					WHERE ' . $condition,
					$parameters,
					function ($row)
					{
						return $row['member_name'];
					}
				);
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

	require_once(SUBSDIR . '/Membergroups.subs.php');
	updatePostGroupStats($members, array_keys($data));

	$cache = Cache::instance();

	// Clear any caching?
	if ($cache->levelHigherThan(1) && !empty($members))
	{
		if (!is_array($members))
			$members = array($members);

		foreach ($members as $member)
		{
			if ($cache->levelHigherThan(2))
			{
				$cache->remove('member_data-profile-' . $member);
				$cache->remove('member_data-normal-' . $member);
				$cache->remove('member_data-minimal-' . $member);
			}

			$cache->remove('user_settings-' . $member);
		}
	}
}

/**
 * Loads members who are associated with an ip address
 *
 * @param string $ip_string raw value to use in where clause
 * @param string $ip_var
 */
function loadMembersIPs($ip_string, $ip_var)
{
	global $scripturl;

	$db = database();

	$request = $db->query('', '
		SELECT
			id_member, real_name AS display_name, member_ip
		FROM {db_prefix}members
		WHERE member_ip ' . $ip_string,
		array(
			'ip_address' => $ip_var,
		)
	);
	$ips = array();
	while ($row = $db->fetch_assoc($request))
		$ips[$row['member_ip']][] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>';
	$db->free_result($request);

	ksort($ips);

	return $ips;
}

function registerAgreementAccepted($id_member, $ip, $agreement_version)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_agreement_accept',
		array(
			'version' => 'date',
			'id_member' => 'int',
			'accepted_date' => 'date',
			'accepted_ip' => 'string-255',
		),
		array(
			array(
				'version' => $agreement_version,
				'id_member' => $id_member,
				'accepted_date' => strftime('%Y-%m-%d', forum_time(false)),
				'accepted_ip' => $ip,
			)
		),
		array('version', 'id_member')
	);
}

/**
 * Utility function to update a members salt to a new value
 *
 * @param int $id member to update
 * @param bool $refresh if to always refresh to a new salt
 * @param int $min if current salt lenght is less than this, gen a new one
 * @return bool
 */
function updateMemberSalt($id, $refresh = false, $min = 9)
{
	global $user_settings;

	if (empty($user_settings['password_salt']))
	{
		return false;
	}

	if ((strlen($user_settings['password_salt']) > $min) && !$refresh)
	{
		return false;
	}

	$tokenizer = new Token_Hash();
	$user_settings['password_salt'] = $tokenizer->generate_hash(16);
	updateMemberData((int) $id, array('password_salt' => $user_settings['password_salt']));

	return true;
}
