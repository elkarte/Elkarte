<?php

/**
 * DB and general functions for working with the message index
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

class Message_Index extends List_Abstract
{
	protected $_use_pre_query = false;

	public function __construct($options)
	{
		$db = database();
		parent::__construct($db, $options);
	}

	public function getResults($id_board, $id_member, $indexOptions)
	{
		if ($this->_queryParams['start'] > 0 && $this->_queryParams['limit'] > 0)
			$this->_use_pre_query = true;

		$this->_doExtend($id_board, $id_member, $indexOptions);

		$query = $this->_generateQueryString($indexOptions);

		$this->_doQuery($query, $this->_use_pre_query ? '' : 'substring');

		$results = array();

		if ($this->_use_pre_query)
		{
			$topic_ids = array();
			while ($row = $this->_db->fetch_assoc($this->_listRequest))
				$topic_ids[] = $row;

			$results = $this->_getTopicsData();
		}
		else
		{
			while ($row = $this->_db->fetch_assoc($this->_listRequest))
				$results[] = $row;
		}

		if ($this->_fake_ascending)
			$results = array_reverse($results, true);

		return $results;
	}

	protected function _doExtend($id_board, $id_member, $indexOptions)
	{
		$sort_by = $this->getSort();

		$this->addQueryParam('current_board', $id_board);
		$this->addQueryParam('current_member', $id_member);
		$this->addQueryParam('is_approved', 1);
		$this->addQueryParam('id_member_guest', 0);

		if ($this->_use_pre_query)
		{
			if ($sort_by === 'last_poster')
			{
				$this->extendQuery('', 'INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)');
			}
			elseif (in_array($sort_by, array('starter', 'subject')))
			{
				$this->extendQuery('', 'INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)');

				if ($sort_by === 'starter')
					$this->extendQuery('', 'LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)');
			}
			if ($indexOptions['only_approved'])
				$this->extendQuery('', '', 'AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')');
		}

		// If empty, no preview at all
		if (!empty($indexOptions['previews']))
		{
			// If -1 means everything
			if ($indexOptions['previews'] === -1)
				$this->extendQuery('ml.body AS last_body, mf.body AS first_body');
			// Default: a SUBSTRING
			else
				$this->extendQuery('SUBSTRING(ml.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS last_body, SUBSTRING(mf.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS first_body');
		}

		if ($id_member == 0)
		{
			$this->extendQuery('0 AS new_from');
		}
		else
		{
			$this->extendQuery('IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from', '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})');
		}
		if ($indexOptions['only_approved'])
		{
			$this->extendQuery('', '', '
				AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')');
		}
		if (!empty($indexOptions['include_avatars']))
		{
			$this->extendQuery('meml.avatar, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, meml.email_address', 'LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)');
		}
		if (!empty($indexOptions['custom_selects']))
		{
			foreach ($indexOptions['custom_selects'] as $select)
				$this->extendQuery($select);
		}
	}

	protected function _generateQueryString($indexOptions)
	{
		$sort_column = $this->_listOptions['allowed_sortings'][$this->getSort()];
		$sort = $this->_fake_ascending ? !$this->getSort(true) : $this->getSort(true);

		if ($this->_use_pre_query)
		{
			return '
				SELECT t.id_topic
				FROM {db_prefix}topics AS t
					{query_extend_join}
				WHERE t.id_board = {int:current_board}
					{query_extend_where}
				ORDER BY ' . ($indexOptions['include_sticky'] ? 'is_sticky' . ($this->_fake_ascending ? '' : ' DESC') . ', ' : '') . $sort_column . ($sort ? '' : ' DESC') . '
				LIMIT {int:start}, {int:maxindex}';
		}
		else
		{
			return '
				SELECT
					t.id_topic, t.num_replies, t.locked, t.num_views, t.num_likes, t.is_sticky, t.id_poll, t.id_previous_board,
					t.id_last_msg, t.approved, t.unapproved_posts, t.id_redirect_topic, t.id_first_msg,
					ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
					ml.poster_name AS last_member_name, ml.id_member AS last_id_member, ml.smileys_enabled AS last_smileys,
					IFNULL(meml.real_name, ml.poster_name) AS last_display_name,
					mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
					mf.poster_name AS first_member_name, mf.id_member AS first_id_member, mf.smileys_enabled AS first_smileys,
					IFNULL(memf.real_name, mf.poster_name) AS first_display_name
					{query_extend_select}
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
					LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
					{query_extend_join}
				WHERE t.id_board = {int:current_board} {query_extend_where}
				ORDER BY ' . ($indexOptions['include_sticky'] ? 'is_sticky' . ($this->_fake_ascending ? '' : ' DESC') . ', ' : '') . $sort_column . ($sort ? '' : ' DESC') . '
				LIMIT {int:start}, {int:maxindex}';
		}
	}

	protected function _getTopicsData()
	{
		$this->_doQuery('
			SELECT
				t.id_topic, t.num_replies, t.locked, t.num_views, t.num_likes, t.is_sticky, t.id_poll, t.id_previous_board,
				t.id_last_msg, t.approved, t.unapproved_posts, t.id_redirect_topic, t.id_first_msg,
				ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
				ml.poster_name AS last_member_name, ml.id_member AS last_id_member, ml.smileys_enabled AS last_smileys,
				IFNULL(meml.real_name, ml.poster_name) AS last_display_name,
				mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
				mf.poster_name AS first_member_name, mf.id_member AS first_id_member, mf.smileys_enabled AS first_smileys,
				IFNULL(memf.real_name, mf.poster_name) AS first_display_name
				{query_extend_select}
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
				{query_extend_join}
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY FIND_IN_SET(t.id_topic, {string:find_set_topics})
			LIMIT {int:maxindex}', 'substring');

			$return = array();
			while ($row = $this->_db->fetch_assoc($this->_listRequest))
				$return[$row['id_topic']] = $row;

			return $return;
	}
}