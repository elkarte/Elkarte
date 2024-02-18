<?php

/**
 * Grabs unread messages from an imap account
 * Passes any new messages found to the postby email function for processing
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Maillist;

use ElkArte\AbstractModel;
use ElkArte\Errors;
use ElkArte\EventManager;

/**
 * Grabs unread messages from an imap account
 * Passes any new messages found to the "postby" email function for processing
 */
class MaillistImap extends AbstractModel
{
	/** @var string The name of the imap host */
	protected $_hostname = '';

	/** @var string The username to access the imap account */
	protected $_username = '';

	/** @var string The password of the imap account */
	protected $_password = '';

	/** @var string The name of the folder where messages are stored */
	protected $_mailbox = 'INBOX';

	/** @var string Type of connection: pop3, pop3tls, pop3ssl, imap, imaptls, imapssl */
	protected $_type = '';

	/** @var bool If the host is gmail. Gmail requires more processing when deleting emails */
	protected $_is_gmail = false;

	/** @var bool Are we going to delete the emails once read? */
	protected $_delete = false;

	/** @var null The inbox object */
	protected $_inbox;

	/** @var string imap_open $mailbox string */
	protected $_imap_server = '';

	/**
	 * The constructor, prepares few variables.
	 *
	 * $modSettings - May contain a few needed settings:
	 *    - maillist_imap_host
	 *    - maillist_imap_uid
	 *    - maillist_imap_pass
	 *    - maillist_imap_mailbox
	 *    - maillist_imap_connection
	 *    - maillist_imap_delete
	 */
	public function __construct()
	{
		parent::__construct();

		// Values used for the connection
		$this->_hostname = $this->_modSettings->maillist_imap_host('');
		$this->_username = $this->_modSettings->maillist_imap_uid('');
		$this->_password = $this->_modSettings->maillist_imap_pass('');
		$this->_mailbox = $this->_modSettings->maillist_imap_mailbox('');
		$this->_type = $this->_modSettings->maillist_imap_connection('');

		// Values used for options
		$this->_delete = (bool) $this->_modSettings->maillist_imap_delete;
		$this->_is_gmail = strpos($this->_hostname, '.gmail.') !== false;
	}

	/**
	 * Does the actual processing of the inbox posting new emails as needed
	 */
	public function process()
	{
		$this->_get_inbox();

		if ($this->_inbox === false)
		{
			return false;
		}

		// Grab all unseen emails, return by message ID
		$emails = imap_search($this->_inbox, 'UNSEEN', SE_UID);

		// You've got mail,
		if (!empty($emails))
		{
			// Initialize Maillist controller
			$controller = new MaillistPost(new EventManager());
			$controller->setUser($this->user);

			// Make sure we work from the oldest to the newest message
			sort($emails);

			// For every email...
			foreach ($emails as $email_uid)
			{
				$email = $this->_fetch_email($email_uid);

				// Create the save-as email
				if (!empty($email))
				{
					$controller->action_pbe_post($email);

					// Mark it for deletion?
					if ($this->_delete)
					{
						$this->_delete_email($email_uid);
					}
				}
			}
		}

		// Close the connection
		imap_close($this->_inbox);

		return !empty($emails);
	}

	/**
	 * Finds the inbox of the email
	 */
	protected function _get_inbox()
	{
		$this->_inbox = $this->_checkValues();

		if ($this->_inbox)
		{
			// Based on the type selected get/set the additional connection details
			$connection = $this->_port_type();
			$this->_hostname .= (strpos($this->_hostname, ':') === false) ? ':' . $connection['port'] : '';
			$this->_imap_server = '{' . $this->_hostname . '/' . $connection['protocol'] . $connection['flags'] . '}';
			$this->_mailbox = $this->_imap_server . imap_utf7_encode($this->_mailbox);

			// Connect to the mailbox using the supplied credentials and protocol
			$this->_inbox = imap_open($this->_mailbox, $this->_username, $this->_password);

			// Connection error, logging may help debug
			if ($this->_inbox === false)
			{
				$imap_error = imap_last_error();
				if (!empty($imap_error))
				{
					Errors::instance()->log_error($imap_error, 'debug', 'IMAP');
				}
			}
		}
	}

	/**
	 * Simply checks to see that the values from the ACP are complete
	 *
	 * @return bool
	 */
	private function _checkValues()
	{
		// I suppose that without this information we can't do anything.
		return !((empty($this->_hostname) || empty($this->_username) || empty($this->_password)));
	}

	/**
	 * Sets port and connection flags based on the chosen protocol
	 */
	protected function _port_type()
	{
		switch ($this->_type)
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

		return ['protocol' => $protocol, 'port' => $port, 'flags' => $flags];
	}

	/**
	 * Retrieves and composes and email (headers+message) from and imap inbox
	 *
	 * @param int $email_uid - The email id
	 *
	 * @return string
	 */
	protected function _fetch_email($email_uid)
	{
		// Get the headers and prefetch the body as well to avoid a second request
		$headers = imap_fetchheader($this->_inbox, $email_uid, FT_PREFETCHTEXT | FT_UID);
		$message = imap_body($this->_inbox, $email_uid, FT_UID);

		return !empty($headers) && !empty($message) ? $headers . "\n" . $message : '';
	}

	/**
	 * Deletes an email from an imap inbox
	 *
	 * @param int $email_uid - The email id
	 */
	protected function _delete_email($email_uid)
	{
		// Gmail labels make this more complicated
		if ($this->_is_gmail)
		{
			// If using gmail, we may need the trash bin name as well
			$trash_bin = $this->_get_trash_folder();
			imap_mail_move($this->_inbox, $email_uid, $trash_bin, CP_UID);
		}

		imap_delete($this->_inbox, $email_uid, FT_UID);
		imap_expunge($this->_inbox);
	}

	/**
	 * Find and return the proper recycle bin for gmail
	 *
	 * @return string
	 */
	protected function _get_trash_folder()
	{
		// Known names for the trash bin, I'm sure there are more
		$trashBox = ['[Google Mail]/Bin', '[Google Mail]/Trash', '[Gmail]/Bin', '[Gmail]/Trash'];

		call_integration_hook('integrate_imap_trash_folders', [&$trashBox]);

		// Get all the folders / labels
		$mailBoxes = imap_list($this->_inbox, $this->_imap_server, '*');

		// Check the names to see if one is known as a trashbin
		foreach ($mailBoxes as $mailbox)
		{
			$name = str_replace($this->_imap_server, '', $mailbox);
			if (in_array($name, $trashBox, true))
			{
				return $name;
			}
		}

		return 'Trash';
	}
}
