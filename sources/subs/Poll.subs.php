<?php

/**
 * This file contains those functions pertaining to polls, including removing
 * resetting votes, editing, adding, and more
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
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
 * @param int|null $pollID = null the ID of the poll, if any. If null is passed, it retrieves the current ID.
 * @return integer
 */
function associatedPoll($topicID, $pollID = null)
{
	// Retrieve the poll ID.
	if ($pollID === null)
	{
		require_once(SUBSDIR . '/Topic.subs.php');
		$pollID = topicAttribute($topicID, array('id_poll'));

		return $pollID['id_poll'];
	}
	else
	{
		setTopicAttribute($topicID, array('id_poll' => $pollID));
	}

	return false;
}

/**
 * Remove a poll.
 *
 * @param int[]|int $pollID The id of the poll to remove
 */
function removePoll($pollID)
{
	$db = database();

	$pollID = is_array($pollID) ? $pollID : array($pollID);

	// Remove votes.
	$db->query('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll IN ({array_int:id_polls})',
		array(
			'id_polls' => $pollID,
		)
	);

	// Remove the choices associated with this poll.
	$db->query('', '
		DELETE FROM {db_prefix}poll_choices
		WHERE id_poll IN ({array_int:id_polls})',
		array(
			'id_polls' => $pollID,
		)
	);

	// Remove the poll.
	$db->query('', '
		DELETE FROM {db_prefix}polls
		WHERE id_poll IN ({array_int:id_polls})',
		array(
			'id_polls' => $pollID,
		)
	);
}

/**
 * Reset votes for the poll.
 *
 * @param int $pollID The ID of the poll to reset the votes on
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
 *
 * - Only returns info on the poll, not its options.
 *
 * @param int $id_poll The id of the poll to load
 * @param bool $ignore_permissions if true permissions are not checked.
 *             If false, {query_see_board} boardsAllowedTo('poll_view') and
 *             $modSettings['postmod_active'] will be considered in the query.
 *             This param is currently used only in SSI, it may be useful in any
 *             kind of integration
 * @return array|false array of poll information, or false if no poll is found
 */
