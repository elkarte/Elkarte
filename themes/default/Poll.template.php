<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Interface to allow edit of a poll.
 */
function template_poll_edit()
{
	global $context, $txt;

	// Some javascript for adding more options.
	echo '
	<script>
		var pollOptionNum = 0,
			pollTabIndex = null,
			pollOptionId = ', $context['last_choice_id'], ',
			txt_option = "', $txt['option'], '",
			form_name = \'postmodify\';
	</script>';

	if (!empty($context['form_url']))
		echo '
	<div id="edit_poll">
		<form id="postmodify" name="postmodify" action="', $context['form_url'], '" method="post" accept-charset="UTF-8" onsubmit="submitonce(this); smc_saveEntities(\'postmodify\', [\'question\'], \'options-\');">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div>
				<div class="well">';

	template_show_error('poll_error');

	if (!empty($context['poll']['id']))
		echo '
					<input type="hidden" name="poll" value="', $context['poll']['id'], '" />';
	echo '
						<fieldset id="poll_main">
							<legend>', $txt['poll_question_options'], '</legend>
							<label for="question"', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], ':</label>
							<input type="text" id="question" name="question" value="', isset($context['poll']['question']) ? $context['poll']['question'] : '', '" tabindex="', $context['tabindex']++, '" size="80" class="input_text" required="required" placeholder="', $txt['poll_question'], '" />
							<ul class="poll_main">';

	// Loop through all the choices and print them out.
	foreach ($context['poll']['choices'] as $choice)
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
							<a class="linkbutton" href="javascript:addPollOption(); void(0);">', $txt['poll_add_option'], '</a>
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
									<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked="checked"' : '', ' />
								</dd>';

		if ($context['poll']['guest_vote_allowed'])
			echo '
								<dt>
									<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
								</dt>
								<dd>
									<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll']['guest_vote']) ? ' checked="checked"' : '', ' />
								</dd>';
	}

	echo '
								<dt>
									', $txt['poll_results_visibility'], ':
								</dt>
								<dd>
									<label for="poll_results_anyone">
										<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll']['hide_results'] == 0 ? ' checked="checked"' : '', ' /> ', $txt['poll_results_anyone'], '
									</label><br />
									<label for="poll_results_voted">
										<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll']['hide_results'] == 1 ? ' checked="checked"' : '', ' /> ', $txt['poll_results_voted'], '
									</label><br />
									<label for="poll_results_expire">
										<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll']['hide_results'] == 2 ? ' checked="checked"' : '', empty($context['poll']['expiration']) ? ' disabled="disabled"' : '', ' /> ', $txt['poll_results_after'], '
									</label>
								</dd>
							</dl>
						</fieldset>';

	// If this is an edit, we can allow them to reset the vote counts.
	// @todo a warning maybe while saving?
	if (!empty($context['is_edit']))
		echo '
					<fieldset id="poll_reset">
						<legend>', $txt['reset_votes'], '</legend>
						<input type="checkbox" id="resetVoteCount" name="resetVoteCount" value="on" /> <label for="resetVoteCount">' . $txt['reset_votes_check'] . '</label>
					</fieldset>';

	if (!empty($context['form_url']))
		echo '
					<div class="submitbutton">
						<input type="submit" name="post" value="', $txt['save'], '" onclick="return submitThisOnce(this);" accesskey="s" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
					</div>
				</div>
			</div>
		</form>
	</div>';
}
