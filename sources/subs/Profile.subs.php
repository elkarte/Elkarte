<?php

/**
 * Functions to support the profile controller
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

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Controller\Avatars;
use ElkArte\Errors\ErrorContext;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\Notifications\Notifications;
use ElkArte\Profile\ProfileFields;
use ElkArte\User;

/**
 * Find the ID of the "current" member
 *
 * @param bool $fatal if the function ends in a fatal error in case of problems (default true)
 * @param bool $reload_id if true the already set value is ignored (default false)
 *
 * @return int if no error.  May return false in case of problems only if $fatal is set to false
 * @throws \ElkArte\Exceptions\Exception not_a_user
 */
function currentMemberID($fatal = true, $reload_id = false)
{
	static $memID;

	// If we already know who we're dealing with
	if (isset($memID) && !$reload_id)
	{
		return (int) $memID;
	}

	// Did we get the user by name...
	if (isset($_REQUEST['user']))
	{
		$memberResult = MembersList::load($_REQUEST['user'], true, 'profile');
	}
	// ... or by id_member?
	elseif (!empty($_REQUEST['u']))
	{
		$memberResult = MembersList::load((int) $_REQUEST['u'], false, 'profile');
	}
	// If it was just ?action=profile, edit your own profile.
	else
	{
		$memberResult = MembersList::load(User::$info->id, false, 'profile');
	}

	// Check if \ElkArte\MembersList::load() has returned a valid result.
	if (!is_array($memberResult))
	{
		// Members only...
		is_not_guest('', $fatal);

		if ($fatal)
		{
			throw new \ElkArte\Exceptions\Exception('not_a_user', false);
		}

		return false;
	}

	// If all went well, we have a valid member ID!
	list ($memID) = $memberResult;

	// Cast here is probably not needed, but don't trust yet !
	return (int) $memID;
}

/**
 * Set the context for a page load!
 *
 * @param array $fields
 * @param string $hook a string that represent the hook that can be used to operate on $fields
 */
function setupProfileContext($fields, $hook = '')
{
	global $profile_fields, $context, $cur_profile, $txt;

	if (!empty($hook))
	{
		call_integration_hook('integrate_' . $hook . '_profile_fields', array(&$fields));
	}

	// Make sure we have this!
	$profileFields = new ProfileFields();
	$profileFields->loadProfileFields(true);

	// First check for any linked sets.
	foreach ($profile_fields as $key => $field)
	{
		if (isset($field['link_with']) && in_array($field['link_with'], $fields, true))
		{
			$fields[] = $key;
		}
	}

	// Some default bits.
	$context['profile_prehtml'] = '';
	$context['profile_posthtml'] = '';
	$context['profile_onsubmit_javascript'] = '';

	$i = 0;
	$last_type = '';
	foreach ($fields as $field)
	{
		if (isset($profile_fields[$field]))
		{
			// Shortcut.
			$cur_field = &$profile_fields[$field];

			// Does it have a preload and does that preload succeed?
			if (isset($cur_field['preload']) && !$cur_field['preload']())
			{
				continue;
			}

			// If this is anything but complex we need to do more cleaning!
			if ($cur_field['type'] !== 'callback' && $cur_field['type'] !== 'hidden')
			{
				if (!isset($cur_field['label']))
				{
					$cur_field['label'] = $txt[$field] ?? $field;
				}

				// Everything has a value!
				if (!isset($cur_field['value']))
				{
					$cur_field['value'] = $cur_profile[$field] ?? '';
				}

				// Any input attributes?
				$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
			}

			// Was there an error with this field on posting?
			if (isset($context['post_errors'][$field]))
			{
				$cur_field['is_error'] = true;
			}

			// Any javascript stuff?
			if (!empty($cur_field['js_submit']))
			{
				$context['profile_onsubmit_javascript'] .= $cur_field['js_submit'];
			}
			if (!empty($cur_field['js']))
			{
				theme()->addInlineJavascript($cur_field['js']);
			}
			if (!empty($cur_field['js_load']))
			{
				loadJavascriptFile($cur_field['js_load']);
			}

			// Any template stuff?
			if (!empty($cur_field['prehtml']))
			{
				$context['profile_prehtml'] .= $cur_field['prehtml'];
			}
			if (!empty($cur_field['posthtml']))
			{
				$context['profile_posthtml'] .= $cur_field['posthtml'];
			}

			// Finally, put it into context?
			if ($cur_field['type'] !== 'hidden')
			{
				$last_type = $cur_field['type'];
				$context['profile_fields'][$field] = &$profile_fields[$field];
			}
		}
		// Bodge in a line break - without doing two in a row ;)
		elseif ($field === 'hr' && $last_type !== 'hr' && $last_type !== '')
		{
			$last_type = 'hr';
			$context['profile_fields'][$i++]['type'] = 'hr';
		}
	}

	// Free up some memory.
	unset($profile_fields);
}

/**
 * Save the profile changes
 *
 * @param array $profile_vars
 * @param int $memID id_member
 */
function saveProfileChanges(&$profile_vars, $memID)
{
	global $context;

	// These make life easier....
	$old_id_theme = MembersList::get($memID)->id_theme;

	// Permissions...
	if ($context['user']['is_owner'])
	{
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own'));
	}
	else
	{
		$changeOther = allowedTo('profile_extra_any');
	}

	// Arrays of all the changes - makes things easier.
	$profile_bools = array(
		'notify_announcements',
		'notify_send_body',
	);

	$profile_ints = array(
		'notify_regularity',
		'notify_types',
	);

	$profile_floats = array();

	$profile_strings = array(
		'buddy_list',
		'ignore_boards',
	);

	call_integration_hook('integrate_save_profile_changes', array(&$profile_bools, &$profile_ints, &$profile_floats, &$profile_strings));

	if (isset($_POST['sa']) && $_POST['sa'] === 'ignoreboards' && empty($_POST['ignore_brd']))
	{
		$_POST['ignore_brd'] = array();
	}

	// Whatever it is set to is a dirty filthy thing.  Kinda like our minds.
	unset($_POST['ignore_boards']);

	if (isset($_POST['ignore_brd']))
	{
		if (!is_array($_POST['ignore_brd']))
		{
			$_POST['ignore_brd'] = array($_POST['ignore_brd']);
		}

		foreach ($_POST['ignore_brd'] as $k => $d)
		{
			$d = (int) $d;
			if ($d !== 0)
			{
				$_POST['ignore_brd'][$k] = $d;
			}
			else
			{
				unset($_POST['ignore_brd'][$k]);
			}
		}
		$_POST['ignore_boards'] = implode(',', $_POST['ignore_brd']);
		unset($_POST['ignore_brd']);
	}

	// Here's where we sort out all the 'other' values...
	if ($changeOther)
	{
		// Make any theme changes
		makeThemeChanges($memID, isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_id_theme);

		// Make any notification changes
		makeNotificationChanges($memID);

		if (!empty($_REQUEST['sa']))
		{
			makeCustomFieldChanges($memID, $_REQUEST['sa'], false);
		}

		foreach ($profile_bools as $var)
		{
			if (isset($_POST[$var]))
			{
				$profile_vars[$var] = empty($_POST[$var]) ? '0' : '1';
			}
		}

		foreach ($profile_ints as $var)
		{
			if (isset($_POST[$var]))
			{
				$profile_vars[$var] = $_POST[$var] != '' ? (int) $_POST[$var] : '';
			}
		}

		foreach ($profile_floats as $var)
		{
			if (isset($_POST[$var]))
			{
				$profile_vars[$var] = (float) $_POST[$var];
			}
		}

		foreach ($profile_strings as $var)
		{
			if (isset($_POST[$var]))
			{
				$profile_vars[$var] = $_POST[$var];
			}
		}
	}
}

/**
 * Make any theme changes that are sent with the profile.
 *
 * @param int $memID
 * @param int $id_theme
 * @throws \ElkArte\Exceptions\Exception
 */
