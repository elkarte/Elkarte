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
	 * @param mixed[] $config_vars
	 */
	public static function integrate_modify_smiley_settings(&$config_vars)
	{
		global $txt;

		Txt::load('emoji');

		// All the options, well at least some of them!
		$config_vars[] = '';
		$config_vars[] = array('select', 'emoji_selection', array(
			'noemoji' => $txt['emoji_disabled'],
			'emojitwo' => $txt['emoji_open'],
			'twemoji' => $txt['emoji_twitter'],
			'noto-emoji' => $txt['emoji_google'],
		));
	}

	/**
	 * Saves the ACP settings
	 */
	public static function integrate_save_smiley_settings()
	{
		$req = HttpReq::instance();

		if (empty($req->post->emoji_selection))
		{
			$req->post->emoji_selection = 'noemoji';
			return;
		}

		// An emoji group was selected, unzip them if required
		if (!FileFunctions::instance()->fileExists(BOARDDIR . '/smileys/' . $req->post->emoji_selection . '\1f44d.svg'))
		{
			if (self::unZipEmoji($req))
			{
				self::removeEmoji($req);
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

		$req->post->emoji_selection = 'noemoji';
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
		if (FileFunctions::instance()->isDir($source))
		{
			$iterator = new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS);
			$files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

			/** @var \FilesystemIterator $file */
			foreach ($files as $file)
			{
				if ($file->getExtension() === 'svg')
				{
					unlink($file->getRealPath());
				}
			}
		}
	}
}