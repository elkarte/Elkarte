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
	{
		echo $before;
	}

	// Loop through each item to show them.
	foreach ($verify_context['test'] as $verification)
	{
		if (empty($verification['values']) || empty($verification['template']))
		{
			continue;
		}

		echo '
			<div id="verification_control_', $i, '" class="verification_control">';

		call_user_func('template_verification_control_' . $verification['template'], $verify_id, $verification['values']);

		echo '
			</div>';

		$i++;
	}

	if ($verify_context['render'])
	{
		echo $after;
	}
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
	{
		echo '
				<div class="verification_question">
					<label for="', $verify_id, '_vv[q][', $question['id'], ']">', $question['q'], ':</label>
					<input type="text" id="', $verify_id, '_vv[q][', $question['id'], ']" name="', $verify_id, '_vv[q][', $question['id'], ']" size="30" value="', $question['a'], '" ', $question['is_error'] ? ' class="border_error"' : '', ' tabindex="', $context['tabindex']++, '" class="input_text" />
				</div>';
	}
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
				<input type="text" id="', $verify_context['field_name'], '" name="', $verify_context['field_name'], '" autocomplete="off" size="30" value="', (empty($verify_context['user_value']) ? '' : $verify_context['user_value']), '" tabindex="', $context['tabindex']++, '" class="', $verify_context['is_error'] ? 'border_error ' : '', 'input_text" />
			</div>';
}

/**
 * Google reCaptcha, Empty div to be populated by the JS
 */
function template_verification_control_recaptcha($id, $values)
{
	echo '
				<div id="g-recaptcha" data-sitekey="' . $values['site_key'] . '" class="centertext" style="display: flex"></div>';
}

/**
 * hCaptcha, Empty div to be populated by the JS
 */
function template_verification_control_hcaptcha($id, $values)
{
	echo '
				<div id="h-captcha" data-sitekey="' . $values['site_key'] . '" class="centertext" style="display: flex"></div>';
}

/**
 * keyCaptcha, Empty div to be populated by the JS
 */
function template_verification_control_keycaptcha($id, $values)
{
	echo '
	<div class="centertext" style="display: flex">
		<div id="div_for_keycaptcha">
			<input name="key-capcode" id="key-capcode" type="hidden" value="">
		</div>
	</div>';
}
