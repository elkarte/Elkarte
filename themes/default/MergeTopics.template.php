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
 * @version 1.0 Alpha
 */

/**
 * Template for the bit to show when merge topics is finished.
 */
function template_merge_done()
{
	global $context, $txt, $scripturl;

	echo '
		<div id="merge_topics">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['merge'], '</h3>
			</div>
			<div class="windowbg">
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
			</div>
		</div>
	<br class="clear" />';
}

/**
 * Template to allow merge of two topics.
 */
function template_merge()
{
	global $context, $txt, $scripturl;

	echo '
		<div id="merge_topics">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['merge'], '</h3>
			</div>
			<div class="information">
				', $txt['merge_desc'], '
			</div>
			<div class="windowbg">
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
				echo '
									<option value="', $board['id'], '"', $board['id'] == $context['target_board'] ? ' selected="selected"' : '', '>', $board['category'], ' - ', $board['name'], '</option>';
			echo '
								</select>
								<input type="submit" value="', $txt['go'], '" class="button_submit" />
							</form>
						</dd>';
	}

	echo '
					</dl>
					<hr class="hrcolor" />
					<dl class="settings merge_topic">
						<dt>
							<strong>', $txt['merge_to_topic_id'], ': </strong>
						</dt>
						<dd>
							<form action="', $scripturl , '?action=mergetopics;sa=options" method="post" accept-charset="UTF-8">
								<input type="hidden" name="topics[]" value="', $context['origin_topic'], '" />
								<input type="text" name="topics[]" class="input_text" />
								<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
								<input type="submit" value="', $txt['merge'], '" class="button_submit" />
							</form>
						</dd>';

		echo '
					</dl>
				</div>
			</div><br />
			<div class="cat_bar">
				<h3 class="catbg">', $txt['target_topic'], '</h3>
			</div>', template_pagesection(false, false, 'go_down'), '
			<div class="windowbg2">
				<div class="content">
					<ul class="merge_topics">';

		$merge_button = create_button('merge.png', 'merge', '');

		foreach ($context['topics'] as $topic)
			echo '
						<li>
							<a href="', $scripturl, '?action=mergetopics;sa=options;board=', $context['current_board'], '.0;from=', $context['origin_topic'], ';to=', $topic['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $merge_button, '</a>&nbsp;
							<a href="', $scripturl, '?topic=', $topic['id'], '.0" target="_blank" class="new_win">', $topic['subject'], '</a> ', $txt['started_by'], ' ', $topic['poster']['link'], '
						</li>';

		echo '
					</ul>
				</div>
			</div>', template_pagesection(false, false), '
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
			<div class="title_bar">
				<h3 class="titlebg">', $txt['merge_topic_list'], '</h3>
			</div>
			<table class="bordercolor table_grid">
				<thead>
					<tr class="catbg">
						<th scope="col" class="first_th centertext" style="width:6em">', $txt['merge_check'], '</th>
						<th scope="col" class="lefttext">', $txt['subject'], '</th>
						<th scope="col" class="lefttext">', $txt['started_by'], '</th>
						<th scope="col" class="lefttext">', $txt['last_post'], '</th>
						<th scope="col" class="last_th centertext" style="width:10em">' . $txt['merge_include_notifications'] . '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['topics'] as $topic)
			echo '
					<tr class="windowbg2">
						<td class="centertext">
							<input type="checkbox" class="input_check" name="topics[]" value="', $topic['id'], '" checked="checked" />
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
							<input type="checkbox" class="input_check" name="notifications[]" value="' . $topic['id'] . '" checked="checked" />
						</td>
					</tr>';

		echo '
				</tbody>
			</table>
			<br />
			<div class="windowbg">
				<div class="content">';

	echo '
					<fieldset id="merge_subject" class="merge_options">
						<legend>', $txt['merge_select_subject'], '</legend>
						<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? \'none\': \'\' ;">';

	foreach ($context['topics'] as $topic)
		echo '
							<option value="', $topic['id'], '"' . ($topic['selected'] ? ' selected="selected"' : '') . '>', $topic['subject'], '</option>';

	echo '
							<option value="0">', $txt['merge_custom_subject'], ':</option>
						</select>
						<br /><input type="text" name="custom_subject" size="60" id="custom_subject" class="input_text custom_subject" style="display: none;" />
						<br />
						<label for="enforce_subject"><input type="checkbox" class="input_check" name="enforce_subject" id="enforce_subject" value="1" /> ', $txt['merge_enforce_subject'], '</label>
					</fieldset>';

	if (!empty($context['boards']) && count($context['boards']) > 1)
	{
		echo '
					<fieldset id="merge_board" class="merge_options">
						<legend>', $txt['merge_select_target_board'], '</legend>
						<ul>';
		foreach ($context['boards'] as $board)
			echo '
							<li>
								<input type="radio" name="board" value="' . $board['id'] . '"' . ($board['selected'] ? ' checked="checked"' : '') . ' class="input_radio" /> ' . $board['name'] . '
							</li>';
		echo '
						</ul>
					</fieldset>';
	}

	if (!empty($context['polls']))
	{
		echo '
					<fieldset id="merge_poll" class="merge_options">
						<legend>' . $txt['merge_select_poll'] . '</legend>
						<ul>';
		foreach ($context['polls'] as $poll)
			echo '
							<li>
								<input type="radio" name="poll" value="' . $poll['id'] . '"' . ($poll['selected'] ? ' checked="checked"' : '') . ' class="input_radio" /> ', $poll['question'], ' (', $txt['topic'], ': <a href="', $scripturl, '?topic=', $poll['topic']['id'], '.0" target="_blank" class="new_win">', $poll['topic']['subject'], '</a>)
							</li>';
		echo '
							<li>
								<input type="radio" name="poll" value="-1" class="input_radio" /> (', $txt['merge_no_poll'], ')
							</li>
						</ul>
					</fieldset>';
	}

	echo '
					<div class="auto_flow">
						<input type="submit" value="' . $txt['merge'] . '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="sa" value="execute" />
					</div>
				</div>
			</div>
		</form>
	</div>';
}