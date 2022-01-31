<?php

/**
 * Attachment display.
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

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Errors\AttachmentErrorContext;
use ElkArte\Exceptions\Exception;
use ElkArte\AttachmentsDirectory;
use ElkArte\Attachments\Download;
use ElkArte\Http\Headers;
use ElkArte\TemporaryAttachmentsList;
use ElkArte\Languages\Txt;

/**
 * Everything to do with attachment handling / processing
 *
 * What it does:
 *
 * - Handles the downloading of an attachment or avatar
 * - Handles the uploading of attachments via Ajax
 * - Increments the download count where applicable
 *
 * @package Attachments
 */
class Attachment extends AbstractController
{
	/**
	 * {@inheritdoc }
	 */
	public function needTheme($action = '')
	{
		global $modSettings, $maintenance;

		// If guests are not allowed to browse and the user is a guest... kick him!
		if (empty($modSettings['allow_guestAccess']) && $this->user->is_guest)
		{
			return true;
		}

		// If not in maintenance or allowed to use the forum in maintenance
		if (empty($maintenance) || allowedTo('admin_forum'))
		{
			$sa = $this->_req->getQuery('sa', 'trim', '');

			return $sa === 'ulattach' || $sa === 'rmattach';
		}

		// ... politely kick them out
		return true;
	}

	/**
	 * {@inheritdoc }
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * The default action is to download an attachment.
	 * This allows ?action=attachment to be forwarded to action_dlattach()
	 */
	public function action_index()
	{
		// add a subaction array to act accordingly
		$subActions = array(
			'dlattach' => array($this, 'action_dlattach'),
			'tmpattach' => array($this, 'action_tmpattach'),
			'ulattach' => array($this, 'action_ulattach'),
			'rmattach' => array($this, 'action_rmattach'),
		);

		// Setup the action handler
		$action = new Action('attachments');
		$subAction = $action->initialize($subActions, 'dlattach');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 * Function to upload attachments via ajax calls
	 *
	 * - Currently called by drag drop attachment functionality
	 * - Pass the form data with session vars
	 * - Responds back with errors or file data
	 */
	public function action_ulattach()
	{
		global $context, $modSettings, $txt;

		$resp_data = array();
		Txt::load('Errors');
		$context['attachments']['can']['post'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));

		// Set up the template details
		$template_layers = theme()->getLayers();
		$template_layers->removeAll();
		theme()->getTemplates()->load('Json');
		$context['sub_template'] = 'send_json';

		// Make sure the session is still valid
		if (checkSession('request', '', false) !== '')
		{
			$context['json_data'] = array('result' => false, 'data' => $txt['session_timeout_file_upload']);

			return false;
		}

		// We should have files, otherwise why are we here?
		if (isset($_FILES['attachment']))
		{
			Txt::load('Post');

			$attach_errors = AttachmentErrorContext::context();
			$attach_errors->activate();

			if ($context['attachments']['can']['post'] && empty($this->_req->post->from_qr))
			{
				require_once(SUBSDIR . '/Attachments.subs.php');

				processAttachments($this->_req->getPost('msg', 'intval', 0));
			}

			// Any mistakes?
			if ($attach_errors->hasErrors())
			{
				$errors = $attach_errors->prepareErrors();

				// Bad news for you, the attachments did not process, lets tell them why
				foreach ($errors as $error)
				{
					$resp_data[] = $error;
				}

				$context['json_data'] = array('result' => false, 'data' => $resp_data);
			}
			// No errors, lets get the details of what we have for our response back to the upload dialog
			else
			{
				$tmp_attachments = new TemporaryAttachmentsList();
				foreach ($tmp_attachments->toArray() as $val)
				{
					// We need to grab the name anyhow
					if (!empty($val['tmp_name']))
					{
						$resp_data = array(
							'name' => $val['name'],
							'attachid' => $val['public_attachid'],
							'size' => $val['size'],
							'resized' => !empty($val['resized']),
						);
					}
				}

				$context['json_data'] = array('result' => true, 'data' => $resp_data);
			}
		}
		// Could not find the files you claimed to have sent
		else
		{
			$context['json_data'] = array('result' => false, 'data' => $txt['no_files_uploaded']);
		}
	}

