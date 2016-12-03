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
 * @version 1.1 beta 3
 *
 */

/**
 * This is just the basic "login" form.
 */
function template_login()
{
	global $context, $scripturl, $modSettings, $txt;

	echo '
		<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
			<div class="login centertext">
				<h2 class="category_header hdicon cat_img_login">
					', $txt['login'], '
				</h2>
				<div class="well">';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		echo '
					<p class="errorbox">', implode('<br />', $context['login_errors']), '</p>';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
					<p class="description">', $context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
					<dl>
						<dt>
							<label for="user">', $txt['username'], ':</label>
						</dt>
						<dd>
							<input type="text" name="user" id="user" size="20" maxlength="80" value="', $context['default_username'], '" class="input_text" ', !empty($context['using_openid']) ? 'autofocus="autofocus" ' : '', 'placeholder="', $txt['username'], '" />
						</dd>
						<dt>
							<label for="passwrd">', $txt['password'], ':</label>
						</dt>
						<dd>
							<input type="password" name="passwrd" id="passwrd" value="', $context['default_password'], '" size="20" class="input_password" placeholder="', $txt['password'], '" />
						</dd>';

	if (!empty($modSettings['enableOTP']))
		echo '
						<dt>', $txt['otp_token'], '</dt>
						<dd>
							<input type="password" name="otp_token" id="otp_token" value="', $context['default_password'], '" size="30" class="input_password" placeholder="', $txt['otp_token'], '" />
						</dd>';

	if (!empty($modSettings['enableOpenID']))
		echo '
					</dl>
					<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
					<dl>
						<dt>
							<label for="openid_identifier">', $txt['openid'], ':</label>
						</dt>
						<dd>
							<input type="text" id="openid_identifier" name="openid_identifier" class="input_text openid_login" size="17"', !empty($context['using_openid']) ? ' autofocus="autofocus" ' : '', ' />&nbsp;<a href="', $scripturl, '?action=quickhelp;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						</dd>
					</dl>
					<hr />';

	echo '
					<dl>
						<dt>
							<label for="cookielength">', $txt['mins_logged_in'], ':</label>
						</dt>
						<dd>
							<input type="text" name="cookielength" id="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled="disabled"' : '', ' class="input_text" />
						</dd>
						<dt>
							<label for="cookieneverexp">', $txt['always_logged_in'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="cookieneverexp" id="cookieneverexp"', $context['never_expire'] ? ' checked="checked"' : '', ' onclick="this.form.cookielength.disabled = this.checked;" />
						</dd>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
						<dt class="alert">
							<label for="undelete">', $txt['undelete_account'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="undelete" id="undelete" />
						</dd>';

	echo '
					</dl>
					<input type="submit" value="', $txt['login'], '" />
					<p class="smalltext">
						<a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a>
					</p>
					<input type="hidden" name="hash_passwrd" value="" />
					<input type="hidden" name="old_hash_passwrd" value="" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
				</div>
			</div>
		</form>';

	// Focus on the correct input - username or password.
	echo '
		<script>
			document.forms.frmLogin.', !empty($context['using_openid']) ? 'openid_identifier' : (isset($context['default_username']) && $context['default_username'] != '' ? 'passwrd' : 'user'), '.focus();
		</script>';
}

/**
 * Tell a guest to get lost or login!
 */
function template_kick_guest()
{
	global $context, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<form action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
		<div class="login centertext">
			<h2 class="category_header">', $txt['warning'], '</h2>';

	// Show the message or default message.
	echo '
			<p class="information">
				', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br />';

	if ($context['can_register'])
		echo sprintf($txt['login_below_or_register'], $scripturl . '?action=register', $context['forum_name_html_safe']);
	else
		echo $txt['login_below'];

	// And now the login information.
	echo '
			</p>
			<h2 class="category_header hdicon cat_img_login">
				', $txt['login'], '
			</h2>
			<div class="well">
				<dl>
					<dt>
						<label for="user">', $txt['username'], ':</label>
					</dt>
					<dd>
						<input type="text" name="user" id="user" size="20" class="input_text" />
					</dd>
					<dt>
						<label for="passwrd">', $txt['password'], ':</label>
					</dt>
					<dd>
						<input type="password" name="passwrd" id="passwrd" size="20" class="input_password" />
					</dd>';

	if (!empty($modSettings['enableOTP']))
		echo '
						<dt>', $txt['otp_token'], '</dt>
						<dd>
							<input type="password" name="otp_token" id="otp_token" value="', $context['default_password'], '" size="30" class="input_password" placeholder="', $txt['otp_token'], '" />
						</dd>';

	if (!empty($modSettings['enableOpenID']))
		echo '
				</dl>
				<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>
						<label for="openid_identifier">', $txt['openid'], ':</label>
					</dt>
					<dd>
						<input id="openid_identifier" type="text" name="openid_identifier" class="input_text openid_login" size="17" />
					</dd>
				</dl>
				<hr />
				<dl>';

	echo '
					<dt>
						<label for="cookielength">', $txt['mins_logged_in'], ':</label>
					</dt>
					<dd>
						<input type="text" name="cookielength" id="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" />
					</dd>
					<dt>
						<label for="cookieneverexp">', $txt['always_logged_in'], ':</label>
					</dt>
					<dd>
						<input type="checkbox" name="cookieneverexp" id="cookieneverexp" onclick="this.form.cookielength.disabled = this.checked;" />
					</dd>
				</dl>
				<input type="submit" value="', $txt['login'], '" />
				<p class="smalltext">
					<a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a>
				</p>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
			<input type="hidden" name="hash_passwrd" value="" />
		</div>
	</form>';

	// Do the focus thing...
	echo '
		<script>
			document.forms.frmLogin.user.focus();
		</script>';
}

