<?php

/**
 * Class used to release email from the queue
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mail;

/**
 * Sends emails from the mail queue in to the wild
 */
class QueueMail
{
	/**
	 * Sends a group of emails from the mail queue.
	 *
	 * - Allows a batch of emails to be released every 5 to 10 seconds (based on per period limits)
	 * - If batch size is not set, will determine a size such that it sends in 1/2 the period (buffer)
	 * - Handles using a cron job and another script to send the emails ("mail_queue_use_cron" setting)
	 *
	 * @param int|bool $batch_size = false the number to send each loop
	 * @param bool $override_limit = false bypassing our limit flaf
	 * @param bool $force_send = false
	 * @return bool
	 * @package Mail
	 */
	public function reduceMailQueue($batch_size = false, $override_limit = false, $force_send = false)
	{
		global $modSettings;

		// Do we have another script to send out the queue?
		if (!empty($modSettings['mail_queue_use_cron']) && empty($force_send))
		{
			return false;
		}

		// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
		if (isset($_GET['ts']) && $_GET['ts'] != $modSettings['mail_next_send'] && empty($force_send))
		{
			return false;
		}

		require_once(SUBSDIR . '/Mail.subs.php');

		// How many emails can we send each time we are called in a period
		$batch_size = $this->setBatchSize($batch_size);

		// Set the delay / pause until the next sending
		$delay = $this->setDelay($override_limit);
		if ($delay === false)
		{
			return false;
		}

		// Make any needed adjustments based on quota remaining in this time period.
		$batch_size = $this->adjustBatchSize($override_limit, $batch_size, $delay);

		// Now we know how many we're sending, let's send them.
		list ($ids, $emails) = emailsInfo($batch_size);

		// Remove these from the queue .... Delete, delete, delete!!!
		if (!empty($ids))
		{
			deleteMailQueueItems($ids);
		}

		// Don't believe we have any left after this batch?
		if (count($ids) < $batch_size)
		{
			resetNextSendTime();
		}

		// Nothing to send?
		if (empty($ids))
		{
			return false;
		}

		// Prepare to send each email, and log that for future-proof.
		require_once(SUBSDIR . '/Maillist.subs.php');

		// We have some to send, lets send them!
		$failed_emails = array();
		$mail = new Mail();
		foreach ($emails as $email)
		{
			// Enable PBE processing if this is a maillist mailing
			if (!empty($modSettings['maillist_enabled'])
				&& $email['message_id'] !== null
				&& strpos($email['headers'], 'List-Id:') !== false)
			{
				$mail->mailList = true;
			}

			// Send it with mail() or SMTP
			$result = $mail->sendMail($email['to'], $email['subject'], $email['headers'], $email['body'], $email['message_id']);

			// Hopefully it sent?
			if (!$result)
			{
				$failed_emails[] = array(time(), $email['to'], $email['body'], $email['subject'], $email['headers'], $email['send_html'], $email['priority'], $email['private'], $email['message_id']);
			}
		}

		// Clear out the stat cache.
		trackStats();

		// Any emails that didn't send get added back to the queue
		if (!empty($failed_emails))
		{
			updateFailedQueue($failed_emails);

			return false;
		}

		// We were able to send the email, clear our failed attempts.
		if (!empty($modSettings['mail_failed_attempts']))
		{
			updateSuccessQueue();
		}

		// Had something to send...
		return true;
	}

	/**
	 * Sets the number of emails that we can release in the next pass
	 *
	 * - Uses value if set in the ACP
	 * - Determines best value based on number per min allowed and no batch size
	 * was set
	 *
	 * @param $batch_size
	 * @return int
	 */
	public function setBatchSize($batch_size)
	{
		global $modSettings;

		// How many emails can we send each time we are called in a period
		if (!$batch_size)
		{
			// Batch size has been set in the ACP, use it
			if (!empty($modSettings['mail_batch_size']))
			{
				return (int) $modSettings['mail_batch_size'];
			}

			// No per period setting or batch size, set to send 5 every 5 seconds, or 60 per minute
			if (empty($modSettings['mail_period_limit']))
			{
				return 5;
			}

			// A per period limit but no defined batch size?  Determine a batch size
			// based on the number of times we will potentially be called each minute
			// as set in updateNextSendTime()
			$delay = !empty($modSettings['mail_queue_delay'])
				? $modSettings['mail_queue_delay']
				: ($modSettings['mail_period_limit'] <= 5 ? 10 : 5);

			// Size is number per minute / number of times we will be called per minute
			$batch_size = (int) ceil($modSettings['mail_period_limit'] / ceil(60 / $delay));
			return ($batch_size === 1 && $modSettings['mail_period_limit'] > 1) ? 2 : $batch_size;
		}

		return 0;
	}

	/**
	 * Set the delay wait time until the next batch of emails can be sent
	 *
	 * @param $override_limit
	 * @return bool|int
	 */
	public function setDelay($override_limit)
	{
		global $modSettings;

		// Set the delay for the next sending
		$delay = 0;
		if (!$override_limit)
		{
			// Update next send time for our mail queue. If there was nothing to update, bail out :P
			$delay = updateNextSendTime();
			if ($delay === false)
			{
				return false;
			}

			$modSettings['mail_next_send'] = time() + $delay;
		}

		return $delay;
	}

	/**
	 * Tracks what we have sent in this time period, ensuring we do not go over our
	 * per minute quota.  If time limit is running out will adjust batch limit up
	 * to fill the allowed quota.  This is necessary as we can not rely on the scheduled
	 * task trigger period, it is based on traffic, not traffic, no trigger
	 *
	 * @param bool $override_limit
	 * @param int $batch_size
	 * @param int $delay
	 * @return int
	 */
	public function adjustBatchSize($override_limit, $batch_size, $delay)
	{
		global $modSettings;

		if (!$override_limit && !empty($modSettings['mail_period_limit']))
		{
			// See if we have quota left to send another batch_size this minute or if we have to wait
			list ($mail_time, $mail_number) = isset($modSettings['mail_recent']) ? explode('|', $modSettings['mail_recent']) : [0, 0];

			// Nothing worth noting...
			if (empty($mail_number) || $mail_time < time() - 60)
			{
				$mail_time = time();
				$mail_number = $batch_size;
			}
			// Otherwise, we may still have quota to send a few more?
			elseif ($mail_number < $modSettings['mail_period_limit'])
			{
				// If this is likely one of the last cycles for this period, then send any remaining quota
				if (($mail_time - (time() - 60)) < $delay * 2)
				{
					$batch_size = $modSettings['mail_period_limit'] - $mail_number;
				}
				// Some batch sizes may need to be adjusted to fit as we approach the end
				elseif ($mail_number + $batch_size > $modSettings['mail_period_limit'])
				{
					$batch_size = $modSettings['mail_period_limit'] - $mail_number;
				}

				$mail_number += $batch_size;
			}
			// No more I'm afraid, return!
			else
			{
				return 0;
			}

			// Reflect that we're about to send some, do it now to be safe.
			updateSettings(array('mail_recent' => $mail_time . '|' . $mail_number));
		}

		return $batch_size;
	}
}