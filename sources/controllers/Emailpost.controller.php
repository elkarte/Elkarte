<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * All the functions that validate and then save an email as a post or pm
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Post-by-email controller.
 */
class Emailpost_Controller extends Action_Controller
{
	/**
	 * Default entry point, it forwards to a worker method,
	 * if we ever get here.
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// default, default, by default... preview
		$this->action_pbe_preview();
	}

	/**
	 * Main email posting controller, reads, parses, checks and posts an email message or PM
	 * Allows a user to reply to a topic on the board by emailing a reply to a
	 * notification message.
	 * - It must have the security key in the email or it will be rejected
	 * - It must be from the email of a registered user
	 * - The key must have been sent to that user
	 * - Keys are used once and then discarded
	 * Accessed by email imap cron script, and ManageMaillist.controller.php.
	 *
	 * @param string $data used to supply a full body+headers email
	 * @param boolean $force used to override common failure errors
	 * @param string $key used to supply a lost key
	 */
	function action_pbe_post($data = null, $force = false, $key = null)
	{
		global $txt, $modSettings, $language, $user_info, $maintenance;

		// The function is not even on ...
		if (empty($modSettings['maillist_enabled']))
			return;

		// Our mail parser and our main subs
		require_once(SUBSDIR . '/EmailParse.class.php');
		require_once(SUBSDIR . '/Emailpost.subs.php');

		// Init
		$pbe = array();
		loadLanguage('Maillist');
		setMemoryLimit('256M');

		// Load the email parser and get some data to work with
		$email_message = new Email_Parse();
		$email_message->read_data($data, BOARDDIR);
		if (!$email_message->raw_message)
			return false;

		// Ask for an html version (if available) and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();
		$email_message->load_key($key);

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']) && empty($modSettings['pbe_pm_enabled']))
			return pbe_emailError('error_email_notenabled', $email_message);

		// Spam I am?
		if ($email_message->load_spam())
			return pbe_emailError('error_found_spam', $email_message);

		// Load the user from the database based on the sending email address
		$email_message->email['from'] = !empty($email_message->email['from']) ? strtolower($email_message->email['from']) : '';
		$pbe = query_load_user_info($email_message->email['from']);

		// Can't find this email in our database, a non-user, a spammer, a looser, a poser or even worse?
		if (empty($pbe))
			return pbe_emailError('error_not_find_member', $email_message);

		// Find the message security key, without it we are not going anywhere ever
		if (empty($email_message->message_key_id))
			return pbe_emailError('error_missing_key', $email_message);

		// Good we have a key, who was it sent to?
		$key_owner = query_key_owner($email_message->message_key_id);

		// Can't find this key in the database, either
		//   a) spam attempt or b) replying with an expired/consumed key
		if (empty($key_owner))
			return pbe_emailError('error_' . ($message_type === 'p' ? 'pm_' : '') . 'not_find_entry', $email_message);

		// The key received was not sent to this member ... how we love those email aggregators
		if (strtolower($key_owner) !== $email_message->email['from'] && !$force)
			return pbe_emailError('error_key_sender_match', $email_message);

		// In maintenance mode, just log it for now
		if (!empty($maintenance) && $maintenance !== 2 && !$pbe['user_info']['is_admin'] && !$user_info['is_admin'])
			return pbe_emailError('error_in_maintenance_mode', $email_message);

		// The email looks valid, now on to check the actual user trying to make the post/pm
		// lets load the topic/message info and any additional permissions we need
		if ($email_message->message_type === 't' || $email_message->message_type === 'm')
		{
			// Load the message/topic details
			$topic_info = array();
			$topic_info = query_load_message($email_message->message_type, $email_message->message_id, $pbe);
			if (empty($topic_info))
				return pbe_emailError('error_topic_gone', $email_message);

			// Load board permissions
			query_load_permissions('board', $pbe, $topic_info);
		}
		else
		{
			// Load the PM details
			$pm_info = array();
			$pm_info = query_load_message($email_message->message_type, $email_message->message_id, $pbe);
			if (empty($pm_info))
			{
				// Duh oh ... likely they deleted the PM on the site and are now
				// replying to the PM by email, the agony!
				return pbe_emailError('error_pm_not_found', $email_message);
			}
		}

		// Account for moderation actions
		pbe_check_moderation($pbe);

		// Maybe they want to do additional spam / security checking
		call_integration_hook('integrate_mailist_checks_before', array($email_message, $pbe));

		// Load in the correct Re: for the language
		if ($language === $pbe['user_info']['language'])
			$pbe['response_prefix'] = $txt['response_prefix'];
		else
		{
			loadLanguage('index', $language, false);
			$pbe['response_prefix'] = $txt['response_prefix'];
			loadLanguage('index');
		}

		// Allow for new topics to be started via a email subject change
		if (!empty($modSettings['maillist_newtopic_change']))
		{
			$subject = str_replace($pbe['response_prefix'], '', pbe_clean_email_subject($email_message->subject));
			$current_subject = str_replace($pbe['response_prefix'], '', $topic_info['subject']);

			// If it does not match, then we go to make a new topic instead
			if (trim($subject) != trim($current_subject))
			{
				$board_info = query_load_board_details($topic_info['id_board'], $pbe);
				return pbe_create_topic($pbe, $email_message, $board_info);
			}
		}

		// Time to make a Post or a PM, first up topic and message replies
		if ($email_message->message_type === 't' || $email_message->message_type === 'm')
			$result = pbe_create_post($pbe, $email_message, $topic_info);
		// Must be a PM then
		elseif ($email_message->message_type === 'p')
			$result = pbe_create_pm($pbe, $email_message, $topic_info);

		if (!empty($result))
		{
			// We have now posted or PM'ed .. lets do some database maintenance cause maintenance is fun :'(
			query_key_maintenance($email_message);

			// Update this user so the log shows they were/are active, no luking in the email ether
			query_update_member_stats($pbe, $email_message, $topic_info);
		}

		return !empty($result);
	}

