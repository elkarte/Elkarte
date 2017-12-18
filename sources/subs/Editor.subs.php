<?php

/**
 * This file contains functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0-dev
 *
 */

/**
 * Retrieves a list of message icons.
 * - Based on the settings, the array will either contain a list of default
 *   message icons or a list of custom message icons retrieved from the database.
 * - The board_id is needed for the custom message icons (which can be set for
 *   each board individually).
 *
 * @param int $board_id
 * @return array
 */
function getMessageIcons($board_id)
{
	global $modSettings, $txt, $settings;

	$db = database();

	if (empty($modSettings['messageIcons_enable']))
	{
		theme()->getTemplates()->loadLanguageFile('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['exclamation_point']),
			array('value' => 'question', 'name' => $txt['question_mark']),
			array('value' => 'lamp', 'name' => $txt['lamp']),
			array('value' => 'smiley', 'name' => $txt['icon_smiley']),
			array('value' => 'angry', 'name' => $txt['icon_angry']),
			array('value' => 'cheesy', 'name' => $txt['icon_cheesy']),
			array('value' => 'grin', 'name' => $txt['icon_grin']),
			array('value' => 'sad', 'name' => $txt['icon_sad']),
			array('value' => 'wink', 'name' => $txt['icon_wink']),
			array('value' => 'poll', 'name' => $txt['icon_poll']),
		);

		foreach ($icons as $k => $dummy)
		{
			$icons[$k]['url'] = $settings['images_url'] . '/post/' . $dummy['value'] . '.png';
			$icons[$k]['is_last'] = false;
		}
	}
	// Otherwise load the icons, and check we give the right image too...
	else
	{
		$icons = array();
		if (!Cache::instance()->getVar($icons, 'posting_icons-' . $board_id, 480))
		{
			$icon_data = $db->fetchQuery('
				SELECT 
					title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})
				ORDER BY icon_order',
				array(
					'board_id' => $board_id,
				)
			);
			$icons = array();
			foreach ($icon_data as $icon)
			{
				$icons[$icon['filename']] = array(
					'value' => $icon['filename'],
					'name' => $icon['title'],
					'url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $icon['filename'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . $icon['filename'] . '.png',
					'is_last' => false,
				);
			}

			Cache::instance()->put('posting_icons-' . $board_id, $icons, 480);
		}
	}

	return array_values($icons);
}

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
 * @uses Post language
 * @uses GenericControls template
 * @throws Elk_Exception
 */
