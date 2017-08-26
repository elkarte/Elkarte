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
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Template to edit and add bans
 */
function template_ban_edit()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="', $context['form_url'], '" method="post" accept-charset="UTF-8" onsubmit="return confirmBan(this);">
			<h2 class="category_header">
				', $context['ban']['is_new'] ? $txt['ban_add_new'] : $txt['ban_edit'] . ' \'' . $context['ban']['name'] . '\'', '
			</h2>';

	if ($context['ban']['is_new'])
		echo '
			<div class="information">', $txt['ban_add_notes'], '</div>';

	// If there were errors creating the ban, show them.
	template_show_error('ban_errors');

	echo '
			<div class="content">
				<dl class="settings">
					<dt id="ban_name_label">
						<label for="ban_name">', $txt['ban_name'], '</label>:
					</dt>
					<dd>
						<input type="text" id="ban_name" name="ban_name" value="', $context['ban']['name'], '" size="45" maxlength="60" class="input_text" />
					</dd>';

	if (isset($context['ban']['reason']))
		echo '
				<dt>
					<label for="reason">', $txt['ban_reason'], ':</label><br />
					<span class="smalltext">', $txt['ban_reason_desc'], '</span>
				</dt>
				<dd>
					<textarea name="reason" id="reason" cols="40" rows="3" class="ban_text">', $context['ban']['reason'], '</textarea>
				</dd>';

	if (isset($context['ban']['notes']))
		echo '
				<dt>
					<label for="ban_notes">', $txt['ban_notes'], ':</label><br />
					<span class="smalltext">', $txt['ban_notes_desc'], '</span>
				</dt>
				<dd>
					<textarea name="notes" id="ban_notes" cols="40" rows="3" class="ban_text">', $context['ban']['notes'], '</textarea>
				</dd>';

	echo '
				</dl>
				<fieldset class="ban_settings floatleft">
					<legend>
						', $txt['ban_expiration'], '
					</legend>
					<input type="radio" name="expiration" value="never" id="never_expires" onclick="fUpdateStatus();"', $context['ban']['expiration']['status'] == 'never' ? ' checked="checked"' : '', ' /> <label for="never_expires">', $txt['never'], '</label><br />
					<input type="radio" name="expiration" value="one_day" id="expires_one_day" onclick="fUpdateStatus();"', $context['ban']['expiration']['status'] == 'one_day' ? ' checked="checked"' : '', ' /> <label for="expires_one_day">', $txt['ban_will_expire_within'], '</label>: <input type="text" name="expire_date" id="expire_date" size="3" value="', $context['ban']['expiration']['days'], '" class="input_text" /> ', $txt['ban_days'], '<br />
					<input type="radio" name="expiration" value="expired" id="already_expired" onclick="fUpdateStatus();"', $context['ban']['expiration']['status'] == 'expired' ? ' checked="checked"' : '', ' /> <label for="already_expired">', $txt['ban_expired'], '</label>
				</fieldset>
				<fieldset class="ban_settings floatright">
					<legend>
						', $txt['ban_restriction'], '
					</legend>
					<input type="radio" name="full_ban" id="full_ban" value="1" onclick="fUpdateStatus();"', $context['ban']['cannot']['access'] ? ' checked="checked"' : '', ' /> <label for="full_ban">', $txt['ban_full_ban'], '</label><br />
					<input type="radio" name="full_ban" id="partial_ban" value="0" onclick="fUpdateStatus();"', !$context['ban']['cannot']['access'] ? ' checked="checked"' : '', ' /> <label for="partial_ban">', $txt['ban_partial_ban'], '</label><br />
					<input type="checkbox" name="cannot_post" id="cannot_post" value="1"', $context['ban']['cannot']['post'] ? ' checked="checked"' : '', ' class="ban_restriction input_radio" /> <label for="cannot_post">', $txt['ban_cannot_post'], '</label><a href="', $scripturl, '?action=quickhelp;help=ban_cannot_post" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a><br />
					<input type="checkbox" name="cannot_register" id="cannot_register" value="1"', $context['ban']['cannot']['register'] ? ' checked="checked"' : '', ' class="ban_restriction input_radio" /> <label for="cannot_register">', $txt['ban_cannot_register'], '</label><br />
					<input type="checkbox" name="cannot_login" id="cannot_login" value="1"', $context['ban']['cannot']['login'] ? ' checked="checked"' : '', ' class="ban_restriction input_radio" /> <label for="cannot_login">', $txt['ban_cannot_login'], '</label><br />
				</fieldset>';

	if (!empty($context['ban_suggestions']))
	{
		echo '
				<fieldset class="clear">
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, \'ban_suggestion\');" class="input_check"> ', $txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip" ', !empty($context['ban_suggestions']['main_ip']) ? 'checked="checked" ' : '', '/>
							<label for="main_ip_check">', $txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', $context['ban_suggestions']['main_ip'], '" size="44" onfocus="document.getElementById(\'main_ip_check\').checked = true;" class="input_text" />
						</dd>';

		if (empty($modSettings['disableHostnameLookup']))
			echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname" ', !empty($context['ban_suggestions']['hostname']) ? 'checked="checked" ' : '', '/>
							<label for="hostname_check">', $txt['ban_on_hostname'], '</label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="', $context['ban_suggestions']['hostname'], '" size="44" onfocus="document.getElementById(\'hostname_check\').checked = true;" class="input_text" />
						</dd>';

		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email" ', !empty($context['ban_suggestions']['email']) ? 'checked="checked" ' : '', '/>
							<label for="email_check">', $txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="text" name="email" value="', $context['ban_suggestions']['email'], '" size="44" onfocus="document.getElementById(\'email_check\').checked = true;" class="input_text" />
						</dd>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user" ', !empty($context['ban_suggestions']['user']) || isset($context['ban']['from_user']) ? 'checked="checked" ' : '', '/>
							<label for="user_check">', $txt['ban_on_username'], '</label>:
						</dt>
						<dd>
							<input type="text" ', !empty($context['ban']['from_user']) ? 'readonly="readonly" value="' . $context['ban_suggestions']['member']['name'] . '"' : ' value="' . (isset($context['ban_suggestions']['member']['name']) ? $context['ban_suggestions']['member']['name'] : '') . '"', ' name="user" id="user" size="44" class="input_text" />
						</dd>
					</dl>';

		if (!empty($context['ban_suggestions']['other_ips']))
		{
			foreach ($context['ban_suggestions']['other_ips'] as $key => $ban_ips)
			{
				if (!empty($ban_ips))
				{
					echo '
					<div>', $txt[$key], ':</div>
					<dl class="settings">';

					$count = 0;
					foreach ($ban_ips as $ip)
						echo '
						<dt>
							<input type="checkbox" id="suggestions_', $key, '_', $count, '" name="ban_suggestions[', $key, '][]" ', !empty($context['ban_suggestions']['saved_triggers'][$key]) && in_array($ip, $context['ban_suggestions']['saved_triggers'][$key]) ? 'checked="checked" ' : '', 'value="', $ip, '" />
						</dt>
						<dd>
							<label for="suggestions_', $key, '_', ($count++), '">', $ip, '</label>
						</dd>';

					echo '
					</dl>';
				}
			}
		}

		echo '
				</fieldset>';
	}

	echo '
				<div class="submitbutton clear">
					<input type="submit" name="', $context['ban']['is_new'] ? 'add_ban' : 'modify_ban', '" value="', $context['ban']['is_new'] ? $txt['ban_add'] : $txt['ban_modify'], '" />
					<input type="hidden" name="old_expire" value="', $context['ban']['expiration']['days'], '" />
					<input type="hidden" name="bg" value="', $context['ban']['id'], '" />', isset($context['ban']['from_user']) ? '
					<input type="hidden" name="u" value="' . $context['ban_suggestions']['member']['id'] . '" />' : '', '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-bet_token_var'], '" value="', $context['admin-bet_token'], '" />
				</div>
			</div>
		</form>';

	if (!$context['ban']['is_new'] && empty($context['ban_suggestions']))
	{
		echo '
		<br />';

		template_show_list('ban_items');
	}

	echo '
	</div>';

	// Auto suggest only needed for adding new bans, not editing
	if (!empty($context['use_autosuggest']))
		echo '
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'user\',
			sControlId: \'user\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});

		oAddMemberSuggest.registerCallback(\'onBeforeUpdate\', \'onUpdateName\');
	</script>';
}

