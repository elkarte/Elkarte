<?php
/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 */

// This is just the basic "login" form.
function template_login()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	echo '
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		echo '
		<p>', implode('<br />', $context['login_errors']), '</p>';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
		<p>', $context['description'], '</p>';

	echo '
	<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="', $context['character_set'], '" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
		<ul data-role="listview">
			<li><h3>', $txt['login'], '</h3></li>';

	// Now just get the basic information - username, password, etc.
	echo '
			<li>
				<div data-role="fieldcontain">
					', $txt['username'], ':
					<input type="text" name="user" size="20" value="', $context['default_username'], '" class="input_text" />
				</div>
				<div data-role="fieldcontain">
					', $txt['password'], ':
					<input type="password" name="passwrd" value="', $context['default_password'], '" size="20" class="input_password" />
				</div>
			</li>';

	if (!empty($modSettings['enableOpenID']))
		echo '
			<li>
				<div data-role="fieldcontain">
					<strong>&mdash;', $txt['or'], '&mdash;</strong>

					', $txt['openid'], ':
					<input type="text" name="openid_identifier" class="input_text openid_login" size="17" />&nbsp;<a href="', $scripturl, '?action=helpadmin;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" class="centericon" /></a>
				</div>
			</li>';

	echo '
			<li>
				<div data-role="fieldcontain">
					', $txt['mins_logged_in'], ':
					<input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled="disabled"' : '', ' class="input_text" />
				</div>
				<div data-role="fieldcontain">
					', $txt['always_logged_in'], ':
					<select name="cookieneverexp" data-role="slider">
						<option value="0">', $txt['no'], '</option>
						<option value="1" ', !empty($context['never_expire']) ? ' selected="selected"' : '', '>', $txt['yes'], '</option>
					</select>
				</div>
			</li>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
			<li>
				<div data-role="fieldcontain">
				', $txt['undelete_account'], ':
				<input type="checkbox" name="undelete" class="input_check" />
				</div>
			</li>';
echo '
			<li>
				<a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a>
			</li>
		</ul>
		<br />
		<input type="submit" value="', $txt['login'], '" />

		<input type="hidden" name="hash_passwrd" value="" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
	</form>';

}

