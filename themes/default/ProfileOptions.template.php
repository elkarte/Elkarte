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
 * @version 1.0 Alpha
 */

/**
 * Template for showing all the buddies of the current user.
 */
function template_editBuddies()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	$disabled_fields = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	echo '
	<div class="generic_list_wrapper" id="edit_buddies">
		<div class="title_bar">
			<h3 class="titlebg">
				<img src="', $settings['images_url'], '/icons/online.png" alt="" class="icon" />', $txt['editBuddies'], '
			</h3>
		</div>
		<table class="table_grid">
			<tr class="catbg">
				<th class="first_th" scope="col" style="width:20%">', $txt['name'], '</th>
				<th class="centertext" scope="col">', $txt['status'], '</th>';

	if ($context['can_send_email'])
		echo '
				<th class="centertext" scope="col">', $txt['email'], '</th>';

	echo '
				<th class="last_th centertext" scope="col"></th>
			</tr>';

	// If they don't have any buddies don't list them!
	if (empty($context['buddies']))
		echo '
			<tr class="windowbg2">
				<td colspan="8" class="centertext"><strong>', $txt['no_buddies'], '</strong></td>
			</tr>';

	// Now loop through each buddy showing info on each.
	$alternate = false;
	foreach ($context['buddies'] as $buddy)
	{
		echo '
			<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
				<td>', $buddy['link'], '</td>
				<td class="centertext">
					<a href="', $buddy['online']['href'], '"><img src="', $buddy['online']['image_href'], '" alt="', $buddy['online']['text'], '" title="', $buddy['online']['text'], '" /></a>
				</td>';

		if ($context['can_send_email'])
			echo '
				<td class="centertext">', ($buddy['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $buddy['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $buddy['name'] . '" /></a>'), '</td>';

		// If these are off, don't show them
		// @todo this used to show the IM agents for buddies ... do we want to get any custom profile fields to populate here?
		if (isset($buddy_fields))
		{
			foreach ($buddy_fields as $key => $column)
			{
				if (!isset($disabled_fields[$column]))
					echo '
						<td class="centertext">', $buddy[$column]['link'], '</td>';
			}
		}

		echo '
				<td class="centertext">
					<a href="', $scripturl, '?action=profile;area=lists;sa=buddies;u=', $context['id_member'], ';remove=', $buddy['id'], ';', $context['session_var'], '=', $context['session_id'], '"><img src="', $settings['images_url'], '/icons/delete.png" alt="', $txt['buddy_remove'], '" title="', $txt['buddy_remove'], '" /></a>
				</td>
			</tr>';

		$alternate = !$alternate;
	}

	echo '
		</table>
	</div>';

	// Add a new buddy?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=buddies" method="post" accept-charset="UTF-8">
		<div class="tborder add_buddy">
			<div class="title_bar">
				<h3 class="titlebg">', $txt['buddy_add'], '</h3>
			</div>
			<div class="roundframe">
				<dl class="settings">
					<dt>
						<label for="new_buddy"><strong>', $txt['who_member'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="new_buddy" id="new_buddy" size="30" class="input_text" />
						<input type="submit" value="', $txt['buddy_add_button'], '" class="button_submit floatnone" />
					</dd>
				</dl>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />

			</div>
		</div>
	</form>
	<script src="', $settings['default_theme_url'], '/scripts/suggest.js?alp21"></script>
	<script><!-- // --><![CDATA[
		var oAddBuddySuggest = new smc_AutoSuggest({
			sSelf: \'oAddBuddySuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'new_buddy\',
			sControlId: \'new_buddy\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	// ]]></script>';
}

/**
 * Template for showing the ignore list of the current user.
 */
function template_editIgnoreList()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div class="generic_list_wrapper" id="edit_buddies">
		<div class="title_bar">
			<h3 class="titlebg">
				<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['editIgnoreList'], '
			</h3>
		</div>
		<table class="table_grid">
			<tr class="catbg">
				<th class="first_th" scope="col" style="width:20%">', $txt['name'], '</th>
				<th class="centertext" scope="col">', $txt['status'], '</th>';

	if ($context['can_send_email'])
		echo '
				<th class="centertext" scope="col">', $txt['email'], '</th>';

	echo '
				<th class="centertext last_th" scope="col"></th>
			</tr>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty($context['ignore_list']))
		echo '
			<tr class="windowbg2">
				<td colspan="4" class="centertext"><strong>', $txt['no_ignore'], '</strong></td>
			</tr>';

	// Now loop through each buddy showing info on each.
	$alternate = false;
	foreach ($context['ignore_list'] as $member)
	{
		echo '
			<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
				<td>', $member['link'], '</td>
				<td class="centertext"><a href="', $member['online']['href'], '"><img src="', $member['online']['image_href'], '" alt="', $member['online']['text'], '" title="', $member['online']['text'], '" /></a></td>';

		if ($context['can_send_email'])
			echo '
				<td class="centertext">', ($member['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '" /></a>'), '</td>';

		echo '
				<td class="centertext"><a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', $context['session_var'], '=', $context['session_id'], '"><img src="', $settings['images_url'], '/icons/delete.png" alt="', $txt['ignore_remove'], '" title="', $txt['ignore_remove'], '" /></a></td>
			</tr>';

		$alternate = !$alternate;
	}

	echo '
		</table>
	</div>';

	// Add to the ignore list?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="UTF-8">
		<div class="tborder add_buddy">
			<div class="title_bar">
				<h3 class="titlebg">', $txt['ignore_add'], '</h3>
			</div>
			<div class="roundframe">
				<dl class="settings">
					<dt>
						<label for="new_ignore"><strong>', $txt['who_member'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="new_ignore" id="new_ignore" size="25" class="input_text" />
						<input type="submit" value="', $txt['ignore_add_button'], '" class="button_submit floatnone" />
					</dd>
				</dl>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</div>
	</form>
	<script src="', $settings['default_theme_url'], '/scripts/suggest.js?alp21"></script>
	<script><!-- // --><![CDATA[
		var oAddIgnoreSuggest = new smc_AutoSuggest({
			sSelf: \'oAddIgnoreSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sSuggestId: \'new_ignore\',
			sControlId: \'new_ignore\',
			sSearchType: \'member\',
			sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
			bItemList: false
		});
	// ]]></script>';
}

/**
 * Template for editing profile options.
 */
