<?php

/**
 * Handles the preparing of attachments from the post form.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Attachments;

use ElkArte\Exceptions\Exception as ElkException;
use ElkArte\Graphics\Image;
use ElkArte\Graphics\ImageUploadResize;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;

/**
 * TemporaryAttachment value bag for attachments
 */
class TemporaryAttachment extends ValuesContainer
{
	/**
	 * {@inheritDoc}
	 */
	public function __construct($data = null)
	{
		$data['errors'] = [];
		$data['name'] = Util::clean_4byte_chars(htmlspecialchars($data['name'], ENT_COMPAT, 'UTF-8'));

		ValuesContainer::__construct($data);
	}

	/**
	 * Deletes a temporary attachment from the filesystem
	 *
	 * @param bool $fatal
	 * @return bool
	 * @throws ElkException thrown if fatal is true
	 */
	public function remove(bool $fatal = true): bool
	{
		$this->data['size'] = 0;
		$this->data['type'] = '';

		if ($fatal && !$this->fileWritable())
		{
			throw new ElkException('attachment_not_found');
		}

		return $this->unlinkFile();
	}

	/**
	 * Checks if the file (not a directory) exists, and is editable, in the file system.
	 */
	public function fileWritable(): bool
	{
		$path = $this->data['tmp_name'] ?? '';
		if ($path === '')
		{
			return false;
		}

		$fs = FileFunctions::instance();
		return $fs->fileExists($path) && $fs->isWritable($path);
	}

	/**
	 * Returns an array of names of temporary attachments.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->data['name'];
	}

	/**
	 * Error setter, adds errors to the stack
	 *
	 * @param $error
	 */
	public function setErrors($error): void
	{
		// Normalize input to a flat list of error items where each item is an array:
		// [code] or [code, [args]]
		if ($error === null || $error === '')
		{
			return;
		}

		$append = function ($item) {
			if ($item === null || $item === '')
			{
				return;
			}

			// If a string, wrap as [code]
			if (!is_array($item))
			{
				$this->data['errors'][] = [$item];
				return;
			}

			// If it looks like a single error tuple [code, [args]] or [code]
			// keep as-is; otherwise, best-effort wrap
			if (isset($item[0]) && (is_string($item[0]) || is_scalar($item[0])))
			{
				// If args present but not an array, wrap it
				if (isset($item[1]) && !is_array($item[1]))
				{
					$item[1] = [$item[1]];
				}

				$this->data['errors'][] = $item;
				return;
			}

			// Fallback: wrap whole structure as a single error payload
			$this->data['errors'][] = [$item];
		};

		// If we received a list of errors (mixed strings and arrays), append each
		if (is_array($error) && !(isset($error[0]) && is_string($error[0]) && (count($error) === 1 || (count($error) === 2 && isset($error[1]) && is_array($error[1])))))
		{
			foreach ($error as $e)
			{
				$append($e);
			}
		}
		else
		{
			$append($error);
		}
	}

	/**
	 * Return if errors were found for this attachment attempt
	 *
	 * @return bool
	 */
	public function hasErrors(): bool
	{
		return !empty($this->data['errors']);
	}

	/**
	 * Error getter
	 *
	 * @return array
	 */
	public function getErrors(): mixed
	{
		return $this->data['errors'];
	}

	/**
	 * Return the attachment filesize
	 *
	 * @return int
	 */
	public function getSize(): int
	{
		return $this->data['size'];
	}

	/**
	 * Return the mime type of the file, if available
	 *
	 * @return string
	 */
	public function getMime(): string
	{
		return $this->data['mime'] ?? '';
	}

	/**
	 * Checks if the file exists, and is editable, in the file system.
	 */
	public function fileExists(): bool
	{
		$path = $this->data['tmp_name'] ?? '';
		return $path !== '' && FileFunctions::instance()->fileExists($path);
	}

	/**
	 * Renaming and moving
	 *
	 * @param $file_path
	 */
	public function moveTo($file_path): void
	{
		$destination = $file_path . '/' . $this->data['attachid'];
		rename($this->data['tmp_name'], $destination);
		$this->data['tmp_name'] = $destination;
	}

	/**
	 * Moves the uploaded file to a specified destination folder.
	 *
	 * @param string $file_path The destination folder path.
	 * @param bool $strict Determines whether to use strict file moving or not.
	 * @return bool Returns true if the file is moved successfully, false otherwise.
	 */
	public function moveUploaded(string $file_path, bool $strict = true): bool
	{
		$destName = $file_path . '/' . $this->data['attachid'];

		if (!$strict)
		{
			$result = rename($this->data['tmp_name'], $destName);
		}
		else
		{
			// Move the file to the attachment folder with a temp name for now.
			set_error_handler(static function () { /* ignore warnings */ });
			try
			{
				$result = move_uploaded_file($this->data['tmp_name'], $destName);
			}
			catch (\Throwable)
			{
				$result = false;
			}
			finally
			{
				restore_error_handler();
			}
		}

		if ($result === true)
		{
			$this->data['tmp_name'] = $destName;
			FileFunctions::instance()->chmod($destName);

			return true;
		}

		$this->setErrors('attach_timeout');
		$this->unlinkFile();

		return false;
	}

