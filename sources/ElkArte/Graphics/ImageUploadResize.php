<?php

/**
 * This class deals with the resizing of attachment images during the upload process.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Graphics;

class ImageUploadResize
{
	/** @var array Holds the current image size / type values */
	protected $_sizeCurrent;

	/** @var int[] Holds the WxH bounds an image must be within */
	protected $_bounds;

	/** @var Image The image we are working with */
	protected $image;

	/** @var string the full path name to the image */
	protected $_imageName;

	/** @var string the filename of the image */
	protected $_fileName;

	/**
	 * Sets the WxH bounds for an image and prepares for the resize call
	 *
	 * What it does:
	 *
	 * - Loads the current image information, most importantly its size and format
	 * - Sets the WxH bounds based on ACP settings
	 * - Forwards to the proper resizer (same or new format)
	 *
	 * @param array $fileData
	 */
	public function autoResize(&$fileData): bool
	{
		global $modSettings;

		// Only attempt resizing on proper images
		if (!empty($fileData['imagesize'][2]) && $fileData['imagesize'][2] !== -1)
		{
			$this->_sizeCurrent = $fileData['imagesize'];
			$this->_imageName = $fileData['tmp_name'];
			$this->_fileName = $fileData['name'];
			$this->image = new Image($fileData['tmp_name']);

			// Bounds to use for constraining this image
			$this->_setBounds();

			// Attempt to WxH resize maintaining the existing format
			$success = $this->image->isImageLoaded() && $this->resize();
			if ($success)
			{
				$fileData = array_merge($fileData, $this->updateSizing());

				// IF still over the filesize limit only now attempt to change the format
				if (!empty($modSettings['attachmentSizeLimit'])
					&& $this->image->getFilesize() > $modSettings['attachmentSizeLimit'] * 1024
					&& $this->canChangeFormat())
				{
					// Work with the newly resized image
					$this->image = new Image($fileData['tmp_name']);
					$success = $this->image->isImageLoaded() && $this->resize(false);
					if ($success)
					{
						$fileData = array_merge($fileData, $this->updateSizing(false));
					}
				}

				unset($this->image);

				return $success;
			}
		}

		return false;
	}

	/**
	 * Sets the width X height bounds for resizing, ensuring we never scale up
	 */
	private function _setBounds(): void
	{
		global $modSettings;

		$this->_bounds[0] = empty($modSettings['attachment_image_resize_width'])
			? $this->_sizeCurrent[0]
			: min($this->_sizeCurrent[0], $modSettings['attachment_image_resize_width']);
		$this->_bounds[1] = empty($modSettings['attachment_image_resize_height'])
			? $this->_sizeCurrent[1]
			: min($this->_sizeCurrent[1], $modSettings['attachment_image_resize_height']);
	}

	/**
	 * Executes the call to resizeImage
	 *
	 * - Change an images WxH dimension to those defined in the resize section of the ACP
	 * - Optionally will change the format PNG->JPG, JPG->WebP, PNG->WebP
	 *
	 * @param bool $same_format if true, will maintain the current image format
	 * @return bool
	 */
	public function resize($same_format = true): bool
	{
		// Attempt to resize the bounds
		if ($this->image->resizeImage($this->_bounds[0], $this->_bounds[1], false, false))
		{
			$new_format = !$same_format ? $this->image->getDefaultFormat() : IMAGETYPE_JPEG;
			$this->image->saveImage($this->_imageName, $same_format ? $this->_sizeCurrent[2] : $new_format, $new_format === IMAGETYPE_JPEG ? 90 : 85);

			return true;
		}

		return false;
	}

	/**
	 * Updates return values as we manipulate the image
	 *
	 * @param bool $same_format if to update the type values
	 * @return array
	 */
	public function updateSizing($same_format = true): array
	{
		$update = [
			'size' => $this->image->getFilesize(),
			'imagesize' => $this->image->getImageDimensions(),
			'resized' => true,
		];

		if (!$same_format)
		{
			// You're something else now
			$info = pathinfo($this->_fileName);
			$type = $this->getWebP() ? ['image/webp', '.webp'] : ['image/jpeg', '.jpg'];
			$update += [
				'type' => $type[0],
				'mime' => $type[0],
				'name' => $info['filename'] . $type[1],
			];
		}

		return $update;
	}

	/**
	 * Validates a format change would be prudent.
	 *
	 * - Don't reformat if not enabled
	 * - Don't reformat if it is already a JPG (loss of quality)
	 * - Don't reformat if it is a transparent PNG (loss of transparency and artifacts)
	 *
	 * @return bool
	 */
	public function canChangeFormat(): bool
	{
		global $modSettings;

		if (empty($modSettings['attachment_image_resize_reformat']))
		{
			return false;
		}

		// A WebP image will generally be smaller than Jpeg or Png
		if (($this->_sizeCurrent[2] === IMAGETYPE_JPEG || $this->_sizeCurrent[2] === IMAGETYPE_PNG) && $this->getWebP())
		{
			return true;
		}

		// Do not attempt to reformat AVIF images — keep original format
		if ($this->_sizeCurrent[2] === IMAGETYPE_AVIF)
		{
			return false;
		}

		// Already a JPEG and no WebP, out of options, I'm afraid
		if ($this->_sizeCurrent[2] === IMAGETYPE_JPEG)
		{
			return false;
		}

		// A transparent PNG, no WebP, If converted would be destructive, so just no
		// Others like BMP or GIF maybe even TIFF
		return !($this->_sizeCurrent[2] === IMAGETYPE_PNG && $this->image->getTransparency());
	}

	/**
	 * Check if we can use webp format
	 *
	 * @return bool
	 */
	public function getWebP(): bool
	{
		return $this->image->canUseWebp() && $this->image->hasWebpSupport();
	}
}
