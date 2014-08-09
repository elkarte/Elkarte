<?php

/**
 * This file contains the database work for likes.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

/**
 * Updates the like value for a post/member combo if there are no problems with
 * the request, such as being a narcissist
 *
 * @package Likes
 * @param int $id_liker - user_id of the liker/disliker
 * @param mixed[] $liked_message - message array that is being worked on
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
 * Replaces the current member id with 'You' if they like a post and makes it first
 * Truncates the like list at a given number and adds in +x others
 *
 * @package Likes
 * @param int[] $likes array of like ids to process
 * @return integer[]
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
 * @param int $direction - options: - or +
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
			array('id_member' => 'int', 'id_msg' => 'int', 'id_poster' => 'int'),
			array($id_liker, $liked_message['id_msg'], $liked_message['id_member']),
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
 * Return how many likes a user has given or the count of thier posts that
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
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $memberID
 */
function likesPostsGiven($start, $items_per_page, $sort, $memberID)
{
	global $scripturl, $context;

	$db = database();
	$likes = array();

	// Load up what the user likes from the db
	$request = $db->query('', '
		SELECT
			l.id_member, l.id_msg,
			m.subject, m.poster_name, m.id_board, m.id_topic,
			b.name
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = l.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE l.id_member = {int:id_member}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'id_member' => $memberID,
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$likes[] = array(
			'subject' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
			'poster_name' => $row['poster_name'],
			'name' => $row['name'],
			'delete' => $scripturl . '?action=likes;sa=unlikepost;profile;msg=' . $row['id_msg'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		);
	}

	return $likes;
}

/**
 * Returns an array of details based on posts that others have liked of this user
 * Creates links to show the users who liked a post
 *
 * Used by action=profile;area=showlikes;sa=received
 *
 * @package Likes
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $memberID
 */
function likesPostsReceived($start, $items_per_page, $sort, $memberID)
{
	global $scripturl;

	$db = database();
	$likes = array();

	// Load up what the user likes from the db
	$request = $db->query('', '
		SELECT
			m.subject, m.id_topic,
			b.name, l.id_msg, COUNT(l.id_msg) AS likes
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = l.id_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE l.id_poster = {int:id_member}
		GROUP BY (l.id_msg)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'id_member' => $memberID,
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$likes[] = array(
			'subject' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
			'name' => $row['name'],
			'who' => $scripturl . '?action=likes;sa=showWhoLiked;msg=' . $row['id_msg'],
			'likes' => $row['likes']
		);
	}

	return $likes;
}

/**
 * Function to load all of the likers of a message
 *
 * @package Likes
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
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
	$request = $db->query('', '
		SELECT
			l.id_member, l.id_msg,
			m.real_name' . ($simple === true ? '' : ',
			IFNULL(a.id_attach, 0) AS id_attach,
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
		)
	);
	while ($row = $db->fetch_assoc($request))
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
		$likes[] = $like;
	}
	$db->free_result($request);

	return $likes;
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
 * Function to get most liked message
 */
function dbMostLikedMessage()
{
	global $scripturl, $modSettings, $settings, $txt;

	$db = database();

	// Most liked Message
	$mostLikedMessage = array();

	$request = $db->query('', '
		SELECT IFNULL(mem.real_name, m.poster_name) AS member_received_name, lp.id_msg,
			m.id_topic, m.id_board, m.id_member,
			lp.like_count AS like_count, m.subject, m.body, m.poster_time,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, mem.avatar,
			mem.posts, m.smileys_enabled, mem.email_address
		FROM (
			SELECT COUNT(lp.id_msg) AS like_count, lp.id_msg
			FROM {db_prefix}message_likes AS lp
			GROUP BY lp.id_msg
			ORDER BY like_count DESC
		) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
		WHERE {query_wanna_see_board}
		LIMIT 1',
		array()
	);

	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['body']);
		$msgString = shorten_text($row['body'], 255, true);
		$avatar = determineAvatar($row);

		$mostLikedMessage = array(
			'id_msg' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'id_board' => $row['id_board'],
			'like_count' => $row['like_count'],
			'subject' => $row['subject'],
			'body' => parse_bbc($msgString, $row['smileys_enabled'], $row['id_msg']),
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
		);
		$id_msg = $row['id_msg'];
	}
	$db->free_result($request);

	if (empty($mostLikedMessage))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	$mostLikedMessage['member_liked_data'] = postLikers(0, 20, 'l.id_member DESC', $id_msg, false);

	return $mostLikedMessage;
}

