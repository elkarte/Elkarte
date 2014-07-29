<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
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
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Recent Post Controller, retrieve information about recent posts
 */
class Recent_Controller extends Action_Controller
{
	/**
	 * Intended entry point for recent controller class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Figure out what action to do
		$this->action_recent();
	}

	/**
	 * Find the ten most recent posts.
	 * Accessed by action=recent.
	 */
	public function action_recent()
	{
		global $txt, $scripturl, $user_info, $context, $modSettings, $board;

		$db = database();

		loadTemplate('Recent');
		$context['page_title'] = $txt['recent_posts'];
		$context['sub_template'] = 'recent';

		require_once(SUBSDIR . '/Recent.subs.php');

		if (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
			$_REQUEST['start'] = 95;

		$query_parameters = array();

		// Recent posts by category id's
		if (!empty($_REQUEST['c']) && empty($board))
		{
			$categories = array_map('intval', explode(',', $_REQUEST['c']));

			if (count($categories) === 1)
			{
				require_once(SUBSDIR . '/Categories.subs.php');
				$name = categoryName($categories[0]);

				if (empty($name))
					fatal_lang_error('no_access', false);

				$context['linktree'][] = array(
					'url' => $scripturl . '#c' . $categories[0],
					'name' => $name
				);
			}

			// Find the number of posts in these categorys, exclude the recycle board.
			require_once(SUBSDIR . '/Boards.subs.php');
			$boards_posts = boardsPosts(array(), $categories, false, false);
			$total_cat_posts = array_sum($boards_posts);
			$boards = array_keys($boards_posts);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			// The query for getting the messages
			$query_this_board = 'b.id_board IN ({array_int:boards})';
			$query_parameters['boards'] = $boards;

			// If this category has a significant number of posts in it...
			if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
			{
				$query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
				$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 400 - $_REQUEST['start'] * 7);
			}

			$context['page_index'] = constructPageIndex($scripturl . '?action=recent;c=' . implode(',', $categories), $_REQUEST['start'], min(100, $total_cat_posts), 10, false);
		}
		// Or recent posts by board id's?
		elseif (!empty($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $i => $b)
				$_REQUEST['boards'][$i] = (int) $b;

			// Fetch the number of posts for the supplied board IDs
			require_once(SUBSDIR . '/Boards.subs.php');
			$boards_posts = boardsPosts($_REQUEST['boards'], array());
			$total_posts = array_sum($boards_posts);
			$boards = array_keys($boards_posts);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			// Build the query for finding the messages
			$query_this_board = 'b.id_board IN ({array_int:boards})';
			$query_parameters['boards'] = $boards;

			// If these boards have a significant number of posts in them...
			if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			{
				$query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
				$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 500 - $_REQUEST['start'] * 9);
			}

			$context['page_index'] = constructPageIndex($scripturl . '?action=recent;boards=' . implode(',', $_REQUEST['boards']), $_REQUEST['start'], min(100, $total_posts), 10, false);
		}
		// Or just the recent posts for a specific board
		elseif (!empty($board))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$board_data = fetchBoardsInfo(array('boards' => $board), array('selects' => 'posts'));

			$query_this_board = 'b.id_board = {int:board}';
			$query_parameters['board'] = $board;

