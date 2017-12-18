<?php

/**
 * This file contains the database work for likes.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 */

/**
 * Updates the like value for a post/member combo if there are no problems with
 * the request, such as being a narcissist
 *
 * @package Likes
 * @param int $id_liker - user_id of the liker/disliker
 * @param mixed $liked_message - message array that is being worked on
 * @param string $direction - + for like - for unlike a previous liked one
 */
function likePost($id_liker, $liked_message, $direction)
{
	global $txt, $modSettings;

	// If we have a message, then we have passed all checks ...
	if (!empty($liked_message))
	{
		// You can't like your own stuff, no matter how brilliant you think you are
		if ($liked_message['id_member'] == $id_liker && empty($modSettings['likeAllowSelf']))
			return $txt['cant_like_yourself'];
		else
		{
			updateLike($id_liker, $liked_message, $direction);
			return true;
		}
	}
}

/**
 * Loads all of the likes for a group of messages
 * Returns an array of message_id to members who liked that post
 * If prepare is true, will also prep the array for template use
 *
 * @package Likes
 * @param int[]|int $messages
 * @param bool $prepare
 */
function loadLikes($messages, $prepare = true)
{
	$db = database();
	$likes = array();

	if (empty($messages))
		return $likes;

	if (!is_array($messages))
		$messages = (array((int) $messages));

	// Load up them likes from the db
	$request = $db->query('', '
		SELECT
			l.id_member, l.id_msg,
			m.real_name
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}members AS m ON (m.id_member = l.id_member)
		WHERE id_msg IN ({array_int:id_messages})',
		array(
			'id_messages' => $messages,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$likes[$row['id_msg']]['member'][$row['id_member']] = $row['real_name'];

	// Total likes for this group
	foreach ($likes as $msg_id => $like)
		$likes[$msg_id]['count'] = count($like['member']);

	$db->free_result($request);

	if ($prepare)
		$likes = prepareLikes($likes);

	return $likes;
}

/**
 * Prepares the like array for use in the template
 *
 * What it does:
 *
 * - Replaces the current member id with 'You' if they like a post and makes it first
 * - Truncates the like list at a given number and adds in +x others
 *
 * @package Likes
 * @param int[] $likes array of like ids to process
 *
 * @return int[]
 */
function prepareLikes($likes)
{
	global $user_info, $modSettings, $txt;

	// Prepare this like page context for the user
	foreach ($likes as $msg_id => $like)
	{
		// Did they like this message ?
		$you_liked = isset($like['member'][$user_info['id']]);
		if ($you_liked)
			unset($likes[$msg_id]['member'][$user_info['id']]);

		// Any limits on how many to display
		$limit = isset($modSettings['likeDisplayLimit']) ? $modSettings['likeDisplayLimit'] : 0;

		// If there are a lot of likes for this message, we cull the herd
		if ($limit > 0 && $like['count'] > $limit)
		{
			// Mix up the likers so we don't show the same ones every time
			shuffle($likes[$msg_id]['member']);
			$likes[$msg_id]['member'] = array_slice($likes[$msg_id]['member'], 0, $you_liked ? $limit - 1 : $limit);

			// Trick, member id's below $limit will cause a wrong +x others due to the slice above
			if ($user_info['id'] <= $limit)
				$like['count'] += 1;

			// How many others liked this
			$likes[$msg_id]['member'][] = sprintf('%+d %s', ($like['count'] - $limit), $txt['liked_more']);
		}

		// Top billing just for you, the big lights, the grand stage, plus we need that key returned
		if ($you_liked)
			$likes[$msg_id]['member'] = array($user_info['id'] => $txt['liked_you']) + $likes[$msg_id]['member'];
	}

	return $likes;
}

/**
 * Clear the likes log of older actions ... used to prevent a like love fest
 *
 * @package Likes
 * @param int $likeWaitTime
 */
function clearLikes($likeWaitTime)
{
	$db = database();

	// Delete all older items from the log
	$db->query('', '
		DELETE FROM {db_prefix}log_likes
		WHERE {int:current_time} - log_time > {int:wait_time}',
		array(
			'wait_time' => (int) ($likeWaitTime * 60),
			'current_time' => time(),
		)
	);
}

/**
 * Checks if the member has exceeded the number of like actions they are
 * allowed in a given time period.
 *
 * - The log is maintained to the time period by the clearLikes function so
 * the count is always current.
 * - returns true if they can like again, or false if they have to wait a bit
 *
 * @package Likes
 * @param int $id_liker
 */
function lastLikeOn($id_liker)
{
	global $modSettings;

	if (empty($modSettings['likeWaitCount']))
		return true;

	// Find out if, and how many, this user has done recently...
	$db = database();
	$request = $db->query('', '
		SELECT action
		FROM {db_prefix}log_likes
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $id_liker,
		)
	);
	$actions = $db->num_rows($request);
	$db->free_result($request);

	return $actions < $modSettings['likeWaitCount'];
}

