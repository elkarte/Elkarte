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
 * This file handles the uploading and creation of attachments
 * as well as the auto management of the attachment directories.
 * Note to enhance documentation later:
 * attachment_type = 3 is a thumbnail, etc.
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Check and create a directory automatically.
 *
 */
function automanage_attachments_check_directory()
{
	global $modSettings, $context;

	// Not pretty, but since we don't want folders created for every post. It'll do unless a better solution can be found.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'admin')
		$doit = true;
	elseif (empty($modSettings['automanage_attachments']))
		return;
	elseif (!isset($_FILES))
		return;
	elseif (isset($_FILES['attachment']))
	{
		foreach ($_FILES['attachment']['tmp_name'] as $dummy)
		{
			if (!empty($dummy))
			{
				$doit = true;
				break;
			}
		}
	}

	if (!isset($doit))
		return;

	// get our date and random numbers for the directory choices
	$year = date('Y');
	$month = date('m');

	$rand = md5(mt_rand());
	$rand1 = $rand[1];
	$rand = $rand[0];

	if (!empty($modSettings['attachment_basedirectories']) && !empty($modSettings['use_subdirectories_for_attachments']))
	{
		if (!is_array($modSettings['attachment_basedirectories']))
			$modSettings['attachment_basedirectories'] = unserialize($modSettings['attachment_basedirectories']);

		$base_dir = array_search($modSettings['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']);
	}
	else
		$base_dir = 0;

	if ($modSettings['automanage_attachments'] == 1)
	{
		if (!isset($modSettings['last_attachments_directory']))
			$modSettings['last_attachments_directory'] = array();
		if (!is_array($modSettings['last_attachments_directory']))
			$modSettings['last_attachments_directory'] = unserialize($modSettings['last_attachments_directory']);
		if (!isset($modSettings['last_attachments_directory'][$base_dir]))
			$modSettings['last_attachments_directory'][$base_dir] = 0;
	}

	$basedirectory = (!empty($modSettings['use_subdirectories_for_attachments']) ? ($modSettings['basedirectory_for_attachments']) : BOARDDIR);
	//Just to be sure: I don't want directory separators at the end
	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	switch ($modSettings['automanage_attachments'])
	{
		case 1:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . (isset($modSettings['last_attachments_directory'][$base_dir]) ? $modSettings['last_attachments_directory'][$base_dir] : 0);
			break;
		case 2:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . $year;
			break;
		case 3:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
			break;
		case 4:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty($modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand;
			break;
		case 5:
			$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty($modSettings['use_subdirectories_for_attachments']) ? 'attachments-' : 'random_') . $rand . DIRECTORY_SEPARATOR . $rand1;
			break;
		default :
			$updir = '';
	}

	if (!is_array($modSettings['attachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
	if (!in_array($updir, $modSettings['attachmentUploadDir']) && !empty($updir))
		$outputCreation = automanage_attachments_create_directory($updir);
	elseif (in_array($updir, $modSettings['attachmentUploadDir']))
		$outputCreation = true;

	if ($outputCreation)
	{
		$modSettings['currentAttachmentUploadDir'] = array_search($updir, $modSettings['attachmentUploadDir']);
		$context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

		updateSettings(array(
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		));
	}

	return $outputCreation;
}

/**
 * Creates a directory as defined by the admin attach options
 * Attempts to make the directory writable
 * Places an .htaccess in new directories for security
 *
 * @param type $updir
 * @return boolean
 */
function automanage_attachments_create_directory($updir)
{
	global $modSettings, $context;

	$tree = get_directory_tree_elements($updir);
	$count = count($tree);

	$directory = attachments_init_dir($tree, $count);
	if ($directory === false)
	{
		// Maybe it's just the folder name
		$tree = get_directory_tree_elements(BOARDDIR . DIRECTORY_SEPARATOR . $updir);
		$count = count($tree);

		$directory = attachments_init_dir($tree, $count);
		if ($directory === false)
			return false;
	}

	$directory .= DIRECTORY_SEPARATOR . array_shift($tree);

	while (!@is_dir($directory) || $count != -1)
	{
		if (!@is_dir($directory))
		{
			if (!@mkdir($directory, 0755))
			{
				$context['dir_creation_error'] = 'attachments_no_create';
				return false;
			}
		}

		$directory .= DIRECTORY_SEPARATOR . array_shift($tree);
		$count--;
	}

	// try to make it writable
	if (!is_writable($directory))
	{
		chmod($directory, 0755);
		if (!is_writable($directory))
		{
			chmod($directory, 0775);
			if (!is_writable($directory))
			{
				chmod($directory, 0777);
				if (!is_writable($directory))
				{
					$context['dir_creation_error'] = 'attachments_no_write';
					return false;
				}
			}
		}
	}

	// Everything seems fine...let's create the .htaccess
	if (!file_exists($directory . DIRECTORY_SEPARATOR . '.htaccess'))
		secureDirectory($updir, true);

	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$updir = rtrim($updir, $sep);

	// Only update if it's a new directory
	if (!in_array($updir, $modSettings['attachmentUploadDir']))
	{
		$modSettings['currentAttachmentUploadDir'] = max(array_keys($modSettings['attachmentUploadDir'])) + 1;
		$modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']] = $updir;

		updateSettings(array(
			'attachmentUploadDir' => serialize($modSettings['attachmentUploadDir']),
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		), true);
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
	}

	$context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
	return true;
}

/**
 * Determines the current base directory and attachment directory
 * Increments the above directory to the next availble slot
 * Uses automanage_attachments_create_directory to create the incremental directory
 *
 * @return boolean
 */
function automanage_attachments_by_space()
{
	global $modSettings;

	if (!isset($modSettings['automanage_attachments']) || (!empty($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] != 1))
		return;

	$basedirectory = (!empty($modSettings['use_subdirectories_for_attachments']) ? ($modSettings['basedirectory_for_attachments']) : BOARDDIR);

	// Just to be sure: I don't want directory separators at the end
	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	// Get the current base directory
	if (!empty($modSettings['use_subdirectories_for_attachments']) && !empty($modSettings['attachment_basedirectories']))
	{
		$base_dir = array_search($modSettings['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']);
		$base_dir = !empty($modSettings['automanage_attachments']) ? $base_dir : 0;
	}
	else
		$base_dir = 0;

	// Get the last attachment directory for that base directory
	if (empty($modSettings['last_attachments_directory'][$base_dir]))
		$modSettings['last_attachments_directory'][$base_dir] = 0;
	// And increment it.
	$modSettings['last_attachments_directory'][$base_dir]++;

	$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . $modSettings['last_attachments_directory'][$base_dir];

	// make sure it exists and is writable
	if (automanage_attachments_create_directory($updir))
	{
		$modSettings['currentAttachmentUploadDir'] = array_search($updir, $modSettings['attachmentUploadDir']);
		updateSettings(array(
			'last_attachments_directory' => serialize($modSettings['last_attachments_directory']),
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		));
		$modSettings['last_attachments_directory'] = unserialize($modSettings['last_attachments_directory']);

		return true;
	}
	else
		return false;
}

/**
 * Finds the current directory tree for the supplied base directory
 *
 * @param type $directory
 * @return boolean on fail else array of directory names
 */
function get_directory_tree_elements ($directory)
{
	/*
		In Windows server both \ and / can be used as directory separators in paths
		In Linux (and presumably *nix) servers \ can be part of the name
		So for this reasons:
			* in Windows we need to explode for both \ and /
			* while in linux should be safe to explode only for / (aka DIRECTORY_SEPARATOR)
	*/
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		$tree = preg_split('#[\\\/]#', $directory);
	else
	{
		if (substr($directory, 0, 1) != DIRECTORY_SEPARATOR)
			return false;

		$tree = explode(DIRECTORY_SEPARATOR, trim($directory, DIRECTORY_SEPARATOR));
	}
	return $tree;
}

/**
 * Helper function for automanage_attachments_create_directory
 * Gets the directory w/o drive letter for windows
 *
 * @param array $tree
 * @param int $count
 * @return boolean
 */
function attachments_init_dir (&$tree, &$count)
{
	$directory = '';
	// If on Windows servers the first part of the path is the drive (e.g. "C:")
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
	{
		// Better be sure that the first part of the path is actually a drive letter...
		// ...even if, I should check this in the admin page...isn't it?
		// ...NHAAA Let's leave space for users' complains! :P
		if (preg_match('/^[a-z]:$/i', $tree[0]))
			$directory = array_shift($tree);
		else
			return false;

		$count--;
	}

	return $directory;
}

/**
 * Handles the actual saving of attachments to a directory.
 * Loops through $_FILES['attachment'] array and saves each file to the current attachments folder.
 * Validates the save location actually exists.
 *
 * @param $id_msg = null id of the message with attachments, if any. If null, this is an upload in progress for a new post.
 *
 */
function processAttachments($id_msg = null)
{
	global $context, $modSettings, $txt, $user_info, $ignore_temp, $topic, $board;

	// Make sure we're uploading to the right place.
	if (!empty($modSettings['automanage_attachments']))
		automanage_attachments_check_directory();

	if (!is_array($modSettings['attachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

	$context['attach_dir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

	// Is the attachments folder actualy there?
	if (!empty($context['dir_creation_error']))
		$initial_error = $context['dir_creation_error'];
	elseif (!is_dir($context['attach_dir']))
	{
		$initial_error = 'attach_folder_warning';
		log_error(sprintf($txt['attach_folder_admin_warning'], $context['attach_dir']), 'critical');
	}

	if (!isset($initial_error) && !isset($context['attachments']))
	{
		// If this isn't a new post, check the current attachments.
		if (!empty($id_msg))
			list ($context['attachments']['quantity'], $context['attachments']['total_size']) = attachmentsSizeForMessage($id_msg);
		else
			$context['attachments'] = array(
				'quantity' => 0,
				'total_size' => 0,
			);
	}

	// Hmm. There are still files in session.
	$ignore_temp = false;
	if (!empty($_SESSION['temp_attachments']['post']['files']) && count($_SESSION['temp_attachments']) > 1)
	{
		// Let's try to keep them. But...
		$ignore_temp = true;
		// If new files are being added. We can't ignore those
		foreach ($_FILES['attachment']['tmp_name'] as $dummy)
		{
			if (!empty($dummy))
			{
				$ignore_temp = false;
				break;
			}
		}

		// Need to make space for the new files. So, bye bye.
		if (!$ignore_temp)
		{
			foreach ($_SESSION['temp_attachments'] as $attachID => $attachment)
			{
				if (strpos($attachID, 'post_tmp_' . $user_info['id']) !== false)
					unlink($attachment['tmp_name']);
			}

			$context['we_are_history'] = $txt['error_temp_attachments_flushed'];
			$_SESSION['temp_attachments'] = array();
		}
	}

	if (!isset($_FILES['attachment']['name']))
		$_FILES['attachment']['tmp_name'] = array();

	if (!isset($_SESSION['temp_attachments']))
		$_SESSION['temp_attachments'] = array();

	// Remember where we are at. If it's anywhere at all.
	if (!$ignore_temp)
		$_SESSION['temp_attachments']['post'] = array(
			'msg' => !empty($id_msg) ? $id_msg : 0,
			'last_msg' => !empty($_REQUEST['last_msg']) ? $_REQUEST['last_msg'] : 0,
			'topic' => !empty($topic) ? $topic : 0,
			'board' => !empty($board) ? $board : 0,
		);

	// If we have an initial error, lets just display it.
	if (!empty($initial_error))
	{
		$_SESSION['temp_attachments']['initial_error'] = $initial_error;

		// And delete the files 'cos they ain't going nowhere.
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
		{
			if (file_exists($_FILES['attachment']['tmp_name'][$n]))
				unlink($_FILES['attachment']['tmp_name'][$n]);
		}

		$_FILES['attachment']['tmp_name'] = array();
	}

	// Loop through $_FILES['attachment'] array and move each file to the current attachments folder.
	foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
	{
		if ($_FILES['attachment']['name'][$n] == '')
			continue;

		// First, let's first check for PHP upload errors.
		$errors = array();
		if (!empty($_FILES['attachment']['error'][$n]))
		{
			if ($_FILES['attachment']['error'][$n] == 2)
				$errors[] = array('file_too_big', array($modSettings['attachmentSizeLimit']));
			elseif ($_FILES['attachment']['error'][$n] == 6)
				log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_6'], 'critical');
			else
				log_error($_FILES['attachment']['name'][$n] . ': ' . $txt['php_upload_error_' . $_FILES['attachment']['error'][$n]]);
			if (empty($errors))
				$errors[] = 'attach_php_error';
		}

		// Try to move and rename the file before doing any more checks on it.
		$attachID = 'post_tmp_' . $user_info['id'] . '_' . md5(mt_rand());
		$destName = $context['attach_dir'] . '/' . $attachID;
		if (empty($errors))
		{
			$_SESSION['temp_attachments'][$attachID] = array(
				'name' => htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
				'tmp_name' => $destName,
				'size' => $_FILES['attachment']['size'][$n],
				'type' => $_FILES['attachment']['type'][$n],
				'id_folder' => $modSettings['currentAttachmentUploadDir'],
				'errors' => array(),
			);

			// Move the file to the attachments folder with a temp name for now.
			if (@move_uploaded_file($_FILES['attachment']['tmp_name'][$n], $destName))
				@chmod($destName, 0644);
			else
			{
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_timeout';
				if (file_exists($_FILES['attachment']['tmp_name'][$n]))
					unlink($_FILES['attachment']['tmp_name'][$n]);
			}
		}
		else
		{
			$_SESSION['temp_attachments'][$attachID] = array(
				'name' => htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
				'tmp_name' => $destName,
				'errors' => $errors,
			);

			if (file_exists($_FILES['attachment']['tmp_name'][$n]))
				unlink($_FILES['attachment']['tmp_name'][$n]);
		}
		// If there's no errors to this pont. We still do need to apply some addtional checks before we are finished.
		if (empty($_SESSION['temp_attachments'][$attachID]['errors']))
			attachmentChecks($attachID);
	}
	// Mod authors, finally a hook to hang an alternate attachment upload system upon
	// Upload to the current attachment folder with the file name $attachID or 'post_tmp_' . $user_info['id'] . '_' . md5(mt_rand())
	// Populate $_SESSION['temp_attachments'][$attachID] with the following:
	//   name => The file name
	//   tmp_name => Path to the temp file ($context['attach_dir'] . '/' . $attachID).
	//   size => File size (required).
	//   type => MIME type (optional if not available on upload).
	//   id_folder => $modSettings['currentAttachmentUploadDir']
	//   errors => An array of errors (use the index of the $txt variable for that error).
	// Template changes can be done using "integrate_upload_template".
	call_integration_hook('integrate_attachment_upload');
}

/**
 * Performs various checks on an uploaded file.
 * - Requires that $_SESSION['temp_attachments'][$attachID] be properly populated.
 *
 * @param int $attachID id of the attachment to check
 */
function attachmentChecks($attachID)
{
	global $modSettings, $context, $attachmentOptions;

	$db = database();

	// No data or missing data .... Not necessarily needed, but in case a mod author missed something.
	if ( empty($_SESSION['temp_attachments'][$attachID]))
		$error = '$_SESSION[\'temp_attachments\'][$attachID]';
	elseif (empty($attachID))
		$error = '$attachID';
	elseif (empty($context['attachments']))
		$error = '$context[\'attachments\']';
	elseif (empty($context['attach_dir']))
		$error = '$context[\'attach_dir\']';

	// Let's get their attention.
	if (!empty($error))
		fatal_lang_error('attach_check_nag', 'debug', array($error));

	// These are the only valid image types.
	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	// Just in case this slipped by the first checks, we stop it here and now
	if ($_SESSION['temp_attachments'][$attachID]['size'] == 0)
	{
		$_SESSION['temp_attachments'][$attachID]['errors'][] = 'attach_0_byte_file';
		return false;
	}

	// First, the dreaded security check. Sorry folks, but this should't be avoided
	$size = @getimagesize($_SESSION['temp_attachments'][$attachID]['tmp_name']);
	if (isset($validImageTypes[$size[2]]))
	{
		require_once(SUBSDIR . '/Graphics.subs.php');
		if (!checkImageContents($_SESSION['temp_attachments'][$attachID]['tmp_name'], !empty($modSettings['attachment_image_paranoid'])))
		{
			// It's bad. Last chance, maybe we can re-encode it?
			if (empty($modSettings['attachment_image_reencode']) || (!reencodeImage($_SESSION['temp_attachments'][$attachID]['tmp_name'], $size[2])))
			{
				// Nothing to do: not allowed or not successful re-encoding it.
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'bad_attachment';
				return false;
			}
			// Success! However, successes usually come for a price:
			// we might get a new format for our image...
			$old_format = $size[2];
			$size = @getimagesize($attachmentOptions['tmp_name']);
			if (!(empty($size)) && ($size[2] != $old_format))
			{
				if (isset($validImageTypes[$size[2]]))
					$_SESSION['temp_attachments'][$attachID]['type'] = 'image/' . $validImageTypes[$size[2]];
			}
		}
	}

	// Is there room for this sucker?
	if (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit']))
	{
		// Check the folder size and count. If it hasn't been done already.
		if (empty($context['dir_size']) || empty($context['dir_files']))
		{
			$request = $db->query('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_folder = {int:folder_id}
					AND attachment_type != {int:type}',
				array(
					'folder_id' => $modSettings['currentAttachmentUploadDir'],
					'type' => 1,
				)
			);
			list ($context['dir_files'], $context['dir_size']) = $db->fetch_row($request);
			$db->free_result($request);
		}
		$context['dir_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
		$context['dir_files']++;

		// Are we about to run out of room? Let's notify the admin then.
		if (empty($modSettings['attachment_full_notified']) && !empty($modSettings['attachmentDirSizeLimit']) && $modSettings['attachmentDirSizeLimit'] > 4000 && $context['dir_size'] > ($modSettings['attachmentDirSizeLimit'] - 2000) * 1024
			|| (!empty($modSettings['attachmentDirFileLimit']) && $modSettings['attachmentDirFileLimit'] * .95 < $context['dir_files'] && $modSettings['attachmentDirFileLimit'] > 500))
		{
			require_once(SUBSDIR . '/Admin.subs.php');
			emailAdmins('admin_attachments_full');
			updateSettings(array('attachment_full_notified' => 1));
		}

		// No room left.... What to do now???
		if (!empty($modSettings['attachmentDirFileLimit']) && $context['dir_files'] + 2 > $modSettings['attachmentDirFileLimit']
			|| (!empty($modSettings['attachmentDirSizeLimit']) && $context['dir_size'] > $modSettings['attachmentDirSizeLimit'] * 1024))
		{
			if (!empty($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] == 1)
			{
				// Move it to the new folder if we can.
				if (automanage_attachments_by_space())
				{
					rename($_SESSION['temp_attachments'][$attachID]['tmp_name'], $context['attach_dir'] . '/' . $attachID);
					$_SESSION['temp_attachments'][$attachID]['tmp_name'] = $context['attach_dir'] . '/' . $attachID;
					$_SESSION['temp_attachments'][$attachID]['id_folder'] = $modSettings['currentAttachmentUploadDir'];
					$context['dir_size'] = 0;
					$context['dir_files'] = 0;
				}
				// Or, let the user know that it ain't gonna happen.
				else
				{
					if (isset($context['dir_creation_error']))
						$_SESSION['temp_attachments'][$attachID]['errors'][] = $context['dir_creation_error'];
					else
						$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
				}
			}
			else
				$_SESSION['temp_attachments'][$attachID]['errors'][] = 'ran_out_of_space';
		}
	}

	// Is the file too big?
	$context['attachments']['total_size'] += $_SESSION['temp_attachments'][$attachID]['size'];
	if (!empty($modSettings['attachmentSizeLimit']) && $_SESSION['temp_attachments'][$attachID]['size'] > $modSettings['attachmentSizeLimit'] * 1024)
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('file_too_big', array(comma_format($modSettings['attachmentSizeLimit'], 0)));

	// Check the total upload size for this post...
	if (!empty($modSettings['attachmentPostLimit']) && $context['attachments']['total_size'] > $modSettings['attachmentPostLimit'] * 1024)
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('attach_max_total_file_size', array(comma_format($modSettings['attachmentPostLimit'], 0), comma_format($modSettings['attachmentPostLimit'] - (($context['attachments']['total_size'] - $_SESSION['temp_attachments'][$attachID]['size']) / 1024), 0)));

	// Have we reached the maximum number of files we are allowed?
	$context['attachments']['quantity']++;

	// Set a max limit if none exists
	if (empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] >= 50)
		$modSettings['attachmentNumPerPostLimit'] = 50;

	if (!empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] > $modSettings['attachmentNumPerPostLimit'])
		$_SESSION['temp_attachments'][$attachID]['errors'][] = array('attachments_limit_per_post', array($modSettings['attachmentNumPerPostLimit']));

	// File extension check
	if (!empty($modSettings['attachmentCheckExtensions']))
	{
		$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
		foreach ($allowed as $k => $dummy)
			$allowed[$k] = trim($dummy);

		if (!in_array(strtolower(substr(strrchr($_SESSION['temp_attachments'][$attachID]['name'], '.'), 1)), $allowed))
		{
			$allowed_extensions = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
			$_SESSION['temp_attachments'][$attachID]['errors'][] = array('cant_upload_type', array($allowed_extensions));
		}
	}

	// Undo the math if there's an error
	if (!empty($_SESSION['temp_attachments'][$attachID]['errors']))
	{
		if (isset($context['dir_size']))
			$context['dir_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
		if (isset($context['dir_files']))
			$context['dir_files']--;
		$context['attachments']['total_size'] -= $_SESSION['temp_attachments'][$attachID]['size'];
		$context['attachments']['quantity']--;
		return false;
	}

	return true;
}

/**
 * Create an attachment, with the given array of parameters.
 * - Adds any addtional or missing parameters to $attachmentOptions.
 * - Renames the temporary file.
 * - Creates a thumbnail if the file is an image and the option enabled.
 *
 * @param array $attachmentOptions
 */
function createAttachment(&$attachmentOptions)
{
	global $modSettings, $context;

	$db = database();

	require_once(SUBSDIR . '/Graphics.subs.php');

	// These are the only valid image types.
	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	// If this is an image we need to set a few additional parameters.
	$size = @getimagesize($attachmentOptions['tmp_name']);
	list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;

	// If it's an image get the mime type right.
	if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
	{
		// Got a proper mime type?
		if (!empty($size['mime']))
			$attachmentOptions['mime_type'] = $size['mime'];
		// Otherwise a valid one?
		elseif (isset($validImageTypes[$size[2]]))
			$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
	}

	// Get the hash if no hash has been given yet.
	if (empty($attachmentOptions['file_hash']))
		$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], false, null, true);

	// Assuming no-one set the extension let's take a look at it.
	if (empty($attachmentOptions['fileext']))
	{
		$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
		if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] == $attachmentOptions['name'])
			$attachmentOptions['fileext'] = '';
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
	$attachmentOptions['id'] = $db->insert_id('{db_prefix}attachments', 'id_attach');

	// @todo Add an error here maybe?
	if (empty($attachmentOptions['id']))
		return false;

	// Now that we have the attach id, let's rename this sucker and finish up.
	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $attachmentOptions['id_folder'], false, $attachmentOptions['file_hash']);
	rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

	// If it's not approved then add to the approval queue.
	if (!$attachmentOptions['approved'])
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

	if (empty($modSettings['attachmentThumbnails']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
		return true;

	// Like thumbnails, do we?
	if (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachmentOptions['width'] > $modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > $modSettings['attachmentThumbHeight']))
	{
		if (createThumbnail($attachmentOptions['destination'], $modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight']))
		{
			// Figure out how big we actually made it.
			$size = @getimagesize($attachmentOptions['destination'] . '_thumb');
			list ($thumb_width, $thumb_height) = $size;

			if (!empty($size['mime']))
				$thumb_mime = $size['mime'];
			elseif (isset($validImageTypes[$size[2]]))
				$thumb_mime = 'image/' . $validImageTypes[$size[2]];
			// Lord only knows how this happened...
			else
				$thumb_mime = '';

			$thumb_filename = $attachmentOptions['name'] . '_thumb';
			$thumb_size = filesize($attachmentOptions['destination'] . '_thumb');
			$thumb_file_hash = getAttachmentFilename($thumb_filename, false, null, true);
			$thumb_path = $attachmentOptions['destination'] . '_thumb';

			// We should check the file size and count here since thumbs are added to the existing totals.
			if (!empty($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] == 1 && !empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit']))
			{
				$context['dir_size'] = isset($context['dir_size']) ? $context['dir_size'] += $thumb_size : $context['dir_size'] = 0;
				$context['dir_files'] = isset($context['dir_files']) ? $context['dir_files']++ : $context['dir_files'] = 0;

				// If the folder is full, try to create a new one and move the thumb to it.
				if ($context['dir_size'] > $modSettings['attachmentDirSizeLimit'] * 1024 || $context['dir_files'] + 2 > $modSettings['attachmentDirFileLimit'])
				{
					if (automanage_attachments_by_space())
					{
						rename($thumb_path, $context['attach_dir'] . '/' . $thumb_filename);
						$thumb_path = $context['attach_dir'] . '/' . $thumb_filename;
						$context['dir_size'] = 0;
						$context['dir_files'] = 0;
					}
				}
			}
			// If a new folder has been already created. Gotta move this thumb there then.
			if ($modSettings['currentAttachmentUploadDir'] != $attachmentOptions['id_folder'])
			{
				rename($thumb_path, $context['attach_dir'] . '/' . $thumb_filename);
				$thumb_path = $context['attach_dir'] . '/' . $thumb_filename;
			}

			// To the database we go!
			$db->insert('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20', 'approved' => 'int',
				),
				array(
					$modSettings['currentAttachmentUploadDir'], (int) $attachmentOptions['post'], 3, $thumb_filename, $thumb_file_hash, $attachmentOptions['fileext'],
					$thumb_size, $thumb_width, $thumb_height, $thumb_mime, (int) $attachmentOptions['approved'],
				),
				array('id_attach')
			);
			$attachmentOptions['thumb'] = $db->insert_id('{db_prefix}attachments', 'id_attach');

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

				rename($thumb_path, getAttachmentFilename($thumb_filename, $attachmentOptions['thumb'], $modSettings['currentAttachmentUploadDir'], false, $thumb_file_hash));
			}
		}
	}
	return true;
}

/**
 * Get the avatar with the specified ID.
 * It gets avatar data (folder, name of the file, filehash, etc)
 * from the database.
 *
 * @param int $id_attach
 * @return array, the avatar data as array
*/
function getAvatar($id_attach)
{
	$db = database();

	// Use our cache when possible
	if (($cache = cache_get_data('getAvatar_id-' . $id_attach)) !== null)
		$avatarData = $cache;
	else
	{
		$request = $db->query('', '
			SELECT id_folder, filename, file_hash, fileext, id_attach, attachment_type, mime_type, approved, id_member
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}
				AND id_member > {int:blank_id_member}
			LIMIT 1',
			array(
				'id_attach' => $id_attach,
				'blank_id_member' => 0,
			)
		);

		$avatarData = array();
		if ($db->num_rows($request) != 0)
			$avatarData = $db->fetch_row($request);
		$db->free_result($request);

		cache_put_data('getAvatar_id-' . $id_attach, $avatarData, 900);
	}

	return $avatarData;
}

/**
 * Get the specified attachment. This includes a check of the topic
 * (it only returns the attachment if it's indeed attached to a message
 * in the topic given as parameter), and query_see_board...
 *
 * @param int $id_attach
 * @param int $id_topic
*/
function getAttachmentFromTopic($id_attach, $id_topic)
{
	$db = database();

	// Make sure this attachment is on this board.

	$request = $db->query('', '
		SELECT a.id_folder, a.filename, a.file_hash, a.fileext, a.attachment_type, a.mime_type, a.approved, m.id_member
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

	$attachmentData = array();
	if ($db->num_rows($request) != 0)
		$attachmentData = $db->fetch_row($request);
	$db->free_result($request);

	return $attachmentData;
}

/**
 * Increase download counter for id_attach.
 * Does not check if it's a thumbnail.
 *
 * @param int $id_attach
*/
function increaseDownloadCounter($id_attach)
{
	$db = database();

	$db->query('attach_download_increase', '
		UPDATE LOW_PRIORITY {db_prefix}attachments
		SET downloads = downloads + 1
		WHERE id_attach = {int:id_attach}',
		array(
			'id_attach' => $id_attach,
		)
	);
}

/**
 * Approve an attachment, or maybe even more - no permission check!
 *
 * @param array $attachments
 */
function approveAttachments($attachments)
{
	$db = database();

	if (empty($attachments))
		return 0;

	// For safety, check for thumbnails...
	$request = $db->query('', '
		SELECT
			a.id_attach, a.id_member, IFNULL(thumb.id_attach, 0) AS id_thumb
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
		)
	);
	$attachments = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Update the thumbnail too...
		if (!empty($row['id_thumb']))
			$attachments[] = $row['id_thumb'];

		$attachments[] = $row['id_attach'];
	}
	$db->free_result($request);

	if (empty($attachments))
		return 0;

	// Approving an attachment is not hard - it's easy.
	$db->query('', '
		UPDATE {db_prefix}attachments
		SET approved = {int:is_approved}
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
			'is_approved' => 1,
		)
	);

	// In order to log the attachments, we really need their message and filename
	$request = $db->query('', '
		SELECT m.id_msg, a.filename
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
		)
	);

	while ($row = $db->fetch_assoc($request))
		logAction(
			'approve_attach',
			array(
				'message' => $row['id_msg'],
				'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Util::htmlspecialchars($row['filename'])),
			)
		);
	$db->free_result($request);

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
 * Called by remove avatar/attachment functions.
 * It removes attachments based that match the $condition.
 * It allows query_types 'messages' and 'members', whichever is need by the
 * $condition parameter.
 * It does no permissions check.
 *
 * @param array $condition
 * @param string $query_type
 * @param bool $return_affected_messages = false
 * @param bool $autoThumbRemoval = true
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
			$is_not = substr($real_type, 0, 4) == 'not_';
			$type = $is_not ? substr($real_type, 4) : $real_type;

			if (in_array($type, array('id_member', 'id_attach', 'id_msg')))
				$new_condition[] = 'a.' . $type . ($is_not ? ' NOT' : '') . ' IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';
			elseif ($type == 'attachment_type')
				$new_condition[] = 'a.attachment_type = {int:' . $real_type . '}';
			elseif ($type == 'poster_time')
				$new_condition[] = 'm.poster_time < {int:' . $real_type . '}';
			elseif ($type == 'last_login')
				$new_condition[] = 'mem.last_login < {int:' . $real_type . '}';
			elseif ($type == 'size')
				$new_condition[] = 'a.size > {int:' . $real_type . '}';
			elseif ($type == 'id_topic')
				$new_condition[] = 'm.id_topic IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';

			// Add the parameter!
			$query_parameter[$real_type] = $restriction;

			if ($type == 'do_logging')
				$do_logging = $condition['id_attach'];
		}
		$condition = implode(' AND ', $new_condition);
	}

	// Delete it only if it exists...
	$msgs = array();
	$attach = array();
	$parents = array();

	// Get all the attachment names and id_msg's.
	$request = $db->query('', '
		SELECT
			a.id_folder, a.filename, a.file_hash, a.attachment_type, a.id_attach, a.id_member' . ($query_type == 'messages' ? ', m.id_msg' : ', a.id_msg') . ',
			thumb.id_folder AS thumb_folder, IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.filename AS thumb_filename, thumb.file_hash AS thumb_file_hash, thumb_parent.id_attach AS id_parent
		FROM {db_prefix}attachments AS a' .($query_type == 'members' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)' : ($query_type == 'messages' ? '
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)' : '')) . '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
			LEFT JOIN {db_prefix}attachments AS thumb_parent ON (thumb.attachment_type = {int:thumb_attachment_type} AND thumb_parent.id_thumb = a.id_attach)
		WHERE ' . $condition,
		$query_parameter
	);
	while ($row = $db->fetch_assoc($request))
	{
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
				$parents[] = $row['id_parent'];

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
			$msgs[] = $row['id_msg'];
		$attach[] = $row['id_attach'];
	}
	$db->free_result($request);

	// Removed attachments don't have to be updated anymore.
	$parents = array_diff($parents, $attach);
	if (!empty($parents))
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET id_thumb = {int:no_thumb}
			WHERE id_attach IN ({array_int:parent_attachments})',
			array(
				'parent_attachments' => $parents,
				'no_thumb' => 0,
			)
		);

	if (!empty($do_logging))
	{
		// In order to log the attachments, we really need their message and filename
		$request = $db->query('', '
			SELECT m.id_msg, a.filename
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			WHERE a.id_attach IN ({array_int:attachments})
				AND a.attachment_type = {int:attachment_type}',
			array(
				'attachments' => $do_logging,
				'attachment_type' => 0,
			)
		);

		while ($row = $db->fetch_assoc($request))
			logAction(
				'remove_attach',
				array(
					'message' => $row['id_msg'],
					'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', Util::htmlspecialchars($row['filename'])),
				)
			);
		$db->free_result($request);
	}

	if (!empty($attach))
		$db->query('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $attach,
			)
		);

	call_integration_hook('integrate_remove_attachments', array($attach));

	if ($return_affected_messages)
		return array_unique($msgs);
}