function makeThemeChanges($memID, $id_theme)
{
	global $modSettings, $context;

	$db = database();

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
	if ((isset($_POST['options']) && count(array_intersect(array_keys($_POST['options']), $reservedVars)) !== 0) || (isset($_POST['default_options']) && count(array_intersect(array_keys($_POST['default_options']), $reservedVars)) !== 0))
	{
		throw new \ElkArte\Exceptions\Exception('no_access', false);
	}

	// Don't allow any overriding of custom fields with default or non-default options.
	$custom_fields = array();
	$db->fetchQuery('
		SELECT 
			col_name
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}',
		array(
			'is_active' => 1,
		)
	)->fetch_callback(
		function ($row) use (&$custom_fields) {
			$custom_fields[] = $row['col_name'];
		}
	);

	// These are the theme changes...
	$themeSetArray = array();
	if (isset($_POST['options']) && is_array($_POST['options']))
	{
		foreach ($_POST['options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
			{
				continue;
			}

			// These need to be controlled.
			if ($opt === 'topics_per_page' || $opt === 'messages_per_page')
			{
				$val = max(0, min($val, 50));
			}
			// We don't set this per theme anymore.
			elseif ($opt === 'allow_no_censored')
			{
				continue;
			}

			$themeSetArray[] = array($id_theme, $memID, $opt, is_array($val) ? implode(',', $val) : $val);
		}
	}

	$erase_options = array();
	if (isset($_POST['default_options']) && is_array($_POST['default_options']))
	{
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
			{
				continue;
			}

			// These need to be controlled.
			if ($opt === 'topics_per_page' || $opt === 'messages_per_page')
			{
				$val = max(0, min($val, 50));
			}
			// Only let admins and owners change the censor.
			elseif ($opt === 'allow_no_censored' && User::$info->is_admin && !$context['user']['is_owner'])
			{
				continue;
			}

			$themeSetArray[] = array(1, $memID, $opt, is_array($val) ? implode(',', $val) : $val);
			$erase_options[] = $opt;
		}
	}

	// If themeSetArray isn't still empty, send it to the database.
	if (empty($context['password_auth_failed']))
	{
		require_once(SUBSDIR . '/Themes.subs.php');
		if (!empty($themeSetArray))
		{
			updateThemeOptions($themeSetArray);
		}

		if (!empty($erase_options))
		{
			removeThemeOptions('custom', $memID, $erase_options);
		}

		$themes = explode(',', $modSettings['knownThemes']);
		foreach ($themes as $t)
		{
			Cache::instance()->remove('theme_settings-' . $t . ':' . $memID);
		}
	}
}

/**
 * Make any notification changes that need to be made.
 *
 * @param int $memID id_member
 */
function makeNotificationChanges($memID)
{
	$db = database();

	if (isset($_POST['notify_submit']))
	{
		$to_save = [];

		foreach (getMemberNotificationsProfile($memID) as $mention => $data)
		{
			if (isset($_POST['notify'][$mention]) && !empty($_POST['notify'][$mention]['status']))
			{
				// When is not an array it means => use default => 0 so it's skipped on INSERT
				if (!is_array($_POST['notify'][$mention]['status']))
				{
					$to_save[$mention] = 0;
					continue;
				}

				foreach ($_POST['notify'][$mention]['status'] as $method)
				{
					// This ensures that the $method passed by the user is valid and safe to INSERT.
					if (isset($data['data'][$method]))
					{
						if (!isset($to_save[$mention]))
						{
							$to_save[$mention] = [];
						}
						$to_save[$mention][] = $method;
					}
				}
			}
			else
			{
				$to_save[$mention] = [Notifications::DEFAULT_NONE];
			}
		}

		saveUserNotificationsPreferences($memID, $to_save);
	}

	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']))
	{
		if (!isset($_POST['notify_boards']))
		{
			$_POST['notify_boards'] = array();
		}

		// Make sure only integers are added/deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
		{
			$_POST['notify_boards'][$index] = (int) $id;
		}

		// id_board = 0 is reserved for topic notifications only
		$notification_wanted = array_diff($_POST['notify_boards'], array(0));

		// Gather up any any existing board notifications.
		$notification_current = array();
		$db->fetchQuery('
			SELECT 
				id_board
			FROM {db_prefix}log_notify
			WHERE id_member = {int:selected_member}
				AND id_board != {int:id_board}',
			array(
				'selected_member' => $memID,
				'id_board' => 0,
			)
		)->fetch_callback(
			function ($row) use (&$notification_current) {
				$notification_current[] = $row['id_board'];
			}
		);

		// And remove what they no longer want
		$notification_deletes = array_diff($notification_current, $notification_wanted);
		if (!empty($notification_deletes))
		{
			$db->query('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_board IN ({array_int:board_list})
					AND id_member = {int:selected_member}',
				array(
					'board_list' => $notification_deletes,
					'selected_member' => $memID,
				)
			);
		}

		// Now add in what they do want
		$notification_inserts = array();
		foreach ($notification_wanted as $id)
		{
			$notification_inserts[] = array($memID, $id);
		}

		if (!empty($notification_inserts))
		{
			$db->insert('ignore',
				'{db_prefix}log_notify',
				array('id_member' => 'int', 'id_board' => 'int'),
				$notification_inserts,
				array('id_member', 'id_board')
			);
		}
	}
	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		$edit_notify_topics = array();
		foreach ($_POST['notify_topics'] as $index => $id)
		{
			$edit_notify_topics[$index] = (int) $id;
		}

		// Make sure there are no zeros left.
		$edit_notify_topics = array_diff($edit_notify_topics, array(0));

		$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:selected_member}',
			array(
				'topic_list' => $edit_notify_topics,
				'selected_member' => $memID,
			)
		);
	}
}

/**
 * Save any changes to the custom profile fields
 *
 * @param int $memID
 * @param string $area
 * @param bool $sanitize = true
 */
function makeCustomFieldChanges($memID, $area, $sanitize = true)
{
	global $context, $modSettings;

	$db = database();

	if ($sanitize && isset($_POST['customfield']))
	{
		$_POST['customfield'] = Util::htmlspecialchars__recursive($_POST['customfield']);
	}

	$where = $area === 'register' ? 'show_reg != 0' : 'show_profile = {string:area}';

	// Load the fields we are saving too - make sure we save valid data (etc).
	$request = $db->query('', '
		SELECT 
			col_name, field_name, field_desc, field_type, field_length, field_options, default_value, show_reg, mask, private
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
			AND active = {int:is_active}',
		array(
			'is_active' => 1,
			'area' => $area,
		)
	);
	$changes = array();
	$log_changes = array();
	while (($row = $request->fetch_assoc()))
	{
		/* This means don't save if:
			- The user is NOT an admin.
			- The data is not freely viewable and editable by users.
			- The data is not invisible to users but editable by the owner (or if it is the user is not the owner)
			- The area isn't registration, and if it is that the field is not supposed to be shown there.
		*/
		if ($row['private'] != 0 && !allowedTo('admin_forum') && ($memID != User::$info->id || $row['private'] != 2) && ($area !== 'register' || $row['show_reg'] == 0))
		{
			continue;
		}

		// Validate the user data.
		if ($row['field_type'] === 'check')
		{
			$value = isset($_POST['customfield'][$row['col_name']]) ? 1 : 0;
		}
		elseif (in_array($row['field_type'], array('radio', 'select')))
		{
			$value = $row['default_value'];
			$options = explode(',', $row['field_options']);

			foreach ($options as $k => $v)
			{
				if (isset($_POST['customfield'][$row['col_name']]) && $_POST['customfield'][$row['col_name']] == $k)
				{
					$key = $k;
					$value = $v;
				}
			}
		}
		// Otherwise some form of text!
		else
		{
			// TODO: This is a bit backwards.
			$value = $_POST['customfield'][$row['col_name']] ?? $row['default_value'];
			$is_valid = isCustomFieldValid($row, $value);

			if ($is_valid !== true)
			{
				switch ($is_valid)
				{
					case 'custom_field_too_long':
						$value = Util::substr($value, 0, $row['field_length']);
						break;
					case 'custom_field_invalid_email':
					case 'custom_field_inproper_format':
						$value = $row['default_value'];
						break;
				}
			}

			if ($row['mask'] === 'number')
			{
				$value = (int) $value;
			}
		}

		$options = MembersList::get($memID)->options;
		// Did it change or has it been set?
		if ((!isset($options[$row['col_name']]) && !empty($value)) || (isset($options[$row['col_name']]) && $options[$row['col_name']] !== $value))
		{
			$log_changes[] = array(
				'action' => 'customfield_' . $row['col_name'],
				'log_type' => 'user',
				'extra' => array(
					'previous' => !empty($options[$row['col_name']]) ? $options[$row['col_name']] : '',
					'new' => $value,
					'applicator' => User::$info->id,
					'member_affected' => $memID,
				),
			);

			$changes[] = array($row['col_name'], $value, $memID);
			if (in_array($row['field_type'], array('radio', 'select')))
			{
				$options[$row['col_name']] = $value;
				$options[$row['col_name'] . '_key'] = $row['col_name'] . '_' . ($key ?? 0);
			}
			else
			{
				$options[$row['col_name']] = $value;
			}
		}
	}
	$request->free_result();

	call_integration_hook('integrate_save_custom_profile_fields', array(&$changes, &$log_changes, $memID, $area, $sanitize));

	// Make those changes!
	if (!empty($changes) && empty($context['password_auth_failed']))
	{
		$db->replace(
			'{db_prefix}custom_fields_data',
			array('variable' => 'string-255', 'value' => 'string-65534', 'id_member' => 'int'),
			$changes,
			array('variable', 'id_member')
		);

		if (!empty($log_changes) && featureEnabled('ml') && !empty($modSettings['userlog_enabled']))
		{
			logActions($log_changes);
		}
	}
}