/**
 * Perform a like action, either + or -
 *
 * @package Likes
 * @param int $id_liker
 * @param int[] $liked_message
 * @param string $direction - options: - or +
 */
function updateLike($id_liker, $liked_message, $direction)
{
	$db = database();

	// See if they already likeyed this message
	$request = $db->query('', '
		SELECT
			id_member
		FROM {db_prefix}message_likes
		WHERE id_member = {int:id_member}
			AND id_msg = {int:id_msg}
		LIMIT 1',
		array(
			'id_member' => $id_liker,
			'id_msg' => $liked_message['id_msg'],
		)
	);
	$count = $db->num_rows($request);
	$db->free_result($request);

	// Not previously liked, and you want to
	if ($count === 0 && $direction === '+')
	{
		$db->insert('',
			'{db_prefix}message_likes',
			array('id_member' => 'int', 'id_msg' => 'int', 'id_poster' => 'int', 'like_timestamp' => 'int',),
			array($id_liker, $liked_message['id_msg'], $liked_message['id_member'], time()),
			array('id_msg', 'id_member', 'id_poster')
		);

		// If we are liking the first message in a topic, we are de facto liking the topic
		if ($liked_message['id_msg'] === $liked_message['id_first_msg'])
			increaseTopicLikes($liked_message['id_topic'], $direction);

		// And update the stats
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($id_liker, array('likes_given' => '+'));
		updateMemberData($liked_message['id_member'], array('likes_received' => '+'));
	}
	// Or you are just being fickle?
	elseif ($count !== 0 && $direction === '-')
	{
		$db->query('', '
			DELETE FROM {db_prefix}message_likes
			WHERE id_member = {int:id_member}
				AND id_msg = {int:id_msg}',
			array(
				'id_member' => $id_liker,
				'id_msg' => $liked_message['id_msg'],
			)
		);

		// If we are unliking the first message in a topic, we are de facto unliking the topic
		if ($liked_message['id_msg'] === $liked_message['id_first_msg'])
			increaseTopicLikes($liked_message['id_topic'], $direction);

		// And update the stats
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($id_liker, array('likes_given' => '-'));
		updateMemberData($liked_message['id_member'], array('likes_received' => '-'));
	}

	// Put it in the log so we can prevent flooding the system with likes
	$db->insert('replace',
		'{db_prefix}log_likes',
		array('action' => 'string', 'id_target' => 'int', 'id_member' => 'int', 'log_time' => 'int'),
		array($direction, $liked_message['id_msg'], $id_liker, time()),
		array('id_target', 'id_member')
	);
}

/**
 * Increase the number of likes for this topic.
 *
 * @package Likes
 * @param int $id_topic - the topic
 * @param string $direction +/- liking or unliking
 */
function increaseTopicLikes($id_topic, $direction)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}topics
		SET num_likes = num_likes ' . ($direction === '+' ? '+ 1' : '- 1') . '
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $id_topic,
		)
	);
}

/**
 * Return how many likes a user has given or the count of their posts that
 * have received a like (not the total likes received)
 *
 * @package Likes
 * @param int $memberID
 * @param boolean $given
 */
function likesCount($memberID, $given = true)
{
	global $user_profile;

	$db = database();

	// Give is a given, received takes a query so its only the unique messages
	if ($given === true)
		$likes = $user_profile[$memberID]['likes_given'];
	else
	{
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}message_likes
			WHERE id_poster = {int:id_member}
			GROUP BY id_msg',
			array(
				'id_member' => $memberID,
			)
		);
		$likes = $db->num_rows($request);
		$db->free_result($request);
	}

	return $likes;
}

