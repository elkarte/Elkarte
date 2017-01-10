<?php

/**
 * The job of this file is to handle everything related to posting replies,
 * new topics, quotes, and modifications to existing posts.  It also handles
 * quoting posts by way of javascript.
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
 * @version 1.0.5
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Post Controller
 */
class Post_Controller extends Action_Controller
{
	/**
	 * Dispatch to the right action method for the request.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Figure out the right action to do.
		// hint: I'm post controller. :P
		$this->action_post();
	}

	/**
	 * Handles showing the post screen, loading the post to be modified, and loading any post quoted.
	 *
	 * - additionally handles previews of posts.
	 * - requires different permissions depending on the actions, but most notably post_new, post_reply_own, and post_reply_any.
	 * - shows options for the editing and posting of calendar events and attachments, as well as the posting of polls.
	 * - accessed from ?action=post.
	 *
	 * @uses the Post template and language file, main sub template.
	 */
	public function action_post()
	{
		global $txt, $scripturl, $topic, $modSettings, $board, $user_info, $context, $options;

		loadLanguage('Post');
		loadLanguage('Errors');
		require_once(SOURCEDIR . '/AttachmentErrorContext.class.php');

		// You can't reply with a poll... hacker.
		if (isset($_REQUEST['poll']) && !empty($topic) && !isset($_REQUEST['msg']))
			unset($_REQUEST['poll']);

		$post_errors = Error_Context::context('post', 1);
		$attach_errors = Attachment_Error_Context::context();
		$attach_errors->activate();
		$first_subject = '';

		// Posting an event?
		$context['make_event'] = isset($_REQUEST['calendar']);
		$context['robot_no_index'] = true;
		$template_layers = Template_Layers::getInstance();
		$template_layers->add('postarea');

		// You must be posting to *some* board.
		if (empty($board) && !$context['make_event'])
			fatal_lang_error('no_board', false);

		if ($context['make_event'])
			$template_layers->add('make_event');

		// All those wonderful modifiers and attachments
		$template_layers->add('additional_options', 200);

		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		if (isset($_REQUEST['xml']))
		{
			$context['sub_template'] = 'post';

			// Just in case of an earlier error...
			$context['preview_message'] = '';
			$context['preview_subject'] = '';
		}

		if (!empty($modSettings['mentions_enabled']) && !empty($_REQUEST['uid']))
			$context['member_ids'] = array_unique(array_map('intval', $_REQUEST['uid']));

		// No message is complete without a topic.
		if (empty($topic) && !empty($_REQUEST['msg']))
		{
			$topic = associatedTopic((int) $_REQUEST['msg']);
			if (empty($topic))
				unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);
		}

