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

namespace ElkArte\Graphics\Manipulators;

use \ElkArte\Graphics\Image;

/**
 * Class Imagick
 *
 * This class will load and save an animated gif, however any manipulation will remove said animation.  It currently
 * only provides validation/inspection functions (should you want to keep the animation intact).
 *
 * @package ElkArte\Graphics
 */
class Imagick extends AbstractManipulator
{
	/** @var \Imagick */
	protected $_image;

	/**
	 * Imagick constructor.
	 *
	 * @param string $image
	 */
	public function __construct($image)
	{
		$this->_fileName = $image;

		try
		{
			$this->memoryCheck();
		}
		catch (\Exception $e)
		{
			// Just pass through
		}
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

	/**
	 * Loads an image file into the image engine for processing
	 *
	 * @return bool
	 */
	public function createImageFromFile()
	{
		$this->setImageDimensions();

		if ($this->imageDimensions[2] === IMAGETYPE_WEBP && !$this->hasWebpSupport())
		{
			return false;
		}

		if (isset(Image::DEFAULT_FORMATS[$this->imageDimensions[2]]))
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
		$this->_image->setFirstIterator();

		try
		{
			$this->_width = $this->imageDimensions[0] = $this->_image->getImageWidth();
			$this->_height = $this->imageDimensions[1] = $this->_image->getImageHeight();
		}
		catch (\ImagickException $e)
		{
			$this->_width = $this->imageDimensions[0] = $this->imageDimensions[0] ?? 0;
			$this->_height = $this->imageDimensions[1] = $this->imageDimensions[1] ?? 0;
		}
	}

	/**
	 * Loads an image from a web address into the image engine for processing
	 *
	 * @return bool
	 */
	public function createImageFromWeb()
	{
		require_once(SUBSDIR . '/Package.subs.php');
		$image_data = fetch_web_data($this->_fileName);
		if ($image_data === false)
		{
			return false;
		}

		$this->setImageDimensions('string', $image_data);
		if (isset(Image::DEFAULT_FORMATS[$this->imageDimensions[2]]))
		{
			try
			{
				$this->_image = new \Imagick();
				$this->_image->readImageBlob($image_data);
			}
			catch (\ImagickException $e)
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
	 * @param int|null $max_width The maximum allowed width
	 * @param int|null $max_height The maximum allowed height
	 * @param bool $strip Whether to have IM strip EXIF data as GD will
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 *
	 * @return bool Whether resize was successful.
	 */
	public function resizeImage($max_width = null, $max_height = null, $strip = false, $force_resize = false)
	{
		$success = true;

		// No image, no further
		if (empty($this->_image))
		{
			return false;
		}

		// Set the input and output image size
		$src_width = $this->_width;
		$src_height = $this->_height;

		// Allow for a re-encode to the same size
		$max_width = $max_width ?? $src_width;
		$max_height = $max_height ?? $src_height;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		list($dst_width, $dst_height) = $this->imageRatio($max_width, $max_height);

		// Don't bother resizing if it's already smaller...
		if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
		{
			$success = $this->_image->resizeImage($dst_width, $dst_height, \Imagick::FILTER_LANCZOS, .9891, true);
			$this->_setImage();
		}

		// Remove EXIF / ICC data?
		if ($strip)
		{
			try
			{
				$success = $this->_image->stripImage() && $success;
			}
			catch (\ImagickException $e)
			{
				return $success;
			}

		}

		return $success;
	}

	/**
	 * Output the image resource to a file in a chosen format
	 *
	 * @param string $file_name where to save the image, if '' output to screen
	 * @param int $preferred_format jpg,png,gif, etc
	 * @param int $quality the jpg image quality
	 *
	 * @return bool
	 */
	public function output($file_name = '', $preferred_format = IMAGETYPE_JPEG, $quality = 85)
	{
		// An unknown type
		if (!isset(Image::DEFAULT_FORMATS[$preferred_format]))
		{
			return false;
		}

		switch ($preferred_format)
		{
			case IMAGETYPE_GIF:
				$success = $this->_image->setImageFormat('gif');
				break;
			case IMAGETYPE_PNG:
				// Save a few bytes the only way, realistically, we can
				$this->_image->setOption('png:compression-level', '9');
				$this->_image->setOption('png:exclude-chunk', 'all');
				$success = $this->_image->setImageFormat('png');
				break;
			case IMAGETYPE_WBMP:
				$success = $this->_image->setImageFormat('wbmp');
				break;
			case IMAGETYPE_BMP:
				$success = $this->_image->setImageFormat('bmp');
				break;
			case IMAGETYPE_WEBP:
				$this->_image->setImageCompressionQuality($quality);
				$success = $this->_image->setImageFormat('webp');
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
				// Screen or file, your choice
				if (empty($file_name))
				{
					echo $this->_image->getImagesBlob();
				}
				else
				{
					if ($preferred_format === IMAGETYPE_GIF && $this->_image->getNumberImages() !== 0)
					{
						// Write all animated gif frames
						$success = $this->_image->writeImages($file_name, true);
					}
					else
					{
						$success = $this->_image->writeImage($file_name);
					}
				}
			}
		}
		catch (\Exception $e)
		{
			return false;
		}

		// Update the sizes array to the output file
		if ($success && $file_name !== '')
		{
			$this->_fileName = $file_name;
			$this->imageDimensions[2] = $preferred_format;
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
	public function autoRotate()
	{
		try
		{
			switch ($this->orientation)
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
			if ($this->orientation >= 2)
			{
				$this->_image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				$this->orientation = 1;
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

	/**
	 * Returns the ORIENTATION constant for an image
	 *
	 * @return int
	 */
	public function getOrientation()
	{
		try
		{
			$this->orientation = $this->_image->getImageOrientation();
		}
		catch (\ImagickException $e)
		{
			$this->orientation = 0;
		}

		return $this->orientation;
	}

	/**
	 * Returns if the image (png) has any alpha pixels
	 *
	 * @return bool
	 */
	public function getTransparency()
	{
		// No image, return false
		if (empty($this->_image))
		{
			return false;
		}

		try
		{
			$transparency = false;
			$pixel_iterator = $this->_image->getPixelIterator();

			// Look at each one, or until we find the first alpha pixel
			foreach ($pixel_iterator as $pixels)
			{
				foreach ($pixels as $pixel)
				{
					$color = $pixel->getColor();
					if ($color['a'] < 1)
					{
						$transparency = true;
						break 2;
					}
				}
			}
		}
		catch (\ImagickException $e)
		{
			// We don't know what it is, so don't mess with it
			return false;
		}

		return $transparency;
	}

	/**
	 * Function to generate an image containing some text.
	 * Attempts to adjust font size to fit within bounds
	 *
	 * @param string $text The text the image should contain
	 * @param int $width Width of the final image
	 * @param int $height Height of the image
	 * @param string $format Type of the image (valid types are png, jpeg, gif)
	 *
	 * @return bool|string The image or false on error
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

			// Make sure the text will fit the allowed space
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

	/**
	 * Check if this installation supports webP
	 *
	 * @return bool
	 */
	public function hasWebpSupport()
	{
		$check = Imagick::queryformats();

		return in_array('WEBP', $check);
	}

	/**
	 * CLean up
	 */
	public function __destruct()
	{
		if (gettype($this->_image) === 'object' && get_class($this->_image) === 'Imagick')
		{
			$this->_image->clear();
		}
	}
}
