<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains all the functions that allow for the saving,
 * retrieving, deleting and settings for the drafts function.
 */

if (!defined('ELKARTE'))
	die('No access...');

// language and helper functions
loadLanguage('Drafts');
require_once(SUBSDIR . '/Drafts.subs.php');

/**
 * Draft controller.
 */
class Draft_Controller
{
	/**
	 * Loads in a group of drafts for the user of a given type
	 * (0/posts, 1/pm's)
	 * loads a specific draft for forum use if selected.
	 * Used in the posting screens to allow draft selection
	 * Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int $topic
	 * @param int $draft_type
	 * @return boolean
	 */
	public function action_showDrafts($member_id, $topic = false, $draft_type = 0)
	{
		global $scripturl, $context, $txt, $modSettings;

		$context['drafts'] = array();

		// Permissions
		if (($draft_type === 0 && empty($context['drafts_save'])) || ($draft_type === 1 && empty($context['drafts_pm_save'])) || empty($member_id))
			return false;

		// has a specific draft has been selected?  Load it up if there is not already a message already in the editor
		if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
			loadDraft((int) $_REQUEST['id_draft'], $draft_type, true, true);

		// load all the drafts for this user that meet the criteria
		$drafts_keep_days = !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0;
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, $draft_type, $topic, $drafts_keep_days, $order);

