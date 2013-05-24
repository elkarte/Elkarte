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
 * Show a lovely interface for issuing warnings.
 */
function template_issueWarning()
{
	global $context, $settings, $scripturl, $txt;

	template_load_warning_variables();

	echo '
	<script><!-- // --><![CDATA[
		function setWarningBarPos(curEvent, isMove, changeAmount)
		{
			barWidth = ', $context['warningBarWidth'], ';

			// Are we passing the amount to change it by?
			if (changeAmount)
			{
				if (document.getElementById(\'warning_level\').value == \'SAME\')
					percent = ', $context['member']['warning'], ' + changeAmount;
				else
					percent = parseInt(document.getElementById(\'warning_level\').value) + changeAmount;
			}
			// If not then it\'s a mouse thing.
			else
			{
				if (!curEvent)
					var curEvent = window.event;

				// If it\'s a movement check the button state first!
				if (isMove)
				{
					if (!curEvent.button || curEvent.button != 1)
						return false
				}

				// Get the position of the container.
				contain = document.getElementById(\'warning_progress\');
				position = 0;
				while (contain != null)
				{
					position += contain.offsetLeft;
					contain = contain.offsetParent;
				}

				// Where is the mouse?
				if (curEvent.pageX)
				{
					mouse = curEvent.pageX;
				}
				else
				{
					mouse = curEvent.clientX;
					mouse += document.documentElement.scrollLeft != "undefined" ? document.documentElement.scrollLeft : document.body.scrollLeft;
				}

				// Is this within bounds?
				if (mouse < position || mouse > position + barWidth)
					return;

				percent = Math.round(((mouse - position) / barWidth) * 100);

				// Round percent to the nearest 5 - by kinda cheating!
				percent = Math.round(percent / 5) * 5;
			}

			// What are the limits?
			minLimit = ', $context['min_allowed'], ';
			maxLimit = ', $context['max_allowed'], ';

			percent = Math.max(percent, minLimit);
			percent = Math.min(percent, maxLimit);

			size = barWidth * (percent/100);

			setInnerHTML(document.getElementById(\'warning_text\'), percent + "%");
			document.getElementById(\'warning_text\').innerHTML = percent + "%";
			document.getElementById(\'warning_level\').value = percent;
			document.getElementById(\'warning_progress\').style.width = size + "px";

			// Get the right color.
			color = "black"';

	foreach ($context['colors'] as $limit => $color)
		echo '
			if (percent >= ', $limit, ')
				color = "', $color, '";';

	echo '
			document.getElementById(\'warning_progress\').style.backgroundColor = color;
			document.getElementById(\'warning_progress\').style.backgroundImage = "none";

			// Also set the right effect.
			effectText = "";';

	foreach ($context['level_effects'] as $limit => $text)
		echo '
			if (percent >= ', $limit, ')
				effectText = "', $text, '";';

	echo '
			setInnerHTML(document.getElementById(\'cur_level_div\'), effectText);
		}

		// Disable notification boxes as required.
		function modifyWarnNotify()
		{
			disable = !document.getElementById(\'warn_notify\').checked;
			document.getElementById(\'warn_sub\').disabled = disable;
			document.getElementById(\'warn_body\').disabled = disable;
			document.getElementById(\'warn_temp\').disabled = disable;
			document.getElementById(\'new_template_link\').style.display = disable ? \'none\' : \'\';
			document.getElementById(\'preview_button\').style.display = disable ? \'none\' : \'\';
		}

		function changeWarnLevel(amount)
		{
			setWarningBarPos(false, false, amount);
		}

		// Warn template.
		function populateNotifyTemplate()
		{
			index = document.getElementById(\'warn_temp\').value;
			if (index == -1)
				return false;

			// Otherwise see what we can do...';

	foreach ($context['notification_templates'] as $k => $type)
		echo '
			if (index == ', $k, ')
				document.getElementById(\'warn_body\').value = "', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";';

	echo '
		}

	// ]]></script>';

	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=issuewarning" method="post" class="flow_hidden" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />
				', $context['user']['is_owner'] ? $txt['profile_warning_level'] : $txt['profile_issue_warning'], '
			</h3>
		</div>';

	if (!$context['user']['is_owner'])
		echo '
		<p class="description">', $txt['profile_warning_desc'], '</p>';

	echo '
		<div class="windowbg">
			<div class="content">
				<dl class="settings">';

	if (!$context['user']['is_owner'])
		echo '
					<dt>
						<strong>', $txt['profile_warning_name'], ':</strong>
					</dt>
					<dd>
						<strong>', $context['member']['name'], '</strong>
					</dd>';

	echo '
					<dt>
						<strong>', $txt['profile_warning_level'], ':</strong>';

	// Is there only so much they can apply?
	if ($context['warning_limit'])
		echo '
						<br /><span class="smalltext">', sprintf($txt['profile_warning_limit_attribute'], $context['warning_limit']), '</span>';

	echo '
					</dt>
					<dd>
						<div id="warndiv1" style="display: none;">
							<div>
								<span class="floatleft" style="padding: .1em .5em"><a href="#" onclick="changeWarnLevel(-5); return false;">[-]</a></span>
								<div  class="floatleft progress_bar" style="width: ', $context['warningBarWidth'], 'px; margin:0" onmousedown="setWarningBarPos(event, true);" onmousemove="setWarningBarPos(event, true);" onclick="setWarningBarPos(event);">
									<div id="warning_text" class="full_bar">', $context['member']['warning'], '%</div>
									<div id="warning_progress" class="green_percent" style="width: ', $context['member']['warning'], '%;">&nbsp;</div>
								</div>
								<span class="floatleft" style="padding: .1em .5em"><a href="#" onclick="changeWarnLevel(5); return false;">[+]</a></span>
								<div class="clear_left smalltext">', $txt['profile_warning_impact'], ': <span id="cur_level_div">', $context['level_effects'][$context['current_level']], '</span></div>
							</div>
							<input type="hidden" name="warning_level" id="warning_level" value="SAME" />
						</div>
						<div id="warndiv2">
							<input type="text" name="warning_level_nojs" size="6" maxlength="4" value="', $context['member']['warning'], '" class="input_text" />&nbsp;', $txt['profile_warning_max'], '
							<div class="smalltext">', $txt['profile_warning_impact'], ':<br />';

	// For non-javascript give a better list.
	foreach ($context['level_effects'] as $limit => $effect)
		echo '
							', sprintf($txt['profile_warning_effect_text'], $limit, $effect), '<br />';

	echo '
							</div>
						</div>
					</dd>';

	if (!$context['user']['is_owner'])
	{
		echo '
					<dt>
						<strong>', $txt['profile_warning_reason'], ':</strong><br />
						<span class="smalltext">', $txt['profile_warning_reason_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="warn_reason" id="warn_reason" value="', $context['warning_data']['reason'], '" size="50" style="width: 80%;" class="input_text" />
					</dd>
				</dl>
				<hr />
				<div id="box_preview"', !empty($context['warning_data']['body_preview']) ? '' : ' style="display:none"', '>
					<dl class="settings">
						<dt>
							<strong>', $txt['preview'] , '</strong>
						</dt>
						<dd id="body_preview">
							', !empty($context['warning_data']['body_preview']) ? $context['warning_data']['body_preview'] : '', '
						</dd>
					</dl>
					<hr />
				</div>
				<dl class="settings">
					<dt>
						<strong><label for="warn_notify">', $txt['profile_warning_notify'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();" ', $context['warning_data']['notify'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						<strong><label for="warn_sub">', $txt['profile_warning_notify_subject'], ':</label></strong>
					</dt>
					<dd>
						<input type="text" name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? $txt['profile_warning_notify_template_subject'] : $context['warning_data']['notify_subject'], '" size="50" style="width: 80%;" class="input_text" />
					</dd>
					<dt>
						<strong><label for="warn_temp">', $txt['profile_warning_notify_body'], ':</label></strong>
					</dt>
					<dd>
						<select name="warn_temp" id="warn_temp" disabled="disabled" onchange="populateNotifyTemplate();" style="font-size: x-small;">
							<option value="-1">', $txt['profile_warning_notify_template'], '</option>
							<option value="-1">------------------------------</option>';

		foreach ($context['notification_templates'] as $id_template => $template)
			echo '
							<option value="', $id_template, '">', $template['title'], '</option>';

		echo '
						</select>
						<span class="smalltext" id="new_template_link" style="display: none;">[<a href="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank" class="new_win">', $txt['profile_warning_new_template'], '</a>]</span><br />
						<textarea name="warn_body" id="warn_body" cols="40" rows="8" style="min-width: 50%; max-width: 99%;">', $context['warning_data']['notify_body'], '</textarea>
					</dd>';
	}

	echo '
				</dl>
				<div class="righttext">';

	if (!empty($context['token_check']))
		echo '
					<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" class="button_submit" />
					<input type="submit" name="save" value="', $context['user']['is_owner'] ? $txt['change_profile'] : $txt['profile_warning_issue'], '" class="button_submit" />
				</div>
			</div>
		</div>
	</form>
	</br />';

	// Previous warnings?
	template_show_list('issued_warnings');

	// Do our best to get pretty javascript enabled.
	echo '
	<script><!-- // --><![CDATA[
		document.getElementById(\'warndiv1\').style.display = "";
		document.getElementById(\'preview_button\').style.display = "none";
		document.getElementById(\'warndiv2\').style.display = "none";';

	if (!$context['user']['is_owner'])
		echo '
		modifyWarnNotify();
		$(document).ready(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});

		function ajax_getTemplatePreview ()
		{
			$.ajax({
				type: "POST",
				url: "' . $scripturl . '?action=xmlhttp;sa=previews;xml",
				data: {item: "warning_preview", title: $("#warn_sub").val(), body: $("#warn_body").val(), issuing: true},
				context: document.body,
				success: function(request){
					$("#box_preview").css({display:""});
					$("#body_preview").html($(request).find(\'body\').text());
					if ($(request).find("error").text() != \'\')
					{
						$("#profile_error").css({display:""});
						var errors_html = \'<span>\' + $("#profile_error").find("span").html() + \'</span>\' + \'<ul class="list_errors" class="reset">\';
						var errors = $(request).find(\'error\').each(function() {
							errors_html += \'<li>\' + $(this).text() + \'</li>\';
						});
						errors_html += \'</ul>\';

						$("#profile_error").html(errors_html);
					}
					else
					{
						$("#profile_error").css({display:"none"});
						$("#error_list").html(\'\');
					}
				return false;
				},
			});
			return false;
		}';

	echo '
	// ]]></script>';
}

/**
 * Template to show for deleting a users account - now with added delete post capability!
 */
function template_deleteAccount()
{
	global $context, $settings, $scripturl, $txt, $scripturl;

	// The main containing header.
	echo '
		<form action="', $scripturl, '?action=profile;area=deleteaccount;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<div class="title_bar">
				<h3 class="titlebg">
					<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['deleteAccount'], '
				</h3>
			</div>';

	// If deleting another account give them a lovely info box.
	if (!$context['user']['is_owner'])
		echo '
			<p class="windowbg2 description">', $txt['deleteAccount_desc'], '</p>';

	echo '
			<div class="windowbg2">
				<div class="content">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if ($context['needs_approval'])
		echo '
					<div class="errorbox">', $txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if ($context['user']['is_owner'])
	{
		echo '
					<div class="alert">', $txt['own_profile_confirm'], '</div>
					<div>
						<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '>', $txt['current_password'], ': </strong>
						<input type="password" name="oldpasswrd" size="20" class="input_password" />&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="submit" value="', $txt['yes'], '" class="button_submit" />';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

		echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>';
	}
	// Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts!
	else
	{
		echo '
					<div class="alert">', $txt['deleteAccount_warning'], '</div>';

		// Only actually give these options if they are kind of important.
		if ($context['can_delete_posts'])
			echo '
					<div>
						', $txt['deleteAccount_posts'], ':
						<select name="remove_type">
							<option value="none">', $txt['deleteAccount_none'], '</option>
							<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
							<option value="topics">', $txt['deleteAccount_topics'], '</option>
						</select>
					</div>';

		echo '
					<div>
						<label for="deleteAccount"><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" class="input_check" onclick="if (this.checked) return confirm(\'', $txt['deleteAccount_confirm'], '\');" /> ', $txt['deleteAccount_member'], '.</label>
					</div>
					<div>
						<input type="submit" value="', $txt['delete'], '" class="button_submit" />';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

		echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="u" value="', $context['id_member'], '" />
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />
					</div>';
	}
	echo '
				</div>
			</div>
			<br />
		</form>';
}
