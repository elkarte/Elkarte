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
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
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
		$subActions = [
			'boardindex' => [$this, 'action_boardindex'],
			'collapse' => [$this, 'action_collapse'],
		];

		// Set up the action handler
		$action = new Action('boardindex');

		// Default to collapse if requested via action=collapse, otherwise display the board index
		$default = $this->_req->getQuery('action') === 'collapse' ? 'collapse' : 'boardindex';
		$subAction = $action->initialize($subActions, $default);

		// Call the action
		$action->dispatch($subAction);
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

		// Track most online statistics?
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
	 * Retrieves and prepares like statistics data for topics, boards, and messages,
	 * and configures the context for display in the information center.
	 *
	 * The method populates the provided context with like statistics from a
	 * single cached payload and determines whether the block should be shown.
	 *
	 * Stale data is delivered immediately; regeneration happens in the background.
	 *
	 * @param bool $with_avatar ignored; the board index uses the compact lists
	 */
	public function getLikeStatsContext($with_avatar = false)
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Likes.subs.php');
		Txt::load('LikePosts');

		// The board index never uses avatars; it only needs the compact lists.
		// Cache lookup is non-blocking: old/stale data is delivered immediately and
		// refreshed by an ajax request after the page has been displayed.
		$key = md5($this->user->query_wanna_see_board ?? '');
		$stats = cache_getBoardIndexLikeStats($key);

		$context['likestats'] = [
			'topics' => $stats['data']['topics'] ?? [],
			'boards' => $stats['data']['boards'] ?? [],
			'messages' => $stats['data']['messages'] ?? [],
			'show' => false,
			'deferred' => $stats['deferred'],
			'refresh' => $stats['refresh'],
		];

		foreach (['topics', 'boards', 'messages'] as $type)
		{
			if (!empty($context['likestats'][$type]) && empty($context['likestats'][$type]['noDataMessage']))
			{
				$context['likestats']['show'] = true;
				break;
			}
		}

		// A stale cache still gets the block, with a placeholder that the ajax
		// request fills in once the data has been generated.
		if ($context['likestats']['show'] || $context['likestats']['deferred'])
		{
			$context['info_center_callbacks'][] = 'show_likestats';
		}

		if ($context['likestats']['refresh'] || $context['likestats']['deferred'])
		{
			loadJavascriptFile('boardindex_likestats.js');
			theme()->addJavascriptVar(['elk_likestats_refresh' => 'true']);
			theme()->addJavascriptVar([
				'elk_likestats_label_topics' => $txt['like_post_tab_mlt'],
				'elk_likestats_label_messages' => $txt['like_post_tab_mlm'],
				'elk_likestats_label_boards' => $txt['like_post_tab_mlb'],
			], true);
		}
	}
}
