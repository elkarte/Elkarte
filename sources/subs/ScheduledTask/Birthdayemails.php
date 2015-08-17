<?php

/**
 * Schedule birthday emails.
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
 * Schedule birthday emails.
 * (aka "Happy birthday!!")
 *
 * @package ScheduledTasks
 */
class Birthdayemails implements Scheduled_Task_Interface
{
	public function run()
	{
		global $modSettings, $txt, $txtBirthdayEmails;

		$db = database();

		// Need this in order to load the language files.
		loadEssentialThemeData();

		// Going to need this to send the emails.
		require_once(SUBSDIR . '/Mail.subs.php');

		$greeting = isset($modSettings['birthday_email']) ? $modSettings['birthday_email'] : 'happy_birthday';

		// Get the month and day of today.
		$month = date('n'); // Month without leading zeros.
		$day = date('j'); // Day without leading zeros.

		// So who are the lucky ones?  Don't include those who are banned and those who don't want them.
		$result = $db->query('', '
			SELECT id_member, real_name, lngfile, email_address
			FROM {db_prefix}members
			WHERE is_activated < 10
				AND MONTH(birthdate) = {int:month}
				AND DAYOFMONTH(birthdate) = {int:day}
				AND notify_announcements = {int:notify_announcements}
				AND YEAR(birthdate) > {int:year}',
			array(
				'notify_announcements' => 1,
				'year' => 1,
				'month' => $month,
				'day' => $day,
			)
		);

		// Group them by languages.
		$birthdays = array();
		while ($row = $db->fetch_assoc($result))
		{
			if (!isset($birthdays[$row['lngfile']]))
				$birthdays[$row['lngfile']] = array();
			$birthdays[$row['lngfile']][$row['id_member']] = array(
				'name' => $row['real_name'],
				'email' => $row['email_address']
			);
		}
		$db->free_result($result);

		// Send out the greetings!
		foreach ($birthdays as $lang => $recps)
		{
			// We need to do some shuffling to make this work properly.
			loadLanguage('EmailTemplates', $lang);
			$txt['emails']['happy_birthday']['subject'] = $txtBirthdayEmails[$greeting . '_subject'];
			$txt['emails']['happy_birthday']['body'] = $txtBirthdayEmails[$greeting . '_body'];

			foreach ($recps as $recp)
			{
				$replacements = array(
					'REALNAME' => $recp['name'],
				);

				$emaildata = loadEmailTemplate('happy_birthday', $replacements, $lang, false);

				sendmail($recp['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 4);

				// Try to stop a timeout, this would be bad...
				setTimeLimit(300);
			}
		}

		// Flush the mail queue, just in case.
		AddMailQueue(true);

		return true;
	}
}