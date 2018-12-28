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
		'1' => 'gif',
		'2' => 'jpeg',
		'3' => 'png',
		'6' => 'bmp',
		'15' => 'wbmp'
	];
	protected $manipulator = null;
	protected $fileName = '';
	protected $forse_gd = false;
	protected $do_memory_check = true;

	// $do_memory_check = !empty($modSettings['attachment_thumb_memory']
	public function __construct($fileName, $forse_gd = false, $do_memory_check = true)
	{
		$this->fileName = $fileName;
		$this->forse_gd = $forse_gd;
		$this->do_memory_check = $do_memory_check;
		$this->setManipulator($fileName, $forse_gd, $do_memory_check);
	}

	protected function setManipulator($fileName, $forse_gd, $do_memory_check)
	{
		// Later this could become an array of "manipulators" (or not) and remove the hard-coded IM/GD requirements
		if ($forse_gd === false && Imagick::canUse())
		{
			$this->manipulator = new Imagick($fileName, $do_memory_check);
		}
		elseif (Gd2::canUse())
		{
			$this->manipulator = new Gd2($fileName, $do_memory_check);
		}
		else
		{
			throw new \Exception('No image manipulators available');
		}
	}

	public function getFileName()
	{
		return $this->fileName;
	}

	public function getSize()
	{
		return $this->manipulator->getSize();
	}

	public function isWebAddress()
	{
		return substr($source, 0, 7) === 'http://' || substr($this->fileName, 0, 8) === 'https://';
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
		if ($this->manipulator === null)
		{
			return false;
		}

		$sourceImage = new Image($source);

		if (!file_exists($this->fileName) && $sourceImage->isWebAddress() == false)
		{
			return false;
		}

		$this->manipulator->copyFrom($sourceImage);

		try
		{
			return $this->manipulator->resizeImageFile($max_width, $max_height, $preferred_format, $strip, $force_resize);
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
		$this->manipulator->autoRotateImage();
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

		return $this->manipulator->generateTextImage($text, $width, $height, $format);
	}

	public function moveTo($destination)
	{
		$this->fileName = $destination;
		$this->setManipulator($destination, $this->forse_gd, $this->do_memory_check);
		return @rename($tempName, $destName);
	}

	public function filesize()
	{
		return @filesize($this->fileName);
	}
}