function pollInfo($id_poll, $ignore_permissions = true)
{
	global $modSettings;

	$db = database();

	$boardsAllowed = array();
	if ($ignore_permissions === false)
	{
		$boardsAllowed = boardsAllowedTo('poll_view');

		if (empty($boardsAllowed))
			return false;
	}

	// Read info from the db
	$request = $db->query('', '
		SELECT
			p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
			p.guest_vote, p.id_member, COALESCE(mem.real_name, p.poster_name) AS poster_name,
			p.num_guest_voters, p.reset_poll' . ($ignore_permissions ? '' : ',
			b.id_board') . '
		FROM {db_prefix}polls AS p
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)' . ($ignore_permissions ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)') . '
		WHERE p.id_poll = {int:id_poll}' . ($ignore_permissions ? '' : ((in_array(0, $boardsAllowed) ? '' : '
			AND b.id_board IN ({array_int:boards_allowed_see})') . (!$modSettings['postmod_active'] ? '' : '
			AND t.approved = {int:is_approved}'))) . '
		LIMIT 1',
		array(
			'id_poll' => $id_poll,
			'boards_allowed_see' => $boardsAllowed,
			'is_approved' => 1,
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
 *
 * @return bool
 */
function pollInfoForTopic($topicID)
{
	$db = database();

	// Check if a poll currently exists on this topic, and get the id, question and starter.
	$request = $db->query('', '
		SELECT
			t.id_member_started AS id_member, p.id_poll, p.voting_locked, p.question,
			p.hide_results, p.expire_time, p.max_votes, p.change_vote,
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
 * Retrieve the id of the topic associated to a poll
 *
 * @param int $pollID the topic with an associated poll.
 * @return array the topic id and the board id, false if no topics found
 */
function topicFromPoll($pollID)
{
	$db = database();

	// Check if a poll currently exists on this topic, and get the id, question and starter.
	$request = $db->query('', '
		SELECT
			t.id_topic, b.id_board
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE p.id_poll = {int:current_poll}
		LIMIT 1',
		array(
			'current_poll' => $pollID,
		)
	);

	// The topic must exist
	if ($db->num_rows($request) == 0)
		$topicID = false;
	// Get the poll information.
	else
		list ($topicID, $boardID) = $db->fetch_row($request);

	$db->free_result($request);

	return array($topicID, $boardID);
}

/**
 * Return poll options, customized for a given member.
 *
 * What it does:
 *
 * - The function adds to poll options the information if the user
 * has voted in this poll.
 * - It censors the label in the result array.
 *
 * @param int $id_poll The id of the poll to query
 * @param int $id_member The id of the member
 *
 * @return array
 */
function pollOptionsForMember($id_poll, $id_member)
{
	$db = database();

	// Get the choices
	$request = $db->query('', '
		SELECT pc.id_choice, pc.label, pc.votes, COALESCE(lp.id_choice, -1) AS voted_this
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
		$row['label'] = censor($row['label']);
		$pollOptions[$row['id_choice']] = $row;
	}
	$db->free_result($request);

	return $pollOptions;
}

/**
 * Returns poll options.
 * It censors the label in the result array.
 *
 * @param int $id_poll The id of the poll to load its options
 *
 * @return array
 */
function pollOptions($id_poll)
{
	$db = database();

	$request = $db->query('', '
		SELECT label, votes, id_choice
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $id_poll,
		)
	);
	$pollOptions = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['label'] = censor($row['label']);
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
 * @param int $hide_results = 1 If the results should be hidden
 * @param int $expire = 0 The time in days that this poll will expire
 * @param int $can_change_vote = 0 If you can change your vote
 * @param int $can_guest_vote = 0 If guests can vote
 * @param mixed[] $options = array() The poll options
 * @return int the id of the created poll
 */
function createPoll($question, $id_member, $poster_name, $max_votes = 1, $hide_results = 1, $expire = 0, $can_change_vote = 0, $can_guest_vote = 0, array $options = array())
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
 * Modify an existing poll
 *
 * @param int $id_poll The id of the poll that should be updated
 * @param string $question The title/question of the poll
 * @param int $max_votes = 1 The maximum number of votes you can do
 * @param int $hide_results = 1 If the results should be hidden
 * @param int $expire = 0 The time in days that this poll will expire
 * @param int $can_change_vote = 0 If you can change your vote
 * @param int $can_guest_vote = 0 If guests can vote
 */
function modifyPoll($id_poll, $question, $max_votes = 1, $hide_results = 1, $expire = 0, $can_change_vote = 0, $can_guest_vote = 0)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}polls
		SET question = {string:question}, change_vote = {int:change_vote},' . (allowedTo('moderate_board') ? '
			hide_results = {int:hide_results}, expire_time = {int:expire_time}, max_votes = {int:max_votes},
			guest_vote = {int:guest_vote}' : '
			hide_results = CASE WHEN expire_time = {int:expire_time_zero} AND {int:hide_results} = 2 THEN 1 ELSE {int:hide_results} END') . '
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $id_poll,
			'question' => $question,
			'max_votes' => $max_votes,
			'hide_results' => $hide_results,
			'expire_time' => $expire,
			'change_vote' => $can_change_vote,
			'guest_vote' => $can_guest_vote,
			'expire_time_zero' => 0,
		)
	);

	call_integration_hook('integrate_poll_add_edit', array($id_poll, true));
}

/**
 * Add options to an already created poll
 *
 * @param int $id_poll The id of the poll you're adding the options to
 * @param mixed[] $options The options to choose from
 */
function addPollOptions($id_poll, array $options)
{
	$db = database();

	$pollOptions = array();
	foreach ($options as $i => $option)
		$pollOptions[] = array($id_poll, $i, $option);

	$db->insert('insert',
		'{db_prefix}poll_choices',
		array('id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255'),
		$pollOptions,
		array('id_poll', 'id_choice')
	);
}

/**
 * Insert some options to an already created poll
 *
 * @param mixed[] $options An array holding the poll choices
 */
function insertPollOptions($options)
{
	$db = database();

	$db->insert('',
		'{db_prefix}poll_choices',
		array(
			'id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255', 'votes' => 'int',
		),
		$options,
		array()
	);
}

/**
 * Add a single option to an already created poll
 *
 * @param mixed[] $options An array holding the poll choices
 */
function modifyPollOption($options)
{
	$db = database();

	foreach ($options as $option)
		$db->query('', '
			UPDATE {db_prefix}poll_choices
			SET label = {string:option_name}
			WHERE id_poll = {int:id_poll}
				AND id_choice = {int:id_choice}',
			array(
				'id_poll' => $option[0],
				'id_choice' => $option[1],
				'option_name' => $option[2],
			)
		);
}

/**
 * Delete a bunch of options from a poll
 *
 * @param int $id_poll The id of the poll you're deleting the options from
 * @param int[] $id_options An array holding the choice id
 */
