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

function template_main()
{
	global $context, $settings, $txt, $scripturl;

	/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
	foreach ($context['categories'] as $category)
	{
		// If there are no parent boards we can see, avoid showing an empty category (unless its collapsed).
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		// @todo - Invent nifty class name for board index header bars.
		// @todo - Note these are now h2, not the old h3.
		echo '
		<div class="forum_category" id="category_', $category['id'], '">
			<h2 class="category_header">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
				<a class="collapse" href="', $category['collapse_href'], '" title="' ,$category['is_collapsed'] ? $txt['show'] : $txt['hide'] ,'">', $category['collapse_image'], '</a>';

		// The "category link" is only a link for logged in members. Guests just get the name.
		echo '
				', $category['link'], '
			</h2>';

		// Assuming the category hasn't been collapsed...
		if (!$category['is_collapsed'])
		{

		echo '
			<ul class="category_boards" id="category_', $category['id'], '_boards">';
			/* Each board in each category's boards has:
			new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
			children (see below.), link_children (easier to use.), children_new (are they new?),
			topics (# of), posts (# of), link, href, and last_post. (see below.) */
			foreach ($category['boards'] as $board)
			{
				echo '
				<li class="board_row ', (!empty($board['children'])) ? 'parent_board':'', '" id="board_', $board['id'], '">
					<div class="board_info">
						<a class="icon_anchor" href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

				// If the board or children is new, show an indicator.
				if ($board['new'] || $board['children_new'])
					echo '
							<img class="board_icon" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'on', $board['new'] ? '' : '2', '.png" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], '" />';
				// Is it a redirection board?
				elseif ($board['is_redirect'])
					echo '
							<img class="board_icon" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'redirect.png" alt="*" title="*" />';
				// No new posts at all! The agony!!
				else
					echo '
							<img class="board_icon" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'off.png" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

				echo '
						</a>
						<h3 class="board_name">
							<a href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

				// Has it outstanding posts for approval? @todo - Might change presentation here.
				if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
					echo '
							<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				echo '
						</h3>
						<p class="board_description">', $board['description'] , '</p>';

				// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
				if (!empty($board['moderators']))
					echo '
						<p class="moderators">', count($board['moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

				// Show some basic information about the number of posts, etc.
					echo '
					</div>
					<div class="board_latest">
						<p class="board_stats">
							', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], '
							', $board['is_redirect'] ? '' : '<br /> '.comma_format($board['topics']) . ' ' . $txt['board_topics'], '
						</p>';

				// @todo - Last post message still needs some work. Probably split the language string into three chunks.
				// Example:
				// <chunk>Re: Nunc aliquam justo e...</chunk>  <chunk>by Whoever</chunk> <chunk>Last post: Today at 08:00:37 am</chunk> 
				// That should still allow sufficient scope for any language, if done sensibly.
				if (!empty($board['last_post']['id']))
					echo '
						<p class="board_lastpost">
							', $board['last_post']['last_post_message'], '
						</p>';
				echo '
					</div>
				</li>';

				// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
				if (!empty($board['children']))
				{
					// Sort the links into an array with new boards bold so it can be imploded.
					$children = array();
					/* Each child in each board's children has:
							id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
					foreach ($board['children'] as $child)
					{
						if (!$child['is_redirect'])
							$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
						else
							$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

						// Has it posts awaiting approval? @todo - Might change presentation here.
						if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
							$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['link'];
					}

				// New <li> for child boards (if any). Can be styled to look like part of previous <li>.
				// Use h4 tag here for better a11y. Use <ul> for list of child boards.
				// Having child board links in <li>'s will allow "tidy child boards" via easy CSS tweaks. ;)
				echo '

				<li class="childboard_row" id="board_', $board['id'], '_children">
					<ul class="childboards">
						<li>
							<h4>', $txt['parent_boards'], ':</h4>
						</li>
						<li>
							', implode('</li><li> - ', $children), '
						</li>
					</ul>
				</li>';

				}
			}

	echo '
			</ul>';
		}

	echo '
		</div>';
	}
}

function template_boardindex_outer_above()
{
	global $context, $settings, $txt;

	// Show some statistics if info centre stats is off.
	if (!$settings['show_stats_index'])
		echo '
		<div id="index_common_stats">
			', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics_made'], ': ', $context['common_stats']['total_topics'], '<br />
			', $settings['show_latest_member'] ? ' ' . sprintf($txt['welcome_newest_member'], ' <strong>' . $context['common_stats']['latest_member']['link'] . '</strong>') : '' , '
		</div>';

	// Show the news fader?  (assuming there are things to show...)
	if ($settings['show_newsfader'] && !empty($context['news_lines']))
		template_news_fader();
}

function template_boardindex_outer_below()
{
	global $context, $settings, $txt;

	// @todo - Just <div> for the parent, <p>'s for the icon stuffz, and the buttonlist <ul> for "Mark read".
	// Sort the floats in the CSS file, as other tricks will be needed as well (media queries, for instance).
	echo '
		<div id="posting_icons">';

	// Show the mark all as read button?
	if ($settings['show_mark_read'] && !empty($context['categories']))
	echo '
			', template_button_strip($context['mark_read_button'], 'right');

	if ($context['user']['is_logged'])
	echo '
			<p><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_some.png" alt="" /> ', $txt['new_posts'], '</p>';

	echo '
			<p><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_none.png" alt="" /> ', $txt['old_posts'], '</p>
			<p><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_redirect.png" alt="" /> ', $txt['redirect_board'], '</p>
		</div>';

	template_info_center();
}

function template_info_center()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// Here's where the "Info Center" starts...
	echo '
	<div id="info_center">
		<h2 class="category_header">
			<img class="icon" id="upshrink_ic" title="', $txt['hide'], '" style="display: none;" src="', $settings['images_url'], '/', empty($context['admin_preferences']['apn']) ? 'collapse' : 'expand', '.png" alt="*" />
			<a href="#" id="upshrink_link">', sprintf($txt['info_center_title'], $context['forum_name_html_safe']), '</a>
		</h2>
		<div id="upshrinkHeaderIC"', empty($context['minmax_preferences']['info']) ? '' : ' style="display: none;"', '>';

	// This is the "Recent Posts" bar.
	if (!empty($settings['number_recent_posts']) && (!empty($context['latest_posts']) || !empty($context['latest_post'])))
	{
		// Show the Recent Posts title, and attach webslices feed to this section
		// The format requires: hslice, entry-title and entry-content classes
		// @todo - Note that the old titlebg has been changed to an h3, with a different name.
		echo '
			<div class="hslice" id="recent_posts_content">
				<h3 class="ic_section_header">
					<a href="', $scripturl, '?action=recent"><img class="icon" src="', $settings['images_url'], '/post/xx.png" alt="" />', $txt['recent_posts'], '</a>
				</h3>
				<div class="entry-title" style="display: none;">', $context['forum_name_html_safe'], ' - ', $txt['recent_posts'], '</div>
				<div class="entry-content" style="display: none;">
					<a rel="feedurl" href="', $scripturl, '?action=.xml;type=webslice">', $txt['subscribe_webslice'], '</a>
				</div>';

		// Only show one post.
		if ($settings['number_recent_posts'] == 1)
		{
			// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
			echo '
				<p id="infocenter_onepost" class="inline">
					<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>&nbsp;&quot;', sprintf($txt['is_recent_updated'], '&quot;' . $context['latest_post']['link'], '&quot;'), ' (<time datetime="', htmlTime($context['latest_post']['timestamp']), '">', $context['latest_post']['time'], '</time>)
				</p>';
		}
		// Show lots of posts. @todo - Although data here is actually tabular, perhaps use faux table for greater responsiveness.
		elseif (!empty($context['latest_posts']))
		{
			echo '
				<table id="ic_recentposts">
					<tr>
						<th class="recentpost first_th">', $txt['message'], '</th>
						<th class="recentposter">', $txt['author'], '</th>
						<th class="recentboard">', $txt['board'], '</th>
						<th class="recenttime last_th">', $txt['date'], '</th>
					</tr>';

			/* Each post in latest_posts has:
					board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
					subject, short_subject (shortened with...), time, link, and href. */
			foreach ($context['latest_posts'] as $post)
				echo '
					<tr>
						<td class="recentpost"><strong>', $post['link'], '</strong></td>
						<td class="recentposter">', $post['poster']['link'], '</td>
						<td class="recentboard">', $post['board']['link'], '</td>
						<td class="recenttime"><time datetime="', htmlTime($post['time']), '">', $post['time'], '</time></td>
					</tr>';
			echo '
				</table>';
		}
		echo '
			</div>';
	}

	// Show information about events, birthdays, and holidays on the calendar.
	if ($context['show_calendar'])
	{
		echo '
			<h3 class="ic_section_header">
				<a href="', $scripturl, '?action=calendar' . '"><img class="icon" src="', $settings['images_url'], '/icons/calendar.png', '" alt="" />', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '</a>
			</h3>';

		// Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P.
		if (!empty($context['calendar_holidays']))
			echo '
			<p class="inline holiday">', $txt['calendar_prompt'], ' ', implode(', ', $context['calendar_holidays']), '</p>';

		// People's birthdays. Like mine. And yours, I guess. Kidding.
		if (!empty($context['calendar_birthdays']))
		{
			echo '
			<p class="inline">
				<span class="birthday">', $context['calendar_only_today'] ? $txt['birthdays'] : $txt['birthdays_upcoming'], '</span>';
			// Each member in calendar_birthdays has: id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?)
			foreach ($context['calendar_birthdays'] as $member)
				echo '
				<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong class="fix_rtl_names">' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';
			echo '
			</p>';
		}

		// Events like community get-togethers.
		if (!empty($context['calendar_events']))
		{
			echo '
			<p class="inline">
				<span class="event">', $context['calendar_only_today'] ? $txt['events'] : $txt['events_upcoming'], '</span> ';

			// Each event in calendar_events should have:
			//		title, href, is_last, can_edit (are they allowed?), modify_href, and is_today.
			foreach ($context['calendar_events'] as $event)
				echo '
					', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . $txt['calendar_edit'] . '"><img src="' . $settings['images_url'] . '/icons/calendar_modify.png" alt="*" class="centericon" /></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br />' : ', ';
			echo '
			</p>';
		}
	}

	// Show statistical style information...
	if ($settings['show_stats_index'])
	{
		echo '
			<h3 class="ic_section_header">
				<a href="', $scripturl, '?action=stats" title="', $txt['more_stats'], '"><img class="icon" src="', $settings['images_url'], '/icons/info.png" alt="" />', $txt['forum_stats'], '</a>
			</h3>
			<p class="inline">
				', $context['common_stats']['boardindex_total_posts'], '', !empty($settings['show_latest_member']) ? ' - '. $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong>' : '', '<br />
				', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong>  ( ' . $context['latest_post']['time'] . ' )<br />' : ''), '
				<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>
			</p>';
	}

	// "Users online" - in order of activity.
	echo '
			<h3 class="ic_section_header">
				', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<img class="icon" src="', $settings['images_url'], '/icons/online.png', '" alt="" />', $txt['online_users'], '', $context['show_who'] ? '</a>' : '', '
			</h3>
			<p class="inline">
				', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<strong>', $txt['online'], ': </strong>', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . ($context['num_spiders'] == 1 ? $txt['hidden'] : $txt['hidden_s']);

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '

				&nbsp;-&nbsp;', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong>&nbsp;-&nbsp;
				', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' (<time datetime="', htmlTime($modSettings['mostDate']), '">', relativeTime($modSettings['mostDate']), '</time>)<br />';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
				', sprintf($txt['users_active'], $modSettings['lastActive']), ': ', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
				<span class="membergroups">[' . implode(',&nbsp;', $context['membergroups']). ']</span>';
	}

	echo '
			</p>';

	// If they are logged in, but statistical information is off... show a personal message bar.
	// @todo - Methinks this next bit is prehistoric bloat. Kill it, if nobody objects.
	/*if ($context['user']['is_logged'] && !$settings['show_stats_index'])
	{
		echo '
			<h3 class="subsection_header">
				', $context['allow_pm'] ? '<a href="' . $scripturl . '?action=pm">' : '', '<img class="icon" src="', $settings['images_url'], '/message_sm.png" alt="" />', $txt['personal_message'], '', $context['allow_pm'] ? '</a>' : '', '
			</h3>
			<p class="inline">
					', empty($context['user']['messages']) ? $txt['you_have_no_msg'] : ($context['user']['messages'] == 1 ? sprintf($txt['you_have_one_msg'], $scripturl . '?action=pm') : sprintf($txt['you_have_many_msgs'], $scripturl . '?action=pm', $context['user']['messages'])), '
			</p>';
	}*/

	echo '
		</div>
	</div>';

	// Info center collapse object.
	echo '
	<script><!-- // --><![CDATA[
		var oInfoCenterToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($context['minmax_preferences']['info']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'upshrinkHeaderIC\'
			],
			aSwapImages: [
				{
					sId: \'upshrink_ic\',
					srcExpanded: elk_images_url + \'/collapse.png\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					srcCollapsed: elk_images_url + \'/expand.png\',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'upshrink_link\',
					msgExpanded: ', JavaScriptEscape(sprintf($txt['info_center_title'], $context['forum_name_html_safe'])), ',
					msgCollapsed: ', JavaScriptEscape(sprintf($txt['info_center_title'], $context['forum_name_html_safe'])), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
				sOptionName: \'minmax_preferences\',
				sSessionId: elk_session_id,
				sSessionVar: elk_session_var,
				sAdditionalVars: \';minmax_key=info\'
			},
			oCookieOptions: {
				bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
				sCookieName: \'upshrinkIC\'
			}
		});
	// ]]></script>';
}

