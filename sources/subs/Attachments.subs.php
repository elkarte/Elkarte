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

if (!defined('ELKARTE'))
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
	$day = date('d');

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
	global $modSettings, $initial_error, $context;

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
	global $modSettings, $context;

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
 * Handles the actual saving of attachments to a directory
 * Loops through $_FILES['attachment'] array and saves each file to the current attachments folder
 * Validates the save location actually exists
 */
function processAttachments()
{
	global $context, $modSettings, $smcFunc, $txt, $user_info;

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
		if (isset($_REQUEST['msg']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				array(
					'id_msg' => (int) $_REQUEST['msg'],
					'attachment_type' => 0,
				)
			);
			list ($context['attachments']['quantity'], $context['attachments']['total_size']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
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
			'msg' => !empty($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
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
	global $modSettings, $context, $smcFunc;

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
			list ($context['dir_files'], $context['dir_size']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
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
	global $modSettings, $smcFunc, $context;
	global $txt;

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

	$smcFunc['db_insert']('',
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
	$attachmentOptions['id'] = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');

	// @todo Add an error here maybe?
	if (empty($attachmentOptions['id']))
		return false;

	// Now that we have the attach id, let's rename this sucker and finish up.
	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $attachmentOptions['id_folder'], false, $attachmentOptions['file_hash']);
	rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);

	// If it's not approved then add to the approval queue.
	if (!$attachmentOptions['approved'])
		$smcFunc['db_insert']('',
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
			$smcFunc['db_insert']('',
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
			$attachmentOptions['thumb'] = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');

			if (!empty($attachmentOptions['thumb']))
			{
				$smcFunc['db_query']('', '
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
	global $smcFunc;

	// Use our cache when possible
	if (($cache = cache_get_data('getAvatar_id-' . $id_attach)) !== null)
		$avatarData = $cache;
	else
	{
		$request = $smcFunc['db_query']('', '
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
		if ($smcFunc['db_num_rows']($request) != 0)
			$avatarData = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

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
	global $smcFunc;

	// Make sure this attachment is on this board.

	$request = $smcFunc['db_query']('', '
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
	if ($smcFunc['db_num_rows']($request) != 0)
		$attachmentData = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

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
	global $smcFunc;

	$smcFunc['db_query']('attach_download_increase', '
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
	global $smcFunc;

	if (empty($attachments))
		return 0;

	// For safety, check for thumbnails...
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Update the thumbnail too...
		if (!empty($row['id_thumb']))
			$attachments[] = $row['id_thumb'];

		$attachments[] = $row['id_attach'];
	}
	$smcFunc['db_free_result']($request);

	if (empty($attachments))
		return 0;

	// Approving an attachment is not hard - it's easy.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}attachments
		SET approved = {int:is_approved}
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
			'is_approved' => 1,
		)
	);

	// In order to log the attachments, we really need their message and filename
	$request = $smcFunc['db_query']('', '
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

	while ($row = $smcFunc['db_fetch_assoc']($request))
		logAction(
			'approve_attach',
			array(
				'message' => $row['id_msg'],
				'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($row['filename'])),
			)
		);
	$smcFunc['db_free_result']($request);

	// Remove from the approval queue.
	$smcFunc['db_query']('', '
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
	global $modSettings, $smcFunc;

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
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
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
	$smcFunc['db_free_result']($request);

	// Removed attachments don't have to be updated anymore.
	$parents = array_diff($parents, $attach);
	if (!empty($parents))
		$smcFunc['db_query']('', '
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
		$request = $smcFunc['db_query']('', '
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

		while ($row = $smcFunc['db_fetch_assoc']($request))
			logAction(
				'remove_attach',
				array(
					'message' => $row['id_msg'],
					'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($row['filename'])),
				)
			);
		$smcFunc['db_free_result']($request);
	}

	if (!empty($attach))
		$smcFunc['db_query']('', '
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
	global $modSettings, $smcFunc;

	$ext = !empty($modSettings['avatar_download_png']) ? 'png' : 'jpeg';
	$destName = 'avatar_' . $memID . '_' . time() . '.' . $ext;

	// Just making sure there is a non-zero member.
	if (empty($memID))
		return false;

	removeAttachments(array('id_member' => $memID));

	$id_folder = getAttachmentPathID();
	$avatar_hash = empty($modSettings['custom_avatar_enabled']) ? getAttachmentFilename($destName, false, null, true) : '';
	$smcFunc['db_insert']('',
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
	$attachID = $smcFunc['db_insert_id']('{db_prefix}attachments', 'id_attach');

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
			$smcFunc['db_query']('', '
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
		$smcFunc['db_query']('', '
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
			fwrite($fp, 'HEAD /' . $match[2] . ' HTTP/1.1' . "\r\n" . 'Host: ' . $match[1] . "\r\n" . 'User-Agent: PHP/ELKARTE' . "\r\n" . 'Connection: close' . "\r\n\r\n");

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
function getAttachments($messages, $includeUnapproved = false, $filter = null)
{
	global $smcFunc, $modSettings;

	$attachments = array();
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!$row['approved'] && !$includeUnapproved && (empty($filter) || !call_user_func($filter, $row)))
			continue;

		$temp[$row['id_attach']] = $row;

		if (!isset($attachments[$row['id_msg']]))
			$attachments[$row['id_msg']] = array();
	}
	$smcFunc['db_free_result']($request);

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
	global $smcFunc;

	// Get the number of attachments....
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member = {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);
	list ($num_attachments) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_attachments;
}

/**
 * How many avatars do we have. Need to know. :P
 *
 * @return int
 */
function getAvatarCount()
{
	global $smcFunc;

	// Get the avatar amount....
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_member != {int:guest_id_member}',
		array(
			'guest_id_member' => 0,
		)
	);
	list ($num_avatars) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

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
	global $smcFunc;

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

	$avatars = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$avatars[] = $row;
	$smcFunc['db_free_result']($request);

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
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}attachments
		WHERE id_attach IN ({array_int:to_remove})',
		array(
			'to_remove' => $attach_ids,
		)
	);
	$smcFunc['db_query']('', '
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
	global $smcFunc;

	if ($filesize === null)
	{
		$result = $smcFunc['db_query']('', '
			SELECT size
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attach_id,
			)
		);
		if (!empty($result))
		{
			list($filesize) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);
			return $filesize;
		}
		return false;
	}
	else
	{
		$smcFunc['db_query']('', '
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
	global $smcFunc;

	if ($folder_id === null)
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_folder
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attach_id,
			)
		);
		if (!empty($result))
		{
			list($folder_id) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);
			return $folder_id;
		}
		return false;
	}
	else
	{
		$smcFunc['db_query']('', '
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
	global $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT MAX(id_attach)
		FROM {db_prefix}attachments
		WHERE id_thumb != {int:no_thumb}',
		array(
			'no_thumb' => 0,
		)
	);
	list ($thumbnails) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return $thumbnails;
}