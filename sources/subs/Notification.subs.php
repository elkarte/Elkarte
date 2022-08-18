<?php

/**
 * Functions to support the sending of notifications (new posts, replys, topics)
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Languages\Loader;
use ElkArte\Notifications\Notifications;
use ElkArte\Notifications\PostNotifications;
use ElkArte\TokenHash;
use ElkArte\User;
use ElkArte\Util;

/**
 * Sends a notification to members who have elected to receive emails
 *
 * @param int[]|int $topics - represents the topics the action is happening to.
 * @param string $type - can be any of reply, sticky, lock, unlock, remove,
 *                       move, merge, and split.  An appropriate message will be sent for each.
 * @param int[]|int $exclude = array() - members in exclude array will not be
 *                             processed for the topic with the same key.
 * @param int[]|int $members_only = array() - are the only ones that will be sent the notification if they have it on.
 * @param array $pbe = array() - array containing user_info if this is being run as a result of an email posting
 */
function sendNotifications($topics, $type, $exclude = [], $members_only = [], $pbe = [])
{
	// Simply redirects to PostNotifications class sendNotifications method
	// @todo I could find no use of $exclude in any calls to this function, that is why its missing :/
	(new PostNotifications())->sendNotifications($topics, $type, $members_only, $pbe);
}

/**
 * Notifies members who have requested notification for new topics posted on a board of said posts.
 *
 * @param array $topicData
 */
function sendBoardNotifications(&$topicData)
{
	// Redirects to PostNotifications class sendBoardNotifications method
	(new PostNotifications())->sendBoardNotifications($topicData);
}

/**
 * A special function for handling the hell which is sending approval notifications.
 *
 * @param array $topicData
 */
function sendApprovalNotifications(&$topicData)
{
	(new PostNotifications())->sendApprovalNotifications($topicData);
}

/**
 * This simple function gets a list of all administrators and emails them
 * to let them know a new member has joined.
 * Called by registerMember() function in subs/Members.subs.php.
 * Email is sent to all groups that have the moderate_forum permission.
 * The language set by each member is being used (if available).
 *
 * @param string $type types supported are 'approval', 'activation', and 'standard'.
 * @param int $memberID
 * @param string|null $member_name = null
 * @uses the Login language file.
 */
function sendAdminNotifications($type, $memberID, $member_name = null)
{
	global $modSettings, $language;

	$db = database();

	// If the setting isn't enabled then just exit.
	if (empty($modSettings['notify_new_registration']))
	{
		return;
	}

	// Needed to notify admins, or anyone
	require_once(SUBSDIR . '/Mail.subs.php');

	if ($member_name === null)
	{
		require_once(SUBSDIR . '/Members.subs.php');

		// Get the new user's name....
		$member_info = getBasicMemberData($memberID);
		$member_name = $member_info['real_name'];
	}

	// All membergroups who can approve members.
	$groups = [];
	$db->fetchQuery('
		SELECT 
			id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:moderate_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		[
			'add_deny' => 1,
			'id_group' => 0,
			'moderate_forum' => 'moderate_forum',
		]
	)->fetch_callback(
		function ($row) use (&$groups) {
			$groups[] = $row['id_group'];
		}
	);

	// Add administrators too...
	$groups[] = 1;
	$groups = array_unique($groups);

	// Get a list of all members who have ability to approve accounts - these are the people who we inform.
	$current_language = User::$info->language;
	$db->query('', '
		SELECT 
			id_member, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		[
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		]
	)->fetch_callback(
		function ($row) use ($type, $member_name, $memberID, $language) {
			global $scripturl, $modSettings;

			$replacements = [
				'USERNAME' => $member_name,
				'PROFILELINK' => $scripturl . '?action=profile;u=' . $memberID
			];
			$emailtype = 'admin_notify';

			// If they need to be approved add more info...
			if ($type === 'approval')
			{
				$replacements['APPROVALLINK'] = $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
				$emailtype .= '_approval';
			}

			$emaildata = loadEmailTemplate($emailtype, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// And do the actual sending...
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}
	);

	if (isset($current_language) && $current_language !== User::$info->language)
	{
		$lang_loader = new Loader(null, $txt, database());
		$lang_loader->load('Login', false);
	}
}

/**
 * Checks if a user has the correct access to get notifications
 * - validates they have proper group access to a board
 * - if using the maillist, checks if they should get a reply-able message
 *     - not muted
 *     - has postby_email permission on the board
 *
 * Returns false if they do not have the proper group access to a board
 * Sets email_perm to false if they should not get a reply-able message
 *
 * @param array $row
 * @param bool $maillist
 * @param bool $email_perm
 *
 * @return bool
 */
function validateNotificationAccess($row, $maillist, &$email_perm = true)
{
	global $modSettings;

	static $board_profile = [];

	$member_in_groups = array_merge([$row['id_group'], $row['id_post_group']], (empty($row['additional_groups']) ? [] : explode(',', $row['additional_groups'])));
	$board_allowed_groups = explode(',', $row['member_groups']);

	// Standardize the data
	$member_in_groups = array_map('intval', $member_in_groups);
	$board_allowed_groups = array_map('intval', $board_allowed_groups);

	// No need to check for you ;)
	if (!in_array(1, $member_in_groups, true))
	{
		$email_perm = true;

		return true;
	}

	// They do have access to this board?
	if (count(array_intersect($member_in_groups, $board_allowed_groups)) === 0)
	{
		$email_perm = false;

		return false;
	}

	// If using maillist, see if they should get a reply-able message
	if ($email_perm && $maillist)
	{
		// Perhaps they don't require or deserve a security key in the message
		if (!empty($modSettings['postmod_active'])
			&& !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $row['warning'])
		{
			$email_perm = false;

			return false;
		}

		if (!isset($board_profile[$row['id_board']]))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$board_profile[$row['id_board']] = groupsAllowedTo('postby_email', $row['id_board']);
		}

		// In a group that has email posting permissions on this board
		if (count(array_intersect($board_profile[$row['id_board']]['allowed'], $member_in_groups)) === 0)
		{
			$email_perm = false;

			return false;
		}

		// And not specifically denied?
		if ($email_perm && !empty($modSettings['permission_enable_deny'])
			&& count(array_intersect($member_in_groups, $board_profile[$row['id_board']]['denied'])) !== 0)
		{
			$email_perm = false;
		}
	}

	return $email_perm;
}