/**
 * Function to get most liked topic
 */
function dbMostLikedTopic()
{
	global $scripturl, $modSettings, $settings, $txt;

	$db = database();

	// Most liked topic
	$mostLikedTopic = array();
	$request = $db->query('group_concat_convert', '
		SELECT m.id_topic, lp.like_count, GROUP_CONCAT(m.id_msg SEPARATOR \',\') AS id_msgs
		FROM {db_prefix}message_likes AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
			INNER JOIN (
				SELECT COUNT(m.id_topic) AS like_count, m.id_topic
				FROM {db_prefix}message_likes AS lp
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
				GROUP BY m.id_topic
				ORDER BY like_count DESC
				LIMIT {int:limit}
			) AS lp ON (lp.id_topic = m.id_topic)
		WHERE {query_wanna_see_board}
		ORDER BY m.id_msg DESC
		LIMIT {int:limit2}',
		array(
			'limit' => 1,
			'limit2' => 10
		)
	);
	$mostLikedTopic = $db->fetch_assoc($request);
	$db->free_result($request);

	if (empty($mostLikedTopic))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	// Lets fetch few messages in the topic
	$request = $db->query('', '
		SELECT m.id_msg, m.body, m.poster_time, m.smileys_enabled,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.id_member, IFNULL(mem.real_name, m.poster_name) AS real_name, mem.avatar, mem.email_address
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
		WHERE m.id_msg IN ({array_int:id_msgs})
		ORDER BY m.id_msg
		LIMIT {int:limit}',
		array(
			'id_msgs' => array_map('intval', explode(',', $mostLikedTopic['id_msgs'])),
			'limit' => 10
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['body']);
		$msgString = shorten_text($row['body'], 255, true);
		$avatar = determineAvatar($row);

		$mostLikedTopic['msg_data'][] = array(
			'id_msg' => $row['id_msg'],
			'body' => parse_bbc($msgString, $row['smileys_enabled'], $row['id_msg']),
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'member' => array(
				'id_member' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'avatar' => $avatar['href'],
			),
		);
	}
	$db->free_result($request);

	return $mostLikedTopic;
}

/**
 * Function to get most liked board
 * @todo: fix postgre
 */
function dbMostLikedBoard()
{
	global $scripturl, $txt;

	$db = database();
	// Most liked board
	$mostLikedBoard = array();
	$request = $db->query('group_concat_convert', '
		SELECT m.id_board, b.name, b.num_topics, b.num_posts,
			COUNT(DISTINCT(m.id_topic)) AS topics_liked, COUNT(DISTINCT(lp.id_msg)) AS msgs_liked,
			SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT(CONVERT(m.id_topic, CHAR(8))) ORDER BY m.id_topic DESC SEPARATOR ","), ",", 10) AS id_topic,
			COUNT(m.id_board) AS like_count
		FROM {db_prefix}message_likes AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
		WHERE {query_wanna_see_board}
		GROUP BY m.id_board
		ORDER BY like_count DESC
		LIMIT 1',
		array()
	);
	list ($mostLikedBoard['id_board'], $mostLikedBoard['name'], $mostLikedBoard['num_topics'], $mostLikedBoard['num_posts'], $mostLikedBoard['topics_liked'], $mostLikedBoard['msgs_liked'], $id_topics, $mostLikedBoard['like_count'])= $db->fetch_row($request);

	$db->free_result($request);

	if (empty($id_topics))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	// Lets fetch few topics from this board
	$request = $db->query('', '
		SELECT t.id_topic, m.id_msg, m.body, m.poster_time, m.smileys_enabled,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.id_member, IFNULL(mem.real_name, m.poster_name) as real_name, mem.avatar, mem.email_address
		FROM {db_prefix}topics as t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
		WHERE t.id_topic IN ({raw:id_topics})
		ORDER BY t.id_topic DESC',
		array(
			'id_topics' => $id_topics
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['body']);
		$msgString = shorten_text($row['body'], 255, true);
		$avatar = determineAvatar($row);

		$mostLikedBoard['topic_data'][] = array(
			'id_topic' => $row['id_topic'],
			'body' => parse_bbc($msgString, $row['smileys_enabled'], $row['id_msg']),
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'member' => array(
				'id_member' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'avatar' => $avatar['href'],
			),
		);
	}
	$db->free_result($request);

	return $mostLikedBoard;
}

/**
 * Function to get most liked user
 */