	/**
	 * Sets the folder ID value in the object's data.
	 *
	 * @param int $id The ID to be assigned to the folder.
	 * @return void
	 */
	public function setIdFolder(int $id): void
	{
		$this->data['id_folder'] = $id;
	}

	/**
	 * Performs various checks on an uploaded file.
	 *
	 * @param AttachmentsDirectory $attachmentDirectory
	 * @return bool
	 * @throws ElkException attach_check_nag
	 */
	public function doElkarteUploadChecks(AttachmentsDirectory $attachmentDirectory): bool
	{
		global $context;

		// If there were already errors at this point, no need to check further
		if (!empty($this->data['errors']))
		{
			return false;
		}

		// Apply some additional checks
		if (empty($this->data['attachid']))
		{
			$error = 'attachid';
		}
		// @TODO this needs to go away, not sure where though.
		elseif (empty($context['attachments']))
		{
			$error = '$context[\'attachments\']';
		}

		// Let's get their attention.
		if (!empty($error))
		{
			throw new ElkException('attach_check_nag', 'debug', [$error]);
		}

		// Just in case this slipped by the first checks, we stop it here and now
		if ($this->data['size'] === 0)
		{
			$this->setErrors('attach_0_byte_file');

			return false;
		}

		// Allow addons to make their own pre checks / adjustments
		call_integration_hook('integrate_attachment_checks', [$this->data['attachid']]);

		// Did you pack this bag yourself?
		$this->checkImageContents();

		// WebP may require special processing that will affect size/type
		$this->convertFromWebp();

		// We may allow resizing uploaded images, so they take less room
		$this->adjustImageSizeType();

		// We may want to correct rotated images
		$this->autoRotate();

		// Run our batch of tests, set any errors along the way
		$this->checkDirectorySpace($attachmentDirectory);
		$this->checkFileSize();
		$this->checkTotalUploadSize();
		$this->checkTotalUploadCount();
		$this->checkFileExtensions();

		// Undo the math if there's an error
		if ($this->hasErrors())
		{
			if (isset($context['dir_size']))
			{
				$context['dir_size'] -= $this->data['size'];
			}

			if (isset($context['dir_files']))
			{
				$context['dir_files']--;
			}

			$context['attachments']['total_size'] -= $this->data['size'];
			$context['attachments']['quantity']--;

			return false;
		}

		return true;
	}

	/**
	 * If we have a valid image type, inspect to see if there is any
	 * injected code fragments.  If found re encode to remove those fragments
	 */
	public function checkImageContents(): void
	{
		global $modSettings;

		// First, the dreaded security check. Sorry folks, but this should't be avoided
		$image = new Image($this->data['tmp_name']);
		if ($image->isImageLoaded())
		{
			$this->data['imagesize'] = $image->getImageDimensions();
			$this->data['size'] = $image->getFilesize();
			try
			{
				if (!$image->checkImageContents())
				{
					// It's bad. Last chance, maybe we can re-encode it?
					if (empty($modSettings['attachment_image_reencode']) || (!$image->reEncodeImage()))
					{
						// Nothing to do: not allowed or not successful re-encoding it.
						$this->setErrors('bad_attachment');
						$this->data['imagesize'] = [];
					}
					else
					{
						$this->data['size'] = $image->getFilesize();
					}
				}
			}
			catch (\Exception)
			{
				$this->setErrors('bad_attachment');
			}
		}

		unset($image);
	}

	/**
	 * If enabled, call the attachment image resizing functions.  These reduce the image WxH
	 * and potentially change the format in order to reduce size.
	 */
	public function adjustImageSizeType(): void
	{
		global $modSettings;

		// Auto resize enabled, then do sizing manipulations up front
		if (!empty($modSettings['attachmentSizeLimit']) && !empty($modSettings['attachment_image_resize_enabled']))
		{
			$autoSizer = new ImageUploadResize();
			$autoSizer->autoResize($this->data);
		}
	}

	/**
	 * If the admin does not want to save webP (attachment_webp_enable is off) but they accept
	 * webp extensions and the server has webp capabilities, then webP -> PNG or -> JPG (best choice)
	 * based on the input image
	 *
	 * @return void
	 */
	public function convertFromWebp(): void
	{
		global $modSettings;

		// We may have to adjust for webp based on ACP settings
		if (empty($this->data['imagesize'][2])
			|| $this->data['imagesize'][2] !== IMAGETYPE_WEBP
			|| !empty($modSettings['attachment_webp_enable'])
			|| (!empty($modSettings['attachmentCheckExtensions']) && stripos($modSettings['attachmentExtensions'], ',webp') === false))
		{
			return;
		}

		// Is a webp image and manipulation is possible?
		$image = new Image($this->data['tmp_name']);
		if ($image->hasWebpSupport())
		{
			$format = $image->getDefaultFormat();
			if ($image->isImageLoaded() && $image->saveImage($this->data['tmp_name'], $format))
			{
				$valid_mime = getValidMimeImageType($format);
				$ext = str_replace('jpeg', 'jpg', substr($valid_mime, strpos($valid_mime, '/') + 1));

				// Update to what it now is (webp to png or jpg)
				$update = [
					'size' => $image->getFilesize(),
					'imagesize' => $image->getImageDimensions(),
					'type' => $valid_mime,
					'mime' => $valid_mime,
					'name' => $this->data['name'] . '.' . $ext
				];

				$this->data = array_merge($this->data, $update);
			}
		}
	}

