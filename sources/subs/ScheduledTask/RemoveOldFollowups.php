<?php

/**
 * Check for followups from removed topics and remove them from the table
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

if (!defined('ELK'))
	die('No access...');

/**
 * Check for followups from removed topics and remove them from the table
 *
 * @package ScheduledTasks
 */
class Remove_Old_Followups implements Scheduled_Task_Interface
{
	public function run()
	{
		global $modSettings;

		if (empty($modSettings['enableFollowup']))
			return false;

		$db = database();

		$request = $db->query('', '
			SELECT fu.derived_from
			FROM {db_prefix}follow_ups AS fu
				LEFT JOIN {db_prefix}messages AS m ON (fu.derived_from = m.id_msg)
			WHERE m.id_msg IS NULL
			LIMIT {int:limit}',
			array(
				'limit' => 100,
			)
		);

		$remove = array();
		while ($row = $db->fetch_assoc($request))
			$remove[] = $row['derived_from'];
		$db->free_result($request);

		if (empty($remove))
			return true;

		require_once(SUBSDIR . '/FollowUps.subs.php');
		removeFollowUpsByMessage($remove);

		return true;
	}
}