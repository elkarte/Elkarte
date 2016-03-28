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
 * @version 1.0.7
 *
 */

/**
 * Start of the template, just calls in the helpers
 */
function template_ManageNews_init()
{
	loadtemplate('GenericHelpers');
}

/**
 * Template for the email to members page in admin panel.
 * It allows to select members and membergroups.
 */
function template_email_members()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingcompose" method="post" id="admin_newsletters" class="flow_hidden" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['admin_newsletters'], '</h2>
			<div class="information">
				', $txt['admin_news_select_recipients'], '
			</div>
			<div id="include_panel_header">
				<h3 class="category_header">
					', $txt['include_these'], '
				</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['admin_news_select_group'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_group_desc'], '</span>
						</dt>
						<dd>';

	template_list_groups_collapsible('groups');

	echo '
						</dd>
						<dt>
							<strong><label for="emails">', $txt['admin_news_select_email'], '</label>:</strong><br />
							<span class="smalltext">', $txt['admin_news_select_email_desc'], '</span>
						</dt>
						<dd>
							<textarea id="emails" name="emails" rows="5" cols="30" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 98%; min-width: 98%' : 'width: 98%') . ';"></textarea>
						</dd>
						<dt>
							<strong><label for="members">', $txt['admin_news_select_members'], '</label>:</strong><br />
							<span class="smalltext">', $txt['admin_news_select_members_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="members" id="members" value="" size="30" class="input_text" />
							<span id="members_container"></span>
						</dd>
					</dl>
					<hr class="bordercolor" />
					<dl class="settings">
						<dt>
							<label for="email_force"><strong>', $txt['admin_news_select_override_notify'], ':</strong></label><br />
							<span class="smalltext">', $txt['email_force'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="email_force" id="email_force" value="1" class="input_check" />
						</dd>
					</dl>
				</div>
			</div>
			<div id="exclude_panel_header">
				<h3 class="category_header">
					<span id="category_toggle">&nbsp;
						<span id="upshrink_ic" class="', empty($context['admin_preferences']['apn']) ? 'collapse' : 'expand', '" style="display: none;" title="', $txt['hide'], '"></span>
					</span>
					<a href="#" id="exclude_panel_link" >', $txt['exclude_these'], '</a>
				</h3>
			</div>
			<div id="exclude_panel_div" class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt>
							<strong>', $txt['admin_news_select_excluded_groups'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_excluded_groups_desc'], '</span>
						</dt>
						<dd>';

	template_list_groups_collapsible('exclude_groups');

	echo '
						<dt>
							<strong>', $txt['admin_news_select_excluded_members'], ':</strong><br />
							<span class="smalltext">', $txt['admin_news_select_excluded_members_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="exclude_members" id="exclude_members" value="" size="30" class="input_text" />
							<span id="exclude_members_container"></span>
						</dd>
					</dl>
				</div>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['admin_next'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>';

	// This is some javascript for the simple/advanced toggling and member suggest
	addInlineJavascript('
		var oAdvancedPanelToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['admin_preferences']['apn']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'exclude_panel_div\'
			],
			aSwapClasses: [
				{
					sId: \'upshrink_ic\',
					classExpanded: \'collapse\',
					titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
					classCollapsed: \'expand\',
					titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			aSwapLinks: [
				{
					sId: \'exclude_panel_link\',
					msgExpanded: ' . JavaScriptEscape($txt['exclude_these']) . ',
					msgCollapsed: ' . JavaScriptEscape($txt['exclude_these']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'admin_preferences\',
				sSessionVar: elk_session_var,
				sSessionId: elk_session_id,
				sThemeId: \'1\',
				sAdditionalVars: \';admin_key=apn\'
			}
		});

		var oMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'members\',
			sControlId: \'members\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'member_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			sItemListContainerId: \'members_container\',
			aListItems: []
		});

		var oExcludeMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oExcludeMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'exclude_members\',
			sControlId: \'exclude_members\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'exclude_member_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			sItemListContainerId: \'exclude_members_container\',
			aListItems: []
		});', true);
}

/**
 * Template for the section to compose an email to members
 */
