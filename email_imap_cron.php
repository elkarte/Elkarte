<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * Should be run from a cron job to fetch messages from an imap mailbox
 * Can be called from scheduled tasks (fake-cron) if needed
 */

// Any output here is not good
error_reporting(0);

// SSI needed to get ElkArte functions
require_once(dirname(__FILE__) . '/SSI.php');

// Get and save the latest emails
postbyemail_imap();

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

	// No imap, why bother?
	if (!function_exists('imap_open'))
		return false;

	// Values used for the connection
	$hostname = !empty($modSettings['maillist_imap_host']) ? $modSettings['maillist_imap_host'] : '';
	$username = !empty($modSettings['maillist_imap_uid']) ? $modSettings['maillist_imap_uid'] : '';
	$password = !empty($modSettings['maillist_imap_pass']) ? $modSettings['maillist_imap_pass'] : '';
	$mailbox = !empty($modSettings['maillist_imap_mailbox']) ? $modSettings['maillist_imap_mailbox'] : 'INBOX';
	$type = !empty($modSettings['maillist_imap_connection']) ? $modSettings['maillist_imap_connection'] : '';

	// Based on the type selected get/set the additonal connection details
	$connection = port_type($type);
	$hostname .= (strpos($hostname, ':') === false) ? ':' . $connection['port'] : '';
	$mailbox = '{' . $hostname . '/' . $connection['protocol'] . $connection['flags'] . '}' . $mailbox;

	// Connect and search for e-mail messages.
	$inbox = @imap_open($mailbox, $username, $password);
	if ($inbox === false)
		return false;

	// Grab all unseen emails
	$emails = imap_search($inbox, 'UNSEEN');

	// If emails are returned, cycle through each...
	if ($emails)
	{
		// You've got mail
		require_once(CONTROLLERDIR . '/Emailpost.controller.php');

		// Make sure we work from the oldest to the newest message
		sort($emails);

		// For every email...
		foreach ($emails as $email_number)
		{
			$email_number = (int) trim($email_number);

			// Get the headers and prefetch the body as well to avoid a second request
			$headers = imap_fetchheader($inbox, $email_number, FT_PREFETCHTEXT);
			$message = imap_body($inbox, $email_number, 0);

			// Create the save-as email
			if (!empty($headers) && !empty($message))
			{
				$email = $headers . "\n" . $message;
				action_pbe_post($email);

				// Mark it for deletion?
				if (!empty($modSettings['maillist_imap_delete']))
				{
					maillist_imap_delete($inbox, $email_number);
					imap_expunge($inbox);
					imap_close($inbox);
				}
			}
		}

		// Close the connection
		imap_close($inbox);
		return true;
	}
	else
		return false;
}

/**
 * Sets port and connection flags based on the chosen protocol
 *
 */
function port_type($type)
{
	switch ($type)
	{
		case 'pop3':
			// Standard POP3 mailbox.
			$protocol = 'POP3';
			$port = 110;
			$flags = '/novalidate-cert';
			break;
		case 'pop3tls':
			// POP3, TLS mode.
			$protocol = 'POP3';
			$port = 110;
			$flags = '/tls/novalidate-cert';
			break;
		case 'pop3ssl':
			// POP3, SSL mode.
			$protocol = 'POP3SSL';
			$port = 995;
			$flags = '/ssl/novalidate-cert';
			break;
		case 'imap':
			// Standard IMAP mailbox.
			$protocol = 'IMAP';
			$port = 143;
			$flags = '/novalidate-cert';
			break;
		case 'imaptls':
			// IMAP in TLS mode.
			$protocol = 'IMAPTLS';
			$port = 143;
			$flags = '/tls/novalidate-cert';
			break;
		case 'imapssl':
			// IMAP in SSL mode.
			$protocol = 'IMAP';
			$port = 993;
			$flags = '/ssl/novalidate-cert';
			break;
		default:
			// Somethings wrong, so use a standard POP3 mailbox.
			$protocol = 'POP3';
			$port = 110;
			$flags = '/novalidate-cert';
			break;
	}

	return array('protocol' => $protocol, 'port' => $port, 'flags' => $flags);
}