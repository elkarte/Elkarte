<?php

/**
 * Handles the retreving and display of a users posts, attachments, stats, permissions
 * warnings and the like
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
 * ProfileInfo_Controller class, access all profile summary areas for a user
 * incuding overall summary, post listing, attachment listing, user statistics
 * user permisssions, user warnings
 */
class ProfileInfo_Controller extends Action_Controller
{
	/**
	 * Intended as entry point which delegates to methods in this class...
	 */
	public function action_index()
	{
		// what do we do, do you even know what you do?
		// $this->action_showPosts();
	}

	/**
	 * View the user profile summary.
	 * @uses ProfileInfo template
	 */
	public function action_summary()
	{
		global $context, $memberContext, $txt, $modSettings, $user_info, $user_profile, $scripturl, $settings;

		$memID = currentMemberID();

		// Attempt to load the member's profile data.
		if (!loadMemberContext($memID) || !isset($memberContext[$memID]))
			fatal_lang_error('not_a_user', false);

		loadTemplate('ProfileInfo');

		// Set up the stuff and load the user.
		$context += array(
			'page_title' => sprintf($txt['profile_of_username'], $memberContext[$memID]['name']),
			'can_send_pm' => allowedTo('pm_send'),
			'can_send_email' => allowedTo('send_email_to_members'),
			'can_have_buddy' => allowedTo('profile_identity_own') && !empty($modSettings['enable_buddylist']),
			'can_issue_warning' => in_array('w', $context['admin_features']) && allowedTo('issue_warning') && !empty($modSettings['warning_enable']),
		);
		$context['member'] = &$memberContext[$memID];
		$context['can_view_warning'] = in_array('w', $context['admin_features']) && (allowedTo('issue_warning') && !$context['user']['is_owner']) || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $context['user']['is_owner']));

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?action=profile;u=' . $memID;

		// Are there things we don't show?
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

		// Menu tab
		$context[$context['profile_menu_name']]['tab_data'] = array();

		// Tab information for use in the summary page
		// Each tab template defines a div, the value of which are the template(s) to load in that div
		// Templates are named template_profile_block_YOURNAME
		$context['summarytabs'] = array(
			'summary' => array(
				'name' => $txt['summary'],
				'templates' => array(
					array('summary', 'user_info'),
					array('contact', 'other_info'),
					array('user_customprofileinfo', 'moderation'),
				),
				'active' => true,
			),
			'recent' => array(
				'name' => $txt['profile_recent_activity'],
				'templates' => array('posts', 'topics', 'attachments'),
				'active' => true,
			),
			'buddies' => array(
				'name' => $txt['buddies'],
				'templates' => array('buddies'),
				'active' => !empty($modSettings['enable_buddylist']) && $context['user']['is_owner'],
			),
		);

		// Let addons add or remove to the tabs array
		call_integration_hook('integrate_profile_summary', array($memID));

		// Go forward with whats left
		$summary_areas = '';
		foreach ($context['summarytabs'] as $id => $tab)
		{
			// If the tab is active we add it
			if ($tab['active'] !== true)
				unset($context['summarytabs'][$id]);
			else
			{
				// All the active templates, used to prevent processing data we don't need
				foreach ($tab['templates'] as $template)
					$summary_areas .= is_array($template) ? implode(',', $template) : ',' . $template;
			}
		}
		$summary_areas = explode(',', $summary_areas);

		// See if they have broken any warning levels...
		if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $context['member']['warning'])
			$context['warning_status'] = $txt['profile_warning_is_muted'];
		elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $context['member']['warning'])
			$context['warning_status'] = $txt['profile_warning_is_moderation'];
		elseif (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $context['member']['warning'])
			$context['warning_status'] = $txt['profile_warning_is_watch'];

		// They haven't even been registered for a full day!?
		$days_registered = (int) ((time() - $user_profile[$memID]['date_registered']) / (3600 * 24));
		if (empty($user_profile[$memID]['date_registered']) || $days_registered < 1)
			$context['member']['posts_per_day'] = $txt['not_applicable'];
		else
			$context['member']['posts_per_day'] = comma_format($context['member']['real_posts'] / $days_registered, 3);

		// Set the age...
		if (empty($context['member']['birth_date']))
		{
			$context['member'] += array(
				'age' => $txt['not_applicable'],
				'today_is_birthday' => false
			);
		}
		else
		{
			list ($birth_year, $birth_month, $birth_day) = sscanf($context['member']['birth_date'], '%d-%d-%d');
			$datearray = getdate(forum_time());
			$context['member'] += array(
				'age' => $birth_year <= 4 ? $txt['not_applicable'] : $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1),
				'today_is_birthday' => $datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day
			);
		}

		if (allowedTo('moderate_forum'))
		{
			// Make sure it's a valid ip address; otherwise, don't bother...
			if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $memberContext[$memID]['ip']) == 1 && empty($modSettings['disableHostnameLookup']))
				$context['member']['hostname'] = host_from_ip($memberContext[$memID]['ip']);
			else
				$context['member']['hostname'] = '';

			$context['can_see_ip'] = true;
		}
		else
			$context['can_see_ip'] = false;

		if (!empty($modSettings['who_enabled']))
		{
			include_once(SUBSDIR . '/Who.subs.php');
			$action = determineActions($user_profile[$memID]['url']);

			if ($action !== false)
				$context['member']['action'] = $action;
		}

		// If the user is awaiting activation, and the viewer has permission - setup some activation context messages.
		if ($context['member']['is_activated'] % 10 != 1 && allowedTo('moderate_forum'))
		{
			$context['activate_type'] = $context['member']['is_activated'];

			// What should the link text be?
			$context['activate_link_text'] = in_array($context['member']['is_activated'], array(3, 4, 5, 13, 14, 15)) ? $txt['account_approve'] : $txt['account_activate'];

			// Should we show a custom message?
			$context['activate_message'] = isset($txt['account_activate_method_' . $context['member']['is_activated'] % 10]) ? $txt['account_activate_method_' . $context['member']['is_activated'] % 10] : $txt['account_not_activated'];
			$context['activate_url'] = $scripturl . '?action=profile;save;area=activateaccount;u=' . $memID . ';' . $context['session_var'] . '=' . $context['session_id'] . ';' . $context['profile-aa' . $memID . '_token_var'] . '=' . $context['profile-aa' . $memID . '_token'];
		}

		// Is the signature even enabled on this forum?
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;

		// How about, are they banned?
		if (allowedTo('moderate_forum'))
		{
			require_once(SUBSDIR . '/Bans.subs.php');
			$hostname = !empty($context['member']['hostname']) ? $context['member']['hostname'] : '';
			$email = !empty($context['member']['email']) ? $context['member']['email'] : '';
			$context['member']['bans'] = BanCheckUser($memID, $hostname, $email);

			// Can they edit the ban?
			$context['can_edit_ban'] = allowedTo('manage_bans');
		}

		// Load up the most recent attachments for this user for use in profile views etc.
		$context['thumbs'] = array();
		if (!empty($modSettings['attachmentEnable']) && !empty($settings['attachments_on_summary']) && in_array('attachments', $summary_areas))
		{
			$boardsAllowed = boardsAllowedTo('view_attachments');
			if (empty($boardsAllowed))
				$boardsAllowed = array(-1);
			$attachments = $this->list_getAttachments(0, $settings['attachments_on_summary'], 'm.poster_time DESC', $boardsAllowed, $memID);

			// Load them in to $context for use in the template
			$i = 0;

			// @todo keep or loose the mime thumbs ... useful at all?
			$mime_images_url = $settings['default_images_url'] . '/mime_images/';
			$mime_path = $settings['default_theme_dir'] . '/images/mime_images/';

			for ($i = 0, $count = count($attachments); $i < $count; $i++)
			{
				$context['thumbs'][$i] = array(
					'url' => $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id'],
					'img' => '',
					'filename' => $attachments[$i]['filename'],
					'downloads' => $attachments[$i]['downloads'],
				);

				// Show a thumbnail image as well?
				if ($attachments[$i]['is_image'] && !empty($modSettings['attachmentShowImages']) && !empty($modSettings['attachmentThumbnails']))
				{
					if (!empty($attachments[$i]['id_thumb']))
						$context['thumbs'][$i]['img'] = '<img src="' . $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id_thumb'] . ';image" title="" alt="" />';
					else
					{
						// no thumbnail available ... use html instead
						if (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']))
						{
							if ($attachments[$i]['width'] > $modSettings['attachmentThumbWidth'] || $attachments[$i]['height'] > $modSettings['attachmentThumbHeight'])
								$context['thumbs'][$i]['img'] = '<img src="' . $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id'] . '" title="" alt="" width="' . $modSettings['attachmentThumbWidth'] . '" height="' . $modSettings['attachmentThumbHeight'] . '" />';
							else
								$context['thumbs'][$i]['img'] = '<img src="' . $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id'] . '" title="" alt="" width="' . $attachments[$i]['width'] . '" height="' . $attachments[$i]['height'] . '" />';
						}
					}
				}
				// Not an image so lets set a mime thumbnail based off the filetype
				else
				{
					if ((!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight'])) && (128 > $modSettings['attachmentThumbWidth'] || 128 > $modSettings['attachmentThumbHeight']))
						$context['thumbs'][$i]['img'] = '<img src="' . $mime_images_url . (!file_exists($mime_path . $attachments[$i]['fileext'] . '.png') ? 'default' : $attachments[$i]['fileext']) . '.png" title="" alt="" width="' . $modSettings['attachmentThumbWidth'] . '" height="' . $modSettings['attachmentThumbHeight'] . '" />';
					else
						$context['thumbs'][$i]['img'] = '<img src="' . $mime_images_url . (!file_exists($mime_path . $attachments[$i]['fileext'] . '.png') ? 'default' : $attachments[$i]['fileext']) . '.png" title="" alt="" />';
				}
			}
		}

		// Would you be mine? Could you be mine? Be my buddy :D
		if (!empty($modSettings['enable_buddylist']) && $context['user']['is_owner'] && !empty($user_info['buddies']) && in_array('buddies', $summary_areas))
		{
			$context['buddies'] = array();
			loadMemberData($user_info['buddies'], false, 'profile');

			// Get the info for this buddy
			foreach ($user_info['buddies'] as $buddy)
			{
				loadMemberContext($buddy);
				$context['buddies'][$buddy] = $memberContext[$buddy];
			}
		}

		// How about thier most recent posts?
		if (in_array('posts', $summary_areas))
		{
			// Is the load average too high just now, then let them know
			if (!empty($modSettings['loadavg_show_posts']) && $modSettings['current_load'] >= $modSettings['loadavg_show_posts'])
				$context['loadaverage'] = true;
			else
			{
				// Set up to get the last 10 psots of this member
				$msgCount = count_user_posts($memID);
				$range_limit = '';
				$maxIndex = 10;
				$start = (int) $_REQUEST['start'];

				// If they are a frequent poster, we guess the range to help minimize what the query work
				if ($msgCount > 1000)
				{
					list ($min_msg_member, $max_msg_member) = findMinMaxUserMessage($memID);
					$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
					$range_limit = 'm.id_msg > ' . ($max_msg_member - $margin);
				}

				// Find this user's most recent posts
				$rows = load_user_posts($memID, 0, $maxIndex, $range_limit);
				$context['posts'] = array();
				foreach ($rows as $row)
				{
					// Censor....
					censorText($row['body']);
					censorText($row['subject']);

					// Do the code.
					$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);
					$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));
					$preview = shorten_text($preview, !empty($modSettings['ssi_preview_length']) ? $modSettings['ssi_preview_length'] : 128);
					$short_subject = shorten_text($row['subject'], !empty($modSettings['ssi_subject_length']) ? $modSettings['ssi_subject_length'] : 24);

					// And the array...
					$context['posts'][] = array(
						'body' => $preview,
						'board' => array(
							'name' => $row['bname'],
							'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
						),
						'subject' => $row['subject'],
						'short_subject' => $short_subject,
						'time' => standardTime($row['poster_time']),
						'html_time' => htmlTime($row['poster_time']),
						'timestamp' => forum_time(true, $row['poster_time']),
						'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $short_subject . '</a>',
					);
				}
			}
		}

		// How about the most recent topics that they started?
		if (in_array('topics', $summary_areas))
		{
			// Is the load average still too high?
			if (!empty($modSettings['loadavg_show_posts']) && $modSettings['current_load'] >= $modSettings['loadavg_show_posts'])
				$context['loadaverage'] = true;
			else
			{
				// Set up to get the last 10 topics of this member
				$msgCount = count_user_topics($memID);
				$range_limit = '';
				$maxIndex = 10;

				// If they are a frequent topic starter, we guess the range to help the query
				if ($msgCount > 1000)
				{
					$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
					$margin *= 5;
					$range_limit = 't.id_first_msg > ' . ($max_msg_member - $margin);
				}

				// Find this user's most recent topics
				$rows = load_user_topics($memID, 0, $maxIndex, $range_limit);
				$context['topics'] = array();
				foreach ($rows as $row)
				{
					// Censor....
					censorText($row['body']);
					censorText($row['subject']);

					// Do the code.
					$short_subject = shorten_text($row['subject'], !empty($modSettings['ssi_subject_length']) ? $modSettings['ssi_subject_length'] : 24);

					// And the array...
					$context['topics'][] = array(
						'board' => array(
							'name' => $row['bname'],
							'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
						),
						'subject' => $row['subject'],
						'short_subject' => $short_subject,
						'time' => standardTime($row['poster_time']),
						'html_time' => htmlTime($row['poster_time']),
						'timestamp' => forum_time(true, $row['poster_time']),
						'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $short_subject . '</a>',
					);
				}
			}
		}

		// To finish this off, custom profile fields.
		require_once(SUBSDIR . '/Profile.subs.php');
		loadCustomFields($memID);

		// To make tabs work, we need jQueryUI
		$modSettings['jquery_include_ui'] = true;
		addInlineJavascript('
		$(function() {$( "#tabs" ).tabs();});', true);
	}

	/**
	 * Show all posts by the current user.
	 *
	 * @todo This function needs to be split up properly.
	 */
	public function action_showPosts()
	{
		global $txt, $user_info, $scripturl, $modSettings, $context, $user_profile, $board;

		$memID = currentMemberID();

		// Some initial context.
		$context['start'] = (int) $_REQUEST['start'];
		$context['current_member'] = $memID;

		loadTemplate('ProfileInfo');

		// Create the tabs for the template.
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['showPosts'],
			'description' => $txt['showPosts_help'],
			'class' => 'profile',
			'tabs' => array(
				'messages' => array(
				),
				'topics' => array(
				),
				'unwatchedtopics' => array(
				),
				'attach' => array(
				),
			),
		);

		// Set the page title
		$context['page_title'] = $txt['showPosts'] . ' - ' . $user_profile[$memID]['real_name'];

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_show_posts']) && $modSettings['current_load'] >= $modSettings['loadavg_show_posts'])
			fatal_lang_error('loadavg_show_posts_disabled', false);

		// If we're specifically dealing with attachments use that function!
		if (isset($_GET['sa']) && $_GET['sa'] == 'attach')
			return $this->action_showAttachments($memID);
		// Instead, if we're dealing with unwatched topics (and the feature is enabled) use that other function.
		elseif (isset($_GET['sa']) && $_GET['sa'] == 'unwatchedtopics' && $modSettings['enable_unwatch'])
			return $this->action_showUnwatched($memID);

		// Are we just viewing topics?
		$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

		// If just deleting a message, do it and then redirect back.
		if (isset($_GET['delete']) && !$context['is_topics'])
		{
			checkSession('get');

			// We need msg info for logging.
			require_once(SUBSDIR . '/Messages.subs.php');
			$info = basicMessageInfo((int) $_GET['delete'], true);

			// Trying to remove a message that doesn't exist.
			if (empty($info))
				redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);

			// We can be lazy, since removeMessage() will check the permissions for us.
			removeMessage((int) $_GET['delete']);

			// Add it to the mod log.
			if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info['id_member'] != $user_info['id']))
				logAction('delete', array('topic' => $info['id_topic'], 'subject' => $info['subject'], 'member' => $info['id_member'], 'board' => $info['id_board']));

			// Back to... where we are now ;).
			redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);
		}

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
			$_REQUEST['viewscount'] = '10';

		if ($context['is_topics'])
			$msgCount = count_user_topics($memID, $board);
		else
			$msgCount = count_user_posts($memID, $board);

		list ($min_msg_member, $max_msg_member) = findMinMaxUserMessage($memID, $board);
		$reverse = false;
		$range_limit = '';
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=showposts' . ($context['is_topics'] ? ';sa=topics' : ';sa=messages') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = $context['start'];
		$reverse = $_REQUEST['start'] > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
			$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
		}

		// Guess the range of messages to be shown to help minimize what the query needs to do
		if ($msgCount > 1000)
		{
			$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));

			// Make a bigger margin for topics only.
			if ($context['is_topics'])
			{
				$margin *= 5;
				$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
			}
			else
				$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
		}

		// Find this user's posts or topics started
		if ($context['is_topics'])
			$rows = load_user_topics($memID, $start, $maxIndex, $range_limit, $reverse, $board);
		else
			$rows = load_user_posts($memID, $start, $maxIndex, $range_limit, $reverse, $board);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = array();
		$board_ids = array('own' => array(), 'any' => array());
		foreach ($rows as $row)
		{
			// Censor....
			censorText($row['body']);
			censorText($row['subject']);

			// Do the code.
			$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

			// And the array...
			$context['posts'][$counter += $reverse ? -1 : 1] = array(
				'body' => $row['body'],
				'counter' => $counter,
				'alternate' => $counter % 2,
				'category' => array(
					'name' => $row['cname'],
					'id' => $row['id_cat']
				),
				'board' => array(
					'name' => $row['bname'],
					'id' => $row['id_board']
				),
				'topic' => $row['id_topic'],
				'subject' => $row['subject'],
				'start' => 'msg' . $row['id_msg'],
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id' => $row['id_msg'],
				'can_reply' => false,
				'can_mark_notify' => false,
				'can_delete' => false,
				'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
				'approved' => $row['approved'],
			);

			if ($user_info['id'] == $row['id_member_started'])
				$board_ids['own'][$row['id_board']][] = $counter;
			$board_ids['any'][$row['id_board']][] = $counter;
		}

		// All posts were retrieved in reverse order, get them right again.
		if ($reverse)
			$context['posts'] = array_reverse($context['posts'], true);

		// These are all the permissions that are different from board to board..
		if ($context['is_topics'])
			$permissions = array(
				'own' => array(
					'post_reply_own' => 'can_reply',
				),
				'any' => array(
					'post_reply_any' => 'can_reply',
					'mark_any_notify' => 'can_mark_notify',
				)
			);
		else
			$permissions = array(
				'own' => array(
					'post_reply_own' => 'can_reply',
					'delete_own' => 'can_delete',
				),
				'any' => array(
					'post_reply_any' => 'can_reply',
					'mark_any_notify' => 'can_mark_notify',
					'delete_any' => 'can_delete',
				)
			);

		// For every permission in the own/any lists...
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// Get the boards they can do this on...
				$boards = boardsAllowedTo($permission);

				// Hmm, they can do it on all boards, can they?
				if (!empty($boards) && $boards[0] == 0)
					$boards = array_keys($board_ids[$type]);

				// Now go through each board they can do the permission on.
				foreach ($boards as $board_id)
				{
					// There aren't any posts displayed from this board.
					if (!isset($board_ids[$type][$board_id]))
						continue;

					// Set the permission to true ;).
					foreach ($board_ids[$type][$board_id] as $counter)
						$context['posts'][$counter][$allowed] = true;
				}
			}
		}

		// Clean up after posts that cannot be deleted and quoted.
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
		foreach ($context['posts'] as $counter => $dummy)
		{
			$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
			$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
		}
	}

	/**
	 * Show all the attachments of a user.
	 */
	public function action_showAttachments()
	{
		global $txt, $scripturl, $modSettings;

		// OBEY permissions!
		$boardsAllowed = boardsAllowedTo('view_attachments');

		// Make sure we can't actually see anything...
		if (empty($boardsAllowed))
			$boardsAllowed = array(-1);

		$memID = currentMemberID();

		require_once(SUBSDIR . '/List.class.php');

		// This is all the information required to list attachments.
		$listOptions = array(
			'id' => 'attachments',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['show_attachments_none'],
			'base_href' => $scripturl . '?action=profile;area=showposts;sa=attach;u=' . $memID,
			'default_sort_col' => 'filename',
			'get_items' => array(
				'function' => array($this, 'list_getAttachments'),
				'params' => array(
					$boardsAllowed,
					$memID,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getNumAttachments'),
				'params' => array(
					$boardsAllowed,
					$memID,
				),
			),
			'data_check' => array(
				'class' => create_function('$data', '
					return $data[\'approved\'] ? \'\' : \'approvebg\';
				')
			),
			'columns' => array(
				'filename' => array(
					'header' => array(
						'value' => $txt['show_attach_downloads'],
						'class' => 'lefttext',
						'style' => 'width: 25%;',
					),
					'data' => array(
						'db' => 'filename',
					),
					'sort' => array(
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					),
				),
				'downloads' => array(
					'header' => array(
						'value' => $txt['show_attach_downloads'],
						'style' => 'width: 12%;',
					),
					'data' => array(
						'db' => 'downloads',
						'comma_format' => true,
					),
					'sort' => array(
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					),
				),
				'subject' => array(
					'header' => array(
						'value' => $txt['message'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					),
					'data' => array(
						'db' => 'subject',
					),
					'sort' => array(
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					),
				),
				'posted' => array(
					'header' => array(
						'value' => $txt['show_attach_posted'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'posted',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);
	}

	/**
	 * Show all the unwatched topics.
	 */
	public function action_showUnwatched()
	{
		global $txt, $user_info, $scripturl, $modSettings, $context;

		$memID = currentMemberID();

		// Only the owner can see the list (if the function is enabled of course)
		if ($user_info['id'] != $memID || !$modSettings['enable_unwatch'])
			return;

		require_once(SUBSDIR . '/List.class.php');

		// And here they are: the topics you don't like
		$listOptions = array(
			'id' => 'unwatched_topics',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['unwatched_topics_none'],
			'base_href' => $scripturl . '?action=profile;area=showposts;sa=unwatchedtopics;u=' . $memID,
			'default_sort_col' => 'started_on',
			'get_items' => array(
				'function' => array($this, 'list_getUnwatched'),
				'params' => array(
					$memID,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getNumUnwatched'),
				'params' => array(
					$memID,
				),
			),
			'columns' => array(
				'subject' => array(
					'header' => array(
						'value' => $txt['subject'],
						'class' => 'lefttext',
						'style' => 'width: 30%;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?topic=%1$d.0">%2$s</a>',
							'params' => array(
								'id_topic' => false,
								'subject' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					),
				),
				'started_by' => array(
					'header' => array(
						'value' => $txt['started_by'],
						'style' => 'width: 15%;',
					),
					'data' => array(
						'db' => 'started_by',
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'started_on' => array(
					'header' => array(
						'value' => $txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					),
					'data' => array(
						'db' => 'started_on',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
				'last_post_by' => array(
					'header' => array(
						'value' => $txt['last_post'],
						'style' => 'width: 15%;',
					),
					'data' => array(
						'db' => 'last_post_by',
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'last_post_on' => array(
					'header' => array(
						'value' => $txt['on'],
						'class' => 'lefttext',
						'style' => 'width: 20%;',
					),
					'data' => array(
						'db' => 'last_post_on',
						'timeformat' => true,
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'unwatched_topics';
	}

	/**
	 * Gets the user stats for display.
	 */
	public function action_statPanel()
	{
		global $txt, $context, $user_profile, $modSettings;

		$memID = currentMemberID();
		require_once(SUBSDIR . '/Stats.subs.php');

		$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $user_profile[$memID]['real_name'];

		// Is the load average too high to allow searching just now?
		if (!empty($modSettings['loadavg_userstats']) && $modSettings['current_load'] >= $modSettings['loadavg_userstats'])
			fatal_lang_error('loadavg_userstats_disabled', false);

		loadTemplate('ProfileInfo');

		// General user statistics.
		$timeDays = floor($user_profile[$memID]['total_time_logged_in'] / 86400);
		$timeHours = floor(($user_profile[$memID]['total_time_logged_in'] % 86400) / 3600);
		$context['time_logged_in'] = ($timeDays > 0 ? $timeDays . $txt['totalTimeLogged2'] : '') . ($timeHours > 0 ? $timeHours . $txt['totalTimeLogged3'] : '') . floor(($user_profile[$memID]['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged4'];
		$context['num_posts'] = comma_format($user_profile[$memID]['posts']);

		// Menu tab
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['statPanel_generalStats'] . ' - ' . $context['member']['name'],
			'class' => 'stats_info'
		);

		// Number of topics started.
		$context['num_topics'] = UserStatsTopicsStarted($memID);

		// Number of polls started.
		$context['num_polls'] = UserStatsPollsStarted($memID);

		// Number of polls voted in.
		$context['num_votes'] = UserStatsPollsVoted($memID);

		// Format the numbers...
		$context['num_topics'] = comma_format($context['num_topics']);
		$context['num_polls'] = comma_format($context['num_polls']);
		$context['num_votes'] = comma_format($context['num_votes']);

		// Grab the boards this member posted in most often.
		$context['popular_boards'] = UserStatsMostPostedBoard($memID);

		// Now get the 10 boards this user has most often participated in.
		$context['board_activity'] = UserStatsMostActiveBoard($memID);

		// Posting activity by time.
		$context['posts_by_time'] = UserStatsPostingTime($memID);

		// Custom stats (just add a template_layer to add it to the template!)
		call_integration_hook('integrate_profile_stats', array($memID));
	}

	/**
	 * Show permissions for a user.
	 */
	public function action_showPermissions()
	{
		global $txt, $board, $user_profile, $context;

		// Verify if the user has sufficient permissions.
		isAllowedTo('manage_permissions');

		loadLanguage('ManagePermissions');
		loadLanguage('Admin');
		loadTemplate('ManageMembers');
		loadTemplate('ProfileInfo');

		// Load all the permission profiles.
		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		loadPermissionProfiles();

		$memID = currentMemberID();

		$context['member']['id'] = $memID;
		$context['member']['name'] = $user_profile[$memID]['real_name'];

		$context['page_title'] = $txt['showPermissions'];
		$board = empty($board) ? 0 : (int) $board;
		$context['board'] = $board;

		// Determine which groups this user is in.
		if (empty($user_profile[$memID]['additional_groups']))
			$curGroups = array();
		else
			$curGroups = explode(',', $user_profile[$memID]['additional_groups']);

		$curGroups[] = $user_profile[$memID]['id_group'];
		$curGroups[] = $user_profile[$memID]['id_post_group'];

		// Load a list of boards for the jump box - except the defaults.
		require_once(SUBSDIR . '/Boards.subs.php');
		$board_list = getBoardList(array('moderator' => $memID), true);

		$context['boards'] = array();
		$context['no_access_boards'] = array();
		foreach ($board_list as $row)
		{
			if (count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0 && !$row['is_mod'])
				$context['no_access_boards'][] = array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'is_last' => false,
				);
			elseif ($row['id_profile'] != 1 || $row['is_mod'])
				$context['boards'][$row['id_board']] = array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'selected' => $board == $row['id_board'],
					'profile' => $row['id_profile'],
					'profile_name' => $context['profiles'][$row['id_profile']]['name'],
				);
		}

		if (!empty($context['no_access_boards']))
			$context['no_access_boards'][count($context['no_access_boards']) - 1]['is_last'] = true;

		$context['member']['permissions'] = array(
			'general' => array(),
			'board' => array()
		);

		// If you're an admin we know you can do everything, we might as well leave.
		$context['member']['has_all_permissions'] = in_array(1, $curGroups);
		if ($context['member']['has_all_permissions'])
			return;

		// Get all general permissions for the groups this member is in
		$context['member']['permissions']['general'] = getMemberGeneralPermissions($curGroups);

		// Get all board permissions for this member
		$context['member']['permissions']['board'] = getMemberBoardPermissions($memID, $curGroups, $board);
	}

	/**
	 * View a members warnings.
	 */
	public function action_viewWarning()
	{
		global $modSettings, $context, $txt, $scripturl;

		// Firstly, can we actually even be here?
		if (!allowedTo('issue_warning') && (empty($modSettings['warning_show']) || ($modSettings['warning_show'] == 1 && !$context['user']['is_owner'])))
			fatal_lang_error('no_access', false);

		loadTemplate('ProfileInfo');

		// We need this because of template_load_warning_variables
		loadTemplate('Profile');

		// Make sure things which are disabled stay disabled.
		$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
		$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) && !empty($modSettings['postmod_active']) ? $modSettings['warning_moderate'] : 110;
		$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

		// Let's use a generic list to get all the current warnings
		// and use the issue warnings grab-a-granny thing.
		require_once(SUBSDIR . '/List.class.php');

		$memID = currentMemberID();

		$listOptions = array(
			'id' => 'view_warnings',
			'title' => $txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['profile_viewwarning_no_warnings'],
			'base_href' => $scripturl . '?action=profile;area=viewwarning;sa=user;u=' . $memID,
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
				'log_time' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_time'],
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
						'style' => 'width: 50%;',
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'level' => array(
					'header' => array(
						'value' => $txt['profile_warning_previous_level'],
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
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['profile_viewwarning_desc'],
					'class' => 'smalltext',
					'style' => 'padding: 2ex;',
				),
			),
		);

		// Create the list for viewing.
		createList($listOptions);

		// Create some common text bits for the template.
		$context['level_effects'] = array(
			0 => '',
			$modSettings['warning_watch'] => $txt['profile_warning_effect_own_watched'],
			$modSettings['warning_moderate'] => $txt['profile_warning_effect_own_moderated'],
			$modSettings['warning_mute'] => $txt['profile_warning_effect_own_muted'],
		);
		$context['current_level'] = 0;
		$context['sub_template'] = 'viewWarning';
		foreach ($context['level_effects'] as $limit => $dummy)
		{
			if ($context['member']['warning'] >= $limit)
				$context['current_level'] = $limit;
		}
	}

	/**
	 * Get a list of attachments for this user
	 * Callback for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param array $boardsAllowed
	 * @param int $memID
	 */
	public function list_getAttachments($start, $items_per_page, $sort, $boardsAllowed, $memID)
	{
		// @todo tweak this method to use $context, etc,
		// then call subs function with params set.
		return profileLoadAttachments($start, $items_per_page, $sort, $boardsAllowed, $memID);
	}

	/**
	 * Callback for createList()
	 *
	 * @param array $boardsAllowed
	 * @param int $memID
	 */
	public function list_getNumAttachments($boardsAllowed, $memID)
	{
		// @todo tweak this method to use $context, etc,
		// then call subs function with params set.
		return getNumAttachments($boardsAllowed, $memID);
	}

	/**
	 * Get the relevant topics in the unwatched list
	 * Callback for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $memID
	 */
	function list_getUnwatched($start, $items_per_page, $sort, $memID)
	{
		return getUnwatchedBy($start, $items_per_page, $sort, $memID);
	}

	/**
	 * Count the number of topics in the unwatched list
	 * Callback for createList()
	 *
	 * @param int $memID
	 */
	public function list_getNumUnwatched($memID)
	{
		return getNumUnwatchedBy($memID);
	}
}