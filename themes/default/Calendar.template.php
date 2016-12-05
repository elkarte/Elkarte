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
 * Start the calendar
 */
function template_Calendar_init()
{
	loadTemplate('GenericHelpers');
}

/**
 * The main calendar - January, for example.
 */
function template_show_calendar()
{
	global $context, $txt, $scripturl;

	echo '
		<div id="calendar">
			<div id="month_grid">
				', template_show_month_grid('prev'), '
				', template_show_month_grid('current'), '
				', template_show_month_grid('next'), '
			</div>
			<div id="main_grid">
				', $context['view_week'] ? template_show_week_grid('main') : template_show_month_grid('main');

	// Show some controls to allow easy calendar navigation.
	echo '
				<form id="calendar_navigation" action="', $scripturl, '?action=calendar" method="post" accept-charset="UTF-8">';

	template_button_strip($context['calendar_buttons'], 'right');

	echo '
					<select name="month">';

	// Show a select box with all the months.
	foreach ($txt['months'] as $number => $month)
		echo '
						<option value="', $number, '"', $number == $context['current_month'] ? ' selected="selected"' : '', '>', $month, '</option>';

	echo '
					</select>
					<select name="year">';

	// Show a link for every year.....
	for ($year = $context['cal_minyear']; $year <= $context['cal_maxyear']; $year++)
		echo '
						<option value="', $year, '"', $year == $context['current_year'] ? ' selected="selected"' : '', '>', $year, '</option>';

	echo '
					</select>
					<input type="submit" value="', $txt['view'], '" />
				</form>
			</div>
		</div>';
}

/**
 * Template for posting a calendar event.
 */
function template_unlinked_event_post()
{
	global $context, $txt, $scripturl, $modSettings;

	// Start the javascript for drop down boxes...
	echo '
		<form action="', $scripturl, '?action=calendar;sa=post" method="post" name="postevent" accept-charset="UTF-8" onsubmit="submitonce(this);smc_saveEntities(\'postevent\', [\'evtitle\']);">';

	if (!empty($context['event']['new']))
		echo '
			<input type="hidden" name="eventid" value="', $context['event']['eventid'], '" />';

	// Start the main table.
	echo '
			<div id="post_event">
				<h2 class="category_header">', $context['page_title'], '</h2>';

	if (!empty($context['post_error']['messages']))
	{
		echo '
				<div class="errorbox">
					<dl class="event_error">
						<dt>
							', $context['error_type'] == 'serious' ? '<strong>' . $txt['error_while_submitting'] . '</strong>' : '', '
						</dt>
						<dt class="error">
							', implode('<br />', $context['post_error']['messages']), '
						</dt>
					</dl>
				</div>';
	}

	echo '
				<div id="event_main" class="well">
					<label for="evtitle"', isset($context['post_error']['no_event']) ? ' class="error"' : '', ' id="caption_evtitle">', $txt['calendar_event_title'], '</label>
					<input type="text" id="evtitle" name="evtitle" maxlength="255" size="55" value="', $context['event']['title'], '" tabindex="', $context['tabindex']++, '" class="input_text" />
					<div id="datepicker">
						<input type="hidden" name="calendar" value="1" /><label for="year">', $txt['calendar_year'], '</label>
						<select name="year" id="year" tabindex="', $context['tabindex']++, '" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = $context['cal_minyear']; $year <= $context['cal_maxyear']; $year++)
		echo '
							<option value="', $year, '"', $year == $context['event']['year'] ? ' selected="selected"' : '', '>', $year, '</option>';

	echo '
						</select>
						<label for="month">', $txt['calendar_month'], '</label>
						<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == $context['event']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '</option>';

	echo '
						</select>
						<label for="day">', $txt['calendar_day'], '</label>
						<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['event']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == $context['event']['day'] ? ' selected="selected"' : '', '>', $day, '</option>';

	echo '
						</select>
					</div>';

	if (!empty($modSettings['cal_allowspan']) || $context['event']['new'])
		echo '
					<ul class="event_options">';

	// If events can span more than one day then allow the user to select how long it should last.
	if (!empty($modSettings['cal_allowspan']))
	{
		echo '
						<li>
							<label for="span">', $txt['calendar_numb_days'], '</label>
							<select id="span" name="span">';

		for ($days = 1; $days <= $modSettings['cal_maxspan']; $days++)
			echo '
								<option value="', $days, '"', $context['event']['span'] == $days ? ' selected="selected"' : '', '>', $days, '</option>';

		echo '
							</select>
						</li>';
	}

	// If this is a new event let the user specify which board they want the linked post to be put into.
	if ($context['event']['new'])
	{
		echo '
						<li>
							<label for="link_to_board">', $txt['calendar_link_event'], '</label>
							<input type="checkbox" id="link_to_board" name="link_to_board" checked="checked" onclick="toggleLinked(this.form);" />
						</li>
						<li>
							', template_select_boards('board', $txt['calendar_post_in'], 'onchange="this.form.submit();"'), '
						</li>';
	}

	if (!empty($modSettings['cal_allowspan']) || $context['event']['new'])
		echo '
					</ul>';

	echo '
					<div class="submitbutton">
						<input type="submit" value="', empty($context['event']['new']) ? $txt['save'] : $txt['post'], '" />';

	// Delete button?
	if (empty($context['event']['new']))
		echo '
						<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" onclick="return confirm(\'', $txt['calendar_confirm_delete'], '\');" />';

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="eventid" value="', $context['event']['eventid'], '" />
					</div>
				</div>
			</div>
		</form>';
}

