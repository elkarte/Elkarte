<?php

/**
 * Used to allow users to like or unlike a post or an entire topic
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\Notifications\Notifications;
use ElkArte\Notifications\NotificationsTask;
use ElkArte\User;

/**
 * This class contains one likable use, which allows members to like a post
 *
 * @package Likes
 */
class Likes extends AbstractController
{
	/** @var array Holds the ajax response */
	protected $_likes_response = [];

	/** @var int The id of the message being liked */
	protected $_id_liked;

	/**
	 * Entry point function for likes, permission checks, just makes sure it's on
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		// If likes are disabled, we don't go any further
		if (empty($modSettings['likes_enabled']))
		{
			throw new Exception('feature_disabled', true);
		}
	}

	/**
	 * Default action method, if a specific method was not
	 * directly called already. Simply forwards to likepost.
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		global $context;

		$subActions = [
			'likepost' => [$this, 'action_likepost'],
			'unlikepost' => [$this, 'action_unlikepost'],
			'likestats' => [$this, 'action_likestats'],
		];

		// We may or may not like you.
		$action = new Action('likes');
		$subAction = $action->initialize($subActions, 'likepost');
		$context['sub_action'] = $subAction;

		$action->dispatch($subAction);
	}

	/**
	 * Likes a post due to its awesomeness
	 *
	 * What it does:
	 *
	 * - Permission checks are done in prepare_likes
	 * - It redirects back to the referrer afterward.
	 * - It is accessed via ?action=like,sa=likepost
	 */
	public function action_likepost(): void
	{
		global $topic;

		if ($this->getApi() === 'json')
		{
			$this->action_likepost_api();
			return;
		}

		$this->_doLikePost('+', 'likemsg');

		redirectexit('topic=' . $topic . '.msg' . $this->_id_liked . '#msg' . $this->_id_liked);
	}

