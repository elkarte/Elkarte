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

/**
 * SearchAPI-Sphinx.class.php, Sphinx API,
 *
 * What it does:
 *
 * - used when a Sphinx search daemon is running
 * - Access is via the Sphinx native search API (SphinxAPI)
 * - sphinxapi.php is part of the Sphinx package, the file must be added to SOURCEDIR
 *
 * @package Search
 */
class Sphinx extends AbstractAPI
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
	protected $bannedWords = array();

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
	protected $supported_databases = array('MySQL');

	/**
	 * Check we support this db, set banned words
	 */
	public function __construct($config, $searchParams)
	{
		parent::__construct($config, $searchParams);

		// Is this database supported?
		if (!in_array($this->_db->title(), $this->supported_databases))
		{
			$this->is_supported = false;

			return;
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
	 * Callback function for usort used to sort the results.
	 *
	 * - The order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted (-1, 0 1)
	 */
	public function searchSort($a, $b)
	{
		$x = strlen($a) - (in_array($a, $this->_excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $this->_excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * {@inheritdoc }
	 */
	public function indexedWordQuery($words, $search_data)
	{
	}

	/**
	 * {@inheritdoc }
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
	{
		$subwords = text2words($word, null, false);

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded !== '')
		{
			$wordsExclude[] = $fulltextWord;
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function searchQuery($search_words, $excluded_words, &$participants, &$search_results)
	{
		global $context, $modSettings;

		if (!$this->_searchParams->subject_only)
		{
			return 0;
		}

		// Only request the results if they haven't been cached yet.
		$cached_results = array();
		$cache_key = 'search_results_' . md5(User::$info->query_see_board . '_' . $context['params']);
		if (!Cache::instance()->getVar($cached_results, $cache_key))
		{
			// The API communicating with the search daemon, this file is part of Sphinix and not distributed
			// Elkarte
			require_once(SOURCEDIR . '/sphinxapi.php');

			// Create an instance of the sphinx client and set a few options.
			$mySphinx = new \SphinxClient();
			$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
			$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results'], (int) $modSettings['sphinx_max_results']);

			// Put together a sort string; besides the main column sort (relevance, id_topic, or num_replies),
			$this->_searchParams->sort_dir = strtoupper($this->_searchParams->sort_dir);
			$sphinx_sort = $this->_searchParams->sort === 'id_msg' ? 'id_topic' : $this->_searchParams->sort;

			// Add secondary sorting based on relevance value (if not the main sort method) and age
			$sphinx_sort .= ' ' . $this->_searchParams->sort_dir . ($this->_searchParams->sort === 'relevance' ? '' : ', relevance DESC') . ', poster_time DESC';

			// Include the engines weight values in the group sort
			$sphinx_sort = str_replace('relevance ', '@weight ' . $this->_searchParams->sort_dir . ', relevance ', $sphinx_sort);

			// Grouping by topic id makes it return only one result per topic, so don't set that for in-topic searches
			if (empty($this->_searchParams->topic))
			{
				$mySphinx->SetGroupBy('id_topic', SPH_GROUPBY_ATTR, $sphinx_sort);
			}

			// Set up the sort expression
			$mySphinx->SetSortMode(SPH_SORT_EXPR, '(@weight + (relevance / 10))');

			// Update the field weights for subject vs body
			$mySphinx->SetFieldWeights(array('subject' => !empty($modSettings['search_weight_subject']) ? $modSettings['search_weight_subject'] * 10 : 100, 'body' => 100));

			// Set the limits based on the search parameters.
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

			// Construct the (binary mode & |) query while accounting for excluded words
			$orResults = array();
			$inc_words = array();
			foreach ($search_words as $orIndex => $words)
			{
				$inc_words = array_merge($inc_words, $words['indexed_words']);
				$andResult = '';
				foreach ($words['indexed_words'] as $sphinxWord)
				{
					$andResult .= (in_array($sphinxWord, $excluded_words) ? '-' : '') . $this->_cleanWordSphinx($sphinxWord, $mySphinx) . ' & ';
				}
				$orResults[] = substr($andResult, 0, -3);
			}

			// If no search terms are left after comparing against excluded words (i.e. "test -test" or "test last -test -last"),
			// sending that to Sphinx would result in a fatal error
			if (count(array_diff($inc_words, $excluded_words)) === 0)
			{
				// Instead, fail gracefully (return "no results")
				return 0;
			}

			$query = count($orResults) === 1 ? $orResults[0] : '(' . implode(') | (', $orResults) . ')';

			// Subject only searches need to be specified.
			if ($this->_searchParams->subject_only)
			{
				$query = '@(subject) ' . $query;
			}

			// Choose an appropriate matching mode
			$mode = SPH_MATCH_ALL;

			// Over two words and searching for any (since we build a binary string, this will never get set)
			if (substr_count($query, ' ') > 1 && (!empty($this->_searchParams->searchtype) && $this->_searchParams->searchtype == 2))
			{
				$mode = SPH_MATCH_ANY;
			}
			// Binary search?
			if (preg_match('~[\|\(\)\^\$\?"\/=-]~', $query))
			{
				$mode = SPH_MATCH_EXTENDED;
			}

			// Set the matching mode
			$mySphinx->SetMatchMode($mode);

			// Execute the search query.
			$index = (!empty($modSettings['sphinx_index_prefix']) ? $modSettings['sphinx_index_prefix'] : 'elkarte') . '_index';
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
				'matches' => array(),
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
						'matches' => array(),
					);
				}
			}

			// Store the search results in the cache.
			Cache::instance()->put($cache_key, $cached_results, 600);
		}

		$participants = array();
		$topics = array();
		foreach (array_slice(array_keys($cached_results['matches']), (int) $_REQUEST['start'], $modSettings['search_results_per_page']) as $msgID)
		{
			$topics[$msgID] = $cached_results['matches'][$msgID];
			$participants[$cached_results['matches'][$msgID]['id']] = false;
		}

		// Sentences need to be broken up in words for proper highlighting.
		$search_results = array();
		foreach ($search_words as $orIndex => $words)
		{
			$search_results = array_merge($search_results, $search_words[$orIndex]['subject_words']);
		}
		$this->_num_results = $cached_results['num_results'];

		return $topics;
	}

	/**
	 * Clean up a search word/phrase/term for Sphinx.
	 *
	 * @param string $sphinx_term
	 * @param \SphinxClient $sphinx_client
	 *
	 * @return mixed|null|string|string[]
	 */
	private function _cleanWordSphinx($sphinx_term, $sphinx_client)
	{
		// Multiple quotation marks in a row can cause fatal errors, so handle them
		$sphinx_term = preg_replace('/""+/', '"', $sphinx_term);

		// Unmatched (i.e. odd number of) quotation marks also cause fatal errors, so handle them
		if (substr_count($sphinx_term, '"') % 2 !== 0)
		{
			// Using preg_replace since it supports limiting the number of replacements
			$sphinx_term = preg_replace('/"/', '', $sphinx_term, 1);
		}

		// Use the Sphinx API's built-in EscapeString function to escape special characters
		$sphinx_term = $sphinx_client->EscapeString($sphinx_term);

		// Since it escapes quotation marks and we don't want that, unescape them
		$sphinx_term = str_replace('\"', '"', $sphinx_term);

		return $sphinx_term;
	}

	public function useWordIndex()
	{
		return false;
	}
}
