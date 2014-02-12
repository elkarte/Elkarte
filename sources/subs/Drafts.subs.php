<?php

/**
 * Support functions for the drafts controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 */

/**
 * Create PM draft in the database
 *
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
	$draft['id_draft'] = $db->insert_id('{db_prefix}user_drafts', 'id_draft');

	return $draft['id_draft'];
}

/**
 * Update an existing PM draft with the new data
 *
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

	// Get the id of the new draft
	$draft['id_draft'] = $db->insert_id('{db_prefix}user_drafts', 'id_draft');

	return $draft['id_draft'];
}

/**
 * Update a Post draft with the supplied data
 *
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
			is_sticky = {int:is_sticky}
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

	// Load in a draft from the DB
	$request = $db->query('', '
		SELECT id_draft, id_topic, id_board, id_reply, type, poster_time, id_member, subject,
			smileys_enabled, body, icon, locked, is_sticky, to_list
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
 * Optionly can load just the drafts for a specific topic (post) or reply (pm)
 *
 * @param int $member_id - user id to get drafts for
 * @param int $draft_type - 0 for post, 1 for pm
 * @param int|false $topic - if set, load drafts for that specific topic / pm
 * @param string $order - optional parameter to order the results
 * @param string $limit - optional parameter to limit the number returned 0,15
 */
