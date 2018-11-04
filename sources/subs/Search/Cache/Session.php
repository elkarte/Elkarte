<?php

/**
 * This file contains those functions specific to the various verification controls
 * used to challenge users, and hopefully robots as well.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Search\Cache;

use \ElkArte\sources\subs\SessionIndex;

class Session
{
	protected $_id_search = 0;
	protected $_num_results = -1;
	protected $_params = [];
	protected $_session_index = 'search_cache';

	public function __construct($index = '')
	{
		if (!empty($index))
		{
			$this->_session_index = 'search_' . $index;
		}
	}

	public function __destruct()
	{
		new SessionIndex($this->_session_index, array(
			'id_search' => $this->_id_search,
			'num_results' => $this->_num_results,
			'params' => $this->_params,
		));
	}

	public function getId()
	{
		return $this->_id_search;
	}

	public function increaseId($pointer = 0)
	{
		$this->_id_search = (int) $pointer;
		$this->_id_search += 1;

		if ($this->_id_search > 255)
		{
			$this->_id_search = 0;
		}

		return $this->getId();
	}

	public function existsWithParams($params)
	{
		return $this->_params == $params;
	}

	public function setNumResults($num_results = 0)
	{
		$this->_num_results = (int) $num_results;
	}

	/**
	 * Returns the number of results obtained from the query.
	 *
	 * @return int
	 */
	public function getNumResults()
	{
		return $this->_num_results;
	}
}