		// Check if it's locked. It isn't locked if no topic is specified.
		if (!empty($topic))
		{
			list ($locked, $context['notify'], $sticky, $pollID, $context['topic_last_message'], $id_member_poster, $id_first_msg, $first_subject, $lastPostTime) = array_values(topicUserAttributes($topic, $user_info['id']));

			// If this topic already has a poll, they sure can't add another.
			if (isset($_REQUEST['poll']) && $pollID > 0)
				unset($_REQUEST['poll']);

			if (empty($_REQUEST['msg']))
			{
				if ($user_info['is_guest'] && !allowedTo('post_reply_any') && (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_replies_any')))
					is_not_guest();

				// By default the reply will be approved...
				$context['becomes_approved'] = true;
				if ($id_member_poster != $user_info['id'])
				{
					if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
						$context['becomes_approved'] = false;
					else
						isAllowedTo('post_reply_any');
				}
				elseif (!allowedTo('post_reply_any'))
				{
					if ($modSettings['postmod_active'])
					{
						if (allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
							$context['becomes_approved'] = false;
						// Guests do not have post_unapproved_replies_own permission, so it's always post_unapproved_replies_any
						elseif ($user_info['is_guest'] && allowedTo('post_unapproved_replies_any'))
							$context['becomes_approved'] = false;
						else
							isAllowedTo('post_reply_own');
					}
					else
						isAllowedTo('post_reply_own');
				}
			}
			else
				$context['becomes_approved'] = true;

			$context['can_lock'] = allowedTo('lock_any') || ($user_info['id'] == $id_member_poster && allowedTo('lock_own'));
			$context['can_sticky'] = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);
			$context['notify'] = !empty($context['notify']);
			$context['sticky'] = isset($_REQUEST['sticky']) ? !empty($_REQUEST['sticky']) : $sticky;

			// It's a new reply
			if (empty($_REQUEST['msg']))
				$context['can_add_poll'] = false;
			else
				$context['can_add_poll'] = (allowedTo('poll_add_any') || (!empty($_REQUEST['msg']) && $id_first_msg == $_REQUEST['msg'] && allowedTo('poll_add_own'))) && !empty($modSettings['pollMode']) && $pollID <= 0;
		}
		else
		{
			$context['becomes_approved'] = true;
			if ((!$context['make_event'] || !empty($board)))
			{
				if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_new');
			}

			$locked = 0;

			// @todo These won't work if you're making an event.
			$context['can_lock'] = allowedTo(array('lock_any', 'lock_own'));
			$context['can_sticky'] = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);

			$context['notify'] = !empty($context['notify']);
			$context['sticky'] = !empty($_REQUEST['sticky']);
			$context['can_add_poll'] = (allowedTo('poll_add_any') || allowedTo('poll_add_own')) && !empty($modSettings['pollMode']);
		}

		// @todo These won't work if you're posting an event!
		$context['can_notify'] = allowedTo('mark_any_notify');
		$context['can_move'] = allowedTo('move_any');
		$context['move'] = !empty($_REQUEST['move']);
		$context['announce'] = !empty($_REQUEST['announce']);

		if ($context['can_add_poll'])
		{
			addJavascriptVar(array(
				'poll_remove' => $txt['poll_remove'],
				'poll_add' => $txt['add_poll']), true);
		}

		// You can only announce topics that will get approved...
		$context['can_announce'] = allowedTo('announce_topic') && $context['becomes_approved'];
		$context['locked'] = !empty($locked) || !empty($_REQUEST['lock']);
		$context['can_quote'] = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		// Generally don't show the approval box... (Assume we want things approved)
		$context['show_approval'] = allowedTo('approve_posts') && $context['becomes_approved'] ? 2 : (allowedTo('approve_posts') ? 1 : 0);

		// An array to hold all the attachments for this topic.
		$context['attachments']['current'] = array();

		// Don't allow a post if it's locked and you aren't all powerful.
		if ($locked && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		// Check the users permissions - is the user allowed to add or post a poll?
		if (isset($_REQUEST['poll']) && !empty($modSettings['pollMode']))
		{
			// New topic, new poll.
			if (empty($topic))
				isAllowedTo('poll_post');
			// This is an old topic - but it is yours!  Can you add to it?
			elseif ($user_info['id'] == $id_member_poster && !allowedTo('poll_add_any'))
				isAllowedTo('poll_add_own');
			// If you're not the owner, can you add to any poll?
			else
				isAllowedTo('poll_add_any');
			$context['can_moderate_poll'] = true;

			require_once(SUBSDIR . '/Members.subs.php');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

			// Set up the poll options.
			$context['poll'] = array(
				'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
				'hide_results' => empty($_POST['poll_hide']) ? 0 : (int) $_POST['poll_hide'],
				'expiration' => !isset($_POST['poll_expire']) ? 0 : (int) $_POST['poll_expire'],
				'change_vote' => isset($_POST['poll_change_vote']),
				'guest_vote' => isset($_POST['poll_guest_vote']),
				'guest_vote_allowed' => in_array(-2, $allowedVoteGroups['allowed']),
			);

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

		if ($context['make_event'])
		{
			// They might want to pick a board.
			if (!isset($context['current_board']))
				$context['current_board'] = 0;

			// Start loading up the event info.
			$context['event'] = array();
			$context['event']['title'] = isset($_REQUEST['evtitle']) ? htmlspecialchars(stripslashes($_REQUEST['evtitle']), ENT_COMPAT, 'UTF-8') : '';
			$context['event']['id'] = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : -1;
			$context['event']['new'] = $context['event']['id'] == -1;

			// Permissions check!
			isAllowedTo('calendar_post');

			// Editing an event?  (but NOT previewing!?)
			if (empty($context['event']['new']) && !isset($_REQUEST['subject']))
			{
				// If the user doesn't have permission to edit the post in this topic, redirect them.
				if ((empty($id_member_poster) || $id_member_poster != $user_info['id'] || !allowedTo('modify_own')) && !allowedTo('modify_any'))
				{
					require_once(CONTROLLERDIR . '/Calendar.controller.php');
					$controller = new Calendar_Controller();
					return $controller->action_post();
				}

				// Get the current event information.
				require_once(SUBSDIR . '/Calendar.subs.php');
				$event_info = getEventProperties($context['event']['id']);

				// Make sure the user is allowed to edit this event.
				if ($event_info['member'] != $user_info['id'])
					isAllowedTo('calendar_edit_any');
				elseif (!allowedTo('calendar_edit_any'))
					isAllowedTo('calendar_edit_own');

				$context['event']['month'] = $event_info['month'];
				$context['event']['day'] = $event_info['day'];
				$context['event']['year'] = $event_info['year'];
				$context['event']['title'] = $event_info['title'];
				$context['event']['span'] = $event_info['span'];
			}
			else
			{
				// Posting a new event? (or preview...)
				$today = getdate();

				// You must have a month and year specified!
				if (!isset($_REQUEST['month']))
					$_REQUEST['month'] = $today['mon'];

				if (!isset($_REQUEST['year']))
					$_REQUEST['year'] = $today['year'];

				$context['event']['month'] = (int) $_REQUEST['month'];
				$context['event']['year'] = (int) $_REQUEST['year'];
				$context['event']['day'] = isset($_REQUEST['day']) ? $_REQUEST['day'] : ($_REQUEST['month'] == $today['mon'] ? $today['mday'] : 0);
				$context['event']['span'] = isset($_REQUEST['span']) ? $_REQUEST['span'] : 1;

				// Make sure the year and month are in the valid range.
				if ($context['event']['month'] < 1 || $context['event']['month'] > 12)
					fatal_lang_error('invalid_month', false);

				if ($context['event']['year'] < $modSettings['cal_minyear'] || $context['event']['year'] > $modSettings['cal_maxyear'])
					fatal_lang_error('invalid_year', false);

				// Get a list of boards they can post in.
				require_once(SUBSDIR . '/Boards.subs.php');

				$boards = boardsAllowedTo('post_new');
				if (empty($boards))
					fatal_lang_error('cannot_post_new', 'user');

				// Load a list of boards for this event in the context.
				$boardListOptions = array(
					'included_boards' => in_array(0, $boards) ? null : $boards,
					'not_redirection' => true,
					'selected_board' => empty($context['current_board']) ? $modSettings['cal_defaultboard'] : $context['current_board'],
				);
				$context += getBoardList($boardListOptions);
			}

			// Find the last day of the month.
			$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

			$context['event']['board'] = !empty($board) ? $board : $modSettings['cal_defaultboard'];
		}

		// See if any new replies have come along.
		if (empty($_REQUEST['msg']) && !empty($topic))
		{
			if (empty($options['no_new_reply_warning']) && isset($_REQUEST['last_msg']) && $context['topic_last_message'] > $_REQUEST['last_msg'])
			{
				$context['new_replies'] = countMessagesSince($topic, (int) $_REQUEST['last_msg'], false, $modSettings['postmod_active'] && !allowedTo('approve_posts'));

				if (!empty($context['new_replies']))
				{
					if ($context['new_replies'] == 1)
						$txt['error_new_replies'] = isset($_GET['last_msg']) ? $txt['error_new_reply_reading'] : $txt['error_new_reply'];
					else
						$txt['error_new_replies'] = sprintf(isset($_GET['last_msg']) ? $txt['error_new_replies_reading'] : $txt['error_new_replies'], $context['new_replies']);

					$post_errors->addError('new_replies', 0);

					$modSettings['topicSummaryPosts'] = $context['new_replies'] > $modSettings['topicSummaryPosts'] ? max($modSettings['topicSummaryPosts'], 5) : $modSettings['topicSummaryPosts'];
				}
			}
		}

		// Get a response prefix (like 'Re:') in the default forum language.
		$context['response_prefix'] = response_prefix();

		// Previewing, modifying, or posting?
		// Do we have a body, but an error happened.
		if (isset($_REQUEST['message']) || $post_errors->hasErrors() || $attach_errors->hasErrors())
		{
			// Validate inputs.
			if (!$post_errors->hasErrors() && !$attach_errors->hasErrors())
			{
				// This means they didn't click Post and get an error.
				$really_previewing = true;
			}
			else
			{
				if (!isset($_REQUEST['subject']))
					$_REQUEST['subject'] = '';

				if (!isset($_REQUEST['message']))
					$_REQUEST['message'] = '';

				if (!isset($_REQUEST['icon']))
					$_REQUEST['icon'] = 'xx';

				// They are previewing if they asked to preview (i.e. came from quick reply).
				$really_previewing = !empty($_REQUEST['preview']);
			}

			// In order to keep the approval status flowing through, we have to pass it through the form...
			$context['becomes_approved'] = empty($_REQUEST['not_approved']);
			$context['show_approval'] = isset($_REQUEST['approve']) ? ($_REQUEST['approve'] ? 2 : 1) : 0;
			$context['can_announce'] &= $context['becomes_approved'];

			// Set up the inputs for the form.
			$form_subject = strtr(Util::htmlspecialchars($_REQUEST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
			$form_message = Util::htmlspecialchars($_REQUEST['message'], ENT_QUOTES, 'UTF-8', true);

			// Make sure the subject isn't too long - taking into account special characters.
			if (Util::strlen($form_subject) > 100)
				$form_subject = Util::substr($form_subject, 0, 100);

			if (isset($_REQUEST['poll']))
			{
				$context['poll']['question'] = isset($_REQUEST['question']) ? Util::htmlspecialchars(trim($_REQUEST['question'])) : '';

				$context['choices'] = array();
				$choice_id = 0;

				$_POST['options'] = empty($_POST['options']) ? array() : htmlspecialchars__recursive($_POST['options']);
				foreach ($_POST['options'] as $option)
				{
					if (trim($option) == '')
						continue;

					$context['choices'][] = array(
						'id' => $choice_id++,
						'number' => $choice_id,
						'label' => $option,
						'is_last' => false
					);
				}

				// One empty option for those with js disabled...I know are few... :P
				$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => '',
					'is_last' => false
				);

				if (count($context['choices']) < 2)
				{
					$context['choices'][] = array(
						'id' => $choice_id++,
						'number' => $choice_id,
						'label' => '',
						'is_last' => false
					);
				}

				$context['last_choice_id'] = $choice_id;
				$context['choices'][count($context['choices']) - 1]['is_last'] = true;
			}

			// Are you... a guest?
			if ($user_info['is_guest'])
			{
				$context['name'] = !isset($_REQUEST['guestname']) ? '' : Util::htmlspecialchars(trim($_REQUEST['guestname']));
				$context['email'] = !isset($_REQUEST['email']) ? '' : Util::htmlspecialchars(trim($_REQUEST['email']));
				$user_info['name'] = $context['name'];
			}

			// Only show the preview stuff if they hit Preview.
			if (($really_previewing === true || isset($_REQUEST['xml'])) && !isset($_REQUEST['save_draft']))
			{
				// Set up the preview message and subject
				$context['preview_message'] = $form_message;
				preparsecode($form_message, true);

				// Do all bulletin board code thing on the message
				preparsecode($context['preview_message']);
				$context['preview_message'] = parse_bbc($context['preview_message'], isset($_REQUEST['ns']) ? 0 : 1);
				censorText($context['preview_message']);

				// Don't forget the subject
				$context['preview_subject'] = $form_subject;
				censorText($context['preview_subject']);

				// Any errors we should tell them about?
				if ($form_subject === '')
				{
					$post_errors->addError('no_subject');
					$context['preview_subject'] = '<em>' . $txt['no_subject'] . '</em>';
				}

				if ($context['preview_message'] === '')
					$post_errors->addError('no_message');
				elseif (!empty($modSettings['max_messageLength']) && Util::strlen($form_message) > $modSettings['max_messageLength'])
					$post_errors->addError(array('long_message', array($modSettings['max_messageLength'])));

				// Protect any CDATA blocks.
				if (isset($_REQUEST['xml']))
					$context['preview_message'] = strtr($context['preview_message'], array(']]>' => ']]]]><![CDATA[>'));
			}

			// Set up the checkboxes.
			$context['notify'] = !empty($_REQUEST['notify']);
			$context['use_smileys'] = !isset($_REQUEST['ns']);
			$context['icon'] = isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : 'xx';

			// Set the destination action for submission.
			$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '') . (isset($_REQUEST['poll']) ? ';poll' : '');
			$context['submit_label'] = isset($_REQUEST['msg']) ? $txt['save'] : $txt['post'];

			// Previewing an edit?
			if (isset($_REQUEST['msg']) && !empty($topic))
			{
				require_once(SUBSDIR . '/Messages.subs.php');

				// Get the existing message.
				$message = messageDetails((int) $_REQUEST['msg'], $topic);

				// The message they were trying to edit was most likely deleted.
				// @todo Change this error message?
				if ($message === false)
					fatal_lang_error('no_board', false);

				$errors = checkMessagePermissions($message['message']);
				if (!empty($errors))
					foreach ($errors as $error)
						$post_errors->addError($error);

				prepareMessageContext($message);
			}
			elseif (isset($_REQUEST['last_msg']))
				list ($form_subject,) = getFormMsgSubject(false, $topic, $first_subject);

			// No check is needed, since nothing is really posted.
			checkSubmitOnce('free');
		}
		// Editing a message...
		elseif (isset($_REQUEST['msg']) && !empty($topic))
		{
			$_REQUEST['msg'] = (int) $_REQUEST['msg'];

			$message = getFormMsgSubject(true, $topic);
			if (!empty($message['errors']))
				foreach ($errors as $error)
					$post_errors->addError($error);

			// Get the stuff ready for the form.
			$form_subject = $message['message']['subject'];
			$form_message = un_preparsecode($message['message']['body']);

			censorText($form_message);
			censorText($form_subject);

			// Check the boxes that should be checked.
			$context['use_smileys'] = !empty($message['message']['smileys_enabled']);
			$context['icon'] = $message['message']['icon'];

			// Set the destination.
			$context['destination'] = 'post2;start=' . $_REQUEST['start'] . ';msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] . (isset($_REQUEST['poll']) ? ';poll' : '');
			$context['submit_label'] = $txt['save'];
		}
		// Posting...
		else
		{
			// By default....
			$context['use_smileys'] = true;
			$context['icon'] = 'xx';

			if ($user_info['is_guest'])
			{
				$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
				$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
			}
			$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['poll']) ? ';poll' : '');

			$context['submit_label'] = $txt['post'];

			list ($form_subject, $form_message) = getFormMsgSubject(false, $topic, $first_subject);
		}

		// Check whether this is a really old post being bumped...
		if (!empty($topic) && !empty($modSettings['oldTopicDays']) && $lastPostTime + $modSettings['oldTopicDays'] * 86400 < time() && empty($sticky) && !isset($_REQUEST['subject']))
			$post_errors->addError(array('old_topic', array($modSettings['oldTopicDays'])), 0);

		// Are we moving a discussion to its own topic?
		if (!empty($modSettings['enableFollowup']) && !empty($_REQUEST['followup']))
		{
			$context['original_post'] = isset($_REQUEST['quote']) ? (int) $_REQUEST['quote'] : (int) $_REQUEST['followup'];
			$context['show_boards_dropdown'] = true;
			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList(array('not_redirection' => true, 'allowed_to' => 'post_new'));
			$context['boards_current_disabled'] = false;
			if (!empty($board))
			{
				foreach ($context['categories'] as $id => $values)
					if (isset($values['boards'][$board]))
					{
						$context['categories'][$id]['boards'][$board]['selected'] = true;
						break;
					}
			}
		}

		$context['attachments']['can']['post'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
		if ($context['attachments']['can']['post'])
		{
			// If there are attachments, calculate the total size and how many.
			$attachments = array();
			$attachments['total_size'] = 0;
			$attachments['quantity'] = 0;

			// If this isn't a new post, check the current attachments.
			if (isset($_REQUEST['msg']))
			{
				$attachments['quantity'] = count($context['attachments']['current']);
				foreach ($context['attachments']['current'] as $attachment)
					$attachments['total_size'] += $attachment['size'];
			}

			// A bit of house keeping first.
			if (!empty($_SESSION['temp_attachments']) && count($_SESSION['temp_attachments']) == 1)
				unset($_SESSION['temp_attachments']);

			if (!empty($_SESSION['temp_attachments']))
			{
				// Is this a request to delete them?
				if (isset($_GET['delete_temp']))
				{
					foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
					{
						if (strpos($attachID, 'post_tmp_' . $user_info['id']) !== false)
							@unlink($attachment['tmp_name']);
					}
					$attach_errors->addError('temp_attachments_gone');
					$_SESSION['temp_attachments'] = array();
				}
				// Hmm, coming in fresh and there are files in session.
				elseif ($context['current_action'] != 'post2' || !empty($_POST['from_qr']))
				{
					// Let's be nice and see if they belong here first.
					if ((empty($_REQUEST['msg']) && empty($_SESSION['temp_attachments']['post']['msg']) && $_SESSION['temp_attachments']['post']['board'] == $board) || (!empty($_REQUEST['msg']) && $_SESSION['temp_attachments']['post']['msg'] == $_REQUEST['msg']))
					{
						// See if any files still exist before showing the warning message and the files attached.
						foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
						{
							if (strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
								continue;

							if (file_exists($attachment['tmp_name']))
							{
								$attach_errors->addError('temp_attachments_new');
								$context['files_in_session_warning'] = $txt['attached_files_in_session'];
								unset($_SESSION['temp_attachments']['post']['files']);
								break;
							}
						}
					}
					else
					{
						// Since, they don't belong here. Let's inform the user that they exist..
						if (!empty($topic))
							$delete_url = $scripturl . '?action=post' .(!empty($_REQUEST['msg']) ? (';msg=' . $_REQUEST['msg']) : '') . (!empty($_REQUEST['last_msg']) ? (';last_msg=' . $_REQUEST['last_msg']) : '') . ';topic=' . $topic . ';delete_temp';
						else
							$delete_url = $scripturl . '?action=post;board=' . $board . ';delete_temp';

						// Compile a list of the files to show the user.
						$file_list = array();
						foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
							if (strpos($attachID, 'post_tmp_' . $user_info['id']) !== false)
								$file_list[] = $attachment['name'];

						$_SESSION['temp_attachments']['post']['files'] = $file_list;
						$file_list = '<div class="attachments">' . implode('<br />', $file_list) . '</div>';

						if (!empty($_SESSION['temp_attachments']['post']['msg']))
						{
							// We have a message id, so we can link back to the old topic they were trying to edit..
							$goback_link = '<a href="' . $scripturl . '?action=post' .(!empty($_SESSION['temp_attachments']['post']['msg']) ? (';msg=' . $_SESSION['temp_attachments']['post']['msg']) : '') . (!empty($_SESSION['temp_attachments']['post']['last_msg']) ? (';last_msg=' . $_SESSION['temp_attachments']['post']['last_msg']) : '') . ';topic=' . $_SESSION['temp_attachments']['post']['topic'] . ';additionalOptions">' . $txt['here'] . '</a>';

							$attach_errors->addError(array('temp_attachments_found', array($delete_url, $goback_link, $file_list)));
							$context['ignore_temp_attachments'] = true;
						}
						else
						{
							$attach_errors->addError(array('temp_attachments_lost', array($delete_url, $file_list)));
							$context['ignore_temp_attachments'] = true;
						}
					}
				}

				foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
				{
					// Skipping over these
					if (isset($context['ignore_temp_attachments']) || isset($_SESSION['temp_attachments']['post']['files']))
						break;

					// Initial errors (such as missing directory), we can recover
					if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
						continue;

					if ($attachID == 'initial_error')
					{
						if ($context['current_action'] != 'post2')
						{
							$txt['error_attach_initial_error'] = $txt['attach_no_upload'] . '<div class="attachmenterrors">' . (is_array($attachment) ? vsprintf($txt[$attachment[0]], $attachment[1]) : $txt[$attachment]) . '</div>';
							$attach_errors->addError('attach_initial_error');
						}
						unset($_SESSION['temp_attachments']);
						break;
					}

					// Show any errors which might have occurred.
					if (!empty($attachment['errors']))
					{
						if ($context['current_action'] != 'post2')
						{
							$txt['error_attach_errors'] = empty($txt['error_attach_errors']) ? '<br />' : '';
							$txt['error_attach_errors'] .= vsprintf($txt['attach_warning'], $attachment['name']) . '<div class="attachmenterrors">';
							foreach ($attachment['errors'] as $error)
								$txt['error_attach_errors'] .= (is_array($error) ? vsprintf($txt[$error[0]], $error[1]) : $txt[$error]) . '<br  />';
							$txt['error_attach_errors'] .= '</div>';
							$attach_errors->addError('attach_errors');
						}

						// Take out the trash.
						unset($_SESSION['temp_attachments'][$attachID]);
						@unlink($attachment['tmp_name']);

						continue;
					}

					// More house keeping.
					if (!file_exists($attachment['tmp_name']))
					{
						unset($_SESSION['temp_attachments'][$attachID]);
						continue;
					}

					$attachments['quantity']++;
					$attachments['total_size'] += $attachment['size'];

					if (!isset($context['files_in_session_warning']))
						$context['files_in_session_warning'] = $txt['attached_files_in_session'];

					$context['attachments']['current'][] = array(
						'name' => '<u>' . htmlspecialchars($attachment['name'], ENT_COMPAT, 'UTF-8') . '</u>',
						'size' => $attachment['size'],
						'id' => $attachID,
						'unchecked' => false,
						'approved' => 1,
					);
				}
			}
		}

		// Do we need to show the visual verification image?
		$context['require_verification'] = !$user_info['is_moderator'] && !$user_info['is_admin'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
		if ($context['require_verification'])
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
			$verificationOptions = array(
				'id' => 'post',
			);
			$context['require_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		// If they came from quick reply, and have to enter verification details, give them some notice.
		if (!empty($_REQUEST['from_qr']) && !empty($context['require_verification']))
			$post_errors->addError('need_qr_verification');

		// Any errors occurred?
		$context['post_error'] = array(
			'errors' => $post_errors->prepareErrors(),
			'type' => $post_errors->getErrorType() == 0 ? 'minor' : 'serious',
			'title' => $post_errors->getErrorType() == 0 ? $txt['warning_while_submitting'] : $txt['error_while_submitting'],
		);

		// If there are attachment errors. Let's show a list to the user.
		if ($attach_errors->hasErrors())
		{
			loadTemplate('Errors');

			$errors = $attach_errors->prepareErrors();

			foreach ($errors as $key => $error)
			{
				$context['attachment_error_keys'][] = $key . '_error';
				$context[$key . '_error'] = $error;
			}
		}

		// What are you doing? Posting a poll, modifying, previewing, new post, or reply...
		if (isset($_REQUEST['poll']))
			$context['page_title'] = $txt['new_poll'];
		elseif ($context['make_event'])
			$context['page_title'] = $context['event']['id'] == -1 ? $txt['calendar_post_event'] : $txt['calendar_edit'];
		elseif (isset($_REQUEST['msg']))
			$context['page_title'] = $txt['modify_msg'];
		elseif (isset($_REQUEST['subject'], $context['preview_subject']))
			$context['page_title'] = $txt['post_reply'];
		elseif (empty($topic))
			$context['page_title'] = $txt['start_new_topic'];
		else
			$context['page_title'] = $txt['post_reply'];

		// Update the topic summary, needed to show new posts in a preview
		if (!empty($topic) && !empty($modSettings['topicSummaryPosts']))
		{
			$only_approved = $modSettings['postmod_active'] && !allowedTo('approve_posts');

			if (isset($_REQUEST['xml']))
				$limit = empty($context['new_replies']) ? 0 : (int) $context['new_replies'];
			else
				$limit = $modSettings['topicSummaryPosts'];

			$before = isset($_REQUEST['msg']) ? array('before' => (int) $_REQUEST['msg']) : array();

			$counter = 0;
			$context['previous_posts'] = empty($limit) ? array() : selectMessages($topic, 0, $limit, $before, $only_approved);
			foreach ($context['previous_posts'] as &$post)
			{
				$post['is_new'] = !empty($context['new_replies']);
				$post['counter'] = $counter++;
				$post['is_ignored'] = !empty($modSettings['enable_buddylist']) && in_array($post['id_poster'], $user_info['ignoreusers']);

				if (!empty($context['new_replies']))
					$context['new_replies']--;
			}
		}

		// Just ajax previewing then lets stop now
		if (isset($_REQUEST['xml']))
			obExit();

		// Build the link tree.
		if (empty($topic))
		{
			$context['linktree'][] = array(
				'name' => '<em>' . $txt['start_new_topic'] . '</em>'
			);
		}
		else
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'],
				'name' => $form_subject,
				'extra_before' => '<span><strong class="nav">' . $context['page_title'] . ' ( </strong></span>',
				'extra_after' => '<span><strong class="nav"> )</strong></span>'
			);
		}

		$context['subject'] = addcslashes($form_subject, '"');
		$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

		// Are post drafts enabled?
		$context['drafts_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft');
		$context['drafts_autosave'] = !empty($context['drafts_save']) && !empty($modSettings['drafts_autosave_enabled']) && allowedTo('post_autosave_draft');

		if (!empty($modSettings['mentions_enabled']))
		{
			$context['mentions_enabled'] = true;
			loadCSSFile('jquery.atwho.css');

			addInlineJavascript('
			$(document).ready(function () {
				for (var i = 0, count = all_elk_mentions.length; i < count; i++)
					all_elk_mentions[i].oMention = new elk_mentions(all_elk_mentions[i].oOptions);
			});');
		}

		// Build a list of drafts that they can load into the editor
		if (!empty($context['drafts_save']))
		{
			$this->_prepareDraftsContext($user_info['id'], $topic);

			if (!empty($context['drafts']))
				$template_layers->add('load_drafts', 100);
		}

		// Needed for the editor and message icons.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Now create the editor.
		$editorOptions = array(
			'id' => 'message',
			'value' => $context['message'],
			'labels' => array(
				'post_button' => $context['submit_label'],
			),
			// add height and width for the editor
			'height' => '275px',
			'width' => '100%',
			// We do XML preview here.
			'preview_type' => 2
		);
		create_control_richedit($editorOptions);

		$context['attached'] = '';
		$context['make_poll'] = isset($_REQUEST['poll']);

		if ($context['make_poll'])
		{
			loadTemplate('Poll');
			$template_layers->add('poll_edit');
		}

		// Message icons - customized or not, retrieve them...
		$context['icons'] = getMessageIcons($board);

		$context['icon_url'] = '';

		if (!empty($context['icons']))
		{
			$context['icons'][count($context['icons']) - 1]['is_last'] = true;
			$context['icons'][0]['selected'] = true;
			// $context['icon'] is set when editing a message
			if (!isset($context['icon']))
				$context['icon'] = $context['icons'][0]['value'];
			$found = false;
			foreach ($context['icons'] as $icon)
			{
				if ($icon['value'] === $context['icon'])
				{
					$found = true;
					$context['icon_url'] = $icon['url'];
					break;
				}
			}
			// Failsafe
			if (!$found)
			{
				$context['icon'] = $context['icons'][0]['value'];
				$context['icon_url'] = $context['icons'][0]['url'];
			}
		}

		// Are we starting a poll? if set the poll icon as selected if its available
		if (isset($_REQUEST['poll']))
		{
			for ($i = 0, $n = count($context['icons']); $i < $n; $i++)
			{
				if ($context['icons'][$i]['value'] == 'poll')
				{
					$context['icons'][$i]['selected'] = true;
					$context['icon'] = 'poll';
					$context['icon_url'] = $context['icons'][$i]['url'];
					break;
				}
			}
		}

		// If the user can post attachments prepare the warning labels.
		if ($context['attachments']['can']['post'])
		{
			// If they've unchecked an attachment, they may still want to attach that many more files, but don't allow more than num_allowed_attachments.
			$context['attachments']['num_allowed'] = empty($modSettings['attachmentNumPerPostLimit']) ? 50 : min($modSettings['attachmentNumPerPostLimit'] - count($context['attachments']['current']), $modSettings['attachmentNumPerPostLimit']);
			$context['attachments']['can']['post_unapproved'] = allowedTo('post_attachment');
			$context['attachments']['restrictions'] = array();
			if (!empty($modSettings['attachmentCheckExtensions']))
				$context['attachments']['allowed_extensions'] = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
			else
				$context['attachments']['allowed_extensions'] = '';
			$context['attachments']['templates'] = array(
				'add_new' => 'template_add_new_attachments',
				'existing' => 'template_show_existing_attachments',
			);

			$attachmentRestrictionTypes = array('attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit');
			foreach ($attachmentRestrictionTypes as $type)
			{
				if (!empty($modSettings[$type]))
				{
					$context['attachments']['restrictions'][] = sprintf($txt['attach_restrict_' . $type], comma_format($modSettings[$type], 0));

					// Show some numbers. If they exist.
					if ($type == 'attachmentNumPerPostLimit' && $attachments['quantity'] > 0)
						$context['attachments']['restrictions'][] = sprintf($txt['attach_remaining'], $modSettings['attachmentNumPerPostLimit'] - $attachments['quantity']);
					elseif ($type == 'attachmentPostLimit' && $attachments['total_size'] > 0)
						$context['attachments']['restrictions'][] = sprintf($txt['attach_available'], comma_format(round(max($modSettings['attachmentPostLimit'] - ($attachments['total_size'] / 1028), 0)), 0));
				}
			}

			// Load up the drag and drop attachment magic
			addInlineJavascript('
			var dropAttach = dragDropAttachment.prototype.init({
				board: ' . $board . ',
				allowedExtensions: ' . JavaScriptEscape($context['attachments']['allowed_extensions']) . ',
				totalSizeAllowed: ' . JavaScriptEscape(empty($modSettings['attachmentPostLimit']) ? '' : $modSettings['attachmentPostLimit']) . ',
				individualSizeAllowed: ' . JavaScriptEscape(empty($modSettings['attachmentSizeLimit']) ? '' : $modSettings['attachmentSizeLimit']) . ',
				numOfAttachmentAllowed: ' . $context['attachments']['num_allowed'] . ',
				totalAttachSizeUploaded: ' . (isset($context['attachments']['total_size']) && !empty($context['attachments']['total_size']) ? $context['attachments']['total_size'] : 0) . ',
				numAttachUploaded: ' . (isset($context['attachments']['quantity']) && !empty($context['attachments']['quantity']) ? $context['attachments']['quantity'] : 0) . ',
				oTxt: ({
					allowedExtensions : ' . JavaScriptEscape(sprintf($txt['cant_upload_type'], $context['attachments']['allowed_extensions'])) . ',
					totalSizeAllowed : ' . JavaScriptEscape($txt['attach_max_total_file_size']) . ',
					individualSizeAllowed : ' . JavaScriptEscape(sprintf($txt['file_too_big'], comma_format($modSettings['attachmentSizeLimit'], 0))) . ',
					numOfAttachmentAllowed : ' . JavaScriptEscape(sprintf($txt['attachments_limit_per_post'], $modSettings['attachmentNumPerPostLimit'])) . ',
					postUploadError : ' . JavaScriptEscape($txt['post_upload_error']) . ',
				}),
			});', true);
		}

		$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
		$context['show_additional_options'] = !empty($_POST['additional_options']) || isset($_SESSION['temp_attachments']['post']) || isset($_GET['additionalOptions']);
		$context['is_new_topic'] = empty($topic);
		$context['is_new_post'] = !isset($_REQUEST['msg']);
		$context['is_first_post'] = $context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == $id_first_msg);
		$context['current_action'] = 'post';

		// Register this form in the session variables.
		checkSubmitOnce('register');

		// Finally, load the template.
		if (!isset($_REQUEST['xml']))
		{
			loadTemplate('Post');
			$context['sub_template'] = 'post_page';
		}
	}

	/**
	 * Posts or saves the message composed with Post().
	 *
	 * requires various permissions depending on the action.
	 * handles attachment, post, and calendar saving.
	 * sends off notifications, and allows for announcements and moderation.
	 * accessed from ?action=post2.
	 */
	public function action_post2()
	{
		global $board, $topic, $txt, $modSettings, $context, $user_settings;
		global $user_info, $board_info, $options, $ignore_temp;

		// Sneaking off, are we?
		if (empty($_POST) && empty($topic))
		{
			if (empty($_SERVER['CONTENT_LENGTH']))
				redirectexit('action=post;board=' . $board . '.0');
			else
				fatal_lang_error('post_upload_error', false);
		}
		elseif (empty($_POST) && !empty($topic))
			redirectexit('action=post;topic=' . $topic . '.0');

		// No need!
		$context['robot_no_index'] = true;

		// We are now in post2 action
		$context['current_action'] = 'post2';

		require_once(SOURCEDIR . '/AttachmentErrorContext.class.php');

		// No errors as yet.
		$post_errors = Error_Context::context('post', 1);
		$attach_errors = Attachment_Error_Context::context();

		// If the session has timed out, let the user re-submit their form.
		if (checkSession('post', '', false) != '')
		{
			$post_errors->addError('session_timeout');

			// Disable the preview so that any potentially malicious code is not executed
			$_REQUEST['preview'] = false;

			return $this->action_post();
		}

		// Wrong verification code?
		if (!$user_info['is_admin'] && !$user_info['is_moderator'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1)))
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');
			$verificationOptions = array(
				'id' => 'post',
			);
			$context['require_verification'] = create_control_verification($verificationOptions, true);

			if (is_array($context['require_verification']))
				foreach ($context['require_verification'] as $verification_error)
					$post_errors->addError($verification_error);
		}

		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');
		loadLanguage('Post');

		// Drafts enabled and needed?
		if (!empty($modSettings['drafts_enabled']) && (isset($_POST['save_draft']) || isset($_POST['id_draft'])))
			require_once(SUBSDIR . '/Drafts.subs.php');

		// First check to see if they are trying to delete any current attachments.
		if (isset($_POST['attach_del']))
		{
			$keep_temp = array();
			$keep_ids = array();
			foreach ($_POST['attach_del'] as $dummy)
			{
				if (strpos($dummy, 'post_tmp_' . $user_info['id']) !== false)
					$keep_temp[] = $dummy;
				else
					$keep_ids[] = (int) $dummy;
			}

			if (isset($_SESSION['temp_attachments']))
			{
				foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
				{
					if ((isset($_SESSION['temp_attachments']['post']['files'], $attachment['name']) && in_array($attachment['name'], $_SESSION['temp_attachments']['post']['files'])) || in_array($attachID, $keep_temp) || strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
						continue;

					unset($_SESSION['temp_attachments'][$attachID]);
					@unlink($attachment['tmp_name']);
				}
			}

			if (!empty($_REQUEST['msg']))
			{
				require_once(SUBSDIR . '/ManageAttachments.subs.php');
				$attachmentQuery = array(
					'attachment_type' => 0,
					'id_msg' => (int) $_REQUEST['msg'],
					'not_id_attach' => $keep_ids,
				);
				removeAttachments($attachmentQuery);
			}
		}

		// Then try to upload any attachments.
		$context['attachments']['can']['post'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));
		if ($context['attachments']['can']['post'] && empty($_POST['from_qr']))
		{
			require_once(SUBSDIR . '/Attachments.subs.php');
			if (isset($_REQUEST['msg']))
				processAttachments((int) $_REQUEST['msg']);
			else
				processAttachments();
		}

		// Previewing? Go back to start.
		if (isset($_REQUEST['preview']))
			return $this->action_post();

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// If this isn't a new topic load the topic info that we need.
		if (!empty($topic))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			$topic_info = getTopicInfo($topic);

			// Though the topic should be there, it might have vanished.
			if (empty($topic_info))
				fatal_lang_error('topic_doesnt_exist');

			// Did this topic suddenly move? Just checking...
			if ($topic_info['id_board'] != $board)
				fatal_lang_error('not_a_topic');
		}

		// Replying to a topic?
		if (!empty($topic) && !isset($_REQUEST['msg']))
		{
			// Don't allow a post if it's locked.
			if ($topic_info['locked'] != 0 && !allowedTo('moderate_board'))
				fatal_lang_error('topic_locked', false);

			// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
			if (isset($_REQUEST['poll']) && $topic_info['id_poll'] > 0)
				unset($_REQUEST['poll']);

			// Do the permissions and approval stuff...
			$becomesApproved = true;
			if ($topic_info['id_member_started'] != $user_info['id'])
			{
				if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
					$becomesApproved = false;
				else
					isAllowedTo('post_reply_any');
			}
			elseif (!allowedTo('post_reply_any'))
			{
				if ($modSettings['postmod_active'])
				{
					if (allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
						$becomesApproved = false;
					// Guests do not have post_unapproved_replies_own permission, so it's always post_unapproved_replies_any
					elseif ($user_info['is_guest'] && allowedTo('post_unapproved_replies_any'))
						$becomesApproved = false;
					else
						isAllowedTo('post_reply_own');
				}
			}

			if (isset($_POST['lock']))
			{
				// Nothing is changed to the lock.
				if ((empty($topic_info['locked']) && empty($_POST['lock'])) || (!empty($_POST['lock']) && !empty($topic_info['locked'])))
					unset($_POST['lock']);
				// You're have no permission to lock this topic.
				elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $topic_info['id_member_started']))
					unset($_POST['lock']);
				// You are allowed to (un)lock your own topic only.
				elseif (!allowedTo('lock_any'))
				{
					// You cannot override a moderator lock.
					if ($topic_info['locked'] == 1)
						unset($_POST['lock']);
					else
						$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
				}
				// Hail mighty moderator, (un)lock this topic immediately.
				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
			}

			// So you wanna (un)sticky this...let's see.
			if (isset($_POST['sticky']) && (empty($modSettings['enableStickyTopics']) || $_POST['sticky'] == $topic_info['is_sticky'] || !allowedTo('make_sticky')))
				unset($_POST['sticky']);

			// If drafts are enabled, then pass this off
			if (!empty($modSettings['drafts_enabled']) && isset($_POST['save_draft']))
			{
				saveDraft();
				return $this->action_post();
			}

			// If the number of replies has changed, if the setting is enabled, go back to action_post() - which handles the error.
			if (empty($options['no_new_reply_warning']) && isset($_POST['last_msg']) && $topic_info['id_last_msg'] > $_POST['last_msg'])
			{
				addInlineJavascript('
					$(document).ready(function () {
						$("html,body").scrollTop($(\'.category_header:visible:first\').offset().top);
					});'
				);

				return $this->action_post();
			}

			$posterIsGuest = $user_info['is_guest'];
		}
		// Posting a new topic.
		elseif (empty($topic))
		{
			// Now don't be silly, new topics will get their own id_msg soon enough.
			unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);

			// Do like, the permissions, for safety and stuff...
			$becomesApproved = true;
			if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
				$becomesApproved = false;
			else
				isAllowedTo('post_new');

			if (isset($_POST['lock']))
			{
				// New topics are by default not locked.
				if (empty($_POST['lock']))
					unset($_POST['lock']);
				// Besides, you need permission.
				elseif (!allowedTo(array('lock_any', 'lock_own')))
					unset($_POST['lock']);
				// A moderator-lock (1) can override a user-lock (2).
				else
					$_POST['lock'] = allowedTo('lock_any') ? 1 : 2;
			}

			if (isset($_POST['sticky']) && (empty($modSettings['enableStickyTopics']) || empty($_POST['sticky']) || !allowedTo('make_sticky')))
				unset($_POST['sticky']);

			// Saving your new topic as a draft first?
			if (!empty($modSettings['drafts_enabled']) && isset($_POST['save_draft']))
			{
				saveDraft();
				return $this->action_post();
			}

			$posterIsGuest = $user_info['is_guest'];
		}
		// Modifying an existing message?
		elseif (isset($_REQUEST['msg']) && !empty($topic))
		{
			$_REQUEST['msg'] = (int) $_REQUEST['msg'];

			require_once(SUBSDIR . '/Messages.subs.php');
			$msgInfo = basicMessageInfo($_REQUEST['msg'], true);

			if (empty($msgInfo))
				fatal_lang_error('cant_find_messages', false);

			if (!empty($topic_info['locked']) && !allowedTo('moderate_board'))
				fatal_lang_error('topic_locked', false);

			if (isset($_POST['lock']))
			{
				// Nothing changes to the lock status.
				if ((empty($_POST['lock']) && empty($topic_info['locked'])) || (!empty($_POST['lock']) && !empty($topic_info['locked'])))
					unset($_POST['lock']);
				// You're simply not allowed to (un)lock this.
				elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $topic_info['id_member_started']))
					unset($_POST['lock']);
				// You're only allowed to lock your own topics.
				elseif (!allowedTo('lock_any'))
				{
					// You're not allowed to break a moderator's lock.
					if ($topic_info['locked'] == 1)
						unset($_POST['lock']);
					// Lock it with a soft lock or unlock it.
					else
						$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
				}
				// You must be the moderator.
				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
			}

