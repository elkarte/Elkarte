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
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Unread posts and replies Controller
 */
class Unread_Controller extends Action_Controller
{
	private $_boards = array();

	private $_is_topics = false;

	private $_num_topics = 0;

	private $_action = 'unread';

	private $_action_unread = false;

	private $_action_unreadreplies = false;

	/**
	 * The object that will retrieve the data
	 */
	private $_grabber = null;

	/**
	 * Called before any other action method in this class.
	 *
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
		global $txt, $scripturl, $context, $settings, $modSettings, $options, $user_info;

		// Guests can't have unread things, we don't know anything about them.
		is_not_guest();

		// Prefetching + lots of MySQL work = bad mojo.
		stop_prefetching();

		require_once(SUBSDIR . '/Recent.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Unread.class.php');

		$this->_action = !isset($_REQUEST['action']) && $_REQUEST['action'] === 'unreadreplies' ? $_REQUEST['action'] : 'unread';
		$this->_action_unread = $this->_action === 'unread';
		$this->_action_unreadreplies = $this->_action !== 'unread';

		$context['showCheckboxes'] = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];
		$context['showing_all_topics'] = isset($_GET['all']);
		$context['start'] = (int) $_REQUEST['start'];
		$context['topics_per_page'] = (int) (empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics']);

		$this->_grabber = new Unread_Class($user_info['id'], $modSettings['postmod_active'], $modSettings['enable_unwatch'], $context['showing_all_topics']);

		if ($this->_action_unread)
			$context['page_title'] = $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'];
		else
			$context['page_title'] = $txt['unread_replies'];

		if ($context['showing_all_topics'] && !empty($modSettings['loadavg_allunread']) && $modSettings['current_load'] >= $modSettings['loadavg_allunread'])
			fatal_lang_error('loadavg_allunread_disabled', false);
		elseif ($this->_action_unreadreplies && !empty($modSettings['loadavg_unreadreplies']) && $modSettings['current_load'] >= $modSettings['loadavg_unreadreplies'])
			fatal_lang_error('loadavg_unreadreplies_disabled', false);
		elseif (!$context['showing_all_topics'] && $this->_action_unread && !empty($modSettings['loadavg_unread']) && $modSettings['current_load'] >= $modSettings['loadavg_unread'])
			fatal_lang_error('loadavg_unread_disabled', false);

		// Are we specifying any specific board?
		$this->_wanted_boards();
		$this->_sorting_conditions();

		if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1)
		{
			$name = categoryName((int) $_REQUEST['c'][0]);

			$context['linktree'][] = array(
				'url' => $scripturl . '#c' . (int) $_REQUEST['c'][0],
				'name' => $name
			);
		}

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=' . $this->_action . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
			'name' => $this->_action_unread ? $txt['unread_topics_visit'] : $txt['unread_replies']
		);

		loadTemplate('Recent');
		$context['sub_template'] = 'unread';
		$context['unread_header_title'] = $this->_action_unread ? ($context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit']) : $txt['unread_replies'];

		// Setup the default topic icons... for checking they exist and the like ;)
		require_once(SUBSDIR . '/MessageIndex.subs.php');
		$context['icon_sources'] = MessageTopicIcons();

		$this->_is_topics = $this->_action_unread;

		// If empty, no preview at all
		if (!empty($modSettings['message_index_preview']))
		{
			// If 0 means everything
			if (empty($modSettings['preview_characters']))
				$this->_grabber->bodyPreview(true);
			// Default: a SUBSTRING
			else
				$this->_grabber->bodyPreview($modSettings['preview_characters']);
		}
	}

	/**
	 * Intended entry point for unread controller class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Figure out what action to do
		$this->action_unread();
	}

	/**
	 * Find unread topics.
	 * Accessed by action=unread
	 */
	public function action_unread()
	{
		global $context, $modSettings, $settings;

		$this->_grabber->setAction(Unread_Class::UNREAD);

		$this->_grabber->setEarliestMsg($context['showing_all_topics'] ? earliest_msg() : 0);

		// @todo Add modified_time in for log_time check?
		// Let's copy things out of the log_topics table, to reduce searching.
		if ($modSettings['totalMessages'] > 100000 && $context['showing_all_topics'])
			$this->_grabber->createTempTable();

		// All unread replies with temp table
		if ($context['showing_all_topics'] && $this->_grabber->hasTempTable())
		{
			$this->_num_topics = $this->_grabber->numUnreads(false);
			$type = 'message';
		}
		// New posts with or without temp table
		elseif ($this->_is_topics)
		{
			$this->_num_topics = $this->_grabber->numUnreads(empty($_SESSION['first_login']), $_SESSION['id_msg_last_visit']);
			$type = 'topics';
		}
		// Does it make sense?... Dunno.
		else
			return $this->action_unreadreplies();

		if ($this->_num_topics == 0)
		{
			// Messages mark always, topics only if this is an all topics query
			if ($type == 'message' || ($type == 'topics' && $context['showing_all_topics']))
			{
				// Since there are no unread topics, mark the boards as read!
				// @todo look at this... there are no more unread topics already.
				// If clearing of log_topics is still needed, perhaps do it separately.
				markBoardsRead($this->_boards, false, true);
			}

			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%1$d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		$context['topics'] = $this->_grabber->getUnreads($type, $_REQUEST['start'], $context['topics_per_page'], !empty($settings['avatars_on_indexes']));

		$this->_exiting_unread();
	}

	/**
	 * Find unread replies.
	 * Accessed by action=unreadreplies
	 */
	public function action_unreadreplies()
	{
		global $scripturl, $context, $modSettings, $settings;

		$this->_grabber->setAction(Unread_Class::UNREADREPLIES);

		if ($modSettings['totalMessages'] > 100000)
			$this->_grabber->createTempTable();

		$this->_num_topics = $this->_grabber->numUnreads();

		if ($this->_num_topics == 0)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%1$d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		$context['links'] += array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $this->_num_topics ? $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($this->_num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($this->_num_topics - 1) / $context['topics_per_page']) + 1
		);

		$context['topics'] = $this->_grabber->getUnreads(null, $_REQUEST['start'], $context['topics_per_page'], !empty($settings['avatars_on_indexes']));

		if ($context['topics'] === false)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%1$d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		$this->_exiting_unread();
	}

