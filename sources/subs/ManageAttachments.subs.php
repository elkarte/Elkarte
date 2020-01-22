<?php

/**
 * This file handles the uploading and creation of attachments
 * as well as the auto management of the attachment directories.
 * Note to enhance documentation later:
 * attachment_type = 3 is a thumbnail, etc.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use BBC\ParserWrapper;
use ElkArte\Util;

/**
 * Approve an attachment, or maybe even more - no permission check!
 *
 * @param int[] $attachments
 *
 * @return int
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function approveAttachments($attachments)
{
	$db = database();

	if (empty($attachments))
	{
		return 0;
	}

	// For safety, check for thumbnails...
	$attachments = array();
	$db->fetchQuery('
		SELECT
			a.id_attach, a.id_member, COALESCE(thumb.id_attach, 0) AS id_thumb
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$attachments) {
			// Update the thumbnail too...
			if (!empty($row['id_thumb']))
			{
				$attachments[] = $row['id_thumb'];
			}

			$attachments[] = $row['id_attach'];
		}
	);

	if (empty($attachments))
	{
		return 0;
	}

	// Approving an attachment is not hard - it's easy.
	$db->query('', '
		UPDATE {db_prefix}attachments
		SET 
			approved = {int:is_approved}
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
			'is_approved' => 1,
		)
	);

	// In order to log the attachments, we really need their message and filename
	$db->fetchQuery('
		SELECT 
			m.id_msg, a.filename
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
		)
	)->fetch_callback(
		function ($row) {
			logAction(
				'approve_attach',
				array(
					'message' => $row['id_msg'],
					'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Util::htmlspecialchars($row['filename'])),
				)
			);
		}
	);

	// Remove from the approval queue.
	$db->query('', '
		DELETE FROM {db_prefix}approval_queue
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
		)
	);

	call_integration_hook('integrate_approve_attachments', array($attachments));
}

/**
 * Removes attachments or avatars based on a given query condition.
 *
 * - Called by remove avatar/attachment functions.
 * - It removes attachments based that match the $condition.
 * - It allows query_types 'messages' and 'members', whichever is need by the
 * $condition parameter.
 * - It does no permissions check.
 *
 * @param mixed[] $condition
 * @param string $query_type
 * @param bool $return_affected_messages = false
 * @param bool $autoThumbRemoval = true
 * @return int[]|bool returns affected messages if $return_affected_messages is set to true
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function removeAttachments($condition, $query_type = '', $return_affected_messages = false, $autoThumbRemoval = true)
{
	global $modSettings;

	$db = database();

	// @todo This might need more work!
	$new_condition = array();
	$query_parameter = array(
		'thumb_attachment_type' => 3,
	);
	$do_logging = array();

	if (is_array($condition))
	{
		foreach ($condition as $real_type => $restriction)
		{
			// Doing a NOT?
			$is_not = substr($real_type, 0, 4) === 'not_';
			$type = $is_not ? substr($real_type, 4) : $real_type;

			switch ($type)
			{
				case 'id_member':
				case 'id_attach':
				case 'id_msg':
					// @todo the !empty($restriction) is a trick to override the checks on $_POST['attach_del'] in Post.controller
					// In theory it should not be necessary
					if (!empty($restriction))
					{
						$new_condition[] = 'a.' . $type . ($is_not ? ' NOT' : '') . ' IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';
					}
					break;
				case 'attachment_type':
					$new_condition[] = 'a.attachment_type = {int:' . $real_type . '}';
					break;
				case 'poster_time':
					$new_condition[] = 'm.poster_time < {int:' . $real_type . '}';
					break;
				case 'last_login':
					$new_condition[] = 'mem.last_login < {int:' . $real_type . '}';
					break;
				case 'size':
					$new_condition[] = 'a.size > {int:' . $real_type . '}';
					break;
				case 'id_topic':
					$new_condition[] = 'm.id_topic IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';
					break;
				case 'do_logging':
					$do_logging = $condition['id_attach'];
					break;
			}

			// Add the parameter!
			$query_parameter[$real_type] = $restriction;
		}

		if (empty($new_condition))
		{
			return false;
		}

		$condition = implode(' AND ', $new_condition);
	}

	// Delete it only if it exists...
	$msgs = array();
	$attach = array();
	$parents = array();

	require_once(SUBSDIR . '/Attachments.subs.php');

	// Get all the attachment names and id_msg's.
	$db->fetchQuery('
		SELECT
			a.id_folder, a.filename, a.file_hash, a.attachment_type, a.id_attach, a.id_member' . ($query_type == 'messages' ? ', m.id_msg' : ', a.id_msg') . ',
			thumb.id_folder AS thumb_folder, COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.filename AS thumb_filename, thumb.file_hash AS thumb_file_hash, thumb_parent.id_attach AS id_parent
		FROM {db_prefix}attachments AS a' . ($query_type == 'members' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)' : ($query_type == 'messages' ? '
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)' : '')) . '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
			LEFT JOIN {db_prefix}attachments AS thumb_parent ON (thumb.attachment_type = {int:thumb_attachment_type} AND thumb_parent.id_thumb = a.id_attach)
		WHERE ' . $condition,
		$query_parameter
	)->fetch_callback(
		function ($row) use (&$attach, &$parents, &$msgs, $modSettings, $return_affected_messages, $autoThumbRemoval) {
			// Figure out the "encrypted" filename and unlink it ;).
			if ($row['attachment_type'] == 1)
			{
				// if attachment_type = 1, it's... an avatar in a custom avatar directory.
				// wasn't it obvious? :P
				// @todo look again at this.
				@unlink($modSettings['custom_avatar_dir'] . '/' . $row['filename']);
			}
			else
			{
				$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
				@unlink($filename);

				// If this was a thumb, the parent attachment should know about it.
				if (!empty($row['id_parent']))
				{
					$parents[] = $row['id_parent'];
				}

				// If this attachments has a thumb, remove it as well.
				if (!empty($row['id_thumb']) && $autoThumbRemoval)
				{
					$thumb_filename = getAttachmentFilename($row['thumb_filename'], $row['id_thumb'], $row['thumb_folder'], false, $row['thumb_file_hash']);
					@unlink($thumb_filename);
					$attach[] = $row['id_thumb'];
				}
			}

			// Make a list.
			if ($return_affected_messages && empty($row['attachment_type']))
			{
				$msgs[] = $row['id_msg'];
			}

			$attach[] = $row['id_attach'];
		}
	);

	// Removed attachments don't have to be updated anymore.
	$parents = array_diff($parents, $attach);
	if (!empty($parents))
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET 
				id_thumb = {int:no_thumb}
			WHERE id_attach IN ({array_int:parent_attachments})',
			array(
				'parent_attachments' => $parents,
				'no_thumb' => 0,
			)
		);
	}

	if (!empty($do_logging))
	{
		// In order to log the attachments, we really need their message and filename
		$db->fetchQuery('
			SELECT 
				m.id_msg, a.filename
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			WHERE a.id_attach IN ({array_int:attachments})
				AND a.attachment_type = {int:attachment_type}',
			array(
				'attachments' => $do_logging,
				'attachment_type' => 0,
			)
		)->fetch_callback(
			function ($row) {
				logAction(
					'remove_attach',
					array(
						'message' => $row['id_msg'],
						'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Util::htmlspecialchars($row['filename'])),
					)
				);
			}
		);
	}

	if (!empty($attach))
	{
		$db->query('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $attach,
			)
		);
	}

	call_integration_hook('integrate_remove_attachments', array($attach));

	if ($return_affected_messages)
	{
		return array_unique($msgs);
	}
	else
	{
		return true;
	}
}

/**
 * How many attachments we have overall.
 *
 * @return int
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function getAttachmentCount()
{
	$db = database();

	// Get the number of attachments....
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member = {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);
	list ($num_attachments) = $request->fetch_row();
	$request->free_result();

	return (int) $num_attachments;
}

/**
 * How many attachments we have in a certain folder.
 *
 * @param string $folder
 *
 * @return int
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function getFolderAttachmentCount($folder)
{
	$db = database();

	// Get the number of attachments....
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_folder = {int:folder_id}
			AND attachment_type != {int:attachment_type}',
		array(
			'folder_id' => $folder,
			'attachment_type' => 1,
		)
	);
	list ($num_attachments) = $request->fetch_row();
	$request->free_result();

	return (int) $num_attachments;
}

/**
 * How many avatars do we have. Need to know. :P
 *
 * @return int
 * @package Attachments
 */
