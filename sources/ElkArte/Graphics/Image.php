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

use ElkArte\Graphics\Manipulators\Imagick;
use ElkArte\Graphics\Manipulators\Gd2;
use ElkArte\Exceptions\Exception;

/**
 * Class Image
 *
 * Base class for image function and interaction with the various graphic engines (GD/IMagick)
 *
 * @package ElkArte\Graphics
 */
class Image
{
	/** @var array The image formats we will work with */
	public const DEFAULT_FORMATS = [
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_BMP => 'bmp',
		IMAGETYPE_WBMP => 'wbmp'
	];

	/** @var \ElkArte\Graphics\Manipulators\Imagick|\ElkArte\Graphics\Manipulators\Gd2 */
	protected $_manipulator;

	/** @var string filename we are working with */
	protected $_fileName = '';

	/** @var bool if to force only using GD even if Imagick is present */
	protected $_force_gd = false;

	/** @var bool if the image has been loaded into the manipulator */
	protected $_image_loaded = false;

	/**
	 * Image constructor.
	 *
	 * @param string $fileName
	 * @param bool $force_gd
	 */
	public function __construct($fileName, $force_gd = false)
	{
		$this->_fileName = $fileName;
		$this->_force_gd = $force_gd;

		$this->loadImage();
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
	 * Load an image from a file or web address into the active graphics library
	 * @throws \ImagickException
	 */
	protected function loadImage()
	{
		// Determine and set what image library we will use
		try
		{
			$this->setManipulator();
		}
		catch (\Exception $e)
		{
			// Nothing to do
		}

		if ($this->isWebAddress())
		{
			$success = $this->_manipulator->createImageFromWeb();
		}
		else
		{
			$success = $this->_manipulator->createImageFromFile();
		}

		if ($success === true)
		{
			$this->_image_loaded = true;
		}
	}

	/**
	 * Return if the source is actually a web address vs local file
	 *
	 * @return bool
	 */
	protected function isWebAddress()
	{
		return substr($this->_fileName, 0, 7) === 'http://' || substr($this->_fileName, 0, 8) === 'https://';
	}

	/**
	 * Its how big ?
	 *
	 * @return int
	 */
	public function getFilesize()
	{
		return @filesize($this->_fileName);
	}

	/**
	 * If the file is an image or not
	 *
	 * @return bool
	 */
	public function isImage()
	{
		return substr(get_finfo_mime($this->_fileName), 0, 5) === 'image';
	}

	/**
	 * Creates a thumbnail from and image.
	 *
	 * - "recipe" function to create, rotate and save a thumbnail of a given image
	 * - Thumbnail will be proportional to the original image
	 * - Will adjust for image rotation if needed.
	 * - Saves the thumbnail file
	 *
	 * @param int $max_width allowed width
	 * @param int $max_height allowed height
	 * @param string $dstName name to save
	 * @param string $format image format to save the thumbnail
	 * @return bool
	 * @throws \Exception
	 */
	public function createThumbnail($max_width, $max_height, $dstName = '', $format = '')
	{
		global $modSettings;

		// The particulars
		$dstName = $dstName === '' ? $this->_fileName . '_thumb' : $dstName;
		$format = empty($format) && !empty($modSettings['attachment_thumb_png']) ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
		$max_width = max(16, $max_width);
		$max_height = max(16, $max_height);

		// Do the actual resize, thumbnails by default strip EXIF data to save space
		$success = $this->resizeImage($max_width, $max_height, true);

		// Save our work
		if ($success)
		{
			$this->saveImage($dstName, $format);
			$success = new Image($dstName);
		}
		else
		{
			@touch($dstName);
		}

		return $success;
	}

	/**
	 * Calls functions to correct an images orientation based on the EXIF orientation flag
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function autoRotateImage()
	{
		$this->getOrientation();

		// For now we only process jpeg images, so check that we have one
		if (!isset($this->_manipulator->sizes[2]))
		{
			$this->_manipulator->getSize();
		}

		// Not a jpeg or not rotated, done!
		if ($this->_manipulator->sizes[2] !== 2 || $this->_manipulator->orientation <= 1)
		{
			return false;
		}

		try
		{
			$this->_manipulator->autoRotateImage();
		}
		catch (\Exception $e)
		{
			// Nice try
			return false;
		}

		return true;
	}

	/**
	 * Finds the orientation flag of the image as defined by its EXIF data
	 *
	 * @return int
	 */
	public function getOrientation()
	{
		return $this->_manipulator->getOrientation();
	}

	/**
	 * Resize an image from a remote location or a local file.
	 *
	 * What it does:
	 *
	 * - Resize an image, proportionally, to fit withing WxH limits
	 * - The file would have the format preferred_format if possible,
	 * otherwise the default format is jpeg.
	 * - Optionally removes exif header data to make a smaller image
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize Always resize the image (force scale up)
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 *
	 * @return bool Whether the thumbnail creation was successful.
	 */
	public function resizeImage($max_width, $max_height, $strip = false, $force_resize = true)
	{
		// Nothing to do without GD or IM or an Image
		if ($this->_manipulator === null)
		{
			return false;
		}

		try
		{
			return $this->_manipulator->resizeImage($max_width, $max_height, $strip, $force_resize);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Save the image object to a file.
	 *
	 * @param string $file_name name to save the image to
	 * @param int $preferred_format what format to save the image
	 * @param int $quality some formats require we provide a compression quality
	 *
	 * @return bool
	 */
	public function saveImage($file_name = '', $preferred_format = IMAGETYPE_JPEG, $quality = 85)
	{
		$success = $this->_manipulator->output($file_name, $preferred_format, $quality);

		return $success;
	}

	/**
	 * Used to re-encodes an image to a specified image format
	 *
	 * What it does:
	 *
	 * - creates a copy of the file at the same location.
	 * - the file would have the format preferred_format if possible, otherwise the default format is jpeg.
	 * - strips the exif data
	 * - the function makes sure that all non-essential image contents are disposed.
	 *
	 * @return bool
	 * @throws \ImagickException
	 */
	public function reencodeImage()
	{
		// The image should already be loaded
		if (!$this->_image_loaded)
		{
			return false;
		}

		// re-encode the image at the same size it is now, strip exif data.
		$sizes = $this->getSize();
		$success = $this->resizeImage(null, null, true, true);

		// if all went well, and its valid, save it back in place
		if ($success && !empty(Image::DEFAULT_FORMATS[$sizes[2]]))
		{
			// Write over the original file
			$success = $this->saveImage($this->_fileName, $sizes[2]);
			$this->loadImage();
		}

		return $success;
	}

	/**
	 * Return or set (via getimagesize or getimagesizefromstring) some image details such
	 * as size and mime type
	 *
	 * @return array
	 */
	public function getSize()
	{
		return $this->_manipulator->sizes;
	}

	/**
	 * Searches through the file to see if there's potentially harmful content.
	 *
	 * What it does:
	 *
	 * - Basic search of an image file for potential web (php/script) infections
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function checkImageContents()
	{
		$fp = fopen($this->_fileName, 'rb');

		// If we can't open it to scan, go no further
		if ($fp === false)
		{
			throw new Exception('Post.attach_timeout');
		}

		$prev_chunk = '';
		while (!feof($fp))
		{
			$cur_chunk = fread($fp, 32768);
			$test_chunk = $prev_chunk . $cur_chunk;

			// Though not exhaustive lists, better safe than sorry.
			if (preg_match('~<\\?php|<script\s+language\s*=\s*(?:php|"php"|\'php\')\s*>~i', $test_chunk) === 1)
			{
				fclose($fp);

				return false;
			}

			$prev_chunk = $cur_chunk;
		}

		fclose($fp);

		return true;
	}
}