/**
 * Saves a file and stores it locally for avatar use by id_member.
 * - supports GIF, JPG, PNG, BMP and WBMP formats.
 * - detects if GD2 is available.
 * - uses resizeImageFile() to resize to max_width by max_height, and saves the result to a file.
 * - updates the database info for the member's avatar.
 * - returns whether the download and resize was successful.
 * @uses subs/Graphics.subs.php
 *
 * @param string $temporary_path the full path to the temporary file
 * @param int $memID member ID
 * @param int $max_width
 * @param int $max_height
 * @return boolean whether the download and resize was successful.
 *
 */
function saveAvatar($temporary_path, $memID, $max_width, $max_height)
{
	global $modSettings;

	$db = database();

	$ext = !empty($modSettings['avatar_download_png']) ? 'png' : 'jpeg';
	$destName = 'avatar_' . $memID . '_' . time() . '.' . $ext;

	// Just making sure there is a non-zero member.
	if (empty($memID))
		return false;

	removeAttachments(array('id_member' => $memID));

	$id_folder = getAttachmentPathID();
	$avatar_hash = empty($modSettings['custom_avatar_enabled']) ? getAttachmentFilename($destName, false, null, true) : '';
	$db->insert('',
		'{db_prefix}attachments',
		array(
			'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-255', 'fileext' => 'string-8', 'size' => 'int',
			'id_folder' => 'int',
		),
		array(
			$memID, empty($modSettings['custom_avatar_enabled']) ? 0 : 1, $destName, $avatar_hash, $ext, 1,
			$id_folder,
		),
		array('id_attach')
	);
	$attachID = $db->insert_id('{db_prefix}attachments', 'id_attach');

	// First, the temporary file will have the .tmp extension.
	$tempName = getAvatarPath() . '/' . $destName . '.tmp';

	// The destination filename will depend on whether custom dir for avatars has been set
	$destName = getAvatarPath() . '/' . $destName;
	$path = getAttachmentPath();
	$destName = empty($avatar_hash) ? $destName : $path . '/' . $attachID . '_' . $avatar_hash;

	// Resize it.
	require_once(SUBSDIR . '/Graphics.subs.php');
	if (!empty($modSettings['avatar_download_png']))
		$success = resizeImageFile($temporary_path, $tempName, $max_width, $max_height, 3);
	else
		$success = resizeImageFile($temporary_path, $tempName, $max_width, $max_height);

	if ($success)
	{
		// Remove the .tmp extension from the attachment.
		if (rename($tempName, $destName))
		{
			list ($width, $height) = getimagesize($destName);
			$mime_type = 'image/' . $ext;

			// Write filesize in the database.
			$db->query('', '
				UPDATE {db_prefix}attachments
				SET size = {int:filesize}, width = {int:width}, height = {int:height},
					mime_type = {string:mime_type}
				WHERE id_attach = {int:current_attachment}',
				array(
					'filesize' => filesize($destName),
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
				'type' => empty($modSettings['custom_avatar_enabled']) ? 0 : 1,
			);
			return true;
		}
		else
			return false;
	}
	else
	{
		$db->query('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach = {int:current_attachment}',
			array(
				'current_attachment' => $attachID,
			)
		);

		@unlink($tempName);
		return false;
	}
}

/**
 * Get the size of a specified image with better error handling.
 * @todo see if it's better in subs/Graphics.subs.php, but one step at the time.
 * Uses getimagesize() to determine the size of a file.
 * Attempts to connect to the server first so it won't time out.
 *
 * @param string $url
 * @return array or false, the image size as array (width, height), or false on failure
 */
function url_image_size($url)
{
	// Make sure it is a proper URL.
	$url = str_replace(' ', '%20', $url);

	// Can we pull this from the cache... please please?
	if (($temp = cache_get_data('url_image_size-' . md5($url), 240)) !== null)
		return $temp;
	$t = microtime(true);

	// Get the host to pester...
	preg_match('~^\w+://(.+?)/(.*)$~', $url, $match);

	// Can't figure it out, just try the image size.
	if ($url == '' || $url == 'http://' || $url == 'https://')
	{
		return false;
	}
	elseif (!isset($match[1]))
	{
		$size = @getimagesize($url);
	}
	else
	{
		// Try to connect to the server... give it half a second.
		$temp = 0;
		$fp = @fsockopen($match[1], 80, $temp, $temp, 0.5);

		// Successful?  Continue...
		if ($fp != false)
		{
			// Send the HEAD request (since we don't have to worry about chunked, HTTP/1.1 is fine here.)
			fwrite($fp, 'HEAD /' . $match[2] . ' HTTP/1.1' . "\r\n" . 'Host: ' . $match[1] . "\r\n" . 'User-Agent: PHP/ELK' . "\r\n" . 'Connection: close' . "\r\n\r\n");

			// Read in the HTTP/1.1 or whatever.
			$test = substr(fgets($fp, 11), -1);
			fclose($fp);

			// See if it returned a 404/403 or something.
			if ($test < 4)
			{
				$size = @getimagesize($url);

				// This probably means allow_url_fopen is off, let's try GD.
				if ($size === false && function_exists('imagecreatefromstring'))
				{
					include_once(SUBSDIR . '/Package.subs.php');

					// It's going to hate us for doing this, but another request...
					$image = @imagecreatefromstring(fetch_web_data($url));
					if ($image !== false)
					{
						$size = array(imagesx($image), imagesy($image));
						imagedestroy($image);
					}
				}
			}
		}
	}

	// If we didn't get it, we failed.
	if (!isset($size))
		$size = false;

	// If this took a long time, we may never have to do it again, but then again we might...
	if (microtime(true) - $t > 0.8)
		cache_put_data('url_image_size-' . md5($url), $size, 240);

	// Didn't work.
	return $size;
}

/**
 * The current attachments path:
 *  - BOARDDIR . '/attachments', if nothing is set yet.
 *  - if the forum is using multiple attachments directories,
 *    then the current path is stored as unserialize($modSettings['attachmentUploadDir'])[$modSettings['currentAttachmentUploadDir']]
 *  - otherwise, the current path is $modSettings['attachmentUploadDir'].
 */
function getAttachmentPath()
{
	global $modSettings;

	// Make sure this thing exists and it is unserialized
	if (empty($modSettings['attachmentUploadDir']))
		$attachmentDir = BOARDDIR . '/attachments';
	elseif (!empty($modSettings['currentAttachmentUploadDir']) && !is_array($modSettings['attachmentUploadDir']))
		$attachmentDir = unserialize($modSettings['attachmentUploadDir']);
	else
		$attachmentDir = $modSettings['attachmentUploadDir'];

	return is_array($attachmentDir) ? $attachmentDir[$modSettings['currentAttachmentUploadDir']] : $attachmentDir;
}

/**
 * Return an array of attachments directories.
 * @see getAttachmentPath()
 */
function attachmentPaths()
{
	global $modSettings, $boarddir;

	if (empty($modSettings['attachmentUploadDir']))
		return array($boarddir . '/attachments');
	elseif (!empty($modSettings['currentAttachmentUploadDir']))
	{
		// we have more directories
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

		return $modSettings['attachmentUploadDir'];
	}
	else
		return array($modSettings['attachmentUploadDir']);
}

/**
 * The avatars path: if custom avatar directory is set, that's it.
 * Otherwise, it's attachments path.
 */
function getAvatarPath()
{
	global $modSettings;

	return empty($modSettings['custom_avatar_enabled']) ? getAttachmentPath() : $modSettings['custom_avatar_dir'];
}

/**
 * Little utility function for the $id_folder computation for attachments.
 * This returns the id of the folder where the attachment or avatar will be saved.
 * If multiple attachment directories are not enabled, this will be 1 by default.
 *
 * @return int, return 1 if multiple attachment directories are not enabled,
 * or the id of the current attachment directory otherwise.
 */
function getAttachmentPathID()
{
	global $modSettings;

	// utility function for the endless $id_folder computation for attachments.
	return !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['currentAttachmentUploadDir'] : 1;
}

/**
 * Returns the ID of the folder avatars are currently saved in.
 *
 * @return int, returns 1 if custom avatar directory is enabled,
 * and the ID of the current attachment folder otherwise.
 * NB: the latter could also be 1.
 */
function getAvatarPathID()
{
	global $modSettings;

	// Little utility function for the endless $id_folder computation for avatars.
	if (!empty($modSettings['custom_avatar_enabled']))
		return 1;
	else
		return getAttachmentPathID();
}

/**
 * Get all attachments associated with a set of posts.
 * This does not check permissions.
 *
 * @param array $messages array of messages ids
 * @param bool $includeUnapproved = false
 */
function getAttachments($messages, $includeUnapproved = false, $filter = null, $all_posters = array())
{
	global $modSettings;

	$db = database();

	$attachments = array();
	$request = $db->query('', '
		SELECT
			a.id_attach, a.id_folder, a.id_msg, a.filename, a.file_hash, IFNULL(a.size, 0) AS filesize, a.downloads, a.approved,
			a.width, a.height' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ',
			IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
    	FROM {db_prefix}attachments AS a' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
		WHERE a.id_msg IN ({array_int:message_list})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'message_list' => $messages,
			'attachment_type' => 0,
		)
	);
	$temp = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!$row['approved'] && !$includeUnapproved && (empty($filter) || !call_user_func($filter, $row, $all_posters)))
			continue;

		$temp[$row['id_attach']] = $row;

		if (!isset($attachments[$row['id_msg']]))
			$attachments[$row['id_msg']] = array();
	}
	$db->free_result($request);

	// This is better than sorting it with the query...
	ksort($temp);

	foreach ($temp as $row)
		$attachments[$row['id_msg']][] = $row;

	return $attachments;
}

