<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains the database work for likes.
 *
 */

/**
 * Updates the like value for a post/member combo if there are no problems with
 * the request, such as being a narcissist
 *
 * @param int $id_liker - user_id of the liker/disliker
 * @param array $liked_message - message array that is being worked on
 * @param type $direction - + for like - for unlike a previous liked one
 */
function like_post($id_liker, $liked_message, $direction)
{
	// If we have a message, then we have passed all checks ...
	if (!empty($liked_message))
	{
		// You can't like your own stuff, no matter how brilliant you think you are
		if ($liked_message['id_member'] === $id_liker)
			fatal_lang_error('cant_like_yourself', false);

		addlike($id_liker, $liked_message, $direction);
	}
}

/**
 * Loads all of the likes for a group of messages
 * Returns an array of message_id to members who liked that post
 * If prepare is true, will also prep the array for template use
 *
 * @param array $messages
 * @param bool prepare
 */
function loadLikes($messages, $prepare = true)
{
	$likes = array();

	if (empty($messages))
		return $likes;

	if (!is_array($messages))
		$messages = (array((int) $messages));

	// Load up them likes from the db
	$db = database();
	$request = $db->query('', '
		SELECT l.id_member, l.id_msg, m.real_name
		FROM {db_prefix}message_likes AS l
			LEFT JOIN {db_prefix}members AS m on (m.id_member = l.id_member)
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
 * @param type $likes
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
			// Mix up the likers so we don't show the same ones everytime
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
 * @param type $likeWaitTime
 */
function clearLikes($likeWaitTime)
{
	// Delete all older items from the log
	$db = database();
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
 * by the clearLikes function so the count is allways current.
 *
 * returns true if they can like again, or false if they have to wait a bit
 *
 * @param int $id_liker
 */
function lastLikeOn($id_liker)
{
	global $modSettings;

	$actions = 0;
	if (empty($modSettings['likeWaitCount']))
		return true;

	// Find out if, and how many, this user has done recently...
	$db = database();
	$request = $db->query('', '
		SELECT action
		FROM {db_prefix}log_likes
		WHERE id_executor = {int:current_member}',
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
 * @param array $liked_message
 * @param int $direction - options: - or +
 */
function addLike($id_liker, $liked_message, $direction)
{
	// See if they already likeyed this message
	$db = database();
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

	// Not previosly liked, and you want to
	if ($count === 0 && $direction === '+')
	{
		$db->insert('',
			'{db_prefix}message_likes',
			array('id_member' => 'int', 'id_msg' => 'int'),
			array($id_liker, $liked_message['id_msg']),
			array('id_msg', 'id_member')
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
		$db->query('','
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
 * @param int $id_topic, the topic
 * @param int $direction +/- liking or unliking
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