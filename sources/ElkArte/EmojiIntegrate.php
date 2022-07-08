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

use BBC\ParserWrapper;

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
	 * Called by loadIntegrations() in Hooks.php
	 *
	 * @return array
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['emoji_selection']) || $modSettings['emoji_selection'] === 'no-emoji')
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
	 * Called by loadIntegrationsSettings() in Hooks.php
	 *
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return [
			[
				'integrate_modify_smiley_settings',
				'\\ElkArte\\AdminController\\ManageEmojiModule::integrate_modify_smiley_settings'
			],
			[
				'integrate_save_smiley_settings',
				'\\ElkArte\\AdminController\\ManageEmojiModule::integrate_save_smiley_settings'
			],
		];
	}

	/**
	 * integrate_pre_bbc_parser, called from BBCParser
	 *
	 * What it does:
	 * - Allow addons access before entering the main parse_bbc loop
	 * - searches message for emoji :smile: tags and converts to svg image
	 *
	 * @param string $message
	 * @param array $parse_tags
	 */
	public static function integrate_pre_bbc_parser(&$message, &$parse_tags)
	{
		$req = HttpReq::instance();
		$sa = $req->getQuery('sa', 'trim');

		$canUseSmiley = ParserWrapper::instance()->getSmileysEnabled();

		// If we are doing smileys, then we are doing emoji!
		if ((empty($sa) || $sa !== 'install2') && $message !== false && $canUseSmiley)
		{
			$emoji = Emoji::instance();
			$message = $emoji->emojiNameToImage($message);
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
			loadJavascriptFile(['jquery.atwho.min.js', 'jquery.caret.min.js', 'emoji.plugin.js'], ['defer' => true]);
		}
		else
		{
			loadJavascriptFile(['emoji.plugin.js'], ['defer' => true]);
		}

		// Add the emoji plugin to the editor
		$context['controls']['richedit'][$editor_id]['plugin_addons'][] = 'emoji';
		$context['controls']['richedit'][$editor_id]['plugin_options'][] = '
			emojiOptions: {
				editor_id: \'' . $editor_id . '\',
				emoji_group: \'' . (empty($modSettings['emoji_selection']) ? 'openemoji' : $modSettings['emoji_selection']) . '\'
			}';
	}
}
