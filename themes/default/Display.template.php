<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Util;

/**
 * Loads the template of the poster area
 */
function template_Display_init()
{
	theme()->getTemplates()->load('GenericMessages');
	if (!empty($context['likes']))
	{
		template_load_likes_button();
	}
}

/**
 * Show a status block above the report to staff page
 */
function template_report_sent_above()
{
	global $txt;

	// Let them know, if their report was a success!
	echo '
		<div class="successbox">
			', $txt['report_sent'], '
		</div>';
}

/**
 * Topic information, descriptions, etc.
 */
function template_messages_informations_above()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// Show the topic information - icon, subject, etc.
	echo '
		<main id="forumposts">
			<header class="category_header">
				<img src="', $settings['images_url'], '/topic/', $context['class'], '.png" alt="" />
				', $txt['topic'], ': ', $context['subject'], '&nbsp;<span class="views_text">(', $context['num_views_text'], ')</span>
				<span class="nextlinks">',
					!empty($context['links']['go_prev']) ? '<a href="' . $context['links']['go_prev'] . '">' . $txt['previous_next_back'] . '</a>' : '',
					!empty($context['links']['go_next']) ? ' - <a href="' . $context['links']['go_next'] . '">' . $txt['previous_next_forward'] . '</a>' : '',
					!empty($context['links']['derived_from']) ? ' - <a href="' . $context['links']['derived_from'] . '">' . sprintf($txt['topic_derived_from'], '<em>' . Util::shorten_text($context['topic_derived_from']['subject'], !empty($modSettings['subject_length']) ? $modSettings['subject_length'] : 32)) . '</em></a>' : '',
				'</span>
			</header>
			<section>';

	if (!empty($settings['display_who_viewing']) || !empty($context['topic_redirected_from']))
	{
		echo '
			<div class="generalinfo">';
		if (!empty($settings['display_who_viewing']))
		{
			echo '
				<span id="whoisviewing">';

			// Show just numbers...?
			if ($settings['display_who_viewing'] == 1)
			{
				echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
			}
			// Or show the actual people viewing the topic?
			else
			{
				echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
			}

			// Now show how many guests are here too.
			echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
				</span>';
		}

		// Is this topic a redirect?
		if (!empty($context['topic_redirected_from']))
		{
			echo '
				<span id="redirectfrom">
					' . sprintf($txt['no_redir'], '<a href="' . $context['topic_redirected_from']['redir_href'] . '">' . $context['topic_redirected_from']['subject'] . '</a>'), '
				</span>';
		}
		echo '
			</div>';
	}

	echo '
			<form id="quickModForm" action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\') : false">';
}

/**
 * The main template for displaying a topic, does it all, its the king, the bomb, the real deal
 */
