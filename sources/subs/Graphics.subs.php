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
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\User;

/**
 * Show an image containing the visual verification code for registration.
 *
 * What it does:
 *
 * - Requires the GD2 extension.
 * - Uses a random font for each letter from default_theme_dir/fonts.
 * - Outputs a png if possible, otherwise a gif.
 *
 * @param string $code The code to display
 *
 * @return false|null false if something goes wrong.
 * @package Graphics
 */
function showCodeImage($code)
{
	global $settings, $modSettings;

	// No GD, No image code
	if (get_extension_funcs('gd') === [])
	{
		return false;
	}

	// What type are we going to be doing?
	// Note: The higher the value of visual_verification_type the harder the verification is
	// from 0 as disabled through to 4 as "Very hard".
	$imageType = $modSettings['visual_verification_type'];

	// Special case to allow the admin center to show samples.
	if (User::$info->is_admin && isset($_GET['type']))
	{
		$imageType = (int) $_GET['type'];
	}

	// Some quick references for what we do.
	// Do we show no, low or high noise?
	$noiseType = $imageType == 3 ? 'low' : ($imageType == 4 ? 'high' : ($imageType == 5 ? 'extreme' : 'none'));
	// Can we have more than one font in use?
	$varyFonts = $imageType > 1;
	// Just a plain white background?
	$simpleBGColor = $imageType < 3;
	// Plain black foreground?
	$simpleFGColor = $imageType == 0;
	// High much to rotate each character.
	$rotationType = $imageType == 1 ? 'none' : ($imageType > 3 ? 'low' : 'high');
	// Do we show some characters inverse?
	$showReverseChars = $imageType > 3;
	// Special case for not showing any characters.
	$disableChars = $imageType == 0;
	// What do we do with the font colors. Are they one color, close to one color or random?
	$fontColorType = $imageType == 1 ? 'plain' : ($imageType > 3 ? 'random' : 'cyclic');
	// Are the fonts random sizes?
	$fontSizeRandom = $imageType > 3;
	// How much space between characters?
	$fontHorSpace = $imageType > 3 ? 'high' : ($imageType == 1 ? 'medium' : 'minus');
	// Where do characters sit on the image? (Fixed position or random/very random)
	$fontVerPos = $imageType == 1 ? 'fixed' : ($imageType > 3 ? 'vrandom' : 'random');
	// Make font semi-transparent?
	$fontTrans = $imageType == 2 || $imageType == 3;
	// Give the image a border?
	$hasBorder = $simpleBGColor;

	// The amount of pixels in between characters.
	$character_spacing = 1;

	// What color is the background - generally white unless we're on "hard".
	if ($simpleBGColor)
	{
		$background_color = array(255, 255, 255);
	}
	else
	{
		$background_color = isset($settings['verification_background']) ? $settings['verification_background'] : array(236, 237, 243);
	}

	// The color of the characters shown (red, green, blue).
	if ($simpleFGColor)
	{
		$foreground_color = array(0, 0, 0);
	}
	else
	{
		$foreground_color = array(64, 101, 136);

		// Has the theme author requested a custom color?
		if (isset($settings['verification_foreground']))
		{
			$foreground_color = $settings['verification_foreground'];
		}
	}

	if (!is_dir($settings['default_theme_dir'] . '/fonts'))
	{
		return false;
	}

	// Can we use true type fonts?
	$can_do_ttf = function_exists('imagettftext');

	// Get a list of the available fonts.
	$font_dir = dir($settings['default_theme_dir'] . '/fonts');
	$font_list = array();
	$ttfont_list = array();
	while ($entry = $font_dir->read())
	{
		if (preg_match('~^(.+)\.gdf$~', $entry, $matches) === 1)
		{
			$font_list[] = $entry;
		}
		elseif (preg_match('~^(.+)\.ttf$~', $entry, $matches) === 1)
		{
			$ttfont_list[] = $entry;
		}
	}

	if (empty($font_list) && ($can_do_ttf && empty($ttfont_list)))
	{
		return false;
	}

	// For non-hard things don't even change fonts.
	if (!$varyFonts)
	{
		$font_list = !empty($font_list) ? array($font_list[0]) : $font_list;

		// Try use Screenge if we can - it looks good!
		if (in_array('VDS_New.ttf', $ttfont_list))
		{
			$ttfont_list = array('VDS_New.ttf');
		}
		else
		{
			$ttfont_list = empty($ttfont_list) ? array() : array($ttfont_list[0]);
		}
	}

	// Create a list of characters to be shown.
	$characters = array();
	$loaded_fonts = array();
	$str_len = strlen($code);
	for ($i = 0; $i < $str_len; $i++)
	{
		$characters[$i] = array(
			'id' => $code[$i],
			'font' => array_rand($can_do_ttf ? $ttfont_list : $font_list),
		);

		$loaded_fonts[$characters[$i]['font']] = null;
	}

	// Load all fonts and determine the maximum font height.
	if (!$can_do_ttf)
	{
		foreach ($loaded_fonts as $font_index => $dummy)
		{
			$loaded_fonts[$font_index] = imageloadfont($settings['default_theme_dir'] . '/fonts/' . $font_list[$font_index]);
		}
	}

	// Determine the dimensions of each character.
	$total_width = $character_spacing * strlen($code) + 50;
	$max_height = 0;
	foreach ($characters as $char_index => $character)
	{
		if ($can_do_ttf)
		{
			$font_size = $fontSizeRandom ? mt_rand(17, 19) : 17;

			$img_box = imagettfbbox($font_size, 0, $settings['default_theme_dir'] . '/fonts/' . $ttfont_list[$character['font']], $character['id']);

			$characters[$char_index]['width'] = abs($img_box[2] - $img_box[0]);
			$characters[$char_index]['height'] = abs($img_box[7] - $img_box[1]);
		}
		else
		{
			$characters[$char_index]['width'] = imagefontwidth($loaded_fonts[$character['font']]);
			$characters[$char_index]['height'] = imagefontheight($loaded_fonts[$character['font']]);
		}

		$max_height = max($characters[$char_index]['height'] + 5, $max_height);
		$total_width += $characters[$char_index]['width'] + 2;
	}

	// Create an image.
	$code_image = imagecreatetruecolor($total_width, $max_height);

	// Draw the background.
	$bg_color = imagecolorallocate($code_image, $background_color[0], $background_color[1], $background_color[2]);
	imagefilledrectangle($code_image, 0, 0, $total_width - 1, $max_height - 1, $bg_color);

	// Randomize the foreground color a little.
	for ($i = 0; $i < 3; $i++)
	{
		$foreground_color[$i] = mt_rand(max($foreground_color[$i] - 3, 0), min($foreground_color[$i] + 3, 255));
	}
	$fg_color = imagecolorallocate($code_image, $foreground_color[0], $foreground_color[1], $foreground_color[2]);

	// Color for the noise dots.
	$dotbgcolor = array();
	for ($i = 0; $i < 3; $i++)
	{
		$dotbgcolor[$i] = $background_color[$i] < $foreground_color[$i] ? mt_rand(0, max($foreground_color[$i] - 20, 0)) : mt_rand(min($foreground_color[$i] + 20, 255), 255);
	}
	$randomness_color = imagecolorallocate($code_image, $dotbgcolor[0], $dotbgcolor[1], $dotbgcolor[2]);

	// Some squares/rectangles for new extreme level
	if ($noiseType == 'extreme')
	{
		for ($i = 0; $i < rand(1, 5); $i++)
		{
			$x1 = rand(0, $total_width / 4);
			$x2 = $x1 + round(rand($total_width / 4, $total_width));
			$y1 = rand(0, $max_height);
			$y2 = $y1 + round(rand(0, $max_height / 3));
			imagefilledrectangle($code_image, $x1, $y1, $x2, $y2, mt_rand(0, 1) !== 0 ? $fg_color : $randomness_color);
		}
	}

	// Fill in the characters.
	if (!$disableChars)
	{
		$cur_x = 0;
		$last_index = -1;
		foreach ($characters as $char_index => $character)
		{
			// How much rotation will we give?
			if ($rotationType == 'none')
			{
				$angle = 0;
			}
			else
			{
				$angle = mt_rand(-100, 100) / ($rotationType == 'high' ? 6 : 10);
			}

			// What color shall we do it?
			if ($fontColorType == 'cyclic')
			{
				// Here we'll pick from a set of acceptance types.
				$colors = array(
					array(10, 120, 95),
					array(46, 81, 29),
					array(4, 22, 154),
					array(131, 9, 130),
					array(0, 0, 0),
					array(143, 39, 31),
				);

				// Pick a color, but not the same one twice in a row
				$new_index = $last_index;
				while ($last_index === $new_index)
				{
					$new_index = mt_rand(0, count($colors) - 1);
				}
				$char_fg_color = $colors[$new_index];
				$last_index = $new_index;
			}
			elseif ($fontColorType === 'random')
			{
				$char_fg_color = array(mt_rand(max($foreground_color[0] - 2, 0), $foreground_color[0]), mt_rand(max($foreground_color[1] - 2, 0), $foreground_color[1]), mt_rand(max($foreground_color[2] - 2, 0), $foreground_color[2]));
			}
			else
			{
				$char_fg_color = array($foreground_color[0], $foreground_color[1], $foreground_color[2]);
			}

			if (!empty($can_do_ttf))
			{
				$font_size = $fontSizeRandom ? mt_rand(17, 19) : 18;

				// Work out the sizes - also fix the character width cause TTF not quite so wide!
				$font_x = $fontHorSpace === 'minus' && $cur_x > 0 ? $cur_x - 3 : $cur_x + 5;
				$font_y = $max_height - ($fontVerPos === 'vrandom' ? mt_rand(2, 8) : ($fontVerPos === 'random' ? mt_rand(3, 5) : 5));

				// What font face?
				if (!empty($ttfont_list))
				{
					$fontface = $settings['default_theme_dir'] . '/fonts/' . $ttfont_list[mt_rand(0, count($ttfont_list) - 1)];
				}

				// What color are we to do it in?
				$is_reverse = $showReverseChars ? mt_rand(0, 1) : false;
				$char_color = $fontTrans ? imagecolorallocatealpha($code_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2], 50) : imagecolorallocate($code_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2]);

				$fontcord = @imagettftext($code_image, $font_size, $angle, $font_x, $font_y, $char_color, $fontface, $character['id']);
				if (empty($fontcord))
				{
					$can_do_ttf = false;
				}
				elseif ($is_reverse !== false)
				{
					imagefilledpolygon($code_image, $fontcord, 4, $fg_color);

					// Put the character back!
					imagettftext($code_image, $font_size, $angle, $font_x, $font_y, $randomness_color, $fontface, $character['id']);
				}

				if ($can_do_ttf)
				{
					$cur_x = max($fontcord[2], $fontcord[4]) + ($angle == 0 ? 0 : 3);
				}
			}

			if (!$can_do_ttf)
			{
				$char_image = imagecreatetruecolor($character['width'], $character['height']);
				$char_bgcolor = imagecolorallocate($char_image, $background_color[0], $background_color[1], $background_color[2]);
				imagefilledrectangle($char_image, 0, 0, $character['width'] - 1, $character['height'] - 1, $char_bgcolor);
				imagechar($char_image, $loaded_fonts[$character['font']], 0, 0, $character['id'], imagecolorallocate($char_image, $char_fg_color[0], $char_fg_color[1], $char_fg_color[2]));
				$rotated_char = imagerotate($char_image, mt_rand(-100, 100) / 10, $char_bgcolor);
				imagecopy($code_image, $rotated_char, $cur_x, 0, 0, 0, $character['width'], $character['height']);
				imagedestroy($rotated_char);
				imagedestroy($char_image);

				$cur_x += $character['width'] + $character_spacing;
			}
		}
	}
	// If disabled just show a cross.
	else
	{
		imageline($code_image, 0, 0, $total_width, $max_height, $fg_color);
		imageline($code_image, 0, $max_height, $total_width, 0, $fg_color);
	}

	// Make the background color transparent on the hard image.
	if (!$simpleBGColor)
	{
		imagecolortransparent($code_image, $bg_color);
	}

	if ($hasBorder)
	{
		imagerectangle($code_image, 0, 0, $total_width - 1, $max_height - 1, $fg_color);
	}

	// Add some noise to the background?
	if ($noiseType != 'none')
	{
		for ($i = mt_rand(0, 2); $i < $max_height; $i += mt_rand(1, 2))
		{
			for ($j = mt_rand(0, 10); $j < $total_width; $j += mt_rand(1, 10))
			{
				imagesetpixel($code_image, $j, $i, mt_rand(0, 1) !== 0 ? $fg_color : $randomness_color);
			}
		}

		// Put in some lines too?
		if ($noiseType != 'extreme')
		{
			$num_lines = $noiseType == 'high' ? mt_rand(3, 7) : mt_rand(2, 5);
			for ($i = 0; $i < $num_lines; $i++)
			{
				if (mt_rand(0, 1) !== 0)
				{
					$x1 = mt_rand(0, $total_width);
					$x2 = mt_rand(0, $total_width);
					$y1 = 0;
					$y2 = $max_height;
				}
				else
				{
					$y1 = mt_rand(0, $max_height);
					$y2 = mt_rand(0, $max_height);
					$x1 = 0;
					$x2 = $total_width;
				}
				imagesetthickness($code_image, mt_rand(1, 2));
				imageline($code_image, $x1, $y1, $x2, $y2, mt_rand(0, 1) !== 0 ? $fg_color : $randomness_color);
			}
		}
		else
		{
			// Put in some ellipse
			$num_ellipse = $noiseType == 'extreme' ? mt_rand(6, 12) : mt_rand(2, 6);
			for ($i = 0; $i < $num_ellipse; $i++)
			{
				$x1 = round(rand(($total_width / 4) * -1, $total_width + ($total_width / 4)));
				$x2 = round(rand($total_width / 2, 2 * $total_width));
				$y1 = round(rand(($max_height / 4) * -1, $max_height + ($max_height / 4)));
				$y2 = round(rand($max_height / 2, 2 * $max_height));
				imageellipse($code_image, $x1, $y1, $x2, $y2, mt_rand(0, 1) !== 0 ? $fg_color : $randomness_color);
			}
		}
	}

	// Show the image.
	if (function_exists('imagepng'))
	{
		header('Content-type: image/png');
		imagepng($code_image);
	}
	else
	{
		header('Content-type: image/gif');
		imagegif($code_image);
	}

	// Bail out.
	imagedestroy($code_image);
	die();
}

