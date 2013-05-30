<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Create PM draft in the database
 *
 * @param array $draft
 * @param array $recipientList
 */
function create_pm_draft($draft, $recipientList)
{
	$db = database();

	$draft_columns = array(
		'id_reply' => 'int',
		'type' => 'int',
		'poster_time' => 'int',
		'id_member' => 'int',
		'subject' => 'string-255',
		'body' => 'string-65534',
		'to_list' => 'string-255',
		'outbox' => 'int',
	);
	$draft_parameters = array(
		$draft['reply_id'],
		1,
		time(),
		$draft['id_member'],
		$draft['subject'],
		$draft['body'],
		serialize($recipientList),
		$draft['outbox'],
	);
	$db->insert('',
		'{db_prefix}user_drafts',
		$draft_columns,
		$draft_parameters,
		array(
			'id_draft'
		)
	);

	// get the new id
	$draft['id_draft'] = $db->insert_id('{db_prefix}user_drafts', 'id_draft');

	return $draft['id_draft'];
}

/**
 * Update an existing PM draft with the new data
 *
 * @param array $draft
 * @param array $recipientList
 */
function modify_pm_draft($draft, $recipientList)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}user_drafts
		SET id_reply = {int:id_reply},
			type = {int:type},
			poster_time = {int:poster_time},
			subject = {string:subject},
			body = {string:body},
			to_list = {string:to_list},
			outbox = {int:outbox}
		WHERE id_draft = {int:id_pm_draft}
		LIMIT 1',
		array(
			'id_reply' => $draft['reply_id'],
			'type' => 1,
			'poster_time' => time(),
			'subject' => $draft['subject'],
			'body' => $draft['body'],
			'id_pm_draft' => $draft['id_pm_draft'],
			'to_list' => serialize($recipientList),
			'outbox' => $draft['outbox'],
		)
	);
}

/**
 * Create a new post draft in the database
 *
 * @param array $draft
 */
function create_post_draft($draft)
{
	global $modSettings;

	$db = database();

	$draft_columns = array(
		'id_topic' => 'int',
		'id_board' => 'int',
		'type' => 'int',
		'poster_time' => 'int',
		'id_member' => 'int',
		'subject' => 'string-255',
		'smileys_enabled' => 'int',
		'body' => (!empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] > 65534 ? 'string-' . $modSettings['max_messageLength'] : 'string-65534'),
		'icon' => 'string-16',
		'locked' => 'int',
		'is_sticky' => 'int'
	);
	$draft_parameters = array(
		$draft['topic_id'],
		$draft['board'],
		0,
		time(),
		$draft['id_member'],
		$draft['subject'],
		$draft['smileys_enabled'],
		$draft['body'],
		$draft['icon'],
		$draft['locked'],
		$draft['sticky']
	);
	$db->insert('',
		'{db_prefix}user_drafts',
		$draft_columns,
		$draft_parameters,
		array(
			'id_draft'
		)
	);

	// get the id of the new draft
	$draft['id_draft'] = $db->insert_id('{db_prefix}user_drafts', 'id_draft');

	return $draft['id_draft'];
}

/**
 * Update a Post draft with the supplied data
 *
 * @param array $draft
 */
function modify_post_draft($draft)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}user_drafts
		SET
			id_topic = {int:id_topic},
			id_board = {int:id_board},
			poster_time = {int:poster_time},
			subject = {string:subject},
			smileys_enabled = {int:smileys_enabled},
			body = {string:body},
			icon = {string:icon},
			locked = {int:locked},
			is_sticky = {int:is_sticky}
		WHERE id_draft = {int:id_draft}',
		array (
			'id_topic' => $draft['topic_id'],
			'id_board' => $draft['board'],
			'poster_time' => time(),
			'subject' => $draft['subject'],
			'smileys_enabled' => (int) $draft['smileys_enabled'],
			'body' => $draft['body'],
			'icon' => $draft['icon'],
			'locked' => $draft['locked'],
			'is_sticky' => $draft['sticky'],
			'id_draft' => $draft['id_draft'],
		)
	);
}

/**
 * Loads a single draft
 * Validates draft id/owner match if check is set to true
 * Draft id must match the type selected (post or pm)
 *
 * @param int $id_draft - specific draft number to get from the db
 * @param int $uid - member id who created the draft
 * @param int $type - 0 for post and 1 for pm
 * @param int $drafts_keep_days - number of days to consider a draft is still valid
 * @param bool $check - validate the draft is by the user, true by default
 */
