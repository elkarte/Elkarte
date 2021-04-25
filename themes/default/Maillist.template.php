<?php

/**
 * Templates for the PBE maillist function
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
 *
 */

/**
 * Shows the details of a failed post by email for moderation review
 */
function template_show_email()
{
	global $txt, $context, $boardurl;

	echo '
		<h2 class="category_header">', $txt['show_notice'], '</h2>
		<div class="content">
			<ul>
				<li><strong>' . $txt['subject'] . ': </strong>', $context['notice_subject'], '</li>
				<li><strong>' . $txt['from'] . ': </strong>', $context['notice_from'], '</li>
				<li><strong>' . $txt['to'] . ': </strong>', $context['to'], '</li>
			</ul>
			<p class="warningbox">', $txt['email_failure'], ': ', $context['error_code'], '</p>
		</div>
		<div class="content">
			<dl>
				<dt>
					<strong>', $txt['show_notice_text'], ':</strong>
				</dt>
				<dd>
					', $context['body'], '
				</dd>
			</dl>
		</div>
		<div class="centertext">
			<a class="linkbutton" href="' . $boardurl . '/index.php?action=admin;area=maillist;sa=emaillist">', $txt['back'], '</a>
		</div>';
}

/**
 * Used to select a bounce template and send a failed message to a email sender
 */