function template_messages()
{
	global $context, $settings, $options, $txt, $scripturl;

	$context['quick_reply_removableMessageIDs'] = array();
	$context['quick_reply_ignoredMsgs'] = array();

	// Get all the messages...
	$reset = isset($context['reset_renderer']);
	$controller = $context['get_message'][0];
	while ($message = $controller->{$context['get_message'][1]}($reset))
	{
		$reset = false;

		if ($message['can_remove'])
		{
			$context['quick_reply_removableMessageIDs'][] = $message['id'];
		}

		// Are we ignoring this message?
		if (!empty($message['is_ignored']))
		{
			$ignoring = true;
			$context['quick_reply_ignoredMsgs'][] = $message['id'];
		}
		else
		{
			$ignoring = false;
		}

		// Show the message anchor and a "new" anchor if this message is new.
		if (($message['id'] != $context['first_message']) && $message['first_new'])
		{
			echo '
				<a id="new">&nbsp;</a>
				<hr class="new_post_separator" />';
		}

		echo '
				<article class="post_wrapper forumposts', $message['classes'], $message['approved'] ? '' : ' approvebg', '">', $message['id'] != $context['first_message'] ? '
					<a class="post_anchor" id="msg' . $message['id'] . '"></a>' : '';

		// Showing the sidebar poster area?
		if (empty($options['hide_poster_area']))
		{
			echo '
					<aside>
						<ul class="poster no_js">', template_build_poster_div($message, $ignoring), '</ul>
					</aside>';
		}

		echo '
					<div class="postarea', empty($options['hide_poster_area']) ? '' : '2', '">
						<header class="keyinfo">
						', (!empty($options['hide_poster_area']) ? '<ul class="poster poster2">' . template_build_poster_div($message, $ignoring) . '</ul>' : '');

		if (!empty($context['follow_ups'][$message['id']]))
		{
			echo '
							<ul class="quickbuttons follow_ups no_js">
								<li class="listlevel1 subsections" aria-haspopup="true">
									<a class="linklevel1">', $txt['follow_ups'], '</a>
									<ul class="menulevel2">';

			foreach ($context['follow_ups'][$message['id']] as $follow_up)
			{
				echo '
										<li class="listlevel2">
											<a class="linklevel2" href="', getUrl('topic', ['topic' => $follow_up['follow_up'], 'start' => '0', 'subject' => $follow_up['subject']]), '">', $follow_up['subject'], '</a>
										</li>';
			}

			echo '
									</ul>
								</li>
							</ul>';
		}

		echo '
							<h2 id="post_subject_', $message['id'], '" class="post_subject">', $message['subject'], '</h2>
							<span id="messageicon_', $message['id'], '" class="messageicon', ($message['icon_url'] !== $settings['images_url'] . '/post/xx.png') ? '"' : ' hide"', '>
								<img src="', $message['icon_url'] . '" alt=""', $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '', ' />
							</span>
							<h5 id="info_', $message['id'], '">
								<a href="', $message['href'], '" rel="nofollow">', !empty($message['counter']) ? sprintf($txt['reply_number'], $message['counter']) : '', '</a>', !empty($message['counter']) ? ' &ndash; ' : '', $message['html_time'], '
							</h5>
							<div id="msg_', $message['id'], '_quick_mod"', $ignoring ? ' class="hide"' : '', '></div>
						</header>';

		// Ignoring this user? Hide the post.
		if ($ignoring)
		{
			echo '
						<details id="msg_', $message['id'], '_ignored_prompt">
							', $txt['ignoring_user'], '
							<a href="#" id="msg_', $message['id'], '_ignored_link" class="hide">', $txt['show_ignore_user_post'], '</a>
						</details>';
		}

		// Awaiting moderation?
		if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == $context['user']['id'])
		{
			echo '
						<div class="approve_post">
							', $txt['post_awaiting_approval'], '
						</div>';
		}

		// Show the post itself, finally!
		echo '
						<section id="msg_', $message['id'], '" class="messageContent', $ignoring ? ' hide"' : '"', '>',
							$message['body'], '
						</section>
						<footer>';

		// Assuming there are attachments...
		if (!empty($message['attachment']))
		{
			template_display_attachments($message, $ignoring);
		}

		// Show the quickbuttons, for various operations on posts.
		echo '
					<nav>
						<ul id="buttons_', $message['id'], '" class="quickbuttons no_js">';

		// Show a checkbox for quick moderation?
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $message['can_remove'])
		{
			echo '
							<li class="listlevel1 inline_mod_check hide" id="in_topic_mod_check_', $message['id'], '"></li>';
		}

		// Show "Last Edit: Time by Person" if this post was edited.
		if ($settings['show_modify'])
		{
			echo '
							<li id="modified_', $message['id'], '" class="listlevel1 modified', !empty($message['modified']['name']) ? '"' : ' hide"', '>
								', !empty($message['modified']['name']) ? $message['modified']['last_edit_text'] : '', '
							</li>';
		}

		// Maybe they can modify the post (this is the more button)
		if ($message['can_modify'] || ($context['can_report_moderator']))
		{
			echo '
							<li class="listlevel1 subsections" aria-haspopup="true">
								<a href="#" ', !empty($options['use_click_menu']) ? '' : 'onclick="event.stopPropagation();return false;" ', 'class="linklevel1 post_options">', $txt['post_options'], '
							</a>';
		}

		if ($message['can_modify'] || $message['can_remove'] || !empty($context['can_follow_up']) || ($context['can_split'] && !empty($context['real_num_replies'])) || $context['can_restore_msg'] || $message['can_approve'] || $message['can_unapprove'] || $context['can_report_moderator'])
		{
			// Show them the other options they may have in a nice pulldown
			echo '
								<ul class="menulevel2">';

			// Can the user modify the contents of this post?
			if ($message['can_modify'])
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '" class="linklevel2 modify_button">', $txt['modify'], '</a>
									</li>';
			}

			// How about... even... remove it entirely?!
			if ($message['can_remove'])
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=deletemsg;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="linklevel2 remove_button">', $txt['remove'], '</a>
									</li>';
			}

			// Can they quote to a new topic? @todo - This needs rethinking for GUI layout.
			if (!empty($context['can_follow_up']))
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=post;board=', $context['current_board'], ';quote=', $message['id'], ';followup=', $message['id'], '" class="linklevel2 quotetonew_button">', $txt['quote_new'], '</a>
									</li>';
			}

			// What about splitting it off the rest of the topic?
			if ($context['can_split'] && !empty($context['real_num_replies']) && $context['topic_first_message'] !== $message['id'])
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=splittopics;topic=', $context['current_topic'], '.0;at=', $message['id'], '" class="linklevel2 split_button">', $txt['split_topic'], '</a>
									</li>';
			}

			// Can we restore topics?
			if ($context['can_restore_msg'])
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=restoretopic;msgs=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="linklevel2 restore_button">', $txt['restore_message'], '</a>
									</li>';
			}

			// Maybe we can approve it, maybe we should?
			if ($message['can_approve'])
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '"  class="linklevel2 approve_button">', $txt['approve'], '</a>
									</li>';
			}

			// Maybe we can unapprove it?
			if ($message['can_unapprove'])
			{
				echo '
									<li class="listlevel2">
										<a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '"  class="linklevel2 unapprove_button">', $txt['unapprove'], '</a>
									</li>';
			}

			// Maybe they want to report this post to the moderator(s)?
			if ($context['can_report_moderator'])
			{
				echo '
									<li class="listlevel2">
										<a href="' . $scripturl . '?action=reporttm;topic=' . $context['current_topic'] . '.' . $message['counter'] . ';msg=' . $message['id'] . '" class="linklevel2 warn_button">' . $txt['report_to_mod'] . '</a>
									</li>';
			}

			// Anything else added by mods for example?
			if (!empty($context['additional_drop_buttons']))
			{
				foreach ($context['additional_drop_buttons'] as $key => $button)
				{
					echo '
									<li class="listlevel2">
										<a href="' . $button['href'] . '" class="linklevel2 ', $key, '">' . $button['text'] . '</a>
									</li>';
				}
			}

			echo '
								</ul>
							</li>';
		}

		// Hide likes if its off
		if ($message['likes_enabled'])
		{
			// Can they like/unlike this post?
			if ($message['can_like'] || $message['can_unlike'])
			{
				echo '
							<li class="listlevel1', !empty($message['like_counter']) ? ' liked"' : '"', '>
								<a class="linklevel1 ', $message['can_unlike'] ? 'unreact_button' : 'react_button', '" href="javascript:void(0)"', !empty($message['like_counter']) ? ' title="' . $txt['liked_by'] . ' ' . implode(', ', $context['likes'][$message['id']]['member']) . '"' : '', ' onclick="likePosts.prototype.likeUnlikePosts(event,', $message['id'], ', ', $context['current_topic'], '); return false;">',
									!empty($message['like_counter']) ? '<span class="likes_indicator">' . $message['like_counter'] . '</span>&nbsp;' . $txt['likes'] : $txt['like_post'], '
								</a>
							</li>';
			}

			// Or just view the count
			else
			{
				echo '
							<li class="listlevel1', !empty($message['like_counter']) ? ' liked"' : '"', '>
								<a href="javascript:void(0)" role="button" title="', !empty($message['like_counter']) ? $txt['liked_by'] . ' ' . implode(', ', $context['likes'][$message['id']]['member']) : '', '" class="linklevel1 reacts_button">',
									!empty($message['like_counter']) ? '<span class="likes_indicator">' . $message['like_counter'] . '</span>&nbsp;' . $txt['likes'] : '&nbsp;', '
								</a>
							</li>';
			}
		}

		// Can the user quick modify the contents of this post?  Show the quick (inline) modify button.
		if ($message['can_modify'])
		{
			echo '
							<li id="modify_button_', $message['id'], '" class="listlevel1 quick_edit hide">
								<a class="linklevel1 quick_edit" role="button" onclick="oQuickModify.modifyMsg(\'', $message['id'], '\')">', $txt['quick_edit'], '</a>
							</li>';
		}

		// Can they reply? Have they turned on quick reply?
		if ($context['can_quote'] && !empty($options['display_quick_reply']))
		{
			echo '
							<li class="listlevel1">
								<a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" onclick="return oQuickReply.quote(', $message['id'], ');" class="linklevel1 quote_button">', $txt['quote'], '</a>
							</li>';
		}
		// So... quick reply is off, but they *can* reply?
		elseif ($context['can_quote'])
		{
			echo '
							<li class="listlevel1">
								<a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" class="linklevel1 quote_button">', $txt['quote'], '</a>
							</li>';
		}

		// Anything else added by mods for example?
		if (!empty($context['additional_quick_buttons']))
		{
			foreach ($context['additional_quick_buttons'] as $key => $button)
			{
				echo '
								<li class="listlevel1">
									<a href="' . $button['href'] . '" class="linklevel1 ', $key, '">' . $button['text'] . '</a>
								</li>';
			}
		}

		echo '
						</ul>
					</nav>';

		// Are there any custom profile fields for above the signature?
		// Show them if signatures are enabled and you want to see them.
		if (!empty($message['member']['custom_fields']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 2 || empty($custom['value']))
				{
					continue;
				}

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
			{
				echo '
								</ul>
							</div>';
			}
		}

		// Show the member's signature?
		if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
		{
			echo '
							<div id="msg_', $message['id'], '_signature" class="signature', $ignoring ? ' hide"' : '"', '>', $message['member']['signature'], '</div>';
		}

		echo '
						</footer>
					</div>
				</article>
				<hr class="post_separator" />';
	}
}