/**
 * Validates the value of a custom field
 *
 * @param array $field - An array describing the field. It consists of the
 *                indexes:
 *                  - type; if different from 'text', only the length is checked
 *                  - mask; if empty or equal to 'none', only the length is
 *                          checked, possible masks are: email, number, regex
 *                  - field_length; maximum length of the field
 * @param string|int $value - The value that we want to validate
 * @return string|bool - A string representing the type of error, or true
 */
function isCustomFieldValid($field, $value)
{
	// Is it too long?
	if ($field['field_length'] && $field['field_length'] < Util::strlen($value))
	{
		return 'custom_field_too_long';
	}

	// Any masks to apply?
	if ($field['field_type'] === 'text' && !empty($field['mask']) && $field['mask'] !== 'none')
	{
		if ($field['mask'] === 'email' && !isValidEmail($value))
		{
			return 'custom_field_invalid_email';
		}

		if ($field['mask'] === 'number' && preg_match('~\D~', $value))
		{
			return 'custom_field_not_number';
		}

		if (strpos($field['mask'], 'regex') === 0 && trim($value) !== '' && preg_match(substr($field['mask'], 5), $value) === 0)
		{
			return 'custom_field_inproper_format';
		}
	}

	return true;
}

/**
 * Send the user a new activation email if they need to reactivate!
 */
