<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

/**
 * Set the user theme options.
 */
function template_options()
{
	global $context, $txt;

	$context['theme_options'] = [
		[
			'id' => 'use_sidebar_menu',
			'label' => $txt['use_sidebar_menu'],
			'default' => true,
		],
		[
			'id' => 'show_no_avatars',
			'label' => $txt['show_no_avatars'],
			'default' => true,
		],
		[
			'id' => 'show_no_smileys',
			'label' => $txt['show_no_smileys'],
			'default' => true,
		],
		[
			'id' => 'hide_poster_area',
			'label' => $txt['hide_poster_area'],
			'default' => true,
		],
		[
			'id' => 'show_no_signatures',
			'label' => $txt['show_no_signatures'],
			'default' => true,
		],
		[
			'id' => 'return_to_post',
			'label' => $txt['return_to_post'],
			'default' => true,
		],
		[
			'id' => 'no_new_reply_warning',
			'label' => $txt['no_new_reply_warning'],
			'default' => true,
		],
		[
			'id' => 'view_newest_pm_first',
			'label' => $txt['recent_pms_at_top'],
			'default' => true,
		],
		[
			'id' => 'wysiwyg_default',
			'label' => $txt['wysiwyg_default'],
			'default' => false,
		],
		[
			'id' => 'popup_messages',
			'label' => $txt['popup_messages'],
			'default' => true,
		],
		[
			'id' => 'pm_remove_inbox_label',
			'label' => $txt['pm_remove_inbox_label'],
			'default' => true,
		],
		[
			'id' => 'auto_notify',
			'label' => $txt['auto_notify'],
			'default' => true,
		],
		[
			'id' => 'topics_per_page',
			'label' => $txt['topics_per_page'],
			'options' => [
				0 => $txt['per_page_default'],
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			],
			'default' => true,
		],
		[
			'id' => 'messages_per_page',
			'label' => $txt['messages_per_page'],
			'options' => [
				0 => $txt['per_page_default'],
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			],
			'default' => true,
		],		[
			'id' => 'calendar_start_day',
			'label' => $txt['calendar_start_day'],
			'options' => [
				0 => $txt['days'][0],
				1 => $txt['days'][1],
				6 => $txt['days'][6],
			],
			'default' => true,
		],
		[
			'id' => 'display_quick_reply',
			'label' => $txt['display_quick_reply'],
			'default' => true,
		],
		[
			'id' => 'display_quick_mod',
			'label' => $txt['display_quick_mod'],
			'default' => true,
		],
	];
}

/**
 * Set the theme settings for display and edit in admin panel.
 */
function template_settings()
{
	global $context, $txt;

	$context['theme_settings'] = [
		[
			'id' => 'header_logo_url',
			'label' => $txt['header_logo_url'],
			'description' => $txt['header_logo_url_desc'],
			'type' => 'text',
		],
		[
			'id' => 'site_slogan',
			'label' => $txt['site_slogan'],
			'description' => $txt['site_slogan_desc'],
			'type' => 'text',
		],
		[
			'id' => 'header_layout',
			'label' => $txt['header_layout'],
			'options' => [
				0 => $txt['header_layout_default'],
				1 => $txt['header_layout_logo_only'],
				2 => $txt['header_layout_inverted'],
			],
			'description' => [
				'main' => $txt['header_layout_desc'],
				'options' => [
					0 => ['header_layout_default_name', 'header_layout_default_desc'],
					1 => ['header_layout_logo_only_name', 'header_layout_logo_only_desc'],
					2 => ['header_layout_inverted_name', 'header_layout_inverted_desc'],
				]
			],
			'type' => 'select',
		],
		'',
		[
			'id' => 'smiley_sets_default',
			'label' => $txt['smileys_default_set_for_theme'],
			'options' => $context['smiley_sets'],
			'type' => 'text',
		],
		[
			'id' => 'forum_width',
			'label' => $txt['forum_width'],
			'description' => $txt['forum_width_desc'],
			'type' => 'text',
			'size' => 8,
		],
		'',
		[
			'id' => 'show_mark_read',
			'label' => $txt['enable_mark_as_read'],
		],
		'',
		[
			'id' => 'enable_news',
			'label' => $txt['enable_news'],
			'options' => [
				0 => $txt['enable_news_off'],
				1 => $txt['enable_news_random'],
				2 => $txt['enable_news_fader'],
			],
			'type' => 'number',
			'description' => [
				'main' => '',
				'options' => [
					0 => ['enable_news_off_name', 'enable_news_off_desc'],
					1 => ['enable_news_random_name', 'enable_news_random_desc'],
					2 => ['enable_news_fader_name', 'enable_news_fader_desc'],
				]
			],
		],
		[
			'id' => 'newsfader_time',
			'label' => $txt['admin_fader_delay'],
			'type' => 'number',
		],
		'',
		[
			'id' => 'recent_post_topics',
			'label' => $txt['recent_post_topics'],
			'options' => [
				0 => $txt['show_recent_posts'],
				1 => $txt['show_recent_topics'],
			],
			'type' => 'number',
		],
		[
			'id' => 'number_recent_posts',
			'label' => $txt['number_recent_posts'],
			'description' => $txt['number_recent_posts_desc'],
			'type' => 'number',
		],
		[
			'id' => 'show_stats_index',
			'label' => $txt['show_stats_index'],
		],
		[
			'id' => 'show_likestats_index',
			'label' => $txt['show_likestats'],
		],
		[
			'id' => 'show_latest_member',
			'label' => $txt['latest_members'],
		],
		[
			'id' => 'show_group_key',
			'label' => $txt['show_group_key'],
		],
		[
			'id' => 'display_who_viewing',
			'label' => $txt['who_display_viewing'],
			'options' => [
				0 => $txt['who_display_viewing_off'],
				1 => $txt['who_display_viewing_numbers'],
				2 => $txt['who_display_viewing_names'],
			],
			'type' => 'number',
		],
		'',
		[
			'id' => 'additional_options_collapsible',
			'label' => $txt['additional_options_collapsible'],
		],
		[
			'id' => 'show_keyinfo_above',
			'label' => $txt['show_keyinfo_above'],
		],
	];

	// This is a special case as theme settings will trigger new ThemeLoader() which essentially clears inline JS
	$context['html_headers'] = '
	<script>
	document.addEventListener("DOMContentLoaded", function() {
		// Hide the option first
		document.getElementById("dt_newsfader_time").style.display = "none";
		document.getElementById("dd_newsfader_time").style.display = "none";
		
		// Update visibility based on the current value
		toggleNewsFaderTime(document.getElementById("enable_news").value);
		
		// Set up the onchange event
		document.getElementById("enable_news").addEventListener("change", function() {
		    toggleNewsFaderTime(this.value);
		});
		
		function toggleNewsFaderTime(value)
		{
		    let dtElem = document.getElementById("dt_newsfader_time"),
		        ddElem = document.getElementById("dd_newsfader_time");
		  
		    if (value === "2")
		    {
		        dtElem.fadeIn(500);
		        ddElem.fadeIn(500);
		    }
		    else
		    {
		        dtElem.fadeOut(500);
		        ddElem.fadeOut(500);
		    }
		}
	});
	</script>';
}
