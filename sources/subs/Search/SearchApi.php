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

/**
 * Actually do the searches
 */
class SearchApi
{
	const DEFAULT_API = 'standard';

	/**
	 * Holds instance of the search api in use such as ElkArte\Search\API\Standard_Search
	 * @var null|object
	 */
	protected $_searchAPI = null;

	/**
	 * Constructor
	 *
	 * @package Search
	 */
	public function __construct($current_index)
	{
		$this->load($current_index);
	}

	/**
	 * Wrapper for postCreated of the SearchAPI
	 *
	 * @param mixed[] $msgOptions
	 * @param mixed[] $topicOptions
	 * @param mixed[] $posterOptions
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
	 * @param mixed[] $msgOptions
	 * @param mixed[] $topicOptions
	 * @param mixed[] $posterOptions
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
	 * @param mixed[] $topics
	 * @param int[] $affected_msgs
	 * @param string $subject
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
	 * @param mixed[] $config_vars
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
	 * @param mixed[] $search_params
	 * @param string[] $search_words
	 * @param string[] $excluded_words
	 * @param bool[] $participants
	 * @param string[] $search_results
	 * @param \ElkArte\Search\Search $search
	 *
	 * @return mixed[]
	 */
	public function searchQuery($search_params, $search_words, $excluded_words, &$participants, &$search_results, $search)
	{
		return $this->_searchAPI->searchQuery($search_params, $search_words, $excluded_words, $participants, $search_results, $search);
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
	 * Returns the number of results obtained from the query.
	 *
	 * @return int
	 */
	public function getNumResults()
	{
		return $this->_searchAPI->getNumResults();
	}

	/**
	 * Creates a search API and returns the object.
	 *
	 * @param string $searchClass
	 */
	protected function load($searchClass = '')
	{
		global $txt;

		require_once(SUBSDIR . '/Package.subs.php');
		\Elk_Autoloader::instance()->register(SUBSDIR . '/Search', '\\ElkArte\\Search');

		// Load up the search API we are going to use.
		if (empty($searchClass))
		{
			$searchClass = self::DEFAULT_API;
		}

		// Try to initialize the API
		$fqcn = 'API\\' . ucfirst($searchClass);
		if (class_exists($fqcn) && is_a($fqcn, 'API\\SearchAPI', true))
		{
			// Create an instance of the search API and check it is valid for this version of the software.
			$this->_searchAPI = new $fqcn;
		}

		// An invalid Search API? Log the error and set it to use the standard API
		if (!$this->_searchAPI || (!$this->_searchAPI->isValid()) || !matchPackageVersion(Search::FORUM_VERSION, $this->_searchAPI->min_elk_version . '-' . $this->_searchAPI->version_compatible))
		{
			// Log the error.
			theme()->getTemplates()->loadLanguageFile('Errors');
			\Errors::instance()->log_error(sprintf($txt['search_api_not_compatible'], $fqcn), 'critical');

			$this->_searchAPI = new API\Standard;
		}
	}
}
