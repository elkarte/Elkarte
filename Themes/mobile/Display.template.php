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

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Is this topic also a poll?
	if ($context['is_poll'])
	{
		echo '
			<ul data-role="listview" data-inset="true">
				<li data-role="list-divider">
					<h3>
						<span><img src="', $settings['images_url'], '/topic/', $context['poll']['is_locked'] ? 'normal_poll_locked' : 'normal_poll', '.png" alt="" class="icon" /> ', $txt['poll'], '</span>
					</h3>
				</li>
				<li>
					<h4 id="pollquestion">
						', $context['poll']['question'], '
					</h4>
				</li>
				<li>';

		// Are they not allowed to vote but allowed to view the options?
		if ($context['poll']['show_results'] || !$context['allow_vote'])
		{
			// Show each option with its corresponding percentage bar.
			foreach ($context['poll']['options'] as $option)
			{
				echo '
					<div>', $option['option'], '</div>';

				if ($context['allow_poll_view'])
					echo '
					', $option['bar_ndt'], '
					<span>', $option['votes'], ' (', $option['percent'], '%)</span><hr />';
			}

			if ($context['allow_poll_view'])
				echo '
					<p><strong>', $txt['poll_total_voters'], ':</strong> ', $context['poll']['total_votes'], '</p>';
		}
		// They are allowed to vote! Go to it!
		else
		{
			echo '
					<form action="', $scripturl, '?action=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="', $context['character_set'], '">';

			// Show a warning if they are allowed more than one option.
			if ($context['poll']['allowed_warning'])
				echo '
						<p>', $context['poll']['allowed_warning'], '</p>';

			// Show each option with its button - a radio likely.
			foreach ($context['poll']['options'] as $option)
				echo '
						', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label>';

			echo '
						<input data-theme="a" type="submit" value="', $txt['poll_vote'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />

					</form>';
		}

		// Is the clock ticking?
		if (!empty($context['poll']['expire_time']))
			echo '
					<div><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</div>';

		echo '
				</li>
			</ul>
			<div id="pollmoderation">';

		template_button_strip($context['poll_buttons']);

		echo '
			</div>';
	}

	// Does this topic have some events linked to it?
	if (!empty($context['linked_calendar_events']))
	{
		echo '
			<div class="linked_events">
				<h3 class="titlebg headerpadding">', $txt['calendar_linked_events'], '</h3>
				<ul class="reset">';

		foreach ($context['linked_calendar_events'] as $event)
			echo '
					<li>
						', ($event['can_edit'] ? '<a href="' . $event['modify_href'] . '"> <img src="' . $settings['images_url'] . '/icons/calendar_modify.png" alt="" title="' . $txt['modify'] . '" class="edit_event" /></a> ' : ''), '<strong>', $event['title'], '</strong>: ', $event['start_date'], ($event['start_date'] != $event['end_date'] ? ' - ' . $event['end_date'] : ''), '
					</li>';

		echo '
				</ul>
			</div>';
	}

	$ignoredMsgs = array();
	
	echo '
	<ul data-role="listview" data-inset="true">';
	
	// Get all the messages...
	while ($message = $context['get_message']())
	{
		$ignoring = false;

		// Are we ignoring this message?
		if (!empty($message['is_ignored']))
		{
			$ignoring = true;
			$ignoredMsgs[] = $message['id'];
		}

		// Show information about the poster of this message.
		echo '
		<li data-role="list-divider">';

		// Show the message anchor and a "new" anchor if this message is new.
		if ($message['id'] != $context['first_message'])
			echo '
			<span><a id="msg', $message['id'], '"></a>', $message['first_new'] ? '<a id="new"></a>' : '', '</span>';
				
		if (!$message['member']['is_guest'])
		{
			// Show avatars, images, etc.?
			if (!empty($settings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
				echo '
			<div class="avatar">
				<img src="', $message['member']['avatar']['href'], '" alt="" />
			</div>';
		}
		if (!empty($modSettings['onlineEnable']) && !$message['member']['is_guest'])
			echo '
			<img src="', $message['member']['online']['image_href'], '" alt="', $message['member']['online']['text'], '" />';
			
			echo '
			<h4>', $message['subject'], '</h4> 
			<p>', $txt['by'], ' <a class="postedby" href="', $scripturl, '?action=profile;u=', $message['member']['id'], '">', $message['member']['name'], '</a> -- ', $message['time'] , '</p>';

			if ($context['can_report_moderator'] || $context['can_quote'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'] || $context['can_reply'])
			{			
				// Custom drop down for reply, quote etc.
				echo '
				<span style="position: absolute; right: 10px; top: 10px">
				<select name="post_options" class="post_options" data-icon="gear" data-iconpos="notext" data-select-menu="true" data-native-menu="false">
					<option value="0">', $txt['postOptions'], '</option>';
				
				// Can they reply?
				if ($context['can_reply'])
					echo '
					<option value="1" data-drop-url="', $scripturl , '?action=post;topic=' , $context['current_topic'] , '.' , $context['start'] , ';num_replies=' , $context['num_replies'], '">', $txt['reply'], '</option>';

				// So... quick reply is off, but they *can* reply?
				elseif ($context['can_quote'])
					echo '
					<option value="2" data-drop-url="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '">', $txt['quote'], '</option>';

				// Can the user modify the contents of this post?
				if ($message['can_modify'])
					echo '
					<option value="3" data-drop-url="', $scripturl, '?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '">', $txt['modify'], '</option>';

				// How about... even... remove it entirely?!
				if ($message['can_remove'])
					echo '
					<option value="4" data-drop-url="', $scripturl, '?action=deletemsg;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['remove'], '</option>';

				// What about splitting it off the rest of the topic?
				if ($context['can_split'] && !empty($context['real_num_replies']))
					echo '
					<option value="5" data-drop-url="', $scripturl, '?action=splittopics;topic=', $context['current_topic'], '.0;at=', $message['id'], '">', $txt['split'], '</option>';

				// Maybe they want to report this post to the moderator(s)?
				if ($context['can_report_moderator'])
					echo '
					<option value="6" data-drop-url="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', $message['counter'], ';msg=', $message['id'], '">', $txt['report_to_mod'], '</option>';					
				
				echo '
				</select>
				</span>';
			}			
		
		// Done with the information about the poster... on to the post itself.		
		echo '
		</li>';
			
		// Ignoring this user? Hide the post.
		if ($ignoring)
			echo '
		<li><em>', $txt['ignoring_user'], '</em></li>';
		else
			echo '
		<li><div class="message_body">', $message['body'], '</div></li>';
	}

	echo '
	</ul>';
	echo '
	<script>
		$(document).on("pageinit", function () {
			$(".post_options").on("change", function () {
				var selected = $(this).find("option:selected").val();
				var url = $(this).find("option:selected").data("drop-url");
				if(selected == 4) {
					if(confirm("', $txt['remove_message'], '?") == true) {
						window.location.href = url;
					} 
					return false;
				} else {
					window.location.href = url;
					return false;
				}
			});
		});
	</script>';	
}

?>