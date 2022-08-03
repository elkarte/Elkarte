<?php

/**
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

/**
 * This function displays all the goodies you get with a richedit box - BBC, smileys etc.
 *
 * @param string $editor_id
 * @param string|null $smileyContainer if set show the smiley container id
 * @param string|null $bbcContainer show the bbc container id
 *
 * @return void echo output
 */
function template_control_richedit($editor_id, $smileyContainer = null, $bbcContainer = null)
{
	global $context, $settings;

	$editor_context = &$context['controls']['richedit'][$editor_id];
	$useSmileys = (!empty($context['smileys']['postform']) || !empty($context['smileys']['popup'])) && !$editor_context['disable_smiley_box'] && $smileyContainer !== null;
	$class = isset($context['post_error']['errors']['no_message']) || isset($context['post_error']['errors']['long_message']) ? ' border_error' : '';
	$style = 'width:' . $editor_context['width'] . ';height: ' . $editor_context['height'];

	echo '
		<div id="editor_toolbar_container"></div>
		<label for="', $editor_id, '" class="hide">', $editor_id, '</label>
		<textarea class="editor', $class, '" name="', $editor_id, '" id="', $editor_id, '" tabindex="', $context['tabindex']++, '" style="', $style, ';" required="required">', $editor_context['value'], '</textarea>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />
		<script>
			let $editor_data = {},
				$editor_container = {},
				eTextarea = document.getElementById("', $editor_id, '");

			function elk_editor() {
				sceditor.createEx(eTextarea, {
					style: "', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'jquery.sceditor.elk_wiz', $context['theme_variant'], '.css', CACHE_STALE, '",
					width: "100%",
					startInSourceMode: ', $editor_context['rich_active'] ? 'false' : 'true', ',
					toolbarContainer: document.getElementById("editor_toolbar_container"),
					resizeWidth: false,
					resizeMaxHeight: -1,
					emoticonsCompat: true,
					emoticonsEnabled: ', $useSmileys ? 'true' : 'false', ',
					locale: "', !empty($editor_context['locale']) ? $editor_context['locale'] : 'en_US', '",
					rtl: ', empty($context['right_to_left']) ? 'false' : 'true', ',
					colors: "black,red,yellow,pink,green,orange,purple,blue,beige,brown,teal,navy,maroon,limegreen,white",
					enablePasteFiltering: true,
					format: "bbcode",
					plugins: "', implode(',', $context['plugins']), '",
					', trim(implode(',', $context['plugin_options']));

	// Show the smileys.
	if ($useSmileys)
	{
		echo ',
					emoticons: {';
		$countLocations = count($context['smileys']);
		foreach ($context['smileys'] as $location => $smileyRows)
		{
			$countLocations--;
			if ($location === 'postform')
			{
				echo '
						dropdown: {';
			}

			if ($location === 'popup')
			{
				echo '
						popup: {';
			}

			$numRows = count($smileyRows);

			// This is needed because otherwise the editor will remove all the duplicate (empty) keys and leave only 1 additional line
			$emptyPlaceholder = 0;
			foreach ($smileyRows as $smileyRow)
			{
				foreach ($smileyRow['smileys'] as $smiley)
				{
					echo '
							', JavaScriptEscape($smiley['code']), ': {url: ', JavaScriptEscape(rtrim($settings['smileys_url'], '/\\') . '/' . $smiley['filename']), ', tooltip: ', JavaScriptEscape($smiley['description']), '}', empty($smiley['isLast']) ? ',' : '';
				}

				if (empty($smileyRow['isLast']) && $numRows !== 1)
				{
					echo ',
						\'-', $emptyPlaceholder++, '\': \'\',';
				}
			}

			echo '
						}', $countLocations != 0 ? ',' : '';
		}

		echo '
					}';
	}
	else
	{
		echo ',
					emoticons: {}';
	}

	// Show all the editor command buttons
	if ($bbcContainer !== null)
	{
		echo ',
					toolbar: "';

		// Create the tooltag rows to display the buttons in the editor
		foreach ($context['bbc_toolbar'] as $i => $buttonRow)
		{
			echo $buttonRow[0], '||';
		}

		echo '"';
	}
	else
	{
		echo ',
					toolbar: "source"';
	}

	echo '});
				$editor_data.', $editor_id, ' = sceditor.instance(eTextarea);
				$editor_container.', $editor_id, ' = $(".sceditor-container");
				$editor_data.', $editor_id, '.css("code {white-space: pre;}").createPermanentDropDown();',
				isset($context['post_error']['errors']['no_message']) || isset($context['post_error']['errors']['long_message'])
					? '$editor_container.' . $editor_id . '.find("eTextarea, iframe").addClass("border_error");'
					: '', '
			}
		</script>
		
		<script type="module">
			$(function() {
				elk_editor();
			});
		</script>';
}

/**
 * Shows the buttons that the user can see .. preview, post, draft etc
 *
 * @param string $editor_id
 *
 * @return void echo output
 */
function template_control_richedit_buttons($editor_id)
{
	global $context, $txt;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<span class="shortcuts">';

	// If this message has been edited in the past - display when it was.
	if (isset($context['last_modified']))
	{
		echo '
			<p class="lastedit">', $context['last_modified_text'], '</p>';
	}

	// Show the helpful shortcut text
	echo '
			', $context['shortcuts_text'], '
		</span>
		<input type="submit" name="', $editor_context['labels']['post_name'] ?? 'post', '" value="', $editor_context['labels']['post_button'] ?? $txt['post'], '" tabindex="', $context['tabindex']++, '" onclick="return onPostSubmit() && submitThisOnce(this);" accesskey="s" />';

	if ($editor_context['preview_type'])
	{
		echo '
		<input type="button" name="preview" value="', $editor_context['labels']['preview_button'] ?? $txt['preview'], '" tabindex="', $context['tabindex']++, '" onclick="', $editor_context['preview_type'] == 2 ? 'return event.ctrlKey || previewControl();' : 'return submitThisOnce(this);', '" accesskey="p" />';
	}

	foreach ($editor_context['buttons'] as $button)
	{
		echo '
		<input type="button" name="', $button['name'], '" value="', $button['value'], '" tabindex="', $context['tabindex']++, '" ', $button['options'], ' />';
	}

	foreach ($editor_context['hidden_fields'] as $hidden)
	{
		echo '
		<input type="hidden" id="', $hidden['name'], '" name="', $hidden['name'], '" value="', $hidden['value'], '" />';
	}
}
