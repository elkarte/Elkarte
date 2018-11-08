<?php

/**
 * Support functions for the drafts controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * Create PM draft in the database
 *
 * @package Drafts
 * @param mixed[] $draft
 * @param string[] $recipientList
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
	);
	$draft_parameters = array(
		$draft['reply_id'],
		1,
		time(),
		$draft['id_member'],
		$draft['subject'],
		$draft['body'],
		serialize($recipientList),
	);
	$db->insert('',
		'{db_prefix}user_drafts',
		$draft_columns,
		$draft_parameters,
		array(
			'id_draft'
		)
	);

	// Return the new id
	return $db->insert_id('{db_prefix}user_drafts', 'id_draft');
}

/**
 * Update an existing PM draft with the new data
 *
 * @package Drafts
 * @param mixed[] $draft
 * @param string[] $recipientList
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
			to_list = {string:to_list}
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
		)
	);
}

/**
 * Create a new post draft in the database
 *
 * @package Drafts
 * @param mixed[] $draft
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
		'is_sticky' => 'int',
		'is_usersaved' => 'int'
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
		$draft['sticky'],
		$draft['is_usersaved']
	);
	$db->insert('',
		'{db_prefix}user_drafts',
		$draft_columns,
		$draft_parameters,
		array(
			'id_draft'
		)
	);

	// Get the id of the new draft
	return $db->insert_id('{db_prefix}user_drafts', 'id_draft');
}

/**
 * Update a Post draft with the supplied data
 *
 * @package Drafts
 * @param mixed[] $draft
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
			is_sticky = {int:is_sticky},
			is_usersaved = {int:is_usersaved}
		WHERE id_draft = {int:id_draft}',
		array(
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
			'is_usersaved' => $draft['is_usersaved'],
		)
	);
}

/**
 * Loads a single draft
 *
 * What it does:
 *
 * - Validates draft id/owner match if check is set to true
 * - Draft id must match the type selected (post or pm)
 *
 * @package Drafts
 *
 * @param int $id_draft - specific draft number to get from the db
 * @param int $uid - member id who created the draft
 * @param int $type - 0 for post and 1 for pm
 * @param int $drafts_keep_days - number of days to consider a draft is still valid
 * @param bool $check - validate the draft is by the user, true by default
 *
 * @return bool
 */
function load_draft($id_draft, $uid, $type = 0, $drafts_keep_days = 0, $check = true)
{
	$db = database();

	// Load in a draft from the DB
	$request = $db->query('', '
		SELECT id_draft, id_topic, id_board, id_reply, type, poster_time, id_member, subject,
			smileys_enabled, body, icon, locked, is_sticky, to_list, is_usersaved
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

	// No results?
	if (!$db->num_rows($request))
		return false;

	// Load up the data
	$draft_info = $db->fetch_assoc($request);
	$db->free_result($request);

	// A little cleaning
	$draft_info['body'] = !empty($draft_info['body']) ? str_replace('<br />', "\n", un_htmlspecialchars(stripslashes($draft_info['body']))) : '';
	$draft_info['subject'] = !empty($draft_info['subject']) ? stripslashes($draft_info['subject']) : '';

	return $draft_info;
}

/**
 * Loads all of the drafts for a user
 *
 * - Optionally can load just the drafts for a specific topic (post) or reply (pm)
 *
 * @package Drafts
 *
 * @param int $member_id - user id to get drafts for
 * @param int $draft_type - 0 for post, 1 for pm
 * @param int|bool $topic - if set, load drafts for that specific topic / pm
 * @param string $order - optional parameter to order the results
 * @param string $limit - optional parameter to limit the number returned 0,15
 *
 * @return array
 */
function load_user_drafts($member_id, $draft_type = 0, $topic = false, $order = '', $limit = '')
{
	global $modSettings;

	$db = database();

	// Load the drafts that the user has available for the given type & action
	return $db->fetchQuery('
		SELECT ud.*' . ($draft_type === 0 ? ',b.id_board, b.name AS bname' : '') . '
		FROM {db_prefix}user_drafts AS ud' . ($draft_type === 0 ? '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ud.id_board)' : '') . '
		WHERE ud.id_member = {int:id_member}' . ($draft_type === 0 ? ($topic !== false ? '
			AND id_topic = {int:id_topic}' : '') : (!empty($topic) ? '
			AND id_reply = {int:id_topic}' : '')) . '
			AND type = {int:draft_type}' . (!empty($modSettings['drafts_keep_days']) ? '
			AND poster_time > {int:time}' : '') . (!empty($order) ? '
		ORDER BY {raw:order}' : '') . (!empty($limit) ? '
		LIMIT {raw:limit}' : ''),
		array(
			'id_member' => $member_id,
			'id_topic' => (int) $topic,
			'draft_type' => $draft_type,
			'time' => !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0,
			'order' => $order,
			'limit' => $limit,
		)
	);
}

