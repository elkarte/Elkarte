<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Load in the generic templates for use
 */
function template_Post_init()
{
	theme()->getTemplates()->load('GenericHelpers');
}

/**
 * The area above the post box,
 * Typically holds subject, preview, info messages, message icons, etc
 */
function template_postarea_above()
{
	global $context, $scripturl, $txt, $modSettings;

	// Start the javascript...
	echo '
		<script>';

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
		</script>';

	// Start the form and display the link tree.
	echo '
		<form id="postmodify" action="', $scripturl, '?action=', $context['destination'], ';', empty($context['current_board']) ? '' : 'board=' . $context['current_board'], '" method="post" accept-charset="UTF-8" name="postmodify" class="flow_hidden" onsubmit="', ($context['becomes_approved'] ? '' : 'alert(\'' . $txt['js_post_will_require_approval'] . '\');'), 'submitonce(this);smc_saveEntities(\'postmodify\', [\'subject\', \'', $context['post_box_name'], '\', \'guestname\', \'evtitle\', \'question\'], \'options\');revalidateMentions(\'postmodify\', \'', $context['post_box_name'], '\');" enctype="multipart/form-data">';

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
			<div id="preview_section"', isset($context['preview_message']) ? '' : ' class="hide"', '>
				<h2 class="category_header">
					<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
				</h2>
				<div id="preview_body">
					', empty($context['preview_message']) ? '<br />' : $context['preview_message'], '
				</div>
			</div>';

	// Start the main table.
	echo '
			<div id="forumposts">', isset($context['current_topic']) ? '<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />' : '', '
				<h2 class="category_header">', $context['page_title'], '</h2>
				<div class="forumposts">
					<div class="editor_wrapper">';

	// If an error occurred, explain what happened.
	template_show_error('post_error');
	if (!empty($context['attachment_error_keys']))
		template_attachment_errors();

	// If this won't be approved let them know!
	// @todo why not use the template_show_error above?
	if (!$context['becomes_approved'])
	{
		echo '
						<div class="successbox">
							', $txt['wait_for_approval'], '
							<input type="hidden" name="not_approved" value="1" />
						</div>';
	}

	// If it's locked, show a message to warn the replyer.
	// @todo why not output it only for locked topics and why not use the template_show_error above?
	echo '
						<p id="lock_warning" class="information', $context['locked'] ? '"' : ' hide"', '>
							', $txt['topic_locked_no_reply'], '
						</p>';

	if (!empty($context['drafts_autosave']))
		echo '
						<div id="draft_section" class="successbox', isset($context['draft_saved']) ? '"' : ' hide"', '>
							', sprintf($txt['draft_saved'], getUrl('profile', ['action' => 'profile', 'area' => 'showdrafts', 'u' => $context['user']['id'], 'name' => $context['user']['name']])), '
						</div>';

	// The post header... important stuff
	echo '
						<dl id="post_header">';

	// Guests have to put in their name and email...
	if (isset($context['name']) && isset($context['email']))
	{
		echo '
							<dt>
								<label for="guestname"', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? ' class="error"' : '', ' id="caption_guestname">', $txt['name'], ':</label>
							</dt>
							<dd>
								<input type="text" id="guestname" name="guestname" size="25" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" class="input_text" required="required" />
							</dd>';

		if (empty($modSettings['guest_post_no_email']))
			echo '
							<dt>
								<label for="email"', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? ' class="error"' : '', ' id="caption_email">', $txt['email'], ':</label>
							</dt>
							<dd>
								<input type="email" id="email" name="email" size="25" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" class="input_text" required="required" />
							</dd>';
	}

	// Now show the subject box for this post.
	echo '
							<dt class="clear">
								<label for="post_subject"', isset($context['post_error']['no_subject']) ? ' class="error"' : '', ' id="caption_subject">', $txt['subject'], ':</label>
							</dt>
							<dd>
								<input id="post_subject" type="text" name="subject"', $context['subject'] == '' ? '' : ' value="' . $context['subject'] . '"', ' tabindex="', $context['tabindex']++, '" size="80" maxlength="80"', isset($context['post_error']['no_subject']) ? ' class="error"' : ' class="input_text"', ' placeholder="', $txt['subject'], '" required="required" />
							</dd>
							<dt class="clear_left">
								<label for="icon">', $txt['message_icon'], '</label>:
							</dt>
							<dd>
								<select name="icon" id="icon" onchange="showimage()">';

	// Loop through each message icon allowed, adding it to the drop down list.
	foreach ($context['icons'] as $icon)
		echo '
									<option value="', $icon['value'], '"', $icon['value'] == $context['icon'] ? ' selected="selected"' : '', '>', $icon['name'], '</option>';

	echo '
								</select>
								<img src="', $context['icon_url'], '" id="icons" alt="" />
							</dd>';

	if (!empty($context['show_boards_dropdown']))
		echo '
							<dt class="clear_left">
								<label for="post_in_board">', $txt['post_in_board'], '</label>:
							</dt>
							<dd>', template_select_boards('post_in_board'), '
							</dd>';

	echo '
						</dl>';
}

