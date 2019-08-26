<?php

/**
 * This file contains a class to collect the data needed to
 * show a list of boards for the board index and the message index.
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

namespace ElkArte;

use \ElkArte\User;

/**
 * This class fetches all the stuff needed to build a list of boards
 */
class BoardsList
{
	/**
	 * All the options
	 * @var mixed[]
	 */
	private $_options = array();

	/**
	 * Some data regarding the current user
	 * @var mixed[]
	 */
	private $_user = array();

	/**
	 * Holds the info about the latest post of the series
	 * @var mixed[]
	 */
	private $_latest_post = array();

	/**
	 * Remembers boards to easily scan the array to add moderators later
	 * @var int[]
	 */
	private $_boards = array();

	/**
	 * An array containing all the data of the categories and boards requested
	 * @var mixed[]
	 */
	private $_categories = array();

	/**
	 * The category/board that is being processed "now"
	 * @var mixed[]
	 */
	private $_current_boards = array();

	/**
	 * The url where to find images
	 * @var string
	 */
	private $_images_url = '';

	/**
	 * A string with session data to be user in urls
	 * @var string
	 */
	private $_session_url = '';

	/**
	 * Cut the subject at this number of chars
	 * @var int
	 */
	private $_subject_length = 24;

	/**
	 * The id of the recycle board (0 for none or not enabled)
	 * @var int
	 */
	private $_recycle_board = 0;

	/**
	 * The database!
	 * @var object
	 */
	private $_db = null;

	/**
	 * Initialize the class
	 *
	 * @param mixed[] $options - Available options and corresponding defaults are:
	 *       'include_categories' => false
	 *       'countChildPosts' => false
	 *       'base_level' => false
	 *       'parent_id' => 0
	 *       'set_latest_post' => false
	 *       'get_moderators' => true
	 */
	public function __construct($options)
	{
		global $settings, $context, $modSettings;

		$this->_options = array_merge(array(
			'include_categories' => false,
			'countChildPosts' => false,
			'base_level' => 0,
			'parent_id' => 0,
			'set_latest_post' => false,
			'get_moderators' => true,
		), $options);

		$this->_options['avatars_on_indexes'] = !empty($settings['avatars_on_indexes']) && $settings['avatars_on_indexes'] !== 2;

		$this->_images_url = $settings['images_url'] . '/' . $context['theme_variant_url'];
		$this->_session_url = $context['session_var'] . '=' . $context['session_id'];

		$this->_subject_length = $modSettings['subject_length'];

		$this->_user = User::$info;
		$this->_user['mod_cache_ap'] = !empty($this->_user->mod_cache['ap']) ? $this->_user->mod_cache['ap'] : array();

		$this->_db = database();

		// Start with an empty array.
		if ($this->_options['include_categories'])
			$this->_categories = array();
		else
			$this->_current_boards = array();

		// For performance, track the latest post while going through the boards.
		if (!empty($this->_options['set_latest_post']))
			$this->_latest_post = array('timestamp' => 0);

		if (!empty($modSettings['recycle_enable']))
			$this->_recycle_board = $modSettings['recycle_board'];
	}

