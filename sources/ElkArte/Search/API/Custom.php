<?php

/**
 * Search class used when a custom index is used.  Handles its creation as well
 * as maintaining it as posts are added / removed
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

use ElkArte\Util;

/**
 * SearchAPI-Custom.class.php, Custom Search API class .. used when custom ElkArte index is used
 *
 * @package Search
 */
class Custom extends Standard
{
	/**
	 *This is the last version of ElkArte that this was tested on, to protect against API changes.
	 *
	 * @var string
	 */
	public $version_compatible = 'ElkArte 2.0 dev';

	/**
	 *This won't work with versions of ElkArte less than this.
	 *
	 * @var string
	 */
	public $min_elk_version = 'ElkArte 1.0 Beta';

	/**
	 * Is it supported?
	 *
	 * @var bool
	 */
	public $is_supported = true;

	/**
	 * Index Settings
	 *
	 * @var array
	 */
	protected $indexSettings = array();

	/**
	 * Custom::__construct()
	 */
	public function __construct($config, $searchParams)
	{
		global $modSettings;

		parent::__construct($config, $searchParams);

		// Is this database supported?
		if (!in_array($this->_db->title(), $this->supported_databases))
		{
			$this->is_supported = false;

			return;
		}

		if (empty($modSettings['search_custom_index_config']))
		{
			return;
		}

		$this->indexSettings = Util::unserialize($modSettings['search_custom_index_config']);

		$this->bannedWords = empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
	}

	/**
	 * If the settings don't exist we can't continue.
	 */
	public function isValid()
	{
		global $modSettings;

		return !empty($modSettings['search_custom_index_config']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
	{
		global $modSettings;

		$subwords = text2words($word, true);

		if (empty($modSettings['search_force_index']))
		{
			$wordsSearch['words'][] = $word;
		}

		// Excluded phrases don't benefit from being split into subwords.
		if (count($subwords) > 1 && $isExcluded)
		{
			return;
		}
		else
		{
			foreach ($subwords as $subword)
			{
				if (Util::strlen($subword) >= $this->min_word_length && !in_array($subword, $this->bannedWords))
				{
					$wordsSearch['indexed_words'][] = $subword;
					if ($isExcluded !== false)
					{
						$wordsExclude[] = $subword;
					}
				}
			}
		}
	}

	public function useWordIndex()
	{
		return true;
	}

	/**
	 * Search for indexed words.
	 *
	 * @param array $words An array of words
	 * @param array $search_data An array of search data
	 *
	 * @return \ElkArte\Database\AbstractResult|boolean
	 */
	public function indexedWordQuery($words, $search_data)
	{
		global $modSettings;

		$db = database();

		// We can't do anything without this
		$db_search = db_search();

		$query_select = array(
			'id_msg' => 'm.id_msg',
		);
		$query_inner_join = array();
		$query_left_join = array();
		$query_where = array();
		$query_params = $search_data['params'];

		if ($query_params['id_search'])
		{
			$query_select['id_search'] = '{int:id_search}';
		}

		$count = 0;
		foreach ($words['words'] as $regularWord)
		{
			$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' {not_' : '{') . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? 'ilike} ' : 'rlike} ') . '{string:complex_body_' . $count . '}';
			$query_params['complex_body_' . ($count++)] = $this->prepareWord($regularWord, $search_data['no_regexp']);
		}

		// Modifiers such as specific user or specific board.
		$query_where += $this->queryWhereModifiers($query_params);

		// Modifiers to exclude words from the subject
		$query_where += $this->queryExclusionModifiers($query_params, $search_data);

		$numTables = 0;
		$prev_join = 0;
		foreach ($words['indexed_words'] as $indexedWord)
		{
			$numTables++;
			if (in_array($indexedWord, $query_params['excluded_index_words']))
			{
				$query_left_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_word = ' . $indexedWord . ' AND lsw' . $numTables . '.id_msg = m.id_msg)';
				$query_where[] = '(lsw' . $numTables . '.id_word IS NULL)';
			}
			else
			{
				$query_inner_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_msg = ' . ($prev_join === 0 ? 'm' : 'lsw' . $prev_join) . '.id_msg)';
				$query_where[] = 'lsw' . $numTables . '.id_word = ' . $indexedWord;
				$prev_join = $numTables;
			}
		}

		return $db_search->search_query('', ($db->support_ignore() ? ('
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m' . (empty($query_inner_join) ? '' : '
				INNER JOIN ' . implode('
				INNER JOIN ', $query_inner_join)) . (empty($query_left_join) ? '' : '
				LEFT JOIN ' . implode('
				LEFT JOIN ', $query_left_join)) . '
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params
		);
	}

	/**
	 * After a post is made, we update the search index database
	 *
	 * @param array $msgOptions Contains the post data
	 * @param array $topicOptions Not used in this API
	 * @param array $posterOptions Not used in this API
	 */
	public function postCreated($msgOptions, $topicOptions, $posterOptions)
	{
		$db = database();

		$inserts = array();
		foreach (text2words($msgOptions['body'], true) as $word)
		{
			$inserts[] = array($word, $msgOptions['id']);
		}

		if (!empty($inserts))
		{
			$db->insert('ignore',
				'{db_prefix}log_search_words',
				array('id_word' => 'int', 'id_msg' => 'int'),
				$inserts,
				array('id_word', 'id_msg')
			);
		}
	}

	/**
	 * After a post is modified, we update the search index database.
	 *
	 * @param array $msgOptions The post data
	 * @param array $topicOptions Not used in this API
	 * @param array $posterOptions Not used in this API
	 */
	public function postModified($msgOptions, $topicOptions, $posterOptions)
	{
		global $modSettings;

		$db = database();

		if (isset($msgOptions['body']))
		{
			$stopwords = empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
			$old_body = $msgOptions['old_body'] ?? '';

			// Create the new and old index
			$old_index = text2words($old_body, true);
			$new_index = text2words($msgOptions['body'], true);

			// Calculate the words to be added and removed from the index.
			$removed_words = array_diff(array_diff($old_index, $new_index), $stopwords);
			$inserted_words = array_diff(array_diff($new_index, $old_index), $stopwords);

			// Delete the removed words AND the added ones to avoid key constraints.
			if (!empty($removed_words))
			{
				$removed_words = array_merge($removed_words, $inserted_words);
				$db->query('', '
					DELETE FROM {db_prefix}log_search_words
					WHERE id_msg = {int:id_msg}
						AND id_word IN ({array_int:removed_words})',
					array(
						'removed_words' => $removed_words,
						'id_msg' => $msgOptions['id'],
					)
				);
			}

			// Add the new words to be indexed.
			if (!empty($inserted_words))
			{
				$inserts = array();
				foreach ($inserted_words as $word)
				{
					$inserts[] = array($word, $msgOptions['id']);
				}
				$db->insert('insert',
					'{db_prefix}log_search_words',
					array('id_word' => 'string', 'id_msg' => 'int'),
					$inserts,
					array('id_word', 'id_msg')
				);
			}
		}
	}
}