function deletePollOptions($id_poll, $id_options)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_poll = {int:id_poll}
			AND id_choice IN ({array_int:delete_options})',
		array(
			'delete_options' => $id_options,
			'id_poll' => $id_poll,
		)
	);

	$db->query('', '
		DELETE FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}
			AND id_choice IN ({array_int:delete_options})',
		array(
			'delete_options' => $id_options,
			'id_poll' => $id_poll,
		)
	);
}

/**
 * Retrieves the topic and, if different, poll starter
 * for the poll associated with the $id_topic.
 *
 * @param int $id_topic The id of the topic
 *
 * @return array
 */
function pollStarters($id_topic)
{
	$db = database();

	$request = $db->query('', '
		SELECT t.id_member_started, p.id_member AS poll_starter
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $id_topic,
		)
	);

	$pollStarters = array();

	if ($db->num_rows($request) != 0)
		$pollStarters = $db->fetch_row($request);

	$db->free_result($request);

	return $pollStarters;
}

/**
 * Check if they have already voted, or voting is locked.
 *
 * @param int $topic the topic with an associated poll
 */
function checkVote($topic)
{
	global $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT COALESCE(lp.id_choice, -1) AS selected, p.voting_locked, p.id_poll, p.expire_time, p.max_votes, p.change_vote,
			p.guest_vote, p.reset_poll, p.num_guest_voters
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			LEFT JOIN {db_prefix}log_polls AS lp ON (p.id_poll = lp.id_poll AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'not_guest' => 0,
		)
	);

	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	return $row;
}

/**
 * Removes the member's vote from a poll.
 *
 * @param int $id_member The id of the member
 * @param int $id_poll The topic with an associated poll.
 */
function removeVote($id_member, $id_poll)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}log_polls
		WHERE id_member = {int:current_member}
			AND id_poll = {int:id_poll}',
		array(
			'current_member' => $id_member,
			'id_poll' => $id_poll,
		)
	);
}

/**
 * Used to decrease the vote counter for the given poll.
 *
 * @param int $id_poll The id of the poll to lower the vote count
 * @param int[] $options The available poll options
 */
function decreaseVoteCounter($id_poll, $options)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}poll_choices
		SET votes = votes - 1
		WHERE id_poll = {int:id_poll}
			AND id_choice IN ({array_int:poll_options})
			AND votes > {int:votes}',
		array(
			'poll_options' => $options,
			'id_poll' => $id_poll,
			'votes' => 0,
		)
	);
}

/**
 * Increase the vote counter for the given poll.
 *
 * @param int $id_poll The id of the poll to increase the vote count
 * @param int[] $options The available poll options
 */
function increaseVoteCounter($id_poll, $options)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}poll_choices
		SET votes = votes + 1
		WHERE id_poll = {int:id_poll}
			AND id_choice IN ({array_int:poll_options})',
		array(
			'poll_options' => $options,
			'id_poll' => $id_poll,
		)
	);
}

/**
 * Add a vote to a poll.
 *
 * @param mixed[] $insert array of vote details, includes member and their choice
 */
function addVote($insert)
{
	$db = database();

	$db->insert('insert',
		'{db_prefix}log_polls',
		array('id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'),
		$insert,
		array('id_poll', 'id_member', 'id_choice')
	);
}

/**
 * Increase the vote counter for guest votes.
 *
 * @param int $id_poll The id of the poll to increase
 */
function increaseGuestVote($id_poll)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}polls
		SET num_guest_voters = num_guest_voters + 1
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $id_poll,
		)
	);
}

/**
 * Determines who voted what.
 *
 * @param int $id_member id of the member who's vote choice we want
 * @param int $id_poll id fo the poll the member voted in
 *
 * @return int[]
 */
function determineVote($id_member, $id_poll)
{
	$db = database();
	$pollOptions = array();

	$request = $db->query('', '
		SELECT id_choice
		FROM {db_prefix}log_polls
		WHERE id_member = {int:current_member}
			AND id_poll = {int:id_poll}',
		array(
			'current_member' => $id_member,
			'id_poll' => $id_poll,
		)
	);
	while ($choice = $db->fetch_row($request))
		$pollOptions[] = $choice[0];
	$db->free_result($request);

	return $pollOptions;
}

/**
 * Get some basic details from a poll
 *
 * @deprecated since 2.0 - use pollInfoForTopic instead
 * @param int $id_topic
 * @return array
 */
