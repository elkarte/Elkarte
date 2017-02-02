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
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Set the user theme options.
 */
function template_options()
{
	global $context, $txt;

	$context['theme_options'] = array(
		array(
			'id' => 'use_sidebar_menu',
			'label' => $txt['use_sidebar_menu'],
			'default' => true,
		),
		array(
			'id' => 'show_no_avatars',
			'label' => $txt['show_no_avatars'],
			'default' => true,
		),
		array(
			'id' => 'hide_poster_area',
			'label' => $txt['hide_poster_area'],
			'default' => true,
		),
		array(
			'id' => 'show_no_signatures',
			'label' => $txt['show_no_signatures'],
			'default' => true,
		),
		array(
			'id' => 'return_to_post',
			'label' => $txt['return_to_post'],
			'default' => true,
		),
		array(
			'id' => 'no_new_reply_warning',
			'label' => $txt['no_new_reply_warning'],
			'default' => true,
		),
		array(
			'id' => 'view_newest_pm_first',
			'label' => $txt['recent_pms_at_top'],
			'default' => true,
		),
		array(
			'id' => 'wysiwyg_default',
			'label' => $txt['wysiwyg_default'],
			'default' => false,
		),
		array(
			'id' => 'popup_messages',
			'label' => $txt['popup_messages'],
			'default' => true,
		),
		array(
			'id' => 'pm_remove_inbox_label',
			'label' => $txt['pm_remove_inbox_label'],
			'default' => true,
		),
		array(
			'id' => 'auto_notify',
			'label' => $txt['auto_notify'],
			'default' => true,
		),
		array(
			'id' => 'topics_per_page',
			'label' => $txt['topics_per_page'],
			'options' => array(
				0 => $txt['per_page_default'],
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			),
			'default' => true,
		),
		array(
			'id' => 'messages_per_page',
			'label' => $txt['messages_per_page'],
			'options' => array(
				0 => $txt['per_page_default'],
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			),
			'default' => true,
		),
		array(
			'id' => 'calendar_start_day',
			'label' => $txt['calendar_start_day'],
			'options' => array(
				0 => $txt['days'][0],
				1 => $txt['days'][1],
				6 => $txt['days'][6],
			),
			'default' => true,
		),
		array(
			'id' => 'display_quick_reply',
			'label' => $txt['display_quick_reply'],
			'default' => true,
		),
		array(
			'id' => 'use_editor_quick_reply',
			'label' => $txt['use_editor_quick_reply'],
			'default' => true,
		),
		array(
			'id' => 'display_quick_mod',
			'label' => $txt['display_quick_mod'],
			'options' => array(
				0 => $txt['display_quick_mod_none'],
				1 => $txt['display_quick_mod_check'],
				2 => $txt['display_quick_mod_image'],
			),
			'default' => true,
		),
	);
}

/**
 * Set the theme settings for display and edit in admin panel.
 */
function template_settings()
{
	global $context, $txt;

	$context['theme_settings'] = array(
		array(
			'id' => 'header_logo_url',
			'label' => $txt['header_logo_url'],
			'description' => $txt['header_logo_url_desc'],
			'type' => 'text',
		),
		array(
			'id' => 'site_slogan',
			'label' => $txt['site_slogan'],
			'description' => $txt['site_slogan_desc'],
			'type' => 'text',
		),
		array(
			'id' => 'header_layout',
			'label' => $txt['header_layout'],
			'options' => array(
				0 => $txt['header_layout_default'],
				1 => $txt['header_layout_logo_only'],
				2 => $txt['header_layout_inverted'],
			),
			'description' => array(
				'main' => $txt['header_layout_desc'],
				'options' => array(
					0 => array('header_layout_default_name', 'header_layout_default_desc'),
					1 => array('header_layout_logo_only_name', 'header_layout_logo_only_desc'),
					2 => array('header_layout_inverted_name', 'header_layout_inverted_desc'),
				)
			),
			'type' => 'select',
		),
		array(
			'id' => 'smiley_sets_default',
			'label' => $txt['smileys_default_set_for_theme'],
			'options' => $context['smiley_sets'],
			'type' => 'text',
		),
		array(
			'id' => 'forum_width',
			'label' => $txt['forum_width'],
			'description' => $txt['forum_width_desc'],
			'type' => 'text',
			'size' => 8,
		),
		'',
		array(
			'id' => 'linktree_link',
			'label' => $txt['current_pos_text_img'],
		),
		array(
			'id' => 'show_mark_read',
			'label' => $txt['enable_mark_as_read'],
		),
		array(
			'id' => 'enable_news',
			'label' => $txt['enable_news'],
			'options' => array(
				0 => $txt['enable_news_off'],
				1 => $txt['enable_news_random'],
				2 => $txt['enable_news_fader'],
			),
			'type' => 'number',
			'description' => array(
				'main' => '',
				'options' => array(
					0 => array('enable_news_off_name', 'enable_news_off_desc'),
					1 => array('enable_news_random_name', 'enable_news_random_desc'),
					2 => array('enable_news_fader_name', 'enable_news_fader_desc'),
				)
			),
		),
		array(
			'id' => 'newsfader_time',
			'label' => $txt['admin_fader_delay'],
			'type' => 'number',
		),
		'',
		array(
			'id' => 'recent_post_topics',
			'label' => $txt['recent_post_topics'],
			'options' => array(
				0 => $txt['show_recent_posts'],
				1 => $txt['show_recent_topics'],
			),
			'type' => 'number',
		),
		array(
			'id' => 'number_recent_posts',
			'label' => $txt['number_recent_posts'],
			'description' => $txt['number_recent_posts_desc'],
			'type' => 'number',
		),
		array(
			'id' => 'show_stats_index',
			'label' => $txt['show_stats_index'],
		),
		array(
			'id' => 'show_latest_member',
			'label' => $txt['latest_members'],
		),
		array(
			'id' => 'show_group_key',
			'label' => $txt['show_group_key'],
		),
		array(
			'id' => 'display_who_viewing',
			'label' => $txt['who_display_viewing'],
			'options' => array(
				0 => $txt['who_display_viewing_off'],
				1 => $txt['who_display_viewing_numbers'],
				2 => $txt['who_display_viewing_names'],
			),
			'type' => 'number',
		),
		'',
		array(
			'id' => 'show_modify',
			'label' => $txt['last_modification'],
		),
		array(
			'id' => 'show_profile_buttons',
			'label' => $txt['show_view_profile_button'],
		),
		array(
			'id' => 'show_user_images',
			'label' => $txt['user_avatars'],
		),
		array(
			'id' => 'hide_post_group',
			'label' => $txt['hide_post_group'],
			'description' => $txt['hide_post_group_desc'],
		),
		'',
		array(
			'id' => 'show_bbc',
			'label' => $txt['admin_bbc'],
		),
		array(
			'id' => 'additional_options_collapsible',
			'label' => $txt['additional_options_collapsible'],
		),
	);

	addInlineJavascript('
		// Hide the option first
		$("#dt_newsfader_time, #dd_newsfader_time").hide();

		// Update visablity based on the select value
		toggleNewsFaderTime($("#enable_news").val());

		// Set up the onchange event
		$("#enable_news").on("change", function() {
			toggleNewsFaderTime($(this).val());
		});

		function toggleNewsFaderTime(val)
		{
			if (val == 2)
				$("#dt_newsfader_time, #dd_newsfader_time").fadeIn();
			else
				$("#dt_newsfader_time, #dd_newsfader_time").fadeOut();
		}', true);
}