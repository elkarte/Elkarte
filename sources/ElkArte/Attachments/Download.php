<?php

/**
 * This is the file that takes care of sending the bits to the browser
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

namespace ElkArte\Attachments;

use ElkArte\Graphics\TextImage;
use ElkArte\Graphics\Image;
use ElkArte\Exceptions\Exception;
use ElkArte\AttachmentsDirectory;
use ElkArte\TemporaryAttachmentsList;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\HttpReq;

/**
 * This class takes one file and if it exists it sends it back to the browser that requesetd it
 */
class Download
{
	protected $string_attach = '';
	protected $id_attach = 0;
	protected $file_path = null;
	protected $data = [];
	protected $db = null;
	protected $thumb_suffix = '';

	/**
	 * Starts up the download process
	 *
	 * @param string $id_attach String version of the attachment id
	 */
	public function __construct($id_attach)
	{
		$this->string_attach = $id_attach;
		// Non-temporary attachments shall have integer ids
		if (!$this->isTemporary())
		{
			$this->id_attach = (int) $id;
		}
		$this->db = database();
	}

	/**
	 * Temporary attachments have special names, so need slightly special handling
	 */
	public function isTemporary()
	{
		// Temporary attachment, special case...
		return strpos($this->string_attach, 'post_tmp_' . User::$info->id . '_') !== false;
	}

	/**
	 * Fetches data from the db and determine if the attachment actually exists
	 *
	 * @param null|string $text
	 * @param null|int $id_topic
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function validate($type, $id_topic = null)
	{
		if (empty($this->id_attach))
		{
			return false;
		}

		if ($this->isTemporary())
		{
			return $this->validateTemporary();
		}
		$this->data = $this->getFromTopic($id_topic);

		$this->data['id_folder'] = $this->data['id_folder'] ?? '';
		$this->data['real_filename'] = $this->data['filename'] ?? '';
		$this->data['file_hash'] = $this->data['file_hash'] ?? '';
		$this->data['file_ext'] = $this->data['fileext'] ?? '';
		$this->data['id_attach'] = $this->data['id_attach'] ?? '';
		$this->data['attachment_type'] = $this->data['attachment_type'] ?? '';
		$this->data['mime_type'] = $this->data['mime_type'] ?? '';
		$this->data['is_approved'] = $this->data['approved'] ?? '';
		$this->data['id_member'] = $this->data['id_member'] ?? '';

		if ($type === Attachment::DL_TYPE_THUMB)
		{
			$this->thumb_suffix = '_' . Attachment::DL_TYPE_THUMB;
		}

		return !empty($this->data['real_filename']);
	}

	/**
	 * Same as validate, but for temporary attachments
	 */
	protected function validateTemporary()
	{
		global $modSettings;

		$tmp_attachments = new TemporaryAttachmentsList();
		$attachmentsDir = new AttachmentsDirectory($modSettings, $this->db);

		try
		{
			$this->data = $tmp_attachments->getTempAttachById($this->_req->query->attach, $attachmentsDir, User::$info->id);
			$this->data['file_ext'] = pathinfo($this->data['name'], PATHINFO_EXTENSION);
			$this->file_path = $this->data['tmp_name'];
			$this->data['id_attach'] = $this->data['attachid'];
			$this->data['real_filename'] = $this->data['name'];
			$this->data['mime_type'] = $this->data['type'];
		}
		catch (\Exception $e)
		{
			throw new Exception($e->getMessage(), false);
		}

		$this->data['resize'] = true;

		// Return mime type ala mimetype extension
		if (substr(getMimeType($this->file_path), 0, 5) !== 'image')
		{
			$checkMime = returnMimeThumb($file_ext);
			$mime_type = 'image/png';
			$this->data['resize'] = false;
			$this->file_path = $checkMime;
		}

		return !empty($this->data['real_filename']);
	}

