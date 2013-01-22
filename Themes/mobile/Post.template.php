<?php
/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
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

// The main template for the post page.
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $counter;
		
	echo '
	<form action="', $scripturl, '?action=', $context['destination'], ';', empty($context['current_board']) ? '' : 'board=' . $context['current_board'], '" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" class="flow_hidden" enctype="multipart/form-data">';

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
		<ul data-role="listview" data-inset="true" ', isset($context['preview_message']) ? '' : 'style="display: none;"', '>
			<li>
				<h3>
					<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
				</h3>
			</li>
			<li>
				<div class="post" id="preview_body">
					', empty($context['preview_message']) ? '<br />' : $context['preview_message'], '
				</div>
			</li>
		</ul>';

	if ($context['make_event'] && (!$context['event']['new'] || !empty($context['current_board'])))
		echo '
		<input type="hidden" name="eventid" value="', $context['event']['id'], '" />';

	if (isset($context['current_topic']))
		echo '
		<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />';

	// If an error occurred, explain what happened.
	echo '
		<ul data-role="listview" data-inset="true" ', empty($context['post_error']['messages']) ? 'style="display: none"' : '', '>
			<li>
				<div class="', empty($context['error_type']) || $context['error_type'] != 'serious' ? 'noticebox' : 'errorbox', '" id="errors">
					<strong id="error_serious">', $txt['error_while_submitting'], '</strong>
					', empty($context['post_error']['messages']) ? '' : implode('<br />', $context['post_error']['messages']), '
				</div>
			</li>
		</ul>';
		
	// If this won't be approved let them know!
	if (!$context['becomes_approved'])
	{
		echo '
		<ul data-role="listview" data-inset="true">	
			<li class="information">
				<em>', $txt['wait_for_approval'], '</em>
				<input type="hidden" name="not_approved" value="1" />
			</li>
		</ul>';
	}

	// If it's locked, show a message to warn the replyer.
	echo '
		<ul data-role="listview" data-inset="true">
			<li class="information"', $context['locked'] ? '' : ' style="display: none"', ' id="lock_warning">
				', $txt['topic_locked_no_reply'], '
			</pli>
		</ul>';

	// The post header... important stuff
		// Start the main table.
	echo '<ul data-role="listview" data-inset="true">
			<li data-role="list-divider">', $context['page_title'], '</li>';

	// Guests have to put in their name and email...
	if (isset($context['name']) && isset($context['email']))
	{
		echo '
			<li>
				<label for="guestname">', $txt['name'], ':</label>
				<input type="text" name="guestname" size="25" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" class="input_text" />';

		if (empty($modSettings['guest_post_no_email']))
			echo '
				<label for="email">', $txt['email'], ':</label>
				<input type="text" name="email" size="25" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" class="input_text" />';
		echo '
			</li>';
	}

	// Now show the subject box for this post.
	echo '
			<li>
				<label for="subject" >', $txt['subject'], ':</label>
				<input type="text" name="subject"', $context['subject'] == '' ? '' : ' value="' . $context['subject'] . '"', ' tabindex="', $context['tabindex']++, '" size="80" maxlength="80"', isset($context['post_error']['no_subject']) ? ' class="error"' : ' class="input_text"', ' placeholder="', $txt['subject'], '" />
			</li>';
			
	echo '
			<li>
				<label for="icon">', $txt['message_icon'], ':<span><img src="', $context['icon_url'], '" name="icons" id="message_icon" hspace="15" alt="" /></span></label>
				
				<select name="icon" id="icon">';
	// Loop through each message icon allowed, adding it to the drop down list.
	foreach ($context['icons'] as $icon)
		echo '
					<option value="', $icon['value'], '"', $icon['value'] == $context['icon'] ? ' selected="selected"' : '', '>', $icon['name'], '</option>';

	echo '
				</select>
				<script><!-- // --><![CDATA[
						$(document).ready ( function () {
							$("#icon").bind("change", function () {
								// Get the value of the selected option
								var img_name = $(this).val();
								var url = "'. $settings['images_url']. '/post/";
								if (img_name) {	
									$("#message_icon").attr("src", url + img_name + ".png");
								}
								return false;
							});
						});
				// ]]></script>
			</li>';

	// If this is a poll then display all the poll options!
	if ($context['make_poll'])
	{
		echo '
			<li>
				<span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], '</span>
				<input type="text" name="question" value="', isset($context['question']) ? $context['question'] : '', '" tabindex="', $context['tabindex']++, '" size="80" class="input_text" />
			</li>
			<li id="pollMoreOptions">';

		// Loop through all the choices and print them out.
		foreach ($context['choices'] as $choice)
		{
			echo '
			
				<label for="options-', $choice['id'], '">', $txt['option'], ' ', $choice['number'], ':</label>
				<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" tabindex="', $context['tabindex']++, '" size="80" maxlength="255" class="input_text" />';
		}
			// Some javascript for adding more options.
			echo '	
				<script>
					var pollOptionNum = 0;
					var pollOptionId = ', $context['last_choice_id'], ';

					function addPollOption()
					{
						if (pollOptionNum == 0)
						{
							for (var i = 0; i < document.forms.postmodify.elements.length; i++)
								if (document.forms.postmodify.elements[i].id.substr(0, 8) == "options-")
									pollOptionNum++;
						}
						pollOptionNum++
						pollOptionId++

						$("#pollMoreOptions").append(\'<label class="ui-input-text" for="options-\' + pollOptionId + \'" ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['option'], ' \' + pollOptionNum + \':</label><input class="input_text ui-input-text ui-body-c ui-corner-all ui-shadow-inset" type="text" name="options[\' + (pollOptionId) + \']" id="options-\' + (pollOptionId) + \'" value="" size="80" maxlength="255" class="input_text" />\');
					}
				</script>
			</li>
			<li><strong><a href="javascript:addPollOption(); void(0);">(', $txt['poll_add_option'], ')</a></strong></li>
			
			<li>	
				<fieldset id="poll_options">
					<legend>', $txt['poll_options'], '</legend>

					<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
					<input type="text" name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll_options']['max_votes'], '" class="input_text" />

					<label for="poll_expire">', $txt['poll_run'], ': (', $txt['days_word'], ')</label>
					<span>', $txt['poll_run_limit'], '</span>
					<input type="text" name="poll_expire" id="poll_expire" size="2" value="', $context['poll_options']['expire'], '" onchange="pollOptions();" maxlength="4" class="input_text" />

					<label for="poll_change_vote">', $txt['poll_do_change_vote'], ':</label>
					<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked="checked"' : '', ' class="input_check" />';

		if ($context['poll_options']['guest_vote_enabled'])
			echo '

					<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
					<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll_options']['guest_vote']) ? ' checked="checked"' : '', ' class="input_check" />';

		echo '
					', $txt['poll_results_visibility'], ':

					<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll_options']['hide'] == 0 ? ' checked="checked"' : '', ' class="input_radio" /> <label for="poll_results_anyone">', $txt['poll_results_anyone'], '</label>
					<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll_options']['hide'] == 1 ? ' checked="checked"' : '', ' class="input_radio" /> <label for="poll_results_voted">', $txt['poll_results_voted'], '</label>
					<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll_options']['hide'] == 2 ? ' checked="checked"' : '', empty($context['poll_options']['expire']) ? 'disabled="disabled"' : '', ' class="input_radio" /> <label for="poll_results_expire">', $txt['poll_results_after'], '</label>
				</fieldset>
			</li>';
	}

	echo '
			<li>
			', template_control_richedit($context['post_box_name']), '
			</li>
		</ul>
	<div data-role="collapsible" data-theme="b" data-content-theme="d">
		<h3>', $context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt'], '</h3>';

	// Display the check boxes for all the standard options - if they are available to the user!

	if ($context['can_notify'])
		echo '
		<div data-role="fieldcontain">
			<input type="hidden" name="notify" value="0" />	
			<label for="notify">', $txt['notify_replies'], '</label>
			<select name="notify" id="notify" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . ($context['notify'] || !empty($options['auto_notify']) ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';
	if ($context['can_lock'])
		echo '
		<div data-role="fieldcontain">
			<input type="hidden" name="lock" value="0" />				
			<label for="check_lock">', $txt['lock_topic'], '</label>
			<select name="lock" id="check_lock" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . ($context['locked'] ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';

		echo '
		<div data-role="fieldcontain">				
			<label for="check_back">', $txt['back_to_topic'], '</label>
			<select name="goback" id="check_back" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . ($context['back_to_topic'] || !empty($options['return_to_post']) ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';
	if ($context['can_sticky'])
		echo '
		<div data-role="fieldcontain">			
			<label for="check_sticky">', $txt['sticky_after'], '</label>
			<select name="sticky" id="check_sticky" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . ($context['sticky'] ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';
		echo '
		<div data-role="fieldcontain">
			<input type="hidden" name="check_smileys" value="0" />				
			<label for="check_smileys">', $txt['dont_use_smileys'], '</label>
			<select name="ns" id="check_smileys" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"', $context['use_smileys'] ? '' : ' selected="selected"', '>', $txt['yes'], '</option>
			</select>
		</div>';
	if ($context['can_move'])
		echo '
		<div data-role="fieldcontain">				
			<label for="check_move">', $txt['move_after2'], '</label>
			<select name="move" id="check_move" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . (!empty($context['move']) ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';
	if ($context['can_announce'] && $context['is_first_post'])
		echo '
		<div data-role="fieldcontain">			
			<label for="check_announce">', $txt['announce_topic'], '</label>
			<select name="announce_topic" id="check_announce" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . (!empty($context['announce']) ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';
	if ($context['show_approval'])
		echo '
		<div data-role="fieldcontain">				
			<label for="check_announce">', $txt['announce_topic'], '</label>
			<select name="announce_topic" id="check_announce" data-role="slider" data-theme="c">
				<option value="0">', $txt['no'], '</option>
				<option value="1"' . ($context['show_approval'] === 2 ? ' selected="selected"' : '') . '>', $txt['yes'], '</option>
			</select>
		</div>';
			
	echo '
		<div id="postMoreOptions" class="smalltext">
			<ul class="post_options">
				', $context['show_approval'] ? '<li><label for="approve"><input type="checkbox" name="approve" id="approve" value="2" class="input_check" ' . ($context['show_approval'] === 2 ? 'checked="checked"' : '') . ' /> ' . $txt['approve_this_post'] . '</label></li>' : '', '
			</ul>
		</div>';

	// If this post already has attachments on it - give information about them.
	if (!empty($context['current_attachments']))
	{
		echo '
		<dl id="postAttachment">
			<dt>
				', $txt['attached'], ':
			</dt>
			<dd class="smalltext">
				<input type="hidden" name="attach_del[]" value="0" />
				', $txt['uncheck_unwatchd_attach'], ':
			</dd>';
		foreach ($context['current_attachments'] as $attachment)
			echo '
			<dd class="smalltext">
				<label for="attachment_', $attachment['id'], '"><input type="checkbox" id="attachment_', $attachment['id'], '" name="attach_del[]" value="', $attachment['id'], '"', empty($attachment['unchecked']) ? ' checked="checked"' : '', ' class="input_check" /> ', $attachment['name'], (empty($attachment['approved']) ? ' (' . $txt['awaiting_approval'] . ')' : ''),
				!empty($modSettings['attachmentPostLimit']) || !empty($modSettings['attachmentSizeLimit']) ? sprintf($txt['attach_kb'], comma_format(round(max($attachment['size'], 1028) / 1028), 0)) : '', '</label>
			</dd>';

		if (!empty($context['files_in_session_warning']))
			echo '
			<dd class="smalltext">', $context['files_in_session_warning'], '</dd>';

		echo '
		</dl>';
	}

	// Is the user allowed to post any additional ones? If so give them the boxes to do it!
	if ($context['can_post_attachment'])
	{
		echo '
		<dl id="postAttachment2">';
		
		// But, only show them if they haven't reached a limit. Or a mod author hasn't hidden them.
		if ($context['num_allowed_attachments'] > 0 || !empty($context['dont_show_them']))
		{
			echo '
			<dt>
				', $txt['attach'], ':
			</dt>
			<dd class="smalltext">
				', empty($modSettings['attachmentSizeLimit']) ? '' : ('<input type="hidden" name="MAX_FILE_SIZE" value="' . $modSettings['attachmentSizeLimit'] * 1028 . '" />'), '
				<input type="file" size="60" name="attachment[]" id="attachment1" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFileInput(\'attachment1\');">', $txt['clean_attach'], '</a>)';

			// Show more boxes if they aren't approaching that limit.
			if ($context['num_allowed_attachments'] > 1)
				echo '
				<script type="text/javascript"><!-- // --><![CDATA[
					var allowed_attachments = ', $context['num_allowed_attachments'], ';
					var current_attachment = 1;

					function addAttachment()
					{
						allowed_attachments = allowed_attachments - 1;
						current_attachment = current_attachment + 1;
						if (allowed_attachments <= 0)
							return alert("', $txt['more_attachments_error'], '");

						setOuterHTML(document.getElementById("moreAttachments"), \'<dd class="smalltext"><input type="file" size="60" name="attachment[]" id="attachment\' + current_attachment + \'" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFileInput(\\\'attachment\' + current_attachment + \'\\\');">', $txt['clean_attach'], '<\/a>)\' + \'<\/dd><dd class="smalltext" id="moreAttachments"><a href="#" onclick="addAttachment(); return false;">(', $txt['more_attachments'], ')<\' + \'/a><\' + \'/dd>\');

						return true;
					}
				// ]]></script>
			</dd>
			<dd class="smalltext" id="moreAttachments"><a href="#" onclick="addAttachment(); return false;">(', $txt['more_attachments'], ')</a></dd>';
			else
				echo '
			</dd>';
		}

		// Add any template changes for an alternative upload system here.
		call_integration_hook('integrate_upload_template', array());

		echo '
			<dd class="smalltext">';

		// Show some useful information such as allowed extensions, maximum size and amount of attachments allowed.
		if (!empty($modSettings['attachmentCheckExtensions']))
			echo '
				', $txt['allowed_types'], ': ', $context['allowed_extensions'], '<br />';

		if (!empty($context['attachment_restrictions']))
			echo '
				', $txt['attach_restrictions'], ' ', implode(', ', $context['attachment_restrictions']), '<br />';

		if ($context['num_allowed_attachments'] == 0)
			echo '
				', $txt['attach_limit_nag'], '<br />';

		if (!$context['can_post_attachment_unapproved'])
			echo '
				<span class="alert">', $txt['attachment_requires_approval'], '</span>', '<br />';

		echo '
			</dd>
		</dl>';
	}
	
	echo '
	</div>';

	// Is visual verification enabled?
	if ($context['require_verification'])
	{
		echo '
					<div class="post_verification">
						<span', !empty($context['post_error']['need_qr_verification']) ? ' class="error"' : '', '>
							<strong>', $txt['verification'], ':</strong>
						</span>
						', template_control_verification($context['visual_verification_id'], 'all'), '
					</div>';
	}

	// Finally, the submit buttons.
	echo template_control_richedit_buttons($context['post_box_name']);

	// Assuming this isn't a new topic pass across the last message id.
	if (isset($context['topic_last_message']))
		echo '
			<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />';

	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', $context['show_additional_options'] ? '1' : '0', '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
		</form>';

}

// The template for the spellchecker.
function template_spellcheck()
{
	global $context, $settings, $options, $txt;

	// The style information that makes the spellchecker look... like the forum hopefully!
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $txt['spell_check'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21" />
		<style type="text/css">
			body, td
			{
				font-size: small;
				margin: 0;
				background: #f0f0f0;
				color: #000;
				padding: 10px;
			}
			.highlight
			{
				color: red;
				font-weight: bold;
			}
			#spellview
			{
				border-style: outset;
				border: 1px solid black;
				padding: 5px;
				width: 95%;
				height: 314px;
				overflow: auto;
				background: #ffffff;
			}';

	// As you may expect - we need a lot of javascript for this... load it form the separate files.
	echo '
		</style>
		<script type="text/javascript"><!-- // --><![CDATA[
			var spell_formname = window.opener.spell_formname;
			var spell_fieldname = window.opener.spell_fieldname;
		// ]]></script>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/spellcheck.js"></script>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			', $context['spell_js'], '
		// ]]></script>
	</head>
	<body onload="nextWord(false);">
		<form action="#" method="post" accept-charset="', $context['character_set'], '" name="spellingForm" id="spellingForm" onsubmit="return false;" style="margin: 0;">
			<div id="spellview">&nbsp;</div>
			<table border="0" cellpadding="4" cellspacing="0" width="100%"><tr class="windowbg">
				<td width="50%" valign="top">
					', $txt['spellcheck_change_to'], '<br />
					<input type="text" name="changeto" style="width: 98%;" class="input_text" />
				</td>
				<td width="50%">
					', $txt['spellcheck_suggest'], '<br />
					<select name="suggestions" style="width: 98%;" size="5" onclick="if (this.selectedIndex != -1) this.form.changeto.value = this.options[this.selectedIndex].text;" ondblclick="replaceWord();">
					</select>
				</td>
			</tr></table>
			<div class="righttext" style="padding: 4px;">
				<input type="button" name="change" value="', $txt['spellcheck_change'], '" onclick="replaceWord();" class="button_submit" />
				<input type="button" name="changeall" value="', $txt['spellcheck_change_all'], '" onclick="replaceAll();" class="button_submit" />
				<input type="button" name="ignore" value="', $txt['spellcheck_ignore'], '" onclick="nextWord(false);" class="button_submit" />
				<input type="button" name="ignoreall" value="', $txt['spellcheck_ignore_all'], '" onclick="nextWord(true);" class="button_submit" />
			</div>
		</form>
	</body>
</html>';
}

function template_quotefast()
{
	global $context, $settings, $options, $txt;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<title>', $txt['retrieving_quote'], '</title>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	</head>
	<body>
		', $txt['retrieving_quote'], '
		<div id="temporary_posting_area" style="display: none;"></div>
		<script type="text/javascript"><!-- // --><![CDATA[';

	if ($context['close_window'])
		echo '
			window.close();';
	else
	{
		// Lucky for us, Internet Explorer has an "innerText" feature which basically converts entities <--> text. Use it if possible ;).
		echo '
			var quote = \'', $context['quote']['text'], '\';
			var stage = \'createElement\' in document ? document.createElement("DIV") : document.getElementById("temporary_posting_area");

			if (\'DOMParser\' in window && !(\'opera\' in window))
			{
				var xmldoc = new DOMParser().parseFromString("<temp>" + \'', $context['quote']['mozilla'], '\'.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_") + "</temp>", "text/xml");
				quote = xmldoc.childNodes[0].textContent.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}
			else if (\'innerText\' in stage)
			{
				setInnerHTML(stage, quote.replace(/\n/g, "_SMF-BREAK_").replace(/\t/g, "_SMF-TAB_").replace(/</g, "&lt;").replace(/>/g, "&gt;"));
				quote = stage.innerText.replace(/_SMF-BREAK_/g, "\n").replace(/_SMF-TAB_/g, "\t");
			}

			if (\'opera\' in window)
				quote = quote.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, \'"\').replace(/&amp;/g, "&");

			window.opener.oEditorHandle_', $context['post_box_name'], '.InsertText(quote);

			window.focus();
			setTimeout("window.close();", 400);';
	}
	echo '
		// ]]></script>
	</body>
</html>';
}

function template_announce()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="', $scripturl, '?action=announce;sa=send" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['announce_title'], '</h3>
			</div>
			<div class="information">
				', $txt['announce_desc'], '
			</div>
			<div class="windowbg2">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>
						', $txt['announce_this_topic'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0">', $context['topic_subject'], '</a>
					</p>
					<ul class="reset">';

	foreach ($context['groups'] as $group)
		echo '
						<li>
							<label for="who_', $group['id'], '"><input type="checkbox" name="who[', $group['id'], ']" id="who_', $group['id'], '" value="', $group['id'], '" checked="checked" class="input_check" /> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
						</li>';

	echo '
						<li>
							<label for="checkall"><input type="checkbox" id="checkall" class="input_check" onclick="invertAll(this, this.form);" checked="checked" /> <em>', $txt['check_all'], '</em></label>
						</li>
					</ul>
					<hr class="hrcolor" />
					<div id="confirm_buttons">
						<input type="submit" value="', $txt['post'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="topic" value="', $context['current_topic'], '" />
						<input type="hidden" name="move" value="', $context['move'], '" />
						<input type="hidden" name="goback" value="', $context['go_back'], '" />
					</div>
				</div>
				<br class="clear_right" />
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br />';
}

function template_announcement_send()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="' . $scripturl . '?action=announce;sa=send" method="post" accept-charset="', $context['character_set'], '" name="autoSubmit" id="autoSubmit">
			<div class="windowbg2">
				<span class="topslice"><span></span></span>
				<div class="content">
					<p>', $txt['announce_sending'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0" target="_blank" class="new_win">', $context['topic_subject'], '</a></p>
					<div class="progress_bar">
						<div class="full_bar">', $context['percentage_done'], '% ', $txt['announce_done'], '</div>
						<div class="green_percent" style="width: ', $context['percentage_done'], '%;">&nbsp;</div>
					</div>
					<hr class="hrcolor" />
					<div id="confirm_buttons">
						<input type="submit" name="b" value="', $txt['announce_continue'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="topic" value="', $context['current_topic'], '" />
						<input type="hidden" name="move" value="', $context['move'], '" />
						<input type="hidden" name="goback" value="', $context['go_back'], '" />
						<input type="hidden" name="start" value="', $context['start'], '" />
						<input type="hidden" name="membergroups" value="', $context['membergroups'], '" />
					</div>
				</div>
				<br class="clear_right" />
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br />
		<script type="text/javascript"><!-- // --><![CDATA[
			var countdown = 2;
			doAutoSubmit();

			function doAutoSubmit()
			{
				if (countdown == 0)
					document.forms.autoSubmit.submit();
				else if (countdown == -1)
					return;

				document.forms.autoSubmit.b.value = "', $txt['announce_continue'], ' (" + countdown + ")";
				countdown--;

				setTimeout("doAutoSubmit();", 1000);
			}
		// ]]></script>';
}

?>