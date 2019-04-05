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

namespace ElkArte;

/**
 * Unread posts and replies Controller
 */
class Unread
{
	const UNREAD = 0;
	const UNREADREPLIES = 1;

	/** @var bool */
	private $_have_temp_table = false;
	/** @var bool */
	private $_ascending = false;
	/** @var string */
	private $_sort_query = '';
	/** @var int */
	private $_num_topics = 0;
	/** @var int */
	private $_min_message = 0;
	/** @var int */
	private $_action = self::UNREAD;
	/** @var int */
	private $_earliest_msg = 0;
	/** @var bool */
	private $_showing_all_topics = false;
	/** @var int */
	private $_user_id = 0;
	/** @var bool */
	private $_post_mod = false;
	/** @var bool */
	private $_unwatch = false;
	/** @var \ElkArte\Database\QueryInterface|null */
	private $_db = null;
	/** @var int|string */
	private $_preview_bodies = 0;

	/**
	 * Parameters for the main query.
	 */
	private $_query_parameters = array();

	/**
	 * Constructor
	 *
	 * @param int $user - ID of the user
	 * @param bool|int $post_mod - if post moderation is active or not
	 * @param bool|int $unwatch - if unwatch topics is active or not
	 * @param bool|int $showing_all_topics - Is the user looking at all the unread
	 *             replies, or the recent topics?
	 */
	public function __construct($user, $post_mod, $unwatch, $showing_all_topics = false)
	{
		$this->_user_id = (int) $user;
		$this->_post_mod = (bool) $post_mod;
		$this->_unwatch = (bool) $unwatch;
		$this->_showing_all_topics = (bool) $showing_all_topics;

		$this->_db = database();
	}

	/**
	 * Sets the boards the member is looking at
	 *
	 * @param int|int[] $boards - the id of the boards
	 */
	public function setBoards($boards)
	{
		if (is_array($boards))
			$this->_query_parameters['boards'] = $boards;
		else
			$this->_query_parameters['boards'] = array($boards);
	}

	/**
	 * The action the user is performing
	 *
	 * @param int $action - Unread::UNREAD, Unread::UNREADREPLIES
	 */
	public function setAction($action)
	{
		if (in_array($action, array(self::UNREAD, self::UNREADREPLIES)))
			$this->_action = $action;
	}

	/**
	 * Sets the lower message id to be taken in consideration
	 *
	 * @param int $msg_id - id of the earliest message to consider
	 */
	public function setEarliestMsg($msg_id)
	{
		$this->_earliest_msg = (int) $msg_id;
	}

	/**
	 * Sets the sorting query and the direction
	 *
	 * @param string $query - The query to be used in the ORDER clause
	 * @param bool|int $asc - If the sorting is ascending or not
	 */
	public function setSorting($query, $asc)
	{
		$this->_sort_query = $query;
		$this->_ascending = $asc;
	}

	/**
	 * Return the sorting direction
	 *
	 * @return boolean
	 */
	public function isSortAsc()
	{
		return $this->_ascending;
	}

	/**
	 * Tries to create a temporary table according to the type of action the
	 * class is performing
	 */
	public function createTempTable()
	{
		if ($this->_action === self::UNREAD)
			$this->_recent_log_topics_unread_tempTable();
		else
		{
			$board = !empty($this->_query_parameters['boards'][0]) && count($this->_query_parameters['boards']) === 1 ? $this->_query_parameters['boards'][0] : 0;

			$this->_unreadreplies_tempTable($board);
		}
	}

	/**
	 * Sets if the data returned by the class will include a shorted version
	 * of the body of the last message.
	 *
	 * @param bool|int $chars - The number of chars to retrieve.
	 *                 If true it will return the entire body,
	 *                 if 0 no preview will be generated.
	 */
	public function bodyPreview($chars)
	{
		if ($chars === true)
			$this->_preview_bodies = 'all';
		else
			$this->_preview_bodies = (int) $chars;
	}

	/**
	 * If a temporary table has been created
	 */
	public function hasTempTable()
	{
		return $this->_have_temp_table;
	}

