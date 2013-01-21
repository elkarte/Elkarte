<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
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
 */

if (!defined('ELKARTE'))
	die('Hacking attempt...');

/**
 * Setup the context for a page load!
 *
 * @param array $fields
 */
function setupProfileContext($fields)
{
	global $profile_fields, $context, $cur_profile, $smcFunc, $txt;

	// Make sure we have this!
	loadProfileFields(true);

	// First check for any linked sets.
	foreach ($profile_fields as $key => $field)
		if (isset($field['link_with']) && in_array($field['link_with'], $fields))
			$fields[] = $key;

	// Some default bits.
	$context['profile_prehtml'] = '';
	$context['profile_posthtml'] = '';
	$context['profile_javascript'] = '';
	$context['profile_onsubmit_javascript'] = '';

	$i = 0;
	$last_type = '';
	foreach ($fields as $key => $field)
	{
		if (isset($profile_fields[$field]))
		{
			// Shortcut.
			$cur_field = &$profile_fields[$field];

			// Does it have a preload and does that preload succeed?
			if (isset($cur_field['preload']) && !$cur_field['preload']())
				continue;

			// If this is anything but complex we need to do more cleaning!
			if ($cur_field['type'] != 'callback' && $cur_field['type'] != 'hidden')
			{
				if (!isset($cur_field['label']))
					$cur_field['label'] = isset($txt[$field]) ? $txt[$field] : $field;

				// Everything has a value!
				if (!isset($cur_field['value']))
				{
					$cur_field['value'] = isset($cur_profile[$field]) ? $cur_profile[$field] : '';
				}

				// Any input attributes?
				$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
			}

			// Was there an error with this field on posting?
			if (isset($context['profile_errors'][$field]))
				$cur_field['is_error'] = true;

			// Any javascript stuff?
			if (!empty($cur_field['js_submit']))
				$context['profile_onsubmit_javascript'] .= $cur_field['js_submit'];
			if (!empty($cur_field['js']))
				$context['profile_javascript'] .= $cur_field['js'];

			// Any template stuff?
			if (!empty($cur_field['prehtml']))
				$context['profile_prehtml'] .= $cur_field['prehtml'];
			if (!empty($cur_field['posthtml']))
				$context['profile_posthtml'] .= $cur_field['posthtml'];

			// Finally put it into context?
			if ($cur_field['type'] != 'hidden')
			{
				$last_type = $cur_field['type'];
				$context['profile_fields'][$field] = &$profile_fields[$field];
			}
		}
		// Bodge in a line break - without doing two in a row ;)
		elseif ($field == 'hr' && $last_type != 'hr' && $last_type != '')
		{
			$last_type = 'hr';
			$context['profile_fields'][$i++]['type'] = 'hr';
		}
	}

	// Free up some memory.
	unset($profile_fields);
}

/**
 * Load any custom fields for this area.
 * No area means load all, 'summary' loads all public ones.
 *
 * @param int $memID
 * @param string $area = 'summary'
 */
function loadCustomFields($memID, $area = 'summary')
{
	global $context, $txt, $user_profile, $smcFunc, $user_info, $settings, $scripturl;

	// Get the right restrictions in place...
	$where = 'active = 1';
	if (!allowedTo('admin_forum') && $area != 'register')
	{
		// If it's the owner they can see two types of private fields, regardless.
		if ($memID == $user_info['id'])
			$where .= $area == 'summary' ? ' AND private < 3' : ' AND (private = 0 OR private = 2)';
		else
			$where .= $area == 'summary' ? ' AND private < 2' : ' AND private = 0';
	}

	if ($area == 'register')
		$where .= ' AND show_reg != 0';
	elseif ($area != 'summary')
		$where .= ' AND show_profile = {string:area}';

	// Load all the relevant fields - and data.
	$request = $smcFunc['db_query']('', '
		SELECT
			col_name, field_name, field_desc, field_type, show_reg, field_length, field_options,
			default_value, bbc, enclose, placement
		FROM {db_prefix}custom_fields
		WHERE ' . $where,
		array(
			'area' => $area,
		)
	);
	$context['custom_fields'] = array();
	$context['custom_fields_required'] = false;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Shortcut.
		$exists = $memID && isset($user_profile[$memID], $user_profile[$memID]['options'][$row['col_name']]);
		$value = $exists ? $user_profile[$memID]['options'][$row['col_name']] : '';

		// If this was submitted already then make the value the posted version.
		if (isset($_POST['customfield']) && isset($_POST['customfield'][$row['col_name']]))
		{
			$value = $smcFunc['htmlspecialchars']($_POST['customfield'][$row['col_name']]);
			if (in_array($row['field_type'], array('select', 'radio')))
					$value = ($options = explode(',', $row['field_options'])) && isset($options[$value]) ? $options[$value] : '';
		}

		// HTML for the input form.
		$output_html = $value;
		if ($row['field_type'] == 'check')
		{
			$true = (!$exists && $row['default_value']) || $value;
			$input_html = '<input type="checkbox" name="customfield[' . $row['col_name'] . ']" ' . ($true ? 'checked="checked"' : '') . ' class="input_check" />';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		elseif ($row['field_type'] == 'select')
		{
			$input_html = '<select name="customfield[' . $row['col_name'] . ']"><option value="-1"></option>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<option value="' . $k . '"' . ($true ? ' selected="selected"' : '') . '>' . $v . '</option>';
				if ($true)
					$output_html = $v;
			}

			$input_html .= '</select>';
		}
		elseif ($row['field_type'] == 'radio')
		{
			$input_html = '<fieldset>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<label for="customfield_' . $row['col_name'] . '_' . $k . '"><input type="radio" name="customfield[' . $row['col_name'] . ']" class="input_radio" id="customfield_' . $row['col_name'] . '_' . $k . '" value="' . $k . '" ' . ($true ? 'checked="checked"' : '') . ' />' . $v . '</label><br />';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		elseif ($row['field_type'] == 'text')
		{
			$input_html = '<input type="text" name="customfield[' . $row['col_name'] . ']" ' . ($row['field_length'] != 0 ? 'maxlength="' . $row['field_length'] . '"' : '') . ' size="' . ($row['field_length'] == 0 || $row['field_length'] >= 50 ? 50 : ($row['field_length'] > 30 ? 30 : ($row['field_length'] > 10 ? 20 : 10))) . '" value="' . $value . '" class="input_text" />';
		}
		else
		{
			@list ($rows, $cols) = @explode(',', $row['default_value']);
			$input_html = '<textarea name="customfield[' . $row['col_name'] . ']" ' . (!empty($rows) ? 'rows="' . $rows . '"' : '') . ' ' . (!empty($cols) ? 'cols="' . $cols . '"' : '') . '>' . $value . '</textarea>';
		}

		// Parse BBCode
		if ($row['bbc'])
			$output_html = parse_bbc($output_html);
		elseif ($row['field_type'] == 'textarea')
			// Allow for newlines at least
			$output_html = strtr($output_html, array("\n" => '<br />'));

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']) && !empty($output_html))
			$output_html = strtr($row['enclose'], array(
				'{SCRIPTURL}' => $scripturl,
				'{IMAGES_URL}' => $settings['images_url'],
				'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
				'{INPUT}' => $output_html,
			));

		$context['custom_fields'][] = array(
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
			'type' => $row['field_type'],
			'input_html' => $input_html,
			'output_html' => $output_html,
			'placement' => $row['placement'],
			'colname' => $row['col_name'],
			'value' => $value,
			'show_reg' => $row['show_reg'],
		);
		$context['custom_fields_required'] = $context['custom_fields_required'] || $row['show_reg'];
	}
	$smcFunc['db_free_result']($request);

	call_integration_hook('integrate_load_custom_profile_fields', array($memID, $area));
}

