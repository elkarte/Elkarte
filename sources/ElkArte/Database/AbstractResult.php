<?php

/**
 * This file provides an implementation of the most common functions needed
 * for the database drivers to work.
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

namespace ElkArte\Database;

use ElkArte\ValuesContainer;

/**
 * Abstract database class, implements database to control functions
 */
abstract class AbstractResult
{
	/**
	 * Result object
	 * @var resource
	 */
	protected $result = null;

	/**
	 * @var \ElkArte\ValuesContainer
	 */
	protected $details = null;

	/**
	 * Constructor.
	 * @param $result
	 * @param null $details
	 */
	public function __construct($result, $details = null)
	{
		$this->result = $result;
		$this->details = $details ?? new ValuesContainer();
	}

	/**
	 * Returns the result object as obtained from the query function
	 */
	public function getResultObject()
	{
		return $this->result;
	}

	/**
	 * Affected rows from previous operation.
	 *
	 * @return int
	 */
	abstract public function affected_rows();

	/**
	 * Fetch next result as association.
	 */
	abstract public function fetch_assoc();

	/**
	 * Fetch a row from the result set given as parameter.
	 */
	abstract public function fetch_row();

	/**
	 * Free the resultset.
	 */
	abstract public function free_result();

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
	 * @param integer $counter
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
}