/**
 * How many attachments we have overall.
 *
 * @return int
 */
function getAttachmentCount()
{
	$db = database();

	// Get the number of attachments....
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member = {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);
	list ($num_attachments) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_attachments;
}

/**
 * How many avatars do we have. Need to know. :P
 *
 * @return int
 */
function getAvatarCount()
{
	$db = database();

	// Get the avatar amount....
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_member != {int:guest_id_member}',
		array(
			'guest_id_member' => 0,
		)
	);
	list ($num_avatars) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_avatars;
}

/**
 * Get the attachments directories, as an array.
 *
 * @return array, the attachments directory/directories
 */
function getAttachmentDirs()
{
	global $modSettings;

	if (!empty($modSettings['currentAttachmentUploadDir']))
		$attach_dirs = unserialize($modSettings['attachmentUploadDir']);
	elseif (!empty($modSettings['attachmentUploadDir']))
		$attach_dirs = array($modSettings['attachmentUploadDir']);
	else
		$attach_dirs = array(BOARDDIR . '/attachments');

	return $attach_dirs;
}

/**
 * Get all avatars information... as long as they're in default directory still?
 *
 * @return array, avatars information
 */
function getAvatarsDefault()
{
	$db = database();

	$request = $db->query('', '
		SELECT id_attach, id_folder, id_member, filename, file_hash
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member > {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);

	$avatars = array();
	while ($row = $db->fetch_assoc($request))
		$avatars[] = $row;
	$db->free_result($request);

	return $avatars;
}

/**
 * Recursive function to retrieve server-stored avatar files
 *
 * @param string $directory
 * @param int $level
 * @return array
 */
function getServerStoredAvatars($directory, $level)
{
	global $context, $txt, $modSettings;

	$result = array();

	// Open the directory..
	$dir = dir($modSettings['avatar_directory'] . (!empty($directory) ? '/' : '') . $directory);
	$dirs = array();
	$files = array();

	if (!$dir)
		return array();

	while ($line = $dir->read())
	{
		if (in_array($line, array('.', '..', 'blank.png', 'index.php')))
			continue;

		if (is_dir($modSettings['avatar_directory'] . '/' . $directory . (!empty($directory) ? '/' : '') . $line))
			$dirs[] = $line;
		else
			$files[] = $line;
	}
	$dir->close();

	// Sort the results...
	natcasesort($dirs);
	natcasesort($files);

	if ($level == 0)
	{
		$result[] = array(
			'filename' => 'blank.png',
			'checked' => in_array($context['member']['avatar']['server_pic'], array('', 'blank.png')),
			'name' => $txt['no_pic'],
			'is_dir' => false
		);
	}

	foreach ($dirs as $line)
	{
		$tmp = getServerStoredAvatars($directory . (!empty($directory) ? '/' : '') . $line, $level + 1);
		if (!empty($tmp))
		$result[] = array(
				'filename' => htmlspecialchars($line),
				'checked' => strpos($context['member']['avatar']['server_pic'], $line . '/') !== false,
				'name' => '[' . htmlspecialchars(str_replace('_', ' ', $line)) . ']',
				'is_dir' => true,
				'files' => $tmp
		);
		unset($tmp);
	}

	foreach ($files as $line)
	{
		$filename = substr($line, 0, (strlen($line) - strlen(strrchr($line, '.'))));
		$extension = substr(strrchr($line, '.'), 1);

		// Make sure it is an image.
		if (strcasecmp($extension, 'gif') != 0 && strcasecmp($extension, 'jpg') != 0 && strcasecmp($extension, 'jpeg') != 0 && strcasecmp($extension, 'png') != 0 && strcasecmp($extension, 'bmp') != 0)
			continue;

		$result[] = array(
			'filename' => htmlspecialchars($line),
			'checked' => $line == $context['member']['avatar']['server_pic'],
			'name' => htmlspecialchars(str_replace('_', ' ', $filename)),
			'is_dir' => false
		);
		if ($level == 1)
			$context['avatar_list'][] = $directory . '/' . $line;
	}

	return $result;
}

/**
 * Simple function to remove the strictly needed of orphan attachments.
 * This is used from attachments maintenance.
 * It assumes the files have no message, no member information.
 * It only removes the attachments and thumbnails from the database.
 *
 * @param array $attach_ids
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
			SET id_thumb = {int:no_thumb}
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
 * @param int $filesize = null
 */
function attachment_filesize($attach_id, $filesize = null)
{
	$db = database();

	if ($filesize === null)
	{
		$result = $db->query('', '
			SELECT size
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attach_id,
			)
		);
		if (!empty($result))
		{
			list($filesize) = $db->fetch_row($result);
			$db->free_result($result);
			return $filesize;
		}
		return false;
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET size = {int:filesize}
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
 * @param int $folder_id = null
 */
function attachment_folder($attach_id, $folder_id = null)
{
	$db = database();

	if ($folder_id === null)
	{
		$result = $db->query('', '
			SELECT id_folder
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attach_id,
			)
		);
		if (!empty($result))
		{
			list($folder_id) = $db->fetch_row($result);
			$db->free_result($result);
			return $folder_id;
		}
		return false;
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET id_folder = {int:new_folder}
			WHERE id_attach = {int:id_attach}',
			array(
				'new_folder' => $folder_id,
				'id_attach' => $attach_id,
			)
		);
	}
}

