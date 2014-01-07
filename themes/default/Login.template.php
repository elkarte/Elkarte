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
 * @version 1.0 Beta
 *
 */

/**
 * This is just the basic "login" form.
 */
function template_login()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	echo '
		<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

		<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
		<div class="login">
			<h2 class="category_header hdicon cat_img_login">
				', $txt['login'], '
			</h2>
			<div class="roundframe">';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		echo '
			<p class="errorbox">', implode('<br />', $context['login_errors']), '</p><br />';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
				<p class="description">', $context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd>
						<input type="text" name="user" size="20" maxlength="80" value="', $context['default_username'], '" class="input_text" ', !isset($_GET['openid']) ? 'autofocus="autofocus" ' : '', 'placeholder="', $txt['username'], '" />
					</dd>
					<dt>', $txt['password'], ':</dt>
					<dd>
						<input type="password" name="passwrd" value="', $context['default_password'], '" size="20" class="input_password" placeholder="', $txt['password'], '" />
					</dd>
				</dl>';

	if (!empty($modSettings['enableOpenID']))
		echo '<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd>
						<input type="text" id="openid_identifier" name="openid_identifier" class="input_text openid_login" size="17"', isset($_GET['openid']) ? ' autofocus="autofocus" ' : '', ' />&nbsp;<a href="', $scripturl, '?action=quickhelp;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" class="icon" /></a>
					</dd>
				</dl>
				<hr />';

	echo '
				<dl>
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd>
						<input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled="disabled"' : '', ' class="input_text" />
					</dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd>
						<input type="checkbox" name="cookieneverexp"', $context['never_expire'] ? ' checked="checked"' : '', ' class="input_check" onclick="this.form.cookielength.disabled = this.checked;" />
					</dd>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
					<dt class="alert">', $txt['undelete_account'], ':</dt>
					<dd>
						<input type="checkbox" name="undelete" class="input_check" />
					</dd>';

	echo '
				</dl>
				<p><input type="submit" value="', $txt['login'], '" class="button_submit" /></p>
				<p class="smalltext">
					<a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a>
				</p>
				<input type="hidden" name="hash_passwrd" value="" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
			</div>
		</div>
		</form>';

	// Focus on the correct input - username or password.
	echo '
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.', isset($_GET['openid']) ? 'openid_identifier' : (isset($context['default_username']) && $context['default_username'] != '' ? 'passwrd' : 'user'), '.focus();
		// ]]></script>';
}

/**
 * Tell a guest to get lost or login!
 */
function template_kick_guest()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
	<form action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
		<div class="login">
			<h2 class="category_header">', $txt['warning'], '</h2>';

	// Show the message or default message.
	echo '
			<p class="information centertext">
				', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br />';

	if ($context['can_register'])
		echo sprintf($txt['login_below_or_register'], $scripturl . '?action=register', $context['forum_name_html_safe']);
	else
		echo $txt['login_below'];

	// And now the login information.
	echo '
			<h3 class="category_header hdicon cat_img_login">
				', $txt['login'], '
			</h3>
			<div class="roundframe">
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd>
						<input type="text" name="user" size="20" class="input_text" />
					</dd>
					<dt>', $txt['password'], ':</dt>
					<dd>
						<input type="password" name="passwrd" size="20" class="input_password" />
					</dd>';

	if (!empty($modSettings['enableOpenID']))
		echo '
				</dl>
				<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd>
						<input type="text" name="openid_identifier" class="input_text openid_login" size="17" />
					</dd>
				</dl>
				<hr />
				<dl>';

	echo '
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd>
						<input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" />
					</dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd>
						<input type="checkbox" name="cookieneverexp" class="input_check" onclick="this.form.cookielength.disabled = this.checked;" />
					</dd>
				</dl>
				<p class="centertext">
					<input type="submit" value="', $txt['login'], '" class="button_submit" />
				</p>
				<p class="centertext smalltext">
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
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.user.focus();
		// ]]></script>';
}

/**
 * This is for maintenance mode.
 */
