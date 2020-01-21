<?php

/**
 * Utility class for search functionality.
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

namespace ElkArte\Search;

use ElkArte\Exceptions\Exception;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\ValuesContainer;

/**
 * Actually do the searches
 */
class SearchParams extends ValuesContainer
{
	/**
	 * the db query for members
	 *
	 * @var string
	 */
	public $_userQuery = '';
	/**
	 * The db query for brd's
	 *
	 * @var string
	 */
	public $_boardQuery = '';
	/**
	 * Needed to calculate relevance
	 *
	 * @var int
	 */
	public $_minMsg = 0;
	/**
	 * The minimum message id we will search, needed to calculate relevance
	 *
	 * @var int
	 */
	public $_minMsgID = 0;
	/**
	 * The maximum message ID we will search, needed to calculate relevance
	 *
	 * @var int
	 */
	public $_maxMsgID = 0;
	/**
	 * Message "age" via ID, given bounds, needed to calculate relevance
	 *
	 * @var int
	 */
	public $_recentMsg = 0;
	/**
	 *
	 * @var int[]
	 */
	public $_memberlist = [];
	/**
	 * $_search_params will carry all settings that differ from the default search parameters.
	 *
	 * That way, the URLs involved in a search page will be kept as short as possible.
	 *
	 * @var string[]
	 */
	protected $_search_params = array();
	/**
	 * $_search_params will carry all settings that differ from the default search parameters.
	 *
	 * That way, the URLs involved in a search page will be kept as short as possible.
	 *
	 * @var string
	 */
	protected $_search_string = '';
	/**
	 *
	 * @var null|Object
	 */
	protected $_db = null;

	/**
	 * Constructor
	 *
	 * @param string $string - the string containing encoded search params
	 * @package Search
	 *
	 */
	public function __construct($string)
	{
		$this->_db = database();
		$this->_search_string = $string;
		$this->prepare();
		$this->data = &$this->_search_params;
	}

