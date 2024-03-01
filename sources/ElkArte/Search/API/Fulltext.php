<?php

/**
 * This search class is used when a fulltext index is used (mysql only)
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

use ElkArte\Database\AbstractResult;
use ElkArte\Helper\Util;

/**
 * SearchAPI-Fulltext.class.php, Fulltext API, used when an SQL fulltext index is used
 *
 * @package Search
 */
class Fulltext extends Standard
{
	/** @var string This is the last version of ElkArte that this was tested on, to protect against API changes. */
	public $version_compatible = 'ElkArte 2.0 dev';

	/** @var string This won't work with versions of ElkArte less than this. */
	public $min_elk_version = 'ElkArte 1.0';

	/** @var bool Is it supported? */
	public $is_supported = true;

	/** @var array What words are banned? */
	protected $bannedWords = [];

	/** @var int What is the minimum word length? */
	protected $min_word_length = 4;

	/** @var array What databases support the fulltext index? */
	protected $supported_databases = array('MySQL');

	/**
	 * Fulltext::__construct()
	 */
	public function __construct($config, $searchParams)
	{
		global $modSettings;

		parent::__construct($config, $searchParams);

		// Is this database supported?
		if (!in_array($this->_db->title(), $this->supported_databases, true))
		{
			$this->is_supported = false;

			return;
		}

		$this->bannedWords = empty($modSettings['search_banned_words']) ? [] : explode(',', $modSettings['search_banned_words']);
		$this->min_word_length = $this->_getMinWordLength();
	}

	/**
	 * Fulltext::_getMinWordLength()
	 *
	 * What is the minimum word length full text supports?
	 */
	protected function _getMinWordLength()
	{
		// Need some search specific database tricks
		$db_search = db_search();

		// Try to determine the minimum number of letters for a fulltext search.
		$request = $db_search->search_query('', '
			SHOW VARIABLES
			LIKE {string:fulltext_minimum_word_length}',
			array(
				'fulltext_minimum_word_length' => 'ft_min_word_len',
			)
		);
		if ($request !== false && $request->num_rows() === 1)
		{
			[, $min_word_length] = $request->fetch_row();
			$request->free_result();
		}
		// 4 is the MySQL default...
		else
		{
			$min_word_length = 4;
		}

		return $min_word_length;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
	{
		global $modSettings;

		$subwords = text2words($word, false);

		if (empty($modSettings['search_force_index']))
		{
			// A boolean capable search engine and not forced to only use an index, we may use a non indexed search
			// this is harder on the server so we are restrictive here
			if (count($subwords) > 1 && preg_match('~[.:@$]~', $word))
			{
				// Using special characters that a full index would ignore and the remaining words are
				// short which would also be ignored
				if ((Util::strlen(current($subwords)) < $this->min_word_length) && (Util::strlen(next($subwords)) < $this->min_word_length))
				{
					$wordsSearch['words'][] = trim($word, '/*- ');
					$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
				}
			}
			elseif (Util::strlen(trim($word, '/*- ')) < $this->min_word_length)
			{
				// Short words have feelings too
				$wordsSearch['words'][] = trim($word, '/*- ');
				$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
			}
		}

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded !== false)
		{
			$wordsExclude[] = $fulltextWord;
		}
	}

	/**
	 * Fulltext::useWordIndex()
	 *
	 * Returns a boolean value indicating whether the word index should be used for searching.
	 *
	 * @return boolean Returns true if the word index should be used, otherwise false.
	 */
	public function useWordIndex()
	{
		return true;
	}

	/**
	 * Fulltext::indexedWordQuery()
	 *
	 * Search for indexed words.
	 *
	 * @param array $words Words to index
	 * @param array $search_data
	 *
	 * @return AbstractResult|boolean
	 */
	public function indexedWordQuery($words, $search_data)
	{
		global $modSettings;

		$db = database();

		$db_search = db_search();

		$query_select = [
			'id_msg' => 'm.id_msg',
		];

		$query_where = [];
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
				$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' {not_' : '{') . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? 'ilike} ' : 'rlike} ') . '{string:complex_body_' . $count . '}';
				$query_params['complex_body_' . ($count++)] = $this->prepareWord($regularWord, $search_data['no_regexp']);
			}
		}

		// Modifiers such as specific user or specific board.
		$query_where = array_merge($query_where, $this->queryWhereModifiers($query_params));

		// Modifiers to exclude words from the subject
		$query_where = array_merge($query_where, $this->queryExclusionModifiers($query_params, $search_data));

		if (!empty($modSettings['search_simple_fulltext']))
		{
			$query_where[] = 'MATCH (body, subject) AGAINST ({string:body_match})';
			$query_params['body_match'] = implode(' ', array_diff($words['indexed_words'], $query_params['excluded_index_words']));
		}
		else
		{
			$query_params['boolean_match'] = '';

			// Remove any indexed words that are used in the complex body search terms
			$words['indexed_words'] = array_diff($words['indexed_words'], $words['complex_words']);

			foreach ($words['indexed_words'] as $fulltextWord)
			{
				$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words'], true) ? '-' : '+') . $fulltextWord . ' ';
			}

			$query_params['boolean_match'] = substr($query_params['boolean_match'], 0, -1);

			// If we have bool terms to search, add them in
			if ($query_params['boolean_match'] !== '' && $query_params['boolean_match'] !== '0')
			{
				$query_where[] = 'MATCH (body, subject) AGAINST ({string:boolean_match} IN BOOLEAN MODE)';
			}
		}

		return $db_search->search_query('', ($db->support_ignore() ? ('
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params
		);
	}
}
