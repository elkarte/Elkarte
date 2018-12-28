<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Before showing users a registration form, show them the registration agreement.
 */
function template_registration_agreement()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=register" method="post" accept-charset="UTF-8" id="registration">';

	if (!empty($context['languages']))
	{
		if (count($context['languages']) === 1)
		{
			foreach ($context['languages'] as $lang_key => $lang_val)
			{
				echo '
				<input type="hidden" name="lngfile" value="', $lang_key, '" />';
			}
		}
		else
		{
			echo '
				<select onchange="this.form.submit()" class="floatright" name="lngfile">';

			foreach ($context['languages'] as $lang_key => $lang_val)
			{
				echo '
					<option value="', $lang_key, '"', empty($lang_val['selected']) ? '' : ' selected="selected"', '>', $lang_val['name'], '</option>';
			}

			echo '
				</select>';
		}
	}

	if (!empty($context['agreement']))
	{
		echo '
			<h2 class="category_header">', $txt['registration_agreement'], '</h2>
			<div class="well">
				<p>', $context['agreement'], '</p>
			</div>';
	}

	if (!empty($context['privacy_policy']))
	{
		echo '
			<h2 class="category_header">', $txt['registration_privacy_policy'], '
			</h2>
			<div class="well">
				<p>', $context['privacy_policy'], '</p>
			</div>';
	}

	echo '
			<div id="confirm_buttons" class="submitbutton centertext">';

	// Age restriction in effect?
	if ($context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', $context['coppa_agree_above'], '" />
				<br /><br />
				<input type="submit" name="accept_agreement_coppa" value="', $context['coppa_agree_below'], '" />';
	else
		echo '
				<input type="submit" name="accept_agreement" value="', $txt['agreement_agree'], '" />';
	echo '
				<input type="submit" name="no_accept" value="', $txt['agreement_no_agree'], '" />';

	if ($context['show_contact_button'])
		echo '
				<br /><br />
				<input type="submit" name="show_contact" value="', $txt['contact'], '" />';

	if (!empty($context['register_subaction']))
	{
		echo '
				<input type="hidden" name="sa" value="', $context['register_subaction'], '" />';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '" />
			</div>
			<input type="hidden" name="step" value="1" />
		</form>';
}

/**
 * Before registering - get their information.
 *
 * @uses ParseError
 */
function template_registration_form()
{
	global $context, $scripturl, $txt, $modSettings;

	theme()->addInlineJavascript('
		function verifyAgree()
		{
			if (currentAuthMethod === \'passwd\' && document.forms.registration.elk_autov_pwmain.value !== document.forms.registration.elk_autov_pwverify.value)
			{
				alert("' . $txt['register_passwords_differ_js'] . '");
				return false;
			}

			return true;
		}

		var currentAuthMethod = \'passwd\';', true);

	// Any errors?
	if (!empty($context['registration_errors']))
	{
		echo '
		<div class="errorbox">
			<span>', $txt['registration_errors_occurred'], '</span>
			<ul>';

		// Cycle through each error and display an error message.
		foreach ($context['registration_errors'] as $error)
			echo '
				<li>', $error, '</li>';

		echo '
			</ul>
		</div>';
	}

	echo '
		<form action="', $scripturl, '?action=register;sa=register2" method="post" accept-charset="UTF-8" name="registration" id="registration" onsubmit="return verifyAgree();">
			<h2 class="category_header">', $txt['registration_form'], '</h2>
			<h2 class="category_header">', $txt['required_info'], '</h2>
				<input type="password" name="autofill_honey_pot" class="hide" />
				<input type="hidden" name="allow_email" value="0" />
				<fieldset class="content">
					<dl class="settings">
						<dt>
							<label for="elk_autov_username">', $txt['username'], ':</label>
						</dt>
						<dd>
							<input type="text" name="user" id="elk_autov_username" size="30" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['username']) ? $context['username'] : '', '" class="input_text" placeholder="', $txt['username'], '" required="required" autofocus="autofocus" />
							<span id="elk_autov_username_div" class="hide">
								<a id="elk_autov_username_link" href="#">
									<i id="elk_autov_username_img" class="icon i-check"></i>
								</a>
							</span>
						</dd>';

	if ($context['insert_display_name'] == true)
	{
		echo '
						<dt>
							<label for="elk_autov_displayname">', $txt['display_name'], ':</label>
						</dt>
						<dd>
							<input type="text" name="display" id="elk_autov_displayname" size="30" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['display_name']) ? $context['display_name'] : '', '" class="input_text" placeholder="', $txt['display_name'], '" required="required" />
							<span id="elk_autov_displayname_div" class="hide">
								<a id="elk_autov_displayname_link" href="#">
									<i id="elk_autov_displayname_img" class="icon i-check"></i>
								</a>
							</span>
						</dd>';
	}

	echo '
						<dt>
							<label for="elk_autov_reserve1">', $txt['user_email_address'], ':</label>
						</dt>
						<dd>
							<input type="email" name="email" id="elk_autov_reserve1" size="30" tabindex="', $context['tabindex']++, '" value="', isset($context['email']) ? $context['email'] : '', '" class="input_text" placeholder="', $txt['user_email_address'], '" required="required" />
							<span id="suggestion" class="smalltext"></span>
						</dd>
 						<dt>
 							<label for="notify_announcements">', $txt['notify_announcements'], ':</label>
 						</dt>
 						<dd>
 							<input type="checkbox" name="notify_announcements" id="notify_announcements" tabindex="', $context['tabindex']++, '"', $context['notify_announcements'] ? ' checked="checked"' : '', ' class="input_check" />
 						</dd>
 					</dl>';

	// If OpenID is enabled, give the user a choice between password and OpenID.
	if (!empty($modSettings['enableOpenID']))
	{
		echo '
					<dl class="settings" id="authentication_group">
						<dt>
							<a href="', $scripturl, '?action=quickhelp;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
							<strong>', $txt['authenticate_label'], ':</strong>
						</dt>
						<dd>
							<label for="auth_pass" id="option_auth_pass">
								<input type="radio" name="authenticate" value="passwd" id="auth_pass" tabindex="', $context['tabindex']++, '" ', empty($context['openid']) ? 'checked="checked" ' : '', ' onclick="updateAuthMethod();" />
								', $txt['authenticate_password'], '
							</label>
							<label for="auth_openid" id="option_auth_openid">
								<input type="radio" name="authenticate" value="openid" id="auth_openid" tabindex="', $context['tabindex']++, '" ', !empty($context['openid']) ? 'checked="checked" ' : '', ' onclick="updateAuthMethod();" />
								', $txt['authenticate_openid'], '
							</label>
						</dd>
					</dl>';
	}

	echo '
					<dl class="settings" id="password1_group">
						<dt><label for="elk_autov_pwmain">', $txt['choose_pass'], ':</label></dt>
						<dd>
							<input type="password" name="passwrd1" id="elk_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '" class="input_password" placeholder="', $txt['choose_pass'], '" required="required" />
							<span id="elk_autov_pwmain_div" class="hide">
								<i id="elk_autov_pwmain_img" class="icon i-warn"></i>
							</span>
						</dd>
					</dl>
					<dl class="settings" id="password2_group">
						<dt><label for="elk_autov_pwverify">', $txt['verify_pass'], ':</label></dt>
						<dd>
							<input type="password" name="passwrd2" id="elk_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '" class="input_password" placeholder="', $txt['verify_pass'], '" required="required" />
							<span id="elk_autov_pwverify_div" class="hide">
								<i id="elk_autov_pwverify_img" class="icon i-check"></i>
							</span>
						</dd>
					</dl>';

	// If OpenID is enabled, give the user a choice between password and OpenID.
	if (!empty($modSettings['enableOpenID']))
	{
		echo '
					<dl class="settings" id="openid_group">
						<dt>
							<label>', $txt['authenticate_openid_url'], ':</label>
						</dt>
						<dd>
							<input type="text" name="openid_identifier" id="openid_url" size="30" tabindex="', $context['tabindex']++, '" value="', isset($context['openid']) ? $context['openid'] : '', '" class="input_text openid_login" />
						</dd>
					</dl>';
	}

	// If there is any field marked as required, show it here!
	if (!empty($context['custom_fields_required']) && !empty($context['custom_fields']))
	{
		echo '
					<dl class="settings">';

		foreach ($context['custom_fields'] as $key => $field)
			if ($field['show_reg'] > 1)
			{
				echo '
						<dt>
							<label ', !empty($field['is_error']) ? ' class="error"' : '', ' for="', $field['colname'], '">', $field['name'], ':</label>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', preg_replace_callback('~<(input|select|textarea) ~', function ($matches) {
							global $context;

							return '<' . $matches[1] . ' tabindex="' . ($context['tabindex']++) . '"';
						}, $field['input_html']), '</dd>';

				// Drop this one so we don't show the additonal information header unless needed
				unset($context['custom_fields'][$key]);
			}

		echo '
					</dl>';
	}

	echo '
				</fieldset>';

	// If we have either of these, show the extra group.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
	{
		echo '
			<div class="separator"></div>
			<h2 class="category_header">', $txt['additional_information'], '</h2>
			<fieldset class="content">
				<dl class="settings" id="custom_group">';
	}

	if (!empty($context['profile_fields']))
	{
		// Any fields we particularly want?
		foreach ($context['profile_fields'] as $key => $field)
		{
			if ($field['type'] == 'callback')
			{
				if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
				{
					$callback_func = 'template_profile_' . $field['callback_func'];
					$callback_func();
				}
			}
			else
			{
				echo '
					<dt>
						<label', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['label'], ':</label>';

				// Does it have any subtext to show?
				if (!empty($field['subtext']))
					echo '
						<span class="smalltext">', $field['subtext'], '</span>';

				echo '
					</dt>
					<dd>';

				// Want to put something in front of the box?
				if (!empty($field['preinput']))
					echo '
						', $field['preinput'];

				// What type of data are we showing?
				if ($field['type'] == 'label')
					echo '
						', $field['value'];

				// Maybe it's a text box - very likely!
				elseif (in_array($field['type'], array('int', 'float', 'text', 'password')))
					echo '
						<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" tabindex="', $context['tabindex']++, '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

				// Maybe it's an html5 input
				elseif (in_array($field['type'], array('url', 'search', 'date', 'email', 'color')))
					echo '
						<input type="', $field['type'], '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

				// You "checking" me out? ;)
				elseif ($field['type'] == 'check')
					echo '
						<input type="hidden" name="', $key, '" value="0" /><input type="checkbox" name="', $key, '" id="', $key, '" ', !empty($field['value']) ? ' checked="checked"' : '', ' value="1" tabindex="', $context['tabindex']++, '" ', $field['input_attr'], ' />';

				// Always fun - select boxes!
				elseif ($field['type'] == 'select')
				{
					echo '
						<select name="', $key, '" id="', $key, '" tabindex="', $context['tabindex']++, '">';

					if (isset($field['options']))
					{
						// Is this some code to generate the options?
						if (!is_array($field['options']))
						{
							try
							{
								$field['options'] = eval($field['options']);
							}
							catch (ParseError $e)
							{
								$field['options'] = '';
							}
						}

						// Assuming we now have some!
						if (is_array($field['options']))
						{
							foreach ($field['options'] as $value => $name)
							{
								echo '
							<option value="', $value, '" ', $value == $field['value'] ? 'selected="selected"' : '', '>', $name, '</option>';
							}
						}
					}

					echo '
						</select>';
				}

				// Something to end with?
				if (!empty($field['postinput']))
					echo '
							', $field['postinput'];

				echo '
					</dd>';
			}
		}
	}

	// Are there any custom fields?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['show_reg'] < 2)
				echo '
					<dt>
						<label', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['name'], ':</label>
						<span class="smalltext">', $field['desc'], '</span>
					</dt>
					<dd>', $field['input_html'], '</dd>';
		}
	}

	// If we have either of these, close the list like a proper gent.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
	{
		echo '
				</dl>
			</fieldset>';
	}

	if (isset($context['visual_verification']) && $context['visual_verification'] !== false)
	{
		template_verification_controls($context['visual_verification_id'], '
			<h2 class="category_header">' . $txt['verification'] . '</h2>
			<fieldset class="content centertext">
				', '
			</fieldset>');
	}

	if ($context['checkbox_agreement'] && $context['require_agreement'])
	{
		echo '
			<fieldset class="content">
				<div id="agreement_box">
					', $context['agreement'], '
				</div>
				<label for="checkbox_agreement">
					<input type="checkbox" name="checkbox_agreement" id="checkbox_agreement" value="1"', ($context['registration_passed_agreement'] ? ' checked="checked"' : ''), ' tabindex="', $context['tabindex']++, '" />
					', $txt['checkbox_agreement'], '
				</label>
				<div id="privacypol_box">
					', $context['privacy_policy'], '
				</div>
				<label for="checkbox_privacypol">
					<input type="checkbox" name="checkbox_privacypol" id="checkbox_privacypol" value="1"', ($context['registration_passed_privacypol'] ? ' checked="checked"' : ''), ' tabindex="', $context['tabindex']++, '" />
					', $txt['checkbox_privacypol'], '
				</label>';

		if (!empty($context['languages']))
		{
			echo '
				<br />
				<select id="agreement_lang" class="input_select">';
			foreach ($context['languages'] as $key => $val)
			{
				echo '
					<option value="', $key, '"', !empty($val['selected']) ? ' selected="selected"' : '', '>', $val['name'], '</option>';
			}
			echo '
				</select>';
		}
		echo '
			</fieldset>';
	}

	echo '
			<div id="confirm_buttons" class="submitbutton">';

	// Age restriction in effect?
	if ((!$context['require_agreement'] || $context['checkbox_agreement']) && $context['show_coppa'])
	{
		echo '
				<input type="submit" name="accept_agreement" value="', $context['coppa_agree_above'], '" />
				<br /><br />
				<input type="submit" name="accept_agreement_coppa" value="', $context['coppa_agree_below'], '" />';
	}
	else
	{
		echo '
				<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" />';
	}

	if ($context['show_contact_button'])
	{
		echo '
				<input type="submit" name="show_contact" value="', $txt['contact'], '" />';
	}

	echo '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '" />
			<input type="hidden" name="step" value="2" />
		</form>

		<script>
			var regTextStrings = {
				"username_valid": "', $txt['registration_username_available'], '",
				"username_invalid": "', $txt['registration_username_unavailable'], '",
				"username_check": "', $txt['registration_username_check'], '",
				"password_short": "', $txt['registration_password_short'], '",
				"password_reserved": "', $txt['registration_password_reserved'], '",
				"password_numbercase": "', $txt['registration_password_numbercase'], '",
				"password_no_match": "', $txt['registration_password_no_match'], '",
				"password_valid": "', $txt['registration_password_valid'], '"
			};
			var verificationHandle = new elkRegister("registration", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);

			// Update the authentication status.
			updateAuthMethod();
		</script>';
}

