<?php

/**
 * Payment Gateway: TwoCheckOut
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Subscriptions\PaymentGateway\TwoCheckOut;

use ElkArte\Subscriptions\PaymentGateway\PaymentInterface;

/**
 * Class of functions to validate a twocheckout payment response and provide details of the payment
 *
 * @package Subscriptions
 */
class Payment implements PaymentInterface
{
	/**
	 * Validates that we have valid data to work with
	 *
	 * - Returns true/false for whether this gateway thinks the data is intended for it.
	 *
	 * @return boolean
	 */
	public function isValid()
	{
		global $modSettings;

		// Is it even on?
		if (empty($modSettings['2co_id']) || empty($modSettings['2co_password']))
			return false;

		// Is it a 2co hash?
		if (empty($_POST['x_MD5_Hash']))
			return false;

		// Do we have an invoice number?
		 return !empty($_POST['x_invoice_num']);
	}

	/**
	 * Validate this is valid for this transaction type.
	 *
	 * - If valid returns the subscription and member IDs we are going to process.
	 */
	public function precheck()
	{
		global $modSettings, $txt;

		// Is this the right hash?
		if (empty($modSettings['2co_password']) || $_POST['x_MD5_Hash'] !== strtoupper(md5($modSettings['2co_password'] . $modSettings['2co_id'] . $_POST['x_trans_id'] . $_POST['x_amount'])))
			generateSubscriptionError($txt['2co_password_wrong']);

		// Verify the currency
		list (, $currency) = explode(':', $_POST['x_invoice_num']);

		// Verify the currency!
		if (strtolower($currency) != $modSettings['currency_code'])
			exit;

		// Return the ID_SUB/ID_MEMBER
		return explode('+', $_POST['x_invoice_num']);
	}

	/**
	 * Returns whether this is this a refund
	 */
	public function isRefund()
	{
		return false;
	}

	/**
	 * Returns whether this is a subscription
	 */
	public function isSubscription()
	{
		return false;
	}

	/**
	 * Returns whether this is a normal payment
	 */
	public function isPayment()
	{
		// We have to assume so?
		return true;
	}

	/**
	 * Returns if is this is a cancellation transaction
	 *
	 * @return boolean
	 */
	public function isCancellation()
	{
		return false;
	}

	/**
	 * Returns the cost
	 */
	public function getCost()
	{
		return $_POST['x_amount'];
	}

	/**
	 * Redirect the user away.
	 *
	 * @param int $subscription_id
	 */
	public function close($subscription_id)
	{
		exit();
	}
}