/**
 * Get max attachment ID with a thumbnail.
 */
function maxThumbnails()
{
	$db = database();

	$result = $db->query('', '
		SELECT MAX(id_attach)
		FROM {db_prefix}attachments
		WHERE id_thumb != {int:no_thumb}',
		array(
			'no_thumb' => 0,
		)
	);
	list ($thumbnails) = $db->fetch_row($result);
	$db->free_result($result);

	return $thumbnails;
}

/**
 * Check multiple attachments IDs against the database.
 *
 * @param array $attachments
 * @param string $approve_query
 */
function validateAttachments($attachments, $approve_query)
{
	$db = database();

	// double check the attachments array, pick only what is returned from the database
	$request = $db->query('', '
		SELECT a.id_attach, m.id_board, m.id_msg, m.id_topic
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
	);
	$attachments = array();
	while ($row = $db->fetch_assoc($request))
		$attachments[] = $row['id_attach'];
	$db->free_result($request);

	return $attachments;
}

/**
 * Callback function for action_unapproved_attachments
 * retrieve all the attachments waiting for approval the approver can approve
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $approve_query additional restrictions based on the boards the approver can see
 * @return array, an array of unapproved attachments
 */
function list_getUnapprovedAttachments($start, $items_per_page, $sort, $approve_query)
{
	global $scripturl;

	$db = database();

	// Get all unapproved attachments.
	$request = $db->query('', '
		SELECT a.id_attach, a.filename, a.size, m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
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
	);

	$unapproved_items = array();
	while ($row = $db->fetch_assoc($request))
	{
		$unapproved_items[] = array(
			'id' => $row['id_attach'],
			'filename' => $row['filename'],
			'size' => round($row['size'] / 1024, 2),
			'time' => standardTime($row['poster_time']),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			),
			'message' => array(
				'id' => $row['id_msg'],
				'subject' => $row['subject'],
				'body' => parse_bbc($row['body']),
				'time' => standardTime($row['poster_time']),
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
	$db->free_result($request);

	return $unapproved_items;
}

/**
 * Callback function for action_unapproved_attachments
 * count all the attachments waiting for approval that this approver can approve
 *
 * @param string $approve_query additional restrictions based on the boards the approver can see
 * @return int the number of unapproved attachments
 */
function list_getNumUnapprovedAttachments($approve_query)
{
	$db = database();

	// How many unapproved attachments in total?
	$request = $db->query('', '
		SELECT COUNT(*)
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
	list ($total_unapproved_attachments) = $db->fetch_row($request);
	$db->free_result($request);

	return $total_unapproved_attachments;
}

/**
 * Prepare the actual attachment directories to be displayed in the list.
 * Callback function for createList().
 */
function list_getAttachDirs()
{
	global $modSettings, $context, $txt;

	$db = database();

	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		$expected_files[$row['id_folder']] = $row['num_attach'];
		$expected_size[$row['id_folder']] = $row['size_attach'];
	}
	$db->free_result($request);

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
			'disable_base_dir' =>  $is_base_dir && $sub_dirs > 0 && !empty($files) && empty($error),
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
	$db = database();

	// Depending on the type of file, different queries are used.
	if ($browse_type === 'avatars')
		$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_member != {int:guest_id_member}',
		array(
			'guest_id_member' => 0,
		)
	);
	else
		$request = $db->query('', '
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

	list ($num_files) = $db->fetch_row($request);
	$db->free_result($request);

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
	global $txt;

	$db = database();

	// Choose a query depending on what we are viewing.
	if ($browse_type === 'avatars')
		$request = $db->query('', '
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
		$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$files[] = $row;
	$db->free_result($request);

	return $files;
}

/**
 * Return the overall attachments size
 *
 * @return string
 */
function overallAttachmentsSize()
{
	$db = database();

	// Check the size of all the directories.
	$request = $db->query('', '
		SELECT SUM(size)
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}',
		array(
			'type' => 1,
		)
	);
	list ($attachmentDirSize) = $db->fetch_row($request);
	$db->free_result($request);

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
	global $modSettings;

	$db = database();

	$current_dir = array();

	$request = $db->query('', '
		SELECT COUNT(*), SUM(size)
		FROM {db_prefix}attachments
		WHERE id_folder = {int:folder_id}
			AND attachment_type != {int:type}',
		array(
			'folder_id' => $modSettings['currentAttachmentUploadDir'],
			'type' => 1,
		)
	);
	list ($current_dir['files'], $current_dir['size']) = $db->fetch_row($request);
	$db->free_result($request);
	$current_dir['size'] /= 1024;

	return $current_dir;
}

/**
 * Move avatars to their new directory.
 */
function moveAvatars()
{
	global $modSettings;

	$db = database();

	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

		if (rename($filename, $modSettings['custom_avatar_dir'] . '/' . $row['filename']))
			$updatedAvatars[] = $row['id_attach'];
	}
	$db->free_result($request);

	if (!empty($updatedAvatars))
		$db->query('', '
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
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}messages
		SET body = CONCAT(body, {string:notice})
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $messages,
			'notice' => '<br /><br />' . $notice,
		)
	);
}

