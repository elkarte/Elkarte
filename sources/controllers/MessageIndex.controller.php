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
 * This file is what shows the listing of topics in a board.
 * It's just one or two functions, but don't underestimate it ;).
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class MessageIndex_Controller
{
	/**
	 * Show the list of topics in this board, along with any child boards.
	 */
	function action_messageindex()
	{
		global $txt, $scripturl, $board, $modSettings, $context;
		global $options, $settings, $board_info, $user_info;

		$db = database();

		// Fairly often, we'll work with boards. Current board, child boards.
		require_once(SUBSDIR . '/Boards.subs.php');

		// If this is a redirection board head off.
		if ($board_info['redirect'])
		{
			incrementBoard($board, 'num_posts');
			redirectexit($board_info['redirect']);
		}

		loadTemplate('MessageIndex');
		loadJavascriptFile('topic.js');

		$context['name'] = $board_info['name'];
		$context['description'] = $board_info['description'];

		// How many topics do we have in total?
		$board_info['total_topics'] = allowedTo('approve_posts') ? $board_info['num_topics'] + $board_info['unapproved_topics'] : $board_info['num_topics'] + $board_info['unapproved_user_topics'];

		// View all the topics, or just a few?
		$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
		$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$maxindex = isset($_REQUEST['all']) && !empty($modSettings['enableAllMessages']) ? $board_info['total_topics'] : $context['topics_per_page'];

		// Right, let's only index normal stuff!
		if (count($_GET) > 1)
		{
			$session_name = session_name();
			foreach ($_GET as $k => $v)
			{
				if (!in_array($k, array('board', 'start', $session_name)))
					$context['robot_no_index'] = true;
			}
		}
		if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
			$context['robot_no_index'] = true;

		// If we can view unapproved messages and there are some build up a list.
		if (allowedTo('approve_posts') && ($board_info['unapproved_topics'] || $board_info['unapproved_posts']))
		{
			$untopics = $board_info['unapproved_topics'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=topics;brd=' . $board . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
			$unposts = $board_info['unapproved_posts'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=posts;brd=' . $board . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;
			$context['unapproved_posts_message'] = sprintf($txt['there_are_unapproved_topics'], $untopics, $unposts, $scripturl . '?action=moderate;area=postmod;sa=' . ($board_info['unapproved_topics'] ? 'topics' : 'posts') . ';brd=' . $board);
		}

		// We only know these.
		if (isset($_REQUEST['sort']) && !in_array($_REQUEST['sort'], array('subject', 'starter', 'last_poster', 'replies', 'views', 'first_post', 'last_post')))
			$_REQUEST['sort'] = 'last_post';

		// Make sure the starting place makes sense and construct the page index.
		if (isset($_REQUEST['sort']))
			$context['page_index'] = constructPageIndex($scripturl . '?board=' . $board . '.%1$d;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $board_info['total_topics'], $maxindex, true);
		else
			$context['page_index'] = constructPageIndex($scripturl . '?board=' . $board . '.%1$d', $_REQUEST['start'], $board_info['total_topics'], $maxindex, true);
		$context['start'] = &$_REQUEST['start'];

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?board=' . $board . '.' . $context['start'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?board=' . $board . '.0' : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?board=' . $board . '.' . ($_REQUEST['start'] - $context['topics_per_page']) : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? $scripturl . '?board=' . $board . '.' . ($_REQUEST['start'] + $context['topics_per_page']) : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? $scripturl . '?board=' . $board . '.' . (floor(($board_info['total_topics'] - 1) / $context['topics_per_page']) * $context['topics_per_page']) : '',
			'up' => $board_info['parent'] == 0 ? $scripturl . '?' : $scripturl . '?board=' . $board_info['parent'] . '.0'
		);

		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($board_info['total_topics'] - 1) / $context['topics_per_page']) + 1
		);

		if (isset($_REQUEST['all']) && !empty($modSettings['enableAllMessages']) && $maxindex > $modSettings['enableAllMessages'])
		{
			$maxindex = $modSettings['enableAllMessages'];
			$_REQUEST['start'] = 0;
		}

		// Build a list of the board's moderators.
		$context['moderators'] = &$board_info['moderators'];
		$context['link_moderators'] = array();
		if (!empty($board_info['moderators']))
		{
			foreach ($board_info['moderators'] as $mod)
				$context['link_moderators'][] ='<a href="' . $scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';

			$context['linktree'][count($context['linktree']) - 1]['extra_after'] = '<span class="board_moderators"> (' . (count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators']) . ': ' . implode(', ', $context['link_moderators']) . ')</span>';
		}

		// Mark current and parent boards as seen.
		if (!$user_info['is_guest'])
		{
			// We can't know they read it if we allow prefetches.
			if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
			{
				ob_end_clean();
				header('HTTP/1.1 403 Prefetch Forbidden');
				die;
			}

			// Mark the board as read, and its parents.
			if (!empty($board_info['parent_boards']))
			{
				$board_list = array_keys($board_info['parent_boards']);
				$board_list[] = $board;
			}
			else
				$board_list = array($board);

			// Mark boards as read. Boards alone, no need for topics.
			markBoardsRead($board_list, false, false);

			// Clear topicseen cache
			if (!empty($board_info['parent_boards']))
			{
				// We've seen all these boards now!
				foreach ($board_info['parent_boards'] as $k => $dummy)
					if (isset($_SESSION['topicseen_cache'][$k]))
						unset($_SESSION['topicseen_cache'][$k]);
			}

			if (isset($_SESSION['topicseen_cache'][$board]))
				unset($_SESSION['topicseen_cache'][$board]);

			// From now on, they've seen it. So we reset notifications.
			$context['is_marked_notify'] = resetSentBoardNotification($user_info['id'], $board);
		}
		else
			$context['is_marked_notify'] = false;

		// 'Print' the header and board info.
		$context['page_title'] = strip_tags($board_info['name']);

		// Set the variables up for the template.
		$context['can_mark_notify'] = allowedTo('mark_notify') && !$user_info['is_guest'];
		$context['can_post_new'] = allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
		$context['can_post_poll'] = $modSettings['pollMode'] == '1' && allowedTo('poll_post') && $context['can_post_new'];
		$context['can_moderate_forum'] = allowedTo('moderate_forum');
		$context['can_approve_posts'] = allowedTo('approve_posts');

		// Prepare child boards for display.
		require_once(SUBSDIR . '/BoardIndex.subs.php');
		$boardIndexOptions = array(
			'include_categories' => false,
			'base_level' => $board_info['child_level'] + 1,
			'parent_id' => $board_info['id'],
			'set_latest_post' => false,
			'countChildPosts' => !empty($modSettings['countChildPosts']),
		);
		$context['boards'] = getBoardIndex($boardIndexOptions);

		// Nosey, nosey - who's viewing this board?
		if (!empty($settings['display_who_viewing']))
		{
			require_once(SUBSDIR . '/Who.subs.php');
			formatViewers($board, 'board');
		}

		// And now, what we're here for: topics!
		require_once(SUBSDIR . '/MessageIndex.subs.php');

		// Known sort methods.
		$sort_methods = messageIndexSort();

		// They didn't pick one, default to by last post descending.
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			$context['sort_by'] = 'last_post';
			$sort_column = 'id_last_msg';
			$ascending = isset($_REQUEST['asc']);
		}
		// Otherwise default to ascending.
		else
		{
			$context['sort_by'] = $_REQUEST['sort'];
			$sort_column = $sort_methods[$_REQUEST['sort']];
			$ascending = !isset($_REQUEST['desc']);
		}

		$context['sort_direction'] = $ascending ? 'up' : 'down';
		$txt['starter'] = $txt['started_by'];

		foreach ($sort_methods as $key => $val)
			$context['topics_headers'][$key] = '<a href="' . $scripturl . '?board=' . $context['current_board'] . '.' . $context['start'] . ';sort=' . $key . ($context['sort_by'] == $key && $context['sort_direction'] == 'up' ? ';desc' : '') . '">' . $txt[$key] . ($context['sort_by'] == $key ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '') . '</a>';

		// Calculate the fastest way to get the topics.
		$start = (int) $_REQUEST['start'];
		if ($start > ($board_info['total_topics'] - 1) / 2)
		{
			$ascending = !$ascending;
			$fake_ascending = true;
			$maxindex = $board_info['total_topics'] < $start + $maxindex + 1 ? $board_info['total_topics'] - $start : $maxindex;
			$start = $board_info['total_topics'] < $start + $maxindex + 1 ? 0 : $board_info['total_topics'] - $start - $maxindex;
		}
		else
			$fake_ascending = false;

		// Setup the default topic icons...
		$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved', 'recycled', 'wireless', 'clip');
		$context['icon_sources'] = array();
		foreach ($stable_icons as $icon)
			$context['icon_sources'][$icon] = 'images_url';

		$topic_ids = array();
		$context['topics'] = array();

		$indexOptions = array(
			'include_sticky' => !empty($modSettings['enableStickyTopics']),
			'only_approved' => $modSettings['postmod_active'] && !allowedTo('approve_posts'),
			'previews' => empty($modSettings['preview_characters']) ? 0 : $modSettings['preview_characters'],
			'include_avatars' => !empty($settings['avatars_on_indexes']),
			'ascending' => $ascending,
			'fake_ascending' => $fake_ascending
		);

		$topics_info = messageIndexTopics($board, $user_info['id'], $start, $maxindex, $context['sort_by'], $sort_column, $indexOptions);

		// Begin 'printing' the message index for current board.
		foreach ($topics_info as $row)
		{
			if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
				continue;

			$topic_ids[] = $row['id_topic'];

			// Does the theme support message previews?
			if (!empty($settings['message_index_preview']) && !empty($modSettings['preview_characters']))
			{
				// Limit them to $modSettings['preview_characters'] characters
				$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br />' => '&#10;')));
				if (Util::strlen($row['first_body']) > $modSettings['preview_characters'])
					$row['first_body'] = Util::substr($row['first_body'], 0, $modSettings['preview_characters']) . '...';

				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br />' => '&#10;')));
				if (Util::strlen($row['last_body']) > $modSettings['preview_characters'])
					$row['last_body'] = Util::substr($row['last_body'], 0, $modSettings['preview_characters']) . '...';

				// Censor the subject and message preview.
				censorText($row['first_subject']);
				censorText($row['first_body']);

				// Don't censor them twice!
				if ($row['id_first_msg'] == $row['id_last_msg'])
				{
					$row['last_subject'] = $row['first_subject'];
					$row['last_body'] = $row['first_body'];
				}
				else
				{
					censorText($row['last_subject']);
					censorText($row['last_body']);
				}
			}
			else
			{
				$row['first_body'] = '';
				$row['last_body'] = '';
				censorText($row['first_subject']);

				if ($row['id_first_msg'] == $row['id_last_msg'])
					$row['last_subject'] = $row['first_subject'];
				else
					censorText($row['last_subject']);
			}

			// Decide how many pages the topic should have.
			if ($row['num_replies'] + 1 > $context['messages_per_page'])
			{
				$pages = '&#171; ';

				// We can't pass start by reference.
				$start = -1;
				$pages .= constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $row['num_replies'] + 1, $context['messages_per_page'], true, false);

				// If we can use all, show all.
				if (!empty($modSettings['enableAllMessages']) && $row['num_replies'] + 1 < $modSettings['enableAllMessages'])
					$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
				$pages .= ' &#187;';
			}
			else
				$pages = '';

			// We need to check the topic icons exist...
			if (!empty($modSettings['messageIconChecks_enable']))
			{
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
			}
			else
			{
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = 'images_url';
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = 'images_url';
			}

			if (!empty($settings['avatars_on_indexes']))
			{
				// Allow themers to show the latest poster's avatar along with the topic
				if (!empty($row['avatar']))
				{
					if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
					{
						$avatar_width = !empty($modSettings['avatar_max_width_external']) ? ' width:' . $modSettings['avatar_max_width_external'] . 'px;' : '';
						$avatar_height = !empty($modSettings['avatar_max_height_external']) ? ' height:' . $modSettings['avatar_max_height_external'] . 'px;' : '';
					}
					else
					{
						$avatar_width = '';
						$avatar_height = '';
					}
				}
			}

			// 'Print' the topic info.
			$context['topics'][$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'first_post' => array(
					'id' => $row['id_first_msg'],
					'member' => array(
						'username' => $row['first_member_name'],
						'name' => $row['first_display_name'],
						'id' => $row['first_id_member'],
						'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
						'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
					),
					'time' => relativeTime($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'subject' => $row['first_subject'],
					'preview' => $row['first_body'],
					'icon' => $row['first_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>'
				),
				'last_post' => array(
					'id' => $row['id_last_msg'],
					'member' => array(
						'username' => $row['last_member_name'],
						'name' => $row['last_display_name'],
						'id' => $row['last_id_member'],
						'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
						'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
					),
					'time' => relativeTime($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'subject' => $row['last_subject'],
					'preview' => $row['last_body'],
					'icon' => $row['last_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
				),
				'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
				'is_locked' => !empty($row['locked']),
				'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
				'is_hot' => $row['num_replies'] >= $modSettings['hotTopicPosts'],
				'is_very_hot' => $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
				'is_posted_in' => false,
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'subject' => $row['first_subject'],
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'newtime' => $row['new_from'],
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'pages' => $pages,
				'replies' => comma_format($row['num_replies']),
				'views' => comma_format($row['num_views']),
				'approved' => $row['approved'],
				'unapproved_posts' => $row['unapproved_posts'],
			);
			if (!empty($settings['avatars_on_indexes']))
				$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = array(
					'name' => $row['avatar'],
					'image' => $row['avatar'] == '' ? ($row['id_attach'] > 0 ? '<img class="avatar" src="' . (empty($row['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['filename']) . '" alt="" />' : '') : (stristr($row['avatar'], 'http://') ? '<img class="avatar" src="' . $row['avatar'] . '" style="' . $avatar_width . $avatar_height . '" alt="" />' : '<img class="avatar" src="' . $modSettings['avatar_url'] . '/' . htmlspecialchars($row['avatar']) . '" alt="" />'),
					'href' => $row['avatar'] == '' ? ($row['id_attach'] > 0 ? (empty($row['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['filename']) : '') : (stristr($row['avatar'], 'http://') ? $row['avatar'] : $modSettings['avatar_url'] . '/' . $row['avatar']),
					'url' => $row['avatar'] == '' ? '' : (stristr($row['avatar'], 'http://') ? $row['avatar'] : $modSettings['avatar_url'] . '/' . $row['avatar'])
				);

			determineTopicClass($context['topics'][$row['id_topic']]);
		}

		// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
		if ($fake_ascending)
			$context['topics'] = array_reverse($context['topics'], true);

		if (!empty($modSettings['enableParticipation']) && !$user_info['is_guest'] && !empty($topic_ids))
		{
			$topics_participated_in = topicsParticipation($user_info['id'], $topic_ids);
			foreach ($topics_participated_in as $participated)
			{
				$context['topics'][$participated['id_topic']]['is_posted_in'] = true;
				$context['topics'][$participated['id_topic']]['class'] = 'my_' . $context['topics'][$participated['id_topic']]['class'];
			}
		}

		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => htmlspecialchars(strtr(strip_tags($board_info['name']), array('&amp;' => '&'))),
			'child_level' => $board_info['child_level'],
		);

		// Is Quick Moderation active/needed?
		if (!empty($options['display_quick_mod']) && !empty($context['topics']))
		{
			$context['can_markread'] = $context['user']['is_logged'];
			$context['can_lock'] = allowedTo('lock_any');
			$context['can_sticky'] = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);
			$context['can_move'] = allowedTo('move_any');
			$context['can_remove'] = allowedTo('remove_any');
			$context['can_merge'] = allowedTo('merge_any');
			// Ignore approving own topics as it's unlikely to come up...
			$context['can_approve'] = $modSettings['postmod_active'] && allowedTo('approve_posts') && !empty($board_info['unapproved_topics']);
			// Can we restore topics?
			$context['can_restore'] = allowedTo('move_any') && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board;

			// Set permissions for all the topics.
			foreach ($context['topics'] as $t => $topic)
			{
				$started = $topic['first_post']['member']['id'] == $user_info['id'];
				$context['topics'][$t]['quick_mod'] = array(
					'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
					'sticky' => allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']),
					'move' => allowedTo('move_any') || ($started && allowedTo('move_own')),
					'modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
					'remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
					'approve' => $context['can_approve'] && $topic['unapproved_posts']
				);
				$context['can_lock'] |= ($started && allowedTo('lock_own'));
				$context['can_move'] |= ($started && allowedTo('move_own'));
				$context['can_remove'] |= ($started && allowedTo('remove_own'));
			}

			// Can we use quick moderation checkboxes?
			if ($options['display_quick_mod'] == 1)
				$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_approve'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'] || $context['can_merge'] || $context['can_restore'];
			// Or the icons?
			else
				$context['can_quick_mod'] = $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'];
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
		{
			$context['qmod_actions'] = array('approve', 'remove', 'lock', 'sticky', 'move', 'merge', 'restore', 'markread');
			call_integration_hook('integrate_quick_mod_actions');
		}

		// If there are children, but no topics and no ability to post topics...
		$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];

		addJavascriptVar('notification_board_notice', $context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board'], true);

		// Build the message index button array.
		$context['normal_buttons'] = array(
			'new_topic' => array('test' => 'can_post_new', 'text' => 'new_topic', 'image' => 'new_topic.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0', 'active' => true),
			'post_poll' => array('test' => 'can_post_poll', 'text' => 'new_poll', 'image' => 'new_poll.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
			'notify' => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : ''). 'notify.png', 'lang' => true, 'custom' => 'onclick="return notifyboardButton(this);"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			'markread' => array('text' => 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id'], 'custom' => 'onclick="return markboardreadButton(this);"'),
		);

		// Allow adding new buttons easily.
		call_integration_hook('integrate_messageindex_buttons');
	}

	/**
	 * Allows for moderation from the message index.
	 * @todo refactor this...
	 */
	function action_quickmod()
	{
		global $board, $user_info, $modSettings, $context;

		$db = database();

		// Check the session = get or post.
		checkSession('request');

		// Lets go straight to the restore area.
		if (isset($_REQUEST['qaction']) && $_REQUEST['qaction'] == 'restore' && !empty($_REQUEST['topics']))
			redirectexit('action=restoretopic;topics=' . implode(',', $_REQUEST['topics']) . ';' . $context['session_var'] . '=' . $context['session_id']);

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		// This is going to be needed to send off the notifications and for updateLastMessages().
		require_once(SUBSDIR . '/Post.subs.php');
		// Process process process data.
		require_once(SUBSDIR . '/Topic.subs.php');

		// Remember the last board they moved things to.
		if (isset($_REQUEST['move_to']))
			$_SESSION['move_to_topic'] = $_REQUEST['move_to'];

		// Only a few possible actions.
		$possibleActions = array();

		if (!empty($board))
		{
			$boards_can = array(
				'make_sticky' => allowedTo('make_sticky') ? array($board) : array(),
				'move_any' => allowedTo('move_any') ? array($board) : array(),
				'move_own' => allowedTo('move_own') ? array($board) : array(),
				'remove_any' => allowedTo('remove_any') ? array($board) : array(),
				'remove_own' => allowedTo('remove_own') ? array($board) : array(),
				'lock_any' => allowedTo('lock_any') ? array($board) : array(),
				'lock_own' => allowedTo('lock_own') ? array($board) : array(),
				'merge_any' => allowedTo('merge_any') ? array($board) : array(),
				'approve_posts' => allowedTo('approve_posts') ? array($board) : array(),
			);

			$redirect_url = 'board=' . $board . '.' . $_REQUEST['start'];
		}
		else
		{
			$boards_can = boardsAllowedTo(array('make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'lock_any', 'lock_own', 'merge_any', 'approve_posts'), true, false);

			$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : (isset($_SESSION['old_url']) ? $_SESSION['old_url'] : '');
		}

		if (!$user_info['is_guest'])
			$possibleActions[] = 'markread';
		if (!empty($boards_can['make_sticky']) && !empty($modSettings['enableStickyTopics']))
			$possibleActions[] = 'sticky';
		if (!empty($boards_can['move_any']) || !empty($boards_can['move_own']))
			$possibleActions[] = 'move';
		if (!empty($boards_can['remove_any']) || !empty($boards_can['remove_own']))
			$possibleActions[] = 'remove';
		if (!empty($boards_can['lock_any']) || !empty($boards_can['lock_own']))
			$possibleActions[] = 'lock';
		if (!empty($boards_can['merge_any']))
			$possibleActions[] = 'merge';
		if (!empty($boards_can['approve_posts']))
			$possibleActions[] = 'approve';

		// Two methods: $_REQUEST['actions'] (id_topic => action), and $_REQUEST['topics'] and $_REQUEST['qaction'].
		// (if action is 'move', $_REQUEST['move_to'] or $_REQUEST['move_tos'][$topic] is used.)
		if (!empty($_REQUEST['topics']))
		{
			// If the action isn't valid, just quit now.
			if (empty($_REQUEST['qaction']) || !in_array($_REQUEST['qaction'], $possibleActions))
				redirectexit($redirect_url);

			// Merge requires all topics as one parameter and can be done at once.
			if ($_REQUEST['qaction'] == 'merge')
			{
				// Merge requires at least two topics.
				if (empty($_REQUEST['topics']) || count($_REQUEST['topics']) < 2)
					redirectexit($redirect_url);

				require_once(CONTROLLERDIR . '/MergeTopics.controller.php');
				$controller = new MergeTopics_Controller();
				return $controller->action_mergeExecute($_REQUEST['topics']);
			}

			// Just convert to the other method, to make it easier.
			foreach ($_REQUEST['topics'] as $topic)
				$_REQUEST['actions'][(int) $topic] = $_REQUEST['qaction'];
		}

		// Weird... how'd you get here?
		if (empty($_REQUEST['actions']))
			redirectexit($redirect_url);

		// Validate each action.
		$temp = array();
		foreach ($_REQUEST['actions'] as $topic => $action)
		{
			if (in_array($action, $possibleActions))
				$temp[(int) $topic] = $action;
		}
		$_REQUEST['actions'] = $temp;

		if (!empty($_REQUEST['actions']))
		{
			// Find all topics...
			$request = $db->query('', '
				SELECT id_topic, id_member_started, id_board, locked, approved, unapproved_posts
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:action_topic_ids})
				LIMIT ' . count($_REQUEST['actions']),
				array(
					'action_topic_ids' => array_keys($_REQUEST['actions']),
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				if (!empty($board))
				{
					if ($row['id_board'] != $board || ($modSettings['postmod_active'] && !$row['approved'] && !allowedTo('approve_posts')))
						unset($_REQUEST['actions'][$row['id_topic']]);
				}
				else
				{
					// Don't allow them to act on unapproved posts they can't see...
					if ($modSettings['postmod_active'] && !$row['approved'] && !in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))
						unset($_REQUEST['actions'][$row['id_topic']]);
					// Goodness, this is fun.  We need to validate the action.
					elseif ($_REQUEST['actions'][$row['id_topic']] == 'sticky' && !in_array(0, $boards_can['make_sticky']) && !in_array($row['id_board'], $boards_can['make_sticky']))
						unset($_REQUEST['actions'][$row['id_topic']]);
					elseif ($_REQUEST['actions'][$row['id_topic']] == 'move' && !in_array(0, $boards_can['move_any']) && !in_array($row['id_board'], $boards_can['move_any']) && ($row['id_member_started'] != $user_info['id'] || (!in_array(0, $boards_can['move_own']) && !in_array($row['id_board'], $boards_can['move_own']))))
						unset($_REQUEST['actions'][$row['id_topic']]);
					elseif ($_REQUEST['actions'][$row['id_topic']] == 'remove' && !in_array(0, $boards_can['remove_any']) && !in_array($row['id_board'], $boards_can['remove_any']) && ($row['id_member_started'] != $user_info['id'] || (!in_array(0, $boards_can['remove_own']) && !in_array($row['id_board'], $boards_can['remove_own']))))
						unset($_REQUEST['actions'][$row['id_topic']]);
					// @todo $locked is not set, what are you trying to do? (taking the change it is supposed to be $row['locked'])
					elseif ($_REQUEST['actions'][$row['id_topic']] == 'lock' && !in_array(0, $boards_can['lock_any']) && !in_array($row['id_board'], $boards_can['lock_any']) && ($row['id_member_started'] != $user_info['id'] || $row['locked'] == 1 || (!in_array(0, $boards_can['lock_own']) && !in_array($row['id_board'], $boards_can['lock_own']))))
						unset($_REQUEST['actions'][$row['id_topic']]);
					// If the topic is approved then you need permission to approve the posts within.
					elseif ($_REQUEST['actions'][$row['id_topic']] == 'approve' && (!$row['unapproved_posts'] || (!in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))))
						unset($_REQUEST['actions'][$row['id_topic']]);
				}
			}
			$db->free_result($request);
		}

		$stickyCache = array();
		$moveCache = array(0 => array(), 1 => array());
		$removeCache = array();
		$lockCache = array();
		$markCache = array();
		$approveCache = array();

		// Separate the actions.
		foreach ($_REQUEST['actions'] as $topic => $action)
		{
			$topic = (int) $topic;

			if ($action == 'markread')
				$markCache[] = $topic;
			elseif ($action == 'sticky')
				$stickyCache[] = $topic;
			elseif ($action == 'move')
			{
				moveTopicConcurrence();

				// $moveCache[0] is the topic, $moveCache[1] is the board to move to.
				$moveCache[1][$topic] = (int) (isset($_REQUEST['move_tos'][$topic]) ? $_REQUEST['move_tos'][$topic] : $_REQUEST['move_to']);

				if (empty($moveCache[1][$topic]))
					continue;

				$moveCache[0][] = $topic;
			}
			elseif ($action == 'remove')
				$removeCache[] = $topic;
			elseif ($action == 'lock')
				$lockCache[] = $topic;
			elseif ($action == 'approve')
				$approveCache[] = $topic;
		}

		if (empty($board))
			$affectedBoards = array();
		else
			$affectedBoards = array($board => array(0, 0));

		// Do all the stickies...
		if (!empty($stickyCache))
		{
			toggleTopicSticky($stickyCache);

			// Get the board IDs and Sticky status
			$request = $db->query('', '
				SELECT id_topic, id_board, is_sticky
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:sticky_topic_ids})
				LIMIT ' . count($stickyCache),
				array(
					'sticky_topic_ids' => $stickyCache,
				)
			);
			$stickyCacheBoards = array();
			$stickyCacheStatus = array();
			while ($row = $db->fetch_assoc($request))
			{
				$stickyCacheBoards[$row['id_topic']] = $row['id_board'];
				$stickyCacheStatus[$row['id_topic']] = empty($row['is_sticky']);
			}
			$db->free_result($request);
		}

		// Move sucka! (this is, by the by, probably the most complicated part....)
		if (!empty($moveCache[0]))
		{
			// I know - I just KNOW you're trying to beat the system.  Too bad for you... we CHECK :P.
			$request = $db->query('', '
				SELECT t.id_topic, t.id_board, b.count_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				WHERE t.id_topic IN ({array_int:move_topic_ids})' . (!empty($board) && !allowedTo('move_any') ? '
					AND t.id_member_started = {int:current_member}' : '') . '
				LIMIT ' . count($moveCache[0]),
				array(
					'current_member' => $user_info['id'],
					'move_topic_ids' => $moveCache[0],
				)
			);
			$moveTos = array();
			$moveCache2 = array();
			$countPosts = array();
			while ($row = $db->fetch_assoc($request))
			{
				$to = $moveCache[1][$row['id_topic']];

				if (empty($to))
					continue;

				// Does this topic's board count the posts or not?
				$countPosts[$row['id_topic']] = empty($row['count_posts']);

				if (!isset($moveTos[$to]))
					$moveTos[$to] = array();

				$moveTos[$to][] = $row['id_topic'];

				// For reporting...
				$moveCache2[] = array($row['id_topic'], $row['id_board'], $to);
			}
			$db->free_result($request);

			$moveCache = $moveCache2;

			// Do the actual moves...
			foreach ($moveTos as $to => $topics)
				moveTopics($topics, $to);

			// Does the post counts need to be updated?
			if (!empty($moveTos))
			{
				require_once(SUBSDIR . '/Boards.subs.php');
				$topicRecounts = array();
				$boards_info = fetchBoardsInfo(array('boards' => array_keys($moveTos)), array('selects' => 'posts'));

				foreach ($boards_info as $row)
				{
					$cp = empty($row['count_posts']);

					// Go through all the topics that are being moved to this board.
					foreach ($moveTos[$row['id_board']] as $topic)
					{
						// If both boards have the same value for post counting then no adjustment needs to be made.
						if ($countPosts[$topic] != $cp)
						{
							// If the board being moved to does count the posts then the other one doesn't so add to their post count.
							$topicRecounts[$topic] = $cp ? '+' : '-';
						}
					}
				}

				if (!empty($topicRecounts))
				{
					$members = array();

					// Get all the members who have posted in the moved topics.
					$request = $db->query('', '
						SELECT id_member, id_topic
						FROM {db_prefix}messages
						WHERE id_topic IN ({array_int:moved_topic_ids})',
						array(
							'moved_topic_ids' => array_keys($topicRecounts),
						)
					);

					while ($row = $db->fetch_assoc($request))
					{
						if (!isset($members[$row['id_member']]))
							$members[$row['id_member']] = 0;

						if ($topicRecounts[$row['id_topic']] === '+')
							$members[$row['id_member']] += 1;
						else
							$members[$row['id_member']] -= 1;
					}

					$db->free_result($request);

					// And now update them member's post counts
					foreach ($members as $id_member => $post_adj)
						updateMemberData($id_member, array('posts' => 'posts + ' . $post_adj));

				}
			}
		}

		// Now delete the topics...
		if (!empty($removeCache))
		{
			// They can only delete their own topics. (we wouldn't be here if they couldn't do that..)
			$result = $db->query('', '
				SELECT id_topic, id_board
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:removed_topic_ids})' . (!empty($board) && !allowedTo('remove_any') ? '
					AND id_member_started = {int:current_member}' : '') . '
				LIMIT ' . count($removeCache),
				array(
					'current_member' => $user_info['id'],
					'removed_topic_ids' => $removeCache,
				)
			);

			$removeCache = array();
			$removeCacheBoards = array();
			while ($row = $db->fetch_assoc($result))
			{
				$removeCache[] = $row['id_topic'];
				$removeCacheBoards[$row['id_topic']] = $row['id_board'];
			}
			$db->free_result($result);

			// Maybe *none* were their own topics.
			if (!empty($removeCache))
			{
				// Gotta send the notifications *first*!
				foreach ($removeCache as $topic)
				{
					// Only log the topic ID if it's not in the recycle board.
					logAction('remove', array((empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $removeCacheBoards[$topic] ? 'topic' : 'old_topic_id') => $topic, 'board' => $removeCacheBoards[$topic]));
					sendNotifications($topic, 'remove');
				}

				removeTopics($removeCache);
			}
		}

		// Approve the topics...
		if (!empty($approveCache))
		{
			// We need unapproved topic ids and their authors!
			$request = $db->query('', '
				SELECT id_topic, id_member_started
				FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:approve_topic_ids})
					AND approved = {int:not_approved}
				LIMIT ' . count($approveCache),
				array(
					'approve_topic_ids' => $approveCache,
					'not_approved' => 0,
				)
			);
			$approveCache = array();
			$approveCacheMembers = array();
			while ($row = $db->fetch_assoc($request))
			{
				$approveCache[] = $row['id_topic'];
				$approveCacheMembers[$row['id_topic']] = $row['id_member_started'];
			}
			$db->free_result($request);

			// Any topics to approve?
			if (!empty($approveCache))
			{
				// Handle the approval part...
				approveTopics($approveCache);

				// Time for some logging!
				foreach ($approveCache as $topic)
					logAction('approve_topic', array('topic' => $topic, 'member' => $approveCacheMembers[$topic]));
			}
		}

		// And (almost) lastly, lock the topics...
		if (!empty($lockCache))
		{
			$lockStatus = array();

			// Gotta make sure they CAN lock/unlock these topics...
			if (!empty($board) && !allowedTo('lock_any'))
			{
				// Make sure they started the topic AND it isn't already locked by someone with higher priv's.
				$result = $db->query('', '
					SELECT id_topic, locked, id_board
					FROM {db_prefix}topics
					WHERE id_topic IN ({array_int:locked_topic_ids})
						AND id_member_started = {int:current_member}
						AND locked IN (2, 0)
					LIMIT ' . count($lockCache),
					array(
						'current_member' => $user_info['id'],
						'locked_topic_ids' => $lockCache,
					)
				);
				$lockCache = array();
				$lockCacheBoards = array();
				while ($row = $db->fetch_assoc($result))
				{
					$lockCache[] = $row['id_topic'];
					$lockCacheBoards[$row['id_topic']] = $row['id_board'];
					$lockStatus[$row['id_topic']] = empty($row['locked']);
				}
				$db->free_result($result);
			}
			else
			{
				$result = $db->query('', '
					SELECT id_topic, locked, id_board
					FROM {db_prefix}topics
					WHERE id_topic IN ({array_int:locked_topic_ids})
					LIMIT ' . count($lockCache),
					array(
						'locked_topic_ids' => $lockCache,
					)
				);
				$lockCacheBoards = array();
				while ($row = $db->fetch_assoc($result))
				{
					$lockStatus[$row['id_topic']] = empty($row['locked']);
					$lockCacheBoards[$row['id_topic']] = $row['id_board'];
				}
				$db->free_result($result);
			}

			// It could just be that *none* were their own topics...
			if (!empty($lockCache))
			{
				// Alternate the locked value.
				$db->query('', '
					UPDATE {db_prefix}topics
					SET locked = CASE WHEN locked = {int:is_locked} THEN ' . (allowedTo('lock_any') ? '1' : '2') . ' ELSE 0 END
					WHERE id_topic IN ({array_int:locked_topic_ids})',
					array(
						'locked_topic_ids' => $lockCache,
						'is_locked' => 0,
					)
				);
			}
		}

		if (!empty($markCache))
		{
			$request = $db->query('', '
				SELECT id_topic, disregarded
				FROM {db_prefix}log_topics
				WHERE id_topic IN ({array_int:selected_topics})
					AND id_member = {int:current_user}',
				array(
					'selected_topics' => $markCache,
					'current_user' => $user_info['id'],
				)
			);
			$logged_topics = array();
			while ($row = $db->fetch_assoc($request))
				$logged_topics[$row['id_topic']] = $row['disregarded'];
			$db->free_result($request);

			$markArray = array();
			foreach ($markCache as $topic)
				$markArray[] = array($user_info['id'], $topic, $modSettings['maxMsgID'], $logged_topics[$topic]);

			markTopicsRead($markArray, true);
		}

		foreach ($moveCache as $topic)
		{
			// Didn't actually move anything!
			if (!isset($topic[0]))
				break;

			logAction('move', array('topic' => $topic[0], 'board_from' => $topic[1], 'board_to' => $topic[2]));
			sendNotifications($topic[0], 'move');
		}
		foreach ($lockCache as $topic)
		{
			logAction($lockStatus[$topic] ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $lockCacheBoards[$topic]));
			sendNotifications($topic, $lockStatus[$topic] ? 'lock' : 'unlock');
		}
		foreach ($stickyCache as $topic)
		{
			logAction($stickyCacheStatus[$topic] ? 'unsticky' : 'sticky', array('topic' => $topic, 'board' => $stickyCacheBoards[$topic]));
			sendNotifications($topic, 'sticky');
		}

		updateStats('topic');
		updateStats('message');
		updateSettings(array(
			'calendar_updated' => time(),
		));

		if (!empty($affectedBoards))
			updateLastMessages(array_keys($affectedBoards));

		redirectexit($redirect_url);
	}
}