<?php

/**
 * Used when an Sphinx search daemon is running and access is via the Sphinx
 * native search API (SphinxAPI)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * SearchAPI-Sphinx.class.php, Sphinx API, used when an Sphinx search daemon is running
 * Access is via the Sphinx native search API (SphinxAPI)
 */
class Sphinx_Search
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 * @var string
	 */
	public $version_compatible = 'ElkArte 1.0 Beta';

	/**
	 * This won't work with versions of ElkArte less than this.
	 * @var string
	 */
	public $min_elk_version = 'ElkArte 1.0 Alpha';

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
	protected $supported_databases = array('mysql');

	/**
	 * Nothing to do
	 */
	public function __construct()
	{
		global $db_type, $modSettings;

		// Is this database supported?
		if (!in_array($db_type, $this->supported_databases))
		{
			$this->is_supported = false;
			return;
		}

		$this->bannedWords = empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']);
	}

	/**
	 * Check whether the method can be performed by this API.
	 *
	 * @param string $methodName
	 * @param mixed $query_params
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		switch ($methodName)
		{
			case 'isValid':
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
				return true;
			break;
			case 'searchQuery':
				// Search can be performed, but not for 'subject only' query.
				return !$query_params['subject_only'];
			default:
				// All other methods, too bad dunno you.
				return false;
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
	 * callback function for usort used to sort the results.
	 * the order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int
	 */
	public function searchSort($a, $b)
	{
		global $excludedWords;

		$x = strlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * Do we have to do some work with the words we are searching for to prepare them?
	 *
	 * @param array $word
	 * @param array $wordsSearch
	 * @param array $wordsExclude
	 * @param array $isExcluded
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		$subwords = text2words($word, null, false);

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded)
			$wordsExclude[] = $fulltextWord;
	}

	/**
	 * This has it's own custom search.
	 *
	 * @param array $search_params
	 * @param array $search_words
	 * @param array $excluded_words
	 * @param array $participants
	 * @param array $search_results
	 */
	public function searchQuery($search_params, $search_words, $excluded_words, &$participants, &$search_results)
	{
		global $user_info, $context, $modSettings;

		// Only request the results if they haven't been cached yet.
		if (($cached_results = cache_get_data('search_results_' . md5($user_info['query_see_board'] . '_' . $context['params']))) === null)
		{
			// The API communicating with the search daemon.
			require_once(SOURCEDIR . '/sphinxapi.php');

			// Create an instance of the sphinx client and set a few options.
			$mySphinx = new SphinxClient();
			$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
			$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results'], (int) $modSettings['sphinx_max_results']);
			$mySphinx->SetMatchMode(SPH_MATCH_EXTENDED);

			// Put together a sort string; besides the main column sort (relevance, id_topic, or num_replies), add secondary sorting based on relevance value (if not the main sort method) and age
			$sphinx_sort = ($search_params['sort'] === 'id_msg' ? 'id_topic' : $search_params['sort']) . ' ' . $search_params['sort_dir'] . ($search_params['sort'] === 'relevance' ? '' : ', relevance desc') . ', poster_time desc';

			// Grouping by topic id makes it return only one result per topic, so don't set that for in-topic searches
			if (empty($search_params['topic']) && empty($search_params['show_complete']))
				$mySphinx->SetGroupBy('id_topic', SPH_GROUPBY_ATTR, $sphinx_sort);
			$mySphinx->SetSortMode(SPH_SORT_EXTENDED, $sphinx_sort);

			// Set the limits based on the search parameters.
			if (!empty($search_params['min_msg_id']) || !empty($search_params['max_msg_id']))
				$mySphinx->SetIDRange($search_params['min_msg_id'], empty($search_params['max_msg_id']) ? (int) $modSettings['maxMsgID'] : $search_params['max_msg_id']);

			if (!empty($search_params['topic']))
				$mySphinx->SetFilter('id_topic', array((int) $search_params['topic']));

			if (!empty($search_params['brd']))
				$mySphinx->SetFilter('id_board', $search_params['brd']);

			if (!empty($search_params['memberlist']))
				$mySphinx->SetFilter('id_member', $search_params['memberlist']);

			// Construct the (binary mode) query.
			$orResults = array();
			$inc_words = array();
			foreach ($search_words as $orIndex => $words)
			{
				$inc_words = array_merge($inc_words, $words['indexed_words']);
				$andResult = '';
				foreach ($words['indexed_words'] as $sphinxWord)
					$andResult .= (in_array($sphinxWord, $excluded_words) ? '-' : '') . $this->_cleanWordSphinx($sphinxWord, $mySphinx) . ' & ';
				$orResults[] = substr($andResult, 0, -3);
			}

			// If no search terms are left after comparing against excluded words (i.e. "test -test" or "test last -test -last"), sending that to Sphinx would result in a fatal error
			if (!count(array_diff($inc_words, $excluded_words)))
				// Instead, fail gracefully (return "no results")
				return 0;
			$query = count($orResults) === 1 ? $orResults[0] : '(' . implode(') | (', $orResults) . ')';

			// Subject only searches need to be specified.
			if ($search_params['subject_only'])
				$query = '@(subject) ' . $query;

			// Execute the search query.
			$request = $mySphinx->Query($query, 'elkarte_index');

			// Can a connection to the daemon be made?
			if ($request === false)
			{
				// Just log the error.
				if ($mySphinx->GetLastError())
					log_error($mySphinx->GetLastError());
				fatal_lang_error('error_no_search_daemon');
			}

			// Get the relevant information from the search results.
			$cached_results = array(
				'matches' => array(),
				'num_results' => $request['total'],
			);

			if (isset($request['matches']))
			{
				foreach ($request['matches'] as $msgID => $match)
					$cached_results['matches'][$msgID] = array(
						'id' => $match['attrs']['id_topic'],
						'relevance' => round($match['attrs']['relevance'] / 10000, 1) . '%',
						'num_matches' => empty($search_params['topic']) ? $match['attrs']['@count'] : 0,
						'matches' => array(),
					);
			}

			// Store the search results in the cache.
			cache_put_data('search_results_' . md5($user_info['query_see_board'] . '_' . $context['params']), $cached_results, 600);
		}

		$participants = array();
		foreach (array_slice(array_keys($cached_results['matches']), $_REQUEST['start'], $modSettings['search_results_per_page']) as $msgID)
		{
			$context['topics'][$msgID] = $cached_results['matches'][$msgID];
			$participants[$cached_results['matches'][$msgID]['id']] = false;
		}

		// Sentences need to be broken up in words for proper highlighting.
		$search_results = array();
		foreach ($search_words as $orIndex => $words)
			$search_results = array_merge($search_results, $search_words[$orIndex]['subject_words']);

		return $cached_results['num_results'];
	}

	/**
	 * Clean up a search word/phrase/term for Sphinx.
	 *
	 * @param string $sphinx_term
	 * @param string $sphinx_client
	 */
	private function _cleanWordSphinx($sphinx_term, $sphinx_client)
	{
		// Multiple quotation marks in a row can cause fatal errors, so handle them
		$sphinx_term = preg_replace('/""+/', '"', $sphinx_term);

		// Unmatched (i.e. odd number of) quotation marks also cause fatal errors, so handle them
		if (substr_count($sphinx_term, '"') % 2)

		// Using preg_replace since it supports limiting the number of replacements
		$sphinx_term = preg_replace('/"/', '', $sphinx_term, 1);

		// Use the Sphinx API's built-in EscapeString function to escape special characters
		$sphinx_term = $sphinx_client->EscapeString($sphinx_term);

		// Since it escapes quotation marks and we don't want that, unescape them
		$sphinx_term = str_replace('\"', '"', $sphinx_term);

		return $sphinx_term;
	}
}