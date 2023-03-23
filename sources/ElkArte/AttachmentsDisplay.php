<?php

/**
 * Handles the job of attachment directory management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

class AttachmentsDisplay
{
	/** @var array The good old attachments array */
	protected $messages = [];

	/** @var array The good old attachments array */
	protected $attachments = [];

	/** @var bool If unapproved posts/attachments should be shown */
	protected $includeUnapproved = false;

	/**
	 * @param int[] $messages
	 * @param int[] $posters
	 * @param bool $includeUnapproved
	 */
	public function __construct($messages, $posters, $includeUnapproved)
	{
		$this->messages = $messages;
		$this->includeUnapproved = $includeUnapproved;

		// Fetch attachments.
		if (allowedTo('view_attachments'))
		{
			// Reminder: this should not be necessary, it removes the current user from the list of posters if not present among the actual list of posters
			if (isset($posters[-1]))
			{
				unset($posters[-1]);
			}

			// The filter returns false when:
			//  - the attachment is unapproved, and
			//  - the viewer is not the poster of the message where the attachment is
			$this->getAttachments(
				$this->messages,
				$this->includeUnapproved,
				static function ($attachment_info, $all_posters) {
					return !(!$attachment_info['approved'] && (!isset($all_posters[$attachment_info['id_msg']]) || $all_posters[$attachment_info['id_msg']] != User::$info->id));
				},
				$posters
			);
		}
	}

	/**
	 * Get all attachments associated with a set of posts.
	 *
	 * What it does:
	 *  - This does not check permissions.
	 *
	 * @param int[] $messages array of messages ids
	 * @param bool $includeUnapproved = false
	 * @param string|null $filter name of a callback function
	 * @param array $all_posters
	 *
	 * @package Attachments
	 */
	protected function getAttachments($messages, $includeUnapproved = false, $filter = null, $all_posters = array())
	{
		global $modSettings;

		$db = database();

		$attachments = array();
		$temp = array();
		$db->fetchQuery('
			SELECT
				a.id_attach, a.id_folder, a.id_msg, a.filename, a.file_hash, COALESCE(a.size, 0) AS filesize, a.downloads, a.approved,
				a.width, a.height' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ',
				COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
				FROM {db_prefix}attachments AS a' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
			WHERE a.id_msg IN ({array_int:message_list})
				AND a.attachment_type = {int:attachment_type}',
			array(
				'message_list' => $messages,
				'attachment_type' => 0,
			)
		)->fetch_callback(
			function ($row) use ($includeUnapproved, $filter, $all_posters, &$attachments, &$temp) {
				if (!$row['approved'] && !$includeUnapproved
					&& (empty($filter) || !call_user_func($filter, $row, $all_posters)))
				{
					return;
				}

				$temp[$row['id_attach']] = $row;

				if (!isset($attachments[$row['id_msg']]))
				{
					$attachments[$row['id_msg']] = array();
				}
			}
		);

		// This is better than sorting it with the query...
		ksort($temp);

		foreach ($temp as $row)
		{
			$attachments[$row['id_msg']][] = $row;
		}

		$this->attachments = $attachments;
	}

	/**
	 * This loads an attachment's contextual data including, most importantly, its size if it is an image.
	 *
	 * What it does:
	 *
	 * - Pre-condition: $attachments array to have been filled with the proper attachment data, as Display() does.
	 * - It requires the view_attachments permission to calculate image size.
	 * - It attempts to keep the "aspect ratio" of the posted image in line, even if it has to be resized by
	 * the max_image_width and max_image_height settings.
	 *
	 * @param int $id_msg message number to load attachments for
	 * @return array of attachments
	 * @todo change this pre-condition, too fragile and error-prone.
	 *
	 * @package Attachments
	 */
	public function loadAttachmentContext($id_msg)
	{
		global $context, $modSettings, $scripturl, $topic;

		// Set up the attachment info - based on code by Meriadoc.
		$attachmentData = [];
		$ilaData = [];
		$have_unapproved = false;

		if (isset($this->attachments[$id_msg]) && !empty($modSettings['attachmentEnable']))
		{
			foreach ($this->attachments[$id_msg] as $i => $attachment)
			{
				$attachmentData[$i] = array(
					'id' => $attachment['id_attach'],
					'name' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($attachment['filename'], ENT_COMPAT, 'UTF-8')),
					'downloads' => $attachment['downloads'],
					'size' => byte_format($attachment['filesize']),
					'byte_size' => $attachment['filesize'],
					'href' => $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'],
					'link' => '<a href="' . $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'] . '">' . htmlspecialchars($attachment['filename'], ENT_COMPAT, 'UTF-8') . '</a>',
					'is_image' => !empty($attachment['width']) && !empty($attachment['height']) && !empty($modSettings['attachmentShowImages']),
					'is_approved' => $attachment['approved'],
					'file_hash' => $attachment['file_hash'],
				);

				// If something is unapproved we'll note it so we can sort them.
				if (!$attachment['approved'])
				{
					$have_unapproved = true;
				}

				if ($attachmentData[$i]['is_image'])
				{
					$this->prepareAttachmentImage($attachmentData[$i], $attachment, $id_msg);
				}

				// If this is an ILA
				if ($attachment['approved']
					&& !empty($context['ila_dont_show_attach_below'])
					&& in_array($attachment['id_attach'], $context['ila_dont_show_attach_below']))
				{
					$ilaData[$i] = $attachmentData[$i];
					unset($attachmentData[$i]);
				}
			}
		}

		// Do we need to instigate a sort?
		if ($have_unapproved)
		{
			// Unapproved attachments go first.
			usort($attachmentData, static function ($a, $b) {
				if ($a['is_approved'] === $b['is_approved'])
				{
					return 0;
				}

				return $a['is_approved'] > $b['is_approved'] ? -1 : 1;
			});
		}

		return [$attachmentData, $ilaData];
	}

	/**
	 * Function that prepares image attachments
	 *
	 * What it does:
	 * - Generates thumbnail if non exists, and they are enabled
	 *
	 * @param $attachmentData
	 * @param $attachment
	 * @param $id_msg
	 * @return void
	 */
	public function prepareAttachmentImage(&$attachmentData, $attachment, $id_msg)
	{
		global $modSettings, $topic;

		$attachmentData['real_width'] = $attachment['width'];
		$attachmentData['width'] = $attachment['width'];
		$attachmentData['real_height'] = $attachment['height'];
		$attachmentData['height'] = $attachment['height'];

		// Let's see, do we want thumbs?
		if (!empty($modSettings['attachmentThumbnails'])
			&& !empty($modSettings['attachmentThumbWidth'])
			&& !empty($modSettings['attachmentThumbHeight'])
			&& ($attachment['width'] > $modSettings['attachmentThumbWidth'] || $attachment['height'] > $modSettings['attachmentThumbHeight']) && strlen($attachment['filename']) < 249)
		{
			// A proper thumb doesn't exist yet? Create one! Or, it needs update.
			if (empty($attachment['id_thumb'])
				|| $attachment['thumb_width'] > $modSettings['attachmentThumbWidth']
				|| $attachment['thumb_height'] > $modSettings['attachmentThumbHeight'])
				//|| ($attachment['thumb_width'] < $modSettings['attachmentThumbWidth'] && $attachment['thumb_height'] < $modSettings['attachmentThumbHeight']))
			{
				$filename = getAttachmentFilename($attachment['filename'], $attachment['id_attach'], $attachment['id_folder'], false, $attachment['file_hash']);
				$attachment = array_merge($attachment, updateAttachmentThumbnail($filename, $attachment['id_attach'], $id_msg, $attachment['id_thumb'], $attachment['filename']));
			}

			// Only adjust dimensions on successful thumbnail creation.
			if (!empty($attachment['thumb_width']) && !empty($attachment['thumb_height']))
			{
				$attachmentData['width'] = $attachment['thumb_width'];
				$attachmentData['height'] = $attachment['thumb_height'];
			}
		}

		// If we have a thumbnail, make note of it!
		if (!empty($attachment['id_thumb']))
		{
			$attachmentData['thumbnail'] = array(
				'id' => $attachment['id_thumb'],
				'href' => getUrl('action', ['action' => 'dlattach', 'topic' => $topic . '.0', 'attach' => $attachment['id_thumb'], 'image']),
			);
		}
		$attachmentData['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

		// If thumbnails are disabled, check the maximum size of the image
		if (!$attachmentData['thumbnail']['has_thumb'] && ((!empty($modSettings['max_image_width']) && $attachment['width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachment['height'] > $modSettings['max_image_height'])))
		{
			if (!empty($modSettings['max_image_width']) && (empty($modSettings['max_image_height']) || $attachment['height'] * $modSettings['max_image_width'] / $attachment['width'] <= $modSettings['max_image_height']))
			{
				$attachmentData['width'] = $modSettings['max_image_width'];
				$attachmentData['height'] = floor($attachment['height'] * $modSettings['max_image_width'] / $attachment['width']);
			}
			elseif (!empty($modSettings['max_image_width']))
			{
				$attachmentData['width'] = floor($attachment['width'] * $modSettings['max_image_height'] / $attachment['height']);
				$attachmentData['height'] = $modSettings['max_image_height'];
			}
		}
		elseif ($attachmentData['thumbnail']['has_thumb'])
		{
			// Data attributes for use in expandThumbLB
			$attachmentData['thumbnail']['lightbox'] = 'data-lightboxmessage="' . $id_msg . '" data-lightboximage="' . $attachment['id_attach'] . '"';
		}

		if (!$attachmentData['thumbnail']['has_thumb'])
		{
			$attachmentData['downloads']++;
		}
	}
}
