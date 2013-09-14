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
 * Show a status block above the report to staff page
 */
function template_report_sent_above()
{
	global $txt;

	// Let them know, if their report was a success!
	echo '
		<div class="infobox">
			', $txt['report_sent'], '
		</div>';
}

/**
 * The main template for displaying a topic, does it all, its the king, the bomb, the real deal
 */
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Yeah, I know, though at the moment is the only way...
	global $removableMessageIDs, $ignoredMsgs;

	// Show the topic information - icon, subject, etc.
	echo '
		<div id="forumposts" class="forumposts">
			<h2 class="category_header">
				<img src="', $settings['images_url'], '/topic/', $context['class'], '.png" alt="" />
				', $txt['topic'], ': ', $context['subject'], '&nbsp;<span class="views_text">(', $context['num_views_text'], ')</span>
				<span class="nextlinks">',
					!empty($context['links']['go_prev']) ? '<a href="' . $context['links']['go_prev'] . '">' . $txt['previous_next_back'] . '</a>' : '',
					!empty($context['links']['go_next']) ? ' - <a href="' . $context['links']['go_next'] . '">' . $txt['previous_next_forward'] . '</a>' : '',
					!empty($context['links']['derived_from']) ? ' - <a href="' . $context['links']['derived_from'] . '">' . sprintf($txt['topic_derived_from'], '<em>' . shorten_text($context['topic_derived_from']['subject'], !empty($modSettings['subject_length']) ? $modSettings['subject_length'] : 24)) . '</em></a>' : '',
				'</span>
			</h2>';

	if (!empty($settings['display_who_viewing']))
	{
		echo '
			<p id="whoisviewing">';

		// Show just numbers...?
		if ($settings['display_who_viewing'] == 1)
			echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
		// Or show the actual people viewing the topic?
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

		// Now show how many guests are here too.
		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
			</p>';
	}
	// Is this topic a redirect?
	if (!empty($context['topic_redirected_from']))
		echo '
			<p id="redirectfrom">
				' . sprintf($txt['no_redir'], '<a href="' . $context['topic_redirected_from']['redir_href'] . '">' . $context['topic_redirected_from']['subject'] . '</a>'), '
			</p>';

	echo '
			<form action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0;" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\') : false">';

	$ignoredMsgs = array();
	$removableMessageIDs = array();

	// Get all the messages...
	$controller = $context['get_message'][0];
	while ($message = $controller->{$context['get_message'][1]}())
	{
		if ($message['can_remove'])
			$removableMessageIDs[] = $message['id'];

		// Are we ignoring this message?
		if (!empty($message['is_ignored']))
		{
			$ignoring = true;
			$ignoredMsgs[] = $message['id'];
		}
		else
			$ignoring = false;

		// Show the message anchor and a "new" anchor if this message is new.
		echo '
				<div class="', $message['approved'] ? ($message['alternate'] == 0 ? 'windowbg' : 'windowbg2') : 'approvebg', '">', $message['id'] != $context['first_message'] ? '
					<a id="msg' . $message['id'] . '"></a>' . ($message['first_new'] ? '<a id="new"></a>' : '') : '';

		// Showing the sidebar posting area?
		if (empty($options['hide_poster_area']))
			echo '
					<ul class="poster">', template_build_poster_div($message, $ignoring), '</ul>';

		echo '
					<div class="postarea', empty($options['hide_poster_area']) ? '' : '2', '">
						<div class="keyinfo">
						', (!empty($options['hide_poster_area']) ? '<ul class="poster poster2">' . template_build_poster_div($message, $ignoring) . '</ul>' : '');

		if (!empty($context['follow_ups'][$message['id']]))
		{
			echo '
							<ul class="quickbuttons follow_ups">
								<li class="listlevel1 subsections" aria-haspopup="true"><a class="linklevel1">', $txt['follow_ups'], '</a>
									<ul class="menulevel2">';

			foreach ($context['follow_ups'][$message['id']] as $follow_up)
				echo '
										<li class="listlevel2"><a class="linklevel2" href="', $scripturl, '?topic=', $follow_up['follow_up'], '.0">', $follow_up['subject'], '</a></li>';

			echo '
									</ul>
								</li>
							</ul>';
		}

		echo '
							<span id="post_subject_', $message['id'], '" class="post_subject">', $message['subject'], '</span>
							<span id="messageicon_', $message['id'], '" class="messageicon"  ', ($message['icon_url'] !== $settings['images_url'] . '/post/xx.png') ? '' : 'style="display:none;"', '>
								<img src="', $message['icon_url'] . '" alt=""', $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '', ' />
							</span>
							<h5 id="info_', $message['id'], '">
								<a href="', $message['href'], '" rel="nofollow">', !empty($message['counter']) ? sprintf($txt['reply_number'], $message['counter']) : '', '</a>', !empty($message['counter']) ? ' &ndash; ' : '', $message['time'], '
							</h5>
							<div id="msg_', $message['id'], '_quick_mod"', $ignoring ? ' style="display:none;"' : '', '></div>
						</div>';

		// Ignoring this user? Hide the post.
		if ($ignoring)
			echo '
						<div id="msg_', $message['id'], '_ignored_prompt">
							', $txt['ignoring_user'], '
							<a href="#" id="msg_', $message['id'], '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
						</div>';

		// Awaiting moderation?
		if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == $context['user']['id'])
			echo '
						<div class="approve_post">
							', $txt['post_awaiting_approval'], '
						</div>';

		// Show the post itself, finally!
		echo '
						<div class="inner" id="msg_', $message['id'], '"', $ignoring ? ' style="display:none;"' : '', '>', $message['body'], '</div>';

		// Assuming there are attachments...
		if (!empty($message['attachment']))
			template_display_attachments($message, $ignoring);

		// Show the quickbuttons, for various operations on posts.
		echo '
						<ul class="quickbuttons">';

		// Show a checkbox for quick moderation?
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $message['can_remove'])
			echo '
							<li class="listlevel1 inline_mod_check" style="display: none;" id="in_topic_mod_check_', $message['id'], '"></li>';

		// Show "Last Edit: Time by Person" if this post was edited.
		if ($settings['show_modify'] && !empty($message['modified']['name']))
			echo '
							<li class="listlevel1 modified" id="modified_', $message['id'], '">
								', $message['modified']['last_edit_text'], '
							</li>';

		// Maybe they can modify the post (this is the more button)
		if ($message['can_modify'] || ($context['can_report_moderator']))
			echo '
							<li class="listlevel1 subsections" aria-haspopup="true"><a', ($message['can_modify'] && !empty($options['use_click_menu'])) ? ' href="' . $scripturl . '?action=post;msg=' . $message['id'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . '"' : '', ' class="linklevel1 post_options">', $txt['post_options'], '</a>';

		if ($message['can_modify'] || $message['can_remove'] || ($context['can_split'] && !empty($context['real_num_replies'])) || $context['can_restore_msg'] || $message['can_approve'] || $message['can_unapprove'] || $context['can_report_moderator'])
		{
			// Show them the other options they may have in a nice pulldown
			echo '
								<ul class="menulevel2">';

			// Can the user modify the contents of this post?
			if ($message['can_modify'])
				echo '
									<li class="listlevel2"><a href="', $scripturl, '?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '" class="linklevel2 modify_button">', $txt['modify'], '</a></li>';

			// How about... even... remove it entirely?!
			if ($message['can_remove'])
				echo '
									<li class="listlevel2"><a href="', $scripturl, '?action=deletemsg;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="linklevel2 remove_button">', $txt['remove'], '</a></li>';

			// What about splitting it off the rest of the topic?
			if ($context['can_split'] && !empty($context['real_num_replies']))
				echo '
									<li class="listlevel2"><a href="', $scripturl, '?action=splittopics;topic=', $context['current_topic'], '.0;at=', $message['id'], '" class="linklevel2 split_button">', $txt['split_topic'], '</a></li>';

			// Can we restore topics?
			if ($context['can_restore_msg'])
				echo '
									<li class="listlevel2"><a href="', $scripturl, '?action=restoretopic;msgs=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="linklevel2 restore_button">', $txt['restore_message'], '</a></li>';

			// Maybe we can approve it, maybe we should?
			if ($message['can_approve'])
				echo '
									<li class="listlevel2"><a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '"  class="linklevel2 approve_button">', $txt['approve'], '</a></li>';

			// Maybe we can unapprove it?
			if ($message['can_unapprove'])
				echo '
									<li class="listlevel2"><a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '"  class="linklevel2 unapprove_button">', $txt['unapprove'], '</a></li>';

			// Maybe they want to report this post to the moderator(s)?
			if ($context['can_report_moderator'])
				echo '
									<li class="listlevel2"><a href="' . $scripturl . '?action=reporttm;topic=' . $context['current_topic'] . '.' . $message['counter'] . ';msg=' . $message['id'] . '" class="linklevel2 warn_button">' . $txt['report_to_mod'] . '</a></li>';

			echo '
								</ul>';
		}

		// Hide likes if its off
		if ($message['likes_enabled'])
		{
			// Can they like this post?
			if ($message['can_like'])
				echo '
							<li class="listlevel1"><a href="', $scripturl, '?action=likes;sa=likepost;topic=', $context['current_topic'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', !empty($message['like_counter']) ? $txt['liked_by'] . ' ' . implode(', ', $context['likes'][$message['id']]['member']) : '', '" class="linklevel1 like_button">', !empty($message['like_counter']) ? '&nbsp;' . $message['like_counter'] . '&nbsp;' . $txt['likes'] : '&nbsp;', '</a></li>';
			// Or remove the like they made
			elseif ($message['can_unlike'])
				echo '
							<li class="listlevel1"><a href="', $scripturl, '?action=likes;sa=unlikepost;topic=', $context['current_topic'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', !empty($message['like_counter']) ? $txt['liked_by'] . ' ' . implode(', ', $context['likes'][$message['id']]['member']) : '', '" class="linklevel1 unlike_button">', !empty($message['like_counter']) ? '&nbsp;' . $message['like_counter'] . '&nbsp;' . $txt['likes'] : '&nbsp;', '</a></li>';
			// Or just view the count
			else
				echo '
							<li class="listlevel1"><a href="#" title="', !empty($message['like_counter']) ? $txt['liked_by'] . ' ' . implode(', ', $context['likes'][$message['id']]['member']) : '', '" class="linklevel1 likes_button">', !empty($message['like_counter']) ? '&nbsp;' . $message['like_counter'] . '&nbsp;' . $txt['likes'] : '&nbsp;', '</a></li>';
		}

		// Can the user quick modify the contents of this post?  Show the quick (inline) modify button.
		if ($message['can_modify'])
			echo '
							<li class="listlevel1 quick_edit"><a class="linklevel1"><img src="', $settings['images_url'], '/icons/modify_inline.png" alt="', $txt['modify_msg'], '" title="', $txt['modify_msg'], '" class="modifybutton" id="modify_button_', $message['id'], '" onclick="oQuickModify.modifyMsg(\'', $message['id'], '\')" />', $txt['quick_edit'], '</a></li>';

		// Can they quote to a new topic? @todo - This needs rethinking for GUI layout.
		if ($context['can_follow_up'])
			echo '
							<li class="listlevel1"><a href="', $scripturl, '?action=post;board=', $context['current_board'], ';quote=', $message['id'], ';followup=', $message['id'], '" class="linklevel1 quotetonew_button">', $txt['quote_new'], '</a></li>';

		// Can they reply? Have they turned on quick reply?
		if ($context['can_quote'] && !empty($options['display_quick_reply']))
			echo '
							<li class="listlevel1"><a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" onclick="return oQuickReply.quote(', $message['id'], ');" class="linklevel1 quote_button">', $txt['quote'], '</a></li>';
		// So... quick reply is off, but they *can* reply?
		elseif ($context['can_quote'])
			echo '
							<li class="listlevel1"><a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" class="linklevel1 quote_button">', $txt['quote'], '</a></li>';

		echo '
						</ul>';

		// Are there any custom profile fields for above the signature?
		// Show them if signatures are enabled and you want to see them.
		if (!empty($message['member']['custom_fields']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 2 || empty($custom['value']))
					continue;

				if (empty($shown))
				{
					$shown = true;
					echo '
						<div class="custom_fields_above_signature">
							<ul>';
				}

				echo '
								<li>', $custom['value'], '</li>';
			}

			if ($shown)
				echo '
							</ul>
						</div>';
		}

		// Show the member's signature?
		if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
			echo '
						<div class="signature" id="msg_', $message['id'], '_signature"', $ignoring ? ' style="display:none;"' : '', '>', $message['member']['signature'], '</div>';

		echo '
					</div>
				</div>
				<hr class="post_separator" />';
	}

	echo '
			</form>
		</div>';
}