function getAvatarCount()
{
	$db = database();

	// Get the avatar amount....
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_member != {int:guest_id_member}',
		array(
			'guest_id_member' => 0,
		)
	);
	list ($num_avatars) = $request->fetch_row();
	$request->free_result();

	return (int) $num_avatars;
}

/**
 * Simple function to remove the strictly needed of orphan attachments.
 *
 * - This is used from attachments maintenance.
 * - It assumes the files have no message, no member information.
 * - It only removes the attachments and thumbnails from the database.
 *
 * @param int[] $attach_ids
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function removeOrphanAttachments($attach_ids)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}attachments
		WHERE id_attach IN ({array_int:to_remove})',
		array(
			'to_remove' => $attach_ids,
		)
	);

	$db->query('', '
		UPDATE {db_prefix}attachments
			SET 
				id_thumb = {int:no_thumb}
			WHERE id_thumb IN ({array_int:to_remove})',
		array(
			'to_remove' => $attach_ids,
			'no_thumb' => 0,
		)
	);
}

/**
 * Set or retrieve the size of an attachment.
 *
 * @param int $attach_id
 * @param int|null $filesize = null
 *
 * @return bool|int|null
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function attachment_filesize($attach_id, $filesize = null)
{
	$db = database();

	if ($filesize === null)
	{
		$result = $db->query('', '
			SELECT 
				size
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attach_id,
			)
		);
		if ($result->hasResults())
		{
			list ($filesize) = $result->fetch_row();
			$result->free_result();

			return (int) $filesize;
		}

		return false;
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET 
				size = {int:filesize}
			WHERE id_attach = {int:id_attach}',
			array(
				'filesize' => $filesize,
				'id_attach' => $attach_id,
			)
		);
	}
}

/**
 * Set or retrieve the ID of the folder where an attachment is stored on disk.
 *
 * @param int $attach_id
 * @param int|null $folder_id = null
 *
 * @return bool|int|null
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function attachment_folder($attach_id, $folder_id = null)
{
	$db = database();

	if ($folder_id === null)
	{
		$result = $db->query('', '
			SELECT 
				id_folder
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attach_id,
			)
		);
		if ($result->hasResults())
		{
			list ($folder_id) = $result->fetch_row();
			$result->free_result();

			return (int) $folder_id;
		}

		return false;
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET 
				id_folder = {int:new_folder}
			WHERE id_attach = {int:id_attach}',
			array(
				'new_folder' => $folder_id,
				'id_attach' => $attach_id,
			)
		);
	}
}

/**
 * Get the last attachment ID without a thumbnail.
 *
 * @package Attachments
 */