	/**
	 * Function to remove temporary attachments which were newly added via ajax calls
	 * or to remove previous saved ones from an existing post
	 *
	 * What it does:
	 *
	 * - Currently called by drag drop attachment functionality
	 * - Requires file name and file path
	 * - Responds back with success or error
	 */
	public function action_rmattach()
	{
		global $context, $txt;

		// Prepare the template so we can respond with json
		$template_layers = theme()->getLayers();
		$template_layers->removeAll();
		theme()->getTemplates()->load('Json');
		$context['sub_template'] = 'send_json';

		// Make sure the session is valid
		if (checkSession('request', '', false) !== '')
		{
			Txt::load('Errors');
			$context['json_data'] = array('result' => false, 'data' => $txt['session_timeout']);

			return false;
		}

		// We need a filename and path, or we are not going any further
		if (isset($this->_req->post->attachid))
		{
			$result = false;
			$tmp_attachments = new TemporaryAttachmentsList();
			if ($tmp_attachments->hasAttachments())
			{
				$attachId = $tmp_attachments->getIdFromPublic($this->_req->post->attachid);

				try
				{
					$tmp_attachments->removeById($attachId);
					$context['json_data'] = array('result' => true);
					$result = true;
				}
				catch (\Exception $e)
				{
					$result = $e->getMessage();
				}
			}

			// Not a temporary attachment, but a previously uploaded one?
			if ($result !== true)
			{
				require_once(SUBSDIR . '/ManageAttachments.subs.php');
				$attachId = $this->_req->getPost('attachid', 'intval');
				if (canRemoveAttachment($attachId, $this->user->id))
				{
					$result_tmp = removeAttachments(array('id_attach' => $attachId), '', true);
					if (!empty($result_tmp))
					{
						$context['json_data'] = array('result' => true);
						$result = true;
					}
					else
					{
						$result = $result_tmp;
					}
				}
			}

			if ($result !== true)
			{
				Txt::load('Errors');
				$context['json_data'] = array('result' => false, 'data' => $txt[!empty($result) ? $result : 'attachment_not_found']);
			}
		}
		else
		{
			Txt::load('Errors');
			$context['json_data'] = array('result' => false, 'data' => $txt['attachment_not_found']);
		}
	}

	/**
	 * Downloads an attachment or avatar, and increments the download count.
	 *
	 * What it does:
	 *
	 * - It requires the view_attachments permission. (not for avatars!)
	 * - It disables the session parser, and clears any previous output.
	 * - It is accessed via the query string ?action=dlattach.
	 * - Views to attachments and avatars do not increase hits and are not logged
	 *   in the "Who's Online" log.
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function action_dlattach()
	{
		global $modSettings, $context, $topic, $board;

		// Some defaults that we need.
		$context['no_last_modified'] = true;
		$filename = null;

		// We need to do some work on attachments and avatars.
		require_once(SUBSDIR . '/Attachments.subs.php');

		$id_attach = $this->_req->query->attach ?? '';
		$attachment_class = new Download($id_attach, $this->_req->getQuery('attach', 'intval', 0));

		$inline = $attachment_class->isTemporary();

		$id_attach = $this->_req->getQuery('attach', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// This is just a regular attachment... Avatars are no longer a dlattach option
		if (empty($topic) && !empty($id_attach))
		{
			$id_board = 0;
			$id_topic = 0;
			$attachPos = getAttachmentPosition($id_attach);
			if ($attachPos !== false)
			{
				list($id_board, $id_topic) = array_values($attachPos);
			}
		}
		else
		{
			$id_board = $board;
			$id_topic = $topic;
		}

		isAllowedTo('view_attachments', $id_board);

		$type = $this->_req->getQuery('thumb') !== null ? 'thumb' : $this->_req->getQuery('type');
		if ($attachment_class->validate($type, $id_topic))
		{
			// If it isn't yet approved (and is an attachment), do they have permission to view it?
			if ($attachment_class->isApproved() == false && $attachment_class->isOwner() == false && $attachment_class->isAvatar() == false)
			{
				isAllowedTo('approve_posts', $id_board ?? $board);
			}

			$attachment_class->increaseDownloadCounter();
		}

		$use_compression = !empty($modSettings['enableCompressedOutput']) && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && function_exists('gzencode');
		echo $attachment_class->send($inline, $use_compression);

		obExit(false);
	}
}
