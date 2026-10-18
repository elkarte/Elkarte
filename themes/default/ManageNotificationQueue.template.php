<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

/**
 * Template for the notification queue
 */
function template_notification_queue()
{
	global $context, $txt;

	echo '
	<div id="manage_notification_queue">
		<div id="notificationqueue_stats">
			<h2 class="category_header">', $txt['notificationqueue_stats'], '</h2>
			<div class="content">
				<dl class="settings">
					<dt><strong>', $txt['notificationqueue_size'], '</strong></dt>
					<dd>', $context['queue_size'], '</dd>
					<dt><strong>', $txt['notificationqueue_oldest'], '</strong></dt>
					<dd>', $context['oldest_job'], '</dd>
					<dt><strong>', $txt['notificationqueue_pending'], '</strong></dt>
					<dd>', $context['ready_jobs'], '</dd>
					<dt><strong>', $txt['notificationqueue_in_grace'], '</strong></dt>
					<dd>', $context['in_grace_jobs'], '</dd>
					<dt><strong>', $txt['notificationqueue_locked'], '</strong></dt>
					<dd>', $context['locked_jobs'], '</dd>
				</dl>
			</div>
		</div>';

	template_show_list('notification_queue');

	echo '
	</div>';
}
