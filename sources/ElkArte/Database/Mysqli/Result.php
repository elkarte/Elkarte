<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Mysqli;

use ElkArte\Database\AbstractResult;

/**
 * SQL database class, implements database class to control mysql functions
 */
class Result extends AbstractResult
{
	/**
	 * {@inheritDoc}
	 */
	public function affected_rows()
	{
		return mysqli_affected_rows($this->details->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_id()
	{
		// MySQL doesn't need the table.
		return mysqli_insert_id($this->details->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_row()
	{
		// Just delegate to MySQL's function
		return mysqli_fetch_row($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function free_result()
	{
		// Just delegate to MySQL's function
		if($this->result instanceof mysqli_result)
			mysqli_free_result($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_rows()
	{
		// Simply delegate to the native function
		return mysqli_num_rows($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_fields()
	{
		return mysqli_num_fields($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function data_seek($counter)
	{
		// Delegate to native mysql function
		return mysqli_data_seek($this->result, $counter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_assoc()
	{
		return mysqli_fetch_assoc($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_all()
	{
		return mysqli_fetch_all($this->result, MYSQLI_ASSOC);
	}
}
