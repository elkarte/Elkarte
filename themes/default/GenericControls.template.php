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
 */

// This function displays all the stuff you get with a richedit box - BBC, smileys etc.
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings, $options;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<div id="outer_container">
			<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" cols="600" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', $context['tabindex']++, '" style="width: ', $editor_context['width'], ';height: ', $editor_context['height'], '; ', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '" required="required">', $editor_context['value'], '</textarea>
		</div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />
		<script><!-- // --><![CDATA[
			$(document).ready(function(){',
			!empty($context['bbcodes_handlers']) ? $context['bbcodes_handlers'] : '', '
				$("#', $editor_id, '").sceditor({
					style: "', $settings['default_theme_url'], '/css/jquery.sceditor.default.css",
					width: "', $editor_context['width'], '",
					height: "', $editor_context['height'], '",
					emoticonsCompat: true,', !empty($editor_context['locale']) ? '
					locale: \'' . $editor_context['locale'] . '\',' : '', '
					colors: "black,red,yellow,pink,green,orange,purple,blue,beige,brown,teal,navy,maroon,limegreen,white",
					enablePasteFiltering: true,
					plugins: "bbcode', (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']) ? ', draft",
					draftOptions: {
						sLastNote: \'draft_lastautosave\',
						sSceditorID: \'' . $context['post_box_name'] . '\',
						sType: \'post\',
						iBoard: ' . (empty($context['current_board']) ? 0 : $context['current_board']) . ',
						iFreq: ' . $context['drafts_autosave_frequency'] . ',' . (!empty($context['drafts_save']) ?
						'sLastID: \'id_draft\'' : 'sLastID: \'id_pm_draft\', bPM: true') . '
					},' : '",'), '
					parserOptions: {
						quoteType: $.sceditor.BBCodeParser.QuoteType.auto
					}';

		// Show the smileys.
		if ((!empty($context['smileys']['postform']) || !empty($context['smileys']['popup'])) && !$editor_context['disable_smiley_box'] && $smileyContainer !== null)
		{
			echo ',
					emoticons:
					{';
			$countLocations = count($context['smileys']);
			foreach ($context['smileys'] as $location => $smileyRows)
			{
				$countLocations--;
				if ($location === 'postform')
					echo '
						dropdown:
						{';
				elseif ($location === 'popup')
					echo '
						popup:
						{';

				$numRows = count($smileyRows);

				// This is needed because otherwise the editor will remove all the duplicate (empty) keys and leave only 1 additional line
				$emptyPlaceholder = 0;
				foreach ($smileyRows as $smileyRow)
				{
					foreach ($smileyRow['smileys'] as $smiley)
					{
						echo '
							', JavaScriptEscape($smiley['code']), ': {url: ', JavaScriptEscape($settings['smileys_url'] . '/' . $smiley['filename']), ', tooltip: ', JavaScriptEscape($smiley['description']), '}', empty($smiley['isLast']) ? ',' : '';
					}

					if (empty($smileyRow['isLast']) && $numRows !== 1)
						echo ',
						\'-', $emptyPlaceholder++, '\': \'\',';
				}

				echo '
						}', $countLocations != 0 ? ',' : '';
			}

			echo '
					}';
		}
		else
			echo ',
					emoticons:
					{}';

		if ($context['show_bbc'] && $bbcContainer !== null)
		{
			echo ',
					toolbar: "emoticon,';
			$count_tags = count($context['bbc_tags']);

			// create the tooltag to display the buttons in the editor
			foreach ($context['bbc_toolbar'] as $i => $buttonRow)
			{
				echo implode('|', $buttonRow);
				$count_tags--;
				if (!empty($count_tags))
					echo '||';
			}

			echo '",';
		}
		else
			echo ',
					toolbar: "emoticon,source",';

		echo '
				});
				$("#', $editor_id, '").data("sceditor").createPermanentDropDown();
				$(".sceditor-container").width("100%").height("100%");',
				$editor_context['rich_active'] ? '' : '
				$("#' . $editor_id . '").data("sceditor").setTextMode();', '
				if (!(is_ie || is_ff || is_opera || is_safari || is_chrome))
					$(".sceditor-button-source").hide();
				', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
				$(".sceditor-container").find("textarea").each(function() {$(this).css({border: "1px solid red"})});
				$(".sceditor-container").find("iframe").each(function() {$(this).css({border: "1px solid red"})});' : '', '
			});
		// ]]></script>';
}

