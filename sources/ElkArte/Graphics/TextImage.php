<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for avatars (uploaded avatars), attachments, or
 * visual verification images.
 *
 * TrueType fonts supplied by www.LarabieFonts.com
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Graphics;

use ElkArte\Exceptions\Exception;

/**
 * Class TextImage
 *
 * Base class for image function and interaction with the various graphic engines (GD/IMagick)
 *
 * @package ElkArte\Graphics
 */
class TextImage extends Image
{
	/**
	 * Image constructor.
	 *
	 * @param string $fileName
	 * @param bool $force_gd
	 */
	public function __construct($text, $force_gd = false)
	{
		$this->_text = $text;
		$this->_force_gd = $force_gd;

		// Determine and set what image library we will use
		try
		{
			$this->setManipulator();
		}
		catch (\Exception $e)
		{
			// Nothing to do
		}
	}

	/**
	 * Determine and set what image library we will use
	 *
	 * @throws \Exception
	 */
	protected function setManipulator()
	{
		// Later this could become an array of "manipulators" (or not) and remove the hard-coded IM/GD requirements
		if (!$this->_force_gd && Imagick::canUse())
		{
			$this->_manipulator = new Imagick($this->_fileName);
		}
		elseif (Gd2::canUse())
		{
			$this->_manipulator = new Gd2($this->_fileName);
		}
		else
		{
			throw new \Exception('No image manipulators available');
		}
	}

	/**
	 * Do nothing
	 *
	 * @param string $source
	 */
	public function loadImage($source)
	{
	}

	/**
	 * Do nothing
	 *
	 * @param string $source
	 */
	public function setSource($source)
	{
	}

	/**
	 * Do nothing
	 *
	 * @param $source
	 */
	public function isWebAddress($source)
	{
	}

	/**
	 * Simple function to generate an image containing some text.
	 * It uses preferentially Imagick if present, otherwise GD.
	 * Font and size are fixed.
	 *
	 * @param string $text The text the image should contain
	 * @param int $width Width of the final image
	 * @param int $height Height of the image
	 * @param string $format Type of the image (valid types are png, jpeg, gif)
	 *
	 * @return bool|string The image or false if neither Imagick nor GD are found
	 */
	public function generate($width = 100, $height = 75, $format = 'png')
	{
		$valid_formats = array('jpeg', 'png', 'gif');
		if (!in_array($format, $valid_formats))
		{
			$format = 'png';
		}

		return $this->_manipulator->generateTextImage($this->_text, $width, $height, $format);
	}

	/**
	 * Do nothing
	 */
	public function getFilesize()
	{
	}

	/**
	 * Do nothing
	 */
	public function isImage()
	{
	}

	/**
	 * Do nothing
	 *
	 * @param string $source the image file to thumbnail
	 * @param int $max_width allowed width
	 * @param int $max_height allowed height
	 * @param string $dstName name to save
	 * @param string $format image format to save the thumbnail
	 */
	public function createThumbnail($source, $max_width, $max_height, $dstName = '', $format = '')
	{
	}

	/**
	 * Do nothing
	 */
	public function autoRotateImage()
	{
	}

	/**
	 * Do nothing
	 */
	public function getOrientation()
	{
	}

	/**
	 * Do nothing
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize Always resize the image (force scale up)
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 */
	public function resizeImage($max_width, $max_height, $strip = false, $force_resize = true)
	{
	}

	/**
	 * Do nothing
	 *
	 * @param string $file_name name to save the image to
	 * @param int $preferred_format what format to save the image
	 * @param int $quality some formats require we provide a compression quality
	 */
	public function saveImage($file_name = '', $preferred_format = IMAGETYPE_JPEG, $quality = 85)
	{
	}

	/**
	 * Do nothing
	 */
	public function __destruct()
	{
	}

	/**
	 * Do nothing
	 *
	 * @param string $source
	 */
	public function reencodeImage($source)
	{
	}

	/**
	 * Do nothing
	 *
	 * @param string $source
	 */
	public function getSize($source)
	{
	}

	/**
	 * Do nothing
	 *
	 * @param string $source
	 */
	public function checkImageContents($source)
	{
	}
}