function maxNoThumb()
{
	$db = database();

	$result = $db->query('', '
		SELECT 
			MAX(id_attach)
		FROM {db_prefix}attachments
		WHERE id_thumb != {int:no_thumb}',
		array(
			'no_thumb' => 0,
		)
	);
	list ($thumbnails) = $result->fetch_row();
	$result->free_result();

	return (int) $thumbnails;
}

/**
 * Finds orphan thumbnails in the database
 *
 * - Checks in groups of 500
 * - Called by attachment maintenance
 * - If $fix_errors is set to true it will attempt to remove the thumbnail from disk
 *
 * @param int $start
 * @param bool $fix_errors
 * @param string[] $to_fix
 *
 * @return array
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function findOrphanThumbnails($start, $fix_errors, $to_fix)
{
	$db = database();

	require_once(SUBSDIR . '/Attachments.subs.php');

	$result = $db->query('', '
		SELECT 
			thumb.id_attach, thumb.id_folder, thumb.filename, thumb.file_hash
		FROM {db_prefix}attachments AS thumb
			LEFT JOIN {db_prefix}attachments AS tparent ON (tparent.id_thumb = thumb.id_attach)
		WHERE thumb.id_attach BETWEEN {int:substep} AND {int:substep} + 499
			AND thumb.attachment_type = {int:thumbnail}
			AND tparent.id_attach IS NULL',
		array(
			'thumbnail' => 3,
			'substep' => $start,
		)
	);
	$to_remove = array();
	if ($result->num_rows() != 0)
	{
		$to_fix[] = 'missing_thumbnail_parent';
		while (($row = $result->fetch_assoc($result)))
		{
			// Only do anything once... just in case
			if (!isset($to_remove[$row['id_attach']]))
			{
				$to_remove[$row['id_attach']] = $row['id_attach'];

				// If we are repairing remove the file from disk now.
				if ($fix_errors && in_array('missing_thumbnail_parent', $to_fix))
				{
					$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
					@unlink($filename);
				}
			}
		}
	}
	$result->free_result();

	// Do we need to delete what we have?
	if ($fix_errors && !empty($to_remove) && in_array('missing_thumbnail_parent', $to_fix))
	{
		$db->query('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:to_remove})
				AND attachment_type = {int:attachment_type}',
			array(
				'to_remove' => $to_remove,
				'attachment_type' => 3,
			)
		);
	}

	return $to_remove;
}

/**
 * Finds parents who thing they do have thumbnails, but don't
 *
 * - Checks in groups of 500
 * - Called by attachment maintenance
 * - If $fix_errors is set to true it will attempt to remove the thumbnail from disk
 *
 * @param int $start
 * @param bool $fix_errors
 * @param string[] $to_fix
 *
 * @return array
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function findParentsOrphanThumbnails($start, $fix_errors, $to_fix)
{
	$db = database();

	$to_update = $db->fetchQuery('
		SELECT 
			a.id_attach
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
		WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
			AND a.id_thumb != {int:no_thumb}
			AND thumb.id_attach IS NULL',
		array(
			'no_thumb' => 0,
			'substep' => $start,
		)
	)->fetch_callback(
		function ($row) {
			return $row['id_attach'];
		}
	);

	// Do we need to delete what we have?
	if ($fix_errors && !empty($to_update) && in_array('parent_missing_thumbnail', $to_fix))
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET 
				id_thumb = {int:no_thumb}
			WHERE id_attach IN ({array_int:to_update})',
			array(
				'to_update' => $to_update,
				'no_thumb' => 0,
			)
		);
	}

	return $to_update;
}

/**
 * Goes thought all the attachments and checks that they exist
 *
 * - Goes in increments of 250
 * - if $fix_errors is true will remove empty files, update wrong filesizes in the DB and
 * - remove DB entries if the file can not be found.
 *
 * @param int $start
 * @param bool $fix_errors
 * @param string[] $to_fix
 *
 * @return array
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function repairAttachmentData($start, $fix_errors, $to_fix)
{
	global $modSettings;

	$db = database();
	$attachmentDirectory = new AttachmentsDirectory($modSettings, $db);

	require_once(SUBSDIR . '/Attachments.subs.php');

	$repair_errors = array(
		'wrong_folder' => 0,
		'missing_extension' => 0,
		'file_missing_on_disk' => 0,
		'file_size_of_zero' => 0,
		'file_wrong_size' => 0
	);

	$request = $db->query('', '
		SELECT 
			id_attach, id_folder, filename, file_hash, size, attachment_type
		FROM {db_prefix}attachments
		WHERE id_attach BETWEEN {int:substep} AND {int:substep} + 249',
		array(
			'substep' => $start,
		)
	);
	$to_remove = array();
	while (($row = $request->fetch_assoc()))
	{
		// Get the filename.
		if ($row['attachment_type'] == 1)
		{
			$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
		}
		else
		{
			$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
		}

		// File doesn't exist?
		if (!file_exists($filename))
		{
			// If we're lucky it might just be in a different folder.
			if ($attachmentDirectory->hasMultiPaths())
			{
				// Get the attachment name without the folder.
				$attachment_name = !empty($row['file_hash']) ? $row['id_attach'] . '_' . $row['file_hash'] . '.elk' : getLegacyAttachmentFilename($row['filename'], $row['id_attach'], null, true);

				// Loop through the other folders looking for this file
				$attachmentDirs = $attachmentDirectory->getPaths();
				foreach ($attachmentDirs as $id => $dir)
				{
					if (file_exists($dir . '/' . $attachment_name))
					{
						$repair_errors['wrong_folder']++;

						// Are we going to fix this now?
						if ($fix_errors && in_array('wrong_folder', $to_fix))
						{
							attachment_folder($row['id_attach'], $id);
						}

						// Found it, on to the next attachment
						continue 2;
					}
				}

				if (!empty($row['file_hash']))
				{
					// It may be without the elk extension (something wrong during upgrade/conversion)
					$attachment_name = $row['id_attach'] . '_' . $row['file_hash'];

					// Loop through the other folders looking for this file
					foreach ($attachmentDirectory->getPaths() as $id => $dir)
					{
						if (file_exists($dir . '/' . $attachment_name))
						{
							$repair_errors['missing_extension']++;

							// Are we going to fix this now?
							if ($fix_errors && in_array('missing_extension', $to_fix))
							{
								rename($dir . '/' . $attachment_name, $dir . '/' . $attachment_name . '.elk');
								attachment_folder($row['id_attach'], $id);
							}

							// Found it, on to the next attachment
							continue 2;
						}
					}
				}
			}

			// Could not find it anywhere
			$to_remove[] = $row['id_attach'];
			$repair_errors['file_missing_on_disk']++;
		}
		// An empty file on disk?
		elseif (filesize($filename) == 0)
		{
			$repair_errors['file_size_of_zero']++;

			// Fixing?
			if ($fix_errors && in_array('file_size_of_zero', $to_fix))
			{
				$to_remove[] = $row['id_attach'];
				@unlink($filename);
			}
		}
		// Size listed and actual size are not the same?
		elseif (filesize($filename) != $row['size'])
		{
			$repair_errors['file_wrong_size']++;

			// Fix it here?
			if ($fix_errors && in_array('file_wrong_size', $to_fix))
			{
				attachment_filesize($row['id_attach'], filesize($filename));
			}
		}
	}
	$request->free_result();

	// Do we need to delete what we have?
	if ($fix_errors && !empty($to_remove) && in_array('file_missing_on_disk', $to_fix))
	{
		removeOrphanAttachments($to_remove);
	}

	return $repair_errors;
}

/**
 * Finds avatar files that are not assigned to any members
 *
 * - If $fix_errors is set, it will
 *
 * @param int $start
 * @param bool $fix_errors
 * @param string[] $to_fix
 *
 * @return array
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function findOrphanAvatars($start, $fix_errors, $to_fix)
{
	global $modSettings;

	$db = database();

	require_once(SUBSDIR . '/Attachments.subs.php');

	$to_remove = $db->fetchQuery('
		SELECT 
			a.id_attach, a.id_folder, a.filename, a.file_hash, a.attachment_type
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
		WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
			AND a.id_member != {int:no_member}
			AND a.id_msg = {int:no_msg}
			AND mem.id_member IS NULL',
		array(
			'no_member' => 0,
			'no_msg' => 0,
			'substep' => $start,
		)
	)->fetch_callback(
		function ($row) use ($fix_errors, $to_fix, $modSettings) {
			// If we are repairing remove the file from disk now.
			if ($fix_errors && in_array('avatar_no_member', $to_fix))
			{
				if ($row['attachment_type'] == 1)
				{
					$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
				}
				else
				{
					$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
				}
				@unlink($filename);
			}

			return $row['id_attach'];
		}
	);

	// Do we need to delete what we have?
	if ($fix_errors && !empty($to_remove) && in_array('avatar_no_member', $to_fix))
	{
		$db->query('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:to_remove})
				AND id_member != {int:no_member}
				AND id_msg = {int:no_msg}',
			array(
				'to_remove' => $to_remove,
				'no_member' => 0,
				'no_msg' => 0,
			)
		);
	}

	return $to_remove;
}

/**
 * Finds attachments that are not used in any message
 *
 * @param int $start
 * @param bool $fix_errors
 * @param string[] $to_fix
 *
 * @return array
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function findOrphanAttachments($start, $fix_errors, $to_fix)
{
	$db = database();

	require_once(SUBSDIR . '/Attachments.subs.php');

	$to_remove = $db->fetchQuery('
		SELECT 
			a.id_attach, a.id_folder, a.filename, a.file_hash
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
		WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
			AND a.id_member = {int:no_member}
			AND a.id_msg != {int:no_msg}
			AND m.id_msg IS NULL',
		array(
			'no_member' => 0,
			'no_msg' => 0,
			'substep' => $start,
		)
	)->fetch_callback(
		function ($row) use ($fix_errors, $to_fix) {
			// If we are repairing remove the file from disk now.
			if ($fix_errors && in_array('attachment_no_msg', $to_fix))
			{
				$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
				@unlink($filename);
			}

			return $row['id_attach'];
		}
	);

	// Do we need to delete what we have?
	if ($fix_errors && !empty($to_remove) && in_array('attachment_no_msg', $to_fix))
	{
		$db->query('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:to_remove})
				AND id_member = {int:no_member}
				AND id_msg != {int:no_msg}',
			array(
				'to_remove' => $to_remove,
				'no_member' => 0,
				'no_msg' => 0,
			)
		);
	}

	return $to_remove;
}

/**
 * Get the max attachment ID which is a thumbnail.
 *
 * @package Attachments
 */
