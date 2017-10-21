<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * Show the main page with the reminder form.
 */
function template_reminder()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="login">
			<h2 class="category_header">', $txt['authentication_reminder'], '</h2>
			<div class="well">
				<p class="smalltext centertext">', $txt['password_reminder_desc'], '</p>
				<dl>
					<dt>
						<label for="user">', $txt['user_email'], ':</label>
					</dt>
					<dd>
						<input type="text" id="user" name="user" size="30" class="input_text" />
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['reminder_continue'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['remind_token_var'], '" value="', $context['remind_token'], '" />
				</div>
			</div>
		</div>
	</form>';
}

/**
 * Page to allow to pick a reminder.
 */
function template_reminder_pick()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="login">
			<h2 class="category_header">', $txt['authentication_reminder'], '</h2>
			<div class="well">
				<p><strong>', $txt['authentication_options'], ':</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked="checked" />
					<label for="reminder_type_email">', $txt['authentication_' . $context['account_type'] . '_email'], '</label>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret" />
					<label for="reminder_type_secret">', $txt['authentication_' . $context['account_type'] . '_secret'], '</label>
				</p>
				<div class="submitbutton">
					<input type="submit" value="', $txt['reminder_continue'], '" />
					<input type="hidden" name="uid" value="', $context['current_member']['id'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['remind_token_var'], '" value="', $context['remind_token'], '" />
				</div>
			</div>
		</div>
	</form>';
}

/**
 * Inform the user that reminder has been sent.
 */
function template_sent()
{
	global $context;

	echo '
		<div class="login" id="reminder_sent">
			<h2 class="category_header">' . $context['page_title'] . '</h2>
			<p class="information">' . $context['description'] . '</p>
		</div>';
}

/**
 * Allow to set a password.
 */
function template_set_password()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<form id="reminder_form" action="', $scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" method="post" accept-charset="UTF-8">
		<div class="login">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="well">
				<dl>
					<dt>
						<label for="elk_autov_pwmain">', $txt['choose_pass'], ':</label>
 					</dt>
					<dd>
						<input id="elk_autov_pwmain" type="password" name="passwrd1" size="22" class="input_password" />
						<span id="elk_autov_pwmain_div" class="hide">
							<i id="elk_autov_pwmain_img" class="icon i-warn" alt="*"></i>
						</span>
					</dd>
					<dt>
						<label for="elk_autov_pwverify">', $txt['verify_pass'], ':</label>
					</dt>
					<dd>
						<input id="elk_autov_pwverify" type="password" name="passwrd2"  size="22" class="input_password" />
						<span id="elk_autov_pwverify_div" class="hide">
							<i id="elk_autov_pwverify_img" class="icon i-warn" alt="*"></i>
						</span>
					</dd>';

	if (!empty($modSettings['enableOTP']))
		echo '
					<dt>
						<label for="otp">', $txt['disable_otp'], ':</label>
					</dt>
					<dd>
						<input id="otp" type="checkbox"  name="otp" />
					</dd>';

	echo '
				</dl>
				<div class="centertext">
					<input type="submit" value="', $txt['save'], '" />
				</div>
			</div>
		</div>
		<input type="hidden" name="code" value="', $context['code'], '" />
		<input type="hidden" name="u" value="', $context['memID'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="', $context['remind-sp_token_var'], '" value="', $context['remind-sp_token'], '" />
	</form>
	<script>
		var regTextStrings = {
			"password_short": "', $txt['registration_password_short'], '",
			"password_reserved": "', $txt['registration_password_reserved'], '",
			"password_numbercase": "', $txt['registration_password_numbercase'], '",
			"password_no_match": "', $txt['registration_password_no_match'], '",
			"password_valid": "', $txt['registration_password_valid'], '"
		};

		var verificationHandle = new elkRegister("reminder_form", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
	</script>';
}

/**
 * Show a page to allow a new password to be entered.
 */
function template_ask()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<form id="creator" action="', $scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="UTF-8" name="creator">
		<div class="login">
			<h2 class="category_header">', $txt['authentication_reminder'], '</h2>
			<div class="well">
				<p class="smalltext">', $context['account_type'] === 'password' ? $txt['enter_new_password'] : $txt['openid_secret_reminder'], '</p>
				<dl>
					<dt>
						<label>', $txt['secret_question'], ':</label>
					</dt>
					<dd>', $context['secret_question'], '</dd>
					<dt>
						<label for="secret_answer">', $txt['secret_answer'], ':</label>
					</dt>
					<dd>
						<input type="text" name="secret_answer" size="22" class="input_text" />
					</dd>';

	if ($context['account_type'] === 'password')
		echo '
					<dt>
						<label for="elk_autov_pwmain">', $txt['choose_pass'], ':</label>
 					</dt>
					<dd>
						<input type="password" name="passwrd1" id="elk_autov_pwmain" size="22" class="input_password" />
						<span id="elk_autov_pwmain_div" class="hide">
							<i id="elk_autov_pwmain_img" class="icon i-warn" alt="*"></i>
						</span>
					</dd>
					<dt>
						<label for="elk_autov_pwverify">', $txt['verify_pass'], ':</label>
					</dt>
					<dd>
						<input type="password" name="passwrd2" id="elk_autov_pwverify" size="22" class="input_password" />
						<span id="elk_autov_pwverify_div" class="hide">
							<i id="elk_autov_pwverify_img" class="icon i-check" alt="*"></i>
						</span>
					</dd>';

	echo '
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['save'], '" />
					<input type="hidden" name="uid" value="', $context['remind_user'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['remind-sai_token_var'], '" value="', $context['remind-sai_token'], '" />
				</div>
			</div>
		</div>
	</form>';

	if ($context['account_type'] === 'password')
		echo '
<script>
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};

	var verificationHandle = new elkRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
</script>';
}