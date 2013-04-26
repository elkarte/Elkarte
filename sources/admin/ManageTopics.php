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
	 * Topic settings form
	 * @var Settings_Form
	 */
	protected $_topicSettings;

	public function action_index()
	{
		// Only admins are allowed around here.
		isAllowedTo('admin_forum');

		$subActions = array(
			'display' => array (
				'controller' => $this,
				'function' => 'action_topicSettings_display',
				'default' => true));

		$subAction = 'display';

		// Set up action/subaction stuff.
		$action = new Action();
		$action->initialize($subActions);

		// lets just do it!
		$action->dispatch($subAction);
	}

	/**
	 * Adminstration page for topics: allows to display and set settings related to topics.
	 *
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=topics.

	 * @uses Admin template, edit_topic_settings sub-template.
	 */
	public function action_topicSettings_display()
	{
		global $context, $txt, $scripturl;

		// initialize the form
		$this->_initTopicSettingsForm();

		// retrieve the current config settings
		$config_vars = $this->_topicSettings->settings();

		call_integration_hook('integrate_modify_topic_settings');

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
			Settings_Form::save_db($config_vars);

			// We're done here, pal.
			redirectexit('action=admin;area=postsettings;sa=topics');
		}

		// Set up the template stuff nicely.
		$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=topics';
		$context['settings_title'] = $txt['manageposts_topic_settings'];

		// Prepare the settings
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize topicSettings form with the configuration settings for topics.
	 */
	private function _initTopicSettingsForm()
	{
		global $txt;

		// we're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// instantiate the form
		$this->_topicSettings = new Settings_Form();

		// initialize it with our settings
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

		return $this->_topicSettings->settings($config_vars);
	}

	/**
	 * Return configuration settings for topics.
	 */
	public function settings()
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