function getMaxThumbnail()
{
	$db = database();

	$result = $db->query('', '
		SELECT 
			MAX(id_attach)
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:thumbnail}',
		array(
			'thumbnail' => 3,
		)
	);
	list ($thumbnail) = $result->fetch_row();
	$result->free_result();

	return $thumbnail;
}

/**
 * Get the max attachment ID.
 *
 * @package Attachments
 */
function maxAttachment()
{
	$db = database();

	$result = $db->query('', '
		SELECT 
			MAX(id_attach)
		FROM {db_prefix}attachments',
		array()
	);
	list ($attachment) = $result->fetch_row();
	$result->free_result();

	return $attachment;
}

/**
 * Check multiple attachments IDs against the database.
 *
 * @param int[] $attachments
 * @param string $approve_query
 *
 * @return array
 * @package Attachments
 * @throws \Exception
 */
function validateAttachments($attachments, $approve_query)
{
	$db = database();

	// double check the attachments array, pick only what is returned from the database
	return $db->fetchQuery('
		SELECT 
			a.id_attach, m.id_board, m.id_msg, m.id_topic
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.approved = {int:not_approved}
			AND a.attachment_type = {int:attachment_type}
			AND {query_see_board}
			' . $approve_query,
		array(
			'attachments' => $attachments,
			'not_approved' => 0,
			'attachment_type' => 0,
		)
	)->fetch_callback(
		function ($row) {
			return $row['id_attach'];
		}
	);
}

