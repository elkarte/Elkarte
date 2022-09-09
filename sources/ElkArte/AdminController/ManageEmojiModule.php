<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\FileFunctions;
use ElkArte\HttpReq;
use ElkArte\Languages\Txt;
use ElkArte\UnZip;

/**
 * Emoji administration controller.
 * This class allows modifying emoji settings for the forum.
 *
 * @package Emoji
 */
abstract class ManageEmojiModule extends AbstractController
{
	/**
	 * Adds the necessary settings to the smiley area of the ACP
	 *
	 * @param array $config_vars
	 */
	public static function integrate_modify_smiley_settings(&$config_vars)
	{
		global $txt;

		Txt::load('Emoji');

		// All the options, well at least some of them!
		$options = [
			['select', 'emoji_selection', [
				'no-emoji' => $txt['emoji_disabled'],
				'open-moji' => $txt['emoji_open'],
				'tw-emoji' => $txt['emoji_twitter'],
				'noto-emoji' => $txt['emoji_google'],
			]],
			''
		];

		// Insert these at the top of the form
		$config_vars = array_merge(array_slice($config_vars, 0, 1), $options, array_slice($config_vars, 1));
	}

	/**
	 * Saves the ACP settings
	 */
	public static function integrate_save_smiley_settings()
	{
		$req = HttpReq::instance();

		if (empty($req->post->emoji_selection))
		{
			$req->post->emoji_selection = 'no-emoji';
			return;
		}

		// An emoji group was selected, unzip them if required
		if (!FileFunctions::instance()->fileExists(BOARDDIR . '/smileys/' . $req->post->emoji_selection . '\1f44d.svg'))
		{
			if (self::unZipEmoji($req))
			{
				self::removeEmoji($req);
				self::copyEmojiToSmiley($req);
			}
		}
	}

	/**
	 * Unzips a selected Emoji set if it has not already been extracted
	 *
	 * @param \ElkArte\HttpReq $req
	 */
	private static function unZipEmoji($req)
	{
		$source = BOARDDIR . '/smileys/' . $req->post->emoji_selection . '/' . $req->post->emoji_selection . '.zip';
		if (FileFunctions::instance()->fileExists($source))
		{
			$destination = BOARDDIR . '/smileys/' . $req->post->emoji_selection;
			$unzip = new UnZip(file_get_contents($source), $destination);
			if ($unzip->read_zip_data())
			{
				return true;
			}
		}

		$req->post->emoji_selection = 'no-emoji';

		return false;
	}

	/**
	 * If changing emoji sets, this simply removes the currently extracted set
	 */
	private static function removeEmoji($req)
	{
		global $modSettings;

		// Saved but did not change ...
		if ($modSettings['emoji_selection'] === $req->post->emoji_selection)
		{
			return true;
		}

		$source = BOARDDIR . '/smileys/' . $modSettings['emoji_selection'];
		$fileFunc = FileFunctions::instance();
		if ($fileFunc->isDir($source))
		{
			$iterator = new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS);
			$files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

			/** @var \FilesystemIterator $file */
			foreach ($files as $file)
			{
				if ($file->getExtension() === 'svg')
				{
					$fileFunc->delete($file->getRealPath());
				}
			}
		}

		return true;
	}

	/**
	 * When the emoji set changes, this will copy certain emoji smileys to the default
	 * smiley set for the site.  This keeps the smileys and emoji in sync
	 *
	 * @param $req
	 * @return bool
	 */
	public static function copyEmojiToSmiley($req)
	{
		global $modSettings;

		// Saved but did not change ...
		if ($modSettings['emoji_selection'] === $req->post->emoji_selection)
		{
			return true;
		}

		$fileFunc = FileFunctions::instance();
		$source = BOARDDIR . '/smileys/' . $req->post->emoji_selection . '/';
		$destination = BOARDDIR . '/smileys/default/';

		// They removed the default set and directory ?
		if (!$fileFunc->isDir($destination))
		{
			$fileFunc->createDirectory($destination);
		}

		// Code points to common/legacy file names so matches can occur across sets
		$fileFunc->chmod($destination);
		$emoji = [
			'1F44D' => 'thumbsup', '1F44E' => 'thumbsdown', '1F480' => 'skull', '1F4A9' => 'poop',
			'1F600' => 'grin', '1F601' => 'cheesy', '1F605' => 'sweat', '1F607' => 'angel',
			'1F608' => 'evil', '1F609' => 'wink', '1F614' => 'sad', '1F616' => 'huh', '1F618' => 'kiss',
			'1F61B' => 'tongue', '1F60D' => 'heart', '1F60E' => 'cool', '1F62C' => 'grimacing',
			'1F62D' => 'cry', '1F633' => 'embarrassed', '1F642' => 'smiley', '1F644' => 'rolleyes',
			'1F6A8' => 'police', '1F910' => 'lipsrsealed', '1F913' => 'nerd', '1F914' => 'undecided',
			'1F915' => 'clumsy', '1F921' => 'clown', '1F923' => 'laugh', '1F92A' => 'zany',
			'1F92B' => 'shh', '1F92C' => 'angry', '1F92E' => 'vomit', '1F92F' => 'shocked',
			'1f973' => 'party',
		];

		// Copy / overwrite each codepoint to the common smiley name in the default smiley directory
		// Default smileys follow the emoji set in use
		foreach ($emoji as $codepoint => $smiley)
		{
			if ($fileFunc->fileExists($source . strtolower($codepoint) . '.svg'))
			{
				copy($source . strtolower($codepoint) . '.svg', $destination . $smiley . '.svg');
			}
		}

		Cache::instance()->remove('parsing_smileys');

		return true;
	}
}