/**
 * This is for maintenance mode.
 */
function template_maintenance()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Display the administrator's message at the top.
	echo '
<form action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
	<div class="login" id="maintenance_mode">
		<h2 class="category_header">', $context['title'], '</h2>
		<p class="description flow_auto">
			<img class="floatleft" src="', $settings['images_url'], '/construction.png" alt="', $txt['in_maintain_mode'], '" />
			', $context['description'], '
		</p>
		<h2 class="category_header">', $txt['admin_login'], '</h2>
		<div class="well centertext">
			<dl>
				<dt>
					<label for="user">', $txt['username'], ':</label>
				</dt>
				<dd>
					<input type="text" name="user" id="user" size="20" class="input_text" />
				</dd>
				<dt>
					<label for="passwrd">', $txt['password'], ':</label>
				</dt>
				<dd>
					<input type="password" name="passwrd" id="passwrd" size="20" class="input_password" />
				</dd>
				<dt>
					<label for="cookielength">', $txt['mins_logged_in'], ':</label>
				</dt>
				<dd>
					<input type="text" name="cookielength" id="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" />
				</dd>
				<dt>
					<label for="cookieneverexp">', $txt['always_logged_in'], ':</label>
				</dt>
				<dd>
					<input type="checkbox" name="cookieneverexp" id="cookieneverexp" />
				</dd>
			</dl>
			<input type="submit" value="', $txt['login'], '" />
			</div>
		<input type="hidden" name="hash_passwrd" value="" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
	</div>
</form>';
}

/**
 * This is for the security stuff - makes administrators login every so often.
 */
function template_admin_login()
{
	global $context, $scripturl, $txt;

	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
<form action="', $scripturl, $context['get_data'], '" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin" onsubmit="hash', ucfirst($context['sessionCheckType']), 'Password(this, \'', $context['user']['username'], '\', \'', $context['session_id'], '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');">
	<div class="login" id="admin_login">
		<h2 class="category_header hdicon cat_img_login">
			', $txt['login'], '
		</h2>
		<div class="well centertext">';

	if (!empty($context['incorrect_password']))
		echo '
			<div class="errorbox">', $txt['admin_incorrect_password'], '</div>';

	echo '
			<label for="', $context['sessionCheckType'], '_pass">', $txt['password'], ':</label>
			<input type="password" name="', $context['sessionCheckType'], '_pass" id="', $context['sessionCheckType'], '_pass" size="24" class="input_password" autofocus="autofocus" placeholder="', $txt['password'], '"/>
			<a href="', $scripturl, '?action=quickhelp;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-login_token_var'], '" value="', $context['admin-login_token'], '" />
			<p>
				<input type="submit" value="', $txt['login'], '" />
			</p>';

	// Make sure to output all the old post data.
	echo $context['post_data'], '
		</div>
	</div>
	<input type="hidden" name="', $context['sessionCheckType'], '_hash_pass" value="" />
</form>';

	// Focus on the password box.
	echo '
<script>
	document.forms.frmLogin.', $context['sessionCheckType'], '_pass.focus();
</script>';
}

/**
 * Activate your account manually?
 */
function template_retry_activate()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=register;sa=activate;u=', $context['member_id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="well">
				<dl class="settings">';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
					<dt>
						<label for="user">', $txt['invalid_activation_username'], ':</label>
					</dt>
					<dd>
						<input type="text" name="user" id="user" size="30" class="input_text" />
					</dd>';

	echo '
					<dt>
						<label for="code">', $txt['invalid_activation_retry'], ':</label>
					</dt>
					<dd>
						<input type="text" name="code" id="code" size="30" class="input_text" />
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['invalid_activation_submit'], '" />
				</div>
			</div>
		</form>';
}

/**
 * Resend the activation information?
 */
function template_resend()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=register;sa=activate;resend" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="well">
				<dl class="settings">
					<dt>
						<label for="user">', $txt['invalid_activation_username'], ':</label><p>', $txt['invalid_activation_new'], '</p>
					</dt>
					<dd>
						<input type="text" name="user" id="user" size="40" value="', $context['default_username'], '" class="input_text" />
					</dd>
					<dt>
						<label for="new_email">', $txt['invalid_activation_new_email'], ':</label>
					</dt>
					<dd>
						<input type="text" name="new_email" id="new_email" size="40" class="input_text" />
					</dd>
					<dt>
						<label for="passwd">', $txt['invalid_activation_password'], ':</label>
					</dt>
					<dd>
						<input type="password" name="passwd" id="passwd" size="30" class="input_password" />
					</dd>';

	if ($context['can_activate'])
		echo '
					<dt>
						<label for="code">', $txt['invalid_activation_retry'], ':</label>
						<p>', $txt['invalid_activation_known'], '</p>
					</dt>
					<dd>
						<input type="text" name="code" id="code" size="30" class="input_text" />
					</dd>';

	echo '
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['invalid_activation_resend'], '" />
				</div>
			</div>
		</form>';
}
