<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;

/**
 * Handles the finding of Unread posts and replies
 */
class Unread extends AbstractController
{
	/** @var array The board ids we are marking */
	private $_boards = [];

	/** @var bool */
	private $_is_topics = false;

	/** @var int Number of topics */
	private $_num_topics = 0;

	/** @var string The action being performed */
	private $_action = 'unread';

	/** @var bool */
	private $_action_unread = false;

	/** @var bool */
	private $_action_unreadreplies = false;

	/**
	 * The object that will retrieve the data
	 *
	 * @var \ElkArte\Unread
	 */
	private $_grabber;

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
		global $txt, $scripturl, $context, $settings, $modSettings, $options;

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
		$context['showCheckboxes'] = !empty($options['display_quick_mod']) && $settings['show_mark_read'];
		$context['showing_all_topics'] = $this->_req->hasQuery('all');
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);
		$context['topics_per_page'] = (int) (empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics']);

		// Initialize the Unread class
		$this->_grabber = new \ElkArte\Unread($this->user->id, $modSettings['postmod_active'], $modSettings['enable_unwatch'], $context['showing_all_topics']);

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

		if (!empty($context['selected_categories_array']) && is_array($context['selected_categories_array']) && count($context['selected_categories_array']) === 1)
		{
			require_once(SUBSDIR . '/Categories.subs.php');
			$name = categoryName((int) $context['selected_categories_array'][0]);

			$context['breadcrumbs'][] = [
				'url' => getUrl('action', $modSettings['default_forum_action']) . '#c' . (int) $context['selected_categories_array'][0],
				'name' => $name
			];
		}

		$context['breadcrumbs'][] = [
			'url' => $scripturl . '?action=' . $this->_action . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
			'name' => $this->_action_unread ? $txt['unread_topics_visit'] : $txt['unread_replies']
		];

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
			loadJavascriptFile('elk_toolTips.js', ['defer' => true]);
			$context['message_index_preview'] = true;

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
	 * Validates the server can perform the required operation given its current loading
	 */
	private function _checkServerLoad(): void
	{
		global $context, $modSettings;
		// Check for any server load issues
		if ($context['showing_all_topics']
			&& !empty($modSettings['loadavg_allunread'])
			&& $modSettings['current_load'] >= $modSettings['loadavg_allunread'])
		{
			throw new Exception('loadavg_allunread_disabled', false);
		}

		if ($this->_action_unreadreplies
			&& !empty($modSettings['loadavg_unreadreplies'])
			&& $modSettings['current_load'] >= $modSettings['loadavg_unreadreplies'])
		{
			throw new Exception('loadavg_unreadreplies_disabled', false);
		}

		if (!$context['showing_all_topics']
			&& $this->_action_unread && !empty($modSettings['loadavg_unread'])
			&& $modSettings['current_load'] >= $modSettings['loadavg_unread'])
		{
			throw new Exception('loadavg_unread_disabled', false);
		}
	}

	/**
	 * Finds out the boards the user want.
	 */
	private function _wanted_boards(): void
	{
		global $board, $context;

		if ($this->_req->hasQuery('children') && (!empty($board) || $this->_req->hasQuery('boards')))
		{
			$this->_boards = [];

			if ($this->_req->hasQuery('boards'))
			{
				$boards_csv = $this->_req->getQuery('boards', 'trim|strval', '');
				$this->_boards = $boards_csv === '' ? [] : array_map('intval', explode(',', $boards_csv));
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
			$this->_boards = [$board];
			$context['querystring_board_limits'] = ';board=' . $board . '.%1$d';
		}
		elseif ($this->_req->hasQuery('boards'))
		{
			$boards_csv = $this->_req->getQuery('boards', 'trim|strval', '');
			$selected_boards = $boards_csv === '' ? [] : array_map('intval', explode(',', $boards_csv));

			$this->_boards = accessibleBoards($selected_boards);

			$context['querystring_board_limits'] = ';boards=' . implode(',', $this->_boards) . ';start=%1$d';
		}
		elseif ($this->_req->hasQuery('c'))
		{
			$categories_csv = $this->_req->getQuery('c', 'trim|strval', '');
			$categories = $categories_csv === '' ? [] : array_map('intval', explode(',', $categories_csv));

			$this->_boards = array_keys(boardsPosts([], $categories, $this->_action_unread));

			$context['querystring_board_limits'] = ';c=' . $categories_csv . ';start=%1$d';
			$context['selected_categories_array'] = $categories;
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
			throw new Exception('error_no_boards_selected');
		}

		$this->_grabber->setBoards($this->_boards);
	}

	/**
	 * Set up the array for the sorting dropdown.
	 */
	private function _sorting_conditions(): void
	{
		global $context, $txt, $scripturl;

		$sort_methods = [
			'subject' => 'ms.subject',
			'starter' => 'COALESCE(mems.real_name, ms.poster_name)',
			'replies' => 't.num_replies',
			'views' => 't.num_views',
			'first_post' => 't.id_topic',
			'last_post' => 't.id_last_msg'
		];

		// The default is the most logical: newest first.
		if (!isset($this->_req->query->sort, $sort_methods[$this->_req->query->sort]))
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

		foreach (array_keys($sort_methods) as $key)
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

			$context['topics_headers'][$key] = ['url' => $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $this->_req->query->start) . ';sort=' . $key . ($context['sort_by'] == $key && $context['sort_direction'] === 'up' ? ';desc' : ''), 'sort_dir_img' => $context['sort_by'] == $key ? '<i class="icon icon-small i-sort-' . $sorticon . '-' . $context['sort_direction'] . '" title="' . $context['sort_title'] . '"></i>' : '',];
		}
	}

	/**
	 * Intended entry point for unread controller class.
	 *
	 * @see AbstractController::action_index
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
	public function action_unread(): ?bool
	{
		global $context, $settings;

		$this->_grabber->setAction(\ElkArte\Unread::UNREAD);
		$this->_grabber->setEarliestMsg($context['showing_all_topics'] ? earliest_msg() : 0);

		// @todo Add modified_time in for log_time check?
		if ($this->_is_topics)
		{
			$this->_num_topics = $this->_grabber->numUnreads(empty($_SESSION['first_login']), $_SESSION['id_msg_last_visit']);
			$type = 'topics';
		}
		// Does it make sense?... Dunno.
		else
		{
			$this->action_unreadreplies();
			return null;
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

			$context['topics'] = [];

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
	public function action_unreadreplies(): void
	{
		global $scripturl, $context, $settings;

		$this->_grabber->setAction(\ElkArte\Unread::UNREADREPLIES);

		$this->_num_topics = $this->_grabber->numUnreads();

		if ($this->_num_topics == 0)
		{
			$context['topics'] = [];
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
			$context['links'] += [
				'first' => $this->_req->query->start >= $context['topics_per_page'] ? $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
				'last' => $this->_req->query->start + $context['topics_per_page'] < $this->_num_topics ? $scripturl . '?action=' . $this->_action . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($this->_num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
				'up' => $scripturl,
			];
			$context['topics'] = $this->_grabber->getUnreads(null, $this->_req->query->start, $context['topics_per_page'], $settings['avatars_on_indexes']);

			if ($context['topics'] === false)
			{
				$context['topics'] = [];

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
	 * Some common things done at the end of each action.
	 */
	private function _exiting_unread(): void
	{
		global $scripturl, $context, $settings, $modSettings, $txt;

		$topic_ids = array_keys($context['topics']);

		if ($this->_is_topics && !empty($modSettings['enableParticipation']) && !empty($topic_ids))
		{
			require_once(SUBSDIR . '/MessageIndex.subs.php');
			$topics_participated_in = topicsParticipation($this->user->id, $topic_ids);

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
		$context['page_index'] = constructPageIndex('{scripturl}?action=' . $this->_action . $all . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $this->_req->query->start, $this->_num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $this->_req->query->start / $context['topics_per_page'];

		if ($context['showing_all_topics'])
		{
			$context['breadcrumbs'][] = [
				'url' => $scripturl . '?action=' . $this->_action . ';all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'],
				'name' => $txt['unread_topics_all']
			];

			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all', $txt['unread_topics_visit_none']);
		}
		else
		{
			$txt['unread_topics_visit_none'] = str_replace('{unread_all_url}', $scripturl . '?action=unread;all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'], $txt['unread_topics_visit_none']);
		}

		$context['links'] += [
			'prev' => $this->_req->query->start >= $context['topics_per_page'] ? $scripturl . '?action=' . $this->_action . $all . sprintf($context['querystring_board_limits'], $this->_req->query->start - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $this->_req->query->start + $context['topics_per_page'] < $this->_num_topics ? $scripturl . '?action=' . $this->_action . $all . sprintf($context['querystring_board_limits'], $this->_req->query->start + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
		];

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
	private function _buttonsArray($topics_to_mark): array
	{
		global $context, $scripturl, $txt;

		if ($this->_is_topics)
		{
			theme()->addJavascriptVar([
				'txt_mark_as_read_confirm' => $txt['mark_these_as_read_confirm']
			], true);

			$recent_buttons = [
				'markread' => [
					'text' => empty($context['no_board_limits']) ? 'mark_read_short' : 'mark_as_read',
					'lang' => true,
					'custom' => 'onclick="return markunreadButton(this);"',
					'url' => $scripturl . '?action=markasread;sa=' . (empty($context['no_board_limits']) ? 'board' . $context['querystring_board_limits'] : 'all') . ';' . $context['session_var'] . '=' . $context['session_id'],
				],
			];

			if ($context['showCheckboxes'])
			{
				$recent_buttons['markselectread'] = [
					'text' => 'quick_mod_markread',
					'lang' => true,
					'url' => 'javascript:document.quickModForm.submit();',
				];
			}

			if (!empty($context['topics']) && !$context['showing_all_topics'])
			{
				$recent_buttons['readall'] = ['text' => 'unread_topics_all',
					'lang' => true,
					'url' => $scripturl . '?action=unread;all' . $context['querystring_board_limits'],
					'active' => true];
			}
		}
		elseif (!$this->_is_topics && isset($topics_to_mark))
		{
			theme()->addJavascriptVar([
				'txt_mark_as_read_confirm' => $txt['mark_these_as_read_confirm']
			], true);

			$recent_buttons = [
				'markread' => [
					'text' => 'mark_these_as_read',
					'lang' => true,
					'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $topics_to_mark . ';' . $context['session_var'] . '=' . $context['session_id'],
				],
			];

			if ($context['showCheckboxes'])
			{
				$recent_buttons['markselectread'] = [
					'text' => 'quick_mod_markread',
					'lang' => true,
					'url' => 'javascript:document.quickModForm.submit();',
				];
			}
		}

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_recent_buttons', [&$recent_buttons]);

		return $recent_buttons;
	}
}