			// Change the sticky status of this topic?
			if (isset($_POST['sticky']) && (!allowedTo('make_sticky') || $_POST['sticky'] == $topic_info['is_sticky']))
				unset($_POST['sticky']);

			if ($msgInfo['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
			{
				if ((!$modSettings['postmod_active'] || $msgInfo['approved']) && !empty($modSettings['edit_disable_time']) && $msgInfo['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
				elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('modify_own'))
					isAllowedTo('modify_replies');
				else
					isAllowedTo('modify_own');
			}
			elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('modify_any'))
			{
				isAllowedTo('modify_replies');

				// If you're modifying a reply, I say it better be logged...
				$moderationAction = true;
			}
			else
			{
				isAllowedTo('modify_any');

				// Log it, assuming you're not modifying your own post.
				if ($msgInfo['id_member'] != $user_info['id'])
					$moderationAction = true;
			}

			// If drafts are enabled, then lets send this off to save
			if (!empty($modSettings['drafts_enabled']) && isset($_POST['save_draft']))
			{
				saveDraft();
				return $this->action_post();
			}

			$posterIsGuest = empty($msgInfo['id_member']);

			// Can they approve it?
			$can_approve = allowedTo('approve_posts');
			$becomesApproved = $modSettings['postmod_active'] ? ($can_approve && !$msgInfo['approved'] ? (!empty($_REQUEST['approve']) ? 1 : 0) : $msgInfo['approved']) : 1;
			$approve_has_changed = $msgInfo['approved'] != $becomesApproved;

			if (!allowedTo('moderate_forum') || !$posterIsGuest)
			{
				$_POST['guestname'] = $msgInfo['poster_name'];
				$_POST['email'] = $msgInfo['poster_email'];
			}
		}

		// In case we want to override
		if (allowedTo('approve_posts'))
		{
			$becomesApproved = !isset($_REQUEST['approve']) || !empty($_REQUEST['approve']) ? 1 : 0;
			$approve_has_changed = isset($msgInfo['approved']) ? $msgInfo['approved'] != $becomesApproved : false;
		}

		// If the poster is a guest evaluate the legality of name and email.
		if ($posterIsGuest)
		{
			$_POST['guestname'] = !isset($_POST['guestname']) ? '' : Util::htmlspecialchars(trim($_POST['guestname']));
			$_POST['email'] = !isset($_POST['email']) ? '' : Util::htmlspecialchars(trim($_POST['email']));

			if ($_POST['guestname'] == '' || $_POST['guestname'] == '_')
				$post_errors->addError('no_name');

			if (Util::strlen($_POST['guestname']) > 25)
				$post_errors->addError('long_name');

			if (empty($modSettings['guest_post_no_email']))
			{
				// Only check if they changed it!
				if (!isset($msgInfo) || $msgInfo['poster_email'] != $_POST['email'])
				{
					require_once(SUBSDIR . '/DataValidator.class.php');
					if (!allowedTo('moderate_forum') && !Data_Validator::is_valid($_POST, array('email' => 'valid_email|required'), array('email' => 'trim')))
						empty($_POST['email']) ? $post_errors->addError('no_email') : $post_errors->addError('bad_email');
				}

				// Now make sure this email address is not banned from posting.
				isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));
			}

