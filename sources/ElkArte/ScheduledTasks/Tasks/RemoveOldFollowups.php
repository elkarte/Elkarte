<?php

/**
 * Check for followups from removed topics and remove them from the table
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\ScheduledTasks\Tasks;

/**
 * Check for followups from removed topics and remove them from the table
 *
 * @package ScheduledTasks
 */
class RemoveOldFollowups implements ScheduledTaskInterface
{
	/**
	 * Remove followups that point to removed topics
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function run()
	{
		global $modSettings;

		if (empty($modSettings['enableFollowup']))
		{
			return false;
		}

		$db = database();

		// The old FU request :P
		$remove = array();
		$db->fetchQuery('
			SELECT 
				fu.derived_from
			FROM {db_prefix}follow_ups AS fu
				LEFT JOIN {db_prefix}messages AS m ON (fu.derived_from = m.id_msg)
			WHERE m.id_msg IS NULL
			LIMIT {int:limit}',
			array(
				'limit' => 100,
			)
		)->fetch_callback(
			function ($row) use (&$remove) {
				$remove[] = $row['derived_from'];
			}
		);

		if (empty($remove))
		{
			return true;
		}

		require_once(SUBSDIR . '/FollowUps.subs.php');
		removeFollowUpsByMessage($remove);

		return true;
	}
}