/**
 * Queries the database for notification preferences of a set of members.
 *
 * @param string[]|string $notification_types
 * @param int[]|int $members
 *
 * @return array
 */
function getUsersNotificationsPreferences($notification_types, $members)
{
	$db = database();

	$notification_types = (array) $notification_types;
	$query_members = (array) $members;
	$defaults = [];
	foreach (getConfiguredNotificationMethods('*') as $notification => $methods)
	{
		$return = [];
		foreach ($methods as $k => $level)
		{
			if ($level == Notifications::DEFAULT_LEVEL)
			{
				$return[] = $k;
			}
		}
		$defaults[$notification] = $return;
	}

	$results = [];
	$db->fetchQuery('
		SELECT 
			id_member, notification_type, mention_type
		FROM {db_prefix}notifications_pref
		WHERE id_member IN ({array_int:members_to})
			AND mention_type IN ({array_string:mention_types})',
		[
			'members_to' => $query_members,
			'mention_types' => $notification_types,
		]
	)->fetch_callback(
		function ($row) use (&$results) {
			if (!isset($results[$row['id_member']]))
			{
				$results[$row['id_member']] = [];
			}

			$results[$row['id_member']][$row['mention_type']] = json_decode($row['notification_type']);
		}
	);

	// Set the defaults
	foreach ($query_members as $member)
	{
		foreach ($notification_types as $type)
		{
			if (empty($results[$member]) && !empty($defaults[$type]))
			{
				if (!isset($results[$member]))
				{
					$results[$member] = [];
				}

				if (!isset($results[$member][$type]))
				{
					$results[$member][$type] = [];
				}

				$results[$member][$type] = $defaults[$type];
			}
		}
	}

	return $results;
}

/**
 * Saves into the database the notification preferences of a certain member.
 *
 * @param int $member The member id
 * @param array $notification_data The array of notifications ('type' => ['level'])
 */
function saveUserNotificationsPreferences($member, $notification_data)
{
	$db = database();

	$inserts = [];

	// First drop the existing settings
	$db->query('', '
		DELETE FROM {db_prefix}notifications_pref
		WHERE id_member = {int:member}
			AND mention_type IN ({array_string:mention_types})',
		[
			'member' => $member,
			'mention_types' => array_keys($notification_data),
		]
	);

	foreach ($notification_data as $type => $level)
	{
		// used to skip values that are here only to remove the default
		if (empty($level))
		{
			continue;
		}

		// If they have any site notifications enabled, set a flag to request Push.Permissions
		if (in_array('notification', $level))
		{
			$_SESSION['push_enabled'] = true;
		}

		$inserts[] = [
			$member,
			$type,
			json_encode($level),
		];
	}

	if (empty($inserts))
	{
		return;
	}

	$db->insert('',
		'{db_prefix}notifications_pref',
		[
			'id_member' => 'int',
			'mention_type' => 'string-12',
			'notification_type' => 'string',
		],
		$inserts,
		['id_member', 'mention_type']
	);
}

