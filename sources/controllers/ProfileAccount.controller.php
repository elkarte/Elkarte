<?php

/**
 * Handles actions made against a user's profile.
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
 * @version 1.0.7
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ProfileAccount controller handles actions made on a user's profile.
 */
class ProfileAccount_Controller extends Action_Controller
{
	/**
	 * Entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// figure out what action to do... if we're called directly
		// actions in this class are called from the Profile menu, though.
	}

	/**
	 * Issue/manage an user's warning status.
	 * @uses ProfileAccount template issueWarning sub template
	 * @uses Profile template
	 */
	public function action_issuewarning()
	{
		global $txt, $scripturl, $modSettings, $mbname, $context, $cur_profile;

		$memID = currentMemberID();

		// make sure the sub-template is set...
		loadTemplate('ProfileAccount');
		$context['sub_template'] = 'issueWarning';
		// We need this because of template_load_warning_variables
		loadTemplate('Profile');
		loadJavascriptFile('profile.js');
		// jQuery-UI FTW!
		$modSettings['jquery_include_ui'] = true;
		loadCSSFile('jquery.ui.slider.css');
		loadCSSFile('jquery.ui.theme.css');

		// Get all the actual settings.
		list ($modSettings['warning_enable'], $modSettings['user_limit']) = explode(',', $modSettings['warning_settings']);

		// This stores any legitimate errors.
		$issueErrors = array();

		// Doesn't hurt to be overly cautious.
		if (empty($modSettings['warning_enable']) || ($context['user']['is_owner'] && !$cur_profile['warning']) || !allowedTo('issue_warning'))
			fatal_lang_error('no_access', false);

		// Get the base (errors related) stuff done.
		loadLanguage('Errors');
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
			$current_applied = warningDailyLimit($memID);

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
		if (isset($_POST['save']))
		{
			// Security is good here.
			checkSession('post');

			// This cannot be empty!
			$_POST['warn_reason'] = isset($_POST['warn_reason']) ? trim($_POST['warn_reason']) : '';
			if ($_POST['warn_reason'] == '' && !$context['user']['is_owner'])
				$issueErrors[] = 'warning_no_reason';

			$_POST['warn_reason'] = Util::htmlspecialchars($_POST['warn_reason']);

			// If the value hasn't changed it's either no JS or a real no change (Which this will pass)
			if ($_POST['warning_level'] == 'SAME')
				$_POST['warning_level'] = $_POST['warning_level_nojs'];

			$_POST['warning_level'] = (int) $_POST['warning_level'];
			$_POST['warning_level'] = max(0, min(100, $_POST['warning_level']));

			if ($_POST['warning_level'] < $context['min_allowed'])
				$_POST['warning_level'] = $context['min_allowed'];
			elseif ($_POST['warning_level'] > $context['max_allowed'])
				$_POST['warning_level'] = $context['max_allowed'];

			require_once(SUBSDIR . '/Moderation.subs.php');

			// Do we actually have to issue them with a PM?
			$id_notice = 0;
			if (!empty($_POST['warn_notify']) && empty($issueErrors))
			{
				$_POST['warn_sub'] = trim($_POST['warn_sub']);
				$_POST['warn_body'] = trim($_POST['warn_body']);

				if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
					$issueErrors[] = 'warning_notify_blank';
				// Send the PM?
				else
				{
					require_once(SUBSDIR . '/PersonalMessage.subs.php');
					$from = array(
						'id' => 0,
						'name' => $context['forum_name'],
						'username' => $context['forum_name'],
					);
					sendpm(array('to' => array($memID), 'bcc' => array()), $_POST['warn_sub'], $_POST['warn_body'], false, $from);

					// Log the notice.
					$id_notice = logWarningNotice($_POST['warn_sub'], $_POST['warn_body']);
				}
			}

			// Just in case - make sure notice is valid!
			$id_notice = (int) $id_notice;

			// What have we changed?
			$level_change = $_POST['warning_level'] - $cur_profile['warning'];

			// No errors? Proceed! Only log if you're not the owner.
			if (empty($issueErrors))
			{
				// Log what we've done!
				if (!$context['user']['is_owner'])
					logWarning($memID, $cur_profile['real_name'], $id_notice, $level_change, $_POST['warn_reason']);

				// Make the change.
				updateMemberData($memID, array('warning' => $_POST['warning_level']));

				// Leave a lovely message.
				$context['profile_updated'] = $context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_warning_success'];
			}
			else
			{
				// Try to remember some bits.
				$context['warning_data'] = array(
					'reason' => $_POST['warn_reason'],
					'notify' => !empty($_POST['warn_notify']),
					'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
					'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
				);
			}

			// Show the new improved warning level.
			$context['member']['warning'] = $_POST['warning_level'];
		}

		// Taking a look first, good idea that one.
		if (isset($_POST['preview']))
		{
			$warning_body = !empty($_POST['warn_body']) ? trim(censorText($_POST['warn_body'])) : '';
			$context['preview_subject'] = !empty($_POST['warn_sub']) ? trim(Util::htmlspecialchars($_POST['warn_sub'])) : '';

			if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
				$issueErrors[] = 'warning_notify_blank';

			if (!empty($_POST['warn_body']))
			{
				require_once(SUBSDIR . '/Post.subs.php');

				preparsecode($warning_body);
				$warning_body = parse_bbc($warning_body, true);
			}

			// Try to remember some bits.
			$context['warning_data'] = array(
				'reason' => $_POST['warn_reason'],
				'notify' => !empty($_POST['warn_notify']),
				'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
				'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
				'body_preview' => $warning_body,
			);
		}

		if (!empty($issueErrors))
		{
			// Fill in the suite of errors.
			$context['post_errors'] = array();
			foreach ($issueErrors as $error)
				$context['post_errors'][] = $txt[$error];
		}

		$context['page_title'] = $txt['profile_issue_warning'];

		// Let's use a generic list to get all the current warnings
		require_once(SUBSDIR . '/GenericList.class.php');
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

		// Build a list to view the warnings
		$listOptions = array(
			'id' => 'issued_warnings',
			'title' => $txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['profile_viewwarning_no_warnings'],
			'base_href' => $scripturl . '?action=profile;area=issuewarning;sa=user;u=' . $memID,
			'default_sort_col' => 'log_time',
			'get_items' => array(
				'function' => 'list_getUserWarnings',
				'params' => array(
					$memID,
				),
			),
			'get_count' => array(
				'function' => 'list_getUserWarningCount',
				'params' => array(
					$memID,
				),
			),
			'columns' => array(
				'issued_by' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_issued'],
						'style' => 'width: 20%;',
					),
					'data' => array(
						'function' => create_function('$warning', '
							return $warning[\'issuer\'][\'link\'];
						'
						),
					),
					'sort' => array(
						'default' => 'lc.member_name DESC',
						'reverse' => 'lc.member_name',
					),
				),
				'log_time' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_time'],
						'style' => 'width: 30%;',
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
						'function' => create_function('$warning', '
							global $scripturl, $txt, $settings;

							$ret = \'
							<div class="floatleft">
								\' . $warning[\'reason\'] . \'
							</div>\';

							// If a notice was sent, provide a way to view it
							if (!empty($warning[\'id_notice\']))
								$ret .= \'
							<div class="floatright">
								<a href="\' . $scripturl . \'?action=moderate;area=notice;nid=\' . $warning[\'id_notice\'] . \'" onclick="window.open(this.href, \\\'\\\', \\\'scrollbars=yes,resizable=yes,width=400,height=250\\\');return false;" target="_blank" class="new_win" title="\' . $txt[\'profile_warning_previous_notice\'] . \'"><img src="\' . $settings[\'images_url\'] . \'/filter.png" alt="" /></a>
							</div>\';

							return $ret;'),
					),
				),
				'level' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_level'],
						'style' => 'width: 6%;',
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

		$warning_for_message = isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : false;
		$warned_message_subject = '';

		// Are they warning because of a message?
		if (isset($_REQUEST['msg']) && 0 < (int) $_REQUEST['msg'])
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$message = basicMessageInfo((int) $_REQUEST['msg']);

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
			if (!$warning_for_message && strpos($row['body'], '{MESSAGE}') !== false)
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
		loadTemplate('ProfileAccount');
		$context['sub_template'] = 'deleteAccount';
	}

	/**
	 * Actually delete an account.
	 */
	public function action_deleteaccount2()
	{
		global $user_info, $context, $cur_profile, $user_profile, $modSettings;

		// Try get more time...
		@set_time_limit(600);

		// @todo Add a way to delete pms as well?
		if (!$context['user']['is_owner'])
			isAllowedTo('profile_remove_any');
		elseif (!allowedTo('profile_remove_any'))
			isAllowedTo('profile_remove_own');

		checkSession();

		$memID = currentMemberID();

		// Check we got here as we should have!
		if ($cur_profile != $user_profile[$memID])
			fatal_lang_error('no_access', false);

		$old_profile = &$cur_profile;

		// This file is needed for our utility functions.
		require_once(SUBSDIR . '/Members.subs.php');

		// Too often, people remove/delete their own only administrative account.
		if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
		{
			// Are you allowed to administrate the forum, as they are?
			isAllowedTo('admin_forum');

			$another = isAnotherAdmin($memID);

			if (empty($another))
				fatal_lang_error('at_least_one_admin', 'critical');
		}

		// Do you have permission to delete others profiles, or is that your profile you wanna delete?
		if ($memID != $user_info['id'])
		{
			isAllowedTo('profile_remove_any');

			// Now, have you been naughty and need your posts deleting?
			// @todo Should this check board permissions?
			if ($_POST['remove_type'] != 'none' && allowedTo('moderate_forum'))
			{
				// Include subs/Topic.subs.php - essential for this type of work!
				require_once(SUBSDIR . '/Topic.subs.php');
				require_once(SUBSDIR . '/Messages.subs.php');

				// First off we delete any topics the member has started - if they wanted topics being done.
				if ($_POST['remove_type'] == 'topics')
				{
					// Fetch all topics started by this user.
					$topicIDs = topicsStartedBy($memID);

					// Actually remove the topics.
					// @todo This needs to check permissions, but we'll let it slide for now because of moderate_forum already being had.
					removeTopics($topicIDs);
				}

				// Now delete the remaining messages.
				removeNonTopicMessages($memID);
			}

			// Only delete this poor member's account if they are actually being booted out of camp.
			if (isset($_POST['deleteAccount']))
				deleteMembers($memID);
		}
		// Do they need approval to delete?
		elseif (!empty($modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum'))
		{
			// Setup their account for deletion ;)
			updateMemberData($memID, array('is_activated' => 4));
			// Another account needs approval...
			updateSettings(array('unapprovedMembers' => true), true);
		}
		// Also check if you typed your password correctly.
		else
		{
			deleteMembers($memID);

			require_once(CONTROLLERDIR . '/Auth.controller.php');
			$controller = new Auth_Controller();
			$controller->action_logout(true);

			redirectexit();
		}
	}

	/**
	 * Activate an account.
	 * This function is called from the profile account actions area.
	 */
	public function action_activateaccount()
	{
		global $context, $user_profile, $modSettings;

		isAllowedTo('moderate_forum');

		$memID = currentMemberID();

		if (isset($_REQUEST['save']) && isset($user_profile[$memID]['is_activated']) && $user_profile[$memID]['is_activated'] != 1)
		{
			require_once(SUBSDIR . '/Members.subs.php');

			// If we are approving the deletion of an account, we do something special ;)
			if ($user_profile[$memID]['is_activated'] == 4)
			{
				deleteMembers($context['id_member']);
				redirectexit();
			}

			// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
			approveMembers(array('members' => array($context['id_member']), 'activated_status' => $user_profile[$memID]['is_activated']));

			// Log what we did?
			logAction('approve_member', array('member' => $memID), 'admin');

			// If we are doing approval, update the stats for the member just in case.
			if (in_array($user_profile[$memID]['is_activated'], array(3, 4, 13, 14)))
				updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > 1 ? $modSettings['unapprovedMembers'] - 1 : 0)));

			// Make sure we update the stats too.
			updateStats('member', false);
		}

		// Leave it be...
		redirectexit('action=profile;u=' . $memID . ';area=summary');
	}
}