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
	protected $fileName = '';
	protected $fileHandle = null;
	protected $sizes = [];
	protected $image = null;

	abstract public function __construct($fileName);

	abstract public static function canUse();

	abstract public function resizeImageFile($max_width, $max_height, $preferred_format = 0, $strip = false, $force_resize = true);

	abstract public function autoRotateImage();

	abstract public function generateTextImage($text, $width = 100, $height = 100, $format = 'png');

	public function copyFrom(Image $source)
	{
		// Get the image file, we have to work with something after all
		$fp_destination = fopen($this->fileName, 'wb');
		if ($fp_destination && $source->isWebAddress())
		{
			require_once(SUBSDIR . '/Package.subs.php');
			$fileContents = fetch_web_data($source->getFileName());

			fwrite($fp_destination, $fileContents);
			fclose($fp_destination);
		}
		elseif ($fp_destination)
		{
			$fp_source = fopen($source->getFileName(), 'rb');
			if ($fp_source !== false)
			{
				while (!feof($fp_source))
				{
					fwrite($fp_destination, fread($fp_source, 8192));
				}
				fclose($fp_source);
			}

			fclose($fp_destination);
		}

		$this->sizes = $this->getSize();
		return $this->sizes;
	}

	abstract public function getSize();

	/**
	 * See if we have enough memory to thumbnail an image
	 *
	 * @param int[] $sizes image size
	 *
	 * @return bool Whether or not the memory is available.
	 */
	public function memoryCheck()
	{
		// Just to be sure
		if (!is_array($this->sizes) || $this->sizes[0] === -1)
		{
			return true;
		}

		// Determine the memory requirements for this image, note: if you want to use an image formula
		// W x H x bits/8 x channels x Overhead factor
		// You will need to account for single bit images as GD expands them to an 8 bit and will greatly
		// overun the calculated value.
		// The 5 below is simply a shortcut of 8bpp, 3 channels, 1.66 overhead
		$needed_memory = ($sizes[0] * $sizes[1] * 5);

		// If we need more, lets try to get it
		return detectServer()->setMemoryLimit($needed_memory, true);
	}

}