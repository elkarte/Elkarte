<?php

/**
 * Utility class for search functionality.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Search;

use \ElkArte\Search\SearchParams;

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
	 * $_search_params will carry all settings that differ from the default search parameters.
	 *
	 * That way, the URLs involved in a search page will be kept as short as possible.
	 */
	private $_search_params = array();

	/**
	 * Holds the words and phrases to be searched on
	 * @var array
	 */
	private $_searchArray = array();

	/**
	 * Holds instance of the search api in use such as ElkArte\Search\API\Standard_Search
	 * @var null|object
	 */
	private $_searchAPI = null;

	/**
	 * Database instance
	 * @var \Database|null
	 */
	private $_db = null;

	/**
	 * Search db instance
	 * @var \DbSearch|null
	 */
	private $_db_search = null;

	/**
	 * Holds words that will not be search on to inform the user they were skipped
	 * @var array
	 */
	private $_ignored = array();

	/**
	 * Searching for posts from a specific user(s)
	 * @var array
	 */
	private $_memberlist = array();

	/**
	 * If we are performing a boolean or simple search
	 * @var bool
	 */
	private $_no_regexp = false;

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
	 * Words not be be found in the search results (-word)
	 * @var array
	 */
	private $_excludedWords = array();

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
	 * If search words were found on the blacklist
	 * @var bool
	 */
	private $_foundBlackListedWords = false;

	/**
	 * Words we do not search due to length or common terms
	 * @var array
	 */
	private $_blacklisted_words = array();

	/**
	 * The weights to associate to various areas for relevancy
	 * @var array
	 */
	private $_weight_factors = array();

	/**
	 * Weighing factor each area, ie frequency, age, sticky, etc
	 * @var array
	 */
	private $_weight = array();

	/**
	 * The sum of the _weight_factors, normally but not always 100
	 * @var int
	 */
	private $_weight_total = 0;

	/**
	 * If we are creating a tmp db table
	 * @var bool
	 */
	private $_createTemporary = true;

	/**
	 * 
	 * @var int
	 */
	public $humungousTopicPosts = 0;

	/**
	 * 
	 * @var int
	 */
	public $maxMessageResults = 0;

	/**
	 * 
	 * @var int
	 */
	protected $_num_results = 0;

	/**
	 * 
	 * @var mixed[]
	 */
	protected $_participants = [];

	/**
	 * Constructor
	 * Easy enough, initialize the database objects (generic db and search db)
	 *
	 * @package Search
	 */
	public function __construct($humungousTopicPosts = 0, $maxMessageResults = 0)
	{
		$this->_search_version = strtr('ElkArte 1+1', array('+' => '.', '=' => ' '));
		$this->_db = database();
		$this->_db_search = db_search();
		$this->humungousTopicPosts = $humungousTopicPosts;
		$this->maxMessageResults = $maxMessageResults;

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
		if (isset($this->_search_params[$name]))
		{
			return $this->_search_params[$name];
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
		return array_merge($this->_search_params, array(
			'min_msg_id' => (int) $this->_searchParams->_minMsgID,
			'max_msg_id' => (int) $this->_searchParams->_maxMsgID,
			'memberlist' => $this->_memberlist,
		));
	}

	/**
	 * Returns the ignored words
	 */
	public function getIgnored()
	{
		return $this->_ignored;
	}

	/**
	 * Returns words excluded from indexes
	 */
	public function getExcludedIndexWords()
	{
		return $this->_excludedIndexWords;
	}

	/**
	 * Set the weight factors
	 *
	 * @param \ElkArte\Search\WeightFactors $weight
	 */
	public function setWeights($weight)
	{
		$this->_weight_factors = $weight->getFactors();

		$this->_weight = $weight->getWeight();

		$this->_weight_total = $weight->getTotal();
	}

	public function setParams(SearchParams $paramObject)
	{
		$this->_searchParams = $paramObject;
		$this->_search_params = $this->_searchParams->get();

		// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
		// @todo Setting to add more here?
		// @todo Maybe only blacklist if they are the only word, or "any" is used?
		$this->_blacklisted_words = array('img', 'url', 'quote', 'www', 'http', 'the', 'is', 'it', 'are', 'if');
		call_integration_hook('integrate_search_blacklisted_words', array(&$this->_blacklisted_words));
	}

	/**
	 * If the query uses regexp or not
	 *
	 * @return bool
	 */
	public function noRegexp()
	{
		return $this->_no_regexp;
	}

	/**
	 * If any black-listed word has been found
	 *
	 * @return bool
	 */
	public function foundBlackListedWords()
	{
		return $this->_foundBlackListedWords;
	}

	/**
	 * Builds the search array
	 *
	 * @param bool - Force splitting of strings enclosed in double quotes
	 *
	 * @return 0|array
	 */
	public function searchArray($search_simple_fulltext = false)
	{
		// Change non-word characters into spaces.
		$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $this->param('search'));

		// Make the query lower case. It's gonna be case insensitive anyway.
		$stripped_query = un_htmlspecialchars(\Util::strtolower($stripped_query));

		// This option will do fulltext searching in the most basic way.
		if ($search_simple_fulltext)
		{
			$stripped_query = strtr($stripped_query, array('"' => ''));
		}

		$this->_no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;

		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $this->param('search'));
		$wordArray = explode(' ', \Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

		// A minus sign in front of a word excludes the word.... so...
		// .. first, we check for things like -"some words", but not "-some words".
		$phraseArray = $this->_checkExcludePhrase($matches[1], $phraseArray);

		// Now we look for -test, etc.... normaller.
		$wordArray = $this->_checkExcludeWord($wordArray);

		// The remaining words and phrases are all included.
		$this->_searchArray = array_merge($phraseArray, $wordArray);

		// Trim everything and make sure there are no words that are the same.
		foreach ($this->_searchArray as $index => $value)
		{
			// Skip anything practically empty.
			if (($this->_searchArray[$index] = trim($value, '-_\' ')) === '')
			{
				unset($this->_searchArray[$index]);
			}
			// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
			elseif (in_array($this->_searchArray[$index], $this->_blacklisted_words))
			{
				$this->_foundBlackListedWords = true;
				unset($this->_searchArray[$index]);
			}
			// Don't allow very, very short words.
			elseif (\Util::strlen($value) < 2)
			{
				$this->_ignored[] = $value;
				unset($this->_searchArray[$index]);
			}
		}

		$this->_searchArray = array_slice(array_unique($this->_searchArray), 0, 10);

		return $this->_searchArray;
	}

	public function getSearchArray()
	{
		return $this->_searchArray;
	}

	/**
	 * Looks for phrases that should be excluded from results
	 *
	 * - Check for things like -"some words", but not "-some words"
	 * - Prevents redundancy with blacklisted words
	 *
	 * @param string[] $matches
	 * @param string[] $phraseArray
	 *
	 * @return string[]
	 */
	private function _checkExcludePhrase($matches, $phraseArray)
	{
		foreach ($matches as $index => $word)
		{
			if ($word === '-')
			{
				if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $this->_blacklisted_words))
				{
					$this->_excludedWords[] = $word;
				}

				unset($phraseArray[$index]);
			}
		}

		return $phraseArray;
	}

	/**
	 * Looks for words that should be excluded in the results (-word)
	 *
	 * - Look for -test, etc
	 * - Prevents excluding blacklisted words since it is redundant
	 *
	 * @param string[] $wordArray
	 *
	 * @return string[]
	 */
	private function _checkExcludeWord($wordArray)
	{
		foreach ($wordArray as $index => $word)
		{
			if (strpos(trim($word), '-') === 0)
			{
				if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $this->_blacklisted_words))
				{
					$this->_excludedWords[] = $word;
				}

				unset($wordArray[$index]);
			}
		}

		return $wordArray;
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

		// All words/sentences must match.
		if (!empty($this->_searchArray) && empty($this->_search_params['searchtype']))
		{
			$orParts[0] = $this->_searchArray;
		}
		// Any word/sentence must match.
		else
		{
			foreach ($this->_searchArray as $index => $value)
				$orParts[$index] = array($value);
		}

		// Make sure the excluded words are in all or-branches.
		foreach ($orParts as $orIndex => $andParts)
		{
			foreach ($this->_excludedWords as $word)
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

			// Sort the indexed words (large words -> small words -> excluded words).
			if (is_callable(array($this->_searchAPI, 'searchSort')))
			{
				$this->_searchAPI->setExcludedWords($this->_excludedWords);
				usort($orParts[$orIndex], array($this->_searchAPI, 'searchSort'));
			}

			foreach ($orParts[$orIndex] as $word)
			{
				$is_excluded = in_array($word, $this->_excludedWords);
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
				if (is_callable(array($this->_searchAPI, 'prepareIndexes')))
				{
					$this->_searchAPI->prepareIndexes($word, $this->_searchWords[$orIndex], $this->_excludedIndexWords, $is_excluded);
				}
			}

			// Search_force_index requires all AND parts to have at least one fulltext word.
			if (!empty($modSettings['search_force_index']) && empty($this->_searchWords[$orIndex]['indexed_words']))
			{
				$context['search_errors']['query_not_specific_enough'] = true;
				break;
			}
			elseif ($this->param('subject_only') && empty($this->_searchWords[$orIndex]['subject_words']) && empty($this->_excludedSubjectWords))
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
		return empty($this->_search_params['show_complete']);
	}

	/**
	 * Encodes search params ($this->_search_params) in an URL-compatible way
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
	 * Setup spellchecking suggestions and load them into the two variable
	 * passed by ref
	 *
	 * @param string $suggestion_display - the string to display in the template
	 * @param string $suggestion_param - a param string to be used in a url
	 * @param string $display_highlight - a template to enclose in each suggested word
	 */
	public function loadSuggestions(&$suggestion_display = '', &$suggestion_param = '', $display_highlight = '')
	{
		global $txt;

		// Windows fix.
		ob_start();
		$old = error_reporting(0);

		pspell_new('en');
		$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', 'utf-8', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		if (!$pspell_link)
		{
			$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);
		}

		error_reporting($old);
		@ob_end_clean();

		$did_you_mean = array('search' => array(), 'display' => array());
		$found_misspelling = false;
		foreach ($this->_searchArray as $word)
		{
			if (empty($pspell_link))
			{
				continue;
			}

			// Don't check phrases.
			if (preg_match('~^\w+$~', $word) === 0)
			{
				$did_you_mean['search'][] = '"' . $word . '"';
				$did_you_mean['display'][] = '&quot;' . \Util::htmlspecialchars($word) . '&quot;';
				continue;
			}
			// For some strange reason spell check can crash PHP on decimals.
			elseif (preg_match('~\d~', $word) === 1)
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = \Util::htmlspecialchars($word);
				continue;
			}
			elseif (pspell_check($pspell_link, $word))
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = \Util::htmlspecialchars($word);
				continue;
			}

			$suggestions = pspell_suggest($pspell_link, $word);
			foreach ($suggestions as $i => $s)
			{
				// Search is case insensitive.
				if (\Util::strtolower($s) == \Util::strtolower($word))
				{
					unset($suggestions[$i]);
				}
				// Plus, don't suggest something the user thinks is rude!
				elseif ($suggestions[$i] != censor($s))
				{
					unset($suggestions[$i]);
				}
			}

			// Anything found?  If so, correct it!
			if (!empty($suggestions))
			{
				$suggestions = array_values($suggestions);
				$did_you_mean['search'][] = $suggestions[0];
				$did_you_mean['display'][] = str_replace('{word}', \Util::htmlspecialchars($suggestions[0]), $display_highlight);
				$found_misspelling = true;
			}
			else
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = \Util::htmlspecialchars($word);
			}
		}

		if ($found_misspelling)
		{
			// Don't spell check excluded words, but add them still...
			$temp_excluded = array('search' => array(), 'display' => array());
			foreach ($this->_excludedWords as $word)
			{
				if (preg_match('~^\w+$~', $word) == 0)
				{
					$temp_excluded['search'][] = '-"' . $word . '"';
					$temp_excluded['display'][] = '-&quot;' . \Util::htmlspecialchars($word) . '&quot;';
				}
				else
				{
					$temp_excluded['search'][] = '-' . $word;
					$temp_excluded['display'][] = '-' . \Util::htmlspecialchars($word);
				}
			}

			$did_you_mean['search'] = array_merge($did_you_mean['search'], $temp_excluded['search']);
			$did_you_mean['display'] = array_merge($did_you_mean['display'], $temp_excluded['display']);

			// Provide the potential correct spelling term in the param
			$suggestion_param = $this->compileURLparams($did_you_mean['search']);
			$suggestion_display = implode(' ', $did_you_mean['display']);
		}
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
	 * @param int $humungousTopicPosts - Message length used to tweak messages
	 *            relevance of the results.
	 *
	 * @return int - number of results otherwise
	 */
	public function getSubjectResults($id_search, $humungousTopicPosts)
	{
		global $modSettings;

		// We do this to try and avoid duplicate keys on databases not supporting INSERT IGNORE.
		foreach ($this->_searchWords as $words)
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
				if (in_array($subjectWord, $this->_excludedSubjectWords))
				{
					$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
					$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
				}
				else
				{
					$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
					$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');
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

			if (!empty($this->_search_params['topic']))
			{
				$subject_query['where'][] = 't.id_topic = ' . $this->_search_params['topic'];
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
					$subject_query['where'][] = 'm.subject NOT ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? 'LIKE' : 'RLIKE') . ' {string:excluded_phrases_' . $count . '}';
					$subject_query_params['excluded_phrases_' . ($count++)] = $this->_searchAPI->prepareWord($phrase, $this->noRegexp());
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
				'huge_topic_posts' => $humungousTopicPosts,
				'is_approved' => 1,
				'limit' => empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] - $numSubjectResults,
			));

			call_integration_hook('integrate_subject_only_search_query', array(&$subject_query, &$subject_query_params));

			$numSubjectResults += $this->_build_search_results_log($subject_query, 'insert_log_search_results_subject');

			if (!empty($modSettings['search_max_results']) && $numSubjectResults >= $modSettings['search_max_results'])
			{
				break;
			}
		}

		return empty($numSubjectResults) ? 0 : $numSubjectResults;
	}

	/**
	 * Grabs results when the search is performed in subjects and bodies
	 *
	 * @param int $id_search - the id of the search
	 * @param int $humungousTopicPosts - Message length used to tweak messages relevance of the results.
	 * @param int $maxMessageResults - Maximum number of results
	 *
	 * @return bool|int - boolean (false) in case of errors, number of results otherwise
	 */
	public function getResults($id_search, $humungousTopicPosts, $maxMessageResults)
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
				'huge_topic_posts' => $humungousTopicPosts,
				'is_approved' => 1,
				'limit' => $modSettings['search_max_results'],
			),
		);

		if (empty($this->_search_params['topic']) && empty($this->_search_params['show_complete']))
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

			if (!empty($this->_search_params['topic']))
			{
				$main_query['where'][] = 't.id_topic = {int:topic}';
				$main_query['parameters']['topic'] = $this->param('topic');
			}

			if (!empty($this->_search_params['show_complete']))
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
		if (is_callable(array($this->_searchAPI, 'indexedWordQuery')))
		{
			$indexedResults = $this->_prepare_word_index($id_search, $maxMessageResults);

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
			foreach ($this->_searchWords as $words)
			{
				$where = array();
				foreach ($words['all_words'] as $regularWord)
				{
					$where[] = 'm.body' . (in_array($regularWord, $this->_excludedWords) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $this->noRegexp() ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
					if (in_array($regularWord, $this->_excludedWords))
					{
						$where[] = 'm.subject NOT' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
					}
					$main_query['parameters']['all_word_body_' . ($count++)] = $this->_searchAPI->prepareWord($regularWord, $this->noRegexp());
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

			if (!empty($this->_search_params['topic']))
			{
				$main_query['where'][] = 'm.id_topic = {int:topic}';
				$main_query['parameters']['topic'] = $this->param('topic');
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
		if (!empty($indexedResults) || !is_callable(array($this->_searchAPI, 'indexedWordQuery')))
		{
			$main_query['select']['relevance'] = $this->_build_relevance($main_query['weights']);
			$num_results += $this->_build_search_results_log($main_query, 'insert_log_search_results_no_index');
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
					'huge_topic_posts' => $humungousTopicPosts,
					'limit' => empty($modSettings['search_max_results']) ? 0 : $modSettings['search_max_results'] - $num_results,
				),
			);

			$num_results += $this->_build_search_results_log($subject_query, 'insert_log_search_results_sub_only', true);
		}
		elseif ($num_results == -1)
		{
			$num_results = 0;
		}

		return $num_results;
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
			SELECT ' . (empty($this->_search_params['topic']) ? 'lsr.id_topic' : $this->param('topic') . ' AS id_topic') . ', lsr.id_msg, lsr.relevance, lsr.num_matches
			FROM {db_prefix}log_search_results AS lsr' . ($this->param('sort') === 'num_replies' ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = lsr.id_topic)' : '') . '
			WHERE lsr.id_search = {int:id_search}
			ORDER BY {raw:sort} {raw:sort_dir}
			LIMIT {int:start}, {int:limit}',
			array(
				'id_search' => $id_search,
				'sort' => $this->param('sort'),
				'sort_dir' => $this->param('sort_dir'),
				'start' => $start,
				'limit' => $limit,
			)
		);
		while ($row = $this->_db->fetch_assoc($request))
		{
			$topics[$row['id_msg']] = array(
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => array(),
			);
			// By default they didn't participate in the topic!
			$participants[$row['id_topic']] = false;
		}
		$this->_db->free_result($request);

		return $participants;
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
	 * @return resource
	 */
	public function loadMessagesRequest($msg_list, $limit)
	{
		global $modSettings;

		$request = $this->_db->query('', '
			SELECT
				m.id_msg, m.subject, m.poster_name, m.poster_email, m.poster_time, m.id_member,
				m.icon, m.poster_ip, m.body, m.smileys_enabled, m.modified_time, m.modified_name,
				first_m.id_msg AS first_msg, first_m.subject AS first_subject, first_m.icon AS first_icon, first_m.poster_time AS first_poster_time,
				first_mem.id_member AS first_member_id, COALESCE(first_mem.real_name, first_m.poster_name) AS first_member_name,
				last_m.id_msg AS last_msg, last_m.poster_time AS last_poster_time, last_mem.id_member AS last_member_id,
				COALESCE(last_mem.real_name, last_m.poster_name) AS last_member_name, last_m.icon AS last_icon, last_m.subject AS last_subject,
				t.id_topic, t.is_sticky, t.locked, t.id_poll, t.num_replies, t.num_views, t.num_likes,
				b.id_board, b.name AS board_name, c.id_cat, c.name AS cat_name
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

		return $request;
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

	public function searchQuery($searchAPI)
	{
		$this->_searchAPI = $searchAPI;
		$searchArray = array();

		return $searchAPI->searchQuery(
			$this->getParams(),
			$this->searchWords(),
			$this->getExcludedIndexWords(),
			$this->_participants,
			$searchArray,
			$this
		);
	}

	public function getNumResults()
	{
		return $this->_num_results;
	}

	public function getParticipants()
	{
		return $this->_participants;
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

		if (!empty($this->_search_params['topic']))
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
					$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($modSettings['search_match_words']) ? 'LIKE {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
					$subject_query['params']['subject_not_' . $count] = empty($modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

					$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
					$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? ' LIKE ' : ' RLIKE ') . '{string:body_not_' . $count . '}';
					$subject_query['params']['body_not_' . ($count++)] = $this->_searchAPI->prepareWord($subjectWord, $this->noRegexp());
				}
				else
				{
					$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
					$subject_query['where'][] = 'subj' . $numTables . '.word LIKE {string:subject_like_' . $count . '}';
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

			if (!empty($this->_search_params['topic']))
			{
				$subject_query['where'][] = 't.id_topic = {int:topic}';
				$subject_query['params']['topic'] = $this->param('topic');
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
					$subject_query['where'][] = 'm.subject NOT ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? 'LIKE' : 'RLIKE') . ' {string:exclude_phrase_' . $count . '}';
					$subject_query['where'][] = 'm.body NOT ' . (empty($modSettings['search_match_words']) || $this->noRegexp() ? 'LIKE' : 'RLIKE') . ' {string:exclude_phrase_' . $count . '}';
					$subject_query['params']['exclude_phrase_' . ($count++)] = $this->_searchAPI->prepareWord($phrase, $this->noRegexp());
				}
			}

			call_integration_hook('integrate_subject_search_query', array(&$subject_query));

			// Nothing to search for?
			if (empty($subject_query['where']))
			{
				continue;
			}

			$ignoreRequest = $this->_db_search->search_query('insert_log_search_topics', ($this->_db->support_ignore() ? ('
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
				while ($row = $this->_db->fetch_row($ignoreRequest))
				{
					$ind = $this->_createTemporary ? 0 : 1;

					// No duplicates!
					if (isset($inserts[$row[$ind]]))
					{
						continue;
					}

					$inserts[$row[$ind]] = $row;
				}
				$this->_db->free_result($ignoreRequest);
				$numSubjectResults = count($inserts);
			}
			else
			{
				$numSubjectResults += $this->_db->affected_rows();
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

	/**
	 * Populates log_search_messages
	 *
	 * @param int $id_search - the id of the search to delete from logs
	 * @param int $maxMessageResults - the maximum number of messages to index
	 *
	 * @return int - the number of indexed results
	 */
	private function _prepare_word_index($id_search, $maxMessageResults)
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

		foreach ($this->_searchWords as $words)
		{
			// Search for this word, assuming we have some words!
			if (!empty($words['indexed_words']))
			{
				// Variables required for the search.
				$search_data = array(
					'insert_into' => ($this->_createTemporary ? 'tmp_' : '') . 'log_search_messages',
					'no_regexp' => $this->noRegexp(),
					'max_results' => $maxMessageResults,
					'indexed_results' => $indexedResults,
					'params' => array(
						'id_search' => !$this->_createTemporary ? $id_search : 0,
						'excluded_words' => $this->_excludedWords,
						'user_query' => !empty($this->_searchParams->_userQuery) ? $this->_searchParams->_userQuery : '',
						'board_query' => !empty($this->_searchParams->_boardQuery) ? $this->_searchParams->_boardQuery : '',
						'topic' => !empty($this->_search_params['topic']) ? $this->param('topic') : 0,
						'min_msg_id' => (int) $this->_searchParams->_minMsgID,
						'max_msg_id' => (int) $this->_searchParams->_maxMsgID,
						'excluded_phrases' => $this->_excludedPhrases,
						'excluded_index_words' => $this->_excludedIndexWords,
						'excluded_subject_words' => $this->_excludedSubjectWords,
					),
				);

				$ignoreRequest = $this->_searchAPI->indexedWordQuery($words, $search_data);

				if (!$this->_db->support_ignore())
				{
					while ($row = $this->_db->fetch_row($ignoreRequest))
					{
						// No duplicates!
						if (isset($inserts[$row[0]]))
						{
							continue;
						}

						$inserts[$row[0]] = $row;
					}
					$this->_db->free_result($ignoreRequest);
					$indexedResults = count($inserts);
				}
				else
				{
					$indexedResults += $this->_db->affected_rows();
				}

				if (!empty($maxMessageResults) && $indexedResults >= $maxMessageResults)
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
	 * 		'select' => string[] - the select columns
	 * 		'from' => string - the table for the FROM clause
	 * 		'inner_join' => string[] - any INNER JOIN
	 * 		'left_join' => string[] - any LEFT JOIN
	 * 		'where' => string[] - the conditions
	 * 		'group_by' => string[] - the fields to group by
	 * 		'parameters' => mixed[] - any parameter required by the query
	 * @param string $query_identifier - a string to identify the query
	 * @param bool $use_old_ids - if true the topic ids retrieved by a previous
	 * call to this function will be used to identify duplicates
	 *
	 * @return int - the number of rows affected by the query
	 */
	private function _build_search_results_log($main_query, $query_identifier, $use_old_ids = false)
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

			while ($row = $this->_db->fetch_assoc($ignoreRequest))
			{
				// No duplicates!
				if ($use_old_ids)
				{
					if (isset($usedIDs[$row['id_topic']]))
					{
						continue;
					}
				}
				else
				{
					if (isset($inserts[$row['id_topic']]))
					{
						continue;
					}
				}

				$usedIDs[$row['id_topic']] = true;
				foreach ($row as $key => $value)
					$inserts[$row['id_topic']][] = (int) $row[$key];
			}
			$this->_db->free_result($ignoreRequest);

			// Now put them in!
			if (!empty($inserts))
			{
				$query_columns = array();
				foreach ($main_query['select'] as $k => $v)
					$query_columns[$k] = 'int';

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
			$num_results = $this->_db->affected_rows();
		}

		return $num_results;
	}
}
