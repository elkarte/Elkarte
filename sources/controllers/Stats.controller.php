<?php

/**
 * Provide a display for forum statistics
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
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
	 *
	 * @uses Stats language file
	 * @uses Stats template, statistics sub template
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
		$context['sub_template'] = 'statistics';
		loadJavascriptFile('stats.js');

		// These are the templates that will be used to render the statistics
		$context['statistics_callbacks'] = array(
			'general_statistics',
			'top_statistics',
		);

		$this->loadGeneralStatistics();
		$this->loadTopStatistics();
		$this->loadMontlyActivity();

		// Custom stats (just add a template_layer or another callback to add it to the page!)
		call_integration_hook('integrate_forum_stats');
	}

	/**
	 * Load some general statistics of the forum
	 */
	public function loadGeneralStatistics()
	{
		global $scripturl, $modSettings, $context;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Get averages...
		$averages = getAverages();
		// This would be the amount of time the forum has been up... in days...
		$total_days_up = ceil((time() - strtotime($averages['date'])) / (60 * 60 * 24));
		$date = strftime('%Y-%m-%d', forum_time(false));

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

		$context['general_statistics']['left'] = array(
			'total_members' => allowedTo('view_mlist') ? '<a href="' . $scripturl . '?action=memberlist">' . comma_format($modSettings['totalMembers']) . '</a>' : comma_format($modSettings['totalMembers']),
			'total_posts' => comma_format($modSettings['totalMessages']),
			'total_topics' => comma_format($modSettings['totalTopics']),
			'total_cats' => comma_format(numCategories()),
			// How many users are online now.
			'users_online' => comma_format(onlineCount()),
			'most_online' => array(
				'number' => comma_format($modSettings['mostOnline']),
				'date' => standardTime($modSettings['mostDate'])
			),
			// Members online so far today.
			'users_online_today' => comma_format(mostOnline($date)),
		);

		if (!empty($modSettings['hitStats']))
			$context['general_statistics']['left'] += array(
				'num_hits' => comma_format($averages['hits'], 0)
			);

		$context['general_statistics']['right'] = array(
			'average_members' => comma_format(round($averages['registers'] / $total_days_up, 2)),
			'average_posts' => comma_format(round($averages['posts'] / $total_days_up, 2)),
			'average_topics' => comma_format(round($averages['topics'] / $total_days_up, 2)),
			// Statistics such as number of boards, categories, etc.
			'total_boards' => comma_format(countBoards('all', array('include_redirects' => false))),
			'latest_member' => &$context['common_stats']['latest_member'],
			'average_online' => comma_format(round($averages['most_on'] / $total_days_up, 2)),
			'gender_ratio' => $context['gender']['ratio'],
		);

		if (!empty($modSettings['hitStats']))
			$context['general_statistics']['right'] += array(
				'average_hits' => comma_format(round($averages['hits'] / $total_days_up, 2)),
			);
	}

	/**
	 * Top posters, boards, replies, etc.
	 */
	public function loadTopStatistics()
	{
		global $context;

		// Poster top 10.
		$context['top']['posters'] = topPosters();

		// Board top 10.
		$context['top']['boards'] = topBoards();

		// Topic replies top 10.
		$context['top']['topics_replies'] = topTopicReplies();

		// Topic views top 10.
		$context['top']['topics_views'] = topTopicViews();

		// Topic poster top 10.
		$context['top']['starters'] = topTopicStarter();

		// Time online top 10.
		$context['top']['time_online'] = topTimeOnline();
	}

	/**
	 * Load the huge table of activity by month
	 */
	public function loadMontlyActivity()
	{
		global $context;

		// Activity by month.
		monthlyActivity();

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
	}
}