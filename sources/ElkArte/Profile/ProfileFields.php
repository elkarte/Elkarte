<?php

/**
 * Handles the loading of custom and standard fields
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Profile;

use BBC\ParserWrapper;
use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\DataValidator;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\User;

/**
 * The ProfileFields class is responsible for loading and rendering profile fields.
 */
class ProfileFields
{
	/**
	 * Load any custom fields for this area.
	 * No area means load all, 'summary' loads all public ones.
	 *
	 * @param int $memID
	 * @param string $area = 'summary'
	 * @param array $custom_fields = array()
	 */
	public function loadCustomFields($memID, $area = 'summary', array $custom_fields = [])
	{
		global $context;

		$context['custom_fields'] = [];
		$context['custom_fields_required'] = false;

		require_once(SUBSDIR . '/Profile.subs.php');
		$where = $this->getProfileFieldWhereClause($area, $memID);
		$data = getCustomFieldData($where, $area);
		foreach ($data as $row)
		{
			// Shortcut.
			$options = MembersList::get($memID)->options;
			$value = $options[$row['col_name']] ?? $row['default_value'];

			// If this was submitted already then make the value the posted version.
			if (!empty($custom_fields) && isset($custom_fields[$row['col_name']]))
			{
				$value = Util::htmlspecialchars($custom_fields[$row['col_name']]);
				if (in_array($row['field_type'], ['select', 'radio']))
				{
					$options = explode(',', $row['field_options']);
					$value = $options[$value] ?? '';
				}
			}

			// Generate HTML for the various form inputs.
			[$input_html, $output_html, $key] = $this->generateFormFieldHtml($row, $value);
			$output_html = $this->postProcessOutputHtml($row, $output_html, $key);

			$context['custom_fields_required'] = $context['custom_fields_required'] || $row['show_reg'];
			$valid_areas = ['register', 'account', 'forumprofile', 'theme'];

			if (($value === '' || $value === 'no_default') && !in_array($area, $valid_areas))
			{
				continue;
			}

			$context['custom_fields'][] = [
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
			];
		}

		call_integration_hook('integrate_load_custom_profile_fields', [$memID, $area]);
	}

	/**
	 * Generate HTML for a form field based on the given row and value.
	 *
	 * @param array $row - The row containing information about the field.
	 * @param mixed $value - The current value of the field.
	 *
	 * @return array - An array containing the generated input HTML and the corresponding output HTML.
	 */
	private function generateFormFieldHtml($row, $value)
	{
		global $txt;

		$output_html = $value;
		$key = null;

		// Implement form field HTML generation based on field_type
		switch ($row['field_type'])
		{
			case 'check':
				// generate HTML for checkbox
				$true = (bool) $value;
				$input_html = '<input id="' . $row['col_name'] . '" type="checkbox" name="customfield[' . $row['col_name'] . ']" ' . ($true ? 'checked="checked"' : '') . ' class="input_check" />';
				$output_html = $true ? $txt['yes'] : $txt['no'];
				break;
			case 'select':
				// generate HTML for select
				$input_html = '<select id="' . $row['col_name'] . '" name="customfield[' . $row['col_name'] . ']"><option value=""' . ($row['default_value'] === 'no_default' ? ' selected="selected"' : '') . '></option>';
				$options = explode(',', $row['field_options']);

				foreach ($options as $k => $v)
				{
					$true = ($value === $v);
					$input_html .= '<option value="' . $k . '"' . ($true ? ' selected="selected"' : '') . '>' . $v . '</option>';
					if ($true)
					{
						$key = $k;
						$output_html = $v;
					}
				}

				$input_html .= '</select>';
				break;
			case 'radio':
				// generate HTML for radio
				$input_html = '<fieldset><legend>' . $row['field_name'] . '</legend>';
				$options = explode(',', $row['field_options']);

				foreach ($options as $k => $v)
				{
					$true = ($value === $v);
					$input_html .= '<label for="customfield_' . $row['col_name'] . '_' . $k . '"><input type="radio" name="customfield[' . $row['col_name'] . ']" class="input_radio" id="customfield_' . $row['col_name'] . '_' . $k . '" value="' . $k . '" ' . ($true ? 'checked="checked"' : '') . ' />' . $v . '</label><br />';
					if ($true)
					{
						$key = $k;
						$output_html = $v;
					}
				}

				$input_html .= '</fieldset>';
				break;
			case 'text':
			case 'url':
			case 'search':
			case 'date':
			case 'email':
			case 'color':
				// A standard input field, including some html5 variants
				$row['field_length'] = (int) $row['field_length'];
				$input_html = '<input id="' . $row['col_name'] . '" type="' . $row['field_type'] . '" name="customfield[' . $row['col_name'] . ']"';

				if ($row['field_length'] !== 0)
				{
					$input_html .= ' maxlength="' . $row['field_length'] . '"';
				}

				if ($row['field_length'] === 0 || $row['field_length'] >= 50)
				{
					$input_html .= ' size="50"';
				}
				elseif ($row['field_length'] > 30)
				{
					$input_html .= ' size="30"';
				}
				elseif ($row['field_length'] > 10)
				{
					$input_html .= ' size="20"';
				}
				else
				{
					$input_html .= ' size="10"';
				}

				$input_html .= ' value="' . $value . '" placeholder="' . $row['field_name'] . '" class="input_text" />';
				break;
			default:
				// generate HTML for textarea
				$input_html = '<textarea id="' . $row['col_name'] . '" name="customfield[' . $row['col_name'] . ']"';

				if (!empty($row['rows']))
				{
					$input_html .= ' rows="' . $row['rows'] . '"';
				}

				if (!empty($row['cols']))
				{
					$input_html .= ' cols="' . $row['cols'] . '"';
				}

				$input_html .= '>' . $value . '</textarea>';
				break;
		}

		return [$input_html, $output_html, $key];
	}

