<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Util;

/**
 * Stats page.
 */
function template_statistics()
{
	global $context;

	echo '
	<div id="statistics" class="forum_category">
		<h2 class="category_header">
			', $context['page_title'], '
		</h2>
		<ul class="statistics">';

	foreach ($context['statistics_callbacks'] as $callback)
	{
		$function = 'template_' . $callback;
		$function();
	}

	echo '
		</ul>
	</div>';

	template_forum_chart();
}

/**
 * Used to show the general statistics blocks
 */
function template_general_statistics()
{
	global $context, $settings, $txt;

	// These two are special formatting strings for special elements of the statistics:
	// The most_online value is an array composed of two elements: number and date,
	// they will be replaced in the foreach below with the corresponding values.
	// If you want to change the way to present the field, change this string,
	// for example if you want to show it as: "123 members on the 20/01/2010" you could use:
	// $settings['most_online'] = 'number members on the date';
	$settings['most_online'] = 'number - date';

	// Similarly to the previous one, this is a "template" for the latest_member stats
	// The elements available to style this entry are: id, name, href, link.
	// So, if you want to change it to the plain username you could use:
	// $settings['latest_member'] = 'name';
	$settings['latest_member'] = 'link';

	echo '
			<li class="flow_hidden" id="top_row">
				<h2 class="category_header hdicon cat_img_stats_info">
					', $txt['general_stats'], '
				</h2>
				<dl class="stats floatleft">';

	foreach ($context['general_statistics']['left'] as $key => $value)
	{
		if (is_array($value))
		{
			if (!isset($settings[$key]))
			{
				continue;
			}

			$value = strtr($settings[$key], $value);
		}

		echo '
					<dt>', $txt[$key], ':</dt>
					<dd>', $value, '</dd>';
	}

	echo '
				</dl>
				<dl class="stats">';

	foreach ($context['general_statistics']['right'] as $key => $value)
	{
		if (is_array($value))
		{
			if (!isset($settings[$key]))
			{
				continue;
			}

			$value = strtr($settings[$key], $value);
		}

		echo '
					<dt>', $txt[$key], ':</dt>
					<dd>', $value, '</dd>';
	}

	echo '
				</dl>
			</li>';
}

/**
 * Shows "top" statistics horizontal bar chart, like top posters, top boards, top replies, etc
 */
function template_top_statistics()
{
	global $context, $txt;

	echo '
			<script>
				Chart.defaults.font.family = \'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Droid Sans", "Helvetica Neue", "Trebuchet MS", Arial, sans-serif\';
				Chart.defaults.font.lineHeight = .5;
			</script>';

	// Top Posters
	[$data, $labels, $tooltips] = getChartData($context['top']['posters'], 'num_posts');
	echo '
			<li class="flow_hidden">
				<h2 class="category_header floatleft hdicon cat_img_star">
					', $txt['top_posters'], '
				</h2>
				<div class="stats floatleft">
					<canvas id="topPoster"></canvas>
				</div>';

	showBarChart("topPoster", $data, $labels, $tooltips);

	// Top Boards
	[$data, $labels, $tooltips] = getChartData($context['top']['boards'], 'num_posts');
	echo '
				<h2 class="category_header hdicon cat_img_topics">
					', $txt['top_boards'], '
				</h2>
				<div class="stats">
					<canvas id="topBoards"></canvas>
				</div>
			</li>';

	showBarChart("topBoards", $data, $labels, $tooltips);

	// Top Replies
	[$data, $labels, $tooltips] = getChartData($context['top']['topics_replies'], 'num_replies');
	echo '
			<li class="flow_hidden">
				<h2 class="category_header floatleft hdicon cat_img_talk">
					', $txt['top_topics_replies'], '
				</h2>
				<div class="stats floatleft">
					<canvas id="topReplies"></canvas>
				</div>';

	showBarChart("topReplies", $data, $labels, $tooltips);

	// Top Views
	[$data, $labels, $tooltips] = getChartData($context['top']['topics_views'], 'num_views');
	echo '
				<h2 class="category_header hdicon cat_img_eye">
					', $txt['top_topics_views'], '
				</h2>
				<div class="stats">
					<canvas id="topViews"></canvas>
				</div>
			</li>';

	showBarChart("topViews", $data, $labels, $tooltips);

	// Top Starters
	[$data, $labels, $tooltips] = getChartData($context['top']['starters'], 'num_topics');

	echo '
			<li class="flow_hidden">
				<h2 class="category_header floatleft hdicon cat_img_write">
					', $txt['top_starters'], '
				</h2>
				<div class="stats floatleft">
					<canvas id="topStarters"></canvas>
				</div>';

	showBarChart("topStarters", $data, $labels, $tooltips);

	// Top Time Online
	[$data, $labels, $tooltips] = getChartData($context['top']['time_online'], 'time_online', true);
	echo '
				<h2 class="category_header hdicon cat_img_clock">
					', $txt['most_time_online'], '
				</h2>
				<div class="stats">
					<canvas id="topOnline"></canvas>
				</div>
			</li>';

	showBarChart("topOnline", $data, $labels, $tooltips);
}

