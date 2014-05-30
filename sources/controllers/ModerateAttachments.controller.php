<?php

/**
 * All of the moderation actions for attachments, basically approve them
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
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Moderate Attachments Controller
 */
class ModerateAttachments_Controller extends Action_Controller
{
	/**
	 * Forward to attachments approval method, the only responsibility
	 * of this controller.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// forward to our method(s) to do the job
		$this->action_attachapprove();
	}

	/**
	 * Called from a mouse click,
	 * works out what we want to do with attachments and actions it.
	 * Accessed by ?action=attachapprove
	 */
	public function action_attachapprove()
	{
		global $user_info;

		// Security is our primary concern...
		checkSession('get');

		// If it approve or delete?
		$is_approve = !isset($_GET['sa']) || $_GET['sa'] != 'reject' ? true : false;

		$attachments = array();
		require_once(SUBSDIR . '/ManageAttachments.subs.php');

		// If we are approving all ID's in a message , get the ID's.
		if ($_GET['sa'] == 'all' && !empty($_GET['mid']))
		{
			$id_msg = (int) $_GET['mid'];
			$attachments = attachmentsOfMessage($id_msg);
		}
		elseif (!empty($_GET['aid']))
			$attachments[] = (int) $_GET['aid'];

		if (empty($attachments))
			fatal_lang_error('no_access', false);

		// @todo nb: this requires permission to approve posts, not manage attachments
		// Now we have some ID's cleaned and ready to approve, but first - let's check we have permission!
		$allowed_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		if ($allowed_boards == array(0))
			$approve_query = '';
		elseif (!empty($allowed_boards))
			$approve_query = ' AND m.id_board IN (' . implode(',', $allowed_boards) . ')';
		else
			$approve_query = ' AND 0';

		// Validate the attachments exist and have the right approval state.
		$attachments = validateAttachments($attachments, $approve_query);

		// Set up a return link based off one of the attachments for this message
		$attach_home = attachmentBelongsTo($attachments[0]);
		$redirect = 'topic=' . $attach_home['id_topic'] . '.msg' . $attach_home['id_msg'] . '#msg' . $attach_home['id_msg'];

		if (empty($attachments))
			fatal_lang_error('no_access', false);

		// Finally, we are there. Follow through!
		if ($is_approve)
		{
			// Checked and deemed worthy.
			approveAttachments($attachments);
		}
		else
			removeAttachments(array('id_attach' => $attachments, 'do_logging' => true));

		// We approved or removed, either way we reset those numbers
		cache_put_data('num_menu_errors', null, 900);

		// Return to the topic....
		redirectexit($redirect);
	}
}