	/**
	 * This defines every profile field known to man.
	 *
	 * @param bool $force_reload = false
	 */
	public function loadProfileFields($force_reload = false)
	{
		global $context, $profile_fields, $txt, $scripturl, $modSettings, $cur_profile, $language, $settings;

		// Don't load this twice!
		if (!empty($profile_fields) && !$force_reload)
		{
			return;
		}

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

		$profile_fields = [
			'avatar_choice' => [
				'type' => 'callback',
				'callback_func' => 'avatar_select',
				// This handles the permissions too.
				'preload' => 'profileLoadAvatarData',
				'input_validate' => 'profileSaveAvatarData',
				'save_key' => 'avatar',
			],
			'bday1' => [
				'type' => 'callback',
				'callback_func' => 'birthdate',
				'permission' => 'profile_extra',
				'preload' => static function () {
					global $cur_profile, $context;

					// Split up the birth date....
					[$uyear, $umonth, $uday] = explode('-', empty($cur_profile['birthdate']) || $cur_profile['birthdate'] === '0001-01-01' ? '0000-00-00' : $cur_profile['birthdate']);
					$context['member']['birth_date'] = [
						'year' => $uyear === '0004' ? '0000' : $uyear,
						'month' => $umonth,
						'day' => $uday,
					];

					return true;
				},
				'input_validate' => static function (&$value) {
					global $profile_vars, $cur_profile;

					if (isset($_POST['bday1']))
					{
						$date_parts = explode('-', $_POST['bday1']);
						$bday3 = (int) $date_parts[0]; // Year
						$bday1 = (int) $date_parts[1]; // Month
						$bday2 = (int) $date_parts[2]; // Day

						// Set to blank?
						if ($bday3 === 1 && $bday2 === 1 && $bday1 === 1)
						{
							$value = '0001-01-01';
						}
						else
						{
							$value = checkdate($bday1, $bday2, max($bday3, 4)) ? sprintf('%04d-%02d-%02d', max($bday3, 4), $bday1, $bday2) : '0001-01-01';
						}
					}
					else
					{
						$value = '0001-01-01';
					}
					$profile_vars['birthdate'] = $value;
					$cur_profile['birthdate'] = $value;

					return false;
				},
			],
			'date_registered' => [
				'type' => 'date',
				'value' => empty($cur_profile['date_registered']) ? $txt['not_applicable'] : Util::strftime('%Y-%m-%d', $cur_profile['date_registered'] + (User::$info->time_offset + $modSettings['time_offset']) * 3600),
				'label' => $txt['date_registered'],
				'log_change' => true,
				'permission' => 'moderate_forum',
				'input_validate' => static function (&$value) {
					global $txt, $modSettings, $cur_profile;

					// Bad date!  Go try again - please?
					if (($value = strtotime($value)) === -1)
					{
						$value = $cur_profile['date_registered'];

						return $txt['invalid_registration'] . ' ' . Util::strftime('%d %b %Y ' . (strpos(User::$info->time_format, '%H') !== false ? '%I:%M:%S %p' : '%H:%M:%S'), forum_time(false));
					}

					// As long as it doesn't equal "N/A"...
					if ($value !== $txt['not_applicable'] && $value !== strtotime(Util::strftime('%Y-%m-%d', $cur_profile['date_registered'] + (User::$info->time_offset + $modSettings['time_offset']) * 3600)))
					{
						$value -= (User::$info->time_offset + $modSettings['time_offset']) * 3600;
					}
					else
					{
						$value = $cur_profile['date_registered'];
					}

					return true;
				},
			],
			'email_address' => [
				'type' => 'email',
				'label' => $txt['user_email_address'],
				'subtext' => $txt['valid_email'],
				'log_change' => true,
				'permission' => 'profile_identity',
				'input_validate' => function ($value) {
					global $context, $old_profile, $profile_vars, $modSettings;

					if (strtolower($value) === strtolower($old_profile['email_address']))
					{
						return false;
					}

					$isValid = self::profileValidateEmail($value, $context['id_member']);

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
			],
			'hide_email' => [
				'type' => 'check',
				'value' => empty($cur_profile['hide_email']),
				'label' => $txt['allow_user_email'],
				'permission' => 'profile_identity',
				'input_validate' => static function (&$value) {
					$value = (int) $value === 0 ? 1 : 0;

					return true;
				},
			],
			// Selecting group membership is a complicated one, so we treat it separate!
			'id_group' => [
				'type' => 'callback',
				'callback_func' => 'group_manage',
				'permission' => 'manage_membergroups',
				'preload' => 'profileLoadGroups',
				'log_change' => true,
				'input_validate' => 'profileSaveGroups',
			],
			'id_theme' => [
				'type' => 'callback',
				'callback_func' => 'theme_pick',
				'permission' => 'profile_extra',
				'enabled' => empty($settings['disable_user_variant']) || !empty($modSettings['theme_allow']) || allowedTo('admin_forum'),
				'preload' => static function () {
					global $context, $cur_profile, $txt;

					$db = database();
					$request = $db->query('', '
					SELECT value
					FROM {db_prefix}themes
					WHERE id_theme = {int:id_theme}
						AND variable = {string:variable}
					LIMIT 1', [
							'id_theme' => $cur_profile['id_theme'],
							'variable' => 'name',
						]
					);
					[$name] = $request->fetch_row();
					$request->free_result();
					$context['member']['theme'] = [
						'id' => $cur_profile['id_theme'],
						'name' => empty($cur_profile['id_theme']) ? $txt['theme_forum_default'] : $name
					];

					return true;
				},
				'input_validate' => static function (&$value) {
					$value = (int) $value;
					return true;
				},
			],
			'karma_good' => [
				'type' => 'callback',
				'callback_func' => 'karma_modify',
				'permission' => 'admin_forum',
				// Set karma_bad too!
				'input_validate' => static function (&$value) {
					global $profile_vars, $cur_profile;

					$value = (int) $value;
					if (isset($_POST['karma_bad']))
					{
						$profile_vars['karma_bad'] = $_POST['karma_bad'] !== '' ? (int) $_POST['karma_bad'] : 0;
						$cur_profile['karma_bad'] = $_POST['karma_bad'] !== '' ? (int) $_POST['karma_bad'] : 0;
					}

					return true;
				},
				'preload' => static function () {
					global $context, $cur_profile;

					$context['member']['karma'] = [
						'good' => (int) $cur_profile['karma_good'],
						'bad' => (int) $cur_profile['karma_bad']
					];

					return true;
				},
				'enabled' => !empty($modSettings['karmaMode']),
			],
			'lngfile' => [
				'type' => 'select',
				'options' => 'return $context[\'profile_languages\'];',
				'label' => $txt['preferred_language'],
				'permission' => 'profile_identity',
				'preload' => 'profileLoadLanguages',
				'enabled' => !empty($modSettings['userLanguage']),
				'value' => empty($cur_profile['lngfile']) ? $language : $cur_profile['lngfile'],
				'input_validate' => static function (&$value) {
					global $context, $cur_profile;

					// Load the languages.
					profileLoadLanguages();
					if (isset($context['profile_languages'][$value]))
					{
						if ($context['user']['is_owner'] && empty($context['password_auth_failed']))
						{
							$_SESSION['language'] = $value;
						}

						return true;
					}

					$value = $cur_profile['lngfile'];

					return false;
				},
			],
			// The username is not always editable - so adjust it as such.
			'member_name' => [
				'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
				'label' => $txt['username'],
				'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '[<a href="' . $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=account;changeusername" class="em">' . $txt['username_change'] . '</a>]' : '',
				'log_change' => true,
				'permission' => 'profile_identity',
				'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="warningbox">' . $txt['username_warning'] . '</div>' : '',
				'input_validate' => static function (&$value) {
					global $context, $cur_profile;

					if (allowedTo('admin_forum'))
					{
						// We'll need this...
						require_once(SUBSDIR . '/Auth.subs.php');

						// Maybe they are trying to change their password as well?
						$resetPassword = true;
						if (isset($_POST['passwrd1'], $_POST['passwrd2']) && $_POST['passwrd1'] !== '' && $_POST['passwrd1'] === $_POST['passwrd2'] && validatePassword($_POST['passwrd1'], $value, [$cur_profile['real_name'], User::$info->username, User::$info->name, User::$info->email]) === null)
						{
							$resetPassword = false;
						}

						// Do the reset... this will email them too.
						if ($resetPassword)
						{
							resetPassword($context['id_member'], $value);
						}
						elseif ($value !== null)
						{
							$errors = ErrorContext::context('change_username', 0);

							validateUsername($context['id_member'], $value, 'change_username');

							// No errors we can proceed normally
							if (!$errors->hasErrors())
							{
								updateMemberData($context['id_member'], ['member_name' => $value]);
							}
							else
							{
								// If there are "important" errors, and you are not an admin: log the first error
								// Otherwise grab all of them and do not log anything
								$error_severity = $errors->hasErrors(1) && User::$info->is_admin === false ? 1 : null;
								foreach ($errors->prepareErrors($error_severity) as $error)
								{
									throw new Exception($error, $error_severity === null ? false : 'general');
								}
							}
						}
					}

					return false;
				},
			],
			'passwrd1' => [
				'type' => 'password',
				'label' => ucwords($txt['choose_pass']),
				'subtext' => $txt['password_strength'],
				'size' => 20,
				'value' => '',
				'enabled' => true,
				'permission' => 'profile_identity',
				'save_key' => 'passwd',
				// Note this will only work if passwrd2 also exists!
				'input_validate' => static function (&$value) {
					global $cur_profile;

					// If we didn't try it then ignore it!
					if ($value === '')
					{
						return false;
					}

					// Do the two entries for the password even match?
					if (!isset($_POST['passwrd2']) || $value !== $_POST['passwrd2'])
					{
						return 'bad_new_password';
					}

					// Let's get the validation function into play...
					require_once(SUBSDIR . '/Auth.subs.php');
					$passwordErrors = validatePassword($value, $cur_profile['member_name'], [$cur_profile['real_name'], User::$info->username, User::$info->name, User::$info->email]);

					// Were there errors?
					if ($passwordErrors !== null)
					{
						return 'password_' . $passwordErrors;
					}

					// Set up the new password variable... ready for storage.
					require_once(SUBSDIR . '/Auth.subs.php');
					$value = validateLoginPassword($value, '', $cur_profile['member_name'], true);

					return true;
				},
			],
			'passwrd2' => [
				'type' => 'password',
				'label' => ucwords($txt['verify_pass']),
				'enabled' => true,
				'size' => 20,
				'value' => '',
				'permission' => 'profile_identity',
				'is_dummy' => true,
			],
			'enable_otp' => [
				'type' => 'check',
				'value' => !empty($cur_profile['enable_otp']),
				'subtext' => $txt['otp_enabled_help'],
				'label' => $txt['otp_enabled'],
				'permission' => 'profile_identity',
			],
			'otp_secret' => [
				'type' => 'text',
				'label' => ucwords($txt['otp_token']),
				'subtext' => $txt['otp_token_help'],
				'enabled' => true,
				'size' => 20,
				'value' => empty($cur_profile['otp_secret']) ? '' : $cur_profile['otp_secret'],
				'postinput' => '<div style="display: inline-block;"><input type="button" value="' . $txt['otp_generate'] . '" onclick="generateSecret();"></div><div id="qrcode"></div>',
				'permission' => 'profile_identity',
			],
			// This does contact-related settings
			'receive_from' => [
				'type' => 'select',
				'options' => [
					$txt['receive_from_everyone'],
					$txt['receive_from_ignore'],
					$txt['receive_from_buddies'],
					$txt['receive_from_admins'],
				],
				'subtext' => $txt['receive_from_description'],
				'value' => empty($cur_profile['receive_from']) ? 0 : $cur_profile['receive_from'],
				'input_validate' => static function (&$value) {
					global $cur_profile, $profile_vars;

					// Simple validate and apply the two "sub settings"
					$value = max(min($value, 3), 0);
					$cur_profile['receive_from'] = $profile_vars['receive_from'] = max(min((int) $_POST['receive_from'], 4), 0);

					return true;
				},
			],
			// This does ALL the pm settings
			'pm_settings' => [
				'type' => 'callback',
				'callback_func' => 'pm_settings',
				'permission' => 'pm_read',
				'save_key' => 'pm_prefs',
				'preload' => static function () {
					global $context, $cur_profile;

					$context['display_mode'] = $cur_profile['pm_prefs'] & 3;
					$context['send_email'] = $cur_profile['pm_email_notify'];

					return true;
				},
				'input_validate' => static function (&$value) {
					global $cur_profile, $profile_vars;

					// Simple validate and apply the two "sub settings"
					$value = max(min($value, 2), 0);
					$cur_profile['pm_email_notify'] = $profile_vars['pm_email_notify'] = max(min((int) $_POST['pm_email_notify'], 2), 0);

					return true;
				},
			],
			'posts' => [
				'type' => 'int',
				'label' => $txt['profile_posts'],
				'log_change' => true,
				'size' => 7,
				'permission' => 'moderate_forum',
				'input_validate' => static function (&$value) {
					// Account for comma_format presentation up front
					$check = strtr($value, [',' => '', '.' => '', ' ' => '']);
					if (!is_numeric($check))
					{
						return 'digits_only';
					}
					$value = $check !== '' ? $check : 0;

					return true;
				},
			],
			'real_name' => [
				'type' => !empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum') ? 'text' : 'label',
				'label' => $txt['name'],
				'subtext' => $txt['display_name_desc'],
				'log_change' => true,
				'input_attr' => ['maxlength="60"'],
				'permission' => 'profile_identity',
				'enabled' => !empty($modSettings['allow_editDisplayName']) || allowedTo('moderate_forum'),
				'input_validate' => static function (&$value) {
					global $context, $cur_profile;

					$value = trim(preg_replace('~\s~u', ' ', $value));
					if (trim($value) === '')
					{
						return 'no_name';
					}

					if (Util::strlen($value) > 60)
					{
						return 'name_too_long';
					}

					if ($cur_profile['real_name'] !== $value)
					{
						require_once(SUBSDIR . '/Members.subs.php');
						if (isReservedName($value, $context['id_member']))
						{
							return 'name_taken';
						}
					}

					return true;
				},
			],
			'secret_question' => [
				'type' => 'text',
				'label' => $txt['secret_question'],
				'subtext' => $txt['secret_desc'],
				'size' => 50,
				'permission' => 'profile_identity',
			],
			'secret_answer' => [
				'type' => 'text',
				'label' => $txt['secret_answer'],
				'subtext' => $txt['secret_desc2'],
				'size' => 20,
				'postinput' => '<span class="smalltext" style="margin-left: 4ex;">[<a href="' . $scripturl . '?action=quickhelp;help=secret_why_blank" onclick="return reqOverlayDiv(this.href);">' . $txt['secret_why_blank'] . '</a>]</span>',
				'value' => '',
				'permission' => 'profile_identity',
				'input_validate' => static function (&$value) {
					global $cur_profile;

					if (empty($value))
					{
						require_once(SUBSDIR . '/Members.subs.php');
						$member = getBasicMemberData($cur_profile['id_member'], ['authentication' => true]);

						// No previous answer was saved, so that\'s all good
						if (empty($member['secret_answer']))
						{
							return true;
						}

						// There is a previous secret answer to the secret question, so let\'s put it back in the db...
						$value = $member['secret_answer'];

						// We have to tell the code is an error otherwise an empty value will go into the db
						return false;
					}

					$value = $value !== '' ? md5($value) : '';

					return true;
				},
			],
			'signature' => [
				'type' => 'callback',
				'callback_func' => 'signature_modify',
				'permission' => 'profile_extra',
				'enabled' => strpos($modSettings['signature_settings'], (string) 1) === 0,
				'preload' => 'profileLoadSignatureData',
				'input_validate' => 'profileValidateSignature',
			],
			'show_online' => [
				'type' => 'check',
				'label' => $txt['show_online'],
				'permission' => 'profile_identity',
				'enabled' => !empty($modSettings['allow_hideOnline']) || allowedTo('moderate_forum'),
			],
			// Pretty much a dummy entry - it populates all the theme settings.
			'theme_settings' => [
				'type' => 'callback',
				'callback_func' => 'theme_settings',
				'permission' => 'profile_extra',
				'is_dummy' => true,
				'preload' => static function () {
					global $context;

					Txt::load('Settings');

					// Can they disable censoring?
					$context['allow_no_censored'] = false;
					if (User::$info->is_admin || $context['user']['is_owner'])
					{
						$context['allow_no_censored'] = allowedTo('disable_censor');
					}

					return true;
				},
			],
			'time_format' => [
				'type' => 'callback',
				'callback_func' => 'timeformat_modify',
				'permission' => 'profile_extra',
				'preload' => static function () {
					global $context, $txt, $cur_profile, $modSettings;

					$context['easy_timeformats'] = [
						['format' => '', 'title' => $txt['timeformat_default']],
						['format' => '%B %d, %Y, %I:%M:%S %p', 'title' => $txt['timeformat_easy1']],
						['format' => '%B %d, %Y, %H:%M:%S', 'title' => $txt['timeformat_easy2']],
						['format' => '%Y-%m-%d, %H:%M:%S', 'title' => $txt['timeformat_easy3']],
						['format' => '%d %B %Y, %H:%M:%S', 'title' => $txt['timeformat_easy4']],
						['format' => '%d-%m-%Y, %H:%M:%S', 'title' => $txt['timeformat_easy5']]
					];
					$context['member']['time_format'] = $cur_profile['time_format'];
					$context['current_forum_time'] = standardTime(time() - User::$info->time_offset * 3600, false);
					$context['current_forum_time_js'] = Util::strftime('%Y,' . ((int) Util::strftime('%m', time() + $modSettings['time_offset'] * 3600) - 1) . ',%d,%H,%M,%S', time() + $modSettings['time_offset'] * 3600);
					$context['current_forum_time_hour'] = (int) Util::strftime('%H', forum_time(false));

					return true;
				},
			],
			'time_offset' => [
				'type' => 'callback',
				'callback_func' => 'timeoffset_modify',
				'permission' => 'profile_extra',
				'preload' => static function () {
					global $context, $cur_profile;

					$context['member']['time_offset'] = $cur_profile['time_offset'];

					return true;
				},
				'input_validate' => static function (&$value) {
					// Validate the time_offset...
					$value = (float) str_replace(',', '.', $value);
					if ($value < -23.5 || $value > 23.5)
					{
						return 'bad_offset';
					}

					return true;
				},
			],
			'usertitle' => [
				'type' => 'text',
				'label' => $txt['custom_title'],
				'log_change' => true,
				'input_attr' => ['maxlength="50"'],
				'size' => 50,
				'permission' => 'profile_title',
				'enabled' => !empty($modSettings['titlesEnable']),
				'input_validate' => static function ($value) {
					if (Util::strlen($value) > 50)
					{
						return 'user_title_too_long';
					}

					return true;
				},
			],
			'website_title' => [
				'type' => 'text',
				'label' => $txt['website_title'],
				'subtext' => $txt['include_website_url'],
				'size' => 50,
				'permission' => 'profile_extra',
				'link_with' => 'website',
			],
			'website_url' => [
				'type' => 'url',
				'label' => $txt['website_url'],
				'subtext' => $txt['complete_url'],
				'size' => 50,
				'permission' => 'profile_extra',
				// Fix the URL...
				'input_validate' => static function (&$value) {
					$value = addProtocol($value, ['http://', 'https://', 'ftp://', 'ftps://']);
					if (strlen($value) < 8)
					{
						$value = '';
					}

					return true;
				},
				'link_with' => 'website',
			],
		];

		call_integration_hook('integrate_load_profile_fields', [&$profile_fields]);

		$disabled_fields = empty($modSettings['disabled_profile_fields']) ? [] : explode(',', $modSettings['disabled_profile_fields']);

		// Hard to imagine this won't be necessary
		require_once(SUBSDIR . '/Members.subs.php');

		// For each of the above let's take out the bits which don't apply - to save memory and security!
		foreach ($profile_fields as $key => $field)
		{
			// Do we have permission to do this?
			if (isset($field['permission']) && !allowedTo(($context['user']['is_owner'] ? [$field['permission'] . '_own', $field['permission'] . '_any'] : $field['permission'] . '_any')) && !allowedTo($field['permission']))
			{
				unset($profile_fields[$key]);
			}

			// Is it enabled?
			if (isset($field['enabled']) && !$field['enabled'])
			{
				unset($profile_fields[$key]);
			}

			// Is it specifically disabled?
			if (in_array($key, $disabled_fields, true) || (isset($field['link_with']) && in_array($field['link_with'], $disabled_fields, true)))
			{
				unset($profile_fields[$key]);
			}
		}
	}