function dbMostLikesReceivedUser()
{
	global $scripturl, $txt;

	$db = database();

	$mostLikedMember = array();

	$request = $db->query('', '
		SELECT lp.id_poster, lp.like_count,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			IFNULL(mem.real_name, m.poster_name) AS real_name, mem.avatar, mem.date_registered, mem.posts, mem.email_address
		FROM (
			SELECT id_poster,
			COUNT(id_msg) AS like_count,
				MAX(id_msg) AS id_msg
			FROM {db_prefix}message_likes
			WHERE id_poster != 0
			GROUP BY id_poster
			ORDER BY like_count DESC
			LIMIT 1
		) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
		LIMIT 1',
		array()
	);
	while ($row = $db->fetch_assoc($request))
	{
		$avatar = determineAvatar($row);
		$mostLikedMember = array(
			'member_received' => array(
				'id_member' => $row['id_poster'],
				'name' => $row['real_name'],
				'total_posts' => $row['posts'],
				'date_registered' => $row['date_registered'],
				'href' => !empty($row['id_poster']) ? $scripturl . '?action=profile;u=' . $row['id_poster'] : '',
				'avatar' => $avatar['href'],
			),
			'like_count' => $row['like_count'],
		);
		$id_member = $row['id_poster'];
	}
	$db->free_result($request);

	if (empty($id_member))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	// Lets fetch highest liked posts by this user
	$request = $db->query('', '
		SELECT lp.id_msg, m.id_topic, COUNT(lp.id_msg) AS like_count, m.subject,
			m.body, m.poster_time, m.smileys_enabled
		FROM {db_prefix}message_likes AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_wanna_see_board}
			AND lp.id_poster = {int:id_member}
		GROUP BY lp.id_msg, m.id_topic, m.subject, m.body, m.poster_time, m.smileys_enabled
		ORDER BY like_count DESC
		LIMIT 10',
		array(
			'id_member' => $id_member
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['body']);
		$msgString = shorten_text($row['body'], 255, true);

		$mostLikedMember['topic_data'][] = array(
			'id_topic' => $row['id_topic'],
			'id_msg' => $row['id_msg'],
			'like_count' => $row['like_count'],
			'subject' => $row['subject'],
			'body' => parse_bbc($msgString, $row['smileys_enabled'], $row['id_msg']),
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
		);
	}
	$db->free_result($request);

	return $mostLikedMember;
}

/**
 * Function to get most likes giving user
 */
function dbMostLikesGivenUser()
{
	global $scripturl, $txt;

	$db = database();

	$mostLikeGivingMember = array();
	$request = $db->query('group_concat_convert', '
		SELECT lp.id_member, lp.like_count,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			IFNULL(mem.real_name, m.poster_name) AS real_name, mem.avatar, mem.date_registered, mem.posts, mem.email_address
		FROM (
			SELECT COUNT(id_msg) AS like_count, id_member, MAX(id_msg) AS id_msg
			FROM {db_prefix}message_likes
			GROUP BY id_member
			ORDER BY like_count DESC
			LIMIT 1
		) AS lp
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = lp.id_msg)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lp.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = lp.id_member)',
		array()
	);

	while ($row = $db->fetch_assoc($request))
	{
		$avatar = determineAvatar($row);
		$mostLikeGivingMember = array(
			'member_given' => array(
				'id_member' => $row['id_member'],
				'name' => $row['real_name'],
				'total_posts' => $row['posts'],
				'date_registered' => $row['date_registered'],
				'href' => !empty($row['id_member_gave']) ? $scripturl . '?action=profile;u=' . $row['id_member_gave'] : '',
				'avatar' => $avatar['href'],
			),
			'like_count' => $row['like_count'],
		);
		$id_liker = $row['id_member'];
	}
	$db->free_result($request);

	if (empty($mostLikeGivingMember))
	{
		return array(
			'noDataMessage' => $txt['like_post_error_no_data']
		);
	}

	// Lets fetch the latest posts by this user
	$request = $db->query('', '
		SELECT m.id_msg, m.id_topic, m.subject, m.body, m.poster_time, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_wanna_see_board}
			AND m.id_member = {int:id_member}
		ORDER BY m.id_msg DESC
		LIMIT 10',
		array(
			'id_member' => $id_liker
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['body']);
		$msgString = shorten_text($row['body'], 255, true);

		$mostLikeGivingMember['topic_data'][] = array(
			'id_msg' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'body' => parse_bbc($msgString, $row['smileys_enabled'], $row['id_msg']),
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
		);
	}
	$db->free_result($request);

	return $mostLikeGivingMember;
}