	/**
	 * Is there room in the directory for this file
	 *
	 * @param AttachmentsDirectory $attachmentDirectory
	 */
	public function checkDirectorySpace(AttachmentsDirectory $attachmentDirectory): void
	{
		try
		{
			$attachmentDirectory->checkDirSpace($this);
		}
		catch (\Exception $exception)
		{
			$this->setErrors($exception->getMessage());
		}
	}

	/**
	 * Is the file larger than we accept
	 */
	public function checkFileSize(): void
	{
		global $modSettings;

		// Is the file too big?
		if (empty($modSettings['attachmentSizeLimit']))
		{
			return;
		}

		if ($this->data['size'] <= $modSettings['attachmentSizeLimit'] * 1024)
		{
			return;
		}

		$this->setErrors([
			'file_too_big', [
				comma_format($modSettings['attachmentSizeLimit'], 0)
			]
		]);
	}

	/**
	 * Check if they are sending too much data in a single post
	 */
	public function checkTotalUploadSize(): void
	{
		global $context, $modSettings;

		// Check the total upload size for this post...
		$context['attachments']['total_size'] += $this->data['size'];
		if (empty($modSettings['attachmentPostLimit']))
		{
			return;
		}

		if ($context['attachments']['total_size'] <= $modSettings['attachmentPostLimit'] * 1024)
		{
			return;
		}

		$this->setErrors([
			'attach_max_total_file_size', [
				comma_format($modSettings['attachmentPostLimit'], 0),
				comma_format($modSettings['attachmentPostLimit'] - (($context['attachments']['total_size'] - $this->data['size']) / 1024), 0)
			]
		]);
	}

	/**
	 * Check if they are sending too many files at once
	 */
	public function checkTotalUploadCount(): void
	{
		global $context, $modSettings;

		// Have we reached the maximum number of files we are allowed?
		$context['attachments']['quantity']++;

		// Set a max limit if none exists
		if (empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] >= 15)
		{
			$modSettings['attachmentNumPerPostLimit'] = 15;
		}

		if (empty($modSettings['attachmentNumPerPostLimit']))
		{
			return;
		}

		if ($context['attachments']['quantity'] <= $modSettings['attachmentNumPerPostLimit'])
		{
			return;
		}

		$this->setErrors([
			'attachments_limit_per_post', [
				$modSettings['attachmentNumPerPostLimit']
			]
		]);
	}

	/**
	 * If enabled, check if this is a filetype we accept (by extension)
	 */
	public function checkFileExtensions(): void
	{
		global $modSettings;

		// File extension check
		if (!empty($modSettings['attachmentCheckExtensions']))
		{
			$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
			$allowed = array_map('trim', $allowed);

			if (!in_array(strtolower(substr(strrchr($this->data['name'], '.'), 1)), $allowed, true))
			{
				$allowed_extensions = strtr(strtolower($modSettings['attachmentExtensions']), [',' => ', ']);
				$this->setErrors([
					'cant_upload_type', [
						$allowed_extensions
					]
				]);
			}
		}
	}

	/**
	 * Rotate an image top side up based on its EXIF data
	 */
	public function autoRotate(): void
	{
		global $modSettings;

		// Want to correct for phone rotated photos, hell yeah ya do!
		if (!empty($modSettings['attachment_autorotate'])
			&& $this->hasErrors() === false && strpos($this->data['type'], 'image') === 0)
		{
			$image = new Image($this->data['tmp_name']);
			if ($image->isImageLoaded() && $image->autoRotate())
			{
				$image->saveImage($this->data['tmp_name'], IMAGETYPE_JPEG, 95);
				$this->data['size'] = filesize($this->data['tmp_name']);
			}
		}
	}

	/**
	 * Checks if a file existence/permission and if granted will attempt
	 * to remove/unlink the file.
	 *
	 * @return bool
	 */
	private function unlinkFile(): bool
	{
		try
		{
			if (!$this->fileWritable())
			{
				throw new \Exception('attachment_not_found');
			}

			$fs = FileFunctions::instance();
			$path = $this->data['tmp_name'];

			// Best-effort deletes; ignore missing thumb
			$fs->delete($path);
			$thumb = $path . '_thumb';
			if ($fs->fileExists($thumb))
			{
				$fs->delete($thumb);
			}
		}
		catch (\Exception)
		{
			return false;
		}

		return true;
	}
}
