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

use ElkArte\DataValidator;
use ElkArte\Exceptions\Exception;
use ElkArte\HttpReq;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\ValuesContainer;

/**
 * Actually do the searches
 */
class SearchParams extends ValuesContainer
{
	/** @var string the db query for members */
	public $_userQuery = '';

	/** @var string The db query for brd's */
	public $_boardQuery = '';

	/** @var int Needed to calculate relevance */
	public $_minMsg = 0;

	/** @var int The minimum message id we will search, needed to calculate relevance */
	public $_minMsgID = 0;

	/** @var int The maximum message ID we will search, needed to calculate relevance */
	public $_maxMsgID = 0;

	/** @var int Message "age" via ID, given bounds, needed to calculate relevance */
	public $_recentMsg = 0;

	/** @var int[] */
	public $_memberlist = [];

	/** @var string The string containing encoded search params */
	protected $_search_string = '';

	/** @var \ElkArte\Database\QueryInterface|null */
	protected $_db;

	/** @var \Elkarte\HttpReq HttpReq instance */
	protected $_req;

	/**
	 * $_search_params will carry all settings that differ from the default search parameters.
	 * That way, the URLs involved in a search page will be kept as short as possible.
	 *
	 * @var mixed
	 */
	protected $_search_params = [];

	/**
	 * Constructor
	 *
	 * @package Search
	 */
	public function __construct($string)
	{
		$this->_db = database();
		$this->_search_string = $string;
		$this->prepare();
		$this->data = &$this->_search_params;
		$this->_req = HttpReq::instance();
	}

