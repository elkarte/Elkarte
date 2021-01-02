<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class EmojiIntegrate, adds hooks to the system when called
 *
 * @package ElkArte
 */
class EmojiIntegrate
{
	/**
	 * Register Emoji hooks to the system
	 *
	 * @return array
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['emoji_selection']) || $modSettings['emoji_selection'] === 'noemoji')
		{
			return [];
		}

		// $hook, $function, $file
		return [
			[
				'integrate_pre_bbc_parser',
				'\\ElkArte\\EmojiIntegrate::integrate_pre_bbc_parser'
			],
			[
				'integrate_editor_plugins',
				'\\ElkArte\\EmojiIntegrate::integrate_editor_plugins'
			],
		];
	}

	/**
	 * Register ACP config hooks for setting values
	 *
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return [
			[
				'integrate_modify_smiley_settings',
				'\\ElkArte\\EmojiIntegrate::integrate_modify_smiley_settings'
			],
			[
				'integrate_save_smiley_settings',
				'\\ElkArte\\EmojiIntegrate::integrate_save_smiley_settings'
			],
		];
	}

	/**
	 * integrate_pre_bbc_parser, called from BBCParser
	 *
	 * - Allow addons access before entering the main parse_bbc loop
	 * - searches message for emoji :smile: tags and converts to image
	 *
	 * @param string $message
	 * @param mixed[] $parse_tags
	 */
	public static function integrate_pre_bbc_parser(&$message, &$parse_tags)
	{
		// If we are doing smileys, then we are doing emoji!
		if ((empty($_REQUEST['sa']) || $_REQUEST['sa'] !== 'install2') && $message !== false)
		{
			$message = Emoji::emojiNameToImage($message);
		}
	}

	/**
	 * integrate_editor_plugins called from Editor.subs.php
	 *
	 * What it does:
	 * - Used to load in additional JS for the editor
	 * - Add plugins to the editor
	 * - Add initialization objects to the editor
	 *
	 * @param string $editor_id
	 */
	public static function integrate_editor_plugins($editor_id)
	{
		global $context, $modSettings;

		// Need caret and atwho to be available
		if (empty($context['mentions_enabled']))
		{
			loadCSSFile('jquery.atwho.css');
			loadJavascriptFile(array('jquery.atwho.min.js', 'jquery.caret.min.js', 'Emoji.plugin.js'));
		}
		else
		{
			loadJavascriptFile(array('Emoji.plugin.js'));
		}

		// Add the emoji plugin to the editor
		$context['controls']['richedit'][$editor_id]['plugin_addons'][] = 'emoji';
		$context['controls']['richedit'][$editor_id]['plugin_options'][] = '
			emojiOptions: {
				editor_id: \'' . $editor_id . '\',
				emoji_group: \'' . (empty($modSettings['emoji_selection']) ? 'openemoji' : $modSettings['emoji_selection']) . '\'
			}';
	}

	/**
	 * Adds the necessary settings to the smiley area of the ACP
	 *
	 * @param mixed[] $config_vars
	 */
	public static function integrate_modify_smiley_settings(&$config_vars)
	{
		global $txt;

		theme()->getTemplates()->loadLanguageFile('emoji');

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
	 *
	 * @param mixed[] $config_vars
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