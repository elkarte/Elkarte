<?php

/**
 * PHP 5.3 compatibility for PHP 5.4's SessionHandler
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */

namespace ElkArte\sources\subs\SessionHandler;

/**
 * The SessionHandler class.
 *
 * PHP 5.3 compatibility for PHP 5.4's SessionHandler
 *
 * @link http://php.net/manual/en/class.sessionhandler.php
 */
class SessionHandler extends \AbstractModel implements SessionHandlerInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function close()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function destroy($sessionId)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function gc($maxlifetime)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function open($savePath, $sessionId)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($sessionId)
	{
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($sessionId, $data)
	{
		return true;
	}
}
