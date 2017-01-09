<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * It also allows the user to change some of their or another's preferences,
 * and such things
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
 * @version 1.0.3
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ProfileOptions_Controller class. Does the job of showing and editing people's profiles.
 * Interface to buddy list, ignore list, notifications, authenitcation options, forum profile
 * account settings, etc
 */
class ProfileOptions_Controller extends Action_Controller
{
	public static function getFields($area)
	{
		global $modSettings;

		$fields = array(
			'account' => array(
				'fields' => array(
					'member_name', 'real_name', 'date_registered', 'posts', 'lngfile', 'hr',
					'id_group', 'hr',
					'email_address', 'hide_email', 'show_online', 'hr',
					'passwrd1', 'passwrd2', 'hr',
					'secret_question', 'secret_answer',
				),
				'hook' => 'account'
				),
			'forumprofile' => array(
				'fields' => array(
					'avatar_choice', 'hr', 'personal_text', 'hr',
					'bday1', 'location', 'gender', 'hr',
					'usertitle', 'signature', 'hr',
					'karma_good', 'hr',
					'website_title', 'website_url',
				),
				'hook' => 'forum'
			),
			'theme' => array(
				'fields' => array(
					'id_theme', 'smiley_set', 'hr',
					'time_format', 'time_offset', 'hr',
					'theme_settings',
				),
				'hook' => 'themepick'
			),
			'contactprefs' => array(
				'fields' => array(
					'receive_from',
					'hr',
					'pm_settings',
				),
				'hook' => 'pmprefs'
			),
			'registration' => array(
				'fields' => explode(',', $modSettings['registration_fields']),
				'hook' => 'registration'
			)
		);

		if (isset($fields[$area]))
		{
			return $fields[$area];
		}
		else
		{
			return array();
		}
	}
	/**
	 * Default method, if another action is not called
	 * by the menu.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// action_account() is the first to do
		// these subactions are mostly routed to from the profile
		// menu though.
	}

	/**
	 * Show all the users buddies, as well as a add/delete interface.
	 */
	public function action_editBuddyIgnoreLists()
	{
		global $context, $txt, $modSettings;

		$memID = currentMemberID();

		// Do a quick check to ensure people aren't getting here illegally!
		if (!$context['user']['is_owner'] || empty($modSettings['enable_buddylist']))
			fatal_lang_error('no_access', false);

		loadTemplate('ProfileOptions');

		// Can we email the user direct?
		$context['can_moderate_forum'] = allowedTo('moderate_forum');
		$context['can_send_email'] = allowedTo('send_email_to_members');

		$subActions = array(
			'buddies' => array('action_editBuddies', $txt['editBuddies']),
			'ignore' => array('action_editIgnoreList', $txt['editIgnoreList']),
		);

		// Set a subaction
		$subAction = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'buddies';

		// Create the tabs for the template.
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['editBuddyIgnoreLists'],
			'description' => $txt['buddy_ignore_desc'],
			'class' => 'profile',
			'tabs' => array(
				'buddies' => array(),
				'ignore' => array(),
			),
		);

