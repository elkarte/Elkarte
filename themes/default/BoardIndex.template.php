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
 * @version 1.1
 *
 */

/**
 * Loads the template used to display boards
 */
function template_BoardIndex_init()
{
	theme()->getTemplates()->load('GenericBoards');
}

/**
 * Main template for displaying the list of boards
 */
function template_boards_list()
{
	global $context, $txt;

	// Each category in categories is made up of:
	// id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	// new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	// and boards. (see below.)
	foreach ($context['categories'] as $category)
	{
		// If there are no parent boards we can see, avoid showing an empty category (unless its collapsed).
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		// @todo - Invent nifty class name for boardindex header bars.
		echo '
		
		<header class="category_header">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
			<a class="chevricon i-chevron-', $category['is_collapsed'] ? 'down' : 'up', '" href="', $category['collapse_href'], '" title="', $category['is_collapsed'] ? $txt['show'] : $txt['hide'], '"></a>';

		// The "category link" is only a link for logged in members. Guests just get the name.
		echo '
				', $category['link'], '
		</header>
		<section class="forum_category" id="category_', $category['id'], '">';

		// Assuming the category hasn't been collapsed...
		if (!$category['is_collapsed'])
			template_list_boards($category['boards'], 'category_' . $category['id'] . '_boards');

		echo '
		</section>';
	}
}

/**
 * Show information above the boardindex, like the newsfader
 */
function template_boardindex_outer_above()
{
	global $context, $settings, $txt;

	// Show some statistics if info centre stats is off.
	if (!$settings['show_stats_index'])
	{
		echo '
		<div id="index_common_stats">
			', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics_made'], ': ', $context['common_stats']['total_topics'], '<br />
			', $settings['show_latest_member'] ? ' ' . sprintf($txt['welcome_newest_member'], ' <strong>' . $context['common_stats']['latest_member']['link'] . '</strong>') : '', '
		</div>';
	}

		echo '
		<main>';
}

/**
 * Show information below the boardindex, like stats, infocenter
 */
function template_boardindex_outer_below()
{
	global $context, $settings, $txt;

	// @todo - Just <div> for the parent, <p>'s for the icon stuffz, and the buttonlist <ul> for "Mark read".
	// Sort the floats in the CSS file, as other tricks will be needed as well (media queries, for instance).
	echo '
		</main>
		<aside id="posting_icons">';

	// Show the mark all as read button?
	if ($settings['show_mark_read'] && !$context['user']['is_guest'] && !empty($context['categories']))
		echo '
			', template_button_strip($context['mark_read_button'], 'right');

	if ($context['user']['is_logged'])
		echo '
			<p title="', $txt['new_posts'], '"><i class="icon i-board-new"></i>', $txt['new_posts'], '</p>';

	echo '
			<p title="', $txt['old_posts'], '"><i class="icon i-board-off"></i>', $txt['old_posts'], '</p>
			<p title="', $txt['redirect_board'], '"><i class="icon i-board-redirect"></i>', $txt['redirect_board'], '</p>
		</aside>';
}

/**
 * The infocenter ... stats, recent topics, other important information that never gets seen :P
 */
