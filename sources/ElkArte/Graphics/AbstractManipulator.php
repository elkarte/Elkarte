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
	protected $do_memory_check = true;
	protected $image = null;

	abstract public function __construct($fileName, $do_memory_check = true);

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
}