function pollStatus($id_topic)
{
	Errors::instance()->log_deprecated('pollStatus()', 'pollInfoForTopic()');
	return pollInfoForTopic($id_topic);
}

/**
 * Update the locked status from a given poll.
 *
 * @param int $id_poll The id of the poll to check
 * @param int $locked the value to set in voting_locked
 */
function lockPoll($id_poll, $locked)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}polls
		SET voting_locked = {int:voting_locked}
		WHERE id_poll = {int:id_poll}',
		array(
			'voting_locked' => $locked,
			'id_poll' => $id_poll,
		)
	);
}

/**
 * Gets poll choices from a given poll.
 *
 * @param int $id_poll The id of the poll
 * @return array
 */
function getPollChoices($id_poll)
{
	$db = database();

	$request = $db->query('', '
		SELECT label, votes, id_choice
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:id_poll}',
		array(
			'id_poll' => $id_poll,
		)
	);

	$choices = array();
	$number = 1;
	while ($row = $db->fetch_assoc($request))
	{
		$row['label'] = censor($row['label']);
		$choices[$row['id_choice']] = array(
			'id' => $row['id_choice'],
			'number' => $number++,
			'votes' => $row['votes'],
			'label' => $row['label'],
			'is_last' => false
		);
	}
	$db->free_result($request);

	return $choices;
}

/**
 * Get the poll starter from a given poll.
 *
 * @param int $id_topic The id of the topic that has an associated poll
 *
 * @return array
 * @throws Elk_Exception no_board
 */
function getPollStarter($id_topic)
{
	$db = database();

	$request = $db->query('', '
		SELECT t.id_member_started, t.id_poll, p.id_member AS poll_starter, p.expire_time
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $id_topic,
		)
	);
	if ($db->num_rows($request) == 0)
		throw new Elk_Exception('no_board');
	$bcinfo = $db->fetch_assoc($request);
	$db->free_result($request);

	return $bcinfo;
}

/**
 * Loads in $context whatever is needed to show a poll
 *
 * @param int $poll_id simply a poll id...
 */
