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

namespace ElkArte;

use ElkArte\Exceptions\Exception as ElkException;
use ElkArte\Graphics\Image;
use ElkArte\Graphics\ImageUploadResize;

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

		parent::__construct($data);
	}

	/**
	 * Deletes a temporary attachment from the filesystem
	 *
	 * @param bool $fatal
	 * @return bool
	 * @throws \Exception thrown if fatal is true
	 */
	public function remove($fatal = true)
	{
		$this->data['size'] = 0;
		$this->data['type'] = '';

		if ($fatal && !$this->fileWritable())
		{
			throw new \Exception('attachment_not_found');
		}

		return $this->unlinkFile();
	}

	/**
	 * Checks if the file (not a directory) exists, and is editable, in the file system.
	 */
	public function fileWritable()
	{
		$fs = FileFunctions::instance();

		return $fs->fileExists($this->data['tmp_name']) && $fs->isWritable($this->data['tmp_name']);
	}

	/**
	 * Returns an array of names of temporary attachments.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->data['name'];
	}

	/**
	 * Error setter, adds errors to the stack
	 *
	 * @param $error
	 */
	public function setErrors($error)
	{
		$this->data['errors'][] = array_merge($this->data['errors'], (array) $error);
	}

	/**
	 * Return if errors were found for this attachment attempt
	 *
	 * @return bool
	 */
	public function hasErrors()
	{
		return !empty($this->data['errors']);
	}

	/**
	 * Error getter
	 *
	 * @return mixed
	 */
	public function getErrors()
	{
		return $this->data['errors'];
	}

	/**
	 * Return the attachment filesize
	 *
	 * @return int
	 */
	public function getSize()
	{
		return $this->data['size'];
	}

	/**
	 * Return the mime type of the file, if available
	 *
	 * @return string
	 */
	public function getMime()
	{
		return $this->data['mime'] ?? '';
	}

	/**
	 * Checks if the file exists, and is editable, in the file system.
	 */
	public function fileExists()
	{
		return FileFunctions::instance()->fileExists($this->data['tmp_name']);
	}

	/**
	 * Renaming and moving
	 *
	 * @param $file_path
	 */
	public function moveTo($file_path)
	{
		rename($this->data['tmp_name'], $file_path . '/' . $this->data['attachid']);
		$this->data['tmp_name'] = $file_path;
	}

	/**
	 * Move a file from one location to another.  Generally used to move from /tmp
	 * to the current attachment directory
	 *
	 * @param $file_path
	 */
	public function moveUploaded($file_path)
	{
		$destName = $file_path . '/' . $this->data['attachid'];

		// Move the file to the attachment folder with a temp name for now.
		if (@move_uploaded_file($this->data['tmp_name'], $destName))
		{
			$this->data['tmp_name'] = $destName;
			FileFunctions::instance()->chmod($destName);
		}
		else
		{
			$this->setErrors('attach_timeout');
			$this->unlinkFile();
		}
	}

	public function setIdFolder($id)
	{
		$this->data['id_folder'] = $id;
	}

	/**
	 * Performs various checks on an uploaded file.
	 *
	 * @param \ElkArte\AttachmentsDirectory $attachmentDirectory
	 * @throws \ElkArte\Exceptions\Exception attach_check_nag
	 * @return bool
	 */
	public function doChecks($attachmentDirectory)
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
		if ($this->data['size'] == 0)
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
	public function checkImageContents()
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
			catch (\Exception $e)
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
	public function adjustImageSizeType()
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
	 * based on input image
	 *
	 * @return void
	 */
	public function convertFromWebp()
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
	 * @param \ElkArte\AttachmentsDirectory $attachmentDirectory
	 */
	public function checkDirectorySpace($attachmentDirectory)
	{
		try
		{
			$attachmentDirectory->checkDirSpace($this);
		}
		catch (\Exception $e)
		{
			$this->setErrors($e->getMessage());
		}
	}

	/**
	 * Is the file larger than we accept
	 */
	public function checkFileSize()
	{
		global $modSettings;

		// Is the file too big?
		if (!empty($modSettings['attachmentSizeLimit'])
			&& $this->data['size'] > $modSettings['attachmentSizeLimit'] * 1024)
		{
			$this->setErrors([
				'file_too_big', [
					comma_format($modSettings['attachmentSizeLimit'], 0)
				]
			]);
		}
	}

	/**
	 * Check if they are sending too much data in a single post
	 */
	public function checkTotalUploadSize()
	{
		global $context, $modSettings;

		// Check the total upload size for this post...
		$context['attachments']['total_size'] += $this->data['size'];
		if (!empty($modSettings['attachmentPostLimit'])
			&& $context['attachments']['total_size'] > $modSettings['attachmentPostLimit'] * 1024)
		{
			$this->setErrors([
				'attach_max_total_file_size', [
					comma_format($modSettings['attachmentPostLimit'], 0),
					comma_format($modSettings['attachmentPostLimit'] - (($context['attachments']['total_size'] - $this->data['size']) / 1024), 0)
				]
			]);
		}
	}

	/**
	 * Check if they are sending too many files at once
	 */
	public function checkTotalUploadCount()
	{
		global $context, $modSettings;

		// Have we reached the maximum number of files we are allowed?
		$context['attachments']['quantity']++;

		// Set a max limit if none exists
		if (empty($modSettings['attachmentNumPerPostLimit']) && $context['attachments']['quantity'] >= 15)
		{
			$modSettings['attachmentNumPerPostLimit'] = 15;
		}

		if (!empty($modSettings['attachmentNumPerPostLimit'])
			&& $context['attachments']['quantity'] > $modSettings['attachmentNumPerPostLimit'])
		{
			$this->setErrors([
				'attachments_limit_per_post', [
					$modSettings['attachmentNumPerPostLimit']
				]
			]);
		}
	}

	/**
	 * If enabled, check if this is a filetype we accept (by extension)
	 */
	public function checkFileExtensions()
	{
		global $modSettings;

		// File extension check
		if (!empty($modSettings['attachmentCheckExtensions']))
		{
			$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
			$allowed = array_map('trim', $allowed);

			if (!in_array(strtolower(substr(strrchr($this->data['name'], '.'), 1)), $allowed))
			{
				$allowed_extensions = strtr(strtolower($modSettings['attachmentExtensions']), array(',' => ', '));
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
	public function autoRotate()
	{
		if ($this->hasErrors() === false && substr($this->data['type'], 0, 5) === 'image')
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
	private function unlinkFile()
	{
		try
		{
			if (!$this->fileWritable())
			{
				throw new \Exception('attachment_not_found');
			}

			FileFunctions::instance()->delete($this->data['tmp_name']);
		}
		catch (\Exception $e)
		{
			return false;
		}

		return true;
	}
}
