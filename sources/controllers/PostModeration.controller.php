<?php

/**
 * Handles Post Moderation approvals and un-approvals
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * PostModeration_Controller Class
 * Handles post moderation actions. (approvals, unapproved)
 */
class PostModeration_Controller extends Action_Controller
{
	/**
	 * Holds any passed brd values, used for filtering and the like
	 * @var array|null
	 */
	private $_brd = null;

	/**
	 * This is the entry point for all things post moderation.
	 *
	 * @uses ModerationCenter.template
	 * @uses ModerationCenter language file
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// @todo We'll shift these later bud.
		loadLanguage('ModerationCenter');
		loadTemplate('ModerationCenter');

		// Allowed sub-actions, you know the drill by now!
		$subActions = array(
			'approve' => array($this, 'action_approve'),
			'attachments' => array($this, 'action_unapproved_attachments'),
			'replies' => array($this, 'action_unapproved'),
			'topics' => array($this, 'action_unapproved'),
		);

		// Pick something valid...
		$action = new Action();
		$subAction = $action->initialize($subActions, 'replies');
		$action->dispatch($subAction);
	}

	/**
	 * View all unapproved posts or topics
	 */
	public function action_unapproved()
	{
		global $txt, $scripturl, $context, $user_info;

		$context['current_view'] = $this->_req->getQuery('sa', 'trim', '') === 'topics' ? 'topics' : 'replies';
		$context['page_title'] = $txt['mc_unapproved_posts'];
		$context['header_title'] = $txt['mc_' . ($context['current_view'] === 'topics' ? 'topics' : 'posts')];

		// Work out what boards we can work in!
		$approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		$this->_brd = $this->_req->getPost('brd', 'intval', $this->_req->getQuery('brd', 'intval', null));

		// If we filtered by board remove ones outside of this board.
		// @todo Put a message saying we're filtered?
		if (isset($this->_brd))
		{
			$filter_board = array($this->_brd);
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
		if ($context['current_view'] === 'topics')
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

		// No action yet
		$toAction = array();

		// Check if we have something to do?
		if (isset($this->_req->query->approve))
			$toAction[] = (int) $this->_req->query->approve;
		// Just a deletion?
		elseif (isset($this->_req->query->delete))
			$toAction[] = (int) $this->_req->query->delete;
		// Lots of approvals?
		elseif (isset($this->_req->post->item))
			$toAction = array_map('intval', $this->_req->post->item);

		// What are we actually doing.
		if (isset($this->_req->query->approve) || (isset($this->_req->post->do) && $this->_req->post->do === 'approve'))
			$curAction = 'approve';
		elseif (isset($this->_req->query->delete) || (isset($this->_req->post->do) && $this->_req->post->do === 'delete'))
			$curAction = 'delete';

		// Right, so we have something to do?
		if (!empty($toAction) && isset($curAction))
		{
			checkSession('request');

			require_once(SUBSDIR . '/Topic.subs.php');
			require_once(SUBSDIR . '/Messages.subs.php');

			// Handy shortcut.
			$any_array = $curAction === 'approve' ? $approve_boards : $delete_any_boards;

			// Now for each message work out whether it's actually a topic, and what board it's on.
			$request = loadMessageDetails(
				array('m.id_board', 't.id_topic', 't.id_first_msg', 't.id_member_started'),
				array(
					'INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)',
					'LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)'
				),
				array(
					'message_list' => $toAction,
					'not_approved' => 0,
				),
				array(
					'additional_conditions' => '
					AND m.approved = {int:not_approved}
					AND {query_see_board}'
				)
			);
			$toAction = array();
			$details = array();
			foreach ($request as $row)
			{
				// If it's not within what our view is ignore it...
				if (($row['id_msg'] == $row['id_first_msg'] && $context['current_view'] !== 'topics') || ($row['id_msg'] != $row['id_first_msg'] && $context['current_view'] !== 'replies'))
					continue;

				$can_add = false;

				// If we're approving this is simple.
				if ($curAction === 'approve' && ($any_array == array(0) || in_array($row['id_board'], $any_array)))
					$can_add = true;
				// Delete requires more permission checks...
				elseif ($curAction === 'delete')
				{
					// Own post is easy!
					if ($row['id_member'] == $user_info['id'] && ($delete_own_boards == array(0) || in_array($row['id_board'], $delete_own_boards)))
						$can_add = true;
					// Is it a reply to their own topic?
					elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'] && ($delete_own_replies == array(0) || in_array($row['id_board'], $delete_own_replies)))
						$can_add = true;
					// Someone else's?
					elseif ($row['id_member'] != $user_info['id'] && ($delete_any_boards == array(0) || in_array($row['id_board'], $delete_any_boards)))
						$can_add = true;
				}

				if ($can_add)
				{
					$anItem = $context['current_view'] === 'topics' ? $row['id_topic'] : $row['id_msg'];
					$toAction[] = $anItem;

					// All clear. What have we got now, what, what?
					$details[$anItem] = array();
					$details[$anItem]['subject'] = $row['subject'];
					$details[$anItem]['topic'] = $row['id_topic'];
					$details[$anItem]['member'] = ($context['current_view'] === 'topics') ? $row['id_member_started'] : $row['id_member'];
					$details[$anItem]['board'] = $row['id_board'];
				}
			}

			// If we have anything left we can actually do the approving (etc).
			if (!empty($toAction))
			{
				if ($curAction === 'approve')
					approveMessages($toAction, $details, $context['current_view']);
				else
					removeMessages($toAction, $details, $context['current_view']);

				Cache::instance()->remove('num_menu_errors');
			}
		}

		// Get the moderation values for the board level
		require_once(SUBSDIR . '/Moderation.subs.php');
		$mod_count = loadModeratorMenuCounts($this->_brd);

		$context['total_unapproved_topics'] = $mod_count['topics'];
		$context['total_unapproved_posts'] = $mod_count['posts'];
		$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=postmod;sa=' . $context['current_view'] . (isset($this->_brd) ? ';brd=' . $this->_brd : ''), $this->_req->query->start, $context['current_view'] === 'topics' ? $context['total_unapproved_topics'] : $context['total_unapproved_posts'], 10);
		$context['start'] = $this->_req->query->start;

		// We have enough to make some pretty tabs!
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_unapproved_posts'],
			'help' => 'postmod',
			'description' => $txt['mc_unapproved_posts_desc'],
		);

		// Update the tabs with the correct number of actions to account for brd filtering
		$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['label'] = $context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['label'] . ' [' . $context['total_unapproved_posts'] . ']';
		$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['label'] = $context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['label'] . ' [' . $context['total_unapproved_topics'] . ']';

		// If we are filtering some boards out then make sure to send that along with the links.
		if (isset($this->_brd))
		{
			$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['add_params'] = ';brd=' . $this->_brd;
			$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['add_params'] = ';brd=' . $this->_brd;
		}

		// Get all unapproved posts.
		$context['unapproved_items'] = getUnapprovedPosts($approve_query, $context['current_view'], array(
			'delete_own_boards' => $delete_own_boards,
			'delete_any_boards' => $delete_any_boards,
			'delete_own_replies' => $delete_own_replies,
		), $context['start'], 10);

		foreach ($context['unapproved_items'] as $key => $item)
		{
			$context['unapproved_items'][$key]['buttons'] = array(
				'quickmod_check' => array(
					'checkbox' => true,
					'name' => 'item',
					'value' => $item['id'],
				),
					'approve' => array(
						'href' => $scripturl . '?action=moderate;area=postmod;sa=' . $context['current_view'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';approve=' . $item['id'],
						'text' => $txt['approve'],
					),
					'unapprove' => array(
						'href' => $scripturl . '?action=moderate;area=postmod;sa=' . $context['current_view'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';delete=' . $item['id'],
						'text' => $txt['remove'],
						'test' => 'can_delete',
					),
			);

			$context['unapproved_items'][$key]['tests'] = array(
				'can_delete' => $item['can_delete']
			);
		}

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
		if (isset($this->_req->query->approve))
			$attachments[] = (int) $this->_req->query->approve;
		elseif (isset($this->_req->query->delete))
			$attachments[] = (int) $this->_req->query->delete;
		elseif (isset($this->_req->post->item))
		{
			foreach ($this->_req->post->item as $item)
				$attachments[] = (int) $item;
		}

		// Are we approving or deleting?
		if (isset($this->_req->query->approve) || (isset($this->_req->post->do) && $this->_req->post->do === 'approve'))
			$curAction = 'approve';
		elseif (isset($this->_req->query->delete) || (isset($this->_req->post->do) && $this->_req->post->do === 'delete'))
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
				if ($curAction === 'approve')
					approveAttachments($attachments);
				else
					removeAttachments(array('id_attach' => $attachments, 'do_logging' => true));

				Cache::instance()->remove('num_menu_errors');
			}
		}

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
						'function' => function ($data) {
							return $data['poster']['link'];
						},
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
						'function' => function ($data) {
							global $modSettings;

							return '<a href="' . $data['message']['href'] . '">' . Util::shorten_text($data['message']['subject'], !empty($modSettings['subject_length']) ? $modSettings['subject_length'] : 24) . '</a>';
						},
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
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 4%',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="item[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
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
							<option value="approve">&#10148;&nbsp;' . $txt['approve'] . '</option>
							<option value="delete">&#10148;&nbsp;' . $txt['delete'] . '</option>
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
	 * Approve or un-approve a post just the one or a topic if its the first post
	 */
	public function action_approve()
	{
		global $user_info, $topic, $board;

		checkSession('get');

		$current_msg = $this->_req->getQuery('msg', 'intval', 0);

		// Needy baby, Greedy baby
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		isAllowedTo('approve_posts');

		$message_info = basicMessageInfo($current_msg, false, true);

		// If it's the first in a topic then the whole topic gets approved!
		if ($message_info['id_first_msg'] == $current_msg)
			approveTopics($topic, !$message_info['approved'], $message_info['id_member_started'] != $user_info['id']);
		else
		{
			approvePosts($current_msg, !$message_info['approved']);

			if ($message_info['id_member'] != $user_info['id'])
				logAction(($message_info['approved'] ? 'un' : '') . 'approve', array('topic' => $topic, 'subject' => $message_info['subject'], 'member' => $message_info['id_member'], 'board' => $board));
		}

		Cache::instance()->remove('num_menu_errors');

		redirectexit('topic=' . $topic . '.msg' . $current_msg . '#msg' . $current_msg);
	}
}