/**
 * Area above the poll edit
 */
function template_poll_edit_above()
{
	echo '
						<div class="separator"></div>
						<div id="edit_poll">';

	template_poll_edit();

	echo '
						</div>';
}

/**
 * Area above the event box
 */
function template_make_event_above()
{
	global $context, $txt, $modSettings;

	// Are you posting a calendar event?
	echo '
						<hr class="clear" />
						<div id="post_event">
							<fieldset id="event_main">
								<legend>', $txt['calendar_event_options'], '</legend>
								<label for="evtitle"', isset($context['post_error']['no_event']) ? ' class="error"' : '', ' id="caption_evtitle">', $txt['calendar_event_title'], ':</label>
								<input type="text" id="evtitle" name="evtitle" maxlength="255" size="55" value="', $context['event']['title'], '" tabindex="', $context['tabindex']++, '" class="input_text" />
								<div id="datepicker">
									<input type="hidden" name="calendar" value="1" /><label for="year">', $txt['calendar_year'], '</label>
									<select name="year" id="year" tabindex="', $context['tabindex']++, '" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = $context['cal_minyear']; $year <= $context['cal_maxyear']; $year++)
		echo '
										<option value="', $year, '"', $year == $context['event']['year'] ? ' selected="selected"' : '', '>', $year, '&nbsp;</option>';

	echo '
									</select>
									<label for="month">', $txt['calendar_month'], '</label>
									<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
										<option value="', $month, '"', $month == $context['event']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '&nbsp;</option>';

	echo '
									</select>
									<label for="day">', $txt['calendar_day'], '</label>
									<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['event']['last_day']; $day++)
		echo '
										<option value="', $day, '"', $day == $context['event']['day'] ? ' selected="selected"' : '', '>', $day, '&nbsp;</option>';

	echo '
									</select>
								</div>';

	if (!empty($modSettings['cal_allowspan']) || ($context['event']['new'] && $context['is_new_post']))
	{
		echo '
								<ul class="event_options">';

		// If events can span more than one day then allow the user to select how long it should last.
		if (!empty($modSettings['cal_allowspan']))
		{
			echo '
									<li>
										<label for="span">', $txt['calendar_numb_days'], '</label>
										<select id="span" name="span">';

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
										', template_select_boards('board', $txt['calendar_post_in']), '
									</li>';
		}

		echo '
								</ul>';
	}

	if ($context['make_event'] && (!$context['event']['new'] || !empty($context['current_board'])))
		echo '
								<input type="hidden" name="eventid" value="', $context['event']['id'], '" />';

	echo '
							</fieldset>
						</div>';
}

/**
 * The main template for the post page.
 */
