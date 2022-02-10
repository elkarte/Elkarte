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
 * Loads the GenericHelpers template
 *
 * @throws \ElkArte\Exceptions\Exception
 */
function template_MergeTopics_init()
{
	theme()->getTemplates()->load('GenericHelpers');
}

/**
 * Template for the bit to show when merge topics is finished.
 */
function template_merge_done()
{
	global $context, $txt, $scripturl;

	echo '
		<div id="merge_topics">
			<h2 class="category_header">', $txt['merge'], '</h2>
			<div class="content">
				<p>', $txt['merge_successful'], '</p>
				<br />
				<ul>
					<li>
						<a href="', $scripturl, '?board=', $context['target_board'], '.0">', $txt['message_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?topic=', $context['target_topic'], '.0">', $txt['new_merged_topic'], '</a>
					</li>
				</ul>
			</div>
		</div>';
}

/**
 * Template to allow merge of two topics.
 */
function template_merge()
{
	global $context, $txt, $scripturl, $settings;

	echo '
		<div id="merge_topics">
			<h2 class="category_header">', $txt['merge'], '</h2>
			<div class="information">
				', $txt['merge_desc'], '
			</div>
			<div class="content">
				<dl class="settings merge_topic">
					<dt>
						<strong>', $txt['topic_to_merge'], ':</strong>
					</dt>
					<dd>
						', $context['origin_subject'], '
					</dd>';

	if (!empty($context['boards']) && count($context['boards']) > 1)
	{
		echo '
					<dt>
						<strong>', $txt['target_board'], ':</strong>
					</dt>
					<dd>
						<form action="' . $scripturl . '?action=mergetopics;from=', $context['origin_topic'] . ';targetboard=' . $context['target_board'], ';board=', $context['current_board'], '.0" method="post" accept-charset="UTF-8">
							<input type="hidden" name="from" value="' . $context['origin_topic'] . '" />
							<select name="targetboard" onchange="this.form.submit();">';

		foreach ($context['boards'] as $board)
		{
			echo '
									<option value="', $board['id'], '"', $board['id'] == $context['target_board'] ? ' selected="selected"' : '', '>', $board['category'], ' - ', $board['name'], '</option>';
		}

		echo '
							</select>
							<input type="submit" value="', $txt['go'], '" />
						</form>
					</dd>';
	}

	echo '
				</dl>
				<hr />
				<dl class="settings merge_topic">
					<dt>
						<label for="topics">', $txt['merge_to_topic_id'], '</label>: </strong>
					</dt>
					<dd>
						<form action="', $scripturl, '?action=mergetopics;sa=options" method="post" accept-charset="UTF-8">
							<input type="hidden" name="topics[]" value="', $context['origin_topic'], '" />
							<input type="text" id="topics" name="topics[]" class="input_text" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="submit" value="', $txt['merge'], '" />
						</form>
					</dd>';

	echo '
				</dl>
			</div>
			<div class="separator"></div>
			<h2 class="category_header">', $txt['target_topic'], '</h2>
			', template_pagesection(), '
			<div class="content">
				<ul class="merge_topics">';

	foreach ($context['topics'] as $topic)
	{
		echo '
					<li>
						<a class="linkbutton" href="', $scripturl, '?action=mergetopics;sa=options;board=', $context['current_board'], '.0;from=', $context['origin_topic'], ';to=', $topic['id'], ';', $context['session_var'], '=', $context['session_id'], '">
							<i class="icon i-merge"></i>', $txt['merge'], '
						</a>&nbsp;
						<a href="', $scripturl, '?topic=', $topic['id'], '.0" target="_blank" class="new_win">', $topic['subject'], '</a> ', sprintf($txt['topic_started_by'], $topic['poster']['link']), '
					</li>';
	}

	echo '
				</ul>
			</div>', template_pagesection(), '
		</div>';
}

/**
 * Template for the extra options for a topics merge.
 */
function template_merge_extra_options()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="merge_topics">
		<form action="', $scripturl, '?action=mergetopics;sa=execute;" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['merge_topic_list'], '</h2>
			<table class="bordercolor table_grid">
				<thead>
					<tr class="table_head">
						<th scope="col" style="width: 6em;">', $txt['merge_check'], '</th>
						<th scope="col" class="lefttext">', $txt['subject'], '</th>
						<th scope="col" class="lefttext">', $txt['started_by'], '</th>
						<th scope="col" class="lefttext">', $txt['last_post'], '</th>
						<th scope="col" style="width: 10em;">' . $txt['merge_include_notifications'] . '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['topics'] as $topic)
	{
		echo '
					<tr>
						<td class="centertext">
							<input type="checkbox" name="topics[]" value="', $topic['id'], '" checked="checked" />
						</td>
						<td>
							<a href="', $scripturl, '?topic=', $topic['id'], '.0" target="_blank" class="new_win">', $topic['subject'] . '</a>
						</td>
						<td>
							', $topic['started']['link'], '<br />
							<span class="smalltext">', $topic['started']['time'], '</span>
						</td>
						<td>
							' . $topic['updated']['link'] . '<br />
							<span class="smalltext">', $topic['updated']['time'], '</span>
						</td>
						<td class="centertext">
							<input type="checkbox" name="notifications[]" value="' . $topic['id'] . '" checked="checked" />
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="content">';

	echo '
				<fieldset id="merge_subject" class="merge_options">
					<legend>', $txt['merge_select_subject'], '</legend>
					<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? \'none\': \'block\' ;">';

	foreach ($context['topics'] as $topic)
	{
		echo '
						<option value="', $topic['id'], '"' . ($topic['selected'] ? ' selected="selected"' : '') . '>', $topic['subject'], '</option>';
	}

	echo '
						<option value="0">', $txt['merge_custom_subject'], ':</option>
					</select>
					<br />
					<input type="text" name="custom_subject" size="50" id="custom_subject" class="input_text custom_subject hide" />
					<br />
					<input type="checkbox" name="enforce_subject" id="enforce_subject" value="1" />
					<label for="enforce_subject">', $txt['merge_enforce_subject'], '</label>
				</fieldset>';

	if (!empty($context['boards']) && count($context['boards']) > 1)
	{
		echo '
					<fieldset id="merge_board" class="merge_options">
						<legend>', $txt['merge_select_target_board'], '</legend>';

		template_select_boards('board');

		echo '
					</fieldset>';
	}

	if (!empty($context['polls']))
	{
		echo '
					<fieldset id="merge_poll" class="merge_options">
						<legend>', $txt['merge_select_poll'], '</legend>
						<ul>';

		foreach ($context['polls'] as $poll)
		{
			echo '
							<li>
								<input type="radio" id="poll', $poll['id'], '" name="poll" value="', $poll['id'], '"', $poll['selected'] ? ' checked="checked"' : '', ' /> <label for="poll', $poll['id'], '">', $poll['question'], '</label> (', $txt['topic'], ': <a href="', $scripturl, '?topic=', $poll['topic']['id'], '.0" target="_blank" class="new_win">', $poll['topic']['subject'], '</a>)
							</li>';
		}

		echo '
							<li>
								<input type="radio" id="nopoll" name="poll" value="-1" /> <label for="nopoll">', $txt['merge_no_poll'], '</label>
							</li>
						</ul>
					</fieldset>';
	}

	echo '
					<div class="submitbutton">
						<input type="submit" value="' . $txt['merge'] . '" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="sa" value="execute" />
					</div>
				</div>
			</div>
		</form>
	</div>';
}