/**
 * Display a monthly calendar grid.
 *
 * @param string $grid_name
 */
function template_show_month_grid($grid_name)
{
	global $context, $txt, $scripturl, $modSettings;

	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	$calendar_data = &$context['calendar_grid_' . $grid_name];

	if (empty($calendar_data['disable_title']))
	{
		echo '
				<h2 class="category_header">';

		if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'])
			echo '
					<a href="', $calendar_data['previous_calendar']['href'], '" class="previous_month">
						<i class="icon icon-lg i-chevron-circle-left"></i>
					</a>';

		if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'])
			echo '
					<a href="', $calendar_data['next_calendar']['href'], '" class="next_month">
						<i class="icon icon-lg i-chevron-circle-right"></i>
					</a>';

		if ($calendar_data['show_next_prev'])
			echo '
					', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'];
		else
			echo '
					<a href="', $scripturl, '?action=calendar;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], '">
						<i class="icon icon-small i-calendar"></i> ', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'], '
					</a>';

		echo '
				</h2>';
	}

	// Show the sidebar months
	echo '
				<table class="calendar_table">';

	// Show each day of the week.
	if (empty($calendar_data['disable_day_titles']))
	{
		echo '
					<tr class="table_head">';

		if (!empty($calendar_data['show_week_links']))
			echo '
						<th>&nbsp;</th>';

		foreach ($calendar_data['week_days'] as $day)
			echo '
						<th scope="col" class="days">', !empty($calendar_data['short_day_titles']) ? (Util::substr($txt['days'][$day], 0, 1)) : $txt['days'][$day], '</th>';

		echo '
					</tr>';
	}

	// Each week in weeks contains the following:
	// days (a list of days), number (week # in the year.)
	foreach ($calendar_data['weeks'] as $week)
	{
		echo '
					<tr>';

		if (!empty($calendar_data['show_week_links']))
			echo '
						<td class="weeks">
							<a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $week['days'][0]['day'], '">
								<i class="icon i-eye-plus"></i>
							</a>
						</td>';

		// Every day has the following:
		// day (# in month), is_today (is this day *today*?), is_first_day (first day of the week?),
		// holidays, events, birthdays. (last three are lists.)
		foreach ($week['days'] as $day)
		{
			// If this is today, make it a different color and show a border.
			echo '
						<td class="', $day['is_today'] ? 'calendar_today' : '', ' days">';

			// Skip it if it should be blank - it's not a day if it has no number.
			if (!empty($day['day']))
			{
				// Should the day number be a link?
				if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
					echo '
							<a href="', $scripturl, '?action=calendar;sa=post;month=', $calendar_data['current_month'], ';year=', $calendar_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $day['day'], '</a>';
				else
					echo '
							', $day['day'];

				// Is this the first day of the week? (and are we showing week numbers?)
				if ($day['is_first_day'] && $calendar_data['size'] != 'small')
					echo ' - <a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $day['day'], '">', $txt['calendar_week'], ' ', $week['number'], '</a>';

				// Are there any holidays?
				if (!empty($day['holidays']))
					echo '
							<div class="holiday">', $txt['calendar_prompt'], ' ', implode(', ', $day['holidays']), '</div>';

				// Show any birthdays...
				if (!empty($day['birthdays']))
				{
					echo '
							<div>
								<span class="birthday">', $txt['birthdays'], '</span>';

					// Each of the birthdays has:
					// id, name (person), age (if they have one set?), and is_last. (last in list?)
					$use_js_hide = empty($context['show_all_birthdays']) && count($day['birthdays']) > 10;
					$count = 0;
					foreach ($day['birthdays'] as $member)
					{
						echo '
									<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] || ($count == 10 && $use_js_hide) ? '' : ', ';

						// Stop at ten?
						if ($count == 10 && $use_js_hide)
							echo '
									<span class="hidelink" id="bdhidelink_', $day['day'], '">...<br />
										<a href="', $scripturl, '?action=calendar;month=', $calendar_data['current_month'], ';year=', $calendar_data['current_year'], ';showbd" onclick="document.getElementById(\'bdhide_', $day['day'], '\').style.display = \'block\'; document.getElementById(\'bdhidelink_', $day['day'], '\').style.display = \'none\'; return false;">(', sprintf($txt['calendar_click_all'], count($day['birthdays'])), ')</a>
									</span>
									<span id="bdhide_', $day['day'], '" class="hide">, ';

						$count++;
					}

					if ($use_js_hide)
						echo '
								</span>';

					echo '
							</div>';
				}

				// Any special posted events?
				if (!empty($day['events']))
				{
					echo '
							<div class="lefttext">
								<span class="event">', $txt['events'], '</span><br />';

					// The events are made up of:
					// title, href, is_last, can_edit (are they allowed to?), and modify_href.
					foreach ($day['events'] as $event)
					{
						// If they can edit the event, show an icon they can click on....
						if ($event['can_edit'])
							echo '
								<a class="modify_event" href="', $event['modify_href'], '">
									<i class="icon i-modify" title="' . $txt['modify'] . '"></i>
								</a>';

						if ($event['can_export'])
							echo '
								<a class="modify_event" href="', $event['export_href'], '">
									<i class="icon i-download" title="' . $txt['save'] . '"></i>
								</a>';

						echo '
								', $event['link'], $event['is_last'] ? '' : '<br />';
					}

					echo '
							</div>';
				}
			}

			echo '
						</td>';
		}

		echo '
					</tr>';
	}

	echo '
				</table>';
}

