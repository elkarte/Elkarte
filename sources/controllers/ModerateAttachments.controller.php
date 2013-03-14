<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Called from a mouse click,
 * works out what we want to do with attachments and actions it.
 * Accessed by ?action=attachapprove
 */
function action_attachapprove()
{
	global $smcFunc, $user_info;

	// Security is our primary concern...
	checkSession('get');

	// If it approve or delete?
	$is_approve = !isset($_GET['sa']) || $_GET['sa'] != 'reject' ? true : false;

	$attachments = array();
	// If we are approving all ID's in a message , get the ID's.
	if ($_GET['sa'] == 'all' && !empty($_GET['mid']))
	{
		$id_msg = (int) $_GET['mid'];

		$request = $smcFunc['db_query']('', '
			SELECT id_attach
			FROM {db_prefix}attachments
			WHERE id_msg = {int:id_msg}
				AND approved = {int:is_approved}
				AND attachment_type = {int:attachment_type}',
			array(
				'id_msg' => $id_msg,
				'is_approved' => 0,
				'attachment_type' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$attachments[] = $row['id_attach'];
		$smcFunc['db_free_result']($request);
	}
	elseif (!empty($_GET['aid']))
		$attachments[] = (int) $_GET['aid'];

	if (empty($attachments))
		fatal_lang_error('no_access', false);

	// @todo nb: this requires permission to approve posts, not manage attachments
	// Now we have some ID's cleaned and ready to approve, but first - let's check we have permission!
	$allowed_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

	// Validate the attachments exist and are the right approval state.
	$request = $smcFunc['db_query']('', '
		SELECT a.id_attach, m.id_board, m.id_msg, m.id_topic
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}
			AND a.approved = {int:is_approved}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
			'is_approved' => 0,
		)
	);
	$attachments = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We can only add it if we can approve in this board!
		if ($allowed_boards == array(0) || in_array($row['id_board'], $allowed_boards))
		{
			$attachments[] = $row['id_attach'];

			// Also come up with the redirection URL.
			$redirect = 'topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (empty($attachments))
		fatal_lang_error('no_access', false);

	// Finally, we are there. Follow through!
	require_once(SUBSDIR . '/Attachments.subs.php');
	if ($is_approve)
	{
		// Checked and deemed worthy.
		approveAttachments($attachments);
	}
	else
		removeAttachments(array('id_attach' => $attachments, 'do_logging' => true));

	// Return to the topic....
	redirectexit($redirect);
}