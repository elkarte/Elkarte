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

use ElkArte\Attachments\AttachmentsDirectory;
use ElkArte\Cache\Cache;
use ElkArte\Errors\AttachmentErrorContext;
use ElkArte\Errors\Errors;
use ElkArte\Graphics\Image;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\TokenHash;
use ElkArte\Http\FsockFetchWebdata;
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;

/**
 * Handles the actual saving of attachments to a directory.
 *
 * What it does:
 *
 * - Loops through $_FILES['attachment'] array and saves each file to the current attachments' folder.
 * - Validates the save location actually exists.
 *
 * @param int $id_msg 0 or id of the message with attachments, if any.
 *                    If 0, this is an upload in progress for a new post.
 * @return bool
 */
function processAttachments($id_msg = 0)
{
	global $context, $modSettings, $txt, $topic, $board;

	$attach_errors = AttachmentErrorContext::context();
	$file_functions = FileFunctions::instance();
	$tmp_attachments = new TemporaryAttachmentsList();
	$attachmentDirectory = new AttachmentsDirectory($modSettings, database());

	// Validate we need to do processing, nothing new, nothing previously sent
	if ($tmp_attachments->getPostParam('files') === null
		&& !$tmp_attachments->hasAttachments()
		&& !$attachmentDirectory->hasFileTmpAttachments())
	{
		return false;
	}

	// Make sure we're uploading to the right place.
	$attachmentDirectory->automanageCheckDirectory(isset($_REQUEST['action']) && $_REQUEST['action'] === 'admin');
	$attach_current_dir = $attachmentDirectory->getCurrent();
	if (!$file_functions->isDir($attach_current_dir))
	{
		$tmp_attachments->setSystemError('attach_folder_warning');
		Errors::instance()->log_error(sprintf($txt['attach_folder_admin_warning'], $attach_current_dir), 'critical');
	}

	if ($tmp_attachments->hasSystemError() === false && !isset($context['attachments']['quantity']))
	{
		$context['attachments']['quantity'] = 0;
		$context['attachments']['total_size'] = 0;

		// If this isn't a new post, check the current attachments.
		if ($id_msg !== 0)
		{
			list ($context['attachments']['quantity'], $context['attachments']['total_size']) = attachmentsSizeForMessage($id_msg);
		}
	}

	// There are files in session (temporary attachments list), likely already processed
	$ignore_temp = false;
	if ($tmp_attachments->getPostParam('files') !== null && $tmp_attachments->hasAttachments())
	{
		// Let's try to keep them. But...
		$ignore_temp = true;

		// If new files are being added. We can't ignore those
		// If the array is not empty
		if (!empty($_FILES['attachment']['tmp_name']) && count(array_filter($_FILES['attachment']['tmp_name'])) !== 0)
		{
			$ignore_temp = false;
		}

		// Need to make space for the new files. So, bye bye.
		if (!$ignore_temp)
		{
			$tmp_attachments->removeAll(User::$info->id);
			$tmp_attachments->unset();

			$attach_errors->activate()->addError('temp_attachments_flushed');
		}
	}

	if (!isset($_FILES['attachment']['name']))
	{
		$_FILES['attachment']['tmp_name'] = [];
	}

	// Remember where we are at. If it's anywhere at all.
	if (!$ignore_temp)
	{
		$tmp_attachments->setPostParam([
			'msg' => $id_msg,
			'last_msg' => (int) ($_REQUEST['last_msg'] ?? 0),
			'topic' => (int) ($topic ?? 0),
			'board' => (int) ($board ?? 0),
		]);
	}

	// If we have an initial error, lets just display it.
	if ($tmp_attachments->hasSystemError())
	{
		// This is a generic error
		$attach_errors->activate();
		$attach_errors->addError('attach_no_upload');

		// And delete the files 'cos they ain't going nowhere.
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
		{
			if (is_writable($_FILES['attachment']['tmp_name'][$n]))
			{
				unlink($_FILES['attachment']['tmp_name'][$n]);
			}
		}

		$_FILES['attachment']['tmp_name'] = array();
	}

	// Loop through $_FILES['attachment'] array and move each file to the current attachments' folder.
	foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
	{
		if ($_FILES['attachment']['name'][$n] === '')
		{
			continue;
		}

		// First, let's check for PHP upload errors.
		$errors = attachmentUploadChecks($n);

		$tokenizer = new TokenHash();
		$temp_file = new TemporaryAttachment([
			'name' => basename($_FILES['attachment']['name'][$n]),
			'tmp_name' => $_FILES['attachment']['tmp_name'][$n],
			'attachid' => $tmp_attachments->getTplName(User::$info->id, $tokenizer->generate_hash(16)),
			'public_attachid' => $tmp_attachments->getTplName(User::$info->id, $tokenizer->generate_hash(16)),
			'user_id' => User::$info->id,
			'size' => $_FILES['attachment']['size'][$n],
			'type' => $_FILES['attachment']['type'][$n],
			'id_folder' => $attachmentDirectory->currentDirectoryId(),
			'mime' => getMimeType($_FILES['attachment']['tmp_name'][$n]),
		]);

		// If we are error free, Try to move and rename the file before doing more checks on it.
		if (empty($errors))
		{
			$temp_file->moveUploaded($attach_current_dir);
		}
		// Upload error(s) were detected, flag the error, remove the file
		else
		{
			$temp_file->setErrors($errors);
			$temp_file->remove(false);
		}

		// The file made it to the server, so do more checks injection, size, extension
		$temp_file->doChecks($attachmentDirectory);

		// Sort out the errors for display and delete any associated files.
		if ($temp_file->hasErrors())
		{
			$attach_errors->addAttach($temp_file['attachid'], $temp_file->getName());
			$log_these = ['attachments_no_create', 'attachments_no_write', 'attach_timeout',
						  'ran_out_of_space', 'cant_access_upload_path', 'attach_0_byte_file', 'bad_attachment'];

			foreach ($temp_file->getErrors() as $error)
			{
				$error = array_filter($error);
				$attach_errors->addError(isset($error[1]) ? $error : $error[0]);
				if (in_array($error[0], $log_these))
				{
					Errors::instance()->log_error($temp_file->getName() . ': ' . $txt[$error[0]], 'critical');

					// For critical errors, we don't want the file or session data to persist
					$temp_file->remove(false);
				}
			}
		}

		// Want to correct for phone rotated photos, hell yeah ya do!
		if (!empty($modSettings['attachment_autorotate']))
		{
			$temp_file->autoRotate();
		}

		$tmp_attachments->addAttachment($temp_file);
	}

	// Mod authors, finally a hook to hang an alternate attachment upload system upon
	// Upload to the current attachment folder with the file name $attachID or 'post_tmp_' . User::$info->id . '_' . md5(mt_rand())
	// Populate TemporaryAttachmentsList[$attachID] with the following:
	//   name => The file name
	//   tmp_name => Path to the temp file (AttachmentsDirectory->getCurrent() . '/' . $attachID).
	//   size => File size (required).
	//   type => MIME type (optional if not available on upload).
	//   id_folder => AttachmentsDirectory->currentDirectoryId
	//   errors => An array of errors (use the index of the $txt variable for that error).
	// Template changes can be done using "integrate_upload_template".
	call_integration_hook('integrate_attachment_upload');

	return $ignore_temp;
}