			// In case they are making multiple posts this visit, help them along by storing their name.
			if (!$post_errors->hasErrors())
			{
				$_SESSION['guest_name'] = $_POST['guestname'];
				$_SESSION['guest_email'] = $_POST['email'];
			}
		}

		// Check the subject and message.
		if (!isset($_POST['subject']) || Util::htmltrim(Util::htmlspecialchars($_POST['subject'])) === '')
			$post_errors->addError('no_subject');

		if (!isset($_POST['message']) || Util::htmltrim(Util::htmlspecialchars($_POST['message'], ENT_QUOTES)) === '')
			$post_errors->addError('no_message');
		elseif (!empty($modSettings['max_messageLength']) && Util::strlen($_POST['message']) > $modSettings['max_messageLength'])
			$post_errors->addError(array('long_message', array($modSettings['max_messageLength'])));
		else
		{
			// Prepare the message a bit for some additional testing.
			$_POST['message'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true);

			// Preparse code. (Zef)
			if ($user_info['is_guest'])
				$user_info['name'] = $_POST['guestname'];
			preparsecode($_POST['message']);

			// Let's see if there's still some content left without the tags.
			if (Util::htmltrim(strip_tags(parse_bbc($_POST['message'], false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($_POST['message'], '[html]') === false))
				$post_errors->addError('no_message');
		}

		if (isset($_POST['calendar']) && !isset($_REQUEST['deleteevent']) && Util::htmltrim($_POST['evtitle']) === '')
			$post_errors->addError('no_event');

		// Validate the poll...
		if (isset($_REQUEST['poll']) && !empty($modSettings['pollMode']))
		{
			if (!empty($topic) && !isset($_REQUEST['msg']))
				fatal_lang_error('no_access', false);

			// This is a new topic... so it's a new poll.
			if (empty($topic))
				isAllowedTo('poll_post');
			// Can you add to your own topics?
			elseif ($user_info['id'] == $topic_info['id_member_started'] && !allowedTo('poll_add_any'))
				isAllowedTo('poll_add_own');
			// Can you add polls to any topic, then?
			else
				isAllowedTo('poll_add_any');

			if (!isset($_POST['question']) || trim($_POST['question']) == '')
				$post_errors->addError('no_question');

			$_POST['options'] = empty($_POST['options']) ? array() : htmltrim__recursive($_POST['options']);

			// Get rid of empty ones.
			foreach ($_POST['options'] as $k => $option)
			{
				if ($option == '')
					unset($_POST['options'][$k], $_POST['options'][$k]);
			}

			// What are you going to vote between with one choice?!?
			if (count($_POST['options']) < 2)
				$post_errors->addError('poll_few');
			elseif (count($_POST['options']) > 256)
				$post_errors->addError('poll_many');
		}

		if ($posterIsGuest)
		{
			// If user is a guest, make sure the chosen name isn't taken.
			require_once(SUBSDIR . '/Members.subs.php');
			if (isReservedName($_POST['guestname'], 0, true, false) && (!isset($msgInfo['poster_name']) || $_POST['guestname'] != $msgInfo['poster_name']))
				$post_errors->addError('bad_name');
		}
		// If the user isn't a guest, get his or her name and email.
		elseif (!isset($_REQUEST['msg']))
		{
			$_POST['guestname'] = $user_info['username'];
			$_POST['email'] = $user_info['email'];
		}

		// Posting somewhere else? Are we sure you can?
		if (!empty($_REQUEST['post_in_board']))
		{
			$new_board = (int) $_REQUEST['post_in_board'];
			if (!allowedTo('post_new', $new_board))
			{
				$post_in_board = boardInfo($new_board);

				if (!empty($post_in_board))
					$post_errors->addError(array('post_new_board', array($post_in_board['name'])));
				else
					$post_errors->addError('post_new');
			}
		}

		// Any mistakes?
		if ($post_errors->hasErrors() || $attach_errors->hasErrors())
		{
			addInlineJavascript('
				$(document).ready(function () {
					$("html,body").scrollTop($(\'.category_header:visible:first\').offset().top);
				});'
			);

			return $this->action_post();
		}

		// Make sure the user isn't spamming the board.
		if (!isset($_REQUEST['msg']))
			spamProtection('post');

		// At about this point, we're posting and that's that.
		ignore_user_abort(true);
		@set_time_limit(300);

		// Add special html entities to the subject, name, and email.
		$_POST['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
		$_POST['guestname'] = htmlspecialchars($_POST['guestname'], ENT_COMPAT, 'UTF-8');
		$_POST['email'] = htmlspecialchars($_POST['email'], ENT_COMPAT, 'UTF-8');

		// At this point, we want to make sure the subject isn't too long.
		if (Util::strlen($_POST['subject']) > 100)
			$_POST['subject'] = Util::substr($_POST['subject'], 0, 100);

		if (!empty($modSettings['mentions_enabled']) && !empty($_REQUEST['uid']))
		{
			$query_params = array();
			$query_params['member_ids'] = array_unique(array_map('intval', $_REQUEST['uid']));
			require_once(SUBSDIR . '/Members.subs.php');
			$mentioned_members = membersBy('member_ids', $query_params, true);
			$replacements = 0;
			$actually_mentioned = array();
			foreach ($mentioned_members as $member)
			{
				$_POST['message'] = str_replace('@' . $member['real_name'], '[member=' . $member['id_member'] . ']' . $member['real_name'] . '[/member]', $_POST['message'], $replacements);
				if ($replacements > 0)
					$actually_mentioned[] = $member['id_member'];
			}
		}

		// Make the poll...
		if (isset($_REQUEST['poll']))
		{
			// Make sure that the user has not entered a ridiculous number of options..
			if (empty($_POST['poll_max_votes']) || $_POST['poll_max_votes'] <= 0)
				$_POST['poll_max_votes'] = 1;
			elseif ($_POST['poll_max_votes'] > count($_POST['options']))
				$_POST['poll_max_votes'] = count($_POST['options']);
			else
				$_POST['poll_max_votes'] = (int) $_POST['poll_max_votes'];

			$_POST['poll_expire'] = (int) $_POST['poll_expire'];
			$_POST['poll_expire'] = $_POST['poll_expire'] > 9999 ? 9999 : ($_POST['poll_expire'] < 0 ? 0 : $_POST['poll_expire']);

			// Just set it to zero if it's not there..
			if (!isset($_POST['poll_hide']))
				$_POST['poll_hide'] = 0;
			else
				$_POST['poll_hide'] = (int) $_POST['poll_hide'];

			$_POST['poll_change_vote'] = isset($_POST['poll_change_vote']) ? 1 : 0;
			$_POST['poll_guest_vote'] = isset($_POST['poll_guest_vote']) ? 1 : 0;

			// Make sure guests are actually allowed to vote generally.
			if ($_POST['poll_guest_vote'])
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

				if (!in_array(-1, $allowedVoteGroups['allowed']))
					$_POST['poll_guest_vote'] = 0;
			}

			// If the user tries to set the poll too far in advance, don't let them.
			if (!empty($_POST['poll_expire']) && $_POST['poll_expire'] < 1)
				fatal_lang_error('poll_range_error', false);
			// Don't allow them to select option 2 for hidden results if it's not time limited.
			elseif (empty($_POST['poll_expire']) && $_POST['poll_hide'] == 2)
				$_POST['poll_hide'] = 1;

			// Clean up the question and answers.
			$_POST['question'] = htmlspecialchars($_POST['question'], ENT_COMPAT, 'UTF-8');
			$_POST['question'] = Util::substr($_POST['question'], 0, 255);
			$_POST['question'] = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $_POST['question']);
			$_POST['options'] = htmlspecialchars__recursive($_POST['options']);

			// Finally, make the poll.
			require_once(SUBSDIR . '/Poll.subs.php');
			$id_poll = createPoll(
				$_POST['question'],
				$user_info['id'],
				$_POST['guestname'],
				$_POST['poll_max_votes'],
				$_POST['poll_hide'],
				$_POST['poll_expire'],
				$_POST['poll_change_vote'],
				$_POST['poll_guest_vote'],
				$_POST['options']
			);
		}
		else
			$id_poll = 0;

		// ...or attach a new file...
		if (empty($ignore_temp) && $context['attachments']['can']['post'] && !empty($_SESSION['temp_attachments']) && empty($_POST['from_qr']))
		{
			$attachIDs = array();

			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
			{
				if ($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . $user_info['id']) === false)
					continue;

				// If there was an initial error just show that message.
				if ($attachID == 'initial_error')
				{
					unset($_SESSION['temp_attachments']);
					break;
				}

				// No errors, then try to create the attachment
				if (empty($attachment['errors']))
				{
					// Load the attachmentOptions array with the data needed to create an attachment
					$attachmentOptions = array(
						'post' => isset($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
						'poster' => $user_info['id'],
						'name' => $attachment['name'],
						'tmp_name' => $attachment['tmp_name'],
						'size' => isset($attachment['size']) ? $attachment['size'] : 0,
						'mime_type' => isset($attachment['type']) ? $attachment['type'] : '',
						'id_folder' => isset($attachment['id_folder']) ? $attachment['id_folder'] : 0,
						'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
						'errors' => array(),
					);

					if (createAttachment($attachmentOptions))
					{
						$attachIDs[] = $attachmentOptions['id'];
						if (!empty($attachmentOptions['thumb']))
							$attachIDs[] = $attachmentOptions['thumb'];
					}
				}
				// We have errors on this file, build out the issues for display to the user
				else
					@unlink($attachment['tmp_name']);
			}
			unset($_SESSION['temp_attachments']);
		}

		// Creating a new topic?
		$newTopic = empty($_REQUEST['msg']) && empty($topic);

		$_POST['icon'] = !empty($attachIDs) && $_POST['icon'] == 'xx' ? 'clip' : $_POST['icon'];

		// Collect all parameters for the creation or modification of a post.
		$msgOptions = array(
			'id' => empty($_REQUEST['msg']) ? 0 : (int) $_REQUEST['msg'],
			'subject' => $_POST['subject'],
			'body' => $_POST['message'],
			'icon' => preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']),
			'smileys_enabled' => !isset($_POST['ns']),
			'attachments' => empty($attachIDs) ? array() : $attachIDs,
			'approved' => $becomesApproved,
		);

		$topicOptions = array(
			'id' => empty($topic) ? 0 : $topic,
			'board' => $board,
			'poll' => isset($_REQUEST['poll']) ? $id_poll : null,
			'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
			'sticky_mode' => isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']) ? (int) $_POST['sticky'] : null,
			'mark_as_read' => true,
			'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
		);

		$posterOptions = array(
			'id' => $user_info['id'],
			'name' => $_POST['guestname'],
			'email' => $_POST['email'],
			'update_post_count' => !$user_info['is_guest'] && !isset($_REQUEST['msg']) && $board_info['posts_count'],
		);

		// This is an already existing message. Edit it.
		if (!empty($_REQUEST['msg']))
		{
			// Have admins allowed people to hide their screwups?
			if (time() - $msgInfo['poster_time'] > $modSettings['edit_wait_time'] || $user_info['id'] != $msgInfo['id_member'])
			{
				$msgOptions['modify_time'] = time();
				$msgOptions['modify_name'] = $user_info['name'];
			}

			// This will save some time...
			if (empty($approve_has_changed))
				unset($msgOptions['approved']);

			modifyPost($msgOptions, $topicOptions, $posterOptions);
		}
		// This is a new topic or an already existing one. Save it.
		else
		{
			if (!empty($modSettings['enableFollowup']) && !empty($_REQUEST['followup']))
				$original_post = (int) $_REQUEST['followup'];

			// We also have to fake the board:
			// if it's valid and it's not the current, let's forget about the "current" and load the new one
			if (!empty($new_board) && $board !== $new_board)
			{
				$board = $new_board;
				loadBoard();

				// Some details changed
				$topicOptions['board'] = $board;
				$topicOptions['is_approved'] = !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']);
				$posterOptions['update_post_count'] = !$user_info['is_guest'] && !isset($_REQUEST['msg']) && $board_info['posts_count'];
			}

			createPost($msgOptions, $topicOptions, $posterOptions);

			if (isset($topicOptions['id']))
				$topic = $topicOptions['id'];

			if (!empty($modSettings['enableFollowup']))
			{
				require_once(SUBSDIR . '/FollowUps.subs.php');
				require_once(SUBSDIR . '/Messages.subs.php');

				// Time to update the original message with a pointer to the new one
				if (!empty($original_post) && canAccessMessage($original_post))
					linkMessages($original_post, $topic);
			}
		}

		// If we had a draft for this, its time to remove it since it was just posted
		if (!empty($modSettings['drafts_enabled']) && !empty($_POST['id_draft']))
			deleteDrafts($_POST['id_draft'], $user_info['id']);

		// Editing or posting an event?
		if (isset($_POST['calendar']) && (!isset($_REQUEST['eventid']) || $_REQUEST['eventid'] == -1))
		{
			require_once(SUBSDIR . '/Calendar.subs.php');

			// Make sure they can link an event to this post.
			canLinkEvent();

			// Insert the event.
			$eventOptions = array(
				'id_board' => $board,
				'id_topic' => $topic,
				'title' => $_POST['evtitle'],
				'member' => $user_info['id'],
				'start_date' => sprintf('%04d-%02d-%02d', $_POST['year'], $_POST['month'], $_POST['day']),
				'span' => isset($_POST['span']) && $_POST['span'] > 0 ? min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1) : 0,
			);
			insertEvent($eventOptions);
		}
		elseif (isset($_POST['calendar']))
		{
			$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

			// Validate the post...
			require_once(SUBSDIR . '/Calendar.subs.php');
			validateEventPost();

			// If you're not allowed to edit any events, you have to be the poster.
			if (!allowedTo('calendar_edit_any'))
			{
				$event_poster = getEventPoster($_REQUEST['eventid']);

				// Silly hacker, Trix are for kids. ...probably trademarked somewhere, this is FAIR USE! (parody...)
				isAllowedTo('calendar_edit_' . ($event_poster == $user_info['id'] ? 'own' : 'any'));
			}

			// Delete it?
			if (isset($_REQUEST['deleteevent']))
				removeEvent($_REQUEST['eventid']);
			// ... or just update it?
			else
			{
				$span = !empty($modSettings['cal_allowspan']) && !empty($_REQUEST['span']) ? min((int) $modSettings['cal_maxspan'], (int) $_REQUEST['span'] - 1) : 0;
				$start_time = mktime(0, 0, 0, (int) $_REQUEST['month'], (int) $_REQUEST['day'], (int) $_REQUEST['year']);

				$eventOptions = array(
					'start_date' => strftime('%Y-%m-%d', $start_time),
					'end_date' => strftime('%Y-%m-%d', $start_time + $span * 86400),
					'title' => $_REQUEST['evtitle'],
				);
				modifyEvent($_REQUEST['eventid'], $eventOptions);
			}
		}

		// Marking boards as read.
		// (You just posted and they will be unread.)
		if (!$user_info['is_guest'])
		{
			$board_list = !empty($board_info['parent_boards']) ? array_keys($board_info['parent_boards']) : array();

			// Returning to the topic?
			if (!empty($_REQUEST['goback']))
				$board_list[] = $board;

			if (!empty($board_list))
				markBoardsRead($board_list, false, false);
		}

		// Turn notification on or off.
		if (!empty($_POST['notify']) && allowedTo('mark_any_notify'))
			setTopicNotification($user_info['id'], $topic, true);
		elseif (!$newTopic)
			setTopicNotification($user_info['id'], $topic, false);

		// Log an act of moderation - modifying.
		if (!empty($moderationAction))
			logAction('modify', array('topic' => $topic, 'message' => (int) $_REQUEST['msg'], 'member' => $msgInfo['id_member'], 'board' => $board));

		if (isset($_POST['lock']) && $_POST['lock'] != 2)
			logAction(empty($_POST['lock']) ? 'unlock' : 'lock', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

		if (isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']))
			logAction(empty($_POST['sticky']) ? 'unsticky' : 'sticky', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

		// Notify any members who have notification turned on for this topic/board - only do this if it's going to be approved(!)
		if ($becomesApproved)
		{
			require_once(SUBSDIR . '/Notification.subs.php');
			if ($newTopic)
			{
				$notifyData = array(
					'body' => $_POST['message'],
					'subject' => $_POST['subject'],
					'name' => $user_info['name'],
					'poster' => $user_info['id'],
					'msg' => $msgOptions['id'],
					'board' => $board,
					'topic' => $topic,
					'signature' => (isset($user_settings['signature']) ? $user_settings['signature'] : ''),
				);
				sendBoardNotifications($notifyData);
			}
			elseif (empty($_REQUEST['msg']))
			{
				// Only send it to everyone if the topic is approved, otherwise just to the topic starter if they want it.
				if ($topic_info['approved'])
					sendNotifications($topic, 'reply');
				else
					sendNotifications($topic, 'reply', array(), $topic_info['id_member_started']);
			}
		}

		if (!empty($modSettings['mentions_enabled']) && !empty($actually_mentioned))
		{
			require_once(CONTROLLERDIR . '/Mentions.controller.php');
			$mentions = new Mentions_Controller();
			$mentions->setData(array(
				'id_member' => $actually_mentioned,
				'type' => 'men',
				'id_msg' => $msgOptions['id'],
				'status' => $becomesApproved ? 'new' : 'unapproved',
			));
			$mentions->action_add();
		}

		if ($board_info['num_topics'] == 0)
			cache_put_data('board-' . $board, null, 120);

		if (!empty($_POST['announce_topic']))
			redirectexit('action=announce;sa=selectgroup;topic=' . $topic . (!empty($_POST['move']) && allowedTo('move_any') ? ';move' : '') . (empty($_REQUEST['goback']) ? '' : ';goback'));

		if (!empty($_POST['move']) && allowedTo('move_any'))
			redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));

		// Return to post if the mod is on.
		if (isset($_REQUEST['msg']) && !empty($_REQUEST['goback']))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg'], isBrowser('ie'));
		elseif (!empty($_REQUEST['goback']))
			redirectexit('topic=' . $topic . '.new#new', isBrowser('ie'));
		// Dut-dut-duh-duh-DUH-duh-dut-duh-duh!  *dances to the Final Fantasy Fanfare...*
		else
			redirectexit('board=' . $board . '.0');
	}

	/**
	 * Loads a post an inserts it into the current editing text box.
	 * Used to quick edit a post as well as to quote a post and place it in the quick reply box
	 * Can be used to quick edit just the subject from the topic listing
	 *
	 * uses the Post language file.
	 * uses special (sadly browser dependent) javascript to parse entities for internationalization reasons.
	 * accessed with ?action=quotefast and ?action=quotefast;modify
	 */
	public function action_quotefast()
	{
		global $modSettings, $user_info, $context;

		$db = database();

		loadLanguage('Post');

		require_once(SUBSDIR . '/Post.subs.php');

		$moderate_boards = boardsAllowedTo('moderate_board');

		// Where we going if we need to?
		$context['post_box_name'] = isset($_GET['pb']) ? $_GET['pb'] : '';

		$request = $db->query('', '
			SELECT IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body, m.id_topic, m.subject,
				m.id_board, m.id_member, m.approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg = {int:id_msg}' . (isset($_REQUEST['modify']) || (!empty($moderate_boards) && $moderate_boards[0] == 0) ? '' : '
				AND (t.locked = {int:not_locked}' . (empty($moderate_boards) ? '' : ' OR b.id_board IN ({array_int:moderation_board_list})') . ')') . '
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'moderation_board_list' => $moderate_boards,
				'id_msg' => (int) $_REQUEST['quote'],
				'not_locked' => 0,
			)
		);
		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		$context['sub_template'] = 'quotefast';
		if (!empty($row))
			$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == $user_info['id']) || allowedTo('approve_posts', $row['id_board']);

		if (!empty($can_view_post))
		{
			// Remove special formatting we don't want anymore.
			$row['body'] = un_preparsecode($row['body']);

			// Censor the message!
			censorText($row['body']);

			$row['body'] = preg_replace('~<br ?/?' . '>~i', "\n", $row['body']);

			// Want to modify a single message by double clicking it?
			if (isset($_REQUEST['modify']))
			{
				censorText($row['subject']);

				$context['sub_template'] = 'modifyfast';
				$context['message'] = array(
					'id' => $_REQUEST['quote'],
					'body' => $row['body'],
					'subject' => addcslashes($row['subject'], '"'),
				);

				return;
			}

			// Remove any nested quotes.
			if (!empty($modSettings['removeNestedQuotes']))
				$row['body'] = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $row['body']);

			// Add a quote string on the front and end.
			$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . "]\n" . $row['body'] . "\n[/quote]";
			$context['quote']['text'] = strtr(un_htmlspecialchars($context['quote']['xml']), array('\'' => '\\\'', '\\' => '\\\\', "\n" => '\\n', '</script>' => '</\' + \'script>'));
			$context['quote']['xml'] = strtr($context['quote']['xml'], array('&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;'));

			$context['quote']['mozilla'] = strtr(Util::htmlspecialchars($context['quote']['text']), array('&quot;' => '"'));
		}
		//@todo Needs a nicer interface.
		// In case our message has been removed in the meantime.
		elseif (isset($_REQUEST['modify']))
		{
			$context['sub_template'] = 'modifyfast';
			$context['message'] = array(
				'id' => 0,
				'body' => '',
				'subject' => '',
			);
		}
		else
			$context['quote'] = array(
				'xml' => '',
				'mozilla' => '',
				'text' => '',
			);
	}

	/**
	 * Used to edit the body or subject of a message inline
	 * called from action=jsmodify from script and topic js
	 */
	public function action_jsmodify()
	{
		global $modSettings, $board, $topic;
		global $user_info, $context;

		$db = database();

		// We have to have a topic!
		if (empty($topic))
			obExit(false);

		checkSession('get');
		require_once(SUBSDIR . '/Post.subs.php');

		// Assume the first message if no message ID was given.
		$request = $db->query('', '
			SELECT
				t.locked, t.num_replies, t.id_member_started, t.id_first_msg,
				m.id_msg, m.id_member, m.poster_time, m.subject, m.smileys_enabled, m.body, m.icon,
				m.modified_time, m.modified_name, m.approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			WHERE m.id_msg = {raw:id_msg}
				AND m.id_topic = {int:current_topic}' . (allowedTo('modify_any') || allowedTo('approve_posts') ? '' : (!$modSettings['postmod_active'] ? '
				AND (m.id_member != {int:guest_id} AND m.id_member = {int:current_member})' : '
				AND (m.approved = {int:is_approved} OR (m.id_member != {int:guest_id} AND m.id_member = {int:current_member}))')),
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
				'id_msg' => empty($_REQUEST['msg']) ? 't.id_first_msg' : (int) $_REQUEST['msg'],
				'is_approved' => 1,
				'guest_id' => 0,
			)
		);
		if ($db->num_rows($request) == 0)
			fatal_lang_error('no_board', false);
		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		// Change either body or subject requires permissions to modify messages.
		if (isset($_POST['message']) || isset($_POST['subject']) || isset($_REQUEST['icon']))
		{
			if (!empty($row['locked']))
				isAllowedTo('moderate_board');

			if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
			{
				if ((!$modSettings['postmod_active'] || $row['approved']) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
				elseif ($row['id_member_started'] == $user_info['id'] && !allowedTo('modify_own'))
					isAllowedTo('modify_replies');
				else
					isAllowedTo('modify_own');
			}
			// Otherwise, they're locked out; someone who can modify the replies is needed.
			elseif ($row['id_member_started'] == $user_info['id'] && !allowedTo('modify_any'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_any');

			// Only log this action if it wasn't your message.
			$moderationAction = $row['id_member'] != $user_info['id'];
		}

		$post_errors = Error_Context::context('post', 1);

		if (isset($_POST['subject']) && Util::htmltrim(Util::htmlspecialchars($_POST['subject'])) !== '')
		{
			$_POST['subject'] = strtr(Util::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));

			// Maximum number of characters.
			if (Util::strlen($_POST['subject']) > 100)
				$_POST['subject'] = Util::substr($_POST['subject'], 0, 100);
		}
		elseif (isset($_POST['subject']))
		{
			$post_errors->addError('no_subject');
			unset($_POST['subject']);
		}

		if (isset($_POST['message']))
		{
			if (Util::htmltrim(Util::htmlspecialchars($_POST['message'])) === '')
			{
				$post_errors->addError('no_message');
				unset($_POST['message']);
			}
			elseif (!empty($modSettings['max_messageLength']) && Util::strlen($_POST['message']) > $modSettings['max_messageLength'])
			{
				$post_errors->addError(array('long_message', array($modSettings['max_messageLength'])));
				unset($_POST['message']);
			}
			else
			{
				$_POST['message'] = Util::htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8', true);

				preparsecode($_POST['message']);

				if (Util::htmltrim(strip_tags(parse_bbc($_POST['message'], false), '<img>')) === '')
				{
					$post_errors->addError('no_message');
					unset($_POST['message']);
				}
			}
		}

		if (isset($_POST['lock']))
		{
			if (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $row['id_member']))
				unset($_POST['lock']);
			elseif (!allowedTo('lock_any'))
			{
				if ($row['locked'] == 1)
					unset($_POST['lock']);
				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
			}
			elseif (!empty($row['locked']) && !empty($_POST['lock']) || $_POST['lock'] == $row['locked'])
				unset($_POST['lock']);
			else
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
		}

		if (isset($_POST['sticky']) && !allowedTo('make_sticky'))
			unset($_POST['sticky']);

		if (!$post_errors->hasErrors())
		{
			if (!empty($modSettings['mentions_enabled']))
			{
				if (!empty($_REQUEST['uid']))
				{
					$query_params = array();
					$query_params['member_ids'] = array_unique(array_map('intval', $_REQUEST['uid']));
					require_once(SUBSDIR . '/Members.subs.php');
					$mentioned_members = membersBy('member_ids', $query_params, true);
					$replacements = 0;
					$actually_mentioned = array();
					foreach ($mentioned_members as $member)
					{
						$_POST['message'] = str_replace('@' . $member['real_name'], '[member=' . $member['id_member'] . ']' . $member['real_name'] . '[/member]', $_POST['message'], $replacements);
						if ($replacements > 0)
							$actually_mentioned[] = $member['id_member'];
					}
				}

				if (!empty($actually_mentioned))
				{
					require_once(CONTROLLERDIR . '/Mentions.controller.php');
					$mentions = new Mentions_Controller();
					$mentions->setData(array(
						'id_member' => $actually_mentioned,
						'type' => 'men',
						'id_msg' => $row['id_msg'],
						'status' => $row['approved'] ? 'new' : 'unapproved',
					));
					$mentions->action_add();
				}
			}

			$msgOptions = array(
				'id' => $row['id_msg'],
				'subject' => isset($_POST['subject']) ? $_POST['subject'] : null,
				'body' => isset($_POST['message']) ? $_POST['message'] : null,
				'icon' => isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : null,
			);

			$topicOptions = array(
				'id' => $topic,
				'board' => $board,
				'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
				'sticky_mode' => isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']) ? (int) $_POST['sticky'] : null,
				'mark_as_read' => false,
			);

			$posterOptions = array();

			// Only consider marking as editing if they have edited the subject, message or icon.
			if ((isset($_POST['subject']) && $_POST['subject'] != $row['subject']) || (isset($_POST['message']) && $_POST['message'] != $row['body']) || (isset($_REQUEST['icon']) && $_REQUEST['icon'] != $row['icon']))
			{
				// And even then only if the time has passed...
				if (time() - $row['poster_time'] > $modSettings['edit_wait_time'] || $user_info['id'] != $row['id_member'])
				{
					$msgOptions['modify_time'] = time();
					$msgOptions['modify_name'] = $user_info['name'];
				}
			}
			// If nothing was changed there's no need to add an entry to the moderation log.
			else
				$moderationAction = false;

			modifyPost($msgOptions, $topicOptions, $posterOptions);

			// If we didn't change anything this time but had before put back the old info.
			if (!isset($msgOptions['modify_time']) && !empty($row['modified_time']))
			{
				$msgOptions['modify_time'] = $row['modified_time'];
				$msgOptions['modify_name'] = $row['modified_name'];
			}

			// Changing the first subject updates other subjects to 'Re: new_subject'.
			if (isset($_POST['subject']) && isset($_REQUEST['change_all_subjects']) && $row['id_first_msg'] == $row['id_msg'] && !empty($row['num_replies']) && (allowedTo('modify_any') || ($row['id_member_started'] == $user_info['id'] && allowedTo('modify_replies'))))
			{
				// Get the proper (default language) response prefix first.
				$context['response_prefix'] = response_prefix();

				$db->query('', '
					UPDATE {db_prefix}messages
					SET subject = {string:subject}
					WHERE id_topic = {int:current_topic}
						AND id_msg != {int:id_first_msg}',
					array(
						'current_topic' => $topic,
						'id_first_msg' => $row['id_first_msg'],
						'subject' => $context['response_prefix'] . $_POST['subject'],
					)
				);
			}

			if (!empty($moderationAction))
				logAction('modify', array('topic' => $topic, 'message' => $row['id_msg'], 'member' => $row['id_member'], 'board' => $board));
		}

		if (isset($_REQUEST['xml']))
		{
			$context['sub_template'] = 'modifydone';
			if (!$post_errors->hasErrors() && isset($msgOptions['subject']) && isset($msgOptions['body']))
			{
				$context['message'] = array(
					'id' => $row['id_msg'],
					'modified' => array(
						'time' => isset($msgOptions['modify_time']) ? standardTime($msgOptions['modify_time']) : '',
						'html_time' => isset($msgOptions['modify_time']) ? htmlTime($msgOptions['modify_time']) : '',
						'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
						'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					),
					'subject' => $msgOptions['subject'],
					'first_in_topic' => $row['id_msg'] == $row['id_first_msg'],
					'body' => strtr($msgOptions['body'], array(']]>' => ']]]]><![CDATA[>')),
				);

				censorText($context['message']['subject']);
				censorText($context['message']['body']);

				$context['message']['body'] = parse_bbc($context['message']['body'], $row['smileys_enabled'], $row['id_msg']);
			}
			// Topic?
			elseif (!$post_errors->hasErrors())
			{
				$context['sub_template'] = 'modifytopicdone';
				$context['message'] = array(
					'id' => $row['id_msg'],
					'modified' => array(
						'time' => isset($msgOptions['modify_time']) ? standardTime($msgOptions['modify_time']) : '',
						'html_time' => isset($msgOptions['modify_time']) ? htmlTime($msgOptions['modify_time']) : '',
						'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
						'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					),
					'subject' => isset($msgOptions['subject']) ? $msgOptions['subject'] : '',
				);

				censorText($context['message']['subject']);
			}
			else
			{
				$context['message'] = array(
					'id' => $row['id_msg'],
					'errors' => array(),
					'error_in_subject' => $post_errors->hasError('no_subject'),
					'error_in_body' => $post_errors->hasError('no_message') || $post_errors->hasError('long_message'),
				);
				$context['message']['errors'] = $post_errors->prepareErrors();
			}
		}
		else
			obExit(false);
	}

	/**
	 * Spell checks the post for typos ;).
	 * It uses the pspell library, which MUST be installed.
	 * It has problems with internationalization.
	 * It is accessed via ?action=spellcheck.
	 */
	public function action_spellcheck()
	{
		global $txt, $context;

		// A list of "words" we know about but pspell doesn't.
		$known_words = array('elkarte', 'php', 'mysql', 'www', 'gif', 'jpeg', 'png', 'http');

		loadLanguage('Post');
		loadTemplate('Post');

		// Okay, this looks funny, but it actually fixes a weird bug.
		ob_start();
		$old = error_reporting(0);

		// See, first, some windows machines don't load pspell properly on the first try.  Dumb, but this is a workaround.
		pspell_new('en');

		// Next, the dictionary in question may not exist. So, we try it... but...
		$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', 'utf-8', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		// Most people don't have anything but English installed... So we use English as a last resort.
		if (!$pspell_link)
			$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		error_reporting($old);
		@ob_end_clean();

		if (!isset($_POST['spellstring']) || !$pspell_link)
			die;

		// Construct a bit of Javascript code.
		$context['spell_js'] = '
			var txt = {"done": "' . $txt['spellcheck_done'] . '"},
				mispstr = ' . ($_POST['fulleditor'] === 'true' ? 'window.opener.spellCheckGetText(spell_fieldname)' : 'window.opener.document.forms[spell_formname][spell_fieldname].value') . ',
				misps = Array(';

		// Get all the words (Javascript already separated them).
		$alphas = explode("\n", strtr($_POST['spellstring'], array("\r" => '')));

		$found_words = false;
		for ($i = 0, $n = count($alphas); $i < $n; $i++)
		{
			// Words are sent like 'word|offset_begin|offset_end'.
			$check_word = explode('|', $alphas[$i]);

			// If the word is a known word, or spelled right...
			if (in_array(Util::strtolower($check_word[0]), $known_words) || pspell_check($pspell_link, $check_word[0]) || !isset($check_word[2]))
				continue;

			// Find the word, and move up the "last occurance" to here.
			$found_words = true;

			// Add on the javascript for this misspelling.
			$context['spell_js'] .= '
				new misp("' . strtr($check_word[0], array('\\' => '\\\\', '"' => '\\"', '<' => '', '&gt;' => '')) . '", ' . (int) $check_word[1] . ', ' . (int) $check_word[2] . ', [';

			// If there are suggestions, add them in...
			$suggestions = pspell_suggest($pspell_link, $check_word[0]);
			if (!empty($suggestions))
			{
				// But first check they aren't going to be censored - no naughty words!
				foreach ($suggestions as $k => $word)
					if ($suggestions[$k] != censorText($word))
						unset($suggestions[$k]);

				if (!empty($suggestions))
					$context['spell_js'] .= '"' . implode('", "', $suggestions) . '"';
			}

			$context['spell_js'] .= ']),';
		}

		// If words were found, take off the last comma.
		if ($found_words)
			$context['spell_js'] = substr($context['spell_js'], 0, -1);

		$context['spell_js'] .= '
			);';

		// And instruct the template system to just show the spellcheck sub template.
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'spellcheck';
	}

	/**
	 * Loads in a group of post drafts for the user.
	 * Loads a specific draft for current use in the postbox if selected.
	 * Used in the posting screens to allow draft selection
	 * Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id
	 * @param int|false $id_topic if set, load drafts for the specified topic
	 * @return false|null
	 */
	private function _prepareDraftsContext($member_id, $id_topic = false)
	{
		global $scripturl, $context, $txt, $modSettings;

		$context['drafts'] = array();

		// Need a member
		if (empty($member_id))
			return false;

		// We haz drafts
		loadLanguage('Drafts');
		require_once(SUBSDIR . '/Drafts.subs.php');

		// has a specific draft has been selected?  Load it up if there is not already a message already in the editor
		if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
			loadDraft((int) $_REQUEST['id_draft'], 0, true, true);

		// load all the drafts for this user that meet the criteria
		$order = 'poster_time DESC';
		$user_drafts = load_user_drafts($member_id, 0, $id_topic, $order);

		// Add them to the context draft array for template display
		foreach ($user_drafts as $draft)
		{
			$short_subject = empty($draft['subject']) ? $txt['drafts_none'] : Util::shorten_text(stripslashes($draft['subject']), !empty($modSettings['draft_subject_length']) ? $modSettings['draft_subject_length'] : 24);
			$context['drafts'][] = array(
				'subject' => censorText($short_subject),
				'poster_time' => standardTime($draft['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=post;board=' . $draft['id_board'] . ';' . (!empty($draft['id_topic']) ? 'topic='. $draft['id_topic'] .'.0;' : '') . 'id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
			);
		}
	}
}
