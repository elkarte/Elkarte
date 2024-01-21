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
 *
 * @return void echo output
 */
function template_control_richedit($editor_id)
{
	global $context, $settings;

	$editor_context = &$context['controls']['richedit'][$editor_id];
	$class = isset($context['post_error']['errors']['no_message']) || isset($context['post_error']['errors']['long_message']) ? ' border_error' : '';
	$style = 'width:' . $editor_context['width'] . ';height: ' . $editor_context['height'];

	echo '
		<div id="editor_toolbar_container"></div>
		<label for="', $editor_id, '" class="hide">', $editor_id, '</label>
		<textarea class="editor', $class, '" name="', $editor_id, '" id="', $editor_id, '" tabindex="', $context['tabindex']++, '" style="', $style, ';" required="required">', $editor_context['value'], '</textarea>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />
		<script>
			let eTextarea = document.getElementById("', $editor_id, '"),
				$editor_data = {},
				$editor_container = {};
				
			function elk_editor() {
				sceditor.createEx(eTextarea, {
					style: "', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'jquery.sceditor.elk_wiz', $context['theme_variant'], '.css', CACHE_STALE, '",
					width: "100%",
					autofocus: ', (!empty($context['site_action']) && $context['site_action'] !== 'display') ? 'true' : 'false', ',
					autofocusEnd: false,
					startInSourceMode: ', $editor_context['rich_active'] ? 'false' : 'true', ',
					toolbarContainer: document.getElementById("editor_toolbar_container"),
					resizeWidth: false,
					resizeMaxHeight: -1,
					emoticonsCompat: true,
					emoticonsEnabled: ', $editor_context['disable_smiley_box'] ? 'false' : 'true', ',
					locale: "', !empty($editor_context['locale']) ? $editor_context['locale'] : 'en_US', '",
					rtl: ', empty($context['right_to_left']) ? 'false' : 'true', ',
					colors: "black,red,yellow,pink,green,orange,purple,blue,beige,brown,teal,navy,maroon,limegreen,white",
					enablePasteFiltering: true,
					format: "bbcode",
					plugins: "', implode(',', $context['plugins']), '",
					', trim(implode(',', $context['plugin_options']));

	// Show the smileys.
	echo $context['editor_smileys_toolbar'];

	// Show all the editor command buttons
	echo $context['editor_bbc_toolbar'];

	echo '
		});
		
		$editor_data.', $editor_id, ' = sceditor.instance(eTextarea);
		$editor_container.', $editor_id, ' = $(".sceditor-container");',
		isset($context['post_error']['errors']['no_message']) || isset($context['post_error']['errors']['long_message'])
			? '$editor_container.' . $editor_id . '.find("eTextarea, iframe").addClass("border_error");'
			: '', '
	};
	</script>
	<script type="module">
		elk_editor();
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