/**
 * Return an array of details based on posts a user has liked
 *
 * Used for action=profile;area=showlikes;sa=given
 *
 * @package Likes
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memberID
 */
function likesPostsGiven($start, $items_per_page, $sort, $memberID)
{
	global $scripturl, $context, $modSettings;

	$db = database();

	// Load up what the user likes from the db
	return $db->fetchQueryCallback('
		SELECT
			l.id_member, l.id_msg,
			m.subject, m.poster_name, m.id_board, m.id_topic,
			b.name
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = l.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE l.id_member = {int:id_member}' . (!empty($modSettings['recycle_enable']) ? ('
			AND b.id_board != ' . $modSettings['recycle_board']) : '') . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'id_member' => $memberID,
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		),
		function ($row) use ($scripturl, $context)
		{
			return array(
				'subject' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'poster_name' => $row['poster_name'],
				'name' => $row['name'],
				'delete' => $scripturl . '?action=likes;sa=unlikepost;profile;msg=' . $row['id_msg'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			);
		}
	);
}

/**
 * Returns an array of details based on posts that others have liked of this user
 * Creates links to show the users who liked a post
 *
 * Used by action=profile;area=showlikes;sa=received
 *
 * @package Likes
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memberID
 */
function likesPostsReceived($start, $items_per_page, $sort, $memberID)
{
	global $scripturl, $modSettings;

	$db = database();

	// Load up what the user likes from the db
	return $db->fetchQueryCallback('
		SELECT
			m.subject, m.id_topic,
			b.name, l.id_msg, COUNT(l.id_msg) AS likes
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = l.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE l.id_poster = {int:id_member}' . (!empty($modSettings['recycle_enable']) ? ('
			AND b.id_board != ' . $modSettings['recycle_board']) : '') . '
		GROUP BY (l.id_msg)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'id_member' => $memberID,
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		),
		function ($row) use ($scripturl)
		{
			return array(
				'subject' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'name' => $row['name'],
				'who' => $scripturl . '?action=likes;sa=showWhoLiked;msg=' . $row['id_msg'],
				'likes' => $row['likes']
			);
		}
	);
}

/**
 * Function to load all of the likers of a message
 *
 * @package Likes
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $messageID
 * @param bool $simple
 */
function postLikers($start, $items_per_page, $sort, $messageID, $simple = true)
{
	global $scripturl;

	$db = database();
	$likes = array();

	if (empty($messageID))
		return $likes;

	// Load up the likes for this message
	return $db->fetchQueryCallback('
		SELECT
			l.id_member, l.id_msg,
			m.real_name' . ($simple === true ? '' : ',
			COALESCE(a.id_attach, 0) AS id_attach,
			a.filename, a.attachment_type, m.avatar, m.email_address') . '
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}members AS m ON (m.id_member = l.id_member)' . ($simple === true ? '' : '
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)') . '
		WHERE l.id_msg = {int:id_message}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'id_message' => $messageID,
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		),
		function ($row) use ($scripturl, $simple)
		{
			$like = array(
				'real_name' => $row['real_name'],
				'id_member' => $row['id_member'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			);
			if ($simple !== true)
			{
				$avatar = determineAvatar($row);
				$like['href'] = !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '';
				$like['avatar'] = $avatar['href'];
			}
			return $like;
		}
	);
}

/**
 * Function to get the number of likes for a message
 *
 * @package Likes
 * @param int $message
 */
function messageLikeCount($message)
{
	$db = database();
	$total = 0;

	if (empty($message))
		return $total;

	// Count up the likes for this message
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}message_likes
		WHERE id_msg = {int:id_message}',
		array(
			'id_message' => $message,
		)
	);
	list ($total) = $db->fetch_row($request);
	$db->free_result($request);

	return (int) $total;
}

/**
 * Function to get most liked messages
 *
 * @param int $limit the number of top liked messages to fetch
 * @package Likes
 */
