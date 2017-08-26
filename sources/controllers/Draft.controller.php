<?php

/**
 * Allow for the saving, retrieving, deleting and settings for the drafts
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Draft controller.
 * This class handles requests that allow for the saving,
 * retrieving, deleting and settings for the drafts functionality.
 */
class Draft_Controller extends Post_Controller
{
	/**
	 * The id of the member
	 */
	private $_memID = 0;

	/**
	 * This method is executed before any action handler.
	 * Loads language, common needed stuffs.
	 */
	public function pre_dispatch()
	{
		// Language and helper functions
		loadLanguage('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');
		require_once(SUBSDIR . '/Profile.subs.php');

		$this->_memID = currentMemberID();
	}

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
	 * @override
	 * @todo implement the saving of drafts bypassing the Post_Controller
	 */
	public function action_post()
	{
		$this->action_index();
	}

	/**
	 * @override
	 * @todo implement the saving of drafts bypassing the Post_Controller
	 */
	public function action_post2()
	{
		$this->action_index();
	}

	/**
	 * @override
	 * @todo implement the saving of drafts bypassing the Post_Controller
	 */
	public function action_save()
	{
		$this->action_index();
	}

	/**
	 * Show all drafts of a given type by the current user
	 *
	 * What it does:
	 *
	 * - Allows for the deleting and loading/editing of drafts
	 *
	 * @uses the showdrafts template
	 *
	 */
	public function action_showProfileDrafts()
	{
		global $txt, $scripturl, $modSettings, $context, $user_info;

		// Safe is safe.
		if ($this->_memID != $user_info['id'])
			throw new Elk_Exception('no_access', false);

		require_once(SUBSDIR . '/Drafts.subs.php');

		// Some initial context.
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);
		$context['current_member'] = $this->_memID;

		// If just deleting a draft, do it and then redirect back.
		if (!empty($this->_req->query->delete) || !empty($this->_req->post->delete))
			$this->_action_delete('action=profile;u=' . $this->_memID . ';area=showdrafts;start=' . $context['start']);

