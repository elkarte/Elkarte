<?php

/**
 * This file contains all the administration functions for subscriptions.
 * (and some more than that :P)
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
 * @version 1.0.8
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManagePaid controller, administration controller for paid subscriptions.
 *
 * @package Subscriptions
 */
class ManagePaid_Controller extends Action_Controller
{
	/**
	 * Paid subscriptions settings form.
	 * @var Settings_Form
	 */
	protected $_paidSettings;

	/**
	 * The main entrance point for the 'Paid Subscription' screen,
	 *
	 * What it does:
	 * - calling the right function based on the given sub-action.
	 * - It defaults to sub-action 'view'.
	 * - Accessed from ?action=admin;area=paidsubscribe.
	 * - It requires admin_forum permission for admin based actions.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings;

		// Load the required language and template.
		loadLanguage('ManagePaid');
		loadTemplate('ManagePaid');

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

		// Default the sub-action to 'view subscriptions', but only if they have already set things up..
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (!empty($modSettings['paid_currency_symbol']) ? 'view' : 'settings');

		// Load in the subActions, call integrate_sa_manage_subscriptions
		$action->initialize($subActions, 'settings');

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
	 */
	public function action_paidSettings_display()
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');

		// Initialize the form
		$this->_init_paidSettingsForm();

		$config_vars = $this->_paidSettings->settings();

		// Now load all the other gateway settings.
		$gateways = loadPaymentGateways();
		foreach ($gateways as $gateway)
		{
			$gatewayClass = new $gateway['display_class']();
			$setting_data = $gatewayClass->getGatewaySettings();
			if (!empty($setting_data))
			{
				$config_vars[] = array('title', $gatewayClass->title, 'text_label' => (isset($txt['paidsubs_gateway_title_' . $gatewayClass->title]) ? $txt['paidsubs_gateway_title_' . $gatewayClass->title] : $gatewayClass->title));
				$config_vars = array_merge($config_vars, $setting_data);
			}
		}

		// Some important context stuff
		$context['page_title'] = $txt['settings'];
		$context['sub_template'] = 'show_settings';
		$context['settings_message'] = replaceBasicActionUrl($txt['paid_note']);
		$context[$context['admin_menu_name']]['current_subsection'] = 'settings';

		// Get the final touches in place.
		$context['post_url'] = $scripturl . '?action=admin;area=paidsubscribe;save;sa=settings';
		$context['settings_title'] = $txt['settings'];

		// We want javascript for our currency options.
		addInlineJavascript('
		toggleCurrencyOther();', true);

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession();

			call_integration_hook('integrate_save_subscription_settings');

			// Check that the entered email addresses are valid
			if (!empty($_POST['paid_email_to']))
			{
				require_once(SUBSDIR . '/DataValidator.class.php');
				$validator = new Data_Validator();

				// Some cleaning and some rules
				$validator->sanitation_rules(array('paid_email_to' => 'trim'));
				$validator->validation_rules(array('paid_email_to' => 'valid_email'));
				$validator->input_processing(array('paid_email_to' => 'csv'));
				$validator->text_replacements(array('paid_email_to' => $txt['paid_email_to']));

				if ($validator->validate($_POST))
					$_POST['paid_email_to'] = $validator->paid_email_to;
				else
				{
					// Thats not an email, lets set it back in the form to be fixed and let them know its wrong
					$config_vars[1]['value'] = $_POST['paid_email_to'];
					$context['error_type'] = 'minor';
					$context['settings_message'] = array();
					foreach ($validator->validation_errors() as $id => $error)
						$context['settings_message'][] = $error;
				}
			}

			// No errors, then save away
			if (empty($context['error_type']))
			{
				// Sort out the currency stuff.
				if ($_POST['paid_currency'] != 'other')
				{
					$_POST['paid_currency_code'] = $_POST['paid_currency'];
					$_POST['paid_currency_symbol'] = $txt[$_POST['paid_currency'] . '_symbol'];
				}
				$_POST['paid_currency_code'] = trim($_POST['paid_currency_code']);

				unset($config_vars['dummy_currency']);
				Settings_Form::save_db($config_vars);
				redirectexit('action=admin;area=paidsubscribe;sa=settings');
			}
		}

		// Prepare the settings...
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieve subscriptions settings and initialize the form.
	 */
	private function _init_paidSettingsForm()
	{
		// We're working with them settings here.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		// Instantiate the form
		$this->_paidSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_paidSettings->settings($config_vars);
	}

