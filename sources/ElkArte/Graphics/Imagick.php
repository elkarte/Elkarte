<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for the Imagick library
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

namespace ElkArte\Graphics;

class Imagick extends AbstractManipulator
{
	public function __construct($fileNname)
	{
		$this->_fileName = $fileName;
		$this->_image = new \Imagick($fileName);
	}

	/**
	 * Checks whether the Imagick class is present.
	 *
	 * @return bool Whether or not the Imagick extension is available.
	 */
	public static function canUse()
	{
		return class_exists('\\Imagick', false);
	}

	/**
	 * Resize an image from a remote location or a local file.
	 *
	 * What it does:
	 *
	 * - Puts the resized image at the destination location.
	 * - The file would have the format preferred_format if possible,
	 * otherwise the default format is jpeg.
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param int $preferred_format Used by Imagick/resizeImage
	 * @param bool $force_resize Always resize the image (force scale up)
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 *
	 * @return boolean Whether the thumbnail creation was successful.
	 */
	public function resizeImageFile($max_width, $max_height, $preferred_format = 0, $strip = false, $force_resize = true)
	{
		// A known and supported format?
		if (!isset(Image::DEFAULT_FORMATS[$sizes[2]]))
		{
			throw new \Exception('Format not supported');
		}

		return $this->resizeImage($max_width, $max_height, $force_resize, $preferred_format, $strip);
	}

	/**
	 * Resize an image proportionally to fit within the defined max_width and max_height limits
	 *
	 * What it does:
	 *
	 * - Will do nothing to the image if the file fits within the size limits
	 * - If Image Magick is present it will use those function over any GD solutions
	 * - If GD2 is present, it'll use it to achieve better quality (imagecopyresampled)
	 * - Saves the new image to destination_filename, in the preferred_format
	 * if possible, default is jpeg.
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 * @param int $preferred_format - The preferred format
	 *   - 0 to use jpeg
	 *   - 1 for gif
	 *   - 2 to force jpeg
	 *   - 3 for png
	 *   - 6 for bmp
	 *   - 15 for wbmp
	 * @param bool $strip Whether to have IM strip EXIF data as GD will
	 *
	 * @return bool Whether resize was successful.
	 */
	function resizeImage($max_width, $max_height, $force_resize = false, $preferred_format = 0, $strip = false)
	{
		$preferred_format = empty($preferred_format) || !isset(Image::DEFAULT_FORMATS[$preferred_format]) ? 2 : $preferred_format;

		// Set the input and output image size
		$src_width = $this->_image->getImageWidth();
		$src_height = $this->_image->getImageHeight();

		// It should never happen, but let's keep these two as a failsafe
		$max_width = $max_width === null ? $src_width : $max_width;
		$max_height = $max_height === null ? $src_height : $max_height;

		// The behavior of bestfit changed in Imagick 3.0.0 and it will now scale up, we prevent that
		$dest_width = empty($max_width) ? $src_width : ($force_resize ? $max_width : min($max_width, $src_width));
		$dest_height = empty($max_height) ? $src_height : ($force_resize ? $max_height : min($max_height, $src_height));

		// Set jpeg image quality to 80
		if (Image::DEFAULT_FORMATS[$preferred_format] === 'jpeg')
		{
			$this->_image->borderImage('white', 0, 0);
			$this->_image->setImageCompression(Imagick::COMPRESSION_JPEG);
			$this->_image->setImageCompressionQuality(80);
		}

		// Create a new image in our preferred format and resize it if needed
		$this->_image->setImageFormat(Image::DEFAULT_FORMATS[$preferred_format]);
		$this->_image->resizeImage($dest_width, $dest_height, Imagick::FILTER_LANCZOS, 1, true);

		// Remove EXIF / ICC data?
		if ($strip)
		{
			$this->_image->stripImage();
		}

		// Save the new image in the destination location
		$success = $this->_image->writeImage($this->_fileName);

		// Free resources associated with the Imagick object
		$this->_image->clear();

		return !empty($success);
	}

