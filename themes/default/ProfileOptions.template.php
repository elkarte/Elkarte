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
 * We need this template in order to look at ignored boards
 */
function template_ProfileOptions_init()
{
	loadTemplate('GenericBoards');
}

/**
 * Template for showing all the buddies of the current user.
 */
function template_editBuddies()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="edit_buddies">
		<h2 class="category_header hdicon cat_img_buddies">
			', $txt['editBuddies'], '
		</h2>
		<table class="table_grid">
			<tr class="table_head">
				<th scope="col" class="grid20">', $txt['name'], '</th>
				<th scope="col">', $txt['status'], '</th>';

	if ($context['can_send_email'])
		echo '
				<th scope="col">', $txt['email'], '</th>';

	echo '
				<th scope="col">', $txt['profile_contact'], '</th>
				<th scope="col"></th>
			</tr>';

	// If they don't have any buddies don't list them!
	if (empty($context['buddies']))
		echo '
			<tr>
				<td colspan="5" class="centertext">
					<strong>', $txt['no_buddies'], '</strong>
				</td>
			</tr>';

	// Now loop through each buddy showing info on each.
	foreach ($context['buddies'] as $buddy)
	{
		echo '
			<tr>
				<td>', $buddy['link'], '</td>
				<td>
					', template_member_online($buddy), '
				</td>';

		if ($context['can_send_email'])
			echo '
				<td>', template_member_email($buddy), '</td>';

		//  Any custom profile (with icon) fields to show
		$im = array();
		if (!empty($buddy['custom_fields']))
		{

			foreach ($buddy['custom_fields'] as $key => $cpf)
				if ($cpf['placement'] == 1)
					$im[] = $cpf['value'];
		}

		echo '
				<td>' . implode(' | ', $im) . '</td>';

		echo '
				<td class="righttext">
					<a href="', $scripturl, '?action=profile;area=lists;sa=buddies;u=', $context['id_member'], ';remove=', $buddy['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="icon i-remove" title="', $txt['buddy_remove'], '"></a>
				</td>
			</tr>';
	}

	echo '
		</table>
	</div>';

	// Add a new buddy?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=buddies" method="post" accept-charset="UTF-8">
		<div class="add_buddy">
			<h2 class="category_header">', $txt['buddy_add'], '</h2>
			<div class="well">
				<dl class="settings">
					<dt>
						<label for="new_buddy">', $txt['who_member'], '</label>
					</dt>
					<dd>
						<input type="text" name="new_buddy" id="new_buddy" size="30" class="input_text" />
						<input type="submit" value="', $txt['buddy_add_button'], '" />
					</dd>
				</dl>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</div>
	</form>';

	// Initialize the autosuggest object
	addInlineJavascript('
		var oAddBuddySuggest = new smc_AutoSuggest({
			sSelf: \'oAddBuddySuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'new_buddy\',
			sControlId: \'new_buddy\',
			sSearchType: \'member\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			bItemList: false
		});', true);
}

/**
 * Template for showing the ignore list of the current user.
 */
function template_editIgnoreList()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="edit_buddies">
		<h2 class="category_header hdicon cat_img_profile">
			', $txt['editIgnoreList'], '
		</h2>
		<table class="table_grid">
			<tr class="table_head">
				<th scope="col" style="width: 20%;">', $txt['name'], '</th>
				<th scope="col">', $txt['status'], '</th>';

	if ($context['can_send_email'])
		echo '
				<th scope="col">', $txt['email'], '</th>';

	echo '
				<th scope="col"></th>
			</tr>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty($context['ignore_list']))
		echo '
			<tr>
				<td colspan="4" class="centertext">
					<strong>', $txt['no_ignore'], '</strong>
				</td>
			</tr>';

	// Now loop through each buddy showing info on each.
	foreach ($context['ignore_list'] as $member)
	{
		echo '
			<tr>
				<td>', $member['link'], '</td>
				<td>
					', template_member_online($member), '
				</td>';

		if ($context['can_send_email'])
			echo '
				<td>', template_member_email($member), '</td>';

		echo '
				<td class="righttext">
					<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="icon i-remove" title="', $txt['ignore_remove'], '">
					</a>
				</td>
			</tr>';
	}

	echo '
		</table>
	</div>';

	// Add to the ignore list?
	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="UTF-8">
		<div class="add_buddy">
			<h2 class="category_header">', $txt['ignore_add'], '</h2>
			<div class="well">
				<dl class="settings">
					<dt>
						<label for="new_ignore">', $txt['who_member'], '</label>
					</dt>
					<dd>
						<input type="text" name="new_ignore" id="new_ignore" size="25" class="input_text" />
						<input type="submit" value="', $txt['ignore_add_button'], '" />
					</dd>
				</dl>';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</div>
	</form>';

	addInlineJavascript('
		var oAddIgnoreSuggest = new smc_AutoSuggest({
			sSelf: \'oAddIgnoreSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'new_ignore\',
			sControlId: \'new_ignore\',
			sSearchType: \'member\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			bItemList: false
		});', true);
}

