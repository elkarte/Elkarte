<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
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
 * This file contains functions for dealing with messages. 
 * Low-level functions, i.e. database operations needed to perform.
 * These functions (probably) do NOT make permissions checks. (they assume
 * those were already made).
 *
 */

if (!defined('ELKARTE'))
	die('Hacking attempt...');

function getExistingMessage($id_msg, $id_topic = 0, $attachment_type = 0)
{
	global $smcFunc;

	if (empty($id_msg))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_member, m.modified_time, m.modified_name, m.smileys_enabled, m.body,
			m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
			IFNULL(a.size, -1) AS filesize, a.filename, a.id_attach,
			a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
			m.poster_time, log.id_action
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
			LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}',
		array(
			'current_topic' => $id_topic,
			'attachment_type' => $attachment_type,
			'id_msg' => $id_msg,
			'announce_action' => 'announce_topic',
		)
	);
	// The message they were trying to edit was most likely deleted.
	if ($smcFunc['db_num_rows']($request) == 0)
		return false;
	$row = $smcFunc['db_fetch_assoc']($request);

	$attachment_stuff = array($row);
	while ($row2 = $smcFunc['db_fetch_assoc']($request))
		$attachment_stuff[] = $row2;
	$smcFunc['db_free_result']($request);

	$temp = array();
	foreach ($attachment_stuff as $attachment)
	{
		if ($attachment['filesize'] >= 0 && !empty($modSettings['attachmentEnable']))
			$temp[$attachment['id_attach']] = $attachment;

	}
	ksort($temp);

	return array('message' => $row, 'attachment_stuff' => $temp);
}

function checkMessagePermissions($message)
{
	global $user_info, $modSettings, $context;

	if ($message['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
	{
		// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
		if ($message['approved'] && !empty($modSettings['edit_disable_time']) && $message['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
			fatal_lang_error('modify_post_time_passed', false);
		elseif ($message['id_member_poster'] == $user_info['id'] && !allowedTo('modify_own'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_own');
	}
	elseif ($message['id_member_poster'] == $user_info['id'] && !allowedTo('modify_any'))
		isAllowedTo('modify_replies');
	else
		isAllowedTo('modify_any');

	if ($context['can_announce'] && !empty($message['id_action']))
	{
		loadLanguage('Errors');
		$context['post_error']['messages'][] = $txt['error_topic_already_announced'];
	}
}

function prepareMessageContext($message)
{
	global $context;

	// Load up 'em attachments!
	foreach ($message['attachment_stuff'] as $attachment)
	{
		$context['current_attachments'][] = array(
			'name' => htmlspecialchars($attachment['filename']),
			'size' => $attachment['filesize'],
			'id' => $attachment['id_attach'],
			'approved' => $attachment['attachment_approved'],
		);
	}

	// Allow moderators to change names....
	if (allowedTo('moderate_forum') && empty($message['message']['id_member']))
	{
		$context['name'] = htmlspecialchars($message['message']['poster_name']);
		$context['email'] = htmlspecialchars($message['message']['poster_email']);
	}
}