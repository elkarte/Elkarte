<?php

/**
 * This file has all the main functions in it that relate to the Postgre database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Postgresql;

/**
 * PostgreSQL database class, implements database class to control postgresql functions
 */
class Result extends \ElkArte\Database\AbstractResult
{
	/**
	 * {@inheritDoc}
	 */
	public function affected_rows()
	{
		if ($this->details->replaceResults !== null)
			return $this->details->replaceResults;
		elseif ($this->result === null && !($this->details->lastResult))
			return 0;

		$resource = $this->result === null ? $this->details->lastResult : $this->result;
		if (is_resource($resource))
		{
			return pg_affected_rows($resource);
		}
		else
		{
			return 0;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_id()
	{
		// PG doesn't have an equivalent, so we have to find it while doing the query
		return $this->details->insert_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_row()
	{
		// Return the right row.
		return @pg_fetch_row($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function free_result()
	{
		// Just delegate to the native function
		@pg_free_result($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_rows()
	{
		// simply delegate to the native function
		return pg_num_rows($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_fields()
	{
		return pg_num_fields($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function data_seek($counter)
	{
		return pg_result_seek($this->result, $counter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_assoc()
	{
		return pg_fetch_assoc($this->result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_all()
	{
		$results = pg_fetch_all($this->result, PGSQL_ASSOC);

		return empty($results) ? [] : $results;
	}
}
