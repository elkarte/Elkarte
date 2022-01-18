<?php

/**
 * This file deals with creating an image file based on supplied text.  For example
 * the attachment not found image
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

/**
 * Class TextImage
 *
 * Base class for text to image functions
 *
 * @package ElkArte\Graphics
 */
class TextImage extends Image
{
	/** @var string text to be shown in the image */
	protected $_text = '';

	/**
	 * Image constructor.
	 *
	 * @param string $text
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
	 * Do nothing
	 */
	public function loadImage()
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
	 * @return bool
	 */
	public function isWebAddress()
	{
		return false;
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
	 *
	 * @return int
	 */
	public function getFilesize()
	{
		return 0;
	}

	/**
	 * Do nothing
	 *
	 * @return bool
	 */
	public function isImage()
	{
		return true;
	}

	/**
	 * Do nothing
	 *
	 * @param int $max_width allowed width
	 * @param int $max_height allowed height
	 * @param string $dstName name to save
	 * @param string $format image format to save the thumbnail
	 */
	public function createThumbnail($max_width, $max_height, $dstName = '', $format = '')
	{
	}

	/**
	 * Do nothing
	 */
	public function autoRotate()
	{
	}

	/**
	 * Do nothing
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize Always resize the image (force scale up)
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 *
	 * @return bool Always returns true.
	 */
	public function resizeImage($max_width, $max_height, $strip = false, $force_resize = true)
	{
		return true;
	}

	/**
	 * Do nothing
	 *
	 * @param string $file_name name to save the image to
	 * @param int $preferred_format what format to save the image
	 * @param int $quality some formats require we provide a compression quality
	 *
	 * @return bool Always returns true.
	 */
	public function saveImage($file_name = '', $preferred_format = IMAGETYPE_JPEG, $quality = 85)
	{
		return true;
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
	 * @return bool Always returns true.
	 */
	public function reEncodeImage()
	{
		return true;
	}

	/**
	 * Do nothing
	 *
	 * @return array
	 */
	public function getSize()
	{
		return $this->_manipulator->imageDimensions;
	}

	/**
	 * Do nothing
	 *
	 * @return bool Always returns true.
	 */
	public function checkImageContents()
	{
		return true;
	}
}
