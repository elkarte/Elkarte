<?php

/**
 * Utility class for search functionality.
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

namespace ElkArte\Search;

use ElkArte\Errors\Errors;
use ElkArte\Languages\Txt;
use ElkArte\ValuesContainer;

/**
 * Actually do the searches
 */
class SearchApiWrapper
{
	/** @var string when we don't know what to do, use standard search */
	public const DEFAULT_API = 'standard';

	/** @var object Holds instance of the search api in use such as ElkArte\Search\API\Standard_Search */
	protected $_searchAPI;

	/**
	 * Constructor
	 *
	 * @param \ElkArte\ValuesContainer|string $config The searchAPI
	 * @param \ElkArte\Search\SearchParams $searchParams
	 * @package Search
	 */
	public function __construct($config, $searchParams = null)
	{
		if (!is_object($config))
		{
			$config = new ValuesContainer((array) $config);
		}

		$this->load($config, $searchParams);
	}

	/**
	 * Creates a search API and returns the object.
	 *
	 * @param \ElkArte\ValuesContainer $config
	 * @param \ElkArte\Search\SearchParams $searchParams
	 */
	protected function load($config, $searchParams)
	{
		global $txt;

		$searchClass = $config->search_index;

		require_once(SUBSDIR . '/Package.subs.php');

		// Load up the search API we are going to use.
		if (empty($searchClass))
		{
			$searchClass = self::DEFAULT_API;
		}

		// Try to initialize the API
		$fqcn = '\\ElkArte\\Search\\API\\' . ucfirst($searchClass);

		if (class_exists($fqcn) && is_a($fqcn, '\\ElkArte\\Search\\API\\AbstractAPI', true))
		{
			// Create an instance of the search API and check it is valid for this version of the software.
			$this->_searchAPI = new $fqcn($config, $searchParams);
		}

		// An invalid Search API? Log the error and set it to use the standard API
		if (!$this->_searchAPI || (!$this->_searchAPI->isValid()) || !matchPackageVersion(Search::FORUM_VERSION, $this->_searchAPI->min_elk_version . '-' . $this->_searchAPI->version_compatible))
		{
			// Log the error.
			Txt::load('Errors');
			Errors::instance()->log_error(sprintf($txt['search_api_not_compatible'], $fqcn), 'critical');

			$this->_searchAPI = new API\Standard($config, $searchParams);
		}
	}

	/**
	 * Wrapper for postCreated of the SearchAPI
	 *
	 * @param array $msgOptions
	 * @param array $topicOptions
	 * @param array $posterOptions
	 */
	public function postCreated($msgOptions, $topicOptions, $posterOptions)
	{
		if (is_callable(array($this->_searchAPI, 'postCreated')))
		{
			$this->_searchAPI->postCreated($msgOptions, $topicOptions, $posterOptions);
		}
	}

	/**
	 * Wrapper for postModified of the SearchAPI
	 *
	 * @param array $msgOptions
	 * @param array $topicOptions
	 * @param array $posterOptions
	 */
	public function postModified($msgOptions, $topicOptions, $posterOptions)
	{
		if (is_callable(array($this->_searchAPI, 'postModified')))
		{
			$this->_searchAPI->postModified($msgOptions, $topicOptions, $posterOptions);
		}
	}

	/**
	 * Wrapper for topicSplit of the SearchAPI
	 *
	 * @param int $split2_ID_TOPIC
	 * @param int[] $splitMessages
	 */
	public function topicSplit($split2_ID_TOPIC, $splitMessages)
	{
		if (is_callable(array($this->_searchAPI, 'topicSplit')))
		{
			$this->_searchAPI->topicSplit($split2_ID_TOPIC, $splitMessages);
		}
	}

	/**
	 * Wrapper for topicMerge of the SearchAPI
	 *
	 * @param int $id_topic
	 * @param array $topics
	 * @param int[] $affected_msgs
	 * @param string[] $subject array($response_prefix, $target_subject)
	 */
	public function topicMerge($id_topic, $topics, $affected_msgs, $subject)
	{
		if (is_callable(array($this->_searchAPI, 'topicMerge')))
		{
			$this->_searchAPI->topicMerge($id_topic, $topics, $affected_msgs, $subject);
		}
	}

	/**
	 * Wrapper for searchSettings of the SearchAPI
	 *
	 * @param array $config_vars
	 */
	public function searchSettings(&$config_vars)
	{
		if (is_callable(array($this->_searchAPI, 'searchSettings')))
		{
			$this->_searchAPI->searchSettings($config_vars);
		}
	}

	/**
	 * Wrapper for searchQuery of the SearchAPI
	 *
	 * @param string[] $search_words
	 * @param string[] $excluded_words
	 * @param array $participants
	 *
	 * @return array
	 */
	public function searchQuery($search_words, $excluded_words, &$participants)
	{
		return $this->_searchAPI->searchQuery($search_words, $excluded_words, $participants);
	}

	/**
	 * Wrapper for prepareWord of the SearchAPI
	 *
	 * @return string
	 */
	public function prepareWord($phrase, $no_regexp)
	{
		return $this->_searchAPI->prepareWord($phrase, $no_regexp);
	}

	/**
	 * Wrapper for supportsExtended
	 */
	public function supportsExtended()
	{
		return $this->_searchAPI->supportsExtended();
	}

	/**
	 * Wrapper for setExcludedPhrases of the SearchAPI
	 *
	 * @param string[] $phrase An array of phrases to exclude
	 */
	public function setExcludedPhrases($phrase)
	{
		$this->_searchAPI->setExcludedPhrases($phrase);
	}

	/**
	 * Wrapper for setExcludedWords of the SearchAPI
	 *
	 * @param string[] $words An array of words to exclude
	 */
	public function setExcludedWords($words)
	{
		$this->_searchAPI->setExcludedWords($words);
	}

	/**
	 * Wrapper for setSearchArray of the SearchAPI
	 *
	 * @param \ElkArte\Search\SearchArray $searchArray
	 */
	public function setSearchArray(SearchArray $searchArray)
	{
		$this->_searchAPI->setSearchArray($searchArray);
	}

	/**
	 * Wrapper for prepareIndexes of the SearchAPI
	 *
	 * @param string $word
	 * @param string $wordsSearch
	 * @param string $wordsExclude
	 * @param boolean $isExcluded
	 * @param string $excludedSubjectWords
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded, $excludedSubjectWords)
	{
		$this->_searchAPI->prepareIndexes($word, $wordsSearch, $wordsExclude, $isExcluded, $excludedSubjectWords);
	}

	/**
	 * Wrapper for searchSort of the SearchAPI
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted (-1, 0 1)
	 */
	public function searchSort($a, $b)
	{
		return $this->_searchAPI->searchSort($a, $b);
	}

	/**
	 * Wrapper for setWeightFactors of the SearchAPI
	 *
	 * @param \ElkArte\Search\WeightFactors $weights
	 */
	public function setWeightFactors(WeightFactors $weights)
	{
		$this->_searchAPI->setWeightFactors($weights);
	}

	/**
	 * Wrapper for useTemporary of the SearchAPI
	 *
	 * @param bool $use
	 */
	public function useTemporary($use = false)
	{
		$this->_searchAPI->useTemporary($use);
	}

	/**
	 * Returns the number of results obtained from the query.
	 *
	 * @return int
	 */
	public function getNumResults()
	{
		return $this->_searchAPI->getNumResults();
	}
}