function template_edit_options()
{
	global $context, $settings, $scripturl, $txt;

	// The main header!
	echo '
		<form action="', (!empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member']), '" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data" onsubmit="return checkProfileSubmit();">
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />';

		// Don't say "Profile" if this isn't the profile...
		if (!empty($context['profile_header_text']))
			echo '
					', $context['profile_header_text'];
		else
			echo '
					', $txt['profile'];

		echo '
				</h3>
			</div>';

	// Have we some description?
	if ($context['page_desc'])
		echo '
			<p class="description">', $context['page_desc'], '</p>';

	echo '
			<div class="windowbg2">
				<div class="content">';

	// Any bits at the start?
	if (!empty($context['profile_prehtml']))
		echo '
					<div>', $context['profile_prehtml'], '</div>';

	if (!empty($context['profile_fields']))
		echo '
					<dl>';

	// Start the big old loop 'of love.
	$lastItem = 'hr';
	foreach ($context['profile_fields'] as $key => $field)
	{
		// We add a little hack to be sure we never get more than one hr in a row!
		if ($lastItem == 'hr' && $field['type'] == 'hr')
			continue;

		$lastItem = $field['type'];
		if ($field['type'] == 'hr')
		{
			echo '
					</dl>
					<hr class="hrcolor clear" style="width: 100%; height: 1px" />
					<dl>';
		}
		elseif ($field['type'] == 'callback')
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
							<strong', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['type'] !== 'label' ? '<label for="' . $key . '">' : '', $field['label'], $field['type'] !== 'label' ? '</label>' : '', '</strong>';

			// Does it have any subtext to show?
			if (!empty($field['subtext']))
				echo '
							<br />
							<span class="smalltext">', $field['subtext'], '</span>';

			echo '
						</dt>
						<dd>';

			// Want to put something infront of the box?
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
							<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

			// You "checking" me out? ;)
			elseif ($field['type'] == 'check')
				echo '
							<input type="hidden" name="', $key, '" value="0" /><input type="checkbox" name="', $key, '" id="', $key, '" ', !empty($field['value']) ? ' checked="checked"' : '', ' value="1" class="input_check" ', $field['input_attr'], ' />';

			// Always fun - select boxes!
			elseif ($field['type'] == 'select')
			{
				echo '
							<select name="', $key, '" id="', $key, '">';

				if (isset($field['options']))
				{
					// Is this some code to generate the options?
					if (!is_array($field['options']))
						$field['options'] = eval($field['options']);
					// Assuming we now have some!
					if (is_array($field['options']))
						foreach ($field['options'] as $value => $name)
							echo '
								<option value="', $value, '" ', $value == $field['value'] ? 'selected="selected"' : '', '>', $name, '</option>';
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

	if (!empty($context['profile_fields']))
		echo '
					</dl>';

	// Are there any custom profile fields - if so print them!
	if (!empty($context['custom_fields']))
	{
		if ($lastItem != 'hr')
			echo '
					<hr class="hrcolor clear" style="width: 100%; height: 1px" />';

		echo '
					<dl>';

		foreach ($context['custom_fields'] as $field)
		{
			echo '
						<dt>
							<strong>', $field['name'], ': </strong><br />
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>
							', $field['input_html'], '
						</dd>';
		}

		echo '
					</dl>';

	}

	// Any closing HTML?
	if (!empty($context['profile_posthtml']))
		echo '
					<div>', $context['profile_posthtml'], '</div>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '><label for="oldpasswrd">', $txt['current_password'], ': </label></strong><br />
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" id="oldpasswrd" size="20" style="margin-right: 4ex;" class="input_password" required="required" />
						</dd>
					</dl>';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
					<input type="submit" name="save" value="', $context['submit_button_text'], '" class="button_submit" />';
	else
		echo '
					<input type="submit" name="save" value="', $txt['change_profile'], '" class="button_submit" />';

	if (!empty($context['token_check']))
		echo '
					<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="u" value="', $context['id_member'], '" />
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
				</div>
			</div>
		</form>';

	// Some javascript!
	echo '
		<script><!-- // --><![CDATA[
			function checkProfileSubmit()
			{';

	// If this part requires a password, make sure to give a warning.
	if ($context['require_password'])
		echo '
				// Did you forget to type your password?
				if (document.forms.creator.oldpasswrd.value == "")
				{
					alert("', $txt['required_security_reasons'], '");
					return false;
				}';

	// Any onsubmit javascript?
	if (!empty($context['profile_onsubmit_javascript']))
		echo '
				', $context['profile_javascript'];

	echo '
			}';

	// Any totally custom stuff?
	if (!empty($context['profile_javascript']))
		echo '
			', $context['profile_javascript'];

	echo '
		// ]]></script>';

	// Any final spellchecking stuff?
	if (!empty($context['show_spellchecking']))
		echo '
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="', $scripturl, '?action=spellcheck">
			<input type="hidden" name="spellstring" value="" />
			<input type="hidden" name="fulleditor" value="" />
		</form>
		<script src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js"></script>';
}

/**
 * Personal Message settings.
 */
function template_profile_pm_settings()
{
	global $context, $modSettings, $txt;

	echo '
								<dt>
									<label for="pm_prefs">', $txt['pm_display_mode'], ':</label>
								</dt>
								<dd>
									<select name="pm_prefs" id="pm_prefs" onchange="if (this.value == 2 &amp;&amp; !document.getElementById(\'copy_to_outbox\').checked) alert(\'', $txt['pm_recommend_enable_outbox'], '\');">
										<option value="0"', $context['display_mode'] == 0 ? ' selected="selected"' : '', '>', $txt['pm_display_mode_all'], '</option>
										<option value="1"', $context['display_mode'] == 1 ? ' selected="selected"' : '', '>', $txt['pm_display_mode_one'], '</option>
										<option value="2"', $context['display_mode'] == 2 ? ' selected="selected"' : '', '>', $txt['pm_display_mode_linked'], '</option>
									</select>
								</dd>
								<dt>
									<label for="view_newest_pm_first">', $txt['recent_pms_at_top'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[view_newest_pm_first]" value="0" />
										<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty($context['member']['options']['view_newest_pm_first']) ? ' checked="checked"' : '', ' class="input_check" />
								</dd>
						</dl>
						<hr />
						<dl>
								<dt>
										<label for="pm_receive_from">', $txt['pm_receive_from'], '</label>
								</dt>
								<dd>
										<select name="pm_receive_from" id="pm_receive_from">
												<option value="0"', empty($context['receive_from']) || (empty($modSettings['enable_buddylist']) && $context['receive_from'] < 3) ? ' selected="selected"' : '', '>', $txt['pm_receive_from_everyone'], '</option>';

	if (!empty($modSettings['enable_buddylist']))
		echo '
												<option value="1"', !empty($context['receive_from']) && $context['receive_from'] == 1 ? ' selected="selected"' : '', '>', $txt['pm_receive_from_ignore'], '</option>
												<option value="2"', !empty($context['receive_from']) && $context['receive_from'] == 2 ? ' selected="selected"' : '', '>', $txt['pm_receive_from_buddies'], '</option>';

	echo '
												<option value="3"', !empty($context['receive_from']) && $context['receive_from'] > 2 ? ' selected="selected"' : '', '>', $txt['pm_receive_from_admins'], '</option>
										</select>
								</dd>
								<dt>
										<label for="pm_email_notify">', $txt['email_notify'], '</label>
								</dt>
								<dd>
										<select name="pm_email_notify" id="pm_email_notify">
												<option value="0"', empty($context['send_email']) ? ' selected="selected"' : '', '>', $txt['email_notify_never'], '</option>
												<option value="1"', !empty($context['send_email']) && ($context['send_email'] == 1 || (empty($modSettings['enable_buddylist']) && $context['send_email'] > 1)) ? ' selected="selected"' : '', '>', $txt['email_notify_always'], '</option>';

	if (!empty($modSettings['enable_buddylist']))
		echo '
												<option value="2"', !empty($context['send_email']) && $context['send_email'] > 1 ? ' selected="selected"' : '', '>', $txt['email_notify_buddies'], '</option>';

	echo '
										</select>
								</dd>
								<dt>
										<label for="popup_messages">', $txt['popup_messages'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[popup_messages]" value="0" />
										<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"', !empty($context['member']['options']['popup_messages']) ? ' checked="checked"' : '', ' class="input_check" />
								</dd>
						</dl>
						<hr />
						<dl>
								<dt>
										<label for="copy_to_outbox"> ', $txt['copy_to_outbox'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[copy_to_outbox]" value="0" />
										<input type="checkbox" name="default_options[copy_to_outbox]" id="copy_to_outbox" value="1"', !empty($context['member']['options']['copy_to_outbox']) ? ' checked="checked"' : '', ' class="input_check" />
								</dd>
								<dt>
										<label for="pm_remove_inbox_label">', $txt['pm_remove_inbox_label'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0" />
										<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty($context['member']['options']['pm_remove_inbox_label']) ? ' checked="checked"' : '', ' class="input_check" />
								</dd>';

}

/**
 * Template for showing theme settings. Note: template_options() actually adds the theme specific options.
 */
function template_profile_theme_settings()
{
	global $context, $settings, $modSettings, $txt;

	echo '
							<dt>
								<label for="show_board_desc">', $txt['board_desc_inside'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_board_desc]" value="0" />
								<input type="checkbox" name="default_options[show_board_desc]" id="show_board_desc" value="1"', !empty($context['member']['options']['show_board_desc']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="show_children">', $txt['show_children'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_children]" value="0" />
								<input type="checkbox" name="default_options[show_children]" id="show_children" value="1"', !empty($context['member']['options']['show_children']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="use_sidebar_menu">', $txt['use_sidebar_menu'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_sidebar_menu]" value="0" />
								<input type="checkbox" name="default_options[use_sidebar_menu]" id="use_sidebar_menu" value="1"', !empty($context['member']['options']['use_sidebar_menu']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="use_click_menu">', $txt['use_click_menu'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_click_menu]" value="0" />
								<input type="checkbox" name="default_options[use_click_menu]" id="use_click_menu" value="1"', !empty($context['member']['options']['use_click_menu']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="show_no_avatars">', $txt['show_no_avatars'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_avatars]" value="0" />
								<input type="checkbox" name="default_options[show_no_avatars]" id="show_no_avatars" value="1"', !empty($context['member']['options']['show_no_avatars']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="hide_poster_area">', $txt['hide_poster_area'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[hide_poster_area]" value="0" />
								<input type="checkbox" name="default_options[hide_poster_area]" id="hide_poster_area" value="1"', !empty($context['member']['options']['hide_poster_area']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="show_no_signatures">', $txt['show_no_signatures'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_signatures]" value="0" />
								<input type="checkbox" name="default_options[show_no_signatures]" id="show_no_signatures" value="1"', !empty($context['member']['options']['show_no_signatures']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

	if ($settings['allow_no_censored'])
		echo '
							<dt>
								<label for="show_no_censored">' . $txt['show_no_censored'] . '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_censored]" value="0" />
								<input type="checkbox" name="default_options[show_no_censored]" id="show_no_censored" value="1"' . (!empty($context['member']['options']['show_no_censored']) ? ' checked="checked"' : '') . ' class="input_check" />
							</dd>';

	echo '
							<dt>
								<label for="return_to_post">', $txt['return_to_post'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[return_to_post]" value="0" />
								<input type="checkbox" name="default_options[return_to_post]" id="return_to_post" value="1"', !empty($context['member']['options']['return_to_post']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="no_new_reply_warning">', $txt['no_new_reply_warning'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[no_new_reply_warning]" value="0" />
								<input type="checkbox" name="default_options[no_new_reply_warning]" id="no_new_reply_warning" value="1"', !empty($context['member']['options']['no_new_reply_warning']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

	if (!empty($modSettings['enable_buddylist']))
		echo '
							<dt>
								<label for="posts_apply_ignore_list">', $txt['posts_apply_ignore_list'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[posts_apply_ignore_list]" value="0" />
								<input type="checkbox" name="default_options[posts_apply_ignore_list]" id="posts_apply_ignore_list" value="1"', !empty($context['member']['options']['posts_apply_ignore_list']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

	echo '
							<dt>
								<label for="view_newest_first">', $txt['recent_posts_at_top'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[view_newest_first]" value="0" />
								<input type="checkbox" name="default_options[view_newest_first]" id="view_newest_first" value="1"', !empty($context['member']['options']['view_newest_first']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

	// Choose WYSIWYG settings?
	if (empty($modSettings['disable_wysiwyg']))
		echo '
							<dt>
								<label for="wysiwyg_default">', $txt['wysiwyg_default'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[wysiwyg_default]" value="0" />
								<input type="checkbox" name="default_options[wysiwyg_default]" id="wysiwyg_default" value="1"', !empty($context['member']['options']['wysiwyg_default']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

	if (empty($modSettings['disableCustomPerPage']))
	{
		echo '
							<dt>
								<label for="topics_per_page">', $txt['topics_per_page'], '</label>
							</dt>
							<dd>
								<select name="default_options[topics_per_page]" id="topics_per_page">
									<option value="0"', empty($context['member']['options']['topics_per_page']) ? ' selected="selected"' : '', '>', $txt['per_page_default'], ' (', $modSettings['defaultMaxTopics'], ')</option>
									<option value="5"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 5 ? ' selected="selected"' : '', '>5</option>
									<option value="10"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 10 ? ' selected="selected"' : '', '>10</option>
									<option value="25"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 25 ? ' selected="selected"' : '', '>25</option>
									<option value="50"', !empty($context['member']['options']['topics_per_page']) && $context['member']['options']['topics_per_page'] == 50 ? ' selected="selected"' : '', '>50</option>
								</select>
							</dd>
							<dt>
								<label for="messages_per_page">', $txt['messages_per_page'], '</label>
							</dt>
							<dd>
								<select name="default_options[messages_per_page]" id="messages_per_page">
									<option value="0"', empty($context['member']['options']['messages_per_page']) ? ' selected="selected"' : '', '>', $txt['per_page_default'], ' (', $modSettings['defaultMaxMessages'], ')</option>
									<option value="5"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 5 ? ' selected="selected"' : '', '>5</option>
									<option value="10"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 10 ? ' selected="selected"' : '', '>10</option>
									<option value="25"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 25 ? ' selected="selected"' : '', '>25</option>
									<option value="50"', !empty($context['member']['options']['messages_per_page']) && $context['member']['options']['messages_per_page'] == 50 ? ' selected="selected"' : '', '>50</option>
								</select>
							</dd>';
	}

	if (!empty($modSettings['cal_enabled']))
		echo '
							<dt>
								<label for="calendar_start_day">', $txt['calendar_start_day'], ':</label>
							</dt>
							<dd>
								<select name="default_options[calendar_start_day]" id="calendar_start_day">
									<option value="0"', empty($context['member']['options']['calendar_start_day']) ? ' selected="selected"' : '', '>', $txt['days'][0], '</option>
									<option value="1"', !empty($context['member']['options']['calendar_start_day']) && $context['member']['options']['calendar_start_day'] == 1 ? ' selected="selected"' : '', '>', $txt['days'][1], '</option>
									<option value="6"', !empty($context['member']['options']['calendar_start_day']) && $context['member']['options']['calendar_start_day'] == 6 ? ' selected="selected"' : '', '>', $txt['days'][6], '</option>
								</select>
							</dd>';

	if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_autosave_enabled']))
		echo '
							<dt>
								<label for="drafts_autosave_enabled">', $txt['drafts_autosave_enabled'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[drafts_autosave_enabled]" value="0" />
								<label for="drafts_autosave_enabled"><input type="checkbox" name="default_options[drafts_autosave_enabled]" id="drafts_autosave_enabled" value="1"', !empty($context['member']['options']['drafts_autosave_enabled']) ? ' checked="checked"' : '', ' class="input_check" /></label>
							</dd>';
	if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_show_saved_enabled']))
		echo '
							<dt>
								<label for="drafts_show_saved_enabled">', $txt['drafts_show_saved_enabled'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[drafts_show_saved_enabled]" value="0" />
								<label for="drafts_show_saved_enabled"><input type="checkbox" name="default_options[drafts_show_saved_enabled]" id="drafts_show_saved_enabled" value="1"', !empty($context['member']['options']['drafts_show_saved_enabled']) ? ' checked="checked"' : '', ' class="input_check" /></label>
							</dd>';

	echo '
							<dt>
								<label for="display_quick_reply">', $txt['display_quick_reply'], '</label>
							</dt>
							<dd>
								<select name="default_options[display_quick_reply]" id="display_quick_reply">
									<option value="0"', empty($context['member']['options']['display_quick_reply']) ? ' selected="selected"' : '', '>', $txt['display_quick_reply1'], '</option>
									<option value="1"', !empty($context['member']['options']['display_quick_reply']) && $context['member']['options']['display_quick_reply'] == 1 ? ' selected="selected"' : '', '>', $txt['display_quick_reply2'], '</option>
									<option value="2"', !empty($context['member']['options']['display_quick_reply']) && $context['member']['options']['display_quick_reply'] == 2 ? ' selected="selected"' : '', '>', $txt['display_quick_reply3'], '</option>
								</select>
							</dd>
							<dt>
								<label for="use_editor_quick_reply">', $txt['use_editor_quick_reply'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_editor_quick_reply]" value="0" />
								<label for="use_editor_quick_reply"><input type="checkbox" name="default_options[use_editor_quick_reply]" id="use_editor_quick_reply" value="1"', !empty($context['member']['options']['use_editor_quick_reply']) ? ' checked="checked"' : '', ' class="input_check" /></label>
							</dd>
							<dt>
								<label for="display_quick_mod">', $txt['display_quick_mod'], '</label>
							</dt>
							<dd>
								<select name="default_options[display_quick_mod]" id="display_quick_mod">
									<option value="0"', empty($context['member']['options']['display_quick_mod']) ? ' selected="selected"' : '', '>', $txt['display_quick_mod_none'], '</option>
									<option value="1"', !empty($context['member']['options']['display_quick_mod']) && $context['member']['options']['display_quick_mod'] == 1 ? ' selected="selected"' : '', '>', $txt['display_quick_mod_check'], '</option>
									<option value="2"', !empty($context['member']['options']['display_quick_mod']) && $context['member']['options']['display_quick_mod'] != 1 ? ' selected="selected"' : '', '>', $txt['display_quick_mod_image'], '</option>
								</select>
							</dd>';
}

function template_action_notification()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=notification;save" method="post" accept-charset="UTF-8" id="notify_options" class="flow_hidden">
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['profile'], '
				</h3>
			</div>
			<p class="description">', $txt['notification_info'], '</p>
			<div class="windowbg2">
				<div class="content">
					<dl class="settings">';

	// Allow notification on announcements to be disabled?
	if (!empty($modSettings['allow_disableAnnounce']))
		echo '
						<dt>
							<label for="notify_announcements">', $txt['notify_important_email'], '</label>
						</dt>
						<dd>
							<input type="hidden" name="notify_announcements" value="0" />
							<input type="checkbox" id="notify_announcements" name="notify_announcements"', !empty($context['member']['notify_announcements']) ? ' checked="checked"' : '', ' class="input_check" />
						</dd>';

	// More notification options.
	echo '
						<dt>
							<label for="auto_notify">', $txt['auto_notify'], '</label>
						</dt>
						<dd>
							<input type="hidden" name="default_options[auto_notify]" value="0" />
							<input type="checkbox" id="auto_notify" name="default_options[auto_notify]" value="1"', !empty($context['member']['options']['auto_notify']) ? ' checked="checked"' : '', ' class="input_check" />
						</dd>';

	if (empty($modSettings['disallow_sendBody']))
		echo '
						<dt>
							<label for="notify_send_body">', $txt['notify_send_body'], '</label>
						</dt>
						<dd>
							<input type="hidden" name="notify_send_body" value="0" />
							<input type="checkbox" id="notify_send_body" name="notify_send_body"', !empty($context['member']['notify_send_body']) ? ' checked="checked"' : '', ' class="input_check" />
						</dd>';

	echo '
						<dt>
							<label for="notify_regularity">', $txt['notify_regularity'], ':</label>
						</dt>
						<dd>
							<select name="notify_regularity" id="notify_regularity">
								<option value="0"', $context['member']['notify_regularity'] == 0 ? ' selected="selected"' : '', '>', $txt['notify_regularity_instant'], '</option>
								<option value="1"', $context['member']['notify_regularity'] == 1 ? ' selected="selected"' : '', '>', $txt['notify_regularity_first_only'], '</option>
								<option value="2"', $context['member']['notify_regularity'] == 2 ? ' selected="selected"' : '', '>', $txt['notify_regularity_daily'], '</option>
								<option value="3"', $context['member']['notify_regularity'] == 3 ? ' selected="selected"' : '', '>', $txt['notify_regularity_weekly'], '</option>
							</select>
						</dd>
						<dt>
							<label for="notify_types">', $txt['notify_send_types'], ':</label>
						</dt>
						<dd>
							<select name="notify_types" id="notify_types">';

	if (!empty($modSettings['pbe_no_mod_notices']) && !empty($modSettings['maillist_enabled']))
	{
		echo '
								<option value="1"', $context['member']['notify_types'] == 1 ? ' selected="selected"' : '', '>', $txt['notify_send_type_everything'], '</option>
								<option value="2"', $context['member']['notify_types'] == 2 ? ' selected="selected"' : '', '>', $txt['notify_send_type_everything_own'], '</option>';
	}

	echo '
								<option value="3"', $context['member']['notify_types'] == 3 ? ' selected="selected"' : '', '>', $txt['notify_send_type_only_replies'], '</option>
								<option value="4"', $context['member']['notify_types'] == 4 ? ' selected="selected"' : '', '>', $txt['notify_send_type_nothing'], '</option>
							</select>
						</dd>
					</dl>
					<hr class="hrcolor" />
					<div>
						<input id="notify_submit" type="submit" value="', $txt['notify_save'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />', !empty($context['token_check']) ? '
						<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '" />' : '', '
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>
				</div>
			</div>
		</form>
		<br />';

	template_show_list('board_notification_list');

	echo '
		<br />';

	template_show_list('topic_notification_list');
}

/**
 * Template for choosing group membership.
 */
function template_groupMembership()
{
	global $context, $settings, $scripturl, $txt;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=groupmembership;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['profile'], '
				</h3>
			</div>
			<p class="description">', $txt['groupMembership_info'], '</p>';

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
			<div class="infobox">
				', $context['update_message'], '.
			</div>';

	// Requesting membership to a group?
	if (!empty($context['group_request']))
	{
		echo '
			<div class="groupmembership">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['request_group_membership'], '</h3>
				</div>
				<div class="roundframe">
					', $txt['request_group_membership_desc'], ':
					<textarea name="reason" rows="4" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 99%; min-width: 99%' : 'width: 99%') . ';"></textarea>
					<div class="righttext" style="margin: 0.5em 0.5% 0 0.5%;">
						<input type="hidden" name="gid" value="', $context['group_request']['id'], '" />
						<input type="submit" name="req" value="', $txt['submit_request'], '" class="button_submit" />
					</div>
				</div>
			</div>';
	}
	else
	{
		echo '
			<table style="width:100%" class="table_padding">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col" ', $context['can_edit_primary'] ? ' colspan="2"' : '', '>', $txt['current_membergroups'], '</th>
						<th class="last_th" scope="col"></th>
					</tr>
				</thead>
				<tbody>';

		$alternate = true;
		foreach ($context['groups']['member'] as $group)
		{
			echo '
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '" id="primdiv_', $group['id'], '">';

				if ($context['can_edit_primary'])
					echo '
						<td style="width:4%">
							<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '" ', $group['is_primary'] ? 'checked="checked"' : '', ' onclick="highlightSelected(\'primdiv_' . $group['id'] . '\');" ', $group['can_be_primary'] ? '' : 'disabled="disabled"', ' class="input_radio" />
						</td>';

				echo '
						<td>
							<label for="primary_', $group['id'], '"><strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br /><span class="smalltext">' . $group['desc'] . '</span>' : ''), '</label>
						</td>
						<td style="width:15%" class="righttext">';

				// Can they leave their group?
				if ($group['can_leave'])
					echo '
							<a href="' . $scripturl . '?action=profile;save;u=' . $context['id_member'] . ';area=groupmembership;' . $context['session_var'] . '=' . $context['session_id'] . ';gid=' . $group['id'] . ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">' . $txt['leave_group'] . '</a>';
				echo '
						</td>
					</tr>';
			$alternate = !$alternate;
		}

		echo '
				</tbody>
			</table>';

		if ($context['can_edit_primary'])
			echo '
			<input type="submit" value="', $txt['make_primary'], '" class="button_submit" />';

		// Any groups they can join?
		if (!empty($context['groups']['available']))
		{
			echo '
			<br />
			<table style="width:100%" class="table_padding">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">
							', $txt['available_groups'], '
						</th>
						<th class="last_th" scope="col"></th>
					</tr>
				</thead>
				<tbody>';

			$alternate = true;
			foreach ($context['groups']['available'] as $group)
			{
				echo '
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
						<td>
							<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br /><span class="smalltext">' . $group['desc'] . '</span>' : ''), '
						</td>
						<td style="width:15%" class="lefttext">';

				if ($group['type'] == 3)
					echo '
							<a href="', $scripturl, '?action=profile;save;u=', $context['id_member'], ';area=groupmembership;', $context['session_var'], '=', $context['session_id'], ';gid=', $group['id'], ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">', $txt['join_group'], '</a>';
				elseif ($group['type'] == 2 && $group['pending'])
					echo '
							', $txt['approval_pending'];
				elseif ($group['type'] == 2)
					echo '
							<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=groupmembership;request=', $group['id'], '">', $txt['request_group'], '</a>';
// @todo
//				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

				echo '
						</td>
					</tr>';
				$alternate = !$alternate;
			}
			echo '
				</tbody>
			</table>';
		}

		// Javascript for the selector stuff.
		echo '
		<script><!-- // --><![CDATA[
			var prevClass = "";
			var prevDiv = "";';
		if (isset($context['groups']['member'][$context['primary_group']]))
			echo '
			highlightSelected("primdiv_' . $context['primary_group'] . '");';
		echo '
		// ]]></script>';
	}

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="u" value="', $context['id_member'], '" />
			</form>';
}

function template_ignoreboards()
{
	global $context, $txt, $settings, $scripturl;
	// The main containing header.
	echo '
	<form action="', $scripturl, '?action=profile;area=ignoreboards;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['profile'], '
			</h3>
		</div>
		<p class="description">', $txt['ignoreboards_info'], '</p>
		<div class="windowbg2">
			<div class="content flow_hidden">
				<ul class="ignoreboards floatleft">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);
	foreach ($context['categories'] as $category)
	{
		if ($i == $limit)
		{
			echo '
				</ul>
				<ul class="ignoreboards floatright">';

			$i++;
		}

		echo '
					<li class="category">
						<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'creator\'); return false;">', $category['name'], '</a>
						<ul>';

		foreach ($category['boards'] as $board)
		{
			if ($i == $limit)
				echo '
						</ul>
					</li>
				</ul>
				<ul class="ignoreboards floatright">
					<li class="category">
						<ul>';

			echo '
							<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
								<label for="ignore_brd', $board['id'], '"><input type="checkbox" id="ignore_brd', $board['id'], '" name="ignore_brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> ', $board['name'], '</label>
							</li>';

			$i++;
		}

		echo '
						</ul>
					</li>';
	}

	echo '
				</ul>';

	// Show the standard "Save Settings" profile button.
	template_profile_save();

	echo '
			</div>
		</div>
	</form>
	<br />';
}

/**
 * Display a load of drop down selectors for allowing the user to change group.
 */
function template_profile_group_manage()
{
	global $context, $txt, $scripturl;

	echo '
							<dt>
								<strong>', $txt['primary_membergroup'], ': </strong><br />
								<span class="smalltext">[<a href="', $scripturl, '?action=quickhelp;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);">', $txt['moderator_why_missing'], '</a>]</span>
							</dt>
							<dd>
								<select name="id_group" ', ($context['user']['is_owner'] && $context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . $txt['deadmin_confirm'] . '\')) this.value = 1;"' : ''), '>';

		// Fill the select box with all primary member groups that can be assigned to a member.
		foreach ($context['member_groups'] as $member_group)
			if (!empty($member_group['can_be_primary']))
				echo '
									<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected="selected"' : '', '>
										', $member_group['name'], '
									</option>';
		echo '
								</select>
							</dd>
							<dt>
								<strong>', $txt['additional_membergroups'], ':</strong>
							</dt>
							<dd>
								<span id="additional_groupsList">
									<input type="hidden" name="additional_groups[]" value="0" />';

		// For each membergroup show a checkbox so members can be assigned to more than one group.
		foreach ($context['member_groups'] as $member_group)
			if ($member_group['can_be_additional'])
				echo '
									<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked="checked"' : '', ' class="input_check" /> ', $member_group['name'], '</label><br />';
		echo '
								</span>
								<a href="javascript:void(0);" onclick="document.getElementById(\'additional_groupsList\').style.display = \'block\'; document.getElementById(\'additional_groupsLink\').style.display = \'none\'; return false;" id="additional_groupsLink" style="display: none;">', $txt['additional_membergroups_show'], '</a>
								<script><!-- // --><![CDATA[
									document.getElementById("additional_groupsList").style.display = "none";
									document.getElementById("additional_groupsLink").style.display = "";
								// ]]></script>
							</dd>';

}

/**
 * Callback function for entering a birthdate!
 */
function template_profile_birthdate()
{
	global $txt, $context;

	// Just show the pretty box!
	echo '
							<dt>
								<strong>', $txt['dob'], ':</strong><br />
								<span class="smalltext">', $txt['dob_year'], ' - ', $txt['dob_month'], ' - ', $txt['dob_day'], '</span>
							</dt>
							<dd>
								<input type="text" name="bday3" size="4" maxlength="4" value="', $context['member']['birth_date']['year'], '" class="input_text" /> -
								<input type="text" name="bday1" size="2" maxlength="2" value="', $context['member']['birth_date']['month'], '" class="input_text" /> -
								<input type="text" name="bday2" size="2" maxlength="2" value="', $context['member']['birth_date']['day'], '" class="input_text" />
							</dd>';
}

/**
 * Show the signature editing box.
 */
function template_profile_signature_modify()
{
	global $txt, $context;

	echo '
							<dt id="current_signature"', !isset($context['member']['current_signature']) ? ' style="display:none"' : '', '>
								<strong>', $txt['current_signature'], ':</strong>
							</dt>
							<dd id="current_signature_display"', !isset($context['member']['current_signature']) ? ' style="display:none"' : '', '>
								', isset($context['member']['current_signature']) ? $context['member']['current_signature'] : '', '<hr />
							</dd>';
	echo '
							<dt id="preview_signature"', !isset($context['member']['signature_preview']) ? ' style="display:none"' : '', '>
								<strong>', $txt['signature_preview'], ':</strong>
							</dt>
							<dd id="preview_signature_display"', !isset($context['member']['signature_preview']) ? ' style="display:none"' : '', '>
								', isset($context['member']['signature_preview']) ? $context['member']['signature_preview'] : '', '<hr />
							</dd>';

	echo '
							<dt>
								<strong>', $txt['signature'], ':</strong><br />
								<span class="smalltext">', $txt['sig_info'], '</span>
								<br />
								<br />
							</dt>
							<dd>
								<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50" style="min-width: 50%; max-width: 99%;">', $context['member']['signature'], '</textarea><br />';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
								<span class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></span><br />';

	if ($context['show_spellchecking'])
		echo '
						<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'creator\', \'signature\', false);"  tabindex="', $context['tabindex']++, '" class="button_submit" />';
	if (!empty($context['show_preview_button']))
		echo '
						<input type="submit" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '"  tabindex="', $context['tabindex']++, '" class="button_submit" />';

	if ($context['signature_warning'])
		echo '
								<span class="smalltext">', $context['signature_warning'], '</span>';


	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
								<script><!-- // --><![CDATA[
									var maxLength = ', $context['signature_limits']['max_length'], ';

									$(document).ready(function() {
										calcCharLeft();
										$("#preview_button").click(function() {
											return ajax_getSignaturePreview(true);
										});
									});
								// ]]></script>
							</dd>';
}

/**
 * Interface to select an avatar in profile.
 */
function template_profile_avatar_select()
{
	global $context, $txt, $modSettings;

	// Start with left side menu
	echo '
							<dt>
								<strong id="personal_picture"><label for="avatar_upload_box">', $txt['personal_picture'], '</label></strong>
								<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . ' class="input_radio" />
								<label for="avatar_choice_none"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . $txt['no_avatar'] . '
								</label><br />
								', !empty($context['member']['avatar']['allow_server_stored']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . ($context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') . ' class="input_radio" />
								<label for="avatar_choice_server_stored"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . $txt['choose_avatar_gallery'] . '
								</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_external']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . ' class="input_radio" />
								<label for="avatar_choice_external"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . $txt['my_own_pic'] . '
								</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_gravatar']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"' . ($context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') . ' class="input_radio" />
								<label for="avatar_choice_gravatar"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . $txt['gravatar'] . '
								</label><br />' : '', '
								', !empty($context['member']['avatar']['allow_upload']) ? '<input type="radio" onclick="swap_avatar(this); return true;" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . ' class="input_radio" />
								<label for="avatar_choice_upload"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
									' . $txt['avatar_will_upload'] . '
								</label>' : '', '
							</dt>
							<dd>';

	// If users are allowed to choose avatars stored on the server show the selection boxes to choose them.
	if (!empty($context['member']['avatar']['allow_server_stored']))
	{
		echo '
								<div id="avatar_server_stored">
									<div>
										<select name="cat" id="cat" size="10" onchange="changeSel(\'\');" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');">';

		// This lists all the file catergories.
		foreach ($context['avatars'] as $avatar)
			echo '
											<option value="', $avatar['filename'] . ($avatar['is_dir'] ? '/' : ''), '"', ($avatar['checked'] ? ' selected="selected"' : ''), '>', $avatar['name'], '</option>';
		echo '
										</select>
									</div>
									<div>
										<select name="file" id="file" size="10" style="display: none;" onchange="showAvatar()" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'server_stored\');" disabled="disabled">
											<option></option>
										</select>
									</div>
									<div>
										<img id="avatar" src="',  $modSettings['avatar_url'] . '/blank.png', '" alt="" />
									</div>
								</div>';
	}

	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty($context['member']['avatar']['allow_external']))
	{
		echo '
								<div id="avatar_external">
									<div class="smalltext">', $txt['avatar_by_url'], '</div>
									<input type="text" name="userpicpersonal" size="45" value="', $context['member']['avatar']['external'], '" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'external\');" onchange="if (typeof(previewExternalAvatar) != \'undefined\') previewExternalAvatar(this.value, \'external\');" class="input_text" />
									<br /><br />
									<img id="external" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $modSettings['avatar_url'] . '/blank.png', '" alt="" ', !empty($modSettings['avatar_max_height_external']) ? 'height="' . $modSettings['avatar_max_height_external'] . 'px"' : '', !empty($modSettings['avatar_max_width_external']) ? 'width="' . $modSettings['avatar_max_width_external'] . 'px"' : '', '/>
								</div>';
	}

	// If the user is allowed to use a Gravatar.
	if (!empty($context['member']['avatar']['allow_gravatar']))
	{
		echo '
								<div id="avatar_gravatar">
									<br /><br />
									<img src="' . $context['member']['avatar']['gravatar_preview'] . '" alt="" />
								</div>';
	}

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
	{
		echo '
								<div id="avatar_upload">
									<input type="file" size="44" name="attachment" id="avatar_upload_box" value="" onfocus="selectRadioByName(document.forms.creator.avatar_choice, \'upload\');" class="input_file" />
									', ($context['member']['avatar']['id_attach'] > 0 ? '
									<br /><br />
									<img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '" alt="" />
									<input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '" />' : ''), '
								</div>';
	}

	echo '
								<script><!-- // --><![CDATA[
									var files = ["' . implode('", "', $context['avatar_list']) . '"],
										avatar = document.getElementById("avatar"),
										cat = document.getElementById("cat"),
										selavatar = "' . $context['avatar_selected'] . '",
										avatardir = "' . $modSettings['avatar_url'] . '/",
										size = avatar.alt.substr(3, 2) + " " + avatar.alt.substr(0, 2) + String.fromCharCode(117, 98, 116),
										file = document.getElementById("file"),
										maxHeight = ', !empty($modSettings['avatar_max_height_external']) ? $modSettings['avatar_max_height_external'] : 0, ',
										maxWidth = ', !empty($modSettings['avatar_max_width_external']) ? $modSettings['avatar_max_width_external'] : 0, ';

									if (avatar.src.indexOf("blank.png") > -1)
										changeSel(selavatar);
									else
										previewExternalAvatar(avatar.src)

									// Display the right avatar box based on what they are using
									', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "' . ($context['member']['avatar']['choice'] == 'server_stored' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "' . ($context['member']['avatar']['choice'] == 'external' ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "' . (($context['member']['avatar']['choice'] == 'gravatar' || (empty($context['member']['avatar']['allow_gravatar']))) ? '' : 'none') . '";' : '', '
									', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "' . ($context['member']['avatar']['choice'] == 'upload' ? '' : 'none') . '";' : '', '

									// Show the right avatar based on what radio button they just selected
									function swap_avatar(type)
									{
										switch(type.id)
										{
											case "avatar_choice_server_stored":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												break;
											case "avatar_choice_external":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												break;
											case "avatar_choice_gravatar":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "";' : '', '
												break;
											case "avatar_choice_upload":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "";' : '', '
												break;
											case "avatar_choice_none":
												', !empty($context['member']['avatar']['allow_server_stored']) ? 'document.getElementById("avatar_server_stored").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_external']) ? 'document.getElementById("avatar_external").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_gravatar']) ? 'document.getElementById("avatar_gravatar").style.display = "none";' : '', '
												', !empty($context['member']['avatar']['allow_upload']) ? 'document.getElementById("avatar_upload").style.display = "none";' : '', '
												break;
										}
									}
								// ]]></script>
							</dd>';
}