/**
 * Finds an attachments parent topic/message and returns the values in an array
 *
 * @param int $attachment
 *
 * @return int
 * @package Attachments
 * @throws \Exception
 */
function attachmentBelongsTo($attachment)
{
	$db = database();

	$attachment = $db->fetchQuery('
		SELECT 
			a.id_attach, m.id_board, m.id_msg, m.id_topic
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
		WHERE a.id_attach = ({int:attachment})
			AND a.attachment_type = {int:attachment_type}
			AND {query_see_board}
		LIMIT 1',
		array(
			'attachment' => $attachment,
			'attachment_type' => 0,
		)
	)->fetch_all();

	return $attachment[0] ?? $attachment;
}

/**
 * Checks an attachments id
 *
 * @param int $id_attach
 * @return bool
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function validateAttachID($id_attach)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			id_attach
		FROM {db_prefix}attachments
		WHERE id_attach = {int:attachment_id}
		LIMIT 1',
		array(
			'attachment_id' => $id_attach,
		)
	);
	$count = $request->num_rows();
	$request->free_result();

	return $count != 0;
}

/**
 * Callback function for action_unapproved_attachments
 *
 * - retrieve all the attachments waiting for approval the approver can approve
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $approve_query additional restrictions based on the boards the approver can see
 * @return mixed[] an array of unapproved attachments
 * @package Attachments
 * @throws \Exception
 */
function list_getUnapprovedAttachments($start, $items_per_page, $sort, $approve_query)
{
	global $scripturl;

	$db = database();

	$bbc_parser = ParserWrapper::instance();

	// Get all unapproved attachments.
	return $db->fetchQuery('
		SELECT 
			a.id_attach, a.filename, a.size, m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			t.id_member_started, t.id_first_msg, b.name AS board_name, c.id_cat, c.name AS cat_name
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE a.approved = {int:not_approved}
			AND a.attachment_type = {int:attachment_type}
			AND {query_see_board}
			{raw:approve_query}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'not_approved' => 0,
			'attachment_type' => 0,
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
			'approve_query' => $approve_query,
		)
	)->fetch_callback(
		function ($row) use ($scripturl, $bbc_parser) {
			return array(
				'id' => $row['id_attach'],
				'filename' => $row['filename'],
				'size' => round($row['size'] / 1024, 2),
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'poster' => array(
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				),
				'message' => array(
					'id' => $row['id_msg'],
					'subject' => $row['subject'],
					'body' => $bbc_parser->parseMessage($row['body'], false),
					'time' => standardTime($row['poster_time']),
					'html_time' => htmlTime($row['poster_time']),
					'timestamp' => forum_time(true, $row['poster_time']),
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				),
				'topic' => array(
					'id' => $row['id_topic'],
				),
				'board' => array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
				),
				'category' => array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
				),
			);
		}
	);
}

