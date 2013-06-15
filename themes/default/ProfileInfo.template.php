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
 * This template displays users details without any option to edit them.
 */
function template_action_summary()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// Display the basic information about the user
	echo '
<div id="profileview" class="flow_auto">
	<div id="basicinfo">
		<div class="windowbg">
			<div class="content flow_auto">
				<div class="username"><h4>', $context['member']['name'], ' <span class="position">', (!empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group']), '</span></h4></div>
				', $context['member']['avatar']['image'], '
				<ul>';
	// @TODO fix the <ul> when no fields are visible
	// What about if we allow email only via the forum??
	if ($context['member']['show_email'] === 'yes' || $context['member']['show_email'] === 'no_through_forum' || $context['member']['show_email'] === 'yes_permission_override' && $context['can_send_email'])
		echo '
					<li><a href="', $scripturl, '?action=emailuser;sa=email;uid=', $context['member']['id'], '" title="', $context['member']['show_email'] == 'yes' || $context['member']['show_email'] == 'yes_permission_override' ? $context['member']['email'] : '', '" rel="nofollow"><img src="', $settings['images_url'], '/profile/email_sm.png" alt="', $txt['email'], '" class="centericon" /></a></li>';

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
		echo '
					<li><a href="', $context['member']['website']['url'], '" title="' . $context['member']['website']['title'] . '" target="_blank" class="new_win">', ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/profile/www_sm.png" alt="' . $context['member']['website']['title'] . '" class="centericon" />' : $txt['www']), '</a></li>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
			if (($field['placement'] == 1 || empty($field['output_html'])) && !empty($field['value']))
				echo '
					<li class="cf_icon">', $field['output_html'], '</li>';
	}

	echo '
			</ul>
			<span id="userstatus">', $context['can_send_pm'] ? '<a href="' . $context['member']['online']['href'] . '" title="' . $context['member']['online']['text'] . '" rel="nofollow">' : '', $settings['use_image_buttons'] ? '<img src="' . $context['member']['online']['image_href'] . '" alt="' . $context['member']['online']['text'] . '" class="centericon" />' : $context['member']['online']['label'], $context['can_send_pm'] ? '</a>' : '', $settings['use_image_buttons'] ? '<span class="smalltext"> ' . $context['member']['online']['label'] . '</span>' : '';

	// Can they add this member as a buddy?
	if (!empty($context['can_have_buddy']) && !$context['user']['is_owner'])
		echo '
				<br /><a href="', $scripturl, '?action=buddy;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], '">[', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], ']</a>';

	echo '
				</span>';

	echo '
				<p id="infolinks">';

	if (!$context['user']['is_owner'] && $context['can_send_pm'])
		echo '
					<a href="', $scripturl, '?action=pm;sa=send;u=', $context['id_member'], '">', $txt['profile_sendpm_short'], '</a><br />';

	echo '
					<a href="', $scripturl, '?action=profile;area=showposts;u=', $context['id_member'], '">', $txt['showPosts'], '</a><br />';

	if ($context['user']['is_owner'] && !empty($modSettings['drafts_enabled']))
		echo '
					<a href="', $scripturl, '?action=profile;area=showdrafts;u=', $context['id_member'], '">', $txt['drafts_show'], '</a><br />';

	echo '
					<a href="', $scripturl, '?action=profile;area=statistics;u=', $context['id_member'], '">', $txt['statPanel'], '</a>
				</p>';

	echo '
			</div>
		</div>
	</div>
	<div id="detailedinfo">
		<div class="windowbg2">
			<div class="content">
				<dl>';

	if ($context['user']['is_owner'] || $context['user']['is_admin'])
		echo '
					<dt>', $txt['username'], ': </dt>
					<dd>', $context['member']['username'], '</dd>';

	if (!isset($context['disabled_fields']['posts']))
		echo '
					<dt>', $txt['profile_posts'], ': </dt>
					<dd>', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</dd>';

	if ($context['can_send_email'])
	{
		// Only show the email address fully if it's not hidden - and we reveal the email.
		if ($context['member']['show_email'] == 'yes')
			echo '
						<dt>', $txt['email'], ': </dt>
						<dd><a href="', $scripturl, '?action=emailuser;sa=email;uid=', $context['member']['id'], '">', $context['member']['email'], '</a></dd>';

		// ... Or if the one looking at the profile is an admin they can see it anyway.
		elseif ($context['member']['show_email'] == 'yes_permission_override')
			echo '
						<dt>', $txt['email'], ': </dt>
						<dd><em><a href="', $scripturl, '?action=emailuser;sa=email;uid=', $context['member']['id'], '">', $context['member']['email'], '</a></em></dd>';
	}

	if (!empty($modSettings['titlesEnable']) && !empty($context['member']['title']))
		echo '
					<dt>', $txt['custom_title'], ': </dt>
					<dd>', $context['member']['title'], '</dd>';

	if (!empty($context['member']['blurb']))
		echo '
					<dt>', $txt['personal_text'], ': </dt>
					<dd>', $context['member']['blurb'], '</dd>';

	// If karma enabled show the members karma.
	if ($modSettings['karmaMode'] == '1')
		echo '
					<dt>', $modSettings['karmaLabel'], ' </dt>
					<dd>', ($context['member']['karma']['good'] - $context['member']['karma']['bad']), '</dd>';

	elseif ($modSettings['karmaMode'] == '2')
		echo '
					<dt>', $modSettings['karmaLabel'], ' </dt>
					<dd>+', $context['member']['karma']['good'], '/-', $context['member']['karma']['bad'], '</dd>';

	if (!isset($context['disabled_fields']['gender']) && !empty($context['member']['gender']['name']))
		echo '
					<dt>', $txt['gender'], ': </dt>
					<dd>', $context['member']['gender']['name'], '</dd>';

	echo '
					<dt>', $txt['age'], ':</dt>
					<dd>', $context['member']['age'] . ($context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . $settings['images_url'] . '/cake.png" alt="" />' : ''), '</dd>';

	if (!isset($context['disabled_fields']['location']) && !empty($context['member']['location']))
		echo '
					<dt>', $txt['location'], ':</dt>
					<dd>', $context['member']['location'], '</dd>';

	echo '
				</dl>';

	// Any custom fields for standard placement?
	if (!empty($context['custom_fields']))
	{
		$shown = false;
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 0 || empty($field['output_html']))
				continue;

			if (empty($shown))
			{
				echo '
				<dl>';
				$shown = true;
			}

			echo '
					<dt>', $field['name'], ':</dt>
					<dd>', $field['output_html'], '</dd>';
		}

		if (!empty($shown))
			echo '
				</dl>';
	}

	echo '
				<dl class="noborder">';

	// Can they view/issue a warning?
	if ($context['can_view_warning'] && $context['member']['warning'])
	{
		echo '
					<dt>', $txt['profile_warning_level'], ': </dt>
					<dd>
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=', $context['can_issue_warning'] ? 'issuewarning' : 'viewwarning', '">', $context['member']['warning'], '%</a>';

		// Can we provide information on what this means?
		if (!empty($context['warning_status']))
			echo '
						<span class="smalltext">(', $context['warning_status'], ')</span>';

		echo '
					</dd>';
	}

	// Is this member requiring activation and/or banned?
	if (!empty($context['activate_message']) || !empty($context['member']['bans']))
	{

		// If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves.
		if (!empty($context['activate_message']))
			echo '
					<dt class="clear"><span class="alert">', $context['activate_message'], '</span>&nbsp;(<a href="' . $scripturl . '?action=profile;save;area=activateaccount;u=' . $context['id_member'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"', ($context['activate_type'] == 4 ? ' onclick="return confirm(\'' . $txt['profileConfirm'] . '\');"' : ''), '>', $context['activate_link_text'], '</a>)</dt>';

		// If the current member is banned, show a message and possibly a link to the ban.
		if (!empty($context['member']['bans']))
		{
			echo '
					<dt class="clear"><span class="alert">', $txt['user_is_banned'], '</span>&nbsp;[<a href="#" onclick="document.getElementById(\'ban_info\').style.display = document.getElementById(\'ban_info\').style.display == \'none\' ? \'\' : \'none\';return false;">' . $txt['view_ban'] . '</a>]</dt>
					<dt class="clear" id="ban_info" style="display: none;">
						<strong>', $txt['user_banned_by_following'], ':</strong>';

			foreach ($context['member']['bans'] as $ban)
				echo '
						<br /><span class="smalltext">', $ban['explanation'], '</span>';

			echo '
					</dt>';
		}
	}

	echo '
					<dt>', $txt['date_registered'], ': </dt>
					<dd>', $context['member']['registered'], '</dd>';

	// If the person looking is allowed, they can check the members IP address and hostname.
	if ($context['can_see_ip'])
	{
		if (!empty($context['member']['ip']))
		echo '
					<dt>', $txt['ip'], ': </dt>
					<dd><a href="', $scripturl, '?action=profile;area=history;sa=ip;searchip=', $context['member']['ip'], ';u=', $context['member']['id'], '">', $context['member']['ip'], '</a></dd>';

		if (empty($modSettings['disableHostnameLookup']) && !empty($context['member']['ip']))
			echo '
					<dt>', $txt['hostname'], ': </dt>
					<dd>', $context['member']['hostname'], '</dd>';
	}

	echo '
					<dt>', $txt['local_time'], ':</dt>
					<dd>', $context['member']['local_time'], '</dd>';

	if (!empty($modSettings['userLanguage']) && !empty($context['member']['language']))
		echo '
					<dt>', $txt['language'], ':</dt>
					<dd>', $context['member']['language'], '</dd>';

	echo '
					<dt>', $txt['lastLoggedIn'], ': </dt>
					<dd>', $context['member']['last_login'], '</dd>
				</dl>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		$shown = false;
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 2 || empty($field['output_html']))
				continue;
			if (empty($shown))
			{
				$shown = true;
				echo '
				<div class="custom_fields_above_signature">
					<ul class="nolist">';
			}
			echo '
						<li>', $field['output_html'], '</li>';
		}
		if ($shown)
				echo '
					</ul>
				</div>';
	}

	// Show the users signature.
	if ($context['signature_enabled'] && !empty($context['member']['signature']))
		echo '
				<div class="signature">
					<h5>', $txt['signature'], ':</h5>
					', $context['member']['signature'], '
				</div>';

	echo '
			</div>
		</div>
	</div>