	/**
	 * New Topic posting controller, reads, parses, checks and posts a new topic
	 * - New topics do not have security keys in them so they are subject to spoofing
	 * - It must be from the email of a registered user
	 * - It must have been sent to an email ID that has been set to post new topics
	 * Accessed through emailtopic.
	 *
	 * @param string $data used to supply a full body+headers email
	 * @param type $force used to override common failure errors
	 */
	function action_pbe_topic($data = null, $force = false)
	{
		global $modSettings, $user_info;

		// The function is not even on ...
		if (empty($modSettings['maillist_enabled']))
			return;

		// Our mail parser and our main subs
		require_once(SUBSDIR . '/EmailParse.class.php');
		require_once(SUBSDIR . '/Emailpost.subs.php');

		// Init
		$pbe = array();
		loadLanguage('Maillist');
		setMemoryLimit('256M');

		// Get the data from one of our sources
		$email_message = new Email_Parse();
		$email_message->read_data($data, BOARDDIR);
		if (!$email_message->raw_message)
			return false;

		// Parse the header and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();

		// No key for this, so set some blanks for the error function (if needed)
		$email_message->message_type = 'x';
		$email_message->message_key_id = '';
		$email_message->message_id = 0;

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']))
			return pbe_emailError('error_email_notenabled', $email_message);

		// Load the user from the database based on the sending email address
		$email_message->email['from'] = !empty($email_message->email['from']) ? strtolower($email_message->email['from']) : '';
		$pbe = query_load_user_info($email_message->email['from']);

		// Can't find this email as one of our users?
		if (empty($pbe))
			return pbe_emailError('error_not_find_member', $email_message);

		// Getting hammy with it?
		if ($email_message->load_spam())
			return pbe_emailError('error_found_spam', $email_message);

		// The board that this email address corresponds to
		$board_number = pbe_find_board_number($email_message);
		if (empty($board_number))
			return pbe_emailError('error_not_find_board', $email_message);

		// In maintenance mode so just save it for the moderators to deal with
		if (!empty($maintenance) && $maintenance !== 2 && !$pbe['user_info']['is_admin'] && !$user_info['is_admin'])
			return pbe_emailError('error_in_maintenance_mode', $email_message);

		// Any additional spam / security checking
		call_integration_hook('integrate_mailist_checks_before', array($email_message, $pbe));

		// To post a NEW topic, we need some board details for where it goes
		$board_info = query_load_board_details($board_number, $pbe);
		if (empty($board_info))
			return pbe_emailError('error_board_gone', $email_message);

		// Load up this users permissions for that board
		query_load_permissions('board', $pbe, $board_info);

		// Account for any moderation they may be under
		pbe_check_moderation($pbe);

		// Create the topic, send notifications
		return pbe_create_topic($pbe, $email_message, $board_info);
	}

