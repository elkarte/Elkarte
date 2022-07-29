<?php

/**
 * Used when the Sphinx search daemon is running and Access is via Sphinx's own
 * implementation of MySQL network protocol (SphinxQL)
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

use ElkArte\Cache\Cache;
use ElkArte\Errors\Errors;
use ElkArte\User;

/**
 * SearchAPI-Sphinxql.class.php, SphinxQL API,
 *
 * What it does:
 *
 * - Used when the Sphinx search daemon is running
 * - Access is via Sphinx's own implementation of MySQL network protocol (SphinxQL)
 * - Requires Sphinx 2.3 or higher
 *
 * @package Search
 */
class Sphinxql extends AbstractAPI
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
	public $min_elk_version = 'ElkArte 1.0 Beta 1';

	/**
	 * Is it supported?
	 *
	 * @var bool
	 */
	public $is_supported = true;

	/**
	 * What words are banned?
	 *
	 * @var array
	 */
	protected $bannedWords = [];

	/**
	 * What is the minimum word length?
	 *
	 * @var int
	 */
	protected $min_word_length = 4;

	/**
	 * What databases are supported?
	 *
	 * @var array
	 */
	protected $supported_databases = ['MySQL'];

	/**
	 * Nothing to do ...
	 */
	public function __construct($config, $searchParams)
	{
		parent::__construct($config, $searchParams);

		// Is this database supported?
		if (!in_array($this->_db->title(), $this->supported_databases, true))
		{
			$this->is_supported = false;
		}
	}

	/**
	 * If the settings don't exist we can't continue.
	 */
	public function isValid()
	{
		global $modSettings;

		return !empty($modSettings['sphinx_searchd_server']) && !empty($modSettings['sphinxql_searchd_port']);
	}

	/**
	 * {@inheritdoc }
	 */
	public function indexedWordQuery($words, $search_data)
	{
		// Sphinx uses its internal engine
	}

	/**
	 *  {@inheritdoc }
	 */
	public function supportsExtended()
	{
		return true;
	}

	/**
	 * {@inheritdoc }
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
	{
		$subwords = text2words($word, false);

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded !== false)
		{
			$wordsExclude[] = $fulltextWord;
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function searchQuery($search_words, $excluded_words, &$participants)
	{
		global $context, $modSettings;

		// Only request the results if they haven't been cached yet.
		$cached_results = [];
		$cache_key = 'searchql_results_' . md5(User::$info->query_see_board . '_' . $context['params']);
		if (!Cache::instance()->getVar($cached_results, $cache_key))
		{
			// Connect to the sphinx searchd and set a few options.
			$mySphinx = $this->sphinxConnect();

			// Compile different options for our query
			$index = (!empty($modSettings['sphinx_index_prefix']) ? $modSettings['sphinx_index_prefix'] : 'elkarte') . '_index';
			$query = 'SELECT *' . (empty($this->_searchParams->topic) ? ', COUNT(*) num' : '') . ', WEIGHT() relevance FROM ' . $index;

			// Construct the (binary mode & |) query.
			$where_match = $search_words[0];

			// Nothing to search, return zero results
			if (trim($where_match) === '')
			{
				return 0;
			}

			if ($this->_searchParams->subject_only)
			{
				$where_match = '@subject ' . $where_match;
			}

			$query .= ' WHERE MATCH(\'' . $where_match . '\')';

			// Set the limits based on the search parameters, board, member, dates, etc
			$extra_where = $this->buildQueryLimits();
			if (!empty($extra_where))
			{
				$query .= ' AND ' . implode(' AND ', $extra_where);
			}

			// Put together a sort string; besides the main column sort (relevance, id_topic, or num_replies)
			$this->_searchParams->sort_dir = strtoupper($this->_searchParams->sort_dir);
			$sphinx_sort = $this->_searchParams->sort === 'id_msg' ? 'id_topic' : $this->_searchParams->sort;

			// Add secondary sorting based on relevance(rank) value (if not the main sort method) and age
			$sphinx_sort .= ' ' . $this->_searchParams->sort_dir . ($this->_searchParams->sort === 'relevance' ? '' : ', relevance DESC') . ', poster_time DESC';

			// Grouping by topic id makes it return only one result per topic, so don't set that for in-topic searches
			if (empty($this->_searchParams->topic))
			{
				// In the topic group, use the most weighty result for display purposes
				$query .= ' GROUP BY id_topic WITHIN GROUP ORDER BY relevance DESC';
			}

			$query .= ' ORDER BY ' . $sphinx_sort;
			$query .= ' LIMIT ' . min(500, $modSettings['sphinx_max_results']);

			// Set any options needed, like field weights.
			// ranker is a modification of SPH_RANK_SPH04 sum((4*lcs+2*(min_hit_pos==1)+exact_hit)*user_weight)*1000+bm25
			// Each term will return a 0-1000 range we include our acprel value for the final total and order.  Position
			// is the relative reply # to a post, so the later a reply in a topic the less overall weight it is given
			// the calculated value of ranker is returned in WEIGHTS() which we name relevance in the query
			$subject_weight = !empty($modSettings['search_weight_subject']) ? $modSettings['search_weight_subject'] : 30;
			$query .= '
			OPTION 
				field_weights=(subject=' . $subject_weight . ', body=' . (100 - $subject_weight) . '),
				ranker=expr(\'sum((4*lcs+2*(min_hit_pos==1)+word_count)*user_weight*position) + acprel + bm25 \'),
				idf=plain,
				boolean_simplify=1,
				max_matches=' . min(500, $modSettings['sphinx_max_results']);

			// Execute the search query.
			$request = mysqli_query($mySphinx, $query);

			// Bad query, lets log the error and act like it's not our fault
			if ($request === false)
			{
				// Just log the error.
				if (mysqli_error($mySphinx) !== '')
				{
					Errors::instance()->log_error(mysqli_error($mySphinx));
				}

				Errors::instance()->fatal_lang_error('error_invalid_search_daemon');
			}

			// Get the relevant information from the search results.
			$cached_results = [
				'num_results' => 0,
				'matches' => [],
			];

			if (mysqli_num_rows($request) !== 0)
			{
				while ($match = mysqli_fetch_assoc($request))
				{
					$num = 0;
					if (empty($this->_searchParams->topic))
					{
						$num = $match['num'] ?? ($match['@count'] ?? 0);
					}

					$cached_results['matches'][$match['id']] = [
						'id' => $match['id_topic'],
						'num_matches' => $num,
						'matches' => [],
						'relevance' => round($match['relevance'], 0),
					];
				}
			}
			mysqli_free_result($request);
			mysqli_close($mySphinx);

			$cached_results['num_results'] = count($cached_results['matches']);

			// Store the search results in the cache.
			Cache::instance()->put($cache_key, $cached_results, 600);
		}

		$participants = [];
		$topics = [];
		foreach (array_slice(array_keys($cached_results['matches']), $this->_req->getRequest('start', 'intval', 0), $modSettings['search_results_per_page']) as $msgID)
		{
			$topics[$msgID] = $cached_results['matches'][$msgID];
			$participants[$cached_results['matches'][$msgID]['id']] = false;
		}

		$this->_num_results = $cached_results['num_results'];

		return $topics;
	}

	/**
	 * Connect to the sphinx server, on failure log error and exit
	 *
	 * @return \mysqli
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function sphinxConnect()
	{
		global $modSettings;

		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$mySphinx = mysqli_connect(($modSettings['sphinx_searchd_server'] === 'localhost' ? '127.0.0.1' : $modSettings['sphinx_searchd_server']), '', '', '', (int) $modSettings['sphinxql_searchd_port']);
		}
		catch (\Exception $e)
		{
			$mySphinx = false;
		}
		finally
		{
			restore_error_handler();
		}

		// No connection, daemon not running?  log the error and exit
		if ($mySphinx === false)
		{
			Errors::instance()->fatal_lang_error('error_no_search_daemon');
		}

		return $mySphinx;
	}

	/**
	 * {@inheritdoc }
	 */
	public function useWordIndex()
	{
		return false;
	}

	/**
	 * Builds the query modifiers based on age, member, board etc
	 *
	 * @return array
	 */
	public function buildQueryLimits()
	{
		global $modSettings;

		$extra_where = [];

		if (!empty($this->_searchParams->_minMsgID) || !empty($this->_searchParams->_maxMsgID))
		{
			$extra_where[] = 'id >= ' . $this->_searchParams->_minMsgID . ' AND id <= ' . (empty($this->_searchParams->_maxMsgID) ? (int) $modSettings['maxMsgID'] : $this->_searchParams->_maxMsgID);
		}

		if (!empty($this->_searchParams->topic))
		{
			$extra_where[] = 'id_topic = ' . (int) $this->_searchParams->topic;
		}

		if (!empty($this->_searchParams->brd))
		{
			$extra_where[] = 'id_board IN (' . implode(',', $this->_searchParams->brd) . ')';
		}

		if (!empty($this->_searchParams->_memberlist))
		{
			$extra_where[] = 'id_member IN (' . implode(',', $this->_searchParams->_memberlist) . ')';
		}

		return $extra_where;
	}
}