/**
 * Show a letter for the visual verification code.
 *
 * - Alternative function for showCodeImage() in case GD is missing.
 * - Includes an image from a random sub directory of default_theme_dir/fonts.
 *
 * @param string $letter A letter to show as an image
 *
 * @return false|null false if something goes wrong.
 * @package Graphics
 */
function showLetterImage($letter)
{
	global $settings;

	if (!is_dir($settings['default_theme_dir'] . '/fonts'))
	{
		return false;
	}

	// Get a list of the available font directories.
	$font_dir = dir($settings['default_theme_dir'] . '/fonts');
	$font_list = array();
	while ($entry = $font_dir->read())
	{
		if ($entry[0] !== '.' && is_dir($settings['default_theme_dir'] . '/fonts/' . $entry) && file_exists($settings['default_theme_dir'] . '/fonts/' . $entry . '.gdf'))
		{
			$font_list[] = $entry;
		}
	}

	if (empty($font_list))
	{
		return false;
	}

	// Pick a random font.
	$random_font = $font_list[array_rand($font_list)];

	// Check if the given letter exists.
	if (!file_exists($settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . $letter . '.gif'))
	{
		return false;
	}

	// Include it!
	header('Content-type: image/gif');
	include($settings['default_theme_dir'] . '/fonts/' . $random_font . '/' . $letter . '.gif');

	// Nothing more to come.
	die();
}
