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
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Database\QueryInterface;

/**
 * Recent Post Class, retrieve information about recent posts
 *
 * This is used by \ElkArte\Controller\Recent to retrieve the data
 * from the db, in particular by action_recent
 */
class Recent
{
	/** @var string Holds the boards the member is looking at */
	private $_query_this_board = '';

	/** @var array All of hte recent messages */
	private $_messages = [];

	/** @var array All of the recent posts */
	private $_board_ids = [];

	/** @var array */
	private $_posts = [];

	/** @var bool If we cache the results */
	private $_cache_results = false;

	/** @var int user id to check for recent messages */
	private $_user_id;

	/** @var QueryInterface|null */
	private $_db;

	/** @var array Parameters for the main query. */
	private $_query_parameters = [];

	/**
	 * Constructor
	 *
	 * @param int $user - ID of the user
	 * @throws \Exception
	 */
	public function __construct($user)
	{
		$this->_user_id = (int) $user;

		$this->_db = database();
	}

	/**
	 * Sets the boards the member is looking at
	 *
	 * @param int|int[] $boards - the id of the boards
	 */
	public function setBoards($boards)
	{
		$this->_query_parameters['boards'] = is_array($boards) ? $boards : array($boards);

		$this->_query_this_board .= 'b.id_board IN ({array_int:boards})';
	}

	/**
	 * Sets the lower message id to be taken in consideration
	 *
	 * @param int $msg_id - id of the earliest message to consider
	 */
	public function setEarliestMsg($msg_id)
	{
		$this->_query_this_board .= '
			AND m.id_msg >= {int:max_id_msg}';
		$this->_query_parameters['max_id_msg'] = $msg_id;
	}

	/**
	 * Sets boards the user can see
	 * Uses {query_wanna_see_board}
	 *
	 * @param int $msg_id - id of the earliest message to consider
	 * @param int $recycle - id of the recycle board
	 */
	public function setVisibleBoards($msg_id, $recycle)
	{
		$this->_query_this_board .= '{query_wanna_see_board}' . (empty($recycle) ? '' : '
			AND b.id_board != {int:recycle_board}') . '
			AND m.id_msg >= {int:max_id_msg}';

		if (!empty($recycle))
		{
			$this->_query_parameters['recycle_board'] = $msg_id;
		}

		$this->_query_parameters['max_id_msg'] = $msg_id;
	}

	/**
	 * Find the most recent messages in the forum.
	 *
	 * @param int $start - position to start the query
	 * @param int $limit - number of entries to grab from the database
	 *
	 * @return bool
	 */
	public function findRecentMessages($start, $limit = 10)
	{
		$cache = Cache\Cache::instance();
		$key = 'recent-' . $this->_user_id . '-' . md5(serialize(array_diff_key($this->_query_parameters, array('max_id_msg' => 0)))) . '-' . $start . '-' . $limit;
		$this->_messages = $cache->get($key, 120);

		if ($cache->isMiss())
		{
			$this->_findRecentMessages($start, $limit);

			if (!empty($this->_cache_results))
			{
				$cache->put($key, $this->_messages, 120);
			}
		}

		return !empty($this->_messages);
	}

	/**
	 * Actually executes the query to find the most recent messages in the forum.
	 *
	 * @param int $start - position to start the query
	 * @param int $limit - number of entries to grab
	 */
	private function _findRecentMessages($start, $limit = 10)
	{
		$done = false;
		while (!$done)
		{
			// Find the 10 most recent messages they can *view*.
			// @todo SLOW This query is really slow still, probably?
			$request = $this->_db->fetchQuery('
				SELECT 
					m.id_msg
				FROM {db_prefix}messages AS m
					JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				WHERE ' . $this->_query_this_board . '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}
				ORDER BY m.id_msg DESC
				LIMIT {int:limit} OFFSET {int:offset}',
				array_merge($this->_query_parameters, array(
					'is_approved' => 1,
					'offset' => $start,
					'limit' => $limit,
				))
			);

			// If we don't have 10 results, try again with an unoptimized version covering all rows, and cache the result.
			if (isset($this->_query_parameters['max_id_msg']) && $request->num_rows() < $limit)
			{
				$request->free_result();
				$this->_query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $this->_query_this_board);
				$this->_cache_results = true;
				unset($this->_query_parameters['max_id_msg']);
			}
			else
			{
				$done = true;
			}
		}

		$this->_messages = array();
		while (($row = $request->fetch_assoc()))
		{
			$this->_messages[] = $row['id_msg'];
		}

		$request->free_result();
	}

	/**
	 * Find the most recent messages in the forum.
	 *
	 * @param int $start - position to start the query
	 * @param array $permissions - An array of boards permissions the members have.
	 * Used to define the buttons a member can see next to a message.
	 * Format of the array is:
	 * array(
	 *    'own' => array(
	 *        'permission_name' => 'test_name'
	 *             ...
	 *       ),
	 *       'any' => array(
	 *        'permission_name' => 'test_name'
	 *           ...
	 *       )
	 *  )
	 *
	 * @return array
	 */
	public function getRecentPosts($start, $permissions)
	{
		// Provide an easy way for integration to interact with the recent display items
		call_integration_hook('integrate_recent_message_list', [$this->_messages, &$permissions]);

		$this->_getRecentPosts($start);

		// Now go through all the permissions, looking for boards they can do it on.
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// They can do it on these boards...
				$boards = boardsAllowedTo($permission);

				// If 0 is the only thing in the array, they can do it everywhere!
				if (!empty($boards) && $boards[0] === 0)
				{
					$boards = array_keys($this->_board_ids[$type]);
				}

				// Go through the boards, and look for posts they can do this on.
				foreach ($boards as $board_id)
				{
					// Hmm, they have permission, but there are no topics from that board on this page.
					if (!isset($this->_board_ids[$type][$board_id]))
					{
						continue;
					}

					// Okay, looks like they can do it for these posts.
					foreach ($this->_board_ids[$type][$board_id] as $counter)
					{
						if ($type === 'any' || $this->_posts[$counter]['poster']['id'] == $this->_user_id)
						{
							$this->_posts[$counter]['tests'][$allowed] = true;
						}
					}
				}
			}
		}

		return $this->_posts;
	}

	/**
	 * For a supplied list of message id's, loads the posting details for each.
	 *
	 * What it does:
	 *  - Intended to get all the most recent posts.
	 *  - Tracks the posts made by this user (from the supplied message list) and
	 *    loads the id's in to the 'own' or 'any' array.
	 *    Reminder The controller needs to check permissions
	 *  - Returns two arrays, one of the posts one of any/own
	 *
	 * @param int $start
	 */
	private function _getRecentPosts($start)
	{
		// Get all the most recent posts.
		$returns = array();
		$this->_db->fetchQuery('
			SELECT
				m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic, t.id_board, b.id_cat,
				b.name AS bname, c.name AS cname, t.num_replies, m.id_member, m2.id_member AS first_id_member,
				COALESCE(mem2.real_name, m2.poster_name) AS first_display_name, t.id_first_msg,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
			WHERE m.id_msg IN ({array_int:message_list})
			ORDER BY m.id_msg DESC
			LIMIT ' . count($this->_messages),
			array(
				'message_list' => $this->_messages,
			)
		)->fetch_callback(
			static function ($row) use (&$returns) {
				$returns[] = $row;
			}
		);

		[$this->_posts, $this->_board_ids] = prepareRecentPosts($returns, $start);
	}
}