/**
 * This is quick reply area below all the message body's
 */
function template_quickreply_below()
{
	global $context, $options, $settings, $txt, $modSettings, $scripturl;

	// Yeah, I know, though at the moment is the only way...
	global $removableMessageIDs, $ignoredMsgs;

	if ($context['can_reply'] && !empty($options['display_quick_reply']))
	{
		echo '
			<a id="quickreply"></a>
			<div class="forumposts" id="quickreplybox">
				<h2 class="category_header">
					<a href="javascript:oQuickReply.swap();"><img src="', $settings['images_url'], '/', $options['display_quick_reply'] > 1 ? 'collapse' : 'expand', '.png" alt="+" id="quickReplyExpand" class="icon" /></a>
					<a href="javascript:oQuickReply.swap();">', $txt['quick_reply'], '</a>
				</h2>
				<div id="quickReplyOptions" class="windowbg"', $options['display_quick_reply'] > 1 ? '' : ' style="display: none"', '>
					<div class="editor_wrapper">
						<p class="smalltext lefttext">', $txt['quick_reply_desc'], '</p>
						', $context['is_locked'] ? '<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '',
						$context['oldTopicError'] ? '<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']) . '</p>' : '', '
						', $context['can_reply_approved'] ? '' : '<em>' . $txt['wait_for_approval'] . '</em>', '
						', !$context['can_reply_approved'] && $context['require_verification'] ? '<br />' : '', '
						<form action="', $scripturl, '?board=', $context['current_board'], ';action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this);" >
							<input type="hidden" name="topic" value="', $context['current_topic'], '" />
							<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '" />
							<input type="hidden" name="icon" value="xx" />
							<input type="hidden" name="from_qr" value="1" />
							<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '" />
							<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '" />
							<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '" />
							<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />';

		// Guests just need more.
		if ($context['user']['is_guest'])
			echo '
							<strong>', $txt['name'], ':</strong> <input type="text" name="guestname" value="', $context['name'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" />
							<strong>', $txt['email'], ':</strong> <input type="text" name="email" value="', $context['email'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" /><br />';

		// Is visual verification enabled?
		if ($context['require_verification'])
			echo '
							<strong>', $txt['verification'], ':</strong>', template_control_verification($context['visual_verification_id'], 'quick_reply'), '<br />';

		// Using the full editor
		if (empty($options['use_editor_quick_reply']))
		{
			echo '
							<div class="quickReplyContent">
								<textarea cols="600" rows="7" name="message" tabindex="', $context['tabindex']++, '"></textarea>
							</div>';
		}
		else
		{
			// Show the actual posting area...
			if ($context['show_bbc'])
				echo '
							<div id="bbcBox_message"></div>';

			// What about smileys?
			if (!empty($context['smileys']['postform']) || !empty($context['smileys']['popup']))
				echo '
							<div id="smileyBox_message"></div>';

			echo '
							', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
							<script><!-- // --><![CDATA[
								var post_box_name = "', $context['post_box_name'], '";
							// ]]></script>';
		}

		echo '
							<div class="submitbutton">
								<input type="submit" name="post" value="', $txt['post'], '" onclick="return submitThisOnce(this);" accesskey="s" tabindex="', $context['tabindex']++, '" class="button_submit" />
								<input type="submit" name="preview" value="', $txt['preview'], '" onclick="return submitThisOnce(this);" accesskey="p" tabindex="', $context['tabindex']++, '" class="button_submit" />';

		if ($context['show_spellchecking'])
			echo '
								<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'postmodify\', \'message\', ', (empty($options['use_editor_quick_reply']) ? 'false' : 'true'), ')" tabindex="', $context['tabindex']++, '" class="button_submit" />';

		if ($context['drafts_save'] && !empty($options['display_quick_reply']))
		{
			echo '
								<input type="submit" name="save_draft" value="', $txt['draft_save'], '" onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d" tabindex="', $context['tabindex']++, '" class="button_submit" />
								<input type="hidden" id="id_draft" name="id_draft" value="', empty($context['id_draft']) ? 0 : $context['id_draft'], '" />';

			if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
				echo '
								<div class="clear"><span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon" />&nbsp;</span><span id="draft_lastautosave"></span></div>';
		}

		echo '
							</div>
						</form>
					</div>
				</div>
			</div>';
	}

	// tooltips for likes
	echo '
		<script><!-- // --><![CDATA[
			$(".likes").SiteTooltip({hoverIntent: {sensitivity: 10, interval: 150, timeout: 50}});
		// ]]></script>';

	// draft autosave available and the user has it enabled?
	if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']) && !empty($options['display_quick_reply']))
		echo '
			<script><!-- // --><![CDATA[
				var oDraftAutoSave = new elk_DraftAutoSave({
					sSelf: \'oDraftAutoSave\',
					sLastNote: \'draft_lastautosave\',
					sLastID: \'id_draft\',
					iBoard: ', (empty($context['current_board']) ? 0 : $context['current_board']), ',
					iFreq: ', isset($context['drafts_autosave_frequency']) ? $context['drafts_autosave_frequency'] : 30000, ',
				});
			// ]]></script>';

	// Spell check for quick modify and quick reply (w/o the editor)
	if ($context['show_spellchecking'] && (empty($options['use_editor_quick_reply']) || empty($options['display_quick_reply'])))
		echo '
				<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="', $scripturl, '?action=spellcheck">
					<input type="hidden" name="spellstring" value="" />
					<input type="hidden" name="fulleditor" value="" />
				</form>
				<script src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js"></script>';

	echo '
				<script><!-- // --><![CDATA[';

	if (!empty($options['display_quick_reply']))
		echo '
					var oQuickReply = new QuickReply({
						bDefaultCollapsed: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] > 1 ? 'false' : 'true', ',
						iTopicId: ', $context['current_topic'], ',
						iStart: ', $context['start'], ',
						sScriptUrl: elk_scripturl,
						sImagesUrl: elk_images_url,
						sContainerId: "quickReplyOptions",
						sImageId: "quickReplyExpand",
						sImageCollapsed: "collapse.png",
						sImageExpanded: "expand.png",
						sJumpAnchor: "quickreply",
						bIsFull: ', !empty($options['use_editor_quick_reply']) ? 'true' : 'false', '
					});';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
		echo '
					var oInTopicModeration = new InTopicModeration({
						sSelf: \'oInTopicModeration\',
						sCheckboxContainerMask: \'in_topic_mod_check_\',
						aMessageIds: [\'', implode('\', \'', $removableMessageIDs), '\'],
						sSessionId: elk_session_id,
						sSessionVar: elk_session_var,
						sButtonStrip: \'moderationbuttons\',
						sButtonStripDisplay: \'moderationbuttons_strip\',
						bUseImageButton: false,
						bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
						sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
						sRemoveButtonImage: \'delete_selected.png\',
						sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
						bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
						sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
						sRestoreButtonImage: \'restore_selected.png\',
						sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
						bCanSplit: ', $context['can_split'] ? 'true' : 'false', ',
						sSplitButtonLabel: \'', $txt['quickmod_split_selected'], '\',
						sSplitButtonImage: \'split_selected.png\',
						sSplitButtonConfirm: \'', $txt['quickmod_confirm'], '\',
						sFormId: \'quickModForm\'
					});';

	echo '
					if (\'XMLHttpRequest\' in window)
					{
						var oQuickModify = new QuickModify({
							sIconHide: \'xx.png\',
							sScriptUrl: elk_scripturl,
							sClassName: \'quick_edit\',
							sIDSubject: \'post_subject_\',
							sIDInfo: \'info_\',
							bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
							iTopicId: ', $context['current_topic'], ',
							sTemplateBodyEdit: ', JavaScriptEscape('
								<div id="quick_edit_body_container" style="width: 90%">
									<div id="error_box" class="errorbox" style="display:none;"></div>
									<textarea class="editor" name="message" rows="12" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 100%; min-width: 100%' : 'width: 100%') . '; margin-bottom: 10px;" tabindex="' . $context['tabindex']++ . '">%body%</textarea><br />
									<input type="hidden" name="\' + elk_session_var + \'" value="\' + elk_session_id + \'" />
									<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
									<input type="hidden" name="msg" value="%msg_id%" />
									<div class="righttext">
										<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" class="button_submit" />&nbsp;&nbsp;' . ($context['show_spellchecking'] ? '<input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="button_submit" />&nbsp;&nbsp;' : '') . '<input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="button_submit" />
									</div>
								</div>'), ',
							sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
							sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" style="width: 85%;" name="subject" value="%subject%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '" class="input_text" />'), ',
							sTemplateSubjectNormal: ', JavaScriptEscape('%subject%'), ',
							sTemplateTopSubject: ', JavaScriptEscape($txt['topic'] . ': %subject% &nbsp;(' . $context['num_views_text'] . ')'), ',
							sTemplateInfoNormal: ', JavaScriptEscape('<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.msg%msg_id%#msg%msg_id%" rel="nofollow">%subject%</a><span class="smalltext modified" id="modified_%msg_id%"></span>'), ',
							sErrorBorderStyle: ', JavaScriptEscape('1px solid red'), ($context['can_reply'] && !empty($options['display_quick_reply'])) ? ',
							sFormRemoveAccessKeys: \'postmodify\'' : '', '
						});

						aJumpTo[aJumpTo.length] = new JumpTo({
							sContainerId: "display_jump_to",
							sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
							iCurBoardId: ', $context['current_board'], ',
							iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
							sCurBoardName: "', $context['jump_to']['board_name'], '",
							sBoardChildLevelIndicator: "==",
							sBoardPrefix: "=> ",
							sCatSeparator: "-----------------------------",
							sCatPrefix: "",
							sGoButtonLabel: "', $txt['go'], '"
						});

						aIconLists[aIconLists.length] = new IconList({
							sBackReference: "aIconLists[" + aIconLists.length + "]",
							sIconIdPrefix: "msg_icon_",
							sScriptUrl: elk_scripturl,
							bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
							iBoardId: ', $context['current_board'], ',
							iTopicId: ', $context['current_topic'], ',
							sSessionId: elk_session_id,
							sSessionVar: elk_session_var,
							sAction: "messageicons;board=', $context['current_board'], '" ,
							sLabelIconList: "', $txt['message_icon'], '",
							sBoxBackground: "transparent",
							sBoxBackgroundHover: "#ffffff",
							iBoxBorderWidthHover: 1,
							sBoxBorderColorHover: "#adadad" ,
							sContainerBackground: "#ffffff",
							sContainerBorder: "1px solid #adadad",
							sItemBorder: "1px solid #ffffff",
							sItemBorderHover: "1px dotted gray",
							sItemBackground: "transparent",
							sItemBackgroundHover: "#e0e0f0"
						});
					}';

	if (!empty($ignoredMsgs))
		echo '
					ignore_toggles([', implode(', ', $ignoredMsgs), '], ', JavaScriptEscape($txt['show_ignore_user_post']), ');';

	echo '
				// ]]></script>';
}

