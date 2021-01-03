<?php

/**
 * Standard non full index, non custom index search
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

namespace ElkArte\Search\API;

use ElkArte\Search\Cache\Session;

/**
 * SearchAPI-Standard.class.php, Standard non full index, non custom index search
 *
 * @package Search
 */
class Standard extends AbstractAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 *
	 * @var string
	 */
	public $version_compatible = 'ElkArte 2.0 dev';

	/**
	 * This won't work with versions of ElkArte less than this.
	 *
	 * @var string
	 */
	public $min_elk_version = 'ElkArte 1.0 Beta';

	/**
	 * Standard search is supported by default.
	 *
	 * @var bool
	 */
	public $is_supported = true;

	/**
	 *
	 * @var object
	 */
	protected $_search_cache = null;

	/**
	 *
	 * @var int
	 */
	protected $_num_results = 0;

	/**
	 * Wrapper for searchQuery of the SearchAPI
	 *
	 * @param string[] $search_words
	 * @param string[] $excluded_words
	 * @param bool[] $participants
	 * @param string[] $search_results
	 *
	 * @return mixed[]
	 * @throws \Exception
	 */
	public function searchQuery($search_words, $excluded_words, &$participants, &$search_results)
	{
		global $context, $modSettings;

		$this->_search_cache = new Session();
		$this->_searchWords = $search_words;
		$search_id = 0;

		if ($this->_search_cache->existsWithParams($context['params']) === false)
		{
			$search_id = $this->_search_cache->increaseId($modSettings['search_pointer'] ?? 0);
			// Store the new id right off.
			updateSettings([
				'search_pointer' => $search_id
			]);

			// Clear the previous cache of the final results cache.
			$this->clearCacheResults($search_id);

			if ($this->_searchParams['subject_only'])
			{
				$num_res = $this->getSubjectResults(
					$search_id,
					$search_words, $excluded_words
				);
			}
			else
			{
				$num_res = $this->getResults(
					$search_id
				);
				if (empty($num_res))
				{
					throw new \Exception('query_not_specific_enough');
				}
			}

			$this->_search_cache->setNumResults($num_res);
		}

		$topics = array();
		// *** Retrieve the results to be shown on the page
		$participants = $this->addRelevance(
			$topics,
			$search_id,
			(int) $_REQUEST['start'],
			$modSettings['search_results_per_page']
		);
		$this->_num_results = $this->_search_cache->getNumResults();

		return $topics;
	}

	/**
	 * Delete logs of previous searches
	 *
	 * @param int $id_search - the id of the search to delete from logs
	 */
	public function clearCacheResults($id_search)
	{
		$this->_db_search->search_query('delete_log_search_results', '
			DELETE FROM {db_prefix}log_search_results
			WHERE id_search = {int:search_id}',
			array(
				'search_id' => $id_search,
			)
		);
	}

	/**
	 * Grabs results when the search is performed only within the subject
	 *
	 * @param int $id_search - the id of the search
	 *
	 * @return int - number of results otherwise
	 */
	protected function getSubjectResults($id_search, $search_words, $excluded_words)
	{
		global $modSettings;

		$numSubjectResults = 0;
		// We do this to try and avoid duplicate keys on databases not supporting INSERT IGNORE.
		foreach ($search_words as $words)
		{
			$subject_query_params = array();
			$subject_query = array(
				'from' => '{db_prefix}topics AS t',
				'inner_join' => array(),
				'left_join' => array(),
				'where' => array(),
			);

			if ($modSettings['postmod_active'])
			{
				$subject_query['where'][] = 't.approved = {int:is_approved}';
			}

			$numTables = 0;
			$prev_join = 0;
			$numSubjectResults = 0;
			foreach ($words['subject_words'] as $subjectWord)
			{
				$numTables++;
				if (in_array($subjectWord, $excluded_words))
				{
					$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? '{ilike} {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
					$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
				}
				else
				{
					$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
					$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? '{ilike} {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');
					$prev_join = $numTables;
				}

				$subject_query_params['subject_words_' . $numTables] = $subjectWord;
				$subject_query_params['subject_words_' . $numTables . '_wild'] = '%' . $subjectWord . '%';
			}

			if (!empty($this->_searchParams->_userQuery))
			{
				$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_topic = t.id_topic)';
				$subject_query['where'][] = $this->_searchParams->_userQuery;
			}

			if (!empty($this->_searchParams['topic']))
			{
				$subject_query['where'][] = 't.id_topic = ' . $this->_searchParams['topic'];
			}

			if (!empty($this->_searchParams->_minMsgID))
			{
				$subject_query['where'][] = 't.id_first_msg >= ' . $this->_searchParams->_minMsgID;
			}

			if (!empty($this->_searchParams->_maxMsgID))
			{
				$subject_query['where'][] = 't.id_last_msg <= ' . $this->_searchParams->_maxMsgID;
			}

			if (!empty($this->_searchParams->_boardQuery))
			{
				$subject_query['where'][] = 't.id_board ' . $this->_searchParams->_boardQuery;
			}

			if (!empty($this->_excludedPhrases))
			{
				$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';

				$count = 0;
				foreach ($this->_excludedPhrases as $phrase)
				{
					$subject_query['where'][] = 'm.subject ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? '{not_ilike}' : '{not_rlike}') . ' {string:excluded_phrases_' . $count . '}';
					$subject_query_params['excluded_phrases_' . ($count++)] = $this->prepareWord($phrase, $this->noRegexp());
				}
			}

			// Build the search query
			$subject_query['select'] = array(
				'id_search' => '{int:id_search}',
				'id_topic' => 't.id_topic',
				'relevance' => $this->_build_relevance(),
				'id_msg' => empty($this->_searchParams->_userQuery) ? 't.id_first_msg' : 'm.id_msg',
				'num_matches' => 1,
			);

			$subject_query['parameters'] = array_merge($subject_query_params, array(
				'id_search' => $id_search,
				'min_msg' => $this->_searchParams->_minMsg,
				'recent_message' => $this->_searchParams->_recentMsg,
				'huge_topic_posts' => $this->config->humungousTopicPosts,
				'is_approved' => 1,
				'limit' => empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] - $numSubjectResults,
			));

			call_integration_hook('integrate_subject_only_search_query', array(&$subject_query, &$subject_query_params));

			$numSubjectResults += $this->_build_search_results_log($subject_query);

			if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
			{
				break;
			}
		}

		return $numSubjectResults;
	}

	/**
	 * If the query uses regexp or not
	 *
	 * @return bool
	 */
	protected function noRegexp()
	{
		return $this->_searchArray->getNoRegexp();
	}

	/**
	 * Build the search relevance query
	 *
	 * @param null|int[] $factors - is factors are specified that array will
	 * be used to build the relevance value, otherwise the function will use
	 * $this->_weight_factors
	 *
	 * @return string
	 */
	private function _build_relevance($factors = null)
	{
		$relevance = '1000 * (';

		if ($factors !== null && is_array($factors))
		{
			$weight_total = 0;
			foreach ($factors as $type => $value)
			{
				$relevance .= $this->_weight[$type];
				if (!empty($value['search']))
				{
					$relevance .= ' * ' . $value['search'];
				}

				$relevance .= ' + ';
				$weight_total += $this->_weight[$type];
			}
		}
		else
		{
			$weight_total = $this->_weight_total;
			foreach ($this->_weight_factors as $type => $value)
			{
				if (isset($value['results']))
				{
					$relevance .= $this->_weight[$type];
					if (!empty($value['results']))
					{
						$relevance .= ' * ' . $value['results'];
					}

					$relevance .= ' + ';
				}
			}
		}

		$relevance = substr($relevance, 0, -3) . ') / ' . $weight_total . ' AS relevance';

		return $relevance;
	}

	/**
	 * Inserts the data into log_search_results
	 *
	 * @param mixed[] $main_query - An array holding all the query parts.
	 *   Structure:
	 *        'select' => string[] - the select columns
	 *        'from' => string - the table for the FROM clause
	 *        'inner_join' => string[] - any INNER JOIN
	 *        'left_join' => string[] - any LEFT JOIN
	 *        'where' => string[] - the conditions
	 *        'group_by' => string[] - the fields to group by
	 *        'parameters' => mixed[] - any parameter required by the query
	 * @param string $query_identifier - a string to identify the query
	 * @param bool $use_old_ids - if true the topic ids retrieved by a previous
	 * call to this function will be used to identify duplicates
	 *
	 * @return int - the number of rows affected by the query
	 */
	private function _build_search_results_log($main_query, $query_identifier = '', $use_old_ids = false)
	{
		static $usedIDs;

		$ignoreRequest = $this->_db_search->search_query($query_identifier, ($this->_db->support_ignore() ? ('
			INSERT IGNORE INTO {db_prefix}log_search_results
				(' . implode(', ', array_keys($main_query['select'])) . ')') : '') . '
			SELECT
				' . implode(',
				', $main_query['select']) . '
			FROM ' . $main_query['from'] . (!empty($main_query['inner_join']) ? '
				INNER JOIN ' . implode('
				INNER JOIN ', array_unique($main_query['inner_join'])) : '') . (!empty($main_query['left_join']) ? '
				LEFT JOIN ' . implode('
				LEFT JOIN ', array_unique($main_query['left_join'])) : '') . (!empty($main_query['where']) ? '
			WHERE ' : '') . implode('
				AND ', array_unique($main_query['where'])) . (!empty($main_query['group_by']) ? '
			GROUP BY ' . implode(', ', array_unique($main_query['group_by'])) : '') . (!empty($main_query['parameters']['limit']) ? '
			LIMIT {int:limit}' : ''),
			$main_query['parameters']
		);

		// If the database doesn't support IGNORE to make this fast we need to do some tracking.
		if (!$this->_db->support_ignore())
		{
			$inserts = array();

			while (($row = $ignoreRequest->fetch_assoc()))
			{
				// No duplicates!
				if ($use_old_ids)
				{
					if (isset($usedIDs[$row['id_topic']]))
					{
						continue;
					}
				}
				elseif (isset($inserts[$row['id_topic']]))
				{
					continue;
				}

				$usedIDs[$row['id_topic']] = true;
				foreach ($row as $key => $value)
				{
					$inserts[$row['id_topic']][] = (int) $row[$key];
				}
			}
			$ignoreRequest->free_result();

			// Now put them in!
			if (!empty($inserts))
			{
				$query_columns = array();
				foreach ($main_query['select'] as $k => $v)
				{
					$query_columns[$k] = 'int';
				}

				$this->_db->insert('',
					'{db_prefix}log_search_results',
					$query_columns,
					$inserts,
					array('id_search', 'id_topic')
				);
			}
			$num_results = count($inserts);
		}
		else
		{
			$num_results = $ignoreRequest->affected_rows();
		}

		return $num_results;
	}

	/**
	 * Grabs results when the search is performed in subjects and bodies
	 *
	 * @param int $id_search - the id of the search
	 *
	 * @return bool|int - boolean (false) in case of errors, number of results otherwise
	 */
	public function getResults($id_search)
	{
		global $modSettings;

		$num_results = 0;

		$main_query = array(
			'select' => array(
				'id_search' => $id_search,
				'relevance' => '0',
			),
			'weights' => array(),
			'from' => '{db_prefix}topics AS t',
			'inner_join' => array(
				'{db_prefix}messages AS m ON (m.id_topic = t.id_topic)'
			),
			'left_join' => array(),
			'where' => array(),
			'group_by' => array(),
			'parameters' => array(
				'min_msg' => $this->_searchParams->_minMsg,
				'recent_message' => $this->_searchParams->_recentMsg,
				'huge_topic_posts' => $this->config->humungousTopicPosts,
				'is_approved' => 1,
				'limit' => $modSettings['search_max_results'],
			),
		);

		if (empty($this->_searchParams['topic']) && empty($this->_searchParams['show_complete']))
		{
			$main_query['select']['id_topic'] = 't.id_topic';
			$main_query['select']['id_msg'] = 'MAX(m.id_msg) AS id_msg';
			$main_query['select']['num_matches'] = 'COUNT(*) AS num_matches';
			$main_query['weights'] = $this->_weight_factors;
			$main_query['group_by'][] = 't.id_topic';
		}
		else
		{
			// This is outrageous!
			$main_query['select']['id_topic'] = 'm.id_msg AS id_topic';
			$main_query['select']['id_msg'] = 'm.id_msg';
			$main_query['select']['num_matches'] = '1 AS num_matches';

			$main_query['weights'] = array(
				'age' => array(
					'search' => '((m.id_msg - t.id_first_msg) / CASE WHEN t.id_last_msg = t.id_first_msg THEN 1 ELSE t.id_last_msg - t.id_first_msg END)',
				),
				'first_message' => array(
					'search' => 'CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END',
				),
			);

			if (!empty($this->_searchParams['topic']))
			{
				$main_query['where'][] = 't.id_topic = {int:topic}';
				$main_query['parameters']['topic'] = $this->_searchParams->topic;
			}

			if (!empty($this->_searchParams['show_complete']))
			{
				$main_query['group_by'][] = 'm.id_msg, t.id_first_msg, t.id_last_msg';
			}
		}

		// *** Get the subject results.
		$numSubjectResults = $this->_log_search_subjects($id_search);

		if ($numSubjectResults !== 0)
		{
			$main_query['weights']['subject']['search'] = 'CASE WHEN MAX(lst.id_topic) IS NULL THEN 0 ELSE 1 END';
			$main_query['left_join'][] = '{db_prefix}' . ($this->_createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (' . ($this->_createTemporary ? '' : 'lst.id_search = {int:id_search} AND ') . 'lst.id_topic = t.id_topic)';
			if (!$this->_createTemporary)
			{
				$main_query['parameters']['id_search'] = $id_search;
			}
		}

		// We building an index?
		if ($this->useWordIndex())
		{
			$indexedResults = $this->_prepare_word_index($id_search);

			if (empty($indexedResults) && empty($numSubjectResults) && !empty($modSettings['search_force_index']))
			{
				return false;
			}
			elseif (!empty($indexedResults))
			{
				$main_query['inner_join'][] = '{db_prefix}' . ($this->_createTemporary ? 'tmp_' : '') . 'log_search_messages AS lsm ON (lsm.id_msg = m.id_msg)';

				if (!$this->_createTemporary)
				{
					$main_query['where'][] = 'lsm.id_search = {int:id_search}';
					$main_query['parameters']['id_search'] = $id_search;
				}
			}
		}
		// Not using an index? All conditions have to be carried over.
		else
		{
			$orWhere = array();
			$count = 0;
			$excludedWords = $this->_searchArray->getExcludedWords();
			foreach ($this->_searchWords as $words)
			{
				$where = array();
				foreach ($words['all_words'] as $regularWord)
				{
					$where[] = 'm.body' . (in_array($regularWord, $excludedWords) ? ' {not_' : '{') . (empty($modSettings['search_match_words']) || $this->noRegexp() ? 'ilike} ' : 'rlike} ') . '{string:all_word_body_' . $count . '}';
					if (in_array($regularWord, $excludedWords))
					{
						$where[] = 'm.subject ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? ' {not_ilike} ' : ' {not_rlike} ') . '{string:all_word_body_' . $count . '}';
					}
					$main_query['parameters']['all_word_body_' . ($count++)] = $this->prepareWord($regularWord, $this->noRegexp());
				}

				if (!empty($where))
				{
					$orWhere[] = count($where) > 1 ? '(' . implode(' AND ', $where) . ')' : $where[0];
				}
			}

			if (!empty($orWhere))
			{
				$main_query['where'][] = count($orWhere) > 1 ? '(' . implode(' OR ', $orWhere) . ')' : $orWhere[0];
			}

			if (!empty($this->_searchParams->_userQuery))
			{
				$main_query['where'][] = '{raw:user_query}';
				$main_query['parameters']['user_query'] = $this->_searchParams->_userQuery;
			}

			if (!empty($this->_searchParams['topic']))
			{
				$main_query['where'][] = 'm.id_topic = {int:topic}';
				$main_query['parameters']['topic'] = $this->_searchParams->topic;
			}

			if (!empty($this->_searchParams->_minMsgID))
			{
				$main_query['where'][] = 'm.id_msg >= {int:min_msg_id}';
				$main_query['parameters']['min_msg_id'] = $this->_searchParams->_minMsgID;
			}

			if (!empty($this->_searchParams->_maxMsgID))
			{
				$main_query['where'][] = 'm.id_msg <= {int:max_msg_id}';
				$main_query['parameters']['max_msg_id'] = $this->_searchParams->_maxMsgID;
			}

			if (!empty($this->_searchParams->_boardQuery))
			{
				$main_query['where'][] = 'm.id_board {raw:board_query}';
				$main_query['parameters']['board_query'] = $this->_searchParams->_boardQuery;
			}
		}
		call_integration_hook('integrate_main_search_query', array(&$main_query));

		// Did we either get some indexed results, or otherwise did not do an indexed query?
		if (!empty($indexedResults) || !$this->useWordIndex())
		{
			$main_query['select']['relevance'] = $this->_build_relevance($main_query['weights']);
			$num_results += $this->_build_search_results_log($main_query);
		}

		// Insert subject-only matches.
		if ($num_results < $modSettings['search_max_results'] && $numSubjectResults !== 0)
		{
			$subject_query = array(
				'select' => array(
					'id_search' => '{int:id_search}',
					'id_topic' => 't.id_topic',
					'relevance' => $this->_build_relevance(),
					'id_msg' => 't.id_first_msg',
					'num_matches' => 1,
				),
				'from' => '{db_prefix}topics AS t',
				'inner_join' => array(
					'{db_prefix}' . ($this->_createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (lst.id_topic = t.id_topic)'
				),
				'where' => array(
					$this->_createTemporary ? '1=1' : 'lst.id_search = {int:id_search}',
				),
				'parameters' => array(
					'id_search' => $id_search,
					'min_msg' => $this->_searchParams->_minMsg,
					'recent_message' => $this->_searchParams->_recentMsg,
					'huge_topic_posts' => $this->config->humungousTopicPosts,
					'limit' => empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] - $num_results,
				),
			);

			$num_results += $this->_build_search_results_log($subject_query, 'insert_log_search_results_sub_only', true);
		}
		elseif ($num_results === -1)
		{
			$num_results = 0;
		}

		return $num_results;
	}

	/**
	 * If searching in topics only (?), inserts results in log_search_topics
	 *
	 * @param int $id_search - the id of the search to delete from logs
	 *
	 * @return int - the number of search results
	 */
	private function _log_search_subjects($id_search)
	{
		global $modSettings;

		if (!empty($this->_searchParams['topic']))
		{
			return 0;
		}

		$inserts = array();
		$numSubjectResults = 0;

		// Clean up some previous cache.
		if (!$this->_createTemporary)
		{
			$this->_db_search->search_query('delete_log_search_topics', '
				DELETE FROM {db_prefix}log_search_topics
				WHERE id_search = {int:search_id}',
				array(
					'search_id' => $id_search,
				)
			);
		}

		foreach ($this->_searchWords as $words)
		{
			$subject_query = array(
				'from' => '{db_prefix}topics AS t',
				'inner_join' => array(),
				'left_join' => array(),
				'where' => array(),
				'params' => array(),
			);

			$numTables = 0;
			$prev_join = 0;
			$count = 0;
			foreach ($words['subject_words'] as $subjectWord)
			{
				$numTables++;
				if (in_array($subjectWord, $this->_excludedSubjectWords))
				{
					$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
					$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? '{ilike} {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
					$subject_query['params']['subject_not_' . $count] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

					$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
					$subject_query['where'][] = 'm.body ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? ' {not_ilike} ' : ' {not_rlike} ') . '{string:body_not_' . $count . '}';
					$subject_query['params']['body_not_' . ($count++)] = $this->prepareWord($subjectWord, $this->noRegexp());
				}
				else
				{
					$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
					$subject_query['where'][] = 'subj' . $numTables . '.word {ilike} {string:subject_like_' . $count . '}';
					$subject_query['params']['subject_like_' . ($count++)] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;
					$prev_join = $numTables;
				}
			}

			if (!empty($this->_searchParams->_userQuery))
			{
				$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
				$subject_query['where'][] = '{raw:user_query}';
				$subject_query['params']['user_query'] = $this->_searchParams->_userQuery;
			}

			if (!empty($this->_searchParams['topic']))
			{
				$subject_query['where'][] = 't.id_topic = {int:topic}';
				$subject_query['params']['topic'] = $this->_searchParams->topic;
			}

			if (!empty($this->_searchParams->_minMsgID))
			{
				$subject_query['where'][] = 't.id_first_msg >= {int:min_msg_id}';
				$subject_query['params']['min_msg_id'] = $this->_searchParams->_minMsgID;
			}

			if (!empty($this->_searchParams->_maxMsgID))
			{
				$subject_query['where'][] = 't.id_last_msg <= {int:max_msg_id}';
				$subject_query['params']['max_msg_id'] = $this->_searchParams->_maxMsgID;
			}

			if (!empty($this->_searchParams->_boardQuery))
			{
				$subject_query['where'][] = 't.id_board {raw:board_query}';
				$subject_query['params']['board_query'] = $this->_searchParams->_boardQuery;
			}

			if (!empty($this->_excludedPhrases))
			{
				$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
				$count = 0;
				foreach ($this->_excludedPhrases as $phrase)
				{
					$subject_query['where'][] = 'm.subject ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? '{not_ilike}' : '{not_rlike}') . ' {string:exclude_phrase_' . $count . '}';
					$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? '{not_ilike}' : '{not_rlike}') . ' {string:exclude_phrase_' . $count . '}';
					$subject_query['params']['exclude_phrase_' . ($count++)] = $this->prepareWord($phrase, $this->noRegexp());
				}
			}

			call_integration_hook('integrate_subject_search_query', array(&$subject_query));

			// Nothing to search for?
			if (empty($subject_query['where']))
			{
				continue;
			}

			$ignoreRequest = $this->_db_search->search_query('', ($this->_db->support_ignore() ? ('
				INSERT IGNORE INTO {db_prefix}' . ($this->_createTemporary ? 'tmp_' : '') . 'log_search_topics
					(' . ($this->_createTemporary ? '' : 'id_search, ') . 'id_topic)') : '') . '
				SELECT ' . ($this->_createTemporary ? '' : $id_search . ', ') . 't.id_topic
				FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
					INNER JOIN ' . implode('
					INNER JOIN ', array_unique($subject_query['inner_join']))) . (empty($subject_query['left_join']) ? '' : '
					LEFT JOIN ' . implode('
					LEFT JOIN ', array_unique($subject_query['left_join']))) . '
				WHERE ' . implode('
					AND ', array_unique($subject_query['where'])) . (empty($modSettings['search_max_results']) ? '' : '
				LIMIT ' . ($modSettings['search_max_results'] - $numSubjectResults)),
				$subject_query['params']
			);

			// Don't do INSERT IGNORE? Manually fix this up!
			if (!$this->_db->support_ignore())
			{
				while (($row = $ignoreRequest->fetch_row()))
				{
					$ind = $this->_createTemporary ? 0 : 1;

					// No duplicates!
					if (isset($inserts[$row[$ind]]))
					{
						continue;
					}

					$inserts[$row[$ind]] = $row;
				}
				$ignoreRequest->free_result();
				$numSubjectResults = count($inserts);
			}
			else
			{
				$numSubjectResults += $ignoreRequest->affected_rows();
			}

			if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
			{
				break;
			}
		}

		// Got some non-MySQL data to plonk in?
		if (!empty($inserts))
		{
			$this->_db->insert('',
				('{db_prefix}' . ($this->_createTemporary ? 'tmp_' : '') . 'log_search_topics'),
				$this->_createTemporary ? array('id_topic' => 'int') : array('id_search' => 'int', 'id_topic' => 'int'),
				$inserts,
				$this->_createTemporary ? array('id_topic') : array('id_search', 'id_topic')
			);
		}

		return $numSubjectResults;
	}

	public function useWordIndex()
	{
		return false;
	}

	/**
	 * Populates log_search_messages
	 *
	 * @param int $id_search - the id of the search to delete from logs
	 *
	 * @return int - the number of indexed results
	 */
	private function _prepare_word_index($id_search)
	{
		$indexedResults = 0;
		$inserts = array();

		// Clear, all clear!
		if (!$this->_createTemporary)
		{
			$this->_db_search->search_query('delete_log_search_messages', '
				DELETE FROM {db_prefix}log_search_messages
				WHERE id_search = {int:id_search}',
				array(
					'id_search' => $id_search,
				)
			);
		}
		$excludedWords = $this->_searchArray->getExcludedWords();

		foreach ($this->_searchWords as $words)
		{
			// Search for this word, assuming we have some words!
			if (!empty($words['indexed_words']))
			{
				// Variables required for the search.
				$search_data = array(
					'insert_into' => ($this->_createTemporary ? 'tmp_' : '') . 'log_search_messages',
					'no_regexp' => $this->noRegexp(),
					'max_results' => $this->config->maxMessageResults,
					'indexed_results' => $indexedResults,
					'params' => array(
						'id_search' => !$this->_createTemporary ? $id_search : 0,
						'excluded_words' => $excludedWords,
						'user_query' => !empty($this->_searchParams->_userQuery) ? $this->_searchParams->_userQuery : '',
						'board_query' => !empty($this->_searchParams->_boardQuery) ? $this->_searchParams->_boardQuery : '',
						'topic' => (int) $this->_searchParams->topic,
						'min_msg_id' => (int) $this->_searchParams->_minMsgID,
						'max_msg_id' => (int) $this->_searchParams->_maxMsgID,
						'excluded_phrases' => $this->_excludedPhrases,
						'excluded_index_words' => $this->_excludedIndexWords,
						'excluded_subject_words' => $this->_excludedSubjectWords,
					),
				);

				$ignoreRequest = $this->indexedWordQuery($words, $search_data);

				if (!$this->_db->support_ignore())
				{
					while (($row = $ignoreRequest->fetch_row()))
					{
						// No duplicates!
						if (isset($inserts[$row[0]]))
						{
							continue;
						}

						$inserts[$row[0]] = $row;
					}
					$ignoreRequest->free_result();
					$indexedResults = count($inserts);
				}
				else
				{
					$indexedResults += $ignoreRequest->affected_rows();
				}

				if (!empty($this->config->maxMessageResults) && $indexedResults >= $this->config->maxMessageResults)
				{
					break;
				}
			}
		}

		// More non-MySQL stuff needed?
		if (!empty($inserts))
		{
			$this->_db->insert('',
				'{db_prefix}' . ($this->_createTemporary ? 'tmp_' : '') . 'log_search_messages',
				$this->_createTemporary ? array('id_msg' => 'int') : array('id_msg' => 'int', 'id_search' => 'int'),
				$inserts,
				$this->_createTemporary ? array('id_msg') : array('id_msg', 'id_search')
			);
		}

		return $indexedResults;
	}

	/**
	 * {@inheritdoc }
	 */
	public function indexedWordQuery($words, $search_data)
	{
	}

	/**
	 * Determines and add the relevance to the results
	 *
	 * @param mixed[] $topics - The search results (passed by reference)
	 * @param int $id_search - the id of the search
	 * @param int $start - Results are shown starting from here
	 * @param int $limit - No more results than this
	 *
	 * @return bool[]
	 */
	public function addRelevance(&$topics, $id_search, $start, $limit)
	{
		// *** Retrieve the results to be shown on the page
		$participants = array();
		$request = $this->_db_search->search_query('', '
			SELECT ' . (empty($this->_searchParams['topic']) ? 'lsr.id_topic' : $this->_searchParams->topic . ' AS id_topic') . ', lsr.id_msg, lsr.relevance, lsr.num_matches
			FROM {db_prefix}log_search_results AS lsr' . ($this->_searchParams->sort === 'num_replies' ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = lsr.id_topic)' : '') . '
			WHERE lsr.id_search = {int:id_search}
			ORDER BY {raw:sort} {raw:sort_dir}
			LIMIT {int:start}, {int:limit}',
			array(
				'id_search' => $id_search,
				'sort' => $this->_searchParams->sort,
				'sort_dir' => $this->_searchParams->sort_dir,
				'start' => $start,
				'limit' => $limit,
			)
		);
		while (($row = $request->fetch_assoc()))
		{
			$topics[$row['id_msg']] = array(
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => array(),
			);
			// By default they didn't participate in the topic!
			$participants[$row['id_topic']] = false;
		}
		$request->free_result();

		return $participants;
	}
}
