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
 * @version 1.0
 * 
 */

/**
 * This is where we get information about who they want to send the topic to, etc.
 *
 * The template gets shown from:
 *  '?action=emailuser;sa=sendtopic;topic=##.##'
 * And should submit to:
 *  '?action=emailuser;sa=sendtopic;topic=' . $context['current_topic'] . '.' . $context['start']
 *
 * It should send the following fields:
 *  y_name: sender's name.
 *  y_email: sender's email.
 *  comment: any additional comment.
 *  r_name: receiver's name.
 *  r_email: receiver's email address.
 *  send: this just needs to be set, as by the submit button.
 *  sc: the session id, or $context['session_id'].
 */
function template_send_topic()
{
	global $context, $txt, $scripturl;

	template_show_error('sendtopic_error');

	echo '
	<div id="send_topic">
		<form action="', $scripturl, '?action=emailuser;sa=sendtopic;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header hdicon cat_img_mail">
				', $context['page_title'], '
			</h2>
			<div class="windowbg2">
				<div class="content">
					<fieldset id="sender" class="send_topic">
						<dl class="settings send_topic">
							<dt>
								<label for="y_name"><strong>', $txt['sendtopic_sender_name'], ':</strong></label>
							</dt>
							<dd class="y_name">
								<input required="required" type="text" id="y_name" name="y_name" size="30" maxlength="40" value="', $context['user']['name'], '" class="input_text" />
							</dd>
							<dt>
								<label for="y_email"><strong>', $txt['sendtopic_sender_email'], ':</strong></label>
							</dt>
							<dd class="y_email">
								<input required="required" type="text" id="y_email" name="y_email" size="30" maxlength="50" value="', $context['user']['email'], '" class="input_text" />
							</dd>
							<dt>
								<label for="comment"><strong>', $txt['sendtopic_comment'], ':</strong></label>
							</dt>
							<dd class="comment">
								<input type="text" id="comment" name="comment" size="30" maxlength="100" class="input_text" />
							</dd>
						</dl>
					</fieldset>
					<fieldset id="recipient" class="send_topic">
						<dl class="settings send_topic">
							<dt>
								<label for="r_name"><strong>', $txt['sendtopic_receiver_name'], ':</strong></label>
							</dt>
							<dd class="r_name">
								<input required="required" type="text" id="r_name" name="r_name" size="30" maxlength="40" class="input_text" />
							</dd>
							<dt>
								<label for="r_email"><strong>', $txt['sendtopic_receiver_email'], ':</strong></label>
							</dt>
							<dd class="r_email">
								<input required="required" type="text" id="r_email" name="r_email" size="30" maxlength="50" class="input_text" />
							</dd>
						</dl>
					</fieldset>
					<div class="submitbutton">
						<input type="submit" name="send" value="', $txt['sendtopic_send'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
				</div>
			</div>
		</form>
	</div>';
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
			<h2 class="category_header hdicon cat_img_mail">
				', $context['page_title'], '
			</h2>
			<div class="windowbg">
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

	// If it's a guest we need their details.
	if ($context['user']['is_guest'])
		echo '
						<dt>
							<label for="y_name"><strong>', $txt['sendtopic_sender_name'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="y_name" name="y_name" size="24" maxlength="40" value="', $context['user']['name'], '" class="input_text" />
						</dd>
						<dt>
							<label for="y_email"><strong>', $txt['sendtopic_sender_email'], ':</strong></label><br />
							<span class="smalltext">', $txt['send_email_disclosed'], '</span>
						</dt>
						<dd>
							<input type="text" id="y_mail" name="y_email" size="24" maxlength="50" value="', $context['user']['email'], '" class="input_text" />
						</dt>';
	// Otherwise show the user that we know their email.
	else
		echo '
						<dt>
							<strong>', $txt['sendtopic_sender_email'], ':</strong><br />
							<span class="smalltext">', $txt['send_email_disclosed'], '</span>
						</dt>
						<dd>
							<em>', $context['user']['email'], '</em>
						</dd>';

	echo '
						<dt>
							<label for="email_subject"><strong>', $txt['send_email_subject'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="email_subject" name="email_subject" size="50" maxlength="100" class="input_text" />
						</dd>
						<dt>
							<label for="email_body"><strong>', $txt['message'], ':</strong></label>
						</dt>
						<dd>
							<textarea id="email_body" name="email_body" rows="10" cols="20" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 90%; min-width: 90%' : 'width: 90%') . ';"></textarea>
						</dd>
					</dl>
					<hr />
					<div class="submitbutton">
						<input type="submit" name="send" value="', $txt['sendtopic_send'], '" class="button_submit" />
					</div>
				</div>
			</div>';

	foreach ($context['form_hidden_vars'] as $key => $value)
		echo '
			<input type="hidden" name="', $key, '" value="', $value, '" />';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
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
			<input type="hidden" name="msg" value="' . $context['message_id'] . '" />
				<h2 class="category_header">', $txt['report_to_mod'], '</h2>
				<div class="windowbg">
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

	if ($context['require_verification'])
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
							<input type="submit" name="save" value="', $txt['rtm10'], '" class="button_submit" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						</div>
					</div>
				</div>
		</form>
	</div>';
}