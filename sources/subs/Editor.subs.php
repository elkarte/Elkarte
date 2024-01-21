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
use ElkArte\Languages\Txt;

/**
 * Creates a box that can be used for richedit stuff like BBC, Smileys etc.
 *
 * @param array $editorOptions associative array of options => value
 *  Must contain:
 *   - id => unique id for the css
 *   - value => text for the editor or blank
 *   - smiley_container => ID for where the smileys will be placed
 *   - bbc_container => ID for where the toolbar will be placed
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
	global $txt, $options, $context, $settings, $scripturl;

	// Load the Post language file... for the moment at least.
	Txt::load('Post');

	// Every control must have a ID!
	assert(isset($editorOptions['id']));
	assert(isset($editorOptions['value']));

	// Is this the first richedit - if so we need to ensure things are initialised and that we load all needed files
	if (empty($context['controls']['richedit']))
	{
		// Store the name / ID we are creating for template compatibility.
		$context['post_box_name'] = $editorOptions['id'];
		$context['smiley_box_name'] = $editorOptions['smiley_container'] ?? null;
		$context['bbc_box_name'] = $editorOptions['bbc_container'] ?? null;

		// Don't show the smileys if they are off or not wanted.
		$editorOptions['disable_smiley_box'] = !empty($editorOptions['disable_smiley_box'])
			|| $GLOBALS['context']['smiley_set'] === 'none'
			|| !empty($GLOBALS['options']['show_no_smileys'])
			|| empty($editorOptions['smiley_container']);

		// This really has some WYSIWYG stuff.
		theme()->getTemplates()->load('GenericControls');
		loadCSSFile('jquery.sceditor.css');
		if (!empty($context['theme_variant']) && file_exists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/jquery.sceditor.elk' . $context['theme_variant'] . '.css'))
		{
			loadCSSFile($context['theme_variant'] . '/jquery.sceditor.elk' . $context['theme_variant'] . '.css');
		}

		// JS makes the editor go round
		loadJavascriptFile([
			'jquery.sceditor.bbcode.min.js',
			'jquery.sceditor.elkarte.js',
			'post.js',
			'dropAttachments.js'
		]);

		theme()->addJavascriptVar([
			'post_box_name' => $editorOptions['id'],
			'elk_smileys_url' => $context['smiley_path'],
			'elk_emoji_url' => $context['emoji_path'],
			'bbc_quote_from' => $txt['quote_from'],
			'bbc_quote' => $txt['quote'],
			'bbc_search_on' => $txt['search_on'],
			'ila_filename' => $txt['file'] . ' ' . $txt['name']], true
		);

		// Editor language file
		if (!empty($txt['lang_locale']))
		{
			loadJavascriptFile($scripturl . '?action=jslocale;sa=sceditor', array('defer' => true), 'sceditor_language');
		}

		// Our not so concise shortcut line
		$context['shortcuts_text'] = $context['shortcuts_text'] ?? $txt['shortcuts'];
	}

	// Start off the editor...
	$context['controls']['richedit'][$editorOptions['id']] = [
		'id' => $editorOptions['id'],
		'value' => $editorOptions['value'],
		'rich_active' => !empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode']),
		'disable_smiley_box' => $editorOptions['disable_smiley_box'],
		'width' => $editorOptions['width'] ?? '100%',
		'height' => $editorOptions['height'] ?? '250px',
		'form' => $editorOptions['form'] ?? 'postmodify',
		'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
		'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : [],
		'locale' => !empty($txt['lang_locale']) ? $txt['lang_locale'] : 'en_US',
		'plugin_addons' => !empty($editorOptions['plugin_addons']) ? $editorOptions['plugin_addons'] : [],
		'plugin_options' => !empty($editorOptions['plugin_options']) ? $editorOptions['plugin_options'] : [],
		'buttons' => !empty($editorOptions['buttons']) ? $editorOptions['buttons'] : [],
		'hidden_fields' => !empty($editorOptions['hidden_fields']) ? $editorOptions['hidden_fields'] : [],
	];

	// Allow addons an easy way to add plugins, initialization objects, etc to the editor control
	call_integration_hook('integrate_editor_plugins', array($editorOptions['id']));

	// Switch between default images and back... mostly in case you don't have an PersonalMessage template, but do have a Post template.
	$use_defaults = isset($settings['use_default_images'], $settings['default_template']) && $settings['use_default_images'] === 'defaults';
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
	$context['editor_bbc_toolbar'] = buildBBCToolbar($context['bbc_box_name']);
	$context['smileys'] = empty($editorOptions['disable_smiley_box']) ? loadEditorSmileys($context['controls']['richedit'][$editorOptions['id']]) : '';
	$context['editor_smileys_toolbar'] = buildSmileyToolbar(empty($editorOptions['disable_smiley_box']));
	$context['plugins'] = loadEditorPlugins($context['controls']['richedit'][$editorOptions['id']]);
	$context['plugin_options'] = getPluginOptions($context['controls']['richedit'][$editorOptions['id']], $editorOptions['id']);

	// Switch the URLs back... now we're back to whatever the main sub template is (like folder in PersonalMessage.)
	if ($use_defaults)
	{
		list($settings['theme_url'], $settings['images_url'], $settings['theme_dir']) = $temp;
	}

	// Provide some dynamic error checking (no subject, no body, no service!)
	if (!empty($editorOptions['live_errors']))
	{
		Txt::load('Errors');
		theme()->addInlineJavascript('
		
	error_txts[\'no_subject\'] = ' . JavaScriptEscape($txt['error_no_subject']) . ';
	error_txts[\'no_message\'] = ' . JavaScriptEscape($txt['error_no_message']) . ';
		', true);
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

	$plugins[] = 'initialLoad';
	$neededJS[] = 'initialLoad.plugin.js';

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

	// Build our toolbar, taking into account any bbc codes from integration
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
 * - Caches the DB call for 10 mins
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
		$temp = [];
		if (!Cache::instance()->getVar($temp, 'posting_smileys', 600))
		{
			require_once(SUBSDIR . '/Smileys.subs.php');
			$smileys = getEditorSmileys();
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

			Cache::instance()->put('posting_smileys', $smileys, 600);
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

	return empty($context['smileys']) ? $smileys : $context['smileys'];
}

function buildSmileyToolbar($useSmileys)
{
	global $context;

	if (!$useSmileys)
	{
		return ', emoticons: {}';
	}

	$emoticons = ',
		emoticons: {';

	$countLocations = count($context['smileys']);
	foreach ($context['smileys'] as $location => $smileyRows)
	{
		$countLocations--;
		if ($location === 'postform')
		{
			$emoticons .= '
				dropdown: {';
		}

		if ($location === 'popup')
		{
			$emoticons .= '
				popup: {';
		}

		$numRows = count($smileyRows);

		// This is needed because otherwise the editor will remove all the duplicate (empty)
		// keys and leave only 1 additional line
		$emptyPlaceholder = 0;
		foreach ($smileyRows as $smileyRow)
		{
			foreach ($smileyRow['smileys'] as $smiley)
			{
				$emoticons .= '
					' . JavaScriptEscape($smiley['code']) . ': {url: ' . (JavaScriptEscape((isset($smiley['emoji']) ? $context['emoji_path'] : $context['smiley_path']) . $smiley['filename'])) . ', tooltip: ' . JavaScriptEscape($smiley['description']) . '}' . (empty($smiley['isLast']) ? ',' : '');
			}

			if (empty($smileyRow['isLast']) && $numRows !== 1)
			{
				$emoticons .= ",'-" . $emptyPlaceholder++ . "': '',";
			}
		}

		$emoticons .= '
			}' . ($countLocations !== 0 ? ',' : '');
	}

	return $emoticons . '
	}';
}

function buildBBCToolbar($bbcContainer)
{
	global $context;

	if ($bbcContainer === null)
	{
		return ', 
			toolbar: "source"';
	}

	// Show all the editor command buttons
	$toolbar = ',
		toolbar: "';

	// Create the tooltag rows to display the buttons in the editor
	foreach ($context['bbc_toolbar'] as $i => $buttonRow)
	{
		$toolbar .= $buttonRow[0] . '||';
	}

	$toolbar .= '"';

	return $toolbar;
}