	/**
	 * Finds out the boards the user want.
	 */
	private function _wanted_boards()
	{
		global $board, $context;

		if (isset($_REQUEST['children']) && (!empty($board) || !empty($_REQUEST['boards'])))
		{
			$this->_boards = array();

			if (!empty($_REQUEST['boards']))
			{
				$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
				foreach ($_REQUEST['boards'] as $b)
					$this->_boards[] = (int) $b;
			}

			if (!empty($board))
				$this->_boards[] = (int) $board;

			// The easiest thing is to just get all the boards they can see,
			// but since we've specified the top of tree we ignore some of them
			$this->_boards = addChildBoards($this->_boards);

			$context['querystring_board_limits'] = ';boards=' . implode(',', $this->_boards) . ';start=%d';
		}
		elseif (!empty($board))
		{
			$this->_boards = array($board);
			$context['querystring_board_limits'] = ';board=' . $board . '.%1$d';
		}
		elseif (!empty($_REQUEST['boards']))
		{
			$selected_boards = array_map('intval', explode(',', $_REQUEST['boards']));

			$this->_boards = accessibleBoards($selected_boards);

			$context['querystring_board_limits'] = ';boards=' . implode(',', $this->_boards) . ';start=%1$d';
		}
		elseif (!empty($_REQUEST['c']))
		{
			$categories = array_map('intval', explode(',', $_REQUEST['c']));

			$this->_boards = array_keys(boardsPosts(array(), $categories, $this->_action_unread));

			$context['querystring_board_limits'] = ';c=' . $_REQUEST['c'] . ';start=%1$d';
		}
		else
		{
			$see_board = $this->_action_unreadreplies ? 'query_see_board' : 'query_wanna_see_board';

			// Don't bother to show deleted posts!
			$this->_boards = wantedBoards($see_board);

			$context['querystring_board_limits'] = ';start=%1$d';
			$context['no_board_limits'] = true;
		}

		if (empty($this->_boards))
			fatal_lang_error('error_no_boards_selected');

		$this->_grabber->setBoards($this->_boards);
	}

	/**
	 * Set up the array for the sorting dropdown.
	 */
	private function _sorting_conditions()
	{
		global $context, $txt, $scripturl, $settings;

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
		$this->_grabber->setSorting($sort_methods[$context['sort_by']], $ascending);

		$context['sort_direction'] = $ascending ? 'up' : 'down';
		$context['sort_title'] = $ascending ? $txt['sort_desc'] : $txt['sort_asc'];

		// Trick
		$txt['starter'] = $txt['started_by'];

		foreach ($sort_methods as $key => $val)
			$context['topics_headers'][$key] = array(
				'url' => $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start']) . ';sort=subject' . ($context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : ''),
				'sort_dir_img' => $context['sort_by'] == $key ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" title="' . $context['sort_title'] .'" />' : '',
			);
	}

	/**
	 * Some common things done at the end of each action.
	 */
	private function _exiting_unread()
	{
		global $scripturl, $user_info, $context, $settings, $modSettings, $txt;

		$topic_ids = array_keys($context['topics']);

		if ($this->_is_topics && !empty($modSettings['enableParticipation']) && !empty($topic_ids))
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

		$all = $context['showing_all_topics'] ? ';all' : '';
		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $this->_action . $all . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $this->_num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		if ($context['showing_all_topics'])
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=' . $this->_action . ';all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
				'name' => $txt['unread_topics_all']
			);
			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all', $txt['unread_topics_visit_none']);
		}
		else
			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'], $txt['unread_topics_visit_none']);

		$context['links'] += array(
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $this->_action . $all . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $this->_num_topics ? $scripturl . '?action=' . $this->_action . $all . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($this->_num_topics - 1) / $context['topics_per_page']) + 1
		);

		$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
		$topics_to_mark = implode('-', $topic_ids);

		if ($settings['show_mark_read'])
			$context['recent_buttons'] = $this->_buttonsArray($topics_to_mark);

		$context['querystring_board_limits'] = 'action=' . $this->_action . $all . $context['querystring_board_limits'];

		// Allow helpdesks and bug trackers and what not to add their own unread data (just add a template_layer to show custom stuff in the template!)
		call_integration_hook('integrate_unread_list');
	}

	/**
	 * Build the recent button array.
	 *
	 * @param string $topics_to_mark - An array of topic ids properly formatted
	 *               into a string to use in an URL
	 */
	private function _buttonsArray($topics_to_mark)
	{
		global $context, $scripturl;

		if ($this->_is_topics)
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
		elseif (!$this->_is_topics && isset($topics_to_mark))
		{
			$context['recent_buttons'] = array(
				'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $topics_to_mark . ';' . $context['session_var'] . '=' . $context['session_id']),
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
		/**
		 * @deprecated in order to maintain backward compatibility the buttons are
		 * loaded into $context.
		 * Starting from 2.0 this should be changed to a local variable and passed to the hook
		 */
		call_integration_hook('integrate_recent_buttons', array(&$context['recent_buttons']));

		return $context['recent_buttons'];
	}
}