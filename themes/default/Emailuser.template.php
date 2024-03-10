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
 * The report sub-template needs some error stuff
 */
function template_Emailuser_init()
{
	global $context, $txt;

	 if (empty($context['sub_template'])) {
		 return;
	 }

	 if ($context['sub_template'] !== 'report') {
		 return;
	 }

	 theme()->addInlineJavascript('
		error_txts[\'post_too_long\'] = ' . JavaScriptEscape($txt['error_post_too_long']) . ';

		function checkReportForm()
		{
			let checkID = \'report_comment\',
				comment = document.getElementById(checkID).value.trim();
		
			let error = new errorbox_handler({
				error_box_id: \'report_error\',
				error_code: \'post_too_long\',
			});
		
			error.checkErrors(comment.length > 254);
			if (comment.length > 254)
			{
				document.getElementById(checkID).setAttribute(\'onkeyup\', \'checkReportForm()\');
				return false;
			}
			
			return true;
		}
		', true);
}

/**
 * Send an email to a user!
 */
function template_custom_email()
{
	global $context, $txt, $scripturl;

	template_show_error('sendemail_error');

	echo '
	<div id="send_topic">
		<form action="', $scripturl, '?action=emailuser;sa=email" method="post" accept-charset="UTF-8">
			<h2 class="category_header hdicon i-envelope">
				', $context['page_title'], '
			</h2>
			<div class="content">
				<dl class="settings send_mail">
					<dt>
						<strong>', $txt['sendtopic_receiver_name'], ':</strong>
					</dt>
					<dd>
						', $context['recipient']['link'], '
					</dd>';

	// Can the user see the persons email?
	if ($context['can_view_recipient_email'])
	{
		echo '
					<dt>
						<strong>', $txt['sendtopic_receiver_email'], ':</strong>
					</dt>
					<dd>
						', $context['recipient']['email_link'], '
					</dd>
				</dl>
				<hr />
				<dl class="settings send_mail">';
	}

	// If it's a guest we need their details.
	if ($context['user']['is_guest'])
	{
		echo '
					<dt>
						<label for="y_name">', $txt['sendtopic_sender_name'], ':</label>
					</dt>
					<dd>
						<input type="text" id="y_name" name="y_name" size="24" maxlength="40" value="', $context['user']['name'], '" class="input_text" />
					</dd>
					<dt>
						<label for="y_email">', $txt['sendtopic_sender_email'], ':</label><br />
						<span class="smalltext">', $txt['send_email_disclosed'], '</span>
					</dt>
					<dd>
						<input type="text" id="y_mail" name="y_email" size="24" maxlength="50" value="', $context['user']['email'], '" class="input_text" />
					</dt>';
	}
	// Otherwise show the user that we know their email.
	else
	{
		echo '
					<dt>
						<strong>', $txt['sendtopic_sender_email'], ':</strong><br />
						<span class="smalltext">', $txt['send_email_disclosed'], '</span>
					</dt>
					<dd>
						<em>', $context['user']['email'], '</em>
					</dd>';
	}

	echo '
					<dt>
						<label for="email_subject">', $txt['send_email_subject'], ':</label>
					</dt>
					<dd>
						<input type="text" id="email_subject" name="email_subject" size="50" maxlength="100" class="input_text" />
					</dd>
					<dt>
						<label for="email_body">', $txt['message'], ':</label>
					</dt>
					<dd>
						<textarea id="email_body" name="email_body" rows="10" cols="20"></textarea>
					</dd>
				</dl>
				<hr />
				<div class="submitbutton">
					<input type="submit" name="send" value="', $txt['sendtopic_send'], '" />';


	foreach ($context['form_hidden_vars'] as $key => $value)
	{
		echo '
					<input type="hidden" name="', $key, '" value="', $value, '" />';
	}

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * The report sub template gets shown from:
 *  '?action=reporttm;topic=##.##;msg=##'
 * It should submit to:
 *  '?action=reporttm;topic=' . $context['current_topic'] . '.' . $context['start']
 *
 * It only needs to send the following fields:
 *  comment: an additional comment to give the moderator.
 *  sc: the session id, or $context['session_id'].
 */
function template_report()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="report_topic">
		<form action="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['report_to_mod'], '</h2>
			<div class="content">';

	template_show_error('report_error');

	echo '
				<p class="warningbox">', $txt['report_to_mod_func'], '</p>
				<br />
				<dl class="settings" id="report_post">';

	if ($context['user']['is_guest'])
	{
		echo '
					<dt>
						<label for="email_address">', $txt['email'], '</label>:
					</dt>
					<dd>
						<input type="text" id="email_address" name="email" value="', $context['email_address'], '" size="25" maxlength="255" />
					</dd>';
	}

	echo '
					<dt>
						<label for="report_comment">', $txt['enter_comment'], '</label>:
					</dt>
					<dd>
						<textarea id="report_comment" name="comment">', $context['comment_body'], '</textarea>
					</dd>';

	if (!empty($context['require_verification']))
	{
		template_verification_controls($context['visual_verification_id'], '
					<dt>
						' . $txt['verification'] . ':
					</dt>
					<dd>
						', '
					</dd>');
	}

	echo '
				</dl>
				<div class="submitbutton">
					<input type="hidden" name="msg" value="' . $context['message_id'] . '" />
					<input type="submit" name="save" value="', $txt['rtm10'], '" onclick="return checkReportForm();" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>
	</div>';
}
