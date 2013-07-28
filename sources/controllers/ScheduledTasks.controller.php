<?php

/**
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
 * @version 1.0 Alpha
 *
 * This file is automatically called and handles all manner of scheduled things.
 *
 */

if (!defined('ELK'))
	die('No access...');

class ScheduledTasks_Controller
{
	/**
	 * This method works out what to run:
	 *  - it checks if it's time for the next tasks
	 *  - runs next tasks
	 *  - update the database for the next round
	 */
	function action_autotask()
	{
		global $time_start;

		$db = database();

		// Include the ScheduledTasks subs and class.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');
		require_once(SUBSDIR . '/ScheduledTasks.class.php');

		// Special case for doing the mail queue.
		if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
			ReduceMailQueue();
		else
		{
			call_integration_hook('integrate_autotask_include');

			// Select the next task to do.
			$request = $db->query('', '
				SELECT id_task, task, next_time, time_offset, time_regularity, time_unit
				FROM {db_prefix}scheduled_tasks
				WHERE disabled = {int:not_disabled}
					AND next_time <= {int:current_time}
				ORDER BY next_time ASC
				LIMIT 1',
				array(
					'not_disabled' => 0,
					'current_time' => time(),
				)
			);
			if ($db->num_rows($request) != 0)
			{
				// The two important things really...
				$row = $db->fetch_assoc($request);

				// When should this next be run?
				$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

				// How long in seconds it the gap?
				$duration = $row['time_regularity'];
				if ($row['time_unit'] == 'm')
					$duration *= 60;
				elseif ($row['time_unit'] == 'h')
					$duration *= 3600;
				elseif ($row['time_unit'] == 'd')
					$duration *= 86400;
				elseif ($row['time_unit'] == 'w')
					$duration *= 604800;

				// If we were really late running this task actually skip the next one.
				if (time() + ($duration / 2) > $next_time)
					$next_time += $duration;

				// Update it now, so no others run this!
				$db->query('', '
					UPDATE {db_prefix}scheduled_tasks
					SET next_time = {int:next_time}
					WHERE id_task = {int:id_task}
						AND next_time = {int:current_next_time}',
					array(
						'next_time' => $next_time,
						'id_task' => $row['id_task'],
						'current_next_time' => $row['next_time'],
					)
				);
				$affected_rows = $db->affected_rows();

				// The method must exist in ScheduledTask class, or we are wasting our time.
				// Do also some timestamp checking,
				// and do this only if we updated it before.
				$task = new ScheduledTask();
				if (method_exists($task, 'scheduled_' . $row['task']) && (!isset($_GET['ts']) || $_GET['ts'] == $row['next_time']) && $affected_rows)
				{
					ignore_user_abort(true);

					// Do the task...
					$completed = $task->{'scheduled_' . $row['task']}();

					// Log that we did it ;)
					if ($completed)
					{
						$total_time = round(microtime(true) - $time_start, 3);
						logTask($row['id_task'], (int)$total_time);
					}
				}
			}
			$db->free_result($request);

			// Get the next timestamp right.
			$request = $db->query('', '
				SELECT next_time
				FROM {db_prefix}scheduled_tasks
				WHERE disabled = {int:not_disabled}
				ORDER BY next_time ASC
				LIMIT 1',
				array(
					'not_disabled' => 0,
				)
			);
			// No new task scheduled yet?
			if ($db->num_rows($request) === 0)
				$nextEvent = time() + 86400;
			else
				list ($nextEvent) = $db->fetch_row($request);
			$db->free_result($request);

			updateSettings(array('next_task_time' => $nextEvent));
		}

		// Shall we return?
		if (!isset($_GET['scheduled']))
			return true;

		// Finally, send some stuff...
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}
}

/**
 * Send a group of emails from the mail queue.
 *
 * @param mixed $number = false the number to send each loop through
 * @param boolean $override_limit = false bypassing our limit flaf
 * @param boolean $force_send = false
 * @return boolean
 */
