<?php

/**
 * Behavior shared by the maillist controllers which turn an email into a post, PM or topic
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\Maillist;

use ElkArte\Languages\Txt;

/**
 * Expects the using class to be an AbstractController (for getUser()) and to
 * have loaded MaillistPost.subs.php before calling the DSN / maintenance helpers.
 *
 * @package Maillist
 */
trait MaillistEmailTrait
{
	/** @var array holds user information based on found email address */
	public $pbeUser;

	/**
	 * Checks if the mail list is enabled.
	 *
	 * @return bool
	 */
	private function isMailListEnabled(): bool
	{
		global $modSettings;

		return !empty($modSettings['maillist_enabled']);
	}

	/**
	 * Common setup: loads the PBE subs and language file and raises the memory limit
	 */
	private function initPbe(): void
	{
		require_once(SUBSDIR . '/MaillistPost.subs.php');
		Txt::load('Maillist');
		detectServer()->setMemoryLimit('128M');
	}

	/**
	 * Gives addons a chance to do additional spam / security checking
	 *
	 * @param EmailParse $email_message
	 */
	private function runPreChecksHook($email_message): void
	{
		call_integration_hook('integrate_mailist_checks_before', [$email_message, $this->pbeUser]);
	}

	/**
	 * Removes the permissions of PBE concern that the sender's warning level (moderation) denies
	 */
	private function applyModerationLimits(): void
	{
		global $modSettings;

		if (empty($modSettings['postmod_active']))
		{
			return;
		}

		$warning = $this->pbeUser['user_info']['warning'];
		$permissions = $this->pbeUser['user_info']['permissions'];

		// Have they been muted for being naughty?
		if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $warning)
		{
			$denied_permissions = [
				'pm_send', 'postby_email',
				'admin_forum', 'moderate_forum',
				'post_new', 'post_reply_own', 'post_reply_any',
				'post_attachment', 'post_unapproved_attachments',
				'post_unapproved_topics', 'post_unapproved_replies_own', 'post_unapproved_replies_any',
			];
			$permissions = array_diff($permissions, $denied_permissions);
		}
		// Or just moderated, so their posts need approval
		elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $warning)
		{
			$permission_change = [
				'post_new' => 'post_unapproved_topics',
				'post_reply_own' => 'post_unapproved_replies_own',
				'post_reply_any' => 'post_unapproved_replies_any',
				'post_attachment' => 'post_unapproved_attachments',
			];

			foreach ($permission_change as $old => $new)
			{
				if (!in_array($old, $permissions, true))
				{
					unset($permission_change[$old]);
				}
				else
				{
					$permissions[] = $new;
				}
			}

			$permissions = array_diff($permissions, array_keys($permission_change));
		}

		$this->pbeUser['user_info']['permissions'] = $permissions;
	}

	/**
	 * Reads and parses an email message, HTML version if available.
	 *
	 * @param string|null $data full headers+body email, or null to read from stdin
	 *
	 * @return EmailParse
	 */
	private function loadEmailMessage($data): EmailParse
	{
		$email_message = new EmailParse();
		$email_message->read_data($data, BOARDDIR);
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();

		return $email_message;
	}

	/**
	 * Handles a delivery status notification (bounce)
	 *
	 * @param EmailParse $email_message
	 *
	 * @return bool|string true if not a DSN, otherwise the 'error_bounced' code
	 */
	private function checkDsn($email_message)
	{
		global $modSettings;

		if (!$email_message->_is_dsn)
		{
			return true;
		}

		if (!empty($modSettings['pbe_bounce_detect']))
		{
			pbe_disable_user_notify($email_message);

			// Without bounce recording, the failure is simply not logged
			if (empty($modSettings['pbe_bounce_record']))
			{
				return 'error_bounced';
			}
		}

		// Record the DSN in the failed email table for the admins to handle
		pbe_emailError('error_bounced', $email_message);

		return 'error_bounced';
	}

	/**
	 * Loads the sender based on the From: address
	 *
	 * @param EmailParse $email_message
	 *
	 * @return bool|string true if found, otherwise 'error_not_find_member'
	 */
	private function loadSender($email_message)
	{
		$email_message->email['from'] = empty($email_message->email['from']) ? '' : strtolower($email_message->email['from']);
		$this->pbeUser = query_load_user_info($email_message->email['from']);

		if (empty($this->pbeUser))
		{
			pbe_emailError('error_not_find_member', $email_message);
			return 'error_not_find_member';
		}

		return true;
	}

	/**
	 * Is the forum in maintenance mode, with a sender who is not an admin
	 *
	 * @return bool
	 */
	private function isBlockedByMaintenance(): bool
	{
		global $maintenance;

		return !empty($maintenance) && $maintenance !== 2
			&& !$this->pbeUser['user_info']['is_admin'] && $this->getUser()->is_admin === false;
	}
}
