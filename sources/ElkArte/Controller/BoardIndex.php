<?php

/**
 * The single function this file contains is used to display the main board index.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\BoardsList;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\FrontpageInterface;
use ElkArte\Languages\Txt;

/**
 * Displays the main board index
 */
class BoardIndex extends AbstractController implements FrontpageInterface
{
	/**
	 * {@inheritDoc}
	 */
	public static function frontPageHook(&$default_action)
	{
		$default_action = [
			'controller' => BoardIndex::class,
			'function' => 'action_boardindex'
		];
	}

	/**
	 * Forwards to the action to execute here by default.
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		// What to do... boardindex, 'course!
		$this->action_boardindex();
	}

	/**
	 * This function shows the board index.
	 *
	 * What it does:
	 *
	 * - It updates the most online statistics.
	 * - It is accessed by ?action=boardindex.
	 *
	 * @uses the BoardIndex template, and main sub template
	 */
	public function action_boardindex(): void
	{
		global $txt, $modSettings, $context, $settings;

		theme()->getTemplates()->load('BoardIndex');

		// Set a canonical URL for this page.
		$context['canonical_url'] = getUrl('boardindex', []);
		theme()->getLayers()->add('boardindex_outer');

		// Do not let search engines index anything if there is a random thing in $_GET.
		if (!empty($this->_req->query))
		{
			$context['robot_no_index'] = true;
		}

		// Retrieve the categories and boards.
		$boardIndexOptions = [
			'include_categories' => true,
			'base_level' => 0,
			'parent_id' => 0,
			'set_latest_post' => true,
			'countChildPosts' => !empty($modSettings['countChildPosts']),
		];

		$this->_events->trigger('pre_load', ['boardIndexOptions' => &$boardIndexOptions]);

		$boardlist = new BoardsList($boardIndexOptions);
		$context['categories'] = $boardlist->getBoards();
		$context['latest_post'] = $boardlist->getLatestPost();

		// Get the user online list.
		require_once(SUBSDIR . '/MembersOnline.subs.php');
		$membersOnlineOptions = [
			'show_hidden' => allowedTo('moderate_forum'),
			'sort' => 'log_time',
			'reverse_sort' => true,
		];
		$context += getMembersOnlineStats($membersOnlineOptions);

		$context['show_buddies'] = !empty($this->user->buddies);

		// Are we showing all membergroups on the board index?
		if (!empty($settings['show_group_key']))
		{
			$context['membergroups'] = Cache::instance()->quick_get('membergroup_list', 'subs/Membergroups.subs.php', 'cache_getMembergroupList', []);
		}

		// Track most online statistics? (subs/Members.subs.phpOnline.php)
		if (!empty($modSettings['trackStats']))
		{
			trackStatsUsersOnline($context['num_guests'] + $context['num_users_online']);
		}

		// Retrieve the latest posts if the theme settings require it.
		if (isset($settings['number_recent_posts']) && $settings['number_recent_posts'] > 1)
		{
			$latestPostOptions = [
				'number_posts' => $settings['number_recent_posts'],
				'id_member' => $this->user->id,
			];
			if (empty($settings['recent_post_topics']))
			{
				$context['latest_posts'] = Cache::instance()->quick_get('boardindex-latest_posts:' . md5($this->user->query_wanna_see_board . $this->user->language), 'subs/Recent.subs.php', 'cache_getLastPosts', [$latestPostOptions]);
			}
			else
			{
				$context['latest_posts'] = Cache::instance()->quick_get('boardindex-latest_topics:' . md5($this->user->query_wanna_see_board . $this->user->language), 'subs/Recent.subs.php', 'cache_getLastTopics', [$latestPostOptions]);
			}
		}

		// Let the template know what the members can do if the theme enables these options
		$context['show_stats'] = allowedTo('view_stats') && !empty($modSettings['trackStats']);
		$context['show_member_list'] = allowedTo('view_mlist');
		$context['show_who'] = allowedTo('who_view') && !empty($modSettings['who_enabled']);

		$context['page_title'] = sprintf($txt['forum_index'], $context['forum_name']);
		$context['sub_template'] = 'boards_list';
		$context['page_description'] = implode(' | ', array_column($context['categories'], 'name'));

		$context['info_center_callbacks'] = [];
		if (!empty($settings['number_recent_posts']) && (!empty($context['latest_posts']) || !empty($context['latest_post'])))
		{
			$context['info_center_callbacks'][] = 'recent_posts';
		}

		if (!empty($settings['show_likestats_index']) && !empty($modSettings['likes_enabled']) && allowedTo('like_posts_stats'))
		{
			$this->getLikeStatsContext();
		}

		if (!empty($settings['show_stats_index']))
		{
			$context['info_center_callbacks'][] = 'show_stats';
		}

		$context['info_center_callbacks'][] = 'show_users';

		$this->_events->trigger('post_load', ['callbacks' => &$context['info_center_callbacks']]);

		theme()->addJavascriptVar([
			'txt_mark_as_read_confirm' => $txt['mark_as_read_confirm']
		], true);

		// the "Mark read" button
		$context['mark_read_button'] = [
			'markread' => [
				'text' => 'mark_as_read',
				'lang' => true,
				'custom' => 'onclick="return markallreadButton(this);"',
				'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'all', 'bi', '{session_data}'])
			],
		];

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_mark_read_button');
		theme()->getLayers()->add('info_center');
	}

