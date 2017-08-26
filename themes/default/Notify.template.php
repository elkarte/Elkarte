<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Interface to allow notification enable/disable.
 */
function template_notification_settings()
{
	global $context, $txt, $scripturl;

	echo '
		<h2 class="category_header hdicon cat_img_mail">
			', $txt['notify'], '
		</h2>
		<div class="well centertext">
			<p>', $context['notification_set'] ? $txt['notify_deactivate'] : $txt['notify_request'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notify;sa=', $context['notification_set'] ? 'off' : 'on', ';topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $context['topic_href'], '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 * Interface for board notifications toggle.
 */
function template_notify_board()
{
	global $context, $txt, $scripturl;

	echo '
		<h2 class="category_header hdicon cat_img_mail">
			', $txt['notify'], '
		</h2>
		<div class="well centertext">
			<p>', $context['notification_set'] ? $txt['notifyboard_turnoff'] : $txt['notifyboard_turnon'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notifyboard;sa=', $context['notification_set'] ? 'off' : 'on', ';board=', $context['current_board'], '.', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $context['board_href'], '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}