function load_user_drafts($member_id, $draft_type = 0, $topic = false, $order = '', $limit = '')
{
	global $modSettings;

	$db = database();

	// Load the drafts that the user has available for the given type & action
	$user_drafts = array();
	$request = $db->query('', '
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

	// Place them in the draft array
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
 * @param int[]|int $id_draft
 * @param int $member_id
 * @param bool $check
 */
function deleteDrafts($id_draft, $member_id = -1, $check = true)
{
	$db = database();

	// Only a single draft.
	if (is_numeric($id_draft))
		$id_draft = array($id_draft);

	// Can't delete nothing
	if (empty($id_draft))
		return false;

	$db->query('', '
		DELETE FROM {db_prefix}user_drafts
		WHERE id_draft IN ({array_int:id_draft})' . ($check ? '
			AND  id_member = {int:id_member}' : ''),
		array(
			'id_draft' => $id_draft,
			'id_member' => $member_id ,
		)
	);
}

/**
 * Retrieve how many drafts the given user has.
 * This function checks for expired lifetime on drafts (they would be removed
 * by a scheduled task), and doesn't count those.
 *
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
 *  - keeps track of bcc and to names for the PM
 *
 * @todo this is the same as whats in PersonalMessage.controller, when that gets refractored
 *       this should go away and use the refractored PM subs
 *
 * @param int[] $allRecipients
 * @param mixed[] $recipient_ids
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
 * @param int $days
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

/**
 * Saves a post draft in the user_drafts table
 * The core draft feature must be enabled, as well as the post draft option
 * Determines if this is a new or an existing draft
 */
function saveDraft()
{
	global $context, $user_info, $modSettings, $board;

	// Ajax calling
	if (!isset($context['drafts_save']))
		$context['drafts_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft');

	// Can you be, should you be ... here?
	if (empty($context['drafts_save']) || !isset($_POST['save_draft']) || !isset($_POST['id_draft']))
		return false;

	// Read in what they sent, if anything
	$id_draft = empty($_POST['id_draft']) ? 0 : (int) $_POST['id_draft'];
	$draft_info = loadDraft($id_draft);

	// If a draft has been saved less than 5 seconds ago, let's not do the autosave again
	if (isset($_REQUEST['xml']) && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		// Since we were called from the autosave function, send something back
		if (!empty($id_draft))
		{
			loadLanguage('Drafts');
			loadTemplate('Xml');
			$context['sub_template'] = 'xml_draft';
			$context['id_draft'] = $id_draft;
			$context['draft_saved_on'] = $draft_info['poster_time'];
			obExit();
		}

		return;
	}

	// Be ready for surprises
	$post_errors = Error_Context::context('post', 1);

	// Prepare and clean the data, load the draft array
	$draft['id_draft'] = $id_draft;
	$draft['topic_id'] = empty($_REQUEST['topic']) ? 0 : (int) $_REQUEST['topic'];
	$draft['board'] = $board;
	$draft['icon'] = empty($_POST['icon']) ? 'xx' : preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']);
	$draft['smileys_enabled'] = isset($_POST['ns']) ? 0 : 1;
	$draft['locked'] = isset($_POST['lock']) ? (int) $_POST['lock'] : 0;
	$draft['sticky'] = isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']) ? (int) $_POST['sticky'] : 0;
	$draft['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$draft['body'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES);
	$draft['id_member'] = $user_info['id'];

	// The message and subject still need a bit more work
	preparsecode($draft['body']);
	if (Util::strlen($draft['subject']) > 100)
		$draft['subject'] = Util::substr($draft['subject'], 0, 100);

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

	// Cleanup
	unset($_POST['save_draft']);

	// If we were called from the autosave function, send something back
	if (!empty($id_draft) && isset($_REQUEST['xml']) && !$post_errors->hasError('session_timeout'))
	{
		loadTemplate('Xml');
		$context['sub_template'] = 'xml_draft';
		$context['id_draft'] = $id_draft;
		$context['draft_saved_on'] = time();
		obExit();
	}

	return;
}

/**
 * Saves a PM draft in the user_drafts table
 * The core draft feature must be enabled, as well as the pm draft option
 * Determines if this is a new or and update to an existing pm draft
 *
 * @param mixed[] $recipientList
 */
function savePMDraft($recipientList)
{
	global $context, $user_info, $modSettings;

	// Ajax calling
	if (!isset($context['drafts_pm_save']))
		$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');

	// PM survey says ... can you stay or must you go
	if (empty($context['drafts_pm_save']) || !isset($_POST['save_draft']) || !isset($_POST['id_pm_draft']))
		return false;

	// Read in what was sent
	$id_pm_draft = empty($_POST['id_pm_draft']) ? 0 : (int) $_POST['id_pm_draft'];
	$draft_info = loadDraft($id_pm_draft, 1);
	$post_errors = Error_Context::context('pm', 1);

	// 5 seconds is the same limit we have for posting
	if (isset($_REQUEST['xml']) && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		// Send something back to the javascript caller
		if (!empty($id_pm_draft))
		{
			loadTemplate('Xml');
			$context['sub_template'] = 'xml_draft';
			$context['id_draft'] = $id_pm_draft;
			$context['draft_saved_on'] = $draft_info['poster_time'];
			obExit();
		}

		return true;
	}

	// Determine who this is being sent to
	if (isset($_REQUEST['xml']))
	{
		$recipientList['to'] = isset($_POST['recipient_to']) ? explode(',', $_POST['recipient_to']) : array();
		$recipientList['bcc'] = isset($_POST['recipient_bcc']) ? explode(',', $_POST['recipient_bcc']) : array();
	}
	elseif (!empty($draft_info['to_list']) && empty($recipientList))
		$recipientList = unserialize($draft_info['to_list']);

	// Prepare the data
	$draft['id_pm_draft'] = $id_pm_draft;
	$draft['reply_id'] = empty($_POST['replied_to']) ? 0 : (int) $_POST['replied_to'];
	$draft['body'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES);
	$draft['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$draft['id_member'] = $user_info['id'];

	// message and subject always need a bit more work
	preparsecode($draft['body']);
	if (Util::strlen($draft['subject']) > 100)
		$draft['subject'] = Util::substr($draft['subject'], 0, 100);

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
	if (!empty($id_pm_draft) && isset($_REQUEST['xml']) && !$post_errors->hasError('session_timeout'))
	{
		loadTemplate('Xml');
		$context['sub_template'] = 'xml_draft';
		$context['id_draft'] = $id_pm_draft;
		$context['draft_saved_on'] = time();
		obExit();
	}

	return;
}

/**
 * Reads a draft in from the user_drafts table
 * Only loads the draft of a given type 0 for post, 1 for pm draft
 * Validates that the draft is the users draft
 * Optionally loads the draft in to context or superglobal for loading in to the form
 *
 * @param int $id_draft - draft to load
 * @param int $type - type of draft
 * @param bool $check - validate the user
 * @param bool $load - load it for use in a form
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
			$recipients = unserialize($draft_info['to_list']);

			// Make sure we only have integers in this array
			$recipients['to'] = array_map('intval', $recipients['to']);
			$recipients['bcc'] = array_map('intval', $recipients['bcc']);

			// Pretend we messed up to populate the pm message form
			messagePostError(array(), $recipients);
			return true;
		}
	}

	return $draft_info;
}