/**
 * Builds the poster area, avatar, group icons, pulldown information menu, etc
 *
 * @param type $message
 * @param type $ignoring
 * @return string
 */
function template_build_poster_div($message, $ignoring)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	$poster_div = '';

	// Show information about the poster of this message.
	$poster_div .= '
							<li class="listlevel1 subsections" aria-haspopup="true">';

	// Show a link to the member's profile.
	$poster_div .= '
								<a class="linklevel1 name" href="' . $scripturl . '?action=profile;u=' . $message['member']['id'] . '">
									' . $message['member']['name'] . '
								</a>';

	// The new member info dropdown starts here. Note that conditionals have not been fully checked yet.
	$poster_div .= '
								<ul class="menulevel2" id="msg_' . $message['id'] . '_extra_info"' . ($ignoring ? ' style="display:none;"' : ' aria-haspopup="true"') . '>';

	// Don't show these things for guests.
	if (!$message['member']['is_guest'])
	{
		// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
		if ((empty($settings['hide_post_group']) || $message['member']['group'] == '') && $message['member']['post_group'] != '')
			$poster_div .= '
									<li class="listlevel2 postgroup">' . $message['member']['post_group'] . '</li>';

		// Show how many posts they have made.
		if (!isset($context['disabled_fields']['posts']))
			$poster_div .= '
									<li class="listlevel2 postcount">' . $txt['member_postcount'] . ': ' . $message['member']['posts'] . '</li>';

		// Is karma display enabled?  Total or +/-?
		if ($modSettings['karmaMode'] == '1')
			$poster_div .= '
									<li class="listlevel2 karma">' . $modSettings['karmaLabel'] . ' ' . $message['member']['karma']['good'] - $message['member']['karma']['bad'] . '</li>';
		elseif ($modSettings['karmaMode'] == '2')
			$poster_div .= '
									<li class="listlevel2 karma">' . $modSettings['karmaLabel'] . ' +' . $message['member']['karma']['good'] . '/-' . $message['member']['karma']['bad'] . '</li>';

		// Is this user allowed to modify this member's karma?
		if ($message['member']['karma']['allow'])
			$poster_div .= '
									<li class="listlevel2 karma_allow">
										<a class="linklevel2" href="' . $scripturl . '?action=karma;sa=applaud;uid=' . $message['member']['id'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';m=' . $message['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $modSettings['karmaApplaudLabel'] . '</a>
										<a class="linklevel2" href="' . $scripturl . '?action=karma;sa=smite;uid=' . $message['member']['id'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';m=' . $message['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $modSettings['karmaSmiteLabel'] . '</a>
									</li>';

		// Show the member's gender icon?
		if (!empty($settings['show_gender']) && $message['member']['gender']['image'] != '' && !isset($context['disabled_fields']['gender']))
			$poster_div .= '
									<li class="listlevel2 gender">' . $txt['gender'] . ': ' . $message['member']['gender']['image'] . '</li>';

		// Show their personal text?
		if (!empty($settings['show_blurb']) && $message['member']['blurb'] != '')
			$poster_div .= '
									<li class="listlevel2 blurb">' . $message['member']['blurb'] . '</li>';

		// Any custom fields to show as icons?
		if (!empty($message['member']['custom_fields']))
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 1 || empty($custom['value']))
					continue;

				if (empty($shown))
				{
					$shown = true;
					$poster_div .= '
									<li class="listlevel2 cf_icons">
										<ol>';
				}

				$poster_div .= '
											<li>' . $custom['value'] . '</li>';
			}

			if ($shown)
				$poster_div .= '
										</ol>
									</li>';
		}

		// Show the website and email address buttons.
		if ($message['member']['show_profile_buttons'])
		{
			$poster_div .= '
									<li class="listlevel2 profile">
										<ol>';

			// Don't show an icon if they haven't specified a website.
			if ($message['member']['website']['url'] != '' && !isset($context['disabled_fields']['website']))
				$poster_div .= '
											<li><a href="' . $message['member']['website']['url'] . '" title="' . $message['member']['website']['title'] . '" target="_blank" class="new_win">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/profile/www_sm.png" alt="' . $message['member']['website']['title'] . '" />' : $txt['www']) . '</a></li>';

			// Don't show the email address if they want it hidden.
			if (in_array($message['member']['show_email'], array('yes' . 'yes_permission_override' . 'no_through_forum')) && $context['can_send_email'])
				$poster_div .= '
											<li><a href="' . $scripturl . '?action=emailuser;sa=email;msg=' . $message['id'] . '" rel="nofollow">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . '" />' : $txt['email']) . '</a></li>';

			// Want to send them a PM, can you? @todo - This seems to be broken and IMO should be dropped anyway, at least if poster div is not hidden.
			if ($context['can_send_pm'] && !$message['is_message_author'] && !empty($modSettings['onlineEnable']))
				$poster_div .= '
											<li><a href="' . $scripturl . '?action=pm;sa=send;u=' . $message['member']['id'] . '" title="' . $message['member']['online']['member_online_text'] . '"><img src="' . $message['member']['online']['image_href'] . '" alt="" /></a></li>';

			$poster_div .= '
										</ol>
									</li>';
		}

		// Any custom fields for standard placement?
		if (!empty($message['member']['custom_fields']))
		{
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if (empty($custom['placement']) || empty($custom['value']))
					$poster_div .= '
									<li class="listlevel2 custom">' . $custom['title'] . ': ' . $custom['value'] . '</li>';
			}
		}
	}
	// Otherwise, show the guest's email.
	elseif (!empty($message['member']['email']) && in_array($message['member']['show_email'], array('yes' . 'yes_permission_override' . 'no_through_forum')) && $context['can_send_email'])
		$poster_div .= '
									<li class="listlevel2 email"><a class="linklevel2" href="' . $scripturl . '?action=emailuser;sa=email;msg=' . $message['id'] . '" rel="nofollow">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . '" />' : $txt['email']) . '</a></li>';

	// Stuff for the staff to wallop them with.
	$poster_div .= '
									<li class="listlevel2 report_seperator"></li>';

	// Can we issue a warning because of this post?  Remember, we can't give guests warnings.
	if ($context['can_issue_warning'] && !$message['is_message_author'] && !$message['member']['is_guest'])
	{
		$poster_div .= '
									<li class="listlevel2 warning">
										<a class="linklevel2" href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . ';msg=' . $message['id'] . '"><img src="' . $settings['images_url'] . '/profile/warn.png" alt="' . $txt['issue_warning_post'] . '" title="' . $txt['issue_warning_post'] . '" />' . $txt['warning_issue'] . '</a>';

		// Do they have a warning in place?
		if ($message['member']['can_see_warning'] && !empty($options['hide_poster_area']))
			$poster_div .= '
										<a class="linklevel2" href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '"><img src="' . $settings['images_url'] . '/profile/warning_' . $message['member']['warning_status'] . '.png" alt="' . $txt['user_warn_' . $message['member']['warning_status']] . '" /><span class="warn_' . $message['member']['warning_status'] . '">' . $txt['warn_' . $message['member']['warning_status']] . '</span></a>';

		$poster_div .= '
									</li>';
	}

	// Show the IP to this user for this post - because you can moderate?
	if (!empty($context['can_moderate_forum']) && !empty($message['member']['ip']))
		$poster_div .= '
									<li class="listlevel2 poster_ip"><a class="linklevel2 help" href="' . $scripturl . '?action=' . (!empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=history;sa=ip;u=' . $message['member']['id'] . ';searchip=' . $message['member']['ip']) . '"><img src="' . $settings['images_url'] . '/ip.png" alt="" /> ' . $message['member']['ip'] . '</a><a href="' . $scripturl . '?action=quickhelp;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);"><img src="' . $settings['images_url'] . '/helptopics.png" alt="(?)" /></a></li>';
	// Or, should we show it because this is you?
	elseif ($message['can_see_ip'])
		$poster_div .= '
									<li class="listlevel2 poster_ip"><a class="linklevel2 help" href="' . $scripturl . '?action=quickhelp;help=see_member_ip" onclick="return reqOverlayDiv(this.href);"><img src="' . $settings['images_url'] . '/ip.png" alt="" /> ' . $message['member']['ip'] . '</a></li>';
	// Okay, are you at least logged in?  Then we can show something about why IPs are logged...
	elseif (!$context['user']['is_guest'])
		$poster_div .= '
									<li class="listlevel2 poster_ip"><a class="linklevel2 help" href="' . $scripturl . '?action=quickhelp;help=see_member_ip" onclick="return reqOverlayDiv(this.href);">' . $txt['logged'] . '</a></li>';
	// Otherwise, you see NOTHING!
	else
		$poster_div .= '
									<li class="listlevel2 poster_ip">' . $txt['logged'] . '</li>';

	// Done with the detail information about the poster.
	$poster_div .= '
								</ul>
							</li>';

	// Show avatars, images, etc.?
	if (empty($options['hide_poster_area']))
	{
		if (!empty($settings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
			$poster_div .= '
							<li class="listlevel1 avatar">
								<a class="linklevel1" href="' . $scripturl . '?action=profile;u=' . $message['member']['id'] . '">
									' . $message['member']['avatar']['image'] . '
								</a>
							</li>';


		// Show the post group icons, but not for guests.
		if (!$message['member']['is_guest'])
			$poster_div .= '
							<li class="listlevel1 icons">' . $message['member']['group_icons'] . '</li>';

		// Show the member's primary group (like 'Administrator') if they have one.
		if (!empty($message['member']['group']))
			$poster_div .= '
							<li class="listlevel1 membergroup">' . $message['member']['group'] . '</li>';

		// Show the member's custom title, if they have one.
		if (!empty($message['member']['title']))
			$poster_div .= '
							<li class="listlevel1 title">' . $message['member']['title'] . '</li>';

		// Show online and offline buttons? PHP could do with a little bit of cleaning up here for brevity, but it works.
		// The plan is to make these buttons act sensibly, and link to your own inbox in your own posts (with new PM notification).
		// Still has a little bit of hard-coded text. This may be a place where translators should be able to write inclusive strings,
		// instead of dealing with $txt['by'] etc in the markup. Must be brief to work, anyway. Cannot ramble on at all.
		if ($context['can_send_pm'] && $message['is_message_author'])
		{
			$poster_div .= '
							<li class="listlevel1 poster_online"><a class="linklevel1' . ($context['user']['unread_messages'] > 0 ? ' new_pm' : '') . '" href="' . $scripturl . '?action=pm">' . $txt['pm_short'] . ' ' . ($context['user']['unread_messages'] > 0 ? '<span class="pm_indicator">' . $context['user']['unread_messages'] . '</span>' : '') . '</a></li>';
		}
		elseif ($context['can_send_pm'] && !$message['is_message_author'] && !$message['member']['is_guest'])
		{
			if (!empty($modSettings['onlineEnable']))
				$poster_div .= '
							<li class="listlevel1 poster_online"><a class="linklevel1" href="' . $scripturl . '?action=pm;sa=send;u=' . $message['member']['id'] . '" title="' . $message['member']['online']['member_online_text'] . '">' . $txt['send_message'] . ' <img src="' . $message['member']['online']['image_href'] . '" alt="" /></a></li>';
			else
				$poster_div .= '
							<li class="listlevel1 poster_online"><a class="linklevel1" href="' . $scripturl . '?action=pm;sa=send;u=' . $message['member']['id'] . '">' . $txt['send_message'] . ' </a></li>';
		}
		elseif (!$context['can_send_pm'] && !empty($modSettings['onlineEnable']))
			$poster_div .= '
							<li class="listlevel1 poster_online">' . ($message['member']['online']['is_online'] ? $txt['online'] : $txt['offline']) . ' <img src="' . $message['member']['online']['image_href'] . '" alt="" /></li>';

		// Are we showing the warning status?
		if (!$message['member']['is_guest'] && $message['member']['can_see_warning'])
			$poster_div .= '
							<li class="listlevel1 warning">' . ($context['can_issue_warning'] ? '<a class="linklevel1" href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '') . '<img src="' . $settings['images_url'] . '/profile/warning_' . $message['member']['warning_status'] . '.png" alt="' . $txt['user_warn_' . $message['member']['warning_status']] . '" />' . ($context['can_issue_warning'] ? '</a>' : '') . '<span class="warn_' . $message['member']['warning_status'] . '">' . $txt['warn_' . $message['member']['warning_status']] . '</span></li>';
	}

	return $poster_div;
}

