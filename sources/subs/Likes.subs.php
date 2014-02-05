<?php

/**
 * This file contains the database work for likes.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 */

/**
 * Updates the like value for a post/member combo if there are no problems with
 * the request, such as being a narcissist
 *
 * @param int $id_liker - user_id of the liker/disliker
 * @param mixed[] $liked_message - message array that is being worked on
 * @param char $direction - + for like - for unlike a previous liked one
 */
function likePost($id_liker, $liked_message, $direction)
{
	global $modSettings;

	// If we have a message, then we have passed all checks ...
	if (!empty($liked_message))
	{
		// You can't like your own stuff, no matter how brilliant you think you are
		if ($liked_message['id_member'] == $id_liker && empty($modSettings['likeAllowSelf']))
			fatal_lang_error('cant_like_yourself', false);

		updateLike($id_liker, $liked_message, $direction);
	}
}

/**
 * Loads all of the likes for a group of messages
 * Returns an array of message_id to members who liked that post
 * If prepare is true, will also prep the array for template use
 *
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
		SELECT l.id_member, l.id_msg, m.real_name
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}members AS m ON (m.id_member = l.id_member)
		WHERE id_msg IN ({array_int:id_messages})',
		array(
			'id_messages' => $messages,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$likes[$row['id_msg']]['member'][$row['id_member']] = $row['real_name'];

	// total likes for this group
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
 * @param int[] $likes array of like ids to process
 * @return array
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

			// Tag on how many others liked this
			$likes[$msg_id]['member'][] = sprintf('%+d %s', ($like['count'] - $limit), $txt['liked_more']);
		}

		// Top billing just for you, the big lights, the grand stage, plus we need that key returned
		if ($you_liked)
		{
			$likes[$msg_id]['member'][$user_info['id']] = $txt['liked_you'];
			$likes[$msg_id]['member'] = array_reverse($likes[$msg_id]['member'], true);
		}
	}

	return $likes;
}

/**
 * Clear the likes log of older actions ... used to prevent a like love fest
 *
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
 * allowed in a given time period.  The log is maintained to the time period
 * by the clearLikes function so the count is always current.
 *
 * returns true if they can like again, or false if they have to wait a bit
 *
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
 * @param int $id_liker
 * @param int[] $liked_message
 * @param int $direction - options: - or +
 */
function updateLike($id_liker, $liked_message, $direction)
{
	$db = database();

	// See if they already likeyed this message
	$request = $db->query('', '
		SELECT id_member
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
		updateMemberData($id_liker, array('likes_given' => '+'));
		updateMemberData($liked_message['id_member'], array('likes_received' => '+'));
	}
	// Or you are just being fickle?
	elseif ($count !==0 && $direction === '-')
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
 * @param int $id_topic - the topic
 * @param char $direction +/- liking or unliking
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
		SELECT l.id_member, l.id_msg,
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
			'delete' =>  $scripturl . '?action=likes;sa=unlikepost;profile;msg=' . $row['id_msg'] . ';' . $context['session_var'] . '=' . $context['session_id'],
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
		SELECT m.subject, m.id_topic, b.name, l.id_msg, COUNT(l.id_msg) AS likes
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
			'who' =>  $scripturl . '?action=likes;sa=showWhoLiked;msg=' . $row['id_msg'],
			'likes' => $row['likes']
		);
	}

	return $likes;
}

/**
 * Function to load all of the likers of a message
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $messageID
 */
function postLikers($start, $items_per_page, $sort, $messageID)
{
	global $scripturl;

	$db = database();
	$likes = array();

	if (empty($messageID))
		return $likes;

	// Load up the likes for this message
	$request = $db->query('', '
		SELECT l.id_member, l.id_msg, m.real_name
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}members AS m ON (m.id_member = l.id_member)
		WHERE id_msg = {int:id_message}
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
		$likes[] = array(
			'real_name' => $row['real_name'],
			'id_member' => $row['id_member'],
			'link' =>  '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
		);
	}
	$db->free_result($request);

	return $likes;
}

/**
 * Function to get the number of likes for a message
 *
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

	return $total;
}