function load_draft($id_draft, $uid, $type = 0, $drafts_keep_days = 0, $check = true)
{
	$db = database();

	// load in a draft from the DB
	$request = $db->query('', '
		SELECT *
		FROM {db_prefix}user_drafts
		WHERE id_draft = {int:id_draft}' . ($check ? '
			AND id_member = {int:id_member}' : '') . '
			AND type = {int:type}' . (!empty($drafts_keep_days) ? '
			AND poster_time > {int:time}' : '') . '
		LIMIT 1',
		array(
			'id_member' => $uid,
			'id_draft' => $id_draft,
			'type' => $type,
			'time' => $drafts_keep_days,
		)
	);

	// no results?
	if (!$db->num_rows($request))
		return false;

	// load up the data
	$draft_info = $db->fetch_assoc($request);
	$db->free_result($request);

	// a little cleaning
	$draft_info['body'] = !empty($draft_info['body']) ? str_replace('<br />', "\n", un_htmlspecialchars(stripslashes($draft_info['body']))) : '';
	$draft_info['subject'] = !empty($draft_info['subject']) ? stripslashes($draft_info['subject']) : '';

	return $draft_info;
}

/**
 * Loads all of the drafts for a user
 * Optionly can load just the drafts for a specific topic (post) or reply (pm)
 *
 * @param int $member_id - user id to get drafts for
 * @param int $topic - if set, load drafts for that specific topic / pm
 * @param int $draft_type - 0 for post, 1 for pm
 * @param int $drafts_keep_days - number of days to consider a draft is still valid
 * @return type
 */
function load_user_drafts($member_id, $draft_type = 0, $topic = false, $drafts_keep_days = 0, $order = '', $limit = '')
{
	$db = database();

	// load the drafts that the user has available for the given type & action
	$user_drafts = array();
	$request = $db->query('', '
		SELECT ud.*' . ($draft_type === 0 ? ',b.id_board, b.name AS bname' : '') . '
		FROM {db_prefix}user_drafts as ud' . ($draft_type === 0 ? '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ud.id_board)' : '') . '
		WHERE ud.id_member = {int:id_member}' . ((!empty($topic) && $draft_type === 0) ? '
			AND id_topic = {int:id_topic}' : (!empty($topic) ? '
			AND id_reply = {int:id_topic}' : '')) . '
			AND type = {int:draft_type}' . (!empty($drafts_keep_days) ? '
			AND poster_time > {int:time}' : '') . (!empty($order) ? '
		ORDER BY ' . $order : '') . (!empty($limit) ? '
		LIMIT ' . $limit : ''),
		array(
			'id_member' => $member_id,
			'id_topic' => (int) $topic,
			'draft_type' => $draft_type,
			'time' => $drafts_keep_days,
		)
	);

	// place them in the draft array
	while ($row = $db->fetch_assoc($request))
		$user_drafts[] = $row;
	$db->free_result($request);

	return $user_drafts;
}

/**
 * Deletes one or many drafts from the DB
 * Validates the drafts are from the user
 * If supplied an array of drafts will attempt to remove all of them
 *
 * @param int $id_draft
 * @param bool $check
 * @return boolean
 */
function deleteDrafts($id_draft, $member_id = -1, $check = true)
{
	$db = database();

	// Only a single draft.
	if (is_numeric($id_draft))
		$id_draft = array($id_draft);

	// can't delete nothing
	if (empty($id_draft))
		return false;

	$db->query('', '
			DELETE FROM {db_prefix}user_drafts
			WHERE id_draft IN ({array_int:id_draft})' . ($check ? '
				AND  id_member = {int:id_member}' : ''),
			array (
				'id_draft' => $id_draft,
				'id_member' => $member_id ,
			)
		);
}

/**
 * Retrieve how many drafts the given user has.
 * This function checks for expired lifetime on drafts (they would be removed
 *  by a scheduled task), and doesn't count those.
 *
 * @param int $member_id
 * @param int $draft_type
 */
function draftsCount($member_id, $draft_type)
{
	global $modSettings;

	$db = database();

	$request = $db->query('', '
		SELECT COUNT(id_draft)
		FROM {db_prefix}user_drafts
		WHERE id_member = {int:id_member}
			AND type={int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
			AND poster_time > {int:time}' : ''),
		array(
			'id_member' => $member_id,
			'draft_type' => $draft_type,
			'time' => (!empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0),
		)
	);
	list ($msgCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $msgCount;
}

/**
 * Given a list of userid's for a PM, finds the member name associated with the ID
 * so it can be presented to the user.
 *  - keeps track of bcc and to names for the PM
 *
 * @todo this is the same as whats in PersonalMessage.controller, when that gets refractored
 *       this should go away and use the refractored PM subs
 */
function draftsRecipients($allRecipients, $recipient_ids)
{
	$db = database();

	// holds our results
	$recipients = array(
		'to' => array(),
		'bcc' => array(),
	);

	require_once(SUBSDIR . '/Members.subs.php');
	// get all the member names that this PM is goign to
	$results = getBasicMemberData($allRecipients);
	foreach ($results as $result)
	{
		// load the to/bcc name array
		$recipientType = in_array($result['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
		$recipients[$recipientType][] = $result['real_name'];
	}

	return $recipients;
}

/**
 * Gets all old drafts older than x days
 * @param int $days
 * @return array
 */
function getOldDrafts($days)
{
	$db = database();

	$drafts = array();

	// Find all of the old drafts
	$request = $db->query('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * $days),
		)
	);

	while ($row = $db->fetch_row($request))
		$drafts[] = (int) $row[0];
	$db->free_result($request);

	return $drafts;
}