<?php

/**
 * Used when an Sphinx search daemon is running and access is via the Sphinx
 * native search API (SphinxAPI)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

namespace ElkArte\Search\API;

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
class Sphinx extends SearchAPI
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
	public $min_elk_version = 'ElkArte 1.0 Beta 1';

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
	 * What databases are supported?
	 * @var array
	 */
	protected $supported_databases = array('MySQL');

	/**
	 * Check we support this db, set banned words
	 */
	public function __construct()
	{
		// Is this database supported?
		if (!in_array(DB_TYPE, $this->supported_databases))
		{
			$this->is_supported = false;

			return;
		}

		parent::__construct();
	}

	/**
	 * Check whether the method can be performed by this API.
	 *
	 * @deprecated since 1.1 - check that the method is callable
	 *
	 * @param string $methodName The search method
	 * @param mixed[]|null $query_params Parameters for the query
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		switch ($methodName)
		{
			case 'searchQuery':
				// Search can be performed, but not for 'subject only' query.
				return !$query_params['subject_only'];
			default:
				return is_callable(array($this, $methodName));
		}
	}

	/**
	 * If the settings don't exist we can't continue.
	 */
	public function isValid()
	{
		global $modSettings;

		return !(empty($modSettings['sphinx_searchd_server']) || empty($modSettings['sphinx_searchd_port']));
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
	 * Do we have to do some work with the words we are searching for to prepare them?
	 *
	 * @param string Word(s) to index
	 * @param mixed[] $wordsSearch The Search words
	 * @param string[] $wordsExclude Words to exclude
	 * @param boolean $isExcluded
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		$subwords = text2words($word, null, false);

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded)
		{
			$wordsExclude[] = $fulltextWord;
		}
	}

	/**
	 * This has it's own custom search.
	 *
	 * @param mixed[] $search_params
	 * @param mixed[] $search_words
	 * @param string[] $excluded_words
	 * @param int[] $participants
	 * @param string[] $search_results
	 * @throws \Elk_Exception
	 */
	public function searchQuery($search_params, $search_words, $excluded_words, &$participants, &$search_results)
	{
		global $user_info, $context, $modSettings;

		if (!$search_params['subject_only'])
		{
			return 0;
		}

		// Only request the results if they haven't been cached yet.
		$cached_results = array();
		if (!\Cache::instance()->getVar($cached_results, 'search_results_' . md5($user_info['query_see_board'] . '_' . $context['params'])))
		{
			// The API communicating with the search daemon.
			require_once(SOURCEDIR . '/sphinxapi.php');

			// Create an instance of the sphinx client and set a few options.
			$mySphinx = new \SphinxClient();
			$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
			$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results'], (int) $modSettings['sphinx_max_results']);

			// Put together a sort string; besides the main column sort (relevance, id_topic, or num_replies),
			$search_params['sort_dir'] = strtoupper($search_params['sort_dir']);
			$sphinx_sort = $search_params['sort'] === 'id_msg' ? 'id_topic' : $search_params['sort'];

			// Add secondary sorting based on relevance value (if not the main sort method) and age
			$sphinx_sort .= ' ' . $search_params['sort_dir'] . ($search_params['sort'] === 'relevance' ? '' : ', relevance DESC') . ', poster_time DESC';

			// Include the engines weight values in the group sort
			$sphinx_sort = str_replace('relevance ', '@weight ' . $search_params['sort_dir'] . ', relevance ', $sphinx_sort);

			// Grouping by topic id makes it return only one result per topic, so don't set that for in-topic searches
			if (empty($search_params['topic']))
			{
				$mySphinx->SetGroupBy('id_topic', SPH_GROUPBY_ATTR, $sphinx_sort);
			}

			// Set up the sort expression
			$mySphinx->SetSortMode(SPH_SORT_EXPR, '(@weight + (relevance / 10))');

			// Update the field weights for subject vs body
			$mySphinx->SetFieldWeights(array('subject' => !empty($modSettings['search_weight_subject']) ? $modSettings['search_weight_subject'] * 10 : 100, 'body' => 100));

			// Set the limits based on the search parameters.
			if (!empty($search_params['min_msg_id']) || !empty($search_params['max_msg_id']))
			{
				$mySphinx->SetIDRange($search_params['min_msg_id'], empty($search_params['max_msg_id']) ? (int) $modSettings['maxMsgID'] : $search_params['max_msg_id']);
			}

			if (!empty($search_params['topic']))
			{
				$mySphinx->SetFilter('id_topic', array((int) $search_params['topic']));
			}

			if (!empty($search_params['brd']))
			{
				$mySphinx->SetFilter('id_board', $search_params['brd']);
			}

			if (!empty($search_params['memberlist']))
			{
				$mySphinx->SetFilter('id_member', $search_params['memberlist']);
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
			if (!count(array_diff($inc_words, $excluded_words)))
			{
				// Instead, fail gracefully (return "no results")
				return 0;
			}

			$query = count($orResults) === 1 ? $orResults[0] : '(' . implode(') | (', $orResults) . ')';

			// Subject only searches need to be specified.
			if ($search_params['subject_only'])
			{
				$query = '@(subject) ' . $query;
			}

			// Choose an appropriate matching mode
			$mode = SPH_MATCH_ALL;

			// Over two words and searching for any (since we build a binary string, this will never get set)
			if (substr_count($query, ' ') > 1 && (!empty($search_params['searchtype']) && $search_params['searchtype'] == 2))
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
					\Errors::instance()->log_error($mySphinx->GetLastError());
				}

				\Errors::instance()->fatal_lang_error('error_no_search_daemon');
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
						'num_matches' => empty($search_params['topic']) ? $match['attrs']['@count'] : 0,
						'matches' => array(),
					);
				}
			}

			// Store the search results in the cache.
			\Cache::instance()->put('search_results_' . md5($user_info['query_see_board'] . '_' . $context['params']), $cached_results, 600);
		}

		$participants = array();
		foreach (array_slice(array_keys($cached_results['matches']), (int) $_REQUEST['start'], $modSettings['search_results_per_page']) as $msgID)
		{
			$context['topics'][$msgID] = $cached_results['matches'][$msgID];
			$participants[$cached_results['matches'][$msgID]['id']] = false;
		}

		// Sentences need to be broken up in words for proper highlighting.
		$search_results = array();
		foreach ($search_words as $orIndex => $words)
		{
			$search_results = array_merge($search_results, $search_words[$orIndex]['subject_words']);
		}

		return $cached_results['num_results'];
	}

	/**
	 * Clean up a search word/phrase/term for Sphinx.
	 *
	 * @param string $sphinx_term
	 * @param \SphinxClient $sphinx_client
	 */
	private function _cleanWordSphinx($sphinx_term, $sphinx_client)
	{
		// Multiple quotation marks in a row can cause fatal errors, so handle them
		$sphinx_term = preg_replace('/""+/', '"', $sphinx_term);

		// Unmatched (i.e. odd number of) quotation marks also cause fatal errors, so handle them
		if (substr_count($sphinx_term, '"') % 2)
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
}
