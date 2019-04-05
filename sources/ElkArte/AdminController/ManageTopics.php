<?php

/**
 * Handles all the administration settings for topics and posts
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

/**
 * ManagePosts controller handles all the administration settings for topics and posts.
 */
class ManageTopics extends \ElkArte\AbstractController
{
	/**
	 * Check permissions and forward to the right method.
	 *
	 * @event integrate_sa_manage_topics
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		$subActions = array(
			'display' => array(
				'controller' => $this,
				'function' => 'action_topicSettings_display',
				'permission' => 'admin_forum')
		);

		// Control for an action, why not!
		$action = new \ElkArte\Action('manage_topics');

		// Only one option I'm afraid, but integrate_sa_manage_topics may add more
		$subAction = $action->initialize($subActions, 'display');

		// Page items for the template
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['manageposts_topic_settings'];

		// Set up action/subaction stuff.
		$action->dispatch($subAction);
	}

	/**
	 * Administration page for topics: allows to display and set settings related to topics.
	 *
	 * What it does:
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=postsettings;sa=topics.
	 *
	 * @event integrate_save_topic_settings
	 * @uses Admin template, edit_topic_settings sub-template.
	 */
	public function action_topicSettings_display()
	{
		global $context, $txt, $scripturl;

		// Initialize the form
		$settingsForm = new \ElkArte\SettingsForm\SettingsForm(\ElkArte\SettingsForm\SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Setup the template.
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($this->_req->query->save))
		{
			// Security checks
			checkSession();

			// Notify addons and integrations of the settings change.
			call_integration_hook('integrate_save_topic_settings');

			// Save the result!
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// We're done here, pal.
			redirectexit('action=admin;area=postsettings;sa=topics');
		}

		// Set up the template stuff nicely.
		$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=topics';
		$context['settings_title'] = $txt['manageposts_topic_settings'];

		// Prepare the settings
		$settingsForm->prepare();
	}

	/**
	 * Return configuration settings for topics.
	 *
	 * @event integrate_modify_topic_settings
	 */
	private function _settings()
	{
		global $txt;

		// initialize it with our settings
		$config_vars = array(
				// Some simple big bools...
				array('check', 'enableParticipation'),
				array('check', 'enableFollowup'),
				array('check', 'enable_unwatch'),
				array('check', 'pollMode'),
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
				array('check', 'useLikesNotViews'),
			'',
				// All, next/prev...
				array('int', 'enableAllMessages', 'postinput' => $txt['manageposts_posts'], 'subtext' => $txt['enableAllMessages_zero']),
				array('check', 'disableCustomPerPage'),
				array('check', 'enablePreviousNext'),
		);

		call_integration_hook('integrate_modify_topic_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the topic settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