<div class="clear"></div>
</div>';
}

/**
 * Template for showing all the posts of the user, in chronological order.
 */
function template_action_showPosts()
{
	global $context, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', (!isset($context['attachments']) && empty($context['is_topics']) ? $txt['showMessages'] : (!empty($context['is_topics']) ? $txt['showTopics'] : $txt['showAttachments'])), ' - ', $context['member']['name'], '
			</h3>
		</div>';
		template_pagesection(false, false, 'go_down');

	// Are we displaying posts or attachments?
	if (!isset($context['attachments']))
	{
		// For every post to be displayed, give it its own div, and show the important details of the post.
		foreach ($context['posts'] as $post)
		{
			echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg2' : 'windowbg', ' core_posts">
				<div class="content">
					<div class="counter">', $post['counter'], '</div>
					<div class="topic_details">
						<h5><strong><a href="', $scripturl, '?board=', $post['board']['id'], '.0">', $post['board']['name'], '</a> / <a href="', $scripturl, '?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['subject'], '</a></strong></h5>
						<span class="smalltext">', $post['time'], '</span>
					</div>
					<div class="list_posts">';

			if (!$post['approved'])
				echo '
						<div class="approve_post">
							<em>', $txt['post_awaiting_approval'], '</em>
						</div>';

			echo '
					', $post['body'], '
					</div>';

			if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
				echo '
				<div class="floatright">
					<ul class="smalltext quickbuttons">';

			// If they *can* reply?
			if ($post['can_reply'])
				echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '" class="reply_button"><span>', $txt['reply'], '</span></a></li>';

			// If they *can* quote?
			if ($post['can_quote'])
				echo '
						<li><a href="', $scripturl . '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="quote_button"><span>', $txt['quote'], '</span></a></li>';

			// Can we request notification of topics?
			if ($post['can_mark_notify'])
				echo '
						<li><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '" class="notify_button"><span>', $txt['notify'], '</span></a></li>';

			// How about... even... remove it entirely?!
			if ($post['can_delete'])
				echo '
						<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';profile;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');" class="remove_button"><span>', $txt['remove'], '</span></a></li>';

			if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
				echo '
					</ul>
				</div>';

			echo '
				</div>
			</div>';
		}
	}
	else
		template_show_list('attachments');

	// No posts? Just end the table with a informative message.
	if ((isset($context['attachments']) && empty($context['attachments'])) || (!isset($context['attachments']) && empty($context['posts'])))
		echo '
				<div class="windowbg2">
					<div class="content">
						', isset($context['attachments']) ? $txt['show_attachments_none'] : ($context['is_topics'] ? $txt['show_topics_none'] : $txt['show_posts_none']), '
					</div>
				</div>';

	// Show more page numbers.
	template_pagesection();
}