/**
 * Deletes one or many drafts from the DB
 *
 * What it does:
 *
 * - Validates the drafts are from the user
 * - If supplied an array of drafts will attempt to remove all of them
 *
 * @package Drafts
 *
 * @param int[]|int $id_draft
 * @param int $member_id
 * @param bool $check
 *
 * @return bool
 */
function deleteDrafts($id_draft, $member_id = -1, $check = true)
{
	$db = database();

	// Only a single draft.
	if (!is_array($id_draft))
		$id_draft = array($id_draft);

	$id_draft = array_map('intval', $id_draft);

	// Can't delete nothing
	if (empty($id_draft))
		return false;

	$db->query('', '
		DELETE FROM {db_prefix}user_drafts
		WHERE id_draft IN ({array_int:id_draft})' . ($check ? '
			AND  id_member = {int:id_member}' : ''),
		array(
			'id_draft' => $id_draft,
			'id_member' => $member_id,
		)
	);
}

/**
 * Retrieve how many drafts the given user has.
 *
 * What it does:
 *
 * - This function checks for expired lifetime on drafts (they would be removed
 * by a scheduled task), and doesn't count those.
 *
 * @package Drafts
 * @param int $member_id
 * @param int $draft_type
 * @return integer
 */
function draftsCount($member_id, $draft_type = 0)
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
			'time' => !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0,
		)
	);
	list ($msgCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $msgCount;
}

/**
 * Given a list of userid's for a PM, finds the member name associated with the ID
 * so it can be presented to the user.
 *
 * - keeps track of bcc and to names for the PM
 *
 * @package Drafts
 * @todo this is the same as whats in PersonalMessage.controller, when that gets refactored
 * this should go away and use the refactored PM subs
 *
 * @param int[] $allRecipients
 * @param mixed[] $recipient_ids
 *
 * @return array
 */
function draftsRecipients($allRecipients, $recipient_ids)
{
	// Holds our results
	$recipients = array(
		'to' => array(),
		'bcc' => array(),
	);

	require_once(SUBSDIR . '/Members.subs.php');

	// Get all the member names that this PM is going to
	$results = getBasicMemberData($allRecipients);
	foreach ($results as $result)
	{
		// Load the to/bcc name array
		$recipientType = in_array($result['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
		$recipients[$recipientType][] = $result['real_name'];
	}

	return $recipients;
}

/**
 * Get all drafts older than x days
 *
 * @package Drafts
 *
 * @param int $days
 *
 * @return array
 */
function getOldDrafts($days)
{
	$db = database();

	// Find all of the old drafts
	return $db->fetchQueryCallback('
		SELECT
			id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * $days),
		),
		function ($row)
		{
			return (int) $row['id_draft'];
		}
	);
}