/**
 * Callback for modifying karam.
 */
function template_profile_karma_modify()
{
	global $context, $modSettings, $txt;

		echo '
							<dt>
								<strong>', $modSettings['karmaLabel'], '</strong>
							</dt>
							<dd>
								', $modSettings['karmaApplaudLabel'], ' <input type="text" name="karma_good" size="4" value="', $context['member']['karma']['good'], '" onchange="setInnerHTML(document.getElementById(\'karmaTotal\'), this.value - this.form.karma_bad.value);" style="margin-right: 2ex;" class="input_text" /> ', $modSettings['karmaSmiteLabel'], ' <input type="text" name="karma_bad" size="4" value="', $context['member']['karma']['bad'], '" onchange="this.form.karma_good.onchange();" class="input_text" /><br />
								(', $txt['total'], ': <span id="karmaTotal">', ($context['member']['karma']['good'] - $context['member']['karma']['bad']), '</span>)
							</dd>';
}

/**
 * Select the time format!.
 */
function template_profile_timeformat_modify()
{
	global $context, $txt, $scripturl, $settings;

	echo '
							<dt>
								<strong><label for="easyformat">', $txt['time_format'], ':</label></strong><br />
								<a href="', $scripturl, '?action=quickhelp;help=time_format" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" alt="', $txt['help'], '" class="floatleft" /></a>
								<span class="smalltext">&nbsp;<label for="time_format">', $txt['date_format'], '</label></span>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;" style="margin-bottom: 4px;">';

	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
									<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected="selected"' : '', '>', $time_format['title'], '</option>';
	echo '
								</select><br />
								<input type="text" name="time_format" id="time_format" value="', $context['member']['time_format'], '" size="30" class="input_text" />
							</dd>';
}