/**
 * After registration... all done ;).
 */
function template_after()
{
	global $context;

	// Not much to see here, just a quick... "you're now registered!" or what have you.
	echo '
		<div id="registration_success">
			<h2 class="category_header">', $context['title'], '</h2>
			<div class="content">
				', $context['description'], '
			</div>
		</div>';
}

/**
 * Template for giving instructions about COPPA activation.
 */
function template_coppa()
{
	global $context, $txt, $scripturl;

	// Formulate a nice complicated message!
	echo '
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="content">
				<p>', $context['coppa']['body'], '</p>
				<p>
					<span><a href="', $scripturl, '?action=register;sa=coppa;form;member=', $context['coppa']['id'], '" target="_blank" class="new_win">', $txt['coppa_form_link_popup'], '</a> | <a href="', $scripturl, '?action=register;sa=coppa;form;dl;member=', $context['coppa']['id'], '">', $txt['coppa_form_link_download'], '</a></span>
				</p>
				<p>', $context['coppa']['many_options'] ? $txt['coppa_send_to_two_options'] : $txt['coppa_send_to_one_option'], '</p>
				<ol>';

	// Can they send by post?
	if (!empty($context['coppa']['post']))
	{
		echo '
				<li> ', $txt['coppa_send_by_post'], '
					<p class="coppa_contact">
						', $context['coppa']['post'], '
					</p>
				</li>';

		// Can they send by fax??
		if (!empty($context['coppa']['fax']))
		{
			echo '
					<li>', $txt['coppa_send_by_fax'], '
						<p>
						', $context['coppa']['fax'], '
						</p>
					</li>';
		}

		// Offer an alternative Phone Number?
		if ($context['coppa']['phone'])
		{
			echo '
				<li>', $context['coppa']['phone'], '</li>';
		}

		echo '
			</ol>
		<div>';
	}
}