/**
 * This defines every profile field known to man.
 *
 * @param bool $force_reload = false
 */
function loadProfileFields($force_reload = false)
{
	global $context, $profile_fields, $txt, $scripturl, $modSettings, $user_info, $old_profile, $smcFunc, $cur_profile, $language;

	// Don't load this twice!
	if (!empty($profile_fields) && !$force_reload)
		return;

	/* This horrific array defines all the profile fields in the whole world!
		In general each "field" has one array - the key of which is the database column name associated with said field. Each item
		can have the following attributes:

				string $type:			The type of field this is - valid types are:
					- callback:		This is a field which has its own callback mechanism for templating.
					- check:		A simple checkbox.
					- hidden:		This doesn't have any visual aspects but may have some validity.
					- password:		A password box.
					- select:		A select box.
					- text:			A string of some description.

				string $label:			The label for this item - default will be $txt[$key] if this isn't set.
				string $subtext:		The subtext (Small label) for this item.
				int $size:			Optional size for a text area.
				array $input_attr:		An array of text strings to be added to the input box for this item.
				string $value:			The value of the item. If not set $cur_profile[$key] is assumed.
				string $permission:		Permission required for this item (Excluded _any/_own subfix which is applied automatically).
				function $input_validate:	A runtime function which validates the element before going to the database. It is passed
								the relevant $_POST element if it exists and should be treated like a reference.

								Return types:
					- true:			Element can be stored.
					- false:		Skip this element.
					- a text string:	An error occured - this is the error message.

				function $preload:		A function that is used to load data required for this element to be displayed. Must return
								true to be displayed at all.

				string $cast_type:		If set casts the element to a certain type. Valid types (bool, int, float).
				string $save_key:		If the index of this element isn't the database column name it can be overriden
								with this string.
				bool $is_dummy:			If set then nothing is acted upon for this element.
				bool $enabled:			A test to determine whether this is even available - if not is unset.
				string $link_with:		Key which links this field to an overall set.

		Note that all elements that have a custom input_validate must ensure they set the value of $cur_profile correct to enable
		the changes to be displayed correctly on submit of the form.

	*/

	$profile_fields = array(
		'avatar_choice' => array(
			'type' => 'callback',
			'callback_func' => 'avatar_select',
			// This handles the permissions too.
			'preload' => 'profileLoadAvatarData',
			'input_validate' => 'profileSaveAvatarData',
			'save_key' => 'avatar',
		),
		'bday1' => array(
			'type' => 'callback',
			'callback_func' => 'birthdate',
			'permission' => 'profile_extra',
			'preload' => create_function('', '
				global $cur_profile, $context;

				// Split up the birthdate....
				list ($uyear, $umonth, $uday) = explode(\'-\', empty($cur_profile[\'birthdate\']) || $cur_profile[\'birthdate\'] == \'0001-01-01\' ? \'0000-00-00\' : $cur_profile[\'birthdate\']);
				$context[\'member\'][\'birth_date\'] = array(
					\'year\' => $uyear == \'0004\' ? \'0000\' : $uyear,
					\'month\' => $umonth,
					\'day\' => $uday,
				);

				return true;
			'),
			'input_validate' => create_function('&$value', '
				global $profile_vars, $cur_profile;

				if (isset($_POST[\'bday2\'], $_POST[\'bday3\']) && $value > 0 && $_POST[\'bday2\'] > 0)
				{
					// Set to blank?
					if ((int) $_POST[\'bday3\'] == 1 && (int) $_POST[\'bday2\'] == 1 && (int) $value == 1)
						$value = \'0001-01-01\';
					else
						$value = checkdate($value, $_POST[\'bday2\'], $_POST[\'bday3\'] < 4 ? 4 : $_POST[\'bday3\']) ? sprintf(\'%04d-%02d-%02d\', $_POST[\'bday3\'] < 4 ? 4 : $_POST[\'bday3\'], $_POST[\'bday1\'], $_POST[\'bday2\']) : \'0001-01-01\';
				}
				else
					$value = \'0001-01-01\';

				$profile_vars[\'birthdate\'] = $value;
				$cur_profile[\'birthdate\'] = $value;
				return false;
			'),
		),
		// Setting the birthdate the old style way?
		'birthdate' => array(
			'type' => 'hidden',
			'permission' => 'profile_extra',
			'input_validate' => create_function('&$value', '
				global $cur_profile;
				// @todo Should we check for this year and tell them they made a mistake :P? (based on coppa at least?)
				if (preg_match(\'/(\d{4})[\-\., ](\d{2})[\-\., ](\d{2})/\', $value, $dates) === 1)
				{
					$value = checkdate($dates[2], $dates[3], $dates[1] < 4 ? 4 : $dates[1]) ? sprintf(\'%04d-%02d-%02d\', $dates[1] < 4 ? 4 : $dates[1], $dates[2], $dates[3]) : \'0001-01-01\';
					return true;
				}
				else
				{
					$value = empty($cur_profile[\'birthdate\']) ? \'0001-01-01\' : $cur_profile[\'birthdate\'];
					return false;
				}
			'),
		),
		'date_registered' => array(
			'type' => 'text',
			'value' => empty($cur_profile['date_registered']) ? $txt['not_applicable'] : strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
			'label' => $txt['date_registered'],
			'log_change' => true,
			'permission' => 'moderate_forum',
			'input_validate' => create_function('&$value', '
				global $txt, $user_info, $modSettings, $cur_profile, $context;

				// Bad date!  Go try again - please?
				if (($value = strtotime($value)) === -1)
				{
					$value = $cur_profile[\'date_registered\'];
					return $txt[\'invalid_registration\'] . \' \' . strftime(\'%d %b %Y \' . (strpos($user_info[\'time_format\'], \'%H\') !== false ? \'%I:%M:%S %p\' : \'%H:%M:%S\'), forum_time(false));
				}
				// As long as it doesn\'t equal "N/A"...
				elseif ($value != $txt[\'not_applicable\'] && $value != strtotime(strftime(\'%Y-%m-%d\', $cur_profile[\'date_registered\'] + ($user_info[\'time_offset\'] + $modSettings[\'time_offset\']) * 3600)))
					$value = $value - ($user_info[\'time_offset\'] + $modSettings[\'time_offset\']) * 3600;
				else
					$value = $cur_profile[\'date_registered\'];

				return true;
			'),
		),
		'email_address' => array(
			'type' => 'text',
			'label' => $txt['user_email_address'],
			'subtext' => $txt['valid_email'],
			'log_change' => true,
			'permission' => 'profile_identity',
			'input_validate' => create_function('&$value', '
				global $context, $old_profile, $context, $profile_vars, $sourcedir, $modSettings;

				if (strtolower($value) == strtolower($old_profile[\'email_address\']))
					return false;

				$isValid = profileValidateEmail($value, $context[\'id_member\']);

				// Do they need to revalidate? If so schedule the function!
				if ($isValid === true && !empty($modSettings[\'send_validation_onChange\']) && !allowedTo(\'moderate_forum\'))
				{
					require_once($sourcedir . \'/subs/Members.subs.php\');
					$profile_vars[\'validation_code\'] = generateValidationCode();
					$profile_vars[\'is_activated\'] = 2;
					$context[\'profile_execute_on_save\'][] = \'profileSendActivation\';
					unset($context[\'profile_execute_on_save\'][\'reload_user\']);
				}

				return $isValid;
			'),
		),
		'gender' => array(
			'type' => 'select',
			'cast_type' => 'int',
			'options' => 'return array(0 => \'\', 1 => $txt[\'male\'], 2 => $txt[\'female\']);',
			'label' => $txt['gender'],
			'permission' => 'profile_extra',
		),
		'hide_email' => array(
			'type' => 'check',
			'value' => empty($cur_profile['hide_email']) ? true : false,
			'label' => $txt['allow_user_email'],
			'permission' => 'profile_identity',
			'input_validate' => create_function('&$value', '
				$value = $value == 0 ? 1 : 0;

				return true;
			'),
		),
		// Selecting group membership is a complicated one so we treat it separate!
		'id_group' => array(
			'type' => 'callback',
			'callback_func' => 'group_manage',
			'permission' => 'manage_membergroups',
			'preload' => 'profileLoadGroups',
			'log_change' => true,
			'input_validate' => 'profileSaveGroups',
		),
		'id_theme' => array(
			'type' => 'callback',
			'callback_func' => 'theme_pick',
			'permission' => 'profile_extra',
			'enabled' => $modSettings['theme_allow'] || allowedTo('admin_forum'),
			'preload' => create_function('', '
				global $smcFunc, $context, $cur_profile, $txt;

				$request = $smcFunc[\'db_query\'](\'\', \'
					SELECT value
					FROM {db_prefix}themes
					WHERE id_theme = {int:id_theme}
						AND variable = {string:variable}
					LIMIT 1\', array(
						\'id_theme\' => $cur_profile[\'id_theme\'],
						\'variable\' => \'name\',
					)
				);
				list ($name) = $smcFunc[\'db_fetch_row\']($request);
				$smcFunc[\'db_free_result\']($request);

				$context[\'member\'][\'theme\'] = array(
					\'id\' => $cur_profile[\'id_theme\'],
					\'name\' => empty($cur_profile[\'id_theme\']) ? $txt[\'theme_forum_default\'] : $name
				);
				return true;
			'),
			'input_validate' => create_function('&$value', '
				$value = (int) $value;
				return true;
			'),
		),
		'karma_good' => array(
			'type' => 'callback',
			'callback_func' => 'karma_modify',
			'permission' => 'admin_forum',
			// Set karma_bad too!
			'input_validate' => create_function('&$value', '
				global $profile_vars, $cur_profile;

				$value = (int) $value;
				if (isset($_POST[\'karma_bad\']))
				{
					$profile_vars[\'karma_bad\'] = $_POST[\'karma_bad\'] != \'\' ? (int) $_POST[\'karma_bad\'] : 0;
					$cur_profile[\'karma_bad\'] = $_POST[\'karma_bad\'] != \'\' ? (int) $_POST[\'karma_bad\'] : 0;
				}
				return true;
			'),
			'preload' => create_function('', '
				global $context, $cur_profile;

				$context[\'member\'][\'karma\'][\'good\'] = $cur_profile[\'karma_good\'];
				$context[\'member\'][\'karma\'][\'bad\'] = $cur_profile[\'karma_bad\'];

				return true;
			'),
			'enabled' => !empty($modSettings['karmaMode']),
		),
		'lngfile' => array(
			'type' => 'select',
			'options' => 'return $context[\'profile_languages\'];',
			'label' => $txt['preferred_language'],
			'permission' => 'profile_identity',
			'preload' => 'profileLoadLanguages',
			'enabled' => !empty($modSettings['userLanguage']),
			'value' => empty($cur_profile['lngfile']) ? $language : $cur_profile['lngfile'],
			'input_validate' => create_function('&$value', '
				global $context, $cur_profile;

				// Load the languages.
				profileLoadLanguages();

				if (isset($context[\'profile_languages\'][$value]))
				{
					if ($context[\'user\'][\'is_owner\'] && empty($context[\'password_auth_failed\']))
						$_SESSION[\'language\'] = $value;
					return true;
				}
				else
				{
					$value = $cur_profile[\'lngfile\'];
					return false;
				}
			'),
		),
		'location' => array(
			'type' => 'text',
			'label' => $txt['location'],
			'log_change' => true,
			'size' => 50,
			'permission' => 'profile_extra',
		),
		// The username is not always editable - so adjust it as such.
		'member_name' => array(
			'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
			'label' => $txt['username'],
			'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '[<a href="' . $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=account;changeusername" style="font-style: italic;">' . $txt['username_change'] . '</a>]' : '',
			'log_change' => true,
			'permission' => 'profile_identity',
			'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="alert">' . $txt['username_warning'] . '</div>' : '',
			'input_validate' => create_function('&$value', '
				global $sourcedir, $context, $user_info, $cur_profile;

				if (allowedTo(\'admin_forum\'))
				{
					// We\'ll need this...
					require_once($sourcedir . \'/subs/Auth.subs.php\');

					// Maybe they are trying to change their password as well?
					$resetPassword = true;
					if (isset($_POST[\'passwrd1\']) && $_POST[\'passwrd1\'] != \'\' && isset($_POST[\'passwrd2\']) && $_POST[\'passwrd1\'] == $_POST[\'passwrd2\'] && validatePassword($_POST[\'passwrd1\'], $value, array($cur_profile[\'real_name\'], $user_info[\'username\'], $user_info[\'name\'], $user_info[\'email\'])) == null)
						$resetPassword = false;

					// Do the reset... this will send them an email too.
					if ($resetPassword)
						resetPassword($context[\'id_member\'], $value);
					elseif ($value !== null)
					{
						validateUsername($context[\'id_member\'], $value);
						updateMemberData($context[\'id_member\'], array(\'member_name\' => $value));
					}
				}
				return false;
			'),
		),
		'passwrd1' => array(
			'type' => 'password',
			'label' => ucwords($txt['choose_pass']),
			'subtext' => $txt['password_strength'],
			'size' => 20,
			'value' => '',
			'enabled' => empty($cur_profile['openid_uri']),
			'permission' => 'profile_identity',
			'save_key' => 'passwd',
			// Note this will only work if passwrd2 also exists!
			'input_validate' => create_function('&$value', '
				global $sourcedir, $user_info, $smcFunc, $cur_profile;

				// If we didn\'t try it then ignore it!
				if ($value == \'\')
					return false;

				// Do the two entries for the password even match?
				if (!isset($_POST[\'passwrd2\']) || $value != $_POST[\'passwrd2\'])
					return \'bad_new_password\';

				// Let\'s get the validation function into play...
				require_once($sourcedir . \'/subs/Auth.subs.php\');
				$passwordErrors = validatePassword($value, $cur_profile[\'member_name\'], array($cur_profile[\'real_name\'], $user_info[\'username\'], $user_info[\'name\'], $user_info[\'email\']));

				// Were there errors?
				if ($passwordErrors != null)
					return \'password_\' . $passwordErrors;

				// Set up the new password variable... ready for storage.
				$value = sha1(strtolower($cur_profile[\'member_name\']) . un_htmlspecialchars($value));
				return true;
			'),
		),
		'passwrd2' => array(
			'type' => 'password',
			'label' => ucwords($txt['verify_pass']),
			'enabled' => empty($cur_profile['openid_uri']),
			'size' => 20,
			'value' => '',
			'permission' => 'profile_identity',
			'is_dummy' => true,
		),
		'personal_text' => array(
			'type' => 'text',
			'label' => $txt['personal_text'],
			'log_change' => true,
			'input_attr' => array('maxlength="50"'),
			'size' => 50,
			'permission' => 'profile_extra',
			'input_validate' => create_function('&$value', '
				global $smcFunc;

				if ($smcFunc[\'strlen\']($value) > 50)
					return \'personal_text_too_long\';

				return true;
			'),
		),
		// This does ALL the pm settings
		'pm_prefs' => array(
			'type' => 'callback',
			'callback_func' => 'pm_settings',
			'permission' => 'pm_read',
			'preload' => create_function('', '
				global $context, $cur_profile;

				$context[\'display_mode\'] = $cur_profile[\'pm_prefs\'] & 3;
				$context[\'send_email\'] = $cur_profile[\'pm_email_notify\'];
				$context[\'receive_from\'] = !empty($cur_profile[\'pm_receive_from\']) ? $cur_profile[\'pm_receive_from\'] : 0;

				return true;
			'),
			'input_validate' => create_function('&$value', '
				global $cur_profile, $profile_vars;

				// Simple validate and apply the two "sub settings"
				$value = max(min($value, 2), 0);

				$cur_profile[\'pm_email_notify\'] = $profile_vars[\'pm_email_notify\'] = max(min((int) $_POST[\'pm_email_notify\'], 2), 0);
				$cur_profile[\'pm_receive_from\'] = $profile_vars[\'pm_receive_from\'] = max(min((int) $_POST[\'pm_receive_from\'], 4), 0);

				return true;
			'),
		),
		'posts' => array(
			'type' => 'int',
			'label' => $txt['profile_posts'],
			'log_change' => true,
			'size' => 7,
			'permission' => 'moderate_forum',
			'input_validate' => create_function('&$value', '
				if (!is_numeric($value))
					return \'digits_only\';
				else
					$value = $value != \'\' ? strtr($value, array(\',\' => \'\', \'.\' => \'\', \' \' => \'\')) : 0;
				return true;
			'),
		),
		'real_name' => array(
			'type' => !empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum') ? 'text' : 'label',
			'label' => $txt['name'],
			'subtext' => $txt['display_name_desc'],
			'log_change' => true,
			'input_attr' => array('maxlength="60"'),
			'permission' => 'profile_identity',
			'enabled' => !empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum'),
			'input_validate' => create_function('&$value', '
				global $context, $smcFunc, $sourcedir, $cur_profile;

				$value = trim(preg_replace(\'~[\s]~u\', \' \', $value));

				if (trim($value) == \'\')
					return \'no_name\';
				elseif ($smcFunc[\'strlen\']($value) > 60)
					return \'name_too_long\';
				elseif ($cur_profile[\'real_name\'] != $value)
				{
					require_once($sourcedir . \'/subs/Members.subs.php\');
					if (isReservedName($value, $context[\'id_member\']))
						return \'name_taken\';
				}
				return true;
			'),
		),
		'secret_question' => array(
			'type' => 'text',
			'label' => $txt['secret_question'],
			'subtext' => $txt['secret_desc'],
			'size' => 50,
			'permission' => 'profile_identity',
		),
		'secret_answer' => array(
			'type' => 'text',
			'label' => $txt['secret_answer'],
			'subtext' => $txt['secret_desc2'],
			'size' => 20,
			'postinput' => '<span class="smalltext" style="margin-left: 4ex;">[<a href="' . $scripturl . '?action=quickhelp;help=secret_why_blank" onclick="return reqOverlayDiv(this.href);">' . $txt['secret_why_blank'] . '</a>]</span>',
			'value' => '',
			'permission' => 'profile_identity',
			'input_validate' => create_function('&$value', '
				$value = $value != \'\' ? md5($value) : \'\';
				return true;
			'),
		),
		'signature' => array(
			'type' => 'callback',
			'callback_func' => 'signature_modify',
			'permission' => 'profile_extra',
			'enabled' => substr($modSettings['signature_settings'], 0, 1) == 1,
			'preload' => 'profileLoadSignatureData',
			'input_validate' => 'profileValidateSignature',
		),
		'show_online' => array(
			'type' => 'check',
			'label' => $txt['show_online'],
			'permission' => 'profile_identity',
			'enabled' => !empty($modSettings['allow_hideOnline']) || allowedTo('moderate_forum'),
		),
		'smiley_set' => array(
			'type' => 'callback',
			'callback_func' => 'smiley_pick',
			'enabled' => !empty($modSettings['smiley_sets_enable']),
			'permission' => 'profile_extra',
			'preload' => create_function('', '
				global $modSettings, $context, $txt, $cur_profile;

				$context[\'member\'][\'smiley_set\'][\'id\'] = empty($cur_profile[\'smiley_set\']) ? \'\' : $cur_profile[\'smiley_set\'];
				$context[\'smiley_sets\'] = explode(\',\', \'none,,\' . $modSettings[\'smiley_sets_known\']);
				$set_names = explode("\n", $txt[\'smileys_none\'] . "\n" . $txt[\'smileys_forum_board_default\'] . "\n" . $modSettings[\'smiley_sets_names\']);
				foreach ($context[\'smiley_sets\'] as $i => $set)
				{
					$context[\'smiley_sets\'][$i] = array(
						\'id\' => htmlspecialchars($set),
						\'name\' => htmlspecialchars($set_names[$i]),
						\'selected\' => $set == $context[\'member\'][\'smiley_set\'][\'id\']
					);

					if ($context[\'smiley_sets\'][$i][\'selected\'])
						$context[\'member\'][\'smiley_set\'][\'name\'] = $set_names[$i];
				}
				return true;
			'),
			'input_validate' => create_function('&$value', '
				global $modSettings;

				$smiley_sets = explode(\',\', $modSettings[\'smiley_sets_known\']);
				if (!in_array($value, $smiley_sets) && $value != \'none\')
					$value = \'\';
				return true;
			'),
		),
		// Pretty much a dummy entry - it populates all the theme settings.
		'theme_settings' => array(
			'type' => 'callback',
			'callback_func' => 'theme_settings',
			'permission' => 'profile_extra',
			'is_dummy' => true,
			'preload' => create_function('', '
				global $context, $user_info;

				loadLanguage(\'Settings\');

				$context[\'allow_no_censored\'] = false;
				if ($user_info[\'is_admin\'] || $context[\'user\'][\'is_owner\'])
					$context[\'allow_no_censored\'] = allowedTo(\'disable_censor\');

				return true;
			'),
		),
		'time_format' => array(
			'type' => 'callback',
			'callback_func' => 'timeformat_modify',
			'permission' => 'profile_extra',
			'preload' => create_function('', '
				global $context, $user_info, $txt, $cur_profile, $modSettings;

				$context[\'easy_timeformats\'] = array(
					array(\'format\' => \'\', \'title\' => $txt[\'timeformat_default\']),
					array(\'format\' => \'%B %d, %Y, %I:%M:%S %p\', \'title\' => $txt[\'timeformat_easy1\']),
					array(\'format\' => \'%B %d, %Y, %H:%M:%S\', \'title\' => $txt[\'timeformat_easy2\']),
					array(\'format\' => \'%Y-%m-%d, %H:%M:%S\', \'title\' => $txt[\'timeformat_easy3\']),
					array(\'format\' => \'%d %B %Y, %H:%M:%S\', \'title\' => $txt[\'timeformat_easy4\']),
					array(\'format\' => \'%d-%m-%Y, %H:%M:%S\', \'title\' => $txt[\'timeformat_easy5\'])
				);

				$context[\'member\'][\'time_format\'] = $cur_profile[\'time_format\'];
				$context[\'current_forum_time\'] = timeformat(time() - $user_info[\'time_offset\'] * 3600, false);
				$context[\'current_forum_time_js\'] = strftime(\'%Y,\' . ((int) strftime(\'%m\', time() + $modSettings[\'time_offset\'] * 3600) - 1) . \',%d,%H,%M,%S\', time() + $modSettings[\'time_offset\'] * 3600);
				$context[\'current_forum_time_hour\'] = (int) strftime(\'%H\', forum_time(false));
				return true;
			'),
		),
		'time_offset' => array(
			'type' => 'callback',
			'callback_func' => 'timeoffset_modify',
			'permission' => 'profile_extra',
			'preload' => create_function('', '
				global $context, $cur_profile;
				$context[\'member\'][\'time_offset\'] = $cur_profile[\'time_offset\'];
				return true;
			'),
			'input_validate' => create_function('&$value', '
				// Validate the time_offset...
				$value = (float) strtr($value, \',\', \'.\');

				if ($value < -23.5 || $value > 23.5)
					return \'bad_offset\';

				return true;
			'),
		),
		'usertitle' => array(
			'type' => 'text',
			'label' => $txt['custom_title'],
			'log_change' => true,
			'input_attr' => array('maxlength="50"'),
			'size' => 50,
			'permission' => 'profile_title',
			'enabled' => !empty($modSettings['titlesEnable']),
			'input_validate' => create_function('&$value', '
				global $smcFunc;

				if ($smcFunc[\'strlen\'] > 50)
					return \'user_title_too_long\';

				return true;
			'),
		),
		'website_title' => array(
			'type' => 'text',
			'label' => $txt['website_title'],
			'subtext' => $txt['include_website_url'],
			'size' => 50,
			'permission' => 'profile_extra',
			'link_with' => 'website',
		),
		'website_url' => array(
			'type' => 'text',
			'label' => $txt['website_url'],
			'subtext' => $txt['complete_url'],
			'size' => 50,
			'permission' => 'profile_extra',
			// Fix the URL...
			'input_validate' => create_function('&$value', '

				if (strlen(trim($value)) > 0 && strpos($value, \'://\') === false)
					$value = \'http://\' . $value;
				if (strlen($value) < 8 || (substr($value, 0, 7) !== \'http://\' && substr($value, 0, 8) !== \'https://\'))
					$value = \'\';
				return true;
			'),
			'link_with' => 'website',
		),
	);

	call_integration_hook('integrate_load_profile_fields', array($profile_fields));

	$disabled_fields = !empty($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
	// For each of the above let's take out the bits which don't apply - to save memory and security!
	foreach ($profile_fields as $key => $field)
	{
		// Do we have permission to do this?
		if (isset($field['permission']) && !allowedTo(($context['user']['is_owner'] ? array($field['permission'] . '_own', $field['permission'] . '_any') : $field['permission'] . '_any')) && !allowedTo($field['permission']))
			unset($profile_fields[$key]);

		// Is it enabled?
		if (isset($field['enabled']) && !$field['enabled'])
			unset($profile_fields[$key]);

		// Is it specifically disabled?
		if (in_array($key, $disabled_fields) || (isset($field['link_with']) && in_array($field['link_with'], $disabled_fields)))
			unset($profile_fields[$key]);
	}
}

/**
 * Save the profile changes.
 */
function saveProfileFields()
{
	global $profile_fields, $profile_vars, $context, $old_profile;
	global $post_errors, $sourcedir, $librarydir, $modSettings, $cur_profile, $smcFunc;

	// Load them up.
	loadProfileFields();

	// This makes things easier...
	$old_profile = $cur_profile;

	// This allows variables to call activities when they save
	// - by default just to reload their settings
	$context['profile_execute_on_save'] = array();
	if ($context['user']['is_owner'])
		$context['profile_execute_on_save']['reload_user'] = 'profileReloadUser';

	// Assume we log nothing.
	$context['log_changes'] = array();

	// Cycle through the profile fields working out what to do!
	foreach ($profile_fields as $key => $field)
	{
		if (!isset($_POST[$key]) || !empty($field['is_dummy']) || (isset($_POST['preview_signature']) && $key == 'signature'))
			continue;

		// What gets updated?
		$db_key = isset($field['save_key']) ? $field['save_key'] : $key;

		// Right - we have something that is enabled, we can act upon and has a value posted to it. Does it have a validation function?
		if (isset($field['input_validate']))
		{
			$is_valid = $field['input_validate']($_POST[$key]);
			// An error occured - set it as such!
			if ($is_valid !== true)
			{
				// Is this an actual error?
				if ($is_valid !== false)
				{
					$post_errors[$key] = $is_valid;
					$profile_fields[$key]['is_error'] = $is_valid;
				}
				// Retain the old value.
				$cur_profile[$key] = $_POST[$key];
				continue;
			}
		}

		// Are we doing a cast?
		$field['cast_type'] = empty($field['cast_type']) ? $field['type'] : $field['cast_type'];

		// Finally, clean up certain types.
		if ($field['cast_type'] == 'int')
			$_POST[$key] = (int) $_POST[$key];
		elseif ($field['cast_type'] == 'float')
			$_POST[$key] = (float) $_POST[$key];
		elseif ($field['cast_type'] == 'check')
			$_POST[$key] = !empty($_POST[$key]) ? 1 : 0;

		// If we got here we're doing OK.
		if ($field['type'] != 'hidden' && (!isset($old_profile[$key]) || $_POST[$key] != $old_profile[$key]))
		{
			// Set the save variable.
			$profile_vars[$db_key] = $_POST[$key];
			// And update the user profile.
			$cur_profile[$key] = $_POST[$key];

			// Are we logging it?
			if (!empty($field['log_change']) && isset($old_profile[$key]))
				$context['log_changes'][$key] = array(
					'previous' => $old_profile[$key],
					'new' => $_POST[$key],
				);
		}

		// Logging group changes are a bit different...
		if ($key == 'id_group' && $field['log_change'])
		{
			profileLoadGroups();

			// Any changes to primary group?
			if ($_POST['id_group'] != $old_profile['id_group'])
			{
				$context['log_changes']['id_group'] = array(
					'previous' => !empty($old_profile[$key]) && isset($context['member_groups'][$old_profile[$key]]) ? $context['member_groups'][$old_profile[$key]]['name'] : '',
					'new' => !empty($_POST[$key]) && isset($context['member_groups'][$_POST[$key]]) ? $context['member_groups'][$_POST[$key]]['name'] : '',
				);
			}

			// Prepare additional groups for comparison.
			$additional_groups = array(
				'previous' => !empty($old_profile['additional_groups']) ? explode(',', $old_profile['additional_groups']) : array(),
				'new' => !empty($_POST['additional_groups']) ? array_diff($_POST['additional_groups'], array(0)) : array(),
			);

			sort($additional_groups['previous']);
			sort($additional_groups['new']);

			// What about additional groups?
			if ($additional_groups['previous'] != $additional_groups['new'])
			{
				foreach ($additional_groups as $type => $groups)
				{
					foreach ($groups as $id => $group)
					{
						if (isset($context['member_groups'][$group]))
							$additional_groups[$type][$id] = $context['member_groups'][$group]['name'];
						else
							unset($additional_groups[$type][$id]);
					}
					$additional_groups[$type] = implode(', ', $additional_groups[$type]);
				}

				$context['log_changes']['additional_groups'] = $additional_groups;
			}
		}
	}

	// @todo Temporary
	if ($context['user']['is_owner'])
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own'));
	else
		$changeOther = allowedTo('profile_extra_any');
	if ($changeOther && empty($post_errors))
	{
		makeThemeChanges($context['id_member'], isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
		if (!empty($_REQUEST['sa']))
			makeCustomFieldChanges($context['id_member'], $_REQUEST['sa'], false);
	}

	// Free memory!
	unset($profile_fields);
}

/**
 * Validate an email address.
 *
 * @param string $email
 * @param int $memID = 0
 * @return boolean|string
 */
function profileValidateEmail($email, $memID = 0)
{
	global $smcFunc, $context;

	$email = strtr($email, array('&#039;' => '\''));

	// Check the name and email for validity.
	if (trim($email) == '')
		return 'no_email';
	if (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $email) == 0)
		return 'bad_email';

	// Email addresses should be and stay unique.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . ($memID != 0 ? 'id_member != {int:selected_member} AND ' : '') . '
			email_address = {string:email_address}
		LIMIT 1',
		array(
			'selected_member' => $memID,
			'email_address' => $email,
		)
	);

	if ($smcFunc['db_num_rows']($request) > 0)
		return 'email_taken';
	$smcFunc['db_free_result']($request);

	return true;
}

/**
 * Save the profile changes
 *
 * @param array &$profile_variables
 * @param array &$post_errors
 * @param int $memID id_member
 */
function saveProfileChanges(&$profile_vars, &$post_errors, $memID)
{
	global $user_info, $txt, $modSettings, $user_profile;
	global $context, $settings, $sourcedir;
	global $smcFunc;

	// These make life easier....
	$old_profile = &$user_profile[$memID];

	// Permissions...
	if ($context['user']['is_owner'])
	{
		$changeIdentity = allowedTo(array('profile_identity_any', 'profile_identity_own'));
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own'));
	}
	else
	{
		$changeIdentity = allowedTo('profile_identity_any');
		$changeOther = allowedTo('profile_extra_any');
	}

	// Arrays of all the changes - makes things easier.
	$profile_bools = array(
		'notify_announcements', 'notify_send_body',
	);
	$profile_ints = array(
		'notify_regularity',
		'notify_types',
	);
	$profile_floats = array(
	);
	$profile_strings = array(
		'buddy_list',
		'ignore_boards',
	);

	if (isset($_POST['sa']) && $_POST['sa'] == 'ignoreboards' && empty($_POST['ignore_brd']))
		$_POST['ignore_brd'] = array();

	unset($_POST['ignore_boards']); // Whatever it is set to is a dirty fithy thing.  Kinda like our minds.
	if (isset($_POST['ignore_brd']))
	{
		if (!is_array($_POST['ignore_brd']))
			$_POST['ignore_brd'] = array ($_POST['ignore_brd']);

		foreach ($_POST['ignore_brd'] as $k => $d)
		{
			$d = (int) $d;
			if ($d != 0)
				$_POST['ignore_brd'][$k] = $d;
			else
				unset($_POST['ignore_brd'][$k]);
		}
		$_POST['ignore_boards'] = implode(',', $_POST['ignore_brd']);
		unset($_POST['ignore_brd']);

	}

	// Here's where we sort out all the 'other' values...
	if ($changeOther)
	{
		makeThemeChanges($memID, isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
		//makeAvatarChanges($memID, $post_errors);
		makeNotificationChanges($memID);
		if (!empty($_REQUEST['sa']))
			makeCustomFieldChanges($memID, $_REQUEST['sa'], false);

		foreach ($profile_bools as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = empty($_POST[$var]) ? '0' : '1';
		foreach ($profile_ints as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var] != '' ? (int) $_POST[$var] : '';
		foreach ($profile_floats as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = (float) $_POST[$var];
		foreach ($profile_strings as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var];
	}
}

/**
 * Make any theme changes that are sent with the profile.
 *
 * @param int $memID
 * @param int $id_theme
 */
function makeThemeChanges($memID, $id_theme)
{
	global $modSettings, $smcFunc, $context, $user_info;

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
	if ((isset($_POST['options']) && count(array_intersect(array_keys($_POST['options']), $reservedVars)) != 0) || (isset($_POST['default_options']) && count(array_intersect(array_keys($_POST['default_options']), $reservedVars)) != 0))
		fatal_lang_error('no_access', false);

	// Don't allow any overriding of custom fields with default or non-default options.
	$request = $smcFunc['db_query']('', '
		SELECT col_name
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}',
		array(
			'is_active' => 1,
		)
	);
	$custom_fields = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$custom_fields[] = $row['col_name'];
	$smcFunc['db_free_result']($request);

	// These are the theme changes...
	$themeSetArray = array();
	if (isset($_POST['options']) && is_array($_POST['options']))
	{
		foreach ($_POST['options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
				$val = max(0, min($val, 50));
			// We don't set this per theme anymore.
			elseif ($opt == 'allow_no_censored')
				continue;

			$themeSetArray[] = array($memID, $id_theme, $opt, is_array($val) ? implode(',', $val) : $val);
		}
	}

	$erase_options = array();
	if (isset($_POST['default_options']) && is_array($_POST['default_options']))
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
				$val = max(0, min($val, 50));
			// Only let admins and owners change the censor.
			elseif ($opt == 'allow_no_censored' && !$user_info['is_admin'] && !$context['user']['is_owner'])
					continue;

			$themeSetArray[] = array($memID, 1, $opt, is_array($val) ? implode(',', $val) : $val);
			$erase_options[] = $opt;
		}

	// If themeSetArray isn't still empty, send it to the database.
	if (empty($context['password_auth_failed']))
	{
		if (!empty($themeSetArray))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$themeSetArray,
				array('id_member', 'id_theme', 'variable')
			);
		}

		if (!empty($erase_options))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}themes
				WHERE id_theme != {int:id_theme}
					AND variable IN ({array_string:erase_variables})
					AND id_member = {int:id_member}',
				array(
					'id_theme' => 1,
					'id_member' => $memID,
					'erase_variables' => $erase_options
				)
			);
		}

		$themes = explode(',', $modSettings['knownThemes']);
		foreach ($themes as $t)
			cache_put_data('theme_settings-' . $t . ':' . $memID, null, 60);
	}
}

/**
 * Make any notification changes that need to be made.
 *
 * @param int $memID id_member
 */
function makeNotificationChanges($memID)
{
	global $smcFunc;

	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']) && !empty($_POST['notify_boards']))
	{
		// Make sure only integers are deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
			$_POST['notify_boards'][$index] = (int) $id;

		// id_board = 0 is reserved for topic notifications.
		$_POST['notify_boards'] = array_diff($_POST['notify_boards'], array(0));

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:selected_member}',
			array(
				'board_list' => $_POST['notify_boards'],
				'selected_member' => $memID,
			)
		);
	}

	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		foreach ($_POST['notify_topics'] as $index => $id)
			$_POST['notify_topics'][$index] = (int) $id;

		// Make sure there are no zeros left.
		$_POST['notify_topics'] = array_diff($_POST['notify_topics'], array(0));

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:selected_member}',
			array(
				'topic_list' => $_POST['notify_topics'],
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
	global $context, $smcFunc, $user_profile, $user_info, $modSettings, $sourcedir;

	if ($sanitize && isset($_POST['customfield']))
		$_POST['customfield'] = htmlspecialchars__recursive($_POST['customfield']);

	$where = $area == 'register' ? 'show_reg != 0' : 'show_profile = {string:area}';

	// Load the fields we are saving too - make sure we save valid data (etc).
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_desc, field_type, field_length, field_options, default_value, show_reg, mask, private
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		/* This means don't save if:
			- The user is NOT an admin.
			- The data is not freely viewable and editable by users.
			- The data is not invisible to users but editable by the owner (or if it is the user is not the owner)
			- The area isn't registration, and if it is that the field is not suppossed to be shown there.
		*/
		if ($row['private'] != 0 && !allowedTo('admin_forum') && ($memID != $user_info['id'] || $row['private'] != 2) && ($area != 'register' || $row['show_reg'] == 0))
			continue;

		// Validate the user data.
		if ($row['field_type'] == 'check')
			$value = isset($_POST['customfield'][$row['col_name']]) ? 1 : 0;
		elseif ($row['field_type'] == 'select' || $row['field_type'] == 'radio')
		{
			$value = $row['default_value'];
			foreach (explode(',', $row['field_options']) as $k => $v)
				if (isset($_POST['customfield'][$row['col_name']]) && $_POST['customfield'][$row['col_name']] == $k)
					$value = $v;
		}
		// Otherwise some form of text!
		else
		{
			$value = isset($_POST['customfield'][$row['col_name']]) ? $_POST['customfield'][$row['col_name']] : '';
			if ($row['field_length'])
				$value = $smcFunc['substr']($value, 0, $row['field_length']);

			// Any masks?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				// @todo We never error on this - just ignore it at the moment...
				if ($row['mask'] == 'email' && (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $value) === 0 || strlen($value) > 255))
					$value = '';
				elseif ($row['mask'] == 'number')
				{
					$value = (int) $value;
				}
				elseif (substr($row['mask'], 0, 5) == 'regex' && preg_match(substr($row['mask'], 5), $value) === 0)
					$value = '';
			}
		}

		// Did it change?
		if (!isset($user_profile[$memID]['options'][$row['col_name']]) || $user_profile[$memID]['options'][$row['col_name']] != $value)
		{
			$log_changes[] = array(
				'action' => 'customfield_' . $row['col_name'],
				'log_type' => 'user',
				'extra' => array(
					'previous' => !empty($user_profile[$memID]['options'][$row['col_name']]) ? $user_profile[$memID]['options'][$row['col_name']] : '',
					'new' => $value,
					'applicator' => $user_info['id'],
					'member_affected' => $memID,
				),
			);
			$changes[] = array(1, $row['col_name'], $value, $memID);
			$user_profile[$memID]['options'][$row['col_name']] = $value;
		}
	}
	$smcFunc['db_free_result']($request);

	call_integration_hook('integrate_save_custom_profile_fields', array($changes, $log_changes, $memID, $area, $sanitize));

	// Make those changes!
	if (!empty($changes) && empty($context['password_auth_failed']))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534', 'id_member' => 'int'),
			$changes,
			array('id_theme', 'variable', 'id_member')
		);
		if (!empty($log_changes) && !empty($modSettings['modlog_enabled']))
		{
			require_once($sourcedir . '/Logging.php');
			logActions($log_changes);
		}
	}
}