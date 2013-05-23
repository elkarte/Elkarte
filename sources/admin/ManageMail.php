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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * This class is the administration mailing controller.
 * It handles mail configuration, it displays and allows to remove items from the mail queue.
 *
 */
class ManageMail_Controller
{
	/**
	 * Mail settings form
	 * @var Settings_Form
	 */
	protected $_mailSettings;

	/**
	 * Main dispatcher.
	 * This function checks permissions and passes control through to the relevant section.
	 */
	public function action_index()
	{
		global $context, $txt;

		// You need to be an admin to edit settings!
		isAllowedTo('admin_forum');

		loadLanguage('Help');
		loadLanguage('ManageMail');

		// We'll need the utility functions from here.
		require_once(SUBSDIR . '/Settings.class.php');

		$context['page_title'] = $txt['mailqueue_title'];

		$subActions = array(
			'browse' => array($this, 'action_browse'),
			'clear' => array($this, 'action_clear'),
			'settings' => array($this, 'action_mailSettings_display'),
		);

		call_integration_hook('integrate_manage_mail', array(&$subActions));

		// By default we want to browse
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'browse';
		$context['sub_action'] = $_REQUEST['sa'];

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['mailqueue_title'],
			'help' => '',
			'description' => $txt['mailqueue_desc'],
		);

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions);
		$action->dispatch($_REQUEST['sa']);
	}

	/**
	 * Display the mail queue...
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

		$status = list_MailQueueStatus();

		$context['oldest_mail'] = empty($status['mailOldest']) ? $txt['mailqueue_oldest_not_available'] : $this->_time_since(time() - $status['mailOldest']);
		$context['mail_queue_size'] = comma_format($status['mailQueueSize']);

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
							return Util::strlen($rowData[\'subject\']) > 50 ? sprintf(\'%1$s...\', htmlspecialchars(Util::substr($rowData[\'subject\'], 0, 47))) : htmlspecialchars($rowData[\'subject\']);
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
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							// We probably have a text label with your priority.
							$txtKey = sprintf(\'mq_mpriority_%1$s\', $rowData[\'priority\']);

							// But if not, revert to priority 0.
							return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt[\'mq_mpriority_1\'];
						'),
						'class' => 'smalltext',
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
							return $this->_time_since(time() - $rowData[\'time_sent\']);
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
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return \'<input type="checkbox" name="delete[]" value="\' . $rowData[\'id_mail\'] . \'" class="input_check" />\';
						'),
						'class' => 'smalltext',
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
					'value' => '<input type="submit" name="delete_redirects" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['quickmod_confirm'] . '\');" class="button_submit" /><a class="button_link" href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['mailqueue_clear_list_warning'] . '\');">' . $txt['mailqueue_clear_list'] . '</a> ',
				),
			),
		);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);
	}

	/**
	 * Allows to view and modify the mail settings.
	 */
	public function action_mailSettings_display()
	{
		global $txt, $scripturl, $context, $txtBirthdayEmails;

		// Some important context stuff
		$context['page_title'] = $txt['calendar_settings'];
		$context['sub_template'] = 'show_settings';

		// initialize the form
		$this->_initMailSettingsForm();

		// piece of redundant code, for the javascript
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

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=mailqueue;sa=settings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=mailqueue;save;sa=settings';
		$context['settings_title'] = $txt['mailqueue_settings'];

		Settings_Form::prepare_db($config_vars);

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
		global $txt, $modSettings, $txtBirthdayEmails;

		// instantiate the form
		$this->_mailSettings = new Settings_Form();

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
				array('int', 'mail_limit'),
				array('int', 'mail_quantity'),
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

		return $this->_mailSettings->settings($config_vars);
	}

	/**
	 * Retrieve and return mail administration settings.
	 */
	public function settings()
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
				array('int', 'mail_limit'),
				array('int', 'mail_quantity'),
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
	 * This function clears the mail queue of all emails, and at the end redirects to browse.
	 */
	public function action_clear()
	{
		checkSession('get');

		// This is certainly needed!
		require_once(SOURCEDIR . '/ScheduledTasks.php');
		require_once(SUBSDIR . '/Mail.subs.php');

		// If we don't yet have the total to clear, find it.
		$all_emails = isset($_GET['te']) ? (int) $_GET['te'] : list_getMailQueueSize();

		// If we don't know how many we sent, it must be because... we didn't send any!
		$sent_emails = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

		// Send 50 at a time, then go for a break...
		while (ReduceMailQueue(50, true, true) === true)
		{
			// Sent another 50.
			$sent_emails += 50;
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
		$context['continue_countdown'] = '2';
		$context['sub_template'] = 'not_done';

		// Keep browse selected.
		$context['selected'] = 'browse';

		// What percent through are we?
		$context['continue_percent'] = round(($sent_emails / $all_emails) * 100, 1);

		// Never more than 100%!
		$context['continue_percent'] = min($context['continue_percent'], 100);

		obExit();
	}

	/**
	* Little utility function to calculate how long ago a time was.
	*
	* @param long $time_diff
	* @return string
	*/
	private function _time_since($time_diff)
	{
		global $txt;

		if ($time_diff < 0)
			$time_diff = 0;

		// Just do a bit of an if fest...
		if ($time_diff > 86400)
		{
			$days = round($time_diff / 86400, 1);
			return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
		}
		// Hours?
		elseif ($time_diff > 3600)
		{
			$hours = round($time_diff / 3600, 1);
			return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
		}
		// Minutes?
		elseif ($time_diff > 60)
		{
			$minutes = (int) ($time_diff / 60);
			return sprintf($minutes == 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
		}
		// Otherwise must be second
		else
			return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
	}
}