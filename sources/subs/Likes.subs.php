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
 * @param array $liked_message - message that is being worked on
 * @param type $dir - +1 for like -1 for unlike a previous liked one
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
 *
 * @param array $messages
 */
function loadLikes($messages)
{
	global $smcFunc;

	$likes = array();

	if (empty($messages))
		return $likes;

	if (!is_array($messages))
		$messages = (array((int) $messages));

	// Load up them likes from the db
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_msg
		FROM {db_prefix}message_likes
		WHERE id_msg IN ({array_int:id_messages})',
		array(
			'id_messages' => $messages,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$likes[$row['id_msg']][] = $row['id_member'];

		// Keep a running per message total as well
		if (isset($likes[$row['id_msg']]['count']))
			$likes[$row['id_msg']]['count']++;
		else
			$likes[$row['id_msg']]['count'] = 1;
	}
	$smcFunc['db_free_result']($request);

	return $likes;
}

/**
 * Clear the likes log of older actions ... used to prevent a like love fest
 *
 * @param type $likeWaitTime
 */
function clearLikes($likeWaitTime)
{
	global $smcFunc;

	// Delete all older items from the log
	$smcFunc['db_query']('', '
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
	global $smcFunc, $modSettings;

	$actions = 0;
	if (empty($modSettings['likeWaitCount']))
		return true;

	// Find out if, and how many, this user has done recently...
	$request = $smcFunc['db_query']('', '
		SELECT action
		FROM {db_prefix}log_likes
		WHERE id_executor = {int:current_member}',
		array(
			'current_member' => $id_liker,
		)
	);
	$actions = $smcFunc['db_num_rows']($request);
	$smcFunc['db_free_result']($request);

	return $actions < $modSettings['likeWaitCount'];
}

/**
 * Add a like action, from executor to target.
 *
 * @param int $id_liker
 * @param int $id_target
 * @param int $direction - options: -1 or 1
 */
function addLike($id_liker, $liked_message, $direction)
{
	global $smcFunc, $topic;

	// If we are liking the first message in a topic, we are de facto liking the topic
	if ($topic === $liked_message['id_first_message'])
		increaseTopicLikes($topic, $direction);

	// See if they already likeyed this message
	$request = $smcFunc['db_query']('', '
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
	$count = $smcFunc['db_num_rows']($request);
	$smcFunc['db_free_result']($request);

	// Not previosly liked, and you want to
	if ($count === 0 && $direction === '+')
	{
		$smcFunc['db_insert']('',
			'{db_prefix}message_likes',
			array('id_member' => 'int', 'id_msg' => 'int'),
			array($id_liker, $liked_message['id_msg']),
			array('id_msg', 'id_member')
		);
	}
	// Or you are just being fickle?
	elseif ($count !==0 && $direction === '-')
	{
		$smcFunc['db_query']('','
			DELETE FROM {db_prefix}message_likes
			WHERE id_member = {int:id_member}
				AND id_msg = {int:id_msg}',
			array(
				'id_member' => $id_liker,
				'id_msg' => $liked_message['id_msg'],
			)
		);
	}

	// Put it in the log so we can prevent flooding the system with likes
	$smcFunc['db_insert']('replace',
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
 * @param int $dir +/- liking or unliking
 */
function increaseTopicLikes($id_topic, $direction)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET num_likes = num_likes ' . $direction === '+' ? '+ 1' : '- 1' . '
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $id_topic,
		)
	);
}