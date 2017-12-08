<?php

/**
 * Functions to support the profile controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.1
 *
 */

/**
 * Find the ID of the "current" member
 *
 * @param boolean $fatal if the function ends in a fatal error in case of problems (default true)
 * @param boolean $reload_id if true the already set value is ignored (default false)
 *
 * @return int if no error.  May return false in case of problems only if $fatal is set to false
 * @throws Elk_Exception not_a_user
 */
function currentMemberID($fatal = true, $reload_id = false)
{
	global $user_info;
	static $memID;

	// If we already know who we're dealing with
	if (isset($memID) && !$reload_id)
		return $memID;

	// Did we get the user by name...
	if (isset($_REQUEST['user']))
		$memberResult = loadMemberData($_REQUEST['user'], true, 'profile');
	// ... or by id_member?
	elseif (!empty($_REQUEST['u']))
		$memberResult = loadMemberData((int) $_REQUEST['u'], false, 'profile');
	// If it was just ?action=profile, edit your own profile.
	else
		$memberResult = loadMemberData($user_info['id'], false, 'profile');

	// Check if loadMemberData() has returned a valid result.
	if (!is_array($memberResult))
	{
		// Members only...
		is_not_guest('', $fatal);

		if ($fatal)
			throw new Elk_Exception('not_a_user', false);
		else
			return false;
	}

	// If all went well, we have a valid member ID!
	list ($memID) = $memberResult;

	return $memID;
}

/**
 * Setup the context for a page load!
 *
 * @param mixed[] $fields
 * @param string $hook a string that represent the hook that can be used to operate on $fields
 */
function setupProfileContext($fields, $hook = '')
{
	global $profile_fields, $context, $cur_profile, $txt;

	if (!empty($hook))
		call_integration_hook('integrate_' . $hook . '_profile_fields', array(&$fields));

	// Make sure we have this!
	loadProfileFields(true);

	// First check for any linked sets.
	foreach ($profile_fields as $key => $field)
		if (isset($field['link_with']) && in_array($field['link_with'], $fields))
			$fields[] = $key;

	// Some default bits.
	$context['profile_prehtml'] = '';
	$context['profile_posthtml'] = '';
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
			if ($cur_field['type'] !== 'callback' && $cur_field['type'] !== 'hidden')
			{
				if (!isset($cur_field['label']))
					$cur_field['label'] = isset($txt[$field]) ? $txt[$field] : $field;

				// Everything has a value!
				if (!isset($cur_field['value']))
					$cur_field['value'] = isset($cur_profile[$field]) ? $cur_profile[$field] : '';

				// Any input attributes?
				$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
			}

			// Was there an error with this field on posting?
			if (isset($context['post_errors'][$field]))
				$cur_field['is_error'] = true;

			// Any javascript stuff?
			if (!empty($cur_field['js_submit']))
				$context['profile_onsubmit_javascript'] .= $cur_field['js_submit'];
			if (!empty($cur_field['js']))
				theme()->addInlineJavascript($cur_field['js']);
			if (!empty($cur_field['js_load']))
				loadJavascriptFile($cur_field['js_load']);

			// Any template stuff?
			if (!empty($cur_field['prehtml']))
				$context['profile_prehtml'] .= $cur_field['prehtml'];
			if (!empty($cur_field['posthtml']))
				$context['profile_posthtml'] .= $cur_field['posthtml'];

			// Finally put it into context?
			if ($cur_field['type'] !== 'hidden')
			{
				$last_type = $cur_field['type'];
				$context['profile_fields'][$field] = &$profile_fields[$field];
			}
		}
		// Bodge in a line break - without doing two in a row ;)
		elseif ($field === 'hr' && $last_type !== 'hr' && $last_type != '')
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
 * @param mixed[] $custom_fields = array()
 */
function loadCustomFields($memID, $area = 'summary', array $custom_fields = array())
{
	global $context, $txt, $user_profile, $user_info, $settings, $scripturl;

	$db = database();

	// Get the right restrictions in place...
	$where = 'active = 1';
	if (!allowedTo('admin_forum') && $area !== 'register')
	{
		// If it's the owner they can see two types of private fields, regardless.
		if ($memID == $user_info['id'])
			$where .= $area === 'summary' ? ' AND private < 3' : ' AND (private = 0 OR private = 2)';
		else
			$where .= $area === 'summary' ? ' AND private < 2' : ' AND private = 0';
	}

	if ($area === 'register')
		$where .= ' AND show_reg != 0';
	elseif ($area !== 'summary')
		$where .= ' AND show_profile = {string:area}';

	// Load all the relevant fields - and data.
	// The fully-qualified name for rows is here because it's a reserved word in Mariadb 10.2.4+ and quoting would be different for MySQL/Mariadb and PSQL
	$request = $db->query('', '
		SELECT
			col_name, field_name, field_desc, field_type, show_reg, field_length, field_options,
			default_value, bbc, enclose, placement, mask, vieworder, {db_prefix}custom_fields.rows, cols
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
		ORDER BY vieworder ASC',
		array(
			'area' => $area,
		)
	);
	$context['custom_fields'] = array();
	$context['custom_fields_required'] = false;
	$bbc_parser = \BBC\ParserWrapper::instance();
	while ($row = $db->fetch_assoc($request))
	{
		// Shortcut.
		$exists = $memID && isset($user_profile[$memID], $user_profile[$memID]['options'][$row['col_name']]);
		$value = $exists ? $user_profile[$memID]['options'][$row['col_name']] : $row['default_value'];

		// If this was submitted already then make the value the posted version.
		if (!empty($custom_fields) && isset($custom_fields[$row['col_name']]))
		{
			$value = Util::htmlspecialchars($custom_fields[$row['col_name']]);
			if (in_array($row['field_type'], array('select', 'radio')))
				$value = ($options = explode(',', $row['field_options'])) && isset($options[$value]) ? $options[$value] : '';
		}

		// HTML for the input form.
		$output_html = $value;

		// Checkbox inputs
		if ($row['field_type'] === 'check')
		{
			$true = (bool) $value;
			$input_html = '<input id="' . $row['col_name'] . '" type="checkbox" name="customfield[' . $row['col_name'] . ']" ' . ($true ? 'checked="checked"' : '') . ' class="input_check" />';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		// A select list
		elseif ($row['field_type'] === 'select')
		{
			$input_html = '<select id="' . $row['col_name'] . '" name="customfield[' . $row['col_name'] . ']"><option value=""' . ($row['default_value'] === 'no_default' ? ' selected="selected"' : '') . '></option>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = ($value == $v);
				$input_html .= '<option value="' . $k . '"' . ($true ? ' selected="selected"' : '') . '>' . $v . '</option>';
				if ($true)
					$output_html = $v;
			}

			$input_html .= '</select>';
		}
		// Radio buttons
		elseif ($row['field_type'] === 'radio')
		{
			$input_html = '<fieldset>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = ($value == $v);
				$input_html .= '<label for="customfield_' . $row['col_name'] . '_' . $k . '"><input type="radio" name="customfield[' . $row['col_name'] . ']" class="input_radio" id="customfield_' . $row['col_name'] . '_' . $k . '" value="' . $k . '" ' . ($true ? 'checked="checked"' : '') . ' />' . $v . '</label><br />';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		// A standard input field, including some html5 variants
		elseif (in_array($row['field_type'], array('text', 'url', 'search', 'date', 'email', 'color')))
		{
			$input_html = '<input id="' . $row['col_name'] . '" type="' . $row['field_type'] . '" name="customfield[' . $row['col_name'] . ']" ' . ($row['field_length'] != 0 ? 'maxlength="' . $row['field_length'] . '"' : '') . ' size="' . ($row['field_length'] == 0 || $row['field_length'] >= 50 ? 50 : ($row['field_length'] > 30 ? 30 : ($row['field_length'] > 10 ? 20 : 10))) . '" value="' . $value . '" class="input_text" />';
		}
		// Only thing left, a textbox for you
		else
		{
			$input_html = '<textarea id="' . $row['col_name'] . '" name="customfield[' . $row['col_name'] . ']" ' . (!empty($rows) ? 'rows="' . $row['rows'] . '"' : '') . ' ' . (!empty($cols) ? 'cols="' . $row['cols'] . '"' : '') . '>' . $value . '</textarea>';
		}

		// Parse BBCode
		if ($row['bbc'])
			$output_html = $bbc_parser->parseCustomFields($output_html);
		// Allow for newlines at least
		elseif ($row['field_type'] === 'textarea')
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
			'field_type' => $row['field_type'],
			'input_html' => $input_html,
			'output_html' => $output_html,
			'placement' => $row['placement'],
			'colname' => $row['col_name'],
			'value' => $value,
			'show_reg' => $row['show_reg'],
			'field_length' => $row['field_length'],
			'mask' => $row['mask'],
		);
		$context['custom_fields_required'] = $context['custom_fields_required'] || $row['show_reg'];
	}

	$db->free_result($request);

	call_integration_hook('integrate_load_custom_profile_fields', array($memID, $area));
}

/**
 * This defines every profile field known to man.
 *
 * @param bool $force_reload = false
 */
