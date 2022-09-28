<?php

/**
 * This manages the admin logs, and forwards to display, pruning,
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;

/**
 * Admin logs controller.
 *
 * What it does:
 *
 * - This class manages logs, and forwards to display, pruning,
 * and other actions on logs.
 *
 * @package AdminLog
 */
class AdminLog extends AbstractController
{
	/**
	 * This method decides which log to load.
	 * Accessed by ?action=admin;area=logs
	 *
	 * @event integrate_sa_manage_logs used to add additional log viewing functions, passed subActions array
	 * @uses _initPruningSettingsForm
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings;

		// These are the logs they can load.
		$subActions = array(
			'errorlog' => array(
				'function' => 'action_index',
				'controller' => '\\ElkArte\\AdminController\\ManageErrors'),
				'disabled' => empty($modSettings['enableErrorLogging']),
			'adminlog' => array(
				'function' => 'action_log',
				'controller' => '\\ElkArte\\AdminController\\Modlog'),
			'modlog' => array(
				'function' => 'action_log',
				'controller' => '\\ElkArte\\AdminController\\Modlog',
				'disabled' => !featureEnabled('ml') || empty($modSettings['modlog_enabled'])),
			'banlog' => array(
				'function' => 'action_log',
				'controller' => '\\ElkArte\\AdminController\\ManageBans'),
			'spiderlog' => array(
				'function' => 'action_logs',
				'controller' => '\\ElkArte\\AdminController\\ManageSearchEngines'),
			'tasklog' => array(
				'function' => 'action_log',
				'controller' => '\\ElkArte\\AdminController\\ManageScheduledTasks'),
			'pruning' => array(
				'controller' => $this,
				'function' => 'action_pruningSettings_display'),
		);

		// Setup the custom tabs.
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'logs',
			'description' => 'maintain_info',
			'tabs' => [
				'errorlog' => [
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog', 'desc']),
					'description' => sprintf($txt['errlog_desc'], $txt['remove']),
					'disabled' => empty($modSettings['enableErrorLogging']),
				],
				'adminlog' => [
					'description' => $txt['admin_log_desc'],
				],
				'modlog' => [
					'description' => $txt['moderation_log_desc'],
					'disabled' => !featureEnabled('ml') || empty($modSettings['modlog_enabled']),
				],
				'banlog' => [
					'description' => $txt['ban_log_description'],
				],
				'spiderlog' => [
					'description' => $txt['spider_log_desc'],
				],
				'tasklog' => [
					'description' => $txt['scheduled_log_desc'],
				],
				'pruning' => [
					'description' => $txt['pruning_log_desc'],
				],
			]]
		);

		// If there is no sa set it must have come here for first time,
		// pretend error log should be reversed.
		if (!isset($this->_req->query->sa))
		{
			$this->_req->query->desc = true;
		}

		// Set up the action control
		$action = new Action('manage_logs');

		// By default do the basic settings, call integrate_sa_manage_logs
		$subAction = $action->initialize($subActions, 'errorlog');

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Allow to edit the settings on the pruning screen.
	 *
	 * @event integrate_prune_settings add additional settings to the auto pruning display.  If you add any
	 * additional logs make sure to add them at the end.  Additionally, make sure you add them to the
	 * weekly scheduled task.
	 * @uses _pruningSettings form.
	 */
	public function action_pruningSettings_display()
	{
		global $txt, $context, $modSettings;

		// Make sure we understand what's going on.
		Txt::load('ManageSettings');

		$context['page_title'] = $txt['log_settings'];

		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize settings
		$config_vars = $this->_settings();
		$settingsForm->setConfigVars($config_vars);

		call_integration_hook('integrate_prune_settings');

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			$savevar = [
				['check', 'enableErrorLogging'],
				['check', 'enableErrorQueryLogging'],
				['check', 'modlog_enabled'],
				['check', 'userlog_enabled'],
				['text', 'pruningOptions']
			];

			// If pruning is enabled, compile all pruneXYZlog options into a CSV string, yes
			// this is really this badly thought out.
			if (!empty($this->_req->post->pruningOptions))
			{
				$vals = [];
				foreach ($config_vars as $index => $config_value)
				{
					if (!is_array($config_value) || $index === 'pruningOptions' || strpos($config_value[1], 'prune') !== 0)
					{
						continue;
					}

					$vals[] = empty($this->_req->post->{$config_value[1]}) || $this->_req->post->{$config_value[1]} < 0 ? 0 : $this->_req->getPost($config_value[1], 'intval');
				}

				$_POST['pruningOptions'] = implode(',', $vals);
			}
			else
			{
				$_POST['pruningOptions'] = '';
			}

			$settingsForm->setConfigVars($savevar);
			$settingsForm->setConfigValues((array) $_POST);
			$settingsForm->save();
			redirectexit('action=admin;area=logs;sa=pruning');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'pruning', 'save']);
		$context['settings_title'] = $txt['log_settings'];
		$context['sub_template'] = 'show_settings';

		// Get the actual values
		if (!empty($modSettings['pruningOptions']))
		{
			list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneSpiderHitLog']) = array_pad(explode(',', $modSettings['pruningOptions']), 7, 0);
		}
		else
		{
			$modSettings['pruneErrorLog'] = $modSettings['pruneModLog'] = $modSettings['pruneBanLog'] = $modSettings['pruneReportLog'] = $modSettings['pruneScheduledTaskLog'] = $modSettings['pruneSpiderHitLog'] = 0;
		}

		$settingsForm->prepare();
	}

	/**
	 * Returns the configuration settings for pruning logs.
	 */
	private function _settings()
	{
		global $txt;

		return [
			// See all the mistakes the developers make
			['check', 'enableErrorLogging'],
			['check', 'enableErrorQueryLogging'],
			// Moderation logging is a Core feature, it enables Admin, Moderation and Profile Edit logging.  This
			// allows some fine-tuning of that features, e.g. only allow admin logging
			featureEnabled('ml') ?
			['check', 'modlog_enabled'] : '',
			featureEnabled('ml') ?
			['check', 'userlog_enabled'] : '',
			// Even do the pruning?
			['title', 'pruning_title', 'force_div_id' => 'pruning_title'],
			// The array indexes are here so, we can remove/change them before saving.
			'pruningOptions' => ['check', 'pruningOptions'],
			'',
			// Various logs that could be pruned.
			['int', 'pruneErrorLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Error log.
			['int', 'pruneModLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Moderation log.
			['int', 'pruneBanLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Ban hit log.
			['int', 'pruneReportLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Report to moderator log.
			['int', 'pruneScheduledTaskLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Log of the scheduled tasks and how long they ran.
			['int', 'pruneSpiderHitLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']], // Log of the scheduled tasks and how long they ran.
			// If you add any additional logs make sure to add them after this point.  Additionally, make sure you
			// add them to the weekly scheduled task.
		];
	}

	/**
	 * Return the search engine settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
