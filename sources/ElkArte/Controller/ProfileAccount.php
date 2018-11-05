<?php

/**
 * Handles actions made against a user's profile.
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

namespace ElkArte\Controller;

/**
 * Processes user warnings, account activation and account deletion
 */
class ProfileAccount extends \ElkArte\AbstractController
{
	/**
	 * Member id for the account being worked on
	 * @var int
	 */
	private $_memID = 0;

	/**
	 * The array from $user_profile stored here to avoid some global
	 * @var mixed[]
	 */
	private $_profile = [];

	/**
	 * Holds any errors that were generated when issuing a warning
	 * @var array
	 */
	private $_issueErrors = array();

	/**
	 * Called before all other methods when coming from the dispatcher or
	 * action class.
	 */
	public function pre_dispatch()
	{
		global $user_profile;

		$this->_memID = currentMemberID();
		$this->_profile = $user_profile[$this->_memID];
	}

	/**
	 * Entry point for this class.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// figure out what action to do... if we're called directly
		// actions in this class are called from the Profile menu, though.
	}

	/**
	 * Issue/manage an user's warning status.
	 *
	 * @uses template_issueWarning sub template in ProfileAccount
	 * @uses Profile template
	 */
	public function action_issuewarning()
	{
		global $txt, $scripturl, $modSettings, $mbname, $context, $cur_profile;

		// Make sure the sub-template is set...
		theme()->getTemplates()->load('ProfileAccount');
		$context['sub_template'] = 'issueWarning';

		// We need this because of template_load_warning_variables
		theme()->getTemplates()->load('Profile');
		loadJavascriptFile('profile.js');

		// jQuery-UI FTW!
		$modSettings['jquery_include_ui'] = true;
		loadCSSFile('jquery.ui.slider.css');
		loadCSSFile('jquery.ui.theme.min.css');

		// Get all the actual settings.
		list ($modSettings['warning_enable'], $modSettings['user_limit']) = explode(',', $modSettings['warning_settings']);

		// Doesn't hurt to be overly cautious.
		if (empty($modSettings['warning_enable'])
			|| ($context['user']['is_owner'] && !$cur_profile['warning'])
			|| !allowedTo('issue_warning'))
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		// Get the base (errors related) stuff done.
		theme()->getTemplates()->loadLanguageFile('Errors');
		$context['custom_error_title'] = $txt['profile_warning_errors_occurred'];

		// Make sure things which are disabled stay disabled.
		$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
		$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) && !empty($modSettings['postmod_active']) ? $modSettings['warning_moderate'] : 110;
		$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

		$context['warning_limit'] = allowedTo('admin_forum') ? 0 : $modSettings['user_limit'];
		$context['member']['warning'] = $cur_profile['warning'];
		$context['member']['name'] = $cur_profile['real_name'];

		// What are the limits we can apply?
		$context['min_allowed'] = 0;
		$context['max_allowed'] = 100;
		if ($context['warning_limit'] > 0)
		{
			require_once(SUBSDIR . '/Moderation.subs.php');
			$current_applied = warningDailyLimit($this->_memID);

			$context['min_allowed'] = max(0, $cur_profile['warning'] - $current_applied - $context['warning_limit']);
			$context['max_allowed'] = min(100, $cur_profile['warning'] - $current_applied + $context['warning_limit']);
		}

		// Defaults.
		$context['warning_data'] = array(
			'reason' => '',
			'notify' => '',
			'notify_subject' => '',
			'notify_body' => '',
		);

		// Are we saving?
		$this->_save_warning();

		// Perhaps taking a look first? Good idea that one.
		$this->_preview_warning();

		// If we have errors, lets set them for the template
		if (!empty($this->_issueErrors))
		{
			// Fill in the suite of errors.
			$context['post_errors'] = array();
			foreach ($this->_issueErrors as $error)
				$context['post_errors'][] = $txt[$error];
		}

		$context['page_title'] = $txt['profile_issue_warning'];

		// Let's use a generic list to get all the current warnings
		require_once(SUBSDIR . '/Profile.subs.php');

		// Work our the various levels.
		$context['level_effects'] = array(
			0 => $txt['profile_warning_effect_none'],
		);

		foreach (array('watch', 'moderate', 'mute') as $status)
		{
			if ($modSettings['warning_' . $status] != 110)
			{
				$context['level_effects'][$modSettings['warning_' . $status]] = $txt['profile_warning_effect_' . $status];
			}
		}
		$context['current_level'] = 0;

		foreach ($context['level_effects'] as $limit => $dummy)
		{
			if ($context['member']['warning'] >= $limit)
				$context['current_level'] = $limit;
		}

