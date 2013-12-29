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
 * @version 1.0 Beta
 *
 */

/**
 * What's this, verification?!
 *
 * @param int $verify_id
 * @param string $before
 * @param string $after
 */
function template_control_verification($verify_id, $before = '', $after = '')
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

		call_user_func('template_control_verification_' . $verification['template'], $verify_id, $verification['values']);

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
 * @param array $verify_context
 */
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

/**
 * Used to show one of those easy for robot, hard for human captcha's
 *
 * @param int $verify_id
 * @param array $verify_context
 */
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
					', $txt['visual_verification_description'], ':
					<input type="text" name="', $verify_id, '_vv[code]" value="', !empty($verify_context['text_value']) ? $verify_context['text_value'] : '', '" size="30" tabindex="', $context['tabindex']++, '" class="', $verify_context['is_error'] ? 'border_error ' : '', 'input_text" />
				</div>';
}

/**
 * Display the empty field verificaiton
 *
 * @param int $verify_id
 * @param array $verify_context
 */
function template_control_verification_emptyfield($verify_id, $verify_context)
{
	global $context, $txt;

	// Display an empty field verificaiton
	echo '
			<div class="smalltext verification_control_valid">
				', $txt['visual_verification_hidden'], ':
				<input type="text" name="', $verify_context['field_name'], '" autocomplete="off" size="30" value="', (!empty($verify_context['user_value']) ? $verify_context['user_value'] : '' ), '" tabindex="', $context['tabindex']++, '" class="', $verify_context['is_error'] ? 'border_error ' : '', 'input_text" />
			</div>';
}