function profileSendActivation()
{
	global $profile_vars, $old_profile, $txt, $context, $scripturl, $cookiename, $cur_profile, $language, $modSettings;

	require_once(SUBSDIR . '/Mail.subs.php');

	// Shouldn't happen but just in case.
	if (empty($profile_vars['email_address']))
	{
		return;
	}

	$replacements = array(
		'ACTIVATIONLINK' => $scripturl . '?action=register;sa=activate;u=' . $context['id_member'] . ';code=' . $old_profile['validation_code'],
		'ACTIVATIONCODE' => $old_profile['validation_code'],
		'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=register;sa=activate;u=' . $context['id_member'],
	);

	// Send off the email.
	$emaildata = loadEmailTemplate('activate_reactivate', $replacements, empty($cur_profile['lngfile']) || empty($modSettings['userLanguage']) ? $language : $cur_profile['lngfile']);
	sendmail($profile_vars['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

	// Log the user out.
	require_once(SUBSDIR . '/Logging.subs.php');
	logOnline($context['id_member'], false);
	$_SESSION['log_time'] = 0;
	$_SESSION['login_' . $cookiename] = serialize(array(0, '', 0));

	if (isset($_COOKIE[$cookiename]))
	{
		$_COOKIE[$cookiename] = '';
	}

	User::load(true);
	User::$info['is_logged'] = $context['user']['is_logged'] = false;
	User::$info['is_guest'] = $context['user']['is_guest'] = true;

	// Send them to the done-with-registration-login screen.
	theme()->getTemplates()->load('Register');

	$context['page_title'] = $txt['profile'];
	$context['sub_template'] = 'after';
	$context['title'] = $txt['activate_changed_email_title'];
	$context['description'] = $txt['activate_changed_email_desc'];

	// We're gone!
	obExit();
}

/**
 * Load key signature context data.
 *
 * @return bool
 */
function profileLoadSignatureData()
{
	global $modSettings, $context, $txt, $cur_profile;

	// Signature limits.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);

	$context['signature_enabled'] = $sig_limits[0] ?? 0;
	$context['signature_limits'] = array(
		'max_length' => $sig_limits[1] ?? 0,
		'max_lines' => $sig_limits[2] ?? 0,
		'max_images' => $sig_limits[3] ?? 0,
		'max_smileys' => $sig_limits[4] ?? 0,
		'max_image_width' => $sig_limits[5] ?? 0,
		'max_image_height' => $sig_limits[6] ?? 0,
		'max_font_size' => $sig_limits[7] ?? 0,
		'bbc' => !empty($sig_bbc) ? explode(',', $sig_bbc) : array(),
	);

	// Warning message for signature image limits?
	$context['signature_warning'] = '';
	if ($context['signature_limits']['max_image_width'] && $context['signature_limits']['max_image_height'])
	{
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_size'], $context['signature_limits']['max_image_width'], $context['signature_limits']['max_image_height']);
	}
	elseif ($context['signature_limits']['max_image_width'] || $context['signature_limits']['max_image_height'])
	{
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_' . ($context['signature_limits']['max_image_width'] ? 'width' : 'height')], $context['signature_limits'][$context['signature_limits']['max_image_width'] ? 'max_image_width' : 'max_image_height']);
	}

	if (empty($context['do_preview']))
	{
		$context['member']['signature'] = empty($cur_profile['signature_raw']) ? '' : str_replace(array('<br />', '<', '>', '"', '\''), array("\n", '&lt;', '&gt;', '&quot;', '&#039;'), $cur_profile['signature_raw']);
	}
	else
	{
		$signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
		$validation = profileValidateSignature($signature);
		if (empty($context['post_errors']))
		{
			Txt::load('Errors');
			$context['post_errors'] = array();
		}

		$context['post_errors'][] = 'signature_not_yet_saved';
		if ($validation !== true && $validation !== false)
		{
			$context['post_errors'][] = $validation;
		}

		$context['member']['signature'] = censor($context['member']['signature']);
		$context['member']['current_signature'] = $context['member']['signature'];
		$signature = censor($signature);
		$bbc_parser = ParserWrapper::instance();
		$context['member']['signature_preview'] = $bbc_parser->parseSignature($signature, true);
		$context['member']['signature'] = $_POST['signature'];
	}

	return true;
}

/**
 * Load avatar context data.
 *
 * @return bool
 */
function profileLoadAvatarData()
{
	global $context, $cur_profile, $modSettings;

	if (!is_array($cur_profile['avatar']))
	{
		$cur_profile['avatar'] = determineAvatar($cur_profile);
	}

	$context['avatar_url'] = $modSettings['avatar_url'];
	$valid_protocol = preg_match('~^https' . (detectServer()->supportsSSL() ? '' : '?') . '://~i', $cur_profile['avatar']['name']) === 1;
	$schema = 'http' . (detectServer()->supportsSSL() ? 's' : '') . '://';

	// @todo Temporary
	if ($context['user']['is_owner'])
	{
		$allowedChange = allowedTo('profile_set_avatar') && allowedTo(array('profile_extra_any', 'profile_extra_own'));
	}
	else
	{
		$allowedChange = allowedTo('profile_set_avatar') && allowedTo('profile_extra_any');
	}

	// Default context.
	$context['member']['avatar'] += array(
		'custom' => $valid_protocol ? $cur_profile['avatar']['name'] : $schema,
		'selection' => $valid_protocol ? $cur_profile['avatar']['name'] : '',
		'id_attach' => $cur_profile['id_attach'],
		'filename' => $cur_profile['filename'],
		'allow_server_stored' => !empty($modSettings['avatar_stored_enabled']) && $allowedChange,
		'allow_upload' => !empty($modSettings['avatar_upload_enabled']) && $allowedChange,
		'allow_external' => !empty($modSettings['avatar_external_enabled']) && $allowedChange,
		'allow_gravatar' => !empty($modSettings['avatar_gravatar_enabled']) && $allowedChange,
	);

	if ($cur_profile['avatar']['name'] === '' && $cur_profile['id_attach'] > 0 && $context['member']['avatar']['allow_upload'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'upload',
			'server_pic' => 'blank.png',
			'external' => $schema
		);

		$context['member']['avatar'] += array(
			'href' => empty($cur_profile['attachment_type'])
				? getUrl('attach', ['action' => 'dlattach', 'attach' => (int) $cur_profile['id_attach'], 'name' => $cur_profile['filename'], 'type' => 'avatar'])
				: $modSettings['custom_avatar_url'] . '/' . $cur_profile['filename']
		);
	}
	elseif ($valid_protocol && $context['member']['avatar']['allow_external'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'external',
			'server_pic' => 'blank.png',
			'external' => $cur_profile['avatar']['name']
		);
	}
	elseif ($cur_profile['avatar']['name'] === 'gravatar' && $context['member']['avatar']['allow_gravatar'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'gravatar',
			'server_pic' => 'blank.png',
			'external' => 'https://'
		);
	}
	elseif ($cur_profile['avatar']['name'] != '' && FileFunctions::instance()->fileExists($modSettings['avatar_directory'] . '/' . $cur_profile['avatar']['name']) && $context['member']['avatar']['allow_server_stored'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'server_stored',
			'server_pic' => $cur_profile['avatar']['name'] === '' ? 'blank.png' : $cur_profile['avatar']['name'],
			'external' => $schema
		);
	}
	else
	{
		$context['member']['avatar'] += array(
			'choice' => 'none',
			'server_pic' => 'blank.png',
			'external' => $schema
		);
	}

	// Get a list of all the avatars.
	if ($context['member']['avatar']['allow_server_stored'])
	{
		require_once(SUBSDIR . '/Attachments.subs.php');
		$context['avatar_list'] = array();
		$context['avatars'] = FileFunctions::instance()->isDir($modSettings['avatar_directory']) ? getServerStoredAvatars('') : array();
	}
	else
	{
		$context['avatar_list'] = array();
		$context['avatars'] = array();
	}

	// Second level selected avatar...
	$context['avatar_selected'] = substr(strrchr($context['member']['avatar']['server_pic'], '/'), 1);

	return true;
}

/**
 * Loads all the member groups that this member can assign
 * Places the result in context for template use
 */
function profileLoadGroups()
{
	global $cur_profile, $context;

	require_once(SUBSDIR . '/Membergroups.subs.php');

	$context['member_groups'] = getGroupsList();
	$context['member_groups'][0]['is_primary'] = (int) $cur_profile['id_group'] === 0;

	$curGroups = explode(',', $cur_profile['additional_groups']);
	$curGroups = array_map('intval', $curGroups);

	foreach ($context['member_groups'] as $id_group => $row)
	{
		$id_group = (int) $id_group;
		// Registered member was already taken care before
		if ($id_group === 0)
		{
			continue;
		}

		$context['member_groups'][$id_group]['is_primary'] = $cur_profile['id_group'] == $id_group;
		$context['member_groups'][$id_group]['is_additional'] = in_array($id_group, $curGroups, true);
		$context['member_groups'][$id_group]['can_be_additional'] = true;
		$context['member_groups'][$id_group]['can_be_primary'] = $row['hidden'] != 2;
	}

	$context['member']['group_id'] = User::$settings['id_group'];

	return true;
}

/**
 * Load all the languages for the profile.
 */
function profileLoadLanguages()
{
	global $context;

	$context['profile_languages'] = array();

	// Get our languages!
	$languages = getLanguages();

	// Setup our languages.
	foreach ($languages as $lang)
	{
		$context['profile_languages'][$lang['filename']] = $lang['name'];
	}

	ksort($context['profile_languages']);

	// Return whether we should proceed with this.
	return count($context['profile_languages']) > 1;
}

/**
 * Reload a users settings.
 */
function profileReloadUser()
{
	global $modSettings, $context, $cur_profile;

	// Log them back in - using the verify password as they must have matched and this one doesn't get changed by anyone!
	if (isset($_POST['passwrd2']) && $_POST['passwrd2'] !== '')
	{
		require_once(SUBSDIR . '/Auth.subs.php');
		$check = validateLoginPassword($_POST['passwrd2'], $_POST['passwrd1'], $cur_profile['member_name']);
		if ($check === true)
		{
			setLoginCookie(60 * $modSettings['cookieTime'], $context['id_member'], hash('sha256', $_POST['passwrd1'] . $cur_profile['password_salt']));
		}
	}

	User::load(true);
	writeLog();
}

/**
 * Validate the signature
 *
 * @param string $value
 *
 * @return bool|string
 */
function profileValidateSignature(&$value)
{
	global $modSettings, $txt;

	require_once(SUBSDIR . '/Post.subs.php');

	// Admins can do whatever they hell they want!
	if (!allowedTo('admin_forum'))
	{
		// Load all the signature limits.
		list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

		$unparsed_signature = strtr(un_htmlspecialchars($value), array("\r" => '', '&#039' => '\''));

		// Too many lines?
		if (!empty($sig_limits[2]) && substr_count($unparsed_signature, "\n") >= $sig_limits[2])
		{
			$txt['profile_error_signature_max_lines'] = sprintf($txt['profile_error_signature_max_lines'], $sig_limits[2]);

			return 'signature_max_lines';
		}

		// Too many images?!
		if (!empty($sig_limits[3]) && (substr_count(strtolower($unparsed_signature), '[img') + substr_count(strtolower($unparsed_signature), '<img')) > $sig_limits[3])
		{
			$txt['profile_error_signature_max_image_count'] = sprintf($txt['profile_error_signature_max_image_count'], $sig_limits[3]);

			return 'signature_max_image_count';
		}

		// What about too many smileys!
		$smiley_parsed = $unparsed_signature;
		$wrapper = ParserWrapper::instance();
		$parser = $wrapper->getSmileyParser();
		$parser->setEnabled($GLOBALS['user_info']['smiley_set'] !== 'none' && trim($smiley_parsed) !== '');
		$smiley_parsed = $parser->parseBlock($smiley_parsed);

		$smiley_count = substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($unparsed_signature), '<img');
		if (!empty($sig_limits[4]) && $sig_limits[4] == -1 && $smiley_count > 0)
		{
			return 'signature_allow_smileys';
		}

		if (!empty($sig_limits[4]) && $sig_limits[4] > 0 && $smiley_count > $sig_limits[4])
		{
			$txt['profile_error_signature_max_smileys'] = sprintf($txt['profile_error_signature_max_smileys'], $sig_limits[4]);

			return 'signature_max_smileys';
		}

		// Maybe we are abusing font sizes?
		if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)(\]|px|pt|em|x-large|larger)~i', $unparsed_signature, $matches) !== false)
		{
			// Same as parse_bbc
			$sizes = array(1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95);

			foreach ($matches[1] as $ind => $size)
			{
				$limit_broke = 0;

				// Just specifying as [size=x]?
				if (empty($matches[2][$ind]))
				{
					$matches[2][$ind] = 'em';
					$size = $sizes[(int) $size] ?? 0;
				}

				// Attempt to allow all sizes of abuse, so to speak.
				if ($matches[2][$ind] === 'px' && $size > $sig_limits[7])
				{
					$limit_broke = $sig_limits[7] . 'px';
				}
				elseif ($matches[2][$ind] === 'pt' && $size > ($sig_limits[7] * 0.75))
				{
					$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
				}
				elseif ($matches[2][$ind] === 'em' && $size > ((float) $sig_limits[7] / 14))
				{
					$limit_broke = ((float) $sig_limits[7] / 14) . 'em';
				}
				elseif ($matches[2][$ind] !== 'px' && $matches[2][$ind] !== 'pt' && $matches[2][$ind] !== 'em' && $sig_limits[7] < 18)
				{
					$limit_broke = 'large';
				}

				if ($limit_broke)
				{
					$txt['profile_error_signature_max_font_size'] = sprintf($txt['profile_error_signature_max_font_size'], $limit_broke);

					return 'signature_max_font_size';
				}
			}
		}

		// The difficult one - image sizes! Don't error on this - just fix it.
		if ((!empty($sig_limits[5]) || !empty($sig_limits[6])))
		{
			// Get all BBC tags...
			preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br />)*([^<">]+?)(?:<br />)*\[/img\]~i', $unparsed_signature, $matches);

			// ... and all HTML ones.
			preg_match_all('~<img\s+src=(?:")?((?:http://|ftp://|https://|ftps://).+?)(?:")?(?:\s+alt=(?:")?(.*?)(?:")?)?(?:\s?/)?>~i', $unparsed_signature, $matches2, PREG_PATTERN_ORDER);

			// And stick the HTML in the BBC.
			if (!empty($matches2))
			{
				foreach ($matches2[0] as $ind => $dummy)
				{
					$matches[0][] = $matches2[0][$ind];
					$matches[1][] = '';
					$matches[2][] = '';
					$matches[3][] = '';
					$matches[4][] = '';
					$matches[5][] = '';
					$matches[6][] = '';
					$matches[7][] = $matches2[1][$ind];
				}
			}

			$replaces = array();

			// Try to find all the images!
			if (!empty($matches))
			{
				foreach ($matches[0] as $key => $image)
				{
					$width = -1;
					$height = -1;

					// Does it have predefined restraints? Width first.
					if ($matches[6][$key])
					{
						$matches[2][$key] = $matches[6][$key];
					}

					if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
					{
						$width = $sig_limits[5];
						$matches[4][$key] *= $width / $matches[2][$key];
					}
					elseif ($matches[2][$key])
					{
						$width = $matches[2][$key];
					}

					// ... and height.
					if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
					{
						$height = $sig_limits[6];
						if ($width != -1)
						{
							$width *= $height / $matches[4][$key];
						}
					}
					elseif ($matches[4][$key])
					{
						$height = $matches[4][$key];
					}

					// If the dimensions are still not fixed - we need to check the actual image.
					if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
					{
						require_once(SUBSDIR . '/Attachments.subs.php');
						$sizes = url_image_size($matches[7][$key]);
						if (is_array($sizes))
						{
							// Too wide?
							if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
							{
								$width = $sig_limits[5];
								$sizes[1] *= $width / $sizes[0];
							}

							// Too high?
							if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width == -1)
								{
									$width = $sizes[0];
								}
								$width *= $height / $sizes[1];
							}
							elseif ($width != -1)
							{
								$height = $sizes[1];
							}
						}
					}

					// Did we come up with some changes? If so remake the string.
					if ($width != -1 || $height != -1)
					{
						$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
					}
				}

				if (!empty($replaces))
				{
					$value = str_replace(array_keys($replaces), array_values($replaces), $value);
				}
			}
		}

		// @todo temporary, footnotes in signatures is not available at this time
		$disabledTags[] = 'footnote';

		// Any disabled BBC?
		$disabledSigBBC = implode('|', $disabledTags);

		if (!empty($disabledSigBBC))
		{
			if (preg_match('~\[(' . $disabledSigBBC . '[ =\]/])~i', $unparsed_signature, $matches) !== false && isset($matches[1]))
			{
				$disabledTags = array_unique($disabledTags);
				$txt['profile_error_signature_disabled_bbc'] = sprintf($txt['profile_error_signature_disabled_bbc'], implode(', ', $disabledTags));

				return 'signature_disabled_bbc';
			}
		}
	}

	preparsecode($value);

	// Too long?
	if (!allowedTo('admin_forum') && !empty($sig_limits[1]) && Util::strlen(str_replace('<br />', "\n", $value)) > $sig_limits[1])
	{
		$_POST['signature'] = trim(htmlspecialchars(str_replace('<br />', "\n", $value), ENT_QUOTES, 'UTF-8'));
		$txt['profile_error_signature_max_length'] = sprintf($txt['profile_error_signature_max_length'], $sig_limits[1]);

		return 'signature_max_length';
	}

	return true;
}