	/**
	 * Extract search params from a string
	 */
	protected function prepare()
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $this->_search_string));

		// Test for gzuncompress failing, our ErrorException will die on any E_WARNING with no
		// Exception, so turn it off/on for this check.
		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$check = gzuncompress($temp_params);
		}
		catch (\Exception $e)
		{
			$check = $temp_params;
		}
		finally
		{
			restore_error_handler();
		}

		$this->_search_params = json_decode($check, true);
	}

	/**
	 * Encodes search params ($this->_search_params) in an URL-compatible way
	 *
	 * @param array $search build param index with specific search term (did you mean?)
	 *
	 * @return string - the encoded string to be appended to the URL
	 */
	public function compileURL($search = [])
	{
		$temp_params = $this->_search_params;

		if (!empty($search))
		{
			$temp_params['search'] = implode(' ', $search);
		}

		// *** Encode all search params
		// All search params have been checked, let's compile them to a single string.
		$encoded = json_encode($temp_params);

		// Due to some potential browser/server limitations, attempt to compress
		// old IE's 2083 character limit, we have to compress long search
		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$compressed = gzcompress($encoded);
		}
		catch (\Exception $e)
		{
			$compressed = $encoded;
		}
		finally
		{
			restore_error_handler();
		}

		return str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode($compressed));
	}

	/**
	 * Merge search params extracted with SearchParams::prepare
	 * with those present in the $param array (usually $_REQUEST['params'])
	 *
	 * @param array $params - An array of search parameters
	 * @param int $recentPercentage - A coefficient to calculate the lowest
	 *                message id to start search from
	 * @param int $maxMembersToSearch - The maximum number of members to consider
	 *                when multiple are found
	 *
	 * @throws \ElkArte\Exceptions\Exception topic_gone
	 */
	public function merge($params, $recentPercentage, $maxMembersToSearch)
	{
		global $modSettings;

		// Determine the search settings from the form or get params
		$this->cleanParams($params);
		$this->setAdvanced($params);
		$this->setSearchType($params);
		$this->setMinMaxAge($params);
		$this->setTopic($params);
		$this->setUser($params);

		// If there's no specific user, then don't mention it in the main query.
		if (empty($this->_search_params['userspec']))
		{
			$this->_userQuery = '';
		}
		else
		{
			$this->buildUserQuery($maxMembersToSearch);
		}

		// Ensure that boards are an array of integers (or nothing).
		$query_boards = $this->setBoards($params);

		// What boards are we searching in, all, selected, limited due to a topic?
		$this->_search_params['brd'] = $this->setTopicBoardLimit($query_boards);
		$this->_boardQuery = $this->setBoardQuery();

		$this->_search_params['show_complete'] = !empty($this->_search_params['show_complete']) || !empty($params['show_complete']);
		$this->_search_params['subject_only'] = !empty($this->_search_params['subject_only']) || !empty($params['subject_only']);

		// Get the sorting parameters right. Default to sort by relevance descending.
		$this->setSortAndDirection($params);

		// Determine some values needed to calculate the relevance.
		$this->_minMsg = (int) ((1 - $recentPercentage) * $modSettings['maxMsgID']);
		$this->_recentMsg = $modSettings['maxMsgID'] - $this->_minMsg;

		// *** Parse the search query
		call_integration_hook('integrate_search_params', array(&$this->_search_params));

		// What are we searching for?
		$this->_search_params['search'] = $this->setSearchTerm();
	}

	/**
	 * Cast the passed params to what we demand they be
	 *
	 * @param mixed $params
	 */
	public function cleanParams(&$params)
	{
		$validator = new DataValidator();

		// Convert dates to days between now and ...
		$params['minage'] = $this->daysBetween($params['minage'], 0);
		$params['maxage'] = $this->daysBetween($params['maxage'], 9999);

		$validator->sanitation_rules(array(
			'advanced' => 'intval',
			'searchtype' => 'intval',
			'minage' => 'intval',
			'maxage' => 'intval',
			'search_selection' => 'intval',
			'topic' => 'intval',
			'sd_topic' => 'intval',
			'userspec' => 'trim',
			'brd' => 'intval',
			'sort' => 'trim',
			'show_complete' => 'boolval',
			'sd_brd' => 'intval'
		));
		$validator->input_processing(array(
			'brd' => 'array',
			'sd_brd' => 'array'
		));
		$validator->validate($params);

		$params = array_replace((array) $params, $validator->validation_data());
	}

	/**
	 * Sets if using the advanced search mode
	 *
	 * @param mixed $params
	 */
	public function setAdvanced($params)
	{
		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($this->_search_params['advanced']))
		{
			$this->_search_params['advanced'] = empty($params['advanced']) ? 0 : 1;
		}
	}

	/**
	 * Set the search type to all or any
	 *
	 * @param mixed $params
	 */
	public function setSearchType($params)
	{
		// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
		if (!empty($this->_search_params['searchtype']) || (!empty($params['searchtype']) && $params['searchtype'] === 2))
		{
			$this->_search_params['searchtype'] = 2;
		}
	}

	/**
	 * Sets the timeline to search in, if any, for messages
	 *
	 * @param mixed $params
	 */
	public function setMinMaxAge($params)
	{
		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($this->_search_params['minage']) || (!empty($params['minage']) && $params['minage'] > 0))
		{
			$this->_search_params['minage'] = !empty($this->_search_params['minage']) ? $this->_search_params['minage'] : $params['minage'];
		}

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($this->_search_params['maxage']) || (!empty($params['maxage']) && $params['maxage'] < 9999))
		{
			$this->_search_params['maxage'] = !empty($this->_search_params['maxage']) ? $this->_search_params['maxage'] : $params['maxage'];
		}

		if (!empty($this->_search_params['minage']) || !empty($this->_search_params['maxage']))
		{
			$this->getMinMaxLimits();
		}
	}

	/**
	 * Determines and sets the min and max message ID based on timelines of min/max
	 */
	private function getMinMaxLimits()
	{
		global $modSettings, $context;

		$request = $this->_db->query('', '
			SELECT ' .
			(empty($this->_search_params['maxage']) ? '0, ' : 'COALESCE(MIN(id_msg), -1), ') . (empty($this->_search_params['minage']) ? '0' : 'COALESCE(MAX(id_msg), -1)') . '
			FROM {db_prefix}messages
			WHERE 1=1' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved_true}' : '') . (empty($this->_search_params['minage']) ? '' : '
				AND poster_time <= {int:timestamp_minimum_age}') . (empty($this->_search_params['maxage']) ? '' : '
				AND poster_time >= {int:timestamp_maximum_age}'),
			array(
				'timestamp_minimum_age' => empty($this->_search_params['minage']) ? 0 : time() - 86400 * $this->_search_params['minage'],
				'timestamp_maximum_age' => empty($this->_search_params['maxage']) ? 0 : time() - 86400 * $this->_search_params['maxage'],
				'is_approved_true' => 1,
			)
		);
		list($this->_minMsgID, $this->_maxMsgID) = $request->fetch_row();
		if ($this->_minMsgID < 0 || $this->_maxMsgID < 0)
		{
			$context['search_errors']['no_messages_in_time_frame'] = true;
		}
	}

	/**
	 * Set the topic to search in, if any
	 *
	 * @param mixed $params
	 */
	public function setTopic($params)
	{
		// Searching a specific topic?
		if (!empty($params['topic']) || (!empty($params['search_selection']) && $params['search_selection'] === 'topic'))
		{
			$this->_search_params['topic'] = empty($params['search_selection']) ? (int) $params['topic'] : ($params['sd_topic'] ?? '');
			$this->_search_params['show_complete'] = true;
		}
	}

	/**
	 * Set the search by user value, if any
	 *
	 * @param mixed $params
	 */
	public function setUser($params)
	{
		// Default the user name to a wildcard matching every user (*).
		if (!empty($this->_search_params['userspec']) || (!empty($params['userspec']) && $params['userspec'] !== '*'))
		{
			$this->_search_params['userspec'] = $this->_search_params['userspec'] ?? $params['userspec'];
		}
	}

	/**
	 * So you want to search for items based on a specific user, or group of users or wildcard users?
	 *
	 * Will use real_name first and if nothing found, backup to member_name
	 *
	 * @param int $maxMembersToSearch
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function buildUserQuery($maxMembersToSearch)
	{
		$userString = strtr(Util::htmlspecialchars($this->_search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));
		$possible_users = array_map('trim', $possible_users);
		$possible_users = array_filter($possible_users);

		// Create a list of database-escaped search names.
		$realNameMatches = [];
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
		// Nothing? lets try the poster name instead since that is what they go by
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
		// We have some users!
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

	/**
	 * Figures what boards we have been requested to search in.  Does not check
	 * permissions at this point.
	 *
	 * @param $params
	 * @return int[]
	 */
	public function setBoards($params)
	{
		if (!empty($this->_search_params['brd']) && is_array($this->_search_params['brd']))
		{
			return $this->_search_params['brd'];
		}

		if (!empty($params['brd']) && is_array($params['brd']))
		{
			return $params['brd'];
		}

		if (!empty($params['search_selection']) && $params['search_selection'] === 'board' && !empty($params['sd_brd']) && is_array($params['sd_brd']))
		{
			return $params['sd_brd'];
		}

		return [];
	}

	/**
	 * Determines what boards you can or should be searching
	 *
	 * @param $query_boards
	 * @return int[] array of boards to search in
	 * @throws \ElkArte\Exceptions\Exception topic_gone
	 */
	public function setTopicBoardLimit($query_boards)
	{
		global $modSettings, $context;

		// Searching by topic means the board is set as well.
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
			$this->_search_params['brd'] = [];
			$brd = (int) $request->fetch_row()[0];

			$request->free_result();

			return [$brd];
		}

		// Select all boards you've selected AND are allowed to see.
		if (User::$info->is_admin && (!empty($this->_search_params['advanced']) || !empty($query_boards)))
		{
			return $query_boards;
		}

		require_once(SUBSDIR . '/Boards.subs.php');
		$brd = array_keys(fetchBoardsInfo(array(
			'boards' => $query_boards), array(
				'include_recycle' => false,
				'include_redirects' => false,
				'wanna_see_board' => empty($this->_search_params['advanced'])
			)
		));

		// This error should pro'bly only happen for hackers.
		if (empty($brd))
		{
			$context['search_errors']['no_boards_selected'] = true;
			$brd = [];
		}

		return $brd;
	}

	/**
	 * Builds the query for the boards we are searching in.
	 *
	 * @return string
	 */
	public function setBoardQuery()
	{
		if (count($this->_search_params['brd']) !== 0)
		{
			array_map('intval', $this->_search_params['brd']);

			// If we've selected all boards, this parameter can be left empty.
			require_once(SUBSDIR . '/Boards.subs.php');
			$num_boards = countBoards();

			if (count($this->_search_params['brd']) == $num_boards)
			{
				return $this->_boardQuery = '';
			}

			if (count($this->_search_params['brd']) == $num_boards - 1 && !empty($modSettings['recycle_board']) && !in_array($modSettings['recycle_board'], $this->_search_params['brd']))
			{
				return $this->_boardQuery = '!= ' . $modSettings['recycle_board'];
			}

			return $this->_boardQuery = 'IN (' . implode(', ', $this->_search_params['brd']) . ')';
		}

		return '';
	}

	/**
	 * Sets the sort column and direction
	 *
	 * @event integrate_search_sort_columns
	 * @param mixed $params
	 */
	public function setSortAndDirection($params)
	{
		$sort_columns = ['relevance', 'num_replies', 'id_msg',];

		// Allow integration to add additional sort columns
		call_integration_hook('integrate_search_sort_columns', array(&$sort_columns));

		if (empty($this->_search_params['sort']) && !empty($params['sort']))
		{
			list($this->_search_params['sort'], $this->_search_params['sort_dir']) = array_pad(explode('|', $params['sort']), 2, '');
		}

		$this->_search_params['sort'] = !empty($this->_search_params['sort']) && in_array($this->_search_params['sort'], $sort_columns) ? $this->_search_params['sort'] : 'relevance';

		if (!empty($this->_search_params['topic']) && $this->_search_params['sort'] === 'num_replies')
		{
			$this->_search_params['sort'] = 'id_msg';
		}

		// Sorting direction: descending unless stated otherwise.
		$this->_search_params['sort_dir'] = !empty($this->_search_params['sort_dir']) && $this->_search_params['sort_dir'] === 'asc' ? 'asc' : 'desc';
	}

	/**
	 * Set the search term from wherever we can find it!
	 *
	 * @return string
	 */
	public function setSearchTerm()
	{
		if (!empty($this->_search_params['search']))
		{
			return $this->_search_params['search'];
		}

		return $this->_req->getRequest('search', 'un_htmlspecialchars', '');
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

	/**
	 * Number of days between today/now and some date.
	 *
	 * @param string $date
	 * @param int $default
	 * @return int
	 */
	private function daysBetween($date, $default)
	{
		// Already a number, validate
		if (is_numeric($date))
		{
			return (max(min(0, $date), 9999));
		}

		// Nothing, then full range
		if (empty($date))
		{
			return $default;
		}

		$startTimeStamp = time();
		$endTimeStamp = strtotime($date);
		$timeDiff = $startTimeStamp - ($endTimeStamp !== false ? $endTimeStamp : $startTimeStamp);

		// Can't search into the future
		if ($timeDiff < 1)
		{
			$timeDiff = 0;
		}

		return (int) $timeDiff / 86400;
	}
}
