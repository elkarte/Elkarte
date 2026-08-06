<?php

/**
 * Handles all the administration settings for topics and posts.
 *
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\SettingsForm\SettingsForm;

/**
 * ManagePosts controller handles all the administration settings for topics and posts.
 *
 * @package Posts
 */
class ManagePosts extends AbstractController
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
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		$subActions = [
			'posts' => [$this, 'action_postSettings_display', 'permission' => 'admin_forum'],
			'censor' => [$this, 'action_censor', 'permission' => 'admin_forum'],
			'topics' => ['controller' => ManageTopics::class, 'function' => 'action_index', 'permission' => 'admin_forum'],
			'sig' => ['controller' => ManageSignature::class, 'function' => 'action_index', 'permission' => 'admin_forum'],
		];

		// Good old action handle
		$action = new Action('manage_posts');

		// Default the sub-action to 'posts'. call integrate_sa_manage_posts
		$subAction = $action->initialize($subActions, 'posts');

		// Just for the template
		$context['page_title'] = $txt['manageposts_title'];
		$context['sub_action'] = $subAction;

		// Tabs for browsing the different post-functions.
		$context[$context['admin_menu_name']]['object']->prepareTabData([
				'title' => 'manageposts_title',
				'help' => 'posts_and_topics',
				'description' => 'manageposts_description',
				'tabs' => [
					'posts' => [
						'description' => $txt['manageposts_settings_description'],
					],
					'censor' => [
						'description' => $txt['admin_censored_desc'],
					],
					'topics' => [
						'description' => $txt['manageposts_topic_settings_description'],
					],
					'sig' => [
						'description' => $txt['signature_settings_desc'],
					],
				]]
		);

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
	public function action_censor(): void
	{
		global $txt, $modSettings, $context;

		if ($this->_req->hasPost('save_censor'))
		{
			// Make sure censoring is something they can do.
			checkSession();
			validateToken('admin-censor');

			$censored_vulgar = [];
			$censored_proper = [];

			// Rip it apart, then split it into two arrays.
			if ($this->_req->hasPost('censortext'))
			{
				$censorText = $this->_req->getPost('censortext', 'trim', '');
				$censorText = explode("\n", strtr($censorText, ["\r" => '']));
				foreach ($censorText as $c)
				{
					[$censored_vulgar[], $censored_proper[]] = array_pad(explode('=', trim($c)), 2, '');
				}
			}
			elseif ($this->_req->hasPost('censor_vulgar') && $this->_req->hasPost('censor_proper'))
			{
				$posted_vulgar = $this->_req->getPost('censor_vulgar', null, []);
				if (is_array($posted_vulgar))
				{
					// Work on local copies to avoid mutating the request
					$local_vulgar = (array) $posted_vulgar;
					$local_proper = (array) $this->_req->getPost('censor_proper', null, []);

					foreach ($local_vulgar as $i => $value)
					{
						if (trim(str_replace('*', ' ', $value)) === '')
						{
							unset($local_vulgar[$i], $local_proper[$i]);
						}
					}

					$censored_vulgar = $local_vulgar;
					$censored_proper = $local_proper;
				}
				else
				{
					$vulgar_text = (string) $this->_req->getPost('censor_vulgar', null, '');
					$proper_text = (string) $this->_req->getPost('censor_proper', null, '');
					$censored_vulgar = explode("\n", strtr($vulgar_text, ["\r" => '']));
					$censored_proper = explode("\n", strtr($proper_text, ["\r" => '']));
				}
			}

			// Set the new arrays and settings in the database.
			$updates = [
				'censor_vulgar' => implode("\n", $censored_vulgar),
				'censor_proper' => implode("\n", $censored_proper),
				'censorWholeWord' => empty($this->_req->getPost('censorWholeWord', null, '')) ? '0' : '1',
				'censorIgnoreCase' => empty($this->_req->getPost('censorIgnoreCase', null, '')) ? '0' : '1',
				'allow_no_censored' => empty($this->_req->getPost('allow_no_censored', null, '')) ? '0' : '1',
			];

			call_integration_hook('integrate_save_censors', [&$updates]);

			updateSettings($updates);
		}

		// Testing a word to see how it will be censored?
		$pre_censor = '';
		if ($this->_req->hasPost('censortest'))
		{
			checkSession();

			require_once(SUBSDIR . '/Post.subs.php');
			$raw = (string) $this->_req->getPost('censortest', null, '');
			$censorText = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
			preparsecode($censorText);
			$pre_censor = $censorText;
			$context['censor_test'] = strtr(censor($censorText), ['"' => '&quot;']);
		}

		// Set everything up for the template to do its thang.
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		$context['censored_words'] = [];
		foreach ($censor_vulgar as $i => $censor_vulgar_i)
		{
			if ($censor_vulgar_i === '' || $censor_vulgar_i === '0')
			{
				continue;
			}

			// Skip it, it's either spaces or stars only.
			if (trim(str_replace('*', ' ', $censor_vulgar_i)) === '')
			{
				continue;
			}

			$context['censored_words'][htmlspecialchars(trim($censor_vulgar_i))] = isset($censor_proper[$i])
				? htmlspecialchars($censor_proper[$i], ENT_COMPAT, 'UTF-8')
				: '';
		}

		call_integration_hook('integrate_censors');
		createToken('admin-censor');

		// Using ajax?
		if ($this->_req->hasPost('censortest') && $this->getApi() === 'json')
		{
			// Clear the templates
			setJsonTemplate();

			// Send back a response
			$context['json_data'] = [
				'result' => true,
				'censor' => $pre_censor . ' <i class="icon i-chevron-circle-right"></i> ' . $context['censor_test'],
				'token_val' => $context['admin-censor_token_var'],
				'token' => $context['admin-censor_token'],
			];
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
	public function action_postSettings_display(): void
	{
		global $context, $txt, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		if (!empty($modSettings['nofollow_allowlist']))
		{
			$modSettings['nofollow_allowlist'] = implode("\n", (array) json_decode($modSettings['nofollow_allowlist'], true));
		}

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Set up the template.
		$context['page_title'] = $txt['manageposts_settings'];
		$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if ($this->_req->hasQuery('save'))
		{
			checkSession();
			$db = database();

			// If we're changing the message length (and we are using MySQL), let's check the column is big enough.
			$postedMaxLen = $this->_req->getPost('max_messageLength', 'intval');
			if ($postedMaxLen !== null && $postedMaxLen !== (int) $modSettings['max_messageLength'] && $db->supportMediumtext())
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

				if (isset($body_type) && ($postedMaxLen > 65535 || $postedMaxLen === 0) && $body_type === 'text')
				{
					throw new Exception('convert_to_mediumtext', false, [getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'database'])]);
				}
			}

			// If we're changing the post-preview length, let's check its valid
			$preview_chars = $this->_req->getPost('preview_characters', 'intval');
			if (!empty($preview_chars))
			{
				$preview_chars = min(max(0, $preview_chars), 512);
			}

			// Set a min quote length of 3 lines of text (@ default font size)
			$heightBefore = $this->_req->getPost('heightBeforeShowMore', 'intval');
			if (!empty($heightBefore))
			{
				$heightBefore = max($heightBefore, 155);
			}

			$allowRaw = $this->_req->getPost('nofollow_allowlist', 'trim');
			if ($allowRaw !== null)
			{
				$allowList = array_unique(explode("\n", $allowRaw));
				$allowList = array_filter(array_map('\\ElkArte\\Helper\\Util::htmlspecialchars', array_map('trim', $allowList)));
				$allowRaw = json_encode($allowList);
			}

			call_integration_hook('integrate_save_post_settings');

			$config = (array) $this->_req->post;
			if ($postedMaxLen !== null)
			{
				$config['max_messageLength'] = $postedMaxLen;
			}
			if ($preview_chars !== null)
			{
				$config['preview_characters'] = $preview_chars;
			}
			if ($heightBefore !== null)
			{
				$config['heightBeforeShowMore'] = $heightBefore;
			}
			if ($allowRaw !== null)
			{
				$config['nofollow_allowlist'] = $allowRaw;
			}

			$settingsForm->setConfigValues($config);
			$settingsForm->save();
			redirectexit('action=admin;area=postsettings;sa=posts');
		}

		// Final settings...
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'postsettings', 'save', 'sa' => 'posts']);
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
		$config_vars = [
			// Quote options...
			['int', 'removeNestedQuotes', 'postinput' => $txt['zero_for_none']],
			['int', 'heightBeforeShowMore', 'postinput' => $txt['zero_to_disable']],
			'',
			// Video options
			['check', 'enableVideoEmbeding'],
			['int', 'video_embed_limit', 'postinput' => $txt['video_embed_limit_note']],
			'',
			// Posting limits...
			['int', 'max_messageLength', 'subtext' => $txt['max_messageLength_zero'], 'postinput' => $txt['manageposts_characters']],
			['int', 'topicSummaryPosts', 'postinput' => $txt['manageposts_posts']],
			'',
			// Posting time limits...
			['int', 'spamWaitTime', 'postinput' => $txt['manageposts_seconds']],
			['int', 'edit_wait_time', 'postinput' => $txt['manageposts_seconds']],
			['int', 'edit_disable_time', 'subtext' => $txt['edit_disable_time_zero'], 'postinput' => $txt['manageposts_minutes']],
			['check', 'show_modify'],
			'',
			['check', 'show_user_images'],
			['check', 'hide_post_group'],
			'',
			// First & Last message preview lengths
			['select', 'message_index_preview', [$txt['message_index_preview_off'], $txt['message_index_preview_first'], $txt['message_index_preview_last']]],
			['int', 'preview_characters', 'subtext' => $txt['preview_characters_zero'], 'postinput' => $txt['preview_characters_units']],
			// Misc
			['title', 'mods_cat_modifications_misc'],
			['check', 'enableCodePrettify'],
			['check', 'autoLinkUrls'],
			['large_text', 'nofollow_allowlist', 'subtext' => $txt['nofollow_allowlist_desc']],
			['check', 'enablePostHTML'],
			['check', 'enablePostMarkdown'],
		];

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_post_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Return the post-settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