/**
 * Callback function for action_unapproved_attachments
 *
 * - count all the attachments waiting for approval that this approver can approve
 *
 * @param string $approve_query additional restrictions based on the boards the approver can see
 * @return int the number of unapproved attachments
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function list_getNumUnapprovedAttachments($approve_query)
{
	$db = database();

	// How many unapproved attachments in total?
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE a.approved = {int:not_approved}
			AND a.attachment_type = {int:attachment_type}
			AND {query_see_board}
			' . $approve_query,
		array(
			'not_approved' => 0,
			'attachment_type' => 0,
		)
	);
	list ($total_unapproved_attachments) = $request->fetch_row();
	$request->free_result();

	return $total_unapproved_attachments;
}

/**
 * Prepare the actual attachment directories to be displayed in the list.
 *
 * - Callback function for createList().
 *
 * @package Attachments
 */
function list_getAttachDirs()
{
	global $modSettings, $context, $txt, $scripturl;

	$db = database();
	$attachmentsDir = new AttachmentsDirectory($modSettings, $db);

	$expected_files = array();
	$expected_size = array();
	$db->fetchQuery('
		SELECT 
			id_folder, COUNT(id_attach) AS num_attach, SUM(size) AS size_attach
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}
		GROUP BY id_folder',
		array(
			'type' => 1,
		)
	)->fetch_callback(
		function ($row) use (&$expected_files, &$expected_size) {
			$expected_files[$row['id_folder']] = $row['num_attach'];
			$expected_size[$row['id_folder']] = $row['size_attach'];
		}
	);

	$attachdirs = array();
	foreach ($attachmentDirectory->getPaths() as $id => $dir)
	{
		// If there aren't any attachments in this directory this won't exist.
		if (!isset($expected_files[$id]))
		{
			$expected_files[$id] = 0;
		}

		// Check if the directory is doing okay.
		list ($status, $error, $files) = attachDirStatus($dir, $expected_files[$id]);

		// If it is one, let's show that it's a base directory.
		$sub_dirs = 0;
		$is_base_dir = false;
		if ($attachmentDirectory->hasBaseDir())
		{
			$is_base_dir = $attachmentDirectory->hasBaseDir($dir);

			// Count any sub-folders.
			$sub_dirs = $attachmentDirectory->countSubdirs($dir);
			$expected_files[$id] += $sub_dirs;
		}

		$attachdirs[] = array(
			'id' => $id,
			'current' => $attachmentsDir->isCurrentDirectoryId($id),
			'disable_current' => $attachmentsDir->autoManageEnabled(AttachmentsDirectory::AUTO_SEQUENCE),
			'disable_base_dir' => $is_base_dir && $sub_dirs > 0 && !empty($files) && empty($error),
			'path' => $dir,
			'current_size' => !empty($expected_size[$id]) ? comma_format($expected_size[$id] / 1024, 0) : 0,
			'num_files' => comma_format($expected_files[$id] - $sub_dirs, 0) . ($sub_dirs > 0 ? ' (' . $sub_dirs . ')' : ''),
			'status' => ($is_base_dir ? $txt['attach_dir_basedir'] . '<br />' : '') . ($error ? '<div class="error">' : '') . str_replace('{repair_url}', $scripturl . '?action=admin;area=manageattachments;sa=repair;' . $context['session_var'] . '=' . $context['session_id'], $txt['attach_dir_' . $status]) . ($error ? '</div>' : ''),
		);
	}

	// Just stick a new directory on at the bottom.
	if (isset($_REQUEST['new_path']))
	{
		$attachdirs[] = array(
			'id' => max(array_merge(array_keys($expected_files), array_keys($attachmentDirectory->getPaths()))) + 1,
			'current' => false,
			'path' => '',
			'current_size' => '',
			'num_files' => '',
			'status' => '',
		);
	}

	return $attachdirs;
}

/**
 * Checks the status of an attachment directory and returns an array
 * of the status key, if that status key signifies an error, and the file count.
 *
 * @param string $dir
 * @param int $expected_files
 *
 * @return array
 * @package Attachments
 *
 */
function attachDirStatus($dir, $expected_files)
{
	if (!is_dir($dir))
	{
		return array('does_not_exist', true, '');
	}
	elseif (!is_writable($dir))
	{
		return array('not_writable', true, '');
	}

	// Count the files with a glob, easier and less time consuming
	$glob = new GlobIterator($dir . '/*.elk', FilesystemIterator::SKIP_DOTS);
	try
	{
		$num_files = $glob->count();
	}
	catch (LogicException $e)
	{
		$num_files = count(iterator_to_array($glob));
	}

	if ($num_files < $expected_files)
	{
		return array('files_missing', true, $num_files);
	}
	// Empty?
	elseif ($expected_files == 0)
	{
		return array('unused', false, $num_files);
	}

	// All good!
	return array('ok', false, $num_files);
}