	/**
	 * Reads and returns the data of the image
	 *
	 * @param bool $inline
	 * @param bool $use_compression
	 */
	public function send($inline, $use_compression)
	{
		if (empty($this->id_attach) || empty($this->data['real_filename']))
		{
			return $this->noAttach();
		}

		if ($this->file_path === null)
		{
			$this->file_path = getAttachmentFilename($this->data['real_filename'], $this->id_attach, $this->data['id_folder'], false, $this->data['file_hash']) . $this->thumb_suffix;
		}

		$eTag = '"' . substr($this->id_attach . $this->data['real_filename'] . @filemtime($this->file_path), 0, 64) . '"';
		$disposition = $inline ? 'inline' : 'attachment';
		$do_cache = !($inline === false && getValidMimeImageType($this->data['file_ext']) !== '');

		// Make sure the mime type warrants an inline display.
		if ($inline && !empty($this->data['mime_type']) && strpos($this->data['mime_type'], 'image/') !== 0)
		{
			$this->data['mime_type'] = '';
		}
		// Does this have a mime type?
		elseif (empty($this->data['mime_type']) || $inline === false && getValidMimeImageType($this->data['file_ext']) !== '')
		{
			$this->data['mime_type'] = '';
		}
		$this->prepare_headers($this->file_path, $eTag, $this->data['mime_type'], $disposition, $this->data['real_filename'], $do_cache);

		if (!empty($this->data['resize']))
		{
			// Create a thumbnail image
			$image = new Image($this->file_path);

			$this->file_path = $this->file_path . '_thumb';
			$image->createThumbnail(100, 100, $this->file_path, '',false);
		}

		return $this->send_file($use_compression && $this->isCompressible());
	}

	/**
	 * Is the atachment is approved or not
	 */
	public function isApproved()
	{
		return !empty($this->data['is_approved']);
	}

	/**
	 * If the user requesting the attachment is its owner
	 */
	public function isOwner()
	{
		return (int) $this->data['id_member'] !== 0 && User::$info->id === (int) $this->data['id_member'];
	}

	/**
	 * If the attachment is an avatar
	 */
	public function isAvatar()
	{
		return $this->data['attachment_type'] == Attachment::DB_TYPE_AVATAR;
	}