	/**
	 * Collapse or expand a category
	 *
	 * - accessed by ?action=collapse
	 */
	public function action_collapse(): void
	{
		global $context;

		// Just in case, no need, no need.
		$context['robot_no_index'] = true;

		checkSession('request');

		if (!$this->_req->hasQuery('sa'))
		{
			throw new Exception('no_access', false);
		}

		// Check if the input values are correct.
		$sa = $this->_req->getQuery('sa', 'trim|strval', '');
		if ($this->_req->hasQuery('c') && in_array($sa, ['expand', 'collapse', 'toggle']))
		{
			// And collapse/expand/toggle the category.
			require_once(SUBSDIR . '/Categories.subs.php');
			$c = $this->_req->getQuery('c', 'intval', 0);
			collapseCategories([$c], $sa, [$this->user->id]);
		}

		// And go back to the board index.
		$this->action_boardindex();
	}

	/**
	 * Retrieves and prepares like statistics data for topics, boards, and messages,
	 * and configures the context for display in the information center.
	 *
	 * The method populates the provided context with like statistics retrieved
	 * from the cache or database and determines whether the like statistics
	 * should be shown in the information center.
	 *
	 */
	public function getLikeStatsContext()
	{
		global $context;

		require_once(SUBSDIR . '/Likes.subs.php');
		Txt::load('LikePosts');

		$context['likestats'] = [
			'topics' => [],
			'boards' => [],
			'messages' => [],
		];

		$key = md5($this->user->query_wanna_see_board ?? '');

		if (Cache::instance()->getVar($context['likestats']['topics'], 'ic_likestats_topic:' . $key, 1800) === false)
		{
			$context['likestats']['topics'] = dbMostLikedTopic(null, 5);
			Cache::instance()->put('ic_likestats_topic:' . $key, $context['likestats']['topics'], 1800);
		}

		if (Cache::instance()->getVar($context['likestats']['boards'], 'ic_likestats_board:' . $key, 1800) === false)
		{
			$context['likestats']['boards'] = dbMostLikedBoard(3);
			Cache::instance()->put('ic_likestats_board:' . $key, $context['likestats']['boards'], 1800);
		}

		if (Cache::instance()->getVar($context['likestats']['messages'], 'ic_likestats_message:' . $key, 1800) === false)
		{
			$context['likestats']['messages'] = dbMostLikedMessage(5);
			Cache::instance()->put('ic_likestats_message:' . $key, $context['likestats']['messages'], 1800);
		}

		$context['likestats']['show'] = false;
		foreach (['topics', 'boards', 'messages'] as $key)
		{
			if (!empty($context['likestats'][$key]) && empty($context['likestats'][$key]['noDataMessage']))
			{
				$context['likestats']['show'] = true;
				break;
			}
		}

		if ($context['likestats']['show'])
		{
			$context['info_center_callbacks'][] = 'show_likestats';
		}
	}
}