function ReduceMailQueue($number = false, $override_limit = false, $force_send = false)
{
	global $modSettings, $context, $webmaster_email, $scripturl;

	$db = database();

	// Are we intending another script to be sending out the queue?
	if (!empty($modSettings['mail_queue_use_cron']) && empty($force_send))
		return false;

	// By default send 5 at once.
	if (!$number)
		$number = empty($modSettings['mail_quantity']) ? 5 : $modSettings['mail_quantity'];

	// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
	if (isset($_GET['ts']) && $_GET['ts'] != $modSettings['mail_next_send'] && empty($force_send))
		return false;

	// By default move the next sending on by 10 seconds, and require an affected row.
	if (!$override_limit)
	{
		// Set our delay based on our per min limit (mail_limit)
		$delay = !empty($modSettings['mail_queue_delay']) ? $modSettings['mail_queue_delay'] : (!empty($modSettings['mail_limit']) && $modSettings['mail_limit'] < 5 ? 10 : 5);

		$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:next_mail_send}
			WHERE variable = {string:mail_next_send}
				AND value = {string:last_send}',
			array(
				'next_mail_send' => time() + $delay,
				'mail_next_send' => 'mail_next_send',
				'last_send' => $modSettings['mail_next_send'],
			)
		);
		if ($db->affected_rows() == 0)
			return false;
		$modSettings['mail_next_send'] = time() + $delay;
	}

	// If we're not overriding how many are we allow to send?
	if (!$override_limit && !empty($modSettings['mail_limit']))
	{
		// See if we have quota left to send another group this minute or if we have to wait
		list ($mail_time, $mail_number) = @explode('|', $modSettings['mail_recent']);

		// Nothing worth noting...
		if (empty($mail_number) || $mail_time < time() - 60)
		{
			$mail_time = time();
			$mail_number = $number;
		}
		// Otherwise we have a few more we can spend?
		elseif ($mail_number < $modSettings['mail_limit'])
		{
			$mail_number += $number;
		}
		// No more I'm afraid, return!
		else
			return false;

		// Reflect that we're about to send some, do it now to be safe.
		updateSettings(array('mail_recent' => $mail_time . '|' . $mail_number));
	}

	// Now we know how many we're sending, let's send them.
	$request = $db->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_mail, recipient, body, subject, headers, send_html, time_sent, priority, message_id
		FROM {db_prefix}mail_queue
		ORDER BY priority ASC, id_mail ASC
		LIMIT ' . $number,
		array(
		)
	);
	$ids = array();
	$emails = array();
	while ($row = $db->fetch_assoc($request))
	{
		// We want to delete these from the database ASAP, so just get the data and go.
		$ids[] = $row['id_mail'];
		$emails[] = array(
			'to' => $row['recipient'],
			'body' => $row['body'],
			'subject' => $row['subject'],
			'headers' => $row['headers'],
			'send_html' => $row['send_html'],
			'time_sent' => $row['time_sent'],
			'priority' => $row['priority'],
			'message_id' => $row['message_id'],
		);
	}
	$db->free_result($request);

	// Delete, delete, delete!!!
	if (!empty($ids))
		$db->query('', '
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_list})',
			array(
				'mail_list' => $ids,
			)
		);

	// Don't believe we have any left?
	if (count($ids) < $number)
	{
		// Only update the setting if no-one else has beaten us to it.
		$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:no_send}
			WHERE variable = {string:mail_next_send}
				AND value = {string:last_mail_send}',
			array(
				'no_send' => '0',
				'mail_next_send' => 'mail_next_send',
				'last_mail_send' => $modSettings['mail_next_send'],
			)
		);
	}

	if (empty($ids))
		return false;

	// Send each email, yea!
	require_once(SUBSDIR . '/Mail.subs.php');
	$sent = array();
	$failed_emails = array();

	// Use sendmail or SMTP
	$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

	// Line breaks need to be \r\n only in windows or for SMTP.
	$line_break = !empty($context['server']['is_windows']) || !$use_sendmail ? "\r\n" : "\n";

	foreach ($emails as $key => $email)
	{
		// Use the right mail resource
		if ($use_sendmail)
		{
			$email['subject'] = strtr($email['subject'], array("\r" => '', "\n" => ''));
			if (!empty($modSettings['mail_strip_carriage']))
			{
				$email['body'] = strtr($email['body'], array("\r" => ''));
				$email['headers'] = strtr($email['headers'], array("\r" => ''));
			}
			$need_break = substr($email['headers'], -1) === "\n" || substr($email['headers'], -1) === "\r" ? false : true;

			// Create our unique reply to email header, priority 3 and below only (4 = digest, 5 = newsletter)
			$unq_id = '';
			$unq_head = '';
			if (!empty($modSettings['maillist_enabled']) && $email['message_id'] !== null && $email['priority'] < 4 && empty($modSettings['mail_no_message_id']))
			{
				$unq_head = md5($scripturl . microtime() . rand()) . '-' . $email['message_id'];
				$encoded_unq_head = base64_encode($line_break . $line_break . '[' . $unq_head . ']' . $line_break);
				$unq_id = $need_break ? $line_break : '' . 'Message-ID: <' . $unq_head . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . ">";
				$email['body'] = mail_insert_key($email['body'], $unq_head, $encoded_unq_head, $line_break);
			}
			elseif ($email['message_id'] !== null && empty($modSettings['mail_no_message_id']))
				$unq_id = $need_break ? $line_break : '' . 'Message-ID: <' . md5($scripturl . microtime()) . '-' . $email['message_id'] . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>';

			// No point logging a specific error here, as we have no language. PHP error is helpful anyway...
			$result = mail(strtr($email['to'], array("\r" => '', "\n" => '')), $email['subject'], $email['body'], $email['headers'] . $unq_id);

			// if it sent, keep a record so we can save it in our allowed to reply log
			if (!empty($unq_head) && $result)
				$sent[] = array($unq_head, time(), $email['to']);

			// track total emails sent
			if ($result && !empty($modSettings['trackStats']))
				trackStats(array('email' => '+'));

			// Try to stop a timeout, this would be bad...
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
		else
			$result = smtp_mail(array($email['to']), $email['subject'], $email['body'], $email['send_html'] ? $email['headers'] : 'Mime-Version: 1.0' . "\r\n" . $email['headers'], $email['message_id']);

		// Hopefully it sent?
		if (!$result)
			$failed_emails[] = array(time(), $email['to'], $email['body'], $email['subject'], $email['headers'], $email['send_html'], $email['priority'], $email['message_id']);
	}

	// Clear out the stat cache.
	trackStats();

	// Log each email.
	if (!empty($sent))
	{
		$db->insert('ignore',
			'{db_prefix}postby_emails',
			array(
				'id_email' => 'int', 'time_sent' => 'string', 'email_to' => 'string'
			),
			$sent,
			array('id_email')
		);
	}

	// Any emails that didn't send?
	if (!empty($failed_emails))
	{
		// Update the failed attempts check.
		$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('mail_failed_attempts', empty($modSettings['mail_failed_attempts']) ? 1 : ++$modSettings['mail_failed_attempts']),
			array('variable')
		);

		// If we have failed to many times, tell mail to wait a bit and try again.
		if ($modSettings['mail_failed_attempts'] > 5)
			$db->query('', '
				UPDATE {db_prefix}settings
				SET value = {string:next_mail_send}
				WHERE variable = {string:mail_next_send}
					AND value = {string:last_send}',
				array(
					'next_mail_send' => time() + 60,
					'mail_next_send' => 'mail_next_send',
					'last_send' => $modSettings['mail_next_send'],
				)
			);

		// Add our email back to the queue, manually.
		$db->insert('insert',
			'{db_prefix}mail_queue',
			array('time_sent' => 'int', 'recipient' => 'string', 'body' => 'string', 'subject' => 'string', 'headers' => 'string', 'send_html' => 'int', 'priority' => 'int', 'message_id' => 'int'),
			$failed_emails,
			array('id_mail')
		);

		return false;
	}
	// We where unable to send the email, clear our failed attempts.
	elseif (!empty($modSettings['mail_failed_attempts']))
		$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:zero}
			WHERE variable = {string:mail_failed_attempts}',
			array(
				'zero' => '0',
				'mail_failed_attempts' => 'mail_failed_attempts',
			)
		);

	// Had something to send...
	return true;
}