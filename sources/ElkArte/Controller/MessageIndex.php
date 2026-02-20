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
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\BoardsList;
use ElkArte\EventManager;
use ElkArte\FrontpageInterface;
use ElkArte\Helper\DataValidator;
use ElkArte\MembersList;
use ElkArte\TopicUtil;
use ElkArte\User;

/**
 * The all powerful messageindex, shows all the topics on a given board
 */
class MessageIndex extends AbstractController implements FrontpageInterface
{
	/** @var string The db column wer are going to sort */
	public $sort_column = '';

	/** @var array Know sort methods to db column */
	public $sort_methods = [];

	/** @var bool Sort direction asc or desc */
	public $ascending = '';

	/** @var bool if we are marking as read */
	public $is_marked_notify;

	/** @var string Chosen sort method from the request */
	public $sort_by;

	/** @var int Basically the page start */
	public $sort_start;

	/**
	 * {@inheritDoc}
	 */
	public static function frontPageHook(&$default_action)
	{
		add_integration_function('integrate_menu_buttons', '\\ElkArte\\Controller\\MessageIndex::addForumButton', '', false);
		add_integration_function('integrate_current_action', '\\ElkArte\\Controller\\MessageIndex::fixCurrentAction', '', false);

		$default_action = [
			'controller' => MessageIndex::class,
			'function' => 'action_messageindex_fp'
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function frontPageOptions()
	{
		parent::frontPageOptions();

		theme()->addInlineJavascript('
			document.getElementById("front_page").addEventListener("change", function() {
			    let base = document.getElementById("message_index_frontpage").parentNode;
			
			    if (this.value.endsWith("MessageIndex")) 
			    {
			        base.fadeIn();
			        base.previousElementSibling.fadeIn();
			    }
			    else 
			    {
			        base.fadeOut();
			        base.previousElementSibling.fadeOut();
			    }
			});
			
			// Trigger change event
			let event = new Event("change");
			document.getElementById("front_page").dispatchEvent(event);', true);

		return [['select', 'message_index_frontpage', self::_getBoardsList()]];
	}

	/**
	 * Return the board listing for use in this class
	 *
	 * @return string[] list of boards with key = id and value = cat + name
	 * @uses getBoardList()
	 */
	protected static function _getBoardsList(): array
	{
		// Load the boards list.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_list = getBoardList(['override_permissions' => true, 'not_redirection' => true], true);

		$boards = [];
		foreach ($boards_list as $board)
		{
			$boards[$board['id_board']] = $board['cat_name'] . ' - ' . $board['board_name'];
		}

		return $boards;
	}

	/**
	 * {@inheritDoc}
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
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		// Forward to the message index, it's not like we know much more :P
		$this->action_messageindex();
	}

	/**
	 * Show the list of topics in this board, along with any sub-boards.
	 *
	 * @uses template_topic_listing() sub template of the MessageIndex template
	 */
	public function action_messageindex(): void
	{
		global $txt, $context, $board_info;

		// Check for the redirection board, and if found, head off
		if ($board_info['redirect'])
		{
			$this->handleRedirectBoard();
		}

		// Load any necessary resources
		$this->loadSupportingResources();

		// Initialize $context
		$this->initializeContext();

		// Build a list of unapproved posts, if applicable
		if ($this->currentUserCanApprovePosts() && $this->hasUnapprovedPosts())
		{
			$context['unapproved_posts_message'] = $this->buildUnapprovedPostsMessage();
		}

		// Make sure the starting place makes sense and construct the page index
		$this->setPageNavigation();

		// Prepare profile links to those who can moderate on this board
		$this->setBoardModeratorLinks();

		// Mark current and parent boards as seen.
		$this->markCurrentAndParentBoardsAsSeen();

		// Load basic information about the boards children, aka sub boards
		$this->prepareSubBoardsForDisplay();

		// Who else is taking a look?
		$this->prepareWhoViewing();

		// Setup topic sort icons/options for template use
		$this->setSortIcons();

		// Load the topic listing, accounting for sort, start page, etc.
		$this->loadBoardTopics();

		// What quick moderation options are available?
		$this->quickModeration();

		// Set template details/layers
		$template_layers = theme()->getLayers();
		if (!empty($context['boards']) && $this->sort_start === 0)
		{
			$template_layers->add('display_child_boards');
		}

		// If there are children, but no topics and no ability to post topics...
		$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];

		$template_layers->add('topic_listing');

		theme()->addJavascriptVar(['notification_board_notice' => $this->is_marked_notify ? $txt['notification_disable_board'] : $txt['notification_enable_board']], true);

		// Is Quick Topic available?
		$this->quickTopic();

		// Finally, action buttons like start a new topic, notify, mark read ...
		$this->buildBoardButtons();
	}

	/**
	 * Handles redirection for a board. Increments the number of posts in the board
	 * and redirects to the specified board URL.
	 */
	private function handleRedirectBoard(): void
	{
		global $board, $board_info;

		// If this is a redirection board, head off.
		require_once(SUBSDIR . '/Boards.subs.php');

		incrementBoard($board, 'num_posts');
		redirectexit($board_info['redirect']);
	}

	/**
	 * Initializes the context by setting various variables for the template.
	 */
	private function initializeContext(): void
	{
		global $txt, $context, $board_info, $modSettings;

		$description = ParserWrapper::instance()->parseBoard($board_info['description']);

		$context += [
			'name' => $board_info['name'],
			'sub_template' => 'topic_listing',
			'description' => $description,
			'robot_no_index' => $this->setRobotNoIndex(),
			// 'Print' the header and board info.
			'page_title' => strip_tags($board_info['name']),
			'page_description' => strip_tags($description),
			// Set the variables up for the template.
			'can_mark_notify' => $this->currentUserCanMarkNotify(),
			'can_post_new' => $this->currentUserCanPostNew(),
			'can_post_poll' => $this->currentUserCanPostPoll(),
			'can_moderate_forum' => $this->currentUserCanModerate(),
			'can_approve_posts' => $this->currentUserCanApprovePosts(),
			'jump_to' => [
				'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
				'board_name' => htmlspecialchars(strtr(strip_tags($board_info['name']), ['&amp;' => '&']), ENT_COMPAT, 'UTF-8'),
				'child_level' => $board_info['child_level'],
			],
			'message_index_preview' => !empty($modSettings['message_index_preview'])
		];
	}

	/**
	 * Sets if this is a page that we do, or do not, want bots to index
	 *
	 * @return bool
	 */
	public function setRobotNoIndex(): bool
	{
		global $modSettings;

		foreach ($this->_req->query as $k => $v)
		{
			// Don't index a sort result etc.
			if (!in_array($k, ['board', 'start', session_name()], true))
			{
				return true;
			}
		}

		$start = $this->_req->getQuery('start', 'intval', 0);
		return $start !== 0 && ($start % $modSettings['defaultMaxMessages'] !== 0);
	}

	/**
	 * Checks whether the current user has permission to mark notifications
	 *
	 * @return bool True if the current user can mark notifications, false otherwise
	 */
	private function currentUserCanMarkNotify(): bool
	{
		return allowedTo('mark_notify') && $this->user->is_guest === false;
	}

	/**
	 * Checks if the current user is allowed to post new topics
	 *
	 * @return bool Returns true if the current user is allowed to post new topics, otherwise false.
	 */
	private function currentUserCanPostNew(): bool
	{
		global $modSettings;

		return allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
	}

	/**
	 * Checks if the current user can post a poll
	 *
	 * @return bool Returns true if the current user can post a poll, false otherwise
	 */
	private function currentUserCanPostPoll(): bool
	{
		global $modSettings;

		return !empty($modSettings['pollMode']) && allowedTo('poll_post') && $this->currentUserCanPostNew();
	}

	/**
	 * Checks if the current user is allowed to moderate the forum
	 *
	 * @return bool Returns true if the current user is allowed to moderate the forum, false otherwise
	 */
	private function currentUserCanModerate(): bool
	{
		return allowedTo('moderate_forum');
	}

	/**
	 * Checks if the current user has the permission to approve posts
	 *
	 * @return bool True if the current user can approve posts, false otherwise
	 */
	private function currentUserCanApprovePosts(): bool
	{
		return allowedTo('approve_posts');
	}

	/**
	 * Check if the current user can restore a topic
	 *
	 * @return bool True if they can restore a topic
	 */
	private function currentUserCanRestore(): bool
	{
		global $modSettings, $board;

		return allowedTo('move_any') && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board;
	}

	/**
	 * Loads supporting resources for the MessageIndex page.
	 */
	private function loadSupportingResources(): void
	{
		global $modSettings, $txt;

		// Fairly often, we'll work with boards. Current board, sub-boards.
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/MessageIndex.subs.php');

		theme()->getTemplates()->load('MessageIndex');
		loadJavascriptFile('topic.js');

		if (!empty($modSettings['message_index_preview']))
		{
			loadJavascriptFile('elk_toolTips.js', ['defer' => true]);
		}

		theme()->addJavascriptVar([
			'txt_mark_as_read_confirm' => $txt['mark_these_as_read_confirm']
		], true);
	}

	/**
	 * Checks if the current board has unapproved posts or topics.
	 *
	 * @return bool Returns true if the board has unapproved posts or topics, otherwise false.
	 */
	private function hasUnapprovedPosts(): bool
	{
		global $board_info;

		return $board_info['unapproved_topics'] || $board_info['unapproved_posts'];
	}

	/**
	 * Builds the message/links for the number of unapproved posts and topics in the current board.
	 *
	 * @return string The message containing the number of unapproved topics and posts.
	 */
	private function buildUnapprovedPostsMessage(): string
	{
		global $txt, $board_info, $board;

		$unApprovedTopics = $board_info['unapproved_topics'] ? '<a href="' . getUrl('action', ['action' => 'moderate', 'area' => 'postmod', 'sa' => 'topics', 'brd' => $board]) . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
		$unApprovedPosts = $board_info['unapproved_posts'] ? '<a href="' . getUrl('action', ['action' => 'moderate', 'area' => 'postmod', 'sa' => 'posts', 'brd' => $board]) . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;

		return sprintf($txt['there_are_unapproved_topics'], $unApprovedTopics, $unApprovedPosts, getUrl('action', ['action' => 'moderate', 'area' => 'postmod', 'sa' => ($board_info['unapproved_topics'] ? 'topics' : 'posts'), 'brd' => $board]));
	}

	/**
	 * Sets up the page navigation for the board view.
	 */
	private function setPageNavigation(): void
	{
		global $board, $modSettings, $context, $options, $board_info, $url_format;

		// How many topics do we have in total?
		$board_info['total_topics'] = $this->currentUserCanApprovePosts()
			? $board_info['num_topics'] + $board_info['unapproved_topics']
			: $board_info['num_topics'] + $board_info['unapproved_user_topics'];

		$all = $this->_req->isSet('all');
		$start = $this->_req->getQuery('start', 'intval', 0);

		// View all the topics, or just a few?
		$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
		$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$per_page = $all && !empty($modSettings['enableAllMessages']) ? $board_info['total_topics'] : $context['topics_per_page'];

		// Make sure the starting place makes sense based on the chosen URL style and construct the page index.
		if ($url_format === 'queryless')
		{
			$context['page_index'] = constructPageIndex('{scripturl}?board,' . $board . '.%1$d.html' . $this->buildSortingString(), $start, $board_info['total_topics'], $per_page, true);
		}
		else
		{
			$base_url = getUrl('board', ['board' => $board_info['id'], 'name' => $board_info['name']]);
			$base_url = str_replace('%', '%%', $base_url);
			$context['page_index'] = constructPageIndex($base_url . '.%1$d' . $this->buildSortingString(), $start, $board_info['total_topics'], $per_page, true);
		}

		// Set a canonical URL for this page.
		$context['canonical_url'] = getUrl('board', ['board' => $board, 'start' => $start, 'name' => $board_info['name']]);

		$context['links'] += [
			'prev' => $start >= $context['topics_per_page'] ? getUrl('board', ['board' => $board, 'start' => $start - $context['topics_per_page'], 'name' => $board_info['name']]) : '',
			'next' => $start + $context['topics_per_page'] < $board_info['total_topics'] ? getUrl('board', ['board' => $board, 'start' => $start + $context['topics_per_page'], 'name' => $board_info['name']]) : '',
		];

		if ($all && !empty($modSettings['enableAllMessages']) && $per_page > $modSettings['enableAllMessages'])
		{
			$per_page = $modSettings['enableAllMessages'];
			$start = 0;
		}

		$this->sort_start = $start;
		$context['start'] = $start;
		$context['per_page'] = $per_page;
	}

	/**
	 * Builds the sorting string for the message index page.
	 *
	 * @return string The sorting string with the chosen sort method and direction
	 */
	private function buildSortingString(): string
	{
		global $context, $txt;

		// Known sort methods.
		$this->sort_methods = messageIndexSort();
		$default_sort_method = 'last_post';

		// Requested a sorting method?
		$chosen_sort = $this->_req->getQuery('sort', 'trim', $default_sort_method);

		// We only know these.
		if (!isset($this->sort_methods[$chosen_sort]))
		{
			$chosen_sort = $default_sort_method;
		}

		$sort_string = ';sort=' . $chosen_sort . ($this->_req->isSet('desc') ? ';desc' : '');
		$this->sort_by = $chosen_sort;
		$this->ascending = $this->_req->isSet('asc');
		$this->sort_column = $this->sort_methods[$this->sort_by];

		$context['sort_by'] = $this->sort_by;
		$context['sort_direction'] = $this->ascending ? 'up' : 'down';
		$context['sort_title'] = $this->ascending ? $txt['sort_desc'] : $txt['sort_asc'];

		return $sort_string;
	}

	/**
	 * Load board moderator links into context for displaying on the template.
	 */
	private function setBoardModeratorLinks(): void
	{
		global $board_info, $context, $txt;

		// Build a list of the board's moderators.
		$context['moderators'] = &$board_info['moderators'];
		$context['link_moderators'] = [];

		if (!empty($board_info['moderators']))
		{
			foreach ($board_info['moderators'] as $mod)
			{
				$context['link_moderators'][] = '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $mod['id'], 'name' => $mod['name']]) . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';
			}
		}
	}

	/**
	 * Mark the current board and its parent boards as seen for the current user
	 */
	public function markCurrentAndParentBoardsAsSeen(): void
	{
		global $board_info, $board;

		if ($this->user->is_guest)
		{
			$this->is_marked_notify = false;
			return;
		}

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
			$board_list = [$board];
		}

		// Mark boards as read. Boards alone, no need for topics.
		markBoardsRead($board_list);

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
		$this->is_marked_notify = resetSentBoardNotification($this->user->id, $board);
	}

	/**
	 * Prepare and load sub-boards for display.
	 */
	private function prepareSubBoardsForDisplay(): void
	{
		global $board_info, $modSettings, $context;

		// Prepare sub-boards for display.
		$boardIndexOptions = [
			'include_categories' => false,
			'base_level' => $board_info['child_level'] + 1,
			'parent_id' => $board_info['id'],
			'set_latest_post' => false,
			'countChildPosts' => !empty($modSettings['countChildPosts']),
		];

		$boardList = new BoardsList($boardIndexOptions);
		$context['boards'] = $boardList->getBoards();
	}

	/**
	 * Prepares and loads into context the information about who is currently viewing the board
	 */
	private function prepareWhoViewing(): void
	{
		global $settings, $board;

		// Nosey, nosey - who's viewing this board?
		if (!empty($settings['display_who_viewing']))
		{
			require_once(SUBSDIR . '/Who.subs.php');
			formatViewers($board, 'board');
		}
	}

	/**
	 * Sets the sort icons for the topics headers in the context.
	 */
	private function setSortIcons(): void
	{
		global $context, $board, $board_info, $txt;

		// Trick
		$txt['starter'] = $txt['started_by'];

		// todo: Need to move this to theme.
		foreach ($this->sort_methods as $key => $val)
		{
			$sortIcon = match ($key)
			{
				'subject', 'starter', 'last_poster' => 'alpha',
				default => 'numeric',
			};

			$context['topics_headers'][$key] = [
				'url' => getUrl('board', ['board' => $board, 'start' => $this->sort_start, 'sort' => $key, 'name' => $board_info['name'], $this->sort_by === $key && $this->ascending ? 'desc' : 'asc']),
				'sort_dir_img' => $this->sort_by === $key ? '<i class="icon icon-small i-sort-' . $sortIcon . '-' . $context['sort_direction'] . '" title="' . $context['sort_title'] . '"><s>' . $context['sort_title'] . '</s></i>' : '',
			];
		}
	}

	/**
	 * Loads board topics into the context
	 */
	private function loadBoardTopics(): void
	{
		global $board, $modSettings, $context, $settings, $board_info;

		// Calculate the fastest way to get the topics.
		$start = $this->_req->getQuery('start', 'intval', 0);
		$per_page = $context['per_page'];
		$fake_ascending = false;
		if ($start > ($board_info['total_topics'] - 1) / 2)
		{
			// Rolled off the end?
			$start = ($start >= $board_info['total_topics']) ? $board_info['total_topics'] - $per_page : $start;
			$this->ascending = !$this->ascending;
			$fake_ascending = true;
			$per_page = $board_info['total_topics'] < $start + $per_page + 1 ? $board_info['total_topics'] - $start : $per_page;
			$start = $board_info['total_topics'] < $start + $per_page + 1 ? 0 : $board_info['total_topics'] - $start - $per_page;
		}

		$context['topics'] = [];

		// Set up the query options
		$indexOptions = [
			'only_approved' => $modSettings['postmod_active'] && !allowedTo('approve_posts'),
			'previews' => empty($modSettings['message_index_preview']) ? 0 : (empty($modSettings['preview_characters']) ? -1 : $modSettings['preview_characters']),
			'include_avatars' => $settings['avatars_on_indexes'],
			'ascending' => $this->ascending,
			'fake_ascending' => $fake_ascending
		];

		// Allow integration to modify / add to the $indexOptions
		call_integration_hook('integrate_messageindex_topics', [&$this->sort_column, &$indexOptions]);

		$topics_info = messageIndexTopics($board, $this->user->id, $start, $per_page, $this->sort_by, $this->sort_column, $indexOptions);

		$context['topics'] = TopicUtil::prepareContext($topics_info, false, empty($modSettings['preview_characters']) ? 128 : $modSettings['preview_characters']);

		// Allow addons to add to the $context['topics']
		call_integration_hook('integrate_messageindex_listing', [$topics_info]);

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
		$this->_events->trigger('topicinfo', ['callbacks' => &$context['topics']]);
	}

	/**
	 * Determines which quick moderation actions are available for this user.
	 * Loads which actions are available, on a per-topic basis, into $context.
	 */
	private function quickModeration(): void
	{
		global $modSettings, $context, $options, $board_info;

		// Is Quick Moderation active/needed?
		if (!empty($options['display_quick_mod']) && !empty($context['topics']))
		{
			$context += [
				'can_markread' => $context['user']['is_logged'],
				'can_lock' => allowedTo('lock_any'),
				'can_sticky' => allowedTo('make_sticky'),
				'can_move' => allowedTo('move_any'),
				'can_remove' => allowedTo('remove_any'),
				'can_merge' => allowedTo('merge_any'),
			];

			// Ignore approving own topics as it's unlikely to come up...
			$context['can_approve'] = $modSettings['postmod_active'] && allowedTo('approve_posts') && !empty($board_info['unapproved_topics']);

			// Can we restore topics?
			$context['can_restore'] = $this->currentUserCanRestore();

			// Set permissions for all the topics.
			foreach ($context['topics'] as $t => $topic)
			{
				$started = (int) $topic['first_post']['member']['id'] === $this->user->id;
				$context['topics'][$t]['quick_mod'] = [
					'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
					'sticky' => allowedTo('make_sticky'),
					'move' => allowedTo('move_any') || ($started && allowedTo('move_own')),
					'modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
					'remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
					'approve' => $context['can_approve'] && $topic['unapproved_posts']
				];
				$context['can_lock'] |= ($started && allowedTo('lock_own'));
				$context['can_move'] |= ($started && allowedTo('move_own'));
				$context['can_remove'] |= ($started && allowedTo('remove_own'));
			}

			// Can we even use quick moderation on this batch?
			$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_approve'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'] || $context['can_merge'] || $context['can_restore'];
			if (!empty($context['can_quick_mod']))
			{
				$this->buildQuickModerationButtons();
				$context['qmod_actions'] = ['approve', 'remove', 'lock', 'sticky', 'move', 'merge', 'restore', 'markread'];
				call_integration_hook('integrate_quick_mod_actions');
			}
		}
	}