/**
 * Closes the topic information, descriptions, etc. divs and forms
 */
function template_messages_informations_below()
{
	echo '
			</form>
			</section>
		</main>';
}

/**
 * This is quick reply area below all the message body's
 */
function template_quickreply_below()
{
	global $context, $options, $settings, $txt, $modSettings, $scripturl;

	// Using the quick reply box below the messages and you can reply?
	if ($context['can_reply'] && !empty($options['display_quick_reply']))
	{
		// Wrap the Quick Reply area, making it look like a post / message.
		echo '
	<h3 class="category_header">', $txt['quick_reply'], '</h3>
	<div id="quickreplybox">
		<a id="quickreply"></a>
		<section>
			<article class="post_wrapper forumposts">';

		if (empty($options['hide_poster_area']))
		{
			echo '
				<ul class="poster no_js">', template_build_poster_div($context['thisMember'], false), '</ul>';
		}

		// Make a postarea similar to post
		echo '
				<div class="postarea', empty($options['hide_poster_area']) ? '' : '2', '">
					<header class="category_header">
						<h5>',  $txt['reply'], '</h5>
					</header>
					<div id="quickReplyOptions" class="', empty($context['minmax_preferences']['qreply']) ? '"' : ' hide"', '>
						<form action="', getUrl('action', ['action' => 'post2', 'board' => $context['current_board']]), '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this);', (!empty($modSettings['mentions_enabled']) ? 'revalidateMentions(\'postmodify\', \'' . (empty($options['use_editor_quick_reply']) ? 'message' : $context['post_box_name']) . '\');' : ''), '">
							<input type="hidden" name="topic" value="', $context['current_topic'], '" />
							<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '" />
							<input type="hidden" name="icon" value="xx" />
							<input type="hidden" name="from_qr" value="1" />
							<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '" />
							<input type="hidden" name="not_approved" value="', (int) !$context['can_reply_approved'], '" />
							<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '" />
							<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />';

		// Guests just need more.
		if ($context['user']['is_guest'])
		{
			echo '
							<dl>
								<dt>
									<label for="guestname">', $txt['name'], ':</label> <input type="text" name="guestname" id="guestname" value="', $context['name'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" />
								</dd>
								<dt>
									<label for="email">', $txt['email'], ':</label> <input type="text" name="email" id="email" value="', $context['email'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" />
								</dd>
							</dl>';
		}

		// Is visual verification enabled?
		if (!empty($context['require_verification']))
		{
			template_verification_controls($context['visual_verification_id'], '
							<strong>' . $txt['verification'] . ':</strong>', '<br />');
		}

		// Using the full editor or a plain text box?
		if (empty($options['use_editor_quick_reply']))
		{
			echo '
							<div class="quickReplyContent">
								<textarea cols="600" rows="7" class="quickreply" name="message" id="message" tabindex="', $context['tabindex']++, '"></textarea>
							</div>';
		}
		else
		{
			echo '
							', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message');
		}

		echo '
							', $context['is_locked'] ? '<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '',
							$context['oldTopicError'] ? '<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']) . '</p>' : '', '
							', $context['can_reply_approved'] ? '' : '<em>' . $txt['wait_for_approval'] . '</em>', '
							', !$context['can_reply_approved'] && $context['require_verification'] ? '<br />' : '';

		echo '
							<div id="post_confirm_buttons" class="submitbutton">
								<input type="submit" name="post" value="', $txt['post'], '" onclick="return submitThisOnce(this);" accesskey="s" tabindex="', $context['tabindex']++, '" />
								<input type="submit" name="preview" value="', $txt['preview'], '" onclick="return submitThisOnce(this);" accesskey="p" tabindex="', $context['tabindex']++, '" />';

		// Draft save button?
		if (!empty($context['drafts_save']))
		{
			echo '
								<input type="button" name="save_draft" value="', $txt['draft_save'], '" onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d" tabindex="', $context['tabindex']++, '" />
								<input type="hidden" id="id_draft" name="id_draft" value="', empty($context['id_draft']) ? 0 : $context['id_draft'], '" />';
		}

		echo '
							</div>';

		// Show the draft last saved on area
		if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
		{
			echo '
							<div class="draftautosave">
								<span id="throbber" class="hide"><i class="icon i-oval"></i>&nbsp;</span>
								<span id="draft_lastautosave"></span>
							</div>';
		}

		echo '
						</form>
					</div>
				</div>
			</article>
		</section>
	</div>';

		// Using the plain text box we need to load in some additional javascript
		if (empty($options['use_editor_quick_reply']))
		{
			echo '
			<script>';

			// Mentions enabled
			if (!empty($modSettings['mentions_enabled']))
			{
				echo '
				add_elk_mention(\'#message\');';
			}

			echo '
			</script>';
		}
	}

	// Finally, enable the quick reply quote function
	echo '
		<script>
			let oQuickReply = new QuickReply({
				bDefaultCollapsed: ', empty($context['minmax_preferences']['qreply']) ? 'false' : 'true', ',
				iTopicId: ', $context['current_topic'], ',
				iStart: ', $context['start'], ',
				sScriptUrl: elk_scripturl,
				sImagesUrl: elk_images_url,
				sContainerId: "quickReplyOptions",
				sClassId: "quickReplyExpand",
				sClassCollapsed: "chevricon i-chevron-up",
				sTitleCollapsed: ', JavaScriptEscape($txt['show']), ',
				sClassExpanded: "chevricon i-chevron-down",
				sTitleExpanded: ', JavaScriptEscape($txt['hide']), ',
				sJumpAnchor: "quickreply",
				bIsFull: ', !empty($options['use_editor_quick_reply']) ? 'true,
				sEditorId: ' . $options['use_editor_quick_reply'] : 'false', ',
				oThemeOptions: {
					bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
					sOptionName: \'minmax_preferences\',
					sSessionId: elk_session_id,
					sSessionVar: elk_session_var,
					sAdditionalVars: \';minmax_key=qreply\'
				},
				oCookieOptions: {
					bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
					sCookieName: \'elk_qreply\'
				}
			});
		</script>';

	// Quick moderation options
	echo '
			<script>';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
	{
		echo '
				let oInTopicModeration = new InTopicModeration({
					sCheckboxContainerMask: \'in_topic_mod_check_\',
					aMessageIds: [\'', implode('\', \'', $context['quick_reply_removableMessageIDs']), '\'],
					sSessionId: elk_session_id,
					sSessionVar: elk_session_var,
					sButtonStrip: \'moderationbuttons\',
					sButtonStripDisplay: \'moderationbuttons_strip\',
					sButtonStripClass: \'menuitem\',
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
	}

	// Quick modify can be used
	echo '
				var oQuickModify = new QuickModify({
					sIconHide: \'xx.png\',
					sScriptUrl: elk_scripturl,
					sClassName: \'quick_edit\',
					sIDSubject: \'post_subject_\',
					sIDInfo: \'info_\',
					bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
					iTopicId: ', $context['current_topic'], ',
					sTemplateBodyEdit: ', JavaScriptEscape('
						<div id="quick_edit_body_container">
							<div id="error_box" class="errorbox hide"></div>
							<textarea class="editor" name="message" rows="12" tabindex="' . ($context['tabindex']++) . '">%body%</textarea><br />
							<div class="submitbutton">
								<input type="hidden" name="\' + elk_session_var + \'" value="\' + elk_session_id + \'" />
								<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
								<input type="hidden" name="msg" value="%msg_id%" />
								<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . ($context['tabindex']++) . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" />
								<input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . ($context['tabindex']++) . '" onclick="return oQuickModify.modifyCancel();" />
							</div>
						</div>'), ',
					sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
					sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" style="width: 85%;" name="subject" value="%subject%" size="80" maxlength="80" tabindex="' . ($context['tabindex']++) . '" class="input_text" />'), ',
					sTemplateSubjectNormal: ', JavaScriptEscape('%subject%'), ',
					sTemplateTopSubject: ', JavaScriptEscape($txt['topic'] . ': %subject% &nbsp;(' . $context['num_views_text'] . ')'), ',
					sTemplateInfoNormal: ', JavaScriptEscape('<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.msg%msg_id%#msg%msg_id%" rel="nofollow">%subject%</a><span class="smalltext modified" id="modified_%msg_id%"></span>'), ($context['can_reply'] && !empty($options['display_quick_reply'])) ? ',
					sFormRemoveAccessKeys: \'postmodify\'' : '', ',
					funcOnAfterCreate: function () {
						// Attach AtWho to the quick edit box
						add_elk_mention(\'#quick_edit_body_container textarea\');
						var i = all_elk_mentions.length - 1;
						all_elk_mentions[i].oMention = new elk_mentions(all_elk_mentions[i].oOptions);
					}
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
				});';

	if (!empty($context['quick_reply_ignoredMsgs']))
	{
		echo '
				ignore_toggles([', implode(', ', $context['quick_reply_ignoredMsgs']), '], ', JavaScriptEscape($txt['show_ignore_user_post']), ');';
	}

	echo '
			</script>';
}

/**
 * Used to display a polls / poll results
 */
function template_display_poll_above()
{
	global $context, $txt;

	echo '
			<div id="poll">
				<h2 class="category_header">
					<i class="icon i-poll', $context['poll']['is_locked'] ? '-locked' : '', '"></i> ', $txt['poll'], '
				</h2>
				<div id="poll_options" class="content">
					<h4 id="pollquestion">
						', $context['poll']['question'], '
					</h4>';

	// Are they not allowed to vote but allowed to view the options?
	if ($context['poll']['show_results'] || !$context['allow_vote'])
	{
		echo '
					<dl class="stats floatleft">';

		// Show each option with its corresponding percentage bar.
		foreach ($context['poll']['options'] as $option)
		{
			echo '
						<dt', $option['voted_this'] ? ' class="voted"' : '', '>', $option['option'], '</dt>
						<dd class="statsbar">';

			if ($context['allow_poll_view'])
			{
				echo '
							', $option['bar_ndt'], '
							<span class="righttext poll-percent">[ ', $option['votes'], ' ] (', $option['percent'], '%)</span>';
			}

			echo '
						</dd>';
		}

		echo '
					</dl>';

		if ($context['allow_poll_view'])
		{
			echo '
					<p>
						<strong>', $txt['poll_total_voters'], ':</strong> ', $context['poll']['total_votes'], '
					</p>';
		}
	}
	// They are allowed to vote! Go to it!
	else
	{
		echo '
					<form action="', getUrl('action', ['action' => 'poll', 'sa' => 'vote', 'topic' => $context['current_topic'] . '.' . $context['start'], 'poll' => $context['poll']['id']]), '" method="post" accept-charset="UTF-8">';

		// Show a warning if they are allowed more than one option.
		if ($context['poll']['allowed_warning'])
		{
			echo '
						<p>', $context['poll']['allowed_warning'], '</p>';
		}

		echo '
						<ul class="options">';

		// Show each option with its button - a radio likely.
		foreach ($context['poll']['options'] as $option)
		{
			echo '
							<li>', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';
		}

		echo '
						</ul>
						<div class="submitbutton">
							<input type="submit" value="', $txt['poll_vote'], '" class="left_submit" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						</div>
					</form>';
	}

	// Is the clock ticking?
	if (!empty($context['poll']['expire_time']))
	{
		echo '
					<p>
						<strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '
					</p>';
	}

	echo '
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
	global $context, $txt;

	echo '
			<section class="linked_events">
				<h2 class="category_header">', $txt['calendar_linked_events'], '</h2>
				<div class="content">
					<ul>';

	foreach ($context['linked_calendar_events'] as $event)
	{
		echo '
						<li>
							', ($event['can_edit'] ? '<a href="' . $event['modify_href'] . '"><i class="icon i-modify" title="' . $txt['modify'] . '"></i></a> ' : ''), '<strong>', $event['title'], '</strong>: ', $event['start_date'], ($event['start_date'] != $event['end_date'] ? ' - ' . $event['end_date'] : ''), '
						</li>';
	}

	echo '
					</ul>
				</div>
			</section>';
}

/**
 * Used to display items above the page, like page navigation
 */
function template_pages_and_buttons_above()
{
	global $context;

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '
			<a id="msg', $context['first_message'], '"></a>', $context['first_new_message'] ? '<a id="new"></a>' : '';

	// Show the page index... "Pages: [1]".
	template_pagesection('normal_buttons');
}

/**
 * Used to display items below the page, like page navigation
 */
function template_pages_and_buttons_below()
{
	global $context, $txt;

	// Show the page index... "Pages: [1]".
	template_pagesection('normal_buttons');

	// Show the lower breadcrumbs.
	theme_linktree();

	echo '
			<div id="moderationbuttons" class="hide_30 hamburger_30_target">';

	if (can_see_button_strip($context['mod_buttons']))
	{
		echo '
				<i class="icon icon-lg i-menu hamburger_30" data-id="moderationbuttons"></i>';
	}

	template_button_strip($context['mod_buttons'], '', array('id' => 'moderationbuttons_strip'));

	// Show the jump-to box, or actually...let Javascript do it.
	echo '
				<div id="display_jump_to">&nbsp;</div>
				<script>
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "display_jump_to",
						sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "&#8195;",
						sBoardPrefix: "&#10148;",
						sCatClass: "jump_to_header",
						sCatPrefix: "",
						sGoButtonLabel: "', $txt['go'], '"
					});
				</script>
			</div>';
}

/**
 * Used to display attachments
 *
 * @param array $message
 * @param bool $ignoring
 */
function template_display_attachments($message, $ignoring)
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
							<div id="msg_', $message['id'], '_footer" class="attachments', $ignoring ? ' hide"' : '"', '>';

	$last_approved_state = 1;

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
			{
				echo '
										&nbsp;<a class="linkbutton" href="', $scripturl, '?action=attachapprove;sa=all;mid=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve_all'], '</a>';
			}

			echo '
									</legend>';
		}

		echo '
									<div class="attachment_block">';

		if ($attachment['is_image'])
		{
			if ($attachment['thumbnail']['has_thumb'])
			{
				echo '
											<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" ', $attachment['thumbnail']['lightbox'], '>
												<img class="attachment_image" src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" />
											</a>';
			}
			else
			{
				echo '
											<img class="attachment_image" src="', $attachment['href'], ';image" alt="" style="max-width:100%; max-height:' . $attachment['height'] . 'px;"/>';
			}
		}
		elseif (!empty($modSettings['attachmentShowImages']))
		{
			echo '							<img class="attachment_image" src="', $attachment['href'], ';thumb" alt="" style="max-width:' . $modSettings['attachmentThumbWidth'] . 'px; max-height:' . $modSettings['attachmentThumbHeight'] . 'px;" />';
		}

		echo '
											<a href="', $attachment['href'], '" class="attachment_name">
												<i class="icon icon-small i-paperclip"></i>&nbsp;' . $attachment['name'] . '
											</a>
											<span class="attachment_details">', $attachment['size'], ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . ' - ' . sprintf($txt['attach_viewed'], $attachment['downloads']) : ' ' . sprintf($txt['attach_downloaded'], $attachment['downloads'])) . '</span>';

		if (!$attachment['is_approved'] && $context['can_approve'])
		{
			echo '
											<a class="linkbutton" href="', $scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve'], '</a>&nbsp;|&nbsp;<a class="linkbutton" href="', $scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['delete'], '</a>';
		}

		echo '
										</div>';
	}

	// If we had unapproved attachments clean up.
	if ($last_approved_state == 0)
	{
		echo '
								</fieldset>';
	}

	echo '
							</div>';
}
