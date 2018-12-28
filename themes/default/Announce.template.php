<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Announce a topic
 */
function template_announce()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="', $scripturl, '?action=announce;sa=send" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['announce_title'], '</h2>
			<div class="information">
				', $txt['announce_desc'], '
			</div>
			<div class="content">
				<p>
					', $txt['announce_this_topic'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0">', $context['topic_subject'], '</a>
				</p>
				<ul>';

	foreach ($context['groups'] as $group)
		echo '
					<li>
						<label for="who_', $group['id'], '"><input type="checkbox" name="who[', $group['id'], ']" id="who_', $group['id'], '" value="', $group['id'], '" checked="checked" /> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
					</li>';

	echo '
					<li>
						<label for="checkall"><input type="checkbox" id="checkall" onclick="invertAll(this, this.form);" checked="checked" /> <em>', $txt['check_all'], '</em></label>
					</li>
				</ul>
				<hr />
				<div class="submitbutton">
					<input type="submit" value="', $txt['sendtopic_send'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="topic" value="', $context['current_topic'], '" />
					<input type="hidden" name="move" value="', $context['move'], '" />
					<input type="hidden" name="goback" value="', $context['go_back'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Send an announcement out in increments
 * Shows a progress bar with continue button
 * autoSubmitted with JS
 */
function template_announcement_send()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="' . $scripturl . '?action=announce;sa=send" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<div class="content">
				<p class="infobox">', $txt['announce_sending'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0" target="_blank" class="new_win">: ', $context['topic_subject'], '</a></p>
				<div class="progress_bar">
					<div class="full_bar">', $context['percentage_done'], '% ', $txt['announce_done'], '</div>
					<div class="green_percent" style="width: ', $context['percentage_done'], '%;">&nbsp;</div>
				</div>
				<hr />
				<div class="submitbutton">
					<input type="submit" name="cont" value="', $txt['announce_continue'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="topic" value="', $context['current_topic'], '" />
					<input type="hidden" name="move" value="', $context['move'], '" />
					<input type="hidden" name="goback" value="', $context['go_back'], '" />
					<input type="hidden" name="start" value="', $context['start'], '" />
					<input type="hidden" name="membergroups" value="', $context['membergroups'], '" />
				</div>
			</div>
		</form>
	</div>
	<script>
		doAutoSubmit(3, ', JavaScriptEscape($txt['announce_continue']), ');
	</script>';
}
