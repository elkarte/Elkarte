<?php

/**
 * Check for old drafts and remove them
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
 * Check for old drafts and remove them
 *
 * @package ScheduledTasks
 */
class Remove_Old_Drafts implements Scheduled_Task_Interface
{
	public function run()
	{
		global $modSettings;

		$db = database();

		if (empty($modSettings['drafts_keep_days']))
			return true;

		// init
		$drafts = array();

		// We need this for language items
		loadEssentialThemeData();

		// Find all of the old drafts
		$request = $db->query('', '
			SELECT id_draft
			FROM {db_prefix}user_drafts
			WHERE poster_time <= {int:poster_time_old}',
			array(
				'poster_time_old' => time() - (86400 * $modSettings['drafts_keep_days']),
			)
		);
		while ($row = $db->fetch_row($request))
			$drafts[] = (int) $row[0];
		$db->free_result($request);

		// If we have old one, remove them
		if (count($drafts) > 0)
		{
			require_once(SUBSDIR . '/Drafts.subs.php');
			deleteDrafts($drafts, -1, false);
		}

		return true;
	}
}