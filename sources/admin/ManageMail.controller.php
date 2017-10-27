<?php

/**
 * Handles mail configuration, displays the queue and allows for the removal of specific items
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * This class is the administration mailing controller.
 *
 * What it does:
 *
 * - It handles mail configuration,
 * - It displays and allows to remove items from the mail queue.
 *
 * @package Mail
 */
class ManageMail_Controller extends Action_Controller
{
	/**
	 * Main dispatcher.
	 *
	 * - This function checks permissions and passes control through to the relevant section.
	 *
	 * @event integrate_sa_manage_mail Used to add more sub actions
	 * @see Action_Controller::action_index()
	 * @uses Help and MangeMail language files
	 */
	public function action_index()
	{
		global $context, $txt;

		theme()->getTemplates()->loadLanguageFile('Help');
		theme()->getTemplates()->loadLanguageFile('ManageMail');

		$subActions = array(
			'browse' => array($this, 'action_browse', 'permission' => 'admin_forum'),
			'clear' => array($this, 'action_clear', 'permission' => 'admin_forum'),
			'settings' => array($this, 'action_mailSettings_display', 'permission' => 'admin_forum'),
		);

		// Action control
		$action = new Action('manage_mail');

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['mailqueue_title'],
			'help' => '',
			'description' => $txt['mailqueue_desc'],
		);

		// By default we want to browse, call integrate_sa_manage_mail
		$subAction = $action->initialize($subActions, 'browse');

