<?php

/**
 * Check for old drafts and remove them
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
 * Class Remove_Old_Drafts - Check for old drafts and remove them
 *
 * @package ScheduledTasks
 */
class RemoveOldDrafts implements ScheduledTaskInterface
{
	/**
	 * Scheduled task for removing those old and abandoned drafts
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function run()
	{
		global $modSettings;

		$db = database();

		if (empty($modSettings['drafts_keep_days']))
		{
			return true;
		}

		// init
		$drafts = array();

		// We need this for language items
		ThemeLoader::loadEssentialThemeData();

		// Find all of the old drafts
		$request = $db->query('', '
			SELECT 
				id_draft
			FROM {db_prefix}user_drafts
			WHERE poster_time <= {int:poster_time_old}',
			array(
				'poster_time_old' => time() - (86400 * $modSettings['drafts_keep_days']),
			)
		);
		while (($row = $request->fetch_row()))
		{
			$drafts[] = (int) $row[0];
		}
		$request->free_result();

		// If we have old one, remove them
		if (count($drafts) > 0)
		{
			require_once(SUBSDIR . '/Drafts.subs.php');
			deleteDrafts($drafts, -1, false);
		}

		return true;
	}
}