/**
 * Shows the forum history in a nice interactive chart
 */
function template_forum_chart()
{
	global $context, $txt, $modSettings;

	// Type of stat views available above the chart
	echo '
	<div id="forum_history" class="forum_category">
		<h2 class="category_header hdicon cat_img_piechart">
			', $txt['yearly_summary'], '
		</h2>
		<div class="flow_hidden">
			<br />
			<div class="buttonlist">
				<button class="stats_button linklevel1" data-title="new_topics">', $txt['stats_new_topics'], '</button>
				<button class="stats_button linklevel1" data-title="new_posts">', $txt['stats_new_posts'], '</button>
				<button class="stats_button linklevel1" data-title="new_members">', $txt['stats_new_members'], '</button>
				<button class="stats_button linklevel1" data-title="most_members_online">', $txt['most_online'], '</button>',
				(!empty($modSettings['hitStats']) ? '
				<button class="stats_button linklevel1" data-title="hits">' . $txt['page_views'] . '</button>' : ''), '
			</div>	
			<canvas id="yearStats" height="200" style="width:80%"></canvas>';

	// Generate all the data and place it in JS constants
	setYearData();

	// Place the initial chart up, all years, new topics
	showLineChart('new_topics');

	// Year buttons below the chart
	echo '
			<br />
			<div class="buttonlist">
				<button class="stats_button linklevel1" data-year="all">', $txt['all'], '</button>';

	foreach (array_reverse($context['yearly']) as $year)
	{
		echo '
				<button class="stats_button linklevel1" data-year="', $year['year'], '">', $year['year'], '</button>';
	}

	echo '
			</div>
			<br />
		</div>
	</div>';

	// Activate the above type and year buttons, it will look for .stats_button class items
	echo '
	<script>
		setYearClickEvents();
	</script>';
}

/**
 * Shows the forum history, year/month breakdown of activity such as topics, posts, members, etc
 */
function template_forum_history()
{
	global $context, $settings, $txt, $modSettings;

	// Onto the standard tabulated data
	echo '
	<div id="forum_history" class="forum_category">
		<h2 class="category_header hdicon cat_img_clock">
			', $txt['forum_history'], '
		</h2>
		<div class="flow_hidden">';

	if (!empty($context['yearly']))
	{
		echo '
			<table class="table_grid" id="stats">
				<thead>
					<tr>
						<th class="history_head lefttext">', $txt['yearly_summary'], '</th>
						<th class="history_head">', $txt['stats_new_topics'], '</th>
						<th class="history_head">', $txt['stats_new_posts'], '</th>
						<th class="history_head">', $txt['stats_new_members'], '</th>
						<th class="history_head">', $txt['most_online'], '</th>';

		if (!empty($modSettings['hitStats']))
		{
			echo '
						<th class="history_head">', $txt['page_views'], '</th>';
		}

		echo '
					</tr>
				</thead>
				<tbody>';

		foreach ($context['yearly'] as $id => $year)
		{
			echo '
					<tr id="year_', $id, '">
						<th class="stats_year lefttext">
							<img id="year_img_', $id, '" src="', $settings['images_url'], '/selected_open.png" alt="*" /> <a href="#year_', $id, '" id="year_link_', $id, '">', $year['year'], '</a>
						</th>
						<th>', $year['new_topics'], '</th>
						<th>', $year['new_posts'], '</th>
						<th>', $year['new_members'], '</th>
						<th>', $year['most_members_online'], '</th>';

			if (!empty($modSettings['hitStats']))
			{
				echo '
						<th>', $year['hits'], '</th>';
			}

			echo '
					</tr>';

			foreach ($year['months'] as $month)
			{
				echo '
					<tr id="tr_month_', $month['id'], '">
						<th class="stats_month lefttext">
							<img src="', $settings['images_url'], '/', $month['expanded'] ? 'selected_open.png' : 'selected.png', '" alt="" id="img_', $month['id'], '" /> <a id="m', $month['id'], '" href="', $month['href'], '">', $month['month'], ' ', $month['year'], '</a>
						</th>
						<th>', $month['new_topics'], '</th>
						<th>', $month['new_posts'], '</th>
						<th>', $month['new_members'], '</th>
						<th>', $month['most_members_online'], '</th>';

				if (!empty($modSettings['hitStats']))
				{
					echo '
						<th>', $month['hits'], '</th>';
				}

				echo '
					</tr>';

				if ($month['expanded'])
				{
					foreach ($month['days'] as $day)
					{
						echo '
					<tr id="tr_day_', $day['year'], '-', $day['month'], '-', $day['day'], '">
						<td class="stats_day lefttext">', $day['year'], '-', $day['month'], '-', $day['day'], '</td>
						<td>', $day['new_topics'], '</td>
						<td>', $day['new_posts'], '</td>
						<td>', $day['new_members'], '</td>
						<td>', $day['most_members_online'], '</td>';

						if (!empty($modSettings['hitStats']))
						{
							echo '
						<td>', $day['hits'], '</td>';
						}

						echo '
					</tr>';
					}
				}
			}
		}

		echo '
				</tbody>
			</table>
		</div>
	</div>
	<script>
		let oStatsCenter = new elk_StatsCenter({
			sTableId: \'stats\',

			reYearPattern: /year_(\d+)/,
			sYearImageCollapsed: \'selected.png\',
			sYearImageExpanded: \'selected_open.png\',
			sYearImageIdPrefix: \'year_img_\',
			sYearLinkIdPrefix: \'year_link_\',

			reMonthPattern: /tr_month_(\d+)/,
			sMonthImageCollapsed: \'selected.png\',
			sMonthImageExpanded: \'selected_open.png\',
			sMonthImageIdPrefix: \'img_\',
			sMonthLinkIdPrefix: \'m\',

			reDayPattern: /tr_day_(\d+-\d+-\d+)/,
			sDayRowClassname: \'\',
			sDayRowIdPrefix: \'tr_day_\',

			aCollapsedYears: [';

		foreach ($context['collapsed_years'] as $id => $year)
		{
			echo '
				\'', $year, '\'', $id !== count($context['collapsed_years']) - 1 ? ',' : '';
		}

		echo '
			],

			aDataCells: [
				\'date\',
				\'new_topics\',
				\'new_posts\',
				\'new_members\',
				\'most_members_online\'', empty($modSettings['hitStats']) ? '' : ',
				\'hits\'', '
			]
		});
	</script>';
	}
}

/**
 * Given a set of data, labels, and tooltips, builds a chart.js BAR config
 *
 * @param string $id the name of the canvas
 * @param array $data the data to plot
 * @param array $labels the labels to associate with the data
 * @param array $tooltips what to show when you hover over the data
 */
function showBarChart($id, $data, $labels, $tooltips)
{
	// The use of var and not let, is intentional as we call this multiple times.
	echo '
	<script>
		var bar_ctx' . $id . ' = document.getElementById("', $id, '").getContext("2d"),
			background = bar_ctx' . $id . '.createLinearGradient(0, 0, 600, 0);
		
		// Right to left fade on canvas
		background.addColorStop(1, "#60BC78");
		background.addColorStop(0, "#27a348");
		
		// Set these vars for easy use in the config object
		var labels = [', implode(',', $labels), '],
			tooltips = [', implode(',', $tooltips), '],
			bar_data = {
				labels: labels,
				datasets: [{
					data: [', implode(',', $data), '],
					backgroundColor: background,
				}]
			};
		
		new Chart(bar_ctx' . $id . ', barConfig(bar_data, tooltips));
	</script>';
}

/**
 * Helper function.  Given a dataset, parses it to data, labels and tooltips
 * for consumption by chart.js
 *
 * @param array $stats
 * @param string $num
 * @return array[]
 */
function getChartData($stats, $num = 'num_posts', $usePercent = false)
{
	$labels = [];
	$data = [];
	$tooltips = [];

	foreach ($stats as $value)
	{
		if ($usePercent)
		{
			$data[] = !empty($value['percent']) ? $value['percent'] : '0';
		}
		else
		{
			$data[] = !empty($value[$num]) ? removeComma($value[$num]) : '0';
		}

		$labels[] = "'" . Util::shorten_text(($value['name'] ?? strip_tags($value['link'])), 26, true, '...', true, 0) . "'";
		$tooltips[] = "'" . $value[$num] . "'";
	}

	return [$data, $labels, $tooltips];
}

/**
 * Generates JS constant objects with all available yearly/monthly
 * data along with titles and colors.  This is used in chart.js datasets
 * when click events request the data
 */
function setYearData()
{
	global $context, $txt;

	// No data, no chart
	if (empty($context['yearly']))
	{
		return;
	}

	$yearChart = array(
		'axis_labels' => [],
		'hits' => [],
		'new_posts' => [],
		'most_members_online' => [],
		'new_topics' => [],
		'new_members' => [],
	);

	$monthChart = array(
		'axis_labels' => [],
		'hits' => [],
		'new_posts' => [],
		'most_members_online' => [],
		'new_topics' => [],
		'new_members' => [],
	);

	// Low to high looks best on a chart
	$yearly = array_reverse($context['yearly'], true);
	foreach ($yearly as $year => $data)
	{
		// The year data
		$yearChart['axis_labels'][$year] = $year;
		$yearChart['new_topics'][$year] = removeComma($data['new_topics']);
		$yearChart['new_members'][$year] = removeComma($data['new_members']);
		$yearChart['new_posts'][$year] = removeComma($data['new_posts']);
		$yearChart['most_members_online'][$year] = removeComma($data['most_members_online']);
		$yearChart['hits'][$year] = isset($data['hits']) ? removeComma($data['hits']) : 0;

		// The monthly data for that year
		$monthly = array_reverse($context['yearly'][$year]['months'], true);
		foreach ($monthly as $month => $mdata)
		{
			$monthChart['axis_labels'][$year][$month] = substr($mdata['month'], 0, 3) . '/' . $year;
			$monthChart['new_topics'][$year][$month] = removeComma($mdata['new_topics']);
			$monthChart['new_members'][$year][$month] = removeComma($mdata['new_members']);
			$monthChart['new_posts'][$year][$month] = removeComma($mdata['new_posts']);
			$monthChart['most_members_online'][$year][$month] = removeComma($mdata['most_members_online']);
			$monthChart['hits'][$year][$month] = isset($mdata['hits']) ? removeComma($mdata['hits']) : 0;
		}
	}

	// Colors for the line charts
	$colors = array(
		'new_topics' => '55,187,89',
		'new_members' => '187,55,89',
		'new_posts' => '89,55,187',
		'most_members_online' => '187,89,55',
		'hits' => '55,89,187',
	);

	// Chart title so you remember what you are looking at
	$titles = array(
		'new_topics' => $txt['stats_new_topics'],
		'new_members' => $txt['stats_new_members'],
		'new_posts' => $txt['stats_new_posts'],
		'most_members_online' => $txt['most_time_online'],
		'hits' => $txt['page_views'],
	);

	// Now dump it out in JS objects
	echo '
	<script>
		const yeardata = ', json_encode($yearChart), ';
		const monthdata = ', json_encode($monthChart), ';
		const colors = ', json_encode($colors), ';
		const titles = ', json_encode($titles), ';
	</script>';
}

/**
 * This is why you should not really do formatting outside the templates
 *
 * @param $c
 * @return array|string|string[]|null
 */
function removeComma($c)
{
	return preg_replace('~\D~', '', $c);
}

/**
 * Draws the initial chart on the defined page canvas
 *
 * @param string $type The initial ALL years type to show
 */
function showLineChart($type)
{
	echo '
	<script>
		let year = "all",
			request = "', $type, '",
			ctx_yearStats = document.getElementById("yearStats").getContext("2d"),
			yearLabels = Object.values(yeardata["axis_labels"]),
			yearDataset = {
				labels: yearLabels,
				datasets: [{
					label: titles[request],
					data: Object.values(yeardata[request]),
					backgroundColor: [
						"rgba(" + colors[request] + ", 0.1)",
					],
					borderColor: [
						"rgba(" + colors[request] + ", 1)",
					],
					borderWidth: 1,
					pointStyle: "circle",
					pointRadius: 4,
					lineTension: 0.2,
					fill: "origin"
				}]
			},
			yearConfig = {
				type: "line",
				responsive: true,
				data: yearDataset,
				options: {
					scales: {
						y: {
							stacked: true,
							ticks: {beginAtZero: true}
						}
					},
					plugins: {
						filler: {propagate: false}
					}
				}
			};	
		const yearStats = new Chart(ctx_yearStats, yearConfig);
	</script>';
}
