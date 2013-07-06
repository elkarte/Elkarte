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
 * Stats page.
 */
function template_main()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<div id="statistics" class="forum_category">
		<h2 class="category_header">
			', $context['page_title'], '
		</h2>
		<ul class="statistics">
			<li class="flow_hidden" id="top_row">
				<h3 class="category_header">
					<img src="', $settings['images_url'], '/stats_info.png" class="icon" alt="" /> ', $txt['general_stats'], '
				</h3>
				<dl class="stats floatleft">
					<dt>', $txt['total_members'], ':</dt>
					<dd>', $context['show_member_list'] ? '<a href="' . $scripturl . '?action=memberlist">' . $context['num_members'] . '</a>' : $context['num_members'], '</dd>
					<dt>', $txt['total_posts'], ':</dt>
					<dd>', $context['num_posts'], '</dd>
					<dt>', $txt['total_topics'], ':</dt>
					<dd>', $context['num_topics'], '</dd>
					<dt>', $txt['total_cats'], ':</dt>
					<dd>', $context['num_categories'], '</dd>
					<dt>', $txt['users_online'], ':</dt>
					<dd>', $context['users_online'], '</dd>
					<dt>', $txt['most_online'], ':</dt>
					<dd>', $context['most_members_online']['number'], ' - ', $context['most_members_online']['date'], '</dd>
					<dt>', $txt['users_online_today'], ':</dt>
					<dd>', $context['online_today'], '</dd>';

	if (!empty($modSettings['hitStats']))
		echo '
					<dt>', $txt['num_hits'], ':</dt>
					<dd>', $context['num_hits'], '</dd>';

	echo '
				</dl>
				<dl class="stats">
					<dt>', $txt['average_members'], ':</dt>
					<dd>', $context['average_members'], '</dd>
					<dt>', $txt['average_posts'], ':</dt>
					<dd>', $context['average_posts'], '</dd>
					<dt>', $txt['average_topics'], ':</dt>
					<dd>', $context['average_topics'], '</dd>
					<dt>', $txt['total_boards'], ':</dt>
					<dd>', $context['num_boards'], '</dd>
					<dt>', $txt['latest_member'], ':</dt>
					<dd>', $context['common_stats']['latest_member']['link'], '</dd>
					<dt>', $txt['average_online'], ':</dt>
					<dd>', $context['average_online'], '</dd>
					<dt>', $txt['gender_ratio'], ':</dt>
					<dd>', $context['gender']['ratio'], '</dd>';

	if (!empty($modSettings['hitStats']))
		echo '
					<dt>', $txt['average_hits'], ':</dt>
					<dd>', $context['average_hits'], '</dd>';

	echo '
				</dl>
			</li>
			<li class="flow_hidden">
				<h3 class="category_header floatleft">
					<img src="', $settings['images_url'], '/stats_posters.png" class="icon" alt="" /> ', $txt['top_posters'], '
				</h3>
				<dl class="stats floatleft">';

	foreach ($context['top_posters'] as $poster)
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
				<h3 class="category_header">
					<img src="', $settings['images_url'], '/stats_board.png" class="icon" alt="" /> ', $txt['top_boards'], '
				</h3>
				<dl class="stats">';

	foreach ($context['top_boards'] as $board)
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
				<h3 class="category_header floatleft">
					<img src="', $settings['images_url'], '/stats_replies.png" class="icon" alt="" /> ', $txt['top_topics_replies'], '
				</h3>
				<dl class="stats floatleft">';

	foreach ($context['top_topics_replies'] as $topic)
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
				<h3 class="category_header">
					<img src="', $settings['images_url'], '/stats_views.png" class="icon" alt="" /> ', $txt['top_topics_views'], '
				</h3>
				<dl class="stats">';

	foreach ($context['top_topics_views'] as $topic)
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
				<h3 class="category_header floatleft">
					<img src="', $settings['images_url'], '/stats_replies.png" class="icon" alt="" /> ', $txt['top_starters'], '
				</h3>
				<dl class="stats floatleft">';

	foreach ($context['top_starters'] as $poster)
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
				<h3 class="category_header">
					<img src="', $settings['images_url'], '/stats_views.png" class="icon" alt="" /> ', $txt['most_time_online'], '
				</h3>
				<dl class="stats">';

	foreach ($context['top_time_online'] as $poster)
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
			</li>
		</ul>
	</div>
	<div id="forum_history" class="forum_category">
		<h2 class="category_header">
			<img src="', $settings['images_url'], '/stats_history.png" class="icon" alt="" /> ', $txt['forum_history'], '
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
						<th class="history_head>', $txt['page_views'], '</th>';

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
	<script src="', $settings['default_theme_url'], '/scripts/stats.js"></script>
	<script><!-- // --><![CDATA[
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
			sDayRowClassname: \'windowbg2\',
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
	// ]]></script>';
	}
}