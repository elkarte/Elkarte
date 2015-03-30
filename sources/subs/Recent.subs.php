<?php

/**
 * This file contains a couple of functions for the latests posts on forum.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Get the latest posts of a forum.
 *
 * @param mixed[] $latestPostOptions
 * @return array
 */
function getLastPosts($latestPostOptions)
{
	global $scripturl, $modSettings;

	$db = database();

	// Find all the posts. Newer ones will have higher IDs. (assuming the last 20 * number are accessable...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $db->query('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_board, b.name AS board_name,
			SUBSTRING(m.body, 1, 385) AS body, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg >= {int:likely_max_msg}' .
			(!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}' : '') . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $latestPostOptions['number_posts'],
		array(
			'likely_max_msg' => max(0, $modSettings['maxMsgID'] - 50 * $latestPostOptions['number_posts']),
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	$posts = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Censor the subject and post for the preview ;).
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		$row['body'] = Util::shorten_text($row['body'], !empty($modSettings['lastpost_preview_characters']) ? $modSettings['lastpost_preview_characters'] : 128, true);

		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => Util::shorten_text($row['subject'], $modSettings['subject_length']),
			'preview' => $row['body'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>'
		);
	}
	$db->free_result($request);

	return $posts;
}

/**
 * Callback-function for the cache for getLastPosts().
 *
 * @param mixed[] $latestPostOptions
 */
