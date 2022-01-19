<?php

/**
 * This file contains functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
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
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;

/**
 * Creates a box that can be used for richedit stuff like BBC, Smileys etc.
 *
 * @param mixed[] $editorOptions associative array of options => value
 *  Must contain:
 *   - id => unique id for the css
 *   - value => text for the editor or blank
 * Optionally:
 *   - height => height of the initial box
 *   - width => width of the box (100%)
 *   - force_rich => force wysiwyg to be enabled
 *   - disable_smiley_box => boolean to turn off the smiley box
 *   - labels => array(
 *       - 'post_button' => $txt['for post button'],
 *     ),
 *   - preview_type => 2 how to act on preview click, see template_control_richedit_buttons
 *
 * @event integrate_editor_plugins
 * @uses GenericControls template
 * @uses Post language
 */
function create_control_richedit($editorOptions)
{
	global $txt, $modSettings, $options, $context, $settings, $scripturl;

	// Load the Post language file... for the moment at least.
	ThemeLoader::loadLanguageFile('Post');

	// Every control must have a ID!
	assert(isset($editorOptions['id']));
	assert(isset($editorOptions['value']));

	// Is this the first richedit - if so we need to ensure things are initialised and that we load all of the needed files
	if (empty($context['controls']['richedit']))
	{
		// Store the name / ID we are creating for template compatibility.
		$context['post_box_name'] = $editorOptions['id'];

		// Some general stuff.
		$settings['smileys_url'] = $context['user']['smiley_path'];

		// This really has some WYSIWYG stuff.
		theme()->getTemplates()->load('GenericControls');
		loadCSSFile('jquery.sceditor.css');
		if (!empty($context['theme_variant']) && file_exists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/jquery.sceditor.elk' . $context['theme_variant'] . '.css'))
		{
			loadCSSFile($context['theme_variant'] . '/jquery.sceditor.elk' . $context['theme_variant'] . '.css');
		}

		// JS makes the editor go round
		loadJavascriptFile(array('jquery.sceditor.bbcode.min.js', 'jquery.sceditor.elkarte.js', 'post.js', 'dropAttachments.js'));
		theme()->addJavascriptVar(array(
			'post_box_name' => $editorOptions['id'],
			'elk_smileys_url' => $settings['smileys_url'],
			'bbc_quote_from' => $txt['quote_from'],
			'bbc_quote' => $txt['quote'],
			'bbc_search_on' => $txt['search_on'],
			'ila_filename' => $txt['file'] . ' ' . $txt['name']), true
		);

		// Editor language file
		if (!empty($txt['lang_locale']))
		{
			loadJavascriptFile($scripturl . '?action=jslocale;sa=sceditor', array('defer' => true), 'sceditor_language');
		}

		// Our not so concise shortcut line
		$context['shortcuts_text'] = $context['shortcuts_text'] ?? $txt['shortcuts'];

		// Spellcheck?
		$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
		if ($context['show_spellchecking'])
		{
			// Some hidden information is needed in order to make spell check work.
			if (!isset($_REQUEST['xml']))
			{
				$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" id="spellstring" name="spellstring" value="" />
			<input type="hidden" id="fulleditor" name="fulleditor" value="" />
		</form>';
			}
			loadJavascriptFile('spellcheck.js', array('defer' => true));
		}
	}

	// Start off the editor...
	$context['controls']['richedit'][$editorOptions['id']] = array(
		'id' => $editorOptions['id'],
		'value' => $editorOptions['value'],
		'rich_active' => !empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode']),
		'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
		'width' => $editorOptions['width'] ?? '100%',
		'height' => $editorOptions['height'] ?? '250px',
		'form' => $editorOptions['form'] ?? 'postmodify',
		'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
		'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
		'locale' => !empty($txt['lang_locale']) ? $txt['lang_locale'] : 'en_US',
		'plugin_addons' => !empty($editorOptions['plugin_addons']) ? $editorOptions['plugin_addons'] : array(),
		'plugin_options' => !empty($editorOptions['plugin_options']) ? $editorOptions['plugin_options'] : array(),
		'buttons' => !empty($editorOptions['buttons']) ? $editorOptions['buttons'] : array(),
		'hidden_fields' => !empty($editorOptions['hidden_fields']) ? $editorOptions['hidden_fields'] : array(),
	);

	// Allow addons an easy way to add plugins, initialization objects, etc to the editor control
	call_integration_hook('integrate_editor_plugins', array($editorOptions['id']));

	// Switch between default images and back... mostly in case you don't have an PersonalMessage template, but do have a Post template.
	$use_defaults = isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']);
	if ($use_defaults)
	{
		$temp = [];
		$temp[] = $settings['theme_url'];
		$temp[] = $settings['images_url'];
		$temp[] = $settings['theme_dir'];

		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}

	// Setup the toolbar, smileys, plugins
	$context['bbc_toolbar'] = loadEditorToolbar();
	$context['smileys'] = loadEditorSmileys($context['controls']['richedit'][$editorOptions['id']]);
	$context['plugins'] = loadEditorPlugins($context['controls']['richedit'][$editorOptions['id']]);
	$context['plugin_options'] = getPluginOptions($context['controls']['richedit'][$editorOptions['id']], $editorOptions['id']);

	// Switch the URLs back... now we're back to whatever the main sub template is.  (like folder in PersonalMessage.)
	if ($use_defaults)
	{
		list($settings['theme_url'], $settings['images_url'], $settings['theme_dir']) = $temp;
	}

	// Provide some dynamic error checking (no subject, no body, no service!)
	if (!empty($editorOptions['live_errors']))
	{
		ThemeLoader::loadLanguageFile('Errors');
		theme()->addInlineJavascript('
	error_txts[\'no_subject\'] = ' . JavaScriptEscape($txt['error_no_subject']) . ';
	error_txts[\'no_message\'] = ' . JavaScriptEscape($txt['error_no_message']) . ';

	var subject_err = new errorbox_handler({
		self: \'subject_err\',
		error_box_id: \'post_error\',
		error_checks: [{
			code: \'no_subject\',
			efunction: function(box_value) {
				if (box_value.length === 0)
					return true;
				else
					return false;
			}
		}],
		check_id: "post_subject"
	});

	var body_err_' . $editorOptions['id'] . ' = new errorbox_handler({
		self: \'body_err_' . $editorOptions['id'] . '\',
		error_box_id: \'post_error\',
		error_checks: [{
			code: \'no_message\',
			efunction: function(box_value) {
				if (box_value.length === 0)
					return true;
				else
					return false;
			}
		}],
		editor_id: \'' . $editorOptions['id'] . '\',
		editor: ' . JavaScriptEscape('
		(function () {
			return $editor_data[\'' . $editorOptions['id'] . '\'].val();
		});') . '
	});', true);
	}
}

/**
 * Defines plugins to load and loads the JS needed by core plugins.
 * Will merge in plugins added by addons.  Its the editor that will load
 * and call the plugins, this just defines what to load
 *
 * @param array $editor_context
 * @return array
 */
function loadEditorPlugins($editor_context)
{
	global $modSettings;

	$plugins = [];
	$neededJS = [];

	if (!empty($modSettings['enableSplitTag']))
	{
		$plugins[] = 'splittag';
		$neededJS[] = 'splittag.plugin.js';
	}

	if (!empty($modSettings['enableUndoRedo']))
	{
		$plugins[] = 'undo';
		$neededJS[] = 'undo.plugin.min.js';
	}

	if (!empty($modSettings['mentions_enabled']))
	{
		$plugins[] = 'mention';
		$neededJS = array_merge($neededJS, ['jquery.atwho.min.js', 'jquery.caret.min.js', 'mentioning.plugin.js']);
	}

	if (!empty($neededJS))
	{
		loadJavascriptFile($neededJS, array('defer' => true));
	}

	// Merge with other plugins added by core features or addons
	if (!empty($editor_context['plugin_addons']))
	{
		if (!is_array($editor_context['plugin_addons']))
		{
			$editor_context['plugin_addons'] = array($editor_context['plugin_addons']);
		}

		$plugins = array_filter(array_merge($plugins, $editor_context['plugin_addons']));
	}

	return $plugins;
}

/**
 * Loads any built in plugin options and merges in any addon defined
 * ones.
 *
 * @param array $editor_context
 * @param string $editor_id
 * @return array
 */
function getPluginOptions($editor_context, $editor_id)
{
	global $modSettings;

	$plugin_options = [];

	if (!empty($modSettings['mentions_enabled']))
	{
		$plugin_options[] = '
			mentionOptions: {
				editor_id: \'' . $editor_id . '\',
				cache: {
					mentions: [],
					queries: [],
					names: []
				}
			}';
	}

	// Allow addons to insert additional editor objects
	if (!empty($editor_context['plugin_options']) && is_array($editor_context['plugin_options']))
	{
		$plugin_options = array_merge($plugin_options, $editor_context['plugin_options']);
	}

	return $plugin_options;
}

/**
 * Loads the editor toolbar with just the enabled commands
 *
 * @return array
 */
function loadEditorToolbar()
{
	$bbc_tags = loadToolbarDefaults();
	$disabledToolbar = getDisabledBBC();

	// Build our toolbar, taking in to account any bbc codes from integration
	$bbcToolbar = [];
	foreach ($bbc_tags as $row => $tagRow)
	{
		$bbcToolbar[$row] = $bbcToolbar[$row] ?? [];
		$tagsRow = [];

		// For each row of buttons defined, lets build our tags
		foreach ($tagRow as $tags)
		{
			foreach ($tags as $tag)
			{
				// Just add this code in the existing grouping
				if (!isset($disabledToolbar[$tag]))
				{
					$tagsRow[] = $tag;
				}
			}

			// If the row is not empty, and the last added tag is not a space, add a space.
			if (!empty($tagsRow) && $tagsRow[count($tagsRow) - 1] !== 'space')
			{
				$tagsRow[] = 'space';
			}
		}

		// Build that beautiful button row
		if (!empty($tagsRow))
		{
			$bbcToolbar[$row][] = implode(',', $tagsRow);
		}
	}

	return $bbcToolbar;
}

/**
 * Loads disabled BBC tags from the DB as defined in the ACP.  It
 * will then translate BBC to editor commands (b => bold) such that
 * disabled BBC will also disable the associated editor command button.
 *
 * @return array
 */
function getDisabledBBC()
{
	global $modSettings;

	// Generate a list of buttons that shouldn't be shown
	$disabled_tags = [];
	$disabledToolbar = [];

	if (!empty($modSettings['disabledBBC']))
	{
		$disabled_tags = explode(',', $modSettings['disabledBBC']);
	}

	// Map bbc codes to editor toolbar tags
	$translate_tags_to_code = ['b' => 'bold', 'i' => 'italic', 'u' => 'underline', 's' => 'strike',
							   'img' => 'image', 'url' => 'link', 'sup' => 'superscript', 'sub' => 'subscript', 'hr' => 'horizontalrule'];

	// Remove the toolbar buttons for any bbc tags that have been turned off in the ACP
	foreach ($disabled_tags as $tag)
	{
		// list is special, its prevents two tags
		if ($tag === 'list')
		{
			$disabledToolbar['bulletlist'] = true;
			$disabledToolbar['orderedlist'] = true;
		}
		elseif (isset($translate_tags_to_code[$tag]))
		{
			$disabledToolbar[$translate_tags_to_code[$tag]] = true;
		}

		// Tag is the same as the code, like font, color, size etc
		$disabledToolbar[trim($tag)] = true;
	}

	return $disabledToolbar;
}

/**
 * Loads the toolbar default buttons which defines what editor buttons might show
 *
 * @event integrate_bbc_buttons
 * @return array|mixed
 */
function loadToolbarDefaults()
{
	$bbc_tags = [];

	// The below array is used to show a command button in the editor, the execution
	// and display details of any added buttons must be defined in the javascript files
	// see jquery.sceditor.elkarte.js under the sceditor.formats.bbcode area
	// for examples of how to use the .set command to add codes.  Include your new
	// JS with addInlineJavascript() or loadJavascriptFile() in your addon
	$bbc_tags['row1'] = array(
		array('bold', 'italic', 'underline', 'strike', 'superscript', 'subscript'),
		array('left', 'center', 'right', 'pre', 'tt'),
		array('font', 'size', 'color'),
	);

	$bbc_tags['row2'] = array(
		array('quote', 'code', 'table'),
		array('bulletlist', 'orderedlist', 'horizontalrule'),
		array('spoiler', 'footnote', 'splittag'),
		array('image', 'link', 'email'),
		array('undo', 'redo'),
	);

	// Allow mods to add BBC buttons to the toolbar, actions are defined in the JS
	call_integration_hook('integrate_bbc_buttons', array(&$bbc_tags));

	// Show the format and toggle buttons
	$bbc_tags['row2'][] = array('removeformat', 'source');

	return $bbc_tags;
}

/**
 * Loads the smileys for use in the required locations (postform or popup)
 *
 * What it does:
 * - Will load the default smileys or custom smileys as defined in ACP
 * - Caches the DB call for 8 mins
 * - Sorts smileys to proper array positions removing hidden ones
 *
 * @return array|array[]|mixed
 */
function loadEditorSmileys($editorOptions)
{
	global $context, $modSettings;

	$smileys = [
		'postform' => [],
		'popup' => [],
	];

	// Initialize smiley array... if not loaded before.
	if (empty($context['smileys']) && empty($editorOptions['disable_smiley_box']))
	{
		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($modSettings['smiley_enable']) && User::$info->smiley_set !== 'none')
		{
			$smileys['postform'][] = loadDefaultSmileys();

			return $smileys;
		}

		if (User::$info->smiley_set !== 'none')
		{
			$temp = [];
			if (!Cache::instance()->getVar($temp, 'posting_smileys', 480))
			{
				$db = database();
				$db->fetchQuery('
					SELECT 
						code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order',
					array()
				)->fetch_callback(
					function ($row) use (&$smileys) {
						$row['filename'] = htmlspecialchars($row['filename'], ENT_COMPAT, 'UTF-8');
						$row['description'] = htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8');

						$smileys[empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
					}
				);

				foreach ($smileys as $section => $smileyRows)
				{
					$last_row = null;
					foreach ($smileyRows as $rowIndex => $smileRow)
					{
						$smileys[$section][$rowIndex]['smileys'][count($smileRow['smileys']) - 1]['isLast'] = true;
						$last_row = $rowIndex;
					}

					if ($last_row !== null)
					{
						$smileys[$section][$last_row]['isLast'] = true;
					}
				}

				Cache::instance()->put('posting_smileys', $smileys, 480);
			}
			else
			{
				$smileys = $temp;
			}

			// The smiley popup may take advantage of Jquery UI ....
			if (!empty($smileys['popup']))
			{
				$modSettings['jquery_include_ui'] = true;
			}

			return $smileys;
		}
	}

	return empty($context['smileys']) ? $smileys : $context['smileys'];
}

/**
 * Returns an array of default smileys that are enabled w/o any db
 * requirements
 *
 * @return array
 */
function loadDefaultSmileys()
{
	global $txt;

	return ['smileys' => [
		[
			'code' => ':)',
			'filename' => 'smiley.gif',
			'description' => $txt['icon_smiley'],
		],
		[
			'code' => ';)',
			'filename' => 'wink.gif',
			'description' => $txt['icon_wink'],
		],
		[
			'code' => ':D',
			'filename' => 'cheesy.gif',
			'description' => $txt['icon_cheesy'],
		],
		[
			'code' => ';D',
			'filename' => 'grin.gif',
			'description' => $txt['icon_grin']
		],
		[
			'code' => '>:(',
			'filename' => 'angry.gif',
			'description' => $txt['icon_angry'],
		],
		[
			'code' => ':))',
			'filename' => 'laugh.gif',
			'description' => $txt['icon_laugh'],
		],
		[
			'code' => ':(',
			'filename' => 'sad.gif',
			'description' => $txt['icon_sad'],
		],
		[
			'code' => ':o',
			'filename' => 'shocked.gif',
			'description' => $txt['icon_shocked'],
		],
		[
			'code' => '8)',
			'filename' => 'cool.gif',
			'description' => $txt['icon_cool'],
		],
		[
			'code' => '???',
			'filename' => 'huh.gif',
			'description' => $txt['icon_huh'],
		],
		[
			'code' => '::)',
			'filename' => 'rolleyes.gif',
			'description' => $txt['icon_rolleyes'],
		],
		[
			'code' => ':P',
			'filename' => 'tongue.gif',
			'description' => $txt['icon_tongue'],
		],
		[
			'code' => ':-[',
			'filename' => 'embarrassed.gif',
			'description' => $txt['icon_embarrassed'],
		],
		[
			'code' => ':-X',
			'filename' => 'lipsrsealed.gif',
			'description' => $txt['icon_lips'],
		],
		[
			'code' => ':-\\',
			'filename' => 'undecided.gif',
			'description' => $txt['icon_undecided'],
		],
		[
			'code' => ':-*',
			'filename' => 'kiss.gif',
			'description' => $txt['icon_kiss'],
		],
		[
			'code' => 'O:)',
			'filename' => 'angel.gif',
			'description' => $txt['icon_angel'],
		],
		[
			'code' => ':\'(',
			'filename' => 'cry.gif',
			'description' => $txt['icon_cry'],
			'isLast' => true,
		],
	],
			'isLast' => true,
	];
}