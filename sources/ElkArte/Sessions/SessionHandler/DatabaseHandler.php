<?php

/**
 * Our handler for database sessions
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

namespace ElkArte\Sessions\SessionHandler;

use ElkArte\Database\QueryInterface;
use ElkArte\Helper\ValuesContainer;

/**
 * Class DatabaseHandler
 *
 * @package ElkArte\Sessions
 */
class DatabaseHandler extends \SessionHandler
{
	/** @var QueryInterface The database object */
	protected $_db;

	/** @var object The modSettings */
	protected $_modSettings = [];

	/**
	 * Make "global" items available to the class
	 *
	 * @param object|null $db
	 */
	public function __construct($db = null)
	{
		global $modSettings;

		$this->_db = $db ?: database();
		$this->_modSettings = new ValuesContainer($modSettings ?: array());
	}

	/**
	 * {@inheritDoc}
	 */
	public function destroy($sessionId): bool
	{
		// Better safe than sorry
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) == 0)
		{
			return false;
		}

		// Just delete the row...
		$this->_db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}',
			array(
				'session_id' => $sessionId,
			)
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[\ReturnTypeWillChange]
	public function gc($maxLifetime)
	{
		// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
		if (!empty($this->_modSettings['databaseSession_lifetime']) && ($maxLifetime <= 1440 || $this->_modSettings['databaseSession_lifetime'] > $maxLifetime))
		{
			$maxLifetime = max($this->_modSettings['databaseSession_lifetime'], 60);
		}

		// Clean up after yerself ;).
		$result = $this->_db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			array(
				'last_update' => time() - $maxLifetime,
			)
		);

		return $result->affected_rows() !== 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read($sessionId): string
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) !== 1)
		{
			return '';
		}

		// Look for it in the database.
		$result = $this->_db->query('', '
			SELECT data
			FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}
			LIMIT 1',
			array(
				'session_id' => $sessionId,
			)
		);
		[$sessionData] = $result->fetch_row();
		$result->free_result();

		return empty($sessionData) ? '' : $sessionData;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($sessionId, $data): bool
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) !== 1)
		{
			return false;
		}

		// Update the session data, replace if necessary
		$this->_db->replace(
			'{db_prefix}sessions',
			['session_id' => 'string', 'data' => 'string', 'last_update' => 'int'],
			[$sessionId, $data, time()],
			['session_id']
		);

		return true;
	}
}
