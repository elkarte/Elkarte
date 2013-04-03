<?php

/**
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
 * @version 1.0 Alpha
 *
 * Manage and maintain the boards and categories of the forum.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Loads properties from non-standard groups
 * 
 * @param int $curBoard
 * @return array
 */
function getOtherGroups($curBoard)
{
	global $smcFunc;

	$groups = array();
	
	// Load membergroups.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group > {int:moderator_group} OR id_group = {int:global_moderator}
		ORDER BY min_posts, id_group != {int:global_moderator}, group_name',
		array(
			'moderator_group' => 3,
			'global_moderator' => 2,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($_REQUEST['sa'] == 'newboard' && $row['min_posts'] == -1)
			$curBoard['member_groups'][] = $row['id_group'];

		$groups[(int) $row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => trim($row['group_name']),
			'allow' => in_array($row['id_group'], $curBoard['member_groups']),
			'deny' => in_array($row['id_group'], $curBoard['deny_groups']),
			'is_post_group' => $row['min_posts'] != -1,
		);
		}
	$smcFunc['db_free_result']($request);

	return $groups;
}

/**
 * Get a list of moderators from a specific board
 * @param int $idboard
 * @return array
 */
function getBoardModerators($idboard)
{
	global $smcFunc;

	$moderators = array();

	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_board = {int:current_board}',
		array(
			'current_board' => $idboard,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$moderators[$row['id_member']] = $row['real_name'];
	$smcFunc['db_free_result']($request);

	return $moderators;
}

/**
 * g
 * @return array
 */
function getAllThemes()
{
	global $smcFunc;

	$themes = array();

	// Get all the themes...
	$request = $smcFunc['db_query']('', '
		SELECT id_theme AS id, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}',
		array(
			'name' => 'name',
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[] = $row;
	$smcFunc['db_free_result']($request);

	return $themes;
}