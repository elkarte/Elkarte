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
	protected $_fileName = '';
	protected $_fileHandle = null;
	public $sizes = [];
	/** @var \Imagick | resource  */
	protected $_image ;
	protected $_width = 0;
	protected $_height = 0;
	public $_orientation = 0;

	abstract public function __construct($fileName);

	abstract public static function canUse();

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
	 * @param string|boolean $error return array or false on error
	 */
	public function getSize($type = 'file', $error = 'array')
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
			if ($error === 'array')
			{
				$this->sizes = array(-1, -1, -1);
			}
			else
			{
				$this->sizes = false;
			}
		}
	}

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