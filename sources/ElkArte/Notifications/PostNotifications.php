<?php

/**
 * This deals with sending email notifications to members who have elected to receive them
 * when things happen to a topic, or board, which they have subscribed.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Notifications;

use ElkArte\AbstractModel;
use ElkArte\Languages\Loader;
use ElkArte\Mail\BuildMail;
use ElkArte\Mail\PreparseMail;
use ElkArte\User;

class PostNotifications extends AbstractModel
{
	/** Notification levels, what the user has selected in profile, **for reference** */
	private const NOTIFY_TYPE_ALL_MESSAGES = 1;
	private const NOTIFY_TYPE_MODERATION_ONLY_IF_STARTED = 2;
	private const NOTIFY_TYPE_ONLY_REPLIES = 3;
	private const NOTIFY_TYPE_NOTHING_AT_ALL = 4;

	/** Notification, **for reference** */
	private const NOTIFY_REPLY = 'reply';
	private const NOTIFY_STICKY = 'sticky';
	private const NOTIFY_LOCK = 'lock';
	private const NOTIFY_UNLOCK = 'unlock';
	private const NOTIFY_REMOVE = 'remove';
	private const NOTIFY_MOVE = 'move';
	private const NOTIFY_MERGE = 'merge';
	private const NOTIFY_SPLIT = 'split';

	/** Notification Regularity, **for reference** */
	private const REGULARITY_NOTHING = 99;
	private const REGULARITY_INSTANTLY = 0;
	private const REGULARITY_FIRST_UNREAD_MSG = 1;
	private const REGULARITY_DAILY_DIGEST = 2;
	private const REGULARITY_WEEKLY_DIGEST = 3;

	/** @var int how many emails were sent */
	protected $sent = 0;

	/** @var array If the topic was sent via a board notification to prevent double sending */
	protected $boards = [];

	/** @var string Humm could it be the current language? */
	protected $current_language = '';

	/**
	 * PostNotifications constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		// Load in dependencies
		require_once(SUBSDIR . '/Emailpost.subs.php');
		require_once(SUBSDIR . '/Notification.subs.php');
		require_once(SUBSDIR . '/Mail.subs.php');
	}

	/**
	 * The function automatically finds the subject and its board, and then
	 * checks permissions for each member who is "signed up" for notifications.
	 *
	 * It will not send 'reply' notifications more than once in a row.  It will send new replies to topics on
	 * subscribed boards and/or subscribed (watched) topics.
	 *
	 * @param int[]|int $topics - represents the topics the action is happening to.
	 * @param string $type - can be any of reply, sticky, lock, unlock, remove,
	 *                       move, merge, and split.  An appropriate message will be sent for each.
	 * @param int[]|int $members_only = array() - restrict to only send the notification to this list, otherwise all
	 * @param array $pbe = array() - PBE user_info if this is being run as a result of an email posting
	 */
	public function sendNotifications($topics, $type, $members_only = [], $pbe = [])
	{
		global $txt;

		// Can't do it if there's no topics.
		if (empty($topics))
		{
			return;
		}

		// It must be an array - it must!
		if (!is_array($topics))
		{
			$topics = [$topics];
		}

		// I hope we are not sending one of those silly moderation notices
		if ($type !== self::NOTIFY_REPLY && !empty($this->_modSettings['pbe_no_mod_notices']) && $this->isUsingMailList())
		{
			return;
		}

		// Who are we?
		$user_id = $this->_getUserID($pbe);
		$user_language = $this->_getUserLanguage($pbe);

		// Get the subject, body and basic poster details, number of attachments if any
		list($boards_index, $topicData) = getTopicInfos($topics, $type);

		// Nada?
		if (empty($topicData))
		{
			trigger_error('sendNotifications(): topics not found', E_USER_NOTICE);
		}

		// Just in case they've gone walkies, or trying to get to something they no longer can
		$topics = array_keys($topicData);
		if (empty($topics))
		{
			return;
		}

		// Insert all of these items into the digest log for those who want notifications later.
		$digest_insert = [];
		foreach ($topicData as $data)
		{
			$digest_insert[] = [$data['topic'], $data['last_id'], $type, (int) $data['exclude']];
		}
		insertLogDigestQueue($digest_insert);

		// Using the posting email function then process posts to subscribed boards
		if ($this->isUsingMailList())
		{
			$this->sendBoardTopicNotifications($topicData, $user_id, $boards_index, $type, $members_only);
		}

		// Find the members with watch notifications set for this topic, it will skip any sent above
		$this->sendTopicNotifications($user_id, $topicData, $type, $members_only);

		if (!empty($this->current_language) && $this->current_language !== $user_language)
		{
			$lang_loader = new Loader(null, $txt, database());
			$lang_loader->load('Post', false);
		}

		// Sent!
		if ($type === self::NOTIFY_REPLY && !empty($this->sent))
		{
			updateLogNotify($user_id, $topics);
		}
	}

	/**
	 * If we are using the mail list functionality
	 *
	 * @return bool
	 */
	public function isUsingMailList()
	{
		return !empty($this->_modSettings['maillist_enabled']) && !empty($this->_modSettings['pbe_post_enabled']);
	}

	/**
	 * The current user, which might be the one posting via email
	 *
	 * @param array $pbe
	 * @return int
	 */
	private function _getUserID($pbe)
	{
		return (!empty($pbe['user_info']['id']) && !empty($this->_modSettings['maillist_enabled']))
			? (int) $pbe['user_info']['id'] : User::$info->id;
	}

	/**
	 * The language of the poster
	 *
	 * @param array $pbe
	 * @return string
	 */
	private function _getUserLanguage($pbe)
	{
		return (!empty($pbe['user_info']['language']) && !empty($this->_modSettings['maillist_enabled']))
			? $pbe['user_info']['language'] : User::$info->language;
	}

	/**
	 * Sends a new topic notification to members subscribed to a board
	 *
	 * @param array $topicData the topic in question
	 * @param int $user_id the poster
	 * @param int[] $boards_index the boards the topic(s) are on
	 * @param string $type see Notify Types
	 * @param int|int[] $members_only if only sending to a select list of members
	 */
	public function sendBoardTopicNotifications($topicData, $user_id, $boards_index, $type, $members_only)
	{
		global $language;

		// Using the posting email function in either group or list mode
		$maillist = $this->isUsingMailList();

		// Fetch the members with *board* notifications on.
		$boardNotifyData = fetchBoardNotifications($user_id, $boards_index, $type, $members_only);

		// Check each board notification entry against the topic
		foreach ($boardNotifyData as $notifyDatum)
		{
			// For this member/board, loop through the topics and see if we should send it
			foreach ($topicData as $id_topic => $topicDatum)
			{
				// Don't if it is not from the right board
				if ($topicDatum['board'] !== $notifyDatum['id_board'])
				{
					continue;
				}

				// Don't when they want moderation notifications for their topics, and it is not theirs.
				if ($type !== self::NOTIFY_REPLY && $topicDatum['id_member_started'] !== $notifyDatum['id_member']
					&& $notifyDatum['notify_types'] === self::NOTIFY_TYPE_MODERATION_ONLY_IF_STARTED)
				{
					continue;
				}

				// Don't if they don't have notification permissions
				$email_perm = true;
				if (!validateNotificationAccess($notifyDatum, $maillist, $email_perm))
				{
					continue;
				}

				$needed_language = empty($notifyDatum['lngfile']) || empty($this->_modSettings['userLanguage']) ? $language : $notifyDatum['lngfile'];
				$this->_checkLanguage($needed_language);

				// Set the mail template
				$message_type = $this->setMessageTemplate($type, $notifyDatum);

				// Set the replacement values for the template
				$replacements = $this->setTemplateReplacements($topicDatum, $notifyDatum, $id_topic, $type, 'board');

				// Give them a way to add in their own replacements
				call_integration_hook('integrate_notification_replacements', [&$replacements, $notifyDatum, $type, $this->current_language]);

				// Send moderation notices, "instantly" notifications, and any that have not been sent.
				if ($type !== self::NOTIFY_REPLY || empty($notifyDatum['notify_regularity']) || empty($notifyDatum['sent']))
				{
					// If they have PBE access, and it is on ... use those templates.
					$template = ($email_perm && $type === self::NOTIFY_REPLY && $this->canSendPostBody($notifyDatum) ? 'pbe_' : '') . $message_type;
					$emaildata = loadEmailTemplate($template, $replacements, $needed_language, true);

					$sendMail = new BuildMail();
					$sendMail->setEmailReplacements($replacements);

					// If using the maillist functions, we adjust who this is coming from
					if ($email_perm && $type === self::NOTIFY_REPLY && $this->canSendPostBody($notifyDatum))
					{
						$email_from = $this->_getEmailFrom($topicDatum);
						$from_wrapper = $this->_getFromWrapper();

						$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], $email_from, 'm' . $topicDatum['last_id'], true, 3, false, $from_wrapper, $id_topic);
					}
					else
					{
						$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicDatum['last_id'], true);
					}

					// Make a note that this member was sent this topic
					$this->sent++;
					$this->boards[$notifyDatum['id_member']][$id_topic] = 1;
				}
			}
		}
	}

	/**
	 * Checks if the language in use is what the user needs and if not loads the right one
	 *
	 * @param string $needed_language
	 * @uses Post language file
	 */
	private function _checkLanguage($needed_language)
	{
		global $txt;

		if (empty($this->current_language) || $this->current_language !== $needed_language)
		{
			$lang_loader = new Loader($needed_language, $txt, database());
			$lang_loader->load('Post', false);
			$this->current_language = $needed_language;
		}
	}

	/**
	 * Returns the string of the email template to use, like notification_reply_body
	 * pbe variants are appended back in the main flow
	 *
	 * @param string $type
	 * @param array $notifyDatum
	 * @return string
	 */
	private function setMessageTemplate($type, $notifyDatum)
	{
		$message_type = 'notification_' . $type;

		if ($type === self::NOTIFY_REPLY)
		{
			if (!empty($notifyDatum['notify_send_body']) && $this->canSendPostBody($notifyDatum))
			{
				$message_type .= '_body';
			}

			if (!empty($notifyDatum['notify_regularity']))
			{
				$message_type .= '_once';
			}
		}

		return $message_type;
	}

	/**
	 * Creates the replacement strings for use in email templates
	 *
	 * @param array $topicDatum
	 * @param array $notifyDatum
	 * @param int $id id of the topic/message
	 * @param string $type one of the TYPE constants
	 * @param string $area topic or board, defines the unsubscribe link
	 * @return array
	 */
	private function setTemplateReplacements($topicDatum, $notifyDatum, $id, $type = 'reply', $area = 'topic')
	{
		global $scripturl, $txt;

		$mailPreparse = new PreparseMail();

		// Set the replacement values for the template
		$replacements = [
			'TOPICSUBJECT' => $mailPreparse->preparseSubject($topicDatum['subject']),
			'POSTERNAME' => un_htmlspecialchars($topicDatum['name']),
			'SIGNATURE' => $mailPreparse->preparseSignature($topicDatum['signature']),
			'BOARDNAME' => $notifyDatum['board_name'],
			'SUBSCRIPTION' => $txt['topic'],
		];

		// Notification due to a board or topic subscription, we provide a proper no más correo no deseado link
		if ($area === 'board')
		{
			$replacements['SUBSCRIPTION'] = $txt['board'];
			$replacements['UNSUBSCRIBELINK'] = replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
				getNotifierToken($notifyDatum['id_member'], $notifyDatum['email_address'], $notifyDatum['password_salt'], 'board', $topicDatum['board']));
		}
		else
		{
			$replacements['UNSUBSCRIBELINK'] = replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
				getNotifierToken($notifyDatum['id_member'], $notifyDatum['email_address'], $notifyDatum['password_salt'], 'topic', $notifyDatum['id_topic']));
		}

		// New topic or a reply
		if (!empty($topicDatum['last_id']))
		{
			$replacements['TOPICLINK'] = $scripturl . '?topic=' . $id . '.msg' . $topicDatum['last_id'] . '#msg' . $topicDatum['last_id'];
			$replacements['TOPICLINKNEW'] = $scripturl . '?topic=' . $id . '.new;topicseen#new';
		}
		else
		{
			$replacements['TOPICLINK'] = $scripturl . '?topic=' . $id . '.new#new';
			$replacements['TOPICLINKNEW'] = $scripturl . '?topic=' . $id . '.new#new';
		}

		// If removed, no sense in sending links that point to nothing
		if ($type === 'remove')
		{
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);
		}

		// Do they want the body of the message sent too?
		if ($type === self::NOTIFY_REPLY && $this->canSendPostBody($notifyDatum))
		{
			$body = $topicDatum['body'];

			// Any attachments? if so lets make a big deal about them!
			if (!empty($topicDatum['attachments']))
			{
				$body .= "\n\n" . sprintf($txt['message_attachments'], $topicDatum['attachments'], $replacements['TOPICLINK']);
			}

			$replacements['MESSAGE'] = $mailPreparse->preparseHtml($body);
		}

		return $replacements;
	}

	/**
	 * Returns if we are to send the post text/body in the email
	 *
	 * @param array $data
	 * @return bool
	 */
	private function canSendPostBody($data)
	{
		if (empty($data['notify_send_body']))
		{
			return false;
		}

		return $this->isUsingMailList() || empty($this->_modSettings['disallow_sendBody']);
	}

	/**
	 * Who the email "envelope: is from which depends on the mode set
	 *
	 * @param array $topicDatum
	 * @return string
	 */
	private function _getEmailFrom($topicDatum)
	{
		global $mbname;

		// In group mode like google groups or yahoo groups, the mail is from the poster
		if (!empty($this->_modSettings['maillist_group_mode']))
		{
			return un_htmlspecialchars($topicDatum['name']);
		}

		// Otherwise in maillist mode, it is from the site
		if (!empty($this->_modSettings['maillist_sitename']))
		{
			return un_htmlspecialchars($this->_modSettings['maillist_sitename']);
		}

		// Fallback to the forum name
		return $mbname;
	}

	/**
	 * The actual sender of the email, needs to be correct, or it will bounce
	 *
	 * @return string
	 */
	private function _getFromWrapper()
	{
		global $webmaster_email;

		// The email address of the sender, irrespective of the envelope name above
		if (!empty($this->_modSettings['maillist_mail_from']))
		{
			return $this->_modSettings['maillist_mail_from'];
		}

		if (!empty($this->_modSettings['maillist_sitename_address']))
		{
			return $this->_modSettings['maillist_sitename_address'];
		}

		return $webmaster_email;
	}

	/**
	 * Sends reply notifications to topics with new replies on watched topics
	 *
	 * @param int $user_id the poster
	 * @param array $topicData new replies topic data
	 * @param string $type see Notify Types
	 * @param int[] $members_only if only sending to a select list of members
	 */
	public function sendTopicNotifications($user_id, $topicData, $type, $members_only)
	{
		global $language;

		$maillist = $this->isUsingMailList();

		// Find the members with watch notifications set for these topics.
		$topics = array_keys($topicData);
		$topicNotifications = fetchTopicNotifications($user_id, $topics, $type, $members_only);
		foreach ($topicNotifications as $notifyDatum)
		{
			// Don't do the ones that were already sent via board notification, you only get one notice
			if (isset($this->boards[$notifyDatum['id_member']][$notifyDatum['id_topic']]))
			{
				continue;
			}

			// Don't do the ones that are interested in only their own topic moderation events when it is not their topic
			if ($type !== self::NOTIFY_REPLY && $notifyDatum['id_member'] !== $notifyDatum['id_member_started']
				&& $notifyDatum['notify_types'] === self::NOTIFY_TYPE_MODERATION_ONLY_IF_STARTED)
			{
				continue;
			}

			// Don't if they don't have notification permissions
			$email_perm = true;
			if (!validateNotificationAccess($notifyDatum, $maillist, $email_perm))
			{
				continue;
			}

			$needed_language = empty($notifyDatum['lngfile']) || empty($this->_modSettings['userLanguage']) ? $language : $notifyDatum['lngfile'];
			$this->_checkLanguage($needed_language);

			$message_type = $this->setMessageTemplate($type, $notifyDatum);
			$replacements = $this->setTemplateReplacements($topicData[$notifyDatum['id_topic']], $notifyDatum, $notifyDatum['id_topic'], $type);

			// Send only if once, is off or it's on, and it hasn't been sent.
			if ($type !== self::NOTIFY_REPLY || empty($notifyDatum['notify_regularity']) || empty($notifyDatum['sent']))
			{
				// Use the pbe template when appropriate
				$template = ($email_perm && $type === self::NOTIFY_REPLY && $this->canSendPostBody($notifyDatum) ? 'pbe_' : '') . $message_type;
				$emaildata = loadEmailTemplate($template, $replacements, $needed_language, true);

				$sendMail = new BuildMail();
				$sendMail->setEmailReplacements($replacements);

				// Using the maillist functions? Then adjust the from wrapper
				if ($maillist && $email_perm && $type === self::NOTIFY_REPLY && !empty($notifyDatum['notify_send_body']))
				{
					// Set from name based on group or maillist mode
					$email_from = $this->_getEmailFrom($topicNotifications[$notifyDatum['id_topic']]);
					$from_wrapper = $this->_getFromWrapper();

					$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], $email_from, 'm' . $topicNotifications[$notifyDatum['id_topic']]['last_id'], true, 3, false, $from_wrapper, $notifyDatum['id_topic']);
				}
				else
				{
					$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicNotifications[$notifyDatum['id_topic']]['last_id'], true);
				}

				$this->sent++;
			}
		}
	}

	/**
	 * Notifies members who have requested notification for new topics posted on a board of said posts.
	 *
	 * What it does:
	 * - Receives data on the topics to send out notifications to the passed in array.
	 * - Only sends notifications to those who can *currently* see the topic (it doesn't matter if they could when they requested notification.)
	 *  -Loads the Post language file multiple times for each language if the userLanguage setting is set.
	 *
	 * @param array $topicData
	 */
	public function sendBoardNotifications(&$topicData)
	{
		global $language, $txt;

		// Do we have one or lots of topics?
		if (isset($topicData['body']))
		{
			$topicData = [$topicData];
		}

		// Find out what boards we have... and clear out any rubbish!
		$boards = [];
		foreach ($topicData as $key => $topic)
		{
			if (!empty($topic['board']))
			{
				$boards[$topic['board']][] = $key;
			}
			else
			{
				unset($topic[$key]);
			}
		}

		// Just the board numbers.
		$board_index = array_unique(array_keys($boards));
		if (empty($board_index))
		{
			return;
		}

		// Yea, we need to add this to the digest queue.
		$digest_insert = [];
		foreach ($topicData as $data)
		{
			$digest_insert[] = [$data['topic'], $data['msg'], 'topic', User::$info->id];
		}
		insertLogDigestQueue($digest_insert);

		// Using the post to email functions?
		$maillist = $this->isUsingMailList();

		// Find the members with notification on for these boards.
		$boardNotifyData = fetchBoardNotifications(User::$info->id, $board_index, 'reply', []);
		foreach ($boardNotifyData as $notifyDatum)
		{
			// No access, no notification, easy
			$email_perm = true;
			if (!validateNotificationAccess($notifyDatum, $maillist, $email_perm))
			{
				continue;
			}

			$langloaded = empty($notifyDatum['lngfile']) || empty($this->_modSettings['userLanguage']) ? $language : $notifyDatum['lngfile'];
			$lang_loader = new Loader($langloaded, $txt, database());
			$lang_loader->load('index', false);

			// Now loop through all the notifications to send for this board.
			if (empty($boards[$notifyDatum['id_board']]))
			{
				continue;
			}

			$sentOnceAlready = false;

			// For each message we need to send (from this board to this member)
			foreach ($boards[$notifyDatum['id_board']] as $key)
			{
				// Don't notify the guy who started the topic!
				// @todo In this case actually send them a "it's approved hooray" email :P
				if ($topicData[$key]['poster'] === $notifyDatum['id_member'])
				{
					continue;
				}

				// Setup the string for adding the body to the message, if a user wants it.
				$replacements = $this->setTemplateReplacements($topicData[$key], $notifyDatum, $topicData[$key]['topic'], 'reply', 'board');

				// Figure out which email to send
				$email_type = $this->setBoardTemplate($notifyDatum, $sentOnceAlready);
				if (!empty($email_type))
				{
					// Perhaps PBE as well?
					$template = ($email_perm && $this->canSendPostBody($notifyDatum) ? 'pbe_' : '') . $email_type;
					$emaildata = loadEmailTemplate($template, $replacements, $langloaded, true);

					$sendMail = new BuildMail();
					$sendMail->setEmailReplacements($replacements);

					// Maillist style?
					if ($email_perm && $this->canSendPostBody($notifyDatum))
					{
						// Add in the from wrapper and trigger sendmail to add in a security key
						$email_from = $this->_getEmailFrom($topicData[$key]);
						$from_wrapper = $this->_getFromWrapper();

						$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], $email_from, 't' . $topicData[$key]['topic'], true, 3, false, $from_wrapper, $topicData[$key]['topic']);
					}
					else
					{
						$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], null, null, true);
					}
				}

				$sentOnceAlready = true;
			}
		}

		$lang_loader = new Loader(null, $txt, database());
		$lang_loader->load('index', false);

		// Sent!
		updateLogNotify(User::$info->id, $board_index, true);
	}

	/**
	 * Returns the string of the email template to use, similar to setMessageTemplate but for
	 * new topics on boards
	 *
	 * PBE variants are appended back in the main flow
	 *
	 * @param array $data
	 * @param boolean $sentOnceAlready
	 * @return string
	 */
	public function setBoardTemplate($data, $sentOnceAlready)
	{
		$email_type = '';

		// Send only if once is off or it's on and it hasn't been sent.
		if (!empty($data['notify_regularity']) && !$sentOnceAlready && empty($data['sent']))
		{
			$email_type = 'notify_boards_once';
		}
		elseif (empty($data['notify_regularity']))
		{
			$email_type = 'notify_boards';
		}

		if (!empty($email_type))
		{
			$email_type .= $this->canSendPostBody($data) ? '_body' : '';
		}

		return $email_type;
	}

	/**
	 * A special function for handling the hell which is sending approval notifications. Called
	 * when a post is approved in a topic.
	 *
	 * @param array $topicData
	 */
	public function sendApprovalNotifications(&$topicData)
	{
		global $language;

		// Clean up the data...
		if (!is_array($topicData) || empty($topicData))
		{
			return;
		}

		$topics = array_keys($topicData);
		if (empty($topics))
		{
			return;
		}

		// These need to go into the digest too...
		$digest_insert = [];
		foreach ($topicData as $topic_data)
		{
			foreach ($topic_data as $data)
			{
				$digest_insert[] = [$data['topic'], $data['id'], 'reply', User::$info->id];
			}
		}
		insertLogDigestQueue($digest_insert);

		// Find everyone who needs to know about this.
		$topicNotifications = fetchApprovalNotifications($topics);

		$sent = 0;
		$this->current_language = User::$info->language;
		foreach ($topicNotifications as $notifyDatum)
		{
			// Don't if they don't have notification permissions, they could be pure evil.
			$email_perm = true;
			if (!validateNotificationAccess($notifyDatum, $this->isUsingMailList(), $email_perm))
			{
				continue;
			}

			$needed_language = empty($notifyDatum['lngfile']) || empty($this->_modSettings['userLanguage']) ? $language : $notifyDatum['lngfile'];
			$this->_checkLanguage($needed_language);

			$sent_this_time = false;

			// Now loop through all the messages to send to this member ($notifyDatum)
			foreach ($topicData[$notifyDatum['id_topic']] as $msg)
			{
				$message_type = $this->setMessageTemplate('reply', $notifyDatum);
				$replacements = $this->setTemplateReplacements($msg, $notifyDatum, $notifyDatum['id_topic']);

				// Send only if once
				if (empty($notifyDatum['notify_regularity']) || (empty($notifyDatum['sent']) && !$sent_this_time))
				{
					$template = ($email_perm && $this->canSendPostBody($notifyDatum) ? 'pbe_' : '') . $message_type;
					$emaildata = loadEmailTemplate($template, $replacements, $needed_language, true);

					$sendMail = new BuildMail();
					$sendMail->setEmailReplacements($replacements);

					// If using the maillist functions, we adjust who this is coming from
					if ($email_perm && $this->canSendPostBody($notifyDatum))
					{
						$email_from = $this->_getEmailFrom($msg);
						$from_wrapper = $this->_getFromWrapper();

						$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], $email_from, 'm' . $msg['id'], true, 3, false, $from_wrapper, $msg['topic']);
					}
					else
					{
						$sendMail->buildEmail($notifyDatum['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $msg['id'], true);
					}

					$sent++;
				}

				$sent_this_time = true;
			}
		}

		$this->_checkLanguage(User::$info->language);

		// Sent!
		if (!empty($sent))
		{
			updateLogNotify(User::$info->id, $topics);
		}
	}
}