function template_post_page()
{
	global $context, $txt;

	// Show the actual posting area...
	echo '
					', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');

	// A placeholder for our mention box if needed
	if (!empty($context['member_ids']))
	{
		echo '
							<div id="mentioned" class="hide">';

		foreach ($context['member_ids'] as $id)
			echo '
								<input type="hidden" name="uid[]" value="', $id, '" />';

		echo '
							</div>';
	}

	// Show our submit buttons before any more options
	echo '
						<div id="post_confirm_buttons" class="submitbutton">
							', template_control_richedit_buttons($context['post_box_name']);

	// Option to delete an event if user is editing one.
	if (!empty($context['make_event']) && !$context['event']['new'])
		echo '
							<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" onclick="return confirm(\'', $txt['event_delete_confirm'], '\');" />';

	// Option to add a poll (javascript if enabled, otherwise preview with poll)
	if (empty($context['make_poll']) && $context['can_add_poll'])
		echo '
							<input type="submit" name="poll" aria-label="', $txt['add_poll'], '" value="', $txt['add_poll'], '" onclick="return loadAddNewPoll(this, ', empty($context['current_board']) ? '0' : $context['current_board'], ', \'postmodify\');" />';

	echo '
						</div>';
}

/**
 * Show the additional options section, allowing locking, sticky, adding of attachments, etc
 */
function template_additional_options_below()
{
	global $context, $settings, $options, $txt;

	// If the admin has enabled the hiding of the additional options - show a link and image for it.
	if (!empty($settings['additional_options_collapsible']))
		echo '
					<h3 id="postAdditionalOptionsHeader" class="category_header panel_toggle">
							<i id="postMoreExpand" class="chevricon i-chevron-', empty($context['minmax_preferences']['post']) ? 'up' : 'down', ' hide" title="', $txt['hide'], '"></i>
						<a href="#" id="postMoreExpandLink">', !empty($context['attachments']) && $context['attachments']['can']['post'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt'], '</a>
					</h3>';

	echo '
					<div id="', empty($settings['additional_options_collapsible']) ? 'postAdditionalOptionsNC"' : 'postAdditionalOptions"', empty($settings['additional_options_collapsible']) || empty($context['minmax_preferences']['post']) ? '' : ' class="hide"', '>';

	// Is the user allowed to post or if this post already has attachments on it give them the boxes.
	if (!empty($context['attachments']) && ($context['attachments']['can']['post'] || !empty($context['attachments']['current'])))
		$context['attachments']['template']();

	// Display the check boxes for all the standard options - if they are available to the user!
	echo '
						<div id="postMoreOptions" class="smalltext">
							<ul class="post_options">
								', $context['can_notify'] ? '<li><input type="hidden" name="notify" value="0" /><label for="check_notify"><input type="checkbox" name="notify" id="check_notify"' . ($context['notify'] || !empty($options['auto_notify']) ? ' checked="checked"' : '') . ' value="1" /> ' . $txt['notify_replies'] . '</label></li>' : '', '
								', $context['can_lock'] ? '<li><input type="hidden" name="lock" value="0" /><label for="check_lock"><input type="checkbox" name="lock" id="check_lock"' . ($context['locked'] ? ' checked="checked"' : '') . ' value="1" /> ' . $txt['lock_topic'] . '</label></li>' : '', '
								<li><label for="check_back"><input type="checkbox" name="goback" id="check_back"' . ($context['back_to_topic'] || !empty($options['return_to_post']) ? ' checked="checked"' : '') . ' value="1" /> ' . $txt['back_to_topic'] . '</label></li>
								', $context['can_sticky'] ? '<li><input type="hidden" name="sticky" value="0" /><label for="check_sticky"><input type="checkbox" name="sticky" id="check_sticky"' . ($context['sticky'] ? ' checked="checked"' : '') . ' value="1" /> ' . $txt['sticky_after'] . '</label></li>' : '', '
								<li><label for="check_smileys"><input type="checkbox" name="ns" id="check_smileys"', $context['use_smileys'] ? '' : ' checked="checked"', ' value="NS" /> ', $txt['dont_use_smileys'], '</label></li>', '
								', $context['can_move'] ? '<li><input type="hidden" name="move" value="0" /><label for="check_move"><input type="checkbox" name="move" id="check_move" value="1" ' . (!empty($context['move']) ? 'checked="checked" ' : '') . '/> ' . $txt['move_after2'] . '</label></li>' : '', '
								', $context['can_announce'] && $context['is_first_post'] ? '<li><label for="check_announce"><input type="checkbox" name="announce_topic" id="check_announce" value="1" ' . (!empty($context['announce']) ? 'checked="checked" ' : '') . '/> ' . $txt['announce_topic'] . '</label></li>' : '', '
								', $context['show_approval'] ? '<li><label for="approve"><input type="checkbox" name="approve" id="approve" value="2" ' . ($context['show_approval'] === 2 ? 'checked="checked"' : '') . ' /> ' . $txt['approve_this_post'] . '</label></li>' : '', '
							</ul>
						</div>';

	echo '
					</div>';
}

