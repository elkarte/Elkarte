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
 * Template for the mail queue
 */
function template_mail_queue()
{
	global $context, $txt;

	echo '
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mailqueue_stats'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt><strong>', $txt['mailqueue_size'], '</strong></dt>
						<dd>', $context['mail_queue_size'], '</dd>
						<dt><strong>', $txt['mailqueue_oldest'], '</strong></dt>
						<dd>', $context['oldest_mail'], '</dd>
					</dl>
				</div>
			</div>
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>';
}