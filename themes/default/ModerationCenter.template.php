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
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Someone else did that for us, let's use it.
 */
function template_ModerationCenter_init()
{
	loadTemplate('GenericMessages');
}

/**
 * Template for the main page of moderation center.
 * It shows blocks with information.
 */
function template_moderation_center()
{
	global $context;

	// Show a welcome message to the user.
	echo '
					<div id="modcenter">
						<div id="mod_main_section" >';

	$alternate = true;

	// Show all the blocks they want to see.
	foreach ($context['mod_blocks'] as $block)
	{
		$block_function = 'template_' . $block;

		echo '
							<div class="modblock_', $alternate ? 'left' : 'right', '">', function_exists($block_function) ? $block_function() : '', '</div>';

		$alternate = !$alternate;
	}

	echo '
						</div>
					</div>';
}

/**
 * Template to show latest news.
 */
function template_latest_news()
{
	global $txt, $scripturl;

	echo '
								<h2 class="category_header">
									<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=live_news" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['mc_latest_news'], '
								</h2>
								<div class="content">
									<div id="ourAnnouncements" class="smalltext">', $txt['mc_cannot_connect_sm'], '</div>
								</div>';

	// This requires a lot of javascript...
	echo '
								<span class="hide" id="installedVersion">??</span>
								<span class="hide" id="latestVersion">??</span>
								<script>
						var oAdminCenter = new elk_AdminIndex({
							bLoadAnnouncements: true,
							sAnnouncementTemplate: ', JavaScriptEscape('
								<dl>
									%content%
								</dl>
							'), ',
							sAnnouncementMessageTemplate: ', JavaScriptEscape('
								<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
								<dd>
									%message%
								</dd>
							'), ',
							sAnnouncementContainerId: \'ourAnnouncements\',

							bLoadVersions: true,
							slatestVersionContainerId: \'latestVersion\',
							sinstalledVersionContainerId: \'installedVersion\',
							sVersionOutdatedTemplate: ', JavaScriptEscape('
								<span class="alert">%currentVersion%</span>
							'), '
						});
								</script>';
}

/**
 * Show all the group requests the user can see.
 */
function template_group_requests_block()
{
	global $context, $txt, $scripturl;

	echo '
								<h2 class="category_header hdicon cat_img_plus">
									<a href="', $scripturl, '?action=groups;sa=requests">', $txt['mc_group_requests'], '</a>
								</h2>
								<div class="content modbox">
									<ul>';

	foreach ($context['group_requests'] as $request)
		echo '
										<li class="smalltext">
											<a href="', $request['request_href'], '">', $request['group']['name'], '</a> ', $txt['mc_groupr_by'], ' ', $request['member']['link'], '
										</li>';

	// Don't have any watched users right now?
	if (empty($context['group_requests']))
		echo '
										<li>
											<strong class="smalltext">', $txt['mc_group_requests_none'], '</strong>
										</li>';

	echo '
									</ul>
								</div>';
}

/**
 * A block to show the current top reported posts.
 */
function template_reported_posts_block()
{
	global $context, $txt, $scripturl;

	echo '
								<h2 class="category_header hdicon cat_img_talk">
									<a href="', $scripturl, '?action=moderate;area=', $context['admin_area'], '">', $txt['mc_recent_reports'], '</a>
								</h2>
								<div class="content modbox">
									<ul>';

	foreach ($context['reported_posts'] as $report)
		echo '
										<li class="smalltext">
											<a href="', $report['report_href'], '">', $report['subject'], '</a> ', $txt['mc_reportedp_by'], ' ', $report['author']['link'], '
										</li>';

	// Don't have any watched users right now?
	if (empty($context['reported_posts']))
		echo '
										<li>
											<strong class="smalltext">', $txt['mc_recent_reports_none'], '</strong>
										</li>';

	echo '
									</ul>
								</div>';
}

/**
 * Template for viewing users on the watch list
 */
