<?php

/**
 * This file is mainly concerned with tasks relating to follow-ups, such as
 * link messages and topics, delete follow-ups, etc.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 */

/**
 * Retrieves all the follow-up topic for a certain message
 *
 * @param int[] $messages int array of message ids to work on
 * @param boolean $include_approved
 */
function followupTopics($messages, $include_approved = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT fu.derived_from, fu.follow_up, m.subject
		FROM {db_prefix}follow_ups AS fu
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = fu.follow_up)
			LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE fu.derived_from IN ({array_int:messages})' . ($include_approved ? '' : '
			AND m.approved = {int:approved}'),
		array(
			'messages' => $messages,
			'approved' => 1,
		)
	);

	$returns = array();
	while ($row = $db->fetch_assoc($request))
		$returns[$row['derived_from']][] = $row;

	return $returns;
}

/**
 * Retrieves the message from which the topic started
 *
 * @param int $topic id of the original topic the threads were started from
 * @param boolean $include_approved
 */
function topicStartedHere($topic, $include_approved = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT fu.derived_from, m.subject
		FROM {db_prefix}follow_ups AS fu
			LEFT JOIN {db_prefix}messages AS m ON (fu.derived_from = m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE fu.follow_up = {int:original_topic}' . ($include_approved ? '' : '
			AND m.approved = {int:approved}') . '
		LIMIT 1',
		array(
			'original_topic' => $topic,
			'approved' => 1,
		)
	);

	$returns = array();
	while ($row = $db->fetch_assoc($request))
		$returns = $row;

	return $returns;
}

/**
 * Simple function used to create a "followup" relation between a message and a topic
 *
 * @param int $msg message id
 * @param int $topic topic id
 */
function linkMessages($msg, $topic)
{
	$db = database();

	$db->insert('ignore',
		'{db_prefix}follow_ups',
		array('follow_up' => 'int', 'derived_from' => 'int'),
		array($topic, $msg),
		array('follow_up', 'derived_from')
	);
}

/**
 * Used to break a "followup" relation between a message and a topic
 * Actually the function is not used at all...
 *
 * @todo remove?
 * @param int $msg message id
 * @param int $topic topic id
 */
function unlinkMessages($msg, $topic)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}follow_ups
		WHERE derived_from = {int:id_msg}
			AND follow_up = {int:id_topic}
		LIMIT 1',
		array(
			'id_msg' => $msg,
			'id_topic' => $topic,
		)
	);
}

/**
 * Removes all the follow-ups from the db by topics
 *
 * @param int|int[] $topics topic id
 */
function removeFollowUpsByTopic($topics)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}follow_ups
		WHERE follow_up IN ({array_int:id_topics})',
		array(
			'id_topics' => is_array($topics) ? $topics : array($topics),
		)
	);
}

/**
 * Removes all the follow-ups from the db by message id
 *
 * @param int[]|int $msgs int or array of ints for the message id's to work on
 */
function removeFollowUpsByMessage($msgs)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}follow_ups
		WHERE derived_from IN ({array_int:id_msgs})',
		array(
			'id_msgs' => is_array($msgs) ? $msgs : array($msgs),
		)
	);
}