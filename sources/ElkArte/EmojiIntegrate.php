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
	 * Called by loadIntegrations() in Hooks.php
	 *
	 * @return array
	 */
	public static function register()
	{
		// $hook, $function, $file
		return [
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

		if (empty($context['emoji_enabled']))
		{
			return;
		}

		// Need caret and atwho to be available
		if (empty($context['mentions_enabled']))
		{
			loadCSSFile('jquery.atwho.css');
			loadJavascriptFile(['editor/jquery.atwho.min.js', 'editor/jquery.caret.min.js', 'editor/emoji.plugin.js'], ['defer' => true]);
		}
		else
		{
			loadJavascriptFile(['editor/emoji.plugin.js'], ['defer' => true]);
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
