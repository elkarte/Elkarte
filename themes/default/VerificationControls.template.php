<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * What's this, verification?!
 *
 * @param int $verify_id
 * @param string $before
 * @param string $after
 */
function template_verification_controls($verify_id, $before = '', $after = '')
{
	global $context;

	$verify_context = &$context['controls']['verification'][$verify_id];

	$i = 0;

	if ($verify_context['render'])
		echo $before;

	// Loop through each item to show them.
	foreach ($verify_context['test'] as $key => $verification)
	{
		if (empty($verification['values']) || empty($verification['template']))
			continue;

		echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		call_user_func('template_verification_control_' . $verification['template'], $verify_id, $verification['values']);

		echo '
			</div>';

		$i++;
	}

	if ($verify_context['render'])
		echo $after;
}

/**
 * Used to show a verification question
 *
 * @param int $verify_id
 * @param mixed[] $verify_context
 */
function template_verification_control_questions($verify_id, $verify_context)
{
	global $context;

	foreach ($verify_context as $question)
		echo '
				<div class="verificationquestion">
					<label for="', $verify_id, '_vv[q][', $question['id'], ']">', $question['q'], ':</label>
					<input type="text" id="', $verify_id, '_vv[q][', $question['id'], ']" name="', $verify_id, '_vv[q][', $question['id'], ']" size="30" value="', $question['a'], '" ', $question['is_error'] ? ' class="border_error"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" />
				</div>';
}

/**
 * Used to show one of those easy for robot, hard for human captcha's
 *
 * @param int $verify_id
 * @param mixed[] $verify_context
 */
function template_verification_control_captcha($verify_id, $verify_context)
{
	global $context, $txt;

	if ($verify_context['use_graphic_library'])
		echo '
				<img src="', $verify_context['image_href'], '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '" />';
	else
		for ($i = 0; $i < $verify_context['chars_number']; $i++)
			echo '
				<img src="', $verify_context['image_href'], ';letter=', $i, '" alt="', $txt['visual_verification_description'], '" id="verification_image_', $verify_id, '_', $i, '" />';

	echo '
				<div class="smalltext">
					<a href="', $verify_context['image_href'], ';sound" id="visual_verification_', $verify_id, '_sound" data-type="sound" class="playsound" rel="nofollow" onclick="document.getElementById(\'', $verify_id, '_vv[code]\').focus()">', $txt['visual_verification_sound'], '</a> / <a href="#visual_verification_', $verify_id, '_refresh" id="visual_verification_', $verify_id, '_refresh" class="refreshimage">', $txt['visual_verification_request_new'], '</a><br /><br />
					<label for="', $verify_id, '_vv[code]">', $txt['visual_verification_description'], '</label>:
					<input type="text" id="', $verify_id, '_vv[code]" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="', $verify_context['is_error'] ? 'border_error ' : '', 'input_text" />
				</div>';

	theme()-addInlineJavascript('
		$(\'.playsound, .refreshimage\').Elk_Captcha({
			\'uniqueID\': ' . JavaScriptEscape($verify_id) . ',
			\'imageURL\': ' . JavaScriptEscape($verify_context['image_href']) . ',
			\'useLibrary\': ' . ($verify_context['use_graphic_library'] ? 'true' : 'false') . ',
			\'letterCount\': ' . $verify_context['chars_number'] . '
		});', true);
}

/**
 * Display the empty field verification
 *
 * @param int $verify_id
 * @param mixed[] $verify_context
 */
function template_verification_control_emptyfield($verify_id, $verify_context)
{
	global $context, $txt;

	// Display an empty field verification
	echo '
			<div class="verification_control_valid">
				<label for="', $verify_context['field_name'], '">', $txt['visual_verification_hidden'], '</label>:
				<input type="text" id="', $verify_context['field_name'], '" name="', $verify_context['field_name'], '" autocomplete="off" size="30" value="', (!empty($verify_context['user_value']) ? $verify_context['user_value'] : ''), '" tabindex="', $context['tabindex']++, '" class="', $verify_context['is_error'] ? 'border_error ' : '', 'input_text" />
			</div>';
}