function template_maintenance()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Display the administrator's message at the top.
	echo '
<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
<form action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
	<div class="login" id="maintenance_mode">
		<h2 class="category_header">', $context['title'], '</h2>
		<p class="description flow_auto">
			<img class="floatleft" src="', $settings['images_url'], '/construction.png" alt="', $txt['in_maintain_mode'], '" />
			', $context['description'], '
		</p>
		<h3 class="category_header">', $txt['admin_login'], '</h3>
		<div class="roundframe">
			<dl>
				<dt>', $txt['username'], ':</dt>
				<dd>
					<input type="text" name="user" size="20" class="input_text" />
				</dd>
				<dt>', $txt['password'], ':</dt>
				<dd>
					<input type="password" name="passwrd" size="20" class="input_password" />
				</dd>
				<dt>', $txt['mins_logged_in'], ':</dt>
				<dd>
					<input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" />
				</dd>
				<dt>', $txt['always_logged_in'], ':</dt>
				<dd>
					<input type="checkbox" name="cookieneverexp" class="input_check" />
				</dd>
			</dl>
			<p>
				<input type="submit" value="', $txt['login'], '" class="button_submit" />
			</p>
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
	global $context, $settings, $scripturl, $txt;

	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

<form action="', $scripturl, $context['get_data'], '" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin" onsubmit="hash', ucfirst($context['sessionCheckType']), 'Password(this, \'', $context['user']['username'], '\', \'', $context['session_id'], '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');">
	<div class="login" id="admin_login">
		<h2 class="category_header hdicon cat_img_login">
			', $txt['login'], '
		</h2>
		<div class="roundframe centertext">';

	if (!empty($context['incorrect_password']))
		echo '
			<div class="error">', $txt['admin_incorrect_password'], '</div>';

	echo '
			<strong>', $txt['password'], ':</strong>
			<input type="password" name="', $context['sessionCheckType'], '_pass" size="24" class="input_password"  autofocus="autofocus" placeholder="', $txt['password'], '"/>
			<a href="', $scripturl, '?action=quickhelp;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><img class="icon" src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" /></a><br />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-login_token_var'], '" value="', $context['admin-login_token'], '" />
			<p>
				<input type="submit" value="', $txt['login'], '" class="button_submit" />
			</p>';

	// Make sure to output all the old post data.
	echo $context['post_data'], '
		</div>
	</div>
	<input type="hidden" name="', $context['sessionCheckType'], '_hash_pass" value="" />
</form>';

	// Focus on the password box.
	echo '
<script><!-- // --><![CDATA[
	document.forms.frmLogin.', $context['sessionCheckType'], '_pass.focus();
// ]]></script>';
}

/**
 * Activate your account manually?
 */
function template_retry_activate()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;u=', $context['member_id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="roundframe">
				<dl>';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd>
						<input type="text" name="user" size="30" class="input_text" />
					</dd>';

	echo '
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd>
						<input type="text" name="code" size="30" class="input_text" />
					</dd>
				</dl>
				<p>
					<input type="submit" value="', $txt['invalid_activation_submit'], '" class="button_submit" />
				</p>
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
		<form action="', $scripturl, '?action=activate;sa=resend" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="roundframe">
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="40" value="', $context['default_username'], '" class="input_text" /></dd>
				</dl>
				<p>', $txt['invalid_activation_new'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_new_email'], ':</dt>
					<dd>
						<input type="text" name="new_email" size="40" class="input_text" />
					</dd>
					<dt>', $txt['invalid_activation_password'], ':</dt>
					<dd>
						<input type="password" name="passwd" size="30" class="input_password" />
					</dd>
				</dl>';

	if ($context['can_activate'])
		echo '
				<p>', $txt['invalid_activation_known'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd>
						<input type="text" name="code" size="30" class="input_text" />
					</dd>
				</dl>';

	echo '
				<p>
					<input type="submit" value="', $txt['invalid_activation_resend'], '" class="button_submit" />
				</p>
			</div>
		</form>';
}