function template_action_showPermissions()
{
	global $context, $settings, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />', $txt['showPermissions'], '
			</h3>
		</div>';

	if ($context['member']['has_all_permissions'])
	{
		echo '
		<p class="description">', $txt['showPermissions_all'], '</p>';
	}
	else
	{
		echo '
		<p class="description">',$txt['showPermissions_help'],'</p>
		<div id="permissions" class="flow_hidden">';

		if (!empty($context['no_access_boards']))
		{
			echo '
				<div class="cat_bar">
					<h3 class="catbg">', $txt['showPermissions_restricted_boards'], '</h3>
				</div>
				<div class="windowbg smalltext">
					<div class="content">', $txt['showPermissions_restricted_boards_desc'], ':<br />';

				foreach ($context['no_access_boards'] as $no_access_board)
					echo '
						<a href="', $scripturl, '?board=', $no_access_board['id'], '.0">', $no_access_board['name'], '</a>', $no_access_board['is_last'] ? '' : ', ';

				echo '
					</div>
				</div>';
		}

		// General Permissions section.
		echo '
				<div class="tborder">
					<div class="cat_bar">
						<h3 class="catbg">', $txt['showPermissions_general'], '</h3>
					</div>';

		if (!empty($context['member']['permissions']['general']))
		{
			echo '
					<table class="table_grid">
						<thead>
							<tr class="titlebg">
								<th class="lefttext first_th" scope="col" style="width:50%">', $txt['showPermissions_permission'], '</th>
								<th class="lefttext last_th" scope="col" style="width:50%">', $txt['showPermissions_status'], '</th>
							</tr>
						</thead>
						<tbody>';

			foreach ($context['member']['permissions']['general'] as $permission)
			{
				echo '
							<tr>
								<td class="windowbg" title="', $permission['id'], '">
									', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
								</td>
								<td class="windowbg2 smalltext">';

				if ($permission['is_denied'])
					echo '
									<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']),'</span>';
				else
					echo '
									', $txt['showPermissions_given'], ':&nbsp;', implode(', ', $permission['groups']['allowed']);

					echo '
								</td>
							</tr>';
			}

			echo '
						</tbody>
					</table>
				</div><br />';
		}
		else
			echo '
			<p class="windowbg2 description">', $txt['showPermissions_none_general'], '</p>';

		// Board permission section.
		echo '
			<div class="tborder">
				<form action="' . $scripturl . '?action=profile;u=', $context['id_member'], ';area=permissions#board_permissions" method="post" accept-charset="UTF-8">
					<div class="cat_bar">
						<h3 class="catbg">
							<a id="board_permissions"></a>', $txt['showPermissions_select'], ':
							<select name="board" onchange="if (this.options[this.selectedIndex].value) this.form.submit();">
								<option value="0"', $context['board'] == 0 ? ' selected="selected"' : '', '>', $txt['showPermissions_global'], '&nbsp;</option>';

		if (!empty($context['boards']))
			echo '
								<option value="" disabled="disabled">---------------------------</option>';

		// Fill the box with any local permission boards.
		foreach ($context['boards'] as $board)
			echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['name'], ' (', $board['profile_name'], ')</option>';

		echo '
							</select>
						</h3>
					</div>
				</form>';

		if (!empty($context['member']['permissions']['board']))
		{
			echo '
				<table class="table_grid">
					<thead>
						<tr class="titlebg">
							<th class="lefttext first_th" scope="col" style="width:50%">', $txt['showPermissions_permission'], '</th>
							<th class="lefttext last_th" scope="col" style="width:50%">', $txt['showPermissions_status'], '</th>
						</tr>
					</thead>
					<tbody>';

			foreach ($context['member']['permissions']['board'] as $permission)
			{
				echo '
						<tr>
							<td class="windowbg" title="', $permission['id'], '">
								', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
							</td>
							<td class="windowbg2 smalltext">';

				if ($permission['is_denied'])
				{
					echo '
								<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']), '</span>';
				}
				else
				{
					echo '
								', $txt['showPermissions_given'], ': &nbsp;', implode(', ', $permission['groups']['allowed']);
				}

				echo '
							</td>
						</tr>';
			}

			echo '
					</tbody>
				</table>';
		}
		else
			echo '
			<p class="windowbg2 description">', $txt['showPermissions_none_board'], '</p>';

	echo '
			</div>
		</div>';
	}
}