/**
 * Template to edit ban triggers
 */
function template_ban_edit_trigger()
{
	global $context, $txt, $modSettings;

	echo '
	<div id="manage_bans">
		<form id="admin_form_wrapper" action="', $context['form_url'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">
				', $context['ban_trigger']['is_new'] ? $txt['ban_add_trigger'] : $txt['ban_edit_trigger_title'], '
			</h2>
			<div class="content">
				<fieldset>
					<legend>
						<input type="checkbox" onclick="invertAll(this, this.form, \'ban_suggestion\');" class="input_check"> ', $txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="main_ip_check" value="main_ip" ', $context['ban_trigger']['ip']['selected'] ? 'checked="checked" ' : '', '/>
							<label for="main_ip_check">', $txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', $context['ban_trigger']['ip']['value'], '" size="44" onfocus="document.getElementById(\'main_ip_check\').checked = true;" class="input_text" />
						</dd>';

	if (empty($modSettings['disableHostnameLookup']))
		echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="hostname_check" value="hostname" ', $context['ban_trigger']['hostname']['selected'] ? 'checked="checked" ' : '', '/>
							<label for="hostname_check">', $txt['ban_on_hostname'], '</label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="', $context['ban_trigger']['hostname']['value'], '" size="44" onfocus="document.getElementById(\'hostname_check\').checked = true;" class="input_text" />
						</dd>';

	echo '
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="email_check" value="email" ', $context['ban_trigger']['email']['selected'] ? 'checked="checked" ' : '', '/>
							<label for="email_check">', $txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="text" name="email" value="', $context['ban_trigger']['email']['value'], '" size="44" onfocus="document.getElementById(\'email_check\').checked = true;" class="input_text" />
						</dd>
						<dt>
							<input type="checkbox" name="ban_suggestions[]" id="user_check" value="user" ', $context['ban_trigger']['banneduser']['selected'] ? 'checked="checked" ' : '', '/>
							<label for="user_check">', $txt['ban_on_username'], '</label>:
						</dt>
						<dd>
							<input type="text" value="' . $context['ban_trigger']['banneduser']['value'] . '" name="user" id="user" size="44"  onfocus="document.getElementById(\'user_check\').checked = true;"class="input_text" />
						</dd>
					</dl>
				</fieldset>
				<div class="submitbutton">
					<input type="submit" name="', $context['ban_trigger']['is_new'] ? 'add_new_trigger' : 'edit_trigger', '" value="', $context['ban_trigger']['is_new'] ? $txt['ban_add_trigger_submit'] : $txt['ban_edit_trigger_submit'], '" />
				</div>
			</div>
			<input type="hidden" name="bi" value="' . $context['ban_trigger']['id'] . '" />
			<input type="hidden" name="bg" value="' . $context['ban_trigger']['group'] . '" />
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
			<input type="hidden" name="', $context['admin-bet_token_var'], '" value="', $context['admin-bet_token'], '" />
		</form>
	</div>

	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'username\',
			sControlId: \'user\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});

		oAddMemberSuggest.registerCallback(\'onBeforeUpdate\', \'onUpdateName\');
	</script>';
}
