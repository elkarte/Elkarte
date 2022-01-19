<?php

/**
 * DB and general functions for working with the message index
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

/**
 * Builds the message index with the supplied parameters
 * creates all you ever wanted on message index, returns the data in array
 *
 * @param int $id_board board to build the topic listing for
 * @param int $id_member who we are building it for so we don't show unapproved topics
 * @param int $start where to start from
 * @param int $items_per_page The number of items to show per page
 * @param string $sort_by how to sort the results asc/desc
 * @param string $sort_column which value we sort by
 * @param mixed[] $indexOptions
 *     'include_sticky' => if on, loads sticky topics as additional
 *     'only_approved' => if on, only load approved topics
 *     'previews' => if on, loads in a substring of the first/last message text for use in previews
 *     'include_avatars' => if on loads the last message posters avatar
 *     'ascending' => ASC or DESC for the sort
 *     'fake_ascending' =>
 *     'custom_selects' => loads additional values from the tables used in the query, for addon use
 *
 * @return array
 */
function messageIndexTopics($id_board, $id_member, $start, $items_per_page, $sort_by, $sort_column, $indexOptions)
{
	$db = database();

	$topics = array();
	$indexOptions = array_merge(array(
		'include_sticky' => true,
		'fake_ascending' => false,
		'ascending' => true,
		'only_approved' => true,
		'previews' => -1,
		'include_avatars' => false,
		'custom_selects' => array(),
		'custom_joins' => array(),
	), $indexOptions);

	// Fetch topic list in the order we want.
	$db->fetchQuery('
		SELECT 
			t.id_topic
		FROM {db_prefix}topics AS t' . ($sort_by === 'last_poster' ? '
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' : (in_array($sort_by, array('starter', 'subject')) ? '
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)' : '')) . ($sort_by === 'starter' ? '
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' : '') . ($sort_by === 'last_poster' ? '
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' : '') . '
		WHERE t.id_board = {int:current_board}' . (!$indexOptions['only_approved'] ? '' : '
			AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
		ORDER BY ' . ($indexOptions['include_sticky'] ? 'is_sticky' . ($indexOptions['fake_ascending'] ? '' : ' DESC') . ', ' : '') . $sort_column . ($indexOptions['ascending'] ? '' : ' DESC') . '
		LIMIT {int:maxindex} OFFSET {int:start}',
		array(
			'current_board' => $id_board,
			'current_member' => $id_member,
			'is_approved' => 1,
			'id_member_guest' => 0,
			'start' => $start,
			'maxindex' => $items_per_page,
		)
	)->fetch_callback(
		function ($row) use (&$topics) {
			$topics[$row['id_topic']] = [];
		}
	);

	// -1 means preview the whole body
	if ($indexOptions['previews'] === -1)
	{
		$indexOptions['custom_selects'] = array_merge($indexOptions['custom_selects'], array('ml.body AS last_body', 'mf.body AS first_body'));
	}
	// Default: a SUBSTRING
	elseif (!empty($indexOptions['previews']))
	{
		$indexOptions['custom_selects'] = array_merge($indexOptions['custom_selects'], array('SUBSTRING(ml.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS last_body', 'SUBSTRING(mf.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS first_body'));
	}

	if (!empty($indexOptions['include_avatars']))
	{
		if ($indexOptions['include_avatars'] === 1 || $indexOptions['include_avatars'] === 3)
		{
			$indexOptions['custom_selects'] = array_merge($indexOptions['custom_selects'], array('meml.avatar', 'COALESCE(a.id_attach, 0) AS id_attach', 'a.filename', 'a.attachment_type', 'meml.email_address'));
			$indexOptions['custom_joins'] = array_merge($indexOptions['custom_joins'], array('LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member AND a.id_member != 0)'));
		}

		if ($indexOptions['include_avatars'] === 2 || $indexOptions['include_avatars'] === 3)
		{
			$indexOptions['custom_selects'] = array_merge($indexOptions['custom_selects'], array('memf.avatar AS avatar_first', 'COALESCE(af.id_attach, 0) AS id_attach_first', 'af.filename AS filename_first', 'af.attachment_type AS attachment_type_first', 'memf.email_address AS email_address_first'));
			$indexOptions['custom_joins'] = array_merge($indexOptions['custom_joins'], array('LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mf.id_member AND af.id_member != 0)'));
		}

		$request = $db->fetchQuery('
			SELECT
				t.id_topic, t.num_replies, t.locked, t.num_views, t.num_likes, t.is_sticky, t.id_poll, t.id_previous_board,
				' . ($id_member == 0 ? '0' : 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1') . ' AS new_from,
				t.id_last_msg, t.approved, t.unapproved_posts, t.id_redirect_topic, t.id_first_msg,
				ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
				ml.poster_name AS last_member_name, ml.id_member AS last_id_member, ml.smileys_enabled AS last_smileys,
				COALESCE(meml.real_name, ml.poster_name) AS last_display_name,
				mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
				mf.poster_name AS first_member_name, mf.id_member AS first_id_member, mf.smileys_enabled AS first_smileys,
				COALESCE(memf.real_name, mf.poster_name) AS first_display_name
				' . (!empty($indexOptions['custom_selects']) ? ' ,' . implode(',', $indexOptions['custom_selects']) : '') . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . ($id_member == 0 ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') .
			(!empty($indexOptions['custom_joins']) ? implode("\n\t\t\t\t", $indexOptions['custom_joins']) : '') . '
			WHERE t.id_topic IN ({array_int:topic_list})',
			array(
				'current_board' => $id_board,
				'current_member' => $id_member,
				'topic_list' => $topics === [] ? [0] : array_keys($topics),
			)
		);
		// Now we fill the above array, maintaining index association.
		while (($row = $request->fetch_assoc()))
		{
			$topics[$row['id_topic']] = $row;
		}
		$request->free_result();
	}

	return $topics;
}

/**
 * This simple function returns the sort methods for message index in an array.
 */
function messageIndexSort()
{
	// Default sort methods for message index.
	$sort_methods = array(
		'subject' => 'mf.subject',
		'starter' => 'COALESCE(memf.real_name, mf.poster_name)',
		'last_poster' => 'COALESCE(meml.real_name, ml.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'likes' => 't.num_likes',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	call_integration_hook('integrate_messageindex_sort', array(&$sort_methods));

	return $sort_methods;
}

/**
 * This function determines if a user has posted in the list of topics,
 * and returns the list of those topics they posted in.
 *
 * @param int $id_member member to check
 * @param int[] $topic_ids array of topics ids to check for participation
 *
 * @return array
 */
function topicsParticipation($id_member, $topic_ids)
{
	$db = database();
	$topics = array();

	$db->fetchQuery('
		SELECT DISTINCT id_topic
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topic_list})
			AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'topic_list' => $topic_ids,
		)
	)->fetch_callback(
		function ($row) use (&$topics) {
			$topics[] = $row;
		}
	);

	return $topics;
}
