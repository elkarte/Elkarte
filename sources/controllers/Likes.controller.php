<?php

/**
 * Used to allow users to like or unlike a post or an entire topic
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
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
		global $user_info, $topic, $modSettings;

		$id_liked = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// We like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to like it
		if ($this->prepare_like() && canAccessMessage($id_liked))
		{
			$liked_message = basicMessageInfo($id_liked, true, true);
			if ($liked_message)
			{
				likePost($user_info['id'], $liked_message, '+');

				// Lets add in a mention to the member that just had their post liked
				if (!empty($modSettings['mentions_enabled']))
				{
					require_once(CONTROLLERDIR . '/Mentions.controller.php');
					$mentions = new Mentions_Controller();
					$mentions->setData(array(
						'id_member' => $liked_message['id_member'],
						'type' => 'like',
						'id_msg' => $id_liked,
					));
					$mentions->action_add();
				}
			}
		}

		// Back to where we were, in theory
		redirectexit('topic=' . $topic . '.msg' . $id_liked . '#msg' . $id_liked);
	}

	/**
	 * Unlikes a post that you previously liked ... no negatives though, hurts feelings :'(
	 * It redirects back to the referrer afterward.
	 * It is accessed via ?action=like,sa=unlikepost.
	 */
	public function action_unlikepost()
	{
		global $user_info, $topic, $modSettings;

		$id_liked = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// We used to like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to unlike it now
		if ($this->prepare_like() && canAccessMessage($id_liked))
		{
			$liked_message = basicMessageInfo($id_liked, true, true);
			if ($liked_message)
			{
				likePost($user_info['id'], $liked_message, '-');

				// Oh noes, taking the like back, let them know so they can complain
				if (!empty($modSettings['mentions_enabled']))
				{
					require_once(CONTROLLERDIR . '/Mentions.controller.php');
					$mentions = new Mentions_Controller();
					$mentions->setData(array(
						'id_member' => $liked_message['id_member'],
						'type' => 'rlike',
						'id_msg' => $id_liked,
					));

					if (!empty($modSettings['mentions_dont_notify_rlike']))
						$mentions->action_rlike();
					else
						$mentions->action_add();
				}
			}
		}

		// Back we go
		if (!isset($_REQUEST['profile']))
			redirectexit('topic=' . $topic . '.msg' . $id_liked . '#msg' . $id_liked);
		else
			redirectexit('action=profile;area=showlikes;sa=given;u=' .$user_info['id']);
	}

	/**
	 * Checks that few things are in order (in addition to permissions) for likes.
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

	/**
	 * Dispatch to show all the posts you liked OR all your posts liked
	 */
	public function action_showProfileLikes()
	{
		// Load in our helper functions
		require_once(SUBSDIR . '/List.class.php');
		require_once(SUBSDIR . '/Likes.subs.php');

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'received')
			$this->_action_showReceived();
		else
			$this->_action_showGiven();
	}

	/**
	 * Shows all posts that they have liked
	 */
	private function _action_showGiven()
	{
		global $context, $txt, $scripturl;

		$memID = currentMemberID();

		// Build the listoption array to display the like data
		$listOptions = array(
			'id' => 'view_likes',
			'title' => $txt['likes'],
			'items_per_page' => 25,
			'no_items_label' => $txt['likes_none_given'],
			'base_href' => $scripturl . '?action=profile;area=showlikes;sa=given;u=' . $memID,
			'default_sort_col' => 'subject',
			'get_items' => array(
				'function' => array($this, 'list_loadLikesPosts'),
				'params' => array(
					$memID,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getLikesCount'),
				'params' => array(
					$memID,
					true
				),
			),
			'columns' => array(
				'subject' => array(
					'header' => array(
						'value' => $txt['subject'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'subject',
					),
					'sort' => array(
						'default' => 'm.subject DESC',
						'reverse' => 'm.subject',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['board'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'name',
					),
					'sort' => array(
						'default' => 'b.name ',
						'reverse' => 'b.name DESC',
					),
				),
				'poster_name' => array(
					'header' => array(
						'value' => $txt['username'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'poster_name',
					),
					'sort' => array(
						'default' => 'm.poster_name ',
						'reverse' => 'm.poster_name DESC',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['delete'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$row', '
							global $txt, $settings;

							$result = \'<a href="\' . $row[\'delete\'] . \'" onclick="return confirm(\\\'\' . $txt[\'likes_confirm_delete\'] . \'\\\');" title="\' . $txt[\'likes_delete\'] . \'"><img src="\' . $settings[\'images_url\'] . \'/icons/delete.png" alt="" /></a>\';

							return $result;'
						),
						'class' => "centertext",
						'style' => "width: 10%",
					),
				),
			),
		);

		// Menu tabs
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['likes_given'],
			'class' => 'star',
		);

		// Set the context values
		$context['page_title'] = $txt['likes'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_likes';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Shows all posts that others have liked of theirs
	 */
	private function _action_showReceived()
	{
		global $context, $txt, $scripturl;

		$memID = currentMemberID();

		// Build the listoption array to display the data
		$listOptions = array(
			'id' => 'view_likes',
			'title' => $txt['likes'],
			'items_per_page' => 25,
			'no_items_label' => $txt['likes_none_received'],
			'base_href' => $scripturl . '?action=profile;area=showlikes;sa=received;u=' . $memID,
			'default_sort_col' => 'subject',
			'get_items' => array(
				'function' => array($this, 'list_loadLikesReceived'),
				'params' => array(
					$memID,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getLikesCount'),
				'params' => array(
					$memID,
					false,
				),
			),
			'columns' => array(
				'subject' => array(
					'header' => array(
						'value' => $txt['subject'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'subject',
					),
					'sort' => array(
						'default' => 'm.subject DESC',
						'reverse' => 'm.subject',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['board'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'name',
					),
					'sort' => array(
						'default' => 'b.name',
						'reverse' => 'b.name DESC',
					),
				),
				'likes' => array(
					'header' => array(
						'value' => $txt['likes'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'likes',
					),
					'sort' => array(
						'default' => 'likes',
						'reverse' => 'likes DESC',
					),
				),
				'action' => array(
					'header' => array(
						'value' => $txt['show'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$row', '
							global $txt, $settings;

							$result = \'<a href="\' . $row[\'who\'] . \'" title="\' . $txt[\'likes_show_who\'] . \'"><img src="\' . $settings[\'images_url\'] . \'/icons/members.png" alt="" /></a>\';

							return $result;'
						),
						'class' => "centertext",
						'style' => "width: 10%",
					),
				),
			),
		);

		// Menu tabs
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['likes_received'],
			'class' => 'star',
		);

		// Set the context values
		$context['page_title'] = $txt['likes'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_likes';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Function to return an array of users that liked a particular
	 * message.  Used in profile so a user can see the full
	 * list of members, vs the truncated (optional) one shown in message display
	 *
	 * @param int $message
	 */
	public function action_showWhoLiked($message)
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/List.class.php');
		require_once(SUBSDIR . '/Likes.subs.php');
		loadLanguage('Profile');

		// Get the message in question
		$message = isset($_REQUEST['msg']) ? $_REQUEST['msg'] : 0;

		// Build the listoption array to display the data
		$listOptions = array(
			'id' => 'view_likers',
			'title' => $txt['likes_by'],
			'items_per_page' => 25,
			'no_items_label' => $txt['likes_none_given'],
			'base_href' => $scripturl . '?action=likes;sa=showWhoLiked;msg=' . $message,
			'default_sort_col' => 'member',
			'get_items' => array(
				'function' => array($this, 'list_loadPostLikers'),
				'params' => array(
					$message,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getMessageLikeCount'),
				'params' => array(
					$message,
				),
			),
			'columns' => array(
				'member' => array(
					'header' => array(
						'value' => $txt['members'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'link',
					),
					'sort' => array(
						'default' => 'm.real_name DESC',
						'reverse' => 'm.real_name',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<a class="linkbutton_right" href="javascript:history.go(-1)">' . $txt['back'] . '</a>',
				),
			),
		);

		// Set the context values
		$context['page_title'] = $txt['likes_by'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_likers';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Callback for createList(),
	 * Returns the number of likes a member has given if given = true
	 * else the number of posts (not likes) of theirs that have been liked
	 *
	 * @param int $memberID
	 * @param boolean $given
	 */
	public function list_getLikesCount($memberID, $given)
	{
		return likesCount($memberID, $given);
	}

	/**
	 * Callback for createList(),
	 * Returns the number of likes a message has received
	 *
	 * @param int $messageID
	 */
	public function list_getMessageLikeCount($messageID)
	{
		return messageLikeCount($messageID);
	}

	/**
	 * Callback for createList()
	 * Returns a list of liked posts for a memmber
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $memberID
	 */
	public function list_loadLikesPosts($start, $items_per_page, $sort, $memberID)
	{
		// Get all of our liked posts
		return likesPostsGiven($start, $items_per_page, $sort, $memberID);
	}

	/**
	 * Callback for createList()
	 * Returns a list of received likes based on posts
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $memberID
	 */
	public function list_loadLikesReceived($start, $items_per_page, $sort, $memberID)
	{
		// Get a list of all posts (of a members) that have been liked
		return likesPostsReceived($start, $items_per_page, $sort, $memberID);
	}

	/**
	 * Callback for createList()
	 * Returns a list of members that liked a post
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $messageID
	 */
	public function list_loadPostLikers($start, $items_per_page, $sort, $messageID)
	{
		// Get a list of this posts likers
		return postLikers($start, $items_per_page, $sort, $messageID);
	}
}