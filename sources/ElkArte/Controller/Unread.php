<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Handles the finding of Unread posts and replies
 */
class Unread extends \ElkArte\AbstractController
{
	/**
	 * The board ids we are marking
	 * @var array
	 */
	private $_boards = array();

	/**
	 * @var bool
	 */
	private $_is_topics = false;

	/**
	 * Number of topics
	 * @var int
	 */
	private $_num_topics = 0;

	/**
	 * The action being performed
	 * @var string
	 */
	private $_action = 'unread';

	/**
	 * @var bool
	 */
	private $_action_unread = false;

	/**
	 * @var bool
	 */
	private $_action_unreadreplies = false;

	/**
	 * The object that will retrieve the data
	 * @var Unread
	 */
	private $_grabber = null;

	/**
	 * Called before any other action method in this class.
	 *
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 *
	 * @uses template_unread() in Recent.template.php
	 */
	public function pre_dispatch()
	{
		global $txt, $scripturl, $context, $settings, $modSettings, $options, $user_info;

		// Guests can't have unread things, we don't know anything about them.
		is_not_guest();

		// Pre-fetching + lots of MySQL work = bad mojo.
		stop_prefetching();

		require_once(SUBSDIR . '/Recent.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// Determine the action, unreadreplies or unread
		$this->_action = $this->_req->getQuery('action') === 'unreadreplies' ? 'unreadreplies' : 'unread';
		$this->_action_unread = $this->_action === 'unread';
		$this->_action_unreadreplies = $this->_action !== 'unread';

		// Some goodies for template use
		$context['showCheckboxes'] = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];
		$context['showing_all_topics'] = isset($this->_req->query->all);
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);
		$context['topics_per_page'] = (int) (empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics']);

		// Initialize the Unread class
		$this->_grabber = new \ElkArte\Unread($user_info['id'], $modSettings['postmod_active'], $modSettings['enable_unwatch'], $context['showing_all_topics']);

		// Make sure we can continue
		$this->_checkServerLoad();

		// Set the right page title for the action we are performing
		if ($this->_action_unread)
		{
			$context['page_title'] = $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'];
		}
		else
		{
			$context['page_title'] = $txt['unread_replies'];
		}

		// Are we specifying any specific board?
		$this->_wanted_boards();
		$this->_sorting_conditions();

		if (!empty($this->_req->query->c) && is_array($this->_req->query->c) && count($this->_req->query->c) == 1)
		{
			require_once(SUBSDIR . '/Categories.subs.php');
			$name = categoryName((int) $this->_req->query->c[0]);

			$context['linktree'][] = array(
				'url' => getUrl('action', $modSettings['default_forum_action']) . '#c' . (int) $this->_req->query->c[0],
				'name' => $name
			);
		}

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=' . $this->_action . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
			'name' => $this->_action_unread ? $txt['unread_topics_visit'] : $txt['unread_replies']
		);

		// Prepare the template
		theme()->getTemplates()->load('Recent');
		$context['sub_template'] = 'unread';
		$context['unread_header_title'] = $this->_action_unread ? ($context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit']) : $txt['unread_replies'];
		$template_layers = theme()->getLayers();
		$template_layers->add($context['sub_template']);

		$this->_is_topics = $this->_action_unread;

		// If empty, no preview at all
		if (!empty($modSettings['message_index_preview']))
		{
			// If 0 means everything
			if (empty($modSettings['preview_characters']))
			{
				$this->_grabber->bodyPreview(true);
			}
			// Default: a SUBSTRING
			else
			{
				$this->_grabber->bodyPreview($modSettings['preview_characters']);
			}
		}
	}

	/**
	 * Intended entry point for unread controller class.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// Figure out what action to do .. Thinking, Thinking, OK unread
		$this->action_unread();
	}

	/**
	 * Find unread topics.
	 *
	 * Accessed by action=unread
	 */
	public function action_unread()
	{
		global $context, $modSettings, $settings;

		$this->_grabber->setAction(\ElkArte\Unread::UNREAD);
		$this->_grabber->setEarliestMsg($context['showing_all_topics'] ? earliest_msg() : 0);

		// @todo Add modified_time in for log_time check?
		// Let's copy things out of the log_topics table, to reduce searching.
		if ($modSettings['totalMessages'] > 100000 && $context['showing_all_topics'])
		{
			$this->_grabber->createTempTable();
		}

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
		{
			return $this->action_unreadreplies();
		}

		if ($this->_num_topics == 0)
		{
			// Messages mark always, topics only if this is an all topics query
			if ($type === 'message' || ($type === 'topics' && $context['showing_all_topics']))
			{
				// Since there are no unread topics, mark the boards as read!
				// @todo look at this... there are no more unread topics already.
				// If clearing of log_topics is still needed, perhaps do it separately.
				markBoardsRead($this->_boards, false, true);
			}

			$context['topics'] = array();

			if ($context['querystring_board_limits'] === ';start=%1$d')
			{
				$context['querystring_board_limits'] = '';
			}
			else
			{
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $this->_req->query->start);
			}
		}
		else
		{
			$context['topics'] = $this->_grabber->getUnreads($type, $this->_req->query->start, $context['topics_per_page'], $settings['avatars_on_indexes']);
		}
		$this->_exiting_unread();

