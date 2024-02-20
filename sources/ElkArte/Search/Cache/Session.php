<?php

/**
 * This file contains those functions specific to the cached search results in session
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Search\Cache;

use ElkArte\Sessions\SessionIndex;

/**
 * Class Session
 *
 * @package ElkArte\Search\Cache
 */
class Session
{

	/** @var int $_id_search The ID used for searching. */
	protected $_id_search = 0;

	/** @var int Number of results variable. Stores the total number of results. */
	protected $_num_results = -1;

	/** @var array */
	protected $_params = [];

	/** @var string $_session_index Variable to hold the index for storing the search cache in the session. */
	protected $_session_index = 'search_cache';

	/**
	 * Class constructor method.
	 *
	 * @param string $index (optional) The index value to be used in the session index.
	 * @return void
	 */
	public function __construct($index = '')
	{
		if (!empty($index))
		{
			$this->_session_index = 'search_' . $index;
		}
	}

	/**
	 * The __destruct method is called when the object is destroyed.
	 * It creates a new SessionIndex object with the session index, id search, number of results, and params as parameters.
	 *
	 * @return void
	 */
	public function __destruct()
	{
		new SessionIndex($this->_session_index, array(
			'id_search' => $this->_id_search,
			'num_results' => $this->_num_results,
			'params' => $this->_params,
		));
	}

	/**
	 * Increases the search ID value by one and returns the updated ID.
	 *
	 * @param int $pointer (optional) The starting search ID pointer. Default is 0.
	 * @return int The updated ID value.
	 */
	public function increaseId($pointer = 0)
	{
		$this->_id_search = (int) $pointer;
		++$this->_id_search;

		if ($this->_id_search > 255)
		{
			$this->_id_search = 0;
		}

		return $this->getId();
	}

	/**
	 * Returns the ID of the search.
	 *
	 * @return int The ID of the search.
	 */
	public function getId()
	{
		return $this->_id_search;
	}

	/**
	 * Checks if the given parameters match the internal params.
	 *
	 * @param mixed $params The parameters to check against the internal params.
	 *
	 * @return bool Returns true if the given parameters match the internal params,
	 *              false otherwise.
	 */
	public function existsWithParams($params)
	{
		return $this->_params === $params;
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

	public function setNumResults($num_results = 0)
	{
		$this->_num_results = (int) $num_results;
	}
}
