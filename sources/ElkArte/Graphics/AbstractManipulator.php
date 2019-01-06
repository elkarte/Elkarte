<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for avatars (uploaded avatars), attachments, or
 * visual verification images.
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

abstract class AbstractManipulator
{
	/** @var array the array as output by getimagesize */
	public $sizes = [];
	/** @var int the exif orientation value */
	public $_orientation = 0;
	/** @var string the name of the file we are manipulating */
	protected $_fileName = '';
	/** @var \Imagick | resource */
	protected $_image;
	/** @var int width of the image, updated after any manipulation */
	protected $_width = 0;
	/** @var int the height of the image, updated after any manipulation */
	protected $_height = 0;

	/**
	 * AbstractManipulator constructor.
	 *
	 * @param $fileName
	 */
	abstract public function __construct($fileName);

	/**
	 * If a given manipulator can be used
	 *
	 * @return mixed
	 */
	abstract public static function canUse();

	/**
	 * Resize an image proportionally to fit within the defined max_width and max_height limits
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $strip Whether to have IM strip EXIF data as GD will
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 */
	abstract public function resizeImage($max_width, $max_height, $strip = false, $force_resize = true);

	abstract public function autoRotateImage();

	abstract public function generateTextImage($text, $width = 100, $height = 100, $format = 'png');

	abstract function createImageFromFile();

	abstract function createImageFromWeb();

	abstract function output($file_name, $preferred_format = IMAGETYPE_JPEG, $quality = 85);

	/**
	 * Simple wrapper for getimagesize
	 *
	 * @param string $type
	 */
	public function getSize($type = 'file')
	{
		try
		{
			if ($type === 'string')
			{
				$this->sizes = getimagesizefromstring($this->_image);
			}
			else
			{
				$this->sizes = getimagesize($this->_fileName);
			}
		}
		catch (\Exception $e)
		{
			$this->sizes = false;
		}

		// Can't get it, what shall we return
		if (empty($this->sizes))
		{
			$this->sizes = array(-1, -1, -1);
		}
	}

	/**
	 * Uses the manipulator functions to read the exif orientation flag
	 *
	 * @return mixed
	 */
	abstract function getOrientation();

	/**
	 * See if we have enough memory to thumbnail an image
	 *
	 * @param bool $fatal if to throw an exception on lack of memory
	 *
	 * @return bool Whether or not the memory is available.
	 * @throws \Exception
	 */
	public function memoryCheck($fatal = false)
	{
		// No Need
		if (empty($this->_width) || empty($this->_height))
		{
			return true;
		}

		// Determine the memory requirements for this image, note: if you want to use an image formula
		// W x H x bits/8 x channels x Overhead factor
		// You will need to account for single bit images as GD expands them to an 8 bit and will greatly
		// overun the calculated value.
		// The 5 below is simply a shortcut of 8bpp, 3 channels, 1.66 overhead
		$needed_memory = $this->_width * $this->_height * 5;

		// If we need more, lets try to get it
		$success = detectServer()->setMemoryLimit($needed_memory, true);

		if ($fatal && !$success)
		{
			throw new \Exception('Not enough memory');
		}

		return $success;
	}
}