/**
 * Checks if an uploaded file produced any appropriate error code
 *
 * What it does:
 *
 * - Checks for error codes in the error segment of the file array that is
 * created by PHP during the file upload.
 *
 * @param int $attachID
 *
 * @return array
 */
function attachmentUploadChecks($attachID)
{
	global $modSettings, $txt;

	$errors = array();

	// Did PHP create any errors during the upload processing of this file?
	if (!empty($_FILES['attachment']['error'][$attachID]))
	{
		switch ($_FILES['attachment']['error'][$attachID])
		{
			case 1:
			case 2:
				// 1 The file exceeds the max_filesize directive in php.ini
				// 2 The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.
				$errors[] = array('file_too_big', array($modSettings['attachmentSizeLimit']));
				break;
			case 3:
			case 4:
			case 8:
				// 3 partially uploaded
				// 4 no file uploaded
				// 8 upload blocked by extension
				Errors::instance()->log_error($_FILES['attachment']['name'][$attachID] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$attachID]]);
				$errors[] = 'attach_php_error';
				break;
			case 6:
			case 7:
				// 6 Missing or a full a temp directory on the server
				// 7 Failed to write file
				Errors::instance()->log_error($_FILES['attachment']['name'][$attachID] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$attachID]], 'critical');
				$errors[] = 'attach_php_error';
				break;
			default:
				Errors::instance()->log_error($_FILES['attachment']['name'][$attachID] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$attachID]]);
				$errors[] = 'attach_php_error';
		}
	}

	return $errors;
}

/**
 * Create an attachment, with the given array of parameters.
 *
 * What it does:
 *
 * - Adds any additional or missing parameters to $attachmentOptions.
 * - Renames the temporary file.
 * - Creates a thumbnail if the file is an image and the option enabled.
 *
 * @param mixed[] $attachmentOptions associative array of options
 *
 * @return bool
 */
