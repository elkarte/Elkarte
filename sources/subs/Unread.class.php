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
class Unread_Class
{
	const UNREAD = 0;
	const UNREADREPLIES = 1;

	private $_have_temp_table = false;

	private $_ascending = false;

	private $_sort_query = '';

	private $_num_topics = 0;

	private $_min_message = 0;

	private $_action = Unread_Class::UNREAD;

	private $_earliest_msg = 0;

	private $_showing_all_topics = false;

	private $_preview_bodies = 0;

	/**
	 * Parameters for the main query.
	 */
	private $_query_parameters = array();

	public function __construct($showing_all_topics = false)
	{
		$this->_showing_all_topics = $showing_all_topics;
	}

	public function setBoards($boards)
	{
		if (is_array($boards))
			$this->_query_parameters['boards'] = $boards;
		else
			$this->_query_parameters['boards'] = array($boards);
	}

	public function setAction($action)
	{
		if (in_array($action, array(Unread_Class::UNREAD, Unread_Class::UNREADREPLIES)))
			$this->_action = $action;
	}

	public function setEarliestMsg($earliest_msg)
	{
		$this->_earliest_msg = (int) $earliest_msg;
	}

	public function setSorting($query, $asc)
	{
		$this->_sort_query = $query;
		$this->_ascending = $asc;
	}

	public function sortAsc()
	{
		return $this->_ascending;
	}

	public function createTempTable()
	{
		if ($this->_action === Unread_Class::UNREAD)
			$this->_have_temp_table = recent_log_topics_unread_tempTable($this->_query_parameters, $this->_earliest_msg);
		else
		{
			$board = !empty($this->_query_parameters['boards'][0]) ? $this->_query_parameters['boards'][0] : 0;

			$this->_have_temp_table = unreadreplies_tempTable($board, $this->_sort_query);
		}
	}

	public function bodyPreview($chars = 0)
	{
		if ($chars === true)
			$this->_preview_bodies = 'all';
		else
			$this->_preview_bodies = (int) $chars;
	}

	public function hasTempTable()
	{
		return $this->_have_temp_table;
	}

	public function numUnreads($first_login = false, $id_msg_last_visit = 0)
	{
		if ($this->_action === Unread_Class::UNREAD)
		{
			$this->_countRecentTopics($first_login, $id_msg_last_visit);
		}
		else
		{
			$this->_countUnreadReplies();
		}

		return $this->_num_topics;
	}

	public function getUnreads($type, $start, $limit, $show_avatars)
	{
		if ($this->_action === Unread_Class::UNREAD)
			return getUnreadTopics($this->_query_parameters, $this->_preview_bodies, $type, $this->_have_temp_table, $this->_min_message, $this->_sort_query, $this->_ascending, $start, $limit, $show_avatars);
		else
			return getUnreadReplies($this->_query_parameters, $this->_preview_bodies, $this->_have_temp_table, $this->_min_message, $this->_sort_query, $this->_ascending, $start, $limit, $show_avatars);
	}

	private function _countRecentTopics($first_login, $id_msg_last_visit)
	{
		list ($this->_num_topics, $this->_min_message) = countRecentTopics($this->_query_parameters, $this->_showing_all_topics, $this->_have_temp_table, $first_login, $this->_earliest_msg, $id_msg_last_visit);
	}

	private function _countUnreadReplies()
	{
		list ($this->_num_topics, $this->_min_message) = countUnreadReplies($this->_query_parameters, $this->_have_temp_table);
	}
}