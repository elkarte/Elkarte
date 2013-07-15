<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class contains one likable use, which allows members to like a post
 */
class Likes_Controller extends Action_Controller
{
	/**
	 * Default action method, if a specific methods wasn't
	 * directly called already. Simply forwards to likepost.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// We like you.
		$this->action_likepost();
	}

	/**
	 * Entry point function for likes, permission checks, just makes sure its on
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		// If likes are disabled, we don't go any further
		if (empty($modSettings['likes_enabled']))
			fatal_lang_error('feature_disabled', true);
	}

	/**
	 * Likes a post due to its awesomeness
	 * Permission checks are done in prepare_likes
	 * It redirects back to the referrer afterward.
	 * It is accessed via ?action=like,sa=likepost
	 */
	public function action_likepost()
	{
		global $user_info, $topic;

		$id_liked = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// We like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to like it
		if ($this->prepare_like() && canAccessMessage($id_liked))
		{
			$liked_message = basicMessageInfo($id_liked, true, true);
			if ($liked_message)
				like_post($user_info['id'], $liked_message, '+');
		}

		// Back to where we were, in theory
		redirectexit('topic=' . $topic . '.msg' . $id_liked . '#msg' . $id_liked);
	}

	/**
	 * Unlikes a post that you previosly liked ... no negatives though, hurts feelings :'(
	 * It redirects back to the referrer afterward.
	 * It is accessed via ?action=like,sa=unlikepost.
	 */
	public function action_unlikepost()
	{
		global $user_info, $topic;

		$id_liked = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// We used to like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to unlike it now
		if ($this->prepare_like() && canAccessMessage($id_liked))
		{
			$liked_message = basicMessageInfo($id_liked, true, true);
			if ($liked_message)
				like_post($user_info['id'], $liked_message, '-');
		}

		// Back we go
		redirectexit('topic=' . $topic . '.msg' . $id_liked . '#msg' . $id_liked);
	}

	/**
	 * Checks that few things are in order (in additon to permissions) for likes.
	 * @param type $id_liked
	 * @return type
	 */
	private function prepare_like()
	{
		global $modSettings, $user_info;

		$check = true;

		// Valid request
		checkSession('get');

		// If you're a guest or simply can't do this, we stop
		is_not_guest();
		isAllowedTo('like_posts');

		// Load up the helpers
		require_once(SUBSDIR . '/Likes.subs.php');

		// Maintain our log
		clearLikes(empty($modSettings['likeWaitTime']) ? 0 : $modSettings['likeWaitTime']);

		// Not a moderator/administrator then we do some checking
		if (!empty($modSettings['likeRestrictAdmins']) || !allowedTo('moderate_forum'))
		{
			// Find out if this user has done this recently...
			$check = lastLikeOn($user_info['id']);
		}

		// Past the post threshold?
		if (!$user_info['is_admin'] && !empty($modSettings['likeMinPosts']) && $user_info['posts'] < $modSettings['likeMinPosts'])
			$check = false;

		return $check;
	}
}