		// Get things started
		$msgCount = draftsCount($this->_memID, 0);
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $this->_memID . ';area=showdrafts', $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		list ($maxIndex, $reverse, $limit, $order) = $this->_query_limits($msgCount, $maxIndex);
		$user_drafts = load_user_drafts($this->_memID, 0, false, $order, $limit);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = array();
		foreach ($user_drafts as $row)
		{
			$this->_prepare_body_subject($row['body'], $row['subject'], $txt['drafts_none'], (bool) $row['smileys_enabled']);

			// And the data used by the template
			$context['drafts'][$counter += $reverse ? -1 : 1] = array(
				'body' => $row['body'],
				'counter' => $counter,
				'alternate' => $counter % 2,
				'board' => array(
					'name' => $row['bname'],
					'id' => $row['id_board'],
					'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>',
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
				'buttons' => array(
					'checkbox' => array(
						'checkbox' => 'always',
						'value' => $row['id_draft'],
						'name' => 'delete',
					),
					'remove' => array(
						'href' => $scripturl . '?action=profile;u=' . $context['member']['id'] . ';area=showdrafts;delete=' . $row['id_draft'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
						'text' => $txt['draft_delete'],
						'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['draft_remove'] . '?') . ');"',
					),
					'edit' => array(
						'href' => $scripturl . '?action=post;' . (empty($row['id_topic']) ? 'board=' . $row['id_board'] : 'topic=' . $row['id_topic']) . '.0;id_draft=' . $row['id_draft'],
						'text' => $txt['draft_edit'],
					),
				)
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
	 *
	 * What it does:
	 *
	 * - Allows for the deleting and loading/editing of PM drafts
	 *
	 * @uses the showPMDrafts template
	 */
	public function action_showPMDrafts()
	{
		global $txt, $user_info, $scripturl, $modSettings, $context;

		require_once(SUBSDIR . '/Drafts.subs.php');

		// Quick check how we got here.
		if ($this->_memID != $user_info['id'])
			// empty($modSettings['drafts_enabled']) || empty($modSettings['drafts_pm_enabled']))
			throw new Elk_Exception('no_access', false);

		// Set up what we will need
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);

		// If just deleting a draft, do it and then redirect back.
		if (!empty($this->_req->query->delete) || !empty($this->_req->post->delete))
			return $this->_action_delete('action=pm;sa=showpmdrafts;start=' . $context['start']);

		// Perhaps a draft was selected for editing? if so pass this off
		if (!empty($this->_req->query->id_draft) && !empty($context['drafts_pm_save']))
		{
			checkSession('get');
			redirectexit('action=pm;sa=send;id_draft=' . $this->_req->getQuery('id_draft', 'intval'));
		}

		// Get the count of applicable drafts
		$msgCount = draftsCount($this->_memID, 1);
		$maxIndex = (int) $modSettings['defaultMaxMessages'];

		// Make sure the starting place makes sense and construct our friend the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=showpmdrafts', $context['start'], $msgCount, $maxIndex);
		$context['current_page'] = $context['start'] / $maxIndex;

		list ($maxIndex, $reverse, $limit, $order) = $this->_query_limits($msgCount, $maxIndex);
		$user_drafts = load_user_drafts($this->_memID, 1, false, $order, $limit);

		// Start counting at the number of the first message displayed.
		$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
		$context['posts'] = array();
		foreach ($user_drafts as $row)
		{
			$this->_prepare_body_subject($row['body'], $row['subject'], $txt['no_subject'], true);

			// Have they provided who this will go to?
			$recipients = array(
				'to' => array(),
				'bcc' => array(),
			);
			$recipient_ids = (!empty($row['to_list'])) ? Util::unserialize($row['to_list']) : array();

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

	/**
	 * Deletes drafts stored in the $_REQUEST['delete'] index.
	 * The function redirects to a selected location.
	 *
	 * @param string $redirect - The url to redirect to after the drafts have
	 * been deleted
	 * @throws Elk_Exception
	 */
	private function _action_delete($redirect = '')
	{

		checkSession(empty($this->_req->post) ? 'get' : '');

		// Lets see what we have been sent, one or many to delete
		$toDelete = array();
		if (!empty($this->_req->query->delete))
		{
			$toDelete[] = (int) $this->_req->query->delete;
		}
		else
		{
			$toDelete = array_map('intval', $this->_req->post->delete);
		}

		if (!empty($toDelete))
			deleteDrafts($toDelete, $this->_memID);

		redirectexit($redirect);
	}

	/**
	 * Calculates start and limit for the query, maxIndex for the counter and
	 * if the query is reversed or not
	 *
	 * @param int $msgCount - Total number of drafts
	 * @param int $maxIndex - The maximum number of messages
	 *
	 * @return mixed[] - an array consisting of: $maxIndex, $reverse, $limit, $order
	 */
	private function _query_limits($msgCount, $maxIndex)
	{
		global $context, $modSettings;

		// Reverse the query if we're past 50% of the total for better performance.
		$start = $context['start'];
		$reverse = $start > $msgCount / 2;
		if ($reverse)
		{
			$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
			$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
		}

		// Find this user's drafts
		$limit = $start . ', ' . $maxIndex;
		$order = 'ud.poster_time ' . ($reverse ? 'ASC' : 'DESC');

		return array($maxIndex, $reverse, $limit, $order);
	}

	/**
	 * Prepares the body and the subject of the draft
	 *
	 * @param string $body - The body of the message, passed by-ref
	 * @param string $subject - The subject, passed by-ref
	 * @param string $default_subject - The default subject if $subject is empty
	 * @param bool $smiley_enabled - Is the smiley are enabled or not
	 */
	private function _prepare_body_subject(&$body, &$subject, $default_subject, $smiley_enabled = true)
	{
		// Cleanup...
		if (empty($body))
		{
			$body = '';
		}

		$subject = Util::htmltrim($subject);
		if (empty($subject))
		{
			$subject = $default_subject;
		}

		// Censor...
		$body = censor($body);
		$subject = censor($subject);

		// BBC-ilize the message.
		$parser = \BBC\ParserWrapper::instance();
		$body = $parser->parseMessage($body, $smiley_enabled);
	}
}
