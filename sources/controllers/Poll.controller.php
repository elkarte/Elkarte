<?php

/**
 * This receives requests for voting, locking, removing editing polls
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.3
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Poll Controller.
 * This receives requests for voting, locking, removing and
 * editing polls. Note that that posting polls is done in
 * Post.controller.php.
 */
class Poll_Controller extends Action_Controller
{
	/**
	 * Forward to the right action.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Figure out the right action to do.
	}

	/**
	 * Allow the user to vote.
	 * It is called to register a vote in a poll.
	 * Must be called with a topic and option specified.
	 * Requires the poll_vote permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=poll;sa=vote.
	 *
	 * @uses Post language file.
	 */
	public function action_vote()
	{
		global $topic, $user_info, $modSettings;

		require_once(SUBSDIR . '/Poll.subs.php');

		// Make sure you can vote.
		isAllowedTo('poll_vote');

		loadLanguage('Post');

		// Check if they have already voted, or voting is locked.
		$row = checkVote($topic);

		if (empty($row))
			fatal_lang_error('poll_error', false);

		// If this is a guest can they vote?
		if ($user_info['is_guest'])
		{
			// Guest voting disabled?
			if (!$row['guest_vote'])
				fatal_lang_error('guest_vote_disabled');
			// Guest already voted?
			elseif (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . $row['id_poll'] . ',') !== false)
			{
				// ;id,timestamp,[vote,vote...]; etc
				$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);

				// Find the poll we're after.
				foreach ($guestinfo as $i => $guestvoted)
				{
					$guestvoted = explode(',', $guestvoted);
					if ($guestvoted[0] == $row['id_poll'])
						break;
				}

				// Has the poll been reset since guest voted?
				if (isset($guestvoted[1]) && $row['reset_poll'] > $guestvoted[1])
				{
					// Remove the poll info from the cookie to allow guest to vote again
					unset($guestinfo[$i]);
					if (!empty($guestinfo))
						$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
					else
						unset($_COOKIE['guest_poll_vote']);
				}
				else
					fatal_lang_error('poll_error', false);

				unset($guestinfo, $guestvoted, $i);
			}
		}

		// Is voting locked or has it expired?
		if (!empty($row['voting_locked']) || (!empty($row['expire_time']) && time() > $row['expire_time']))
			fatal_lang_error('poll_error', false);

		// If they have already voted and aren't allowed to change their vote - hence they are outta here!
		if (!$user_info['is_guest'] && $row['selected'] != -1 && empty($row['change_vote']))
			fatal_lang_error('poll_error', false);
		// Otherwise if they can change their vote yet they haven't sent any options... remove their vote and redirect.
		elseif (!empty($row['change_vote']) && !$user_info['is_guest'] && empty($_POST['options']))
		{
			checkSession('request');

			// Find out what they voted for before.
			$pollOptions = determineVote($user_info['id'], $row['id_poll']);

			// Just skip it if they had voted for nothing before.
			if (!empty($pollOptions))
			{
				// Update the poll totals.
				decreaseVoteCounter($row['id_poll'], $pollOptions);

				// Delete off the log.
				removeVote($user_info['id'], $row['id_poll']);
			}

			// Redirect back to the topic so the user can vote again!
			if (empty($_POST['options']))
				redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
		}

		checkSession('request');

		// Make sure the option(s) are valid.
		if (empty($_POST['options']))
			fatal_lang_error('didnt_select_vote', false);

		// Too many options checked!
		if (count($_REQUEST['options']) > $row['max_votes'])
			fatal_lang_error('poll_too_many_votes', false, array($row['max_votes']));

		$pollOptions = array();
		$inserts = array();
		foreach ($_REQUEST['options'] as $id)
		{
			$id = (int) $id;

			$pollOptions[] = $id;
			$inserts[] = array($row['id_poll'], $user_info['id'], $id);
		}

		// Add their vote to the tally.
		addVote($inserts);
		increaseVoteCounter($row['id_poll'], $pollOptions);

		// If it's a guest don't let them vote again.
		if ($user_info['is_guest'] && count($pollOptions) > 0)
		{
			// Time is stored in case the poll is reset later, plus what they voted for.
			$_COOKIE['guest_poll_vote'] = empty($_COOKIE['guest_poll_vote']) ? '' : $_COOKIE['guest_poll_vote'];

			// ;id,timestamp,[vote,vote...]; etc
			$_COOKIE['guest_poll_vote'] .= ';' . $row['id_poll'] . ',' . time() . ',' . (count($pollOptions) > 1 ? implode(',', $pollOptions) : $pollOptions[0]);

			// Increase num guest voters count by 1
			increaseGuestVote($row['id_poll']);

			require_once(SUBSDIR . '/Auth.subs.php');
			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
			elk_setcookie('guest_poll_vote', $_COOKIE['guest_poll_vote'], time() + 2500000, $cookie_url[1], $cookie_url[0], false, false);
		}

		// Maybe let a social networking mod log this, or something?
		call_integration_hook('integrate_poll_vote', array(&$row['id_poll'], &$pollOptions));

		// Return to the post...
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Lock the voting for a poll.
	 * Must be called with a topic specified in the URL.
	 * An admin always has over riding permission to lock a poll.
	 * If not an admin must have poll_lock_any permission, otherwise must
	 * be poll starter with poll_lock_own permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=lockvoting.
	 */
	public function action_lockvoting()
	{
		global $topic, $user_info;

		require_once(SUBSDIR . '/Poll.subs.php');

		checkSession('get');

		// Get the poll starter, ID, and whether or not it is locked.
		$poll = pollStatus($topic);

		// If the user _can_ modify the poll....
		if (!allowedTo('poll_lock_any'))
			isAllowedTo('poll_lock_' . ($user_info['id'] == $poll['id_member'] ? 'own' : 'any'));

		// It's been locked by a non-moderator.
		if ($poll['locked'] == '1')
			$poll['locked'] = '0';
		// Locked by a moderator, and this is a moderator.
		elseif ($poll['locked'] == '2' && allowedTo('moderate_board'))
			$poll['locked'] = '0';
		// Sorry, a moderator locked it.
		elseif ($poll['locked'] == '2' && !allowedTo('moderate_board'))
			fatal_lang_error('locked_by_admin', 'user');
		// A moderator *is* locking it.
		elseif ($poll['locked'] == '0' && allowedTo('moderate_board'))
			$poll['locked'] = '2';
		// Well, it's gonna be locked one way or another otherwise...
		else
			$poll['locked'] = '1';

		// Lock!  *Poof* - no one can vote.
		lockPoll($poll['id_poll'], $poll['locked']);

		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Display screen for editing or adding a poll.
	 * Must be called with a topic specified in the URL.
	 * If the user is adding a poll to a topic, must contain the variable
	 * 'add' in the url.
	 * User must have poll_edit_any/poll_add_any permission for the
	 * relevant action, otherwise must be poll starter with poll_edit_own
	 * permission for editing, or be topic starter with poll_add_any permission for adding.
	 * Accessed via ?action=editpoll.
	 *
	 * @uses Post language file.
	 * @uses Poll template, main sub-template.
	 */
	public function action_editpoll()
	{
		global $txt, $user_info, $context, $topic, $board, $scripturl;

		if (empty($topic))
			fatal_lang_error('no_access', false);

		// We work hard with polls.
		require_once(SUBSDIR . '/Poll.subs.php');

		loadLanguage('Post');
		loadTemplate('Poll');
		loadJavascriptFile('post.js', array(), 'post_scripts');

		$context['sub_template'] = 'poll_edit';
		$context['start'] = (int) $_REQUEST['start'];
		$context['is_edit'] = isset($_REQUEST['add']) ? 0 : 1;

		$poll_errors = Error_Context::context('poll');
		$pollinfo = pollInfoForTopic($topic);

		// Assume it all exists, right?
		if (empty($pollinfo))
			fatal_lang_error('no_board');

		// If we are adding a new poll - make sure that there isn't already a poll there.
		if (!$context['is_edit'] && !empty($pollinfo['id_poll']))
			fatal_lang_error('poll_already_exists');
		// Otherwise, if we're editing it, it does exist I assume?
		elseif ($context['is_edit'] && empty($pollinfo['id_poll']))
			fatal_lang_error('poll_not_found');

		// Can you do this?
		if ($context['is_edit'] && !allowedTo('poll_edit_any'))
			isAllowedTo('poll_edit_' . ($user_info['id'] == $pollinfo['id_member_started'] || ($pollinfo['poll_starter'] != 0 && $user_info['id'] == $pollinfo['poll_starter']) ? 'own' : 'any'));
		elseif (!$context['is_edit'] && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_' . ($user_info['id'] == $pollinfo['id_member_started'] ? 'own' : 'any'));

		$context['can_moderate_poll'] = isset($_REQUEST['add']) ? true : allowedTo('poll_edit_' . ($user_info['id'] == $pollinfo['id_member_started'] || ($pollinfo['poll_starter'] != 0 && $user_info['id'] == $pollinfo['poll_starter']) ? 'own' : 'any'));

		// Do we enable guest voting?
		require_once(SUBSDIR . '/Members.subs.php');
		$groupsAllowedVote = groupsAllowedTo('poll_vote', $board);

		// Want to make sure before you actually submit?  Must be a lot of options, or something.
		if (isset($_POST['preview']) || $poll_errors->hasErrors())
		{
			$question = Util::htmlspecialchars($_POST['question']);

			// Basic theme info...
			$context['poll'] = array(
				'id' => $pollinfo['id_poll'],
				'question' => $question,
				'hide_results' => empty($_POST['poll_hide']) ? 0 : $_POST['poll_hide'],
				'change_vote' => isset($_POST['poll_change_vote']),
				'guest_vote' => isset($_POST['poll_guest_vote']),
				'guest_vote_allowed' => in_array(-1, $groupsAllowedVote['allowed']),
				'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
			);

			// Start at number one with no last id to speak of.
			$number = 1;
			$last_id = 0;

			// Get all the choices - if this is an edit.
			if ($context['is_edit'])
			{
				$pollOptions = pollOptions($pollinfo['id_poll']);
				$context['choices'] = array();
				foreach ($pollOptions as $option)
				{
					// Get the highest id so we can add more without reusing.
					if ($option['id_choice'] >= $last_id)
						$last_id = $option['id_choice'] + 1;

					// They cleared this by either omitting it or emptying it.
					if (!isset($_POST['options'][$option['id_choice']]) || $_POST['options'][$option['id_choice']] == '')
						continue;

					// Add the choice!
					$context['choices'][$option['id_choice']] = array(
						'id' => $option['id_choice'],
						'number' => $number++,
						'votes' => $option['votes'],
						'label' => $option['label'],
						'is_last' => false
					);
				}
			}

			// Work out how many options we have, so we get the 'is_last' field right...
			$totalPostOptions = 0;
			foreach ($_POST['options'] as $id => $label)
			{
				if ($label != '')
					$totalPostOptions++;
			}

			$count = 1;

			// If an option exists, update it.  If it is new, add it - but don't reuse ids!
			foreach ($_POST['options'] as $id => $label)
			{
				$label = Util::htmlspecialchars($label);
				censorText($label);

				if (isset($context['choices'][$id]))
					$context['choices'][$id]['label'] = $label;
				elseif ($label != '')
					$context['choices'][] = array(
						'id' => $last_id++,
						'number' => $number++,
						'label' => $label,
						'votes' => -1,
						'is_last' => $count++ == $totalPostOptions && $totalPostOptions > 1 ? true : false,
					);
			}

			// Make sure we have two choices for sure!
			if ($totalPostOptions < 2)
			{
				// Need two?
				if ($totalPostOptions == 0)
				{
					$context['choices'][] = array(
						'id' => $last_id++,
						'number' => $number++,
						'label' => '',
						'votes' => -1,
						'is_last' => false
					);
				}
				$poll_errors->addError('poll_few');
			}

			// Always show one extra box...
			$context['choices'][] = array(
				'id' => $last_id++,
				'number' => $number++,
				'label' => '',
				'votes' => -1,
				'is_last' => true
			);

			$context['last_choice_id'] = $last_id;

			if ($context['can_moderate_poll'])
				$context['poll']['expiration'] = (int) $_POST['poll_expire'];

			// Check the question/option count for errors.
			// @todo: why !$poll_errors->hasErrors()?
			if (trim($_POST['question']) == '' && !$poll_errors->hasErrors())
				$poll_errors->addError('no_question');

			// No check is needed, since nothing is really posted.
			checkSubmitOnce('free');

			// Take a check for any errors... assuming we haven't already done so!
			$context['poll_error'] = array(
				'errors' => $poll_errors->prepareErrors(),
				'type' => $poll_errors->getErrorType() == 0 ? 'minor' : 'serious',
				'title' => $context['is_edit'] ? $txt['error_while_editing_poll'] : $txt['error_while_adding_poll'],
			);
		}
		else
		{
			// Basic theme info...
			$context['poll'] = array(
				'id' => $pollinfo['id_poll'],
				'question' => $pollinfo['question'],
				'hide_results' => $pollinfo['hide_results'],
				'max_votes' => $pollinfo['max_votes'],
				'change_vote' => !empty($pollinfo['change_vote']),
				'guest_vote' => !empty($pollinfo['guest_vote']),
				'guest_vote_allowed' => in_array(-1, $groupsAllowedVote['allowed']),
			);

			// Poll expiration time?
			$context['poll']['expiration'] = empty($pollinfo['expire_time']) || !$context['can_moderate_poll'] ? '' : ceil($pollinfo['expire_time'] <= time() ? -1 : ($pollinfo['expire_time'] - time()) / (3600 * 24));

			// Get all the choices - if this is an edit.
			if ($context['is_edit'])
			{
				$context['choices'] = getPollChoices($pollinfo['id_poll']);

				$last_id = max(array_keys($context['choices'])) + 1;

				// Add an extra choice...
				$context['choices'][] = array(
					'id' => $last_id,
					'number' => $context['choices'][$last_id - 1]['number'] + 1,
					'votes' => -1,
					'label' => '',
					'is_last' => true
				);
				$context['last_choice_id'] = $last_id;
			}
			// New poll?
			else
			{
				// Setup the default poll options.
				$context['poll'] = array(
					'id' => 0,
					'question' => '',
					'hide_results' => 0,
					'max_votes' => 1,
					'change_vote' => 0,
					'guest_vote' => 0,
					'guest_vote_allowed' => in_array(-1, $groupsAllowedVote['allowed']),
					'expiration' => '',
				);

				// Make all five poll choices empty.
				$context['choices'] = array(
					array('id' => 0, 'number' => 1, 'votes' => -1, 'label' => '', 'is_last' => false),
					array('id' => 1, 'number' => 2, 'votes' => -1, 'label' => '', 'is_last' => false),
					array('id' => 2, 'number' => 3, 'votes' => -1, 'label' => '', 'is_last' => false),
					array('id' => 3, 'number' => 4, 'votes' => -1, 'label' => '', 'is_last' => false),
					array('id' => 4, 'number' => 5, 'votes' => -1, 'label' => '', 'is_last' => true)
				);
				$context['last_choice_id'] = 4;
			}
		}
		$context['page_title'] = $context['is_edit'] ? $txt['poll_edit'] : $txt['add_poll'];
		$context['form_url'] = $scripturl . '?action=editpoll2' . ($context['is_edit'] ? '' : ';add') . ';topic=' . $context['current_topic'] . '.' . $context['start'];

		// Build the link tree.
		censorText($pollinfo['subject']);
		$context['linktree'][] = array(
			'url' => $scripturl . '?topic=' . $topic . '.0',
			'name' => $pollinfo['subject'],
		);
		$context['linktree'][] = array(
			'name' => $context['page_title'],
		);

		// Register this form in the session variables.
		checkSubmitOnce('register');
	}

	/**
	 * Update the settings for a poll, or add a new one.
	 * Must be called with a topic specified in the URL.
	 * The user must have poll_edit_any/poll_add_any permission
	 * for the relevant action. Otherwise they must be poll starter
	 * with poll_edit_own permission for editing, or be topic starter
	 * with poll_add_any permission for adding.
	 * In the case of an error, this function will redirect back to
	 * action_editpoll and display the relevant error message.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=editpoll2.
	 */
	public function action_editpoll2()
	{
		global $topic, $board, $user_info;

		// Sneaking off, are we?
		if (empty($_POST))
			redirectexit('action=editpoll;topic=' . $topic . '.0');

		$poll_errors = Error_Context::context('poll');

		if (checkSession('post', '', false) != '')
			$poll_errors->addError('session_timeout');

		if (isset($_POST['preview']))
			return $this->action_editpoll();

		// HACKERS (!!) can't edit :P.
		if (empty($topic))
			fatal_lang_error('no_access', false);

		// Is this a new poll, or editing an existing?
		$isEdit = isset($_REQUEST['add']) ? 0 : 1;

		// Make sure we have our stuff.
		require_once(SUBSDIR . '/Poll.subs.php');

		// Get the starter and the poll's ID - if it's an edit.
		$bcinfo = getPollStarter($topic);

		// Check their adding/editing is valid.
		if (!$isEdit && !empty($bcinfo['id_poll']))
			fatal_lang_error('poll_already_exists');
		// Are we editing a poll which doesn't exist?
		elseif ($isEdit && empty($bcinfo['id_poll']))
			fatal_lang_error('poll_not_found');

		// Check if they have the power to add or edit the poll.
		if ($isEdit && !allowedTo('poll_edit_any'))
			isAllowedTo('poll_edit_' . ($user_info['id'] == $bcinfo['id_member_started'] || ($bcinfo['poll_starter'] != 0 && $user_info['id'] == $bcinfo['poll_starter']) ? 'own' : 'any'));
		elseif (!$isEdit && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_' . ($user_info['id'] == $bcinfo['id_member_started'] ? 'own' : 'any'));

		$optionCount = 0;
		$idCount = 0;

		// Ensure the user is leaving a valid amount of options - there must be at least two.
		foreach ($_POST['options'] as $k => $option)
		{
			if (trim($option) != '')
			{
				$optionCount++;
				$idCount = max($idCount, $k);
			}
		}

		if ($optionCount < 2)
			$poll_errors->addError('poll_few');
		elseif ($optionCount > 256 || $idCount > 255)
			$poll_errors->addError('poll_many');

		// Also - ensure they are not removing the question.
		if (trim($_POST['question']) == '')
			$poll_errors->addError('no_question');

		// Got any errors to report?
		if ($poll_errors->hasErrors())
			return $this->action_editpoll();

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// Now we've done all our error checking, let's get the core poll information cleaned... question first.
		$_POST['question'] = Util::htmlspecialchars($_POST['question']);
		$_POST['question'] = Util::substr($_POST['question'], 0, 255);

		$_POST['poll_hide'] = (int) $_POST['poll_hide'];
		$_POST['poll_expire'] = isset($_POST['poll_expire']) ? (int) $_POST['poll_expire'] : 0;
		$_POST['poll_change_vote'] = isset($_POST['poll_change_vote']) ? 1 : 0;
		$_POST['poll_guest_vote'] = isset($_POST['poll_guest_vote']) ? 1 : 0;

		// Make sure guests are actually allowed to vote generally.
		if ($_POST['poll_guest_vote'])
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$allowedGroups = groupsAllowedTo('poll_vote', $board);
			if (!in_array(-1, $allowedGroups['allowed']))
				$_POST['poll_guest_vote'] = 0;
		}

		// Ensure that the number options allowed makes sense, and the expiration date is valid.
		if (!$isEdit || allowedTo('moderate_board'))
		{
			$_POST['poll_expire'] = $_POST['poll_expire'] > 9999 ? 9999 : ($_POST['poll_expire'] < 0 ? 0 : $_POST['poll_expire']);

			if (empty($_POST['poll_expire']) && $_POST['poll_hide'] == 2)
				$_POST['poll_hide'] = 1;
			elseif (!$isEdit || $_POST['poll_expire'] != ceil($bcinfo['expire_time'] <= time() ? -1 : ($bcinfo['expire_time'] - time()) / (3600 * 24)))
				$_POST['poll_expire'] = empty($_POST['poll_expire']) ? '0' : time() + $_POST['poll_expire'] * 3600 * 24;
			else
				$_POST['poll_expire'] = $bcinfo['expire_time'];

			if (empty($_POST['poll_max_votes']) || $_POST['poll_max_votes'] <= 0)
				$_POST['poll_max_votes'] = 1;
			else
				$_POST['poll_max_votes'] = (int) $_POST['poll_max_votes'];
		}

		// If we're editing, let's commit the changes.
		if ($isEdit)
		{
			modifyPoll($bcinfo['id_poll'], $_POST['question'],
				!empty($_POST['poll_max_votes']) ? $_POST['poll_max_votes'] : 0,
				$_POST['poll_hide'],
				!empty($_POST['poll_expire']) ? $_POST['poll_expire'] : 0,
				$_POST['poll_change_vote'], $_POST['poll_guest_vote']
			);
		}
		// Otherwise, let's get our poll going!
		else
		{
			// Create the poll.
			$bcinfo['id_poll'] = createPoll($_POST['question'], $user_info['id'], $user_info['username'],
				$_POST['poll_max_votes'], $_POST['poll_hide'], $_POST['poll_expire'],
				$_POST['poll_change_vote'], $_POST['poll_guest_vote']
			);

			// Link the poll to the topic.
			associatedPoll($topic, $bcinfo['id_poll']);
		}

		// Get all the choices.  (no better way to remove all emptied and add previously non-existent ones.)
		$choices = array_keys(pollOptions($bcinfo['id_poll']));

		$add_options = array();
		$update_options = array();
		$delete_options = array();
		foreach ($_POST['options'] as $k => $option)
		{
			// Make sure the key is numeric for sanity's sake.
			$k = (int) $k;

			// They've cleared the box.  Either they want it deleted, or it never existed.
			if (trim($option) == '')
			{
				// They want it deleted.  Bye.
				if (in_array($k, $choices))
					$delete_options[] = $k;

				// Skip the rest...
				continue;
			}

			// Dress the option up for its big date with the database.
			$option = Util::htmlspecialchars($option);

			// If it's already there, update it.  If it's not... add it.
			if (in_array($k, $choices))
				$update_options[] = array($bcinfo['id_poll'], $k, $option);
			else
				$add_options[] = array($bcinfo['id_poll'], $k, $option, 0);
		}
		if (!empty($update_options))
			modifyPollOption($update_options);

		if (!empty($add_options))
			insertPollOptions($add_options);

		// I'm sorry, but... well, no one was choosing you. Poor options, I'll put you out of your misery.
		if (!empty($delete_options))
			deletePollOptions($bcinfo['id_poll'], $delete_options);

		// Shall I reset the vote count, sir?
		if (isset($_POST['resetVoteCount']))
			resetVotes($bcinfo['id_poll']);

		call_integration_hook('integrate_poll_add_edit', array($bcinfo['id_poll'], $isEdit));

		// Off we go.
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Remove a poll from a topic without removing the topic.
	 * Must be called with a topic specified in the URL.
	 * Requires poll_remove_any permission, unless it's the poll starter
	 * with poll_remove_own permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=poll;sa=remove.
	 */
	public function action_remove()
	{
		global $topic, $user_info;

		// Make sure the topic is not empty.
		if (empty($topic))
			fatal_lang_error('no_access', false);

		// Verify the session.
		checkSession('get');

		// We need to work with them polls.
		require_once(SUBSDIR . '/Poll.subs.php');

		// Check permissions.
		if (!allowedTo('poll_remove_any'))
		{
			$pollStarters = pollStarters($topic);
			if (empty($pollStarters))
				fatal_lang_error('no_access', false);
			list ($topicStarter, $pollStarter) = $pollStarters;
			if ($topicStarter == $user_info['id'] || ($pollStarter != 0 && $pollStarter == $user_info['id']))
				isAllowedTo('poll_remove_own');
		}

		// Retrieve the poll ID.
		$pollID = associatedPoll($topic);

		// Remove the poll!
		removePoll($pollID);

		// Finally set the topic poll ID back to 0!
		associatedPoll($topic, 0);

		// A mod might have logged this (social network?), so let them remove, it too
		call_integration_hook('integrate_poll_remove', array($pollID));

		// Take the moderator back to the topic.
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * The only reason of this function is to build the poll UI and send it back in an XML form
	 */
	public function action_interface()
	{
		global $context, $board, $db_show_debug;

		loadTemplate('Poll');
		loadLanguage('Post');
		Template_Layers::getInstance()->removeAll();

		if (isset($db_show_debug))
			$db_show_debug = false;

		$context['sub_template'] = 'poll_edit';

		require_once(SUBSDIR . '/Members.subs.php');
		$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

		// Set up the poll options.
		$context['poll'] = array(
			'max_votes' => 1,
			'hide_results' => 0,
			'expiration' => '',
			'change_vote' => false,
			'guest_vote' => false,
			'guest_vote_allowed' => in_array(-1, $allowedVoteGroups['allowed']),
		);

		$context['can_moderate_poll'] = true;

		// Make all five poll choices empty.
		$context['choices'] = array(
			array('id' => 0, 'number' => 1, 'label' => '', 'is_last' => false),
			array('id' => 1, 'number' => 2, 'label' => '', 'is_last' => false),
			array('id' => 2, 'number' => 3, 'label' => '', 'is_last' => false),
			array('id' => 3, 'number' => 4, 'label' => '', 'is_last' => false),
			array('id' => 4, 'number' => 5, 'label' => '', 'is_last' => true)
		);
		$context['last_choice_id'] = 4;

	}
}