	/**
	 * Extract search params from a string
	 */
	protected function prepare()
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $this->_search_string));

		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', (!empty($temp_params2) ? $temp_params2 : $temp_params));

		foreach ($temp_params as $i => $data)
		{
			list($k, $v) = array_pad(explode('|\'|', $data), 2, '');
			$this->_search_params[$k] = $v;
		}

		if (isset($this->_search_params['brd']))
		{
			$this->_search_params['brd'] = empty($this->_search_params['brd']) ? array() : explode(',', $this->_search_params['brd']);
		}
	}

	/**
	 * Encodes search params ($this->_search_params) in an URL-compatible way
	 *
	 * @param array $search build param index with specific search term (did you mean?)
	 *
	 * @return string - the encoded string to be appended to the URL
	 */
	public function compileURL($search = array())
	{
		$temp_params = $this->_search_params;
		$encoded = array();

		if (!empty($search))
		{
			$temp_params['search'] = implode(' ', $search);
		}

		// *** Encode all search params
		// All search params have been checked, let's compile them to a single string... made less simple by PHP 4.3.9 and below.
		if (isset($temp_params['brd']))
		{
			$temp_params['brd'] = implode(',', $temp_params['brd']);
		}

		foreach ($temp_params as $k => $v)
		{
			$encoded[] = $k . '|\'|' . $v;
		}

		if (!empty($encoded))
		{
			// Due to old IE's 2083 character limit, we have to compress long search strings
			$params = @gzcompress(implode('|"|', $encoded));

			// Gzcompress failed, use try non-gz
			if (empty($params))
			{
				$params = implode('|"|', $encoded);
			}

			// Base64 encode, then replace +/= with uri safe ones that can be reverted
			$encoded = str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode($params));
		}
		else
		{
			$encoded = '';
		}

		return $encoded;
	}

	/**
	 * Merge search params extracted with SearchParams::prepare
	 * with those present in the $param array (usually $_REQUEST['params'])
	 *
	 * @param mixed[] $params - An array of search parameters
	 * @param int $recentPercentage - A coefficient to calculate the lowest
	 *                message id to start search from
	 * @param int $maxMembersToSearch - The maximum number of members to consider
	 *                when multiple are found
	 *
	 * @throws \ElkArte\Exceptions\Exception topic_gone
	 */
	public function merge($params, $recentPercentage, $maxMembersToSearch)
	{
		global $modSettings, $context;

		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($this->_search_params['advanced']))
		{
			$this->_search_params['advanced'] = empty($params['advanced']) ? 0 : 1;
		}

		// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
		if (!empty($this->_search_params['searchtype']) || (!empty($params['searchtype']) && $params['searchtype'] == 2))
		{
			$this->_search_params['searchtype'] = 2;
		}

		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($this->_search_params['minage']) || (!empty($params['minage']) && $params['minage'] > 0))
		{
			$this->_search_params['minage'] = !empty($this->_search_params['minage']) ? (int) $this->_search_params['minage'] : (int) $params['minage'];
		}

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($this->_search_params['maxage']) || (!empty($params['maxage']) && $params['maxage'] < 9999))
		{
			$this->_search_params['maxage'] = !empty($this->_search_params['maxage']) ? (int) $this->_search_params['maxage'] : (int) $params['maxage'];
		}

		// Searching a specific topic?
		if (!empty($params['topic']) || (!empty($params['search_selection']) && $params['search_selection'] === 'topic'))
		{
			$this->_search_params['topic'] = empty($params['search_selection']) ? (int) $params['topic'] : (isset($params['sd_topic']) ? (int) $params['sd_topic'] : '');
			$this->_search_params['show_complete'] = true;
		}
		elseif (!empty($this->_search_params['topic']))
		{
			$this->_search_params['topic'] = (int) $this->_search_params['topic'];
		}

		if (!empty($this->_search_params['minage']) || !empty($this->_search_params['maxage']))
		{
			$request = $this->_db->query('', '
				SELECT ' . (empty($this->_search_params['maxage']) ? '0, ' : 'COALESCE(MIN(id_msg), -1), ') . (empty($this->_search_params['minage']) ? '0' : 'COALESCE(MAX(id_msg), -1)') . '
				FROM {db_prefix}messages
				WHERE 1=1' . ($modSettings['postmod_active'] ? '
					AND m.approved = {int:is_approved_true}
					AND approved = {int:is_approved_true}' : '') . (empty($this->_search_params['minage']) ? '' : '
					AND poster_time <= {int:timestamp_minimum_age}') . (empty($this->_search_params['maxage']) ? '' : '
					AND poster_time >= {int:timestamp_maximum_age}'),
				array(
					'timestamp_minimum_age' => empty($this->_search_params['minage']) ? 0 : time() - 86400 * $this->_search_params['minage'],
					'timestamp_maximum_age' => empty($this->_search_params['maxage']) ? 0 : time() - 86400 * $this->_search_params['maxage'],
					'is_approved_true' => 1,
				)
			);
			list ($this->_minMsgID, $this->_maxMsgID) = $request->fetch_row();
			if ($this->_minMsgID < 0 || $this->_maxMsgID < 0)
			{
				$context['search_errors']['no_messages_in_time_frame'] = true;
			}
			$request->free_result();
		}

		// Default the user name to a wildcard matching every user (*).
		if (!empty($this->_search_params['userspec']) || (!empty($params['userspec']) && $params['userspec'] != '*'))
		{
			$this->_search_params['userspec'] = isset($this->_search_params['userspec']) ? $this->_search_params['userspec'] : $params['userspec'];
		}

		// If there's no specific user, then don't mention it in the main query.
		if (empty($this->_search_params['userspec']))
		{
			$this->_userQuery = '';
		}
		else
		{
			$userString = strtr(Util::htmlspecialchars($this->_search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
			$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

			preg_match_all('~"([^"]+)"~', $userString, $matches);
			$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

			foreach ($possible_users as $k => $possible_user)
			{
				$possible_users[$k] = trim($possible_user);
				if ($possible_users[$k] === '')
				{
					unset($possible_users[$k]);
				}
			}

			// Create a list of database-escaped search names.
			$realNameMatches = array();
			foreach ($possible_users as $possible_user)
			{
				$realNameMatches[] = $this->_db->quote(
					'{string:possible_user}',
					array(
						'possible_user' => $possible_user
					)
				);
			}

			// Retrieve a list of possible members.
			$request = $this->_db->query('', '
				SELECT
					id_member
				FROM {db_prefix}members
				WHERE {raw:match_possible_users}',
				array(
					'match_possible_users' => 'real_name LIKE ' . implode(' OR real_name LIKE ', $realNameMatches),
				)
			);

			// Simply do nothing if there're too many members matching the criteria.
			if ($request->num_rows() > $maxMembersToSearch)
			{
				$this->_userQuery = '';
			}
			elseif ($request->num_rows() == 0)
			{
				$this->_userQuery = $this->_db->quote(
					'm.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})',
					array(
						'id_member_guest' => 0,
						'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
					)
				);
			}
			else
			{
				while (($row = $request->fetch_assoc()))
				{
					$this->_memberlist[] = $row['id_member'];
				}

				$this->_userQuery = $this->_db->quote(
					'(m.id_member IN ({array_int:matched_members}) OR (m.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})))',
					array(
						'matched_members' => $this->_memberlist,
						'id_member_guest' => 0,
						'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
					)
				);
			}
			$request->free_result();
		}

		// Ensure that boards are an array of integers (or nothing).
		if (!empty($this->_search_params['brd']) && is_array($this->_search_params['brd']))
		{
			$query_boards = array_map('intval', $this->_search_params['brd']);
		}
		elseif (!empty($params['brd']) && is_array($params['brd']))
		{
			$query_boards = array_map('intval', $params['brd']);
		}
		elseif (!empty($params['brd']))
		{
			$query_boards = array_map('intval', explode(',', $params['brd']));
		}
		elseif (!empty($params['search_selection']) && $params['search_selection'] === 'board' && !empty($params['sd_brd']) && is_array($params['sd_brd']))
		{
			$query_boards = array_map('intval', $params['sd_brd']);
		}
		elseif (!empty($params['search_selection']) && $params['search_selection'] === 'board' && isset($params['sd_brd']) && (int) $params['sd_brd'] !== 0)
		{
			$query_boards = array((int) $params['sd_brd']);
		}
		else
		{
			$query_boards = array();
		}

		// Special case for boards: searching just one topic?
		if (!empty($this->_search_params['topic']))
		{
			$request = $this->_db->query('', '
				SELECT
					b.id_board
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				WHERE t.id_topic = {int:search_topic_id}
					AND {query_see_board}' . ($modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved_true}' : '') . '
				LIMIT 1',
				array(
					'search_topic_id' => $this->_search_params['topic'],
					'is_approved_true' => 1,
				)
			);

			if ($request->num_rows() == 0)
			{
				throw new Exception('topic_gone', false);
			}

			$this->_search_params['brd'] = array();
			list ($this->_search_params['brd'][0]) = $request->fetch_row();
			$request->free_result();
		}
		// Select all boards you've selected AND are allowed to see.
		elseif (User::$info->is_admin && (!empty($this->_search_params['advanced']) || !empty($query_boards)))
		{
			$this->_search_params['brd'] = $query_boards;
		}
		else
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$this->_search_params['brd'] = array_keys(fetchBoardsInfo(array('boards' => $query_boards), array('include_recycle' => false, 'include_redirects' => false, 'wanna_see_board' => empty($this->_search_params['advanced']))));

			// This error should pro'bly only happen for hackers.
			if (empty($this->_search_params['brd']))
			{
				$context['search_errors']['no_boards_selected'] = true;
			}
		}

		if (count($this->_search_params['brd']) !== 0)
		{
			foreach ($this->_search_params['brd'] as $k => $v)
			{
				$this->_search_params['brd'][$k] = (int) $v;
			}

			// If we've selected all boards, this parameter can be left empty.
			require_once(SUBSDIR . '/Boards.subs.php');
			$num_boards = countBoards();

			if (count($this->_search_params['brd']) == $num_boards)
			{
				$this->_boardQuery = '';
			}
			elseif (count($this->_search_params['brd']) == $num_boards - 1 && !empty($modSettings['recycle_board']) && !in_array($modSettings['recycle_board'], $this->_search_params['brd']))
			{
				$this->_boardQuery = '!= ' . $modSettings['recycle_board'];
			}
			else
			{
				$this->_boardQuery = 'IN (' . implode(', ', $this->_search_params['brd']) . ')';
			}
		}
		else
		{
			$this->_boardQuery = '';
		}

		$this->_search_params['show_complete'] = !empty($this->_search_params['show_complete']) || !empty($params['show_complete']);
		$this->_search_params['subject_only'] = !empty($this->_search_params['subject_only']) || !empty($params['subject_only']);

		// Get the sorting parameters right. Default to sort by relevance descending.
		$sort_columns = array(
			'relevance',
			'num_replies',
			'id_msg',
		);

		// Allow integration to add additional sort columns
		call_integration_hook('integrate_search_sort_columns', array(&$sort_columns));

		if (empty($this->_search_params['sort']) && !empty($params['sort']))
		{
			list ($this->_search_params['sort'], $this->_search_params['sort_dir']) = array_pad(explode('|', $params['sort']), 2, '');
		}

		$this->_search_params['sort'] = !empty($this->_search_params['sort']) && in_array($this->_search_params['sort'], $sort_columns) ? $this->_search_params['sort'] : 'relevance';

		if (!empty($this->_search_params['topic']) && $this->_search_params['sort'] === 'num_replies')
		{
			$this->_search_params['sort'] = 'id_msg';
		}

		// Sorting direction: descending unless stated otherwise.
		$this->_search_params['sort_dir'] = !empty($this->_search_params['sort_dir']) && $this->_search_params['sort_dir'] === 'asc' ? 'asc' : 'desc';

		// Determine some values needed to calculate the relevance.
		$this->_minMsg = (int) ((1 - $recentPercentage) * $modSettings['maxMsgID']);
		$this->_recentMsg = $modSettings['maxMsgID'] - $this->_minMsg;

		// *** Parse the search query
		call_integration_hook('integrate_search_params', array(&$this->_search_params));

		// What are we searching for?
		if (empty($this->_search_params['search']))
		{
			if (isset($_GET['search']))
			{
				$this->_search_params['search'] = un_htmlspecialchars($_GET['search']);
			}
			elseif (isset($_POST['search']))
			{
				$this->_search_params['search'] = $_POST['search'];
			}
			else
			{
				$this->_search_params['search'] = '';
			}
		}
	}

	/**
	 * Return the current set of search details
	 *
	 * @return string[]
	 */
	public function get()
	{
		return $this->_search_params;
	}
}
