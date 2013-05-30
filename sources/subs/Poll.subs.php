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
	$db = database();

	if ($pollID === null)
	{
		// Retrieve the poll ID.
		$request = $db->query('', '
			SELECT id_poll
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topicID,
			)
		);
		list ($pollID) = $db->fetch_row($request);
		$db->free_result($request);

		return $pollID;
	}
	else
	{
		$db->query('', '
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
	$db = database();

	// Remove votes.
	$db->query('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);

	// Remove the choices associated with this poll.
	$db->query('', '
		DELETE FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);

	// Remove the poll.
	$db->query('', '
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
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}polls
		SET num_guest_voters = {int:no_votes}, reset_poll = {int:time}
		WHERE id_poll = {int:id_poll}',
		array(
			'no_votes' => 0,
			'id_poll' => $pollID,
			'time' => time(),
		)
	);
	$db->query('', '
		UPDATE {db_prefix}poll_choices
		SET votes = {int:no_votes}
		WHERE id_poll = {int:id_poll}',
		array(
			'no_votes' => 0,
			'id_poll' => $pollID,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $pollID,
		)
	);
}

/**
 * Get all poll information you wanted to know.
 * Only returns info on the poll, not its options.
 *
 * @param int $id_poll
 *
 * @return array|false array of poll information, or false if no poll is found
 */
function pollInfo($id_poll)
{
	$db = database();

	// Read info from the db
	$request = $db->query('', '
		SELECT
			p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
			p.guest_vote, p.id_member, IFNULL(mem.real_name, p.poster_name) AS poster_name, p.num_guest_voters, p.reset_poll
		FROM {db_prefix}polls AS p
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)
		WHERE p.id_poll = {int:id_poll}
		LIMIT 1',
		array(
			'id_poll' => $id_poll,
		)
	);
	$poll_info = $db->fetch_assoc($request);
	$db->free_result($request);

	if (empty($poll_info))
		return false;

	$request = $db->query('', '
		SELECT COUNT(DISTINCT id_member) AS total
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}
			AND id_member != {int:not_guest}',
		array(
			'id_poll' => $id_poll,
			'not_guest' => 0,
		)
	);
	list ($poll_info['total']) = $db->fetch_row($request);
	$db->free_result($request);

	// Total voters needs to include guest voters
	$poll_info['total'] += $poll_info['num_guest_voters'];

	return $poll_info;
}

/**
 * Retrieve poll information, for the poll associated
 * to topic $topicID.
 *
 * @param int $topicID the topic with an associated poll.
 */
function pollInfoForTopic($topicID)
{
	$db = database();

	// Check if a poll currently exists on this topic, and get the id, question and starter.
	$request = $db->query('', '
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
	if ($db->num_rows($request) == 0)
		return false;

	// Get the poll information.
	$pollinfo = $db->fetch_assoc($request);
	$db->free_result($request);

	return $pollinfo;
}

/**
 * Return poll options, customized for a given member.
 * The function adds to poll options the information if the user
 * has voted in this poll.
 *
 * @param int $id_poll
 * @param int $id_member
 */
function pollOptionsForMember($id_poll, $id_member)
{
	$db = database();

	// Get the choices
	$request = $db->query('', '
		SELECT pc.id_choice, pc.label, pc.votes, IFNULL(lp.id_choice, -1) AS voted_this
		FROM {db_prefix}poll_choices AS pc
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_choice = pc.id_choice AND lp.id_poll = {int:id_poll} AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
		WHERE pc.id_poll = {int:id_poll}',
		array(
			'current_member' => $id_member,
			'id_poll' => $id_poll,
			'not_guest' => 0,
		)
	);
	$pollOptions = array();
	while ($row = $db->fetch_assoc($request))
	{
		censorText($row['label']);
		$pollOptions[$row['id_choice']] = $row;
	}
	$db->free_result($request);

	return $pollOptions;
}

/**
 * Create a poll
 *
 * @param string $question The title/question of the poll
 * @param int $id_member = false The id of the creator
 * @param string $poster_name The name of the poll creator
 * @param int $max_votes = 1 The maximum number of votes you can do
 * @param bool $hide_results = true If the results should be hidden
 * @param int $expire = 0 The time in days that this poll will expire
 * @param bool $can_change_vote = false If you can change your vote
 * @param bool $can_guest_vote = false If guests can vote
 * @param array $options = array() The poll options
 * @return int the id of the created poll
 */
function createPoll($question, $id_member, $poster_name, $max_votes = 1, $hide_results = true, $expire = 0, $can_change_vote = false, $can_guest_vote = false, array $options = array())
{
	$expire = empty($expire) ? 0 : time() + $expire * 3600 * 24;

	$db = database();
	$db->insert('',
		'{db_prefix}polls',
		array(
			'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int', 'expire_time' => 'int', 'id_member' => 'int',
			'poster_name' => 'string-255', 'change_vote' => 'int', 'guest_vote' => 'int'
		),
		array(
			$question, $hide_results, $max_votes, $expire, $id_member,
			$poster_name, $can_change_vote, $can_guest_vote,
		),
		array('id_poll')
	);

	$id_poll = $db->insert_id('{db_prefix}polls', 'id_poll');

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
	$pollOptions = array();
	foreach ($options as $i => $option)
	{
		$pollOptions[] = array($id_poll, $i, $option);
	}

	$db = database();
	$db->insert('insert',
		'{db_prefix}poll_choices',
		array('id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255'),
		$pollOptions,
		array('id_poll', 'id_choice')
	);
}