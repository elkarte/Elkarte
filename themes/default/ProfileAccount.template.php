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
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Show a lovely interface for issuing warnings.
 */
function template_issueWarning()
{
	global $context, $scripturl, $txt;

	template_load_warning_variables();

	echo '
	<script>
	var barWidth = ', $context['warningBarWidth'], ',
		currentLevel = ', $context['member']['warning'], ',
		minLimit = ', $context['min_allowed'], ',
		maxLimit = ', $context['max_allowed'], ',
		color = "black",
		effectText = "";

	// Colors for the warning level
	var colors = {';

	foreach ($context['colors'] as $limit => $color)
		echo $limit, ' : "', $color, '", ';

	echo '};

	// Text to describe the effect of the chosen level
	var effectTexts = {';

	foreach ($context['level_effects'] as $limit => $text)
		echo $limit, ' : "', $text, '", ';

	echo '}

	// Warning templates that can be sent to the user
	var templates = {';

	foreach ($context['notification_templates'] as $limit => $type)
		echo $limit, ' :"', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '", ';

	echo '};
	</script>';

	echo '
	<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=issuewarning" method="post" class="flow_hidden" accept-charset="UTF-8">
		<h2 class="category_header hdicon cat_img_profile">
			', $context['user']['is_owner'] ? $txt['profile_warning_level'] : $txt['profile_issue_warning'], '
		</h2>';

	if (!$context['user']['is_owner'])
		echo '
		<p class="description">', $txt['profile_warning_desc'], '</p>';

	echo '
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
					<label for="warning_level">', $txt['profile_warning_level'], ':</label>
					<div class="smalltext">', $txt['profile_warning_impact'], ':<br />';

	// For non-javascript give a better list.
	foreach ($context['level_effects'] as $limit => $effect)
		echo '
						', sprintf($txt['profile_warning_effect_text'], $limit, $effect), '<br />';

	echo '
					</div>';

	// Is there only so much they can apply?
	if ($context['warning_limit'])
		echo '
					<br />
					<span class="smalltext">', sprintf($txt['profile_warning_limit_attribute'], $context['warning_limit']), '</span>';

	echo '
				</dt>
				<dd>
					<div id="warndiv">
						<input type="text" id="warning_level" name="warning_level" size="6" maxlength="4" value="', $context['member']['warning'], '" class="input_text" />&nbsp;', $txt['profile_warning_max'], '
						<div id="slider-range-min"></div>

						<script>
							initWarnSlider("slider-range-min", "warning_level", [', implode(',', array_keys($context['level_effects'])), ']);
						</script>
					</div>
				</dd>';

	if (!$context['user']['is_owner'])
	{
		echo '
				<dt>
					<label for="warn_reason">', $txt['profile_warning_reason'], ':</label><br />
					<span class="smalltext">', $txt['profile_warning_reason_desc'], '</span>
				</dt>
				<dd>
					<input id="warn_reason" class="warn_input" type="text" name="warn_reason"  value="', $context['warning_data']['reason'], '" size="50" class="input_text" />
				</dd>
			</dl>
			<hr />
			<div id="box_preview"', !empty($context['warning_data']['body_preview']) ? '' : ' class="hide"', '>
				<dl class="settings">
					<dt>
						<strong>', $txt['preview'], '</strong>
					</dt>
					<dd id="body_preview">
						', !empty($context['warning_data']['body_preview']) ? $context['warning_data']['body_preview'] : '', '
					</dd>
				</dl>
			<hr />
			</div>
			<dl class="settings">
				<dt>
					<label for="warn_notify">', $txt['profile_warning_notify'], ':</label>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();" ', $context['warning_data']['notify'] ? 'checked="checked"' : '', ' />
				</dd>
				<dt>
					<label for="warn_sub">', $txt['profile_warning_notify_subject'], ':</label>
				</dt>
				<dd>
					<input id="warn_sub" class="warn_input" type="text" name="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? $txt['profile_warning_notify_template_subject'] : $context['warning_data']['notify_subject'], '" size="50" class="input_text" />
				</dd>
				<dt>
					<label for="warn_temp">', $txt['profile_warning_notify_body'], ':</label>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled="disabled" onchange="populateNotifyTemplate();">
						<option value="-1">', $txt['profile_warning_notify_template'], '</option>
						<option value="-1" disabled="disabled">', str_repeat('&#8212;', strlen($txt['profile_warning_notify_template'])), '</option>';

		foreach ($context['notification_templates'] as $id_template => $template)
			echo '
						<option value="', $id_template, '">&#10148;&nbsp;', $template['title'], '</option>';

		echo '
					</select>
					<span id="new_template_link" class="hide">
						<a class="linkbutton new_win" href="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank">', $txt['profile_warning_new_template'], '</a>
					</span>
					<textarea id="warn_body" class="warn_input" name="warn_body" cols="40" rows="8">', $context['warning_data']['notify_body'], '</textarea>
				</dd>';
	}

	echo '
			</dl>
			<div class="submitbutton">';

	if (!empty($context['token_check']))
		echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="submit" name="save" value="', $context['user']['is_owner'] ? $txt['change_profile'] : $txt['profile_warning_issue'], '" />
				<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" />
			</div>
		</div>
	</form>
	<br />';

	// Previous warnings?
	template_show_list('issued_warnings');

	// Do our best to get pretty javascript enabled.
	echo '
	<script>
		document.getElementById(\'preview_button\').style.display = "none";';

	if (!$context['user']['is_owner'])
		echo '
		modifyWarnNotify();';

	echo '
	</script>';
}

/**
 * Template to show for deleting a users account - now with added delete post capability!
 */
function template_deleteAccount()
{
	global $context, $scripturl, $txt;

	// The main containing header.
	echo '
		<form id="creator" action="', $scripturl, '?action=profile;area=deleteaccount;u=', $context['id_member'], '" method="post" accept-charset="UTF-8" name="creator">
			<h2 class="category_header hdicon cat_img_profile">
				', $txt['deleteAccount'], '
			</h2>';

	// If deleting another account give them a lovely info box.
	if (!$context['user']['is_owner'])
		echo '
			<p class="description">', $txt['deleteAccount_desc'], '</p>';

	echo '
			<div class="content">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if ($context['needs_approval'])
		echo '
				<div class="warningbox">', $txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if ($context['user']['is_owner'])
	{
		echo '
				<div class="errorbox">', $txt['own_profile_confirm'], '</div>
				<div class="submitbutton">
					<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '><label for="oldpasswrd">', $txt['current_password'], '</label>: </strong>
					<input type="password" id="oldpasswrd" name="oldpasswrd" size="20" class="input_password" />&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="submit" name="save" value="', $txt['delete'], '" />';

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
				<div class="errorbox">', $txt['deleteAccount_warning'], '</div>
				<dl class="settings">';

		// Only actually give these options if they are kind of important.
		if ($context['can_delete_posts'])
			echo '
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=deleteAccount_posts" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="remove_type">', $txt['deleteAccount_posts'], '</label>:
					</dt>
					<dd>
						<select id="remove_type" name="remove_type">
							<option value="none">', $txt['deleteAccount_none'], '</option>
							<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
							<option value="topics">', $txt['deleteAccount_topics'], '</option>
						</select>
					</dd>';

		echo '
					<dt>
						<label for="deleteAccount">', $txt['deleteAccount_member'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" onclick="if (this.checked) return confirm(\'', $txt['deleteAccount_confirm'], '\');" />
					</dd>
				</dl>
				<input type="submit" name="save" value="', $txt['delete'], '" class="right_submit" />';

		if (!empty($context['token_check']))
			echo '
				<input type="hidden" name="', $context[$context['token_check'] . '_token_var'], '" value="', $context[$context['token_check'] . '_token'], '" />';

		echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="u" value="', $context['id_member'], '" />
				<input type="hidden" name="sa" value="', $context['menu_item_selected'], '" />';
	}

	echo '
			</div>
		</form>';
}