/**
 * Creates the interface to upload attachments
 */
function template_add_new_attachments()
{
	global $context, $txt, $modSettings;

	echo '
						<span id="postAttachment"></span>
						<dl id="postAttachment2">';

	// Show the box even if they reached the limit, maybe they want to remove one!
	if (empty($context['dont_show_them']))
	{
		echo '
							<dt class="drop_area">
								<i class="icon i-upload"></i>
								<span class="desktop">', $txt['attach_drop_files'], '</span>
								<span class="mobile">', $txt['attach_drop_files_mobile'], '</span>
								<input id="attachment_click" class="drop_area_fileselect input_file" type="file" multiple="multiple" name="attachment_click[]" />
							</dt>
							<dd class="progress_tracker"></dd>
							<dd class="drop_attachments_error"></dd>
							<dt class="drop_attachments_no_js">
								', $txt['attach'], ':
							</dt>
							<dd class="smalltext drop_attachments_no_js">
								', empty($modSettings['attachmentSizeLimit']) ? '' : ('<input type="hidden" name="MAX_FILE_SIZE" value="' . $modSettings['attachmentSizeLimit'] * 1028 . '" />'), '
								<input type="file" multiple="multiple" name="attachment[]" id="attachment1" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFileInput(\'attachment1\');">', $txt['clean_attach'], '</a>)';

		// Show more boxes if they aren't approaching that limit.
		if ($context['attachments']['num_allowed'] > 1)
			echo '
								<script>
									var allowed_attachments = ', $context['attachments']['num_allowed'], ',
										current_attachment = 1,
										txt_more_attachments_error = "', $txt['more_attachments_error'], '",
										txt_more_attachments = "', $txt['more_attachments'], '",
										txt_clean_attach = "', $txt['clean_attach'], '";
								</script>
							</dd>
							<dd class="smalltext drop_attachments_no_js" id="moreAttachments"><a href="#" onclick="addAttachment(); return false;">(', $txt['more_attachments'], ')</a></dd>';
		else
			echo '
							</dd>';
	}

	foreach ($context['attachments']['current'] as $attachment)
	{
		$label = $attachment['name'];
		if (empty($attachment['approved']))
			$label .= ' (' . $txt['awaiting_approval'] . ')';
		if (!empty($modSettings['attachmentPostLimit']) || !empty($modSettings['attachmentSizeLimit']))
			$label .= sprintf($txt['attach_kb'], comma_format(round(max($attachment['size'], 1028) / 1028), 0));

		echo '
							<dd class="smalltext">
								<label for="attachment_', $attachment['id'], '">
									<input type="checkbox" id="attachment_', $attachment['id'], '" name="attach_del[]" value="', $attachment['id'], '"', empty($attachment['unchecked']) ? ' checked="checked"' : '', ' class="input_check inline_insert" data-attachid="', $attachment['id'], '" data-size="', $attachment['size'], '"/> ', $label, '
								</label>
							</dd>';
	}

	echo '
							<dd class="smalltext">';

	// Show some useful information such as allowed extensions, maximum size and amount of attachments allowed.
	if (!empty($context['attachments']['allowed_extensions']))
		echo '
								', $txt['allowed_types'], ': ', $context['attachments']['allowed_extensions'], '<br />';

	if (!empty($context['attachments']['restrictions']))
		echo '
								', $txt['attach_restrictions'], ' ', implode(', ', $context['attachments']['restrictions']), '<br />';

	if ($context['attachments']['num_allowed'] == 0)
		echo '
								', $txt['attach_limit_nag'], '<br />';

	if (!$context['attachments']['can']['post_unapproved'])
		echo '
								<span class="alert">', $txt['attachment_requires_approval'], '</span>', '<br />';

	echo '
							</dd>
						</dl>';

	if (!empty($context['attachments']['ila_enabled']))
	{
		theme()->addInlineJavascript('
		var IlaDropEvents = {
			UploadSuccess: function($button, data) {
				var inlineAttach = ElkInlineAttachments(\'#postAttachment2,#postAttachment\', \'' . $context['post_box_name'] . '\', {
					trigger: $(\'<div class="share icon i-share" />\'),
					template: ' . JavaScriptEscape('<div class="insertoverlay">
						<input type="button" class="button" value="insert">
						<ul data-group="tabs" class="tabs">
							<li data-tab="size">' . $txt['ila_opt_size'] . '</li><li data-tab="align">' . $txt['ila_opt_align'] . '</li>
						</ul>
						<div class="container" data-visual="size">
							<label><input data-size="thumb" type="radio" name="imgmode">' . $txt['ila_opt_size_thumb'] . '</label>
							<label><input data-size="full" type="radio" name="imgmode">' . $txt['ila_opt_size_full'] . '</label>
							<label><input data-size="cust" type="radio" name="imgmode">' . $txt['ila_opt_size_cust'] . '</label>
							<div class="customsize">
								<input type="range" class="range" min="100" max="500"><input type="text" class="visualizesize" disabled="disabled">
							</div>
						</div>
						<div class="container" data-visual="align">
							<label><input data-align="none" type="radio" name="align">' . $txt['ila_opt_align_none'] . '</label>
							<label><input data-align="left" type="radio" name="align">' . $txt['ila_opt_align_left'] . '</label>
							<label><input data-align="center" type="radio" name="align">' . $txt['ila_opt_align_center'] . '</label>
							<label><input data-align="right" type="radio" name="align">' . $txt['ila_opt_align_right'] . '</label>
						</div>
					</div>') . '
				});
				inlineAttach.addInterface($button, data.attachid);
			},
			RemoveSuccess: function(attachid) {
				var inlineAttach = ElkInlineAttachments(\'#postAttachment2,#postAttachment\', \'' . $context['post_box_name'] . '\', {
					trigger: $(\'<div class="share icon i-share" />\')
				});
				inlineAttach.removeAttach(attachid);
			}
		};', true);
	}
	else
	{
		theme()->addInlineJavascript('
		var IlaDropEvents = {};', true);
	}

	// Load up the drag and drop attachment magic
	theme()->addInlineJavascript('
	var dropAttach = new dragDropAttachment({
		board: ' . $context['current_board'] . ',
		allowedExtensions: ' . JavaScriptEscape($context['attachments']['allowed_extensions']) . ',
		totalSizeAllowed: ' . JavaScriptEscape(empty($modSettings['attachmentPostLimit']) ? '' : $modSettings['attachmentPostLimit']) . ',
		individualSizeAllowed: ' . JavaScriptEscape(empty($modSettings['attachmentSizeLimit']) ? '' : $modSettings['attachmentSizeLimit']) . ',
		numOfAttachmentAllowed: ' . $context['attachments']['num_allowed'] . ',
		totalAttachSizeUploaded: ' . (isset($context['attachments']['total_size']) && !empty($context['attachments']['total_size']) ? $context['attachments']['total_size'] : 0) . ',
		numAttachUploaded: ' . (isset($context['attachments']['quantity']) && !empty($context['attachments']['quantity']) ? $context['attachments']['quantity'] : 0) . ',
		fileDisplayTemplate: \'<div class="statusbar"><div class="info"></div><div class="progressBar"><div></div></div><div class="control icon i-close"></div></div>\',
		oTxt: {
			allowedExtensions : ' . JavaScriptEscape(sprintf($txt['cant_upload_type'], $context['attachments']['allowed_extensions'])) . ',
			totalSizeAllowed : ' . JavaScriptEscape($txt['attach_max_total_file_size']) . ',
			individualSizeAllowed : ' . JavaScriptEscape(sprintf($txt['file_too_big'], comma_format($modSettings['attachmentSizeLimit'], 0))) . ',
			numOfAttachmentAllowed : ' . JavaScriptEscape(sprintf($txt['attachments_limit_per_post'], $modSettings['attachmentNumPerPostLimit'])) . ',
			postUploadError : ' . JavaScriptEscape($txt['post_upload_error']) . ',
			areYouSure: ' . JavaScriptEscape($txt['ila_confirm_removal']) . '
		},
		existingSelector: \'.inline_insert\',
		events: IlaDropEvents' . (isset($context['current_topic']) ? ',
			topic: ' . $context['current_topic'] : '') . '
	});', true);
}

/**
 * Shows the draft selection box
 */
function template_load_drafts_below()
{
	global $context, $txt;

	// Show a draft selection box
	echo '
					<h3 id="postDraftOptionsHeader" class="category_header panel_toggle">
							<i id="postDraftExpand" class="chevricon i-chevron-', empty($context['minmax_preferences']['draft']) ? 'up' : 'down', ' hide" title="', $txt['hide'], '"></i>
						<a href="#" id="postDraftExpandLink">', $txt['draft_load'], '</a>
					</h3>
					<div id="postDraftOptions"', empty($context['minmax_preferences']['draft']) ? '' : ' class="hide"', '>
						<dl class="settings">
							<dt>
								<strong>', $txt['subject'], '</strong>
							</dt>
							<dd>
								<strong>', $txt['draft_saved_on'], '</strong>
							</dd>';

	foreach ($context['drafts'] as $draft)
		echo '
							<dt>', $draft['link'], '</dt>
							<dd>', $draft['poster_time'], '</dd>';

	echo '
						</dl>
					</div>';

	// Code for showing and hiding drafts
	theme()->addInlineJavascript('
			var oSwapDraftOptions = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['draft']) ? 'false' : 'true') . ',
				aSwappableContainers: [
					\'postDraftOptions\',
				],
				aSwapClasses: [
					{
						sId: \'postDraftExpand\',
						classExpanded: \'chevricon i-chevron-up\',
						titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
						classCollapsed: \'chevricon i-chevron-down\',
						titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
					}
				],
				aSwapLinks: [
					{
						sId: \'postDraftExpandLink\',
						msgExpanded: ' . JavaScriptEscape($txt['draft_hide']) . ',
						msgCollapsed: ' . JavaScriptEscape($txt['draft_load']) . '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
					sOptionName: \'minmax_preferences\',
					sSessionId: elk_session_id,
					sSessionVar: elk_session_var,
					sAdditionalVars: \';minmax_key=draft\'
				},
			});
		', true);
}

/**
 * Show the topic replies below the post box
 */
function template_topic_replies_below()
{
	global $context, $txt;

	// If the user is replying to a topic show the previous posts.
	if (isset($context['previous_posts']) && count($context['previous_posts']) > 0)
	{
		echo '
		<div id="topic_summary">
			<h2 class="category_header">', $txt['topic_summary'], '</h2>
			<span id="new_replies"></span>';

		$ignored_posts = array();
		foreach ($context['previous_posts'] as $post)
		{
			$ignoring = false;
			if (!empty($post['is_ignored']))
				$ignored_posts[] = $ignoring = $post['id'];

			echo '
			<div class="content forumposts">
				<div class="postarea2" id="msg', $post['id'], '">
					<div class="keyinfo">
						<h5 class="floatleft">
							<span>', $txt['posted_by'], '</span>&nbsp;', $post['poster'], '&nbsp;-&nbsp;', $post['time'], '
						</h5>';

			if ($context['can_quote'])
				echo '
						<ul class="quickbuttons" id="msg_', $post['id'], '_quote">
							<li class="listlevel1"><a href="#postmodify" onmousedown="return insertQuoteFast(', $post['id'], ');" class="linklevel1 quote_button">', $txt['bbc_quote'], '</a></li>
						</ul>';

			echo '
					</div>';

			if ($ignoring)
				echo '
					<div id="msg_', $post['id'], '_ignored_prompt">
						', $txt['ignoring_user'], '
						<a href="#" id="msg_', $post['id'], '_ignored_link" class="hide">', $txt['show_ignore_user_post'], '</a>
					</div>';

			echo '
					<div class="inner" id="msg_', $post['id'], '_body">', $post['body'], '</div>
				</div>
			</div>';
		}

		echo '
		</div>
		<script>
			var aIgnoreToggles = [];';

		foreach ($ignored_posts as $post_id)
		{
			echo '
			aIgnoreToggles[', $post_id, '] = new elk_Toggle({
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
		</script>';
	}
}

/**
 * The area below the postbox
 * Typically holds our action buttons, save, preivew, drafts, etc
 * Oh and lots of JS ;)
 */
function template_postarea_below()
{
	global $context, $txt, $settings;

	// Is visual verification enabled?
	if (!empty($context['require_verification']))
	{
		template_verification_controls($context['visual_verification_id'], '
						<div class="post_verification">
							<span' . (!empty($context['post_error']['need_qr_verification']) ? ' class="error"' : '') . '>
								<strong>' . $txt['verification'] . ':</strong>
							</span>
							', '
						</div>');
	}

	echo '
						</div>
					</div>
				</div>';

	// Assuming this isn't a new topic pass across the last message id.
	if (isset($context['topic_last_message']))
	{
		echo '
			<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />';
	}

	// Better remember the draft id when passing from a page to another.
	if (isset($context['id_draft']))
	{
		echo '
			<input type="hidden" name="id_draft" value="', $context['id_draft'], '" />';
	}

	// If we are starting a new topic starting from another one, here is the place to remember some details
	if (!empty($context['original_post']))
		echo '
			<input type="hidden" name="followup" value="' . $context['original_post'] . '" />';

	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', $context['show_additional_options'] ? '1' : '0', '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
		</form>';

	// The variables used to preview a post without loading a new page.
	echo '
		<script>
			var form_name = "postmodify",
				preview_area = "post",
				current_board = ', empty($context['current_board']) ? 'null' : $context['current_board'], ',
				txt_preview_title = "', $txt['preview_title'], '",
				txt_preview_fetch = "', $txt['preview_fetch'], '",
				make_poll = ', $context['make_poll'] ? 'true' : 'false', ',
				new_replies = new Array(),
				can_quote = ', $context['can_quote'] ? 'true' : 'false', ',
				show_ignore_user_post = "', $txt['show_ignore_user_post'], '",
				txt_bbc_quote = "', $txt['bbc_quote'], '",
				txt_ignoring_user = "', $txt['ignoring_user'], '",
				txt_new = "', $txt['new'], '",
				txt_posted_by = "', $txt['posted_by'], '",
				txt_on = "', $txt['on'], '";
		</script>';

	// Code for showing and hiding additional options.
	if (!empty($settings['additional_options_collapsible']))
		theme()->addInlineJavascript('
			var oSwapAdditionalOptions = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['post']) ? 'false' : 'true') . ',
				funcOnBeforeCollapse: function () {
					document.getElementById(\'additional_options\').value = \'0\';
				},
				funcOnBeforeExpand: function () {
					document.getElementById(\'additional_options\').value = \'1\';
				},
				aSwappableContainers: [
					\'postAdditionalOptions\',
				],
				aSwapClasses: [
					{
						sId: \'postMoreExpand\',
						classExpanded: \'chevricon i-chevron-up\',
						titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
						classCollapsed: \'chevricon i-chevron-down\',
						titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
					}
				],
				aSwapLinks: [
					{
						sId: \'postMoreExpandLink\',
						msgExpanded: ' . JavaScriptEscape(!empty($context['attachments']) && $context['attachments']['can']['post'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt']) . ',
						msgCollapsed: ' . JavaScriptEscape(!empty($context['attachments']) && $context['attachments']['can']['post'] ? $txt['post_additionalopt_attach'] : $txt['post_additionalopt']) . '
					}
				],
				oThemeOptions: {
					bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
					sOptionName: \'minmax_preferences\',
					sSessionId: elk_session_id,
					sSessionVar: elk_session_var,
					sAdditionalVars: \';minmax_key=post\'
				},
			});', true);

	template_topic_replies_below();
}

/**
 * The template for the spellchecker.
 */
function template_spellcheck()
{
	global $context, $settings, $txt;

	// The style information that makes the spellchecker look... like the forum hopefully!
	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<title>', $txt['spell_check'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index.css', CACHE_STALE, '" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'index', $context['theme_variant'], '.css', CACHE_STALE, '" />
		<style>
			body, td {
				font-size: small;
				margin: 0;
				background: #f0f0f0;
				color: #000;
				padding: 10px 10px 0 10px;
			}
			.highlight {
				color: red;
				font-weight: bold;
			}
			#spellview {
				border: 1px inset black;
				padding: 5px;
				height: 300px;
				overflow: auto;
				background: #ffffff;
			}
			select {
				height: auto;
				max-height: none;
			}
		</style>';

	// As you may expect - we need a lot of javascript for this... load it from the separate files.
	echo '
		<script>
			var spell_formname = window.opener.spell_formname,
				spell_fieldname = window.opener.spell_fieldname,
				spell_full = window.opener.spell_full;
		</script>
		<script src="', $settings['default_theme_url'], '/scripts/spellcheck.js"></script>
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script>
			', $context['spell_js'], '
		</script>
	</head>
	<body onload="nextWord(false);">
		<form action="#" method="post" accept-charset="UTF-8" name="spellingForm" id="spellingForm" onsubmit="return false;" style="margin: 0;">
			<div id="spellview">&nbsp;</div>
			<table class="table_grid">
				<tr>
					<td style="width: 50%;vertical-align: top;">
						<label for="changeto">', $txt['spellcheck_change_to'], '</label><br />
						<input type="text" id="changeto" name="changeto" style="width: 98%;" class="input_text" />
					</td>
					<td style="width: 50%;">
						', $txt['spellcheck_suggest'], '<br />
							<select name="suggestions" style="width: 98%;" size="5" onclick="if (this.selectedIndex != -1) this.form.changeto.value = this.options[this.selectedIndex].text;" ondblclick="replaceWord();">
							</select>
					</td>
				</tr>
			</table>
			<div class="submitbutton">
				<input type="button" name="change" value="', $txt['spellcheck_change'], '" onclick="replaceWord();" />
				<input type="button" name="changeall" value="', $txt['spellcheck_change_all'], '" onclick="replaceAll();" />
				<input type="button" name="ignore" value="', $txt['spellcheck_ignore'], '" onclick="nextWord(false);" />
				<input type="button" name="ignoreall" value="', $txt['spellcheck_ignore_all'], '" onclick="nextWord(true);" />
			</div>
		</form>
	</body>
</html>';
}