function createAttachment(&$attachmentOptions)
{
	global $modSettings;

	$db = database();
	$attachmentsDir = new AttachmentsDirectory($modSettings, $db);

	$image = new Image($attachmentOptions['tmp_name']);

	// If this is an image we need to set a few additional parameters.
	$is_image = $image->isImageLoaded();
	$size = $is_image ? $image->getImageDimensions() : [0, 0, 0];
	list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;
	$attachmentOptions['width'] = max(0, $attachmentOptions['width']);
	$attachmentOptions['height'] = max(0, $attachmentOptions['height']);

	// If it's an image get the mime type right.
	if ($is_image)
	{
		$attachmentOptions['mime_type'] = getValidMimeImageType($size[2]);

		// Want to correct for phonetographer photos?
		if (!empty($modSettings['attachment_autorotate']))
		{
			$image->autoRotate();
		}
	}

	// Get the hash if no hash has been given yet.
	if (empty($attachmentOptions['file_hash']))
	{
		$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], 0, null, true);
	}

	// Assuming no-one set the extension let's take a look at it.
	if (empty($attachmentOptions['fileext']))
	{
		$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
		if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] == $attachmentOptions['name'])
		{
			$attachmentOptions['fileext'] = '';
		}
	}

	$db->insert('',
		'{db_prefix}attachments',
		array(
			'id_folder' => 'int', 'id_msg' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
			'size' => 'int', 'width' => 'int', 'height' => 'int',
			'mime_type' => 'string-20', 'approved' => 'int',
		),
		array(
			(int) $attachmentOptions['id_folder'], (int) $attachmentOptions['post'], $attachmentOptions['name'], $attachmentOptions['file_hash'], $attachmentOptions['fileext'],
			(int) $attachmentOptions['size'], (empty($attachmentOptions['width']) ? 0 : (int) $attachmentOptions['width']), (empty($attachmentOptions['height']) ? '0' : (int) $attachmentOptions['height']),
			(!empty($attachmentOptions['mime_type']) ? $attachmentOptions['mime_type'] : ''), (int) $attachmentOptions['approved'],
		),
		array('id_attach')
	);
	$attachmentOptions['id'] = $db->insert_id('{db_prefix}attachments');

	// @todo Add an error here maybe?
	if (empty($attachmentOptions['id']))
	{
		return false;
	}

	// Now that we have the attach id, let's rename this and finish up.
	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $attachmentOptions['id_folder'], false, $attachmentOptions['file_hash']);
	if (rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']) && $is_image)
	{
		// Let the manipulator the (loaded) file new location
		$image->setFileName($attachmentOptions['destination']);
	}

	// If it's not approved then add to the approval queue.
	if (!$attachmentOptions['approved'])
	{
		$db->insert('',
			'{db_prefix}approval_queue',
			array(
				'id_attach' => 'int', 'id_msg' => 'int',
			),
			array(
				$attachmentOptions['id'], (int) $attachmentOptions['post'],
			),
			array()
		);
	}

	if (empty($modSettings['attachmentThumbnails']) || !$is_image || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
	{
		return true;
	}

	// Like thumbnails, do we?
	if (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight'])
		&& ($attachmentOptions['width'] > $modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > $modSettings['attachmentThumbHeight']))
	{
		$thumb_filename = $attachmentOptions['name'] . '_thumb';
		$thumb_path = $attachmentOptions['destination'] . '_thumb';
		$thumb_image = $image->createThumbnail($modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight'], $thumb_path);
		if ($thumb_image !== false)
		{
			// Figure out how big we actually made it.
			$size = $thumb_image->getImageDimensions();
			list ($thumb_width, $thumb_height) = $size;

			$thumb_mime = getValidMimeImageType($size[2]);
			$thumb_size = $thumb_image->getFilesize();
			$thumb_file_hash = getAttachmentFilename($thumb_filename, 0, null, true);

			// We should check the file size and count here since thumbs are added to the existing totals.
			$attachmentsDir->checkDirSize($thumb_size);
			$current_dir_id = $attachmentsDir->currentDirectoryId();

			// If a new folder has been already created. Gotta move this thumb there then.
			if ($attachmentsDir->isCurrentDirectoryId($attachmentOptions['id_folder']) === false)
			{
				$current_dir = $attachmentsDir->getCurrent();
				$current_dir_id = $attachmentsDir->currentDirectoryId();
				rename($thumb_path, $current_dir . '/' . $thumb_filename);
				$thumb_path = $current_dir . '/' . $thumb_filename;
			}

			// To the database we go!
			$db->insert('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20', 'approved' => 'int',
				),
				array(
					$current_dir_id, (int) $attachmentOptions['post'], 3, $thumb_filename, $thumb_file_hash, $attachmentOptions['fileext'],
					$thumb_size, $thumb_width, $thumb_height, $thumb_mime, (int) $attachmentOptions['approved'],
				),
				array('id_attach')
			);
			$attachmentOptions['thumb'] = $db->insert_id('{db_prefix}attachments');

			if (!empty($attachmentOptions['thumb']))
			{
				$db->query('', '
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:id_thumb}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_thumb' => $attachmentOptions['thumb'],
						'id_attach' => $attachmentOptions['id'],
					)
				);

				rename($thumb_path, getAttachmentFilename($thumb_filename, $attachmentOptions['thumb'], $current_dir_id, false, $thumb_file_hash));
			}
		}
	}

	return true;
}

/**
 * Get the specified attachment.
 *
 * What it does:
 *
 * - This includes a check of the topic
 * - it only returns the attachment if it's indeed attached to a message in the topic given as parameter, and
 * query_see_board...
 * - Must return the same array keys as getAvatar() and getAttachmentThumbFromTopic()
 *
 * @param int $id_attach
 * @param int $id_topic
 *
 * @return array
 */