function dbMostLikedMessage($limit = 10)
{
	global $scripturl, $txt;

	$db = database();

	// Most liked Message
	$request = $db->query('', '
		SELECT
			COALESCE(mem.real_name, m.poster_name) AS member_received_name,
			lp.id_msg, lp.like_count AS like_count,
			m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time, m.smileys_enabled,
			COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.avatar, mem.posts, mem.email_address
		FROM (
				SELECT
					COUNT(lp.id_msg) AS like_count, lp.id_msg
				FROM {db_prefix}message_likes AS lp
				GROUP BY lp.id_msg
				ORDER BY like_count DESC
				LIMIT {int:limit}
			) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
		WHERE {query_wanna_see_board}
		LIMIT {int:limit}',
		array(
			'limit' => $limit,
		)
	);

	$mostLikedMessages = array();
	$bbc_parser = \BBC\ParserWrapper::instance();

	while ($row = $db->fetch_assoc($request))
	{
		// Censor it!
		$row['subject'] = censor($row['subject']);
		$row['body'] = censor($row['body']);

		$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

		// Something short and sweet
		$msgString = Util::shorten_html($row['body'], 255);
		$preview = Util::htmlspecialchars(strtr($msgString, array('<br />' => "\n", '&nbsp;' => ' ')));

		// Love those avatars
		$avatar = determineAvatar($row);

		// Build it out
		$mostLikedMessages[] = array(
			'id_msg' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'id_board' => $row['id_board'],
			'like_count' => $row['like_count'],
			'subject' => $row['subject'],
			'preview' => $preview,
			'body' => $msgString,
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'member_received' => array(
				'id_member' => $row['id_member'],
				'name' => $row['member_received_name'],
				'total_posts' => $row['posts'],
				'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'avatar' => $avatar['href'],
			),
			'member_liked_data' => postLikers(0, 20, 'l.id_member DESC', $row['id_msg'], false),
		);
	}
	$db->free_result($request);

	// No likes in the system?
	if (empty($mostLikedMessages))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	return $mostLikedMessages;
}

/**
 * Function to get most liked messages in a topic
 *
 * What it does:
 *
 * - For a supplied topic gets the, default 5, posts that have been liked
 * - Returns the messages in descending order of likes
 *
 * @param int $topic the topic_id we are going to look for liked posts within
 * @param int $limit the maximum number of liked posts to return
 * @package Likes
 */
function dbMostLikedMessagesByTopic($topic, $limit = 5)
{
	global $scripturl;

	$db = database();
	$bbc_parser = \BBC\ParserWrapper::instance();

	// Most liked messages in a given topic
	return $db->fetchQueryCallback('
		SELECT
			COALESCE(mem.real_name, m.poster_name) AS member_received_name, lp.id_msg,
			m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
			lp.like_count AS like_count,
			COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.posts, m.smileys_enabled, mem.email_address, mem.avatar
		FROM (
				SELECT
					COUNT(lp.id_msg) AS like_count, lp.id_msg
				FROM {db_prefix}message_likes AS lp
				GROUP BY lp.id_msg
				ORDER BY like_count DESC
			) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member AND a.attachment_type = {int:type_avatar})
		WHERE t.id_topic = {int:id_topic}
		ORDER BY lp.like_count DESC
		LIMIT {int:limit}',
		array(
			'id_topic' => $topic,
			'limit' => $limit,
			'type_avatar' => 1,
		),
		function ($row) use ($scripturl, $bbc_parser)
		{
			// Censor those naughty words
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// Something short to show is all that's needed
			$msgString = Util::shorten_html($row['body'], 255);
			$preview = Util::htmlspecialchars(strtr($msgString, array('<br />' => "\n", '&nbsp;' => ' ')));

			$avatar = determineAvatar($row);

			return array(
				'id_msg' => $row['id_msg'],
				'id_topic' => $row['id_topic'],
				'id_board' => $row['id_board'],
				'like_count' => $row['like_count'],
				'subject' => $row['subject'],
				'body' => $msgString,
				'preview' => $preview,
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'member' => array(
					'id_member' => $row['id_member'],
					'name' => $row['member_received_name'],
					'total_posts' => $row['posts'],
					'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
					'avatar' => $avatar['href'],
				),
			);
		}
	);
}

/**
 * Function to get most liked topics.
 *
 *  - Rewards threads that generate distinct likers in fewer posts.  So if a thread generated 20 unique
 * likes in 3 posts vs 20 in 20 posts it would get more weight.
 * - The more unique members that like a thread the more popular it will be.
 * - Adds weight to threads which have posts with many likes vs threads with many posts with many single likes
 * - Can still be gamed but what can you do
 *
 * @package Likes
 * @param null|int $board - An optional board id to find most liked topics in.
 *  If omitted, {query_wanna_see_board} is used to return the most liked topics in the boards
 * they can see
 * @param int $limit - Optional, number of topics to return (default 10).
 */