/**
 * Template for editing profile options.
 */
function template_edit_options()
{
	global $context, $scripturl, $txt;

	// The main header!
	echo '
		<form action="', (!empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : $scripturl . '?action=profile;area=' . $context['menu_item_selected'] . ';u=' . $context['id_member']), '" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data" onsubmit="return checkProfileSubmit();">
			<h2 class="category_header hdicon cat_img_profile">';

	// Don't say "Profile" if this isn't the profile...
	if (!empty($context['profile_header_text']))
		echo '
				', $context['profile_header_text'];
	else
		echo '
				', $txt['profile'];

	echo '
			</h2>';

	// Have we some description?
	if ($context['page_desc'])
		echo '
			<p class="description">', $context['page_desc'], '</p>';

	echo '
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
		if ($lastItem === 'hr' && $field['type'] === 'hr')
			continue;

		$lastItem = $field['type'];
		if ($field['type'] === 'hr')
		{
			echo '
				</dl>
				<hr class="clear" />
				<dl>';
		}
		elseif ($field['type'] === 'callback')
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
						<label', !empty($field['is_error']) ? ' class="error"' : '', ' for="' . $key . '">', $field['label'], '</label>';

			// Does it have any subtext to show?
			if (!empty($field['subtext']))
				echo '
						<p class="smalltext">', $field['subtext'], '</p>';

			echo '
					</dt>
					<dd>';

			// Want to put something in front of the box?
			if (!empty($field['preinput']))
				echo '
						', $field['preinput'];

			// What type of data are we showing?
			if ($field['type'] === 'label')
				echo '
						', $field['value'];

			// Maybe it's a text box - very likely!
			elseif (in_array($field['type'], array('int', 'float', 'text', 'password')))
				echo '
						<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

			// Maybe it's an html5 input
			elseif (in_array($field['type'], array('url', 'search', 'date', 'email', 'color')))
				echo '
						<input type="', $field['type'], '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

			// You "checking" me out? ;)
			elseif ($field['type'] === 'check')
				echo '
						<input type="hidden" name="', $key, '" value="0" /><input type="checkbox" name="', $key, '" id="', $key, '" ', !empty($field['value']) ? ' checked="checked"' : '', ' value="1" ', $field['input_attr'], ' />';

			// Always fun - select boxes!
			elseif ($field['type'] === 'select')
			{
				echo '
						<select name="', $key, '" id="', $key, '">';

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
		if ($lastItem !== 'hr')
			echo '
				<hr class="clear" />';

		echo '
				<dl>';

		foreach ($context['custom_fields'] as $field)
		{
			echo '
					<dt>
						<strong>', $field['name'], '</strong><br />
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
	template_profile_save();

	echo '
			</div>
		</form>';

	// Some javascript!
	echo '
		<script>
			function checkProfileSubmit()
			{';

	// If this part requires a password, make sure to give a warning.
	if ($context['require_password'])
		echo '
				// Did you forget to type your password?
				if (document.forms.creator.oldpasswrd.value === "")
				{
					alert("', $txt['required_security_reasons'], '");
					return false;
				}';

	// Any onsubmit javascript?
	if (!empty($context['profile_onsubmit_javascript']))
		echo '
				', $context['profile_onsubmit_javascript'];

	echo '
			}
		</script>';

	// Any final spellchecking stuff?
	if (!empty($context['show_spellchecking']))
		echo '
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="', $scripturl, '?action=spellcheck">
			<input type="hidden" id="spellstring" name="spellstring" value="" />
			<input type="hidden" id="fulleditor" name="fulleditor" value="" />
		</form>';
}

/**
 * Personal Message settings.
 */
function template_profile_pm_settings()
{
	global $context, $modSettings, $txt;

	echo '
							<dt>
								<label for="pm_settings">', $txt['pm_display_mode'], '</label>
							</dt>
							<dd>
								<select name="pm_settings" id="pm_settings">
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
								<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty($context['member']['options']['view_newest_pm_first']) ? ' checked="checked"' : '', ' />
							</dd>
						</dl>
						<dl>
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
										<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"', !empty($context['member']['options']['popup_messages']) ? ' checked="checked"' : '', ' />
								</dd>
						</dl>
						<dl>
								<dt>
										<label for="pm_remove_inbox_label">', $txt['pm_remove_inbox_label'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0" />
										<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty($context['member']['options']['pm_remove_inbox_label']) ? ' checked="checked"' : '', ' />
								</dd>';
}

/**
 * Template for showing theme settings. Note: template_options() actually adds the theme specific options.
 */
function template_profile_theme_settings()
{
	global $context, $modSettings, $txt;

	echo '
							<dt>
								<label for="use_sidebar_menu">', $txt['use_sidebar_menu'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_sidebar_menu]" value="0" />
								<input type="checkbox" name="default_options[use_sidebar_menu]" id="use_sidebar_menu" value="1"', !empty($context['member']['options']['use_sidebar_menu']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="use_click_menu">', $txt['use_click_menu'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_click_menu]" value="0" />
								<input type="checkbox" name="default_options[use_click_menu]" id="use_click_menu" value="1"', !empty($context['member']['options']['use_click_menu']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="show_no_avatars">', $txt['show_no_avatars'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_avatars]" value="0" />
								<input type="checkbox" name="default_options[show_no_avatars]" id="show_no_avatars" value="1"', !empty($context['member']['options']['show_no_avatars']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="hide_poster_area">', $txt['hide_poster_area'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[hide_poster_area]" value="0" />
								<input type="checkbox" name="default_options[hide_poster_area]" id="hide_poster_area" value="1"', !empty($context['member']['options']['hide_poster_area']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="show_no_signatures">', $txt['show_no_signatures'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_signatures]" value="0" />
								<input type="checkbox" name="default_options[show_no_signatures]" id="show_no_signatures" value="1"', !empty($context['member']['options']['show_no_signatures']) ? ' checked="checked"' : '', ' />
							</dd>';

	if ($context['allow_no_censored'])
		echo '
							<dt>
								<label for="show_no_censored">', $txt['show_no_censored'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[show_no_censored]" value="0" />
								<input type="checkbox" name="default_options[show_no_censored]" id="show_no_censored" value="1"' . (!empty($context['member']['options']['show_no_censored']) ? ' checked="checked"' : '') . ' />
							</dd>';

	echo '
							<dt>
								<label for="return_to_post">', $txt['return_to_post'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[return_to_post]" value="0" />
								<input type="checkbox" name="default_options[return_to_post]" id="return_to_post" value="1"', !empty($context['member']['options']['return_to_post']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="no_new_reply_warning">', $txt['no_new_reply_warning'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[no_new_reply_warning]" value="0" />
								<input type="checkbox" name="default_options[no_new_reply_warning]" id="no_new_reply_warning" value="1"', !empty($context['member']['options']['no_new_reply_warning']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="wysiwyg_default">', $txt['wysiwyg_default'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[wysiwyg_default]" value="0" />
								<input type="checkbox" name="default_options[wysiwyg_default]" id="wysiwyg_default" value="1"', !empty($context['member']['options']['wysiwyg_default']) ? ' checked="checked"' : '', ' />
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
								<label for="calendar_start_day">', $txt['calendar_start_day'], '</label>
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
								<label for="drafts_autosave_enabled"><input type="checkbox" name="default_options[drafts_autosave_enabled]" id="drafts_autosave_enabled" value="1"', !empty($context['member']['options']['drafts_autosave_enabled']) ? ' checked="checked"' : '', ' /></label>
							</dd>';

	echo '
							<dt>
								<label for="display_quick_reply">', $txt['display_quick_reply'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[display_quick_reply]" value="0" />
								<input type="checkbox" name="default_options[display_quick_reply]" id="display_quick_reply" value="1"', !empty($context['member']['options']['display_quick_reply']) ? ' checked="checked"' : '', ' />
							</dd>
							<dt>
								<label for="use_editor_quick_reply">', $txt['use_editor_quick_reply'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="default_options[use_editor_quick_reply]" value="0" />
								<input type="checkbox" name="default_options[use_editor_quick_reply]" id="use_editor_quick_reply" value="1"', !empty($context['member']['options']['use_editor_quick_reply']) ? ' checked="checked"' : '', ' />
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

/**
 * Template for setting up how and what you want to be notified about
 */
function template_action_notification()
{
	global $context, $txt, $scripturl, $modSettings;

	// The main containing header.
	echo '
		<form id="creator" class="flow_hidden" action="', $scripturl, '?action=profile;area=notification" method="post" accept-charset="UTF-8">
			<h2 class="category_header hdicon cat_img_profile">
				', $txt['profile'], '
			</h2>
			<p class="description">', $txt['notification_info'], '</p>
			<div class="content">
				<dl>';

	foreach ($context['mention_types'] as $type => $mentions)
	{
		echo '
					<dt>
						<label for="notify_', $type, '">', $txt['notify_type_' . $type], '</label>
					</dt>
					<dd>
						<input id="notify_', $type, '" name="notify[', $type, '][status]" class="toggle_notify" type="checkbox" value="1" ', $mentions['enabled'] ? 'checked="checked"' : '', '/>
						<label id="notify_', $type, '_method" for="notify_', $type, '_method">', $txt['notify_method'], '
							<select name="notify[', $type, '][method]">';

		foreach ($mentions['data'] as $key => $method)
		{
			echo '
								<option value="', $key, '"', $method['enabled'] ? ' selected="selected"' : '', '>', $txt['notify_' . $method['id']], '</option>';
		}

		echo '
							</select>
						</label>
					</dd>';
	}

	echo '
				</dl>
				<dl>';

	// Allow notification on announcements to be disabled?
	if (!empty($modSettings['allow_disableAnnounce']))
		echo '
					<dt>
						<label for="notify_announcements">', $txt['notify_important_email'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="notify_announcements" value="0" />
						<input type="checkbox" id="notify_announcements" name="notify_announcements"', !empty($context['member']['notify_announcements']) ? ' checked="checked"' : '', ' />
					</dd>';

	// Auto notification when you reply / start a topic?
	echo '
					<dt>
						<label for="auto_notify">', $txt['auto_notify'], '</label>
					</dt>
					<dd>
						<input type="hidden" name="default_options[auto_notify]" value="0" />
						<input type="checkbox" id="auto_notify" name="default_options[auto_notify]" value="1"', !empty($context['member']['options']['auto_notify']) ? ' checked="checked"' : '', ' />
						', (!empty($modSettings['maillist_enabled']) ? $txt['auto_notify_pbe_post'] : ''), '
					</dd>';

	// Can the body of the post be sent, PBE will ensure it can
	if (empty($modSettings['disallow_sendBody']))
		echo '
					<dt>
						<label for="notify_send_body">', $txt['notify_send_body' . (!empty($modSettings['maillist_enabled']) ? '_pbe' : '')], '</label>
					</dt>
					<dd>
						<input type="hidden" name="notify_send_body" value="0" />
						<input type="checkbox" id="notify_send_body" name="notify_send_body"', !empty($context['member']['notify_send_body']) ? ' checked="checked"' : '', ' />
						', (!empty($modSettings['maillist_enabled']) ? $txt['notify_send_body_pbe_post'] : ''), '
					</dd>';

	// How often do you want to hear from us, instant, daily, weekly?
	echo '
					<dt>
						<label for="notify_regularity">', $txt['notify_regularity'], '</label>
					</dt>
					<dd>
						<select name="notify_regularity" id="notify_regularity">
							<option value="0"', $context['member']['notify_regularity'] == 0 ? ' selected="selected"' : '', '>', $txt['notify_regularity_instant'], '</option>
							<option value="1"', $context['member']['notify_regularity'] == 1 ? ' selected="selected"' : '', '>', $txt['notify_regularity_first_only'], '</option>
							<option value="2"', $context['member']['notify_regularity'] == 2 ? ' selected="selected"' : '', '>', $txt['notify_regularity_daily'], '</option>
							<option value="3"', $context['member']['notify_regularity'] == 3 ? ' selected="selected"' : '', '>', $txt['notify_regularity_weekly'], '</option>
							<option value="99"', $context['member']['notify_regularity'] == 99 ? ' selected="selected"' : '', '>', $txt['notify_regularity_none'], '</option>
						</select>
					</dd>
					<dt>
						<label for="notify_types">', $txt['notify_send_types'], '</label>
					</dt>
					<dd>
						<select name="notify_types" id="notify_types">';

	// Using the maillist functions, then limit the options so they make sense
	if (empty($modSettings['maillist_enabled']) || (empty($modSettings['pbe_no_mod_notices']) && !empty($modSettings['maillist_enabled'])))
	{
		echo '
							<option value="1"', $context['member']['notify_types'] == 1 ? ' selected="selected"' : '', '>', $txt['notify_send_type_everything'], '</option>
							<option value="2"', $context['member']['notify_types'] == 2 ? ' selected="selected"' : '', '>', $txt['notify_send_type_everything_own'], '</option>';
	}

	echo '
							<option value="3"', $context['member']['notify_types'] == 3 ? ' selected="selected"' : '', '>', $txt['notify_send_type_only_replies' . (!empty($modSettings['maillist_enabled']) ? '_pbe' : '')], '</option>
							<option value="4"', $context['member']['notify_types'] == 4 ? ' selected="selected"' : '', '>', $txt['notify_send_type_nothing'], '</option>
						</select>
					</dd>
				</dl>
				<div class="submitbutton">
					<input id="notify_submit" name="notify_submit" type="submit" value="', $txt['notify_save'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />', !empty($context['token_check']) ? '
					<input type="hidden" name="' . $context[$context['token_check'] . '_token_var'] . '" value="' . $context[$context['token_check'] . '_token'] . '" />' : '', '
					<input type="hidden" name="u" value="', $context['id_member'], '" />
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					<input type="hidden" name="save" value="save" />
				</div>
			</div>
		</form>';

	// Show the boards you have on notify
	template_show_list('board_notification_list');

	echo '
		<br />';

	// And any topics you are watching as well
	template_show_list('topic_notification_list');
}

/**
 * Template for choosing group membership.
 */
function template_groupMembership()
{
	global $context, $scripturl, $txt;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=groupmembership" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<h2 class="category_header hdicon cat_img_profile">
				', $txt['profile'], '
			</h2>
			<p class="description">', $txt['groupMembership_info'], '</p>';

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
			<div class="successbox">
				', $context['update_message'], '
			</div>';

	// Requesting membership to a group?
	if (!empty($context['group_request']))
	{
		echo '
			<div class="groupmembership">
				<h2 class="category_header">', $txt['request_group_membership'], '</h2>
				<div class="well">
					', $txt['request_group_membership_desc'], ':
					<textarea name="reason" rows="4" style="width: 99%;"></textarea>
					<div class="submitbutton">
						<input type="hidden" name="gid" value="', $context['group_request']['id'], '" />
						<input type="submit" name="req" value="', $txt['submit_request'], '" />
					</div>
				</div>
			</div>';
	}
	else
	{
		echo '
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th scope="col" ', $context['can_edit_primary'] ? ' colspan="2"' : '', '>', $txt['current_membergroups'], '</th>
						<th scope="col"></th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['groups']['member'] as $group)
		{
			echo '
					<tr  id="primdiv_', $group['id'], '">';

			if ($context['can_edit_primary'])
				echo '
						<td>
							<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '" ', $group['is_primary'] ? 'checked="checked" ' : '', $group['can_be_primary'] ? '' : 'disabled="disabled" ', ' />
						</td>';

			echo '
						<td>
							<label for="primary_', $group['id'], '">
								<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br /><span class="smalltext">' . $group['desc'] . '</span>' : ''), '
							</label>
						</td>
						<td class="grid17 righttext">';

			// Can they leave their group?
			if ($group['can_leave'])
				echo '
							<a class="linkbutton" href="' . $scripturl . '?action=profile;save;u=' . $context['id_member'] . ';area=groupmembership;' . $context['session_var'] . '=' . $context['session_id'] . ';gid=' . $group['id'] . ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">' . $txt['leave_group'] . '</a>';
			echo '
						</td>
					</tr>';
		}

		echo '
				</tbody>
			</table>';

		if ($context['can_edit_primary'])
			echo '
			<div class="submitbutton">
				<input type="submit" value="', $txt['make_primary'], '" />
			</div>';

		// Any groups they can join?
		if (!empty($context['groups']['available']))
		{
			echo '
			<br />
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th scope="col">
							', $txt['available_groups'], '
						</th>
						<th scope="col"></th>
					</tr>
				</thead>
				<tbody>';

			foreach ($context['groups']['available'] as $group)
			{
				echo '
					<tr>
						<td>
							<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '<br /><span class="smalltext">' . $group['desc'] . '</span>' : ''), '
						</td>
						<td class="lefttext">';

				if ($group['type'] == 3)
					echo '
							<a class="linkbutton_right" href="', $scripturl, '?action=profile;save;u=', $context['id_member'], ';area=groupmembership;', $context['session_var'], '=', $context['session_id'], ';gid=', $group['id'], ';', $context[$context['token_check'] . '_token_var'], '=', $context[$context['token_check'] . '_token'], '">', $txt['join_group'], '</a>';
				elseif ($group['type'] == 2 && $group['pending'])
					echo '
							', $txt['approval_pending'];
				elseif ($group['type'] == 2)
					echo '
							<a class="linkbutton_right" href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=groupmembership;request=', $group['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['request_group'], '</a>';
// @todo
//				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

				echo '
						</td>
					</tr>';
			}

			echo '
				</tbody>
			</table>';
		}

		// Javascript for the selector stuff.
		echo '
		<script>
			var prevClass = "",
				prevDiv = "";';

		if (isset($context['groups']['member'][$context['primary_group']]))
			echo '
			initHighlightSelection("primdiv_' . $context['primary_group'] . '");';

		echo '
		</script>';
	}

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="save" value="save" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="u" value="', $context['id_member'], '" />
			</form>';
}

/**
 * Display a list of boards so a user can choose to ignore some
 */
function template_ignoreboards()
{
	global $txt, $scripturl;

	// The main containing header.
	echo '
	<form id="creator" action="', $scripturl, '?action=profile;area=ignoreboards" method="post" accept-charset="UTF-8" name="creator">
		<h2 class="category_header hdicon cat_img_profile">
			', $txt['profile'], '
		</h2>
		<p class="description">', $txt['ignoreboards_info'], '</p>
		<div class="content flow_hidden">';

	template_pick_boards('creator', 'ignore_brd', false);

	// Show the standard "Save Settings" profile button.
	template_profile_save();

	echo '
			<input type="hidden" name="save" value="save" />
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
								<label>', $txt['primary_membergroup'], '</label>
								<p class="smalltext">[<a href="', $scripturl, '?action=quickhelp;help=moderator_why_missing" onclick="return reqOverlayDiv(this.href);">', $txt['moderator_why_missing'], '</a>]</p>
							</dt>
							<dd>
								<select name="id_group" ', ($context['user']['is_owner'] && $context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1 &amp;&amp; !confirm(\'' . $txt['deadmin_confirm'] . '\')) this.value = 1;"' : ''), '>';

	// Fill the select box with all primary membergroups that can be assigned to a member.
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
								<label>', $txt['additional_membergroups'], '</label>
							</dt>
							<dd>
								<input type="hidden" name="additional_groups[]" value="0" />
								<fieldset id="additional_groupsList">
									<legend data-collapsed="true">', $txt['additional_membergroups_show'], '</legend>
									<ul>';

	// For each membergroup show a checkbox so members can be assigned to more than one group.
	foreach ($context['member_groups'] as $member_group)
		if ($member_group['can_be_additional'])
			echo '
										<li>
											<label for="additional_groups-', $member_group['id'], '"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked="checked"' : '', ' /> ', $member_group['name'], '</label>
										</li>';

	echo '
									</ul>
								</fieldset>
							</dd>';
}

/**
 * Callback function for entering a birth date!
 */
function template_profile_birthdate()
{
	global $txt, $context;

	// Just show the pretty box!
	echo '
							<dt>
								<label>', $txt['dob'], '</label>
								<p class="smalltext">', $txt['dob_year'], ' - ', $txt['dob_month'], ' - ', $txt['dob_day'], '</p>
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
							<dt id="current_signature"', !isset($context['member']['current_signature']) ? ' class="hide"' : '', '>
								<label>', $txt['current_signature'], ':</label>
							</dt>
							<dd id="current_signature_display"', !isset($context['member']['current_signature']) ? ' class="hide"' : '', '>
								', isset($context['member']['current_signature']) ? $context['member']['current_signature'] : '', '<hr />
							</dd>

							<dt id="preview_signature"', !isset($context['member']['signature_preview']) ? ' class="hide"' : '', '>
								<label>', $txt['signature_preview'], ':</label>
							</dt>
							<dd id="preview_signature_display"', !isset($context['member']['signature_preview']) ? ' class="hide"' : '', '>
								', isset($context['member']['signature_preview']) ? $context['member']['signature_preview'] : '', '<hr />
							</dd>
							<dt>
								<label>', $txt['signature'], '</label>
								<p class="smalltext">', $txt['sig_info'], '</p>
							</dt>
							<dd>
								<textarea class="editor" onkeyup="calcCharLeft();" id="signature" name="signature" rows="5" cols="50" style="min-width: 50%; width: 99%;">', $context['member']['signature'], '</textarea>';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
								<p class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></p>';

	if ($context['show_spellchecking'])
		echo '
								<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'creator\', \'signature\', false);"  tabindex="', $context['tabindex']++, '" class="right_submit" />';

	if (!empty($context['show_preview_button']))
		echo '
								<input type="submit" name="preview_signature" id="preview_button" value="', $txt['preview_signature'], '"  tabindex="', $context['tabindex']++, '" class="right_submit" />';

	if ($context['signature_warning'])
		echo '
								<span class="smalltext">', $context['signature_warning'], '</span>';

	// Some javascript used to count how many characters have been used so far in the signature.
	echo '
								<script>
									var maxLength = ', $context['signature_limits']['max_length'], ';

									$(function() {
										calcCharLeft();
										$("#preview_button").click(function() {
											return ajax_getSignaturePreview(true);
										});
									});
								</script>
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
								<label id="personal_picture">', $txt['personal_picture'], '</label>
								<ul id="avatar_choices">
									<li>
										<input type="radio" onclick="swap_avatar();" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked="checked"' : '') . ' />
										<label for="avatar_choice_none"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
											' . $txt['no_avatar'] . '
										</label>
									</li>', !empty($context['member']['avatar']['allow_server_stored']) ? '
									<li>
										<input type="radio" onclick="swap_avatar();" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . ($context['member']['avatar']['choice'] == 'server_stored' ? ' checked="checked"' : '') . ' />
										<label for="avatar_choice_server_stored"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
											' . $txt['choose_avatar_gallery'] . '
										</label>
									</li>' : '', !empty($context['member']['avatar']['allow_external']) ? '
									<li>
										<input type="radio" onclick="swap_avatar();" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked="checked"' : '') . ' />
										<label for="avatar_choice_external"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
											' . $txt['my_own_pic'] . '
										</label>
									</li>' : '', !empty($context['member']['avatar']['allow_gravatar']) ? '
									<li>
										<input type="radio" onclick="swap_avatar();" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"' . ($context['member']['avatar']['choice'] == 'gravatar' ? ' checked="checked"' : '') . ' />
										<label for="avatar_choice_gravatar"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
											' . $txt['gravatar'] . '
										</label>
									</li>' : '', !empty($context['member']['avatar']['allow_upload']) ? '
									<li>
										<input type="radio" onclick="swap_avatar();" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked="checked"' : '') . ' />
										<label for="avatar_choice_upload"' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>
											' . $txt['avatar_will_upload'] . '
										</label>
									</li>' : '', '
								</ul>
							</dt>
							<dd>';

	// If users are allowed to choose avatars stored on the server show the selection boxes to choose them.
	if (!empty($context['member']['avatar']['allow_server_stored']))
	{
		echo '
								<div id="avatar_server_stored">
									<div>
										<select name="cat" id="cat" size="10" onchange="changeSel(\'\');">';

		// This lists all the file categories.
		foreach ($context['avatars'] as $avatar)
			echo '
											<option value="', $avatar['filename'] . ($avatar['is_dir'] ? '/' : ''), '"', ($avatar['checked'] ? ' selected="selected"' : ''), '>', $avatar['name'], '</option>';
		echo '
										</select>
									</div>
									<div>
										<select id="file" name="file" size="10" class="hide" onchange="showAvatar()" disabled="disabled">
											<option> </option>
										</select>
									</div>
									<div>
										<img id="avatar" src="', $modSettings['avatar_url'] . '/blank.png', '" alt="" />
									</div>
								</div>';
	}

	// If the user can link to an off server avatar, show them a box to input the address.
	if (!empty($context['member']['avatar']['allow_external']))
	{
		echo '
								<div id="avatar_external">
									<div class="smalltext">
										<label for="userpicpersonal">', $txt['avatar_by_url'], '</label>
									</div>
									<input type="text" id="userpicpersonal" name="userpicpersonal" value="', $context['member']['avatar']['external'], '" onchange="previewExternalAvatar(this.value);" class="input_text" />
									<br /><br />
									<img id="external" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $modSettings['avatar_url'] . '/blank.png', '" alt="" ', !empty($modSettings['avatar_max_height']) ? 'height="' . $modSettings['avatar_max_height'] . '" ' : '', !empty($modSettings['avatar_max_width']) ? 'width="' . $modSettings['avatar_max_width'] . '"' : '', '/>
								</div>';
	}

	// If the user is allowed to use a Gravatar.
	if (!empty($context['member']['avatar']['allow_gravatar']))
	{
		echo '
								<div id="avatar_gravatar">
									<img src="' . $context['member']['avatar']['gravatar_preview'] . '" alt="" />
								</div>';
	}

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
	{
		echo '
								<div id="avatar_upload">
									<input type="file" name="attachment" id="avatar_upload_box" class="input_file" />
									', ($context['member']['avatar']['id_attach'] > 0 ? '
									<br /><br />
									<img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '" alt="" />
									<input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '" />' : ''), '
								</div>';
	}

	echo '
								<script>
									var files = ["' . implode('", "', $context['avatar_list']) . '"],
										cat = document.getElementById("cat"),
										file = document.getElementById("file"),
										selavatar = "' . $context['avatar_selected'] . '",
										avatardir = "' . $modSettings['avatar_url'] . '/",
										refuse_too_large = ', !empty($modSettings['avatar_action_too_large']) && $modSettings['avatar_action_too_large'] == 'option_refuse' ? 'true' : 'false', ',
										maxHeight = ', !empty($modSettings['avatar_max_height']) ? $modSettings['avatar_max_height'] : 0, ',
										maxWidth = ', !empty($modSettings['avatar_max_width']) ? $modSettings['avatar_max_width'] : 0, ';

									// Display the right avatar box based on what they are using
									init_avatars();
								</script>
							</dd>';
}

/**
 * Callback for modifying karma.
 */
function template_profile_karma_modify()
{
	global $context, $modSettings, $txt;

	echo '
							<dt>
								<label>', $modSettings['karmaLabel'], '</label>
							</dt>
							<dd>
								<label for="karma_good">', $modSettings['karmaApplaudLabel'], '</label> <input type="text" id="karma_good" name="karma_good" size="4" value="', $context['member']['karma']['good'], '" style="margin-right: 2ex;" class="input_text" />
								<label for="karma_bad">', $modSettings['karmaSmiteLabel'], '</label> <input type="text" id="karma_bad" name="karma_bad" size="4" value="', $context['member']['karma']['bad'], '" class="input_text" /><br />
								(', $txt['total'], ': <span id="karmaTotal">', ($context['member']['karma']['good'] - $context['member']['karma']['bad']), '</span>)
							</dd>';
}

/**
 * Select the time format!.
 */
function template_profile_timeformat_modify()
{
	global $context, $txt, $scripturl;

	echo '
							<dt>
								<label for="easyformat">', $txt['time_format'], '</label>
								<p>
									<a href="', $scripturl, '?action=quickhelp;help=time_format" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
									&nbsp;', $txt['date_format'], '
								</p>
							</dt>
							<dd>
								<select name="easyformat" id="easyformat" onchange="document.forms.creator.time_format.value = this.options[this.selectedIndex].value;" style="margin-bottom: 4px;">';

	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
									<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected="selected"' : '', '>', $time_format['title'], '</option>';

	echo '
								</select>
								<br />
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
								<label', (isset($context['modify_error']['bad_offset']) ? ' class="error"' : ''), ' for="time_offset">', $txt['time_offset'], '</label>
								<p>', $txt['personal_time_offset'], '</p>
							</dt>
							<dd>
								<input type="text" name="time_offset" id="time_offset" size="5" maxlength="5" value="', $context['member']['time_offset'], '" class="input_text" /> ', $txt['hours'], ' <a class="linkbutton" href="javascript:void(0);" onclick="currentDate = new Date(', $context['current_forum_time_js'], '); document.getElementById(\'time_offset\').value = autoDetectTimeOffset(currentDate); return false;">', $txt['timeoffset_autodetect'], '</a><br />', $txt['current_time'], ': <em>', $context['current_forum_time'], '</em>
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
								<label>', $txt['current_theme'], '</label>
							</dt>
							<dd>
								', $context['member']['theme']['name'], ' <a class="linkbutton" href="', $scripturl, '?action=theme;sa=pick;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['change'], '</a>
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
								<label for="smiley_set">', $txt['smileys_current'], '</label>
							</dt>
							<dd>
								<select name="smiley_set" id="smiley_set" onchange="document.getElementById(\'smileypr\').src = this.selectedIndex == 0 ? \'', $settings['images_url'], '/blank.png\' : \'', $modSettings['smileys_url'], '/\' + (this.selectedIndex != 1 ? this.options[this.selectedIndex].value : \'', !empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'], '\') + \'/smiley.gif\';">';

	foreach ($context['smiley_sets'] as $set)
		echo '
									<option value="', $set['id'], '"', $set['selected'] ? ' selected="selected"' : '', '>', $set['name'], '</option>';

	echo '
								</select>
								<img id="smileypr" class="centericon" src="', $context['member']['smiley_set']['id'] != 'none' ? $modSettings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] : (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'])) . '/smiley.gif' : $settings['images_url'] . '/blank.png', '" alt=":)"  style="padding-left: 1.25em;" />
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
		<form action="', $scripturl, '?action=profile;area=authentication" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data">
			<h2 class="category_header hdicon cat_img_profile">
				', $txt['authentication'], '
			</h2>
			<p class="description">', $txt['change_authentication'], '</p>
			<div class="content">
				<dl>
					<dt>
						<input type="radio" onclick="updateAuthMethod();" name="authenticate" value="openid" id="auth_openid"', $context['auth_method'] == 'openid' ? ' checked="checked"' : '', ' />
						<label for="auth_openid">', $txt['authenticate_openid'], '</label>
						<a href="', $scripturl, '?action=quickhelp;help=register_openid" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<br>
						<input type="radio" onclick="updateAuthMethod();" name="authenticate" value="passwd" id="auth_pass"', $context['auth_method'] == 'password' ? ' checked="checked"' : '', ' />
						<label for="auth_pass">', $txt['authenticate_password'], '</label>
					</dt>
					<dd>
						<dl id="openid_group">
							<dt>
								<label for="openid_url">', $txt['authenticate_openid_url'], '</label>
							</dt>
							<dd>
								<input type="text" name="openid_identifier" id="openid_url" size="30" tabindex="', $context['tabindex']++, '" value="', $context['member']['openid_uri'], '" class="input_text openid_login" />
							</dd>
						</dl>
						<dl id="password1_group">
							<dt>
								<em>', $txt['choose_pass'], ':</em>
							</dt>
							<dd>
								<input type="password" name="passwrd1" id="elk_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '" class="input_password" placeholder="', $txt['choose_pass'], '" />
								<span id="elk_autov_pwmain_div" class="hide">
									<img id="elk_autov_pwmain_img" class="centericon" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" />
								</span>
							</dd>
						</dl>
						<dl id="password2_group">
							<dt>
								<em for="elk_autov_pwverify">', $txt['verify_pass'], ':</em>
							</dt>
							<dd>
								<input type="password" name="passwrd2" id="elk_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '" class="input_password" placeholder="', $txt['verify_pass'], '" />
								<span id="elk_autov_pwverify_div" class="hide">
									<img id="elk_autov_pwverify_img" class="centericon" src="', $settings['images_url'], '/icons/field_valid.png" alt="*" />
								</span>
							</dd>
						</dl>
					</dd>
				</dl>';

	template_profile_save();

	echo '
				<input type="hidden" name="save" value="save" />
			</div>
		</form>';

	// The password stuff.
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
		var currentAuthMethod = \'passwd\';

		updateAuthMethod();
	</script>';
}