function template_email_members_compose()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form name="newsmodify" action="', $scripturl, '?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8">
			<h3 class="category_header">
				', $txt['admin_newsletters'], '
			</h3>
			<div class="information">
				', str_replace('{help_emailmembers}', $scripturl . '?action=quickhelp;help=emailmembers" onclick="return reqOverlayDiv(this.href);', $txt['email_variables']), '
			</div>';

	// The preview section
	echo '
			<div id="preview_section" class="forumposts"', isset($context['preview_message']) ? '' : ' style="display: none;"', '>
				<h3 class="category_header">
					<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
				</h3>
				<div class="post" id="preview_body">
					', empty($context['preview_message']) ? '<br />' : $context['preview_message'], '
				</div>
			</div>';

	// Any errors to speak of?
	echo '
			<div class="windowbg">
				<div id="post_error" class="', empty($context['error_type']) || $context['error_type'] != 'serious' ? 'warningbox' : 'errorbox', '"', empty($context['post_error']['messages']) ? ' style="display: none"' : '', '>
					<dl>
						<dt>
							<strong id="error_serious">', $txt['error_while_submitting'], '</strong>
						</dt>
						<dd>
							<ul class="error" id="post_error_list">
								', empty($context['post_error']['messages']) ? '' : '<li>' . implode('</li><li>', $context['post_error']['messages']) . '</li>', '
							</ul>
						</dd>
					</dl>
				</div>';

	// Show the editor area
	echo'
				<div class="editor_wrapper">
					<dl id="post_header">
						<dt class="clear_left">
							<label for="subject"', (isset($context['post_error']['no_subject']) ? ' class="error"' : ''), ' id="caption_subject">', $txt['subject'], ':</label>
						</dt>
						<dd id="pm_subject">
							<input type="text" id="subject" name="subject" value="', $context['subject'], '" tabindex="', $context['tabindex']++, '" size="60" maxlength="60"', isset($context['post_error']['no_subject']) ? ' class="error"' : ' class="input_text"', '/>
						</dd>
					</dl>
					<hr class="clear" />';

	// Show BBC buttons, smileys and textbox.
	echo '
					', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	echo '
					<ul>
						<li><label for="send_pm"><input type="checkbox" name="send_pm" id="send_pm" ', !empty($context['send_pm']) ? 'checked="checked"' : '', 'class="input_check" onclick="checkboxes_status(this);" /> ', $txt['email_as_pms'], '</label></li>
						<li><label for="send_html"><input type="checkbox" name="send_html" id="send_html" ', !empty($context['send_html']) ? 'checked="checked"' : '', 'class="input_check" onclick="checkboxes_status(this);" /> ', $txt['email_as_html'], '</label></li>
						<li><label for="parse_html"><input type="checkbox" name="parse_html" id="parse_html" checked="checked" disabled="disabled" class="input_check" /> ', $txt['email_parsed_html'], '</label></li>
					</ul>
					<div class="submitbutton">
						', template_control_richedit_buttons($context['post_box_name']), '
					</div>
				</div>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="email_force" value="', $context['email_force'], '" />
				<input type="hidden" name="total_emails" value="', $context['total_emails'], '" />
				<input type="hidden" name="max_id_member" value="', $context['max_id_member'], '" />
			</div>';

	foreach ($context['recipients'] as $key => $values)
		echo '
			<input type="hidden" name="', $key, '" value="', implode(($key == 'emails' ? ';' : ','), $values), '" />';

	// The vars used to preview a newsletter without loading a new page, used by post.js previewControl()
	addInlineJavascript('
		var form_name = "newsmodify",
			preview_area = "news",
			txt_preview_title = "' . $txt['preview_title'] . '",
			txt_preview_fetch = "' . $txt['preview_fetch'] . '";

		function checkboxes_status (item)
		{
			if (item.id == \'send_html\')
				document.getElementById(\'parse_html\').disabled = !document.getElementById(\'parse_html\').disabled;

			if (item.id == \'send_pm\')
			{
				if (!document.getElementById(\'send_html\').checked)
					document.getElementById(\'parse_html\').disabled = true;
				else
					document.getElementById(\'parse_html\').disabled = false;

				document.getElementById(\'send_html\').disabled = !document.getElementById(\'send_html\').disabled;
			}
		}', true);

	echo '
		</form>
	</div>';
}

/**
 * Template for sending an email to members
 */
function template_email_members_send()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<h3 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=email_members" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['admin_newsletters'], '
			</h3>
			<div class="windowbg">
				<div class="content">
					<div class="progress_bar">
						<div class="full_bar">', $context['percentage_done'], '% ', $txt['email_done'], '</div>
						<div class="green_percent" style="width: ', $context['percentage_done'], '%;">&nbsp;</div>
					</div>
					<hr />
					<input type="submit" name="cont" value="', $txt['email_continue'], '" class="right_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="subject" value="', $context['subject'], '" />
					<input type="hidden" name="message" value="', $context['message'], '" />
					<input type="hidden" name="start" value="', $context['start'], '" />
					<input type="hidden" name="total_emails" value="', $context['total_emails'], '" />
					<input type="hidden" name="max_id_member" value="', $context['max_id_member'], '" />
					<input type="hidden" name="send_pm" value="', $context['send_pm'], '" />
					<input type="hidden" name="send_html" value="', $context['send_html'], '" />
					<input type="hidden" name="parse_html" value="', $context['parse_html'], '" />';

	// All the things we must remember!
	foreach ($context['recipients'] as $key => $values)
		echo '
					<input type="hidden" name="', $key, '" value="', implode(($key == 'emails' ? ';' : ','), $values), '" />';

	echo '
				</div>
			</div>
		</form>
	</div>

	<script><!-- // --><![CDATA[
		doAutoSubmit(2, ', JavaScriptEscape($txt['email_continue']), ');
	// ]]></script>';
}

/**
 * Template for informing the user the sending succeeded
 */
function template_email_members_succeeded()
{
	global $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h3 class="category_header">
			<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=email_members" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['admin_newsletters'], '
		</h3>
		<div class="windowbg">
			<div class="content">
				<div class="successbox">
					', $txt['email_members_succeeded'], '
				</div>
				<hr />
				<a href="', $scripturl, '?action=admin" class="linkbutton right_submit">', $txt['admin_back_to'], '</a>
			</div>
		</div>
	</div>';
}