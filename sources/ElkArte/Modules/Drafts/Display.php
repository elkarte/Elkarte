<?php

/**
 * Integration system for drafts into Post controller
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

namespace ElkArte\Modules\Drafts;

use ElkArte\EventManager;
use ElkArte\Languages\Txt;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Drafts\Display
 *
 * Enables draft functions for teh Display.controller page (quick reply)
 */
class Display extends AbstractModule
{
	/** @var bool Autosave switch  */
	protected static $_autosave_enabled = false;

	/** @var int Autosave frequency, default to 30 seconds */
	protected static $_autosave_frequency = 30000;

	/**
	 * {@inheritDoc}
	 */
	public static function hooks(EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']))
		{
			self::$_autosave_enabled = !empty($modSettings['drafts_autosave_enabled']);

			if (!empty($modSettings['drafts_autosave_frequency']))
			{
				self::$_autosave_frequency = (int) $modSettings['drafts_autosave_frequency'] * 1000;
			}

			return [
				['prepare_context', [Display::class, 'prepare_context'], ['use_quick_reply', 'editorOptions', 'board']],
			];
		}

		return [];
	}

	/**
	 * Prepares context for draft buttons and listing
	 *
	 * What it does:
	 *
	 * - Sets/checks the ability to save and autosave drafts for JS and button display
	 * - Builds the list of drafts available to load
	 * - Loads necessary Draft javascript functions for full editor or text area
	 *
	 * @param bool $use_quick_reply
	 * @param array $editorOptions
	 * @param int $board
	 */
	public function prepare_context($use_quick_reply, &$editorOptions, $board)
	{
		global $context, $options, $txt;

		// Check if the draft functions are enabled and that they have permission to use them (for quick reply.)
		$context['drafts_save'] = $use_quick_reply && allowedTo('post_draft') && $context['can_reply'];
		$context['drafts_autosave'] = $context['drafts_save'] && self::$_autosave_enabled && allowedTo('post_autosave_draft') && !empty($options['drafts_autosave_enabled']);

		// Enable the drafts functions for the QR area
		if (!empty($context['drafts_save']))
		{
			Txt::load('Drafts');

			if ($context['drafts_autosave'])
			{
				Txt::load('Post');

				$editorOptions['plugin_addons'] = $editorOptions['plugin_addons'] ?? [];
				$editorOptions['plugin_options'] = $editorOptions['plugin_options'] ?? [];

				// @todo remove
				$context['drafts_autosave_frequency'] = self::$_autosave_frequency;

				$editorOptions['plugin_addons'][] = 'draft';
				$editorOptions['plugin_options'][] = '
					draftOptions: {
						sLastNote: \'draft_lastautosave\',
						sSceditorID: \'' . $editorOptions['id'] . '\',
						sType: \'post\',
						iBoard: ' . $board . ',
						iFreq: ' . self::$_autosave_frequency . ',
						sLastID: \'id_draft\',
						sTextareaID: \'' . $editorOptions['id'] . '\',
						id_draft: ' . (empty($context['id_draft']) ? 0 : $context['id_draft']) . '
					}';

				$context['shortcuts_text'] = $txt['shortcuts_drafts'];

				$editorOptions['buttons'] = $editorOptions['buttons'] ?? [];
				$editorOptions['hidden_fields'] = $editorOptions['hidden_fields'] ?? [];

				$editorOptions['buttons'][] = [
					'name' => 'save_draft',
					'value' => $txt['draft_save'],
					'options' => 'onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d"',
				];

				$editorOptions['hidden_fields'][] = [
					'name' => 'id_draft',
					'value' => empty($context['id_draft']) ? 0 : $context['id_draft'],
				];

				loadJavascriptFile('editor/drafts.plugin.js', ['defer' => true]);
			}
		}
	}
}
