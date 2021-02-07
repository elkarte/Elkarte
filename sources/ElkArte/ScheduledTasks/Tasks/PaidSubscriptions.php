<?php

/**
 * Perform the standard checks on expiring/near expiring subscriptions
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

/**
 * Class Paid_Subscriptions - Perform the standard checks on expiring/near expiring subscriptions:
 *
 * - Remove expired subscriptions
 * - Notify of subscriptions about to expire
 *
 * @package ElkArte\ScheduledTasks\Tasks
 */
class PaidSubscriptions implements ScheduledTaskInterface
{
	/**
	 * Removes expired and reminds members who have ones close to expiration
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function run()
	{
		global $modSettings, $language;

		$db = database();

		// Start off by checking for removed subscriptions.
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		$db->fetchQuery('
			SELECT 
				id_subscribe, id_member
			FROM {db_prefix}log_subscribed
			WHERE status = {int:is_active}
				AND end_time < {int:time_now}',
			array(
				'is_active' => 1,
				'time_now' => time(),
			)
		)->fetch_callback(
			function ($row) {
				removeSubscription($row['id_subscribe'], $row['id_member']);
			}
		);

		// Get all those about to expire that have not had a reminder sent.
		$subs_reminded = array();
		$db->fetchQuery('
			SELECT 
				ls.id_sublog, ls.end_time,
				m.id_member, m.member_name, m.email_address, m.lngfile, 
				s.name
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
		)->fetch_callback(
			function ($row) use (&$subs_reminded, $modSettings, $language) {
				// If this is the first one load the important bits.
				if (empty($subs_reminded))
				{
					// Need the below for \ElkArte\Themes\ThemeLoader::loadLanguageFile to work!
					require_once(SUBSDIR . '/Mail.subs.php');
					\ElkArte\Themes\ThemeLoader::loadEssentialThemeData();
				}

				$subs_reminded[] = $row['id_sublog'];

				$replacements = array(
					'PROFILE_LINK' => getUrl('profile', ['action' => 'profile', 'area' => 'subscriptions', 'name' => $row['member_name'], 'u' => $row['id_member']]),
					'REALNAME' => $row['member_name'],
					'SUBSCRIPTION' => $row['name'],
					'END_DATE' => strip_tags(standardTime($row['end_time'])),
				);

				$emaildata = loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

				// Send the actual email.
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
			}
		);

		// Mark the reminder as sent.
		if (!empty($subs_reminded))
		{
			$db->query('', '
				UPDATE {db_prefix}log_subscribed
				SET reminder_sent = {int:reminder_sent}
				WHERE id_sublog IN ({array_int:subscription_list})',
				array(
					'subscription_list' => $subs_reminded,
					'reminder_sent' => 1,
				)
			);
		}

		return true;
	}
}