/**
 * Template for user statistics, showing graphs and the like.
 */
function template_action_statPanel()
{
	global $context, $settings, $txt;

	// First, show a few text statistics such as post/topic count.
	echo '
	<div id="profileview">
		<div id="generalstats">
			<div class="windowbg2">
				<div class="content">
					<dl>
						<dt>', $txt['statPanel_total_time_online'], ':</dt>
						<dd>', $context['time_logged_in'], '</dd>
						<dt>', $txt['statPanel_total_posts'], ':</dt>
						<dd>', $context['num_posts'], ' ', $txt['statPanel_posts'], '</dd>
						<dt>', $txt['statPanel_total_topics'], ':</dt>
						<dd>', $context['num_topics'], ' ', $txt['statPanel_topics'], '</dd>
						<dt>', $txt['statPanel_users_polls'], ':</dt>
						<dd>', $context['num_polls'], ' ', $txt['statPanel_polls'], '</dd>
						<dt>', $txt['statPanel_users_votes'], ':</dt>
						<dd>', $context['num_votes'], ' ', $txt['statPanel_votes'], '</dd>
					</dl>
				</div>
			</div>
		</div>';

	// This next section draws a graph showing what times of day they post the most.
	echo '
		<div id="activitytime" class="flow_hidden">
			<div class="cat_bar">
				<h3 class="catbg">
				<img src="', $settings['images_url'], '/stats_history.png" alt="" class="icon" />', $txt['statPanel_activityTime'], '
				</h3>
			</div>
			<div class="windowbg2">
				<div class="content">';

	// If they haven't post at all, don't draw the graph.
	if (empty($context['posts_by_time']))
		echo '
					<span class="centertext">', $txt['statPanel_noPosts'], '</span>';
	// Otherwise do!
	else
	{
		echo '
					<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach ($context['posts_by_time'] as $time_of_day)
		{
			echo '
						<li', $time_of_day['is_last'] ? ' class="last"' : '', '>
							<div class="bar" style="padding-top: ', ((int) (100 - $time_of_day['relative_percent'])), 'px;" title="', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '">
								<div style="height: ', (int) $time_of_day['relative_percent'], 'px;">
									<span>', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '</span>
								</div>
							</div>
							<span class="stats_hour">', $time_of_day['hour_format'], '</span>
						</li>';
		}

		echo '

					</ul>';
	}

	echo '
					<span class="clear" />
				</div>
			</div>
		</div>';

	// Two columns with the most popular boards by posts and activity (activity = users posts / total posts).
	echo '
		<div class="flow_hidden">
			<div id="popularposts">
				<div class="cat_bar">
					<h3 class="catbg">
						<img src="', $settings['images_url'], '/stats_replies.png" alt="" class="icon" />', $txt['statPanel_topBoards'], '
					</h3>
				</div>
				<div class="windowbg2">
					<div class="content">';

	if (empty($context['popular_boards']))
		echo '
						<span class="centertext">', $txt['statPanel_noPosts'], '</span>';

	else
	{
		echo '
						<dl>';

		// Draw a bar for every board.
		foreach ($context['popular_boards'] as $board)
		{
			echo '
							<dt>', $board['link'], '</dt>
							<dd>
								<div class="profile_pie" style="background-position: -', ((int) ($board['posts_percent'] / 5) * 20), 'px 0;" title="', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '">
									', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '
								</div>
								<span>', empty($context['hide_num_posts']) ? $board['posts'] : '', '</span>
							</dd>';
		}

		echo '
						</dl>';
	}
	echo '
					</div>
				</div>
			</div>';
	echo '
			<div id="popularactivity">
				<div class="cat_bar">
					<h3 class="catbg">
						<img src="', $settings['images_url'], '/stats_replies.png" alt="" class="icon" />', $txt['statPanel_topBoardsActivity'], '
					</h3>
				</div>
				<div class="windowbg2">
					<div class="content">';

	if (empty($context['board_activity']))
		echo '
						<span>', $txt['statPanel_noPosts'], '</span>';
	else
	{
		echo '
						<dl>';

		// Draw a bar for every board.
		foreach ($context['board_activity'] as $activity)
		{
			echo '
							<dt>', $activity['link'], '</dt>
							<dd>
								<div class="profile_pie" style="background-position: -', ((int) ($activity['percent'] / 5) * 20), 'px 0;" title="', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '">
									', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '
								</div>
								<span>', $activity['percent'], '%</span>
							</dd>';
		}

		echo '
						</dl>';
	}
	echo '
					</div>
				</div>
			</div>
		</div>';

	echo '
	</div>';
}

