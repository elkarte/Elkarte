<?php

/**
 * Allow for the saving, retrieving, deleting and settings for the drafts
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Draft controller.
 * This class handles requests that allow for the saving,
 * retrieving, deleting and settings for the drafts functionality.
 */
class Draft_Controller extends Action_Controller
{
	/**
	 * Default method, just forwards, if we ever get here.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Where do you want to go today? :P
		$this->action_showProfileDrafts();
	}

	/**
	 * This method is executed before any action handler.
	 * Loads language, common needed stuffs.
	 */
	public function pre_dispatch()
	{
		// Language and helper functions
		loadLanguage('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');
	}

	/**
	 * Show all drafts of a given type by the current user
	 * Uses the showdraft template
	 * Allows for the deleting and loading/editing of drafts
	 */
	public function action_showProfileDrafts()
	{
		global $txt, $scripturl, $modSettings, $context, $user_info;

		$memID = currentMemberID();

		// Safe is safe.
		if ($memID != $user_info['id'])
			fatal_lang_error('no_access', false);

		require_once(SUBSDIR . '/Drafts.subs.php');

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
		$msgCount = draftsCount($memID, 0);

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
		$user_drafts = load_user_drafts($memID, 0, false, $order, $limit);

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
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
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
			'title' => $txt['drafts_show'],
			'class' => 'talk',
			'description' => $txt['drafts_show_desc'],
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
		require_once(SUBSDIR . '/Drafts.subs.php');
		$memID = currentMemberID();

		// Quick check how we got here.
		if ($memID != $user_info['id'])
			// empty($modSettings['drafts_enabled']) || empty($modSettings['drafts_pm_enabled']))
			fatal_lang_error('no_access', false);

		// Set up what we will need
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($_REQUEST['delete']))
		{
			checkSession('get');

			$id_delete = (int) $_REQUEST['delete'];
			deleteDrafts($id_delete, $memID);
			redirectexit('action=pm;sa=showpmdrafts;start=' . $context['start']);
		}

		// Perhaps a draft was selected for editing? if so pass this off
		if (!empty($_REQUEST['id_draft']) && !empty($context['drafts_pm_save']))
		{
			checkSession('get');
			$id_draft = (int) $_REQUEST['id_draft'];
			redirectexit('action=pm;sa=send;id_draft=' . $id_draft);
		}

		// Init
		$user_drafts = array();
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Default to 10.
		if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
			$_REQUEST['viewscount'] = 10;

		// Get the count of applicable drafts
		$msgCount = draftsCount($memID, 1);

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

		// Go get em'
		$order = 'ud.id_draft ' . ($reverse ? 'ASC' : 'DESC');
		$limit = $start . ', ' . $maxIndex;
		$user_drafts = load_user_drafts($memID, 1, false, $order, $limit);

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
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'id_draft' => $row['id_draft'],
				'recipients' => $recipients,
				'age' => floor((time() - $row['poster_time']) / 86400),
				'remaining' => (!empty($modSettings['drafts_keep_days']) ? floor($modSettings['drafts_keep_days'] - ((time() - $row['poster_time']) / 86400)) : 0),
			);
		}

		// If the drafts were retrieved in reverse order, then put them in the right order again.
		if ($reverse)
			$context['drafts'] = array_reverse($context['drafts'], true);

		// Off to the template we go
		$context['page_title'] = $txt['drafts'];
		$context['sub_template'] = 'showPMDrafts';
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=showpmdrafts',
			'name' => $txt['drafts'],
		);
	}
}