/**
 * The avatar is incredibly complicated, what with the options... and what not.
 *
 * @param string $value
 *
 * @return false|string
 *
 */
function profileSaveAvatarData($value)
{
	global $profile_vars, $cur_profile, $context;

	$memID = $context['id_member'];
	if (empty($memID) && !empty($context['password_auth_failed']))
	{
		return false;
	}

	$avatar = new Avatars();
	$result = $avatar->processValue($value);
	if ($result !== true)
	{
		$profile_vars['avatar'] = '';

		// @todo add some specific errors to the class
		$errors = ErrorContext::context('profile', 0);
		$errors->addError($result);
		$context['post_errors'] = array(
			'errors' => $errors->prepareErrors(),
			'type' => $errors->getErrorType() == 0 ? 'minor' : 'serious',
		);

		return $result;
	}

	// Setup the profile variables so it shows things right on display!
	$cur_profile['avatar'] = $profile_vars['avatar'];

	return false;
}

/**
 * Save a members group.
 *
 * @param int $value
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception at_least_one_admin
 */
function profileSaveGroups(&$value)
{
	global $profile_vars, $old_profile, $context, $cur_profile;

	$db = database();

	// Do we need to protect some groups?
	if (!allowedTo('admin_forum'))
	{
		$protected_groups = array(1);
		$db->fetchQuery('
			SELECT 
				id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		)->fetch_callback(
			function ($row) use (&$protected_groups) {
				$protected_groups[] = (int) $row['id_group'];
			}
		);

		$protected_groups = array_unique($protected_groups);
	}

	// The account page allows the change of your id_group - but not to a protected group!
	if (empty($protected_groups) || count(array_intersect(array((int) $value, $old_profile['id_group']), $protected_groups)) === 0)
	{
		$value = (int) $value;
	}
	// ... otherwise it's the old group sir.
	else
	{
		$value = $old_profile['id_group'];
	}

	// Find the additional membergroups (if any)
	if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
	{
		$additional_groups = array();
		foreach ($_POST['additional_groups'] as $group_id)
		{
			$group_id = (int) $group_id;
			if (!empty($group_id) && (empty($protected_groups) || !in_array($group_id, $protected_groups)))
			{
				$additional_groups[] = $group_id;
			}
		}

		// Put the protected groups back in there if you don't have permission to take them away.
		$old_additional_groups = explode(',', $old_profile['additional_groups']);
		foreach ($old_additional_groups as $group_id)
		{
			if (!empty($protected_groups) && in_array($group_id, $protected_groups))
			{
				$additional_groups[] = $group_id;
			}
		}

		if (implode(',', $additional_groups) !== $old_profile['additional_groups'])
		{
			$profile_vars['additional_groups'] = implode(',', $additional_groups);
			$cur_profile['additional_groups'] = implode(',', $additional_groups);
		}
	}

	// Too often, people remove delete their own account, or something.
	if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
	{
		$stillAdmin = $value == 1 || (isset($additional_groups) && in_array(1, $additional_groups));

		// If they would no longer be an admin, look for any other...
		if (!$stillAdmin)
		{
			$request = $db->query('', '
				SELECT 
					id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				array(
					'admin_group' => 1,
					'selected_member' => $context['id_member'],
				)
			);
			list ($another) = $request->fetch_row();
			$request->free_result();

			if (empty($another))
			{
				throw new \ElkArte\Exceptions\Exception('at_least_one_admin', 'critical');
			}
		}
	}

	// If we are changing group status, update permission cache as necessary.
	if ($value != $old_profile['id_group'] || isset($profile_vars['additional_groups']))
	{
		if ($context['user']['is_owner'])
		{
			$_SESSION['mc']['time'] = 0;
		}
		else
		{
			updateSettings(array('settings_updated' => time()));
		}
	}

	return true;
}