/**
 * From the list of all possible notification methods available, only those
 * enabled are returned.
 *
 * @param string[] $possible_methods The array of notifications ('type' => 'level')
 * @param string $type The type of notification (mentionmem, likemsg, etc.)
 *
 * @return array
 */
function filterNotificationMethods($possible_methods, $type)
{
	$unserialized = getConfiguredNotificationMethods($type);

	if (empty($unserialized))
	{
		return [];
	}

	$allowed = [];
	foreach ($possible_methods as $class)
	{
		$class = strtolower($class);
		if (!empty($unserialized[$class]))
		{
			$allowed[] = $class;
		}
	}

	return $allowed;
}

/**
 * Returns all the enabled methods of notification for a specific
 * type of notification.
 *
 * @param string $type The type of notification (mentionmem, likemsg, etc.)
 *
 * @return array
 */
function getConfiguredNotificationMethods($type = '*')
{
	global $modSettings;

	$unserialized = Util::unserialize($modSettings['notification_methods']);

	if (isset($unserialized[$type]))
	{
		return $unserialized[$type];
	}

	if ($type === '*')
	{
		return $unserialized;
	}

	return [];
}

/**
 * Creates a hash code using the notification details and our secret key
 *
 * - If no salt (secret key) has been set, creates a random one and saves it
 * in modSettings for future use
 *
 * @param string $memID member id
 * @param string $memEmail member email address
 * @param string $memSalt member salt
 * @param string $area area to unsubscribe
 * @param string $extra area specific data such as topic id or liked msg
 * @return string the token for the unsubscribe link
 */
function getNotifierToken($memID, $memEmail, $memSalt, $area, $extra)
{
	global $modSettings;

	$tokenizer = new TokenHash();

	// We need a site salt to keep things moving
	if (empty($modSettings['unsubscribe_site_salt']))
	{
		// Extra digits of salt
		$unsubscribe_site_salt = $tokenizer->generate_hash(22);
		updateSettings(['unsubscribe_site_salt' => $unsubscribe_site_salt]);
	}

	// Generate a code suitable for Blowfish crypt.
	$blowfish_salt = '$2a$07$' . $memSalt . $modSettings['unsubscribe_site_salt'] . '$';
	$now = time();
	$hash = crypt($area . $extra . $now . $memEmail . $memSalt, $blowfish_salt);

	// Return just the hash, drop the salt
	return urlencode(implode('_',
		[
			$memID,
			substr($hash, 28),
			$area,
			$extra,
			$now
		]
	));
}

/**
 * Validates a hash code using the notification details and our secret key
 *
 * - If no site salt (secret key) has been set, simply fails
 *
 * @param string $memEmail member email address
 * @param string $memSalt member salt
 * @param string $area data to validate = area + extra + time from link
 * @param string $hash the hash from the link
 * @return bool
 */
function validateNotifierToken($memEmail, $memSalt, $area, $hash)
{
	global $modSettings;

	if (empty($modSettings['unsubscribe_site_salt']))
	{
		return false;
	}

	$blowfish_salt = '$2a$07$' . $memSalt . $modSettings['unsubscribe_site_salt']. '$';
	$expected = substr($blowfish_salt, 0, 28) . $hash;
	$check = crypt($area . $memEmail . $memSalt, $blowfish_salt);

	// Basic safe compare
	return hash_equals($expected, $check);
}

/**
 * Fetches a set of data for a topic that will then be used in creating/building notification emails.
 *
 * @param int[] $topics
 * @param string $type
 * @return array[] A board array and the topic info array.  Board array used to search for board subscriptions.
 */
