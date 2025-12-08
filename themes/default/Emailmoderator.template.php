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
 * The report sub-template needs some error stuff
 */
function template_Emailmoderator_init()
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
 * The report sub template gets shown from:
 *  '?action=reporttm;topic=##.##;msg=##'
 * It should submit to:
 *  '?action=reporttm;topic=' . $context['current_topic'] . '.' . $context['start']
 *
 * It only needs to send the following fields:
 *  comment: an additional comment to give the moderator.
 *  sessionDI: $context['session_id'].
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
