<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

function currentMessage($messages_request, $reset = false)
{
	// Can't work with a database without a database :P
	$db = database();

	// Start from the beginning...
	if ($reset)
		return $db->data_seek($messages_request, 0);

	// Attempt to get the next message.
	$message = $db->fetch_assoc($messages_request);
	if (!$message)
	{
		$db->free_result($messages_request);
		return false;
	}

	return $message;
}