function loadProfileFields($force_reload = false)
{
	global $context, $profile_fields, $txt, $scripturl, $modSettings, $user_info, $cur_profile, $language, $settings;

	// Don't load this twice!
	if (!empty($profile_fields) && !$force_reload)
		return;

	/**
	 * This horrific array defines all the profile fields in the whole world!
	 * In general each "field" has one array - the key of which is the database
	 * column name associated with said field.
	 *
	 * Each item can have the following attributes:
	 *
	 * string $type: The type of field this is - valid types are:
	 *   - callback: This is a field which has its own callback mechanism for templating.
	 *   - check:    A simple checkbox.
	 *   - hidden:   This doesn't have any visual aspects but may have some validity.
	 *   - password: A password box.
	 *   - select:   A select box.
	 *   - text:     A string of some description.
	 *
	 * string $label:       The label for this item - default will be $txt[$key] if this isn't set.
	 * string $subtext:     The subtext (Small label) for this item.
	 * int $size:           Optional size for a text area.
	 * array $input_attr:   An array of text strings to be added to the input box for this item.
	 * string $value:       The value of the item. If not set $cur_profile[$key] is assumed.
	 * string $permission:  Permission required for this item (Excluded _any/_own suffix which is applied automatically).
	 * func $input_validate: A runtime function which validates the element before going to the database. It is passed
	 *                       the relevant $_POST element if it exists and should be treated like a reference.
	 *
	 * Return types:
	 *   - true:          Element can be stored.
	 *   - false:         Skip this element.
	 *   - a text string: An error occurred - this is the error message.
	 *
	 * function $preload: A function that is used to load data required for this element to be displayed. Must return
	 *                    true to be displayed at all.
	 *
	 * string $cast_type: If set casts the element to a certain type. Valid types (bool, int, float).
	 * string $save_key:  If the index of this element isn't the database column name it can be overridden with this string.
	 * bool $is_dummy:    If set then nothing is acted upon for this element.
	 * bool $enabled:     A test to determine whether this is even available - if not is unset.
	 * string $link_with: Key which links this field to an overall set.
	 *
	 * string $js_submit: javascript to add inside the function checkProfileSubmit() in the template
	 * string $js:        javascript to add to the page in general
	 * string $js_load:   filename of js to be loaded with loadJavasciptFile
	 *
	 * Note that all elements that have a custom input_validate must ensure they set the value of $cur_profile correct to enable
	 * the changes to be displayed correctly on submit of the form.
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
			'preload' => function () {
				global $cur_profile, $context;

				// Split up the birth date....
				list ($uyear, $umonth, $uday) = explode('-', empty($cur_profile['birthdate']) || $cur_profile['birthdate'] === '0001-01-01' ? '0000-00-00' : $cur_profile['birthdate']);
				$context['member']['birth_date'] = array(
					'year' => $uyear === '0004' ? '0000' : $uyear,
					'month' => $umonth,
					'day' => $uday,
				);

				return true;
			},
			'input_validate' => function (&$value) {
				global $profile_vars, $cur_profile;

				if (isset($_POST['bday2'], $_POST['bday3']) && $value > 0 && $_POST['bday2'] > 0)
				{
					// Set to blank?
					if ((int) $_POST['bday3'] == 1 && (int) $_POST['bday2'] == 1 && (int) $value == 1)
						$value = '0001-01-01';
					else
						$value = checkdate($value, $_POST['bday2'], $_POST['bday3'] < 4 ? 4 : $_POST['bday3']) ? sprintf('%04d-%02d-%02d', $_POST['bday3'] < 4 ? 4 : $_POST['bday3'], $_POST['bday1'], $_POST['bday2']) : '0001-01-01';
				}
				else
					$value = '0001-01-01';

				$profile_vars['birthdate'] = $value;
				$cur_profile['birthdate'] = $value;
				return false;
			},
		),
		// Setting the birth date the old style way?
		'birthdate' => array(
			'type' => 'hidden',
			'permission' => 'profile_extra',
			'input_validate' => function (&$value) {
				global $cur_profile;

				// @todo Should we check for this year and tell them they made a mistake :P? (based on coppa at least?)
				if (preg_match('/(\d{4})[\-\., ](\d{2})[\-\., ](\d{2})/', $value, $dates) === 1)
				{
					$value = checkdate($dates[2], $dates[3], $dates[1] < 4 ? 4 : $dates[1]) ? sprintf('%04d-%02d-%02d', $dates[1] < 4 ? 4 : $dates[1], $dates[2], $dates[3]) : '0001-01-01';
					return true;
				}
				else
				{
					$value = empty($cur_profile['birthdate']) ? '0001-01-01' : $cur_profile['birthdate'];
					return false;
				}
			},
		),
		'date_registered' => array(
			'type' => 'date',
			'value' => empty($cur_profile['date_registered']) ? $txt['not_applicable'] : strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
			'label' => $txt['date_registered'],
			'log_change' => true,
			'permission' => 'moderate_forum',
			'input_validate' => function (&$value) {
				global $txt, $user_info, $modSettings, $cur_profile;

				// Bad date!  Go try again - please?
				if (($value = strtotime($value)) === -1)
				{
					$value = $cur_profile['date_registered'];
					return $txt['invalid_registration'] . ' ' . strftime('%d %b %Y ' . (strpos($user_info['time_format'], '%H') !== false ? '%I:%M:%S %p' : '%H:%M:%S'), forum_time(false));
				}
				// As long as it doesn't equal "N/A"...
				elseif ($value != $txt['not_applicable'] && $value != strtotime(strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600)))
					$value = $value - ($user_info['time_offset'] + $modSettings['time_offset']) * 3600;
				else
					$value = $cur_profile['date_registered'];

				return true;
			},
		),
		'email_address' => array(
			'type' => 'email',
			'label' => $txt['user_email_address'],
			'subtext' => $txt['valid_email'],
			'log_change' => true,
			'permission' => 'profile_identity',
			'input_validate' => function (&$value) {
				global $context, $old_profile, $profile_vars, $modSettings;

				if (strtolower($value) == strtolower($old_profile['email_address']))
					return false;

				$isValid = profileValidateEmail($value, $context['id_member']);

				// Do they need to re-validate? If so schedule the function!
				if ($isValid === true && !empty($modSettings['send_validation_onChange']) && !allowedTo('moderate_forum'))
				{
					require_once(SUBSDIR . '/Auth.subs.php');
					$old_profile['validation_code'] = generateValidationCode(14);
					$profile_vars['validation_code'] = substr(hash('sha256', $old_profile['validation_code']), 0, 10);
					$profile_vars['is_activated'] = 2;
					$context['profile_execute_on_save'][] = 'profileSendActivation';
					unset($context['profile_execute_on_save']['reload_user']);
				}

				return $isValid;
			},
		),
		'hide_email' => array(
			'type' => 'check',
			'value' => empty($cur_profile['hide_email']) ? true : false,
			'label' => $txt['allow_user_email'],
			'permission' => 'profile_identity',
			'input_validate' => function (&$value) {
				$value = $value == 0 ? 1 : 0;

				return true;
			},
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
			'enabled' => empty($settings['disable_user_variant']) || !empty($modSettings['theme_allow']) || allowedTo('admin_forum'),
			'preload' => function () {
				global $context, $cur_profile, $txt;

				$db = database();

				$request = $db->query('', '
					SELECT value
					FROM {db_prefix}themes
					WHERE id_theme = {int:id_theme}
						AND variable = {string:variable}
					LIMIT 1', array(
						'id_theme' => $cur_profile['id_theme'],
						'variable' => 'name',
					)
				);
				list ($name) = $db->fetch_row($request);
				$db->free_result($request);

				$context['member']['theme'] = array(
					'id' => $cur_profile['id_theme'],
					'name' => empty($cur_profile['id_theme']) ? $txt['theme_forum_default'] : $name
				);
				return true;
			},
			'input_validate' => function (&$value) {
				$value = (int) $value;
				return true;
			},
		),
		'karma_good' => array(
			'type' => 'callback',
			'callback_func' => 'karma_modify',
			'permission' => 'admin_forum',
			// Set karma_bad too!
			'input_validate' => function (&$value) {
				global $profile_vars, $cur_profile;

				$value = (int) $value;
				if (isset($_POST['karma_bad']))
				{
					$profile_vars['karma_bad'] = $_POST['karma_bad'] != '' ? (int) $_POST['karma_bad'] : 0;
					$cur_profile['karma_bad'] = $_POST['karma_bad'] != '' ? (int) $_POST['karma_bad'] : 0;
				}
				return true;
			},
			'preload' => function () {
				global $context, $cur_profile;

				$context['member']['karma']['good'] = $cur_profile['karma_good'];
				$context['member']['karma']['bad'] = $cur_profile['karma_bad'];

				return true;
			},
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
			'input_validate' => function (&$value) {
				global $context, $cur_profile;

				// Load the languages.
				profileLoadLanguages();

				if (isset($context['profile_languages'][$value]))
				{
					if ($context['user']['is_owner'] && empty($context['password_auth_failed']))
						$_SESSION['language'] = $value;
					return true;
				}
				else
				{
					$value = $cur_profile['lngfile'];
					return false;
				}
			},
		),
		// The username is not always editable - so adjust it as such.
		'member_name' => array(
			'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
			'label' => $txt['username'],
			'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '[<a href="' . $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=account;changeusername" class="em">' . $txt['username_change'] . '</a>]' : '',
			'log_change' => true,
			'permission' => 'profile_identity',
			'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="warningbox">' . $txt['username_warning'] . '</div>' : '',
			'input_validate' => function (&$value) {
				global $context, $user_info, $cur_profile;

				if (allowedTo('admin_forum'))
				{
					// We'll need this...
					require_once(SUBSDIR . '/Auth.subs.php');

					// Maybe they are trying to change their password as well?
					$resetPassword = true;
					if (isset($_POST['passwrd1']) && $_POST['passwrd1'] !== '' && isset($_POST['passwrd2']) && $_POST['passwrd1'] === $_POST['passwrd2'] && validatePassword($_POST['passwrd1'], $value, array($cur_profile['real_name'], $user_info['username'], $user_info['name'], $user_info['email'])) === null)
					{
						$resetPassword = false;
					}

					// Do the reset... this will send them an email too.
					if ($resetPassword)
					{
						resetPassword($context['id_member'], $value);
					}
					elseif ($value !== null)
					{
						$errors = ElkArte\Errors\ErrorContext::context('change_username', 0);

						validateUsername($context['id_member'], $value, 'change_username');

						// No errors we can proceed normally
						if (!$errors->hasErrors())
							updateMemberData($context['id_member'], array('member_name' => $value));
						else
						{
							// If there are "important" errors and you are not an admin: log the first error
							// Otherwise grab all of them and do not log anything
							$error_severity = $errors->hasErrors(1) && !$user_info['is_admin'] ? 1 : null;
							foreach ($errors->prepareErrors($error_severity) as $error)
								throw new Elk_Exception($error, $error_severity === null ? false : 'general');
						}
					}
				}
				return false;
			},
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
			'input_validate' => function (&$value) {
				global $user_info, $cur_profile;

				// If we didn't try it then ignore it!
				if ($value == '')
					return false;

				// Do the two entries for the password even match?
				if (!isset($_POST['passwrd2']) || $value != $_POST['passwrd2'])
					return 'bad_new_password';

				// Let's get the validation function into play...
				require_once(SUBSDIR . '/Auth.subs.php');
				$passwordErrors = validatePassword($value, $cur_profile['member_name'], array($cur_profile['real_name'], $user_info['username'], $user_info['name'], $user_info['email']));

				// Were there errors?
				if ($passwordErrors !== null)
					return 'password_' . $passwordErrors;

				// Set up the new password variable... ready for storage.
				require_once(SUBSDIR . '/Auth.subs.php');
				$value = validateLoginPassword($value, '', $cur_profile['member_name'], true);
				return true;
			},
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
		'enable_otp' => array(
			'type' => 'check',
			'value' => empty($cur_profile['enable_otp']) ? false : true,
			'subtext' => $txt['otp_enabled_help'],
			'label' => $txt['otp_enabled'],
			'permission' => 'profile_identity',
		),
		'otp_secret' => array(
			'type' => 'text',
			'label' => ucwords($txt['otp_token']),
			'subtext' => $txt['otp_token_help'],
			'enabled' => empty($cur_profile['openid_uri']),
			'size' => 20,
			'value' => empty($cur_profile['otp_secret']) ? '' : $cur_profile['otp_secret'],
			'postinput' => '<div style="display: inline-block;"><input type="button" value="' . $txt['otp_generate'] . '" onclick="generateSecret();"></div><div id="qrcode"></div>',
			'permission' => 'profile_identity',
		),
		// This does contact-related settings
		'receive_from' => array(
			'type' => 'select',
			'options' => array(
				$txt['receive_from_everyone'],
				$txt['receive_from_ignore'],
				$txt['receive_from_buddies'],
				$txt['receive_from_admins'],
			),
			'subtext' => $txt['receive_from_description'],
			'value' => empty($cur_profile['receive_from']) ? 0 : $cur_profile['receive_from'],
			'input_validate' => function (&$value) {
				global $cur_profile, $profile_vars;

				// Simple validate and apply the two "sub settings"
				$value = max(min($value, 3), 0);

				$cur_profile['receive_from'] = $profile_vars['receive_from'] = max(min((int) $_POST['receive_from'], 4), 0);

				return true;
			},
		),
		// This does ALL the pm settings
		'pm_settings' => array(
			'type' => 'callback',
			'callback_func' => 'pm_settings',
			'permission' => 'pm_read',
			'save_key' => 'pm_prefs',
			'preload' => function () {
				global $context, $cur_profile;

				$context['display_mode'] = $cur_profile['pm_prefs'] & 3;
				$context['send_email'] = $cur_profile['pm_email_notify'];

				return true;
			},
			'input_validate' => function (&$value) {
				global $cur_profile, $profile_vars;

				// Simple validate and apply the two "sub settings"
				$value = max(min($value, 2), 0);

				$cur_profile['pm_email_notify'] = $profile_vars['pm_email_notify'] = max(min((int) $_POST['pm_email_notify'], 2), 0);

				return true;
			},
		),
		'posts' => array(
			'type' => 'int',
			'label' => $txt['profile_posts'],
			'log_change' => true,
			'size' => 7,
			'permission' => 'moderate_forum',
			'input_validate' => function (&$value) {
				if (!is_numeric($value))
					return 'digits_only';
				else
					$value = $value != '' ? strtr($value, array(',' => '', '.' => '', ' ' => '')) : 0;
				return true;
			},
		),
		'real_name' => array(
			'type' => !empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum') ? 'text' : 'label',
			'label' => $txt['name'],
			'subtext' => $txt['display_name_desc'],
			'log_change' => true,
			'input_attr' => array('maxlength="60"'),
			'permission' => 'profile_identity',
			'enabled' => !empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum'),
			'input_validate' => function (&$value) {
				global $context, $cur_profile;

				$value = trim(preg_replace('~[\s]~u', ' ', $value));

				if (trim($value) == '')
					return 'no_name';
				elseif (Util::strlen($value) > 60)
					return 'name_too_long';
				elseif ($cur_profile['real_name'] != $value)
				{
					require_once(SUBSDIR . '/Members.subs.php');
					if (isReservedName($value, $context['id_member']))
						return 'name_taken';
				}
				return true;
			},
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
			'input_validate' => function (&$value) {
				global $cur_profile;

				if (empty($value))
				{
					require_once(SUBSDIR . '/Members.subs.php');
					$member = getBasicMemberData($cur_profile['id_member'], array('authentication' => true));

					// No previous answer was saved, so that\'s all good
					if (empty($member['secret_answer']))
					{
						return true;
					}
					// There is a previous secret answer to the secret question, so let\'s put it back in the db...
					else
					{
						$value = $member['secret_answer'];
						// We have to tell the code is an error otherwise an empty value will go into the db
						return false;
					}
				}
				$value = $value != '' ? md5($value) : '';
				return true;
			},
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
			'preload' => function () {
				global $modSettings, $context, $txt, $cur_profile;

				$context['member']['smiley_set']['id'] = empty($cur_profile['smiley_set']) ? '' : $cur_profile['smiley_set'];
				$context['smiley_sets'] = explode(',', 'none,,' . $modSettings['smiley_sets_known']);
				$set_names = explode("\n", $txt['smileys_none'] . "\n" . $txt['smileys_forum_board_default'] . "\n" . $modSettings['smiley_sets_names']);
				foreach ($context['smiley_sets'] as $i => $set)
				{
					$context['smiley_sets'][$i] = array(
						'id' => htmlspecialchars($set, ENT_COMPAT, 'UTF-8'),
						'name' => htmlspecialchars($set_names[$i], ENT_COMPAT, 'UTF-8'),
						'selected' => $set == $context['member']['smiley_set']['id']
					);

					if ($context['smiley_sets'][$i]['selected'])
						$context['member']['smiley_set']['name'] = $set_names[$i];
				}
				return true;
			},
			'input_validate' => function (&$value) {
				global $modSettings;

				$smiley_sets = explode(',', $modSettings['smiley_sets_known']);
				if (!in_array($value, $smiley_sets) && $value !== 'none')
					$value = '';
				return true;
			},
		),
		// Pretty much a dummy entry - it populates all the theme settings.
		'theme_settings' => array(
			'type' => 'callback',
			'callback_func' => 'theme_settings',
			'permission' => 'profile_extra',
			'is_dummy' => true,
			'preload' => function () {
				global $context, $user_info;

				theme()->getTemplates()->loadLanguageFile('Settings');

				// Can they disable censoring?
				$context['allow_no_censored'] = false;
				if ($user_info['is_admin'] || $context['user']['is_owner'])
					$context['allow_no_censored'] = allowedTo('disable_censor');

				return true;
			},
		),
		'time_format' => array(
			'type' => 'callback',
			'callback_func' => 'timeformat_modify',
			'permission' => 'profile_extra',
			'preload' => function () {
				global $context, $user_info, $txt, $cur_profile, $modSettings;

				$context['easy_timeformats'] = array(
					array('format' => '', 'title' => $txt['timeformat_default']),
					array('format' => '%B %d, %Y, %I:%M:%S %p', 'title' => $txt['timeformat_easy1']),
					array('format' => '%B %d, %Y, %H:%M:%S', 'title' => $txt['timeformat_easy2']),
					array('format' => '%Y-%m-%d, %H:%M:%S', 'title' => $txt['timeformat_easy3']),
					array('format' => '%d %B %Y, %H:%M:%S', 'title' => $txt['timeformat_easy4']),
					array('format' => '%d-%m-%Y, %H:%M:%S', 'title' => $txt['timeformat_easy5'])
				);

				$context['member']['time_format'] = $cur_profile['time_format'];
				$context['current_forum_time'] = standardTime(time() - $user_info['time_offset'] * 3600, false);
				$context['current_forum_time_js'] = strftime('%Y,' . ((int) strftime('%m', time() + $modSettings['time_offset'] * 3600) - 1) . ',%d,%H,%M,%S', time() + $modSettings['time_offset'] * 3600);
				$context['current_forum_time_hour'] = (int) strftime('%H', forum_time(false));
				return true;
			},
		),
		'time_offset' => array(
			'type' => 'callback',
			'callback_func' => 'timeoffset_modify',
			'permission' => 'profile_extra',
			'preload' => function () {
				global $context, $cur_profile;

				$context['member']['time_offset'] = $cur_profile['time_offset'];
				return true;
			},
			'input_validate' => function (&$value) {
				// Validate the time_offset...
				$value = (float) strtr($value, ',', '.');

				if ($value < -23.5 || $value > 23.5)
					return 'bad_offset';

				return true;
			},
		),
		'usertitle' => array(
			'type' => 'text',
			'label' => $txt['custom_title'],
			'log_change' => true,
			'input_attr' => array('maxlength="50"'),
			'size' => 50,
			'permission' => 'profile_title',
			'enabled' => !empty($modSettings['titlesEnable']),
			'input_validate' => function (&$value) {
				if (Util::strlen($value) > 50)
					return 'user_title_too_long';

				return true;
			},
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
			'type' => 'url',
			'label' => $txt['website_url'],
			'subtext' => $txt['complete_url'],
			'size' => 50,
			'permission' => 'profile_extra',
			// Fix the URL...
			'input_validate' => function (&$value) {

				$value = addProtocol($value, array('http://', 'https://', 'ftp://', 'ftps://'));
				if (strlen($value) < 8)
					$value = '';
				return true;
			},
			'link_with' => 'website',
		),
	);

	call_integration_hook('integrate_load_profile_fields', array(&$profile_fields));

	$disabled_fields = !empty($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();

	// Hard to imagine this won't be necessary
	require_once(SUBSDIR . '/Members.subs.php');

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
 *
 * @param string[] $fields
 * @param string $hook
 * @throws \Elk_Exception
 */
function saveProfileFields($fields, $hook)
{
	global $profile_fields, $profile_vars, $context, $old_profile, $post_errors, $cur_profile;

	if (!empty($hook))
		call_integration_hook('integrate_' . $hook . '_profile_fields', array(&$fields));

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
	foreach ($fields as $key)
	{
		if (!isset($profile_fields[$key]))
		{
			continue;
		}

		$field = $profile_fields[$key];

		if (!isset($_POST[$key]) || !empty($field['is_dummy']) || (isset($_POST['preview_signature']) && $key === 'signature'))
			continue;

		// What gets updated?
		$db_key = isset($field['save_key']) ? $field['save_key'] : $key;

		// Right - we have something that is enabled, we can act upon and has a value posted to it. Does it have a validation function?
		if (isset($field['input_validate']))
		{
			$is_valid = $field['input_validate']($_POST[$key]);

			// An error occurred - set it as such!
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
		if ($field['cast_type'] === 'int')
			$_POST[$key] = (int) $_POST[$key];
		elseif ($field['cast_type'] === 'float')
			$_POST[$key] = (float) $_POST[$key];
		elseif ($field['cast_type'] === 'check')
			$_POST[$key] = !empty($_POST[$key]) ? 1 : 0;

		// If we got here we're doing OK.
		if ($field['type'] !== 'hidden' && (!isset($old_profile[$key]) || $_POST[$key] != $old_profile[$key]))
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
		if ($key === 'id_group' && $field['log_change'])
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
 */
function profileValidateEmail($email, $memID = 0)
{
	$db = database();

	// Check the name and email for validity.
	$check = array();
	$check['email'] = strtr($email, array('&#039;' => '\''));
	if (Data_Validator::is_valid($check, array('email' => 'valid_email|required'), array('email' => 'trim')))
		$email = $check['email'];
	else
		return empty($check['email']) ? 'no_email' : 'bad_email';

	// Email addresses should be and stay unique.
	$request = $db->query('', '
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
	$num = $db->num_rows($request);
	$db->free_result($request);

	return ($num > 0) ? 'email_taken' : true;
}

/**
 * Save the profile changes
 *
 * @param mixed[] $profile_vars
 * @param int $memID id_member
 * @throws Elk_Exception
 */
function saveProfileChanges(&$profile_vars, $memID)
{
	global $context, $user_profile;

	// These make life easier....
	$old_profile = &$user_profile[$memID];

	// Permissions...
	if ($context['user']['is_owner'])
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own'));
	else
		$changeOther = allowedTo('profile_extra_any');

	// Arrays of all the changes - makes things easier.
	$profile_bools = array(
		'notify_announcements',
		'notify_send_body',
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

	call_integration_hook('integrate_save_profile_changes', array(&$profile_bools, &$profile_ints, &$profile_floats, &$profile_strings));

	if (isset($_POST['sa']) && $_POST['sa'] === 'ignoreboards' && empty($_POST['ignore_brd']))
		$_POST['ignore_brd'] = array();

	// Whatever it is set to is a dirty filthy thing.  Kinda like our minds.
	unset($_POST['ignore_boards']);

	if (isset($_POST['ignore_brd']))
	{
		if (!is_array($_POST['ignore_brd']))
			$_POST['ignore_brd'] = array($_POST['ignore_brd']);

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
 *
 * @throws Elk_Exception no_access
 */
function makeThemeChanges($memID, $id_theme)
{
	global $modSettings, $context, $user_info;

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
	if ((isset($_POST['options']) && count(array_intersect(array_keys($_POST['options']), $reservedVars)) != 0) || (isset($_POST['default_options']) && count(array_intersect(array_keys($_POST['default_options']), $reservedVars)) != 0))
		throw new Elk_Exception('no_access', false);

	// Don't allow any overriding of custom fields with default or non-default options.
	$request = $db->query('', '
		SELECT col_name
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}',
		array(
			'is_active' => 1,
		)
	);
	$custom_fields = array();
	while ($row = $db->fetch_assoc($request))
		$custom_fields[] = $row['col_name'];
	$db->free_result($request);

	// These are the theme changes...
	$themeSetArray = array();
	if (isset($_POST['options']) && is_array($_POST['options']))
	{
		foreach ($_POST['options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt === 'topics_per_page' || $opt === 'messages_per_page')
				$val = max(0, min($val, 50));
			// We don't set this per theme anymore.
			elseif ($opt === 'allow_no_censored')
				continue;

			$themeSetArray[] = array($id_theme, $memID, $opt, is_array($val) ? implode(',', $val) : $val);
		}
	}

	$erase_options = array();
	if (isset($_POST['default_options']) && is_array($_POST['default_options']))
	{
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt === 'topics_per_page' || $opt === 'messages_per_page')
				$val = max(0, min($val, 50));
			// Only let admins and owners change the censor.
			elseif ($opt === 'allow_no_censored' && !$user_info['is_admin'] && !$context['user']['is_owner'])
				continue;

			$themeSetArray[] = array(1, $memID, $opt, is_array($val) ? implode(',', $val) : $val);
			$erase_options[] = $opt;
		}
	}

	// If themeSetArray isn't still empty, send it to the database.
	if (empty($context['password_auth_failed']))
	{
		require_once(SUBSDIR . '/Themes.subs.php');
		if (!empty($themeSetArray))
			updateThemeOptions($themeSetArray);

		if (!empty($erase_options))
			removeThemeOptions('custom', $memID, $erase_options);

		$themes = explode(',', $modSettings['knownThemes']);
		foreach ($themes as $t)
			Cache::instance()->remove('theme_settings-' . $t . ':' . $memID);
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
		$to_save = array();
		foreach (getMemberNotificationsProfile($memID) as $mention => $data)
		{
			if (isset($_POST['notify'][$mention]) && !empty($_POST['notify'][$mention]['status']) && isset($data['data'][$_POST['notify'][$mention]['method']]))
			{
				$to_save[$mention] = (int) $_POST['notify'][$mention]['method'];
			}
			else
				$to_save[$mention] = 0;
		}
		saveUserNotificationsPreferences($memID, $to_save);
	}

	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']))
	{
		if (!isset($_POST['notify_boards']))
			$_POST['notify_boards'] = array();

		// Make sure only integers are added/deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
			$_POST['notify_boards'][$index] = (int) $id;

		// id_board = 0 is reserved for topic notifications only
		$notification_wanted = array_diff($_POST['notify_boards'], array(0));

		// Gather up any any existing board notifications.
		$request = $db->query('', '
			SELECT id_board
			FROM {db_prefix}log_notify
			WHERE id_member = {int:selected_member}
				AND id_board != {int:id_board}',
			array(
				'selected_member' => $memID,
				'id_board' => 0,
			)
		);
		$notification_current = array();
		while ($row = $db->fetch_assoc($request))
			$notification_current[] = $row['id_board'];
		$db->free_result($request);

		// And remove what they no longer want
		$notification_deletes = array_diff($notification_current, $notification_wanted);
		if (!empty($notification_deletes))
			$db->query('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_board IN ({array_int:board_list})
					AND id_member = {int:selected_member}',
				array(
					'board_list' => $notification_deletes,
					'selected_member' => $memID,
				)
			);

		// Now add in what they do want
		$notification_inserts = array();
		foreach ($notification_wanted as $id)
			$notification_inserts[] = array($memID, $id);

		if (!empty($notification_inserts))
			$db->insert('ignore',
				'{db_prefix}log_notify',
				array('id_member' => 'int', 'id_board' => 'int'),
				$notification_inserts,
				array('id_member', 'id_board')
			);
	}

	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		$edit_notify_topics = array();
		foreach ($_POST['notify_topics'] as $index => $id)
			$edit_notify_topics[$index] = (int) $id;

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
	global $context, $user_profile, $user_info, $modSettings;

	$db = database();

	if ($sanitize && isset($_POST['customfield']))
		$_POST['customfield'] = htmlspecialchars__recursive($_POST['customfield']);

	$where = $area === 'register' ? 'show_reg != 0' : 'show_profile = {string:area}';

	// Load the fields we are saving too - make sure we save valid data (etc).
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		/* This means don't save if:
			- The user is NOT an admin.
			- The data is not freely viewable and editable by users.
			- The data is not invisible to users but editable by the owner (or if it is the user is not the owner)
			- The area isn't registration, and if it is that the field is not supposed to be shown there.
		*/
		if ($row['private'] != 0 && !allowedTo('admin_forum') && ($memID != $user_info['id'] || $row['private'] != 2) && ($area !== 'register' || $row['show_reg'] == 0))
			continue;

		// Validate the user data.
		if ($row['field_type'] === 'check')
			$value = isset($_POST['customfield'][$row['col_name']]) ? 1 : 0;
		elseif ($row['field_type'] === 'select' || $row['field_type'] === 'radio')
		{
			$value = $row['default_value'];
			foreach (explode(',', $row['field_options']) as $k => $v)
			{
				if (isset($_POST['customfield'][$row['col_name']]) && $_POST['customfield'][$row['col_name']] == $k)
					$value = $v;
			}
		}
		// Otherwise some form of text!
		else
		{
			// TODO: This is a bit backwards.
			$value = isset($_POST['customfield'][$row['col_name']]) ? $_POST['customfield'][$row['col_name']] : $row['default_value'];
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
				$value = (int) $value;
		}

		// Did it change or has it been set?
		if ((!isset($user_profile[$memID]['options'][$row['col_name']]) && !empty($value)) || (isset($user_profile[$memID]['options'][$row['col_name']]) && $user_profile[$memID]['options'][$row['col_name']] !== $value))
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

			$changes[] = array($row['col_name'], $value, $memID);
			$user_profile[$memID]['options'][$row['col_name']] = $value;
		}
	}
	$db->free_result($request);

	call_integration_hook('integrate_save_custom_profile_fields', array(&$changes, &$log_changes, $memID, $area, $sanitize));

	// Make those changes!
	if (!empty($changes) && empty($context['password_auth_failed']))
	{
		$db->insert('replace',
			'{db_prefix}custom_fields_data',
			array('variable' => 'string-255', 'value' => 'string-65534', 'id_member' => 'int'),
			$changes,
			array('variable', 'id_member')
		);

		if (!empty($log_changes) && !empty($modSettings['modlog_enabled']))
			logActions($log_changes);
	}
}

/**
 * Validates the value of a custom field
 *
 * @param mixed[] $field - An array describing the field. It consists of the
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
		return 'custom_field_too_long';

	// Any masks to apply?
	if ($field['field_type'] === 'text' && !empty($field['mask']) && $field['mask'] !== 'none')
	{
		// @todo We never error on this - just ignore it at the moment...
		if ($field['mask'] === 'email' && !isValidEmail($value))
			return 'custom_field_invalid_email';
		elseif ($field['mask'] === 'number' && preg_match('~[^\d]~', $value))
			return 'custom_field_not_number';
		elseif (substr($field['mask'], 0, 5) === 'regex' && trim($value) !== '' && preg_match(substr($field['mask'], 5), $value) === 0)
			return 'custom_field_inproper_format';
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
		return;

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
		$_COOKIE[$cookiename] = '';

	loadUserSettings();

	$context['user']['is_logged'] = false;
	$context['user']['is_guest'] = true;

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
 * @return boolean
 */
function profileLoadSignatureData()
{
	global $modSettings, $context, $txt, $cur_profile;

	// Signature limits.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);

	$context['signature_enabled'] = isset($sig_limits[0]) ? $sig_limits[0] : 0;
	$context['signature_limits'] = array(
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'max_smileys' => isset($sig_limits[4]) ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
		'bbc' => !empty($sig_bbc) ? explode(',', $sig_bbc) : array(),
	);
	// Kept this line in for backwards compatibility!
	$context['max_signature_length'] = $context['signature_limits']['max_length'];

	// Warning message for signature image limits?
	$context['signature_warning'] = '';
	if ($context['signature_limits']['max_image_width'] && $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_size'], $context['signature_limits']['max_image_width'], $context['signature_limits']['max_image_height']);
	elseif ($context['signature_limits']['max_image_width'] || $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_' . ($context['signature_limits']['max_image_width'] ? 'width' : 'height')], $context['signature_limits'][$context['signature_limits']['max_image_width'] ? 'max_image_width' : 'max_image_height']);

	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
	if ($context['show_spellchecking'])
		loadJavascriptFile('spellcheck.js', array('defer' => true));

	if (empty($context['do_preview']))
		$context['member']['signature'] = empty($cur_profile['signature']) ? '' : str_replace(array('<br />', '<', '>', '"', '\''), array("\n", '&lt;', '&gt;', '&quot;', '&#039;'), $cur_profile['signature']);
	else
	{
		$signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
		$validation = profileValidateSignature($signature);
		if (empty($context['post_errors']))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['post_errors'] = array();
		}

		$context['post_errors'][] = 'signature_not_yet_saved';
		if ($validation !== true && $validation !== false)
			$context['post_errors'][] = $validation;

		$context['member']['signature'] = censor($context['member']['signature']);
		$context['member']['current_signature'] = $context['member']['signature'];
		$signature = censor($signature);
		$bbc_parser = \BBC\ParserWrapper::instance();
		$context['member']['signature_preview'] = $bbc_parser->parseSignature($signature, true);
		$context['member']['signature'] = $_POST['signature'];
	}

	return true;
}

/**
 * Load avatar context data.
 *
 * @return boolean
 */
function profileLoadAvatarData()
{
	global $context, $cur_profile, $modSettings, $scripturl;

	$context['avatar_url'] = $modSettings['avatar_url'];

	$valid_protocol = preg_match('~^https' . (detectServer()->supportsSSL() ? '' : '?') . '://~i', $cur_profile['avatar']) === 1;
	$schema = 'http' . (detectServer()->supportsSSL() ? 's' : '') . '://';

	// @todo Temporary
	if ($context['user']['is_owner'])
		$allowedChange = allowedTo('profile_set_avatar') && allowedTo(array('profile_extra_any', 'profile_extra_own'));
	else
		$allowedChange = allowedTo('profile_set_avatar') && allowedTo('profile_extra_any');

	// Default context.
	$context['member']['avatar'] += array(
		'custom' => $valid_protocol ? $cur_profile['avatar'] : $schema,
		'selection' => $valid_protocol ? $cur_profile['avatar'] : '',
		'id_attach' => $cur_profile['id_attach'],
		'filename' => $cur_profile['filename'],
		'allow_server_stored' => !empty($modSettings['avatar_stored_enabled']) && $allowedChange,
		'allow_upload' =>  !empty($modSettings['avatar_upload_enabled']) && $allowedChange,
		'allow_external' =>  !empty($modSettings['avatar_external_enabled']) && $allowedChange,
		'allow_gravatar' =>  !empty($modSettings['avatar_gravatar_enabled']) && $allowedChange,
	);

	if ($cur_profile['avatar'] === '' && $cur_profile['id_attach'] > 0 && $context['member']['avatar']['allow_upload'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'upload',
			'server_pic' => 'blank.png',
			'external' => $schema
		);
		$context['member']['avatar']['href'] = empty($cur_profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $cur_profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $cur_profile['filename'];
	}
	elseif ($valid_protocol && $context['member']['avatar']['allow_external'])
		$context['member']['avatar'] += array(
			'choice' => 'external',
			'server_pic' => 'blank.png',
			'external' => $cur_profile['avatar']
		);
	elseif ($cur_profile['avatar'] === 'gravatar' && $context['member']['avatar']['allow_gravatar'])
		$context['member']['avatar'] += array(
			'choice' => 'gravatar',
			'server_pic' => 'blank.png',
			'external' => 'https://'
		);
	elseif ($cur_profile['avatar'] != '' && file_exists($modSettings['avatar_directory'] . '/' . $cur_profile['avatar']) && $context['member']['avatar']['allow_server_stored'])
		$context['member']['avatar'] += array(
			'choice' => 'server_stored',
			'server_pic' => $cur_profile['avatar'] == '' ? 'blank.png' : $cur_profile['avatar'],
			'external' => $schema
		);
	else
		$context['member']['avatar'] += array(
			'choice' => 'none',
			'server_pic' => 'blank.png',
			'external' => $schema
		);

	// Get a list of all the avatars.
	if ($context['member']['avatar']['allow_server_stored'])
	{
		require_once(SUBSDIR . '/Attachments.subs.php');
		$context['avatar_list'] = array();
		$context['avatars'] = is_dir($modSettings['avatar_directory']) ? getServerStoredAvatars('', 0) : array();
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
	global $cur_profile, $context, $user_settings;

	require_once(SUBSDIR . '/Membergroups.subs.php');

	$context['member_groups'] = getGroupsList();
	$context['member_groups'][0]['is_primary'] = $cur_profile['id_group'] == 0;

	$curGroups = explode(',', $cur_profile['additional_groups']);

	foreach ($context['member_groups'] as $id_group => $row)
	{
		// Registered member was already taken care before
		if ($id_group == 0)
			continue;

		$context['member_groups'][$id_group]['is_primary'] = $cur_profile['id_group'] == $id_group;
		$context['member_groups'][$id_group]['is_additional'] = in_array($id_group, $curGroups);
		$context['member_groups'][$id_group]['can_be_additional'] = true;
		$context['member_groups'][$id_group]['can_be_primary'] = $row['hidden'] != 2;
	}

	$context['member']['group_id'] = $user_settings['id_group'];

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
		$context['profile_languages'][$lang['filename']] = $lang['name'];

	ksort($context['profile_languages']);

	// Return whether we should proceed with this.
	return count($context['profile_languages']) > 1 ? true : false;
}

/**
 * Reload a users settings.
 */
function profileReloadUser()
{
	global $modSettings, $context, $cur_profile;

	// Log them back in - using the verify password as they must have matched and this one doesn't get changed by anyone!
	if (isset($_POST['passwrd2']) && $_POST['passwrd2'] != '')
	{
		require_once(SUBSDIR . '/Auth.subs.php');
		setLoginCookie(60 * $modSettings['cookieTime'], $context['id_member'], hash('sha256', Util::strtolower($cur_profile['member_name']) . un_htmlspecialchars($_POST['passwrd2']) . $cur_profile['password_salt']));
	}

	loadUserSettings();
	writeLog();
}

/**
 * Validate the signature
 *
 * @param string $value
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
		$wrapper = \BBC\ParserWrapper::instance();
		$parser = $wrapper->getSmileyParser();
		$parser->setEnabled($GLOBALS['user_info']['smiley_set'] !== 'none' && trim($smiley_parsed) !== '');
		$smiley_parsed = $parser->parseBlock($smiley_parsed);

		$smiley_count = substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($unparsed_signature), '<img');
		if (!empty($sig_limits[4]) && $sig_limits[4] == -1 && $smiley_count > 0)
			return 'signature_allow_smileys';
		elseif (!empty($sig_limits[4]) && $sig_limits[4] > 0 && $smiley_count > $sig_limits[4])
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
					$size = isset($sizes[(int) $size]) ? $sizes[(int) $size] : 0;
				}

				// Attempt to allow all sizes of abuse, so to speak.
				if ($matches[2][$ind] === 'px' && $size > $sig_limits[7])
					$limit_broke = $sig_limits[7] . 'px';
				elseif ($matches[2][$ind] === 'pt' && $size > ($sig_limits[7] * 0.75))
					$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
				elseif ($matches[2][$ind] === 'em' && $size > ((float) $sig_limits[7] / 14))
					$limit_broke = ((float) $sig_limits[7] / 14) . 'em';
				elseif ($matches[2][$ind] !== 'px' && $matches[2][$ind] !== 'pt' && $matches[2][$ind] !== 'em' && $sig_limits[7] < 18)
					$limit_broke = 'large';

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
						$matches[2][$key] = $matches[6][$key];

					if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
					{
						$width = $sig_limits[5];
						$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
					}
					elseif ($matches[2][$key])
						$width = $matches[2][$key];

					// ... and height.
					if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
					{
						$height = $sig_limits[6];
						if ($width != -1)
							$width = $width * ($height / $matches[4][$key]);
					}
					elseif ($matches[4][$key])
						$height = $matches[4][$key];

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
								$sizes[1] = $sizes[1] * ($width / $sizes[0]);
							}

							// Too high?
							if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width == -1)
									$width = $sizes[0];
								$width = $width * ($height / $sizes[1]);
							}
							elseif ($width != -1)
								$height = $sizes[1];
						}
					}

					// Did we come up with some changes? If so remake the string.
					if ($width != -1 || $height != -1)
						$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
				}

				if (!empty($replaces))
					$value = str_replace(array_keys($replaces), array_values($replaces), $value);
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
 * @todo argh, the avatar here. Take this out of here!
 *
 * @param mixed[] $value
 *
 * @return false|string
 * @throws Elk_Exception attachments_no_write, attach_timeout
 */
function profileSaveAvatarData(&$value)
{
	global $modSettings, $profile_vars, $cur_profile, $context;

	$db = database();

	$memID = $context['id_member'];
	if (empty($memID) && !empty($context['password_auth_failed']))
		return false;

	// We need to know where we're going to be putting it..
	require_once(SUBSDIR . '/Attachments.subs.php');
	require_once(SUBSDIR . '/ManageAttachments.subs.php');
	$uploadDir = getAvatarPath();
	$id_folder = getAvatarPathID();

	$downloadedExternalAvatar = false;
	$valid_http = isset($_POST['userpicpersonal']) && substr($_POST['userpicpersonal'], 0, 7) === 'http://' && strlen($_POST['userpicpersonal']) > 7;
	$valid_https = isset($_POST['userpicpersonal']) && substr($_POST['userpicpersonal'], 0, 8) === 'https://' && strlen($_POST['userpicpersonal']) > 8;
	if ($value === 'external' && !empty($modSettings['avatar_external_enabled']) && ($valid_http || $valid_https) && !empty($modSettings['avatar_download_external']))
	{
		theme()->getTemplates()->loadLanguageFile('Post');
		if (!is_writable($uploadDir))
			throw new Elk_Exception('attachments_no_write', 'critical');

		require_once(SUBSDIR . '/Package.subs.php');

		$url = parse_url($_POST['userpicpersonal']);
		$contents = fetch_web_data((empty($url['scheme']) ? 'http://' : $url['scheme'] . '://') . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path'])));

		if ($contents !== false)
		{
			// Create a hashed name to save
			$new_avatar_name = $uploadDir . '/' . getAttachmentFilename('avatar_tmp_' . $memID, null, null, true);
			if (file_put_contents($new_avatar_name, $contents) !== false)
			{
				$downloadedExternalAvatar = true;
				$_FILES['attachment']['tmp_name'] = $new_avatar_name;
			}
		}
	}

	if ($value === 'none')
	{
		$profile_vars['avatar'] = '';

		// Reset the attach ID.
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value === 'server_stored' && !empty($modSettings['avatar_stored_enabled']))
	{
		$profile_vars['avatar'] = strtr(empty($_POST['file']) ? (empty($_POST['cat']) ? '' : $_POST['cat']) : $_POST['file'], array('&amp;' => '&'));
		$profile_vars['avatar'] = preg_match('~^([\w _!@%*=\-#()\[\]&.,]+/)?[\w _!@%*=\-#()\[\]&.,]+$~', $profile_vars['avatar']) != 0 && preg_match('/\.\./', $profile_vars['avatar']) == 0 && file_exists($modSettings['avatar_directory'] . '/' . $profile_vars['avatar']) ? ($profile_vars['avatar'] === 'blank.png' ? '' : $profile_vars['avatar']) : '';

		// Clear current profile...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Get rid of their old avatar. (if uploaded.)
		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value === 'gravatar' && !empty($modSettings['avatar_gravatar_enabled']))
	{
		$profile_vars['avatar'] = 'gravatar';

		// Reset the attach ID.
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value === 'external' && !empty($modSettings['avatar_external_enabled']) && ($valid_http || $valid_https) && empty($modSettings['avatar_download_external']))
	{
		// We need these clean...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Remove any attached avatar...
		removeAttachments(array('id_member' => $memID));

		$profile_vars['avatar'] = str_replace(' ', '%20', preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $_POST['userpicpersonal']));

		if (preg_match('~^https?:///?$~i', $profile_vars['avatar']) === 1)
			$profile_vars['avatar'] = '';
		// Trying to make us do something we'll regret?
		elseif ((!$valid_http && !$valid_https) || ($valid_http && detectServer()->supportsSSL()))
			return 'bad_avatar';
		// Should we check dimensions?
		elseif (!empty($modSettings['avatar_max_height']) || !empty($modSettings['avatar_max_width']))
		{
			// Now let's validate the avatar.
			$sizes = url_image_size($profile_vars['avatar']);

			if (is_array($sizes) && (($sizes[0] > $modSettings['avatar_max_width'] && !empty($modSettings['avatar_max_width'])) || ($sizes[1] > $modSettings['avatar_max_height'] && !empty($modSettings['avatar_max_height']))))
			{
				// Houston, we have a problem. The avatar is too large!!
				if ($modSettings['avatar_action_too_large'] === 'option_refuse')
					return 'bad_avatar';
				elseif ($modSettings['avatar_action_too_large'] === 'option_download_and_resize')
				{
					// @todo remove this if appropriate
					require_once(SUBSDIR . '/Attachments.subs.php');
					if (saveAvatar($profile_vars['avatar'], $memID, $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
					{
						$profile_vars['avatar'] = '';
						$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
						$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
						$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
					}
					else
						return 'bad_avatar';
				}
			}
		}
	}
	elseif (($value === 'upload' && !empty($modSettings['avatar_upload_enabled'])) || $downloadedExternalAvatar)
	{
		if ((isset($_FILES['attachment']['name']) && $_FILES['attachment']['name'] != '') || $downloadedExternalAvatar)
		{
			// Get the dimensions of the image.
			if (!$downloadedExternalAvatar)
			{
				if (!is_writable($uploadDir))
				{
					theme()->getTemplates()->loadLanguageFile('Post');
					throw new Elk_Exception('attachments_no_write', 'critical');
				}

				$new_avatar_name = $uploadDir . '/' . getAttachmentFilename('avatar_tmp_' . $memID, null, null, true);
				if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $new_avatar_name))
				{
					theme()->getTemplates()->loadLanguageFile('Post');
					throw new Elk_Exception('attach_timeout', 'critical');
				}

				$_FILES['attachment']['tmp_name'] = $new_avatar_name;
			}

			// If there is no size, then it's probably not a valid pic, so lets remove it.
			$sizes = elk_getimagesize($_FILES['attachment']['tmp_name'], false);
			if ($sizes === false)
			{
				@unlink($_FILES['attachment']['tmp_name']);
				return 'bad_avatar';
			}
			// Check whether the image is too large.
			elseif ((!empty($modSettings['avatar_max_width']) && $sizes[0] > $modSettings['avatar_max_width']) || (!empty($modSettings['avatar_max_height']) && $sizes[1] > $modSettings['avatar_max_height']))
			{
				if (!empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] === 'option_download_and_resize')
				{
					// Attempt to chmod it.
					@chmod($_FILES['attachment']['tmp_name'], 0644);

					// @todo remove this require when appropriate
					require_once(SUBSDIR . '/Attachments.subs.php');
					if (!saveAvatar($_FILES['attachment']['tmp_name'], $memID, $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
					{
						// Something went wrong, so lets delete this offender
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					// Reset attachment avatar data.
					$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
					$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
					$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
				}
				elseif (!empty($modSettings['avatar_action_too_large']) && !empty($modSettings['avatar_reencode']))
				{
					// Attempt to chmod it.
					@chmod($_FILES['attachment']['tmp_name'], 0644);

					require_once(SUBSDIR . '/Graphics.subs.php');
					if (!reencodeImage($_FILES['attachment']['tmp_name'], $sizes[2]))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					// @todo remove this require when appropriate
					require_once(SUBSDIR . '/Attachments.subs.php');
					if (!saveAvatar($_FILES['attachment']['tmp_name'], $memID, $modSettings['avatar_max_width'], $modSettings['avatar_max_height']))
					{
						// Something went wrong, so lets delete this offender
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar5';
					}

					// Reset attachment avatar data.
					$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
					$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
					$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
				}
				else
				{
					@unlink($_FILES['attachment']['tmp_name']);
					return 'bad_avatar';
				}
			}
			elseif (is_array($sizes))
			{
				// Now try to find an infection.
				require_once(SUBSDIR . '/Graphics.subs.php');
				if (!checkImageContents($_FILES['attachment']['tmp_name'], !empty($modSettings['avatar_paranoid'])))
				{
					// It's bad. Try to re-encode the contents?
					if (empty($modSettings['avatar_reencode']) || (!reencodeImage($_FILES['attachment']['tmp_name'], $sizes[2])))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					// We were successful. However, at what price?
					$sizes = elk_getimagesize($_FILES['attachment']['tmp_name'], false);

					// Hard to believe this would happen, but can you bet?
					if ($sizes === false)
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}
				}

				$extensions = array(
					'1' => 'gif',
					'2' => 'jpg',
					'3' => 'png',
					'6' => 'bmp'
				);

				$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';
				$mime_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : ($extension === 'bmp' ? 'x-ms-bmp' : $extension));
				$destName = 'avatar_' . $memID . '_' . time() . '.' . $extension;
				list ($width, $height) = elk_getimagesize($_FILES['attachment']['tmp_name']);
				$file_hash = empty($modSettings['custom_avatar_enabled']) ? getAttachmentFilename($destName, null, null, true) : '';

				// Remove previous attachments this member might have had.
				removeAttachments(array('id_member' => $memID));

				$db->insert('',
					'{db_prefix}attachments',
					array(
						'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'fileext' => 'string', 'size' => 'int',
						'width' => 'int', 'height' => 'int', 'mime_type' => 'string', 'id_folder' => 'int',
					),
					array(
						$memID, (empty($modSettings['custom_avatar_enabled']) ? 0 : 1), $destName, $file_hash, $extension, filesize($_FILES['attachment']['tmp_name']),
						(int) $width, (int) $height, $mime_type, $id_folder,
					),
					array('id_attach')
				);

				$cur_profile['id_attach'] = $db->insert_id('{db_prefix}attachments', 'id_attach');
				$cur_profile['filename'] = $destName;
				$cur_profile['attachment_type'] = empty($modSettings['custom_avatar_enabled']) ? 0 : 1;

				$destinationPath = $uploadDir . '/' . (empty($file_hash) ? $destName : $cur_profile['id_attach'] . '_' . $file_hash . '.elk');
				if (!rename($_FILES['attachment']['tmp_name'], $destinationPath))
				{
					theme()->getTemplates()->loadLanguageFile('Post');
					// I guess a man can try.
					removeAttachments(array('id_member' => $memID));
					throw new Elk_Exception('attach_timeout', 'critical');
				}

				// Attempt to chmod it.
				@chmod($uploadDir . '/' . $destinationPath, 0644);
			}
			$profile_vars['avatar'] = '';

			// Delete any temporary file.
			if (file_exists($_FILES['attachment']['tmp_name']))
				@unlink($_FILES['attachment']['tmp_name']);
		}
		// Selected the upload avatar option and had one already uploaded before or didn't upload one.
		else
			$profile_vars['avatar'] = '';
	}
	else
		$profile_vars['avatar'] = '';

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
 * @throws Elk_Exception at_least_one_admin
 */
function profileSaveGroups(&$value)
{
	global $profile_vars, $old_profile, $context, $cur_profile;

	$db = database();

	// Do we need to protect some groups?
	if (!allowedTo('admin_forum'))
	{
		$request = $db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		$protected_groups = array(1);
		while ($row = $db->fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		$db->free_result($request);

		$protected_groups = array_unique($protected_groups);
	}

	// The account page allows the change of your id_group - but not to a protected group!
	if (empty($protected_groups) || count(array_intersect(array((int) $value, $old_profile['id_group']), $protected_groups)) == 0)
		$value = (int) $value;
	// ... otherwise it's the old group sir.
	else
		$value = $old_profile['id_group'];

	// Find the additional membergroups (if any)
	if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
	{
		$additional_groups = array();
		foreach ($_POST['additional_groups'] as $group_id)
		{
			$group_id = (int) $group_id;
			if (!empty($group_id) && (empty($protected_groups) || !in_array($group_id, $protected_groups)))
				$additional_groups[] = $group_id;
		}

		// Put the protected groups back in there if you don't have permission to take them away.
		$old_additional_groups = explode(',', $old_profile['additional_groups']);
		foreach ($old_additional_groups as $group_id)
		{
			if (!empty($protected_groups) && in_array($group_id, $protected_groups))
				$additional_groups[] = $group_id;
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
				SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				array(
					'admin_group' => 1,
					'selected_member' => $context['id_member'],
				)
			);
			list ($another) = $db->fetch_row($request);
			$db->free_result($request);

			if (empty($another))
				throw new Elk_Exception('at_least_one_admin', 'critical');
		}
	}

	// If we are changing group status, update permission cache as necessary.
	if ($value != $old_profile['id_group'] || isset($profile_vars['additional_groups']))
	{
		if ($context['user']['is_owner'])
			$_SESSION['mc']['time'] = 0;
		else
			updateSettings(array('settings_updated' => time()));
	}

	return true;
}

/**
 * Get the data about a users warnings.
 * Returns an array of them
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID the member ID
 */
function list_getUserWarnings($start, $items_per_page, $sort, $memID)
{
	global $scripturl;

	$db = database();

	$request = $db->query('', '
		SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
			lc.log_time, lc.body, lc.counter, lc.id_notice
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_recipient = {int:selected_member}
			AND lc.comment_type = {string:warning}
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'selected_member' => $memID,
			'warning' => 'warning',
		)
	);
	$previous_warnings = array();
	while ($row = $db->fetch_assoc($request))
	{
		$previous_warnings[] = array(
			'issuer' => array(
				'id' => $row['id_member'],
				'link' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name'] . '</a>') : $row['member_name'],
			),
			'time' => standardTime($row['log_time']),
			'html_time' => htmlTime($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
			'reason' => $row['body'],
			'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
			'id_notice' => $row['id_notice'],
		);
	}
	$db->free_result($request);

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
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE id_recipient = {int:selected_member}
			AND comment_type = {string:warning}',
		array(
			'selected_member' => $memID,
			'warning' => 'warning',
		)
	);
	list ($total_warnings) = $db->fetch_row($request);
	$db->free_result($request);

	return $total_warnings;
}

/**
 * Get a list of attachments for this user
 * (used by createList() callback and others)
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int[] $boardsAllowed
 * @param integer $memID
 * @param int[]|null|boolean $exclude_boards
 */
function profileLoadAttachments($start, $items_per_page, $sort, $boardsAllowed, $memID, $exclude_boards = null)
{
	global $board, $modSettings, $context, $settings, $scripturl, $txt;

	$db = database();

	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);

	// Retrieve some attachments.
	$request = $db->query('', '
		SELECT a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, a.fileext, a.width, a.height, ' .
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
		LIMIT {int:offset}, {int:limit}',
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
	);
	$attachments = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!$row['approved'])
			$row['filename'] = str_replace(array('{attachment_link}', '{txt_awaiting}'), array('<a href="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . '">' . $row['filename'] . '</a>', $txt['awaiting_approval']), $settings['attachments_awaiting_approval']);
		else
			$row['filename'] = '<a href="' . $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . '">' . $row['filename'] . '</a>';

		$attachments[] = array(
			'id' => $row['id_attach'],
			'filename' => $row['filename'],
			'fileext' => $row['fileext'],
			'width' => $row['width'],
			'height' => $row['height'],
			'downloads' => $row['downloads'],
			'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($modSettings['attachmentShowImages']),
			'id_thumb' => !empty($row['id_thumb']) ? $row['id_thumb'] : '',
			'subject' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . censor($row['subject']) . '</a>',
			'posted' => $row['poster_time'],
			'msg' => $row['id_msg'],
			'topic' => $row['id_topic'],
			'board' => $row['id_board'],
			'board_name' => $row['name'],
			'approved' => $row['approved'],
		);
	}

	$db->free_result($request);

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
		SELECT COUNT(*)
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
	list ($attachCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $attachCount;
}

/**
 * Get the relevant topics in the unwatched list
 * (used by createList() callbacks)
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID
 */
function getUnwatchedBy($start, $items_per_page, $sort, $memID)
{
	$db = database();

	// Get the list of topics we can see
	$request = $db->query('', '
		SELECT lt.id_topic
		FROM {db_prefix}log_topics AS lt
			LEFT JOIN {db_prefix}topics AS t ON (lt.id_topic = t.id_topic)
			LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)' . (in_array($sort, array('mem.real_name', 'mem.real_name DESC', 'mem.poster_time', 'mem.poster_time DESC')) ? '
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)' : '') . '
		WHERE lt.id_member = {int:current_member}
			AND lt.unwatched = 1
			AND {query_see_board}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'current_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$topics = array();
	while ($row = $db->fetch_assoc($request))
		$topics[] = $row['id_topic'];
	$db->free_result($request);

	// Any topics found?
	$topicsInfo = array();
	if (!empty($topics))
	{
		$request = $db->query('', '
			SELECT mf.subject, mf.poster_time as started_on, COALESCE(memf.real_name, mf.poster_name) as started_by, ml.poster_time as last_post_on, COALESCE(meml.real_name, ml.poster_name) as last_post_by, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
			WHERE t.id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$topicsInfo[] = $row;
		$db->free_result($request);
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
		SELECT COUNT(*)
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
	list ($unwatchedCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $unwatchedCount;
}

/**
 * Returns the total number of posts a user has made
 *
 * - Counts all posts or just the posts made on a particular board
 *
 * @param int $memID
 * @param int|null $board
 * @return integer
 */
function count_user_posts($memID, $board = null)
{
	global $modSettings, $user_info;

	$db = database();

	$is_owner = $memID == $user_info['id'];

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] === '1=1' ? '' : '
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

	list ($msgCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $msgCount;
}

/**
 * Returns the total number of new topics a user has made
 *
 * - Counts all posts or just the topics made on a particular board
 *
 * @param int $memID
 * @param int|null $board
 * @return integer
 */
function count_user_topics($memID, $board = null)
{
	global $modSettings, $user_info;

	$db = database();

	$is_owner = $memID == $user_info['id'];

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics AS t' . ($user_info['query_see_board'] === '1=1' ? '' : '
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

	list ($msgCount) = $db->fetch_row($request);
	$db->free_result($request);

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
 */
function findMinMaxUserMessage($memID, $board = null)
{
	global $modSettings, $user_info;

	$db = database();

	$is_owner = $memID == $user_info['id'];

	$request = $db->query('', '
		SELECT MIN(id_msg), MAX(id_msg)
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
	$minmax = $db->fetch_row($request);
	$db->free_result($request);

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
 */
function findMinMaxUserTopic($memID, $board = null)
{
	global $modSettings, $user_info;

	$db = database();

	$is_owner = $memID == $user_info['id'];

	$request = $db->query('', '
		SELECT MIN(id_topic), MAX(id_topic)
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
	$minmax = $db->fetch_row($request);
	$db->free_result($request);

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
 * @param boolean $reverse
 * @param int|null $board
 */
function load_user_posts($memID, $start, $count, $range_limit = '', $reverse = false, $board = null)
{
	global $modSettings, $user_info;

	$db = database();

	$is_owner = $memID == $user_info['id'];
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
		if ($db->num_rows($request) === $count || empty($range_limit))
			break;
		else
			$range_limit = '';
	}

	// Place them in the post array
	while ($row = $db->fetch_assoc($request))
		$user_posts[] = $row;
	$db->free_result($request);

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
 * @param boolean $reverse
 * @param int|null $board
 */
function load_user_topics($memID, $start, $count, $range_limit = '', $reverse = false, $board = null)
{
	global $modSettings, $user_info;

	$db = database();

	$is_owner = $memID == $user_info['id'];
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
		if ($db->num_rows($request) === $count || empty($range_limit))
			break;
		else
			$range_limit = '';
	}

	// Place them in the topic array
	while ($row = $db->fetch_assoc($request))
		$user_topics[] = $row;
	$db->free_result($request);

	return $user_topics;
}

/**
 * Loads the permissions that are given to a member group or set of groups
 *
 * @param int[] $curGroups
 */
function getMemberGeneralPermissions($curGroups)
{
	global $txt;

	$db = database();
	theme()->getTemplates()->loadLanguageFile('ManagePermissions');

	// Get all general permissions.
	$request = $db->query('', '
		SELECT p.permission, p.add_deny, mg.group_name, p.id_group
		FROM {db_prefix}permissions AS p
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
		WHERE p.id_group IN ({array_int:group_list})
		ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'group_list' => $curGroups,
			'newbie_group' => 4,
		)
	);
	$general_permission = array();
	while ($row = $db->fetch_assoc($request))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		// Permissions that end with _own or _any consist of two parts.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

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
		$general_permission[$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		// Once denied is always denied.
		$general_permission[$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	$db->free_result($request);

	return $general_permission;
}

/**
 * Get the permissions a member has, or group they are in has
 * If $board is supplied will return just the permissions for that board
 *
 * @param int $memID
 * @param int[] $curGroups
 * @param int|null $board
 */
function getMemberBoardPermissions($memID, $curGroups, $board = null)
{
	global $txt;

	$db = database();
	theme()->getTemplates()->loadLanguageFile('ManagePermissions');

	$request = $db->query('', '
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
	);
	$board_permission = array();
	while ($row = $db->fetch_assoc($request))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Create the structure for this permission.
		if (!isset($board_permission[$row['permission']]))
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

		$board_permission[$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][$row['id_group']] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];
		$board_permission[$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	$db->free_result($request);

	return $board_permission;
}

/**
 * Retrieves (most of) the IPs used by a certain member in his messages and errors
 *
 * @param int $memID the id of the member
 */
function getMembersIPs($memID)
{
	global $modSettings, $user_profile;

	$db = database();

	// @todo cache this
	// If this is a big forum, or a large posting user, let's limit the search.
	if ($modSettings['totalMessages'] > 50000 && $user_profile[$memID]['posts'] > 500)
	{
		$request = $db->query('', '
			SELECT MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}',
			array(
				'current_member' => $memID,
			)
		);
		list ($max_msg_member) = $db->fetch_row($request);
		$db->free_result($request);

		// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
		$min_msg_member = max(0, $max_msg_member - $user_profile[$memID]['posts'] * 3);
	}

	// Default to at least the ones we know about.
	$ips = array(
		$user_profile[$memID]['member_ip'],
		$user_profile[$memID]['member_ip2'],
	);

	// @todo cache this
	// Get all IP addresses this user has used for his messages.
	$request = $db->query('', '
		SELECT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member} ' . (isset($min_msg_member) ? '
			AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
		GROUP BY poster_ip',
		array(
			'current_member' => $memID,
			'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
			'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$ips[] = $row['poster_ip'];

	$db->free_result($request);

	// Now also get the IP addresses from the error messages.
	$request = $db->query('', '
		SELECT COUNT(*) AS error_count, ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_member}
		GROUP BY ip',
		array(
			'current_member' => $memID,
		)
	);

	$error_ips = array();

	while ($row = $db->fetch_assoc($request))
		$error_ips[] = $row['ip'];

	$db->free_result($request);

	return array('message_ips' => array_unique($ips), 'error_ips' => array_unique($error_ips));
}

/**
 * Return the details of the members using a certain range of IPs
 * except the current one
 *
 * @param string[] $ips a list of IP addresses
 * @param int $memID the id of the "current" member (maybe it could be retrieved with currentMemberID)
 */
function getMembersInRange($ips, $memID)
{
	$db = database();

	$message_members = array();
	$members_in_range = array();

	// Get member ID's which are in messages...
	$request = $db->query('', '
		SELECT mem.id_member
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.poster_ip IN ({array_string:ip_list})
		GROUP BY mem.id_member
		HAVING mem.id_member != {int:current_member}',
		array(
			'current_member' => $memID,
			'ip_list' => $ips,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$message_members[] = $row['id_member'];
	$db->free_result($request);

	// And then get the member ID's belong to other users
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE id_member != {int:current_member}
			AND member_ip IN ({array_string:ip_list})',
		array(
			'current_member' => $memID,
			'ip_list' => $ips,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$message_members[] = $row['id_member'];
	$db->free_result($request);

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
 */
function getMemberNotificationsProfile($member_id)
{
	global $modSettings;

	if (empty($modSettings['enabled_mentions']))
		return array();

	require_once(SUBSDIR . '/Notification.subs.php');
	Elk_Autoloader::instance()->register(SUBSDIR . '/MentionType', '\\ElkArte\\sources\\subs\\MentionType');

	$mention_methods = Notifications::instance()->getNotifiers();
	$enabled_mentions = explode(',', $modSettings['enabled_mentions']);
	$user_preferences = getUsersNotificationsPreferences($enabled_mentions, $member_id);
	$mention_types = array();

	foreach ($enabled_mentions as $type)
	{
		$type_on = false;
		$notif = filterNotificationMethods($mention_methods, $type);

		foreach ($notif as $key => $val)
		{
			$notif[$key] = array('id' => $val, 'enabled' => $user_preferences[$member_id][$type] === $key);
			if ($user_preferences[$member_id][$type] > 0)
				$type_on = true;
		}

		if (!empty($notif))
			$mention_types[$type] = array('data' => $notif, 'enabled' => $type_on);
	}

	return $mention_types;
}
