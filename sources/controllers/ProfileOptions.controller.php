<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file has the primary job of showing and editing people's profiles.
 * It also allows the user to change some of their or another's preferences,
 * and such things
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 */
function action_editBuddyIgnoreLists()
{
	global $context, $txt, $scripturl, $modSettings, $user_profile;

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

	$context['list_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'buddies';

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['editBuddyIgnoreLists'],
		'description' => $txt['buddy_ignore_desc'],
		'icon' => 'profile_hd.png',
		'tabs' => array(
			'buddies' => array(),
			'ignore' => array(),
		),
	);

	// Pass on to the actual function.
	$subActions[$context['list_area']][0]($memID);
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID id_member
 */
function action_editBuddies($memID)
{
	global $txt, $scripturl, $modSettings;
	global $context, $user_profile, $memberContext;

	$db = database();

	loadTemplate('ProfileOptions');

	// We want to view what we're doing :P
	$context['sub_template'] = 'editBuddies';

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

			// Add the new member to the buddies array.
			while ($row = $db->fetch_assoc($request))
				$buddiesArray[] = (int) $row['id_member'];
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
		loadMemberContext($buddy);
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
function action_editIgnoreList($memID)
{
	global $txt, $scripturl, $modSettings;
	global $context, $user_profile, $memberContext;

	$db = database();

	loadTemplate('ProfileOptions');

	// We want to view what we're doing :P
	$context['sub_template'] = 'editIgnoreList';

	// For making changes!
	$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
	foreach ($ignoreArray as $k => $dummy)
		if ($dummy == '')
			unset($ignoreArray[$k]);

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
function action_account()
{
	global $context, $txt;

	$memID = currentMemberID();

	loadTemplate('ProfileOptions');

	loadThemeOptions($memID);
	if (allowedTo(array('profile_identity_own', 'profile_identity_any')))
		loadCustomFields($memID, 'account');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['account_info'];

	setupProfileContext(
		array(
			'member_name', 'real_name', 'date_registered', 'posts', 'lngfile', 'hr',
			'id_group', 'hr',
			'email_address', 'hide_email', 'show_online', 'hr',
			'passwrd1', 'passwrd2', 'hr',
			'secret_question', 'secret_answer',
		)
	);
}

/**
 * Allow the user to change the forum options in their profile.
 *
 */
function action_forumProfile()
{
	global $context, $user_profile, $user_info, $txt, $modSettings;

	$memID = currentMemberID();

	loadTemplate('ProfileOptions');

	loadThemeOptions($memID);
	if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
		loadCustomFields($memID, 'forumprofile');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['forumProfile_info'];
	$context['show_preview_button'] = true;

	setupProfileContext(
		array(
			'avatar_choice', 'hr', 'personal_text', 'hr',
			'bday1', 'location', 'gender', 'hr',
			'usertitle', 'signature', 'hr',
			'karma_good', 'hr',
			'website_title', 'website_url',
		)
	);
}

/**
 * Allow the edit of *someone elses* personal message settings.
 *
 */
function action_pmprefs()
{
	global $context, $txt, $scripturl;

	$memID = currentMemberID();

	loadThemeOptions($memID);
	loadCustomFields($memID, 'pmprefs');

	loadTemplate('ProfileOptions');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['pm_settings_desc'];

	setupProfileContext(
		array(
			'pm_prefs',
		)
	);
}

/**
 * Allow the user to pick a theme.
 *
 */
function action_themepick()
{
	global $txt, $context, $user_profile, $modSettings, $settings, $user_info;

	$memID = currentMemberID();

	loadThemeOptions($memID);
	if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
		loadCustomFields($memID, 'theme');

	loadTemplate('ProfileOptions');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['theme_info'];

	setupProfileContext(
		array(
			'id_theme', 'smiley_set', 'hr',
			'time_format', 'time_offset', 'hr',
			'theme_settings',
		)
	);
}

/**
 * Changing authentication method?
 * Only appropriate for people using OpenID.
 *
 * @param int $memID id_member
 * @param bool $saving = false
 */
function action_authentication($memID, $saving = false)
{
	global $context, $cur_profile, $txt, $post_errors, $modSettings;

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
				$passwd = sha1(strtolower($cur_profile['member_name']) . un_htmlspecialchars($_POST['passwrd1']));

				// Do the important bits.
				updateMemberData($memID, array('openid_uri' => '', 'passwd' => $passwd));
				if ($context['user']['is_owner'])
				{
					setLoginCookie(60 * $modSettings['cookieTime'], $memID, sha1(sha1(strtolower($cur_profile['member_name']) . un_htmlspecialchars($_POST['passwrd2'])) . $cur_profile['password_salt']));
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
			$_POST['openid_identifier'] = openID_canonize($_POST['openid_identifier']);

			if (openid_member_exists($_POST['openid_identifier']))
				$post_errors[] = 'openid_in_use';
			elseif (empty($post_errors))
			{
				// Authenticate using the new OpenID URI first to make sure they didn't make a mistake.
				if ($context['user']['is_owner'])
				{
					$_SESSION['new_openid_uri'] = $_POST['openid_identifier'];

					openID_validate($_POST['openid_identifier'], false, null, 'change_uri');
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
}

/**
 * Display the notifications and settings for changes.
 *
 */
function action_notification()
{
	global $txt, $scripturl, $user_profile, $user_info, $context, $modSettings, $settings;

	$db = database();

	loadTemplate('ProfileOptions');

	$memID = currentMemberID();

	// Gonna want this for the list.
	require_once(SUBSDIR . '/List.subs.php');

	// Fine, start with the board list.
	$listOptions = array(
		'id' => 'board_notification_list',
		'width' => '100%',
		'no_items_label' => $txt['notifications_boards_none'] . '<br /><br />' . $txt['notifications_boards_howto'],
		'no_items_align' => 'left',
		'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification',
		'default_sort_col' => 'board_name',
		'get_items' => array(
			'function' => 'list_getBoardNotifications',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'board_name' => array(
				'header' => array(
					'value' => $txt['notifications_boards'],
					'class' => 'lefttext first_th',
				),
				'data' => array(
					'function' => create_function('$board', '
						global $settings, $txt;

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
					'style' => 'width: 4%;',
					'class' => 'centertext',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d" class="input_check" %2$s />',
						'params' => array(
							'id' => false,
							'checked' => false,
						),
					'class' => 'centertext',
					),
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
				'value' => '<input type="submit" name="edit_notify_boards" value="' . $txt['notifications_boards_update'] . '" class="button_submit" />',
			),
			array(
				'position' => 'after_title',
				'value' => getBoardNotificationsCount($memID) == 0 ? $txt['notifications_boards_none'] . '<br />' . $txt['notifications_boards_howto'] : $txt['notifications_boards_current'],
				'class' => 'windowbg2',
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
			'function' => 'list_getTopicNotifications',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getTopicNotificationCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['notifications_topics'],
					'class' => 'lefttext first_th',
				),
				'data' => array(
					'function' => create_function('$topic', '
						global $settings, $txt;

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
					'style' => 'width: 4%;',
					'class' => 'centertext',
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
				'value' => '<input type="submit" name="edit_notify_topics" value="' . $txt['notifications_update'] . '" class="button_submit" />',
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
 * @return string
 */
function list_getTopicNotificationCount($memID)
{
	global $user_info, $context, $modSettings;

	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln' . (!$modSettings['postmod_active'] && $user_info['query_see_board'] === '1=1' ? '' : '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)') . ($user_info['query_see_board'] === '1=1' ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)') . '
		WHERE ln.id_member = {int:selected_member}' . ($user_info['query_see_board'] === '1=1' ? '' : '
			AND {query_see_board}') . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : ''),
		array(
			'selected_member' => $memID,
			'is_approved' => 1,
		)
	);
	list ($totalNotifications) = $db->fetch_row($request);
	$db->free_result($request);

	// @todo make this an integer before it gets returned
	return $totalNotifications;
}

/**
 * Callback for createList() in action_notification()
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $memID id_member
 * @return array $notification_topics
 */
function list_getTopicNotifications($start, $items_per_page, $sort, $memID)
{
	global $txt, $scripturl, $user_info, $context, $modSettings;

	$db = database();

	// All the topics with notification on...
	$request = $db->query('', '
		SELECT
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name,
			t.id_topic, ms.subject, ms.id_member, IFNULL(mem.real_name, ms.poster_name) AS real_name_col,
			ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
			IFNULL(mem2.real_name, ml.poster_name) AS last_real_name
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:items_per_page}',
		array(
			'current_member' => $user_info['id'],
			'is_approved' => 1,
			'selected_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	$notification_topics = array();
	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['subject']);

		$notification_topics[] = array(
			'id' => $row['id_topic'],
			'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
			'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
			'subject' => $row['subject'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'updated' => relativeTime($row['poster_time']),
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
			'board_link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
		);
	}
	$db->free_result($request);

	return $notification_topics;
}

function getBoardNotificationsCount($memID)
{
	global $user_info;

	$db = database();

	// All the boards that you have notification enabled
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}',
		array(
			'current_member' => $user_info['id'],
			'selected_member' => $memID,
		)
	);
	list ($totalNotifications) = $db->fetch_row($request);
	$db->free_result($request);

	return $totalNotifications;
}

/**
 * Callback for createList() in action_notification()
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $memID id_member
 * @return array
 */
function list_getBoardNotifications($start, $items_per_page, $sort, $memID)
{
	global $txt, $scripturl, $user_info, $modSettings;

	$db = database();

	// All the boards that you have notification enabled
	$request = $db->query('', '
		SELECT b.id_board, b.name, IFNULL(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
		ORDER BY ' . $sort,
		array(
			'current_member' => $user_info['id'],
			'selected_member' => $memID,
		)
	);

	$notification_boards = array();
	while ($row = $db->fetch_assoc($request))
		$notification_boards[] = array(
			'id' => $row['id_board'],
			'name' =>  $row['name'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' .'<strong>' . $row['name'] . '</strong></a>',
			'new' => $row['board_read'] < $row['id_msg_updated'],
			'checked' => 'checked="checked"',
		);
	$db->free_result($request);

	// and all the boards that you can see but don't have notify turned on for
	$request = $db->query('', '
		SELECT b.id_board, b.name, IFNULL(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_board = b.id_board AND ln.id_member = {int:selected_member})
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE {query_see_board}
			AND ln.id_board is null ' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
		ORDER BY ' . $sort,
		array(
			'selected_member' => $memID,
			'current_member' => $user_info['id'],
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	while ($row = $db->fetch_assoc($request))
		$notification_boards[] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'new' => $row['board_read'] < $row['id_msg_updated'],
			'checked' => '',
		);
	$db->free_result($request);

	return $notification_boards;
}

/**
 * Load the options for an user.
 *
 * @param int $memID id_member
 */
function loadThemeOptions($memID)
{
	global $context, $options, $cur_profile;

	$db = database();

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
		$request = $db->query('', '
			SELECT id_member, variable, value
			FROM {db_prefix}themes
			WHERE id_theme IN (1, {int:member_theme})
				AND id_member IN (-1, {int:selected_member})',
			array(
				'member_theme' => (int) $cur_profile['id_theme'],
				'selected_member' => $memID,
			)
		);
		$temp = array();
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['id_member'] == -1)
			{
				$temp[$row['variable']] = $row['value'];
				continue;
			}

			if (isset($_POST['options'][$row['variable']]))
				$row['value'] = $_POST['options'][$row['variable']];
			$context['member']['options'][$row['variable']] = $row['value'];
		}
		$db->free_result($request);

		// Load up the default theme options for any missing.
		foreach ($temp as $k => $v)
		{
			if (!isset($context['member']['options'][$k]))
				$context['member']['options'][$k] = $v;
		}
	}
}

/**
 * Allows the user to see the list of their ignored boards.
 * (and un-ignore them)
 *
 */
function action_ignoreboards()
{
	global $context, $modSettings, $cur_profile;

	$memID = currentMemberID();

	// Have the admins enabled this option?
	if (empty($modSettings['allow_ignore_boards']))
		fatal_lang_error('ignoreboards_disallowed', 'user');

	loadTemplate('ProfileOptions');

	$context['sub_template'] = 'ignoreboards';
	require_once(SUBSDIR . '/Boards.subs.php');
	$context += getBoardList(array('use_permissions' => true, 'not_redirection' => true, 'ignore' => !empty($cur_profile['ignore_boards']) ? explode(',', $cur_profile['ignore_boards']) : array()));

	// Include a list of boards per category for easy toggling.
	foreach ($context['categories'] as &$category)
		$category['child_ids'] = array_keys($category['boards']);

	loadThemeOptions($memID);
}

/**
 * Function to allow the user to choose group membership etc...
 *
 */
function action_groupMembership()
{
	global $txt, $scripturl, $user_profile, $user_info, $context, $modSettings;

	$db = database();

	$memID = currentMemberID();

	loadTemplate('ProfileOptions');

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
 * @param array $profile_vars
 * @param array $post_errors
 * @param int $memID id_member
 * @return mixed
 */
function action_groupMembership2($profile_vars, $post_errors, $memID)
{
	global $user_info, $context, $user_profile, $modSettings, $txt, $scripturl, $language;

	$db = database();

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
	// Protected groups too!
	else
	{
		$is_protected = membergroupsById($group_id);

		if ($is_protected['group_type'] == 1)
			isAllowedTo('admin_forum');
	}

	// What ever we are doing, we need to determine if changing primary is possible!
	$groups_details = membergroupsById(array($group_id, $old_profile['id_group']), 0, true);
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
			$moderators = membersAllowedTo('moderate_board', $board);
			$result = getBasicMemberData($moderators, array('preferences' => true, 'sort' => 'lngfile'));

			foreach ($result as $row)
			{
				if ($row['notify_types'] != 4)
					continue;

				// Check whether they are interested.
				if (!empty($row['mod_prefs']))
				{
					list(,, $pref_binary) = explode('|', $row['mod_prefs']);
					if (!($pref_binary & 4))
						continue;
				}

				$replacements = array(
					'RECPNAME' => $row['member_name'],
					'APPYNAME' => $old_profile['member_name'],
					'GROUPNAME' => $group_name,
					'REASON' => $_POST['reason'],
					'MODLINK' => $scripturl . '?action=moderate;area=groups;sa=requests',
				);

				$emaildata = loadEmailTemplate('request_membership', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
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
		if (empty($id))
			unset($addGroups[$id]);
	$addGroups = implode(',', array_flip($addGroups));

	// Ensure that we don't cache permissions if the group is changing.
	if ($context['user']['is_owner'])
		$_SESSION['mc']['time'] = 0;
	else
		updateSettings(array('settings_updated' => time()));

	updateMemberData($memID, array('id_group' => $newPrimary, 'additional_groups' => $addGroups));

	return $changeType;
}