function template_watched_users()
{
	global $context, $txt, $scripturl;

	echo '
						<h2 class="category_header hdicon cat_img_eye">
							<a href="', $scripturl, '?action=moderate;area=userwatch">', $txt['mc_watched_users'], '</a>
						</h2>
						<div class="content modbox">
							<ul>';

	foreach ($context['watched_users'] as $user)
		echo '
								<li>
									<span class="smalltext">', sprintf(!empty($user['last_login']) ? $txt['mc_seen'] : $txt['mc_seen_never'], $user['link'], $user['last_login']), '</span>
								</li>';

	// Don't have any watched users right now?
	if (empty($context['watched_users']))
		echo '
								<li>
									<strong class="smalltext">', $txt['mc_watched_users_none'], '</strong>
								</li>';

	echo '
							</ul>
						</div>';
}

/**
 * Little section for making... notes.
 */
function template_notes()
{
	global $settings, $context, $txt, $scripturl;

	echo '
						<form action="', $scripturl, '?action=moderate;area=index" method="post">
							<h2 class="category_header hdicon cat_img_write">', $txt['mc_notes'], '</h2>
							<div class="content modbox">
								<div class="submitbutton">
									<input type="text" name="new_note" placeholder="', $txt['mc_click_add_note'], '" style="width: 89%" class="floatleft input_text" />
									<input type="submit" name="makenote" value="', $txt['mc_add_note'], '" />
								</div>';

	if (!empty($context['notes']))
	{
		echo '
								<ul class="moderation_notes">';

		// Cycle through the notes.
		foreach ($context['notes'] as $note)
			echo '
									<li class="smalltext"><a href="', $note['delete_href'], '"><img src="', $settings['images_url'], '/pm_recipient_delete.png" alt="" /></a> <strong>', $note['author']['link'], ':</strong> ', $note['text'], '</li>';

		echo '
								</ul>
								<div class="pagesection notes">
									<ul class="pagelinks">', $context['page_index'], '</ul>
								</div>';
	}

	echo '
							</div>
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						</form>';
}

/**
 * Template for viewing users on the watch list
 */
function template_action_required()
{
	global $context, $txt, $scripturl;

	echo '
						<h2 class="category_header hdicon cat_img_moderation">', $txt['mc_required'], ' : ', $context['mc_required'], '</h2>
						<div class="content modbox">
							<ul>';

	foreach ($context['required'] as $area => $total)
	{
		echo '
								<li>
									<i class="icon ', ($total == 0) ? 'i-check' : 'i-warning', '"></i>
									<a href="', $scripturl, $context['links'][$area], '">
										<span class="smalltext">', $txt['mc_' . $area], ' : ', $total, '</span>
									</a>
								</li>';
	}

	echo '
							</ul>
						</div>';
}

/**
 * Template for viewing posts that have been reported
 */
function template_reported_posts()
{
	global $context, $txt, $scripturl, $options;

	echo '
					<form id="reported_posts" action="', $scripturl, '?action=moderate;area=', $context['admin_area'], $context['view_closed'] ? ';sa=closed' : '', ';start=', $context['start'], '" method="post" accept-charset="UTF-8">
						<h2 class="category_header">
							', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
						</h2>';

	if (!empty($context['reports']))
		template_pagesection();

	foreach ($context['reports'] as $report)
	{
		$report['class'] = 'content';
		$report['title'] = '<strong>' . (!empty($report['board_name']) ? '<a href="' . $scripturl . '?board=' . $report['board'] . '.0">' . $report['board_name'] . '</a> / ' : '') . '<a href="' . $report['topic_href'] . '">' . $report['subject'] . '</a></strong> ' . $txt['mc_reportedp_by'] . ' <strong>' . $report['author']['link'] . '</strong>';

		// Prepare the comments...
		$comments = array();
		foreach ($report['comments'] as $comment)
			$comments[$comment['member']['id']] = $comment['member']['link'];
		$report['date'] = $txt['mc_reportedp_last_reported'] . ': ' . $report['last_updated'] . '&nbsp;-&nbsp;' . '
										' . $txt['mc_reportedp_reported_by'] . ': ' . implode(', ', $comments);

		template_simple_message($report);
	}

	// Were none found?
	if (empty($context['reports']))
		echo '
						<p class="infobox">', $txt['mc_reportedp_none_found'], '</p>';
	else
		template_pagesection(false, '', array('extra' => !$context['view_closed'] && !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 ? '<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="right_submit" />' : ''));

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</form>';
}

