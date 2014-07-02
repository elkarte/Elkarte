<?php

/**
 * Attachment display.
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

/**
 * Attachment_Controller class.
 *
 * - Handles the downloading of an attachment or avatar
 * - Handles the uploadign of attachments via Ajax
 * - increments the download count where applicable
 *
 * @package Attachments
 */
class Attachment_Controller extends Action_Controller
{
	/**
	 * The default action is to download an attachment.
	 * This allows ?action=attachment to be forwarded to action_dlattach()
	 */
	public function action_index()
	{
		require_once(SUBSDIR . '/Action.class.php');

		// add an subaction array to act accordingly
		$subActions = array(
			'dlattach' => array($this, 'action_dlattach'),
			'ulattach' => array($this, 'action_ulattach'),
			'rmattach' => array($this, 'action_rmattach'),
		);

		// Setup the action handler
		$action = new Action();
		$subAction = $action->initialize($subActions, 'dlattach');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 * Function to upload attachements via ajax calls
	 *
	 * - Currently called by drag drop attachment functionality
	 * - Pass the form data with session vars
	 * - Responds back with errors or file data
	 */
	public function action_ulattach()
	{
		global $context, $modSettings, $txt;

		$resp_data = array();
		loadLanguage('Errors');
		$context['attachments']['can']['post'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));

		// Set up the template details
		$template_layers = Template_Layers::getInstance();
		$template_layers->removeAll();
		loadTemplate('Json');
		$context['sub_template'] = 'send_json';

		// Make sure the session is still valid
		if (checkSession('request', '', false) != '')
		{
			$context['json_data'] = array('result' => false, 'data' => $txt['session_timeout_file_upload']);
			return false;
		}

		// We should have files, otherwise why are we here?
		if (isset($_FILES['attachment']))
		{
			loadLanguage('Post');

			require_once(SOURCEDIR . '/AttachmentErrorContext.class.php');
			$attach_errors = Attachment_Error_Context::context();
			$attach_errors->activate();

			if ($context['attachments']['can']['post'] && empty($_POST['from_qr']))
			{
				require_once(SUBSDIR . '/Attachments.subs.php');

				if (isset($_REQUEST['msg']))
					processAttachments((int) $_REQUEST['msg']);
				else
					processAttachments();
			}

			// Any mistakes?
			if ($attach_errors->hasErrors())
			{
				$errors = $attach_errors->prepareErrors();

				// Bad news for you, the attachments did not process, lets tell them why
				foreach ($errors as $key => $error)
					$resp_data[] = $error;

				$context['json_data'] = array('result' => false, 'data' => $resp_data);
			}
			// No errors, lets get the details of what we have for our response back
			else
			{
				foreach ($_SESSION['temp_attachments'] as $attachID => $val)
				{
					// We need to grab the name anyhow
					if (!empty($val['tmp_name']))
					{
						$resp_data = array(
							'name' => $val['name'],
							'attachid' => $attachID,
							'size' => $val['size']
						);
					}
				}

				$context['json_data'] = array('result' => true, 'data' => $resp_data);
			}
		}
		// Could not find the files you claimed to have sent
		else
			$context['json_data'] = array('result' => false, 'data' => $txt['no_files_uploaded']);
	}

	/**
	 * Function to remove attachements which were added via ajax calls
	 * Currently called by drag drop attachment functionality
	 * Requires file name and file path
	 * responds back with success or error
	 */
	public function action_rmattach()
	{
		global $context, $txt;

		// Prepare the template so we can respond with json
		$template_layers = Template_Layers::getInstance();
		$template_layers->removeAll();
		loadTemplate('Json');
		$context['sub_template'] = 'send_json';

		// Make sure the session is valid
		if (checkSession('request', '', false) !== '')
		{
			loadLanguage('Errors');
			$context['json_data'] = array('result' => false, 'data' => $txt['session_timeout']);

			return false;
		}

		// We need a filename and path or we are not going any further
		if (isset($_REQUEST['attachid']))
		{
			require_once(SUBSDIR . '/Attachments.subs.php');
			$result = removeTempAttachById($_REQUEST['attachid']);
			if ($result === true)
				$context['json_data'] = array('result' => true);
			else
			{
				loadLanguage('Errors');
				$context['json_data'] = array('result' => false, 'data' => $txt[$result]);
			}
		}
		else
		{
			loadLanguage('Errors');
			$context['json_data'] = array('result' => false, 'data' => $txt['attachment_not_found']);
		}
	}