			// If this board has a significant number of posts in it...
			if ($board_data[$board]['num_posts'] > 80 && $board_data[$board]['num_posts'] > $modSettings['totalMessages'] / 10)
			{
				$query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
				$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 600 - $_REQUEST['start'] * 10);
			}

			$context['page_index'] = constructPageIndex($scripturl . '?action=recent;board=' . $board . '.%1$d', $_REQUEST['start'], min(100, $board_data[$board]['num_posts']), 10, true);
		}
		// All the recent posts across boards and categories it is then
		else
		{
			$query_this_board = '{query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
						AND b.id_board != {int:recycle_board}' : '') . '
						AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 100 - $_REQUEST['start'] * 6);
			$query_parameters['recycle_board'] = $modSettings['recycle_board'];

			// Set up the pageindex
			require_once(SUBSDIR . '/Boards.subs.php');
			$context['page_index'] = constructPageIndex($scripturl . '?action=recent', $_REQUEST['start'], min(100, sumRecentPosts()), 10, false);
		}

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=recent' . (empty($board) ? (empty($categories) ? '' : ';c=' . implode(',', $categories)) : ';board=' . $board . '.0'),
			'name' => $context['page_title']
		);

		$key = 'recent-' . $user_info['id'] . '-' . md5(serialize(array_diff_key($query_parameters, array('max_id_msg' => 0)))) . '-' . (int) $_REQUEST['start'];
		if (empty($modSettings['cache_enable']) || ($messages = cache_get_data($key, 120)) == null)
		{
			$done = false;
			while (!$done)
			{
				// Find the 10 most recent messages they can *view*.
				// @todo SLOW This query is really slow still, probably?
				$request = $db->query('', '
					SELECT m.id_msg
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					WHERE ' . $query_this_board . '
						AND m.approved = {int:is_approved}
					ORDER BY m.id_msg DESC
					LIMIT {int:offset}, {int:limit}',
					array_merge($query_parameters, array(
						'is_approved' => 1,
						'offset' => $_REQUEST['start'],
						'limit' => 10,
					))
				);
				// If we don't have 10 results, try again with an unoptimized version covering all rows, and cache the result.
				if (isset($query_parameters['max_id_msg']) && $db->num_rows($request) < 10)
				{
					$db->free_result($request);
					$query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $query_this_board);
					$cache_results = true;
					unset($query_parameters['max_id_msg']);
				}
				else
					$done = true;
			}
			$messages = array();
			while ($row = $db->fetch_assoc($request))
				$messages[] = $row['id_msg'];
			$db->free_result($request);

			if (!empty($cache_results))
				cache_put_data($key, $messages, 120);
		}

		// Nothing here... Or at least, nothing you can see...
		if (empty($messages))
		{
			$context['posts'] = array();
			return;
		}

		list ($context['posts'], $board_ids) = getRecentPosts($messages, $_REQUEST['start']);

		// There might be - and are - different permissions between any and own.
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

		// Provide an easy way for integration to interact with the recent display items
		call_integration_hook('integrate_recent_message_list', array($messages, &$permissions));

		// Now go through all the permissions, looking for boards they can do it on.
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// They can do it on these boards...
				$boards = boardsAllowedTo($permission);

				// If 0 is the only thing in the array, they can do it everywhere!
				if (!empty($boards) && $boards[0] == 0)
					$boards = array_keys($board_ids[$type]);

				// Go through the boards, and look for posts they can do this on.
				foreach ($boards as $board_id)
				{
					// Hmm, they have permission, but there are no topics from that board on this page.
					if (!isset($board_ids[$type][$board_id]))
						continue;

					// Okay, looks like they can do it for these posts.
					foreach ($board_ids[$type][$board_id] as $counter)
						if ($type == 'any' || $context['posts'][$counter]['poster']['id'] == $user_info['id'])
							$context['posts'][$counter]['tests'][$allowed] = true;
				}
			}
		}

		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
		foreach ($context['posts'] as $counter => $post)
		{
			// Some posts - the first posts - can't just be deleted.
			$context['posts'][$counter]['tests']['can_delete'] &= $context['posts'][$counter]['delete_possible'];

			// And some cannot be quoted...
			$context['posts'][$counter]['tests']['can_quote'] = $context['posts'][$counter]['tests']['can_reply'] && $quote_enabled;

			// Let's add some buttons here!
			$context['posts'][$counter]['buttons'] = array(
				// How about... even... remove it entirely?!
				'remove' => array(
					'href' => $scripturl . '?action=deletemsg;msg=' . $post['id'] . ';topic=' . $post['topic'] . ';recent;' . $context['session_var'] . '=' . $context['session_id'],
					'text' => $txt['remove'],
					'test' => 'can_delete',
					'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['remove_message'] . '?') . ');"',
				),
				// Can we request notification of topics?
				'notify' => array(
					'href' => $scripturl . '?action=notify;topic=' . $post['topic'] . '.' . $post['start'],
					'text' => $txt['notify'],
					'test' => 'can_mark_notify',
				),
				// If they *can* reply?
				'reply' => array(
					'href' => $scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'],
					'text' => $txt['reply'],
					'test' => 'can_reply',
				),
				// If they *can* quote?
				'quote' => array(
					'href' => $scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'] . ';quote=' . $post['id'],
					'text' => $txt['quote'],
					'test' => 'can_quote',
				),
			);
		}
	}

	/**
	 * Find unread topics and replies.
	 * Accessed by action=unread and action=unreadreplies
	 */
	public function action_unread()
	{
		global $board, $txt, $scripturl;
		global $user_info, $context, $settings, $modSettings, $options;

		$db = database();

		// Guests can't have unread things, we don't know anything about them.
		is_not_guest();

		// Prefetching + lots of MySQL work = bad mojo.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		{
			@ob_end_clean();
			header('HTTP/1.1 403 Forbidden');
			die;
		}

		// We need... we need... I know!
		require_once(SUBSDIR . '/Recent.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		$context['showCheckboxes'] = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];
		$context['showing_all_topics'] = isset($_GET['all']);
		$context['start'] = (int) $_REQUEST['start'];
		$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];

		if ($_REQUEST['action'] == 'unread')
			$context['page_title'] = $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'];
		else
			$context['page_title'] = $txt['unread_replies'];

		if ($context['showing_all_topics'] && !empty($modSettings['loadavg_allunread']) && $modSettings['current_load'] >= $modSettings['loadavg_allunread'])
			fatal_lang_error('loadavg_allunread_disabled', false);
		elseif ($_REQUEST['action'] != 'unread' && !empty($modSettings['loadavg_unreadreplies']) && $modSettings['current_load'] >= $modSettings['loadavg_unreadreplies'])
			fatal_lang_error('loadavg_unreadreplies_disabled', false);
		elseif (!$context['showing_all_topics'] && $_REQUEST['action'] == 'unread' && !empty($modSettings['loadavg_unread']) && $modSettings['current_load'] >= $modSettings['loadavg_unread'])
			fatal_lang_error('loadavg_unread_disabled', false);

		// Parameters for the main query.
		$query_parameters = array();

		// Are we specifying any specific board?
		if (isset($_REQUEST['children']) && (!empty($board) || !empty($_REQUEST['boards'])))
		{
			$boards = array();

			if (!empty($_REQUEST['boards']))
			{
				$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
				foreach ($_REQUEST['boards'] as $b)
					$boards[] = (int) $b;
			}

			if (!empty($board))
				$boards[] = (int) $board;

			// The easiest thing is to just get all the boards they can see,
			// but since we've specified the top of tree we ignore some of them
			addChildBoards($boards);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			$query_this_board = 'id_board IN ({array_int:boards})';
			$query_parameters['boards'] = $boards;
			$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%d';
		}
		elseif (!empty($board))
		{
			$query_this_board = 'id_board = {int:board}';
			$query_parameters['board'] = $board;
			$context['querystring_board_limits'] = ';board=' . $board . '.%1$d';
		}
		elseif (!empty($_REQUEST['boards']))
		{
			$selected_boards = array_map('intval', explode(',', $_REQUEST['boards']));

			$boards = accessibleBoards($selected_boards);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			$query_this_board = 'id_board IN ({array_int:boards})';
			$query_parameters['boards'] = $boards;
			$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%1$d';
		}
		elseif (!empty($_REQUEST['c']))
		{
			$categories = array_map('intval', explode(',', $_REQUEST['c']));

			$boards = array_keys(boardsPosts(array(), $categories, isset($_REQUEST['action']) && $_REQUEST['action'] != 'unreadreplies'));

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			$query_this_board = 'id_board IN ({array_int:boards})';
			$query_parameters['boards'] = $boards;
			$context['querystring_board_limits'] = ';c=' . $_REQUEST['c'] . ';start=%1$d';
		}
		else
		{
			$see_board = isset($_REQUEST['action']) && $_REQUEST['action'] == 'unreadreplies' ? 'query_see_board' : 'query_wanna_see_board';

			// Don't bother to show deleted posts!
			$boards = wantedBoards($see_board);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			$query_this_board = 'id_board IN ({array_int:boards})';
			$query_parameters['boards'] = $boards;
			$context['querystring_board_limits'] = ';start=%1$d';
			$context['no_board_limits'] = true;
		}

		$sort_methods = array(
			'subject' => 'ms.subject',
			'starter' => 'IFNULL(mems.real_name, ms.poster_name)',
			'replies' => 't.num_replies',
			'views' => 't.num_views',
			'first_post' => 't.id_topic',
			'last_post' => 't.id_last_msg'
		);

		// The default is the most logical: newest first.
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			$context['sort_by'] = 'last_post';
			$ascending = isset($_REQUEST['asc']);

			$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
		}
		// But, for other methods the default sort is ascending.
		else
		{
			$context['sort_by'] = $_REQUEST['sort'];
			$ascending = !isset($_REQUEST['desc']);

			$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
		}
		$_REQUEST['sort'] = $sort_methods[$context['sort_by']];

		$context['sort_direction'] = $ascending ? 'up' : 'down';
		$context['sort_title'] = $ascending ? $txt['sort_desc'] : $txt['sort_asc'];

		// Trick
		$txt['starter'] = $txt['started_by'];

		foreach ($sort_methods as $key => $val)
			$context['topics_headers'][$key] = array(
				'url' => $scripturl . '?action=unread' . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start']) . ';sort=subject' . ($context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : ''),
				'sort_dir_img' => $context['sort_by'] == $key ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" title="' . $context['sort_title'] .'" />' : '',
			);

		if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1)
		{
			$name = categoryName((int) $_REQUEST['c'][0]);

			$context['linktree'][] = array(
				'url' => $scripturl . '#c' . (int) $_REQUEST['c'][0],
				'name' => $name
			);
		}

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=' . $_REQUEST['action'] . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
			'name' => $_REQUEST['action'] == 'unread' ? $txt['unread_topics_visit'] : $txt['unread_replies']
		);

		if ($context['showing_all_topics'])
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=' . $_REQUEST['action'] . ';all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
				'name' => $txt['unread_topics_all']
			);
			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all', $txt['unread_topics_visit_none']);
		}
		else
			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'], $txt['unread_topics_visit_none']);

		loadTemplate('Recent');
		$context['sub_template'] = $_REQUEST['action'] == 'unread' ? 'unread' : 'replies';

		// Setup the default topic icons... for checking they exist and the like ;)
		require_once(SUBSDIR . '/MessageIndex.subs.php');
		$context['icon_sources'] = MessageTopicIcons();

		$is_topics = $_REQUEST['action'] == 'unread';

		// If empty, no preview at all
		if (empty($modSettings['message_index_preview']))
			$preview_bodies = '';
		// If 0 means everything
		elseif (empty($modSettings['preview_characters']))
			$preview_bodies = 'ml.body AS last_body, ms.body AS first_body,';
		// Default: a SUBSTRING
		else
			$preview_bodies = 'SUBSTRING(ml.body, 1, ' . ($modSettings['preview_characters'] + 256) . ') AS last_body, SUBSTRING(ms.body, 1, ' . ($modSettings['preview_characters'] + 256) . ') AS first_body,';

		// This part is the same for each query.
		$select_clause = '
					ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
					t.num_replies, t.num_views, t.num_likes, ms.id_member AS id_first_member, ml.id_member AS id_last_member,
					ml.poster_time AS last_poster_time, IFNULL(mems.real_name, ms.poster_name) AS first_poster_name,
					IFNULL(meml.real_name, ml.poster_name) AS last_poster_name, ml.subject AS last_subject,
					ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
					IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from,
					' . $preview_bodies . '
					ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg';

		if ($context['showing_all_topics'])
			$earliest_msg = earliest_msg();

		// @todo Add modified_time in for log_time check?
		if ($modSettings['totalMessages'] > 100000 && $context['showing_all_topics'])
		{
			$db->query('', '
				DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
				array(
				)
			);

			// Let's copy things out of the log_topics table, to reduce searching.
			$have_temp_table = $db->query('', '
				CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
					PRIMARY KEY (id_topic)
				)
				SELECT lt.id_topic, lt.id_msg, lt.unwatched
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
				WHERE lt.id_member = {int:current_member}
					AND t.' . $query_this_board . (empty($earliest_msg) ? '' : '
					AND t.id_last_msg > {int:earliest_msg}') . ($modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_unwatch'] ? '
					AND lt.unwatched != 1' : ''),
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
					'is_approved' => 1,
					'db_error_skip' => true,
				))
			) !== false;
		}
		else
			$have_temp_table = false;

		if ($context['showing_all_topics'] && $have_temp_table)
		{
			$request = $db->query('', '
				SELECT COUNT(*), MIN(t.id_last_msg)
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . (!empty($earliest_msg) ? '
					AND t.id_last_msg > {int:earliest_msg}' : '') . '
					AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' .
					($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') .
					($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : ''),
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
					'is_approved' => 1,
				))
			);
			list ($num_topics, $min_message) = $db->fetch_row($request);
			$db->free_result($request);

			// Make sure the starting place makes sense and construct the page index.
			$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
			$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

			$context['links'] += array(
				'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			);
			$context['page_info'] = array(
				'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
				'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
			);

			if ($num_topics == 0)
			{
				// Mark the boards as read if there are no unread topics!
				// @todo look at this... there are no more unread topics already.
				// If clearing of log_topics is still needed, perhaps do it separately.
				markBoardsRead(empty($boards) ? $board : $boards, false, true);

				$context['topics'] = array();
				if ($context['querystring_board_limits'] == ';start=%1$d')
					$context['querystring_board_limits'] = '';
				else
					$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);

				return;
			}
			else
				$min_message = (int) $min_message;

			$request = $db->query('substring', '
				SELECT ' . $select_clause . '
				FROM {db_prefix}messages AS ms
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
					LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE b.' . $query_this_board . '
					AND t.id_last_msg >= {int:min_message}
					AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' .
					($modSettings['postmod_active'] ? ' AND ms.approved = {int:is_approved}' : '') .
					($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : '') . '
				ORDER BY {raw:sort}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'min_message' => $min_message,
					'is_approved' => 1,
					'sort' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => $context['topics_per_page'],
				))
			);
		}
		elseif ($is_topics)
		{
			$request = $db->query('', '
				SELECT COUNT(*), MIN(t.id_last_msg)
				FROM {db_prefix}topics AS t' . (!empty($have_temp_table) ? '
					LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . ($context['showing_all_topics'] && !empty($earliest_msg) ? '
					AND t.id_last_msg > {int:earliest_msg}' : (!$context['showing_all_topics'] && empty($_SESSION['first_login']) ? '
					AND t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
					AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' .
					($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') .
					($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : ''),
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
					'id_msg_last_visit' => $_SESSION['id_msg_last_visit'],
					'is_approved' => 1,
				))
			);
			list ($num_topics, $min_message) = $db->fetch_row($request);
			$db->free_result($request);

			// Make sure the starting place makes sense and construct the page index.
			$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
			$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

			$context['links'] += array(
				'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			);
			$context['page_info'] = array(
				'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
				'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
			);

			if ($num_topics == 0)
			{
				// Is this an all topics query?
				if ($context['showing_all_topics'])
				{
					// Since there are no unread topics, mark the boards as read!
					// @todo look at this... there are no more unread topics already.
					// If clearing of log_topics is still needed, perhaps do it separately.
					markBoardsRead(empty($boards) ? $board : $boards, false, true);
				}

				$context['topics'] = array();
				if ($context['querystring_board_limits'] == ';start=%d')
					$context['querystring_board_limits'] = '';
				else
					$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
				return;
			}
			else
				$min_message = (int) $min_message;

			$request = $db->query('substring', '
				SELECT ' . $select_clause . '
				FROM {db_prefix}messages AS ms
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($have_temp_table) ? '
					LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.' . $query_this_board . '
					AND t.id_last_msg >= {int:min_message}
					AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < ml.id_msg' .
					($modSettings['postmod_active'] ? ' AND ms.approved = {int:is_approved}' : '') .
					($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : '') . '
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'min_message' => $min_message,
					'is_approved' => 1,
					'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => $context['topics_per_page'],
				))
			);
		}
		else
		{
			if ($modSettings['totalMessages'] > 100000)
			{
				$db->query('', '
					DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
					array(
					)
				);

				$db->query('', '
					DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
					array(
					)
				);

				$sortKey_joins = array(
					'ms.subject' => '
						INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
					'IFNULL(mems.real_name, ms.poster_name)' => '
						INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
						LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
				);

				// The main benefit of this temporary table is not that it's faster; it's that it avoids locks later.
				$have_temp_table = $db->query('', '
					CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
						id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
						id_board smallint(5) unsigned NOT NULL default {string:string_zero},
						id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
						id_msg int(10) unsigned NOT NULL default {string:string_zero},
						PRIMARY KEY (id_topic)
					)
					SELECT t.id_topic, t.id_board, t.id_last_msg, IFNULL(lmr.id_msg, 0) AS id_msg' . (!in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? ', ' . $_REQUEST['sort'] . ' AS sort_key' : '') . '
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' . ($modSettings['enable_unwatch'] ? '
						LEFT JOIN {db_prefix}log_topics AS lt ON (t.id_topic = lt.id_topic AND lt.id_member = {int:current_member})' : '') . '
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$_REQUEST['sort']]) ? $sortKey_joins[$_REQUEST['sort']] : '') . '
					WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
						AND t.id_board = {int:current_board}' : '') . ($modSettings['postmod_active'] ? '
						AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_unwatch'] ? '
						AND IFNULL(lt.unwatched, 0) != 1' : '') . '
					GROUP BY m.id_topic',
					array(
						'current_board' => $board,
						'current_member' => $user_info['id'],
						'is_approved' => 1,
						'string_zero' => '0',
						'db_error_skip' => true,
					)
				) !== false;

				// If that worked, create a sample of the log_topics table too.
				if ($have_temp_table)
					$have_temp_table = $db->query('', '
						CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
							PRIMARY KEY (id_topic)
						)
						SELECT lt.id_topic, lt.id_msg
						FROM {db_prefix}log_topics AS lt
							INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
						WHERE lt.id_member = {int:current_member}',
						array(
							'current_member' => $user_info['id'],
							'db_error_skip' => true,
						)
					) !== false;
			}

			if (!empty($have_temp_table))
			{
				$request = $db->query('', '
					SELECT COUNT(*)
					FROM {db_prefix}topics_posted_in AS pi
						LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
					WHERE pi.' . $query_this_board . '
						AND IFNULL(lt.id_msg, pi.id_msg) < pi.id_last_msg',
					array_merge($query_parameters, array(
					))
				);
				list ($num_topics) = $db->fetch_row($request);
				$db->free_result($request);
				$min_message = 0;
			}
			else
			{
				$request = $db->query('unread_fetch_topic_count', '
					SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
					WHERE t.' . $query_this_board . '
						AND m.id_member = {int:current_member}
						AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
						AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_unwatch'] ? '
						AND IFNULL(lt.unwatched, 0) != 1' : ''),
					array_merge($query_parameters, array(
						'current_member' => $user_info['id'],
						'is_approved' => 1,
					))
				);
				list ($num_topics, $min_message) = $db->fetch_row($request);
				$db->free_result($request);
			}

			// Make sure the starting place makes sense and construct the page index.
			$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
			$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

			$context['links'] += array(
				'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
				'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'up' => $scripturl,
			);
			$context['page_info'] = array(
				'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
				'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
			);

			if ($num_topics == 0)
			{
				$context['topics'] = array();
				if ($context['querystring_board_limits'] == ';start=%d')
					$context['querystring_board_limits'] = '';
				else
					$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
				return;
			}

			if (!empty($have_temp_table))
				$request = $db->query('', '
					SELECT t.id_topic
					FROM {db_prefix}topics_posted_in AS t
						LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
					WHERE t.' . $query_this_board . '
						AND IFNULL(lt.id_msg, t.id_msg) < t.id_last_msg
					ORDER BY {raw:order}
					LIMIT {int:offset}, {int:limit}',
					array_merge($query_parameters, array(
						'order' => (in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? $_REQUEST['sort'] : 't.sort_key') . ($ascending ? '' : ' DESC'),
						'offset' => $_REQUEST['start'],
						'limit' => $context['topics_per_page'],
					))
				);
			else
				$request = $db->query('unread_replies', '
					SELECT DISTINCT t.id_topic
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($_REQUEST['sort'], 'ms.') === false ? '' : '
						INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($_REQUEST['sort'], 'mems.') === false ? '' : '
						LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
					WHERE t.' . $query_this_board . '
						AND t.id_last_msg >= {int:min_message}
						AND (IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0))) < t.id_last_msg' .
						($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') .
						($modSettings['enable_unwatch'] ? ' AND IFNULL(lt.unwatched, 0) != 1' : '') . '
					ORDER BY {raw:order}
					LIMIT {int:offset}, {int:limit}',
					array_merge($query_parameters, array(
						'current_member' => $user_info['id'],
						'min_message' => (int) $min_message,
						'is_approved' => 1,
						'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
						'offset' => $_REQUEST['start'],
						'limit' => $context['topics_per_page'],
						'sort' => $_REQUEST['sort'],
					))
				);

			$topics = array();
			while ($row = $db->fetch_assoc($request))
				$topics[] = $row['id_topic'];
			$db->free_result($request);

			// Sanity... where have you gone?
			if (empty($topics))
			{
				$context['topics'] = array();
				if ($context['querystring_board_limits'] == ';start=%d')
					$context['querystring_board_limits'] = '';
				else
					$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
				return;
			}

			$request = $db->query('substring', '
				SELECT ' . $select_clause . '
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.id_topic IN ({array_int:topic_list})
				ORDER BY ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
				LIMIT ' . count($topics),
				array(
					'current_member' => $user_info['id'],
					'topic_list' => $topics,
				)
			);
		}

		$context['topics'] = array();
		$topic_ids = array();

		while ($row = $db->fetch_assoc($request))
		{
			$topic_ids[] = $row['id_topic'];

			if (!empty($modSettings['message_index_preview']))
			{
				// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
				$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], false, $row['id_first_msg']), array('<br />' => "\n", '&nbsp;' => ' ')));
				$row['first_body'] = Util::shorten_text($row['first_body'], !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128, true);

				// No reply then they are the same, no need to process it again
				if ($row['num_replies'] == 0)
					$row['last_body'] == $row['first_body'];
				else
				{
					$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], false, $row['id_last_msg']), array('<br />' => "\n", '&nbsp;' => ' ')));
					$row['last_body'] = Util::shorten_text($row['last_body'], !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128, true);
				}

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
			$topic_length = $row['num_replies'] + 1;
			$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
			if ($topic_length > $messages_per_page)
			{
				$start = -1;
				$pages = constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d;topicseen', $start, $topic_length, $messages_per_page, true, array('prev_next' => false, 'all' => !empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages']));
			}
			else
				$pages = '';

			// We need to check the topic icons exist... you can never be too sure!
			if (!empty($modSettings['messageIconChecks_enable']))
			{
				// First icon first... as you'd expect.
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';

				// Last icon... last... duh.
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
			}

			// And build the array.
			$context['topics'][$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'first_post' => array(
					'id' => $row['id_first_msg'],
					'member' => array(
						'name' => $row['first_poster_name'],
						'id' => $row['id_first_member'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_first_member'],
						'link' => !empty($row['id_first_member']) ? '<a class="preview" href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_poster_name'] . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
					),
					'time' => standardTime($row['first_poster_time']),
					'html_time' => htmlTime($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'subject' => $row['first_subject'],
					'preview' => $row['first_body'],
					'icon' => $row['first_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen',
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen">' . $row['first_subject'] . '</a>'
				),
				'last_post' => array(
					'id' => $row['id_last_msg'],
					'member' => array(
						'name' => $row['last_poster_name'],
						'id' => $row['id_last_member'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_last_member'],
						'link' => !empty($row['id_last_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_last_member'] . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name']
					),
					'time' => standardTime($row['last_poster_time']),
					'html_time' => htmlTime($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'subject' => $row['last_subject'],
					'preview' => $row['last_body'],
					'icon' => $row['last_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'],
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
				),
				'default_preview' => trim($row[!empty($modSettings['message_index_preview']) && $modSettings['message_index_preview'] == 2 ? 'last_body' : 'first_body']),
				'new_from' => $row['new_from'],
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen' . ($row['num_replies'] == 0 ? '' : 'new'),
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
				'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
				'is_locked' => !empty($row['locked']),
				'is_poll' => !empty($modSettings['pollMode']) && $row['id_poll'] > 0,
				'is_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicPosts'] : $row['num_replies'] >= $modSettings['hotTopicPosts'],
				'is_very_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicVeryPosts'] : $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
				'is_posted_in' => false,
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'subject' => $row['first_subject'],
				'pages' => $pages,
				'replies' => comma_format($row['num_replies']),
				'views' => comma_format($row['num_views']),
				'likes' => comma_format($row['num_likes']),
				'board' => array(
					'id' => $row['id_board'],
					'name' => $row['bname'],
					'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
					'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
				)
			);

			$context['topics'][$row['id_topic']]['first_post']['started_by'] = sprintf($txt['topic_started_by_in'], '<strong>' . $context['topics'][$row['id_topic']]['first_post']['member']['link'] . '</strong>', '<em>' . $context['topics'][$row['id_topic']]['board']['link'] . '</em>');
			determineTopicClass($context['topics'][$row['id_topic']]);
		}
		$db->free_result($request);

		if ($is_topics && !empty($modSettings['enableParticipation']) && !empty($topic_ids))
		{
			require_once(SUBSDIR . '/MessageIndex.subs.php');
			$topics_participated_in = topicsParticipation($user_info['id'], $topic_ids);

			foreach ($topics_participated_in as $topic)
			{
				if (empty($context['topics'][$topic['id_topic']]['is_posted_in']))
				{
					$context['topics'][$topic['id_topic']]['is_posted_in'] = true;
					$context['topics'][$topic['id_topic']]['class'] = 'my_' . $context['topics'][$topic['id_topic']]['class'];
				}
			}
		}

		$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
		$context['topics_to_mark'] = implode('-', $topic_ids);

		if ($settings['show_mark_read'])
		{
			// Build the recent button array.
			if ($is_topics)
			{
				$context['recent_buttons'] = array(
					'markread' => array('text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'custom' => 'onclick="return markunreadButton(this);"', 'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_var'] . '=' . $context['session_id']),
				);

				if ($context['showCheckboxes'])
					$context['recent_buttons']['markselectread'] = array(
						'text' => 'quick_mod_markread',
						'image' => 'markselectedread.png',
						'lang' => true,
						'url' => 'javascript:document.quickModForm.submit();',
					);

				if (!empty($context['topics']) && !$context['showing_all_topics'])
					$context['recent_buttons']['readall'] = array('text' => 'unread_topics_all', 'image' => 'markreadall.png', 'lang' => true, 'url' => $scripturl . '?action=unread;all' . $context['querystring_board_limits'], 'active' => true);
			}
			elseif (!$is_topics && isset($context['topics_to_mark']))
			{
				$context['recent_buttons'] = array(
					'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $context['topics_to_mark'] . ';' . $context['session_var'] . '=' . $context['session_id']),
				);

				if ($context['showCheckboxes'])
					$context['recent_buttons']['markselectread'] = array(
						'text' => 'quick_mod_markread',
						'image' => 'markselectedread.png',
						'lang' => true,
						'url' => 'javascript:document.quickModForm.submit();',
					);
			}

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_recent_buttons');
		}

		// Allow helpdesks and bug trackers and what not to add their own unread data (just add a template_layer to show custom stuff in the template!)
		call_integration_hook('integrate_unread_list');
	}
}