function getTopicInfos($topics, $type)
{
	$db = database();

	$topicData = [];
	$boards_index = [];

	$db->fetchQuery('
		SELECT 
			mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic, t.id_board, t.id_member_started,
			mem.signature, COALESCE(mem.real_name, ml.poster_name) AS poster_name, COUNT(a.id_attach) as num_attach
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON(a.attachment_type = {int:attachment_type} AND a.id_msg = t.id_last_msg)
		WHERE t.id_topic IN ({array_int:topic_list})
		GROUP BY 1, 2, 3, 4, 5, 6, 7, 8, 9',
		[
			'topic_list' => $topics,
			'attachment_type' => 0,
		]
	)->fetch_callback(
		function ($row) use (&$topicData, &$boards_index, $type) {
			// all the boards for these topics, used to find all the members to be notified
			$boards_index[] = $row['id_board'];

			// And the information we are going to tell them about
			$topicData[$row['id_topic']] = [
				'subject' => $row['subject'],
				'body' => $row['body'],
				'last_id' => (int) $row['id_last_msg'],
				'topic' => (int) $row['id_topic'],
				'board' => (int) $row['id_board'],
				'id_member_started' => (int) $row['id_member_started'],
				'name' => $type === 'reply' ? $row['poster_name'] : User::$info->name,
				'exclude' => '',
				'signature' => $row['signature'],
				'attachments' => (int) $row['num_attach'],
			];
		}
	);

	return [$boards_index, $topicData];
}

/**
 * Keeps the log_digest up to date for members who want weekly/daily updates
 *
 * @param array $digest_insert
 * @return void
 */
function insertLogDigestQueue($digest_insert)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_digest',
		[
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		],
		$digest_insert,
		[]
	);
}

/**
 * Find the members with *board* notifications on.
 *
 * What it does:
 * Finds notifications that meet:
 * 	- Member has watch notifications on for the board
 *  - The notification type of reply and/or moderation notices
 *  - Notification regularity of instantly or first unread
 *  - Member is activated
 *  - Member has access to the board where the topic resides
 *
 * @param int $user_id the id of the poster as they do not get a notification of their own post
 * @param int[] $boards_index list of boards to check
 * @param string $type type of activity, like reply or lock
 * @param int|int[] $members_only returns data only for a list of members
 * @return array
 */
function fetchBoardNotifications($user_id, $boards_index, $type, $members_only)
{
	$db = database();

	$boardNotifyData = [];

	// Find the members (excluding the poster) that have board notification enabled.
	$db->fetchQuery('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, 
			mem.lngfile, mem.warning, mem.id_group, mem.additional_groups, mem.id_post_group, mem.password_salt,
			b.member_groups, b.name, b.id_profile,
			ln.id_board, ln.sent
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
		WHERE ln.id_board IN ({array_int:board_list})
			AND mem.notify_types != {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}
			AND ln.id_member != {int:current_member}' .
		(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
		ORDER BY mem.lngfile',
		[
			'current_member' => $user_id,
			'board_list' => $boards_index,
			'notify_types' => $type === 'reply' ? 4 : 3,
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : [$members_only],
		]
	)->fetch_callback(
		function ($row) use (&$boardNotifyData) {
			// The board subscription information for the member
			$clean = [
				'id_member' => (int) $row['id_member'],
				'email_address' => $row['email_address'],
				'notify_regularity' => (int) $row['notify_regularity'],
				'notify_types' => (int) $row['notify_types'],
				'notify_send_body' => (int) $row['notify_send_body'],
				'lngfile' => $row['lngfile'],
				'warning' => $row['warning'],
				'id_group' => (int) $row['id_group'],
				'additional_groups' => $row['additional_groups'],
				'id_post_group' => (int) $row['id_post_group'],
				'password_salt' => $row['password_salt'],
				'member_groups' => $row['member_groups'],
				'board_name' => $row['name'],
				'id_profile_board' => (int) $row['id_profile'],
				'id_board' => (int) $row['id_board'],
				'sent' => (int) $row['sent'],
			];

			if (validateNotificationAccess($clean, false))
			{
				$boardNotifyData[] = $clean;
			}
		}
	);

	return $boardNotifyData;
}

/**
 * Finds members who have topic notification on for a topic where the type is one that they want to be
 * kept in the loop.
 *
 * What it does:
 * Finds notifications that meet:
 * 	- Member has watch notifications on for these topics
 *  - The notification type of reply and/or moderation notices
 *  - Notification regularity of instantly or first unread
 *  - Member is activated
 *  - Member has access to the board where the topic resides
 *
 * @param int $user_id some goon, e.g. the originator of the action who WILL NOT get a notification
 * @param int[] $topics array of topic id's that have updates
 * @param string $type type of notification like reply or lock
 * @param int|int[] $members_only if not empty, only send notices to these members
 * @return array
 */
function fetchTopicNotifications($user_id, $topics, $type, $members_only)
{
	$db = database();

	$topicNotifyData = [];

	// Find the members with notification on for this topic.
	$db->fetchQuery('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, 
			mem.lngfile, mem.warning, mem.id_group, mem.additional_groups, mem.id_post_group, mem.password_salt,
			t.id_member_started,
			b.member_groups, b.name, b.id_profile, b.id_board,
			ln.id_topic, ln.sent
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}
			AND ln.id_member != {int:current_member}' .
		(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
		ORDER BY mem.lngfile',
		[
			'current_member' => $user_id,
			'topic_list' => $topics,
			'notify_types' => $type === 'reply' ? 4 : 3,
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : [$members_only],
		]
	)->fetch_callback(
		function ($row) use (&$topicNotifyData) {
			// The topic subscription information for the member
			$clean = [
				'id_member' => (int) $row['id_member'],
				'email_address' => $row['email_address'],
				'notify_regularity' => (int) $row['notify_regularity'],
				'notify_types' => (int) $row['notify_types'],
				'notify_send_body' => (int) $row['notify_send_body'],
				'lngfile' => $row['lngfile'],
				'warning' => $row['warning'],
				'id_group' => (int) $row['id_group'],
				'additional_groups' => $row['additional_groups'],
				'id_post_group' => (int) $row['id_post_group'],
				'password_salt' => $row['password_salt'],
				'id_member_started' => (int) $row['id_member_started'],
				'member_groups' => $row['member_groups'],
				'board_name' => $row['name'],
				'id_profile_board' => (int) $row['id_profile'],
				'id_board' =>  (int) $row['id_board'],
				'id_topic' => (int) $row['id_topic'],
				'sent' => (int) $row['sent'],
			];

			if (validateNotificationAccess($clean, false))
			{
				$topicNotifyData[$clean['id_topic']] = $clean;
			}
		}
	);

	return $topicNotifyData;
}

/**
 * Updates the log_notify table for all members that have received notifications
 *
 * @param int $user_id
 * @param array $data
 * @param boolean $board if true updates a boards log notify, else topic
 * @return void
 */
function updateLogNotify($user_id, $data, $board = false)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_notify
		SET 
			sent = {int:is_sent}
		WHERE ' . ($board ? 'id_board' : 'id_topic') . ' IN ({array_int:data_list})
			AND id_member != {int:current_member}',
		[
			'current_member' => $user_id,
			'data_list' => $data,
			'is_sent' => 1,
		]
	);
}

