<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
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

function template_poll_edit()
{
	global $context, $settings, $txt, $scripturl;

	// Some javascript for adding more options.
	if (!empty($context['form_url']))
		echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var pollOptionNum = 0, pollTabIndex;
		var pollOptionId = ', $context['last_choice_id'], ';
		var txt_option = "', $txt['option'], '";
		var form_name = \'postmodify\';
	// ]]></script>
	<div id="edit_poll">
		<form action="', $context['form_url'], '" method="post" accept-charset="UTF-8" onsubmit="submitonce(this); smc_saveEntities(\'postmodify\', [\'question\'], \'options-\');" name="postmodify" id="postmodify">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>
			<div>
				<div class="roundframe">';

	template_show_error('poll_error');

	if (!empty($context['poll']['id']))
		echo '
					<input type="hidden" name="poll" value="', $context['poll']['id'], '" />';
	echo '
					<fieldset id="poll_main">
						<legend><span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], ':</span></legend>
						<input type="text" name="question" value="', isset($context['poll']['question']) ? $context['poll']['question'] : '', '" tabindex="', $context['tabindex']++, '" size="80" class="input_text" />
						<ul class="poll_main">';

	// Loop through all the choices and print them out.
	foreach ($context['choices'] as $choice)
	{
		echo '
							<li>
								<label for="options-', $choice['id'], '" ', (isset($context['poll_error']['poll_few']) ? ' class="error"' : ''), '>', $txt['option'], ' ', $choice['number'], '</label>:
								<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" tabindex="', $context['tabindex']++, '" size="80" maxlength="255" class="input_text" />';

		// Does this option have a vote count yet, or is it new?
		if (isset($choice['votes']) && $choice['votes'] != -1)
			echo ' (', $choice['votes'], ' ', $txt['votes'], ')';

		echo '
							</li>';
	}

	echo '
							<li id="pollMoreOptions"></li>
						</ul>
						<strong><a href="javascript:addPollOption(); void(0);">(', $txt['poll_add_option'], ')</a></strong>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', $txt['poll_options'], ':</legend>
						<dl class="settings poll_options">';

	if ($context['can_moderate_poll'])
	{
		echo '
							<dt>
								<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
							</dt>
							<dd>
								<input type="text" name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll']['max_votes'], '" class="input_text" />
							</dd>
							<dt>
								<label for="poll_expire">', $txt['poll_run'], ':</label><br />
								<em class="smalltext">', $txt['poll_run_limit'], '</em>
							</dt>
							<dd>
								<input type="text" name="poll_expire" id="poll_expire" size="2" value="', $context['poll']['expiration'], '" onchange="pollOptions();" maxlength="4" class="input_text" /> ', $txt['days_word'], '
							</dd>
							<dt>
								<label for="poll_change_vote">', $txt['poll_do_change_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

		if ($context['poll']['guest_vote_allowed'])
			echo '
							<dt>
								<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll']['guest_vote']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';
	}

	echo '
							<dt>
								', $txt['poll_results_visibility'], ':
							</dt>
							<dd>
								<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll']['hide_results'] == 0 ? ' checked="checked"' : '', ' class="input_radio" /> <label for="poll_results_anyone">', $txt['poll_results_anyone'], '</label><br />
								<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll']['hide_results'] == 1 ? ' checked="checked"' : '', ' class="input_radio" /> <label for="poll_results_voted">', $txt['poll_results_voted'], '</label><br />
								<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll']['hide_results'] == 2 ? ' checked="checked"' : '', empty($context['poll']['expiration']) ? ' disabled="disabled"' : '', ' class="input_radio" /> <label for="poll_results_expire">', $txt['poll_results_after'], '</label>
							</dd>
						</dl>
					</fieldset>';
	// If this is an edit, we can allow them to reset the vote counts.
	if (!empty($context['is_edit']))
		echo '
					<fieldset id="poll_reset">
						<legend>', $txt['reset_votes'], '</legend>
						<input type="checkbox" name="resetVoteCount" value="on" class="input_check" /> ' . $txt['reset_votes_check'] . '
					</fieldset>';

	if (!empty($context['form_url']))
		echo '
					<div class="padding flow_auto">
						<input type="submit" name="post" value="', $txt['save'], '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />
					</div>
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
		</form>
	</div>';
}
