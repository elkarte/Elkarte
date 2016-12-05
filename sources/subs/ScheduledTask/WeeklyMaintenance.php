<?php

/**
 * Weekly maintenance tasks
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

/**
 * Class Weekly_Maintenance - Weekly maintenance tasks
 *
 * What it does:
 * - remove empty or temporary settings
 * - prune logs
 * - obsolete paid subscriptions
 * - clear sessions table
 *
 * @package ScheduledTasks
 */
class Weekly_Maintenance implements Scheduled_Task_Interface
{
	/**
	 * Runs the weekly maintenance tasks to keep the forum running smooth as silk
	 *
	 * @return bool
	 */
	public function run()
	{
		global $modSettings;

		$db = database();

		// Delete some settings that needn't be set if they are otherwise empty.
		$emptySettings = array(
			'warning_mute', 'warning_moderate', 'warning_watch', 'warning_show', 'disableCustomPerPage', 'spider_mode', 'spider_group',
			'paid_currency_code', 'paid_currency_symbol', 'paid_email_to', 'paid_email', 'paid_enabled', 'paypal_email',
			'search_enable_captcha', 'search_floodcontrol_time', 'show_spider_online',
		);

		$db->query('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})
				AND (value = {string:zero_value} OR value = {string:blank_value})',
			array(
				'zero_value' => '0',
				'blank_value' => '',
				'setting_list' => $emptySettings,
			)
		);

		// Some settings we never want to keep - they are just there for temporary purposes.
		$deleteAnywaySettings = array(
			'attachment_full_notified',
		);

		removeSettings($deleteAnywaySettings);

		// Ok should we prune the logs?
		if (!empty($modSettings['pruningOptions']))
		{
			if (!empty($modSettings['pruningOptions']) && strpos($modSettings['pruningOptions'], ',') !== false)
				list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneSpiderHitLog']) = explode(',', $modSettings['pruningOptions']);

			if (!empty($modSettings['pruneErrorLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneErrorLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_errors
					WHERE log_time < {int:log_time}',
					array(
						'log_time' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneModLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneModLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_actions
					WHERE log_time < {int:log_time}
						AND id_log = {int:moderation_log}',
					array(
						'log_time' => $t,
						'moderation_log' => 1,
					)
				);
			}

			if (!empty($modSettings['pruneBanLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneBanLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_banned
					WHERE log_time < {int:log_time}',
					array(
						'log_time' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneBadbehaviorLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneBadbehaviorLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_badbehavior
					WHERE log_time < {int:log_time}',
					array(
						'log_time' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneReportLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneReportLog'] * 86400;

				// This one is more complex then the other logs.  First we need to figure out which reports are too old.
				$reports = array();
				$result = $db->query('', '
					SELECT id_report
					FROM {db_prefix}log_reported
					WHERE time_started < {int:time_started}
						AND closed = {int:closed}',
					array(
						'time_started' => $t,
						'closed' => 1,
					)
				);
				while ($row = $db->fetch_row($result))
					$reports[] = $row[0];
				$db->free_result($result);

				if (!empty($reports))
				{
					// Now delete the reports...
					$db->query('', '
						DELETE FROM {db_prefix}log_reported
						WHERE id_report IN ({array_int:report_list})',
						array(
							'report_list' => $reports,
						)
					);
					// And delete the comments for those reports...
					$db->query('', '
						DELETE FROM {db_prefix}log_reported_comments
						WHERE id_report IN ({array_int:report_list})',
						array(
							'report_list' => $reports,
						)
					);
				}
			}

			if (!empty($modSettings['pruneScheduledTaskLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneScheduledTaskLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_scheduled_tasks
					WHERE time_run < {int:time_run}',
					array(
						'time_run' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneSpiderHitLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneSpiderHitLog'] * 86400;

				require_once(SUBSDIR . '/SearchEngines.subs.php');
				removeSpiderOldLogs($t);
			}
		}

		// Get rid of any paid subscriptions that were never actioned.
		$db->query('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE end_time = {int:no_end_time}
				AND status = {int:not_active}
				AND start_time < {int:start_time}
				AND payments_pending < {int:payments_pending}',
			array(
				'no_end_time' => 0,
				'not_active' => 0,
				'start_time' => time() - 60,
				'payments_pending' => 1,
			)
		);

		// Some OS's don't seem to clean out their sessions.
		$db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			array(
				'last_update' => time() - 86400,
			)
		);

		return true;
	}
}