<?php

/**
 * This file contains the database work for karma.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

/**
 * Remove old karma from the log
 *
 * @package Karma
 * @param int $karmaWaitTime
 */
function clearKarma($karmaWaitTime)
{
	$db = database();

	// Delete any older items from the log. (karmaWaitTime is by hour.)
	$db->query('', '
		DELETE FROM {db_prefix}log_karma
		WHERE {int:current_time} - log_time > {int:wait_time}',
		array(
			'wait_time' => $karmaWaitTime * 3600,
			'current_time' => time(),
		)
	);
}

/**
 * Last action this user has done
 *
 * @package Karma
 * @param int $id_executor
 * @param int $id_target
 */
function lastActionOn($id_executor, $id_target)
{
	$db = database();

	// Find out if this user has done this recently...
	$request = $db->query('', '
		SELECT action
		FROM {db_prefix}log_karma
		WHERE id_target = {int:id_target}
			AND id_executor = {int:current_member}
		LIMIT 1',
		array(
			'current_member' => $id_executor,
			'id_target' => $id_target,
		)
	);
	if ($db->num_rows($request) > 0)
		list ($action) = $db->fetch_row($request);
	$db->free_result($request);

	return isset($action) ? $action : null;
}

/**
 * Add a karma action, from executor to target.
 *
 * @package Karma
 * @param int $id_executor
 * @param int $id_target
 * @param int $direction - options: -1 or 1
 */
function addKarma($id_executor, $id_target, $direction)
{
	$db = database();

	// Put it in the log.
	$db->insert('replace',
		'{db_prefix}log_karma',
		array('action' => 'int', 'id_target' => 'int', 'id_executor' => 'int', 'log_time' => 'int'),
		array($direction, $id_target, $id_executor, time()),
		array('id_target', 'id_executor')
	);

	// Change by one.
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($_REQUEST['uid'], array($direction == 1 ? 'karma_good' : 'karma_bad' => '+'));
}

/**
 * Update a former karma action from executor to target.
 *
 * @package Karma
 * @param int $id_executor
 * @param int $id_target
 * @param int $direction - options: -1 or 1
 */
function updateKarma($id_executor, $id_target, $direction)
{
	$db = database();

	// You decided to go back on your previous choice?
	$db->query('', '
		UPDATE {db_prefix}log_karma
		SET action = {int:action}, log_time = {int:current_time}
		WHERE id_target = {int:id_target}
			AND id_executor = {int:current_member}',
		array(
			'current_member' => $id_executor,
			'action' => $direction,
			'current_time' => time(),
			'id_target' => $id_target,
		)
	);

	// It was recently changed the OTHER way... so... reverse it!
	require_once(SUBSDIR . '/Members.subs.php');
	if ($direction == 1)
		updateMemberData($_REQUEST['uid'], array('karma_good' => '+', 'karma_bad' => '-'));
	else
		updateMemberData($_REQUEST['uid'], array('karma_bad' => '+', 'karma_good' => '-'));
}