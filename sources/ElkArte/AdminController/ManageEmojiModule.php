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
use ElkArte\HttpReq;
use ElkArte\Themes\ThemeLoader;

/**
 * Emoji administration controller.
 * This class allows to modify emoji settings for the forum.
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

		ThemeLoader::loadLanguageFile('emoji');

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
		}
	}
}