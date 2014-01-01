<?php

/**
 * Handles mail configuration, displays the queue and allows for the removal of specific items
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class is the administration mailing controller.
 * It handles mail configuration, it displays and allows to remove items from the mail queue.
 */
class ManageMail_Controller extends Action_Controller
{
	/**
	 * Mail settings form
	 * @var Settings_Form
	 */
	protected $_mailSettings;

	/**
	 * Main dispatcher.
	 * This function checks permissions and passes control through to the relevant section.
	 * @see Action_Controller::action_index()
	 * @uses Help and MangeMail language files
	 */
	public function action_index()
	{
		global $context, $txt;

		loadLanguage('Help');
		loadLanguage('ManageMail');

		// We'll need the utility functions from here.
		require_once(SUBSDIR . '/Settings.class.php');

		$subActions = array(
			'browse' => array($this, 'action_browse', 'permission' => 'admin_forum'),
			'clear' => array($this, 'action_clear', 'permission' => 'admin_forum'),
			'settings' => array($this, 'action_mailSettings_display', 'permission' => 'admin_forum'),
		);

		call_integration_hook('integrate_manage_mail', array(&$subActions));

		// By default we want to browse
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'browse';

		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['mailqueue_title'];

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['mailqueue_title'],
			'help' => '',
			'description' => $txt['mailqueue_desc'],
		);

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions, 'browse');
		$action->dispatch($subAction);
	}

	/**
	 * Display the mail queue...
	 * @uses ManageMail template
	 */
	public function action_browse()
	{
		global $scripturl, $context, $txt;

		require_once(SUBSDIR . '/Mail.subs.php');
		loadTemplate('ManageMail');

		// First, are we deleting something from the queue?
		if (isset($_REQUEST['delete']))
		{
			checkSession('post');
			deleteMailQueueItems($_REQUEST['delete']);
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
						'function' => create_function('$rowData', '
							return Util::strlen($rowData[\'subject\']) > 50 ? sprintf(\'%1$s...\', Util::htmlspecialchars(Util::substr($rowData[\'subject\'], 0, 47))) : Util::htmlspecialchars($rowData[\'subject\']);
						'),
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
						'class' => 'smalltext',
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
						'function' => create_function('$rowData', '
							global $txt;

							// We probably have a text label with your priority.
							$txtKey = sprintf(\'mq_mpriority_%1$s\', $rowData[\'priority\']);

							// But if not, revert to priority 0.
							return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt[\'mq_mpriority_1\'];
						'),
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
						'function' => create_function('$rowData', '
							return time_since(time() - $rowData[\'time_sent\']);
						'),
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
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return \'<input type="checkbox" name="delete[]" value="\' . $rowData[\'id_mail\'] . \'" class="input_check" />\';
						'),
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
					'value' => '
						<input type="submit" name="delete_redirects" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['quickmod_confirm'] . '\');" class="right_submit" />
						<a class="linkbutton_right" href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['mailqueue_clear_list_warning'] . '\');">' . $txt['mailqueue_clear_list'] . '</a> ',
				),
			),
		);

		require_once(SUBSDIR . '/List.class.php');
		createList($listOptions);
	}

	/**
	 * Allows to view and modify the mail settings.
	 * @uses show_settings sub template
	 */
	public function action_mailSettings_display()
	{
		global $txt, $scripturl, $context, $txtBirthdayEmails;

		// Some important context stuff
		$context['page_title'] = $txt['mail_settings'];
		$context['sub_template'] = 'show_settings';

		// Initialize the form
		$this->_initMailSettingsForm();

		// Piece of redundant code, for the javascript
		$processedBirthdayEmails = array();
		foreach ($txtBirthdayEmails as $key => $value)
		{
			$index = substr($key, 0, strrpos($key, '_'));
			$element = substr($key, strrpos($key, '_') + 1);
			$processedBirthdayEmails[$index][$element] = $value;
		}

		$config_vars = $this->_mailSettings->settings();

		call_integration_hook('integrate_modify_mail_settings', array(&$config_vars));

		// Saving?
		if (isset($_GET['save']))
		{
			// Make the SMTP password a little harder to see in a backup etc.
			if (!empty($_POST['smtp_password'][1]))
			{
				$_POST['smtp_password'][0] = base64_encode($_POST['smtp_password'][0]);
				$_POST['smtp_password'][1] = base64_encode($_POST['smtp_password'][1]);
			}
			checkSession();

			// We don't want to save the subject and body previews.
			unset($config_vars['birthday_subject'], $config_vars['birthday_body']);
			call_integration_hook('integrate_save_mail_settings');

			// You can not send more per page load than you can per minute
			if (!empty($_POST['mail_batch_size']))
				 $_POST['mail_batch_size'] = min((int) $_POST['mail_batch_size'], (int) $_POST['mail_period_limit']);

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=mailqueue;sa=settings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=mailqueue;save;sa=settings';
		$context['settings_title'] = $txt['mailqueue_settings'];

		// Prepare the config form
		Settings_Form::prepare_db($config_vars);

		// Build a litte JS so the birthday mail can be seen
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

		addInlineJavascript($javascript . '
		};
		function fetch_birthday_preview()
		{
			var index = document.getElementById(\'birthday_email\').value;

			document.getElementById(\'birthday_subject\').innerHTML = bDay[index].subject;
			document.getElementById(\'birthday_body\').innerHTML = bDay[index].body;
		}', true);
	}

	/**
	 * Initialize mail administration settings.
	 */
	private function _initMailSettingsForm()
	{
		// Instantiate the form
		$this->_mailSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_mailSettings->settings($config_vars);
	}

	/**
	 * Retrieve and return mail administration settings.
	 */
	private function _settings()
	{
		global $txt, $modSettings, $txtBirthdayEmails;

		// we need $txtBirthdayEmails
		loadLanguage('EmailTemplates');

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
				array('text', 'smtp_username'),
				array('password', 'smtp_password'),
			'',
				array('select', 'birthday_email', $emails, 'value' => array('subject' => $subject, 'body' => $body), 'javascript' => 'onchange="fetch_birthday_preview()"'),
				'birthday_subject' => array('var_message', 'birthday_subject', 'var_message' => $processedBirthdayEmails[empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']]['subject'], 'disabled' => true, 'size' => strlen($subject) + 3),
				'birthday_body' => array('var_message', 'birthday_body', 'var_message' => nl2br($body), 'disabled' => true, 'size' => ceil(strlen($body) / 25)),
		);

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
	 * Note force clearing the queue may cause a site to exceed hosting mail limit quotas
	 * Some hosts simple loose these excess emails, others queue them server side, up to a limit
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
		$all_emails = isset($_GET['te']) ? (int) $_GET['te'] : list_getMailQueueSize();

		// If we don't know how many we sent, it must be because... we didn't send any!
		$sent_emails = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

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
	 */
	private function _pauseMailQueueClear($all_emails, $sent_emails)
	{
		global $context, $txt, $time_start;

		// Try get more time...
		@set_time_limit(600);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

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