<?php

/**
 * Standard non full index, non custom index search
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

namespace ElkArte\Search\API;

/**
 * SearchAPI-Standard.class.php, Standard non full index, non custom index search
 *
 * @package Search
 */
class Standard extends SearchAPI
{
	/**
	 * This is the last version of ElkArte that this was tested on, to protect against API changes.
	 * @var string
	 */
	public $version_compatible = 'ElkArte 2.0 dev';

	/**
	 * This won't work with versions of ElkArte less than this.
	 * @var string
	 */
	public $min_elk_version = 'ElkArte 1.0 Beta';

	/**
	 * Standard search is supported by default.
	 * @var boolean
	 */
	public $is_supported = true;

	/**
	 * 
	 * @var object
	 */
	protected $_search_cache = null;

	public function searchQuery($search_params, $search_words, $excluded_words, &$participants, &$search_results, $search)
	{
		global $context, $modSettings;

		$this->_search_cache = new \ElkArte\Search\Cache\Session();
		$search_id = 0;

		if ($this->_search_cache->existsWithParams($context['params']) === false)
		{
			$search_id = $this->_search_cache->increaseId($modSettings['search_pointer'] ?? 0);
			// Store the new id right off.
			updateSettings([
				'search_pointer' => $search_id
			]);

			// Clear the previous cache of the final results cache.
			$search->clearCacheResults($search_id);

			if ($search->param('subject_only'))
			{
				$num_res = $search->getSubjectResults(
					$search_id, $search->humungousTopicPosts
				);
			}
			else
			{
				$num_res = $search->getResults(
					$search_id, $search->humungousTopicPosts, $search->maxMessageResults
				);
				if (empty($num_res))
				{
					throw new \Exception('query_not_specific_enough');
				}
			}

			$this->_search_cache->setNumResults($num_res);
		}

		$topics = array();
		// *** Retrieve the results to be shown on the page
		$participants = $search->addRelevance(
			$topics,
			$search_id,
			(int) $_REQUEST['start'],
			$modSettings['search_results_per_page']
		);
		$this->_num_results = $this->_search_cache->getNumResults();

		return $topics;
	}
}
