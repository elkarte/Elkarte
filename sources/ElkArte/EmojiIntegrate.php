<?php

/**
 * @package Emoji for ElkArte
 * @author Spuds
 * @copyright (c) 2011-2017 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * @version 1.0.3
 */

namespace ElkArte;

class EmojiIntegrate
{
	/**
	 * Register ILA hooks to the system
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
	 * integrate_pre_bbc_parser, called from BBCParser (ElkArte 1.1+)
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
	 * Adds the necessary setting
	 *
	 * @param mixed[] $config_vars
	 */
	public static function integrate_modify_smiley_settings(&$config_vars)
	{
		global $txt, $context, $scripturl;

		loadlanguage('emoji');

		// All the options, well at least some of them!
		$config_vars[] = '';
		$config_vars[] = array('select', 'emoji_selection', array(
			'noemoji' => $txt['emoji_disabled'],
			'openemoji' => $txt['emoji_open'],
			'twitter' => $txt['emoji_twitter'],
		));
	}


	/**
	 * saves the setting
	 *
	 * @param mixed[] $config_vars
	 */
	public static function integrate_save_smiley_settings()
	{
		$req = HttpReq::instance();

		if (empty($req->post->emoji_selection))
		{
			$req->post->emoji_selection = 'openemoji';
		}
	}
}