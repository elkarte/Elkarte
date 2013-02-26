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

	// Start the javascript... and boy is there a lot.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[';

	// When using Go Back due to fatal_error, allow the form to be re-submitted with changes.
	if (isBrowser('is_firefox'))
		echo '
			window.addEventListener("pageshow", reActivate, false);';

	// Start with message icons - and any missing from this theme.
	echo '
			var icon_urls = {';
	foreach ($context['icons'] as $icon)
		echo '
				\'', $icon['value'], '\': \'', $icon['url'], '\'', $icon['is_last'] ? '' : ',';
	echo '
			};';

	// End of the javascript
	echo '
		// ]]></script>';

	// Start the form and display the link tree.
	echo '
		<form action="', $scripturl, '?action=', $context['destination'], ';', empty($context['current_board']) ? '' : 'board=' . $context['current_board'], '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="', ($context['becomes_approved'] ? '' : 'alert(\'' . $txt['js_post_will_require_approval'] . '\');'), 'submitonce(this);smc_saveEntities(\'postmodify\', [\'subject\', \'', $context['post_box_name'], '\', \'guestname\', \'evtitle\', \'question\'], \'options\');" enctype="multipart/form-data">';

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
			<div id="preview_section"', isset($context['preview_message']) ? '' : ' style="display: none;"', '>
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
					</h3>
				</div>
				<div class="windowbg">
					<div class="content">
						<div class="post" id="preview_body">
							', empty($context['preview_message']) ? '<br />' : $context['preview_message'], '
						</div>
					</div>
				</div>
			</div><br />';

	if ($context['make_event'] && (!$context['event']['new'] || !empty($context['current_board'])))
		echo '
			<input type="hidden" name="eventid" value="', $context['event']['id'], '" />';

	// Start the main table.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div>
				<div class="roundframe">', isset($context['current_topic']) ? '
					<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />' : '';

	// If an error occurred, explain what happened.
	template_show_error('post_error');

	// If this won't be approved let them know!
	if (!$context['becomes_approved'])
	{
		echo '
					<p class="information">
						<em>', $txt['wait_for_approval'], '</em>
						<input type="hidden" name="not_approved" value="1" />
					</p>';
	}

	// If it's locked, show a message to warn the replyer.
	echo '
					<p class="information"', $context['locked'] ? '' : ' style="display: none"', ' id="lock_warning">
						', $txt['topic_locked_no_reply'], '
					</p>';

	if (!empty($modSettings['drafts_post_enabled']))
		echo '
				<div id="draft_section" class="infobox"', isset($context['draft_saved']) ? '' : ' style="display: none;"', '>',
					sprintf($txt['draft_saved'], $scripturl . '?action=profile;u=' . $context['user']['id'] . ';area=showdrafts'), '
				</div>';

	// The post header... important stuff
	echo '
					<dl id="post_header">';

	// Guests have to put in their name and email...
	if (isset($context['name']) && isset($context['email']))
	{
		echo '
						<dt>
							<span', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? ' class="error"' : '', ' id="caption_guestname">', $txt['name'], ':</span>
						</dt>
						<dd>
							<input type="text" name="guestname" size="25" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" class="input_text" />
						</dd>';

		if (empty($modSettings['guest_post_no_email']))
			echo '
						<dt>
							<span', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? ' class="error"' : '', ' id="caption_email">', $txt['email'], ':</span>
						</dt>
						<dd>
							<input type="text" name="email" size="25" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" class="input_text" />
						</dd>';
	}

	// Now show the subject box for this post.
	echo '
						<dt class="clear">
							<span', isset($context['post_error']['no_subject']) ? ' class="error"' : '', ' id="caption_subject">', $txt['subject'], ':</span>
						</dt>
						<dd>
							<input id="post_subject" type="text" name="subject"', $context['subject'] == '' ? '' : ' value="' . $context['subject'] . '"', ' tabindex="', $context['tabindex']++, '" size="80" maxlength="80"', isset($context['post_error']['no_subject']) ? ' class="error"' : ' class="input_text"', '/>
						</dd>
						<dt class="clear_left">
							', $txt['message_icon'], ':
						</dt>
						<dd>
							<select name="icon" id="icon" onchange="showimage()">';

	// Loop through each message icon allowed, adding it to the drop down list.
	foreach ($context['icons'] as $icon)
		echo '
								<option value="', $icon['value'], '"', $icon['value'] == $context['icon'] ? ' selected="selected"' : '', '>', $icon['name'], '</option>';

	echo '
							</select>
							<img src="', $context['icon_url'], '" name="icons" hspace="15" alt="" />
						</dd>
					</dl>
					<hr class="clear" />';

	// Are you posting a calendar event?
	if ($context['make_event'])
	{
		echo '
					<div id="post_event">
						<fieldset id="event_main">
							<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', ' id="caption_evtitle">', $txt['calendar_event_title'], '</span></legend>
							<input type="text" name="evtitle" maxlength="255" size="55" value="', $context['event']['title'], '" tabindex="', $context['tabindex']++, '" class="input_text" />
							<div class="smalltext" style="white-space: nowrap;">
								<input type="hidden" name="calendar" value="1" />', $txt['calendar_year'], '
								<select name="year" id="year" tabindex="', $context['tabindex']++, '" onchange="generateDays();">';

		// Show a list of all the years we allow...
		for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
			echo '
									<option value="', $year, '"', $year == $context['event']['year'] ? ' selected="selected"' : '', '>', $year, '&nbsp;</option>';

		echo '
								</select>
								', $txt['calendar_month'], '
								<select name="month" id="month" onchange="generateDays();">';

		// There are 12 months per year - ensure that they all get listed.
		for ($month = 1; $month <= 12; $month++)
			echo '
									<option value="', $month, '"', $month == $context['event']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '&nbsp;</option>';

		echo '
								</select>
								', $txt['calendar_day'], '
								<select name="day" id="day">';

		// This prints out all the days in the current month - this changes dynamically as we switch months.
		for ($day = 1; $day <= $context['event']['last_day']; $day++)
			echo '
									<option value="', $day, '"', $day == $context['event']['day'] ? ' selected="selected"' : '', '>', $day, '&nbsp;</option>';

		echo '
								</select>
							</div>
						</fieldset>';

		if (!empty($modSettings['cal_allowspan']) || ($context['event']['new'] && $context['is_new_post']))
		{
			echo '
						<fieldset id="event_options">
							<legend>', $txt['calendar_event_options'], '</legend>
							<div class="event_options smalltext">
								<ul class="event_options">';

			// If events can span more than one day then allow the user to select how long it should last.
			if (!empty($modSettings['cal_allowspan']))
			{
				echo '
									<li>
										', $txt['calendar_numb_days'], '
										<select name="span">';

				for ($days = 1; $days <= $modSettings['cal_maxspan']; $days++)
					echo '
											<option value="', $days, '"', $days == $context['event']['span'] ? ' selected="selected"' : '', '>', $days, '&nbsp;</option>';

				echo '
										</select>
									</li>';
			}

			// If this is a new event let the user specify which board they want the linked post to be put into.
			if ($context['event']['new'] && $context['is_new_post'])
			{
				echo '
									<li>
										', $txt['calendar_post_in'], '
										<select name="board">';
				foreach ($context['event']['categories'] as $category)
				{
					echo '
											<optgroup label="', $category['name'], '">';
					foreach ($category['boards'] as $board)
						echo '
												<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '&nbsp;</option>';
					echo '
											</optgroup>';
				}
				echo '
										</select>
									</li>';
			}

			echo '
								</ul>
							</div>
						</fieldset>';
		}

		echo '
					</div>';
	}

	// If this is a poll then display all the poll options!
	if ($context['make_poll'])
	{
		echo '
					<hr class="clear" />
					<div id="edit_poll">';
		template_poll_edit();
		echo '
					</div>';
	}

	// Show the actual posting area...
	echo '
					', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	// If this message has been edited in the past - display when it was.
	if (isset($context['last_modified']))
		echo '
					<div class="padding smalltext">
						<strong>', $txt['last_edit'], ':</strong>
						', $context['last_modified_text'], '
					</div>';

	// If the admin has enabled the hiding of the additional options - show a link and image for it.
	if (!empty($settings['additional_options_collapsable']))
		echo '
					<div id="postAdditionalOptionsHeader" class="title_bar">
						<h4 class="titlebg">
							<img id="postMoreExpand" class="panel_toggle" style="display: none;" src="', $settings['images_url'], '/collapse.png" alt="-" /> <strong><a href="#" id="postMoreExpandLink">', $context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt'], '</a></strong>
						</h4>
					</div>
					<div id="postAdditionalOptions">';

	// Display the check boxes for all the standard options - if they are available to the user!
	echo '
						<div id="postMoreOptions" class="smalltext">
							<ul class="post_options">
								', $context['can_notify'] ? '<li><input type="hidden" name="notify" value="0" /><label for="check_notify"><input type="checkbox" name="notify" id="check_notify"' . ($context['notify'] || !empty($options['auto_notify']) ? ' checked="checked"' : '') . ' value="1" class="input_check" /> ' . $txt['notify_replies'] . '</label></li>' : '', '
								', $context['can_lock'] ? '<li><input type="hidden" name="lock" value="0" /><label for="check_lock"><input type="checkbox" name="lock" id="check_lock"' . ($context['locked'] ? ' checked="checked"' : '') . ' value="1" class="input_check" /> ' . $txt['lock_topic'] . '</label></li>' : '', '
								<li><label for="check_back"><input type="checkbox" name="goback" id="check_back"' . ($context['back_to_topic'] || !empty($options['return_to_post']) ? ' checked="checked"' : '') . ' value="1" class="input_check" /> ' . $txt['back_to_topic'] . '</label></li>
								', $context['can_sticky'] ? '<li><input type="hidden" name="sticky" value="0" /><label for="check_sticky"><input type="checkbox" name="sticky" id="check_sticky"' . ($context['sticky'] ? ' checked="checked"' : '') . ' value="1" class="input_check" /> ' . $txt['sticky_after'] . '</label></li>' : '', '
								<li><label for="check_smileys"><input type="checkbox" name="ns" id="check_smileys"', $context['use_smileys'] ? '' : ' checked="checked"', ' value="NS" class="input_check" /> ', $txt['dont_use_smileys'], '</label></li>', '
								', $context['can_move'] ? '<li><input type="hidden" name="move" value="0" /><label for="check_move"><input type="checkbox" name="move" id="check_move" value="1" class="input_check" ' . (!empty($context['move']) ? 'checked="checked" ' : '') . '/> ' . $txt['move_after2'] . '</label></li>' : '', '
								', $context['can_announce'] && $context['is_first_post'] ? '<li><label for="check_announce"><input type="checkbox" name="announce_topic" id="check_announce" value="1" class="input_check" ' . (!empty($context['announce']) ? 'checked="checked" ' : '') . '/> ' . $txt['announce_topic'] . '</label></li>' : '', '
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
								<input type="file" size="60" multiple="multiple" name="attachment[]" id="attachment1" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFileInput(\'attachment1\');">', $txt['clean_attach'], '</a>)';
			// Show more boxes if they aren't approaching that limit.
			if ($context['num_allowed_attachments'] > 1)
				echo '
								<script type="text/javascript"><!-- // --><![CDATA[
									var allowed_attachments = ', $context['num_allowed_attachments'], ';
									var current_attachment = 1;
									var txt_more_attachments_error = "', $txt['more_attachments_error'], '";
									var txt_more_attachments = "', $txt['more_attachments'], '";
									var txt_clean_attach = "', $txt['clean_attach'], '";
								// ]]></script>
							</dd>
							<dd class="smalltext" id="moreAttachments"><a href="#" onclick="addAttachment(); return false;">(', $txt['more_attachments'], ')</a></dd>';
			else
				echo '
							</dd>';
		}

		// Add any template changes for an alternative upload system here.
		call_integration_hook('integrate_upload_template');

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
	// If the admin enabled the drafts feature, show a draft selection box
	if (!empty($modSettings['drafts_enabled']) && !empty($context['drafts']) && !empty($options['drafts_show_saved_enabled']))
	{
		echo '
					<br />
					<div id="postDraftOptionsHeader" class="title_bar">
						<h4 class="titlebg">
							<img id="postDraftExpand" class="panel_toggle" style="display: none;" src="', $settings['images_url'], '/collapse.png" alt="-" /> <strong><a href="#" id="postDraftExpandLink">', $txt['draft_load'], '</a></strong>
						</h4>
					</div>
					<div id="postDraftOptions" class="load_drafts padding">
						<dl class="settings">
							<dt><strong>', $txt['subject'], '</strong></dt>
							<dd><strong>', $txt['draft_saved_on'], '</strong></dd>';

		foreach ($context['drafts'] as $draft)
			echo '
							<dt>', $draft['link'], '</dt>
							<dd>', $draft['poster_time'], '</dd>';
		echo '
						</dl>
					</div>';
	}

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
	echo '
					<hr class="hrcolor" />
					<span id="post_confirm_buttons">
						', template_control_richedit_buttons($context['post_box_name']);

	// Option to delete an event if user is editing one.
	if ($context['make_event'] && !$context['event']['new'])
		echo '
						<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" onclick="return confirm(\'', $txt['event_delete_confirm'], '\');" class="button_submit" />';

	echo '
					</span>
				</div>
			</div>
			<br class="clear" />';

	// Assuming this isn't a new topic pass across the last message id.
	if (isset($context['topic_last_message']))
		echo '
			<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />';

	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', $context['show_additional_options'] ? '1' : '0', '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
		</form>';

	// The variables used to preview a post without loading a new page.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var post_box_name = "', $context['post_box_name'], '";
			var form_name = "postmodify";
			var preview_area = "post";
			var current_board = ', empty($context['current_board']) ? 'null' : $context['current_board'], ';
			var txt_preview_title = "', $txt['preview_title'], '";
			var txt_preview_fetch = "', $txt['preview_fetch'], '";
			var make_poll = ', $context['make_poll'] ? 'true' : 'false', ';
			var new_replies = new Array();
			var reply_counter = ', empty($counter) ? 0 : $counter, ';
			var can_quote = ', $context['can_quote'] ? 'true' : 'false', ';
			var show_ignore_user_post = "', $txt['show_ignore_user_post'], '";
			var txt_bbc_quote = "', $txt['bbc_quote'], '";
			var txt_ignoring_user = "', $txt['ignoring_user'], '";
			var txt_new = "', $txt['new'], '";
			var txt_posted_by = "', $txt['posted_by'], '";
			var txt_on = "', $txt['on'], '";';

	// Code for showing and hiding additional options.
	if (!empty($settings['additional_options_collapsable']))
		echo '
			var oSwapAdditionalOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', $context['show_additional_options'] ? 'false' : 'true', ',
				funcOnBeforeCollapse: function () {
					document.getElementById(\'additional_options\').value = \'0\';
				},
				funcOnBeforeExpand: function () {
					document.getElementById(\'additional_options\').value = \'1\';
				},
				aSwappableContainers: [
					\'postAdditionalOptions\',
				],
				aSwapImages: [
					{
						sId: \'postMoreExpand\',
						srcExpanded: smf_images_url + \'/collapse.png\',
						altExpanded: \'-\',
						srcCollapsed: smf_images_url + \'/expand.png\',
						altCollapsed: \'+\'
					}
				],
				aSwapLinks: [
					{
						sId: \'postMoreExpandLink\',
						msgExpanded: ', JavaScriptEscape($context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt']), ',
						msgCollapsed: ', JavaScriptEscape($context['can_post_attachment'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt']), '
					}
				]
			});';

	// Code for showing and hiding drafts
	if (!empty($context['drafts']))
		echo '
			var oSwapDraftOptions = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					\'postDraftOptions\',
				],
				aSwapImages: [
					{
						sId: \'postDraftExpand\',
						srcExpanded: smf_images_url + \'/collapse.png\',
						altExpanded: \'-\',
						srcCollapsed: smf_images_url + \'/expand.png\',
						altCollapsed: \'+\'
					}
				],
				aSwapLinks: [
					{
						sId: \'postDraftExpandLink\',
						msgExpanded: ', JavaScriptEscape($txt['draft_hide']), ',
						msgCollapsed: ', JavaScriptEscape($txt['draft_load']), '
					}
				]
			});';

	echo '
		// ]]></script>';

	// If the user is replying to a topic show the previous posts.
	if (isset($context['previous_posts']) && count($context['previous_posts']) > 0)
	{
		echo '
		<div id="recent" class="flow_hidden main_section">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['topic_summary'], '</h3>
			</div>
			<span id="new_replies"></span>';

		$ignored_posts = array();
		foreach ($context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];

			echo '
				<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
				<div class="content" id="msg', $post['id'], '">
					<div class="floatleft">
						<h5>', $txt['posted_by'], ': ', $post['poster'], '</h5>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['on'], ':</strong> ', $post['time'], '&nbsp;&#187;</span>
					</div>';

			if ($context['can_quote'])
			{
				echo '
					<ul class="reset smalltext quickbuttons" id="msg_', $post['id'], '_quote">
						<li><a href="#postmodify" onclick="return insertQuoteFast(', $post['id'], ');" class="quote_button"><span>',$txt['bbc_quote'],'</span></a></li>
					</ul>';
			}

			echo '
					<br class="clear" />';

			if ($ignoring)
			{
				echo '
					<div id="msg_', $post['id'], '_ignored_prompt" class="smalltext">
						', $txt['ignoring_user'], '
						<a href="#" id="msg_', $post['id'], '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
					</div>';
			}

			echo '
					<div class="list_posts smalltext" id="msg_', $post['id'], '_body">', $post['message'], '</div>
				</div>
			</div>';
		}

		echo '
		</div>
		<script type="text/javascript"><!-- // --><![CDATA[
			var post_box_name = "', $context['post_box_name'], '";
			var aIgnoreToggles = new Array();';

		foreach ($ignored_posts as $post_id)
		{
			echo '
			aIgnoreToggles[', $post_id, '] = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					\'msg_', $post_id, '_body\',
					\'msg_', $post_id, '_quote\',
				],
				aSwapLinks: [
					{
						sId: \'msg_', $post_id, '_ignored_link\',
						msgExpanded: \'\',
						msgCollapsed: ', JavaScriptEscape($txt['show_ignore_user_post']), '
					}
				]
			});';
		}

		echo '
		// ]]></script>';
	}
}

// The template for the spellchecker.
function template_spellcheck()
{
	global $context, $settings, $txt;

	// The style information that makes the spellchecker look... like the forum hopefully!
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $txt['spell_check'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
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
			}
		</style>';

	// As you may expect - we need a lot of javascript for this... load it from the separate files.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var spell_formname = window.opener.spell_formname;
			var spell_fieldname = window.opener.spell_fieldname;
			var spell_full = window.opener.spell_full;
		// ]]></script>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/spellcheck.js"></script>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			', $context['spell_js'], '
		// ]]></script>
	</head>
	<body onload="nextWord(false);">
		<form action="#" method="post" accept-charset="UTF-8" name="spellingForm" id="spellingForm" onsubmit="return false;" style="margin: 0;">
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
	global $context, $settings, $txt;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
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

			window.opener.onReceiveOpener(quote);

			window.focus();
			setTimeout("window.close();", 400);';
	}
	echo '
		// ]]></script>
	</body>
</html>';
}