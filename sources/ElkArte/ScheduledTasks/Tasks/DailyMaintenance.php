<?php

/**
 * This class does daily cleaning up.
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

use ElkArte\Cache\Cache;

/**
 * Class Daily_Maintenance - This function does daily cleaning up:
 *
 * What it does:
 *
 * - decrements warning levels if it's enabled
 * - consolidate spider statistics
 * - fix MySQL version
 * - remove obsolete login history logs
 *
 * @package ScheduledTasks
 */
class DailyMaintenance implements ScheduledTaskInterface
{
	/**
	 * Our only method, runs the show
	 *
	 * @return bool
	 */
	public function run()
	{
		global $modSettings;

		$db = database();

		// First clean out the cache.
		Cache::instance()->clean('data');

		// If warning decrement is enabled and we have people who have not had a new warning in 24 hours, lower their warning level.
		[, , $modSettings['warning_decrement']] = explode(',', $modSettings['warning_settings']);
		if ($modSettings['warning_decrement'] !== '' && $modSettings['warning_decrement'] !== '0')
		{
			// Find every member who has a warning level...
			$members = array();
			$db->fetchQuery('
				SELECT 
					id_member, warning
				FROM {db_prefix}members
				WHERE warning > {int:no_warning}',
				array(
					'no_warning' => 0,
				)
			)->fetch_callback(
				static function ($row) use (&$members) {
					$members[$row['id_member']] = $row['warning'];
				}
			);

			// Have some members to check?
			if (!empty($members))
			{
				// Find out when they were last warned.
				$member_changes = array();
				$db->fetchQuery('
					SELECT 
						id_recipient, MAX(log_time) AS last_warning
					FROM {db_prefix}log_comments
					WHERE id_recipient IN ({array_int:member_list})
						AND comment_type = {string:warning}
					GROUP BY id_recipient',
					array(
						'member_list' => array_keys($members),
						'warning' => 'warning',
					)
				)->fetch_callback(
					static function ($row) use (&$member_changes, $modSettings, $members) {
						// More than 24 hours ago?
						if ($row['last_warning'] <= time() - 86400)
						{
							$member_changes[] = array(
								'id' => $row['id_recipient'],
								'warning' => $members[$row['id_recipient']] >= $modSettings['warning_decrement'] ? $members[$row['id_recipient']] - $modSettings['warning_decrement'] : 0,
							);
						}
					}
				);

				// Have some members to change?
				if (!empty($member_changes))
				{
					require_once(SUBSDIR . '/Members.subs.php');
					foreach ($member_changes as $change)
					{
						updateMemberData($change['id'], array('warning' => $change['warning']));
					}
				}
			}
		}

		// Do any spider stuff.
		if (!empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
		{
			// We'll need this.
			require_once(SUBSDIR . '/SearchEngines.subs.php');
			consolidateSpiderStats();
		}

		// Clean up some old login history information.
		$db->query('', '
			DELETE FROM {db_prefix}member_logins
			WHERE time > {int:oldLogins}',
			array(
				'oldLogins' => empty($modSettings['loginHistoryDays']) ? 108000 : 60 * 60 * $modSettings['loginHistoryDays'],
			));

		// Log we've done it...
		return true;
	}
}
