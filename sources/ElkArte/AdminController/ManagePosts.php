<?php

/**
 * Handles all the administration settings for topics and posts.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

/**
 * ManagePosts controller handles all the administration settings for topics and posts.
 *
 * @package Posts
 */
class ManagePosts extends \ElkArte\AbstractController
{
	/**
	 * The main entrance point for the 'Posts and topics' screen.
	 *
	 * What it does:
	 *
	 * - Like all others, it checks permissions, then forwards to the right function
	 * based on the given sub-action.
	 * - Defaults to sub-action 'posts'.
	 * - Accessed from ?action=admin;area=postsettings.
	 * - Requires (and checks for) the admin_forum permission.
	 *
	 * @event integrate_sa_manage_posts used to add new subactions
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		$subActions = array(
			'posts' => array(
				$this, 'action_postSettings_display', 'permission' => 'admin_forum'),
			'bbc' => array(
				'function' => 'action_index',
				'controller' => '\\ElkArte\\admin\\ManageBBC',
				'permission' => 'admin_forum'),
			'censor' => array(
				$this, 'action_censor', 'permission' => 'admin_forum'),
			'topics' => array(
				'function' => 'action_index',
				'controller' => '\\ElkArte\\admin\\ManageTopics',
				'permission' => 'admin_forum'),
		);

		// Good old action handle
		$action = new \ElkArte\Action('manage_posts');

		// Tabs for browsing the different post functions.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['manageposts_title'],
			'help' => 'posts_and_topics',
			'description' => $txt['manageposts_description'],
			'tabs' => array(
				'posts' => array(
					'description' => $txt['manageposts_settings_description'],
				),
				'bbc' => array(
					'description' => $txt['manageposts_bbc_settings_description'],
				),
				'censor' => array(
					'description' => $txt['admin_censored_desc'],
				),
				'topics' => array(
					'description' => $txt['manageposts_topic_settings_description'],
				),
			),
		);

		// Default the sub-action to 'posts'. call integrate_sa_manage_posts
		$subAction = $action->initialize($subActions, 'posts');

		// Just for the template
		$context['page_title'] = $txt['manageposts_title'];
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Shows an interface to set and test censored words.
	 *
	 * - It uses the censor_vulgar, censor_proper, censorWholeWord, and
	 * censorIgnoreCase settings.
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=postsettings;sa=censor.
	 *
	 * @event integrate_save_censors
	 * @event integrate_censors
	 * @uses the Admin template and the edit_censored sub template.
	 */
	public function action_censor()
	{
		global $txt, $modSettings, $context;

		if (!empty($this->_req->post->save_censor))
		{
			// Make sure censoring is something they can do.
			checkSession();
			validateToken('admin-censor');

			$censored_vulgar = array();
			$censored_proper = array();

			// Rip it apart, then split it into two arrays.
			if (isset($this->_req->post->censortext))
			{
				$this->_req->post->censortext = explode("\n", strtr($this->_req->post->censortext, array("\r" => '')));

				foreach ($this->_req->post->censortext as $c)
				{
					list ($censored_vulgar[], $censored_proper[]) = array_pad(explode('=', trim($c)), 2, '');
				}
			}
			elseif (isset($this->_req->post->censor_vulgar, $this->_req->post->censor_proper))
			{
				if (is_array($this->_req->post->censor_vulgar))
				{
					foreach ($this->_req->post->censor_vulgar as $i => $value)
					{
						if (trim(strtr($value, '*', ' ')) === '')
						{
							unset($this->_req->post->censor_vulgar[$i], $this->_req->post->censor_proper[$i]);
						}
					}

					$censored_vulgar = $this->_req->post->censor_vulgar;
					$censored_proper = $this->_req->post->censor_proper;
				}
				else
				{
					$censored_vulgar = explode("\n", strtr($this->_req->post->censor_vulgar, array("\r" => '')));
					$censored_proper = explode("\n", strtr($this->_req->post->censor_proper, array("\r" => '')));
				}
			}

			// Set the new arrays and settings in the database.
			$updates = array(
				'censor_vulgar' => implode("\n", $censored_vulgar),
				'censor_proper' => implode("\n", $censored_proper),
				'censorWholeWord' => empty($this->_req->post->censorWholeWord) ? '0' : '1',
				'censorIgnoreCase' => empty($this->_req->post->censorIgnoreCase) ? '0' : '1',
				'allow_no_censored' => empty($this->_req->post->allow_no_censored) ? '0' : '1',
			);

			call_integration_hook('integrate_save_censors', array(&$updates));

			updateSettings($updates);
		}

		// Testing a word to see how it will be censored?
		$pre_censor = '';
		if (isset($this->_req->post->censortest))
		{
			require_once(SUBSDIR . '/Post.subs.php');
			$censorText = htmlspecialchars($this->_req->post->censortest, ENT_QUOTES, 'UTF-8');
			preparsecode($censorText);
			$pre_censor = $censorText;
			$context['censor_test'] = strtr(censor($censorText), array('"' => '&quot;'));
		}

		// Set everything up for the template to do its thang.
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		$context['censored_words'] = array();
		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
		{
			if (empty($censor_vulgar[$i]))
			{
				continue;
			}

			// Skip it, it's either spaces or stars only.
			if (trim(strtr($censor_vulgar[$i], '*', ' ')) === '')
			{
				continue;
			}

			$context['censored_words'][htmlspecialchars(trim($censor_vulgar[$i]))] = isset($censor_proper[$i])
				? htmlspecialchars($censor_proper[$i], ENT_COMPAT, 'UTF-8')
				: '';
		}

		call_integration_hook('integrate_censors');
		createToken('admin-censor');

		// Using ajax?
		if (isset($this->_req->query->xml, $this->_req->post->censortest))
		{
			// Clear the templates
			$template_layers = theme()->getLayers();
			$template_layers->removeAll();

			// Send back a response
			theme()->getTemplates()->load('Json');
			$context['sub_template'] = 'send_json';
			$context['json_data'] = array(
				'result' => true,
				'censor' => $pre_censor . ' <i class="icon i-chevron-circle-right"></i> ' . $context['censor_test'],
				'token_val' => $context['admin-censor_token_var'],
				'token' => $context['admin-censor_token'],
			);
		}
		else
		{
			$context['sub_template'] = 'edit_censored';
			$context['page_title'] = $txt['admin_censored_words'];
		}
	}

