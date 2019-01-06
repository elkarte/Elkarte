<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for the Imagick library
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Graphics;

class Imagick extends AbstractManipulator
{
	public function __construct($image)
	{
		$this->setSource($image);

		try
		{
			$this->memoryCheck();
		}
		catch (\Exception $e)
		{
			return false;
		}

		return true;
	}

	public function setSource($source)
	{
		$this->_fileName = $source;
	}

	/**
	 * Checks whether the Imagick class is present.
	 *
	 * @return bool Whether or not the Imagick extension is available.
	 */
	public static function canUse()
	{

		return class_exists('\Imagick', false);
	}

	public function __destruct()
	{
		if ($this->_image)
		{
			$this->_image->clear();
		}
	}

	public function createImageFromFile()
	{
		$this->getSize();

		if (isset(Image::DEFAULT_FORMATS[$this->sizes[2]]))
		{
			try
			{
				$this->_image = new \Imagick($this->_fileName);
			}
			catch (\Exception $e)
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		$this->_setImage();

		return true;
	}

	/**
	 * Sets the image sizes.
	 */
	protected function _setImage()
	{
		// Update the image size values
		$this->_width = $this->sizes[0] = $this->_image->getImageWidth();
		$this->_height = $this->sizes[1] = $this->_image->getImageHeight();
	}

	public function createImageFromWeb()
	{
		require_once(SUBSDIR . '/Package.subs.php');
		$this->_image = fetch_web_data($this->_fileName);
		$this->getSize('string');

		if (isset(Image::DEFAULT_FORMATS[$this->sizes[2]]))
		{
			try
			{
				$blob = $this->_image;
				$this->_image = new \Imagick();
				$this->_image->readImageBlob($blob);
			}
			catch (\Exception $e)
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		$this->_setImage();

		return true;
	}

	/**
	 * Resize an image proportionally to fit within the defined max_width and max_height limits
	 *
	 * What it does:
	 *
	 * - Will do nothing to the image if the file fits within the size limits
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $strip Whether to have IM strip EXIF data as GD will
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 *
	 * @return bool Whether resize was successful.
	 */
	public function resizeImage($max_width, $max_height, $strip = false, $force_resize = false)
	{
		$success = false;

		// No image, no further
		if (empty($this->_image))
		{
			return $success;
		}

		// Set the input and output image size
		$src_width = $this->_width;
		$src_height = $this->_height;

		// Allow for a re-encode to the same size
		$max_width = $max_width === null ? $src_width : $max_width;
		$max_height = $max_height === null ? $src_height : $max_height;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		$image_ratio = $this->_width / $this->_height;
		$requested_ratio = $max_width / $max_height;

		if ($requested_ratio > $image_ratio)
		{
			$dst_width = max(1, $max_height * $image_ratio);
			$dst_height = $max_height;
		}
		else
		{
			$dst_width = $max_width;
			$dst_height = max(1, $max_width / $image_ratio);
		}

		// Don't bother resizing if it's already smaller...
		if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
		{
			$success = $this->_image->resizeImage($dst_width, $dst_height, \Imagick::FILTER_LANCZOS, 1, true);
			$this->_setImage();
		}

		// Remove EXIF / ICC data?
		if ($strip)
		{
			$this->_image->stripImage();
		}

		return $success;
	}

	/**
	 * Output the image resource to a file in a chosen format
	 *
	 * @param string $file_name where to save the image, if null output to screen
	 * @param int $preferred_format jpg,png,gif, etc
	 * @param int $quality the jpg image quality
	 *
	 * @return bool
	 */
	public function output($file_name, $preferred_format = IMAGETYPE_JPEG, $quality = 85)
	{
		$success = false;

		// No name, but not null, or an unknown type
		if ($file_name === '' || !isset(Image::DEFAULT_FORMATS[$preferred_format]))
		{
			return $success;
		}

		switch ($preferred_format)
		{
			case IMAGETYPE_GIF:
				$success = $this->_image->setImageFormat('gif');
				break;
			case IMAGETYPE_PNG:
				$success = $this->_image->setImageFormat('png');
				break;
			case IMAGETYPE_WBMP:
				$success = $this->_image->setImageFormat('wbmp');
				break;
			case IMAGETYPE_BMP:
				$success = $this->_image->setImageFormat('bmp');
				break;
			default:
				$this->_image->borderImage('white', 0, 0);
				$this->_image->setImageCompression(\Imagick::COMPRESSION_JPEG);
				$this->_image->setImageCompressionQuality($quality);
				$success = $this->_image->setImageFormat('jpeg');
				break;
		}

		try
		{
			if ($success)
			{
				// Screen to file, your choice
				if ($file_name === null)
				{
					echo $this->_image->getImagesBlob();
				}
				else
				{
					$success = $this->_image->writeImage($file_name);
				}
			}
		}
		catch (\Exception $e)
		{
			return false;
		}

		// Update the sizes array to the output file
		if ($success)
		{
			$this->_fileName = $file_name;
			$this->sizes[2] = $preferred_format;
			$this->_setImage();
		}

		return $success;
	}

	/**
	 * Autorotate an image based on its EXIF Orientation tag.
	 *
	 * What it does:
	 *
	 * - Checks exif data for orientation flag and rotates image so its proper
	 * - Updates orientation flag if rotation was required
	 *
	 * @return bool
	 */
	function autoRotateImage()
	{
		try
		{
			switch ($this->_orientation)
			{
				// 0 & 1 Not set or Normal
				case \Imagick::ORIENTATION_UNDEFINED:
				case \Imagick::ORIENTATION_TOPLEFT:
					break;
				// 2 Mirror image, Normal orientation
				case \Imagick::ORIENTATION_TOPRIGHT:
					$this->_image->flopImage();
					break;
				// 3 Normal image, rotated 180
				case \Imagick::ORIENTATION_BOTTOMRIGHT:
					$this->_image->rotateImage('#000', 180);
					break;
				// 4 Mirror image, rotated 180
				case \Imagick::ORIENTATION_BOTTOMLEFT:
					$this->_image->flipImage();
					break;
				// 5 Mirror image, rotated 90 CCW
				case \Imagick::ORIENTATION_LEFTTOP:
					$this->_image->rotateImage('#000', 90);
					$this->_image->flopImage();
					break;
				// 6 Normal image, rotated 90 CCW
				case \Imagick::ORIENTATION_RIGHTTOP:
					$this->_image->rotateImage('#000', 90);
					break;
				// 7 Mirror image, rotated 90 CW
				case \Imagick::ORIENTATION_RIGHTBOTTOM:
					$this->_image->rotateImage('#000', -90);
					$this->_image->flopImage();
					break;
				// 8 Normal image, rotated 90 CW
				case \Imagick::ORIENTATION_LEFTBOTTOM:
					$this->_image->rotateImage('#000', -90);
					break;
			}

			// Now that it's auto-rotated, make sure the EXIF data is correctly updated
			if ($this->_orientation >= 2)
			{
				$this->_image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				$this->_orientation = 1;
				$this->_setImage();
			}

			$success = true;
		}
		catch (\Exception $e)
		{
			$success = false;
		}

		return $success;
	}

	public function getOrientation()
	{
		$this->_orientation = $this->_image->getImageOrientation();

		return (int) $this->_orientation;
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
	public function generateTextImage($text, $width = 100, $height = 75, $format = 'png')
	{
		global $settings;

		try
		{
			$this->_image = new \Imagick();
			$this->_image->newImage($width, $height, new \ImagickPixel('white'));
			$this->_image->setImageFormat($format);

			// 28pt is ~2em given default font stack
			$font_size = 28;

			$draw = new \ImagickDraw();
			$draw->setStrokeColor(new \ImagickPixel("rgba(100%, 100%, 100%, 0)"));
			$draw->setFillColor(new \ImagickPixel('#A9A9A9'));
			$draw->setStrokeWidth(1);
			$draw->setTextAlignment(\Imagick::ALIGN_CENTER);
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
			$this->__destruct();

			return $image;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}
}