/**
 * Show a list of all the unapproved posts or topics
 * Provides links to approve to remove each
 */
function template_unapproved_posts()
{
	global $options, $context, $txt, $scripturl;

	// Just a big div of it all really...
	echo '
				<form action="', $scripturl, '?action=moderate;area=postmod;start=', $context['start'], ';sa=', $context['current_view'], '" method="post" accept-charset="UTF-8">
						<h2 class="category_header hdicon cat_img_posts">
							', $context['header_title'], '
						</h2>';

	// No posts?
	if (empty($context['unapproved_items']))
		echo '
						<div class="content">
							<p class="centertext">', $txt['mc_unapproved_' . $context['current_view'] . '_none_found'], '</p>
						</div>';
	else
		template_pagesection();

	// Loop through and show each unapproved post
	foreach ($context['unapproved_items'] as $item)
	{
		$item['class'] = 'content';
		$item['title'] = '<h5><strong>' . $item['category']['link'] . ' / ' . $item['board']['link'] . ' / ' . $item['link'] . '</strong></h5>';
		$item['date'] = $txt['mc_unapproved_by'] . ' <strong>' . $item['poster']['link'] . '</strong> : ' . $item['time'];

		template_simple_message($item);
	}

	// Quick moderation checkbox action selection
	$quick_mod = '';
	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['unapproved_items']))
		$quick_mod = '
					<div class="floatright">
						<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm(\'' . $txt['mc_unapproved_sure'] . '\')) submit();">
							<option value="0">' . $txt['with_selected'] . ':</option>
							<option value="0" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['approve'])) . '</option>
							<option value="approve">&#10148;&nbsp;' . $txt['approve'] . '</option>
							<option value="delete">&#10148;&nbsp;' . $txt['remove'] . '</option>
						</select>
						<noscript>
							<input type="submit" name="mc_go" value="' . $txt['go'] . '" />
						</noscript>
					</div>';

	if (!empty($context['unapproved_items']))
		template_pagesection(false, '', array('extra' => $quick_mod));

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>';
}

/**
 * View the details of a moderation report
 */
function template_viewmodreport()
{
	global $context, $scripturl, $txt;

	echo '
					<div id="modcenter">
						<form action="', $scripturl, '?action=moderate;area=', $context['admin_area'], ';report=', $context['report']['id'], '" method="post" accept-charset="UTF-8">
							<h2 class="category_header">
								', $context['section_title'], '
							</h2>
							<div class="content">
								<p class="warningbox">', $context['section_descripion'], '</p>
								<div class="content">
									', $context['report']['body'], '
								</div>
								<ul class="quickbuttons">
									<li class="listlevel1">
										<a class="linklevel1 close_button" href="', $scripturl, '?action=moderate;area=', $context['admin_area'], ';close=', (int) !$context['report']['closed'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $context['report']['closed'] ? $txt['mc_reportedp_open'] : $txt['mc_reportedp_close'], '</a>
									</li>
									<li class="listlevel1">
										<a class="linklevel1 ignore_button" href="', $scripturl, '?action=moderate;area=', $context['admin_area'], ';ignore=', (int) !$context['report']['ignore'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], '" ', !$context['report']['ignore'] ? 'onclick="return confirm(' . JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) . ');"' : '', '>', $context['report']['ignore'] ? $txt['mc_reportedp_unignore'] : $txt['mc_reportedp_ignore'], '</a>
									</li>
								</ul>
							</div>
							<h2 class="category_header">', $txt['mc_modreport_whoreported_title'], '</h2>';

	foreach ($context['report']['comments'] as $comment)
		echo '
							<div class="content">
								<p class="smalltext">', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '</p>
								<p>', $comment['message'], '</p>
							</div>';

	echo '
							<h2 class="category_header">', $txt['mc_modreport_mod_comments'], '</h2>
							<div class="content">';

	if (empty($context['report']['mod_comments']))
		echo '
								<p class="successbox">', $txt['mc_modreport_no_mod_comment'], '</p>';

	foreach ($context['report']['mod_comments'] as $comment)
		echo
		'<p>', $comment['member']['link'], ': ', $comment['message'], ' <em class="smalltext">(', $comment['time'], ')</em></p>';

	echo '
								<textarea rows="2" cols="60" style="width: 100%;" name="mod_comment"></textarea>
								<div class="submitbutton">
									<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" />
								</div>
							</div>';

	template_show_list('moderation_actions_list');

	echo '
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						</form>
					</div>';
}