function loadPollContext($poll_id)
{
	global $context, $user_info, $txt, $scripturl;

	// Get the question and if it's locked.
	$pollinfo = pollInfo($poll_id);

	// Get all the options, and calculate the total votes.
	$pollOptions = pollOptionsForMember($poll_id, $user_info['id']);

	// Compute total votes.
	$realtotal = 0;
	$pollinfo['has_voted'] = false;
	foreach ($pollOptions as $choice)
	{
		$realtotal += $choice['votes'];
		$pollinfo['has_voted'] |= $choice['voted_this'] != -1;
	}

	// If this is a guest we need to do our best to work out if they have voted, and what they voted for.
	if ($user_info['is_guest'] && $pollinfo['guest_vote'] && allowedTo('poll_vote'))
	{
		if (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . $poll_id . ',') !== false)
		{
			// ;id,timestamp,[vote,vote...]; etc
			$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);

			// Find the poll we're after.
			foreach ($guestinfo as $i => $guestvoted)
			{
				$guestvoted = explode(',', $guestvoted);
				if ($guestvoted[0] == $poll_id)
					break;
			}

			// Has the poll been reset since guest voted?
			if ($pollinfo['reset_poll'] > $guestvoted[1])
			{
				// Remove the poll info from the cookie to allow guest to vote again
				unset($guestinfo[$i]);
				if (!empty($guestinfo))
					$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
				else
					unset($_COOKIE['guest_poll_vote']);
			}
			else
			{
				// What did they vote for?
				unset($guestvoted[0], $guestvoted[1]);
				foreach ($pollOptions as $choice => $details)
				{
					$pollOptions[$choice]['voted_this'] = in_array($choice, $guestvoted) ? 1 : -1;
					$pollinfo['has_voted'] |= $pollOptions[$choice]['voted_this'] != -1;
				}
				unset($choice, $details, $guestvoted);
			}
			unset($guestinfo, $guestvoted, $i);
		}
	}

	$bbc_parser = \BBC\ParserWrapper::instance();

	// Set up the basic poll information.
	$context['poll'] = array(
		'id' => $poll_id,
		'image' => 'normal_' . (empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll'),
		'question' => $bbc_parser->parsePoll($pollinfo['question']),
		'total_votes' => $pollinfo['total'],
		'change_vote' => !empty($pollinfo['change_vote']),
		'is_locked' => !empty($pollinfo['voting_locked']),
		'options' => array(),
		'lock' => allowedTo('poll_lock_any') || ($context['user']['started'] && allowedTo('poll_lock_own')),
		'edit' => allowedTo('poll_edit_any') || ($context['user']['started'] && allowedTo('poll_edit_own')),
		'allowed_warning' => $pollinfo['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($pollOptions), $pollinfo['max_votes'])) : '',
		'is_expired' => !empty($pollinfo['expire_time']) && $pollinfo['expire_time'] < time(),
		'expire_time' => !empty($pollinfo['expire_time']) ? standardTime($pollinfo['expire_time']) : 0,
		'has_voted' => !empty($pollinfo['has_voted']),
		'starter' => array(
			'id' => $pollinfo['id_member'],
			'name' => $pollinfo['poster_name'],
			'href' => $pollinfo['id_member'] == 0 ? '' : $scripturl . '?action=profile;u=' . $pollinfo['id_member'],
			'link' => $pollinfo['id_member'] == 0 ? $pollinfo['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $pollinfo['id_member'] . '">' . $pollinfo['poster_name'] . '</a>'
		)
	);

	// Make the lock and edit permissions defined above more directly accessible.
	$context['allow_lock_poll'] = $context['poll']['lock'];
	$context['allow_edit_poll'] = $context['poll']['edit'];

	// You're allowed to vote if:
	// 1. the poll did not expire, and
	// 2. you're either not a guest OR guest voting is enabled... and
	// 3. you're not trying to view the results, and
	// 4. the poll is not locked, and
	// 5. you have the proper permissions, and
	// 6. you haven't already voted before.
	$context['allow_vote'] = !$context['poll']['is_expired'] && (!$user_info['is_guest'] || ($pollinfo['guest_vote'] && allowedTo('poll_vote'))) && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && !$context['poll']['has_voted'];

	// You're allowed to view the results if:
	// 1. you're just a super-nice-guy, or
	// 2. anyone can see them (hide_results == 0), or
	// 3. you can see them after you voted (hide_results == 1), or
	// 4. you've waited long enough for the poll to expire. (whether hide_results is 1 or 2.)
	$context['allow_poll_view'] = allowedTo('moderate_board') || $pollinfo['hide_results'] == 0 || ($pollinfo['hide_results'] == 1 && $context['poll']['has_voted']) || $context['poll']['is_expired'];
	$context['poll']['show_results'] = $context['allow_poll_view'] && (isset($_REQUEST['viewresults']) || isset($_REQUEST['viewResults']));

	// You're allowed to change your vote if:
	// 1. the poll did not expire, and
	// 2. you're not a guest... and
	// 3. the poll is not locked, and
	// 4. you have the proper permissions, and
	// 5. you have already voted, and
	// 6. the poll creator has said you can!
	$context['allow_change_vote'] = !$context['poll']['is_expired'] && !$user_info['is_guest'] && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && $context['poll']['has_voted'] && $context['poll']['change_vote'];

	// You're allowed to return to voting options if:
	// 1. you are (still) allowed to vote.
	// 2. you are currently seeing the results.
	$context['allow_return_vote'] = $context['allow_vote'] && $context['poll']['show_results'];

	// Calculate the percentages and bar lengths...
	$divisor = $realtotal == 0 ? 1 : $realtotal;

	// Determine if a decimal point is needed in order for the options to add to 100%.
	$precision = $realtotal == 100 ? 0 : 1;

	// Now look through each option, and...
	foreach ($pollOptions as $i => $option)
	{
		// First calculate the percentage, and then the width of the bar...
		$bar = round(($option['votes'] * 100) / $divisor, $precision);
		$barWide = $bar == 0 ? 1 : floor(($bar * 8) / 3);

		// Now add it to the poll's contextual theme data.
		$context['poll']['options'][$i] = array(
			'id' => 'options-' . $i,
			'percent' => $bar,
			'votes' => $option['votes'],
			'voted_this' => $option['voted_this'] != -1, /* Todo: I notice 'bar' here is not used in the theme any longer - only in SSI. */
			'bar' => '<div class="poll_gradient" style="width: ' . $barWide . 'px;"></div>',
			'bar_ndt' => $bar > 0 ? '<div class="bar poll-bar" style="width: ' . $bar . '%;"></div>' : '<div class="bar poll-bar"></div>',
			'bar_width' => $barWide,
			'option' => $bbc_parser->parsePoll($option['label']),
			'vote_button' => '<input type="' . ($pollinfo['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . $i . '" value="' . $i . '" class="input_' . ($pollinfo['max_votes'] > 1 ? 'check' : 'radio') . '" />'
		);
	}
}
