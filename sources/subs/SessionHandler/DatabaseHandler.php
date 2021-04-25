<?php

/**
 * Our handler for database sessions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.7
 *
 */

namespace ElkArte\sources\subs\SessionHandler;

/**
 * Class DatabaseHandler
 *
 * @package ElkArte\sources\subs\SessionHandler
 */
class DatabaseHandler extends SessionHandler
{
	/**
	 * {@inheritdoc}
	 */
	public function destroy($sessionId)
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
	 * {@inheritdoc}
	 */
	public function gc($maxLifetime)
	{
		// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
		if (!empty($this->_modSettings['databaseSession_lifetime']) && ($maxLifetime <= 1440 || $this->_modSettings['databaseSession_lifetime'] > $maxLifetime))
		{
			$maxLifetime = max($this->_modSettings['databaseSession_lifetime'], 60);
		}

		// Clean up after yerself ;).
		$this->_db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			array(
				'last_update' => time() - $maxLifetime,
			)
		);

		return $this->_db->affected_rows() != 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($sessionId)
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) == 0)
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
		list ($sessionData) = $this->_db->fetch_row($result);
		$this->_db->free_result($result);

		return empty($sessionData) ? '' : $sessionData;
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($sessionId, $data)
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) == 0)
		{
			return false;
		}

		// Update the session data, replace if necessary
		$this->_db->insert('replace',
			'{db_prefix}sessions',
			array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
			array($sessionId, $data, time()),
			array('session_id')
		);

		return true;
	}
}
