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
 *
 * Provide a display for forum statistics
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Statistics Controller
 */
class Stats_Controller extends Action_Controller
{
	/**
	 * Entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Call the right method... wait, we only know how to do
		// one thing (and do it well! :P)
		$this->action_stats();
	}

	/**
	 * Display some useful/interesting board statistics.
	 *
	 * gets all the statistics in order and puts them in.
	 * uses the Stats template and language file. (and main sub template.)
	 * requires the view_stats permission.
	 * accessed from ?action=stats.
	 */
	public function action_stats()
	{
		global $txt, $scripturl, $modSettings, $context;

		isAllowedTo('view_stats');

		// Page disabled - redirect them out
		if (empty($modSettings['trackStats']))
			fatal_lang_error('feature_disabled', true);

		if (!empty($_REQUEST['expand']))
		{
			$context['robot_no_index'] = true;

			$month = (int) substr($_REQUEST['expand'], 4);
			$year = (int) substr($_REQUEST['expand'], 0, 4);
			if ($year > 1900 && $year < 2200 && $month >= 1 && $month <= 12)
				$_SESSION['expanded_stats'][$year][] = $month;
		}
		elseif (!empty($_REQUEST['collapse']))
		{
			$context['robot_no_index'] = true;

			$month = (int) substr($_REQUEST['collapse'], 4);
			$year = (int) substr($_REQUEST['collapse'], 0, 4);
			if (!empty($_SESSION['expanded_stats'][$year]))
				$_SESSION['expanded_stats'][$year] = array_diff($_SESSION['expanded_stats'][$year], array($month));
		}

		// Just a lil' help from our friend :P
		require_once(SUBSDIR . '/Stats.subs.php');

		// Handle the XMLHttpRequest.
		if (isset($_REQUEST['xml']))
		{
			// Collapsing stats only needs adjustments of the session variables.
			if (!empty($_REQUEST['collapse']))
				obExit(false);

			$context['sub_template'] = 'stats';
			getDailyStats('YEAR(date) = {int:year} AND MONTH(date) = {int:month}', array('year' => $year, 'month' => $month));
			$context['yearly'][$year]['months'][$month]['date'] = array(
				'month' => sprintf('%02d', $month),
				'year' => $year,
			);
			return;
		}

		loadLanguage('Stats');
		loadTemplate('Stats');

		// Build the link tree......
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=stats',
			'name' => $txt['stats_center']
		);
		$context['page_title'] = $context['forum_name'] . ' - ' . $txt['stats_center'];

		$context['show_member_list'] = allowedTo('view_mlist');

		// Get averages...
		$averages = getAverages();
		// This would be the amount of time the forum has been up... in days...
		$total_days_up = ceil((time() - strtotime($averages['date'])) / (60 * 60 * 24));

		$context['average_posts'] = comma_format(round($averages['posts'] / $total_days_up, 2));
		$context['average_topics'] = comma_format(round($averages['topics'] / $total_days_up, 2));
		$context['average_members'] = comma_format(round($averages['registers'] / $total_days_up, 2));
		$context['average_online'] = comma_format(round($averages['most_on'] / $total_days_up, 2));
		$context['average_hits'] = comma_format(round($averages['hits'] / $total_days_up, 2));

		$context['num_hits'] = comma_format($averages['hits'], 0);

		// How many users are online now.
		$context['users_online'] = onlineCount();

		// Statistics such as number of boards, categories, etc.
		$context['num_boards'] = numBoards();
		$context['num_categories'] = numCategories();

		// Format the numbers nicely.
		$context['users_online'] = comma_format($context['users_online']);
		$context['num_boards'] = comma_format($context['num_boards']);
		$context['num_categories'] = comma_format($context['num_categories']);

		$context['num_members'] = comma_format($modSettings['totalMembers']);
		$context['num_posts'] = comma_format($modSettings['totalMessages']);
		$context['num_topics'] = comma_format($modSettings['totalTopics']);
		$context['most_members_online'] = array(
			'number' => comma_format($modSettings['mostOnline']),
			'date' => relativeTime($modSettings['mostDate'])
		);
		$context['latest_member'] = &$context['common_stats']['latest_member'];

		// Male vs. female ratio - let's calculate this only every four minutes.
		if (($context['gender'] = cache_get_data('stats_gender', 240)) == null)
		{
			$context['gender'] = genderRatio();

			// Set these two zero if the didn't get set at all.
			if (empty($context['gender']['males']))
				$context['gender']['males'] = 0;
			if (empty($context['gender']['females']))
				$context['gender']['females'] = 0;

			// Try and come up with some "sensible" default states in case of a non-mixed board.
			if ($context['gender']['males'] == $context['gender']['females'])
				$context['gender']['ratio'] = '1:1';
			elseif ($context['gender']['males'] == 0)
				$context['gender']['ratio'] = '0:1';
			elseif ($context['gender']['females'] == 0)
				$context['gender']['ratio'] = '1:0';
			elseif ($context['gender']['males'] > $context['gender']['females'])
				$context['gender']['ratio'] = round($context['gender']['males'] / $context['gender']['females'], 1) . ':1';
			elseif ($context['gender']['females'] > $context['gender']['males'])
				$context['gender']['ratio'] = '1:' . round($context['gender']['females'] / $context['gender']['males'], 1);

			cache_put_data('stats_gender', $context['gender'], 240);
		}

		$date = strftime('%Y-%m-%d', forum_time(false));

		// Members online so far today.
		$context['online_today'] = mostOnline($date);
		$context['online_today'] = comma_format((int) $context['online_today']);

		// Poster top 10.
		$context['top_posters'] = topPosters();

		// Board top 10.
		$context['top_boards'] = topBoards();

		// Topic replies top 10.
		$context['top_topics_replies'] = topTopicReplies();
	
		// Topic views top 10.
		$context['top_topics_views'] = topTopicViews();

		// Topic poster top 10.
		$context['top_starters'] = topTopicStarter();

		// Time online top 10.
		$context['top_time_online'] = topTimeOnline();

		// Activity by month.
		montlyActivity();

		$context['collapsed_years'] = array();
		foreach ($context['yearly'] as $year => $data)
		{
			// This gets rid of the filesort on the query ;).
			krsort($context['yearly'][$year]['months']);

			$context['yearly'][$year]['new_topics'] = comma_format($data['new_topics']);
			$context['yearly'][$year]['new_posts'] = comma_format($data['new_posts']);
			$context['yearly'][$year]['new_members'] = comma_format($data['new_members']);
			$context['yearly'][$year]['most_members_online'] = comma_format($data['most_members_online']);
			$context['yearly'][$year]['hits'] = comma_format($data['hits']);

			// Keep a list of collapsed years.
			if (!$data['expanded'] && !$data['current_year'])
				$context['collapsed_years'][] = $year;
		}

		if (empty($_SESSION['expanded_stats']))
			return;

		$condition_text = array();
		$condition_params = array();
		foreach ($_SESSION['expanded_stats'] as $year => $months)
			if (!empty($months))
			{
				$condition_text[] = 'YEAR(date) = {int:year_' . $year . '} AND MONTH(date) IN ({array_int:months_' . $year . '})';
				$condition_params['year_' . $year] = $year;
				$condition_params['months_' . $year] = $months;
			}

		// No daily stats to even look at?
		if (empty($condition_text))
			return;

		getDailyStats(implode(' OR ', $condition_text), $condition_params);

		// Custom stats (just add a template_layer to add it to the template!)
	 	call_integration_hook('integrate_forum_stats');
	}
}