<?php

/**
 * This file/class handles known scheduled tasks
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
 * @version 1.1 dev
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

if (!defined('ELK'))
	die('No access...');

/**
 * This class handles known scheduled tasks.
 *
 * - Each method implements a task, and
 * - it's called automatically for the task to run.
 *
 * @package ScheduledTasks
 */
class Remove_Topic_Redirect implements Scheduled_Task_Interface
{
	public function run()
	{
		$db = database();

		// Init
		$topics = array();

		// We will need this for lanaguage files
		loadEssentialThemeData();

		// Find all of the old MOVE topic notices that were set to expire
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE redirect_expires <= {int:redirect_expires}
				AND redirect_expires <> 0',
			array(
				'redirect_expires' => time(),
			)
		);

		while ($row = $db->fetch_row($request))
			$topics[] = $row[0];
		$db->free_result($request);

		// Zap, you're gone
		if (count($topics) > 0)
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			removeTopics($topics, false, true);
		}

		return true;
	}
}