/**
 * Update an attachment's thumbnail
 *
 * @param string $filename
 * @param int $id_attach
 * @param int $id_msg
 * @param int $old_id_thumb = 0
 *
 * @return array The updated information
 */
function updateAttachmentThumbnail($filename, $id_attach, $id_msg, $old_id_thumb = 0)
{
	global $modSettings;

	$attachment = array('id_attach' => $id_attach);

	require_once(SUBSDIR . '/Graphics.subs.php');
	if (createThumbnail($filename, $modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight']))
	{
		// So what folder are we putting this image in?
		$id_folder_thumb = getAttachmentPathID();

		// Calculate the size of the created thumbnail.
		$size = @getimagesize($filename . '_thumb');
		list ($attachment['thumb_width'], $attachment['thumb_height']) = $size;
		$thumb_size = filesize($filename . '_thumb');

		// These are the only valid image types.
		$validImageTypes = array(1 => 'gif', 2 => 'jpeg', 3 => 'png', 5 => 'psd', 6 => 'bmp', 7 => 'tiff', 8 => 'tiff', 9 => 'jpeg', 14 => 'iff');

		// What about the extension?
		$thumb_ext = isset($validImageTypes[$size[2]]) ? $validImageTypes[$size[2]] : '';

		// Figure out the mime type.
		if (!empty($size['mime']))
			$thumb_mime = $size['mime'];
		else
			$thumb_mime = 'image/' . $thumb_ext;

		$thumb_filename = $filename . '_thumb';
		$thumb_hash = getAttachmentFilename($thumb_filename, false, null, true);

		$db = database();

		// Add this beauty to the database.
		$db->insert('',
			'{db_prefix}attachments',
			array('id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'size' => 'int', 'width' => 'int', 'height' => 'int', 'fileext' => 'string', 'mime_type' => 'string'),
			array($id_folder_thumb, $id_msg, 3, $thumb_filename, $thumb_hash, (int) $thumb_size, (int) $attachment['thumb_width'], (int) $attachment['thumb_height'], $thumb_ext, $thumb_mime),
			array('id_attach')
		);

		$attachment['id_thumb'] = $db->insert_id('{db_prefix}attachments', 'id_attach');
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
			rename($filename . '_thumb', $thumb_realname);

			// Do we need to remove an old thumbnail?
			if (!empty($old_id_thumb))
			{
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
 *
 * @return array
 */
function attachmentsSizeForMessage($id_msg, $include_count = true)
{
	$db = database();

	if ($include_count)
	{
		$request = $db->query('', '
			SELECT COUNT(*), SUM(size)
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
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE id_msg = {int:id_msg}
				AND attachment_type = {int:attachment_type}',
			array(
				'id_msg' => $id_msg,
				'attachment_type' => 0,
			)
		);
	}
	$size = $db->fetch_row($request);
	$db->free_result($request);

	return $size;
}

/**
 * Finds all the attachments of a single message.
 *
 * @param int $id_msg
 * @param bool $unapproved if true returns also the unapproved attachments (default false)
 *
 * @todo $unapproved may be superfluous
 *
 * @return array
 */
function attachmentsOfMessage($id_msg, $unapproved = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_attach
		FROM {db_prefix}attachments
		WHERE id_msg = {int:id_msg}' . ($unapproved ? '' : '
			AND approved = {int:is_approved}') . '
			AND attachment_type = {int:attachment_type}',
		array(
			'id_msg' => $id_msg,
			'is_approved' => 0,
			'attachment_type' => 0,
		)
	);
	$attachments = array();
	while ($row = $db->fetch_assoc($request))
		$attachments[] = $row['id_attach'];
	$db->free_result($request);

	return $attachments;
}

/**
 * This loads an attachment's contextual data including, most importantly, its size if it is an image.
 * Pre-condition: $attachments array to have been filled with the proper attachment data, as Display() does.
 * (@todo change this pre-condition, too fragile and error-prone.)
 * It requires the view_attachments permission to calculate image size.
 * It attempts to keep the "aspect ratio" of the posted image in line, even if it has to be resized by
 * the max_image_width and max_image_height settings.
 *
 * @param type $id_msg message number to load attachments for
 * @return array of attachments
 */
function loadAttachmentContext($id_msg)
{
	global $attachments, $modSettings, $txt, $scripturl, $topic;

	// Set up the attachment info - based on code by Meriadoc.
	$attachmentData = array();
	$have_unapproved = false;
	if (isset($attachments[$id_msg]) && !empty($modSettings['attachmentEnable']))
	{
		foreach ($attachments[$id_msg] as $i => $attachment)
		{
			$attachmentData[$i] = array(
				'id' => $attachment['id_attach'],
				'name' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($attachment['filename'])),
				'downloads' => $attachment['downloads'],
				'size' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . $txt['megabyte'],
				'byte_size' => $attachment['filesize'],
				'href' => $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'],
				'link' => '<a href="' . $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'] . '">' . htmlspecialchars($attachment['filename']) . '</a>',
				'is_image' => !empty($attachment['width']) && !empty($attachment['height']) && !empty($modSettings['attachmentShowImages']),
				'is_approved' => $attachment['approved'],
			);

			// If something is unapproved we'll note it so we can sort them.
			if (!$attachment['approved'])
				$have_unapproved = true;

			if (!$attachmentData[$i]['is_image'])
				continue;

			$attachmentData[$i]['real_width'] = $attachment['width'];
			$attachmentData[$i]['width'] = $attachment['width'];
			$attachmentData[$i]['real_height'] = $attachment['height'];
			$attachmentData[$i]['height'] = $attachment['height'];

			// Let's see, do we want thumbs?
			if (!empty($modSettings['attachmentThumbnails']) && !empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachment['width'] > $modSettings['attachmentThumbWidth'] || $attachment['height'] > $modSettings['attachmentThumbHeight']) && strlen($attachment['filename']) < 249)
			{
				// A proper thumb doesn't exist yet? Create one! Or, it needs update.
				if (empty($attachment['id_thumb']) || $attachment['thumb_width'] > $modSettings['attachmentThumbWidth'] || $attachment['thumb_height'] > $modSettings['attachmentThumbHeight'] || ($attachment['thumb_width'] < $modSettings['attachmentThumbWidth'] && $attachment['thumb_height'] < $modSettings['attachmentThumbHeight']))
				{
					$filename = getAttachmentFilename($attachment['filename'], $attachment['id_attach'], $attachment['id_folder']);

					require_once(SUBSDIR . '/Attachments.subs.php');
					$attachment = array_merge($attachment, updateAttachmentThumbnail($filename, $attachment['id_attach'], $id_msg, $attachment['id_thumb']));
				}

				// Only adjust dimensions on successful thumbnail creation.
				if (!empty($attachment['thumb_width']) && !empty($attachment['thumb_height']))
				{
					$attachmentData[$i]['width'] = $attachment['thumb_width'];
					$attachmentData[$i]['height'] = $attachment['thumb_height'];
				}
			}

			if (!empty($attachment['id_thumb']))
			{
				$attachmentData[$i]['thumbnail'] = array(
					'id' => $attachment['id_thumb'],
					'href' => $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_thumb'] . ';image',
				);
			}
			$attachmentData[$i]['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

			// If thumbnails are disabled, check the maximum size of the image.
			if (!$attachmentData[$i]['thumbnail']['has_thumb'] && ((!empty($modSettings['max_image_width']) && $attachment['width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachment['height'] > $modSettings['max_image_height'])))
			{
				if (!empty($modSettings['max_image_width']) && (empty($modSettings['max_image_height']) || $attachment['height'] * $modSettings['max_image_width'] / $attachment['width'] <= $modSettings['max_image_height']))
				{
					$attachmentData[$i]['width'] = $modSettings['max_image_width'];
					$attachmentData[$i]['height'] = floor($attachment['height'] * $modSettings['max_image_width'] / $attachment['width']);
				}
				elseif (!empty($modSettings['max_image_width']))
				{
					$attachmentData[$i]['width'] = floor($attachment['width'] * $modSettings['max_image_height'] / $attachment['height']);
					$attachmentData[$i]['height'] = $modSettings['max_image_height'];
				}
			}
			elseif ($attachmentData[$i]['thumbnail']['has_thumb'])
			{
				// If the image is too large to show inline, make it a popup.
				if (((!empty($modSettings['max_image_width']) && $attachmentData[$i]['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachmentData[$i]['real_height'] > $modSettings['max_image_height'])))
					$attachmentData[$i]['thumbnail']['javascript'] = 'return reqWin(\'' . $attachmentData[$i]['href'] . ';image\', ' . ($attachment['width'] + 20) . ', ' . ($attachment['height'] + 20) . ', true);';
				else
					$attachmentData[$i]['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['id_attach'] . ');';
			}

			if (!$attachmentData[$i]['thumbnail']['has_thumb'])
				$attachmentData[$i]['downloads']++;
		}
	}

	// Do we need to instigate a sort?
	if ($have_unapproved)
		usort($attachmentData, 'approved_attach_sort');

	return $attachmentData;
}

/**
 * A sort function for putting unapproved attachments first.
 * @param $a
 * @param $b
 * @return int, -1, 0, 1
 */
function approved_attach_sort($a, $b)
{
	if ($a['is_approved'] == $b['is_approved'])
		return 0;

	return $a['is_approved'] > $b['is_approved'] ? -1 : 1;
}

/**
 * Callback filter for the retrieval of attachments.
 * This function returns false when:
 *  - the attachment is unapproved, and
 *  - the viewer is not the poster of the message where the attachment is
 *
 * @param array $attachment_info
 */
function filter_accessible_attachment($attachment_info, $all_posters)
{
	global $user_info;

	if (!$attachment_info['approved'] && (!isset($all_posters[$attachment_info['id_msg']]) || $all_posters[$attachment_info['id_msg']] != $user_info['id']))
		return false;

	return true;
}