/**
 * Finds members who have topic notification on for a topic where the type is one that they want to be
 * kept in the loop.
 *
 * What it does:
 * Finds notifications that meet:
 * 	- Members has watch notifications enabled for these topics
 *  - The notification type is not none/off (all, replies+moderation, moderation only)
 *  - Notification regularity of instantly or first unread
 *  - Member is activated
 *  - Member has access to the board where the topic resides
 *
 * @param int[] $topics array of topic id's that have updates
 * @return array
 */
function fetchApprovalNotifications($topics)
{
	$db = database();

	$approvalNotifyData = [];

	// Find everyone who needs to know about this.
	$db->fetchQuery('
		SELECT
			DISTINCT mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body,
			mem.lngfile, mem.warning, mem.id_group, mem.additional_groups, mem.id_post_group, mem.password_salt,
			t.id_member_started,
			b.member_groups, b.name, b.id_profile, b.id_board,
			ln.id_topic, ln.sent
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		ORDER BY mem.lngfile',
		[
			'topic_list' => $topics,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		]
	)->fetch_callback(
		function ($row) use (&$approvalNotifyData) {
			$clean = [
				'id_member' => (int) $row['id_member'],
				'email_address' => $row['email_address'],
				'notify_regularity' => (int) $row['notify_regularity'],
				'notify_types' => (int) $row['notify_types'],
				'notify_send_body' => (int) $row['notify_send_body'],
				'lngfile' => $row['lngfile'],
				'warning' => $row['warning'],
				'id_group' => (int) $row['id_group'],
				'additional_groups' => $row['additional_groups'],
				'id_post_group' => (int) $row['id_post_group'],
				'password_salt' => $row['password_salt'],
				'id_member_started' => (int) $row['id_member_started'],
				'member_groups' => $row['member_groups'],
				'board_name' => $row['name'],
				'id_profile_board' => (int) $row['id_profile'],
				'id_board' =>  (int) $row['id_board'],
				'id_topic' => (int) $row['id_topic'],
				'sent' => (int) $row['sent'],
			];

			if (validateNotificationAccess($clean, false))
			{
				$approvalNotifyData[] = $clean;
			}
		}
	);

	return $approvalNotifyData;
}
