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
 * @version 2.0 Beta 1
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
		$this->_modSettings = new ValuesContainer($modSettings ?: []);
	}

	/**
	 * Validates a session ID against the expected format.
	 *
	 * @param string $sessionId
	 * @return bool
	 */
	protected function isValidSessionId(string $sessionId): bool
	{
		return preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) === 1;
	}

	/**
	 * {@inheritDoc}
	 */
	public function destroy($sessionId): bool
	{
		// Better safe than sorry
		if (!$this->isValidSessionId($sessionId))
		{
			return false;
		}

		// Just delete the row...
		$this->_db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}',
			[
				'session_id' => $sessionId,
			]
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function gc($maxLifetime): int|false
	{
		// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
		if (!empty($this->_modSettings['databaseSession_lifetime']) && ($maxLifetime <= 1440 || $this->_modSettings['databaseSession_lifetime'] > $maxLifetime))
		{
			$maxLifetime = max($this->_modSettings['databaseSession_lifetime'], 60);
		}

		// Clean up after yourself.
		$result = $this->_db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			[
				'last_update' => time() - $maxLifetime,
			]
		);

		return $result->affected_rows();
	}

	/**
	 * {@inheritDoc}
	 */
	public function read($sessionId): string
	{
		if (!$this->isValidSessionId($sessionId))
		{
			return '';
		}

		// Look for it in the database.
		$result = $this->_db->query('', '
			SELECT data
			FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}
			LIMIT 1',
			[
				'session_id' => $sessionId,
			]
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
		// Don't bother writing the session data if cookies are disabled
		if (empty($_COOKIE))
		{
			return true;
		}

		if (!$this->isValidSessionId($sessionId))
		{
			return false;
		}

		// Update the session data, replace it if necessary
		$this->_db->replace(
			'{db_prefix}sessions',
			['session_id' => 'string', 'data' => 'string', 'last_update' => 'int'],
			[$sessionId, $data, time()],
			['session_id']
		);

		return true;
	}
}
