<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains functions for dealing with polls.
 *
 */

/**
 * Retrieve the poll ID associated with this topic ID.
 *
 * @param int $topicID
 * @param int $pollID
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