/**
 * Used to display a polls / poll results
 */
function template_display_poll_above()
{
	global $settings, $context, $txt, $scripturl;
	echo '
			<div id="poll">
				<div class="cat_bar">
					<h3 class="catbg">
						<img src="', $settings['images_url'], '/topic/', $context['poll']['is_locked'] ? 'normal_poll_locked' : 'normal_poll', '.png" alt="" class="icon" /> ', $txt['poll'], '
					</h3>
				</div>
				<div class="windowbg">
					<div class="content" id="poll_options">
						<h4 id="pollquestion">
							', $context['poll']['question'], '
						</h4>';

	// Are they not allowed to vote but allowed to view the options?
	if ($context['poll']['show_results'] || !$context['allow_vote'])
	{
		echo '
					<dl class="options">';

		// Show each option with its corresponding percentage bar.
		foreach ($context['poll']['options'] as $option)
		{
			echo '
						<dt class="', $option['voted_this'] ? 'voted' : '', '">', $option['option'], '</dt>
						<dd class="statsbar', $option['voted_this'] ? ' voted' : '', '">';

			if ($context['allow_poll_view'])
				echo '
							', $option['bar_ndt'], '
							<span class="percentage">', $option['votes'], ' (', $option['percent'], '%)</span>';

			echo '
						</dd>';
		}

		echo '
					</dl>';

		if ($context['allow_poll_view'])
			echo '
						<p><strong>', $txt['poll_total_voters'], ':</strong> ', $context['poll']['total_votes'], '</p>';
	}
	// They are allowed to vote! Go to it!
	else
	{
		echo '
						<form action="', $scripturl, '?action=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="UTF-8">';

		// Show a warning if they are allowed more than one option.
		if ($context['poll']['allowed_warning'])
			echo '
							<p>', $context['poll']['allowed_warning'], '</p>';

		echo '
							<ul class="options">';

		// Show each option with its button - a radio likely.
		foreach ($context['poll']['options'] as $option)
			echo '
								<li>', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';

		echo '
							</ul>
							<div class="submitbutton">
								<input type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
								<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							</div>
						</form>';
	}

	// Is the clock ticking?
	if (!empty($context['poll']['expire_time']))
		echo '
						<p><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</p>';

	echo '
					</div>
				</div>
			</div>
			<div id="pollmoderation">';

	template_button_strip($context['poll_buttons']);

	echo '
			</div>';
}

