<?php

/**
 * Re-syncs if a user can access a mention.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

/**
 * Re-syncs if a user can access a mention,
 *
 * - for example if they loose or gain access to a board, this will correct
 * the viewing of the mention table.  Since this can be a large job it is run
 * as a scheduled immediate task
 *
 * @package ScheduledTasks
 */
class User_Access_Mentions implements Scheduled_Task_Interface
{
	/**
	 * Validates / Updates user mention access
	 * 
	 * @return bool
	 */
	public function run()
	{
		global $modSettings;

		$db = database();
		$user_access_mentions = \Util::unserialize($modSettings['user_access_mentions']);

		// This should be set only because of an immediate scheduled task, so higher priority
		if (!empty($user_access_mentions))
		{
			foreach ($user_access_mentions as $member => $begin)
			{
				// Just to stay on the safe side...
				if (empty($member))
					continue;

				// Just a touch of needy
				require_once(SUBSDIR . '/Boards.subs.php');
				require_once(SUBSDIR . '/Mentions.subs.php');
				require_once(SUBSDIR . '/Members.subs.php');

				$user_see_board = memberQuerySeeBoard($member);
				$limit = 100;

				// We need to repeat this twice: once to find the boards the user can access,
				// once for those he cannot access
				foreach (array('can', 'cannot') as $can)
				{
					// Let's always start from the begin
					$start = $begin;

					while (true)
					{
						// Find all the mentions that this user can or cannot see
						$request = $db->query('', '
							SELECT 
								mnt.id_mention, m.id_board
							FROM {db_prefix}log_mentions as mnt
								LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = mnt.id_target)
								LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
							WHERE mnt.id_member = {int:current_member}
								AND mnt.mention_type IN ({array_string:mention_types})
								AND {raw:user_see_board}
							LIMIT {int:start}, {int:limit}',
							array(
								'current_member' => $member,
								'mention_types' => array('mentionmem', 'likemsg', 'rlikemsg'),
								'user_see_board' => ($can == 'can' ? '' : 'NOT ') . $user_see_board,
								'start' => $start,
								'limit' => $limit,
							)
						);
						$mentions = array();
						$remove = array();
						while ($row = $db->fetch_assoc($request))
						{
							if (empty($row['id_board']))
								$remove[] = $row['id_mention'];
							else
								$mentions[] = $row['id_mention'];
						}
						$db->free_result($request);

						if (!empty($remove))
						{
							removeMentions($remove);
						}

						// If we found something toggle them and increment the start for the next round
						if (!empty($mentions))
							toggleMentionsAccessibility($mentions, $can == 'can');
						// Otherwise it means we have finished with this access level for this member
						else
							break;

						// Next batch
						$start += $limit;
					}
				}

				// Drop the member
				unset($user_access_mentions[$member]);

				// And save everything for the next run
				updateSettings(array('user_access_mentions' => serialize($user_access_mentions)));

				// Count helps keep things correct
				countUserMentions(false, '', $member);

				// Run this only once for each user, it may be quite heavy, let's split up the load
				break;
			}

			// If there are no more users, scheduleTaskImmediate can be stopped
			if (empty($user_access_mentions))
				removeScheduleTaskImmediate('user_access_mentions', false);

			return true;
		}
		else
		{
			// Checks 10 users at a time, the scheduled task is set to run once per hour, so 240 users a day
			// @todo <= I know you like it Spuds! :P It may be necessary to set it to something higher.
			$limit = 10;
			$current_check = !empty($modSettings['mentions_member_check']) ? $modSettings['mentions_member_check'] : 0;

			require_once(SUBSDIR . '/Members.subs.php');
			require_once(SUBSDIR . '/Mentions.subs.php');

			// Grab users with mentions
			$request = $db->query('', '
				SELECT COUNT(DISTINCT(id_member))
				FROM {db_prefix}log_mentions
				WHERE id_member > {int:last_id_member}
					AND mention_type IN ({array_string:mention_types})',
				array(
					'last_id_member' => $current_check,
					'mention_types' => array('mentionmem', 'likemsg', 'rlikemsg'),
				)
			);
			list ($remaining) = $db->fetch_row($request);
			$db->free_result($request);

			if ($remaining == 0)
				$current_check = 0;

			// Grab users with mentions
			$request = $db->query('', '
				SELECT 
					DISTINCT(id_member) as id_member
				FROM {db_prefix}log_mentions
				WHERE id_member > {int:last_id_member}
					AND mention_type IN ({array_string:mention_types})
				LIMIT {int:limit}',
				array(
					'last_id_member' => $current_check,
					'mention_types' => array('mentionmem', 'likemsg', 'rlikemsg'),
					'limit' => $limit,
				)
			);

			// Remember where we are
			updateSettings(array('mentions_member_check' => $current_check + $limit));

			while ($row = $db->fetch_assoc($request))
			{
				// Rebuild 'query_see_board', a lot of code duplication... :(
				$user_see_board = memberQuerySeeBoard($row['id_member']);

				// Find out if this user cannot see something that was supposed to be able to see
				$request2 = $db->query('', '
					SELECT 
						mnt.id_mention
					FROM {db_prefix}log_mentions as mnt
						LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = mnt.id_target)
						LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					WHERE mnt.id_member = {int:current_member}
						AND mnt.mention_type IN ({array_string:mention_types})
						AND {raw:user_see_board}
						AND mnt.is_accessible = 0
					LIMIT 1',
					array(
						'current_member' => $row['id_member'],
						'mention_types' => array('mentionmem', 'likemsg', 'rlikemsg'),
						'user_see_board' => 'NOT ' . $user_see_board,
					)
				);
				// One row of results is enough: scheduleTaskImmediate!
				if ($db->num_rows($request2) == 1)
				{
					if (!empty($modSettings['user_access_mentions']))
						$modSettings['user_access_mentions'] = \Util::unserialize($modSettings['user_access_mentions']);
					else
						$modSettings['user_access_mentions'] = array();

					// But if the member is already on the list, let's skip it
					if (!isset($modSettings['user_access_mentions'][$row['id_member']]))
					{
						$modSettings['user_access_mentions'][$row['id_member']] = 0;
						updateSettings(array('user_access_mentions' => serialize(array_unique($modSettings['user_access_mentions']))));
						scheduleTaskImmediate('user_access_mentions');
					}
				}
				$db->free_result($request2);
			}
			$db->free_result($request);

			return true;
		}
	}
}