function template_bounce_email()
{
	global $txt, $context, $scripturl;

	// Build the "it bounced" javascript ....
	echo '
	<script>
		// Disable notification boxes as required.
		function modifyWarnNotify()
		{
			var disable = !document.getElementById(\'warn_notify\').checked;
			document.getElementById(\'warn_sub\').disabled = disable;
			document.getElementById(\'warn_body\').disabled = disable;
			document.getElementById(\'warn_temp\').disabled = disable;
		}

		// bounce template.
		function populateNotifyTemplate()
		{
			var index = document.getElementById(\'warn_temp\').value;
			if (index == -1)
				return false;

			// Otherwise see what we can do...';

	foreach ($context['bounce_templates'] as $k => $type)
		echo '
			if (index == ', $k, ')
			{
				document.getElementById(\'warn_body\').value = "', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";
				document.getElementById(\'warn_sub\').value = "', strtr($type['subject'], array('"' => "'", "\n" => '\\n', "\r" => '')), '";
			}';

	echo '
		}

	</script>';

	echo '
	<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=maillist;sa=bounce" method="post" class="flow_hidden" accept-charset="UTF-8">
		<h2 class="category_header hdicon cat_img_mail">
			', $txt['show_notice'], '
		</h2>';

	// Any special messages?
	if (!empty($context['settings_message']))
		echo '
			<div class="successbox">', $context['settings_message'], '</div>';

	// The main body
	echo '
		<h2 class="category_header">', $context['notice_to'], '</h2>
		<div class="content">
			<dl class="settings">
				<dt>
					<strong>', $txt['bounce_error'], ':</strong>
				</dt>
				<dd>
					', $context['body'], '
				</dd>
			</dl>
		</div>
		<div class="content">
			<dl class="settings">
				<dt>
					<label for="warn_notify">', $txt['bounce_notify'], ':</label>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();" ', $context['warning_data']['notify'] ? 'checked="checked"' : '', ' />
				</dd>
				<dt>
					<label for="warn_temp">', $txt['bounce_notify_template'], ':</label>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled="disabled" onchange="populateNotifyTemplate();">
						<option value="-1">', $txt['bounce_notify_template'], '</option>
						<option value="-1" disabled="disabled">', str_repeat('&#8212;', strlen($txt['bounce_notify_template'])), '</option>';

	foreach ($context['bounce_templates'] as $id_template => $template)
		echo '
							<option value="', $id_template, '">&#10148;&nbsp;', $template['title'], '</option>';

	echo '
					</select>
				</dd>
				<dt>
					<label for="warn_sub">', $txt['bounce_notify_subject'], ':</label>
				</dt>
				<dd>
					<input type="text" name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? '' : $context['warning_data']['notify_subject'], '" size="50" style="width: 80%;" class="input_text" />
				</dd>
				<dt>
					<label for="warn_body">', $txt['bounce_notify_body'], ':</label>
				</dt>
				<dd>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8">', $context['warning_data']['notify_body'], '</textarea>
				</dd>
			</dl>
			<div class="submitbutton">
				<a class="linkbutton" href="', $scripturl, '?action=admin;area=maillist;sa=emaillist;">', $txt['back'], '</a>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="item" value="', $context['item'], '" />
				<input type="submit" name="bounce" value="', $txt['bounce_issue'], '" />
				<input type="hidden" name="', $context['admin-ml_token_var'], '" value="', $context['admin-ml_token'], '" />
			</div>
		</div>
	</form>';

	// kick off the javascript.
	echo '
	<script>
		modifyWarnNotify();
	</script>';
}

/**
 * Shows email address to board selections, used to determine where to post
 * a new topic post by email
 */
function template_callback_maillist_receive_email_list()
{
	global $txt, $context;

	echo '
		</dl>
		<p>', $txt['receiving_address_desc'], '</p>
		<dl class="settings">
			<dt>
				<strong>', $txt['receiving_address'], '</strong>
			</dt>
			<dd>
				<strong>', $txt['receiving_board'], '</strong>
			</dd>';

	foreach ($context['maillist_from_to_board'] as $data)
	{
		echo '
			<dt>
				<input type="text" name="emailfrom[', $data['id'], ']" value="', $data['emailfrom'], '" size="50" class="input_text" />
			</dt>
			<dd>
				<select class="input_select" name="boardto[', $data['id'], ']" >';

		foreach ($context['boards'] as $board_id => $board_name)
			echo '
					<option value="', $board_id, '"', (($data['boardto'] == $board_id) ? ' selected="selected"' : ''), '>', $board_name, '</option>';

		echo '
				</select>
			</dd>';
	}

	// Some blank ones.
	if (empty($context['maillist_from_to_board']))
	{
		for ($count = 0; $count < 2; $count++)
		{
			echo '
			<dt>
				<input type="text" name="emailfrom[]" size="50" class="input_text" />
			</dt>
			<dd>
				<select name="boardto[', $count, ']" >';

			foreach ($context['boards'] as $board_id => $board_name)
				echo '
					<option value="', $board_id, '">', $board_name, '</option>';

			echo '
				</select>
			</dd>';
		}
	}

	echo '
		<dt id="add_more_email_placeholder" class="hide"></dt>
		<dd></dd>
		<dt id="add_more_board_div" class="hide">
			<a href="#" onclick="addAnotherOption(sEmailParent, oEmailOptionsdt, oEmailOptionsdd, oEmailSelectData); return false;" class="linkbutton_left">', $txt['reply_add_more'], '</a>
		</dt>
		<dd></dd>';
}

/**
 * Add or edit a bounce template.
 */
function template_bounce_template()
{
	global $context, $txt, $scripturl;

	echo '
	<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=maillist;sa=emailtemplates;tid=', $context['id_template'], '" method="post" accept-charset="UTF-8">
		<div id="preview_section"', isset($context['template_preview']) ? '' : ' class="hide"', '>
			<h2 class="category_header">
				<span id="preview_subject">', $txt['preview'], '</span>
			</h2>
			<div id="preview_body">
				', empty($context['template_preview']) ? '<br />' : $context['template_preview'], '
			</div>
		</div>

		<h2 class="category_header">', $context['page_title'], '</h2>
		<div class="information">
			', $txt['ml_bounce_template_desc'], '
		</div>
		<div class="content">
			<div id="errors" class="errorbox', empty($context['warning_errors']) ? ' hide"' : '"', '>
				<dl>
					<dt>
						<strong id="error_serious">', $txt['error_while_submitting'], '</strong>
					</dt>
					<dd class="error" id="error_list">
						', empty($context['warning_errors']) ? '' : implode('<br />', $context['warning_errors']), '
					</dd>
				</dl>
			</div>
			<dl class="settings">
				<dt>
					<label for="template_title">', $txt['ml_bounce_template_title'], '</label>:<br />
					<span class="smalltext">', $txt['ml_bounce_template_title_desc'], '</span>
				</dt>
				<dd>
					<input type="text" id="template_title" name="template_title" value="', $context['template_data']['title'], '" size="60" class="input_text" />
				</dd>
				<dt>
					<label>', $txt['subject'], '</label>:<br />
				</dt>
				<dd>
					', $txt['ml_bounce_template_subject_default'], '
				</dd>
				<dt>
					<label for="template_body">', $txt['ml_bounce_template_body'], '</label>:<br />
					<span class="smalltext">', $txt['ml_bounce_template_body_desc'], '</span>
				</dt>
				<dd>
					<textarea id="template_body" name="template_body" rows="10" cols="65">', $context['template_data']['body'], '</textarea>
				</dd>
			</dl>';

	if ($context['template_data']['can_edit_personal'])
		echo '
			<input type="checkbox" name="make_personal" id="make_personal" ', $context['template_data']['personal'] ? 'checked="checked"' : '', ' />
				<label for="make_personal">
					', $txt['ml_bounce_template_personal'], '
				</label>
				<br />
				<span class="smalltext">', $txt['ml_bounce_template_personal_desc'], '</span>';

	echo '
			<div class="submitbutton">
				<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" />
				<input type="submit" name="save" value="', $context['page_title'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['mod-mlt_token_var'], '" value="', $context['mod-mlt_token'], '" />
			</div>
		</div>
	</form>
	<script>
		$(function() {
			$("#preview_button").click(function() {
				return ajax_getEmailTemplatePreview();
			});
		});
	</script>';
}