/**
 * Used to display an attached calendar event.
 */
function template_display_calendar_above()
{
	global $context, $txt, $settings;

	echo '
			<div class="linked_events">
				<div class="title_bar">
					<h3 class="titlebg">', $txt['calendar_linked_events'], '</h3>
				</div>
				<div class="windowbg">
					<div class="content">
						<ul>';

	foreach ($context['linked_calendar_events'] as $event)
		echo '
							<li>
								', ($event['can_edit'] ? '<a href="' . $event['modify_href'] . '"> <img src="' . $settings['images_url'] . '/icons/calendar_modify.png" alt="" title="' . $txt['modify'] . '" class="edit_event" /></a> ' : ''), '<strong>', $event['title'], '</strong>: ', $event['start_date'], ($event['start_date'] != $event['end_date'] ? ' - ' . $event['end_date'] : ''), '
							</li>';

	echo '
						</ul>
					</div>
				</div>
			</div>';
}

/**
 * Used to display items above the page, like page navigation
 */
function template_pages_and_buttons_above()
{
	global $context;

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '
			<a id="msg', $context['first_message'], '"></a>', $context['first_new_message'] ? '<a name="new" id="new"></a>' : '';

	// Show the page index... "Pages: [1]".
	template_pagesection('normal_buttons', 'right');
}

