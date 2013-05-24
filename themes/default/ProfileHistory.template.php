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
 * This template shows an admin information on a users IP addresses used and errors attributed to them.
 */
function template_trackActivity()
{
	global $context, $scripturl, $txt;

	// The first table shows IP information about the user.
	echo '
		<div class="generic_list_wrapper">
			<div class="title_bar">
				<h3 class="titlebg"><strong>', $txt['view_ips_by'], ' ', $context['member']['name'], '</strong></h3>
			</div>';

	// The last IP the user used.
	echo '
			<div id="tracking" class="windowbg2">
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
	<div class="tborder">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['trackIP'], '</h3>
		</div>
		<div class="roundframe">
			<form action="', $context['base_url'], '" method="post" accept-charset="UTF-8">
				<dl class="settings">
					<dt>
						<label for="searchip"><strong>', $txt['enter_ip'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="searchip" value="', $context['ip'], '" class="input_text" />
					</dd>
				</dl>
				<input type="submit" value="', $txt['trackIP'], '" class="button_submit" />
			</form>
		</div>
	</div>
	<br />
	<div class="generic_list_wrapper">';

	// The table inbetween the first and second table shows links to the whois server for every region.
	if ($context['single_ip'])
	{
		echo '
			<div class="title_bar">
				<h3 class="titlebg">', $txt['whois_title'], ' ', $context['ip'], '</h3>
			</div>
			<div class="windowbg2">
				<div class="padding">';
			foreach ($context['whois_servers'] as $server)
				echo '
					<a href="', $server['url'], '" target="_blank" class="new_win"', isset($context['auto_whois_server']) && $context['auto_whois_server']['name'] == $server['name'] ? ' style="font-weight: bold;"' : '', '>', $server['name'], '</a><br />';
			echo '
				</div>
			</div>';
	}

	// The second table lists all the members who have been logged as using this IP address.
	echo '
		<div class="title_bar">
			<h3 class="titlebg">', $txt['members_from_ip'], ' ', $context['ip'], '</h3>
		</div>';
	if (empty($context['ips']))
		echo '
		<p class="windowbg2 description"><em>', $txt['no_members_from_ip'], '</em></p>';
	else
	{
		echo '
		<table class="table_grid">
			<thead>
				<tr class="catbg">
					<th class="first_th" scope="col">', $txt['ip_address'], '</th>
					<th class="last_th" scope="col">', $txt['display_name'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Loop through each of the members and display them.
		foreach ($context['ips'] as $ip => $memberlist)
			echo '
				<tr>
					<td class="windowbg2"><a href="', $context['base_url'], ';searchip=', $ip, '">', $ip, '</a></td>
					<td class="windowbg2">', implode(', ', $memberlist), '</td>
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