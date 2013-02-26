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
 * This file handles subscriptions on a user's profile.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Function for doing all the paid subscription stuff - kinda.
 *
 * @param int $memID
 */
function action_subscriptions($memID)
{
	global $context, $txt, $modSettings, $smcFunc, $scripturl;

	// Load the paid template anyway.
	loadTemplate('ManagePaid');
	loadLanguage('ManagePaid');

	// Load all of the subscriptions.
	require_once(ADMINDIR . '/ManagePaid.php');
	loadSubscriptions();
	$context['member']['id'] = $memID;

	// Remove any invalid ones.
	foreach ($context['subscriptions'] as $id => $sub)
	{
		// Work out the costs.
		$costs = @unserialize($sub['real_cost']);

		$cost_array = array();
		if ($sub['real_length'] == 'F')
		{
			foreach ($costs as $duration => $cost)
			{
				if ($cost != 0)
					$cost_array[$duration] = $cost;
			}
		}
		else
		{
			$cost_array['fixed'] = $costs['fixed'];
		}

		if (empty($cost_array))
			unset($context['subscriptions'][$id]);
		else
		{
			$context['subscriptions'][$id]['member'] = 0;
			$context['subscriptions'][$id]['subscribed'] = false;
			$context['subscriptions'][$id]['costs'] = $cost_array;
		}
	}

	// Work out what gateways are enabled.
	$gateways = loadPaymentGateways();
	foreach ($gateways as $id => $gateway)
	{
		$gateways[$id] = new $gateway['display_class']();
		if (!$gateways[$id]->gatewayEnabled())
			unset($gateways[$id]);
	}

	// No gateways yet?
	if (empty($gateways))
		fatal_error($txt['paid_admin_not_setup_gateway']);

	// Get the current subscriptions.
	$request = $smcFunc['db_query']('', '
		SELECT id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	$context['current'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// The subscription must exist!
		if (!isset($context['subscriptions'][$row['id_subscribe']]))
			continue;

		$context['current'][$row['id_subscribe']] = array(
			'id' => $row['id_sublog'],
			'sub_id' => $row['id_subscribe'],
			'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
			'name' => $context['subscriptions'][$row['id_subscribe']]['name'],
			'start' => timeformat($row['start_time'], false),
			'end' => $row['end_time'] == 0 ? $txt['not_applicable'] : timeformat($row['end_time'], false),
			'pending_details' => $row['pending_details'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? $txt['paid_pending'] : $txt['paid_finished']) : $txt['paid_active'],
		);

		if ($row['status'] == 1)
			$context['subscriptions'][$row['id_subscribe']]['subscribed'] = true;
	}
	$smcFunc['db_free_result']($request);

	// Simple "done"?
	if (isset($_GET['done']))
	{
		$_GET['sub_id'] = (int) $_GET['sub_id'];

		// Must exist but let's be sure...
		if (isset($context['current'][$_GET['sub_id']]))
		{
			// What are the details like?
			$current_pending = @unserialize($context['current'][$_GET['sub_id']]['pending_details']);
			if (!empty($current_pending))
			{
				$current_pending = array_reverse($current_pending);
				foreach ($current_pending as $id => $sub)
				{
					// Just find one and change it.
					if ($sub[0] == $_GET['sub_id'] && $sub[3] == 'prepay')
					{
						$current_pending[$id][3] = 'payback';
						break;
					}
				}

				// Save the details back.
				$pending_details = serialize($current_pending);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_id}
						AND id_member = {int:selected_member}',
					array(
						'current_subscription_id' => $context['current'][$_GET['sub_id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}
		}

		$context['sub_template'] = 'paid_done';
		return;
	}
	// If this is confirmation then it's simpler...
	if (isset($_GET['confirm']) && isset($_POST['sub_id']) && is_array($_POST['sub_id']))
	{
		// Hopefully just one.
		foreach ($_POST['sub_id'] as $k => $v)
			$ID_SUB = (int) $k;

		if (!isset($context['subscriptions'][$ID_SUB]) || $context['subscriptions'][$ID_SUB]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		$context['sub'] = $context['subscriptions'][$ID_SUB];
		$period = 'xx';
		if ($context['sub']['flexible'])
			$period = isset($_POST['cur'][$ID_SUB]) && isset($context['sub']['costs'][$_POST['cur'][$ID_SUB]]) ? $_POST['cur'][$ID_SUB] : 'xx';

		// Check we have a valid cost.
		if ($context['sub']['flexible'] && $period == 'xx')
			fatal_lang_error('paid_sub_not_active');

		// Sort out the cost/currency.
		$context['currency'] = $modSettings['paid_currency_code'];
		$context['recur'] = $context['sub']['repeatable'];

		if ($context['sub']['flexible'])
		{
			// Real cost...
			$context['value'] = $context['sub']['costs'][$_POST['cur'][$ID_SUB]];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']) . '/' . $txt[$_POST['cur'][$ID_SUB]];
			// The period value for paypal.
			$context['paypal_period'] = strtoupper(substr($_POST['cur'][$ID_SUB], 0, 1));
		}
		else
		{
			// Real cost...
			$context['value'] = $context['sub']['costs']['fixed'];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']);

			// Recur?
			preg_match('~(\d*)(\w)~', $context['sub']['real_length'], $match);
			$context['paypal_unit'] = $match[1];
			$context['paypal_period'] = $match[2];
		}

		// Setup the gateway context.
		$context['gateways'] = array();
		foreach ($gateways as $id => $gateway)
		{
			$fields = $gateways[$id]->fetchGatewayFields($context['sub']['id'] . '+' . $memID, $context['sub'], $context['value'], $period, $scripturl . '?action=profile;u=' . $memID . ';area=subscriptions;sub_id=' . $context['sub']['id'] . ';done');
			if (!empty($fields['form']))
				$context['gateways'][] = $fields;
		}

		// Bugger?!
		if (empty($context['gateways']))
			fatal_error($txt['paid_admin_not_setup_gateway']);

		// Now we are going to assume they want to take this out ;)
		$new_data = array($context['sub']['id'], $context['value'], $period, 'prepay');
		if (isset($context['current'][$context['sub']['id']]))
		{
			// What are the details like?
			$current_pending = array();
			if ($context['current'][$context['sub']['id']]['pending_details'] != '')
				$current_pending = @unserialize($context['current'][$context['sub']['id']]['pending_details']);
			// Don't get silly.
			if (count($current_pending) > 9)
				$current_pending = array();
			$pending_count = 0;
			// Only record real pending payments as will otherwise confuse the admin!
			foreach ($current_pending as $pending)
				if ($pending[3] == 'payback')
					$pending_count++;

			if (!in_array($new_data, $current_pending))
			{
				$current_pending[] = $new_data;
				$pending_details = serialize($current_pending);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_item}
						AND id_member = {int:selected_member}',
					array(
						'pending_count' => $pending_count,
						'current_subscription_item' => $context['current'][$context['sub']['id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}

		}
		// Never had this before, lovely.
		else
		{
			$pending_details = serialize(array($new_data));
			$smcFunc['db_insert']('',
				'{db_prefix}log_subscribed',
				array(
					'id_subscribe' => 'int', 'id_member' => 'int', 'status' => 'int', 'payments_pending' => 'int', 'pending_details' => 'string-65534',
					'start_time' => 'int', 'vendor_ref' => 'string-255',
				),
				array(
					$context['sub']['id'], $memID, 0, 0, $pending_details,
					time(), '',
				),
				array('id_sublog')
			);
		}

		// Change the template.
		$context['sub_template'] = 'choose_payment';

		// Quit.
		return;
	}
	else
		$context['sub_template'] = 'user_subscription';
}

/**
 * Activate an account.
 * This function is called from the profile account actions area.
 *
 * @param int $memID the member ID
 */
function action_activateaccount($memID)
{
	global $context, $user_profile, $modSettings, $user_info;

	isAllowedTo('moderate_forum');

	if (isset($_REQUEST['save']) && isset($user_profile[$memID]['is_activated']) && $user_profile[$memID]['is_activated'] != 1)
	{
		// If we are approving the deletion of an account, we do something special ;)
		if ($user_profile[$memID]['is_activated'] == 4)
		{
			require_once(SUBSDIR . '/Members.subs.php');
			deleteMembers($context['id_member']);
			redirectexit();
		}

		// Let the integrations know of the activation.
		call_integration_hook('integrate_activate', array($user_profile[$memID]['member_name']));

		// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
		updateMemberData($context['id_member'], array('is_activated' => $user_profile[$memID]['is_activated'] >= 10 ? 11 : 1, 'validation_code' => ''));

		// Log what we did?
		require_once(SOURCEDIR . '/Logging.php');
		logAction('approve_member', array('member' => $memID), 'admin');

		// If we are doing approval, update the stats for the member just in case.
		if (in_array($user_profile[$memID]['is_activated'], array(3, 4, 13, 14)))
			updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > 1 ? $modSettings['unapprovedMembers'] - 1 : 0)));

		// Make sure we update the stats too.
		updateStats('member', false);
	}

	// Leave it be...
	redirectexit('action=profile;u=' . $memID . ';area=summary');
}