	/**
	 * Downloads an attachment or avatar, and increments the download count.
	 * It requires the view_attachments permission. (not for avatars!)
	 * It disables the session parser, and clears any previous output.
	 * It is accessed via the query string ?action=dlattach.
	 * Views to attachments and avatars do not increase hits and are not logged in the "Who's Online" log.
	 */
	public function action_dlattach()
	{
		global $txt, $modSettings, $user_info, $context, $topic;

		// Some defaults that we need.
		$context['no_last_modified'] = true;

		// Make sure some attachment was requested!
		if (!isset($_REQUEST['attach']) && !isset($_REQUEST['id']))
			fatal_lang_error('no_access', false);

		// We need to do some work on attachments and avatars.
		require_once(SUBSDIR . '/Attachments.subs.php');

		$id_attach = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : (int) $_REQUEST['id'];

		if (isset($_REQUEST['type']) && $_REQUEST['type'] == 'avatar')
		{
			$attachment = getAvatar($id_attach);

			$is_avatar = true;
			$_REQUEST['image'] = true;
		}
		// This is just a regular attachment...
		else
		{
			isAllowedTo('view_attachments');
			$attachment = getAttachmentFromTopic($id_attach, $topic);
		}

		if (empty($attachment))
			fatal_lang_error('no_access', false);
		list ($id_folder, $real_filename, $file_hash, $file_ext, $id_attach, $attachment_type, $mime_type, $is_approved, $id_member) = $attachment;

		// If it isn't yet approved, do they have permission to view it?
		if (!$is_approved && ($id_member == 0 || $user_info['id'] != $id_member) && ($attachment_type == 0 || $attachment_type == 3))
			isAllowedTo('approve_posts');

		// Update the download counter (unless it's a thumbnail or an avatar).
		if (empty($is_avatar) || $attachment_type != 3)
			increaseDownloadCounter($id_attach);

		$filename = getAttachmentFilename($real_filename, $id_attach, $id_folder, false, $file_hash);

		// This is done to clear any output that was made before now.
		while (ob_get_level() > 0)
			@ob_end_clean();

		if (!empty($modSettings['enableCompressedOutput']) && @filesize($filename) <= 4194304 && in_array($file_ext, array('txt', 'html', 'htm', 'js', 'doc', 'docx', 'rtf', 'css', 'php', 'log', 'xml', 'sql', 'c', 'java')))
			ob_start('ob_gzhandler');
		else
		{
			ob_start();
			header('Content-Encoding: none');
		}

		// No point in a nicer message, because this is supposed to be an attachment anyway...
		if (!file_exists($filename))
		{
			loadLanguage('Errors');

			header((preg_match('~HTTP/1\.[01]~i', $_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 404 Not Found');
			header('Content-Type: text/plain; charset=UTF-8');

			// We need to die like this *before* we send any anti-caching headers as below.
			die('404 - ' . $txt['attachment_not_found']);
		}

		// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
		{
			list ($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
			if (strtotime($modified_since) >= filemtime($filename))
			{
				@ob_end_clean();

				// Answer the question - no, it hasn't been modified ;).
				header('HTTP/1.1 304 Not Modified');
				exit;
			}
		}

		// Check whether the ETag was sent back, and cache based on that...
		$eTag = '"' . substr($id_attach . $real_filename . filemtime($filename), 0, 64) . '"';
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
		{
			@ob_end_clean();

			header('HTTP/1.1 304 Not Modified');
			exit;
		}

		// Send the attachment headers.
		header('Pragma: ');
		if (!isBrowser('gecko'))
			header('Content-Transfer-Encoding: binary');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filename)) . ' GMT');
		header('Accept-Ranges: bytes');
		header('Connection: close');
		header('ETag: ' . $eTag);

		// Make sure the mime type warrants an inline display.
		if (isset($_REQUEST['image']) && !empty($mime_type) && strpos($mime_type, 'image/') !== 0)
			unset($_REQUEST['image']);
		// Does this have a mime type?
		elseif (!empty($mime_type) && (isset($_REQUEST['image']) || !in_array($file_ext, array('jpg', 'gif', 'jpeg', 'x-ms-bmp', 'png', 'psd', 'tiff', 'iff'))))
			header('Content-Type: ' . strtr($mime_type, array('image/bmp' => 'image/x-ms-bmp')));
		else
		{
			header('Content-Type: ' . (isBrowser('ie') || isBrowser('opera') ? 'application/octetstream' : 'application/octet-stream'));
			if (isset($_REQUEST['image']))
				unset($_REQUEST['image']);
		}

		$disposition = !isset($_REQUEST['image']) ? 'attachment' : 'inline';

		// Different browsers like different standards...
		if (isBrowser('firefox'))
			header('Content-Disposition: ' . $disposition . '; filename*=UTF-8\'\'' . rawurlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $real_filename)));
		elseif (isBrowser('opera'))
			header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $real_filename) . '"');
		elseif (isBrowser('ie'))
			header('Content-Disposition: ' . $disposition . '; filename="' . urlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $real_filename)) . '"');
		else
			header('Content-Disposition: ' . $disposition . '; filename="' . $real_filename . '"');

		// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
		if (!isset($_REQUEST['image']) && in_array($file_ext, array('gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff')))
			header('Cache-Control: no-cache');
		else
			header('Cache-Control: max-age=' . (525600 * 60) . ', private');

		if (empty($modSettings['enableCompressedOutput']) || filesize($filename) > 4194304)
			header('Content-Length: ' . filesize($filename));

		// Try to buy some time...
		@set_time_limit(600);

		// Recode line endings for text files, if enabled.
		if (!empty($modSettings['attachmentRecodeLineEndings']) && !isset($_REQUEST['image']) && in_array($file_ext, array('txt', 'css', 'htm', 'html', 'php', 'xml')))
		{
			$req = request();
			if (strpos($req->user_agent(), 'Windows') !== false)
				$callback = create_function('$buffer', 'return preg_replace(\'~[\r]?\n~\', "\r\n", $buffer);');
			elseif (strpos($req->user_agent(), 'Mac') !== false)
				$callback = create_function('$buffer', 'return preg_replace(\'~[\r]?\n~\', "\r", $buffer);');
			else
				$callback = create_function('$buffer', 'return preg_replace(\'~[\r]?\n~\', "\n", $buffer);');
		}

		// Since we don't do output compression for files this large...
		if (filesize($filename) > 4194304)
		{
			// Forcibly end any output buffering going on.
			while (ob_get_level() > 0)
				@ob_end_clean();

			$fp = fopen($filename, 'rb');
			while (!feof($fp))
			{
				if (isset($callback))
					echo $callback(fread($fp, 8192));
				else
					echo fread($fp, 8192);

				flush();
			}
			fclose($fp);
		}
		// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
		elseif (isset($callback) || @readfile($filename) === null)
			echo isset($callback) ? $callback(file_get_contents($filename)) : file_get_contents($filename);

		obExit(false);
	}
}