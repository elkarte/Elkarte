<?php

/**
 * Handles paid subscriptions on a user's profile.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * ProfileSubscriptions_Controller Class
 * This class handles the paid subscriptions on a user's profile.
 */
class ProfileSubscriptions_Controller extends Action_Controller
{
	/**
	 * Holds the the details of the subscription order
	 * @var
	 */
	private $_order;

	/**
	 * Holds all the available gateways so they can be initialized
	 * @var array
	 */
	private $_gateways;

	/**
	 * The id of the subscription
	 * @var int
	 */
	private $_id_sub;

	/**
	 * Default action for the controller
	 *
	 * - This is just a stub as action_subscriptions is called from a menu pick
	 * and not routed through this method.
	 *
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

		// Load all of the subscriptions in the system (loads in to $context)
		require_once(SUBSDIR . '/PaidSubscriptions.subs.php');
		loadSubscriptions();

		// Remove any invalid ones, ones not properly set up
		$this->_remove_invalid();

		// Work out what payment gateways are enabled.
		$this->_gateways = loadPaymentGateways();
		foreach ($this->_gateways as $id => $gateway)
		{
			$this->_gateways[$id] = new $gateway['display_class']();

			if (!$this->_gateways[$id]->gatewayEnabled())
				unset($this->_gateways[$id]);
		}

		// No gateways yet, no way to pay then, blame the admin !
		if (empty($this->_gateways))
			throw new Elk_Exception($txt['paid_admin_not_setup_gateway']);

		// Get the members current subscriptions.
		$context['current'] = loadMemberSubscriptions($memID, $context['subscriptions']);

		// Find the active subscribed ones
		foreach ($context['current'] as $id => $current)
		{
			if ($current['status'] == 1)
				$context['subscriptions'][$id]['subscribed'] = true;
		}

		// Simple "done"?
		if (isset($this->_req->query->done))
			$this->_orderDone($memID);
		// They have selected a subscription to order.
		elseif (isset($this->_req->query->confirm) && isset($this->_req->post->sub_id) && is_array($this->_req->post->sub_id))
			$this->_confirmOrder($memID);
		// Show the users whats available and what they have
		else
			$context['sub_template'] = 'user_subscription';
	}

	/**
	 * Removes any subscriptions that are found to be invalid
	 *
	 * - Invalid defined by missing cost or missing period
	 */
	private function _remove_invalid()
	{
		global $context;

		foreach ($context['subscriptions'] as $id => $sub)
		{
			// Work out the costs.
			$costs = Util::unserialize($sub['real_cost']);

			$cost_array = array();

			// Flexible cost to time?
			if ($sub['real_length'] === 'F')
			{
				foreach ($costs as $duration => $cost)
				{
					if ($cost != 0)
					{
						$cost_array[$duration] = $cost;
					}
				}
			}
			else
			{
				$cost_array['fixed'] = $costs['fixed'];
			}

			// No cost associated with it, then drop it
			if (empty($cost_array))
			{
				unset($context['subscriptions'][$id]);
			}
			else
			{
				$context['subscriptions'][$id]['member'] = 0;
				$context['subscriptions'][$id]['subscribed'] = false;
				$context['subscriptions'][$id]['costs'] = $cost_array;
			}
		}
	}

