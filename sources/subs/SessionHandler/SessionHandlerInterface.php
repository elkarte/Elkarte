<?php

/**
 * PHP 5.3 compatibility for PHP 5.4's SessionHandlerInterface
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
 *
 */

namespace ElkArte\sources\subs\SessionHandler;

/**
 * SessionHandler Interface
 *
 * PHP 5.3 compatibility for PHP 5.4's SessionHandlerInterface
 *
 * @link http://php.net/manual/en/class.sessionhandlerinterface.php
 */
interface SessionHandlerInterface
{
	/**
	 * Close the session
	 *
	 * @return bool The return value (usually TRUE on success, FALSE on failure).
	 *              Note this value is returned internally to PHP for processing.
	 */
	public function close();

	/**
	 * Destroy a session
	 *
	 * @param   int   $sessionId  The session ID being destroyed.
	 *
	 * @return  bool  The return value (usually TRUE on success, FALSE on failure).
	 *                Note this value is returned internally to PHP for processing.
	 */
	public function destroy($sessionId);

	/**
	 * Cleanup old sessions
	 *
	 * @param   int  $maxLifetime  Sessions that have not updated for
	 *                             the last maxLifetime seconds will be removed.
	 *
	 * @return  bool  The return value (usually TRUE on success, FALSE on failure).
	 *                Note this value is returned internally to PHP for processing.
	 */
	public function gc($maxLifetime);

	/**
	 * Initialize session
	 *
	 * @param   string  $savePath  The path where to store/retrieve the session.
	 * @param   string  $sessionId        The session id.
	 *
	 * @return  bool  The return value (usually TRUE on success, FALSE on failure).
	 *                Note this value is returned internally to PHP for processing.
	 */
	public function open($savePath, $sessionId);

	/**
	 * Read session data
	 *
	 * @param   string  $sessionId  The session id to read data for.
	 *
	 * @return  string  Returns an encoded string of the read data.
	 *                  If nothing was read, it must return an empty string.
	 *                  Note this value is returned internally to PHP for processing.
	 */
	public function read($sessionId);

	/**
	 * Write session data
	 *
	 * @param   string  $sessionId    The session id.
	 * @param   string  $data  The encoded session data. This data is the
	 *                         result of the PHP internally encoding
	 *                         the $_SESSION super global to a serialized
	 *                         string and passing it as this parameter.
	 *                         Please note sessions use an alternative serialization method.
	 *
	 * @return   bool  The return value (usually TRUE on success, FALSE on failure).
	 *                 Note this value is returned internally to PHP for processing.
	 */
	public function write($sessionId, $data);
}