/**
 * Time offset.
 */
function template_profile_timeoffset_modify()
{
	global $txt, $context;

	echo '
							<dt>
								<strong', (isset($context['modify_error']['bad_offset']) ? ' class="error"' : ''), '><label for="time_offset">', $txt['time_offset'], ':</label></strong><br />
								<span class="smalltext">', $txt['personal_time_offset'], '</span>
							</dt>
							<dd>
								<input type="text" name="time_offset" id="time_offset" size="5" maxlength="5" value="', $context['member']['time_offset'], '" class="input_text" /> ', $txt['hours'], ' [<a href="javascript:void(0);" onclick="currentDate = new Date(', $context['current_forum_time_js'], '); document.getElementById(\'time_offset\').value = autoDetectTimeOffset(currentDate); return false;">', $txt['timeoffset_autodetect'], '</a>]<br />', $txt['current_time'], ': <em>', $context['current_forum_time'], '</em>
							</dd>';
}

/**
 * Interface to allow the member to pick a theme.
 */
function template_profile_theme_pick()
{
	global $txt, $context, $scripturl;

	echo '
							<dt>
								<strong>', $txt['current_theme'], ':</strong>
							</dt>
							<dd>
								', $context['member']['theme']['name'], ' [<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['change'], '</a>]
							</dd>';
}

/**
 * Smiley set picker.
 */
function template_profile_smiley_pick()
{
	global $txt, $context, $modSettings, $settings;

	echo '
							<dt>
								<strong><label for="smiley_set">', $txt['smileys_current'], ':</label></strong>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set" onchange="document.getElementById(\'smileypr\').src = this.selectedIndex == 0 ? \'', $settings['images_url'], '/blank.png\' : \'', $modSettings['smileys_url'], '/\' + (this.selectedIndex != 1 ? this.options[this.selectedIndex].value : \'', !empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'], '\') + \'/smiley.gif\';">';
	foreach ($context['smiley_sets'] as $set)
		echo '
									<option value="', $set['id'], '"', $set['selected'] ? ' selected="selected"' : '', '>', $set['name'], '</option>';
	echo '
								</select> <img id="smileypr" class="centericon" src="', $context['member']['smiley_set']['id'] != 'none' ? $modSettings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] : (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'])) . '/smiley.gif' : $settings['images_url'] . '/blank.png', '" alt=":)"  style="padding-left: 20px;" />
							</dd>';
}

/**
 * Interface to allow the member to change the way they login to the forum.
 */
function template_authentication_method()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// The main header!
	echo '
		<script src="', $settings['default_theme_url'], '/scripts/register.js"></script>
		<form action="', $scripturl, '?action=profile;area=authentication;save" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data">
			<div class="cat_bar">
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['authentication'], '
				</h3>
			</div>
			<p class="description">', $txt['change_authentication'], '</p>
			<div class="windowbg2">
				<div class="content">
					<dl>
						<dt>
							<input type="radio" onclick="updateAuthMethod();" name="authenticate" value="openid" id="auth_openid"', $context['auth_method'] == 'openid' ? ' checked="checked"' : '', ' class="input_radio" /><label for="auth_openid"><strong>', $txt['authenticate_openid'], '</strong></label>&nbsp;<em><a href="', $scripturl, '?action=quickhelp;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a></em><br />
							<input type="radio" onclick="updateAuthMethod();" name="authenticate" value="passwd" id="auth_pass"', $context['auth_method'] == 'password' ? ' checked="checked"' : '', ' class="input_radio" /><label for="auth_pass"><strong>', $txt['authenticate_password'], '</strong></label>
						</dt>
						<dd>
							<dl id="auth_openid_div">
								<dt>
									<em>', $txt['authenticate_openid_url'], ':</em>
								</dt>
								<dd>
									<input type="text" name="openid_identifier" id="openid_url" size="30" tabindex="', $context['tabindex']++, '" value="', $context['member']['openid_uri'], '" class="input_text openid_login" />
								</dd>
							</dl>
							<dl id="auth_pass_div">
								<dt>
									<em>', $txt['choose_pass'], ':</em>
								</dt>
								<dd>
									<input type="password" name="passwrd1" id="smf_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '" class="input_password" placeholder="', $txt['choose_pass'], '" />
									<span id="smf_autov_pwmain_div" style="display: none;"><img id="smf_autov_pwmain_img" class="centericon" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" /></span>
								</dd>
								<dt>
									<em>', $txt['verify_pass'], ':</em>
								</dt>
								<dd>
									<input type="password" name="passwrd2" id="smf_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '" class="input_password" placeholder="', $txt['verify_pass'], '" />
									<span id="smf_autov_pwverify_div" style="display: none;"><img id="smf_autov_pwverify_img" class="centericon" src="', $settings['images_url'], '/icons/field_valid.png" alt="*" /></span>
								</dd>
							</dl>
						</dd>
					</dl>';

	if ($context['require_password'])
		echo '
					<hr class="hrcolor clear" style="width: 100%; height: 1px" />
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong><br />
							<span class="smalltext">', $txt['required_security_reasons'], '</span>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" tabindex="', $context['tabindex']++, '" size="20" style="margin-right: 4ex;" class="input_password" placeholder="', $txt['current_password'], '" required="required" />
						</dd>
					</dl>';

	echo '
					<hr class="hrcolor" />';

	if (!empty($context['token_check']))
		echo '
					<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="u" value="', $context['id_member'], '" />
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					<input type="submit" value="', $txt['change_profile'], '" class="button_submit" />
				</div>
			</div>
		</form>';

	// The password stuff.
	echo '
	<script><!-- // --><![CDATA[
	var regTextStrings = {
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	var verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
	var currentAuthMethod = \'passwd\';
	updateAuthMethod();
	// ]]></script>';
}