<?php

/**
 * This file contains all the administration functions for subscriptions.
 * (and some more than that :P)
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\DataValidator;
use ElkArte\Exceptions\Exception;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;
use ElkArte\Util;

/**
 * ManagePaid controller, administration controller for paid subscriptions.
 *
 * @package Subscriptions
 */
class ManagePaid extends AbstractController
{
	/**
	 * The main entrance point for the 'Paid Subscription' screen,
	 *
	 * What it does:
	 *
	 * - calling the right function based on the given sub-action.
	 * - It defaults to sub-action 'view'.
	 * - Accessed from ?action=admin;area=paidsubscribe.
	 * - It requires admin_forum permission for admin based actions.
	 *
	 * @event integrate_sa_manage_subscriptions
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings;

		// Load the required language and template.
		Txt::load('ManagePaid');
		theme()->getTemplates()->load('ManagePaid');

		$subActions = array(
			'modify' => array(
				'controller' => $this,
				'function' => 'action_modify',
				'permission' => 'admin_forum'),
			'modifyuser' => array(
				'controller' => $this,
				'function' => 'action_modifyuser',
				'permission' => 'admin_forum'),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_paidSettings_display',
				'permission' => 'admin_forum'),
			'view' => array(
				'controller' => $this,
				'function' => 'action_view',
				'permission' => 'admin_forum'),
			'viewsub' => array(
				'controller' => $this,
				'function' => 'action_viewsub',
				'permission' => 'admin_forum'),
		);

		// Some actions
		$action = new Action('manage_subscriptions');

		// Tabs for browsing the different subscription functions.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['paid_subscriptions'],
			'help' => '',
			'description' => $txt['paid_subscriptions_desc'],
			'tabs' => array(
				'view' => array(
					'description' => $txt['paid_subs_view_desc'],
				),
				'settings' => array(
					'description' => $txt['paid_subs_settings_desc'],
				),
			),
		);

		// Load in the subActions, call integrate_sa_manage_subscriptions
		$subAction = $action->initialize($subActions, !empty($modSettings['paid_currency_symbol']) ? 'view' : 'settings');

		// Final things for the template
		$context['page_title'] = $txt['paid_subscriptions'];
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Set any setting related to paid subscriptions,
	 *
	 * - i.e. modify which payment methods are to be used.
	 * - It requires the moderate_forum permission
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=settings.
	 *
	 * @event integrate_save_subscription_settings
	 */
	public function action_paidSettings_display()
	{
		global $context, $txt, $modSettings;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_settings();
		$settingsForm->setConfigVars($config_vars);

		// Some important context stuff
		$context['page_title'] = $txt['settings'];
		$context['sub_template'] = 'show_settings';
		$context['settings_message'] = replaceBasicActionUrl($txt['paid_note']);
		$context[$context['admin_menu_name']]['current_subsection'] = 'settings';

		// Get the final touches in place.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'save', 'sa' => 'settings']);
		$context['settings_title'] = $txt['settings'];

		// We want javascript for our currency options.
		theme()->addInlineJavascript('
		toggleCurrencyOther();', true);

		// Saving the settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_subscription_settings');

			// Check that the entered email addresses are valid
			if (!empty($this->_req->post->paid_email_to))
			{
				$validator = new DataValidator();

				// Some cleaning and some rules
				$validator->sanitation_rules(array('paid_email_to' => 'trim'));
				$validator->validation_rules(array('paid_email_to' => 'valid_email'));
				$validator->input_processing(array('paid_email_to' => 'csv'));
				$validator->text_replacements(array('paid_email_to' => $txt['paid_email_to']));

				if ($validator->validate($this->_req->post))
				{
					$this->_req->post->paid_email_to = $validator->validation_data('paid_email_to');
				}
				else
				{
					// That's not an email, lets set it back in the form to be fixed and let them know its wrong
					$modSettings['paid_email_to'] = $this->_req->post->paid_email_to;
					$context['error_type'] = 'minor';
					$context['settings_message'] = array();
					foreach ($validator->validation_errors() as $id => $error)
					{
						$context['settings_message'][] = $error;
					}
				}
			}

			// No errors, then save away
			if (empty($context['error_type']))
			{
				// Sort out the currency stuff.
				if ($this->_req->post->paid_currency !== 'other')
				{
					$this->_req->post->paid_currency_code = $this->_req->post->paid_currency;
					$this->_req->post->paid_currency_symbol = $txt[$this->_req->post->paid_currency . '_symbol'];
				}
				$this->_req->post->paid_currency_code = trim($this->_req->post->paid_currency_code);

				unset($config_vars['dummy_currency']);
				$settingsForm->setConfigVars($config_vars);
				$settingsForm->setConfigValues((array) $this->_req->post);
				$settingsForm->save();
				redirectexit('action=admin;area=paidsubscribe;sa=settings');
			}
		}

		// Prepare the settings...
		$settingsForm->prepare();
	}