/**
 * Get the data about a users warnings.
 * Returns an array of them
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID the member ID
 *
 * @return array
 */
function list_getUserWarnings($start, $items_per_page, $sort, $memID)
{
	$db = database();

	$previous_warnings = array();
	$db->fetchQuery('
		SELECT 
			COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
			lc.log_time, lc.body, lc.counter, lc.id_notice
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_recipient = {int:selected_member}
			AND lc.comment_type = {string:warning}
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array(
			'selected_member' => $memID,
			'warning' => 'warning',
		)
	)->fetch_callback(
		function ($row) use (&$previous_warnings) {
			$previous_warnings[] = array(
				'issuer' => array(
					'id' => $row['id_member'],
					'link' => $row['id_member'] ? ('<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_member'], 'name' => $row['member_name']]) . '">' . $row['member_name'] . '</a>') : $row['member_name'],
				),
				'time' => standardTime($row['log_time']),
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
				'reason' => $row['body'],
				'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
				'id_notice' => $row['id_notice'],
			);
		}
	);

	return $previous_warnings;
}

/**
 * Get the number of warnings a user has.
 * Returns the total number of warnings for the user
 *
 * @param int $memID
 * @return int the number of warnings
 */
function list_getUserWarningCount($memID)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}log_comments
		WHERE id_recipient = {int:selected_member}
			AND comment_type = {string:warning}',
		array(
			'selected_member' => $memID,
			'warning' => 'warning',
		)
	);
	list ($total_warnings) = $request->fetch_row();
	$request->free_result();

	return $total_warnings;
}

/**
 * Get a list of attachments for this user
 * (used by createList() callback and others)
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int[] $boardsAllowed
 * @param int $memID
 * @param int[]|null|bool $exclude_boards
 *
 * @return array
 */
function profileLoadAttachments($start, $items_per_page, $sort, $boardsAllowed, $memID, $exclude_boards = null)
{
	global $board, $modSettings, $context;

	$db = database();

	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
	{
		$exclude_boards = array($modSettings['recycle_board']);
	}

	// Retrieve some attachments.
	$attachments = array();
	$db->fetchQuery('
		SELECT
		 	a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, a.fileext, a.width, a.height, ' .
		(empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ' COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height, ') . '
			m.id_msg, m.id_topic, m.id_board, m.poster_time, m.subject, b.name
		FROM {db_prefix}attachments AS a' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!empty($exclude_boards) ? '
			AND b.id_board NOT IN ({array_int:exclude_boards})' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}') . '
		ORDER BY {raw:sort}
		LIMIT {int:limit} OFFSET {int:offset} ',
		array(
			'boards_list' => $boardsAllowed,
			'exclude_boards' => $exclude_boards,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	)->fetch_callback(
		function ($row) use (&$attachments) {
			global $txt, $settings, $modSettings;

			$row['subject'] = censor($row['subject']);
			if (!$row['approved'])
			{
				$row['filename'] = str_replace(array('{attachment_link}', '{txt_awaiting}'), array('<a href="' . getUrl('attach', ['action' => 'dlattach', 'attach' => $row['id_attach'], 'name' => $row['filename'], 'topic' => $row['id_topic'], 'subject' => $row['subject']]) . '">' . $row['filename'] . '</a>', $txt['awaiting_approval']), $settings['attachments_awaiting_approval']);
			}
			else
			{
				$row['filename'] = '<a href="' . getUrl('attach', ['action' => 'dlattach', 'attach' => $row['id_attach'], 'name' => $row['filename'], 'topic' => $row['id_topic'], 'subject' => $row['subject']]) . '">' . $row['filename'] . '</a>';
			}

			$attachments[] = array(
				'id' => $row['id_attach'],
				'filename' => $row['filename'],
				'fileext' => $row['fileext'],
				'width' => $row['width'],
				'height' => $row['height'],
				'downloads' => $row['downloads'],
				'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($modSettings['attachmentShowImages']),
				'id_thumb' => !empty($row['id_thumb']) ? $row['id_thumb'] : '',
				'subject' => '<a href="' . getUrl('topic', ['topic' => $row['id_topic'], 'start' => 'msg' . $row['id_msg'], 'subject' => $row['subject']]) . '#msg' . $row['id_msg'] . '" rel="nofollow">' . censor($row['subject']) . '</a>',
				'posted' => $row['poster_time'],
				'msg' => $row['id_msg'],
				'topic' => $row['id_topic'],
				'board' => $row['id_board'],
				'board_name' => $row['name'],
				'approved' => $row['approved'],
			);
		}
	);

	return $attachments;
}

/**
 * Gets the total number of attachments for the user
 * (used by createList() callbacks)
 *
 * @param int[] $boardsAllowed
 * @param int $memID
 * @return int number of attachments
 */
function getNumAttachments($boardsAllowed, $memID)
{
	global $board, $modSettings, $context;

	$db = database();

	// Get the total number of attachments they have posted.
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($attachCount) = $request->fetch_row();
	$request->free_result();

	return $attachCount;
}

/**
 * Get the relevant topics in the unwatched list
 * (used by createList() callbacks)
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID
 *
 * @return array
 */
function getUnwatchedBy($start, $items_per_page, $sort, $memID)
{
	$db = database();

	// Get the list of topics we can see
	$topics = array();
	$db->fetchQuery('
		SELECT
		 	lt.id_topic
		FROM {db_prefix}log_topics AS lt
			LEFT JOIN {db_prefix}topics AS t ON (lt.id_topic = t.id_topic)
			LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)' . (in_array($sort, array('mem.real_name', 'mem.real_name DESC', 'mem.poster_time', 'mem.poster_time DESC')) ? '
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)' : '') . '
		WHERE lt.id_member = {int:current_member}
			AND lt.unwatched = 1
			AND {query_see_board}
		ORDER BY {raw:sort}
		LIMIT {int:limit} OFFSET {int:offset} ',
		array(
			'current_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	)->fetch_callback(
		function ($row) use (&$topics) {
			$topics[] = $row['id_topic'];
		}
	);

	// Any topics found?
	$topicsInfo = array();
	if (!empty($topics))
	{
		$db->fetchQuery('
			SELECT 
				mf.subject, mf.poster_time as started_on, COALESCE(memf.real_name, mf.poster_name) as started_by, ml.poster_time as last_post_on, COALESCE(meml.real_name, ml.poster_name) as last_post_by, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
			WHERE t.id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		)->fetch_callback(
			function ($row) use (&$topicsInfo) {
				$topicsInfo[] = $row;
			}
		);
	}

	return $topicsInfo;
}

/**
 * Count the number of topics in the unwatched list
 *
 * @param int $memID
 * @return int
 */
function getNumUnwatchedBy($memID)
{
	$db = database();

	// Get the total number of attachments they have posted.
	$request = $db->query('', '
		SELECT
		 	COUNT(*)
		FROM {db_prefix}log_topics AS lt
		LEFT JOIN {db_prefix}topics AS t ON (lt.id_topic = t.id_topic)
		LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
		WHERE id_member = {int:current_member}
			AND unwatched = 1
			AND {query_see_board}',
		array(
			'current_member' => $memID,
		)
	);
	list ($unwatchedCount) = $request->fetch_row();
	$request->free_result();

	return $unwatchedCount;
}

/**
 * Returns the total number of posts a user has made
 *
 * - Counts all posts or just the posts made on a particular board
 *
 * @param int $memID
 * @param int|null $board
 * @return int
 */
