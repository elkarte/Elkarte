<?php

/**
 * Schedule birthday emails.
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
 * Schedule birthday emails.
 * (aka "Happy birthday!!")
 *
 * @package ScheduledTasks
 */
class Birthdayemails implements ScheduledTaskInterface
{
	/**
	 * Happy birthday to me ! Sends out birthday greeting emails.
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function run()
	{
		global $modSettings, $txt, $txtBirthdayEmails;

		$db = database();

		// Need this in order to load the language files.
		\ElkArte\Themes\ThemeLoader::loadEssentialThemeData();

		// Going to need this to send the emails.
		require_once(SUBSDIR . '/Mail.subs.php');

		$greeting = isset($modSettings['birthday_email']) ? $modSettings['birthday_email'] : 'happy_birthday';

		// Get the month and day of today.
		$month = date('n'); // Month without leading zeros.
		$day = date('j'); // Day without leading zeros.

		// So who are the lucky ones?  Don't include those who are banned and those who don't want them.
		$birthdays = array();
		$db->fetchQuery('
			SELECT 
				id_member, real_name, lngfile, email_address
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
		)->fetch_callback(
			function ($row) use (&$birthdays) {
				// Group them by languages.
				if (!isset($birthdays[$row['lngfile']]))
				{
					$birthdays[$row['lngfile']] = array();
				}

				$birthdays[$row['lngfile']][$row['id_member']] = array(
					'name' => $row['real_name'],
					'email' => $row['email_address']
				);
			}
		);

		// Send out the greetings!
		foreach ($birthdays as $lang => $recps)
		{
			// We need to do some shuffling to make this work properly.
			\ElkArte\Themes\ThemeLoader::loadLanguageFile('EmailTemplates', $lang);
			$txt['happy_birthday_subject'] = $txtBirthdayEmails[$greeting . '_subject'];
			$txt['happy_birthday_body'] = $txtBirthdayEmails[$greeting . '_body'];

			foreach ($recps as $recp)
			{
				$replacements = array(
					'REALNAME' => $recp['name'],
				);

				$emaildata = loadEmailTemplate('happy_birthday', $replacements, $lang, false);

				sendmail($recp['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 4);

				// Try to stop a timeout, this would be bad...
				detectServer()->setTimeLimit(300);
			}
		}

		// Flush the mail queue, just in case.
		AddMailQueue(true);

		return true;
	}
}
