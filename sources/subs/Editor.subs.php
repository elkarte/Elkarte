<?php

/**
 * This file contains functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

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
		loadLanguage('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['excamation_point']),
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
		if (($temp = cache_get_data('posting_icons-' . $board_id, 480)) == null)
		{
			$request = $db->query('', '
				SELECT title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})
				ORDER BY icon_order',
				array(
					'board_id' => $board_id,
				)
			);
			$icon_data = array();
			while ($row = $db->fetch_assoc($request))
				$icon_data[] = $row;
			$db->free_result($request);

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

			cache_put_data('posting_icons-' . $board_id, $icons, 480);
		}
		else
			$icons = $temp;
	}

	return array_values($icons);
}

/**
 * Creates a box that can be used for richedit stuff like BBC, Smileys etc.
 * @param mixed[] $editorOptions associative array of options => value
 *	must contain
 *		id => unique id for the css
 *		value => text for the editor or blank
 * Optionaly
 *		height => height of the intial box
 * 		width => width of the box (100%)
 *		force_rich => force wysiwyg to be enabled
 *		disable_smiley_box => boolean to turn off the smiley box
 * 		labels => array(
 * 			'post_button' => $txt['for post button'],
 * 		),
 * 		preview_type => 2 how to act on preview click, see template_control_richedit_buttons
 */