		// add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			// Post drafts
			if ($draft_type === 0)
				$context['drafts'][] = array(
					'subject' => empty($draft['subject']) ? $txt['drafts_none'] : censorText(shorten_text(stripslashes($draft['subject']), !empty($modSettings['draft_subject_length']) ? $modSettings['draft_subject_length'] : 24)),
				'poster_time' => relativeTime($draft['poster_time']),
					'link' => '<a href="' . $scripturl . '?action=post;board=' . $draft['id_board'] . ';' . (!empty($draft['id_topic']) ? 'topic='. $draft['id_topic'] .'.0;' : '') . 'id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
				);
			// PM drafts
			elseif ($draft_type === 1)
				$context['drafts'][] = array(
					'subject' => empty($draft['subject']) ? $txt['drafts_none'] : censorText(shorten_text(stripslashes($draft['subject']), !empty($modSettings['draft_subject_length']) ? $modSettings['draft_subject_length'] : 24)),
				'poster_time' => relativeTime($draft['poster_time']),
					'link' => '<a href="' . $scripturl . '?action=pm;sa=send;id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
				);
		}
	}

	/**
	 * Show all drafts of a given type by the current user
	 * Uses the showdraft template
	 * Allows for the deleting and loading/editing of drafts
	 *
	 * @param int $draft_type = 0
	 */
	public function action_showProfileDrafts($draft_type = 0)
	{
		global $txt, $scripturl, $modSettings, $context;

		$memID = currentMemberID();

		// Some initial context.
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['current_member'] = $memID;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete']))
		{
			checkSession('get');

			$id_delete = (int) $_REQUEST['delete'];
			deleteDrafts($id_delete, $memID);
			redirectexit('action=profile;u=' . $memID . ';area=showdrafts;start=' . $context['start']);
		}

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
			$_REQUEST['viewscount'] = 10;

		// Get things started
		$user_drafts = array();
		$msgCount = draftsCount($memID, $draft_type);
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=showdrafts', $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the pages for better performance.
		$start = $context['start'];
		$reverse = $start > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
			$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
		}

		// Find this user's drafts
		$limit = $start . ', ' . $maxIndex;
		$order = 'ud.id_draft ' . ($reverse ? 'ASC' : 'DESC');
		$drafts_keep_days = !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0;
		$user_drafts = load_user_drafts($memID, $draft_type, false, $drafts_keep_days, $order, $limit);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = array();
		foreach ($user_drafts as $row)
		{
			// Censor....
			if (empty($row['body']))
				$row['body'] = '';

			$row['subject'] = Util::htmltrim($row['subject']);
			if (empty($row['subject']))
				$row['subject'] = $txt['drafts_none'];

			censorText($row['body']);
			censorText($row['subject']);

			// BBC-ilize the message.
			$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], 'draft' . $row['id_draft']);

			// And the array...
			$context['drafts'][$counter += $reverse ? -1 : 1] = array(
				'body' => $row['body'],
				'counter' => $counter,
				'alternate' => $counter % 2,
				'board' => array(
					'name' => $row['bname'],
					'id' => $row['id_board'],
				),
				'topic' => array(
					'id' => $row['id_topic'],
					'link' => empty($row['id_topic']) ? $row['subject'] : '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
				),
				'subject' => $row['subject'],
				'time' => relativeTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'icon' => $row['icon'],
				'id_draft' => $row['id_draft'],
				'locked' => $row['locked'],
				'sticky' => $row['is_sticky'],
				'age' => floor((time() - $row['poster_time']) / 86400),
				'remaining' => (!empty($modSettings['drafts_keep_days']) ? round($modSettings['drafts_keep_days'] - ((time() - $row['poster_time']) / 86400)) : 0),
			);
		}

		// If the drafts were retrieved in reverse order, get them right again.
		if ($reverse)
			$context['drafts'] = array_reverse($context['drafts'], true);

		// Menu tab
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['drafts_show'] . ' - ' . $context['member']['name'],
			'icon' => 'inbox_hd.png'
		);

		$context['sub_template'] = 'showDrafts';
	}

	/**
	 * Show all PM drafts of the current user
	 * Uses the showpmdraft template
	 * Allows for the deleting and loading/editing of PM drafts
	 */
	public function action_showPMDrafts()
	{
		global $txt, $user_info, $scripturl, $modSettings, $context;

		require_once(SUBSDIR . '/Profile.subs.php');
		$memID = currentMemberID(false);

		// @todo: is necessary? Added because the default was -1
		if (empty($memID))
			$memID = -1;

		// set up what we will need
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete']))
		{
			checkSession('get');

			$id_delete = (int) $_REQUEST['delete'];
			deleteDrafts($id_delete, $memID);
			redirectexit('action=pm;sa=showpmdrafts;start=' . $context['start']);
		}

		// perhaps a draft was selected for editing? if so pass this off
		if (!empty($_REQUEST['id_draft']) && !empty($context['drafts_pm_save']) && $memID == $user_info['id'])
		{
			checkSession('get');
			$id_draft = (int) $_REQUEST['id_draft'];
			redirectexit('action=pm;sa=send;id_draft=' . $id_draft);
		}

		// init
		$draft_type = 1;
		$user_drafts = array();
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
			$_REQUEST['viewscount'] = 10;

		// Get the count of applicable drafts
		$msgCount = draftsCount($memID, $draft_type);

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=showpmdrafts', $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		// Reverse the query if we're past 50% of the total for better performance.
		$start = $context['start'];
		$reverse = $start > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
			$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
		}

		// go get em'
		$order = 'ud.id_draft ' . ($reverse ? 'ASC' : 'DESC');
		$limit = $start . ', ' . $maxIndex;
		$drafts_keep_days = !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0;
		$user_drafts = load_user_drafts($memID, $draft_type, false, $drafts_keep_days, $order, $limit);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = array();
		foreach ($user_drafts as $row)
		{
			// Censor....
			if (empty($row['body']))
				$row['body'] = '';

			$row['subject'] = Util::htmltrim($row['subject']);
			if (empty($row['subject']))
				$row['subject'] = $txt['no_subject'];

			censorText($row['body']);
			censorText($row['subject']);

			// BBC-ilize the message.
			$row['body'] = parse_bbc($row['body'], true, 'draft' . $row['id_draft']);

			// Have they provided who this will go to?
			$recipients = array(
				'to' => array(),
				'bcc' => array(),
			);
			$recipient_ids = (!empty($row['to_list'])) ? unserialize($row['to_list']) : array();

			// Get nice names to show the user, the id's are not that great to see!
			if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
			{
				$recipient_ids['to'] = array_map('intval', $recipient_ids['to']);
				$recipient_ids['bcc'] = array_map('intval', $recipient_ids['bcc']);
				$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);
				$recipients = draftsRecipients($allRecipients, $recipient_ids);
			}

			// Add the items to the array for template use
			$context['drafts'][$counter += $reverse ? -1 : 1] = array(
				'body' => $row['body'],
				'counter' => $counter,
				'alternate' => $counter % 2,
				'subject' => $row['subject'],
				'time' => relativeTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id_draft' => $row['id_draft'],
				'recipients' => $recipients,
				'age' => floor((time() - $row['poster_time']) / 86400),
				'remaining' => (!empty($modSettings['drafts_keep_days']) ? floor($modSettings['drafts_keep_days'] - ((time() - $row['poster_time']) / 86400)) : 0),
			);
		}

		// if the drafts were retrieved in reverse order, then put them in the right order again.
		if ($reverse)
			$context['drafts'] = array_reverse($context['drafts'], true);

		// off to the template we go
		$context['page_title'] = $txt['drafts'];
		$context['sub_template'] = 'showPMDrafts';
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=showpmdrafts',
			'name' => $txt['drafts'],
		);
	}
}