function template_control_richedit_buttons($editor_id)
{
	global $context, $settings, $options, $txt;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<span class="smalltext">
			', $context['shortcuts_text'], '
		</span>
		<input type="submit" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : $txt['post'], '" tabindex="', $context['tabindex']++, '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />';

	if ($editor_context['preview_type'])
		echo '
		<input type="submit" name="preview" value="', isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $txt['preview'], '" tabindex="', $context['tabindex']++, '" onclick="', $editor_context['preview_type'] == 2 ? 'return event.ctrlKey || previewControl();' : 'return submitThisOnce(this);', '" accesskey="p" class="button_submit" />';

	if ($context['show_spellchecking'])
		echo '
		<input type="button" value="', $txt['spell_check'], '" tabindex="', $context['tabindex']++, '" onclick="spellCheckStart();" class="button_submit" />
		<script src="', $settings['default_theme_url'], '/scripts/spellcheck.js"></script>
		<script><!-- // --><![CDATA[
			// Start up the spellchecker!
			function spellCheckStart(fieldName)
			{
				if (!spellCheck)
					return false

				var sUniqueId = ', JavaScriptEscape($editor_id), ';
				$("#" + sUniqueId).data("sceditor").storeLastState();

				// If we\'re in HTML mode we need to get the non-HTML text.
				$("#" + sUniqueId).data("sceditor").setTextMode()

				spellCheck(false, sUniqueId);

				return true;
			}
		// ]]></script>';

	if (!empty($context['drafts_save']))
	{
		// Show the save draft button
		echo '
		<input type="submit" name="save_draft" value="', $txt['draft_save'], '" tabindex="', $context['tabindex']++, '" onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d" class="button_submit" />
		<input type="hidden" id="id_draft" name="id_draft" value="', empty($context['id_draft']) ? 0 : $context['id_draft'], '" />';

		// Create an area to show the draft last saved on
		if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			echo '
		<br />
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon" />&nbsp;</span>
			<span id="draft_lastautosave" ></span>
		</span>';
	}

	if (!empty($context['drafts_pm_save']))
	{
		// The PM draft save button
		echo '
		<input type="submit" name="save_draft" value="', $txt['draft_save'], '" tabindex="', $context['tabindex']++, '" onclick="submitThisOnce(this);" accesskey="d" class="button_submit" />
		<input type="hidden" id="id_pm_draft" name="id_pm_draft" value="', empty($context['id_pm_draft']) ? 0 : $context['id_pm_draft'], '" />';

		// Load in the PM autosaver if its enabled and the user wants to use it
		if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			echo '
		<span class="righttext padding" style="display: block">
			<span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon" />&nbsp;</span>
			<span id="draft_lastautosave" ></span>
		</span>';
	}
}

// What's this, verification?!
function template_control_verification($verify_id)
{
	global $context;

	$verify_context = &$context['controls']['verification'][$verify_id];

	$i = 0;
	// Loop through each item to show them.
	foreach ($verify_context['test'] as $key => $verification)
	{
		if (empty($verification['values']) || empty($verification['template']))
			continue;

		echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		call_user_func('template_control_verification_' . $verification['template'], $verify_id, $verification['values']);

		echo '
			</div>';
		$i++;
	}
}

function template_control_verification_questions($verify_id, $verify_context)
{
	global $context;

	foreach ($verify_context as $question)
			echo '
				<div class="smalltext">
					', $question['q'], ':<br />
					<input type="text" name="', $verify_id, '_vv[q][', $question['id'], ']" size="30" value="', $question['a'], '" ', $question['is_error'] ? ' class="border_error"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" />
				</div>';
}

function template_control_verification_captcha($verify_id, $verify_context)
{
	global $context, $txt;

			if ($verify_context['use_graphic_library'])
				echo '
				<img src="', $verify_context['image_href'], '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '" />';
			else
				echo '
				<img src="', $verify_context['image_href'], ';letter=1" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_1" />
				<img src="', $verify_context['image_href'], ';letter=2" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_2" />
				<img src="', $verify_context['image_href'], ';letter=3" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_3" />
				<img src="', $verify_context['image_href'], ';letter=4" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_4" />
				<img src="', $verify_context['image_href'], ';letter=5" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_5" />
				<img src="', $verify_context['image_href'], ';letter=6" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_6" />';

			echo '
				<div class="smalltext" style="margin: 4px 0 8px 0;">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" rel="nofollow">', $txt['visual_verification_sound'], '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh">', $txt['visual_verification_request_new'], '</a><br /><br />
					', $txt['visual_verification_description'], ':<br />
					<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="', $verify_context['is_error'] ? 'border_error ' : '', 'input_text" />
				</div>';

}