function count_user_posts($memID, $board = null)
{
	global $modSettings;

	$db = database();

	$is_owner = $memID == User::$info->id;

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}messages AS m' . (User::$info->query_see_board === '1=1' ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
		WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $is_owner ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($msgCount) = $request->fetch_row();
	$request->free_result();

	return $msgCount;
}

/**
 * Returns the total number of new topics a user has made
 *
 * - Counts all posts or just the topics made on a particular board
 *
 * @param int $memID
 * @param int|null $board
 * @return int
 */
function count_user_topics($memID, $board = null)
{
	global $modSettings;

	$db = database();

	$is_owner = $memID == User::$info->id;

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}topics AS t' . (User::$info->query_see_board === '1=1' ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
		WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
			AND t.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $is_owner ? '' : '
			AND t.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($msgCount) = $request->fetch_row();
	$request->free_result();

	return $msgCount;
}

/**
 * Gets a members minimum and maximum message id
 *
 * - Can limit the results to a particular board
 * - Used to help limit queries by proving start/stop points
 *
 * @param int $memID
 * @param int|null $board
 *
 * @return array
 */
function findMinMaxUserMessage($memID, $board = null)
{
	global $modSettings;

	$db = database();

	$is_owner = $memID == User::$info->id;

	$request = $db->query('', '
		SELECT 
			MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m
		WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $is_owner ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	$minmax = $request->fetch_row();
	$request->free_result();

	return empty($minmax) ? array(0, 0) : $minmax;
}

/**
 * Determines a members minimum and maximum topic id
 *
 * - Can limit the results to a particular board
 * - Used to help limit queries by proving start/stop points
 *
 * @param int $memID
 * @param int|null $board
 *
 * @return array
 */
function findMinMaxUserTopic($memID, $board = null)
{
	global $modSettings;

	$db = database();

	$is_owner = $memID == User::$info->id;

	$request = $db->query('', '
		SELECT 
			MIN(id_topic), MAX(id_topic)
		FROM {db_prefix}topics AS t
		WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
			AND t.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $is_owner ? '' : '
			AND t.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	$minmax = $request->fetch_row();
	$request->free_result();

	return empty($minmax) ? array(0, 0) : $minmax;
}

/**
 * Used to load all the posts of a user
 *
 * - Can limit to just the posts of a particular board
 * - If range_limit is supplied, will check if count results were returned, if not
 * will drop the limit and try again
 *
 * @param int $memID
 * @param int $start
 * @param int $count
 * @param string|null $range_limit
 * @param bool $reverse
 * @param int|null $board
 *
 * @return array
 */
function load_user_posts($memID, $start, $count, $range_limit = '', $reverse = false, $board = null)
{
	global $modSettings;

	$db = database();

	$is_owner = $memID == User::$info->id;
	$user_posts = array();

	// Find this user's posts. The left join on categories somehow makes this faster, weird as it looks.
	for ($i = 0; $i < 2; $i++)
	{
		$request = $db->query('', '
			SELECT
				b.id_board, b.name AS bname,
				c.id_cat, c.name AS cname,
				m.id_topic, m.id_msg, m.body, m.smileys_enabled, m.subject, m.poster_time, m.approved,
				t.id_member_started, t.id_first_msg, t.id_last_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
				AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
				AND ' . $range_limit) . '
				AND {query_see_board}' . (!$modSettings['postmod_active'] || $is_owner ? '' : '
				AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
			ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
			LIMIT ' . $start . ', ' . $count,
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			)
		);

		// Did we get what we wanted, if so stop looking
		if ($request->num_rows() === $count || empty($range_limit))
		{
			break;
		}
		else
		{
			$range_limit = '';
		}
	}

	// Place them in the post array
	while (($row = $request->fetch_assoc()))
	{
		$user_posts[] = $row;
	}
	$request->free_result();

	return $user_posts;
}

/**
 * Used to load all the topics of a user
 *
 * - Can limit to just the posts of a particular board
 * - If range_limit 'guess' is supplied, will check if count results were returned, if not
 * it will drop the guessed limit and try again.
 *
 * @param int $memID
 * @param int $start
 * @param int $count
 * @param string $range_limit
 * @param bool $reverse
 * @param int|null $board
 *
 * @return array
 */
function load_user_topics($memID, $start, $count, $range_limit = '', $reverse = false, $board = null)
{
	global $modSettings;

	$db = database();

	$is_owner = $memID == User::$info->id;
	$user_topics = array();

	// Find this user's topics.  The left join on categories somehow makes this faster, weird as it looks.
	for ($i = 0; $i < 2; $i++)
	{
		$request = $db->query('', '
			SELECT
				b.id_board, b.name AS bname,
				c.id_cat, c.name AS cname,
				t.id_member_started, t.id_first_msg, t.id_last_msg, t.approved,
				m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
				AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
				AND ' . $range_limit) . '
				AND {query_see_board}' . (!$modSettings['postmod_active'] || $is_owner ? '' : '
				AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
			ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
			LIMIT ' . $start . ', ' . $count,
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			)
		);

		// Did we get what we wanted, if so stop looking
		if ($request->num_rows() === $count || empty($range_limit))
		{
			break;
		}
		else
		{
			$range_limit = '';
		}
	}

	// Place them in the topic array
	while (($row = $request->fetch_assoc()))
	{
		$user_topics[] = $row;
	}
	$request->free_result();

	return $user_topics;
}

/**
 * Loads the permissions that are given to a member group or set of groups
 *
 * @param int[] $curGroups
 *
 * @return array
 */
function getMemberGeneralPermissions($curGroups)
{
	$db = database();
	Txt::load('ManagePermissions');

	// Get all general permissions.
	$general_permission = array();
	$db->fetchQuery('
		SELECT 
			p.permission, p.add_deny, mg.group_name, p.id_group
		FROM {db_prefix}permissions AS p
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
		WHERE p.id_group IN ({array_int:group_list})
		ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'group_list' => $curGroups,
			'newbie_group' => 4,
		)
	)->fetch_callback(
		function ($row) use (&$general_permission) {
			global $txt;

			// We don't know about this permission, it doesn't exist :P.
			if (!isset($txt['permissionname_' . $row['permission']]))
			{
				return;
			}

			// Permissions that end with _own or _any consist of two parts.
			if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			{
				$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
			}
			else
			{
				$name = $txt['permissionname_' . $row['permission']];
			}

			// Add this permission if it doesn't exist yet.
			if (!isset($general_permission[$row['permission']]))
			{
				$general_permission[$row['permission']] = array(
					'id' => $row['permission'],
					'groups' => array(
						'allowed' => array(),
						'denied' => array()
					),
					'name' => $name,
					'is_denied' => false,
					'is_global' => true,
				);
			}

			// Add the membergroup to either the denied or the allowed groups.
			$general_permission[$row['permission']]['groups'][empty($row['add_deny'])
				? 'denied'
				: 'allowed'][] = $row['id_group'] == 0
					? $txt['membergroups_members']
					: $row['group_name'];

			// Once denied is always denied.
			$general_permission[$row['permission']]['is_denied'] |= empty($row['add_deny']);
		}
	);

	return $general_permission;
}

/**
 * Get the permissions a member has, or group they are in has
 * If $board is supplied will return just the permissions for that board
 *
 * @param int $memID
 * @param int[] $curGroups
 * @param int|null $board
 *
 * @return array
 */