function create_control_richedit($editorOptions)
{
	global $txt, $modSettings, $options, $context, $settings, $scripturl;
	static $bbc_tags;

	$db = database();

	// Load the Post language file... for the moment at least.
	theme()->getTemplates()->loadLanguageFile('Post');

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
			loadCSSFile($context['theme_variant'] . '/jquery.sceditor.elk' . $context['theme_variant'] . '.css');

		// JS makes the editor go round
		loadJavascriptFile(array('jquery.sceditor.bbcode.min.js', 'jquery.sceditor.elkarte.js', 'post.js', 'splittag.plugin.js', 'undo.plugin.min.js', 'dropAttachments.js'));
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
			loadJavascriptFile($scripturl . '?action=jslocale;sa=sceditor', array('defer' => true), 'sceditor_language');

		// Mentions?
		if (!empty($context['mentions_enabled']))
			loadJavascriptFile(array('jquery.atwho.min.js', 'jquery.caret.min.js', 'mentioning.plugin.js'));

		// Our not so concise shortcut line
		if (!isset($context['shortcuts_text']))
			$context['shortcuts_text'] = $txt['shortcuts' . (isBrowser('is_firefox') ? '_firefox' : '')];

		// Spellcheck?
		$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
		if ($context['show_spellchecking'])
		{
			// Some hidden information is needed in order to make spell check work.
			if (!isset($_REQUEST['xml']))
				$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" id="spellstring" name="spellstring" value="" />
			<input type="hidden" id="fulleditor" name="fulleditor" value="" />
		</form>';
			loadJavascriptFile('spellcheck.js', array('defer' => true));
		}
	}

	// Start off the editor...
	$context['controls']['richedit'][$editorOptions['id']] = array(
		'id' => $editorOptions['id'],
		'value' => $editorOptions['value'],
		'rich_active' => !empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode']),
		'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
		'columns' => isset($editorOptions['columns']) ? $editorOptions['columns'] : 60,
		'rows' => isset($editorOptions['rows']) ? $editorOptions['rows'] : 18,
		'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '100%',
		'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '250px',
		'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
		'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
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
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$temp1 = $settings['theme_url'];
		$settings['theme_url'] = $settings['default_theme_url'];

		$temp2 = $settings['images_url'];
		$settings['images_url'] = $settings['default_images_url'];

		$temp3 = $settings['theme_dir'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}

	if (empty($bbc_tags))
	{
		// The below array is used to show a command button in the editor, the execution
		// and display details of any added buttons must be defined in the javascript files
		// see jquery.sceditor.elkarte.js under the $.sceditor.plugins.bbcode.bbcode area
		// for examples of how to use the .set command to add codes.  Include your new
		// JS with addInlineJavascript() or loadJavascriptFile()
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

		// Show the wysiwyg format and toggle buttons?
		$bbc_tags['row2'][] = array('removeformat', 'source');

		// Generate a list of buttons that shouldn't be shown
		$disabled_tags = array();
		if (!empty($modSettings['disabledBBC']))
			$disabled_tags = explode(',', $modSettings['disabledBBC']);

		// Map codes to tags
		$translate_tags_to_code = array('b' => 'bold', 'i' => 'italic', 'u' => 'underline', 's' => 'strike', 'img' => 'image', 'url' => 'link', 'sup' => 'superscript', 'sub' => 'subscript', 'hr' => 'horizontalrule');

		// Remove the toolbar buttons for any bbc tags that have been turned off in the ACP
		foreach ($disabled_tags as $tag)
		{
			// list is special, its prevents two tags
			if ($tag === 'list')
			{
				$context['disabled_tags']['bulletlist'] = true;
				$context['disabled_tags']['orderedlist'] = true;
			}
			elseif (isset($translate_tags_to_code[$tag]))
				$context['disabled_tags'][$translate_tags_to_code[$tag]] = true;

			// Tag is the same as the code, like font, color, size etc
			$context['disabled_tags'][trim($tag)] = true;
		}

		// Build our toolbar, taking in to account any bbc codes from integration
		$context['bbc_toolbar'] = array();
		foreach ($bbc_tags as $row => $tagRow)
		{
			if (!isset($context['bbc_toolbar'][$row]))
				$context['bbc_toolbar'][$row] = array();

			$tagsRow = array();

			// For each row of buttons defined, lets build our tags
			foreach ($tagRow as $tags)
			{
				foreach ($tags as $tag)
				{
					// Just add this code in the existing grouping
					if (!isset($context['disabled_tags'][$tag]))
						$tagsRow[] = $tag;
				}

				// If the row is not empty, and the last added tag is not a space, add a space.
				if (!empty($tagsRow) && $tagsRow[count($tagsRow) - 1] !== 'space')
					$tagsRow[] = 'space';
			}

			// Build that beautiful button row
			if (!empty($tagsRow))
				$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
		}
	}

	// Initialize smiley array... if not loaded before.
	if (empty($context['smileys']) && empty($editorOptions['disable_smiley_box']))
	{
		$context['smileys'] = array(
			'postform' => array(),
			'popup' => array(),
		);

		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($modSettings['smiley_enable']) && $context['smiley_enabled'])
		{
			$context['smileys']['postform'][] = array(
				'smileys' => array(
					array(
						'code' => ':)',
						'filename' => 'smiley.gif',
						'description' => $txt['icon_smiley'],
					),
					array(
						'code' => ';)',
						'filename' => 'wink.gif',
						'description' => $txt['icon_wink'],
					),
					array(
						'code' => ':D',
						'filename' => 'cheesy.gif',
						'description' => $txt['icon_cheesy'],
					),
					array(
						'code' => ';D',
						'filename' => 'grin.gif',
						'description' => $txt['icon_grin']
					),
					array(
						'code' => '>:(',
						'filename' => 'angry.gif',
						'description' => $txt['icon_angry'],
					),
					array(
						'code' => ':))',
						'filename' => 'laugh.gif',
						'description' => $txt['icon_laugh'],
					),
					array(
						'code' => ':(',
						'filename' => 'sad.gif',
						'description' => $txt['icon_sad'],
					),
					array(
						'code' => ':o',
						'filename' => 'shocked.gif',
						'description' => $txt['icon_shocked'],
					),
					array(
						'code' => '8)',
						'filename' => 'cool.gif',
						'description' => $txt['icon_cool'],
					),
					array(
						'code' => '???',
						'filename' => 'huh.gif',
						'description' => $txt['icon_huh'],
					),
					array(
						'code' => '::)',
						'filename' => 'rolleyes.gif',
						'description' => $txt['icon_rolleyes'],
					),
					array(
						'code' => ':P',
						'filename' => 'tongue.gif',
						'description' => $txt['icon_tongue'],
					),
					array(
						'code' => ':-[',
						'filename' => 'embarrassed.gif',
						'description' => $txt['icon_embarrassed'],
					),
					array(
						'code' => ':-X',
						'filename' => 'lipsrsealed.gif',
						'description' => $txt['icon_lips'],
					),
					array(
						'code' => ':-\\',
						'filename' => 'undecided.gif',
						'description' => $txt['icon_undecided'],
					),
					array(
						'code' => ':-*',
						'filename' => 'kiss.gif',
						'description' => $txt['icon_kiss'],
					),
					array(
						'code' => 'O:)',
						'filename' => 'angel.gif',
						'description' => $txt['icon_angel'],
					),
					array(
						'code' => ':\'(',
						'filename' => 'cry.gif',
						'description' => $txt['icon_cry'],
						'isLast' => true,
					),
				),
				'isLast' => true,
			);
		}
		elseif ($context['smiley_enabled'])
		{
			$temp = array();
			if (!Cache::instance()->getVar($temp, 'posting_smileys', 480))
			{
				$db->fetchQueryCallback('
					SELECT code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order',
					array(
					),
					function ($row)
					{
						global $context;

						$row['filename'] = htmlspecialchars($row['filename'], ENT_COMPAT, 'UTF-8');
						$row['description'] = htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8');

						$context['smileys'][empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
					}
				);

				foreach ($context['smileys'] as $section => $smileyRows)
				{
					$last_row = null;
					foreach ($smileyRows as $rowIndex => $smileys)
					{
						$context['smileys'][$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;
						$last_row = $rowIndex;
					}

					if ($last_row !== null)
						$context['smileys'][$section][$last_row]['isLast'] = true;
				}

				Cache::instance()->put('posting_smileys', $context['smileys'], 480);
			}
			else
				$context['smileys'] = $temp;

			// The smiley popup may take advantage of Jquery UI ....
			if (!empty($context['smileys']['popup']))
				$modSettings['jquery_include_ui'] = true;
		}
	}

	// Switch the URLs back... now we're back to whatever the main sub template is.  (like folder in PersonalMessage.)
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $temp1;
		$settings['images_url'] = $temp2;
		$settings['theme_dir'] = $temp3;
	}

	if (!empty($editorOptions['live_errors']))
	{
		theme()->getTemplates()->loadLanguageFile('Errors');

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