function getAttachmentFromTopic($id_attach, $id_topic)
{
	$db = database();

	// Make sure this attachment is on this board.
	$attachmentData = array();
	$request = $db->fetchQuery('
		SELECT 
			a.id_folder, a.filename, a.file_hash, a.fileext, a.id_attach, a.attachment_type, 
			a.mime_type, a.approved, m.id_member
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg AND m.id_topic = {int:current_topic})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.id_attach = {int:attach}
		LIMIT 1',
		array(
			'attach' => $id_attach,
			'current_topic' => $id_topic,
		)
	);
	if ($request->num_rows() !== 0)
	{
		$attachmentData = $request->fetch_assoc();
		$attachmentData['id_folder'] = (int) $attachmentData['id_folder'];
		$attachmentData['id_attach'] = (int) $attachmentData['id_attach'];
		$attachmentData['id_member'] = (int) $attachmentData['id_member'];
		$attachmentData['attachment_type'] = (int) $attachmentData['attachment_type'];
	}
	$request->free_result();

	return $attachmentData;
}

/**
 * Get the thumbnail of specified attachment.
 *
 * What it does:
 *
 * - This includes a check of the topic
 * - it only returns the attachment if it's indeed attached to a message in the topic given as parameter, and
 * query_see_board...
 * - Must return the same array keys as getAvatar() & getAttachmentFromTopic
 *
 * @param int $id_attach
 * @param int $id_topic
 *
 * @return array
 */
function getAttachmentThumbFromTopic($id_attach, $id_topic)
{
	$db = database();

	// Make sure this attachment is on this board.
	$request = $db->fetchQuery('
		SELECT 
			th.id_folder, th.filename, th.file_hash, th.fileext, th.id_attach, 
			th.attachment_type, th.mime_type,
			a.id_folder AS attach_id_folder, a.filename AS attach_filename,
			a.file_hash AS attach_file_hash, a.fileext AS attach_fileext,
			a.id_attach AS attach_id_attach, a.attachment_type AS attach_attachment_type,
			a.mime_type AS attach_mime_type,
		 	a.approved, m.id_member
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg AND m.id_topic = {int:current_topic})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
			LEFT JOIN {db_prefix}attachments AS th ON (th.id_attach = a.id_thumb)
		WHERE a.id_attach = {int:attach}',
		array(
			'attach' => $id_attach,
			'current_topic' => $id_topic,
		)
	);
	$attachmentData = [
		'id_folder' => '', 'filename' => '', 'file_hash' => '', 'fileext' => '', 'id_attach' => '',
		'attachment_type' => '', 'mime_type' => '', 'approved' => '', 'id_member' => ''];
	if ($request->num_rows() !== 0)
	{
		$row = $request->fetch_assoc();

		// If there is a hash then the thumbnail exists
		if (!empty($row['file_hash']))
		{
			$attachmentData = array(
				'id_folder' => $row['id_folder'],
				'filename' => $row['filename'],
				'file_hash' => $row['file_hash'],
				'fileext' => $row['fileext'],
				'id_attach' => $row['id_attach'],
				'attachment_type' => $row['attachment_type'],
				'mime_type' => $row['mime_type'],
				'approved' => $row['approved'],
				'id_member' => $row['id_member'],
			);
		}
		// otherwise $modSettings['attachmentThumbnails'] may be (or was) off, so original file
		elseif (getValidMimeImageType($row['attach_mime_type']) !== '')
		{
			$attachmentData = array(
				'id_folder' => $row['attach_id_folder'],
				'filename' => $row['attach_filename'],
				'file_hash' => $row['attach_file_hash'],
				'fileext' => $row['attach_fileext'],
				'id_attach' => $row['attach_id_attach'],
				'attachment_type' => $row['attach_attachment_type'],
				'mime_type' => $row['attach_mime_type'],
				'approved' => $row['approved'],
				'id_member' => $row['id_member'],
			);
		}
	}

	return $attachmentData;
}

/**
 * Returns if the given attachment ID is an image file or not
 *
 * What it does:
 *
 * - Given an attachment id, checks that it exists as an attachment
 * - Verifies the message its associated is on a board the user can see
 * - Sets 'is_image' if the attachment is an image file
 * - Returns basic attachment values
 *
 * @param int $id_attach
 *
 * @return array|bool
 */
function isAttachmentImage($id_attach)
{
	$db = database();

	// Make sure this attachment is on this board.
	$attachmentData = array();
	$db->fetchQuery('
		SELECT
			a.filename, a.fileext, a.id_attach, a.attachment_type, a.mime_type, a.approved, 
			a.downloads, a.size, a.width, a.height, m.id_topic, m.id_board
		FROM {db_prefix}attachments as a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE id_attach = {int:attach}
			AND attachment_type = {int:type}
		LIMIT 1',
		array(
			'attach' => $id_attach,
			'type' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$attachmentData) {
			$attachmentData = $row;
			$attachmentData['is_image'] = substr($attachmentData['mime_type'], 0, 5) === 'image';
			$attachmentData['size'] = byte_format($attachmentData['size']);
			$attachmentData['is_approved'] = $row['approved'] === '1';
		}
	);

	return !empty($attachmentData) ? $attachmentData : false;
}

/**
 * Increase download counter for id_attach.
 *
 * What it does:
 *
 * - Does not check if it's a thumbnail.
 *
 * @param int $id_attach
 */