/**
 * An easily printable form for giving permission to access the forum for a minor.
 */
function template_coppa_form()
{
	global $context, $txt;

	// Show the form (As best we can)
	echo '
		<table class="table_grid">
			<tr>
				<td class="lefttext">', $context['forum_contacts'], '</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', $txt['coppa_form_address'], '</em>: ', $context['ul'], '<br />
					', $context['ul'], '<br />
					', $context['ul'], '<br />
					', $context['ul'], '
				</td>
			</tr>
			<tr>
				<td class="righttext">
					<em>', $txt['coppa_form_date'], '</em>: ', $context['ul'], '
					<br /><br />
				</td>
			</tr>
			<tr>
				<td class="lefttext">
					', $context['coppa_body'], '
				</td>
			</tr>
		</table>';
}

/**
 * Show a window containing the spoken verification code.
 */
function template_verification_sound()
{
	global $context, $settings, $txt, $db_show_debug;

	$db_show_debug = false;

	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $txt['visual_verification_sound'], '</title>
		<meta name="robots" content="noindex" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index.css', CACHE_STALE, '" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'index', $context['theme_variant'], '.css', CACHE_STALE, '" />';

	// Just show the help text and a "close window" link.
	echo '
	</head>
	<body style="margin: 0.5em;">
		<div class="content centertext">
			<br />
			<a href="', $context['verification_sound_href'], ';sound" rel="nofollow">
				<span style="font-size: 4em;">&#128266;</span>
			</a>
			<br /><br />
			<div>';

	if (isBrowser('is_ie'))
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1" />
				<param name="FileName" value="', $context['verification_sound_href'], '" />
			</object>';
	else
		echo '
			<audio controls="controls" autoplay="autoplay">
				<source src="', $context['verification_sound_href'], '" type="audio/x-wav">
			</audio>';

	echo '
			</div>
			<a href="', $context['verification_sound_href'], ';sound" rel="nofollow">', $txt['visual_verification_sound_again'], '</a><br />
			<a href="', $context['verification_sound_href'], '" rel="nofollow">', $txt['visual_verification_sound_direct'], '</a><br /><br />
			<a href="javascript:self.close();">', $txt['visual_verification_sound_close'], '</a><br />
		</div>
	</body>
</html>';
}

