<?php

/**
 * Manage post notification queue administration.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\Notifications\PostNotificationQueue;
use ElkArte\Notifications\PostNotificationRunner;
use ElkArte\SettingsForm\SettingsForm;

/**
 * Notification Queue administration controller.
 * This class handles viewing, processing, and clearing of deferred post notifications.
 */
class ManageNotificationQueue extends AbstractController
{
	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		require_once(SUBSDIR . '/Notification.subs.php');
		require_once(SUBSDIR . '/Mail.subs.php');

		Txt::load('Help+ManageSettings+ManageNotificationQueue+ManageMail');
	}

	/**
	 * Main dispatcher for notification queue administration.
	 *
	 * @event integrate_sa_manage_notification_queue
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		$subActions = [
			'browse' => [$this, 'action_browse', 'permission' => 'admin_forum'],
			'clear' => [$this, 'action_clear', 'permission' => 'admin_forum'],
			'purge' => [$this, 'action_purge', 'permission' => 'admin_forum'],
			'settings' => [$this, 'action_notificationQueueSettings_display', 'permission' => 'admin_forum'],
		];

		// Set up action control
		$action = new Action('manage_notification_queue');

		// Hook for additional subactions
		$subAction = $action->initialize($subActions, 'browse');

		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['notificationqueue_title'];

		// Prepare tab data
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'notificationqueue_title',
			'class' => 'i-bell-blank',
			'description' => 'notificationqueue_desc',
		]);

		$action->dispatch($subAction);
	}

	/**
	 * Display and browse the notification queue.
	 *
	 * @uses ManageNotificationQueue template
	 */
	public function action_browse(): void
	{
		global $context, $txt;

		theme()->getTemplates()->load('ManageNotificationQueue');

		// Deleting something from the queue?
		if (isset($this->_req->post->delete) || isset($this->_req->post->delete_selected))
		{
			checkSession();
			$delete_ids = (array) ($this->_req->post->delete ?? []);
			if (!empty($delete_ids))
			{
				deleteNotificationQueueItems($delete_ids);
			}
		}

		// Fetch queue status statistics
		$status = list_NotificationQueueStatus();

		$context['oldest_job'] = empty($status['oldest']) ? $txt['notificationqueue_oldest_not_available'] : time_since(time() - $status['oldest']);
		$context['queue_size'] = comma_format($status['queue_size']);
		$context['ready_jobs'] = comma_format($status['ready']);
		$context['in_grace_jobs'] = comma_format($status['in_grace']);
		$context['locked_jobs'] = comma_format($status['locked']);

		// Build the display list
		$listOptions = [
			'id' => 'notification_queue',
			'title' => $txt['notificationqueue_browse'],
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'notificationqueue']),
			'default_sort_col' => 'id_job',
			'no_items_label' => $txt['notificationqueue_no_items'],
			'get_items' => [
				'function' => 'list_getNotificationQueue',
			],
			'get_count' => [
				'function' => 'list_getNotificationQueueSize',
			],
			'columns' => [
				'id_job' => [
					'header' => [
						'value' => '#',
						'class' => 'centertext',
					],
					'data' => [
						'db' => 'id_job',
						'class' => 'centertext smalltext',
					],
					'sort' => [
						'default' => 'id_job',
						'reverse' => 'id_job DESC',
					],
				],
				'task_type' => [
					'header' => [
						'value' => $txt['notificationqueue_task_type'],
					],
					'data' => [
						'function' => static function ($rowData) use ($txt) {
							return $txt['notificationqueue_type_' . $rowData['task_type']] ?? Util::htmlspecialchars($rowData['task_type']);
						},
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'task_type',
						'reverse' => 'task_type DESC',
					],
				],
				'subject' => [
					'header' => [
						'value' => $txt['notificationqueue_subject'],
					],
					'data' => [
						'function' => static function ($rowData) {
							if (!empty($rowData['subject']))
							{
								return '<a href="' . getUrl('topic', ['topic' => $rowData['id_topic'], 'msg' => $rowData['id_msg']]) . '">' . Util::shorten_text(Util::htmlspecialchars($rowData['subject']), 50) . '</a>';
							}

							return '#' . (int) $rowData['id_topic'] . ' / #' . (int) $rowData['id_msg'];
						},
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'subject',
						'reverse' => 'subject DESC',
					],
				],
				'poster' => [
					'header' => [
						'value' => $txt['notificationqueue_poster'],
					],
					'data' => [
						'function' => static function ($rowData) {
							if (!empty($rowData['id_member']) && !empty($rowData['poster_name']))
							{
								return '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $rowData['id_member']]) . '">' . Util::htmlspecialchars($rowData['poster_name']) . '</a>';
							}

							if (!empty($rowData['poster_name']))
							{
								return Util::htmlspecialchars($rowData['poster_name']);
							}

							return '#' . (int) $rowData['id_member'];
						},
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'poster',
						'reverse' => 'poster DESC',
					],
				],
				'status' => [
					'header' => [
						'value' => $txt['notificationqueue_status'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) use ($txt) {
							$now = time();

							if ($rowData['attempts'] >= PostNotificationQueue::MAX_ATTEMPTS)
							{
								return sprintf($txt['notificationqueue_status_failed'], $rowData['attempts'], PostNotificationQueue::MAX_ATTEMPTS);
							}

							if ($rowData['locked_until'] > $now)
							{
								$left = $rowData['locked_until'] - $now;

								if ((int) $rowData['attempts'] === 0)
								{
									return sprintf($txt['notificationqueue_status_grace'], $left);
								}

								return sprintf($txt['notificationqueue_status_locked'], $left);
							}

							return $txt['notificationqueue_status_ready'];
						},
						'class' => 'centertext smalltext',
					],
					'sort' => [
						'default' => 'status',
						'reverse' => 'status DESC',
					],
				],
				'attempts' => [
					'header' => [
						'value' => $txt['notificationqueue_attempts'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static fn($rowData) => $rowData['attempts'] . ' / ' . PostNotificationQueue::MAX_ATTEMPTS,
						'class' => 'centertext smalltext',
					],
					'sort' => [
						'default' => 'attempts',
						'reverse' => 'attempts DESC',
					],
				],
				'age' => [
					'header' => [
						'value' => $txt['notificationqueue_age'],
					],
					'data' => [
						'function' => static fn($rowData) => time_since(time() - $rowData['created']),
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'age',
						'reverse' => 'age DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext'
					],
					'data' => [
						'function' => static fn($rowData) => '<input type="checkbox" name="delete[]" value="' . $rowData['id_job'] . '" class="input_check" />',
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'notificationqueue']),
				'include_start' => true,
				'include_sort' => true,
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'class' => 'submitbutton',
					'value' => '
						<input type="submit" name="delete_selected" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['quickmod_confirm'] . '\');" />
						<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'notificationqueue', 'sa' => 'clear', '{session_data}']) . '" onclick="return confirm(\'' . $txt['notificationqueue_clear_list_warning'] . '\');">' . $txt['notificationqueue_clear_list'] . '</a>
						<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'notificationqueue', 'sa' => 'purge', '{session_data}']) . '" onclick="return confirm(\'' . $txt['notificationqueue_purge_list_warning'] . '\');">' . $txt['notificationqueue_purge_list'] . '</a>',
				],
			],
		];

		createList($listOptions);
	}

	/**
	 * Process the notification queue immediately.
	 */
	public function action_clear()
	{
		checkSession('get');

		$queue = new PostNotificationQueue();
		$runner = new PostNotificationRunner(null, null, $queue);

		$all_jobs = $this->_req->getQuery('tj', 'intval', $queue->countPending());
		$processed_jobs = $this->_req->getQuery('processed', 'intval', 0);
		$number_to_process = 10;

		// Process batches with force=true to bypass lock/grace delays
		while ($runner->runBatch($number_to_process, true) > 0)
		{
			$processed_jobs += $number_to_process;
			$this->_pauseNotificationQueueClear($all_jobs, $processed_jobs);
		}

		$this->action_browse();
		return null;
	}

	/**
	 * Used for pausing during large notification queue processing runs.
	 *
	 * @param int $all_jobs total jobs to process
	 * @param int $processed_jobs number of jobs processed so far
	 */
	private function _pauseNotificationQueueClear(int $all_jobs, int $processed_jobs): void
	{
		global $context, $txt, $time_start;

		detectServer()->setTimeLimit(600);

		if ((microtime(true) - $time_start) < 3)
		{
			return;
		}

		$context['continue_get_data'] = '?action=admin;area=notificationqueue;sa=clear;tj=' . $all_jobs . ';processed=' . $processed_jobs . ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = '10';
		$context['sub_template'] = 'not_done';
		$context['selected'] = 'browse';

		$context['continue_percent'] = $all_jobs > 0 ? min(round(($processed_jobs / $all_jobs) * 100, 1), 100) : 100;

		obExit();
	}

	/**
	 * Purge all items from the notification queue.
	 */
	public function action_purge(): void
	{
		checkSession('get');

		(new PostNotificationQueue())->purgeQueue();

		redirectexit('action=admin;area=notificationqueue');
	}

	/**
	 * View and modify notification queue settings.
	 *
	 * @event integrate_save_notification_queue_settings
	 * @uses show_settings sub template
	 */
	public function action_notificationQueueSettings_display(): void
	{
		global $txt, $context;

		$context['page_title'] = $txt['notificationqueue_settings'];
		$context['sub_template'] = 'show_settings';

		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);
		$config_vars = $this->_settings();
		$settingsForm->setConfigVars($config_vars);

		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_notification_queue_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			redirectexit('action=admin;area=notificationqueue;sa=settings');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'notificationqueue', 'sa' => 'settings', 'save']);
		$context['settings_title'] = $txt['notificationqueue_settings'];

		$settingsForm->prepare();
	}

	/**
	 * Retrieve configuration variables for notification queue settings.
	 *
	 * @event integrate_modify_notification_queue_settings
	 * @return array
	 */
	private function _settings(): array
	{
		global $txt;

		$config_vars = [
			'deferred_post_notifications' => ['check', 'deferred_post_notifications', 'subtext' => $txt['deferred_post_notifications_desc']],
			'deferred_post_notifications_delay' => ['int', 'deferred_post_notifications_delay', 'postinput' => $txt['manageposts_seconds'], 'min' => 0,	'subtext' => $txt['deferred_post_notifications_delay_desc']],
		];

		call_integration_hook('integrate_modify_notification_queue_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search.
	 */
	public function settings_search(): array
	{
		return $this->_settings();
	}
}