function increaseDownloadCounter($id_attach)
{
	$db = database();

	$db->fetchQuery('
		UPDATE {db_prefix}attachments
		SET downloads = downloads + 1
		WHERE id_attach = {int:id_attach}',
		array(
			'id_attach' => $id_attach,
		)
	);
}

/**
 * Saves a file and stores it locally for avatar use by id_member.
 *
 * What it does:
 *
 * - supports input of GIF, JPG, PNG, BMP, WEBP and WBMP formats
 * - outputs png, jpg or webp based on best choice
 * - uses createThumbnail() to resize to max_width by max_height, and saves the result to a file.
 * - updates the database info for the member's avatar.
 * - returns whether the download and resize was successful.
 *
 * @param string $temporary_path the full path to the temporary file
 * @param int $memID member ID
 * @param int $max_width
 * @param int $max_height
 * @return bool whether the download and resize was successful.
 */
function saveAvatar($temporary_path, $memID, $max_width, $max_height)
{
	global $modSettings;

	$db = database();

	// Just making sure there is a non-zero member.
	if (empty($memID))
	{
		return false;
	}

	// Get this party started
	$valid_avatar_extensions = [
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_WEBP => 'webp'
	];

	$image = new Image($temporary_path);
	if (!$image->isImageLoaded())
	{
		return false;
	}

	$format = $image->getDefaultFormat();
	$ext = $valid_avatar_extensions[$format];
	$tokenizer = new TokenHash();
	$fileName = 'avatar_' . $memID . '_' . $tokenizer->generate_hash(16) . '.' . $ext;

	// Clear out any old attachment
	require_once(SUBSDIR . '/ManageAttachments.subs.php');
	removeAttachments(array('id_member' => $memID));

	$db->insert('',
		'{db_prefix}attachments',
		array(
			'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255',
			'file_hash' => 'string-255', 'fileext' => 'string-8', 'size' => 'int', 'id_folder' => 'int',
		),
		array(
			$memID, 1, $fileName, '', $ext, 1, 1,
		),
		array('id_attach')
	);
	$attachID = $db->insert_id('{db_prefix}attachments');

	// The destination filename depends on the custom dir for avatars
	$destName = $modSettings['custom_avatar_dir'] . '/' . $fileName;

	// Resize and rotate it.
	if (!empty($modSettings['attachment_autorotate']))
	{
		$image->autoRotate();
	}

	$thumb_image = $image->createThumbnail($max_width, $max_height, $destName, $format);
	if ($thumb_image !== false)
	{
		list ($width, $height) = $thumb_image->getImageDimensions();
		$mime_type = $thumb_image->getMimeType();

		// Write filesize in the database.
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET 
				size = {int:filesize}, width = {int:width}, height = {int:height}, 
				mime_type = {string:mime_type}
			WHERE id_attach = {int:current_attachment}',
			array(
				'filesize' => $thumb_image->getFilesize(),
				'width' => (int) $width,
				'height' => (int) $height,
				'current_attachment' => $attachID,
				'mime_type' => $mime_type,
			)
		);

		// Retain this globally in case the script wants it.
		$modSettings['new_avatar_data'] = array(
			'id' => $attachID,
			'filename' => $destName,
			'type' => 1,
		);

		return true;
	}

	// Having a problem with image manipulation, rotation, resize, etc
	$db->query('', '
		DELETE FROM {db_prefix}attachments
		WHERE id_attach = {int:current_attachment}',
		array(
			'current_attachment' => $attachID,
		)
	);

	return false;
}

/**
 * Get the size of a specified image with better error handling.
 *
 * What it does:
 *
 * - Uses getimagesizefromstring() to determine the dimensions of an image file.
 * - Attempts to connect to the server first, so it won't time out.
 * - Attempts to read a short byte range of the file, just enough to validate
 * the mime type.
 *
 * @param string $url
 * @return mixed[]|bool the image size as array(width, height), or false on failure
 */
function url_image_size($url)
{
	// Can we pull this from the cache... please please?
	$temp = [];
	if (Cache::instance()->getVar($temp, 'url_image_size-' . md5($url), 3600))
	{
		return $temp;
	}

	$url_path = parse_url($url, PHP_URL_PATH);
	$extension = pathinfo($url_path, PATHINFO_EXTENSION);

	// Set a RANGE to read
	switch ($extension)
	{
		case 'jpg':
		case 'jpeg':
			// Size location block is variable, so we fetch a meaningful chunk
			$range = 32768;
			break;
		case 'png':
			// Size will be in the first 24 bytes
			$range = 1024;
			break;
		case 'gif':
			// Size will be in the first 10 bytes
			$range = 1024;
			break;
		case 'bmp':
			// Size will be in the first 32 bytes
			$range = 1024;
			break;
		default:
			// Read the entire file then, webp for example might have the exif at the end
			$range = 0;
	}

	$image = new FsockFetchWebdata(['max_length' => $range]);
	$image->get_url_data($url);

	// The server may not understand Range: so lets try to fetch the entire thing
	// assuming we were not simply turned away and did not already try
	if ($range !== 0 && $image->result('code') != 200 && $image->result('code') != 403)
	{
		$image = new FsockFetchWebdata([]);
		$image->get_url_data($url);
	}

	// Here is the data, getimagesizefromstring does not care if its a complete image, it only
	// searches for size headers in a given data set.
	$data = $image->result('body');
	unset($image);
	$size = empty($data) ? elk_getimagesize($url) : getimagesizefromstring($data);

	// Well, ok, umm, fail!
	if ($size === false || $data === false)
	{
		$size = [-1, -1, -1];
	}

	// Save this for 1hour, its not like the image size is going to change, and if we
	// failed, no sense trying again and again!
	Cache::instance()->put('url_image_size-' . md5($url), $size, 3600);

	return $size;
}

