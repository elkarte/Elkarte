<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains functions for dealing with polls.
 *
 */

/**
 * This function deals with the poll ID associated to a topic.
 * It allows to retrieve or update the poll ID associated with this topic ID.
 *
 * If $pollID is not passed, the current poll ID of the topic, if any, is returned.
 * If $pollID is passed, the topic is updated to point to the new poll.
 *
 * @param int $topicID the ID of the topic
 * @param int $pollID = null the ID of the poll, if any. If null is passed, it retrives the current ID.
 *
 */
function associatedPoll($topicID, $pollID = null)
{
	global $smcFunc;

	if ($pollID === null)
	{
		// Retrieve the poll ID.
		$request = $smcFunc['db_query']('', '
			SELECT id_poll
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topicID,
			)
		);
		list ($pollID) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return $pollID;
	}
	else
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET id_poll = {int:poll}
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topicID,
				'poll' => $pollID,
			)
		);
	}
}

/**
 * Remove a poll.
 *
 * @param int $pollID
 */
function removePoll($pollID)
{
	global $smcFunc;

	// Remove votes.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);

	// Remove the choices associated with this poll.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);

	// Remove the poll.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);
}

/**
 * Reset votes for the poll.
 *
 * @param $pollID
 */
function resetVotes($pollID)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}polls
		SET num_guest_voters = {int:no_votes}, reset_poll = {int:time}
		WHERE id_poll = {int:id_poll}',
		array(
			'no_votes' => 0,
			'id_poll' => $pollID,
			'time' => time(),
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}poll_choices
		SET votes = {int:no_votes}
		WHERE id_poll = {int:id_poll}',
		array(
			'no_votes' => 0,
			'id_poll' => $pollID,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);
}

/**
 * Retrieve poll information.
 *
 * @param int $topicID the topic with an associated poll.
 */
function getPollInfo($topicID)
{
	global $smcFunc;

	// Check if a poll currently exists on this topic, and get the id, question and starter.
	$request = $smcFunc['db_query']('', '
		SELECT
			t.id_member_started, p.id_poll, p.question, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
			m.subject, p.guest_vote, p.id_member AS poll_starter
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topicID,
		)
	);

	// The topic must exist
	if ($smcFunc['db_num_rows']($request) == 0)
		return false;

	// Get the poll information.
	$pollinfo = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $pollinfo;
}

/**
 * Create a poll
 * 
 * @param string $question The title/question of the poll
 * @param int|false $id_member = false The id of the creator (if false, the current user)
 * @param string|false $poster_name = false The name of the poll creator (if false, the current user)
 * @param int $max_votes = 1 The maximum number of votes you can do
 * @param bool $hide_results = true If the results should be hidden
 * @param int $expire = 0 The time in days that this poll will expire
 * @param bool $can_change_vote = false If you can change your vote
 * @param bool $can_guest_vote = false If guests can vote
 * @param array $options = array() The poll options
 * @return int the id of the created poll
 */
function createPoll($question, $id_member = false, $poster_name = false, $max_votes = 1, $hide_results = true, $expire = 0, $can_change_vote = false, $can_guest_vote = false, array $options = array())
{
	global $smcFunc, $user_info;

	if ($id_member == false)
		$id_member = $id_member === false ? $user_info['id'] : (int) $id_member;
	if ($poster_name == false)
		$poster_name = $poster_name === false ? $user_info['real_name'] : $poster_name;

	$expire = empty($expire) ? 0 : time() + $expire * 3600 * 24;

	$smcFunc['db_insert']('',
		'{db_prefix}polls',
		array(
			'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int', 'expire_time' => 'int', 'id_member' => 'int',
			'poster_name' => 'string-255', 'change_vote' => 'int', 'guest_vote' => 'int'
		),
		array(
			$question, $hide_results, $max_votes, $expire, $user_info['id'],
			$poster_name, $can_change_vote, $can_guest_vote,
		),
		array('id_poll')
	);

	$id_poll = $smcFunc['db_insert_id']('{db_prefix}polls', 'id_poll');

	if (!empty($options))
		addPollOptions($id_poll, $options);

	call_integration_hook('integrate_poll_add_edit', array($id_poll, false));

	return $id_poll;
}

/**
 * Add options to an already created poll
 * 
 * @param int $id_poll The id of the poll you're adding the options to
 * @param array $options The options to choose from
 */
function addPollOptions($id_poll, array $options)
{
	global $smcFunc;

	$pollOptions = array();
	foreach ($options as $i => $option)
	{
		$pollOptions[] = array($id_poll, $i, $option);
	}

	$smcFunc['db_insert']('insert',
		'{db_prefix}poll_choices',
		array('id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255'),
		$pollOptions,
		array('id_poll', 'id_choice')
	);
}