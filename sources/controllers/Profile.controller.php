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
 * and such things.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Allow the change or view of profiles...
 */
function action_modifyprofile()
{
	global $txt, $scripturl, $user_info, $context, $user_profile, $cur_profile;
	global $modSettings, $memberContext, $profile_vars, $post_errors, $options, $user_settings;

	$db = database();

	// Don't reload this as we may have processed error strings.
	if (empty($post_errors))
		loadLanguage('Profile+Drafts');
	loadTemplate('Profile');

	require_once(SUBSDIR . '/Menu.subs.php');
	require_once(SUBSDIR . '/Profile.subs.php');

	$memID = currentMemberID();
	$context['id_member'] = $memID;
	$cur_profile = $user_profile[$memID];

	// Let's have some information about this member ready, too.
	loadMemberContext($memID);
	$context['member'] = $memberContext[$memID];

	// Is this the profile of the user himself or herself?
	$context['user']['is_owner'] = $memID == $user_info['id'];

	/* Define all the sections within the profile area!
		We start by defining the permission required - then we take this and turn it into the relevant context ;)
		Possible fields:
			For Section:
				string $title:		Section title.
				array $areas:		Array of areas within this section.

			For Areas:
				string $label:		Text string that will be used to show the area in the menu.
				string $file:		Optional text string that may contain a file name that's needed for inclusion in order to display the area properly.
				string $custom_url:	Optional href for area.
				string $function:	Function to execute for this section.
				bool $enabled:		Should area be shown?
				string $sc:			Session check validation to do on save - note without this save will get unset - if set.
				bool $hidden:		Does this not actually appear on the menu?
				bool $password:		Whether to require the user's password in order to save the data in the area.
				array $subsections:	Array of subsections, in order of appearance.
				array $permission:	Array of permissions to determine who can access this area. Should contain arrays $own and $any.
	*/
	$profile_areas = array(
		'info' => array(
			'title' => $txt['profileInfo'],
			'areas' => array(
				'summary' => array(
					'label' => $txt['summary'],
					'file' => '/controllers/ProfileInfo.controller.php',
					'function' => 'action_summary',
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
				'statistics' => array(
					'label' => $txt['statPanel'],
					'file' => '/controllers/ProfileInfo.controller.php',
					'function' => 'action_statPanel',
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
				'showposts' => array(
					'label' => $txt['showPosts'],
					'file' => '/controllers/ProfileInfo.controller.php',
					'function' => 'action_showPosts',
					'subsections' => array(
						'messages' => array($txt['showMessages'], array('profile_view_own', 'profile_view_any')),
						'topics' => array($txt['showTopics'], array('profile_view_own', 'profile_view_any')),
						'disregardedtopics' => array($txt['showDisregarded'], array('profile_view_own', 'profile_view_any'), 'enabled' => $modSettings['enable_disregard'] && $context['user']['is_owner']),
						'attach' => array($txt['showAttachments'], array('profile_view_own', 'profile_view_any')),
					),
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
				'showdrafts' => array(
					'label' => $txt['drafts_show'],
					'file' => '/controllers/Draft.controller.php',
					'controller' => 'Draft_Controller',
					'function' => 'action_showProfileDrafts',
					'enabled' => !empty($modSettings['drafts_enabled']) && $context['user']['is_owner'],
					'permission' => array(
						'own' => 'profile_view_own',
						'any' =>  array(),
					),
				),
				'permissions' => array(
					'label' => $txt['showPermissions'],
					'file' => '/controllers/ProfileInfo.controller.php',
					'function' => 'action_showPermissions',
					'permission' => array(
						'own' => 'manage_permissions',
						'any' => 'manage_permissions',
					),
				),
				'history' => array(
					'label' => $txt['history'],
					'file' => '/controllers/ProfileHistory.controller.php',
					'function' => 'action_history',
					'subsections' => array(
						'activity' => array($txt['trackActivity'], 'moderate_forum'),
						'ip' => array($txt['trackIP'], 'moderate_forum'),
						'edits' => array($txt['trackEdits'], 'moderate_forum'),
						'logins' => array($txt['trackLogins'], array('profile_view_own', 'moderate_forum')),
					),
					'permission' => array(
						'own' => 'moderate_forum',
						'any' => 'moderate_forum',
					),
				),
				'viewwarning' => array(
					'label' => $txt['profile_view_warnings'],
					'enabled' => in_array('w', $context['admin_features']) && !empty($modSettings['warning_enable']) && $cur_profile['warning'] && (!empty($modSettings['warning_show']) && ($context['user']['is_owner'] || $modSettings['warning_show'] == 2)),
					'file' => '/controllers/ProfileInfo.controller.php',
					'function' => 'action_viewWarning',
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'issue_warning',
					),
				),
			),
		),
		'edit_profile' => array(
			'title' => $txt['profileEdit'],
			'areas' => array(
				'account' => array(
					'label' => $txt['account'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_account',
					'enabled' => $context['user']['is_admin'] || ($cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups']))),
					'sc' => 'post',
					'token' => 'profile-ac%u',
					'password' => true,
					'permission' => array(
						'own' => array('profile_identity_any', 'profile_identity_own', 'manage_membergroups'),
						'any' => array('profile_identity_any', 'manage_membergroups'),
					),
				),
				'forumprofile' => array(
					'label' => $txt['forumprofile'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_forumProfile',
					'sc' => 'post',
					'token' => 'profile-fp%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own', 'profile_title_own', 'profile_title_any'),
						'any' => array('profile_extra_any', 'profile_title_any'),
					),
				),
				'theme' => array(
					'label' => $txt['theme'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_themepick',
					'sc' => 'post',
					'token' => 'profile-th%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'authentication' => array(
					'label' => $txt['authentication'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_authentication',
					'enabled' => !empty($modSettings['enableOpenID']) || !empty($cur_profile['openid_uri']),
					'sc' => 'post',
					'token' => 'profile-au%u',
					'hidden' => empty($modSettings['enableOpenID']) && empty($cur_profile['openid_uri']),
					'password' => true,
					'permission' => array(
						'own' => array('profile_identity_any', 'profile_identity_own'),
						'any' => array('profile_identity_any'),
					),
				),
				'notification' => array(
					'label' => $txt['notification'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_notification',
					'sc' => 'post',
					'token' => 'profile-nt%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				// Without profile_extra_own, settings are accessible from the PM section.
				'pmprefs' => array(
					'label' => $txt['pmprefs'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_pmprefs',
					'enabled' => allowedTo(array('profile_extra_own', 'profile_extra_any')),
					'sc' => 'post',
					'token' => 'profile-pm%u',
					'permission' => array(
						'own' => array('pm_read'),
						'any' => array('profile_extra_any'),
					),
				),
				'ignoreboards' => array(
					'label' => $txt['ignoreboards'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_ignoreboards',
					'enabled' => !empty($modSettings['allow_ignore_boards']),
					'sc' => 'post',
					'token' => 'profile-ib%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'lists' => array(
					'label' => $txt['editBuddyIgnoreLists'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_editBuddyIgnoreLists',
					'enabled' => !empty($modSettings['enable_buddylist']) && $context['user']['is_owner'],
					'sc' => 'post',
					'token' => 'profile-bl%u',
					'subsections' => array(
						'buddies' => array($txt['editBuddies']),
						'ignore' => array($txt['editIgnoreList']),
					),
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array(),
					),
				),
				'groupmembership' => array(
					'label' => $txt['groupmembership'],
					'file' => '/controllers/ProfileOptions.controller.php',
					'function' => 'action_groupMembership',
					'enabled' => !empty($modSettings['show_group_membership']) && $context['user']['is_owner'],
					'sc' => 'request',
					'token' => 'profile-gm%u',
					'token_type' => 'request',
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('manage_membergroups'),
					),
				),
			),
		),
		'profile_action' => array(
			'title' => $txt['profileAction'],
			'areas' => array(
				'sendpm' => array(
					'label' => $txt['profileSendIm'],
					'custom_url' => $scripturl . '?action=pm;sa=send',
					'permission' => array(
						'own' => array(),
						'any' => array('pm_send'),
					),
				),
				'issuewarning' => array(
					'label' => $txt['profile_issue_warning'],
					'enabled' => in_array('w', $context['admin_features']) && !empty($modSettings['warning_enable']) && (!$context['user']['is_owner'] || $context['user']['is_admin']),
					'file' => '/controllers/ProfileAccount.controller.php',
					'function' => 'action_issuewarning',
					'token' => 'profile-iw%u',
					'permission' => array(
						'own' => array('issue_warning'),
						'any' => array('issue_warning'),
					),
				),
				'banuser' => array(
					'label' => $txt['profileBanUser'],
					'custom_url' => $scripturl . '?action=admin;area=ban;sa=add',
					'enabled' => $cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups'])),
					'permission' => array(
						'own' => array(),
						'any' => array('manage_bans'),
					),
				),
				'subscriptions' => array(
					'label' => $txt['subscriptions'],
					'file' => '/controllers/ProfileSubscriptions.controller.php',
					'function' => 'action_subscriptions',
					'enabled' => !empty($modSettings['paid_enabled']),
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('moderate_forum'),
					),
				),
				'deleteaccount' => array(
					'label' => $txt['deleteAccount'],
					'file' => '/controllers/ProfileAccount.controller.php',
					'function' => 'action_deleteaccount',
					'sc' => 'post',
					'token' => 'profile-da%u',
					'password' => true,
					'permission' => array(
						'own' => array('profile_remove_any', 'profile_remove_own'),
						'any' => array('profile_remove_any'),
					),
				),
				'activateaccount' => array(
					'file' => '/controllers/ProfileAccount.controller.php',
					'function' => 'action_activateaccount',
					'sc' => 'get',
					'token' => 'profile-aa%u',
					'select' => 'summary',
					'permission' => array(
						'own' => array(),
						'any' => array('moderate_forum'),
					),
				),
			),
		),
	);

	// Let them modify profile areas easily.
	call_integration_hook('integrate_profile_areas', array(&$profile_areas));

	// Do some cleaning ready for the menu function.
	$context['password_areas'] = array();
	$current_area = isset($_REQUEST['area']) ? $_REQUEST['area'] : '';

	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// If it said no permissions that meant it wasn't valid!
			if (empty($area['permission'][$context['user']['is_owner'] ? 'own' : 'any']))
				$profile_areas[$section_id]['areas'][$area_id]['enabled'] = false;
			// Otherwise pick the right set.
			else
				$profile_areas[$section_id]['areas'][$area_id]['permission'] = $area['permission'][$context['user']['is_owner'] ? 'own' : 'any'];

			// Password required - only if not on OpenID.
			if (!empty($area['password']))
				$context['password_areas'][] = $area_id;
		}
	}

	// Is there an updated message to show?
	if (isset($_GET['updated']))
		$context['profile_updated'] = $txt['profile_updated_own'];

	// Set a few options for the menu.
	$menuOptions = array(
		'disable_url_session_check' => true,
		'current_area' => $current_area,
		'extra_url_parameters' => array(
			'u' => $context['id_member'],
		),
	);

	// Actually create the menu!
	$profile_include_data = createMenu($profile_areas, $menuOptions);

	// No menu means no access.
	if (!$profile_include_data && (!$user_info['is_guest'] || validateSession()))
		fatal_lang_error('no_access', false);

	// Make a note of the Unique ID for this menu.
	$context['profile_menu_id'] = $context['max_menu_id'];
	$context['profile_menu_name'] = 'menu_data_' . $context['profile_menu_id'];

	// Set the selected item - now it's been validated.
	$current_area = $profile_include_data['current_area'];
	$context['menu_item_selected'] = $current_area;

	// Before we go any further, let's work on the area we've said is valid.
	// Note this is done here just in case we ever compromise the menu function in error!
	$context['completed_save'] = false;
	$context['do_preview'] = isset($_REQUEST['preview_signature']);

	$security_checks = array();
	$found_area = false;
	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Is this our area?
			if ($current_area == $area_id)
			{
				// This can't happen - but is a security check.
				if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($area['enabled']) && $area['enabled'] == false))
					fatal_lang_error('no_access', false);

				// Are we saving data in a valid area?
				if (isset($area['sc']) && (isset($_REQUEST['save']) || $context['do_preview']))
				{
					$security_checks['session'] = $area['sc'];
					$context['completed_save'] = true;
				}

				// Do we need to perform a token check?
				if (!empty($area['token']))
				{
					$security_checks[isset($_REQUEST['save']) ? 'validateToken' : 'needsToken'] = $area['token'];
					$token_name = $area['token'] !== true ? str_replace('%u', $context['id_member'], $area['token']) : 'profile-u' . $context['id_member'];

					$token_type = isset($area['token_type']) && in_array($area['token_type'], array('request', 'post', 'get')) ? $area['token_type'] : 'post';
				}

				// Does this require session validating?
				if (!empty($area['validate']) || (isset($_REQUEST['save']) && !$context['user']['is_owner']))
					$security_checks['validate'] = true;

				// Permissions for good measure.
				if (!empty($profile_include_data['permission']))
					$security_checks['permission'] = $profile_include_data['permission'];

				// Either way got something.
				$found_area = true;
			}
		}
	}

	// Oh dear, some serious security lapse is going on here... we'll put a stop to that!
	if (!$found_area)
		fatal_lang_error('no_access', false);

	// Release this now.
	unset($profile_areas);

	// Now the context is setup have we got any security checks to carry out additional to that above?
	if (isset($security_checks['validateToken']))
		validateToken($token_name, $token_type);
	if (isset($security_checks['session']))
		checkSession($security_checks['session']);
	if (isset($security_checks['validate']))
		validateSession();
	if (isset($security_checks['permission']))
		isAllowedTo($security_checks['permission']);

	// Create a token if needed.
	if (isset($security_checks['needsToken']) || isset($security_checks['validateToken']))
	{
		createToken($token_name, $token_type);
		$context['token_check'] = $token_name;
	}

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : ''),
		'name' => sprintf($txt['profile_of_username'], $context['member']['name']),
	);

	if (!empty($profile_include_data['label']))
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'],
			'name' => $profile_include_data['label'],
		);

	if (!empty($profile_include_data['current_subsection']) && $profile_include_data['subsections'][$profile_include_data['current_subsection']][0] != $profile_include_data['label'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'] . ';sa=' . $profile_include_data['current_subsection'],
			'name' => $profile_include_data['subsections'][$profile_include_data['current_subsection']][0],
		);

	// Set the template for this area... if you still can :P
	// and add the profile layer.
	$context['sub_template'] = $profile_include_data['function'];
	Template_Layers::getInstance()->add('profile');

	// All the subactions that require a user password in order to validate.
	$check_password = $context['user']['is_owner'] && in_array($profile_include_data['current_area'], $context['password_areas']);
	$context['require_password'] = $check_password && empty($user_settings['openid_uri']);

	// These will get populated soon!
	$post_errors = array();
	$profile_vars = array();

	// Right - are we saving - if so let's save the old data first.
	if ($context['completed_save'])
	{
		// Clean up the POST variables.
		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		if ($check_password)
		{
			// If we're using OpenID try to revalidate.
			if (!empty($user_settings['openid_uri']))
			{
				require_once(SUBSDIR . '/OpenID.subs.php');
				openID_revalidate();
			}
			else
			{
				// You didn't even enter a password!
				if (trim($_POST['oldpasswrd']) == '')
					$post_errors[] = 'no_password';

				// Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
				$_POST['oldpasswrd'] = un_htmlspecialchars($_POST['oldpasswrd']);

				// Does the integration want to check passwords?
				$good_password = in_array(true, call_integration_hook('integrate_verify_password', array($cur_profile['member_name'], $_POST['oldpasswrd'], false)), true);

				// Bad password!!!
				if (!$good_password && $user_info['passwd'] != sha1(strtolower($user_profile[$memID]['member_name']) . un_htmlspecialchars(stripslashes($_POST['oldpasswrd']))))
					$post_errors[] = 'bad_password';

				// Warn other elements not to jump the gun and do custom changes!
				if (in_array('bad_password', $post_errors))
					$context['password_auth_failed'] = true;
			}
		}

		// Change the IP address in the database.
		if ($context['user']['is_owner'])
			$profile_vars['member_ip'] = $user_info['ip'];

		// Now call the sub-action function...
		if ($current_area == 'activateaccount')
		{
			if (empty($post_errors))
				action_activateaccount();
		}
		elseif ($current_area == 'deleteaccount')
		{
			if (empty($post_errors))
			{
				action_deleteaccount2($memID);
				redirectexit();
			}
		}
		elseif ($current_area == 'groupmembership' && empty($post_errors))
		{
			$msg = action_groupMembership2($profile_vars, $post_errors, $memID);

			// Whatever we've done, we have nothing else to do here...
			redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $memID) . ';area=groupmembership' . (!empty($msg) ? ';msg=' . $msg : ''));
		}
		// Authentication changes?
		elseif ($current_area == 'authentication')
			action_authentication($memID, true);
		elseif (in_array($current_area, array('account', 'forumprofile', 'theme', 'pmprefs')))
			saveProfileFields();
		else
		{
			$force_redirect = true;
			saveProfileChanges($profile_vars, $post_errors, $memID);
		}

		call_integration_hook('integrate_profile_save', array(&$profile_vars, &$post_errors, $memID));

		// There was a problem, let them try to re-enter.
		if (!empty($post_errors))
		{
			// Load the language file so we can give a nice explanation of the errors.
			loadLanguage('Errors');
			$context['post_errors'] = $post_errors;
		}
		elseif (!empty($profile_vars))
		{
			// If we've changed the password, notify any integration that may be listening in.
			if (isset($profile_vars['passwd']))
				call_integration_hook('integrate_reset_pass', array($cur_profile['member_name'], $cur_profile['member_name'], $_POST['passwrd2']));

			updateMemberData($memID, $profile_vars);

			// What if this is the newest member?
			if ($modSettings['latestMember'] == $memID)
				updateStats('member');
			elseif (isset($profile_vars['real_name']))
				updateSettings(array('memberlist_updated' => time()));

			// If the member changed his/her birthdate, update calendar statistics.
			if (isset($profile_vars['birthdate']) || isset($profile_vars['real_name']))
				updateSettings(array(
					'calendar_updated' => time(),
				));

			// Anything worth logging?
			if (!empty($context['log_changes']) && !empty($modSettings['modlog_enabled']))
			{
				$log_changes = array();
				foreach ($context['log_changes'] as $k => $v)
					$log_changes[] = array(
						'action' => $k,
						'log_type' => 'user',
						'extra' => array_merge($v, array(
							'applicator' => $user_info['id'],
							'member_affected' => $memID,
						)),
					);

				logActions($log_changes);
			}

			// Have we got any post save functions to execute?
			if (!empty($context['profile_execute_on_save']))
				foreach ($context['profile_execute_on_save'] as $saveFunc)
					$saveFunc();

			// Let them know it worked!
			$context['profile_updated'] = $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']);

			// Invalidate any cached data.
			cache_put_data('member_data-profile-' . $memID, null, 0);
		}
	}

	// Have some errors for some reason?
	if (!empty($post_errors))
	{
		// Set all the errors so the template knows what went wrong.
		foreach ($post_errors as $error_type)
			$context['modify_error'][$error_type] = true;
	}
	// If it's you then we should redirect upon save.
	elseif (!empty($profile_vars) && $context['user']['is_owner'] && !$context['do_preview'])
		redirectexit('action=profile;area=' . $current_area . ';updated');
	elseif (!empty($force_redirect))
		redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $memID) . ';area=' . $current_area);

	if (isset($profile_include_data['file']))
		require_once(SOURCEDIR . '/' . $profile_include_data['file']);

	callMenu($profile_include_data);

	// Set the page title if it's not already set...
	if (!isset($context['page_title']))
		$context['page_title'] = $txt['profile'] . (isset($txt[$current_area]) ? ' - ' . $txt[$current_area] : '');
}
