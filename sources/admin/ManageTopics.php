<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * ManagePosts controller handles all the administration settings for topics and posts.
 */
class ManageTopics_Controller
{
	/**
	 * Adminstration page for topics: allows to display and set settings related to topics.
	 *
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=topics.

	 * @uses Admin template, edit_topic_settings sub-template.
	 */
	function action_settings()
	{
		global $context, $txt, $modSettings, $scripturl;

		// retrieve the current config settings
		$config_vars = $this->settings();

		call_integration_hook('integrate_modify_topic_settings', array(&$config_vars));

		// Get the settings ready.
		require_once(SUBSDIR . '/Settings.php');

		// Setup the template.
		$context['page_title'] = $txt['manageposts_topic_settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($_GET['save']))
		{
			// Security checks
			checkSession();

			// Notify add-ons and integrations of the settings change.
			call_integration_hook('integrate_save_topic_settings', array(&$config_vars));

			// Save the result!
			Settings_Form::saveDBSettings($config_vars);

			// We're done here, pal.
			redirectexit('action=admin;area=postsettings;sa=topics');
		}

		// Set up the template stuff nicely.
		$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=topics';
		$context['settings_title'] = $txt['manageposts_topic_settings'];

		// Prepare the settings
		Settings_Form::prepareDBSettingContext($config_vars);
	}

	/**
	 * Return configuration settings for topics.
	 */
	function settings()
	{
		global $txt;

		// Here are all the topic settings.
		$config_vars = array(
				// Some simple bools...
				array('check', 'enableStickyTopics'),
				array('check', 'enableParticipation'),
			'',
				// Pagination etc...
				array('int', 'oldTopicDays', 'postinput' => $txt['manageposts_days'], 'subtext' => $txt['oldTopicDays_zero']),
				array('int', 'defaultMaxTopics', 'postinput' => $txt['manageposts_topics']),
				array('int', 'defaultMaxMessages', 'postinput' => $txt['manageposts_posts']),
				array('check', 'disable_print_topic'),
			'',
				// Hot topics (etc)...
				array('int', 'hotTopicPosts', 'postinput' => $txt['manageposts_posts']),
				array('int', 'hotTopicVeryPosts', 'postinput' => $txt['manageposts_posts']),
			'',
				// All, next/prev...
				array('int', 'enableAllMessages', 'postinput' => $txt['manageposts_posts'], 'subtext' => $txt['enableAllMessages_zero']),
				array('check', 'disableCustomPerPage'),
				array('check', 'enablePreviousNext'),

		);

		return $config_vars;
	}
}