	/**
	 * Save the profile changes.
	 *
	 * @param string[] $fields
	 * @param string $hook
	 */
	public function saveProfileFields($fields, $hook)
	{
		global $profile_fields, $profile_vars, $context, $old_profile, $post_errors, $cur_profile;

		if (!empty($hook))
		{
			call_integration_hook('integrate_' . $hook . '_profile_fields', [&$fields]);
		}

		// Load them up.
		$this->loadProfileFields();

		// This makes things easier...
		$old_profile = $cur_profile;

		// This allows variables to call activities when they save
		// - by default just to reload their settings
		$context['profile_execute_on_save'] = [];
		if ($context['user']['is_owner'])
		{
			$context['profile_execute_on_save']['reload_user'] = 'profileReloadUser';
		}

		// Assume we log nothing.
		$context['log_changes'] = [];

		// Cycle through the profile fields working out what to do!
		foreach ($fields as $key)
		{
			if (!isset($profile_fields[$key]))
			{
				continue;
			}

			$field = $profile_fields[$key];

			if (!isset($_POST[$key]) || !empty($field['is_dummy']) || (isset($_POST['preview_signature']) && $key === 'signature'))
			{
				continue;
			}

			// What gets updated?
			$db_key = $field['save_key'] ?? $key;

			// Right - we have something that is enabled, we can act upon and has a value
			// posted to it. Does it have a validation function?
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
			{
				$_POST[$key] = (int) $_POST[$key];
			}
			elseif ($field['cast_type'] === 'float')
			{
				$_POST[$key] = (float) $_POST[$key];
			}
			elseif ($field['cast_type'] === 'check')
			{
				$_POST[$key] = empty($_POST[$key]) ? 0 : 1;
			}

			// If we got here we're doing OK.
			if ($field['type'] !== 'hidden' && (!isset($old_profile[$key]) || $_POST[$key] != $old_profile[$key]))
			{
				// Set the save variable.
				$profile_vars[$db_key] = $_POST[$key];

				// And update the user profile.
				$cur_profile[$key] = $_POST[$key];

				// Are we logging it?
				if (!empty($field['log_change']) && isset($old_profile[$key]))
				{
					$context['log_changes'][$key] = [
						'previous' => $old_profile[$key],
						'new' => $_POST[$key],
					];
				}
			}

			// Logging group changes are a bit different...
			if ($key === 'id_group' && $field['log_change'])
			{
				profileLoadGroups();

				// Any changes to primary group?
				if ((int) $_POST['id_group'] !== (int) $old_profile['id_group'])
				{
					$context['log_changes']['id_group'] = [
						'previous' => !empty($old_profile[$key]) && isset($context['member_groups'][$old_profile[$key]]) ? $context['member_groups'][$old_profile[$key]]['name'] : '',
						'new' => !empty($_POST[$key]) && isset($context['member_groups'][$_POST[$key]]) ? $context['member_groups'][$_POST[$key]]['name'] : '',
					];
				}

				// Prepare additional groups for comparison.
				$additional_groups = [
					'previous' => empty($old_profile['additional_groups']) ? [] : explode(',', $old_profile['additional_groups']),
					'new' => empty($_POST['additional_groups']) ? [] : array_diff($_POST['additional_groups'], [0]),
				];

				sort($additional_groups['previous']);
				sort($additional_groups['new']);

				// What about additional groups?
				if ($additional_groups['previous'] !== $additional_groups['new'])
				{
					foreach ($additional_groups as $type => $groups)
					{
						foreach ($groups as $id => $group)
						{
							if (isset($context['member_groups'][$group]))
							{
								$additional_groups[$type][$id] = $context['member_groups'][$group]['name'];
							}
							else
							{
								unset($additional_groups[$type][$id]);
							}
						}

						$additional_groups[$type] = implode(', ', $additional_groups[$type]);
					}

					$context['log_changes']['additional_groups'] = $additional_groups;
				}
			}
		}

		// @todo Temporary
		if ($context['user']['is_owner'])
		{
			$changeOther = allowedTo(['profile_extra_any', 'profile_extra_own']);
		}
		else
		{
			$changeOther = allowedTo('profile_extra_any');
		}

		if ($changeOther && empty($post_errors))
		{
			makeThemeChanges($context['id_member'], isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
			if (!empty($_REQUEST['sa']))
			{
				makeCustomFieldChanges($context['id_member'], $_REQUEST['sa'], false);
			}
		}

		// Free memory!
		unset($profile_fields);
	}

	/**
	 * Validate an email address.
	 *
	 * @param string $email
	 * @param int $memID = 0
	 *
	 * @return bool|string
	 */
	public function profileValidateEmail($email, $memID = 0)
	{
		// Check the name and email for validity.
		$check = [];
		$check['email'] = strtr($email, ['&#039;' => "'"]);
		if (DataValidator::is_valid($check, ['email' => 'valid_email|required'], ['email' => 'trim']))
		{
			$email = $check['email'];
		}
		else
		{
			return empty($check['email']) ? 'no_email' : 'bad_email';
		}

		// Email addresses should be and stay unique.
		$num = isUniqueEmail($memID, $email);

		return ($num > 0) ? 'email_taken' : true;
	}

	/**
	 * Get the WHERE clause for retrieving profile fields.
	 *
	 * @param string $area
	 * @param int $memID
	 * @return string
	 */
	public function getProfileFieldWhereClause(string $area, int $memID): string
	{
		// Get the right restrictions in place...
		$where = 'active = 1';
		if ($area !== 'register' && !allowedTo('admin_forum'))
		{
			// If it's the owner they can see two types of private fields, regardless.
			if ($memID === User::$info->id)
			{
				$where .= $area === 'summary' ? ' AND private < 3' : ' AND (private = 0 OR private = 2)';
			}
			else
			{
				$where .= $area === 'summary' ? ' AND private < 2' : ' AND private = 0';
			}
		}

		if ($area === 'register')
		{
			$where .= ' AND show_reg != 0';
		}
		elseif ($area !== 'summary')
		{
			$where .= ' AND show_profile = {string:area}';
		}

		return $where;
	}

	/**
	 * Do any post-processing to the output HTML for custom fields.
	 *
	 * @param array $row
	 * @param string $output_html
	 * @param string $key
	 *
	 * @return string
	 */
	public function postProcessOutputHtml($row, $output_html, $key)
	{
		global $scripturl, $settings;

		// Parse BBCode
		if ($row['bbc'])
		{
			$bbc_parser = ParserWrapper::instance();

			$output_html = $bbc_parser->parseCustomFields($output_html);
		}
		// Allow for newlines at least
		elseif ($row['field_type'] === 'textarea')
		{
			$output_html = strtr($output_html, ["\n" => '<br />']);
		}

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']) && !empty($output_html))
		{
			$replacements = [
				'{SCRIPTURL}' => $scripturl,
				'{IMAGES_URL}' => $settings['images_url'],
				'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
				'{INPUT}' => $output_html,
			];

			if (in_array($row['field_type'], ['radio', 'select']))
			{
				$replacements['{KEY}'] = $row['col_name'] . '_' . ($key ?? 0);
			}

			$output_html = strtr($row['enclose'], $replacements);
		}

		return $output_html;
	}
}
