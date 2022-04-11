<?php

/**
 * This file is what shows the listing of topics in a board.
 * It's just one or two functions, but don't underestimate it ;).
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\BoardsList;
use ElkArte\EventManager;
use ElkArte\FrontpageInterface;
use ElkArte\TopicUtil;
use ElkArte\User;

/**
 */
class MessageIndex extends AbstractController implements FrontpageInterface
{
	/**
	 * {@inheritdoc}
	 */
	public static function frontPageHook(&$default_action)
	{
		add_integration_function('integrate_menu_buttons', '\\ElkArte\\Controller\\MessageIndex::addForumButton', '', false);
		add_integration_function('integrate_current_action', '\\ElkArte\\Controller\\MessageIndex::fixCurrentAction', '', false);

		$default_action = array(
			'controller' => '\\ElkArte\\Controller\\MessageIndex',
			'function' => 'action_messageindex_fp'
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public static function frontPageOptions()
	{
		parent::frontPageOptions();

		theme()->addInlineJavascript('
			$(\'#front_page\').on(\'change\', function() {
				var $base = $(\'#message_index_frontpage\').parent();
				if ($(this).val() == \'\ElkArte\Controller\MessageIndex\')
				{
					$base.fadeIn();
					$base.prev().fadeIn();
				}
				else
				{
					$base.fadeOut();
					$base.prev().fadeOut();
				}
			}).change();', true);

		return array(array('select', 'message_index_frontpage', self::_getBoardsList()));
	}

	/**
	 * Return the board listing for use in this class
	 *
	 * @return string[] list of boards with key = id and value = cat + name
	 * @uses getBoardList()
	 */
	protected static function _getBoardsList()
	{
		// Load the boards list.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_list = getBoardList(array('override_permissions' => true, 'not_redirection' => true), true);

		$boards = array();
		foreach ($boards_list as $board)
		{
			$boards[$board['id_board']] = $board['cat_name'] . ' - ' . $board['board_name'];
		}

		return $boards;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function validateFrontPageOptions($post)
	{
		parent::validateFrontPageOptions($post);
		$boards = self::_getBoardsList();

		if (empty($post->message_index_frontpage) || !isset($boards[$post->message_index_frontpage]))
		{
			$post->front_page = null;

			return false;
		}

		return true;
	}

	/**
	 * Dispatches forward to message index handler.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// Forward to message index, it's not like we know much more :P
		$this->action_messageindex();
	}

	/**
	 * Show the list of topics in this board, along with any sub-boards.
	 *
	 * @uses template_topic_listing() sub template of the MessageIndex template
	 */
	public function action_messageindex()
	{
		global $txt, $board, $modSettings, $context, $options, $settings, $board_info;

		// Fairly often, we'll work with boards. Current board, sub-boards.
		require_once(SUBSDIR . '/Boards.subs.php');

		// If this is a redirection board head off.
		if ($board_info['redirect'])
		{
			incrementBoard($board, 'num_posts');
			redirectexit($board_info['redirect']);
		}

		theme()->getTemplates()->load('MessageIndex');
		loadJavascriptFile('topic.js');

		$bbc = ParserWrapper::instance();

		$context['name'] = $board_info['name'];
		$context['sub_template'] = 'topic_listing';
		$context['description'] = $bbc->parseBoard($board_info['description']);
		$template_layers = theme()->getLayers();

		// How many topics do we have in total?
		$board_info['total_topics'] = allowedTo('approve_posts') ? $board_info['num_topics'] + $board_info['unapproved_topics'] : $board_info['num_topics'] + $board_info['unapproved_user_topics'];

		// View all the topics, or just a few?
		$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
		$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$maxindex = isset($this->_req->query->all) && !empty($modSettings['enableAllMessages']) ? $board_info['total_topics'] : $context['topics_per_page'];

		// Right, let's only index normal stuff!
		$session_name = session_name();
		foreach ($this->_req->query as $k => $v)
		{
			// Don't index a sort result etc.
			if (!in_array($k, array('board', 'start', $session_name)))
			{
				$context['robot_no_index'] = true;
			}
		}

		if (!empty($this->_req->query->start) && (!is_numeric($this->_req->query->start) || $this->_req->query->start % $context['messages_per_page'] !== 0))
		{
			$context['robot_no_index'] = true;
		}

		// If we can view unapproved messages and there are some build up a list.
		if (allowedTo('approve_posts') && ($board_info['unapproved_topics'] || $board_info['unapproved_posts']))
		{
			$untopics = $board_info['unapproved_topics'] ? '<a href="' . getUrl('action', ['action' => 'moderate', 'area' => 'postmod', 'sa' => 'topics', 'brd' => $board]) . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
			$unposts = $board_info['unapproved_posts'] ? '<a href="' . getUrl('action', ['action' => 'moderate', 'area' => 'postmod', 'sa' => 'posts', 'brd' => $board]) . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;
			$context['unapproved_posts_message'] = sprintf($txt['there_are_unapproved_topics'], $untopics, $unposts, getUrl('action', ['action' => 'moderate', 'area' => 'postmod', 'sa' => ($board_info['unapproved_topics'] ? 'topics' : 'posts'), 'brd' => $board]));
		}

		// And now, what we're here for: topics!
		require_once(SUBSDIR . '/MessageIndex.subs.php');

		// Known sort methods.
		$sort_methods = messageIndexSort();
		$default_sort_method = 'last_post';

		// We only know these.
		if (isset($this->_req->query->sort) && !isset($sort_methods[$this->_req->query->sort]))
		{
			$this->_req->query->sort = $default_sort_method;
		}

		// Make sure the starting place makes sense and construct the page index.
		$sort_string = '';
		if (isset($this->_req->query->sort))
		{
			$sort_string = ';sort=' . $this->_req->query->sort . (isset($this->_req->query->desc) ? ';desc' : '');
		}

		$context['page_index'] = constructPageIndex('{scripturl}?board=' . $board . '.%1$d' . $sort_string, $this->_req->query->start, $board_info['total_topics'], $maxindex, true);
		$context['start'] = &$this->_req->query->start;

		// Set a canonical URL for this page.
		$context['canonical_url'] = getUrl('board', ['board' => $board, 'start' => $context['start'], 'name' => $board_info['name']]);

		$context['links'] += array(
			'prev' => $this->_req->query->start >= $context['topics_per_page'] ? getUrl('board', ['board' => $board, 'start' => $this->_req->query->start - $context['topics_per_page'], 'name' => $board_info['name']]) : '',
			'next' => $this->_req->query->start + $context['topics_per_page'] < $board_info['total_topics'] ? getUrl('board', ['board' => $board, 'start' => $this->_req->query->start + $context['topics_per_page'], 'name' => $board_info['name']]) : '',
		);

		if (isset($this->_req->query->all) && !empty($modSettings['enableAllMessages']) && $maxindex > $modSettings['enableAllMessages'])
		{
			$maxindex = $modSettings['enableAllMessages'];
			$this->_req->query->start = 0;
		}

		// Build a list of the board's moderators.
		$context['moderators'] = &$board_info['moderators'];
		$context['link_moderators'] = array();
		if (!empty($board_info['moderators']))
		{
			foreach ($board_info['moderators'] as $mod)
			{
				$context['link_moderators'][] = '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $mod['id'], 'name' => $mod['name']]) . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';
			}
		}

		// Mark current and parent boards as seen.
		if ($this->user->is_guest === false)
		{
			// We can't know they read it if we allow prefetches.
			stop_prefetching();

			// Mark the board as read, and its parents.
			if (!empty($board_info['parent_boards']))
			{
				$board_list = array_keys($board_info['parent_boards']);
				$board_list[] = $board;
			}
			else
			{
				$board_list = array($board);
			}

			// Mark boards as read. Boards alone, no need for topics.
			markBoardsRead($board_list, false, false);

			// Clear topicseen cache
			if (!empty($board_info['parent_boards']))
			{
				// We've seen all these boards now!
				foreach ($board_info['parent_boards'] as $k => $dummy)
				{
					if (isset($_SESSION['topicseen_cache'][$k]))
					{
						unset($_SESSION['topicseen_cache'][$k]);
					}
				}
			}

			if (isset($_SESSION['topicseen_cache'][$board]))
			{
				unset($_SESSION['topicseen_cache'][$board]);
			}

			// From now on, they've seen it. So we reset notifications.
			$context['is_marked_notify'] = resetSentBoardNotification($this->user->id, $board);
		}
		else
		{
			$context['is_marked_notify'] = false;
		}

		// 'Print' the header and board info.
		$context['page_title'] = strip_tags($board_info['name']);

		// Set the variables up for the template.
		$context['can_mark_notify'] = allowedTo('mark_notify') && $this->user->is_guest === false;
		$context['can_post_new'] = allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
		$context['can_post_poll'] = !empty($modSettings['pollMode']) && allowedTo('poll_post') && $context['can_post_new'];
		$context['can_moderate_forum'] = allowedTo('moderate_forum');
		$context['can_approve_posts'] = allowedTo('approve_posts');

		// Prepare sub-boards for display.
		$boardIndexOptions = array(
			'include_categories' => false,
			'base_level' => $board_info['child_level'] + 1,
			'parent_id' => $board_info['id'],
			'set_latest_post' => false,
			'countChildPosts' => !empty($modSettings['countChildPosts']),
		);
		$boardlist = new BoardsList($boardIndexOptions);
		$context['boards'] = $boardlist->getBoards();

		// Nosey, nosey - who's viewing this board?
		if (!empty($settings['display_who_viewing']))
		{
			require_once(SUBSDIR . '/Who.subs.php');
			formatViewers($board, 'board');
		}

		// They didn't pick one, default to by last post descending.
		if (!isset($this->_req->query->sort) || !isset($sort_methods[$this->_req->query->sort]))
		{
			$context['sort_by'] = $default_sort_method;
			$ascending = isset($this->_req->query->asc);
		}
		// Otherwise sort by user selection and default to ascending.
		else
		{
			$context['sort_by'] = $this->_req->query->sort;
			$ascending = !isset($this->_req->query->desc);
		}

		$sort_column = $sort_methods[$context['sort_by']];

		$context['sort_direction'] = $ascending ? 'up' : 'down';
		$context['sort_title'] = $ascending ? $txt['sort_desc'] : $txt['sort_asc'];

		// Trick
		$txt['starter'] = $txt['started_by'];

		// todo: Need to move this to theme.
		foreach ($sort_methods as $key => $val)
		{
			switch ($key)
			{
				case 'subject':
				case 'starter':
				case 'last_poster':
					$sorticon = 'alpha';
					break;
				default:
					$sorticon = 'numeric';
			}

			$context['topics_headers'][$key] = array(
				'url' => getUrl('board', ['board' => $context['current_board'], 'start' => $context['start'], 'sort' => $key, 'name' => $board_info['name'], $context['sort_by'] == $key && $context['sort_direction'] === 'up' ? 'desc' : '']),
				'sort_dir_img' => $context['sort_by'] == $key ? '<i class="icon icon-small i-sort-' . $sorticon . '-' . $context['sort_direction'] . '" title="' . $context['sort_title'] . '"><s>' . $context['sort_title'] . '</s></i>' : '',
			);
		}

		// Calculate the fastest way to get the topics.
		$start = (int) $this->_req->query->start;
		if ($start > ($board_info['total_topics'] - 1) / 2)
		{
			$ascending = !$ascending;
			$fake_ascending = true;
			$maxindex = $board_info['total_topics'] < $start + $maxindex + 1 ? $board_info['total_topics'] - $start : $maxindex;
			$start = $board_info['total_topics'] < $start + $maxindex + 1 ? 0 : $board_info['total_topics'] - $start - $maxindex;
		}
		else
		{
			$fake_ascending = false;
		}

		$context['topics'] = array();

		// Set up the query options
		$indexOptions = array(
			'only_approved' => $modSettings['postmod_active'] && !allowedTo('approve_posts'),
			'previews' => !empty($modSettings['message_index_preview']) ? (empty($modSettings['preview_characters']) ? -1 : $modSettings['preview_characters']) : 0,
			'include_avatars' => $settings['avatars_on_indexes'],
			'ascending' => $ascending,
			'fake_ascending' => $fake_ascending
		);

		// Allow integration to modify / add to the $indexOptions
		call_integration_hook('integrate_messageindex_topics', array(&$sort_column, &$indexOptions));

		$topics_info = messageIndexTopics($board, $this->user->id, $start, $maxindex, $context['sort_by'], $sort_column, $indexOptions);

		$context['topics'] = TopicUtil::prepareContext($topics_info, false, !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128);

		// Allow addons to add to the $context['topics']
		call_integration_hook('integrate_messageindex_listing', array($topics_info));

		// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
		if ($fake_ascending)
		{
			$context['topics'] = array_reverse($context['topics'], true);
		}

		$topic_ids = array_keys($context['topics']);

		if (!empty($modSettings['enableParticipation']) && $this->user->is_guest === false && !empty($topic_ids))
		{
			$topics_participated_in = topicsParticipation($this->user->id, $topic_ids);
			foreach ($topics_participated_in as $participated)
			{
				$context['topics'][$participated['id_topic']]['is_posted_in'] = true;
				$context['topics'][$participated['id_topic']]['class'] = 'my_' . $context['topics'][$participated['id_topic']]['class'];
			}
		}

		// Trigger a topic loaded event
		$this->_events->trigger('topicinfo', array('callbacks' => &$context['topics']));

		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => htmlspecialchars(strtr(strip_tags($board_info['name']), array('&amp;' => '&')), ENT_COMPAT, 'UTF-8'),
			'child_level' => $board_info['child_level'],
		);

		// Is Quick Moderation active/needed?
		if (!empty($options['display_quick_mod']) && !empty($context['topics']))
		{
			$context['can_markread'] = $context['user']['is_logged'];
			$context['can_lock'] = allowedTo('lock_any');
			$context['can_sticky'] = allowedTo('make_sticky');
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
				$started = $topic['first_post']['member']['id'] == $this->user->id;
				$context['topics'][$t]['quick_mod'] = array(
					'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
					'sticky' => allowedTo('make_sticky'),
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
			{
				$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_approve'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'] || $context['can_merge'] || $context['can_restore'];
			}
			// Or the icons?
			else
			{
				$context['can_quick_mod'] = $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'];
			}
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
		{
			$context['qmod_actions'] = array('approve', 'remove', 'lock', 'sticky', 'move', 'merge', 'restore', 'markread');
			call_integration_hook('integrate_quick_mod_actions');
		}

		if (!empty($context['boards']) && $context['start'] == 0)
		{
			$template_layers->add('display_child_boards');
		}

		// If there are children, but no topics and no ability to post topics...
		$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];
		$template_layers->add('topic_listing');

		theme()->addJavascriptVar(array('notification_board_notice' => $context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']), true);

		// Build the message index button array.
		$context['normal_buttons'] = array(
			'new_topic' => array('test' => 'can_post_new',
								 'text' => 'new_topic',
								 'lang' => true,
								 'url' => getUrl('action', ['action' => 'post', 'board' => $context['current_board'] . '.0']),
								 'active' => true),
			'notify' => array('test' => 'can_mark_notify',
							  'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify',
							  'lang' => true, 'custom' => 'onclick="return notifyboardButton(this);"',
							  'url' => getUrl('action', ['action' => 'notifyboard', 'sa' => ($context['is_marked_notify'] ? 'off' : 'on'), 'board' => $context['current_board'] . '.' . $context['start'], '{session_data}'])),
		);

		theme()->addJavascriptVar(array(
			'txt_mark_as_read_confirm' => $txt['mark_these_as_read_confirm']
		), true);

		// They can only mark read if they are logged in and it's enabled!
		if ($this->user->is_guest === false && $settings['show_mark_read'])
		{
			$context['normal_buttons']['markread'] = array(
				'text' => 'mark_read_short',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'board', 'board' => $context['current_board'] . '.0', '{session_data}']),
				'custom' => 'onclick="return markboardreadButton(this);"'
			);
		}

		// Allow adding new buttons easily.
		call_integration_hook('integrate_messageindex_buttons');

		// Trigger a post load event with quick access to normal buttons
		$this->_events->trigger('post_load', array('callbacks' => &$context['normal_buttons']));
	}

	/**
	 * Show the list of topics in this board, along with any sub-boards.
	 *
	 * @uses template_topic_listing() sub template of the MessageIndex template
	 */
	public function action_messageindex_fp()
	{
		global $modSettings, $board;

		$board = $modSettings['message_index_frontpage'];
		loadBoard();

		$this->action_messageindex();
	}

	/**
	 * Allows for moderation from the message index.
	 *
	 * @todo refactor this...
	 */
	public function action_quickmod()
	{
		global $board, $modSettings, $context;

		// Check the session = get or post.
		checkSession('request');

		// Lets go straight to the restore area.
		if ($this->_req->getPost('qaction') === 'restore' && !empty($this->_req->post->topics))
		{
			redirectexit('action=restoretopic;topics=' . implode(',', $this->_req->post->topics) . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		if (isset($_SESSION['topicseen_cache']))
		{
			$_SESSION['topicseen_cache'] = array();
		}

		// This is going to be needed to send off the notifications and for updateLastMessages().
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Notification.subs.php');

		// Process process process data.
		require_once(SUBSDIR . '/Topic.subs.php');

		// Remember the last board they moved things to.
		if (isset($this->_req->post->move_to))
		{
			$_SESSION['move_to_topic'] = array(
				'move_to' => $this->_req->post->move_to,
				// And remember the last expiry period too.
				'redirect_topic' => $this->_req->getPost('redirect_topic', 'intval', 0),
				'redirect_expires' => $this->_req->getPost('redirect_expires', 'intval', 0),
			);
		}

		// Only a few possible actions.
		$possibleActions = array();
		$actions = array();

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

			$redirect_url = 'board=' . $board . '.' . $this->_req->query->start;
		}
		else
		{
			$boards_can = boardsAllowedTo(array('make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'lock_any', 'lock_own', 'merge_any', 'approve_posts'), true, false);
			$redirect_url = $this->_req->post->redirect_url ?? ($_SESSION['old_url'] ?? getUrlQuery('action', $modSettings['default_forum_action']));
		}

		if ($this->user->is_guest === false)
		{
			$possibleActions[] = 'markread';
		}
		if (!empty($boards_can['make_sticky']))
		{
			$possibleActions[] = 'sticky';
		}
		if (!empty($boards_can['move_any']) || !empty($boards_can['move_own']))
		{
			$possibleActions[] = 'move';
		}
		if (!empty($boards_can['remove_any']) || !empty($boards_can['remove_own']))
		{
			$possibleActions[] = 'remove';
		}
		if (!empty($boards_can['lock_any']) || !empty($boards_can['lock_own']))
		{
			$possibleActions[] = 'lock';
		}
		if (!empty($boards_can['merge_any']))
		{
			$possibleActions[] = 'merge';
		}

		// Two methods: $_REQUEST['actions'] (id_topic => action), and $_REQUEST['topics'] and $this->_req->post->qaction.
		// (if action is 'move', $_REQUEST['move_to'] or $_REQUEST['move_tos'][$topic] is used.)
		if (!empty($this->_req->post->topics))
		{
			// If the action isn't valid, just quit now.
			if (empty($this->_req->post->qaction) || !in_array($this->_req->post->qaction, $possibleActions))
			{
				redirectexit($redirect_url);
			}

			// Merge requires all topics as one parameter and can be done at once.
			if ($this->_req->post->qaction === 'merge')
			{
				// Merge requires at least two topics.
				if (empty($this->_req->post->topics) || count($this->_req->post->topics) < 2)
				{
					redirectexit($redirect_url);
				}

				$controller = new MergeTopics(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();

				return $controller->action_mergeExecute($this->_req->post->topics);
			}

			// Just convert to the other method, to make it easier.
			foreach ($this->_req->post->topics as $topic)
			{
				$actions[(int) $topic] = $this->_req->post->qaction;
			}
		}
		else
		{
			$actions = $this->_req->actions;
		}

		// Weird... how'd you get here?
		if (empty($actions))
		{
			redirectexit($redirect_url);
		}

		// Validate each action.
		$all_actions = array();
		$action = '';
		foreach ($actions as $topic => $action)
		{
			if (in_array($action, $possibleActions))
			{
				$all_actions[(int) $topic] = $action;
			}
		}

		$stickyCache = array();
		$moveCache = array(0 => array(), 1 => array());
		$removeCache = array();
		$lockCache = array();
		$markCache = array();

		if (!empty($all_actions))
		{
			// Find all topics...
			$topics_info = topicsDetails(array_keys($all_actions));

			foreach ($topics_info as $row)
			{
				if (!empty($board))
				{
					if ($row['id_board'] != $board || ($modSettings['postmod_active'] && !$row['approved'] && !allowedTo('approve_posts')))
					{
						continue;
					}
				}
				elseif ($modSettings['postmod_active'] && !$row['approved'] && !in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))
				// Don't allow them to act on unapproved posts they can't see...
				{
					continue;
				}
				// Goodness, this is fun.  We need to validate the action.
				elseif ($all_actions[$row['id_topic']] === 'sticky'
					&& !in_array(0, $boards_can['make_sticky'])
					&& !in_array($row['id_board'], $boards_can['make_sticky']))
				{
					continue;
				}
				elseif ($all_actions[$row['id_topic']] === 'move'
					&& !in_array(0, $boards_can['move_any'])
					&& !in_array($row['id_board'], $boards_can['move_any'])
					&& ($row['id_member_started'] != $this->user->id
						|| (!in_array(0, $boards_can['move_own']) && !in_array($row['id_board'], $boards_can['move_own']))))
				{
					continue;
				}
				elseif ($all_actions[$row['id_topic']] === 'remove'
					&& !in_array(0, $boards_can['remove_any'])
					&& !in_array($row['id_board'], $boards_can['remove_any'])
					&& ($row['id_member_started'] != $this->user->id
						|| (!in_array(0, $boards_can['remove_own']) && !in_array($row['id_board'], $boards_can['remove_own']))))
				{
					continue;
				}
				elseif ($all_actions[$row['id_topic']] === 'lock'
					&& !in_array(0, $boards_can['lock_any'])
					&& !in_array($row['id_board'], $boards_can['lock_any'])
					&& ($row['id_member_started'] != $this->user->id
						|| $row['locked'] == 1
						|| (!in_array(0, $boards_can['lock_own']) && !in_array($row['id_board'], $boards_can['lock_own']))))
				{
					continue;
				}

				// Separate the actions.
				switch ($action)
				{
					case 'markread':
						$markCache[] = $row['id_topic'];
						break;
					case 'sticky':
						$stickyCache[] = $row['id_topic'];
						break;
					case 'move':
						if (isset($this->_req->query->current_board))
						{
							moveTopicConcurrence((int) $this->_req->query->current_board, $board, $row['id_topic']);
						}

						// $moveCache[0] is the topic, $moveCache[1] is the board to move to.
						$moveCache[1][$row['id_topic']] = (int) ($this->_req->post->move_tos[$row['id_topic']] ?? $this->_req->post->move_to);

						if (!empty($moveCache[1][$row['id_topic']]))
						{
							$moveCache[0][] = $row['id_topic'];
						}
						break;
					case 'remove':
						$removeCache[] = $row['id_topic'];
						break;
					case 'lock':
						$lockCache[] = $row['id_topic'];
						break;
				}
			}
		}

		$affectedBoards = empty($board) ? array() : array((int) $board => array(0, 0));

		// Do all the stickies...
		if (!empty($stickyCache))
		{
			toggleTopicSticky($stickyCache, true);
		}

		// Move sucka! (this is, by the by, probably the most complicated part....)
		if (!empty($moveCache[0]))
		{
			moveTopicsPermissions($moveCache);
		}

		// Now delete the topics...
		if (!empty($removeCache))
		{
			removeTopicsPermissions($removeCache);
		}

		// And (almost) lastly, lock the topics...
		if (!empty($lockCache))
		{
			toggleTopicsLock($lockCache, true);
		}

		if (!empty($markCache))
		{
			$logged_topics = getLoggedTopics($this->user->id, $markCache);

			$markArray = array();
			foreach ($markCache as $topic)
			{
				$markArray[] = array($this->user->id, $topic, $modSettings['maxMsgID'], (int) !empty($logged_topics[$topic]));
			}

			markTopicsRead($markArray, true);
		}

		updateTopicStats();
		require_once(SUBSDIR . '/Messages.subs.php');
		updateMessageStats();
		updateSettings(array(
			'calendar_updated' => time(),
		));

		if (!empty($affectedBoards))
		{
			updateLastMessages(array_keys($affectedBoards));
		}

		redirectexit($redirect_url);
	}
}
