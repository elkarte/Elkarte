<?php

/**
 * Used when an Sphinx search daemon is running and access is via the Sphinx
 * native search API (SphinxAPI)
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
use Elkarte\User;
use SphinxClient;

/**
 * SearchAPI-Sphinx.class.php, Sphinx API,
 *
 * What it does:
 *
 * - Used when a Sphinx search daemon is running
 * - Access is via the Sphinx native search API (SphinxAPI)
 * - sphinxapi.php is part of the Sphinx package, YOU must add the file must be added to SOURCEDIR
 *
 * @package Search
 */
class Sphinx extends AbstractAPI
{
	/** @var string This is the last version of ElkArte that this was tested on, to protect against API changes. */
	public $version_compatible = 'ElkArte 2.0 dev';

	/** @var string This won't work with versions of ElkArte less than this. */
	public $min_elk_version = 'ElkArte 1.0 Beta 1';

	/** @var bool Is it supported? */
	public $is_supported = true;

	/** @var array What words are banned? */
	protected $bannedWords = [];

	/** @var int What is the minimum word length? */
	protected $min_word_length = 4;

	/** @var array What databases are supported? */
	protected $supported_databases = ['MySQL'];

	/**
	 * Check we support this db
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

		return !empty($modSettings['sphinx_searchd_server']) && !empty($modSettings['sphinx_searchd_port']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery($words, $search_data)
	{
		// Sphinx uses its internal engine
	}

	/**
	 *  {@inheritDoc}
	 */
	public function supportsExtended()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function searchQuery($search_words, $excluded_words, &$participants)
	{
		global $context, $modSettings;

		// Only request the results if they haven't been cached yet.
		$cached_results = [];
		$cache_key = 'search_results_' . md5(User::$info->query_see_board . '_' . $context['params']);
		if (!Cache::instance()->getVar($cached_results, $cache_key))
		{
			// The API communicating with the search daemon.  This file is part of Sphinix and not distributed
			// with ElkArte.  You will need to https://sphinxsearch.com/downloads/current/ the package and copy
			// the file from the api directory to your sourcedir ??/??/sources
			require_once(SOURCEDIR . '/sphinxapi.php');

			// Create an instance of the sphinx client and set a few options.
			$mySphinx = new SphinxClient();
			$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
			$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results'], (int) $modSettings['sphinx_max_results'], 1000);
			$mySphinx->SetSelect('*' . (empty($this->_searchParams->topic) ? ', COUNT(*) num' : '') . ', WEIGHT() relevance');

			// Put together a sort string; besides the main column sort (relevance, id_topic, or num_replies),
			$this->_searchParams->sort_dir = strtoupper($this->_searchParams->sort_dir);
			$sphinx_sort = $this->_searchParams->sort === 'id_msg' ? 'id_topic' : $this->_searchParams->sort;

			// Add secondary sorting based on relevance value (if not the main sort method) and age
			$sphinx_sort .= ' ' . $this->_searchParams->sort_dir . ($this->_searchParams->sort === 'relevance' ? '' : ', relevance DESC') . ', poster_time DESC';

			// Grouping by topic id makes it return only one result per topic, so don't set that for in-topic searches
			if (empty($this->_searchParams->topic))
			{
				$mySphinx->SetGroupBy('id_topic', SPH_GROUPBY_ATTR, 'relevance DESC');
			}

			// Set up the sort expression
			$mySphinx->SetSortMode(SPH_SORT_EXTENDED, $sphinx_sort);

			// Update the field weights for subject vs body
			$subject_weight = empty($modSettings['search_weight_subject']) ? 30 : (int) $modSettings['search_weight_subject'];
			$mySphinx->SetFieldWeights(array('subject' => $subject_weight, 'body' => 100 - $subject_weight));

			// Set the limits based on the search parameters.
			$this->buildQueryLimits($mySphinx);

			// Construct the (binary mode & |) query while accounting for excluded words
			$query = $this->_searchArray->searchArrayExtended();

			// If no search terms are left after comparing against excluded words (i.e. "test -test" or "test last -test -last"),
			// sending that to Sphinx would result in a fatal error
			if (trim($query) === '')
			{
				// Instead, fail gracefully (return "no results")
				return 0;
			}

			// Subject only searches need to be specified.
			if ($this->_searchParams->subject_only)
			{
				$query = '@(subject) ' . $query;
			}

			// Set our ranking equation
			$mySphinx->SetRankingMode(SPH_RANK_EXPR, 'sum((4*lcs+2*(min_hit_pos==1)+word_count)*user_weight*position) + acprel + bm25');

			// Execute the search query.
			$index = (empty($modSettings['sphinx_index_prefix']) ? 'elkarte' : $modSettings['sphinx_index_prefix']) . '_index';
			$request = $mySphinx->Query($query, $index);

			// Can a connection to the daemon be made?
			if ($request === false)
			{
				// Just log the error.
				if ($mySphinx->GetLastError())
				{
					Errors::instance()->log_error($mySphinx->GetLastError());
				}

				Errors::instance()->fatal_lang_error('error_no_search_daemon');
			}

			// Get the relevant information from the search results.
			$cached_results = array(
				'matches' => [],
				'num_results' => $request['total'],
			);

			if (isset($request['matches']))
			{
				foreach ($request['matches'] as $msgID => $match)
				{
					$cached_results['matches'][$msgID] = array(
						'id' => $match['attrs']['id_topic'],
						'relevance' => round($match['attrs']['@count'] + $match['attrs']['relevance'] / 5000, 1) . '%',
						'num_matches' => empty($this->_searchParams->topic) ? $match['attrs']['@count'] : 0,
						'matches' => [],
					);
				}
			}

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
	 * Answer no
	 *
	 * @return false
	 */
	public function useWordIndex()
	{
		return false;
	}

	/**
	 * Builds the query modifiers based on age, member, board etc
	 *
	 * @param SphinxClient $mySphinx
	 */
	public function buildQueryLimits($mySphinx)
	{
		global $modSettings;

		if (!empty($this->_searchParams->min_msg_id) || !empty($this->_searchParams->max_msg_id))
		{
			$mySphinx->SetIDRange($this->_searchParams->min_msg_id, empty($this->_searchParams->max_msg_id) ? (int) $modSettings['maxMsgID'] : $this->_searchParams->max_msg_id);
		}

		if (!empty($this->_searchParams->topic))
		{
			$mySphinx->SetFilter('id_topic', array((int) $this->_searchParams->topic));
		}

		if (!empty($this->_searchParams->brd))
		{
			$mySphinx->SetFilter('id_board', $this->_searchParams->brd);
		}

		if (!empty($this->_searchParams->_memberlist))
		{
			$mySphinx->SetFilter('id_member', $this->_searchParams->_memberlist);
		}
	}
}
