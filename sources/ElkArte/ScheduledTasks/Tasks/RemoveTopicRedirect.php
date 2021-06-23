<?php

/**
 * This file/class handles known scheduled tasks
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\ScheduledTasks\Tasks;

use ElkArte\Themes\ThemeLoader;

/**
 * Class Remove_Topic_Redirect - This class handles known scheduled tasks.
 *
 * - Each method implements a task, and
 * - it's called automatically for the task to run.
 *
 * @package ScheduledTasks
 */
class RemoveTopicRedirect implements ScheduledTaskInterface
{
	/**
	 * Removes all of the expired Move Redirect topic notices that people hate
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function run()
	{
		$db = database();

		// Init
		$topics = array();

		// We will need this for language files
		ThemeLoader::loadEssentialThemeData();

		// Find all of the old MOVE topic notices that were set to expire
		$db->fetchQuery('
			SELECT 
				id_topic
			FROM {db_prefix}topics
			WHERE redirect_expires <= {int:redirect_expires}
				AND redirect_expires <> 0',
			array(
				'redirect_expires' => time(),
			)
		)->fetch_callback(
			function ($row) use (&$topics) {
				$topics[] = $row[0];
			}
		);

		// Zap, you're gone
		if (count($topics) > 0)
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			removeTopics($topics, false, true);
		}

		return true;
	}
}
