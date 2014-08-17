<?php

/**
 * Used to allow users to like or unlike a post or an entire topic
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class contains one likable use, which allows members to like a post
 *
 * @package Likes
 */
class Likes_Controller extends Action_Controller
{
	/**
	 * Holds the ajax response
	 * @var string[]
	 */
	protected $_likes_response = array();

	/**
	 * If this was an ajax request or not
	 * @var boolean
	 */
	protected $_api = false;

	/**
	 * The id of the message being liked
	 * @var int
	 */
	protected $_id_liked = null;

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
	 * Liking a post via ajax, then _api will be added to the sa=
	 * and this method will be called
	 * Calls the standard like method and then the api return method
	 */
	public function action_likepost_api()
	{
		$this->_api = true;
		$this->action_likepost();
	}

	/**
	 * Un liking a post via ajax
	 * Calls the standard unlike method and then the api return method
	 */
	public function action_unlikepost_api()
	{
		$this->_api = true;
		$this->action_unlikepost();
	}

	/**
	 * Likes a post due to its awesomeness
	 * Permission checks are done in prepare_likes
	 * It redirects back to the referrer afterward.
	 * It is accessed via ?action=like,sa=likepost
	 */
	public function action_likepost()
	{
		global $user_info, $topic, $txt, $modSettings;

		$this->_id_liked = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// We like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to like it
		if ($this->prepare_like() && canAccessMessage($this->_id_liked))
		{
			$liked_message = basicMessageInfo($this->_id_liked, true, true);
			if ($liked_message)
			{
				// Like it
				$likeResult = likePost($user_info['id'], $liked_message, '+');
				if ($likeResult === true)
				{
					// Lets add in a mention to the member that just had their post liked
					if (!empty($modSettings['mentions_enabled']))
					{
						require_once(CONTROLLERDIR . '/Mentions.controller.php');
						$mentions = new Mentions_Controller();
						$mentions->setData(array(
							'id_member' => $liked_message['id_member'],
							'type' => 'like',
							'id_msg' => $this->_id_liked,
						));
						$mentions->action_add();
					}
				}
				elseif ($this->_api)
					$this->_likes_response = array('result' => false, 'data' => $likeResult);
			}
			elseif ($this->_api)
			{
				loadLanguage('Errors');
				$this->_likes_response = array('result' => false, 'data' => $txt['like_unlike_error']);
			}
		}

		// Back to where we were, in theory
		if ($this->_api)
			$this->likeResponse();
		else
			redirectexit('topic=' . $topic . '.msg' . $this->_id_liked . '#msg' . $this->_id_liked);
	}

	/**
	 * Unlikes a post that you previously liked ... no negatives though, hurts feelings :'(
	 * It redirects back to the referrer afterward.
	 * It is accessed via ?action=like,sa=unlikepost.
	 */
	public function action_unlikepost()
	{
		global $user_info, $topic, $txt, $modSettings;

		$this->_id_liked = !empty($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;

		// We used to like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to unlike it now
		if ($this->prepare_like() && canAccessMessage($this->_id_liked))
		{
			$liked_message = basicMessageInfo($this->_id_liked, true, true);
			if ($liked_message)
			{
				$likeResult = likePost($user_info['id'], $liked_message, '-');
				if ($likeResult === true)
				{
					// Oh noes, taking the like back, let them know so they can complain
					if (!empty($modSettings['mentions_enabled']))
					{
						require_once(CONTROLLERDIR . '/Mentions.controller.php');
						$mentions = new Mentions_Controller();
						$mentions->setData(array(
							'id_member' => $liked_message['id_member'],
							'type' => 'rlike',
							'id_msg' => $this->_id_liked,
						));

						// Notifying that likes were removed ?
						if (!empty($modSettings['mentions_dont_notify_rlike']))
							$mentions->action_rlike();
						else
							$mentions->action_add();
					}
				}
				elseif ($this->_api)
					$this->_likes_response = array('result' => false, 'data' => $likeResult);
			}
			elseif ($this->_api)
			{
				loadLanguage('Errors');
				$this->_likes_response = array('result' => false, 'data' => $txt['like_unlike_error']);
			}
		}

		// Back we go
		if ($this->_api)
			$this->likeResponse();
		elseif (!isset($_REQUEST['profile']))
			redirectexit('topic=' . $topic . '.msg' . $this->_id_liked . '#msg' . $this->_id_liked);
		else
			redirectexit('action=profile;area=showlikes;sa=given;u=' .$user_info['id']);
	}

	/**
	 * When liking / unliking via ajax, clears the templates and returns a json
	 * response to the page
	 */
	private function likeResponse()
	{
		global $context, $txt;

		// Make room for ajax
		loadTemplate('Json');
		$context['sub_template'] = 'send_json';

		// No errors, build the new button tag
		if (empty($this->_likes_response))
		{
			$details = loadLikes($this->_id_liked, true);
			$count = empty($details) ? 0 : $details[$this->_id_liked]['count'];
			$text = $count !== 0 ? $txt['likes'] : $txt['like_post'];
			$title = empty($details) ? '' : $txt['liked_by'] . ' ' . implode(', ', $details[$this->_id_liked]['member']);
			$this->_likes_response = array(
				'result' => true,
				'text' => $text,
				'count' => $count,
				'title' => $title
			);
		}

		// Provide the response
		$context['json_data'] = $this->_likes_response;
	}

	/**
	 * Checks that few things are in order (in addition to permissions) for likes.
	 */
	private function prepare_like()
	{
		global $modSettings, $user_info, $txt;

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

		// If they have exceeded their limits, provide a message for the ajax response
		if ($check === false && $this->_api)
		{
			loadLanguage('Errors');
			$wait = $modSettings['likeWaitTime'] > 60 ? round($modSettings['likeWaitTime'] / 60, 2) : $modSettings['likeWaitTime'];
			$error = sprintf($txt['like_wait_time'], $wait, ($modSettings['likeWaitTime'] < 60 ? strtolower($txt['minutes']) : $txt['hours']));
			$this->_likes_response = array('result' => false, 'data' => $error);
		}

		return $check;
	}

	/**
	 * Dispatch to show all the posts you liked OR all your posts liked
	 */
	public function action_showProfileLikes()
	{
		// Load in our helper functions
		require_once(SUBSDIR . '/GenericList.class.php');
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
						'class' => 'centertext',
						'style' => 'width: 10%',
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
						'class' => 'centertext',
						'style' => 'width: 10%',
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
	 * accessed by ?action=likes;sa=showWhoLiked;msg=x
	 */
	public function action_showWhoLiked()
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/GenericList.class.php');
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
					'class' => 'submitbutton',
					'value' => '<a class="linkbutton" href="javascript:history.go(-1)">' . $txt['back'] . '</a>',
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