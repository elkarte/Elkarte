<?php

/**
 * Provide a display for forum statistics
 *
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

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\Themes\ThemeLoader;
use Elkarte\Util;

/**
 * Handles the calculation of forum statistics
 */
class Stats extends AbstractController
{
	/**
	 * Entry point for this class.
	 *
	 * @see \ElkArte\AbstractController::action_index()
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
	 * What it does:
	 *
	 * - Gets all the statistics in order and puts them in.
	 * - Uses the Stats template and language file. (and main sub template.)
	 * - Requires the view_stats permission.
	 * - Accessed from ?action=stats.
	 *
	 * @uses Stats language file
	 * @uses template_stats() sub template in Stats.template
	 */
	public function action_stats()
	{
		global $txt, $modSettings, $context;

		// You have to be able to see these
		isAllowedTo('view_stats');

		// Page disabled - redirect them out
		if (empty($modSettings['trackStats']))
		{
			throw new Exception('feature_disabled', true);
		}

		// Expanding out the history summary
		list($year, $month) = $this->_expandedStats();

		// Just a lil' help from our friend :P
		require_once(SUBSDIR . '/Stats.subs.php');

		// Handle the XMLHttpRequest.
		if (isset($this->_req->query->xml))
		{
			if (empty($year) || empty($month))
			{
				redirectexit('action=stats');
			}

			// Collapsing stats only needs adjustments of the session variables.
			if (!empty($this->_req->query->collapse))
			{
				obExit(false);
			}

			$context['sub_template'] = 'stats';
			getDailyStats('YEAR(date) = {int:year} AND MONTH(date) = {int:month}', array('year' => $year, 'month' => $month));
			$context['yearly'][$year]['months'][$month]['date'] = array(
				'month' => sprintf('%02d', $month),
				'year' => $year,
			);

			return true;
		}

		// Stats it is
		ThemeLoader::loadLanguageFile('Stats');
		theme()->getTemplates()->load('Stats');
		loadJavascriptFile('stats.js');

		// Build the link tree......
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'stats']),
			'name' => $txt['stats_center']
		);

		// Prepare some things for the template page
		$context['page_title'] = $context['forum_name'] . ' - ' . $txt['stats_center'];
		$context['sub_template'] = 'statistics';

		// These are the templates that will be used to render the statistics
		$context['statistics_callbacks'] = array(
			'general_statistics',
			'top_statistics',
		);

		// Call each area of statics to load our friend $context
		$this->loadGeneralStatistics();
		$this->loadTopStatistics();
		$this->loadMontlyActivity();

		// Custom stats (just add a template_layer or another callback to add it to the page!)
		call_integration_hook('integrate_forum_stats');
	}

	/**
	 * Sanitize and validate the year / month for expand / collapse stats
	 *
	 * @return array of year and month from expand / collapse link
	 */
	private function _expandedStats()
	{
		global $context;

		$year = '';
		$month = '';

		if (!empty($this->_req->query->expand))
		{
			$context['robot_no_index'] = true;

			$month = (int) substr($this->_req->query->expand, 4);
			$year = (int) substr($this->_req->query->expand, 0, 4);
			if ($year > 1900 && $year < 2200 && $month >= 1 && $month <= 12)
			{
				$_SESSION['expanded_stats'][$year][] = $month;
			}
		}
		// Done looking at the details and want to fold it back up
		elseif (!empty($this->_req->query->collapse))
		{
			$context['robot_no_index'] = true;

			$month = (int) substr($this->_req->query->collapse, 4);
			$year = (int) substr($this->_req->query->collapse, 0, 4);
			if (!empty($_SESSION['expanded_stats'][$year]))
			{
				$_SESSION['expanded_stats'][$year] = array_diff($_SESSION['expanded_stats'][$year], array($month));
			}
		}

		return array($year, $month);
	}

	/**
	 * Load some general statistics of the forum
	 */
	public function loadGeneralStatistics()
	{
		global $modSettings, $context;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Get averages...
		$averages = getAverages();

		// This would be the amount of time the forum has been up... in days...
		$total_days_up = ceil((time() - strtotime($averages['date'])) / (60 * 60 * 24));
		$date = Util::strftime('%Y-%m-%d', forum_time(false));

		// General forum stats
		$context['general_statistics']['left'] = array(
			'total_members' => allowedTo('view_mlist') ? '<a href="' . getUrl('action', ['action' => 'memberlist']) . '">' . comma_format($modSettings['totalMembers']) . '</a>' : comma_format($modSettings['totalMembers']),
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
		{
			$context['general_statistics']['left'] += array(
				'num_hits' => comma_format($averages['hits'], 0)
			);
		}

		$context['general_statistics']['right'] = array(
			'average_members' => comma_format(round($averages['registers'] / $total_days_up, 2)),
			'average_posts' => comma_format(round($averages['posts'] / $total_days_up, 2)),
			'average_topics' => comma_format(round($averages['topics'] / $total_days_up, 2)),
			// Statistics such as number of boards, categories, etc.
			'total_boards' => comma_format(countBoards('all', array('include_redirects' => false))),
			'latest_member' => &$context['common_stats']['latest_member'],
			'average_online' => comma_format(round($averages['most_on'] / $total_days_up, 2)),
			'emails_sent' => comma_format(round($averages['email'] / $total_days_up, 2))
		);

		if (!empty($modSettings['hitStats']))
		{
			$context['general_statistics']['right'] += array(
				'average_hits' => comma_format(round($averages['hits'] / $total_days_up, 2)),
			);
		}
	}

	/**
	 * Loads in the the "top" statistics
	 *
	 * What it does:
	 *
	 * - Calls support topXXXX functions to load stats
	 * - Places results in to context
	 * - Uses Top posters, topBoards, topTopicReplies, topTopicViews, topTopicStarter, topTimeOnline
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

			// Yearly stats, topics, posts, members, etc
			$context['yearly'][$year]['new_topics'] = comma_format($data['new_topics']);
			$context['yearly'][$year]['new_posts'] = comma_format($data['new_posts']);
			$context['yearly'][$year]['new_members'] = comma_format($data['new_members']);
			$context['yearly'][$year]['most_members_online'] = comma_format($data['most_members_online']);
			$context['yearly'][$year]['hits'] = comma_format($data['hits']);

			// Keep a list of collapsed years.
			if (!$data['expanded'] && !$data['current_year'])
			{
				$context['collapsed_years'][] = $year;
			}
		}

		// Want to expand out the yearly stats
		if (empty($_SESSION['expanded_stats']))
		{
			return false;
		}

		$condition_text = array();
		$condition_params = array();
		foreach ($_SESSION['expanded_stats'] as $year => $months)
		{
			if (!empty($months))
			{
				$condition_text[] = 'YEAR(date) = {int:year_' . $year . '} AND MONTH(date) IN ({array_int:months_' . $year . '})';
				$condition_params['year_' . $year] = $year;
				$condition_params['months_' . $year] = $months;
			}
		}

		// No daily stats to even look at?
		if (empty($condition_text))
		{
			return false;
		}

		getDailyStats(implode(' OR ', $condition_text), $condition_params);

		return true;
	}
}
