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
 * @version 1.0 Release Candidate 2
 *
 */

/**
 * Show the main page with the reminder form.
 */
function template_reminder()
{
	global $context, $txt, $scripturl;

	echo '
	<br />
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="login">
			<h2 class="category_header">', $txt['authentication_reminder'], '</h2>
			<div class="roundframe">
				<p class="smalltext centertext">', $txt['password_reminder_desc'], '</p>
				<dl>
					<dt><label for="user">', $txt['user_email'], '</label>:</dt>
					<dd><input type="text" id="user" name="user" size="30" class="input_text" /></dd>
				</dl>
				<input type="submit" value="', $txt['reminder_continue'], '" class="right_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['remind_token_var'], '" value="', $context['remind_token'], '" />
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
	<br />
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="login">
			<h2 class="category_header">', $txt['authentication_reminder'], '</h2>
			<div class="roundframe">
				<p><strong>', $txt['authentication_options'], ':</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked="checked" class="input_radio" /></dt>
					<label for="reminder_type_email">', $txt['authentication_' . $context['account_type'] . '_email'], '</label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret" class="input_radio" />
					<label for="reminder_type_secret">', $txt['authentication_' . $context['account_type'] . '_secret'], '</label>
				</p>
				<div class="submitbutton">
					<input type="submit" value="', $txt['reminder_continue'], '" class="button_submit" />
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
		<br />
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
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<br />
	<form action="', $scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="UTF-8">
		<div class="login">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="roundframe">
				<dl>
					<dt><label for="elk_autov_pwmain">', $txt['choose_pass'], '</label>: </dt>
					<dd>
						<input type="password" name="passwrd1" id="elk_autov_pwmain" size="22" class="input_password" />
						<span id="elk_autov_pwmain_div" style="display: none;">
							<img id="elk_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" />
						</span>
					</dd>
					<dt><label for="elk_autov_pwverify">', $txt['verify_pass'], '</label>: </dt>
					<dd>
						<input type="password" name="passwrd2" id="elk_autov_pwverify" size="22" class="input_password" />
						<span id="elk_autov_pwverify_div" style="display: none;">
							<img id="elk_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" />
						</span>
					</dd>
				</dl>
				<div class="centertext">
					<input type="submit" value="', $txt['save'], '" class="button_submit" />
				</div>
			</div>
		</div>
		<input type="hidden" name="code" value="', $context['code'], '" />
		<input type="hidden" name="u" value="', $context['memID'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="', $context['remind-sp_token_var'], '" value="', $context['remind-sp_token'], '" />
	</form>
	<script><!-- // --><![CDATA[
		var regTextStrings = {
			"password_short": "', $txt['registration_password_short'], '",
			"password_reserved": "', $txt['registration_password_reserved'], '",
			"password_numbercase": "', $txt['registration_password_numbercase'], '",
			"password_no_match": "', $txt['registration_password_no_match'], '",
			"password_valid": "', $txt['registration_password_valid'], '"
		};

		var verificationHandle = new elkRegister("reminder_form", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
	// ]]></script>';
}

/**
 * Show a page to allow a new password to be entered.
 */
function template_ask()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<br />
	<form action="', $scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="login">
			<h2 class="category_header">', $txt['authentication_reminder'], '</h2>
			<div class="roundframe">
				<p class="smalltext">', $context['account_type'] == 'password' ? $txt['enter_new_password'] : $txt['openid_secret_reminder'], '</p>
				<dl>
					<dt>', $txt['secret_question'], ':</dt>
					<dd>', $context['secret_question'], '</dd>
					<dt><label for="secret_answer">', $txt['secret_answer'], '</label>:</dt>
					<dd><input type="text" name="secret_answer" size="22" class="input_text" /></dd>';

	if ($context['account_type'] == 'password')
		echo '
					<dt><label for="elk_autov_pwmain">', $txt['choose_pass'], '</label>: </dt>
					<dd>
						<input type="password" name="passwrd1" id="elk_autov_pwmain" size="22" class="input_password" />
						<span id="elk_autov_pwmain_div" style="display: none;">
							<img id="elk_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" />
						</span>
					</dd>
					<dt><label for="elk_autov_pwverify">', $txt['verify_pass'], '</label>: </dt>
					<dd>
						<input type="password" name="passwrd2" id="elk_autov_pwverify" size="22" class="input_password" />
						<span id="elk_autov_pwverify_div" style="display: none;">
							<img id="elk_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_valid.png" alt="*" />
						</span>
					</dd>';

	echo '
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['save'], '" class="button_submit" />
					<input type="hidden" name="uid" value="', $context['remind_user'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['remind-sai_token_var'], '" value="', $context['remind-sai_token'], '" />
				</div>
			</div>
		</div>
	</form>';

	if ($context['account_type'] == 'password')
		echo '
<script><!-- // --><![CDATA[
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	
	var verificationHandle = new elkRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
// ]]></script>';
}