/**
 * Used to display items below the page, like page navigation
 */
function template_pages_and_buttons_below()
{
	global $context;

	// Show the page index... "Pages: [1]".
	template_pagesection('normal_buttons', 'right');

	// Show the lower breadcrumbs.
	theme_linktree();

	echo '
			<div id="moderationbuttons">', template_button_strip($context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip')), '</div>';

	// Show the jump-to box, or actually...let Javascript do it.
	echo '
			<div id="display_jump_to">&nbsp;</div>';
}

/**
 * Used to display attachments
 *
 * @param array $message
 * @param bool $ignoring
 */
function template_display_attachments($message, $ignoring)
{
	global $context, $txt, $scripturl, $settings;

	echo '
							<div id="msg_', $message['id'], '_footer" class="attachments"', $ignoring ? ' style="display:none;"' : '', '>';

	$last_approved_state = 1;
	$attachments_per_line = 4;
	$i = 0;

	foreach ($message['attachment'] as $attachment)
	{
		// Show a special box for unapproved attachments...
		if ($attachment['is_approved'] != $last_approved_state)
		{
			$last_approved_state = 0;
			echo '
								<fieldset>
									<legend>', $txt['attach_awaiting_approve'];

			if ($context['can_approve'])
				echo '
										&nbsp;<a class="linkbutton" href="', $scripturl, '?action=attachapprove;sa=all;mid=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve_all'], '</a>';

			echo '
									</legend>';
		}

		echo '
									<div class="floatleft">';

		if ($attachment['is_image'])
		{
			echo '
										<div class="attachments_top">';

			if ($attachment['thumbnail']['has_thumb'])
				echo '
											<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" onclick="', $attachment['thumbnail']['javascript'], '"><img src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" /></a>';
			else
				echo '
											<img src="' . $attachment['href'] . ';image" alt="" style="width:' . $attachment['width'] . 'px; height:' . $attachment['height'] . 'px"/>';

			echo '
										</div>';
		}

		echo '
										<div class="attachments_bot">
											<a href="' . $attachment['href'] . '"><img src="' . $settings['images_url'] . '/icons/clip.png" class="centericon" alt="*" />&nbsp;' . $attachment['name'] . '</a> ';

		if (!$attachment['is_approved'] && $context['can_approve'])
			echo '
											<a class="linkbutton" href="', $scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve'], '</a>&nbsp;|&nbsp;<a class="linkbutton" href="', $scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['delete'], '</a>';
		echo '
											<br />', $attachment['size'], ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . '<br />' . sprintf($txt['attach_viewed'], $attachment['downloads']) : '<br />' . sprintf($txt['attach_downloaded'], $attachment['downloads'])), '
										</div>';

		echo '
									</div>';

		// Next attachment line ?
		if (++$i % $attachments_per_line === 0)
			echo '
									<hr />';
	}

	// If we had unapproved attachments clean up.
	if ($last_approved_state == 0)
		echo '
								</fieldset>';

	echo '
							</div>';
}