	/**
	 * Counts the number of unread topics or messages
	 *
	 * @param bool $first_login - If this is the first login of the user
	 * @param int $id_msg_last_visit - highest id_msg found during the last visit
	 *
	 * @return int
	 */
	public function numUnreads($first_login = false, $id_msg_last_visit = 0)
	{
		if ($this->_action === self::UNREAD)
			$this->_countRecentTopics($first_login, $id_msg_last_visit);
		else
			$this->_countUnreadReplies();

		return $this->_num_topics;
	}

	/**
	 * Retrieves unread topics or messages
	 *
	 * @param string $join - kind of "JOIN" to execute. If 'topic' JOINs boards on
	 *                       the topics table, otherwise ('message') the JOIN is on
	 *                       the messages table
	 * @param int $start - position to start the query
	 * @param int $limit - number of entries to grab
	 * @param bool $include_avatars - if avatars should be retrieved as well
	 * @return mixed[] - see \ElkArte\TopicUtil::prepareContext
	 */
	public function getUnreads($join, $start, $limit, $include_avatars)
	{
		if ($this->_action === self::UNREAD)
			return $this->_getUnreadTopics($join, $start, $limit, $include_avatars);
		else
			return $this->_getUnreadReplies($start, $limit, $include_avatars);
	}

	/**
	 * Retrieves unread topics, used in *all* unread replies with temp table and
	 * new posts since last visit
	 *
	 * @param string $join - kind of "JOIN" to execute. If 'topic' JOINs boards on
	 *                       the topics table, otherwise ('message') the JOIN is on
	 *                       the messages table
	 * @param int $start - position to start the query
	 * @param int $limit - number of entries to grab
	 * @param bool|int $include_avatars - if avatars should be retrieved as well
	 * @return mixed[] - see \ElkArte\TopicUtil::prepareContext
	 */
	private function _getUnreadTopics($join, $start, $limit, $include_avatars = false)
	{
		if ($this->_preview_bodies == 'all')
			$body_query = 'ml.body AS last_body, ms.body AS first_body,';
		else
		{
			// If empty, no preview at all
			if (empty($this->_preview_bodies))
				$body_query = '';
			// Default: a SUBSTRING
			else
				$body_query = 'SUBSTRING(ml.body, 1, ' . ($this->_preview_bodies + 256) . ') AS last_body, SUBSTRING(ms.body, 1, ' . ($this->_preview_bodies + 256) . ') AS first_body,';
		}

		if (!empty($include_avatars))
		{
			if ($include_avatars === 1 || $include_avatars === 3)
			{
				$custom_selects = array('meml.avatar', 'COALESCE(a.id_attach, 0) AS id_attach', 'a.filename', 'a.attachment_type', 'meml.email_address');
				$custom_joins = array('LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)');
			}
			else
			{
				$custom_selects = array();
				$custom_joins = array();
			}

			if ($include_avatars === 2 || $include_avatars === 3)
			{
				$custom_selects = array_merge($custom_selects, array('memf.avatar AS avatar_first', 'COALESCE(af.id_attach, 0) AS id_attach_first', 'af.filename AS filename_first', 'af.attachment_type AS attachment_type_first', 'memf.email_address AS email_address_first'));
				$custom_joins = array_merge($custom_joins, array('LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mf.id_member AND af.id_member != 0)'));
			}
		}

		$request = $this->_db->query('substring', '
			SELECT
				ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.poster_name AS first_member_name,
				ms.id_topic, t.id_board, b.name AS bname, t.num_replies, t.num_views, t.num_likes, t.approved,
				ms.id_member AS first_id_member, ml.id_member AS last_id_member, ml.poster_name AS last_member_name,
				ml.poster_time AS last_poster_time, COALESCE(mems.real_name, ms.poster_name) AS first_display_name,
				COALESCE(meml.real_name, ml.poster_name) AS last_display_name, ml.subject AS last_subject,
				ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
				COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from,
				' . $body_query . '
				' . (!empty($custom_selects) ? implode(',', $custom_selects) . ', ' : '') . '
				ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' . ($join == 'topics' ? '
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' : '
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)') . '
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . ($this->_have_temp_table ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . (!empty($custom_joins) ? implode("\n\t\t\t\t", $custom_joins) : '') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_board IN ({array_int:boards})
				AND t.id_last_msg >= {int:min_message}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < ml.id_msg' .
				($this->_post_mod ? ' AND ms.approved = {int:is_approved}' : '') .
				($this->_unwatch ? ' AND COALESCE(lt.unwatched, 0) != 1' : '') . '
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($this->_query_parameters, array(
				'current_member' => $this->_user_id,
				'min_message' => $this->_min_message,
				'is_approved' => 1,
				'order' => $this->_sort_query . ($this->_ascending ? '' : ' DESC'),
				'offset' => $start,
				'limit' => $limit,
			))
		);
		$topics = array();
		while ($row = $this->_db->fetch_assoc($request))
			$topics[] = $row;
		$this->_db->free_result($request);

		return TopicUtil::prepareContext($topics, true, ((int) $this->_preview_bodies) + 128);
	}

