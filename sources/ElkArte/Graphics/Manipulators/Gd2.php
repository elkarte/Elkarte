<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for the GD library
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Graphics\Manipulators;

use ElkArte\Graphics\Image;
use Exception;
use GdImage;

/**
 * Class Gd2
 *
 * @package ElkArte\Graphics
 */
class Gd2 extends AbstractManipulator
{
	/** @var GdImage */
	protected $_image;

	/**
	 * Gd2 constructor.
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
		catch (Exception)
		{
			// Just pass through since the check is not fatal
		}
	}

	/**
	 * Used to determine if the GD2 library is present.
	 *
	 * @return bool Whether or not GD is available.
	 */
	public static function canUse()
	{
		// Check to see if GD is installed and what version.
		return get_extension_funcs('gd') !== false;
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
				$imagecreatefrom = 'imagecreatefrom' . Image::DEFAULT_FORMATS[$this->imageDimensions[2]];
				/** @var $image GdImage */
				$image = $imagecreatefrom($this->_fileName);
			}
			catch (Exception)
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		$this->_setImage($image);

		return true;
	}

	/**
	 * Sets the internal GD image resource.
	 *
	 * @param GdImage $image
	 */
	protected function _setImage($image)
	{
		$this->_image = $image;

		// Get the image size via GD functions
		$this->_width = $this->imageDimensions[0] = imagesx($image);
		$this->_height = $this->imageDimensions[1] = imagesy($image);
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
				$image = imagecreatefromstring($image_data);
				unset($image_data);
			}
			catch (Exception)
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		$this->_setImage($image);

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
	 * @param bool $strip if to remove the images Exif data (GD will always)
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 * @param bool $thumbnail True if creating a simple thumbnail
	 *
	 * @return bool Whether resize was successful.
	 */
	public function resizeImage($max_width = null, $max_height = null, $strip = true, $force_resize = false, $thumbnail = false)
	{
		$success = false;

		// No image, no further
		if (empty($this->_image))
		{
			return false;
		}

		$src_width = $this->_width;
		$src_height = $this->_height;

		// Allow for a re-encode to the same size
		$max_width = $max_width ?? $src_width;
		$max_height = $max_height ?? $src_height;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if (!empty($max_width) && !empty($max_height))
		{
			[$dst_width, $dst_height] = $this->imageRatio($max_width, $max_height);

			// Don't bother resizing if it's already smaller...
			if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
			{
				$dst_img = imagecreatetruecolor($dst_width, $dst_height);
				$this->_createCanvas($dst_img);

				// Resize it!
				$success = imagecopyresampled($dst_img, $this->_image, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
			}
			else
			{
				$dst_img = $this->_image;
				$success = true;
			}
		}
		else
		{
			$dst_img = $this->_image;
		}

		// Update all settings to the converted image
		$this->_setImage($dst_img);

		return $success;
	}

	/**
	 * Create a transparent true image canvas to place our image on
	 *
	 * @param resource $dst_img
	 */
	protected function _createCanvas($dst_img)
	{
		// Make a true color image, because it just looks better for resizing.
		imagesavealpha($dst_img, true);
		$color = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
		imagefill($dst_img, 0, 0, $color);
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
		$success = false;

		// No bogus formats
		if (!isset(Image::DEFAULT_FORMATS[$preferred_format]))
		{
			return false;
		}

		// Save the image as ..
		$output_name = empty($file_name) ? null : $file_name;
		switch ($preferred_format)
		{
			case IMAGETYPE_PNG:
				if (function_exists('imagepng'))
				{
					imagepalettetotruecolor($this->_image);
					imagealphablending($this->_image, true);
					imagesavealpha($this->_image, true);
					$success = imagepng($this->_image, $output_name, 9, PNG_ALL_FILTERS);
				}

				break;
			case IMAGETYPE_GIF:
				if (function_exists('imagegif'))
				{
					$success = imagegif($this->_image, $output_name);
				}

				break;
			case IMAGETYPE_WBMP:
				if (function_exists('imagewbmp'))
				{
					$success = imagewbmp($this->_image, $output_name);
				}

				break;
			case IMAGETYPE_BMP:
				if (function_exists('imagebmp'))
				{
					$success = imagebmp($this->_image, $output_name);
				}

				break;
			case IMAGETYPE_WEBP:
				if (function_exists('imagewebp'))
				{
					$success = imagewebp($this->_image, $output_name, $quality);
				}

				break;
			default:
				if (function_exists('imagejpeg'))
				{
					$success = imagejpeg($this->_image, $output_name, $quality);
				}
		}

		// Update the sizes array to the output file
		if ($success && !empty($file_name))
		{
			$this->_fileName = $file_name;
			$this->imageDimensions[2] = $preferred_format;
			$this->_setImage($this->_image);
		}

		return $success;
	}

	/**
	 * Autorotate an image based on its EXIF Orientation tag.
	 *
	 * What it does:
	 *
	 * - Checks exif data for orientation flag and rotates image so its proper
	 * - Does not update the orientation flag as GD also removes EXIF data
	 * - Only works with jpeg images, could add TIFF as well
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function autoRotate()
	{
		// Time to spin and mirror as needed
		switch ($this->orientation)
		{
			// 0 & 1 Not set or Normal
			case 0:
			case 1:
				break;
			// 2 Mirror image, Normal orientation
			case 2:
				$this->flopImage();
				break;
			// 3 Normal image, rotated 180
			case 3:
				$this->rotateImage(180);
				break;
			// 4 Mirror image, rotated 180
			case 4:
				$this->flipImage();
				break;
			// 5 Mirror image, rotated 90 CCW
			case 5:
				$this->flopImage();
				$this->rotateImage(90);
				break;
			// 6 Normal image, rotated 90 CCW
			case 6:
				$this->rotateImage(-90);
				break;
			// 7 Mirror image, rotated 90 CW
			case 7:
				$this->flopImage();
				$this->rotateImage(-90);
				break;
			// 8 Normal image, rotated 90 CW
			case 8:
				$this->rotateImage(90);
				break;
		}

		$this->orientation = 1;
		$this->_setImage($this->_image);

		return true;
	}

	/**
	 * Flop an image using GD functions by copying top to bottom / flop
	 */
	protected function flopImage()
	{
		$this->flipImage('horizontal');
	}

	/**
	 * Flip an image using GD function by copying top to bottom / flip vertical
	 *
	 * @param string $axis vertical for flip about vertical otherwise horizontal flip
	 */
	protected function flipImage($axis = 'vertical')
	{
		imageflip($this->_image, $axis === 'vertical' ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
	}

	/**
	 * Rotate an image by X degrees, GD function
	 *
	 * @param int $degrees
	 */
	protected function rotateImage($degrees)
	{
		// Kind of need this to do anything
		if (function_exists('imagerotate'))
		{
			// Use positive degrees so GD does not get confused
			$degrees -= floor($degrees / 360) * 360;

			// Rotate away
			$background = imagecolorallocatealpha($this->_image, 255, 255, 255, 127);
			$this->_image = imagerotate($this->_image, $degrees, $background);
		}
	}

	/**
	 * Returns the ORIENTATION constant for an image
	 *
	 * @return int
	 */
	public function getOrientation()
	{
		// Read the EXIF data
		$exif = function_exists('exif_read_data') ? @exif_read_data($this->_fileName) : array();

		$this->orientation = $exif['Orientation'] ?? 0;

		// We're only interested in the exif orientation
		return (int) $this->orientation;
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

		$width = $this->_width;
		$height = $this->_height;
		$image = $this->_image;
		if ($width > 1024 || $height > 1024)
		{
			// Single pass scale down, not looking for quality here
			[$width, $height] = $this->imageScaleFactor(800);
			$image = imagescale($this->_image, $width, $height, IMG_NEAREST_NEIGHBOUR);
		}

		// Go through the image pixel by pixel until we find a transparent pixel
		$transparency = false;
		for ($i = 0; $i < $width; $i++)
		{
			for ($j = 0; $j < $height; $j++)
			{
				if ((imagecolorat($image, $i, $j) & 0x7F000000) !== 0)
				{
					$transparency = true;
					break 2;
				}
			}
		}

		unset($image);

		return $transparency;
	}

	/**
	 * Simple function to generate an image containing some text.
	 * Attempts to scale the font such that it fits inside the bounds
	 *
	 * @param string $text The text the image should contain
	 * @param int $width Width of the final image
	 * @param int $height Height of the image
	 * @param string $format Type of the image (valid types are png, jpeg, gif)
	 *
	 * @return string|bool The image
	 */
	public function generateTextImage($text, $width = 100, $height = 75, $format = 'png')
	{
		global $settings;

		$create_function = 'image' . $format;

		// Create a white filled box
		try
		{
			$image = imagecreatetruecolor($width, $height);
			$background = imagecolorallocate($image, 255, 255, 255);
			imagefill($image, 0, 0, $background);
		}
		catch (Exception)
		{
			return false;
		}

		$text_color = imagecolorallocate($image, 109, 109, 109);
		$font = $settings['default_theme_dir'] . '/fonts/OpenSans.ttf';
		$true_type = function_exists('imagettftext');

		if (!$true_type)
		{
			$text = str_replace("\n", ' ', $text);
			$text_length = strlen($text);
		}

		// The loop is to try to fit the text into the image.
		$font_size = $true_type ? 28 : 5;
		do
		{
			if ($true_type)
			{
				$metric = imagettfbbox($font_size, 0, $font, $text);
				$text_width = abs($metric[4] - $metric[6]);
				$text_height = abs($metric[5] - $metric[3]);
				$text_offset = $metric[7];
			}
			else
			{
				$text_width = imagefontwidth($font_size) * $text_length;
				$text_height = imagefontheight($font_size);
			}
		} while ($text_width > $width && $font_size-- > 1);

		// Center the image on the canvas
		$w_offset = floor(($width - $text_width) / 2);
		$h_offset = floor(($height - $text_height) / 2);
		$h_offset = $true_type ? $h_offset - $text_offset : $h_offset;

		if ($true_type)
		{
			imagettftext($image, $font_size, 0, $w_offset, $h_offset, $text_color, $font, $text);
		}
		else
		{
			imagestring($image, $font_size, $w_offset, $h_offset, $text, $text_color);
		}

		// Capture the image string
		ob_start();
		$result = $create_function($image);
		$image = ob_get_clean();
		$this->__destruct();

		return $result ? $image : false;
	}

	/**
	 * If this installation of GD supports webP
	 *
	 * @return bool
	 */
	public function hasWebpSupport()
	{
		$check = gd_info();

		return !empty($check['WebP Support']);
	}

	/**
	 * Cleanup when done
	 */
	public function __destruct()
	{
		if ($this->_image instanceof GdImage)
		{
			imagedestroy($this->_image);
			unset($this->_image);
		}
	}
}
