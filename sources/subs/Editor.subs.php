<?php

/**
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
 * @version 1.0 Alpha
 *
 * This file contains those functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Creates the javascript code for localization of the editor (SCEditor)
 */
function action_loadlocale()
{
	global $txt, $editortxt, $modSettings;

	loadLanguage('Editor');

	Template_Layers::getInstance()->removeAll();

	// Lets make sure we aren't going to output anything nasty.
	@ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		@ob_start();

	// If we don't have any locale better avoid broken js
	if (empty($txt['lang_locale']))
		die();

	$file_data = '(function ($) {
	\'use strict\';

	$.sceditor.locale[' . javaScriptEscape($txt['lang_locale']) . '] = {';

	foreach ($editortxt as $key => $val)
		$file_data .= '
		' . javaScriptEscape($key) . ': ' . javaScriptEscape($val) . ',';

	$file_data .= '
		dateFormat: "day.month.year"
	}
})(jQuery);';

	// Make sure they know what type of file we are.
	header('Content-Type: text/javascript');
	echo $file_data;
	obExit(false);
}

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
			$request = $db->query('select_message_icons', '
				SELECT title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})',
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
 * @param array $editorOptions
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
		// Some general stuff.
		$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];
		if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			$context['drafts_autosave_frequency'] = empty($modSettings['drafts_autosave_frequency']) ? 30000 : $modSettings['drafts_autosave_frequency'] * 1000;

		// This really has some WYSIWYG stuff.
		loadTemplate('GenericControls', 'jquery.sceditor');

		// JS makes the editor go round
		loadJavascriptFile(array('jquery.sceditor.js', 'jquery.sceditor.bbcode.js', 'jquery.sceditor.elkarte.js', 'post.js'));
		addJavascriptVar(array(
			'elk_smileys_url' => '"' . $settings['smileys_url'] . '"',
			'bbc_quote_from' => '"' . addcslashes($txt['quote_from'], "'") . '"',
			'bbc_quote' => '"' . addcslashes($txt['quote'], "'") . '"',
			'bbc_search_on' => '"' . addcslashes($txt['search_on'], "'") . '"')
		);

		// editor language file
		if (!empty($txt['lang_locale']) && $txt['lang_locale'] != 'en_US')
			loadJavascriptFile($scripturl . '?action=loadeditorlocale', array(), 'sceditor_language');

		// Drafts?
		if ((!empty($context['drafts_save']) || !empty($context['drafts_pm_save'])) && !empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			loadJavascriptFile('drafts.plugin.js');

		if (!empty($context['notifications_enabled']))
			loadJavascriptFile('mentioning.plugin.js');

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
			array(
				'code' => 'ftp',
				'description' => $txt['ftp']
			),
			array(
				'code' => 'flash',
				'description' => $txt['flash']
			),
			array(),
			array(
				'code' => 'glow',
				'description' => $txt['glow']
			),
			array(
				'code' => 'shadow',
				'description' => $txt['shadow']
			),
			array(
				'code' => 'move',
				'description' => $txt['marquee']
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

		if (empty($modSettings['enableEmbeddedFlash']))
			$disabled_tags[] = 'flash';

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
	<style type="text/css">' . $bbcodes_styles . '
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

/**
 * Create a anti-bot verification control?
 * @param array &$verificationOptions
 * @param bool $do_test = false
 */
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $context;

	// We need to remember this because when failing the page is realoaded and the
	// code must remain the same (unless it has to change)
	static $all_instances = array();

	// @todo: maybe move the list to $modSettings instead of hooking it?
	// Used in ManageSecurity_Controller->action_spamSettings_display too
	$known_verifications = array(
		'captcha',
		'questions',
		'emptyfield'
	);
	call_integration_hook('integrate_control_verification', array(&$known_verifications));

	// Always have an ID.
	assert(isset($verificationOptions['id']));
	$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);

	if ($isNew)
		$context['controls']['verification'][$verificationOptions['id']] = array(
			'id' => $verificationOptions['id'],
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
		);
	$thisVerification = &$context['controls']['verification'][$verificationOptions['id']];

	if (!isset($_SESSION[$verificationOptions['id'] . '_vv']))
		$_SESSION[$verificationOptions['id'] . '_vv'] = array();

	$force_refresh = ((!empty($_SESSION[$verificationOptions['id'] . '_vv']['did_pass']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) || $_SESSION[$verificationOptions['id'] . '_vv']['count'] > 3) && empty($verificationOptions['dont_refresh']));
	if (!isset($all_instances[$verificationOptions['id']]))
	{
		$all_instances[$verificationOptions['id']] = array();

		$current_instance = null;
		foreach ($known_verifications as $verification)
		{
			$class_name = 'Control_Verification_' . ucfirst($verification);
			$current_instance = new $class_name($verificationOptions);

			// If there is anything to show, otherwise forget it
			if ($current_instance->showVerification($isNew, $force_refresh))
				$all_instances[$verificationOptions['id']][$verification] = $current_instance;
		}
	}

	$instances = &$all_instances[$verificationOptions['id']];

	// Is there actually going to be anything?
	if (empty($instances))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

	$verification_errors = Error_Context::context($verificationOptions['id']);
	$increase_error_count = false;

	// Start with any testing.
	if ($do_test)
	{
		// This cannot happen!
		if (!isset($_SESSION[$verificationOptions['id'] . '_vv']['count']))
			fatal_lang_error('no_access', false);

		foreach ($instances as $instance)
		{
			$outcome = $instance->doTest();
			if ($outcome !== true)
			{
				$increase_error_count = true;
				$verification_errors->addError($outcome);
			}
		}
	}

	// Any errors means we refresh potentially.
	if ($increase_error_count)
	{
		if (empty($_SESSION[$verificationOptions['id'] . '_vv']['errors']))
			$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		// Too many errors?
		elseif ($_SESSION[$verificationOptions['id'] . '_vv']['errors'] > $thisVerification['max_errors'])
			$force_refresh = true;

		// Keep a track of these.
		$_SESSION[$verificationOptions['id'] . '_vv']['errors']++;
	}

	// Are we refreshing then?
	if ($force_refresh)
	{
		// Assume nothing went before.
		$_SESSION[$verificationOptions['id'] . '_vv']['count'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = false;
	}

	foreach ($instances as $test => $instance)
	{
		$instance->createTest($force_refresh);
		$thisVerification['test'][$test] = $instance->prepareContext();
	}

	$_SESSION[$verificationOptions['id'] . '_vv']['count'] = empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) ? 1 : $_SESSION[$verificationOptions['id'] . '_vv']['count'] + 1;

	// Return errors if we have them.
	if ($verification_errors->hasErrors())
	{
		// @todo temporary until the error class is implemented in register
		$error_codes = array();
		foreach ($verification_errors->getErrors() as $errors)
			foreach ($errors as $error)
				$error_codes[] = $error;

		return $error_codes;
	}
	// If we had a test that one, make a note.
	elseif ($do_test)
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = true;

	// Say that everything went well chaps.
	return true;
}

