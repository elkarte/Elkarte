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
 * Recent Post Class, retrieve information about recent posts
 * This is used by Recent_Controller to retrieve the data
 * from the db, in particular by action_recent
 */
class Recent_Class
{
	private $_query_this_board = '';

	private $_start = 0;

	private $_boards = array();

	private $_messages = array();

	private $_board_ids = array();

	private $_posts = array();

	/**
	 * Parameters for the main query.
	 */
	private $_query_parameters = array();

	public function setStart($start)
	{
		$this->_start = min((int) $start, 95);
	}

	public function getStart()
	{
		return $this->_start;
	}

	public function setBoards($boards)
	{
		if (is_array($boards))
			$this->_query_parameters['boards'] = $boards;
		else
			$this->_query_parameters['boards'] = array($boards);

		$this->_query_this_board .= 'b.id_board IN ({array_int:boards})';
	}

	public function setMaxMsgId($msg_id)
	{
		$this->_query_this_board .= '
						AND m.id_msg >= {int:max_id_msg}';
		$this->_query_parameters['max_id_msg'] = $msg_id;
	}

	public function setVisibleBoards($msg_id, $recycle)
	{
		$this->_query_this_board .= '{query_wanna_see_board}' . (!empty($recycle) ? '
						AND b.id_board != {int:recycle_board}' : '') . '
						AND m.id_msg >= {int:max_id_msg}';

		if (!empty($recycle))
			$this->_query_parameters['recycle_board'] = $msg_id;

		$this->_query_parameters['max_id_msg'] = $msg_id;
	}

	/**
	 * Find the most recent messages in the forum.
	 * Respects 
	 *
	 * @param int $limit - number of entries to grab from the database
	 */
	public function findRecentMessages($limit = 10)
	{
		global $modSettings, $user_info;

		$key = 'recent-' . $user_info['id'] . '-' . md5(serialize(array_diff_key($this->_query_parameters, array('max_id_msg' => 0)))) . '-' . $this->_start . '-' . $limit;
		if (empty($modSettings['cache_enable']) || ($this->_messages = cache_get_data($key, 120)) === null)
		{
			list ($this->_messages, $cache_results) = findRecentMessages($this->_query_parameters, $this->_query_this_board, $this->_start, $limit);

			if (!empty($cache_results))
					cache_put_data($key, $this->_messages, 120);
		}

		return !empty($this->_messages);
	}

	public function getRecentPosts($permissions)
	{
		global $user_info;

		list ($this->_posts, $this->_board_ids) = getRecentPosts($this->_messages, $this->_start);

		// Now go through all the permissions, looking for boards they can do it on.
		foreach ($permissions as $type => $list)
		{
			foreach ($list as $permission => $allowed)
			{
				// They can do it on these boards...
				$boards = boardsAllowedTo($permission);

				// If 0 is the only thing in the array, they can do it everywhere!
				if (!empty($boards) && $boards[0] == 0)
					$boards = array_keys($this->_board_ids[$type]);

				// Go through the boards, and look for posts they can do this on.
				foreach ($boards as $board_id)
				{
					// Hmm, they have permission, but there are no topics from that board on this page.
					if (!isset($this->_board_ids[$type][$board_id]))
						continue;

					// Okay, looks like they can do it for these posts.
					foreach ($this->_board_ids[$type][$board_id] as $counter)
						if ($type == 'any' || $this->_posts[$counter]['poster']['id'] == $user_info['id'])
							$this->_posts[$counter]['tests'][$allowed] = true;
				}
			}
		}

		return $this->_posts;
	}
}