/**
 * Saves a post draft in the user_drafts table
 *
 * - The core draft feature must be enabled, as well as the post draft option
 * - Determines if this is a new or an existing draft
 *
 * @package Drafts
 * @param mixed[] $draft
 * @param boolean $check_last_save
 * @throws \ElkArte\Exceptions\Exception
 */
function saveDraft($draft, $check_last_save = false)
{
	global $context;

	$id_draft = $draft['id_draft'];

	// Read in what they sent, if anything
	$draft_info = loadDraft($id_draft);

	// If a draft has been saved less than 5 seconds ago, let's not do the autosave again
	if (!empty($check_last_save) && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		// Since we were called from the autosave function, send something back
		if (!empty($id_draft))
		{
			theme()->getTemplates()->loadLanguageFile('Drafts');
			theme()->getTemplates()->load('Xml');
			$context['sub_template'] = 'xml_draft';
			$context['id_draft'] = $id_draft;
			$context['draft_saved_on'] = $draft_info['poster_time'];
			obExit();
		}

		return;
	}

	// Be ready for surprises
	$post_errors = \ElkArte\Errors\ErrorContext::context('post', 1);

	// The message and subject still need a bit more work
	preparsecode($draft['body']);
	if (\ElkArte\Util::strlen($draft['subject']) > 100)
		$draft['subject'] = \ElkArte\Util::substr($draft['subject'], 0, 100);

	if (!isset($draft['is_usersaved']))
		$draft['is_usersaved'] = 0;

	if ($draft_info['is_usersaved'] == 1)
		$draft['is_usersaved'] = 1;

	// Modifying an existing draft, like hitting the save draft button or autosave enabled?
	if (!empty($id_draft) && !empty($draft_info))
	{
		modify_post_draft($draft);

		// Some items to return to the form
		$context['draft_saved'] = true;
		$context['id_draft'] = $id_draft;
	}
	// Otherwise creating a new draft
	else
	{
		$id_draft = create_post_draft($draft);

		// Everything go as expected?
		if (!empty($id_draft))
		{
			$context['draft_saved'] = true;
			$context['id_draft'] = $id_draft;
		}
		else
			$post_errors->addError('draft_not_saved');
	}

	return;
}

/**
 * Saves a PM draft in the user_drafts table
 *
 * - The core draft feature must be enabled, as well as the pm draft option
 * - Determines if this is a new or and update to an existing pm draft
 *
 * @package Drafts
 *
 * @param mixed[] $recipientList
 * @param mixed[] $draft
 * @param boolean $check_last_save
 *
 * @return bool|void
 * @throws \ElkArte\Exceptions\Exception
 */
function savePMDraft($recipientList, $draft, $check_last_save = false)
{
	global $context;

	// Read in what was sent
	$id_pm_draft = $draft['id_pm_draft'];
	$draft_info = loadDraft($id_pm_draft, 1);
	$post_errors = \ElkArte\Errors\ErrorContext::context('pm', 1);

	// 5 seconds is the same limit we have for posting
	if ($check_last_save && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		// Send something back to the javascript caller
		if (!empty($id_pm_draft))
		{
			theme()->getTemplates()->load('Xml');
			$context['sub_template'] = 'xml_draft';
			$context['id_draft'] = $id_pm_draft;
			$context['draft_saved_on'] = $draft_info['poster_time'];
			obExit();
		}

		return true;
	}

	// Determine who this is being sent to
	if (!$check_last_save && !empty($draft_info['to_list']) && empty($recipientList))
		$recipientList = \ElkArte\Util::unserialize($draft_info['to_list']);

	// message and subject always need a bit more work
	preparsecode($draft['body']);
	if (\ElkArte\Util::strlen($draft['subject']) > 100)
		$draft['subject'] = \ElkArte\Util::substr($draft['subject'], 0, 100);

	if (!isset($draft['is_usersaved']))
		$draft['is_usersaved'] = 0;

	if ($draft_info['is_usersaved'] == 1)
		$draft['is_usersaved'] = 1;

	// Modifying an existing PM draft?
	if (!empty($id_pm_draft) && !empty($draft_info))
	{
		modify_pm_draft($draft, $recipientList);

		// some items to return to the form
		$context['draft_saved'] = true;
		$context['id_pm_draft'] = $id_pm_draft;
	}
	// Otherwise creating a new PM draft.
	else
	{
		$id_pm_draft = create_pm_draft($draft, $recipientList);

		// Everything go as expected, if not toss back an error
		if (!empty($id_pm_draft))
		{
			$context['draft_saved'] = true;
			$context['id_pm_draft'] = $id_pm_draft;
		}
		else
			$post_errors->addError('draft_not_saved');
	}

	// if we were called from the autosave function, send something back
	if (!empty($id_pm_draft) && $check_last_save && !$post_errors->hasError('session_timeout'))
	{
		theme()->getTemplates()->load('Xml');
		$context['sub_template'] = 'xml_draft';
		$context['id_draft'] = $id_pm_draft;
		$context['draft_saved_on'] = time();
		obExit();
	}

	return;
}