/**
 * Prepare the base directories to be displayed in a list.
 *
 * - Callback function for createList().
 *
 * @package Attachments
 */
function list_getBaseDirs()
{
	global $modSettings, $txt;

	$attachmentsDir = new AttachmentsDirectory($modSettings, database());

	if ($attachmentsDir->hasBaseDir() === false)
	{
		return;
	}

	// Get a list of the base directories.
	$basedirs = array();
	foreach ($attachmentsDir->getBaseDirs() as $id => $dir)
	{
		$expected_dirs = $attachmentsDir->countSubdirs($dir);

		if (!is_dir($dir))
		{
			$status = 'does_not_exist';
		}
		elseif (!is_writeable($dir))
		{
			$status = 'not_writable';
		}
		else
		{
			$status = 'ok';
		}

		$basedirs[] = array(
			'id' => $id,
			'current' => $attachmentsDir->isCurrentBaseDir($dir),
			'path' => $expected_dirs > 0 ? $dir : ('<input type="text" name="base_dir[' . $id . ']" value="' . $dir . '" size="40" />'),
			'num_dirs' => $expected_dirs,
			'status' => $status == 'ok' ? $txt['attach_dir_ok'] : ('<span class="error">' . $txt['attach_dir_' . $status] . '</span>'),
		);
	}

	if (isset($_REQUEST['new_base_path']))
	{
		$basedirs[] = array(
			'id' => '',
			'current' => false,
			'path' => '<input type="text" name="new_base_dir" value="" size="40" />',
			'num_dirs' => '',
			'status' => '',
		);
	}

	return $basedirs;
}

/**
 * Return the number of files of the specified type recorded in the database.
 *
 * - (the specified type being attachments or avatars).
 * - Callback function for createList()
 *
 * @param string $browse_type can be one of 'avatars' or not. (in which case they're attachments)
 *
 * @return int
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function list_getNumFiles($browse_type)
{
	$db = database();

	// Depending on the type of file, different queries are used.
	if ($browse_type === 'avatars')
	{
		$request = $db->query('', '
			SELECT 
				COUNT(*)
			FROM {db_prefix}attachments
			WHERE id_member != {int:guest_id_member}',
			array(
				'guest_id_member' => 0,
			)
		);
	}
	else
	{
		$request = $db->query('', '
			SELECT 
				COUNT(*) AS num_attach
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			WHERE a.attachment_type = {int:attachment_type}
				AND a.id_member = {int:guest_id_member}',
			array(
				'attachment_type' => $browse_type === 'thumbs' ? '3' : '0',
				'guest_id_member' => 0,
			)
		);
	}

	list ($num_files) = $request->fetch_row();
	$request->free_result();

	return (int) $num_files;
}

/**
 * Returns the list of attachments files (avatars or not), recorded
 * in the database, per the parameters received.
 *
 * - Callback function for createList()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $browse_type can be on eof 'avatars' or ... not. :P
 *
 * @return array
 * @package Attachments
 * @throws \Exception
 */
function list_getFiles($start, $items_per_page, $sort, $browse_type)
{
	global $txt;

	$db = database();

	// Choose a query depending on what we are viewing.
	if ($browse_type === 'avatars')
	{
		return $db->fetchQuery('
			SELECT
				{string:blank_text} AS id_msg, COALESCE(mem.real_name, {string:not_applicable_text}) AS poster_name,
				mem.last_login AS poster_time, 0 AS id_topic, a.id_member, a.id_attach, a.filename, a.file_hash, a.attachment_type,
				a.size, a.width, a.height, a.downloads, {string:blank_text} AS subject, 0 AS id_board
			FROM {db_prefix}attachments AS a
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
			WHERE a.id_member != {int:guest_id}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:per_page}',
			array(
				'guest_id' => 0,
				'blank_text' => '',
				'not_applicable_text' => $txt['not_applicable'],
				'sort' => $sort,
				'start' => $start,
				'per_page' => $items_per_page,
			)
		)->fetch_all();
	}
	else
	{
		return $db->fetchQuery('
			SELECT
				m.id_msg, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.id_topic, m.id_member,
				a.id_attach, a.filename, a.file_hash, a.attachment_type, a.size, a.width, a.height, a.downloads, mf.subject, t.id_board
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE a.attachment_type = {int:attachment_type}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:per_page}',
			array(
				'attachment_type' => $browse_type === 'thumbs' ? '3' : '0',
				'sort' => $sort,
				'start' => $start,
				'per_page' => $items_per_page,
			)
		)->fetch_all();
	}
}

/**
 * Return the overall attachments size
 *
 * @package Attachments
 */
function overallAttachmentsSize()
{
	$db = database();

	// Check the size of all the directories.
	$request = $db->query('', '
		SELECT 
			SUM(size)
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}',
		array(
			'type' => 1,
		)
	);
	list ($attachmentDirSize) = $request->fetch_row();
	$request->free_result();

	return byte_format($attachmentDirSize);
}

/**
 * Get files and size from the current attachments dir
 *
 * @package Attachments
 */
function currentAttachDirProperties()
{
	global $modSettings;

	$attachmentsDir = new AttachmentsDirectory($modSettings, database());

	return attachDirProperties($attachmentsDir->currentDirectoryId());
}

