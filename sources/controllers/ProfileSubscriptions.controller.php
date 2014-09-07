<?php

/**
 * Handles paid subscriptions on a user's profile.
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
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class handles the paid subscriptions on a user's profile.
 */
class ProfileSubscriptions_Controller extends Action_Controller
{
	/**
	 * Default action for the controller
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// $this->action_subscriptions();
	}

	/**
	 * Method for doing all the paid subscription stuff - kinda.
	 */
	public function action_subscriptions()
	{
		global $context, $txt;

		// Load the paid template anyway.
		loadTemplate('ManagePaid');
		loadLanguage('ManagePaid');

		$memID = currentMemberID();
		$context['member']['id'] = $memID;

		// Load all of the subscriptions in the system
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		loadSubscriptions();

		// Remove any invalid ones, ones not properly set up
		foreach ($context['subscriptions'] as $id => $sub)
		{
			// Work out the costs.
			$costs = @unserialize($sub['real_cost']);

			$cost_array = array();

			// Flexible cost to time?
			if ($sub['real_length'] == 'F')
			{
				foreach ($costs as $duration => $cost)
					if ($cost != 0)
						$cost_array[$duration] = $cost;
			}
			else
				$cost_array['fixed'] = $costs['fixed'];

			// No cost associated with it, then drop it
			if (empty($cost_array))
				unset($context['subscriptions'][$id]);
			else
			{
				$context['subscriptions'][$id]['member'] = 0;
				$context['subscriptions'][$id]['subscribed'] = false;
				$context['subscriptions'][$id]['costs'] = $cost_array;
			}
		}

		// Work out what payment gateways are enabled.
		$gateways = loadPaymentGateways();
		foreach ($gateways as $id => $gateway)
		{
			$gateways[$id] = new $gateway['display_class']();

			if (!$gateways[$id]->gatewayEnabled())
				unset($gateways[$id]);
		}

		// No gateways yet, no way to pay then!
		if (empty($gateways))
			fatal_error($txt['paid_admin_not_setup_gateway']);

		// Get the members current subscriptions.
		$context['current'] = loadMemberSubscriptions($memID, $context['subscriptions']);

		// Find the active subscribed ones
		foreach ($context['current'] as $id => $current)
		{
			if ($current['status'] == 1)
				$context['subscriptions'][$id]['subscribed'] = true;
		}

		// Simple "done"?
		if (isset($_GET['done']))
			$this->_orderDone($memID);
		// They have selected a subscription to order.
		elseif (isset($_GET['confirm']) && isset($_POST['sub_id']) && is_array($_POST['sub_id']))
			$this->_confirmOrder($gateways, $memID);
		// Show the users whats available and what they have
		else
			$context['sub_template'] = 'user_subscription';
	}