	/**
	 * Retrieve subscriptions settings.
	 *
	 * @event integrate_modify_subscription_settings
	 */
	private function _settings()
	{
		global $modSettings, $txt;

		// If the currency is set to something different then we need to set it to other for this to work and set it back shortly.
		$modSettings['paid_currency'] = !empty($modSettings['paid_currency_code']) ? $modSettings['paid_currency_code'] : '';
		if (!empty($modSettings['paid_currency_code']) && !in_array($modSettings['paid_currency_code'], array('usd', 'eur', 'gbp')))
		{
			$modSettings['paid_currency'] = 'other';
		}

		// These are all the default settings.
		$config_vars = array(
			array('select', 'paid_email', array(0 => $txt['paid_email_no'], 1 => $txt['paid_email_error'], 2 => $txt['paid_email_all']), 'subtext' => $txt['paid_email_desc']),
			array('text', 'paid_email_to', 'subtext' => $txt['paid_email_to_desc'], 'size' => 60),
			'',
			'dummy_currency' => array('select', 'paid_currency', array('usd' => $txt['usd'], 'eur' => $txt['eur'], 'gbp' => $txt['gbp'], 'other' => $txt['other']), 'javascript' => 'onchange="toggleCurrencyOther();"'),
			array('text', 'paid_currency_code', 'subtext' => $txt['paid_currency_code_desc'], 'size' => 5, 'force_div_id' => 'custom_currency_code_div'),
			array('text', 'paid_currency_symbol', 'subtext' => $txt['paid_currency_symbol_desc'], 'size' => 8, 'force_div_id' => 'custom_currency_symbol_div'),
			array('check', 'paidsubs_test', 'subtext' => $txt['paidsubs_test_desc'], 'onclick' => 'return document.getElementById(\'paidsubs_test\').checked ? confirm(\'' . $txt['paidsubs_test_confirm'] . '\') : true;'),
		);

		// Now load all the other gateway settings.
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		$gateways = loadPaymentGateways();
		foreach ($gateways as $gateway)
		{
			$gatewayClass = new $gateway['display_class']();
			$setting_data = $gatewayClass->getGatewaySettings();
			if (!empty($setting_data))
			{
				$config_vars[] = array('title', $gatewayClass->title, 'text_label' => ($txt['paidsubs_gateway_title_' . $gatewayClass->title] ?? $gatewayClass->title));
				$config_vars = array_merge($config_vars, $setting_data);
			}
		}

		call_integration_hook('integrate_modify_subscription_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the paid sub settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * View a list of all the current subscriptions
	 *
	 * What it does:
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=view.
	 *
	 * @event integrate_list_subscription_list
	 */
	public function action_view()
	{
		global $context, $txt, $modSettings;

		// Not made the settings yet?
		if (empty($modSettings['paid_currency_symbol']))
		{
			throw new Exception('paid_not_set_currency', false, array(getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'settings'])));
		}

		// Some basic stuff.
		$context['page_title'] = $txt['paid_subs_view'];
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		loadSubscriptions();

		$listOptions = array(
			'id' => 'subscription_list',
			'title' => $txt['subscriptions'],
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'view']),
			'get_items' => array(
				'function' => function () {
					global $context;

					return $context['subscriptions'];
				},
			),
			'get_count' => array(
				'function' => function () {
					global $context;

					return count($context['subscriptions']);
				},
			),
			'no_items_label' => $txt['paid_none_yet'],
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['paid_name'],
						'style' => 'width: 30%;',
					),
					'data' => array(
						'function' => function ($rowData) {
							return sprintf('<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'viewsub', 'sid' => '%1$s']) . '">%2$s</a>', $rowData['id'], $rowData['name']);
						},
					),
				),
				'cost' => array(
					'header' => array(
						'value' => $txt['paid_cost'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return $rowData['flexible'] ? '<em>' . $txt['flexible'] . '</em>' : $rowData['cost'] . ' / ' . $rowData['length'];
						},
					),
				),
				'pending' => array(
					'header' => array(
						'value' => $txt['paid_pending'],
						'class' => 'nowrap',
					),
					'data' => array(
						'db_htmlsafe' => 'pending',
					),
				),
				'finished' => array(
					'header' => array(
						'value' => $txt['paid_finished'],
					),
					'data' => array(
						'db_htmlsafe' => 'finished',
					),
				),
				'total' => array(
					'header' => array(
						'value' => $txt['paid_active'],
					),
					'data' => array(
						'db_htmlsafe' => 'total',
					),
				),
				'is_active' => array(
					'header' => array(
						'value' => $txt['paid_is_active'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return '<span class="' . ($rowData['active'] ? 'success' : 'alert') . '">' . ($rowData['active'] ? $txt['yes'] : $txt['no']) . '</span>';
						},
					),
				),
				'subscribers' => array(
					'header' => array(
						'value' => $txt['subscribers'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'viewsub', 'sid' => $rowData['id']]) . '"><i class="icon i-view" title="' . $txt['view'] . '"></i></a>';
						},
						'class' => 'centertext',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['modify'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'modify', 'sid' => $rowData['id']]) . '"><i class="icon i-modify" title="' . $txt['modify'] . '"></i></a>';
						},
						'class' => 'centertext',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => $txt['remove']
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'modify', 'delete', 'sid' => $rowData['id']]) . '"><i class="icon i-delete" title="' . $txt['delete'] . '"></i></a>';
						},
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'modify']),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'class' => 'flow_flex_additional_row',
					'value' => '<input type="submit" name="add" value="' . $txt['paid_add_subscription'] . '" class="right_submit" />',
				),
			),
		);

		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'subscription_list';
	}

	/**
	 * Adding, editing and deleting subscriptions.
	 *
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=modify.
	 *
	 * @event integrate_delete_subscription passed ID of deletion
	 * @event integrate_save_subscription
	 */
	public function action_modify()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');

		$context['sub_id'] = isset($this->_req->query->sid) ? (int) $this->_req->query->sid : 0;
		$context['action_type'] = $context['sub_id'] ? (isset($this->_req->query->delete) ? 'delete' : 'edit') : 'add';

		// Setup the template.
		$context['sub_template'] = $context['action_type'] === 'delete' ? 'delete_subscription' : 'modify_subscription';
		$context['page_title'] = $txt['paid_' . $context['action_type'] . '_subscription'];

		// Delete it?
		if (isset($this->_req->post->delete_confirm, $this->_req->query->delete))
		{
			checkSession();
			validateToken('admin-pmsd');

			deleteSubscription($context['sub_id']);

			call_integration_hook('integrate_delete_subscription', array($context['sub_id']));

			redirectexit('action=admin;area=paidsubscribe;view');
		}

		// Saving?
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-pms');

			// Some cleaning...
			$isActive = max($this->_req->getPost('active', 'intval', 0), 1);
			$isRepeatable = max($this->_req->getPost('repeatable', 'intval', 0), 1);
			$allowPartial = max($this->_req->getPost('allow_partial', 'intval', 0), 1);
			$reminder = max($this->_req->getPost('reminder', 'intval', 0), 1);
			$emailComplete = strlen($this->_req->post->emailcomplete) > 10 ? trim($this->_req->post->emailcomplete) : '';

			// Is this a fixed one?
			if ($this->_req->post->duration_type === 'fixed')
			{
				// Clean the span.
				$span = $this->_req->post->span_value . $this->_req->post->span_unit;

				// Sort out the cost.
				$cost = array('fixed' => sprintf('%01.2f', strtr($this->_req->post->cost, ',', '.')));

				// There needs to be something.
				if (empty($this->_req->post->span_value) || empty($this->_req->post->cost))
				{
					throw new Exception('paid_no_cost_value');
				}
			}
			// Flexible is harder but more fun ;)
			else
			{
				$span = 'F';

				$cost = array(
					'day' => sprintf('%01.2f', strtr($this->_req->post->cost_day, ',', '.')),
					'week' => sprintf('%01.2f', strtr($this->_req->post->cost_week, ',', '.')),
					'month' => sprintf('%01.2f', strtr($this->_req->post->cost_month, ',', '.')),
					'year' => sprintf('%01.2f', strtr($this->_req->post->cost_year, ',', '.')),
				);

				if (empty($this->_req->post->cost_day) && empty($this->_req->post->cost_week) && empty($this->_req->post->cost_month) && empty($this->_req->post->cost_year))
				{
					throw new Exception('paid_all_freq_blank');
				}
			}

			$cost = serialize($cost);

			// Yep, time to do additional groups.
			$addGroups = array();
			if (!empty($this->_req->post->addgroup))
			{
				foreach ($this->_req->post->addgroup as $id => $dummy)
				{
					$addGroups[] = (int) $id;
				}
			}
			$addGroups = implode(',', $addGroups);

			// Is it new?!
			if ($context['action_type'] === 'add')
			{
				$insert = array(
					'name' => $this->_req->post->name,
					'desc' => $this->_req->post->desc,
					'isActive' => $isActive,
					'span' => $span,
					'cost' => $cost,
					'prim_group' => $this->_req->post->prim_group,
					'addgroups' => $addGroups,
					'isRepeatable' => $isRepeatable,
					'allowpartial' => $allowPartial,
					'emailComplete' => $emailComplete,
					'reminder' => $reminder,
				);

				$sub_id = insertSubscription($insert);
			}
			// Otherwise must be editing.
			else
			{
				$ignore_active = countActiveSubscriptions($context['sub_id']);

				$update = array(
					'is_active' => $isActive,
					'id_group' => !empty($this->_req->post->prim_group) ? $this->_req->post->prim_group : 0,
					'repeatable' => $isRepeatable,
					'allow_partial' => $allowPartial,
					'reminder' => $reminder,
					'current_subscription' => $context['sub_id'],
					'name' => $this->_req->post->name,
					'desc' => $this->_req->post->desc,
					'length' => $span,
					'cost' => $cost,
					'additional_groups' => !empty($addGroups) ? $addGroups : '',
					'email_complete' => $emailComplete,
				);

				updateSubscription($update, $ignore_active);
			}

			call_integration_hook('integrate_save_subscription', array(($context['action_type'] === 'add' ? $sub_id : $context['sub_id']), $this->_req->post->name, $this->_req->post->desc, $isActive, $span, $cost, $this->_req->post->prim_group, $addGroups, $isRepeatable, $allowPartial, $emailComplete, $reminder));

			redirectexit('action=admin;area=paidsubscribe;view');
		}

		// Defaults.
		if ($context['action_type'] === 'add')
		{
			$context['sub'] = array(
				'name' => '',
				'desc' => '',
				'cost' => array(
					'fixed' => 0,
				),
				'span' => array(
					'value' => '',
					'unit' => 'D',
				),
				'prim_group' => 0,
				'add_groups' => array(),
				'active' => 1,
				'repeatable' => 1,
				'allow_partial' => 0,
				'duration' => 'fixed',
				'email_complete' => '',
				'reminder' => 0,
			);
		}
		// Otherwise load up all the details.
		else
		{
			$context['sub'] = getSubscriptionDetails($context['sub_id']);

			// Does this have members who are active?
			$context['disable_groups'] = countActiveSubscriptions($context['sub_id']);
		}

		// Load up all the groups.
		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['groups'] = getBasicMembergroupData(array('permission'));

		// This always happens.
		createToken($context['action_type'] === 'delete' ? 'admin-pmsd' : 'admin-pms');
	}

	/**
	 * Edit or add a user subscription.
	 *
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=modifyuser
	 */
	public function action_modifyuser()
	{
		global $context, $txt, $modSettings;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		loadSubscriptions();

		$context['log_id'] = $this->_req->getQuery('lid', 'intval', 0);
		$context['sub_id'] = $this->_req->getQuery('sid', 'intval', 0);
		$context['action_type'] = $context['log_id'] ? 'edit' : 'add';

		// Setup the template.
		$context['sub_template'] = 'modify_user_subscription';
		$context['page_title'] = $txt[$context['action_type'] . '_subscriber'];
		loadJavascriptFile('suggest.js');

		// If we haven't been passed the subscription ID get it.
		if ($context['log_id'] && !$context['sub_id'])
		{
			$context['sub_id'] = validateSubscriptionID($context['log_id']);
		}

		if (!isset($context['subscriptions'][$context['sub_id']]))
		{
			throw new Exception('no_access', false);
		}

		$context['current_subscription'] = $context['subscriptions'][$context['sub_id']];

		// Searching?
		if (isset($this->_req->post->ssearch))
		{
			return $this->action_viewsub();
		}
		// Saving?
		elseif (isset($this->_req->post->save_sub))
		{
			checkSession();

			// Work out the dates...
			$starttime = mktime($this->_req->post->hour, $this->_req->post->minute, 0, $this->_req->post->month, $this->_req->post->day, $this->_req->post->year);
			$endtime = mktime($this->_req->post->hourend, $this->_req->post->minuteend, 0, $this->_req->post->monthend, $this->_req->post->dayend, $this->_req->post->yearend);

			// Status.
			$status = $this->_req->post->status;

			// New one?
			if (empty($context['log_id']))
			{
				// Find the user...
				require_once(SUBSDIR . '/Members.subs.php');
				$member = getMemberByName($this->_req->post->name);

				if (empty($member))
				{
					throw new Exception('error_member_not_found');
				}

				if (alreadySubscribed($context['sub_id'], $member['id_member']))
				{
					throw new Exception('member_already_subscribed');
				}

				// Actually put the subscription in place.
				if ($status == 1)
				{
					addSubscription($context['sub_id'], $member['id_member'], 0, $starttime, $endtime);
				}
				else
				{
					$details = array(
						'id_subscribe' => $context['sub_id'],
						'id_member' => $member['id_member'],
						'id_group' => $member['id_group'],
						'start_time' => $starttime,
						'end_time' => $endtime,
						'status' => $status,
						'pending_details' => '',
					);

					logSubscription($details);
				}
			}
			// Updating.
			else
			{
				$subscription_status = getSubscriptionStatus($context['log_id']);

				// Pick the right permission stuff depending on what the status is changing from/to.
				if ($subscription_status['old_status'] == 1 && $status != 1)
				{
					removeSubscription($context['sub_id'], $subscription_status['id_member']);
				}
				elseif ($status == 1 && $subscription_status['old_status'] != 1)
				{
					addSubscription($context['sub_id'], $subscription_status['id_member'], 0, $starttime, $endtime);
				}
				else
				{
					$item = array(
						'start_time' => $starttime,
						'end_time' => $endtime,
						'status' => $status,
						'current_log_item' => $context['log_id']
					);
					updateSubscriptionItem($item);
				}
			}

			// Done - redirect...
			redirectexit('action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id']);
		}
		// Deleting?
		elseif (isset($this->_req->post->delete) || isset($this->_req->post->finished))
		{
			checkSession();

			// Do the actual deletes!
			if (!empty($this->_req->post->delsub))
			{
				$toDelete = array();
				foreach ($this->_req->post->delsub as $id => $dummy)
				{
					$toDelete[] = (int) $id;
				}

				$deletes = prepareDeleteSubscriptions($toDelete);

				foreach ($deletes as $id_subscribe => $id_member)
				{
					removeSubscription($id_subscribe, $id_member, isset($this->_req->post->delete));
				}
			}
			redirectexit('action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id']);
		}

		// Default attributes.
		if ($context['action_type'] === 'add')
		{
			$context['sub'] = array(
				'id' => 0,
				'start' => array(
					'year' => (int) Util::strftime('%Y', time()),
					'month' => (int) Util::strftime('%m', time()),
					'day' => (int) Util::strftime('%d', time()),
					'hour' => (int) Util::strftime('%H', time()),
					'min' => (int) Util::strftime('%M', time()) < 10 ? '0' . (int) Util::strftime('%M', time()) : (int) Util::strftime('%M', time()),
					'last_day' => 0,
				),
				'end' => array(
					'year' => (int) Util::strftime('%Y', time()),
					'month' => (int) Util::strftime('%m', time()),
					'day' => (int) Util::strftime('%d', time()),
					'hour' => (int) Util::strftime('%H', time()),
					'min' => (int) Util::strftime('%M', time()) < 10 ? '0' . (int) Util::strftime('%M', time()) : (int) Util::strftime('%M', time()),
					'last_day' => 0,
				),
				'status' => 1,
			);
			$context['sub']['start']['last_day'] = (int) Util::strftime('%d', mktime(0, 0, 0, $context['sub']['start']['month'] == 12 ? 1 : $context['sub']['start']['month'] + 1, 0, $context['sub']['start']['month'] == 12 ? $context['sub']['start']['year'] + 1 : $context['sub']['start']['year']));
			$context['sub']['end']['last_day'] = (int) Util::strftime('%d', mktime(0, 0, 0, $context['sub']['end']['month'] == 12 ? 1 : $context['sub']['end']['month'] + 1, 0, $context['sub']['end']['month'] == 12 ? $context['sub']['end']['year'] + 1 : $context['sub']['end']['year']));

			if (isset($this->_req->query->uid))
			{
				require_once(SUBSDIR . '/Members.subs.php');

				// Get the latest activated member's display name.
				$result = getBasicMemberData((int) $this->_req->query->uid);
				$context['sub']['username'] = $result['real_name'];
			}
			else
			{
				$context['sub']['username'] = '';
			}
		}
		// Otherwise load the existing info.
		else
		{
			$row = getPendingSubscriptions($context['log_id']);
			if (empty($row))
			{
				throw new Exception('no_access', false);
			}

			// Any pending payments?
			$context['pending_payments'] = array();
			if (!empty($row['pending_details']))
			{
				$pending_details = Util::unserialize($row['pending_details']);
				foreach ($pending_details as $id => $pending)
				{
					// Only this type need be displayed.
					if ($pending[3] === 'payback')
					{
						// Work out what the options were.
						$costs = Util::unserialize($context['current_subscription']['real_cost']);

						if ($context['current_subscription']['real_length'] === 'F')
						{
							foreach ($costs as $duration => $cost)
							{
								if ($cost != 0 && $cost == $pending[1] && $duration == $pending[2])
								{
									$context['pending_payments'][$id] = array(
										'desc' => sprintf($modSettings['paid_currency_symbol'], $cost . '/' . $txt[$duration]),
									);
								}
							}
						}
						elseif ($costs['fixed'] == $pending[1])
						{
							$context['pending_payments'][$id] = array(
								'desc' => sprintf($modSettings['paid_currency_symbol'], $costs['fixed']),
							);
						}
					}
				}

				// Check if we are adding/removing any.
				if (isset($this->_req->query->pending))
				{
					foreach ($pending_details as $id => $pending)
					{
						// Found the one to action?
						if ($this->_req->query->pending == $id && $pending[3] === 'payback' && isset($context['pending_payments'][$id]))
						{
							// Flexible?
							if (isset($this->_req->query->accept))
							{
								addSubscription($context['current_subscription']['id'], $row['id_member'], $context['current_subscription']['real_length'] === 'F' ? strtoupper(substr($pending[2], 0, 1)) : 0);
							}
							unset($pending_details[$id]);

							$new_details = serialize($pending_details);

							// Update the entry.
							updatePendingSubscription($context['log_id'], $new_details);

							// Reload
							redirectexit('action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $context['log_id']);
						}
					}
				}
			}

			$context['sub_id'] = $row['id_subscribe'];
			$context['sub'] = array(
				'id' => 0,
				'start' => array(
					'year' => (int) Util::strftime('%Y', $row['start_time']),
					'month' => (int) Util::strftime('%m', $row['start_time']),
					'day' => (int) Util::strftime('%d', $row['start_time']),
					'hour' => (int) Util::strftime('%H', $row['start_time']),
					'min' => (int) Util::strftime('%M', $row['start_time']) < 10 ? '0' . (int) Util::strftime('%M', $row['start_time']) : (int) Util::strftime('%M', $row['start_time']),
					'last_day' => 0,
				),
				'end' => array(
					'year' => (int) Util::strftime('%Y', $row['end_time']),
					'month' => (int) Util::strftime('%m', $row['end_time']),
					'day' => (int) Util::strftime('%d', $row['end_time']),
					'hour' => (int) Util::strftime('%H', $row['end_time']),
					'min' => (int) Util::strftime('%M', $row['end_time']) < 10 ? '0' . (int) Util::strftime('%M', $row['end_time']) : (int) Util::strftime('%M', $row['end_time']),
					'last_day' => 0,
				),
				'status' => $row['status'],
				'username' => $row['username'],
			);

			$context['sub']['start']['last_day'] = (int) Util::strftime('%d', mktime(0, 0, 0, $context['sub']['start']['month'] == 12 ? 1 : $context['sub']['start']['month'] + 1, 0, $context['sub']['start']['month'] == 12 ? $context['sub']['start']['year'] + 1 : $context['sub']['start']['year']));
			$context['sub']['end']['last_day'] = (int) Util::strftime('%d', mktime(0, 0, 0, $context['sub']['end']['month'] == 12 ? 1 : $context['sub']['end']['month'] + 1, 0, $context['sub']['end']['month'] == 12 ? $context['sub']['end']['year'] + 1 : $context['sub']['end']['year']));
		}
	}

	/**
	 * View all the users subscribed to a particular subscription.
	 *
	 * What it does:
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=viewsub.
	 * - Subscription ID is required, in the form of $_GET['sid'].
	 *
	 * @event integrate_list_subscribed_users_list
	 */
	public function action_viewsub()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');

		// Setup the template.
		$context['page_title'] = $txt['viewing_users_subscribed'];

		// ID of the subscription.
		$context['sub_id'] = (int) $this->_req->query->sid;

		// Load the subscription information.
		$context['subscription'] = getSubscriptionDetails($context['sub_id']);

		// Are we searching for people?
		$search_string = isset($this->_req->post->ssearch) && !empty($this->_req->post->sub_search) ? ' AND COALESCE(mem.real_name, {string:guest}) LIKE {string:search}' : '';
		$search_vars = empty($this->_req->post->sub_search) ? array() : array('search' => '%' . $this->_req->post->sub_search . '%', 'guest' => $txt['guest']);

		$listOptions = array(
			'id' => 'subscribed_users_list',
			'title' => sprintf($txt['view_users_subscribed'], $context['subscription']['name']),
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'viewsub', 'sid' => $context['sub_id']]),
			'default_sort_col' => 'name',
			'get_items' => array(
				'function' => function ($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars) {
					return $this->getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars);
				},
				'params' => array(
					$context['sub_id'],
					$search_string,
					$search_vars,
				),
			),
			'get_count' => array(
				'function' => function ($id_sub, $search_string, $search_vars) {
					return $this->getSubscribedUserCount($id_sub, $search_string, $search_vars);
				},
				'params' => array(
					$context['sub_id'],
					$search_string,
					$search_vars,
				),
			),
			'no_items_label' => $txt['no_subscribers'],
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['who_member'],
						'style' => 'width: 20%;',
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return $rowData['id_member'] == 0 ? $txt['guest'] : '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $rowData['id_member'], 'name' =>  $rowData['name']]) . '">' . $rowData['name'] . '</a>';
						},
					),
					'sort' => array(
						'default' => 'name',
						'reverse' => 'name DESC',
					),
				),
				'status' => array(
					'header' => array(
						'value' => $txt['paid_status'],
						'style' => 'width: 10%;',
					),
					'data' => array(
						'db_htmlsafe' => 'status_text',
					),
					'sort' => array(
						'default' => 'status',
						'reverse' => 'status DESC',
					),
				),
				'payments_pending' => array(
					'header' => array(
						'value' => $txt['paid_payments_pending'],
						'style' => 'width: 15%;',
					),
					'data' => array(
						'db_htmlsafe' => 'pending',
					),
					'sort' => array(
						'default' => 'payments_pending',
						'reverse' => 'payments_pending DESC',
					),
				),
				'start_time' => array(
					'header' => array(
						'value' => $txt['start_date'],
						'style' => 'width: 20%;',
					),
					'data' => array(
						'db_htmlsafe' => 'start_date',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'start_time',
						'reverse' => 'start_time DESC',
					),
				),
				'end_time' => array(
					'header' => array(
						'value' => $txt['end_date'],
						'style' => 'width: 20%;',
					),
					'data' => array(
						'db_htmlsafe' => 'end_date',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'end_time',
						'reverse' => 'end_time DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'style' => 'width: 10%;',
						'class' => 'nowrap',
						'value' => $txt['edit_subscriber'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							return '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => '=modifyuser', 'lid' => $rowData['id']]) . '">' . $txt['modify'] . '</a>';
						},
						'class' => 'centertext',
					),
				),
				'delete' => array(
					'header' => array(
						'style' => 'width: 4%;',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => function ($rowData) {
							return '<input type="checkbox" name="delsub[' . $rowData['id'] . ']" class="input_check" />';
						},
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' =>getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe', 'sa' => 'modifyuser', 'sid' => $context['sub_id']]),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="add" value="' . $txt['add_subscriber'] . '" class="right_submit" />
						<input type="submit" name="finished" value="' . $txt['complete_selected'] . '" onclick="return confirm(\'' . $txt['complete_are_sure'] . '\');" class="right_submit" />
						<input type="submit" name="delete" value="' . $txt['delete_selected'] . '" onclick="return confirm(\'' . $txt['delete_are_sure'] . '\');" class="right_submit" />
					',
				),
				array(
					'position' => 'top_of_list',
					'value' => '
						<div class="flow_auto">
							<input type="submit" name="ssearch" value="' . $txt['search_sub'] . '" class="right_submit" />
							<input type="text" name="sub_search" value="" class="input_text floatright" />
						</div>
					',
				),
			),
		);

		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'subscribed_users_list';

		return true;
	}

	/**
	 * Returns an array of subscription details and members for a specific subscription
	 *
	 * - Callback for createList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param int $id_sub
	 * @param string $search_string
	 * @param mixed[] $search_vars
	 *
	 * @return array
	 */
	public function getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars)
	{
		return list_getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars);
	}

	/**
	 * Returns the number of subscribers to a specific subscription in the system
	 *
	 * - Callback for createList()
	 *
	 * @param int $id_sub
	 * @param string $search_string
	 * @param mixed[] $search_vars
	 *
	 * @return int
	 */
	public function getSubscribedUserCount($id_sub, $search_string, $search_vars)
	{
		return list_getSubscribedUserCount($id_sub, $search_string, $search_vars);
	}
}