/**
 * Show a page for admins to register new members.
 */
function template_admin_register()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<div id="admin_form_wrapper">
			<form id="postForm" action="', $scripturl, '?action=admin;area=regcenter" method="post" autocomplete="off" accept-charset="UTF-8" name="postForm">
				<h2 class="category_header">', $txt['admin_browse_register_new'], '</h2>
				<div id="register_screen" class="content">';

	if (!empty($context['registration_done']))
		echo '
					<div class="successbox">
						', $context['registration_done'], '
					</div>';

	echo '
					<input type="password" name="autofill_honey_pot" class="hide" />
					<div class="flow_auto">
					<dl class="settings" id="admin_register_form">
						<dt>
							<label for="user_input">', $txt['admin_register_username'], ':</label>
							<span class="smalltext">', $txt['admin_register_username_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" tabindex="', $context['tabindex']++, '" size="30" maxlength="25" class="input_text" />
						</dd>
						<dt>
							<label for="email_input">', $txt['admin_register_email'], ':</label>
							<span class="smalltext">', $txt['admin_register_email_desc'], '</span>
						</dt>
						<dd>
							<input type="email" name="email" id="email_input" tabindex="', $context['tabindex']++, '" size="30" class="input_text" />
							<span id="suggestion" class="smalltext"></span>
						</dd>
						<dt>
							<label for="password_input">', $txt['admin_register_password'], ':</label>
							<span class="smalltext">', $txt['admin_register_password_desc'], '</span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" tabindex="', $context['tabindex']++, '" size="30" class="input_password" onchange="onCheckChange();" />
						</dd>';

	if (!empty($context['member_groups']))
	{
		echo '
						<dt>
							<label for="group_select">', $txt['admin_register_group'], ':</label>
							<span class="smalltext">', $txt['admin_register_group_desc'], '</span>
						</dt>
						<dd>
							<select name="group" id="group_select" tabindex="', $context['tabindex']++, '">';

		foreach ($context['member_groups'] as $id => $name)
			echo '
								<option value="', $id, '">', $name, '</option>';

		echo '
							</select>
						</dd>';
	}

	echo '
						<dt>
							<label for="emailPassword_check">', $txt['admin_register_email_detail'], ':</label>
							<span class="smalltext">', $txt['admin_register_email_detail_desc'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" tabindex="', $context['tabindex']++, '" checked="checked" disabled="disabled" />
						</dd>
						<dt>
							<label for="emailActivate_check">', $txt['admin_register_email_activate'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', $context['tabindex']++, '"', !empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? ' checked="checked"' : '', ' onclick="onCheckChange();" />
						</dd>
					</dl>
					</div>
					<div class="submitbutton">
						<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="right_submit" />
						<input type="hidden" name="sa" value="register" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-regc_token_var'], '" value="', $context['admin-regc_token'], '" />
					</div>
				</div>
			</form>
		</div>
	</div>';
}

