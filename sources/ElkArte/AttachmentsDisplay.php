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
	/** @var The good old attachments array */
	protected $messages = [];

	/** @var mixed[] The good old attachments array */
	protected $attachments = [];

	/** @var bool If unapproved posts/attachments should be shown */
	protected $includeUnapproved = false;

	/**
	 * @param int[] $messages
	 * @param int[] $posters
	 * @param bool $includeUnapproved
	 */
	public function __construct($messages, $posters,  $includeUnapproved)
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
			$this->getAttachments($this->messages, $this->includeUnapproved, static function ($attachment_info, $all_posters) {
				return !(!$attachment_info['approved'] && (!isset($all_posters[$attachment_info['id_msg']]) || $all_posters[$attachment_info['id_msg']] != User::$info->id));
			}, $posters);
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
	 * @param mixed[] $all_posters
	 *
	 * @return array
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
		$attachmentData = array();
		$have_unapproved = false;
		if (isset($this->attachments[$id_msg]) && !empty($modSettings['attachmentEnable']))
		{
			foreach ($this->attachments[$id_msg] as $i => $attachment)
			{
				if (!empty($context['ila_dont_show_attach_below']) && in_array($attachment['id_attach'], $context['ila_dont_show_attach_below']))
				{
					continue;
				}
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

				if (!$attachmentData[$i]['is_image'])
				{
					continue;
				}

				$attachmentData[$i]['real_width'] = $attachment['width'];
				$attachmentData[$i]['width'] = $attachment['width'];
				$attachmentData[$i]['real_height'] = $attachment['height'];
				$attachmentData[$i]['height'] = $attachment['height'];

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
						$attachmentData[$i]['width'] = $attachment['thumb_width'];
						$attachmentData[$i]['height'] = $attachment['thumb_height'];
					}
				}

				if (!empty($attachment['id_thumb']))
				{
					$attachmentData[$i]['thumbnail'] = array(
						'id' => $attachment['id_thumb'],
						'href' => getUrl('action', ['action' => 'dlattach', 'topic' => $topic . '.0', 'attach' => $attachment['id_thumb'], 'image']),
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
					// Data attributes for use in expandThumb
					$attachmentData[$i]['thumbnail']['lightbox'] = 'data-lightboxmessage="' . $id_msg . '" data-lightboximage="' . $attachment['id_attach'] . '"';

					/*
					// If the image is too large to show inline, make it a popup.
					// @todo this needs to be removed or depreciated
					if (((!empty($modSettings['max_image_width']) && $attachmentData[$i]['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachmentData[$i]['real_height'] > $modSettings['max_image_height'])))
					{
						$attachmentData[$i]['thumbnail']['javascript'] = 'return reqWin(\'' . $attachmentData[$i]['href'] . ';image\', ' . ($attachment['width'] + 20) . ', ' . ($attachment['height'] + 20) . ', true);';
					}
					else
					{
						$attachmentData[$i]['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['id_attach'] . ');';
					}
					*/
				}

				if (!$attachmentData[$i]['thumbnail']['has_thumb'])
				{
					$attachmentData[$i]['downloads']++;
				}
			}
		}

		// Do we need to instigate a sort?
		if ($have_unapproved)
		{
			// Unapproved attachments go first.
			usort($attachmentData, function($a, $b) {
				if ($a['is_approved'] === $b['is_approved'])
				{
					return 0;
				}

				return $a['is_approved'] > $b['is_approved'] ? -1 : 1;
			});
		}

		return $attachmentData;
	}
}
