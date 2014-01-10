<?php

/**
 * Handles Post Moderation approvals and unapprovals
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
 * PostModeration Controller handles post moderation actions. (approvals, unapprovals)
 */
class PostModeration_Controller extends Action_Controller
{
	/**
	 * This is the entry point for all things post moderation.
	 *
	 * @uses ModerationCenter template
	 * @uses ModerationCenter language file
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// @todo We'll shift these later bud.
		loadLanguage('ModerationCenter');
		loadTemplate('ModerationCenter');

		require_once(SUBSDIR . '/Action.class.php');

		// Allowed sub-actions, you know the drill by now!
		$subActions = array(
			'approve' =>  array($this, 'action_approve'),
			'attachments' =>  array($this, 'action_unapproved_attachments'),
			'replies' =>  array($this, 'action_unapproved'),
			'topics' =>  array($this, 'action_unapproved'),
		);

		// Pick something valid...
		$subAction = !isset($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]) ? 'replies' : $_REQUEST['sa'];

		$action = new Action();
		$action->initialize($subActions, 'replies');
		$action->dispatch($subAction);
	}

	/**
	 * View all unapproved posts or topics
	 */
	public function action_unapproved()
	{
		global $txt, $scripturl, $context, $user_info;

		$db = database();

		$context['current_view'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? 'topics' : 'replies';
		$context['page_title'] = $txt['mc_unapproved_posts'];
		$context['header_title'] = $txt['mc_' . ($context['current_view'] == 'topics' ? 'topics' : 'posts')];

		// Work out what boards we can work in!
		$approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		// If we filtered by board remove ones outside of this board.
		// @todo Put a message saying we're filtered?
		if (isset($_REQUEST['brd']))
		{
			$filter_board = array((int) $_REQUEST['brd']);
			$approve_boards = $approve_boards == array(0) ? $filter_board : array_intersect($approve_boards, $filter_board);
		}

		if ($approve_boards == array(0))
			$approve_query = '';
		elseif (!empty($approve_boards))
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		// Nada, zip, etc...
		else
			$approve_query = ' AND 1=0';

		// We also need to know where we can delete topics and/or replies to.
		if ($context['current_view'] == 'topics')
		{
			$delete_own_boards = boardsAllowedTo('remove_own');
			$delete_any_boards = boardsAllowedTo('remove_any');
			$delete_own_replies = array();
		}
		else
		{
			$delete_own_boards = boardsAllowedTo('delete_own');
			$delete_any_boards = boardsAllowedTo('delete_any');
			$delete_own_replies = boardsAllowedTo('delete_own_replies');
		}

		$toAction = array();
		// Check if we have something to do?
		if (isset($_GET['approve']))
			$toAction[] = (int) $_GET['approve'];
		// Just a deletion?
		elseif (isset($_GET['delete']))
			$toAction[] = (int) $_GET['delete'];
		// Lots of approvals?
		elseif (isset($_POST['item']))
			foreach ($_POST['item'] as $item)
				$toAction[] = (int) $item;

		// What are we actually doing.
		if (isset($_GET['approve']) || (isset($_POST['do']) && $_POST['do'] == 'approve'))
			$curAction = 'approve';
		elseif (isset($_GET['delete']) || (isset($_POST['do']) && $_POST['do'] == 'delete'))
			$curAction = 'delete';

		// Right, so we have something to do?
		if (!empty($toAction) && isset($curAction))
		{
			checkSession('request');

			require_once(SUBSDIR . '/Topic.subs.php');

			// Handy shortcut.
			$any_array = $curAction == 'approve' ? $approve_boards : $delete_any_boards;

			// Now for each message work out whether it's actually a topic, and what board it's on.
			$request = $db->query('', '
				SELECT m.id_msg, m.id_member, m.id_board, m.subject, t.id_topic, t.id_first_msg, t.id_member_started
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				WHERE m.id_msg IN ({array_int:message_list})
					AND m.approved = {int:not_approved}
					AND {query_see_board}',
				array(
					'message_list' => $toAction,
					'not_approved' => 0,
				)
			);
			$toAction = array();
			$details = array();
			while ($row = $db->fetch_assoc($request))
			{
				// If it's not within what our view is ignore it...
				if (($row['id_msg'] == $row['id_first_msg'] && $context['current_view'] != 'topics') || ($row['id_msg'] != $row['id_first_msg'] && $context['current_view'] != 'replies'))
					continue;

				$can_add = false;

				// If we're approving this is simple.
				if ($curAction == 'approve' && ($any_array == array(0) || in_array($row['id_board'], $any_array)))
					$can_add = true;
				// Delete requires more permission checks...
				elseif ($curAction == 'delete')
				{
					// Own post is easy!
					if ($row['id_member'] == $user_info['id'] && ($delete_own_boards == array(0) || in_array($row['id_board'], $delete_own_boards)))
						$can_add = true;
					// Is it a reply to their own topic?
					elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'] && ($delete_own_replies == array(0) || in_array($row['id_board'], $delete_own_replies)))
						$can_add = true;
					// Someone elses?
					elseif ($row['id_member'] != $user_info['id'] && ($delete_any_boards == array(0) || in_array($row['id_board'], $delete_any_boards)))
						$can_add = true;
				}

				if ($can_add)
					$anItem = $context['current_view'] == 'topics' ? $row['id_topic'] : $row['id_msg'];
				$toAction[] = $anItem;

				// All clear. What have we got now, what, what?
				$details[$anItem] = array();
				$details[$anItem]["subject"] = $row['subject'];
				$details[$anItem]["topic"] = $row['id_topic'];
				$details[$anItem]["member"] = ($context['current_view'] == 'topics') ? $row['id_member_started'] : $row['id_member'];
				$details[$anItem]["board"] = $row['id_board'];
			}
			$db->free_result($request);

			// If we have anything left we can actually do the approving (etc).
			if (!empty($toAction))
			{
				if ($curAction == 'approve')
					approveMessages($toAction, $details, $context['current_view']);
				else
					removeMessages($toAction, $details, $context['current_view']);

				cache_put_data('num_menu_errors', null, 900);
			}
		}

		// Get the moderation values for the board level
		$brd = isset($_REQUEST['brd']) ? (int) $_REQUEST['brd'] : null;
		require_once(SUBSDIR . '/Moderation.subs.php');
		$mod_count = loadModeratorMenuCounts($brd);

		$context['total_unapproved_topics'] = $mod_count['topics'];
		$context['total_unapproved_posts'] = $mod_count['posts'];
		$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=postmod;sa=' . $context['current_view'] . (isset($_REQUEST['brd']) ? ';brd=' . (int) $_REQUEST['brd'] : ''), $_GET['start'], $context['current_view'] == 'topics' ? $context['total_unapproved_topics'] : $context['total_unapproved_posts'], 10);
		$context['start'] = $_GET['start'];

		// We have enough to make some pretty tabs!
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_unapproved_posts'],
			'help' => 'postmod',
			'description' => $txt['mc_unapproved_posts_desc'],
		);

		// Update the tabs with the correct number of actions to account for brd filtering
		$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['label'] = $context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['label'] . ' [' . $context['total_unapproved_posts'] . ']';
		$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['label'] = $context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['label']. ' [' . $context['total_unapproved_topics'] . ']';

		// If we are filtering some boards out then make sure to send that along with the links.
		if (isset($_REQUEST['brd']))
		{
			$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['add_params'] = ';brd=' . (int) $_REQUEST['brd'];
			$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['add_params'] = ';brd=' . (int) $_REQUEST['brd'];
		}

		// Get all unapproved posts.
		$request = $db->query('', '
			SELECT m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
				IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.smileys_enabled,
				t.id_member_started, t.id_first_msg, b.name AS board_name, c.id_cat, c.name AS cat_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE m.approved = {int:not_approved}
				AND t.id_first_msg ' . ($context['current_view'] == 'topics' ? '=' : '!=') . ' m.id_msg
				AND {query_see_board}
				' . $approve_query . '
			LIMIT ' . $context['start'] . ', 10',
			array(
				'not_approved' => 0,
			)
		);
		$context['unapproved_items'] = array();
		for ($i = 1; $row = $db->fetch_assoc($request); $i++)
		{
			// Can delete is complicated, let's solve it first... is it their own post?
			if ($row['id_member'] == $user_info['id'] && ($delete_own_boards == array(0) || in_array($row['id_board'], $delete_own_boards)))
				$can_delete = true;
			// Is it a reply to their own topic?
			elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'] && ($delete_own_replies == array(0) || in_array($row['id_board'], $delete_own_replies)))
				$can_delete = true;
			// Someone elses?
			elseif ($row['id_member'] != $user_info['id'] && ($delete_any_boards == array(0) || in_array($row['id_board'], $delete_any_boards)))
				$can_delete = true;
			else
				$can_delete = false;

			$context['unapproved_items'][] = array(
				'id' => $row['id_msg'],
				'alternate' => $i % 2,
				'counter' => $context['start'] + $i,
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'subject' => $row['subject'],
				'body' => parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']),
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'poster' => array(
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				),
				'topic' => array(
					'id' => $row['id_topic'],
				),
				'board' => array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>',
				),
				'category' => array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'link' => '<a href="' . $scripturl . '#c' . $row['id_cat'] . '">' . $row['cat_name'] . '</a>',
				),
				'can_delete' => $can_delete,
			);
		}
		$db->free_result($request);

		$context['sub_template'] = 'unapproved_posts';
	}

	/**
	 * View all unapproved attachments.
	 */
	public function action_unapproved_attachments()
	{
		global $txt, $scripturl, $context, $user_info, $modSettings;

		$context['page_title'] = $txt['mc_unapproved_attachments'];

		// Once again, permissions are king!
		$approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		if ($approve_boards == array(0))
			$approve_query = '';
		elseif (!empty($approve_boards))
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		else
			$approve_query = ' AND 0';

		// Get together the array of things to act on, if any.
		$attachments = array();
		if (isset($_GET['approve']))
			$attachments[] = (int) $_GET['approve'];
		elseif (isset($_GET['delete']))
			$attachments[] = (int) $_GET['delete'];
		elseif (isset($_POST['item']))
		{
			foreach ($_POST['item'] as $item)
				$attachments[] = (int) $item;
		}

		// Are we approving or deleting?
		if (isset($_GET['approve']) || (isset($_POST['do']) && $_POST['do'] == 'approve'))
			$curAction = 'approve';
		elseif (isset($_GET['delete']) || (isset($_POST['do']) && $_POST['do'] == 'delete'))
			$curAction = 'delete';

		// Something to do, let's do it!
		if (!empty($attachments) && isset($curAction))
		{
			checkSession('request');

			// This will be handy.
			require_once(SUBSDIR . '/ManageAttachments.subs.php');

			// Confirm the attachments are eligible for changing!
			$attachments = validateAttachments($attachments, $approve_query);

			// Assuming it wasn't all like, proper illegal, we can do the approving.
			if (!empty($attachments))
			{
				if ($curAction == 'approve')
					approveAttachments($attachments);
				else
					removeAttachments(array('id_attach' => $attachments, 'do_logging' => true));

				cache_put_data('num_menu_errors', null, 900);
			}
		}

		require_once(SUBSDIR . '/List.class.php');
		require_once(SUBSDIR . '/ManageAttachments.subs.php');

		$listOptions = array(
			'id' => 'mc_unapproved_attach',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['mc_unapproved_attachments_none_found'],
			'base_href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
			'default_sort_col' => 'attach_name',
			'get_items' => array(
				'function' => 'list_getUnapprovedAttachments',
				'params' => array(
					$approve_query,
				),
			),
			'get_count' => array(
				'function' => 'list_getNumUnapprovedAttachments',
				'params' => array(
					$approve_query,
				),
			),
			'columns' => array(
				'attach_name' => array(
					'header' => array(
						'value' => $txt['mc_unapproved_attach_name'],
					),
					'data' => array(
						'db' => 'filename',
					),
					'sort' => array(
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					),
				),
				'attach_size' => array(
					'header' => array(
						'value' => $txt['mc_unapproved_attach_size'],
					),
					'data' => array(
						'db' => 'size',
					),
					'sort' => array(
						'default' => 'a.size',
						'reverse' => 'a.size DESC',
					),
				),
				'attach_poster' => array(
					'header' => array(
						'value' => $txt['mc_unapproved_attach_poster'],
					),
					'data' => array(
						'function' => create_function('$data', '
							return $data[\'poster\'][\'link\'];'
						)
					),
					'sort' => array(
						'default' => 'm.id_member',
						'reverse' => 'm.id_member DESC',
					),
				),
				'date' => array(
					'header' => array(
						'value' => $txt['date'],
						'style' => 'width: 18%;',
					),
					'data' => array(
						'db' => 'time',
						'class' => 'smalltext',
						'style' => 'white-space:nowrap;',
					),
					'sort' => array(
						'default' => 'm.poster_time',
						'reverse' => 'm.poster_time DESC',
					),
				),
				'message' => array(
					'header' => array(
						'value' => $txt['post'],
					),
					'data' => array(
						'function' => create_function('$data', '
							global $modSettings;
							return \'<a href="\' . $data[\'message\'][\'href\'] . \'">\' . shorten_text($data[\'message\'][\'subject\'], !empty($modSettings[\'subject_length\']) ? $modSettings[\'subject_length\'] : 24) . \'</a>\';'
						),
						'class' => 'smalltext',
						'style' => 'width:15em;',
					),
					'sort' => array(
						'default' => 'm.subject',
						'reverse' => 'm.subject DESC',
					),
				),
				'action' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" checked="checked" />',
						'style' => 'width: 4%;text-align: center',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="item[]" value="%1$d" checked="checked" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
				'token' => 'mod-ap',
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '
						<select name="do" onchange="if (this.value != 0 &amp;&amp; confirm(\'' . $txt['mc_unapproved_sure'] . '\')) submit();">
							<option value="0">' . $txt['with_selected'] . ':</option>
							<option value="0" disabled="disabled">' . str_repeat('&#8212;', strlen($txt['approve'])) . '</option>
							<option value="approve">' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;' . $txt['approve'] . '</option>
							<option value="delete">' . (isBrowser('ie8') ? '&#187;' : '&#10148;') . '&nbsp;' . $txt['delete'] . '</option>
						</select>
						<noscript><input type="submit" name="ml_go" value="' . $txt['go'] . '" class="right_submit" /></noscript>',
					'class' => 'floatright',
				),
			),
		);

		// Create the request list.
		createToken('mod-ap');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'mc_unapproved_attach';
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_unapproved_attachments'],
			'help' => '',
			'description' => $txt['mc_unapproved_attachments_desc']
		);
	}

	/**
	 * Approve a post, just the one.
	 */
	public function action_approve()
	{
		global $user_info, $topic, $board;

		checkSession('get');

		$current_msg = (int) $_REQUEST['msg'];

		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		isAllowedTo('approve_posts');

		$message_info = basicMessageInfo($current_msg, false, true);

		// If it's the first in a topic then the whole topic gets approved!
		if ($message_info['id_first_msg'] == $current_msg)
		{
			approveTopics($topic, !$message_info['approved']);

			if ($message_info['id_member_started'] != $user_info['id'])
				logAction(($message_info['approved'] ? 'un' : '') . 'approve_topic', array('topic' => $topic, 'subject' => $message_info['subject'], 'member' => $message_info['id_member_started'], 'board' => $board));
		}
		else
		{
			approvePosts($current_msg, !$message_info['approved']);

			if ($message_info['id_member'] != $user_info['id'])
				logAction(($message_info['approved'] ? 'un' : '') . 'approve', array('topic' => $topic, 'subject' => $message_info['subject'], 'member' => $message_info['id_member'], 'board' => $board));
		}

		cache_put_data('num_menu_errors', null, 900);

		redirectexit('topic=' . $topic . '.msg' . $current_msg. '#msg' . $current_msg);
	}
}