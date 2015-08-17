<?php

/**
 * Perform the standard checks on expiring/near expiring subscriptions
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
 * Perform the standard checks on expiring/near expiring subscriptions:
 *
 * - remove expired subscriptions
 * - notify of subscriptions about to expire
 *
 * @package ScheduledTasks
 */
class Paid_Subscriptions implements Scheduled_Task_Interface
{
	public function run()
	{
		global $scripturl, $modSettings, $language;

		$db = database();

		// Start off by checking for removed subscriptions.
		$request = $db->query('', '
			SELECT id_subscribe, id_member
			FROM {db_prefix}log_subscribed
			WHERE status = {int:is_active}
				AND end_time < {int:time_now}',
			array(
				'is_active' => 1,
				'time_now' => time(),
			)
		);
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		while ($row = $db->fetch_assoc($request))
		{
			removeSubscription($row['id_subscribe'], $row['id_member']);
		}
		$db->free_result($request);

		// Get all those about to expire that have not had a reminder sent.
		$request = $db->query('', '
			SELECT ls.id_sublog, m.id_member, m.member_name, m.email_address, m.lngfile, s.name, ls.end_time
			FROM {db_prefix}log_subscribed AS ls
				INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
				INNER JOIN {db_prefix}members AS m ON (m.id_member = ls.id_member)
			WHERE ls.status = {int:is_active}
				AND ls.reminder_sent = {int:reminder_sent}
				AND s.reminder > {int:reminder_wanted}
				AND ls.end_time < ({int:time_now} + s.reminder * 86400)',
			array(
				'is_active' => 1,
				'reminder_sent' => 0,
				'reminder_wanted' => 0,
				'time_now' => time(),
			)
		);
		$subs_reminded = array();
		while ($row = $db->fetch_assoc($request))
		{
			// If this is the first one load the important bits.
			if (empty($subs_reminded))
			{
				require_once(SUBSDIR . '/Mail.subs.php');
				// Need the below for loadLanguage to work!
				loadEssentialThemeData();
			}

			$subs_reminded[] = $row['id_sublog'];

			$replacements = array(
				'PROFILE_LINK' => $scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
				'REALNAME' => $row['member_name'],
				'SUBSCRIPTION' => $row['name'],
				'END_DATE' => strip_tags(standardTime($row['end_time'])),
			);

			$emaildata = loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// Send the actual email.
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
		}
		$db->free_result($request);

		// Mark the reminder as sent.
		if (!empty($subs_reminded))
			$db->query('', '
				UPDATE {db_prefix}log_subscribed
				SET reminder_sent = {int:reminder_sent}
				WHERE id_sublog IN ({array_int:subscription_list})',
				array(
					'subscription_list' => $subs_reminded,
					'reminder_sent' => 1,
				)
			);

		return true;
	}
}