	/**
	 * Actually perform the "like" operation.
	 *
	 * Fills $_likes_response that can be used by likeResponse() to
	 * return a JSON response
	 *
	 * @param string $sign '+' or '-'
	 * @param string $type the type of like 'likemsg' or 'rlikemsg'
	 *
	 * @return bool
	 */
	protected function _doLikePost($sign, $type): bool
	{
		global $modSettings;

		// Accept msg id from POST (preferred) or GET as a fallback
		$this->_id_liked = $this->_req->getRequest('msg', 'intval', 0);

		// We like these
		require_once(SUBSDIR . '/Likes.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Have to be able to access it to like/unlike it
		if ($this->prepare_like() && canAccessMessage($this->_id_liked))
		{
			$liked_message = basicMessageInfo($this->_id_liked, true, true);
			if ($liked_message && empty($liked_message['locked']))
			{
				// Like it
				$likeResult = likePost($this->user->id, $liked_message, $sign);

				// Let's add in a mention to the member that just had their post liked/unliked
				if (($likeResult === true) && !empty($modSettings['mentions_enabled']))
				{
					$notifier = Notifications::instance();
					$notifier->add(new NotificationsTask(
						$type,
						$this->_id_liked,
						$this->user->id,
						['id_members' => [$liked_message['id_member']], 'rlike_notif' => $type === 'rlikemsg', 'subject' => $liked_message['subject']]
					));
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Checks that few things are in order (in addition to permissions) for likes.
	 */
	private function prepare_like()
	{
		global $modSettings, $txt;

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
			$check = lastLikeOn($this->user->id);
		}

		// Past the post threshold?
		if ($this->user->is_admin === false && !empty($modSettings['likeMinPosts']) && $this->user->posts < $modSettings['likeMinPosts'])
		{
			$check = false;
		}

		// If they have exceeded their limits, provide a message for the ajax response
		if ($check === false)
		{
			Txt::load('Errors');
			$wait = $modSettings['likeWaitTime'] > 60 ? round($modSettings['likeWaitTime'] / 60, 2) : $modSettings['likeWaitTime'];
			$error = sprintf($txt['like_wait_time'], $wait, ($modSettings['likeWaitTime'] < 60 ? strtolower($txt['minutes']) : $txt['hours']));
			$this->_likes_response = ['result' => false, 'data' => $error];
		}

		return $check;
	}

	/**
	 * Liking a post via ajax
	 *
	 * Calls the standard like method and then the api return method
	 */
	public function action_likepost_api(): void
	{
		global $txt;

		// An error if not possible to like.
		if (!$this->_doLikePost('+', 'likemsg') && empty($this->_likes_response))
		{
			Txt::load('Errors');
			$this->_likes_response = ['result' => false, 'data' => $txt['like_unlike_error']];
		}

		$this->likeResponse();
	}

	/**
	 * When liking / unliking via ajax, clears the templates and returns a JSON
	 * response to the page
	 */
	private function likeResponse(): void
	{
		global $context, $txt;

		// Make room for ajax
		setJsonTemplate();

		// No errors, build the new button tag
		if (empty($this->_likes_response))
		{
			$details = loadLikes($this->_id_liked);
			$count = empty($details) ? 0 : $details[$this->_id_liked]['count'];
			$youLiked = $count !== 0 && array_key_exists(User::$info->id, $details[$this->_id_liked]['member']);
			$text = $count !== 0 ? ($youLiked ? $txt['unlike_post'] : $txt['likes']) : $txt['like_post'];
			$title = empty($details) ? '' : $txt['liked_by'] . ' ' . implode(', ', $details[$this->_id_liked]['member']);
			$this->_likes_response = [
				'result' => true,
				'text' => $text,
				'count' => $count,
				'title' => $title
			];
		}

		// Provide the response
		$context['json_data'] = $this->_likes_response;
	}

	/**
	 * Unliking a post via ajax
	 *
	 * Calls the standard unlike method and then the api return method
	 */
	public function action_unlikepost_api(): void
	{
		global $txt;

		// An error if not possible to like.
		if (!$this->_doLikePost('-', 'rlikemsg') && empty($this->_likes_response))
		{
			Txt::load('Errors');
			$this->_likes_response = ['result' => false, 'data' => $txt['like_unlike_error']];
		}

		$this->likeResponse();
	}

	/**
	 * Unlikes a post that you previously liked ... no negatives though, hurts feelings :'(
	 *
	 * - It redirects back to the referrer afterward.
	 * - It is accessed via ?action=like,sa=unlikepost.
	 */
	public function action_unlikepost(): void
	{
		global $topic;

		if ($this->getApi() === 'json')
		{
			$this->action_unlikepost_api();
			return;
		}

		$this->_doLikePost('-', 'rlikemsg');

		// No longer liked, return to whence you came
		if (!isset($this->_req->query->profile))
		{
			redirectexit('topic=' . $topic . '.msg' . $this->_id_liked . '#msg' . $this->_id_liked);
		}
		else
		{
			redirectexit('action=profile;area=showlikes;sa=given;u=' . $this->user->id);
		}
	}

	/**
	 * Dispatch to show all the posts you liked OR all your posts liked
	 */
	public function action_showProfileLikes(): void
	{
		// Load in our helper functions
		require_once(SUBSDIR . '/Likes.subs.php');

		if ($this->_req->getQuery('sa') === 'received')
		{
			$this->_action_showReceived();
		}
		else
		{
			$this->_action_showGiven();
		}
	}

	/**
	 * Shows all posts that others have liked of theirs
	 */
	private function _action_showReceived(): void
	{
		global $context, $txt;

		$memID = currentMemberID();
		$name = MembersList::get($memID)->real_name;

		// Build the listoption array to display the data
		$listOptions = [
			'id' => 'view_likes',
			'title' => $txt['likes'],
			'items_per_page' => 25,
			'no_items_label' => $txt['likes_none_received'],
			'base_href' => getUrl('profile', ['action' => 'profile', 'area' => 'showlikes', 'sa' => 'received', 'u' => $memID, 'name' => $name]),
			'default_sort_col' => 'subject',
			'get_items' => [
				'function' => fn($start, $items_per_page, $sort, $memberID) => $this->list_loadLikesReceived($start, $items_per_page, $sort, $memberID),
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => fn($memberID, $given) => $this->list_getLikesCount($memberID, $given),
				'params' => [
					$memID,
					false,
				],
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => $txt['subject'],
					],
					'data' => [
						'db' => 'subject',
					],
					'sort' => [
						'default' => 'm.subject DESC',
						'reverse' => 'm.subject',
					],
				],
				'name' => [
					'header' => [
						'value' => $txt['board'],
					],
					'data' => [
						'db' => 'name',
					],
					'sort' => [
						'default' => 'b.name',
						'reverse' => 'b.name DESC',
					],
				],
				'likes' => [
					'header' => [
						'value' => $txt['likes'],
					],
					'data' => [
						'db' => 'likes',
					],
					'sort' => [
						'default' => 'likes',
						'reverse' => 'likes DESC',
					],
				],
				'action' => [
					'header' => [
						'value' => $txt['show'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($row) {
							global $txt;
							return '<a href="' . $row['who'] . '" title="' . $txt['likes_show_who'] . '"><i class="icon i-users"></i></a>';
						},
						'class' => 'centertext',
						'style' => 'width: 10%',
					],
				],
			],
		];

		// Menu tabs
		$context[$context['profile_menu_name']]['object']->prepareTabData([
			'title' => $txt['likes'],
			'description' => $txt['likes_received'],
			'class' => 'i-thumbsup',
		]);

		// Set the context values
		$context['page_title'] = $txt['likes'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_likes';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Callback for createList()
	 * Returns a list of received likes based on posts
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $memberID
	 *
	 * @return array
	 */
	public function list_loadLikesReceived($start, $items_per_page, $sort, $memberID): array
	{
		// Get a list of all posts (of a member) that have been liked
		return likesPostsReceived($start, $items_per_page, $sort, $memberID);
	}

	/**
	 * Callback for createList(),
	 * Returns the number of likes a member has given if given = true
	 * else the number of posts (not likes) of theirs that have been liked
	 *
	 * @param int $memberID
	 * @param bool $given
	 *
	 * @return int
	 */
	public function list_getLikesCount($memberID, $given): int
	{
		return likesCount($memberID, $given);
	}

	/**
	 * Shows all posts that they have liked
	 */
	private function _action_showGiven(): void
	{
		global $context, $txt;

		$memID = currentMemberID();
		$name = MembersList::get($memID)->real_name;

		// Build the listoption array to display the like data
		$listOptions = [
			'id' => 'view_likes',
			'title' => $txt['likes'],
			'items_per_page' => 25,
			'no_items_label' => $txt['likes_none_given'],
			'base_href' => getUrl('profile', ['action' => 'profile', 'area' => 'showlikes', 'sa' => 'given', 'u' => $memID, 'name' => $name]),
			'default_sort_col' => 'subject',
			'get_items' => [
				'function' => fn($start, $items_per_page, $sort, $memberID) => $this->list_loadLikesPosts($start, $items_per_page, $sort, $memberID),
				'params' => [
					$memID,
				],
			],
			'get_count' => [
				'function' => fn($memberID, $given) => $this->list_getLikesCount($memberID, $given),
				'params' => [
					$memID,
					true
				],
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => $txt['subject'],
					],
					'data' => [
						'db' => 'subject',
					],
					'sort' => [
						'default' => 'm.subject DESC',
						'reverse' => 'm.subject',
					],
				],
				'name' => [
					'header' => [
						'value' => $txt['board'],
					],
					'data' => [
						'db' => 'name',
					],
					'sort' => [
						'default' => 'b.name ',
						'reverse' => 'b.name DESC',
					],
				],
				'poster_name' => [
					'header' => [
						'value' => $txt['username'],
					],
					'data' => [
						'db' => 'poster_name',
					],
					'sort' => [
						'default' => 'm.poster_name ',
						'reverse' => 'm.poster_name DESC',
					],
				],
				'action' => [
					'header' => [
						'value' => $txt['delete'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($row) {
							global $txt;
							return '<a href="' . $row['delete'] . '" onclick="return confirm(\'' . $txt['likes_confirm_delete'] . '\');" title="' . $txt['likes_delete'] . '"><i class="icon i-delete"></i></a>';
						},
						'class' => 'centertext',
						'style' => 'width: 10%',
					],
				],
			],
		];

		// Menu tabs
		$context[$context['profile_menu_name']]['object']->prepareTabData([
			'title' => $txt['likes'],
			'description' => $txt['likes_given'],
			'class' => 'i-thumbsup',
		]);

		// Set the context values
		$context['page_title'] = $txt['likes'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_likes';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Callback for createList()
	 * Returns a list of liked posts for a member
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $memberID
	 *
	 * @return array
	 */
	public function list_loadLikesPosts($start, $items_per_page, $sort, $memberID): array
	{
		// Get all of our liked posts
		return likesPostsGiven($start, $items_per_page, $sort, $memberID);
	}

	/**
	 * Function to return an array of users that liked a particular message.
	 *
	 * - Used in profile, so a user can see the full list of members, vs. the
	 * truncated (optional) one shown in message display
	 * - Accessed by ?action=likes;sa=showWhoLiked;msg=x
	 */
	public function action_showWhoLiked(): void
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Likes.subs.php');
		Txt::load('Profile');

		// Get the message in question
		$message = $this->_req->getQuery('msg', 'intval', 0);

		// Build the listoption array to display the data
		$listOptions = [
			'id' => 'view_likers',
			'title' => $txt['likes_by'],
			'items_per_page' => 25,
			'no_items_label' => $txt['likes_none_given'],
			'base_href' => getUrl('action', ['action' => 'likes', 'sa' => 'showWhoLiked', 'msg' => $message]),
			'default_sort_col' => 'member',
			'get_items' => [
				'function' => fn($start, $items_per_page, $sort, $messageID) => $this->list_loadPostLikers($start, $items_per_page, $sort, $messageID),
				'params' => [
					$message,
				],
			],
			'get_count' => [
				'function' => fn($messageID) => $this->list_getMessageLikeCount($messageID),
				'params' => [
					$message,
				],
			],
			'columns' => [
				'member' => [
					'header' => [
						'value' => $txt['members'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'link',
					],
					'sort' => [
						'default' => 'm.real_name DESC',
						'reverse' => 'm.real_name',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '<a class="linkbutton" href="javascript:history.go(-1)">' . $txt['back'] . '</a>',
				],
			],
		];

		// Set the context values
		$context['page_title'] = $txt['likes_by'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'view_likers';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Callback for createList()
	 * Returns a list of members that liked a post
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $messageID
	 *
	 * @return array
	 */
	public function list_loadPostLikers($start, $items_per_page, $sort, $messageID): array
	{
		// Get a list of these posts likers
		return postLikers($start, $items_per_page, $sort, $messageID);
	}

	/**
	 * Callback for createList(),
	 * Returns the number of likes a message has received
	 *
	 * @param int $messageID
	 *
	 * @return int
	 */
	public function list_getMessageLikeCount($messageID): int
	{
		return messageLikeCount($messageID);
	}

	/**
	 * Like stats controller function, used by API calls.
	 *
	 * What it does:
	 *
	 * - Validates whether user is allowed to see stats or not.
	 * - Decides which tab data to fetch and show to user.
	 */
	public function action_likestats_api(): void
	{
		global $context;

		checkSession('get');

		Txt::load('LikePosts');

		$subActions = [
			'messagestats' => [$this, 'action_messageStats'],
			'topicstats' => [$this, 'action_topicStats'],
			'boardstats' => [$this, 'action_boardStats'],
			'mostlikesreceiveduserstats' => [$this, 'action_mostLikesReceivedUserStats'],
			'mostlikesgivenuserstats' => [$this, 'action_mostLikesGivenUserStats'],
		];

		// Set up the action controller
		$action = new Action('likesstats');

		// Pick the correct sub-action, call integrate_sa_likesstats
		$subAction = $action->initialize($subActions, 'messagestats', 'area');
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Like stats controller function.
	 *
	 * What it does:
	 *
	 * - Validates whether user is allowed to see stats or not.
	 * - Presents a general page without data that will be fully loaded by API calls.
	 * - Used when call is made from the main menu selection
	 */
	public function action_likestats(): void
	{
		global $context, $txt, $modSettings;

		require_once(SUBSDIR . '/Likes.subs.php');

		// Likes are not on, your quest for statistics ends here
		if (empty($modSettings['likes_enabled']))
		{
			throw new Exception('feature_disabled', true);
		}

		// Worthy to view like statistics?
		isAllowedTo('like_posts_stats');

		if ($this->getApi() === 'json')
		{
			$this->action_likestats_api();
			return;
		}

		// Load the required files
		Txt::load('LikePosts');
		loadJavascriptFile('like_posts.js');
		theme()->getTemplates()->load('LikePostsStats');

		// Template and tab data
		$context['page_title'] = $txt['like_post_stats'];
		$context['like_posts']['tab_desc'] = $txt['like_posts_stats_desc'];
		$context['lp_stats_tabs'] = [
			'messagestats' => [
				'label' => $txt['like_post_message'],
				'id' => 'messagestats',
			],
			'topicstats' => [
				'label' => $txt['like_post_topic'],
				'id' => 'topicstats',
			],
			'boardstats' => [
				'label' => $txt['like_post_board'],
				'id' => 'boardstats',
			],
			'usergivenstats' => [
				'label' => $txt['like_post_tab_mlmember'],
				'id' => 'mostlikesreceiveduserstats',
			],
			'userreceivedstats' => [
				'label' => $txt['like_post_tab_mlgmember'],
				'id' => 'mostlikesgivenuserstats',
			],
		];
		$context['sub_template'] = 'lp_stats';
	}

	/**
	 * Determines the most liked message in the system
	 *
	 * What it does:
	 *
	 * - Fetches the most liked message data
	 * - Returns the data via ajax
	 */
	public function action_messageStats(): void
	{
		global $txt;

		$data = null;

		// Let's get the statistics!
		if (Cache::instance()->getVar($data, 'likestats_message:' . md5($this->user->query_wanna_see_board), 900) === false)
		{
			$data = dbMostLikedMessage();
			Cache::instance()->put('likestats_message:' . md5($this->user->query_wanna_see_board), $data, 900);
		}

		// Set the response
		if (!empty($data))
		{
			$this->_likes_response = ['result' => true, 'data' => $data];
		}
		else
		{
			$this->_likes_response = ['result' => false, 'error' => $txt['like_post_error_something_wrong']];
		}

		// Off we go
		$this->likeResponse();
	}

	/**
	 * Determine the most liked topics in the system
	 *
	 * What it does:
	 *
	 * - Gets the most liked topics in the system
	 * - Returns the data via ajax
	 */
	public function action_topicStats(): void
	{
		global $txt;

		$data = null;

		if (Cache::instance()->getVar($data, 'likestats_topic:' . md5($this->user->query_wanna_see_board), 900) === false)
		{
			$data = dbMostLikedTopic();
			Cache::instance()->put('likestats_topic:' . md5($this->user->query_wanna_see_board), $data, 900);
		}

		if (!empty($data))
		{
			$this->_likes_response = ['result' => true, 'data' => $data];
		}
		else
		{
			$this->_likes_response = ['result' => false, 'error' => $txt['like_post_error_something_wrong']];
		}

		$this->likeResponse();
	}

	/**
	 * Fetches the most liked board data
	 * Returns the data via ajax
	 */
	public function action_boardStats(): void
	{
		global $txt;

		$data = null;

		if (Cache::instance()->getVar($data, 'likestats_board:' . md5($this->user->query_wanna_see_board), 3600) === false)
		{
			$data = dbMostLikedBoard();
			Cache::instance()->put('likestats_board:' . md5($this->user->query_wanna_see_board), $data, 3600);
		}

		if (!empty($data))
		{
			$this->_likes_response = ['result' => true, 'data' => $data];
		}
		else
		{
			$this->_likes_response = ['result' => false, 'error' => $txt['like_post_error_something_wrong']];
		}

		$this->likeResponse();
	}

	/**
	 * Fetches the data for the highest likes received user
	 * Returns the data via ajax
	 */
	public function action_mostLikesReceivedUserStats(): void
	{
		global $txt;

		$data = null;

		if (Cache::instance()->getVar($data, 'likestats_most_received:' . md5($this->user->query_wanna_see_board), 900) === false)
		{
			$data = dbMostLikesReceivedUser();
			Cache::instance()->put('likestats_most_received:' . md5($this->user->query_wanna_see_board), $data, 900);
		}

		if (!empty($data))
		{
			$this->_likes_response = ['result' => true, 'data' => $data];
		}
		else
		{
			$this->_likes_response = ['result' => false, 'error' => $txt['like_post_error_something_wrong']];
		}

		$this->likeResponse();
	}

	/**
	 * Retrieves the most like giving user
	 *
	 * Returns the data via ajax
	 */
	public function action_mostLikesGivenUserStats(): void
	{
		global $txt;

		$data = null;

		if (Cache::instance()->getVar($data, 'likestats_most_given:' . md5($this->user->query_wanna_see_board), 900) === false)
		{
			$data = dbMostLikesGivenUser();
			Cache::instance()->put('likestats_most_given:' . md5($this->user->query_wanna_see_board), $data, 900);
		}

		if (!empty($data))
		{
			$this->_likes_response = ['result' => true, 'data' => $data];
		}
		else
		{
			$this->_likes_response = ['result' => false, 'error' => $txt['like_post_error_something_wrong']];
		}

		$this->likeResponse();
	}
}
