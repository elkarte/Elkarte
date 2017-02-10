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
 * @version 1.0.10
 *
 */

/**
 * This function displays all the goodies you get with a richedit box - BBC, smileys etc.
 *
 * @param string $editor_id
 * @param string|null $smileyContainer if set show the smiley container id
 * @param string|null $bbcContainer show the bbc container id
 */
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings, $options;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	$plugins = array_filter(array('bbcode', 'splittag', (!empty($context['mentions_enabled']) ? 'mention' : ''), (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']) ? 'draft' : '')));

	// Allow addons to insert additional editor plugin scripts
	if (!empty($editor_context['plugin_addons']) && is_array($editor_context['plugin_addons']))
		$plugins = array_filter(array_merge($plugins, $editor_context['plugin_addons']));

	// Add in special config objects to the editor, typically for plugin use
	$plugin_options = array();
	$plugin_options[] = '
					parserOptions: {
						quoteType: $.sceditor.BBCodeParser.QuoteType.auto
					}';

	// Drafts?
	if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			$plugin_options[] = '
					draftOptions: {
						sLastNote: \'draft_lastautosave\',
						sSceditorID: \'' . $editor_id . '\',
						sType: \'post\',
						iBoard: ' . (empty($context['current_board']) ? 0 : $context['current_board']) . ',
						iFreq: ' . $context['drafts_autosave_frequency'] . ',' . (!empty($context['drafts_save']) ?
						'sLastID: \'id_draft\'' : 'sLastID: \'id_pm_draft\', bPM: true') . '
					}';

	if (!empty($context['mentions_enabled']))
			$plugin_options[] = '
					mentionOptions: {
						editor_id: \'' . $editor_id . '\',
						cache: {
							mentions: [],
							queries: [],
							names: []
						}
					}';

	// Allow addons to insert additional editor objects
	if (!empty($editor_context['plugin_options']) && is_array($editor_context['plugin_options']))
		$plugin_options = array_merge($plugin_options, $editor_context['plugin_options']);

	echo '
		<div id="editor_toolbar_container"></div>
		<label for="', $editor_id, '">
			<textarea class="editor', isset($context['post_error']['errors']['no_message']) || isset($context['post_error']['errors']['long_message']) ? ' border_error' : '', '" name="', $editor_id, '" id="', $editor_id, '" tabindex="', $context['tabindex']++, '" style="width:', $editor_context['width'], ';height: ', $editor_context['height'], ';" required="required">', $editor_context['value'], '</textarea>
		</label>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />
		<script><!-- // --><![CDATA[
			var $editor_data = {},
				$editor_container = {};

			function elk_editor() {',
				!empty($context['bbcodes_handlers']) ? $context['bbcodes_handlers'] : '', '
				$("#', $editor_id, '").sceditor({
					style: "', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'jquery.sceditor.elk_wiz', $context['theme_variant'], '.css', CACHE_STALE, '",
					width: "100%",
					toolbarContainer: $("#editor_toolbar_container"),
					resizeWidth: false,
					resizeMaxHeight: -1,
					emoticonsCompat: true,
					locale: "', !empty($editor_context['locale']) ? $editor_context['locale'] : 'en_US', '",
					rtl: ', empty($context['right_to_left']) ? 'false' : 'true', ',
					colors: "black,red,yellow,pink,green,orange,purple,blue,beige,brown,teal,navy,maroon,limegreen,white",
					enablePasteFiltering: true,
					plugins: "', implode(',', $plugins), '",
					', trim(implode(',', $plugin_options));

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

	// Show all the editor command buttons
	if ($context['show_bbc'] && $bbcContainer !== null)
	{
		echo ',
					toolbar: "';

		// Create the tooltag rows to display the buttons in the editor
		foreach ($context['bbc_toolbar'] as $i => $buttonRow)
			echo implode('', $buttonRow), '||';

		echo ',emoticon",';
	}
	else
		echo ',
					toolbar: "source,emoticon",';

	echo '
				});
				$editor_data[\'', $editor_id, '\'] = $("#', $editor_id, '").data("sceditor");
				$editor_container[\'', $editor_id, '\'] = $(".sceditor-container");
				$editor_data[\'', $editor_id, '\'].css(\'code {white-space: pre;}\').createPermanentDropDown();
				$editor_container[\'', $editor_id, '\'].width("100%").height("100%");', $editor_context['rich_active'] ? '' : '
				$editor_data[\'' . $editor_id . '\'].setTextMode();', '
				if (!(is_ie || is_ff || is_opera || is_safari || is_chrome))
					$(".sceditor-button-source").hide();
				', isset($context['post_error']['errors']['no_message']) || isset($context['post_error']['errors']['long_message']) ? '
				$editor_container[\'' . $editor_id . '\'].find("textarea, iframe").addClass("border_error");' : '', '
		}

		$(document).ready(function(){
			elk_editor();
		});

		// ]]></script>';
}

/**
 * Shows the buttons that the user can see .. preview, spellchecker, drafts, etc
 *
 * @param string $editor_id
 */
function template_control_richedit_buttons($editor_id)
{
	global $context, $options, $txt;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<span class="shortcuts">';

	// If this message has been edited in the past - display when it was.
	if (isset($context['last_modified']))
		echo '
			<p class="lastedit">', $context['last_modified_text'], '</p>';

	// Show the helpful shortcut text
	echo '
			', $context['shortcuts_text'], '
		</span>
		<input type="submit" name="', isset($editor_context['labels']['post_name']) ? $editor_context['labels']['post_name'] : 'post', '" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : $txt['post'], '" tabindex="', $context['tabindex']++, '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />';

	if ($editor_context['preview_type'])
		echo '
		<input type="submit" name="preview" value="', isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $txt['preview'], '" tabindex="', $context['tabindex']++, '" onclick="', $editor_context['preview_type'] == 2 ? 'return event.ctrlKey || previewControl();' : 'return submitThisOnce(this);', '" accesskey="p" class="button_submit" />';

	// Show the spellcheck button?
	if ($context['show_spellchecking'])
		echo '
		<input type="button" value="', $txt['spell_check'], '" tabindex="', $context['tabindex']++, '" onclick="spellCheckStart();" class="button_submit" />';

	// Maybe drafts are enabled?
	if (!empty($context['drafts_save']))
	{
		echo '
		<input type="submit" name="save_draft" value="', $txt['draft_save'], '" tabindex="', $context['tabindex']++, '" onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d" class="button_submit" />
		<input type="hidden" id="id_draft" name="id_draft" value="', empty($context['id_draft']) ? 0 : $context['id_draft'], '" />';
	}

	// The PM draft save button
	if (!empty($context['drafts_pm_save']))
	{
		echo '
		<input type="submit" name="save_draft" value="', $txt['draft_save'], '" tabindex="', $context['tabindex']++, '" onclick="submitThisOnce(this);" accesskey="d" class="button_submit" />
		<input type="hidden" id="id_pm_draft" name="id_pm_draft" value="', empty($context['id_pm_draft']) ? 0 : $context['id_pm_draft'], '" />';
	}

	// Create an area to show the draft last saved on text
	if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
		echo '
		<div class="draftautosave">
			<span id="throbber" style="display:none"><i class="fa fa-spinner fa-spin"></i>&nbsp;</span>
			<span id="draft_lastautosave"></span>
		</div>';
}