		return true;
	}

	/**
	 * Find unread replies.
	 *
	 * Accessed by action=unreadreplies
	 */
	public function action_unreadreplies()
	{
		global $scripturl, $context, $modSettings, $settings;

		$this->_grabber->setAction(\ElkArte\Unread::UNREADREPLIES);

		if ($modSettings['totalMessages'] > 100000)
		{
			$this->_grabber->createTempTable();
		}

		$this->_num_topics = $this->_grabber->numUnreads();

		if ($this->_num_topics == 0)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] === ';start=%1$d')
			{
				$context['querystring_board_limits'] = '';
			}
			else
			{
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $this->_req->query->start);
			}
		}
		else
		{
			$context['links'] += array(
				'first' => $this->_req->query->start >= $context['topics_per_page'] ? $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
				'last' => $this->_req->query->start + $context['topics_per_page'] < $this->_num_topics ? $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($this->_num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'up' => $scripturl,
			);
			$context['page_info'] = array(
				'current_page' => $this->_req->query->start / $context['topics_per_page'] + 1,
				'num_pages' => floor(($this->_num_topics - 1) / $context['topics_per_page']) + 1
			);
			$context['topics'] = $this->_grabber->getUnreads(null, $this->_req->query->start, $context['topics_per_page'], $settings['avatars_on_indexes']);

			if ($context['topics'] === false)
			{
				$context['topics'] = array();

				if ($context['querystring_board_limits'] === ';start=%1$d')
				{
					$context['querystring_board_limits'] = '';
				}
				else
				{
					$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $this->_req->query->start);
				}

				return;
			}
		}

		$this->_exiting_unread();
	}

	/**
	 * Finds out the boards the user want.
	 */
	private function _wanted_boards()
	{
		global $board, $context;

		if (isset($this->_req->query->children) && (!empty($board) || !empty($this->_req->query->boards)))
		{
			$this->_boards = array();

			if (!empty($this->_req->query->boards))
			{
				$this->_boards = array_map('intval', explode(',', $this->_req->query->boards));
			}

			if (!empty($board))
			{
				$this->_boards[] = (int) $board;
			}

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
		elseif (!empty($this->_req->query->boards))
		{
			$selected_boards = array_map('intval', explode(',', $this->_req->query->boards));

			$this->_boards = accessibleBoards($selected_boards);

			$context['querystring_board_limits'] = ';boards=' . implode(',', $this->_boards) . ';start=%1$d';
		}
		elseif (!empty($this->_req->query->c))
		{
			$categories = array_map('intval', explode(',', $this->_req->query->c));

			$this->_boards = array_keys(boardsPosts(array(), $categories, $this->_action_unread));

			$context['querystring_board_limits'] = ';c=' . $this->_req->query->c . ';start=%1$d';

			$this->_req->query->c = explode(',', $this->_req->query->c);
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
		{
			throw new \ElkArte\Exceptions\Exception('error_no_boards_selected');
		}
		else
		{
			$this->_grabber->setBoards($this->_boards);
		}
	}

	/**
	 * Set up the array for the sorting dropdown.
	 */
	private function _sorting_conditions()
	{
		global $context, $txt, $scripturl;

		$sort_methods = array(
			'subject' => 'ms.subject',
			'starter' => 'COALESCE(mems.real_name, ms.poster_name)',
			'replies' => 't.num_replies',
			'views' => 't.num_views',
			'first_post' => 't.id_topic',
			'last_post' => 't.id_last_msg'
		);

		// The default is the most logical: newest first.
		if (!isset($this->_req->query->sort) || !isset($sort_methods[$this->_req->query->sort]))
		{
			$context['sort_by'] = 'last_post';
			$ascending = isset($this->_req->query->asc);

			$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
		}
		// But, for other methods the default sort is ascending.
		else
		{
			$context['sort_by'] = $this->_req->query->sort;
			$ascending = !isset($this->_req->query->desc);

			$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
		}

		$this->_grabber->setSorting($sort_methods[$context['sort_by']], $ascending);

		$context['sort_direction'] = $ascending ? 'up' : 'down';
		$context['sort_title'] = $ascending ? $txt['sort_desc'] : $txt['sort_asc'];

		// Trick
		$txt['starter'] = $txt['started_by'];

		foreach ($sort_methods as $key => $val)
		{
			switch ($key)
			{
				case 'subject':
				case 'starter':
					$sorticon = 'alpha';
					break;
				default:
					$sorticon = 'numeric';
			}

			$context['topics_headers'][$key] = array('url' => $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $this->_req->query->start) . ';sort=' . $key . ($context['sort_by'] == $key && $context['sort_direction'] === 'up' ? ';desc' : ''), 'sort_dir_img' => $context['sort_by'] == $key ? '<i class="icon icon-small i-sort-' . $sorticon . '-' . $context['sort_direction'] . '" title="' . $context['sort_title'] . '"></i>' : '',);
		}
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
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $this->_action . $all . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $this->_req->query->start, $this->_num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $this->_req->query->start / $context['topics_per_page'];

		if ($context['showing_all_topics'])
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=' . $this->_action . ';all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
				'name' => $txt['unread_topics_all']
			);

			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all', $txt['unread_topics_visit_none']);
		}
		else
		{
			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'], $txt['unread_topics_visit_none']);
		}

		$context['links'] += array(
			'prev' => $this->_req->query->start >= $context['topics_per_page'] ? $scripturl . '?action=' . $this->_action . $all . sprintf($context['querystring_board_limits'], $this->_req->query->start - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $this->_req->query->start + $context['topics_per_page'] < $this->_num_topics ? $scripturl . '?action=' . $this->_action . $all . sprintf($context['querystring_board_limits'], $this->_req->query->start + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
		);
		$context['page_info'] = array(
			'current_page' => $this->_req->query->start / $context['topics_per_page'] + 1,
			'num_pages' => floor(($this->_num_topics - 1) / $context['topics_per_page']) + 1
		);

		$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $this->_req->query->start);
		$topics_to_mark = implode('-', $topic_ids);

		if ($settings['show_mark_read'])
		{
			$context['recent_buttons'] = $this->_buttonsArray($topics_to_mark);
		}

		$context['querystring_board_limits'] = 'action=' . $this->_action . $all . $context['querystring_board_limits'];

		// Allow help desks and bug trackers and what not to add their own unread
		// data (just add a template_layer to show custom stuff in the template!)
		call_integration_hook('integrate_unread_list');
	}

	/**
	 * Build the recent button array.
	 *
	 * @param string $topics_to_mark - An array of topic ids properly formatted
	 *               into a string to use in an URL
	 *
	 * @return array
	 */
	private function _buttonsArray($topics_to_mark)
	{
		global $context, $scripturl, $txt;

		if ($this->_is_topics)
		{
			theme()->addJavascriptVar(array(
				'txt_mark_as_read_confirm' => $txt['mark_these_as_read_confirm']
			), true);

			$recent_buttons = array(
				'markread' => array(
					'text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short',
					'image' => 'markread.png',
					'lang' => true,
					'custom' => 'onclick="return markunreadButton(this);"',
					'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_var'] . '=' . $context['session_id'],
				),
			);

			if ($context['showCheckboxes'])
			{
				$recent_buttons['markselectread'] = array(
					'text' => 'quick_mod_markread',
					'image' => 'markselectedread.png',
					'lang' => true,
					'url' => 'javascript:document.quickModForm.submit();',
				);
			}

			if (!empty($context['topics']) && !$context['showing_all_topics'])
			{
				$recent_buttons['readall'] = array('text' => 'unread_topics_all', 'image' => 'markreadall.png', 'lang' => true, 'url' => $scripturl . '?action=unread;all' . $context['querystring_board_limits'], 'active' => true);
			}
		}
		elseif (!$this->_is_topics && isset($topics_to_mark))
		{
			theme()->addJavascriptVar(array(
				'txt_mark_as_read_confirm' => $txt['mark_these_as_read_confirm']
			), true);

			$recent_buttons = array(
				'markread' => array(
					'text' => 'mark_these_as_read',
					'image' => 'markread.png',
					'lang' => true,
					'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $topics_to_mark . ';' . $context['session_var'] . '=' . $context['session_id'],
				),
			);

			if ($context['showCheckboxes'])
			{
				$recent_buttons['markselectread'] = array(
					'text' => 'quick_mod_markread',
					'image' => 'markselectedread.png',
					'lang' => true,
					'url' => 'javascript:document.quickModForm.submit();',
				);
			}
		}

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_recent_buttons', array(&$recent_buttons));

		return $recent_buttons;
	}

	/**
	 * Validates the server can perform the required operation given its current loading
	 */
	private function _checkServerLoad()
	{
		global $context, $modSettings;

		// Check for any server load issues
		if ($context['showing_all_topics'] && !empty($modSettings['loadavg_allunread']) && $modSettings['current_load'] >= $modSettings['loadavg_allunread'])
		{
			throw new \ElkArte\Exceptions\Exception('loadavg_allunread_disabled', false);
		}
		elseif ($this->_action_unreadreplies && !empty($modSettings['loadavg_unreadreplies']) && $modSettings['current_load'] >= $modSettings['loadavg_unreadreplies'])
		{
			throw new \ElkArte\Exceptions\Exception('loadavg_unreadreplies_disabled', false);
		}
		elseif (!$context['showing_all_topics'] && $this->_action_unread && !empty($modSettings['loadavg_unread']) && $modSettings['current_load'] >= $modSettings['loadavg_unread'])
		{
			throw new \ElkArte\Exceptions\Exception('loadavg_unread_disabled', false);
		}
	}
}
