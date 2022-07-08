<?php

/**
 * This file provides an implementation of the most common functions needed
 * for the database drivers to work.
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

namespace ElkArte\Database;

use ElkArte\ValuesContainer;

/**
 * Abstract database class, implements database to control functions
 */
abstract class AbstractResult
{
	/**
	 * Result object
	 *
	 * @var resource
	 */
	protected $result = null;

	/**
	 * @var \ElkArte\ValuesContainer
	 */
	protected $details = null;

	/**
	 * Constructor.
	 *
	 * @param $result
	 * @param null $details
	 */
	public function __construct($result, $details = null)
	{
		$this->result = $result;
		$this->details = $details ?? new ValuesContainer();
	}

	/**
	 * The destructor is used to free the results.
	 */
	public function __destruct()
	{
		if (!is_bool($this->result))
		{
			$this->free_result();
		}
	}

	/**
	 * Free the resultset.
	 */
	abstract public function free_result();

	/**
	 * Returns the result object as obtained from the query function
	 *
	 * @deprecated - no longer needed
	 */
	public function getResultObject()
	{
		return $this->result;
	}

	/**
	 * Returns the value of a "detail"
	 *
	 * @param string $index
	 * @return mixed
	 */
	public function getDetail($index)
	{
		return $this->details[$index] ?? null;
	}

	/**
	 * Update details
	 *
	 * @param array $details
	 */
	public function updateDetails($details)
	{
		foreach ($details as $key => $val)
		{
			$this->details[$key] = $val;
		}
	}

	/**
	 * Allows to check if the results obtained are valid.
	 */
	public function hasResults()
	{
		return !empty($this->result);
	}

	/**
	 * Affected rows from previous operation.
	 *
	 * @return int
	 */
	abstract public function affected_rows();

	/**
	 * Fetch a row from the result set given as parameter.
	 */
	abstract public function fetch_row();

	/**
	 * Fetch all the results at once.
	 */
	abstract public function fetch_all();

	/**
	 * Get the number of rows in the result.
	 *
	 * @return int
	 */
	abstract public function num_rows();

	/**
	 * Get the number of fields in the resultset.
	 *
	 * @return int
	 */
	abstract public function num_fields();

	/**
	 * Reset the internal result pointer.
	 *
	 * @param int $counter
	 *
	 * @return bool
	 */
	abstract public function data_seek($counter);

	/**
	 * Last inserted id.
	 *
	 * @return int|string
	 */
	abstract public function insert_id();

	/**
	 * Returns the results calling a callback on each row.
	 *
	 * The callback is supposed to accept as argument the row of data fetched
	 * by the query from the database.
	 *
	 * @param callable|null|object|string $callback
	 * @param array|null
	 * @return array
	 */
	public function fetch_callback($callback, $seeds = null)
	{
		$results = $seeds !== null ? (array) $seeds : array();

		if (!is_bool($this->result))
		{
			while (($row = $this->fetch_assoc()))
			{
				$results[] = call_user_func($callback, $row);
			}
		}
		else
		{
			$results = (bool) $this->result;
		}

		return $results;
	}

	/**
	 * Fetch next result as association.
	 */
	abstract public function fetch_assoc();
}