	/**
	 * Autorotate an image based on its EXIF Orientation tag.
	 *
	 * What it does:
	 *
	 * - ImageMagick only
	 * - Checks exif data for orientation flag and rotates image so its proper
	 * - Updates orientation flag if rotation was required
	 * - Writes the update image back to $image_name
	 *
	 * @return bool
	 */
	function autoRotateImage()
	{
		try
		{
			// This method should exist if Imagick has been compiled against ImageMagick version
			// 6.3.0 or higher which is forever ago, but we check anyway ;)
			if (!method_exists($this->_image, 'getImageOrientation'))
			{
				return false;
			}

			$orientation = $this->_image->getImageOrientation();
			switch ($orientation)
			{
				// 0 & 1 Not set or Normal
				case Imagick::ORIENTATION_UNDEFINED:
				case Imagick::ORIENTATION_TOPLEFT:
					break;
				// 2 Mirror image, Normal orientation
				case Imagick::ORIENTATION_TOPRIGHT:
					$this->_image->flopImage();
					break;
				// 3 Normal image, rotated 180
				case Imagick::ORIENTATION_BOTTOMRIGHT:
					$this->_image->rotateImage('#000', 180);
					break;
				// 4 Mirror image, rotated 180
				case Imagick::ORIENTATION_BOTTOMLEFT:
					$this->_image->flipImage();
					break;
				// 5 Mirror image, rotated 90 CCW
				case Imagick::ORIENTATION_LEFTTOP:
					$this->_image->rotateImage('#000', 90);
					$this->_image->flopImage();
					break;
				// 6 Normal image, rotated 90 CCW
				case Imagick::ORIENTATION_RIGHTTOP:
					$this->_image->rotateImage('#000', 90);
					break;
				// 7 Mirror image, rotated 90 CW
				case Imagick::ORIENTATION_RIGHTBOTTOM:
					$this->_image->rotateImage('#000', -90);
					$this->_image->flopImage();
					break;
				// 8 Normal image, rotated 90 CW
				case Imagick::ORIENTATION_LEFTBOTTOM:
					$this->_image->rotateImage('#000', -90);
					break;
			}

			// Now that it's auto-rotated, make sure the EXIF data is correctly updated
			if ($orientation >= 2)
			{
				$this->_image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
			}

			// Save the new image in the destination location
			$success = $this->_image->writeImage($this->_fileName);

			// Free resources associated with the Imagick object
			$this->_image->clear();
		}
		catch (\Exception $e)
		{
			$success = false;
		}

		return $success;
	}

	/**
	 * Function to generate an image containing some text.
	 * It uses Imagick, Font and size are fixed to fit within width
	 *
	 * @param string $text The text the image should contain
	 * @param int $width Width of the final image
	 * @param int $height Height of the image
	 * @param string $format Type of the image (valid types are png, jpeg, gif)
	 *
	 * @return boolean|resource The image or false on error
	 */
	function generateTextImage($text, $width = 100, $height = 100, $format = 'png')
	{
		global $settings;

		try
		{
			$this->_image->newImage($width, $height, new ImagickPixel('white'));
			$this->_image->setImageFormat($format);

			// 28pt is ~2em given default font stack
			$font_size = 28;

			$draw = new ImagickDraw();
			$draw->setStrokeColor(new ImagickPixel('#000000'));
			$draw->setFillColor(new ImagickPixel('#000000'));
			$draw->setStrokeWidth(0);
			$draw->setTextAlignment(Imagick::ALIGN_CENTER);
			$draw->setFont($settings['default_theme_dir'] . '/fonts/VDS_New.ttf');

			// Make sure the text will fit the the allowed space
			do
			{
				$draw->setFontSize($font_size);
				$metric = $this->_image->queryFontMetrics($draw, $text);
				$text_width = (int) $metric['textWidth'];
			} while ($text_width > $width && $font_size-- > 1);

			// Place text in center of block
			$this->_image->annotateImage($draw, $width / 2, $height / 2 + $font_size / 4, 0, $text);
			$image = $this->_image->getImageBlob();

			return $image;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Simple wrapper for getimagesize
	 * For an ImageMacig "pure" alternative getImageGeometry and
	 * getImageMimeType (plus some conversions) could be used
	 *
	 * @param string|boolean $error return array or false on error
	 *
	 * @return array|boolean
	 */
	protected function getSize($error = 'array')
	{
		$sizes = @getimagesize($file);

		// Can't get it, what shall we return
		if (empty($sizes))
		{
			if ($error === 'array')
			{
				$sizes = array(-1, -1, -1);
			}
			else
			{
				$sizes = false;
			}
		}

		return $sizes;
	}
}