function dbMostLikedTopic($board = null, $limit = 10)
{
	global $txt;

	$db = database();

	// The most liked topics by sum of likes and distinct likers
	$request = $db->query('', '
		SELECT
			t.id_topic, t.num_replies, t.id_board,
			COUNT(lp.id_msg) AS like_count,
			COUNT(DISTINCT lp.id_member) AS distinct_likers,
			COUNT(DISTINCT m.id_msg) AS num_messages_liked
		FROM {db_prefix}message_likes AS lp
			INNER JOIN {db_prefix}messages AS m ON (lp.id_msg = m.id_msg)
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
		WHERE ' . ($board === null ? '{query_wanna_see_board}' : 'b.id_board = {int:id_board}') . '
		GROUP BY t.id_topic
		ORDER BY distinct_likers DESC
		LIMIT {int:limit}',
		array(
			'id_board' => $board,
			'limit' => $limit * 5,
		)
	);
	$mostLikedTopics = array();
	while ($row = $db->fetch_assoc($request))
	{
		$mostLikedTopics[$row['id_topic']] = $row;

		$log = log($row['like_count'] / ($row['num_replies'] + ($row['num_replies'] == 0 || $row['like_count'] == $row['num_replies'] ? 1 : 0)));
		$distinct_likers = max(1,
			min($row['distinct_likers'],
				1 / ($log == 0 ? 1 : $log)));

		$mostLikedTopics[$row['id_topic']]['relevance'] = $row['distinct_likers'] +
			$row['distinct_likers'] / $row['num_messages_liked'] +
			$distinct_likers;
	}
	$db->free_result($request);

	// Sort the results from the net we cast, then cut it down to the top X limit
	uasort($mostLikedTopics, 'sort_by_relevance');
	$mostLikedTopics = array_slice($mostLikedTopics, 0, $limit);

	// Fetch some sample posts for each of the top X topics
	foreach ($mostLikedTopics as $key => $topic)
	{
		$mostLikedTopics[$key]['msg_data'] = dbMostLikedMessagesByTopic($topic['id_topic']);
	}

	// Looks like there is nothing liked
	if (empty($mostLikedTopics))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	return $mostLikedTopics;
}

/**
 * Helper function to sort by topic like relevance
 *
 * @param float $a
 * @param float $b
 *
 * @return mixed
 */
function sort_by_relevance($a, $b)
{
	return $b['relevance'] - $a['relevance'];
}

/**
 * Function to get most liked board
 *
 * @package Likes
 */
function dbMostLikedBoard()
{
	global $txt;

	$db = database();

	// Most liked board
	$request = $db->query('', '
		SELECT
		 	b.id_board, b.name, b.num_topics, b.num_posts,
			tc.topics_liked, tc.msgs_liked, tc.like_count
		FROM {db_prefix}boards AS b
			INNER JOIN (
				SELECT
					m.id_board,
					COUNT(DISTINCT(m.id_topic)) AS topics_liked,
					COUNT(DISTINCT(lp.id_msg)) AS msgs_liked,
					COUNT(m.id_board) AS like_count
				FROM {db_prefix}message_likes AS lp
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
				WHERE {query_wanna_see_board}
				GROUP BY m.id_board
				ORDER BY like_count DESC
				LIMIT {int:limit}
			) AS tc ON (tc.id_board = b.id_board)
		LIMIT {int:limit}',
		array(
			'limit' => 1
		)
	);
	$mostLikedBoard = $db->fetch_assoc($request);
	$db->free_result($request);

	if (empty($mostLikedBoard['id_board']))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	$mostLikedTopic = dbMostLikedTopic($mostLikedBoard['id_board']);
	$mostLikedBoard['topic_data'] = $mostLikedTopic[0]['msg_data'];

	return $mostLikedBoard;
}

/**
 * Function to get most liked members
 *
 * @package Likes
 * @param int $limit the number of most liked members to return
 */
