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
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Graphics;


class Image
{
	const DEFAULT_FORMATS = [
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_BMP => 'bmp',
		IMAGETYPE_WBMP => 'wbmp'
	];
	/** @var \ElkArte\Graphics\Imagick|\ElkArte\Graphics\Gd2  */
	protected $_manipulator;
	protected $_fileName = '';
	protected $_force_gd = false;

	public function __construct($fileName = '', $force_gd = false)
	{
		$this->_fileName = $fileName;
		$this->_force_gd = $force_gd;

		try
		{
			$this->setManipulator($fileName, $force_gd);
		}
		catch (\Exception $e)
		{
		}
	}

	protected function setManipulator($fileName, $force_gd)
	{
		// Later this could become an array of "manipulators" (or not) and remove the hard-coded IM/GD requirements
		if ($force_gd === false && Imagick::canUse())
		{
			$this->_manipulator = new Imagick($fileName);
		}
		elseif (Gd2::canUse())
		{
			$this->_manipulator = new Gd2($fileName);
		}
		else
		{
			throw new \Exception('No image manipulators available');
		}
	}

	public function loadImage($source)
	{
		if ($this->isWebAddress($source))
		{
			$this->_fileName = $source;
			$this->_manipulator->createImageFromWeb();
		}
		else
		{
			$this->_fileName = $source;
			$this->_manipulator->createImageFromFile();
		}
	}

	public function getFileName()
	{
		return $this->_fileName;
	}

	public function getSize()
	{
		return $this->_manipulator->getSize();
	}

	public function isWebAddress($source)
	{
		return substr($source, 0, 7) === 'http://' || substr($this->_fileName, 0, 8) === 'https://';
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
	 * @param string $source The name of the source image
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param int $preferred_format Used by Imagick/resizeImage
	 * @param bool $force_resize Always resize the image (force scale up)
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 *
	 * @return boolean Whether the thumbnail creation was successful.
	 */
	public function resizeImageFile($source, $max_width, $max_height, $preferred_format = 0, $strip = false, $force_resize = true)
	{
		// Nothing to do without GD or IM
		if ($this->_manipulator === null)
		{
			return false;
		}

		if (!file_exists($this->_fileName) && $sourceImage->isWebAddress() == false)
		{
			return false;
		}

		$this->_manipulator->copyFrom($sourceImage);

		try
		{
			return $this->_manipulator->resizeImageFile($max_width, $max_height, $preferred_format, $strip, $force_resize);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Calls GD or ImageMagick functions to correct an images orientation
	 * based on the EXIF orientation flag
	 *
	 * @param string $image_name
	 */
	public function autoRotateImage()
	{
		$this->_manipulator->autoRotateImage();
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
	 * @return boolean|resource The image or false if neither Imagick nor GD are found
	 */
	public function generateTextImage($text, $width = 100, $height = 100, $format = 'png')
	{
		$valid_formats = array('jpeg', 'png', 'gif');
		if (!in_array($format, $valid_formats))
		{
			$format = 'png';
		}

		return $this->_manipulator->generateTextImage($text, $width, $height, $format);
	}

	public function moveTo($destination)
	{
		$this->_fileName = $destination;
		$this->setManipulator($destination, $this->_force_gd);

		return @rename($tempName, $destName);
	}

	public function filesize()
	{
		return @filesize($this->_fileName);
	}
}