	/**
	 * When the chosen payment gateway is done and it supports a receipt link url
	 * it will be set to come here.
	 *
	 * - This is NOT the same as the notify processing url which will point to subscriptions.php
	 * - Accessed by ?action=profile;u=123;area=subscriptions;sub_id=?;done
	 *
	 * @param int $memID
	 */
	private function _orderDone($memID)
	{
		global $context;

		$sub_id = (int) $this->_req->query->sub_id;

		// Must exist but let's be sure...
		if (isset($context['current'][$sub_id]))
		{
			// What are the pending details?
			$current_pending = Util::unserialize($context['current'][$sub_id]['pending_details']);

			// Nothing pending, nothing to do
			if (!empty($current_pending))
			{
				$current_pending = array_reverse($current_pending);
				foreach ($current_pending as $id => $sub)
				{
					// Just find one and change it to payback
					if ($sub[0] == $sub_id && trim($sub[3]) === 'prepay')
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

	/**
	 * Called when the user selects "Order" from the subscription page
	 *
	 * - Accessed with ?action=profile;u=123;area=subscriptions;confirm
	 *
	 * @param int $memID The id of the member who is ordering
	 */
	private function _confirmOrder($memID)
	{
		global $context, $modSettings, $txt;

		// Hopefully just one, if not we use the last one.
		foreach ($this->_req->post->sub_id as $k => $v)
			$this->_id_sub = (int) $k;

		// Selecting a subscription that does not exist or is not active?
		if (!isset($this->_id_sub, $context['subscriptions'][$this->_id_sub]) || $context['subscriptions'][$this->_id_sub]['active'] == 0)
		{
			throw new Elk_Exception('paid_sub_not_active');
		}

		// Simplify...
		$this->_order = $context['subscriptions'][$this->_id_sub];

		// Set the period in case this is a flex time frame.
		$period = 'xx';
		if ($this->_order['flexible'])
		{
			$period = isset($this->_req->post->cur[$this->_id_sub]) && isset($this->_order['costs'][$this->_req->post->cur[$this->_id_sub]]) ? $this->_req->post->cur[$this->_id_sub] : 'xx';
		}

		// Check we have a valid cost.
		if ($this->_order['flexible'] && $period === 'xx')
		{
			throw new Elk_Exception('paid_sub_not_active');
		}

		// Sort out the cost/currency.
		$context['currency'] = $modSettings['paid_currency_code'];
		$context['recur'] = $this->_order['repeatable'];

		// Payment details based on one time or flex
		$this->_set_value_cost_context();

		// Setup the all the payment gateway context.
		$this->_set_payment_gatway_context($memID, $period);

		// No active payment gateways, then no way to pay, time to bail out, blame the admin
		if (empty($context['gateways']))
			throw new Elk_Exception($txt['paid_admin_not_setup_gateway']);

		// Now we are going to assume they want to take this out ;)
		$new_data = array($this->_order['id'], $context['value'], $period, 'prepay');

		// They have one of these already?
		if (isset($context['current'][$this->_order['id']]))
		{
			// What are the details like?
			$current_pending = array();
			if ($context['current'][$this->_order['id']]['pending_details'] != '')
				$current_pending = Util::unserialize($context['current'][$this->_order['id']]['pending_details']);

			// Don't get silly.
			if (count($current_pending) > 9)
				$current_pending = array();

			// Only record real pending payments as will otherwise confuse the admin!
			$pending_count = 0;
			foreach ($current_pending as $pending)
			{
				if (trim($pending[3]) === 'payback')
					$pending_count++;
			}

			// If its already pending, don't increase the pending count
			if (!in_array($new_data, $current_pending))
			{
				$current_pending[] = $new_data;
				$pending_details = serialize($current_pending);
				updatePendingSubscriptionCount($pending_count, $context['current'][$this->_order['id']]['id'], $memID, $pending_details);
			}
		}
		// Never had this before, lovely.
		else
		{
			$pending_details = serialize(array($new_data));
			logNewSubscription($this->_order['id'], $memID, $pending_details);
		}

		// Change the template.
		$context['sub'] = $this->_order;
		$context['sub_template'] = 'choose_payment';
	}

	/**
	 * Sets the value/cost/period/unit of the chosen order for use in templates
	 */
	private function _set_value_cost_context()
	{
		global $context, $modSettings, $txt;

		if ($this->_order['flexible'])
		{
			// Real cost...
			$context['value'] = $this->_order['costs'][$this->_req->post->cur[$this->_id_sub]];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']) . '/' . $txt[$this->_req->post->cur[$this->_id_sub]];

			// The period value for paypal.
			$context['paypal_period'] = strtoupper(substr($this->_req->post->cur[$this->_id_sub], 0, 1));
		}
		else
		{
			// Real cost...
			$context['value'] = $this->_order['costs']['fixed'];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']);

			// Recur?
			preg_match('~(\d*)(\w)~', $this->_order['real_length'], $match);
			$context['paypal_unit'] = $match[1];
			$context['paypal_period'] = $match[2];
		}
	}

	/**
	 * Sets the required payment form fields for the various payment gateways
	 *
	 * @param int $memID The id of the member who is ordering
	 * @param string period xx for none or a value of time
	 */
	private function _set_payment_gatway_context($memID, $period)
	{
		global $context, $scripturl;

		$context['gateways'] = array();
		foreach ($this->_gateways as $id => $gateway)
		{
			$fields = $this->_gateways[$id]->fetchGatewayFields($this->_order['id'] . '+' . $memID, $this->_order, $context['value'], $period, $scripturl . '?action=profile&u=' . $memID . '&area=subscriptions&sub_id=' . $this->_order['id'] . '&done');

			if (!empty($fields['form']))
			{
				$context['gateways'][] = $fields;

				// Does this gateway have any javascript?
				if (!empty($fields['javascript']))
				{
					addInlineJavascript($fields['javascript'], true);
				}
			}
		}
	}
}