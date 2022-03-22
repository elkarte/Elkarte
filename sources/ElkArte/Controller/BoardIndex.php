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
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\BoardsList;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\FrontpageInterface;

/**
 * Displays the main board index
 */
class BoardIndex extends AbstractController implements FrontpageInterface
{
	/**
	 * {@inheritdoc }
	 */
	public static function frontPageHook(&$default_action)
	{
		$default_action = array(
			'controller' => '\\ElkArte\\Controller\\BoardIndex',
			'function' => 'action_boardindex'
		);
	}

	/**
	 * Forwards to the action to execute here by default.
	 *
	 * @see \ElkArte\AbstractController::action_index()
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
	public function action_boardindex()
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
		$boardIndexOptions = array(
			'include_categories' => true,
			'base_level' => 0,
			'parent_id' => 0,
			'set_latest_post' => true,
			'countChildPosts' => !empty($modSettings['countChildPosts']),
		);

		$this->_events->trigger('pre_load', array('boardIndexOptions' => &$boardIndexOptions));

		$boardlist = new BoardsList($boardIndexOptions);
		$context['categories'] = $boardlist->getBoards();
		$context['latest_post'] = $boardlist->getLatestPost();

		// Get the user online list.
		require_once(SUBSDIR . '/MembersOnline.subs.php');
		$membersOnlineOptions = array(
			'show_hidden' => allowedTo('moderate_forum'),
			'sort' => 'log_time',
			'reverse_sort' => true,
		);
		$context += getMembersOnlineStats($membersOnlineOptions);

		$context['show_buddies'] = !empty($this->user->buddies);

		// Are we showing all membergroups on the board index?
		if (!empty($settings['show_group_key']))
		{
			$context['membergroups'] = Cache::instance()->quick_get('membergroup_list', 'subs/Membergroups.subs.php', 'cache_getMembergroupList', array());
		}

		// Track most online statistics? (subs/Members.subs.phpOnline.php)
		if (!empty($modSettings['trackStats']))
		{
			trackStatsUsersOnline($context['num_guests'] + $context['num_users_online']);
		}

		// Retrieve the latest posts if the theme settings require it.
		if (isset($settings['number_recent_posts']) && $settings['number_recent_posts'] > 1)
		{
			$latestPostOptions = array(
				'number_posts' => $settings['number_recent_posts'],
				'id_member' => $this->user->id,
			);
			if (empty($settings['recent_post_topics']))
			{
				$context['latest_posts'] = Cache::instance()->quick_get('boardindex-latest_posts:' . md5($this->user->query_wanna_see_board . $this->user->language), 'subs/Recent.subs.php', 'cache_getLastPosts', array($latestPostOptions));
			}
			else
			{
				$context['latest_posts'] = Cache::instance()->quick_get('boardindex-latest_topics:' . md5($this->user->query_wanna_see_board . $this->user->language), 'subs/Recent.subs.php', 'cache_getLastTopics', array($latestPostOptions));
			}
		}

		// Let the template know what the members can do if the theme enables these options
		$context['show_stats'] = allowedTo('view_stats') && !empty($modSettings['trackStats']);
		$context['show_member_list'] = allowedTo('view_mlist');
		$context['show_who'] = allowedTo('who_view') && !empty($modSettings['who_enabled']);

		$context['page_title'] = sprintf($txt['forum_index'], $context['forum_name']);
		$context['sub_template'] = 'boards_list';

		$context['info_center_callbacks'] = array();
		if (!empty($settings['number_recent_posts']) && (!empty($context['latest_posts']) || !empty($context['latest_post'])))
		{
			$context['info_center_callbacks'][] = 'recent_posts';
		}

		if (!empty($settings['show_stats_index']))
		{
			$context['info_center_callbacks'][] = 'show_stats';
		}

		$context['info_center_callbacks'][] = 'show_users';

		$this->_events->trigger('post_load', array('callbacks' => &$context['info_center_callbacks']));

		theme()->addJavascriptVar(array(
			'txt_mark_as_read_confirm' => $txt['mark_as_read_confirm']
		), true);

		// Mark read button
		$context['mark_read_button'] = array(
			'markread' => array(
				'text' => 'mark_as_read',
				'lang' => true,
				'custom' => 'onclick="return markallreadButton(this);"',
				'url' => getUrl('action', array('action' => 'markasread', 'sa' => 'all', 'bi', '{session_data}'))
			),
		);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_mark_read_button');
		if (!empty($context['info_center_callbacks']))
		{
			theme()->getLayers()->add('info_center');
		}
	}

	/**
	 * Collapse or expand a category
	 *
	 * - accessed by ?action=collapse
	 */
	public function action_collapse()
	{
		global $context;

		// Just in case, no need, no need.
		$context['robot_no_index'] = true;

		checkSession('request');

		if (!isset($this->_req->query->sa))
		{
			throw new Exception('no_access', false);
		}

		// Check if the input values are correct.
		if (isset($this->_req->query->c) && in_array($this->_req->query->sa, array('expand', 'collapse', 'toggle')) )
		{
			// And collapse/expand/toggle the category.
			require_once(SUBSDIR . '/Categories.subs.php');
			collapseCategories(array((int) $this->_req->query->c), $this->_req->query->sa, array($this->user->id));
		}

		// And go back to the board index.
		$this->action_boardindex();
	}
}
