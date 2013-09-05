<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains functions that make easier send notifications to mentioned users.
 *
 */

if (!defined('ELK'))
	die('No access...');

function identifyMentionUsers(&$body)
{
	global $modSettings;

	$users = findNotifiedUsers($body);

	if (empty($users))
		return;

	$users_string_array = array();
	$limit = min(empty($modSettings['max_mentioned_members']) ? count($users) : $modSettings['max_mentioned_members'], count($users));
	$notifications = array();
	for ($i = 0; $i < $limit; $i++)
	{
		$notifications[$users[$i]['id_member']] = preg_quote($users[$i]['real_name']);
		$users_string_array[] = '\'' . addcslashes($users[$i]['real_name'], '\'') . '\' => ' . $users[$i]['id_member'];
	}

	// Let's make it easier for us to show a member has been mentioned with a bbcode
	$new_body = preg_replace_callback(
		'~(\s|<br />)@(' . implode('|', $notifications) . ')~',
		create_function('$match', '
			$members = array(' . implode(', ', $users_string_array) . ');
			// @todo do we need addcslashes on $match[2]?
			return $match[1] . \'[user=\' . $members[$match[2]] . \']\' . $match[2] . \'[/user]\';'
		),
		'<br />' . un_htmlspecialchars($body)
	);

	$body = substr($new_body, 6);

	return $notifications;
}

function findNotifiedUsers($body)
{
	// Valid are:
	//  - 1st thing in the message (i.e. at the beginning of the string) detected adding a <br /> so it falls into the next,
	//  - <br />@
	//  - \s@
	// Non valid are:
	//  - @\s
	//  - [^ ]@
	// Invalid chars in names: <>&"\'=\\\\
	//  plus space as first char
	// @todo the 2nd match can be removed and included in the current 3rd
	preg_match_all('~(\s|<br />)@([^ <>&"\'=\\\\]{1})([^<>&"\'=\\\\]{1})([^<>&"\'=\\\\]{0,58})~', '<br />' . un_htmlspecialchars($body), $matches);

	$lookups = array();
	for ($i = 0; $i < count($matches[2]); $i++)
	{
		if (!isset($lookups[$matches[2][$i] . $matches[3][$i]]))
			$lookups[$matches[2][$i] . $matches[3][$i]] = array();
		$lookups[$matches[2][$i] . $matches[3][$i]][] = Util::substr($matches[0][$i], strpos($matches[0][$i], '@') + 1);

		if ($matches[3][$i] == ' ')
		{
			if (!isset($lookups[$matches[2][$i]]))
				$lookups[$matches[2][$i]] = array();
			$lookups[$matches[2][$i]][] = Util::substr($matches[0][$i], strpos($matches[0][$i], '@') + 1);
		}
	}

	$to_notify = array();
	foreach ($lookups as $key => $names)
	{
		if (($names_data = cache_get_data('lists_of_members_' . $key, 3600)) === null)
			$names_data = rebuildMembersCache($key);

		// This key doesn't have any valid name, go on
		if (empty($names_data['names']))
			continue;

		foreach ($names as $name)
		{
			$name_len = strlen($name);
			// The name is longer or shorter than those existing
			if ($name_len < $names_data['min_len'] || $name_len > $names_data['max_len'])
				continue;

			// min because if the name is shorter than the maximum available is useless to check things twice
			// Instead the end of the loop is min_len because the real name can be shorter than what we guessed
			for ($i = min($name_len, $names_data['max_len']); $i >= $names_data['min_len']; $i--)
			{
				$short_name = Util::substr($name, 0, $i);
				if (isset($names_data['names'][$short_name]))
				{
					$to_notify[] = $names_data['names'][$short_name];
					continue;
				}
			}
		}
	}

	return $to_notify;
}

function rebuildMembersCache($key)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:abbreviation}
			AND (is_activated = 1 OR is_activated = 2)',
		array(
			'abbreviation' => $key . '%',
		)
	);
	$return = array(
		'names' => array(),
		'max_len' => 0,
		'min_len' => 60,
	);
	while ($row = $db->fetch_assoc($request))
	{
		$len = Util::strlen($row['real_name']);
		if ($len > $return['max_len'])
			$return['max_len'] = $len;
		if ($len < $return['min_len'])
			$return['min_len'] = $len;
		$return['names'][$row['real_name']] = $row;
	}
	$db->free_result($request);

	cache_put_data('lists_of_members_' . $key, $return, 3600);

	return $return;
}

function sendMentionNotification($mentioner, $message, $users)
{
	global $scripturl;

	$db = database();
	require_once(SUBSDIR . '/Members.subs.php');

	$request = $db->query('', '
		SELECT subject
		FROM {db_prefix}messages
		WHERE id_msg = {int:current_message}',
		array(
			'current_message' => $message,
		)
	);

	// Select the email addresses for this batch.
	$conditions = array(
		'activated_status' => 1,
		'members' => array_keys($users),
	);

	$data = retrieveMemberData($conditions);

	$mentions = array();
	while ($row = $db->fetch_row($request))
	{
		$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

		$replacements = array(
			'MENTIONER' => $mentioner,
			'TOPICSUBJECT' => $row['subject'],
			'TOPICLINK' => $scripturl . '?msg=' . $row['id_mgs'],
		);

		$emaildata = loadEmailTemplate('mention_notification', $replacements, $cur_language);

		$mentions[$cur_language] = array(
			'subject' => $emaildata['subject'],
			'body' => $emaildata['body'],
			'recipients' => array(),
		);
	}
	$db->free_result($request);

	foreach ($mentions as $lang => $mail)
		sendmail($mail['recipients'], $mail['subject'], $mail['body']);
}