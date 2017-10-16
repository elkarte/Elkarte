<?php

/**
 * This search class is used when a fulltext index is used (mysql only)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

namespace ElkArte\Search\API;

/**
 * SearchAPI-Fulltext.class.php, Fulltext API, used when an SQL fulltext index is used
 *
 * @package Search
 */
class Fulltext extends SearchAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 * @var string
	 */
	public $version_compatible = 'ElkArte 1.1';

	/**
	 * This won't work with versions of ElkArte less than this.
	 * @var string
	 */
	public $min_elk_version = 'ElkArte 1.0';

	/**
	 * Is it supported?
	 * @var boolean
	 */
	public $is_supported = true;

	/**
	 * What words are banned?
	 * @var array
	 */
	protected $bannedWords = array();

	/**
	 * What is the minimum word length?
	 * @var int
	 */
	protected $min_word_length = 4;

	/**
	 * What databases support the fulltext index?
	 * @var array
	 */
	protected $supported_databases = array('MySQL');

	/**
	 * Fulltext::__construct()
	 */
	public function __construct()
	{
		global $modSettings;

		// Is this database supported?
		if (!in_array(DB_TYPE, $this->supported_databases))
		{
			$this->is_supported = false;

			return;
		}

		$this->bannedWords = empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']);
		$this->min_word_length = $this->_getMinWordLength();
	}

	/**
	 * Fulltext::_getMinWordLength()
	 *
	 * What is the minimum word length full text supports?
	 */
	protected function _getMinWordLength()
	{
		$db = database();

		// Need some search specific database tricks
		$db_search = db_search();

		// Try to determine the minimum number of letters for a fulltext search.
		$request = $db_search->search_query('max_fulltext_length', '
			SHOW VARIABLES
			LIKE {string:fulltext_minimum_word_length}',
			array(
				'fulltext_minimum_word_length' => 'ft_min_word_len',
			)
		);
		if ($request !== false && $db->num_rows($request) == 1)
		{
			list (, $min_word_length) = $db->fetch_row($request);
			$db->free_result($request);
		}
		// 4 is the MySQL default...
		else
		{
			$min_word_length = 4;
		}

		return $min_word_length;
	}

	/**
	 * Callback function for usort used to sort the fulltext results.
	 *
	 * - The order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 *
	 * @return int An integer indicating how the words should be sorted (-1, 0 1)
	 */
	public function searchSort($a, $b)
	{
		$x = \Util::strlen($a) - (in_array($a, $this->_excludedWords) ? 1000 : 0);
		$y = \Util::strlen($b) - (in_array($b, $this->_excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * Fulltext::prepareIndexes()
	 *
	 * Do we have to do some work with the words we are searching for to prepare them?
	 *
	 * @param string $word A word to index
	 * @param mixed[] $wordsSearch The Search words
	 * @param string[] $wordsExclude Words to exclude
	 * @param boolean $isExcluded If the $wordsSearch are those to exclude
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		global $modSettings;

		$subwords = text2words($word, null, false);

		if (empty($modSettings['search_force_index']))
		{
			// A boolean capable search engine and not forced to only use an index, we may use a non indexed search
			// this is harder on the server so we are restrictive here
			if (count($subwords) > 1 && preg_match('~[.:@$]~', $word))
			{
				// Using special characters that a full index would ignore and the remaining words are
				// short which would also be ignored
				if ((\Util::strlen(current($subwords)) < $this->min_word_length) && (\Util::strlen(next($subwords)) < $this->min_word_length))
				{
					$wordsSearch['words'][] = trim($word, '/*- ');
					$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
				}
			}
			elseif (\Util::strlen(trim($word, '/*- ')) < $this->min_word_length)
			{
				// Short words have feelings too
				$wordsSearch['words'][] = trim($word, '/*- ');
				$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
			}
		}

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded)
		{
			$wordsExclude[] = $fulltextWord;
		}
	}

	/**
	 * Fulltext::indexedWordQuery()
	 *
	 * Search for indexed words.
	 *
	 * @param mixed[] $words Words to index
	 * @param mixed[] $search_data
	 */
	public function indexedWordQuery($words, $search_data)
	{
		global $modSettings;

		$db = database();

		$db_search = db_search();

		$query_select = array(
			'id_msg' => 'm.id_msg',
		);

		$query_where = array();
		$query_params = $search_data['params'];

		if ($query_params['id_search'])
		{
			$query_select['id_search'] = '{int:id_search}';
		}

		$count = 0;
		if (empty($modSettings['search_simple_fulltext']))
		{
			foreach ($words['words'] as $regularWord)
			{
				$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:complex_body_' . $count . '}';
				$query_params['complex_body_' . ($count++)] = $this->prepareWord($regularWord, $search_data['no_regexp']);
			}
		}

		// Just by a specific user
		if ($query_params['user_query'])
		{
			$query_where[] = '{raw:user_query}';
		}

		// Just in specific boards
		if ($query_params['board_query'])
		{
			$query_where[] = 'm.id_board {raw:board_query}';
		}

		// Just search in a specific topic
		if ($query_params['topic'])
		{
			$query_where[] = 'm.id_topic = {int:topic}';
		}

		// Just in a range of messages (age)
		if ($query_params['min_msg_id'])
		{
			$query_where[] = 'm.id_msg >= {int:min_msg_id}';
		}

		if ($query_params['max_msg_id'])
		{
			$query_where[] = 'm.id_msg <= {int:max_msg_id}';
		}

		$count = 0;
		if (!empty($query_params['excluded_phrases']) && empty($modSettings['search_force_index']))
		{
			foreach ($query_params['excluded_phrases'] as $phrase)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:exclude_subject_phrase_' . $count . '}';
				$query_params['exclude_subject_phrase_' . ($count++)] = $this->prepareWord($phrase, $search_data['no_regexp']);
			}
		}

		$count = 0;
		if (!empty($query_params['excluded_subject_words']) && empty($modSettings['search_force_index']))
		{
			foreach ($query_params['excluded_subject_words'] as $excludedWord)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:exclude_subject_words_' . $count . '}';
				$query_params['exclude_subject_words_' . ($count++)] = $this->prepareWord($excludedWord, $search_data['no_regexp']);
			}
		}

		if (!empty($modSettings['search_simple_fulltext']))
		{
			$query_where[] = 'MATCH (body) AGAINST ({string:body_match})';
			$query_params['body_match'] = implode(' ', array_diff($words['indexed_words'], $query_params['excluded_index_words']));
		}
		else
		{
			$query_params['boolean_match'] = '';

			// Remove any indexed words that are used in the complex body search terms
			$words['indexed_words'] = array_diff($words['indexed_words'], $words['complex_words']);

			foreach ($words['indexed_words'] as $fulltextWord)
				$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '-' : '+') . $fulltextWord . ' ';
			$query_params['boolean_match'] = substr($query_params['boolean_match'], 0, -1);

			// If we have bool terms to search, add them in
			if ($query_params['boolean_match'])
			{
				$query_where[] = 'MATCH (body) AGAINST ({string:boolean_match} IN BOOLEAN MODE)';
			}
		}

		$ignoreRequest = $db_search->search_query('insert_into_log_messages_fulltext', ($db->support_ignore() ? ('
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params
		);

		return $ignoreRequest;
	}
}