// Tell a guest to get lost or login!
function template_kick_guest()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
	<h3>', $txt['warning'], '</h3>';

	// Show the message or default message.
	echo '
	<p>
		', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br />';


	if ($context['can_register'])
		echo sprintf($txt['login_below_or_register'], $scripturl . '?action=register', $context['forum_name_html_safe']);
	else
		echo $txt['login_below'];

	// And now the login information.
	echo '
	<p>
	<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
		<ul data-role="listview">
			<li><h3>', $txt['login'], '</h3></li>
			<li>
				<div data-role="fieldcontain">
					', $txt['username'], ':
					<input type="text" name="user" size="20" class="input_text" />
				</div>
				<div data-role="fieldcontain">
					', $txt['password'], ':
					<input type="password" name="passwrd" size="20" class="input_password" />
				</div>
			</li>';

	if (!empty($modSettings['enableOpenID']))
		echo '
			<li>
				<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<div data-role="fieldcontain">
					`', $txt['openid'], ':
					<input type="text" name="openid_identifier" class="input_text openid_login" size="17" />
				</div>
			</li>';

	echo '
			<li>
				<div data-role="fieldcontain"
					', $txt['mins_logged_in'], ':
					<input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" />
				</div>
				<div data-role="fieldcontain">
					', $txt['always_logged_in'], ':
					<select name="cookieneverexp" data-role="slider">
						<option value="0">', $txt['no'], '</option>
						<option value="1" ', !empty($context['never_expire']) ? ' selected="selected"' : '', '>', $txt['yes'], '</option>
					</select>
				</div>
			</li>
			<li>
				<a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a>
			</li>
		</ul>
		<br />
		<input type="submit" value="', $txt['login'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
		<input type="hidden" name="hash_passwrd" value="" />
	</form>';
}

// This is for maintenance mode.
function template_maintenance()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Display the administrator's message at the top.
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

	<form method="post" action="', $scripturl, '?action=login2" accept-charset="', $context['character_set'], '"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
	<h3>', $context['title'], '</h3>
	<p>', $context['description'], '</p>
	<ul data-role="listview">
		<li><h4>', $txt['admin_login'], '</h4></li>
		<li>
			<div data-role="fieldcontain">
				', $txt['username'], ':
				<input type="text" name="user" size="20" class="input_text" />
			</div>
			<div data-role="fieldcontain">
				', $txt['password'], ':
				<input type="password" name="passwrd" size="20" class="input_password" />
			</div>
			<div data-role="fieldcontain">
				', $txt['mins_logged_in'], ':
				<input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" />
			</div>
			<div data-role="fieldcontain">
				', $txt['always_logged_in'], ':
				<select name="cookieneverexp" data-role="slider">
					<option value="0">', $txt['no'], '</option>
					<option value="1" ', !empty($context['never_expire']) ? ' selected="selected"' : '', '>', $txt['yes'], '</option>
				</select>
			</div>
		</li>
	</ul>
	<br />
	<input type="submit" value="', $txt['login'], '" />
	<input type="hidden" name="hash_passwrd" value="" />
	<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
</form>';
}

// This is for the security stuff - makes administrators login every so often.
function template_admin_login()
{
	global $context, $settings, $scripturl, $txt;

	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>';

	if (!empty($context['incorrect_password']))
		echo '
	<div>', $txt['admin_incorrect_password'], '</div>';

	echo '
	<form action="', $scripturl, $context['get_data'], '" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin" onsubmit="hash', ucfirst($context['sessionCheckType']), 'Password(this, \'', $context['user']['username'], '\', \'', $context['session_id'], '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');">
		<ul data-role="listview">
			<li><h3>', $txt['login'], '</h3></li>
			<li>
				<div data-role="fieldcontain">
					<strong>', $txt['password'], ':</strong>
					<input type="password" name="', $context['sessionCheckType'], '_pass" size="24" class="input_password" />
				</div>
			</li>
			<li>
				<p><a href="', $scripturl, '?action=helpadmin;help=securityDisable_why" onclick="return reqOverlayDiv(this.href);" class="help"><img class="icon" src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" /></a></p>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-login_token_var'], '" value="', $context['admin-login_token'], '" />
			</li>
		</ul>
		<br />
		<input type="submit" value="', $txt['login'], '" />';

		// Make sure to output all the old post data.
		echo $context['post_data'], '
		<input type="hidden" name="', $context['sessionCheckType'], '_hash_pass" value="" />
	</form>';

}

// Activate your account manually?
function template_retry_activate()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
	<form action="', $scripturl, '?action=activate;u=', $context['member_id'], '" method="post" accept-charset="', $context['character_set'], '">
		<ul data-role="listview">
			<li><h3>', $context['page_title'], '</h3></li>';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
			<li>
				<div data-role="fieldcontain">
					', $txt['invalid_activation_username'], ':
					<input type="text" name="user" size="30" class="input_text" />
				</div>
			</li>';
	echo '
			<li>
				<div data-role="fieldcontain">
					', $txt['invalid_activation_retry'], ':
					<input type="text" name="code" size="30" class="input_text" />
				</div>
			</li>
		</ul>
		<br >/
		<input type="submit" value="', $txt['invalid_activation_submit'], '" />
	</form>';
}

// Activate your account manually?
function template_resend()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;sa=resend" method="post" accept-charset="', $context['character_set'], '">
			<ul data-role="listview">
				<li><h3>', $context['page_title'], '</h3></li>
				<li>
					<div data-role="fieldcontain">
						', $txt['invalid_activation_username'], ':
						<input type="text" name="user" size="40" value="', $context['default_username'], '" class="input_text" />
					</div>
				</li>
				<li>
					<p>', $txt['invalid_activation_new'], '</p>
					<div data-role="fieldcontain">
						', $txt['invalid_activation_new_email'], ':
						<input type="text" name="new_email" size="40" class="input_text" />
					</div>
					<div data-role="fieldcontain">
						', $txt['invalid_activation_password'], ':
						<input type="password" name="passwd" size="30" class="input_password" />
					</div>
				</li>';

	if ($context['can_activate'])
		echo '
				<li>
					<p>', $txt['invalid_activation_known'], '</p>
					<div data-role="fieldcontain">
						', $txt['invalid_activation_retry'], ':
						<input type="text" name="code" size="30" class="input_text" />
					</div>
				</li>';

	echo '
			</ul>
			<br />
			<input type="submit" value="', $txt['invalid_activation_resend'], '" class="button_submit" />
		</form>';
}

?>