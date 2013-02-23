<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * Should be run from a cron job to fetch messages from an imap mailbox
 * Can be called from scheduled tasks (fake-cron) if needed
 */

// Any output here is not good
error_reporting(0);

// SSI needed to get Elkarte functions
require_once(dirname(__FILE__) . '/SSI.php');

// Get and save the latest emails
$result = postbyemail_imap();

exit(0);

/**
 * postbyemail_imap()
 *
 * Grabs unread messages from an imap account and saves them as .eml files
 * Passes any new messages found to the postby email function for processing
 * Called by a scheduled task or cronjob
 *
 * @return
 */
function postbyemail_imap()
{
	global $modSettings;

	// no imap, why bother?
	if (!function_exists('imap_open'))
		return false;

	// values used for the connections
	// @todo add ssl and tls connections?
	$hostname = !empty($modSettings['email_maillist_imap_host']) ? $modSettings['email_maillist_imap_host'] : '';
	$username = !empty($modSettings['email_maillist_imap_uid']) ? $modSettings['email_maillist_imap_uid'] : '';
	$password = !empty($modSettings['email_maillist_imap_pass']) ? $modSettings['email_maillist_imap_pass'] : '';

	// try to connect
	$inbox = @imap_open($hostname, $username, $password);
	if ($inbox === false)
		return false;

	// grab all unseen emails
	$emails = imap_search($inbox, 'UNSEEN');
	$to_post = array();

	// if emails are returned, cycle through each...
	if ($emails)
	{
		// You've got mail
		require_once(CONTROLLERDIR . '/Emailpost.controller.php');

		// make sure we work from the oldest to the newest message
		sort($emails);

		// for every email...
		foreach($emails as $email_number)
		{
			$output = '';
			$email_number = (int) trim($email_number);

			// Get the headers and prefetch the body as well to avoid a second request
			$headers = imap_fetchheader($inbox, $email_number, FT_PREFETCHTEXT);
			$message = imap_body($inbox, $email_number, 0);

			// create the save-as email
			if (!empty($headers) && !empty($message))
			{
				$email = $headers . "\n" . $message;
				$result = pbe_main($email);

				// mark it for deletion?
				if (!empty($modSettings['email_maillist_imap_delete']))
				{
					maillist_imap_delete($inbox, $email_number);
					imap_expunge($inbox);
					imap_close($inbox);
				}
			}
		}

		// close the connection
		imap_close($inbox);

		return true;
	}
	else
		return false;
}