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
 * @version 1.1 Release Candidate 1
 *
 */

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

	template_forum_history();
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
			if (isset($settings[$key]))
				$value = strtr($settings[$key], $value);
			else
				continue;
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
			if (isset($settings[$key]))
				$value = strtr($settings[$key], $value);
			else
				continue;
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
 * Shows "top" statistics, like top posters, top boards, top replies, etc
 */
function template_top_statistics()
{
	global $context, $txt;

	echo '
			<li class="flow_hidden">
				<h2 class="category_header floatleft hdicon cat_img_star">
					', $txt['top_posters'], '
				</h2>
				<dl class="stats floatleft">';

	foreach ($context['top']['posters'] as $poster)
	{
		echo '
					<dt>
						', $poster['link'], '
					</dt>
					<dd class="statsbar">
						<div class="bar" style="width: ', !empty($poster['post_percent']) ? $poster['post_percent'] : '0', 'px;"></div>
						<span class="righttext">', $poster['num_posts'], '</span>
					</dd>';
	}

	echo '
				</dl>
				<h2 class="category_header hdicon cat_img_topics">
					', $txt['top_boards'], '
				</h2>
				<dl class="stats">';

	foreach ($context['top']['boards'] as $board)
	{
		echo '
					<dt>
						', $board['link'], '
					</dt>
					<dd class="statsbar">
						<div class="bar" style="width: ', !empty($board['post_percent']) ? $board['post_percent'] : '0', 'px;"></div>
						<span class="righttext">', $board['num_posts'], '</span>
					</dd>';
	}

	echo '
				</dl>
			</li>
			<li class="flow_hidden">
				<h2 class="category_header floatleft hdicon cat_img_talk">
					', $txt['top_topics_replies'], '
				</h2>
				<dl class="stats floatleft">';

	foreach ($context['top']['topics_replies'] as $topic)
	{
		echo '
					<dt>
						', $topic['link'], '
					</dt>
					<dd class="statsbar">
						<div class="bar" style="width: ', !empty($topic['post_percent']) ? $topic['post_percent'] : '0', 'px;"></div>
						<span class="righttext">' . $topic['num_replies'] . '</span>
					</dd>';
	}

	echo '
				</dl>
				<h2 class="category_header hdicon cat_img_eye">
					', $txt['top_topics_views'], '
				</h2>
				<dl class="stats">';

	foreach ($context['top']['topics_views'] as $topic)
	{
		echo '
					<dt>', $topic['link'], '</dt>
					<dd class="statsbar">
						<div class="bar" style="width: ', !empty($topic['post_percent']) ? $topic['post_percent'] : '0', 'px;"></div>
						<span class="righttext">' . $topic['num_views'] . '</span>
					</dd>';
	}

	echo '
				</dl>
			</li>
			<li class="flow_hidden">
				<h2 class="category_header floatleft hdicon cat_img_write">
					', $txt['top_starters'], '
				</h2>
				<dl class="stats floatleft">';

	foreach ($context['top']['starters'] as $poster)
	{
		echo '
					<dt>
						', $poster['link'], '
					</dt>
					<dd class="statsbar">
						<div class="bar" style="width: ', !empty($poster['post_percent']) ? $poster['post_percent'] : '0', 'px;"></div>
						<span class="righttext">', $poster['num_topics'], '</span>
					</dd>';
	}

	echo '
				</dl>
				<h2 class="category_header hdicon cat_img_clock">
					', $txt['most_time_online'], '
				</h2>
				<dl class="stats">';

	foreach ($context['top']['time_online'] as $poster)
	{
		echo '
					<dt>
						', $poster['link'], '
					</dt>
					<dd class="statsbar">
						<div class="bar" style="width: ', !empty($poster['time_percent']) ? $poster['time_percent'] : '0', 'px;"></div>
						<span class="righttext">', $poster['time_online'], '</span>
					</dd>';
	}

	echo '
				</dl>
			</li>';
}

/**
 * Shows the forum history, year/month breakdown of activity such as topics, posts, members, etc
 */
function template_forum_history()
{
	global $context, $settings, $txt, $modSettings;

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
			echo '
						<th class="history_head">', $txt['page_views'], '</th>';

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
				echo '
						<th>', $year['hits'], '</th>';

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
					echo '
						<th>', $month['hits'], '</th>';

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
							echo '
						<td>', $day['hits'], '</td>';

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
		var oStatsCenter = new elk_StatsCenter({
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
				\'', $year, '\'', $id != count($context['collapsed_years']) - 1 ? ',' : '';
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
