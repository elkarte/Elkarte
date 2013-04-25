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
 * This file handles various maintanance tasks for attachments
 */


if (!defined('ELKARTE'))
	die('No access...');

/**
 * Prepare the actual attachment directories to be displayed in the list.
 * Callback function for createList().
 */
function list_getAttachDirs()
{
	global $smcFunc, $modSettings, $context, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT id_folder, COUNT(id_attach) AS num_attach, SUM(size) AS size_attach
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}
		GROUP BY id_folder',
		array(
			'type' => 1,
		)
	);

	$expected_files = array();
	$expected_size = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$expected_files[$row['id_folder']] = $row['num_attach'];
		$expected_size[$row['id_folder']] = $row['size_attach'];
	}
	$smcFunc['db_free_result']($request);

	$attachdirs = array();
	foreach ($modSettings['attachmentUploadDir'] as $id => $dir)
	{
		// If there aren't any attachments in this directory this won't exist.
		if (!isset($expected_files[$id]))
			$expected_files[$id] = 0;

		// Check if the directory is doing okay.
		list ($status, $error, $files) = attachDirStatus($dir, $expected_files[$id]);

		// If it is one, let's show that it's a base directory.
		$sub_dirs = 0;
		$is_base_dir = false;
		if (!empty($modSettings['attachment_basedirectories']))
		{
			$is_base_dir = in_array($dir, $modSettings['attachment_basedirectories']);

			// Count any sub-folders.
			foreach ($modSettings['attachmentUploadDir'] as $sid => $sub)
				if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false)
				{
					$expected_files[$id]++;
					$sub_dirs++;
				}
		}

		$attachdirs[] = array(
			'id' => $id,
			'current' => $id == $modSettings['currentAttachmentUploadDir'],
			'disable_current' => isset($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] > 1,
			'disable_base_dir' =>  $is_base_dir && $sub_dirs > 0 && !empty($files) && empty($error) && empty($save_errors),
			'path' => $dir,
			'current_size' => !empty($expected_size[$id]) ? comma_format($expected_size[$id] / 1024, 0) : 0,
			'num_files' => comma_format($expected_files[$id] - $sub_dirs, 0) . ($sub_dirs > 0 ? ' (' . $sub_dirs . ')' : ''),
			'status' => ($is_base_dir ? $txt['attach_dir_basedir'] . '<br />' : '') . ($error ? '<div class="error">' : '') . sprintf($txt['attach_dir_' . $status], $context['session_id'], $context['session_var']) . ($error ? '</div>' : ''),
		);
	}

	// Just stick a new directory on at the bottom.
	if (isset($_REQUEST['new_path']))
		$attachdirs[] = array(
			'id' => max(array_merge(array_keys($expected_files), array_keys($modSettings['attachmentUploadDir']))) + 1,
			'current' => false,
			'path' => '',
			'current_size' => '',
			'num_files' => '',
			'status' => '',
		);

	return $attachdirs;
}

/**
 * Checks the status of an attachment directory and returns an array
 *  of the status key, if that status key signifies an error, and
 *  the file count.
 *
 * @param string $dir
 * @param int $expected_files
 */
function attachDirStatus($dir, $expected_files)
{
	if (!is_dir($dir))
		return array('does_not_exist', true, '');
	elseif (!is_writable($dir))
		return array('not_writable', true, '');

	// Everything is okay so far, start to scan through the directory.
	$num_files = 0;
	$dir_handle = dir($dir);
	while ($file = $dir_handle->read())
	{
		// Now do we have a real file here?
		if (in_array($file, array('.', '..', '.htaccess', 'index.php')))
			continue;

		$num_files++;
	}
	$dir_handle->close();

	if ($num_files < $expected_files)
		return array('files_missing', true, $num_files);
	// Empty?
	elseif ($expected_files == 0)
		return array('unused', false, $num_files);
	// All good!
	else
		return array('ok', false, $num_files);
}

/**
 * Prepare the base directories to be displayed in a list.
 * Callback function for createList().
 */
