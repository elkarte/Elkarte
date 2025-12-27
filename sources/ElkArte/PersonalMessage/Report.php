<?php

/**
 * This file deals with the report actions related to personal messages.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\PersonalMessage;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Languages\Loader;

/**
 * Class Report
 * It allows reporting personal messages
 *
 * @package ElkArte\PersonalMessage
 */
class Report extends AbstractController
{
	/**
	 * Default action for the class
	 */
	public function action_index()
	{
		$this->action_report();
	}

	/**
	 * Allows the user to report a personal message to an administrator.
	 *
	 * What it does:
	 *
	 * - In the first instance requires that the ID of the message to report is passed through $_GET.
	 * - It allows the user to report to either a particular administrator - or the whole admin team.
	 * - It will forward on a copy of the original message without allowing the reporter to make changes.
	 *
	 * @uses report_message sub-template.
	 */
	public function action_report(): void
	{
		global $txt, $context;

		// Check that this feature is even enabled and we have a PM!
		$pmsg = $this->_validateRequest();

		$context['pm_id'] = $pmsg;
		$context['page_title'] = $txt['pm_report_title'];
		$context['sub_template'] = 'report_message';

		// If we're here, just send the user to the template, with a few useful context bits.
		if ($this->_req->hasPost('report'))
		{
			// Check the session before proceeding any further!
			checkSession();

			// Going to need these.
			require_once(SUBSDIR . '/PersonalMessage.subs.php');
			require_once(SUBSDIR . '/Messages.subs.php');
			require_once(SUBSDIR . '/Members.subs.php');

			$reason = $this->_getReportReason();

			// First, load up the message they want to file a complaint against and verify it actually went to them!
			$pmData = $this->_loadReportedMessage($pmsg);

			// Record this report for the admins to see.
			$this->_recordReport($pmsg, $pmData, $reason);

			// Notify the admins that there is a report.
			$this->_sendReports($pmData, $reason);

			// Leave them with a template.
			$context['sub_template'] = 'report_message_complete';
		}
	}

	/**
	 * Check that this feature is even enabled and we have a valid PM!
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function _validateRequest(): int
	{
		global $modSettings;

		$pmsg = $this->_req->getPost('pmsg', 'intval', $this->_req->getQuery('pmsg', 'intval', 0));

		if (empty($modSettings['enableReportPM']) || empty($pmsg))
		{
			throw new Exception('no_access', false);
		}

		if (!isAccessiblePM($pmsg, 'inbox'))
		{
			throw new Exception('no_access', false);
		}

		return $pmsg;
	}

	/**
	 * Get and validate the report reason.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function _getReportReason(): string
	{
		$reason = $this->_req->getPost('reason', 'trim|strval', '');
		$poster_comment = strtr(Util::htmlspecialchars($reason), ["\r" => '', "\t" => '']);

		if (Util::strlen($poster_comment) > 254)
		{
			throw new Exception('post_too_long', false);
		}

		return $poster_comment;
	}

	/**
	 * Load the message they want to file a complaint against.
	 *
	 * @param int $pmsg
	 * @return array
	 */
	protected function _loadReportedMessage(int $pmsg): array
	{
		[$subject, $body, $time, $memberFromID, $memberFromName, $poster_name, $time_message] = loadPersonalMessage($pmsg);

		return [
			'id_pm' => $pmsg,
			'subject' => $subject,
			'body' => $body,
			'time' => $time,
			'memberFromID' => $memberFromID,
			'memberFromName' => $memberFromName,
			'poster_name' => $poster_name,
			'time_message' => $time_message,
		];
	}

	/**
	 * Record the report for the admins.
	 *
	 * @param int $pmsg
	 * @param array $pmData
	 * @param string $comment
	 */
	protected function _recordReport(int $pmsg, array $pmData, string $comment): void
	{
		recordReport([
			'id_msg' => $pmsg,
			'id_topic' => 0,
			'id_board' => 0,
			'type' => 'pm',
			'id_poster' => $pmData['memberFromID'],
			'real_name' => $pmData['memberFromName'],
			'poster_name' => $pmData['poster_name'],
			'subject' => $pmData['subject'],
			'body' => $pmData['body'],
			'time_message' => $pmData['time_message'],
		], $comment);
	}

	/**
	 * Send the reports to the admins.
	 *
	 * @param array $pmData
	 * @param string $reason
	 */
	protected function _sendReports(array $pmData, string $reason): void
	{
		global $language, $modSettings;

		// Remove the line breaks...
		$body = preg_replace('~<br ?/?>~i', "\n", $pmData['body']);

		$recipients = $this->_getPMRecipients($pmData['id_pm'] ?? (int) $this->_req->getPost('pmsg', 'intval', $this->_req->getQuery('pmsg', 'intval', 0)));

		// Now let's get out and loop through the admins.
		$admins = admins($this->_req->getPost('id_admin', 'intval', 0));

		// Maybe we shouldn't advertise this?
		if (empty($admins))
		{
			throw new Exception('no_access', false);
		}

		$memberFromName = un_htmlspecialchars($pmData['memberFromName']);

		// Prepare the message storage array.
		$messagesToSend = [];

		// Loop through each admin and add them to the right language pile...
		foreach ($admins as $id_admin => $admin_info)
		{
			// Need to send in the correct language!
			$cur_language = empty($admin_info['lngfile']) || empty($modSettings['userLanguage']) ? $language : $admin_info['lngfile'];

			if (!isset($messagesToSend[$cur_language]))
			{
				$mtxt = [];
				$lang = new Loader($cur_language, $mtxt, database());
				$lang->load('PersonalMessage', false);

				// Make the body.
				$report_body = str_replace(['{REPORTER}', '{SENDER}'], [un_htmlspecialchars($this->user->name), $memberFromName], $mtxt['pm_report_pm_user_sent']);
				$report_body .= "\n" . '[b]' . $reason . '[/b]' . "\n\n";
				if (!empty($recipients))
				{
					$report_body .= $mtxt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
				}

				$report_body .= $mtxt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($pmData['memberFromID']) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $pmData['memberFromID'] . ' date=' . $pmData['time']) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';

				// Plonk it in the array ;)
				$messagesToSend[$cur_language] = [
					'subject' => (Util::strpos($pmData['subject'], $mtxt['pm_report_pm_subject']) === false ? $mtxt['pm_report_pm_subject'] : '') . un_htmlspecialchars($pmData['subject']),
					'body' => $report_body,
					'recipients' => [
						'to' => [],
						'bcc' => []
					],
				];
			}

			// Add them to the list.
			$messagesToSend[$cur_language]['recipients']['to'][$id_admin] = $id_admin;
		}

		// Send a different email for each language.
		foreach ($messagesToSend as $message)
		{
			sendpm($message['recipients'], $message['subject'], $message['body']);
		}
	}

	/**
	 * Get the list of recipients for the PM.
	 *
	 * @param int $pmsg
	 * @return array
	 */
	protected function _getPMRecipients(int $pmsg): array
	{
		$recipients = [];
		$temp = loadPMRecipientsAll($pmsg, true);
		foreach ($temp as $recipient)
		{
			$recipients[] = $recipient['link'];
		}

		return $recipients;
	}
}