	/**
	 * Called when the user selects Order from the subscription page
	 * accessed with ?action=profile;u=123;area=subscriptions;confirm
	 *
	 * @param mixed[] $gateways array of available payment gateway objects
	 * @param int $memID
	 */
	private function _confirmOrder($gateways, $memID)
	{
		global $context, $modSettings, $scripturl, $txt;

		// Hopefully just one, if not we use the last one.
		foreach ($_POST['sub_id'] as $k => $v)
			$id_sub = (int) $k;

		// Selecting a subscription that does not exist or is not active?
		if (!isset($context['subscriptions'][$id_sub]) || $context['subscriptions'][$id_sub]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		$order = $context['subscriptions'][$id_sub];

		$period = 'xx';
		if ($order['flexible'])
			$period = isset($_POST['cur'][$id_sub]) && isset($order['costs'][$_POST['cur'][$id_sub]]) ? $_POST['cur'][$id_sub] : 'xx';

		// Check we have a valid cost.
		if ($order['flexible'] && $period == 'xx')
			fatal_lang_error('paid_sub_not_active');

		// Sort out the cost/currency.
		$context['currency'] = $modSettings['paid_currency_code'];
		$context['recur'] = $order['repeatable'];

		// Payment details based on one time or flex
		if ($order['flexible'])
		{
			// Real cost...
			$context['value'] = $order['costs'][$_POST['cur'][$id_sub]];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']) . '/' . $txt[$_POST['cur'][$id_sub]];

			// The period value for paypal.
			$context['paypal_period'] = strtoupper(substr($_POST['cur'][$id_sub], 0, 1));
		}
		else
		{
			// Real cost...
			$context['value'] = $order['costs']['fixed'];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']);

			// Recur?
			preg_match('~(\d*)(\w)~', $order['real_length'], $match);
			$context['paypal_unit'] = $match[1];
			$context['paypal_period'] = $match[2];
		}

		// Setup the all the payment gateway context.
		$context['gateways'] = array();
		foreach ($gateways as $id => $gateway)
		{
			$fields = $gateways[$id]->fetchGatewayFields($order['id'] . '+' . $memID, $order, $context['value'], $period, $scripturl . '?action=profile&u=' . $memID . '&area=subscriptions&sub_id=' . $order['id'] . '&done');
			if (!empty($fields['form']))
			{
				$context['gateways'][] = $fields;

				// Does this gateway have any javascript?
				if (!empty($fields['javascript']))
					addInlineJavascript($fields['javascript'], true);
			}
		}

		// No active payment gateways, then no way to pay, time to bail out, blame the admin
		if (empty($context['gateways']))
			fatal_error($txt['paid_admin_not_setup_gateway']);

		// Now we are going to assume they want to take this out ;)
		$new_data = array($order['id'], $context['value'], $period, 'prepay');

		// They have one of these already?
		if (isset($context['current'][$order['id']]))
		{
			// What are the details like?
			$current_pending = array();
			if ($context['current'][$order['id']]['pending_details'] != '')
				$current_pending = @unserialize($context['current'][$order['id']]['pending_details']);

			// Don't get silly.
			if (count($current_pending) > 9)
				$current_pending = array();

			// Only record real pending payments as will otherwise confuse the admin!
			$pending_count = 0;
			foreach ($current_pending as $pending)
			{
				if ($pending[3] == 'payback')
					$pending_count++;
			}

			// If its already pending, don't increase the pending count
			if (!in_array($new_data, $current_pending))
			{
				$current_pending[] = $new_data;
				$pending_details = serialize($current_pending);
				updatePendingSubscriptionCount($pending_count, $context['current'][$order['id']]['id'], $memID, $pending_details);
			}
		}
		// Never had this before, lovely.
		else
		{
			$pending_details = serialize(array($new_data));
			logNewSubscription($order['id'], $memID, $pending_details);
		}

		// Change the template.
		$context['sub'] = $order;
		$context['sub_template'] = 'choose_payment';
	}

	/**
	 * When the chosen payment gateway is done and it supports a receipt link url
	 * it will be set to come here.  This is NOT the same as the notify processing url
	 * which will point to subscriptions.php
	 *
	 * ?action=profile;u=123;area=subscriptions;sub_id=?;done
	 *
	 * @param int $memID
	 */
	private function _orderDone($memID)
	{
		global $context;

		$sub_id = (int) $_GET['sub_id'];

		// Must exist but let's be sure...
		if (isset($context['current'][$sub_id]))
		{
			// What are the pending details?
			$current_pending = @unserialize($context['current'][$sub_id]['pending_details']);

			// Nothing pending, nothing to do
			if (!empty($current_pending))
			{
				$current_pending = array_reverse($current_pending);
				foreach ($current_pending as $id => $sub)
				{
					// Just find one and change it to payback
					if ($sub[0] == $sub_id && $sub[3] == 'prepay')
					{
						$current_pending[$id][3] = 'payback';
						break;
					}
				}

				// Save the details back.
				$pending_details = serialize($current_pending);
				updatePendingStatus($context['current'][$sub_id]['id'], $memID, $pending_details);
			}
		}

		// A simple thank you
		$context['sub_template'] = 'paid_done';
	}
}