<?php

/**
 * Should be run from a cron job to fetch messages from an imap mailbox
 * Can be called from scheduled tasks (fake-cron) if needed
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

// Any output here is not good
error_reporting(0);

// Being run as a cron job
if (!defined('ELK'))
{
	global $ssi_guest_access;

	require_once(__DIR__ . '/bootstrap.php');
	$ssi_guest_access = true;
	new Bootstrap(true);
	postbyemail_imap();

	// Need to keep the cli clean on return
	exit(0);
}
// Or a scheduled task
else
{
	postbyemail_imap();
}

/**
 * postbyemail_imap()
 *
 * Starts the posting of new messages found in the imap account
 * or the .eml file
 *
 * Called by a scheduled task or cronjob
 */
function postbyemail_imap()
{
	// No imap, why bother?
	if (!function_exists('imap_open'))
	{
		return false;
	}

	$pbe = new \ElkArte\PbeImap();

	if ($pbe !== false)
	{
		return $pbe->process();
	}
	else
	{
		return false;
	}
}
