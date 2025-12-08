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
use ElkArte\Attachments\AttachmentsDirectory;
use ElkArte\Attachments\TemporaryAttachmentChunk;
use ElkArte\Attachments\TemporaryAttachmentProcess;
use ElkArte\Attachments\TemporaryAttachmentsList;
use ElkArte\Errors\AttachmentErrorContext;
use ElkArte\Exceptions\Exception;
use ElkArte\Graphics\Image;
use ElkArte\Graphics\TextImage;
use ElkArte\Helper\FileFunctions;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\Request;
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;

/**
 * Everything to do with attachment handling / processing
 *
 * What it does:
 *
 * - Handles the downloading of an attachment or avatar
 * - Handles the uploading of attachments via Ajax
 * - Increments the download count where applicable
 *
 */
class Attachment extends AbstractController
{
	/** @var int Maximum size of a file to compress */
	private const SMALL_COMPRESS_THRESHOLD = 1048576;

	/**
	 * {@inheritDoc}
	 */
	public function needTheme($action = ''): bool
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

			// We will need to respond with Json
			return $sa === 'ulattach' || $sa === 'rmattach' || $sa === 'ulasync';
		}

		// ... politely kick them out
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = ''): bool
	{
		return false;
	}

	/**
	 * The default action is to download an attachment.
	 * This allows ?action=attachment to be forwarded to action_dlattach()
	 */
	public function action_index(): void
	{
		// add a subaction array to act accordingly
		$subActions = [
			'dlattach' => [$this, 'action_dlattach'],
			'tmpattach' => [$this, 'action_tmpattach'],
			'ulattach' => [$this, 'action_ulattach'],
			'ulasync' => [$this, 'action_ulasync'],
			'rmattach' => [$this, 'action_rmattach'],
		];

		// Setup the action handler
		$action = new Action('attachments');
		$subAction = $action->initialize($subActions, 'dlattach');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 *  Method to upload attachments as fragments via ajax
	 *
	 * - Currently called by post attachment functionality
	 * - Passed the form data with session vars
	 * - Responds back with errors or file data
	 *
	 * @return bool Returns false if there was an error, otherwise true.
	 */
	public function action_ulasync(): bool
	{
		global $context;

		// Going to send back Json
		setJsonTemplate();

		// Final request, rebuild the file and do standard upload checks
		if ($this->_req->comparePost('async', 'complete', 'trim'))
		{
			$this->combineChunksAndProcess();
			return true;
		}

		Txt::load('Errors');

		// Process the chunk
		$chunk = new TemporaryAttachmentChunk();
		$resp_data = $chunk->action_async();

		// If we have a PHP upload error, set the error context
		if ($resp_data['result'] !== true)
		{
			$attach_errors = AttachmentErrorContext::context();
			$attach_errors->activate();
			if ($attach_errors->hasErrors())
			{
				$errors = $attach_errors->prepareErrors();
				foreach ($errors as $error)
				{
					$resp_data[] = $error;
				}

				$context['json_data'] = ['result' => false, 'data' => $resp_data];
				return false;
			}
		}

		// Set up the template details
		$context['json_data'] = $resp_data;

		return true;
	}

	/**
	 * Combines the temporary attachment chunks into a single file
	 *
	 * This method combines the temporary attachment chunks into a single file and performs the final
	 * processing request for the combined chunks. If the response data indicates that the result is
	 * successful, the method passes the file off to the action_ulattach method as if it were a single file.
	 * Otherwise, the response data is assigned to the $context['json_data'] variable.
	 *
	 * @return void
	 */
	private function combineChunksAndProcess(): void
	{
		global $context;

		// Final chuck processing request
		$chunk = new TemporaryAttachmentChunk();

		$resp_data = $chunk->action_combineChunks();
		if ($resp_data['result'] === true)
		{
			// Pass this off to action_ulattach just like it was a single file, set strict as false as we already have the
			// combined chunks in the attachment directory, and we don't need to verify it was a php upload any longer
			$this->action_ulattach(false);
		}
		else
		{
			$context['json_data'] = $resp_data;
		}
	}

	/**
	 *  Method to upload attachments via ajax
	 *
	 *  - Currently called by drag drop attachment functionality
	 *  - Passed the form data with session vars
	 *  - Responds back with errors or file data
	 *
	 * @param bool $strict True if attachment processing should use move_uploaded_file, rename otherwise. Default is true.
	 *
	 * @return bool|null False if the session is invalid or an error occurred, void otherwise.
	 */
	public function action_ulattach($strict = true): ?bool
	{
		global $context, $modSettings, $txt;

		$resp_data = [];
		Txt::load('Errors');
		$context['attachments']['can']['post'] = !empty($modSettings['attachmentEnable']) && (int) $modSettings['attachmentEnable'] === 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments')));

		// Set up the template details
		setJsonTemplate();

		// Make sure the session is still valid
		if (checkSession('post', '', false) !== '')
		{
			$context['json_data'] = ['result' => false, 'data' => $txt['session_timeout_file_upload']];

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
				$processAttachments = new TemporaryAttachmentProcess();
				$processAttachments->strict = $strict;
				$processAttachments->processAttachments($this->_req->getPost('msg', 'intval', 0));
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

				$context['json_data'] = ['result' => false, 'data' => $resp_data];
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
						$resp_data = [
							'name' => $val['name'],
							'attachid' => $val['public_attachid'],
							'size' => $val['size'],
							'resized' => !empty($val['resized']),
						];
					}
				}

				$context['json_data'] = ['result' => true, 'data' => $resp_data];
			}
		}
		// Could not find the files you claimed to have sent
		else
		{
			$context['json_data'] = ['result' => false, 'data' => $txt['no_files_uploaded']];
		}

		return null;
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
	public function action_rmattach(): ?bool
	{
		global $context, $txt;

		// Prepare the template so we can respond with json
		setJsonTemplate();

		// Make sure the session is valid
		if (checkSession('post', '', false) !== '')
		{
			Txt::load('Errors');
			$context['json_data'] = ['result' => false, 'data' => $txt['session_timeout']];

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
					$context['json_data'] = ['result' => true];
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
				if (canRemoveAttachment($attachId, User::$info->id))
				{
					$result_tmp = removeAttachments(['id_attach' => $attachId], '', true);
					if (!empty($result_tmp))
					{
						$context['json_data'] = ['result' => true];
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
				$context['json_data'] = ['result' => false, 'data' => $txt[empty($result) ? 'attachment_not_found' : $result]];
			}
		}
		else
		{
			Txt::load('Errors');
			$context['json_data'] = ['result' => false, 'data' => $txt['attachment_not_found']];
		}

		return null;
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
	 * @throws Exception
	 */
	public function action_dlattach(): void
	{
		global $modSettings, $context, $topic, $board, $settings;

		// Some defaults that we need.
		$context['no_last_modified'] = true;
		$filename = null;

		// Make sure some attachment was requested!
		if (!isset($this->_req->query->attach))
		{
			if (!isset($this->_req->query->id))
			{
				// Give them the old can't find it image
				$this->action_text_to_image('attachment_not_found');
			}

			if ($this->_req->query->id === 'ila')
			{
				// Give them the old can't touch this
				$this->action_text_to_image(($this->user->is_guest ? 'not_applicable' : 'awaiting_approval'), 90, 90, true);
			}
		}

		// We need to do some work on attachments and avatars.
		require_once(SUBSDIR . '/Attachments.subs.php');

		// Temporary attachment, special case...
		if (isset($this->_req->query->attach) && str_contains($this->_req->query->attach, 'post_tmp_' . $this->user->id . '_'))
		{
			// Return via tmpattach, back presumably to the post form
			$this->action_tmpattach();
		}

		$id_attach = $this->_req->getQuery('attach', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// This is just a regular attachment... Avatars are no longer a dlattach option
		if (empty($topic) && !empty($id_attach))
		{
			$id_board = 0;
			$id_topic = 0;
			$attachPos = getAttachmentPosition($id_attach);
			if ($attachPos !== false)
			{
				[$id_board, $id_topic] = array_values($attachPos);
			}
		}
		else
		{
			$id_board = $board;
			$id_topic = $topic;
		}

		isAllowedTo('view_attachments', $id_board);

		if ($this->_req->getQuery('thumb') === null)
		{
			$attachment = getAttachmentFromTopic($id_attach, $id_topic);
		}
		else
		{
			$this->_req->query->image = true;
			$attachment = getAttachmentThumbFromTopic($id_attach, $id_topic);

			// No file name, no thumbnail, no image.
			if (empty($attachment['filename']))
			{
				$full_attach = getAttachmentFromTopic($id_attach, $id_topic);
				$attachment['filename'] = empty($full_attach['filename']) ? '' : $full_attach['filename'];
				$attachment['id_attach'] = 0;
				$attachment['attachment_type'] = 0;
				$attachment['approved'] = $full_attach['approved'] ?? 0;
				$attachment['id_member'] = $full_attach['id_member'];

				// If it is a known extension, show a mimetype extension image
				$check = returnMimeThumb(empty($full_attach['fileext']) ? 'default' : $full_attach['fileext']);
				if ($check !== false)
				{
					$attachment['fileext'] = 'png';
					$attachment['mime_type'] = 'image/png';
					$filename = $check;
				}
				else
				{
					$attachmentsDir = new AttachmentsDirectory($modSettings, database());
					$filename = $attachmentsDir->getCurrent() . '/' . $attachment['filename'];
				}

				if (!str_starts_with(getMimeType($filename), 'image'))
				{
					$attachment['fileext'] = 'png';
					$attachment['mime_type'] = 'image/png';
					$filename = $settings['theme_dir'] . '/images/mime_images/default.png';
				}
			}
		}

		if (empty($attachment))
		{
			// Exit via action_text_to_image
			$this->action_text_to_image('attachment_not_found');
		}

		$id_folder = $attachment['id_folder'] ?? '';
		$real_filename = $attachment['filename'] ?? '';
		$file_hash = $attachment['file_hash'] ?? '';
		$file_ext = $attachment['fileext'] ?? '';
		$id_attach = $attachment['id_attach'] ?? -1;
		$attachment_type = $attachment['attachment_type'] ?? -1;
		$mime_type = $attachment['mime_type'] ?? '';
		$is_approved = $attachment['approved'] ?? '';
		$id_member = $attachment['id_member'] ?? '';

		// If it isn't yet approved, do they have permission to view it?
		if (!$is_approved && ($id_member === 0 || $this->user->id !== $id_member) && ($attachment_type === 0 || $attachment_type === 3))
		{
			isAllowedTo('approve_posts', $id_board ?? $board);
		}

		// Update the download counter (unless it's a thumbnail).
		if (!empty($id_attach && $attachment_type != 3))
		{
			increaseDownloadCounter($id_attach);
		}

		if ($filename === null)
		{
			$filename = getAttachmentFilename($real_filename, $id_attach, $id_folder, false, $file_hash);
		}

		$possibleMobi = strpos(Request::instance()->user_agent(), 'Mobi');
		$eTag = '"' . substr($id_attach . $real_filename . @filemtime($filename), 0, 64) . '"';
		$do_cache = !(!isset($this->_req->query->image) && getValidMimeImageType($file_ext) !== '');

		// Make sure the mime type warrants an inline display.
		if (isset($this->_req->query->image) && !empty($mime_type) && !str_starts_with($mime_type, 'image/'))
		{
			unset($this->_req->query->image);
			$mime_type = '';
		}
		// Does this have a mime type?
		elseif (empty($mime_type) || (!isset($this->_req->query->image) && getValidMimeImageType($file_ext) !== ''))
		{
			$mime_type = '';
			if (isset($this->_req->query->image))
			{
				unset($this->_req->query->image);
			}
		}

		// Show this content inline or download?
		$disposition = (isset($this->_req->query->image) || ($possibleMobi && (str_starts_with($mime_type, 'audio/') || str_starts_with($mime_type, 'video/')))) ? 'inline' : 'attachment';
		$this->prepare_headers($filename, $eTag, $mime_type, $disposition, $real_filename, $do_cache);
		$this->send_file($filename, $mime_type);

		obExit(false);
	}

	/**
	 * Generates a language image based on text for display, outputs that image and exits
	 *
	 * @param null|string $text if null will use default attachment not found string
	 * @param int $width If set, defines the width of the image, text font size will be scaled to fit
	 * @param int $height If set, defines the height of the image
	 * @param bool $split If true will break text strings so all words are separated by newlines
	 * @throws Exception
	 */
	public function action_text_to_image($text = null, $width = 200, $height = 75, $split = false): void
	{
		global $txt;

		new ThemeLoader();
		Txt::load('Errors');
		$text = $text === null ? $txt['attachment_not_found'] : $txt[$text] ?? $text;
		$text = $split ? str_replace(' ', "\n", $text) : $text;

		try
		{
			$img = new TextImage($text);
			$img = $img->generate($width, $height);
		}
		catch (\Exception)
		{
			throw new Exception('no_access', false);
		}

		$this->prepare_headers('no_image', 'no_image', 'image/png', 'inline', 'no_image.png', true, false);
		Headers::instance()->sendHeaders();
		echo $img;

		obExit(false);
	}

	/**
	 * If the mime type benefits from compression e.g. text/xyz and gzencode is
	 * available and the user agent accepts gzip, then return true, else false
	 *
	 * @param string $mime_type
	 * @return bool if we should compress the file
	 */
	public function useCompression($mime_type): bool
	{
		global $modSettings;

		// Not compressible, or not supported / requested by client
		if (!preg_match('~^(?:text/|application/(?:json|xml|rss\+xml)$)~i', $mime_type)
			|| (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) || !str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')))
		{
			return false;
		}

		// Support is available on the serve
		return !(!function_exists('gzencode') && !empty($modSettings['enableCompressedOutput']));
	}

	/**
	 * Takes care of sending out the most common headers.
	 *
	 * @param string $filename Full path+file name of the file in the filesystem
	 * @param string $eTag ETag cache validator
	 * @param string $mime_type The mime-type of the file
	 * @param string $disposition The value of the Content-Disposition header
	 * @param string $real_filename The original name of the file
	 * @param bool $do_cache Send a max-age header or not
	 * @param bool $check_filename When false, any check on $filename is skipped
	 */
	public function prepare_headers($filename, $eTag, $mime_type, $disposition, $real_filename, $do_cache, $check_filename = true): void
	{
		global $txt;

		$headers = Headers::instance();

		// No point in a nicer message, because this is supposed to be an attachment anyway...
		if ($check_filename && !FileFunctions::instance()->fileExists($filename))
		{
			Txt::load('Errors');

			$headers
				->removeHeader('all')
				->httpCode(404)
				->sendHeaders();

			// We need to die like this *before* we send any anti-caching headers as below.
			die('404 - ' . $txt['attachment_not_found']);
		}

		// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
		{
			[$modified_since] = explode(';', $this->_req->server->HTTP_IF_MODIFIED_SINCE);
			if (!$check_filename || strtotime($modified_since) >= filemtime($filename))
			{
				$this->flush_buffers();

				// Answer the question - no, it hasn't been modified ;).
				$headers
					->removeHeader('all')
					->httpCode(304)
					->sendHeaders();
				exit;
			}
		}

		// Check whether the ETag was sent back, and cache based on that...
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && str_contains($_SERVER['HTTP_IF_NONE_MATCH'], $eTag))
		{
			$this->flush_buffers();

			$headers
				->removeHeader('all')
				->httpCode(304)
				->sendHeaders();
			exit;
		}

		// Send the attachment headers.
		$headers
			->header('Expires', gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT')
			->header('Last-Modified', gmdate('D, d M Y H:i:s', $check_filename ? filemtime($filename) : time() - 525600 * 60) . ' GMT')
			->header('Accept-Ranges', 'bytes')
			->header('Connection', 'close')
			->header('ETag', $eTag);

		// Different browsers like different standards...
		$headers->setAttachmentFileParams($mime_type, $real_filename, $disposition);

		// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
		if ($do_cache)
		{
			$headers
				->header('Cache-Control', 'max-age=' . (525600 * 60) . ', private');
		}
		else
		{
			$headers
				->header('Pragma', 'no-cache')
				->header('Cache-Control', 'no-cache');
		}

		// Try to buy some time...
		detectServer()->setTimeLimit(600);
	}

	/**
	 * Sends the requested file to the user.  If the file is compressible e.g.
	 * has a mine type of text/??? may compress the file prior to sending.
	 *
	 * @param string $filename
	 * @param string $mime_type
	 */
	public function send_file($filename, $mime_type): void
	{
		$headers = Headers::instance();
		$fileFuncs = FileFunctions::instance();
		$filesize = $fileFuncs->fileSize($filename);
		$use_compression = $this->useCompression($mime_type);

		// Flush any buffers that may be in place.
		$this->flush_buffers();

		// For small compressible files, compress in-memory and provide a compressed Content-Length
		if ($use_compression && $filesize > 24 && $filesize <= self::SMALL_COMPRESS_THRESHOLD)
		{
			$body = $fileFuncs->fileGetContents($filename);
			if ($body !== false)
			{
				$body = gzencode($body, 4);
				$length = strlen($body);
				$headers
					->header('Content-Encoding', 'gzip')
					->header('Vary', 'Accept-Encoding')
					->header('Content-Length', (string) $length);
				$headers->send();
				echo $body;
			}

			return;
		}

		// Uncompressed streaming (default and fallback)
		if (!empty($filesize))
		{
			$headers->header('Content-Length', (string) $filesize);
		}

		$headers->send();
		readfile($filename);
	}

	/**
	 * Flushes and clears all output buffers
	 *
	 * This method forcibly ends any ongoing output buffering to prevent issues such as double compression
	 * and oversized output buffers by iterating through and clearing all active buffer levels.
	 *
	 * @return void
	 */
	public function flush_buffers(): void
	{
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}
	}

	/**
	 * "Simplified", cough, version of action_dlattach to send out thumbnails while creating
	 * or editing a message.
	 */
	public function action_tmpattach(): void
	{
		global $modSettings, $topic;

		// Make sure some attachment was requested!
		if (!isset($this->_req->query->attach))
		{
			$this->action_text_to_image('attachment_not_found');
		}

		// We will need some help
		require_once(SUBSDIR . '/Attachments.subs.php');
		$tmp_attachments = new TemporaryAttachmentsList();
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());

		try
		{
			if (empty($topic) || (string) (int) $this->_req->query->attach !== (string) $this->_req->query->attach)
			{
				$attach_data = $tmp_attachments->getTempAttachById($this->_req->query->attach, $attachmentsDir, User::$info->id);
				$file_ext = pathinfo($attach_data['name'], PATHINFO_EXTENSION);
				$filename = $attach_data['tmp_name'];
				$id_attach = $attach_data['attachid'];
				$real_filename = $attach_data['name'];
				$mime_type = $attach_data['type'];
			}
			else
			{
				$id_attach = $this->_req->getQuery('attach', 'intval', -1);

				isAllowedTo('view_attachments');
				$attachment = getAttachmentFromTopic($id_attach, $topic);
				if (empty($attachment))
				{
					// Exit via action_text_to_image
					$this->action_text_to_image('attachment_not_found');
				}

				// Save some typing
				$id_folder = $attachment['id_folder'];
				$real_filename = $attachment['filename'];
				$file_hash = $attachment['file_hash'];
				$file_ext = $attachment['fileext'];
				$id_attach = $attachment['id_attach'];
				$attachment_type = (int) $attachment['attachment_type'];
				$mime_type = $attachment['mime_type'];
				$is_approved = $attachment['approved'];
				$id_member = (int) $attachment['id_member'];

				// If it isn't yet approved, do they have permission to view it?
				if (!$is_approved && ($id_member === 0 || $this->user->id !== $id_member)
					&& ($attachment_type === 0 || $attachment_type === 3))
				{
					isAllowedTo('approve_posts');
				}

				$filename = getAttachmentFilename($real_filename, $id_attach, $id_folder, false, $file_hash);
			}
		}
		catch (\Exception $exception)
		{
			throw new Exception($exception->getMessage(), false);
		}

		$resize = true;

		// Return mime type ala mimetype extension
		if (!str_starts_with(getMimeType($filename), 'image'))
		{
			$checkMime = returnMimeThumb($file_ext);
			$mime_type = 'image/png';
			$resize = false;
			$filename = $checkMime;
		}

		$eTag = '"' . substr($id_attach . $real_filename . filemtime($filename), 0, 64) . '"';
		$do_cache = !(!isset($this->_req->query->image) && getValidMimeImageType($file_ext) !== '');

		$this->prepare_headers($filename, $eTag, $mime_type, 'inline', $real_filename, $do_cache);

		// do not resize for ;image
		if ($resize && !isset($this->_req->query->ila, $this->_req->query->image))
		{
			// Create a thumbnail image
			$image = new Image($filename);

			$filename .= '_thumb';
			$max_width = $this->_req->isSet('thumb') && !empty($modSettings['attachmentThumbWidth']) ? $modSettings['attachmentThumbWidth'] : 300;
			$max_height = $this->_req->isSet('thumb') && !empty($modSettings['attachmentThumbHeight']) ? $modSettings['attachmentThumbHeight'] : 300;

			$image->createThumbnail($max_width, $max_height, $filename, null, false);
		}

		// With the headers complete, send the file data
		$this->send_file($filename, $mime_type);

		obExit(false);
	}
}