/**
 * Function to retrieve server-stored avatar files
 *
 * @param string $directory
 * @return array
 */
function getServerStoredAvatars($directory)
{
	global $context, $txt, $modSettings;

	$result = [];
	$file_functions = FileFunctions::instance();

	// You can always have no avatar
	$result[] = [
		'filename' => 'blank.png',
		'checked' => in_array($context['member']['avatar']['server_pic'], ['', 'blank.png']),
		'name' => $txt['no_pic'],
		'is_dir' => false
	];

	// Not valid is easy
	$avatarDir = $modSettings['avatar_directory'] . (!empty($directory) ? '/' : '') . $directory;
	if (!$file_functions->isDir($avatarDir))
	{
		return $result;
	}

	// Find all avatars under, and in, the avatar directory
	$serverAvatars = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(
			$avatarDir,
			FilesystemIterator::SKIP_DOTS
		),
		\RecursiveIteratorIterator::SELF_FIRST,
		\RecursiveIteratorIterator::CATCH_GET_CHILD
	);
	$key = 0;
	foreach ($serverAvatars as $entry)
	{
		if ($entry->isDir())
		{
			// Add a new directory
			$result[] = [
				'filename' => htmlspecialchars(basename($entry), ENT_COMPAT, 'UTF-8'),
				'checked' => strpos($context['member']['avatar']['server_pic'], basename($entry) . '/') !== false,
				'name' => '[' . htmlspecialchars(str_replace('_', ' ', basename($entry)), ENT_COMPAT, 'UTF-8') . ']',
				'is_dir' => true,
				'files' => []
			];
			$key++;

			continue;
		}

		// Add the files under the current directory we are iterating on
		if (!in_array($entry->getFilename(), array('blank.png', 'index.php', '.htaccess')))
		{
			$extension = $entry->getExtension();
			$filename = $entry->getBasename('.' . $extension);

			// Make sure it is an image.
			if (empty(getValidMimeImageType($extension)))
			{
				continue;
			}

			$result[$key]['files'][] = [
				'filename' => htmlspecialchars($entry->getFilename(), ENT_COMPAT, 'UTF-8'),
				'checked' => $entry->getFilename() == $context['member']['avatar']['server_pic'],
				'name' => htmlspecialchars(str_replace('_', ' ', $filename), ENT_COMPAT, 'UTF-8'),
				'is_dir' => false
			];

			if (dirname($entry->getPath(), 1) === $modSettings['avatar_directory'])
			{
				$context['avatar_list'][] = str_replace($modSettings['avatar_directory'] . '/', '', $entry->getPathname());
			}
		}
	}

	return $result;
}

/**
 * Update an attachment's thumbnail
 *
 * @param string $filename the actual name of the file
 * @param int $id_attach the numeric attach id
 * @param int $id_msg the numeric message the attachment is associated with
 * @param int $old_id_thumb = 0 id of thumbnail to remove, such as from our post form
 * @param string $real_filename the fully qualified hash name of where the file is
 * @return array The updated information
 */