/**
 * This is the newsfader
 */
function template_news_fader()
{
	global $settings, $options, $txt, $context;

	// An aside seems better here than a section.
	echo '
	<div id="newsfader" class="forum_category">
		<h2 class="category_header">
			<img id="newsupshrink" src="', $settings['images_url'], '/collapse.png" alt="*" title="', $txt['hide'], '" style="display: none;vertical-align: bottom" />
			', $txt['news'], '
		</h2>';

	// No, here we need a list because the fade uses the list to split the items.
	echo '
		<ul id="elkFadeScroller"', empty($options['collapse_news_fader']) ? '' : ' style="display: none;"', '>
			<li>
				', implode('</li><li>', $context['news_lines']), '
			</li>
		</ul>
	</div>
	<script src="', $settings['default_theme_url'], '/scripts/fader.js"></script>
	<script><!-- // --><![CDATA[

		// Create a news fader object.
		var oNewsFader = new elk_NewsFader({
			sFaderControlId: \'elkFadeScroller\',
			sItemTemplate: ', JavaScriptEscape('<strong>%1$s</strong>'), ',
			iFadeDelay: ', empty($settings['newsfader_time']) ? 5000 : $settings['newsfader_time'], '
		});

		// Create the news fader toggle.
		var elkNewsFadeToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', empty($options['collapse_news_fader']) ? 'false' : 'true', ',
			aSwappableContainers: [
				\'elkFadeScroller\'
			],
			aSwapImages: [
				{
					sId: \'newsupshrink\',
					srcExpanded: elk_images_url + \'/collapse.png\',
					altExpanded: ', JavaScriptEscape($txt['hide']), ',
					srcCollapsed: elk_images_url + \'/expand.png\',
					altCollapsed: ', JavaScriptEscape($txt['show']), '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
				sOptionName: \'collapse_news_fader\',
				sSessionVar: elk_session_var,
				sSessionId: elk_session_id
			},
			oCookieOptions: {
				bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
				sCookieName: \'newsupshrink\'
			}
		});
	// ]]></script>';
}