		// Build a listing to view the previous warnings for this user
		$this->_create_issued_warnings_list();

		$warning_for_message = $this->_req->getQuery('msg', 'intval', false);
		$warned_message_subject = '';

		// Are they warning because of a message?
		if (!empty($warning_for_message) && $warning_for_message > 0)
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$message = basicMessageInfo($warning_for_message);

			if (!empty($message))
				$warned_message_subject = $message['subject'];
		}

		require_once(SUBSDIR . '/Maillist.subs.php');

		// Any custom templates?
		$context['notification_templates'] = array();
		$notification_templates = maillist_templates('warntpl');

		foreach ($notification_templates as $row)
		{
			// If we're not warning for a message skip any that are.
			if ($warning_for_message === false && strpos($row['body'], '{MESSAGE}') !== false)
				continue;

			$context['notification_templates'][] = array(
				'title' => $row['title'],
				'body' => $row['body'],
			);
		}

		// Setup the "default" templates.
		foreach (array('spamming', 'offence', 'insulting') as $type)
		{
			$context['notification_templates'][] = array(
				'title' => $txt['profile_warning_notify_title_' . $type],
				'body' => sprintf($txt['profile_warning_notify_template_outline' . (!empty($warning_for_message) ? '_post' : '')], $txt['profile_warning_notify_for_' . $type]),
			);
		}

		// Replace all the common variables in the templates.
		foreach ($context['notification_templates'] as $k => $name)
			$context['notification_templates'][$k]['body'] = strtr($name['body'],
				array(
					'{MEMBER}' => un_htmlspecialchars($context['member']['name']),
					'{MESSAGE}' => '[url=' . $scripturl . '?msg=' . $warning_for_message . ']' . un_htmlspecialchars($warned_message_subject) . '[/url]',
					'{SCRIPTURL}' => $scripturl,
					'{FORUMNAME}' => $mbname,
					'{REGARDS}' => replaceBasicActionUrl($txt['regards_team'])
				)
			);
	}

	/**
	 * Creates the listing of issued warnings
	 */
	private function _create_issued_warnings_list()
	{
		global $txt, $scripturl, $modSettings;

		$listOptions = array(
			'id' => 'issued_warnings',
			'title' => $txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['profile_viewwarning_no_warnings'],
			'base_href' => $scripturl . '?action=profile;area=issuewarning;sa=user;u=' . $this->_memID,
			'default_sort_col' => 'log_time',
			'get_items' => array(
				'function' => array($this, 'list_getUserWarnings'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getUserWarningCount'),
			),
			'columns' => array(
				'issued_by' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_issued'],
						'class' => 'grid20',
					),
					'data' => array(
						'function' => function ($warning)
						{
							return $warning['issuer']['link'];
						},
					),
					'sort' => array(
						'default' => 'lc.member_name DESC',
						'reverse' => 'lc.member_name',
					),
				),
				'log_time' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_time'],
						'class' => 'grid30',
					),
					'data' => array(
						'db' => 'time',
					),
					'sort' => array(
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_reason'],
					),
					'data' => array(
						'function' => function ($warning)
						{
							global $scripturl, $txt;

							$ret = '
							<div class="floatleft">
								' . $warning['reason'] . '
							</div>';

							// If a notice was sent, provide a way to view it
							if (!empty($warning['id_notice']))
							{
								$ret .= '
							<div class="floatright">
								<a href="' . $scripturl . '?action=moderate;area=notice;nid=' . $warning['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" class="new_win" title="' . $txt['profile_warning_previous_notice'] . '"><i class="icon icon-small i-search"></i></a>
							</div>';
							}

							return $ret;

						},
					),
				),
				'level' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_level'],
						'class' => 'grid8',
					),
					'data' => array(
						'db' => 'counter',
					),
					'sort' => array(
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					),
				),
			),
		);

		// Create the list for viewing.
		createList($listOptions);
	}

	/**
	 * Simply returns the total count of warnings
	 * Callback for createList().
	 *
	 * @return int
	 */
	public function list_getUserWarningCount()
	{
		return list_getUserWarningCount($this->_memID);
	}

	/**
	 * Callback for createList(). Called by action_hooks
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 *
	 * @return array
	 */
	public function list_getUserWarnings($start, $items_per_page, $sort)
	{
		return list_getUserWarnings($start, $items_per_page, $sort, $this->_memID);
	}

	/**
	 * Prepares a warning preview
	 */
	private function _preview_warning()
	{
		global $context;

		if (isset($this->_req->post->preview))
		{
			$warning_body = !empty($this->_req->post->warn_body) ? trim(censor($this->_req->post->warn_body)) : '';

			if (empty($this->_req->post->warn_sub) || empty($this->_req->post->warn_body))
			{
				$this->_issueErrors[] = 'warning_notify_blank';
			}

			if (!empty($this->_req->post->warn_body))
			{
				require_once(SUBSDIR . '/Post.subs.php');

				$bbc_parser = \BBC\ParserWrapper::instance();
				preparsecode($warning_body);
				$warning_body = $bbc_parser->parseNotice($warning_body);
			}

			// Try to remember some bits.
			$context['preview_subject'] = $this->_req->getPost('warn_sub', 'trim|Util::htmlspecialchars', '');
			$context['warning_data'] = array(
				'reason' => $this->_req->post->warn_reason,
				'notify' => !empty($this->_req->post->warn_notify),
				'notify_subject' => $this->_req->getPost('warn_sub', 'trim', ''),
				'notify_body' => $this->_req->getPost('warn_body', 'trim', ''),
				'body_preview' => $warning_body,
			);
		}
	}

	/**
	 * Does the actual issuing of a warning to a member
	 *
	 * What it does:
	 *
	 * - Validates the inputs
	 * - Sends the warning PM if required, to the member
	 * - Logs the action
	 * - Updates the users data with the new warning level
	 */
	private function _save_warning()
	{
		global $txt, $context, $cur_profile;

		if (isset($this->_req->post->save))
		{
			// Security is good here.
			checkSession('post');

			// There must be a reason, and use of flowery words is allowed.
			$warn_reason = $this->_req->getPost('warn_reason', 'trim|Util::htmlspecialchars', '');
			if ($warn_reason === '' && !$context['user']['is_owner'])
			{
				$this->_issueErrors[] = 'warning_no_reason';
			}

			// If the value hasn't changed it's either no JS or a real no change (Which this will pass)
			if ($warn_reason === 'SAME')
			{
				$this->_req->post->warning_level = $this->_req->post->warning_level_nojs;
			}

			// Set and contain the level and level changes
			$warning_level = (int) $this->_req->post->warning_level;
			$warning_level = max(0, min(100, $warning_level));

			if ($warning_level < $context['min_allowed'])
			{
				$warning_level = $context['min_allowed'];
			}
			elseif ($warning_level > $context['max_allowed'])
			{
				$warning_level = $context['max_allowed'];
			}

			// We need this to log moderation notices
			require_once(SUBSDIR . '/Moderation.subs.php');

			// Do we actually have to issue them with a PM?
			$id_notice = $this->_issue_warning_pm();

			// What have we changed?
			$level_change = $warning_level - $cur_profile['warning'];

			// No errors? Proceed! Only log if you're not the owner.
			if (empty($this->_issueErrors))
			{
				// Log what we've done!
				if (!$context['user']['is_owner'])
				{
					logWarning($this->_memID, $cur_profile['real_name'], $id_notice, $level_change, $warn_reason);
				}

				// Make the change.
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($this->_memID, array('warning' => $warning_level));

				// Leave a lovely message.
				$context['profile_updated'] = $context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_warning_success'];
			}
			else
			{
				// Try to remember some bits.
				$context['warning_data'] = array(
					'reason' => $warn_reason,
					'notify' => !empty($this->_req->post->warn_notify),
					'notify_subject' => $this->_req->getPost('warn_sub', 'trim', ''),
					'notify_body' => $this->_req->getPost('warn_body', 'trim', ''),
				);
			}

			// Show the new improved warning level.
			$context['member']['warning'] = $warning_level;
		}
	}

	/**
	 * Issue a pm to the member getting the warning
	 *
	 * @return int
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _issue_warning_pm()
	{
		global $context;

		$id_notice = 0;
		if (!empty($this->_req->post->warn_notify) && empty($this->_issueErrors))
		{
			$warn_sub = $this->_req->getPost('warn_sub', 'trim', '');
			$warn_body = $this->_req->getPost('warn_body', 'trim', '');

			if (empty($warn_sub) || empty($warn_body))
			{
				$this->_issueErrors[] = 'warning_notify_blank';
			}
			// Send the PM?
			else
			{
				require_once(SUBSDIR . '/PersonalMessage.subs.php');
				$from = array(
					'id' => 0,
					'name' => $context['forum_name'],
					'username' => $context['forum_name'],
				);
				sendpm(array('to' => array($this->_memID), 'bcc' => array()), $warn_sub, $warn_body, false, $from);

				// Log the notice.
				$id_notice = logWarningNotice($warn_sub, $warn_body);
			}
		}

		return $id_notice;
	}

	/**
	 * Present a screen to make sure the user wants to be deleted.
	 */
	public function action_deleteaccount()
	{
		global $txt, $context, $modSettings, $cur_profile;

		if (!$context['user']['is_owner'])
			isAllowedTo('profile_remove_any');
		elseif (!allowedTo('profile_remove_any'))
			isAllowedTo('profile_remove_own');

		// Permissions for removing stuff...
		$context['can_delete_posts'] = !$context['user']['is_owner'] && allowedTo('moderate_forum');

		// Can they do this, or will they need approval?
		$context['needs_approval'] = $context['user']['is_owner'] && !empty($modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum');
		$context['page_title'] = $txt['deleteAccount'] . ': ' . $cur_profile['real_name'];

		// make sure the sub-template is set...
		theme()->getTemplates()->load('ProfileAccount');
		$context['sub_template'] = 'deleteAccount';
	}

	/**
	 * Actually delete an account.
	 */
	public function action_deleteaccount2()
	{
		global $user_info, $context, $cur_profile, $modSettings;

		// Try get more time...
		detectServer()->setTimeLimit(600);

		// @todo Add a way to delete pms as well?
		if (!$context['user']['is_owner'])
		{
			isAllowedTo('profile_remove_any');
		}
		elseif (!allowedTo('profile_remove_any'))
		{
			isAllowedTo('profile_remove_own');
		}

		checkSession();

		// Check we got here as we should have!
		if ($cur_profile != $this->_profile)
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		$old_profile = &$cur_profile;

		// This file is needed for our utility functions.
		require_once(SUBSDIR . '/Members.subs.php');

		// Too often, people remove/delete their own only administrative account.
		if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
		{
			// Are you allowed to administrate the forum, as they are?
			isAllowedTo('admin_forum');

			$another = isAnotherAdmin($this->_memID);

			if (empty($another))
				throw new \ElkArte\Exceptions\Exception('at_least_one_admin', 'critical');
		}

		// Do you have permission to delete others profiles, or is that your profile you wanna delete?
		if ($this->_memID != $user_info['id'])
		{
			isAllowedTo('profile_remove_any');

			// Now, have you been naughty and need your posts deleting?
			// @todo Should this check board permissions?
			if ($this->_req->post->remove_type != 'none' && allowedTo('moderate_forum'))
			{
				// Include subs/Topic.subs.php - essential for this type of work!
				require_once(SUBSDIR . '/Topic.subs.php');
				require_once(SUBSDIR . '/Messages.subs.php');

				// First off we delete any topics the member has started - if they wanted topics being done.
				if ($this->_req->post->remove_type === 'topics')
				{
					// Fetch all topics started by this user.
					$topicIDs = topicsStartedBy($this->_memID);

					// Actually remove the topics.
					// @todo This needs to check permissions, but we'll let it slide for now because of moderate_forum already being had.
					removeTopics($topicIDs);
				}

				// Now delete the remaining messages.
				removeNonTopicMessages($this->_memID);
			}

			// Only delete this poor member's account if they are actually being booted out of camp.
			if (isset($this->_req->post->deleteAccount))
				deleteMembers($this->_memID);
		}
		// Do they need approval to delete?
		elseif (!empty($modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum'))
		{
			// Setup their account for deletion ;)
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($this->_memID, array('is_activated' => 4));

			// Another account needs approval...
			updateSettings(array('unapprovedMembers' => true), true);
		}
		// Also check if you typed your password correctly.
		else
		{
			deleteMembers($this->_memID);

			$controller = new \ElkArte\controller\Auth(new Event_manager());
			$controller->action_logout(true);

			redirectexit();
		}
	}

	/**
	 * Activate an account.
	 *
	 * - This function is called from the profile account actions area.
	 */
	public function action_activateaccount()
	{
		global $context, $modSettings;

		isAllowedTo('moderate_forum');

		if (isset($this->_req->query->save)
			&& isset($this->_profile['is_activated'])
			&& $this->_profile['is_activated'] != 1)
		{
			require_once(SUBSDIR . '/Members.subs.php');

			// If we are approving the deletion of an account, we do something special ;)
			if ($this->_profile['is_activated'] == 4)
			{
				deleteMembers($context['id_member']);
				redirectexit();
			}

			// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
			approveMembers(array('members' => array($context['id_member']), 'activated_status' => $this->_profile['is_activated']));

			// Log what we did?
			logAction('approve_member', array('member' => $this->_memID), 'admin');

			// If we are doing approval, update the stats for the member just in case.
			if (in_array($this->_profile['is_activated'], array(3, 4, 13, 14)))
				updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > 1 ? $modSettings['unapprovedMembers'] - 1 : 0)));

			// Make sure we update the stats too.
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberStats();
		}

		// Leave it be...
		redirectexit('action=profile;u=' . $this->_memID . ';area=summary');
	}
}