	/**
	 * Fetches a list of boards and (optional) categories including
	 * statistical information, sub-boards and moderators.
	 *  - Used by both the board index (main data) and the message index (child
	 * boards).
	 *  - Depending on the include_categories setting returns an associative
	 * array with categories->boards->child_boards or an associative array
	 * with boards->child_boards.
	 *
	 * @return array
	 */
	public function getBoards()
	{
		global $txt, $modSettings;

		// Find all boards and categories, as well as related information.
		$request = $this->_db->fetchQuery('
			SELECT' . ($this->_options['include_categories'] ? '
				c.id_cat, c.name AS cat_name, c.cat_order,' : '') . '
				b.id_board, b.name AS board_name, b.description, b.board_order,
				CASE WHEN b.redirect != {string:blank_string} THEN 1 ELSE 0 END AS is_redirect,
				b.num_posts, b.num_topics, b.unapproved_posts, b.unapproved_topics, b.id_parent,
				COALESCE(m.poster_time, 0) AS poster_time, COALESCE(mem.member_name, m.poster_name) AS poster_name,
				m.subject, m.id_topic, COALESCE(mem.real_name, m.poster_name) AS real_name,
				' . ($this->_user['is_guest'] ? ' 1 AS is_read, 0 AS new_from,' : '
				(CASE WHEN COALESCE(lb.id_msg, 0) >= b.id_msg_updated THEN 1 ELSE 0 END) AS is_read, COALESCE(lb.id_msg, -1) + 1 AS new_from,' . ($this->_options['include_categories'] ? '
				c.can_collapse, COALESCE(cc.id_member, 0) AS is_collapsed,' : '')) . '
				COALESCE(mem.id_member, 0) AS id_member, mem.avatar, m.id_msg' . ($this->_options['avatars_on_indexes'] ? ',
				COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, mem.email_address' : '') . '
			FROM {db_prefix}boards AS b' . ($this->_options['include_categories'] ? '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' : '') . '
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($this->_user['is_guest'] ? '' : '
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})' . ($this->_options['include_categories'] ? '
				LEFT JOIN {db_prefix}collapsed_categories AS cc ON (cc.id_cat = c.id_cat AND cc.id_member = {int:current_member})' : '')) . ($this->_options['avatars_on_indexes'] ? '
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member AND a.id_member != 0)' : '') . '
			WHERE {query_see_board}' . (empty($this->_options['countChildPosts']) ? (empty($this->_options['base_level']) ? '' : '
				AND b.child_level >= {int:child_level}') : '
				AND b.child_level BETWEEN {int:child_level} AND {int:upper_level}'),
			array(
				'current_member' => $this->_user['id'],
				'child_level' => $this->_options['base_level'],
				'upper_level' => $this->_options['base_level'] + 1,
				'blank_string' => '',
			)
		);

		$result_boards = $request->fetch_all();

		usort($result_boards, function ($a, $b) {
			return $a['board_order'] <=> $b['board_order'];
		});

		$bbc_parser = \BBC\ParserWrapper::instance();

		// Run through the categories and boards (or only boards)....
		foreach ($result_boards as $row_board)
		{
			// Perhaps we are ignoring this board?
			$ignoreThisBoard = in_array($row_board['id_board'], $this->_user['ignoreboards']);
			$row_board['is_read'] = !empty($row_board['is_read']) || $ignoreThisBoard ? '1' : '0';
			// Not a child.
			$isChild = false;

			if ($this->_options['include_categories'])
			{
				// Haven't set this category yet.
				if (empty($this->_categories[$row_board['id_cat']]))
				{
					$cat_name = $row_board['cat_name'];
					$this->_categories[$row_board['id_cat']] = array(
						'id' => $row_board['id_cat'],
						'name' => $row_board['cat_name'],
						'order' => $row_board['cat_order'],
						'is_collapsed' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1 && $row_board['is_collapsed'] > 0,
						'can_collapse' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1,
						'collapse_href' => isset($row_board['can_collapse']) ? getUrl('action', ['action' => 'collapse', 'c' => $row_board['id_cat'], 'sa' => $row_board['is_collapsed'] > 0 ? 'expand' : 'collapse', '{session_data}']) . '#c' . $row_board['id_cat'] : '',
						'collapse_image' => isset($row_board['can_collapse']) ? '<img src="' . $this->_images_url . ($row_board['is_collapsed'] > 0 ? 'expand.png" alt="+"' : 'collapse.png" alt="-"') . ' />' : '',
						'href' => getUrl('action', $modSettings['default_forum_action']) . '#c' . $row_board['id_cat'],
						'boards' => array(),
						'new' => false
					);
					$this->_categories[$row_board['id_cat']]['link'] = '<a id="c' . $row_board['id_cat'] . '"></a>' . (!$this->_user['is_guest']
							? '<a href="' . getUrl('action', ['action' => 'unread', 'c' => $row_board['id_cat']]) . '" title="' . sprintf($txt['new_posts_in_category'], strip_tags($row_board['cat_name'])) . '">' . $cat_name . '</a>'
							: $cat_name);
				}

				// If this board has new posts in it (and isn't the recycle bin!) then the category is new.
				if ($this->_recycle_board != $row_board['id_board'])
					$this->_categories[$row_board['id_cat']]['new'] |= empty($row_board['is_read']) && $row_board['poster_name'] != '';

				// Avoid showing category unread link where it only has redirection boards.
				$this->_categories[$row_board['id_cat']]['show_unread'] = !empty($this->_categories[$row_board['id_cat']]['show_unread']) ? 1 : !$row_board['is_redirect'];

				// Collapsed category - don't do any of this.
				if ($this->_categories[$row_board['id_cat']]['is_collapsed'])
					continue;

				// Let's save some typing.  Climbing the array might be slower, anyhow.
				$this->_current_boards = &$this->_categories[$row_board['id_cat']]['boards'];
			}

			// This is a parent board.
			if ($row_board['id_parent'] == $this->_options['parent_id'])
			{
				// Is this a new board, or just another moderator?
				if (!isset($this->_current_boards[$row_board['id_board']]))
				{
					$href = getUrl('board', ['board' => $row_board['id_board'], 'start' => '0', 'name' => $row_board['board_name']]);
					$this->_current_boards[$row_board['id_board']] = array(
						'new' => empty($row_board['is_read']),
						'id' => $row_board['id_board'],
						'name' => $row_board['board_name'],
						'description' => $bbc_parser->parseBoard($row_board['description']),
						'raw_description' => $row_board['description'],
						'moderators' => array(),
						'link_moderators' => array(),
						'children' => array(),
						'link_children' => array(),
						'children_new' => false,
						'topics' => $row_board['num_topics'],
						'posts' => $row_board['num_posts'],
						'is_redirect' => $row_board['is_redirect'],
						'unapproved_topics' => $row_board['unapproved_topics'],
						'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
						'can_approve_posts' => $this->_user['mod_cache_ap'] == array(0) || in_array($row_board['id_board'], $this->_user['mod_cache_ap']),
						'href' => $href,
						'link' => '<a href="' . $href . '">' . $row_board['board_name'] . '</a>'
					);
				}
				$this->_boards[$row_board['id_board']] = $this->_options['include_categories'] ? $row_board['id_cat'] : 0;
			}
			// Found a sub-board.... make sure we've found its parent and the child hasn't been set already.
			elseif (isset($this->_current_boards[$row_board['id_parent']]['children']) && !isset($this->_current_boards[$row_board['id_parent']]['children'][$row_board['id_board']]))
			{
				// A valid child!
				$isChild = true;

				$href = getUrl('board', ['board' => $row_board['id_board'], 'start' => '0', 'name' => $row_board['board_name']]);
				$this->_current_boards[$row_board['id_parent']]['children'][$row_board['id_board']] = array(
					'id' => $row_board['id_board'],
					'name' => $row_board['board_name'],
					'description' => $bbc_parser->parseBoard($row_board['description']),
					'raw_description' => $row_board['description'],
					'new' => empty($row_board['is_read']) && $row_board['poster_name'] != '',
					'topics' => $row_board['num_topics'],
					'posts' => $row_board['num_posts'],
					'is_redirect' => $row_board['is_redirect'],
					'unapproved_topics' => $row_board['unapproved_topics'],
					'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
					'can_approve_posts' => $this->_user['mod_cache_ap'] == array(0) || in_array($row_board['id_board'], $this->_user['mod_cache_ap']),
					'href' => $href,
					'link' => '<a href="' . $href . '">' . $row_board['board_name'] . '</a>'
				);

				// Counting sub-board posts is... slow :/.
				if (!empty($this->_options['countChildPosts']) && !$row_board['is_redirect'])
				{
					$this->_current_boards[$row_board['id_parent']]['posts'] += $row_board['num_posts'];
					$this->_current_boards[$row_board['id_parent']]['topics'] += $row_board['num_topics'];
				}

				// Does this board contain new boards?
				$this->_current_boards[$row_board['id_parent']]['children_new'] |= empty($row_board['is_read']);

				// This is easier to use in many cases for the theme....
				$this->_current_boards[$row_board['id_parent']]['link_children'][] = &$this->_current_boards[$row_board['id_parent']]['children'][$row_board['id_board']]['link'];
			}
			// Child of a child... just add it on...
			elseif (!empty($this->_options['countChildPosts']))
			{
				// @todo why this is not initialized outside the loop?
				if (!isset($parent_map))
					$parent_map = array();

				if (!isset($parent_map[$row_board['id_parent']]))
					foreach ($this->_current_boards as $id => $board)
					{
						if (!isset($board['children'][$row_board['id_parent']]))
							continue;

						$parent_map[$row_board['id_parent']] = array(&$this->_current_boards[$id], &$this->_current_boards[$id]['children'][$row_board['id_parent']]);
						$parent_map[$row_board['id_board']] = array(&$this->_current_boards[$id], &$this->_current_boards[$id]['children'][$row_board['id_parent']]);

						break;
					}

				if (isset($parent_map[$row_board['id_parent']]) && !$row_board['is_redirect'])
				{
					$parent_map[$row_board['id_parent']][0]['posts'] += $row_board['num_posts'];
					$parent_map[$row_board['id_parent']][0]['topics'] += $row_board['num_topics'];
					$parent_map[$row_board['id_parent']][1]['posts'] += $row_board['num_posts'];
					$parent_map[$row_board['id_parent']][1]['topics'] += $row_board['num_topics'];

					continue;
				}

				continue;
			}
			// Found a child of a child - skip.
			else
				continue;

			// Prepare the subject, and make sure it's not too long.
			$row_board['subject'] = censor($row_board['subject']);
			$row_board['short_subject'] = Util::shorten_text($row_board['subject'], $this->_subject_length);
			$poster_href = getUrl('profile', ['action' => 'profile', 'u' => $row_board['id_member'], 'name' => $row_board['real_name']]);
			$this_last_post = array(
				'id' => $row_board['id_msg'],
				'time' => $row_board['poster_time'] > 0 ? standardTime($row_board['poster_time']) : $txt['not_applicable'],
				'html_time' => $row_board['poster_time'] > 0 ? htmlTime($row_board['poster_time']) : $txt['not_applicable'],
				'timestamp' => forum_time(true, $row_board['poster_time']),
				'subject' => $row_board['short_subject'],
				'member' => array(
					'id' => $row_board['id_member'],
					'username' => $row_board['poster_name'] != '' ? $row_board['poster_name'] : $txt['not_applicable'],
					'name' => $row_board['real_name'],
					'href' => $row_board['poster_name'] != '' && !empty($row_board['id_member']) ? $poster_href : '',
					'link' => $row_board['poster_name'] != '' ? (!empty($row_board['id_member']) ? '<a href="' . $poster_href . '">' . $row_board['real_name'] . '</a>' : $row_board['real_name']) : $txt['not_applicable'],
				),
				'start' => 'msg' . $row_board['new_from'],
				'topic' => $row_board['id_topic']
			);

			if ($this->_options['avatars_on_indexes'])
				$this_last_post['member']['avatar'] = determineAvatar($row_board);

			// Provide the href and link.
			if ($row_board['subject'] != '')
			{
				$this_last_post['href'] = getUrl('topic', ['topic' => $row_board['id_topic'], 'start' => 'msg' . ($this->_user['is_guest'] ? $row_board['id_msg'] : $row_board['new_from']), 'subject' => $row_board['subject'], 0 => empty($row_board['is_read']) ? 'boardseen' : '']) . '#new';
				$this_last_post['link'] = '<a href="' . $this_last_post['href'] . '" title="' . Util::htmlspecialchars($row_board['subject']) . '">' . $row_board['short_subject'] . '</a>';
				/* The board's and children's 'last_post's have:
				time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
				link, href, subject, start (where they should go for the first unread post.),
				and member. (which has id, name, link, href, username in it.) */
				$this_last_post['last_post_message'] = sprintf($txt['last_post_message'], $this_last_post['member']['link'], $this_last_post['link'], $this_last_post['html_time']);
			}
			else
			{
				$this_last_post['href'] = '';
				$this_last_post['link'] = $txt['not_applicable'];
				$this_last_post['last_post_message'] = '';
			}

			// Set the last post in the parent board.
			if ($row_board['id_parent'] == $this->_options['parent_id'] || ($isChild && !empty($row_board['poster_time']) && $this->_current_boards[$row_board['id_parent']]['last_post']['timestamp'] < forum_time(true, $row_board['poster_time'])))
				$this->_current_boards[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'] = $this_last_post;
			// Just in the child...?
			if ($isChild)
			{
				$this->_current_boards[$row_board['id_parent']]['children'][$row_board['id_board']]['last_post'] = $this_last_post;

				// If there are no posts in this board, it really can't be new...
				$this->_current_boards[$row_board['id_parent']]['children'][$row_board['id_board']]['new'] &= $row_board['poster_name'] != '';
			}
			// No last post for this board?  It's not new then, is it..?
			elseif ($row_board['poster_name'] == '')
				$this->_current_boards[$row_board['id_board']]['new'] = false;

			// Determine a global most recent topic.
			if ($this->_options['set_latest_post'] && !empty($row_board['poster_time']) && $row_board['poster_time'] > $this->_latest_post['timestamp'] && !$ignoreThisBoard)
				$this->_latest_post = &$this->_current_boards[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'];
		}

		if ($this->_options['get_moderators'] && !empty($this->_boards))
			$this->_getBoardModerators();

		usort($this->_categories, function ($a, $b) {
			return $a['order'] <=> $b['order'];
		});

		return $this->_options['include_categories'] ? $this->_categories : $this->_current_boards;
	}

	/**
	 * Returns the array containing the "latest post" information
	 *
	 * @return array
	 */
	public function getLatestPost()
	{
		if (empty($this->_latest_post) || empty($this->_latest_post['link']))
			return array();
		else
			return $this->_latest_post;
	}

	/**
	 * Fetches and adds to the results the board moderators for the current boards
	 */
	private function _getBoardModerators()
	{
		global $txt;

		$boards = array_keys($this->_boards);
		$mod_cached = array();

		if (!Cache\Cache::instance()->getVar($mod_cached, 'localmods_' . md5(implode(',', $boards)), 3600))
		{
			$request = $this->_db->fetchQuery('
				SELECT mods.id_board, COALESCE(mods_mem.id_member, 0) AS id_moderator, mods_mem.real_name AS mod_real_name
				FROM {db_prefix}moderators AS mods
					LEFT JOIN {db_prefix}members AS mods_mem ON (mods_mem.id_member = mods.id_member)
				WHERE mods.id_board IN ({array_int:id_boards})',
				array(
					'id_boards' => $boards,
				)
			);
			$mod_cached = $request->fetch_all();

			Cache\Cache::instance()->put('localmods_' . md5(implode(',', $boards)), $mod_cached, 3600);
		}

		foreach ($mod_cached as $row_mods)
		{
			if ($this->_options['include_categories'])
				$this->_current_boards = &$this->_categories[$this->_boards[$row_mods['id_board']]]['boards'];

			$href = getUrl('profile', ['action' => 'profile', 'u' => $row_mods['id_moderator'], 'name' => $row_mods['mod_real_name']]);
			$this->_current_boards[$row_mods['id_board']]['moderators'][$row_mods['id_moderator']] = array(
				'id' => $row_mods['id_moderator'],
				'name' => $row_mods['mod_real_name'],
				'href' => $href,
				'link' => '<a href="' . $href . '" title="' . $txt['board_moderator'] . '">' . $row_mods['mod_real_name'] . '</a>'
			);
			$this->_current_boards[$row_mods['id_board']]['link_moderators'][] = '<a href="' . $href . '" title="' . $txt['board_moderator'] . '">' . $row_mods['mod_real_name'] . '</a>';
		}
	}
}
