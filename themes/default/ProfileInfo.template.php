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

/**
 * Add settings that will be used in the template
 */
function template_ProfileInfo_init()
{
	global $settings;

	// This piece is used to style attachments awaiting approval in the list
	$settings['attachments_awaiting_approval'] = '{attachment_link}&nbsp;(<em>{txt_awaiting}</em>)';

	// This setting is used to load a certain number of attachments
	// in the user's profile summary, change it to a number if you need any
	$settings['attachments_on_summary'] = 10;

	theme()->getTemplates()->load('GenericMessages');
}

/**
 * This template displays users details without any option to edit them.
 */
function template_action_summary()
{
	global $context;

	// We do have some data to show I would hope
	if (!empty($context['summarytabs']))
	{
		// All the tab names
		$tabs = array_keys($context['summarytabs']);
		$tab_num = 0;

		// Start with the navigation ul, its converted to the tab navigation by jqueryUI
		echo '
			<div class="profile_center">
				<div id="tabs">
					<ul>';

		// A link for every tab
		foreach ($tabs as $tab)
		{
			$tab_num++;
			echo '
						<li>
							<a href="', (isset($context['summarytabs'][$tab]['href']) ? $context['summarytabs'][$tab]['href'] : '#tab_' . $tab_num), '">', $context['summarytabs'][$tab]['name'], '</a>
						</li>';
		}

		echo '
					</ul>';

		// For preload tabs (those without href), output the content divs and call the templates as defined by the tabs
		$tab_num = 0;
		foreach ($tabs as $tab)
		{
			if (isset($context['summarytabs'][$tab]['href']))
			{
				continue;
			}

			// Start a tab
			$tab_num++;
			echo '
					<div id="tab_', $tab_num, '">';

			// Each template in the tab gets placed in a container
			foreach ($context['summarytabs'][$tab]['templates'] as $templates)
			{
				echo '
						<div class="profile_content">';

				// This container has multiple templates in it (like side x side)
				if (is_array($templates))
				{
					foreach ($templates as $template)
					{
						$block = 'template_profile_block_' . $template;
						$block();
					}
				}
				// Or just a single template is fine
				else
				{
					$block = 'template_profile_block_' . $templates;
					$block();
				}

				echo '
						</div>';
			}

			// Close the tab
			echo '
					</div>';
		}

		// Close the profile center
		echo '
				</div>
			</div>';
	}
}

/**
 * Template for showing all the posts of the user, in chronological order.
 */
function template_action_showPosts()
{
	global $context, $txt;

	template_pagesection();

	echo '
		<div id="recentposts" class="profile_center">
			<h2 class="category_header">
				', empty($context['is_topics']) ? $txt['showMessages'] : $txt['showTopics'], $context['user']['is_owner'] ? '' : ' - ' . $context['member']['name'], '
			</h2>';

	// No posts? Just end the table with a informative message.
	if (empty($context['posts']))
	{
		echo '
				<div class="content">
					', $context['is_topics'] ? $txt['show_topics_none'] : $txt['show_posts_none'], '
				</div>';
	}
	else
	{
		// For every post to be displayed, give it its own div, and show the important details of the post.
		foreach ($context['posts'] as $post)
		{
			$post['title'] = '<strong>' . $post['board']['link'] . ' / ' . $post['topic']['link'] . '</strong>';
			$post['date'] = $post['html_time'];
			$post['class'] = 'content';

			if (!$post['approved'])
			{
				$post['body'] = '
						<div class="approve_post">
							<em>' . $txt['post_awaiting_approval'] . '</em>
						</div>' . '
					' . $post['body'];
			}

			template_simple_message($post);
		}
	}

	echo '
		</div>';

	// Show more page numbers.
	template_pagesection();
}

/**
 * Show the individual users permissions
 */