	/**
	 * Generates a language image based on text for display, outputs image and exits
	 *
	 * @param null|string $text
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function noAttach($text = null)
	{
		global $txt;

		if ($text === null)
		{
			Txt::load('Errors');
			$text = $txt['attachment_not_found'];
		}

		try
		{
			$img = new TextImage($text);
			$img = $img->generate(200);
		}
		catch (\Exception $e)
		{
			throw new Exception('no_access', false);
		}

		$this->prepare_headers('no_image', 'no_image', 'image/png', 'inline', 'no_image.png', true, false);

		Headers::instance()->sendHeaders();

		return $img;
	}

	/**
	 * Increase download counter for id_attach.
	 *
	 * What it does:
	 *
	 * - Does not check if it's a thumbnail.
	 *
	 * @param int $id_attach
	 * @package Attachments
	 */
	public function increaseDownloadCounter()
	{
		if (empty($this->id_attach) || $this->isAvatar())
		{
			return;
		}

		$this->db->fetchQuery('
			UPDATE {db_prefix}attachments
			SET downloads = downloads + 1
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $this->id_attach,
			)
		);
	}

	/**
	 * Sends the requested file to the user.  If the file is compressible e.g.
	 * has a mine type of text/??? may compress the file prior to sending.
	 */
	protected function send_file($use_compression)
	{
		$headers = Headers::instance();
		$body = file_get_contents($this->file_path);

		// If we can/should compress this file
		if ($use_compression && strlen($body) > 250)
		{
			$body = gzencode($body, 2);
			$headers
				->header('Content-Encoding', 'gzip')
				->header('Vary', 'Accept-Encoding');
		}

		// Someone is getting a present
		$headers->header('Content-Length', strlen($body));
		$headers->send();
		return $body;
	}

	/**
	 * If the mime type benefits from compression e.g. text/xyz and gzencode is
	 * available and the user agent accepts gzip, then return true, else false
	 *
	 * @return bool if we should compress the file
	 */
	protected function isCompressible()
	{
		// Not compressible, or not supported / requested by client
		return (bool) preg_match('~^(?:text/|application/(?:json|xml|rss\+xml)$)~i', $this->data['mime_type']);
	}

	/**
	 * Takes care of sending out the most common headers.
	 *
	 * @param string $filename Full path+file name of the file in the filesystem
	 * @param string $eTag ETag cache validator
	 * @param string $mime_type The mime-type of the file
	 * @param string $disposition The value of the Content-Disposition header
	 * @param string $real_filename The original name of the file
	 * @param bool $do_cache If send the a max-age header or not
	 * @param bool $check_filename When false, any check on $filename is skipped
	 */
	protected function prepare_headers($filename, $eTag, $mime_type, $disposition, $real_filename, $do_cache, $check_filename = true)
	{
		global $txt;

		$headers = Headers::instance();
		$request = HttpReq::instance();

		// No point in a nicer message, because this is supposed to be an attachment anyway...
		if ($check_filename && !file_exists($filename))
		{
			Txt::load('Errors');

			$protocol = preg_match('~HTTP/1\.[01]~i', $request->server->SERVER_PROTOCOL) ? $request->server->SERVER_PROTOCOL : 'HTTP/1.0';
			$headers
				->removeHeader('all')
				->headerSpecial($protocol . ' 404 Not Found')
				->sendHeaders();

			// We need to die like this *before* we send any anti-caching headers as below.
			die('404 - ' . $txt['attachment_not_found']);
		}

		// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
		if (!empty($request->server->HTTP_IF_MODIFIED_SINCE))
		{
			list ($modified_since) = explode(';', $request->server->HTTP_IF_MODIFIED_SINCE);
			if (!$check_filename || strtotime($modified_since) >= filemtime($filename))
			{
				@ob_end_clean();

				// Answer the question - no, it hasn't been modified ;).
				$headers
					->removeHeader('all')
					->headerSpecial('HTTP/1.1 304 Not Modified')
					->sendHeaders();
				exit;
			}
		}

		// Check whether the ETag was sent back, and cache based on that...
		if (!empty($request->server->HTTP_IF_NONE_MATCH) && strpos($request->server->HTTP_IF_NONE_MATCH, $eTag) !== false)
		{
			@ob_end_clean();

			$headers
				->removeHeader('all')
				->headerSpecial('HTTP/1.1 304 Not Modified')
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
	 * Some magic in case data are not available immediately
	 */
	protected function rebuildData($id_topic)
	{
		global $modSettings;

		$full_attach = $this->getFromTopic($id_topic);
		$attachment = [
			'filename' => !empty($full_attach['filename']) ? $full_attach['filename'] : '',
			'id_attach' => 0,
			'attachment_type' => 0,
			'approved' => $full_attach['approved'],
			'id_member' => $full_attach['id_member']
		];

		// If it is a known extension, show a mimetype extension image
		$check = returnMimeThumb(!empty($full_attach['fileext']) ? $full_attach['fileext'] : 'default');
		if ($check !== false)
		{
			$attachment['fileext'] = 'png';
			$attachment['mime_type'] = 'image/png';
			$this->file_path = $check;
		}
		else
		{
			$attachmentsDir = new AttachmentsDirectory($modSettings, $this->db);
			$this->file_path = $attachmentsDir->getCurrent() . '/' . $attachment['filename'];
		}

		if (substr(getMimeType($this->file_path), 0, 5) !== 'image')
		{
			$attachment['fileext'] = 'png';
			$attachment['mime_type'] = 'image/png';
			$this->file_path = $settings['theme_dir'] . '/images/mime_images/default.png';
		}
		return $attachment;
	}

	/**
	 * Get the specified attachment.
	 *
	 * What it does:
	 *
	 * - This includes a check of the topic
	 * - it only returns the attachment if it's indeed attached to a message in the topic given as parameter, and
	 * query_see_board...
	 * - Must return the same array keys as getThumbFromTopic()
	 *
	 * @param int $id_topic
	 *
	 * @return array
	 * @package Attachments
	 */
	protected function getFromTopic($id_topic)
	{
		// Make sure this attachment is on this board.
		$attachmentData = array();
		$request = $this->db->fetchQuery('
			SELECT 
				a.id_folder, a.filename, a.file_hash, a.fileext, a.id_attach, a.attachment_type, 
				a.mime_type, a.approved, m.id_member
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg AND m.id_topic = {int:current_topic})
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND 1=1)
			WHERE a.id_attach = {int:attach}
			LIMIT 1',
			array(
				'attach' => $this->id_attach,
				'current_topic' => $id_topic,
			)
		);
		if ($request->num_rows() != 0)
		{
			$attachmentData = $request->fetch_assoc();
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
	 * - Must return the same array keys as getFromTopic()
	 *
	 * @param int $id_topic
	 *
	 * @return array
	 * @package Attachments
	 */
	function getThumbFromTopic($id_topic)
	{
		// Make sure this attachment is on this board.
		$request = $this->db->fetchQuery('
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
				'attach' => $this->id_attach,
				'current_topic' => $id_topic,
			)
		);
		$attachmentData = [
			'id_folder' => '', 'filename' => '', 'file_hash' => '', 'fileext' => '', 'id_attach' => '',
			'attachment_type' => '', 'mime_type' => '', 'approved' => '', 'id_member' => ''];
		if ($request->num_rows() != 0)
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
}