function create_control_richedit($editorOptions)
{
	global $txt, $modSettings, $options, $context, $settings, $user_info, $scripturl;

	$db = database();

	// Load the Post language file... for the moment at least.
	loadLanguage('Post');

	if (!empty($context['drafts_save']) || !empty($context['drafts_pm_save']))
		loadLanguage('Drafts');

	// Every control must have a ID!
	assert(isset($editorOptions['id']));
	assert(isset($editorOptions['value']));

	// Is this the first richedit - if so we need to ensure things are initialised and that we load all of the needed files
	if (empty($context['controls']['richedit']))
	{
		// Store the name / ID we are creating for template compatibility.
		$context['post_box_name'] = $editorOptions['id'];

		// Some general stuff.
		$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];
		if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			$context['drafts_autosave_frequency'] = empty($modSettings['drafts_autosave_frequency']) ? 30000 : $modSettings['drafts_autosave_frequency'] * 1000;

		// This really has some WYSIWYG stuff.
		loadTemplate('GenericControls', 'jquery.sceditor');

		// JS makes the editor go round
		loadJavascriptFile(array('jquery.sceditor.js', 'jquery.sceditor.bbcode.js', 'jquery.sceditor.elkarte.js', 'post.js', 'splittag.plugin.js'));
		addJavascriptVar(array(
			'post_box_name' => '"' . $editorOptions['id'] . '"',
			'elk_smileys_url' => '"' . $settings['smileys_url'] . '"',
			'bbc_quote_from' => '"' . addcslashes($txt['quote_from'], "'") . '"',
			'bbc_quote' => '"' . addcslashes($txt['quote'], "'") . '"',
			'bbc_search_on' => '"' . addcslashes($txt['search_on'], "'") . '"')
		);

		// editor language file
		if (!empty($txt['lang_locale']) && $txt['lang_locale'] != 'en_US')
			loadJavascriptFile($scripturl . '?action=jslocale;sa=sceditor', array(), 'sceditor_language');

		// Drafts?
		if ((!empty($context['drafts_save']) || !empty($context['drafts_pm_save'])) && !empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			loadJavascriptFile('drafts.plugin.js');

		// Mentions?
		if (!empty($context['mentions_enabled']))
			loadJavascriptFile(array('jquery.atwho.js', 'jquery.caret.js', 'mentioning.plugin.js'));

		// Our not so concise shortcut line
		$context['shortcuts_text'] = $txt['shortcuts' . (!empty($context['drafts_save']) ? '_drafts' : '') . (isBrowser('is_firefox') ? '_firefox' : '')];

		// Spellcheck?
		$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
		if ($context['show_spellchecking'])
		{
			// Some hidden information is needed in order to make spell check work.
			if (!isset($_REQUEST['xml']))
				$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" name="spellstring" value="" />
			<input type="hidden" name="fulleditor" value="" />
		</form>';
			loadJavascriptFile('spellcheck.js', array('defer' => true));
		}
	}

	// Start off the editor...
	$context['controls']['richedit'][$editorOptions['id']] = array(
		'id' => $editorOptions['id'],
		'value' => $editorOptions['value'],
		'rich_value' => $editorOptions['value'], // 2.0 editor compatibility
		'rich_active' => empty($modSettings['disable_wysiwyg']) && (!empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode'])),
		'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
		'columns' => isset($editorOptions['columns']) ? $editorOptions['columns'] : 60,
		'rows' => isset($editorOptions['rows']) ? $editorOptions['rows'] : 18,
		'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '100%',
		'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '250px',
		'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
		'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
		'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
		'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
		'locale' => !empty($txt['lang_locale']) && substr($txt['lang_locale'], 0, 5) != 'en_US' ? $txt['lang_locale'] : '',
	);

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

	if (empty($context['bbc_tags']))
	{
		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		/*
			array(
				'image' => 'bold',
				'code' => 'b',
				'before' => '[b]',
				'after' => '[/b]',
				'description' => $txt['bold'],
			),
		*/
		$context['bbc_tags'] = array();
		$context['bbc_tags'][] = array(
			array(
				'code' => 'bold',
				'description' => $txt['bold'],
			),
			array(
				'code' => 'italic',
				'description' => $txt['italic'],
			),
			array(
				'code' => 'underline',
				'description' => $txt['underline']
			),
			array(
				'code' => 'strike',
				'description' => $txt['strike']
			),
			array(
				'code' => 'superscript',
				'description' => $txt['superscript']
			),
			array(
				'code' => 'subscript',
				'description' => $txt['subscript']
			),
			array(),
			array(
				'code' => 'left',
				'description' => $txt['left_align']
			),
			array(
				'code' => 'center',
				'description' => $txt['center']
			),
			array(
				'code' => 'right',
				'description' => $txt['right_align']
			),
			array(
				'code' => 'pre',
				'description' => $txt['preformatted']
			),
			array(
				'code' => 'tt',
				'description' => $txt['teletype']
			),
		);
		$context['bbc_tags'][] = array(
			array(
				'code' => 'bulletlist',
				'description' => $txt['list_unordered']
			),
			array(
				'code' => 'orderedlist',
				'description' => $txt['list_ordered']
			),
			array(
				'code' => 'horizontalrule',
				'description' => $txt['horizontal_rule']
			),
			array(),
			array(
				'code' => 'table',
				'description' => $txt['table']
			),
			array(),
			array(
				'code' => 'code',
				'description' => $txt['bbc_code']
			),
			array(
				'code' => 'quote',
				'description' => $txt['bbc_quote']
			),
			array(
				'code' => 'spoiler',
				'description' => $txt['bbc_spoiler']
			),
			array(
				'code' => 'footnote',
				'description' => $txt['bbc_footnote']
			),
			array(),
			array(
				'code' => 'image',
				'description' => $txt['image']
			),
			array(
				'code' => 'link',
				'description' => $txt['hyperlink']
			),
			array(
				'code' => 'email',
				'description' => $txt['insert_email']
			),
		);

		// Allow mods to modify BBC buttons.
		call_integration_hook('integrate_bbc_buttons');

		// Show the toggle?
		if (empty($modSettings['disable_wysiwyg']))
		{
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array();
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'code' => 'unformat',
				'description' => $txt['unformat_text'],
			);
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'code' => 'toggle',
				'description' => $txt['toggle_view'],
			);
		}

		// Generate a list of buttons that shouldn't be shown - this should be the fastest way to do this.
		$disabled_tags = array();
		if (!empty($modSettings['disabledBBC']))
			$disabled_tags = explode(',', $modSettings['disabledBBC']);

		foreach ($disabled_tags as $tag)
		{
			if ($tag === 'list')
			{
				$context['disabled_tags']['bulletlist'] = true;
				$context['disabled_tags']['orderedlist'] = true;
			}
			elseif ($tag === 'b')
				$context['disabled_tags']['bold'] = true;
			elseif ($tag === 'i')
				$context['disabled_tags']['italic'] = true;
			elseif ($tag === 'u')
				$context['disabled_tags']['underline'] = true;
			elseif ($tag === 's')
				$context['disabled_tags']['strike'] = true;
			elseif ($tag === 'img')
				$context['disabled_tags']['image'] = true;
			elseif ($tag === 'url')
				$context['disabled_tags']['link'] = true;
			elseif ($tag === 'sup')
				$context['disabled_tags']['superscript'] = true;
			elseif ($tag === 'sub')
				$context['disabled_tags']['subscript'] = true;
			elseif ($tag === 'hr')
				$context['disabled_tags']['horizontalrule'] = true;

			$context['disabled_tags'][trim($tag)] = true;
		}

		$bbcodes_styles = '';
		$context['bbcodes_handlers'] = '';
		$context['bbc_toolbar'] = array();

		// Build our toolbar, taking in to account any custom bbc codes from integration
		foreach ($context['bbc_tags'] as $row => $tagRow)
		{
			if (!isset($context['bbc_toolbar'][$row]))
				$context['bbc_toolbar'][$row] = array();

			$tagsRow = array();
			foreach ($tagRow as $tag)
			{
				if (!empty($tag))
				{
					if (empty($context['disabled_tags'][$tag['code']]))
					{
						$tagsRow[] = $tag['code'];

						// Special Image
						if (isset($tag['image']))
							$bbcodes_styles .= '
		.sceditor-button-' . $tag['code'] . ' div {
			background: url(\'' . $settings['default_theme_url'] . '/images/bbc/' . $tag['image'] . '.png\');
		}';

						// Special commands
						if (isset($tag['before']))
						{
							$context['bbcodes_handlers'] = '
				$.sceditor.command.set(
					' . javaScriptEscape($tag['code']) . ', {
					exec: function () {
						this.wysiwygEditorInsertHtml(' . javaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . javaScriptEscape($tag['after']) : '') . ');
					},
					tooltip:' . javaScriptEscape($tag['description']) . ',
					txtExec: [' . javaScriptEscape($tag['before']) . (isset($tag['after']) ? ', ' . javaScriptEscape($tag['after']) : '') . '],
					}
				);';
						}
					}
				}
				else
				{
					$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
					$tagsRow = array();
				}
			}

			if ($row === 0)
			{
				$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
				$tagsRow = array();

				if (!isset($context['disabled_tags']['font']))
					$tagsRow[] = 'font';

				if (!isset($context['disabled_tags']['size']))
					$tagsRow[] = 'size';

				if (!isset($context['disabled_tags']['color']))
					$tagsRow[] = 'color';
			}
			elseif ($row === 1 && empty($modSettings['disable_wysiwyg']))
			{
				$tmp = array();
				$tagsRow[] = 'removeformat';
				$tagsRow[] = 'source';
				if (!empty($tmp))
					$tagsRow[] = '|' . implode(',', $tmp);
			}

			if (!empty($tagsRow))
				$context['bbc_toolbar'][$row][] = implode(',', $tagsRow);
		}

		if (!empty($bbcodes_styles))
			$context['html_headers'] .= '
	<style>' . $bbcodes_styles . '
	</style>';
	}

	// Initialize smiley array... if not loaded before.
	if (empty($context['smileys']) && empty($editorOptions['disable_smiley_box']))
	{
		$context['smileys'] = array(
			'postform' => array(),
			'popup' => array(),
		);

		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($modSettings['smiley_enable']) && $user_info['smiley_set'] != 'none')
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
		elseif ($user_info['smiley_set'] != 'none')
		{
			if (($temp = cache_get_data('posting_smileys', 480)) == null)
			{
				$request = $db->query('', '
					SELECT code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order',
					array(
					)
				);
				while ($row = $db->fetch_assoc($request))
				{
					$row['filename'] = htmlspecialchars($row['filename'], ENT_COMPAT, 'UTF-8');
					$row['description'] = htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8');

					$context['smileys'][empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
				}
				$db->free_result($request);

				foreach ($context['smileys'] as $section => $smileyRows)
				{
					foreach ($smileyRows as $rowIndex => $smileys)
						$context['smileys'][$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;

					if (!empty($smileyRows))
						$context['smileys'][$section][count($smileyRows) - 1]['isLast'] = true;
				}

				cache_put_data('posting_smileys', $context['smileys'], 480);
			}
			else
				$context['smileys'] = $temp;

			// The smiley popup may take advantage of Jquery UI ....
			if (!empty($context['smileys']['popup']))
				$modSettings['jquery_include_ui'] = true;

		}
	}

	// Set a flag so the sub template knows what to do...
	$context['show_bbc'] = !empty($modSettings['enableBBC']) && !empty($settings['show_bbc']);

	// Switch the URLs back... now we're back to whatever the main sub template is.  (like folder in PersonalMessage.)
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $temp1;
		$settings['images_url'] = $temp2;
		$settings['theme_dir'] = $temp3;
	}

	if (!empty($editorOptions['live_errors']))
	{
		loadLanguage('Errors');

		addInlineJavascript('
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

	var body_err = new errorbox_handler({
		self: \'body_err\',
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
			return $("#' . $editorOptions['id'] . '").data("sceditor").val();
		});') . '
	});', true);
	}
}