function template_action_showPermissions()
{
	global $context, $scripturl, $txt;

	echo '
		<h2 class="category_header hdicon cat_img_profile">
			', $txt['showPermissions'], '
		</h2>';

	if ($context['member']['has_all_permissions'])
	{
		echo '
		<p class="description">', $txt['showPermissions_all'], '</p>';
	}
	else
	{
		echo '
		<p class="description">', $txt['showPermissions_help'], '</p>
		<div id="permissions" class="flow_hidden">';

		if (!empty($context['no_access_boards']))
		{
			echo '
				<h2 class="category_header">', $txt['showPermissions_restricted_boards'], '</h2>
				<div class="content smalltext">', $txt['showPermissions_restricted_boards_desc'], ':<br />';

			foreach ($context['no_access_boards'] as $no_access_board)
			{
				echo '
					', $no_access_board['name'], $no_access_board['is_last'] ? '' : ', ';
			}

			echo '
				</div>';
		}

		// General Permissions section.
		echo '
				<div>
					<h4 class="category_header">', $txt['showPermissions_general'], '</h4>';

		if (!empty($context['member']['permissions']['general']))
		{
			echo '
					<table class="table_grid">
						<thead>
							<tr class="table_head">
								<th scope="col" class="lefttext" style="width: 50%;">', $txt['showPermissions_permission'], '</th>
								<th scope="col" class="lefttext" style="width: 50%;">', $txt['showPermissions_status'], '</th>
							</tr>
						</thead>
						<tbody>';

			foreach ($context['member']['permissions']['general'] as $permission)
			{
				echo '
							<tr>
								<td title="', $permission['id'], '">
									', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
								</td>
								<td class="smalltext">';

				if ($permission['is_denied'])
				{
					echo '
									<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']), '</span>';
				}
				else
				{
					echo '
									', $txt['showPermissions_given'], ':&nbsp;', implode(', ', $permission['groups']['allowed']);
				}

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
		{
			echo '
			<p class="description">', $txt['showPermissions_none_general'], '</p>';
		}

		// Board permission section.
		echo '
			<div>
				<form action="' . $scripturl . '?action=profile;u=', $context['id_member'], ';area=permissions#board_permissions" method="post" accept-charset="UTF-8">
					<h4 class="category_header">
						<a id="board_permissions"></a>', $txt['showPermissions_select'], ':
						<select name="board" onchange="if (this.options[this.selectedIndex].value) this.form.submit();">
							<option value="0"', $context['board'] == 0 ? ' selected="selected"' : '', '>', $txt['showPermissions_global'], '&nbsp;</option>';

		if (!empty($context['boards']))
		{
			echo '
							<option value="" disabled="disabled">', str_repeat('&#8212;', strlen($txt['showPermissions_global'])), '</option>';
		}

		// Fill the box with any local permission boards.
		foreach ($context['boards'] as $board)
		{
			echo '
							<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['name'], ' (', $board['profile_name'], ')</option>';
		}

		echo '
						</select>
					</h4>
				</form>';

		if (!empty($context['member']['permissions']['board']))
		{
			echo '
				<table class="table_grid">
					<thead>
						<tr class="table_head">
							<th scope="col" class="lefttext" style="width: 50%;">', $txt['showPermissions_permission'], '</th>
							<th scope="col" class="lefttext" style="width: 50%;">', $txt['showPermissions_status'], '</th>
						</tr>
					</thead>
					<tbody>';

			foreach ($context['member']['permissions']['board'] as $permission)
			{
				echo '
						<tr>
							<td title="', $permission['id'], '">
								', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
							</td>
							<td class="smalltext">';

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
		{
			echo '
			<p class="description">', $txt['showPermissions_none_board'], '</p>';
		}

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
	global $context, $txt;

	// First, show a few text statistics such as post/topic count.
	echo '
	<div id="profileview">
		<div id="generalstats">
			<div class="content content_noframe">
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
		</div>';

	// This next section draws a graph showing what times of day they post the most.
	echo '
		<div class="separator"></div>
		<div id="activitytime" class="flow_hidden">
			<h2 class="category_header hdicon cat_img_clock">
				', $txt['statPanel_activityTime'], '
			</h2>
			<div class="content content_noframe">';

	// If they haven't post at all, don't draw the graph.
	if (empty($context['posts_by_time']))
	{
		echo '
				<span class="centertext">', $txt['statPanel_noPosts'], '</span>';
	}
	// Otherwise do!
	else
	{
		echo '
				<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach ($context['posts_by_time'] as $time_of_day)
		{
			echo '
					<li>
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
				<span class="clear"></span>
			</div>
		</div>';

	// Two columns with the most popular boards by posts and activity (activity = users posts / total posts).
	echo '
		<div class="flow_hidden">
			<div id="popularposts">
				<h2 class="category_header hdicon cat_img_write">
					', $txt['statPanel_topBoards'], '
				</h2>
				<div class="content content_noframe">';

	if (empty($context['popular_boards']))
	{
		echo '
					<span class="centertext">', $txt['statPanel_noPosts'], '</span>';
	}

	else
	{
		echo '
					<dl>';

		// Draw a bar for every board.
		foreach ($context['popular_boards'] as $board)
		{
			$position = -1 * intval(((int) $board['posts_percent'] / 5)) * 20;

			echo '
						<dt>', $board['link'], '</dt>
						<dd>
							<div class="profile_pie" style="background-position: ', $position, 'px 0;" title="', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '">
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
			<div id="popularactivity">
				<h2 class="category_header hdicon cat_img_piechart">
					', $txt['statPanel_topBoardsActivity'], '
				</h2>
				<div class="content content_noframe">';

	if (empty($context['board_activity']))
	{
		echo '
					<span>', $txt['statPanel_noPosts'], '</span>';
	}
	else
	{
		echo '
					<dl>';

		// Draw a bar for every board.
		foreach ($context['board_activity'] as $activity)
		{
			$position = -1 * intval(((int) $activity['percent'] / 5)) * 20;

			echo '
						<dt>', $activity['link'], '</dt>
						<dd>
							<div class="profile_pie" style="background-position: ', $position, 'px 0;" title="', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '">
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
}

/**
 * Show all warnings of a user
 */
function template_viewWarning()
{
	global $context, $txt;

	template_load_warning_variables();

	echo '
		<h2 class="category_header hdicon cat_img_profile">
			', sprintf($txt['profile_viewwarning_for_user'], $context['member']['name']), '
		</h2>
		<p class="description">', $txt['viewWarning_help'], '</p>
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
					<div class="progress_bar progress_bar_compact">
						<div class="full_bar full_bar_compact">', $context['member']['warning'], '%</div>
						<div class="green_percent green_percent_compact" style="width: ', $context['member']['warning'], '%;">&nbsp;</div>
					</div>
				</dd>';

	// There's some impact of this?
	if (!empty($context['level_effects'][$context['current_level']]))
	{
		echo '
				<dt>
					<strong>', $txt['profile_viewwarning_impact'], ':</strong>
				</dt>
				<dd>
					', $context['level_effects'][$context['current_level']], '
				</dd>';
	}

	echo '
			</dl>
		</div>';

	template_show_list('view_warnings');
}

/**
 * Profile Summary Block
 *
 * Show avatar, title, group info, number of posts, karma, likes
 * Has links to show posts, drafts and attachments
 */
function template_profile_block_summary()
{
	global $txt, $context, $modSettings;

	echo '
			<div class="profileblock_left">
				<h2 class="category_header hdicon cat_img_profile">
					', ($context['user']['is_owner']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'area' => 'forumprofile', 'u' => $context['member']['id'], 'name' => $context['member']['name']]) . '">' . $txt['profile_user_summary'] . '</a>' : $txt['profile_user_summary'], '
				</h2>
				<div id="basicinfo">
					<div class="username">
						<h4><span class="position">', (!empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group']), '</span></h4>
					</div>
					', $context['member']['avatar']['image'], '
					<span id="userstatus">', template_member_online($context['member']), '<span class="smalltext"> ' . $context['member']['online']['label'] . '</span>', '</span>
				</div>
				<div id="detailedinfo">
					<dl>';

	// The members display name
	echo '
						<dt>', $txt['display_name'], ':</dt>
						<dd>', $context['member']['name'], '</dd>';

	// The username if allowed
	if ($context['user']['is_owner'] || $context['user']['is_admin'])
	{
		echo '
						<dt>', $txt['username'], ':</dt>
						<dd>', $context['member']['username'], '</dd>';
	}

	// Some posts stats for fun
	if (!isset($context['disabled_fields']['posts']))
	{
		echo '
						<dt>', $txt['profile_posts'], ':</dt>
						<dd>', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</dd>';
	}

	// Title?
	if (!empty($modSettings['titlesEnable']) && !empty($context['member']['title']))
	{
		echo '
						<dt>', $txt['custom_title'], ':</dt>
						<dd>', $context['member']['title'], '</dd>';
	}

	// If karma is enabled show the members karma.
	if ($modSettings['karmaMode'] == '1')
	{
		echo '
						<dt>', $modSettings['karmaLabel'], '</dt>
						<dd>', ($context['member']['karma']['good'] - $context['member']['karma']['bad']), '</dd>';
	}
	elseif ($modSettings['karmaMode'] == '2')
	{
		echo '
						<dt>', $modSettings['karmaLabel'], '</dt>
						<dd>+', $context['member']['karma']['good'], '/-', $context['member']['karma']['bad'], '</dd>';
	}

	// What do they like?
	if (!empty($modSettings['likes_enabled']))
	{
		echo '
						<dt>', $txt['likes'], ': </dt>
						<dd>', $txt['likes_profile_given'], ': ', $context['member']['likes']['given'], ' / ', $txt['likes_profile_received'], ': ', $context['member']['likes']['received'], '</dd>';
	}

	// Some links to this users fine work
	echo '
						<dt>', $txt['profile_activity'], ': </dt>
						<dd>
							<a href="', getUrl('profile', ['action' => 'profile', 'area' => 'showposts', 'u' => $context['member']['id'], 'name' => $context['member']['name']]), '">', $txt['showPosts'], '</a>
							<br />';

	if ($context['user']['is_owner'] && !empty($modSettings['drafts_enabled']))
	{
		echo '
							<a href="', getUrl('profile', ['action' => 'profile', 'area' => 'showdrafts', 'u' => $context['member']['id'], 'name' => $context['member']['name']]), '">', $txt['drafts_show'], '</a>
							<br />';
	}

	echo '
							<a href="', getUrl('profile', ['action' => 'profile', 'area' => 'statistics', 'u' => $context['member']['id'], 'name' => $context['member']['name']]), '">', $txt['statPanel'], '</a>
						</dd>';

	// close this block up
	echo '
					</dl>
				</div>
			</div>';
}

/**
 * Profile Info Block
 *
 * Show additional user details including: age, join date,
 * localization details (language and time)
 * If user has permissions can see IP address
 */
function template_profile_block_user_info()
{
	global $settings, $txt, $context, $scripturl, $modSettings;

	echo '
		<div class="profileblock_right">
			<h2 class="category_header hdicon cat_img_stats_info">
				', ($context['user']['is_owner']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'area' => 'forumprofile', 'u' => $context['member']['id'], 'name' => $context['member']['name']]) . '">' . $txt['profile_user_info'] . '</a>' : $txt['profile_user_info'], '
			</h2>
			<div class="profileblock">
					<dl>';

	// And how old are we, oh my!
	echo '
					<dt>', $txt['age'], ':</dt>
					<dd>', $context['member']['age'] . ($context['member']['today_is_birthday'] ? ' &nbsp; <img src="' . $settings['images_url'] . '/cake.png" alt="" />' : ''), '</dd>';

	// How long have they been a member, and when were they last on line?
	echo '
					<dt>', $txt['date_registered'], ':</dt>
					<dd>', $context['member']['registered'], '</dd>

					<dt>', $txt['lastLoggedIn'], ':</dt>
					<dd>', $context['member']['last_login'], '</dd>';

	// If the person looking is allowed, they can check the members IP address and hostname.
	if ($context['can_see_ip'])
	{
		if (!empty($context['member']['ip']))
		{
			echo '
						<dt>', $txt['ip'], ':</dt>
						<dd><a href="', $scripturl, '?action=profile;area=history;sa=ip;searchip=', $context['member']['ip'], ';u=', $context['member']['id'], '">', $context['member']['ip'], '</a></dd>';
		}

		if (empty($modSettings['disableHostnameLookup']) && !empty($context['member']['ip']))
		{
			echo '
						<dt>', $txt['hostname'], ':</dt>
						<dd>', $context['member']['hostname'], '</dd>';
		}
	}

	// Users language
	if (!empty($modSettings['userLanguage']) && !empty($context['member']['language']))
	{
		echo '
						<dt>', $txt['language'], ':</dt>
						<dd>', $context['member']['language'], '</dd>';
	}

	// And their time settings
	echo '
						<dt>', $txt['local_time'], ':</dt>
						<dd>', $context['member']['local_time'], '</dd>';

	// What are they up to?
	if (!isset($context['disabled_fields']['action']) && !empty($context['member']['action']))
	{
		echo '
						<dt>', $txt['profile_action'], ':</dt>
						<dd>', $context['member']['action'], '</dd>';
	}

	// nuff about them, lets get back to me!
	echo '
				</dl>
			</div>
	</div>';
}

/**
 * Profile Contact Block
 * Show information on how to contact a member
 * Allows the adding or removal of buddies
 * Provides a PM link
 * Provides a Email link
 * Shows any profile website information
 * Shows custom profile fields of placement type '1', "with icons"
 */
function template_profile_block_contact()
{
	global $txt, $context, $scripturl;

	$ci_empty = true;

	echo '
		<div class="profileblock_left">
			<h2 class="category_header hdicon cat_img_contacts">
				', $txt['profile_contact'], '
			</h2>
			<div class="profileblock">
				<dl>';

	// If viewing another profile, can they add this member as a buddy, or can they de-buddy them instead?
	if (!empty($context['can_have_buddy']) && !$context['user']['is_owner'])
	{
		$ci_empty = false;
		echo '
					<dt>
						<i class="icon i-user', $context['member']['is_buddy'] ? '-minus' : '-plus', '"></i>
					</dt>
					<dd>
						<a class="linkbutton" href="', $scripturl, '?action=buddy;u=', $context['id_member'], ';', $context['session_var'], '=', $context['session_id'], ';sa=', ($context['member']['is_buddy'] ? 'remove' : 'add'), '">', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], '</a>
					</dd>';
	}

	// PM's are nice to send, to others, not to your self
	if (!$context['user']['is_owner'] && $context['can_send_pm'])
	{
		$ci_empty = false;
		echo '
					<dt>
						<i class="icon i-comment', $context['member']['online']['is_online'] ? '' : '-blank', '"></i>
					</dt>
					<dd>
						<a class="linkbutton" href="', $scripturl, '?action=pm;sa=send;u=', $context['member']['id'], '">', $txt['send_member_pm'], '</a>
					</dd>';
	}

	// Show the email contact info?
	if ($context['can_send_email'])
	{
		$ci_empty = false;
		echo '
					<dt>
						<i class="icon i-envelope-o', $context['member']['online']['is_online'] ? '' : '-blank', '"><s>', $txt['email'], '</s></i>
					</dt>
					<dd><em>', template_member_email($context['member'], true), '</em></dd>';
	}

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
	{
		$ci_empty = false;
		echo '
					<dt>
						<i class="icon i-website" title="', $txt['website'], '"></i>
					</dt>
					<dd>
						<a href="', $context['member']['website']['url'], '" target="_blank" rel="noopener noreferrer" class="new_win">', $context['member']['website']['title'] == '' ? $context['member']['website']['url'] : $context['member']['website']['title'], '</a>
					</dd>';
	}

	echo '
				</dl>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		$cf_show = false;
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] == 1 && !empty($field['value']))
			{
				$ci_empty = false;
				if (empty($cf_show))
				{
					echo '
				<ul class="cf_icons">';
					$cf_show = true;
				}

				// removed $field['name'], ': ', as it was annoying

				echo '
					<li class="cf_icon">', $field['output_html'], '</li>';
			}
		}

		if (!empty($cf_show))
		{
			echo '
				</ul>';
		}
	}

	// No way to contact this member at all ... welcome home freak!
	if ($ci_empty === true)
	{
		echo
		$txt['profile_contact_no'];
	}

	echo '
			</div>
		</div>';
}

/**
 * Profile Signature / Other Block
 *
 * Shows the users signature
 * Shows custom profile fields that are placed with the signature line
 */
function template_profile_block_other_info()
{
	global $txt, $context;

	echo '
		<div class="profileblock_right">
			<h2 class="category_header hdicon cat_img_write">
				', ($context['user']['is_owner']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'area' => 'forumprofile', 'u' => $context['member']['id'], 'name' => $context['member']['name']]) . '">' . $txt['profile_more'] . '</a>' : $txt['profile_more'], '
			</h2>
			<div class="profileblock profileblock_signature">';

	// Are there any custom profile fields for the above signature area?
	if (!empty($context['custom_fields']))
	{
		$shown = false;
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 2 || empty($field['output_html']))
			{
				continue;
			}

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
	}

	// Show the users signature.
	if ($context['signature_enabled'] && !empty($context['member']['signature']))
	{
		if (empty($shown))
		{
			echo '
				<dl>';

			$shown = true;
		}

		echo '
					<dt>', $txt['signature'], ':</dt>
					<dd>', $context['member']['signature'], '</dd>';
	}

	if (empty($shown))
	{
		echo $txt['profile_signature_no'];
	}
	else
	{
		echo '
				</dl>';
	}

	// Done with this block
	echo '
			</div>
		</div>';
}

/**
 * Profile Custom Block
 *
 * Show the custom profile fields for standard (value 0) placement
 */
function template_profile_block_user_customprofileinfo()
{
	global $txt, $context;

	echo '
		<div class="profileblock_left">
			<h2 class="category_header hdicon cat_img_plus">
				', ($context['user']['is_owner']) ? '<a href="' . getUrl('profile', ['action' => 'profile', 'area' => 'forumprofile', 'u' => $context['member']['id'], 'name' => $context['member']['name']]) . '">' . $txt['profile_info'] . '</a>' : $txt['profile_info'], '
			</h2>
			<div class="profileblock">';

	// Any custom fields for standard (non-icon, non-sig) placement?
	if (!empty($context['custom_fields']))
	{
		$shown = false;
		foreach ($context['custom_fields'] as $field)
		{
			if (($field['placement'] == 0 || $field['placement'] == 3) && !empty($field['output_html']))
			{
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
		}

		if (!empty($shown))
		{
			echo '
				</dl>';
		}
	}

	if (empty($shown))
	{
		echo $txt['profile_additonal_no'];
	}

	echo '
			</div>
		</div>';
}

/**
 * Profile Moderation Block
 *
 * Show any warnings on this user and allows for editing
 * Can approve members waiting activation
 * Needs the correct permissions for either action in order to view
 */
function template_profile_block_moderation()
{
	global $txt, $context, $scripturl;

	// Can they view warnings or approve members if so only show this if we needed
	if (($context['can_view_warning'] && $context['member']['warning']) || (!empty($context['activate_message']) || !empty($context['member']['bans'])))
	{
		echo '
		<div class="profileblock_right">
			<h2 class="category_header hdicon cat_img_moderation">
				', $txt['profile_moderation'], '
			</h2>
			<div class="profileblock">';

		// Can they view/issue a warning?
		if ($context['can_view_warning'] && $context['member']['warning'])
		{
			echo '
				<dl>
					<dt>', $txt['profile_warning_level'], ':</dt>
					<dd>
						<a href="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=', $context['can_issue_warning'] ? 'issuewarning' : 'viewwarning', '">', $context['member']['warning'], '%</a>';

			// Can we provide information on what this means?
			if (!empty($context['warning_status']))
			{
				echo '
					<span class="smalltext">(', $context['warning_status'], ')</span>';
			}

			echo '
					</dd>
				</dl>';
		}

		// Is this member requiring activation and/or banned?
		if (!empty($context['activate_message']) || !empty($context['member']['bans']))
		{
			echo '
				<dl>';

			// If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves.
			if (!empty($context['activate_message']))
			{
				echo '
					<dt class="clear">
						<span class="alert">', $context['activate_message'], '</span>&nbsp;(<a href="' . $context['activate_url'] . '"', ($context['activate_type'] == 4 ? ' onclick="return confirm(\'' . $txt['profileConfirm'] . '\');"' : ''), '>', $context['activate_link_text'], '</a>)
					</dt>';
			}

			// If the current member is banned, show a message and possibly a link to the ban.
			if (!empty($context['member']['bans']))
			{
				echo '
					<dt class="clear">
						<span class="alert">', $txt['user_is_banned'], '</span>&nbsp;
						<a class="linkbutton" href="#" onclick="document.getElementById(\'ban_info\').style.display = window.getComputedStyle(getElementById(\'ban_info\')).getPropertyValue(\'display\') === \'none\' ? \'inline\' : \'none\';return false;">' . $txt['view_ban'] . '</a>
					</dt>
					<dd class="hide" id="ban_info">
						<strong>', $txt['user_banned_by_following'], ':</strong>';

				foreach ($context['member']['bans'] as $ban)
				{
					echo '
						<br />
						<span class="smalltext">', $ban['explanation'], '</span>';
				}

				echo '
					</dd>';
			}

			echo '
				</dl>';
		}

		// Done with this block
		echo '
			</div>
		</div>';
	}
}

/**
 * Profile Buddies Block
 *
 * Shows a list of your buddies with pm/email links if available
 */
function template_profile_block_buddies()
{
	global $context, $scripturl, $txt, $modSettings;

	// Set the div height to about 4 lines of buddies w/avatars
	if (isset($context['buddies']))
	{
		$div_height = 120 + (4 * max(empty($modSettings['avatar_max_height']) ? 0 : $modSettings['avatar_max_height'], empty($modSettings['avatar_max_height']) ? 0 : $modSettings['avatar_max_height'], 65));
	}

	if (!empty($modSettings['enable_buddylist']) && $context['user']['is_owner'])
	{
		echo '
		<h2 class="category_header hdicon cat_img_buddies">
			<a href="', $scripturl, '?action=profile;area=lists;sa=buddies;u=', $context['member']['id'], '">', $txt['buddies'], '</a>
		</h2>
		<div class="flow_auto" ', (isset($div_height) ? 'style="max-height: ' . $div_height . 'px;"' : ''), '>
			<div class="attachments">';

		// Now show them all
		if (isset($context['buddies']))
		{
			foreach ($context['buddies'] as $buddy_id => $data)
			{
				echo '
				<div class="attachment">
					<div class="generic_border centertext">
						', $data['avatar']['image'], '<br />
						<a href="', getUrl('profile', ['action' => 'profile', 'u' => $data['id'], 'name' => $data['name']]), '">', $data['name'], '</a>
						<br />
						', template_member_online($data), '<em><span class="smalltext"> ' . $txt[$data['online']['is_online'] ? 'online' : 'offline'] . '</span></em>
						<div class="contact">';

				// Only show the email address fully if it's not hidden - and we reveal the email.
				echo template_member_email($data);

				// Can they send the buddy a PM?
				if ($context['can_send_pm'])
				{
					echo '
						<a href="', $scripturl, '?action=pm;sa=send;u=', $data['id'], '" class="icon i-comment', $data['online']['is_online'] ? '' : '-blank', '" title="', $txt['profile_sendpm_short'], ' to ', $data['name'], '"><s>', $txt['profile_sendpm_short'], ' to ', $data['name'], '</s></a>';
				}

				// Other contact info from custom profile fields?
				if (isset($data['custom_fields']))
				{
					$im = array();

					foreach ($data['custom_fields'] as $key => $cpf)
					{
						if ($cpf['placement'] == 1)
						{
							$im[] = $cpf['value'];
						}
					}

					echo implode(' ', $im);
				}
				// Done with the contact information
				echo '
						</div>
					</div>
				</div>';
			}
		}
		// Buddyless how sad :'(
		else
		{
			echo '
				<div class="infobox">
					', $txt['profile_buddies_no'], '
				</div>';
		}

		// All done
		echo '
			</div>
		</div>';
	}
}

/**
 * Consolidated profile block output.
 */
function template_profile_blocks()
{
	global $context;

	if (empty($context['profile_blocks']))
	{
		return;
	}
	else
	{
		foreach ($context['profile_blocks'] as $profile_block)
		{
			$profile_block();
		}
	}
}

/**
 * Profile Attachments Block
 *
 * Shows the most recent attachments (as thumbnails) for this user
 */
function template_profile_block_attachments()
{
	global $txt, $context;

	// The attachment div
	echo '
	<h2 class="category_header hdicon cat_img_attachments">
		<a href="', getUrl('profile', ['action' => 'profile', 'area' => 'showposts', 'sa' => 'attach', 'u' => $context['member']['id'], 'name' => $context['member']['name']]), '">', $txt['profile_attachments'], '</a>
	</h2>
	<div class="attachments">';

	// Show the thumbnails
	if (!empty($context['thumbs']))
	{
		foreach ($context['thumbs'] as $picture)
		{
			echo '
		<div class="attachment generic_border">
			<div class="profile_content attachment_thumb">
				<a id="link_', $picture['id'], '" href="', $picture['url'], '">', $picture['img'], '</a>
			</div>
			<div class="attachment_name">
				', $picture['subject'], '
			</div>
		</div>';
		}
	}
	// No data for this member
	else
	{
		echo '
		<div class="infobox">
			', $txt['profile_attachments_no'], '
		</div>';
	}

	// All done
	echo '
	</div>';
}

/**
 * Profile Posts Block
 *
 * Shows the most recent posts for this user
 */
function template_profile_block_posts()
{
	global $txt, $context;

	// The posts block
	echo '
	<h2 class="category_header hdicon cat_img_posts">
		<a href="', getUrl('profile', ['action' => 'profile', 'area' => 'showposts', 'sa' => 'messages', 'u' => $context['member']['id'], 'name' => $context['member']['name']]), '">', $txt['profile_recent_posts'], '</a>
	</h2>
	<div class="flow_auto">
		<table id="ps_recentposts">';

	if (!empty($context['posts']))
	{
		echo '
			<tr>
				<th class="recentpost">', $txt['message'], '</th>
				<th class="recentposter">', $txt['board'], '</th>
				<th class="recentboard">', $txt['subject'], '</th>
				<th class="recenttime">', $txt['date'], '</th>
			</tr>';

		foreach ($context['posts'] as $post)
		{
			echo '
			<tr>
				<td class="recentpost">', $post['body'], '</td>
				<td class="recentboard">', $post['board']['link'], '</td>
				<td class="recentsubject">', $post['link'], '</td>
				<td class="recenttime">', $post['time'], '</td>
			</tr>';
		}
	}
	// No data for this member
	else
	{
		echo '
			<tr>
				<td class="norecent">', (isset($context['loadaverage']) ? $txt['profile_loadavg'] : $txt['profile_posts_no']), '</td>
			</tr>';
	}

	// All done
	echo '
		</table>
	</div>';
}

/**
 * Profile Topics Block
 *
 * Shows the most recent topics that this user has started
 */
function template_profile_block_topics()
{
	global $txt, $context;

	// The topics block
	echo '
	<h2 class="category_header hdicon cat_img_topics">
		<a href="', getUrl('profile', ['action' => 'profile', 'area' => 'showposts', 'sa' => 'topics', 'u' => $context['member']['id'], 'name' => $context['member']['name']]), '">', $txt['profile_topics'], '</a>
	</h2>
	<div class="flow_auto">
		<table id="ps_recenttopics">';

	if (!empty($context['topics']))
	{
		echo '
			<tr>
				<th class="recenttopic">', $txt['subject'], '</th>
				<th class="recentboard">', $txt['board'], '</th>
				<th class="recenttime">', $txt['date'], '</th>
			</tr>';

		foreach ($context['topics'] as $post)
		{
			echo '
			<tr>
				<td class="recenttopic">', $post['link'], '</td>
				<td class="recentboard">', $post['board']['link'], '</td>
				<td class="recenttime">', $post['time'], '</td>
			</tr>';
		}
	}
	// No data for this member
	else
	{
		echo '
			<tr>
				<td class="norecent">', $txt['profile_topics_no'], '</td>
			</tr>';
	}

	// All done
	echo '
		</table>
	</div>';
}