	/**
	 * Used to preview a failed email from the ACP
	 *  - Called from ManageMaillist.controller, which checks topic/message permission for viewing
	 *  - Calls pbe_load_text to prepare text for the preview
	 *  - Returns an array of values for use in the template
	 *
	 * @param type $data
	 * @return boolean
	 */
	function action_pbe_preview($data = null)
	{
		global $txt, $modSettings;

		// Our mail parser and our main subs
		require_once(SUBSDIR . '/EmailParse.class.php');
		require_once(SUBSDIR . '/Emailpost.subs.php');

		// Init
		$pbe = array();
		loadLanguage('Maillist');

		// Load the email parser and get some data to work with
		$email_message = new Email_Parse();
		$email_message->read_data($data, BOARDDIR);
		if (!$email_message->raw_message)
			return false;

		// Ask for an html version (if available) and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$html = $email_message->html_found;
		$email_message->load_address();
		$email_message->load_key();

		// Any reason to avoid using the html version since it helps with formatting
		if ($html && preg_match_all('~<table.*?>~i', $email_message->body, $matches) >=2)
		{
			// Some mobile responses wrap everything in a table structure
			$text = $email_message->body_plain;
			$html = false;
		}
		else
			$text = $email_message->body;

		// Convert to BBC and Format for the preview
		$text = pbe_load_text($html, $email_message, $pbe);

		// If there are attachments, just get the count
		$attachment_count = 0;
		if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
		{
			if ($email_message->message_type === 'p')
				$text .= "\n\n" . $txt['error_no_pm_attach'] . "\n";
			else
				$attachment_count = count($email_message->attachments);
		}

		if ($attachment_count)
			$text .= "\n\n" . sprintf($txt['email_attachments'], $attachment_count);

		// Return the parsed and formatted body and who it was sent to for the template
		return array('body' => $text, 'to' => implode(' & ', $email_message->email['to']) . (!empty($email_message->email['cc']) ? ', ' . implode(' & ',$email_message->email['cc']) : ''));
	}
}

/**
 * Attempts to create a reply post on the forum
 *  - Checks if the user has permissions to post/reply/postby email
 *  - Calls pbe_load_text to prepare text for the post
 *  - returns true if successful or false for any number of failures
 *
 * @param type $pbe
 * @param type $email_message
 * @param type $topic_info
 */
function pbe_create_post($pbe, $email_message, $topic_info)
{
    global $modSettings, $txt;

	// Validate they have permission to reply
	$becomesApproved = true;
	if (!in_array('postby_email', $pbe['user_info']['permissions']))
		return pbe_emailError('error_permission', $email_message);
	elseif ($topic_info['locked'] && !$pbe['user_info']['is_admin'] && !in_array('moderate_forum', $pbe['user_info']['permissions']))
		return pbe_emailError('error_locked', $email_message);
	elseif ($topic_info['id_member_started'] === $pbe['profile']['id_member'] && !$pbe['user_info']['is_admin'])
	{
		if ($modSettings['postmod_active'] && in_array('post_unapproved_replies_any', $pbe['user_info']['permissions']) && (!in_array('post_reply_any', $pbe['user_info']['permissions'])))
			$becomesApproved = false;
		elseif (!in_array('post_reply_own', $pbe['user_info']['permissions']))
			return pbe_emailError('error_cant_reply', $email_message);
	}
	elseif (!$pbe['user_info']['is_admin'])
	{
		if ($modSettings['postmod_active'] && in_array('post_unapproved_replies_any', $pbe['user_info']['permissions']) && (!in_array('post_reply_any', $pbe['user_info']['permissions'])))
			$becomesApproved = false;
		elseif (!in_array('post_reply_any', $pbe['user_info']['permissions']))
			return pbe_emailError('error_cant_reply', $email_message);
	}

	// Convert to BBC and Format the message
	$html = $email_message->html_found;
	$text = pbe_load_text($html, $email_message, $pbe);
	if (empty($text))
		return pbe_emailError('error_no_message', $email_message);

	// Seriously? Attachments?
	if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
	{
		if (($modSettings['postmod_active'] && in_array('post_unapproved_attachments', $pbe['user_info']['permissions'])) || in_array('post_attachment', $pbe['user_info']['permissions']))
			$attachIDs = pbe_email_attachments($pbe, $email_message);
		else
			$text .= "\n\n" . $txt['error_no_attach'] . "\n";
	}

	// Setup the post variables.
	$msgOptions = array(
		'id' => 0,
		'subject' => strpos($topic_info['subject'], trim($pbe['response_prefix'])) === 0 ? $topic_info['subject'] : $pbe['response_prefix'] . $topic_info['subject'],
		'smileys_enabled' => true,
		'body' => $text,
		'attachments' => empty($attachIDs) ? array() : $attachIDs,
		'approved' => $becomesApproved
	);

	$topicOptions = array(
		'id' => $topic_info['id_topic'],
		'board' => $topic_info['id_board'],
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic_info['id_topic']) || !empty($topic_info['approved'])
	);

	$posterOptions = array(
		'id' => $pbe['profile']['id_member'],
		'name' => $pbe['profile']['real_name'],
		'email' => $pbe['profile']['email_address'],
		'update_post_count' => empty($topic_info['count_posts']),
		'ip' => $email_message->load_ip() ? $email_message->ip : $pbe['profile']['member_ip']
	);

	// Make the post.
	createPost($msgOptions, $topicOptions, $posterOptions);

	// We need the auto_notify setting, it may be theme based so pass the theme in use
	$theme_settings = query_get_theme($pbe['profile']['id_member'], $pbe['profile']['id_theme'], $topic_info);
	$auto_notify = isset($theme_settings['auto_notify']) ? $theme_settings['auto_notify'] : 0;

	// Turn notifications on or off
	query_notifications($pbe['profile']['id_member'], $topic_info['id_board'], $topic_info['id_topic'], $auto_notify);

	// Notify members who have notification turned on for this,
	// but only if it's going to be approved
	if ($becomesApproved)
		sendNotifications($topic_info['id_topic'], 'reply', array(), array(), $pbe);

	return true;
}

