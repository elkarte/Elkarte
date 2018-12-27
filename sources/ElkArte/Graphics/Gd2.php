<?php

/**
 * This file deals with low-level graphics operations performed on images,
 * specially as needed for avatars (uploaded avatars), attachments, or
 * visual verification images.
 *
 * TrueType fonts supplied by www.LarabieFonts.com
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Graphics;

class Gd2 extends AbstractManipulator
{
	protected $src_img = null;
	protected static $gd2 = false;

	/**
	 * Used to determine if GD2 whether the GD2 library is present.
	 * The parameter returns the check for imagecreatetruecolor
	 *
	 * @param bool $gd2
	 *
	 * @return bool Whether or not GD is available.
	 */
	public static function canUse($gd2 = false)
	{
		// Check to see if GD is installed and what version.
		if (($extensionFunctions = get_extension_funcs('gd')) === false)
		{
			return false;
		}

		// Also determine if GD2 is installed and store it in a global.
		self::$gd2 = in_array('imagecreatetruecolor', $extensionFunctions) && function_exists('imagecreatetruecolor');

		if ($gd2 === true)
		{
			return self::$gd2;
		}

		return true;
	}

	public function __construct($fileName, $do_memory_check = true)
	{
		$this->fileName = $fileName;
		$this->do_memory_check = $do_memory_check;
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
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param int $preferred_format Used by Imagick/resizeImage
	 * @param bool $strip Allow IM to remove exif data as GD always will
	 * @param bool $force_resize Always resize the image (force scale up)
	 *
	 * @return boolean Whether the thumbnail creation was successful.
	 */
	public function resizeImageFile($max_width, $max_height, $preferred_format = 0, $strip = false, $force_resize = true)
	{
		if ($this->memoryCheck() === false)
		{
			throw new \Exception('Not enough memory');
		}

		if (!isset(Image::DEFAULT_FORMATS[$sizes[2]]) || !function_exists('imagecreatefrom' . Image::DEFAULT_FORMATS[$sizes[2]]))
		{
			throw new \Exception('Format not supported');
		}

		$imagecreatefrom = 'imagecreatefrom' . Image::DEFAULT_FORMATS[$sizes[2]];
		$this->image = @$imagecreatefrom($this->fileName);
		if ($this->image !== false)
		{
			return $this->resizeImage($max_width, $max_height, $force_resize, $preferred_format, $strip);
		}

		return false;
	}

	/**
	 * See if we have enough memory to thumbnail an image
	 *
	 * @param int[] $sizes image size
	 *
	 * @return bool Whether or not the memory is available.
	 */
	protected function memoryCheck()
	{
		// Just to be sure
		if (!is_array($this->sizes) || $this->sizes[0] === -1)
		{
			return true;
		}

		// Doing the old 'set it and hope' way?
		if ($this->do_memory_check === false)
		{
			detectServer()->setMemoryLimit('128M');
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

	/**
	 * Resize an image proportionally to fit within the defined max_width and max_height limits
	 *
	 * What it does:
	 *
	 * - Will do nothing to the image if the file fits within the size limits
	 * - It'll use GD to achieve better quality (imagecopyresampled)
	 * - Saves the new image to destination_filename, in the preferred_format
	 * if possible, default is jpeg.
	 *
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize = false Whether to override defaults and resize it
	 * @param int $preferred_format - The preferred format
	 *   - 0 to use jpeg
	 *   - 1 for gif
	 *   - 2 to force jpeg
	 *   - 3 for png
	 *   - 6 for bmp
	 *   - 15 for wbmp
	 * @param bool $strip Whether to have IM strip EXIF data as GD will
	 *
	 * @return bool Whether resize was successful.
	 */
	protected function resizeImage($max_width, $max_height, $force_resize = false, $preferred_format = 0, $strip = false)
	{
		$src_width = imagesx($this->image);
		$src_height = imagesy($this->image);

		// It should never happen, but let's keep these two as a failsafe
		$max_width = $max_width === null ? $src_width : $max_width;
		$max_height = $max_height === null ? $src_height : $max_height;

		// Determine whether to resize to max width or to max height (depending on the limits.)
		if (!empty($max_width) || !empty($max_height))
		{
			if (!empty($max_width) && (empty($max_height) || $src_height * $max_width / $src_width <= $max_height))
			{
				$dst_width = $max_width;
				$dst_height = floor($src_height * $max_width / $src_width);
			}
			elseif (!empty($max_height))
			{
				$dst_width = floor($src_width * $max_height / $src_height);
				$dst_height = $max_height;
			}

			// Don't bother resizing if it's already smaller...
			if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
			{
				// (make a true color image, because it just looks better for resizing.)
				if (self::$gd2)
				{
					$dst_img = imagecreatetruecolor($dst_width, $dst_height);

					// Deal nicely with a PNG - because we can.
					if ((!empty($preferred_format)) && ($preferred_format == 3))
					{
						imagealphablending($dst_img, false);
						if (function_exists('imagesavealpha'))
						{
							imagesavealpha($dst_img, true);
						}
					}
				}
				else
				{
					$dst_img = imagecreate($dst_width, $dst_height);
				}

				// Resize it!
				if (self::$gd2)
				{
					imagecopyresampled($dst_img, $this->image, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
				}
				else
				{
					$this->imagecopyresamplebicubic($dst_img, $this->image, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
				}
			}
			else
			{
				$dst_img = $this->image;
			}
		}
		else
		{
			$dst_img = $this->image;
		}

		$success = false;
		// Save the image as ...
		if (!empty($preferred_format) && ($preferred_format == 3) && function_exists('imagepng'))
		{
			$success = imagepng($dst_img, $this->fileName);
		}
		elseif (!empty($preferred_format) && ($preferred_format == 1) && function_exists('imagegif'))
		{
			$success = imagegif($dst_img, $this->fileName);
		}
		elseif (function_exists('imagejpeg'))
		{
			$success = imagejpeg($dst_img, $this->fileName, 80);
		}

		// Free the memory.
		imagedestroy($this->image);
		if ($dst_img != $this->image)
		{
			imagedestroy($dst_img);
		}

		return $success;
	}

	/**
	 * Autorotate an image based on its EXIF Orientation tag.
	 *
	 * What it does:
	 *
	 * - GD only
	 * - Checks exif data for orientation flag and rotates image so its proper
	 * - Does not update orientation flag as GD removes EXIF data
	 * - Only works with jpeg images, could add TIFF as well
	 * - Writes the update image back to $image_name
	 *
	 * @return bool
	 */
	function autoRotateImage()
	{
		// Read the EXIF data
		$exif = function_exists('exif_read_data') ? @exif_read_data($this->fileName) : array();

		// We're only interested in the exif orientation
		$orientation = isset($exif['Orientation']) ? $exif['Orientation'] : 0;

		// For now we only process jpeg images, so check that we have one
		$sizes = $this->getSize();

		// Not a jpeg or not rotated, done!
		if ($sizes[2] !== 2 || $orientation === 0 || $this->memoryCheck() === false)
		{
			return false;
		}

		// Time to spin and mirror as needed
		switch ($orientation)
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

		// Save the updated image, free resources
		imagejpeg($source, $image_name);
		imagedestroy($source);

		return true;
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
			$background = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
			$this->image = imagerotate($this->image, $degrees, $background);
		}
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
		imageflip($this->image, $axis === 'vertical' ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
	}

	/**
	 * Copy / resize an image using GD bicubic methods
	 *
	 * What it does:
	 *
	 * - Used when imagecopyresample() is not available
	 * - Uses bicubic resizing methods which are lower quality then imagecopyresample
	 *
	 * @param resource $dst_img The destination image - a GD image resource
	 * @param resource $src_img The source image - a GD image resource
	 * @param int $dst_x The "x" coordinate of the destination image
	 * @param int $dst_y The "y" coordinate of the destination image
	 * @param int $src_x The "x" coordinate of the source image
	 * @param int $src_y The "y" coordinate of the source image
	 * @param int $dst_w The width of the destination image
	 * @param int $dst_h The height of the destination image
	 * @param int $src_w The width of the destination image
	 * @param int $src_h The height of the destination image
	 */
	protected function imagecopyresamplebicubic($dst_img, $src_img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h)
	{
		$palsize = imagecolorstotal($src_img);
		for ($i = 0; $i < $palsize; $i++)
		{
			$colors = imagecolorsforindex($src_img, $i);
			imagecolorallocate($dst_img, $colors['red'], $colors['green'], $colors['blue']);
		}

		$scaleX = ($src_w - 1) / $dst_w;
		$scaleY = ($src_h - 1) / $dst_h;

		$scaleX2 = (int) $scaleX / 2;
		$scaleY2 = (int) $scaleY / 2;

		for ($j = $src_y; $j < $dst_h; $j++)
		{
			$sY = (int) $j * $scaleY;
			$y13 = $sY + $scaleY2;

			for ($i = $src_x; $i < $dst_w; $i++)
			{
				$sX = (int) $i * $scaleX;
				$x34 = $sX + $scaleX2;

				$color1 = imagecolorsforindex($src_img, imagecolorat($src_img, $sX, $y13));
				$color2 = imagecolorsforindex($src_img, imagecolorat($src_img, $sX, $sY));
				$color3 = imagecolorsforindex($src_img, imagecolorat($src_img, $x34, $y13));
				$color4 = imagecolorsforindex($src_img, imagecolorat($src_img, $x34, $sY));

				$red = ($color1['red'] + $color2['red'] + $color3['red'] + $color4['red']) / 4;
				$green = ($color1['green'] + $color2['green'] + $color3['green'] + $color4['green']) / 4;
				$blue = ($color1['blue'] + $color2['blue'] + $color3['blue'] + $color4['blue']) / 4;

				$color = imagecolorresolve($dst_img, $red, $green, $blue);
				if ($color == -1)
				{
					if ($palsize++ < 256)
						imagecolorallocate($dst_img, $red, $green, $blue);
					$color = imagecolorclosest($dst_img, $red, $green, $blue);
				}

				imagesetpixel($dst_img, $i + $dst_x - $src_x, $j + $dst_y - $src_y, $color);
			}
		}
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
	 * @return resource|boolean The image
	 */
	public function generateTextImage($text, $width = 100, $height = 100, $format = 'png')
	{
		global $settings;

		$create_function = 'image' . $format;

		// Create a white filled box
		$image = imagecreate($width, $height);
		imagecolorallocate($image, 255, 255, 255);

		$text_color = imagecolorallocate($image, 0, 0, 0);
		$font = $settings['default_theme_dir'] . '/fonts/VDS_New.ttf';

		// The loop is to try to fit the text into the image.
		$true_type = function_exists('imagettftext');
		$font_size = $true_type ? 28 : 5;
		do
		{
			if ($true_type)
			{
				$metric = imagettfbbox($font_size, 0, $font, $text);
				$text_width = abs($metric[4] - $metric[0]);
				$text_height = abs($metric[5] - $metric[1]);
			}
			else
			{
				$text_width = imagefontwidth($font_size) * strlen($text);
				$text_height = imagefontheight($font_size);
			}
		} while ($text_width > $width && $font_size-- > 1);

		$w_offset = ($width - $text_width) / 2;
		$h_offset = $true_type ? ($height / 2) + ($text_height / 2) : ($height - $text_height) / 2;

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
		$image = ob_get_contents();
		ob_end_clean();

		return $result ? $image : false;
	}

	/**
	 * Simple wrapper for getimagesize
	 *
	 * @param string $file
	 * @param string|boolean $error return array or false on error
	 *
	 * @return array|boolean
	 */
	function getSize($error = 'array')
	{
		$sizes = @getimagesize($this->fileName);

		// Can't get it, what shall we return
		if (empty($sizes))
		{
			if ($error === 'array')
			{
				$sizes = array(-1, -1, -1);
			}
			else
			{
				$sizes = false;
			}
		}

		return $sizes;
	}
}

if (!function_exists('imagecreatefrombmp'))
{
	/**
	 * It is set only if it doesn't already exist (for forwards compatibility.)
	 *
	 * What it does:
	 *
	 * - It only supports uncompressed bitmaps.
	 * - It only supports standard windows bitmaps (no os/2 variants)
	 * - Returns an image identifier representing the bitmap image
	 * obtained from the given filename.
	 *
	 * @param string $filename The name of the file
	 * @return resource An image identifier representing the bitmap image
	 */
	function imagecreatefrombmp($filename)
	{
		$fp = fopen($filename, 'rb');

		$errors = error_reporting(0);

		// Unpack the general information about the Bitmap Image File, first 14 Bytes
		$header = unpack('vtype/Vsize/Vreserved/Voffset', fread($fp, 14));

		// Unpack the DIB header, it stores detailed information about the bitmap image the pixel format, 40 Bytes long
		$info = unpack('Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vcolorimportant', fread($fp, 40));

		// Not a standard bitmap, bail out
		if ($header['type'] != 0x4D42)
			return false;

		// Create our image canvas with the given WxH
		if (Gd2::canUse(true))
			$dst_img = imagecreatetruecolor($info['width'], $info['height']);
		else
			$dst_img = imagecreate($info['width'], $info['height']);

		// Color bitCounts 1,4,8 have palette information we use
		$palette = array();
		if ($info['bits'] == 1 || $info['bits'] == 4 || $info['bits'] == 8)
		{
			$palette_size = $header['offset'] - 54;

			// Read the palette data
			$palettedata = fread($fp, $palette_size);

			// Create the rgb color array
			$n = 0;
			for ($j = 0; $j < $palette_size; $j++)
			{
				$b = ord($palettedata[$j++]);
				$g = ord($palettedata[$j++]);
				$r = ord($palettedata[$j++]);

				$palette[$n++] = imagecolorallocate($dst_img, $r, $g, $b);
			}
		}

		$scan_line_size = ($info['bits'] * $info['width'] + 7) >> 3;
		$scan_line_align = $scan_line_size & 3 ? 4 - ($scan_line_size & 3) : 0;

		for ($y = 0, $l = $info['height'] - 1; $y < $info['height']; $y++, $l--)
		{
			fseek($fp, $header['offset'] + ($scan_line_size + $scan_line_align) * $l);
			$scan_line = fread($fp, $scan_line_size);

			if (strlen($scan_line) < $scan_line_size)
				continue;

			// 32 bits per pixel
			if ($info['bits'] == 32)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b = ord($scan_line[$j++]);
					$g = ord($scan_line[$j++]);
					$r = ord($scan_line[$j++]);
					$j++;

					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			// 24 bits per pixel
			elseif ($info['bits'] == 24)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b = ord($scan_line[$j++]);
					$g = ord($scan_line[$j++]);
					$r = ord($scan_line[$j++]);

					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			// 16 bits per pixel
			elseif ($info['bits'] == 16)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b1 = ord($scan_line[$j++]);
					$b2 = ord($scan_line[$j++]);

					$word = $b2 * 256 + $b1;

					$b = (($word & 31) * 255) / 31;
					$g = ((($word >> 5) & 31) * 255) / 31;
					$r = ((($word >> 10) & 31) * 255) / 31;

					// Scale the image colors up properly.
					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			// 8 bits per pixel
			elseif ($info['bits'] == 8)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
					imagesetpixel($dst_img, $x, $y, $palette[ord($scan_line[$j++])]);
			}
			// 4 bits per pixel
			elseif ($info['bits'] == 4)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$byte = ord($scan_line[$j++]);

					imagesetpixel($dst_img, $x, $y, $palette[(int) ($byte / 16)]);
					if (++$x < $info['width'])
						imagesetpixel($dst_img, $x, $y, $palette[$byte & 15]);
				}
			}
			// 1 bit
			elseif ($info['bits'] == 1)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$byte = ord($scan_line[$j++]);

					imagesetpixel($dst_img, $x, $y, $palette[(($byte) & 128) != 0]);
					for ($shift = 1; $shift < 8; $shift++)
					{
						if (++$x < $info['width'])
							imagesetpixel($dst_img, $x, $y, $palette[(($byte << $shift) & 128) != 0]);
					}
				}
			}
		}

		fclose($fp);

		error_reporting($errors);

		return $dst_img;
	}
}