		// Pass on to the actual function.
		$this->{$subActions[$subAction][0]}($memID);
	}

	/**
	 * Show all the users buddies, as well as a add/delete interface.
	 *
	 * @param int $memID id_member
	 */
	public function action_editBuddies($memID)
	{
		global $context, $user_profile, $memberContext, $modSettings;

		$db = database();

		loadTemplate('ProfileOptions');

		// We want to view what we're doing :P
		$context['sub_template'] = 'editBuddies';
		loadJavascriptFile('suggest.js', array('defer' => true));

		// For making changes!
		$buddiesArray = explode(',', $user_profile[$memID]['buddy_list']);
		foreach ($buddiesArray as $k => $dummy)
			if ($dummy == '')
				unset($buddiesArray[$k]);

		// Removing a buddy?
		if (isset($_GET['remove']))
		{
			checkSession('get');

			call_integration_hook('integrate_remove_buddy', array($memID));

			// Heh, I'm lazy, do it the easy way...
			foreach ($buddiesArray as $key => $buddy)
				if ($buddy == (int) $_GET['remove'])
					unset($buddiesArray[$key]);

			// Make the changes.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));

			// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
			redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
		}
		// Or adding a new one
		elseif (isset($_POST['new_buddy']))
		{
			checkSession();

			// Prepare the string for extraction...
			$_POST['new_buddy'] = strtr(Util::htmlspecialchars($_POST['new_buddy'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);
			$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

			foreach ($new_buddies as $k => $dummy)
			{
				$new_buddies[$k] = strtr(trim($new_buddies[$k]), array('\'' => '&#039;'));

				if (strlen($new_buddies[$k]) == 0 || in_array($new_buddies[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
					unset($new_buddies[$k]);
			}

			call_integration_hook('integrate_add_buddies', array($memID, &$new_buddies));

			if (!empty($new_buddies))
			{
				// Now find out the id_member of the buddy.
				$request = $db->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
					LIMIT {int:count_new_buddies}',
					array(
						'new_buddies' => $new_buddies,
						'count_new_buddies' => count($new_buddies),
					)
				);

				// Let them know who's their buddy.
				if (!empty($modSettings['mentions_enabled']) && !empty($modSettings['mentions_buddy']))
				{
					require_once(CONTROLLERDIR . '/Mentions.controller.php');
					$mentions = new Mentions_Controller();
				}

				// Add the new member to the buddies array.
				while ($row = $db->fetch_assoc($request))
				{
					$buddiesArray[] = (int) $row['id_member'];

					if (!empty($modSettings['mentions_enabled']) && !empty($modSettings['mentions_buddy']))
					{
						// Set a mentions for our buddy.
						$mentions->setData(array(
							'id_member' => $row['id_member'],
							'type' => 'buddy',
							'id_msg' => 0,
						));
						$mentions->action_add();
					}
				}
				$db->free_result($request);

				// Now update the current users buddy list.
				$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
				updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));
			}

			// Back to the buddy list!
			redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
		}

		// Get all the users "buddies"...
		$buddies = array();

		if (!empty($buddiesArray))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$result = getBasicMemberData($buddiesArray, array('sort' => 'real_name', 'limit' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1));
			foreach ($result as $row)
				$buddies[] = $row['id_member'];
		}

		$context['buddy_count'] = count($buddies);

		// Load all the members up.
		loadMemberData($buddies, false, 'profile');

		// Setup the context for each buddy.
		$context['buddies'] = array();
		foreach ($buddies as $buddy)
		{
			loadMemberContext($buddy, true);
			$context['buddies'][$buddy] = $memberContext[$buddy];
		}

		call_integration_hook('integrate_view_buddies', array($memID));
	}

	/**
	 * Allows the user to view their ignore list,
	 * as well as the option to manage members on it.
	 *
	 * @param int $memID id_member
	 */
	public function action_editIgnoreList($memID)
	{
		global $context, $user_profile, $memberContext;

		$db = database();

		loadTemplate('ProfileOptions');

		// We want to view what we're doing :P
		$context['sub_template'] = 'editIgnoreList';
		loadJavascriptFile('suggest.js', array('defer' => true));

		// For making changes!
		$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
		foreach ($ignoreArray as $k => $dummy)
		{
			if ($dummy == '')
				unset($ignoreArray[$k]);
		}

		// Removing a member from the ignore list?
		if (isset($_GET['remove']))
		{
			checkSession('get');

			// Heh, I'm lazy, do it the easy way...
			foreach ($ignoreArray as $key => $id_remove)
				if ($id_remove == (int) $_GET['remove'])
					unset($ignoreArray[$key]);

			// Make the changes.
			$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
			updateMemberData($memID, array('pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']));

			// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
			redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
		}
		elseif (isset($_POST['new_ignore']))
		{
			checkSession();

			// Prepare the string for extraction...
			$_POST['new_ignore'] = strtr(Util::htmlspecialchars($_POST['new_ignore'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $_POST['new_ignore'], $matches);
			$new_entries = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_ignore']))));

			foreach ($new_entries as $k => $dummy)
			{
				$new_entries[$k] = strtr(trim($new_entries[$k]), array('\'' => '&#039;'));

				if (strlen($new_entries[$k]) == 0 || in_array($new_entries[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
					unset($new_entries[$k]);
			}

			if (!empty($new_entries))
			{
				// Now find out the id_member for the members in question.
				$request = $db->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:new_entries}) OR real_name IN ({array_string:new_entries})
					LIMIT {int:count_new_entries}',
					array(
						'new_entries' => $new_entries,
						'count_new_entries' => count($new_entries),
					)
				);

				// Add the new member to the buddies array.
				while ($row = $db->fetch_assoc($request))
					$ignoreArray[] = (int) $row['id_member'];
				$db->free_result($request);

				// Now update the current users buddy list.
				$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
				updateMemberData($memID, array('pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']));
			}

			// Back to the list of pityful people!
			redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
		}

		// Initialise the list of members we're ignoring.
		$ignored = array();

		if (!empty($ignoreArray))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$result = getBasicMemberData($ignoreArray, array('sort' => 'real_name', 'limit' => substr_count($user_profile[$memID]['pm_ignore_list'], ',') + 1));
			foreach ($result as $row)
				$ignored[] = $row['id_member'];
		}

		$context['ignore_count'] = count($ignored);

		// Load all the members up.
		loadMemberData($ignored, false, 'profile');

		// Setup the context for each buddy.
		$context['ignore_list'] = array();
		foreach ($ignored as $ignore_member)
		{
			loadMemberContext($ignore_member);
			$context['ignore_list'][$ignore_member] = $memberContext[$ignore_member];
		}
	}

	/**
	 * Allows the user to see or change their account info.
	 *
	 */
	public function action_account()
	{
		global $context, $txt;

		$memID = currentMemberID();

		loadTemplate('ProfileOptions');
		loadThemeOptions($memID);

		if (allowedTo(array('profile_identity_own', 'profile_identity_any')))
			loadCustomFields($memID, 'account');

		$context['sub_template'] = 'edit_options';
		$context['page_desc'] = $txt['account_info'];

		$fields = ProfileOptions_Controller::getFields('account');
		setupProfileContext($fields['fields'], $fields['hook']);
	}

	/**
	 * Allow the user to change the forum options in their profile.
	 *
	 */
	public function action_forumProfile()
	{
		global $context, $txt;

		$memID = currentMemberID();

		loadTemplate('ProfileOptions');
		loadThemeOptions($memID);

		if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
			loadCustomFields($memID, 'forumprofile');

		$context['sub_template'] = 'edit_options';
		$context['page_desc'] = replaceBasicActionUrl($txt['forumProfile_info']);
		$context['show_preview_button'] = true;

		$fields = ProfileOptions_Controller::getFields('forumprofile');
		setupProfileContext($fields['fields'], $fields['hook']);
	}

	/**
	 * Allow the edit of *someone elses* personal message settings.
	 */
	public function action_pmprefs()
	{
		global $context, $txt;

		$memID = currentMemberID();

		loadThemeOptions($memID);
		loadCustomFields($memID, 'pmprefs');
		loadTemplate('ProfileOptions');

		$context['sub_template'] = 'edit_options';
		$context['page_desc'] = $txt['pm_settings_desc'];

		// Setup the profile context and call the 'integrate_pmprefs_profile_fields' hook
		$fields = ProfileOptions_Controller::getFields('contactprefs');
		setupProfileContext($fields['fields'], $fields['hook']);
	}

	/**
	 * Allow the user to pick a theme.
	 *
	 */
	public function action_themepick()
	{
		global $txt, $context;

		$memID = currentMemberID();

		loadThemeOptions($memID);

		if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
			loadCustomFields($memID, 'theme');

		loadTemplate('ProfileOptions');

		$context['sub_template'] = 'edit_options';
		$context['page_desc'] = $txt['theme_info'];

		$fields = ProfileOptions_Controller::getFields('theme');
		setupProfileContext($fields['fields'], $fields['hook']);
	}

	/**
	 * Changing authentication method?
	 * Only appropriate for people using OpenID.
	 *
	 * @param bool $saving = false
	 */
	public function action_authentication($saving = false)
	{
		global $context, $cur_profile, $post_errors, $modSettings;

		$memID = currentMemberID();

		loadLanguage('Login');
		loadTemplate('ProfileOptions');

		// We are saving?
		if ($saving)
		{
			// Moving to password passed authentication?
			if ($_POST['authenticate'] == 'passwd')
			{
				// Didn't enter anything?
				if ($_POST['passwrd1'] == '')
					$post_errors[] = 'no_password';
				// Do the two entries for the password even match?
				elseif (!isset($_POST['passwrd2']) || $_POST['passwrd1'] != $_POST['passwrd2'])
					$post_errors[] = 'bad_new_password';
				// Is it valid?
				else
				{
					require_once(SUBSDIR . '/Auth.subs.php');
					$passwordErrors = validatePassword($_POST['passwrd1'], $cur_profile['member_name'], array($cur_profile['real_name'], $cur_profile['email_address']));

					// Were there errors?
					if ($passwordErrors != null)
						$post_errors[] = 'password_' . $passwordErrors;
				}

				if (empty($post_errors))
				{
					// Integration?
					call_integration_hook('integrate_reset_pass', array($cur_profile['member_name'], $cur_profile['member_name'], $_POST['passwrd1']));

					// Go then.
					require_once(SUBSDIR . '/Auth.subs.php');
					$new_pass = $_POST['passwrd1'];
					$passwd = validateLoginPassword($new_pass, '', $cur_profile['member_name'], true);

					// Do the important bits.
					updateMemberData($memID, array('openid_uri' => '', 'passwd' => $passwd));
					if ($context['user']['is_owner'])
					{
						setLoginCookie(60 * $modSettings['cookieTime'], $memID, hash('sha256', $new_pass . $cur_profile['password_salt']));
						redirectexit('action=profile;area=authentication;updated');
					}
					else
						redirectexit('action=profile;u=' . $memID);
				}

				return true;
			}
			// Not right yet!
			elseif ($_POST['authenticate'] == 'openid' && !empty($_POST['openid_identifier']))
			{
				require_once(SUBSDIR . '/OpenID.subs.php');
				require_once(SUBSDIR . '/Members.subs.php');

				$openID = new OpenID();
				$_POST['openid_identifier'] = $openID->canonize($_POST['openid_identifier']);

				if (memberExists($_POST['openid_identifier']))
					$post_errors[] = 'openid_in_use';
				elseif (empty($post_errors))
				{
					// Authenticate using the new OpenID URI first to make sure they didn't make a mistake.
					if ($context['user']['is_owner'])
					{
						$_SESSION['new_openid_uri'] = $_POST['openid_identifier'];

						$openID->validate($_POST['openid_identifier'], false, null, 'change_uri');
					}
					else
						updateMemberData($memID, array('openid_uri' => $_POST['openid_identifier']));
				}
			}
		}

		// Some stuff.
		$context['member']['openid_uri'] = $cur_profile['openid_uri'];
		$context['auth_method'] = empty($cur_profile['openid_uri']) ? 'password' : 'openid';
		$context['sub_template'] = 'authentication_method';
		loadJavascriptFile('register.js');
	}

	/**
	 * Display the notifications and settings for changes.
	 */
	public function action_notification()
	{
		global $txt, $scripturl, $user_profile, $context, $modSettings;

		loadTemplate('ProfileOptions');

		$memID = currentMemberID();

		// Going to need this for the list.
		require_once(SUBSDIR . '/GenericList.class.php');
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// Fine, start with the board list.
		$listOptions = array(
			'id' => 'board_notification_list',
			'width' => '100%',
			'no_items_label' => $txt['notifications_boards_none'] . '<br /><br />' . $txt['notifications_boards_howto'],
			'no_items_align' => 'left',
			'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification',
			'default_sort_col' => 'board_name',
			'get_items' => array(
				'function' => array($this, 'list_getBoardNotifications'),
				'params' => array(
					$memID,
				),
			),
			'columns' => array(
				'board_name' => array(
					'header' => array(
						'value' => $txt['notifications_boards'],
						'class' => 'lefttext',
					),
					'data' => array(
						'function' => create_function('$board', '
							global $txt;

							$link = $board[\'link\'];

							if ($board[\'new\'])
								$link .= \' <a href="\' . $board[\'href\'] . \'"><span class="new_posts">' . $txt['new'] . '</span></a>\';

							return $link;
						'),
					),
					'sort' => array(
						'default' => 'name',
						'reverse' => 'name DESC',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'class' => 'centertext',
						'style' => 'width:4%;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d" class="input_check" %2$s />',
							'params' => array(
								'id' => false,
								'checked' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=profile;area=notification;save',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					'u' => $memID,
					'sa' => $context['menu_item_selected'],
					$context['session_var'] => $context['session_id'],
				),
				'token' => $context['token_check'],
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="edit_notify_boards" value="' . $txt['notifications_boards_update'] . '" class="right_submit" />',
				),
				array(
					'position' => 'after_title',
					'value' => getBoardNotificationsCount($memID) == 0 ? $txt['notifications_boards_none'] . '<br />' . $txt['notifications_boards_howto'] : $txt['notifications_boards_current'],
				),
			),
		);

		// Create the board notification list.
		createList($listOptions);

		// Now do the topic notifications.
		$listOptions = array(
			'id' => 'topic_notification_list',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['notifications_topics_none'] . '<br /><br />' . $txt['notifications_topics_howto'],
			'no_items_align' => 'left',
			'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification',
			'default_sort_col' => 'last_post',
			'get_items' => array(
				'function' => array($this, 'list_getTopicNotifications'),
				'params' => array(
					$memID,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getTopicNotificationCount'),
				'params' => array(
					$memID,
				),
			),
			'columns' => array(
				'subject' => array(
					'header' => array(
						'value' => $txt['notifications_topics'],
						'class' => 'lefttext',
					),
					'data' => array(
						'function' => create_function('$topic', '
							global $txt;

							$link = $topic[\'link\'];

							if ($topic[\'new\'])
								$link .= \' <a href="\' . $topic[\'new_href\'] . \'"><span class="new_posts">\' . $txt[\'new\'] . \'</span></a>\';

							$link .= \'<br /><span class="smalltext"><em>\' . $txt[\'in\'] . \' \' . $topic[\'board_link\'] . \'</em></span>\';

							return $link;
						'),
					),
					'sort' => array(
						'default' => 'ms.subject',
						'reverse' => 'ms.subject DESC',
					),
				),
				'started_by' => array(
					'header' => array(
						'value' => $txt['started_by'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'poster_link',
					),
					'sort' => array(
						'default' => 'real_name_col',
						'reverse' => 'real_name_col DESC',
					),
				),
				'last_post' => array(
					'header' => array(
						'value' => $txt['last_post'],
							'class' => 'lefttext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<span class="smalltext">%1$s<br />' . $txt['by'] . ' %2$s</span>',
							'params' => array(
								'updated' => false,
								'poster_updated_link' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'ml.id_msg DESC',
						'reverse' => 'ml.id_msg',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'class' => 'centertext',
						'style' => 'width:4%;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=profile;area=notification;save',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					'u' => $memID,
					'sa' => $context['menu_item_selected'],
					$context['session_var'] => $context['session_id'],
				),
				'token' => $context['token_check'],
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="edit_notify_topics" value="' . $txt['notifications_update'] . '" class="right_submit" />',
					'align' => 'right',
				),
			),
		);

		// Create the notification list.
		createList($listOptions);

		// What options are set?
		$context['member'] += array(
			'notify_announcements' => $user_profile[$memID]['notify_announcements'],
			'notify_send_body' => $user_profile[$memID]['notify_send_body'],
			'notify_types' => $user_profile[$memID]['notify_types'],
			'notify_regularity' => $user_profile[$memID]['notify_regularity'],
		);

		loadThemeOptions($memID);
	}

	/**
	 * Callback for createList() in action_notification()
	 * Retrieve topic notifications count.
	 *
	 * @param int $memID id_member
	 * @return integer
	 */
	public function list_getTopicNotificationCount($memID)
	{
		// Topic notifications count, for the list
		return topicNotificationCount($memID);
	}

	/**
	 * Callback for createList() in action_notification()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $memID id_member
	 * @return mixed array of topic notifications
	 */
	public function list_getTopicNotifications($start, $items_per_page, $sort, $memID)
	{
		// topic notifications, for the list
		return topicNotifications($start, $items_per_page, $sort, $memID);
	}

	/**
	 * Callback for createList() in action_notification()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $memID id_member
	 * @return mixed[] array of board notifications
	 */
	public function list_getBoardNotifications($start, $items_per_page, $sort, $memID)
	{
		// return boards you see and their notification status for the list
		return boardNotifications($start, $items_per_page, $sort, $memID);
	}

	/**
	 * Allows the user to see the list of their ignored boards.
	 * (and un-ignore them)
	 *
	 */
	public function action_ignoreboards()
	{
		global $context, $modSettings, $cur_profile;

		$memID = currentMemberID();

		// Have the admins enabled this option?
		if (empty($modSettings['allow_ignore_boards']))
			fatal_lang_error('ignoreboards_disallowed', 'user');

		loadTemplate('ProfileOptions');

		$context['sub_template'] = 'ignoreboards';
		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList(array('not_redirection' => true, 'ignore' => !empty($cur_profile['ignore_boards']) ? explode(',', $cur_profile['ignore_boards']) : array()));

		// Include a list of boards per category for easy toggling.
		foreach ($context['categories'] as $cat => &$category)
		{
			$context['boards_in_category'][$cat] = count($category['boards']);
			$category['child_ids'] = array_keys($category['boards']);
		}

		loadThemeOptions($memID);
	}

	/**
	 * Function to allow the user to choose group membership etc...
	 *
	 */
	public function action_groupMembership()
	{
		global $txt, $user_profile, $context;

		$db = database();

		$memID = currentMemberID();

		loadTemplate('ProfileOptions');
		$context['sub_template'] = 'groupMembership';

		$curMember = $user_profile[$memID];
		$context['primary_group'] = $curMember['id_group'];

		// Can they manage groups?
		$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
		$context['can_manage_protected'] = allowedTo('admin_forum');
		$context['can_edit_primary'] = $context['can_manage_protected'];
		$context['update_message'] = isset($_GET['msg']) && isset($txt['group_membership_msg_' . $_GET['msg']]) ? $txt['group_membership_msg_' . $_GET['msg']] : '';

		// Get all the groups this user is a member of.
		$groups = explode(',', $curMember['additional_groups']);
		$groups[] = $curMember['id_group'];

		// Ensure the query doesn't croak!
		if (empty($groups))
			$groups = array(0);

		// Just to be sure...
		foreach ($groups as $k => $v)
			$groups[$k] = (int) $v;

		// Get all the membergroups they can join.
		$request = $db->query('', '
			SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
				IFNULL(lgr.id_member, 0) AS pending
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}log_group_requests AS lgr ON (lgr.id_member = {int:selected_member} AND lgr.id_group = mg.id_group)
			WHERE (mg.id_group IN ({array_int:group_list})
				OR mg.group_type > {int:nonjoin_group_id})
				AND mg.min_posts = {int:min_posts}
				AND mg.id_group != {int:moderator_group}
			ORDER BY group_name',
			array(
				'group_list' => $groups,
				'selected_member' => $memID,
				'nonjoin_group_id' => 1,
				'min_posts' => -1,
				'moderator_group' => 3,
			)
		);
		// This beast will be our group holder.
		$context['groups'] = array(
			'member' => array(),
			'available' => array()
		);
		while ($row = $db->fetch_assoc($request))
		{
			// Can they edit their primary group?
			if (($row['id_group'] == $context['primary_group'] && $row['group_type'] > 1) || ($row['hidden'] != 2 && $context['primary_group'] == 0 && in_array($row['id_group'], $groups)))
				$context['can_edit_primary'] = true;

			// If they can't manage (protected) groups, and it's not publically joinable or already assigned, they can't see it.
			if (((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0)) && $row['id_group'] != $context['primary_group'])
				continue;

			$context['groups'][in_array($row['id_group'], $groups) ? 'member' : 'available'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'desc' => $row['description'],
				'color' => $row['online_color'],
				'type' => $row['group_type'],
				'pending' => $row['pending'],
				'is_primary' => $row['id_group'] == $context['primary_group'],
				'can_be_primary' => $row['hidden'] != 2,
				// Anything more than this needs to be done through account settings for security.
				'can_leave' => $row['id_group'] != 1 && $row['group_type'] > 1 ? true : false,
			);
		}
		$db->free_result($request);

		// Add registered members on the end.
		$context['groups']['member'][0] = array(
			'id' => 0,
			'name' => $txt['regular_members'],
			'desc' => $txt['regular_members_desc'],
			'type' => 0,
			'is_primary' => $context['primary_group'] == 0 ? true : false,
			'can_be_primary' => true,
			'can_leave' => 0,
		);

		// No changing primary one unless you have enough groups!
		if (count($context['groups']['member']) < 2)
			$context['can_edit_primary'] = false;

		// In the special case that someone is requesting membership of a group, setup some special context vars.
		if (isset($_REQUEST['request']) && isset($context['groups']['available'][(int) $_REQUEST['request']]) && $context['groups']['available'][(int) $_REQUEST['request']]['type'] == 2)
			$context['group_request'] = $context['groups']['available'][(int) $_REQUEST['request']];
	}

	/**
	 * This function actually makes all the group changes
	 *
	 * @return string
	 */
	public function action_groupMembership2()
	{
		global $context, $user_profile, $modSettings, $scripturl, $language;

		$db = database();
		$memID = currentMemberID();

		// Let's be extra cautious...
		if (!$context['user']['is_owner'] || empty($modSettings['show_group_membership']))
			isAllowedTo('manage_membergroups');

		if (!isset($_REQUEST['gid']) && !isset($_POST['primary']))
			fatal_lang_error('no_access', false);

		checkSession(isset($_GET['gid']) ? 'get' : 'post');

		require_once(SUBSDIR . '/Membergroups.subs.php');

		$old_profile = &$user_profile[$memID];
		$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
		$context['can_manage_protected'] = allowedTo('admin_forum');

		// By default the new primary is the old one.
		$newPrimary = $old_profile['id_group'];
		$addGroups = array_flip(explode(',', $old_profile['additional_groups']));
		$canChangePrimary = $old_profile['id_group'] == 0 ? 1 : 0;
		$changeType = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

		// One way or another, we have a target group in mind...
		$group_id = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : (int) $_POST['primary'];
		$foundTarget = $changeType == 'primary' && $group_id == 0 ? true : false;

		// Sanity check!!
		if ($group_id == 1)
			isAllowedTo('admin_forum');

		// What ever we are doing, we need to determine if changing primary is possible!
		$groups_details = membergroupsById(array($group_id, $old_profile['id_group']), 0, true);

		// Protected groups require proper permissions!
		if ($group_id != 1 && $groups_details[$group_id]['group_type'] == 1)
			isAllowedTo('admin_forum');

		foreach ($groups_details as $key => $row)
		{
			// Is this the new group?
			if ($row['id_group'] == $group_id)
			{
				$foundTarget = true;
				$group_name = $row['group_name'];

				// Does the group type match what we're doing - are we trying to request a non-requestable group?
				if ($changeType == 'request' && $row['group_type'] != 2)
					fatal_lang_error('no_access', false);
				// What about leaving a requestable group we are not a member of?
				elseif ($changeType == 'free' && $row['group_type'] == 2 && $old_profile['id_group'] != $row['id_group'] && !isset($addGroups[$row['id_group']]))
					fatal_lang_error('no_access', false);
				elseif ($changeType == 'free' && $row['group_type'] != 3 && $row['group_type'] != 2)
					fatal_lang_error('no_access', false);

				// We can't change the primary group if this is hidden!
				if ($row['hidden'] == 2)
					$canChangePrimary = false;
			}

			// If this is their old primary, can we change it?
			if ($row['id_group'] == $old_profile['id_group'] && ($row['group_type'] > 1 || $context['can_manage_membergroups']) && $canChangePrimary !== false)
				$canChangePrimary = 1;

			// If we are not doing a force primary move, don't do it automatically if current primary is not 0.
			if ($changeType != 'primary' && $old_profile['id_group'] != 0)
				$canChangePrimary = false;

			// If this is the one we are acting on, can we even act?
			if ((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0))
				$canChangePrimary = false;
		}

		// Didn't find the target?
		if (!$foundTarget)
			fatal_lang_error('no_access', false);

		// Final security check, don't allow users to promote themselves to admin.
		if ($context['can_manage_membergroups'] && !allowedTo('admin_forum'))
		{
			$request = $db->query('', '
				SELECT COUNT(permission)
				FROM {db_prefix}permissions
				WHERE id_group = {int:selected_group}
					AND permission = {string:admin_forum}
					AND add_deny = {int:not_denied}',
				array(
					'selected_group' => $group_id,
					'not_denied' => 1,
					'admin_forum' => 'admin_forum',
				)
			);
			list ($disallow) = $db->fetch_row($request);
			$db->free_result($request);

			if ($disallow)
				isAllowedTo('admin_forum');
		}

		// If we're requesting, add the note then return.
		if ($changeType == 'request')
		{
			$request = $db->query('', '
				SELECT id_member
				FROM {db_prefix}log_group_requests
				WHERE id_member = {int:selected_member}
					AND id_group = {int:selected_group}',
				array(
					'selected_member' => $memID,
					'selected_group' => $group_id,
				)
			);
			if ($db->num_rows($request) != 0)
				fatal_lang_error('profile_error_already_requested_group');
			$db->free_result($request);

			// Log the request.
			$db->insert('',
				'{db_prefix}log_group_requests',
				array(
					'id_member' => 'int', 'id_group' => 'int', 'time_applied' => 'int', 'reason' => 'string-65534',
				),
				array(
					$memID, $group_id, time(), $_POST['reason'],
				),
				array('id_request')
			);

			// Send an email to all group moderators etc.
			require_once(SUBSDIR . '/Mail.subs.php');

			// Do we have any group moderators?
			$request = $db->query('', '
				SELECT id_member
				FROM {db_prefix}group_moderators
				WHERE id_group = {int:selected_group}',
				array(
					'selected_group' => $group_id,
				)
			);
			$moderators = array();
			while ($row = $db->fetch_assoc($request))
				$moderators[] = $row['id_member'];
			$db->free_result($request);

			// Otherwise this is the backup!
			if (empty($moderators))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$moderators = membersAllowedTo('manage_membergroups');
			}

			if (!empty($moderators))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$members = getBasicMemberData($moderators, array('preferences' => true, 'sort' => 'lngfile'));

				foreach ($members as $member)
				{
					if ($member['notify_types'] != 4)
						continue;

					// Check whether they are interested.
					if (!empty($member['mod_prefs']))
					{
						list (,, $pref_binary) = explode('|', $member['mod_prefs']);
						if (!($pref_binary & 4))
							continue;
					}

					$replacements = array(
						'RECPNAME' => $member['member_name'],
						'APPYNAME' => $old_profile['member_name'],
						'GROUPNAME' => $group_name,
						'REASON' => $_POST['reason'],
						'MODLINK' => $scripturl . '?action=moderate;area=groups;sa=requests',
					);

					$emaildata = loadEmailTemplate('request_membership', $replacements, empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile']);
					sendmail($member['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
				}
			}

			return $changeType;
		}
		// Otherwise we are leaving/joining a group.
		elseif ($changeType == 'free')
		{
			// Are we leaving?
			if ($old_profile['id_group'] == $group_id || isset($addGroups[$group_id]))
			{
				if ($old_profile['id_group'] == $group_id)
					$newPrimary = 0;
				else
					unset($addGroups[$group_id]);
			}
			// ... if not, must be joining.
			else
			{
				// Can we change the primary, and do we want to?
				if ($canChangePrimary)
				{
					if ($old_profile['id_group'] != 0)
						$addGroups[$old_profile['id_group']] = -1;
					$newPrimary = $group_id;
				}
				// Otherwise it's an additional group...
				else
					$addGroups[$group_id] = -1;
			}
		}
		// Finally, we must be setting the primary.
		elseif ($canChangePrimary)
		{
			if ($old_profile['id_group'] != 0)
				$addGroups[$old_profile['id_group']] = -1;
			if (isset($addGroups[$group_id]))
				unset($addGroups[$group_id]);
			$newPrimary = $group_id;
		}

		// Finally, we can make the changes!
		foreach ($addGroups as $id => $dummy)
		{
			if (empty($id))
				unset($addGroups[$id]);
		}
		$addGroups = implode(',', array_flip($addGroups));

		// Ensure that we don't cache permissions if the group is changing.
		if ($context['user']['is_owner'])
			$_SESSION['mc']['time'] = 0;
		else
			updateSettings(array('settings_updated' => time()));

		updateMemberData($memID, array('id_group' => $newPrimary, 'additional_groups' => $addGroups));

		return $changeType;
	}
}

/**
 * Load the options for an user.
 *
 * @param int $memID id_member
 */
function loadThemeOptions($memID)
{
	global $context, $options, $cur_profile;

	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];

	if ($context['user']['is_owner'])
	{
		$context['member']['options'] = $options;
		if (isset($_POST['options']) && is_array($_POST['options']))
			foreach ($_POST['options'] as $k => $v)
				$context['member']['options'][$k] = $v;
	}
	else
	{
		require_once(SUBSDIR . '/Themes.subs.php');
		$context['member']['options'] = loadThemeOptionsInto(array(1, (int) $cur_profile['id_theme']), array(-1, $memID), $context['member']['options']);
		if (isset($_POST['options']))
		{
			foreach ($_POST['options'] as $var => $val)
				$context['member']['options'][$var] = $val;
		}
	}
}