/**
 * Attempts to create a PM (reply) on the forum
 *  - Checks if the user has permissions
 *  - Calls pbe_load_text to prepare text for the pm
 *  - Calls query_mark_pms to mark things as read
 *  - Uses sendpm to do the actual "sending"
 *  - Returns true if successful or false for any number of failures
 *
 * @param type $pbe
 * @param type $email_message
*/
function pbe_create_pm($pbe, $email_message)
{
    global $modSettings, $txt;

	// Can they send?
	if (!$pbe['user_info']['is_admin'] && !in_array('pm_send', $pbe['user_info']['permissions']))
		return pbe_emailError('error_pm_not_allowed', $email_message);

	// Convert the PM to BBC and Format the message
	$html = $email_message->html_found;
	$text = pbe_load_text($html, $email_message, $pbe);
	if (empty($text))
		return pbe_emailError('error_no_message', $email_message);

	// If they tried to attach a file, just say sorry
	if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
		$text .= "\n\n" . $txt['error_no_pm_attach'] . "\n";

	// We need how they have their PM options are set up
	$theme_settings = query_get_theme($pbe['profile']['id_member'], $pbe['profile']['id_theme'], array());
	$copy_to_outbox = isset($theme_settings['copy_to_outbox']) ? true : false;

	// For sending the message...
	$from = array(
		'id' => $pbe['profile']['id_member'],
		'name' => $pbe['profile']['real_name'],
		'username' => $pbe['profile']['member_name']
	);

	$pm_info['subject'] = strpos($pm_info['subject'], trim($pbe['response_prefix'])) === 0 ? $pm_info['subject'] : $pbe['response_prefix'] . $pm_info['subject'];

	// send/save the actual PM.
	require_once(SUBSDIR . '/PersonalMessage.subs.php');
	$pm_result = sendpm(array('to' => array($pm_info['id_member_from']), 'bcc' => array()), $pm_info['subject'], $text, $copy_to_outbox, $from, $pm_info['id_pm_head']);

	// Assuming all went well, mark this as read, replied to and update the unread counter
	if (!empty($pm_result))
		query_mark_pms($email_message, $pbe);

	return !empty($pm_result);
}

/**
 * Called by
 *	- pbe_topic to create a new topic
 *  - pbe_main to create a new topic via a subject change
 * Checks posting permissions, but requires email validation checks are complete
 *  - Calls pbe_load_text to prepare text for the post
 *  - Uses createPost to do the actual "posting"
 *  - Calls sendNotifications to announce the new post
 *  - Calls query_update_member_stats to show they did something
 * Requires pbe and email_message to be populated.
 *
 * @param type $pbe
 * @param type $email_message
 * @param type $board_info
 */