/**
 * Or show a weekly one?
 *
 * @param string $grid_name
 */
function template_show_week_grid($grid_name)
{
	global $context, $txt, $scripturl, $modSettings;

	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	$calendar_data = &$context['calendar_grid_' . $grid_name];
	$done_title = false;

	// Loop through each month (At least one) and print out each day.
	foreach ($calendar_data['months'] as $month_data)
	{
		echo '
				<h2 class="category_header">';

		if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'] && empty($done_title))
			echo '
					<span class="previous_month">
						<a href="', $calendar_data['previous_week']['href'], '">
							<i class="icon icon-lg i-chevron-circle-left"></i>
						</a>
					</span>';

		if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'] && empty($done_title))
			echo '
					<span class="next_month">
						<a href="', $calendar_data['next_week']['href'], '">
							<i class="icon icon-lg i-chevron-circle-right"></i>
						</a>
					</span>';

		echo '
					<a href="', $scripturl, '?action=calendar;month=', $month_data['current_month'], ';year=', $month_data['current_year'], '">', $txt['months_titles'][$month_data['current_month']], ' ', $month_data['current_year'], '</a>', empty($done_title) && !empty($calendar_data['week_number']) ? (' - ' . $txt['calendar_week'] . ' ' . $calendar_data['week_number']) : '', '
				</h2>';

		$done_title = true;

		echo '
				<ul class="weeklist">';

		foreach ($month_data['days'] as $day)
		{
			echo '
					<li>
						<h4>';

			// Should the day number be a link?
			if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
				echo '
							<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['days'][$day['day_of_week']], ' - ', $day['day'], '</a>';
			else
				echo '
							', $txt['days'][$day['day_of_week']], ' - ', $day['day'];

			echo '
						</h4>
						<div class="', $day['is_today'] ? 'calendar_today' : '', ' weekdays">';

			// Are there any holidays?
			if (!empty($day['holidays']))
				echo '
							<div class="smalltext holiday">', $txt['calendar_prompt'], ' ', implode(', ', $day['holidays']), '</div>';

			// Show any birthdays...
			if (!empty($day['birthdays']))
			{
				echo '
							<div class="smalltext">
								<span class="birthday">', $txt['birthdays'], '</span>';

				// Each of the birthdays has:
				// id, name (person), age (if they have one set?), and is_last. (last in list?)
				foreach ($day['birthdays'] as $member)
					echo '
								<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '' : ', ';

				echo '
							</div>';
			}

			// Any special posted events?
			if (!empty($day['events']))
			{
				echo '
							<div class="smalltext">
								<span class="event">', $txt['events'], '</span>';

				// The events are made up of:
				// title, href, is_last, can_edit (are they allowed to?), and modify_href.
				foreach ($day['events'] as $event)
				{
					// If they can edit the event, show a star they can click on....
					if ($event['can_edit'])
						echo '
								<a href="', $event['modify_href'], '">
									<i class="icon i-modify" title="' . $txt['modify'] . '"></i>
								</a> ';

					echo '
								', $event['link'], $event['is_last'] ? '' : ', ';
				}

				echo '
							</div>';
			}

			echo '
						</div>
					</li>';
		}

		echo '
				</ul>';
	}
}