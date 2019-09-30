<?php

/**
 * Handles sound processing. In order to make sure the visual
 * verification is still accessible for all users, a sound clip is being added
 * that reads the letters that are being shown.
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

use ElkArte\Cache\Cache;
use ElkArte\User;

/**
 * Creates a wave file that spells the letters of $word.
 * Tries the user's language first, and defaults to english.
 * Used by action=register;sa=verificationcode() (Register.controller.php).
 *
 * @param string $word
 *
 * @return bool
 */
function createWaveFile($word)
{
	global $settings;

	$cache = Cache::instance();

	// Allow max 2 requests per 20 seconds.
	if (($ip = $cache->get('wave_file/' . User::$info->ip, 20)) > 2 || ($ip2 = $cache->get('wave_file/' . User::$info->ip2, 20)) > 2)
	{
		die(header('HTTP/1.1 400 Bad Request'));
	}

	$cache->put('wave_file/' . User::$info->ip, $ip ? $ip + 1 : 1, 20);
	$cache->put('wave_file/' . User::$info->ip2, $ip2 ? $ip2 + 1 : 1, 20);

	$unpacked = unpack('n', md5($word . session_id()));
	mt_srand(end($unpacked));

	// Try to see if there's a sound font in the user's language.
	if (file_exists($settings['default_theme_dir'] . '/fonts/sound/a.' . User::$info->language . '.wav'))
	{
		$sound_language = User::$info->language;
	}
	// English should be there.
	elseif (file_exists($settings['default_theme_dir'] . '/fonts/sound/a.english.wav'))
	{
		$sound_language = 'english';
	}
	// Guess not...
	else
	{
		return false;
	}

	// File names are in lower case so lets make sure that we are only using a lower case string
	$word = strtolower($word);

	// Loop through all letters of the word $word.
	$sound_word = '';
	$str_len = strlen($word);
	for ($i = 0; $i < $str_len; $i++)
	{
		$sound_letter = implode('', file($settings['default_theme_dir'] . '/fonts/sound/' . $word[$i] . '.' . $sound_language . '.wav'));
		if (strpos($sound_letter, 'data') === false)
		{
			return false;
		}

		$sound_letter = substr($sound_letter, strpos($sound_letter, 'data') + 8);
		switch ($word[$i] === 's' ? 0 : mt_rand(0, 2))
		{
			case 0:
				for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++)
				{
					for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++)
					{
						$sound_word .= $word[$i] === 's' ? $sound_letter[$j] : chr(mt_rand(max(ord($sound_letter[$j]) - 1, 0x00), min(ord($sound_letter[$j]) + 1, 0xFF)));
					}
				}
				break;

			case 1:
				for ($j = 0, $n = strlen($sound_letter) - 1; $j < $n; $j += 2)
				{
					$sound_word .= (mt_rand(0, 3) == 0 ? '' : $sound_letter[$j]) . (mt_rand(0, 3) === 0 ? $sound_letter[$j + 1] : $sound_letter[$j]) . (mt_rand(0, 3) === 0 ? $sound_letter[$j] : $sound_letter[$j + 1]) . $sound_letter[$j + 1] . (mt_rand(0, 3) == 0 ? $sound_letter[$j + 1] : '');
				}
				$sound_word .= str_repeat($sound_letter[$n], 2);
				break;

			case 2:
				$shift = 0;
				for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++)
				{
					if (mt_rand(0, 10) === 0)
					{
						$shift += mt_rand(-3, 3);
					}
					for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++)
					{
						$sound_word .= chr(min(max(ord($sound_letter[$j]) + $shift, 0x00), 0xFF));
					}
				}
				break;
		}

		$sound_word .= str_repeat(chr(0x80), mt_rand(10000, 10500));
	}

	$data_size = strlen($sound_word);
	$chunk_id = 0x52494646; // Contains the letters "RIFF" (0x52494646 in big-endian form).
	$chunk_size = $data_size + 0x20; // This is the size of the entire file in bytes minus 8 bytes for the two leading fields not included in this count: ChunkID and ChunkSize.
	$format = 0x57415645; // Contains the letters "WAVE" (0x57415645 big-endian form).
	$subchunk1ID = 0x666D7420; // Contains the letters "fmt "(0x666d7420 big-endian form).
	$subchunk1Size = 0x10000000; // 16 for PCM.  This is the size of the rest of the Subchunk which follows this number.
	$audioFormat = 0x0100; // PCM = 1 (i.e. Linear quantization)
	$numChannels = 0x0100; // Mono = 1, Stereo = 2, etc.
	$sample_rate = 0x803E0000; // 8000, 16000, etc
	$byteRate = 0x803E0000; // = SampleRate * NumChannels * BitsPerSample/8 (for use the same as sample rate)
	$blockAlign = 0x0100; // = NumChannels * BitsPerSample/8
	$bitsPerSample = 0x0800; // 8 bits = 8, 16 bits = 16, etc.
	$subchunk2ID = 0x64617461; // Contains the letters "data" (0x64617461 big-endian form).

	// Create the wav file
	$wav = pack('NVNNNnnNNnnNV', $chunk_id, $chunk_size, $format, $subchunk1ID, $subchunk1Size, $audioFormat, $numChannels, $sample_rate, $byteRate, $blockAlign, $bitsPerSample, $subchunk2ID, $data_size) . $sound_word;
	$time = $chunk_size / 16000;

	// Clear anything in the buffers
	while (@ob_get_level() > 0)
	{
		@ob_end_clean();
	}

	// Set up our headers
	header('Content-Encoding: none');
	header('Content-Duration: ' . round($time, 0));
	header('Content-Disposition: inline; filename="captcha.wav"');
	header('Content-Type: audio/x-wav');
	header('Cache-Control: no-cache');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Accept-Ranges: bytes');

	// Output the wav.
	$range = set_range(strlen($wav));
	$length = $range[1] - $range[0] + 1;
	$stub = substr($wav, $range[0], $length);
	header('Content-Length: ' . strlen($stub));
	echo $stub;

	return true;
}

/**
 * Determines the range (file substr) to return in response to a media range request
 *
 * - Sets the substr range if the HTTP_RANGE header is found
 * - Returns 206 Partial Content along with Content-Range header of the chosen range
 * - Supports single range request only
 *
 * @param int $file_size
 * @return array
 */
function set_range($file_size)
{
	$range = array(0, $file_size - 1);

	// A range seek request (iOS, Android Chrome, etc)
	if (isset($_SERVER['HTTP_RANGE']))
	{
		preg_match('~bytes=(\d+)?-(\d+)?~', $_SERVER['HTTP_RANGE'], $matches);

		// range 0-1 or range 0-123456 style
		if ($matches[1] !== '' && !empty($matches[2]))
		{
			$range = array(intval($matches[1]), intval($matches[2]));
		}
		// range x- Last bytes of the file starting from byte x
		elseif ($matches[1] !== '' && empty($matches[2]))
		{
			$range = array(intval($matches[1]), $file_size - 1);
		}
		// range -y Last y bytes of the file
		elseif (empty($matches[1]) && !empty($matches[2]))
		{
			$range = array($file_size - intval($matches[2]), $file_size - 1);
		}

		header('HTTP/1.1 206 Partial Content');
		header('Content-Range: bytes ' . $range[0] . '-' . $range[1] . '/' . $file_size);
	}

	return $range;
}