interface Control_Verifications
{
	function showVerification($isNew, $force_refresh = true);
	function createTest($refresh = true);
	function prepareContext();
	function doTest();
	function settings();
}

class Control_Verification_Captcha implements Control_Verifications
{
	private $_options = null;
	private $_show_captcha = false;
	private $_text_value = null;
	private $_image_href = null;
	private $_tested = false;
	private $_use_graphic_library = false;
	private $_standard_captcha_range = array();

	public function __construct($verificationOptions = null)
	{
		$this->_use_graphic_library = in_array('gd', get_loaded_extensions());

		// Skip I, J, L, O, Q, S and Z.
		$this->_standard_captcha_range = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));

		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	public function showVerification($isNew, $force_refresh = true)
	{
		global $context, $modSettings, $scripturl;

		// a bit of a trick, just to load only once the js
		if (!isset($context['captcha_js_loaded']))
		{
			$context['captcha_js_loaded'] = false;

			// The template
			loadTemplate('GenericControls');
		}

		// Some javascript ma'am? (But load it only once)
		if (!empty($this->_options['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($this->_options['override_visual'])) && empty($context['captcha_js_loaded']))
		{
			loadJavascriptFile('captcha.js');
			$context['captcha_js_loaded'] = true;
		}

		$this->_tested = false;

		if ($isNew)
		{
			$this->_show_captcha = !empty($this->_options['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($this->_option['override_visual']));
			$this->_text_value = '';
			$this->_image_href = $scripturl . '?action=verificationcode;vid=' . $this->_options['id'] . ';rand=' . md5(mt_rand());

			addInlineJavascript('
				var verification' . $this->_options['id'] . 'Handle = new elkCaptcha("' . $this->_image_href . '", "' . $this->_options['id'] . '", ' . ($this->_use_graphic_library ? 1 : 0) . ');', true);
		}

		if ($isNew || $force_refresh)
			$this->createTest($force_refresh);

		return $this->_show_captcha;
	}

	public function createTest($refresh = true)
	{
		global $modSettings;

		if (!$this->_show_captcha)
			return;

		if ($refresh)
		{
			$_SESSION[$this->_options['id'] . '_vv']['code'] = '';

			// Are we overriding the range?
			$character_range = !empty($this->_options['override_range']) ? $this->_options['override_range'] : $this->_standard_captcha_range;

			for ($i = 0; $i < $modSettings['visual_verification_num_chars']; $i++)
				$_SESSION[$this->_options['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}
		else
			$this->_text_value = !empty($_REQUEST[$this->_options['id'] . '_vv']['code']) ? Util::htmlspecialchars($_REQUEST[$this->_options['id'] . '_vv']['code']) : '';
	}

	public function prepareContext()
	{
		return array(
			'template' => 'captcha',
			'values' => array(
				'image_href' => $this->_image_href,
				'text_value' => $this->_text_value,
				'use_graphic_library' => $this->_use_graphic_library,
				'is_error' => $this->_tested && !$this->_verifyCode(),
			)
		);
	}

	public function doTest()
	{
		$this->_tested = true;

		if (!$this->_verifyCode())
			return 'wrong_verification_code';

		return true;
	}

	public function settings()
	{
		global $txt, $scripturl, $modSettings;

		// Generate a sample registration image.
		$verification_image = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

		// Visual verification.
		$config_vars = array(
			array('title', 'configure_verification_means'),
			array('desc', 'configure_verification_means_desc'),
			array('int', 'visual_verification_num_chars'),
			'vv' => array('select', 'visual_verification_type',
				array($txt['setting_image_verification_off'], $txt['setting_image_verification_vsimple'], $txt['setting_image_verification_simple'], $txt['setting_image_verification_medium'], $txt['setting_image_verification_high'], $txt['setting_image_verification_extreme']),
				'subtext'=> $txt['setting_visual_verification_type_desc'], 'onchange' => $this->_use_graphic_library ? 'refreshImages();' : ''),
		);

		if (isset($_GET['save']))
		{
			if (isset($_POST['visual_verification_num_chars']) && $_POST['visual_verification_num_chars'] < 6)
				$_POST['visual_verification_num_chars'] = 5;
		}

		$_SESSION['visual_verification_code'] = '';
		for ($i = 0; $i < $modSettings['visual_verification_num_chars']; $i++)
			$_SESSION['visual_verification_code'] .= $this->_standard_captcha_range[array_rand($this->_standard_captcha_range)];

		// Some javascript for CAPTCHA.
		if ($this->_use_graphic_library)
			addInlineJavascript('
			function refreshImages()
			{
				var imageType = document.getElementById(\'visual_verification_type\').value;
				document.getElementById(\'verification_image\').src = \'' . $verification_image . ';type=\' + imageType;
			}', true);

		// Show the image itself, or text saying we can't.
		if ($this->_use_graphic_library)
			$config_vars['vv']['postinput'] = '<br /><img src="' . $verification_image . ';type=' . (empty($modSettings['visual_verification_type']) ? 0 : $modSettings['visual_verification_type']) . '" alt="' . $txt['setting_image_verification_sample'] . '" id="verification_image" /><br />';
		else
			$config_vars['vv']['postinput'] = '<br /><span class="smalltext">' . $txt['setting_image_verification_nogd'] . '</span>';

		return $config_vars;
	}

	private function _verifyCode()
	{
		return !$this->_show_captcha || (!empty($_REQUEST[$this->_options['id'] . '_vv']['code']) && !empty($_SESSION[$this->_options['id'] . '_vv']['code']) && strtoupper($_REQUEST[$this->_options['id'] . '_vv']['code']) === $_SESSION[$this->_options['id'] . '_vv']['code']);
	}
}

class Control_Verification_Questions implements Control_Verifications
{
	private $_options = null;
	private $_questionIDs = null;
	private $_number_questions = null;
	private $_questions_language = null;
	private $_possible_questions = null;

	public function __construct($verificationOptions = null)
	{
		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	public function showVerification($isNew, $force_refresh = true)
	{
		global $modSettings, $user_info, $language;

		if ($isNew)
		{
			$this->_number_questions = isset($this->_options['override_qs']) ? $this->_options['override_qs'] : (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0);

			// If we want questions do we have a cache of all the IDs?
			if (!empty($this->_number_questions) && empty($modSettings['question_id_cache']))
				$this->_refreshQuestionsCache();

			// Let's deal with languages
			// First thing we need to know what language the user wants and if there is at least one question
			$this->_questions_language = !empty($_SESSION[$this->_options['id'] . '_vv']['language']) ? $_SESSION[$this->_options['id'] . '_vv']['language'] : (!empty($user_info['language']) ? $user_info['language'] : $language);

			// No questions in the selected language?
			if (empty($modSettings['question_id_cache'][$this->_questions_language]))
			{
				// Not even in the forum default? What the heck are you doing?!
				if (empty($modSettings['question_id_cache'][$language]))
				{
					$this->_number_questions = 0;
				}
				// Fall back to the default
				else
					$this->_questions_language = $language;
			}

			// Do we have enough questions?
			if (!empty($this->_number_questions) && $this->_number_questions <= count($modSettings['question_id_cache'][$this->_questions_language]))
			{
				$this->_possible_questions = $modSettings['question_id_cache'][$this->_questions_language];
				$this->_number_questions = count($this->_possible_questions);
				$this->_questionIDs = array();

				if ($isNew || $force_refresh)
					$this->createTest($force_refresh);
			}
		}

		return !empty($this->_number_questions);
	}

	public function createTest($refresh = true)
	{
		if (empty($this->_number_questions))
			return;

		// Getting some new questions?
		if ($refresh)
		{
			// Pick some random IDs
			if ($this->_number_questions == 1)
				$this->_questionIDs[] = $this->_possible_questions[array_rand($this->_possible_questions, $this->_number_questions)];
			else
				foreach (array_rand($this->_possible_questions, $this->_number_questions) as $index)
					$this->_questionIDs[] = $this->_possible_questions[$index];
		}
		// Same questions as before.
		else
			$this->_questionIDs = !empty($_SESSION[$this->_options['id'] . '_vv']['q']) ? $_SESSION[$this->_options['id'] . '_vv']['q'] : array();
	}

	public function prepareContext()
	{
		$_SESSION[$this->_options['id'] . '_vv']['q'] = array();

		$questions = $this->_loadAntispamQuestions(array('type' => 'id_question', 'value' => $this->_questionIDs));
		$asked_questions = array();

		foreach ($questions as $row)
		{
			$asked_questions[] = array(
				'id' => $row['id_question'],
				'q' => parse_bbc($row['question']),
				'is_error' => !empty($this->_incorrectQuestions) && in_array($row['id_question'], $this->_incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$this->_options['id'] . '_vv'], $_REQUEST[$this->_options['id'] . '_vv']['q'], $_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) ? Util::htmlspecialchars($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) : '',
			);
			$_SESSION[$this->_options['id'] . '_vv']['q'][] = $row['id_question'];
		}

		return array(
			'template' => 'questions',
			'values' => $asked_questions,
		);
	}

	public function doTest()
	{
		if ($this->_number_questions && (!isset($_SESSION[$this->_options['id'] . '_vv']['q']) || !isset($_REQUEST[$this->_options['id'] . '_vv']['q'])))
			fatal_lang_error('no_access', false);

		if (!$this->_verifyAnswers())
			return 'wrong_verification_answer';

		return true;
	}

	public function settings()
	{
		global $txt, $context, $language;

		// Load any question and answers!
		$filter = null;
		if (isset($_GET['language']))
			$filter = array(
				'type' => 'language',
				'value' => $_GET['language'],
			);
		$context['question_answers'] = $this->_loadAntispamQuestions($filter);
		$languages = getLanguages();
		// Languages dropdown only if we have more than a lang installed, otherwise is plain useless
		if (count($languages) > 1)
		{
			$context['languages'] = $languages;
			foreach ($context['languages'] as &$lang)
				if ($lang['filename'] === $language)
					$lang['selected'] = true;
		}

		if (isset($_GET['save']))
		{
			// Handle verification questions.
			$questionInserts = array();
			$count_questions = 0;

			foreach ($_POST['question'] as $id => $question)
			{
				$question = trim(Util::htmlspecialchars($question, ENT_COMPAT));
				$answers = array();
				$question_lang = isset($_POST['language'][$id]) && isset($languages[$_POST['language'][$id]]) ? $_POST['language'][$id] : $language;
				if (!empty($_POST['answer'][$id]))
					foreach ($_POST['answer'][$id] as $answer)
					{
						$answer = trim(Util::strtolower(Util::htmlspecialchars($answer, ENT_COMPAT)));
						if ($answer != '')
							$answers[] = $answer;
					}

				// Already existed?
				if (isset($context['question_answers'][$id]))
				{
					$count_questions++;
					// Changed?
					if ($question == '' || empty($answers))
					{
						$this->_delete($id);
						$count_questions--;
					}
					else
						$this->_update($id, $question, $answers, $question_lang);
				}
				// It's so shiney and new!
				elseif ($question != '' && !empty($answers))
				{
					$questionInserts[] = array(
						'question' => $question,
						// @todo: remotely possible that the serialized value is longer than 65535 chars breaking the update/insertion
						'answer' => serialize($answers),
						'language' => $question_lang,
					);
					$count_questions++;
				}
			}

			// Any questions to insert?
			if (!empty($questionInserts))
				$this->_insert($questionInserts);

			if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
				$_POST['qa_verification_number'] = $count_questions;

		}

		return array(
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			array('title', 'setup_verification_questions'),
				array('desc', 'setup_verification_questions_desc'),
				array('int', 'qa_verification_number', 'postinput' => $txt['setting_qa_verification_number_desc']),
				array('callback', 'question_answer_list'),
		);
	}

	/**
	* Checks if an the answers to anti-spam questions are correct
	* @param string $verificationId the ID of the verification element
	* @return mixed true if the answers are correct, an array of id of wrong questions otherwise
	*/
	private function _verifyAnswers()
	{
		// Get the answers and see if they are all right!
		$questions = $this->_loadAntispamQuestions(array('type' => 'id_question', 'value' => $_SESSION[$this->_options['id'] . '_vv']['q']));
		$this->_incorrectQuestions = array();
		foreach ($questions as $row)
		{
			// Everything lowercase
			$answers = array();
			foreach ($row['answer'] as $answer)
				$answers[] = Util::strtolower($answer);

			if (!isset($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) || trim($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]) == '' || !in_array(trim(Util::htmlspecialchars(Util::strtolower($_REQUEST[$this->_options['id'] . '_vv']['q'][$row['id_question']]))), $answers))
				$this->_incorrectQuestions[] = $row['id_question'];
		}

		return empty($this->_incorrectQuestions);
	}

	/**
	* Updates the cache of questions IDs
	*/
	private function _refreshQuestionsCache()
	{
		global $modSettings;

		$db = database();

		if (($modSettings['question_id_cache'] = cache_get_data('verificationQuestionIds', 300)) == null)
		{
			$request = $db->query('', '
				SELECT id_question, language
				FROM {db_prefix}antispam_questions',
				array()
			);
			$modSettings['question_id_cache'] = array();
			while ($row = $db->fetch_assoc($request))
				$modSettings['question_id_cache'][$row['language']][] = $row['id_question'];
			$db->free_result($request);

			if (!empty($modSettings['cache_enable']))
				cache_put_data('verificationQuestionIds', $modSettings['question_id_cache'], 300);
		}
	}

	/**
	* Loads all the available antispam questions, or a subset based on a filter
	* @param array $filter, if specified it myst be an array with two indexes:
	*              - 'type' => a valid filter, it can be 'language' or 'id_question'
	*              - 'value' => the value of the filter (i.e. the language)
	*/
	private function _loadAntispamQuestions($filter = null)
	{
		$db = database();

		$available_filters = array(
			'language' => 'language = {string:current_filter}',
			'id_question' => 'id_question IN ({array_int:current_filter})',
		);

		// Load any question and answers!
		$question_answers = array();
		$request = $db->query('', '
			SELECT id_question, question, answer, language
			FROM {db_prefix}antispam_questions' . ($filter === null || !isset($available_filters[$filter['type']]) ? '' : '
			WHERE ' . $available_filters[$filter['type']]),
			array(
				'current_filter' => $filter['value'],
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			$question_answers[$row['id_question']] = array(
				'id_question' => $row['id_question'],
				'question' => $row['question'],
				'answer' => unserialize($row['answer']),
				'language' => $row['language'],
			);
		}
		$db->free_result($request);

		return $question_answers;
	}

	private function _delete($id)
	{
		$db = database();

		$db->query('', '
			DELETE FROM {db_prefix}antispam_questions
			WHERE id_question = {int:id}',
			array(
				'id' => $id,
			)
		);
	}

	private function _update($id, $question, $answers, $language)
	{
		$db = database();

		$db->query('', '
			UPDATE {db_prefix}antispam_questions
			SET
				question = {string:question},
				answer = {string:answer},
				language = {string:language}
			WHERE id_question = {int:id}',
			array(
				'id' => $id,
				'question' => $question,
				// @todo: remotely possible that the serialized value is longer than 65535 chars breaking the update/insertion
				'answer' => serialize($answers),
				'language' => $language,
			)
		);
	}

	private function _insert($questions)
	{
		$db = database();

		$db->insert('',
			'{db_prefix}antispam_questions',
			array('question' => 'string-65535', 'answer' => 'string-65535', 'language' => 'string-50'),
			$questions,
			array('id_question')
		);
	}
}

/**
 * This class shows an anti spam bot box in the form
 * The proper response is to leave the field empty, bots however will see this
 * much like a session field and populate it with a value.
 *
 * Adding additional catch terms is recommended to keep bots from learning
 */
class Control_Verification_EmptyField implements Control_Verifications
{
	private $_options = null;
	private $_empty_field = null;
	private $_tested = false;
	private $_user_value = null;
	private $_hash = null;
	private $_terms = array('gadget', 'device', 'uid', 'gid', 'guid', 'uuid', 'unique', 'identifier', 'bb2');
	private $_second_terms = array('hash', 'cipher', 'code', 'key', 'unlock', 'bit', 'value', 'screener');

	public function __construct($verificationOptions = null)
	{
		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	/**
	 * Returns if we are showing this verification control or not
	 *
	 * @param type $isNew
	 * @param type $force_refresh
	 */
	public function showVerification($isNew, $force_refresh = true)
	{
		global $modSettings;

		$this->_tested = false;

		if ($isNew)
		{
			$this->_empty_field = !empty($this->_options['no_empty_field']) || (!empty($modSettings['enable_emptyfield']) && !isset($this->_option['no_empty_field']));
			$this->_user_value = '';
		}

		if ($isNew || $force_refresh)
			$this->createTest($force_refresh);

		return $this->_empty_field;
	}

	/**
	 * Create the name data for the empty field that will be added to the template
	 *
	 * @param boolean $refresh
	 */
	public function createTest($refresh = true)
	{
		if (!$this->_empty_field)
			return;

		// Building a field with a believable name that will be inserted lives in the template.
		if ($refresh)
		{
			$start = mt_rand(0, 27);
			$this->_hash = substr(md5(time()), $start, 6);
			$_SESSION[$this->_options['id'] . '_vv']['empty_field'] = '';
			$_SESSION[$this->_options['id'] . '_vv']['empty_field'] = $this->_terms[array_rand($this->_terms)] . '-' . $this->_second_terms[array_rand($this->_second_terms)] . '-' . $this->_hash;
		}
		else
			$this->_user_value = !empty($_REQUEST[$_SESSION[$this->_options['id'] . '_vv']['empty_field']]) ? $_REQUEST[$_SESSION[$this->_options['id'] . '_vv']['empty_field']] : '';
	}

	/**
	 * Values passed to the template inside of GenericControls
	 * Use the values to adjust how the control does or does not appear
	 */
	public function prepareContext()
	{
		return array(
			'template' => 'emptyfield',
			'values' => array(
				'is_error' => $this->_tested && !$this->_verifyField(),
				// Can be used in the template to show the normally hidden field to add some spice to things
				'show' => !empty($_SESSION[$this->_options['id'] . '_vv']['empty_field']) && (mt_rand(1, 100) > 60),
				'user_value' => $this->_user_value,
				// Can be used in the template to randomly add a value to the empty field that needs to be removed when show is on
				'clear' => (mt_rand(1, 100) > 60),
			)
		);
	}

	/**
	 * Run the test on the returned value and return pass or fail
	 */
	public function doTest()
	{
		$this->_tested = true;

		if (!$this->_verifyField())
			return 'wrong_verification_answer';

		return true;
	}

	/**
	 * Test the field, easy, its on, its is set and it is empty
	 */
	private function _verifyField()
	{
		return $this->_empty_field && !empty($_SESSION[$this->_options['id'] . '_vv']['empty_field']) && empty($_REQUEST[$_SESSION[$this->_options['id'] . '_vv']['empty_field']]);
	}

	/**
	 * Callback for this verification control options, which is on or off
	 */
	public function settings()
	{
		// Empty field verification.
		$config_vars = array(
			array('title', 'configure_emptyfield'),
			array('desc', 'configure_emptyfield_desc'),
			array('check', 'enable_emptyfield'),
		);

		return $config_vars;
	}
}