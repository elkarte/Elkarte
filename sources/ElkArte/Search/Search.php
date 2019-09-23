<?php

/**
 * Utility class for search functionality.
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

namespace ElkArte\Search;

/**
 * Actually do the searches
 */
class Search
{
	/**
	 * This is the forum version but is repeated due to some people
	 * rewriting FORUM_VERSION.
	 */
	const FORUM_VERSION = 'ElkArte 2.0 dev';

	/**
	 * This is the minimum version of ElkArte that an API could have been written
	 * for to work.
	 * (strtr to stop accidentally updating version on release)
	 */
	private $_search_version = '';

	/**
	 * Holds the words and phrases to be searched on
	 * @var \ElkArte\Search\SearchArray
	 */
	private $_searchArray = null;

	/**
	 * Holds instance of the search api in use such as ElkArte\Search\API\Standard_Search
	 * @var null|object
	 */
	private $_searchAPI = null;

	/**
	 * Database instance
	 * @var \ElkArte\Database\QueryInterface|null
	 */
	private $_db = null;

	/**
	 * Search db instance
	 * @var \ElkArte\Database\SearchInterface|null
	 */
	private $_db_search = null;

	/**
	 * Searching for posts from a specific user(s)
	 * @var array
	 */
	private $_memberlist = array();

	/**
	 * Builds the array of words for use in the db query
	 * @var array
	 */
	private $_searchWords = array();

	/**
	 * Words excluded from indexes
	 * @var array
	 */
	private $_excludedIndexWords = array();

	/**
	 * Words not be be found in the subject (-word)
	 * @var array
	 */
	private $_excludedSubjectWords = array();

	/**
	 * Phrases not to be found in the search results (-"some phrase")
	 * @var array
	 */
	private $_excludedPhrases = array();

	/**
	 * The weights to associate to various areas for relevancy
	 * @var \ElkArte\Search\WeightFactors
	 */
	private $_weightFactors = array();

	/**
	 * If we are creating a tmp db table
	 * @var bool
	 */
	private $_createTemporary = true;

	/**
	 *
	 * @var mixed[]
	 */
	protected $_participants = [];

	/**
	 *
	 * @var null|\ElkArte\Search\SearchParams
	 */
	protected $_searchParams = null;

	/**
	 * Constructor
	 * Easy enough, initialize the database objects (generic db and search db)
	 *
	 * @package Search
	 */
	public function __construct()
	{
		$this->_search_version = strtr('ElkArte 1+1', array('+' => '.', '=' => ' '));
		$this->_db = database();
		$this->_db_search = db_search();

		$this->_db_search->skip_next_error();
		// Create new temporary table(s) (if we can) to store preliminary results in.
		$this->_createTemporary = $this->_db_search->createTemporaryTable(
			'{db_prefix}tmp_log_search_messages',
			array(
				array(
					'name' => 'id_msg',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
					'default' => 0,
				)
			),
			array(
				array(
					'name' => 'id_msg',
					'columns' => array('id_msg'),
					'type' => 'primary'
				)
			)
		) !== false;

		$this->_db_search->skip_next_error();
		$this->_db_search->createTemporaryTable('{db_prefix}tmp_log_search_topics',
			array(
				array(
					'name' => 'id_topic',
					'type' => 'mediumint',
					'unsigned' => true,
					'size' => 8,
					'default' => 0
				)
			),
			array(
				array(
					'name' => 'id_topic',
					'columns' => array('id_topic'),
					'type' => 'primary'
				)
			)
		);
	}