function dbMostLikesReceivedUser($limit = 10)
{
	global $scripturl, $txt;

	$db = database();

	$request = $db->query('', '
		SELECT
			lp.id_poster, lp.like_count,
			COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			COALESCE(mem.real_name, m.poster_name) AS real_name,
			mem.avatar, mem.date_registered, mem.posts, mem.email_address
		FROM (
			SELECT
				id_poster,
				COUNT(id_msg) AS like_count,
				MAX(id_msg) AS id_msg
			FROM {db_prefix}message_likes
			WHERE id_poster != 0
			GROUP BY id_poster
			ORDER BY like_count DESC
			LIMIT {int:limit}
		) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
		LIMIT {int:limit}',
		array(
			'limit' => $limit
		)
	);
	$mostLikedMembers = array();
	while ($row = $db->fetch_assoc($request))
	{
		$avatar = determineAvatar($row);
		$mostLikedMembers[] = array(
			'member_received' => array(
				'id_member' => $row['id_poster'],
				'name' => $row['real_name'],
				'total_posts' => $row['posts'],
				'date_registered' => $row['date_registered'],
				'href' => !empty($row['id_poster']) ? $scripturl . '?action=profile;u=' . $row['id_poster'] : '',
				'avatar' => $avatar['href'],
			),
			'like_count' => $row['like_count'],
			'post_data' => dbMostLikedPostsByUser($row['id_poster']),
		);
	}
	$db->free_result($request);

	if (empty($mostLikedMembers))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	return $mostLikedMembers;
}

/**
 * Returns the most liked posts of a given user
 *
 * @param int $id_member find top posts for this member id
 * @param int $limit then number of top posts to return
 *
 * @return array
 */
function dbMostLikedPostsByUser($id_member, $limit = 10)
{
	$db = database();
	$bbc_parser = \BBC\ParserWrapper::instance();

	// Lets fetch highest liked posts by this user
	return $db->fetchQueryCallback('
		SELECT
			lp.id_msg, COUNT(lp.id_msg) AS like_count,
			m.body, m.poster_time, m.smileys_enabled, m.id_topic, m.subject
		FROM {db_prefix}message_likes AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_wanna_see_board}
			AND lp.id_poster = {int:id_member}
		GROUP BY lp.id_msg, m.id_topic, m.subject, m.body, m.poster_time, m.smileys_enabled
		ORDER BY like_count DESC
		LIMIT {int:limit}',
		array(
			'id_member' => $id_member,
			'limit' => $limit
		),
		function ($row) use ($bbc_parser)
		{
			// Censor those naughty words
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// Something short to show is all that's needed
			$msgString = Util::shorten_html($row['body'], 255);
			$preview = Util::htmlspecialchars(strtr($msgString, array('<br />' => "\n", '&nbsp;' => ' ')));

			return array(
				'id_topic' => $row['id_topic'],
				'id_msg' => $row['id_msg'],
				'like_count' => $row['like_count'],
				'subject' => $row['subject'],
				'body' => $msgString,
				'preview' => $preview,
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
			);
		}
	);
}

/**
 * Function to get most likes giving user
 *
 * @package Likes
 * @param int $limit the number of members to return
 */
function dbMostLikesGivenUser($limit = 10)
{
	global $scripturl, $txt;

	$db = database();

	$request = $db->query('', '
		SELECT
			lp.id_member, lp.like_count,
			COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			COALESCE(mem.real_name, m.poster_name) AS real_name,
			mem.avatar, mem.date_registered, mem.posts, mem.email_address
		FROM (
			SELECT
				COUNT(id_msg) AS like_count, id_member, MAX(id_msg) AS id_msg
			FROM {db_prefix}message_likes
			GROUP BY id_member
			ORDER BY like_count DESC
			LIMIT {int:limit}
		) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lp.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = lp.id_member)',
		array(
			'limit' => $limit
		)
	);
	$mostLikeGivingMembers = array();
	while ($row = $db->fetch_assoc($request))
	{
		$avatar = determineAvatar($row);

		$mostLikeGivingMembers[] = array(
			'member_given' => array(
				'id_member' => $row['id_member'],
				'name' => $row['real_name'],
				'total_posts' => $row['posts'],
				'date_registered' => $row['date_registered'],
				'href' => !empty($row['id_member_gave']) ? $scripturl . '?action=profile;u=' . $row['id_member_gave'] : '',
				'avatar' => $avatar['href'],
			),
			'like_count' => $row['like_count'],
			'post_data' => dbRecentlyLikedPostsGivenUser($row['id_member'])
		);
	}
	$db->free_result($request);

	if (empty($mostLikeGivingMembers))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	return $mostLikeGivingMembers;
}