function cache_getLastPosts($latestPostOptions)
{
	return array(
		'data' => getLastPosts($latestPostOptions),
		'expires' => time() + 60,
		'post_retri_eval' => '
			foreach ($cache_block[\'data\'] as $k => $post)
			{
				$cache_block[\'data\'][$k] += array(
					\'time\' => standardTime($post[\'raw_timestamp\']),
					\'html_time\' => htmlTime($post[\'raw_timestamp\']),
					\'timestamp\' => $post[\'raw_timestamp\'],
				);
			}',
	);
}

/**
 * Formats data supplied into a form that can be used in the template
 *
 * @param mixed[] $messages
 * @param int $start
 */
function prepareRecentPosts($messages, $start)
{
	global $user_info, $scripturl, $modSettings;

	$counter = $start + 1;
	$posts = array();
	$board_ids = array('own' => array(), 'any' => array());
	foreach ($messages as $row)
	{
		// Censor everything.
		censorText($row['body']);
		censorText($row['subject']);

		// BBC-atize the message.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And build the array.
		$posts[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cname'],
				'href' => $scripturl . '#c' . $row['id_cat'],
				'link' => '<a href="' . $scripturl . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => standardTime($row['poster_time']),
			'html_time' => htmlTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'first_poster' => array(
				'id' => $row['first_id_member'],
				'name' => $row['first_display_name'],
				'href' => empty($row['first_id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['first_id_member'],
				'link' => empty($row['first_id_member']) ? $row['first_display_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '">' . $row['first_display_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'body' => $row['body'],
			'message' => $row['body'],
			'tests' => array(
				'can_reply' => false,
				'can_mark_notify' => false,
				'can_delete' => false,
			),
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
		);

		if ($user_info['id'] == $row['first_id_member'])
			$board_ids['own'][$row['id_board']][] = $row['id_msg'];
		$board_ids['any'][$row['id_board']][] = $row['id_msg'];
	}

	return array($posts, $board_ids);
}

/**
 * Return the earliest message a user can...see?
 */
function earliest_msg()
{
	global $board, $user_info;

	$db = database();

	if (!empty($board))
	{
		$request = $db->query('', '
			SELECT MIN(id_msg)
			FROM {db_prefix}log_mark_read
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
			)
		);
		list ($earliest_msg) = $db->fetch_row($request);
		$db->free_result($request);
	}
	else
	{
		$request = $db->query('', '
			SELECT MIN(lmr.id_msg)
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
			WHERE {query_see_board}',
			array(
				'current_member' => $user_info['id'],
			)
		);
		list ($earliest_msg) = $db->fetch_row($request);
		$db->free_result($request);
	}

	// This is needed in case of topics marked unread.
	if (empty($earliest_msg))
		$earliest_msg = 0;
	else
	{
		// Using caching, when possible, to ignore the below slow query.
		if (isset($_SESSION['cached_log_time']) && $_SESSION['cached_log_time'][0] + 45 > time())
			$earliest_msg2 = $_SESSION['cached_log_time'][1];
		else
		{
			// This query is pretty slow, but it's needed to ensure nothing crucial is ignored.
			$request = $db->query('', '
				SELECT MIN(id_msg)
				FROM {db_prefix}log_topics
				WHERE id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
				)
			);
			list ($earliest_msg2) = $db->fetch_row($request);
			$db->free_result($request);

			// In theory this could be zero, if the first ever post is unread, so fudge it ;)
			if ($earliest_msg2 == 0)
				$earliest_msg2 = -1;

			$_SESSION['cached_log_time'] = array(time(), $earliest_msg2);
		}

		$earliest_msg = min($earliest_msg2, $earliest_msg);
	}

	return $earliest_msg;
}

/**
 * Formats the data obtained from the database in a template-friendly way
 *
 * Currently used by getUnreadTopics and getUnreadReplies
 *
 * @param mixed[] $topics_info - data coming from a query,
 *                for example generated by getUnreadTopics
 * @param bool $topicseen - if use the temp table or not
 * @return mixed[] - array of data related to topics
 */
function processRecentTopicList($topics_info, $topicseen = false)
{
	global $modSettings, $options, $scripturl, $context, $txt, $settings, $user_info;

	$topics = array();
	$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
	$topicseen = $topicseen ? ';topicseen' : '';

	foreach ($topics_info as $row)
	{
		// is message previews enabled?
		if (!empty($modSettings['message_index_preview']))
		{
			// Limit them to $modSettings['preview_characters'] characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br />' => "\n", '&nbsp;' => ' ')));
			$row['first_body'] = Util::shorten_text($row['first_body'], !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128, true);

			// No reply then they are the same, no need to process it again
			if ($row['num_replies'] == 0)
				$row['last_body'] == $row['first_body'];
			else
			{
				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br />' => "\n", '&nbsp;' => ' ')));
				$row['last_body'] = Util::shorten_text($row['last_body'], !empty($modSettings['preview_characters']) ? $modSettings['preview_characters'] : 128, true);
			}

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		if ($topic_length > $messages_per_page)
		{
			// We can't pass start by reference.
			$start = -1;
			$pages = constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d' . $topicseen, $start, $topic_length, $messages_per_page, true, array('prev_next' => false, 'all' => !empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages']));

			// If we can use all, show it.
			if (!empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
		}
		else
			$pages = '';

		// We need to check the topic icons exist... you can never be too sure!
		if (!empty($modSettings['messageIconChecks_enable']))
		{
			// First icon first... as you'd expect.
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';

			// Last icon... last... duh.
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		}
		else
		{
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = 'images_url';

			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = 'images_url';
		}

		if ($user_info['is_guest'])
		{
			$url_fragment = '.' . ((int) (($row['num_replies']) / $messages_per_page)) * $messages_per_page . $topicseen . '#msg' . $row['id_last_msg'];
		}
		else
		{
			$url_fragment = ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . $topicseen . '#new';
		}

		// And build the array.
		$topics[$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'username' => $row['first_member_name'],
					'name' => $row['first_display_name'],
					'id' => $row['first_id_member'],
					'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
					'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
				),
				'time' => standardTime($row['first_poster_time']),
				'html_time' => htmlTime($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'preview' => trim($row['first_body']),
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0' . $topicseen,
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0' . $topicseen . '">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'username' => $row['last_member_name'],
					'name' => $row['last_display_name'],
					'id' => $row['last_id_member'],
					'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
					'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
				),
				'time' => standardTime($row['last_poster_time']),
				'html_time' => htmlTime($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'preview' => trim($row['last_body']),
				'icon' => $row['last_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
				'href' => $scripturl . '?topic=' . $row['id_topic'] . $url_fragment,
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . $url_fragment . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>',
			),
			'default_preview' => trim($row[!empty($modSettings['message_index_preview']) && $modSettings['message_index_preview'] == 2 ? 'last_body' : 'first_body']),
			'is_sticky' => !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'is_poll' => !empty($modSettings['pollMode']) && $row['id_poll'] > 0,
			'is_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicPosts'] : $row['num_replies'] >= $modSettings['hotTopicPosts'],
			'is_very_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicVeryPosts'] : $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
			'is_posted_in' => false,
			'icon' => $row['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
			'subject' => $row['first_subject'],
			'new' => !empty($row['id_msg_modified']) && $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'newtime' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . $topicseen . '#new',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . $topicseen . ($row['num_replies'] == 0 ? '' : 'new'),
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . $topicseen . '#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
			'redir_href' => !empty($row['id_redirect_topic']) ? $scripturl . '?topic=' . $row['id_topic'] . '.0;noredir' : '',
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views']),
			'likes' => comma_format($row['num_likes']),
			'approved' => $row['approved'],
			'unapproved_posts' => !empty($row['unapproved_posts']) ? $row['unapproved_posts'] : 0,
		);

		if (!empty($row['id_board']))
			$topics[$row['id_topic']]['board'] = array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			);

		if (!empty($settings['avatars_on_indexes']))
		{
			$topics[$row['id_topic']]['last_post']['member']['avatar'] = determineAvatar($row);
			if ($settings['avatars_on_indexes'] > 1)
			{
				$first_avatar = array(
					'avatar' => $row['avatar_first'],
					'id_attach' => $row['id_attach_first'],
					'attachment_type' => $row['attachment_type_first'],
					'filename' => $row['filename_first'],
					'email_address' => $row['email_address_first'],
				);
				$topics[$row['id_topic']]['first_post']['member']['avatar'] = determineAvatar($first_avatar);
			}
		}

		// @deprecated since 1.0 - better have the sprintf in the template because using html here is bad
		$topics[$row['id_topic']]['first_post']['started_by'] = sprintf($txt['topic_started_by_in'], '<strong>' . $topics[$row['id_topic']]['first_post']['member']['link'] . '</strong>', '<em>' . $topics[$row['id_topic']]['board']['link'] . '</em>');

		determineTopicClass($topics[$row['id_topic']]);
	}

	return $topics;
}