/**
 * Get files and size from the current attachments dir
 *
 * @param string $dir
 *
 * @return array
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function attachDirProperties($dir)
{
	$db = database();

	$current_dir = array();

	$request = $db->query('', '
		SELECT 
			COUNT(*), SUM(size)
		FROM {db_prefix}attachments
		WHERE id_folder = {int:folder_id}
			AND attachment_type != {int:type}',
		array(
			'folder_id' => $dir,
			'type' => 1,
		)
	);
	list ($current_dir['files'], $current_dir['size']) = $request->fetch_row();
	$request->free_result();

	return $current_dir;
}

/**
 * Move avatars to their new directory.
 *
 * @package Attachments
 */
function moveAvatars()
{
	global $modSettings;

	$db = database();

	require_once(SUBSDIR . '/Attachments.subs.php');

	$updatedAvatars = array();
	$db->fetchQuery('
		SELECT 
			id_attach, id_folder, id_member, filename, file_hash
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member > {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$updatedAvatars, $modSettings) {
			$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

			if (rename($filename, $modSettings['custom_avatar_dir'] . '/' . $row['filename']))
			{
				$updatedAvatars[] = $row['id_attach'];
			}
		}
	);

	if (!empty($updatedAvatars))
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET 
				attachment_type = {int:attachment_type}
			WHERE id_attach IN ({array_int:updated_avatars})',
			array(
				'updated_avatars' => $updatedAvatars,
				'attachment_type' => 1,
			)
		);
	}
}

/**
 * Select a group of attachments to move to a new destination
 *
 * Used by maintenance transfer attachments
 * Returns number found and array of details
 *
 * @param string $from source location
 * @param int $start
 * @param int $limit
 *
 * @return array
 * @throws \Exception
 */
function findAttachmentsToMove($from, $start, $limit)
{
	$db = database();

	// Find some attachments to move
	$attachments = $db->fetchQuery('
		SELECT 
			id_attach, filename, id_folder, file_hash, size
		FROM {db_prefix}attachments
		WHERE id_folder = {int:folder}
			AND attachment_type != {int:attachment_type}
		LIMIT {int:start}, {int:limit}',
		array(
			'folder' => $from,
			'attachment_type' => 1,
			'start' => $start,
			'limit' => $limit,
		)
	)->fetch_all();
	$number = count($attachments);

	return array($number, $attachments);
}

/**
 * Update the database to reflect the new directory of an array of attachments
 *
 * @param int[] $moved integer array of attachment ids
 * @param string $new_dir new directory string
 * @throws \ElkArte\Exceptions\Exception
 */
function moveAttachments($moved, $new_dir)
{
	$db = database();

	// Update the database
	$db->query('', '
		UPDATE {db_prefix}attachments
		SET 
			id_folder = {int:new}
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $moved,
			'new' => $new_dir,
		)
	);
}

/**
 * Extend the message body with a removal message.
 *
 * @param int[] $messages array of message id's to update
 * @param string $notice notice to add
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function setRemovalNotice($messages, $notice)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}messages
		SET 
			body = CONCAT(body, {string:notice})
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $messages,
			'notice' => '<br /><br />' . $notice,
		)
	);
}

/**
 * Finds all the attachments of a single message.
 *
 * @param int $id_msg
 * @param bool $unapproved if true returns also the unapproved attachments (default false)
 * @return array
 * @todo $unapproved may be superfluous
 * @package Attachments
 * @throws \Exception
 */
function attachmentsOfMessage($id_msg, $unapproved = false)
{
	$db = database();

	return $db->fetchQuery('
		SELECT 
			id_attach
		FROM {db_prefix}attachments
		WHERE id_msg = {int:id_msg}' . ($unapproved ? '' : '
			AND approved = {int:is_approved}') . '
			AND attachment_type = {int:attachment_type}',
		array(
			'id_msg' => $id_msg,
			'is_approved' => 0,
			'attachment_type' => 0,
		)
	)->fetch_callback(
		function ($row) {
			return $row['id_attach'];
		}
	);
}

/**
 * Counts attachments for the given folder.
 *
 * @param int $id_folder
 *
 * @return
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function countAttachmentsInFolders($id_folder)
{
	$db = database();

	// Let's not try to delete a path with files in it.
	$request = $db->query('', '
		SELECT 
			COUNT(id_attach) AS num_attach
		FROM {db_prefix}attachments
		WHERE id_folder = {int:id_folder}',
		array(
			'id_folder' => $id_folder,
		)
	);
	list ($num_attach) = $request->fetch_row();
	$request->free_result();

	return $num_attach;
}

/**
 * Changes the folder id of all the attachments in a certain folder
 *
 * @param int $from - the folder the attachments are in
 * @param int $to - the folder the attachments should be moved to
 * @package Attachments
 * @throws \ElkArte\Exceptions\Exception
 */
function updateAttachmentIdFolder($from, $to)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}attachments
		SET 
			id_folder = {int:folder_to}
		WHERE id_folder = {int:folder_from}',
		array(
			'folder_from' => $from,
			'folder_to' => $to,
		)
	);
}