function updateAttachmentThumbnail($filename, $id_attach, $id_msg, $old_id_thumb = 0, $real_filename = '')
{
	global $modSettings;

	$attachment = array('id_attach' => $id_attach);

	// Load our image functions, it will determine which graphics library to use
	$image = new Image($filename);

	// Image is not autorotated because it was at the time of upload (hopefully)
	$thumb_filename = (!empty($real_filename) ? $real_filename : $filename) . '_thumb';
	$thumb_image = $image->createThumbnail($modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight']);

	if ($thumb_image instanceof Image)
	{
		// So what folder are we putting this image in?
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());
		$id_folder_thumb = $attachmentsDir->currentDirectoryId();

		// Calculate the size of the created thumbnail.
		$size = $thumb_image->getImageDimensions();
		list ($attachment['thumb_width'], $attachment['thumb_height']) = $size;
		$thumb_size = $thumb_image->getFilesize();

		// Figure out the mime type and other details
		$thumb_mime = getValidMimeImageType($size[2]);
		$thumb_ext = substr($thumb_mime, strpos($thumb_mime, '/') + 1);
		$thumb_hash = getAttachmentFilename($thumb_filename, 0, null, true);

		// Add this beauty to the database.
		$db = database();
		$db->insert('',
			'{db_prefix}attachments',
			array('id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'size' => 'int', 'width' => 'int', 'height' => 'int', 'fileext' => 'string-8', 'mime_type' => 'string-255'),
			array($id_folder_thumb, $id_msg, 3, $thumb_filename, $thumb_hash, (int) $thumb_size, (int) $attachment['thumb_width'], (int) $attachment['thumb_height'], $thumb_ext, $thumb_mime),
			array('id_attach')
		);

		$attachment['id_thumb'] = $db->insert_id('{db_prefix}attachments');
		if (!empty($attachment['id_thumb']))
		{
			$db->query('', '
				UPDATE {db_prefix}attachments
				SET id_thumb = {int:id_thumb}
				WHERE id_attach = {int:id_attach}',
				array(
					'id_thumb' => $attachment['id_thumb'],
					'id_attach' => $attachment['id_attach'],
				)
			);

			$thumb_realname = getAttachmentFilename($thumb_filename, $attachment['id_thumb'], $id_folder_thumb, false, $thumb_hash);
			if (file_exists($filename . '_thumb'))
			{
				rename($filename . '_thumb', $thumb_realname);
			}

			// Do we need to remove an old thumbnail?
			if (!empty($old_id_thumb))
			{
				require_once(SUBSDIR . '/ManageAttachments.subs.php');
				removeAttachments(array('id_attach' => $old_id_thumb), '', false, false);
			}
		}
	}

	return $attachment;
}

/**
 * Compute and return the total size of attachments to a single message.
 *
 * @param int $id_msg
 * @param bool $include_count = true if true, it also returns the attachments count
 * @return mixed
 */
function attachmentsSizeForMessage($id_msg, $include_count = true)
{
	$db = database();

	if ($include_count)
	{
		$request = $db->fetchQuery('
			SELECT 
				COUNT(*), SUM(size)
			FROM {db_prefix}attachments
			WHERE id_msg = {int:id_msg}
				AND attachment_type = {int:attachment_type}',
			array(
				'id_msg' => $id_msg,
				'attachment_type' => 0,
			)
		);
	}
	else
	{
		$request = $db->fetchQuery('
			SELECT 
				COUNT(*)
			FROM {db_prefix}attachments
			WHERE id_msg = {int:id_msg}
				AND attachment_type = {int:attachment_type}',
			array(
				'id_msg' => $id_msg,
				'attachment_type' => 0,
			)
		);
	}

	return $request->fetch_row();
}

/**
 * Older attachments may still use this function.
 *
 * @param string $filename
 * @param int $attachment_id
 * @param string|null $dir
 * @param bool $new
 *
 * @return null|string|string[]
 */
function getLegacyAttachmentFilename($filename, $attachment_id, $dir = null, $new = false)
{
	global $modSettings;

	$clean_name = $filename;

	// Sorry, no spaces, dots, or anything else but letters allowed.
	$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);

	$enc_name = $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
	$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

	if (empty($attachment_id) || ($new && empty($modSettings['attachmentEncryptFilenames'])))
	{
		return $clean_name;
	}
	elseif ($new)
	{
		return $enc_name;
	}

	$attachmentsDir = new AttachmentsDirectory($modSettings, database());
	$path = $attachmentsDir->getCurrent();

	return file_exists($path . '/' . $enc_name) ? $path . '/' . $enc_name : $path . '/' . $clean_name;
}

/**
 * Binds a set of attachments to a message.
 *
 * @param int $id_msg
 * @param int[] $attachment_ids
 */
function bindMessageAttachments($id_msg, $attachment_ids)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}attachments
		SET id_msg = {int:id_msg}
		WHERE id_attach IN ({array_int:attachment_list})',
		array(
			'attachment_list' => $attachment_ids,
			'id_msg' => $id_msg,
		)
	);
}

/**
 * Get an attachment's encrypted filename. If $new is true, won't check for file existence.
 *
 * - If new is set returns a hash for the db
 * - If no file hash is supplied, determines one and returns it
 * - Returns the path to the file
 *
 * @param string $filename The name of the file
 * @param int|null $attachment_id The ID of the attachment
 * @param string|null $dir Which directory it should be in (null to use current)
 * @param bool $new If this is a new attachment, if so just returns a hash
 * @param string $file_hash The file hash
 *
 * @return string
 * @todo this currently returns the hash if new, and the full filename otherwise.
 * Something messy like that.
 * @todo and of course everything relies on this behavior and work around it. :P.
 * Converters included.
 */
function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $modSettings;

	// Just make up a nice hash...
	if ($new)
	{
		$tokenizer = new TokenHash();

		return $tokenizer->generate_hash(32);
	}

	// In case of files from the old system, do a legacy call.
	if (empty($file_hash))
	{
		return getLegacyAttachmentFilename($filename, $attachment_id, $dir, $new);
	}

	// If we were passed the directory id, use it
	$modSettings['currentAttachmentUploadDir'] = $dir;
	$attachmentsDir = new AttachmentsDirectory($modSettings, database());
	$path = $attachmentsDir->getCurrent();

	return $path . '/' . $attachment_id . '_' . $file_hash . '.elk';
}

/**
 * Returns the board and the topic the attachment belongs to.
 *
 * @param int $id_attach
 * @return int[]|bool on fail else an array of id_board, id_topic
 */
