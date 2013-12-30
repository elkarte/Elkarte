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
 * @version 1.0 Beta
 *
 */

/**
 * Template to display the who's online table header
 */
function template_whos_selection_above()
{
	global $context, $scripturl, $txt;

	// Display the table header and linktree.
	echo '
	<div id="whos_online">
		<form action="', $scripturl, '?action=who" method="post" id="whoFilter" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['who_title'], '</h2>';

	$extra = '
				<div class="selectbox floatright">' . $txt['who_show1'] . '
					<select name="show_top" onchange="document.forms.whoFilter.show.value = this.value; document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		$extra .= '
						<option value="' . $value . '" ' . ($value == $context['show_by'] ? ' selected="selected"' : '') . '>' . $label . '</option>';
	$extra .= '
					</select>
					<noscript>
						<input type="submit" name="submit_top" value="' . $txt['go'] . '" class="button_submit submitgo" />
					</noscript>
				</div>';

	template_pagesection(false, false, array('extra' => $extra));
}

/**
 * Who's online page.
 */
function template_whos_online()
{
	global $context, $settings, $scripturl, $txt;

	echo '
			<div class="topic_table" id="mlist">
				<table class="table_grid" >
					<thead>
						<tr class="table_head">
							<th scope="col" class="lefttext" style="width:40%">
								<a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=user', $context['sort_direction'] != 'down' && $context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', $txt['who_user'], $context['sort_by'] == 'user' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>
							<th scope="col" class="lefttext" style="width:10%">
								<a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=time', $context['sort_direction'] == 'down' && $context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', $txt['who_time'], $context['sort_by'] == 'time' ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '', '</a>
							</th>
							<th scope="col" class="lefttext" style="width:50%">', $txt['who_action'], '</th>
						</tr>
					</thead>
					<tbody>';

	// For every member display their name, time and action (and more for admin).
	$alternate = 0;

	foreach ($context['members'] as $member)
	{
		// $alternate will either be true or false. If it's true, use "windowbg2" and otherwise use "windowbg".
		echo '
						<tr class="windowbg', $alternate ? '2' : '', '">
							<td>
								<span class="member', $member['is_hidden'] ? ' hidden' : '', '">
									', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . $txt['profile_of'] . ' ' . $member['name'] . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . '"') . '>' . $member['name'] . '</a>', '
								</span>';

		if (!empty($member['ip']))
			echo '
								(<a href="' . $scripturl . '?action=', ($member['is_guest'] ? 'trackip' : 'profile;area=history;sa=ip;u=' . $member['id']), ';searchip=' . $member['ip'] . '">' . $member['ip'] . '</a>)';

		echo '
							</td>
							<td nowrap="nowrap">', $member['time'], '</td>
							<td>', $member['action'], '</td>
						</tr>';

		// Switch alternate to whatever it wasn't this time. (true -> false -> true -> false, etc.)
		$alternate = !$alternate;
	}

	// No members?
	if (empty($context['members']))
	{
		echo '
						<tr class="windowbg2">
							<td colspan="3" class="centertext">
							', $txt['who_no_online_' . ($context['show_by'] == 'guests' || $context['show_by'] == 'spiders' ? $context['show_by'] : 'members')], '
							</td>
						</tr>';
	}

	echo '
					</tbody>
				</table>
			</div>';
}

/**
 * Close up the who's online page
 */
function template_whos_selection_below()
{
	global $context, $txt;

	$extra_bottom = '
			<div class="selectbox floatright">' . $txt['who_show1'] . '
				<select name="show" onchange="document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		$extra_bottom .= '
					<option value="' . $value . '" ' . ($value == $context['show_by'] ? ' selected="selected"' : '') . '>' . $label . '</option>';
	$extra_bottom .= '
				</select>
				<noscript>
					<input type="submit" name="submit_top" value="' . $txt['go'] . '" class="button_submit submitgo" />
				</noscript>
			</div>';

	template_pagesection(false, false, array('extra' => $extra_bottom));

	echo '
		</form>
	</div>';
}

/**
 * Display the credits page.
 */
function template_credits()
{
	global $context, $txt;

	// The most important part - the credits :P.
	echo '
	<div id="credits">
		<h2 class="category_header">', $txt['credits'], '</h2>';

	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
			echo '
		<div class="windowbg">
			<div class="content">
				<p>', $section['pretext'], '</p>
			</div>
		</div>';

		if (isset($section['title']))
			echo '
			<h3 class="category_header">', $section['title'], '</h3>';

		echo '
		<div class="windowbg2">
			<div class="content">
				<dl>';

		foreach ($section['groups'] as $group)
		{
			if (isset($group['title']))
				echo '
					<dt>
						<strong>', $group['title'], '</strong>
					</dt>
					<dd>';

			// Try to make this read nicely.
			if (count($group['members']) <= 2)
				echo implode(' ' . $txt['credits_and'] . ' ', $group['members']);
			else
			{
				$last_peep = array_pop($group['members']);
				echo implode(', ', $group['members']), ' ', $txt['credits_and'], ' ', $last_peep;
			}

			echo '
					</dd>';
		}

		echo '
				</dl>';

		if (isset($section['posttext']))
			echo '
				<p class="posttext">', $section['posttext'], '</p>';

		echo '
			</div>
		</div>';
	}

	// Other software and graphics
	if (!empty($context['credits_software_graphics']))
	{
		echo '
		<h3 class="category_header">', $txt['credits_software_graphics'], '</h3>
		<div class="windowbg">
			<div class="content">';

		foreach ($context['credits_software_graphics'] as $section => $credits)
			echo '
				<dl>
					<dt><strong>', $txt['credits_' . $section], '</strong></dt>
					<dd>', implode('</dd><dd>', $credits), '</dd>
				</dl>';

		echo '
			</div>
		</div>';
	}

	// Addons credits, copyright, license
	if (!empty($context['credits_addons']))
	{
		echo '
		<h3 class="category_header">', $txt['credits_addons'], '</h3>
		<div class="windowbg">
			<div class="content">';

		echo '
				<dl>
					<dt><strong>', $txt['credits_addons'], '</strong></dt>
					<dd>', implode('</dd><dd>', $context['credits_addons']), '</dd>
				</dl>';

		echo '
			</div>
		</div>';
	}

	// ElkArte !
	echo '
		<h3 class="category_header">', $txt['credits_copyright'], '</h3>
		<div class="windowbg">
			<div class="content">
				<dl>
					<dt><strong>', $txt['credits_forum'], '</strong></dt>', '
					<dd>', $context['copyrights']['elkarte'];

	echo '
					</dd>
				</dl>';

	if (!empty($context['copyrights']['addons']))
	{
		echo '
				<dl>
					<dt><strong>', $txt['credits_addons'], '</strong></dt>
					<dd>', implode('</dd><dd>', $context['copyrights']['addons']), '</dd>
				</dl>';
	}

	echo '
			</div>
		</div>
	</div>';
}