		// Final bits
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['mailqueue_title'];

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Display the mail queue...
	 *
	 * @uses ManageMail template
	 */
	public function action_browse()
	{
		global $scripturl, $context, $txt;

		require_once(SUBSDIR . '/Mail.subs.php');
		theme()->getTemplates()->load('ManageMail');

		// First, are we deleting something from the queue?
		if (isset($this->_req->post->delete))
		{
			checkSession('post');
			deleteMailQueueItems($this->_req->post->delete);
		}

		// Fetch the number of items in the current queue
		$status = list_MailQueueStatus();

		$context['oldest_mail'] = empty($status['mailOldest']) ? $txt['mailqueue_oldest_not_available'] : time_since(time() - $status['mailOldest']);
		$context['mail_queue_size'] = comma_format($status['mailQueueSize']);

		// Build our display list
		$listOptions = array(
			'id' => 'mail_queue',
			'title' => $txt['mailqueue_browse'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=mailqueue',
			'default_sort_col' => 'age',
			'no_items_label' => $txt['mailqueue_no_items'],
			'get_items' => array(
				'function' => 'list_getMailQueue',
			),
			'get_count' => array(
				'function' => 'list_getMailQueueSize',
			),
			'columns' => array(
				'subject' => array(
					'header' => array(
						'value' => $txt['mailqueue_subject'],
					),
					'data' => array(
						'function' => function ($rowData) {
							return Util::shorten_text(Util::htmlspecialchars($rowData['subject'], 50));
						},
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'subject',
						'reverse' => 'subject DESC',
					),
				),
				'recipient' => array(
					'header' => array(
						'value' => $txt['mailqueue_recipient'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="mailto:%1$s">%1$s</a>',
							'params' => array(
								'recipient' => true,
							),
						),
					),
					'sort' => array(
						'default' => 'recipient',
						'reverse' => 'recipient DESC',
					),
				),
				'priority' => array(
					'header' => array(
						'value' => $txt['mailqueue_priority'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							// We probably have a text label with your priority.
							$txtKey = sprintf('mq_mpriority_%1$s', $rowData['priority']);

							// But if not, revert to priority 0.
							return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt['mq_mpriority_1'];
						},
						'class' => 'centertext smalltext',
					),
					'sort' => array(
						'default' => 'priority',
						'reverse' => 'priority DESC',
					),
				),
				'age' => array(
					'header' => array(
						'value' => $txt['mailqueue_age'],
					),
					'data' => array(
						'function' => function ($rowData) {
							return time_since(time() - $rowData['time_sent']);
						},
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'time_sent',
						'reverse' => 'time_sent DESC',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					),
					'data' => array(
						'function' => function ($rowData) {
							return '<input type="checkbox" name="delete[]" value="' . $rowData['id_mail'] . '" class="input_check" />';
						},
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=mailqueue',
				'include_start' => true,
				'include_sort' => true,
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'class' => 'submitbutton',
					'value' => '
						<input type="submit" name="delete_redirects" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['quickmod_confirm'] . '\');" />
						<a class="linkbutton" href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['mailqueue_clear_list_warning'] . '\');">' . $txt['mailqueue_clear_list'] . '</a> ',
				),
			),
		);

		createList($listOptions);
	}

	/**
	 * Allows to view and modify the mail settings.
	 *
	 * @event integrate_save_mail_settings
	 * @uses show_settings sub template
	 */
	public function action_mailSettings_display()
	{
		global $txt, $scripturl, $context, $txtBirthdayEmails;

		// Some important context stuff
		$context['page_title'] = $txt['mail_settings'];
		$context['sub_template'] = 'show_settings';

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_settings();
		$settingsForm->setConfigVars($config_vars);

		// Piece of redundant code, for the javascript
		$processedBirthdayEmails = array();
		foreach ($txtBirthdayEmails as $key => $value)
		{
			$index = substr($key, 0, strrpos($key, '_'));
			$element = substr($key, strrpos($key, '_') + 1);
			$processedBirthdayEmails[$index][$element] = $value;
		}

		// Saving?
		if (isset($this->_req->query->save))
		{
			// Make the SMTP password a little harder to see in a backup etc.
			if (!empty($this->_req->post->smtp_password[1]))
			{
				$this->_req->post->smtp_password[0] = base64_encode($this->_req->post->smtp_password[0]);
				$this->_req->post->smtp_password[1] = base64_encode($this->_req->post->smtp_password[1]);
			}
			checkSession();

			// We don't want to save the subject and body previews.
			unset($config_vars['birthday_subject'], $config_vars['birthday_body']);
			$settingsForm->setConfigVars($config_vars);
			call_integration_hook('integrate_save_mail_settings');

			// You can not send more per page load than you can per minute
			if (!empty($this->_req->post->mail_batch_size))
				$this->_req->post->mail_batch_size = min((int) $this->_req->post->mail_batch_size, (int) $this->_req->post->mail_period_limit);

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=mailqueue;sa=settings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=mailqueue;save;sa=settings';
		$context['settings_title'] = $txt['mailqueue_settings'];

		// Prepare the config form
		$settingsForm->prepare();

		// Build a little JS so the birthday mail can be seen
		$javascript = '
			var bDay = {';

		$i = 0;
		foreach ($processedBirthdayEmails as $index => $email)
		{
			$is_last = ++$i == count($processedBirthdayEmails);
			$javascript .= '
				' . $index . ': {
				subject: ' . JavaScriptEscape($email['subject']) . ',
				body: ' . JavaScriptEscape(nl2br($email['body'])) . '
			}' . (!$is_last ? ',' : '');
		}

		theme()->addInlineJavascript($javascript . '
		};
		function fetch_birthday_preview()
		{
			var index = document.getElementById(\'birthday_email\').value;

			document.getElementById(\'birthday_subject\').innerHTML = bDay[index].subject;
			document.getElementById(\'birthday_body\').innerHTML = bDay[index].body;
		}', true);
	}

	/**
	 * Retrieve and return mail administration settings.
	 *
	 * @event integrate_modify_mail_settings Add new settings
	 */
	private function _settings()
	{
		global $txt, $modSettings, $txtBirthdayEmails;

		// We need $txtBirthdayEmails
		theme()->getTemplates()->loadLanguageFile('EmailTemplates');

		$body = $txtBirthdayEmails[(empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']) . '_body'];
		$subject = $txtBirthdayEmails[(empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']) . '_subject'];

		$emails = array();
		$processedBirthdayEmails = array();
		foreach ($txtBirthdayEmails as $key => $value)
		{
			$index = substr($key, 0, strrpos($key, '_'));
			$element = substr($key, strrpos($key, '_') + 1);
			$processedBirthdayEmails[$index][$element] = $value;
		}
		foreach ($processedBirthdayEmails as $index => $dummy)
			$emails[$index] = $index;

		$config_vars = array(
				// Mail queue stuff, this rocks ;)
				array('check', 'mail_queue'),
				array('int', 'mail_period_limit'),
				array('int', 'mail_batch_size'),
			'',
				// SMTP stuff.
				array('select', 'mail_type', array($txt['mail_type_default'], 'SMTP')),
				array('text', 'smtp_host'),
				array('text', 'smtp_port'),
				array('check', 'smtp_starttls'),
				array('text', 'smtp_username'),
				array('password', 'smtp_password'),
			'',
				array('select', 'birthday_email', $emails, 'value' => array('subject' => $subject, 'body' => $body), 'javascript' => 'onchange="fetch_birthday_preview()"'),
				'birthday_subject' => array('var_message', 'birthday_subject', 'message' => $processedBirthdayEmails[empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']]['subject'], 'disabled' => true, 'size' => strlen($subject) + 3),
				'birthday_body' => array('var_message', 'birthday_body', 'message' => nl2br($body), 'disabled' => true, 'size' => ceil(strlen($body) / 25)),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_mail_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * This function clears the mail queue of all emails, and at the end redirects to browse.
	 *
	 * - Note force clearing the queue may cause a site to exceed hosting mail limit quotas
	 * - Some hosts simple loose these excess emails, others queue them server side, up to a limit
	 */
	public function action_clear()
	{
		global $modSettings;

		checkSession('get');

		// This is certainly needed!
		require_once(SUBSDIR . '/Mail.subs.php');

		// Set a number to send each loop
		$number_to_send = empty($modSettings['mail_period_limit']) ? 25 : $modSettings['mail_period_limit'];

		// If we don't yet have the total to clear, find it.
		$all_emails = isset($this->_req->query->te) ? (int) $this->_req->query->te : list_getMailQueueSize();

		// If we don't know how many we sent, it must be because... we didn't send any!
		$sent_emails = isset($this->_req->query->sent) ? (int) $this->_req->query->sent : 0;

		// Send this batch, then go for a short break...
		while (reduceMailQueue($number_to_send, true, true) === true)
		{
			// Sent another batch
			$sent_emails += $number_to_send;
			$this->_pauseMailQueueClear($all_emails, $sent_emails);
		}

		return $this->action_browse();
	}

	/**
	 * Used for pausing the mail queue.
	 *
	 * @param int $all_emails total emails to be sent
	 * @param int $sent_emails number of emails sent so far
	 * @throws Elk_Exception
	 */
	private function _pauseMailQueueClear($all_emails, $sent_emails)
	{
		global $context, $txt, $time_start;

		// Try get more time...
		detectServer()->setTimeLimit(600);

		// Have we already used our maximum time?
		if (time() - array_sum(explode(' ', $time_start)) < 5)
			return;

		$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $all_emails . ';sent=' . $sent_emails . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = '10';
		$context['sub_template'] = 'not_done';

		// Keep browse selected.
		$context['selected'] = 'browse';

		// What percent through are we?
		$context['continue_percent'] = round(($sent_emails / $all_emails) * 100, 1);

		// Never more than 100%!
		$context['continue_percent'] = min($context['continue_percent'], 100);

		obExit();
	}
}