function getAttachmentPosition($id_attach)
{
	$db = database();

	// Make sure this attachment is on this board.
	$request = $db->fetchQuery('
		SELECT 
			m.id_board, m.id_topic
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE a.id_attach = {int:attach}
			AND {query_see_board}
		LIMIT 1',
		array(
			'attach' => $id_attach,
		)
	);

	$attachmentData = $request->fetch_all();

	if (empty($attachmentData))
	{
		return false;
	}

	return $attachmentData[0];
}

/**
 * Simple wrapper for getimagesize
 *
 * @param string $file
 * @param string|bool $error return array or false on error
 *
 * @return array|bool
 */
function elk_getimagesize($file, $error = 'array')
{
	$sizes = @getimagesize($file);

	// Can't get it, what shall we return
	if (empty($sizes))
	{
		$sizes = $error === 'array' ? [-1, -1, -1] : false;
	}

	return $sizes;
}

/**
 * Checks if we have a known and support mime-type for which we have a thumbnail image
 *
 * @param string $file_ext
 * @param bool $url
 *
 * @return bool|string
 */
function returnMimeThumb($file_ext, $url = false)
{
	global $settings;

	// These are not meant to be exhaustive, just some of the most common attached on a forum
	$generics = array(
		'arc' => array('tgz', 'zip', 'rar', '7z', 'gz'),
		'doc' => array('doc', 'docx', 'wpd', 'odt'),
		'sound' => array('wav', 'mp3', 'pcm', 'aiff', 'wma', 'm4a', 'flac'),
		'video' => array('mp4', 'mgp', 'mpeg', 'mp4', 'wmv', 'mkv', 'flv', 'aiv', 'mov', 'swf'),
		'txt' => array('rtf', 'txt', 'log'),
		'presentation' => array('ppt', 'pps', 'odp'),
		'spreadsheet' => array('xls', 'xlr', 'ods'),
		'web' => array('html', 'htm')
	);
	foreach ($generics as $generic_extension => $generic_types)
	{
		if (in_array($file_ext, $generic_types))
		{
			$file_ext = $generic_extension;
			break;
		}
	}

	static $distinct = array('arc', 'doc', 'sound', 'video', 'txt', 'presentation', 'spreadsheet', 'web',
							 'c', 'cpp', 'css', 'csv', 'java', 'js', 'pdf', 'php', 'sql', 'xml');

	if (empty($settings))
	{
		ThemeLoader::loadEssentialThemeData();
	}

	// Return the mine thumbnail if it exists or just the default
	if (!in_array($file_ext, $distinct) || !file_exists($settings['theme_dir'] . '/images/mime_images/' . $file_ext . '.png'))
	{
		$file_ext = 'default';
	}

	$location = $url ? $settings['theme_url'] : $settings['theme_dir'];

	return $location . '/images/mime_images/' . $file_ext . '.png';
}

/**
 * From either a mime type, an extension or an IMAGETYPE_* constant
 * returns a valid image mime type
 *
 * @param string $mime
 *
 * @return string
 */
function getValidMimeImageType($mime)
{
	// These are the only valid image types.
	$validImageTypes = array(
		-1 => 'jpg',
		// Starting from here are the IMAGETYPE_* constants
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_PSD => 'psd',
		IMAGETYPE_BMP => 'bmp',
		IMAGETYPE_TIFF_II => 'tiff',
		IMAGETYPE_TIFF_MM => 'tiff',
		IMAGETYPE_JPC => 'jpeg',
		IMAGETYPE_IFF => 'iff',
		IMAGETYPE_WBMP => 'bmp',
		IMAGETYPE_WEBP => 'webp'
	);

	$ext = (int) $mime > 0 && isset($validImageTypes[(int) $mime]) ? $validImageTypes[(int) $mime] : '';
	if (empty($ext))
	{
		$ext = strtolower(trim(substr($mime, strpos($mime, '/')), '/'));
	}

	return in_array($ext, $validImageTypes) ? 'image/' . $ext : '';
}

/**
 * This function returns the mimeType of a file using the best means available
 *
 * @param string $filename
 * @return string
 */
function getMimeType($filename)
{
	$mimeType = '';

	// Check only existing readable files
	if (!file_exists($filename) || !is_readable($filename))
	{
		return '';
	}

	// Try finfo, this is the preferred way
	if (function_exists('finfo_open'))
	{
		$finfo = finfo_open(FILEINFO_MIME);
		$mimeType = finfo_file($finfo, $filename);
		finfo_close($finfo);
	}
	// No finfo? What? lets try the old mime_content_type
	elseif (function_exists('mime_content_type'))
	{
		$mimeType = mime_content_type($filename);
	}
	// Try using an exec call
	elseif (function_exists('exec'))
	{
		$mimeType = @exec("/usr/bin/file -i -b $filename");
	}

	// Still nothing? We should at least be able to get images correct
	if (empty($mimeType))
	{
		$imageData = elk_getimagesize($filename, 'none');
		if (!empty($imageData['mime']))
		{
			$mimeType = $imageData['mime'];
		}
	}

	// Account for long responses like text/plain; charset=us-ascii
	if (!empty($mimeType) && strpos($mimeType, ';'))
	{
		list($mimeType,) = explode(';', $mimeType);
	}

	return $mimeType;
}