	/**
	 * Counts unread replies
	 */
	private function _countUnreadReplies()
	{
		if (!empty($this->_have_temp_table))
		{
			$request = $this->_db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}topics_posted_in AS pi
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
				WHERE pi.id_board IN ({array_int:boards})
					AND COALESCE(lt.id_msg, pi.id_msg) < pi.id_last_msg',
				array_merge($this->_query_parameters, array(
				))
			);
			list ($this->_num_topics) = $this->_db->fetch_row($request);
			$this->_db->free_result($request);
			$this->_min_message = 0;
		}
		else
		{
			$request = $this->_db->query('unread_fetch_topic_count', '
				SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.id_board IN ({array_int:boards})
					AND m.id_member = {int:current_member}
					AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . ($this->_post_mod ? '
					AND t.approved = {int:is_approved}' : '') . ($this->_unwatch ? '
					AND COALESCE(lt.unwatched, 0) != 1' : ''),
				array_merge($this->_query_parameters, array(
					'current_member' => $this->_user_id,
					'is_approved' => 1,
				))
			);
			list ($this->_num_topics, $this->_min_message) = $this->_db->fetch_row($request);
			$this->_db->free_result($request);
		}
	}

	/**
	 * Counts unread topics, used in *all* unread replies with temp table and
	 * new posts since last visit
	 *
	 * @param bool $is_first_login - if the member has already logged in at least
	 *             once, then there is an $id_msg_last_visit
	 * @param int $id_msg_last_visit - highest id_msg found during the last visit
	 */
	private function _countRecentTopics($is_first_login, $id_msg_last_visit = 0)
	{
		$request = $this->_db->query('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t' . (!empty($this->_have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_board IN ({array_int:boards})' . ($this->_showing_all_topics && !empty($this->_earliest_msg) ? '
				AND t.id_last_msg > {int:earliest_msg}' : (!$this->_showing_all_topics && $is_first_login ? '
				AND t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' .
				($this->_post_mod ? ' AND t.approved = {int:is_approved}' : '') .
				($this->_unwatch ? ' AND COALESCE(lt.unwatched, 0) != 1' : ''),
			array_merge($this->_query_parameters, array(
				'current_member' => $this->_user_id,
				'earliest_msg' => $this->_earliest_msg,
				'id_msg_last_visit' => $id_msg_last_visit,
				'is_approved' => 1,
			))
		);
		list ($this->_num_topics, $this->_min_message) = $this->_db->fetch_row($request);
		$this->_db->free_result($request);
	}

	/**
	 * Retrieves unread replies since last visit
	 *
	 * @param int $start - position to start the query
	 * @param int $limit - number of entries to grab
	 * @param bool|int $include_avatars - if avatars should be retrieved as well
	 * @return mixed[]|bool - see \ElkArte\TopicUtil::prepareContext
	 */
	private function _getUnreadReplies($start, $limit, $include_avatars = false)
	{
		if (!empty($this->_have_temp_table))
		{
			$request = $this->_db->query('', '
				SELECT t.id_topic
				FROM {db_prefix}topics_posted_in AS t
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
				WHERE t.id_board IN ({array_int:boards})
					AND COALESCE(lt.id_msg, t.id_msg) < t.id_last_msg
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($this->_query_parameters, array(
					'order' => (in_array($this->_sort_query, array('t.id_last_msg', 't.id_topic')) ? $this->_sort_query : 't.sort_key') . ($this->_ascending ? '' : ' DESC'),
					'offset' => $start,
					'limit' => $limit,
				))
			);
		}
		else
		{
			$request = $this->_db->query('unread_replies', '
				SELECT t.id_topic, ' . $this->_sort_query . '
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($this->_sort_query, 'ms.') === false ? '' : '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($this->_sort_query, 'mems.') === false ? '' : '
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
				WHERE t.id_board IN ({array_int:boards})
					AND t.id_last_msg >= {int:min_message}
					AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' .
					($this->_post_mod ? ' AND t.approved = {int:is_approved}' : '') .
					($this->_unwatch ? ' AND COALESCE(lt.unwatched, 0) != 1' : '') . '
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($this->_query_parameters, array(
					'current_member' => $this->_user_id,
					'min_message' => $this->_min_message,
					'is_approved' => 1,
					'order' => $this->_sort_query . ($this->_ascending ? '' : ' DESC'),
					'offset' => $start,
					'limit' => $limit,
				))
			);
		}
		$topics = array();
		while ($row = $this->_db->fetch_assoc($request))
			$topics[] = $row['id_topic'];
		$this->_db->free_result($request);

		// Sanity... where have you gone?
		if (empty($topics))
			return false;

		if ($this->_preview_bodies == 'all')
			$body_query = 'ml.body AS last_body, ms.body AS first_body,';
		else
		{
			// If empty, no preview at all
			if (empty($this->_preview_bodies))
				$body_query = '';
			// Default: a SUBSTRING
			else
				$body_query = 'SUBSTRING(ml.body, 1, ' . ($this->_preview_bodies + 256) . ') AS last_body, SUBSTRING(ms.body, 1, ' . ($this->_preview_bodies + 256) . ') AS first_body,';
		}

		if (!empty($include_avatars))
		{
			if ($include_avatars === 1 || $include_avatars === 3)
			{
				$custom_selects = array('meml.avatar', 'COALESCE(a.id_attach, 0) AS id_attach', 'a.filename', 'a.attachment_type', 'meml.email_address');
				$custom_joins = array('LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)');
			}
			else
			{
				$custom_selects = array();
				$custom_joins = array();
			}

			if ($include_avatars === 2 || $include_avatars === 3)
			{
				$custom_selects = array_merge($custom_selects, array('memf.avatar AS avatar_first', 'COALESCE(af.id_attach, 0) AS id_attach_first', 'af.filename AS filename_first', 'af.attachment_type AS attachment_type_first', 'memf.email_address AS email_address_first'));
				$custom_joins = array_merge($custom_joins, array('LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = ms.id_member AND af.id_member != 0)'));
			}
		}

		$request = $this->_db->query('substring', '
			SELECT
				ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
				ms.poster_name AS first_member_name, ml.poster_name AS last_member_name, t.approved,
				t.num_replies, t.num_views, t.num_likes, ms.id_member AS first_id_member, ml.id_member AS last_id_member,
				ml.poster_time AS last_poster_time, COALESCE(mems.real_name, ms.poster_name) AS first_display_name,
				COALESCE(meml.real_name, ml.poster_name) AS last_display_name, ml.subject AS last_subject,
				ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
				COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from,
				' . $body_query . '
				' . (!empty($custom_selects) ? implode(',', $custom_selects) . ', ' : '') . '
				ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (!empty($custom_joins) ? implode("\n\t\t\t\t", $custom_joins) : '') . '
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY {raw:order}
			LIMIT {int:limit}',
			array(
				'current_member' => $this->_user_id,
				'order' => $this->_sort_query . ($this->_ascending ? '' : ' DESC'),
				'topic_list' => $topics,
				'limit' => count($topics),
			)
		);
		$return = array();
		while ($row = $this->_db->fetch_assoc($request))
			$return[] = $row;
		$this->_db->free_result($request);

		return TopicUtil::prepareContext($return, true, ((int) $this->_preview_bodies) + 128);
	}

	/**
	 * Handle creation of temporary tables to track unread replies.
	 * The main benefit of this temporary table is not that it's faster;
	 * it's that it avoids locks later.
	 *
	 * @param int $board_id - id of a board if looking at the unread of a single
	 *            board, 0 if looking at the unread of the entire forum
	 */
	private function _unreadreplies_tempTable($board_id)
	{
		$this->_db->query('', '
			DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
			array(
			)
		);

		$this->_db->query('', '
			DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
			array(
			)
		);

		$sortKey_joins = array(
			'ms.subject' => '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
			'COALESCE(mems.real_name, ms.poster_name)' => '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
		);

		$this->_db->skip_next_error();
		$this->_have_temp_table = $this->_db->query('', '
			CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
				id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
				id_board smallint(5) unsigned NOT NULL default {string:string_zero},
				id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
				id_msg int(10) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_topic)
			)
			SELECT t.id_topic, t.id_board, t.id_last_msg, COALESCE(lmr.id_msg, 0) AS id_msg' . (!in_array($this->_sort_query, array('t.id_last_msg', 't.id_topic')) ? ', ' . $this->_sort_query . ' AS sort_key' : '') . '
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' . ($this->_unwatch ? '
				LEFT JOIN {db_prefix}log_topics AS lt ON (t.id_topic = lt.id_topic AND lt.id_member = {int:current_member})' : '') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$this->_sort_query]) ? $sortKey_joins[$this->_sort_query] : '') . '
			WHERE m.id_member = {int:current_member}' . (!empty($board_id) ? '
				AND t.id_board = {int:current_board}' : '') . ($this->_post_mod ? '
				AND t.approved = {int:is_approved}' : '') . ($this->_unwatch ? '
				AND COALESCE(lt.unwatched, 0) != 1' : '') . '
			GROUP BY m.id_topic',
			array(
				'current_board' => $board_id,
				'current_member' => $this->_user_id,
				'is_approved' => 1,
				'string_zero' => '0',
			)
		)->hasResults() !== false;

		// If that worked, create a sample of the log_topics table too.
		if ($this->_have_temp_table)
		{
			$this->_db->skip_next_error();
			$this->_have_temp_table = $this->_db->query('', '
				CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
					PRIMARY KEY (id_topic)
				)
				SELECT lt.id_topic, lt.id_msg
				FROM {db_prefix}log_topics AS lt
					INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
				WHERE lt.id_member = {int:current_member}',
				array(
					'current_member' => $this->_user_id,
				)
			)->hasResults() !== false;
		}
	}

	/**
	 * Creates a temporary table for logging of unread topics
	 */
	private function _recent_log_topics_unread_tempTable()
	{
		$this->_db->query('', '
			DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
			array(
			)
		);

		$this->_db->skip_next_error();
		// Let's copy things out of the log_topics table, to reduce searching.
		$this->_have_temp_table = $this->_db->query('', '
			CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg, lt.unwatched
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}
				AND t.id_board IN ({array_int:boards})' . (empty($this->_earliest_msg) ? '' : '
				AND t.id_last_msg > {int:earliest_msg}') . ($this->_post_mod ? '
				AND t.approved = {int:is_approved}' : '') . ($this->_unwatch ? '
				AND lt.unwatched != 1' : ''),
			array_merge($this->_query_parameters, array(
				'current_member' => $this->_user_id,
				'earliest_msg' => $this->_earliest_msg,
				'is_approved' => 1,
			))
		)->hasResults() !== false;
	}
}
