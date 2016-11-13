<?php

/**
 * The single function this file contains is used to display the main board index.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

use ElkArte\sources\Frontpage_Interface;

/**
 * BoardIndex_Controller class
 * Displays the main board index
 */
class BoardIndex_Controller extends Action_Controller implements Frontpage_Interface
{
	/**
	 * {@inheritdoc }
	 */
	public static function frontPageHook(&$default_action)
	{
		$default_action = array(
			'controller' => 'BoardIndex_Controller',
			'function' => 'action_boardindex'
		);
	}

	/**
	 * Forwards to the action to execute here by default.
	 *
	 * @see Action_Controller::action_index()
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
	 * - It updates the most online statistics.
	 * - It is accessed by ?action=boardindex.
	 *
	 * @uses the BoardIndex template, and main sub template
	 */
	public function action_boardindex()
	{
		global $txt, $user_info, $modSettings, $context, $settings, $scripturl;

		loadTemplate('BoardIndex');

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl;
		Template_Layers::getInstance()->add('boardindex_outer');

		// Do not let search engines index anything if there is a random thing in $_GET.
		if (!empty($this->_req->query))
			$context['robot_no_index'] = true;

		// Retrieve the categories and boards.
		$boardIndexOptions = array(
			'include_categories' => true,
			'base_level' => 0,
			'parent_id' => 0,
			'set_latest_post' => true,
			'countChildPosts' => !empty($modSettings['countChildPosts']),
		);

		$this->_events->trigger('pre_load', array('boardIndexOptions' => &$boardIndexOptions));

		$boardlist = new Boards_List($boardIndexOptions);
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

		$context['show_buddies'] = !empty($user_info['buddies']);

		// Are we showing all membergroups on the board index?
		if (!empty($settings['show_group_key']))
			$context['membergroups'] = cache_quick_get('membergroup_list', 'subs/Membergroups.subs.php', 'cache_getMembergroupList', array());

		// Track most online statistics? (subs/Members.subs.phpOnline.php)
		if (!empty($modSettings['trackStats']))
			trackStatsUsersOnline($context['num_guests'] + $context['num_users_online']);

		// Retrieve the latest posts if the theme settings require it.
		if (isset($settings['number_recent_posts']) && $settings['number_recent_posts'] > 1)
		{
			$latestPostOptions = array(
				'number_posts' => $settings['number_recent_posts'],
				'id_member' => $user_info['id'],
			);
			if (empty($settings['recent_post_topics']))
			{
				$context['latest_posts'] = cache_quick_get('boardindex-latest_posts:' . md5($user_info['query_wanna_see_board'] . $user_info['language']), 'subs/Recent.subs.php', 'cache_getLastPosts', array($latestPostOptions));
			}
			else
			{
				$context['latest_posts'] = cache_quick_get('boardindex-latest_topics:' . md5($user_info['query_wanna_see_board'] . $user_info['language']), 'subs/Recent.subs.php', 'cache_getLastTopics', array($latestPostOptions));
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
			$context['info_center_callbacks'][] = 'recent_posts';

		if (!empty($settings['show_stats_index']))
			$context['info_center_callbacks'][] = 'show_stats';

		$context['info_center_callbacks'][] = 'show_users';

		$this->_events->trigger('post_load', array('callbacks' => &$context['info_center_callbacks']));

		addJavascriptVar(array(
			'txt_mark_as_read_confirm' => $txt['mark_as_read_confirm']
		), true);

		// Mark read button
		$context['mark_read_button'] = array(
			'markread' => array(
				'text' => 'mark_as_read',
				'image' => 'markread.png',
				'lang' => true,
				'custom' => 'onclick="return markallreadButton(this);"',
				'url' => $scripturl . '?action=markasread;sa=all;bi;' . $context['session_var'] . '=' . $context['session_id']
			),
		);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_mark_read_button');
		if (!empty($context['info_center_callbacks']))
		{
			Template_Layers::getInstance()->add('info_center');
		}
	}

	/**
	 * Collapse or expand a category
	 *
	 * - accessed by ?action=collapse
	 */
	public function action_collapse()
	{
		global $user_info, $context;

		// Just in case, no need, no need.
		$context['robot_no_index'] = true;

		checkSession('request');

		if (!isset($this->_req->query->sa))
			Errors::instance()->fatal_lang_error('no_access', false);

		// Check if the input values are correct.
		if (in_array($this->_req->query->sa, array('expand', 'collapse', 'toggle')) && isset($this->_req->query->c))
		{
			// And collapse/expand/toggle the category.
			require_once(SUBSDIR . '/Categories.subs.php');
			collapseCategories(array((int) $this->_req->query->c), $this->_req->query->sa, array($user_info['id']));
		}

		// And go back to the board index.
		$this->action_boardindex();
	}
}