/**
 * Show all warnings of a user
 */
function template_viewWarning()
{
	global $context, $txt, $settings;

	template_load_warning_variables();

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<img src="', $settings['images_url'], '/icons/profile_hd.png" alt="" class="icon" />
				', sprintf($txt['profile_viewwarning_for_user'], $context['member']['name']), '
			</h3>
		</div>
		<p class="description">', $txt['viewWarning_help'], '</p>
		<div class="windowbg">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['profile_warning_name'], ':</strong>
					</dt>
					<dd>
						', $context['member']['name'], '
					</dd>
					<dt>
						<strong>', $txt['profile_warning_level'], ':</strong>
					</dt>
					<dd>
						<div>
							<div>
								<div style="font-size: 8pt; height: 12pt; width: ', $context['warningBarWidth'], 'px; border: 1px solid black; background: white; padding: 1px; position: relative;">
									<div id="warning_text" class="centertext">', $context['member']['warning'], '%</div>
									<div id="warning_progress" style="width: ', $context['member']['warning'], '%; height: 12pt; z-index: 1; background: ', $context['current_color'], ';">&nbsp;</div>
								</div>
							</div>
						</div>
					</dd>';

		// There's some impact of this?
		if (!empty($context['level_effects'][$context['current_level']]))
			echo '
					<dt>
						<strong>', $txt['profile_viewwarning_impact'], ':</strong>
					</dt>
					<dd>
						', $context['level_effects'][$context['current_level']], '
					</dd>';

		echo '
				</dl>
			</div>
		</div>';

	template_show_list('view_warnings');
}