/**
 * Form for editing the agreement shown for people registering to the forum.
 */
function template_edit_agreement()
{
	global $context, $scripturl, $txt;

	// Just a big box to edit the text file ;).
	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8" onsubmit="return confirmAgreement(', JavaScriptEscape($txt['confirm_request_accept_agreement']), ');">
			<h2 class="category_header">', $context['page_title'], '</h2>';

	// Warning for if the file isn't writable.
	if (!empty($context['warning']))
		echo '
			<p class="error">', $context['warning'], '</p>';

	echo '
			<div id="registration_agreement">
				<div class="content">
					<input type="hidden" name="agree_lang" value="', $context['current_agreement'], '" />';

	// Is there more than one language to choose from?
	if (count($context['editable_agreements']) > 1)
	{
		echo '
					<h2 class="category_header">', $txt['language_configuration'], '</h2>
					<div class="information">
						<strong>', $txt['admin_agreement_select_language'], ':</strong>&nbsp;
						<select name="agree_lang" onchange="document.getElementById(\'admin_form_wrapper\').submit();" tabindex="', $context['tabindex']++, '">';

		foreach ($context['editable_agreements'] as $file => $name)
			echo '
							<option value="', $file, '" ', $context['current_agreement'] == $file ? 'selected="selected"' : '', '>', $name, '</option>';

		echo '
						</select>
						<div class="submitbutton">
							<input type="hidden" name="sa" value="agreement" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="submit" name="change" value="', $txt['admin_agreement_select_language_change'], '" tabindex="', $context['tabindex']++, '" />
						</div>
					</div>';
	}

	// Show the actual agreement in an oversized text box.
	echo '
					<p class="agreement">
						<textarea rows="10" name="agreement" id="agreement">', $context['agreement'], '</textarea>
					</p>
					<p>
						<label for="requireAgreement"><input type="checkbox" name="requireAgreement" id="requireAgreement"', $context['require_agreement'] ? ' checked="checked"' : '', ' tabindex="', $context['tabindex']++, '" value="1" /> ', $txt['admin_agreement'], '.</label>';

	if (!empty($context['agreement_show_options']))
	{
		echo '
						<br />
						<label for="checkboxAgreement"><input type="checkbox" name="checkboxAgreement" id="checkboxAgreement"', $context['checkbox_agreement'] ? ' checked="checked"' : '', ' tabindex="', $context['tabindex']++, '" value="1" /> ', $txt['admin_checkbox_agreement'], '.</label>';
	}

	echo '
						<br />
						<label for="checkboxAcceptAgreement"><input type="checkbox" name="checkboxAcceptAgreement" id="checkboxAcceptAgreement" tabindex="', $context['tabindex']++, '" value="1" /> ', $txt['admin_checkbox_accept_agreement'], '.</label>
					</p>
					<div class="submitbutton" >
						<input type="submit" name="save" value="', $txt['save'], '" tabindex="', $context['tabindex']++, '" />
						<input type="hidden" name="sa" value="', $context['subaction'], '" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-rega_token_var'], '" value="', $context['admin-rega_token'], '" />
					</div>
				</div>
			</div>
		</form>';
}