	/**
	 * Returns a search parameter.
	 *
	 * @param string $name - name of the search parameters
	 *
	 * @return bool|mixed - the value of the parameter
	 */
	public function param($name)
	{
		if (isset($this->_searchParams[$name]))
		{
			return $this->_searchParams[$name];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns all the search parameters.
	 *
	 * @return mixed[]
	 */
	public function getParams()
	{
		return $this->_searchParams->mergeWith(array(
			'min_msg_id' => (int) $this->_searchParams->_minMsgID,
			'max_msg_id' => (int) $this->_searchParams->_maxMsgID,
			'memberlist' => $this->_searchParams->_memberlist,
		));
	}

	/**
	 * Returns the ignored words
	 */
	public function getIgnored()
	{
		return $this->_searchArray->getIgnored();
	}

	/**
	 * Set the weight factors
	 *
	 * @param \ElkArte\Search\WeightFactors $weight
	 */
	public function setWeights(WeightFactors $weight)
	{
		$this->_weightFactors = $weight;
	}

	public function setParams(SearchParams $paramObject, $search_simple_fulltext = false)
	{
		$this->_searchParams = $paramObject;

		// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
		// @todo Setting to add more here?
		// @todo Maybe only blacklist if they are the only word, or "any" is used?
		$blacklisted_words = array('img', 'url', 'quote', 'www', 'http', 'the', 'is', 'it', 'are', 'if');
		call_integration_hook('integrate_search_blacklisted_words', array(&$blacklisted_words));

		$this->_searchArray = new SearchArray($this->_searchParams->search, $blacklisted_words, $search_simple_fulltext);
	}

	/**
	 * If any black-listed word has been found
	 *
	 * @return bool
	 */
	public function foundBlackListedWords()
	{
		return $this->_searchArray->foundBlackListedWords();
	}

	public function getSearchArray()
	{
		return $this->_searchArray->getSearchArray();
	}

	public function getExcludedWords()
	{
		return $this->_searchArray->getExcludedWords();
	}

	public function getExcludedSubjectWords()
	{
		return $this->_excludedSubjectWords;
	}

	/**
	 * Returns the search parameters.
	 *
	 * @param bool $array If true returns an array, otherwise an object
	 *
	 * @return mixed
	 */
	public function getSearchParams($array = false)
	{
		if ($array)
		{
			return $this->_searchParams->get();
		}
		else
		{
			return $this->_searchParams;
		}
	}

	public function getExcludedPhrases()
	{
		return $this->_excludedPhrases;
	}

	/**
	 * Builds the array of words for the query
	 */
	public function searchWords()
	{
		global $modSettings, $context;

		if (count($this->_searchWords) > 0)
		{
			return $this->_searchWords;
		}

		$orParts = array();
		$this->_searchWords = array();
		$searchArray = $this->_searchArray->getSearchArray();
		$excludedWords = $this->_searchArray->getExcludedWords();

		// All words/sentences must match.
		if (!empty($searchArray) && empty($this->_searchParams['searchtype']))
		{
			$orParts[0] = $searchArray;
		}
		// Any word/sentence must match.
		else
		{
			foreach ($searchArray as $index => $value)
				$orParts[$index] = array($value);
		}

		// Make sure the excluded words are in all or-branches.
		foreach ($orParts as $orIndex => $andParts)
		{
			foreach ($excludedWords as $word)
			{
				$orParts[$orIndex][] = $word;
			}
		}

		// Determine the or-branches and the fulltext search words.
		foreach ($orParts as $orIndex => $andParts)
		{
			$this->_searchWords[$orIndex] = array(
				'indexed_words' => array(),
				'words' => array(),
				'subject_words' => array(),
				'all_words' => array(),
				'complex_words' => array(),
			);

			$this->_searchAPI->setExcludedWords($excludedWords);
			// Sort the indexed words (large words -> small words -> excluded words).
			usort($orParts[$orIndex], array($this->_searchAPI, 'searchSort'));

			foreach ($orParts[$orIndex] as $word)
			{
				$is_excluded = in_array($word, $excludedWords);
				$this->_searchWords[$orIndex]['all_words'][] = $word;
				$subjectWords = text2words($word);

				if (!$is_excluded || count($subjectWords) === 1)
				{
					$this->_searchWords[$orIndex]['subject_words'] = array_merge($this->_searchWords[$orIndex]['subject_words'], $subjectWords);

					if ($is_excluded)
					{
						$this->_excludedSubjectWords = array_merge($this->_excludedSubjectWords, $subjectWords);
					}
				}
				else
				{
					$this->_excludedPhrases[] = $word;
				}

				// Have we got indexes to prepare?
				$this->_searchAPI->prepareIndexes($word, $this->_searchWords[$orIndex], $this->_excludedIndexWords, $is_excluded, $this->_excludedSubjectWords);
			}

			// Search_force_index requires all AND parts to have at least one fulltext word.
			if (!empty($modSettings['search_force_index']) && empty($this->_searchWords[$orIndex]['indexed_words']))
			{
				$context['search_errors']['query_not_specific_enough'] = true;
				break;
			}
			elseif ($this->_searchParams->subject_only && empty($this->_searchWords[$orIndex]['subject_words']) && empty($this->_excludedSubjectWords))
			{
				$context['search_errors']['query_not_specific_enough'] = true;
				break;
			}
			// Make sure we aren't searching for too many indexed words.
			else
			{
				$this->_searchWords[$orIndex]['indexed_words'] = array_slice($this->_searchWords[$orIndex]['indexed_words'], 0, 7);
				$this->_searchWords[$orIndex]['subject_words'] = array_slice($this->_searchWords[$orIndex]['subject_words'], 0, 7);
				$this->_searchWords[$orIndex]['words'] = array_slice($this->_searchWords[$orIndex]['words'], 0, 4);
			}
		}

		return $this->_searchWords;
	}

	/**
	 * Tell me, do I want to see the full message or just a piece?
	 */
	public function isCompact()
	{
		return empty($this->_searchParams['show_complete']);
	}

	/**
	 * Wrapper around SearchParams::compileURL
	 *
	 * @param array $search build param index with specific search term (did you mean?)
	 *
	 * @return string - the encoded string to be appended to the URL
	 */
	public function compileURLparams($search = array())
	{
		return $this->_searchParams->compileURL($search);
	}

	/**
	 * Finds the posters of the messages
	 *
	 * @param int[] $msg_list - All the messages we want to find the posters
	 * @param int $limit - There are only so much topics
	 *
	 * @return int[] - array of members id
	 */
	public function loadPosters($msg_list, $limit)
	{
		// Load the posters...
		$request = $this->_db->query('', '
			SELECT
				id_member
			FROM {db_prefix}messages
			WHERE id_member != {int:no_member}
				AND id_msg IN ({array_int:message_list})
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'limit' => $limit,
				'no_member' => 0,
			)
		);
		$posters = array();
		while ($row = $this->_db->fetch_assoc($request))
			$posters[] = $row['id_member'];
		$this->_db->free_result($request);

		return $posters;
	}

	/**
	 * Finds the posters of the messages
	 *
	 * @param int[] $msg_list - All the messages we want to find the posters
	 * @param int $limit - There are only so much topics
	 *
	 * @return resource|boolean
	 */
	public function loadMessagesRequest($msg_list, $limit)
	{
		global $modSettings;

		return $this->_db->query('', '
			SELECT
				m.id_msg, m.subject, m.poster_name, m.poster_email, m.poster_time,
				m.id_member, m.icon, m.poster_ip, m.body, m.smileys_enabled,
				m.modified_time, m.modified_name, first_m.id_msg AS id_first_msg,
				first_m.subject AS first_subject, first_m.icon AS first_icon,
				first_m.poster_time AS first_poster_time,
				first_mem.id_member AS first_id_member,
				COALESCE(first_mem.real_name, first_m.poster_name) AS first_display_name,
				COALESCE(first_mem.member_name, first_m.poster_name) AS first_member_name,
				last_m.id_msg AS id_last_msg, last_m.poster_time AS last_poster_time,
				last_mem.id_member AS last_id_member,
				COALESCE(last_mem.real_name, last_m.poster_name) AS last_display_name,
				COALESCE(last_mem.member_name, last_m.poster_name) AS last_member_name,
				last_m.icon AS last_icon, last_m.subject AS last_subject,
				t.id_topic, t.is_sticky, t.locked, t.id_poll, t.num_replies,
				t.num_views, t.num_likes,
				b.id_board, b.name AS bname, c.id_cat, c.name AS cat_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				INNER JOIN {db_prefix}messages AS first_m ON (first_m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS last_m ON (last_m.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS first_mem ON (first_mem.id_member = first_m.id_member)
				LEFT JOIN {db_prefix}members AS last_mem ON (last_mem.id_member = first_m.id_member)
			WHERE m.id_msg IN ({array_int:message_list})' . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY FIND_IN_SET(m.id_msg, {string:message_list_in_set})
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'is_approved' => 1,
				'message_list_in_set' => implode(',', $msg_list),
				'limit' => $limit,
			)
		);
	}

	/**
	 * Did the user find any message at all?
	 *
	 * @param resource $messages_request holds a query result
	 *
	 * @return boolean
	 */
	public function noMessages($messages_request)
	{
		return $this->_db->num_rows($messages_request) == 0;
	}

	public function searchQuery(SearchApiWrapper $searchAPI)
	{
		$this->_searchAPI = $searchAPI;
		$searchAPI->setExcludedPhrases($this->_excludedPhrases);
		$searchAPI->setWeightFactors($this->_weightFactors);
		$searchAPI->useTemporary($this->_createTemporary);
		$searchAPI->setSearchArray($this->_searchArray);

		return $searchAPI->searchQuery(
			$this->searchWords(),
			$this->_excludedIndexWords,
			$this->_participants,
			$this->_searchAPI
		);
	}

	/**
	 * Returns the number of results obtained from the query.
	 *
	 * @return int
	 */
	public function getNumResults()
	{
		return $this->_searchAPI->getNumResults();
	}

	public function getParticipants()
	{
		return $this->_participants;
	}
}