/**
 * Reads a draft in from the user_drafts table
 *
 * - Only loads the draft of a given type 0 for post, 1 for pm draft
 * - Validates that the draft is the users draft
 * - Optionally loads the draft in to context or superglobal for loading in to the form
 *
 * @package Drafts
 *
 * @param int $id_draft - draft to load
 * @param int $type - type of draft
 * @param bool $check - validate the user
 * @param bool $load - load it for use in a form
 *
 * @return bool
 */
function loadDraft($id_draft, $type = 0, $check = true, $load = false)
{
	global $context, $user_info, $modSettings;

	// Like purell always clean to be sure
	$id_draft = (int) $id_draft;
	$type = (int) $type;

	// Nothing to read, nothing to do
	if (empty($id_draft))
		return false;

	// Load in this draft from the DB
	$drafts_keep_days = !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0;
	$draft_info = load_draft($id_draft, $user_info['id'], $type, $drafts_keep_days, $check);

	// Load it up for the templates as well
	if (!empty($load) && !empty($draft_info))
	{
		if ($type === 0)
		{
			// A standard post draft?
			$context['sticky'] = !empty($draft_info['is_sticky']) ? $draft_info['is_sticky'] : '';
			$context['locked'] = !empty($draft_info['locked']) ? $draft_info['locked'] : '';
			$context['use_smileys'] = !empty($draft_info['smileys_enabled']) ? true : false;
			$context['icon'] = !empty($draft_info['icon']) ? $draft_info['icon'] : 'xx';
			$context['message'] = !empty($draft_info['body']) ? $draft_info['body'] : '';
			$context['subject'] = !empty($draft_info['subject']) ? $draft_info['subject'] : '';
			$context['board'] = !empty($draft_info['board_id']) ? $draft_info['id_board'] : '';
			$context['id_draft'] = !empty($draft_info['id_draft']) ? $draft_info['id_draft'] : 0;
		}
		elseif ($type === 1)
		{
			// One of those pm drafts? then set it up like we have an error
			$_REQUEST['subject'] = !empty($draft_info['subject']) ? $draft_info['subject'] : '';
			$_REQUEST['message'] = !empty($draft_info['body']) ? $draft_info['body'] : '';
			$_REQUEST['replied_to'] = !empty($draft_info['id_reply']) ? $draft_info['id_reply'] : 0;
			$context['id_pm_draft'] = !empty($draft_info['id_draft']) ? $draft_info['id_draft'] : 0;
			$recipients = \ElkArte\Util::unserialize($draft_info['to_list']);

			// Make sure we only have integers in this array
			$recipients['to'] = array_map('intval', $recipients['to']);
			$recipients['bcc'] = array_map('intval', $recipients['bcc']);

			$draft_info['to_list'] = $recipients;
		}
	}

	return $draft_info;
}