/**
 * Interface to edit reserved words in admin panel.
 */
function template_edit_reserved_words()
{
	global $context, $scripturl, $txt;

	echo '
		<form id="admin_form_wrapper" class="content" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['admin_reserved_set'], '</h2>
			<div class="content">
				<h4>', $txt['admin_reserved_line'], '</h4>
				<p class="reserved_names">
					<textarea cols="30" rows="6" name="reserved" id="reserved">', implode("\n", $context['reserved_words']), '</textarea>
				</p>
				<dl class="settings">
					<dt>
						<label for="matchword">', $txt['admin_match_whole'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchword" id="matchword" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_word'] ? 'checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="matchcase">', $txt['admin_match_case'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchcase" id="matchcase" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_case'] ? 'checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="matchuser">', $txt['admin_check_user'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchuser" id="matchuser" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_user'] ? 'checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="matchname">', $txt['admin_check_display'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchname" id="matchname" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_name'] ? 'checked="checked"' : '', ' />
					</dd>
				</dl>
				<div class="submitbutton" >
					<input type="submit" value="', $txt['save'], '" name="save_reserved_names" tabindex="', $context['tabindex']++, '" />
					<input type="hidden" name="sa" value="reservednames" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-regr_token_var'], '" value="', $context['admin-regr_token'], '" />
				</div>
			</div>
		</form>';
}

