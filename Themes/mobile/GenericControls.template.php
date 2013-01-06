<?php
/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
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
	global $context, $settings, $options, $txt, $modSettings, $scripturl;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
		<div>
			<div style="width: 98.8%;">
				<div>
					<textarea class="editor" name="', $editor_id, '" id="', $editor_id, '" rows="', $editor_context['rows'], '" cols="600" tabindex="', $context['tabindex']++, '" style="height: ', $editor_context['height'], '; ', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? 'border: 1px solid red;' : '', '">', $editor_context['value'], '</textarea>
				</div>
			</div>
		</div>
		<input type="hidden" name="', $editor_id, '_mode" id="', $editor_id, '_mode" value="0" />';
}

function template_control_richedit_buttons($editor_id)
{
	global $context, $settings, $options, $txt, $modSettings, $scripturl;

	$editor_context = &$context['controls']['richedit'][$editor_id];

	echo '
	<div class="ui-grid-a">
		<span class="ui-block-a">
			<input data-theme="b" type="submit" value="', isset($editor_context['labels']['post_button']) ? $editor_context['labels']['post_button'] : $txt['post'], '" tabindex="', $context['tabindex']++, '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />
		</span>';


	if ($editor_context['preview_type'])
		echo '
		<span class="ui-block-b">
			<input data-theme="c" type="submit" name="preview" value="', isset($editor_context['labels']['preview_button']) ? $editor_context['labels']['preview_button'] : $txt['preview'], '" tabindex="', $context['tabindex']++, '" onclick="', $editor_context['preview_type'] == 2 ? 'return event.ctrlKey || previewPost();' : 'return submitThisOnce(this);', '" accesskey="p" class="button_submit" />
		</span>';

	echo '
	</div>';
}

// What's this, verification?!
function template_control_verification($verify_id, $display_type = 'all', $reset = false)
{
	global $context, $settings, $options, $txt, $modSettings;

	$verify_context = &$context['controls']['verification'][$verify_id];

	// Keep track of where we are.
	if (empty($verify_context['tracking']) || $reset)
		$verify_context['tracking'] = 0;

	// How many items are there to display in total.
	$total_items = count($verify_context['questions']) + ($verify_context['show_visual'] ? 1 : 0);

	// If we've gone too far, stop.
	if ($verify_context['tracking'] > $total_items)
		return false;

	// Loop through each item to show them.
	for ($i = 0; $i < $total_items; $i++)
	{
		// If we're after a single item only show it if we're in the right place.
		if ($display_type == 'single' && $verify_context['tracking'] != $i)
			continue;

		if ($display_type != 'single')
			echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		// Do the actual stuff - image first?
		if ($i == 0 && $verify_context['show_visual'])
		{
			echo '
			<div data-role="fieldcontain">';
			
			if ($context['use_graphic_library'])
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


			echo '<br />
				<label for="verify">', $txt['verification'], ':</label>
				<input id="verify" type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="input_text" />
			</div>';

		}
		else
		{
			// Where in the question array is this question?
			$qIndex = $verify_context['show_visual'] ? $i - 1 : $i;

			echo '
				<div class="smalltext">
					', $verify_context['questions'][$qIndex]['q'], ':<br />
					<input type="text" name="', $verify_id, '_vv[q][', $verify_context['questions'][$qIndex]['id'], ']" size="30" value="', $verify_context['questions'][$qIndex]['a'], '" ', $verify_context['questions'][$qIndex]['is_error'] ? 'style="border: 1px red solid;"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" />
				</div>';
		}

		if ($display_type != 'single')
			echo '
			</div>';

		// If we were displaying just one and we did it, break.
		if ($display_type == 'single' && $verify_context['tracking'] == $i)
			break;
	}

	// Assume we found something, always,
	$verify_context['tracking']++;

	// Tell something displaying piecemeal to keep going.
	if ($display_type == 'single')
		return true;
}

?>