/**
 * Callback function for showing a watched users post in the table.
 *
 * @param mixed[] $post
 */
function template_user_watch_post_callback($post)
{
	global $scripturl, $context, $txt;

	$output_html = '
					<div class="content">
						<div class="counter">' . $post['counter'] . '</div>
						<div class="topic_details">
							<h5><a href="' . $scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a> ' . $txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong></h5>
							<span class="smalltext">' . '&#171; ' . $txt['mc_watched_users_posted'] . ': ' . $post['poster_time'] . ' &#187;</span>
						</div>
						<div class="inner">' . $post['body'] . '</div>';

	if ($post['can_delete'])
		$output_html .= '
						<ul class="quickbuttons">
							<li class="listlevel1">
								<input type="checkbox" name="delete[]" value="' . $post['id'] . '" />
							</li>
							<li class="listlevel1">
								<a class="linklevel1 remove_button" href="' . $scripturl . '?action=moderate;area=userwatch;sa=post;delete=' . $post['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['mc_watched_users_delete_post'] . '\');">' . $txt['remove'] . '</a>
							</li>
						</ul>';

	$output_html .= '
					</div>';

	return $output_html;
}

/**
 * Moderation settings.
 */
function template_moderation_settings()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=settings" method="post" accept-charset="UTF-8">
			<div class="content">
				<dl class="settings">
					<dt>
						<label>', $txt['mc_prefs_homepage'], ':</label>
					</dt>
					<dd>';

	foreach ($context['homepage_blocks'] as $k => $v)
		echo '
						<label for="mod_homepage_', $k, '"><input type="checkbox" id="mod_homepage_', $k, '" name="mod_homepage[', $k, ']"', in_array($k, $context['mod_settings']['user_blocks']) ? ' checked="checked"' : '', ' /> ', $v, '</label><br />';

	echo '
					</dd>';

	// If they can moderate boards they have more options!
	if ($context['can_moderate_boards'])
	{
		echo '
					<dt>
						<label for="mod_show_reports">', $txt['mc_prefs_show_reports'], ':</label>
					</dt>
					<dd>
						<input type="checkbox" id="mod_show_reports" name="mod_show_reports" ', $context['mod_settings']['show_reports'] ? 'checked="checked"' : '', ' />
					</dd>
					<dt>
						<label for="mod_notify_report">', $txt['mc_prefs_notify_report'], ':</label>
					</dt>
					<dd>
						<select id="mod_notify_report" name="mod_notify_report">
							<option value="0" ', $context['mod_settings']['notify_report'] == 0 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_never'], '</option>
							<option value="1" ', $context['mod_settings']['notify_report'] == 1 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_moderator'], '</option>
							<option value="2" ', $context['mod_settings']['notify_report'] == 2 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_always'], '</option>
						</select>
					</dd>';
	}

	if ($context['can_moderate_approvals'])
	{
		echo '
					<dt>
						<label for="mod_notify_approval">', $txt['mc_prefs_notify_approval'], ':</label>
					</dt>
					<dd>
						<input type="checkbox" id="mod_notify_approval" name="mod_notify_approval" ', $context['mod_settings']['notify_approval'] ? 'checked="checked"' : '', ' />
					</dd>';
	}

	echo '
				</dl>
				<div class="submitbutton">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['mod-set_token_var'], '" value="', $context['mod-set_token'], '" />
					<input type="submit" name="save" value="', $txt['save'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Show a notice sent to a user in a new window
 */
