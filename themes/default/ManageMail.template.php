<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

/**
 * Template for the mail queue
 */
function template_mail_queue()
{
	global $context, $txt;

	echo '
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<h2 class="category_header">', $txt['mailqueue_stats'], '</h2>
			<div class="content">
				<dl class="settings">
					<dt><strong>', $txt['mailqueue_size'], '</strong></dt>
					<dd>', $context['mail_queue_size'], '</dd>
					<dt><strong>', $txt['mailqueue_oldest'], '</strong></dt>
					<dd>', $context['oldest_mail'], '</dd>
				</dl>
			</div>
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>';
}

/**
 * Template for testing outbound email.
 */
function template_mail_test()
{
	global $context, $txt, $scripturl;

	// Some result?
	if (!empty($context['result']))
	{
		if ($context['result'] === 'fail')
		{
			$result_txt = sprintf($txt['mail_test_fail'], $scripturl . '?action=admin;area=logs;sa=errorlog;desc');
		}
		else
		{
			$result_txt = $txt['mail_test_pass'];
		}

		echo '
		<div class="', $context['result'] === 'pass' ? 'infobox' : 'errorbox', '">', $result_txt, '</div>';
	}

	echo '
	<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=mailqueue;sa=test" method="post" accept-charset="UTF-8">
		<h2 class="category_header">
			', $txt['mail_test_header'], '
		</h2>
		<div class="content">
			<dl class="settings">
				<dt>
					<label for="send_to">', $txt['mail_send_to'], '</label>
				</dt>
				<dd>
					<input type="email" name="send_to" />
				</dd>
				<dt>
					<label for="subject">', $txt['subject'], '</label>
				</dt>
				<dd>
					<input type="text" name="subject" />
				</dd>
				<dt>
					<label for="message">', $txt['message'], '</label>
				</dt>
				<dd>
					<textarea name="message" rows="5"></textarea>
				</dd>	
			</dl>
			<div class="submitbutton">
				<input type="submit" name="send" value="', $txt['send_message'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-mailtest_token_var'], '" value="', $context['admin-mailtest_token'], '" />
			</div>
		</div>
	</form>';
}
