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
	global $smcFunc;

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
	$smcFunc['db_insert']('',
		'{db_prefix}user_drafts',
		$draft_columns,
		$draft_parameters,
		array(
			'id_draft'
		)
	);

	// get the new id
	$draft['id_draft'] = $smcFunc['db_insert_id']('{db_prefix}user_drafts', 'id_draft');

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
	global $smcFunc;

	$smcFunc['db_query']('', '
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
	global $smcFunc, $modSettings;

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
	$smcFunc['db_insert']('',
		'{db_prefix}user_drafts',
		$draft_columns,
		$draft_parameters,
		array(
			'id_draft'
		)
	);

	// get the id of the new draft
	$draft['id_draft'] = $smcFunc['db_insert_id']('{db_prefix}user_drafts', 'id_draft');

	return $draft['id_draft'];
}

/**
 * Update a Post draft with the supplied data
 *
 * @param array $draft
 */
function modify_post_draft($draft)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
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
	global $smcFunc;

	// load in a draft from the DB
	$request = $smcFunc['db_query']('', '
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
	if (!$smcFunc['db_num_rows']($request))
		return false;

	// load up the data
	$draft_info = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

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
function load_user_drafts($member_id, $topic = false, $draft_type = 0, $drafts_keep_days = 0)
{
	global $smcFunc;

	// load the drafts that the user has available for the given type & action
	$user_drafts = array();
	$request = $smcFunc['db_query']('', '
		SELECT *
		FROM {db_prefix}user_drafts
		WHERE id_member = {int:id_member}' . ((!empty($topic) && $draft_type === 0) ? '
			AND id_topic = {int:id_topic}' : (!empty($topic) ? '
			AND id_reply = {int:id_topic}' : '')) . '
			AND type = {int:draft_type}' . (!empty($drafts_keep_days) ? '
			AND poster_time > {int:time}' : '') . '
		ORDER BY poster_time DESC',
		array(
			'id_member' => $member_id,
			'id_topic' => (int) $topic,
			'draft_type' => $draft_type,
			'time' => $drafts_keep_days,
		)
	);

	// place them in the draft array
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$user_drafts[] = $row;
	$smcFunc['db_free_result']($request);

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
	global $smcFunc;

	// Only a single draft.
	if (is_numeric($id_draft))
		$id_draft = array($id_draft);

	// can't delete nothing
	if (empty($id_draft))
		return false;

	$smcFunc['db_query']('', '
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
	global $modSettings, $smcFunc;

	$request = $smcFunc['db_query']('', '
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
	list ($msgCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $msgCount;
}