function getMemberBoardPermissions($memID, $curGroups, $board = null)
{
	$db = database();
	Txt::load('ManagePermissions');

	$board_permission = array();
	$db->fetchQuery('
		SELECT
			bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty($board) ? '' : ',
			b.id_profile, CASE WHEN mods.id_member IS NULL THEN 0 ELSE 1 END AS is_moderator') . '
		FROM {db_prefix}board_permissions AS bp' . (empty($board) ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
		WHERE bp.id_profile = {raw:current_profile}
			AND bp.id_group IN ({array_int:group_list}' . (empty($board) ? ')' : ', {int:moderator_group})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})'),
		array(
			'current_board' => $board,
			'group_list' => $curGroups,
			'current_member' => $memID,
			'current_profile' => empty($board) ? '1' : 'b.id_profile',
			'moderator_group' => 3,
		)
	)->fetch_callback(
		function ($row) use (&$board_permission, $board) {
			global $txt;

			// We don't know about this permission, it doesn't exist :P.
			if (!isset($txt['permissionname_' . $row['permission']]))
			{
				return;
			}

			// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
			if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			{
				$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
			}
			else
			{
				$name = $txt['permissionname_' . $row['permission']];
			}

			// Create the structure for this permission.
			if (!isset($board_permission[$row['permission']]))
			{
				$board_permission[$row['permission']] = array(
					'id' => $row['permission'],
					'groups' => array(
						'allowed' => array(),
						'denied' => array()
					),
					'name' => $name,
					'is_denied' => false,
					'is_global' => empty($board),
				);
			}

			$board_permission[$row['permission']]['groups'][empty($row['add_deny'])
				? 'denied'
				: 'allowed'][$row['id_group']] = $row['id_group'] == 0
					? $txt['membergroups_members']
					: $row['group_name'];
			$board_permission[$row['permission']]['is_denied'] |= empty($row['add_deny']);
		}
	);

	return $board_permission;
}

/**
 * Retrieves (most of) the IPs used by a certain member in his messages and errors
 *
 * @param int $memID the id of the member
 *
 * @return array
 */
function getMembersIPs($memID)
{
	global $modSettings;

	$db = database();
	$member = MembersList::get($memID);

	// @todo cache this
	// If this is a big forum, or a large posting user, let's limit the search.
	if ($modSettings['totalMessages'] > 50000 && $member->posts > 500)
	{
		$request = $db->query('', '
			SELECT 
				MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}',
			array(
				'current_member' => $memID,
			)
		);
		list ($max_msg_member) = $request->fetch_row();
		$request->free_result();

		// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
		$min_msg_member = max(0, $max_msg_member - $member->posts * 3);
	}

	// Default to at least the ones we know about.
	$ips = array(
		$member->member_ip,
		$member->member_ip2,
	);

	// @todo cache this
	// Get all IP addresses this user has used for his messages.
	$db->fetchQuery('
		SELECT DISTINCT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member} ' . (isset($min_msg_member) ? '
			AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : ''),
		array(
			'current_member' => $memID,
			'min_msg_member' => $min_msg_member ?? 0,
			'max_msg_member' => $max_msg_member ?? 0,
		)
	)->fetch_callback(
		function ($row) use (&$ips) {
			$ips[] = $row['poster_ip'];
		}
	);

	// Now also get the IP addresses from the error messages.
	$error_ips = array();
	$db->fetchQuery('
		SELECT 
			COUNT(*) AS error_count, ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_member}
		GROUP BY ip',
		array(
			'current_member' => $memID,
		)
	)->fetch_callback(
		function ($row) use (&$error_ips) {
			$error_ips[] = $row['ip'];
		}
	);

	return array('message_ips' => array_unique($ips), 'error_ips' => array_unique($error_ips));
}

/**
 * Return the details of the members using a certain range of IPs
 * except the current one
 *
 * @param string[] $ips a list of IP addresses
 * @param int $memID the id of the "current" member (maybe it could be retrieved with currentMemberID)
 *
 * @return array
 */
function getMembersInRange($ips, $memID)
{
	$db = database();

	$message_members = array();
	$members_in_range = array();

	// Get member ID's which are in messages...
	$db->fetchQuery('
		SELECT DISTINCT mem.id_member
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.poster_ip IN ({array_string:ip_list})
			AND mem.id_member != {int:current_member}',
		array(
			'current_member' => $memID,
			'ip_list' => $ips,
		)
	)->fetch_callback(
		function ($row) use (&$message_members) {
			$message_members[] = $row['id_member'];
		}
	);

	// And then get the member ID's belong to other users
	$db->fetchQuery('
		SELECT 
			id_member
		FROM {db_prefix}members
		WHERE id_member != {int:current_member}
			AND member_ip IN ({array_string:ip_list})',
		array(
			'current_member' => $memID,
			'ip_list' => $ips,
		)
	)->fetch_callback(
		function ($row) use (&$message_members) {
			$message_members[] = $row['id_member'];
		}
	);

	// Once the IDs are all combined, let's clean them up
	$message_members = array_unique($message_members);

	// And finally, fetch their names, cause of the GROUP BY doesn't like giving us that normally.
	if (!empty($message_members))
	{
		require_once(SUBSDIR . '/Members.subs.php');

		// Get the latest activated member's display name.
		$members_in_range = getBasicMemberData($message_members);
	}

	return $members_in_range;
}

/**
 * Return a detailed situation of the notification methods for a certain member.
 * Used in the profile page to load the defaults and validate the new
 * settings.
 *
 * @param int $member_id the id of a member
 *
 * @return array
 */
function getMemberNotificationsProfile($member_id)
{
	global $modSettings, $txt;

	if (empty($modSettings['enabled_mentions']))
	{
		return array();
	}

	require_once(SUBSDIR . '/Notification.subs.php');

	$notifiers = Notifications::instance()->getNotifiers();
	$enabled_mentions = explode(',', $modSettings['enabled_mentions']);
	$user_preferences = getUsersNotificationsPreferences($enabled_mentions, $member_id);
	$mention_types = array();
	$defaults = getConfiguredNotificationMethods('*');

	foreach ($enabled_mentions as $type)
	{
		$type_on = 1;
		$data = [];
		foreach ($notifiers as $key => $notifier)
		{
			if ((empty($user_preferences[$member_id]) || empty($user_preferences[$member_id][$type])) && empty($defaults[$type]))
			{
				continue;
			}

			if (!isset($defaults[$type][$key]))
			{
				continue;
			}

			$data[$key] = [
				'name' => $key,
				'id' => '',
				'input_name' => 'notify[' . $type . '][' . $key . ']',
				'text' => $txt['notify_' . $key],
				'enabled' => in_array($key, $user_preferences[$member_id][$type] ?? [])
			];

			if (empty($user_preferences[$member_id][$type]))
			{
				$type_on = 0;
			}
		}

		// In theory data should never be empty.
		if (!empty($data))
		{
			$mention_types[$type] = [
				'data' => $data,
				'default_input_name' => 'notify[' . $type . '][status]',
				'user_input_name' => 'notify[' . $type . '][user]',
				'value' => $type_on
			];
		}
	}

	return $mention_types;
}

/**
 * Retrieves custom field data based on the specified condition and area.
 *
 * @param string $where The condition to filter the custom fields.
 * @param string $area The area to restrict the custom fields.
 *
 * @return array The custom field data matching the given condition and area.
 */
function getCustomFieldData($where, $area)
{
	$db = database();

	// Load all the relevant fields - and data.
	// The fully-qualified name for rows is here because it's a reserved word in Mariadb
	// 10.2.4+ and quoting would be different for MySQL/Mariadb and PSQL
	$request = $db->query('', '
		SELECT
			col_name, field_name, field_desc, field_type, show_reg, field_length, field_options,
			default_value, bbc, enclose, placement, mask, vieworder, {db_prefix}custom_fields.rows, cols
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
		ORDER BY vieworder ASC',
		[
			'area' => $area,
		]
	);
	$data = [];
	while ($row = $request->fetch_assoc())
	{
		$data[] = $row;
	}

	return $data;
}

/**
 * Checks if an email address is unique for a given member ID
 *
 * @param int $memID The member ID (0 for new member, otherwise existing member)
 * @param string $email The email address to check for uniqueness
 *
 * @return bool Returns true if the email address is unique, false otherwise
 */
function isUniqueEmail($memID, $email) {
	$db = database();

	// Email addresses should be and stay unique.
	return $db->fetchQuery('
		SELECT 
			id_member
		FROM {db_prefix}members
		WHERE ' . ($memID !== 0 ? 'id_member != {int:selected_member} AND ' : '') . '
			email_address = {string:email_address}
		LIMIT 1',
		[
			'selected_member' => $memID,
			'email_address' => $email,
		]
	)->num_rows();
}