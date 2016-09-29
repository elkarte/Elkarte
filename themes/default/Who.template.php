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
 * @version 1.1 beta 3
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
			<div class="selectbox floatright">
				<label for="show_top">' . $txt['who_show1'] . '</label>
				<select name="show_top" id="show_top" onchange="document.forms.whoFilter.show.value = this.value; document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		$extra .= '
					<option value="' . $value . '" ' . ($value == $context['show_by'] ? ' selected="selected"' : '') . '>' . $label . '</option>';

	$extra .= '
				</select>
				<noscript>
					<input type="submit" name="submit_top" value="' . $txt['go'] . '" />
				</noscript>
			</div>';

	template_pagesection(false, '', array('extra' => $extra));
}

/**
 * Who's online page.
 */
function template_whos_online()
{
	global $context, $scripturl, $txt;

	echo '
			<div id="mlist">
				<dl class="whos_online', empty($context['members']) ? ' no_members' : '', '">
					<dt class="table_head">
						<div class="online_member">
							<a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=user', $context['sort_direction'] != 'down' && $context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', $txt['who_user'], $context['sort_by'] == 'user' ? '<i class="icon i-sort-alpha-' . $context['sort_direction'] . ' icon-small"></i>' : '', '</a>
						</div>
						<div class="online_time">
							<a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=time', $context['sort_direction'] == 'down' && $context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', $txt['who_time'], $context['sort_by'] == 'time' ? '<i class="icon i-sort-numeric-' . $context['sort_direction'] . ' icon-small"></i>' : '', '</a>
						</div>
						<div class="online_action">', $txt['who_action'], '</div>
					</dt>';

	// For every member display their name, time and action (and more for admin).
	foreach ($context['members'] as $member)
	{
		echo '
					<dd class="online_row">
						<div class="online_member">
							<span class="member', $member['is_hidden'] ? ' hidden' : '', '">
								', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . $txt['profile_of'] . ' ' . $member['name'] . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . '"') . '>' . $member['name'] . '</a>', '
							</span>';

		if (!empty($member['ip']))
			echo '
							<a class="track_ip" href="' . $scripturl . '?action=', ($member['is_guest'] ? 'trackip' : 'profile;area=history;sa=ip;u=' . $member['id']), ';searchip=' . $member['ip'] . '">' . $member['ip'] . '</a>';

		echo '
						</div>
						<div class="online_time nowrap">', $member['time'], '</div>
						<div class="online_action">', $member['action'], '</div>
					</dd>';
	}

	echo '
				</dl>';

	// No members?
	if (empty($context['members']))
		echo '
				<div class="well centertext">
					', $txt['who_no_online_' . ($context['show_by'] == 'guests' || $context['show_by'] == 'spiders' ? $context['show_by'] : 'members')], '
				</div>';

	echo '
			</div>';
}

/**
 * Close up the who's online page
 */
function template_whos_selection_below()
{
	global $context, $txt;

	$extra = '
			<div class="selectbox floatright"><label for="show">' . $txt['who_show1'] . '</label>
				<select name="show" id="show" onchange="document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		$extra .= '
					<option value="' . $value . '" ' . ($value == $context['show_by'] ? ' selected="selected"' : '') . '>' . $label . '</option>';

	$extra .= '
				</select>
				<noscript>
					<input type="submit" name="submit_top" value="' . $txt['go'] . '" />
				</noscript>
			</div>';

	template_pagesection(false, '', array('extra' => $extra));

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
		<div class="content">
			', $section['pretext'], '
		</div>';

		if (isset($section['title']))
			echo '
			<h2 class="category_header">', $section['title'], '</h2>';

		echo '
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
			<p><em>', $section['posttext'], '</em></p>';

		echo '
		</div>';
	}

	// Other software and graphics
	if (!empty($context['credits_software_graphics']))
	{
		echo '
		<h2 class="category_header">', $txt['credits_software_graphics'], '</h2>
		<div class="content">';

		foreach ($context['credits_software_graphics'] as $section => $credits)
			echo '
			<dl>
				<dt>
					<strong>', $txt['credits_' . $section], '</strong>
				</dt>
				<dd>', implode('</dd><dd>', $credits), '</dd>
			</dl>';

		echo '
		</div>';
	}

	// Addons credits, copyright, license
	if (!empty($context['credits_addons']))
	{
		echo '
		<h2 class="category_header">', $txt['credits_addons'], '</h2>
		<div class="content">';

		echo '
			<dl>
				<dt>
					<strong>', $txt['credits_addons'], '</strong>
				</dt>
				<dd>', implode('</dd><dd>', $context['credits_addons']), '</dd>
			</dl>';

		echo '
		</div>';
	}

	// ElkArte !
	echo '
		<h2 class="category_header">', $txt['credits_copyright'], '</h2>
		<div class="content">
			<dl>
				<dt>
					<strong>', $txt['credits_forum'], '</strong>
				</dt>
				<dd>', $context['copyrights']['elkarte'];

	echo '
				</dd>
			</dl>';

	if (!empty($context['copyrights']['addons']))
	{
		echo '
			<dl>
				<dt>
					<strong>', $txt['credits_addons'], '</strong>
				</dt>
				<dd>', implode('</dd><dd>', $context['copyrights']['addons']), '</dd>
			</dl>';
	}

	echo '
		</div>
	</div>';
}