	/**
	 * Loads into $context the moderation button array for template use.
	 * Call integrate_message_index_mod_buttons hook
	 */
	public function buildQuickModerationButtons(): void
	{
		global $context;

		$context['can_show'] = false;
		$quickMod = array_column($context['topics'], 'quick_mod', 'id');
		$context['show_qm_message_checkbox'] = array_column($context['topics'], 'id');

		// Build valid topic id's by action
		$keys = array_keys($quickMod);
		foreach (['move', 'lock', 'remove', 'approve'] as $area)
		{
			// e.g., get topic ids where this quick_mod action xxx value is valid
			$temp = array_combine($keys, array_column($quickMod, $area));
			$context['allow_qm']['can_' . $area] = array_keys($temp, true);
			${'show_' . $area} = !empty($context['allow_qm']['can_' . $area]);
		}

		// Build the mod button array with buttons that are valid for, at least some, of the messages
		$context['mod_buttons'] = [
			'move' => [
				'test' => $show_move ? 'can_move' : 'can_show',
				'text' => 'move_topic',
				'id' => 'move',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'remove' => [
				'test' => $show_remove ? 'can_remove' : 'can_show',
				'text' => 'remove_topic',
				'id' => 'remove',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'lock' => [
				'test' => $show_lock ? 'can_lock' : 'can_show',
				'text' => 'set_lock',
				'id' => 'lock',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'approve' => [
				'test' => $show_approve ? 'can_approve' : 'can_show',
				'text' => 'approve',
				'id' => 'approve',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'sticky' => [
				'test' => 'can_sticky',
				'text' => 'set_sticky',
				'id' => 'sticky',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'merge' => [
				'test' => 'can_merge',
				'text' => 'merge',
				'id' => 'merge',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
			'markread' => [
				'test' => 'can_markread',
				'text' => 'mark_read_short',
				'id' => 'markread',
				'lang' => true,
				'url' => 'javascript:void(0);',
			],
		];

		// Restore a topic, maybe even some doxing!
		if ($context['can_restore'])
		{
			$context['mod_buttons']['restore'] = [
				'text' => 'restore_topic',
				'lang' => true,
				'url' => 'javascript:void(0);',
			];
		}

		// Allow adding new buttons easily.
		call_integration_hook('integrate_message_index_quickmod_buttons');

		$context['mod_buttons'] = array_reverse($context['mod_buttons']);
	}

	/**
	 * Similar to Quick Reply, this is Quick Topic.
	 * Allows a way to start a new topic from the boards message index.
	 */
	private function quickTopic(): void
	{
		global $txt, $modSettings, $context, $options;

		// Quick topic enabled?
		if ($context['can_post_new'] && !empty($options['display_quick_reply']))
		{
			$this->prepareQuickTopic();

			checkSubmitOnce('register');

			$context['becomes_approved'] = true;
			if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
			{
				$context['becomes_approved'] = false;
			}
			else
			{
				isAllowedTo('post_new');
			}

			require_once(SUBSDIR . '/Editor.subs.php');
			// Create the editor for the QT area
			$editorOptions = [
				'id' => 'message',
				'value' => '',
				'labels' => [
					'post_button' => $txt['post'],
				],
				'height' => '200px',
				'width' => '100%',
				'smiley_container' => 'smileyBox_message',
				'bbc_container' => 'bbcBox_message',
				// We submit/switch to the full post page for the preview
				'preview_type' => 1,
				'buttons' => [
					'more' => [
						'type' => 'submit',
						'name' => 'more_options',
						'value' => $txt['post_options'],
						'options' => ''
					]
				],
			];

			// Trigger the prepare_context event for modules that have tied in to it
			$this->_events->trigger('prepare_context', ['editorOptions' => &$editorOptions, 'use_quick_reply' => !empty($options['display_quick_reply'])]);

			create_control_richedit($editorOptions);

			theme()->getTemplates()->load('GenericMessages');
		}
	}

	/**
	 * If Quick Topic is on, we need to load user information into $context so the poster sidebar renders
	 */
	private function prepareQuickTopic(): void
	{
		global $options, $context, $modSettings;

		if (empty($options['hide_poster_area']) && $options['display_quick_reply'])
		{
			if ($this->user->is_guest)
			{
				MembersList::loadGuest();
			}
			else
			{
				MembersList::load(User::$info->id);
			}

			$thisUser = MembersList::get(User::$info->id);
			$thisUser->loadContext();

			$context['thisMember'] = [
				'id' => 'new',
				'is_message_author' => true,
				'member' => $thisUser->toArray()['data']
			];
			$context['can_issue_warning'] = allowedTo('issue_warning') && featureEnabled('w') && !empty($modSettings['warning_enable']);
			$context['can_send_pm'] = allowedTo('pm_send');
		}
	}

	/**
	 * Build the board buttons for the message index page.
	 */
	private function buildBoardButtons(): void
	{
		global $context, $settings, $board;

		// Build the message index button array.
		$context['normal_buttons'] = [
			'new_topic' => [
				'test' => 'can_post_new',
				'text' => 'new_topic',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'post', 'board' => $board . '.0']),
				'active' => true],
			'notify' => [
				'test' => 'can_mark_notify',
				'text' => $this->is_marked_notify ? 'unnotify' : 'notify',
				'lang' => true, 'custom' => 'onclick="return notifyboardButton(this);"',
				'url' => getUrl('action', ['action' => 'notify', 'sa' => 'notifyboard', 'toggle' => ($this->is_marked_notify ? 'off' : 'on'), 'board' => $board . '.' . $this->sort_start, '{session_data}'])],
		];

		// They can only mark read if they are logged in, and it's enabled!
		if ($this->user->is_guest === false && $settings['show_mark_read'])
		{
			$context['normal_buttons']['markread'] = [
				'text' => 'mark_read_short',
				'lang' => true,
				'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'board', 'board' => $board . '.0', '{session_data}']),
				'custom' => 'onclick="return markboardreadButton(this);"'
			];
		}

		// Allow adding new buttons easily.
		call_integration_hook('integrate_messageindex_buttons');

		// Trigger a post load event with quick access to normal buttons
		$this->_events->trigger('post_load', ['callbacks' => &$context['normal_buttons']]);
	}

	/**
	 * Show the list of topics in this board, along with any sub-boards.
	 *
	 * @uses template_topic_listing() sub template of the MessageIndex template
	 */
	public function action_messageindex_fp(): void
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
	public function action_quickmod(): ?bool
	{
		global $board, $modSettings, $context;

		// Check the session = get or post.
		checkSession('request');

		// Cleanup
		$validator = new DataValidator();
		$validator->sanitation_rules([
			'topics' => 'intval',
			'qaction' => 'trim',
			'move_to' => 'intval',
			'redirect_topic' => 'intval',
			'redirect_expires' => 'intval',
		]);
		$validator->input_processing(['topics' => 'array']);
		$validator->validate($this->_req->post);

		$selected_topics = $validator->topics;
		$selected_qaction = $validator->qaction;

		// Let's go straight to the restore area.
		if ($selected_qaction === 'restore' && !empty($selected_topics))
		{
			redirectexit('action=removetopic;sa=restoretopic;topics=' . implode(',', $selected_topics) . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		if (isset($_SESSION['topicseen_cache']))
		{
			$_SESSION['topicseen_cache'] = [];
		}

		// Remember the last board they moved things to.
		if (!empty($validator->move_to))
		{
			$_SESSION['move_to_topic'] = [
				'move_to' => $validator->move_to,
				// And remember the last expiry period too.
				'redirect_topic' => $validator->redirect_topic,
				'redirect_expires' => $validator->redirect_expires,
			];
		}

		// This is going to be needed to send off the notifications and for updateLastMessages().
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Notification.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// Only a few possible actions.
		$actions = [];

		// Permissions on this board
		if (!empty($board))
		{
			$boards_can = [
				'make_sticky' => allowedTo('make_sticky') ? [$board] : [],
				'move_any' => allowedTo('move_any') ? [$board] : [],
				'move_own' => allowedTo('move_own') ? [$board] : [],
				'remove_any' => allowedTo('remove_any') ? [$board] : [],
				'remove_own' => allowedTo('remove_own') ? [$board] : [],
				'lock_any' => allowedTo('lock_any') ? [$board] : [],
				'lock_own' => allowedTo('lock_own') ? [$board] : [],
				'merge_any' => allowedTo('merge_any') ? [$board] : [],
				'approve_posts' => allowedTo('approve_posts') ? [$board] : [],
			];

			$start = $this->_req->getQuery('start', 'intval', 0);
			$redirect_url = 'board=' . $board . '.' . $start;
		}
		else
		{
			$boards_can = boardsAllowedTo(['make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'lock_any', 'lock_own', 'merge_any', 'approve_posts'], true, false);
			$redirect_url = $this->_req->getPost('redirect_url', 'trim|strval', $_SESSION['old_url'] ?? getUrlQuery('action', $modSettings['default_forum_action']));
		}

		// Just what actions can they do?, approve, move, remove, lock, sticky, lock, merge, mark read?
		$possibleActions = $this->setPossibleQmActions($boards_can);

		// Two methods:
		// $_REQUEST['actions'] (id_topic => action), and
		// $_REQUEST['topics'] and $this->_req->post->qaction.
		// (if action is 'move', $_REQUEST['move_to'] or $_REQUEST['move_tos'][$topic] is used.)
		if (!empty($selected_topics))
		{
			// If the action isn't valid, just quit now.
			if (empty($selected_qaction) || !in_array($selected_qaction, $possibleActions, true))
			{
				redirectexit($redirect_url);
			}

			// Merge requires all topics as one parameter and can be done at once.
			if ($selected_qaction === 'merge')
			{
				// Merge requires at least two topics.
				if (count($selected_topics) < 2)
				{
					redirectexit($redirect_url);
				}

				$controller = new Mergetopics(new EventManager());
				$controller->setUser(User::$info);
				$controller->pre_dispatch();

				return $controller->action_mergeExecute($selected_topics);
			}

			// Just convert to the other method to make it easier.
			foreach ($selected_topics as $topic)
			{
				$actions[$topic] = $selected_qaction;
			}
		}
		else
		{
			$actions = $this->_req->getRequest('actions');
		}

		// Weird... how'd you get here?
		if (empty($actions))
		{
			redirectexit($redirect_url);
		}

		// Validate each action.
		$all_actions = [];
		$action = '';
		foreach ($actions as $topic => $action)
		{
			if (in_array($action, $possibleActions, true))
			{
				$all_actions[(int) $topic] = $action;
			}
		}

		$stickyCache = [];
		$moveCache = [0 => [], 1 => []];
		$removeCache = [];
		$lockCache = [];
		$markCache = [];
		$approveCache = [];

		if (!empty($all_actions))
		{
			// Find all topics...
			$topics_info = topicsDetails(array_keys($all_actions));

			foreach ($topics_info as $row)
			{
				if (!empty($board) && ($row['id_board'] != $board || ($modSettings['postmod_active'] && !$row['approved'] && !allowedTo('approve_posts'))))
				{
					continue;
				}

				// Don't allow them to act on unapproved posts they can't see...
				if ($modSettings['postmod_active'] && !$row['approved'] && !in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))
				{
					continue;
				}

				// Goodness, this is fun.  We need to validate the action.
				if ($all_actions[$row['id_topic']] === 'sticky' && !$this->canMakeSticky($boards_can, $row))
				{
					continue;
				}

				if ($all_actions[$row['id_topic']] === 'move' && !$this->canMove($boards_can, $row))
				{
					continue;
				}

				if ($all_actions[$row['id_topic']] === 'remove' && !$this->canRemove($boards_can, $row))
				{
					continue;
				}

				if ($all_actions[$row['id_topic']] === 'lock' && !$this->canLock($boards_can, $row))
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
					case 'approve':
						$approveCache[] = $row['id_topic'];
						break;
				}
			}
		}

		$affectedBoards = empty($board) ? [] : [$board => [0, 0]];

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

		// Approve the topics...
		if (!empty($approveCache))
		{
			approveTopics($approveCache, true, true);
		}

		// And (almost) lastly, lock the topics...
		if (!empty($lockCache))
		{
			toggleTopicsLock($lockCache, true);
		}

		if (!empty($markCache))
		{
			$logged_topics = getLoggedTopics($this->user->id, $markCache);

			$markArray = [];
			foreach ($markCache as $topic)
			{
				$markArray[] = [$this->user->id, $topic, $modSettings['maxMsgID'], (int) !empty($logged_topics[$topic])];
			}

			markTopicsRead($markArray, true);
		}

		updateTopicStats();
		require_once(SUBSDIR . '/Messages.subs.php');
		updateMessageStats();
		updateSettings(['calendar_updated' => time(),]);

		if (!empty($affectedBoards))
		{
			updateLastMessages(array_keys($affectedBoards));
		}

		redirectexit($redirect_url);
	}

	/**
	 * Just what actions can they perform on this board?
	 *
	 * Checks if they can markread, sticky, move, remove, lock, or merge
	 *
	 * @param array $boards_can
	 * @return array
	 */
	public function setPossibleQmActions($boards_can): array
	{
		$possibleActions = [];

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

		if (!empty($boards_can['approve_posts']))
		{
			$possibleActions[] = 'approve';
		}

		return $possibleActions;
	}

	/**
	 * Can they sticky a topic?
	 *
	 * @param array $boards_can
	 * @param array $row
	 * @return bool
	 */
	public function canMakeSticky($boards_can, $row): bool
	{
		return in_array(0, $boards_can['make_sticky'])
			|| in_array($row['id_board'], $boards_can['make_sticky']);
	}

	/**
	 * Can they move a topic?
	 *
	 * @param array $boards_can
	 * @param array $row
	 * @return bool
	 */
	public function canMove($boards_can, $row): bool
	{
		return in_array(0, $boards_can['move_any'])
			|| in_array($row['id_board'], $boards_can['move_any'])
			|| ($row['id_member_started'] == $this->user->id
				&& (in_array(0, $boards_can['move_own']) || in_array($row['id_board'], $boards_can['move_own'])));
	}

	/**
	 * Can they remove a topic?
	 *
	 * @param array $boards_can
	 * @param array $row
	 * @return bool
	 */
	public function canRemove($boards_can, $row): bool
	{
		return in_array(0, $boards_can['remove_any'])
			|| in_array($row['id_board'], $boards_can['remove_any'])
			|| ($row['id_member_started'] == $this->user->id
				&& (in_array(0, $boards_can['remove_own']) || in_array($row['id_board'], $boards_can['remove_own'])));

	}

	/**
	 * Can they lock a topic?
	 *
	 * @param array $boards_can
	 * @param array $row
	 * @return bool
	 */
	public function canLock($boards_can, $row): bool
	{
		return in_array(0, $boards_can['lock_any'])
			|| in_array($row['id_board'], $boards_can['lock_any'])
			|| ($row['id_member_started'] == $this->user->id
				&& $row['locked'] != 1
				&& (in_array(0, $boards_can['lock_own']) || in_array($row['id_board'], $boards_can['lock_own'])));
	}
}