	/**
	 * Retrieve subscriptions settings.
	 */
	private function _settings()
	{
		global $modSettings, $txt;

		// If the currency is set to something different then we need to set it to other for this to work and set it back shortly.
		$modSettings['paid_currency'] = !empty($modSettings['paid_currency_code']) ? $modSettings['paid_currency_code'] : '';
		if (!empty($modSettings['paid_currency_code']) && !in_array($modSettings['paid_currency_code'], array('usd', 'eur', 'gbp')))
			$modSettings['paid_currency'] = 'other';

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
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=view.
	 */
	public function action_view()
	{
		global $context, $txt, $modSettings, $scripturl;

		// Not made the settings yet?
		if (empty($modSettings['paid_currency_symbol']))
			fatal_lang_error('paid_not_set_currency', false, array($scripturl . '?action=admin;area=paidsubscribe;sa=settings'));

		// Some basic stuff.
		$context['page_title'] = $txt['paid_subs_view'];
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		loadSubscriptions();

		$listOptions = array(
			'id' => 'subscription_list',
			'title' => $txt['subscriptions'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=paidsubscribe;sa=view',
			'get_items' => array(
				'function' => create_function('', '
					global $context;
					return $context[\'subscriptions\'];
				'),
			),
			'get_count' => array(
				'function' => create_function('', '
					global $context;
					return count($context[\'subscriptions\']);
				'),
			),
			'no_items_label' => $txt['paid_none_yet'],
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['paid_name'],
						'style' => 'width: 30%;',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl;

							return sprintf(\'<a href="%1$s?action=admin;area=paidsubscribe;sa=viewsub;sid=%2$s">%3$s</a>\', $scripturl, $rowData[\'id\'], $rowData[\'name\']);
						'),
					),
				),
				'cost' => array(
					'header' => array(
						'value' => $txt['paid_cost'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							return $rowData[\'flexible\'] ? \'<em>\' . $txt[\'flexible\'] . \'</em>\' : $rowData[\'cost\'] . \' / \' . $rowData[\'length\'];
						'),
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
						'function' => create_function('$rowData', '
							global $txt;

							return \'<span class="\' . ($rowData[\'active\'] ? \'success\' : \'alert\') . \'">\' . ($rowData[\'active\'] ? $txt[\'yes\'] : $txt[\'no\']) . \'</span>\';
						'),
					),
				),
				'subscribers' => array(
					'header' => array(
						'value' => $txt['subscribers'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl, $txt, $settings;

							return \'<a href="\' . $scripturl . \'?action=admin;area=paidsubscribe;sa=viewsub;sid=\' . $rowData[\'id\'] . \'"><img title="\' . $txt[\'view\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/members.png" alt="*" /></a>\';
						'),
						'class' => 'centertext',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['modify'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt, $scripturl, $settings;

							return \'<a href="\' . $scripturl . \'?action=admin;area=paidsubscribe;sa=modify;sid=\' . $rowData[\'id\'] . \'"><img title="\' . $txt[\'modify\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/modify_inline.png" alt="*" /></a>\';
						'),
						'class' => 'centertext',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => $txt['remove']
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt, $scripturl, $settings;

							return \'<a href="\' . $scripturl . \'?action=admin;area=paidsubscribe;sa=modify;delete;sid=\' . $rowData[\'id\'] . \'"><img title="\' . $txt[\'delete\'] . \'" src="\' . $settings[\'images_url\'] . \'/icons/delete.png" alt="*" /></a>\';
						'),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=paidsubscribe;sa=modify',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="add" value="' . $txt['paid_add_subscription'] . '" class="right_submit" />',
				),
			),
		);

		require_once(SUBSDIR . '/GenericList.class.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'subscription_list';
	}

	/**
	 * Adding, editing and deleting subscriptions.
	 *
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=modify.
	 */
	public function action_modify()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');

		$context['sub_id'] = isset($_REQUEST['sid']) ? (int) $_REQUEST['sid'] : 0;
		$context['action_type'] = $context['sub_id'] ? (isset($_REQUEST['delete']) ? 'delete' : 'edit') : 'add';

		// Setup the template.
		$context['sub_template'] = $context['action_type'] == 'delete' ? 'delete_subscription' : 'modify_subscription';
		$context['page_title'] = $txt['paid_' . $context['action_type'] . '_subscription'];

		// Delete it?
		if (isset($_POST['delete_confirm']) && isset($_REQUEST['delete']))
		{
			checkSession();
			validateToken('admin-pmsd');

			deleteSubscription($context['sub_id']);

			call_integration_hook('integrate_delete_subscription', array($context['sub_id']));

			redirectexit('action=admin;area=paidsubscribe;view');
		}

		// Saving?
		if (isset($_POST['save']))
		{
			// A little checking first
			checkSession();
			validateToken('admin-pms');

			// Lets try and save
			$this->action_modify2();
		}

		// If adding a new subscription set some defaults.
		if ($context['action_type'] == 'add')
		{
			$context['sub'] = array(
				'name' => isset($_POST['name']) ? $_POST['name'] : '',
				'desc' => isset($_POST['desc']) ? $_POST['desc'] : '',
				'cost' => array(
					'fixed' => isset($_POST['cost']) ? $_POST['cost'] : 0,
				),
				'span' => array(
					'value' => isset($_POST['span_value']) ? $_POST['span_value'] : '',
					'unit' => isset($_POST['span_unit']) ? $_POST['span_unit'] : 'D',
				),
				'prim_group' => 0,
				'add_groups' => array(),
				'active' => isset($_POST['active']) ? $_POST['active'] : 1,
				'repeatable' => isset($_POST['repeatable']) ? $_POST['repeatable'] : 1,
				'allow_partial' => isset($_POST['allow_partial']) ? $_POST['allow_partial'] : 0,
				'duration' => 'fixed',
				'email_complete' => isset($_POST['emailcomplete']) ? $_POST['emailcomplete'] : '',
				'reminder' => isset($_POST['reminder']) ? $_POST['reminder'] : 0,
			);
		}
		// Otherwise load up all the details of the existing one
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
		createToken($context['action_type'] == 'delete' ? 'admin-pmsd' : 'admin-pms');
	}

	/**
	 * Validates the post data for saving a subscription
	 * If everything checks out will save or update as needed
	 * If any issues are found with the form will create the error txt to
	 * display and send them back to the form to try again
	 */
	private function action_modify2()
	{
		global $context;

		// Use some standard validation functions in a few spots
		require_once(SUBSDIR . '/DataValidator.class.php');

		// No errors, yet.
		$subscription_errors = Error_Context::context('subscription', 0);

		// Some cleaning...
		$isActive = isset($_POST['active']) ? 1 : 0;
		$isRepeatable = isset($_POST['repeatable']) ? 1 : 0;
		$allowpartial = isset($_POST['allow_partial']) ? 1 : 0;
		$reminder = isset($_POST['reminder']) ? (int) $_POST['reminder'] : 0;
		$emailComplete = strlen($_POST['emailcomplete']) > 10 ? trim($_POST['emailcomplete']) : '';

		// Basic check
		if (!Data_Validator::is_valid($_POST, array('name' => 'required', 'desc' => 'required'), array('name' => 'trim', 'desc' => 'trim')))
			$subscription_errors->addError('paid_no_desc');

		// Is this a fixed duration payment?
		if ($_POST['duration_type'] === 'fixed')
		{
			// There needs to be something.
			if (empty($_POST['cost']))
				$subscription_errors->addError('paid_no_cost_value');

			if (empty($_POST['span_value']))
				$subscription_errors->addError('paid_invalid_duration');

			if (!Data_Validator::is_valid($_POST, array('span_unit' => 'required|contains[D,W,M,Y]', 'span_value' => 'required|limits[1,]'), array('span_unit' => 'trim|strtoupper')))
				$subscription_errors->addError('paid_invalid_duration');

			// No errors, set the value
			if (!$subscription_errors->hasErrors())
			{
				// Clean the span.
				$span = $_POST['span_value'] . $_POST['span_unit'];

				// Sort out the cost.
				$cost = array('fixed' => sprintf('%01.2f', strtr($_POST['cost'], ',', '.')));
			}
		}
		// Flexible is harder but more fun ;)
		else
		{
			$span = 'F';

			if (empty($_POST['cost_day']) && empty($_POST['cost_week']) && empty($_POST['cost_month']) && empty($_POST['cost_year']))
				$subscription_errors->addError('paid_all_freq_blank');
			else
				$cost = array(
					'day' => sprintf('%01.2f', strtr($_POST['cost_day'], ',', '.')),
					'week' => sprintf('%01.2f', strtr($_POST['cost_week'], ',', '.')),
					'month' => sprintf('%01.2f', strtr($_POST['cost_month'], ',', '.')),
					'year' => sprintf('%01.2f', strtr($_POST['cost_year'], ',', '.')),
				);
		}

		// One or more errors, go back to the start
		if ($subscription_errors->hasErrors())
		{
			unset ($_POST['save']);
			createToken('admin-pms');

			// Set the errors so the form can show them
			$context['subscription_error'] = array(
				'errors' => $subscription_errors->prepareErrors(),
				'type' => $subscription_errors->getErrorType() == 0 ? 'minor' : 'serious',
			);

			return $this->action_modify();
		}

		$cost = serialize($cost);

		// Yep, time to do additional groups.
		$addgroups = array();
		if (!empty($_POST['addgroup']))
			foreach ($_POST['addgroup'] as $id => $dummy)
				$addgroups[] = (int) $id;
		$addgroups = implode(',', $addgroups);

		// Is it new?!
		if ($context['action_type'] == 'add')
		{
			$insert = array(
				'name' => $_POST['name'],
				'desc' => $_POST['desc'],
				'isActive' => $isActive,
				'span' => $span,
				'cost' => $cost,
				'prim_group' => $_POST['prim_group'],
				'addgroups' => $addgroups,
				'isRepeatable' => $isRepeatable,
				'allowpartial' => $allowpartial,
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
				'id_group' => !empty($_POST['prim_group']) ? $_POST['prim_group'] : 0,
				'repeatable' => $isRepeatable,
				'allow_partial' => $allowpartial,
				'reminder' => $reminder,
				'current_subscription' => $context['sub_id'],
				'name' => $_POST['name'],
				'desc' => $_POST['desc'],
				'length' => $span,
				'cost' => $cost,
				'additional_groups' => !empty($addgroups) ? $addgroups : '',
				'email_complete' => $emailComplete,
			);

			updateSubscription($update, $ignore_active);
		}

		call_integration_hook('integrate_save_subscription', array(($context['action_type'] == 'add' ? $sub_id : $context['sub_id']), $_POST['name'], $_POST['desc'], $isActive, $span, $cost, $_POST['prim_group'], $addgroups, $isRepeatable, $allowpartial, $emailComplete, $reminder));

		redirectexit('action=admin;area=paidsubscribe;view');
	}

	/**
	 * View all the users subscribed to a particular subscription.
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=paidsubscribe;sa=viewsub.
	 * - Subscription ID is required, in the form of $_GET['sid'].
	 */
	public function action_viewsub()
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');

		// Setup the template.
		$context['page_title'] = $txt['viewing_users_subscribed'];

		// ID of the subscription.
		$context['sub_id'] = (int) $_REQUEST['sid'];

		// Load the subscription information.
		$context['subscription'] = getSubscriptionDetails($context['sub_id']);

		// Are we searching for people?
		$search_string = isset($_POST['ssearch']) && !empty($_POST['sub_search']) ? ' AND IFNULL(mem.real_name, {string:guest}) LIKE {string:search}' : '';
		$search_vars = empty($_POST['sub_search']) ? array() : array('search' => '%' . $_POST['sub_search'] . '%', 'guest' => $txt['guest']);

		$listOptions = array(
			'id' => 'subscribed_users_list',
			'title' => sprintf($txt['view_users_subscribed'], $context['subscription']['name']),
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id'],
			'default_sort_col' => 'name',
			'get_items' => array(
				'function' => array($this, 'getSubscribedUsers'),
				'params' => array(
					$context['sub_id'],
					$search_string,
					$search_vars,
				),
			),
			'get_count' => array(
				'function' => array($this, 'getSubscribedUserCount'),
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
						'function' => create_function('$rowData', '
							global $txt, $scripturl;

							return $rowData[\'id_member\'] == 0 ? $txt[\'guest\'] : \'<a href="\' . $scripturl . \'?action=profile;u=\' . $rowData[\'id_member\'] . \'">\' . $rowData[\'name\'] . \'</a>\';
						'),
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
						'function' => create_function('$rowData', '
							global $txt, $scripturl;

							return \'<a href="\' . $scripturl . \'?action=admin;area=paidsubscribe;sa=modifyuser;lid=\' . $rowData[\'id\'] . \'">\' . $txt[\'modify\'] . \'</a>\';
						'),
						'class' => 'centertext',
					),
				),
				'delete' => array(
					'header' => array(
						'style' => 'width: 4%;',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return \'<input type="checkbox" name="delsub[\' . $rowData[\'id\'] . \']" class="input_check" />\';
						'),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;sid=' . $context['sub_id'],
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

		require_once(SUBSDIR . '/GenericList.class.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'subscribed_users_list';
	}

	/**
	 * Returns the number of subscribers to a specific subscription in the system
	 *
	 * - Callback for createList()
	 *
	 * @param int $id_sub
	 * @param string $search_string
	 * @param mixed[] $search_vars
	 */
	public function getSubscribedUserCount($id_sub, $search_string, $search_vars)
	{
	   return list_getSubscribedUserCount($id_sub, $search_string, $search_vars);
	}

	/**
	 * Returns an array of subscription details and members for a specific subscription
	 *
	 * - Callback for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $id_sub
	 * @param string $search_string
	 * @param mixed[] $search_vars
	 */
	public function getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars)
	{
		return list_getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars);
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

		$context['log_id'] = isset($_REQUEST['lid']) ? (int) $_REQUEST['lid'] : 0;
		$context['sub_id'] = isset($_REQUEST['sid']) ? (int) $_REQUEST['sid'] : 0;
		$context['action_type'] = $context['log_id'] ? 'edit' : 'add';

		// Setup the template.
		$context['sub_template'] = 'modify_user_subscription';
		$context['page_title'] = $txt[$context['action_type'] . '_subscriber'];
		loadJavascriptFile('suggest.js', array('defer' => true));

		// If we haven't been passed the subscription ID get it.
		if ($context['log_id'] && !$context['sub_id'])
			$context['sub_id'] = validateSubscriptionID($context['log_id']);

		if (!isset($context['subscriptions'][$context['sub_id']]))
			fatal_lang_error('no_access', false);

		$context['current_subscription'] = $context['subscriptions'][$context['sub_id']];

		// Searching?
		if (isset($_POST['ssearch']))
			return $this->action_viewsub();
		// Saving?
		elseif (isset($_REQUEST['save_sub']))
		{
			checkSession();

			// Work out the dates...
			$starttime = mktime($_POST['hour'], $_POST['minute'], 0, $_POST['month'], $_POST['day'], $_POST['year']);
			$endtime = mktime($_POST['hourend'], $_POST['minuteend'], 0, $_POST['monthend'], $_POST['dayend'], $_POST['yearend']);

			// Status.
			$status = $_POST['status'];

			// New one?
			if (empty($context['log_id']))
			{
				// Find the user...
				require_once(SUBSDIR . '/Members.subs.php');
				$member = getMemberByName($_POST['name']);

				if (empty($member))
					fatal_lang_error('error_member_not_found');

				if (alreadySubscribed($context['sub_id'], $member['id_member']))
					fatal_lang_error('member_already_subscribed');

				// Actually put the subscription in place.
				if ($status == 1)
					addSubscription($context['sub_id'], $member['id_member'], 0, $starttime, $endtime);
				else
				{
					$details = array(
						'id_subscribe' => $context['sub_id'],
						'id_member' => $member['id_member'],
						'id_group' => $member['id_group'],
						'start_time' => $starttime,
						'end_time' => $endtime,
						'status' => $status,
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
					removeSubscription($context['sub_id'], $subscription_status['id_member']);

				elseif ($status == 1 && $subscription_status['old_status'] != 1)
					addSubscription($context['sub_id'], $subscription_status['id_member'], 0, $starttime, $endtime);

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
		elseif (isset($_REQUEST['delete']) || isset($_REQUEST['finished']))
		{
			checkSession();

			// Do the actual deletes!
			if (!empty($_REQUEST['delsub']))
			{
				$toDelete = array();
				foreach ($_REQUEST['delsub'] as $id => $dummy)
					$toDelete[] = (int) $id;

				$deletes = prepareDeleteSubscriptions($toDelete);

				foreach ($deletes as $id_subscribe => $id_member)
					removeSubscription($id_subscribe, $id_member, isset($_REQUEST['delete']));
			}
			redirectexit('action=admin;area=paidsubscribe;sa=viewsub;sid=' . $context['sub_id']);
		}

		// Default attributes.
		if ($context['action_type'] == 'add')
		{
			$context['sub'] = array(
				'id' => 0,
				'start' => array(
					'year' => (int) strftime('%Y', time()),
					'month' => (int) strftime('%m', time()),
					'day' => (int) strftime('%d', time()),
					'hour' => (int) strftime('%H', time()),
					'min' => (int) strftime('%M', time()) < 10 ? '0' . (int) strftime('%M', time()) : (int) strftime('%M', time()),
					'last_day' => 0,
				),
				'end' => array(
					'year' => (int) strftime('%Y', time()),
					'month' => (int) strftime('%m', time()),
					'day' => (int) strftime('%d', time()),
					'hour' => (int) strftime('%H', time()),
					'min' => (int) strftime('%M', time()) < 10 ? '0' . (int) strftime('%M', time()) : (int) strftime('%M', time()),
					'last_day' => 0,
				),
				'status' => 1,
			);
			$context['sub']['start']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['start']['month'] == 12 ? 1 : $context['sub']['start']['month'] + 1, 0, $context['sub']['start']['month'] == 12 ? $context['sub']['start']['year'] + 1 : $context['sub']['start']['year']));
			$context['sub']['end']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['end']['month'] == 12 ? 1 : $context['sub']['end']['month'] + 1, 0, $context['sub']['end']['month'] == 12 ? $context['sub']['end']['year'] + 1 : $context['sub']['end']['year']));

			if (isset($_GET['uid']))
			{
				require_once(SUBSDIR . '/Members.subs.php');

				// Get the latest activated member's display name.
				$result = getBasicMemberData((int) $_GET['uid']);
				$context['sub']['username'] = $result['real_name'];
			}
			else
				$context['sub']['username'] = '';
		}
		// Otherwise load the existing info.
		else
		{
			$row = getPendingSubscriptions($context['log_id']);
			if (empty($row))
				fatal_lang_error('no_access', false);

			// Any pending payments?
			$context['pending_payments'] = array();
			if (!empty($row['pending_details']))
			{
				$pending_details = Util::unserialize($row['pending_details']);
				foreach ($pending_details as $id => $pending)
				{
					// Only this type need be displayed.
					if ($pending[3] == 'payback')
					{
						// Work out what the options were.
						$costs = Util::unserialize($context['current_subscription']['real_cost']);

						if ($context['current_subscription']['real_length'] == 'F')
						{
							foreach ($costs as $duration => $cost)
							{
								if ($cost != 0 && $cost == $pending[1] && $duration == $pending[2])
									$context['pending_payments'][$id] = array(
										'desc' => sprintf($modSettings['paid_currency_symbol'], $cost . '/' . $txt[$duration]),
									);
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
				if (isset($_GET['pending']))
				{
					foreach ($pending_details as $id => $pending)
					{
						// Found the one to action?
						if ($_GET['pending'] == $id && $pending[3] == 'payback' && isset($context['pending_payments'][$id]))
						{
							// Flexible?
							if (isset($_GET['accept']))
								addSubscription($context['current_subscription']['id'], $row['id_member'], $context['current_subscription']['real_length'] == 'F' ? strtoupper(substr($pending[2], 0, 1)) : 0);
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
					'year' => (int) strftime('%Y', $row['start_time']),
					'month' => (int) strftime('%m', $row['start_time']),
					'day' => (int) strftime('%d', $row['start_time']),
					'hour' => (int) strftime('%H', $row['start_time']),
					'min' => (int) strftime('%M', $row['start_time']) < 10 ? '0' . (int) strftime('%M', $row['start_time']) : (int) strftime('%M', $row['start_time']),
					'last_day' => 0,
				),
				'end' => array(
					'year' => (int) strftime('%Y', $row['end_time']),
					'month' => (int) strftime('%m', $row['end_time']),
					'day' => (int) strftime('%d', $row['end_time']),
					'hour' => (int) strftime('%H', $row['end_time']),
					'min' => (int) strftime('%M', $row['end_time']) < 10 ? '0' . (int) strftime('%M', $row['end_time']) : (int) strftime('%M', $row['end_time']),
					'last_day' => 0,
				),
				'status' => $row['status'],
				'username' => $row['username'],
			);
			$context['sub']['start']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['start']['month'] == 12 ? 1 : $context['sub']['start']['month'] + 1, 0, $context['sub']['start']['month'] == 12 ? $context['sub']['start']['year'] + 1 : $context['sub']['start']['year']));
			$context['sub']['end']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['sub']['end']['month'] == 12 ? 1 : $context['sub']['end']['month'] + 1, 0, $context['sub']['end']['month'] == 12 ? $context['sub']['end']['year'] + 1 : $context['sub']['end']['year']));
		}
	}
}