/**
 * Interface for contact form.
 */
function template_contact_form()
{
	global $context, $scripturl, $txt;

	echo '
		<h2 class="category_header">', $txt['admin_contact_form'], '</h2>
		<form id="contact_form" class="content" action="', $scripturl, '?action=register;sa=contact" method="post" accept-charset="UTF-8">
			<div class="content">';

	if (!empty($context['errors']))
		echo '
				<div class="errorbox">', $txt['errors_contact_form'], ': <ul><li>', implode('</li><li>', $context['errors']), '</li></ul></div>';

	echo '
				<dl class="settings">
					<dt>
						<label for="emailaddress">', $txt['admin_register_email'], '</label>
					</dt>
					<dd>
						<input type="email" name="emailaddress" id="emailaddress" value="', !empty($context['emailaddress']) ? $context['emailaddress'] : '', '" tabindex="', $context['tabindex']++, '" />
						<span id="suggestion" class="smalltext"></span>
					</dd>
					<dt>
						<label for="contactmessage">', $txt['contact_your_message'], '</label>
					</dt>
					<dd>
						<textarea id="contactmessage" name="contactmessage" cols="50" rows="10" tabindex="', $context['tabindex']++, '">', !empty($context['contactmessage']) ? $context['contactmessage'] : '', '</textarea>
					</dd>';

	if (!empty($context['require_verification']))
	{
		template_verification_controls($context['visual_verification_id'], '
					<dt>
							' . $txt['verification'] . ':
					</dt>
					<dd>
							', '
					</dd>');
	}

	echo '
				</dl>
				<hr />
				<div class="submitbutton" >
					<input type="submit" value="', $txt['sendtopic_send'], '" name="send" tabindex="', $context['tabindex']++, '" />
					<input type="hidden" name="sa" value="reservednames" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['contact_token_var'], '" value="', $context['contact_token'], '" />
				</div>
			</div>
		</form>';
}

/**
 * Show a success page when contact form is submitted.
 */
function template_contact_form_done()
{
	global $txt;

	echo '
		<h2 class="category_header">', $txt['admin_contact_form'], '</h2>
		<div class="content">', $txt['contact_thankyou'], '</div>';
}