/**
 * Saves a post draft in the user_drafts table
 * The core draft feature must be enabled, as well as the post draft option
 * Determines if this is a new or an existing draft
 *
 * @return boolean
 */
function saveDraft()
{
	global $context, $user_info, $modSettings, $board;

	// ajax calling
	if (!isset($context['drafts_save']))
		$context['drafts_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft');

	// can you be, should you be ... here?
	if (empty($context['drafts_save']) || !isset($_POST['save_draft']) || !isset($_POST['id_draft']))
		return false;

	// read in what they sent, if anything
	$id_draft =  empty($_POST['id_draft']) ? 0 : (int) $_POST['id_draft'];
	$draft_info = loadDraft($id_draft);

	// If a draft has been saved less than 5 seconds ago, let's not do the autosave again
	if (isset($_REQUEST['xml']) && !empty($draft_info['poster_time']) && time() < $draft_info['poster_time'] + 5)
	{
		// since we were called from the autosave function, send something back
		if (!empty($id_draft))
		{
			loadTemplate('Xml');
			$context['sub_template'] = 'xml_draft';
			$context['id_draft'] = $id_draft;
			$context['draft_saved_on'] = $draft_info['poster_time'];
			obExit();
		}

		return;
	}

	// be ready for surprises
	$post_errors = error_context::context('post', 1);

	// prepare and clean the data, load the draft array
	$draft['id_draft'] = $id_draft;
	$draft['topic_id'] = empty($_REQUEST['topic']) ? 0 : (int) $_REQUEST['topic'];
	$draft['board'] = $board;
	$draft['icon'] = empty($_POST['icon']) ? 'xx' : preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']);
	$draft['smileys_enabled'] = isset($_POST['ns']) ? (int) $_POST['ns'] : 0;
	$draft['locked'] = isset($_POST['lock']) ? (int) $_POST['lock'] : 0;
	$draft['sticky'] = isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']) ? (int) $_POST['sticky'] : 0;
	$draft['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$draft['body'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES);
	$draft['id_member'] = $user_info['id'];

	// the message and subject still need a bit more work
	preparsecode($draft['body']);
	if (Util::strlen($draft['subject']) > 100)
		$draft['subject'] = Util::substr($draft['subject'], 0, 100);

	// Modifying an existing draft, like hitting the save draft button or autosave enabled?
	if (!empty($id_draft) && !empty($draft_info) && $draft_info['id_member'] == $user_info['id'])
	{
		modify_post_draft($draft);

		// some items to return to the form
		$context['draft_saved'] = true;
		$context['id_draft'] = $id_draft;
	}
	// otherwise creating a new draft
	else
	{
		$id_draft = create_post_draft($draft);

		// everything go as expected?
		if (!empty($id_draft))
		{
			$context['draft_saved'] = true;
			$context['id_draft'] = $id_draft;
		}
		else
			$post_errors->addError('draft_not_saved');
	}

	// cleanup
	unset($_POST['save_draft']);

	// if we were called from the autosave function, send something back
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
 * @param type $recipientList
 * @return boolean
 */
function savePMDraft($recipientList)
{
	global $context, $user_info, $modSettings;

	// ajax calling
	if (!isset($context['drafts_pm_save']))
		$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');

	// PM survey says ... can you stay or must you go
	if (empty($context['drafts_pm_save']) || !isset($_POST['save_draft']) || !isset($_POST['id_pm_draft']))
		return false;

	// read in what was sent
	$id_pm_draft = empty($_POST['id_pm_draft']) ? 0 : (int) $_POST['id_pm_draft'];
	$draft_info = loadDraft($id_pm_draft, 1);
	$post_errors = error_context::context('pm', 1);

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

	// determine who this is being sent to
	if (isset($_REQUEST['xml']))
	{
		$recipientList['to'] = isset($_POST['recipient_to']) ? explode(',', $_POST['recipient_to']) : array();
		$recipientList['bcc'] = isset($_POST['recipient_bcc']) ? explode(',', $_POST['recipient_bcc']) : array();
	}
	elseif (!empty($draft_info['to_list']) && empty($recipientList))
		$recipientList = unserialize($draft_info['to_list']);

	// prepare the data
	$draft['id_pm_draft'] = $id_pm_draft;
	$draft['reply_id'] = empty($_POST['replied_to']) ? 0 : (int) $_POST['replied_to'];
	$draft['outbox'] = empty($_POST['outbox']) ? 0 : 1;
	$draft['body'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES);
	$draft['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$draft['id_member'] = $user_info['id'];

	// message and subject always need a bit more work
	preparsecode($draft['body']);
	if (Util::strlen($draft['subject']) > 100)
		$draft['subject'] = Util::substr($draft['subject'], 0, 100);

	// Modifying an existing PM draft?
	if (!empty($id_pm_draft) && !empty($draft_info) && $draft_info['id_member'] == $user_info['id'])
	{
		modify_pm_draft($draft, $recipientList);

		// some items to return to the form
		$context['draft_saved'] = true;
		$context['id_pm_draft'] = $id_pm_draft;
	}
	// otherwise creating a new PM draft.
	else
	{
		$id_pm_draft = create_pm_draft($draft, $recipientList);

		// everything go as expected, if not toss back an error
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
 * @return boolean
 */
function loadDraft($id_draft, $type = 0, $check = true, $load = false)
{
	global $context, $user_info, $modSettings;

	// like purell always clean to be sure
	$id_draft = (int) $id_draft;
	$type = (int) $type;

	// nothing to read, nothing to do
	if (empty($id_draft))
		return false;

	// load in this draft from the DB
	$drafts_keep_days = !empty($modSettings['drafts_keep_days']) ? (time() - ($modSettings['drafts_keep_days'] * 86400)) : 0;
	$draft_info = load_draft($id_draft, $user_info['id'], $type, $drafts_keep_days, $check);

	// Load it up for the templates as well
	$recipients = array();
	if (!empty($load) && !empty($draft_info))
	{
		if ($type === 0)
		{
			// a standard post draft?
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
			// one of those pm drafts? then set it up like we have an error
			$_REQUEST['outbox'] = !empty($draft_info['outbox']);
			$_REQUEST['subject'] = !empty($draft_info['subject']) ? $draft_info['subject'] : '';
			$_REQUEST['message'] = !empty($draft_info['body']) ? $draft_info['body'] : '';
			$_REQUEST['replied_to'] = !empty($draft_info['id_reply']) ? $draft_info['id_reply'] : 0;
			$context['id_pm_draft'] = !empty($draft_info['id_draft']) ? $draft_info['id_draft'] : 0;
			$recipients = unserialize($draft_info['to_list']);

			// make sure we only have integers in this array
			$recipients['to'] = array_map('intval', $recipients['to']);
			$recipients['bcc'] = array_map('intval', $recipients['bcc']);

			// pretend we messed up to populate the pm message form
			messagePostError(array(), $recipients);
			return true;
		}
	}

	return $draft_info;
}