function pbe_create_topic($pbe, $email_message, $board_info)
{
	global $txt, $modSettings;

	// It does not work like that
	if (empty($pbe) || empty($email_message))
		return false;

	// We have the board info, and their permissions - do they have a right to start a new topic?
	$becomesApproved = true;
	if (!$pbe['user_info']['is_admin'])
	{
		if (!in_array('postby_email', $pbe['user_info']['permissions']))
			return pbe_emailError('error_permission', $email_message);
		elseif ($modSettings['postmod_active'] && in_array('post_unapproved_topics', $pbe['user_info']['permissions']) && (!in_array('post_new', $pbe['user_info']['permissions'])))
			$becomesApproved = false;
		elseif (!in_array('post_new', $pbe['user_info']['permissions']))
			return pbe_emailError('error_cant_start', $email_message);
	}

	// Approving all new topics by email anyway, smart admin this one is ;)
	if (!empty($modSettings['maillist_newtopic_needsapproval']))
		$becomesApproved = false;

	// First on the agenda the subject
	$subject = pbe_clean_email_subject($email_message->subject);
	$subject = strtr(Util::htmlspecialchars($subject), array("\r" => '', "\n" => '', "\t" => ''));

	// Not to long not to short
	if (Util::strlen($subject) > 100)
		$subject = Util::substr($subject, 0, 100);
	elseif ($subject == '')
		return pbe_emailError('error_no_subject', $email_message);

	// The message itself will need a bit of work
	$html = $email_message->html_found;
	$text = pbe_load_text($html, $email_message, $pbe);
	if (empty($text))
		return pbe_emailError('error_no_message', $email_message);

	// Build the attachment array if needed
	if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
	{
		if (($modSettings['postmod_active'] && in_array('post_unapproved_attachments', $pbe['user_info']['permissions'])) || in_array('post_attachment', $pbe['user_info']['permissions']))
			$attachIDs = pbe_email_attachments($pbe, $email_message);
		else
			$text .= "\n\n" . $txt['error_no_attach'] . "\n";
	}

	// If we get to this point ... then its time to play, lets start a topic !
	require_once(SUBSDIR . '/Post.subs.php');

	// Setup the topic variables.
	$msgOptions = array(
		'id' => 0,
		'subject' => $subject,
		'smileys_enabled' => true,
		'body' => $text,
		'attachments' => empty($attachIDs) ? array() : $attachIDs,
		'approved' => $becomesApproved
	);

	$topicOptions = array(
		'id' => 0,
		'board' => $board_info['id_board'],
		'mark_as_read' => false
	);

	$posterOptions = array(
		'id' => $pbe['profile']['id_member'],
		'name' => $pbe['profile']['real_name'],
		'email' => $pbe['profile']['email_address'],
		'update_post_count' => empty($board_info['count_posts']),
		'ip' => (isset($email_message->ip)) ? $email_message->ip : $pbe['profile']['member_ip']
	);

	// Attempt to make the new topic.
	createPost($msgOptions, $topicOptions, $posterOptions);

	// The auto_notify setting
	$theme_settings = query_get_theme($pbe['profile']['id_member'], $pbe['profile']['id_theme'], $board_info);
	$auto_notify = isset($theme_settings['auto_notify']) ? $theme_settings['auto_notify'] : 0;

	// Notifications on or off
	query_notifications($pbe['profile']['id_member'], $board_info['id_board'], $topicOptions['id'], $auto_notify);

	// Notify members who have notification turned on for this, (if it's approved)
	if ($becomesApproved)
		sendNotifications($topicOptions['id'], 'reply', array(), array(), $pbe);

	// Update this users info so the log shows them as active
	query_update_member_stats($pbe, $email_message, $topicOptions);

	return true;
}

/**
 * Calls the necessary functions to extract and format the message so its ready for posting
 *
 * @param boolean $html
 * @param object $email_message
 * @param array $pbe
 */
function pbe_load_text($html, $email_message, $pbe)
{
	if ($html && preg_match_all('~<table.*?>~i', $email_message->body, $matches) >= 2)
	{
		// Some mobile responses wrap everything in a table structure so use plain text
		$text = $email_message->body_plain;
		$html = false;
	}
	else
		$text = $email_message->body;

	// Convert to BBC and format it so it looks like a post
	$text = pbe_Email_to_bbc($text, $html);
	$pbe['profile']['real_name'] = isset($pbe['profile']['real_name']) ? $pbe['profile']['real_name'] : '';
	$text = pbe_fix_email_body($text, $html, $pbe['profile']['real_name'], (empty($email_message->_converted_utf8) ? $email_message->headers['x-parameters']['content-type']['charset'] : 'UTF-8'));

	// Do we even have a message left to post?
	$text = Util::htmltrim($text, ENT_QUOTES);
	if (empty($text))
		return;

	if ($email_message->message_type !== 'p')
	{
		// Prepare it for the database
		$text = Util::htmlspecialchars($text, ENT_QUOTES);
		require_once(SUBSDIR . '/Post.subs.php');
		preparsecode($text);
	}

	return $text;
}