function template_show_notice()
{
	global $txt, $settings, $context;

	// We do all the HTML for this one!
	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index.css', CACHE_STALE, '" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'index', $context['theme_variant'], '.css', CACHE_STALE, '" />
	</head>
	<body>
		<h2 class="category_header">', $txt['show_notice'], '</h2>
		<h2 class="category_header">', $txt['show_notice_subject'], ': ', $context['notice_subject'], '</h2>
		<div class="well">
			<div class="content">
				<dl>
					<dt>
						<strong>', $txt['show_notice_text'], ':</strong>
					</dt>
					<dd>
						', $context['notice_body'], '
					</dd>
				</dl>
			</div>
		</div>
	</body>
</html>';
}

/**
 * Add or edit a warning template.
 */
function template_warn_template()
{
	global $context, $txt, $scripturl;

	echo '
	<form action="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=', $context['id_template'], '" method="post" accept-charset="UTF-8">
		<h2 class="category_header">', $context['page_title'], '</h2>
		<div class="information">
			', $txt['mc_warning_template_desc'], '
		</div>
		<div id="modcenter">
			<div class="content">
				<div class="errorbox', empty($context['warning_errors']) ? ' hide"' : '"', ' id="errors">
					<dl>
						<dt>
							<strong id="error_serious">', $txt['error_while_submitting'], '</strong>
						</dt>
						<dd class="error" id="error_list">
							', empty($context['warning_errors']) ? '' : implode('<br />', $context['warning_errors']), '
						</dd>
					</dl>
				</div>
				<div id="box_preview"', !empty($context['template_preview']) ? '' : ' class="hide"', '>
					<dl class="settings">
						<dt>
							<strong>', $txt['preview'], '</strong>
						</dt>
						<dd id="template_preview">
							', !empty($context['template_preview']) ? $context['template_preview'] : '', '
						</dd>
					</dl>
				</div>
				<dl class="settings">
					<dt>
						<label for="template_title">', $txt['mc_warning_template_title'], ':</label>
					</dt>
					<dd>
						<input type="text" id="template_title" name="template_title" value="', $context['template_data']['title'], '" size="30" class="input_text" />
					</dd>
					<dt>
						<label for="template_body">', $txt['profile_warning_notify_body'], ':</label><br />
						<span class="smalltext">', $txt['mc_warning_template_body_desc'], '</span>
					</dt>
					<dd>
						<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext">', $context['template_data']['body'], '</textarea>
					</dd>
				</dl>';

	if ($context['template_data']['can_edit_personal'])
		echo '
				<input type="checkbox" name="make_personal" id="make_personal" ', $context['template_data']['personal'] ? 'checked="checked"' : '', ' />
				<label for="make_personal">
					<strong>', $txt['mc_warning_template_personal'], '</strong>
				</label>
				<br />
				<span class="smalltext">', $txt['mc_warning_template_personal_desc'], '</span>
				<br />';

	echo '
				<hr />
				<div class="submitbutton">
					<input type="submit" name="preview" id="preview_button" value="', $txt['preview'], '" />
					<input type="submit" name="save" value="', $context['page_title'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['mod-wt_token_var'], '" value="', $context['mod-wt_token'], '" />
				</div>
			</div>
		</div>
	</form>

	<script>
		$(function() {
			$("#preview_button").click(function() {
				return ajax_getTemplatePreview();
			});
		});
	</script>';
}