function template_info_center_below()
{
	global $context, $txt;

	if (empty($context['info_center_callbacks']))
	{
		return;
	}

	// Here's where the "Info Center" starts...
	echo '
	<aside id="info_center" class="forum_category">
		<h2 class="category_header panel_toggle">
				<i id="upshrink_ic" class="hide chevricon i-chevron-', empty($context['minmax_preferences']['info']) ? 'up' : 'down', '" title="', $txt['hide'], '"></i>
			<a href="#" id="upshrink_link">', sprintf($txt['info_center_title'], $context['forum_name_html_safe']), '</a>
		</h2>
		<ul id="upshrinkHeaderIC" class="category_boards', empty($context['minmax_preferences']['info']) ? '' : ' hide', '">';

	call_template_callbacks('ic', $context['info_center_callbacks']);

	echo '
		</ul>
	</aside>';

	// Info center collapse object.
	theme()->addInlineJavascript('
		var oInfoCenterToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['info']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'upshrinkHeaderIC\'
			],
			aSwapClasses: [
				{
					sId: \'upshrink_ic\',
					classExpanded: \'chevricon i-chevron-up\',
					titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
					classCollapsed: \'chevricon i-chevron-down\',
					titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			aSwapLinks: [
				{
					sId: \'upshrink_link\',
					msgExpanded: ' . JavaScriptEscape(sprintf($txt['info_center_title'], $context['forum_name_html_safe'])) . ',
					msgCollapsed: ' . JavaScriptEscape(sprintf($txt['info_center_title'], $context['forum_name_html_safe'])) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'minmax_preferences\',
				sSessionId: elk_session_id,
				sSessionVar: elk_session_var,
				sAdditionalVars: \';minmax_key=info\'
			},
			oCookieOptions: {
				bUseCookie: ' . ($context['user']['is_guest'] ? 'true' : 'false') . ',
				sCookieName: \'upshrinkIC\'
			}
		});
	', true);
}

/**
 * This is the "Recent Posts" bar.
 */
function template_ic_recent_posts()
{
	global $context, $txt, $scripturl, $settings;

	// Show the Recent Posts title
	// hslice class is a left over from webslice support.
	echo '
			<li class="board_row hslice" id="recent_posts_content">
				<h3 class="ic_section_header">
					<a href="', $scripturl, '?action=recent"><i class="icon i-post-text"></i>', $txt['recent_posts'], '</a>
				</h3>';

	// Only show one post.
	if ($settings['number_recent_posts'] == 1)
	{
		// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
		echo '
				<p id="infocenter_onepost" class="inline">
					<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>&nbsp;', sprintf($txt['is_recent_updated'], '&quot;' . $context['latest_post']['link'] . '&quot;'), ' (', $context['latest_post']['html_time'], ')
				</p>';
	}
	// Show lots of posts. @todo - Although data here is actually tabular, perhaps use faux table for greater responsiveness.
	elseif (!empty($context['latest_posts']))
	{
		echo '
				<table id="ic_recentposts">
					<tr>
						<th class="recentpost">', $txt['message'], '</th>
						<th class="recentposter">', $txt['author'], '</th>
						<th class="recentboard">', $txt['board'], '</th>
						<th class="recenttime">', $txt['date'], '</th>
					</tr>';

		// Each post in latest_posts has:
		// board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
		// subject, short_subject (shortened with...), time, link, and href.
		foreach ($context['latest_posts'] as $post)
		{
			echo '
					<tr>
						<td class="recentpost"><strong>', $post['link'], '</strong></td>
						<td class="recentposter">', $post['poster']['link'], '</td>
						<td class="recentboard">', $post['board']['link'], '</td>
						<td class="recenttime">', $post['html_time'], '</td>
					</tr>';
		}

		echo '
				</table>';
	}
	echo '
			</li>';
}

/**
 * Show information about events, birthdays, and holidays on the calendar in the info center
 */
function template_ic_show_events()
{
	global $context, $txt, $scripturl;

	echo '
			<li class="board_row">
				<h3 class="ic_section_header">
					<a href="', $scripturl, '?action=calendar">
						<i class="icon i-calendar"></i> ', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '
					</a>
				</h3>';

	// Holidays like "Christmas", "Hanukkah", and "We Love [Unknown] Day" :P.
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
					<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong>' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';

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
		// title, href, is_last, can_edit (are they allowed?), modify_href, and is_today.
		foreach ($context['calendar_events'] as $event)
			echo '
					', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . $txt['calendar_edit'] . '" class="icon i-modify"></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br />' : ', ';

		echo '
				</p>';
	}

	echo '
			</li>';
}

/**
 * Show statistical style information in the info center
 */
function template_ic_show_stats()
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	echo '
			<li class="board_row">
				<h3 class="ic_section_header">
					', $context['show_stats'] ? '<a href="' . $scripturl . '?action=stats" title="' . $txt['more_stats'] . '"><i class="icon i-pie-chart"></i>' . $txt['forum_stats'] . '</a>' : $txt['forum_stats'], '
				</h3>
				<p class="inline">
					', $context['common_stats']['boardindex_total_posts'], '', !empty($settings['show_latest_member']) ? ' - ' . $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong>' : '', ' - ', $txt['most_online_today'], ': ', comma_format($modSettings['mostOnlineToday']), '<br />
					', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong>  ( ' . $context['latest_post']['time'] . ' )' : ''), ' - <a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>
				</p>
			</li>';
}

/**
 * Show the online users in the info center
 */
function template_ic_show_users()
{
	global $context, $txt, $scripturl, $settings, $modSettings;

	// "Users online" - in order of activity.
	echo '
			<li class="board_row">
				<h3 class="ic_section_header">
					', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<i class="icon i-users"></i>', $txt['online_now'], ':
					', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);

	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);

	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . ($context['num_users_hidden'] == 1 ? $txt['hidden'] : $txt['hidden_s']);

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '
				</h3>';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
				<p class="inline">', sprintf($txt['users_active'], $modSettings['lastActive']), ': ', implode(', ', $context['list_users_online']), '</p>';

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
				<p class="inline membergroups">[' . implode(',&nbsp;', $context['membergroups']) . ']</p>';
	}
	echo '
			</li>';
}
