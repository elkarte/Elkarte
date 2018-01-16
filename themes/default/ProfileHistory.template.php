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
 * @version 2.0 dev
 *
 */

/**
 * This template shows an admin information on a users IP addresses used and errors attributed to them.
 */
function template_trackActivity()
{
	global $context, $scripturl, $txt;

	// The first table shows IP information about the user.
	echo '
		<div class="generic_list_wrapper">
			<h2 class="category_header">', $txt['view_ips_by'], ' ', $context['member']['name'], '</h2>';

	// The last IP the user used.
	echo '
			<div id="tracking">
				<div class="content">
					<dl class="noborder">
						<dt>', $txt['most_recent_ip'], ':
							', (empty($context['last_ip2']) ? '' : '<br />
							<span class="smalltext">(<a href="' . $scripturl . '?action=quickhelp;help=whytwoip" onclick="return reqOverlayDiv(this.href);">' . $txt['why_two_ip_address'] . '</a>)</span>'), '
						</dt>
						<dd>
							<a href="', $scripturl, '?action=profile;area=history;sa=ip;searchip=', $context['last_ip'], ';u=', $context['member']['id'], '">', $context['last_ip'], '</a>';

	// Second address detected?
	if (!empty($context['last_ip2']))
		echo '
							, <a href="', $scripturl, '?action=profile;area=history;sa=ip;searchip=', $context['last_ip2'], ';u=', $context['member']['id'], '">', $context['last_ip2'], '</a>';

	echo '
						</dd>';

	// Lists of IP addresses used in messages / error messages.
	echo '
						<dt>', $txt['ips_in_messages'], ':</dt>
						<dd>
							', (count($context['ips']) > 0 ? implode(', ', $context['ips']) : '(' . $txt['none'] . ')'), '
						</dd>
						<dt>', $txt['ips_in_errors'], ':</dt>
						<dd>
							', (count($context['ips']) > 0 ? implode(', ', $context['error_ips']) : '(' . $txt['none'] . ')'), '
						</dd>';

	// List any members that have used the same IP addresses as the current member.
	echo '
						<dt>', $txt['members_in_range'], ':</dt>
						<dd>
							', (count($context['members_in_range']) > 0 ? implode(', ', $context['members_in_range']) : '(' . $txt['none'] . ')'), '
						</dd>
					</dl>
				</div>
			</div>
		</div>
		<br />';

	// Show the track user list.
	template_show_list('track_name_user_list');
}

/**
 * The template for trackIP, allowing the admin to see where/who a certain IP has been used.
 */
function template_trackIP()
{
	global $context, $txt;

	// This function always defaults to the last IP used by a member but can be set to track any IP.
	// The first table in the template gives an input box to allow the admin to enter another IP to track.
	echo '
	<div>
		<h2 class="category_header">', $txt['trackIP'], '</h2>
		<div class="well">
			<form action="', $context['base_url'], '" method="post" accept-charset="UTF-8">
				<dl class="settings">
					<dt>
						<label for="searchip">', $txt['enter_ip'], ':</label>
					</dt>
					<dd>
						<input type="text" id="searchip" name="searchip" value="', $context['ip'], '" class="input_text" />
					</dd>
				</dl>
				<input type="submit" value="', $txt['trackIP'], '" class="right_submit" />
			</form>
		</div>
	</div>
	<br />
	<div class="generic_list_wrapper">';

	// The table in between the first and second table shows links to the whois server for every region.
	if ($context['single_ip'])
	{
		echo '
			<h2 class="category_header">', $txt['whois_title'], ' ', $context['ip'], '</h2>
			<div class="content">';

		foreach ($context['whois_servers'] as $server)
			echo '
					<a href="', $server['url'], '" target="_blank" class="new_win">', $server['name'], '</a><br />';

		echo '
			</div>';
	}

	// The second table lists all the members who have been logged as using this IP address.
	echo '
		<h2 class="category_header">', $txt['members_from_ip'], ' ', $context['ip'], '</h2>';

	if (empty($context['ips']))
		echo '
		<p class="description"><em>', $txt['no_members_from_ip'], '</em></p>';
	else
	{
		echo '
		<table class="table_grid">
			<thead>
				<tr class="table_head">
					<th scope="col">', $txt['ip_address'], '</th>
					<th scope="col">', $txt['display_name'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Loop through each of the members and display them.
		foreach ($context['ips'] as $ip => $memberlist)
			echo '
				<tr>
					<td><a href="', $context['base_url'], ';searchip=', $ip, '">', $ip, '</a></td>
					<td>', implode(', ', $memberlist), '</td>
				</tr>';

		echo '
			</tbody>
		</table>';
	}

	echo '
	</div>
	<br />';

	template_show_list('track_message_list');

	echo '<br />';

	template_show_list('track_ip_user_list');
}