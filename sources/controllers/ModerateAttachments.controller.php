<?php

/**
 * All of the moderation actions for attachments, basically approve them
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * ModerateAttachments_Controller class
 */
class ModerateAttachments_Controller extends Action_Controller
{
	/**
	 * Forward to attachments approval method is the only responsibility
	 * of this controller.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Forward to our method(s) to do the job
		$this->action_attachapprove();
	}

	/**
	 * Approve an attachment
	 *
	 * - Called from a mouse click,
	 * - works out what we want to do with attachments and actions it.
	 * - Accessed by ?action=attachapprove
	 */
	public function action_attachapprove()
	{
		global $user_info;

		// Security is our primary concern...
		checkSession('get');

		// Is it approve or delete?
		$is_approve = !isset($this->_req->query->sa) || $this->_req->query->sa !== 'reject' ? true : false;

		$attachments = array();
		require_once(SUBSDIR . '/ManageAttachments.subs.php');

		// If we are approving all ID's in a message, get the ID's.
		if ($this->_req->query->sa === 'all' && !empty($this->_req->query->mid))
		{
			$id_msg = (int) $this->_req->query->mid;
			$attachments = attachmentsOfMessage($id_msg);
		}
		elseif (!empty($this->_req->query->aid))
			$attachments[] = (int) $this->_req->query->aid;

		if (empty($attachments))
			throw new Elk_Exception('no_access', false);

		// @todo nb: this requires permission to approve posts, not manage attachments
		// Now we have some ID's cleaned and ready to approve, but first - let's check we have permission!
		$allowed_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		if ($allowed_boards == array(0))
		{
			$approve_query = '';
		}
		elseif (!empty($allowed_boards))
		{
			$approve_query = ' AND m.id_board IN (' . implode(',', $allowed_boards) . ')';
		}
		else
		{
			$approve_query = ' AND 0';
		}

		// Validate the attachments exist and have the right approval state.
		$attachments = validateAttachments($attachments, $approve_query);

		// Set up a return link based off one of the attachments for this message
		$attach_home = attachmentBelongsTo($attachments[0]);
		$redirect = 'topic=' . $attach_home['id_topic'] . '.msg' . $attach_home['id_msg'] . '#msg' . $attach_home['id_msg'];

		if (empty($attachments))
			throw new Elk_Exception('no_access', false);

		// Finally, we are there. Follow through!
		if ($is_approve)
		{
			// Checked and deemed worthy.
			approveAttachments($attachments);
		}
		else
			removeAttachments(array('id_attach' => $attachments, 'do_logging' => true));

		// We approved or removed, either way we reset those numbers
		Cache::instance()->remove('num_menu_errors');

		// Return to the topic....
		redirectexit($redirect);
	}
}