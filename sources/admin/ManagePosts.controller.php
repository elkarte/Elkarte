<?php

/**
 * Handles all the administration settings for topics and posts.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManagePosts controller handles all the administration settings
 *  for topics and posts.
 */
class ManagePosts_Controller extends Action_Controller
{
	/**
	 * Posts settings form
	 * @var Settings_Form
	 */
	protected $_postSettings;

	/**
	 * The main entrance point for the 'Posts and topics' screen.
	 * Like all others, it checks permissions, then forwards to the right function
	 * based on the given sub-action.
	 * Defaults to sub-action 'posts'.
	 *
	 * Accessed from ?action=admin;area=postsettings.
	 * Requires (and checks for) the admin_forum permission.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		$subActions = array(
			'posts' => array(
				$this, 'action_postSettings_display', 'permission' => 'admin_forum'),
			'bbc' => array(
				'function' => 'action_index',
				'file' => 'ManageBBC.controller.php',
				'controller' => 'ManageBBC_Controller',
				'permission' => 'admin_forum'),
			'censor' => array(
				$this, 'action_censor', 'permission' => 'admin_forum'),
			'topics' => array(
				'function' => 'action_index',
				'file' => 'ManageTopics.controller.php',
				'controller' => 'ManageTopics_Controller',
				'permission' => 'admin_forum'),
		);

		call_integration_hook('integrate_manage_posts', array(&$subActions));

		// Default the sub-action to 'posts'.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'posts';

		$context['page_title'] = $txt['manageposts_title'];
		$context['sub_action'] = $subAction;

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

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions, 'posts');
		$action->dispatch($subAction);
	}

	/**
	 * Shows an interface to set and test censored words.
	 * It uses the censor_vulgar, censor_proper, censorWholeWord, and censorIgnoreCase
	 * settings.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=censor.
	 *
	 * @uses the Admin template and the edit_censored sub template.
	 */
	public function action_censor()
	{
		global $txt, $modSettings, $context;

		if (!empty($_POST['save_censor']))
		{
			// Make sure censoring is something they can do.
			checkSession();
			validateToken('admin-censor');

			$censored_vulgar = array();
			$censored_proper = array();

			// Rip it apart, then split it into two arrays.
			if (isset($_POST['censortext']))
			{
				$_POST['censortext'] = explode("\n", strtr($_POST['censortext'], array("\r" => '')));

				foreach ($_POST['censortext'] as $c)
					list ($censored_vulgar[], $censored_proper[]) = array_pad(explode('=', trim($c)), 2, '');
			}
			elseif (isset($_POST['censor_vulgar'], $_POST['censor_proper']))
			{
				if (is_array($_POST['censor_vulgar']))
				{
					foreach ($_POST['censor_vulgar'] as $i => $value)
					{
						if (trim(strtr($value, '*', ' ')) == '')
							unset($_POST['censor_vulgar'][$i], $_POST['censor_proper'][$i]);
					}

					$censored_vulgar = $_POST['censor_vulgar'];
					$censored_proper = $_POST['censor_proper'];
				}
				else
				{
					$censored_vulgar = explode("\n", strtr($_POST['censor_vulgar'], array("\r" => '')));
					$censored_proper = explode("\n", strtr($_POST['censor_proper'], array("\r" => '')));
				}
			}

			// Set the new arrays and settings in the database.
			$updates = array(
				'censor_vulgar' => implode("\n", $censored_vulgar),
				'censor_proper' => implode("\n", $censored_proper),
				'censorWholeWord' => empty($_POST['censorWholeWord']) ? '0' : '1',
				'censorIgnoreCase' => empty($_POST['censorIgnoreCase']) ? '0' : '1',
			);

			call_integration_hook('integrate_save_censors', array(&$updates));

			updateSettings($updates);
		}

		if (isset($_POST['censortest']))
		{
			require_once(SUBSDIR . '/Post.subs.php');
			$censorText = htmlspecialchars($_POST['censortest'], ENT_QUOTES, 'UTF-8');
			preparsecode($censorText);
			$context['censor_test'] = strtr(censorText($censorText), array('"' => '&quot;'));
		}

		// Set everything up for the template to do its thang.
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		$context['censored_words'] = array();
		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
		{
			if (empty($censor_vulgar[$i]))
				continue;

			// Skip it, it's either spaces or stars only.
			if (trim(strtr($censor_vulgar[$i], '*', ' ')) == '')
				continue;

			$context['censored_words'][htmlspecialchars(trim($censor_vulgar[$i]))] = isset($censor_proper[$i]) ? htmlspecialchars($censor_proper[$i], ENT_COMPAT, 'UTF-8') : '';
		}

		call_integration_hook('integrate_censors');

		$context['sub_template'] = 'edit_censored';
		$context['page_title'] = $txt['admin_censored_words'];

		createToken('admin-censor');
	}

	/**
	 * Modify any setting related to posts and posting.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=posts.
	 *
	 * @uses Admin template, edit_post_settings sub-template.
	 */
	public function action_postSettings_display()
	{
		global $context, $txt, $modSettings, $scripturl, $db_type;

		// Initialize the form
		$this->_initPostSettingsForm();

		$config_vars = $this->_postSettings->settings();

		call_integration_hook('integrate_modify_post_settings');

		// Setup the template.
		$context['page_title'] = $txt['manageposts_settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($_GET['save']))
		{
			checkSession();

			// If we're changing the message length (and we are using MySQL) let's check the column is big enough.
			if (isset($_POST['max_messageLength']) && $_POST['max_messageLength'] != $modSettings['max_messageLength'] && $db_type == 'mysql')
			{
				require_once(SUBSDIR . '/Maintenance.subs.php');
				$colData = getMessageTableColumns();
				foreach ($colData as $column)
					if ($column['name'] == 'body')
						$body_type = $column['type'];

				if (isset($body_type) && ($_POST['max_messageLength'] > 65535 || $_POST['max_messageLength'] == 0) && $body_type == 'text')
					fatal_lang_error('convert_to_mediumtext', false, array($scripturl . '?action=admin;area=maintain;sa=database'));

			}

			// If we're changing the post preview length let's check its valid
			if (!empty($_POST['preview_characters']))
				$_POST['preview_characters'] = (int) min(max(0, $_POST['preview_characters']), 512);

			call_integration_hook('integrate_save_post_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=postsettings;sa=posts');
		}

		// Final settings...
		$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=posts';
		$context['settings_title'] = $txt['manageposts_settings'];

		// Prepare the settings...
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize postSettings form with admin configuration settings for posts.
	 */
	private function _initPostSettingsForm()
	{
		// Instantiate the form
		$this->_postSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_postSettings->settings($config_vars);
	}

	/**
	 * Return admin configuration settings for posts.
	 */
	private function _settings()
	{
		global $txt;

		// Initialize it with our settings
		$config_vars = array(
				// Simple post options...
				array('check', 'removeNestedQuotes'),
				array('check', 'enableEmbeddedFlash', 'subtext' => $txt['enableEmbeddedFlash_warning']),
				array('check', 'enableVideoEmbeding'),
				array('check', 'enableCodePrettify'),
				// Note show the warning as read if pspell not installed!
				array('check', 'enableSpellChecking', 'subtext' => (function_exists('pspell_new') ? $txt['enableSpellChecking_warning'] : '<span class="error">' . $txt['enableSpellChecking_error'] . '</span>')),
				array('check', 'disable_wysiwyg'),
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
				array('int', 'preview_characters', 'subtext' => $txt['preview_characters_zero'], 'postinput' => $txt['preview_characters_units']),
		);

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