function list_getBaseDirs()
{
	global $modSettings, $txt;

	if (empty($modSettings['attachment_basedirectories']))
		return;

	$basedirs = array();
	// Get a list of the base directories.
	foreach ($modSettings['attachment_basedirectories'] as $id => $dir)
	{
		// Loop through the attach directory array to count any sub-directories
		$expected_dirs = 0;
		foreach ($modSettings['attachmentUploadDir'] as $sid => $sub)
			if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false)
				$expected_dirs++;

		if (!is_dir($dir))
			$status = 'does_not_exist';
		elseif (!is_writeable($dir))
			$status = 'not_writable';
		else
			$status = 'ok';

		$basedirs[] = array(
			'id' => $id,
			'current' => $dir == $modSettings['basedirectory_for_attachments'],
			'path' => $expected_dirs > 0 ? $dir : ('<input type="text" name="base_dir[' . $id . ']" value="' . $dir . '" size="40" />'),
			'num_dirs' => $expected_dirs,
			'status' => $status == 'ok' ? $txt['attach_dir_ok'] : ('<span class="error">' . $txt['attach_dir_' . $status] . '</span>'),
		);
	}

	if (isset($_REQUEST['new_base_path']))
		$basedirs[] = array(
			'id' => '',
			'current' => false,
			'path' => '<input type="text" name="new_base_dir" value="" size="40" />',
			'num_dirs' => '',
			'status' => '',
		);

	return $basedirs;
}

/**
 * Return the number of files of the specified type recorded in the database.
 * (the specified type being attachments or avatars).
 * Callback function for createList()
 *
 * @param string $browse_type can be one of 'avatars' or not. (in which case they're attachments)
 */
function list_getNumFiles($browse_type)
{
	global $smcFunc;

	// Depending on the type of file, different queries are used.
	if ($browse_type === 'avatars')
		$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_member != {int:guest_id_member}',
		array(
			'guest_id_member' => 0,
		)
	);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS num_attach
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

	list ($num_files) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_files;
}

/**
 * Returns the list of attachments files (avatars or not), recorded
 * in the database, per the parameters received.
 * Callback function for createList()
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $browse_type can be on eof 'avatars' or ... not. :P
 */
function list_getFiles($start, $items_per_page, $sort, $browse_type)
{
	global $smcFunc, $txt;

	// Choose a query depending on what we are viewing.
	if ($browse_type === 'avatars')
		$request = $smcFunc['db_query']('', '
			SELECT
				{string:blank_text} AS id_msg, IFNULL(mem.real_name, {string:not_applicable_text}) AS poster_name,
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
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT
				m.id_msg, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.id_topic, m.id_member,
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
				'attachment_type' => $browse_type == 'thumbs' ? '3' : '0',
				'sort' => $sort,
				'start' => $start,
				'per_page' => $items_per_page,
			)
		);
	$files = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$files[] = $row;
	$smcFunc['db_free_result']($request);

	return $files;
}

/**
 * Return the overall attachments size
 *
 * @return string
 */
function overallAttachmentsSize()
{
	global $smcFunc;

	// Check the size of all the directories.
	$request = $smcFunc['db_query']('', '
		SELECT SUM(size)
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}',
		array(
			'type' => 1,
		)
	);
	list ($attachmentDirSize) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Divide it into kilobytes.
	$attachmentDirSize /= 1024;
	return comma_format($attachmentDirSize, 2);
}

/**
 * Get files and size from the current attachments dir
 *
 * @return int
 */
function currentAttachDirProperties()
{
	global $smcFunc, $modSettings;

	$current_dir = array();

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*), SUM(size)
		FROM {db_prefix}attachments
		WHERE id_folder = {int:folder_id}
			AND attachment_type != {int:type}',
		array(
			'folder_id' => $modSettings['currentAttachmentUploadDir'],
			'type' => 1,
		)
	);
	list ($current_dir['files'], $current_dir['size']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$current_dir['size'] /= 1024;

	return $current_dir;
}

/**
 * Move avatars to their new directory.
 */
function moveAvatars()
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT id_attach, id_folder, id_member, filename, file_hash
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member > {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);
	$updatedAvatars = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

		if (rename($filename, $modSettings['custom_avatar_dir'] . '/' . $row['filename']))
			$updatedAvatars[] = $row['id_attach'];
	}
	$smcFunc['db_free_result']($request);

	if (!empty($updatedAvatars))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET attachment_type = {int:attachment_type}
			WHERE id_attach IN ({array_int:updated_avatars})',
			array(
				'updated_avatars' => $updatedAvatars,
				'attachment_type' => 1,
			)
		);
}

/**
 * Extend the message body with a removal message.
 *
 * @param string $messages messages to update
 * @param string $notice notice to add
 */
function setRemovalNotice($messages, $notice)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET body = CONCAT(body, {string:notice})
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $messages,
			'notice' => '<br /><br />' . $notice,
		)
	);
}