	/**
	 * Modify any setting related to posts and posting.
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=postsettings;sa=posts.
	 *
	 * @event integrate_save_post_settings
	 * @uses Admin template, edit_post_settings sub-template.
	 */
	public function action_postSettings_display()
	{
		global $context, $txt, $modSettings, $scripturl;

		// Initialize the form
		$settingsForm = new \ElkArte\SettingsForm(\ElkArte\SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Setup the template.
		$context['page_title'] = $txt['manageposts_settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($this->_req->query->save))
		{
			checkSession();

			// If we're changing the message length (and we are using MySQL) let's check the column is big enough.
			if (isset($this->_req->post->max_messageLength) && $this->_req->post->max_messageLength != $modSettings['max_messageLength'] && DB_TYPE === 'MySQL')
			{
				require_once(SUBSDIR . '/Maintenance.subs.php');
				$colData = getMessageTableColumns();
				foreach ($colData as $column)
				{
					if ($column['name'] === 'body')
					{
						$body_type = $column['type'];
					}
				}

				if (isset($body_type) && ($this->_req->post->max_messageLength > 65535 || $this->_req->post->max_messageLength == 0) && $body_type === 'text')
				{
					throw new \ElkArte\Exceptions\Exception('convert_to_mediumtext', false, array($scripturl . '?action=admin;area=maintain;sa=database'));
				}

			}

			// If we're changing the post preview length let's check its valid
			if (!empty($this->_req->post->preview_characters))
			{
				$this->_req->post->preview_characters = (int) min(max(0, $this->_req->post->preview_characters), 512);
			}

			call_integration_hook('integrate_save_post_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=postsettings;sa=posts');
		}

		// Final settings...
		$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=posts';
		$context['settings_title'] = $txt['manageposts_settings'];

		// Prepare the settings...
		$settingsForm->prepare();
	}

	/**
	 * Return admin configuration settings for posts.
	 *
	 * @event integrate_modify_post_settings
	 */
	private function _settings()
	{
		global $txt;

		// Initialize it with our settings
		$config_vars = array(
			// Simple post options...
			array('check', 'removeNestedQuotes'),
			array('check', 'enableVideoEmbeding'),
			array('check', 'enableCodePrettify'),
			// Note show the warning as read if pspell not installed!
			array('check', 'enableSpellChecking', 'postinput' => (function_exists('pspell_new') ? $txt['enableSpellChecking_warning'] : '<span class="error">' . $txt['enableSpellChecking_error'] . '</span>')),
			'',
			// Posting limits...
			array('int', 'max_messageLength', 'subtext' => $txt['max_messageLength_zero'], 'postinput' => $txt['manageposts_characters']),
			array('int', 'topicSummaryPosts', 'postinput' => $txt['manageposts_posts']),
			'',
			// Posting time limits...
			array('int', 'spamWaitTime', 'postinput' => $txt['manageposts_seconds']),
			array('int', 'edit_wait_time', 'postinput' => $txt['manageposts_seconds']),
			array('int', 'edit_disable_time', 'subtext' => $txt['edit_disable_time_zero'], 'postinput' => $txt['manageposts_minutes']),
			'',
			// First & Last message preview lengths
			array('select', 'message_index_preview', array($txt['message_index_preview_off'], $txt['message_index_preview_first'], $txt['message_index_preview_last'])),
			array('int', 'preview_characters', 'subtext' => $txt['preview_characters_zero'], 'postinput' => $txt['preview_characters_units']),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_post_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the post settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