/**
 * Returns posts that were recently liked by a given user
 *
 * @param int $id_liker the userid to find recently liked posts
 * @param int $limit number of recently liked posts to fetch
 * @return array
 */
function dbRecentlyLikedPostsGivenUser($id_liker, $limit = 5)
{
	$db = database();
	$bbc_parser = \BBC\ParserWrapper::instance();

	// Lets fetch the latest liked posts by this user
	return $db->fetchQueryCallback('
		SELECT
			m.id_msg, m.id_topic, m.subject, m.body, m.poster_time, m.smileys_enabled
		FROM {db_prefix}message_likes AS ml
			INNER JOIN {db_prefix}messages AS m ON (ml.id_msg = m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_wanna_see_board}
			AND ml.id_member = {int:id_member}
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'id_member' => $id_liker,
			'limit' => $limit
		),
		function ($row) use ($bbc_parser)
		{
			// Censor those $%#^&% words
			$row['body'] = censor($row['body']);
			$row['subject'] = censor($row['subject']);

			$row['body'] = $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']);

			// Something short to show is all that's required
			$msgString = Util::shorten_html($row['body'], 255);
			$preview = Util::htmlspecialchars(strtr($msgString, array('<br />' => "\n", '&nbsp;' => ' ')));

			return array(
				'id_msg' => $row['id_msg'],
				'id_topic' => $row['id_topic'],
				'subject' => $row['subject'],
				'body' => $msgString,
				'preview' => $preview,
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
			);
		}
	);
}

/**
 * Utility function to decrease member like counts when a message is removed
 *
 * When a message is removed, we need update the like counts for those who liked the message
 * as well as those who posted the message
 *  - Members who liked the message have likes given decreased
 *  - The member who posted has the likes received decreased by the number of likers
 * for that message.
 *
 * @param int[]|int $messages
 */
function decreaseLikeCounts($messages)
{
	$db = database();

	// Start off with no changes
	$update_given = array();
	$update_received = array();

	// Only a single message
	if (is_numeric($messages))
		$messages = array($messages);

	// Load the members who liked and who posted for this group of messages
	$request = $db->query('', '
		SELECT
			id_member, id_poster
		FROM {db_prefix}message_likes
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $messages,
		)
	);
	$posters = array();
	$likers = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Track how many likes each member gave and how many were received
		$posters[$row['id_poster']] = isset($posters[$row['id_poster']]) ? $posters[$row['id_poster']]++ : 1;
		$likers[$row['id_member']] = isset($likers[$row['id_member']]) ? $likers[$row['id_member']]++ : 1;
	}
	$db->free_result($request);

	// No one?
	if (empty($posters) && empty($likers))
		return;

	// Re-count the "likes given" totals for the likers
	if (!empty($likers))
	{
 		$request = $db->query('', '
			SELECT
				COUNT(id_msg) AS likes, id_member
			FROM {db_prefix}message_likes
			WHERE id_member IN ({array_int:members})
			GROUP BY id_member',
			array(
				'members' => array_keys($likers),
			)
		);
		// All who liked these messages have their "likes given" reduced
		while ($row = $db->fetch_assoc($request))
			$update_given[$row['id_member']] = $row['likes'] - $likers[$row['id_member']];
		$db->free_result($request);
	}

	// Count the "likes received" totals for the message posters
	if (!empty($posters))
	{
		$request = $db->query('', '
			SELECT
				COUNT(id_msg) AS likes, id_poster
			FROM {db_prefix}message_likes
			WHERE id_poster IN ({array_int:members})
			GROUP BY id_poster',
			array(
				'members' => array_keys($posters),
			)
		);
		// The message posters have their "likes received" reduced
		while ($row = $db->fetch_assoc($request))
			$update_received[$row['id_poster']] = $row['likes'] - $posters[$row['id_poster']];
		$db->free_result($request);
	}

	// Update the totals for these members
	foreach ($update_given as $id_member => $total)
		updateMemberData($id_member, array('likes_given' => (int) $total));

	foreach ($update_received as $id_member => $total)
		updateMemberData($id_member, array('likes_received' => (int) $total));
}
